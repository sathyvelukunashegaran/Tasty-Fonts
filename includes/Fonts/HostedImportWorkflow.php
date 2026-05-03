<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\FontUtils;
use WP_Error;

/**
 * Shared workflow for hosted provider imports.
 *
 * @phpstan-type HostedFamily array<string, mixed>
 * @phpstan-type HostedProfile array<string, mixed>
 * @phpstan-type HostedFace array<string, mixed>
 * @phpstan-type HostedVariantList list<string>
 * @phpstan-type HostedVariantPlan array{import: HostedVariantList, skipped: HostedVariantList}
 * @phpstan-type HostedPersistResult array{family_record: array<string, mixed>, variants: HostedVariantList, faces: list<HostedFace>}
 * @phpstan-type HostedImportResult array{
 *     status: string,
 *     message: string,
 *     family: string,
 *     delivery_type: string,
 *     faces: int,
 *     files: int,
 *     variants: HostedVariantList,
 *     imported_variants: HostedVariantList,
 *     skipped_variants: HostedVariantList,
 *     family_record?: array<string, mixed>,
 *     delivery_id?: string
 * }
 */
final class HostedImportWorkflow
{
    /**
     * @param array<string, DeliveryImportStrategy> $strategies
     */
    public function __construct(
        private readonly ImportRepository $imports,
        private readonly AssetService $assets,
        private readonly LogRepository $log,
        private readonly HostedImportVariantPlanner $variantPlanner,
        private readonly array $strategies
    ) {
        if ($this->strategies === []) {
            throw new \InvalidArgumentException('Strategy map must include at least one DeliveryImportStrategy.');
        }
    }

    /**
     * @return HostedImportResult|WP_Error
     */
    public function import(
        HostedImportRequest $request,
        HostedImportProviderAdapterInterface $provider
    ): array|WP_Error {
        $config = $provider->config();
        $familyName = trim(wp_strip_all_tags($request->familyName));

        if ($familyName === '') {
            return $this->error($config->missingFamilyCode, $config->missingFamilyMessage);
        }

        $familySlug = FontUtils::slugify($familyName);
        $deliveryMode = $this->normalizeDeliveryMode($request->deliveryMode);
        $strategySelection = $this->selectStrategy($deliveryMode);

        if (is_wp_error($strategySelection)) {
            return $strategySelection;
        }

        $effectiveDeliveryMode = $strategySelection['mode'];
        $strategy = $strategySelection['strategy'];
        $formatMode = $provider->normalizeFormatMode($request->formatMode);
        $requestedVariants = FontUtils::normalizeVariantTokens($request->variants);
        $existingFamily = $this->imports->getFamily($familySlug);
        $existingProfile = FontUtils::findDeliveryProfile(
            $existingFamily,
            $provider->providerKey(),
            $effectiveDeliveryMode,
            $provider->profileFormatFilter($formatMode)
        );
        $variantPlan = $this->variantPlanner->plan(
            $requestedVariants,
            $existingProfile,
            static fn (array $variants): array => $provider->normalizeRequestedVariants($variants, $formatMode)
        );

        if ($variantPlan['import'] === []) {
            return $this->buildSkippedImportResult(
                $familyName,
                $effectiveDeliveryMode,
                $requestedVariants,
                $variantPlan,
                $config
            );
        }

        $metadata = $provider->fetchMetadata($familyName);
        $css = $provider->fetchCss($familyName, $variantPlan['import'], $metadata, $formatMode);

        if (is_wp_error($css)) {
            return $this->error($this->normalizeErrorCode($css->get_error_code()), $css->get_error_message());
        }

        $faces = $this->selectImportFaces($familyName, $css, $variantPlan['import'], $provider, $config);

        if (is_wp_error($faces)) {
            return $faces;
        }

        $draft = $provider->buildProfileDraft([
            'family_name' => $familyName,
            'family_slug' => $familySlug,
            'delivery_mode' => $effectiveDeliveryMode,
            'format_mode' => $formatMode,
            'metadata' => $metadata,
            'existing_family' => $existingFamily,
            'existing_profile' => $existingProfile,
            'imported_variants' => $variantPlan['import'],
        ]);
        $profile = FontUtils::normalizeStringKeyedMap($draft['profile']);
        $faceProvider = FontUtils::normalizeStringKeyedMap($draft['face_provider']);

        $faceResult = $strategy->importFaces($familyName, $familySlug, $faces, $faceProvider, $config);

        if (is_wp_error($faceResult)) {
            return $this->error($this->normalizeErrorCode($faceResult->get_error_code()), $faceResult->get_error_message());
        }

        if ($faceResult->faces === []) {
            return $this->error($config->emptyManifestCode, $config->emptyManifestMessage);
        }

        $persisted = $this->persistProfile(
            $familyName,
            $familySlug,
            $profile + ['faces' => $faceResult->faces],
            $existingFamily,
            $existingProfile,
            $variantPlan['import']
        );

        $faceCount = count($faceResult->faces);
        $fileCount = $faceResult->files;

        if ($effectiveDeliveryMode === 'cdn') {
            $message = $this->buildImportMessageWithoutFiles(
                $config->cdnSuccessMessage,
                $familyName,
                $faceCount,
                count($variantPlan['skipped'])
            );
            $status = 'saved';
        } else {
            $message = $this->buildImportMessageWithFiles(
                $config->selfHostedSuccessMessage,
                $familyName,
                $faceCount,
                $fileCount,
                count($variantPlan['skipped'])
            );
            $status = 'imported';
        }

        return $this->completeImport(
            $this->finalizeImportResult(
                $status,
                $message,
                $familyName,
                $this->arrayValue($persisted, 'family_record'),
                FontUtils::stringValue($profile, 'type', $effectiveDeliveryMode),
                FontUtils::stringValue($profile, 'id'),
                $faceCount,
                $fileCount,
                $persisted['variants'],
                $variantPlan
            ),
            $provider->providerKey()
        );
    }

