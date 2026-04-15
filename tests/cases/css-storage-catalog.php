<?php

declare(strict_types=1);

use TastyFonts\Adobe\AdobeCssParser;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Fonts\FontFilenameParser;
use TastyFonts\Fonts\HostedCssParser;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
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
    assertNotContainsValue('font-weight: var(--weight-regular);', $css, 'Role usage rules should omit font-weight declarations unless the dedicated setting is enabled.');
};

$tests['css_builder_resolves_unicode_range_output_modes'] = static function (): void {
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
                    'unicode_range' => 'U+0370-03FF',
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
    $baseSettings = [
        'font_display' => 'swap',
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'per_variant_font_variables_enabled' => true,
    ];

    $preservedCss = $builder->build($catalog, $roles, $baseSettings + ['unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_PRESERVE], $catalog);
    assertContainsValue('unicode-range:U+0370-03FF;', $preservedCss, 'Preserve mode should emit the stored face unicode-range.');

    $latinBasicCss = $builder->build($catalog, $roles, $baseSettings + ['unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_LATIN_BASIC], $catalog);
    assertContainsValue('unicode-range:' . FontUtils::UNICODE_RANGE_PRESET_LATIN_BASIC . ';', $latinBasicCss, 'Basic Latin mode should emit the forced Basic Latin unicode-range preset.');

    $latinExtendedCss = $builder->build($catalog, $roles, $baseSettings + ['unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_LATIN_EXTENDED], $catalog);
    assertContainsValue('unicode-range:' . FontUtils::UNICODE_RANGE_PRESET_LATIN_EXTENDED . ';', $latinExtendedCss, 'Latin Extended mode should emit the forced Latin Extended unicode-range preset.');

    $customCss = $builder->build($catalog, $roles, $baseSettings + ['unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_CUSTOM, 'unicode_range_custom_value' => 'U+0000-00FF,U+0100-024F'], $catalog);
    assertContainsValue('unicode-range:U+0000-00FF,U+0100-024F;', $customCss, 'Custom mode should emit the saved custom unicode-range.');

    $offCss = $builder->build($catalog, $roles, $baseSettings + ['unicode_range_mode' => FontUtils::UNICODE_RANGE_MODE_OFF], $catalog);
    assertNotContainsValue('unicode-range:', $offCss, 'Off mode should omit unicode-range declarations from generated CSS.');
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
    $emptyAliasRoles = [
        'heading' => 'Inter',
        'body' => '   ',
        'monospace' => '',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
    ];
    $emptyAliasCss = $builder->buildClassOutputSnippet($emptyAliasRoles, true, [], [
        'class_output_enabled' => true,
    ]);

    assertNotContainsValue('.font-interface', $emptyAliasCss, 'Combined class output should skip the interface alias when no body family is assigned.');
    assertNotContainsValue('.font-ui', $emptyAliasCss, 'Combined class output should skip the UI alias when no body family is assigned.');
    assertNotContainsValue('.font-code', $emptyAliasCss, 'Combined class output should skip the code alias when no monospace family is assigned.');
};

$tests['css_builder_can_add_readable_comments_to_class_output_snippets_only'] = static function (): void {
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
        'Inter' => [
            'family' => 'Inter',
            'font_category' => 'sans-serif',
        ],
        'Lora' => [
            'family' => 'Lora',
            'font_category' => 'serif',
        ],
        'JetBrains Mono' => [
            'family' => 'JetBrains Mono',
            'font_category' => 'monospace',
        ],
    ];
    $settings = [
        'class_output_enabled' => true,
        'class_output_families_enabled' => true,
    ];

    $commentedCss = $builder->buildCommentedClassOutputSnippet($roles, true, $families, $settings);
    $runtimeCss = $builder->buildClassOutputSnippet($roles, true, $families, $settings);

    assertContainsValue('/* Role classes */', $commentedCss, 'Admin-facing class snippets should label the role class section.');
    assertContainsValue('/* Role alias classes */', $commentedCss, 'Admin-facing class snippets should label the role alias section.');
    assertContainsValue('/* Category classes */', $commentedCss, 'Admin-facing class snippets should label the category section.');
    assertContainsValue('/* Family classes */', $commentedCss, 'Admin-facing class snippets should label the family section.');
    assertNotContainsValue('/* Role classes */', $runtimeCss, 'Runtime class output should stay comment-free.');
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
    assertContainsValue(".font-heading {\n  font-family: \"Inter\", sans-serif;\n}", $css, 'Generated CSS should append the heading role class using the selected family fallback when role class output mode is enabled.');
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

$tests['css_builder_minimal_output_preset_emits_only_heading_and_body_variables'] = static function (): void {
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
        'heading' => 'Inter',
        'body' => '',
        'monospace' => 'JetBrains Mono',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
    ];
    $settings = [
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'monospace_role_enabled' => true,
        'class_output_enabled' => true,
        'per_variant_font_variables_enabled' => true,
        'role_usage_font_weight_enabled' => true,
        'minimal_output_preset_enabled' => true,
    ];

    $css = $builder->build([], $roles, $settings, $catalog);

    assertContainsValue('--font-heading: "Inter", sans-serif;', $css, 'Minimal output should still emit the heading role variable using the selected family fallback.');
    assertContainsValue('--font-body: sans-serif;', $css, 'Minimal output should emit the body role variable as a fallback-only stack when needed.');
    assertContainsValue('--font-heading-settings: normal;', $css, 'Minimal output should still emit the heading variation-settings variable.');
    assertContainsValue('--font-body-settings: normal;', $css, 'Minimal output should still emit the body variation-settings variable.');
    assertNotContainsValue('--font-inter', $css, 'Minimal output should suppress family variables.');
    assertContainsValue('--font-monospace: "JetBrains Mono", monospace;', $css, 'Minimal output should still emit the monospace role variable when the monospace role is enabled.');
    assertContainsValue('--font-monospace-settings: normal;', $css, 'Minimal output should still emit the monospace variation-settings variable when the monospace role is enabled.');
    assertNotContainsValue('--font-interface', $css, 'Minimal output should suppress role alias variables.');
    assertNotContainsValue('--weight-400', $css, 'Minimal output should suppress weight tokens.');
    assertNotContainsValue('body {', $css, 'Minimal output should suppress role usage rules.');
    assertNotContainsValue('code, pre {', $css, 'Minimal output should keep snippet output variable-only even when monospace is enabled.');
    assertNotContainsValue('.font-heading', $css, 'Minimal output should suppress class output even if the class flag is otherwise enabled.');
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
        'role_usage_font_weight_enabled' => true,
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
    assertContainsValue("body {\n  font-family: var(--font-body);\n  font-variation-settings: var(--font-body-settings);\n  font-weight: 400;\n}", $css, 'When weight tokens are disabled but extended output stays on, body usage should fall back to raw numeric weights.');
    assertContainsValue("h1, h2, h3, h4, h5, h6 {\n  font-family: var(--font-heading);\n  font-variation-settings: var(--font-heading-settings);\n  font-weight: 700;\n}", $css, 'When weight tokens are disabled but extended output stays on, heading usage should fall back to raw numeric weights.');
};

$tests['css_builder_keeps_role_font_weights_when_variable_output_is_disabled'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'font_category' => 'sans-serif',
            'faces' => [
                ['family' => 'Inter', 'weight' => '400', 'style' => 'normal'],
                ['family' => 'Inter', 'weight' => '700', 'style' => 'normal'],
            ],
        ],
        'JetBrains Mono' => [
            'family' => 'JetBrains Mono',
            'slug' => 'jetbrains-mono',
            'font_category' => 'monospace',
            'faces' => [
                ['family' => 'JetBrains Mono', 'weight' => '400', 'style' => 'normal'],
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
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'monospace_role_enabled' => true,
        'class_output_enabled' => true,
        'per_variant_font_variables_enabled' => false,
        'role_usage_font_weight_enabled' => true,
    ];

    $css = $builder->build([], $roles, $settings, $catalog);

    assertContainsValue("body {\n  font-family: var(--font-body);\n  font-variation-settings: var(--font-body-settings);\n  font-weight: 400;\n}", $css, 'Role usage snippets should keep raw body font weights when variable output is disabled.');
    assertContainsValue("h1, h2, h3, h4, h5, h6 {\n  font-family: var(--font-heading);\n  font-variation-settings: var(--font-heading-settings);\n  font-weight: 700;\n}", $css, 'Role usage snippets should keep raw heading font weights when variable output is disabled.');
    assertContainsValue("code, pre {\n  font-family: var(--font-monospace);\n  font-variation-settings: var(--font-monospace-settings);\n  font-weight: 400;\n}", $css, 'Role usage snippets should keep raw monospace font weights when variable output is disabled.');
    assertNotContainsValue('--weight-400', $css, 'Disabling variable output should continue to suppress global weight tokens.');
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

$tests['css_builder_emits_variable_font_ranges_and_role_variation_settings'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter Variable' => [
            'family' => 'Inter Variable',
            'slug' => 'inter-variable',
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
                    'variation_defaults' => [
                        'WGHT' => '420',
                        'OPSZ' => '14',
                    ],
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter-variable.woff2',
                    ],
                ],
            ],
        ],
    ];
    $roles = [
        'heading' => 'Inter Variable',
        'body' => 'Inter Variable',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
        'heading_axes' => ['WGHT' => '720', 'OPSZ' => '18'],
        'body_axes' => ['WGHT' => '420'],
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'variable_fonts_enabled' => true,
    ];

    $css = $builder->build($catalog, $roles, $settings, $catalog);

    assertContainsValue("font-weight:100 900;", $css, 'Variable font-face output should emit CSS font-weight ranges from the weight axis.');
    assertContainsValue(
        "@font-face{\n  font-family:\"Inter Variable\";\n  font-weight:100 900;\n  font-style:normal;\n  src:url(\"https://example.com/fonts/inter-variable.woff2\") format(\"woff2\");\n  font-display:swap;\n}",
        $css,
        'Variable font-face output should omit face-level variation defaults when only registered axes are present.'
    );
    assertContainsValue('--font-heading-settings: "opsz" 18, "wght" 720;', $css, 'Role variable output should emit configured heading variation settings.');
    assertContainsValue('font-variation-settings: var(--font-body-settings);', $css, 'Role usage output should reference the body variation-settings variable.');
};

$tests['css_builder_minify_preserves_variable_font_descriptor_spacing'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter Variable' => [
            'family' => 'Inter Variable',
            'slug' => 'inter-variable',
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
                    'variation_defaults' => [
                        'WGHT' => '420',
                        'OPSZ' => '14',
                    ],
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter-variable.woff2',
                    ],
                ],
            ],
        ],
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => false,
        'minify_css_output' => true,
        'variable_fonts_enabled' => true,
    ];

    $css = $builder->build($catalog, [], $settings, $catalog);

    assertContainsValue('font-weight:100 900;', $css, 'Minified variable font-face output should preserve the required space inside weight ranges.');
    assertNotContainsValue('font-variation-settings:', $css, 'Minified variable font-face output should not bake registered axis defaults into @font-face rules.');
    assertNotContainsValue('font-weight:100900;', $css, 'Minified variable font-face output should not collapse weight ranges into invalid numeric tokens.');
    assertNotContainsValue('font-variation-settings:"opsz" 14,"wght" 420;', $css, 'Minified variable font-face output should not pin the registered WGHT axis inside @font-face.');
};

