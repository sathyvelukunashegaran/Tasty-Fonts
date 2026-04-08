<?php

declare(strict_types=1);

use TastyFonts\Fonts\FontFilenameParser;
use TastyFonts\Fonts\HostedImportSupport;
use TastyFonts\Support\FontUtils;

// ---------------------------------------------------------------------------
// FontUtils – slugify
// ---------------------------------------------------------------------------

$tests['font_utils_slugifies_family_names_to_lowercase_hyphen_tokens'] = static function (): void {
    assertSameValue('inter-display', FontUtils::slugify('Inter Display'), 'Slugify should lowercase and replace spaces with hyphens.');
    assertSameValue('roboto-slab', FontUtils::slugify('Roboto Slab'), 'Slugify should lowercase and replace spaces.');
    assertSameValue('my-font', FontUtils::slugify('--My Font--'), 'Slugify should trim leading and trailing hyphens.');
    assertSameValue('inter', FontUtils::slugify('   inter   '), 'Slugify should trim whitespace before processing.');
};

$tests['font_utils_slugify_returns_md5_fallback_for_empty_strings'] = static function (): void {
    $slug = FontUtils::slugify('');

    assertTrueValue(str_starts_with($slug, 'font-'), 'Slugify should return a font- prefixed fallback for empty input.');
    assertSameValue(13, strlen($slug), 'Slugify fallback should be "font-" plus 8 hex characters.');
};

$tests['font_utils_slugify_replaces_special_characters_with_hyphens'] = static function (): void {
    assertSameValue('a-b-c', FontUtils::slugify('a/b\\c'), 'Slugify should replace forward and back slashes.');
    assertSameValue('noto-sans', FontUtils::slugify('Noto+Sans'), 'Slugify should replace plus signs.');
};

// ---------------------------------------------------------------------------
// FontUtils – escapeFontFamily
// ---------------------------------------------------------------------------

$tests['font_utils_escape_font_family_handles_plain_names_without_modification'] = static function (): void {
    assertSameValue('Inter', FontUtils::escapeFontFamily('Inter'), 'Plain family names should pass through unmodified.');
};

$tests['font_utils_escape_font_family_escapes_double_quotes_and_backslashes'] = static function (): void {
    assertSameValue('\\"Quoted\\"', FontUtils::escapeFontFamily('"Quoted"'), 'Double quotes in family names should be backslash-escaped.');
    assertSameValue('A\\\\B', FontUtils::escapeFontFamily('A\\B'), 'Backslashes in family names should be doubled.');
};

// ---------------------------------------------------------------------------
// FontUtils – buildFontStack with empty family
// ---------------------------------------------------------------------------

$tests['font_utils_build_font_stack_returns_fallback_when_family_is_empty'] = static function (): void {
    assertSameValue('sans-serif', FontUtils::buildFontStack('', 'sans-serif'), 'Font stack should return only the fallback when the family name is empty.');
    assertSameValue('serif', FontUtils::buildFontStack('   ', 'serif'), 'Font stack should return only the fallback when the family name is whitespace.');
};

// ---------------------------------------------------------------------------
// FontUtils – defaultFallbackForCategory
// ---------------------------------------------------------------------------

$tests['font_utils_default_fallback_for_category_maps_all_known_categories'] = static function (): void {
    assertSameValue('serif', FontUtils::defaultFallbackForCategory('serif'), 'Serif category should map to serif fallback.');
    assertSameValue('serif', FontUtils::defaultFallbackForCategory('SERIF'), 'Category matching should be case-insensitive.');
    assertSameValue('monospace', FontUtils::defaultFallbackForCategory('monospace'), 'Monospace category should map to monospace fallback.');
    assertSameValue('cursive', FontUtils::defaultFallbackForCategory('handwriting'), 'Handwriting category should map to cursive fallback.');
    assertSameValue('cursive', FontUtils::defaultFallbackForCategory('script'), 'Script category should map to cursive fallback.');
    assertSameValue('cursive', FontUtils::defaultFallbackForCategory('cursive'), 'Cursive category should map to cursive fallback.');
    assertSameValue('sans-serif', FontUtils::defaultFallbackForCategory('display'), 'Unknown categories should default to sans-serif.');
    assertSameValue('sans-serif', FontUtils::defaultFallbackForCategory(''), 'Empty category should default to sans-serif.');
};

