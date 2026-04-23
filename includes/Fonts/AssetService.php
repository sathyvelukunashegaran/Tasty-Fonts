<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use TastyFonts\Support\TransientKey;

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
 *     write_path: string,
 *     needs_migration: bool
 * }
 */
final class AssetService
{
    public const ACTION_REGENERATE_CSS = 'tasty_fonts_regenerate_css';
    public const TRANSIENT_CSS = 'tasty_fonts_css_v2';
    public const TRANSIENT_HASH = 'tasty_fonts_css_hash_v2';
    public const TRANSIENT_REGENERATE_CSS_QUEUED = 'tasty_fonts_regenerate_css_queued';
    private const CONTENT_HASH_ALGORITHM = 'sha256';
    private const VERSION_HASH_LENGTH = 16;
    private const REGENERATE_CSS_QUEUE_TTL = 30;
    private const INLINE_STYLE_CONTEXT_RUNTIME = 'runtime';
    private const INLINE_STYLE_CONTEXT_ADMIN_PREVIEW = 'admin_preview';

    private ?string $css = null;
    private ?string $hash = null;
    /** @var array<string, string> */
    private array $inlineStyleNonces = [];
    private bool $inlineStyleOutputBufferStarted = false;

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
        delete_transient(TransientKey::forSite(self::TRANSIENT_CSS));
        delete_transient(TransientKey::forSite(self::TRANSIENT_HASH));
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

        $cachedCss = get_transient(TransientKey::forSite(self::TRANSIENT_CSS));
        $cachedHash = get_transient(TransientKey::forSite(self::TRANSIENT_HASH));

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
        $this->css = FontUtils::scalarStringValue(apply_filters('tasty_fonts_generated_css', $this->css, $localCatalog, $roles, $settings));
        $this->hash = $this->hashContents($this->css);

        set_transient(TransientKey::forSite(self::TRANSIENT_CSS), $this->css, DAY_IN_SECONDS);
        set_transient(TransientKey::forSite(self::TRANSIENT_HASH), $this->hash, DAY_IN_SECONDS);

        return $this->css;
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
     * @return string SHA-256 hash for the version-prefixed generated stylesheet contents.
     */
    public function expectedFileHash(): string
    {
        return $this->hashContents($this->getVersionedCss());
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
        $queuedState = get_transient(TransientKey::forSite(self::TRANSIENT_REGENERATE_CSS_QUEUED));

        if (func_num_args() === 0 && is_array($queuedState) && array_key_exists('log_write_result', $queuedState)) {
            $logWriteResult = !empty($queuedState['log_write_result']);
        }

        delete_transient(TransientKey::forSite(self::TRANSIENT_REGENERATE_CSS_QUEUED));

        $state = $this->getGeneratedStylesheetState();
        $path = (string) $state['path'];

        if ($path === '') {
            return false;
        }

        if (!empty($state['is_current']) && empty($state['needs_migration'])) {
            return true;
        }

        return $this->writeGeneratedCssFile($state, $logWriteResult);
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

        if (!empty($state['is_current']) && !empty($state['needs_migration'])) {
            $this->writeGeneratedCssFile($state, false);
            $state = $this->getGeneratedStylesheetState();
        }

        $url = (string) $state['url'];
        $expectedVersion = (string) $state['expected_version'];

        if (!$this->isInlineDeliveryEnabled() && !empty($state['is_current']) && $url !== '') {
            wp_enqueue_style($handle, $url, [], $expectedVersion);
            return;
        }

        wp_register_style($handle, false);
        wp_enqueue_style($handle);

        if ($css !== '') {
            wp_add_inline_style($handle, $css);
            $this->armInlineStyleNonceHandling($handle, $css, self::INLINE_STYLE_CONTEXT_RUNTIME);
        }

        $this->writeGeneratedCssFile($state);
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
            $this->armInlineStyleNonceHandling($handle, $css, self::INLINE_STYLE_CONTEXT_ADMIN_PREVIEW);
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
     *     last_modified: int,
     *     expected_hash: string,
     *     expected_version: string
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
            'last_modified' => $state['last_modified'],
            'expected_hash' => $state['expected_hash'],
            'expected_version' => $state['expected_version'],
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

        $version = !empty($state['exists']) ? (string) $state['expected_version'] : TASTY_FONTS_VERSION;

        return add_query_arg('ver', $version, $url);
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
        $settings = $this->settings->getSettings();

        return ($settings['css_delivery_mode'] ?? 'file') === 'inline';
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
        if ($html === '' || $this->inlineStyleNonces === [] || !str_contains($html, '<style')) {
            return $html;
        }

        $styleIds = array_keys($this->inlineStyleNonces);
        $pattern = '/<style\b(?P<before>[^>]*)\bid=(["\'])(?P<id>' . implode('|', array_map('preg_quote', $styleIds)) . ')\2(?P<after>[^>]*)>/i';
        $filtered = preg_replace_callback(
            $pattern,
            function (array $matches): string {
                $styleId = (string) ($matches['id'] ?? '');
                $nonce = (string) ($this->inlineStyleNonces[$styleId] ?? '');

                if ($nonce === '') {
                    return (string) $matches[0];
                }

                $attributes = (string) ($matches['before'] ?? '') . ' ' . (string) ($matches['after'] ?? '');

                if (preg_match('/\snonce\s*=/i', $attributes) === 1) {
                    return (string) $matches[0];
                }

                $tag = (string) $matches[0];
                $position = strrpos($tag, '>');

                if ($position === false) {
                    return $tag;
                }

                return substr($tag, 0, $position)
                    . ' nonce="' . esc_attr($nonce) . '"'
                    . substr($tag, $position);
            },
            $html
        );

        return is_string($filtered) ? $filtered : $html;
    }

