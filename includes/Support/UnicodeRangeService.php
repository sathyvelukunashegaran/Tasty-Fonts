<?php

declare(strict_types=1);

namespace TastyFonts\Support;

defined('ABSPATH') || exit;

final class UnicodeRangeService
{
    public const MODE_PRESERVE = 'preserve';
    public const MODE_LATIN_BASIC = 'latin_basic';
    public const MODE_LATIN_EXTENDED = 'latin_extended';
    public const MODE_OFF = 'off';
    public const MODE_CUSTOM = 'custom';

    public const PRESET_LATIN_BASIC = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
    public const PRESET_LATIN_EXTENDED = 'U+0000-00FF,U+0100-024F,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+1E00-1EFF,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';

    public function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return in_array(
            $mode,
            [
                self::MODE_PRESERVE,
                self::MODE_LATIN_BASIC,
                self::MODE_LATIN_EXTENDED,
                self::MODE_OFF,
                self::MODE_CUSTOM,
            ],
            true
        ) ? $mode : self::MODE_OFF;
    }

    public function normalizeValue(string $value): string
    {
        $value = strtoupper(trim($value));

        if ($value === '') {
            return '';
        }

        $parts = array_filter(array_map('trim', explode(',', $value)), static fn (string $part): bool => $part !== '');

        return implode(',', $parts);
    }

    public function isValid(string $value): bool
    {
        $normalized = $this->normalizeValue($value);

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
    public function resolveFaceRange(array $face, array $settings): string
    {
        $mode = $this->normalizeMode($this->stringValue($settings, 'unicode_range_mode', self::MODE_OFF));
        $customRange = $this->stringValue($settings, 'unicode_range_custom_value');

        return match ($mode) {
            self::MODE_LATIN_BASIC => self::PRESET_LATIN_BASIC,
            self::MODE_LATIN_EXTENDED => self::PRESET_LATIN_EXTENDED,
            self::MODE_OFF => '',
            self::MODE_CUSTOM => $this->isValid($customRange)
                ? $this->normalizeValue($customRange)
                : '',
            default => trim($this->stringValue($face, 'unicode_range')),
        };
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function stringValue(array $values, int|string $key, string $default = ''): string
    {
        return FontUtils::stringValue($values, $key, $default);
    }
}