    private function normalizeDeliveryMode(string $deliveryMode): string
    {
        $deliveryMode = strtolower(trim($deliveryMode));

        return $deliveryMode === '' ? 'self_hosted' : $deliveryMode;
    }

    /**
     * @return array{mode: string, strategy: DeliveryImportStrategy}|WP_Error
     */
    private function selectStrategy(string $deliveryMode): array|WP_Error
    {
        if (isset($this->strategies[$deliveryMode])) {
            $strategy = $this->strategies[$deliveryMode];

            if ($strategy->supports($deliveryMode)) {
                return [
                    'mode' => $deliveryMode,
                    'strategy' => $strategy,
                ];
            }
        }

        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($deliveryMode)) {
                return [
                    'mode' => $deliveryMode,
                    'strategy' => $strategy,
                ];
            }
        }

        if (isset($this->strategies['self_hosted'])) {
            $fallback = $this->strategies['self_hosted'];

            if ($fallback->supports('self_hosted')) {
                return [
                    'mode' => 'self_hosted',
                    'strategy' => $fallback,
                ];
            }
        }

        return $this->error(
            'tasty_fonts_delivery_strategy_unavailable',
            sprintf(
                __('No import strategy is available for delivery mode "%s".', 'tasty-fonts'),
                $deliveryMode
            )
        );
    }

    /**
     * @param HostedVariantList $requestedVariants
     * @param HostedVariantPlan $variantPlan
     * @return HostedImportResult
     */
    private function buildSkippedImportResult(
        string $familyName,
        string $deliveryMode,
        array $requestedVariants,
        array $variantPlan,
        HostedImportProviderConfig $config
    ): array {
        $message = $deliveryMode === 'cdn'
            ? sprintf($config->skippedCdnMessage, $familyName)
            : sprintf($config->skippedExistingMessage, $familyName);

        $this->log->add($message, [
            'category' => LogRepository::CATEGORY_IMPORT,
            'event' => 'hosted_import_skipped',
            'outcome' => 'info',
            'status_label' => __('Skipped', 'tasty-fonts'),
            'source' => __('Import', 'tasty-fonts'),
            'entity_type' => 'font_family',
            'entity_name' => $familyName,
            'details' => [
                ['label' => __('Family', 'tasty-fonts'), 'value' => $familyName],
                ['label' => __('Delivery type', 'tasty-fonts'), 'value' => $deliveryMode],
                ['label' => __('Requested variants', 'tasty-fonts'), 'value' => implode(', ', $requestedVariants)],
                ['label' => __('Skipped variants', 'tasty-fonts'), 'value' => implode(', ', $variantPlan['skipped'])],
            ],
        ]);

        return [
            'status' => 'skipped',
            'message' => $message,
            'family' => $familyName,
            'delivery_type' => $deliveryMode,
            'faces' => 0,
            'files' => 0,
            'variants' => $requestedVariants,
            'imported_variants' => [],
            'skipped_variants' => $variantPlan['skipped'],
        ];
    }

    /**
     * @param HostedVariantList $requestedVariants
     * @return list<HostedFace>|WP_Error
     */
    private function selectImportFaces(
        string $familyName,
        string $css,
        array $requestedVariants,
        HostedImportProviderAdapterInterface $provider,
        HostedImportProviderConfig $config
    ): array|WP_Error {
        $faces = HostedImportSupport::selectPreferredFaces(
            FontUtils::normalizeFaceList($provider->parseFaces($css, $familyName)),
            $requestedVariants
        );

        if ($faces === []) {
            return $this->error($config->noFacesCode, $config->noFacesMessage);
        }

        return $faces;
    }

    /**
     * @param HostedProfile $profile
     * @param HostedFamily|null $existingFamily
     * @param HostedProfile|null $existingProfile
     * @param HostedVariantList $importedVariants
     * @return HostedPersistResult
     */
    private function persistProfile(
        string $familyName,
        string $familySlug,
        array $profile,
        ?array $existingFamily,
        ?array $existingProfile,
        array $importedVariants
    ): array {
        $profile['faces'] = HostedImportSupport::mergeManifestFaces(
            FontUtils::normalizeFaceList($existingProfile['faces'] ?? []),
            FontUtils::normalizeFaceList($profile['faces'] ?? [])
        );
        $profile['variants'] = array_values(
            array_unique(
                array_merge(
                    FontUtils::normalizeVariantTokens($this->stringList($existingProfile['variants'] ?? [])),
                    $importedVariants
                )
            )
        );

        $savedFamily = $this->imports->saveProfile(
            $familyName,
            $familySlug,
            $profile,
            $existingFamily === null ? 'library_only' : FontUtils::stringValue($existingFamily, 'publish_state', 'published'),
            $existingFamily === null
        );

        return [
            'family_record' => $savedFamily,
            'variants' => $profile['variants'],
            'faces' => $profile['faces'],
        ];
    }

    private function buildImportMessageWithFiles(
        string $template,
        string $familyName,
        int $faceCount,
        int $downloadedFiles,
        int $skipCount
    ): string {
        $message = sprintf(
            $template,
            $familyName,
            $faceCount,
            $faceCount === 1 ? '' : 's',
            $downloadedFiles,
            $downloadedFiles === 1 ? '' : 's'
        );

        if ($skipCount > 0) {
            $message .= ' ' . sprintf(
                __('%d variant%s already existed in this delivery profile.', 'tasty-fonts'),
                $skipCount,
                $skipCount === 1 ? '' : 's'
            );
        }

        return $message;
    }

    private function buildImportMessageWithoutFiles(
        string $template,
        string $familyName,
        int $faceCount,
        int $skipCount
    ): string {
        $message = sprintf(
            $template,
            $familyName,
            $faceCount,
            $faceCount === 1 ? '' : 's'
        );

        if ($skipCount > 0) {
            $message .= ' ' . sprintf(
                __('%d variant%s already existed in this delivery profile.', 'tasty-fonts'),
                $skipCount,
                $skipCount === 1 ? '' : 's'
            );
        }

        return $message;
    }

    /**
     * @param array<string, mixed> $savedFamily
     * @param HostedVariantList $variants
     * @param HostedVariantPlan $variantPlan
     * @return HostedImportResult
     */
    private function finalizeImportResult(
        string $status,
        string $message,
        string $familyName,
        array $savedFamily,
        string $deliveryType,
        string $deliveryId,
        int $faceCount,
        int $fileCount,
        array $variants,
        array $variantPlan
    ): array {
        $this->log->add($message, [
            'category' => LogRepository::CATEGORY_IMPORT,
            'event' => $status === 'imported' ? 'hosted_import_imported' : 'hosted_import_saved',
            'outcome' => 'success',
            'status_label' => $status === 'imported' ? __('Imported', 'tasty-fonts') : __('Saved', 'tasty-fonts'),
            'source' => __('Import', 'tasty-fonts'),
            'entity_type' => 'font_family',
            'entity_id' => $deliveryId,
            'entity_name' => $familyName,
            'details' => [
                ['label' => __('Family', 'tasty-fonts'), 'value' => $familyName],
                ['label' => __('Delivery type', 'tasty-fonts'), 'value' => $deliveryType],
                ['label' => __('Faces', 'tasty-fonts'), 'value' => (string) $faceCount, 'kind' => 'count'],
                ['label' => __('Files', 'tasty-fonts'), 'value' => (string) $fileCount, 'kind' => 'count'],
                ['label' => __('Imported variants', 'tasty-fonts'), 'value' => $variantPlan['import'] === [] ? __('None', 'tasty-fonts') : implode(', ', $variantPlan['import'])],
                ['label' => __('Skipped variants', 'tasty-fonts'), 'value' => $variantPlan['skipped'] === [] ? __('None', 'tasty-fonts') : implode(', ', $variantPlan['skipped'])],
            ],
        ]);

        return [
            'status' => $status,
            'message' => $message,
            'family' => $familyName,
            'family_record' => $savedFamily,
            'delivery_type' => $deliveryType,
            'delivery_id' => $deliveryId,
            'faces' => $faceCount,
            'files' => $fileCount,
            'variants' => $variants,
            'imported_variants' => $variantPlan['import'],
            'skipped_variants' => $variantPlan['skipped'],
        ];
    }

    /**
     * @param HostedImportResult|WP_Error $result
     * @return HostedImportResult|WP_Error
     */
    private function completeImport(array|WP_Error $result, string $provider): array|WP_Error
    {
        if (is_wp_error($result)) {
            return $result;
        }

        $this->assets->refreshGeneratedAssets();
        do_action('tasty_fonts_after_import', $result, $provider);

        return $result;
    }

    private function error(string $code, string $message): WP_Error
    {
        $this->log->add($message, [
            'category' => LogRepository::CATEGORY_IMPORT,
            'event' => 'hosted_import_failed',
            'outcome' => 'error',
            'status_label' => __('Failed', 'tasty-fonts'),
            'source' => __('Import', 'tasty-fonts'),
            'error_code' => $code,
            'details' => [
                ['label' => __('Failure code', 'tasty-fonts'), 'value' => $code],
                ['label' => __('Reason', 'tasty-fonts'), 'value' => $message],
            ],
        ]);

        return new WP_Error($code, $message);
    }

    private function normalizeErrorCode(int|string $code): string
    {
        return is_int($code) ? (string) $code : $code;
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<string, mixed>
     */
    private function arrayValue(array $values, string $key): array
    {
        return FontUtils::normalizeStringKeyedMap($values[$key] ?? []);
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            $normalizedValue = FontUtils::scalarStringValue($value);

            if ($normalizedValue === '') {
                continue;
            }

            $normalized[] = $normalizedValue;
        }

        return $normalized;
    }

}