$tests['css_builder_emits_role_weight_variables_when_enabled'] = static function (): void {
    $builder = new CssBuilder();
    $roles = [
        'heading' => 'Inter Variable',
        'body' => 'Inter Variable',
        'monospace' => 'IBM Plex Mono Variable',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
        'heading_weight' => '600',
        'body_weight' => '400',
        'monospace_weight' => '500',
        'heading_axes' => ['WGHT' => '650', 'OPSZ' => '18'],
        'body_axes' => ['WGHT' => '420'],
        'monospace_axes' => ['WGHT' => '510'],
    ];
    $settings = [
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'variable_fonts_enabled' => true,
        'extended_variable_role_weight_vars_enabled' => true,
        'monospace_role_enabled' => true,
    ];

    $css = $builder->build([], $roles, $settings, []);

    assertContainsValue('--font-heading-weight: 650;', $css, 'Role weight variable output should expose the resolved heading weight through a dedicated role variable.');
    assertContainsValue('--font-body-weight: 420;', $css, 'Role weight variable output should expose the resolved body weight through a dedicated role variable.');
    assertContainsValue('--font-monospace-weight: 510;', $css, 'Role weight variable output should expose the resolved monospace weight through a dedicated role variable when the role is enabled.');
    assertNotContainsValue('html:root {', $css, 'Role weight variable output should not emit a dedicated compatibility rule block.');
    assertNotContainsValue('body :is(p, li, blockquote)', $css, 'Role weight variable output should not inject extra ACSS-specific variation selector rules.');
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
    assertContainsValue("code, pre {\n  font-family: var(--font-monospace);\n  font-variation-settings: var(--font-monospace-settings);\n}", $css, 'Enabled monospace support should emit the code/pre usage rule.');
    assertNotContainsValue('--font-monospace: var(--font-', $css, 'Fallback-only monospace output should not point the role variable at a synthetic family variable.');
};

