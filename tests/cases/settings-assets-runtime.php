<?php

declare(strict_types=1);

use TastyFonts\Admin\AdminPageContextBuilder;
use TastyFonts\Admin\AdminController;
use TastyFonts\Admin\AdminPageViewBuilder;
use TastyFonts\Adobe\AdobeCssParser;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Fonts\FontFilenameParser;
use TastyFonts\Fonts\RuntimeAssetPlanner;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Integrations\AcssIntegrationService;
use TastyFonts\Integrations\BricksIntegrationService;
use TastyFonts\Maintenance\HealthCheckService;
use TastyFonts\Maintenance\SiteTransferService;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use TastyFonts\Support\TransientKey;

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
        false,
        array_key_exists('google_api_key', (array) ($optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA] ?? [])),
        'The dedicated Google API key option should not store the plaintext key once encryption at rest is available.'
    );
    assertTrueValue(
        is_string($optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA]['google_api_key_encrypted'] ?? null)
        && $optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA]['google_api_key_encrypted'] !== '',
        'Google API key data should store an encrypted ciphertext in its dedicated option row.'
    );
    assertSameValue(
        'unknown',
        (string) ($optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA]['google_api_key_status'] ?? ''),
        'Google API key encryption should preserve the validation state alongside the ciphertext.'
    );
    assertSameValue(
        '',
        (string) ($optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA]['google_api_key_status_message'] ?? ''),
        'Google API key encryption should preserve the status message alongside the ciphertext.'
    );
    assertSameValue(
        0,
        (int) ($optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA]['google_api_key_checked_at'] ?? -1),
        'Google API key encryption should preserve the checked-at timestamp alongside the ciphertext.'
    );
    assertSameValue(
        false,
        str_contains((string) ($optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA]['google_api_key_encrypted'] ?? ''), 'live-key'),
        'Google API key ciphertext should not contain the plaintext key.'
    );
    assertSameValue(
        false,
        $optionAutoload[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA] ?? null,
        'The dedicated Google API key option should be saved with autoload disabled.'
    );
    assertSameValue('live-key', $settings->getSettings()['google_api_key'] ?? '', 'Google API key reads should transparently decrypt the stored ciphertext.');
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
    assertSameValue(
        false,
        array_key_exists('google_api_key', (array) ($optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA] ?? [])),
        'Updating Google API key validation state should store existing plaintext key material as ciphertext.'
    );
    assertTrueValue(
        is_string($optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA]['google_api_key_encrypted'] ?? null)
        && $optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA]['google_api_key_encrypted'] !== '',
        'Updating Google API key validation state should preserve the API key in encrypted dedicated storage.'
    );
};

$tests['settings_repository_encrypts_plaintext_google_key_option_rows'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA] = [
        'google_api_key' => 'live-key',
        'google_api_key_status' => 'valid',
        'google_api_key_status_message' => 'Ready',
        'google_api_key_checked_at' => 123,
    ];

    $settings = new SettingsRepository();
    $loaded = $settings->getSettings();

    assertSameValue('live-key', (string) ($loaded['google_api_key'] ?? ''), 'Reading Google API key settings should transparently decrypt stored key material.');
    assertSameValue(
        false,
        array_key_exists('google_api_key', (array) ($optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA] ?? [])),
        'Reading plaintext Google API key option rows should rewrite them without the plaintext key.'
    );
    assertTrueValue(
        is_string($optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA]['google_api_key_encrypted'] ?? null)
        && $optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA]['google_api_key_encrypted'] !== '',
        'Reading plaintext Google API key option rows should store them as encrypted data.'
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

    $saved = $settings->saveAcssFontRoleSyncState(true, true, 'Inter, sans-serif', 'system-ui, sans-serif', '700', '400');

    assertSameValue(true, $saved['acss_font_role_sync_applied'], 'Automatic.css sync state should record when the managed ACSS values are currently applied.');
    assertSameValue('Inter, sans-serif', $saved['acss_font_role_sync_previous_heading_font_family'], 'Automatic.css sync state should preserve the previous heading font-family value for later restore.');
    assertSameValue('system-ui, sans-serif', $saved['acss_font_role_sync_previous_text_font_family'], 'Automatic.css sync state should preserve the previous text font-family value for later restore.');
    assertSameValue('700', $saved['acss_font_role_sync_previous_heading_font_weight'], 'Automatic.css sync state should preserve the previous heading font-weight value for later restore.');
    assertSameValue('400', $saved['acss_font_role_sync_previous_text_font_weight'], 'Automatic.css sync state should preserve the previous text font-weight value for later restore.');
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

$tests['settings_repository_normalizes_preview_sentence_before_saving'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $saved = $settings->saveSettings([
        'preview_sentence' => "<b>Hello</b>\n\tworld\x07 " . str_repeat('A', 400),
    ]);
    $previewSentence = (string) ($saved['preview_sentence'] ?? '');

    assertNotContainsValue('<b>', $previewSentence, 'Preview sentence saving should strip HTML tags before storage.');
    assertNotContainsValue("\n", $previewSentence, 'Preview sentence saving should normalize multiline content to a single line.');
    assertNotContainsValue("\x07", $previewSentence, 'Preview sentence saving should remove non-printable control characters.');
    assertSameValue(280, strlen($previewSentence), 'Preview sentence saving should cap the stored value length.');
    assertContainsValue('Hello world ', $previewSentence, 'Preview sentence saving should preserve readable text with normalized spacing.');
};

$tests['settings_repository_normalizes_stored_preview_sentence_values_on_read'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'preview_sentence' => "<script>alert(1)</script>\r\nPreview\x00 text",
    ];

    $settings = new SettingsRepository();
    $loaded = $settings->getSettings();

    assertSameValue(
        'alert(1) Preview text',
        (string) ($loaded['preview_sentence'] ?? ''),
        'Reading stored preview text should normalize values into printable single-line content.'
    );
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
        'google_font_imports_enabled' => '0',
        'bunny_font_imports_enabled' => '0',
        'local_font_uploads_enabled' => '0',
        'adobe_font_imports_enabled' => '1',
        'custom_css_url_imports_enabled' => '1',
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
    assertSameValue('swap', (string) ($result['font_display'] ?? ''), 'Reset plugin settings should restore the default font-display.');
    assertSameValue(false, !empty($result['training_wheels_off']), 'Reset plugin settings should restore behavior toggles to their defaults.');
    assertSameValue(false, !empty($result['google_font_imports_enabled']), 'Reset plugin settings should restore Google Fonts imports to the default-off workflow.');
    assertSameValue(false, !empty($result['bunny_font_imports_enabled']), 'Reset plugin settings should restore Bunny Fonts imports to the default-off workflow.');
    assertSameValue(false, !empty($result['local_font_uploads_enabled']), 'Reset plugin settings should restore custom uploads to the default-off workflow.');
    assertSameValue(false, !empty($result['adobe_font_imports_enabled']), 'Reset plugin settings should restore Adobe imports to the default-off workflow.');
    assertSameValue(false, !empty($result['custom_css_url_imports_enabled']), 'Reset plugin settings should restore URL imports to the default-off workflow.');
    assertSameValue(true, !empty($result['delete_uploaded_files_on_uninstall']), 'Reset plugin settings should restore the keep-uploaded-fonts toggle to off.');
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
    assertSameValue(true, file_exists((string) $services['storage']->pathForRelativePath('.htaccess')), 'Wiping the managed library should restore the root .htaccess hardening stub.');
    assertSameValue(true, file_exists((string) $services['storage']->pathForRelativePath('google/.htaccess')), 'Wiping the managed library should restore the Google .htaccess hardening stub.');
    assertSameValue(true, file_exists((string) $services['storage']->pathForRelativePath('bunny/.htaccess')), 'Wiping the managed library should restore the Bunny .htaccess hardening stub.');
    assertSameValue(true, file_exists((string) $services['storage']->pathForRelativePath('upload/.htaccess')), 'Wiping the managed library should restore the uploads .htaccess hardening stub.');
    assertSameValue(true, file_exists((string) $services['storage']->pathForRelativePath('adobe/.htaccess')), 'Wiping the managed library should restore the Adobe .htaccess hardening stub.');
    assertSameValue(true, file_exists((string) $services['storage']->pathForRelativePath('.generated/.htaccess')), 'Wiping the managed library should restore the generated-assets .htaccess hardening stub.');
    assertContainsValue(
        'Options -Indexes',
        (string) file_get_contents((string) $services['storage']->pathForRelativePath('.htaccess')),
        'Wiping the managed library should restore Apache directory-listing hardening in the font storage root.'
    );
    assertContainsValue(
        'Require all denied',
        (string) file_get_contents((string) $services['storage']->pathForRelativePath('.htaccess')),
        'Wiping the managed library should restore Apache PHP-request blocking in the font storage root.'
    );
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
        TransientKey::forSite(CatalogService::TRANSIENT_CATALOG) => ['cached'],
        TransientKey::forSite(AssetService::TRANSIENT_CSS) => 'body{}',
        TransientKey::forSite(AssetService::TRANSIENT_HASH) => 'hash',
        TransientKey::forSite(AssetService::TRANSIENT_REGENERATE_CSS_QUEUED) => true,
        TransientKey::forSite(GoogleFontsClient::TRANSIENT_CATALOG) => ['Inter'],
        TransientKey::forSite(GoogleFontsClient::TRANSIENT_METADATA) => ['inter' => ['family' => 'Inter', 'axes' => []]],
        TransientKey::forSite(BunnyFontsClient::TRANSIENT_CATALOG) => ['Inter'],
        TransientKey::forSite('tasty_fonts_github_release_manifest_v1') => ['latest_for_channel' => ['stable' => ['version' => '1.0.0']]],
        TransientKey::forSite('tasty_fonts_github_release_version_v1') => '1.0.0',
        TransientKey::forSite(BunnyFontsClient::TRANSIENT_FAMILY_PREFIX . 'abc') => ['family' => 'Inter'],
        TransientKey::forSite(AdminController::SEARCH_CACHE_TRANSIENT_PREFIX . 'google_inter') => ['Inter'],
        TransientKey::forSite(AdminController::SEARCH_COOLDOWN_TRANSIENT_PREFIX . 'google_inter') => 1,
        TransientKey::forSite(AdobeProjectClient::TRANSIENT_PREFIX . md5('abc123')) => ['families' => ['Inter']],
    ];

    $result = $services['developer_tools']->clearPluginCachesAndRegenerateAssets();

    assertTrueValue($result, 'Clearing plugin caches should report success.');
    assertSameValue(true, in_array(TransientKey::forSite(CatalogService::TRANSIENT_CATALOG), $transientDeleted, true), 'Clearing plugin caches should invalidate the catalog transient.');
    assertSameValue(true, in_array(TransientKey::forSite(AssetService::TRANSIENT_CSS), $transientDeleted, true), 'Clearing plugin caches should invalidate the CSS transient.');
    assertSameValue(true, in_array(TransientKey::forSite(AssetService::TRANSIENT_HASH), $transientDeleted, true), 'Clearing plugin caches should invalidate the CSS hash transient.');
    assertSameValue(true, in_array(TransientKey::forSite(GoogleFontsClient::TRANSIENT_CATALOG), $transientDeleted, true), 'Clearing plugin caches should invalidate the Google catalog transient.');
    assertSameValue(true, in_array(TransientKey::forSite(GoogleFontsClient::TRANSIENT_METADATA), $transientDeleted, true), 'Clearing plugin caches should invalidate the Google metadata transient.');
    assertSameValue(true, in_array(TransientKey::forSite(BunnyFontsClient::TRANSIENT_CATALOG), $transientDeleted, true), 'Clearing plugin caches should invalidate the Bunny catalog transient.');
    assertSameValue(true, in_array(TransientKey::forSite(AdobeProjectClient::TRANSIENT_PREFIX . md5('abc123')), $transientDeleted, true), 'Clearing plugin caches should invalidate the saved Adobe project transient.');
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
    $transientStore[TransientKey::forSite(GoogleFontsClient::TRANSIENT_CATALOG)] = ['Inter'];
    $transientStore[TransientKey::forSite(BunnyFontsClient::TRANSIENT_CATALOG)] = ['Inter'];

    $result = $services['developer_tools']->regenerateCss();
    $afterCss = is_readable($generatedPath) ? (string) file_get_contents($generatedPath) : '';

    assertTrueValue($result, 'Regenerating CSS should report success.');
    assertSameValue(true, in_array(AssetService::ACTION_REGENERATE_CSS, $clearedScheduledHooks, true), 'Regenerating CSS should clear queued CSS regeneration hooks before rebuilding.');
    assertSameValue(false, in_array(TransientKey::forSite(GoogleFontsClient::TRANSIENT_CATALOG), $transientDeleted, true), 'Regenerating CSS should not clear the Google catalog cache.');
    assertSameValue(false, in_array(TransientKey::forSite(BunnyFontsClient::TRANSIENT_CATALOG), $transientDeleted, true), 'Regenerating CSS should not clear the Bunny catalog cache.');
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
        'etch_integration_enabled' => '0',
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
    assertSameValue(null, $settings['etch_integration_enabled'], 'Integration reset should clear Etch detection state.');
    assertSameValue(null, $settings['bricks_integration_enabled'], 'Integration reset should clear Bricks detection state.');
    assertSameValue(null, $settings['oxygen_integration_enabled'], 'Integration reset should clear Oxygen detection state.');
    assertSameValue(null, $settings['acss_font_role_sync_enabled'], 'Integration reset should clear Automatic.css detection state.');
    assertSameValue(false, !empty($settings['acss_font_role_sync_applied']), 'Integration reset should clear the applied Automatic.css sync flag.');
    assertSameValue('', (string) ($settings['acss_font_role_sync_previous_heading_font_family'] ?? ''), 'Integration reset should clear Automatic.css heading backups.');
    assertSameValue('', (string) ($settings['acss_font_role_sync_previous_text_font_family'] ?? ''), 'Integration reset should clear Automatic.css body backups.');
    assertSameValue('', (string) ($settings['acss_font_role_sync_previous_heading_font_weight'] ?? ''), 'Integration reset should clear Automatic.css heading weight backups.');
    assertSameValue('', (string) ($settings['acss_font_role_sync_previous_text_font_weight'] ?? ''), 'Integration reset should clear Automatic.css body weight backups.');
    assertSameValue(1, did_action('tasty_fonts_before_reset_integration_detection'), 'Integration reset should emit a before hook.');
    assertSameValue(1, did_action('tasty_fonts_after_reset_integration_detection'), 'Integration reset should emit an after hook.');

    $services['developer_tools']->resetSuppressedNotices();

    assertSameValue(false, array_key_exists(AdminController::LOCAL_ENV_NOTICE_OPTION, $optionStore), 'Reset suppressed notices should clear saved notice preferences.');
    assertSameValue(1, did_action('tasty_fonts_before_reset_suppressed_notices'), 'Reset suppressed notices should emit a before hook.');
    assertSameValue(1, did_action('tasty_fonts_after_reset_suppressed_notices'), 'Reset suppressed notices should emit an after hook.');
};

$tests['site_transfer_service_exports_a_portable_bundle_without_secrets_or_generated_css'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('upload/inter/inter-400-normal.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->getGeneratedCssPath(), 'body{font-family:Inter;}');
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
        'google_api_key' => 'live-key',
        'acss_font_role_sync_enabled' => '1',
    ]);
    $services['settings']->saveAppliedRoles(['heading' => 'Inter', 'body' => 'Inter'], []);
    $services['settings']->saveRoles(['heading' => 'Inter', 'body' => 'Inter'], []);
    $services['settings']->saveAdobeProject('abc123', true);

    $bundle = $services['site_transfer']->buildExportBundle();

    assertFalseValue(is_wp_error($bundle), 'Building a site transfer bundle should succeed.');
    assertSameValue(true, is_readable((string) ($bundle['path'] ?? '')), 'Building a site transfer bundle should create a readable zip file.');

    $zip = new ZipArchive();
    assertSameValue(true, $zip->open((string) $bundle['path']) === true, 'The exported bundle should be a readable zip archive.');

    $manifestJson = (string) $zip->getFromName('tasty-fonts-export.json');
    $fontPayload = (string) $zip->getFromName('fonts/upload/inter/inter-400-normal.woff2');
    $generatedCssPayload = $zip->getFromName('fonts/.generated/tasty-fonts.css');
    $zip->close();

    $manifest = json_decode($manifestJson, true);

    assertSameValue(true, is_array($manifest), 'The exported bundle should contain a JSON manifest.');
    assertSameValue(SiteTransferService::SCHEMA_VERSION, (int) ($manifest['schema_version'] ?? 0), 'The exported bundle should stamp the current schema version.');
    assertSameValue(false, isset($manifest['settings']['google_api_key']), 'The exported bundle should omit Google API secrets from the settings snapshot.');
    assertSameValue(false, isset($manifest['settings']['applied_roles']), 'The exported bundle should store applied roles outside the main settings snapshot.');
    assertSameValue(false, !empty($manifest['settings']['acss_font_role_sync_applied']), 'The exported bundle should clear non-portable ACSS applied state.');
    assertSameValue('', (string) ($manifest['settings']['acss_font_role_sync_previous_heading_font_family'] ?? ''), 'The exported bundle should clear non-portable ACSS restore state.');
    assertSameValue('unknown', (string) ($manifest['settings']['adobe_project_status'] ?? ''), 'The exported bundle should reset Adobe project status for destination revalidation.');
    assertSameValue('Inter', (string) ($manifest['roles']['heading'] ?? ''), 'The exported bundle should include saved role drafts.');
    assertSameValue('Inter', (string) ($manifest['applied_roles']['heading'] ?? ''), 'The exported bundle should include applied live roles.');
    assertSameValue('font-data', $fontPayload, 'The exported bundle should include managed font files under the fonts payload directory.');
    assertSameValue(false, is_string($generatedCssPayload), 'The exported bundle should exclude generated CSS from the fonts payload.');
    assertSameValue('google_api_key', (string) ($manifest['secret_requirements'][0]['key'] ?? ''), 'The exported bundle should describe fresh secret inputs required at import time.');
    assertSameValue(true, in_array('upload/inter/inter-400-normal.woff2', array_column((array) ($manifest['files'] ?? []), 'relative_path'), true), 'The exported bundle manifest should list the managed font files that were included.');

    @unlink((string) ($bundle['path'] ?? ''));
};

$tests['site_transfer_service_retains_recent_export_bundles_and_respects_protection'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('upload/inter/inter-400-normal.woff2'), 'font-data');
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
    $services['settings']->saveAppliedRoles(['heading' => 'Inter', 'body' => 'Inter'], []);

    for ($index = 0; $index < 6; $index++) {
        $bundle = $services['site_transfer']->buildExportBundle(true);

        assertFalseValue(is_wp_error($bundle), 'Retained export bundle creation should succeed.');
        assertSameValue(true, is_readable((string) ($bundle['path'] ?? '')), 'Retained export bundles should remain readable after creation.');
    }

    $retained = $services['site_transfer']->listExportBundles();

    assertSameValue(5, count($retained), 'Export history should keep the latest five unprotected bundles.');

    $protectedId = (string) ($retained[4]['id'] ?? '');
    $protectResult = $services['site_transfer']->setExportBundleProtected($protectedId, true);

    assertFalseValue(is_wp_error($protectResult), 'A retained export bundle should be protectable.');

    $extraBundle = $services['site_transfer']->buildExportBundle(true);

    assertFalseValue(is_wp_error($extraBundle), 'Creating another retained export should still succeed after protecting an older export.');

    $afterProtect = $services['site_transfer']->listExportBundles();
    $afterProtectIds = array_column($afterProtect, 'id');
    $protectedRows = array_values(array_filter($afterProtect, static fn (array $bundle): bool => !empty($bundle['protected'])));
    $unprotectedRows = array_values(array_filter($afterProtect, static fn (array $bundle): bool => empty($bundle['protected'])));

    assertSameValue(true, in_array($protectedId, $afterProtectIds, true), 'Protected export bundles should not be pruned by the five-bundle retention limit.');
    assertSameValue(1, count($protectedRows), 'Only the explicitly protected export should carry the protected state.');
    assertSameValue(5, count($unprotectedRows), 'The retained export limit should apply to unprotected bundles.');
    assertSameValue(['Inter'], (array) ($afterProtect[0]['family_names'] ?? []), 'Retained export metadata should include captured family names.');
    assertSameValue(['Inter'], (array) ($afterProtect[0]['role_families'] ?? []), 'Retained export metadata should include captured live role families.');

    $renameResult = $services['site_transfer']->renameExportBundle($protectedId, 'Before rename');
    $renamedRows = $services['site_transfer']->listExportBundles();
    $renamedIndex = array_search($protectedId, array_column($renamedRows, 'id'), true);

    assertFalseValue(is_wp_error($renameResult), 'Protected export bundles should still be renameable.');
    assertSameValue(
        'Before rename',
        (string) (is_int($renamedIndex) ? ($renamedRows[$renamedIndex]['label'] ?? '') : ''),
        'Renaming should persist the saved export bundle label.'
    );

    $deleteProtected = $services['site_transfer']->deleteExportBundle($protectedId);

    assertSameValue(true, is_wp_error($deleteProtected), 'Protected export bundles should reject deletion.');
    assertSameValue('tasty_fonts_transfer_export_protected', $deleteProtected instanceof WP_Error ? $deleteProtected->get_error_code() : '', 'Protected export bundle deletion should use the dedicated locked-export error code.');

    $bulkDeleteProtected = $services['site_transfer']->deleteAllExportBundlesUnlessProtected();

    assertSameValue(true, is_wp_error($bulkDeleteProtected), 'Bulk export deletion should reject protected export bundles.');
    assertSameValue('tasty_fonts_transfer_export_bulk_delete_blocked', $bulkDeleteProtected instanceof WP_Error ? $bulkDeleteProtected->get_error_code() : '', 'Bulk export deletion should use a specific locked-export error code.');
    assertSameValue(count($afterProtect), count($services['site_transfer']->listExportBundles()), 'Blocked bulk export deletion should preserve retained export history.');

    $unprotectResult = $services['site_transfer']->setExportBundleProtected($protectedId, false);
    $deleteResult = $services['site_transfer']->deleteExportBundle($protectedId);

    assertFalseValue(is_wp_error($unprotectResult), 'Protected export bundles should be unprotectable.');
    assertFalseValue(is_wp_error($deleteResult), 'Unprotected export bundles should be deletable.');
    assertSameValue(false, in_array($protectedId, array_column($services['site_transfer']->listExportBundles(), 'id'), true), 'Deleted export bundles should be removed from history.');

    $bulkDeleteResult = $services['site_transfer']->deleteAllExportBundlesUnlessProtected();

    assertFalseValue(is_wp_error($bulkDeleteResult), 'Bulk export deletion should succeed after all exports are unprotected.');
    assertSameValue([], $services['site_transfer']->listExportBundles(), 'Successful bulk export deletion should clear retained export history.');
};