// ---------------------------------------------------------------------------
// FontUtils – fontVariableName / fontVariableReference
// ---------------------------------------------------------------------------

$tests['font_utils_font_variable_name_produces_css_custom_property_tokens'] = static function (): void {
    assertSameValue('--font-inter', FontUtils::fontVariableName('Inter'), 'Font variable name should use the --font- prefix and slugified family.');
    assertSameValue('--font-jetbrains-mono', FontUtils::fontVariableName('JetBrains Mono'), 'Font variable name should slugify spaces.');
    // slugify('') produces a hash-based fallback so the variable name is never empty.
    assertTrueValue(str_starts_with(FontUtils::fontVariableName(''), '--font-'), 'Font variable name should still produce a --font- prefixed token even for an empty family via the slugify hash fallback.');
};

$tests['font_utils_font_variable_reference_wraps_name_in_var_function'] = static function (): void {
    assertSameValue('var(--font-inter)', FontUtils::fontVariableReference('Inter'), 'Font variable reference should wrap the variable name in var().');
    // Because slugify('') returns a hash-based slug, the reference is also non-empty.
    assertTrueValue(str_starts_with(FontUtils::fontVariableReference(''), 'var(--font-'), 'Font variable reference should produce a var(--font-…) token even for an empty family.');
};

// ---------------------------------------------------------------------------
// FontUtils – weightVariableName / weightSemanticVariableName
// ---------------------------------------------------------------------------

$tests['font_utils_weight_variable_name_builds_numeric_weight_tokens'] = static function (): void {
    assertSameValue('--weight-400', FontUtils::weightVariableName('400'), 'Numeric weight variable should use the --weight- prefix.');
    assertSameValue('--weight-700', FontUtils::weightVariableName('bold'), 'bold keyword should resolve to numeric 700 for the variable name.');
    assertSameValue('--weight-400', FontUtils::weightVariableName('normal'), 'normal keyword should resolve to numeric 400 for the variable name.');
    assertSameValue('', FontUtils::weightVariableName('100..900'), 'Variable-range weights should not produce a numeric weight variable.');
};

$tests['font_utils_weight_semantic_variable_name_builds_human_readable_tokens'] = static function (): void {
    assertSameValue('--weight-regular', FontUtils::weightSemanticVariableName('400'), 'Weight 400 should produce the --weight-regular semantic variable.');
    assertSameValue('--weight-bold', FontUtils::weightSemanticVariableName('700'), 'Weight 700 should produce the --weight-bold semantic variable.');
    assertSameValue('--weight-light', FontUtils::weightSemanticVariableName('300'), 'Weight 300 should produce the --weight-light semantic variable.');
    assertSameValue('--weight-medium', FontUtils::weightSemanticVariableName('500'), 'Weight 500 should produce the --weight-medium semantic variable.');
    assertSameValue('', FontUtils::weightSemanticVariableName('100..900'), 'Variable-range weights should not produce a semantic weight variable.');
};

// ---------------------------------------------------------------------------
// FontUtils – weightVariableReference
// ---------------------------------------------------------------------------

$tests['font_utils_weight_variable_reference_prefers_semantic_names_by_default'] = static function (): void {
    assertSameValue('var(--weight-regular)', FontUtils::weightVariableReference('400'), 'Default weight reference should prefer the semantic name.');
    assertSameValue('var(--weight-bold)', FontUtils::weightVariableReference('700'), 'Bold weight reference should prefer the semantic name.');
    assertSameValue('var(--weight-400)', FontUtils::weightVariableReference('400', false), 'Numeric weight reference should emit the numeric variable when semantic is disabled.');
};

