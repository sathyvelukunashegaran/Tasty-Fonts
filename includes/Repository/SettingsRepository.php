<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

use TastyFonts\Admin\AdminAccessService;
use TastyFonts\Maintenance\SnapshotService;
use TastyFonts\Maintenance\SiteTransferService;
use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type RoleSet from RoleRepository
 * @phpstan-import-type AdobeProjectStatus from AdobeProjectRepository
 * @phpstan-import-type GoogleApiKeyStatus from GoogleApiKeyRepository
 * @phpstan-import-type GoogleApiKeyData from GoogleApiKeyRepository
 * @phpstan-import-type FamilyFallbackMap from FamilyMetadataRepository
 * @phpstan-import-type FamilyFontDisplayMap from FamilyMetadataRepository
 * @phpstan-type AdminAccessRoleSlugList list<string>
 * @phpstan-type AdminAccessUserIdList list<int>
 * @phpstan-type SettingsInput array<string, mixed>
 * @phpstan-type NormalizedSettings array<string, mixed>
 */
final class SettingsRepository
{
    private readonly GoogleApiKeyRepository $googleApiKeyRepo;
    private readonly AdobeProjectRepository $adobeProjectRepo;
    private readonly RoleRepository $roleRepo;
    private readonly FamilyMetadataRepository $familyMetadataRepo;

    public function __construct(
        ?GoogleApiKeyRepository $googleApiKeyRepo = null,
        ?AdobeProjectRepository $adobeProjectRepo = null,
        ?RoleRepository $roleRepo = null,
        ?FamilyMetadataRepository $familyMetadataRepo = null,
    ) {
        $this->googleApiKeyRepo = $googleApiKeyRepo ?? new GoogleApiKeyRepository();
        $this->adobeProjectRepo = $adobeProjectRepo ?? new AdobeProjectRepository();
        $this->roleRepo = $roleRepo ?? new RoleRepository();
        $this->familyMetadataRepo = $familyMetadataRepo ?? new FamilyMetadataRepository();
    }