$tests['admin_controller_delete_plugin_managed_files_clears_storage_exports_and_snapshots'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();
    $fontPath = (string) $services['storage']->pathForRelativePath('upload/inter/inter-400-normal.woff2');
    $services['storage']->writeAbsoluteFile($fontPath, 'font-data');
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
    $services['settings']->saveSettings(['google_api_key' => 'preserve-key']);

    $export = $services['site_transfer']->buildExportBundle(true);
    $snapshot = $services['snapshots']->createSnapshot('manual');

    assertFalseValue(is_wp_error($export), 'A retained export bundle should be created before managed-file cleanup.');
    assertFalseValue(is_wp_error($snapshot), 'A rollback snapshot should be created before managed-file cleanup.');
    assertSameValue(1, count($services['site_transfer']->listExportBundles()), 'The export bundle should be listed before cleanup.');
    assertSameValue(1, count($services['snapshots']->listSnapshots()), 'The rollback snapshot should be listed before cleanup.');

    $result = $services['controller']->deletePluginManagedFiles();

    assertFalseValue(is_wp_error($result), 'Deleting all plugin-managed files through the controller should succeed.');
    assertSameValue(false, file_exists($fontPath), 'Managed font files should be deleted.');
    assertSameValue(null, $services['imports']->getFamily('inter'), 'Managed-file cleanup should clear font library metadata to avoid broken file references.');
    assertSameValue(true, is_readable((string) $services['storage']->pathForRelativePath('index.php')), 'Managed-file cleanup should recreate the root storage scaffold.');
    assertSameValue(true, is_readable((string) $services['storage']->pathForRelativePath('.generated/index.php')), 'Managed-file cleanup should recreate the generated CSS scaffold.');
    assertSameValue(false, file_exists((string) $services['storage']->getGeneratedCssPath()), 'Managed-file cleanup should delete generated CSS output.');
    assertSameValue([], $services['site_transfer']->listExportBundles(), 'Managed-file cleanup should clear retained transfer export metadata.');
    assertSameValue([], $services['snapshots']->listSnapshots(), 'Managed-file cleanup should clear retained rollback snapshot metadata.');
    assertSameValue([], glob((string) $GLOBALS['uploadBaseDir'] . '/tasty-fonts-export-bundles/*.zip') ?: [], 'Managed-file cleanup should remove retained transfer ZIP files.');
    assertSameValue([], glob((string) $GLOBALS['uploadBaseDir'] . '/tasty-fonts-snapshots/*.zip') ?: [], 'Managed-file cleanup should remove rollback snapshot ZIP files.');
    assertSameValue('preserve-key', (string) ($services['settings']->getSettings()['google_api_key'] ?? ''), 'Managed-file cleanup should preserve unrelated settings such as the Google API key.');
    assertSameValue(1, (int) ($result['deleted_export_bundles'] ?? 0), 'Managed-file cleanup should report deleted export bundle metadata count.');
    assertSameValue(1, (int) ($result['deleted_snapshots'] ?? 0), 'Managed-file cleanup should report deleted snapshot metadata count.');
};

$tests['admin_controller_deletes_all_snapshots_and_blocks_locked_bulk_export_deletes'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();
    $fontPath = (string) $services['storage']->pathForRelativePath('upload/inter/inter-400-normal.woff2');
    $services['storage']->writeAbsoluteFile($fontPath, 'font-data');
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

    $export = $services['site_transfer']->buildExportBundle(true);
    $snapshotOne = $services['snapshots']->createSnapshot('manual');
    $snapshotTwo = $services['snapshots']->createSnapshot('before_reset_settings');

    assertFalseValue(is_wp_error($export), 'A retained export bundle should be created before bulk export delete testing.');
    assertFalseValue(is_wp_error($snapshotOne), 'The first rollback snapshot should be created before bulk snapshot delete testing.');
    assertFalseValue(is_wp_error($snapshotTwo), 'The second rollback snapshot should be created before bulk snapshot delete testing.');

    $exportId = (string) ($services['site_transfer']->listExportBundles()[0]['id'] ?? '');
    $protectResult = $services['site_transfer']->setExportBundleProtected($exportId, true);

    assertFalseValue(is_wp_error($protectResult), 'The retained export should be lockable before testing guarded bulk deletion.');

    $blockedDelete = $services['controller']->deleteAllSiteTransferExportBundles();

    assertSameValue(true, is_wp_error($blockedDelete), 'Controller bulk export deletion should reject locked retained exports.');
    assertSameValue('tasty_fonts_transfer_export_bulk_delete_blocked', $blockedDelete instanceof WP_Error ? $blockedDelete->get_error_code() : '', 'Controller bulk export deletion should surface the locked-export error code.');
    assertSameValue(1, count($services['site_transfer']->listExportBundles()), 'Blocked controller bulk export deletion should preserve export history.');

    $snapshotDelete = $services['controller']->deleteAllRollbackSnapshots();

    assertSameValue('All rollback snapshots deleted.', (string) ($snapshotDelete['message'] ?? ''), 'Controller bulk snapshot deletion should return the shared success message.');
    assertSameValue(2, (int) ($snapshotDelete['deleted_snapshots'] ?? 0), 'Controller bulk snapshot deletion should report deleted snapshot metadata count.');
    assertSameValue([], $services['snapshots']->listSnapshots(), 'Controller bulk snapshot deletion should clear retained snapshots.');

    $unprotect = $services['site_transfer']->setExportBundleProtected($exportId, false);
    $exportDelete = $services['controller']->deleteAllSiteTransferExportBundles();

    assertFalseValue(is_wp_error($unprotect), 'The retained export should be unlockable before successful bulk deletion.');
    assertFalseValue(is_wp_error($exportDelete), 'Controller bulk export deletion should succeed after exports are unlocked.');
    assertSameValue('All site transfer export bundles deleted.', (string) ($exportDelete['message'] ?? ''), 'Controller bulk export deletion should return the shared success message.');
    assertSameValue(1, (int) ($exportDelete['deleted_export_bundles'] ?? 0), 'Controller bulk export deletion should report deleted export metadata count.');
    assertSameValue([], $services['site_transfer']->listExportBundles(), 'Controller bulk export deletion should clear retained export history when nothing is locked.');
};

$tests['admin_controller_can_delete_all_activity_history'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    update_option(LogRepository::OPTION_LOG, [
        [
            'time' => '2026-04-29 12:00:00',
            'message' => 'Fonts rescanned.',
            'actor' => 'Builder',
        ],
        [
            'time' => '2026-04-29 12:05:00',
            'message' => 'Generated CSS regenerated.',
            'actor' => 'Builder',
        ],
    ]);

    $result = $services['controller']->deleteAllHistory();

    assertSameValue('Activity history deleted.', (string) ($result['message'] ?? ''), 'Controller history deletion should return the shared success message.');
    assertSameValue(2, (int) ($result['deleted_history_entries'] ?? 0), 'Controller history deletion should report deleted activity entries.');
    assertSameValue([], $services['log']->all(), 'Controller history deletion should leave no retained activity log entries.');
};

$tests['site_transfer_service_rejects_checksum_mismatches_during_bundle_validation'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('upload/inter/inter-400-normal.woff2'), 'font-data');
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

    $bundle = $services['site_transfer']->buildExportBundle();
    $zip = new ZipArchive();
    $zip->open((string) $bundle['path']);
    $zip->addFromString('fonts/upload/inter/inter-400-normal.woff2', 'tampered-font-data');
    $zip->close();

    $validation = $services['site_transfer']->validateImportBundle((string) $bundle['path']);

    assertTrueValue(is_wp_error($validation), 'Bundle validation should fail when a managed file checksum no longer matches the manifest.');
    assertSameValue('tasty_fonts_transfer_checksum_mismatch', $validation->get_error_code(), 'Checksum validation failures should use the dedicated transfer error code.');

    @unlink((string) ($bundle['path'] ?? ''));
};

$tests['site_transfer_service_import_replaces_existing_state_and_accepts_a_fresh_google_api_key'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;
    global $remoteRequestResponses;
    global $remoteGetCalls;
    global $remoteRequestCalls;
    global $uploadedFilePaths, $remoteGetResponses;

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();
    $sourceFile = (string) $services['storage']->pathForRelativePath('upload/inter/inter-400-normal.woff2');
    $services['storage']->writeAbsoluteFile($sourceFile, 'source-font-data');
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
        'block_editor_font_library_sync_enabled' => '1',
        'google_api_key' => 'source-secret',
        'acss_font_role_sync_enabled' => '1',
    ]);
    $services['settings']->saveRoles(['heading' => 'Inter', 'body' => 'Inter'], []);
    $services['settings']->saveAppliedRoles(['heading' => 'Inter', 'body' => 'Inter'], []);
    $services['settings']->setAutoApplyRoles(true);

    $bundle = $services['site_transfer']->buildExportBundle();
    assertFalseValue(is_wp_error($bundle), 'Building the source bundle for import testing should succeed.');

    $existingFile = (string) $services['storage']->pathForRelativePath('upload/existing/existing-400-normal.woff2');
    $services['storage']->writeAbsoluteFile($existingFile, 'existing-font-data');
    $services['imports']->saveProfile(
        'Existing Sans',
        'existing-sans',
        [
            'id' => 'existing-upload',
            'label' => 'Local Upload',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                [
                    'family' => 'Existing Sans',
                    'slug' => 'existing-sans',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'files' => ['woff2' => 'upload/existing/existing-400-normal.woff2'],
                    'paths' => ['woff2' => 'upload/existing/existing-400-normal.woff2'],
                ],
            ],
        ],
        'published',
        true
    );
    $services['settings']->saveSettings(['google_api_key' => 'old-secret']);
    $services['log']->add('Old log entry.');

    $baseUrl = 'https://example.test/wp-json/wp/v2/font-families';
    $findUrl = $baseUrl . '?slug=tasty-fonts-inter&context=edit';
    $remoteGetResponses[$findUrl] = [
        'response' => ['code' => 200],
        'body' => wp_json_encode([]),
    ];
    $remoteRequestResponses['POST ' . $baseUrl] = [
        'response' => ['code' => 201],
        'body' => wp_json_encode(['id' => 321]),
    ];
    $remoteRequestResponses['POST ' . $baseUrl . '/321/font-faces'] = [
        'response' => ['code' => 201],
        'body' => wp_json_encode(['id' => 654]),
    ];

    $uploadedFilePaths[] = (string) $bundle['path'];
    $result = $services['site_transfer']->importBundleReplacingCurrentState(
        [
            'name' => 'tasty-fonts-transfer.zip',
            'tmp_name' => (string) $bundle['path'],
            'error' => UPLOAD_ERR_OK,
            'size' => (int) filesize((string) $bundle['path']),
        ],
        'fresh-destination-key'
    );

    assertFalseValue(is_wp_error($result), 'Importing a valid site transfer bundle should succeed.');
    assertSameValue(false, file_exists($existingFile), 'Importing a site transfer bundle should remove previously managed font files that are not in the bundle.');
    assertSameValue('source-font-data', (string) file_get_contents($sourceFile), 'Importing a site transfer bundle should restore bundled managed font files.');
    assertSameValue(null, $services['imports']->getFamily('existing-sans'), 'Importing a site transfer bundle should replace the existing library instead of merging.');
    assertSameValue('Inter', (string) ($services['settings']->getRoles([])['heading'] ?? ''), 'Importing a site transfer bundle should restore saved role drafts.');
    assertSameValue('Inter', (string) ($services['settings']->getAppliedRoles([])['heading'] ?? ''), 'Importing a site transfer bundle should restore applied live roles.');
    assertSameValue('fresh-destination-key', (string) ($services['settings']->getSettings()['google_api_key'] ?? ''), 'Importing a site transfer bundle should accept a fresh Google API key for the destination site.');
    assertSameValue([], $services['log']->all(), 'Importing a site transfer bundle directly should clear the previous activity log before the controller adds any new import summary entry.');
    assertSameValue(true, is_readable((string) $services['storage']->getGeneratedCssPath()), 'Importing a site transfer bundle should rebuild generated CSS from the restored library.');
    $remoteGetUrls = array_map(
        static fn (array $call): string => (string) ($call['url'] ?? ''),
        $remoteGetCalls
    );
    assertTrueValue(in_array($findUrl, $remoteGetUrls, true), 'Importing a site transfer bundle should re-sync the restored library to the Block Editor Font Library when that feature is enabled.');
    assertSameValue(2, count($remoteRequestCalls), 'Importing a site transfer bundle should create one managed family and one managed font face for the restored library.');
};

$tests['admin_controller_creates_restorable_snapshot_before_direct_site_transfer_import'] = static function (): void {
    resetTestState();

    global $uploadedFilePaths, $remoteGetResponses;

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();
    $sourceFile = (string) $services['storage']->pathForRelativePath('upload/inter/inter-400-normal.woff2');
    $services['storage']->writeAbsoluteFile($sourceFile, 'source-font-data');
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
    $services['settings']->saveSettings(['block_editor_font_library_sync_enabled' => '0']);

    $bundle = $services['site_transfer']->buildExportBundle();
    assertFalseValue(is_wp_error($bundle), 'Building the source bundle for controller import testing should succeed.');

    $existingFile = (string) $services['storage']->pathForRelativePath('upload/existing/existing-400-normal.woff2');
    $services['storage']->writeAbsoluteFile($existingFile, 'existing-font-data');
    $services['imports']->saveProfile(
        'Existing Sans',
        'existing-sans',
        [
            'id' => 'existing-upload',
            'label' => 'Local Upload',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                [
                    'family' => 'Existing Sans',
                    'slug' => 'existing-sans',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'files' => ['woff2' => 'upload/existing/existing-400-normal.woff2'],
                    'paths' => ['woff2' => 'upload/existing/existing-400-normal.woff2'],
                ],
            ],
        ],
        'published',
        true
    );

    $uploadedFilePaths[] = (string) $bundle['path'];
    $result = $services['controller']->importSiteTransferBundle([
        'name' => 'tasty-fonts-transfer.zip',
        'tmp_name' => (string) $bundle['path'],
        'error' => UPLOAD_ERR_OK,
        'size' => (int) filesize((string) $bundle['path']),
    ]);

    assertFalseValue(is_wp_error($result), 'Importing a direct site transfer bundle through the controller should succeed.');
    assertSameValue(false, file_exists($existingFile), 'The direct controller import should still replace previous managed files.');

    $snapshots = $services['snapshots']->listSnapshots();
    assertSameValue(1, count($snapshots), 'Importing a direct site transfer bundle through the controller should create one rollback snapshot first.');
    assertSameValue('before_transfer_import', (string) ($snapshots[0]['reason'] ?? ''), 'The direct import snapshot should record that it protects a transfer import.');

    $restore = $services['snapshots']->restoreSnapshot((string) ($snapshots[0]['id'] ?? ''));
    assertFalseValue(is_wp_error($restore), 'The direct import snapshot should be restorable.');
    assertSameValue('existing-font-data', (string) file_get_contents($existingFile), 'Restoring the direct import snapshot should bring back the previous managed font file contents.');
    assertSameValue('Existing Sans', (string) ($services['imports']->getFamily('existing-sans')['family'] ?? ''), 'Restoring the direct import snapshot should bring back the previous library entry.');
};

$tests['site_transfer_service_stage_import_bundle_stages_a_validated_bundle_for_follow_up_import'] = static function (): void {
    resetTestState();

    global $uploadedFilePaths, $remoteGetResponses;

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('upload/inter/inter-400-normal.woff2'), 'font-data');
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
        'google_api_key' => 'source-secret',
        'block_editor_font_library_sync_enabled' => '0',
    ]);
    $services['settings']->saveRoles(['heading' => 'Inter', 'body' => 'Inter'], []);
    $services['settings']->saveAppliedRoles(['heading' => 'Inter', 'body' => 'Inter'], []);

    $bundle = $services['site_transfer']->buildExportBundle();
    $uploadedFilePaths[] = (string) $bundle['path'];

    $stage = $services['site_transfer']->stageImportBundle([
        'name' => 'tasty-fonts-transfer.zip',
        'tmp_name' => (string) $bundle['path'],
        'error' => UPLOAD_ERR_OK,
        'size' => (int) filesize((string) $bundle['path']),
    ]);

    assertFalseValue(is_wp_error($stage), 'Dry-running a valid site transfer bundle should succeed.');
    assertSameValue(true, trim((string) ($stage['stage_token'] ?? '')) !== '', 'Dry-running should return a staged bundle token for the follow-up import.');
    assertSameValue(1, (int) ($stage['families'] ?? 0), 'Dry-running should report the number of importable families from the validated bundle.');
    assertSameValue(true, (int) ($stage['files'] ?? 0) >= 1, 'Dry-running should report at least one managed file from the validated bundle.');

    $existingFile = (string) $services['storage']->pathForRelativePath('upload/existing/existing-400-normal.woff2');
    $services['storage']->writeAbsoluteFile($existingFile, 'existing-font-data');
    $services['imports']->saveProfile(
        'Existing Sans',
        'existing-sans',
        [
            'id' => 'existing-upload',
            'label' => 'Local Upload',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                [
                    'family' => 'Existing Sans',
                    'slug' => 'existing-sans',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'files' => ['woff2' => 'upload/existing/existing-400-normal.woff2'],
                    'paths' => ['woff2' => 'upload/existing/existing-400-normal.woff2'],
                ],
            ],
        ],
        'published',
        true
    );
    $services['settings']->saveSettings(['google_api_key' => 'old-secret']);
    $remoteGetResponses['https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key=fresh-destination-key'] = [
        'response' => ['code' => 200],
        'body' => wp_json_encode(['items' => []]),
    ];

    $result = $services['controller']->importStagedSiteTransferBundle((string) ($stage['stage_token'] ?? ''), 'fresh-destination-key');

    assertFalseValue(is_wp_error($result), 'Importing a staged site transfer bundle should succeed.');
    assertSameValue(false, file_exists($existingFile), 'Importing a staged site transfer bundle should replace previously managed files that are not in the bundle.');
    assertSameValue('fresh-destination-key', (string) ($services['settings']->getSettings()['google_api_key'] ?? ''), 'Importing a staged site transfer bundle should still accept a fresh destination Google API key.');
    assertSameValue('valid', (string) ($services['settings']->getSettings()['google_api_key_status'] ?? ''), 'Importing a staged site transfer bundle should preserve the validated Google API key status.');
    assertSameValue(false, get_transient('tasty_fonts_transfer_stage_1') !== false, 'The staged bundle should be cleared after the destructive import completes.');

    $snapshots = $services['snapshots']->listSnapshots();
    assertSameValue(1, count($snapshots), 'Importing a staged site transfer bundle through the controller should create one rollback snapshot first.');
    assertSameValue('before_transfer_import', (string) ($snapshots[0]['reason'] ?? ''), 'The automatic rollback snapshot should record that it protects a transfer import.');

    $restore = $services['snapshots']->restoreSnapshot((string) ($snapshots[0]['id'] ?? ''));
    assertFalseValue(is_wp_error($restore), 'The automatic pre-import snapshot should be restorable.');
    assertSameValue(true, file_exists($existingFile), 'Restoring the automatic pre-import snapshot should bring back the replaced managed font file.');
    assertSameValue('Existing Sans', (string) ($services['imports']->getFamily('existing-sans')['family'] ?? ''), 'Restoring the automatic pre-import snapshot should bring back the previous library entry.');
};

