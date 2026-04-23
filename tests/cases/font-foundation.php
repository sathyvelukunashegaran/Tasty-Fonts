<?php

declare(strict_types=1);

use TastyFonts\Adobe\AdobeCssParser;
use TastyFonts\Bunny\BunnyCssParser;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Fonts\FontFilenameParser;
use TastyFonts\Fonts\HostedImportSupport;
use TastyFonts\Google\GoogleCssParser;
use TastyFonts\Support\FontUtils;

$tests['font_filename_parser_detects_weight_and_style'] = static function (): void {
    $parser = new FontFilenameParser();
    $parsed = $parser->parse('Inter-ExtraBoldItalic');

    assertSameValue('Inter', $parsed['family'], 'Parser should remove suffixes from the family name.');
    assertSameValue('800', $parsed['weight'], 'Parser should detect extra-bold weight.');
    assertSameValue('italic', $parsed['style'], 'Parser should detect italic style.');
    assertSameValue(false, $parsed['is_variable'], 'Static fonts should not be marked variable.');
};

$tests['font_filename_parser_preserves_oblique_style'] = static function (): void {
    $parser = new FontFilenameParser();
    $parsed = $parser->parse('Satoshi-500Oblique');

    assertSameValue('Satoshi', $parsed['family'], 'Parser should keep the family name when reading oblique files.');
    assertSameValue('500', $parsed['weight'], 'Parser should detect the numeric weight for oblique files.');
    assertSameValue('oblique', $parsed['style'], 'Parser should preserve oblique instead of collapsing it to italic.');
};

$tests['font_utils_builds_static_upload_filename'] = static function (): void {
    $filename = FontUtils::buildStaticFontFilename('Satoshi Display', '700', 'italic', 'woff2');

    assertSameValue('Satoshi Display-700-italic.woff2', $filename, 'Uploaded static files should use deterministic scanner-friendly filenames.');
};

$tests['font_utils_normalizes_google_variants'] = static function (): void {
    $variants = FontUtils::normalizeVariantTokens(['700italic', 'regular', '700italic', 'bogus']);

    assertSameValue(['regular', '700italic'], $variants, 'Variant normalization should dedupe and discard unsupported tokens.');
};

$tests['font_utils_normalizes_google_variant_aliases'] = static function (): void {
    $variants = FontUtils::normalizeVariantTokens(['bold', 'extra-bold italic', 'book', 'bogus']);

    assertSameValue(['regular', '700', '800italic'], $variants, 'Variant normalization should canonicalize named weight aliases into the supported token set.');
};

$tests['font_utils_normalizes_and_validates_custom_unicode_ranges'] = static function (): void {
    assertSameValue(FontUtils::UNICODE_RANGE_MODE_OFF, FontUtils::normalizeUnicodeRangeMode('bogus'), 'Unsupported unicode-range modes should normalize back to off.');
    assertSameValue('U+0000-00FF,U+0100-024F,U+4??', FontUtils::normalizeUnicodeRangeValue(' u+0000-00ff, u+0100-024f , u+4?? '), 'Unicode-range normalization should uppercase values and collapse comma spacing.');
    assertTrueValue(FontUtils::unicodeRangeValueIsValid('U+0000-00FF,U+0100-024F,U+4??'), 'Custom unicode-range validation should accept codepoints, ranges, and wildcard tokens.');
    assertFalseValue(FontUtils::unicodeRangeValueIsValid('latin'), 'Custom unicode-range validation should reject free-form text.');
    assertFalseValue(FontUtils::unicodeRangeValueIsValid('U+4??-4FF'), 'Custom unicode-range validation should reject wildcard range tokens.');
};

$tests['font_utils_preserves_custom_fallback_stacks'] = static function (): void {
    $fallback = FontUtils::sanitizeFallback('-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif');
    $stack = FontUtils::buildFontStack('Inter', $fallback);

    assertSameValue('-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif', $fallback, 'Fallback sanitizer should allow custom browser/system font stacks.');
    assertSameValue('"Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif', $stack, 'Font stack builder should preserve sanitized custom fallback stacks.');
};

$tests['font_utils_builds_face_axis_keys'] = static function (): void {
    $axisKey = FontUtils::faceAxisKey(400, 'ITALIC');

    assertSameValue('400|italic', $axisKey, 'Face axis keys should normalize weight and style before composing the dedupe key.');
};