$tests['css_builder_uses_saved_role_weight_overrides_for_usage_rules'] = static function (): void {
    $builder = new CssBuilder();
    $roles = [
        'heading' => 'Lora',
        'body' => 'Inter',
        'monospace' => 'JetBrains Mono',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
        'heading_weight' => '600',
        'body_weight' => '500',
        'monospace_weight' => '500',
    ];
    $settings = [
        'auto_apply_roles' => true,
        'minify_css_output' => false,
        'monospace_role_enabled' => true,
        'role_usage_font_weight_enabled' => true,
        'per_variant_font_variables_enabled' => true,
        'extended_variable_weight_tokens_enabled' => false,
    ];

    $css = $builder->build([], $roles, $settings, []);

    assertContainsValue("body {\n  font-family: var(--font-body);\n  font-variation-settings: var(--font-body-settings);\n  font-weight: 500;\n}", $css, 'Body usage output should honor saved static role weight overrides.');
    assertContainsValue("h1, h2, h3, h4, h5, h6 {\n  font-family: var(--font-heading);\n  font-variation-settings: var(--font-heading-settings);\n  font-weight: 600;\n}", $css, 'Heading usage output should honor saved static role weight overrides.');
    assertContainsValue("code, pre {\n  font-family: var(--font-monospace);\n  font-variation-settings: var(--font-monospace-settings);\n  font-weight: 500;\n}", $css, 'Monospace usage output should honor saved static role weight overrides.');
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
        'role_usage_font_weight_enabled' => true,
        'class_output_enabled' => true,
    ];

    $css = $builder->build($catalog, $roles, $settings, $catalog);

    assertSameValue(false, str_contains($css, "\n"), 'Minified CSS should not leave newline characters in the generated output.');
    assertSameValue(false, str_contains($css, "\t"), 'Minified CSS should not leave tab characters in the generated output.');
    assertContainsValue('@font-face{font-family:"Inter";font-weight:400;font-style:normal;', $css, 'Minified CSS should collapse @font-face declarations into a compact form.');
    assertContainsValue('body{font-family:var(--font-body);font-variation-settings:var(--font-body-settings);font-weight:var(--weight-regular)}', $css, 'Minified CSS should collapse role usage rules into a compact form.');
    assertContainsValue('.font-heading{font-family:"Inter",sans-serif}', $css, 'Minified CSS should collapse emitted role class utilities into a compact form.');
    assertContainsValue('.font-inter{font-family:"Inter",sans-serif}', $css, 'Minified CSS should collapse emitted family class utilities into a compact form.');
};