$tests['snapshot_service_creates_sanitized_rollback_snapshots_and_restores_state'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();
    $fontPath = (string) $services['storage']->pathForRelativePath('upload/inter/inter-400-normal.woff2');
    $services['storage']->writeAbsoluteFile($fontPath, 'snapshot-font-data');
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
    $services['settings']->saveSettings(['google_api_key' => 'secret-google-key', 'auto_apply_roles' => '1']);
    $services['settings']->saveRoles(['heading' => 'Inter', 'body' => 'Inter'], ['Inter']);
    $services['settings']->saveAppliedRoles(['heading' => 'Inter', 'body' => 'Inter'], ['Inter']);

    $snapshot = $services['snapshots']->createSnapshot('manual');

    assertFalseValue(is_wp_error($snapshot), 'Creating a rollback snapshot should succeed.');
    $summary = is_array($snapshot['snapshot'] ?? null) ? $snapshot['snapshot'] : [];
    $snapshotId = (string) ($summary['id'] ?? '');
    assertSameValue(true, $snapshotId !== '', 'Rollback snapshots should return a stable snapshot id.');
    assertSameValue(['Inter'], $summary['family_names'] ?? [], 'Rollback snapshot summaries should expose captured family names for the recovery UI.');
    assertSameValue(['Inter'], $summary['role_families'] ?? [], 'Rollback snapshot summaries should expose live role families for the recovery UI.');
    assertSameValue(1, (int) ($summary['font_files'] ?? -1), 'Rollback snapshot summaries should count actual font files separately from storage scaffolding.');
    assertSameValue(13, (int) ($summary['storage_files'] ?? -1), 'Rollback snapshot summaries should still expose the total captured storage file count.');

    $zipPath = null;
    foreach (glob((string) $GLOBALS['uploadBaseDir'] . '/tasty-fonts-snapshots/*.zip') ?: [] as $candidate) {
        if (str_contains((string) $candidate, $snapshotId)) {
            $zipPath = (string) $candidate;
            break;
        }
    }

    assertSameValue(true, is_string($zipPath) && is_readable($zipPath), 'Creating a rollback snapshot should write a readable zip outside managed font storage.');

    $zip = new ZipArchive();
    assertSameValue(true, $zip->open((string) $zipPath) === true, 'The rollback snapshot should be a readable ZIP archive.');
    $manifestJson = (string) $zip->getFromName('tasty-fonts-snapshot.json');
    $fontPayload = (string) $zip->getFromName('fonts/upload/inter/inter-400-normal.woff2');
    $zip->close();
    $manifest = json_decode($manifestJson, true);

    assertSameValue(true, is_array($manifest), 'The rollback snapshot should include a JSON manifest.');
    assertSameValue(false, isset($manifest['settings']['google_api_key']), 'Rollback snapshots should not store Google API keys.');
    assertSameValue('snapshot-font-data', $fontPayload, 'Rollback snapshots should include managed font files.');

    $services['storage']->deleteAbsolutePath((string) $services['storage']->getRoot());
    $services['imports']->clearLibrary();
    $services['settings']->resetStoredSettingsToDefaults();

    $restore = $services['snapshots']->restoreSnapshot($snapshotId);

    assertFalseValue(is_wp_error($restore), 'Restoring a rollback snapshot should succeed.');
    assertSameValue('snapshot-font-data', (string) file_get_contents($fontPath), 'Rollback restore should restore managed font files.');
    assertSameValue(true, $services['imports']->getFamily('inter') !== null, 'Rollback restore should restore the font library.');
    assertSameValue('Inter', (string) ($services['settings']->getRoles([])['heading'] ?? ''), 'Rollback restore should restore saved roles.');
    assertSameValue('', (string) ($services['settings']->getSettings()['google_api_key'] ?? ''), 'Rollback restore should keep excluded Google API keys empty.');

    $rename = $services['snapshots']->renameSnapshot($snapshotId, 'Before homepage launch');

    assertFalseValue(is_wp_error($rename), 'Renaming a rollback snapshot should succeed.');
    assertSameValue('Before homepage launch', (string) ($rename['snapshot']['label'] ?? ''), 'Renamed rollback snapshots should return their friendly label.');
    assertSameValue('Before homepage launch', (string) ($services['snapshots']->listSnapshots()[0]['label'] ?? ''), 'Renamed rollback snapshots should persist their friendly label.');

    $delete = $services['snapshots']->deleteSnapshot($snapshotId);

    assertFalseValue(is_wp_error($delete), 'Deleting a rollback snapshot should succeed.');
    assertSameValue([], $services['snapshots']->listSnapshots(), 'Deleting a rollback snapshot should remove it from the local snapshot list.');
    assertSameValue(false, is_string($zipPath) && file_exists($zipPath), 'Deleting a rollback snapshot should remove the local ZIP archive.');

    $services['settings']->saveSettings(['snapshot_retention_limit' => 2]);
    assertFalseValue(is_wp_error($services['snapshots']->createSnapshot('manual')), 'Creating the first retained snapshot should succeed.');
    assertFalseValue(is_wp_error($services['snapshots']->createSnapshot('manual')), 'Creating the second retained snapshot should succeed.');
    assertFalseValue(is_wp_error($services['snapshots']->createSnapshot('manual')), 'Creating the third retained snapshot should succeed.');
    assertSameValue(2, count($services['snapshots']->listSnapshots()), 'Rollback snapshots should be pruned to the configured retention limit.');
};

$tests['support_bundle_service_exports_sanitized_diagnostics_without_secrets'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();
    $services['settings']->saveSettings(['google_api_key' => 'secret-google-key']);
    $services['storage']->writeAbsoluteFile((string) $services['storage']->getGeneratedCssPath(), 'body{font-family:Inter;}');
    $services['log']->add('Generated CSS regenerated.');

    $bundle = $services['support_bundles']->buildBundle([
        'advanced_tools' => [
            'google_api_key' => 'secret-google-key',
            'health_checks' => [
                [
                    'slug' => 'generated_css',
                    'message' => 'Generated CSS is present.',
                ],
            ],
        ],
    ]);

    assertFalseValue(is_wp_error($bundle), 'Building a support bundle should succeed.');

    $zip = new ZipArchive();
    assertSameValue(true, $zip->open((string) ($bundle['path'] ?? '')) === true, 'The support bundle should be a readable ZIP archive.');
    $settingsJson = (string) $zip->getFromName('diagnostics/settings.json');
    $advancedToolsJson = (string) $zip->getFromName('diagnostics/advanced-tools.json');
    $generatedCss = (string) $zip->getFromName('generated-css/tasty-fonts.css');
    $zip->close();

    assertNotContainsValue('secret-google-key', $settingsJson . $advancedToolsJson, 'Support bundles should exclude saved and payload API keys.');
    assertContainsValue('generated_css', $advancedToolsJson, 'Support bundles should preserve list-shaped Advanced Tools diagnostics.');
    assertContainsValue('body{font-family:Inter;}', $generatedCss, 'Support bundles should include generated CSS when available.');

    @unlink((string) ($bundle['path'] ?? ''));
};

$tests['site_transfer_dry_run_reports_import_diff_and_snapshot_notice'] = static function (): void {
    resetTestState();

    global $uploadedFilePaths;

    $source = makeServiceGraph();
    $source['developer_tools']->ensureStorageScaffolding();
    $source['storage']->writeAbsoluteFile((string) $source['storage']->pathForRelativePath('upload/inter/inter-400-normal.woff2'), 'source-font-data');
    $source['imports']->saveProfile(
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
    $bundle = $source['site_transfer']->buildExportBundle();
    assertFalseValue(is_wp_error($bundle), 'Building a source transfer bundle should succeed.');

    resetTestState();
    $destination = makeServiceGraph();
    $destination['developer_tools']->ensureStorageScaffolding();
    $destination['imports']->saveProfile(
        'Existing Sans',
        'existing-sans',
        [
            'id' => 'existing',
            'label' => 'Existing',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [],
        ],
        'published',
        true
    );
    $uploadedFilePaths[] = (string) ($bundle['path'] ?? '');
    $stage = $destination['site_transfer']->stageImportBundle([
        'name' => 'phase-three-transfer.zip',
        'tmp_name' => (string) ($bundle['path'] ?? ''),
        'error' => UPLOAD_ERR_OK,
        'size' => (int) filesize((string) ($bundle['path'] ?? '')),
    ]);

    assertFalseValue(is_wp_error($stage), 'Dry-running a valid transfer bundle should succeed.');
    $diff = is_array($stage['diff'] ?? null) ? $stage['diff'] : [];
    assertSameValue(true, in_array('inter', is_array($diff['families_added'] ?? null) ? $diff['families_added'] : [], true), 'Transfer dry-run diff should list families added by the incoming bundle.');
    assertSameValue(true, in_array('existing-sans', is_array($diff['families_removed'] ?? null) ? $diff['families_removed'] : [], true), 'Transfer dry-run diff should list current families that the import will remove.');
    assertSameValue(true, !empty($diff['snapshot_will_be_created']), 'Transfer dry-run diff should disclose that a current-state snapshot will be created before import.');

    @unlink((string) ($bundle['path'] ?? ''));
};

$tests['site_transfer_service_import_leaves_google_api_key_empty_when_no_fresh_secret_is_provided'] = static function (): void {
    resetTestState();

    global $uploadedFilePaths;

    $services = makeServiceGraph();
    $services['developer_tools']->ensureStorageScaffolding();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('upload/inter/inter-400-normal.woff2'), 'font-data');
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
        'google_api_key' => 'source-secret',
        'block_editor_font_library_sync_enabled' => '0',
    ]);

    $bundle = $services['site_transfer']->buildExportBundle();
    $uploadedFilePaths[] = (string) $bundle['path'];
    $result = $services['site_transfer']->importBundleReplacingCurrentState(
        [
            'name' => 'tasty-fonts-transfer.zip',
            'tmp_name' => (string) $bundle['path'],
            'error' => UPLOAD_ERR_OK,
            'size' => (int) filesize((string) $bundle['path']),
        ],
        ''
    );

    assertFalseValue(is_wp_error($result), 'Importing a valid site transfer bundle without a fresh Google API key should still succeed.');
    assertSameValue('', (string) ($services['settings']->getSettings()['google_api_key'] ?? ''), 'Importing without a fresh Google API key should leave the destination site with no saved Google secret.');
};

$tests['site_transfer_service_import_rejects_missing_uploads_before_validation'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $result = $services['site_transfer']->importBundleReplacingCurrentState([
        'name' => '',
        'tmp_name' => '',
        'error' => UPLOAD_ERR_NO_FILE,
    ]);

    assertWpErrorCode(
        'tasty_fonts_transfer_missing_upload',
        $result,
        'Importing a site transfer bundle should reject requests that do not include an uploaded file.'
    );
};

$tests['site_transfer_service_import_rejects_non_zip_uploads_before_reading_the_file'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $tmpPath = tempnam(sys_get_temp_dir(), 'tasty-fonts-transfer-text-');
    file_put_contents((string) $tmpPath, 'not-a-zip');

    $result = $services['site_transfer']->importBundleReplacingCurrentState([
        'name' => 'tasty-fonts-transfer.txt',
        'tmp_name' => (string) $tmpPath,
        'error' => UPLOAD_ERR_OK,
        'size' => (int) filesize((string) $tmpPath),
    ]);

    assertWpErrorCode(
        'tasty_fonts_transfer_upload_not_zip',
        $result,
        'Importing a site transfer bundle should reject uploads whose filename is not a zip archive.'
    );

    @unlink((string) $tmpPath);
};

$tests['site_transfer_service_import_rejects_unverified_zip_uploads'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $tmpPath = tempnam(sys_get_temp_dir(), 'tasty-fonts-transfer-upload-');
    file_put_contents((string) $tmpPath, 'placeholder');

    $result = $services['site_transfer']->importBundleReplacingCurrentState([
        'name' => 'tasty-fonts-transfer.zip',
        'tmp_name' => (string) $tmpPath,
        'error' => UPLOAD_ERR_OK,
        'size' => (int) filesize((string) $tmpPath),
    ]);

    assertWpErrorCode(
        'tasty_fonts_transfer_upload_unverified',
        $result,
        'Importing a site transfer bundle should reject zip files that were not verified as uploaded files by the validator.'
    );

    @unlink((string) $tmpPath);
};

