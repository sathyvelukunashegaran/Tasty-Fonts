<?php

declare(strict_types=1);

use TastyFonts\Admin\AdminPageContextBuilder;
use TastyFonts\Admin\AdminController;
use TastyFonts\Adobe\AdobeCssParser;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Fonts\FontFilenameParser;
use TastyFonts\Fonts\RuntimeAssetPlanner;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;

if (!class_exists('Automatic_CSS\\API')) {
    eval(<<<'PHP'
namespace Automatic_CSS;

class API
{
    public static function get_setting($setting_key)
    {
        global $automaticCssSettings;

        return is_array($automaticCssSettings) ? ($automaticCssSettings[$setting_key] ?? '') : '';
    }

    public static function update_settings($new_vars, $options = array(), $settings = null)
    {
        global $automaticCssSettings;

        $automaticCssSettings = is_array($automaticCssSettings) ? $automaticCssSettings : array();

        foreach ((array) $new_vars as $key => $value) {
            $automaticCssSettings[$key] = $value;
        }

        return $automaticCssSettings;
    }
}
PHP);
}

$tests['settings_repository_persists_adobe_project_state'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $settings->saveAdobeProject(' AbC-123 ', true);
    $saved = $settings->getSettings();

    assertSameValue(true, $saved['adobe_enabled'], 'Saving an Adobe project should persist the enabled flag.');
    assertSameValue('abc123', $saved['adobe_project_id'], 'Saving an Adobe project should normalize the project ID.');
    assertSameValue('unknown', $saved['adobe_project_status'], 'Saving a non-empty Adobe project should reset status to unknown before validation.');

    $settings->saveAdobeProjectStatus('valid', 'Adobe project ready.');
    $status = $settings->getAdobeProjectStatus();

    assertSameValue('valid', $status['state'], 'Adobe project status updates should persist the normalized state.');
    assertSameValue('Adobe project ready.', $status['message'], 'Adobe project status updates should persist the status message.');
    assertSameValue(true, $status['checked_at'] > 0, 'Adobe project status updates should record a validation timestamp.');

    $settings->clearAdobeProject();
    $cleared = $settings->getSettings();

    assertSameValue(false, $cleared['adobe_enabled'], 'Clearing an Adobe project should disable remote loading.');
    assertSameValue('', $cleared['adobe_project_id'], 'Clearing an Adobe project should remove the saved project ID.');
    assertSameValue('empty', $cleared['adobe_project_status'], 'Clearing an Adobe project should reset the status to empty.');
};

$tests['settings_repository_persists_google_api_key_data_in_dedicated_option'] = static function (): void {
    resetTestState();

    global $optionAutoload;
    global $optionStore;

    $settings = new SettingsRepository();
    $saved = $settings->saveSettings(['google_api_key' => '  live-key  ']);

    assertSameValue('live-key', $saved['google_api_key'], 'Saving a Google API key should still expose the trimmed key through getSettings/saveSettings.');
    assertSameValue(
        false,
        array_key_exists('google_api_key', (array) ($optionStore[SettingsRepository::OPTION_SETTINGS] ?? [])),
        'The main settings option should no longer persist the Google API key.'
    );
    assertSameValue(
        [
            'google_api_key' => 'live-key',
            'google_api_key_status' => 'unknown',
            'google_api_key_status_message' => '',
            'google_api_key_checked_at' => 0,
        ],
        $optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA] ?? null,
        'Google API key data should be stored in its dedicated option row.'
    );
    assertSameValue(
        false,
        $optionAutoload[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA] ?? null,
        'The dedicated Google API key option should be saved with autoload disabled.'
    );
};

$tests['settings_repository_migrates_legacy_google_api_key_data_when_saving_other_settings'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'preview_sentence' => 'Legacy preview',
        'google_api_key' => 'legacy-key',
        'google_api_key_status' => 'valid',
        'google_api_key_status_message' => 'Ready',
        'google_api_key_checked_at' => 123,
    ];

    $settings = new SettingsRepository();
    $saved = $settings->saveSettings(['preview_sentence' => 'Updated preview']);

    assertSameValue('legacy-key', $saved['google_api_key'], 'Saving unrelated settings should preserve the existing Google API key during migration.');
    assertSameValue(
        false,
        array_key_exists('google_api_key', (array) ($optionStore[SettingsRepository::OPTION_SETTINGS] ?? [])),
        'Migrated main settings should no longer keep Google API key fields in the shared settings blob.'
    );
    assertSameValue('Updated preview', (string) ($optionStore[SettingsRepository::OPTION_SETTINGS]['preview_sentence'] ?? ''), 'Unrelated settings should still save into the main settings option.');
    assertSameValue(
        [
            'google_api_key' => 'legacy-key',
            'google_api_key_status' => 'valid',
            'google_api_key_status_message' => 'Ready',
            'google_api_key_checked_at' => 123,
        ],
        $optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA] ?? null,
        'Saving unrelated settings should migrate legacy Google API key data into the dedicated option.'
    );
};

$tests['settings_repository_updates_google_key_status_without_rewriting_main_settings'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'preview_sentence' => 'Keep this',
    ];
    $optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA] = [
        'google_api_key' => 'live-key',
        'google_api_key_status' => 'unknown',
        'google_api_key_status_message' => '',
        'google_api_key_checked_at' => 0,
    ];

    $settings = new SettingsRepository();
    $settings->saveGoogleApiKeyStatus('valid', 'Ready');

    assertSameValue(
        ['preview_sentence' => 'Keep this'],
        $optionStore[SettingsRepository::OPTION_SETTINGS] ?? null,
        'Updating Google API key validation state should not rewrite the main settings option.'
    );
    assertSameValue(
        'valid',
        (string) ($optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA]['google_api_key_status'] ?? ''),
        'Updating Google API key validation state should only touch the dedicated option.'
    );
};

$tests['settings_repository_tracks_acss_font_sync_state'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $defaults = $settings->getSettings();

    assertSameValue(null, $defaults['acss_font_role_sync_enabled'], 'Automatic.css sync should start unconfigured so first-run detection can opt users in once.');
    assertSameValue(false, $defaults['acss_font_role_sync_applied'], 'Automatic.css sync should start unapplied.');

    $saved = $settings->saveSettings(['acss_font_role_sync_enabled' => '1']);

    assertSameValue(true, $saved['acss_font_role_sync_enabled'], 'Saving the Automatic.css sync toggle should persist an explicit enabled state.');

    $saved = $settings->saveAcssFontRoleSyncState(true, true, 'Inter, sans-serif', 'system-ui, sans-serif');

    assertSameValue(true, $saved['acss_font_role_sync_applied'], 'Automatic.css sync state should record when the managed ACSS values are currently applied.');
    assertSameValue('Inter, sans-serif', $saved['acss_font_role_sync_previous_heading_font_family'], 'Automatic.css sync state should preserve the previous heading font-family value for later restore.');
    assertSameValue('system-ui, sans-serif', $saved['acss_font_role_sync_previous_text_font_family'], 'Automatic.css sync state should preserve the previous text font-family value for later restore.');
};

$tests['settings_repository_reuses_request_scoped_settings_until_a_write_invalidates_the_cache'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'preview_sentence' => 'Original preview',
        'font_display' => 'swap',
    ];

    $settings = new SettingsRepository();
    $first = $settings->getSettings();

    $optionStore[SettingsRepository::OPTION_SETTINGS]['preview_sentence'] = 'Changed underneath cache';
    $second = $settings->getSettings();

    assertSameValue('Original preview', $first['preview_sentence'], 'Initial settings reads should normalize the stored option value.');
    assertSameValue('Original preview', $second['preview_sentence'], 'Subsequent settings reads in the same request should reuse the normalized cache.');

    $settings->saveSettings(['preview_sentence' => 'Saved preview']);
    $afterSave = $settings->getSettings();

    assertSameValue('Saved preview', $afterSave['preview_sentence'], 'Settings writes should refresh the request-scoped cache.');
};

$tests['settings_repository_persists_delete_files_on_uninstall_preference'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $settings->saveSettings(['delete_uploaded_files_on_uninstall' => '1']);
    $saved = $settings->getSettings();

    assertSameValue(true, !empty($saved['delete_uploaded_files_on_uninstall']), 'Settings should persist the uninstall file cleanup preference when enabled.');

    $settings->saveSettings(['delete_uploaded_files_on_uninstall' => '0']);
    $saved = $settings->getSettings();

    assertSameValue(false, !empty($saved['delete_uploaded_files_on_uninstall']), 'Settings should persist the uninstall file cleanup preference when disabled.');
};

$tests['settings_repository_persists_preload_primary_fonts_preference'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $settings->saveSettings(['preload_primary_fonts' => '1']);
    $saved = $settings->getSettings();

    assertSameValue(true, !empty($saved['preload_primary_fonts']), 'Settings should persist the primary font preload preference when enabled.');

    $settings->saveSettings(['preload_primary_fonts' => '0']);
    $saved = $settings->getSettings();

    assertSameValue(false, !empty($saved['preload_primary_fonts']), 'Settings should persist the primary font preload preference when disabled.');
};

$tests['developer_tools_reset_plugin_settings_preserves_library_and_files'] = static function (): void {
    resetTestState();

    global $actionCalls;
    global $optionStore;

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();

    $filePath = (string) $services['storage']->pathForRelativePath('upload/inter/inter-400-normal.woff2');
    $services['storage']->writeAbsoluteFile($filePath, 'font-data');
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-upload',
            'label' => 'Local Upload',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'files' => ['woff2' => 'upload/inter/inter-400-normal.woff2'],
                    'paths' => ['woff2' => 'upload/inter/inter-400-normal.woff2'],
                ],
            ],
        ],
        'published',
        true
    );
    $services['settings']->saveSettings([
        'auto_apply_roles' => '1',
        'preview_sentence' => 'Changed preview',
        'font_display' => 'swap',
        'training_wheels_off' => '1',
        'monospace_role_enabled' => '1',
    ]);
    $services['settings']->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'serif',
            'body_fallback' => 'sans-serif',
        ],
        ['Inter']
    );
    $services['settings']->saveAdobeProject('abc123', true);
    $services['settings']->saveFamilyFallback('Inter', 'serif');
    $services['settings']->saveFamilyFontDisplay('Inter', 'fallback');
    update_option(AdminController::LOCAL_ENV_NOTICE_OPTION, [1 => ['hidden_until' => 123456, 'dismissed_forever' => true]], false);
    update_option(
        SettingsRepository::OPTION_GOOGLE_API_KEY_DATA,
        [
            'google_api_key' => 'live-key',
            'google_api_key_status' => 'valid',
            'google_api_key_status_message' => 'Ready',
            'google_api_key_checked_at' => 123,
        ],
        false
    );

    $result = $services['developer_tools']->resetPluginSettings();

    assertFalseValue(is_wp_error($result), 'Reset plugin settings should succeed.');
    assertSameValue(true, file_exists($filePath), 'Reset plugin settings should preserve managed font files.');
    assertSameValue(true, $services['imports']->getFamily('inter') !== null, 'Reset plugin settings should preserve the saved font library.');
    assertSameValue('optional', (string) ($result['font_display'] ?? ''), 'Reset plugin settings should restore the default font-display.');
    assertSameValue(false, !empty($result['training_wheels_off']), 'Reset plugin settings should restore behavior toggles to their defaults.');
    assertSameValue('', (string) ($result['google_api_key'] ?? ''), 'Reset plugin settings should clear the saved Google API key.');
    assertSameValue(false, array_key_exists(AdminController::LOCAL_ENV_NOTICE_OPTION, $optionStore), 'Reset plugin settings should clear suppressed notice preferences.');
    assertSameValue(1, did_action('tasty_fonts_before_reset_settings'), 'Reset plugin settings should emit a before hook.');
    assertSameValue(1, did_action('tasty_fonts_after_reset_settings'), 'Reset plugin settings should emit an after hook.');
    assertSameValue(true, isset($actionCalls['tasty_fonts_after_reset_settings'][0][0]), 'Reset plugin settings should pass the restored settings into the after hook.');
};

