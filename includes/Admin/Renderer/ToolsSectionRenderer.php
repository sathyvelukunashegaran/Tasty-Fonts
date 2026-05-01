<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

/**
 * @phpstan-type ToolsView array<string, mixed>
 * @phpstan-type CodePanel array<string, mixed>
 * @phpstan-type CodeEditorOptions array<string, mixed>
 * @phpstan-type CssLineList list<string>
 */
final class ToolsSectionRenderer extends AbstractSectionRenderer
{
    private ?SnippetHighlighter $snippetHighlighter = null;

    /**
     * @param ToolsView $view
     */
    public function render(array $view): void
    {
        $this->renderTemplate('tools-section.php', $view);
    }

    /**
     * @param CodePanel $panel
     * @param CodeEditorOptions $options
     */
    public function renderCodeEditor(array $panel, array $options = []): void
    {
        $label = $this->stringValue($panel, 'label');
        $target = $this->stringValue($panel, 'target');
        $headingId = $target !== '' ? $this->buildElementId($target . '-label', 'tasty-fonts-code-panel-label') : 'tasty-fonts-code-panel-label';
        $value = $this->stringValue($panel, 'value');
        $hasDisplayValue = array_key_exists('display_value', $panel);
        $displayValue = $hasDisplayValue
            ? $this->stringValue($panel, 'display_value')
            : '';
        $hasReadableDisplayValue = array_key_exists('readable_display_value', $panel);
        $readableDisplayValue = $hasReadableDisplayValue
            ? $this->stringValue($panel, 'readable_display_value')
            : '';
        $preserveDisplayFormat = !empty($options['preserve_display_format']);

        if (!$hasDisplayValue) {
            $displayValue = $preserveDisplayFormat ? $value : $this->formatSnippetForDisplay($value);
        }

        if (!$hasReadableDisplayValue) {
            $readableDisplayValue = $this->formatSnippetForDisplay($value);
        }

        $canToggleReadableView = !empty($options['allow_readable_toggle'])
            && $this->looksLikeCssSnippet(trim($value))
            && $readableDisplayValue !== $displayValue;
        $defaultToReadable = $canToggleReadableView && !empty($options['default_to_readable']);
        $readableTarget = $target !== '' ? $target . '-readable' : '';
        $toggleDefaultLabel = trim($this->stringValue($options, 'toggle_label_default'));
        $toggleActiveLabel = trim($this->stringValue($options, 'toggle_label_active'));
        $toggleDefaultLabel = $toggleDefaultLabel !== ''
            ? $toggleDefaultLabel
            : __('Readable Preview', 'tasty-fonts');
        $toggleActiveLabel = $toggleActiveLabel !== ''
            ? $toggleActiveLabel
            : __('Show Actual Output', 'tasty-fonts');
        $downloadUrl = trim($this->stringValue($options, 'download_url'));
        $downloadLabelOption = trim($this->stringValue($options, 'download_label'));
        $downloadLabel = $downloadLabelOption !== ''
            ? $downloadLabelOption
            : __('Download snippet', 'tasty-fonts');
        ?>
        <div class="tasty-fonts-code-panel-head">
            <span id="<?php echo esc_attr($headingId); ?>"><?php echo esc_html($label); ?></span>
            <div class="tasty-fonts-code-panel-actions">
                <?php if ($canToggleReadableView): ?>
                    <button
                        type="button"
                        class="button tasty-fonts-output-display-toggle"
                        data-snippet-display-toggle
                        data-label-default="<?php echo esc_attr($toggleDefaultLabel); ?>"
                        data-label-active="<?php echo esc_attr($toggleActiveLabel); ?>"
                        aria-pressed="<?php echo $defaultToReadable ? 'true' : 'false'; ?>"
                        aria-controls="<?php echo esc_attr(trim($target . ' ' . $readableTarget)); ?>"
                    >
                        <?php echo esc_html($defaultToReadable ? $toggleActiveLabel : $toggleDefaultLabel); ?>
                    </button>
                <?php endif; ?>
                <?php if ($downloadUrl !== ''): ?>
                    <a
                        class="button tasty-fonts-output-download-button"
                        href="<?php echo esc_url($downloadUrl); ?>"
                        aria-label="<?php echo esc_attr($downloadLabel); ?>"
                        title="<?php echo esc_attr($downloadLabel); ?>"
                    >
                        <span class="screen-reader-text"><?php echo esc_html($downloadLabel); ?></span>
                    </a>
                <?php endif; ?>
                <button
                    type="button"
                    class="button tasty-fonts-output-copy-button"
                    data-copy-text="<?php echo esc_attr($value); ?>"
                    data-copy-static-label="1"
                    data-copy-success="<?php esc_attr_e('Snippet copied.', 'tasty-fonts'); ?>"
                    aria-label="<?php echo esc_attr(sprintf(__('Copy %s snippet', 'tasty-fonts'), $label)); ?>"
                ></button>
            </div>
        </div>
        <div class="tasty-fonts-code-panel-body" data-snippet-display aria-labelledby="<?php echo esc_attr($headingId); ?>">
            <pre class="tasty-fonts-output" data-snippet-view="raw" aria-labelledby="<?php echo esc_attr($headingId); ?>"<?php echo $defaultToReadable ? ' hidden' : ''; ?>><code id="<?php echo esc_attr($target); ?>" class="tasty-fonts-output-code"><?php $this->renderHighlightedSnippet($displayValue); ?></code></pre>
            <?php if ($canToggleReadableView): ?>
                <pre class="tasty-fonts-output" data-snippet-view="readable" aria-labelledby="<?php echo esc_attr($headingId); ?>"<?php echo $defaultToReadable ? '' : ' hidden'; ?>><code id="<?php echo esc_attr($readableTarget); ?>" class="tasty-fonts-output-code"><?php $this->renderHighlightedSnippet($readableDisplayValue); ?></code></pre>
            <?php endif; ?>
        </div>
        <?php
    }

    public function formatSnippetForDisplay(string $value): string
    {
        return $this->snippetHighlighter()->formatSnippetForDisplay($value);
    }

    public function looksLikeCssSnippet(string $value): bool
    {
        return $this->snippetHighlighter()->looksLikeCssSnippet($value);
    }

    public function looksLikeCssDeclarationList(string $value): bool
    {
        return $this->snippetHighlighter()->looksLikeCssDeclarationList($value);
    }

    public function prettyPrintCssSnippet(string $value): string
    {
        return $this->snippetHighlighter()->prettyPrintCssSnippet($value);
    }

    public function prettyPrintCssDeclarationList(string $value): string
    {
        return $this->snippetHighlighter()->prettyPrintCssDeclarationList($value);
    }

    /**
     * @param CssLineList $lines
     */
    public function appendFormattedCssLine(array &$lines, string $line, int $indentLevel): void
    {
        $this->snippetHighlighter()->appendFormattedCssLine($lines, $line, $indentLevel);
    }

    public function renderHighlightedSnippet(string $value): void
    {
        $this->snippetHighlighter()->renderHighlightedSnippet($value);
    }

    public function highlightSnippetLine(string $line): string
    {
        return $this->snippetHighlighter()->highlightSnippetLine($line);
    }

    public function highlightSnippetValue(string $value): string
    {
        return $this->snippetHighlighter()->highlightSnippetValue($value);
    }

    private function snippetHighlighter(): SnippetHighlighter
    {
        if (!$this->snippetHighlighter instanceof SnippetHighlighter) {
            $this->snippetHighlighter = new SnippetHighlighter();
        }

        return $this->snippetHighlighter;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }
}
