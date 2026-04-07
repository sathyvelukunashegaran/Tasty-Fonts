<?php

declare(strict_types=1);

use TastyFonts\Adobe\AdobeCssParser;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Fonts\FontFilenameParser;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\Storage;

$tests['css_builder_generates_font_face_and_role_variables'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                    ],
                ],
            ],
        ],
    ];
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'serif',
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'per_variant_font_variables_enabled' => true,
    ];

    $css = $builder->build($catalog, $roles, $settings, $catalog);

    assertContainsValue('@font-face', $css, 'CSS builder should emit @font-face rules.');
    assertContainsValue('font-family:"Inter"', $css, 'CSS builder should include the family name.');
    assertContainsValue('--font-inter: "Inter", sans-serif;', $css, 'CSS builder should emit the family variable stack.');
    assertContainsValue('--font-heading', $css, 'CSS builder should emit the heading role variable.');
    assertContainsValue('font-family: var(--font-body);', $css, 'CSS builder should emit the body usage rule.');
    assertContainsValue('font-weight: var(--weight-regular);', $css, 'CSS builder should emit the shared regular weight token in the body rule.');
};

$tests['css_builder_builds_role_class_snippets_when_class_output_is_enabled'] = static function (): void {
    $builder = new CssBuilder();
    $roles = [
        'heading' => 'Inter',
        'body' => '',
        'monospace' => '',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
    ];
    $settings = [
        'auto_apply_roles' => true,
        'class_output_enabled' => true,
        'class_output_families_enabled' => false,
    ];

    $css = $builder->buildRoleClassSnippet($roles, true, $settings);

    assertContainsValue(".font-heading {\n  font-family: \"Inter\", serif;\n}", $css, 'Role class output should emit the heading class with the resolved font stack.');
    assertContainsValue(".font-body {\n  font-family: sans-serif;\n}", $css, 'Role class output should emit fallback-only body classes when no family is selected.');
    assertContainsValue(".font-monospace {\n  font-family: monospace;\n}", $css, 'Role class output should emit the optional monospace class when the feature is enabled.');
};

$tests['css_builder_emits_role_class_snippets_from_draft_roles_when_apply_sitewide_is_off'] = static function (): void {
    $builder = new CssBuilder();
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];

    $css = $builder->buildRoleClassSnippet($roles, false, ['auto_apply_roles' => false, 'class_output_enabled' => true]);

    assertContainsValue('.font-heading', $css, 'Role classes should still be emitted from the saved role draft while Apply Sitewide is off.');
    assertContainsValue('.font-body', $css, 'Role classes should still include the body role while Apply Sitewide is off.');
};

$tests['css_builder_builds_family_class_snippets_for_runtime_visible_families'] = static function (): void {
    $builder = new CssBuilder();
    $families = [
        'Inter' => [
            'family' => 'Inter',
            'font_category' => 'sans-serif',
        ],
        'JetBrains Mono' => [
            'family' => 'JetBrains Mono',
            'font_category' => 'monospace',
        ],
        'Inter Duplicate' => [
            'family' => 'Inter!!!',
            'font_category' => 'sans-serif',
        ],
    ];
    $settings = [
        'class_output_enabled' => true,
        'class_output_families_enabled' => true,
        'family_fallbacks' => ['Inter' => 'system-ui, sans-serif'],
    ];

    $css = $builder->buildFamilyClassSnippet($families, $settings);

    assertContainsValue(".font-inter {\n  font-family: \"Inter\", system-ui, sans-serif;\n}", $css, 'Family class output should use saved per-family fallbacks.');
    assertContainsValue(".font-jetbrains-mono {\n  font-family: \"JetBrains Mono\", monospace;\n}", $css, 'Family class output should infer category-aware fallback stacks.');
    assertSameValue(1, substr_count($css, '.font-inter {'), 'Family class output should avoid duplicate selectors when slugs collide.');
};

