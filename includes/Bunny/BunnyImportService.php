<?php

declare(strict_types=1);

namespace TastyFonts\Bunny;

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

final class BunnyImportService
{
    use HostedProviderImportTrait;

    private const MAX_FONT_FILE_BYTES = 10 * MB_IN_BYTES;

    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly BunnyFontsClient $client,
        private readonly BunnyCssParser $parser,
        CatalogService $catalog,
        private readonly AssetService $assets,
        private readonly LogRepository $log
    ) {
        unset($catalog);
    }

    /**
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
        string $deliveryMode = 'self_hosted'
    ): array|WP_Error {
        $familyName = trim(wp_strip_all_tags($familyName));

        if ($familyName === '') {
            return $this->error('tasty_fonts_missing_family', __('Choose a Bunny Fonts family before importing.', 'tasty-fonts'));
        }

        $familySlug = FontUtils::slugify($familyName);
        $deliveryMode = $this->normalizeDeliveryMode($deliveryMode);
        $requestedVariants = FontUtils::normalizeVariantTokens($variants);
        $existingFamily = $this->imports->getFamily($familySlug);
        $existingProfile = $this->findDeliveryProfile($existingFamily, 'bunny', $deliveryMode);
        $variantPlan = $this->buildVariantPlan($requestedVariants, $existingProfile);

        if ($variantPlan['import'] === []) {
            return $this->buildHostedSkippedImportResult(
                $familyName,
                $deliveryMode,
                $requestedVariants,
                $variantPlan,
                [
                    'cdn' => __('Bunny CDN delivery for %s already includes the selected variants.', 'tasty-fonts'),
                    'existing' => __('%s already exists in the library for the selected variants.', 'tasty-fonts'),
                ]
            );
        }

        $metadata = $this->client->getFamily($familyName);
        $css = $this->client->fetchCss($familyName, $variantPlan['import']);

        if (is_wp_error($css)) {
            return $this->error($css->get_error_code(), $css->get_error_message());
        }

        $faces = $this->selectHostedImportFaces(
            $familyName,
            $css,
            $variantPlan['import'],
            [$this->parser, 'parse'],
            'tasty_fonts_bunny_no_faces',
            __('No usable Bunny Fonts faces were returned for that family.', 'tasty-fonts')
        );

        if (is_wp_error($faces)) {
            return $faces;
        }

        $result = $deliveryMode === 'cdn'
            ? $this->saveCdnProfile($familyName, $familySlug, $faces, $metadata, $variantPlan, $existingFamily, $existingProfile)
            : $this->saveSelfHostedProfile($familyName, $familySlug, $faces, $metadata, $variantPlan, $existingFamily, $existingProfile);

        return $this->completeHostedImport($result, 'bunny');
    }

    private function saveSelfHostedProfile(
        string $familyName,
        string $familySlug,
        array $faces,
        ?array $metadata,
        array $variantPlan,
        ?array $existingFamily,
        ?array $existingProfile
    ): array|WP_Error {
        $profileId = $this->resolveProfileId($existingFamily, 'self_hosted');
        return $this->saveHostedSelfHostedProfile(
            $familyName,
            $familySlug,
            $faces,
            $variantPlan,
            $existingFamily,
            $existingProfile,
            [
                'id' => $profileId,
                'provider' => 'bunny',
                'provider_face' => $this->buildProviderMetadata($metadata, $variantPlan['import']),
                'type' => 'self_hosted',
                'format' => 'static',
                'label' => __('Self-hosted (Bunny import)', 'tasty-fonts'),
                'meta' => [
                    'category' => (string) ($metadata['category'] ?? ''),
                    'imported_at' => current_time('mysql'),
                ],
            ],
            $this->providerConfig(),
            'tasty_fonts_bunny_empty_manifest',
            __('No local font files were saved from that import.', 'tasty-fonts'),
            __('Added %1$s as a self-hosted Bunny delivery (%2$d variant%3$s, %4$d file%5$s).', 'tasty-fonts')
        );
    }

    private function saveCdnProfile(
        string $familyName,
        string $familySlug,
        array $faces,
        ?array $metadata,
        array $variantPlan,
        ?array $existingFamily,
        ?array $existingProfile
    ): array|WP_Error {
        $profileId = $this->resolveProfileId($existingFamily, 'cdn');
        return $this->saveHostedCdnProfile(
            $familyName,
            $familySlug,
            $faces,
            $variantPlan,
            $existingFamily,
            $existingProfile,
            [
                'id' => $profileId,
                'provider' => 'bunny',
                'provider_face' => $this->buildProviderMetadata($metadata, $variantPlan['import']),
                'type' => 'cdn',
                'format' => 'static',
                'label' => __('Bunny CDN', 'tasty-fonts'),
                'meta' => [
                    'category' => (string) ($metadata['category'] ?? ''),
                    'saved_at' => current_time('mysql'),
                ],
            ],
            $this->providerConfig(),
            __('Added %1$s as a Bunny CDN delivery (%2$d variant%3$s).', 'tasty-fonts')
        );
    }

    private function normalizeDeliveryMode(string $deliveryMode): string
    {
        return $this->normalizeHostedDeliveryMode($deliveryMode);
    }

    private function findDeliveryProfile(?array $family, string $provider, string $type): ?array
    {
        return $this->findHostedDeliveryProfile($family, $provider, $type);
    }

    private function buildProviderMetadata(?array $metadata, array $variants): array
    {
        return [
            'type' => 'bunny',
            'category' => (string) ($metadata['category'] ?? ''),
            'variants' => $variants,
        ];
    }

    private function buildVariantPlan(array $requestedVariants, ?array $existingProfile): array
    {
        return $this->buildHostedVariantPlan($requestedVariants, $existingProfile);
    }

    private function profileId(string $deliveryMode): string
    {
        return FontUtils::slugify('bunny-' . $deliveryMode);
    }

    private function resolveProfileId(?array $family, string $deliveryMode): string
    {
        $existing = $this->findDeliveryProfile($family, 'bunny', $deliveryMode);

        if (is_array($existing) && trim((string) ($existing['id'] ?? '')) !== '') {
            return (string) $existing['id'];
        }

        return $this->profileId($deliveryMode);
    }

    private function providerConfig(): array
    {
        return [
            'provider_root' => 'bunny',
            'source' => 'bunny',
            'family_dir_error_code' => 'tasty_fonts_bunny_family_dir_failed',
            'family_dir_error_message' => __('The Bunny Fonts import directory could not be created.', 'tasty-fonts'),
            'expected_host' => 'fonts.bunny.net',
            'invalid_host_code' => 'tasty_fonts_bunny_invalid_host',
            'invalid_host_message' => __('Bunny font downloads must come from fonts.bunny.net.', 'tasty-fonts'),
            'invalid_extension_code' => 'tasty_fonts_bunny_invalid_extension',
            'invalid_extension_message' => __('Only WOFF2 files can be imported from Bunny Fonts.', 'tasty-fonts'),
            'download_failed_code' => 'tasty_fonts_bunny_download_failed',
            'empty_file_code' => 'tasty_fonts_bunny_empty_file',
            'empty_file_message' => __('Bunny Fonts returned an empty font file.', 'tasty-fonts'),
            'file_too_large_code' => 'tasty_fonts_bunny_file_too_large',
            'invalid_type_code' => 'tasty_fonts_bunny_invalid_type',
            'invalid_type_message' => __('The downloaded file was not returned as a WOFF2 font.', 'tasty-fonts'),
            'write_failed_code' => 'tasty_fonts_bunny_write_failed',
            'write_failed_message' => __('The imported font file could not be written to uploads/fonts.', 'tasty-fonts'),
        ];
    }
}
