<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\Storage;

final class AssetService
{
    public const ACTION_REGENERATE_CSS = 'tasty_fonts_regenerate_css';

    private const TRANSIENT_CSS = 'tasty_fonts_css_v2';
    private const TRANSIENT_HASH = 'tasty_fonts_css_hash_v2';
    private const TRANSIENT_REGENERATE_CSS_QUEUED = 'tasty_fonts_regenerate_css_queued';
    private const REGENERATE_CSS_QUEUE_TTL = 30;

    private ?string $css = null;
    private ?string $hash = null;

    /**
     * Create the asset service.
     *
     * @since 1.4.0
     *
     * @param Storage $storage Storage abstraction for generated stylesheet reads and writes.
     * @param CatalogService $catalog Catalog service used to invalidate and rebuild font data.
     * @param SettingsRepository $settings Settings repository used to resolve delivery and role options.
     * @param CssBuilder $cssBuilder CSS builder used to generate the runtime stylesheet.
     * @param RuntimeAssetPlanner $planner Planner used to scope runtime and preview catalogs.
     * @param LogRepository $log Log repository used for generated-file write notices.
     */
    public function __construct(
        private readonly Storage $storage,
        private readonly CatalogService $catalog,
        private readonly SettingsRepository $settings,
        private readonly CssBuilder $cssBuilder,
        private readonly RuntimeAssetPlanner $planner,
        private readonly LogRepository $log
    ) {
    }

    /**
     * Clear cached generated CSS and hash data from memory and transients.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function invalidate(): void
    {
        delete_transient(self::TRANSIENT_CSS);
        delete_transient(self::TRANSIENT_HASH);
        $this->css = null;
        $this->hash = null;
    }

    /**
     * Return the generated CSS payload for the current runtime catalog.
     *
     * @since 1.4.0
     *
     * @return string Generated CSS payload.
     */
    public function getCss(): string
    {
        if (is_string($this->css)) {
            return $this->css;
        }

        $cachedCss = get_transient(self::TRANSIENT_CSS);
        $cachedHash = get_transient(self::TRANSIENT_HASH);

        if (is_string($cachedCss) && is_string($cachedHash)) {
            $this->css = $cachedCss;
            $this->hash = $cachedHash;

            return $this->css;
        }

        $catalog = $this->catalog->getCatalog();
        $localCatalog = $this->planner->getLocalRuntimeCatalog();
        $variableFamilies = $this->planner->getRuntimeVariableFamilies();
        $settings = $this->settings->getSettings();
        $roles = !empty($settings['auto_apply_roles'])
            ? $this->settings->getAppliedRoles($catalog)
            : $this->settings->getRoles($catalog);

        $this->css = $this->cssBuilder->build($localCatalog, $roles, $settings, $variableFamilies);
        $this->css = (string) apply_filters('tasty_fonts_generated_css', $this->css, $localCatalog, $roles, $settings);
        $this->hash = hash('crc32b', $this->css);

        set_transient(self::TRANSIENT_CSS, $this->css, DAY_IN_SECONDS);
        set_transient(self::TRANSIENT_HASH, $this->hash, DAY_IN_SECONDS);

        return $this->css;
    }

    /**
     * Return the cached hash for the generated CSS payload.
     *
     * @since 1.4.0
     *
     * @return string CRC32b hash for the generated CSS.
     */
    public function getCssHash(): string
    {
        if ($this->hash !== null) {
            return $this->hash;
        }

        $this->getCss();

        return (string) $this->hash;
    }

    /**
     * Return the hash expected for the versioned generated stylesheet file.
     *
     * @since 1.4.0
     *
     * @return string CRC32b hash for the version-prefixed generated stylesheet contents.
     */
    public function expectedFileHash(): string
    {
        return hash('crc32b', $this->getVersionedCss());
    }

    /**
     * Ensure the generated stylesheet file exists and matches the current CSS payload.
     *
     * @since 1.4.0
     *
     * @param bool $logWriteResult Whether to record a log entry for file write success or failure.
     * @return bool True when the generated stylesheet is current or was written successfully.
     */
    public function ensureGeneratedCssFile(bool $logWriteResult = true): bool
    {
        $queuedState = get_transient(self::TRANSIENT_REGENERATE_CSS_QUEUED);

        if (func_num_args() === 0 && is_array($queuedState) && array_key_exists('log_write_result', $queuedState)) {
            $logWriteResult = !empty($queuedState['log_write_result']);
        }

        delete_transient(self::TRANSIENT_REGENERATE_CSS_QUEUED);

        if (!$this->isFileDeliveryEnabled()) {
            return false;
        }

        $state = $this->getGeneratedStylesheetState();
        $path = (string) $state['path'];

        if ($path === '') {
            return false;
        }

        if (!empty($state['is_current'])) {
            return true;
        }

        $written = $this->storage->writeAbsoluteFile($path, $this->getVersionedCss());

        if ($logWriteResult) {
            $this->log->add(
                $written
                    ? __('Generated CSS file written successfully.', 'tasty-fonts')
                    : __('Could not write generated CSS file. Inline fallback will be used.', 'tasty-fonts')
            );
        }

        return $written;
    }