$tests['css_builder_omits_role_font_weights_by_default_even_when_weight_tokens_exist'] = static function (): void {
    $builder = new CssBuilder();
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
        'extended_variable_weight_tokens_enabled' => true,
    ];

    $css = $builder->build([], $roles, $settings, []);

    assertContainsValue("body {\n  font-family: var(--font-body);\n  font-variation-settings: var(--font-body-settings);\n}", $css, 'Body usage output should still be emitted when role font-weight output is off.');
    assertContainsValue("h1, h2, h3, h4, h5, h6 {\n  font-family: var(--font-heading);\n  font-variation-settings: var(--font-heading-settings);\n}", $css, 'Heading usage output should still be emitted when role font-weight output is off.');
    assertNotContainsValue('font-weight: var(--weight-regular);', $css, 'Weight token usage rules should stay out of role CSS by default.');
    assertNotContainsValue('font-weight: var(--weight-bold);', $css, 'Heading weight token usage rules should stay out of role CSS by default.');
};

$tests['css_builder_format_output_respects_minify_flag'] = static function (): void {
    $builder = new CssBuilder();
    $snippet = ":root {\n  --font-heading: var(--font-lora);\n}\n";

    assertSameValue($snippet, $builder->formatOutput($snippet, false), 'Formatted output should preserve readable snippets when minification is disabled.');
    assertSameValue(':root{--font-heading:var(--font-lora)}', $builder->formatOutput($snippet, true), 'Formatted output should minify snippets when requested.');
};

