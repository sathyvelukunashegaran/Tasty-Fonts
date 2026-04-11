<?php

declare(strict_types=1);

use TastyFonts\Admin\AdminController;
use TastyFonts\Api\RestController;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Plugin;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;

$tests['admin_controller_merges_adobe_families_into_selectable_role_names'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2'), 'font-data');
    $services['settings']->saveAdobeProject('abc1234', true);
    $services['settings']->saveAdobeProjectStatus('valid', 'Adobe project ready.');
    $remoteGetResponses['https://use.typekit.net/abc1234.css'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: "mr-eaves-xl-modern";
  font-style: normal;
  font-weight: 400;
  src: url("https://use.typekit.net/af/def456/000000000000000000000000/30/l?primer=1") format("woff2");
}
CSS,
    ];

    $catalog = $services['catalog']->getCatalog();
    $families = invokePrivateMethod($services['controller'], 'buildSelectableFamilyNames', [$catalog]);

    assertSameValue(
        ['Inter', 'mr-eaves-xl-modern'],
        $families,
        'Selectable role names should use the unified catalog, including Adobe project families.'
    );
};

$tests['rest_controller_falls_back_to_variant_tokens_when_variants_are_missing'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/google/import');
    $request->set_body_params(['variant_tokens' => 'regular, 700italic']);
    $variants = invokePrivateMethod($services['rest'], 'getVariantTokens', [$request]);

    assertSameValue(['regular', '700italic'], $variants, 'REST import requests should fall back to the comma-separated variant token field when an explicit variants array is absent.');
};

$tests['admin_controller_normalizes_uploaded_files_by_sparse_row_index'] = static function (): void {
    resetTestState();

    $postedRows = [
        7 => [
            'family' => 'Inter',
            'weight' => '400',
            'style' => 'italic',
            'fallback' => 'Arial, sans-serif',
        ],
    ];
    $rawFiles = [
        'name' => [7 => 'inter-400-italic.woff2'],
        'type' => [7 => 'font/woff2'],
        'tmp_name' => [7 => '/tmp/php-font'],
        'error' => [7 => UPLOAD_ERR_OK],
        'size' => [7 => 2048],
    ];

    $services = makeServiceGraph();
    $rows = $services['controller']->prepareUploadRows($postedRows, $rawFiles);

    assertSameValue('Inter', $rows[0]['family'], 'Uploaded row normalization should preserve the family name.');
    assertSameValue('italic', $rows[0]['style'], 'Uploaded row normalization should preserve the submitted style.');
    assertSameValue('Arial, sans-serif', $rows[0]['fallback'], 'Uploaded row normalization should preserve the submitted fallback.');
    assertSameValue('inter-400-italic.woff2', $rows[0]['file']['name'], 'Uploaded row normalization should attach the correct file payload to a sparse row index.');
    assertSameValue(2048, $rows[0]['file']['size'], 'Uploaded row normalization should preserve the uploaded file size.');
};

$tests['admin_controller_sanitizes_posted_fallback_values'] = static function (): void {
    resetTestState();

    $_POST['fallback'] = '  -apple-system, BlinkMacSystemFont, "Segoe UI", serif !@#$  ';

    $controller = makeAdminControllerTestInstance();
    $fallback = invokePrivateMethod($controller, 'getPostedFallback', ['fallback']);

    assertSameValue(
        '-apple-system, BlinkMacSystemFont, "Segoe UI", serif',
        $fallback,
        'Posted fallback values should be normalized through the controller before they reach settings storage.'
    );
};

$tests['admin_controller_builds_specific_settings_saved_message'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $message = invokePrivateMethod(
        $controller,
        'buildSettingsSavedMessage',
        [
            [
                'css_delivery_mode' => 'file',
                'font_display' => 'swap',
                'unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_PRESERVE,
                'unicode_range_custom_value' => 'U+0000-00FF',
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
                'minify_css_output' => true,
                'role_usage_font_weight_enabled' => false,
                'per_variant_font_variables_enabled' => true,
                'extended_variable_weight_tokens_enabled' => true,
                'preload_primary_fonts' => false,
                'block_editor_font_library_sync_enabled' => false,
                'training_wheels_off' => false,
                'preview_sentence' => 'Alpha',
            ],
            [
                'css_delivery_mode' => 'inline',
                'font_display' => 'optional',
                'unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_CUSTOM,
                'unicode_range_custom_value' => 'U+0000-00FF,U+0100-024F',
                'class_output_enabled' => true,
                'class_output_role_heading_enabled' => true,
                'class_output_role_body_enabled' => true,
                'class_output_role_monospace_enabled' => true,
                'class_output_role_alias_interface_enabled' => true,
                'class_output_role_alias_ui_enabled' => true,
                'class_output_role_alias_code_enabled' => false,
                'class_output_category_sans_enabled' => true,
                'class_output_category_serif_enabled' => true,
                'class_output_category_mono_enabled' => true,
                'class_output_families_enabled' => true,
                'minify_css_output' => false,
                'role_usage_font_weight_enabled' => true,
                'per_variant_font_variables_enabled' => false,
                'extended_variable_weight_tokens_enabled' => false,
                'preload_primary_fonts' => true,
                'block_editor_font_library_sync_enabled' => true,
                'training_wheels_off' => true,
                'preview_sentence' => 'Beta',
            ],
        ]
    );

    assertContainsValue('delivery mode set to inline CSS', $message, 'Settings save messages should explain delivery-mode changes.');
    assertContainsValue('font-display set to optional', $message, 'Settings save messages should explain font-display changes.');
    assertContainsValue('unicode-range output set to Custom', $message, 'Settings save messages should explain unicode-range mode changes.');
    assertContainsValue('custom unicode-range updated', $message, 'Settings save messages should explain custom unicode-range changes.');
    assertContainsValue('class output enabled', $message, 'Settings save messages should explain class-output enablement changes.');
    assertContainsValue('class output settings updated', $message, 'Settings save messages should explain granular class-output changes.');
    assertContainsValue('CSS minification disabled', $message, 'Settings save messages should explain CSS minification changes.');
    assertContainsValue('role font-weight output enabled', $message, 'Settings save messages should explain role font-weight output changes.');
    assertContainsValue('extended font output variables disabled', $message, 'Settings save messages should explain extended font output changes.');
    assertContainsValue('extended variable subsettings updated', $message, 'Settings save messages should explain granular extended-variable changes.');
    assertContainsValue('primary font preloads enabled', $message, 'Settings save messages should explain preload setting changes.');
    assertContainsValue('Block Editor Font Library sync enabled', $message, 'Settings save messages should explain editor sync changes.');
    assertContainsValue('onboarding hints hidden', $message, 'Settings save messages should explain plugin behavior changes.');
    assertContainsValue('preview text updated', $message, 'Settings save messages should explain preview text changes.');
    assertContainsValue('Reload the page to apply this change.', $message, 'Settings save messages should mention reload-only behavior changes.');
};

$tests['admin_controller_exposes_all_font_display_options_with_optional_first'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $options = invokePrivateMethod($controller, 'buildFontDisplayOptions', []);

    assertSameValue('optional', (string) ($options[0]['value'] ?? ''), 'Optional should be the first font-display choice so the recommended default is selected first.');
    assertSameValue(
        ['optional', 'swap', 'fallback', 'block', 'auto'],
        array_values(array_map(static fn (array $option): string => (string) ($option['value'] ?? ''), $options)),
        'Output Settings should expose every supported font-display option.'
    );
};

$tests['admin_controller_exposes_family_font_display_options_with_inherit_first'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $options = invokePrivateMethod($controller, 'buildFamilyFontDisplayOptions', ['swap']);

    assertSameValue('inherit', (string) ($options[0]['value'] ?? ''), 'Per-family font-display controls should offer inherit as the first option.');
    assertContainsValue('Swap', (string) ($options[0]['label'] ?? ''), 'The inherit option should explain which global font-display value will be used.');
    assertSameValue(
        ['inherit', 'optional', 'swap', 'fallback', 'block', 'auto'],
        array_values(array_map(static fn (array $option): string => (string) ($option['value'] ?? ''), $options)),
        'Per-family font-display controls should expose inherit plus every supported override option.'
    );
};

$tests['admin_controller_detects_which_setting_changes_require_asset_refresh'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresAssetRefresh',
            [
                ['minify_css_output' => true, 'per_variant_font_variables_enabled' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
                ['minify_css_output' => false, 'per_variant_font_variables_enabled' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
            ]
        ),
        'Disabling CSS minification should trigger a generated asset refresh.'
    );

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresAssetRefresh',
            [
                ['minify_css_output' => true, 'per_variant_font_variables_enabled' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
                ['minify_css_output' => true, 'per_variant_font_variables_enabled' => true, 'font_display' => 'optional', 'css_delivery_mode' => 'file'],
            ]
        ),
        'Changing font-display should trigger a generated asset refresh.'
    );

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresAssetRefresh',
            [
                ['font_display' => 'swap', 'unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_PRESERVE, 'css_delivery_mode' => 'file'],
                ['font_display' => 'swap', 'unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_LATIN_BASIC, 'css_delivery_mode' => 'file'],
            ]
        ),
        'Changing unicode-range mode should trigger a generated asset refresh.'
    );

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresAssetRefresh',
            [
                ['font_display' => 'swap', 'unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_CUSTOM, 'unicode_range_custom_value' => 'U+0000-00FF', 'css_delivery_mode' => 'file'],
                ['font_display' => 'swap', 'unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_CUSTOM, 'unicode_range_custom_value' => 'U+0000-00FF,U+0100-024F', 'css_delivery_mode' => 'file'],
            ]
        ),
        'Changing the custom unicode-range value should trigger a generated asset refresh.'
    );

    assertSameValue(
        false,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresAssetRefresh',
            [
                ['minify_css_output' => true, 'per_variant_font_variables_enabled' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file', 'preload_primary_fonts' => true],
                ['minify_css_output' => true, 'per_variant_font_variables_enabled' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file', 'preload_primary_fonts' => false],
            ]
        ),
        'Preload-only changes should not force a generated CSS refresh.'
    );

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresAssetRefresh',
            [
                ['minify_css_output' => true, 'per_variant_font_variables_enabled' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file', 'monospace_role_enabled' => false],
                ['minify_css_output' => true, 'per_variant_font_variables_enabled' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file', 'monospace_role_enabled' => true],
            ]
        ),
        'Toggling monospace support should trigger a generated asset refresh because it changes snippets and live CSS output.'
    );

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresAssetRefresh',
            [
                ['minify_css_output' => true, 'per_variant_font_variables_enabled' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
                ['minify_css_output' => true, 'per_variant_font_variables_enabled' => false, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
            ]
        ),
        'Toggling extended font output should trigger a generated CSS refresh because it changes emitted CSS.'
    );

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresAssetRefresh',
            [
                ['minify_css_output' => true, 'per_variant_font_variables_enabled' => true, 'extended_variable_category_sans_enabled' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
                ['minify_css_output' => true, 'per_variant_font_variables_enabled' => true, 'extended_variable_category_sans_enabled' => false, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
            ]
        ),
        'Changing granular extended-variable subsettings should trigger a generated CSS refresh because emitted CSS changes.'
    );

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresAssetRefresh',
            [
                ['class_output_enabled' => false, 'minify_css_output' => true, 'per_variant_font_variables_enabled' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
                ['class_output_enabled' => true, 'class_output_families_enabled' => true, 'minify_css_output' => true, 'per_variant_font_variables_enabled' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
            ]
        ),
        'Changing class output settings should trigger a generated CSS refresh because emitted CSS changes.'
    );
};