$tests['site_transfer_service_validation_rejects_archives_without_a_manifest'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $zipPath = tempnam(sys_get_temp_dir(), 'tasty-fonts-transfer-no-manifest-');
    $zip = new ZipArchive();
    $zip->open((string) $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('fonts/upload/inter/inter-400-normal.woff2', 'font-data');
    $zip->close();

    $validation = $services['site_transfer']->validateImportBundle((string) $zipPath);

    assertWpErrorCode(
        'tasty_fonts_transfer_missing_manifest',
        $validation,
        'Bundle validation should reject archives that do not include the export manifest.'
    );

    @unlink((string) $zipPath);
};

$tests['site_transfer_service_validation_rejects_unexpected_root_entries'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $zipPath = tempnam(sys_get_temp_dir(), 'tasty-fonts-transfer-unexpected-entry-');
    $zip = new ZipArchive();
    $zip->open((string) $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString(
        SiteTransferService::MANIFEST_FILENAME,
        (string) wp_json_encode([
            'schema_version' => SiteTransferService::SCHEMA_VERSION,
            'settings' => [],
            'roles' => [],
            'applied_roles' => [],
            'library' => [],
            'files' => [],
        ])
    );
    $zip->addFromString('notes.txt', 'unexpected');
    $zip->close();

    $validation = $services['site_transfer']->validateImportBundle((string) $zipPath);

    assertWpErrorCode(
        'tasty_fonts_transfer_unexpected_archive_entry',
        $validation,
        'Bundle validation should reject unexpected root-level files outside the managed fonts payload.'
    );

    @unlink((string) $zipPath);
};

$tests['settings_repository_defaults_and_persists_class_output_settings'] = static function (): void {
    resetTestState();

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
    assertSameValue(true, !empty($settings->getSettings()['extended_variable_role_weight_vars_enabled']), 'Role weight variables should default to enabled.');
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

    $settings->saveSettings(['extended_variable_role_weight_vars_enabled' => '0']);
    assertSameValue(
        false,
        !empty($settings->getSettings()['extended_variable_role_weight_vars_enabled']),
        'Settings should persist disabled role weight variable output.'
    );

    $settings->saveSettings([
        'role_usage_font_weight_enabled' => '0',
        'minimal_output_preset_enabled' => '1',
        'extended_variable_role_weight_vars_enabled' => '0',
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
    assertSameValue(true, $saved['extended_variable_role_weight_vars_enabled'], 'Minimal output preset should keep role weight variables enabled.');
    assertSameValue(false, $saved['extended_variable_weight_tokens_enabled'], 'Settings should persist disabled extended weight tokens.');
    assertSameValue(false, $saved['extended_variable_role_aliases_enabled'], 'Settings should persist disabled extended role aliases.');
    assertSameValue(false, $saved['extended_variable_category_sans_enabled'], 'Settings should persist disabled extended sans aliases.');
    assertSameValue(false, $saved['extended_variable_category_serif_enabled'], 'Settings should persist disabled extended serif aliases.');
    assertSameValue(false, $saved['extended_variable_category_mono_enabled'], 'Settings should persist disabled extended mono aliases.');
    assertSameValue('minimal', (string) ($saved['output_quick_mode_preference'] ?? ''), 'Saving the minimal preset should persist the matching quick-mode preference.');
};

$tests['settings_repository_preserves_custom_output_modes_when_minimal_flag_is_missing'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'class_output_enabled' => true,
        'class_output_families_enabled' => true,
        'per_variant_font_variables_enabled' => true,
    ];

    $settings = new SettingsRepository();
    $normalized = $settings->getSettings();

    assertSameValue(false, !empty($normalized['minimal_output_preset_enabled']), 'Saved custom output settings should not silently opt into the minimal preset.');
    assertSameValue(true, !empty($normalized['class_output_enabled']), 'Saved class output settings should be preserved when the minimal flag is missing.');
    assertSameValue(true, !empty($normalized['per_variant_font_variables_enabled']), 'Saved variable output settings should be preserved when the minimal flag is missing.');
    assertSameValue('custom', (string) ($normalized['output_quick_mode_preference'] ?? ''), 'Mixed output settings should derive a custom quick-mode preference when no explicit preference was stored.');
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
        'extended_variable_role_weight_vars_enabled' => '1',
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
        'extended_variable_role_weight_vars_enabled' => '1',
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
        'class_output_role_styles_enabled' => '1',
        'per_variant_font_variables_enabled' => '0',
        'role_usage_font_weight_enabled' => '0',
    ]);

    assertSameValue(true, !empty($saved['class_output_role_styles_enabled']), 'Role class styles should persist when the opt-in toggle is enabled.');
    assertSameValue('classes', (string) ($saved['output_quick_mode_preference'] ?? ''), 'Opting role classes into weights and variation settings should keep the quick-mode preference on classes.');
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

$tests['settings_repository_hides_activity_log_by_default_and_persists_visibility'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue(false, !empty($settings->getSettings()['show_activity_log']), 'The full activity log should be hidden by default.');

    $settings->saveSettings(['show_activity_log' => '1']);
    $saved = $settings->getSettings();

    assertSameValue(true, !empty($saved['show_activity_log']), 'Settings should persist the activity log visibility preference when enabled.');

    $settings->saveSettings(['show_activity_log' => '0']);
    $saved = $settings->getSettings();

    assertSameValue(false, !empty($saved['show_activity_log']), 'Settings should persist the activity log visibility preference when disabled.');
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

$tests['settings_repository_defaults_font_display_to_swap_and_normalizes_invalid_values'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue('swap', $settings->getSettings()['font_display'], 'Font display should default to swap for new installs.');

    $settings->saveSettings(['font_display' => 'block']);
    assertSameValue('block', $settings->getSettings()['font_display'], 'Settings should persist supported font-display values.');

    $settings->saveSettings(['font_display' => 'unsupported-value']);
    assertSameValue('swap', $settings->getSettings()['font_display'], 'Invalid saved font-display values should normalize back to swap.');
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
        'extended_variable_role_weight_vars_enabled' => '0',
        'extended_variable_weight_tokens_enabled' => '0',
        'extended_variable_role_aliases_enabled' => '0',
        'extended_variable_category_sans_enabled' => '0',
        'extended_variable_category_serif_enabled' => '0',
        'extended_variable_category_mono_enabled' => '0',
        'preload_primary_fonts' => '0',
        'block_editor_font_library_sync_enabled' => '1',
        'delete_uploaded_files_on_uninstall' => '1',
        'show_activity_log' => '1',
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
    assertSameValue(false, $saved['extended_variable_role_weight_vars_enabled'], 'Saving unrelated settings should not re-enable role weight variables.');
    assertSameValue(false, $saved['extended_variable_weight_tokens_enabled'], 'Saving unrelated settings should not re-enable extended weight tokens.');
    assertSameValue(false, $saved['extended_variable_role_aliases_enabled'], 'Saving unrelated settings should not re-enable extended role aliases.');
    assertSameValue(false, $saved['extended_variable_category_sans_enabled'], 'Saving unrelated settings should not re-enable the sans alias.');
    assertSameValue(false, $saved['extended_variable_category_serif_enabled'], 'Saving unrelated settings should not re-enable the serif alias.');
    assertSameValue(false, $saved['extended_variable_category_mono_enabled'], 'Saving unrelated settings should not re-enable the mono alias.');
    assertSameValue(false, $saved['preload_primary_fonts'], 'Saving unrelated settings should not re-enable primary font preloads.');
    assertSameValue(true, $saved['block_editor_font_library_sync_enabled'], 'Saving unrelated settings should not disable the Block Editor Font Library sync preference.');
    assertSameValue(true, $saved['delete_uploaded_files_on_uninstall'], 'Saving unrelated settings should not disable uninstall cleanup.');
    assertSameValue(true, $saved['show_activity_log'], 'Saving unrelated settings should not hide the activity log once it is enabled.');
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
    assertSameValue('system-ui, sans-serif', $defaultRoles['heading_fallback'], 'Draft roles should default the heading fallback stack to a modern system sans stack.');
    assertSameValue('system-ui, sans-serif', $defaultRoles['body_fallback'], 'Draft roles should default the body fallback stack to a modern system sans stack.');
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
    $transientStore[TransientKey::forSite(CatalogService::TRANSIENT_CATALOG)] = ['stale' => true];
    $transientStore[TransientKey::forSite('tasty_fonts_css_v2')] = 'stale-css';
    $transientStore[TransientKey::forSite('tasty_fonts_css_hash_v2')] = 'stale-hash';

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
    assertSameValue(true, in_array(TransientKey::forSite(CatalogService::TRANSIENT_CATALOG), $transientDeleted, true), 'Refreshing generated assets should invalidate the catalog cache first.');
    assertSameValue(true, in_array(TransientKey::forSite('tasty_fonts_css_v2'), $transientDeleted, true), 'Refreshing generated assets should invalidate the cached CSS payload.');
    assertSameValue(true, in_array(TransientKey::forSite('tasty_fonts_css_hash_v2'), $transientDeleted, true), 'Refreshing generated assets should invalidate the cached CSS hash.');
    assertSameValue(false, array_key_exists(TransientKey::forSite('tasty_fonts_css_v2'), $transientStore), 'Refreshing generated assets should leave CSS transient regeneration to the next request.');
    assertSameValue(false, array_key_exists(TransientKey::forSite('tasty_fonts_css_hash_v2'), $transientStore), 'Refreshing generated assets should leave CSS hash regeneration to the next request.');
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
        $transientStore[TransientKey::forSite('tasty_fonts_regenerate_css_queued')] ?? null,
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

$tests['asset_service_can_inject_a_filtered_nonce_into_plugin_inline_style_tags'] = static function (): void {
    resetTestState();

    global $inlineStyles;

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['css_delivery_mode' => 'inline']);
    add_filter(
        'tasty_fonts_generated_css',
        static fn (string $css): string => $css . "\nbody{color:blue;}"
    );

    add_filter(
        'tasty_fonts_inline_style_nonce',
        static function (string $nonce, string $handle, string $css, string $context): string {
            assertSameValue('tasty-fonts-runtime', $handle, 'Inline style nonce filters should receive the enqueued handle.');
            assertContainsValue('body{color:blue;}', $css, 'Inline style nonce filters should receive the CSS payload being printed.');
            assertSameValue('runtime', $context, 'Inline style nonce filters should receive the runtime/admin-preview context.');

            return 'csp-nonce-123';
        },
        10,
        4
    );

    $services['assets']->enqueue('tasty-fonts-runtime');

    $html = "<style id='tasty-fonts-runtime-inline-css'>\n"
        . (string) ($inlineStyles['tasty-fonts-runtime'] ?? '')
        . "\n</style>\n";
    $filtered = $services['assets']->filterInlineStyleOutputBuffer($html);

    assertContainsValue(
        'nonce="csp-nonce-123"',
        $filtered,
        'Configured CSP nonces should be injected into this plugin\'s rendered inline style tags.'
    );
};

$tests['asset_service_inline_style_nonce_filter_leaves_unknown_style_tags_unchanged'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    add_filter('tasty_fonts_inline_style_nonce', static fn (): string => 'csp-nonce-123');
    $services['settings']->saveSettings(['css_delivery_mode' => 'inline']);
    $services['assets']->enqueue('tasty-fonts-runtime');

    $html = "<style id='someone-else-inline-css'>body{color:red;}</style>";

    assertSameValue(
        $html,
        $services['assets']->filterInlineStyleOutputBuffer($html),
        'The inline style nonce buffer should not rewrite unrelated inline style tags.'
    );
};

$tests['asset_service_inline_style_nonce_strategy_can_be_switched_off'] = static function (): void {
    resetTestState();

    global $inlineStyles;

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['css_delivery_mode' => 'inline']);
    add_filter('tasty_fonts_inline_style_nonce', static fn (): string => 'csp-nonce-123');
    add_filter('tasty_fonts_inline_style_nonce_strategy', static fn (): string => 'off');

    $services['assets']->enqueue('tasty-fonts-runtime');

    $html = "<style id='tasty-fonts-runtime-inline-css'>\n"
        . (string) ($inlineStyles['tasty-fonts-runtime'] ?? '')
        . "\n</style>\n";

    assertSameValue(
        $html,
        $services['assets']->filterInlineStyleOutputBuffer($html),
        'Sites should be able to disable the plugin-managed inline style nonce strategy explicitly.'
    );
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
    assertContainsValue('body{letter-spacing:.02em;}', (string) ($transientStore[TransientKey::forSite('tasty_fonts_css_v2')] ?? ''), 'Generated CSS filters should run before the CSS transient is written.');
};

$tests['asset_service_can_refresh_generated_assets_without_logging_file_writes'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['assets']->refreshGeneratedAssets(true, false);
    $services['assets']->ensureGeneratedCssFile();
    $entries = $services['log']->all();

    assertSameValue(0, count($entries), 'Deferred CSS regeneration should honor the no-log file write option.');
};

$tests['asset_service_status_ignores_retired_generated_stylesheet_when_canonical_file_is_missing'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $retiredPath = trailingslashit((string) $services['storage']->getRoot()) . 'tasty-fonts.css';
    $canonicalPath = (string) $services['storage']->getGeneratedCssPath();
    $canonicalUrl = (string) $services['storage']->getGeneratedCssUrl();
    $contents = "/* Version: " . TASTY_FONTS_VERSION . " */\n" . $services['assets']->getCss();

    wp_mkdir_p(dirname($retiredPath));
    file_put_contents($retiredPath, $contents);
    clearstatcache(true, $retiredPath);
    clearstatcache(true, $canonicalPath);

    $status = $services['assets']->getStatus();

    assertSameValue($canonicalPath, (string) ($status['path'] ?? ''), 'Generated stylesheet status should stay on the canonical v2 path.');
    assertSameValue($canonicalUrl, (string) ($status['url'] ?? ''), 'Generated stylesheet status should stay on the canonical v2 URL.');
    assertSameValue(false, !empty($status['exists']), 'Retired generated CSS should not make canonical status look available.');
    assertSameValue(0, (int) ($status['size'] ?? -1), 'Missing canonical generated CSS should report an empty file size.');
    assertSameValue(0, (int) ($status['last_modified'] ?? -1), 'Missing canonical generated CSS should report no modified time.');
};

$tests['asset_service_enqueue_ignores_retired_generated_stylesheet_contents'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $inlineStyles;

    $services = makeServiceGraph();
    $retiredPath = trailingslashit((string) $services['storage']->getRoot()) . 'tasty-fonts.css';
    $canonicalPath = (string) $services['storage']->getGeneratedCssPath();
    $currentContents = "/* Version: " . TASTY_FONTS_VERSION . " */\n" . $services['assets']->getCss();
    $retiredContents = "/* Version: " . TASTY_FONTS_VERSION . " */\nbody{font-family:RetiredOnly;}";

    wp_mkdir_p(dirname($retiredPath));
    file_put_contents($retiredPath, $retiredContents);
    clearstatcache(true, $retiredPath);
    clearstatcache(true, $canonicalPath);

    $services['assets']->enqueue('tasty-fonts-runtime');

    assertSameValue(true, is_file($canonicalPath), 'Enqueue should keep the normal canonical generated CSS write path available.');
    assertSameValue($currentContents, (string) file_get_contents($canonicalPath), 'Enqueue should write freshly generated CSS instead of copying retired generated CSS contents.');
    assertSameValue('', (string) ($enqueuedStyles['tasty-fonts-runtime']['src'] ?? ''), 'Retired generated CSS should not be enqueued as a file delivery URL.');
    assertSameValue(true, is_file($retiredPath), 'Ignoring the retired generated stylesheet path should leave the old file untouched.');
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

$tests['health_check_service_builds_structured_advanced_tools_statuses'] = static function (): void {
    resetTestState();

    $health = new HealthCheckService();
    $checks = $health->build(
        [
            'path' => '/tmp/missing-generated.css',
            'url' => 'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css',
            'exists' => false,
            'size' => 0,
            'last_modified' => 0,
        ],
        null,
        ['css_delivery_mode' => 'file'],
        ['families' => 0, 'files' => 0],
        ['available' => false, 'message' => 'ZipArchive is unavailable.']
    );
    $summary = $health->summarize($checks);

    assertSameValue('generated_css', (string) ($checks[0]['slug'] ?? ''), 'Health checks should expose a stable generated CSS check slug.');
    assertSameValue('warning', (string) ($checks[0]['severity'] ?? ''), 'Missing generated CSS file delivery should be reported as a warning.');
    assertContainsValue('Run Regenerate CSS File', (string) ($checks[0]['guidance'] ?? ''), 'Generated CSS warnings should explain the next practical step.');
    assertContainsValue('Advanced-Tools', (string) ($checks[0]['help_url'] ?? ''), 'Generated CSS checks should link to the Advanced Tools knowledge base page.');
    assertSameValue('Open knowledge base', (string) ($checks[0]['help_label'] ?? ''), 'Health checks should expose a consistent knowledge-base link label.');
    $checksBySlug = [];

    foreach ($checks as $check) {
        $checksBySlug[(string) ($check['slug'] ?? '')] = $check;
        assertSameValue(true, trim((string) ($check['guidance'] ?? '')) !== '', 'Every health check should explain what the result means or what to do next.');
        assertSameValue(true, trim((string) ($check['help_url'] ?? '')) !== '', 'Every health check should expose a knowledge-base URL.');
    }

    assertSameValue(true, isset($checksBySlug['storage_root']), 'Health checks should expose managed storage as a first-class check.');
    assertSameValue('critical', (string) ($checksBySlug['storage_root']['severity'] ?? ''), 'Unavailable managed storage should be reported as critical.');
    assertSameValue(true, isset($checksBySlug['site_transfer']), 'Health checks should expose site transfer capability.');
    assertSameValue(true, isset($checksBySlug['sitewide_delivery']), 'Health checks should expose Sitewide Delivery readiness.');
    assertSameValue('warning', (string) ($checksBySlug['sitewide_delivery']['severity'] ?? ''), 'Sitewide Delivery off should be reported as an action-needed warning.');
    assertSameValue('deploy_fonts', (string) ($checksBySlug['sitewide_delivery']['action']['slug'] ?? ''), 'Sitewide Delivery warnings should send users to Deploy Fonts.');
    assertSameValue(true, isset($checksBySlug['self_hosted_files']), 'Health checks should expose self-hosted file integrity.');
    assertSameValue(true, isset($checksBySlug['external_stylesheets']), 'Health checks should expose external stylesheet delivery.');
    assertSameValue(true, isset($checksBySlug['font_preload']), 'Health checks should expose preload readiness.');
    assertSameValue(true, isset($checksBySlug['block_editor_sync']), 'Health checks should expose editor sync risk.');
    assertSameValue(true, isset($checksBySlug['google_fonts_api']), 'Health checks should expose Google API readiness.');
    assertSameValue('info', (string) ($checksBySlug['google_fonts_api']['severity'] ?? ''), 'Missing Google API keys should be advisory, not a passing verified check.');
    assertSameValue(true, isset($checksBySlug['update_channel']), 'Health checks should expose update channel readiness.');
    assertSameValue('critical', (string) ($summary['status'] ?? ''), 'Health summaries should elevate critical checks above warnings and notices.');
};

$tests['health_check_service_does_not_warn_for_block_editor_sync_while_sitewide_delivery_is_off'] = static function (): void {
    resetTestState();

    $health = new HealthCheckService();
    $checks = $health->build(
        [
            'path' => '/tmp/generated.css',
            'url' => 'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css',
            'exists' => true,
            'size' => 128,
            'last_modified' => 1710000000,
        ],
        ['available' => true, 'root' => '/tmp/tasty-fonts'],
        ['css_delivery_mode' => 'file'],
        ['families' => 0, 'files' => 0],
        ['available' => true, 'message' => 'Ready.'],
        [
            'delivery' => ['auto_apply_roles' => false],
            'editor' => ['block_editor_sync_enabled' => true],
        ],
        [],
        [],
        ['environment_type' => 'local']
    );
    $checksBySlug = [];

    foreach ($checks as $check) {
        $checksBySlug[(string) ($check['slug'] ?? '')] = $check;
    }

    assertSameValue('ok', (string) ($checksBySlug['block_editor_sync']['severity'] ?? ''), 'Saved Block Editor sync preferences should not warn while Sitewide Delivery keeps sync inactive.');
    assertContainsValue('does not show', (string) ($checksBySlug['block_editor_sync']['message'] ?? ''), 'Inactive Block Editor sync should use the non-warning health message.');

    $checks = $health->build(
        [
            'path' => '/tmp/generated.css',
            'url' => 'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css',
            'exists' => true,
            'size' => 128,
            'last_modified' => 1710000000,
        ],
        ['available' => true, 'root' => '/tmp/tasty-fonts'],
        ['css_delivery_mode' => 'file'],
        ['families' => 0, 'files' => 0],
        ['available' => true, 'message' => 'Ready.'],
        [
            'delivery' => ['auto_apply_roles' => true],
            'editor' => ['block_editor_sync_enabled' => true],
        ],
        [],
        [],
        ['environment_type' => 'local']
    );
    $checksBySlug = [];

    foreach ($checks as $check) {
        $checksBySlug[(string) ($check['slug'] ?? '')] = $check;
    }

    assertSameValue('warning', (string) ($checksBySlug['block_editor_sync']['severity'] ?? ''), 'Block Editor sync should still warn on local sites when Sitewide Delivery makes it active.');
};

$tests['admin_page_context_builder_does_not_report_hydrated_self_hosted_urls_as_missing_files'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400.woff2'), 'font-data');
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Self-hosted',
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
                'files' => ['woff2' => 'inter/Inter-400.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400.woff2'],
            ]],
        ],
        'published',
        true
    );

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
    $advancedTools = is_array($context['advanced_tools'] ?? null) ? $context['advanced_tools'] : [];
    $manifest = is_array($advancedTools['runtime_manifest'] ?? null) ? $advancedTools['runtime_manifest'] : [];
    $families = is_array($manifest['families'] ?? null) ? $manifest['families'] : [];
    $checks = is_array($advancedTools['health_checks'] ?? null) ? $advancedTools['health_checks'] : [];
    $checksBySlug = [];

    foreach ($checks as $check) {
        if (is_array($check)) {
            $checksBySlug[(string) ($check['slug'] ?? '')] = $check;
        }
    }

    assertSameValue([], (array) ($families[0]['missing_files'] ?? ['unexpected']), 'Hydrated public URLs should be checked through their stored relative paths instead of being reported missing.');
    assertSameValue('ok', (string) ($checksBySlug['self_hosted_files']['severity'] ?? ''), 'The self-hosted files health check should pass when the hydrated public URL points at an existing managed file.');
};

$tests['admin_page_context_builder_resolves_self_hosted_upload_urls_without_paths_before_reporting_missing_files'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-700.woff2'), 'font-data');

    add_filter('tasty_fonts_catalog', static function (array $catalog): array {
        $catalog['Inter'] = [
            'family' => 'Inter',
            'slug' => 'inter',
            'publish_state' => 'published',
            'active_delivery_id' => 'local-self_hosted',
            'delivery_profiles' => [
                'local-self_hosted' => [
                    'id' => 'local-self_hosted',
                    'label' => 'Self-hosted',
                    'provider' => 'local',
                    'type' => 'self_hosted',
                    'format' => 'static',
                    'variants' => ['700'],
                    'faces' => [[
                        'family' => 'Inter',
                        'slug' => 'inter',
                        'source' => 'local',
                        'weight' => '700',
                        'style' => 'normal',
                        'files' => ['woff2' => '/wp-content/uploads/fonts/inter/Inter-700.woff2'],
                    ]],
                ],
            ],
        ];

        return $catalog;
    });

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
    $advancedTools = is_array($context['advanced_tools'] ?? null) ? $context['advanced_tools'] : [];
    $manifest = is_array($advancedTools['runtime_manifest'] ?? null) ? $advancedTools['runtime_manifest'] : [];
    $families = is_array($manifest['families'] ?? null) ? $manifest['families'] : [];
    $checks = is_array($advancedTools['health_checks'] ?? null) ? $advancedTools['health_checks'] : [];
    $checksBySlug = [];

    foreach ($checks as $check) {
        if (is_array($check)) {
            $checksBySlug[(string) ($check['slug'] ?? '')] = $check;
        }
    }

    assertSameValue([], (array) ($families[0]['missing_files'] ?? ['unexpected']), 'Root-relative uploads URLs should resolve back to the managed fonts directory before diagnostics report missing files.');
    assertSameValue('ok', (string) ($checksBySlug['self_hosted_files']['severity'] ?? ''), 'The self-hosted files check should not flag upload URLs that resolve to existing managed files.');
};

$tests['admin_page_context_builder_keeps_reporting_truly_missing_self_hosted_files'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Self-hosted',
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
                'files' => ['woff2' => 'inter/Missing-400.woff2'],
                'paths' => ['woff2' => 'inter/Missing-400.woff2'],
            ]],
        ],
        'published',
        true
    );

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
    $advancedTools = is_array($context['advanced_tools'] ?? null) ? $context['advanced_tools'] : [];
    $manifest = is_array($advancedTools['runtime_manifest'] ?? null) ? $advancedTools['runtime_manifest'] : [];
    $families = is_array($manifest['families'] ?? null) ? $manifest['families'] : [];
    $checks = is_array($advancedTools['health_checks'] ?? null) ? $advancedTools['health_checks'] : [];
    $checksBySlug = [];

    foreach ($checks as $check) {
        if (is_array($check)) {
            $checksBySlug[(string) ($check['slug'] ?? '')] = $check;
        }
    }

    assertSameValue(['inter/Missing-400.woff2'], (array) ($families[0]['missing_files'] ?? []), 'The runtime manifest should still expose genuinely missing self-hosted files.');
    assertSameValue('critical', (string) ($checksBySlug['self_hosted_files']['severity'] ?? ''), 'The self-hosted files health check should remain critical when a managed self-hosted file is actually missing.');
};

$tests['admin_page_context_builder_exposes_advanced_tools_context'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
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
    $advancedTools = is_array($context['advanced_tools'] ?? null) ? $context['advanced_tools'] : [];
    $manifest = is_array($advancedTools['runtime_manifest'] ?? null) ? $advancedTools['runtime_manifest'] : [];
    $healthChecks = is_array($advancedTools['health_checks'] ?? null) ? $advancedTools['health_checks'] : [];
    $healthSummary = is_array($advancedTools['health_summary'] ?? null) ? $advancedTools['health_summary'] : [];
    $toolActions = is_array($advancedTools['tool_actions'] ?? null) ? $advancedTools['tool_actions'] : [];
    $toolActionIds = array_map(
        static fn (mixed $action): string => is_array($action) ? (string) ($action['id'] ?? '') : '',
        $toolActions
    );

    assertSameValue(false, $advancedTools === [], 'The page context should expose a structured Advanced Tools payload.');
    assertSameValue(true, in_array('repair_storage_scaffold', $toolActionIds, true), 'Advanced Tools should expose structured safe action descriptors.');
    assertSameValue(true, in_array('site_transfer_import', $toolActionIds, true), 'Advanced Tools should expose structured destructive action descriptors.');
    assertSameValue(true, in_array('delete_all_snapshots', $toolActionIds, true), 'Advanced Tools should expose the bulk snapshot delete descriptor.');
    assertSameValue(true, in_array('delete_all_exports', $toolActionIds, true), 'Advanced Tools should expose the guarded bulk export delete descriptor.');
    assertSameValue(true, in_array('delete_all_history', $toolActionIds, true), 'Advanced Tools should expose the retained activity history delete descriptor.');
    assertSameValue(true, isset($manifest['roles']), 'The runtime manifest should expose the role resolution matrix.');
    assertSameValue(true, isset($manifest['families']), 'The runtime manifest should expose the active delivery matrix.');
    assertSameValue(true, isset($manifest['preload_urls']), 'The runtime manifest should expose runtime preload URLs.');
    assertSameValue(true, isset($manifest['preconnect_origins']), 'The runtime manifest should expose runtime preconnect origins.');
    assertSameValue(true, isset($manifest['external_stylesheets']), 'The runtime manifest should expose runtime external stylesheet descriptors.');
    assertSameValue(true, isset($manifest['integrations']), 'The runtime manifest should expose integration summaries.');
    assertSameValue(false, $healthChecks === [], 'Advanced Tools should include structured health checks for power-user diagnostics.');
    assertSameValue(false, $healthSummary === [], 'Advanced Tools should include a summarized health status.');
    assertSameValue('file', (string) ($manifest['delivery']['css_delivery_mode'] ?? ''), 'The runtime manifest should expose the current generated CSS delivery mode.');
    assertSameValue(
        (int) ($context['overview_metrics'][0]['value'] ?? -1),
        (int) ($manifest['library']['families'] ?? -2),
        'The runtime manifest should keep library counts aligned with the existing overview metrics.'
    );
};

