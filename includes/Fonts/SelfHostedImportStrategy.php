<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

final class SelfHostedImportStrategy implements DeliveryImportStrategy
{
    public function __construct(private readonly Storage $storage) {}

    public function supports(string $deliveryMode): bool
    {
        return $deliveryMode === 'self_hosted';
    }

    /**
     * @param list<array<string, mixed>> $faces
     * @param array<string, mixed> $provider
     * @return ImportFacesResult|WP_Error
     */
    public function importFaces(
        string $familyName,
        string $familySlug,
        array $faces,
        array $provider,
        HostedImportProviderConfig $config
    ): ImportFacesResult|WP_Error {
        $familyDirectory = $this->resolveImportTarget($familySlug, $config);

        if (is_wp_error($familyDirectory)) {
            return $familyDirectory;
        }

        $manifest = $this->buildManifestFaces(
            $familyName,
            $familySlug,
            $familyDirectory,
            $faces,
            $provider,
            $config
        );

        if (is_wp_error($manifest)) {
            return $manifest;
        }

        return new ImportFacesResult($manifest['faces'], $manifest['files']);
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
            return new WP_Error(
                $config->familyDirErrorCode,
                $this->storageErrorMessage($config->familyDirErrorMessage)
            );
        }

        return $familyDirectory;
    }

    /**
     * @return string|WP_Error
     */
    private function getImportRootDirectory(HostedImportProviderConfig $config): string|WP_Error
    {
        if (!$this->storage->ensureRootDirectory()) {
            return new WP_Error(
                'tasty_fonts_storage_unavailable',
                $this->storageErrorMessage(__('The uploads/fonts storage directory could not be created.', 'tasty-fonts'))
            );
        }

        $providerRoot = $this->storage->getProviderRoot($config->providerRoot);

        if (!$providerRoot) {
            return new WP_Error(
                'tasty_fonts_storage_unavailable',
                $this->storageErrorMessage(__('The uploads/fonts storage directory could not be created.', 'tasty-fonts'))
            );
        }

        return $providerRoot;
    }

    /**
     * @param list<array<string, mixed>> $faces
     * @param array<string, mixed> $provider
     * @return array{faces: list<array<string, mixed>>, files: int}|WP_Error
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
     * @param array<string, mixed> $face
     * @param array<string, mixed> $provider
     * @return array<string, mixed>|WP_Error|null
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
            return new WP_Error($this->normalizeErrorCode($response->get_error_code()), $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            return new WP_Error(
                $config->downloadFailedCode,
                sprintf($config->downloadFailedMessage, $status)
            );
        }

        $body = wp_remote_retrieve_body($response);

        if ($body === '') {
            return new WP_Error($config->emptyFileCode, $config->emptyFileMessage);
        }

        if (strlen($body) > $config->maxFontFileBytes) {
            return new WP_Error($config->fileTooLargeCode, $config->fileTooLargeMessage);
        }

        $contentType = strtolower($this->normalizeHeaderValue(wp_remote_retrieve_header($response, 'content-type')));

        if (
            $contentType !== ''
            && !str_contains($contentType, 'woff2')
            && !str_contains($contentType, 'font')
            && !str_contains($contentType, 'octet-stream')
        ) {
            return new WP_Error($config->invalidTypeCode, $config->invalidTypeMessage);
        }

        if (!$this->storage->writeAbsoluteFile($targetPath, $body)) {
            return new WP_Error(
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
            return new WP_Error($config->invalidHostCode, $config->invalidHostMessage);
        }

        if (!str_ends_with($path, '.woff2')) {
            return new WP_Error($config->invalidExtensionCode, $config->invalidExtensionMessage);
        }

        return true;
    }

    private function storageErrorMessage(string $fallback): string
    {
        $message = trim($this->storage->getLastFilesystemErrorMessage());

        return $message !== '' ? $message : $fallback;
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
     * @param array<string, mixed> $face
     * @param array<string, string> $files
     * @param array<string, mixed> $provider
     * @return array<string, mixed>
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
}