$tests['css_builder_builds_combined_class_output_snippets'] = static function (): void {
    $builder = new CssBuilder();
    $roles = [
        'heading' => 'Lora',
        'body' => 'Inter',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
    ];
    $families = [
        'Inter' => [
            'family' => 'Inter',
            'font_category' => 'sans-serif',
        ],
        'Lora' => [
            'family' => 'Lora',
            'font_category' => 'serif',
        ],
    ];
    $settings = [
        'auto_apply_roles' => true,
        'class_output_enabled' => true,
    ];

    $css = $builder->buildClassOutputSnippet($roles, false, $families, $settings);

    assertContainsValue('.font-heading', $css, 'Combined class output should include role classes.');
    assertContainsValue('.font-inter', $css, 'Combined class output should include family classes.');
    assertContainsValue("\n\n.font-inter", $css, 'Combined class output should separate role and family blocks with a blank line.');
};

$tests['css_builder_includes_class_output_in_generated_css_when_enabled'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'faces' => [[
                'family' => 'Inter',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'https://example.com/fonts/inter.woff2'],
            ]],
        ],
    ];
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => true,
        'minify_css_output' => false,
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

    $css = $builder->build($catalog, $roles, $settings, $catalog);

    assertContainsValue('@font-face', $css, 'Generated CSS should continue to include font faces when class output is enabled.');
    assertContainsValue(".font-inter {\n  font-family: \"Inter\", sans-serif;\n}", $css, 'Generated CSS should append family classes when class output mode enables them.');
};

$tests['css_builder_includes_role_class_output_in_generated_css_when_enabled'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'faces' => [[
                'family' => 'Inter',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'https://example.com/fonts/inter.woff2'],
            ]],
        ],
    ];
    $roles = [
        'heading' => 'Inter',
        'body' => '',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'class_output_enabled' => true,
        'class_output_families_enabled' => false,
    ];

    $css = $builder->build($catalog, $roles, $settings, $catalog);

    assertContainsValue('@font-face', $css, 'Generated CSS should continue to include font faces when role class output is enabled.');
    assertContainsValue(".font-heading {\n  font-family: \"Inter\", serif;\n}", $css, 'Generated CSS should append the heading role class when role class output mode is enabled.');
    assertContainsValue(".font-body {\n  font-family: sans-serif;\n}", $css, 'Generated CSS should append the body role class when role class output mode is enabled.');
};

$tests['css_builder_omits_class_output_in_generated_css_when_disabled'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'font_category' => 'sans-serif',
            'faces' => [[
                'family' => 'Inter',
                'weight' => '400',
                'style' => 'normal',
            ]],
        ],
    ];
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];

    $css = $builder->build([], $roles, ['auto_apply_roles' => true, 'minify_css_output' => false, 'class_output_enabled' => false], $catalog);

    assertNotContainsValue('.font-heading', $css, 'Generated CSS should not include role classes when class output mode is off.');
    assertNotContainsValue('.font-inter', $css, 'Generated CSS should not include family classes when class output mode is off.');
};

$tests['css_builder_emits_global_weight_tokens_and_semantic_font_aliases_when_enabled'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'font_category' => 'sans-serif',
            'faces' => [
                [
                    'family' => 'Inter',
                    'weight' => '400',
                    'style' => 'normal',
                ],
                [
                    'family' => 'Inter',
                    'weight' => '700',
                    'style' => 'italic',
                ],
            ],
        ],
        'Lora' => [
            'family' => 'Lora',
            'slug' => 'lora',
            'font_category' => 'serif',
            'faces' => [
                [
                    'family' => 'Lora',
                    'weight' => '400',
                    'style' => 'normal',
                ],
            ],
        ],
    ];
    $roles = [
        'heading' => 'Lora',
        'body' => 'Inter',
        'heading_fallback' => 'serif',
        'body_fallback' => 'serif',
    ];
    $settings = [
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'per_variant_font_variables_enabled' => true,
        'family_fallbacks' => ['Inter' => 'system-ui, sans-serif'],
    ];

    $css = $builder->build([], $roles, $settings, $catalog);

    assertContainsValue('--font-inter: "Inter", system-ui, sans-serif;', $css, 'Family variables should respect the saved per-family fallback.');
    assertContainsValue('--font-sans: var(--font-inter);', $css, 'Extended output should expose the active sans family alias.');
    assertContainsValue('--font-serif: var(--font-lora);', $css, 'Extended output should expose the active serif family alias.');
    assertContainsValue('--font-interface: var(--font-body);', $css, 'Extended output should expose the interface alias.');
    assertContainsValue('--font-ui: var(--font-body);', $css, 'Extended output should expose the UI alias.');
    assertContainsValue('--weight-400: 400;', $css, 'Extended output should emit the concrete numeric weight token.');
    assertContainsValue('--weight-regular: var(--weight-400);', $css, 'Extended output should emit the semantic regular alias.');
    assertContainsValue('--weight-700: 700;', $css, 'Extended output should emit imported bold weights as numeric tokens.');
    assertContainsValue('--weight-bold: var(--weight-700);', $css, 'Extended output should emit the semantic bold alias.');
    assertNotContainsValue('--font-inter-400', $css, 'Extended output should replace per-family numeric variant variables.');
    assertNotContainsValue('--font-inter-regular', $css, 'Extended output should replace per-family semantic variant variables.');
};

