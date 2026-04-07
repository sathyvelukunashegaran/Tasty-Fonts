<?php

declare(strict_types=1);

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
use TastyFonts\Support\Storage;

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

$tests['settings_repository_defaults_and_normalizes_class_output_mode'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue('off', $settings->getSettings()['class_output_mode'], 'Class output mode should default to off for new installs.');

    $settings->saveSettings(['class_output_mode' => 'roles']);
    assertSameValue('roles', $settings->getSettings()['class_output_mode'], 'Settings should persist supported class output modes.');

    $settings->saveSettings(['class_output_mode' => 'families']);
    assertSameValue('families', $settings->getSettings()['class_output_mode'], 'Settings should persist family class output mode.');

    $settings->saveSettings(['class_output_mode' => 'all']);
    assertSameValue('all', $settings->getSettings()['class_output_mode'], 'Settings should persist combined class output mode.');

    $settings->saveSettings(['class_output_mode' => 'unsupported-value']);
    assertSameValue('off', $settings->getSettings()['class_output_mode'], 'Invalid class output modes should normalize back to off.');
};

$tests['settings_repository_enables_per_variant_font_variables_by_default_and_persists_changes'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue(
        true,
        !empty($settings->getSettings()['per_variant_font_variables_enabled']),
        'Per-variant font variables should default to enabled for new installs.'
    );
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

    $settings->saveSettings([
        'extended_variable_weight_tokens_enabled' => '0',
        'extended_variable_role_aliases_enabled' => '0',
        'extended_variable_category_sans_enabled' => '0',
        'extended_variable_category_serif_enabled' => '0',
        'extended_variable_category_mono_enabled' => '0',
    ]);
    $saved = $settings->getSettings();

    assertSameValue(false, $saved['extended_variable_weight_tokens_enabled'], 'Settings should persist disabled extended weight tokens.');
    assertSameValue(false, $saved['extended_variable_role_aliases_enabled'], 'Settings should persist disabled extended role aliases.');
    assertSameValue(false, $saved['extended_variable_category_sans_enabled'], 'Settings should persist disabled extended sans aliases.');
    assertSameValue(false, $saved['extended_variable_category_serif_enabled'], 'Settings should persist disabled extended serif aliases.');
    assertSameValue(false, $saved['extended_variable_category_mono_enabled'], 'Settings should persist disabled extended mono aliases.');
};

$tests['settings_repository_defaults_block_editor_font_library_sync_off_on_local_hosts'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue(
        false,
        !empty($settings->getSettings()['block_editor_font_library_sync_enabled']),
        'Local .test installs should default Block Editor Font Library sync to off until the user enables it explicitly.'
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

$tests['settings_repository_defaults_font_display_to_optional_and_normalizes_invalid_values'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue('optional', $settings->getSettings()['font_display'], 'Font display should default to optional for new installs.');

    $settings->saveSettings(['font_display' => 'block']);
    assertSameValue('block', $settings->getSettings()['font_display'], 'Settings should persist supported font-display values.');

    $settings->saveSettings(['font_display' => 'unsupported-value']);
    assertSameValue('optional', $settings->getSettings()['font_display'], 'Invalid saved font-display values should normalize back to optional.');
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
        'class_output_mode' => 'all',
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
    assertSameValue('all', $saved['class_output_mode'], 'Saving unrelated settings should not overwrite the class output mode.');
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

    assertContainsValue('display=optional', $runtimeUrl, 'Frontend runtime stylesheets should continue honoring the saved font-display policy.');
    assertContainsValue('display=swap', $previewUrl, 'Admin preview stylesheets should force swap so previews remain visible after reload.');
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

$tests['runtime_service_outputs_primary_font_preloads_for_live_sitewide_roles'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-700.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('lora/Lora-400.woff2'), 'font-data');
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
