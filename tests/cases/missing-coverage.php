<?php

declare(strict_types=1);

use TastyFonts\Admin\AdminController;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\SiteEnvironment;
use TastyFonts\Support\Storage;
use TastyFonts\Support\TransientKey;
use TastyFonts\Uninstall\UninstallHandler;

// ---------------------------------------------------------------------------
// TransientKey – forSite / prefixForSite
// ---------------------------------------------------------------------------

$tests['transient_key_for_site_prepends_blog_prefix'] = static function (): void {
    resetTestState();

    global $currentBlogId;
    $currentBlogId = 1;

    $key = TransientKey::forSite('tasty_fonts_css_v2');

    assertSameValue('blog_1_tasty_fonts_css_v2', $key, 'TransientKey::forSite should prepend blog_<id>_ to the key.');
};

$tests['transient_key_for_site_strips_leading_underscore_from_key'] = static function (): void {
    resetTestState();

    global $currentBlogId;
    $currentBlogId = 1;

    $key = TransientKey::forSite('_tasty_fonts_css_v2');

    assertSameValue('blog_1_tasty_fonts_css_v2', $key, 'TransientKey::forSite should strip a leading underscore from the key.');
};

$tests['transient_key_for_site_uses_blog_id_in_multisite'] = static function (): void {
    resetTestState();

    global $currentBlogId;
    $currentBlogId = 5;

    $key = TransientKey::forSite('my_key');

    assertSameValue('blog_5_my_key', $key, 'TransientKey::forSite should embed the current blog ID in the key.');
};

$tests['transient_key_prefix_for_site_produces_the_same_prefix'] = static function (): void {
    resetTestState();

    global $currentBlogId;
    $currentBlogId = 1;

    assertSameValue(
        TransientKey::forSite('some_prefix'),
        TransientKey::prefixForSite('some_prefix'),
        'TransientKey::prefixForSite should return the same string as forSite for a given prefix.'
    );
};

// ---------------------------------------------------------------------------
// SiteEnvironment – currentEnvironmentType
// ---------------------------------------------------------------------------

$tests['site_environment_current_type_returns_empty_string_without_wp_function'] = static function (): void {
    // The harness does not define wp_get_environment_type, so
    // currentEnvironmentType() falls through to WP_ENVIRONMENT_TYPE constant.
    // Since WP_ENVIRONMENT_TYPE is not defined in the test harness the method
    // must return an empty string.
    $type = SiteEnvironment::currentEnvironmentType();

    assertSameValue(
        '',
        $type,
        'currentEnvironmentType() should return an empty string when no environment source is defined.'
    );
};

// ---------------------------------------------------------------------------
// FontUtils – normalizeAxisTag
// ---------------------------------------------------------------------------

$tests['font_utils_normalize_axis_tag_uppercases_and_validates_four_char_tags'] = static function (): void {
    assertSameValue('WGHT', FontUtils::normalizeAxisTag('wght'), 'normalizeAxisTag should uppercase a valid 4-char tag.');
    assertSameValue('ITAL', FontUtils::normalizeAxisTag('ITAL'), 'normalizeAxisTag should preserve an already-uppercase tag.');
    assertSameValue('WDTH', FontUtils::normalizeAxisTag(' wdth '), 'normalizeAxisTag should trim whitespace before processing.');
    assertSameValue('', FontUtils::normalizeAxisTag('wg'), 'normalizeAxisTag should reject tags shorter than 4 characters.');
    assertSameValue('', FontUtils::normalizeAxisTag('wghtt'), 'normalizeAxisTag should reject tags longer than 4 characters.');
    assertSameValue('', FontUtils::normalizeAxisTag(''), 'normalizeAxisTag should return empty string for empty input.');
    assertSameValue('', FontUtils::normalizeAxisTag('W!HT'), 'normalizeAxisTag should reject tags containing non-alphanumeric characters.');
};

// ---------------------------------------------------------------------------
// FontUtils – normalizeAxisValue
// ---------------------------------------------------------------------------

$tests['font_utils_normalize_axis_value_accepts_numeric_strings'] = static function (): void {
    assertSameValue('400', FontUtils::normalizeAxisValue('400'), 'normalizeAxisValue should accept integer strings.');
    assertSameValue('3.14', FontUtils::normalizeAxisValue('3.14'), 'normalizeAxisValue should accept decimal strings.');
    assertSameValue('-1', FontUtils::normalizeAxisValue('-1'), 'normalizeAxisValue should accept negative integers.');
    assertSameValue('-0.5', FontUtils::normalizeAxisValue('-0.5'), 'normalizeAxisValue should accept negative decimals.');
};

