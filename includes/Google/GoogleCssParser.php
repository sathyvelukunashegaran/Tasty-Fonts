<?php

declare(strict_types=1);

namespace EtchFonts\Google;

use EtchFonts\Support\FontUtils;

final class GoogleCssParser
{
    public function parse(string $css, string $expectedFamily = ''): array
    {
        $matchCount = preg_match_all('/@font-face\s*\{(.*?)\}/si', $css, $matches);

        if ($matchCount === false || empty($matches[1])) {
            return [];
        }

        $faces = [];

        foreach ($matches[1] as $block) {
            $face = $this->buildFace($block, $expectedFamily);

            if ($face !== null) {
                $faces[] = $face;
            }
        }

        return $faces;
    }

    private function propertyValue(string $block, string $property): string
    {
        if (preg_match('/' . preg_quote($property, '/') . '\s*:\s*([^;]+);/i', $block, $matches) !== 1) {
            return '';
        }

        return trim($matches[1]);
    }

    private function extractFiles(string $src): array
    {
        $files = [];

        $matchCount = preg_match_all('/url\(([^)]+)\)\s*format\(([^)]+)\)/i', $src, $matches, PREG_SET_ORDER);

        if (is_int($matchCount) && $matchCount > 0) {
            foreach ($matches as $match) {
                $url = $this->trimCssString($match[1]);
                $format = strtolower($this->trimCssString($match[2]));

                if ($url === '' || $format !== 'woff2') {
                    continue;
                }

                $files['woff2'] = $url;
            }
        }

        if ($files === [] && preg_match('/url\(([^)]+\.woff2[^)]*)\)/i', $src, $fallback) === 1) {
            $files['woff2'] = $this->trimCssString($fallback[1]);
        }

        return $files;
    }

    private function buildFace(string $block, string $expectedFamily): ?array
    {
        $family = $this->trimCssString($this->propertyValue($block, 'font-family'));

        if ($family === '') {
            return null;
        }

        if ($expectedFamily !== '' && strcasecmp($expectedFamily, $family) !== 0) {
            return null;
        }

        $files = $this->extractFiles($this->propertyValue($block, 'src'));

        if ($files === []) {
            return null;
        }

        return [
            'family' => $family,
            'slug' => FontUtils::slugify($family),
            'source' => 'google',
            'weight' => FontUtils::normalizeWeight($this->propertyValue($block, 'font-weight') ?: '400'),
            'style' => FontUtils::normalizeStyle($this->propertyValue($block, 'font-style') ?: 'normal'),
            'unicode_range' => trim((string) ($this->propertyValue($block, 'unicode-range') ?: '')),
            'files' => $files,
            'provider' => ['type' => 'google'],
        ];
    }

    private function trimCssString(string $value): string
    {
        $value = trim($value);

        return trim($value, "\"'");
    }
}