$tests['font_utils_detects_remote_urls'] = static function (): void {
    assertSameValue(true, FontUtils::isRemoteUrl('https://example.com/fonts/inter.woff2'), 'Remote URL detection should recognize HTTPS URLs.');
    assertSameValue(true, FontUtils::isRemoteUrl('//fonts.bunny.net/inter.woff2'), 'Remote URL detection should recognize protocol-relative CDN URLs.');
    assertSameValue(false, FontUtils::isRemoteUrl('google/inter/inter-400-normal.woff2'), 'Remote URL detection should treat relative storage paths as local.');
};

$tests['font_utils_normalizes_string_keyed_maps_and_lists'] = static function (): void {
    assertSameValue(
        ['family' => 'Inter', 'provider' => ['name' => 'google']],
        FontUtils::normalizeStringKeyedMap(['family' => 'Inter', 0 => 'skip', 'provider' => ['name' => 'google']]),
        'String-keyed map normalization should discard non-string keys while preserving nested values.'
    );

    assertSameValue(
        [
            ['family' => 'Inter'],
            ['family' => 'Satoshi', 'weight' => '700'],
        ],
        FontUtils::normalizeListOfStringKeyedMaps([
            ['family' => 'Inter', 0 => 'skip'],
            'ignore',
            ['family' => 'Satoshi', 'weight' => '700'],
            [0 => 'skip-only'],
        ]),
        'List normalization should keep only rows that still have string-keyed data after normalization.'
    );
};

$tests['font_utils_normalizes_face_lists_and_scalar_floats'] = static function (): void {
    assertSameValue(
        [
            ['family' => 'Inter', 'weight' => '400'],
            ['family' => 'Satoshi', 'weight' => '700'],
        ],
        FontUtils::normalizeFaceList([
            ['family' => 'Inter', 'weight' => '400', 0 => 'skip'],
            'ignore',
            ['family' => 'Satoshi', 'weight' => '700'],
        ]),
        'Face-list normalization should reuse the shared string-keyed list contract.'
    );

    assertSameValue(1.5, FontUtils::scalarFloatValue('1.5'), 'scalarFloatValue should convert numeric strings to floats.');
    assertSameValue(2.0, FontUtils::scalarFloatValue(2), 'scalarFloatValue should accept integer values.');
    assertSameValue(3.25, FontUtils::scalarFloatValue('bogus', 3.25), 'scalarFloatValue should fall back to the provided default for non-numeric input.');
};

$tests['hosted_import_support_builds_variants_from_faces'] = static function (): void {
    $variants = HostedImportSupport::variantsFromFaces([
        ['weight' => '400', 'style' => 'normal'],
        ['weight' => '700', 'style' => 'normal'],
        ['weight' => '400', 'style' => 'italic'],
        ['weight' => '700', 'style' => 'italic'],
        ['weight' => '700', 'style' => 'italic'],
        'invalid-face',
    ]);

    assertSameValue(
        ['regular', '700', 'italic', '700italic'],
        $variants,
        'Hosted face variant synthesis should mirror the existing catalog and library token format exactly.'
    );
};

$tests['font_utils_compares_faces_by_weight_and_style'] = static function (): void {
    $faces = [
        ['weight' => '700', 'style' => 'normal'],
        ['weight' => '400', 'style' => 'normal'],
        ['weight' => '400', 'style' => 'italic'],
    ];

    usort($faces, [FontUtils::class, 'compareFacesByWeightAndStyle']);

    assertSameValue(
        [
            ['weight' => '400', 'style' => 'italic'],
            ['weight' => '400', 'style' => 'normal'],
            ['weight' => '700', 'style' => 'normal'],
        ],
        $faces,
        'Face sorting should remain stable across shared catalog/import comparators.'
    );
};