// ---------------------------------------------------------------------------
// FontUtils – variantVariableNames
// ---------------------------------------------------------------------------

$tests['font_utils_variant_variable_names_builds_numeric_and_semantic_tokens'] = static function (): void {
    $names = FontUtils::variantVariableNames('Inter', '400', 'normal');

    assertSameValue('--font-inter', $names['family'], 'Variant variable names should include the family variable token.');
    assertSameValue('--font-inter-400', $names['numeric'], 'Variant variable names should include the numeric face token.');
    assertSameValue('--font-inter-regular', $names['named'], 'Variant variable names should include the semantic face token.');
};

$tests['font_utils_variant_variable_names_appends_italic_suffix'] = static function (): void {
    $names = FontUtils::variantVariableNames('Lora', '700', 'italic');

    assertSameValue('--font-lora-700-italic', $names['numeric'], 'Italic face tokens should append -italic to the numeric name.');
    assertSameValue('--font-lora-bold-italic', $names['named'], 'Italic face tokens should append -italic to the semantic name.');
};

$tests['font_utils_variant_variable_names_returns_hash_based_tokens_for_empty_family'] = static function (): void {
    // slugify('') produces a non-empty hash fallback, so tokens are also non-empty.
    $names = FontUtils::variantVariableNames('', '400', 'normal');

    assertTrueValue(str_starts_with($names['family'], '--font-'), 'Empty family should still produce a --font- prefixed family token via the slugify hash fallback.');
    assertTrueValue(str_starts_with($names['numeric'], '--font-'), 'Empty family should produce a hash-based numeric face token.');
    assertTrueValue(str_starts_with($names['named'], '--font-'), 'Empty family should produce a hash-based named face token.');
};

// ---------------------------------------------------------------------------
// FontUtils – normalizeStyle
// ---------------------------------------------------------------------------

$tests['font_utils_normalize_style_accepts_known_values_and_rejects_others'] = static function (): void {
    assertSameValue('normal', FontUtils::normalizeStyle('normal'), 'normalizeStyle should accept normal.');
    assertSameValue('italic', FontUtils::normalizeStyle('ITALIC'), 'normalizeStyle should normalize case to lowercase.');
    assertSameValue('oblique', FontUtils::normalizeStyle('Oblique'), 'normalizeStyle should accept oblique (case-insensitively).');
    assertSameValue('normal', FontUtils::normalizeStyle('bold'), 'normalizeStyle should reject unknown values and return normal.');
    assertSameValue('normal', FontUtils::normalizeStyle(''), 'normalizeStyle should return normal for an empty string.');
};

// ---------------------------------------------------------------------------
// FontUtils – normalizeWeight
// ---------------------------------------------------------------------------

$tests['font_utils_normalize_weight_accepts_numeric_and_keyword_values'] = static function (): void {
    assertSameValue('400', FontUtils::normalizeWeight('400'), 'normalizeWeight should keep valid numeric string weights.');
    assertSameValue('1', FontUtils::normalizeWeight('1'), 'normalizeWeight should accept the minimum valid weight of 1.');
    assertSameValue('1000', FontUtils::normalizeWeight('1000'), 'normalizeWeight should accept the maximum valid weight of 1000.');
    assertSameValue('400', FontUtils::normalizeWeight('0'), 'normalizeWeight should reject out-of-range weight 0.');
    assertSameValue('400', FontUtils::normalizeWeight('1001'), 'normalizeWeight should reject out-of-range weight 1001.');
    assertSameValue('normal', FontUtils::normalizeWeight('normal'), 'normalizeWeight should accept the normal keyword.');
    assertSameValue('bold', FontUtils::normalizeWeight('bold'), 'normalizeWeight should accept the bold keyword.');
    assertSameValue('bolder', FontUtils::normalizeWeight('bolder'), 'normalizeWeight should accept the bolder keyword.');
    assertSameValue('lighter', FontUtils::normalizeWeight('lighter'), 'normalizeWeight should accept the lighter keyword.');
    assertSameValue('100..900', FontUtils::normalizeWeight('100..900'), 'normalizeWeight should accept variable weight ranges.');
    assertSameValue('400', FontUtils::normalizeWeight(''), 'normalizeWeight should default to 400 for an empty string.');
    assertSameValue('400', FontUtils::normalizeWeight('garbage'), 'normalizeWeight should default to 400 for unrecognized values.');
};