$tests['admin_page_context_builder_surfaces_available_disabled_integrations_as_worth_reviewing_health'] = static function (): void {
    resetTestState();

    global $automaticCssSettings;

    $automaticCssSettings = [
        AcssIntegrationService::OPTION_HEADING_FONT_FAMILY => '',
        AcssIntegrationService::OPTION_TEXT_FONT_FAMILY => '',
    ];

    add_filter('tasty_fonts_etch_integration_available', static fn (): bool => true);
    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'auto_apply_roles' => false,
        'block_editor_font_library_sync_enabled' => false,
        'etch_integration_enabled' => false,
    ]);
    $services['settings']->saveAcssFontRoleSyncState(false, false, '', '');

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
    $advancedTools = is_array($context['advanced_tools'] ?? null) ? $context['advanced_tools'] : [];
    $healthChecks = is_array($advancedTools['health_checks'] ?? null) ? $advancedTools['health_checks'] : [];
    $checksBySlug = [];

    foreach ($healthChecks as $check) {
        if (is_array($check)) {
            $checksBySlug[(string) ($check['slug'] ?? '')] = $check;
        }
    }

    foreach (['integration_gutenberg', 'integration_etch', 'integration_automatic_css'] as $slug) {
        assertSameValue(true, isset($checksBySlug[$slug]), $slug . ' should appear in Overview health when the available integration is not delivering output.');
        assertSameValue('info', (string) ($checksBySlug[$slug]['severity'] ?? ''), $slug . ' should be Worth reviewing, not Action needed.');
        assertSameValue('review_integrations', (string) ($checksBySlug[$slug]['action']['slug'] ?? ''), $slug . ' should route users back to Integrations settings.');
    }
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
    assertSameValue('Needs Sitewide Delivery', (string) ($context['acss_integration']['status_label'] ?? ''), 'Automatic.css should use dependency-focused Sitewide Delivery badge copy.');
    assertContainsValue('Turn on Sitewide Delivery', (string) ($context['acss_integration']['status_copy'] ?? ''), 'Automatic.css waiting help should explain the Sitewide Delivery dependency.');
    assertSameValue(true, (bool) ($context['acss_integration']['control_disabled'] ?? false), 'Automatic.css should render as dependency-disabled while Sitewide Delivery is off.');
    assertSameValue(false, (bool) ($context['acss_integration']['control_checked'] ?? true), 'Automatic.css should not visually appear enabled while Sitewide Delivery is off.');
    assertSameValue('1', (string) ($context['acss_integration']['submitted_enabled_value'] ?? ''), 'Automatic.css should preserve the saved opt-in while the dependency disables the visible switch.');
    assertSameValue('', (string) ($context['acss_integration']['current']['heading'] ?? ''), 'Automatic.css integration context should expose the current heading font-family value.');
    assertSameValue('', (string) ($context['acss_integration']['current']['body'] ?? ''), 'Automatic.css integration context should expose the current text font-family value.');
};

$tests['admin_page_context_builder_reports_integration_dependencies_separately_from_live_delivery'] = static function (): void {
    $buildContext = static function (callable $setup): array {
        resetTestState();
        add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

        $services = makeServiceGraph();
        $setup($services);

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

        return $builder->build();
    };

    $configuredContext = $buildContext(static function (array $services): void {
        $services['settings']->saveSettings([
            'block_editor_font_library_sync_enabled' => '1',
            'bricks_integration_enabled' => '1',
        ]);
    });

    assertSameValue('Needs Sitewide Delivery', (string) ($configuredContext['gutenberg_integration']['status_label'] ?? ''), 'Enabled WordPress integration setup should explain the missing Sitewide Delivery dependency before publishing.');
    assertSameValue('Needs Sitewide Delivery', (string) ($configuredContext['bricks_integration']['status_label'] ?? ''), 'Enabled Bricks setup should explain the missing Sitewide Delivery dependency before publishing.');
    assertSameValue('1', (string) ($configuredContext['gutenberg_integration']['submitted_enabled_value'] ?? ''), 'The saved WordPress integration opt-in should be preserved while Sitewide Delivery disables the visible control.');
    assertSameValue('1', (string) ($configuredContext['bricks_integration']['submitted_enabled_value'] ?? ''), 'The saved Bricks integration opt-in should be preserved while Sitewide Delivery disables the visible control.');

    $liveContext = $buildContext(static function (array $services): void {
        global $automaticCssSettings;

        $automaticCssSettings = [
            AcssIntegrationService::OPTION_HEADING_FONT_FAMILY => AcssIntegrationService::DESIRED_HEADING_VALUE,
            AcssIntegrationService::OPTION_TEXT_FONT_FAMILY => AcssIntegrationService::DESIRED_TEXT_VALUE,
            AcssIntegrationService::OPTION_HEADING_FONT_WEIGHT => AcssIntegrationService::DESIRED_HEADING_WEIGHT_VALUE,
            AcssIntegrationService::OPTION_TEXT_FONT_WEIGHT => AcssIntegrationService::DESIRED_TEXT_WEIGHT_VALUE,
        ];

        $services['settings']->setAutoApplyRoles(true);
        $services['settings']->saveAcssFontRoleSyncState(true, true, '', '', '', '');
        $services['settings']->saveSettings([
            'bricks_integration_enabled' => '1',
            'bricks_theme_styles_sync_enabled' => '1',
        ]);
        $services['bricks_integration']->applyThemeStylesSync();
    });

    assertSameValue('Live', (string) ($liveContext['acss_integration']['status_label'] ?? ''), 'Automatic.css should claim Live only when Sitewide delivery is on and mapping is applied.');
    assertContainsValue('Sitewide delivery is distributing', (string) ($liveContext['acss_integration']['status_copy'] ?? ''), 'Live Automatic.css help should name Sitewide delivery as the delivery path.');

    $view = (new AdminPageViewBuilder(new Storage()))->build($liveContext);
    assertSameValue('Live', (string) ($view['bricksIntegration']['theme_styles']['ui']['status_label'] ?? ''), 'Bricks Theme Style role output should render as Live when the applied mapping matches.');
    assertContainsValue('Sitewide delivery is distributing', (string) ($view['bricksIntegration']['theme_styles']['ui']['status_help'] ?? ''), 'Bricks live help should name Sitewide delivery as the delivery path.');

    $staleContext = $buildContext(static function (array $services): void {
        global $automaticCssSettings;

        $automaticCssSettings = [
            AcssIntegrationService::OPTION_HEADING_FONT_FAMILY => 'Inter',
            AcssIntegrationService::OPTION_TEXT_FONT_FAMILY => 'System UI',
            AcssIntegrationService::OPTION_HEADING_FONT_WEIGHT => '700',
            AcssIntegrationService::OPTION_TEXT_FONT_WEIGHT => '400',
        ];

        $services['settings']->setAutoApplyRoles(true);
        $services['settings']->saveAcssFontRoleSyncState(true, true, '', '', '', '');
        $services['settings']->saveSettings([
            'bricks_integration_enabled' => '1',
            'bricks_theme_styles_sync_enabled' => '1',
        ]);
        update_option(BricksIntegrationService::OPTION_SYNC_STATE, [
            'theme_styles' => [
                'applied' => true,
                'target_mode' => BricksIntegrationService::TARGET_MODE_MANAGED,
                'target_style_id' => BricksIntegrationService::MANAGED_THEME_STYLE_ID,
            ],
        ], false);
    });

    assertSameValue('Needs reapply', (string) ($staleContext['acss_integration']['status_label'] ?? ''), 'Automatic.css stale mapping should render the Needs reapply badge.');
    assertSameValue('out_of_sync', (string) ($staleContext['bricks_integration']['theme_styles']['status'] ?? ''), 'Bricks Theme Style stale mapping should keep a distinct out-of-sync state for presentation.');

    $staleView = (new AdminPageViewBuilder(new Storage()))->build($staleContext);
    assertSameValue('Needs reapply', (string) ($staleView['bricksIntegration']['theme_styles']['ui']['status_label'] ?? ''), 'Bricks Theme Style stale mapping should render the Needs reapply badge.');
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

$tests['admin_page_context_builder_uses_catalog_family_count_for_delete_library_summary'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
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
    $services['imports']->saveProfile(
        'Lora',
        'lora',
        [
            'id' => 'lora-static',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'static',
            'variants' => ['regular'],
            'faces' => [],
        ],
        'library_only',
        true
    );

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
    $summary = (string) ($context['developer_tool_statuses']['wipe_managed_font_library']['summary'] ?? '');

    assertSameValue('2 managed font families in the library.', $summary, 'The delete-library summary should use the catalog family count instead of a missing total key.');
};

$tests['admin_controller_applies_acss_font_mapping_when_sync_is_enabled'] = static function (): void {
    resetTestState();

    global $automaticCssSettings;

    $automaticCssSettings = [
        'heading-font-family' => 'Inter, sans-serif',
        'text-font-family' => 'system-ui, sans-serif',
        'heading-weight' => '700',
        'text-font-weight' => '400',
    ];

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $services['settings']->setAutoApplyRoles(true);

    $result = $services['controller']->saveSettingsValues([
        'acss_font_role_sync_enabled' => '1',
    ]);

    assertSameValue('var(--font-heading)', (string) ($automaticCssSettings['heading-font-family'] ?? ''), 'Enabling Automatic.css sync should push the heading role variable into Automatic.css.');
    assertSameValue('var(--font-body)', (string) ($automaticCssSettings['text-font-family'] ?? ''), 'Enabling Automatic.css sync should push the body role variable into Automatic.css.');
    assertSameValue('var(--font-heading-weight)', (string) ($automaticCssSettings['heading-weight'] ?? ''), 'Enabling Automatic.css sync should push the heading weight variable into Automatic.css.');
    assertSameValue('var(--font-body-weight)', (string) ($automaticCssSettings['text-font-weight'] ?? ''), 'Enabling Automatic.css sync should push the body weight variable into Automatic.css.');
    assertSameValue(true, (bool) ($result['settings']['acss_font_role_sync_applied'] ?? false), 'Controller saves should mark Automatic.css sync as applied after the ACSS settings update succeeds.');
    assertSameValue(true, !empty($result['settings']['extended_variable_role_weight_vars_enabled']), 'Enabling Automatic.css sync should force role weight variables on so the mapped ACSS values always resolve.');
    assertSameValue('Inter, sans-serif', (string) ($result['settings']['acss_font_role_sync_previous_heading_font_family'] ?? ''), 'The previous ACSS heading value should be backed up before Tasty Fonts overwrites it.');
    assertSameValue('700', (string) ($result['settings']['acss_font_role_sync_previous_heading_font_weight'] ?? ''), 'The previous ACSS heading weight value should be backed up before Tasty Fonts overwrites it.');
    assertContainsValue('Automatic.css now uses Tasty Fonts role variables', (string) ($result['message'] ?? ''), 'The settings response should explain that Automatic.css is now mapped to Tasty Fonts variables.');
};

$tests['admin_controller_does_not_reenable_explicitly_disabled_acss_sync_after_restoring_defaults'] = static function (): void {
    resetTestState();

    global $automaticCssSettings;

    $automaticCssSettings = [
        'heading-font-family' => '',
        'text-font-family' => '',
        'heading-weight' => '',
        'text-font-weight' => '',
    ];

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'acss_font_role_sync_enabled' => '0',
        'acss_font_role_sync_opted_out' => '1',
    ]);

    invokePrivateMethod($services['controller'], 'initializeDetectedIntegrations');

    $saved = $services['settings']->getSettings();

    assertSameValue(false, $saved['acss_font_role_sync_enabled'], 'Explicitly disabled Automatic.css sync should stay disabled after its managed values were cleared back to defaults.');
    assertSameValue(false, $saved['acss_font_role_sync_applied'], 'Explicitly disabled Automatic.css sync should remain unapplied after initialization runs.');
    assertSameValue('', (string) ($automaticCssSettings['heading-font-family'] ?? ''), 'Initialization should leave restored Automatic.css heading defaults untouched after an explicit opt-out.');
    assertSameValue('', (string) ($automaticCssSettings['text-font-family'] ?? ''), 'Initialization should leave restored Automatic.css body defaults untouched after an explicit opt-out.');
};

$tests['admin_controller_preserves_unavailable_integration_detection_state_on_settings_save'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'etch_integration_enabled' => null,
        'bricks_integration_enabled' => null,
        'oxygen_integration_enabled' => null,
        'acss_font_role_sync_enabled' => null,
    ];

    add_filter('tasty_fonts_etch_integration_available', static fn (): bool => false);
    add_filter('tasty_fonts_bricks_integration_available', static fn (): bool => false);
    add_filter('tasty_fonts_oxygen_integration_available', static fn (): bool => false);
    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => false);

    $services = makeServiceGraph();
    $saved = $services['settings']->getSettings();

    assertSameValue(null, $saved['etch_integration_enabled'], 'Etch integration should start unconfigured for this regression test.');
    assertSameValue(null, $saved['bricks_integration_enabled'], 'Bricks integration should start unconfigured for this regression test.');
    assertSameValue(null, $saved['oxygen_integration_enabled'], 'Oxygen integration should start unconfigured for this regression test.');
    assertSameValue(null, $saved['acss_font_role_sync_enabled'], 'Automatic.css integration should start unconfigured for this regression test.');

    $result = $services['controller']->saveSettingsValues([
        'etch_integration_enabled' => '0',
        'bricks_integration_enabled' => '0',
        'oxygen_integration_enabled' => '0',
        'acss_font_role_sync_enabled' => '0',
    ]);

    assertSameValue(null, $result['settings']['etch_integration_enabled'], 'Saving settings while Etch is unavailable should preserve its unconfigured detection state so later installs can still auto-enable it.');
    assertSameValue(null, $result['settings']['bricks_integration_enabled'], 'Saving settings while Bricks is unavailable should preserve its unconfigured detection state so later installs can still auto-enable it.');
    assertSameValue(null, $result['settings']['oxygen_integration_enabled'], 'Saving settings while Oxygen is unavailable should preserve its unconfigured detection state so later installs can still auto-enable it.');
    assertSameValue(null, $result['settings']['acss_font_role_sync_enabled'], 'Saving settings while Automatic.css is unavailable should preserve its unconfigured detection state so later installs can still auto-enable it.');
};

$tests['admin_controller_clears_stale_acss_managed_values_when_sync_is_off_without_backups'] = static function (): void {
    resetTestState();

    global $automaticCssSettings;

    $automaticCssSettings = [
        'heading-font-family' => 'var(--font-heading)',
        'text-font-family' => 'var(--font-body)',
        'heading-weight' => 'var(--font-heading-weight)',
        'text-font-weight' => 'var(--font-body-weight)',
    ];

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $services['settings']->setAutoApplyRoles(true);

    $result = $services['controller']->saveSettingsValues([
        'acss_font_role_sync_enabled' => '0',
    ]);

    assertSameValue('', (string) ($automaticCssSettings['heading-font-family'] ?? ''), 'Saving Automatic.css sync off without stored backups should clear stale managed heading values so Automatic.css can fall back to its defaults.');
    assertSameValue('', (string) ($automaticCssSettings['text-font-family'] ?? ''), 'Saving Automatic.css sync off without stored backups should clear stale managed body values so Automatic.css can fall back to its defaults.');
    assertSameValue('', (string) ($automaticCssSettings['heading-weight'] ?? ''), 'Saving Automatic.css sync off without stored backups should clear stale managed heading weights so Automatic.css can fall back to its defaults.');
    assertSameValue('', (string) ($automaticCssSettings['text-font-weight'] ?? ''), 'Saving Automatic.css sync off without stored backups should clear stale managed body weights so Automatic.css can fall back to its defaults.');
    assertSameValue(false, (bool) ($result['settings']['acss_font_role_sync_applied'] ?? true), 'Saving Automatic.css sync off without stored backups should keep the sync marked unapplied.');
    assertContainsValue('use its defaults again', (string) ($result['message'] ?? ''), 'Saving Automatic.css sync off without stored backups should explain that stale managed values were cleared.');
};

$tests['admin_controller_clears_stale_acss_managed_backup_values_when_sync_is_disabled'] = static function (): void {
    resetTestState();

    global $automaticCssSettings;

    $automaticCssSettings = [
        'heading-font-family' => 'var(--font-heading)',
        'text-font-family' => 'var(--font-body)',
        'heading-weight' => 'var(--font-heading-weight)',
        'text-font-weight' => 'var(--font-body-weight)',
    ];

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveAcssFontRoleSyncState(
        true,
        true,
        'var(--font-heading)',
        'var(--font-body)',
        'var(--font-heading-weight)',
        'var(--font-body-weight)'
    );

    $result = $services['controller']->saveSettingsValues([
        'acss_font_role_sync_enabled' => '0',
    ]);

    assertSameValue('', (string) ($automaticCssSettings['heading-font-family'] ?? ''), 'Disabling Automatic.css sync should not restore a stale Tasty-managed heading variable from backup.');
    assertSameValue('', (string) ($automaticCssSettings['text-font-family'] ?? ''), 'Disabling Automatic.css sync should not restore a stale Tasty-managed body variable from backup.');
    assertSameValue('', (string) ($automaticCssSettings['heading-weight'] ?? ''), 'Disabling Automatic.css sync should not restore a stale Tasty-managed heading weight variable from backup.');
    assertSameValue('', (string) ($automaticCssSettings['text-font-weight'] ?? ''), 'Disabling Automatic.css sync should not restore a stale Tasty-managed body weight variable from backup.');
    assertSameValue(false, (bool) ($result['settings']['acss_font_role_sync_applied'] ?? true), 'Disabling Automatic.css sync should still mark the integration unapplied after stale managed backups are cleared.');
    assertSameValue('', (string) ($result['settings']['acss_font_role_sync_previous_heading_font_family'] ?? 'unexpected'), 'Disabling Automatic.css sync should clear stale managed heading backups from Tasty state.');
    assertSameValue('', (string) ($result['settings']['acss_font_role_sync_previous_text_font_family'] ?? 'unexpected'), 'Disabling Automatic.css sync should clear stale managed body backups from Tasty state.');
    assertSameValue('', (string) ($result['settings']['acss_font_role_sync_previous_heading_font_weight'] ?? 'unexpected'), 'Disabling Automatic.css sync should clear stale managed heading weight backups from Tasty state.');
    assertSameValue('', (string) ($result['settings']['acss_font_role_sync_previous_text_font_weight'] ?? 'unexpected'), 'Disabling Automatic.css sync should clear stale managed body weight backups from Tasty state.');
    assertContainsValue('Previous Automatic.css font settings were restored.', (string) ($result['message'] ?? ''), 'Disabling Automatic.css sync from an applied state should still report the restore action.');
};

$tests['admin_controller_restores_previous_acss_font_values_when_sitewide_roles_are_disabled'] = static function (): void {
    resetTestState();

    global $automaticCssSettings;

    $automaticCssSettings = [
        'heading-font-family' => 'var(--font-heading)',
        'text-font-family' => 'var(--font-body)',
        'heading-weight' => 'var(--font-heading-weight)',
        'text-font-weight' => 'var(--font-body-weight)',
    ];

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $settings = $services['settings']->saveAcssFontRoleSyncState(true, true, 'Inter, sans-serif', 'system-ui, sans-serif', '700', '400');
    $settings = $services['settings']->setAutoApplyRoles(false);
    $result = invokePrivateMethod($services['controller'], 'syncAcssIntegrationForRuntimeState', [$settings]);
    $saved = $services['settings']->getSettings();

    assertSameValue('Inter, sans-serif', (string) ($automaticCssSettings['heading-font-family'] ?? ''), 'Disabling sitewide roles should restore the previous Automatic.css heading font-family value.');
    assertSameValue('system-ui, sans-serif', (string) ($automaticCssSettings['text-font-family'] ?? ''), 'Disabling sitewide roles should restore the previous Automatic.css text font-family value.');
    assertSameValue('700', (string) ($automaticCssSettings['heading-weight'] ?? ''), 'Disabling sitewide roles should restore the previous Automatic.css heading font-weight value.');
    assertSameValue('400', (string) ($automaticCssSettings['text-font-weight'] ?? ''), 'Disabling sitewide roles should restore the previous Automatic.css text font-weight value.');
    assertSameValue(false, (bool) ($saved['acss_font_role_sync_applied'] ?? true), 'Automatic.css sync should mark itself unapplied after restoring the previous ACSS values.');
    assertContainsValue('restored to its previous font settings', (string) $result, 'The runtime sync helper should explain that ACSS was restored after sitewide roles were turned off.');
};

$tests['admin_controller_turns_off_acss_sync_when_acss_drifts_outside_tasty_fonts'] = static function (): void {
    resetTestState();

    global $automaticCssSettings;

    $automaticCssSettings = [
        'heading-font-family' => '',
        'text-font-family' => '',
        'heading-weight' => '',
        'text-font-weight' => '',
    ];

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveAcssFontRoleSyncState(true, true, 'Inter, sans-serif', 'system-ui, sans-serif', '700', '400');

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
        $transientStore[TransientKey::forSite('tasty_fonts_regenerate_css_queued')] ?? null,
        'Queued CSS regeneration should keep the short-lived guard transient until the write runs.'
    );
};