$tests['css_builder_builds_role_variable_declarations_without_root_wrapper'] = static function (): void {
    $builder = new CssBuilder();
    $roles = [
        'heading' => 'Lora',
        'body' => 'Inter',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
    ];

    $snippet = $builder->buildRoleVariableDeclarationsSnippet($roles, false, [], []);

    assertContainsValue('--font-heading: "Lora", serif;', $snippet, 'Variable declarations should include the heading role stack.');
    assertContainsValue('--font-body: "Inter", sans-serif;', $snippet, 'Variable declarations should include the body role stack.');
    assertNotContainsValue(':root', $snippet, 'Variable declarations should omit the root selector wrapper.');
};

$tests['css_builder_can_add_readable_comments_to_admin_role_snippets_only'] = static function (): void {
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
        'heading' => 'Lora',
        'body' => 'Inter',
        'monospace' => 'JetBrains Mono',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
        'monospace_fallback' => 'monospace',
    ];
    $settings = [
        'auto_apply_roles' => true,
        'monospace_role_enabled' => true,
        'minify_css_output' => false,
        'minimal_output_preset_enabled' => false,
        'extended_variable_output_enabled' => true,
        'extended_variable_role_aliases_enabled' => true,
        'extended_variable_weight_tokens_enabled' => true,
        'extended_variable_category_sans_enabled' => true,
        'extended_variable_category_serif_enabled' => true,
        'extended_variable_category_mono_enabled' => true,
        'role_usage_font_weight_enabled' => true,
    ];

    $annotatedSnippet = $builder->buildRoleUsageSnippet($roles, true, $catalog, $settings, true);
    $runtimeCss = $builder->build([], $roles, $settings, $catalog);

    assertContainsValue('/* Family font stacks */', $annotatedSnippet, 'Admin-facing snippets should label the family variable section.');
    assertContainsValue('/* Role aliases */', $annotatedSnippet, 'Admin-facing snippets should label the helper alias section.');
    assertContainsValue('/* Weight tokens */', $annotatedSnippet, 'Admin-facing snippets should label the weight token section.');
    assertContainsValue('/* Body text */', $annotatedSnippet, 'Admin-facing snippets should label the body usage rule.');
    assertContainsValue('/* Code blocks */', $annotatedSnippet, 'Admin-facing snippets should label the monospace usage rule.');
    assertNotContainsValue('/* Family font stacks */', $runtimeCss, 'Runtime CSS generation should stay comment-free even when admin snippets are annotated.');
    assertNotContainsValue('/* Body text */', $runtimeCss, 'Role usage comments should not leak into the live generated CSS.');
};

$tests['css_builder_defaults_font_display_to_swap'] = static function (): void {
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

    assertContainsValue('font-display:swap;', $css, 'Generated font-face CSS should default to font-display swap when no explicit setting is stored.');
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
    $family = $families['Inter'] ?? [];

    assertSameValue(['Inter'], array_values(array_keys($families)), 'Catalog scanning should ignore local EOT and SVG files so the scanned formats match the upload allowlist.');
    assertSameValue('library_only', (string) ($family['publish_state'] ?? ''), 'Families discovered by scanning the fonts directory should start in the library instead of being published immediately.');
};

$tests['catalog_service_only_includes_local_variable_fonts_when_the_feature_flag_is_enabled'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $storage->ensureRootDirectory();
    $storage->writeAbsoluteFile((string) $storage->pathForRelativePath('inter-variable/Inter-VariableFont.woff2'), 'font-data');

    $settings = new SettingsRepository();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeCssParser());

    $catalog = new CatalogService($storage, $imports, new FontFilenameParser(), $log, $adobe);
    assertSameValue([], array_values(array_keys($catalog->getCatalog())), 'Variable font files should stay out of the local catalog while the feature flag is disabled.');

    $settings->saveSettings(['variable_fonts_enabled' => '1']);
    $catalog->invalidate();
    $families = $catalog->getCatalog();
    $family = $families['Inter'] ?? [];

    assertSameValue(true, isset($families['Inter']), 'Variable font files should be added to the local catalog once the feature flag is enabled.');
    assertSameValue(true, !empty($family['has_variable_faces']), 'Catalog families should expose whether any active faces are variable.');
    assertSameValue(true, in_array('variable', (array) ($family['font_category_tokens'] ?? []), true), 'Variable families should expose the Variable type filter token.');
    assertSameValue('100', (string) ($family['variation_axes']['WGHT']['min'] ?? ''), 'Catalog families should aggregate available variable axes for the active family.');
};