// ---------------------------------------------------------------------------
// FontUtils – weightNameSlug
// ---------------------------------------------------------------------------

$tests['font_utils_weight_name_slug_maps_all_standard_weights'] = static function (): void {
    assertSameValue('thin', FontUtils::weightNameSlug('100'), 'Weight 100 should map to thin.');
    assertSameValue('ultra-light', FontUtils::weightNameSlug('200'), 'Weight 200 should map to ultra-light.');
    assertSameValue('light', FontUtils::weightNameSlug('300'), 'Weight 300 should map to light.');
    assertSameValue('regular', FontUtils::weightNameSlug('400'), 'Weight 400 should map to regular.');
    assertSameValue('regular', FontUtils::weightNameSlug('normal'), 'Weight keyword normal should map to regular.');
    assertSameValue('medium', FontUtils::weightNameSlug('500'), 'Weight 500 should map to medium.');
    assertSameValue('semi-bold', FontUtils::weightNameSlug('600'), 'Weight 600 should map to semi-bold.');
    assertSameValue('bold', FontUtils::weightNameSlug('700'), 'Weight 700 should map to bold.');
    assertSameValue('bold', FontUtils::weightNameSlug('bold'), 'Weight keyword bold should map to bold.');
    assertSameValue('extra-bold', FontUtils::weightNameSlug('800'), 'Weight 800 should map to extra-bold.');
    assertSameValue('black', FontUtils::weightNameSlug('900'), 'Weight 900 should map to black.');
    assertSameValue('extra-black', FontUtils::weightNameSlug('950'), 'Weight 950 should map to extra-black.');
    assertSameValue('ultra-black', FontUtils::weightNameSlug('1000'), 'Weight 1000 should map to ultra-black.');
    assertSameValue('bolder', FontUtils::weightNameSlug('bolder'), 'Weight keyword bolder should map to bolder.');
    assertSameValue('lighter', FontUtils::weightNameSlug('lighter'), 'Weight keyword lighter should map to lighter.');
    assertSameValue('variable-range', FontUtils::weightNameSlug('100..900'), 'Variable-range weights should map to variable-range.');
    assertSameValue('', FontUtils::weightNameSlug('1'), 'Non-standard numeric weights that do not map to a named slug should return an empty string.');
    assertSameValue('', FontUtils::weightNameSlug('450'), 'Intermediate numeric weights without a named slug should return an empty string.');
};

// ---------------------------------------------------------------------------
// FontUtils – variantKey
// ---------------------------------------------------------------------------

$tests['font_utils_variant_key_builds_dedupe_key_from_weight_style_and_range'] = static function (): void {
    assertSameValue('400|normal|none', FontUtils::variantKey('400', 'normal'), 'Variant key without a unicode range should use "none" as the range segment.');
    assertSameValue('700|italic|none', FontUtils::variantKey('700', 'italic'), 'Variant key should normalize the style segment.');
    $rangeKey = FontUtils::variantKey('400', 'normal', 'U+0000-00FF');
    assertTrueValue(str_contains($rangeKey, '400|normal|'), 'Variant key with a unicode range should embed the weight and style segments.');
    assertFalseValue(str_contains($rangeKey, 'none'), 'Variant key with a unicode range should not use the "none" placeholder.');
};

// ---------------------------------------------------------------------------
// FontUtils – weightSortValue
// ---------------------------------------------------------------------------

