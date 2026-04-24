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
        $readableTarget = $target !== '' ? $target . '-readable' : '';
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
                        data-label-default="<?php esc_attr_e('Readable Preview', 'tasty-fonts'); ?>"
                        data-label-active="<?php esc_attr_e('Show Actual Output', 'tasty-fonts'); ?>"
                        aria-pressed="false"
                        aria-controls="<?php echo esc_attr(trim($target . ' ' . $readableTarget)); ?>"
                    >
                        <?php esc_html_e('Readable Preview', 'tasty-fonts'); ?>
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
            <pre class="tasty-fonts-output" data-snippet-view="raw" aria-labelledby="<?php echo esc_attr($headingId); ?>"><code id="<?php echo esc_attr($target); ?>" class="tasty-fonts-output-code"><?php $this->renderHighlightedSnippet($displayValue); ?></code></pre>
            <?php if ($canToggleReadableView): ?>
                <pre class="tasty-fonts-output" data-snippet-view="readable" aria-labelledby="<?php echo esc_attr($headingId); ?>" hidden><code id="<?php echo esc_attr($readableTarget); ?>" class="tasty-fonts-output-code"><?php $this->renderHighlightedSnippet($readableDisplayValue); ?></code></pre>
            <?php endif; ?>
        </div>
        <?php
    }

    public function formatSnippetForDisplay(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '' || preg_match("/\r\n|\n|\r/", $trimmed) === 1) {
            return $value;
        }

        if ($this->looksLikeCssSnippet($trimmed)) {
            return $this->prettyPrintCssSnippet($trimmed);
        }

        if ($this->looksLikeCssDeclarationList($trimmed)) {
            return $this->prettyPrintCssDeclarationList($trimmed);
        }

        return $value;
    }

    public function looksLikeCssSnippet(string $value): bool
    {
        return str_contains($value, '{')
            && str_contains($value, '}')
            && str_contains($value, ':');
    }

    public function looksLikeCssDeclarationList(string $value): bool
    {
        return !str_contains($value, '{')
            && !str_contains($value, '}')
            && str_contains($value, ';')
            && preg_match('/(?:^|;)\s*--[\w-]+\s*:/', $value) === 1;
    }

    public function prettyPrintCssSnippet(string $value): string
    {
        $lines = [];
        $current = '';
        $indentLevel = 0;
        $quote = null;
        $escapeNext = false;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];
            $next = $index + 1 < $length ? $value[$index + 1] : null;

            if ($quote !== null) {
                $current .= $character;

                if ($escapeNext) {
                    $escapeNext = false;
                    continue;
                }

                if ($character === '\\') {
                    $escapeNext = true;
                    continue;
                }

                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === '\'') {
                $quote = $character;
                $current .= $character;
                continue;
            }

            if ($character === '/' && $next === '*') {
                $commentEnd = strpos($value, '*/', $index + 2);

                if ($commentEnd === false) {
                    $current .= substr($value, $index);
                    break;
                }

                $this->appendFormattedCssLine($lines, $current, $indentLevel);
                $comment = trim(substr($value, $index, ($commentEnd - $index) + 2));

                if ($comment !== '') {
                    $lines[] = str_repeat('  ', $indentLevel) . $comment;
                }

                $current = '';
                $index = $commentEnd + 1;
                continue;
            }

            if (preg_match('/\s/', $character) === 1) {
                if ($current !== '' && !preg_match('/\s$/', $current)) {
                    $current .= ' ';
                }

                continue;
            }

            if ($character === '{') {
                $selector = trim($current);
                $line = $selector !== '' ? $selector . ' {' : '{';
                $lines[] = str_repeat('  ', $indentLevel) . $line;
                $current = '';
                $indentLevel++;
                continue;
            }

            if ($character === ';') {
                $current .= ';';
                $this->appendFormattedCssLine($lines, $current, $indentLevel);
                $current = '';
                continue;
            }

            if ($character === '}') {
                $this->appendFormattedCssLine($lines, $current, $indentLevel);
                $current = '';
                $indentLevel = max(0, $indentLevel - 1);
                $lines[] = str_repeat('  ', $indentLevel) . '}';
                continue;
            }

            $current .= $character;
        }

        $this->appendFormattedCssLine($lines, $current, $indentLevel);

        return implode("\n", $lines);
    }

    public function prettyPrintCssDeclarationList(string $value): string
    {
        $lines = [];
        $current = '';
        $quote = null;
        $escapeNext = false;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];

            if ($quote !== null) {
                $current .= $character;

                if ($escapeNext) {
                    $escapeNext = false;
                    continue;
                }

                if ($character === '\\') {
                    $escapeNext = true;
                    continue;
                }

                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === '\'') {
                $quote = $character;
                $current .= $character;
                continue;
            }

            $current .= $character;

            if ($character === ';') {
                $this->appendFormattedCssLine($lines, $current, 0);
                $current = '';
            }
        }

        $this->appendFormattedCssLine($lines, $current, 0);

        return implode("\n", $lines);
    }

    /**
     * @param CssLineList $lines
     */
    public function appendFormattedCssLine(array &$lines, string $line, int $indentLevel): void
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return;
        }

        $lines[] = str_repeat('  ', $indentLevel) . $trimmed;
    }

    public function renderHighlightedSnippet(string $value): void
    {
        $lines = preg_split("/\r\n|\n|\r/", $value);

        if ($lines === false) {
            echo esc_html($value);

            return;
        }

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                echo "\n";
            }

            echo $this->highlightSnippetLine($line);
        }
    }

    public function highlightSnippetLine(string $line): string
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^\s*(\/\*.*\*\/|\*\/|\*)/', $line) === 1) {
            return '<span class="tasty-fonts-syntax-comment">' . esc_html($line) . '</span>';
        }

        if (preg_match('/^(\s*)(@[\w-]+)(\s+[^{};]+)?(\s*\{?\s*;?\s*)$/', $line, $matches) === 1) {
            return esc_html($matches[1])
                . '<span class="tasty-fonts-syntax-at-rule">' . esc_html($matches[2]) . '</span>'
                . $this->highlightSnippetValue((string) $matches[3])
                . '<span class="tasty-fonts-syntax-punctuation">' . esc_html((string) $matches[4]) . '</span>';
        }

        if (preg_match('/^(\s*)([^{}]+)(\s*\{\s*)$/', $line, $matches) === 1) {
            return esc_html($matches[1])
                . '<span class="tasty-fonts-syntax-selector">' . esc_html(trim((string) $matches[2])) . '</span>'
                . '<span class="tasty-fonts-syntax-punctuation">' . esc_html((string) $matches[3]) . '</span>';
        }

        if (preg_match('/^(\s*)(--[\w-]+|[\w-]+)(\s*:\s*)(.+?)(\s*[;,]?\s*)$/', $line, $matches) === 1) {
            return esc_html($matches[1])
                . '<span class="tasty-fonts-syntax-property">' . esc_html($matches[2]) . '</span>'
                . '<span class="tasty-fonts-syntax-punctuation">' . esc_html($matches[3]) . '</span>'
                . $this->highlightSnippetValue($matches[4])
                . '<span class="tasty-fonts-syntax-punctuation">' . esc_html((string) $matches[5]) . '</span>';
        }

        if (preg_match('/^(\s*)([{}])(\s*)$/', $line, $matches) === 1) {
            return esc_html($matches[1])
                . '<span class="tasty-fonts-syntax-punctuation">' . esc_html($matches[2]) . '</span>'
                . esc_html((string) $matches[3]);
        }

        return esc_html($line);
    }

    public function highlightSnippetValue(string $value): string
    {
        $parts = preg_split('/(".*?"|\'.*?\')/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return esc_html($value);
        }

        $highlighted = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (($part[0] === '"' && str_ends_with($part, '"')) || ($part[0] === '\'' && str_ends_with($part, '\''))) {
                $highlighted .= '<span class="tasty-fonts-syntax-string">' . esc_html($part) . '</span>';
                continue;
            }

            $escaped = esc_html($part);
            $escaped = preg_replace('/(var\()(--[\w-]+)(\))/', '<span class="tasty-fonts-syntax-function">$1</span><span class="tasty-fonts-syntax-variable">$2</span><span class="tasty-fonts-syntax-punctuation">$3</span>', $escaped);
            $escaped = preg_replace('/(?<![\w-])(--[\w-]+)/', '<span class="tasty-fonts-syntax-variable">$1</span>', (string) $escaped);
            $escaped = preg_replace('/(?<![\w-])(#[0-9a-fA-F]{3,8})/', '<span class="tasty-fonts-syntax-number">$1</span>', (string) $escaped);
            $escaped = preg_replace('/(?<![\w-])(\\d+(?:\\.\\d+)?(?:px|rem|em|vh|vw|%|fr|ms|s)?)/', '<span class="tasty-fonts-syntax-number">$1</span>', (string) $escaped);
            $escaped = preg_replace('/(?<![\w-])(optional|swap|fallback|block|auto|none|normal|italic|inherit|initial|unset|serif|sans-serif|monospace)(?![\w-])/i', '<span class="tasty-fonts-syntax-keyword">$1</span>', (string) $escaped);

            $highlighted .= (string) $escaped;
        }

        return $highlighted;
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
