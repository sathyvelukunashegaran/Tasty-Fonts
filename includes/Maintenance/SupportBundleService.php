<?php

declare(strict_types=1);

namespace TastyFonts\Maintenance;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;
use ZipArchive;

/**
 * @phpstan-import-type NormalizedSettings from SettingsRepository
 * @phpstan-type SupportBundle array{path: string, filename: string, content_type: string, size: int}
 */
final class SupportBundleService
{
    private const TEMP_ZIP_PREFIX = 'tasty-fonts-support-';

    public function __construct(
        private readonly Storage $storage,
        private readonly SettingsRepository $settings,
        private readonly ImportRepository $imports,
        private readonly LogRepository $log
    ) {
    }

    /**
     * @param array<string, mixed> $advancedToolsPayload
     * @return SupportBundle|WP_Error
     */
    public function buildBundle(array $advancedToolsPayload): array|WP_Error
    {
        if (!class_exists(ZipArchive::class)) {
            return new WP_Error(
                'tasty_fonts_support_bundle_zip_unavailable',
                __('ZipArchive is unavailable on this server, so support bundles cannot be created.', 'tasty-fonts')
            );
        }

        $path = $this->createTemporaryZipPath();

        if (is_wp_error($path)) {
            return $path;
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($path);

            return new WP_Error(
                'tasty_fonts_support_bundle_create_failed',
                __('The support bundle archive could not be created.', 'tasty-fonts')
            );
        }

        $this->addJson($zip, 'diagnostics/summary.json', [
            'plugin_version' => defined('TASTY_FONTS_VERSION') ? (string) TASTY_FONTS_VERSION : '',
            'created_at' => current_time('mysql', true),
            'storage_root' => $this->storage->getRoot(),
            'generated_css_path' => $this->storage->getGeneratedCssPath(),
        ]);
        $this->addJson($zip, 'diagnostics/advanced-tools.json', $this->sanitizePayload($advancedToolsPayload));
        $this->addJson($zip, 'diagnostics/settings.json', $this->sanitizedSettings());
        $this->addJson($zip, 'diagnostics/library.json', $this->imports->allFamilies());
        $this->addJson($zip, 'diagnostics/activity.json', $this->log->all());
        $this->addJson($zip, 'diagnostics/storage-files.json', $this->storageFileMetadata());

        $generatedCssPath = $this->storage->getGeneratedCssPath();

        if (is_string($generatedCssPath) && is_readable($generatedCssPath)) {
            $zip->addFile($generatedCssPath, 'generated-css/tasty-fonts.css');
        }

        $zip->close();

        return [
            'path' => $path,
            'filename' => $this->buildFilename(),
            'content_type' => 'application/zip',
            'size' => is_file($path) ? (int) filesize($path) : 0,
        ];
    }

    /**
     * @return NormalizedSettings
     */
    private function sanitizedSettings(): array
    {
        $settings = $this->settings->getSettings();

        unset(
            $settings['google_api_key'],
            $settings['google_api_key_encrypted'],
            $settings['google_api_key_status_message'],
            $settings['google_api_key_checked_at']
        );

        $settings['google_api_key_status'] = trim(FontUtils::scalarStringValue($settings['google_api_key_status'] ?? '')) === ''
            ? 'empty'
            : FontUtils::scalarStringValue($settings['google_api_key_status']);

        return $settings;
    }

    /**
     * @return list<array{relative_path: string, size: int, sha256: string}>
     */
    private function storageFileMetadata(): array
    {
        $root = $this->storage->getRoot();

        if (!is_string($root) || $root === '' || !is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                continue;
            }

            $absolutePath = wp_normalize_path($item->getPathname());
            $relativePath = $this->storage->relativePath($absolutePath);

            if ($relativePath === '') {
                continue;
            }

            $checksum = hash_file('sha256', $absolutePath);

            $files[] = [
                'relative_path' => $relativePath,
                'size' => (int) $item->getSize(),
                'sha256' => is_string($checksum) ? $checksum : '',
            ];
        }

        usort($files, static fn (array $left, array $right): int => strcmp($left['relative_path'], $right['relative_path']));

        return $files;
    }

    /**
     * @param array<mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (str_contains(strtolower($key), 'secret') || str_contains(strtolower($key), 'api_key')) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizePayload(FontUtils::normalizeStringKeyedMap($value));
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed>|list<mixed> $payload
     */
    private function addJson(ZipArchive $zip, string $path, array $payload): void
    {
        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (is_string($json)) {
            $zip->addFromString($path, $json);
        }
    }

    private function createTemporaryZipPath(): string|WP_Error
    {
        $path = tempnam($this->temporaryBaseDirectory(), self::TEMP_ZIP_PREFIX);

        if ($path === false) {
            return new WP_Error(
                'tasty_fonts_support_bundle_temp_file_failed',
                __('A temporary zip file could not be created for the support bundle.', 'tasty-fonts')
            );
        }

        return wp_normalize_path($path);
    }

    private function temporaryBaseDirectory(): string
    {
        $baseDirectory = function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : '';

        return wp_normalize_path(trim($baseDirectory) !== '' ? $baseDirectory : ABSPATH);
    }

    private function buildFilename(): string
    {
        $version = defined('TASTY_FONTS_VERSION') ? sanitize_file_name((string) TASTY_FONTS_VERSION) : 'bundle';

        return sprintf('tasty-fonts-support-%s-%s.zip', $version, gmdate('Ymd-His'));
    }
}