    public const UPDATE_CHANNEL_STABLE = 'stable';
    public const UPDATE_CHANNEL_BETA = 'beta';
    public const UPDATE_CHANNEL_NIGHTLY = 'nightly';
    public const OPTION_SETTINGS = 'tasty_fonts_settings';
    public const OPTION_ROLES = 'tasty_fonts_roles';
    public const OPTION_GOOGLE_API_KEY_DATA = GoogleApiKeyRepository::OPTION_GOOGLE_API_KEY_DATA;
    private const PREVIEW_SENTENCE_MAX_LENGTH = 280;
    private const OUTPUT_QUICK_MODE_MINIMAL = 'minimal';
    private const OUTPUT_QUICK_MODE_VARIABLES = 'variables';
    private const OUTPUT_QUICK_MODE_CLASSES = 'classes';
    private const OUTPUT_QUICK_MODE_CUSTOM = 'custom';
    private const OUTPUT_QUICK_MODE_VALUES = [
        self::OUTPUT_QUICK_MODE_MINIMAL,
        self::OUTPUT_QUICK_MODE_VARIABLES,
        self::OUTPUT_QUICK_MODE_CLASSES,
        self::OUTPUT_QUICK_MODE_CUSTOM,
    ];
    private const CLASS_OUTPUT_BOOLEAN_FIELDS = [
        'class_output_enabled',
        'class_output_role_heading_enabled',
        'class_output_role_body_enabled',
        'class_output_role_monospace_enabled',
        'class_output_role_alias_interface_enabled',
        'class_output_role_alias_ui_enabled',
        'class_output_role_alias_code_enabled',
        'class_output_category_sans_enabled',
        'class_output_category_serif_enabled',
        'class_output_category_mono_enabled',
        'class_output_families_enabled',
        'class_output_role_styles_enabled',
    ];
    private const CLASS_OUTPUT_SUBGROUP_FIELDS = [
        'class_output_role_heading_enabled',
        'class_output_role_body_enabled',
        'class_output_role_monospace_enabled',
        'class_output_role_alias_interface_enabled',
        'class_output_role_alias_ui_enabled',
        'class_output_role_alias_code_enabled',
        'class_output_category_sans_enabled',
        'class_output_category_serif_enabled',
        'class_output_category_mono_enabled',
        'class_output_families_enabled',
        'class_output_role_styles_enabled',
    ];
    private const VARIABLE_OUTPUT_SUBGROUP_FIELDS = [
        'extended_variable_role_weight_vars_enabled',
        'extended_variable_weight_tokens_enabled',
        'extended_variable_role_aliases_enabled',
        'extended_variable_role_alias_interface_enabled',
        'extended_variable_role_alias_ui_enabled',
        'extended_variable_role_alias_code_enabled',
        'extended_variable_category_sans_enabled',
        'extended_variable_category_serif_enabled',
        'extended_variable_category_mono_enabled',
    ];
    private const EXTENDED_VARIABLE_ROLE_ALIAS_FIELDS = [
        'extended_variable_role_alias_interface_enabled',
        'extended_variable_role_alias_ui_enabled',
        'extended_variable_role_alias_code_enabled',
    ];
    private const DEFAULT_SETTINGS = [
        'auto_apply_roles' => false,
        'applied_roles' => [],
        'delete_uploaded_files_on_uninstall' => true,
        'css_delivery_mode' => 'file',
        'font_display' => 'swap',
        'unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_OFF,
        'unicode_range_custom_value' => '',
        'fallback_heading' => FontUtils::DEFAULT_ROLE_SANS_FALLBACK,
        'fallback_body' => FontUtils::DEFAULT_ROLE_SANS_FALLBACK,
        'fallback_monospace' => FontUtils::DEFAULT_ROLE_MONOSPACE_FALLBACK,
        'output_quick_mode_preference' => self::OUTPUT_QUICK_MODE_MINIMAL,
        'class_output_enabled' => false,
        'class_output_role_heading_enabled' => true,
        'class_output_role_body_enabled' => true,
        'class_output_role_monospace_enabled' => true,
        'class_output_role_alias_interface_enabled' => true,
        'class_output_role_alias_ui_enabled' => true,
        'class_output_role_alias_code_enabled' => true,
        'class_output_category_sans_enabled' => true,
        'class_output_category_serif_enabled' => true,
        'class_output_category_mono_enabled' => true,
        'class_output_families_enabled' => true,
        'class_output_role_styles_enabled' => false,
        'minify_css_output' => true,
        'role_usage_font_weight_enabled' => false,
        'per_variant_font_variables_enabled' => true,
        'minimal_output_preset_enabled' => true,
        'extended_variable_role_weight_vars_enabled' => true,
        'extended_variable_weight_tokens_enabled' => true,
        'extended_variable_role_aliases_enabled' => true,
        'extended_variable_role_alias_interface_enabled' => true,
        'extended_variable_role_alias_ui_enabled' => true,
        'extended_variable_role_alias_code_enabled' => true,
        'extended_variable_category_sans_enabled' => true,
        'extended_variable_category_serif_enabled' => true,
        'extended_variable_category_mono_enabled' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'update_channel' => self::UPDATE_CHANNEL_STABLE,
        'block_editor_font_library_sync_enabled' => null,
        'etch_integration_enabled' => null,
        'etch_quick_roles_panel_enabled' => false,
        'bricks_integration_enabled' => null,
        'bricks_theme_styles_sync_enabled' => false,
        'bricks_theme_style_target_mode' => 'managed',
        'bricks_theme_style_target_id' => 'managed',
        'bricks_disable_google_fonts_enabled' => false,
        'oxygen_integration_enabled' => null,
        'show_activity_log' => false,
        'snapshot_retention_limit' => SnapshotService::DEFAULT_SNAPSHOT_RETENTION_LIMIT,
        'site_transfer_export_retention_limit' => SiteTransferService::DEFAULT_EXPORT_RETENTION_LIMIT,
        'training_wheels_off' => false,
        'monospace_role_enabled' => false,
        'variable_fonts_enabled' => false,
        'google_font_imports_enabled' => false,
        'bunny_font_imports_enabled' => false,
        'adobe_font_imports_enabled' => false,
        'local_font_uploads_enabled' => false,
        'custom_css_url_imports_enabled' => false,
        'admin_access_custom_enabled' => false,
        'admin_access_role_slugs' => [],
        'admin_access_user_ids' => [],
        'acss_font_role_sync_enabled' => null,
        'acss_font_role_sync_opted_out' => false,
        'acss_font_role_sync_applied' => false,
        'acss_font_role_sync_previous_heading_font_family' => '',
        'acss_font_role_sync_previous_text_font_family' => '',
        'acss_font_role_sync_previous_heading_font_weight' => '',
        'acss_font_role_sync_previous_text_font_weight' => '',
        'preview_sentence' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'adobe_enabled' => false,
        'adobe_project_id' => '',
        'adobe_project_status' => 'empty',
        'adobe_project_status_message' => '',
        'adobe_project_checked_at' => 0,
        'family_fallbacks' => [],
        'family_font_displays' => [],
    ];
    private const GLOBAL_FALLBACK_DEFAULTS = [
        'fallback_heading' => FontUtils::DEFAULT_ROLE_SANS_FALLBACK,
        'fallback_body' => FontUtils::DEFAULT_ROLE_SANS_FALLBACK,
        'fallback_monospace' => FontUtils::DEFAULT_ROLE_MONOSPACE_FALLBACK,
    ];
    /**
     * @return NormalizedSettings
     */
    public function getSettings(): array
    {
        $storedSettings = $this->getOptionArray(self::OPTION_SETTINGS);
        $settings = $this->normalizeInputMap(wp_parse_args(
            $storedSettings,
            self::DEFAULT_SETTINGS
        ));
        $settings = $this->defaultRoleAliasVariableSettingsFromLegacy($settings, $storedSettings);
        $settings = $this->mergeGoogleApiKeyDataIntoSettings($settings, $this->googleApiKeyRepo->getData());
        $settings = array_replace($settings, $this->normalizeClassOutputSettings($this->normalizeInputMap($storedSettings), $settings));
        $settings['auto_apply_roles'] = !empty($settings['auto_apply_roles']);
        $settings['font_display'] = $this->normalizeFontDisplay($this->stringValue($settings, 'font_display', 'swap'));
        $settings['unicode_range_mode'] = FontUtils::normalizeUnicodeRangeMode(
            $this->stringValue($settings, 'unicode_range_mode', FontUtils::UNICODE_RANGE_MODE_OFF)
        );
        $settings['unicode_range_custom_value'] = FontUtils::normalizeUnicodeRangeValue(
            $this->stringValue($settings, 'unicode_range_custom_value')
        );
        $settings['fallback_heading'] = $this->normalizeGlobalFallback($settings['fallback_heading'] ?? '', FontUtils::DEFAULT_ROLE_SANS_FALLBACK);
        $settings['fallback_body'] = $this->normalizeGlobalFallback($settings['fallback_body'] ?? '', FontUtils::DEFAULT_ROLE_SANS_FALLBACK);
        $settings['fallback_monospace'] = $this->normalizeGlobalFallback($settings['fallback_monospace'] ?? '', FontUtils::DEFAULT_ROLE_MONOSPACE_FALLBACK);
        $settings['minify_css_output'] = !empty($settings['minify_css_output']);
        $settings['role_usage_font_weight_enabled'] = !empty($settings['role_usage_font_weight_enabled']);
        $settings['class_output_role_styles_enabled'] = !empty($settings['class_output_role_styles_enabled']);
        $settings['per_variant_font_variables_enabled'] = !empty($settings['per_variant_font_variables_enabled']);
        $settings['minimal_output_preset_enabled'] = $this->resolveMinimalOutputPresetEnabled($storedSettings, $settings);
        $settings['extended_variable_role_weight_vars_enabled'] = !empty($settings['extended_variable_role_weight_vars_enabled']);
        $settings['extended_variable_weight_tokens_enabled'] = !empty($settings['extended_variable_weight_tokens_enabled']);
        $settings['extended_variable_role_aliases_enabled'] = !empty($settings['extended_variable_role_aliases_enabled']);
        $settings['extended_variable_role_alias_interface_enabled'] = !empty($settings['extended_variable_role_alias_interface_enabled']);
        $settings['extended_variable_role_alias_ui_enabled'] = !empty($settings['extended_variable_role_alias_ui_enabled']);
        $settings['extended_variable_role_alias_code_enabled'] = !empty($settings['extended_variable_role_alias_code_enabled']);
        $settings['extended_variable_category_sans_enabled'] = !empty($settings['extended_variable_category_sans_enabled']);
        $settings['extended_variable_category_serif_enabled'] = !empty($settings['extended_variable_category_serif_enabled']);
        $settings['extended_variable_category_mono_enabled'] = !empty($settings['extended_variable_category_mono_enabled']);
        $settings['preload_primary_fonts'] = !empty($settings['preload_primary_fonts']);
        $settings['remote_connection_hints'] = !empty($settings['remote_connection_hints']);
        $settings['update_channel'] = $this->normalizeUpdateChannel(
            $this->stringValue($settings, 'update_channel', self::UPDATE_CHANNEL_STABLE)
        );
        $settings['block_editor_font_library_sync_enabled'] = $this->normalizeBlockEditorFontLibrarySyncSetting(
            $settings['block_editor_font_library_sync_enabled'] ?? null
        );
        $settings['etch_integration_enabled'] = $this->normalizeOptionalBoolean($settings['etch_integration_enabled'] ?? null);
        $settings['etch_quick_roles_panel_enabled'] = !empty($settings['etch_quick_roles_panel_enabled']);
        $settings['bricks_integration_enabled'] = $this->normalizeOptionalBoolean($settings['bricks_integration_enabled'] ?? null);
        $settings['bricks_theme_styles_sync_enabled'] = !empty($settings['bricks_theme_styles_sync_enabled']);
        $settings['bricks_theme_style_target_mode'] = $this->normalizeBricksThemeStyleTargetMode($settings['bricks_theme_style_target_mode'] ?? 'managed');
        $settings['bricks_theme_style_target_id'] = $this->normalizeBricksThemeStyleTargetId($settings['bricks_theme_style_target_id'] ?? 'managed');
        $settings['bricks_disable_google_fonts_enabled'] = !empty($settings['bricks_disable_google_fonts_enabled']);
        $settings['oxygen_integration_enabled'] = $this->normalizeOptionalBoolean($settings['oxygen_integration_enabled'] ?? null);
        $settings['show_activity_log'] = !empty($settings['show_activity_log']);
        $settings['snapshot_retention_limit'] = SnapshotService::normalizeRetentionLimit($settings['snapshot_retention_limit'] ?? SnapshotService::DEFAULT_SNAPSHOT_RETENTION_LIMIT);
        $settings['site_transfer_export_retention_limit'] = SiteTransferService::normalizeRetentionLimit($settings['site_transfer_export_retention_limit'] ?? SiteTransferService::DEFAULT_EXPORT_RETENTION_LIMIT);
        $settings['training_wheels_off'] = !empty($settings['training_wheels_off']);
        $settings['monospace_role_enabled'] = !empty($settings['monospace_role_enabled']);
        $settings['variable_fonts_enabled'] = !empty($settings['variable_fonts_enabled']);
        $settings['google_font_imports_enabled'] = !empty($settings['google_font_imports_enabled']);
        $settings['bunny_font_imports_enabled'] = !empty($settings['bunny_font_imports_enabled']);
        $settings['adobe_font_imports_enabled'] = !empty($settings['adobe_font_imports_enabled']);
        $settings['local_font_uploads_enabled'] = !empty($settings['local_font_uploads_enabled']);
        $settings['custom_css_url_imports_enabled'] = !empty($settings['custom_css_url_imports_enabled']);
        $settings['admin_access_role_slugs'] = $this->normalizeAdminAccessRoleSlugs($settings['admin_access_role_slugs'] ?? []);
        $settings['admin_access_user_ids'] = $this->normalizeAdminAccessUserIds($settings['admin_access_user_ids'] ?? []);
        $settings['admin_access_custom_enabled'] = $this->normalizeAdminAccessCustomEnabled(
            $storedSettings['admin_access_custom_enabled'] ?? null,
            $settings
        );
        $settings['acss_font_role_sync_enabled'] = $this->normalizeOptionalBoolean($settings['acss_font_role_sync_enabled'] ?? null);
        $settings['acss_font_role_sync_opted_out'] = !empty($settings['acss_font_role_sync_opted_out']);
        $settings['acss_font_role_sync_applied'] = !empty($settings['acss_font_role_sync_applied']);
        $settings['acss_font_role_sync_previous_heading_font_family'] = $this->sanitizeTextValue($settings['acss_font_role_sync_previous_heading_font_family'] ?? '');
        $settings['acss_font_role_sync_previous_text_font_family'] = $this->sanitizeTextValue($settings['acss_font_role_sync_previous_text_font_family'] ?? '');
        $settings['acss_font_role_sync_previous_heading_font_weight'] = $this->sanitizeTextValue($settings['acss_font_role_sync_previous_heading_font_weight'] ?? '');
        $settings['acss_font_role_sync_previous_text_font_weight'] = $this->sanitizeTextValue($settings['acss_font_role_sync_previous_text_font_weight'] ?? '');
        $settings['preview_sentence'] = $this->sanitizePreviewSentence($settings['preview_sentence'] ?? '');
        $settings = $this->adobeProjectRepo->normalizeSettingsData($settings);
        $settings['fallback_heading'] = $this->normalizeGlobalFallback($settings['fallback_heading'] ?? '', FontUtils::DEFAULT_ROLE_SANS_FALLBACK);
        $settings['fallback_body'] = $this->normalizeGlobalFallback($settings['fallback_body'] ?? '', FontUtils::DEFAULT_ROLE_SANS_FALLBACK);
        $settings['fallback_monospace'] = $this->normalizeGlobalFallback($settings['fallback_monospace'] ?? '', FontUtils::DEFAULT_ROLE_MONOSPACE_FALLBACK);
        $settings = $this->familyMetadataRepo->normalizeSettingsData($settings);
        $settings['delete_uploaded_files_on_uninstall'] = !empty($settings['delete_uploaded_files_on_uninstall']);
        $settings = $this->normalizeMinimalOutputPresetSettings($settings);
        $settings = $this->normalizeAcssRoleWeightVariableOutput($settings);
        $settings = $this->normalizeMonospaceOutputSettings($settings);
        $settings = $this->normalizeRoleUsageFontWeightOutput($settings);
        $settings['output_quick_mode_preference'] = $this->resolveOutputQuickModePreference($storedSettings, $settings);

        return $settings;
    }