$tests['google_css_parser_extracts_woff2_faces_and_unicode_ranges'] = static function (): void {
    $css = <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/inter/v18/u-4k0qWljRw-PfU81xCK.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Inter';
  font-style: italic;
  font-weight: 700;
  src: url(https://fonts.gstatic.com/s/inter/v18/u-4i0qWljRw.woff2) format('woff2');
  unicode-range: U+0100-024F;
}
CSS;

    $parser = new GoogleCssParser();
    $faces = $parser->parse($css, 'Inter');

    assertSameValue(2, count($faces), 'Google CSS parser should return one face per @font-face block.');
    assertSameValue('400', $faces[0]['weight'], 'Google CSS parser should capture the weight.');
    assertSameValue('italic', $faces[1]['style'], 'Google CSS parser should capture style.');
    assertSameValue('U+0100-024F', $faces[1]['unicode_range'], 'Google CSS parser should preserve unicode-range.');
    assertSameValue('https://fonts.gstatic.com/s/inter/v18/u-4k0qWljRw-PfU81xCK.woff2', $faces[0]['files']['woff2'], 'Google CSS parser should keep the remote WOFF2 URL.');
};

$tests['bunny_css_parser_extracts_woff2_faces_and_unicode_ranges'] = static function (): void {
    $css = <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Inter';
  font-style: italic;
  font-weight: 700;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-700-italic.woff2) format('woff2');
  unicode-range: U+0100-024F;
}
CSS;

    $parser = new BunnyCssParser();
    $faces = $parser->parse($css, 'Inter');

    assertSameValue(2, count($faces), 'Bunny CSS parser should return one face per @font-face block.');
    assertSameValue('bunny', $faces[0]['source'], 'Bunny CSS parser should tag parsed faces with the bunny source.');
    assertSameValue('italic', $faces[1]['style'], 'Bunny CSS parser should capture style.');
    assertSameValue('U+0100-024F', $faces[1]['unicode_range'], 'Bunny CSS parser should preserve unicode-range.');
    assertSameValue('https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2', $faces[0]['files']['woff2'], 'Bunny CSS parser should keep the remote WOFF2 URL.');
};

$tests['adobe_css_parser_groups_families_and_dedupes_faces'] = static function (): void {
    $css = <<<'CSS'
@font-face {
  font-family: "ff-tisa-web-pro";
  font-style: normal;
  font-weight: 400;
  src: url("https://use.typekit.net/af/abc123/000000000000000000000000/30/l?primer=1") format("woff2");
}
@font-face {
  font-family: "ff-tisa-web-pro";
  font-style: normal;
  font-weight: 400;
  src: url("https://use.typekit.net/af/duplicate/000000000000000000000000/30/l?primer=1") format("woff2");
}
@font-face {
  font-family: "mr-eaves-xl-modern";
  font-style: italic;
  font-weight: 700;
  src: url("https://use.typekit.net/af/def456/000000000000000000000000/30/l?primer=1") format("woff2");
}
CSS;

    $parser = new AdobeCssParser();
    $families = $parser->parseFamilies($css);

    assertSameValue(2, count($families), 'Adobe CSS parser should return one entry per unique family.');
    assertSameValue('ff-tisa-web-pro', $families[0]['family'], 'Adobe CSS parser should preserve CSS family names for the project.');
    assertSameValue(1, count($families[0]['faces']), 'Adobe CSS parser should dedupe duplicate axis pairs.');
    assertSameValue('700', $families[1]['faces'][0]['weight'], 'Adobe CSS parser should capture weight from @font-face blocks.');
    assertSameValue('italic', $families[1]['faces'][0]['style'], 'Adobe CSS parser should capture style from @font-face blocks.');
};

$tests['font_utils_modern_user_agent_tracks_a_recent_chrome_release'] = static function (): void {
    $matched = preg_match('/Chrome\/(\d+)\./', FontUtils::MODERN_USER_AGENT, $matches);

    assertSameValue(1, $matched, 'The modern browser user agent should advertise a Chrome version.');
    assertSameValue(true, ((int) ($matches[1] ?? 0)) >= 130, 'The modern browser user agent should stay recent enough to trigger Google Fonts CSS2 WOFF2 responses.');
};

$tests['css_builder_emits_alias_and_category_classes_when_enabled'] = static function (): void {
    $builder = new CssBuilder();
    $roles = [
        'heading' => 'Lora',
        'body' => 'Inter',
        'monospace' => 'JetBrains Mono',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
    ];
    $families = [
        ['family' => 'Lora', 'font_category' => 'serif'],
        ['family' => 'Inter', 'font_category' => 'sans-serif'],
        ['family' => 'JetBrains Mono', 'font_category' => 'monospace'],
    ];
    $settings = ['class_output_enabled' => true];

    $css = $builder->buildClassOutputSnippet($roles, true, $families, $settings);

    assertContainsValue('.font-interface {', $css, 'Class output should emit the interface alias class when enabled.');
    assertContainsValue('.font-ui {', $css, 'Class output should emit the UI alias class when enabled.');
    assertContainsValue('.font-code {', $css, 'Class output should emit the code alias class when enabled.');
    assertContainsValue('.font-sans {', $css, 'Class output should emit the sans category class when enabled.');
    assertContainsValue('.font-serif {', $css, 'Class output should emit the serif category class when enabled.');
    assertContainsValue('.font-mono {', $css, 'Class output should emit the mono category class when enabled.');
};