$tests['font_utils_normalize_axis_value_rejects_non_numeric_strings'] = static function (): void {
    assertSameValue('', FontUtils::normalizeAxisValue('bold'), 'normalizeAxisValue should reject keyword strings.');
    assertSameValue('', FontUtils::normalizeAxisValue(''), 'normalizeAxisValue should return empty string for empty input.');
    assertSameValue('', FontUtils::normalizeAxisValue('1px'), 'normalizeAxisValue should reject values with units.');
    assertSameValue('', FontUtils::normalizeAxisValue([]), 'normalizeAxisValue should return empty string for arrays.');
    assertSameValue('', FontUtils::normalizeAxisValue(null), 'normalizeAxisValue should return empty string for null.');
};

$tests['font_utils_normalize_axis_value_accepts_numeric_non_string_scalars'] = static function (): void {
    assertSameValue('400', FontUtils::normalizeAxisValue(400), 'normalizeAxisValue should accept integer scalars.');
    assertSameValue('1.5', FontUtils::normalizeAxisValue(1.5), 'normalizeAxisValue should accept float scalars.');
};

// ---------------------------------------------------------------------------
// FontUtils – cssAxisTag
// ---------------------------------------------------------------------------

$tests['font_utils_css_axis_tag_maps_known_registered_axes_to_lowercase'] = static function (): void {
    assertSameValue('wght', FontUtils::cssAxisTag('WGHT'), 'cssAxisTag should map WGHT to wght.');
    assertSameValue('wght', FontUtils::cssAxisTag('wght'), 'cssAxisTag should accept lowercase input and map to wght.');
    assertSameValue('wdth', FontUtils::cssAxisTag('WDTH'), 'cssAxisTag should map WDTH to wdth.');
    assertSameValue('slnt', FontUtils::cssAxisTag('SLNT'), 'cssAxisTag should map SLNT to slnt.');
    assertSameValue('ital', FontUtils::cssAxisTag('ITAL'), 'cssAxisTag should map ITAL to ital.');
    assertSameValue('opsz', FontUtils::cssAxisTag('OPSZ'), 'cssAxisTag should map OPSZ to opsz.');
};

$tests['font_utils_css_axis_tag_preserves_custom_four_char_tags'] = static function (): void {
    // Custom axes that are not in the registered list should come back as
    // uppercase 4-char strings (the output of normalizeAxisTag).
    $result = FontUtils::cssAxisTag('GRAD');
    assertSameValue('GRAD', $result, 'cssAxisTag should preserve valid custom axis tags.');
};

$tests['font_utils_css_axis_tag_returns_empty_string_for_invalid_tags'] = static function (): void {
    assertSameValue('', FontUtils::cssAxisTag(''), 'cssAxisTag should return empty string for empty input.');
    assertSameValue('', FontUtils::cssAxisTag('toolong'), 'cssAxisTag should return empty string for tags longer than 4 chars.');
};

// ---------------------------------------------------------------------------
// FontUtils – faceIsVariable / facesHaveVariableMetadata / facesHaveStaticMetadata
// ---------------------------------------------------------------------------

$tests['font_utils_face_is_variable_detects_is_variable_flag'] = static function (): void {
    assertTrueValue(
        FontUtils::faceIsVariable(['is_variable' => true]),
        'faceIsVariable should return true when is_variable flag is set.'
    );
    assertFalseValue(
        FontUtils::faceIsVariable(['is_variable' => false]),
        'faceIsVariable should return false when is_variable flag is false and no axes are present.'
    );
};

$tests['font_utils_face_is_variable_detects_axes_map'] = static function (): void {
    assertTrueValue(
        FontUtils::faceIsVariable(['axes' => ['WGHT' => ['min' => '100', 'default' => '400', 'max' => '900']]]),
        'faceIsVariable should return true when a non-empty axes map is present.'
    );
    assertFalseValue(
        FontUtils::faceIsVariable(['axes' => []]),
        'faceIsVariable should return false when axes map is empty.'
    );
    assertFalseValue(
        FontUtils::faceIsVariable([]),
        'faceIsVariable should return false for a plain static face with no axes.'
    );
};

$tests['font_utils_faces_have_variable_metadata_returns_true_on_first_variable_face'] = static function (): void {
    $faces = [
        ['weight' => '400', 'style' => 'normal'],
        ['weight' => '100..900', 'is_variable' => true],
    ];

    assertTrueValue(
        FontUtils::facesHaveVariableMetadata($faces),
        'facesHaveVariableMetadata should return true when at least one face is variable.'
    );
};