$tests['css_builder_can_emit_runtime_weight_variables_without_local_font_faces'] = static function (): void {
    $builder = new CssBuilder();
    $variableFamilies = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'font_category' => 'sans-serif',
            'active_delivery' => [
                'provider' => 'google',
                'type' => 'cdn',
            ],
            'faces' => [
                [
                    'family' => 'Inter',
                    'weight' => '400',
                    'style' => 'normal',
                ],
                [
                    'family' => 'Inter',
                    'weight' => '500',
                    'style' => 'italic',
                ],
            ],
        ],
    ];
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $settings = [
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'per_variant_font_variables_enabled' => true,
    ];

    $css = $builder->build([], $roles, $settings, $variableFamilies);

    assertNotContainsValue('@font-face', $css, 'Variable-only runtime families should not synthesize @font-face rules when the delivery is remote.');
    assertContainsValue('--weight-400: 400;', $css, 'Runtime variable sources should still emit global weight tokens without local faces.');
    assertContainsValue('--weight-500: 500;', $css, 'Runtime variable sources should emit every imported concrete weight.');
    assertContainsValue('--weight-medium: var(--weight-500);', $css, 'Runtime variable sources should emit semantic aliases for imported weights.');
};

$tests['css_builder_can_disable_extended_variable_emission'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'font_category' => 'sans-serif',
            'faces' => [
                [
                    'family' => 'Inter',
                    'weight' => '400',
                    'style' => 'normal',
                ],
            ],
        ],
    ];
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $settings = [
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'per_variant_font_variables_enabled' => false,
    ];

    $css = $builder->build([], $roles, $settings, $catalog);

    assertContainsValue('--font-inter: "Inter", sans-serif;', $css, 'Family variables should still be emitted when extended output is disabled.');
    assertNotContainsValue('--font-sans', $css, 'Disabling extended output should suppress semantic family aliases.');
    assertNotContainsValue('--font-interface', $css, 'Disabling extended output should suppress role alias variables.');
    assertNotContainsValue('--weight-400', $css, 'Disabling extended output should suppress global numeric weight tokens.');
    assertNotContainsValue('font-weight: var(--weight-regular);', $css, 'Disabling extended output should suppress weight-token usage rules.');
};

