<?php

declare(strict_types=1);

namespace TastyFonts\Support;

defined('ABSPATH') || exit;

/**
 * @phpstan-type VariantVariableNames array{family: string, numeric: string, named: string}
 * @phpstan-type VariationDefaults array<string, int|float|string>
 * @phpstan-type AxisDefinition array<string, int|float|string>
 * @phpstan-type AxesMap array<string, AxisDefinition>
 * @phpstan-type FormatAvailability array<string, array{label: string, available: bool, source_only: bool}>
 * @phpstan-type WeightRange array{0: int, 1: int}
 * @phpstan-type HostedAxis array{tag?: mixed, start?: mixed, end?: mixed, min?: mixed, max?: mixed, default?: mixed}
 * @phpstan-type FaceLike array{weight?: mixed, style?: mixed, unicode_range?: mixed, axes?: mixed, is_variable?: mixed}
 * @phpstan-type ProfileLike array{format?: mixed, faces?: mixed}
 * @phpstan-type FormatEntryLike array{label?: mixed, available?: mixed, source_only?: mixed}
 * @phpstan-type FormatAvailabilityEntryMap array<string, FormatEntryLike>
 */
final class FontUtils
{
    public const UNICODE_RANGE_MODE_PRESERVE = 'preserve';
    public const UNICODE_RANGE_MODE_LATIN_BASIC = 'latin_basic';
    public const UNICODE_RANGE_MODE_LATIN_EXTENDED = 'latin_extended';
    public const UNICODE_RANGE_MODE_OFF = 'off';
    public const UNICODE_RANGE_MODE_CUSTOM = 'custom';
    public const UNICODE_RANGE_PRESET_LATIN_BASIC = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
    public const UNICODE_RANGE_PRESET_LATIN_EXTENDED = 'U+0000-00FF,U+0100-024F,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+1E00-1EFF,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
    public const DEFAULT_ROLE_SANS_FALLBACK = 'system-ui, sans-serif';
    public const FALLBACK_SUGGESTIONS = [
        self::DEFAULT_ROLE_SANS_FALLBACK,
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

    /**
     * @return VariantVariableNames
     */
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

    /**
     * @param array<string, mixed> $face
     * @param array<string, mixed> $settings
     */
    public static function resolveFaceUnicodeRange(array $face, array $settings): string
    {
        $mode = self::normalizeUnicodeRangeMode(self::stringValue($settings, 'unicode_range_mode', self::UNICODE_RANGE_MODE_OFF));
        $customRange = self::stringValue($settings, 'unicode_range_custom_value');

        return match ($mode) {
            self::UNICODE_RANGE_MODE_LATIN_BASIC => self::UNICODE_RANGE_PRESET_LATIN_BASIC,
            self::UNICODE_RANGE_MODE_LATIN_EXTENDED => self::UNICODE_RANGE_PRESET_LATIN_EXTENDED,
            self::UNICODE_RANGE_MODE_OFF => '',
            self::UNICODE_RANGE_MODE_CUSTOM => self::unicodeRangeValueIsValid($customRange)
                ? self::normalizeUnicodeRangeValue($customRange)
                : '',
            default => trim(self::stringValue($face, 'unicode_range')),
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

    /**
     * @return AxesMap
     */
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

    /**
     * @param AxesMap $axes
     * @return VariationDefaults
     */
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

    /**
     * @param VariationDefaults $settings
     */
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

    /**
     * @param AxesMap $axes
     * @return VariationDefaults
     */
    public static function faceLevelVariationDefaults(mixed $defaults, array $axes = []): array
    {
        $normalized = self::normalizeVariationDefaults($defaults, $axes);

        foreach (array_keys($normalized) as $tag) {
            if (self::isRegisteredAxisTag($tag)) {
                unset($normalized[$tag]);
            }
        }

        return $normalized;
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

    /**
     * @param array<string, mixed> $face
     */
    public static function faceIsVariable(array $face): bool
    {
        if (!empty($face['is_variable'])) {
            return true;
        }

        return self::normalizeAxesMap($face['axes'] ?? []) !== [];
    }

    /**
     * @param list<array<string, mixed>> $faces
     */
    public static function facesHaveVariableMetadata(array $faces): bool
    {
        foreach ($faces as $face) {
            if (self::faceIsVariable($face)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $faces
     */
    public static function facesHaveStaticMetadata(array $faces): bool
    {
        foreach ($faces as $face) {
            if (self::faceIsVariable($face)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $profile
     */
    public static function resolveProfileFormat(array $profile): string
    {
        $format = strtolower(trim(self::stringValue($profile, 'format')));

        if (in_array($format, ['static', 'variable'], true)) {
            return $format;
        }

        return self::facesHaveVariableMetadata(self::normalizeFaceList($profile['faces'] ?? [])) ? 'variable' : 'static';
    }

    /**
     * @param array<string, mixed> $entry
     * @return FormatAvailability
     */
    public static function resolveFormatAvailability(array $entry): array
    {
        $formats = [];

        foreach (self::formatEntryMap($entry['formats'] ?? []) as $key => $value) {
            $normalizedKey = strtolower(trim($key));

            if (!in_array($normalizedKey, ['static', 'variable'], true)) {
                continue;
            }

            $formats[$normalizedKey] = [
                'label' => sanitize_text_field(self::stringValue($value, 'label', ucfirst($normalizedKey))),
                'available' => !empty($value['available']),
                'source_only' => !empty($value['source_only']),
            ];
        }

        if ($formats !== []) {
            return $formats;
        }

        if (self::facesHaveStaticMetadata(self::normalizeFaceList($entry['faces'] ?? []))) {
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
            || self::facesHaveVariableMetadata(self::normalizeFaceList($entry['faces'] ?? []))
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

    /**
     * @param mixed $faces
     * @return list<array<string, mixed>>
     */
    public static function normalizeFaceList(mixed $faces): array
    {
        return self::normalizeListOfStringKeyedMaps($faces);
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    public static function normalizeStringKeyedMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                continue;
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return list<array<string, mixed>>
     */
    public static function normalizeListOfStringKeyedMaps(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            $normalizedItem = self::normalizeStringKeyedMap($item);

            if ($normalizedItem === []) {
                continue;
            }

            $normalized[] = $normalizedItem;
        }

        return $normalized;
    }

    /**
     * @param mixed $values
     * @return array<string, string>
     */
    public static function normalizeStringMap(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $normalizedValue = self::scalarStringValue($value);

            if ($normalizedValue === '') {
                continue;
            }

            $normalized[$key] = $normalizedValue;
        }

        return $normalized;
    }

    public static function scalarStringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @return array{
     *     method?: string,
     *     timeout?: float,
     *     redirection?: int,
     *     httpversion?: string,
     *     user-agent?: string,
     *     reject_unsafe_urls?: bool,
     *     blocking?: bool,
     *     headers?: array<string, string>|string
     * }
     */
    public static function normalizeHttpArgs(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        if (isset($value['method']) && is_string($value['method'])) {
            $normalized['method'] = $value['method'];
        }

        if (isset($value['timeout']) && (is_int($value['timeout']) || is_float($value['timeout']))) {
            $normalized['timeout'] = (float) $value['timeout'];
        }

        if (isset($value['redirection']) && is_int($value['redirection'])) {
            $normalized['redirection'] = $value['redirection'];
        }

        if (isset($value['httpversion']) && is_string($value['httpversion'])) {
            $normalized['httpversion'] = $value['httpversion'];
        }

        if (isset($value['user-agent']) && is_string($value['user-agent'])) {
            $normalized['user-agent'] = $value['user-agent'];
        }

        if (isset($value['reject_unsafe_urls']) && is_bool($value['reject_unsafe_urls'])) {
            $normalized['reject_unsafe_urls'] = $value['reject_unsafe_urls'];
        }

        if (isset($value['blocking']) && is_bool($value['blocking'])) {
            $normalized['blocking'] = $value['blocking'];
        }

        if (isset($value['headers'])) {
            if (is_string($value['headers'])) {
                $normalized['headers'] = $value['headers'];
            } elseif (is_array($value['headers'])) {
                $headers = [];

                foreach ($value['headers'] as $headerKey => $headerValue) {
                    if (is_string($headerKey) && is_scalar($headerValue)) {
                        $headers[$headerKey] = (string) $headerValue;
                    }
                }

                $normalized['headers'] = $headers;
            }
        }

        return $normalized;
    }

    public static function scalarIntValue(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        $normalized = self::scalarStringValue($value);

        if ($normalized !== '' && preg_match('/^-?\d+$/', $normalized) === 1) {
            return (int) $normalized;
        }

        return $default;
    }

    public static function scalarFloatValue(mixed $value, float $default = 0.0): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        $normalized = self::scalarStringValue($value);

        return is_numeric($normalized) ? (float) $normalized : $default;
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

    /**
     * @return WeightRange|null
     */
    public static function weightRangeFromWeightAndAxes(string|int $weight, mixed $axes = []): ?array
    {
        $normalizedAxes = self::normalizeAxesMap($axes);

        if (isset($normalizedAxes['WGHT']['min'], $normalizedAxes['WGHT']['max'])) {
            return [(int) $normalizedAxes['WGHT']['min'], (int) $normalizedAxes['WGHT']['max']];
        }

        $normalizedWeight = self::normalizeWeight($weight);

        if (preg_match('/^(\d{1,4})\.\.(\d{1,4})$/', $normalizedWeight, $matches) === 1) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $face
     * @return WeightRange|null
     */
    public static function weightRangeFromFace(array $face): ?array
    {
        return self::weightRangeFromWeightAndAxes(
            self::stringValue($face, 'weight', '400'),
            $face['axes'] ?? []
        );
    }

    public static function requestedWeightMatchesRange(string|int $requestedWeight, int $start, int $end): bool
    {
        $normalizedWeight = self::normalizeWeight($requestedWeight);

        if (preg_match('/^(\d{1,4})\.\.(\d{1,4})$/', $normalizedWeight, $matches) === 1) {
            return $start <= (int) $matches[1] && $end >= (int) $matches[2];
        }

        $requestedValue = self::weightSortValue($normalizedWeight);

        return $requestedValue >= $start && $requestedValue <= $end;
    }

    public static function blockEditorFontWeightValue(string|int $weight, mixed $axes = []): string
    {
        $weightRange = self::weightRangeFromWeightAndAxes($weight, $axes);

        if ($weightRange !== null) {
            return (string) $weightRange[0] . ' ' . (string) $weightRange[1];
        }

        return self::normalizeWeight($weight);
    }

    /**
     * @param list<string> $variants
     * @return list<string>
     */
    public static function buildHostedCssAxes(array $variants): array
    {
        $axes = [];

        foreach (self::normalizeVariantTokens($variants) as $token) {
            $axis = self::googleVariantToAxis($token);

            if ($axis === null) {
                continue;
            }

            $axes[] = ($axis['style'] === 'italic' ? '1' : '0') . ',' . $axis['weight'];
        }

        return array_values(array_unique($axes));
    }

    public static function sanitizeHostedCssDisplay(string $display): string
    {
        $display = strtolower(trim($display));

        return in_array($display, ['auto', 'block', 'swap', 'fallback', 'optional'], true)
            ? $display
            : 'swap';
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    public static function compareFacesByWeightAndStyle(array $left, array $right): int
    {
        $weightCompare = self::weightSortValue(self::stringValue($left, 'weight', '400'))
            <=> self::weightSortValue(self::stringValue($right, 'weight', '400'));

        if ($weightCompare !== 0) {
            return $weightCompare;
        }

        return strcmp(
            self::normalizeStyle(self::stringValue($left, 'style', 'normal')),
            self::normalizeStyle(self::stringValue($right, 'style', 'normal'))
        );
    }

    public static function compactRelativePath(string $path): string
    {
        $segments = array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== '');
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

    /**
     * @return array{style: string, weight: string}|null
     */
    public static function googleVariantToAxis(string $variant): ?array
    {
        $variant = self::canonicalVariantToken($variant);

        if ($variant === '') {
            return null;
        }

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

    private static function canonicalVariantToken(string $variant): string
    {
        $variant = strtolower(trim($variant));

        if ($variant === '') {
            return '';
        }

        $compact = preg_replace('/[\s_-]+/', '', $variant);

        if (!is_string($compact) || $compact === '') {
            return '';
        }

        $weightAliases = [
            'thin' => '100',
            'hairline' => '100',
            'extralight' => '200',
            'ultralight' => '200',
            'light' => '300',
            'regular' => 'regular',
            'normal' => 'regular',
            'book' => 'regular',
            'medium' => '500',
            'semibold' => '600',
            'demibold' => '600',
            'bold' => '700',
            'extrabold' => '800',
            'ultrabold' => '800',
            'black' => '900',
            'heavy' => '900',
        ];

        if ($compact === 'italic') {
            return 'italic';
        }

        if (
            preg_match('/^([1-9]00)(italic)?$/', $compact) === 1
            || preg_match('/^([1-9]00)\.\.([1-9]00)(italic)?$/', $compact) === 1
        ) {
            return $compact;
        }

        if (isset($weightAliases[$compact])) {
            return $weightAliases[$compact];
        }

        if (str_ends_with($compact, 'italic')) {
            $weightAlias = substr($compact, 0, -6);

            if ($weightAlias === '') {
                return 'italic';
            }

            if (isset($weightAliases[$weightAlias])) {
                return $weightAliases[$weightAlias] === 'regular'
                    ? 'italic'
                    : $weightAliases[$weightAlias] . 'italic';
            }
        }

        return '';
    }

    private static function isRegisteredAxisTag(string $tag): bool
    {
        return in_array(
            self::normalizeAxisTag($tag),
            ['WGHT', 'WDTH', 'SLNT', 'ITAL', 'OPSZ'],
            true
        );
    }

    /**
     * @param list<mixed> $axes
     * @return AxesMap
     */
    public static function normalizeHostedAxisList(array $axes): array
    {
        $normalized = [];

        foreach ($axes as $axis) {
            if (!is_array($axis)) {
                continue;
            }

            $tag = self::normalizeAxisTag(self::stringValue($axis, 'tag'));
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

    /**
     * @param list<string> $variants
     * @return list<string>
     */
    public static function normalizeVariantTokens(array $variants): array
    {
        $normalized = [];

        foreach ($variants as $variant) {
            $parts = array_map('trim', explode(',', $variant));

            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }

                $canonical = self::canonicalVariantToken($part);

                if ($canonical === '' || self::googleVariantToAxis($canonical) === null) {
                    continue;
                }

                $normalized[$canonical] = $canonical;
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

    /**
     * @param array<int|string, mixed> $values
     */
    private static function stringValue(array $values, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $value = $values[$key];

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @return FormatAvailabilityEntryMap
     */
    private static function formatEntryMap(mixed $formats): array
    {
        if (!is_array($formats)) {
            return [];
        }

        $normalized = [];

        foreach ($formats as $key => $value) {
            if (!is_string($key) || !is_array($value)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
