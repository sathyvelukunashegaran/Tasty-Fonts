<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

use TastyFonts\Support\FontUtils;

final class HostedImportSupport
{
    public static function buildLocalFilename(string $familyName, array $face): string
    {
        $weight = preg_replace('/[^0-9]+/', '-', (string) ($face['weight'] ?? '400')) ?: '400';
        $style = FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal'));

        return implode(
            '-',
            [
                FontUtils::slugify($familyName),
                trim($weight, '-'),
                $style,
            ]
        ) . '.woff2';
    }

    public static function selectPreferredFaces(array $faces, array $requestedVariants): array
    {
        $allowedKeys = [];

        foreach ($requestedVariants as $variant) {
            $faceKey = self::faceKeyFromVariant((string) $variant);

            if ($faceKey !== null) {
                $allowedKeys[$faceKey] = true;
            }
        }

        $selected = [];

        foreach ($faces as $face) {
            if (!is_array($face)) {
                continue;
            }

            $faceKey = self::faceKeyFromFace($face);

            if ($allowedKeys !== [] && !isset($allowedKeys[$faceKey])) {
                continue;
            }

            if (!isset($selected[$faceKey]) || self::preferredFaceScore($face) >= self::preferredFaceScore($selected[$faceKey])) {
                $selected[$faceKey] = $face;
            }
        }

        uasort($selected, [FontUtils::class, 'compareFacesByWeightAndStyle']);

        return array_values($selected);
    }

    public static function mergeManifestFaces(array $existingFaces, array $newFaces): array
    {
        $merged = [];

        foreach ($existingFaces as $face) {
            if (!is_array($face)) {
                continue;
            }

            $merged[self::faceKeyFromFace($face)] = $face;
        }

        foreach ($newFaces as $face) {
            if (!is_array($face)) {
                continue;
            }

            $merged[self::faceKeyFromFace($face)] = $face;
        }

        uasort($merged, [FontUtils::class, 'compareFacesByWeightAndStyle']);

        return array_values($merged);
    }

    public static function faceKeyFromVariant(string $variant): ?string
    {
        $axis = FontUtils::googleVariantToAxis($variant);

        if ($axis === null) {
            return null;
        }

        return FontUtils::faceAxisKey($axis['weight'], $axis['style']);
    }

    public static function faceKeyFromFace(array $face): string
    {
        return FontUtils::faceAxisKey(
            (string) ($face['weight'] ?? '400'),
            (string) ($face['style'] ?? 'normal')
        );
    }

    private static function preferredFaceScore(array $face): int
    {
        $range = strtoupper((string) ($face['unicode_range'] ?? ''));

        if ($range === '') {
            return 1000;
        }

        $score = 0;

        if (str_contains($range, 'U+0000-00FF')) {
            $score += 500;
        }

        if (str_contains($range, 'U+0100-024F')) {
            $score += 220;
        }

        if (str_contains($range, 'U+1E00-1EFF')) {
            $score += 80;
        }

        if (str_contains($range, 'U+20AC')) {
            $score += 30;
        }

        $score -= min(strlen($range), 300);

        return $score;
    }
}