$tests['css_builder_can_granularly_disable_selected_extended_variable_groups'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'font_category' => 'sans-serif',
            'faces' => [
                [
                    'family' => 'Inter',
                    'weight' => '400',
                    'style' => 'normal',
                ],
                [
                    'family' => 'Inter',
                    'weight' => '700',
                    'style' => 'normal',
                ],
            ],
        ],
        'Lora' => [
            'family' => 'Lora',
            'slug' => 'lora',
            'font_category' => 'serif',
            'faces' => [
                [
                    'family' => 'Lora',
                    'weight' => '400',
                    'style' => 'normal',
                ],
            ],
        ],
    ];
    $roles = [
        'heading' => 'Lora',
        'body' => 'Inter',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
    ];
    $settings = [
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'per_variant_font_variables_enabled' => true,
        'extended_variable_weight_tokens_enabled' => false,
        'extended_variable_role_aliases_enabled' => false,
        'extended_variable_category_sans_enabled' => false,
        'extended_variable_category_serif_enabled' => true,
        'extended_variable_category_mono_enabled' => true,
    ];

    $css = $builder->build([], $roles, $settings, $catalog);

    assertNotContainsValue('--font-interface', $css, 'Disabling extended role aliases should suppress interface aliases.');
    assertNotContainsValue('--font-ui', $css, 'Disabling extended role aliases should suppress UI aliases.');
    assertNotContainsValue('--weight-400', $css, 'Disabling extended weight tokens should suppress numeric weight variables.');
    assertContainsValue('--font-serif: var(--font-lora);', $css, 'Enabled category aliases should continue to emit allowed category variables.');
    assertNotContainsValue('--font-sans: var(--font-inter);', $css, 'Disabled category aliases should suppress only their own alias variable.');
    assertContainsValue("body {\n  font-family: var(--font-body);\n  font-weight: 400;\n}", $css, 'When weight tokens are disabled but extended output stays on, body usage should fall back to raw numeric weights.');
    assertContainsValue("h1, h2, h3, h4, h5, h6 {\n  font-family: var(--font-heading);\n  font-weight: 700;\n}", $css, 'When weight tokens are disabled but extended output stays on, heading usage should fall back to raw numeric weights.');
};

$tests['css_builder_infers_family_variable_fallbacks_from_catalog_category'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'JetBrains Mono' => [
            'family' => 'JetBrains Mono',
            'slug' => 'jetbrains-mono',
            'font_category' => 'monospace',
            'faces' => [
                [
                    'family' => 'JetBrains Mono',
                    'weight' => '400',
                    'style' => 'normal',
                ],
            ],
        ],
    ];
    $roles = [
        'heading' => 'JetBrains Mono',
        'body' => 'JetBrains Mono',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $settings = [
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'per_variant_font_variables_enabled' => true,
    ];

    $css = $builder->build([], $roles, $settings, $catalog);

    assertContainsValue('--font-jetbrains-mono: "JetBrains Mono", monospace;', $css, 'Monospace family variables should default to the monospace generic fallback when no explicit family fallback is saved.');
};

$tests['css_builder_skips_variable_weight_ranges_when_emitting_global_weight_tokens'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter Variable' => [
            'family' => 'Inter Variable',
            'slug' => 'inter-variable',
            'font_category' => 'sans-serif',
            'faces' => [
                [
                    'family' => 'Inter Variable',
                    'weight' => '100..900',
                    'style' => 'normal',
                ],
            ],
        ],
    ];
    $roles = [
        'heading' => 'Inter Variable',
        'body' => 'Inter Variable',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $settings = [
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'per_variant_font_variables_enabled' => true,
    ];

    $css = $builder->build([], $roles, $settings, $catalog);

    assertNotContainsValue('--weight-variable-range', $css, 'Variable weight ranges should not emit global semantic weight aliases.');
    assertNotContainsValue('--weight-100-900', $css, 'Variable weight ranges should not emit global numeric weight aliases.');
};

$tests['css_builder_emits_optional_monospace_role_css_when_enabled'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                    ],
                ],
            ],
        ],
    ];
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'monospace' => '',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'serif',
        'monospace_fallback' => 'monospace',
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'monospace_role_enabled' => true,
    ];

    $css = $builder->build($catalog, $roles, $settings, $catalog);

    assertContainsValue('--font-monospace: monospace;', $css, 'Enabled monospace support should emit a fallback-only monospace variable when no family is selected.');
    assertContainsValue("code, pre {\n  font-family: var(--font-monospace);\n}", $css, 'Enabled monospace support should emit the code/pre usage rule.');
    assertNotContainsValue('--font-monospace: var(--font-', $css, 'Fallback-only monospace output should not point the role variable at a synthetic family variable.');
};