$tests['runtime_service_skips_frontend_assets_while_sitewide_delivery_is_disabled'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $inlineStyles;

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
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['settings']->saveSettings([
        'auto_apply_roles' => false,
        'class_output_enabled' => true,
    ]);

    $services['runtime']->enqueueFrontend();

    assertSameValue(false, array_key_exists('tasty-fonts-frontend', $enqueuedStyles), 'Frontend runtime should not enqueue the generated stylesheet while sitewide delivery is disabled.');
    assertSameValue([], $inlineStyles, 'Frontend runtime should not add inline generated CSS while sitewide delivery is disabled.');
};

$tests['runtime_service_enqueues_frontend_assets_when_sitewide_delivery_is_enabled'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;

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
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['settings']->setAutoApplyRoles(true);

    $services['runtime']->enqueueFrontend();

    assertSameValue(true, array_key_exists('tasty-fonts-frontend', $enqueuedStyles), 'Frontend runtime should enqueue the generated stylesheet once sitewide delivery is enabled.');
};

$tests['runtime_service_enqueues_adobe_stylesheet_and_exposes_it_to_etch_canvas'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $currentUserCapabilities;
    global $localizedScripts;
    global $remoteGetResponses;

    add_filter('tasty_fonts_etch_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $currentUserCapabilities['edit_posts'] = true;
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

$tests['runtime_service_exposes_acss_inline_weight_styles_to_etch_canvas'] = static function (): void {
    resetTestState();

    global $currentUserCapabilities;
    global $localizedScripts;

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);
    add_filter('tasty_fonts_etch_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $currentUserCapabilities['edit_posts'] = true;
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings([
        'acss_font_role_sync_enabled' => true,
    ]);
    $services['settings']->saveAcssFontRoleSyncState(true, true, 'system-ui', 'system-ui', '700', '400');
    $_GET['etch'] = '1';

    $services['runtime']->enqueueFrontend();
    $canvasInlineCss = (array) ($localizedScripts['tasty-fonts-canvas']['data']['inlineCss'] ?? []);
    $combinedInlineCss = implode("\n", $canvasInlineCss);

    assertContainsValue('body{font-family:var(--font-body);font-weight:var(--font-body-weight);}', $combinedInlineCss, 'Etch canvas runtime data should include the ACSS-managed body weight bridge CSS.');
    assertContainsValue('font-family:var(--font-heading);font-weight:var(--font-heading-weight);', $combinedInlineCss, 'Etch canvas runtime data should include the ACSS-managed heading weight bridge CSS.');
};

$tests['runtime_service_exposes_generic_role_bridge_styles_to_etch_canvas_when_minimal_output_is_active'] = static function (): void {
    resetTestState();

    global $currentUserCapabilities;
    global $localizedScripts;

    add_filter('tasty_fonts_etch_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $currentUserCapabilities['edit_posts'] = true;
    $services['settings']->saveAppliedRoles(
        [
            'heading' => '',
            'body' => '',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        []
    );
    $services['settings']->setAutoApplyRoles(true);
    $_GET['etch'] = '1';

    $services['runtime']->enqueueFrontend();
    $canvasInlineCss = (array) ($localizedScripts['tasty-fonts-canvas']['data']['inlineCss'] ?? []);
    $combinedInlineCss = implode("\n", $canvasInlineCss);

    assertContainsValue('body {', $combinedInlineCss, 'Etch canvas runtime data should expose the generic body role bridge when Minimal output keeps the generated stylesheet variable-only.');
    assertContainsValue('font-family: var(--font-body) !important;', $combinedInlineCss, 'Etch canvas runtime data should bridge the live body role variable into the canvas preview.');
    assertContainsValue('font-family: var(--font-heading) !important;', $combinedInlineCss, 'Etch canvas runtime data should bridge the live heading role variable into the canvas preview.');
};

$tests['runtime_service_exposes_acss_runtime_stylesheet_to_etch_canvas_when_sync_is_active'] = static function (): void {
    resetTestState();

    global $currentUserCapabilities;
    global $localizedScripts;
    global $registeredStyles;

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);
    add_filter('tasty_fonts_etch_integration_available', static fn (): bool => true);

    $registeredStyles[AcssIntegrationService::RUNTIME_STYLESHEET_HANDLE] = [
        'src' => 'https://example.test/wp-content/uploads/automatic-css/automatic.css',
        'deps' => [],
        'ver' => '1775939707',
    ];

    $services = makeServiceGraph();
    $currentUserCapabilities['edit_posts'] = true;
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings([
        'acss_font_role_sync_enabled' => true,
    ]);
    $services['settings']->saveAcssFontRoleSyncState(true, true, 'system-ui', 'system-ui', '700', '400');
    $_GET['etch'] = '1';

    $services['runtime']->enqueueFrontend();
    $canvasStylesheetUrls = (array) ($localizedScripts['tasty-fonts-canvas']['data']['stylesheetUrls'] ?? []);

    assertSameValue(
        true,
        in_array('https://example.test/wp-content/uploads/automatic-css/automatic.css', $canvasStylesheetUrls, true),
        'Etch canvas runtime data should include the Automatic.css runtime stylesheet when Tasty manages the ACSS font mapping.'
    );
};

$tests['runtime_service_exposes_acss_runtime_stylesheet_to_etch_canvas_when_sync_is_inactive_but_acss_is_available'] = static function (): void {
    resetTestState();

    global $currentUserCapabilities;
    global $localizedScripts;
    global $registeredStyles;

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);
    add_filter('tasty_fonts_etch_integration_available', static fn (): bool => true);

    $registeredStyles[AcssIntegrationService::RUNTIME_STYLESHEET_HANDLE] = [
        'src' => 'https://example.test/wp-content/uploads/automatic-css/automatic.css',
        'deps' => [],
        'ver' => '1775939707',
    ];

    $services = makeServiceGraph();
    $currentUserCapabilities['edit_posts'] = true;
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings([
        'acss_font_role_sync_enabled' => false,
    ]);
    $_GET['etch'] = '1';

    $services['runtime']->enqueueFrontend();
    $canvasStylesheetUrls = (array) ($localizedScripts['tasty-fonts-canvas']['data']['stylesheetUrls'] ?? []);

    assertSameValue(
        true,
        in_array('https://example.test/wp-content/uploads/automatic-css/automatic.css', $canvasStylesheetUrls, true),
        'Etch canvas runtime data should mirror the live Automatic.css runtime stylesheet even when Tasty ACSS sync is off.'
    );
};

$tests['runtime_service_ignores_public_etch_query_parameters_without_editor_access'] = static function (): void {
    resetTestState();

    global $enqueuedScripts;
    global $isUserLoggedIn;
    global $localizedScripts;

    add_filter('tasty_fonts_etch_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $isUserLoggedIn = false;
    $_GET['etch'] = '1';

    $services['runtime']->enqueueFrontend();

    assertSameValue(
        false,
        array_key_exists('tasty-fonts-canvas', $enqueuedScripts),
        'Public requests should not be able to trigger Etch canvas bridge assets with the etch query parameter alone.'
    );
    assertSameValue(
        false,
        array_key_exists('tasty-fonts-canvas', $localizedScripts),
        'Public requests should not receive Etch canvas runtime stylesheet URLs through the query parameter alone.'
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

$tests['runtime_service_enqueues_acss_runtime_stylesheet_for_gutenberg_iframe_when_sync_is_active'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $isAdminRequest;
    global $registeredStyles;

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $registeredStyles[AcssIntegrationService::RUNTIME_STYLESHEET_HANDLE] = [
        'src' => 'https://example.test/wp-content/uploads/automatic-css/automatic.css',
        'deps' => [],
        'ver' => '1775939707',
    ];

    $services = makeServiceGraph();
    $isAdminRequest = true;
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings([
        'acss_font_role_sync_enabled' => true,
    ]);
    $services['settings']->saveAcssFontRoleSyncState(true, true, 'system-ui', 'system-ui', '700', '400');

    $services['runtime']->enqueueBlockEditorContent();

    assertSameValue(
        'https://example.test/wp-content/uploads/automatic-css/automatic.css',
        (string) ($enqueuedStyles['tasty-fonts-acss-runtime-editor-content']['src'] ?? ''),
        'Gutenberg iframe styles should also enqueue the Automatic.css runtime stylesheet when Tasty manages the ACSS font mapping.'
    );
    assertSameValue(
        '1775939707',
        (string) ($enqueuedStyles['tasty-fonts-acss-runtime-editor-content']['ver'] ?? ''),
        'Gutenberg iframe styles should preserve the Automatic.css runtime stylesheet version for cache-busting.'
    );
};

$tests['runtime_service_enqueues_acss_runtime_stylesheet_for_gutenberg_iframe_when_sync_is_inactive_but_acss_is_available'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $isAdminRequest;
    global $registeredStyles;

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $registeredStyles[AcssIntegrationService::RUNTIME_STYLESHEET_HANDLE] = [
        'src' => 'https://example.test/wp-content/uploads/automatic-css/automatic.css',
        'deps' => [],
        'ver' => '1775939707',
    ];

    $services = makeServiceGraph();
    $isAdminRequest = true;
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings([
        'acss_font_role_sync_enabled' => false,
    ]);

    $services['runtime']->enqueueBlockEditorContent();

    assertSameValue(
        'https://example.test/wp-content/uploads/automatic-css/automatic.css',
        (string) ($enqueuedStyles['tasty-fonts-acss-runtime-editor-content']['src'] ?? ''),
        'Gutenberg iframe styles should mirror the live Automatic.css runtime stylesheet even when Tasty ACSS sync is off.'
    );
    assertSameValue(
        '1775939707',
        (string) ($enqueuedStyles['tasty-fonts-acss-runtime-editor-content']['ver'] ?? ''),
        'Gutenberg iframe styles should preserve the Automatic.css runtime stylesheet version when mirroring ACSS for editor parity.'
    );
};

$tests['runtime_service_does_not_enqueue_acss_runtime_stylesheet_for_gutenberg_iframe_when_sitewide_roles_are_off'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $isAdminRequest;
    global $registeredStyles;

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $registeredStyles[AcssIntegrationService::RUNTIME_STYLESHEET_HANDLE] = [
        'src' => 'https://example.test/wp-content/uploads/automatic-css/automatic.css',
        'deps' => [],
        'ver' => '1775939707',
    ];

    $services = makeServiceGraph();
    $isAdminRequest = true;
    $services['settings']->saveSettings([
        'auto_apply_roles' => false,
        'acss_font_role_sync_enabled' => false,
    ]);

    $services['runtime']->enqueueBlockEditorContent();

    assertSameValue(
        false,
        isset($enqueuedStyles['tasty-fonts-acss-runtime-editor-content']),
        'Gutenberg iframe styles should not mirror the Automatic.css runtime stylesheet while sitewide role delivery remains off.'
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

$tests['runtime_service_builds_link_header_values_for_primary_preloads'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
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
            'variants' => ['700'],
            'faces' => [
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

    $headers = $services['runtime']->getPreloadLinkHeaderValues();

    assertContainsValue(
        '</wp-content/uploads/fonts/inter/Inter-700.woff2>; rel=preload; as=font; type="font/woff2"; crossorigin',
        implode("\n", $headers),
        'RuntimeService should expose Link header values for primary preload candidates.'
    );
    assertContainsValue(
        '</wp-content/uploads/fonts/lora/Lora-400.woff2>; rel=preload; as=font; type="font/woff2"; crossorigin',
        implode("\n", $headers),
        'RuntimeService should build Link header values for both heading and body preload fonts.'
    );
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

    assertContainsValue('href="/wp-content/uploads/fonts/inter/Inter-Variable.woff2"', $output, 'Frontend preload output should follow the family active delivery.');
    assertNotContainsValue('href="/wp-content/uploads/fonts/inter/Inter-700-static.woff2"', $output, 'Frontend preload output should not use inactive delivery profiles.');
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
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular'],
            'faces' => [],
        ],
        'published',
        true
    );
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
    $services['settings']->saveSettings([
        'preload_primary_fonts' => '0',
        'remote_connection_hints' => '0',
    ]);

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

$tests['runtime_asset_planner_omits_registered_axis_defaults_from_editor_font_faces'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Inter Variable',
        'inter-variable',
        [
            'id' => 'local-self_hosted-variable',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'variable',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter Variable',
                'weight' => '300..800',
                'style' => 'normal',
                'is_variable' => true,
                'axes' => [
                    'WGHT' => ['min' => '300', 'default' => '400', 'max' => '800'],
                    'OPSZ' => ['min' => '8', 'default' => '14', 'max' => '32'],
                    'XTRA' => ['min' => '400', 'default' => '500', 'max' => '600'],
                ],
                'variation_defaults' => [
                    'WGHT' => '650',
                    'OPSZ' => '14',
                    'XTRA' => '500',
                ],
                'files' => ['woff2' => 'upload/inter-variable/Inter Variable-VariableFont.woff2'],
                'paths' => ['woff2' => 'upload/inter-variable/Inter Variable-VariableFont.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['settings']->saveSettings(['variable_fonts_enabled' => '1']);
    $services['settings']->setAutoApplyRoles(true);

    $families = $services['planner']->getEditorFontFamilies();
    $face = (array) (($families[0]['fontFace'][0] ?? null) ?: []);

    assertSameValue('300 800', (string) ($face['fontWeight'] ?? ''), 'Editor font-face payloads should keep the variable weight range available for live weight selection.');
    assertSameValue('"XTRA" 500', (string) ($face['fontVariationSettings'] ?? ''), 'Editor font-face payloads should keep custom axis defaults.');
    assertSameValue(false, str_contains((string) ($face['fontVariationSettings'] ?? ''), '"wght"'), 'Editor font-face payloads should not pin the registered WGHT axis.');
    assertSameValue(false, str_contains((string) ($face['fontVariationSettings'] ?? ''), '"opsz"'), 'Editor font-face payloads should not pin the registered OPSZ axis.');
};

$tests['block_editor_font_library_sync_omits_registered_axis_defaults_from_face_payloads'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;
    global $remoteRequestResponses;
    global $remoteRequestCalls;

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Inter Variable',
        'inter-variable',
        [
            'id' => 'local-self_hosted-variable',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'variable',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter Variable',
                'weight' => '300..800',
                'style' => 'normal',
                'is_variable' => true,
                'axes' => [
                    'WGHT' => ['min' => '300', 'default' => '400', 'max' => '800'],
                    'OPSZ' => ['min' => '8', 'default' => '14', 'max' => '32'],
                    'XTRA' => ['min' => '400', 'default' => '500', 'max' => '600'],
                ],
                'variation_defaults' => [
                    'WGHT' => '650',
                    'OPSZ' => '14',
                    'XTRA' => '500',
                ],
                'files' => ['woff2' => 'upload/inter-variable/Inter Variable-VariableFont.woff2'],
                'paths' => ['woff2' => 'upload/inter-variable/Inter Variable-VariableFont.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['settings']->saveSettings([
        'variable_fonts_enabled' => '1',
        'block_editor_font_library_sync_enabled' => '1',
    ]);
    $services['settings']->setAutoApplyRoles(true);

    $baseUrl = 'https://example.test/wp-json/wp/v2/font-families';
    $findUrl = $baseUrl . '?slug=tasty-fonts-inter-variable&context=edit';
    $remoteGetResponses[$findUrl] = [
        'response' => ['code' => 200],
        'body' => wp_json_encode([]),
    ];
    $remoteRequestResponses['POST ' . $baseUrl] = [
        'response' => ['code' => 201],
        'body' => wp_json_encode(['id' => 321]),
    ];
    $remoteRequestResponses['POST ' . $baseUrl . '/321/font-faces'] = [
        'response' => ['code' => 201],
        'body' => wp_json_encode(['id' => 654]),
    ];

    $services['block_editor_font_library']->syncImportedFamily(['family' => 'Inter Variable'], 'local');

    assertSameValue(2, count($remoteRequestCalls), 'Block Editor sync should create one family and one font face for the managed variable family.');

    $faceRequest = (array) ($remoteRequestCalls[1] ?? []);
    $faceBody = json_decode((string) (($faceRequest['args']['body']['font_face_settings'] ?? '') ?: ''), true);

    assertSameValue('300 800', (string) ($faceBody['fontWeight'] ?? ''), 'Block Editor sync should preserve the variable weight range for the self-hosted face.');
    assertSameValue('"XTRA" 500', (string) ($faceBody['fontVariationSettings'] ?? ''), 'Block Editor sync should preserve custom axis defaults on the synced face payload.');
    assertSameValue(false, str_contains((string) ($faceBody['fontVariationSettings'] ?? ''), '"wght"'), 'Block Editor sync should not pin the registered WGHT axis in synced face payloads.');
    assertSameValue(false, str_contains((string) ($faceBody['fontVariationSettings'] ?? ''), '"opsz"'), 'Block Editor sync should not pin the registered OPSZ axis in synced face payloads.');
};

$tests['settings_repository_tracks_builder_integration_state'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $defaults = $settings->getSettings();

    assertSameValue(null, $defaults['etch_integration_enabled'], 'Etch integration should start unconfigured so supported sites can default on once detected.');
    assertSameValue(null, $defaults['bricks_integration_enabled'], 'Bricks integration should start unconfigured so supported sites can default on once detected.');
    assertSameValue(null, $defaults['oxygen_integration_enabled'], 'Oxygen integration should start unconfigured so supported sites can default on once detected.');
    assertSameValue(false, $defaults['bricks_theme_styles_sync_enabled'], 'Bricks Theme Style sync should default off until the user opts into deeper integration.');
    assertSameValue('managed', $defaults['bricks_theme_style_target_mode'], 'Bricks Theme Style sync should default to the managed target mode.');
    assertSameValue('managed', $defaults['bricks_theme_style_target_id'], 'Bricks Theme Style sync should default to the managed Tasty Theme Style target.');
    assertSameValue(false, $defaults['bricks_disable_google_fonts_enabled'], 'Bricks Google font disabling should default off until the user opts into Tasty-only picker mode.');

    $saved = $settings->saveSettings([
        'etch_integration_enabled' => '0',
        'bricks_integration_enabled' => '1',
        'bricks_theme_styles_sync_enabled' => '1',
        'bricks_theme_style_target_mode' => 'selected',
        'bricks_theme_style_target_id' => 'sitewide-primary',
        'bricks_disable_google_fonts_enabled' => '1',
        'oxygen_integration_enabled' => '0',
    ]);

    assertSameValue(false, $saved['etch_integration_enabled'], 'Saving the Etch integration toggle should persist an explicit disabled state.');
    assertSameValue(true, $saved['bricks_integration_enabled'], 'Saving the Bricks integration toggle should persist an explicit enabled state.');
    assertSameValue(true, $saved['bricks_theme_styles_sync_enabled'], 'Saving Bricks Theme Style sync should persist an explicit enabled state.');
    assertSameValue('selected', $saved['bricks_theme_style_target_mode'], 'Saving the Bricks Theme Style mode should persist the selected targeting strategy.');
    assertSameValue('sitewide-primary', $saved['bricks_theme_style_target_id'], 'Saving the Bricks Theme Style target should persist the selected style ID.');
    assertSameValue(true, $saved['bricks_disable_google_fonts_enabled'], 'Saving Bricks Google font disabling should persist an explicit enabled state.');
    assertSameValue(false, $saved['oxygen_integration_enabled'], 'Saving the Oxygen integration toggle should persist an explicit disabled state.');
};

$tests['admin_controller_creates_a_managed_bricks_theme_style_target_when_requested'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();

    $result = $services['controller']->saveSettingsValues([
        'bricks_create_theme_style' => '1',
    ]);
    $settings = $result['settings'];

    assertSameValue(true, $settings['bricks_integration_enabled'], 'Creating a managed Bricks Theme Style should turn on the master Bricks integration bridge.');
    assertSameValue(true, $settings['bricks_theme_styles_sync_enabled'], 'Creating a managed Bricks Theme Style should enable Bricks Theme Style sync.');
    assertSameValue(BricksIntegrationService::TARGET_MODE_MANAGED, $settings['bricks_theme_style_target_mode'], 'Creating a managed Bricks Theme Style should switch the target mode to the managed Tasty Theme Style.');
    assertSameValue(BricksIntegrationService::MANAGED_THEME_STYLE_ID, $settings['bricks_theme_style_target_id'], 'Creating a managed Bricks Theme Style should target the managed Tasty Theme Style.');
};

$tests['admin_controller_switches_bricks_theme_style_mode_back_to_managed_cleanly'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();
    $optionStore[BRICKS_DB_THEME_STYLES] = [
        'sitewide-primary' => [
            'label' => 'Primary',
            'settings' => [
                'typography' => [
                    'typographyBody' => ['font-family' => 'Inter'],
                    'typographyHeadings' => ['font-family' => 'Lora'],
                ],
            ],
        ],
        BricksIntegrationService::MANAGED_THEME_STYLE_ID => [
            'label' => 'Tasty Fonts',
            'settings' => [
                'typography' => [
                    'typographyBody' => ['font-family' => 'Georgia'],
                    'typographyHeadings' => ['font-family' => 'Merriweather'],
                ],
            ],
        ],
    ];

    $services['settings']->saveSettings([
        'bricks_integration_enabled' => '1',
        'bricks_theme_styles_sync_enabled' => '1',
        'bricks_theme_style_target_mode' => BricksIntegrationService::TARGET_MODE_SELECTED,
        'bricks_theme_style_target_id' => 'sitewide-primary',
    ]);
    $services['settings']->setAutoApplyRoles(true);
    $services['bricks_integration']->applyThemeStylesSync(BricksIntegrationService::TARGET_MODE_SELECTED, 'sitewide-primary');

    $result = $services['controller']->saveSettingsValues([
        'bricks_theme_style_target_mode' => BricksIntegrationService::TARGET_MODE_MANAGED,
        'bricks_theme_style_target_id' => 'sitewide-primary',
    ]);
    $syncState = $services['bricks_integration']->getSyncState();

    assertSameValue(
        BricksIntegrationService::TARGET_MODE_MANAGED,
        (string) ($result['settings']['bricks_theme_style_target_mode'] ?? ''),
        'Switching Bricks Theme Style mode back to managed should persist the managed target mode.'
    );
    assertSameValue(
        BricksIntegrationService::MANAGED_THEME_STYLE_ID,
        (string) ($result['settings']['bricks_theme_style_target_id'] ?? ''),
        'Switching Bricks Theme Style mode back to managed should discard the stale selected style ID.'
    );
    assertSameValue(
        BricksIntegrationService::TARGET_MODE_MANAGED,
        (string) ($syncState['theme_styles']['target_mode'] ?? ''),
        'Switching Bricks Theme Style mode back to managed should update the stored Bricks sync target mode.'
    );
    assertSameValue(
        BricksIntegrationService::MANAGED_THEME_STYLE_ID,
        (string) ($syncState['theme_styles']['target_style_id'] ?? ''),
        'Switching Bricks Theme Style mode back to managed should update the stored Bricks sync target ID.'
    );
    assertSameValue(
        BricksIntegrationService::DESIRED_BODY_VALUE,
        (string) (($optionStore[BRICKS_DB_THEME_STYLES][BricksIntegrationService::MANAGED_THEME_STYLE_ID]['settings']['typography']['typographyBody']['font-family'] ?? '')),
        'Switching Bricks Theme Style mode back to managed should reapply Tasty typography to the managed Bricks Theme Style.'
    );
};

$tests['admin_controller_resets_bricks_integration_state_and_restores_bricks_options'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();
    $optionStore[BRICKS_DB_THEME_STYLES] = [
        'sitewide-primary' => [
            'label' => 'Sitewide Primary',
            'settings' => [
                'conditions' => ['conditions' => [['priority' => 10]]],
                'typography' => [
                    'typographyBody' => ['font-family' => 'Inter'],
                    'typographyHeadings' => ['font-family' => 'Lora'],
                ],
            ],
        ],
    ];
    $optionStore[BricksIntegrationService::OPTION_GLOBAL_SETTINGS] = ['builderHeaderSticky' => true];

    $services['settings']->saveSettings([
        'bricks_integration_enabled' => '1',
        'bricks_theme_styles_sync_enabled' => '1',
        'bricks_theme_style_target_mode' => 'selected',
        'bricks_theme_style_target_id' => 'sitewide-primary',
        'bricks_disable_google_fonts_enabled' => '1',
    ]);
    $services['settings']->setAutoApplyRoles(true);
    $services['bricks_integration']->applyThemeStylesSync('sitewide-primary');
    $services['bricks_integration']->applyGoogleFontsSetting();

    $result = $services['controller']->saveSettingsValues([
        'bricks_reset_integration' => '1',
    ]);

    assertSameValue(false, $result['settings']['bricks_integration_enabled'], 'Resetting Bricks integration should disable the master Bricks integration switch.');
    assertSameValue(false, $result['settings']['bricks_theme_styles_sync_enabled'], 'Resetting Bricks integration should disable Bricks Theme Style sync.');
    assertSameValue(false, $result['settings']['bricks_disable_google_fonts_enabled'], 'Resetting Bricks integration should disable Bricks Google font control.');
    assertSameValue(BricksIntegrationService::TARGET_MODE_MANAGED, $result['settings']['bricks_theme_style_target_mode'], 'Resetting Bricks integration should restore the managed Theme Style target mode.');
    assertSameValue(
        'Inter',
        (string) (($optionStore[BRICKS_DB_THEME_STYLES]['sitewide-primary']['settings']['typography']['typographyBody']['font-family'] ?? '')),
        'Resetting Bricks integration should restore the original Bricks Theme Style values.'
    );
    assertSameValue(
        ['builderHeaderSticky' => true],
        (array) ($optionStore[BricksIntegrationService::OPTION_GLOBAL_SETTINGS] ?? []),
        'Resetting Bricks integration should restore the previous Bricks global settings.'
    );
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

    assertSameValue(['Inter', 'Arial'], $fonts, 'Bricks should receive published Tasty Fonts runtime families first, followed by existing standard fonts.');
};

$tests['bricks_integration_service_applies_and_restores_managed_theme_styles'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();
    $optionStore[BRICKS_DB_THEME_STYLES] = [
        'style-primary' => [
            'label' => 'Primary',
            'settings' => [
                'conditions' => [
                    'conditions' => [
                        ['priority' => 10],
                    ],
                ],
                'typography' => [
                    'typographyBody' => ['font-family' => 'Inter', 'font-size' => '18px'],
                    'typographyLead' => ['font-family' => 'Inter'],
                    'typographyHeadings' => ['font-family' => 'Lora'],
                    'typographyHeadingH1' => ['font-family' => 'Lora'],
                    'typographyHeadingH2' => ['font-family' => 'Lora'],
                    'typographyHeadingH3' => ['font-family' => 'Lora'],
                    'typographyHeadingH4' => ['font-family' => 'Lora'],
                    'typographyHeadingH5' => ['font-family' => 'Lora'],
                    'typographyHeadingH6' => ['font-family' => 'Lora'],
                    'typographyHero' => ['font-family' => 'Lora'],
                ],
            ],
        ],
        'style-secondary' => [
            'label' => 'Secondary',
            'settings' => [
                'conditions' => [
                    'conditions' => [
                        ['priority' => 20],
                    ],
                ],
                'typography' => [
                    'typographyBody' => ['font-family' => 'Georgia'],
                    'typographyHeadings' => ['font-family' => 'Merriweather'],
                ],
            ],
        ],
    ];

    $services['bricks_integration']->applyThemeStylesSync(BricksIntegrationService::TARGET_MODE_SELECTED, 'style-primary');
    $styles = (array) ($optionStore[BRICKS_DB_THEME_STYLES] ?? []);
    $typography = (array) (($styles['style-primary']['settings']['typography'] ?? null) ?: []);

    assertSameValue(BricksIntegrationService::DESIRED_BODY_VALUE, (string) ($typography['typographyBody']['font-family'] ?? ''), 'Applying Bricks Theme Style sync should map body typography to the Tasty body variable.');
    assertSameValue(BricksIntegrationService::DESIRED_BODY_WEIGHT_VALUE, (string) ($typography['typographyBody']['font-weight'] ?? ''), 'Applying Bricks Theme Style sync should map body weights to the Tasty body weight variable.');
    assertSameValue(BricksIntegrationService::DESIRED_HEADING_VALUE, (string) ($typography['typographyHeadingH1']['font-family'] ?? ''), 'Applying Bricks Theme Style sync should map heading typography to the Tasty heading variable.');
    assertSameValue(BricksIntegrationService::DESIRED_HEADING_WEIGHT_VALUE, (string) ($typography['typographyHeadingH1']['font-weight'] ?? ''), 'Applying Bricks Theme Style sync should map heading weights to the Tasty heading weight variable.');
    assertSameValue('18px', (string) ($typography['typographyBody']['font-size'] ?? ''), 'Applying Bricks Theme Style sync should preserve unrelated Theme Style typography properties.');
    assertSameValue('Georgia', (string) (($styles['style-secondary']['settings']['typography']['typographyBody']['font-family'] ?? '')), 'Applying Bricks Theme Style sync should leave non-targeted Theme Styles untouched.');

    $services['bricks_integration']->restoreThemeStylesSync();

    assertSameValue(
        'Inter',
        (string) (($optionStore[BRICKS_DB_THEME_STYLES]['style-primary']['settings']['typography']['typographyBody']['font-family'] ?? '')),
        'Restoring Bricks Theme Style sync should restore the previous body typography family.'
    );
    assertSameValue(
        'Lora',
        (string) (($optionStore[BRICKS_DB_THEME_STYLES]['style-primary']['settings']['typography']['typographyHeadingH1']['font-family'] ?? '')),
        'Restoring Bricks Theme Style sync should restore the previous heading typography family.'
    );
};

$tests['bricks_integration_service_can_apply_theme_sync_to_all_bricks_theme_styles'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();
    $optionStore[BRICKS_DB_THEME_STYLES] = [
        'style-primary' => [
            'label' => 'Primary',
            'settings' => [
                'typography' => [
                    'typographyBody' => ['font-family' => 'Inter'],
                ],
            ],
        ],
        'style-secondary' => [
            'label' => 'Secondary',
            'settings' => [
                'typography' => [
                    'typographyHeadings' => ['font-family' => 'Lora'],
                ],
            ],
        ],
    ];

    $services['bricks_integration']->applyThemeStylesSync(BricksIntegrationService::TARGET_MODE_ALL, BricksIntegrationService::MANAGED_THEME_STYLE_ID);

    assertSameValue(
        BricksIntegrationService::DESIRED_BODY_VALUE,
        (string) (($optionStore[BRICKS_DB_THEME_STYLES]['style-primary']['settings']['typography']['typographyBody']['font-family'] ?? '')),
        'Applying all-style Bricks Theme Style sync should update body typography across every Theme Style.'
    );
    assertSameValue(
        BricksIntegrationService::DESIRED_HEADING_VALUE,
        (string) (($optionStore[BRICKS_DB_THEME_STYLES]['style-secondary']['settings']['typography']['typographyHeadings']['font-family'] ?? '')),
        'Applying all-style Bricks Theme Style sync should update heading typography across every Theme Style.'
    );
};

$tests['admin_controller_can_delete_the_managed_bricks_theme_style'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();
    $optionStore[BRICKS_DB_THEME_STYLES] = [
        BricksIntegrationService::MANAGED_THEME_STYLE_ID => [
            'label' => 'Tasty Fonts',
            'settings' => ['typography' => []],
        ],
        'sitewide-primary' => [
            'label' => 'Primary',
            'settings' => ['typography' => []],
        ],
    ];
    $services['settings']->saveSettings([
        'bricks_integration_enabled' => '1',
        'bricks_theme_styles_sync_enabled' => '1',
        'bricks_theme_style_target_mode' => BricksIntegrationService::TARGET_MODE_MANAGED,
        'bricks_theme_style_target_id' => BricksIntegrationService::MANAGED_THEME_STYLE_ID,
    ]);

    $result = $services['controller']->saveSettingsValues([
        'bricks_delete_theme_style' => '1',
    ]);

    assertSameValue(false, isset($optionStore[BRICKS_DB_THEME_STYLES][BricksIntegrationService::MANAGED_THEME_STYLE_ID]), 'Deleting the managed Bricks Theme Style should remove it from Bricks storage.');
    assertSameValue(BricksIntegrationService::TARGET_MODE_SELECTED, (string) ($result['settings']['bricks_theme_style_target_mode'] ?? ''), 'Deleting the managed Bricks Theme Style should fall back to a selected existing Theme Style when one exists.');
    assertSameValue('sitewide-primary', (string) ($result['settings']['bricks_theme_style_target_id'] ?? ''), 'Deleting the managed Bricks Theme Style should switch the target to the first remaining Bricks Theme Style.');
};

$tests['bricks_integration_service_creates_a_managed_theme_style_when_none_exist'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();
    $optionStore[BRICKS_DB_THEME_STYLES] = [];

    $services['bricks_integration']->applyThemeStylesSync(BricksIntegrationService::TARGET_MODE_MANAGED, BricksIntegrationService::MANAGED_THEME_STYLE_ID);
    $styles = (array) ($optionStore[BRICKS_DB_THEME_STYLES] ?? []);

    assertSameValue(true, isset($styles[BricksIntegrationService::MANAGED_THEME_STYLE_ID]), 'Applying Bricks Theme Style sync should create a managed Theme Style when Bricks has none yet.');
    assertSameValue(
        BricksIntegrationService::DESIRED_BODY_VALUE,
        (string) (($styles[BricksIntegrationService::MANAGED_THEME_STYLE_ID]['settings']['typography']['typographyBody']['font-family'] ?? '')),
        'The fallback managed Theme Style should point body typography at the Tasty body variable.'
    );

    $services['bricks_integration']->restoreThemeStylesSync();

    assertSameValue([], $optionStore[BRICKS_DB_THEME_STYLES] ?? [], 'Restoring a fallback managed Theme Style should return Bricks to its previous empty Theme Style state.');
};

$tests['bricks_integration_service_reset_restores_later_targeted_styles_without_deleting_them'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();
    $optionStore[BRICKS_DB_THEME_STYLES] = [];

    $services['bricks_integration']->applyThemeStylesSync(BricksIntegrationService::TARGET_MODE_MANAGED, BricksIntegrationService::MANAGED_THEME_STYLE_ID);

    $optionStore[BRICKS_DB_THEME_STYLES]['custom-user-style'] = [
        'label' => 'Custom User Style',
        'settings' => [
            'typography' => [
                'typographyBody' => ['font-family' => 'Inter', 'font-size' => '18px'],
                'typographyHeadings' => ['font-family' => 'Lora', 'letter-spacing' => '0.02em'],
            ],
        ],
    ];

    $services['bricks_integration']->applyThemeStylesSync(BricksIntegrationService::TARGET_MODE_SELECTED, 'custom-user-style');
    $services['bricks_integration']->restoreThemeStylesSync();

    assertSameValue(
        false,
        isset($optionStore[BRICKS_DB_THEME_STYLES][BricksIntegrationService::MANAGED_THEME_STYLE_ID]),
        'Restoring Bricks Theme Styles should remove the managed Tasty Theme Style only when Tasty created it.'
    );
    assertSameValue(
        true,
        isset($optionStore[BRICKS_DB_THEME_STYLES]['custom-user-style']),
        'Restoring Bricks Theme Styles should not delete a user-created Theme Style that Tasty later targeted.'
    );
    assertSameValue(
        'Inter',
        (string) (($optionStore[BRICKS_DB_THEME_STYLES]['custom-user-style']['settings']['typography']['typographyBody']['font-family'] ?? '')),
        'Restoring Bricks Theme Styles should restore the original body typography on a later-targeted user-created Theme Style.'
    );
    assertSameValue(
        '18px',
        (string) (($optionStore[BRICKS_DB_THEME_STYLES]['custom-user-style']['settings']['typography']['typographyBody']['font-size'] ?? '')),
        'Restoring Bricks Theme Styles should preserve unrelated properties on a later-targeted user-created Theme Style.'
    );
    assertSameValue(
        'Lora',
        (string) (($optionStore[BRICKS_DB_THEME_STYLES]['custom-user-style']['settings']['typography']['typographyHeadings']['font-family'] ?? '')),
        'Restoring Bricks Theme Styles should restore the original heading typography on a later-targeted user-created Theme Style.'
    );
};

$tests['bricks_integration_service_reports_managed_theme_style_values_when_no_active_screen_match_exists'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();
    $optionStore[BRICKS_DB_THEME_STYLES] = [
        BricksIntegrationService::MANAGED_THEME_STYLE_ID => [
            'label' => 'Tasty Fonts',
            'settings' => [
                'conditions' => [
                    'conditions' => [
                        ['key' => 'main', 'compare' => '==', 'value' => 'any', 'priority' => 10],
                    ],
                ],
                'typography' => [
                    'typographyBody' => [
                        'font-family' => BricksIntegrationService::DESIRED_BODY_VALUE,
                        'font-weight' => BricksIntegrationService::DESIRED_BODY_WEIGHT_VALUE,
                    ],
                    'typographyHeadings' => [
                        'font-family' => BricksIntegrationService::DESIRED_HEADING_VALUE,
                        'font-weight' => BricksIntegrationService::DESIRED_HEADING_WEIGHT_VALUE,
                    ],
                ],
            ],
        ],
    ];

    $state = $services['bricks_integration']->readState([
        'bricks_integration_enabled' => true,
        'bricks_theme_styles_sync_enabled' => true,
        'bricks_theme_style_target_mode' => BricksIntegrationService::TARGET_MODE_MANAGED,
        'bricks_theme_style_target_id' => BricksIntegrationService::MANAGED_THEME_STYLE_ID,
        'auto_apply_roles' => true,
    ]);

    assertSameValue(
        BricksIntegrationService::DESIRED_BODY_VALUE,
        (string) ($state['theme_styles']['current']['body_family'] ?? ''),
        'Bricks state should report the managed body Theme Style value even when the settings screen has no active Bricks screen match.'
    );
    assertSameValue(
        BricksIntegrationService::DESIRED_HEADING_VALUE,
        (string) ($state['theme_styles']['current']['heading_family'] ?? ''),
        'Bricks state should report the managed heading Theme Style value even when the settings screen has no active Bricks screen match.'
    );
    assertSameValue(
        'Tasty Fonts',
        (string) ($state['theme_styles']['summary']['managed_style_label'] ?? ''),
        'Bricks state should report the managed Theme Style label when Tasty created that Theme Style.'
    );
    assertSameValue(
        BricksIntegrationService::MANAGED_THEME_STYLE_ID,
        (string) ($state['theme_styles']['summary']['target_style_id'] ?? ''),
        'Bricks state should report which Theme Style target Tasty is managing.'
    );
    assertSameValue(
        BricksIntegrationService::TARGET_MODE_MANAGED,
        (string) ($state['theme_styles']['summary']['target_mode'] ?? ''),
        'Bricks state should report which Theme Style mode Tasty is using.'
    );
};

$tests['bricks_integration_service_resolves_managed_target_even_when_a_selected_style_id_is_saved'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();
    $optionStore[BRICKS_DB_THEME_STYLES] = [
        'sitewide-primary' => [
            'label' => 'Primary',
            'settings' => ['typography' => []],
        ],
        BricksIntegrationService::MANAGED_THEME_STYLE_ID => [
            'label' => 'Tasty Fonts',
            'settings' => [
                'typography' => [
                    'typographyBody' => ['font-family' => BricksIntegrationService::DESIRED_BODY_VALUE],
                    'typographyHeadings' => ['font-family' => BricksIntegrationService::DESIRED_HEADING_VALUE],
                ],
            ],
        ],
    ];

    $state = $services['bricks_integration']->readState([
        'bricks_integration_enabled' => true,
        'bricks_theme_styles_sync_enabled' => true,
        'bricks_theme_style_target_mode' => BricksIntegrationService::TARGET_MODE_MANAGED,
        'bricks_theme_style_target_id' => 'sitewide-primary',
        'auto_apply_roles' => true,
    ]);

    assertSameValue(
        BricksIntegrationService::MANAGED_THEME_STYLE_ID,
        (string) ($state['theme_styles']['summary']['target_style_id'] ?? ''),
        'Managed Bricks Theme Style mode should resolve to the managed Tasty Theme Style even when an old selected style ID is still stored.'
    );
    assertSameValue(
        'Tasty Fonts',
        (string) ($state['theme_styles']['summary']['target_style_label'] ?? ''),
        'Managed Bricks Theme Style mode should report the managed Tasty Theme Style label instead of a stale selected style label.'
    );
    assertSameValue(
        true,
        !empty($state['theme_styles']['summary']['target_is_managed']),
        'Managed Bricks Theme Style mode should still be recognized as the managed target when an old selected style ID is present.'
    );
};

$tests['bricks_integration_service_reports_when_no_bricks_theme_styles_exist'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $state = $services['bricks_integration']->readState([
        'bricks_integration_enabled' => true,
        'bricks_theme_styles_sync_enabled' => false,
        'auto_apply_roles' => true,
    ]);

    assertSameValue(
        false,
        !empty($state['theme_styles']['summary']['has_theme_styles']),
        'Bricks state should report when no Theme Styles exist yet.'
    );
    assertSameValue(
        'Tasty Fonts',
        (string) ($state['theme_styles']['summary']['managed_style_label'] ?? ''),
        'Bricks state should still expose the default Tasty Theme Style label when none exists yet.'
    );
};

$tests['bricks_integration_service_applies_and_restores_disable_google_fonts_setting'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();
    $optionStore[BricksIntegrationService::OPTION_GLOBAL_SETTINGS] = [
        'builderHeaderSticky' => true,
    ];

    $services['bricks_integration']->applyGoogleFontsSetting();

    assertSameValue(true, !empty($optionStore[BricksIntegrationService::OPTION_GLOBAL_SETTINGS]['disableGoogleFonts']), 'Applying the Bricks Google font setting should turn on Bricks disableGoogleFonts.');

    $services['bricks_integration']->restoreGoogleFontsSetting();

    assertSameValue(
        ['builderHeaderSticky' => true],
        $optionStore[BricksIntegrationService::OPTION_GLOBAL_SETTINGS] ?? [],
        'Restoring the Bricks Google font setting should restore the previous Bricks global settings without leaving disableGoogleFonts behind.'
    );
};

$tests['runtime_service_enqueues_bricks_builder_assets_when_preview_loading_is_enabled'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $inlineScripts;

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'bricks_integration_enabled' => true,
    ]);
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
    $_GET['brickspreview'] = '1';

    $services['runtime']->enqueueBricksBuilder();

    assertSameValue(true, isset($enqueuedStyles['tasty-fonts-bricks-builder']), 'Bricks builder preview loading should enqueue the generated Tasty stylesheet for the builder.');
    assertSameValue(true, isset($enqueuedStyles['tasty-fonts-google-jetbrains-mono-cdn-bricks-builder']), 'Bricks builder preview loading should enqueue external runtime font stylesheets with Bricks-specific handles.');
    assertSameValue(true, isset($inlineScripts['bricks-builder'][0]), 'Bricks selector exposure should inject a builder script so Tasty Fonts appear in their own top-level picker group.');
    assertSameValue('before', (string) ($inlineScripts['bricks-builder'][0]['position'] ?? ''), 'The Bricks picker grouping script should run before the builder app boots.');
    assertContainsValue('var tastyFamilies = ["JetBrains Mono"];', (string) ($inlineScripts['bricks-builder'][0]['data'] ?? ''), 'The Bricks picker grouping script should include the published Tasty runtime family names.');
};

$tests['runtime_service_appends_managed_bricks_editor_styles_when_theme_style_sync_is_active'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'bricks_integration_enabled' => true,
        'bricks_theme_styles_sync_enabled' => true,
        'bricks_theme_style_target_mode' => BricksIntegrationService::TARGET_MODE_MANAGED,
        'bricks_theme_style_target_id' => BricksIntegrationService::MANAGED_THEME_STYLE_ID,
    ]);
    $services['settings']->setAutoApplyRoles(true);

    $services['bricks_integration']->applyThemeStylesSync(BricksIntegrationService::TARGET_MODE_MANAGED, BricksIntegrationService::MANAGED_THEME_STYLE_ID);

    $settings = $services['runtime']->filterBlockEditorSettings([], null);
    $css = (string) ($settings['styles'][0]['css'] ?? '');

    assertContainsValue('body{font-family:var(--font-body);font-weight:var(--font-body-weight);}', $css, 'Managed Bricks Theme Style sync should mirror the Tasty body role variables into Gutenberg.');
    assertContainsValue('font-family:var(--font-heading);font-weight:var(--font-heading-weight);', $css, 'Managed Bricks Theme Style sync should mirror the Tasty heading role variables into Gutenberg.');
};

$tests['runtime_service_enqueues_bricks_frontend_override_when_theme_style_sync_is_active'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $inlineStyles;

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'bricks_integration_enabled' => true,
        'bricks_theme_styles_sync_enabled' => true,
        'bricks_theme_style_target_mode' => BricksIntegrationService::TARGET_MODE_MANAGED,
        'bricks_theme_style_target_id' => BricksIntegrationService::MANAGED_THEME_STYLE_ID,
    ]);
    $services['settings']->setAutoApplyRoles(true);

    $services['bricks_integration']->applyThemeStylesSync(BricksIntegrationService::TARGET_MODE_MANAGED, BricksIntegrationService::MANAGED_THEME_STYLE_ID);
    $services['runtime']->enqueueBricksFrontendOverride();

    assertSameValue(
        true,
        isset($enqueuedStyles['tasty-fonts-bricks-runtime-override']),
        'Managed Bricks Theme Style sync should enqueue a late runtime override stylesheet so Bricks frontend output resolves Tasty role variables correctly.'
    );

    $css = (string) ($inlineStyles['tasty-fonts-bricks-runtime-override'] ?? '');

    assertContainsValue(
        'body,.bricks-type-lead{font-family:var(--font-body);font-weight:var(--font-body-weight);}',
        $css,
        'The Bricks runtime override should restore unquoted body and lead font-role variables on the frontend.'
    );
    assertContainsValue(
        'h1,h2,h3,h4,h5,h6,.bricks-type-hero{font-family:var(--font-heading);font-weight:var(--font-heading-weight);}',
        $css,
        'The Bricks runtime override should restore unquoted heading and hero font-role variables on the frontend.'
    );
};

$tests['runtime_service_enqueues_bricks_frontend_override_when_live_bricks_style_keeps_only_font_family_values'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $inlineStyles;
    global $optionStore;

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'bricks_integration_enabled' => true,
        'bricks_theme_styles_sync_enabled' => true,
        'bricks_theme_style_target_mode' => BricksIntegrationService::TARGET_MODE_MANAGED,
        'bricks_theme_style_target_id' => BricksIntegrationService::MANAGED_THEME_STYLE_ID,
    ]);
    $services['settings']->setAutoApplyRoles(true);

    $services['bricks_integration']->applyThemeStylesSync(BricksIntegrationService::TARGET_MODE_MANAGED, BricksIntegrationService::MANAGED_THEME_STYLE_ID);

    $optionStore[BRICKS_DB_THEME_STYLES][BricksIntegrationService::MANAGED_THEME_STYLE_ID]['settings']['typography']['typographyBody'] = [
        'font-family' => 'var(--font-body)',
    ];
    $optionStore[BRICKS_DB_THEME_STYLES][BricksIntegrationService::MANAGED_THEME_STYLE_ID]['settings']['typography']['typographyLead'] = [
        'font-family' => 'var(--font-body)',
    ];
    $optionStore[BRICKS_DB_THEME_STYLES][BricksIntegrationService::MANAGED_THEME_STYLE_ID]['settings']['typography']['typographyHeadings'] = [
        'font-family' => 'var(--font-heading)',
    ];
    $optionStore[BRICKS_DB_THEME_STYLES][BricksIntegrationService::MANAGED_THEME_STYLE_ID]['settings']['typography']['typographyHeadingH1'] = [
        'font-family' => 'var(--font-heading)',
    ];
    $optionStore[BRICKS_DB_THEME_STYLES][BricksIntegrationService::MANAGED_THEME_STYLE_ID]['settings']['typography']['typographyHeadingH2'] = [
        'font-family' => 'var(--font-heading)',
    ];
    $optionStore[BRICKS_DB_THEME_STYLES][BricksIntegrationService::MANAGED_THEME_STYLE_ID]['settings']['typography']['typographyHeadingH3'] = [
        'font-family' => 'var(--font-heading)',
    ];
    $optionStore[BRICKS_DB_THEME_STYLES][BricksIntegrationService::MANAGED_THEME_STYLE_ID]['settings']['typography']['typographyHeadingH4'] = [
        'font-family' => 'var(--font-heading)',
    ];
    $optionStore[BRICKS_DB_THEME_STYLES][BricksIntegrationService::MANAGED_THEME_STYLE_ID]['settings']['typography']['typographyHeadingH5'] = [
        'font-family' => 'var(--font-heading)',
    ];
    $optionStore[BRICKS_DB_THEME_STYLES][BricksIntegrationService::MANAGED_THEME_STYLE_ID]['settings']['typography']['typographyHeadingH6'] = [
        'font-family' => 'var(--font-heading)',
    ];
    $optionStore[BRICKS_DB_THEME_STYLES][BricksIntegrationService::MANAGED_THEME_STYLE_ID]['settings']['typography']['typographyHero'] = [
        'font-family' => 'var(--font-heading)',
    ];

    $services['runtime']->enqueueBricksFrontendOverride();

    assertSameValue(
        true,
        isset($enqueuedStyles['tasty-fonts-bricks-runtime-override']),
        'The Bricks frontend override should still enqueue when Bricks keeps only the synced font-family values in the live Theme Style record.'
    );
    assertContainsValue(
        'body,.bricks-type-lead{font-family:var(--font-body);font-weight:var(--font-body-weight);}',
        (string) ($inlineStyles['tasty-fonts-bricks-runtime-override'] ?? ''),
        'The Bricks frontend override should recover the full role-variable mapping even when Bricks has stripped the stored font-weight fields.'
    );
};

$tests['runtime_service_skips_bricks_frontend_override_when_sitewide_delivery_is_disabled'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $inlineScripts;

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'bricks_integration_enabled' => true,
        'bricks_theme_styles_sync_enabled' => true,
        'bricks_theme_style_target_mode' => BricksIntegrationService::TARGET_MODE_MANAGED,
        'bricks_theme_style_target_id' => BricksIntegrationService::MANAGED_THEME_STYLE_ID,
    ]);
    $services['bricks_integration']->applyThemeStylesSync(BricksIntegrationService::TARGET_MODE_MANAGED, BricksIntegrationService::MANAGED_THEME_STYLE_ID);

    $services['runtime']->enqueueBricksFrontendOverride();

    assertSameValue(false, isset($inlineScripts['bricks-scripts'][0]), 'Bricks frontend fixer script should not leave a Tasty Fonts trace while sitewide delivery is disabled.');
    assertSameValue(false, isset($enqueuedStyles['tasty-fonts-bricks-runtime-override']), 'Bricks frontend override stylesheet should stay disabled while sitewide delivery is disabled.');
};

