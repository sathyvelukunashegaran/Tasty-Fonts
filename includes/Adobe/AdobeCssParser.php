<?php

declare(strict_types=1);

namespace TastyFonts\Adobe;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\HostedCssParser;
use TastyFonts\Support\FontUtils;

final class AdobeCssParser
{
    public function parseFamilies(string $css): array
    {
        $faces = (new HostedCssParser('adobe'))->parse($css);

        if ($faces === []) {
            return [];
        }

        $families = [];

        foreach ($faces as $face) {
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

            $existingFace = (array) ($families[$familyKey]['faces'][$faceKey] ?? []);
            $nextFace = [
                'weight' => (string) $face['weight'],
                'style' => (string) $face['style'],
                'is_variable' => !empty($face['is_variable']),
                'axes' => FontUtils::normalizeAxesMap($face['axes'] ?? []),
                'variation_defaults' => FontUtils::normalizeVariationDefaults($face['variation_defaults'] ?? [], $face['axes'] ?? []),
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
}
