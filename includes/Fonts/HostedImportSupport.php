<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type CatalogFace from CatalogService
 * @phpstan-type FaceList list<CatalogFace>
 * @phpstan-type RequestedAxis array{weight: string, style: string}
 * @phpstan-type RequestedAxisList list<RequestedAxis>
 * @phpstan-type VariantTokenList list<string>
 */
final class HostedImportSupport
{
    /**
     * @param CatalogFace $face
     */
    public static function buildLocalFilename(string $familyName, array $face): string
    {
        $style = FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal'));

        if (FontUtils::faceIsVariable($face)) {
            return FontUtils::buildVariableFontFilename($familyName, $style, 'woff2');
        }

        $weight = preg_replace('/[^0-9]+/', '-', (string) ($face['weight'] ?? '400')) ?: '400';

        return implode(
            '-',
            [
                FontUtils::slugify($familyName),
                trim($weight, '-'),
                $style,
            ]
        ) . '.woff2';
    }

    /**
     * @param FaceList $faces
     * @param VariantTokenList $requestedVariants
     * @return FaceList
     */
    public static function selectPreferredFaces(array $faces, array $requestedVariants): array
    {
        $requestedAxes = [];

        foreach ($requestedVariants as $variant) {
            $axis = FontUtils::googleVariantToAxis((string) $variant);

            if ($axis !== null) {
                $requestedAxes[] = $axis;
            }
        }

        $selected = [];

        foreach ($faces as $face) {
            if ($requestedAxes !== [] && !self::faceMatchesRequestedAxes($face, $requestedAxes)) {
                continue;
            }

            $faceKey = self::faceKeyFromFace($face);

            if (!isset($selected[$faceKey]) || self::preferredFaceScore($face) >= self::preferredFaceScore($selected[$faceKey])) {
                $selected[$faceKey] = $face;
            }
        }

        uasort($selected, [FontUtils::class, 'compareFacesByWeightAndStyle']);

        return array_values($selected);
    }

    /**
     * @param FaceList $existingFaces
     * @param FaceList $newFaces
     * @return FaceList
     */
    public static function mergeManifestFaces(array $existingFaces, array $newFaces): array
    {
        $merged = [];

        foreach ($existingFaces as $face) {
            $merged[self::faceKeyFromFace($face)] = $face;
        }

        foreach ($newFaces as $face) {
            $merged[self::faceKeyFromFace($face)] = $face;
        }

        uasort($merged, [FontUtils::class, 'compareFacesByWeightAndStyle']);

        return array_values($merged);
    }

    /**
     * @param FaceList $faces
     * @return VariantTokenList
     */
    public static function variantsFromFaces(array $faces): array
    {
        $variants = [];

        foreach ($faces as $face) {
            $weight = FontUtils::normalizeWeight((string) ($face['weight'] ?? '400'));
            $style = FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal'));

            if ($weight === '400' && $style === 'normal') {
                $variants[] = 'regular';
                continue;
            }

            if ($weight === '400' && $style === 'italic') {
                $variants[] = 'italic';
                continue;
            }

            $variants[] = $weight . ($style === 'italic' ? 'italic' : '');
        }

        return FontUtils::normalizeVariantTokens($variants);
    }

    public static function faceKeyFromVariant(string $variant): ?string
    {
        $axis = FontUtils::googleVariantToAxis($variant);

        if ($axis === null) {
            return null;
        }

        return FontUtils::faceAxisKey($axis['weight'], $axis['style']);
    }

    /**
     * @param CatalogFace $face
     */
    public static function faceKeyFromFace(array $face): string
    {
        return FontUtils::faceAxisKey(
            (string) ($face['weight'] ?? '400'),
            (string) ($face['style'] ?? 'normal')
        );
    }

    /**
     * @param CatalogFace $face
     */
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

    /**
     * @param CatalogFace $face
     * @param RequestedAxisList $requestedAxes
     */
    private static function faceMatchesRequestedAxes(array $face, array $requestedAxes): bool
    {
        foreach ($requestedAxes as $requestedAxis) {
            if (self::faceMatchesRequestedAxis($face, $requestedAxis)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param CatalogFace $face
     * @param RequestedAxis $requestedAxis
     */
    private static function faceMatchesRequestedAxis(array $face, array $requestedAxis): bool
    {
        $requestedStyle = FontUtils::normalizeStyle($requestedAxis['style']);
        $requestedWeight = FontUtils::normalizeWeight($requestedAxis['weight']);
        $faceStyle = FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal'));

        if ($faceStyle !== $requestedStyle) {
            return false;
        }

        $weightRange = FontUtils::weightRangeFromFace($face);

        if ($weightRange !== null) {
            return FontUtils::requestedWeightMatchesRange($requestedWeight, $weightRange[0], $weightRange[1]);
        }

        return FontUtils::normalizeWeight((string) ($face['weight'] ?? '400')) === $requestedWeight;
    }
}