$tests['runtime_service_enqueues_bricks_variable_fix_script_for_frontend_and_builder_canvas_styles'] = static function (): void {
    resetTestState();

    global $inlineScripts;

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'bricks_integration_enabled' => true,
    ]);
    $services['settings']->setAutoApplyRoles(true);

    $services['runtime']->enqueueBricksFrontendOverride();

    assertSameValue(
        true,
        isset($inlineScripts['bricks-scripts'][0]),
        'Late Bricks runtime passes should attach the quoted-variable fixer script to the Bricks runtime so Tasty role variables also work in Bricks-generated inline typography CSS.'
    );

    $script = (string) ($inlineScripts['bricks-scripts'][0]['data'] ?? '');

    assertContainsValue(
        "var quotedPropertyPattern = /(font-family\\s*:\\s*)[\"'](var\\(--[^\"']+\\))[\"']/g;",
        $script,
        'The Bricks quoted-variable fixer script should strip quotes around CSS variable font-family values.'
    );
    assertContainsValue(
        "style.setProperty('font-family', normalized, style.getPropertyPriority('font-family'));",
        $script,
        'The Bricks quoted-variable fixer script should also repair live CSSStyleRule font-family declarations inside the Bricks canvas.'
    );
    assertContainsValue(
        "observer.observe(document.documentElement, {",
        $script,
        'The Bricks quoted-variable fixer script should observe later style-tag mutations so builder canvas updates are normalized too.'
    );
    assertContainsValue(
        "var repairTimer = window.setInterval(function () {",
        $script,
        'The Bricks quoted-variable fixer script should keep repairing the live Bricks canvas for a short period while builder styles are still being injected.'
    );
};