$tests['admin_controller_detects_which_setting_changes_require_reload'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresReload',
            [
                ['training_wheels_off' => false, 'monospace_role_enabled' => false],
                ['training_wheels_off' => true, 'monospace_role_enabled' => false],
            ]
        ),
        'Toggling onboarding hints should require a page reload because the admin shell only reads that state on boot.'
    );

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresReload',
            [
                ['training_wheels_off' => false, 'monospace_role_enabled' => false],
                ['training_wheels_off' => false, 'monospace_role_enabled' => true],
            ]
        ),
        'Toggling the monospace role should require a page reload because server-rendered controls depend on that setting.'
    );

    assertSameValue(
        false,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresReload',
            [
                ['training_wheels_off' => false, 'monospace_role_enabled' => false, 'preload_primary_fonts' => true],
                ['training_wheels_off' => false, 'monospace_role_enabled' => false, 'preload_primary_fonts' => false],
            ]
        ),
        'Settings that apply without rebuilding the admin shell should not ask for a page reload.'
    );
};

$tests['admin_controller_versions_admin_assets_from_plugin_version'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $version = invokePrivateMethod($controller, 'assetVersionFor');

    assertSameValue(TASTY_FONTS_VERSION, $version, 'Admin asset versioning should reuse the plugin version instead of hashing shipped files on every request.');
};

$tests['admin_controller_versions_local_admin_assets_with_content_hashes'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $version = invokePrivateMethod($controller, 'assetVersionFor', ['assets/js/admin.js']);

    assertSameValue(
        1,
        preg_match('/^' . preg_quote(TASTY_FONTS_VERSION, '/') . '\.\d+\.[a-f0-9]{12}$/', (string) $version),
        'Local admin asset versioning should append a file timestamp and content hash so browser reloads pick up fresh JS and CSS.'
    );
};

$tests['admin_controller_builds_reordered_overview_metrics'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $controller = $services['controller'];
    $metrics = invokePrivateMethod(
        $controller,
        'buildOverviewMetrics',
        [[
            'families' => 12,
            'published_families' => 7,
            'library_only_families' => 3,
            'local_families' => 9,
        ]]
    );

    assertSameValue(
        ['Families', 'Published', 'In Library Only', 'Self-hosted'],
        array_values(array_map(static fn (array $metric): string => (string) ($metric['label'] ?? ''), $metrics)),
        'The overview metrics should be reordered around library state and self-hosted counts instead of file size.'
    );
    assertSameValue(
        ['12', '7', '3', '9'],
        array_values(array_map(static fn (array $metric): string => (string) ($metric['value'] ?? ''), $metrics)),
        'The overview metrics should preserve the expected family counts after reordering.'
    );
};

$tests['admin_controller_excludes_generated_css_from_snippet_output_panels'] = static function (): void {
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
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-400.woff2'], 'paths' => ['woff2' => 'inter/Inter-400.woff2']],
            ],
        ],
        'published',
        true
    );
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $services['settings']->saveAppliedRoles($roles, ['Inter']);
    $services['settings']->setAutoApplyRoles(true);

    $panels = invokePrivateMethod(
        $services['controller'],
        'buildOutputPanels',
        [$roles, $services['settings']->getSettings(), $services['catalog']->getCatalog()]
    );

    assertSameValue(
        ['usage', 'variables', 'classes', 'stacks', 'names'],
        array_values(array_map(static fn (array $panel): string => (string) ($panel['key'] ?? ''), $panels)),
        'The Snippets panel should only expose the role snippet tabs after Generated CSS is moved to the top tab bar.'
    );
};

$tests['admin_controller_builds_monospace_role_output_panels_when_enabled'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'monospace' => '',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
    ];
    $settings = $services['settings']->saveSettings([
        'monospace_role_enabled' => '1',
        'minify_css_output' => '0',
        'minimal_output_preset_enabled' => '0',
    ]);
    $panels = invokePrivateMethod(
        $services['controller'],
        'buildOutputPanels',
        [$roles, $settings, $services['catalog']->getCatalog()]
    );
    $panelValues = [];
    $panelDisplayValues = [];

    foreach ($panels as $panel) {
        $panelValues[(string) ($panel['key'] ?? '')] = (string) ($panel['value'] ?? '');
        $panelDisplayValues[(string) ($panel['key'] ?? '')] = (string) ($panel['display_value'] ?? '');
    }

    assertContainsValue('--font-monospace: monospace;', $panelValues['variables'] ?? '', 'Enabled monospace support should add the monospace variable to the CSS Variables panel.');
    assertContainsValue('code, pre {', $panelValues['usage'] ?? '', 'Enabled monospace support should add the code/pre usage rule to the Site Snippet panel.');
    assertContainsValue('Class output is off', $panelValues['classes'] ?? '', 'The Font Classes panel should explain when the workflow is disabled.');
    assertContainsValue("monospace\n", ($panelValues['stacks'] ?? '') . "\n", 'Enabled monospace support should include the fallback-only monospace stack in the Font Stacks panel.');
    assertContainsValue('/* Role font stacks */', $panelDisplayValues['variables'] ?? '', 'The CSS Variables panel should expose readable section comments in its display value.');
    assertContainsValue('/* Body text */', $panelDisplayValues['usage'] ?? '', 'The Site Snippet panel should expose readable rule labels in its display value.');
};

$tests['admin_controller_keeps_minimal_output_panels_monospace_aware'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'monospace' => '',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
    ];
    $settings = $services['settings']->saveSettings([
        'monospace_role_enabled' => '1',
        'minify_css_output' => '0',
        'minimal_output_preset_enabled' => '1',
    ]);
    $panels = invokePrivateMethod(
        $services['controller'],
        'buildOutputPanels',
        [$roles, $settings, $services['catalog']->getCatalog()]
    );
    $panelValues = [];

    foreach ($panels as $panel) {
        $panelValues[(string) ($panel['key'] ?? '')] = (string) ($panel['value'] ?? '');
    }

    assertContainsValue('--font-monospace: monospace;', $panelValues['variables'] ?? '', 'Minimal output should still include the monospace role variable in the CSS Variables panel when the monospace role is enabled.');
    assertContainsValue('--font-monospace: monospace;', $panelValues['usage'] ?? '', 'Minimal output should still include the monospace role variable in the Site Snippet panel when the monospace role is enabled.');
    assertNotContainsValue('code, pre {', $panelValues['usage'] ?? '', 'Minimal output should keep the Site Snippet panel variable-only.');
};

$tests['admin_controller_builds_font_class_output_panel_content'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'inter-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Draft Only',
        'draft-only',
        [
            'id' => 'draft-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Draft Only',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'draft-only/DraftOnly-400-normal.woff2'],
            ]],
        ],
        'library_only',
        true
    );

    $roles = [
        'heading' => 'Inter',
        'body' => '',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'serif',
    ];
    $settings = $services['settings']->saveSettings([
        'class_output_enabled' => '1',
        'minify_css_output' => '0',
        'monospace_role_enabled' => '0',
    ]);
    $services['settings']->setAutoApplyRoles(true);
    $settings['auto_apply_roles'] = true;

    $panels = invokePrivateMethod(
        $services['controller'],
        'buildOutputPanels',
        [$roles, $settings, $services['catalog']->getCatalog()]
    );
    $panelValues = [];

    foreach ($panels as $panel) {
        $panelValues[(string) ($panel['key'] ?? '')] = (string) ($panel['value'] ?? '');
    }

    assertContainsValue('.font-heading', $panelValues['classes'] ?? '', 'The Font Classes panel should include role classes when the mode enables them.');
    assertContainsValue('.font-inter', $panelValues['classes'] ?? '', 'The Font Classes panel should include published family classes when the mode enables them.');
    assertNotContainsValue('.font-draft-only', $panelValues['classes'] ?? '', 'The Font Classes panel should skip library-only families so it matches frontend output.');
};

$tests['admin_controller_builds_role_class_output_panel_content_when_apply_sitewide_is_off'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $roles = [
        'heading' => 'Inter',
        'body' => '',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
    ];
    $settings = $services['settings']->saveSettings([
        'class_output_enabled' => '1',
        'class_output_families_enabled' => '0',
        'minify_css_output' => '0',
    ]);

    $panels = invokePrivateMethod(
        $services['controller'],
        'buildOutputPanels',
        [$roles, $settings, $services['catalog']->getCatalog()]
    );
    $panelValues = [];

    foreach ($panels as $panel) {
        $panelValues[(string) ($panel['key'] ?? '')] = (string) ($panel['value'] ?? '');
    }

    assertContainsValue('.font-heading', $panelValues['classes'] ?? '', 'The Font Classes panel should expose role classes from the saved draft even when sitewide delivery is off.');
    assertNotContainsValue('Role classes are unavailable while Apply Sitewide is off.', $panelValues['classes'] ?? '', 'The Font Classes panel should no longer claim role classes are unavailable when sitewide delivery is off.');
};

$tests['admin_controller_builds_output_panels_from_applied_roles_when_sitewide_is_on'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $draftRoles = [
        'heading' => 'Draft Heading',
        'body' => 'Draft Body',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
    ];
    $appliedRoles = [
        'heading' => 'Live Heading',
        'body' => 'Live Body',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
    ];
    $settings = $services['settings']->saveSettings([
        'class_output_enabled' => '1',
        'class_output_families_enabled' => '0',
        'minify_css_output' => '0',
    ]);
    $settings['auto_apply_roles'] = true;

    $panels = invokePrivateMethod(
        $services['controller'],
        'buildOutputPanels',
        [$draftRoles, $settings, $services['catalog']->getCatalog(), $appliedRoles]
    );
    $panelValues = [];
    $panelDisplayValues = [];

    foreach ($panels as $panel) {
        $panelValues[(string) ($panel['key'] ?? '')] = (string) ($panel['value'] ?? '');
        $panelDisplayValues[(string) ($panel['key'] ?? '')] = (string) ($panel['display_value'] ?? '');
    }

    assertContainsValue('"Live Heading"', $panelValues['usage'] ?? '', 'Sitewide-on snippet output should use applied roles for the site snippet.');
    assertContainsValue('"Live Heading"', $panelValues['classes'] ?? '', 'Sitewide-on snippet output should use applied roles for the font classes panel.');
    assertContainsValue('/* Role classes */', $panelDisplayValues['classes'] ?? '', 'Font Classes should expose readable class section comments in the panel display value.');
    assertContainsValue("Live Heading\nLive Body", ($panelValues['names'] ?? ''), 'Sitewide-on snippet output should use applied roles for the font names panel.');
    assertNotContainsValue('"Draft Heading"', $panelValues['classes'] ?? '', 'Sitewide-on font classes should not reflect unsaved draft-only role changes.');
};

