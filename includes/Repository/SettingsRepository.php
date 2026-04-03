<?php

declare(strict_types=1);

namespace EtchFonts\Repository;

use EtchFonts\Support\FontUtils;

final class SettingsRepository
{
    public const OPTION_SETTINGS = 'etch_fonts_settings';
    public const OPTION_ROLES = 'etch_fonts_roles';
    private const DEFAULT_SETTINGS = [
        'auto_apply_roles' => false,
        'css_delivery_mode' => 'file',
        'font_display' => 'swap',
        'minify_css_output' => true,
        'preview_sentence' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'google_api_key' => '',
        'google_api_key_status' => 'empty',
        'google_api_key_status_message' => '',
        'google_api_key_checked_at' => 0,
        'family_fallbacks' => [],
    ];
    private const DEFAULT_ROLE_FALLBACKS = [
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];

    public function getSettings(): array
    {
        $settings = wp_parse_args($this->getOptionArray(self::OPTION_SETTINGS), self::DEFAULT_SETTINGS);
        $settings['google_api_key_status'] = $this->normalizeGoogleApiKeyStatus(
            (string) ($settings['google_api_key_status'] ?? 'empty'),
            (string) ($settings['google_api_key'] ?? '')
        );
        $settings['google_api_key_status_message'] = sanitize_text_field((string) ($settings['google_api_key_status_message'] ?? ''));
        $settings['google_api_key_checked_at'] = max(0, absint($settings['google_api_key_checked_at'] ?? 0));
        $settings['family_fallbacks'] = $this->normalizeFamilyFallbacks($settings['family_fallbacks'] ?? []);

        return $settings;
    }

    public function saveSettings(array $input): array
    {
        $settings = $this->getSettings();
        $clearGoogleKey = !empty($input['etch_fonts_clear_google_api_key']);
        $submittedGoogleKey = isset($input['google_api_key'])
            ? sanitize_text_field(wp_unslash((string) $input['google_api_key']))
            : null;

        if ($clearGoogleKey) {
            $settings['google_api_key'] = '';
            $settings['google_api_key_status'] = 'empty';
            $settings['google_api_key_status_message'] = '';
            $settings['google_api_key_checked_at'] = 0;
        } elseif (is_string($submittedGoogleKey) && trim($submittedGoogleKey) !== '') {
            $settings['google_api_key'] = trim($submittedGoogleKey);
            $settings['google_api_key_status'] = 'unknown';
            $settings['google_api_key_status_message'] = '';
            $settings['google_api_key_checked_at'] = 0;
        }

        return $this->persistSettings($settings);
    }

    public function getRoles(array $catalog): array
    {
        $defaults = $this->getDefaultRoles($catalog);
        $roles = wp_parse_args($this->getOptionArray(self::OPTION_ROLES), $defaults);
        $families = array_keys($catalog);
        $roles = $this->normalizeRoleFamilies($roles, $families, $defaults);

        $roles['heading_fallback'] = FontUtils::sanitizeFallback((string) $roles['heading_fallback']);
        $roles['body_fallback'] = FontUtils::sanitizeFallback((string) $roles['body_fallback']);

        return $roles;
    }

    public function saveRoles(array $input, array $catalog): array
    {
        $families = array_keys($catalog);
        $defaults = $this->getDefaultRoles($catalog);
        $roles = $this->normalizeRoleFamilies(
            [
                'heading' => $this->sanitizeTextValue($input['heading'] ?? $defaults['heading']),
                'body' => $this->sanitizeTextValue($input['body'] ?? $defaults['body']),
            ],
            $families,
            $defaults
        ) + [
            'heading_fallback' => FontUtils::sanitizeFallback((string) ($input['heading_fallback'] ?? 'sans-serif')),
            'body_fallback' => FontUtils::sanitizeFallback((string) ($input['body_fallback'] ?? 'sans-serif')),
        ];

        update_option(self::OPTION_ROLES, $roles, false);

        return $roles;
    }