$tests['developer_tools_wipe_managed_font_library_rebuilds_empty_storage'] = static function (): void {
    resetTestState();

    global $actionCalls;
    global $remoteGetResponses;
    global $remoteRequestResponses;

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();

    $filePath = (string) $services['storage']->pathForRelativePath('upload/inter/inter-400-normal.woff2');
    $services['storage']->writeAbsoluteFile($filePath, 'font-data');
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-upload',
            'label' => 'Local Upload',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'files' => ['woff2' => 'upload/inter/inter-400-normal.woff2'],
                    'paths' => ['woff2' => 'upload/inter/inter-400-normal.woff2'],
                ],
            ],
        ],
        'published',
        true
    );
    $services['settings']->saveSettings([
        'minify_css_output' => '0',
        'block_editor_font_library_sync_enabled' => '0',
        'adobe_enabled' => '1',
    ]);
    $services['settings']->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'serif',
            'body_fallback' => 'sans-serif',
        ],
        ['Inter']
    );
    $services['settings']->saveFamilyFallback('Inter', 'serif');
    $services['settings']->saveFamilyFontDisplay('Inter', 'swap');
    $services['settings']->saveAdobeProject('abc123', true);

    $baseUrl = 'https://example.test/wp-json/wp/v2/font-families';
    $findUrl = $baseUrl . '?slug=tasty-fonts-inter&context=edit';
    $deleteUrl = $baseUrl . '/44?force=true';
    $remoteGetResponses[$findUrl] = [
        'response' => ['code' => 200],
        'body' => wp_json_encode([['id' => 44, 'font_faces' => []]]),
    ];
    $remoteRequestResponses['DELETE ' . $deleteUrl] = [
        'response' => ['code' => 200],
        'body' => wp_json_encode(['deleted' => true]),
    ];

    $result = $services['developer_tools']->wipeManagedFontLibrary();

    assertFalseValue(is_wp_error($result), 'Wiping the managed library should succeed.');
    assertSameValue([], $services['imports']->allFamilies(), 'Wiping the managed library should clear saved family records.');
    assertSameValue(false, file_exists($filePath), 'Wiping the managed library should remove managed font files.');
    assertSameValue(false, !empty($result['auto_apply_roles']), 'Wiping the managed library should disable sitewide role application.');
    assertSameValue([], (array) ($result['family_fallbacks'] ?? []), 'Wiping the managed library should clear per-family fallback overrides.');
    assertSameValue([], (array) ($result['family_font_displays'] ?? []), 'Wiping the managed library should clear per-family font-display overrides.');
    assertSameValue(false, !empty($result['adobe_enabled']), 'Wiping the managed library should clear Adobe project state.');
    assertSameValue(false, !empty($result['minify_css_output']), 'Wiping the managed library should preserve unrelated output settings.');
    assertSameValue(true, is_dir((string) $services['storage']->getRoot()), 'Wiping the managed library should recreate the storage root.');
    assertSameValue(true, file_exists((string) $services['storage']->pathForRelativePath('index.php')), 'Wiping the managed library should restore the root index stub.');
    assertSameValue(true, file_exists((string) $services['storage']->pathForRelativePath('google/index.php')), 'Wiping the managed library should restore the Google storage stub.');
    assertSameValue(true, file_exists((string) $services['storage']->pathForRelativePath('bunny/index.php')), 'Wiping the managed library should restore the Bunny storage stub.');
    assertSameValue(true, file_exists((string) $services['storage']->pathForRelativePath('upload/index.php')), 'Wiping the managed library should restore the uploads storage stub.');
    assertSameValue(true, file_exists((string) $services['storage']->pathForRelativePath('adobe/index.php')), 'Wiping the managed library should restore the Adobe storage stub.');
    assertSameValue(true, file_exists((string) $services['storage']->pathForRelativePath('.generated/index.php')), 'Wiping the managed library should restore the generated-assets storage stub.');
    assertSameValue(1, did_action('tasty_fonts_before_wipe_font_library'), 'Wiping the managed library should emit a before hook.');
    assertSameValue(1, did_action('tasty_fonts_after_wipe_font_library'), 'Wiping the managed library should emit an after hook.');
    assertSameValue(true, isset($actionCalls['tasty_fonts_after_wipe_font_library'][0][0]), 'Wiping the managed library should pass the restored settings into the after hook.');
};

$tests['developer_tools_clear_plugin_caches_and_regenerate_assets'] = static function (): void {
    resetTestState();

    global $clearedScheduledHooks;
    global $transientDeleted;
    global $transientStore;

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();
    $services['settings']->saveAdobeProject('abc123', true);
    $transientStore = [
        CatalogService::TRANSIENT_CATALOG => ['cached'],
        AssetService::TRANSIENT_CSS => 'body{}',
        AssetService::TRANSIENT_HASH => 'hash',
        AssetService::TRANSIENT_REGENERATE_CSS_QUEUED => true,
        GoogleFontsClient::TRANSIENT_CATALOG => ['Inter'],
        GoogleFontsClient::TRANSIENT_METADATA => ['inter' => ['family' => 'Inter', 'axes' => []]],
        BunnyFontsClient::TRANSIENT_CATALOG => ['Inter'],
        'tasty_fonts_github_release_manifest_v1' => ['latest_for_channel' => ['stable' => ['version' => '1.0.0']]],
        'tasty_fonts_github_release_version_v1' => '1.0.0',
        BunnyFontsClient::TRANSIENT_FAMILY_PREFIX . 'abc' => ['family' => 'Inter'],
        AdminController::SEARCH_CACHE_TRANSIENT_PREFIX . 'google_inter' => ['Inter'],
        AdminController::SEARCH_COOLDOWN_TRANSIENT_PREFIX . 'google_inter' => 1,
        AdobeProjectClient::TRANSIENT_PREFIX . md5('abc123') => ['families' => ['Inter']],
    ];

    $result = $services['developer_tools']->clearPluginCachesAndRegenerateAssets();

    assertTrueValue($result, 'Clearing plugin caches should report success.');
    assertSameValue(true, in_array(CatalogService::TRANSIENT_CATALOG, $transientDeleted, true), 'Clearing plugin caches should invalidate the catalog transient.');
    assertSameValue(true, in_array(AssetService::TRANSIENT_CSS, $transientDeleted, true), 'Clearing plugin caches should invalidate the CSS transient.');
    assertSameValue(true, in_array(AssetService::TRANSIENT_HASH, $transientDeleted, true), 'Clearing plugin caches should invalidate the CSS hash transient.');
    assertSameValue(true, in_array(GoogleFontsClient::TRANSIENT_CATALOG, $transientDeleted, true), 'Clearing plugin caches should invalidate the Google catalog transient.');
    assertSameValue(true, in_array(GoogleFontsClient::TRANSIENT_METADATA, $transientDeleted, true), 'Clearing plugin caches should invalidate the Google metadata transient.');
    assertSameValue(true, in_array(BunnyFontsClient::TRANSIENT_CATALOG, $transientDeleted, true), 'Clearing plugin caches should invalidate the Bunny catalog transient.');
    assertSameValue(true, in_array(AdobeProjectClient::TRANSIENT_PREFIX . md5('abc123'), $transientDeleted, true), 'Clearing plugin caches should invalidate the saved Adobe project transient.');
    assertSameValue(true, in_array(AssetService::ACTION_REGENERATE_CSS, $clearedScheduledHooks, true), 'Clearing plugin caches should clear queued CSS regeneration hooks.');
    assertSameValue(1, did_action('tasty_fonts_before_clear_plugin_caches'), 'Clearing plugin caches should emit a before hook.');
    assertSameValue(1, did_action('tasty_fonts_after_clear_plugin_caches'), 'Clearing plugin caches should emit an after hook.');
};

$tests['developer_tools_regenerate_css_rebuilds_the_stylesheet_without_clearing_external_caches'] = static function (): void {
    resetTestState();

    global $clearedScheduledHooks;
    global $transientDeleted;
    global $transientStore;

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-upload',
            'label' => 'Local Upload',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'files' => ['woff2' => 'upload/inter/inter-400-normal.woff2'],
                    'paths' => ['woff2' => 'upload/inter/inter-400-normal.woff2'],
                ],
            ],
        ],
        'published',
        true
    );
    $services['settings']->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'serif',
            'body_fallback' => 'sans-serif',
        ],
        ['Inter']
    );
    $services['settings']->saveSettings([
        'auto_apply_roles' => '1',
        'font_display' => 'optional',
    ]);
    $services['assets']->refreshGeneratedAssets(true, false);
    $services['assets']->ensureGeneratedCssFile(false);

    $generatedPath = (string) $services['storage']->getGeneratedCssPath();
    $beforeCss = is_readable($generatedPath) ? (string) file_get_contents($generatedPath) : '';

    $services['settings']->saveSettings(['font_display' => 'swap']);
    $transientStore[GoogleFontsClient::TRANSIENT_CATALOG] = ['Inter'];
    $transientStore[BunnyFontsClient::TRANSIENT_CATALOG] = ['Inter'];

    $result = $services['developer_tools']->regenerateCss();
    $afterCss = is_readable($generatedPath) ? (string) file_get_contents($generatedPath) : '';

    assertTrueValue($result, 'Regenerating CSS should report success.');
    assertSameValue(true, in_array(AssetService::ACTION_REGENERATE_CSS, $clearedScheduledHooks, true), 'Regenerating CSS should clear queued CSS regeneration hooks before rebuilding.');
    assertSameValue(false, in_array(GoogleFontsClient::TRANSIENT_CATALOG, $transientDeleted, true), 'Regenerating CSS should not clear the Google catalog cache.');
    assertSameValue(false, in_array(BunnyFontsClient::TRANSIENT_CATALOG, $transientDeleted, true), 'Regenerating CSS should not clear the Bunny catalog cache.');
    assertContainsValue('font-display:swap', $afterCss, 'Regenerating CSS should rebuild the generated stylesheet using the current settings.');
    assertTrueValue($beforeCss !== $afterCss, 'Regenerating CSS should rewrite the generated stylesheet when the CSS payload changes.');
    assertSameValue(1, did_action('tasty_fonts_before_regenerate_css'), 'Regenerating CSS should emit a before hook.');
    assertSameValue(1, did_action('tasty_fonts_after_regenerate_css'), 'Regenerating CSS should emit an after hook.');
};

$tests['developer_tools_reset_integration_detection_state_and_suppressed_notices'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'block_editor_font_library_sync_enabled' => '1',
        'bricks_integration_enabled' => '1',
        'oxygen_integration_enabled' => '0',
        'acss_font_role_sync_enabled' => '1',
    ]);
    $services['settings']->saveAcssFontRoleSyncState(true, true, 'Inter, sans-serif', 'system-ui, sans-serif');
    $optionStore[AdminController::LOCAL_ENV_NOTICE_OPTION] = [
        1 => ['hidden_until' => 123456, 'dismissed_forever' => true],
    ];

    $settings = $services['developer_tools']->resetIntegrationDetectionState();

    $storedSettings = is_array($optionStore[SettingsRepository::OPTION_SETTINGS] ?? null)
        ? $optionStore[SettingsRepository::OPTION_SETTINGS]
        : [];

    assertSameValue(
        true,
        !array_key_exists('block_editor_font_library_sync_enabled', $storedSettings)
            || $storedSettings['block_editor_font_library_sync_enabled'] === null,
        'Integration reset should clear the stored block editor sync preference back to an unconfigured state.'
    );
    assertSameValue(null, $settings['bricks_integration_enabled'], 'Integration reset should clear Bricks detection state.');
    assertSameValue(null, $settings['oxygen_integration_enabled'], 'Integration reset should clear Oxygen detection state.');
    assertSameValue(null, $settings['acss_font_role_sync_enabled'], 'Integration reset should clear Automatic.css detection state.');
    assertSameValue(false, !empty($settings['acss_font_role_sync_applied']), 'Integration reset should clear the applied Automatic.css sync flag.');
    assertSameValue('', (string) ($settings['acss_font_role_sync_previous_heading_font_family'] ?? ''), 'Integration reset should clear Automatic.css heading backups.');
    assertSameValue('', (string) ($settings['acss_font_role_sync_previous_text_font_family'] ?? ''), 'Integration reset should clear Automatic.css body backups.');
    assertSameValue(1, did_action('tasty_fonts_before_reset_integration_detection'), 'Integration reset should emit a before hook.');
    assertSameValue(1, did_action('tasty_fonts_after_reset_integration_detection'), 'Integration reset should emit an after hook.');

    $services['developer_tools']->resetSuppressedNotices();

    assertSameValue(false, array_key_exists(AdminController::LOCAL_ENV_NOTICE_OPTION, $optionStore), 'Reset suppressed notices should clear saved notice preferences.');
    assertSameValue(1, did_action('tasty_fonts_before_reset_suppressed_notices'), 'Reset suppressed notices should emit a before hook.');
    assertSameValue(1, did_action('tasty_fonts_after_reset_suppressed_notices'), 'Reset suppressed notices should emit an after hook.');
};

$tests['settings_repository_defaults_and_persists_class_output_settings'] = static function (): void {
    resetTestState();

    global $optionStore;

    $settings = new SettingsRepository();
    $defaults = $settings->getSettings();

    assertSameValue(false, $defaults['class_output_enabled'], 'Class output should default to off for new installs.');
    assertSameValue(true, $defaults['class_output_role_heading_enabled'], 'Granular class output flags should default to enabled for new installs.');
    assertSameValue(true, $defaults['class_output_role_alias_interface_enabled'], 'Role alias class flags should default to enabled for new installs.');
    assertSameValue(true, $defaults['class_output_category_sans_enabled'], 'Category class flags should default to enabled for new installs.');
    assertSameValue(true, $defaults['class_output_families_enabled'], 'Family class flags should default to enabled for new installs.');

    $saved = $settings->saveSettings([
        'class_output_enabled' => '1',
        'class_output_role_heading_enabled' => '1',
        'class_output_role_body_enabled' => '0',
        'class_output_role_monospace_enabled' => '1',
        'class_output_role_alias_interface_enabled' => '1',
        'class_output_role_alias_ui_enabled' => '0',
        'class_output_role_alias_code_enabled' => '1',
        'class_output_category_sans_enabled' => '0',
        'class_output_category_serif_enabled' => '1',
        'class_output_category_mono_enabled' => '0',
        'class_output_families_enabled' => '1',
    ]);

    assertSameValue(true, $saved['class_output_enabled'], 'Settings should persist enabled class output.');
    assertSameValue(false, $saved['class_output_role_body_enabled'], 'Settings should persist disabled role-body class output.');
    assertSameValue(false, $saved['class_output_role_alias_ui_enabled'], 'Settings should persist disabled UI alias class output.');
    assertSameValue(false, $saved['class_output_category_sans_enabled'], 'Settings should persist disabled sans category class output.');
    assertSameValue(true, $saved['class_output_families_enabled'], 'Settings should persist enabled family classes.');
    assertSameValue(
        false,
        array_key_exists('class_output_mode', (array) ($optionStore[SettingsRepository::OPTION_SETTINGS] ?? [])),
        'Saving class output settings should not persist the legacy class_output_mode key.'
    );
};

$tests['settings_repository_migrates_legacy_class_output_mode_on_read'] = static function (): void {
    resetTestState();

    global $optionStore;

    $settings = new SettingsRepository();

    $optionStore[SettingsRepository::OPTION_SETTINGS] = ['class_output_mode' => 'off'];
    assertSameValue(false, $settings->getSettings()['class_output_enabled'], 'Legacy off mode should normalize to disabled class output.');

    resetTestState();
    $optionStore[SettingsRepository::OPTION_SETTINGS] = ['class_output_mode' => 'roles'];
    $settings = new SettingsRepository();
    $roles = $settings->getSettings();
    assertSameValue(true, $roles['class_output_enabled'], 'Legacy roles mode should enable class output.');
    assertSameValue(false, $roles['class_output_families_enabled'], 'Legacy roles mode should disable family classes.');

    resetTestState();
    $optionStore[SettingsRepository::OPTION_SETTINGS] = ['class_output_mode' => 'families'];
    $settings = new SettingsRepository();
    $families = $settings->getSettings();
    assertSameValue(true, $families['class_output_enabled'], 'Legacy families mode should enable class output.');
    assertSameValue(false, $families['class_output_role_heading_enabled'], 'Legacy families mode should disable role classes.');
    assertSameValue(true, $families['class_output_families_enabled'], 'Legacy families mode should keep family classes on.');

    resetTestState();
    $optionStore[SettingsRepository::OPTION_SETTINGS] = ['class_output_mode' => 'all'];
    $settings = new SettingsRepository();
    $all = $settings->getSettings();
    assertSameValue(true, $all['class_output_enabled'], 'Legacy all mode should enable class output.');
    assertSameValue(true, $all['class_output_role_alias_ui_enabled'], 'Legacy all mode should keep alias classes enabled.');
    assertSameValue(true, $all['class_output_category_serif_enabled'], 'Legacy all mode should keep category classes enabled.');
    assertSameValue(true, $all['class_output_families_enabled'], 'Legacy all mode should keep family classes enabled.');
};