$tests['runtime_service_enqueues_etch_frontend_override_when_minimal_output_preset_is_active'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $inlineStyles;

    add_filter('tasty_fonts_etch_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $services['settings']->saveAppliedRoles(
        [
            'heading' => '',
            'body' => '',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        []
    );
    $services['settings']->setAutoApplyRoles(true);
    $services['runtime']->enqueueEtchFrontendOverride();

    assertSameValue(
        true,
        isset($enqueuedStyles['tasty-fonts-etch-runtime-override']),
        'Minimal output on Etch frontends should enqueue a late runtime override so live sitewide role variables are actually consumed on the frontend.'
    );

    $css = (string) ($inlineStyles['tasty-fonts-etch-runtime-override'] ?? '');

    assertContainsValue(
        "body {\n  font-family: var(--font-body) !important;\n  font-variation-settings: var(--font-body-settings) !important;\n}",
        $css,
        'The Etch frontend override should map the live body typography back to the Tasty body role variable.'
    );
    assertContainsValue(
        "h1, h2, h3, h4, h5, h6 {\n  font-family: var(--font-heading) !important;\n  font-variation-settings: var(--font-heading-settings) !important;\n}",
        $css,
        'The Etch frontend override should map live heading typography back to the Tasty heading role variable.'
    );
};

$tests['runtime_service_skips_etch_frontend_override_when_etch_integration_is_explicitly_disabled'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $inlineStyles;

    add_filter('tasty_fonts_etch_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $services['settings']->saveAppliedRoles(
        [
            'heading' => '',
            'body' => '',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        []
    );
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings([
        'etch_integration_enabled' => '0',
    ]);

    $services['runtime']->enqueueEtchFrontendOverride();

    assertSameValue(false, isset($enqueuedStyles['tasty-fonts-etch-runtime-override']), 'Explicitly disabling Etch integration should prevent the Etch frontend runtime override from being enqueued.');
    assertSameValue(false, isset($inlineStyles['tasty-fonts-etch-runtime-override']), 'Explicitly disabling Etch integration should prevent Etch frontend runtime override CSS from being emitted.');
};

$tests['runtime_service_appends_generic_editor_role_bridge_when_minimal_output_preset_is_active'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveAppliedRoles(
        [
            'heading' => '',
            'body' => '',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        []
    );
    $services['settings']->setAutoApplyRoles(true);

    $settings = $services['runtime']->filterBlockEditorSettings([], null);
    $css = (string) ($settings['styles'][0]['css'] ?? '');

    assertContainsValue('body{font-family:var(--font-body)!important;font-variation-settings:var(--font-body-settings)!important;}', preg_replace('/\s+/', '', $css) ?? $css, 'The block editor should receive a generic body role bridge when Minimal output keeps the runtime stylesheet variable-only.');
    assertContainsValue('.editor-post-title', $css, 'The block editor bridge should cover editor post title selectors.');
    assertContainsValue('font-family: var(--font-heading) !important;', $css, 'The block editor bridge should map live heading typography back to the Tasty heading role variable.');
};

$tests['runtime_service_does_not_append_editor_role_bridge_when_sitewide_roles_are_off'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveRoles(
        [
            'heading' => '',
            'body' => '',
            'heading_fallback' => 'system-ui, sans-serif',
            'body_fallback' => 'system-ui, sans-serif',
        ],
        []
    );
    $services['settings']->saveSettings([
        'auto_apply_roles' => false,
        'minimal_output_preset_enabled' => true,
    ]);

    $settings = $services['runtime']->filterBlockEditorSettings([], null);

    assertSameValue(
        false,
        isset($settings['styles']),
        'The block editor should stay untouched by the generic Tasty role bridge while role changes are still saved-only and not applied sitewide.'
    );
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
    assertContainsValue('body :is(h1, h2, h3, h4, h5, h6, .editor-post-title){font-family:"Merriweather", sans-serif;}', $css, 'Bricks heading styles should mirror managed H1 selections into Gutenberg.');
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

    assertContainsValue('body{font-family:var(--font-body);font-weight:var(--font-body-weight);}', $css, 'Automatic.css editor styles should mirror the managed body font-family and weight variables into Gutenberg.');
    assertContainsValue('body :is(h1, h2, h3, h4, h5, h6, .editor-post-title, .wp-block-post-title){font-family:var(--font-heading);font-weight:var(--font-heading-weight);}', $css, 'Automatic.css editor styles should mirror the managed heading font-family and weight variables into Gutenberg, including the post title.');
    assertNotContainsValue('font-variation-settings', $css, 'Automatic.css editor styles should stay focused on the same direct font-family mapping used by the main ACSS sync.');
};

$tests['runtime_service_does_not_append_acss_editor_styles_when_sync_is_inactive_and_minimal_output_is_disabled'] = static function (): void {
    resetTestState();

    add_filter('tasty_fonts_acss_integration_available', static fn (): bool => true);

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'auto_apply_roles' => true,
        'minimal_output_preset_enabled' => false,
        'acss_font_role_sync_enabled' => false,
    ]);

    $settings = $services['runtime']->filterBlockEditorSettings([], null);

    assertSameValue(
        false,
        isset($settings['styles']),
        'When Automatic.css sync is off and Minimal output is not using the generic role bridge, Gutenberg should not receive extra editor-only CSS from Tasty beyond the mirrored ACSS runtime stylesheet.'
    );
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

$tests['asset_service_uses_sha256_for_file_state_comparison_and_a_shorter_version_token'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['assets']->ensureGeneratedCssFile(false);

    $status = $services['assets']->getStatus();
    $url = (string) $services['assets']->getVersionedStylesheetUrl();
    $query = (string) parse_url($url, PHP_URL_QUERY);
    parse_str($query, $queryArgs);

    assertSameValue(
        64,
        strlen((string) ($status['expected_hash'] ?? '')),
        'Generated stylesheet state should use a full SHA-256 digest for file-state comparison.'
    );
    assertSameValue(
        16,
        strlen((string) ($status['expected_version'] ?? '')),
        'Generated stylesheet state should expose a shorter version token for cache-busting URLs.'
    );
    assertSameValue(
        (string) ($status['expected_version'] ?? ''),
        (string) ($queryArgs['ver'] ?? ''),
        'Versioned stylesheet URLs should use the shortened digest token instead of the full comparison hash.'
    );
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

$tests['runtime_asset_planner_generates_custom_remote_css_without_original_stylesheet_enqueue'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $sourceCssUrl = 'https://assets.example.com/foundry.css';
    $fontUrl = 'https://cdn.example.com/fonts/remote-sans.woff2';
    $services['settings']->saveSettings([
        'font_display' => 'optional',
        'unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_PRESERVE,
        'remote_connection_hints' => '1',
    ]);
    $services['imports']->saveProfile(
        'Remote Sans',
        'remote-sans',
        [
            'id' => 'custom-remote-remote-sans-abc123',
            'provider' => 'custom',
            'type' => 'cdn',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Remote Sans',
                'slug' => 'remote-sans',
                'source' => 'custom',
                'weight' => '400',
                'style' => 'normal',
                'unicode_range' => 'U+0000-00FF',
                'files' => ['woff2' => $fontUrl],
                'paths' => [],
            ]],
            'meta' => [
                'delivery_mode' => 'remote',
                'source_css_url' => $sourceCssUrl,
                'source_host' => 'assets.example.com',
            ],
        ],
        'published',
        true
    );

    $localCatalog = $services['planner']->getLocalRuntimeCatalog();
    $css = $services['assets']->getCss();
    $origins = $services['planner']->getPreconnectOrigins();

    assertSameValue([], $services['planner']->getExternalStylesheets(), 'Custom remote deliveries should not enqueue the original third-party stylesheet.');
    assertContainsValue($fontUrl, str_replace('\\/', '/', wp_json_encode($localCatalog) ?: ''), 'Custom remote deliveries should feed absolute remote font URLs into the local runtime catalog.');
    assertContainsValue('@font-face', $css, 'Custom remote deliveries should be emitted as controlled Tasty Fonts-generated @font-face CSS.');
    assertContainsValue($fontUrl, $css, 'Generated CSS should reference the reviewed remote font URL.');
    assertContainsValue('font-display:optional', $css, 'Generated CSS should use the existing font-display setting for custom remote faces.');
    assertContainsValue('unicode-range:U+0000-00FF;', $css, 'Generated CSS should preserve reviewed unicode-range values for custom remote faces.');
    assertNotContainsValue($sourceCssUrl, $css, 'Generated CSS should not include or import the original source stylesheet URL.');
    assertSameValue(['https://cdn.example.com'], $origins, 'Remote connection hints should preconnect to remote font hosts, not source CSS hosts.');
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

$tests['runtime_service_inject_editor_font_presets_preserves_existing_schema_version'] = static function (): void {
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

    $themeJson = new WP_Theme_JSON_Data(['version' => 4, 'settings' => []]);
    $result = $services['runtime']->injectEditorFontPresets($themeJson);

    assertSameValue(
        4,
        $result->get_data()['version'] ?? null,
        'injectEditorFontPresets() should preserve an existing non-default theme JSON schema version.'
    );
};

$tests['runtime_service_inject_editor_font_presets_does_not_force_legacy_schema_version_when_missing'] = static function (): void {
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

    $themeJson = new WP_Theme_JSON_Data(['settings' => []]);
    $result = $services['runtime']->injectEditorFontPresets($themeJson);
    $data = $result->get_data();

    assertFalseValue(
        array_key_exists('version', $data),
        'injectEditorFontPresets() should not inject a fallback schema version when the incoming theme JSON data omits one.'
    );
};
