<?php

declare(strict_types=1);

use TastyFonts\Support\CssVariableNamingService;
use TastyFonts\Support\FontAxisService;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\UnicodeRangeService;
use TastyFonts\Support\VariantTokenService;

$tests['variant_token_service_canonicalizes_tokens'] = static function (): void {
    $service = new VariantTokenService();

    assertSameValue('400', $service->canonicalVariantToken('400'), 'Numeric weight should canonicalize to compact numeric form.');
    assertSameValue('italic', $service->canonicalVariantToken('italic'), 'Italic should remain italic.');
    assertSameValue('700', $service->canonicalVariantToken('700'), 'Already canonical numeric token should pass through.');
    assertSameValue('regular', $service->canonicalVariantToken('normal'), 'Normal should canonicalize to regular.');
    assertSameValue('700italic', $service->canonicalVariantToken('bold italic'), 'Bold italic should canonicalize to 700italic.');
    assertSameValue('', $service->canonicalVariantToken('bogus'), 'Invalid variant should return empty string.');
};

$tests['variant_token_service_converts_google_variants'] = static function (): void {
    $service = new VariantTokenService();

    assertSameValue(['style' => 'normal', 'weight' => '400'], $service->googleVariantToAxis('regular') ?? [], 'Google variant regular should map to normal 400.');
    assertSameValue(['style' => 'normal', 'weight' => '700'], $service->googleVariantToAxis('700') ?? [], 'Google variant 700 should map to normal 700.');
    assertSameValue(['style' => 'italic', 'weight' => '400'], $service->googleVariantToAxis('italic') ?? [], 'Google variant italic should map to italic 400.');
    assertSameValue(['style' => 'italic', 'weight' => '700'], $service->googleVariantToAxis('700italic') ?? [], 'Google variant 700italic should map to italic 700.');
    assertSameValue(['style' => 'normal', 'weight' => '100..900'], $service->googleVariantToAxis('100..900') ?? [], 'Google variant range should map to normal range.');
    assertSameValue(null, $service->googleVariantToAxis('invalid'), 'Invalid variant should return null.');
};

$tests['variant_token_service_normalizes_and_dedupes'] = static function (): void {
    $service = new VariantTokenService();

    $normalized = $service->normalizeVariantTokens(['400', '700', '400']);

    assertSameValue(['400', '700'], $normalized, 'Variant normalization should dedupe and sort tokens.');
};

$tests['variant_token_service_generates_keys'] = static function (): void {
    $service = new VariantTokenService();

    assertSameValue('400|italic|none', $service->variantKey(400, 'italic'), 'Variant key should combine weight, style, and none for empty range.');
    assertSameValue('400|normal|' . md5('latin'), $service->variantKey(400, 'normal', 'latin'), 'Variant key should hash the unicode range when provided.');
    assertSameValue('400|normal', $service->faceAxisKey(400, 'normal'), 'Face axis key should combine weight and style only.');
};

$tests['unicode_range_service_normalizes_modes'] = static function (): void {
    $service = new UnicodeRangeService();

    assertSameValue('off', $service->normalizeMode('off'), 'Off mode should normalize to off.');
    assertSameValue('custom', $service->normalizeMode('custom'), 'Custom mode should normalize to custom.');
    assertSameValue('off', $service->normalizeMode('invalid'), 'Invalid mode should fallback to off.');
    assertSameValue('latin_basic', $service->normalizeMode('latin_basic'), 'Latin basic mode should normalize correctly.');
    assertSameValue('latin_extended', $service->normalizeMode('latin_extended'), 'Latin extended mode should normalize correctly.');
    assertSameValue('preserve', $service->normalizeMode('preserve'), 'Preserve mode should normalize correctly.');
};

$tests['unicode_range_service_normalizes_and_validates_values'] = static function (): void {
    $service = new UnicodeRangeService();

    assertSameValue('U+0020-007E', $service->normalizeValue(' U+0020-007E '), 'Unicode range value should be trimmed and uppercased.');
    assertTrueValue($service->isValid('U+0020-007E'), 'Single valid range should be accepted.');
    assertTrueValue($service->isValid('U+0020-007E,U+00A0-00FF'), 'Multiple valid ranges should be accepted.');
    assertFalseValue($service->isValid('invalid'), 'Free-form text should be rejected.');
    assertFalseValue($service->isValid('U+4??-4FF'), 'Wildcard range tokens should be rejected.');
};