$tests['font_utils_weight_sort_value_returns_comparable_integers'] = static function (): void {
    assertSameValue(400, FontUtils::weightSortValue('400'), 'Numeric weights should return their integer value for sorting.');
    assertSameValue(700, FontUtils::weightSortValue('bold'), 'The bold keyword should resolve to 700 for sorting.');
    assertSameValue(400, FontUtils::weightSortValue('normal'), 'The normal keyword should resolve to 400 for sorting.');
    assertSameValue(100, FontUtils::weightSortValue('100..900'), 'Variable-range weights should use the lower bound for sort comparison.');
};

// ---------------------------------------------------------------------------
// FontUtils – compactRelativePath
// ---------------------------------------------------------------------------

$tests['font_utils_compact_relative_path_normalizes_storage_paths'] = static function (): void {
    assertSameValue(
        'google/inter/inter-400-normal.woff2',
        FontUtils::compactRelativePath('google/inter/inter-400-normal.woff2'),
        'Well-formed paths should pass through unchanged.'
    );
    assertSameValue(
        'google/inter',
        FontUtils::compactRelativePath('/google/inter/'),
        'compactRelativePath should strip leading and trailing slashes.'
    );
    assertSameValue(
        'google/inter',
        FontUtils::compactRelativePath('google//inter'),
        'compactRelativePath should collapse double slashes by filtering empty segments.'
    );
    assertSameValue(
        'google/my font/font.woff2',
        FontUtils::compactRelativePath('google/my%20font/font.woff2'),
        'compactRelativePath should URL-decode percent-encoded segments.'
    );
};

// ---------------------------------------------------------------------------
// FontUtils – googleVariantToAxis
// ---------------------------------------------------------------------------

$tests['font_utils_google_variant_to_axis_converts_all_token_forms'] = static function (): void {
    assertSameValue(['style' => 'normal', 'weight' => '400'], FontUtils::googleVariantToAxis('regular'), 'The regular token should map to 400 normal.');
    assertSameValue(['style' => 'italic', 'weight' => '400'], FontUtils::googleVariantToAxis('italic'), 'The italic token should map to 400 italic.');
    assertSameValue(['style' => 'normal', 'weight' => '700'], FontUtils::googleVariantToAxis('700'), 'A numeric weight token should map to the given weight and normal style.');
    assertSameValue(['style' => 'italic', 'weight' => '700'], FontUtils::googleVariantToAxis('700italic'), 'A numeric italic token should map to the given weight and italic style.');
    assertSameValue(['style' => 'normal', 'weight' => '100..900'], FontUtils::googleVariantToAxis('100..900'), 'A variable-range token should map to the range weight and normal style.');
    assertSameValue(null, FontUtils::googleVariantToAxis('bogus'), 'Unrecognized tokens should return null.');
    assertSameValue(null, FontUtils::googleVariantToAxis(''), 'Empty strings should return null.');
};

// ---------------------------------------------------------------------------
// FontFilenameParser – additional weight and style patterns
// ---------------------------------------------------------------------------

$tests['font_filename_parser_detects_all_named_weight_suffixes'] = static function (): void {
    $parser = new FontFilenameParser();

    $cases = [
        ['file' => 'Inter-Thin',        'weight' => '100', 'family' => 'Inter'],
        ['file' => 'Inter-ExtraLight',   'weight' => '200', 'family' => 'Inter'],
        ['file' => 'Inter-UltraLight',   'weight' => '200', 'family' => 'Inter'],
        ['file' => 'Lato-Light',         'weight' => '300', 'family' => 'Lato'],
        ['file' => 'Inter-Regular',      'weight' => '400', 'family' => 'Inter'],
        ['file' => 'Inter-Normal',       'weight' => '400', 'family' => 'Inter'],
        ['file' => 'Inter-Book',         'weight' => '400', 'family' => 'Inter'],
        ['file' => 'Nunito-Medium',      'weight' => '500', 'family' => 'Nunito'],
        ['file' => 'Ubuntu-SemiBold',    'weight' => '600', 'family' => 'Ubuntu'],
        ['file' => 'Ubuntu-DemiBold',    'weight' => '600', 'family' => 'Ubuntu'],
        ['file' => 'Ubuntu-Demi',        'weight' => '600', 'family' => 'Ubuntu'],
        ['file' => 'Playfair-Black',     'weight' => '900', 'family' => 'Playfair'],
        ['file' => 'Playfair-Heavy',     'weight' => '900', 'family' => 'Playfair'],
    ];

    foreach ($cases as $case) {
        $parsed = $parser->parse($case['file']);
        assertSameValue($case['weight'], $parsed['weight'], 'Parser should detect weight ' . $case['weight'] . ' from "' . $case['file'] . '".');
        assertSameValue($case['family'], $parsed['family'], 'Parser should extract family from "' . $case['file'] . '".');
    }
};

