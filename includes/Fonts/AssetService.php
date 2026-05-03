<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\RoleRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\Storage;

/**
 * @phpstan-type GeneratedStylesheetState array{
 *     path: string,
 *     url: string,
 *     exists: bool,
 *     size: int,
 *     last_modified: int,
 *     expected_hash: string,
 *     expected_version: string,
 *     current_hash: string,
 *     is_current: bool,
 *     write_path: string
 * }
 */
final class AssetService
{
    public const ACTION_REGENERATE_CSS = 'tasty_fonts_regenerate_css';
    public const TRANSIENT_CSS = 'tasty_fonts_css_v2';
    public const TRANSIENT_HASH = 'tasty_fonts_css_hash_v2';
    public const TRANSIENT_REGENERATE_CSS_QUEUED = 'tasty_fonts_regenerate_css_queued';
    private const REGENERATE_CSS_QUEUE_TTL = 30;

    private readonly GeneratedCssCache $cssCache;
    private readonly GeneratedStylesheetFile $stylesheetFile;
    private readonly GeneratedCssRegenerationQueue $regenerationQueue;
    private readonly InlineStyleNonceManager $inlineStyleNonceManager;
    private readonly GeneratedCssDelivery $delivery;

    /**
     * Create the asset service.
     *
     * @since 1.4.0
     *
     * @param Storage $storage Storage abstraction for generated stylesheet reads and writes.
     * @param CatalogCache $catalog Catalog service used to invalidate and rebuild font data.
     * @param SettingsRepository $settings Settings repository used to resolve delivery and role options.
     * @param CssBuilder $cssBuilder CSS builder used to generate the runtime stylesheet.
     * @param RuntimeAssetPlanner $planner Planner used to scope runtime and preview catalogs.
     * @param LogRepository $log Log repository used for generated-file write notices.
     */
    public function __construct(
        Storage $storage,
        private readonly CatalogCache $catalog,
        SettingsRepository $settings,
        CssBuilder $cssBuilder,
        private readonly RuntimeAssetPlanner $planner,
        LogRepository $log,
        RoleRepository $roleRepo,
    ) {
        $this->cssCache = new GeneratedCssCache(
            $catalog,
            $settings,
            $cssBuilder,
            $planner,
            $roleRepo,
            self::TRANSIENT_CSS,
            self::TRANSIENT_HASH
        );
        $this->stylesheetFile = new GeneratedStylesheetFile($storage, $this->cssCache, $log);
        $this->regenerationQueue = new GeneratedCssRegenerationQueue(
            self::ACTION_REGENERATE_CSS,
            self::TRANSIENT_REGENERATE_CSS_QUEUED,
            self::REGENERATE_CSS_QUEUE_TTL
        );
        $this->inlineStyleNonceManager = new InlineStyleNonceManager();
        $this->delivery = new GeneratedCssDelivery(
            $this->cssCache,
            $this->stylesheetFile,
            $this->inlineStyleNonceManager,
            $planner,
            $settings,
            $cssBuilder
        );
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
        $this->cssCache->invalidate();
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
        return $this->cssCache->getCss();
    }

    /**
     * Return the cached hash for the generated CSS payload.
     *
     * @since 1.4.0
     *
     * @return string SHA-256 hash for the generated CSS.
     */
    public function getCssHash(): string
    {
        return $this->cssCache->getHash();
    }

    /**
     * Return the hash expected for the versioned generated stylesheet file.
     *
     * @since 1.4.0
     *
     * @return string SHA-256 hash for the version-prefixed generated stylesheet contents.
     */
    public function expectedFileHash(): string
    {
        return $this->stylesheetFile->expectedFileHash();
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
        $logWriteResult = $this->regenerationQueue->resolveLogWriteResult($logWriteResult, func_num_args() === 0);

        return $this->stylesheetFile->ensureFile($logWriteResult);
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
        $this->regenerationQueue->queue($logWriteResult);
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
        $this->delivery->enqueueGeneratedCss($handle);
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
        $this->delivery->enqueuePreviewFontFacesOnly($handle);
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
     *     last_modified: int,
     *     expected_hash: string,
     *     expected_version: string,
     *     current_hash: string,
     *     is_current: bool,
     *     write_path: string
     * } Generated stylesheet status payload.
     */
    public function getStatus(): array
    {
        return $this->stylesheetFile->getStatus();
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
        return $this->stylesheetFile->getVersionedStylesheetUrl();
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

    public function isInlineDeliveryEnabled(): bool
    {
        return $this->delivery->isInlineDeliveryEnabled();
    }

    /**
     * Add configured nonce attributes to this plugin's rendered inline style tags.
     *
     * When WordPress core gains first-class inline style nonce support, the
     * default "auto" strategy can switch to delegating to core without
     * changing the plugin API exposed to sites today.
     *
     * @since 6.0.2
     *
     * @param string $html HTML fragment that may contain plugin inline style tags.
     * @return string HTML with nonce-bearing inline style tags when configured.
     */
    public function filterInlineStyleOutputBuffer(string $html): string
    {
        return $this->inlineStyleNonceManager->filterOutputBuffer($html);
    }
}
