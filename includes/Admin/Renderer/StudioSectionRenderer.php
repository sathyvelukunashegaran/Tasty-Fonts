<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

final class StudioSectionRenderer extends AbstractSectionRenderer
{
    private readonly PreviewSectionRenderer $previewRenderer;
    private readonly ToolsSectionRenderer $toolsRenderer;

    public function __construct(
        \TastyFonts\Support\Storage $storage,
        ?PreviewSectionRenderer $previewRenderer = null,
        ?ToolsSectionRenderer $toolsRenderer = null
    ) {
        parent::__construct($storage);
        $this->previewRenderer = $previewRenderer ?? new PreviewSectionRenderer($storage);
        $this->toolsRenderer = $toolsRenderer ?? new ToolsSectionRenderer($storage);
    }

    public function render(array $view): void
    {
        ob_start();
        $this->previewRenderer->render($view);
        $view['embeddedPreviewSection'] = (string) ob_get_clean();

        ob_start();
        $this->toolsRenderer->render($view);
        $view['embeddedToolsSection'] = (string) ob_get_clean();

        $this->renderTemplate('studio-section.php', $view);
    }
}