$tests['unicode_range_service_resolves_face_ranges'] = static function (): void {
    $service = new UnicodeRangeService();

    assertSameValue('', $service->resolveFaceRange([], ['unicode_range_mode' => 'off']), 'Off mode should resolve to empty string.');
    assertSameValue(UnicodeRangeService::PRESET_LATIN_BASIC, $service->resolveFaceRange([], ['unicode_range_mode' => 'latin_basic']), 'Latin basic mode should resolve to preset.');
    assertSameValue(UnicodeRangeService::PRESET_LATIN_EXTENDED, $service->resolveFaceRange([], ['unicode_range_mode' => 'latin_extended']), 'Latin extended mode should resolve to preset.');
    assertSameValue('U+0020-007E', $service->resolveFaceRange([], ['unicode_range_mode' => 'custom', 'unicode_range_custom_value' => 'U+0020-007E']), 'Custom mode should resolve to validated custom range.');
    assertSameValue('', $service->resolveFaceRange([], ['unicode_range_mode' => 'custom', 'unicode_range_custom_value' => 'invalid']), 'Invalid custom range should resolve to empty string.');
    assertSameValue('U+0100-024F', $service->resolveFaceRange(['unicode_range' => 'U+0100-024F'], ['unicode_range_mode' => 'preserve']), 'Preserve mode should resolve to face unicode_range.');
};

$tests['font_axis_service_normalizes_tags'] = static function (): void {
    $service = new FontAxisService();

    assertSameValue('WGHT', $service->normalizeAxisTag(' wght '), 'Whitespace should be trimmed and tag uppercased.');
    assertSameValue('', $service->normalizeAxisTag('invalid'), 'Invalid tag should return empty string.');
    assertSameValue('OPSZ', $service->normalizeAxisTag('opsz'), 'Lowercase valid tag should be uppercased.');
};

$tests['font_axis_service_normalizes_axes_map'] = static function (): void {
    $service = new FontAxisService();

    $normalized = $service->normalizeAxesMap(['wght' => ['min' => 100, 'default' => 400, 'max' => 900]]);

    assertArrayHasKeys(['WGHT'], $normalized, 'Normalized axes map should contain the uppercased tag.');
    assertSameValue('100', $normalized['WGHT']['min'], 'Min should be normalized to string.');
    assertSameValue('400', $normalized['WGHT']['default'], 'Default should be normalized to string.');
    assertSameValue('900', $normalized['WGHT']['max'], 'Max should be normalized to string.');
};

$tests['font_axis_service_normalizes_variation_defaults'] = static function (): void {
    $service = new FontAxisService();

    $defaults = $service->normalizeVariationDefaults(['wght' => 400], [['tag' => 'wght', 'min' => 100, 'default' => 400, 'max' => 900]]);

    assertSameValue(['WGHT' => '400'], $defaults, 'Variation defaults should be normalized with axis fallbacks.');
};

$tests['font_axis_service_builds_font_variation_settings'] = static function (): void {
    $service = new FontAxisService();

    assertSameValue('"wght" 700', $service->buildFontVariationSettings(['wght' => 700]), 'Font variation settings should quote tag and include value.');
    assertSameValue('normal', $service->buildFontVariationSettings([]), 'Empty settings should return normal.');
};

$tests['font_axis_service_detects_variable_faces'] = static function (): void {
    $service = new FontAxisService();

    assertTrueValue($service->faceIsVariable(['axes' => ['wght' => ['min' => 100, 'default' => 400, 'max' => 900]]]), 'Face with axes map should be variable.');
    assertFalseValue($service->faceIsVariable(['axes' => []]), 'Face with empty axes should not be variable.');
    assertTrueValue($service->faceIsVariable(['is_variable' => true]), 'Face with is_variable flag should be variable.');
};

$tests['font_axis_service_has_registered_axis_tags_constant'] = static function (): void {
    assertTrueValue(in_array('WGHT', FontAxisService::REGISTERED_AXIS_TAGS, true), 'WGHT should be a registered axis tag.');
    assertTrueValue(in_array('OPSZ', FontAxisService::REGISTERED_AXIS_TAGS, true), 'OPSZ should be a registered axis tag.');
};

