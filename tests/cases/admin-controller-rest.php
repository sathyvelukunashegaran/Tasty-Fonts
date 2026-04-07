<?php

declare(strict_types=1);

use TastyFonts\Admin\AdminController;
use TastyFonts\Api\RestController;
use TastyFonts\Plugin;

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
    assertContainsValue('class output enabled', $message, 'Settings save messages should explain class-output enablement changes.');
    assertContainsValue('class output settings updated', $message, 'Settings save messages should explain granular class-output changes.');
    assertContainsValue('CSS minification disabled', $message, 'Settings save messages should explain CSS minification changes.');
    assertContainsValue('extended font output variables disabled', $message, 'Settings save messages should explain extended font output changes.');
    assertContainsValue('extended variable subsettings updated', $message, 'Settings save messages should explain granular extended-variable changes.');
    assertContainsValue('primary font preloads enabled', $message, 'Settings save messages should explain preload setting changes.');
    assertContainsValue('Block Editor Font Library sync enabled', $message, 'Settings save messages should explain editor sync changes.');
    assertContainsValue('onboarding hints hidden', $message, 'Settings save messages should explain plugin behavior changes.');
    assertContainsValue('preview text updated', $message, 'Settings save messages should explain preview text changes.');
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

$tests['admin_controller_versions_admin_assets_from_plugin_version'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $version = invokePrivateMethod($controller, 'assetVersionFor');

    assertSameValue(TASTY_FONTS_VERSION, $version, 'Admin asset versioning should reuse the plugin version instead of hashing shipped files on every request.');
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
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $services['settings']->saveRoles($roles, ['Inter']);
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
    $settings = $services['settings']->saveSettings(['monospace_role_enabled' => '1', 'minify_css_output' => '0']);
    $panels = invokePrivateMethod(
        $services['controller'],
        'buildOutputPanels',
        [$roles, $settings, $services['catalog']->getCatalog()]
    );
    $panelValues = [];

    foreach ($panels as $panel) {
        $panelValues[(string) ($panel['key'] ?? '')] = (string) ($panel['value'] ?? '');
    }

    assertContainsValue('--font-monospace: monospace;', $panelValues['variables'] ?? '', 'Enabled monospace support should add the monospace variable to the CSS Variables panel.');
    assertContainsValue('code, pre {', $panelValues['usage'] ?? '', 'Enabled monospace support should add the code/pre usage rule to the Site Snippet panel.');
    assertContainsValue('Class output is off', $panelValues['classes'] ?? '', 'The Font Classes panel should explain when the workflow is disabled.');
    assertContainsValue("monospace\n", ($panelValues['stacks'] ?? '') . "\n", 'Enabled monospace support should include the fallback-only monospace stack in the Font Stacks panel.');
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

    foreach ($panels as $panel) {
        $panelValues[(string) ($panel['key'] ?? '')] = (string) ($panel['value'] ?? '');
    }

    assertContainsValue('"Live Heading"', $panelValues['usage'] ?? '', 'Sitewide-on snippet output should use applied roles for the site snippet.');
    assertContainsValue('"Live Heading"', $panelValues['classes'] ?? '', 'Sitewide-on snippet output should use applied roles for the font classes panel.');
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

$tests['admin_controller_exposes_generated_css_as_a_top_level_panel'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400.woff2'), 'font-data');
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $services['settings']->saveRoles($roles, ['Inter']);
    $services['settings']->setAutoApplyRoles(true);

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
    }
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
        'tasty_fonts_google_catalog_v1',
        [
            'inter' => [
                'family' => 'Inter',
                'category' => 'sans-serif',
                'variants_count' => 4,
            ],
        ],
        HOUR_IN_SECONDS
    );

    $request = new WP_REST_Request('GET', '/' . RestController::API_NAMESPACE . '/google/search');
    $request->set_query_params(['query' => 'Inter']);
    $firstResponse = $services['rest']->searchGoogle($request);
    $firstData = $firstResponse instanceof WP_REST_Response ? $firstResponse->get_data() : [];

    set_transient(
        'tasty_fonts_google_catalog_v1',
        [
            'inter' => [
                'family' => 'Inter',
                'category' => 'sans-serif',
                'variants_count' => 8,
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

    invokePrivateMethod($services['controller'], 'saveFamilyFontDisplaySelection', ['Inter', 'swap']);

    assertSameValue(true, in_array('tasty_fonts_css_v2', $transientDeleted, true), 'Saving a family font-display override should invalidate the cached CSS payload.');
    assertContainsValue('font-display:swap', $services['assets']->getCss(), 'Saving a family font-display override should rebuild the generated CSS with the new value.');
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
            'tf_advanced' => '1',
            'tf_studio' => 'preview',
            'tf_preview' => 'card',
            'tf_add_fonts' => '1',
            'tf_source' => 'google',
            'tf_google_access' => '1',
        ],
        $query,
        'Redirect URLs should preserve only the canonical tracked UI query args for the current admin view.'
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

    assertSameValue('plugin-behavior', (string) ($query['tf_studio'] ?? ''), 'Redirect URLs should preserve the Plugin Behavior tab selection when it is active.');
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
    assertSameValue('Open Plugin Behavior', (string) ($notice['settings_label'] ?? ''), 'The rebuilt reminder should still offer the Plugin Behavior deep link.');
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
