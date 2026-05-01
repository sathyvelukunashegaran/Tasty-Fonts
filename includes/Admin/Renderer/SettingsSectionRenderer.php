<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

final class SettingsSectionRenderer extends AbstractSectionRenderer
{
    private ?SnippetHighlighter $snippetHighlighter = null;

    public function render(array $view): void
    {
        $this->renderTemplate('settings-section.php', $view);
    }

    public function renderHighlightedSnippet(string $value): void
    {
        $this->snippetHighlighter()->renderHighlightedSnippet($value);
    }

    private function snippetHighlighter(): SnippetHighlighter
    {
        if (!$this->snippetHighlighter instanceof SnippetHighlighter) {
            $this->snippetHighlighter = new SnippetHighlighter();
        }

        return $this->snippetHighlighter;
    }
}