$tests['settings_repository_prefers_new_class_output_fields_over_legacy_mode'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'class_output_mode' => 'off',
        'class_output_enabled' => true,
        'class_output_role_heading_enabled' => false,
        'class_output_role_body_enabled' => true,
        'class_output_role_monospace_enabled' => true,
        'class_output_role_alias_interface_enabled' => true,
        'class_output_role_alias_ui_enabled' => true,
        'class_output_role_alias_code_enabled' => true,
        'class_output_category_sans_enabled' => true,
        'class_output_category_serif_enabled' => false,
        'class_output_category_mono_enabled' => true,
        'class_output_families_enabled' => false,
    ];

    $settings = new SettingsRepository();
    $normalized = $settings->getSettings();

    assertSameValue(true, $normalized['class_output_enabled'], 'Explicit class output booleans should take precedence over the legacy mode.');
    assertSameValue(false, $normalized['class_output_role_heading_enabled'], 'Explicit class output booleans should not be overridden by the legacy mode.');
    assertSameValue(false, $normalized['class_output_category_serif_enabled'], 'Explicit category class flags should win over the legacy mode.');
    assertSameValue(false, $normalized['class_output_families_enabled'], 'Explicit family class flags should win over the legacy mode.');
};

$tests['settings_repository_enables_per_variant_font_variables_by_default_and_persists_changes'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue(
        true,
        !empty($settings->getSettings()['per_variant_font_variables_enabled']),
        'Per-variant font variables should default to enabled for new installs.'
    );
    assertSameValue(true, !empty($settings->getSettings()['minimal_output_preset_enabled']), 'Minimal output preset should default to enabled for new installs.');
    assertSameValue('minimal', (string) ($settings->getSettings()['output_quick_mode_preference'] ?? ''), 'New installs should default the output quick-mode preference to minimal.');
    assertSameValue(false, !empty($settings->getSettings()['role_usage_font_weight_enabled']), 'Role usage font weights should default to disabled.');
    assertSameValue(true, !empty($settings->getSettings()['extended_variable_weight_tokens_enabled']), 'Extended weight tokens should default to enabled.');
    assertSameValue(true, !empty($settings->getSettings()['extended_variable_role_aliases_enabled']), 'Extended role aliases should default to enabled.');
    assertSameValue(true, !empty($settings->getSettings()['extended_variable_category_sans_enabled']), 'Extended sans category alias should default to enabled.');
    assertSameValue(true, !empty($settings->getSettings()['extended_variable_category_serif_enabled']), 'Extended serif category alias should default to enabled.');
    assertSameValue(true, !empty($settings->getSettings()['extended_variable_category_mono_enabled']), 'Extended mono category alias should default to enabled.');

    $settings->saveSettings(['per_variant_font_variables_enabled' => '0']);
    assertSameValue(
        false,
        !empty($settings->getSettings()['per_variant_font_variables_enabled']),
        'Settings should persist disabled per-variant font variable output.'
    );

    $settings->saveSettings(['per_variant_font_variables_enabled' => '1']);
    assertSameValue(
        true,
        !empty($settings->getSettings()['per_variant_font_variables_enabled']),
        'Settings should persist enabled per-variant font variable output.'
    );

    $settings->saveSettings(['role_usage_font_weight_enabled' => '1']);
    assertSameValue(
        true,
        !empty($settings->getSettings()['role_usage_font_weight_enabled']),
        'Settings should persist enabled role usage font-weight output.'
    );

    $settings->saveSettings([
        'role_usage_font_weight_enabled' => '0',
        'minimal_output_preset_enabled' => '1',
        'extended_variable_weight_tokens_enabled' => '0',
        'extended_variable_role_aliases_enabled' => '0',
        'extended_variable_category_sans_enabled' => '0',
        'extended_variable_category_serif_enabled' => '0',
        'extended_variable_category_mono_enabled' => '0',
    ]);
    $saved = $settings->getSettings();

    assertSameValue(true, $saved['minimal_output_preset_enabled'], 'Settings should persist the minimal output preset flag.');
    assertSameValue(false, $saved['class_output_enabled'], 'Minimal output preset should suppress class output.');
    assertSameValue(true, $saved['per_variant_font_variables_enabled'], 'Minimal output preset should keep variable output enabled.');
    assertSameValue(false, $saved['role_usage_font_weight_enabled'], 'Settings should persist disabled role usage font-weight output.');
    assertSameValue(false, $saved['extended_variable_weight_tokens_enabled'], 'Settings should persist disabled extended weight tokens.');
    assertSameValue(false, $saved['extended_variable_role_aliases_enabled'], 'Settings should persist disabled extended role aliases.');
    assertSameValue(false, $saved['extended_variable_category_sans_enabled'], 'Settings should persist disabled extended sans aliases.');
    assertSameValue(false, $saved['extended_variable_category_serif_enabled'], 'Settings should persist disabled extended serif aliases.');
    assertSameValue(false, $saved['extended_variable_category_mono_enabled'], 'Settings should persist disabled extended mono aliases.');
    assertSameValue('minimal', (string) ($saved['output_quick_mode_preference'] ?? ''), 'Saving the minimal preset should persist the matching quick-mode preference.');
};

$tests['settings_repository_preserves_legacy_output_modes_when_minimal_flag_is_missing'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'class_output_enabled' => true,
        'class_output_families_enabled' => true,
        'per_variant_font_variables_enabled' => true,
    ];

    $settings = new SettingsRepository();
    $normalized = $settings->getSettings();

    assertSameValue(false, !empty($normalized['minimal_output_preset_enabled']), 'Legacy saved output settings should not silently opt into the new minimal preset.');
    assertSameValue(true, !empty($normalized['class_output_enabled']), 'Legacy saved class output settings should be preserved when the minimal flag is missing.');
    assertSameValue(true, !empty($normalized['per_variant_font_variables_enabled']), 'Legacy saved variable output settings should be preserved when the minimal flag is missing.');
    assertSameValue('custom', (string) ($normalized['output_quick_mode_preference'] ?? ''), 'Legacy mixed output settings should derive a custom quick-mode preference when no explicit preference was stored.');
};

$tests['settings_repository_keeps_custom_output_quick_mode_sticky_and_coerces_stale_presets'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $saved = $settings->saveSettings([
        'minimal_output_preset_enabled' => '0',
        'output_quick_mode_preference' => 'custom',
        'class_output_enabled' => '0',
        'per_variant_font_variables_enabled' => '1',
        'role_usage_font_weight_enabled' => '0',
        'extended_variable_weight_tokens_enabled' => '1',
        'extended_variable_role_aliases_enabled' => '1',
        'extended_variable_category_sans_enabled' => '1',
        'extended_variable_category_serif_enabled' => '1',
        'extended_variable_category_mono_enabled' => '1',
    ]);

    assertSameValue('custom', (string) ($saved['output_quick_mode_preference'] ?? ''), 'An explicit custom quick-mode preference should remain sticky even when the saved booleans match variables-only.');

    $saved = $settings->saveSettings([
        'output_quick_mode_preference' => 'variables',
        'extended_variable_category_serif_enabled' => '0',
    ]);

    assertSameValue('custom', (string) ($saved['output_quick_mode_preference'] ?? ''), 'Stale non-custom quick-mode preferences should normalize to custom when the saved booleans no longer match the preset shape.');
};

$tests['settings_repository_persists_variables_and_classes_quick_modes_when_settings_match_presets'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $saved = $settings->saveSettings([
        'minimal_output_preset_enabled' => '0',
        'output_quick_mode_preference' => 'variables',
        'class_output_enabled' => '0',
        'per_variant_font_variables_enabled' => '1',
        'role_usage_font_weight_enabled' => '0',
        'extended_variable_weight_tokens_enabled' => '1',
        'extended_variable_role_aliases_enabled' => '1',
        'extended_variable_category_sans_enabled' => '1',
        'extended_variable_category_serif_enabled' => '1',
        'extended_variable_category_mono_enabled' => '1',
    ]);

    assertSameValue('variables', (string) ($saved['output_quick_mode_preference'] ?? ''), 'Variables-only should remain selected when the saved booleans still match the preset.');
    assertSameValue(false, !empty($saved['class_output_enabled']), 'Variables-only should keep class output disabled.');
    assertSameValue(true, !empty($saved['per_variant_font_variables_enabled']), 'Variables-only should keep variable output enabled.');
    assertSameValue(false, !empty($saved['role_usage_font_weight_enabled']), 'Variables-only should keep role font-weight output disabled.');

    $saved = $settings->saveSettings([
        'minimal_output_preset_enabled' => '0',
        'output_quick_mode_preference' => 'classes',
        'class_output_enabled' => '1',
        'class_output_role_heading_enabled' => '1',
        'class_output_role_body_enabled' => '1',
        'class_output_role_monospace_enabled' => '1',
        'class_output_role_alias_interface_enabled' => '1',
        'class_output_role_alias_ui_enabled' => '1',
        'class_output_role_alias_code_enabled' => '1',
        'class_output_category_sans_enabled' => '1',
        'class_output_category_serif_enabled' => '1',
        'class_output_category_mono_enabled' => '1',
        'class_output_families_enabled' => '1',
        'per_variant_font_variables_enabled' => '0',
        'role_usage_font_weight_enabled' => '0',
    ]);

    assertSameValue('classes', (string) ($saved['output_quick_mode_preference'] ?? ''), 'Classes-only should remain selected when the saved booleans still match the preset.');
    assertSameValue(true, !empty($saved['class_output_enabled']), 'Classes-only should keep class output enabled.');
    assertSameValue(false, !empty($saved['per_variant_font_variables_enabled']), 'Classes-only should keep variable output disabled.');
    assertSameValue(false, !empty($saved['role_usage_font_weight_enabled']), 'Classes-only should keep role font-weight output disabled.');
};

$tests['settings_repository_defaults_block_editor_font_library_sync_on_by_default'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue(
        true,
        !empty($settings->getSettings()['block_editor_font_library_sync_enabled']),
        'New installs should default Block Editor Font Library sync to on until the user turns it off explicitly.'
    );
};

$tests['settings_repository_persists_block_editor_font_library_sync_preference'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $settings->saveSettings(['block_editor_font_library_sync_enabled' => '1']);
    $saved = $settings->getSettings();

    assertSameValue(true, !empty($saved['block_editor_font_library_sync_enabled']), 'Settings should persist the Block Editor Font Library sync preference when enabled.');

    $settings->saveSettings(['block_editor_font_library_sync_enabled' => '0']);
    $saved = $settings->getSettings();

    assertSameValue(false, !empty($saved['block_editor_font_library_sync_enabled']), 'Settings should persist the Block Editor Font Library sync preference when disabled.');
};

$tests['settings_repository_persists_training_wheels_off_preference'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $settings->saveSettings(['training_wheels_off' => '1']);
    $saved = $settings->getSettings();

    assertSameValue(true, !empty($saved['training_wheels_off']), 'Settings should persist the training-wheels-off preference when enabled.');

    $settings->saveSettings(['training_wheels_off' => '0']);
    $saved = $settings->getSettings();

    assertSameValue(false, !empty($saved['training_wheels_off']), 'Settings should persist the training-wheels-off preference when disabled.');
};

$tests['settings_repository_defaults_update_channel_to_stable_and_normalizes_invalid_values'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue(SettingsRepository::UPDATE_CHANNEL_STABLE, $settings->getUpdateChannel(), 'New installs should default to the stable update channel.');

    $settings->saveSettings(['update_channel' => SettingsRepository::UPDATE_CHANNEL_NIGHTLY]);
    assertSameValue(SettingsRepository::UPDATE_CHANNEL_NIGHTLY, $settings->getUpdateChannel(), 'Settings should persist supported update channels.');

    $settings->saveSettings(['update_channel' => 'unsupported']);
    assertSameValue(SettingsRepository::UPDATE_CHANNEL_STABLE, $settings->getUpdateChannel(), 'Invalid update channels should normalize back to stable.');
};

$tests['settings_repository_defaults_font_display_to_optional_and_normalizes_invalid_values'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue('optional', $settings->getSettings()['font_display'], 'Font display should default to optional for new installs.');

    $settings->saveSettings(['font_display' => 'block']);
    assertSameValue('block', $settings->getSettings()['font_display'], 'Settings should persist supported font-display values.');

    $settings->saveSettings(['font_display' => 'unsupported-value']);
    assertSameValue('optional', $settings->getSettings()['font_display'], 'Invalid saved font-display values should normalize back to optional.');
};

$tests['settings_repository_defaults_unicode_range_mode_and_normalizes_custom_values'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue(FontUtils::UNICODE_RANGE_MODE_OFF, $settings->getSettings()['unicode_range_mode'], 'Unicode-range output should default to off for new installs.');
    assertSameValue('', $settings->getSettings()['unicode_range_custom_value'], 'New installs should start with an empty custom unicode-range draft.');

    $settings->saveSettings([
        'unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_CUSTOM,
        'unicode_range_custom_value' => 'u+0000-00ff, u+0100-024f',
    ]);

    assertSameValue(FontUtils::UNICODE_RANGE_MODE_CUSTOM, $settings->getSettings()['unicode_range_mode'], 'Settings should persist supported unicode-range output modes.');
    assertSameValue('U+0000-00FF,U+0100-024F', $settings->getSettings()['unicode_range_custom_value'], 'Settings should normalize saved custom unicode-range values.');

    $settings->saveSettings(['unicode_range_mode' => 'unsupported']);
    assertSameValue(FontUtils::UNICODE_RANGE_MODE_OFF, $settings->getSettings()['unicode_range_mode'], 'Invalid saved unicode-range modes should normalize back to off.');
};

