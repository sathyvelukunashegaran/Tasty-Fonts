<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\SettingsRepository;

/**
 * Delivers generated CSS through WordPress runtime and admin preview style handles.
 */
final class GeneratedCssDelivery
{
    private const INLINE_STYLE_CONTEXT_RUNTIME = 'runtime';
    private const INLINE_STYLE_CONTEXT_ADMIN_PREVIEW = 'admin_preview';

    public function __construct(
        private readonly GeneratedCssCache $cssCache,
        private readonly GeneratedStylesheetFile $stylesheetFile,
        private readonly InlineStyleNonceManager $inlineStyleNonceManager,
        private readonly RuntimeAssetPlanner $planner,
        private readonly SettingsRepository $settings,
        private readonly CssBuilder $cssBuilder
    ) {
    }

    /**
     * Enqueue the generated stylesheet file or its inline fallback.
     */
    public function enqueueGeneratedCss(string $handle): void
    {
        $css = $this->cssCache->getCss();
        $state = $this->stylesheetFile->getState();

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
            $this->inlineStyleNonceManager->arm($handle, $css, self::INLINE_STYLE_CONTEXT_RUNTIME);
        }

        $this->stylesheetFile->writeFile($state);
    }

    /**
     * Enqueue local @font-face rules for plugin admin previews.
     */
    public function enqueuePreviewFontFacesOnly(string $handle): void
    {
        $catalog = $this->planner->getLocalPreviewCatalog();
        $settings = $this->settings->getSettings();
        $css = $this->cssBuilder->buildFontFaceOnly($catalog, $settings, 'swap');

        wp_register_style($handle, false);
        wp_enqueue_style($handle);

        if ($css !== '') {
            wp_add_inline_style($handle, $css);
            $this->inlineStyleNonceManager->arm($handle, $css, self::INLINE_STYLE_CONTEXT_ADMIN_PREVIEW);
        }
    }

    /**
     * Whether runtime generated CSS should be delivered inline for this request.
     */
    public function isInlineDeliveryEnabled(): bool
    {
        $settings = $this->settings->getSettings();

        return ($settings['css_delivery_mode'] ?? 'file') === 'inline';
    }
}
