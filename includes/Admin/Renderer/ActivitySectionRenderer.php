<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

final class ActivitySectionRenderer extends AbstractSectionRenderer
{
    /**
     * @param array<string, mixed> $view
     */
    public function render(array $view): void
    {
        $this->renderTemplate('activity-section.php', $view);
    }
}