$tests['css_builder_omits_monospace_role_css_when_feature_is_disabled'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                    ],
                ],
            ],
        ],
    ];
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'monospace' => 'JetBrains Mono',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'monospace_role_enabled' => false,
    ];

    $css = $builder->build($catalog, $roles, $settings, $catalog);

    assertNotContainsValue('--font-monospace', $css, 'Disabled monospace support should not emit a monospace role variable.');
    assertNotContainsValue('--font-mono', $css, 'Disabled monospace support should not emit the mono category alias either.');
    assertNotContainsValue('code, pre {', $css, 'Disabled monospace support should not emit the code/pre usage rule.');
};

$tests['css_builder_can_generate_font_faces_without_role_usage_rules'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                    ],
                ],
            ],
        ],
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => true,
        'minify_css_output' => false,
    ];

    $css = $builder->buildFontFaceOnly($catalog, $settings);

    assertContainsValue('@font-face', $css, 'Font-face-only CSS should still emit @font-face rules.');
    assertNotContainsValue('--font-heading', $css, 'Font-face-only CSS should not emit role variables.');
    assertNotContainsValue('font-family: var(--font-body);', $css, 'Font-face-only CSS should not emit body usage rules.');
    assertNotContainsValue('--font-monospace', $css, 'Font-face-only CSS should not emit monospace role variables either.');
};

$tests['css_builder_ignores_eot_and_svg_sources'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'eot' => 'https://example.com/fonts/inter.eot',
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                        'svg' => 'https://example.com/fonts/inter.svg',
                    ],
                ],
            ],
        ],
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => false,
        'minify_css_output' => false,
    ];

    $css = $builder->buildFontFaceOnly($catalog, $settings);

    assertContainsValue('format("woff2")', $css, 'CSS builder should continue to emit supported modern formats.');
    assertNotContainsValue('embedded-opentype', $css, 'CSS builder should not emit legacy EOT sources.');
    assertNotContainsValue('inter.svg', $css, 'CSS builder should not emit deprecated SVG font sources.');
};

$tests['css_builder_preserves_raw_query_strings_in_source_urls'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['google'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'google',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2?display=swap&subset=latin',
                    ],
                ],
            ],
        ],
    ];

    $css = $builder->buildFontFaceOnly($catalog, ['minify_css_output' => false]);

    assertContainsValue(
        'url("https://example.com/fonts/inter.woff2?display=swap&subset=latin") format("woff2")',
        $css,
        'CSS builder should preserve raw query-string separators inside font source URLs.'
    );
    assertNotContainsValue('&#038;', $css, 'CSS builder should not HTML-escape ampersands inside CSS source URLs.');
};

$tests['css_builder_minifies_generated_css_without_leaving_layout_whitespace'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                    ],
                ],
            ],
        ],
    ];
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => true,
        'minify_css_output' => true,
        'class_output_enabled' => true,
    ];

    $css = $builder->build($catalog, $roles, $settings, $catalog);

    assertSameValue(false, str_contains($css, "\n"), 'Minified CSS should not leave newline characters in the generated output.');
    assertSameValue(false, str_contains($css, "\t"), 'Minified CSS should not leave tab characters in the generated output.');
    assertContainsValue('@font-face{font-family:"Inter";font-weight:400;font-style:normal;', $css, 'Minified CSS should collapse @font-face declarations into a compact form.');
    assertContainsValue('body{font-family:var(--font-body);font-weight:var(--weight-regular)}', $css, 'Minified CSS should collapse role usage rules into a compact form.');
    assertContainsValue('.font-heading{font-family:"Inter",sans-serif}', $css, 'Minified CSS should collapse emitted role class utilities into a compact form.');
    assertContainsValue('.font-inter{font-family:"Inter",sans-serif}', $css, 'Minified CSS should collapse emitted family class utilities into a compact form.');
};

$tests['css_builder_format_output_respects_minify_flag'] = static function (): void {
    $builder = new CssBuilder();
    $snippet = ":root {\n  --font-heading: var(--font-lora);\n}\n";

    assertSameValue($snippet, $builder->formatOutput($snippet, false), 'Formatted output should preserve readable snippets when minification is disabled.');
    assertSameValue(':root{--font-heading:var(--font-lora)}', $builder->formatOutput($snippet, true), 'Formatted output should minify snippets when requested.');
};