$tests['font_utils_faces_have_variable_metadata_returns_false_for_all_static_faces'] = static function (): void {
    $faces = [
        ['weight' => '400', 'style' => 'normal'],
        ['weight' => '700', 'style' => 'italic'],
    ];

    assertFalseValue(
        FontUtils::facesHaveVariableMetadata($faces),
        'facesHaveVariableMetadata should return false when all faces are static.'
    );
    assertFalseValue(
        FontUtils::facesHaveVariableMetadata([]),
        'facesHaveVariableMetadata should return false for an empty faces list.'
    );
};

$tests['font_utils_faces_have_static_metadata_returns_true_on_first_static_face'] = static function (): void {
    $faces = [
        ['weight' => '400', 'style' => 'normal'],
        ['weight' => '100..900', 'is_variable' => true],
    ];

    assertTrueValue(
        FontUtils::facesHaveStaticMetadata($faces),
        'facesHaveStaticMetadata should return true when at least one face is static.'
    );
};

$tests['font_utils_faces_have_static_metadata_returns_false_for_all_variable_faces'] = static function (): void {
    $faces = [
        ['is_variable' => true, 'axes' => ['WGHT' => ['min' => '100', 'default' => '400', 'max' => '900']]],
    ];

    assertFalseValue(
        FontUtils::facesHaveStaticMetadata($faces),
        'facesHaveStaticMetadata should return false when all faces are variable.'
    );
    assertFalseValue(
        FontUtils::facesHaveStaticMetadata([]),
        'facesHaveStaticMetadata should return false for an empty faces list.'
    );
};

// ---------------------------------------------------------------------------
// FontUtils – resolveProfileFormat
// ---------------------------------------------------------------------------

$tests['font_utils_resolve_profile_format_uses_explicit_format_field'] = static function (): void {
    assertSameValue(
        'static',
        FontUtils::resolveProfileFormat(['format' => 'static']),
        'resolveProfileFormat should honour an explicit static format field.'
    );
    assertSameValue(
        'variable',
        FontUtils::resolveProfileFormat(['format' => 'variable']),
        'resolveProfileFormat should honour an explicit variable format field.'
    );
    assertSameValue(
        'variable',
        FontUtils::resolveProfileFormat(['format' => 'Variable']),
        'resolveProfileFormat should normalize the format field to lowercase.'
    );
};

$tests['font_utils_resolve_profile_format_infers_variable_from_faces'] = static function (): void {
    $profile = [
        'faces' => [
            ['is_variable' => true],
        ],
    ];

    assertSameValue(
        'variable',
        FontUtils::resolveProfileFormat($profile),
        'resolveProfileFormat should infer variable format from variable faces.'
    );
};

$tests['font_utils_resolve_profile_format_defaults_to_static'] = static function (): void {
    $profile = [
        'faces' => [
            ['weight' => '400', 'style' => 'normal'],
        ],
    ];

    assertSameValue(
        'static',
        FontUtils::resolveProfileFormat($profile),
        'resolveProfileFormat should default to static when all faces are static.'
    );
    assertSameValue(
        'static',
        FontUtils::resolveProfileFormat([]),
        'resolveProfileFormat should default to static for an empty profile.'
    );
};

// ---------------------------------------------------------------------------
// FontUtils – resolveFormatAvailability
// ---------------------------------------------------------------------------

$tests['font_utils_resolve_format_availability_uses_explicit_formats_map'] = static function (): void {
    $entry = [
        'formats' => [
            'static' => ['label' => 'Static', 'available' => true, 'source_only' => false],
            'variable' => ['label' => 'Variable', 'available' => true, 'source_only' => true],
        ],
    ];

    $result = FontUtils::resolveFormatAvailability($entry);

    assertArrayHasKeys(['static', 'variable'], $result, 'resolveFormatAvailability should include both format keys when explicitly provided.');
    assertSameValue(true, $result['static']['available'], 'resolveFormatAvailability should preserve the static available flag.');
    assertSameValue(true, $result['variable']['source_only'], 'resolveFormatAvailability should preserve the variable source_only flag.');
};