$tests['settings_repository_persists_family_font_display_overrides_and_unsets_inherit'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue([], $settings->getSettings()['family_font_displays'], 'Per-family font-display overrides should default to an empty map.');

    $settings->saveFamilyFontDisplay('Inter', 'swap');
    assertSameValue('swap', $settings->getFamilyFontDisplay('Inter'), 'Family font-display overrides should persist supported values.');

    $settings->saveFamilyFontDisplay('Lora', 'unsupported-value');
    assertSameValue('', $settings->getFamilyFontDisplay('Lora'), 'Unsupported family font-display values should be ignored instead of being persisted.');

    $settings->saveFamilyFontDisplay('Inter', 'inherit');
    assertSameValue('', $settings->getFamilyFontDisplay('Inter'), 'Saving inherit should remove the stored family font-display override.');
    assertSameValue([], $settings->getSettings()['family_font_displays'], 'Removing the only family font-display override should leave the stored map empty.');
};

$tests['settings_repository_keeps_boolean_output_settings_when_fields_are_absent'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $settings->saveSettings([
        'minify_css_output' => '0',
        'role_usage_font_weight_enabled' => '1',
        'class_output_enabled' => '1',
        'class_output_role_heading_enabled' => '1',
        'class_output_role_body_enabled' => '1',
        'class_output_role_monospace_enabled' => '0',
        'class_output_role_alias_interface_enabled' => '1',
        'class_output_role_alias_ui_enabled' => '1',
        'class_output_role_alias_code_enabled' => '0',
        'class_output_category_sans_enabled' => '1',
        'class_output_category_serif_enabled' => '0',
        'class_output_category_mono_enabled' => '0',
        'class_output_families_enabled' => '1',
        'per_variant_font_variables_enabled' => '0',
        'extended_variable_weight_tokens_enabled' => '0',
        'extended_variable_role_aliases_enabled' => '0',
        'extended_variable_category_sans_enabled' => '0',
        'extended_variable_category_serif_enabled' => '0',
        'extended_variable_category_mono_enabled' => '0',
        'preload_primary_fonts' => '0',
        'block_editor_font_library_sync_enabled' => '1',
        'delete_uploaded_files_on_uninstall' => '1',
        'training_wheels_off' => '1',
    ]);
    $settings->saveSettings([
        'preview_sentence' => 'Updated preview',
    ]);
    $saved = $settings->getSettings();

    assertSameValue(false, $saved['minify_css_output'], 'Saving unrelated settings should not re-enable CSS minification.');
    assertSameValue(true, $saved['role_usage_font_weight_enabled'], 'Saving unrelated settings should not disable role usage font-weight output.');
    assertSameValue(true, $saved['class_output_enabled'], 'Saving unrelated settings should not disable class output.');
    assertSameValue(false, $saved['class_output_role_monospace_enabled'], 'Saving unrelated settings should not re-enable disabled class subsettings.');
    assertSameValue(false, $saved['class_output_role_alias_code_enabled'], 'Saving unrelated settings should not re-enable disabled alias class subsettings.');
    assertSameValue(false, $saved['class_output_category_serif_enabled'], 'Saving unrelated settings should not re-enable disabled category class subsettings.');
    assertSameValue(false, $saved['per_variant_font_variables_enabled'], 'Saving unrelated settings should not re-enable per-variant font variables.');
    assertSameValue(false, $saved['extended_variable_weight_tokens_enabled'], 'Saving unrelated settings should not re-enable extended weight tokens.');
    assertSameValue(false, $saved['extended_variable_role_aliases_enabled'], 'Saving unrelated settings should not re-enable extended role aliases.');
    assertSameValue(false, $saved['extended_variable_category_sans_enabled'], 'Saving unrelated settings should not re-enable the sans alias.');
    assertSameValue(false, $saved['extended_variable_category_serif_enabled'], 'Saving unrelated settings should not re-enable the serif alias.');
    assertSameValue(false, $saved['extended_variable_category_mono_enabled'], 'Saving unrelated settings should not re-enable the mono alias.');
    assertSameValue(false, $saved['preload_primary_fonts'], 'Saving unrelated settings should not re-enable primary font preloads.');
    assertSameValue(true, $saved['block_editor_font_library_sync_enabled'], 'Saving unrelated settings should not disable the Block Editor Font Library sync preference.');
    assertSameValue(true, $saved['delete_uploaded_files_on_uninstall'], 'Saving unrelated settings should not disable uninstall cleanup.');
    assertSameValue(true, $saved['training_wheels_off'], 'Saving unrelated settings should not re-enable training wheels once they are turned off.');
};

$tests['settings_repository_defaults_and_persists_optional_monospace_role_settings'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $catalog = ['Inter', 'JetBrains Mono'];
    $defaults = $settings->getSettings();
    $defaultRoles = $settings->getRoles($catalog);

    assertSameValue(false, $defaults['monospace_role_enabled'], 'The optional monospace role should default to disabled.');
    assertSameValue('', $defaultRoles['heading'], 'Draft roles should default the heading family to fallback-only mode.');
    assertSameValue('', $defaultRoles['body'], 'Draft roles should default the body family to fallback-only mode.');
    assertSameValue('sans-serif', $defaultRoles['heading_fallback'], 'Draft roles should default the heading fallback stack to sans-serif.');
    assertSameValue('sans-serif', $defaultRoles['body_fallback'], 'Draft roles should default the body fallback stack to sans-serif.');
    assertSameValue('', $defaultRoles['monospace'], 'Draft roles should default the monospace family to fallback-only mode.');
    assertSameValue('monospace', $defaultRoles['monospace_fallback'], 'Draft roles should default the monospace fallback stack to the generic monospace keyword.');

    $settings->saveSettings(['monospace_role_enabled' => '1']);
    $savedRoles = $settings->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'monospace' => '',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
            'monospace_fallback' => '',
        ],
        $catalog
    );

    assertSameValue(true, $settings->getSettings()['monospace_role_enabled'], 'The monospace role toggle should persist in plugin settings.');
    assertSameValue('', $savedRoles['monospace'], 'Saving monospace roles should not force a family selection when fallback-only mode is chosen.');
    assertSameValue('monospace', $savedRoles['monospace_fallback'], 'Blank monospace fallback input should normalize back to the generic monospace fallback.');
};

$tests['settings_repository_reenables_monospace_class_outputs_when_the_role_is_first_enabled'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $settings->saveSettings([
        'class_output_enabled' => '1',
        'class_output_role_monospace_enabled' => '0',
        'class_output_role_alias_code_enabled' => '0',
        'class_output_category_mono_enabled' => '0',
    ]);

    $saved = $settings->saveSettings([
        'monospace_role_enabled' => '1',
    ]);

    assertSameValue(true, !empty($saved['monospace_role_enabled']), 'Enabling the monospace role should persist the setting.');
    assertSameValue(true, !empty($saved['class_output_role_monospace_enabled']), 'Enabling the monospace role should restore the monospace class output.');
    assertSameValue(true, !empty($saved['class_output_role_alias_code_enabled']), 'Enabling the monospace role should restore the code alias output.');
    assertSameValue(true, !empty($saved['class_output_category_mono_enabled']), 'Enabling the monospace role should restore the mono category output.');
};

$tests['settings_repository_defaults_and_persists_variable_font_feature_settings'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $catalog = ['Inter Variable', 'Inter'];
    $defaults = $settings->getSettings();
    $defaultRoles = $settings->getRoles($catalog);

    assertSameValue(false, $defaults['variable_fonts_enabled'], 'Variable font support should default to disabled.');
    assertSameValue('', $defaultRoles['heading_weight'], 'Draft roles should default heading weight overrides to empty.');
    assertSameValue('', $defaultRoles['body_weight'], 'Draft roles should default body weight overrides to empty.');
    assertSameValue([], $defaultRoles['heading_axes'], 'Draft roles should default heading axis settings to an empty map.');
    assertSameValue([], $defaultRoles['body_axes'], 'Draft roles should default body axis settings to an empty map.');

    $savedSettings = $settings->saveSettings(['variable_fonts_enabled' => '1']);
    $savedRoles = $settings->saveRoles(
        [
            'heading' => 'Inter Variable',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
            'heading_axes' => ['WGHT' => '720', 'OPSZ' => '18'],
            'body_axes' => ['WGHT' => '400'],
        ],
        $catalog
    );

    assertSameValue(true, $savedSettings['variable_fonts_enabled'], 'Variable font support should persist through settings saves.');
    assertSameValue(['OPSZ' => '18', 'WGHT' => '720'], $savedRoles['heading_axes'], 'Role saves should persist normalized heading axis values.');
    assertSameValue(['WGHT' => '400'], $savedRoles['body_axes'], 'Role saves should persist normalized body axis values.');
    assertSameValue(true, $settings->saveSettings(['preview_sentence' => 'Still enabled'])['variable_fonts_enabled'], 'Saving unrelated settings should preserve the variable font feature flag.');
};

$tests['settings_repository_bootstraps_applied_roles_before_draft_changes'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $catalog = ['Inter', 'Lora'];

    $settings->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        $catalog
    );
    $settings->setAutoApplyRoles(true);

    $bootstrapped = $settings->ensureAppliedRolesInitialized($catalog);

    assertSameValue('Inter', $bootstrapped['heading'], 'Applied roles should bootstrap from the current live heading before draft-only changes.');
    assertSameValue('Inter', $bootstrapped['body'], 'Applied roles should bootstrap from the current live body before draft-only changes.');

    $settings->saveRoles(
        [
            'heading' => 'Lora',
            'body' => 'Inter',
            'heading_fallback' => 'serif',
            'body_fallback' => 'sans-serif',
        ],
        $catalog
    );

    $appliedRoles = $settings->getAppliedRoles($catalog);
    $draftRoles = $settings->getRoles($catalog);

    assertSameValue('Inter', $appliedRoles['heading'], 'Draft-only saves should not replace the bootstrapped live heading.');
    assertSameValue('Inter', $appliedRoles['body'], 'Draft-only saves should not replace the bootstrapped live body.');
    assertSameValue('Lora', $draftRoles['heading'], 'Draft roles should still update independently after bootstrapping applied roles.');
};

$tests['repositories_migrate_legacy_option_keys'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore['etch_fonts_settings'] = [
        'preview_sentence' => 'Legacy preview',
        'google_api_key' => 'legacy-key',
    ];
    $optionStore['etch_fonts_roles'] = [
        'heading' => 'Inter',
        'body' => 'Lora',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
    ];
    $optionStore['etch_fonts_imports'] = [
        'inter' => ['slug' => 'inter', 'family' => 'Inter', 'provider' => 'google'],
    ];
    $optionStore['etch_fonts_log'] = [
        ['time' => '2026-04-04 00:00:00', 'message' => 'Legacy log entry', 'actor' => 'System'],
    ];

    $settings = new SettingsRepository();
    $roles = $settings->getRoles(
        [
            ['family' => 'Inter'],
            ['family' => 'Lora'],
        ]
    );
    $imports = (new ImportRepository())->all();
    $log = (new LogRepository())->all();

    assertSameValue('Legacy preview', $settings->getSettings()['preview_sentence'], 'Settings should fall back to the legacy option key during upgrade.');
    assertSameValue('Inter', $roles['heading'], 'Role settings should migrate from the legacy option key during upgrade.');
    assertSameValue(true, isset($optionStore[SettingsRepository::OPTION_SETTINGS]), 'Settings migration should seed the renamed option key.');
    assertSameValue(true, isset($optionStore[SettingsRepository::OPTION_ROLES]), 'Role migration should seed the renamed option key.');
    assertSameValue(true, isset($optionStore[ImportRepository::OPTION_LIBRARY]), 'Import migration should seed the renamed option key.');
    assertSameValue(true, isset($optionStore[LogRepository::OPTION_LOG]), 'Log migration should seed the renamed option key.');
    assertSameValue('Inter', (string) ($imports['inter']['family'] ?? ''), 'Imports should remain available after migrating the option key.');
    assertSameValue('Legacy log entry', (string) ($log[0]['message'] ?? ''), 'Logs should remain available after migrating the option key.');
};