$tests['css_builder_suppresses_only_the_targeted_class_output_flags'] = static function (): void {
    $builder = new CssBuilder();
    $roles = [
        'heading' => 'Lora',
        'body' => 'Inter',
        'monospace' => 'JetBrains Mono',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
    ];
    $families = [
        ['family' => 'Lora', 'font_category' => 'serif'],
        ['family' => 'Inter', 'font_category' => 'sans-serif'],
        ['family' => 'JetBrains Mono', 'font_category' => 'monospace'],
    ];
    $settings = [
        'class_output_enabled' => true,
        'class_output_role_alias_ui_enabled' => false,
        'class_output_category_serif_enabled' => false,
        'class_output_families_enabled' => true,
    ];

    $css = $builder->buildClassOutputSnippet($roles, true, $families, $settings);

    assertContainsValue('.font-interface {', $css, 'Disabling one alias class should not suppress the other alias classes.');
    assertNotContainsValue('.font-ui {', $css, 'Disabling the UI alias class should suppress only that selector.');
    assertNotContainsValue('.font-serif {', $css, 'Disabling the serif category class should suppress only that selector.');
    assertContainsValue('.font-sans {', $css, 'Disabling one category class should not suppress the other category classes.');
    assertContainsValue('.font-lora {', $css, 'Disabling specific class groups should not suppress family classes.');
};

$tests['css_builder_honors_legacy_class_output_mode_migration'] = static function (): void {
    $builder = new CssBuilder();
    $roles = [
        'heading' => 'Lora',
        'body' => 'Inter',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
    ];
    $families = [
        ['family' => 'Lora', 'font_category' => 'serif'],
        ['family' => 'Inter', 'font_category' => 'sans-serif'],
    ];
    $settings = [
        'class_output_enabled' => true,
        'class_output_role_heading_enabled' => false,
        'class_output_role_body_enabled' => false,
        'class_output_role_monospace_enabled' => false,
        'class_output_role_alias_interface_enabled' => false,
        'class_output_role_alias_ui_enabled' => false,
        'class_output_role_alias_code_enabled' => false,
        'class_output_category_sans_enabled' => false,
        'class_output_category_serif_enabled' => false,
        'class_output_category_mono_enabled' => false,
        'class_output_families_enabled' => true,
    ];

    $css = $builder->buildClassOutputSnippet($roles, false, $families, $settings);

    assertNotContainsValue('.font-heading {', $css, 'Migrated legacy families mode should suppress role classes.');
    assertContainsValue('.font-lora {', $css, 'Migrated legacy families mode should still emit family classes.');
    assertContainsValue('.font-inter {', $css, 'Migrated legacy families mode should still emit all family classes.');
};

$tests['css_builder_omits_mono_and_code_classes_when_monospace_role_is_disabled'] = static function (): void {
    $builder = new CssBuilder();
    $roles = [
        'heading' => 'Lora',
        'body' => 'Inter',
        'monospace' => 'JetBrains Mono',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
    ];
    $families = [
        ['family' => 'Lora', 'font_category' => 'serif'],
        ['family' => 'Inter', 'font_category' => 'sans-serif'],
        ['family' => 'JetBrains Mono', 'font_category' => 'monospace'],
    ];
    $settings = ['class_output_enabled' => true];

    $css = $builder->buildClassOutputSnippet($roles, false, $families, $settings);

    assertNotContainsValue('.font-monospace {', $css, 'Class output should not emit the monospace role class when the monospace role is disabled.');
    assertNotContainsValue('.font-code {', $css, 'Class output should not emit the code alias class when the monospace role is disabled.');
    assertNotContainsValue('.font-mono {', $css, 'Class output should not emit the mono category class when the monospace role is disabled.');
};