$tests['font_utils_resolve_format_availability_infers_static_from_static_faces'] = static function (): void {
    $entry = [
        'faces' => [
            ['weight' => '400', 'style' => 'normal'],
        ],
    ];

    $result = FontUtils::resolveFormatAvailability($entry);

    assertArrayHasKeys(['static'], $result, 'resolveFormatAvailability should infer a static format from static faces.');
    assertSameValue(true, $result['static']['available'], 'resolveFormatAvailability should mark the inferred static format as available.');
};

$tests['font_utils_resolve_format_availability_infers_variable_from_variable_flag'] = static function (): void {
    $entry = [
        'has_variable_faces' => true,
    ];

    $result = FontUtils::resolveFormatAvailability($entry);

    assertArrayHasKeys(['variable'], $result, 'resolveFormatAvailability should infer a variable format when has_variable_faces is true.');
    assertSameValue(true, $result['variable']['available'], 'resolveFormatAvailability should mark the inferred variable format as available.');
};

$tests['font_utils_resolve_format_availability_defaults_to_static_for_empty_entry'] = static function (): void {
    $result = FontUtils::resolveFormatAvailability([]);

    assertArrayHasKeys(['static'], $result, 'resolveFormatAvailability should default to a static format for an empty entry.');
    assertSameValue(true, $result['static']['available'], 'resolveFormatAvailability should mark the fallback static format as available.');
};

$tests['font_utils_resolve_format_availability_ignores_unrecognised_format_keys'] = static function (): void {
    $entry = [
        'formats' => [
            'legacy' => ['available' => true],
        ],
    ];

    // Unknown keys should be filtered out so the method falls back to
    // inference from faces / flags.  With no faces or flags present the
    // fallback static format is returned.
    $result = FontUtils::resolveFormatAvailability($entry);

    assertFalseValue(
        array_key_exists('legacy', $result),
        'resolveFormatAvailability should ignore unrecognised format keys.'
    );
};

// ---------------------------------------------------------------------------
// FontUtils – normalizeHostedAxisList
// ---------------------------------------------------------------------------

$tests['font_utils_normalize_hosted_axis_list_builds_axis_records_from_start_end'] = static function (): void {
    $axes = [
        ['tag' => 'wght', 'start' => '100', 'end' => '900', 'default' => '400'],
    ];

    $result = FontUtils::normalizeHostedAxisList($axes);

    assertArrayHasKeys(['WGHT'], $result, 'normalizeHostedAxisList should uppercase the axis tag.');
    assertSameValue('100', $result['WGHT']['min'], 'normalizeHostedAxisList should use start as min.');
    assertSameValue('900', $result['WGHT']['max'], 'normalizeHostedAxisList should use end as max.');
    assertSameValue('400', $result['WGHT']['default'], 'normalizeHostedAxisList should store the supplied default.');
};

$tests['font_utils_normalize_hosted_axis_list_infers_wght_default_when_missing'] = static function (): void {
    $axes = [
        ['tag' => 'wght', 'min' => '200', 'max' => '800'],
    ];

    $result = FontUtils::normalizeHostedAxisList($axes);

    assertSameValue('200', $result['WGHT']['min'], 'min should be preserved when default is absent.');
    assertSameValue('800', $result['WGHT']['max'], 'max should be preserved when default is absent.');
    // When min <= 400 <= max the inferred default should be 400.
    assertSameValue('400', $result['WGHT']['default'], 'normalizeHostedAxisList should infer a default of 400 for WGHT when min <= 400 <= max.');
};

$tests['font_utils_normalize_hosted_axis_list_skips_axes_with_empty_tags'] = static function (): void {
    $axes = [
        ['tag' => '', 'min' => '100', 'max' => '900'],
        ['tag' => 'wght', 'min' => '100', 'max' => '900', 'default' => '400'],
    ];

    $result = FontUtils::normalizeHostedAxisList($axes);

    assertSameValue(1, count($result), 'normalizeHostedAxisList should skip axes with empty tags.');
    assertArrayHasKeys(['WGHT'], $result, 'normalizeHostedAxisList should include axes with valid tags.');
};

$tests['font_utils_normalize_hosted_axis_list_skips_non_array_items'] = static function (): void {
    $axes = [
        'not-an-array',
        ['tag' => 'wdth', 'min' => '75', 'max' => '125', 'default' => '100'],
    ];

    $result = FontUtils::normalizeHostedAxisList($axes);

    assertSameValue(1, count($result), 'normalizeHostedAxisList should skip non-array items in the input list.');
    assertArrayHasKeys(['WDTH'], $result, 'normalizeHostedAxisList should include the valid axis record.');
};

// ---------------------------------------------------------------------------
// FontUtils – resolveFaceUnicodeRange
// ---------------------------------------------------------------------------

