<?php

declare(strict_types=1);

use TastyFonts\Admin\AdminController;
use TastyFonts\Admin\SettingsSaveFields;
use TastyFonts\Api\RestController;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Cli\Command as CliCommand;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Plugin;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\TransientKey;

if (!function_exists('tastyFontsCustomCssSnapshotTransientKey')) {
    function tastyFontsCustomCssSnapshotTransientKey(): string
    {
        global $transientStore;

        foreach (array_keys($transientStore) as $key) {
            if (is_string($key) && str_contains($key, 'tasty_fonts_custom_css_snapshot_')) {
                return $key;
            }
        }

        return '';
    }
}

if (!function_exists('tastyFontsEnableCustomCssUrlImports')) {
    function tastyFontsEnableCustomCssUrlImports(array $services): void
    {
        $services['settings']->saveSettings(['custom_css_url_imports_enabled' => '1']);
    }
}

if (!function_exists('tastyFontsRunCustomCssDryRunRestFixture')) {
    function tastyFontsRunCustomCssDryRunRestFixture(array $services): array
    {
        tastyFontsEnableCustomCssUrlImports($services);

        global $remoteGetResponses;

        $cssUrl = 'https://assets.example.com/snapshot.css';
        $fontUrl = 'https://cdn.example.com/snapshot-sans.woff2';
        $remoteGetResponses[$cssUrl] = [
            'response' => ['code' => 200],
            'headers' => ['content-type' => 'text/css'],
            'body' => <<<'CSS'
@font-face {
    font-family: "Snapshot Sans";
    font-style: normal;
    font-weight: 400;
    src: url("https://cdn.example.com/snapshot-sans.woff2") format("woff2");
    unicode-range: U+000-5FF;
}
CSS,
        ];
        tastyFontsMockCustomCssFont($fontUrl);

        $request = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/dry-run');
        $request->set_body_params(['url' => $cssUrl]);
        $response = $services['rest']->dryRunCustomCssUrl($request);
        $data = $response instanceof WP_REST_Response ? $response->get_data() : [];

        if (!$response instanceof WP_REST_Response || !is_array($data)) {
            throw new RuntimeException('Expected dry-run fixture to return a REST response.');
        }

        return $data;
    }
}

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

$tests['rest_controller_route_reference_builds_full_api_paths_and_ignores_unknown_keys'] = static function (): void {
    assertSameValue(
        '/tasty-fonts/v1/settings',
        RestController::routeReference('saveSettings'),
        'Route references should include the REST namespace and the mapped route path for known route keys.'
    );
    assertSameValue(
        '/tasty-fonts/v1/',
        RestController::routeReference('missing'),
        'Unknown route keys should fall back to the API namespace root instead of producing malformed paths.'
    );
    assertSameValue(
        '/tasty-fonts/v1/families/card',
        RestController::routeReference('familyCard'),
        'Family card route references should include the REST namespace and lazy fragment path.'
    );
    assertSameValue(
        '/tasty-fonts/v1/transfer/validate',
        RestController::routeReference('validateSiteTransfer'),
        'Site transfer validation should expose a dedicated REST route reference.'
    );
    assertSameValue(
        '/tasty-fonts/v1/custom-css/dry-run',
        RestController::routeReference('customCssDryRun'),
        'Custom CSS URL dry runs should expose a dedicated REST route reference.'
    );
    assertSameValue(
        '/tasty-fonts/v1/custom-css/import',
        RestController::routeReference('customCssImport'),
        'Custom CSS final imports should expose a dedicated REST route reference.'
    );
};

$tests['rest_controller_settings_args_stay_in_sync_with_shared_settings_save_field_definitions'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $args = invokePrivateMethod($services['rest'], 'settingsArgs', []);

    assertSameValue(
        SettingsSaveFields::names(),
        array_keys($args),
        'REST settings args should stay in lockstep with the shared settings save field definitions.'
    );
};

$tests['rest_controller_sanitizes_text_toggle_string_and_nested_array_args'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $rest = $services['rest'];
    $invalidLeaf = new stdClass();

    assertSameValue('Inter (1)', $rest->sanitizeTextArg("  Inter <alert>(1)\n"), 'Text args should be normalized through sanitize_text_field semantics.');
    assertSameValue('', $rest->sanitizeTextArg(['nope']), 'Text args should collapse non-scalar payloads to an empty string.');

    assertSameValue('1', $rest->sanitizeToggleArg('yes'), 'Truthy toggle values should normalize to 1.');
    assertSameValue('0', $rest->sanitizeToggleArg(''), 'Empty toggle values should normalize to 0.');
    assertSameValue('0', $rest->sanitizeToggleArg(['bad']), 'Non-scalar toggle values should normalize to 0.');

    assertSameValue(
        ['regular', '700italic', '0'],
        $rest->sanitizeStringArrayArg([' regular ', '', '700italic', ['bad'], 0]),
        'String array args should keep only sanitized scalar items in order.'
    );

    assertSameValue(
        [2, 7],
        $rest->sanitizeIntegerArrayArg(['2', '0', ['bad'], null, ' 7 ']),
        'Integer array args should keep only positive scalar items in order.'
    );

    assertSameValue(
        [
            'family' => 'Inter',
            'axes' => [
                'WGHT' => '700',
                'deep' => [
                    'ITAL' => '1',
                ],
            ],
            3 => 'keep-int-key',
        ],
        $rest->sanitizeNestedArrayArg([
            'family' => "Inter <script>",
            'axes' => [
                'WGHT' => '700',
                'deep' => [
                    'ITAL' => '1',
                    'skip' => $invalidLeaf,
                ],
            ],
            3 => 'keep-int-key',
            'drop' => $invalidLeaf,
        ]),
        'Nested array args should sanitize string keys and values recursively while skipping non-scalar leaf values.'
    );
};

$tests['rest_controller_validates_text_toggle_string_and_nested_array_args'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $rest = $services['rest'];
    $invalidLeaf = new stdClass();

    assertTrueValue(
        (bool) invokePrivateMethod($rest, 'validateTextArg', [' SWAP ', false, ['swap', 'optional']]),
        'Text arg validation should accept allowed values case-insensitively after sanitization.'
    );
    assertFalseValue(
        (bool) invokePrivateMethod($rest, 'validateTextArg', ['invalid', false, ['swap', 'optional']]),
        'Text arg validation should reject values outside the allowlist.'
    );
    assertFalseValue(
        (bool) invokePrivateMethod($rest, 'validateTextArg', [null, true, null]),
        'Required text args should reject null payloads.'
    );
    assertFalseValue(
        (bool) invokePrivateMethod($rest, 'validateTextArg', [['bad'], false, null]),
        'Text arg validation should reject non-scalar payloads.'
    );

    assertTrueValue($rest->validateToggleArg('0'), 'Toggle arg validation should accept scalar values.');
    assertTrueValue($rest->validateToggleArg(null), 'Toggle arg validation should accept null values.');
    assertFalseValue($rest->validateToggleArg(['bad']), 'Toggle arg validation should reject non-scalar payloads.');

    assertTrueValue(
        $rest->validateStringArrayArg(['regular', 700, null]),
        'String array validation should accept scalar and null array items.'
    );
    assertFalseValue(
        $rest->validateStringArrayArg([['bad']]),
        'String array validation should reject nested non-scalar items.'
    );
    assertFalseValue(
        $rest->validateStringArrayArg('regular', true),
        'Required string array validation should reject non-array payloads.'
    );

    assertTrueValue(
        $rest->validateIntegerArrayArg(['2', 7, null]),
        'Integer array validation should accept scalar and null array items.'
    );
    assertFalseValue(
        $rest->validateIntegerArrayArg([['bad']]),
        'Integer array validation should reject nested non-scalar items.'
    );
    assertFalseValue(
        $rest->validateIntegerArrayArg('2', true),
        'Required integer array validation should reject non-array payloads.'
    );

    assertTrueValue(
        $rest->validateNestedArrayArg(['family' => 'Inter', 'axes' => ['WGHT' => '700', 'ITAL' => null]]),
        'Nested array validation should accept recursive scalar structures.'
    );
    assertFalseValue(
        $rest->validateNestedArrayArg(['axes' => [['bad' => ['deeper' => $invalidLeaf]]]]),
        'Nested array validation should reject nested non-scalar leaf values.'
    );
    assertFalseValue(
        $rest->validateNestedArrayArg('invalid', true),
        'Required nested array validation should reject non-array payloads.'
    );
};

