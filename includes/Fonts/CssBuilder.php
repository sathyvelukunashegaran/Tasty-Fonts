<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

use TastyFonts\Support\FontUtils;

final class CssBuilder
{
    private const FORMAT_ORDER = ['woff2', 'woff', 'ttf', 'otf'];

    public function build(array $catalog, array $roles, array $settings): string
    {
        return $this->buildCss($catalog, $roles, $settings, true);
    }

    public function buildFontFaceOnly(array $catalog, array $settings): string
    {
        return $this->buildCss($catalog, [], $settings, false);
    }

    private function buildCss(array $catalog, array $roles, array $settings, bool $includeRoleUsage): string
    {
        $blocks = [];
        $defaultDisplay = $this->resolveFontDisplay((string) ($settings['font_display'] ?? 'optional'));
        $familyDisplays = is_array($settings['family_font_displays'] ?? null) ? $settings['family_font_displays'] : [];

        foreach ($catalog as $family) {
            $familyName = is_array($family) ? (string) ($family['family'] ?? '') : '';
            $display = $this->resolveFamilyFontDisplay($familyName, $familyDisplays, $defaultDisplay);

            foreach ((array) ($family['faces'] ?? []) as $face) {
                $rule = $this->buildFaceRule($face, $display);

                if ($rule !== '') {
                    $blocks[] = $rule;
                }
            }
        }

        if ($includeRoleUsage && !empty($settings['auto_apply_roles'])) {
            $usageCss = $this->buildRoleUsageSnippet($roles);

            if ($usageCss !== '') {
                $blocks[] = $usageCss;
            }
        }

        $css = implode("\n", $blocks);

        if (!empty($settings['minify_css_output'])) {
            $css = $this->minify($css);
        }

        return trim($css);
    }

    public function buildRoleUsageSnippet(array $roles): string
    {
        $variableLines = $this->buildRoleVariableLines($roles);

        if ($variableLines === []) {
            return '';
        }

        return implode(
            "\n",
            [
                ...$variableLines,
                '',
                'body {',
                '  font-family: var(--font-body);',
                '}',
                '',
                'h1, h2, h3, h4, h5, h6 {',
                '  font-family: var(--font-heading);',
                '}',
            ]
        );
    }

    public function buildRoleVariableSnippet(array $roles): string
    {
        $variableLines = $this->buildRoleVariableLines($roles);

        if ($variableLines === []) {
            return '';
        }

        return implode("\n", $variableLines);
    }

    public function buildRoleStackSnippet(array $roles): string
    {
        return implode(
            "\n",
            [
                FontUtils::buildFontStack((string) ($roles['heading'] ?? ''), (string) ($roles['heading_fallback'] ?? 'sans-serif')),
                FontUtils::buildFontStack((string) ($roles['body'] ?? ''), (string) ($roles['body_fallback'] ?? 'sans-serif')),
            ]
        );
    }

    public function buildRoleNameSnippet(array $roles): string
    {
        return implode("\n", [(string) ($roles['heading'] ?? ''), (string) ($roles['body'] ?? '')]);
    }

    public function formatOutput(string $css, bool $minify = false): string
    {
        if (trim($css) === '') {
            return '';
        }

        return $minify ? $this->minify($css) : $css;
    }

    private function buildFaceRule(array $face, string $display): string
    {
        $family = (string) ($face['family'] ?? '');
        $weight = FontUtils::normalizeWeight((string) ($face['weight'] ?? '400'));
        $style = FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal'));
        $unicodeRange = trim((string) ($face['unicode_range'] ?? ''));
        $files = is_array($face['files'] ?? null) ? $face['files'] : [];

        if ($family === '' || $files === []) {
            return '';
        }

        $css = "@font-face{\n";
        $css .= '  font-family:"' . FontUtils::escapeFontFamily($family) . "\";\n";
        $css .= "  font-weight:{$weight};\n";
        $css .= "  font-style:{$style};\n";

        $sources = [];

        foreach (self::FORMAT_ORDER as $format) {
            if (!isset($files[$format])) {
                continue;
            }

            $sources[] = $this->buildSourceEntry($format, (string) $files[$format]);
        }

        if ($sources !== []) {
            $css .= "  src:" . implode(",\n      ", $sources) . ";\n";
        }

        if ($unicodeRange !== '') {
            $css .= "  unicode-range:{$unicodeRange};\n";
        }

        $css .= '  font-display:' . $this->resolveFontDisplay($display) . ";\n";
        $css .= "}\n";

        return $css;
    }

    private function resolveFamilyFontDisplay(string $family, array $familyDisplays, string $defaultDisplay): string
    {
        if ($family === '' || !array_key_exists($family, $familyDisplays)) {
            return $defaultDisplay;
        }

        return $this->resolveFontDisplay((string) $familyDisplays[$family]);
    }

    private function resolveFontDisplay(string $display): string
    {
        return $this->sanitizeKeyword(
            $display,
            ['auto', 'block', 'swap', 'fallback', 'optional'],
            'optional'
        );
    }

    private function minify(string $css): string
    {
        $css = trim($css);

        if ($css === '') {
            return '';
        }

        $result = '';
        $length = strlen($css);
        $quote = null;
        $escapeNext = false;

        for ($index = 0; $index < $length; $index++) {
            $character = $css[$index];
            $next = $index + 1 < $length ? $css[$index + 1] : null;

            if ($quote !== null) {
                $result .= $character;

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

            if ($character === '/' && $next === '*') {
                $commentEnd = strpos($css, '*/', $index + 2);

                if ($commentEnd === false) {
                    break;
                }

                $index = $commentEnd + 1;
                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $result .= $character;
                continue;
            }

            if (preg_match('/\s/', $character) === 1) {
                continue;
            }

            if ($character === ';' && $next === '}') {
                continue;
            }

            $result .= $character;
        }

        $result = str_replace(';}', '}', $result);

        return trim($result);
    }

    private function sanitizeKeyword(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function buildRoleVariableLines(array $roles): array
    {
        [$headingSlug, $bodySlug, $headingStack, $bodyStack] = $this->roleTokens($roles);

        if ($headingSlug === '' || $bodySlug === '') {
            return [];
        }

        return [
            ':root {',
            "  --font-{$headingSlug}: {$headingStack};",
            "  --font-{$bodySlug}: {$bodyStack};",
            "  --font-heading: var(--font-{$headingSlug});",
            "  --font-body: var(--font-{$bodySlug});",
            '}',
        ];
    }

    private function buildSourceEntry(string $format, string $url): string
    {
        $formatName = match ($format) {
            'otf' => 'opentype',
            'ttf' => 'truetype',
            default => $format,
        };
        $escapedUrl = esc_url($url);

        return 'url("' . $escapedUrl . '") format("' . $formatName . '")';
    }

    private function roleTokens(array $roles): array
    {
        $heading = (string) ($roles['heading'] ?? '');
        $body = (string) ($roles['body'] ?? '');

        if ($heading === '' || $body === '') {
            return ['', '', '', ''];
        }

        return [
            FontUtils::slugify($heading),
            FontUtils::slugify($body),
            FontUtils::buildFontStack($heading, (string) ($roles['heading_fallback'] ?? 'sans-serif')),
            FontUtils::buildFontStack($body, (string) ($roles['body_fallback'] ?? 'sans-serif')),
        ];
    }
}
