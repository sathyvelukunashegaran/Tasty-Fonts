<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Integrations\BricksIntegrationService;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;

final class SettingsSaveFields
{
    /**
     * @return array<int, array{name: string, kind: string, values?: array<int, string>}>
     */
    public static function definitions(): array
    {
        return [
            ['name' => 'google_api_key', 'kind' => 'text'],
            ['name' => 'tasty_fonts_clear_google_api_key', 'kind' => 'toggle'],
            ['name' => 'css_delivery_mode', 'kind' => 'enum', 'values' => ['file', 'inline']],
            ['name' => 'font_display', 'kind' => 'enum', 'values' => ['auto', 'block', 'swap', 'fallback', 'optional']],
            ['name' => 'unicode_range_mode', 'kind' => 'enum', 'values' => [
                FontUtils::UNICODE_RANGE_MODE_PRESERVE,
                FontUtils::UNICODE_RANGE_MODE_LATIN_BASIC,
                FontUtils::UNICODE_RANGE_MODE_LATIN_EXTENDED,
                FontUtils::UNICODE_RANGE_MODE_OFF,
                FontUtils::UNICODE_RANGE_MODE_CUSTOM,
            ]],
            ['name' => 'unicode_range_custom_value', 'kind' => 'text'],
            ['name' => 'output_quick_mode_preference', 'kind' => 'enum', 'values' => ['minimal', 'variables', 'classes', 'custom']],
            ['name' => 'preview_sentence', 'kind' => 'text'],
            ['name' => 'update_channel', 'kind' => 'enum', 'values' => [
                SettingsRepository::UPDATE_CHANNEL_STABLE,
                SettingsRepository::UPDATE_CHANNEL_BETA,
                SettingsRepository::UPDATE_CHANNEL_NIGHTLY,
            ]],
            ['name' => 'bricks_theme_style_target_mode', 'kind' => 'enum', 'values' => [
                BricksIntegrationService::TARGET_MODE_MANAGED,
                BricksIntegrationService::TARGET_MODE_SELECTED,
                BricksIntegrationService::TARGET_MODE_ALL,
            ]],
            ['name' => 'bricks_theme_style_target_id', 'kind' => 'text'],
            ['name' => 'bricks_create_theme_style', 'kind' => 'toggle'],
            ['name' => 'bricks_delete_theme_style', 'kind' => 'toggle'],
            ['name' => 'bricks_reset_integration', 'kind' => 'toggle'],
            ['name' => 'minify_css_output', 'kind' => 'toggle'],
            ['name' => 'class_output_enabled', 'kind' => 'toggle'],
            ['name' => 'class_output_role_heading_enabled', 'kind' => 'toggle'],
            ['name' => 'class_output_role_body_enabled', 'kind' => 'toggle'],
            ['name' => 'class_output_role_monospace_enabled', 'kind' => 'toggle'],
            ['name' => 'class_output_role_alias_interface_enabled', 'kind' => 'toggle'],
            ['name' => 'class_output_role_alias_ui_enabled', 'kind' => 'toggle'],
            ['name' => 'class_output_role_alias_code_enabled', 'kind' => 'toggle'],
            ['name' => 'class_output_category_sans_enabled', 'kind' => 'toggle'],
            ['name' => 'class_output_category_serif_enabled', 'kind' => 'toggle'],
            ['name' => 'class_output_category_mono_enabled', 'kind' => 'toggle'],
            ['name' => 'class_output_families_enabled', 'kind' => 'toggle'],
            ['name' => 'class_output_role_styles_enabled', 'kind' => 'toggle'],
            ['name' => 'per_variant_font_variables_enabled', 'kind' => 'toggle'],
            ['name' => 'minimal_output_preset_enabled', 'kind' => 'toggle'],
            ['name' => 'role_usage_font_weight_enabled', 'kind' => 'toggle'],
            ['name' => 'extended_variable_role_weight_vars_enabled', 'kind' => 'toggle'],
            ['name' => 'extended_variable_weight_tokens_enabled', 'kind' => 'toggle'],
            ['name' => 'extended_variable_role_aliases_enabled', 'kind' => 'toggle'],
            ['name' => 'extended_variable_category_sans_enabled', 'kind' => 'toggle'],
            ['name' => 'extended_variable_category_serif_enabled', 'kind' => 'toggle'],
            ['name' => 'extended_variable_category_mono_enabled', 'kind' => 'toggle'],
            ['name' => 'preload_primary_fonts', 'kind' => 'toggle'],
            ['name' => 'remote_connection_hints', 'kind' => 'toggle'],
            ['name' => 'block_editor_font_library_sync_enabled', 'kind' => 'toggle'],
            ['name' => 'etch_integration_enabled', 'kind' => 'toggle'],
            ['name' => 'bricks_integration_enabled', 'kind' => 'toggle'],
            ['name' => 'bricks_theme_styles_sync_enabled', 'kind' => 'toggle'],
            ['name' => 'bricks_disable_google_fonts_enabled', 'kind' => 'toggle'],
            ['name' => 'oxygen_integration_enabled', 'kind' => 'toggle'],
            ['name' => 'acss_font_role_sync_enabled', 'kind' => 'toggle'],
            ['name' => 'delete_uploaded_files_on_uninstall', 'kind' => 'toggle'],
            ['name' => 'show_activity_log', 'kind' => 'toggle'],
            ['name' => 'snapshot_retention_limit', 'kind' => 'text'],
            ['name' => 'site_transfer_export_retention_limit', 'kind' => 'text'],
            ['name' => 'training_wheels_off', 'kind' => 'toggle'],
            ['name' => 'variable_fonts_enabled', 'kind' => 'toggle'],
            ['name' => 'google_font_imports_enabled', 'kind' => 'toggle'],
            ['name' => 'bunny_font_imports_enabled', 'kind' => 'toggle'],
            ['name' => 'adobe_font_imports_enabled', 'kind' => 'toggle'],
            ['name' => 'local_font_uploads_enabled', 'kind' => 'toggle'],
            ['name' => 'custom_css_url_imports_enabled', 'kind' => 'toggle'],
            ['name' => 'monospace_role_enabled', 'kind' => 'toggle'],
            ['name' => 'admin_access_custom_enabled', 'kind' => 'toggle'],
            ['name' => 'admin_access_role_slugs', 'kind' => 'string_array'],
            ['name' => 'admin_access_user_ids', 'kind' => 'int_array'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(
            static fn (array $definition): string => (string) $definition['name'],
            self::definitions()
        );
    }

    /**
     * @param array<int, string> $kinds
     * @return array<int, string>
     */
    public static function namesForKinds(array $kinds): array
    {
        return array_values(
            array_map(
                static fn (array $definition): string => (string) $definition['name'],
                array_filter(
                    self::definitions(),
                    static fn (array $definition): bool => in_array($definition['kind'], $kinds, true)
                )
            )
        );
    }
}
