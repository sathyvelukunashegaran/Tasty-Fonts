<?php

declare(strict_types=1);

namespace TastyFonts\Bunny;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\HostedImportSupport;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

final class BunnyImportService
{
    private const MAX_FONT_FILE_BYTES = 10 * MB_IN_BYTES;

    /**
     * Create the Bunny import service.
     *
     * @since 1.4.0
     *
     * @param Storage $storage Storage abstraction for downloaded font files.
     * @param ImportRepository $imports Repository used to persist delivery profiles.
     * @param BunnyFontsClient $client Bunny catalog and CSS client.
     * @param BunnyCssParser $parser CSS parser for Bunny-hosted @font-face rules.
     * @param CatalogService $catalog Catalog service used to inspect existing families.
     * @param AssetService $assets Asset service used to refresh generated CSS after imports.
     * @param LogRepository $log Log repository used for import audit entries.
     */
    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly BunnyFontsClient $client,
        private readonly BunnyCssParser $parser,
        private readonly CatalogService $catalog,
        private readonly AssetService $assets,
        private readonly LogRepository $log
    ) {
    }

    /**
     * Import or update a Bunny Fonts family in the library.
     *
     * @since 1.4.0
     *
     * @param string $familyName Bunny Fonts family name.
     * @param array<int, string> $variants Variant tokens to import, such as `regular` or `700italic`.
     * @param string $deliveryMode Delivery mode to save (`self_hosted` or `cdn`).
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
     * }|WP_Error Import result payload, or a WordPress error when the import cannot proceed.
     */
    public function importFamily(
        string $familyName,
        array $variants,
        string $deliveryMode = 'self_hosted'
    ): array|WP_Error
    {
        $familyName = trim(wp_strip_all_tags($familyName));

        if ($familyName === '') {
            return $this->error('tasty_fonts_missing_family', __('Choose a Bunny Fonts family before importing.', 'tasty-fonts'));
        }

        $familySlug = FontUtils::slugify($familyName);
        $deliveryMode = $this->normalizeDeliveryMode($deliveryMode);

        $normalizedVariants = FontUtils::normalizeVariantTokens($variants);
        $requestedVariants = $normalizedVariants === [] ? ['regular'] : $normalizedVariants;
        $existingFamily = $this->imports->getFamily($familySlug);
        $existingProfile = $this->findDeliveryProfile($existingFamily, 'bunny', $deliveryMode);
        $variantPlan = $this->buildVariantPlan($requestedVariants, $existingProfile);

        if ($variantPlan['import'] === []) {
            $message = $deliveryMode === 'cdn'
                ? sprintf(__('Bunny CDN delivery for %s already includes the selected variants.', 'tasty-fonts'), $familyName)
                : sprintf(__('%s already exists in the library for the selected variants.', 'tasty-fonts'), $familyName);

            $this->log->add($message);

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

        $metadata = $this->client->getFamily($familyName);
        $css = $this->client->fetchCss($familyName, $variantPlan['import']);

        if (is_wp_error($css)) {
            return $this->error($css->get_error_code(), $css->get_error_message());
        }

        $faces = HostedImportSupport::selectPreferredFaces(
            $this->parser->parse($css, $familyName),
            $variantPlan['import']
        );

        if ($faces === []) {
            return $this->error(
                'tasty_fonts_bunny_no_faces',
                __('No usable Bunny Fonts faces were returned for that family.', 'tasty-fonts')
            );
        }

        $result = $deliveryMode === 'cdn'
            ? $this->saveCdnProfile($familyName, $familySlug, $faces, $metadata, $variantPlan, $existingFamily, $existingProfile)
            : $this->saveSelfHostedProfile($familyName, $familySlug, $faces, $metadata, $variantPlan, $existingFamily, $existingProfile);

        if (is_wp_error($result)) {
            return $result;
        }

        $this->assets->refreshGeneratedAssets();
        do_action('tasty_fonts_after_import', $result, 'bunny');

        return $result;
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
        $familyDirectory = $this->resolveImportTarget($familySlug);

        if (is_wp_error($familyDirectory)) {
            return $familyDirectory;
        }

        $provider = $this->buildProviderMetadata($metadata, $variantPlan['import']);
        $newManifestFaces = [];
        $downloadedFiles = 0;
        $profileId = $this->resolveProfileId($existingFamily, 'self_hosted');

        foreach ($faces as $face) {
            $manifestFace = $this->buildManifestFace(
                $familyName,
                $familySlug,
                $familyDirectory,
                $face,
                $provider,
                $downloadedFiles
            );

            if (is_wp_error($manifestFace)) {
                return $manifestFace;
            }

            if ($manifestFace !== null) {
                $newManifestFaces[] = $manifestFace;
            }
        }

        if ($newManifestFaces === []) {
            return $this->error(
                'tasty_fonts_bunny_empty_manifest',
                __('No local font files were saved from that import.', 'tasty-fonts')
            );
        }

        $mergedFaces = HostedImportSupport::mergeManifestFaces(
            is_array($existingProfile['faces'] ?? null) ? (array) $existingProfile['faces'] : [],
            $newManifestFaces
        );
        $allVariants = array_values(
            array_unique(
                array_merge(
                    is_array($existingProfile['variants'] ?? null) ? (array) $existingProfile['variants'] : [],
                    $variantPlan['import']
                )
            )
        );

        $savedFamily = $this->imports->saveProfile(
            $familyName,
            $familySlug,
            [
                'id' => $profileId,
                'provider' => 'bunny',
                'type' => 'self_hosted',
                'format' => 'static',
                'label' => __('Self-hosted (Bunny import)', 'tasty-fonts'),
                'variants' => $allVariants,
                'faces' => $mergedFaces,
                'meta' => [
                    'category' => (string) ($metadata['category'] ?? ''),
                    'imported_at' => current_time('mysql'),
                ],
            ],
            $existingFamily === null ? 'library_only' : (string) ($existingFamily['publish_state'] ?? 'published'),
            $existingFamily === null
        );

        $faceCount = count($newManifestFaces);
        $skipCount = count($variantPlan['skipped']);
        $message = sprintf(
            __('Added %1$s as a self-hosted Bunny delivery (%2$d variant%3$s, %4$d file%5$s).', 'tasty-fonts'),
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

        $this->log->add($message);

        return [
            'status' => 'imported',
            'message' => $message,
            'family' => $familyName,
            'family_record' => $savedFamily,
            'delivery_type' => 'self_hosted',
            'delivery_id' => $profileId,
            'faces' => $faceCount,
            'files' => $downloadedFiles,
            'variants' => $allVariants,
            'imported_variants' => $variantPlan['import'],
            'skipped_variants' => $variantPlan['skipped'],
        ];
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
        $provider = $this->buildProviderMetadata($metadata, $variantPlan['import']);
        $cdnFaces = [];
        $profileId = $this->resolveProfileId($existingFamily, 'cdn');

        foreach ($faces as $face) {
            if (!is_array($face)) {
                continue;
            }

            $cdnFaces[] = [
                'family' => $familyName,
                'slug' => $familySlug,
                'source' => 'bunny',
                'weight' => (string) ($face['weight'] ?? '400'),
                'style' => (string) ($face['style'] ?? 'normal'),
                'unicode_range' => (string) ($face['unicode_range'] ?? ''),
                'files' => (array) ($face['files'] ?? []),
                'provider' => $provider,
                'is_variable' => !empty($face['is_variable']),
                'axes' => (array) ($face['axes'] ?? []),
                'variation_defaults' => (array) ($face['variation_defaults'] ?? []),
            ];
        }

        $mergedFaces = HostedImportSupport::mergeManifestFaces(
            is_array($existingProfile['faces'] ?? null) ? (array) $existingProfile['faces'] : [],
            $cdnFaces
        );
        $allVariants = array_values(
            array_unique(
                array_merge(
                    is_array($existingProfile['variants'] ?? null) ? (array) $existingProfile['variants'] : [],
                    $variantPlan['import']
                )
            )
        );

        $savedFamily = $this->imports->saveProfile(
            $familyName,
            $familySlug,
            [
                'id' => $profileId,
                'provider' => 'bunny',
                'type' => 'cdn',
                'format' => 'static',
                'label' => __('Bunny CDN', 'tasty-fonts'),
                'variants' => $allVariants,
                'faces' => $mergedFaces,
                'meta' => [
                    'category' => (string) ($metadata['category'] ?? ''),
                    'saved_at' => current_time('mysql'),
                ],
            ],
            $existingFamily === null ? 'library_only' : (string) ($existingFamily['publish_state'] ?? 'published'),
            $existingFamily === null
        );

        $faceCount = count($cdnFaces);
        $skipCount = count($variantPlan['skipped']);
        $message = sprintf(
            __('Added %1$s as a Bunny CDN delivery (%2$d variant%3$s).', 'tasty-fonts'),
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

        $this->log->add($message);

        return [
            'status' => 'saved',
            'message' => $message,
            'family' => $familyName,
            'family_record' => $savedFamily,
            'delivery_type' => 'cdn',
            'delivery_id' => $profileId,
            'faces' => $faceCount,
            'files' => 0,
            'variants' => $allVariants,
            'imported_variants' => $variantPlan['import'],
            'skipped_variants' => $variantPlan['skipped'],
        ];
    }

    private function resolveImportTarget(string $familySlug): string|WP_Error
    {
        $bunnyRoot = $this->getBunnyRootDirectory();

        if (is_wp_error($bunnyRoot)) {
            return $bunnyRoot;
        }

        $familyDirectory = trailingslashit($bunnyRoot) . $familySlug;

        if (!$this->storage->ensureDirectory($familyDirectory)) {
            return $this->error(
                'tasty_fonts_bunny_family_dir_failed',
                $this->storageErrorMessage(__('The Bunny Fonts import directory could not be created.', 'tasty-fonts'))
            );
        }

        return $familyDirectory;
    }

    private function getBunnyRootDirectory(): string|WP_Error
    {
        if (!$this->storage->ensureRootDirectory()) {
            return $this->error(
                'tasty_fonts_storage_unavailable',
                $this->storageErrorMessage(__('The uploads/fonts storage directory could not be created.', 'tasty-fonts'))
            );
        }

        $bunnyRoot = $this->storage->getProviderRoot('bunny');

        if (!$bunnyRoot) {
            return $this->error(
                'tasty_fonts_storage_unavailable',
                $this->storageErrorMessage(__('The uploads/fonts storage directory could not be created.', 'tasty-fonts'))
            );
        }

        return $bunnyRoot;
    }

    private function buildManifestFace(
        string $familyName,
        string $familySlug,
        string $familyDirectory,
        array $face,
        array $provider,
        int &$downloadedFiles
    ): array|WP_Error|null {
        $relativeFiles = [];

        foreach ((array) $face['files'] as $format => $url) {
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

            $download = $this->downloadFontFile((string) $url, $absolutePath);

            if (is_wp_error($download)) {
                return $download;
            }

            $relativeFiles[$format] = $relativePath;
            $downloadedFiles++;
        }

        if ($relativeFiles === []) {
            return null;
        }

        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'source' => 'bunny',
            'weight' => (string) $face['weight'],
            'style' => (string) $face['style'],
            'unicode_range' => (string) ($face['unicode_range'] ?? ''),
            'files' => $relativeFiles,
            'provider' => $provider,
            'is_variable' => !empty($face['is_variable']),
            'axes' => (array) ($face['axes'] ?? []),
            'variation_defaults' => (array) ($face['variation_defaults'] ?? []),
        ];
    }

    private function downloadFontFile(string $url, string $targetPath): bool|WP_Error
    {
        $validated = $this->validateRemoteFontUrl($url);

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
            return $this->error($response->get_error_code(), $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            return $this->error(
                'tasty_fonts_bunny_download_failed',
                sprintf(__('Font download failed with status %d.', 'tasty-fonts'), $status)
            );
        }

        $body = wp_remote_retrieve_body($response);

        if (!is_string($body) || $body === '') {
            return $this->error(
                'tasty_fonts_bunny_empty_file',
                __('Bunny Fonts returned an empty font file.', 'tasty-fonts')
            );
        }

        if (strlen($body) > self::MAX_FONT_FILE_BYTES) {
            return $this->error(
                'tasty_fonts_bunny_file_too_large',
                __('The downloaded font file exceeded the safety size limit.', 'tasty-fonts')
            );
        }

        $contentType = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));

        if (
            $contentType !== ''
            && !str_contains($contentType, 'woff2')
            && !str_contains($contentType, 'font')
            && !str_contains($contentType, 'octet-stream')
        ) {
            return $this->error(
                'tasty_fonts_bunny_invalid_type',
                __('The downloaded file was not returned as a WOFF2 font.', 'tasty-fonts')
            );
        }

        if (!$this->storage->writeAbsoluteFile($targetPath, $body)) {
            return $this->error(
                'tasty_fonts_bunny_write_failed',
                $this->storageErrorMessage(__('The imported font file could not be written to uploads/fonts.', 'tasty-fonts'))
            );
        }

        return true;
    }

    private function validateRemoteFontUrl(string $url): bool|WP_Error
    {
        $parts = wp_parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));

        if ($host !== 'fonts.bunny.net') {
            return $this->error(
                'tasty_fonts_bunny_invalid_host',
                __('Bunny font downloads must come from fonts.bunny.net.', 'tasty-fonts')
            );
        }

        if (!str_ends_with($path, '.woff2')) {
            return $this->error(
                'tasty_fonts_bunny_invalid_extension',
                __('Only WOFF2 files can be imported from Bunny Fonts.', 'tasty-fonts')
            );
        }

        return true;
    }

    private function normalizeDeliveryMode(string $deliveryMode): string
    {
        $deliveryMode = strtolower(trim($deliveryMode));

        return in_array($deliveryMode, ['self_hosted', 'cdn'], true) ? $deliveryMode : 'self_hosted';
    }

    private function findDeliveryProfile(?array $family, string $provider, string $type): ?array
    {
        if (!is_array($family)) {
            return null;
        }

        foreach ((array) ($family['delivery_profiles'] ?? []) as $profile) {
            if (
                is_array($profile)
                && strtolower(trim((string) ($profile['provider'] ?? ''))) === $provider
                && strtolower(trim((string) ($profile['type'] ?? ''))) === $type
            ) {
                return $profile;
            }
        }

        return null;
    }

    private function buildProviderMetadata(?array $metadata, array $variants): array
    {
        return [
            'type' => 'bunny',
            'category' => (string) ($metadata['category'] ?? ''),
            'variants' => $variants,
        ];
    }

    private function storageErrorMessage(string $fallback): string
    {
        $message = trim($this->storage->getLastFilesystemErrorMessage());

        return $message !== '' ? $message : $fallback;
    }

    private function buildVariantPlan(array $requestedVariants, ?array $existingProfile): array
    {
        $existingKeys = [];

        foreach ((array) ($existingProfile['faces'] ?? []) as $face) {
            if (!is_array($face)) {
                continue;
            }

            $existingKeys[HostedImportSupport::faceKeyFromFace($face)] = true;
        }

        $toImport = [];
        $skipped = [];

        foreach ($requestedVariants as $variant) {
            $faceKey = HostedImportSupport::faceKeyFromVariant((string) $variant);

            if ($faceKey === null) {
                continue;
            }

            if (isset($existingKeys[$faceKey])) {
                $skipped[] = (string) $variant;
                continue;
            }

            $toImport[] = (string) $variant;
        }

        return [
            'import' => array_values(array_unique($toImport)),
            'skipped' => array_values(array_unique($skipped)),
        ];
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

    private function error(string $code, string $message): WP_Error
    {
        $this->log->add($message);

        return new WP_Error($code, $message);
    }
}
