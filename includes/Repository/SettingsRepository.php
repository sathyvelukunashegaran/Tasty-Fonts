<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;
use TastyFonts\Support\SiteEnvironment;

final class SettingsRepository
{
    public const OPTION_SETTINGS = 'tasty_fonts_settings';
    public const OPTION_ROLES = 'tasty_fonts_roles';
    public const OPTION_GOOGLE_API_KEY_DATA = 'tasty_fonts_google_api_key_data';
    private const ROLE_FAMILY_KEYS = ['heading', 'body', 'monospace'];
    private const LEGACY_OPTION_SETTINGS = 'etch_fonts_settings';
    private const LEGACY_OPTION_ROLES = 'etch_fonts_roles';
    private const DEFAULT_SETTINGS = [
        'auto_apply_roles' => false,
        'applied_roles' => [],
        'delete_uploaded_files_on_uninstall' => false,
        'css_delivery_mode' => 'file',
        'font_display' => 'optional',
        'minify_css_output' => true,
        'per_variant_font_variables_enabled' => true,
        'extended_variable_weight_tokens_enabled' => true,
        'extended_variable_role_aliases_enabled' => true,
        'extended_variable_category_sans_enabled' => true,
        'extended_variable_category_serif_enabled' => true,
        'extended_variable_category_mono_enabled' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'block_editor_font_library_sync_enabled' => null,
        'training_wheels_off' => false,
        'monospace_role_enabled' => false,
        'preview_sentence' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'adobe_enabled' => false,
        'adobe_project_id' => '',
        'adobe_project_status' => 'empty',
        'adobe_project_status_message' => '',
        'adobe_project_checked_at' => 0,
        'family_fallbacks' => [],
        'family_font_displays' => [],
    ];
    private const DEFAULT_GOOGLE_API_KEY_DATA = [
        'google_api_key' => '',
        'google_api_key_status' => 'empty',
        'google_api_key_status_message' => '',
        'google_api_key_checked_at' => 0,
    ];
    private const GOOGLE_API_KEY_FIELDS = [
        'google_api_key',
        'google_api_key_status',
        'google_api_key_status_message',
        'google_api_key_checked_at',
    ];
    private const DEFAULT_ROLE_FALLBACKS = [
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
    ];

    public function getSettings(): array
    {
        $settings = wp_parse_args(
            $this->getOptionArray(self::OPTION_SETTINGS, self::LEGACY_OPTION_SETTINGS),
            self::DEFAULT_SETTINGS
        );
        $settings = $this->mergeGoogleApiKeyDataIntoSettings($settings, $this->getGoogleApiKeyDataFromOptions($settings));
        $settings['auto_apply_roles'] = !empty($settings['auto_apply_roles']);
        $settings['font_display'] = $this->normalizeFontDisplay((string) ($settings['font_display'] ?? 'optional'));
        $settings['minify_css_output'] = !empty($settings['minify_css_output']);
        $settings['per_variant_font_variables_enabled'] = !empty($settings['per_variant_font_variables_enabled']);
        $settings['extended_variable_weight_tokens_enabled'] = !empty($settings['extended_variable_weight_tokens_enabled']);
        $settings['extended_variable_role_aliases_enabled'] = !empty($settings['extended_variable_role_aliases_enabled']);
        $settings['extended_variable_category_sans_enabled'] = !empty($settings['extended_variable_category_sans_enabled']);
        $settings['extended_variable_category_serif_enabled'] = !empty($settings['extended_variable_category_serif_enabled']);
        $settings['extended_variable_category_mono_enabled'] = !empty($settings['extended_variable_category_mono_enabled']);
        $settings['preload_primary_fonts'] = !empty($settings['preload_primary_fonts']);
        $settings['remote_connection_hints'] = !empty($settings['remote_connection_hints']);
        $settings['block_editor_font_library_sync_enabled'] = $this->normalizeBlockEditorFontLibrarySyncSetting(
            $settings['block_editor_font_library_sync_enabled'] ?? null
        );
        $settings['training_wheels_off'] = !empty($settings['training_wheels_off']);
        $settings['monospace_role_enabled'] = !empty($settings['monospace_role_enabled']);
        $settings['adobe_enabled'] = !empty($settings['adobe_enabled']);
        $settings['adobe_project_id'] = $this->sanitizeAdobeProjectId((string) ($settings['adobe_project_id'] ?? ''));
        $settings['adobe_project_status'] = $this->normalizeAdobeProjectStatus(
            (string) ($settings['adobe_project_status'] ?? 'empty'),
            (string) ($settings['adobe_project_id'] ?? '')
        );
        $settings['adobe_project_status_message'] = $this->sanitizeStatusMessage($settings['adobe_project_status_message'] ?? '');
        $settings['adobe_project_checked_at'] = $this->normalizeTimestamp($settings['adobe_project_checked_at'] ?? 0);
        $settings['google_api_key_status'] = $this->normalizeGoogleApiKeyStatus(
            (string) ($settings['google_api_key_status'] ?? 'empty'),
            (string) ($settings['google_api_key'] ?? '')
        );
        $settings['google_api_key_status_message'] = $this->sanitizeStatusMessage($settings['google_api_key_status_message'] ?? '');
        $settings['google_api_key_checked_at'] = $this->normalizeTimestamp($settings['google_api_key_checked_at'] ?? 0);
        $settings['family_fallbacks'] = $this->normalizeFamilyFallbacks($settings['family_fallbacks'] ?? []);
        $settings['family_font_displays'] = $this->normalizeFamilyFontDisplays($settings['family_font_displays'] ?? []);
        $settings['delete_uploaded_files_on_uninstall'] = !empty($settings['delete_uploaded_files_on_uninstall']);

        return $settings;
    }