    /**
     * @param SettingsInput $input
     * @return NormalizedSettings
     */
    public function saveSettings(array $input): array
    {
        $input = $this->defaultRoleAliasVariableSettingsFromLegacy($input, $input);
        $settings = $this->getSettings();
        $monospaceRoleWasEnabled = !empty($settings['monospace_role_enabled']);
        $googleApiKeyData = $this->googleApiKeyRepo->getData();
        $clearGoogleKey = !empty($input['tasty_fonts_clear_google_api_key']);
        $submittedGoogleKey = array_key_exists('google_api_key', $input)
            ? sanitize_text_field($this->stringValue($input, 'google_api_key'))
            : null;
        $settingsChanged = false;
        $googleApiKeyDataChanged = false;

        if ($clearGoogleKey) {
            $googleApiKeyData = $this->googleApiKeyRepo->saveData([
                'google_api_key' => '',
                'google_api_key_status' => 'empty',
                'google_api_key_status_message' => '',
                'google_api_key_checked_at' => 0,
            ]);
            $googleApiKeyDataChanged = true;
        } elseif (is_string($submittedGoogleKey) && trim($submittedGoogleKey) !== '') {
            $googleApiKeyData = $this->googleApiKeyRepo->saveData([
                'google_api_key' => trim($submittedGoogleKey),
                'google_api_key_status' => 'unknown',
                'google_api_key_status_message' => '',
                'google_api_key_checked_at' => 0,
            ]);
            $googleApiKeyDataChanged = true;
        }

        if (isset($input['css_delivery_mode'])) {
            $mode = sanitize_text_field($this->stringValue($input, 'css_delivery_mode'));
            $settings['css_delivery_mode'] = in_array($mode, ['file', 'inline'], true) ? $mode : 'file';
            $settingsChanged = true;
        }

        if (isset($input['font_display'])) {
            $settings['font_display'] = $this->normalizeFontDisplay(
                sanitize_text_field($this->stringValue($input, 'font_display'))
            );
            $settingsChanged = true;
        }

        if (array_key_exists('unicode_range_mode', $input)) {
            $settings['unicode_range_mode'] = FontUtils::normalizeUnicodeRangeMode(
                $this->stringValue($input, 'unicode_range_mode')
            );
            $settingsChanged = true;
        }

        if (array_key_exists('unicode_range_custom_value', $input)) {
            $settings['unicode_range_custom_value'] = FontUtils::normalizeUnicodeRangeValue(
                $this->stringValue($input, 'unicode_range_custom_value')
            );
            $settingsChanged = true;
        }

        foreach (self::GLOBAL_FALLBACK_DEFAULTS as $field => $defaultFallback) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $settings[$field] = $this->normalizeGlobalFallback($input[$field], $defaultFallback);
            $settingsChanged = true;
        }

        if (array_key_exists('output_quick_mode_preference', $input)) {
            $settings['output_quick_mode_preference'] = $this->sanitizeOutputQuickModePreference($input['output_quick_mode_preference']);
            $settingsChanged = true;
        }

        if ($this->hasClassOutputInput($input)) {
            $settings = array_replace($settings, $this->normalizeClassOutputSettings($input, $settings));
            $settingsChanged = true;
        }

        if (array_key_exists('minify_css_output', $input)) {
            $settings['minify_css_output'] = !empty($input['minify_css_output']);
            $settingsChanged = true;
        }

        if (array_key_exists('role_usage_font_weight_enabled', $input)) {
            $settings['role_usage_font_weight_enabled'] = !empty($input['role_usage_font_weight_enabled']);
            $settingsChanged = true;
        }

        if (array_key_exists('class_output_role_styles_enabled', $input)) {
            $settings['class_output_role_styles_enabled'] = !empty($input['class_output_role_styles_enabled']);
            $settingsChanged = true;
        }

        if (array_key_exists('per_variant_font_variables_enabled', $input)) {
            $settings['per_variant_font_variables_enabled'] = !empty($input['per_variant_font_variables_enabled']);
            $settingsChanged = true;
        }

        if (array_key_exists('variable_fonts_enabled', $input)) {
            $settings['variable_fonts_enabled'] = !empty($input['variable_fonts_enabled']);
            $settingsChanged = true;
        }