$tests['font_filename_parser_detects_variable_font_suffix'] = static function (): void {
    $parser = new FontFilenameParser();

    $variable = $parser->parse('InterVariableFont');

    assertTrueValue($variable['is_variable'], 'VariableFont suffix should mark the file as variable.');
    assertSameValue('Inter', $variable['family'], 'Parser should strip the VariableFont suffix from the family name.');
};

$tests['font_filename_parser_detects_wght_bracket_variable_font_marker'] = static function (): void {
    $parser = new FontFilenameParser();

    $variable = $parser->parse('Inter[wght]');

    assertTrueValue($variable['is_variable'], '[wght] marker should mark the file as a variable font.');
};

$tests['font_filename_parser_strips_webfont_suffix'] = static function (): void {
    $parser = new FontFilenameParser();

    $parsed = $parser->parse('Inter-400webfont');

    assertSameValue('Inter', $parsed['family'], 'Parser should remove the webfont suffix from the family name.');
    assertSameValue('400', $parsed['weight'], 'Parser should still detect the weight when a webfont suffix is present.');
};

$tests['font_filename_parser_detects_numeric_only_weight_from_filename'] = static function (): void {
    $parser = new FontFilenameParser();

    $parsed = $parser->parse('Noto-700');

    assertSameValue('Noto', $parsed['family'], 'Parser should remove the numeric weight suffix from the family name.');
    assertSameValue('700', $parsed['weight'], 'Parser should detect a numeric-only weight token in the filename.');
};

// ---------------------------------------------------------------------------
// HostedImportSupport – buildLocalFilename
// ---------------------------------------------------------------------------

$tests['hosted_import_support_builds_local_filenames_from_face_metadata'] = static function (): void {
    assertSameValue(
        'inter-400-normal.woff2',
        HostedImportSupport::buildLocalFilename('Inter', ['weight' => '400', 'style' => 'normal']),
        'buildLocalFilename should produce slugified-family-weight-style.woff2.'
    );
    assertSameValue(
        'roboto-slab-700-italic.woff2',
        HostedImportSupport::buildLocalFilename('Roboto Slab', ['weight' => '700', 'style' => 'italic']),
        'buildLocalFilename should slugify spaces in the family name.'
    );
    assertSameValue(
        'inter-400-normal.woff2',
        HostedImportSupport::buildLocalFilename('Inter', []),
        'buildLocalFilename should default weight to 400 and style to normal when omitted.'
    );
};

// ---------------------------------------------------------------------------
// HostedImportSupport – faceKeyFromVariant / faceKeyFromFace
// ---------------------------------------------------------------------------

$tests['hosted_import_support_derives_face_keys_from_variants_and_face_arrays'] = static function (): void {
    assertSameValue('400|normal', HostedImportSupport::faceKeyFromVariant('regular'), 'faceKeyFromVariant should map "regular" to the 400|normal axis key.');
    assertSameValue('700|italic', HostedImportSupport::faceKeyFromVariant('700italic'), 'faceKeyFromVariant should map "700italic" to the 700|italic axis key.');
    assertSameValue(null, HostedImportSupport::faceKeyFromVariant('bogus'), 'faceKeyFromVariant should return null for unrecognized variant tokens.');

    assertSameValue(
        '400|normal',
        HostedImportSupport::faceKeyFromFace(['weight' => '400', 'style' => 'normal']),
        'faceKeyFromFace should compose the axis key from the face weight and style.'
    );
    assertSameValue(
        '700|italic',
        HostedImportSupport::faceKeyFromFace(['weight' => '700', 'style' => 'italic']),
        'faceKeyFromFace should compose the axis key for bold italic faces.'
    );
};

