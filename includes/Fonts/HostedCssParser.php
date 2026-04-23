<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * @phpstan-type HostedFileMap array<string, string>
 * @phpstan-type ParsedFace array<string, mixed>
 * @phpstan-type ParsedFaceList list<ParsedFace>
 */
final class HostedCssParser
{
    public function __construct(private readonly string $source)
    {
    }

    /**
     * @return ParsedFaceList
     */
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

    /**
     * @return HostedFileMap
     */
    private function extractFiles(string $src): array
    {
        $files = [];

        $matchCount = preg_match_all('/url\(([^)]+)\)\s*format\(([^)]+)\)/i', $src, $matches, PREG_SET_ORDER);

        if (is_int($matchCount) && $matchCount > 0) {
            foreach ($matches as $match) {
                $url = $this->trimCssString($match[1]);
                $format = strtolower($this->trimCssString($match[2]));

                if ($url === '' || !in_array($format, ['woff2', 'woff2-variations'], true)) {
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

    /**
     * @return ParsedFace|null
     */
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

        $fontWeight = $this->propertyValue($block, 'font-weight');
        $weight = FontUtils::normalizeWeight($fontWeight ?: '400');
        $style = FontUtils::normalizeStyle($this->propertyValue($block, 'font-style') ?: 'normal');
        $axes = [];
        $variationDefaults = [];

        if (preg_match('/^(\d{1,4})\s+(\d{1,4})$/', $fontWeight, $matches) === 1) {
            $weight = $matches[1] . '..' . $matches[2];
            $defaultWeight = $this->inferDefaultWeightFromRange($matches[1], $matches[2]);
            $axes['WGHT'] = [
                'min' => $matches[1],
                'default' => $defaultWeight,
                'max' => $matches[2],
            ];
            $variationDefaults['WGHT'] = $defaultWeight;
        }

        if (preg_match('/^(\d{1,3})%\s+(\d{1,3})%$/', $this->propertyValue($block, 'font-stretch'), $matches) === 1) {
            $axes['WDTH'] = [
                'min' => $matches[1],
                'default' => $matches[1],
                'max' => $matches[2],
            ];
            $variationDefaults['WDTH'] = $matches[1];
        }

        $fontVariationSettings = $this->propertyValue($block, 'font-variation-settings');

        if ($fontVariationSettings !== '') {
            foreach (explode(',', $fontVariationSettings) as $setting) {
                if (preg_match('/["\']?([A-Za-z0-9]{4})["\']?\s+(-?\d+(?:\.\d+)?)/', trim($setting), $matches) !== 1) {
                    continue;
                }

                $tag = FontUtils::normalizeAxisTag($matches[1]);
                $value = FontUtils::normalizeAxisValue($matches[2]);

                if ($tag === '' || $value === '') {
                    continue;
                }

                if (!isset($axes[$tag])) {
                    $axes[$tag] = [
                        'min' => $value,
                        'default' => $value,
                        'max' => $value,
                    ];
                } else {
                    $axes[$tag]['default'] = $value;
                }

                $variationDefaults[$tag] = $value;
            }
        }

        return [
            'family' => $family,
            'slug' => FontUtils::slugify($family),
            'source' => $this->source,
            'weight' => $weight,
            'style' => $style,
            'unicode_range' => trim((string) ($this->propertyValue($block, 'unicode-range') ?: '')),
            'files' => $files,
            'provider' => ['type' => $this->source],
            'is_variable' => $axes !== [],
            'axes' => FontUtils::normalizeAxesMap($axes),
            'variation_defaults' => FontUtils::normalizeVariationDefaults($variationDefaults, $axes),
        ];
    }

    private function trimCssString(string $value): string
    {
        $value = trim($value);

        return trim($value, "\"'");
    }

    private function inferDefaultWeightFromRange(string $min, string $max): string
    {
        $minimum = (int) $min;
        $maximum = (int) $max;
        $normalWeight = 400;

        if ($normalWeight < $minimum) {
            return (string) $minimum;
        }

        if ($normalWeight > $maximum) {
            return (string) $maximum;
        }

        return (string) $normalWeight;
    }
}
