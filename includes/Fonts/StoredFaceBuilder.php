<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

final class StoredFaceBuilder
{
    /**
     * @param array<string, mixed> $face
     * @param array<string, string> $files
     * @param array<string, mixed> $provider
     * @return array<string, mixed>
     */
    public static function build(
        string $familyName,
        string $familySlug,
        array $face,
        array $files,
        array $provider,
        string $source
    ): array {
        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'source' => $source,
            'weight' => FontUtils::stringValue($face, 'weight', '400'),
            'style' => FontUtils::stringValue($face, 'style', 'normal'),
            'unicode_range' => FontUtils::stringValue($face, 'unicode_range'),
            'files' => $files,
            'provider' => $provider,
            'is_variable' => !empty($face['is_variable']),
            'axes' => FontUtils::normalizeStringKeyedMap($face['axes'] ?? []),
            'variation_defaults' => FontUtils::normalizeStringKeyedMap($face['variation_defaults'] ?? []),
        ];
    }
}