$tests['admin_controller_exposes_class_output_settings_in_page_context'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'class_output_enabled' => '1',
        'class_output_role_heading_enabled' => '0',
        'class_output_role_body_enabled' => '0',
        'class_output_role_monospace_enabled' => '0',
        'class_output_role_alias_interface_enabled' => '0',
        'class_output_role_alias_ui_enabled' => '0',
        'class_output_role_alias_code_enabled' => '0',
        'class_output_category_sans_enabled' => '0',
        'class_output_category_serif_enabled' => '0',
        'class_output_category_mono_enabled' => '0',
        'class_output_families_enabled' => '1',
    ]);

    $context = invokePrivateMethod($services['controller'], 'buildPageContext', []);

    assertSameValue(true, !empty($context['class_output_enabled']), 'Page context should expose the class output master toggle.');
    assertSameValue(false, !empty($context['class_output_role_heading_enabled']), 'Page context should expose granular role class toggles.');
    assertSameValue(false, !empty($context['class_output_category_serif_enabled']), 'Page context should expose granular category class toggles.');
    assertSameValue(true, !empty($context['class_output_families_enabled']), 'Page context should expose the family class toggle.');
};

$tests['admin_controller_exposes_css_delivery_mode_in_page_context'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['css_delivery_mode' => 'inline']);

    $context = invokePrivateMethod($services['controller'], 'buildPageContext', []);

    assertSameValue('inline', (string) ($context['css_delivery_mode'] ?? ''), 'Page context should expose the saved CSS delivery mode.');
    assertSameValue(
        ['file', 'inline'],
        array_values(array_map(static fn (array $option): string => (string) ($option['value'] ?? ''), (array) ($context['css_delivery_mode_options'] ?? []))),
        'Page context should expose the supported CSS delivery mode options for the Output Settings form.'
    );
};

$tests['admin_controller_builds_variant_variable_output_panel_content'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'inter-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular', '700italic'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'weight' => '400',
                    'style' => 'normal',
                    'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                ],
                [
                    'family' => 'Inter',
                    'weight' => '700',
                    'style' => 'italic',
                    'files' => ['woff2' => 'inter/Inter-700-italic.woff2'],
                ],
            ],
        ],
        'published',
        true
    );

    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $settings = $services['settings']->saveSettings(['minify_css_output' => '0', 'per_variant_font_variables_enabled' => '1']);
    $panels = invokePrivateMethod(
        $services['controller'],
        'buildOutputPanels',
        [$roles, $settings, $services['catalog']->getCatalog()]
    );
    $panelValues = [];

    foreach ($panels as $panel) {
        $panelValues[(string) ($panel['key'] ?? '')] = (string) ($panel['value'] ?? '');
    }

    assertContainsValue('--font-inter: "Inter", sans-serif;', $panelValues['variables'] ?? '', 'The CSS Variables panel should include the base family variable.');
    assertContainsValue('--font-interface: var(--font-body);', $panelValues['variables'] ?? '', 'The CSS Variables panel should include interface aliases.');
    assertContainsValue('--weight-400: 400;', $panelValues['variables'] ?? '', 'The CSS Variables panel should include numeric global weight tokens.');
    assertContainsValue('--weight-bold: var(--weight-700);', $panelValues['variables'] ?? '', 'The CSS Variables panel should include semantic global weight aliases.');
    assertNotContainsValue('--font-inter-regular', $panelValues['variables'] ?? '', 'The CSS Variables panel should no longer expose per-family semantic variant aliases.');
    assertNotContainsValue(':root', $panelValues['variables'] ?? '', 'The CSS Variables panel should expose declarations only, without the root selector wrapper.');
};

$tests['admin_controller_builds_five_preview_panels_including_code'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $panels = invokePrivateMethod($services['controller'], 'buildPreviewPanels');
    $keys = array_map(
        static fn (array $panel): string => (string) ($panel['key'] ?? ''),
        $panels
    );

    assertSameValue(
        ['editorial', 'card', 'reading', 'interface', 'code'],
        $keys,
        'Preview panels should include the dedicated Code tab after the existing four preview modes.'
    );
};

$tests['admin_controller_keeps_role_font_weights_in_usage_panel_when_variable_output_is_disabled'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-700-normal.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('jetbrains-mono/JetBrainsMono-400-normal.woff2'), 'font-data');
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
                ['family' => 'Inter', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-400-normal.woff2']],
                ['family' => 'Inter', 'weight' => '700', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-700-normal.woff2']],
            ],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'JetBrains Mono',
        'jetbrains-mono',
        [
            'id' => 'local-self_hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'JetBrains Mono', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2']],
            ],
        ],
        'published',
        true
    );

    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'monospace' => 'JetBrains Mono',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
    ];
    $settings = $services['settings']->saveSettings([
        'minify_css_output' => '0',
        'per_variant_font_variables_enabled' => '0',
        'role_usage_font_weight_enabled' => '1',
        'class_output_enabled' => '1',
        'monospace_role_enabled' => '1',
    ]);
    $panels = invokePrivateMethod(
        $services['controller'],
        'buildOutputPanels',
        [$roles, $settings, $services['catalog']->getCatalog()]
    );
    $panelValues = [];

    foreach ($panels as $panel) {
        $panelValues[(string) ($panel['key'] ?? '')] = (string) ($panel['value'] ?? '');
    }

    assertContainsValue('font-weight: 400;', $panelValues['usage'] ?? '', 'The Site Snippet should keep raw body font weights when variable output is disabled.');
    assertContainsValue('font-weight: 700;', $panelValues['usage'] ?? '', 'The Site Snippet should keep raw heading font weights when variable output is disabled.');
    assertNotContainsValue('var(--weight-700)', $panelValues['usage'] ?? '', 'The Site Snippet should not rely on weight tokens when variable output is disabled.');
};

$tests['admin_controller_exposes_generated_css_as_a_top_level_panel'] = static function (): void {
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
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-400.woff2'], 'paths' => ['woff2' => 'inter/Inter-400.woff2']],
            ],
        ],
        'published',
        true
    );
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $services['settings']->saveAppliedRoles($roles, ['Inter']);
    $services['settings']->setAutoApplyRoles(true);
    $services['catalog']->invalidate();
    $services['assets']->refreshGeneratedAssets(false, false);

    $panel = invokePrivateMethod(
        $services['controller'],
        'buildGeneratedCssPanel',
        [$services['settings']->getSettings()]
    );

    assertSameValue('generated', (string) ($panel['key'] ?? ''), 'Generated CSS should be exposed through a dedicated top-level panel key.');
    assertContainsValue('@font-face', (string) ($panel['value'] ?? ''), 'The top-level Generated CSS panel should include the current stylesheet output.');
};

$tests['admin_controller_marks_top_level_generated_css_panel_unavailable_when_sitewide_is_off'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $panel = invokePrivateMethod(
        $services['controller'],
        'buildGeneratedCssPanel',
        [$services['settings']->getSettings()]
    );

    assertSameValue(
        'Not generated while sitewide delivery is off.',
        (string) ($panel['value'] ?? ''),
        'The top-level Generated CSS panel should explain that there is no live sitewide output while sitewide delivery is off.'
    );
};

$tests['admin_controller_reuses_cached_search_results_during_search_cooldown'] = static function (): void {
    resetTestState();

    global $currentUserId;

    $currentUserId = 42;
    $services = makeServiceGraph();
    $resolverCalls = 0;

    $first = invokePrivateMethod(
        $services['controller'],
        'resolveRateLimitedSearch',
        [
            'google',
            'Inter',
            static function (string $query) use (&$resolverCalls): array {
                $resolverCalls += 1;

                return ['items' => [['family' => $query, 'call' => $resolverCalls]]];
            },
        ]
    );
    $second = invokePrivateMethod(
        $services['controller'],
        'resolveRateLimitedSearch',
        [
            'google',
            'Inter',
            static function (string $query) use (&$resolverCalls): array {
                $resolverCalls += 1;

                return ['items' => [['family' => $query, 'call' => $resolverCalls]]];
            },
        ]
    );
    $third = invokePrivateMethod(
        $services['controller'],
        'resolveRateLimitedSearch',
        [
            'google',
            'Lora',
            static function (string $query) use (&$resolverCalls): array {
                $resolverCalls += 1;

                return ['items' => [['family' => $query, 'call' => $resolverCalls]]];
            },
        ]
    );

    assertSameValue(2, $resolverCalls, 'Repeated search calls in the cooldown window should reuse the cached result, while new queries should still execute.');
    assertSameValue($first, $second, 'Repeated search calls in the cooldown window should return the cached response payload.');
    assertSameValue('Lora', (string) ($third['items'][0]['family'] ?? ''), 'Search cooldown should not block a different query for the same user.');
    assertSameValue(
        true,
        is_array(get_transient('tasty_fonts_search_cooldown_42')),
        'Search cooldown should be stored in a per-user transient.'
    );
};

$tests['admin_controller_enqueues_tokens_before_admin_styles'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;

    $services = makeServiceGraph();
    $services['controller']->enqueueAssets('toplevel_page_' . AdminController::MENU_SLUG);

    assertSameValue(
        TASTY_FONTS_URL . 'assets/css/tokens.css',
        (string) ($enqueuedStyles['tasty-fonts-admin-tokens']['src'] ?? ''),
        'The plugin admin page should enqueue the standalone token stylesheet.'
    );

    assertSameValue(
        ['tasty-fonts-admin-tokens'],
        $enqueuedStyles['tasty-fonts-admin']['deps'] ?? null,
        'The admin stylesheet should depend on the token stylesheet so custom properties load first.'
    );
};

$tests['admin_controller_localizes_rest_transport_config'] = static function (): void {
    resetTestState();

    global $localizedScripts;

    $services = makeServiceGraph();
    $services['controller']->enqueueAssets('toplevel_page_' . AdminController::MENU_SLUG);

    assertSameValue(
        'https://example.test/wp-json/tasty-fonts/v1/',
        (string) ($localizedScripts['tasty-fonts-admin']['data']['restUrl'] ?? ''),
        'Admin scripts should receive the REST base URL used by the bundled admin client.'
    );
    assertSameValue(
        'nonce:wp_rest',
        (string) ($localizedScripts['tasty-fonts-admin']['data']['restNonce'] ?? ''),
        'Admin scripts should receive the WordPress REST nonce for authenticated requests.'
    );
    assertSameValue(
        'google/search',
        (string) ($localizedScripts['tasty-fonts-admin']['data']['routes']['searchGoogle'] ?? ''),
        'Admin scripts should receive the Google search REST route path.'
    );
    assertSameValue(
        'local/upload',
        (string) ($localizedScripts['tasty-fonts-admin']['data']['routes']['uploadLocal'] ?? ''),
        'Admin scripts should receive the local upload REST route path.'
    );
    assertSameValue(
        'draft',
        (string) ($localizedScripts['tasty-fonts-admin']['data']['previewBootstrap']['baselineSource'] ?? ''),
        'Admin scripts should receive the preview baseline source for the workspace bootstrap.'
    );
    assertSameValue(
        AdminController::PAGE_ROLES,
        (string) ($localizedScripts['tasty-fonts-admin']['data']['currentPage'] ?? ''),
        'Admin scripts should receive the current admin page identifier for page-scoped UI state.'
    );
};