$tests['css_builder_defaults_font_display_to_optional'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                    ],
                ],
            ],
        ],
    ];

    $css = $builder->buildFontFaceOnly($catalog, ['minify_css_output' => false]);

    assertContainsValue('font-display:optional;', $css, 'Generated font-face CSS should default to font-display optional when no explicit setting is stored.');
};

$tests['css_builder_uses_per_family_font_display_overrides'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                    ],
                ],
            ],
        ],
        'Lora' => [
            'family' => 'Lora',
            'slug' => 'lora',
            'sources' => ['google'],
            'faces' => [
                [
                    'family' => 'Lora',
                    'slug' => 'lora',
                    'source' => 'google',
                    'weight' => '700',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/lora.woff2',
                    ],
                ],
            ],
        ],
    ];

    $css = $builder->buildFontFaceOnly(
        $catalog,
        [
            'font_display' => 'optional',
            'family_font_displays' => ['Inter' => 'swap'],
            'minify_css_output' => false,
        ]
    );

    assertContainsValue("font-family:\"Inter\";\n  font-weight:400;\n  font-style:normal;\n  src:url(\"https://example.com/fonts/inter.woff2\") format(\"woff2\");\n  font-display:swap;", $css, 'Per-family overrides should change the font-display value for the matching family.');
    assertContainsValue("font-family:\"Lora\";\n  font-weight:700;\n  font-style:normal;\n  src:url(\"https://example.com/fonts/lora.woff2\") format(\"woff2\");\n  font-display:optional;", $css, 'Families without an override should continue using the global font-display default.');
};

$tests['storage_returns_absolute_generated_css_url'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $url = $storage->getGeneratedCssUrl();

    assertSameValue(
        'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css',
        $url,
        'Generated CSS URL should stay absolute so Etch can pass it to new URL(...).'
    );
};

$tests['storage_exposes_local_upload_and_adobe_directories'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $details = $storage->get();

    assertSameValue(
        true,
        is_array($details)
            && str_ends_with((string) ($details['upload_dir'] ?? ''), '/fonts/upload')
            && str_ends_with((string) ($details['adobe_dir'] ?? ''), '/fonts/adobe'),
        'Storage details should expose dedicated upload and Adobe directories under uploads/fonts.'
    );
};

$tests['catalog_service_applies_catalog_filter_before_returning_results'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2'), 'font-data');

    add_filter(
        'tasty_fonts_catalog',
        static function (array $catalog): array {
            unset($catalog['Inter']);

            return $catalog;
        }
    );

    $catalog = $services['catalog']->getCatalog();
    $counts = $services['catalog']->getCounts();

    assertSameValue(false, isset($catalog['Inter']), 'Catalog filters should be able to remove families before getCatalog() returns.');
    assertSameValue(0, (int) ($counts['families'] ?? -1), 'Catalog counts should reflect the filtered catalog payload.');
};

$tests['catalog_service_ignores_eot_and_svg_files_during_local_scan'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $storage->ensureRootDirectory();
    $storage->writeAbsoluteFile((string) $storage->pathForRelativePath('inter/Inter-400-normal.woff2'), 'font-data');
    $storage->writeAbsoluteFile((string) $storage->pathForRelativePath('legacy/Legacy-400-normal.eot'), 'font-data');
    $storage->writeAbsoluteFile((string) $storage->pathForRelativePath('vector/Vector-400-normal.svg'), 'font-data');

    $settings = new SettingsRepository();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeCssParser());
    $catalog = new CatalogService($storage, $imports, new FontFilenameParser(), $log, $adobe);
    $families = $catalog->getCatalog();

    assertSameValue(['Inter'], array_values(array_keys($families)), 'Catalog scanning should ignore local EOT and SVG files so the scanned formats match the upload allowlist.');
};

