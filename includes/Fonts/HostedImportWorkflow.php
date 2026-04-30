<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

/**
 * Shared workflow for hosted provider imports.
 *
 * @phpstan-type HostedFamily array<string, mixed>
 * @phpstan-type HostedProfile array<string, mixed>
 * @phpstan-type HostedFace array<string, mixed>
 * @phpstan-type HostedVariantList list<string>
 * @phpstan-type HostedVariantPlan array{import: HostedVariantList, skipped: HostedVariantList}
 * @phpstan-type HostedManifestResult array{faces: list<HostedFace>, files: int}
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
    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly AssetService $assets,
        private readonly LogRepository $log,
        private readonly HostedImportVariantPlanner $variantPlanner
    ) {}

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
        $formatMode = $provider->normalizeFormatMode($request->formatMode);
        $requestedVariants = FontUtils::normalizeVariantTokens($request->variants);
        $existingFamily = $this->imports->getFamily($familySlug);
        $existingProfile = FontUtils::findDeliveryProfile(
            $existingFamily,
            $provider->providerKey(),
            $deliveryMode,
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
                $deliveryMode,
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
            'delivery_mode' => $deliveryMode,
            'format_mode' => $formatMode,
            'metadata' => $metadata,
            'existing_family' => $existingFamily,
            'existing_profile' => $existingProfile,
            'imported_variants' => $variantPlan['import'],
        ]);
        $profile = FontUtils::normalizeStringKeyedMap($draft['profile']);
        $faceProvider = FontUtils::normalizeStringKeyedMap($draft['face_provider']);

        $result = $deliveryMode === 'cdn'
            ? $this->saveCdnProfile($familyName, $familySlug, $faces, $variantPlan, $existingFamily, $existingProfile, $profile, $faceProvider, $config)
            : $this->saveSelfHostedProfile($familyName, $familySlug, $faces, $variantPlan, $existingFamily, $existingProfile, $profile, $faceProvider, $config);

        return $this->completeImport($result, $provider->providerKey());
    }

    private function normalizeDeliveryMode(string $deliveryMode): string
    {
        $deliveryMode = strtolower(trim($deliveryMode));

        return in_array($deliveryMode, ['self_hosted', 'cdn'], true) ? $deliveryMode : 'self_hosted';
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
     * @return string|WP_Error
     */
    private function resolveImportTarget(string $familySlug, HostedImportProviderConfig $config): string|WP_Error
    {
        $root = $this->getImportRootDirectory($config);

        if (is_wp_error($root)) {
            return $root;
        }

        $familyDirectory = trailingslashit($root) . $familySlug;

        if (!$this->storage->ensureDirectory($familyDirectory)) {
            return $this->error(
                $config->familyDirErrorCode,
                $this->storageErrorMessage($config->familyDirErrorMessage)
            );
        }

        return $familyDirectory;
    }

    private function getImportRootDirectory(HostedImportProviderConfig $config): string|WP_Error
    {
        if (!$this->storage->ensureRootDirectory()) {
            return $this->error(
                'tasty_fonts_storage_unavailable',
                $this->storageErrorMessage(__('The uploads/fonts storage directory could not be created.', 'tasty-fonts'))
            );
        }

        $providerRoot = $this->storage->getProviderRoot($config->providerRoot);

        if (!$providerRoot) {
            return $this->error(
                'tasty_fonts_storage_unavailable',
                $this->storageErrorMessage(__('The uploads/fonts storage directory could not be created.', 'tasty-fonts'))
            );
        }

        return $providerRoot;
    }

    /**
     * @param HostedFace $face
     * @param array<string, mixed> $provider
     * @return HostedFace|WP_Error|null
     */
    private function buildManifestFace(
        string $familyName,
        string $familySlug,
        string $familyDirectory,
        array $face,
        array $provider,
        int &$downloadedFiles,
        HostedImportProviderConfig $config
    ): array|WP_Error|null {
        $relativeFiles = [];

        foreach ($this->arrayValue($face, 'files') as $format => $url) {
            if ($format !== 'woff2') {
                continue;
            }

            $filename = HostedImportSupport::buildLocalFilename($familyName, $face);
            $absolutePath = wp_normalize_path(trailingslashit($familyDirectory) . $filename);
            $relativePath = $this->storage->relativePath($absolutePath);

            if (is_file($absolutePath) && filesize($absolutePath) > 0) {
                $relativeFiles[$format] = $relativePath;
                continue;
            }

            $download = $this->downloadFontFile(FontUtils::scalarStringValue($url), $absolutePath, $config);

            if (is_wp_error($download)) {
                return $download;
            }

            $relativeFiles[$format] = $relativePath;
            $downloadedFiles++;
        }

        if ($relativeFiles === []) {
            return null;
        }

        return $this->buildStoredFace($familyName, $familySlug, $face, $relativeFiles, $provider, $config->source);
    }

    /**
     * @param list<HostedFace> $faces
     * @param array<string, mixed> $provider
     * @return list<HostedFace>
     */
    private function buildCdnFaces(string $familyName, string $familySlug, array $faces, array $provider, string $source): array
    {
        $cdnFaces = [];

        foreach ($faces as $face) {
            $cdnFaces[] = $this->buildStoredFace($familyName, $familySlug, $face, FontUtils::normalizeStringMap($face['files'] ?? []), $provider, $source);
        }

        return $cdnFaces;
    }

    /**
     * @param HostedFace $face
     * @param array<string, string> $files
     * @param array<string, mixed> $provider
     * @return HostedFace
     */
    private function buildStoredFace(
        string $familyName,
        string $familySlug,
        array $face,
        array $files,
        array $provider,
        string $source
    ): array {
        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'source' => $source,
            'weight' => FontUtils::stringValue($face, 'weight', '400'),
            'style' => FontUtils::stringValue($face, 'style', 'normal'),
            'unicode_range' => FontUtils::stringValue($face, 'unicode_range'),
            'files' => $files,
            'provider' => $provider,
            'is_variable' => !empty($face['is_variable']),
            'axes' => $this->arrayValue($face, 'axes'),
            'variation_defaults' => $this->arrayValue($face, 'variation_defaults'),
        ];
    }

    /**
     * @param list<HostedFace> $faces
     * @param array<string, mixed> $provider
     * @return HostedManifestResult|WP_Error
     */
    private function buildManifestFaces(
        string $familyName,
        string $familySlug,
        string $familyDirectory,
        array $faces,
        array $provider,
        HostedImportProviderConfig $config
    ): array|WP_Error {
        $manifestFaces = [];
        $downloadedFiles = 0;

        foreach ($faces as $face) {
            $manifestFace = $this->buildManifestFace(
                $familyName,
                $familySlug,
                $familyDirectory,
                $face,
                $provider,
                $downloadedFiles,
                $config
            );

            if (is_wp_error($manifestFace)) {
                return $manifestFace;
            }

            if ($manifestFace !== null) {
                $manifestFaces[] = $manifestFace;
            }
        }

        return [
            'faces' => $manifestFaces,
            'files' => $downloadedFiles,
        ];
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

    /**
     * @param list<HostedFace> $faces
     * @param HostedVariantPlan $variantPlan
     * @param HostedFamily|null $existingFamily
     * @param HostedProfile|null $existingProfile
     * @param HostedProfile $profile
     * @param array<string, mixed> $provider
     * @return HostedImportResult|WP_Error
     */
    private function saveSelfHostedProfile(
        string $familyName,
        string $familySlug,
        array $faces,
        array $variantPlan,
        ?array $existingFamily,
        ?array $existingProfile,
        array $profile,
        array $provider,
        HostedImportProviderConfig $config
    ): array|WP_Error {
        $familyDirectory = $this->resolveImportTarget($familySlug, $config);

        if (is_wp_error($familyDirectory)) {
            return $familyDirectory;
        }

        $manifest = $this->buildManifestFaces($familyName, $familySlug, $familyDirectory, $faces, $provider, $config);

        if (is_wp_error($manifest)) {
            return $manifest;
        }

        $manifestFaces = $manifest['faces'];

        if ($manifestFaces === []) {
            return $this->error($config->emptyManifestCode, $config->emptyManifestMessage);
        }

        $persisted = $this->persistProfile(
            $familyName,
            $familySlug,
            $profile + ['faces' => $manifestFaces],
            $existingFamily,
            $existingProfile,
            $variantPlan['import']
        );

        $faceCount = count($manifestFaces);
        $fileCount = $this->intValue($manifest, 'files');
        $message = $this->buildImportMessageWithFiles(
            $config->selfHostedSuccessMessage,
            $familyName,
            $faceCount,
            $fileCount,
            count($variantPlan['skipped'])
        );

        return $this->finalizeImportResult(
            'imported',
            $message,
            $familyName,
            $this->arrayValue($persisted, 'family_record'),
            FontUtils::stringValue($profile, 'type', 'self_hosted'),
            FontUtils::stringValue($profile, 'id'),
            $faceCount,
            $fileCount,
            $persisted['variants'],
            $variantPlan
        );
    }

    /**
     * @param list<HostedFace> $faces
     * @param HostedVariantPlan $variantPlan
     * @param HostedFamily|null $existingFamily
     * @param HostedProfile|null $existingProfile
     * @param HostedProfile $profile
     * @param array<string, mixed> $provider
     * @return HostedImportResult
     */
    private function saveCdnProfile(
        string $familyName,
        string $familySlug,
        array $faces,
        array $variantPlan,
        ?array $existingFamily,
        ?array $existingProfile,
        array $profile,
        array $provider,
        HostedImportProviderConfig $config
    ): array {
        $cdnFaces = $this->buildCdnFaces($familyName, $familySlug, $faces, $provider, $config->source);
        $persisted = $this->persistProfile(
            $familyName,
            $familySlug,
            $profile + ['faces' => $cdnFaces],
            $existingFamily,
            $existingProfile,
            $variantPlan['import']
        );

        $faceCount = count($cdnFaces);
        $message = $this->buildImportMessageWithoutFiles(
            $config->cdnSuccessMessage,
            $familyName,
            $faceCount,
            count($variantPlan['skipped'])
        );

        return $this->finalizeImportResult(
            'saved',
            $message,
            $familyName,
            $this->arrayValue($persisted, 'family_record'),
            FontUtils::stringValue($profile, 'type', 'cdn'),
            FontUtils::stringValue($profile, 'id'),
            $faceCount,
            0,
            $persisted['variants'],
            $variantPlan
        );
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

    private function downloadFontFile(string $url, string $targetPath, HostedImportProviderConfig $config): bool|WP_Error
    {
        $validated = $this->validateRemoteFontUrl($url, $config);

        if (is_wp_error($validated)) {
            return $validated;
        }

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 30,
                'headers' => [
                    'Accept' => 'font/woff2,*/*;q=0.1',
                    'User-Agent' => FontUtils::MODERN_USER_AGENT,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return $this->error($this->normalizeErrorCode($response->get_error_code()), $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            return $this->error(
                $config->downloadFailedCode,
                sprintf($config->downloadFailedMessage, $status)
            );
        }

        $body = wp_remote_retrieve_body($response);

        if ($body === '') {
            return $this->error($config->emptyFileCode, $config->emptyFileMessage);
        }

        if (strlen($body) > $config->maxFontFileBytes) {
            return $this->error($config->fileTooLargeCode, $config->fileTooLargeMessage);
        }

        $contentType = strtolower($this->normalizeHeaderValue(wp_remote_retrieve_header($response, 'content-type')));

        if (
            $contentType !== ''
            && !str_contains($contentType, 'woff2')
            && !str_contains($contentType, 'font')
            && !str_contains($contentType, 'octet-stream')
        ) {
            return $this->error($config->invalidTypeCode, $config->invalidTypeMessage);
        }

        if (!$this->storage->writeAbsoluteFile($targetPath, $body)) {
            return $this->error(
                $config->writeFailedCode,
                $this->storageErrorMessage($config->writeFailedMessage)
            );
        }

        return true;
    }

    private function validateRemoteFontUrl(string $url, HostedImportProviderConfig $config): bool|WP_Error
    {
        $parts = FontUtils::normalizeStringKeyedMap(wp_parse_url($url));
        $host = strtolower(FontUtils::stringValue($parts, 'host'));
        $path = strtolower(FontUtils::stringValue($parts, 'path'));

        if ($host !== strtolower($config->expectedHost)) {
            return $this->error($config->invalidHostCode, $config->invalidHostMessage);
        }

        if (!str_ends_with($path, '.woff2')) {
            return $this->error($config->invalidExtensionCode, $config->invalidExtensionMessage);
        }

        return true;
    }

    private function storageErrorMessage(string $fallback): string
    {
        $message = trim($this->storage->getLastFilesystemErrorMessage());

        return $message !== '' ? $message : $fallback;
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

    private function normalizeHeaderValue(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (!is_array($value)) {
            return '';
        }

        foreach ($value as $entry) {
            if (is_scalar($entry)) {
                return trim((string) $entry);
            }
        }

        return '';
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

    /**
     * @param array<int|string, mixed> $values
     */
    private function intValue(array $values, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        return FontUtils::scalarIntValue($values[$key], $default);
    }
}