$tests['admin_controller_localizes_role_family_catalog_from_active_deliveries'] = static function (): void {
    resetTestState();

    global $localizedScripts;

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'inter-static-inactive',
            'label' => 'Self-hosted (inactive)',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'static',
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '700', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-700.woff2'], 'paths' => []],
            ],
        ],
        'published',
        false
    );
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'inter-variable-active',
            'label' => 'Self-hosted (active)',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'variable',
            'variants' => ['regular'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '100 900',
                    'style' => 'normal',
                    'axes' => [
                        'WGHT' => ['min' => '100', 'default' => '400', 'max' => '900'],
                        'opsz' => ['min' => '14', 'default' => '32', 'max' => '72'],
                    ],
                    'files' => ['woff2' => 'inter/Inter-Variable.woff2'],
                    'paths' => [],
                ],
            ],
        ],
        'published',
        true
    );

    $services['controller']->enqueueAssets('toplevel_page_' . AdminController::MENU_SLUG);

    $catalog = is_array($localizedScripts['tasty-fonts-admin']['data']['roleFamilyCatalog'] ?? null)
        ? $localizedScripts['tasty-fonts-admin']['data']['roleFamilyCatalog']
        : [];
    $inter = is_array($catalog['Inter'] ?? null) ? $catalog['Inter'] : [];

    assertSameValue('inter-variable-active', (string) ($inter['activeDeliveryId'] ?? ''), 'Admin scripts should localize the active delivery ID for each family.');
    assertContainsValue('Self-hosted (active)', (string) ($inter['activeDeliveryLabel'] ?? ''), 'Admin scripts should localize the active delivery label for each family.');
    assertSameValue('variable', (string) ($inter['format'] ?? ''), 'Admin scripts should localize the active delivery format for each family.');
    assertSameValue(true, !empty($inter['hasWeightAxis']), 'Admin scripts should report whether the active delivery exposes a weight axis.');
    assertSameValue(
        ['OPSZ', 'WGHT'],
        array_keys(is_array($inter['axes'] ?? null) ? $inter['axes'] : []),
        'Admin scripts should expose variable axes from the active delivery only.'
    );
    assertSameValue(
        [
            [
                'value' => '400',
                'label' => '400 Regular',
            ],
        ],
        $inter['weights'] ?? null,
        'Admin scripts should derive role weight metadata from the active delivery only.'
    );
};

$tests['admin_controller_registers_hidden_legacy_admin_routes'] = static function (): void {
    resetTestState();

    global $menuPageCalls;
    global $submenuPageCalls;

    $services = makeServiceGraph();
    $services['controller']->registerMenu();

    assertSameValue(AdminController::MENU_SLUG, (string) ($menuPageCalls[0]['menu_slug'] ?? ''), 'The top-level Tasty Fonts menu should keep the existing menu slug.');
    assertSameValue(
        [
            AdminController::MENU_SLUG_LIBRARY,
            AdminController::MENU_SLUG_SETTINGS,
            AdminController::MENU_SLUG_DIAGNOSTICS,
        ],
        array_map(static fn (array $entry): string => (string) ($entry['menu_slug'] ?? ''), $submenuPageCalls),
        'The admin menu should keep hidden legacy routes for Library, Settings, and Diagnostics.'
    );
};

$tests['admin_controller_recognizes_task_based_admin_hooks'] = static function (): void {
    resetTestState();

    assertSameValue(true, AdminController::isPluginAdminHook('toplevel_page_' . AdminController::MENU_SLUG), 'The top-level roles page hook should be recognized.');
    assertSameValue(true, AdminController::isPluginAdminHook('tasty-fonts_page_' . AdminController::MENU_SLUG_LIBRARY), 'The Library submenu hook should be recognized.');
    assertSameValue(true, AdminController::isPluginAdminHook('tasty-fonts_page_' . AdminController::MENU_SLUG_SETTINGS), 'The Settings submenu hook should be recognized.');
    assertSameValue(true, AdminController::isPluginAdminHook('tasty-fonts_page_' . AdminController::MENU_SLUG_DIAGNOSTICS), 'The Diagnostics submenu hook should be recognized.');
    assertSameValue(true, AdminController::isPluginAdminHook('custom-parent_page_' . AdminController::MENU_SLUG_SETTINGS), 'Submenu hooks should be recognized by their page slug even when the parent hook prefix differs.');
    assertSameValue(false, AdminController::isPluginAdminHook('settings_page_general'), 'Unrelated admin hooks should not load plugin assets.');
};

$tests['admin_controller_omits_ajax_transport_config_from_localized_admin_data'] = static function (): void {
    resetTestState();

    global $localizedScripts;

    $services = makeServiceGraph();
    $services['controller']->enqueueAssets('toplevel_page_' . AdminController::MENU_SLUG);

    $data = is_array($localizedScripts['tasty-fonts-admin']['data'] ?? null)
        ? $localizedScripts['tasty-fonts-admin']['data']
        : [];

    assertSameValue(false, isset($data['ajaxUrl']), 'Admin scripts should not receive an admin-ajax endpoint once the plugin is REST-only.');

    foreach (
        [
            'searchNonce',
            'bunnySearchNonce',
            'googleFamilyNonce',
            'bunnyFamilyNonce',
            'importNonce',
            'bunnyImportNonce',
            'uploadNonce',
            'saveFallbackNonce',
            'saveFontDisplayNonce',
            'saveRolesNonce',
            'saveFamilyDeliveryNonce',
            'saveFamilyPublishStateNonce',
            'deleteDeliveryProfileNonce',
        ] as $key
    ) {
        assertSameValue(false, isset($data[$key]), sprintf('Admin scripts should not receive the removed "%s" AJAX nonce field.', $key));
    }
};

$tests['admin_controller_enqueues_wp_i18n_and_script_translations'] = static function (): void {
    resetTestState();

    global $enqueuedScripts;
    global $scriptTranslations;
    global $styleData;

    $services = makeServiceGraph();
    $services['controller']->enqueueAssets('toplevel_page_' . AdminController::MENU_SLUG);

    assertSameValue(
        ['wp-i18n', 'tasty-fonts-admin-contracts'],
        $enqueuedScripts['tasty-fonts-admin']['deps'] ?? null,
        'Admin scripts should depend on wp-i18n and the shared admin contracts so translations and tested helper contracts load together.'
    );
    assertSameValue(
        'tasty-fonts',
        (string) ($scriptTranslations['tasty-fonts-admin']['domain'] ?? ''),
        'Admin scripts should register WordPress script translations for the tasty-fonts domain.'
    );
    assertSameValue(
        TASTY_FONTS_DIR . 'languages',
        (string) ($scriptTranslations['tasty-fonts-admin']['path'] ?? ''),
        'Admin scripts should register the plugin languages directory for script translation JSON files.'
    );
    assertSameValue(
        'replace',
        (string) ($styleData['tasty-fonts-admin']['rtl'] ?? ''),
        'Admin styles should register an RTL replacement stylesheet.'
    );
};

$tests['admin_controller_localizes_runtime_admin_strings_only'] = static function (): void {
    resetTestState();

    global $localizedScripts;

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['training_wheels_off' => '1', 'monospace_role_enabled' => '1']);
    $services['controller']->enqueueAssets('toplevel_page_' . AdminController::MENU_SLUG);

    $runtimeStrings = is_array($localizedScripts['tasty-fonts-admin']['data']['runtimeStrings'] ?? null)
        ? $localizedScripts['tasty-fonts-admin']['data']['runtimeStrings']
        : [];

    assertSameValue(
        'Add a Google Fonts API key above to enable search, or use manual import below.',
        (string) ($runtimeStrings['searchDisabled'] ?? ''),
        'Admin scripts should still receive the runtime-only search-disabled message from PHP.'
    );
    assertSameValue(
        false,
        isset($localizedScripts['tasty-fonts-admin']['data']['strings']),
        'Admin scripts should not receive the legacy static string table once script translations are registered.'
    );
    assertSameValue(
        true,
        !empty($localizedScripts['tasty-fonts-admin']['data']['trainingWheelsOff']),
        'Admin scripts should receive the saved training-wheels preference so hover help can be disabled client-side.'
    );
    assertSameValue(
        true,
        !empty($localizedScripts['tasty-fonts-admin']['data']['monospaceRoleEnabled']),
        'Admin scripts should receive the saved monospace-role flag so the admin client can include the optional third role when it is enabled.'
    );
};

$tests['rest_controller_registers_expected_admin_routes'] = static function (): void {
    resetTestState();

    global $registeredRestRoutes;

    $services = makeServiceGraph();
    $services['rest']->registerRoutes();

    $expectedRoutes = [
        'tasty-fonts/v1/settings' => 'PATCH',
        'tasty-fonts/v1/google/search' => 'GET',
        'tasty-fonts/v1/bunny/search' => 'GET',
        'tasty-fonts/v1/google/family' => 'GET',
        'tasty-fonts/v1/bunny/family' => 'GET',
        'tasty-fonts/v1/google/import' => 'POST',
        'tasty-fonts/v1/bunny/import' => 'POST',
        'tasty-fonts/v1/local/upload' => 'POST',
        'tasty-fonts/v1/families/fallback' => 'PATCH',
        'tasty-fonts/v1/families/font-display' => 'PATCH',
        'tasty-fonts/v1/roles/draft' => 'PATCH',
        'tasty-fonts/v1/families/delivery' => 'PATCH',
        'tasty-fonts/v1/families/publish-state' => 'PATCH',
        'tasty-fonts/v1/families/delivery-profile' => 'DELETE',
    ];

    assertSameValue(
        count($expectedRoutes),
        count($registeredRestRoutes),
        'The REST controller should register the full admin route surface.'
    );

    foreach ($expectedRoutes as $route => $method) {
        assertSameValue(
            $method,
            (string) ($registeredRestRoutes[$route]['args']['methods'] ?? ''),
            'Each REST route should register with the expected HTTP method.'
        );
        assertSameValue(
            true,
            is_callable($registeredRestRoutes[$route]['args']['callback'] ?? null),
            'Each REST route should register a callable endpoint handler.'
        );
        assertSameValue(
            true,
            is_callable($registeredRestRoutes[$route]['args']['permission_callback'] ?? null),
            'Each REST route should register a callable permission callback.'
        );
        assertSameValue(
            true,
            is_array($registeredRestRoutes[$route]['args']['args'] ?? null),
            'Each REST route should expose a registered args schema.'
        );
    }

    assertSameValue(
        true,
        isset($registeredRestRoutes['tasty-fonts/v1/google/import']['args']['args']['delivery_mode']['sanitize_callback']),
        'Hosted import routes should sanitize the delivery mode before controller handling.'
    );
    assertSameValue(
        true,
        isset($registeredRestRoutes['tasty-fonts/v1/roles/draft']['args']['args']['heading_axes']['validate_callback']),
        'Role draft routes should validate nested axis payloads.'
    );
    assertSameValue(
        true,
        !empty($registeredRestRoutes['tasty-fonts/v1/families/publish-state']['args']['args']['publish_state']['required']),
        'Publish-state routes should require the publish_state parameter.'
    );
};

$tests['bundled_js_translation_placeholder_exists'] = static function (): void {
    resetTestState();

    global $scriptTranslations;

    $services = makeServiceGraph();
    $services['controller']->enqueueAssets('toplevel_page_' . AdminController::MENU_SLUG);

    assertSameValue(
        'tasty-fonts',
        (string) ($scriptTranslations['tasty-fonts-admin']['domain'] ?? ''),
        'The admin script should register WordPress translation support for the tasty-fonts textdomain.'
    );
};

