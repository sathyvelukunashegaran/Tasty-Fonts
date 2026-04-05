<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Support\FontUtils;
use WP_Theme_JSON_Data;

final class RuntimeService
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly AssetService $assets,
        private readonly AdobeProjectClient $adobe
    ) {
    }

    public function enqueueFrontend(): void
    {
        $this->assets->enqueue('tasty-fonts-frontend');
        $this->enqueueAdobeStylesheet('tasty-fonts-adobe-frontend');

        if ($this->hasEtchCanvasRequest()) {
            $this->enqueueEtchCanvasBridge();
        }
    }

    public function outputPreloadHints(): void
    {
        if (is_admin() || $this->hasEtchCanvasRequest()) {
            return;
        }

        foreach ($this->assets->getPrimaryFontPreloadUrls() as $url) {
            if (!is_string($url) || trim($url) === '') {
                continue;
            }

            echo '<link rel="preload" href="' . esc_url($url) . '" as="font" type="font/woff2" crossorigin>' . "\n";
        }
    }

    public function enqueueEtchCanvas(): void
    {
        $this->assets->enqueue('tasty-fonts-etch');
        $this->enqueueAdobeStylesheet('tasty-fonts-adobe-etch');
    }

    public function enqueueBlockEditor(): void
    {
        $this->assets->enqueue('tasty-fonts-editor');
        $this->enqueueAdobeStylesheet('tasty-fonts-adobe-editor');
    }

    public function enqueueAdminScreenFonts(string $hookSuffix): void
    {
        if (!\TastyFonts\Admin\AdminController::isPluginAdminHook($hookSuffix)) {
            return;
        }

        $this->assets->enqueueFontFacesOnly('tasty-fonts-admin-fonts');
        $this->enqueueAdobeStylesheet('tasty-fonts-adobe-admin');
    }

    public function injectEditorFontPresets(WP_Theme_JSON_Data $themeJson): WP_Theme_JSON_Data
    {
        $fontFamilies = $this->buildEditorFontFamilies();

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

    private function enqueueEtchCanvasBridge(): void
    {
        $stylesheetUrls = $this->getCanvasStylesheetUrls();

        if ($stylesheetUrls === []) {
            return;
        }

        wp_enqueue_script(
            'tasty-fonts-canvas',
            TASTY_FONTS_URL . 'assets/js/tasty-canvas.js',
            [],
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

    private function buildEditorFontFamilies(): array
    {
        $fontFamilies = [];

        foreach ($this->catalog->getCatalog() as $family) {
            $familyName = (string) $family['family'];

            $fontFamilies[$familyName] = [
                'name' => $familyName,
                'slug' => $family['slug'],
                'fontFamily' => FontUtils::buildFontStack($familyName, 'sans-serif'),
            ];
        }

        foreach ($this->adobe->getConfiguredFamilies() as $family) {
            $familyName = (string) ($family['family'] ?? '');

            if ($familyName === '' || isset($fontFamilies[$familyName])) {
                continue;
            }

            $fontFamilies[$familyName] = [
                'name' => $familyName,
                'slug' => (string) ($family['slug'] ?? FontUtils::slugify($familyName)),
                'fontFamily' => FontUtils::buildFontStack($familyName, 'sans-serif'),
            ];
        }

        return array_values($fontFamilies);
    }

    private function enqueueAdobeStylesheet(string $handle): void
    {
        if (!$this->adobe->canEnqueue()) {
            return;
        }

        $url = $this->adobe->getStylesheetUrl($this->adobe->getProjectId());

        if ($url === '') {
            return;
        }

        wp_enqueue_style($handle, $url, [], $this->adobe->getEnqueueVersion());
    }

    private function getCanvasStylesheetUrls(): array
    {
        $urls = [];
        $generatedUrl = $this->assets->getVersionedStylesheetUrl();

        if ($generatedUrl) {
            $urls[] = $generatedUrl;
        }

        if ($this->adobe->canEnqueue()) {
            $adobeUrl = $this->adobe->getStylesheetUrl($this->adobe->getProjectId());

            if ($adobeUrl !== '') {
                $urls[] = add_query_arg('ver', $this->adobe->getEnqueueVersion(), $adobeUrl);
            }
        }

        return array_values(array_unique(array_filter($urls, 'strlen')));
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
