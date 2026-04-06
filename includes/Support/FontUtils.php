<?php

declare(strict_types=1);

namespace TastyFonts\Support;

defined('ABSPATH') || exit;

final class FontUtils
{
    public const FALLBACK_SUGGESTIONS = [
        'sans-serif',
        'serif',
        'monospace',
        'system-ui',
        'ui-sans-serif',
        'ui-serif',
        'ui-monospace',
        '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'Arial, sans-serif',
        'Georgia, serif',
    ];
    public const MODERN_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36';

    private function __construct()
    {
    }

    public static function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9\-_]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'font-' . substr(md5($value), 0, 8);
    }

    public static function sanitizeFallback(string $fallback): string
    {
        $fallback = html_entity_decode(trim($fallback), ENT_QUOTES, 'UTF-8');
        $fallback = preg_replace('/[^a-zA-Z0-9,\- "\']+/', '', $fallback) ?? '';
        $fallback = preg_replace('/\s*,\s*/', ', ', $fallback) ?? '';
        $fallback = preg_replace('/\s+/', ' ', $fallback) ?? '';
        $fallback = trim($fallback, " \t\n\r\0\x0B,");

        return $fallback !== '' ? $fallback : 'sans-serif';
    }

    public static function escapeFontFamily(string $family): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $family);
    }

    public static function buildFontStack(string $family, string $fallback = 'sans-serif'): string
    {
        return '"' . self::escapeFontFamily($family) . '", ' . self::sanitizeFallback($fallback);
    }

    public static function normalizeStyle(string $style): string
    {
        $style = strtolower(trim($style));

        return in_array($style, ['normal', 'italic', 'oblique'], true) ? $style : 'normal';
    }

    public static function normalizeWeight(string|int $weight): string
    {
        $value = trim((string) $weight);

        if ($value === '') {
            return '400';
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            $numeric = (int) $value;

            return ($numeric >= 1 && $numeric <= 1000) ? $value : '400';
        }

        if (preg_match('/^\d{1,4}\.\.\d{1,4}$/', $value) === 1) {
            return $value;
        }

        if (in_array($value, ['normal', 'bold', 'bolder', 'lighter'], true)) {
            return $value;
        }

        return '400';
    }

    public static function variantKey(string|int $weight, string $style, string $unicodeRange = ''): string
    {
        $range = trim($unicodeRange);

        return implode(
            '|',
            [
                self::normalizeWeight($weight),
                self::normalizeStyle($style),
                $range !== '' ? md5($range) : 'none',
            ]
        );
    }

    public static function faceAxisKey(string|int $weight, string $style): string
    {
        return self::normalizeWeight($weight) . '|' . self::normalizeStyle($style);
    }

    public static function weightSortValue(string|int $weight): int
    {
        $weight = self::normalizeWeight($weight);

        if (preg_match('/(\d{3})/', $weight, $matches) === 1) {
            return (int) $matches[1];
        }

        return 400;
    }

    public static function compareFacesByWeightAndStyle(array $left, array $right): int
    {
        $weightCompare = self::weightSortValue($left['weight'] ?? '400') <=> self::weightSortValue($right['weight'] ?? '400');

        if ($weightCompare !== 0) {
            return $weightCompare;
        }

        return strcmp(
            self::normalizeStyle((string) ($left['style'] ?? 'normal')),
            self::normalizeStyle((string) ($right['style'] ?? 'normal'))
        );
    }

    public static function compactRelativePath(string $path): string
    {
        $segments = array_filter(explode('/', trim($path, '/')), 'strlen');
        $segments = array_map('rawurldecode', $segments);

        return implode('/', $segments);
    }

    public static function isRemoteUrl(string $value): bool
    {
        $value = trim($value);

        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '//');
    }

    public static function buildStaticFontFilename(string $family, string|int $weight, string $style, string $extension): string
    {
        $family = preg_replace('/\s+/', ' ', trim($family)) ?? '';
        $family = preg_replace('/[\/\\\\:\*\?"<>\|]+/', ' ', $family) ?? '';
        $family = trim($family, " .-\t\n\r\0\x0B");
        $family = $family !== '' ? $family : 'Font';

        $segments = [
            $family,
            self::normalizeWeight($weight),
        ];

        $normalizedStyle = self::normalizeStyle($style);

        if ($normalizedStyle !== 'normal') {
            $segments[] = $normalizedStyle;
        }

        $safeExtension = strtolower(trim($extension));

        return implode('-', $segments) . '.' . $safeExtension;
    }

    public static function googleVariantToAxis(string $variant): ?array
    {
        $variant = strtolower(trim($variant));

        if ($variant === 'regular') {
            return ['style' => 'normal', 'weight' => '400'];
        }

        if ($variant === 'italic') {
            return ['style' => 'italic', 'weight' => '400'];
        }

        if (preg_match('/^([1-9]00)$/', $variant, $matches) === 1) {
            return ['style' => 'normal', 'weight' => $matches[1]];
        }

        if (preg_match('/^([1-9]00)italic$/', $variant, $matches) === 1) {
            return ['style' => 'italic', 'weight' => $matches[1]];
        }

        if (preg_match('/^([1-9]00)\.\.([1-9]00)$/', $variant, $matches) === 1) {
            return ['style' => 'normal', 'weight' => $matches[1] . '..' . $matches[2]];
        }

        return null;
    }

    public static function normalizeVariantTokens(array $variants): array
    {
        $normalized = [];

        foreach ($variants as $variant) {
            if (!is_string($variant)) {
                continue;
            }

            $parts = array_map('trim', explode(',', $variant));

            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }

                if (self::googleVariantToAxis($part) === null) {
                    continue;
                }

                $normalized[strtolower($part)] = strtolower($part);
            }
        }

        if ($normalized === []) {
            $normalized = ['regular' => 'regular'];
        }

        $tokens = array_values($normalized);

        usort(
            $tokens,
            static function (string $left, string $right): int {
                $leftAxis = self::googleVariantToAxis($left) ?? ['style' => 'normal', 'weight' => '400'];
                $rightAxis = self::googleVariantToAxis($right) ?? ['style' => 'normal', 'weight' => '400'];

                $leftStyle = $leftAxis['style'] === 'italic' ? 1 : 0;
                $rightStyle = $rightAxis['style'] === 'italic' ? 1 : 0;

                if ($leftStyle !== $rightStyle) {
                    return $leftStyle <=> $rightStyle;
                }

                return self::weightSortValue($leftAxis['weight']) <=> self::weightSortValue($rightAxis['weight']);
            }
        );

        return $tokens;
    }
}