$tests['rest_controller_returns_native_payloads_for_write_routes'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/families/fallback');
    $request->set_body_params([
        'family' => 'Inter',
        'fallback' => 'serif',
    ]);

    $response = $services['rest']->saveFamilyFallback($request);

    assertSameValue(true, $response instanceof WP_REST_Response, 'REST write routes should return a native REST response object.');
    assertSameValue('Inter', (string) ($response->get_data()['family'] ?? ''), 'REST write routes should return the saved family in the response body.');
    assertSameValue('serif', (string) ($response->get_data()['fallback'] ?? ''), 'REST write routes should return the saved fallback in the response body.');
};

$tests['rest_controller_family_fallback_returns_refreshed_generated_css_panel'] = static function (): void {
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
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-400.woff2'], 'paths' => ['woff2' => 'inter/Inter-400.woff2']],
            ],
        ],
        'published',
        true
    );
    $services['settings']->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        ['Inter']
    );
    $services['settings']->setAutoApplyRoles(true);
    $services['assets']->refreshGeneratedAssets(false, false);

    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/families/fallback');
    $request->set_body_params([
        'family' => 'Inter',
        'fallback' => 'serif',
    ]);

    $response = $services['rest']->saveFamilyFallback($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];
    $panel = is_array($data['generated_css_panel'] ?? null) ? $data['generated_css_panel'] : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'Family fallback saves should return a native REST response.');
    assertSameValue('generated', (string) ($panel['key'] ?? ''), 'Family fallback saves should include the refreshed Generated CSS diagnostics panel.');
    assertContainsValue('"Inter",serif', (string) ($panel['value'] ?? ''), 'The refreshed Generated CSS panel should include the saved fallback stack.');
    assertContainsValue('"Inter",serif', (string) ($panel['readable_display_value'] ?? ''), 'The readable Generated CSS panel payload should also reflect the saved fallback stack.');
};

$tests['rest_controller_settings_accepts_patch_payloads'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
        'css_delivery_mode' => 'inline',
        'font_display' => 'swap',
        'unicode_range_mode' => 'custom',
        'unicode_range_custom_value' => 'u+0000-00ff, u+0100-024f',
        'minify_css_output' => '0',
        'role_usage_font_weight_enabled' => '1',
        'tasty_fonts_output_quick_mode' => 'minimal',
        'output_quick_mode_preference' => 'minimal',
        'minimal_output_preset_enabled' => '1',
        'class_output_enabled' => '0',
        'per_variant_font_variables_enabled' => '1',
        'class_output_families_enabled' => '0',
        'extended_variable_weight_tokens_enabled' => '0',
    ]);

    $response = $services['rest']->saveSettings($request);
    $data = $response->get_data();

    assertSameValue(true, $response instanceof WP_REST_Response, 'The settings autosave route should return a native REST response object.');
    assertSameValue('inline', (string) ($data['settings']['css_delivery_mode'] ?? ''), 'The settings autosave route should return the saved CSS delivery mode.');
    assertSameValue('swap', (string) ($data['settings']['font_display'] ?? ''), 'The settings autosave route should return the saved font-display mode.');
    assertSameValue('custom', (string) ($data['settings']['unicode_range_mode'] ?? ''), 'The settings autosave route should return the saved unicode-range mode.');
    assertSameValue('U+0000-00FF,U+0100-024F', (string) ($data['settings']['unicode_range_custom_value'] ?? ''), 'The settings autosave route should normalize and return the saved custom unicode-range value.');
    assertSameValue(false, !empty($data['settings']['role_usage_font_weight_enabled']), 'The minimal preset should suppress role font-weight output.');
    assertSameValue(true, !empty($data['settings']['minimal_output_preset_enabled']), 'The settings autosave route should persist the minimal output preset flag.');
    assertSameValue(false, !empty($data['settings']['class_output_enabled']), 'The minimal preset should suppress class output.');
    assertSameValue(true, !empty($data['settings']['per_variant_font_variables_enabled']), 'The settings autosave route should continue to persist variable output via explicit booleans rather than a removed all preset.');
    assertSameValue('minimal', (string) ($data['settings']['output_quick_mode_preference'] ?? ''), 'The settings autosave route should persist the explicit output quick-mode preference.');
    assertSameValue(false, !empty($data['reload_required']), 'Settings that only patch client-synced controls should not ask the autosave client to reload the page.');
    assertSameValue(true, is_array($data['output_panels'] ?? null), 'The settings autosave route should return refreshed output panels for the live admin snippets.');
    assertContainsValue('usage', implode(',', array_map(static fn (array $panel): string => (string) ($panel['key'] ?? ''), (array) ($data['output_panels'] ?? []))), 'The refreshed output panels payload should include the site snippet tab.');
    assertContainsValue('Plugin settings saved', (string) ($data['message'] ?? ''), 'The settings autosave route should return the save summary message.');
};

$tests['rest_controller_settings_keeps_custom_output_preference_sticky'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
        'minimal_output_preset_enabled' => '0',
        'output_quick_mode_preference' => 'custom',
        'class_output_enabled' => '0',
        'per_variant_font_variables_enabled' => '1',
        'role_usage_font_weight_enabled' => '0',
        'extended_variable_weight_tokens_enabled' => '1',
        'extended_variable_role_aliases_enabled' => '1',
        'extended_variable_category_sans_enabled' => '1',
        'extended_variable_category_serif_enabled' => '1',
    ]);

    $response = $services['rest']->saveSettings($request);
    $data = $response->get_data();

    assertSameValue(true, $response instanceof WP_REST_Response, 'Sticky custom output saves should return a native REST response.');
    assertSameValue('custom', (string) ($data['settings']['output_quick_mode_preference'] ?? ''), 'Custom should remain selected when the saved booleans happen to match variables-only.');
};

$tests['rest_controller_settings_preserves_variables_and_classes_output_presets'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
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

    $response = $services['rest']->saveSettings($request);
    $data = $response->get_data();

    assertSameValue(true, $response instanceof WP_REST_Response, 'Variables-only autosave should return a native REST response.');
    assertSameValue('variables', (string) ($data['settings']['output_quick_mode_preference'] ?? ''), 'Variables-only should remain selected when the saved booleans still match the preset.');
    assertSameValue(false, !empty($data['settings']['class_output_enabled']), 'Variables-only should keep class output disabled.');
    assertSameValue(true, !empty($data['settings']['per_variant_font_variables_enabled']), 'Variables-only should keep variable output enabled.');
    assertSameValue(false, !empty($data['settings']['role_usage_font_weight_enabled']), 'Variables-only should keep role font-weight output disabled.');

    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
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

    $response = $services['rest']->saveSettings($request);
    $data = $response->get_data();

    assertSameValue(true, $response instanceof WP_REST_Response, 'Classes-only autosave should return a native REST response.');
    assertSameValue('classes', (string) ($data['settings']['output_quick_mode_preference'] ?? ''), 'Classes-only should remain selected when the saved booleans still match the preset.');
    assertSameValue(true, !empty($data['settings']['class_output_enabled']), 'Classes-only should keep class output enabled.');
    assertSameValue(false, !empty($data['settings']['per_variant_font_variables_enabled']), 'Classes-only should keep variable output disabled.');
    assertSameValue(false, !empty($data['settings']['role_usage_font_weight_enabled']), 'Classes-only should keep role font-weight output disabled.');
};

$tests['rest_controller_settings_rejects_invalid_custom_unicode_range'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
        'unicode_range_mode' => 'custom',
        'unicode_range_custom_value' => 'latin',
    ]);

    $response = $services['rest']->saveSettings($request);

    assertWpErrorCode('tasty_fonts_invalid_unicode_range', $response, 'Invalid custom unicode-range values should be rejected through the settings autosave route.');
};

$tests['rest_controller_settings_rejects_zero_output_states'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
        'minimal_output_preset_enabled' => '0',
        'output_quick_mode_preference' => 'custom',
        'class_output_enabled' => '0',
        'per_variant_font_variables_enabled' => '0',
    ]);

    $response = $services['rest']->saveSettings($request);

    assertWpErrorCode('tasty_fonts_output_required', $response, 'Saving a zero-output configuration should be rejected through the settings autosave route.');
};

$tests['rest_controller_settings_reload_toast_mentions_reload_when_needed'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
        'training_wheels_off' => '1',
    ]);

    $response = $services['rest']->saveSettings($request);
    $data = $response->get_data();

    assertSameValue(true, $response instanceof WP_REST_Response, 'The settings autosave route should return a native REST response object.');
    assertSameValue(true, !empty($data['reload_required']), 'Reload-only settings should return an explicit reload flag for the autosave client.');
    assertContainsValue('Reload the page to apply this change.', (string) ($data['message'] ?? ''), 'Reload-only settings should mention the required page reload in the autosave toast message.');
};

$tests['rest_controller_settings_reenables_monospace_class_outputs_when_the_role_is_first_enabled'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'class_output_enabled' => '1',
        'class_output_role_monospace_enabled' => '0',
        'class_output_role_alias_code_enabled' => '0',
        'class_output_category_mono_enabled' => '0',
    ]);

    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
        'monospace_role_enabled' => '1',
    ]);

    $response = $services['rest']->saveSettings($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'Enabling the monospace role through autosave should return a native REST response.');
    assertSameValue(true, !empty($data['settings']['monospace_role_enabled']), 'Autosave should persist the monospace role setting.');
    assertSameValue(true, !empty($data['settings']['class_output_role_monospace_enabled']), 'Autosave should restore the monospace class output when the role is first enabled.');
    assertSameValue(true, !empty($data['settings']['class_output_role_alias_code_enabled']), 'Autosave should restore the code alias output when the role is first enabled.');
    assertSameValue(true, !empty($data['settings']['class_output_category_mono_enabled']), 'Autosave should restore the mono category output when the role is first enabled.');
};

$tests['rest_controller_settings_reload_flag_covers_update_channel_changes'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
        'update_channel' => SettingsRepository::UPDATE_CHANNEL_BETA,
    ]);

    $response = $services['rest']->saveSettings($request);
    $data = $response->get_data();

    assertSameValue(true, $response instanceof WP_REST_Response, 'The settings autosave route should return a native REST response object.');
    assertSameValue(true, !empty($data['reload_required']), 'Update channel changes should request a page reload so the server-rendered channel status refreshes.');
    assertSameValue(SettingsRepository::UPDATE_CHANNEL_BETA, (string) ($data['settings']['update_channel'] ?? ''), 'The settings autosave route should persist the selected update channel.');
};

$tests['rest_controller_settings_reload_flag_covers_integrations_shell_changes'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
        'block_editor_font_library_sync_enabled' => '0',
    ]);

    $response = $services['rest']->saveSettings($request);
    $data = $response->get_data();

    assertSameValue(true, $response instanceof WP_REST_Response, 'The settings autosave route should return a native REST response object.');
    assertSameValue(true, !empty($data['reload_required']), 'Integration settings that update server-rendered status rows should request an automatic rerender reload.');
};