$tests['rest_controller_role_draft_input_keeps_only_present_supported_fields'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/roles/draft');
    $request->set_body_params([
        'heading' => 'Inter',
        'body' => 'Lora',
        'heading_weight' => '700',
        'heading_axes' => ['WGHT' => '700', 'ITAL' => '1'],
        'body_axes' => 'invalid',
        'ignored_field' => 'should-not-pass',
    ]);

    $input = invokePrivateMethod($services['rest'], 'getRoleDraftInput', [$request]);

    assertSameValue(
        [
            'heading' => 'Inter',
            'body' => 'Lora',
            'heading_weight' => '700',
            'heading_axes' => ['WGHT' => '700', 'ITAL' => '1'],
        ],
        $input,
        'Role draft requests should keep only supported submitted fields and preserve axis maps only when they are arrays.'
    );
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

$tests['admin_controller_collects_only_allowlisted_posted_settings_fields'] = static function (): void {
    resetTestState();

    $_POST = [
        'google_api_key' => " api-key\\with-slash ",
        'preload_primary_fonts' => '1',
        'show_activity_log' => '1',
        'monospace_role_enabled' => '0',
        'admin_access_custom_enabled' => '1',
        'admin_access_role_slugs' => ['editor', '', ['nested']],
        'admin_access_user_ids' => ['2', '0', ['nested'], 3],
        'unexpected_setting' => 'should-not-pass',
        'nested_payload' => ['nope'],
    ];

    $controller = makeAdminControllerTestInstance();
    $submitted = invokePrivateMethod($controller, 'collectPostedSettingsSubmission');

    assertSameValue(
        [
            'google_api_key' => ' api-key\\with-slash ',
            'preload_primary_fonts' => '1',
            'show_activity_log' => '1',
            'monospace_role_enabled' => '0',
            'admin_access_custom_enabled' => '1',
            'admin_access_role_slugs' => ['editor', ''],
            'admin_access_user_ids' => ['2', '0', 3],
        ],
        $submitted,
        'Classic settings saves should only forward explicitly allowlisted fields from $_POST, including array-valued admin access settings.'
    );
};

$tests['admin_access_service_allows_administrators_roles_and_individual_users'] = static function (): void {
    resetTestState();

    global $currentUserId;

    $services = makeServiceGraph();

    assertTrueValue($services['admin_access']->canCurrentUserAccess(), 'Administrators should retain Tasty Fonts admin access by default.');

    $currentUserId = 2;
    assertFalseValue($services['admin_access']->canCurrentUserAccess(), 'Non-admin users should be denied by default when they are not explicitly granted access.');

    $services['settings']->saveSettings([
        'admin_access_custom_enabled' => true,
        'admin_access_role_slugs' => ['editor'],
    ]);
    assertTrueValue($services['admin_access']->canCurrentUserAccess(), 'Users in an explicitly granted role should gain access when custom access is enabled.');

    $currentUserId = 3;
    $services['settings']->saveSettings([
        'admin_access_custom_enabled' => true,
        'admin_access_role_slugs' => [],
        'admin_access_user_ids' => [3],
    ]);
    assertTrueValue($services['admin_access']->canCurrentUserAccess(), 'Explicitly granted individual users should gain access even when their role is not allowlisted.');
};

$tests['rest_controller_permissions_follow_the_shared_admin_access_policy'] = static function (): void {
    resetTestState();

    global $currentUserId;

    $services = makeServiceGraph();

    assertTrueValue($services['rest']->canManageOptions(), 'REST permissions should allow administrators by default.');

    $currentUserId = 2;
    assertFalseValue($services['rest']->canManageOptions(), 'REST permissions should deny unlisted non-admin users.');

    $services['settings']->saveSettings([
        'admin_access_custom_enabled' => true,
        'admin_access_role_slugs' => ['editor'],
    ]);
    assertTrueValue($services['rest']->canManageOptions(), 'REST permissions should allow explicitly granted roles when custom access is enabled.');

    $currentUserId = 3;
    $services['settings']->saveSettings([
        'admin_access_custom_enabled' => true,
        'admin_access_role_slugs' => [],
        'admin_access_user_ids' => [3],
    ]);
    assertTrueValue($services['rest']->canManageOptions(), 'REST permissions should allow explicitly granted users.');
};

$tests['admin_controller_register_menu_uses_the_shared_access_policy_and_read_capability'] = static function (): void {
    resetTestState();

    global $currentUserId;
    global $menuPageCalls;
    global $submenuPageCalls;

    $currentUserId = 2;
    $services = makeServiceGraph();
    $services['controller']->registerMenu();

    assertSameValue([], $menuPageCalls, 'Disallowed users should not receive registered Tasty Fonts menu pages.');
    assertSameValue([], $submenuPageCalls, 'Disallowed users should not receive registered Tasty Fonts submenu pages.');

    $services['settings']->saveSettings([
        'admin_access_custom_enabled' => true,
        'admin_access_role_slugs' => ['editor'],
    ]);
    $services['controller']->registerMenu();

    assertSameValue('read', (string) ($menuPageCalls[0]['capability'] ?? ''), 'Allowed users should receive menu pages registered with the shared read capability.');
    assertSameValue([], $submenuPageCalls, 'Allowed users should not receive retired hidden submenu routes.');
};

$tests['admin_controller_enqueue_assets_skips_unauthorized_plugin_requests'] = static function (): void {
    resetTestState();

    global $currentUserId;
    global $enqueuedScripts;
    global $enqueuedStyles;

    $currentUserId = 2;
    $services = makeServiceGraph();
    $services['controller']->enqueueAssets('toplevel_page_' . AdminController::MENU_SLUG);

    assertSameValue([], $enqueuedStyles, 'Unauthorized plugin page requests should not enqueue admin styles.');
    assertSameValue([], $enqueuedScripts, 'Unauthorized plugin page requests should not enqueue admin scripts.');
};

$tests['admin_controller_render_page_denies_direct_access_for_disallowed_users'] = static function (): void {
    resetTestState();

    global $currentUserId;

    $currentUserId = 2;
    $services = makeServiceGraph();

    try {
        $services['controller']->renderPage();
        throw new RuntimeException('Expected renderPage() to deny access.');
    } catch (WpDieException $e) {
        assertContainsValue('You do not have permission to access Tasty Fonts.', $e->getMessage(), 'Direct admin page access should be denied with a plugin-specific permission message.');
    }
};

$tests['admin_controller_renders_lazy_family_card_fragments_for_known_slugs'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'weight' => '400',
                    'style' => 'normal',
                    'source' => 'local',
                    'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                    'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                ],
            ],
        ],
        'published',
        true
    );

    $result = $services['controller']->renderFamilyCardFragment('inter');

    assertSameValue(false, is_wp_error($result), 'Known family slugs should render a lazy family-card fragment.');
    assertSameValue('inter', (string) ($result['family_slug'] ?? ''), 'The fragment response should echo the normalized family slug.');
    assertContainsValue('Delivery Profiles', (string) ($result['html'] ?? ''), 'Lazy family-card fragments should include the heavy delivery details markup.');
    assertContainsValue('Font Faces', (string) ($result['html'] ?? ''), 'Lazy family-card fragments should include the face detail section.');
};

$tests['admin_controller_returns_a_not_found_error_for_unknown_family_card_fragments'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $result = $services['controller']->renderFamilyCardFragment('missing-family');

    assertWpErrorCode('tasty_fonts_family_not_found', $result, 'Unknown family slugs should return the shared not-found error for lazy family-card fragments.');
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
                'show_activity_log' => false,
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
                'show_activity_log' => true,
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
    assertContainsValue('activity log shown', $message, 'Settings save messages should explain activity log visibility changes.');
    assertContainsValue('onboarding hints turned off', $message, 'Settings save messages should explain plugin behavior changes with the positive setting label.');
    assertContainsValue('preview text updated', $message, 'Settings save messages should explain preview text changes.');
    assertContainsValue('Reload the page to apply this change.', $message, 'Settings save messages should mention reload-only behavior changes.');
};

$tests['admin_controller_exposes_all_font_display_options_with_swap_first'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $options = invokePrivateMethod($controller, 'buildFontDisplayOptions', []);

    assertSameValue('swap', (string) ($options[0]['value'] ?? ''), 'Swap should be the first font-display choice so the recommended default is selected first.');
    assertSameValue(
        ['swap', 'fallback', 'block', 'auto', 'optional'],
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
        ['inherit', 'swap', 'fallback', 'block', 'auto', 'optional'],
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

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresAssetRefresh',
            [
                ['class_output_enabled' => true, 'class_output_role_styles_enabled' => false, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
                ['class_output_enabled' => true, 'class_output_role_styles_enabled' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
            ]
        ),
        'Toggling role class styles should trigger a generated CSS refresh because emitted class CSS changes.'
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
                ['show_activity_log' => false],
                ['show_activity_log' => true],
            ]
        ),
        'Toggling the activity log should require a page reload because the diagnostics section is server-rendered.'
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

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresReload',
            [
                ['admin_access_role_slugs' => [], 'admin_access_user_ids' => []],
                ['admin_access_role_slugs' => ['editor'], 'admin_access_user_ids' => []],
            ]
        ),
        'Changing the admin access role allowlist should require a page reload because menu and page access are evaluated on page load.'
    );

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresReload',
            [
                ['admin_access_role_slugs' => [], 'admin_access_user_ids' => []],
                ['admin_access_role_slugs' => [], 'admin_access_user_ids' => [3]],
            ]
        ),
        'Changing the admin access user allowlist should require a page reload because menu and page access are evaluated on page load.'
    );

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresReload',
            [
                ['admin_access_custom_enabled' => false, 'admin_access_role_slugs' => [], 'admin_access_user_ids' => []],
                ['admin_access_custom_enabled' => true, 'admin_access_role_slugs' => [], 'admin_access_user_ids' => []],
            ]
        ),
        'Turning custom admin access on or off should require a page reload because menu and page access are evaluated on page load.'
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
        'class_output_role_styles_enabled' => '1',
    ]);

    $context = invokePrivateMethod($services['controller'], 'buildPageContext', []);

    assertSameValue(true, !empty($context['class_output_enabled']), 'Page context should expose the class output master toggle.');
    assertSameValue(false, !empty($context['class_output_role_heading_enabled']), 'Page context should expose granular role class toggles.');
    assertSameValue(false, !empty($context['class_output_category_serif_enabled']), 'Page context should expose granular category class toggles.');
    assertSameValue(true, !empty($context['class_output_families_enabled']), 'Page context should expose the family class toggle.');
    assertSameValue(true, !empty($context['class_output_role_styles_enabled']), 'Page context should expose the role class style toggle.');
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

$tests['admin_controller_exposes_admin_access_settings_in_page_context'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'admin_access_custom_enabled' => true,
        'admin_access_role_slugs' => ['editor'],
        'admin_access_user_ids' => [3],
    ]);

    $context = invokePrivateMethod($services['controller'], 'buildPageContext', []);
    $roleOptions = (array) ($context['admin_access_role_options'] ?? []);
    $userOptions = (array) ($context['admin_access_user_options'] ?? []);
    $roleOptionValues = array_values(array_map(static fn (array $option): string => (string) ($option['value'] ?? ''), $roleOptions));
    $roleOptionDisabledByValue = [];
    $userOptionLabelsByValue = [];
    $userOptionDisabledByValue = [];

    foreach ($roleOptions as $option) {
        if (!is_array($option)) {
            continue;
        }

        $roleOptionDisabledByValue[(string) ($option['value'] ?? '')] = !empty($option['disabled']);
    }

    foreach ($userOptions as $option) {
        if (!is_array($option)) {
            continue;
        }

        $optionValue = (string) ($option['value'] ?? '');
        $userOptionLabelsByValue[$optionValue] = (string) ($option['label'] ?? '');
        $userOptionDisabledByValue[$optionValue] = !empty($option['disabled']);
    }

    assertSameValue(true, !empty($context['admin_access_custom_enabled']), 'Page context should expose whether custom admin access is enabled.');
    assertSameValue(['editor'], $context['admin_access_role_slugs'] ?? null, 'Page context should expose the saved admin-access role grants.');
    assertSameValue([3], $context['admin_access_user_ids'] ?? null, 'Page context should expose the saved admin-access user grants.');
    assertSameValue(['administrator', 'author', 'contributor', 'editor', 'subscriber'], $roleOptionValues, 'Page context should expose sorted admin-access role options including the implicit administrator role.');
    assertSameValue(true, $roleOptionDisabledByValue['administrator'] ?? false, 'Page context should mark the implicit administrator role as disabled in the role list.');
    assertSameValue(false, $roleOptionDisabledByValue['editor'] ?? true, 'Page context should keep non-administrator roles selectable in the role list.');
    assertSameValue('Admin User (admin)', $userOptionLabelsByValue['1'] ?? '', 'Page context should still expose administrator users in the individual user list.');
    assertSameValue(true, $userOptionDisabledByValue['1'] ?? false, 'Page context should mark implicit administrator users as disabled individual grant options.');
    assertSameValue('Author User (author)', $userOptionLabelsByValue['3'] ?? '', 'Page context should expose user admin-access options with readable labels.');
    assertSameValue(false, $userOptionDisabledByValue['3'] ?? true, 'Page context should keep non-administrator users selectable in the individual user list.');
    assertSameValue(1, $context['admin_access_summary']['implicit_admin_count'] ?? null, 'Page context should expose the implicit administrator count in the admin-access summary.');
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