        foreach (
            [
                'google_font_imports_enabled',
                'bunny_font_imports_enabled',
                'adobe_font_imports_enabled',
                'local_font_uploads_enabled',
                'custom_css_url_imports_enabled',
            ] as $field
        ) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $settings[$field] = !empty($input[$field]);
            $settingsChanged = true;
        }

        if (array_key_exists('admin_access_custom_enabled', $input)) {
            $settings['admin_access_custom_enabled'] = !empty($input['admin_access_custom_enabled']);
            $settingsChanged = true;
        }

        if (array_key_exists('admin_access_role_slugs', $input)) {
            $settings['admin_access_role_slugs'] = $this->normalizeAdminAccessRoleSlugs($input['admin_access_role_slugs']);
            $settingsChanged = true;
        }

        if (array_key_exists('admin_access_user_ids', $input)) {
            $settings['admin_access_user_ids'] = $this->normalizeAdminAccessUserIds($input['admin_access_user_ids']);
            $settingsChanged = true;
        }

        if (array_key_exists('minimal_output_preset_enabled', $input)) {
            $settings['minimal_output_preset_enabled'] = !empty($input['minimal_output_preset_enabled']);
            $settingsChanged = true;
        }

        foreach (
            [
                'extended_variable_role_weight_vars_enabled',
                'extended_variable_weight_tokens_enabled',
                'extended_variable_role_aliases_enabled',
                'extended_variable_role_alias_interface_enabled',
                'extended_variable_role_alias_ui_enabled',
                'extended_variable_role_alias_code_enabled',
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

        if (array_key_exists('update_channel', $input)) {
            $settings['update_channel'] = $this->normalizeUpdateChannel($this->stringValue($input, 'update_channel'));
            $settingsChanged = true;
        }

        if (array_key_exists('block_editor_font_library_sync_enabled', $input)) {
            $settings['block_editor_font_library_sync_enabled'] = !empty($input['block_editor_font_library_sync_enabled']);
            $settingsChanged = true;
        }

        if (array_key_exists('etch_integration_enabled', $input)) {
            $settings['etch_integration_enabled'] = $this->normalizeOptionalBoolean($input['etch_integration_enabled']);
            $settingsChanged = true;
        }

        if (array_key_exists('etch_quick_roles_panel_enabled', $input)) {
            $settings['etch_quick_roles_panel_enabled'] = !empty($input['etch_quick_roles_panel_enabled']);
            $settingsChanged = true;
        }

        if (array_key_exists('bricks_integration_enabled', $input)) {
            $settings['bricks_integration_enabled'] = $this->normalizeOptionalBoolean($input['bricks_integration_enabled']);
            $settingsChanged = true;
        }

        foreach (
            [
                'bricks_theme_styles_sync_enabled',
                'bricks_disable_google_fonts_enabled',
            ] as $field
        ) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $settings[$field] = !empty($input[$field]);
            $settingsChanged = true;
        }

        if (array_key_exists('bricks_theme_style_target_id', $input)) {
            $settings['bricks_theme_style_target_id'] = $this->normalizeBricksThemeStyleTargetId($input['bricks_theme_style_target_id']);
            $settingsChanged = true;
        }

        if (array_key_exists('bricks_theme_style_target_mode', $input)) {
            $settings['bricks_theme_style_target_mode'] = $this->normalizeBricksThemeStyleTargetMode($input['bricks_theme_style_target_mode']);
            $settingsChanged = true;
        }

        if (array_key_exists('oxygen_integration_enabled', $input)) {
            $settings['oxygen_integration_enabled'] = $this->normalizeOptionalBoolean($input['oxygen_integration_enabled']);
            $settingsChanged = true;
        }

        if (array_key_exists('delete_uploaded_files_on_uninstall', $input)) {
            $settings['delete_uploaded_files_on_uninstall'] = !empty($input['delete_uploaded_files_on_uninstall']);
            $settingsChanged = true;
        }

        if (array_key_exists('show_activity_log', $input)) {
            $settings['show_activity_log'] = !empty($input['show_activity_log']);
            $settingsChanged = true;
        }

        if (array_key_exists('snapshot_retention_limit', $input)) {
            $settings['snapshot_retention_limit'] = SnapshotService::normalizeRetentionLimit($input['snapshot_retention_limit']);
            $settingsChanged = true;
        }

        if (array_key_exists('site_transfer_export_retention_limit', $input)) {
            $settings['site_transfer_export_retention_limit'] = SiteTransferService::normalizeRetentionLimit($input['site_transfer_export_retention_limit']);
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

        if (array_key_exists('acss_font_role_sync_enabled', $input)) {
            $settings['acss_font_role_sync_enabled'] = $this->normalizeOptionalBoolean($input['acss_font_role_sync_enabled']);
            $settingsChanged = true;
        }

        if (array_key_exists('acss_font_role_sync_opted_out', $input)) {
            $settings['acss_font_role_sync_opted_out'] = !empty($input['acss_font_role_sync_opted_out']);
            $settingsChanged = true;
        }

        if (isset($input['preview_sentence'])) {
            $settings['preview_sentence'] = $this->sanitizePreviewSentence($input['preview_sentence']);
            $settingsChanged = true;
        }

        if (
            !array_key_exists('minimal_output_preset_enabled', $input)
            && $this->hasExplicitNonMinimalOutputInput($input)
        ) {
            $settings['minimal_output_preset_enabled'] = false;
            $settingsChanged = true;
        }

        if (
            !array_key_exists('output_quick_mode_preference', $input)
            && !empty($input['minimal_output_preset_enabled'])
        ) {
            $settings['output_quick_mode_preference'] = self::OUTPUT_QUICK_MODE_MINIMAL;
        }

        $settings = $this->applyOutputQuickModeSelectionDefaults($settings, $input);
        $settings = $this->restoreMonospaceOutputDefaultsOnFirstEnable($settings, $input, $monospaceRoleWasEnabled);
        $settings = $this->normalizeMinimalOutputPresetSettings($settings);
        $settings = $this->normalizeAcssRoleWeightVariableOutput($settings);
        $settings = $this->normalizeMonospaceOutputSettings($settings);
        $settings = $this->normalizeRoleUsageFontWeightOutput($settings);
        $settings['output_quick_mode_preference'] = $this->normalizeOutputQuickModePreference(
            $this->stringValue($settings, 'output_quick_mode_preference'),
            $settings
        );

        if ($settingsChanged) {
            update_option(self::OPTION_SETTINGS, $this->withoutGoogleApiKeyData($settings), false);
        }

        if ($settingsChanged || $googleApiKeyDataChanged) {
            $googleApiKeyData = $this->googleApiKeyRepo->saveData($googleApiKeyData);
        }

        return $this->mergeGoogleApiKeyDataIntoSettings($this->withoutGoogleApiKeyData($settings), $googleApiKeyData);
    }

    /**
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function getRoles(array $catalog): array
    {
        return $this->roleRepo->getRoles($catalog);
    }

    public function getUpdateChannel(): string
    {
        return $this->stringValue($this->getSettings(), 'update_channel', self::UPDATE_CHANNEL_STABLE);
    }

    /**
     * @param SettingsInput $input
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function saveRoles(array $input, array $catalog): array
    {
        return $this->roleRepo->saveRoles($input, $catalog);
    }

    /**
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function getAppliedRoles(array $catalog): array
    {
        return $this->roleRepo->getAppliedRoles($catalog);
    }

    /**
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function ensureAppliedRolesInitialized(array $catalog): array
    {
        $roles = $this->roleRepo->ensureAppliedRolesInitialized($catalog);

        return $roles;
    }

    /**
     * @param RoleSet $roles
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function saveAppliedRoles(array $roles, array $catalog): array
    {
        $saved = $this->roleRepo->saveAppliedRoles($roles, $catalog);

        return $saved;
    }

    /**
     * @param array<int|string, mixed> $catalog
     * @return array{roles: RoleSet, applied_roles: RoleSet}
     */
    public function clearDisabledCapabilityRoleData(
        bool $clearVariableAxes,
        bool $clearMonospaceRole,
        array $catalog
    ): array {
        $result = $this->roleRepo->clearDisabledCapabilityRoleData($clearVariableAxes, $clearMonospaceRole, $catalog);

        return $result;
    }

    /**
     * @return NormalizedSettings
     */
    public function setAutoApplyRoles(bool $enabled): array
    {
        $saved = $this->roleRepo->setAutoApplyRoles($enabled);

        return $this->getSettings();
    }

    public function isAdobeEnabled(): bool
    {
        return $this->adobeProjectRepo->isEnabled();
    }

    public function hasGoogleApiKey(): bool
    {
        return $this->googleApiKeyRepo->has();
    }

    /**
     * @return GoogleApiKeyStatus
     */
    public function getGoogleApiKeyStatus(): array
    {
        return $this->googleApiKeyRepo->getStatus();
    }

    public function clearGoogleApiKeyData(): void
    {
        $this->googleApiKeyRepo->clear();
    }

    public function isBlockEditorFontLibrarySyncEnabled(): bool
    {
        return !empty($this->getSettings()['block_editor_font_library_sync_enabled']);
    }

    /**
     * @return NormalizedSettings
     */
    public function saveAcssFontRoleSyncState(
        ?bool $enabled,
        bool $applied,
        string $previousHeading = '',
        string $previousText = '',
        string $previousHeadingWeight = '',
        string $previousTextWeight = ''
    ): array
    {
        $settings = $this->getSettings();
        $settings['acss_font_role_sync_enabled'] = $enabled;
        $settings['acss_font_role_sync_applied'] = $applied;
        $settings['acss_font_role_sync_previous_heading_font_family'] = $this->sanitizeTextValue($previousHeading);
        $settings['acss_font_role_sync_previous_text_font_family'] = $this->sanitizeTextValue($previousText);
        $settings['acss_font_role_sync_previous_heading_font_weight'] = $this->sanitizeTextValue($previousHeadingWeight);
        $settings['acss_font_role_sync_previous_text_font_weight'] = $this->sanitizeTextValue($previousTextWeight);

        return $this->persistSettings($settings);
    }

    /**
     * @return NormalizedSettings
     */
    public function resetStoredSettingsToDefaults(): array
    {
        delete_option(self::OPTION_SETTINGS);
        delete_option(self::OPTION_ROLES);
        $this->googleApiKeyRepo->clear();

        return $this->getSettings();
    }

    /**
     * @return NormalizedSettings
     */
    public function resetFamilyFallbacks(): array
    {
        $this->familyMetadataRepo->resetFallbacks();

        return $this->getSettings();
    }

    /**
     * @return NormalizedSettings
     */
    public function resetAllFallbacksToDefaults(): array
    {
        $this->familyMetadataRepo->resetAll();
        $settings = $this->getSettings();

        foreach (self::GLOBAL_FALLBACK_DEFAULTS as $field => $defaultFallback) {
            $settings[$field] = $defaultFallback;
        }

        $settings['family_fallbacks'] = [];
        $settings['family_font_displays'] = [];

        return $this->persistSettings($settings);
    }

    /**
     * @param SettingsInput $settings
     * @return NormalizedSettings
     */
    public function previewImportedSettings(array $settings): array
    {
        return $this->normalizeImportedSettings($settings);
    }

    /**
     * @param SettingsInput $settings
     * @return NormalizedSettings
     */
    public function replaceImportedSettings(array $settings): array
    {
        $normalized = $this->normalizeImportedSettings($settings);
        update_option(self::OPTION_SETTINGS, $this->withoutGoogleApiKeyData($normalized), false);
        $this->googleApiKeyRepo->clear();

        return $normalized;
    }

    /**
     * @return NormalizedSettings
     */
    public function resetLibraryStateAfterWipe(): array
    {
        $settings = $this->getSettings();
        $settings['auto_apply_roles'] = false;
        $settings['applied_roles'] = [];
        $settings['family_fallbacks'] = [];
        $settings['family_font_displays'] = [];
        $settings['adobe_enabled'] = false;
        $settings['adobe_project_id'] = '';
        $settings['adobe_project_status'] = 'empty';
        $settings['adobe_project_status_message'] = '';
        $settings['adobe_project_checked_at'] = 0;

        delete_option(self::OPTION_ROLES);

        return $this->persistSettings($settings);
    }

    /**
     * @return NormalizedSettings
     */
    public function resetIntegrationDetectionState(): array
    {
        $settings = $this->getSettings();
        $settings['block_editor_font_library_sync_enabled'] = null;
        $settings['etch_integration_enabled'] = null;
        $settings['etch_quick_roles_panel_enabled'] = false;
        $settings['bricks_integration_enabled'] = null;
        $settings['bricks_theme_styles_sync_enabled'] = false;
        $settings['bricks_theme_style_target_mode'] = 'managed';
        $settings['bricks_theme_style_target_id'] = 'managed';
        $settings['bricks_disable_google_fonts_enabled'] = false;
        $settings['oxygen_integration_enabled'] = null;
        $settings['acss_font_role_sync_enabled'] = null;
        $settings['acss_font_role_sync_opted_out'] = false;
        $settings['acss_font_role_sync_applied'] = false;
        $settings['acss_font_role_sync_previous_heading_font_family'] = '';
        $settings['acss_font_role_sync_previous_text_font_family'] = '';
        $settings['acss_font_role_sync_previous_heading_font_weight'] = '';
        $settings['acss_font_role_sync_previous_text_font_weight'] = '';

        return $this->persistSettings($settings);
    }

    public function getAdobeProjectId(): string
    {
        return $this->adobeProjectRepo->getProjectId();
    }

    /**
     * @return AdobeProjectStatus
     */
    public function getAdobeProjectStatus(): array
    {
        return $this->adobeProjectRepo->getStatus();
    }

    /**
     * @return NormalizedSettings
     */
    public function saveAdobeProject(string $projectId, bool $enabled): array
    {
        $saved = $this->adobeProjectRepo->saveProject($projectId, $enabled);

        return $this->getSettings();
    }

    /**
     * @return AdobeProjectStatus
     */
    public function saveAdobeProjectStatus(string $state, string $message = ''): array
    {
        $status = $this->adobeProjectRepo->saveStatus($state, $message);

        return $status;
    }

    /**
     * @return NormalizedSettings
     */
    public function clearAdobeProject(): array
    {
        $cleared = $this->adobeProjectRepo->clear();

        return $this->getSettings();
    }

    /**
     * @return AdobeProjectStatus
     */
    public function saveGoogleApiKeyStatus(string $state, string $message = ''): array
    {
        $status = $this->googleApiKeyRepo->saveStatus($state, $message);

        return $status;
    }

    /**
     * @return FamilyFallbackMap
     */
    public function saveFamilyFallback(string $family, string $fallback): array
    {
        $saved = $this->familyMetadataRepo->saveFallback($family, $fallback);

        return $saved;
    }

    public function getFamilyFallback(string $family, string $default = 'sans-serif'): string
    {
        return $this->familyMetadataRepo->getFallback($family, $default);
    }

    public function getFamilyFontDisplay(string $family, string $default = ''): string
    {
        return $this->familyMetadataRepo->getFontDisplay($family, $default);
    }

    /**
     * @return FamilyFontDisplayMap
     */
    public function saveFamilyFontDisplay(string $family, string $display): array
    {
        $saved = $this->familyMetadataRepo->saveFontDisplay($family, $display);

        return $saved;
    }

    /**
     * @return SettingsInput
     */
    private function getOptionArray(string $option): array
    {
        $value = get_option($option, null);

        if (is_array($value)) {
            return $this->normalizeInputMap($value);
        }

        return [];
    }

    /**
     * @param SettingsInput $settings
     * @return NormalizedSettings
     */
    private function normalizeImportedSettings(array $settings): array
    {
        $settings = $this->withoutGoogleApiKeyData($settings);
        $hasAdminAccessCustomEnabled = array_key_exists('admin_access_custom_enabled', $settings);
        $rawSettings = $settings;
        $settings = $this->normalizeInputMap(wp_parse_args($settings, self::DEFAULT_SETTINGS));
        $settings = $this->defaultRoleAliasVariableSettingsFromLegacy($settings, $rawSettings);
        $settings['acss_font_role_sync_opted_out'] = false;
        $settings['acss_font_role_sync_applied'] = false;
        $settings['acss_font_role_sync_previous_heading_font_family'] = '';
        $settings['acss_font_role_sync_previous_text_font_family'] = '';
        $settings['acss_font_role_sync_previous_heading_font_weight'] = '';
        $settings['acss_font_role_sync_previous_text_font_weight'] = '';

        if (trim($this->stringValue($settings, 'adobe_project_id')) === '') {
            $settings['adobe_enabled'] = false;
            $settings['adobe_project_status'] = 'empty';
        } else {
            $settings['adobe_project_status'] = 'unknown';
        }

        $settings['adobe_project_status_message'] = '';
        $settings['adobe_project_checked_at'] = 0;

        $settings = array_replace($settings, $this->normalizeClassOutputSettings($settings));
        $settings = $this->normalizeMinimalOutputPresetSettings($settings);
        $settings['admin_access_role_slugs'] = $this->normalizeAdminAccessRoleSlugs($settings['admin_access_role_slugs'] ?? []);
        $settings['admin_access_user_ids'] = $this->normalizeAdminAccessUserIds($settings['admin_access_user_ids'] ?? []);
        $settings['admin_access_custom_enabled'] = $this->normalizeAdminAccessCustomEnabled(
            $hasAdminAccessCustomEnabled ? ($settings['admin_access_custom_enabled'] ?? null) : null,
            $settings
        );
        $settings['output_quick_mode_preference'] = $this->normalizeOutputQuickModePreference(
            $this->stringValue($settings, 'output_quick_mode_preference'),
            $settings
        );
        $settings['auto_apply_roles'] = !empty($settings['auto_apply_roles']);
        $settings['font_display'] = $this->normalizeFontDisplay($this->stringValue($settings, 'font_display', 'swap'));
        $settings['unicode_range_mode'] = FontUtils::normalizeUnicodeRangeMode(
            $this->stringValue($settings, 'unicode_range_mode', FontUtils::UNICODE_RANGE_MODE_OFF)
        );
        $settings['unicode_range_custom_value'] = FontUtils::normalizeUnicodeRangeValue(
            $this->stringValue($settings, 'unicode_range_custom_value')
        );
        $settings['fallback_heading'] = $this->normalizeGlobalFallback($settings['fallback_heading'] ?? '', FontUtils::DEFAULT_ROLE_SANS_FALLBACK);
        $settings['fallback_body'] = $this->normalizeGlobalFallback($settings['fallback_body'] ?? '', FontUtils::DEFAULT_ROLE_SANS_FALLBACK);
        $settings['fallback_monospace'] = $this->normalizeGlobalFallback($settings['fallback_monospace'] ?? '', FontUtils::DEFAULT_ROLE_MONOSPACE_FALLBACK);
        $settings['minify_css_output'] = !empty($settings['minify_css_output']);
        $settings['role_usage_font_weight_enabled'] = !empty($settings['role_usage_font_weight_enabled']);
        $settings['class_output_role_styles_enabled'] = !empty($settings['class_output_role_styles_enabled']);
        $settings['per_variant_font_variables_enabled'] = !empty($settings['per_variant_font_variables_enabled']);
        $settings['minimal_output_preset_enabled'] = !empty($settings['minimal_output_preset_enabled']);
        $settings['extended_variable_role_weight_vars_enabled'] = !empty($settings['extended_variable_role_weight_vars_enabled']);
        $settings['extended_variable_weight_tokens_enabled'] = !empty($settings['extended_variable_weight_tokens_enabled']);
        $settings['extended_variable_role_aliases_enabled'] = !empty($settings['extended_variable_role_aliases_enabled']);
        $settings['extended_variable_role_alias_interface_enabled'] = !empty($settings['extended_variable_role_alias_interface_enabled']);
        $settings['extended_variable_role_alias_ui_enabled'] = !empty($settings['extended_variable_role_alias_ui_enabled']);
        $settings['extended_variable_role_alias_code_enabled'] = !empty($settings['extended_variable_role_alias_code_enabled']);
        $settings['extended_variable_category_sans_enabled'] = !empty($settings['extended_variable_category_sans_enabled']);
        $settings['extended_variable_category_serif_enabled'] = !empty($settings['extended_variable_category_serif_enabled']);
        $settings['extended_variable_category_mono_enabled'] = !empty($settings['extended_variable_category_mono_enabled']);
        $settings['preload_primary_fonts'] = !empty($settings['preload_primary_fonts']);
        $settings['remote_connection_hints'] = !empty($settings['remote_connection_hints']);
        $settings['update_channel'] = $this->normalizeUpdateChannel(
            $this->stringValue($settings, 'update_channel', self::UPDATE_CHANNEL_STABLE)
        );
        $settings['block_editor_font_library_sync_enabled'] = $this->normalizeBlockEditorFontLibrarySyncSetting(
            $settings['block_editor_font_library_sync_enabled'] ?? null
        );
        $settings['etch_integration_enabled'] = $this->normalizeOptionalBoolean($settings['etch_integration_enabled'] ?? null);
        $settings['etch_quick_roles_panel_enabled'] = !empty($settings['etch_quick_roles_panel_enabled']);
        $settings['bricks_integration_enabled'] = $this->normalizeOptionalBoolean($settings['bricks_integration_enabled'] ?? null);
        $settings['bricks_theme_styles_sync_enabled'] = !empty($settings['bricks_theme_styles_sync_enabled']);
        $settings['bricks_theme_style_target_mode'] = $this->normalizeBricksThemeStyleTargetMode($settings['bricks_theme_style_target_mode'] ?? 'managed');
        $settings['bricks_theme_style_target_id'] = $this->normalizeBricksThemeStyleTargetId($settings['bricks_theme_style_target_id'] ?? 'managed');
        $settings['bricks_disable_google_fonts_enabled'] = !empty($settings['bricks_disable_google_fonts_enabled']);
        $settings['oxygen_integration_enabled'] = $this->normalizeOptionalBoolean($settings['oxygen_integration_enabled'] ?? null);
        $settings['show_activity_log'] = !empty($settings['show_activity_log']);
        $settings['training_wheels_off'] = !empty($settings['training_wheels_off']);
        $settings['monospace_role_enabled'] = !empty($settings['monospace_role_enabled']);
        $settings['variable_fonts_enabled'] = !empty($settings['variable_fonts_enabled']);
        $settings['google_font_imports_enabled'] = !empty($settings['google_font_imports_enabled']);
        $settings['bunny_font_imports_enabled'] = !empty($settings['bunny_font_imports_enabled']);
        $settings['adobe_font_imports_enabled'] = !empty($settings['adobe_font_imports_enabled']);
        $settings['local_font_uploads_enabled'] = !empty($settings['local_font_uploads_enabled']);
        $settings['custom_css_url_imports_enabled'] = !empty($settings['custom_css_url_imports_enabled']);
        $settings['admin_access_role_slugs'] = $this->normalizeAdminAccessRoleSlugs($settings['admin_access_role_slugs'] ?? []);
        $settings['admin_access_custom_enabled'] = $this->normalizeAdminAccessCustomEnabled(
            $settings['admin_access_custom_enabled'] ?? null,
            $settings
        );
        $settings['admin_access_user_ids'] = [];
        $settings['acss_font_role_sync_enabled'] = $this->normalizeOptionalBoolean($settings['acss_font_role_sync_enabled'] ?? null);
        $settings['acss_font_role_sync_opted_out'] = !empty($settings['acss_font_role_sync_opted_out']);
        $settings['acss_font_role_sync_applied'] = false;
        $settings['acss_font_role_sync_previous_heading_font_family'] = '';
        $settings['acss_font_role_sync_previous_text_font_family'] = '';
        $settings['acss_font_role_sync_previous_heading_font_weight'] = '';
        $settings['acss_font_role_sync_previous_text_font_weight'] = '';
        $settings['preview_sentence'] = $this->sanitizePreviewSentence($settings['preview_sentence'] ?? '');
        $settings = $this->adobeProjectRepo->normalizeSettingsData($settings);
        $settings['fallback_heading'] = $this->normalizeGlobalFallback($settings['fallback_heading'] ?? '', FontUtils::DEFAULT_ROLE_SANS_FALLBACK);
        $settings['fallback_body'] = $this->normalizeGlobalFallback($settings['fallback_body'] ?? '', FontUtils::DEFAULT_ROLE_SANS_FALLBACK);
        $settings['fallback_monospace'] = $this->normalizeGlobalFallback($settings['fallback_monospace'] ?? '', FontUtils::DEFAULT_ROLE_MONOSPACE_FALLBACK);
        $settings = $this->familyMetadataRepo->normalizeSettingsData($settings);
        $settings['delete_uploaded_files_on_uninstall'] = !empty($settings['delete_uploaded_files_on_uninstall']);
        $settings = $this->normalizeMinimalOutputPresetSettings($settings);
        return $this->withoutGoogleApiKeyData($settings);
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
        return true;
    }

    private function normalizeBricksThemeStyleTargetId(mixed $value): string
    {
        $targetId = sanitize_text_field(is_scalar($value) ? (string) $value : '');
        $targetId = trim($targetId);

        return $targetId !== '' ? $targetId : 'managed';
    }

    private function normalizeBricksThemeStyleTargetMode(mixed $value): string
    {
        $mode = sanitize_text_field(is_scalar($value) ? (string) $value : '');
        $mode = trim($mode);

        return in_array($mode, ['managed', 'selected', 'all'], true) ? $mode : 'managed';
    }

    private function normalizeGlobalFallback(mixed $value, string $default): string
    {
        $fallback = FontUtils::sanitizeOptionalFallback(wp_unslash($this->mixedStringValue($value)));

        return $fallback !== '' ? $fallback : $default;
    }

    private function sanitizeTextValue(mixed $value): string
    {
        return sanitize_text_field(wp_unslash($this->mixedStringValue($value)));
    }

    private function sanitizePreviewSentence(mixed $value): string
    {
        $text = wp_unslash($this->mixedStringValue($value));
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            $text = mb_substr($text, 0, self::PREVIEW_SENTENCE_MAX_LENGTH);
        } else {
            $text = substr($text, 0, self::PREVIEW_SENTENCE_MAX_LENGTH);
        }

        return sanitize_text_field($text);
    }

    private function normalizeOptionalBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return !empty($value);
    }

    /**
     * @param SettingsInput|NormalizedSettings $settings
     */
    private function normalizeAdminAccessCustomEnabled(mixed $value, array $settings = []): bool
    {
        if ($value !== null) {
            return !empty($value);
        }

        $roleSlugs = is_array($settings['admin_access_role_slugs'] ?? null)
            ? $settings['admin_access_role_slugs']
            : [];
        $userIds = is_array($settings['admin_access_user_ids'] ?? null)
            ? $settings['admin_access_user_ids']
            : [];

        return $roleSlugs !== [] || $userIds !== [];
    }

    /**
     * @return AdminAccessRoleSlugList
     */
    private function normalizeAdminAccessRoleSlugs(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $registeredRoleSlugs = $this->registeredRoleSlugs();
        $normalized = [];

        foreach ($value as $roleSlug) {
            if (!is_scalar($roleSlug) && $roleSlug !== null) {
                continue;
            }

            $roleSlug = sanitize_key((string) $roleSlug);

            if (
                $roleSlug === ''
                || $roleSlug === AdminAccessService::IMPLICIT_ROLE
                || !in_array($roleSlug, $registeredRoleSlugs, true)
            ) {
                continue;
            }

            $normalized[$roleSlug] = $roleSlug;
        }

        ksort($normalized, SORT_STRING);

        return array_values($normalized);
    }

    /**
     * @return AdminAccessUserIdList
     */
    private function normalizeAdminAccessUserIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $requestedUserIds = [];

        foreach ($value as $userId) {
            if (!is_scalar($userId) && $userId !== null) {
                continue;
            }

            $userId = absint($userId);

            if ($userId <= 0) {
                continue;
            }

            $requestedUserIds[$userId] = $userId;
        }

        if ($requestedUserIds === []) {
            return [];
        }

        $existingUsers = get_users(
            [
                'include' => array_values($requestedUserIds),
            ]
        );

        $normalized = [];

        foreach ($existingUsers as $user) {
            if (!is_object($user) || !isset($user->ID) || !isset($user->roles) || !is_array($user->roles)) {
                continue;
            }

            $userId = absint(FontUtils::scalarStringValue($user->ID));
            $userRoles = array_values(
                array_filter(
                    array_map(
                        static fn (mixed $role): string => is_string($role) ? sanitize_key($role) : '',
                        $user->roles
                    ),
                    static fn (string $role): bool => $role !== ''
                )
            );

            if ($userId <= 0 || in_array('administrator', $userRoles, true)) {
                continue;
            }

            $normalized[$userId] = $userId;
        }

        ksort($normalized, SORT_NUMERIC);

        return array_values($normalized);
    }

    /**
     * @return array<int, string>
     */
    private function registeredRoleSlugs(): array
    {
        $registeredRoleSlugs = array_keys(wp_roles()->roles);

        $registeredRoleSlugs = array_values(
            array_filter(
                array_map(
                    static fn (int|string $roleSlug): string => sanitize_key((string) $roleSlug),
                    $registeredRoleSlugs
                ),
                static fn (string $roleSlug): bool => $roleSlug !== ''
            )
        );

        sort($registeredRoleSlugs, SORT_STRING);

        return $registeredRoleSlugs;
    }

    private function normalizeUpdateChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return in_array(
            $channel,
            [
                self::UPDATE_CHANNEL_STABLE,
                self::UPDATE_CHANNEL_BETA,
                self::UPDATE_CHANNEL_NIGHTLY,
            ],
            true
        ) ? $channel : self::UPDATE_CHANNEL_STABLE;
    }

    /**
     * @param NormalizedSettings $settings
     * @return NormalizedSettings
     */
    private function persistSettings(array $settings): array
    {
        $googleApiKeyData = $this->googleApiKeyRepo->saveData($this->extractGoogleApiKeyData($settings));

        $settings = array_replace($settings, $this->normalizeClassOutputSettings($settings));
        $settings = $this->normalizeMinimalOutputPresetSettings($settings);
        $settings = $this->normalizeAcssRoleWeightVariableOutput($settings);
        $settings = $this->normalizeMonospaceOutputSettings($settings);
        $settings = $this->normalizeRoleUsageFontWeightOutput($settings);
        $settings['admin_access_role_slugs'] = $this->normalizeAdminAccessRoleSlugs($settings['admin_access_role_slugs'] ?? []);
        $settings['admin_access_user_ids'] = $this->normalizeAdminAccessUserIds($settings['admin_access_user_ids'] ?? []);
        $settings['admin_access_custom_enabled'] = $this->normalizeAdminAccessCustomEnabled(
            $settings['admin_access_custom_enabled'] ?? null,
            $settings
        );
        $settings['output_quick_mode_preference'] = $this->normalizeOutputQuickModePreference(
            $this->stringValue($settings, 'output_quick_mode_preference'),
            $settings
        );
        $settings = $this->withoutGoogleApiKeyData($settings);

        update_option(self::OPTION_SETTINGS, $settings, false);

        return $this->mergeGoogleApiKeyDataIntoSettings($settings, $googleApiKeyData);
    }

    /**
     * @param NormalizedSettings $settings
     * @return NormalizedSettings
     */
    private function normalizeMinimalOutputPresetSettings(array $settings): array
    {
        if (empty($settings['minimal_output_preset_enabled'])) {
            return $settings;
        }

        $settings['class_output_enabled'] = false;
        $settings['role_usage_font_weight_enabled'] = false;
        $settings['per_variant_font_variables_enabled'] = true;
        $settings['extended_variable_role_weight_vars_enabled'] = true;
        $settings['class_output_role_styles_enabled'] = false;

        return $settings;
    }

    /**
     * @param NormalizedSettings $settings
     * @return NormalizedSettings
     */
    private function normalizeAcssRoleWeightVariableOutput(array $settings): array
    {
        if (($settings['acss_font_role_sync_enabled'] ?? null) === true) {
            $settings['extended_variable_role_weight_vars_enabled'] = true;
        }

        return $settings;
    }

    /**
     * @param NormalizedSettings $settings
     * @return NormalizedSettings
     */
    private function normalizeMonospaceOutputSettings(array $settings): array
    {
        return $settings;
    }

    /**
     * @param SettingsInput|NormalizedSettings $settings
     * @param SettingsInput|NormalizedSettings $source
     * @return SettingsInput|NormalizedSettings
     */
    private function defaultRoleAliasVariableSettingsFromLegacy(array $settings, array $source): array
    {
        if (!array_key_exists('extended_variable_role_aliases_enabled', $source)) {
            return $settings;
        }

        $legacyValue = !empty($source['extended_variable_role_aliases_enabled']);

        foreach (self::EXTENDED_VARIABLE_ROLE_ALIAS_FIELDS as $field) {
            if (!array_key_exists($field, $source)) {
                $settings[$field] = $legacyValue;
            }
        }

        return $settings;
    }

    /**
     * @param NormalizedSettings $settings
     * @return NormalizedSettings
     */
    private function normalizeRoleUsageFontWeightOutput(array $settings): array
    {
        if ($this->deriveExactOutputQuickMode($settings) !== self::OUTPUT_QUICK_MODE_CUSTOM) {
            $settings['role_usage_font_weight_enabled'] = false;
        }

        return $settings;
    }

    /**
     * @param NormalizedSettings $settings
     * @param SettingsInput $input
     * @return NormalizedSettings
     */
    private function applyOutputQuickModeSelectionDefaults(array $settings, array $input): array
    {
        if (!array_key_exists('output_quick_mode_preference', $input)) {
            return $settings;
        }

        $requestedMode = $this->sanitizeOutputQuickModePreference($input['output_quick_mode_preference']);

        if ($requestedMode === '') {
            return $settings;
        }

        if ($requestedMode === self::OUTPUT_QUICK_MODE_MINIMAL) {
            $settings['minimal_output_preset_enabled'] = true;
            $settings['class_output_enabled'] = false;
            $settings['per_variant_font_variables_enabled'] = true;
            $settings['role_usage_font_weight_enabled'] = false;
            $settings['class_output_role_styles_enabled'] = false;

            return $settings;
        }

        $settings['minimal_output_preset_enabled'] = false;
        $settings['role_usage_font_weight_enabled'] = $requestedMode === self::OUTPUT_QUICK_MODE_CUSTOM
            ? !empty($settings['role_usage_font_weight_enabled'])
            : false;

        if ($requestedMode === self::OUTPUT_QUICK_MODE_VARIABLES) {
            $settings['class_output_enabled'] = false;
            $settings['per_variant_font_variables_enabled'] = true;
            $settings = $this->defaultAbsentBooleanFields($settings, $input, self::VARIABLE_OUTPUT_SUBGROUP_FIELDS, true);
            $settings = $this->defaultAbsentBooleanFields($settings, $input, self::CLASS_OUTPUT_SUBGROUP_FIELDS, false);

            return $settings;
        }

        if ($requestedMode === self::OUTPUT_QUICK_MODE_CLASSES) {
            $settings['class_output_enabled'] = true;
            $settings['per_variant_font_variables_enabled'] = false;
            $settings = $this->defaultAbsentBooleanFields($settings, $input, self::CLASS_OUTPUT_SUBGROUP_FIELDS, true);
            $settings = $this->defaultAbsentBooleanFields($settings, $input, self::VARIABLE_OUTPUT_SUBGROUP_FIELDS, false);

            return $settings;
        }

        $settings['class_output_enabled'] = true;
        $settings['per_variant_font_variables_enabled'] = true;
        $settings = $this->defaultAbsentBooleanFields($settings, $input, self::CLASS_OUTPUT_SUBGROUP_FIELDS, true);
        $settings = $this->defaultAbsentBooleanFields($settings, $input, self::VARIABLE_OUTPUT_SUBGROUP_FIELDS, true);

        return $settings;
    }

    /**
     * @param NormalizedSettings $settings
     * @param SettingsInput $input
     * @param list<string> $fields
     * @return NormalizedSettings
     */
    private function defaultAbsentBooleanFields(array $settings, array $input, array $fields, bool $default): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $input)) {
                continue;
            }

            $settings[$field] = $default;
        }

        return $settings;
    }

    /**
     * @param SettingsInput $storedSettings
     * @param NormalizedSettings $settings
     */
    private function resolveOutputQuickModePreference(array $storedSettings, array $settings): string
    {
        $preference = array_key_exists('output_quick_mode_preference', $storedSettings)
            ? $this->sanitizeOutputQuickModePreference($storedSettings['output_quick_mode_preference'])
            : '';

        return $this->normalizeOutputQuickModePreference($preference, $settings);
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function normalizeOutputQuickModePreference(string $preference, array $settings): string
    {
        $this->sanitizeOutputQuickModePreference($preference);

        return $this->deriveExactOutputQuickMode($settings);
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function deriveExactOutputQuickMode(array $settings): string
    {
        if (!empty($settings['minimal_output_preset_enabled'])) {
            return self::OUTPUT_QUICK_MODE_MINIMAL;
        }

        $classOutputEnabled = !empty($settings['class_output_enabled']);
        $variableOutputEnabled = !empty($settings['per_variant_font_variables_enabled']);

        if ($classOutputEnabled && $variableOutputEnabled) {
            return self::OUTPUT_QUICK_MODE_CUSTOM;
        }

        if ($variableOutputEnabled) {
            return self::OUTPUT_QUICK_MODE_VARIABLES;
        }

        if ($classOutputEnabled) {
            return self::OUTPUT_QUICK_MODE_CLASSES;
        }

        return self::OUTPUT_QUICK_MODE_CUSTOM;
    }

    private function sanitizeOutputQuickModePreference(mixed $value): string
    {
        $value = strtolower(trim($this->mixedStringValue($value)));
        $value = preg_replace('/[^a-z0-9_-]+/', '', $value);
        $value = is_string($value) ? $value : '';

        return in_array($value, self::OUTPUT_QUICK_MODE_VALUES, true)
            ? $value
            : '';
    }

    /**
     * @param SettingsInput $input
     */
    private function hasExplicitNonMinimalOutputInput(array $input): bool
    {
        if ($this->hasClassOutputInput($input)) {
            return true;
        }

        foreach (
            [
                'role_usage_font_weight_enabled',
                'per_variant_font_variables_enabled',
                'extended_variable_role_weight_vars_enabled',
                'extended_variable_weight_tokens_enabled',
                'extended_variable_role_aliases_enabled',
                'extended_variable_role_alias_interface_enabled',
                'extended_variable_role_alias_ui_enabled',
                'extended_variable_role_alias_code_enabled',
                'extended_variable_category_sans_enabled',
                'extended_variable_category_serif_enabled',
                'extended_variable_category_mono_enabled',
            ] as $field
        ) {
            if (array_key_exists($field, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param SettingsInput $storedSettings
     * @param NormalizedSettings $settings
     */
    private function resolveMinimalOutputPresetEnabled(array $storedSettings, array $settings): bool
    {
        if (array_key_exists('minimal_output_preset_enabled', $storedSettings)) {
            return !empty($settings['minimal_output_preset_enabled']);
        }

        foreach (
            [
                'class_output_enabled',
                'class_output_role_heading_enabled',
                'class_output_role_body_enabled',
                'class_output_role_monospace_enabled',
                'class_output_role_alias_interface_enabled',
                'class_output_role_alias_ui_enabled',
                'class_output_role_alias_code_enabled',
                'class_output_category_sans_enabled',
                'class_output_category_serif_enabled',
                'class_output_category_mono_enabled',
                'class_output_families_enabled',
                'role_usage_font_weight_enabled',
                'per_variant_font_variables_enabled',
                'extended_variable_role_weight_vars_enabled',
                'extended_variable_weight_tokens_enabled',
                'extended_variable_role_aliases_enabled',
                'extended_variable_role_alias_interface_enabled',
                'extended_variable_role_alias_ui_enabled',
                'extended_variable_role_alias_code_enabled',
                'extended_variable_category_sans_enabled',
                'extended_variable_category_serif_enabled',
                'extended_variable_category_mono_enabled',
            ] as $field
        ) {
            if (array_key_exists($field, $storedSettings)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param SettingsInput|NormalizedSettings $settings
     * @param GoogleApiKeyData $googleApiKeyData
     * @return NormalizedSettings
     */
    private function mergeGoogleApiKeyDataIntoSettings(array $settings, array $googleApiKeyData): array
    {
        foreach (['google_api_key', 'google_api_key_status', 'google_api_key_status_message', 'google_api_key_checked_at'] as $field) {
            $settings[$field] = $googleApiKeyData[$field];
        }

        return $settings;
    }

    /**
     * @param SettingsInput|NormalizedSettings $settings
     * @return GoogleApiKeyData
     */
    private function extractGoogleApiKeyData(array $settings): array
    {
        return [
            'google_api_key' => $this->stringValue($settings, 'google_api_key'),
            'google_api_key_status' => $this->stringValue($settings, 'google_api_key_status', 'empty'),
            'google_api_key_status_message' => $this->stringValue($settings, 'google_api_key_status_message'),
            'google_api_key_checked_at' => $this->intValue($settings, 'google_api_key_checked_at'),
        ];
    }

    /**
     * @param SettingsInput|NormalizedSettings $settings
     * @return SettingsInput|NormalizedSettings
     */
    private function withoutGoogleApiKeyData(array $settings): array
    {
        foreach (['google_api_key', 'google_api_key_status', 'google_api_key_status_message', 'google_api_key_checked_at'] as $field) {
            unset($settings[$field]);
        }

        return $settings;
    }

    private function normalizeFontDisplay(string $display): string
    {
        return $this->isSupportedFontDisplay($display)
            ? $display
            : 'swap';
    }

    private function isSupportedFontDisplay(string $display): bool
    {
        return in_array($display, ['auto', 'block', 'swap', 'fallback', 'optional'], true);
    }

    /**
     * @param SettingsInput $input
     */
    private function hasClassOutputInput(array $input): bool
    {
        foreach (self::CLASS_OUTPUT_BOOLEAN_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param SettingsInput|NormalizedSettings $input
     * @param SettingsInput|NormalizedSettings|null $fallback
     * @return array<string, bool>
     */
    private function normalizeClassOutputSettings(array $input, ?array $fallback = null): array
    {
        $defaults = [];

        foreach (self::CLASS_OUTPUT_BOOLEAN_FIELDS as $field) {
            $defaults[$field] = self::DEFAULT_SETTINGS[$field];
        }

        $fallback = is_array($fallback) ? $fallback : [];
        $normalized = $defaults;
        $hasBooleanInput = false;

        foreach (self::CLASS_OUTPUT_BOOLEAN_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $normalized[$field] = !empty($input[$field]);
                $hasBooleanInput = true;
                continue;
            }

            if (array_key_exists($field, $fallback)) {
                $normalized[$field] = !empty($fallback[$field]);
            }
        }

        return $normalized;
    }

    /**
     * @param NormalizedSettings $settings
     * @param SettingsInput $input
     * @return NormalizedSettings
     */
    private function restoreMonospaceOutputDefaultsOnFirstEnable(array $settings, array $input, bool $monospaceRoleWasEnabled): array
    {
        return $settings;
    }

    private function normalizeTimestamp(mixed $value): int
    {
        if (!is_scalar($value) && $value !== null) {
            return 0;
        }

        return max(0, absint($value));
    }
    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        return $this->mixedStringValue($values[$key], $default);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function intValue(array $values, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        return $this->normalizeTimestamp($values[$key]);
    }

    private function mixedStringValue(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeInputMap(mixed $value): array
    {
        return FontUtils::normalizeStringKeyedMap($value);
    }
}