$tests['rest_controller_roles_draft_accepts_and_returns_monospace_fields'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['monospace_role_enabled' => '1']);
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/roles/draft');
    $request->set_body_params([
        'heading' => 'Inter',
        'body' => 'Inter',
        'monospace' => '',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'serif',
        'monospace_fallback' => '',
    ]);

    $response = $services['rest']->saveRoleDraft($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'The roles/draft route should return a native REST response.');
    assertSameValue('', (string) ($data['roles']['monospace'] ?? ''), 'The roles/draft route should preserve fallback-only monospace selections.');
    assertSameValue('monospace', (string) ($data['roles']['monospace_fallback'] ?? ''), 'The roles/draft route should normalize blank monospace fallbacks back to the generic monospace stack.');
    assertContainsValue('Monospace: fallback only (monospace).', (string) ($data['role_deployment']['copy'] ?? ''), 'Role deployment payloads should include monospace copy when the feature is enabled.');
};

$tests['rest_controller_roles_draft_accepts_variable_axis_maps_when_the_feature_flag_is_enabled'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['variable_fonts_enabled' => '1']);
    $services['imports']->saveProfile(
        'Inter Variable',
        'inter-variable',
        [
            'id' => 'local-self_hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'label' => 'Self-hosted',
            'variants' => ['regular'],
            'faces' => [
                [
                    'family' => 'Inter Variable',
                    'weight' => '100..900',
                    'style' => 'normal',
                    'is_variable' => true,
                    'axes' => [
                        'WGHT' => ['min' => '100', 'default' => '400', 'max' => '900'],
                        'OPSZ' => ['min' => '8', 'default' => '14', 'max' => '32'],
                    ],
                    'files' => ['woff2' => 'inter-variable/Inter-VariableFont.woff2'],
                ],
            ],
            'meta' => [],
        ],
        'published',
        true
    );

    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/roles/draft');
    $request->set_body_params([
        'heading' => 'Inter Variable',
        'body' => 'Inter Variable',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
        'heading_axes' => ['WGHT' => '720', 'OPSZ' => '18'],
        'body_axes' => ['WGHT' => '420'],
    ]);

    $response = $services['rest']->saveRoleDraft($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'The roles/draft route should return a native REST response for variable axis payloads.');
    assertSameValue(['OPSZ' => '18', 'WGHT' => '720'], (array) ($data['roles']['heading_axes'] ?? []), 'The roles/draft route should preserve normalized heading axis values.');
    assertSameValue(['WGHT' => '420'], (array) ($data['roles']['body_axes'] ?? []), 'The roles/draft route should preserve normalized body axis values.');
};

$tests['rest_controller_roles_draft_accepts_saved_static_role_weights'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['monospace_role_enabled' => '1']);
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'static',
            'variants' => ['regular', '600'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-400.woff2'], 'paths' => []],
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '600', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-600.woff2'], 'paths' => []],
            ],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'JetBrains Mono',
        'jetbrains-mono',
        [
            'id' => 'local-self_hosted-mono',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'static',
            'variants' => ['regular', '500'],
            'faces' => [
                ['family' => 'JetBrains Mono', 'slug' => 'jetbrains-mono', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'jetbrains-mono/JetBrains Mono-400.woff2'], 'paths' => []],
                ['family' => 'JetBrains Mono', 'slug' => 'jetbrains-mono', 'source' => 'local', 'weight' => '500', 'style' => 'normal', 'files' => ['woff2' => 'jetbrains-mono/JetBrains Mono-500.woff2'], 'paths' => []],
            ],
        ],
        'published',
        true
    );

    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/roles/draft');
    $request->set_body_params([
        'heading' => 'Inter',
        'body' => 'Inter',
        'monospace' => 'JetBrains Mono',
        'heading_delivery_id' => 'local-self_hosted',
        'body_delivery_id' => 'local-self_hosted',
        'monospace_delivery_id' => 'local-self_hosted-mono',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
        'heading_weight' => '600',
        'body_weight' => '400',
        'monospace_weight' => '500',
    ]);

    $response = $services['rest']->saveRoleDraft($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'The roles/draft route should return a native REST response for static role weight payloads.');
    assertSameValue('600', (string) ($data['roles']['heading_weight'] ?? ''), 'The roles/draft route should preserve saved heading weight overrides.');
    assertSameValue('400', (string) ($data['roles']['body_weight'] ?? ''), 'The roles/draft route should preserve saved body weight overrides.');
    assertSameValue('500', (string) ($data['roles']['monospace_weight'] ?? ''), 'The roles/draft route should preserve saved monospace weight overrides.');
    assertSameValue('', (string) ($data['roles']['heading_delivery_id'] ?? ''), 'The roles/draft route should ignore legacy heading delivery overrides.');
    assertSameValue('', (string) ($data['roles']['body_delivery_id'] ?? ''), 'The roles/draft route should ignore legacy body delivery overrides.');
    assertSameValue('', (string) ($data['roles']['monospace_delivery_id'] ?? ''), 'The roles/draft route should ignore legacy monospace delivery overrides.');
};

$tests['rest_controller_roles_draft_returns_current_applied_roles_for_client_resync'] = static function (): void {
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
            'format' => 'static',
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-400.woff2'], 'paths' => []],
            ],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Raleway',
        'raleway',
        [
            'id' => 'local-self_hosted-heading',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'format' => 'static',
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Raleway', 'slug' => 'raleway', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'raleway/Raleway-400.woff2'], 'paths' => []],
            ],
        ],
        'published',
        true
    );

    $catalog = $services['catalog']->getCatalog();
    $availableFamilies = array_values($catalog);
    $services['settings']->saveAppliedRoles([
        'heading' => 'Raleway',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ], $availableFamilies);
    $services['settings']->setAutoApplyRoles(true);

    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/roles/draft');
    $request->set_body_params([
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ]);

    $response = $services['rest']->saveRoleDraft($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'The roles/draft route should return a native REST response when applied roles are active.');
    assertSameValue('Raleway', (string) ($data['applied_roles']['heading'] ?? ''), 'The roles/draft route should include the current live heading role for client-side resync.');
    assertSameValue('Inter', (string) ($data['applied_roles']['body'] ?? ''), 'The roles/draft route should include the current live body role for client-side resync.');
};

$tests['rest_controller_settings_accepts_variable_font_feature_flag'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
        'variable_fonts_enabled' => '1',
    ]);

    $response = $services['rest']->saveSettings($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'The settings autosave route should return a native REST response when saving the variable font flag.');
    assertSameValue(true, !empty($data['settings']['variable_fonts_enabled']), 'The settings autosave route should persist the variable font feature flag.');
    assertSameValue(true, !empty($data['reload_required']), 'Changing the variable font feature flag should request a reload so the admin UI can rerender the opt-in controls.');
};

$tests['rest_controller_settings_invalidates_the_cached_catalog_when_variable_font_support_changes'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter-variable/Inter-VariableFont.woff2'), 'font-data');

    assertSameValue([], array_values(array_keys($services['catalog']->getCatalog())), 'The cached catalog should start without local variable fonts while the feature flag is disabled.');

    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
        'variable_fonts_enabled' => '1',
    ]);

    $response = $services['rest']->saveSettings($request);
    $catalog = $services['catalog']->getCatalog();

    assertSameValue(true, $response instanceof WP_REST_Response, 'The settings autosave route should return a native REST response when variable support is enabled with a cached catalog already loaded.');
    assertSameValue(true, isset($catalog['Inter']), 'Enabling variable support through the REST settings route should invalidate the cached catalog so local variable fonts appear immediately.');
};

$tests['rest_controller_wraps_missing_family_errors_with_http_status'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('GET', '/' . RestController::API_NAMESPACE . '/bunny/family');
    $request->set_query_params(['family' => 'Missing Bunny Family']);

    $response = $services['rest']->fetchBunnyFamily($request);

    assertSameValue(true, is_wp_error($response), 'REST read routes should return WP_Error objects when the underlying lookup fails.');
    assertSameValue('tasty_fonts_bunny_family_not_found', $response->get_error_code(), 'REST error responses should preserve the original plugin error code.');
    assertSameValue(404, (int) (($response->get_error_data()['status'] ?? 0)), 'REST error responses should expose the HTTP status expected by the client.');
};

$tests['rest_controller_upload_route_returns_native_payloads'] = static function (): void {
    resetTestState();

    global $uploadedFilePaths;

    $services = makeServiceGraph();
    $tmpName = uniqueTestDirectory('tmp-rest-upload-valid') . '/inter-400-italic.woff2';
    mkdir(dirname($tmpName), FS_CHMOD_DIR, true);
    file_put_contents($tmpName, "wOF2test-font");
    $uploadedFilePaths[] = $tmpName;

    $request = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/local/upload');
    $request->set_param('rows', [[
        'family' => 'Inter',
        'weight' => '400',
        'style' => 'italic',
        'fallback' => 'Arial, sans-serif',
    ]]);
    $request->set_file_params([
        'files' => [
            'name' => [0 => 'inter-400-italic.woff2'],
            'type' => [0 => 'font/woff2'],
            'tmp_name' => [0 => $tmpName],
            'error' => [0 => UPLOAD_ERR_OK],
            'size' => [0 => filesize($tmpName)],
        ],
    ]);

    $response = $services['rest']->uploadLocalFonts($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'The REST upload route should return a native REST response object.');
    assertSameValue('imported', (string) ($data['rows'][0]['status'] ?? ''), 'The REST upload route should return the imported row result directly in the response body.');
    assertSameValue(1, (int) ($data['summary']['imported'] ?? 0), 'The REST upload route should return the import summary directly in the response body.');
};

$tests['google_search_cooldown_cache_is_shared_between_repeated_rest_requests'] = static function (): void {
    resetTestState();

    global $currentUserId;

    $currentUserId = 42;
    $services = makeServiceGraph();
    $services['settings']->saveSettings(['google_api_key' => 'live-key']);
    $services['settings']->saveGoogleApiKeyStatus('valid');
    set_transient(
        GoogleFontsClient::TRANSIENT_CATALOG,
        [
            'inter' => [
                'family' => 'Inter',
                'category' => 'sans-serif',
                'variants_count' => 4,
                'variants' => ['regular', '700', 'italic', '700italic'],
                'is_variable' => false,
                'axes' => [],
            ],
        ],
        HOUR_IN_SECONDS
    );

    $request = new WP_REST_Request('GET', '/' . RestController::API_NAMESPACE . '/google/search');
    $request->set_query_params(['query' => 'Inter']);
    $firstResponse = $services['rest']->searchGoogle($request);
    $firstData = $firstResponse instanceof WP_REST_Response ? $firstResponse->get_data() : [];

    set_transient(
        GoogleFontsClient::TRANSIENT_CATALOG,
        [
            'inter' => [
                'family' => 'Inter',
                'category' => 'sans-serif',
                'variants_count' => 8,
                'variants' => ['100', '200', '300', 'regular', '500', '600', '700', '800'],
                'is_variable' => false,
                'axes' => [],
            ],
        ],
        HOUR_IN_SECONDS
    );

    $secondRequest = new WP_REST_Request('GET', '/' . RestController::API_NAMESPACE . '/google/search');
    $secondRequest->set_query_params(['query' => 'Inter']);
    $secondResponse = $services['rest']->searchGoogle($secondRequest);
    $secondData = $secondResponse instanceof WP_REST_Response ? $secondResponse->get_data() : [];

    assertSameValue(4, (int) ($firstData['items'][0]['variants_count'] ?? 0), 'The first REST search response should reflect the initial cached search result.');
    assertSameValue(4, (int) ($secondData['items'][0]['variants_count'] ?? 0), 'The second REST search response should reuse the cooldown cache instead of re-reading the mutated catalog.');
};