$tests['hosted_css_parser_defaults_variable_weight_axes_to_normal_when_no_explicit_wght_setting_exists'] = static function (): void {
    $parser = new HostedCssParser('google');
    $faces = $parser->parse(
        <<<'CSS'
        @font-face {
            font-family: "Inter Variable";
            font-style: normal;
            font-weight: 100 900;
            src: url("https://fonts.example/inter-variable.woff2") format("woff2-variations");
        }
        CSS
    );

    assertSameValue(1, count($faces), 'Variable hosted CSS should still yield a single parsed face when only the weight range is present.');
    assertSameValue('400', (string) ($faces[0]['axes']['WGHT']['default'] ?? ''), 'Range-only variable hosted CSS should default WGHT to the normal weight instead of the minimum.');
    assertSameValue('400', (string) ($faces[0]['variation_defaults']['WGHT'] ?? ''), 'Range-only variable hosted CSS should keep the derived normal WGHT default in variation_defaults.');
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

// ---------------------------------------------------------------------------
// CatalogService::maybeInvalidateFromAttachment – edge cases
// ---------------------------------------------------------------------------
$tests['catalog_service_maybe_invalidate_from_attachment_is_safe_when_catalog_transient_is_absent'] = static function (): void {
    resetTestState();

    global $attachedFilePaths;
    global $transientDeleted;
    global $transientStore;

    $services = makeServiceGraph();
    $storage = new Storage();
    $root = $storage->getRoot();

    // No catalog transient in the store.
    $attachedFilePaths[50] = $root . '/google/inter/inter-400-normal.woff2';
    $services['catalog']->maybeInvalidateFromAttachment(50);

    // Should attempt to delete the transient without throwing.
    assertSameValue(true, in_array(TastyFonts\Support\TransientKey::forSite('tasty_fonts_catalog_v2'), $transientDeleted, true), 'maybeInvalidateFromAttachment() should still call delete_transient even when no cached catalog transient is present.');
};

$tests['catalog_service_maybe_invalidate_from_attachment_ignores_paths_outside_font_storage'] = static function (): void {
    resetTestState();

    global $attachedFilePaths;
    global $transientDeleted;
    global $transientStore;

    $services = makeServiceGraph();

    $outsideRoot = uniqueTestDirectory('outside-root') . '/image.jpg';
    $attachedFilePaths[60] = $outsideRoot;
    $transientStore[TastyFonts\Support\TransientKey::forSite('tasty_fonts_catalog_v2')] = ['cached' => true];

    $services['catalog']->maybeInvalidateFromAttachment(60);

    assertFalseValue(
        in_array(TastyFonts\Support\TransientKey::forSite('tasty_fonts_catalog_v2'), $transientDeleted, true),
        'maybeInvalidateFromAttachment() should leave the catalog cache intact when the attachment path is outside the font storage root.'
    );
};

$tests['catalog_service_maybe_invalidate_from_attachment_normalises_windows_style_paths'] = static function (): void {
    resetTestState();

    global $attachedFilePaths;
    global $transientDeleted;
    global $transientStore;

    $services = makeServiceGraph();
    $storage = new Storage();
    $root = $storage->getRoot();

    // Simulate an attachment path that uses backslashes (Windows ABSPATH separators).
    $windowsStylePath = str_replace('/', '\\', $root . '/google/inter/inter-400-normal.woff2');
    $attachedFilePaths[70] = $windowsStylePath;
    $transientStore[TastyFonts\Support\TransientKey::forSite('tasty_fonts_catalog_v2')] = ['cached' => true];

    $services['catalog']->maybeInvalidateFromAttachment(70);

    assertSameValue(
        true,
        in_array(TastyFonts\Support\TransientKey::forSite('tasty_fonts_catalog_v2'), $transientDeleted, true),
        'maybeInvalidateFromAttachment() should treat backslash-separated paths as equivalent to their forward-slash counterparts.'
    );
};