$tests['admin_controller_builds_six_preview_panels_including_marketing_and_code'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $panels = invokePrivateMethod($services['controller'], 'buildPreviewPanels');
    $keys = array_map(
        static fn (array $panel): string => (string) ($panel['key'] ?? ''),
        $panels
    );

    assertSameValue(
        ['editorial', 'card', 'reading', 'interface', 'marketing', 'code'],
        $keys,
        'Preview panels should include Marketing after Interface and before the dedicated Code tab.'
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

$tests['admin_controller_adds_classes_only_generated_css_note_to_readable_preview_only'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings([
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

    $panel = invokePrivateMethod(
        $services['controller'],
        'buildGeneratedCssPanel',
        [$services['settings']->getSettings()]
    );

    assertContainsValue(':root', (string) ($panel['value'] ?? ''), 'Classes-only generated CSS should still include the core root block in the real stylesheet value.');
    assertNotContainsValue('Still kept in Classes only', (string) ($panel['value'] ?? ''), 'The real generated CSS value should stay unchanged by the display-only classes-only note.');
    assertContainsValue('Still kept in Classes only', (string) ($panel['readable_display_value'] ?? ''), 'Classes-only generated CSS should explain the retained root variables in the readable preview.');
    assertContainsValue('Automatic.css sync', (string) ($panel['readable_display_value'] ?? ''), 'The readable preview should explain that Automatic.css sync depends on the retained root variables.');
    assertContainsValue('Gutenberg editor parity', (string) ($panel['readable_display_value'] ?? ''), 'The readable preview should explain that Gutenberg editor parity depends on the retained root variables.');
    assertContainsValue('Etch canvas parity', (string) ($panel['readable_display_value'] ?? ''), 'The readable preview should explain that Etch canvas parity depends on the retained root variables.');
};

$tests['admin_controller_adds_classes_only_note_to_site_snippet_display_only'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveAppliedRoles([
        'heading' => '',
        'body' => '',
        'heading_fallback' => 'system-ui, sans-serif',
        'body_fallback' => 'system-ui, sans-serif',
    ], []);
    $settings = $services['settings']->saveSettings([
        'auto_apply_roles' => '1',
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

    $panels = invokePrivateMethod(
        $services['controller'],
        'buildOutputPanels',
        [$services['settings']->getRoles($services['catalog']->getCatalog()), $settings, $services['catalog']->getCatalog(), $services['settings']->getAppliedRoles($services['catalog']->getCatalog())]
    );

    $usagePanel = null;

    foreach ($panels as $panel) {
        if (($panel['key'] ?? '') === 'usage') {
            $usagePanel = $panel;
            break;
        }
    }

    assertSameValue(true, is_array($usagePanel), 'The Site Snippet output panel should still be present in Classes-only mode.');
    assertContainsValue(':root', (string) ($usagePanel['value'] ?? ''), 'The Site Snippet real output should still include the retained root block in Classes-only mode.');
    assertNotContainsValue('Still kept in Classes only', (string) ($usagePanel['value'] ?? ''), 'The Site Snippet real output should stay unchanged by the display-only Classes-only note.');
    assertContainsValue('Still kept in Classes only', (string) ($usagePanel['display_value'] ?? ''), 'The Site Snippet display output should explain why the retained root variables remain in Classes-only mode.');
    assertContainsValue('Automatic.css sync', (string) ($usagePanel['display_value'] ?? ''), 'The Site Snippet display output should explain that Automatic.css sync depends on the retained root variables.');
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
        is_array(get_transient(TransientKey::forSite('tasty_fonts_search_cooldown_42'))),
        'Search cooldown should be stored in a per-user transient.'
    );
};

$tests['admin_controller_rate_limits_expensive_rest_actions_per_user'] = static function (): void {
    resetTestState();

    global $currentUserId;

    $currentUserId = 42;
    $services = makeServiceGraph();
    $resolverCalls = 0;

    $first = invokePrivateMethod(
        $services['controller'],
        'runRateLimitedAction',
        [
            'google_import',
            static function () use (&$resolverCalls): array {
                $resolverCalls += 1;

                return ['status' => 'ok', 'call' => $resolverCalls];
            },
        ]
    );
    $second = invokePrivateMethod(
        $services['controller'],
        'runRateLimitedAction',
        [
            'google_import',
            static function () use (&$resolverCalls): array {
                $resolverCalls += 1;

                return ['status' => 'ok', 'call' => $resolverCalls];
            },
        ]
    );

    $currentUserId = 84;
    $third = invokePrivateMethod(
        $services['controller'],
        'runRateLimitedAction',
        [
            'google_import',
            static function () use (&$resolverCalls): array {
                $resolverCalls += 1;

                return ['status' => 'ok', 'call' => $resolverCalls];
            },
        ]
    );

    assertSameValue(2, $resolverCalls, 'Expensive REST action throttles should block repeated requests for the same user while allowing a different user to proceed.');
    assertSameValue('ok', (string) ($first['status'] ?? ''), 'The first expensive action call should run normally.');
    assertSameValue(true, is_wp_error($second), 'A repeated expensive action in the cooldown window should be rejected.');
    assertSameValue('tasty_fonts_rest_action_rate_limited', is_wp_error($second) ? $second->get_error_code() : '', 'Repeated expensive actions should use the dedicated rate limit error code.');
    assertSameValue('ok', (string) ($third['status'] ?? ''), 'Rate limiting should be scoped per user, not globally.');
    assertSameValue(
        true,
        is_array(get_transient(TransientKey::forSite(AdminController::ACTION_COOLDOWN_TRANSIENT_PREFIX . 'google_import_42'))),
        'Expensive REST action throttles should be stored in per-user transients.'
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
        'custom-css/dry-run',
        (string) ($localizedScripts['tasty-fonts-admin']['data']['routes']['customCssDryRun'] ?? ''),
        'Admin scripts should receive the custom CSS dry-run REST route path.'
    );
    assertSameValue(
        'custom-css/import',
        (string) ($localizedScripts['tasty-fonts-admin']['data']['routes']['customCssImport'] ?? ''),
        'Admin scripts should receive the custom CSS final import REST route path.'
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

$tests['admin_controller_registers_single_canonical_admin_route'] = static function (): void {
    resetTestState();

    global $menuPageCalls;
    global $submenuPageCalls;

    $services = makeServiceGraph();
    $services['controller']->registerMenu();

    assertSameValue(AdminController::MENU_SLUG, (string) ($menuPageCalls[0]['menu_slug'] ?? ''), 'The top-level Tasty Fonts menu should keep the existing menu slug.');
    assertSameValue(
        TASTY_FONTS_URL . 'assets/images/tasty-sidebar-icon.svg',
        (string) ($menuPageCalls[0]['icon_url'] ?? ''),
        'The top-level Tasty Fonts menu should use the custom SVG icon instead of a dashicon.'
    );
    assertSameValue([], $submenuPageCalls, 'Version 2 should expose only the canonical single admin route.');
};

$tests['admin_controller_recognizes_task_based_admin_hooks'] = static function (): void {
    resetTestState();

    assertSameValue(true, AdminController::isPluginAdminHook('toplevel_page_' . AdminController::MENU_SLUG), 'The top-level roles page hook should be recognized.');
    assertSameValue(false, AdminController::isPluginAdminHook('tasty-fonts_page_tasty-custom-fonts-library'), 'Retired hidden Library submenu hooks should not load plugin assets.');
    assertSameValue(false, AdminController::isPluginAdminHook('tasty-fonts_page_tasty-custom-fonts-settings'), 'Retired hidden Settings submenu hooks should not load plugin assets.');
    assertSameValue(false, AdminController::isPluginAdminHook('tasty-fonts_page_tasty-custom-fonts-diagnostics'), 'Retired hidden Diagnostics submenu hooks should not load plugin assets.');
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
        'tasty-fonts/v1/custom-css/dry-run' => 'POST',
        'tasty-fonts/v1/custom-css/import' => 'POST',
        'tasty-fonts/v1/transfer/validate' => 'POST',
        'tasty-fonts/v1/local/upload' => 'POST',
        'tasty-fonts/v1/families/fallback' => 'PATCH',
        'tasty-fonts/v1/families/font-display' => 'PATCH',
        'tasty-fonts/v1/roles/draft' => 'PATCH',
        'tasty-fonts/v1/families/delivery' => 'PATCH',
        'tasty-fonts/v1/families/publish-state' => 'PATCH',
        'tasty-fonts/v1/families/delivery-profile' => 'DELETE',
        'tasty-fonts/v1/families/card' => 'GET',
        'tasty-fonts/v1/tools/health' => 'GET',
        'tasty-fonts/v1/tools/runtime-manifest' => 'GET',
        'tasty-fonts/v1/tools/action' => 'POST',
        'tasty-fonts/v1/tools/support-bundle' => 'POST',
        'tasty-fonts/v1/tools/snapshots' => 'GET,POST',
        'tasty-fonts/v1/tools/snapshots/restore' => 'POST',
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
    assertSameValue(
        true,
        !empty($registeredRestRoutes['tasty-fonts/v1/custom-css/dry-run']['args']['args']['url']['required']),
        'Custom CSS dry-run routes should require the stylesheet URL parameter.'
    );
    assertSameValue(
        true,
        !empty($registeredRestRoutes['tasty-fonts/v1/custom-css/import']['args']['args']['snapshot_token']['required']),
        'Custom CSS final import routes should require the snapshot token parameter.'
    );
    assertSameValue(
        true,
        !empty($registeredRestRoutes['tasty-fonts/v1/custom-css/import']['args']['args']['selected_face_ids']['required']),
        'Custom CSS final import routes should require selected face IDs.'
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

$tests['rest_controller_font_import_workflow_gates_reject_disabled_provider_and_upload_routes'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'google_font_imports_enabled' => '0',
        'bunny_font_imports_enabled' => '0',
        'local_font_uploads_enabled' => '0',
    ]);

    $googleSearchRequest = new WP_REST_Request('GET', '/' . RestController::API_NAMESPACE . '/google/search');
    $googleSearchRequest->set_param('query', 'Inter');
    $googleSearchResponse = $services['rest']->searchGoogle($googleSearchRequest);

    assertSameValue(true, $googleSearchResponse instanceof WP_Error, 'Disabled Google imports should block REST search.');
    assertSameValue('tasty_fonts_font_import_workflow_disabled', $googleSearchResponse->get_error_code(), 'Disabled Google imports should use the shared workflow gate error.');
    assertSameValue(403, (int) (($googleSearchResponse->get_error_data()['status'] ?? 0)), 'Disabled Google imports should return a forbidden REST status.');

    $bunnySearchRequest = new WP_REST_Request('GET', '/' . RestController::API_NAMESPACE . '/bunny/search');
    $bunnySearchRequest->set_param('query', 'Inter');
    $bunnySearchResponse = $services['rest']->searchBunny($bunnySearchRequest);

    assertSameValue(true, $bunnySearchResponse instanceof WP_Error, 'Disabled Bunny imports should block REST search.');
    assertSameValue('tasty_fonts_font_import_workflow_disabled', $bunnySearchResponse->get_error_code(), 'Disabled Bunny imports should use the shared workflow gate error.');
    assertSameValue(403, (int) (($bunnySearchResponse->get_error_data()['status'] ?? 0)), 'Disabled Bunny imports should return a forbidden REST status.');

    $bunnyDirectResponse = $services['controller']->searchBunnyResults('Inter');
    assertSameValue(true, $bunnyDirectResponse instanceof WP_Error, 'Disabled Bunny imports should block direct search helpers too.');

    $uploadRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/local/upload');
    $uploadRequest->set_param('rows', []);
    $uploadResponse = $services['rest']->uploadLocalFonts($uploadRequest);

    assertSameValue(true, $uploadResponse instanceof WP_Error, 'Disabled custom uploads should block the REST upload route before validation.');
    assertSameValue('tasty_fonts_font_import_workflow_disabled', $uploadResponse->get_error_code(), 'Disabled custom uploads should use the shared workflow gate error.');
    assertSameValue(403, (int) (($uploadResponse->get_error_data()['status'] ?? 0)), 'Disabled custom uploads should return a forbidden REST status.');

    $settingsRequest = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $settingsRequest->set_body_params([
        'google_font_imports_enabled' => '0',
        'google_api_key' => 'crafted-key',
    ]);
    $settingsResponse = $services['rest']->saveSettings($settingsRequest);

    assertSameValue(true, $settingsResponse instanceof WP_Error, 'Disabled Google imports should block crafted Google API key saves.');
    assertSameValue('tasty_fonts_font_import_workflow_disabled', $settingsResponse->get_error_code(), 'Blocked Google API key saves should use the shared workflow gate error.');
    assertSameValue(403, (int) (($settingsResponse->get_error_data()['status'] ?? 0)), 'Blocked Google API key saves should return a forbidden REST status.');
};

$tests['rest_controller_custom_css_feature_gate_rejects_dry_run_and_final_import_when_disabled'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $dryRunRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/dry-run');
    $dryRunRequest->set_body_params(['url' => 'https://assets.example.com/custom.css']);

    $dryRunResponse = $services['rest']->dryRunCustomCssUrl($dryRunRequest);

    assertSameValue(true, $dryRunResponse instanceof WP_Error, 'Custom CSS dry runs should be gated off by default.');
    assertSameValue('tasty_fonts_custom_css_url_imports_disabled', $dryRunResponse->get_error_code(), 'Disabled dry runs should use a stable gate error code.');
    assertSameValue(403, (int) (($dryRunResponse->get_error_data()['status'] ?? 0)), 'Disabled dry runs should return a forbidden REST status.');
    assertContainsValue('Settings > Behavior > Font Import Workflows', $dryRunResponse->get_error_message(), 'Disabled dry-run errors should tell users where to enable the workflow.');

    $importRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $importRequest->set_body_params([
        'snapshot_token' => 'snapshot-token',
        'selected_face_ids' => ['face-id'],
    ]);

    $importResponse = $services['rest']->importCustomCssUrl($importRequest);

    assertSameValue(true, $importResponse instanceof WP_Error, 'Custom CSS final imports should be gated off by default.');
    assertSameValue('tasty_fonts_custom_css_url_imports_disabled', $importResponse->get_error_code(), 'Disabled final imports should use the same stable gate error code.');
    assertSameValue(403, (int) (($importResponse->get_error_data()['status'] ?? 0)), 'Disabled final imports should return a forbidden REST status.');
};

$tests['rest_controller_custom_css_dry_run_returns_review_plan'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    tastyFontsEnableCustomCssUrlImports($services);
    $cssUrl = 'https://assets.example.com/custom.css';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Review Sans";
    font-style: normal;
    font-weight: 400;
    src: url("https://cdn.example.com/review-sans.woff2") format("woff2");
}
CSS,
    ];
    tastyFontsMockCustomCssFont('https://cdn.example.com/review-sans.woff2');
    $request = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/dry-run');
    $request->set_body_params(['url' => $cssUrl]);

    $response = $services['rest']->dryRunCustomCssUrl($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'Custom CSS dry runs should return a native REST response object.');
    assertSameValue('dry_run', (string) ($data['status'] ?? ''), 'The REST response should expose the dry-run status.');
    assertSameValue('Review Sans', (string) ($data['plan']['families'][0]['family'] ?? ''), 'The REST response should include detected review families.');
    assertSameValue('woff2', (string) ($data['plan']['families'][0]['faces'][0]['format'] ?? ''), 'The REST response should include detected face formats.');
    assertSameValue('valid', (string) ($data['plan']['families'][0]['faces'][0]['status'] ?? ''), 'The REST response should include validated font URL status.');
    assertContainsValue('WOFF2 signature matched.', implode(' ', $data['plan']['families'][0]['faces'][0]['validation']['notes'] ?? []), 'The REST response should include validation notes for review details.');
};

$tests['rest_controller_custom_css_dry_run_stores_short_lived_server_snapshot'] = static function (): void {
    resetTestState();

    global $transientStore;

    $services = makeServiceGraph();
    $data = tastyFontsRunCustomCssDryRunRestFixture($services);
    $token = (string) ($data['snapshot_token'] ?? '');
    $snapshotKey = tastyFontsCustomCssSnapshotTransientKey();
    $snapshot = is_string($snapshotKey) && $snapshotKey !== '' ? ($transientStore[$snapshotKey] ?? null) : null;

    assertSameValue(true, $token !== '', 'REST dry runs should return a snapshot token to the browser.');
    assertSameValue(900, (int) ($data['snapshot_ttl_seconds'] ?? 0), 'Snapshot tokens should advertise an approximately fifteen-minute TTL.');
    assertSameValue(true, is_array($snapshot), 'REST dry runs should store a server-side snapshot transient.');
    assertSameValue(900, (int) (($snapshot['expires_at'] ?? 0) - ($snapshot['created_at'] ?? 0)), 'Stored snapshots should expire after about fifteen minutes.');
    assertSameValue('Snapshot Sans', (string) ($snapshot['plan']['families'][0]['family'] ?? ''), 'Snapshots should store the normalized server-side review plan.');
    assertSameValue(false, array_key_exists('raw_css', (array) $snapshot), 'Snapshots should not store raw CSS under a raw_css key.');
    assertNotContainsValue('@font-face', wp_json_encode($snapshot) ?: '', 'Snapshots should not store the fetched raw stylesheet body.');
};

$tests['rest_controller_custom_css_final_import_accepts_valid_snapshot_once'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $data = tastyFontsRunCustomCssDryRunRestFixture($services);
    $token = (string) ($data['snapshot_token'] ?? '');
    $faceId = (string) ($data['plan']['families'][0]['faces'][0]['id'] ?? '');
    tastyFontsMockCustomCssFinalFont('https://cdn.example.com/snapshot-sans.woff2', 'woff2', tastyFontsTestFontBytes('woff2'));
    $request = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $request->set_body_params([
        'snapshot_token' => $token,
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'self_hosted',
        'family_fallbacks' => ['snapshot-sans' => 'serif'],
        'duplicate_handling' => 'skip',
    ]);

    $response = $services['rest']->importCustomCssUrl($request);
    $result = $response instanceof WP_REST_Response ? $response->get_data() : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'A valid final import contract should return a native REST response.');
    assertSameValue('imported', (string) ($result['status'] ?? ''), 'Slice 6 final import should persist selected self-hosted faces.');
    assertSameValue('self_hosted', (string) ($result['delivery_mode'] ?? ''), 'Final import should report self-hosted delivery mode.');
    assertSameValue(1, (int) ($result['counts']['faces_imported'] ?? 0), 'Final import should save the selected snapshot face.');
    assertSameValue('serif', $services['settings']->getFamilyFallback('Snapshot Sans'), 'Final import should persist explicit family fallback choices.');
    assertSameValue('custom', (string) ($services['imports']->getFamily('snapshot-sans')['delivery_profiles'][$result['families'][0]['delivery_id']]['provider'] ?? ''), 'Final import should save a custom provider profile from server snapshot metadata.');

    $reusedRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $reusedRequest->set_body_params([
        'snapshot_token' => $token,
        'selected_face_ids' => [$faceId],
    ]);
    $reusedResponse = $services['rest']->importCustomCssUrl($reusedRequest);

    assertSameValue(true, $reusedResponse instanceof WP_Error, 'Snapshot tokens should be single-use after a valid final import contract.');
    assertSameValue('tasty_fonts_custom_css_snapshot_unavailable', $reusedResponse->get_error_code(), 'Reused snapshot tokens should be rejected as unavailable.');
    assertSameValue(410, (int) (($reusedResponse->get_error_data()['status'] ?? 0)), 'Reused snapshot tokens should map to an expired/gone REST status.');
};

$tests['rest_controller_custom_css_remote_final_import_accepts_valid_snapshot_once'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $data = tastyFontsRunCustomCssDryRunRestFixture($services);
    $token = (string) ($data['snapshot_token'] ?? '');
    $faceId = (string) ($data['plan']['families'][0]['faces'][0]['id'] ?? '');
    $remoteRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $remoteRequest->set_body_params([
        'snapshot_token' => $token,
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'remote',
        'family_fallbacks' => ['snapshot-sans' => 'serif'],
    ]);

    $remoteResponse = $services['rest']->importCustomCssUrl($remoteRequest);
    $result = $remoteResponse instanceof WP_REST_Response ? $remoteResponse->get_data() : [];
    $family = $services['imports']->getFamily('snapshot-sans');
    $profile = is_array($family) ? (array) ($family['delivery_profiles'][$result['families'][0]['delivery_id'] ?? ''] ?? []) : [];
    $face = (array) ($profile['faces'][0] ?? []);

    assertSameValue(true, $remoteResponse instanceof WP_REST_Response, 'Remote final imports should return a native REST response for valid snapshot contracts.');
    assertSameValue('imported', (string) ($result['status'] ?? ''), 'Remote final import should persist selected snapshot faces.');
    assertSameValue('remote', (string) ($result['delivery_mode'] ?? ''), 'Remote final import should report remote delivery mode to the client.');
    assertSameValue(1, (int) ($result['counts']['remote_urls_saved'] ?? 0), 'Remote final import should count saved absolute remote font URLs.');
    assertSameValue(0, (int) ($result['counts']['files_written'] ?? -1), 'Remote final import should not write local font files.');
    assertSameValue('custom', (string) ($profile['provider'] ?? ''), 'Remote final import should save custom provider profiles.');
    assertSameValue('cdn', (string) ($profile['type'] ?? ''), 'Remote final import should normalize the stored delivery type to cdn.');
    assertSameValue('https://assets.example.com/snapshot.css', (string) ($profile['meta']['source_css_url'] ?? ''), 'Remote final import should retain the reviewed source CSS URL in profile metadata.');
    assertSameValue('assets.example.com', (string) ($profile['meta']['source_host'] ?? ''), 'Remote final import should retain the reviewed source CSS host in profile metadata.');
    assertSameValue('https://cdn.example.com/snapshot-sans.woff2', (string) ($face['files']['woff2'] ?? ''), 'Remote final import should save the absolute remote font URL as the generated CSS source.');
    assertSameValue([], (array) ($face['paths'] ?? []), 'Remote final import should not store local path metadata for remote faces.');
    assertSameValue('serif', $services['settings']->getFamilyFallback('Snapshot Sans'), 'Remote final import should persist explicit family fallback choices like self-hosted import.');

    $reusedRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $reusedRequest->set_body_params([
        'snapshot_token' => $token,
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'remote',
    ]);
    $reusedResponse = $services['rest']->importCustomCssUrl($reusedRequest);

    assertSameValue(true, $reusedResponse instanceof WP_Error, 'Snapshot tokens should be single-use after a valid remote final import contract.');
    assertSameValue('tasty_fonts_custom_css_snapshot_unavailable', $reusedResponse->get_error_code(), 'Reused remote snapshot tokens should be rejected as unavailable.');
};

$tests['rest_controller_custom_css_final_import_rejects_missing_expired_and_mismatched_tokens'] = static function (): void {
    resetTestState();

    global $currentBlogId;
    global $currentUserId;
    global $transientStore;

    $services = makeServiceGraph();
    tastyFontsEnableCustomCssUrlImports($services);
    $missingRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $missingRequest->set_body_params(['selected_face_ids' => ['face-missing']]);
    $missingResponse = $services['rest']->importCustomCssUrl($missingRequest);

    assertSameValue(true, $missingResponse instanceof WP_Error, 'Final import should reject a missing snapshot token.');
    assertSameValue('tasty_fonts_custom_css_snapshot_token_missing', $missingResponse->get_error_code(), 'Missing tokens should use a stable error code.');

    $data = tastyFontsRunCustomCssDryRunRestFixture($services);
    $token = (string) ($data['snapshot_token'] ?? '');
    $faceId = (string) ($data['plan']['families'][0]['faces'][0]['id'] ?? '');
    update_option('tasty_fonts_custom_css_snapshot_lock_' . hash('sha256', $token), [
        'created_at' => time(),
        'expires_at' => time() + 60,
    ], false);
    $lockedRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $lockedRequest->set_body_params([
        'snapshot_token' => $token,
        'selected_face_ids' => [$faceId],
    ]);
    $lockedResponse = $services['rest']->importCustomCssUrl($lockedRequest);

    assertSameValue(true, $lockedResponse instanceof WP_Error, 'Final import should reject snapshot tokens already being consumed.');
    assertSameValue('tasty_fonts_custom_css_snapshot_in_use', $lockedResponse->get_error_code(), 'Locked tokens should use a stable in-use error code.');
    assertSameValue(409, (int) (($lockedResponse->get_error_data()['status'] ?? 0)), 'Locked tokens should map to a conflict REST status.');
    delete_option('tasty_fonts_custom_css_snapshot_lock_' . hash('sha256', $token));

    resetTestState();
    $services = makeServiceGraph();
    $data = tastyFontsRunCustomCssDryRunRestFixture($services);
    $token = (string) ($data['snapshot_token'] ?? '');
    $faceId = (string) ($data['plan']['families'][0]['faces'][0]['id'] ?? '');
    $snapshotKey = tastyFontsCustomCssSnapshotTransientKey();
    $snapshot = (array) ($transientStore[$snapshotKey] ?? []);
    $snapshot['expires_at'] = time() - 1;
    $transientStore[$snapshotKey] = $snapshot;

    $expiredRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $expiredRequest->set_body_params([
        'snapshot_token' => $token,
        'selected_face_ids' => [$faceId],
    ]);
    $expiredResponse = $services['rest']->importCustomCssUrl($expiredRequest);

    assertSameValue(true, $expiredResponse instanceof WP_Error, 'Final import should reject expired snapshot tokens.');
    assertSameValue('tasty_fonts_custom_css_snapshot_unavailable', $expiredResponse->get_error_code(), 'Expired tokens should be unavailable.');

    resetTestState();
    $services = makeServiceGraph();
    $data = tastyFontsRunCustomCssDryRunRestFixture($services);
    $token = (string) ($data['snapshot_token'] ?? '');
    $faceId = (string) ($data['plan']['families'][0]['faces'][0]['id'] ?? '');
    $currentUserId = 2;

    $userMismatchRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $userMismatchRequest->set_body_params([
        'snapshot_token' => $token,
        'selected_face_ids' => [$faceId],
    ]);
    $userMismatchResponse = $services['rest']->importCustomCssUrl($userMismatchRequest);

    assertSameValue(true, $userMismatchResponse instanceof WP_Error, 'Final import should reject tokens created by another user.');
    assertSameValue('tasty_fonts_custom_css_snapshot_scope_mismatch', $userMismatchResponse->get_error_code(), 'User mismatches should use the scope mismatch error.');

    resetTestState();
    $services = makeServiceGraph();
    $data = tastyFontsRunCustomCssDryRunRestFixture($services);
    $token = (string) ($data['snapshot_token'] ?? '');
    $faceId = (string) ($data['plan']['families'][0]['faces'][0]['id'] ?? '');
    $currentBlogId = 2;

    $siteMismatchRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $siteMismatchRequest->set_body_params([
        'snapshot_token' => $token,
        'selected_face_ids' => [$faceId],
    ]);
    $siteMismatchResponse = $services['rest']->importCustomCssUrl($siteMismatchRequest);

    assertSameValue(true, $siteMismatchResponse instanceof WP_Error, 'Final import should reject tokens outside the current site scope.');
    assertSameValue('tasty_fonts_custom_css_snapshot_unavailable', $siteMismatchResponse->get_error_code(), 'Site-scoped transient keys should not resolve on another site.');
};

$tests['rest_controller_custom_css_final_import_rejects_tampered_selected_ids_and_untrusted_payloads'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $data = tastyFontsRunCustomCssDryRunRestFixture($services);
    $token = (string) ($data['snapshot_token'] ?? '');
    $tamperedRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $tamperedRequest->set_body_params([
        'snapshot_token' => $token,
        'selected_face_ids' => ['face-does-not-exist'],
    ]);

    $tamperedResponse = $services['rest']->importCustomCssUrl($tamperedRequest);

    assertSameValue(true, $tamperedResponse instanceof WP_Error, 'Final import should reject selected IDs that are not present in the snapshot.');
    assertSameValue('tasty_fonts_custom_css_selected_faces_mismatch', $tamperedResponse->get_error_code(), 'Unknown selected IDs should use the mismatch error code.');

    resetTestState();
    global $transientStore;
    $services = makeServiceGraph();
    $data = tastyFontsRunCustomCssDryRunRestFixture($services);
    $token = (string) ($data['snapshot_token'] ?? '');
    $snapshotKey = tastyFontsCustomCssSnapshotTransientKey();
    $invalidFace = (array) ($data['plan']['families'][0]['faces'][0] ?? []);
    $invalidFace['id'] = 'snapshot-invalid-face';
    $invalidFace['status'] = 'invalid';
    $transientStore[$snapshotKey]['plan']['families'][0]['faces'][] = $invalidFace;
    $invalidSelectionRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $invalidSelectionRequest->set_body_params([
        'snapshot_token' => $token,
        'selected_face_ids' => ['snapshot-invalid-face'],
    ]);

    $invalidSelectionResponse = $services['rest']->importCustomCssUrl($invalidSelectionRequest);

    assertSameValue(true, $invalidSelectionResponse instanceof WP_Error, 'Final import should reject non-selectable face IDs even when they exist in the snapshot.');
    assertSameValue('tasty_fonts_custom_css_selected_faces_mismatch', $invalidSelectionResponse->get_error_code(), 'Non-selectable snapshot faces should use the mismatch error code.');

    resetTestState();
    $services = makeServiceGraph();
    $data = tastyFontsRunCustomCssDryRunRestFixture($services);
    $token = (string) ($data['snapshot_token'] ?? '');
    $faceId = (string) ($data['plan']['families'][0]['faces'][0]['id'] ?? '');
    $untrustedRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $untrustedRequest->set_body_params([
        'snapshot_token' => $token,
        'selected_face_ids' => [$faceId],
        'faces' => [[
            'id' => $faceId,
            'url' => 'https://attacker.example.invalid/evil.woff2',
            'family' => 'Injected Family',
        ]],
    ]);

    $untrustedResponse = $services['rest']->importCustomCssUrl($untrustedRequest);

    assertSameValue(true, $untrustedResponse instanceof WP_Error, 'Final import should reject browser-submitted face metadata.');
    assertSameValue('tasty_fonts_custom_css_untrusted_payload', $untrustedResponse->get_error_code(), 'Untrusted face metadata should use a stable rejection code.');

    resetTestState();
    $services = makeServiceGraph();
    $data = tastyFontsRunCustomCssDryRunRestFixture($services);
    $token = (string) ($data['snapshot_token'] ?? '');
    $faceId = (string) ($data['plan']['families'][0]['faces'][0]['id'] ?? '');
    $unexpectedPayloadRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/import');
    $unexpectedPayloadRequest->set_body_params([
        'snapshot_token' => $token,
        'selected_face_ids' => [$faceId],
        'source_url' => 'https://attacker.example.invalid/fonts.css',
    ]);

    $unexpectedPayloadResponse = $services['rest']->importCustomCssUrl($unexpectedPayloadRequest);

    assertSameValue(true, $unexpectedPayloadResponse instanceof WP_Error, 'Final import should reject unallowlisted top-level payload keys.');
    assertSameValue('tasty_fonts_custom_css_untrusted_payload', $unexpectedPayloadResponse->get_error_code(), 'Unexpected payload keys should use the untrusted payload error code.');
};

$tests['rest_controller_custom_css_dry_run_returns_basic_failure_shape'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    tastyFontsEnableCustomCssUrlImports($services);
    $request = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/dry-run');
    $request->set_body_params(['url' => 'http://example.test/fonts.css']);

    $response = $services['rest']->dryRunCustomCssUrl($request);

    assertSameValue(true, $response instanceof WP_Error, 'Invalid custom CSS dry runs should return a REST error.');
    assertSameValue('tasty_fonts_custom_css_url_invalid', $response->get_error_code(), 'The REST error should expose a stable custom CSS error code.');
    assertSameValue(400, (int) (($response->get_error_data()['status'] ?? 0)), 'The REST error should expose the default HTTP failure status.');
};

$tests['rest_controller_custom_css_dry_run_surfaces_safety_and_limit_errors'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    add_filter('tasty_fonts_rest_action_cooldown_window', static fn (): float => 0.0);

    $services = makeServiceGraph();
    tastyFontsEnableCustomCssUrlImports($services);

    $blockedRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/dry-run');
    $blockedRequest->set_body_params(['url' => 'https://localhost/fonts.css']);
    $blockedResponse = $services['rest']->dryRunCustomCssUrl($blockedRequest);

    assertSameValue(true, $blockedResponse instanceof WP_Error, 'Blocked custom CSS URLs should return REST errors.');
    assertSameValue('tasty_fonts_custom_css_url_blocked', $blockedResponse->get_error_code(), 'Blocked custom CSS URLs should preserve the safety error code.');
    assertContainsValue('public HTTPS URL', $blockedResponse->get_error_message(), 'Blocked custom CSS URL errors should remain user-facing.');

    $largeUrl = 'https://assets.example.com/rest-large.css';
    $remoteGetResponses[$largeUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => str_repeat('a', 262145),
    ];
    $largeRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/dry-run');
    $largeRequest->set_body_params(['url' => $largeUrl]);
    $largeResponse = $services['rest']->dryRunCustomCssUrl($largeRequest);

    assertSameValue(true, $largeResponse instanceof WP_Error, 'Oversized custom CSS responses should return REST errors.');
    assertSameValue('tasty_fonts_custom_css_too_large', $largeResponse->get_error_code(), 'Oversized custom CSS responses should preserve the limit error code.');
    assertSameValue(413, (int) (($largeResponse->get_error_data()['status'] ?? 0)), 'Oversized custom CSS responses should surface HTTP 413.');

    $timeoutUrl = 'https://assets.example.com/rest-timeout.css';
    $remoteGetResponses[$timeoutUrl] = new WP_Error('http_request_failed', 'Request timed out');
    $timeoutRequest = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/custom-css/dry-run');
    $timeoutRequest->set_body_params(['url' => $timeoutUrl]);
    $timeoutResponse = $services['rest']->dryRunCustomCssUrl($timeoutRequest);

    assertSameValue(true, $timeoutResponse instanceof WP_Error, 'Timeouts should return REST errors.');
    assertSameValue('tasty_fonts_custom_css_fetch_timeout', $timeoutResponse->get_error_code(), 'Timeouts should preserve the dedicated error code.');
    assertSameValue(504, (int) (($timeoutResponse->get_error_data()['status'] ?? 0)), 'Timeouts should surface HTTP 504.');
};

$tests['rest_controller_returns_advanced_tools_health_and_manifest_payloads'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();

    $healthResponse = $services['rest']->toolsHealth(
        new WP_REST_Request('GET', '/' . RestController::API_NAMESPACE . '/tools/health')
    );
    $manifestResponse = $services['rest']->toolsRuntimeManifest(
        new WP_REST_Request('GET', '/' . RestController::API_NAMESPACE . '/tools/runtime-manifest')
    );

    assertSameValue(true, $healthResponse instanceof WP_REST_Response, 'The tools health route should return a native REST response object.');
    assertSameValue(true, is_array($healthResponse->get_data()['health_checks'] ?? null), 'The tools health route should return refreshed health checks.');
    assertSameValue(true, is_array($healthResponse->get_data()['generated_css_panel'] ?? null), 'The tools health route should return the refreshed generated CSS panel.');
    assertSameValue(true, $manifestResponse instanceof WP_REST_Response, 'The runtime manifest route should return a native REST response object.');
    assertSameValue(true, is_array($manifestResponse->get_data()['runtime_manifest']['generated_css'] ?? null), 'The runtime manifest route should expose generated stylesheet metadata.');
    assertSameValue(true, is_array($manifestResponse->get_data()['runtime_manifest']['roles'] ?? null), 'The runtime manifest route should expose role resolution data.');
};

$tests['rest_controller_runs_safe_advanced_tools_action_and_returns_refreshed_payload'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/tools/action');
    $request->set_param('action', 'repair_storage_scaffold');

    $response = $services['rest']->runToolsAction($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'The tools action route should return a native REST response object.');
    assertSameValue('Storage scaffold repaired.', (string) ($data['message'] ?? ''), 'The tools action route should run the requested safe action.');
    assertSameValue(true, is_array($data['advanced_tools']['health_checks'] ?? null), 'The tools action route should return refreshed Advanced Tools health state.');
    assertSameValue(true, is_array($data['generated_css_panel'] ?? null), 'The tools action route should return refreshed generated CSS data.');
    assertSameValue(true, is_array($data['logs'] ?? null), 'The tools action route should return refreshed activity entries.');
};

$tests['rest_controller_lists_rollback_snapshots_from_snapshot_service'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $create = $services['controller']->createRollbackSnapshot('manual');
    assertFalseValue(is_wp_error($create), 'Creating a rollback snapshot should succeed before listing snapshots through REST.');

    $response = $services['rest']->snapshots(
        new WP_REST_Request('GET', '/' . RestController::API_NAMESPACE . '/tools/snapshots')
    );
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];
    $snapshots = is_array($data['snapshots'] ?? null) ? $data['snapshots'] : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'The snapshots route should return a native REST response object.');
    assertSameValue(1, count($snapshots), 'The snapshots route should return persisted rollback snapshots.');
    assertSameValue((string) ($create['snapshot']['id'] ?? ''), (string) ($snapshots[0]['id'] ?? ''), 'The snapshots route should return the snapshot service entries, not an empty Advanced Tools payload.');
};

$tests['cli_command_routes_phase_four_actions_through_admin_controller'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $command = new CliCommand($services['controller'], static fn (): string => 'cli-secret-key');

    ob_start();
    $command->doctor([], []);
    $doctorOutput = (string) ob_get_clean();

    assertContainsValue('Tasty Fonts doctor:', $doctorOutput, 'The WP-CLI doctor command should summarize Advanced Tools health checks.');

    ob_start();
    $command->googleApiKey(['status'], ['format' => 'json']);
    $initialGoogleOutput = (string) ob_get_clean();

    assertContainsValue('"has_google_api_key": false', $initialGoogleOutput, 'The WP-CLI Google API key status command should report when no key is stored.');
    assertNotContainsValue('cli-secret-key', $initialGoogleOutput, 'The WP-CLI Google API key status command should never print secrets.');

    $remoteGetResponses['https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key=cli-secret-key'] = [
        'response' => ['code' => 200],
        'body' => wp_json_encode(['items' => []]),
    ];

    ob_start();
    $command->googleApiKey(['save'], ['format' => 'json']);
    $saveGoogleOutput = (string) ob_get_clean();

    assertContainsValue('"has_google_api_key": true', $saveGoogleOutput, 'The WP-CLI Google API key save command should persist a validated prompted key.');
    assertContainsValue('"google_api_key_status": "valid"', $saveGoogleOutput, 'The WP-CLI Google API key save command should return validation state.');
    assertNotContainsValue('cli-secret-key', $saveGoogleOutput, 'The WP-CLI Google API key save command should redact the prompted key from JSON output.');
    assertSameValue('cli-secret-key', (string) ($services['settings']->getSettings()['google_api_key'] ?? ''), 'The prompted Google API key should be saved through the shared settings repository.');

    $legacyGoogleKeyRejected = false;

    try {
        $command->googleApiKey(['save'], ['google-api-key' => 'plaintext-secret']);
    } catch (RuntimeException $exception) {
        $legacyGoogleKeyRejected = str_contains($exception->getMessage(), '--google-api-key option has been removed');
    }

    assertSameValue(true, $legacyGoogleKeyRejected, 'The WP-CLI Google API key save command should reject the removed plaintext --google-api-key option.');

    ob_start();
    $command->css(['regenerate'], []);
    $cssOutput = (string) ob_get_clean();

    assertContainsValue('Generated CSS regenerated.', $cssOutput, 'The WP-CLI CSS command should route through the shared regenerate CSS action.');

    ob_start();
    $command->library(['rescan'], []);
    $libraryOutput = (string) ob_get_clean();

    assertContainsValue('Fonts rescanned.', $libraryOutput, 'The WP-CLI library command should route through the shared rescan action.');

    $settingsResetRequiresYes = false;

    try {
        $command->settings(['reset'], []);
    } catch (RuntimeException) {
        $settingsResetRequiresYes = true;
    }

    assertSameValue(true, $settingsResetRequiresYes, 'The WP-CLI settings reset command should require --yes.');

    ob_start();
    $command->settings(['reset'], ['yes' => true]);
    $settingsResetOutput = (string) ob_get_clean();

    assertContainsValue('Plugin settings reset to defaults.', $settingsResetOutput, 'The WP-CLI settings reset command should route through the shared reset action when confirmed.');

    $filesDeleteRequiresYes = false;

    try {
        $command->files(['delete'], []);
    } catch (RuntimeException) {
        $filesDeleteRequiresYes = true;
    }

    assertSameValue(true, $filesDeleteRequiresYes, 'The WP-CLI files delete command should require --yes.');

    $services['storage']->ensureRootDirectory();
    $managedFile = (string) $services['storage']->pathForRelativePath('upload/cli/cli-400.woff2');
    $services['storage']->writeAbsoluteFile($managedFile, 'font-data');

    ob_start();
    $command->files(['delete'], ['yes' => true]);
    $filesDeleteOutput = (string) ob_get_clean();

    assertContainsValue('Plugin-managed files deleted.', $filesDeleteOutput, 'The WP-CLI files delete command should route through the shared managed-file cleanup action.');
    assertSameValue(false, file_exists($managedFile), 'The WP-CLI files delete command should remove managed font files.');
    assertSameValue(true, is_readable((string) $services['storage']->pathForRelativePath('index.php')), 'The WP-CLI files delete command should recreate storage scaffolding.');

    ob_start();
    $command->supportBundle([], ['format' => 'json']);
    $supportBundleOutput = (string) ob_get_clean();
    $supportBundlePayload = json_decode($supportBundleOutput, true);

    assertContainsValue('"bundle"', $supportBundleOutput, 'The WP-CLI support-bundle alias should expose support bundle JSON for automation.');
    if (is_array($supportBundlePayload)) {
        @unlink((string) ($supportBundlePayload['bundle']['path'] ?? ''));
    }

    ob_start();
    $command->snapshot(['list'], ['format' => 'json']);
    $snapshotOutput = (string) ob_get_clean();

    assertContainsValue('"snapshots": []', $snapshotOutput, 'The WP-CLI snapshot list command should expose JSON output for automation.');
};

$tests['cli_transfer_import_accepts_prompted_google_api_key_without_printing_it'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

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
    assertFalseValue(is_wp_error($bundle), 'Building a transfer bundle for the CLI prompt test should succeed.');

    $remoteGetResponses['https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key=fresh-cli-key'] = [
        'response' => ['code' => 200],
        'body' => wp_json_encode(['items' => []]),
    ];

    $command = new CliCommand($services['controller'], static fn (): string => 'fresh-cli-key');

    ob_start();
    $command->transfer(
        ['import', (string) ($bundle['path'] ?? '')],
        ['dry-run' => true, 'prompt-google-api-key' => true, 'format' => 'json']
    );
    $output = (string) ob_get_clean();

    assertContainsValue('"status": "validated"', $output, 'Transfer dry-run should accept a prompted fresh Google API key.');
    assertContainsValue('"google_api_key_state": "valid"', $output, 'Transfer dry-run should report the prompted Google API key validation state.');
    assertNotContainsValue('fresh-cli-key', $output, 'Transfer dry-run JSON output should not print the prompted Google API key.');

    @unlink((string) ($bundle['path'] ?? ''));
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
    assertContainsValue('"Inter",sans-serif', (string) ($panel['value'] ?? ''), 'Generated CSS should keep the explicit role fallback when a family fallback changes.');
    assertContainsValue('"Inter",sans-serif', (string) ($panel['readable_display_value'] ?? ''), 'The readable Generated CSS panel should also keep the explicit role fallback.');
    assertNotContainsValue('"Inter",serif', (string) ($panel['value'] ?? ''), 'Family fallback changes should not override an explicit role fallback stack.');
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
        'extended_variable_role_weight_vars_enabled' => '0',
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
    assertSameValue(true, !empty($data['settings']['extended_variable_role_weight_vars_enabled']), 'The minimal preset should keep role weight variables enabled.');
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
        'extended_variable_role_weight_vars_enabled' => '1',
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
        'extended_variable_role_weight_vars_enabled' => '1',
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
        'extended_variable_role_weight_vars_enabled' => '0',
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

$tests['rest_controller_settings_keeps_role_class_styles_in_classes_output'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
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
        'class_output_role_styles_enabled' => '1',
        'per_variant_font_variables_enabled' => '0',
        'role_usage_font_weight_enabled' => '0',
    ]);

    $response = $services['rest']->saveSettings($request);
    $data = $response->get_data();

    assertSameValue(true, $response instanceof WP_REST_Response, 'Styled role class autosave should return a native REST response.');
    assertSameValue(true, !empty($data['settings']['class_output_role_styles_enabled']), 'The settings autosave route should persist the role class style toggle.');
    assertSameValue('classes', (string) ($data['settings']['output_quick_mode_preference'] ?? ''), 'Opting role classes into weights and settings should keep the output preset on classes only.');
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

$tests['rest_controller_settings_persists_admin_access_lists_and_requests_reload'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
        'admin_access_custom_enabled' => true,
        'admin_access_role_slugs' => ['editor', 'administrator', 'missing'],
        'admin_access_user_ids' => ['3', '0', '999'],
    ]);

    $response = $services['rest']->saveSettings($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'The settings autosave route should return a native REST response when saving admin access settings.');
    assertSameValue(true, !empty($data['settings']['admin_access_custom_enabled']), 'The settings autosave route should persist the custom admin-access toggle.');
    assertSameValue(['editor'], $data['settings']['admin_access_role_slugs'] ?? null, 'The settings autosave route should persist normalized admin access role grants.');
    assertSameValue([3], $data['settings']['admin_access_user_ids'] ?? null, 'The settings autosave route should persist normalized admin access user grants.');
    assertSameValue(true, !empty($data['reload_required']), 'Admin access setting changes should request a reload because menu and page access update on page load.');
    assertContainsValue('admin access updated', (string) ($data['message'] ?? ''), 'Admin access setting saves should mention the access change in the returned settings toast.');
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

$tests['admin_controller_role_draft_save_clears_stale_axes_when_switching_to_static_family'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['variable_fonts_enabled' => '1']);
    $services['imports']->saveProfile(
        'Noto Sans Variable',
        'noto-sans-variable',
        [
            'id' => 'local-variable',
            'provider' => 'local',
            'type' => 'self_hosted',
            'label' => 'Self-hosted',
            'variants' => ['regular'],
            'faces' => [
                [
                    'family' => 'Noto Sans Variable',
                    'weight' => '100..900',
                    'style' => 'normal',
                    'is_variable' => true,
                    'axes' => [
                        'WGHT' => ['min' => '100', 'default' => '400', 'max' => '900'],
                    ],
                    'files' => ['woff2' => 'noto-sans-variable/NotoSans-Variable.woff2'],
                ],
            ],
            'meta' => [],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Noto Sans',
        'noto-sans',
        [
            'id' => 'local-static',
            'provider' => 'local',
            'type' => 'self_hosted',
            'label' => 'Self-hosted',
            'format' => 'static',
            'variants' => ['regular', '700'],
            'faces' => [
                ['family' => 'Noto Sans', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'noto-sans/NotoSans-400.woff2']],
                ['family' => 'Noto Sans', 'weight' => '700', 'style' => 'normal', 'files' => ['woff2' => 'noto-sans/NotoSans-700.woff2']],
            ],
            'meta' => [],
        ],
        'published',
        true
    );

    $catalog = $services['catalog']->getCatalog();
    $families = invokePrivateMethod($services['controller'], 'buildSelectableFamilyNames', [$catalog]);

    $services['settings']->saveRoles(
        [
            'heading' => 'Noto Sans',
            'body' => 'Noto Sans Variable',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
            'body_axes' => ['WGHT' => '900'],
        ],
        $families
    );

    $result = $services['controller']->saveRoleDraftValues([
        'heading' => 'Noto Sans',
        'body' => 'Noto Sans',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
        'body_weight' => '700',
    ]);

    assertSameValue('Noto Sans', (string) ($result['roles']['body'] ?? ''), 'Draft saves should keep the switched static body family.');
    assertSameValue('700', (string) ($result['roles']['body_weight'] ?? ''), 'Draft saves should preserve the submitted static body weight override.');
    assertSameValue([], (array) ($result['roles']['body_axes'] ?? []), 'Switching from a variable family to a static family should clear stale role axis values when the removed axis fields are omitted from the request.');
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
    assertSameValue(false, array_key_exists('heading_delivery_id', (array) ($data['roles'] ?? [])), 'The roles/draft route should not return retired role delivery override fields.');
    assertSameValue(false, array_key_exists('body_delivery_id', (array) ($data['roles'] ?? [])), 'The roles/draft route should not return retired role delivery override fields.');
    assertSameValue(false, array_key_exists('monospace_delivery_id', (array) ($data['roles'] ?? [])), 'The roles/draft route should not return retired role delivery override fields.');
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

$tests['settings_repository_defaults_font_import_workflows_for_existing_behavior'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $settings = $services['settings']->getSettings();

    assertSameValue(true, !empty($settings['google_font_imports_enabled']), 'Google Fonts imports should default on for the standard provider workflow.');
    assertSameValue(true, !empty($settings['bunny_font_imports_enabled']), 'Bunny Fonts imports should default on for the standard provider workflow.');
    assertSameValue(true, !empty($settings['local_font_uploads_enabled']), 'Custom uploads should default on for local font workflows.');
    assertSameValue(false, !empty($settings['adobe_font_imports_enabled']), 'Adobe Fonts imports should default off until explicitly enabled.');
    assertSameValue(false, !empty($settings['custom_css_url_imports_enabled']), 'URL imports should default off until explicitly enabled.');
};

$tests['rest_controller_settings_accepts_font_import_workflow_gates'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/settings');
    $request->set_body_params([
        'google_font_imports_enabled' => '0',
        'bunny_font_imports_enabled' => '0',
        'local_font_uploads_enabled' => '0',
        'adobe_font_imports_enabled' => '1',
        'custom_css_url_imports_enabled' => '1',
    ]);

    $response = $services['rest']->saveSettings($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'The settings route should return a native REST response when saving font import workflow gates.');
    assertSameValue(false, !empty($data['settings']['google_font_imports_enabled']), 'The settings route should persist the Google Fonts import workflow gate.');
    assertSameValue(false, !empty($data['settings']['bunny_font_imports_enabled']), 'The settings route should persist the Bunny Fonts import workflow gate.');
    assertSameValue(false, !empty($data['settings']['local_font_uploads_enabled']), 'The settings route should persist the custom upload workflow gate.');
    assertSameValue(true, !empty($data['settings']['adobe_font_imports_enabled']), 'The settings route should allow Adobe Fonts imports to be turned on.');
    assertSameValue(true, !empty($data['settings']['custom_css_url_imports_enabled']), 'The settings route should allow URL imports to be turned on.');
    assertSameValue(true, !empty($data['reload_required']), 'Changing font import workflow gates should request a reload so server-rendered Add Fonts panels update.');
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

$tests['rest_controller_preserves_structured_error_data_when_wrapping_errors'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $error = new WP_Error(
        'tasty_fonts_custom_css_import_failed',
        'No custom font faces could be imported.',
        [
            'failed_faces' => [[
                'family' => 'Roboto',
                'weight' => '400',
                'style' => 'normal',
                'message' => 'The font URL returned HTTP 404.',
            ]],
        ]
    );

    $response = invokePrivateMethod($services['rest'], 'restResult', [$error, 400]);
    $data = $response instanceof WP_Error ? (array) $response->get_error_data() : [];

    assertSameValue(true, $response instanceof WP_Error, 'Wrapped REST errors should still return WP_Error instances.');
    assertSameValue('tasty_fonts_custom_css_import_failed', $response->get_error_code(), 'Wrapped REST errors should preserve the original plugin error code.');
    assertSameValue(422, (int) ($data['status'] ?? 0), 'Wrapped REST errors should include the mapped HTTP status code.');
    assertSameValue('Roboto', (string) ($data['failed_faces'][0]['family'] ?? ''), 'Wrapped REST errors should preserve failed face payload details.');
    assertSameValue('The font URL returned HTTP 404.', (string) ($data['failed_faces'][0]['message'] ?? ''), 'Wrapped REST errors should preserve failed face failure messages.');
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

$tests['rest_controller_upload_route_returns_429_when_rate_limited'] = static function (): void {
    resetTestState();

    global $uploadedFilePaths;

    $services = makeServiceGraph();
    $tmpName = uniqueTestDirectory('tmp-rest-upload-throttled') . '/inter-400-italic.woff2';
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

    $firstResponse = $services['rest']->uploadLocalFonts($request);
    $secondResponse = $services['rest']->uploadLocalFonts($request);

    assertSameValue(true, $firstResponse instanceof WP_REST_Response, 'The first throttled upload request should still complete normally.');
    assertSameValue(true, is_wp_error($secondResponse), 'Repeated upload requests in the cooldown window should return a REST error.');
    assertSameValue('tasty_fonts_rest_action_rate_limited', $secondResponse->get_error_code(), 'Repeated upload requests should expose the dedicated rate limit error code.');
    assertSameValue(429, (int) (($secondResponse->get_error_data()['status'] ?? 0)), 'Repeated upload requests should surface HTTP 429 to the admin client.');
};

$tests['google_search_cooldown_cache_is_shared_between_repeated_rest_requests'] = static function (): void {
    resetTestState();

    global $currentUserId;

    $currentUserId = 42;
    $services = makeServiceGraph();
    $services['settings']->saveSettings(['google_api_key' => 'live-key']);
    $services['settings']->saveGoogleApiKeyStatus('valid');
    set_transient(
        TransientKey::forSite(GoogleFontsClient::TRANSIENT_CATALOG),
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
        TransientKey::forSite(GoogleFontsClient::TRANSIENT_CATALOG),
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

    assertSameValue(true, in_array(TransientKey::forSite('tasty_fonts_css_v2'), $transientDeleted, true), 'Saving a family font-display override should invalidate the cached CSS payload.');
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

    assertSameValue(true, in_array(TransientKey::forSite('tasty_fonts_css_v2'), $transientDeleted, true), 'Saving a family fallback should invalidate the cached CSS payload.');
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

$tests['admin_controller_surfaces_oversized_site_transfer_posts_before_the_action_field_reaches_php'] = static function (): void {
    resetTestState();

    global $isAdminRequest;

    $isAdminRequest = true;

    $services = makeServiceGraph();
    $controller = $services['controller'];
    $transientKey = invokePrivateMethod($controller, 'getPendingNoticeTransientKey');

    $_GET['page'] = AdminController::MENU_SLUG;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=----tasty-fonts-test';
    $_SERVER['CONTENT_LENGTH'] = '999999999';
    $_POST = [];
    $_FILES = [];

    $controller->handleAdminActions();

    $toasts = get_transient($transientKey);
    $logEntry = $services['log']->all()[0] ?? [];

    assertSameValue('error', (string) ($toasts[0]['tone'] ?? ''), 'Oversized site transfer requests should queue an error toast instead of failing silently.');
    assertContainsValue(
        'larger than this server allows in a single request',
        (string) ($toasts[0]['message'] ?? ''),
        'Oversized site transfer requests should explain that the server rejected the upload before PHP populated the form fields.'
    );
    assertSameValue('transfer', (string) ($logEntry['category'] ?? ''), 'Oversized site transfer failures should be tagged as transfer log entries.');
    assertSameValue('site_transfer_import_failure', (string) ($logEntry['event'] ?? ''), 'Oversized site transfer failures should record the transfer failure event type.');
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
    assertSameValue(AdminController::PAGE_DIAGNOSTICS, (string) ($query['tf_page'] ?? ''), 'Developer deep links should activate the Advanced Tools top-level tab.');
    assertSameValue('maintenance', (string) ($query['tf_studio'] ?? ''), 'Legacy Developer links should resolve to the Advanced Tools developer panel.');
};

$tests['admin_controller_preserves_transfer_studio_tab_in_redirect_urls'] = static function (): void {
    resetTestState();

    $_GET = [
        'page' => AdminController::MENU_SLUG,
        'tf_studio' => 'transfer',
    ];

    $controller = makeAdminControllerTestInstance();
    $url = invokePrivateMethod($controller, 'buildAdminPageUrl');
    $parts = parse_url($url);
    $query = [];

    parse_str((string) ($parts['query'] ?? ''), $query);

    assertSameValue(AdminController::MENU_SLUG, (string) ($query['page'] ?? ''), 'Transfer deep links should canonicalize to the single admin page.');
    assertSameValue(AdminController::PAGE_DIAGNOSTICS, (string) ($query['tf_page'] ?? ''), 'Transfer deep links should activate the Advanced Tools top-level tab.');
    assertSameValue('transfer', (string) ($query['tf_studio'] ?? ''), 'Redirect URLs should preserve the Transfer tab selection when it is active.');

    $_GET = [
        'page' => AdminController::MENU_SLUG,
        'tf_page' => AdminController::PAGE_SETTINGS,
        'tf_studio' => 'transfer',
    ];

    $url = invokePrivateMethod($controller, 'buildAdminPageUrl');
    $parts = parse_url($url);
    $query = [];

    parse_str((string) ($parts['query'] ?? ''), $query);

    assertSameValue(AdminController::PAGE_SETTINGS, (string) ($query['tf_page'] ?? ''), 'Version 2 should not reroute old Settings Transfer URLs to Advanced Tools.');
    assertSameValue(false, isset($query['tf_studio']), 'Version 2 should drop unsupported Settings Transfer tab state.');
};

$tests['admin_controller_preserves_cli_studio_tab_in_redirect_urls'] = static function (): void {
    resetTestState();

    $_GET = [
        'page' => AdminController::MENU_SLUG,
        'tf_studio' => 'cli',
    ];

    $controller = makeAdminControllerTestInstance();
    $url = invokePrivateMethod($controller, 'buildAdminPageUrl');
    $parts = parse_url($url);
    $query = [];

    parse_str((string) ($parts['query'] ?? ''), $query);

    assertSameValue(AdminController::MENU_SLUG, (string) ($query['page'] ?? ''), 'CLI deep links should canonicalize to the single admin page.');
    assertSameValue(AdminController::PAGE_DIAGNOSTICS, (string) ($query['tf_page'] ?? ''), 'CLI deep links should activate the Advanced Tools top-level tab.');
    assertSameValue('cli', (string) ($query['tf_studio'] ?? ''), 'Redirect URLs should preserve the CLI tab selection when it is active.');
};

$tests['admin_controller_builds_site_transfer_download_urls_for_the_transfer_tab'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $url = (string) invokePrivateMethod($controller, 'buildSiteTransferDownloadUrl');

    assertContainsValue('tf_page=diagnostics', $url, 'Site transfer downloads should keep the Advanced Tools page active.');
    assertContainsValue('tf_studio=transfer', $url, 'Site transfer downloads should return to the Transfer tab.');
};

$tests['admin_controller_builds_transfer_context_from_filtered_activity_entries'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $controller = $services['controller'];
    $context = invokePrivateMethod(
        $controller,
        'buildSiteTransferContext',
        [[
            [
                'message' => 'Exported a site transfer bundle.',
                'actor' => 'System',
                'category' => 'transfer',
            ],
            [
                'message' => 'Imported the site transfer bundle (1 family, 2 files).',
                'actor' => 'Alicia',
            ],
            [
                'message' => 'Plugin caches cleared and generated assets refreshed.',
                'actor' => 'System',
            ],
        ]]
    );

    assertSameValue(2, count($context['logs'] ?? []), 'The transfer tab should receive only transfer-related activity entries.');
    assertSameValue(
        ['Alicia', 'System'],
        $context['actor_options'] ?? [],
        'The transfer tab should build actor filters from the filtered transfer activity entries only.'
    );
};

$tests['admin_controller_build_page_context_formats_activity_log_times_in_site_timezone'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore['date_format'] = 'Y-m-d';
    $optionStore['time_format'] = 'H:i:s';
    $optionStore['timezone_string'] = 'Asia/Kuala_Lumpur';
    $optionStore[TastyFonts\Repository\LogRepository::OPTION_LOG] = [[
        'time' => '2026-04-23 13:55:04',
        'message' => 'Fonts rescanned.',
        'actor' => 'System',
    ]];

    $services = makeServiceGraph();
    $context = invokePrivateMethod($services['controller'], 'buildPageContext');
    $logs = $context['logs'] ?? [];
    $firstLog = is_array($logs[0] ?? null) ? $logs[0] : [];

    assertSameValue(
        '2026-04-23 21:55:04',
        (string) ($firstLog['time'] ?? ''),
        'Activity log entries should render in the configured site timezone instead of raw UTC.'
    );
};

$tests['admin_controller_build_page_context_normalizes_enriched_activity_entries'] = static function (): void {
    resetTestState();

    global $optionStore;

    $details = wp_json_encode([[
        'label' => 'Changed settings',
        'value' => 'preview text updated',
        'kind' => 'text',
    ]]);
    $optionStore[TastyFonts\Repository\LogRepository::OPTION_LOG] = [[
        'time' => '2026-04-23 13:55:04',
        'message' => 'Plugin settings saved: preview text updated.',
        'summary' => 'Settings saved.',
        'actor' => 'System',
        'category' => 'settings',
        'event' => 'settings_saved',
        'outcome' => 'success',
        'status_label' => 'Saved',
        'source' => 'Settings',
        'entity_type' => 'settings',
        'entity_name' => 'Output Settings',
        'details_json' => is_string($details) ? $details : '',
    ]];

    $services = makeServiceGraph();
    $context = invokePrivateMethod($services['controller'], 'buildPageContext');
    $logs = $context['logs'] ?? [];
    $firstLog = is_array($logs[0] ?? null) ? $logs[0] : [];
    $details = is_array($firstLog['detail_items'] ?? null) ? $firstLog['detail_items'] : [];

    assertSameValue('Settings saved.', (string) ($firstLog['summary'] ?? ''), 'Activity context should expose the compact summary.');
    assertSameValue('success', (string) ($firstLog['outcome'] ?? ''), 'Activity context should expose the normalized outcome.');
    assertSameValue('Settings', (string) ($firstLog['source'] ?? ''), 'Activity context should expose the source label.');
    assertContainsValue('preview text updated', (string) ($firstLog['search_text'] ?? ''), 'Activity search text should include detail values.');
    assertSameValue('Changed settings', (string) ($details[0]['label'] ?? ''), 'Activity context should decode detail rows from details_json.');
};

$tests['admin_controller_save_role_drafts_logs_role_activity_instead_of_transfer_activity'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['controller']->saveRoleDraftValues([
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ]);

    $entry = $services['log']->all()[0] ?? [];

    assertSameValue('roles', (string) ($entry['category'] ?? ''), 'Role draft saves should be categorized as role activity.');
    assertSameValue('roles_saved', (string) ($entry['event'] ?? ''), 'Role draft saves should record a role-save event.');
    assertSameValue('Saved', (string) ($entry['status_label'] ?? ''), 'Role draft saves should expose a concise outcome label.');
    assertContainsValue('Saved roles', (string) ($entry['details_json'] ?? ''), 'Role draft saves should include role detail rows.');
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
        TransientKey::forSite('tasty_fonts_catalog_v2') => ['cached'],
        TransientKey::forSite('tasty_fonts_css_v2') => 'cached',
        TransientKey::forSite('tasty_fonts_css_hash_v2') => 'hash',
        TransientKey::forSite(GoogleFontsClient::TRANSIENT_CATALOG) => ['google'],
        TransientKey::forSite(GoogleFontsClient::TRANSIENT_METADATA) => ['google' => ['family' => 'Google', 'axes' => []]],
        TransientKey::forSite(BunnyFontsClient::TRANSIENT_CATALOG) => ['bunny'],
    ];

    set_transient(TransientKey::forSite(AdminController::SEARCH_CACHE_TRANSIENT_PREFIX . 'google_inter'), ['Inter'], 300);
    set_transient(TransientKey::forSite(AdminController::SEARCH_COOLDOWN_TRANSIENT_PREFIX . 'google_inter'), 1, 1);

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

$tests['handle_admin_actions_is_a_no_op_when_user_lacks_admin_access'] = static function (): void {
    resetTestState();

    global $currentUserCapabilities;
    global $currentUserId;
    global $isAdminRequest;
    global $redirectLocation;

    $isAdminRequest = true;
    $currentUserId = 2;
    $currentUserCapabilities = ['manage_options' => true];
    $_POST['tasty_fonts_clear_log'] = '1';

    $services = makeServiceGraph();
    $services['controller']->handleAdminActions();

    assertSameValue('', $redirectLocation, 'handleAdminActions() should do nothing when the current user is not allowed by the shared admin access policy.');
};

$tests['handle_admin_actions_allows_explicitly_granted_roles_without_manage_options'] = static function (): void {
    resetTestState();

    global $currentUserCapabilities;
    global $currentUserId;
    global $isAdminRequest;
    global $redirectLocation;

    $isAdminRequest = true;
    $currentUserId = 2;
    $currentUserCapabilities = ['manage_options' => false, 'read' => true];
    $_POST['tasty_fonts_clear_log'] = '1';

    $services = makeServiceGraph();
    $services['settings']->saveSettings([
        'admin_access_custom_enabled' => true,
        'admin_access_role_slugs' => ['editor'],
    ]);
    add_action(
        'tasty_fonts_before_admin_redirect_exit',
        static function (): void {
            throw new WpDieException('redirect');
        }
    );

    try {
        $services['controller']->handleAdminActions();
    } catch (WpDieException $e) {
        // Expected redirect exit.
    }

    assertSameValue(false, empty($redirectLocation), 'Explicitly granted roles should be allowed through admin actions even without manage_options.');
};

$tests['handle_admin_actions_dispatches_clear_log_and_redirects'] = static function (): void {
    resetTestState();

    global $isAdminRequest;
    global $redirectLocation;

    $isAdminRequest = true;
    $_POST['tasty_fonts_clear_log'] = '1';

    $services = makeServiceGraph();
    $services['log']->add('Entry before clear');
    add_action(
        'tasty_fonts_before_admin_redirect_exit',
        static function (): void {
            throw new WpDieException('redirect');
        }
    );

    try {
        $services['controller']->handleAdminActions();
    } catch (WpDieException $e) {
        // Expected: the redirect handler terminates after wp_safe_redirect().
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
    add_action(
        'tasty_fonts_before_admin_redirect_exit',
        static function (): void {
            throw new WpDieException('redirect');
        }
    );

    try {
        $services['controller']->handleAdminActions();
    } catch (WpDieException $e) {
        // Expected: the redirect handler terminates after wp_safe_redirect().
    }

    // The rescan handler would add a "Fonts rescanned." log entry; the clear-log handler would not.
    // If the dispatch stops after clear-log, only the "Activity log cleared." entry should be present.
    $logMessages = implode(' ', array_column($services['log']->all(), 'message'));
    assertNotContainsValue('rescanned', $logMessages, 'Only the clear-log handler should have fired; the rescan log message should be absent.');
};

$tests['handle_admin_actions_records_failed_site_transfer_imports_and_queues_inline_status'] = static function (): void {
    resetTestState();

    global $isAdminRequest;
    global $redirectLocation;

    $isAdminRequest = true;
    $_POST['tasty_fonts_import_site_transfer_bundle'] = '1';
    $_FILES['tasty_fonts_site_transfer_bundle'] = [];

    $services = makeServiceGraph();
    add_action(
        'tasty_fonts_before_admin_redirect_exit',
        static function (): void {
            throw new WpDieException('redirect');
        }
    );

    try {
        $services['controller']->handleAdminActions();
    } catch (WpDieException $e) {
        // Expected: the redirect handler terminates after wp_safe_redirect().
    }

    $logs = $services['log']->all();
    $logMessages = implode(' ', array_column($logs, 'message'));
    $status = invokePrivateMethod($services['controller'], 'consumeQueuedSiteTransferStatus');
    $logEntry = $logs[0] ?? [];

    assertContainsValue('tasty_fonts_transfer_missing_upload', $logMessages, 'Failed site transfer imports should be recorded in Activity with the specific WP_Error code.');
    assertContainsValue('Choose a Tasty Fonts transfer bundle before importing.', $logMessages, 'Failed site transfer imports should record the user-facing error message in Activity.');
    assertSameValue('transfer', (string) ($logEntry['category'] ?? ''), 'Failed site transfer imports should be tagged as transfer log entries.');
    assertSameValue('site_transfer_import_failure', (string) ($logEntry['event'] ?? ''), 'Failed site transfer imports should record the transfer failure event type.');
    assertSameValue('error', (string) ($status['tone'] ?? ''), 'Failed site transfer imports should queue an inline error status for the import panel after redirect.');
    assertSameValue('tasty_fonts_transfer_missing_upload', (string) ($status['code'] ?? ''), 'The inline import status should keep the exact WP_Error code.');
    assertContainsValue('Choose a Tasty Fonts transfer bundle before importing.', (string) ($status['message'] ?? ''), 'The inline import status should keep the user-facing failure message.');
    assertSameValue(false, empty($redirectLocation), 'Failed site transfer imports should still redirect back to the admin page.');
};
