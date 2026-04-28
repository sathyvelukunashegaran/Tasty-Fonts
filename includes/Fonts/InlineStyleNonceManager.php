<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * Applies configured CSP nonces to this plugin's inline style output.
 */
final class InlineStyleNonceManager
{
    /** @var array<string, string> */
    private array $inlineStyleNonces = [];

    private bool $outputBufferStarted = false;

    /**
     * Register nonce handling for a WordPress inline style handle.
     */
    public function arm(string $handle, string $css, string $context): void
    {
        $nonce = $this->getInlineStyleNonce($handle, $css, $context);

        if ($nonce === '' || !$this->shouldUsePluginInlineStyleNonceStrategy($handle, $css, $context)) {
            return;
        }

        $this->inlineStyleNonces[$handle . '-inline-css'] = $nonce;

        if (
            $this->outputBufferStarted
            || PHP_SAPI === 'cli'
            || PHP_SAPI === 'phpdbg'
        ) {
            return;
        }

        ob_start([$this, 'filterOutputBuffer']);
        $this->outputBufferStarted = true;
    }

    /**
     * Add configured nonce attributes to registered inline style tags.
     */
    public function filterOutputBuffer(string $html): string
    {
        if ($html === '' || $this->inlineStyleNonces === [] || !str_contains($html, '<style')) {
            return $html;
        }

        $styleIds = array_keys($this->inlineStyleNonces);
        $pattern = '/<style\b(?P<before>[^>]*)\bid=(["\'])(?P<id>' . implode('|', array_map('preg_quote', $styleIds)) . ')\2(?P<after>[^>]*)>/i';
        $filtered = preg_replace_callback(
            $pattern,
            function (array $matches): string {
                $styleId = (string) ($matches['id'] ?? '');
                $nonce = (string) ($this->inlineStyleNonces[$styleId] ?? '');

                if ($nonce === '') {
                    return (string) $matches[0];
                }

                $attributes = (string) ($matches['before'] ?? '') . ' ' . (string) ($matches['after'] ?? '');

                if (preg_match('/\snonce\s*=/i', $attributes) === 1) {
                    return (string) $matches[0];
                }

                $tag = (string) $matches[0];
                $position = strrpos($tag, '>');

                if ($position === false) {
                    return $tag;
                }

                return substr($tag, 0, $position)
                    . ' nonce="' . esc_attr($nonce) . '"'
                    . substr($tag, $position);
            },
            $html
        );

        return is_string($filtered) ? $filtered : $html;
    }

    private function shouldUsePluginInlineStyleNonceStrategy(string $handle, string $css, string $context): bool
    {
        $strategy = strtolower(trim(FontUtils::scalarStringValue(apply_filters(
            'tasty_fonts_inline_style_nonce_strategy',
            'auto',
            $handle,
            $context,
            $css
        ))));

        if (in_array($strategy, ['off', 'disabled', 'none', 'core'], true)) {
            return false;
        }

        if ($strategy === 'auto' && $this->coreSupportsInlineStyleNonceHandling()) {
            return false;
        }

        return in_array($strategy, ['auto', 'buffer', 'output_buffer', 'plugin'], true);
    }

    private function getInlineStyleNonce(string $handle, string $css, string $context): string
    {
        $nonce = apply_filters('tasty_fonts_inline_style_nonce', '', $handle, $css, $context);

        return is_string($nonce) ? trim($nonce) : '';
    }

    private function coreSupportsInlineStyleNonceHandling(): bool
    {
        return function_exists('wp_get_inline_style_tag') || function_exists('wp_print_inline_style_tag');
    }
}