$tests['asset_service_refresh_generated_assets_invalidates_caches_and_queues_css_regeneration'] = static function (): void {
    resetTestState();

    global $optionStore;
    global $scheduledEvents;
    global $transientDeleted;
    global $transientStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'auto_apply_roles' => false,
        'css_delivery_mode' => 'file',
        'font_display' => 'swap',
        'minify_css_output' => false,
        'preview_sentence' => '',
        'google_api_key' => '',
        'google_api_key_status' => 'empty',
        'google_api_key_status_message' => '',
        'google_api_key_checked_at' => 0,
        'family_fallbacks' => [],
    ];
    $optionStore[SettingsRepository::OPTION_ROLES] = [];
    $transientStore['tasty_fonts_catalog_v2'] = ['stale' => true];
    $transientStore['tasty_fonts_css_v2'] = 'stale-css';
    $transientStore['tasty_fonts_css_hash_v2'] = 'stale-hash';

    $storage = new Storage();
    $settings = new SettingsRepository();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeCssParser());
    $google = new GoogleFontsClient($settings);
    $bunny = new BunnyFontsClient();
    $catalog = new CatalogService($storage, $imports, new FontFilenameParser(), $log, $adobe);
    $planner = new RuntimeAssetPlanner($catalog, $settings, $google, $bunny, $adobe);
    $assets = new AssetService($storage, $catalog, $settings, new CssBuilder(), $planner, $log);

    $assets->refreshGeneratedAssets();

    $generatedPath = $storage->getGeneratedCssPath();

    assertSameValue(false, is_string($generatedPath) && file_exists($generatedPath), 'Refreshing generated assets should defer writing the generated CSS file.');
    assertSameValue(true, in_array('tasty_fonts_catalog_v2', $transientDeleted, true), 'Refreshing generated assets should invalidate the catalog cache first.');
    assertSameValue(true, in_array('tasty_fonts_css_v2', $transientDeleted, true), 'Refreshing generated assets should invalidate the cached CSS payload.');
    assertSameValue(true, in_array('tasty_fonts_css_hash_v2', $transientDeleted, true), 'Refreshing generated assets should invalidate the cached CSS hash.');
    assertSameValue(false, array_key_exists('tasty_fonts_css_v2', $transientStore), 'Refreshing generated assets should leave CSS transient regeneration to the next request.');
    assertSameValue(false, array_key_exists('tasty_fonts_css_hash_v2', $transientStore), 'Refreshing generated assets should leave CSS hash regeneration to the next request.');
    assertSameValue(
        [
            [
                'timestamp' => $scheduledEvents[0]['timestamp'] ?? null,
                'hook' => AssetService::ACTION_REGENERATE_CSS,
                'args' => [],
            ],
        ],
        array_map(
            static fn (array $event): array => [
                'timestamp' => $event['timestamp'] ?? null,
                'hook' => $event['hook'] ?? '',
                'args' => $event['args'] ?? [],
            ],
            $scheduledEvents
        ),
        'Refreshing generated assets should queue a single background CSS regeneration event.'
    );
    assertSameValue(
        ['log_write_result' => 1],
        $transientStore['tasty_fonts_regenerate_css_queued'] ?? null,
        'Refreshing generated assets should set a short-lived cron guard transient.'
    );
};

$tests['asset_service_enqueue_inlines_css_and_rewrites_the_generated_file_when_the_stored_hash_is_stale'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $inlineStyles;
    global $optionStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'auto_apply_roles' => false,
        'css_delivery_mode' => 'file',
        'font_display' => 'swap',
        'minify_css_output' => false,
        'preview_sentence' => '',
        'family_fallbacks' => [],
        'family_font_displays' => [],
    ];
    $optionStore[SettingsRepository::OPTION_ROLES] = [];

    $services = makeServiceGraph();
    $generatedPath = $services['storage']->getGeneratedCssPath();

    add_filter(
        'tasty_fonts_generated_css',
        static fn (string $css): string => $css . "\nbody{color:red;}"
    );

    assertSameValue(true, is_string($generatedPath) && $generatedPath !== '', 'The generated CSS path should be available for file delivery.');

    if (is_string($generatedPath)) {
        mkdir(dirname($generatedPath), FS_CHMOD_DIR, true);
        file_put_contents($generatedPath, '/* stale */');
    }

    $services['assets']->enqueue('tasty-fonts-runtime');

    assertSameValue(
        '',
        (string) ($enqueuedStyles['tasty-fonts-runtime']['src'] ?? ''),
        'Stale generated CSS should fall back to inline delivery for the current request.'
    );
    assertContainsValue('body{color:red;}', (string) ($inlineStyles['tasty-fonts-runtime'] ?? ''), 'Inline fallback should include the generated runtime CSS payload.');
    assertContainsValue('/* Version: ', (string) file_get_contents((string) $generatedPath), 'Stale generated CSS should be rewritten with the versioned file payload.');
};

$tests['asset_service_enqueues_inline_css_when_inline_delivery_mode_is_selected'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $inlineStyles;

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['css_delivery_mode' => 'inline']);

    add_filter(
        'tasty_fonts_generated_css',
        static fn (string $css): string => $css . "\nbody{color:blue;}"
    );

    $services['assets']->enqueue('tasty-fonts-runtime');

    assertSameValue('', (string) ($enqueuedStyles['tasty-fonts-runtime']['src'] ?? ''), 'Inline delivery should register a handle without a stylesheet URL.');
    assertContainsValue('body{color:blue;}', (string) ($inlineStyles['tasty-fonts-runtime'] ?? ''), 'Inline delivery should attach the generated CSS to the enqueued handle.');
    assertSameValue(true, is_file((string) $services['storage']->getGeneratedCssPath()), 'Inline delivery should still keep the generated CSS file on disk.');
};

$tests['asset_service_applies_generated_css_filter_before_caching'] = static function (): void {
    resetTestState();

    global $transientStore;

    $services = makeServiceGraph();
    $filterReceivedContext = false;
    add_filter(
        'tasty_fonts_generated_css',
        static function (string $css, array $localCatalog, array $roles, array $settings) use (&$filterReceivedContext): string {
            $filterReceivedContext = array_key_exists('css_delivery_mode', $settings)
                && is_array($localCatalog)
                && is_array($roles);

            return $css . "\nbody{letter-spacing:.02em;}";
        },
        10,
        4
    );

    $css = $services['assets']->getCss();

    assertSameValue(true, $filterReceivedContext, 'Generated CSS filters should receive the runtime catalog, roles, and settings context.');
    assertContainsValue('body{letter-spacing:.02em;}', $css, 'Generated CSS filters should be able to append CSS before the payload is returned.');
    assertContainsValue('body{letter-spacing:.02em;}', (string) ($transientStore['tasty_fonts_css_v2'] ?? ''), 'Generated CSS filters should run before the CSS transient is written.');
};

$tests['asset_service_can_refresh_generated_assets_without_logging_file_writes'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['assets']->refreshGeneratedAssets(true, false);
    $services['assets']->ensureGeneratedCssFile();
    $entries = $services['log']->all();

    assertSameValue(0, count($entries), 'Deferred CSS regeneration should honor the no-log file write option.');
};

$tests['asset_service_status_falls_back_to_legacy_generated_stylesheet_when_canonical_file_is_missing'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $legacyPath = trailingslashit((string) $services['storage']->getRoot()) . 'tasty-fonts.css';
    $legacyUrl = untrailingslashit((string) $services['storage']->getRootUrlFull()) . '/tasty-fonts.css';
    $contents = "/* Version: " . TASTY_FONTS_VERSION . " */\n" . $services['assets']->getCss();
    $lastModified = 1710000000;

    wp_mkdir_p(dirname($legacyPath));
    file_put_contents($legacyPath, $contents);
    touch($legacyPath, $lastModified);
    clearstatcache(true, $legacyPath);

    $status = $services['assets']->getStatus();

    assertSameValue($legacyPath, (string) ($status['path'] ?? ''), 'Generated stylesheet status should fall back to the legacy file path when the canonical .generated file is absent.');
    assertSameValue($legacyUrl, (string) ($status['url'] ?? ''), 'Generated stylesheet status should expose the legacy request URL when the legacy file is the only generated stylesheet on disk.');
    assertSameValue(true, !empty($status['exists']), 'Generated stylesheet status should treat the legacy file as an existing generated asset.');
    assertSameValue(filesize($legacyPath), (int) ($status['size'] ?? 0), 'Generated stylesheet status should report the legacy file size.');
    assertSameValue($lastModified, (int) ($status['last_modified'] ?? 0), 'Generated stylesheet status should report the legacy file modification time.');
};

$tests['asset_service_enqueue_migrates_a_current_legacy_generated_stylesheet_to_the_canonical_location'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $inlineStyles;

    $services = makeServiceGraph();
    $legacyPath = trailingslashit((string) $services['storage']->getRoot()) . 'tasty-fonts.css';
    $canonicalPath = (string) $services['storage']->getGeneratedCssPath();
    $canonicalUrl = (string) $services['storage']->getGeneratedCssUrl();
    $contents = "/* Version: " . TASTY_FONTS_VERSION . " */\n" . $services['assets']->getCss();

    wp_mkdir_p(dirname($legacyPath));
    file_put_contents($legacyPath, $contents);
    clearstatcache(true, $legacyPath);

    $services['assets']->enqueue('tasty-fonts-runtime');

    assertSameValue(true, is_file($canonicalPath), 'Enqueue should rewrite a current legacy generated stylesheet into the canonical .generated location.');
    assertSameValue($contents, (string) file_get_contents($canonicalPath), 'Canonical generated stylesheet migration should preserve the generated CSS contents.');
    assertSameValue($canonicalUrl, (string) ($enqueuedStyles['tasty-fonts-runtime']['src'] ?? ''), 'File delivery should switch to the canonical generated stylesheet URL after migration.');
    assertSameValue('', (string) ($inlineStyles['tasty-fonts-runtime'] ?? ''), 'A current legacy generated stylesheet should not force inline fallback during migration.');
    assertSameValue(true, is_file($legacyPath), 'Migrating the generated stylesheet should not delete the legacy file.');
};

$tests['admin_page_context_builder_uses_asset_status_metadata_for_generated_css_diagnostics'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();
    $optionStore['date_format'] = 'Y-m-d';
    $optionStore['time_format'] = 'H:i:s';

    $builder = new AdminPageContextBuilder(
        $services['storage'],
        $services['settings'],
        $services['log'],
        $services['catalog'],
        $services['assets'],
        new CssBuilder(),
        $services['adobe'],
        $services['google'],
        $services['acss_integration'],
        $services['bricks_integration'],
        $services['oxygen_integration']
    );

    $items = $builder->buildDiagnosticItems(
        [
            'path' => '/tmp/missing-generated.css',
            'url' => 'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css',
            'exists' => true,
            'size' => 2048,
            'last_modified' => 1710000000,
        ],
        $services['storage']->get(),
        [],
        []
    );

    assertSameValue('2.0 KB', (string) ($items[2]['value'] ?? ''), 'Generated stylesheet diagnostics should use the provided asset status size instead of re-checking the filesystem path.');
    assertSameValue('2024-03-09 16:00:00', (string) ($items[3]['value'] ?? ''), 'Generated stylesheet diagnostics should use the provided asset status timestamp instead of calling filemtime on the path again.');
};

$tests['admin_page_context_builder_reports_acss_sync_waiting_for_sitewide_roles'] = static function (): void {
    resetTestState();

    global $automaticCssSettings;

    $automaticCssSettings = [
        'heading-font-family' => '',
        'text-font-family' => '',
    ];

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $services['settings']->saveAcssFontRoleSyncState(true, false, '', '');
    $builder = new AdminPageContextBuilder(
        $services['storage'],
        $services['settings'],
        $services['log'],
        $services['catalog'],
        $services['assets'],
        new CssBuilder(),
        $services['adobe'],
        $services['google'],
        $services['acss_integration'],
        $services['bricks_integration'],
        $services['oxygen_integration']
    );

    $context = $builder->build();

    assertSameValue('waiting_for_sitewide_roles', (string) ($context['acss_integration']['status'] ?? ''), 'Automatic.css sync should report that it is waiting when sitewide role delivery is still off.');
    assertSameValue('', (string) ($context['acss_integration']['current']['heading'] ?? ''), 'Automatic.css integration context should expose the current heading font-family value.');
    assertSameValue('', (string) ($context['acss_integration']['current']['body'] ?? ''), 'Automatic.css integration context should expose the current text font-family value.');
};

$tests['admin_page_context_builder_treats_unavailable_integrations_as_inactive_even_when_previously_enabled'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'auto_apply_roles' => true,
        'block_editor_font_library_sync_enabled' => true,
        'bricks_integration_enabled' => true,
        'oxygen_integration_enabled' => true,
        'acss_font_role_sync_enabled' => true,
        'acss_font_role_sync_applied' => true,
    ];

    add_filter('tasty_fonts_bricks_integration_available', static fn (): bool => false);
    add_filter('tasty_fonts_oxygen_integration_available', static fn (): bool => false);
    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => false);

    $services = makeServiceGraph();
    $builder = new \TastyFonts\Admin\AdminPageContextBuilder(
        $services['storage'],
        $services['settings'],
        $services['log'],
        $services['catalog'],
        $services['assets'],
        new CssBuilder(),
        $services['adobe'],
        $services['google'],
        $services['acss_integration'],
        $services['bricks_integration'],
        $services['oxygen_integration']
    );

    $context = $builder->build();

    assertSameValue(false, (bool) ($context['acss_integration']['enabled'] ?? true), 'Automatic.css should render as inactive when the plugin is unavailable, even if the stored preference was previously enabled.');
    assertSameValue('unavailable', (string) ($context['acss_integration']['status'] ?? ''), 'Automatic.css should report an unavailable status when the plugin is inactive.');
    assertSameValue(false, (bool) ($context['bricks_integration']['enabled'] ?? true), 'Bricks should render as inactive when the plugin is unavailable, even if the stored preference was previously enabled.');
    assertSameValue('unavailable', (string) ($context['bricks_integration']['status'] ?? ''), 'Bricks should report an unavailable status when the plugin is inactive.');
    assertSameValue(false, (bool) ($context['oxygen_integration']['enabled'] ?? true), 'Oxygen should render as inactive when the plugin is unavailable, even if the stored preference was previously enabled.');
    assertSameValue('unavailable', (string) ($context['oxygen_integration']['status'] ?? ''), 'Oxygen should report an unavailable status when the plugin is inactive.');
};

$tests['admin_page_context_builder_ignores_legacy_role_delivery_ids_when_comparing_live_roles'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Lora',
        'lora',
        [
            'id' => 'bunny-cdn-static',
            'label' => 'Bunny CDN',
            'provider' => 'bunny',
            'type' => 'cdn',
            'format' => 'static',
            'variants' => ['regular'],
            'faces' => [],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Lora',
        'lora',
        [
            'id' => 'self-hosted-static',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'static',
            'variants' => ['regular'],
            'faces' => [],
        ],
        'published',
        false
    );
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'inter-static',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'static',
            'variants' => ['regular'],
            'faces' => [],
        ],
        'published',
        true
    );
    $services['settings']->saveSettings([
        'heading_font' => 'Lora',
        'body_font' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
        'heading_delivery_id' => 'bunny-cdn-static',
        'body_delivery_id' => 'inter-static',
        'applied_roles' => [
            'heading' => 'Lora',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
            'heading_delivery_id' => '',
            'body_delivery_id' => '',
        ],
    ]);
    $services['settings']->setAutoApplyRoles(true);

    $builder = new AdminPageContextBuilder(
        $services['storage'],
        $services['settings'],
        $services['log'],
        $services['catalog'],
        $services['assets'],
        new CssBuilder(),
        $services['adobe'],
        $services['google'],
        $services['acss_integration'],
        $services['bricks_integration'],
        $services['oxygen_integration']
    );

    $context = $builder->build();

    assertSameValue('Live', (string) ($context['role_deployment']['badge'] ?? ''), 'Role deployment should stay live when draft and applied roles only differ by legacy delivery IDs.');
    assertSameValue('Live Roles Active', (string) ($context['role_deployment']['title'] ?? ''), 'Role deployment should ignore legacy delivery ID differences.');
};