$tests['font_axis_service_normalizes_hosted_axis_list'] = static function (): void {
    $service = new FontAxisService();

    $normalized = $service->normalizeHostedAxisList([
        ['tag' => 'wght', 'start' => 100, 'end' => 900],
    ]);

    assertArrayHasKeys(['WGHT'], $normalized, 'Hosted axis list should normalize tag.');
    assertSameValue('100', $normalized['WGHT']['min'], 'Hosted axis start should map to min.');
    assertSameValue('900', $normalized['WGHT']['max'], 'Hosted axis end should map to max.');
};

$tests['font_axis_service_face_level_defaults_filter_registered'] = static function (): void {
    $service = new FontAxisService();

    $defaults = $service->faceLevelVariationDefaults(['wght' => 400, 'XTRA' => 500], ['wght' => ['tag' => 'wght', 'min' => 100, 'default' => 400, 'max' => 900]]);

    assertSameValue(['XTRA' => '500'], $defaults, 'Face level defaults should filter out registered axis tags.');
};

$tests['font_axis_service_normalizes_hosted_axis_list_infers_defaults'] = static function (): void {
    $service = new FontAxisService();

    $withWght = $service->normalizeHostedAxisList([['tag' => 'wght', 'start' => 100, 'end' => 900]]);

    assertSameValue('400', $withWght['WGHT']['default'], 'WGHT default should be inferred as 400 when within range.');

    $withWdth = $service->normalizeHostedAxisList([['tag' => 'wdth', 'start' => 50, 'end' => 150]]);

    assertSameValue('100', $withWdth['WDTH']['default'], 'WDTH default should be inferred as 100 when within range.');

    $outOfRange = $service->normalizeHostedAxisList([['tag' => 'wght', 'start' => 500, 'end' => 900]]);

    assertSameValue('500', $outOfRange['WGHT']['default'], 'WGHT default should fall back to min when preferred is out of range.');
};

$tests['font_utils_proxy_coverage_for_remaining_extracted_methods'] = static function (): void {
    assertSameValue('"wght" 400', FontUtils::buildFontVariationSettings(['wght' => 400]), 'FontUtils proxy for buildFontVariationSettings should work.');
    assertSameValue(['WGHT' => '400'], FontUtils::normalizeVariationDefaults(['wght' => 400]), 'FontUtils proxy for normalizeVariationDefaults should work.');
    assertSameValue('wght', FontUtils::cssAxisTag('wght'), 'FontUtils proxy for cssAxisTag should work.');
    assertSameValue('var(--font-open-sans)', FontUtils::fontVariableReference('Open Sans'), 'FontUtils proxy for fontVariableReference should work.');
    assertSameValue('var(--weight-bold)', FontUtils::weightVariableReference('700', true), 'FontUtils proxy for weightVariableReference should work.');
    assertSameValue('var(--weight-700)', FontUtils::weightVariableReference('700', false), 'FontUtils proxy for weightVariableReference with preferSemantic=false should work.');
    assertSameValue([], FontUtils::faceLevelVariationDefaults(['wght' => 400]), 'FontUtils proxy for faceLevelVariationDefaults should filter registered tags.');
    assertSameValue(['WGHT' => ['min' => '100', 'default' => '400', 'max' => '900']], FontUtils::normalizeHostedAxisList([['tag' => 'wght', 'start' => 100, 'end' => 900]]), 'FontUtils proxy for normalizeHostedAxisList should work.');
};

$tests['css_variable_naming_service_generates_names'] = static function (): void {
    $service = new CssVariableNamingService();

    assertSameValue('--font-open-sans', $service->fontVariableName('Open Sans'), 'Font variable name should slugify and prefix.');
    assertSameValue('var(--font-open-sans)', $service->fontVariableReference('Open Sans'), 'Font variable reference should wrap in var().');
};

