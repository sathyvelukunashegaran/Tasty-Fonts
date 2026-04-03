<?php

declare(strict_types=1);

namespace EtchFonts\Fonts;

use EtchFonts\Support\FontUtils;
use WP_Theme_JSON_Data;

final class RuntimeService
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly AssetService $assets
    ) {
    }

    public function enqueueFrontend(): void
    {
        $this->assets->enqueue('etch-fonts-frontend');

        if ($this->hasEtchCanvasRequest()) {
            $this->enqueueEtchCanvasBridge();
        }
    }

    public function enqueueEtchCanvas(): void
    {
        $this->assets->enqueue('etch-fonts-etch');
    }

    public function enqueueBlockEditor(): void
    {
        $this->assets->enqueue('etch-fonts-editor');
    }

    public function enqueueAdminScreenFonts(string $hookSuffix): void
    {
        if (!\EtchFonts\Admin\AdminController::isPluginAdminHook($hookSuffix)) {
            return;
        }

        $this->assets->enqueue('etch-fonts-admin-fonts');
    }

    public function injectEditorFontPresets(WP_Theme_JSON_Data $themeJson): WP_Theme_JSON_Data
    {
        $fontFamilies = $this->buildEditorFontFamilies();

        if ($fontFamilies === []) {
            return $themeJson;
        }

        return $themeJson->update_with(
            [
                'version' => 3,
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
        $stylesheetUrl = $this->assets->getVersionedStylesheetUrl();

        if (!$stylesheetUrl) {
            return;
        }

        wp_enqueue_script(
            'etch-fonts-canvas',
            ETCH_FONTS_URL . 'assets/js/etch-canvas.js',
            [],
            ETCH_FONTS_VERSION,
            true
        );

        wp_localize_script(
            'etch-fonts-canvas',
            'EtchFontsCanvas',
            ['stylesheetUrl' => $stylesheetUrl]
        );
    }

    private function buildEditorFontFamilies(): array
    {
        $fontFamilies = [];

        foreach ($this->catalog->getCatalog() as $family) {
            $familyName = (string) $family['family'];

            $fontFamilies[] = [
                'name' => $familyName,
                'slug' => $family['slug'],
                'fontFamily' => FontUtils::buildFontStack($familyName, 'sans-serif'),
            ];
        }

        return $fontFamilies;
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
