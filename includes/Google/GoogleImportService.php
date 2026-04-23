<?php

declare(strict_types=1);

namespace TastyFonts\Google;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\HostedImportSupport;
use TastyFonts\Fonts\HostedProviderImportTrait;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

/**
 * @phpstan-type HostedFamily array<string, mixed>
 * @phpstan-type HostedProfile array<string, mixed>
 * @phpstan-type HostedFace array<string, mixed>
 * @phpstan-type ProviderMetadata array<string, mixed>
 * @phpstan-type VariantPlan array{import: list<string>, skipped: list<string>, format_mode: string}
 * @phpstan-type ProviderConfig array<string, string>
 * @phpstan-type ImportResult array{
 *     status: string,
 *     message: string,
 *     family: string,
 *     delivery_type: string,
 *     faces: int,
 *     files: int,
 *     variants: list<string>,
 *     imported_variants: list<string>,
 *     skipped_variants: list<string>,
 *     family_record?: array<string, mixed>,
 *     delivery_id?: string
 * }
 */
final class GoogleImportService
{
    use HostedProviderImportTrait;

    private const MAX_FONT_FILE_BYTES = 10 * MB_IN_BYTES;

    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly GoogleFontsClient $client,
        private readonly GoogleCssParser $parser,
        CatalogService $catalog,
        private readonly AssetService $assets,
        private readonly LogRepository $log
    ) {
        unset($catalog);
    }

    /**
     * @param list<string> $variants
     * @return array{
     *     status: string,
     *     message: string,
     *     family: string,
     *     delivery_type: string,
     *     faces: int,
     *     files: int,
     *     variants: list<string>,
     *     imported_variants: list<string>,
     *     skipped_variants: list<string>,
     *     family_record?: array<string, mixed>,
     *     delivery_id?: string
     * }|WP_Error
     */
    public function importFamily(
        string $familyName,
        array $variants,
        string $deliveryMode = 'self_hosted',
        string $formatMode = 'static'
    ): array|WP_Error {
        $familyName = trim(wp_strip_all_tags($familyName));

        if ($familyName === '') {
            return $this->error('tasty_fonts_missing_family', __('Choose a Google Fonts family before importing.', 'tasty-fonts'));
        }

        $familySlug = FontUtils::slugify($familyName);
        $deliveryMode = $this->normalizeDeliveryMode($deliveryMode);
        $formatMode = $this->normalizeFormatMode($formatMode);
        $requestedVariants = FontUtils::normalizeVariantTokens($variants);
        $existingFamily = $this->imports->getFamily($familySlug);
        $existingProfile = $this->findDeliveryProfile($existingFamily, 'google', $deliveryMode, $formatMode);
        $variantPlan = $this->buildVariantPlan($requestedVariants, $existingProfile, $formatMode);

        if ($variantPlan['import'] === []) {
            return $this->buildHostedSkippedImportResult(
                $familyName,
                $deliveryMode,
                $requestedVariants,
                $variantPlan,
                [
                    'cdn' => __('Google CDN delivery for %s already includes the selected variants.', 'tasty-fonts'),
                    'existing' => __('%s already exists in the library for the selected variants.', 'tasty-fonts'),
                ]
            );
        }

        $metadata = $this->client->getFamily($familyName);
        $css = $this->client->fetchCss(
            $familyName,
            $variantPlan['import'],
            'swap',
            is_array($metadata) ? $metadata : [],
            $formatMode
        );

        if (is_wp_error($css)) {
            return $this->error($this->normalizeHostedErrorCode($css->get_error_code()), $css->get_error_message());
        }

        $faces = $this->selectHostedImportFaces(
            $familyName,
            $css,
            $variantPlan['import'],
            [$this->parser, 'parse'],
            'tasty_fonts_google_no_faces',
            __('No usable Google Fonts faces were returned for that family.', 'tasty-fonts')
        );

        if (is_wp_error($faces)) {
            return $faces;
        }

        $result = $deliveryMode === 'cdn'
            ? $this->saveCdnProfile($familyName, $familySlug, $faces, $metadata, $variantPlan, $existingFamily, $existingProfile)
            : $this->saveSelfHostedProfile($familyName, $familySlug, $faces, $metadata, $variantPlan, $existingFamily, $existingProfile);

        return $this->completeHostedImport($result, 'google');
    }

    /**
     * @param list<HostedFace> $faces
     * @param ProviderMetadata|null $metadata
     * @param VariantPlan $variantPlan
     * @param HostedFamily|null $existingFamily
     * @param HostedProfile|null $existingProfile
     * @return ImportResult|WP_Error
     */
    private function saveSelfHostedProfile(
        string $familyName,
        string $familySlug,
        array $faces,
        ?array $metadata,
        array $variantPlan,
        ?array $existingFamily,
        ?array $existingProfile
    ): array|WP_Error {
        $profileId = $this->resolveProfileId($existingFamily, 'self_hosted', $variantPlan['format_mode']);
        return $this->saveHostedSelfHostedProfile(
            $familyName,
            $familySlug,
            $faces,
            $variantPlan,
            $existingFamily,
            $existingProfile,
            [
                'id' => $profileId,
                'provider' => 'google',
                'provider_face' => $this->buildProviderMetadata($metadata, $variantPlan['import']),
                'type' => 'self_hosted',
                'format' => $variantPlan['format_mode'],
                'label' => __('Self-hosted (Google import)', 'tasty-fonts'),
                'meta' => [
                    'category' => (string) ($metadata['category'] ?? ''),
                    'lastModified' => (string) ($metadata['lastModified'] ?? ''),
                    'version' => (string) ($metadata['version'] ?? ''),
                    'imported_at' => current_time('mysql'),
                ],
            ],
            $this->providerConfig(),
            'tasty_fonts_google_empty_manifest',
            __('No local font files were saved from that import.', 'tasty-fonts'),
            __('Added %1$s as a self-hosted Google delivery (%2$d variant%3$s, %4$d file%5$s).', 'tasty-fonts')
        );
    }

    /**
     * @param list<HostedFace> $faces
     * @param ProviderMetadata|null $metadata
     * @param VariantPlan $variantPlan
     * @param HostedFamily|null $existingFamily
     * @param HostedProfile|null $existingProfile
     * @return ImportResult|WP_Error
     */
    private function saveCdnProfile(
        string $familyName,
        string $familySlug,
        array $faces,
        ?array $metadata,
        array $variantPlan,
        ?array $existingFamily,
        ?array $existingProfile
    ): array|WP_Error {
        $profileId = $this->resolveProfileId($existingFamily, 'cdn', $variantPlan['format_mode']);
        return $this->saveHostedCdnProfile(
            $familyName,
            $familySlug,
            $faces,
            $variantPlan,
            $existingFamily,
            $existingProfile,
            [
                'id' => $profileId,
                'provider' => 'google',
                'provider_face' => $this->buildProviderMetadata($metadata, $variantPlan['import']),
                'type' => 'cdn',
                'format' => $variantPlan['format_mode'],
                'label' => __('Google CDN', 'tasty-fonts'),
                'meta' => [
                    'category' => (string) ($metadata['category'] ?? ''),
                    'lastModified' => (string) ($metadata['lastModified'] ?? ''),
                    'version' => (string) ($metadata['version'] ?? ''),
                    'saved_at' => current_time('mysql'),
                ],
            ],
            $this->providerConfig(),
            __('Added %1$s as a Google CDN delivery (%2$d variant%3$s).', 'tasty-fonts')
        );
    }

    private function normalizeDeliveryMode(string $deliveryMode): string
    {
        return $this->normalizeHostedDeliveryMode($deliveryMode);
    }

    private function normalizeFormatMode(string $formatMode): string
    {
        $formatMode = strtolower(trim($formatMode));

        return $formatMode === 'variable' ? 'variable' : 'static';
    }

    /**
     * @param HostedFamily|null $family
     * @return HostedProfile|null
     */
    private function findDeliveryProfile(?array $family, string $provider, string $type, string $formatMode = 'static'): ?array
    {
        return $this->findHostedDeliveryProfile($family, $provider, $type, $formatMode);
    }

    private function profileId(string $deliveryMode): string
    {
        return FontUtils::slugify('google-' . $deliveryMode);
    }

    /**
     * @param HostedFamily|null $family
     */
    private function resolveProfileId(?array $family, string $deliveryMode, string $formatMode): string
    {
        $existing = $this->findDeliveryProfile($family, 'google', $deliveryMode, $formatMode);

        if (is_array($existing) && trim((string) ($existing['id'] ?? '')) !== '') {
            return (string) $existing['id'];
        }

        $baseId = $this->profileId($deliveryMode);

        if (!$this->findLegacyProfileConflict($family, 'google', $deliveryMode)) {
            return $baseId;
        }

        return FontUtils::slugify($baseId . '-' . $formatMode);
    }

    /**
     * @param HostedFamily|null $family
     */
    private function findLegacyProfileConflict(?array $family, string $provider, string $type): bool
    {
        return $this->findHostedDeliveryProfile($family, $provider, $type) !== null;
    }

    /**
     * @param ProviderMetadata|null $metadata
     * @param list<string> $variants
     * @return ProviderMetadata
     */
    private function buildProviderMetadata(?array $metadata, array $variants): array
    {
        return [
            'type' => 'google',
            'category' => (string) ($metadata['category'] ?? ''),
            'variants' => $variants,
            'lastModified' => (string) ($metadata['lastModified'] ?? ''),
            'version' => (string) ($metadata['version'] ?? ''),
        ];
    }

    /**
     * @param list<string> $requestedVariants
     * @param HostedProfile|null $existingProfile
     * @return VariantPlan
     */
    private function buildVariantPlan(array $requestedVariants, ?array $existingProfile, string $formatMode = 'static'): array
    {
        $variantPlan = $this->buildHostedVariantPlan(
            $requestedVariants,
            $existingProfile,
            $formatMode === 'variable' ? [$this, 'normalizeVariableRequestVariants'] : null
        );
        $variantPlan['format_mode'] = $formatMode;

        return $variantPlan;
    }

    /**
     * @param list<string> $requestedVariants
     * @return list<string>
     */
    private function normalizeVariableRequestVariants(array $requestedVariants): array
    {
        $styles = [];

        foreach ($requestedVariants as $variant) {
            $axis = FontUtils::googleVariantToAxis((string) $variant);

            if ($axis === null) {
                continue;
            }

            $style = $axis['style'] === 'italic' ? 'italic' : 'normal';
            $styles[$style] = $style === 'italic' ? 'italic' : 'regular';
        }

        if ($styles === []) {
            return ['regular'];
        }

        return array_values($styles);
    }

    /**
     * @return ProviderConfig
     */
    private function providerConfig(): array
    {
        return [
            'provider_root' => 'google',
            'source' => 'google',
            'family_dir_error_code' => 'tasty_fonts_google_family_dir_failed',
            'family_dir_error_message' => __('The Google Fonts import directory could not be created.', 'tasty-fonts'),
            'expected_host' => 'fonts.gstatic.com',
            'invalid_host_code' => 'tasty_fonts_google_invalid_host',
            'invalid_host_message' => __('Google font downloads must come from fonts.gstatic.com.', 'tasty-fonts'),
            'invalid_extension_code' => 'tasty_fonts_google_invalid_extension',
            'invalid_extension_message' => __('Only WOFF2 files can be imported from Google Fonts.', 'tasty-fonts'),
            'download_failed_code' => 'tasty_fonts_google_download_failed',
            'empty_file_code' => 'tasty_fonts_google_empty_file',
            'empty_file_message' => __('Google Fonts returned an empty font file.', 'tasty-fonts'),
            'file_too_large_code' => 'tasty_fonts_google_file_too_large',
            'invalid_type_code' => 'tasty_fonts_google_invalid_type',
            'invalid_type_message' => __('The downloaded file was not returned as a WOFF2 font.', 'tasty-fonts'),
            'write_failed_code' => 'tasty_fonts_google_write_failed',
            'write_failed_message' => __('The imported font file could not be written to uploads/fonts.', 'tasty-fonts'),
        ];
    }
}