    /**
     * Refresh cached runtime assets after the font library or settings change.
     *
     * @since 1.4.0
     *
     * @param bool $invalidateCatalog Whether to invalidate the combined catalog before rebuilding CSS.
     * @param bool $logWriteResult Whether the deferred generated-file refresh should log its outcome.
     * @return void
     */
    public function refreshGeneratedAssets(bool $invalidateCatalog = true, bool $logWriteResult = true): void
    {
        if ($invalidateCatalog) {
            $this->catalog->invalidate();
        }

        $this->invalidate();
        $this->queueGeneratedCssRegeneration($logWriteResult);
    }

    /**
     * Enqueue the generated stylesheet or its inline fallback for a WordPress style handle.
     *
     * @since 1.4.0
     *
     * @param string $handle Style handle that should receive the generated CSS.
     * @return void
     */
    public function enqueue(string $handle): void
    {
        $css = $this->getCss();
        $state = $this->getGeneratedStylesheetState();
        $url = (string) $state['url'];
        $expectedHash = (string) $state['expected_hash'];

        if (!empty($state['is_current']) && $url !== '') {
            wp_enqueue_style($handle, $url, [], $expectedHash);
            return;
        }

        wp_register_style($handle, false);
        wp_enqueue_style($handle);

        if ($css !== '') {
            wp_add_inline_style($handle, $css);
        }

        $this->ensureGeneratedCssFile();
    }

    /**
     * Enqueue only local @font-face rules for admin previews.
     *
     * @since 1.4.0
     *
     * @param string $handle Style handle that should receive the preview font-face CSS.
     * @return void
     */
    public function enqueueFontFacesOnly(string $handle): void
    {
        $catalog = $this->planner->getLocalPreviewCatalog();
        $settings = $this->settings->getSettings();
        $css = $this->cssBuilder->buildFontFaceOnly($catalog, $settings, 'swap');

        wp_register_style($handle, false);
        wp_enqueue_style($handle);

        if ($css !== '') {
            wp_add_inline_style($handle, $css);
        }
    }

    /**
     * Return filesystem and hash information for the generated stylesheet.
     *
     * @since 1.4.0
     *
     * @return array{
     *     path: string,
     *     url: string,
     *     exists: bool,
     *     size: int,
     *     expected_hash: string
     * } Generated stylesheet status payload.
     */
    public function getStatus(): array
    {
        $state = $this->getGeneratedStylesheetState();

        return [
            'path' => $state['path'],
            'url' => $state['url'],
            'exists' => $state['exists'],
            'size' => $state['size'],
            'expected_hash' => $state['expected_hash'],
        ];
    }

    /**
     * Return the versioned public URL for the generated stylesheet file when available.
     *
     * @since 1.4.0
     *
     * @return string|null Versioned stylesheet URL, or null when the file delivery path is unavailable.
     */
    public function getVersionedStylesheetUrl(): ?string
    {
        $state = $this->getGeneratedStylesheetState();
        $url = (string) $state['url'];

        if ($url === '') {
            return null;
        }

        return add_query_arg('ver', (string) $state['expected_hash'], $url);
    }

    /**
     * Return same-origin preload candidates for the primary heading and body fonts.
     *
     * @since 1.4.0
     *
     * @return array<int, string> List of font preload URLs.
     */
    public function getPrimaryFontPreloadUrls(): array
    {
        return $this->planner->getPrimaryFontPreloadUrls();
    }

    private function getVersionedCss(): string
    {
        return "/* Version: " . TASTY_FONTS_VERSION . " */\n" . $this->getCss();
    }

    private function queueGeneratedCssRegeneration(bool $logWriteResult = true): void
    {
        if (!$this->isFileDeliveryEnabled()) {
            delete_transient(self::TRANSIENT_REGENERATE_CSS_QUEUED);
            return;
        }

        if (get_transient(self::TRANSIENT_REGENERATE_CSS_QUEUED) !== false) {
            return;
        }

        set_transient(
            self::TRANSIENT_REGENERATE_CSS_QUEUED,
            ['log_write_result' => $logWriteResult ? 1 : 0],
            self::REGENERATE_CSS_QUEUE_TTL
        );

        $scheduled = wp_schedule_single_event(time(), self::ACTION_REGENERATE_CSS);

        if ($scheduled === false || is_wp_error($scheduled)) {
            delete_transient(self::TRANSIENT_REGENERATE_CSS_QUEUED);
        }
    }

    private function isFileDeliveryEnabled(): bool
    {
        $settings = $this->settings->getSettings();

        return ($settings['css_delivery_mode'] ?? 'file') === 'file';
    }

    private function getGeneratedStylesheetState(): array
    {
        $path = $this->storage->getGeneratedCssPath() ?? '';
        $url = $this->storage->getGeneratedCssUrl() ?? '';
        $exists = $path !== '' && file_exists($path);
        $expectedHash = $this->expectedFileHash();
        $currentHash = $exists ? (string) hash_file('crc32b', $path) : '';

        return [
            'path' => $path,
            'url' => $url,
            'exists' => $exists,
            'size' => $exists ? (int) filesize($path) : 0,
            'expected_hash' => $expectedHash,
            'current_hash' => $currentHash,
            'is_current' => $exists && $currentHash === $expectedHash,
        ];
    }
}