    public function saveSettings(array $input): array
    {
        $settings = $this->getSettings();
        $googleApiKeyData = $this->getGoogleApiKeyDataFromOptions($settings);
        $clearGoogleKey = !empty($input['tasty_fonts_clear_google_api_key']);
        $submittedGoogleKey = isset($input['google_api_key'])
            ? sanitize_text_field((string) $input['google_api_key'])
            : null;
        $settingsChanged = false;
        $googleApiKeyDataChanged = false;

        if ($clearGoogleKey) {
            $googleApiKeyData['google_api_key'] = '';
            $googleApiKeyData['google_api_key_status'] = 'empty';
            $googleApiKeyData['google_api_key_status_message'] = '';
            $googleApiKeyData['google_api_key_checked_at'] = 0;
            $googleApiKeyDataChanged = true;
        } elseif (is_string($submittedGoogleKey) && trim($submittedGoogleKey) !== '') {
            $googleApiKeyData['google_api_key'] = trim($submittedGoogleKey);
            $googleApiKeyData['google_api_key_status'] = 'unknown';
            $googleApiKeyData['google_api_key_status_message'] = '';
            $googleApiKeyData['google_api_key_checked_at'] = 0;
            $googleApiKeyDataChanged = true;
        }

        if (isset($input['css_delivery_mode'])) {
            $mode = sanitize_text_field((string) $input['css_delivery_mode']);
            $settings['css_delivery_mode'] = in_array($mode, ['file', 'inline'], true) ? $mode : 'file';
            $settingsChanged = true;
        }

        if (isset($input['font_display'])) {
            $settings['font_display'] = $this->normalizeFontDisplay(sanitize_text_field((string) $input['font_display']));
            $settingsChanged = true;
        }

        if (array_key_exists('minify_css_output', $input)) {
            $settings['minify_css_output'] = !empty($input['minify_css_output']);
            $settingsChanged = true;
        }

        if (array_key_exists('per_variant_font_variables_enabled', $input)) {
            $settings['per_variant_font_variables_enabled'] = !empty($input['per_variant_font_variables_enabled']);
            $settingsChanged = true;
        }

        foreach (
            [
                'extended_variable_weight_tokens_enabled',
                'extended_variable_role_aliases_enabled',
                'extended_variable_category_sans_enabled',
                'extended_variable_category_serif_enabled',
                'extended_variable_category_mono_enabled',
            ] as $field
        ) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $settings[$field] = !empty($input[$field]);
            $settingsChanged = true;
        }

        if (array_key_exists('preload_primary_fonts', $input)) {
            $settings['preload_primary_fonts'] = !empty($input['preload_primary_fonts']);
            $settingsChanged = true;
        }

        if (array_key_exists('remote_connection_hints', $input)) {
            $settings['remote_connection_hints'] = !empty($input['remote_connection_hints']);
            $settingsChanged = true;
        }

        if (array_key_exists('block_editor_font_library_sync_enabled', $input)) {
            $settings['block_editor_font_library_sync_enabled'] = !empty($input['block_editor_font_library_sync_enabled']);
            $settingsChanged = true;
        }