    public function setAutoApplyRoles(bool $enabled): array
    {
        $settings = $this->getSettings();
        $settings['auto_apply_roles'] = $enabled;

        return $this->persistSettings($settings);
    }

    public function hasGoogleApiKey(): bool
    {
        $settings = $this->getSettings();

        return trim((string) $settings['google_api_key']) !== '';
    }

    public function getGoogleApiKeyStatus(): array
    {
        $settings = $this->getSettings();

        return [
            'state' => (string) ($settings['google_api_key_status'] ?? 'empty'),
            'message' => (string) ($settings['google_api_key_status_message'] ?? ''),
            'checked_at' => (int) ($settings['google_api_key_checked_at'] ?? 0),
        ];
    }

    public function saveGoogleApiKeyStatus(string $state, string $message = ''): array
    {
        $settings = $this->getSettings();
        $normalizedState = $this->normalizeGoogleApiKeyStatus($state, (string) ($settings['google_api_key'] ?? ''));

        $settings['google_api_key_status'] = $normalizedState;
        $settings['google_api_key_status_message'] = $normalizedState === 'empty'
            ? ''
            : sanitize_text_field($message);
        $settings['google_api_key_checked_at'] = $normalizedState === 'empty' ? 0 : time();

        $this->persistSettings($settings);

        return $this->getGoogleApiKeyStatus();
    }

    public function getFamilyFallback(string $family, string $default = 'sans-serif'): string
    {
        $fallbacks = $this->getSettings()['family_fallbacks'] ?? [];

        if (!is_array($fallbacks) || trim($family) === '') {
            return FontUtils::sanitizeFallback($default);
        }

        return FontUtils::sanitizeFallback((string) ($fallbacks[$family] ?? $default));
    }

    public function saveFamilyFallback(string $family, string $fallback): array
    {
        $family = sanitize_text_field($family);

        if ($family === '') {
            return $this->getSettings()['family_fallbacks'] ?? [];
        }

        $settings = $this->getSettings();
        $fallbacks = $this->normalizeFamilyFallbacks($settings['family_fallbacks'] ?? []);
        $fallbacks[$family] = FontUtils::sanitizeFallback($fallback);
        ksort($fallbacks, SORT_NATURAL | SORT_FLAG_CASE);

        $settings['family_fallbacks'] = $fallbacks;
        $this->persistSettings($settings);

        return $fallbacks;
    }

    private function getDefaultRoles(array $catalog): array
    {
        $families = array_keys($catalog);
        $heading = $families[0] ?? '';
        $body = $families[1] ?? ($families[0] ?? '');

        return self::DEFAULT_ROLE_FALLBACKS + [
            'heading' => $heading,
            'body' => $body,
        ];
    }

    private function getOptionArray(string $option): array
    {
        $value = get_option($option, []);

        return is_array($value) ? $value : [];
    }

    private function normalizeRoleFamilies(array $roles, array $families, array $defaults): array
    {
        if ($families === []) {
            return $roles;
        }

        if (!in_array($roles['heading'] ?? '', $families, true)) {
            $roles['heading'] = $defaults['heading'];
        }

        if (!in_array($roles['body'] ?? '', $families, true)) {
            $roles['body'] = $defaults['body'];
        }

        return $roles;
    }

    private function sanitizeTextValue(mixed $value): string
    {
        return sanitize_text_field(wp_unslash((string) $value));
    }

    private function persistSettings(array $settings): array
    {
        update_option(self::OPTION_SETTINGS, $settings, false);

        return $settings;
    }

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

            $normalized[$family] = FontUtils::sanitizeFallback((string) $fallback);
        }

        return $normalized;
    }

    private function normalizeGoogleApiKeyStatus(string $state, string $apiKey): string
    {
        if (trim($apiKey) === '') {
            return 'empty';
        }

        return in_array($state, ['unknown', 'valid', 'invalid'], true) ? $state : 'unknown';
    }
}
