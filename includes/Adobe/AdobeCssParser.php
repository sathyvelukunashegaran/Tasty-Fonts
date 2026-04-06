<?php

declare(strict_types=1);

namespace TastyFonts\Adobe;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

final class AdobeCssParser
{
    public function parseFamilies(string $css): array
    {
        $matchCount = preg_match_all('/@font-face\s*\{(.*?)\}/si', $css, $matches);

        if ($matchCount === false || empty($matches[1])) {
            return [];
        }

        $families = [];

        foreach ($matches[1] as $block) {
            $face = $this->buildFace($block);

            if ($face === null) {
                continue;
            }

            $familyName = (string) $face['family'];
            $familyKey = strtolower($familyName);
            $faceKey = FontUtils::faceAxisKey((string) $face['weight'], (string) $face['style']);

            if (!isset($families[$familyKey])) {
                $families[$familyKey] = [
                    'family' => $familyName,
                    'slug' => FontUtils::slugify($familyName),
                    'source' => 'adobe',
                    'faces' => [],
                ];
            }

            $families[$familyKey]['faces'][$faceKey] = [
                'weight' => (string) $face['weight'],
                'style' => (string) $face['style'],
            ];
        }

        foreach ($families as &$family) {
            $family['faces'] = array_values((array) $family['faces']);
            usort($family['faces'], [FontUtils::class, 'compareFacesByWeightAndStyle']);
        }
        unset($family);

        uasort(
            $families,
            static fn (array $left, array $right): int => strcasecmp(
                (string) ($left['family'] ?? ''),
                (string) ($right['family'] ?? '')
            )
        );

        return array_values($families);
    }

    private function buildFace(string $block): ?array
    {
        $family = $this->trimCssString($this->propertyValue($block, 'font-family'));

        if ($family === '') {
            return null;
        }

        $src = $this->propertyValue($block, 'src');

        if ($src === '' || stripos($src, 'url(') === false) {
            return null;
        }

        return [
            'family' => $family,
            'weight' => FontUtils::normalizeWeight($this->propertyValue($block, 'font-weight') ?: '400'),
            'style' => FontUtils::normalizeStyle($this->propertyValue($block, 'font-style') ?: 'normal'),
        ];
    }

    private function propertyValue(string $block, string $property): string
    {
        if (preg_match('/' . preg_quote($property, '/') . '\s*:\s*([^;]+);/i', $block, $matches) !== 1) {
            return '';
        }

        return trim((string) $matches[1]);
    }

    private function trimCssString(string $value): string
    {
        $value = trim($value);

        return trim($value, "\"'");
    }
}