$tests['font_utils_resolve_face_unicode_range_returns_empty_for_off_mode'] = static function (): void {
    $face = ['unicode_range' => 'U+0000-00FF'];
    $settings = ['unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_OFF];

    assertSameValue(
        '',
        FontUtils::resolveFaceUnicodeRange($face, $settings),
        'resolveFaceUnicodeRange should return an empty string when mode is off.'
    );
};

$tests['font_utils_resolve_face_unicode_range_returns_latin_basic_preset'] = static function (): void {
    $settings = ['unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_LATIN_BASIC];

    $result = FontUtils::resolveFaceUnicodeRange([], $settings);

    assertSameValue(
        FontUtils::UNICODE_RANGE_PRESET_LATIN_BASIC,
        $result,
        'resolveFaceUnicodeRange should return the latin_basic preset when the mode is latin_basic.'
    );
};

$tests['font_utils_resolve_face_unicode_range_returns_latin_extended_preset'] = static function (): void {
    $settings = ['unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_LATIN_EXTENDED];

    $result = FontUtils::resolveFaceUnicodeRange([], $settings);

    assertSameValue(
        FontUtils::UNICODE_RANGE_PRESET_LATIN_EXTENDED,
        $result,
        'resolveFaceUnicodeRange should return the latin_extended preset when the mode is latin_extended.'
    );
};

$tests['font_utils_resolve_face_unicode_range_uses_custom_value_when_valid'] = static function (): void {
    $settings = [
        'unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_CUSTOM,
        'unicode_range_custom_value' => 'U+0041-005A',
    ];

    $result = FontUtils::resolveFaceUnicodeRange([], $settings);

    assertSameValue(
        'U+0041-005A',
        $result,
        'resolveFaceUnicodeRange should return the normalized custom value when it is valid.'
    );
};

$tests['font_utils_resolve_face_unicode_range_returns_empty_for_invalid_custom_value'] = static function (): void {
    $settings = [
        'unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_CUSTOM,
        'unicode_range_custom_value' => 'latin',
    ];

    assertSameValue(
        '',
        FontUtils::resolveFaceUnicodeRange([], $settings),
        'resolveFaceUnicodeRange should return an empty string when the custom value is invalid.'
    );
};

$tests['font_utils_resolve_face_unicode_range_preserves_face_range_in_preserve_mode'] = static function (): void {
    $face = ['unicode_range' => 'U+0100-024F'];
    $settings = ['unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_PRESERVE];

    assertSameValue(
        'U+0100-024F',
        FontUtils::resolveFaceUnicodeRange($face, $settings),
        'resolveFaceUnicodeRange should pass through the face unicode_range in preserve mode.'
    );
};

$tests['font_utils_resolve_face_unicode_range_returns_empty_string_for_missing_face_range_in_preserve_mode'] = static function (): void {
    $settings = ['unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_PRESERVE];

    assertSameValue(
        '',
        FontUtils::resolveFaceUnicodeRange([], $settings),
        'resolveFaceUnicodeRange should return an empty string when preserve mode is active but the face has no unicode_range.'
    );
};

// ---------------------------------------------------------------------------
// UninstallHandler
// ---------------------------------------------------------------------------

$tests['uninstall_handler_deletes_all_known_option_keys'] = static function (): void {
    resetTestState();

    global $optionDeleted;
    global $uploadBaseDir;

    // Pre-populate some options so the handler has data to clean up.
    update_option(SettingsRepository::OPTION_SETTINGS, ['some' => 'setting']);
    update_option(ImportRepository::OPTION_LIBRARY, []);
    update_option(LogRepository::OPTION_LOG, []);
    update_option(AdminController::LOCAL_ENV_NOTICE_OPTION, []);

    $services = makeServiceGraph();

    $handler = new UninstallHandler(
        ['delete_uploaded_files_on_uninstall' => false, 'block_editor_font_library_sync_enabled' => false],
        $services['storage'],
        $services['block_editor_font_library'],
        $services['developer_tools']
    );

    $handler->run();

    $expectedOptions = [
        SettingsRepository::OPTION_SETTINGS,
        SettingsRepository::OPTION_ROLES,
        SettingsRepository::OPTION_GOOGLE_API_KEY_DATA,
        ImportRepository::OPTION_LIBRARY,
        ImportRepository::OPTION_IMPORTS,
        LogRepository::OPTION_LOG,
        AdminController::LOCAL_ENV_NOTICE_OPTION,
        SettingsRepository::LEGACY_OPTION_SETTINGS,
        SettingsRepository::LEGACY_OPTION_ROLES,
        ImportRepository::LEGACY_OPTION_IMPORTS,
        LogRepository::LEGACY_OPTION_LOG,
    ];

    foreach ($expectedOptions as $key) {
        assertTrueValue(
            in_array($key, $optionDeleted, true),
            'UninstallHandler::run() should delete the option key "' . $key . '".'
        );
    }
};

$tests['uninstall_handler_skips_managed_file_deletion_when_preference_is_off'] = static function (): void {
    resetTestState();

    global $uploadBaseDir;

    $services = makeServiceGraph();

    // Create the root storage directory so Storage::getRoot() returns a path.
    $storage = $services['storage'];
    $root = $storage->getRoot();

    $handler = new UninstallHandler(
        ['delete_uploaded_files_on_uninstall' => false, 'block_editor_font_library_sync_enabled' => false],
        $storage,
        $services['block_editor_font_library'],
        $services['developer_tools']
    );

    $handler->run();

    // When delete_uploaded_files_on_uninstall is false, the root storage
    // directory should not have been removed by the handler.
    if (is_string($root) && $root !== '') {
        assertFalseValue(
            is_string($root) && !is_dir($root) && is_dir(dirname($root)),
            'UninstallHandler should not remove the storage root when delete_uploaded_files_on_uninstall is false.'
        );
    }
};

$tests['uninstall_handler_deletes_managed_files_when_preference_is_on'] = static function (): void {
    resetTestState();

    global $uploadBaseDir;

    $services = makeServiceGraph();
    $storage = $services['storage'];
    $root = $storage->getRoot();

    if (!is_string($root) || $root === '') {
        // Storage not available in this environment – skip the file-deletion assertions.
        return;
    }

    // Write a dummy font file to verify it is removed.
    $dummyFile = rtrim($root, '/') . '/upload/dummy.woff2';
    $storage->ensureDirectory(dirname($dummyFile));
    file_put_contents($dummyFile, 'dummy');

    assertTrueValue(file_exists($dummyFile), 'Dummy font file should exist before uninstall.');

    $handler = new UninstallHandler(
        ['delete_uploaded_files_on_uninstall' => true, 'block_editor_font_library_sync_enabled' => false],
        $storage,
        $services['block_editor_font_library'],
        $services['developer_tools']
    );

    $handler->run();

    assertFalseValue(
        file_exists($dummyFile),
        'UninstallHandler should remove the font storage directory when delete_uploaded_files_on_uninstall is true.'
    );
};

$tests['uninstall_handler_deletes_adobe_project_transient_when_project_id_is_saved'] = static function (): void {
    resetTestState();

    global $transientDeleted;

    $projectId = 'abc123';

    // Seed an adobe project ID in the settings option.
    update_option(SettingsRepository::OPTION_SETTINGS, ['adobe_project_id' => $projectId, 'adobe_enabled' => true]);

    $services = makeServiceGraph();

    $handler = new UninstallHandler(
        ['adobe_project_id' => $projectId],
        $services['storage'],
        $services['block_editor_font_library'],
        $services['developer_tools']
    );

    $handler->run();

    $expectedTransientKey = TransientKey::forSite(AdobeProjectClient::TRANSIENT_PREFIX . md5($projectId));

    assertTrueValue(
        in_array($expectedTransientKey, $transientDeleted, true),
        'UninstallHandler should delete the Adobe project transient during uninstall.'
    );
};

$tests['uninstall_handler_skips_adobe_transient_deletion_when_no_project_id'] = static function (): void {
    resetTestState();

    global $transientDeleted;

    $services = makeServiceGraph();

    $handler = new UninstallHandler(
        ['adobe_project_id' => ''],
        $services['storage'],
        $services['block_editor_font_library'],
        $services['developer_tools']
    );

    // Should not throw even when the project ID is empty.
    $handler->run();

    // The UninstallHandler should not delete any Adobe project transient
    // when adobe_project_id is empty, because the method returns early.
    $adobePrefix = TransientKey::forSite(AdobeProjectClient::TRANSIENT_PREFIX);
    $deletedAdobeTransients = array_filter(
        $transientDeleted,
        static fn (string $key): bool => str_starts_with($key, $adobePrefix)
    );

    assertSameValue(
        [],
        array_values($deletedAdobeTransients),
        'UninstallHandler should not delete any Adobe project transient when no adobe_project_id is present.'
    );
};