$tests['admin_controller_family_font_display_changes_refresh_generated_assets'] = static function (): void {
    resetTestState();

    global $transientDeleted;

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
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-400.woff2'], 'paths' => ['woff2' => 'inter/Inter-400.woff2']],
            ],
        ],
        'published',
        true
    );
    $services['settings']->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        ['Inter']
    );
    $services['settings']->saveSettings([
        'class_output_enabled' => '1',
        'class_output_families_enabled' => '1',
        'minimal_output_preset_enabled' => '0',
        'output_quick_mode_preference' => 'custom',
    ]);
    $services['settings']->setAutoApplyRoles(true);

    $services['assets']->getCss();
    $transientDeleted = [];

    invokePrivateMethod($services['controller'], 'saveFamilyFontDisplaySelection', ['Inter', 'swap']);

    assertSameValue(true, in_array('tasty_fonts_css_v2', $transientDeleted, true), 'Saving a family font-display override should invalidate the cached CSS payload.');
    assertContainsValue('font-display:swap', $services['assets']->getCss(), 'Saving a family font-display override should rebuild the generated CSS with the new value.');
};

$tests['admin_controller_family_fallback_changes_refresh_generated_assets'] = static function (): void {
    resetTestState();

    global $transientDeleted;

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
            'variants' => ['regular'],
            'faces' => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'inter/Inter-400.woff2'], 'paths' => ['woff2' => 'inter/Inter-400.woff2']],
            ],
        ],
        'published',
        true
    );
    $services['settings']->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        ['Inter']
    );
    $services['settings']->setAutoApplyRoles(true);

    $services['assets']->getCss();
    $transientDeleted = [];

    $result = $services['controller']->saveFamilyFallbackValue('Inter', 'serif');

    assertSameValue(true, in_array('tasty_fonts_css_v2', $transientDeleted, true), 'Saving a family fallback should invalidate the cached CSS payload.');
    assertSameValue('"Inter", serif', (string) ($result['stack'] ?? ''), 'Saving a family fallback should return the rebuilt family stack.');
};

$tests['admin_controller_build_admin_page_url_preserves_settings_context'] = static function (): void {
    resetTestState();

    $_GET['page'] = 'tasty-custom-fonts';
    $_GET['tf_page'] = 'settings';
    $_GET['tf_studio'] = 'output-settings';

    $services = makeServiceGraph();
    $url = (string) invokePrivateMethod($services['controller'], 'buildAdminPageUrl', []);

    assertContainsValue('page=tasty-custom-fonts', $url, 'Admin redirects should always return to the canonical top-level dashboard slug.');
    assertContainsValue('tf_page=settings', $url, 'Admin redirects should preserve the current Settings page context.');
    assertContainsValue('tf_studio=output-settings', $url, 'Admin redirects should preserve the active Settings section context.');

    $_GET = [];
};

$tests['admin_controller_builds_notice_messages_from_known_keys'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();

    assertSameValue(
        'Google Fonts API key saved and validated.',
        invokePrivateMethod($controller, 'buildNoticeMessage', ['google_key_saved']),
        'Known notice keys should resolve to the translated message text.'
    );
    assertSameValue(
        'Font family deleted.',
        invokePrivateMethod($controller, 'buildNoticeMessage', ['family_deleted']),
        'Notice message lookup should cover delete messages that do not have another static translation call site.'
    );
    assertSameValue(
        '',
        invokePrivateMethod($controller, 'buildNoticeMessage', ['missing_notice_key']),
        'Unknown notice keys should return an empty string so the caller can fall back to a plain redirect.'
    );
};

$tests['admin_controller_reads_and_clears_transient_notice_toasts'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $controller = $services['controller'];
    $transientKey = invokePrivateMethod($controller, 'getPendingNoticeTransientKey');

    set_transient(
        $transientKey,
        [[
            'tone' => 'success',
            'message' => 'Plugin settings saved: preview text updated.',
            'role' => 'status',
        ]],
        300
    );

    $toasts = invokePrivateMethod($controller, 'buildNoticeToasts');

    assertSameValue('Plugin settings saved: preview text updated.', $toasts[0]['message'] ?? '', 'Settings toasts should come from the per-user transient store.');
    assertSameValue(false, get_transient($transientKey), 'Notice toasts should be cleared after they are read for rendering.');
};

$tests['admin_controller_queues_redirect_toasts_for_the_current_user'] = static function (): void {
    resetTestState();

    global $transientSet;

    $controller = makeAdminControllerTestInstance();
    $transientKey = invokePrivateMethod($controller, 'getPendingNoticeTransientKey');

    invokePrivateMethod($controller, 'queueNoticeToast', ['error', 'Variant deleted: Inter 400 italic.', 'alert']);
    $toasts = get_transient($transientKey);

    assertSameValue(true, in_array($transientKey, $transientSet, true), 'Queued redirect toasts should be written into the current user transient.');
    assertSameValue('error', (string) ($toasts[0]['tone'] ?? ''), 'Queued redirect toasts should persist the toast tone.');
    assertSameValue('Variant deleted: Inter 400 italic.', (string) ($toasts[0]['message'] ?? ''), 'Queued redirect toasts should persist the rendered message.');
    assertSameValue('alert', (string) ($toasts[0]['role'] ?? ''), 'Queued redirect toasts should persist the ARIA role.');
};

$tests['admin_controller_builds_a_clean_plugin_redirect_url'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $url = invokePrivateMethod($controller, 'buildAdminPageUrl');

    assertSameValue('https://example.test/wp-admin/admin.php?page=tasty-custom-fonts', $url, 'Redirect URLs should stay on the plugin admin page without encoding toast data in the query string.');
};

$tests['admin_controller_builds_distinct_sorted_activity_actor_options'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $actors = invokePrivateMethod(
        $controller,
        'buildActivityActorOptions',
        [[
            ['actor' => 'sathyvelukunashegaran'],
            ['actor' => 'System'],
            ['actor' => 'sathyvelukunashegaran'],
            ['actor' => 'Alicia'],
            ['actor' => ''],
        ]]
    );

    assertSameValue(
        ['Alicia', 'sathyvelukunashegaran', 'System'],
        $actors,
        'Activity actor options should be distinct, trimmed, and sorted for the filter dropdown.'
    );
};

$tests['admin_controller_preserves_only_allowed_tracked_ui_query_args_in_redirect_urls'] = static function (): void {
    resetTestState();

    $_GET = [
        'page' => AdminController::MENU_SLUG,
        'tf_advanced' => '1',
        'tf_studio' => 'preview',
        'tf_preview' => 'card',
        'tf_output' => 'names',
        'tf_add_fonts' => '1',
        'tf_source' => 'google',
        'tf_google_access' => '1',
        'tf_adobe_project' => '1',
        'invalid' => 'kept-out',
    ];

    $controller = makeAdminControllerTestInstance();
    $url = invokePrivateMethod($controller, 'buildAdminPageUrl');
    $parts = parse_url($url);
    $query = [];

    parse_str((string) ($parts['query'] ?? ''), $query);

    assertSameValue(
        [
            'page' => AdminController::MENU_SLUG,
            'tf_page' => AdminController::PAGE_LIBRARY,
            'tf_add_fonts' => '1',
            'tf_source' => 'google',
            'tf_google_access' => '1',
        ],
        $query,
        'Redirect URLs should preserve only the canonical tracked UI query args for the resolved task page.'
    );
};

$tests['admin_controller_preserves_plugin_behavior_studio_tab_in_redirect_urls'] = static function (): void {
    resetTestState();

    $_GET = [
        'page' => AdminController::MENU_SLUG,
        'tf_advanced' => '1',
        'tf_studio' => 'plugin-behavior',
    ];

    $controller = makeAdminControllerTestInstance();
    $url = invokePrivateMethod($controller, 'buildAdminPageUrl');
    $parts = parse_url($url);
    $query = [];

    parse_str((string) ($parts['query'] ?? ''), $query);

    assertSameValue(AdminController::MENU_SLUG, (string) ($query['page'] ?? ''), 'Legacy Plugin Behavior deep links should canonicalize to the single admin page.');
    assertSameValue(AdminController::PAGE_SETTINGS, (string) ($query['tf_page'] ?? ''), 'Legacy Plugin Behavior deep links should activate the Settings top-level tab.');
    assertSameValue('plugin-behavior', (string) ($query['tf_studio'] ?? ''), 'Redirect URLs should preserve the Behavior tab selection when it is active.');
};

$tests['admin_controller_preserves_integrations_studio_tab_in_redirect_urls'] = static function (): void {
    resetTestState();

    $_GET = [
        'page' => AdminController::MENU_SLUG,
        'tf_studio' => 'integrations',
    ];

    $controller = makeAdminControllerTestInstance();
    $url = invokePrivateMethod($controller, 'buildAdminPageUrl');
    $parts = parse_url($url);
    $query = [];

    parse_str((string) ($parts['query'] ?? ''), $query);

    assertSameValue(AdminController::MENU_SLUG, (string) ($query['page'] ?? ''), 'Integration deep links should canonicalize to the single admin page.');
    assertSameValue(AdminController::PAGE_SETTINGS, (string) ($query['tf_page'] ?? ''), 'Integration deep links should activate the Settings top-level tab.');
    assertSameValue('integrations', (string) ($query['tf_studio'] ?? ''), 'Redirect URLs should preserve the Integrations tab selection when it is active.');
};

$tests['admin_controller_preserves_developer_studio_tab_in_redirect_urls'] = static function (): void {
    resetTestState();

    $_GET = [
        'page' => AdminController::MENU_SLUG,
        'tf_studio' => 'developer',
    ];

    $controller = makeAdminControllerTestInstance();
    $url = invokePrivateMethod($controller, 'buildAdminPageUrl');
    $parts = parse_url($url);
    $query = [];

    parse_str((string) ($parts['query'] ?? ''), $query);

    assertSameValue(AdminController::MENU_SLUG, (string) ($query['page'] ?? ''), 'Developer deep links should canonicalize to the single admin page.');
    assertSameValue(AdminController::PAGE_SETTINGS, (string) ($query['tf_page'] ?? ''), 'Developer deep links should activate the Settings top-level tab.');
    assertSameValue('developer', (string) ($query['tf_studio'] ?? ''), 'Redirect URLs should preserve the Developer tab selection when it is active.');
};

$tests['admin_controller_maps_legacy_diagnostics_tabs_to_the_diagnostics_page'] = static function (): void {
    resetTestState();

    $_GET = [
        'page' => AdminController::MENU_SLUG,
        'tf_advanced' => '1',
        'tf_studio' => 'generated',
    ];

    $controller = makeAdminControllerTestInstance();
    $url = invokePrivateMethod($controller, 'buildAdminPageUrl');
    $parts = parse_url($url);
    $query = [];

    parse_str((string) ($parts['query'] ?? ''), $query);

    assertSameValue(AdminController::MENU_SLUG, (string) ($query['page'] ?? ''), 'Legacy diagnostics tabs should canonicalize to the single admin page.');
    assertSameValue(AdminController::PAGE_DIAGNOSTICS, (string) ($query['tf_page'] ?? ''), 'Legacy diagnostics tabs should activate the Diagnostics top-level tab.');
    assertSameValue('generated', (string) ($query['tf_studio'] ?? ''), 'Redirect URLs should preserve the requested diagnostics tab.');
};