$tests['admin_controller_applies_acss_font_mapping_when_sync_is_enabled'] = static function (): void {
    resetTestState();

    global $automaticCssSettings;

    $automaticCssSettings = [
        'heading-font-family' => 'Inter, sans-serif',
        'text-font-family' => 'system-ui, sans-serif',
    ];

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $services['settings']->setAutoApplyRoles(true);

    $result = $services['controller']->saveSettingsValues([
        'acss_font_role_sync_enabled' => '1',
    ]);

    assertSameValue('var(--font-heading)', (string) ($automaticCssSettings['heading-font-family'] ?? ''), 'Enabling Automatic.css sync should push the heading role variable into Automatic.css.');
    assertSameValue('var(--font-body)', (string) ($automaticCssSettings['text-font-family'] ?? ''), 'Enabling Automatic.css sync should push the body role variable into Automatic.css.');
    assertSameValue(true, (bool) ($result['settings']['acss_font_role_sync_applied'] ?? false), 'Controller saves should mark Automatic.css sync as applied after the ACSS settings update succeeds.');
    assertSameValue('Inter, sans-serif', (string) ($result['settings']['acss_font_role_sync_previous_heading_font_family'] ?? ''), 'The previous ACSS heading value should be backed up before Tasty Fonts overwrites it.');
    assertContainsValue('Automatic.css now uses Tasty Fonts role variables', (string) ($result['message'] ?? ''), 'The settings response should explain that Automatic.css is now mapped to Tasty Fonts variables.');
};

$tests['admin_controller_recovers_legacy_acss_detection_state_when_acss_is_activated_later'] = static function (): void {
    resetTestState();

    global $automaticCssSettings;
    global $optionStore;

    $automaticCssSettings = [
        'heading-font-family' => '',
        'text-font-family' => '',
    ];

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'auto_apply_roles' => true,
        'acss_font_role_sync_enabled' => false,
        'acss_font_role_sync_applied' => false,
        'acss_font_role_sync_previous_heading_font_family' => '',
        'acss_font_role_sync_previous_text_font_family' => '',
    ];

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();

    invokePrivateMethod($services['controller'], 'initializeDetectedIntegrations');

    $saved = $services['settings']->getSettings();

    assertSameValue(true, $saved['acss_font_role_sync_enabled'], 'Automatic.css should auto-enable when the stored state was stuck false from legacy unavailable detection and ACSS is activated later.');
    assertSameValue(true, $saved['acss_font_role_sync_applied'], 'Automatic.css should mark the managed mapping applied after recovering the legacy detection state.');
    assertSameValue('var(--font-heading)', (string) ($automaticCssSettings['heading-font-family'] ?? ''), 'Recovering the legacy Automatic.css detection state should apply the heading role variable.');
    assertSameValue('var(--font-body)', (string) ($automaticCssSettings['text-font-family'] ?? ''), 'Recovering the legacy Automatic.css detection state should apply the body role variable.');
};

$tests['admin_controller_preserves_unavailable_integration_detection_state_on_settings_save'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'bricks_integration_enabled' => null,
        'oxygen_integration_enabled' => null,
        'acss_font_role_sync_enabled' => null,
    ];

    add_filter('tasty_fonts_bricks_integration_available', static fn (): bool => false);
    add_filter('tasty_fonts_oxygen_integration_available', static fn (): bool => false);
    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => false);

    $services = makeServiceGraph();
    $saved = $services['settings']->getSettings();

    assertSameValue(null, $saved['bricks_integration_enabled'], 'Bricks integration should start unconfigured for this regression test.');
    assertSameValue(null, $saved['oxygen_integration_enabled'], 'Oxygen integration should start unconfigured for this regression test.');
    assertSameValue(null, $saved['acss_font_role_sync_enabled'], 'Automatic.css integration should start unconfigured for this regression test.');

    $result = $services['controller']->saveSettingsValues([
        'bricks_integration_enabled' => '0',
        'oxygen_integration_enabled' => '0',
        'acss_font_role_sync_enabled' => '0',
    ]);

    assertSameValue(null, $result['settings']['bricks_integration_enabled'], 'Saving settings while Bricks is unavailable should preserve its unconfigured detection state so later installs can still auto-enable it.');
    assertSameValue(null, $result['settings']['oxygen_integration_enabled'], 'Saving settings while Oxygen is unavailable should preserve its unconfigured detection state so later installs can still auto-enable it.');
    assertSameValue(null, $result['settings']['acss_font_role_sync_enabled'], 'Saving settings while Automatic.css is unavailable should preserve its unconfigured detection state so later installs can still auto-enable it.');
};

$tests['admin_controller_restores_previous_acss_font_values_when_sitewide_roles_are_disabled'] = static function (): void {
    resetTestState();

    global $automaticCssSettings;

    $automaticCssSettings = [
        'heading-font-family' => 'var(--font-heading)',
        'text-font-family' => 'var(--font-body)',
    ];

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $settings = $services['settings']->saveAcssFontRoleSyncState(true, true, 'Inter, sans-serif', 'system-ui, sans-serif');
    $settings = $services['settings']->setAutoApplyRoles(false);
    $result = invokePrivateMethod($services['controller'], 'syncAcssIntegrationForRuntimeState', [$settings]);
    $saved = $services['settings']->getSettings();

    assertSameValue('Inter, sans-serif', (string) ($automaticCssSettings['heading-font-family'] ?? ''), 'Disabling sitewide roles should restore the previous Automatic.css heading font-family value.');
    assertSameValue('system-ui, sans-serif', (string) ($automaticCssSettings['text-font-family'] ?? ''), 'Disabling sitewide roles should restore the previous Automatic.css text font-family value.');
    assertSameValue(false, (bool) ($saved['acss_font_role_sync_applied'] ?? true), 'Automatic.css sync should mark itself unapplied after restoring the previous ACSS values.');
    assertContainsValue('restored to its previous font-family values', (string) $result, 'The runtime sync helper should explain that ACSS was restored after sitewide roles were turned off.');
};

$tests['admin_controller_turns_off_acss_sync_when_acss_drifts_outside_tasty_fonts'] = static function (): void {
    resetTestState();

    global $automaticCssSettings;

    $automaticCssSettings = [
        'heading-font-family' => '',
        'text-font-family' => '',
    ];

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveAcssFontRoleSyncState(true, true, 'Inter, sans-serif', 'system-ui, sans-serif');

    invokePrivateMethod($services['controller'], 'reconcileAcssIntegrationDrift');

    $saved = $services['settings']->getSettings();

    assertSameValue(false, (bool) ($saved['acss_font_role_sync_enabled'] ?? true), 'Managed Automatic.css sync should turn itself off when ACSS no longer matches the Tasty Fonts mapping.');
    assertSameValue(false, (bool) ($saved['acss_font_role_sync_applied'] ?? true), 'Managed Automatic.css sync should clear its applied flag after drift is detected.');
    assertSameValue('Inter, sans-serif', (string) ($saved['acss_font_role_sync_previous_heading_font_family'] ?? ''), 'Drift detection should preserve the last backed-up heading value so re-enabling can still restore cleanly.');
};

$tests['asset_service_debounces_background_css_regeneration_events'] = static function (): void {
    resetTestState();

    global $scheduledEvents;
    global $transientStore;

    $services = makeServiceGraph();
    $services['assets']->refreshGeneratedAssets();
    $services['assets']->refreshGeneratedAssets();

    assertSameValue(1, count($scheduledEvents), 'Repeated asset invalidations in a short window should only queue one background CSS regeneration event.');
    assertSameValue(
        ['log_write_result' => 1],
        $transientStore['tasty_fonts_regenerate_css_queued'] ?? null,
        'Queued CSS regeneration should keep the short-lived guard transient until the write runs.'
    );
};

$tests['runtime_service_enqueues_adobe_stylesheet_and_exposes_it_to_etch_canvas'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $localizedScripts;
    global $remoteGetResponses;

    $services = makeServiceGraph();
    $services['settings']->saveAdobeProject('abc1234', true);
    $services['settings']->saveAdobeProjectStatus('valid', 'Adobe project ready.');
    $remoteGetResponses['https://use.typekit.net/abc1234.css'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: "ff-tisa-web-pro";
  font-style: normal;
  font-weight: 400;
  src: url("https://use.typekit.net/af/abc123/000000000000000000000000/30/l?primer=1") format("woff2");
}
CSS,
    ];
    $_GET['etch'] = '1';

    $services['runtime']->enqueueFrontend();
    $editorFamilies = $services['planner']->getEditorFontFamilies();
    $familyNames = array_values(array_map(static fn (array $item): string => (string) ($item['name'] ?? ''), $editorFamilies));
    $styleUrls = array_values(array_map(static fn (array $style): string => (string) ($style['src'] ?? ''), $enqueuedStyles));
    $canvasStylesheetUrls = (array) ($localizedScripts['tasty-fonts-canvas']['data']['stylesheetUrls'] ?? []);

    assertSameValue(
        true,
        in_array('https://use.typekit.net/abc1234.css', $styleUrls, true),
        'Runtime should enqueue the Adobe project stylesheet as a separate frontend style handle.'
    );
    assertSameValue(
        true,
        in_array('ff-tisa-web-pro', $familyNames, true),
        'Runtime editor font families should include Adobe project families.'
    );
    assertSameValue(
        true,
        in_array('https://use.typekit.net/abc1234.css', $canvasStylesheetUrls, true),
        'Etch canvas runtime data should include the Adobe stylesheet URL.'
    );
};

$tests['runtime_service_marks_external_font_stylesheet_links_cors_readable'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $html = "<link rel='stylesheet' id='tasty-fonts-google-jetbrains-mono-cdn-css' href='https://fonts.googleapis.com/css2?family=JetBrains+Mono' media='all' />";

    $filtered = $services['runtime']->filterExternalStylesheetTag(
        $html,
        'tasty-fonts-google-jetbrains-mono-cdn',
        'https://fonts.googleapis.com/css2?family=JetBrains+Mono',
        'all'
    );

    assertContainsValue(
        'crossorigin="anonymous"',
        $filtered,
        'External Google font stylesheets should opt into anonymous CORS so builder integrations can inspect their rules.'
    );

    assertSameValue(
        "<link rel='stylesheet' id='tasty-fonts-frontend-css' href='/wp-content/uploads/fonts/.generated/tasty-fonts.css' media='all' />",
        $services['runtime']->filterExternalStylesheetTag(
            "<link rel='stylesheet' id='tasty-fonts-frontend-css' href='/wp-content/uploads/fonts/.generated/tasty-fonts.css' media='all' />",
            'tasty-fonts-frontend',
            '/wp-content/uploads/fonts/.generated/tasty-fonts.css',
            'all'
        ),
        'Generated local stylesheets should be left unchanged by the external stylesheet tag filter.'
    );
};

$tests['runtime_service_skips_font_preload_hints_when_inline_css_delivery_is_enabled'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['css_delivery_mode' => 'inline']);
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-self-hosted',
            'label' => 'Self-hosted (Google import)',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'google',
                    'weight' => '400',
                    'style' => 'normal',
                    'files' => ['woff2' => 'google/inter/inter-400-normal.woff2'],
                    'paths' => ['woff2' => 'google/inter/inter-400-normal.woff2'],
                ],
            ],
        ],
        'published',
        true
    );

    ob_start();
    $services['runtime']->outputPreloadHints();
    $output = (string) ob_get_clean();

    assertNotContainsValue('rel="preload"', $output, 'Inline CSS delivery should skip font preload hints.');
};

$tests['runtime_asset_planner_forces_swap_for_admin_preview_stylesheets'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['font_display' => 'optional']);
    $services['imports']->saveProfile(
        'JetBrains Mono',
        'jetbrains-mono',
        [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular', '700'],
            'faces' => [],
        ],
        'published',
        true
    );

    $runtimeStylesheets = $services['planner']->getExternalStylesheets();
    $adminPreviewStylesheets = $services['planner']->getAdminPreviewStylesheets();
    $runtimeUrl = (string) ($runtimeStylesheets[0]['url'] ?? '');
    $previewUrl = (string) ($adminPreviewStylesheets[0]['url'] ?? '');

    assertContainsValue('display=swap', $runtimeUrl, 'Frontend CDN runtime stylesheets should promote optional to swap so the custom font still appears after first paint.');
    assertContainsValue('display=swap', $previewUrl, 'Admin preview stylesheets should force swap so previews remain visible after reload.');
};

$tests['runtime_asset_planner_preserves_explicit_cdn_font_display_overrides'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['font_display' => 'optional']);
    $services['settings']->saveFamilyFontDisplay('JetBrains Mono', 'fallback');
    $services['imports']->saveProfile(
        'JetBrains Mono',
        'jetbrains-mono',
        [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular', '700'],
            'faces' => [],
        ],
        'published',
        true
    );

    $runtimeStylesheets = $services['planner']->getExternalStylesheets();
    $runtimeUrl = (string) ($runtimeStylesheets[0]['url'] ?? '');

    assertContainsValue('display=fallback', $runtimeUrl, 'Explicit per-family CDN font-display overrides should still be honored.');
};

