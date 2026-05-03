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
    public const UNICODE_RANGE_MODE_PRESERVE = UnicodeRangeService::MODE_PRESERVE;
    public const UNICODE_RANGE_MODE_LATIN_BASIC = UnicodeRangeService::MODE_LATIN_BASIC;
    public const UNICODE_RANGE_MODE_LATIN_EXTENDED = UnicodeRangeService::MODE_LATIN_EXTENDED;
    public const UNICODE_RANGE_MODE_OFF = UnicodeRangeService::MODE_OFF;
    public const UNICODE_RANGE_MODE_CUSTOM = UnicodeRangeService::MODE_CUSTOM;
    public const UNICODE_RANGE_PRESET_LATIN_BASIC = UnicodeRangeService::PRESET_LATIN_BASIC;
    public const UNICODE_RANGE_PRESET_LATIN_EXTENDED = UnicodeRangeService::PRESET_LATIN_EXTENDED;
    public const DEFAULT_ROLE_SANS_FALLBACK = 'system-ui, sans-serif';
    public const DEFAULT_ROLE_MONOSPACE_FALLBACK = 'ui-monospace, monospace';
    public const FALLBACK_SUGGESTIONS = [
        self::DEFAULT_ROLE_SANS_FALLBACK,
        self::DEFAULT_ROLE_MONOSPACE_FALLBACK,
        'system-ui, sans-serif',
        'sans-serif',
        'serif',
        'monospace',
        'system-ui',
        'ui-sans-serif',
        'ui-serif',
        'ui-monospace',
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

    public static function sanitizeOptionalFallback(mixed $fallback): string
    {
        if (!is_scalar($fallback)) {
            return '';
        }

        $fallback = html_entity_decode(trim((string) $fallback), ENT_QUOTES, 'UTF-8');

        if ($fallback === '') {
            return '';
        }

        return self::sanitizeFallback($fallback);
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
        return (new CssVariableNamingService())->fontVariableName($family);
    }

    public static function fontVariableReference(string $family): string
    {
        return (new CssVariableNamingService())->fontVariableReference($family);
    }

    public static function weightVariableName(string|int $weight): string
    {
        return (new CssVariableNamingService())->weightVariableName($weight);
    }

    public static function weightSemanticVariableName(string|int $weight): string
    {
        return (new CssVariableNamingService())->weightSemanticVariableName($weight);
    }

    public static function weightVariableReference(string|int $weight, bool $preferSemantic = true): string
    {
        return (new CssVariableNamingService())->weightVariableReference($weight, $preferSemantic);
    }

    /**
     * @return VariantVariableNames
     */
    public static function variantVariableNames(string $family, string|int $weight, string $style): array
    {
        return (new CssVariableNamingService())->variantVariableNames($family, $weight, $style);
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
        return (new UnicodeRangeService())->normalizeMode($mode);
    }

    public static function normalizeUnicodeRangeValue(string $value): string
    {
        return (new UnicodeRangeService())->normalizeValue($value);
    }

    public static function unicodeRangeValueIsValid(string $value): bool
    {
        return (new UnicodeRangeService())->isValid($value);
    }

    /**
     * @param array<string, mixed> $face
     * @param array<string, mixed> $settings
     */
    public static function resolveFaceUnicodeRange(array $face, array $settings): string
    {
        return (new UnicodeRangeService())->resolveFaceRange($face, $settings);
    }

    public static function normalizeAxisTag(string $tag): string
    {
        return (new FontAxisService())->normalizeAxisTag($tag);
    }

    public static function normalizeAxisValue(mixed $value): string
    {
        return (new FontAxisService())->normalizeAxisValue($value);
    }

    /**
     * @return AxesMap
     */
    public static function normalizeAxesMap(mixed $axes): array
    {
        return (new FontAxisService())->normalizeAxesMap($axes);
    }

    /**
     * @param AxesMap $axes
     * @return VariationDefaults
     */
    public static function normalizeVariationDefaults(mixed $defaults, array $axes = []): array
    {
        return (new FontAxisService())->normalizeVariationDefaults($defaults, $axes);
    }

    /**
     * @param VariationDefaults $settings
     */
    public static function buildFontVariationSettings(array $settings): string
    {
        return (new FontAxisService())->buildFontVariationSettings($settings);
    }

    /**
     * @param AxesMap $axes
     * @return VariationDefaults
     */
    public static function faceLevelVariationDefaults(mixed $defaults, array $axes = []): array
    {
        return (new FontAxisService())->faceLevelVariationDefaults($defaults, $axes);
    }

    public static function cssAxisTag(string $tag): string
    {
        return (new FontAxisService())->cssAxisTag($tag);
    }

    /**
     * @param array<string, mixed> $face
     */
    public static function faceIsVariable(array $face): bool
    {
        return (new FontAxisService())->faceIsVariable($face);
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
     * @param array<string, mixed> $face
     * @return list<string>
     */
    public static function collectFaceRelativePaths(array $face): array
    {
        $paths = [];

        foreach (self::normalizeStringMap($face['paths'] ?? []) as $path) {
            if (trim($path) === '') {
                continue;
            }

            $paths[] = trim($path);
        }

        foreach (self::normalizeStringMap($face['files'] ?? []) as $file) {
            if (trim($file) === '' || self::isRemoteUrl($file)) {
                continue;
            }

            $paths[] = trim($file);
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param array<string, mixed>|null $family
     * @return array<string, mixed>|null
     */
    public static function findDeliveryProfile(?array $family, string $provider, string $type, string $formatMode = ''): ?array
    {
        if (!is_array($family)) {
            return null;
        }

        $provider = strtolower(trim($provider));
        $type = strtolower(trim($type));

        foreach ((array) ($family['delivery_profiles'] ?? []) as $profile) {
            $profile = self::normalizeStringKeyedMap($profile);

            if (
                $profile === []
                || strtolower(self::stringValue($profile, 'provider')) !== $provider
                || strtolower(self::stringValue($profile, 'type')) !== $type
            ) {
                continue;
            }

            if ($formatMode !== '' && self::resolveProfileFormat($profile) !== $formatMode) {
                continue;
            }

            return $profile;
        }

        return null;
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
     * @return array<string, array<string, float|int|string>>
     */
    public static function normalizeAxesValue(mixed $axes): array
    {
        return (new FontAxisService())->normalizeAxesValue($axes);
    }

    /**
     * @param array<string, array<string, float|int|string>> $axes
     * @return VariationDefaults
     */
    public static function normalizeVariationDefaultsValue(mixed $variationDefaults, array $axes): array
    {
        return (new FontAxisService())->normalizeVariationDefaultsValue($variationDefaults, $axes);
    }

    /**
     * @param mixed $faces
     * @return AxesMap
     */
    public static function collectVariationAxesFromFaces(mixed $faces): array
    {
        $axes = [];

        foreach (self::normalizeFaceList($faces) as $face) {
            foreach (self::normalizeAxesMap($face['axes'] ?? []) as $tag => $definition) {
                if (!isset($axes[$tag])) {
                    $axes[$tag] = $definition;
                    continue;
                }

                $axes[$tag]['min'] = (string) min((float) $axes[$tag]['min'], (float) $definition['min']);
                $axes[$tag]['max'] = (string) max((float) $axes[$tag]['max'], (float) $definition['max']);
            }
        }

        ksort($axes, SORT_STRING);

        return $axes;
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
     *     limit_response_size?: int,
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

        if (isset($value['limit_response_size']) && is_int($value['limit_response_size']) && $value['limit_response_size'] > 0) {
            $normalized['limit_response_size'] = $value['limit_response_size'];
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
        return (new VariantTokenService())->variantKey($weight, $style, $unicodeRange);
    }

    public static function faceAxisKey(string|int $weight, string $style): string
    {
        return (new VariantTokenService())->faceAxisKey($weight, $style);
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
        return (new VariantTokenService())->googleVariantToAxis($variant);
    }

    /**
     * @param list<mixed> $axes
     * @return AxesMap
     */
    public static function normalizeHostedAxisList(array $axes): array
    {
        return (new FontAxisService())->normalizeHostedAxisList($axes);
    }



    /**
     * @param list<string> $variants
     * @return list<string>
     */
    public static function normalizeVariantTokens(array $variants): array
    {
        return (new VariantTokenService())->normalizeVariantTokens($variants);
    }



    /**
     * @param array<int|string, mixed> $values
     */
    public static function stringValue(array $values, int|string $key, string $default = ''): string
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
