<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Integrations\AcssIntegrationService;
use TastyFonts\Integrations\BricksIntegrationService;
use TastyFonts\Integrations\OxygenIntegrationService;
use TastyFonts\Repository\SettingsRepository;
use WP_Theme_JSON_Data;

final class RuntimeService
{
    /**
     * Create the runtime service.
     *
     * @since 1.4.0
     *
     * @param RuntimeAssetPlanner $planner Planner that resolves runtime stylesheets and editor presets.
     * @param AssetService $assets Asset service for generated CSS handles and preload URLs.
     * @param AdobeProjectClient $adobe Adobe client used to version Adobe-hosted stylesheet handles.
     */
    public function __construct(
        private readonly RuntimeAssetPlanner $planner,
        private readonly AssetService $assets,
        private readonly AdobeProjectClient $adobe,
        private readonly SettingsRepository $settings,
        private readonly AcssIntegrationService $acssIntegration,
        private readonly BricksIntegrationService $bricksIntegration,
        private readonly OxygenIntegrationService $oxygenIntegration
    ) {
    }

    /**
     * Enqueue frontend font assets for the live site.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function enqueueFrontend(): void
    {
        $this->assets->enqueue('tasty-fonts-frontend');
        $this->enqueueExternalStylesheets($this->planner->getExternalStylesheets());

        if ($this->hasEtchCanvasRequest()) {
            $this->enqueueEtchCanvasBridge();
        }
    }

    /**
     * Output preload and preconnect hints for active runtime font assets.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function outputPreloadHints(): void
    {
        if (is_admin() || $this->hasEtchCanvasRequest()) {
            return;
        }

        foreach ($this->planner->getPreconnectOrigins() as $origin) {
            if (!is_string($origin) || trim($origin) === '') {
                continue;
            }

            echo '<link rel="preconnect" href="' . esc_url($origin) . '" crossorigin>' . "\n";
        }

        if ($this->assets->isInlineDeliveryEnabled()) {
            return;
        }

        foreach ($this->assets->getPrimaryFontPreloadUrls() as $url) {
            if (!is_string($url) || trim($url) === '') {
                continue;
            }

            echo '<link rel="preload" href="' . esc_url($url) . '" as="font" type="font/woff2" crossorigin>' . "\n";
        }
    }

    /**
     * Enqueue font assets for the Etch canvas runtime.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function enqueueEtchCanvas(): void
    {
        $this->assets->enqueue('tasty-fonts-etch');
        $this->enqueueExternalStylesheets($this->planner->getExternalStylesheets());
    }

    /**
     * Enqueue font assets inside the block editor.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function enqueueBlockEditor(): void
    {
        $this->assets->enqueue('tasty-fonts-editor');
        $this->enqueueExternalStylesheets($this->planner->getExternalStylesheets());
    }

    /**
     * Enqueue content styles that Gutenberg hoists into the editor canvas iframe.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function enqueueBlockEditorContent(): void
    {
        if (!is_admin()) {
            return;
        }

        $this->assets->enqueue('tasty-fonts-editor-content');
        $this->enqueueExternalStylesheets($this->planner->getExternalStylesheets(), 'editor-content');
    }

    /**
     * Enqueue preview fonts for the plugin's admin screens.
     *
     * @since 1.4.0
     *
     * @param string $hookSuffix Current admin hook suffix.
     * @return void
     */
    public function enqueueAdminScreenFonts(string $hookSuffix): void
    {
        if (!\TastyFonts\Admin\AdminController::isPluginAdminHook($hookSuffix)) {
            return;
        }

        $this->assets->enqueueFontFacesOnly('tasty-fonts-admin-fonts');
        $this->enqueueExternalStylesheets($this->planner->getAdminPreviewStylesheets());
    }

    /**
     * Inject plugin-managed font families into the editor theme JSON settings.
     *
     * @since 1.4.0
     *
     * @param WP_Theme_JSON_Data $themeJson Theme JSON object passed through the WordPress filter.
     * @return WP_Theme_JSON_Data Updated theme JSON data.
     */
    public function injectEditorFontPresets(WP_Theme_JSON_Data $themeJson): WP_Theme_JSON_Data
    {
        $fontFamilies = $this->planner->getEditorFontFamilies();

        if ($fontFamilies === []) {
            return $themeJson;
        }

        $existingData = $themeJson->get_data();
        $schemaVersion = (int) ($existingData['version'] ?? 3);

        return $themeJson->update_with(
            [
                'version' => $schemaVersion,
                'settings' => [
                    'typography' => [
                        'fontFamilies' => $fontFamilies,
                    ],
                ],
            ]
        );
    }

    public function filterBlockEditorSettings(array $editorSettings, mixed $editorContext = null): array
    {
        $styles = [];
        $runtimeFamilies = $this->planner->getRuntimeFamilies();
        $settings = $this->settings->getSettings();

        if (
            !empty($settings['auto_apply_roles'])
            && ($settings['acss_font_role_sync_enabled'] ?? null) === true
            && !empty($settings['acss_font_role_sync_applied'])
            && $this->acssIntegration->isAvailable()
        ) {
            $styles = array_merge($styles, $this->acssIntegration->getManagedEditorStyles());
        }

        if ($this->builderIntegrationEnabled('bricks') && $this->bricksIntegration->isAvailable()) {
            $styles = array_merge($styles, $this->bricksIntegration->getEditorStyles($runtimeFamilies));
        }

        if ($this->builderIntegrationEnabled('oxygen') && $this->oxygenIntegration->isAvailable()) {
            $styles = array_merge($styles, $this->oxygenIntegration->getEditorStyles($runtimeFamilies));
        }

        $styles = array_values(array_unique(array_filter($styles, 'strlen')));

        if ($styles === []) {
            return $editorSettings;
        }

        $editorSettings['styles'] = is_array($editorSettings['styles'] ?? null) ? $editorSettings['styles'] : [];
        $editorSettings['styles'][] = ['css' => implode("\n", $styles)];

        return $editorSettings;
    }

