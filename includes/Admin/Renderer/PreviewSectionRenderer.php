<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

final class PreviewSectionRenderer extends AbstractSectionRenderer
{
    public function render(array $view): void
    {
        $this->renderTemplate('preview-section.php', $view);
    }
}