    private function getVersionedCss(): string
    {
        return "/* Version: " . TASTY_FONTS_VERSION . " */\n" . $this->getCss();
    }

    private function hashContents(string $contents): string
    {
        return hash(self::CONTENT_HASH_ALGORITHM, $contents);
    }

    private function hashFile(string $path): string
    {
        return (string) hash_file(self::CONTENT_HASH_ALGORITHM, $path);
    }

    private function versionTokenFromHash(string $hash): string
    {
        return substr($hash, 0, self::VERSION_HASH_LENGTH);
    }

    private function armInlineStyleNonceHandling(string $handle, string $css, string $context): void
    {
        $nonce = $this->getInlineStyleNonce($handle, $css, $context);

        if ($nonce === '' || !$this->shouldUsePluginInlineStyleNonceStrategy($handle, $css, $context)) {
            return;
        }

        $this->inlineStyleNonces[$handle . '-inline-css'] = $nonce;

        if (
            $this->inlineStyleOutputBufferStarted
            || PHP_SAPI === 'cli'
            || PHP_SAPI === 'phpdbg'
        ) {
            return;
        }

        ob_start([$this, 'filterInlineStyleOutputBuffer']);
        $this->inlineStyleOutputBufferStarted = true;
    }

    private function shouldUsePluginInlineStyleNonceStrategy(string $handle, string $css, string $context): bool
    {
        $strategy = strtolower(trim(FontUtils::scalarStringValue(apply_filters(
            'tasty_fonts_inline_style_nonce_strategy',
            'auto',
            $handle,
            $context,
            $css
        ))));

        if (in_array($strategy, ['off', 'disabled', 'none', 'core'], true)) {
            return false;
        }

        if ($strategy === 'auto' && $this->coreSupportsInlineStyleNonceHandling()) {
            return false;
        }

        return in_array($strategy, ['auto', 'buffer', 'output_buffer', 'plugin'], true);
    }

    private function getInlineStyleNonce(string $handle, string $css, string $context): string
    {
        $nonce = apply_filters('tasty_fonts_inline_style_nonce', '', $handle, $css, $context);

        return is_string($nonce) ? trim($nonce) : '';
    }