// ---------------------------------------------------------------------------
// HostedImportSupport – selectPreferredFaces
// ---------------------------------------------------------------------------

$tests['hosted_import_support_select_preferred_faces_filters_by_requested_variants'] = static function (): void {
    $faces = [
        ['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'a.woff2']],
        ['weight' => '700', 'style' => 'normal', 'files' => ['woff2' => 'b.woff2']],
        ['weight' => '400', 'style' => 'italic', 'files' => ['woff2' => 'c.woff2']],
    ];

    $selected = HostedImportSupport::selectPreferredFaces($faces, ['regular']);

    assertSameValue(1, count($selected), 'selectPreferredFaces should keep only the faces that match requested variants.');
    assertSameValue('400', $selected[0]['weight'], 'selectPreferredFaces should keep the matching regular face.');
    assertSameValue('normal', $selected[0]['style'], 'selectPreferredFaces should keep the matching normal style face.');
};

$tests['hosted_import_support_select_preferred_faces_returns_all_when_no_variants_requested'] = static function (): void {
    $faces = [
        ['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'a.woff2']],
        ['weight' => '700', 'style' => 'normal', 'files' => ['woff2' => 'b.woff2']],
    ];

    $selected = HostedImportSupport::selectPreferredFaces($faces, []);

    assertSameValue(2, count($selected), 'selectPreferredFaces should return all faces when no variants are specified.');
};

$tests['hosted_import_support_select_preferred_faces_prefers_faces_without_unicode_range'] = static function (): void {
    $faces = [
        ['weight' => '400', 'style' => 'normal', 'unicode_range' => 'U+0000-00FF', 'files' => ['woff2' => 'latin.woff2']],
        ['weight' => '400', 'style' => 'normal', 'unicode_range' => '', 'files' => ['woff2' => 'full.woff2']],
    ];

    $selected = HostedImportSupport::selectPreferredFaces($faces, []);

    assertSameValue(1, count($selected), 'selectPreferredFaces should deduplicate faces sharing the same axis key.');
    assertSameValue('full.woff2', $selected[0]['files']['woff2'] ?? '', 'selectPreferredFaces should prefer faces without a unicode range over subset-only faces.');
};

// ---------------------------------------------------------------------------
// HostedImportSupport – mergeManifestFaces
// ---------------------------------------------------------------------------

$tests['hosted_import_support_merge_manifest_faces_combines_and_overwrites_by_axis_key'] = static function (): void {
    $existing = [
        ['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'old-400.woff2']],
    ];
    $new = [
        ['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'new-400.woff2']],
        ['weight' => '700', 'style' => 'normal', 'files' => ['woff2' => 'new-700.woff2']],
    ];

    $merged = HostedImportSupport::mergeManifestFaces($existing, $new);

    assertSameValue(2, count($merged), 'mergeManifestFaces should produce one entry per unique axis key.');
    assertSameValue('new-400.woff2', $merged[0]['files']['woff2'] ?? '', 'mergeManifestFaces should allow new faces to overwrite existing faces at the same axis key.');
};

$tests['hosted_import_support_merge_manifest_faces_preserves_existing_faces_not_in_new_set'] = static function (): void {
    $existing = [
        ['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'regular.woff2']],
        ['weight' => '400', 'style' => 'italic', 'files' => ['woff2' => 'italic.woff2']],
    ];
    $new = [
        ['weight' => '700', 'style' => 'normal', 'files' => ['woff2' => 'bold.woff2']],
    ];

    $merged = HostedImportSupport::mergeManifestFaces($existing, $new);

    assertSameValue(3, count($merged), 'mergeManifestFaces should keep all existing faces when new faces do not conflict.');
};
