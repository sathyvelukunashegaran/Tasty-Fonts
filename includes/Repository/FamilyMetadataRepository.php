<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * @phpstan-type FamilyFallbackMap array<string, string>
 * @phpstan-type FamilyFontDisplayMap array<string, string>
 */
final class FamilyMetadataRepository implements FamilyMetadataRepositoryInterface
{
    use RepositoryHelpers;
    private const OPTION_SETTINGS = 'tasty_fonts_settings';

    public function getFallback(string $familySlug, string $default = 'sans-serif'): string
    {
        $fallbacks = $this->readSettings()['family_fallbacks'] ?? [];

        if (!is_array($fallbacks) || trim($familySlug) === '') {
            return FontUtils::sanitizeFallback($default);
        }

        return FontUtils::sanitizeFallback($this->mixedStringValue($fallbacks[$familySlug] ?? $default, $default));
    }

    /**
     * @return FamilyFallbackMap
     */
    public function saveFallback(string $familySlug, string $fallback): array
    {
        $familySlug = sanitize_text_field($familySlug);

        if ($familySlug === '') {
            return $this->normalizeFamilyFallbacks($this->readSettings()['family_fallbacks'] ?? []);
        }

        $settings = $this->readSettings();
        $fallbacks = $this->normalizeFamilyFallbacks($settings['family_fallbacks'] ?? []);
        $fallbacks[$familySlug] = FontUtils::sanitizeFallback($fallback);
        ksort($fallbacks, SORT_NATURAL | SORT_FLAG_CASE);

        $settings['family_fallbacks'] = $fallbacks;
        $this->persistSettings($settings);

        return $fallbacks;
    }

    public function getFontDisplay(string $familySlug, string $default = ''): string
    {
        $displays = $this->readSettings()['family_font_displays'] ?? [];

        if (!is_array($displays) || trim($familySlug) === '') {
            return $default === '' ? '' : $this->normalizeFontDisplay($default);
        }

        if (!array_key_exists($familySlug, $displays)) {
            return $default === '' ? '' : $this->normalizeFontDisplay($default);
        }

        return $this->normalizeFontDisplay($this->mixedStringValue($displays[$familySlug] ?? '', ''));
    }

    /**
     * @return FamilyFontDisplayMap
     */
    public function saveFontDisplay(string $familySlug, string $display): array
    {
        $familySlug = sanitize_text_field($familySlug);

        if ($familySlug === '') {
            return $this->normalizeFamilyFontDisplays($this->readSettings()['family_font_displays'] ?? []);
        }

        $settings = $this->readSettings();
        $displays = $this->normalizeFamilyFontDisplays($settings['family_font_displays'] ?? []);
        $display = sanitize_text_field($display);

        if ($display === 'inherit') {
            unset($displays[$familySlug]);
        } elseif ($this->isSupportedFontDisplay($display)) {
            $displays[$familySlug] = $display;
        } else {
            unset($displays[$familySlug]);
        }

        ksort($displays, SORT_NATURAL | SORT_FLAG_CASE);

        $settings['family_font_displays'] = $displays;
        $this->persistSettings($settings);

        return $displays;
    }

    /**
     * @return array<string, mixed>
     */
    public function resetFallbacks(): array
    {
        $settings = $this->readSettings();
        $settings['family_fallbacks'] = [];

        return $this->persistSettings($settings);
    }

    /**
     * @return array<string, mixed>
     */
    public function resetAll(): array
    {
        $settings = $this->readSettings();
        $settings['family_fallbacks'] = [];
        $settings['family_font_displays'] = [];

        return $this->persistSettings($settings);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function normalizeSettingsData(array $settings): array
    {
        $settings['family_fallbacks'] = $this->normalizeFamilyFallbacks($settings['family_fallbacks'] ?? []);
        $settings['family_font_displays'] = $this->normalizeFamilyFontDisplays($settings['family_font_displays'] ?? []);

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function readSettings(): array
    {
        $value = get_option(self::OPTION_SETTINGS, []);

        return $this->normalizeInputMap($value);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function persistSettings(array $settings): array
    {
        update_option(self::OPTION_SETTINGS, $settings, false);

        return $settings;
    }

    /**
     * @return FamilyFallbackMap
     */
    private function normalizeFamilyFallbacks(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $family => $fallback) {
            $family = sanitize_text_field((string) $family);

            if ($family === '') {
                continue;
            }

            $normalized[$family] = FontUtils::sanitizeFallback($this->mixedStringValue($fallback));
        }

        return $normalized;
    }

    /**
     * @return FamilyFontDisplayMap
     */
    private function normalizeFamilyFontDisplays(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $family => $display) {
            $family = sanitize_text_field((string) $family);
            $display = sanitize_text_field($this->mixedStringValue($display));

            if ($family === '' || !$this->isSupportedFontDisplay($display)) {
                continue;
            }

            $normalized[$family] = $display;
        }

        ksort($normalized, SORT_NATURAL | SORT_FLAG_CASE);

        return $normalized;
    }

    private function normalizeFontDisplay(string $display): string
    {
        return $this->isSupportedFontDisplay($display) ? $display : 'swap';
    }

    private function isSupportedFontDisplay(string $display): bool
    {
        return in_array($display, ['auto', 'block', 'swap', 'fallback', 'optional'], true);
    }
}