$tests['catalog_service_includes_live_role_families_in_published_filter_and_emits_category_aliases'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Caveat',
        'caveat',
        [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular'],
            'faces' => [],
            'meta' => ['category' => 'handwriting'],
        ],
        'role_active',
        true
    );

    $family = $services['catalog']->getCatalog()['Caveat'] ?? [];
    $deliveryTokens = (array) ($family['delivery_filter_tokens'] ?? []);
    $categoryTokens = (array) ($family['font_category_tokens'] ?? []);

    assertSameValue(true, in_array('role_active', $deliveryTokens, true), 'Live role families should keep their dedicated In Use token.');
    assertSameValue(true, in_array('published', $deliveryTokens, true), 'Live role families should also match the Published library filter.');
    assertSameValue('handwriting', (string) ($family['font_category'] ?? ''), 'Catalog families should preserve their normalized font category.');
    assertSameValue(true, in_array('handwriting', $categoryTokens, true), 'Handwriting families should expose their canonical category token.');
    assertSameValue(true, in_array('script', $categoryTokens, true), 'Handwriting families should match the Script type filter.');
    assertSameValue(true, in_array('cursive', $categoryTokens, true), 'Handwriting families should match the Cursive type filter.');
};

$tests['catalog_service_inferrs_monospace_category_from_family_name_when_metadata_is_missing'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'JetBrains Mono',
        'jetbrains-mono',
        [
            'id' => 'local-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [],
            'meta' => [],
        ],
        'published',
        true
    );

    $family = $services['catalog']->getCatalog()['JetBrains Mono'] ?? [];

    assertSameValue('monospace', (string) ($family['font_category'] ?? ''), 'Families with Mono in the name should infer the monospace category when provider metadata is missing.');
    assertSameValue(true, in_array('monospace', (array) ($family['font_category_tokens'] ?? []), true), 'Inferred monospace families should still emit the monospace filter token.');
};

$tests['storage_writes_absolute_files_via_wp_filesystem'] = static function (): void {
    resetTestState();

    global $wpFilesystemInitCalls;
    global $wp_filesystem;

    $storage = new Storage();
    $targetPath = uniqueTestDirectory('storage-write') . '/families/inter/inter-400.woff2';
    $written = $storage->writeAbsoluteFile($targetPath, 'font-data');

    assertSameValue(true, $written, 'Storage should write absolute files through the shared filesystem bridge.');
    assertSameValue('font-data', (string) file_get_contents($targetPath), 'Storage writes should persist the provided file contents.');
    assertSameValue(true, in_array(dirname($targetPath), $wp_filesystem->mkdirCalls, true), 'Storage writes should create missing parent directories before writing.');
    assertSameValue(1, count($wpFilesystemInitCalls), 'Storage writes should initialize the shared filesystem bridge once per write.');
};

$tests['storage_skips_wp_filesystem_when_direct_method_is_unavailable'] = static function (): void {
    resetTestState();

    global $filesystemMethod;
    global $wpFilesystemInitCalls;

    $filesystemMethod = 'ftpext';

    $storage = new Storage();
    $targetPath = uniqueTestDirectory('storage-no-direct') . '/families/inter/inter-400.woff2';
    $written = $storage->writeAbsoluteFile($targetPath, 'font-data');

    assertSameValue(false, $written, 'Storage writes should fail fast when WordPress cannot use the direct filesystem method.');
    assertSameValue(0, count($wpFilesystemInitCalls), 'Storage should not bootstrap WP_Filesystem when the direct method is unavailable.');
    assertContainsValue(
        'Direct filesystem access is unavailable',
        $storage->getLastFilesystemErrorMessage(),
        'Storage should expose a clear error message when direct filesystem access is unavailable.'
    );
};

$tests['storage_can_copy_absolute_files_without_buffering_contents'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $sourcePath = uniqueTestDirectory('storage-copy-source') . '/inter-400.woff2';
    $targetPath = uniqueTestDirectory('storage-copy-target') . '/families/inter/inter-400.woff2';

    mkdir(dirname($sourcePath), FS_CHMOD_DIR, true);
    file_put_contents($sourcePath, 'font-data');

    $copied = $storage->copyAbsoluteFile($sourcePath, $targetPath);

    assertSameValue(true, $copied, 'Storage should copy uploaded files into the target directory without reading the whole file into PHP memory first.');
    assertSameValue('font-data', (string) file_get_contents($targetPath), 'Copied files should preserve the original contents.');
};