$tests['admin_controller_maps_snippets_disclosure_state_to_the_roles_page'] = static function (): void {
    resetTestState();

    $_GET = [
        'page' => AdminController::MENU_SLUG,
        'tf_advanced' => '1',
        'tf_studio' => 'snippets',
        'tf_output' => 'variables',
    ];

    $controller = makeAdminControllerTestInstance();
    $url = invokePrivateMethod($controller, 'buildAdminPageUrl');
    $parts = parse_url($url);
    $query = [];

    parse_str((string) ($parts['query'] ?? ''), $query);

    assertSameValue(AdminController::MENU_SLUG, (string) ($query['page'] ?? ''), 'Snippets deep links should canonicalize to the single admin page.');
    assertSameValue(false, isset($query['tf_page']), 'Snippets deep links should use the default Deploy Fonts page without emitting an extra tf_page query arg.');
    assertSameValue('snippets', (string) ($query['tf_studio'] ?? ''), 'Redirect URLs should preserve the snippets disclosure state.');
    assertSameValue('variables', (string) ($query['tf_output'] ?? ''), 'Redirect URLs should preserve the active snippets tab.');
};

$tests['admin_controller_preserves_code_preview_tab_in_redirect_urls'] = static function (): void {
    resetTestState();

    $_GET = [
        'page' => AdminController::MENU_SLUG,
        'tf_advanced' => '1',
        'tf_studio' => 'preview',
        'tf_preview' => 'code',
    ];

    $controller = makeAdminControllerTestInstance();
    $url = invokePrivateMethod($controller, 'buildAdminPageUrl');
    $parts = parse_url($url);
    $query = [];

    parse_str((string) ($parts['query'] ?? ''), $query);

    assertSameValue('code', (string) ($query['tf_preview'] ?? ''), 'Redirect URLs should preserve the Code preview tab selection when it is active.');
};

$tests['admin_controller_persists_local_environment_notice_preferences_per_user'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();

    invokePrivateMethod(
        $services['controller'],
        'saveLocalEnvironmentNoticePreference',
        [[
            'hidden_until' => 123456,
            'dismissed_forever' => false,
        ]]
    );

    assertSameValue(
        [
            1 => [
                'hidden_until' => 123456,
                'dismissed_forever' => false,
            ],
        ],
        $optionStore['tasty_fonts_local_environment_notice_preferences'] ?? null,
        'Local environment reminder preferences should be stored per user in a dedicated option.'
    );
};

$tests['admin_controller_hides_local_environment_notice_when_snoozed_or_dismissed'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $settings = $services['settings']->getSettings();

    invokePrivateMethod(
        $services['controller'],
        'saveLocalEnvironmentNoticePreference',
        [[
            'hidden_until' => time() + DAY_IN_SECONDS,
            'dismissed_forever' => false,
        ]]
    );

    assertSameValue(
        [],
        invokePrivateMethod($services['controller'], 'buildLocalEnvironmentNotice', [$settings]),
        'Snoozed local-environment reminders should stay hidden until the snooze window expires.'
    );

    invokePrivateMethod(
        $services['controller'],
        'saveLocalEnvironmentNoticePreference',
        [[
            'hidden_until' => 0,
            'dismissed_forever' => true,
        ]]
    );

    assertSameValue(
        [],
        invokePrivateMethod($services['controller'], 'buildLocalEnvironmentNotice', [$settings]),
        'Permanently dismissed local-environment reminders should stay hidden for that account.'
    );
};

$tests['admin_controller_builds_local_environment_notice_again_when_snooze_expires'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();

    invokePrivateMethod(
        $services['controller'],
        'saveLocalEnvironmentNoticePreference',
        [[
            'hidden_until' => time() - 60,
            'dismissed_forever' => false,
        ]]
    );

    $notice = invokePrivateMethod($services['controller'], 'buildLocalEnvironmentNotice', [$services['settings']->getSettings()]);

    assertSameValue('Local environment detected', (string) ($notice['title'] ?? ''), 'Expired snoozes should allow the local-environment reminder to appear again.');
    assertSameValue('Open Integrations', (string) ($notice['settings_label'] ?? ''), 'The rebuilt reminder should offer the Integrations deep link.');
    assertContainsValue('page=' . AdminController::MENU_SLUG, (string) ($notice['settings_url'] ?? ''), 'The reminder deep link should point to the unified admin page.');
    assertContainsValue('tf_page=' . AdminController::PAGE_SETTINGS, (string) ($notice['settings_url'] ?? ''), 'The reminder deep link should activate the Settings tab.');
    assertContainsValue('tf_studio=integrations', (string) ($notice['settings_url'] ?? ''), 'The reminder deep link should activate the Integrations panel.');
};

$tests['admin_controller_clears_plugin_caches_and_logs_the_reset'] = static function (): void {
    resetTestState();

    global $transientStore;

    $services = makeServiceGraph();
    $transientStore = [
        'tasty_fonts_catalog_v2' => ['cached'],
        'tasty_fonts_css_v2' => 'cached',
        'tasty_fonts_css_hash_v2' => 'hash',
        GoogleFontsClient::TRANSIENT_CATALOG => ['google'],
        GoogleFontsClient::TRANSIENT_METADATA => ['google' => ['family' => 'Google', 'axes' => []]],
        'tasty_fonts_bunny_catalog_v1' => ['bunny'],
    ];

    set_transient(AdminController::SEARCH_CACHE_TRANSIENT_PREFIX . 'google_inter', ['Inter'], 300);
    set_transient(AdminController::SEARCH_COOLDOWN_TRANSIENT_PREFIX . 'google_inter', 1, 1);

    $result = $services['controller']->clearPluginCachesAndRegenerateAssets();

    assertSameValue(
        'Plugin caches cleared and generated assets refreshed.',
        (string) ($result['message'] ?? ''),
        'Cache reset should return a success message.'
    );
    assertSameValue(
        'Plugin caches cleared and generated assets refreshed.',
        (string) ($services['log']->all()[0]['message'] ?? ''),
        'Cache reset should append an audit log entry.'
    );
};

$tests['admin_controller_resets_suppressed_notices_and_logs_the_action'] = static function (): void {
    resetTestState();

    global $optionStore;

    $services = makeServiceGraph();
    $optionStore[AdminController::LOCAL_ENV_NOTICE_OPTION] = [
        1 => ['hidden_until' => 123456, 'dismissed_forever' => true],
        2 => ['hidden_until' => 789012, 'dismissed_forever' => false],
    ];

    $result = $services['controller']->resetSuppressedNotices();

    assertSameValue(
        'Suppressed notices reset. Hidden reminders can appear again.',
        (string) ($result['message'] ?? ''),
        'Reset suppressed notices should return a success message.'
    );
    assertSameValue(
        false,
        array_key_exists(AdminController::LOCAL_ENV_NOTICE_OPTION, $optionStore),
        'Reset suppressed notices should clear the stored notice preferences.'
    );
    assertSameValue(
        'Suppressed notices reset. Hidden reminders can appear again.',
        (string) ($services['log']->all()[0]['message'] ?? ''),
        'Reset suppressed notices should append an audit log entry.'
    );
};

$tests['admin_controller_resolves_sitewide_toggle_submissions_into_role_actions'] = static function (): void {
    resetTestState();

    $_POST['tasty_fonts_sitewide_enabled'] = '1';
    $controller = makeAdminControllerTestInstance();

    assertSameValue(
        'apply',
        invokePrivateMethod($controller, 'resolveRoleFormActionType', ['save', false]),
        'Turning the sitewide toggle on should resolve a roles form submission into an apply action.'
    );

    $_POST['tasty_fonts_sitewide_enabled'] = '0';

    assertSameValue(
        'disable',
        invokePrivateMethod($controller, 'resolveRoleFormActionType', ['save', true]),
        'Turning the sitewide toggle off should resolve a roles form submission into a disable action.'
    );

    $_POST['tasty_fonts_sitewide_enabled'] = '1';

    assertSameValue(
        'save',
        invokePrivateMethod($controller, 'resolveRoleFormActionType', ['save', true]),
        'Leaving the toggle on should keep draft saves as save-only submissions when sitewide delivery is already enabled.'
    );
};

// ---------------------------------------------------------------------------
// AdminController::handleAdminActions() – gate logic
// ---------------------------------------------------------------------------

$tests['handle_admin_actions_is_a_no_op_for_non_admin_requests'] = static function (): void {
    resetTestState();

    global $isAdminRequest;
    global $redirectLocation;

    $isAdminRequest = false;
    $_POST['tasty_fonts_clear_log'] = '1';

    $services = makeServiceGraph();
    $services['controller']->handleAdminActions();

    assertSameValue('', $redirectLocation, 'handleAdminActions() should do nothing when is_admin() returns false.');
};

$tests['handle_admin_actions_is_a_no_op_when_user_lacks_manage_options'] = static function (): void {
    resetTestState();

    global $currentUserCapabilities;
    global $isAdminRequest;
    global $redirectLocation;

    $isAdminRequest = true;
    $currentUserCapabilities = ['manage_options' => false];
    $_POST['tasty_fonts_clear_log'] = '1';

    $services = makeServiceGraph();
    $services['controller']->handleAdminActions();

    assertSameValue('', $redirectLocation, 'handleAdminActions() should do nothing when the current user lacks manage_options.');
};

$tests['handle_admin_actions_dispatches_clear_log_and_redirects'] = static function (): void {
    resetTestState();

    global $isAdminRequest;
    global $redirectLocation;

    $isAdminRequest = true;
    $_POST['tasty_fonts_clear_log'] = '1';

    $services = makeServiceGraph();
    $services['log']->add('Entry before clear');

    try {
        $services['controller']->handleAdminActions();
    } catch (WpDieException $e) {
        // Expected: the redirect handler terminates via wp_die() after wp_safe_redirect().
    }

    $logMessages = implode(' ', array_column($services['log']->all(), 'message'));
    assertNotContainsValue('Entry before clear', $logMessages, 'handleAdminActions() should have cleared the old log entries when the clear-log field is posted.');
    assertContainsValue('cleared', $logMessages, 'handleAdminActions() should add a "cleared" confirmation log entry after clearing.');
    assertSameValue(false, empty($redirectLocation), 'handleAdminActions() should redirect after clearing the log.');
};

$tests['handle_admin_actions_returns_early_after_first_matching_handler'] = static function (): void {
    resetTestState();

    global $isAdminRequest;
    global $redirectLocation;

    $isAdminRequest = true;
    // Post both the clear-log and rescan fields; only clear-log should fire.
    $_POST['tasty_fonts_clear_log'] = '1';
    $_POST['tasty_fonts_rescan_fonts'] = '1';

    $services = makeServiceGraph();

    try {
        $services['controller']->handleAdminActions();
    } catch (WpDieException $e) {
        // Expected: the redirect handler terminates via wp_die() after wp_safe_redirect().
    }

    // The rescan handler would add a "Fonts rescanned." log entry; the clear-log handler would not.
    // If the dispatch stops after clear-log, only the "Activity log cleared." entry should be present.
    $logMessages = implode(' ', array_column($services['log']->all(), 'message'));
    assertNotContainsValue('rescanned', $logMessages, 'Only the clear-log handler should have fired; the rescan log message should be absent.');
};
