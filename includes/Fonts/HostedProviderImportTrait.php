<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;
use WP_Error;

/**
 * @phpstan-type HostedFamily array<string, mixed>
 * @phpstan-type HostedProfile array<string, mixed>
 * @phpstan-type HostedFace array<string, mixed>
 * @phpstan-type HostedProviderMeta array<string, mixed>
 * @phpstan-type HostedImportConfig array<string, string>
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
trait HostedProviderImportTrait
{
    private function normalizeHostedDeliveryMode(string $deliveryMode): string
    {
        $deliveryMode = strtolower(trim($deliveryMode));

        return in_array($deliveryMode, ['self_hosted', 'cdn'], true) ? $deliveryMode : 'self_hosted';
    }

    /**
     * @param HostedFamily|null $family
     * @return HostedProfile|null
     */
    private function findHostedDeliveryProfile(?array $family, string $provider, string $type, string $formatMode = ''): ?array
    {
        if (!is_array($family)) {
            return null;
        }

        foreach ((array) ($family['delivery_profiles'] ?? []) as $profile) {
            if (
                !is_array($profile)
                || strtolower(trim((string) ($profile['provider'] ?? ''))) !== $provider
                || strtolower(trim((string) ($profile['type'] ?? ''))) !== $type
            ) {
                continue;
            }

            if ($formatMode !== '' && FontUtils::resolveProfileFormat($profile) !== $formatMode) {
                continue;
            }

            return $profile;
        }

        return null;
    }

    /**
     * @param HostedVariantList $requestedVariants
     * @param HostedProfile|null $existingProfile
     * @return HostedVariantPlan
     */
    private function buildHostedVariantPlan(array $requestedVariants, ?array $existingProfile, ?callable $normalizeRequested = null): array
    {
        $existingKeys = [];

        foreach ((array) ($existingProfile['faces'] ?? []) as $face) {
            if (!is_array($face)) {
                continue;
            }

            $existingKeys[HostedImportSupport::faceKeyFromFace($face)] = true;
        }

        $normalizedRequested = $normalizeRequested === null
            ? $requestedVariants
            : (array) $normalizeRequested($requestedVariants);
        $toImport = [];
        $skipped = [];

        foreach ($normalizedRequested as $variant) {
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

    /**
     * @param HostedVariantList $requestedVariants
     * @param HostedVariantPlan $variantPlan
     * @param array<string, string> $messages
     * @return HostedImportResult
     */
    private function buildHostedSkippedImportResult(
        string $familyName,
        string $deliveryMode,
        array $requestedVariants,
        array $variantPlan,
        array $messages
    ): array {
        $message = $deliveryMode === 'cdn'
            ? sprintf((string) $messages['cdn'], $familyName)
            : sprintf((string) $messages['existing'], $familyName);

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

    /**
     * @param HostedVariantList $requestedVariants
     * @return list<HostedFace>|WP_Error
     */
    private function selectHostedImportFaces(
        string $familyName,
        string $css,
        array $requestedVariants,
        callable $parseFaces,
        string $emptyCode,
        string $emptyMessage
    ): array|WP_Error {
        $faces = HostedImportSupport::selectPreferredFaces(
            $this->normalizeHostedFaceList($parseFaces($css, $familyName)),
            $requestedVariants
        );

        if ($faces === []) {
            return $this->error($emptyCode, $emptyMessage);
        }

        return $faces;
    }

    /**
     * @param HostedImportConfig $config
     */
    private function resolveHostedImportTarget(string $familySlug, array $config): string|WP_Error
    {
        $root = $this->getHostedImportRootDirectory($config);

        if (is_wp_error($root)) {
            return $root;
        }

        $familyDirectory = trailingslashit($root) . $familySlug;

        if (!$this->storage->ensureDirectory($familyDirectory)) {
            return $this->error(
                (string) $config['family_dir_error_code'],
                $this->storageErrorMessage((string) $config['family_dir_error_message'])
            );
        }

        return $familyDirectory;
    }

    /**
     * @param HostedImportConfig $config
     */
    private function getHostedImportRootDirectory(array $config): string|WP_Error
    {
        if (!$this->storage->ensureRootDirectory()) {
            return $this->error(
                'tasty_fonts_storage_unavailable',
                $this->storageErrorMessage(__('The uploads/fonts storage directory could not be created.', 'tasty-fonts'))
            );
        }

        $providerRoot = $this->storage->getProviderRoot((string) $config['provider_root']);

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
     * @param HostedProviderMeta $provider
     * @param HostedImportConfig $config
     * @return array<string, mixed>|WP_Error|null
     */
    private function buildHostedManifestFace(
        string $familyName,
        string $familySlug,
        string $familyDirectory,
        array $face,
        array $provider,
        int &$downloadedFiles,
        array $config
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

            $download = $this->downloadHostedFontFile((string) $url, $absolutePath, $config);

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
            'source' => (string) $config['source'],
            'weight' => (string) ($face['weight'] ?? '400'),
            'style' => (string) ($face['style'] ?? 'normal'),
            'unicode_range' => (string) ($face['unicode_range'] ?? ''),
            'files' => $relativeFiles,
            'provider' => $provider,
            'is_variable' => !empty($face['is_variable']),
            'axes' => (array) ($face['axes'] ?? []),
            'variation_defaults' => (array) ($face['variation_defaults'] ?? []),
        ];
    }

    /**
     * @param list<HostedFace> $faces
     * @param HostedProviderMeta $provider
     * @return list<array<string, mixed>>
     */
    private function buildHostedCdnFaces(string $familyName, string $familySlug, array $faces, array $provider, string $source): array
    {
        $cdnFaces = [];

        foreach ($faces as $face) {
            $cdnFaces[] = [
                'family' => $familyName,
                'slug' => $familySlug,
                'source' => $source,
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

        return $cdnFaces;
    }

    /**
     * @param list<HostedFace> $faces
     * @param HostedProviderMeta $provider
     * @param HostedImportConfig $config
     * @return HostedManifestResult|WP_Error
     */
    private function buildHostedManifestFaces(
        string $familyName,
        string $familySlug,
        string $familyDirectory,
        array $faces,
        array $provider,
        array $config
    ): array|WP_Error {
        $manifestFaces = [];
        $downloadedFiles = 0;

        foreach ($faces as $face) {
            $manifestFace = $this->buildHostedManifestFace(
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
    private function persistHostedProfile(
        string $familyName,
        string $familySlug,
        array $profile,
        ?array $existingFamily,
        ?array $existingProfile,
        array $importedVariants
    ): array {
        $profile['faces'] = HostedImportSupport::mergeManifestFaces(
            $this->normalizeHostedFaceList($existingProfile['faces'] ?? []),
            $this->normalizeHostedFaceList($profile['faces'] ?? [])
        );
        $profile['variants'] = array_values(
            array_unique(
                array_merge(
                    $this->normalizeHostedVariantList($existingProfile['variants'] ?? []),
                    $importedVariants
                )
            )
        );

        $savedFamily = $this->imports->saveProfile(
            $familyName,
            $familySlug,
            $profile,
            $existingFamily === null ? 'library_only' : (string) ($existingFamily['publish_state'] ?? 'published'),
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
     * @param HostedImportConfig $config
     * @return HostedImportResult|WP_Error
     */
    private function saveHostedSelfHostedProfile(
        string $familyName,
        string $familySlug,
        array $faces,
        array $variantPlan,
        ?array $existingFamily,
        ?array $existingProfile,
        array $profile,
        array $config,
        string $emptyManifestCode,
        string $emptyManifestMessage,
        string $successTemplate
    ): array|WP_Error {
        $familyDirectory = $this->resolveHostedImportTarget($familySlug, $config);

        if (is_wp_error($familyDirectory)) {
            return $familyDirectory;
        }

        $provider = (array) ($profile['provider_face'] ?? []);
        unset($profile['provider_face']);
        $manifest = $this->buildHostedManifestFaces($familyName, $familySlug, $familyDirectory, $faces, $provider, $config);

        if (is_wp_error($manifest)) {
            return $manifest;
        }

        $manifestFaces = $manifest['faces'];

        if ($manifestFaces === []) {
            return $this->error($emptyManifestCode, $emptyManifestMessage);
        }

        $persisted = $this->persistHostedProfile(
            $familyName,
            $familySlug,
            $profile + ['faces' => $manifestFaces],
            $existingFamily,
            $existingProfile,
            $variantPlan['import']
        );

        $faceCount = count($manifestFaces);
        $fileCount = (int) $manifest['files'];
        $message = $this->buildHostedImportMessageWithFiles(
            $successTemplate,
            $familyName,
            $faceCount,
            $fileCount,
            count($variantPlan['skipped'])
        );

        return $this->finalizeHostedImportResult(
            'imported',
            $message,
            $familyName,
            (array) $persisted['family_record'],
            (string) ($profile['type'] ?? 'self_hosted'),
            (string) ($profile['id'] ?? ''),
            $faceCount,
            $fileCount,
            (array) $persisted['variants'],
            $variantPlan
        );
    }

    /**
     * @param list<HostedFace> $faces
     * @param HostedVariantPlan $variantPlan
     * @param HostedFamily|null $existingFamily
     * @param HostedProfile|null $existingProfile
     * @param HostedProfile $profile
     * @param HostedImportConfig $config
     * @return HostedImportResult|WP_Error
     */
    private function saveHostedCdnProfile(
        string $familyName,
        string $familySlug,
        array $faces,
        array $variantPlan,
        ?array $existingFamily,
        ?array $existingProfile,
        array $profile,
        array $config,
        string $successTemplate
    ): array|WP_Error {
        $provider = (array) ($profile['provider_face'] ?? []);
        unset($profile['provider_face']);
        $cdnFaces = $this->buildHostedCdnFaces(
            $familyName,
            $familySlug,
            $faces,
            $provider,
            (string) ($config['source'] ?? '')
        );
        $persisted = $this->persistHostedProfile(
            $familyName,
            $familySlug,
            $profile + ['faces' => $cdnFaces],
            $existingFamily,
            $existingProfile,
            $variantPlan['import']
        );

        $faceCount = count($cdnFaces);
        $message = $this->buildHostedImportMessageWithoutFiles(
            $successTemplate,
            $familyName,
            $faceCount,
            count($variantPlan['skipped'])
        );

        return $this->finalizeHostedImportResult(
            'saved',
            $message,
            $familyName,
            (array) $persisted['family_record'],
            (string) ($profile['type'] ?? 'cdn'),
            (string) ($profile['id'] ?? ''),
            $faceCount,
            0,
            (array) $persisted['variants'],
            $variantPlan
        );
    }

    private function buildHostedImportMessageWithFiles(
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

    private function buildHostedImportMessageWithoutFiles(
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
    private function finalizeHostedImportResult(
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
        $this->log->add($message);

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
    private function completeHostedImport(array|WP_Error $result, string $provider): array|WP_Error
    {
        if (is_wp_error($result)) {
            return $result;
        }

        $this->assets->refreshGeneratedAssets();
        do_action('tasty_fonts_after_import', $result, $provider);

        return $result;
    }

    /**
     * @param HostedImportConfig $config
     */
    private function downloadHostedFontFile(string $url, string $targetPath, array $config): bool|WP_Error
    {
        $validated = $this->validateHostedRemoteFontUrl($url, $config);

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
            return $this->error($this->normalizeHostedErrorCode($response->get_error_code()), $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            return $this->error(
                (string) $config['download_failed_code'],
                sprintf(__('Font download failed with status %d.', 'tasty-fonts'), $status)
            );
        }

        $body = wp_remote_retrieve_body($response);

        if ($body === '') {
            return $this->error(
                (string) $config['empty_file_code'],
                (string) $config['empty_file_message']
            );
        }

        if (strlen($body) > static::MAX_FONT_FILE_BYTES) {
            return $this->error(
                (string) $config['file_too_large_code'],
                __('The downloaded font file exceeded the safety size limit.', 'tasty-fonts')
            );
        }

        $contentType = strtolower($this->normalizeHostedHeaderValue(wp_remote_retrieve_header($response, 'content-type')));

        if (
            $contentType !== ''
            && !str_contains($contentType, 'woff2')
            && !str_contains($contentType, 'font')
            && !str_contains($contentType, 'octet-stream')
        ) {
            return $this->error(
                (string) $config['invalid_type_code'],
                (string) $config['invalid_type_message']
            );
        }

        if (!$this->storage->writeAbsoluteFile($targetPath, $body)) {
            return $this->error(
                (string) $config['write_failed_code'],
                $this->storageErrorMessage((string) $config['write_failed_message'])
            );
        }

        return true;
    }

    /**
     * @param mixed $faces
     * @return list<HostedFace>
     */
    private function normalizeHostedFaceList(mixed $faces): array
    {
        if (!is_array($faces)) {
            return [];
        }

        $normalized = [];

        foreach ($faces as $face) {
            if (!is_array($face)) {
                continue;
            }

            $normalized[] = $face;
        }

        return $normalized;
    }

    /**
     * @param mixed $variants
     * @return HostedVariantList
     */
    private function normalizeHostedVariantList(mixed $variants): array
    {
        if (!is_array($variants)) {
            return [];
        }

        $normalized = [];

        foreach ($variants as $variant) {
            if (!is_scalar($variant)) {
                continue;
            }

            $normalized[] = (string) $variant;
        }

        return FontUtils::normalizeVariantTokens($normalized);
    }

    private function normalizeHostedErrorCode(int|string $code): string
    {
        return (string) $code;
    }

    private function normalizeHostedHeaderValue(mixed $value): string
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
     * @param HostedImportConfig $config
     */
    private function validateHostedRemoteFontUrl(string $url, array $config): bool|WP_Error
    {
        $parts = wp_parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));

        if ($host !== strtolower((string) $config['expected_host'])) {
            return $this->error(
                (string) $config['invalid_host_code'],
                (string) $config['invalid_host_message']
            );
        }

        if (!str_ends_with($path, '.woff2')) {
            return $this->error(
                (string) $config['invalid_extension_code'],
                (string) $config['invalid_extension_message']
            );
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
        $this->log->add($message);

        return new WP_Error($code, $message);
    }
}
