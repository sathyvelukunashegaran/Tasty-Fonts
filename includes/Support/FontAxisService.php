<?php

declare(strict_types=1);

namespace TastyFonts\Support;

defined('ABSPATH') || exit;

/**
 * @phpstan-type VariationDefaults array<string, int|float|string>
 * @phpstan-type AxisDefinition array<string, int|float|string>
 * @phpstan-type AxesMap array<string, AxisDefinition>
 * @phpstan-type HostedAxis array{tag?: mixed, start?: mixed, end?: mixed, min?: mixed, max?: mixed, default?: mixed}
 */
final class FontAxisService
{
    public const REGISTERED_AXIS_TAGS = ['WGHT', 'WDTH', 'SLNT', 'ITAL', 'OPSZ'];

    public function normalizeAxisTag(string $tag): string
    {
        $tag = strtoupper(trim($tag));

        if (preg_match('/^[A-Z0-9]{4}$/', $tag) === 1) {
            return $tag;
        }

        return '';
    }

    public function normalizeAxisValue(mixed $value): string
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
    public function normalizeAxesMap(mixed $axes): array
    {
        if (!is_array($axes)) {
            return [];
        }

        $normalized = [];

        foreach ($axes as $tag => $definition) {
            $normalizedTag = $this->normalizeAxisTag((string) $tag);

            if ($normalizedTag === '' || !is_array($definition)) {
                continue;
            }

            $min = $this->normalizeAxisValue($definition['min'] ?? '');
            $default = $this->normalizeAxisValue($definition['default'] ?? '');
            $max = $this->normalizeAxisValue($definition['max'] ?? '');

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
    public function normalizeVariationDefaults(mixed $defaults, array $axes = []): array
    {
        if (!is_array($defaults)) {
            $defaults = [];
        }

        $normalized = [];

        foreach ($defaults as $tag => $value) {
            $normalizedTag = $this->normalizeAxisTag((string) $tag);
            $normalizedValue = $this->normalizeAxisValue($value);

            if ($normalizedTag === '' || $normalizedValue === '') {
                continue;
            }

            $normalized[$normalizedTag] = $normalizedValue;
        }

        foreach ($this->normalizeAxesMap($axes) as $tag => $definition) {
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
    public function buildFontVariationSettings(array $settings): string
    {
        $normalized = $this->normalizeVariationDefaults($settings);

        if ($normalized === []) {
            return 'normal';
        }

        $parts = [];

        foreach ($normalized as $tag => $value) {
            $parts[] = '"' . $this->cssAxisTag($tag) . '" ' . $value;
        }

        return implode(', ', $parts);
    }

    /**
     * @param AxesMap $axes
     * @return VariationDefaults
     */
    public function faceLevelVariationDefaults(mixed $defaults, array $axes = []): array
    {
        $normalized = $this->normalizeVariationDefaults($defaults, $axes);

        foreach (array_keys($normalized) as $tag) {
            if ($this->isRegisteredAxisTag($tag)) {
                unset($normalized[$tag]);
            }
        }

        return $normalized;
    }

    public function cssAxisTag(string $tag): string
    {
        return match ($this->normalizeAxisTag($tag)) {
            'WGHT' => 'wght',
            'WDTH' => 'wdth',
            'SLNT' => 'slnt',
            'ITAL' => 'ital',
            'OPSZ' => 'opsz',
            default => $this->normalizeAxisTag($tag),
        };
    }

    /**
     * @param array<string, mixed> $face
     */
    public function faceIsVariable(array $face): bool
    {
        if (!empty($face['is_variable'])) {
            return true;
        }

        return $this->normalizeAxesMap($face['axes'] ?? []) !== [];
    }

    /**
     * @param list<mixed> $axes
     * @return AxesMap
     */
    public function normalizeHostedAxisList(array $axes): array
    {
        $normalized = [];

        foreach ($axes as $axis) {
            if (!is_array($axis)) {
                continue;
            }

            $tag = $this->normalizeAxisTag($this->stringValue($axis, 'tag'));
            $min = $this->normalizeAxisValue($axis['start'] ?? $axis['min'] ?? '');
            $max = $this->normalizeAxisValue($axis['end'] ?? $axis['max'] ?? '');
            $default = $this->normalizeAxisValue($axis['default'] ?? '');

            if ($tag === '') {
                continue;
            }

            if ($default === '') {
                if ($tag === 'WGHT') {
                    $default = $this->inferDefaultAxisValue($min, $max, '400');
                } elseif ($tag === 'WDTH') {
                    $default = $this->inferDefaultAxisValue($min, $max, '100');
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

        return $this->normalizeAxesMap($normalized);
    }

    /**
     * @return array<string, array<string, float|int|string>>
     */
    public function normalizeAxesValue(mixed $axes): array
    {
        return is_array($axes) ? $this->normalizeAxesMap($axes) : [];
    }

    /**
     * @param array<string, array<string, float|int|string>> $axes
     * @return VariationDefaults
     */
    public function normalizeVariationDefaultsValue(mixed $variationDefaults, array $axes): array
    {
        return is_array($variationDefaults) ? $this->normalizeVariationDefaults($variationDefaults, $axes) : [];
    }

    private function isRegisteredAxisTag(string $tag): bool
    {
        return in_array(
            $this->normalizeAxisTag($tag),
            self::REGISTERED_AXIS_TAGS,
            true
        );
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function stringValue(array $values, int|string $key, string $default = ''): string
    {
        return FontUtils::stringValue($values, $key, $default);
    }

    private function inferDefaultAxisValue(string $min, string $max, string $preferred): string
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
}
