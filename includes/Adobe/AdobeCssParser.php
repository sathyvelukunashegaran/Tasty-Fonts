<?php

declare(strict_types=1);

namespace TastyFonts\Adobe;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\HostedCssParser;
use TastyFonts\Support\FontUtils;

/**
 * @phpstan-type ParsedFamily array<string, mixed>
 * @phpstan-type ParsedFamilyList list<ParsedFamily>
 */
final class AdobeCssParser
{
    /**
     * @return ParsedFamilyList
     */
    public function parseFamilies(string $css): array
    {
        $faces = (new HostedCssParser('adobe'))->parse($css);

        if ($faces === []) {
            return [];
        }

        $families = [];

        foreach ($faces as $face) {
            $familyName = $this->faceStringValue($face, 'family');
            $familyKey = strtolower($familyName);
            $weight = $this->faceStringValue($face, 'weight');
            $style = $this->faceStringValue($face, 'style');
            $axes = FontUtils::normalizeAxesMap($face['axes'] ?? []);
            $faceKey = FontUtils::faceAxisKey($weight, $style);

            if (!isset($families[$familyKey])) {
                $families[$familyKey] = [
                    'family' => $familyName,
                    'slug' => FontUtils::slugify($familyName),
                    'source' => 'adobe',
                    'faces' => [],
                ];
            }

            $existingFace = (array) ($families[$familyKey]['faces'][$faceKey] ?? []);
            $nextFace = [
                'weight' => $weight,
                'style' => $style,
                'is_variable' => !empty($face['is_variable']),
                'axes' => $axes,
                'variation_defaults' => FontUtils::normalizeVariationDefaults($face['variation_defaults'] ?? [], $axes),
            ];

            $families[$familyKey]['faces'][$faceKey] = !empty($existingFace['is_variable']) && empty($nextFace['is_variable'])
                ? $existingFace
                : $nextFace;
        }

        foreach ($families as &$family) {
            $family['faces'] = array_values((array) $family['faces']);
            usort($family['faces'], [FontUtils::class, 'compareFacesByWeightAndStyle']);
        }
        unset($family);

        uasort($families, static fn (array $left, array $right): int => strcasecmp($left['family'], $right['family']));

        return array_values($families);
    }

    /**
     * @param array<string, mixed> $face
     */
    private function faceStringValue(array $face, string $key, string $default = ''): string
    {
        $value = $face[$key] ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }
}