$tests['runtime_service_enqueues_block_editor_content_styles_for_gutenberg_iframe'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $isAdminRequest;

    $services = makeServiceGraph();
    $isAdminRequest = true;
    $services['imports']->saveProfile(
        'JetBrains Mono',
        'jetbrains-mono',
        [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular', '700'],
            'faces' => [],
        ],
        'published',
        true
    );

    $services['runtime']->enqueueBlockEditorContent();

    assertSameValue(
        true,
        isset($enqueuedStyles['tasty-fonts-editor-content']),
        'Gutenberg iframe styles should enqueue a dedicated generated CSS handle during enqueue_block_assets.'
    );
    assertSameValue(
        true,
        isset($enqueuedStyles['tasty-fonts-google-jetbrains-mono-cdn-editor-content']),
        'Gutenberg iframe styles should enqueue remote font stylesheets with iframe-specific handles so WordPress hoists them into the canvas.'
    );
};

$tests['asset_service_forces_swap_for_self_hosted_admin_preview_font_faces'] = static function (): void {
    resetTestState();

    global $inlineStyles;

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Almendra Display',
        'almendra-display',
        [
            'id' => 'google-self_hosted',
            'label' => 'Self-hosted (Google import)',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                [
                    'family' => 'Almendra Display',
                    'slug' => 'almendra-display',
                    'source' => 'google',
                    'weight' => '400',
                    'style' => 'normal',
                    'files' => ['woff2' => 'google/almendra-display/almendra-display-400-normal.woff2'],
                    'paths' => ['woff2' => 'google/almendra-display/almendra-display-400-normal.woff2'],
                ],
            ],
        ],
        'library_only',
        true
    );
    $services['settings']->saveSettings([
        'font_display' => 'optional',
        'family_font_displays' => ['Almendra Display' => 'optional'],
    ]);

    $services['assets']->enqueueFontFacesOnly('tasty-fonts-admin-fonts');

    $css = (string) ($inlineStyles['tasty-fonts-admin-fonts'] ?? '');

    assertContainsValue('font-family:"Almendra Display"', $css, 'Admin preview font-face CSS should include self-hosted imported families.');
    assertContainsValue('font-display:swap', $css, 'Admin preview font-face CSS should force swap so preview text does not get stuck on fallback faces.');
    assertNotContainsValue('font-display:optional', $css, 'Admin preview font-face CSS should ignore optional display policies during preview rendering.');
};

$tests['asset_service_generates_runtime_css_for_only_the_active_self_hosted_delivery'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-static-a.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-static-b.woff2'), 'font-data');
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted-a',
            'label' => 'Self-hosted A',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'static',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400-static-a.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-static-a.woff2'],
            ]],
        ],
        'published',
        false
    );
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted-b',
            'label' => 'Self-hosted B',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'static',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400-static-b.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-static-b.woff2'],
            ]],
        ],
        'published',
        true
    );

    $css = $services['assets']->getCss();

    assertContainsValue('Inter-400-static-b.woff2', $css, 'Generated runtime CSS should include only the active self-hosted delivery.');
    assertNotContainsValue('Inter-400-static-a.woff2', $css, 'Generated runtime CSS should exclude inactive self-hosted deliveries.');
};

$tests['asset_service_generates_preview_font_faces_for_only_the_active_self_hosted_delivery'] = static function (): void {
    resetTestState();

    global $inlineStyles;

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-preview-a.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-preview-b.woff2'), 'font-data');
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted-a',
            'label' => 'Self-hosted A',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'static',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400-preview-a.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-preview-a.woff2'],
            ]],
        ],
        'published',
        false
    );
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted-b',
            'label' => 'Self-hosted B',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'static',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400-preview-b.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-preview-b.woff2'],
            ]],
        ],
        'published',
        true
    );

    $services['assets']->enqueueFontFacesOnly('tasty-fonts-admin-fonts');
    $css = (string) ($inlineStyles['tasty-fonts-admin-fonts'] ?? '');

    assertContainsValue('font-family:"Inter";font-weight:400;font-style:normal;src:url("/wp-content/uploads/fonts/inter/Inter-400-preview-b.woff2")', $css, 'Admin preview font-face CSS should include the active self-hosted delivery for the selected family.');
    assertNotContainsValue('font-family:"Inter";font-weight:400;font-style:normal;src:url("/wp-content/uploads/fonts/inter/Inter-400-preview-a.woff2")', $css, 'Admin preview font-face CSS should exclude inactive self-hosted deliveries for the selected family.');
};

$tests['runtime_service_outputs_primary_font_preloads_for_live_sitewide_roles'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-700.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('lora/Lora-400.woff2'), 'font-data');
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular', '700'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-400.woff2'], 'paths' => ['woff2' => 'inter/Inter-400.woff2']],
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '700', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-700.woff2'], 'paths' => ['woff2' => 'inter/Inter-700.woff2']],
            ],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Lora',
        'lora',
        [
            'id' => 'local-self_hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Lora', 'slug' => 'lora', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'lora/Lora-400.woff2'], 'paths' => ['woff2' => 'lora/Lora-400.woff2']],
            ],
        ],
        'published',
        true
    );
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Lora',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        ['Inter', 'Lora']
    );
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings(['preload_primary_fonts' => '1']);

    ob_start();
    $services['runtime']->outputPreloadHints();
    $output = (string) ob_get_clean();

    assertContainsValue('href="/wp-content/uploads/fonts/inter/Inter-700.woff2"', $output, 'Frontend preload output should include the primary heading WOFF2 file.');
    assertContainsValue('href="/wp-content/uploads/fonts/lora/Lora-400.woff2"', $output, 'Frontend preload output should include the primary body WOFF2 file.');
    assertContainsValue('type="font/woff2"', $output, 'Frontend preload output should declare the WOFF2 mime type.');
    assertContainsValue('crossorigin', $output, 'Frontend preload output should include crossorigin so the hint matches the font request mode.');
};

$tests['runtime_service_uses_saved_role_weight_overrides_for_primary_preloads'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-600.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-700.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('lora/Lora-400.woff2'), 'font-data');
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular', '600', '700'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-400.woff2'], 'paths' => ['woff2' => 'inter/Inter-400.woff2']],
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '600', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-600.woff2'], 'paths' => ['woff2' => 'inter/Inter-600.woff2']],
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '700', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-700.woff2'], 'paths' => ['woff2' => 'inter/Inter-700.woff2']],
            ],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Lora',
        'lora',
        [
            'id' => 'local-self_hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Lora', 'slug' => 'lora', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'lora/Lora-400.woff2'], 'paths' => ['woff2' => 'lora/Lora-400.woff2']],
            ],
        ],
        'published',
        true
    );
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Lora',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
            'heading_weight' => '600',
        ],
        ['Inter', 'Lora']
    );
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings(['preload_primary_fonts' => '1']);

    ob_start();
    $services['runtime']->outputPreloadHints();
    $output = (string) ob_get_clean();

    assertContainsValue('href="/wp-content/uploads/fonts/inter/Inter-600.woff2"', $output, 'Frontend preload output should honor saved heading role weight overrides.');
    assertNotContainsValue('href="/wp-content/uploads/fonts/inter/Inter-700.woff2"', $output, 'Frontend preload output should stop assuming the default bold heading face when a saved weight override exists.');
};

$tests['runtime_service_uses_the_active_family_delivery_for_primary_preloads'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-static.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-700-static.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-Variable.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('lora/Lora-400.woff2'), 'font-data');

    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted-static',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'static',
            'variants' => ['regular', '700'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'files' => ['woff2' => 'inter/Inter-400-static.woff2'],
                    'paths' => ['woff2' => 'inter/Inter-400-static.woff2'],
                ],
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '700',
                    'style' => 'normal',
                    'files' => ['woff2' => 'inter/Inter-700-static.woff2'],
                    'paths' => ['woff2' => 'inter/Inter-700-static.woff2'],
                ],
            ],
        ],
        'published',
        false
    );
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted-variable',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'variable',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'is_variable' => true,
                'axes' => [
                    'WGHT' => ['min' => '100', 'default' => '400', 'max' => '900'],
                ],
                'variation_defaults' => ['WGHT' => '400'],
                'files' => ['woff2' => 'inter/Inter-Variable.woff2'],
                'paths' => ['woff2' => 'inter/Inter-Variable.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Lora',
        'lora',
        [
            'id' => 'local-self_hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'static',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Lora',
                'slug' => 'lora',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'lora/Lora-400.woff2'],
                'paths' => ['woff2' => 'lora/Lora-400.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Lora',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
            'heading_delivery_id' => 'local-self_hosted-static',
        ],
        ['Inter', 'Lora']
    );
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings([
        'preload_primary_fonts' => '1',
        'variable_fonts_enabled' => '1',
    ]);

    ob_start();
    $services['runtime']->outputPreloadHints();
    $output = (string) ob_get_clean();

    assertContainsValue('href="/wp-content/uploads/fonts/inter/Inter-Variable.woff2"', $output, 'Frontend preload output should follow the family active delivery even when legacy role delivery IDs still exist in stored role data.');
    assertNotContainsValue('href="/wp-content/uploads/fonts/inter/Inter-700-static.woff2"', $output, 'Frontend preload output should ignore legacy role delivery overrides.');
};

$tests['runtime_service_preloads_variable_faces_when_their_weight_axis_covers_role_targets'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('upload/inter-variable/Inter Variable-VariableFont.woff2'), 'font-data');
    $services['imports']->saveProfile(
        'Inter Variable',
        'inter-variable',
        [
            'id' => 'local-self_hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter Variable',
                'slug' => 'inter-variable',
                'source' => 'local',
                'weight' => '450',
                'style' => 'normal',
                'is_variable' => true,
                'axes' => [
                    'WGHT' => ['min' => '300', 'default' => '450', 'max' => '700'],
                ],
                'variation_defaults' => ['WGHT' => '450'],
                'files' => ['woff2' => 'upload/inter-variable/Inter Variable-VariableFont.woff2'],
                'paths' => ['woff2' => 'upload/inter-variable/Inter Variable-VariableFont.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter Variable',
            'body' => 'Inter Variable',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        ['Inter Variable']
    );
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings([
        'preload_primary_fonts' => '1',
        'variable_fonts_enabled' => '1',
    ]);

    ob_start();
    $services['runtime']->outputPreloadHints();
    $output = (string) ob_get_clean();

    assertContainsValue(
        'href="/wp-content/uploads/fonts/upload/inter-variable/Inter%20Variable-VariableFont.woff2"',
        $output,
        'Frontend preload output should treat variable faces as valid matches when their WGHT axis covers the requested role weights.'
    );
};

$tests['runtime_asset_planner_uses_category_aware_fallbacks_for_editor_font_families'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'JetBrains Mono',
        'jetbrains-mono',
        [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'JetBrains Mono',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'https://example.com/jetbrains-mono.woff2'],
            ]],
            'meta' => ['category' => 'monospace'],
        ],
        'published',
        true
    );
    $services['settings']->setAutoApplyRoles(true);

    $families = $services['planner']->getEditorFontFamilies();

    assertContainsValue(
        '"JetBrains Mono", monospace',
        (string) ($families[0]['fontFamily'] ?? ''),
        'Editor font family payloads should default monospace families to the monospace generic fallback when no explicit family fallback is saved.'
    );
};

$tests['runtime_service_skips_font_preloads_when_setting_or_live_roles_are_disabled'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400.woff2'), 'font-data');
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        ['Inter']
    );

    ob_start();
    $services['runtime']->outputPreloadHints();
    $outputWithSitewideOff = (string) ob_get_clean();

    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings(['preload_primary_fonts' => '0']);

    ob_start();
    $services['runtime']->outputPreloadHints();
    $outputWithPreloadsOff = (string) ob_get_clean();

    assertSameValue('', $outputWithSitewideOff, 'Frontend preload output should stay empty while live sitewide role output is disabled.');
    assertSameValue('', $outputWithPreloadsOff, 'Frontend preload output should stay empty when the preload setting is turned off.');
};

