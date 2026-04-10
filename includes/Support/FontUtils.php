<?php

declare(strict_types=1);

namespace TastyFonts\Support;

defined('ABSPATH') || exit;

final class FontUtils
{
    public const UNICODE_RANGE_MODE_PRESERVE = 'preserve';
    public const UNICODE_RANGE_MODE_LATIN_BASIC = 'latin_basic';
    public const UNICODE_RANGE_MODE_LATIN_EXTENDED = 'latin_extended';
    public const UNICODE_RANGE_MODE_OFF = 'off';
    public const UNICODE_RANGE_MODE_CUSTOM = 'custom';
    public const UNICODE_RANGE_PRESET_LATIN_BASIC = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
    public const UNICODE_RANGE_PRESET_LATIN_EXTENDED = 'U+0000-00FF,U+0100-024F,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+1E00-1EFF,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
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
        $sanitizedFallback = self::sanitizeFallback($fallback);

        if (trim($family) === '') {
            return $sanitizedFallback;
        }

        return '"' . self::escapeFontFamily($family) . '", ' . $sanitizedFallback;
    }

    public static function defaultFallbackForCategory(string $category): string
    {
        return match (strtolower(trim($category))) {
            'serif' => 'serif',
            'monospace' => 'monospace',
            'handwriting', 'script', 'cursive' => 'cursive',
            default => 'sans-serif',
        };
    }

    public static function fontVariableName(string $family): string
    {
        $slug = self::slugify($family);

        return $slug !== '' ? '--font-' . $slug : '';
    }

    public static function fontVariableReference(string $family): string
    {
        $property = self::fontVariableName($family);

        return $property !== '' ? 'var(' . $property . ')' : '';
    }

    public static function weightVariableName(string|int $weight): string
    {
        $value = self::concreteWeightValue($weight);

        return $value !== '' ? '--weight-' . $value : '';
    }

    public static function weightSemanticVariableName(string|int $weight): string
    {
        $value = self::concreteWeightValue($weight);
        $slug = $value !== '' ? self::weightNameSlug($value) : '';

        return $slug !== '' ? '--weight-' . $slug : '';
    }

    public static function weightVariableReference(string|int $weight, bool $preferSemantic = true): string
    {
        $property = $preferSemantic
            ? self::weightSemanticVariableName($weight)
            : self::weightVariableName($weight);

        if ($property === '') {
            $property = $preferSemantic
                ? self::weightVariableName($weight)
                : self::weightSemanticVariableName($weight);
        }

        return $property !== '' ? 'var(' . $property . ')' : '';
    }

    public static function variantVariableNames(string $family, string|int $weight, string $style): array
    {
        $familyVariable = self::fontVariableName($family);

        if ($familyVariable === '') {
            return [
                'family' => '',
                'numeric' => '',
                'named' => '',
            ];
        }

        $numeric = $familyVariable
            . '-'
            . self::weightVariableSegment($weight)
            . self::styleVariableSuffix($style);
        $namedWeight = self::weightNameSlug($weight);
        $named = $namedWeight !== ''
            ? $familyVariable . '-' . $namedWeight . self::styleVariableSuffix($style)
            : $numeric;

        return [
            'family' => $familyVariable,
            'numeric' => $numeric,
            'named' => $named,
        ];
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

    public static function normalizeUnicodeRangeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return in_array(
            $mode,
            [
                self::UNICODE_RANGE_MODE_PRESERVE,
                self::UNICODE_RANGE_MODE_LATIN_BASIC,
                self::UNICODE_RANGE_MODE_LATIN_EXTENDED,
                self::UNICODE_RANGE_MODE_OFF,
                self::UNICODE_RANGE_MODE_CUSTOM,
            ],
            true
        ) ? $mode : self::UNICODE_RANGE_MODE_OFF;
    }

    public static function normalizeUnicodeRangeValue(string $value): string
    {
        $value = strtoupper(trim($value));

        if ($value === '') {
            return '';
        }

        $parts = array_filter(array_map('trim', explode(',', $value)), static fn (string $part): bool => $part !== '');

        return implode(',', $parts);
    }

    public static function unicodeRangeValueIsValid(string $value): bool
    {
        $normalized = self::normalizeUnicodeRangeValue($value);

        if ($normalized === '') {
            return false;
        }

        foreach (explode(',', $normalized) as $part) {
            if (preg_match('/^U\+[0-9A-F?]{1,6}(?:-[0-9A-F]{1,6})?$/', $part) !== 1) {
                return false;
            }

            if (str_contains($part, '?') && str_contains($part, '-')) {
                return false;
            }
        }

        return true;
    }

    public static function resolveFaceUnicodeRange(array $face, array $settings): string
    {
        $mode = self::normalizeUnicodeRangeMode((string) ($settings['unicode_range_mode'] ?? self::UNICODE_RANGE_MODE_OFF));

        return match ($mode) {
            self::UNICODE_RANGE_MODE_LATIN_BASIC => self::UNICODE_RANGE_PRESET_LATIN_BASIC,
            self::UNICODE_RANGE_MODE_LATIN_EXTENDED => self::UNICODE_RANGE_PRESET_LATIN_EXTENDED,
            self::UNICODE_RANGE_MODE_OFF => '',
            self::UNICODE_RANGE_MODE_CUSTOM => self::unicodeRangeValueIsValid((string) ($settings['unicode_range_custom_value'] ?? ''))
                ? self::normalizeUnicodeRangeValue((string) ($settings['unicode_range_custom_value'] ?? ''))
                : '',
            default => trim((string) ($face['unicode_range'] ?? '')),
        };
    }

    public static function normalizeAxisTag(string $tag): string
    {
        $tag = strtoupper(trim($tag));

        if (preg_match('/^[A-Z0-9]{4}$/', $tag) === 1) {
            return $tag;
        }

        return '';
    }

    public static function normalizeAxisValue(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return preg_match('/^-?\d+(?:\.\d+)?$/', $value) === 1 ? $value : '';
    }

    public static function normalizeAxesMap(mixed $axes): array
    {
        if (!is_array($axes)) {
            return [];
        }

        $normalized = [];

        foreach ($axes as $tag => $definition) {
            $normalizedTag = self::normalizeAxisTag((string) $tag);

            if ($normalizedTag === '' || !is_array($definition)) {
                continue;
            }

            $min = self::normalizeAxisValue($definition['min'] ?? '');
            $default = self::normalizeAxisValue($definition['default'] ?? '');
            $max = self::normalizeAxisValue($definition['max'] ?? '');

            if ($min === '' && $default === '' && $max === '') {
                continue;
            }

            if ($default === '') {
                $default = $min !== '' ? $min : $max;
            }

            if ($min === '') {
                $min = $default;
            }

            if ($max === '') {
                $max = $default;
            }

            if ($min === '' || $default === '' || $max === '') {
                continue;
            }

            $minValue = (float) $min;
            $defaultValue = (float) $default;
            $maxValue = (float) $max;

            if ($minValue > $maxValue || $defaultValue < $minValue || $defaultValue > $maxValue) {
                continue;
            }

            $normalized[$normalizedTag] = [
                'min' => $min,
                'default' => $default,
                'max' => $max,
            ];
        }

        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    public static function normalizeVariationDefaults(mixed $defaults, array $axes = []): array
    {
        if (!is_array($defaults)) {
            $defaults = [];
        }

        $normalized = [];

        foreach ($defaults as $tag => $value) {
            $normalizedTag = self::normalizeAxisTag((string) $tag);
            $normalizedValue = self::normalizeAxisValue($value);

            if ($normalizedTag === '' || $normalizedValue === '') {
                continue;
            }

            $normalized[$normalizedTag] = $normalizedValue;
        }

        foreach (self::normalizeAxesMap($axes) as $tag => $definition) {
            if (!isset($normalized[$tag]) && isset($definition['default'])) {
                $normalized[$tag] = (string) $definition['default'];
            }
        }

        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    public static function buildFontVariationSettings(array $settings): string
    {
        $normalized = self::normalizeVariationDefaults($settings);

        if ($normalized === []) {
            return 'normal';
        }

        $parts = [];

        foreach ($normalized as $tag => $value) {
            $parts[] = '"' . self::cssAxisTag($tag) . '" ' . $value;
        }

        return implode(', ', $parts);
    }

    public static function cssAxisTag(string $tag): string
    {
        return match (self::normalizeAxisTag($tag)) {
            'WGHT' => 'wght',
            'WDTH' => 'wdth',
            'SLNT' => 'slnt',
            'ITAL' => 'ital',
            'OPSZ' => 'opsz',
            default => self::normalizeAxisTag($tag),
        };
    }

    public static function faceIsVariable(array $face): bool
    {
        if (!empty($face['is_variable'])) {
            return true;
        }

        return self::normalizeAxesMap($face['axes'] ?? []) !== [];
    }

    public static function facesHaveVariableMetadata(array $faces): bool
    {
        foreach ($faces as $face) {
            if (is_array($face) && self::faceIsVariable($face)) {
                return true;
            }
        }

        return false;
    }

    public static function facesHaveStaticMetadata(array $faces): bool
    {
        foreach ($faces as $face) {
            if (!is_array($face) || self::faceIsVariable($face)) {
                continue;
            }

            return true;
        }

        return false;
    }

    public static function resolveProfileFormat(array $profile): string
    {
        $format = strtolower(trim((string) ($profile['format'] ?? '')));

        if (in_array($format, ['static', 'variable'], true)) {
            return $format;
        }

        return self::facesHaveVariableMetadata((array) ($profile['faces'] ?? [])) ? 'variable' : 'static';
    }

    public static function resolveFormatAvailability(array $entry): array
    {
        $formats = [];

        foreach ((array) ($entry['formats'] ?? []) as $key => $value) {
            if (!is_string($key) || !is_array($value)) {
                continue;
            }

            $normalizedKey = strtolower(trim($key));

            if (!in_array($normalizedKey, ['static', 'variable'], true)) {
                continue;
            }

            $formats[$normalizedKey] = [
                'label' => sanitize_text_field((string) ($value['label'] ?? ucfirst($normalizedKey))),
                'available' => !empty($value['available']),
                'source_only' => !empty($value['source_only']),
            ];
        }

        if ($formats !== []) {
            return $formats;
        }

        if (self::facesHaveStaticMetadata((array) ($entry['faces'] ?? []))) {
            $formats['static'] = [
                'label' => 'Static',
                'available' => true,
                'source_only' => false,
            ];
        }

        if (
            !empty($entry['has_variable_faces'])
            || !empty($entry['is_variable'])
            || self::normalizeAxesMap($entry['variation_axes'] ?? []) !== []
            || self::normalizeAxesMap($entry['axes'] ?? []) !== []
            || self::facesHaveVariableMetadata((array) ($entry['faces'] ?? []))
        ) {
            $formats['variable'] = [
                'label' => 'Variable',
                'available' => true,
                'source_only' => false,
            ];
        }

        if ($formats === []) {
            $formats['static'] = [
                'label' => 'Static',
                'available' => true,
                'source_only' => false,
            ];
        }

        return $formats;
    }

    public static function weightNameSlug(string|int $weight): string
    {
        return match (self::normalizeWeight($weight)) {
            '100' => 'thin',
            '200' => 'ultra-light',
            '300' => 'light',
            '400', 'normal' => 'regular',
            '500' => 'medium',
            '600' => 'semi-bold',
            '700', 'bold' => 'bold',
            '800' => 'extra-bold',
            '900' => 'black',
            '950' => 'extra-black',
            '1000' => 'ultra-black',
            'bolder' => 'bolder',
            'lighter' => 'lighter',
            default => preg_match('/^\d{1,4}\.\.\d{1,4}$/', self::normalizeWeight($weight)) === 1
                ? 'variable-range'
                : '',
        };
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

        $concreteWeight = self::concreteWeightValue($weight);

        if ($concreteWeight !== '') {
            return (int) $concreteWeight;
        }

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

    public static function buildVariableFontFilename(string $family, string $style, string $extension): string
    {
        $family = preg_replace('/\s+/', ' ', trim($family)) ?? '';
        $family = preg_replace('/[\/\\\\:\*\?"<>\|]+/', ' ', $family) ?? '';
        $family = trim($family, " .-\t\n\r\0\x0B");
        $family = $family !== '' ? $family : 'Font';

        $segments = [$family, 'VariableFont'];
        $normalizedStyle = self::normalizeStyle($style);

        if ($normalizedStyle !== 'normal') {
            $segments[] = $normalizedStyle;
        }

        return implode('-', $segments) . '.' . strtolower(trim($extension));
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

        if (preg_match('/^([1-9]00)\.\.([1-9]00)italic$/', $variant, $matches) === 1) {
            return ['style' => 'italic', 'weight' => $matches[1] . '..' . $matches[2]];
        }

        return null;
    }

    public static function normalizeHostedAxisList(array $axes): array
    {
        $normalized = [];

        foreach ($axes as $axis) {
            if (!is_array($axis)) {
                continue;
            }

            $tag = self::normalizeAxisTag((string) ($axis['tag'] ?? ''));
            $min = self::normalizeAxisValue($axis['start'] ?? $axis['min'] ?? '');
            $max = self::normalizeAxisValue($axis['end'] ?? $axis['max'] ?? '');
            $default = self::normalizeAxisValue($axis['default'] ?? '');

            if ($tag === '') {
                continue;
            }

            if ($default === '') {
                if ($tag === 'WGHT') {
                    $default = self::inferDefaultAxisValue($min, $max, '400');
                } elseif ($tag === 'WDTH') {
                    $default = self::inferDefaultAxisValue($min, $max, '100');
                } else {
                    $default = $min !== '' ? $min : $max;
                }
            }

            $normalized[$tag] = [
                'min' => $min !== '' ? $min : $default,
                'default' => $default,
                'max' => $max !== '' ? $max : $default,
            ];
        }

        return self::normalizeAxesMap($normalized);
    }

    private static function inferDefaultAxisValue(string $min, string $max, string $preferred): string
    {
        if ($preferred !== '') {
            $preferredValue = (float) $preferred;

            if (
                ($min === '' || $preferredValue >= (float) $min)
                && ($max === '' || $preferredValue <= (float) $max)
            ) {
                return $preferred;
            }
        }

        if ($min !== '') {
            return $min;
        }

        return $max;
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

    private static function weightVariableSegment(string|int $weight): string
    {
        return str_replace('..', '-', self::normalizeWeight($weight));
    }

    private static function concreteWeightValue(string|int $weight): string
    {
        return match (self::normalizeWeight($weight)) {
            'normal' => '400',
            'bold' => '700',
            default => preg_match('/^\d{1,4}$/', self::normalizeWeight($weight)) === 1
                ? self::normalizeWeight($weight)
                : '',
        };
    }

    private static function styleVariableSuffix(string $style): string
    {
        $normalizedStyle = self::normalizeStyle($style);

        return $normalizedStyle === 'normal' ? '' : '-' . $normalizedStyle;
    }
}