    public function filterBricksStandardFonts(array $fonts): array
    {
        if (!$this->builderIntegrationEnabled('bricks') || !$this->bricksIntegration->isAvailable()) {
            return $fonts;
        }

        return $this->bricksIntegration->filterStandardFonts($fonts, $this->planner->getRuntimeFamilies());
    }

    public function registerOxygenCompatibilityShim(): void
    {
        if (!$this->builderIntegrationEnabled('oxygen') || !$this->oxygenIntegration->isAvailable()) {
            return;
        }

        $this->oxygenIntegration->registerCompatibilityShim($this->planner->getRuntimeFamilies());
    }

    public function filterExternalStylesheetTag(string $html, string $handle, string $href, string $media): string
    {
        if (!$this->isExternalStylesheetHandle($handle) || str_contains($html, ' crossorigin=')) {
            return $html;
        }

        return preg_replace('/(?=\s*\/?>$)/', ' crossorigin="anonymous"', $html, 1) ?: $html;
    }

    private function enqueueEtchCanvasBridge(): void
    {
        $stylesheetUrls = $this->getCanvasStylesheetUrls();

        if ($stylesheetUrls === []) {
            return;
        }

        wp_enqueue_script(
            'tasty-fonts-canvas-contracts',
            TASTY_FONTS_URL . 'assets/js/canvas-contracts.js',
            [],
            TASTY_FONTS_VERSION,
            true
        );

        wp_enqueue_script(
            'tasty-fonts-canvas',
            TASTY_FONTS_URL . 'assets/js/tasty-canvas.js',
            ['tasty-fonts-canvas-contracts'],
            TASTY_FONTS_VERSION,
            true
        );

        wp_localize_script(
            'tasty-fonts-canvas',
            'TastyFontsCanvas',
            [
                'stylesheetUrl' => $stylesheetUrls[0] ?? '',
                'stylesheetUrls' => $stylesheetUrls,
            ]
        );
    }

    private function enqueueExternalStylesheets(array $stylesheets, string $handleSuffix = ''): void
    {
        foreach ($stylesheets as $stylesheet) {
            if (!is_array($stylesheet)) {
                continue;
            }

            $handle = $this->stylesheetHandle($stylesheet, $handleSuffix);
            $url = (string) ($stylesheet['url'] ?? '');

            if ($handle === '' || $url === '') {
                continue;
            }

            wp_enqueue_style($handle, $url, [], $this->stylesheetVersion($stylesheet));
        }
    }

    private function getCanvasStylesheetUrls(): array
    {
        $urls = [];
        $generatedUrl = $this->assets->getVersionedStylesheetUrl();

        if ($generatedUrl) {
            $urls[] = $generatedUrl;
        }

        foreach ($this->planner->getExternalStylesheets() as $stylesheet) {
            if (!is_array($stylesheet)) {
                continue;
            }

            $url = (string) ($stylesheet['url'] ?? '');

            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return array_values(array_unique(array_filter($urls, 'strlen')));
    }

    private function stylesheetVersion(array $stylesheet): string
    {
        $provider = strtolower(trim((string) ($stylesheet['provider'] ?? '')));

        return $provider === 'adobe'
            ? $this->adobe->getEnqueueVersion()
            : TASTY_FONTS_VERSION;
    }

    private function stylesheetHandle(array $stylesheet, string $handleSuffix = ''): string
    {
        $handle = trim((string) ($stylesheet['handle'] ?? ''));

        if ($handle === '' || $handleSuffix === '') {
            return $handle;
        }

        $suffix = strtolower(trim((string) preg_replace('/[^a-z0-9_-]+/i', '-', $handleSuffix), '-'));

        return $suffix === '' ? $handle : $handle . '-' . $suffix;
    }

    private function isExternalStylesheetHandle(string $handle): bool
    {
        foreach (['google', 'bunny', 'adobe'] as $provider) {
            if (str_starts_with($handle, 'tasty-fonts-' . $provider . '-')) {
                return true;
            }
        }

        return false;
    }

    private function builderIntegrationEnabled(string $builder): bool
    {
        $settings = $this->settings->getSettings();
        $key = match ($builder) {
            'bricks' => 'bricks_integration_enabled',
            'oxygen' => 'oxygen_integration_enabled',
            default => '',
        };

        if ($key === '') {
            return false;
        }

        $value = $settings[$key] ?? null;

        return $value !== false;
    }

    private function hasEtchCanvasRequest(): bool
    {
        if (is_admin()) {
            return false;
        }

        $etch = isset($_GET['etch']) ? sanitize_text_field(wp_unslash((string) $_GET['etch'])) : '';

        return $etch !== '';
    }
}