$tests['runtime_asset_planner_resolves_unicode_range_output_modes_for_editor_faces'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'weight' => '400',
                'style' => 'normal',
                'unicode_range' => 'U+0370-03FF',
                'files' => ['woff2' => 'google/inter/inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['settings']->setAutoApplyRoles(true);

    $defaultFamilies = $services['planner']->getEditorFontFamilies();
    $defaultFace = (array) (($defaultFamilies[0]['fontFace'][0] ?? null) ?: []);
    assertSameValue(false, array_key_exists('unicodeRange', $defaultFace), 'Editor font-face payloads should omit unicodeRange by default now that unicode-range output defaults to off.');

    $services['settings']->saveSettings(['unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_PRESERVE]);
    $preservedFamilies = $services['planner']->getEditorFontFamilies();
    $preservedFace = (array) (($preservedFamilies[0]['fontFace'][0] ?? null) ?: []);
    assertSameValue('U+0370-03FF', (string) ($preservedFace['unicodeRange'] ?? ''), 'Editor font-face payloads should preserve stored unicode ranges when preserve mode is selected.');

    $services['settings']->saveSettings(['unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_LATIN_BASIC]);
    $latinFamilies = $services['planner']->getEditorFontFamilies();
    $latinFace = (array) (($latinFamilies[0]['fontFace'][0] ?? null) ?: []);
    assertSameValue(FontUtils::UNICODE_RANGE_PRESET_LATIN_BASIC, (string) ($latinFace['unicodeRange'] ?? ''), 'Editor font-face payloads should emit the forced Basic Latin unicode-range when selected.');

    $services['settings']->saveSettings(['unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_OFF]);
    $offFamilies = $services['planner']->getEditorFontFamilies();
    $offFace = (array) (($offFamilies[0]['fontFace'][0] ?? null) ?: []);
    assertSameValue(false, array_key_exists('unicodeRange', $offFace), 'Editor font-face payloads should omit unicodeRange when unicode-range output is disabled.');
};

$tests['settings_repository_tracks_builder_integration_state'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $defaults = $settings->getSettings();

    assertSameValue(null, $defaults['bricks_integration_enabled'], 'Bricks integration should start unconfigured so supported sites can default on once detected.');
    assertSameValue(null, $defaults['oxygen_integration_enabled'], 'Oxygen integration should start unconfigured so supported sites can default on once detected.');

    $saved = $settings->saveSettings([
        'bricks_integration_enabled' => '1',
        'oxygen_integration_enabled' => '0',
    ]);

    assertSameValue(true, $saved['bricks_integration_enabled'], 'Saving the Bricks integration toggle should persist an explicit enabled state.');
    assertSameValue(false, $saved['oxygen_integration_enabled'], 'Saving the Oxygen integration toggle should persist an explicit disabled state.');
};

$tests['runtime_service_adds_only_runtime_families_to_bricks_standard_fonts'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $profile = [
        'id' => 'local-self-hosted',
        'label' => 'Local upload',
        'provider' => 'local',
        'type' => 'self_hosted',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Inter',
            'slug' => 'inter',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'files' => ['woff2' => 'upload/inter/inter-400-normal.woff2'],
            'paths' => ['woff2' => 'upload/inter/inter-400-normal.woff2'],
        ]],
    ];

    $services['imports']->saveProfile('Inter', 'inter', $profile, 'published', true);
    $services['imports']->saveProfile('Draft Sans', 'draft-sans', array_replace($profile, [
        'faces' => [[
            'family' => 'Draft Sans',
            'slug' => 'draft-sans',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'files' => ['woff2' => 'upload/draft-sans/draft-sans-400-normal.woff2'],
            'paths' => ['woff2' => 'upload/draft-sans/draft-sans-400-normal.woff2'],
        ]],
    ]), 'library_only', true);

    $fonts = $services['runtime']->filterBricksStandardFonts(['Arial']);

    assertSameValue(['Arial', 'Inter'], $fonts, 'Bricks should receive existing standard fonts plus published Tasty Fonts runtime families only.');
};

$tests['runtime_service_appends_builder_editor_styles_for_managed_bricks_and_oxygen_fonts'] = static function (): void {
    resetTestState();

    global $currentPostId;
    global $optionStore;
    global $oxygenGlobalSettings;

    $services = makeServiceGraph();
    $baseProfile = [
        'id' => 'local-self-hosted',
        'label' => 'Local upload',
        'provider' => 'local',
        'type' => 'self_hosted',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Inter',
            'slug' => 'inter',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'files' => ['woff2' => 'upload/inter/inter-400-normal.woff2'],
            'paths' => ['woff2' => 'upload/inter/inter-400-normal.woff2'],
        ]],
    ];

    $services['imports']->saveProfile('Inter', 'inter', $baseProfile, 'published', true);
    $services['imports']->saveProfile('Merriweather', 'merriweather', array_replace($baseProfile, [
        'faces' => [[
            'family' => 'Merriweather',
            'slug' => 'merriweather',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'files' => ['woff2' => 'upload/merriweather/merriweather-400-normal.woff2'],
            'paths' => ['woff2' => 'upload/merriweather/merriweather-400-normal.woff2'],
        ]],
    ]), 'published', true);
    $services['imports']->saveProfile('Draft Sans', 'draft-sans', array_replace($baseProfile, [
        'faces' => [[
            'family' => 'Draft Sans',
            'slug' => 'draft-sans',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'files' => ['woff2' => 'upload/draft-sans/draft-sans-400-normal.woff2'],
            'paths' => ['woff2' => 'upload/draft-sans/draft-sans-400-normal.woff2'],
        ]],
    ]), 'library_only', true);

    $currentPostId = 42;
    $optionStore[BRICKS_DB_THEME_STYLES] = [
        'style-primary' => [
            'settings' => [
                'conditions' => [
                    'conditions' => [
                        ['priority' => 10],
                    ],
                ],
                'typography' => [
                    'typographyBody' => ['font-family' => 'Inter'],
                    'typographyHeadingH1' => ['font-family' => 'Merriweather'],
                    'typographyHeadingH2' => ['font-family' => 'Draft Sans'],
                ],
            ],
        ],
    ];
    $oxygenGlobalSettings = [
        'fonts' => [
            'Text' => 'Inter',
            'Display' => 'Merriweather',
        ],
    ];

    $settings = $services['runtime']->filterBlockEditorSettings([], null);
    $css = (string) ($settings['styles'][0]['css'] ?? '');

    assertContainsValue('body{font-family:"Inter", sans-serif;}', $css, 'Builder editor styles should mirror managed body families into Gutenberg.');
    assertContainsValue('body :is(h1, .editor-post-title){font-family:"Merriweather", sans-serif;}', $css, 'Bricks heading styles should mirror managed H1 selections into Gutenberg.');
    assertContainsValue('body :is(h1, h2, h3, h4, h5, h6, .editor-post-title){font-family:"Merriweather", sans-serif;}', $css, 'Oxygen display families should mirror into Gutenberg heading styles.');
    assertNotContainsValue('Draft Sans', $css, 'Builder editor styles should ignore library-only families that are not part of the runtime catalog.');
};

$tests['runtime_service_appends_acss_editor_styles_when_font_role_sync_is_active'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'acss_font_role_sync_enabled' => true,
    ]);
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveAcssFontRoleSyncState(true, true, 'system-ui', 'system-ui');

    $settings = $services['runtime']->filterBlockEditorSettings([], null);
    $css = (string) ($settings['styles'][0]['css'] ?? '');

    assertContainsValue('body{font-family:var(--font-body);}', $css, 'Automatic.css editor styles should mirror the managed body variable into Gutenberg.');
    assertContainsValue('body :is(h1, h2, h3, h4, h5, h6, .editor-post-title, .wp-block-post-title){font-family:var(--font-heading);}', $css, 'Automatic.css editor styles should mirror the managed heading variable into Gutenberg, including the post title.');
};

$tests['oxygen_compatibility_shim_returns_only_runtime_family_names'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $profile = [
        'id' => 'local-self-hosted',
        'label' => 'Local upload',
        'provider' => 'local',
        'type' => 'self_hosted',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Inter',
            'slug' => 'inter',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'files' => ['woff2' => 'upload/inter/inter-400-normal.woff2'],
            'paths' => ['woff2' => 'upload/inter/inter-400-normal.woff2'],
        ]],
    ];

    $services['imports']->saveProfile('Inter', 'inter', $profile, 'published', true);
    $services['imports']->saveProfile('Draft Sans', 'draft-sans', array_replace($profile, [
        'faces' => [[
            'family' => 'Draft Sans',
            'slug' => 'draft-sans',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'files' => ['woff2' => 'upload/draft-sans/draft-sans-400-normal.woff2'],
            'paths' => ['woff2' => 'upload/draft-sans/draft-sans-400-normal.woff2'],
        ]],
    ]), 'library_only', true);

    $services['runtime']->registerOxygenCompatibilityShim();

    assertTrueValue(class_exists('ECF_Plugin', false), 'Registering the Oxygen integration should define the compatibility shim when Oxygen support is enabled.');
    assertSameValue(['Inter'], \ECF_Plugin::get_font_families(), 'The Oxygen compatibility shim should expose published runtime Tasty Fonts families only.');
};

// ---------------------------------------------------------------------------
// AssetService::getVersionedStylesheetUrl
// ---------------------------------------------------------------------------

$tests['asset_service_get_versioned_stylesheet_url_includes_hash_version_when_file_exists'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['assets']->ensureGeneratedCssFile();

    $url = $services['assets']->getVersionedStylesheetUrl();

    assertSameValue(false, $url === null, 'getVersionedStylesheetUrl() should return a non-null URL after the file is created.');
    assertContainsValue('ver=', (string) $url, 'getVersionedStylesheetUrl() should append a ver= query string.');
    assertNotContainsValue('ver=', str_replace('ver=', '', (string) $url), 'getVersionedStylesheetUrl() should append exactly one ver= parameter.');
    assertNotContainsValue(TASTY_FONTS_VERSION, (string) $url, 'getVersionedStylesheetUrl() should use the file hash, not the plugin version string, when the file exists.');
};

$tests['asset_service_get_versioned_stylesheet_url_falls_back_to_plugin_version_when_file_is_missing'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();

    $url = $services['assets']->getVersionedStylesheetUrl();

    assertSameValue(false, $url === null, 'getVersionedStylesheetUrl() should return a versioned URL even before the generated CSS file is written.');
    assertContainsValue(TASTY_FONTS_VERSION, (string) $url, 'getVersionedStylesheetUrl() should fall back to the plugin version string when no generated file exists.');
};

// ---------------------------------------------------------------------------
// AssetService::getPrimaryFontPreloadUrls (direct)
// ---------------------------------------------------------------------------

$tests['asset_service_get_primary_font_preload_urls_returns_woff2_urls_for_applied_roles'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('lora/Lora-400.woff2'), 'font-data');
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-400.woff2'], 'paths' => ['woff2' => 'inter/Inter-400.woff2']],
            ],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Lora',
        'lora',
        [
            'id' => 'local-self_hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Lora', 'slug' => 'lora', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'lora/Lora-400.woff2'], 'paths' => ['woff2' => 'lora/Lora-400.woff2']],
            ],
        ],
        'published',
        true
    );
    $services['settings']->saveAppliedRoles(
        ['heading' => 'Inter', 'body' => 'Lora', 'heading_fallback' => 'sans-serif', 'body_fallback' => 'serif'],
        ['Inter', 'Lora']
    );
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings(['preload_primary_fonts' => '1']);

    $urls = $services['assets']->getPrimaryFontPreloadUrls();

    assertSameValue(2, count($urls), 'getPrimaryFontPreloadUrls() should return one URL per applied role when both are resolved.');
    assertContainsValue('Inter-400.woff2', implode(' ', $urls), 'getPrimaryFontPreloadUrls() should include the heading font WOFF2 URL.');
    assertContainsValue('Lora-400.woff2', implode(' ', $urls), 'getPrimaryFontPreloadUrls() should include the body font WOFF2 URL.');
};

// ---------------------------------------------------------------------------
// RuntimeAssetPlanner::getPreconnectOrigins
// ---------------------------------------------------------------------------

$tests['runtime_asset_planner_get_preconnect_origins_returns_empty_when_setting_is_off'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['remote_connection_hints' => '0']);

    assertSameValue([], $services['planner']->getPreconnectOrigins(), 'getPreconnectOrigins() should return an empty array when the remote_connection_hints setting is disabled.');
};

$tests['runtime_asset_planner_get_preconnect_origins_returns_google_origin_for_cdn_delivery'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['remote_connection_hints' => '1']);
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-cdn',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'google', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => ''], 'paths' => []],
            ],
        ],
        'published',
        true
    );

    $origins = $services['planner']->getPreconnectOrigins();

    assertContainsValue('https://fonts.googleapis.com', implode(' ', $origins), 'getPreconnectOrigins() should return the Google origin for CDN-delivered Google fonts.');
};

$tests['runtime_asset_planner_get_preconnect_origins_returns_bunny_origin_for_cdn_delivery'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['remote_connection_hints' => '1']);
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'bunny-cdn',
            'provider' => 'bunny',
            'type' => 'cdn',
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'bunny', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => ''], 'paths' => []],
            ],
        ],
        'published',
        true
    );

    $origins = $services['planner']->getPreconnectOrigins();

    assertContainsValue('https://fonts.bunny.net', implode(' ', $origins), 'getPreconnectOrigins() should return the Bunny origin for CDN-delivered Bunny fonts.');
};

$tests['runtime_asset_planner_get_preconnect_origins_returns_empty_for_self_hosted_delivery'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['remote_connection_hints' => '1']);
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-400.woff2'], 'paths' => ['woff2' => 'inter/Inter-400.woff2']],
            ],
        ],
        'published',
        true
    );

    $origins = $services['planner']->getPreconnectOrigins();

    assertSameValue([], $origins, 'getPreconnectOrigins() should return an empty array for self-hosted deliveries (no external connection needed).');
};

// ---------------------------------------------------------------------------
// RuntimeService::enqueueAdminScreenFonts
// ---------------------------------------------------------------------------

$tests['runtime_service_enqueue_admin_screen_fonts_enqueues_when_hook_suffix_matches_plugin_page'] = static function (): void {
    resetTestState();

    global $registeredStyles;

    $services = makeServiceGraph();
    $services['runtime']->enqueueAdminScreenFonts('toplevel_page_' . AdminController::MENU_SLUG);

    $enqueuedHandles = array_keys($registeredStyles);

    assertTrueValue(
        in_array('tasty-fonts-admin-fonts', $enqueuedHandles, true),
        'enqueueAdminScreenFonts() should register the admin preview fonts stylesheet when called with a plugin admin page hook suffix.'
    );
};

$tests['runtime_service_enqueue_admin_screen_fonts_skips_non_plugin_pages'] = static function (): void {
    resetTestState();

    global $registeredStyles;

    $services = makeServiceGraph();
    $services['runtime']->enqueueAdminScreenFonts('edit.php');

    assertFalseValue(
        in_array('tasty-fonts-admin-fonts', array_keys($registeredStyles), true),
        'enqueueAdminScreenFonts() should skip font registration for non-plugin admin pages.'
    );
};

// ---------------------------------------------------------------------------
// RuntimeService::injectEditorFontPresets
// ---------------------------------------------------------------------------

$tests['runtime_service_inject_editor_font_presets_adds_families_for_live_roles'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-400.woff2'], 'paths' => ['woff2' => 'inter/Inter-400.woff2']],
            ],
        ],
        'published',
        true
    );

    $themeJson = new WP_Theme_JSON_Data(['version' => 3]);
    $result = $services['runtime']->injectEditorFontPresets($themeJson);
    $data = $result->get_data();

    assertTrueValue(
        !empty($data['settings']['typography']['fontFamilies']),
        'injectEditorFontPresets() should add fontFamilies to the theme JSON typography settings when managed fonts are available.'
    );

    $injectedNames = array_map(static fn (array $f): string => (string) ($f['name'] ?? ''), $data['settings']['typography']['fontFamilies']);
    assertContainsValue('Inter', implode(' ', $injectedNames), 'injectEditorFontPresets() should inject the published font family name.');
};

$tests['runtime_service_inject_editor_font_presets_returns_unchanged_when_no_families_available'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();

    $original = ['version' => 3, 'settings' => []];
    $themeJson = new WP_Theme_JSON_Data($original);
    $result = $services['runtime']->injectEditorFontPresets($themeJson);

    assertSameValue($original, $result->get_data(), 'injectEditorFontPresets() should return the theme JSON unchanged when no managed font families are published.');
};