    private function coreSupportsInlineStyleNonceHandling(): bool
    {
        return function_exists('wp_get_inline_style_tag') || function_exists('wp_print_inline_style_tag');
    }

    private function queueGeneratedCssRegeneration(bool $logWriteResult = true): void
    {
        if (get_transient(TransientKey::forSite(self::TRANSIENT_REGENERATE_CSS_QUEUED)) !== false) {
            return;
        }

        set_transient(
            TransientKey::forSite(self::TRANSIENT_REGENERATE_CSS_QUEUED),
            ['log_write_result' => $logWriteResult ? 1 : 0],
            self::REGENERATE_CSS_QUEUE_TTL
        );

        $scheduled = wp_schedule_single_event(time(), self::ACTION_REGENERATE_CSS);

        if ($scheduled === false) {
            delete_transient(TransientKey::forSite(self::TRANSIENT_REGENERATE_CSS_QUEUED));
        }
    }

    /**
     * @return GeneratedStylesheetState
     */
    private function getGeneratedStylesheetState(): array
    {
        $path = $this->storage->getGeneratedCssPath() ?? '';
        $url = $this->storage->getGeneratedCssUrl() ?? '';
        $writePath = $path;
        $canonicalState = $this->buildStylesheetStateForPath($path, $url);
        $expectedHash = $this->expectedFileHash();
        $state = $canonicalState;

        if (!$canonicalState['exists']) {
            $legacyPath = $this->getLegacyGeneratedCssPath();
            $legacyUrl = $this->getLegacyGeneratedCssUrl();

            if ($legacyPath !== '' && $legacyUrl !== '') {
                $legacyState = $this->buildStylesheetStateForPath($legacyPath, $legacyUrl);

                if ($legacyState['exists']) {
                    $state = $legacyState;
                }
            }
        }

        return [
            'path' => $state['path'],
            'url' => $state['url'],
            'exists' => $state['exists'],
            'size' => $state['size'],
            'last_modified' => $state['last_modified'],
            'expected_hash' => $expectedHash,
            'expected_version' => $this->versionTokenFromHash($expectedHash),
            'current_hash' => $state['current_hash'],
            'is_current' => $state['exists'] && $state['current_hash'] === $expectedHash,
            'write_path' => $writePath,
            'needs_migration' => $state['exists'] && $writePath !== '' && $state['path'] !== $writePath,
        ];
    }

    /**
     * @param GeneratedStylesheetState $state
     */
    private function writeGeneratedCssFile(array $state, bool $logWriteResult = true): bool
    {
        $path = $state['write_path'];

        if ($path === '' || (!empty($state['is_current']) && empty($state['needs_migration']))) {
            return $path !== '' && !empty($state['is_current']);
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
     * @return array{path: string, url: string, exists: bool, size: int, last_modified: int, current_hash: string}
     */
    private function buildStylesheetStateForPath(string $path, string $url): array
    {
        if ($path !== '') {
            clearstatcache(true, $path);
        }

        $exists = $path !== '' && file_exists($path);

        return [
            'path' => $path,
            'url' => $url,
            'exists' => $exists,
            'size' => $exists ? (int) filesize($path) : 0,
            'last_modified' => $exists ? (int) filemtime($path) : 0,
            'current_hash' => $exists ? $this->hashFile($path) : '',
        ];
    }

    private function getLegacyGeneratedCssPath(): string
    {
        $root = $this->storage->getRoot();

        if (!is_string($root) || $root === '') {
            return '';
        }

        return trailingslashit($root) . 'tasty-fonts.css';
    }

    private function getLegacyGeneratedCssUrl(): string
    {
        $rootUrl = $this->storage->getRootUrlFull();

        if (!is_string($rootUrl) || $rootUrl === '') {
            return '';
        }

        return untrailingslashit($rootUrl) . '/tasty-fonts.css';
    }
}