        if (array_key_exists('delete_uploaded_files_on_uninstall', $input)) {
            $settings['delete_uploaded_files_on_uninstall'] = !empty($input['delete_uploaded_files_on_uninstall']);
            $settingsChanged = true;
        }

        if (array_key_exists('training_wheels_off', $input)) {
            $settings['training_wheels_off'] = !empty($input['training_wheels_off']);
            $settingsChanged = true;
        }

        if (array_key_exists('monospace_role_enabled', $input)) {
            $settings['monospace_role_enabled'] = !empty($input['monospace_role_enabled']);
            $settingsChanged = true;
        }

        if (isset($input['preview_sentence'])) {
            $settings['preview_sentence'] = sanitize_text_field((string) $input['preview_sentence']);
            $settingsChanged = true;
        }

        if ($settingsChanged) {
            update_option(self::OPTION_SETTINGS, $this->withoutGoogleApiKeyData($settings), false);
        }

        if ($settingsChanged || $googleApiKeyDataChanged) {
            $googleApiKeyData = $this->persistGoogleApiKeyData($googleApiKeyData);
        }

        return $this->mergeGoogleApiKeyDataIntoSettings($this->withoutGoogleApiKeyData($settings), $googleApiKeyData);
    }

    public function getRoles(array $catalog): array
    {
        return $this->normalizeRoleSet(
            $this->getOptionArray(self::OPTION_ROLES, self::LEGACY_OPTION_ROLES),
            $catalog
        );
    }

    public function saveRoles(array $input, array $catalog): array
    {
        $storedRoles = $this->normalizeRoleSet(
            $this->getOptionArray(self::OPTION_ROLES, self::LEGACY_OPTION_ROLES),
            $catalog
        );
        $roles = $storedRoles;

        foreach (self::ROLE_FAMILY_KEYS as $roleKey) {
            if (!array_key_exists($roleKey, $input)) {
                continue;
            }

            $roles[$roleKey] = $this->sanitizeTextValue($input[$roleKey]);
        }

        foreach (self::DEFAULT_ROLE_FALLBACKS as $roleKey => $defaultFallback) {
            if (!array_key_exists($roleKey, $input)) {
                continue;
            }

            $roles[$roleKey] = $this->normalizeRoleFallback($input[$roleKey], $defaultFallback);
        }

        $roles = $this->normalizeRoleSet(
            $roles,
            $catalog
        );

        update_option(self::OPTION_ROLES, $roles, false);

        return $roles;
    }

    public function getAppliedRoles(array $catalog): array
    {
        $settings = $this->getSettings();
        $storedAppliedRoles = is_array($settings['applied_roles'] ?? null)
            ? $settings['applied_roles']
            : [];

        if ($storedAppliedRoles === [] && !empty($settings['auto_apply_roles'])) {
            $storedAppliedRoles = $this->getOptionArray(self::OPTION_ROLES, self::LEGACY_OPTION_ROLES);
        }

        return $this->normalizeRoleSet($storedAppliedRoles, $catalog);
    }

    public function ensureAppliedRolesInitialized(array $catalog): array
    {
        $settings = $this->getSettings();
        $storedAppliedRoles = is_array($settings['applied_roles'] ?? null)
            ? $settings['applied_roles']
            : [];

        if ($storedAppliedRoles !== [] || empty($settings['auto_apply_roles'])) {
            return $this->normalizeRoleSet($storedAppliedRoles, $catalog);
        }

        $currentRoles = $this->getRoles($catalog);
        $settings['applied_roles'] = $currentRoles;
        $this->persistSettings($settings);

        return $currentRoles;
    }

    public function saveAppliedRoles(array $roles, array $catalog): array
    {
        $settings = $this->getSettings();
        $existingRoles = is_array($settings['applied_roles'] ?? null)
            ? $this->normalizeRoleSet($settings['applied_roles'], $catalog)
            : $this->getRoles($catalog);
        $normalizedRoles = $this->normalizeRoleSet(array_replace($existingRoles, $roles), $catalog);
        $settings['applied_roles'] = $normalizedRoles;
        $this->persistSettings($settings);

        return $normalizedRoles;
    }

    public function setAutoApplyRoles(bool $enabled): array
    {
        $settings = $this->getSettings();
        $settings['auto_apply_roles'] = $enabled;

        return $this->persistSettings($settings);
    }

    public function hasGoogleApiKey(): bool
    {
        $googleApiKeyData = $this->getGoogleApiKeyDataFromOptions();

        return trim((string) $googleApiKeyData['google_api_key']) !== '';
    }

    public function isAdobeEnabled(): bool
    {
        return !empty($this->getSettings()['adobe_enabled']);
    }

    public function isBlockEditorFontLibrarySyncEnabled(): bool
    {
        return !empty($this->getSettings()['block_editor_font_library_sync_enabled']);
    }

    public function getAdobeProjectId(): string
    {
        return trim((string) ($this->getSettings()['adobe_project_id'] ?? ''));
    }

    public function getAdobeProjectStatus(): array
    {
        $settings = $this->getSettings();

        return [
            'state' => (string) ($settings['adobe_project_status'] ?? 'empty'),
            'message' => (string) ($settings['adobe_project_status_message'] ?? ''),
            'checked_at' => (int) ($settings['adobe_project_checked_at'] ?? 0),
        ];
    }

    public function saveAdobeProject(string $projectId, bool $enabled): array
    {
        $settings = $this->getSettings();
        $settings['adobe_project_id'] = $this->sanitizeAdobeProjectId($projectId);
        $settings['adobe_enabled'] = $settings['adobe_project_id'] !== '' ? $enabled : false;
        $settings['adobe_project_status'] = $settings['adobe_project_id'] === '' ? 'empty' : 'unknown';
        $settings['adobe_project_status_message'] = '';
        $settings['adobe_project_checked_at'] = 0;

        return $this->persistSettings($settings);
    }

    public function saveAdobeProjectStatus(string $state, string $message = ''): array
    {
        $settings = $this->getSettings();
        $normalizedState = $this->normalizeAdobeProjectStatus($state, (string) ($settings['adobe_project_id'] ?? ''));

        $settings['adobe_project_status'] = $normalizedState;
        $settings['adobe_project_status_message'] = $normalizedState === 'empty'
            ? ''
            : sanitize_text_field($message);
        $settings['adobe_project_checked_at'] = $normalizedState === 'empty' ? 0 : time();

        $this->persistSettings($settings);

        return $this->getAdobeProjectStatus();
    }

    public function clearAdobeProject(): array
    {
        $settings = $this->getSettings();
        $settings['adobe_enabled'] = false;
        $settings['adobe_project_id'] = '';
        $settings['adobe_project_status'] = 'empty';
        $settings['adobe_project_status_message'] = '';
        $settings['adobe_project_checked_at'] = 0;

        return $this->persistSettings($settings);
    }

    public function getGoogleApiKeyStatus(): array
    {
        $googleApiKeyData = $this->getGoogleApiKeyDataFromOptions();

        return [
            'state' => (string) ($googleApiKeyData['google_api_key_status'] ?? 'empty'),
            'message' => (string) ($googleApiKeyData['google_api_key_status_message'] ?? ''),
            'checked_at' => (int) ($googleApiKeyData['google_api_key_checked_at'] ?? 0),
        ];
    }

    public function saveGoogleApiKeyStatus(string $state, string $message = ''): array
    {
        $googleApiKeyData = $this->getGoogleApiKeyDataFromOptions();
        $normalizedState = $this->normalizeGoogleApiKeyStatus($state, (string) ($googleApiKeyData['google_api_key'] ?? ''));

        $googleApiKeyData['google_api_key_status'] = $normalizedState;
        $googleApiKeyData['google_api_key_status_message'] = $normalizedState === 'empty'
            ? ''
            : sanitize_text_field($message);
        $googleApiKeyData['google_api_key_checked_at'] = $normalizedState === 'empty' ? 0 : time();
        $googleApiKeyData = $this->persistGoogleApiKeyData($googleApiKeyData);

        return [
            'state' => (string) ($googleApiKeyData['google_api_key_status'] ?? 'empty'),
            'message' => (string) ($googleApiKeyData['google_api_key_status_message'] ?? ''),
            'checked_at' => (int) ($googleApiKeyData['google_api_key_checked_at'] ?? 0),
        ];
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

    public function getFamilyFontDisplay(string $family, string $default = ''): string
    {
        $displays = $this->getSettings()['family_font_displays'] ?? [];

        if (!is_array($displays) || trim($family) === '') {
            return $default === '' ? '' : $this->normalizeFontDisplay($default);
        }

        if (!array_key_exists($family, $displays)) {
            return $default === '' ? '' : $this->normalizeFontDisplay($default);
        }

        return $this->normalizeFontDisplay((string) $displays[$family]);
    }

    public function saveFamilyFontDisplay(string $family, string $display): array
    {
        $family = sanitize_text_field($family);

        if ($family === '') {
            return $this->getSettings()['family_font_displays'] ?? [];
        }

        $settings = $this->getSettings();
        $displays = $this->normalizeFamilyFontDisplays($settings['family_font_displays'] ?? []);
        $display = sanitize_text_field($display);

        if ($display === 'inherit') {
            unset($displays[$family]);
        } elseif ($this->isSupportedFontDisplay($display)) {
            $displays[$family] = $display;
        } else {
            unset($displays[$family]);
        }

        ksort($displays, SORT_NATURAL | SORT_FLAG_CASE);

        $settings['family_font_displays'] = $displays;
        $this->persistSettings($settings);

        return $displays;
    }

    private function getDefaultRoles(array $catalog): array
    {
        return self::DEFAULT_ROLE_FALLBACKS + [
            'heading' => '',
            'body' => '',
            'monospace' => '',
        ];
    }

    private function getOptionArray(string $option, ?string $legacyOption = null): array
    {
        $value = get_option($option, null);

        if (is_array($value)) {
            return $value;
        }

        $legacyValue = $legacyOption !== null ? get_option($legacyOption, null) : null;

        if (!is_array($legacyValue)) {
            return [];
        }

        update_option($option, $legacyValue, false);

        return $legacyValue;
    }

    private function normalizeRoleSet(array $roles, array $catalog): array
    {
        $defaults = $this->getDefaultRoles($catalog);
        $normalizedRoles = wp_parse_args($roles, $defaults);
        $normalizedRoles['heading'] = $this->sanitizeTextValue($normalizedRoles['heading'] ?? '');
        $normalizedRoles['body'] = $this->sanitizeTextValue($normalizedRoles['body'] ?? '');
        $normalizedRoles['monospace'] = $this->sanitizeTextValue($normalizedRoles['monospace'] ?? '');
        $normalizedRoles['heading_fallback'] = $this->normalizeRoleFallback($normalizedRoles['heading_fallback'] ?? '', 'sans-serif');
        $normalizedRoles['body_fallback'] = $this->normalizeRoleFallback($normalizedRoles['body_fallback'] ?? '', 'sans-serif');
        $normalizedRoles['monospace_fallback'] = $this->normalizeRoleFallback($normalizedRoles['monospace_fallback'] ?? '', 'monospace');

        return $normalizedRoles;
    }

    private function normalizeBlockEditorFontLibrarySyncSetting(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return $this->defaultBlockEditorFontLibrarySyncEnabled();
        }

        return !empty($value);
    }

    private function defaultBlockEditorFontLibrarySyncEnabled(): bool
    {
        if (!function_exists('rest_url')) {
            return true;
        }

        return !SiteEnvironment::isLikelyLocalEnvironment(
            rest_url(''),
            SiteEnvironment::currentEnvironmentType()
        );
    }

    private function normalizeRoleFallback(mixed $value, string $default): string
    {
        $rawValue = trim(wp_unslash((string) $value));

        if ($rawValue === '') {
            return $default;
        }

        return FontUtils::sanitizeFallback($rawValue);
    }

    private function sanitizeTextValue(mixed $value): string
    {
        return sanitize_text_field(wp_unslash((string) $value));
    }

    private function extractFamilyNames(array $catalog): array
    {
        if ($catalog === []) {
            return [];
        }

        if (!array_is_list($catalog)) {
            return array_values(
                array_filter(
                    array_map(static fn (mixed $name): string => is_string($name) ? trim($name) : '', array_keys($catalog)),
                    'strlen'
                )
            );
        }

        $families = [];

        foreach ($catalog as $item) {
            if (is_string($item) && trim($item) !== '') {
                $families[] = trim($item);
                continue;
            }

            if (is_array($item) && is_string($item['family'] ?? null) && trim((string) $item['family']) !== '') {
                $families[] = trim((string) $item['family']);
            }
        }

        return array_values(array_unique($families));
    }

    private function persistSettings(array $settings): array
    {
        $googleApiKeyData = $this->extractGoogleApiKeyData($settings);

        if ($googleApiKeyData === []) {
            $googleApiKeyData = $this->getGoogleApiKeyDataFromOptions();
        } else {
            $googleApiKeyData = $this->normalizeGoogleApiKeyData($googleApiKeyData);
        }

        $settings = $this->withoutGoogleApiKeyData($settings);

        update_option(self::OPTION_SETTINGS, $settings, false);
        $googleApiKeyData = $this->persistGoogleApiKeyData($googleApiKeyData);

        return $this->mergeGoogleApiKeyDataIntoSettings($settings, $googleApiKeyData);
    }

    private function getGoogleApiKeyDataFromOptions(array $settings = []): array
    {
        $googleApiKeyData = get_option(self::OPTION_GOOGLE_API_KEY_DATA, null);

        if (!is_array($googleApiKeyData)) {
            if ($settings === []) {
                $settings = $this->getOptionArray(self::OPTION_SETTINGS, self::LEGACY_OPTION_SETTINGS);
            }

            $googleApiKeyData = $this->extractGoogleApiKeyData($settings);
        }

        return $this->normalizeGoogleApiKeyData($googleApiKeyData);
    }

    private function persistGoogleApiKeyData(array $googleApiKeyData): array
    {
        $googleApiKeyData = $this->normalizeGoogleApiKeyData($googleApiKeyData);

        update_option(self::OPTION_GOOGLE_API_KEY_DATA, $googleApiKeyData, false);

        return $googleApiKeyData;
    }

    private function mergeGoogleApiKeyDataIntoSettings(array $settings, array $googleApiKeyData): array
    {
        foreach (self::GOOGLE_API_KEY_FIELDS as $field) {
            $settings[$field] = $googleApiKeyData[$field] ?? self::DEFAULT_GOOGLE_API_KEY_DATA[$field];
        }

        return $settings;
    }

    private function extractGoogleApiKeyData(array $settings): array
    {
        $googleApiKeyData = [];

        foreach (self::GOOGLE_API_KEY_FIELDS as $field) {
            if (!array_key_exists($field, $settings)) {
                continue;
            }

            $googleApiKeyData[$field] = $settings[$field];
        }

        return $googleApiKeyData;
    }

    private function withoutGoogleApiKeyData(array $settings): array
    {
        foreach (self::GOOGLE_API_KEY_FIELDS as $field) {
            unset($settings[$field]);
        }

        return $settings;
    }

    private function normalizeGoogleApiKeyData(mixed $value): array
    {
        $googleApiKeyData = is_array($value) ? $value : [];
        $googleApiKeyData = wp_parse_args($googleApiKeyData, self::DEFAULT_GOOGLE_API_KEY_DATA);
        $googleApiKeyData['google_api_key'] = trim(sanitize_text_field((string) ($googleApiKeyData['google_api_key'] ?? '')));
        $googleApiKeyData['google_api_key_status'] = $this->normalizeGoogleApiKeyStatus(
            (string) ($googleApiKeyData['google_api_key_status'] ?? 'empty'),
            (string) ($googleApiKeyData['google_api_key'] ?? '')
        );
        $googleApiKeyData['google_api_key_status_message'] = $this->sanitizeStatusMessage($googleApiKeyData['google_api_key_status_message'] ?? '');
        $googleApiKeyData['google_api_key_checked_at'] = $this->normalizeTimestamp($googleApiKeyData['google_api_key_checked_at'] ?? 0);

        return $googleApiKeyData;
    }

    private function normalizeFontDisplay(string $display): string
    {
        return $this->isSupportedFontDisplay($display)
            ? $display
            : 'optional';
    }

    private function isSupportedFontDisplay(string $display): bool
    {
        return in_array($display, ['auto', 'block', 'swap', 'fallback', 'optional'], true);
    }

    private function sanitizeAdobeProjectId(string $projectId): string
    {
        $projectId = strtolower(trim($projectId));
        $projectId = preg_replace('/[^a-z0-9]+/', '', $projectId) ?? '';

        return trim($projectId);
    }

    private function sanitizeStatusMessage(mixed $message): string
    {
        return sanitize_text_field((string) $message);
    }

    private function normalizeTimestamp(mixed $value): int
    {
        return max(0, absint($value));
    }

    private function normalizeAdobeProjectStatus(string $state, string $projectId): string
    {
        $state = sanitize_text_field($state);

        if ($this->sanitizeAdobeProjectId($projectId) === '') {
            return 'empty';
        }

        return in_array($state, ['valid', 'invalid', 'unknown'], true) ? $state : 'unknown';
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

    private function normalizeFamilyFontDisplays(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $family => $display) {
            $family = sanitize_text_field((string) $family);
            $display = sanitize_text_field((string) $display);

            if ($family === '' || !$this->isSupportedFontDisplay($display)) {
                continue;
            }

            $normalized[$family] = $display;
        }

        ksort($normalized, SORT_NATURAL | SORT_FLAG_CASE);

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