$tests['css_variable_naming_service_generates_weight_names'] = static function (): void {
    $service = new CssVariableNamingService();

    assertSameValue('--weight-700', $service->weightVariableName('700'), 'Weight variable name should use numeric value.');
    assertSameValue('--weight-bold', $service->weightSemanticVariableName('700'), 'Semantic weight variable name should use weight slug.');
    assertSameValue('var(--weight-bold)', $service->weightVariableReference('700', true), 'Weight variable reference should prefer semantic by default.');
    assertSameValue('var(--weight-700)', $service->weightVariableReference('700', false), 'Weight variable reference should use numeric when semantic is not preferred.');
};

$tests['css_variable_naming_service_generates_variant_names'] = static function (): void {
    $service = new CssVariableNamingService();

    $names = $service->variantVariableNames('Open Sans', '700', 'normal');

    assertSameValue('--font-open-sans', $names['family'], 'Variant names should include family variable.');
    assertSameValue('--font-open-sans-700', $names['numeric'], 'Numeric variant should use weight number.');
    assertSameValue('--font-open-sans-bold', $names['named'], 'Named variant should use weight slug.');
};

$tests['font_utils_proxies_still_work'] = static function (): void {
    assertSameValue(['style' => 'normal', 'weight' => '400'], FontUtils::googleVariantToAxis('regular') ?? [], 'FontUtils proxy for googleVariantToAxis should work.');
    assertSameValue(['400', '700'], FontUtils::normalizeVariantTokens(['400', '700', '400']), 'FontUtils proxy for normalizeVariantTokens should work.');
    assertSameValue('400|normal|none', FontUtils::variantKey(400, 'normal'), 'FontUtils proxy for variantKey should work.');
    assertSameValue('400|normal', FontUtils::faceAxisKey(400, 'normal'), 'FontUtils proxy for faceAxisKey should work.');
    assertSameValue('off', FontUtils::normalizeUnicodeRangeMode('bogus'), 'FontUtils proxy for normalizeUnicodeRangeMode should work.');
    assertSameValue('U+0020-007E', FontUtils::normalizeUnicodeRangeValue(' u+0020-007e '), 'FontUtils proxy for normalizeUnicodeRangeValue should work.');
    assertTrueValue(FontUtils::unicodeRangeValueIsValid('U+0020-007E'), 'FontUtils proxy for unicodeRangeValueIsValid should work.');
    assertSameValue('', FontUtils::resolveFaceUnicodeRange([], ['unicode_range_mode' => 'off']), 'FontUtils proxy for resolveFaceUnicodeRange should work.');
    assertSameValue('WGHT', FontUtils::normalizeAxisTag('wght'), 'FontUtils proxy for normalizeAxisTag should work.');
    assertSameValue('"wght" 700', FontUtils::buildFontVariationSettings(['wght' => 700]), 'FontUtils proxy for buildFontVariationSettings should work.');
    assertTrueValue(FontUtils::faceIsVariable(['axes' => ['wght' => ['min' => 100, 'default' => 400, 'max' => 900]]]), 'FontUtils proxy for faceIsVariable should work.');
    assertSameValue('--font-open-sans', FontUtils::fontVariableName('Open Sans'), 'FontUtils proxy for fontVariableName should work.');
    assertSameValue('--weight-700', FontUtils::weightVariableName('700'), 'FontUtils proxy for weightVariableName should work.');
    assertSameValue(['family' => '--font-open-sans', 'numeric' => '--font-open-sans-700', 'named' => '--font-open-sans-bold'], FontUtils::variantVariableNames('Open Sans', '700', 'normal'), 'FontUtils proxy for variantVariableNames should work.');
};

$tests['font_utils_unicode_constants_reference_service'] = static function (): void {
    assertSameValue(UnicodeRangeService::MODE_OFF, FontUtils::UNICODE_RANGE_MODE_OFF, 'FontUtils constant should reference UnicodeRangeService constant.');
    assertSameValue(UnicodeRangeService::MODE_CUSTOM, FontUtils::UNICODE_RANGE_MODE_CUSTOM, 'FontUtils custom constant should reference UnicodeRangeService constant.');
    assertSameValue(UnicodeRangeService::PRESET_LATIN_BASIC, FontUtils::UNICODE_RANGE_PRESET_LATIN_BASIC, 'FontUtils preset constant should reference UnicodeRangeService constant.');
};
