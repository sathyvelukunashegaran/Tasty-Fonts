<?php

declare(strict_types=1);

use TastyFonts\Admin\AdminPageRenderer;
use TastyFonts\Plugin;
use TastyFonts\Support\Storage;

$tests['admin_page_renderer_uses_inline_delivery_badge_for_single_delivery_families'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'Inter',
        'slug' => 'inter',
        'delivery_filter_tokens' => ['google'],
        'publish_state' => 'published',
        'active_delivery_id' => 'google-cdn',
        'active_delivery' => [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular', '700'],
        ],
        'available_deliveries' => [
            [
                'id' => 'google-cdn',
                'label' => 'Google CDN',
                'provider' => 'google',
                'type' => 'cdn',
                'variants' => ['regular', '700'],
            ],
        ],
        'delivery_badges' => [
            [
                'label' => 'Google CDN',
                'class' => '',
                'copy' => 'Google CDN',
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'google',
                'files' => [],
                'paths' => [],
            ],
            [
                'weight' => '700',
                'style' => 'normal',
                'source' => 'google',
                'files' => [],
                'paths' => [],
            ],
        ],
    ];

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFamilyRow',
        [
            $family,
            ['heading' => '', 'body' => ''],
            [],
            [],
            [
                ['value' => 'inherit', 'label' => 'Use plugin default'],
                ['value' => 'swap', 'label' => 'swap'],
            ],
            'The quick brown fox jumps over the lazy dog.',
            [],
        ]
    );
    $output = (string) ob_get_clean();

    assertContainsValue('data-font-slug="inter"', $output, 'Library family rows should expose the normalized slug for client-side matching and post-import highlighting.');
    assertContainsValue('tasty-fonts-badges--library-inline', $output, 'Single-delivery families should render the compact inline badge treatment.');
    assertContainsValue('Google CDN', $output, 'The active single delivery label should stay visible on the family card.');
    assertContainsValue('tasty-fonts-detail-group', $output, 'Family details should render as design-system card groups instead of plain WordPress tables.');
    assertNotContainsValue('Available delivery profiles', $output, 'Single-delivery families should not render the verbose available deliveries note.');
    assertNotContainsValue('Live delivery', $output, 'Single-delivery families should not render the verbose live delivery note.');
    assertNotContainsValue('widefat striped tasty-fonts-table', $output, 'Family details should no longer use widefat table markup.');
};

$tests['admin_page_renderer_renders_library_type_filter_and_category_tokens'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [
            'JetBrains Mono' => [
                'family' => 'JetBrains Mono',
                'slug' => 'jetbrains-mono',
                'delivery_filter_tokens' => ['published', 'same-origin'],
                'font_category' => 'monospace',
                'font_category_tokens' => ['monospace'],
                'publish_state' => 'published',
                'active_delivery_id' => 'local-self-hosted',
                'active_delivery' => [
                    'id' => 'local-self-hosted',
                    'label' => 'Self-hosted',
                    'provider' => 'local',
                    'type' => 'self_hosted',
                    'variants' => ['regular'],
                ],
                'available_deliveries' => [
                    [
                        'id' => 'local-self-hosted',
                        'label' => 'Self-hosted',
                        'provider' => 'local',
                        'type' => 'self_hosted',
                        'variants' => ['regular'],
                    ],
                ],
                'delivery_badges' => [
                    [
                        'label' => 'Published',
                        'class' => 'is-success',
                        'copy' => 'Published',
                    ],
                ],
                'faces' => [
                    [
                        'weight' => '400',
                        'style' => 'normal',
                        'source' => 'local',
                        'files' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
                        'paths' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
                    ],
                ],
            ],
        ],
        'available_families' => ['JetBrains Mono'],
        'roles' => [
            'heading' => '',
            'body' => '',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [
            ['value' => 'inherit', 'label' => 'Use plugin default'],
            ['value' => 'swap', 'label' => 'swap'],
        ],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'block_editor_font_library_sync_enabled' => false,
        'training_wheels_off' => false,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [],
        'local_environment_notice' => [],
        'toasts' => [],
        'apply_everywhere' => false,
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();

    assertContainsValue('data-library-category-filter', $output, 'The Font Library toolbar should render a dedicated type filter control.');
    assertContainsValue('All Types', $output, 'The library type filter should include the All Types option.');
    assertContainsValue('Cursive / Script', $output, 'The library type filter should expose the combined cursive/script option.');
    assertContainsValue('data-font-categories="monospace"', $output, 'Library rows should expose normalized font category tokens for client-side filtering.');
    assertContainsValue('>Monospace<', $output, 'Library rows should display the normalized font category badge.');
};

$tests['admin_page_renderer_renders_extended_variable_submenu_controls'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => [],
        'roles' => [],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'per_variant_font_variables_enabled' => true,
        'extended_variable_weight_tokens_enabled' => true,
        'extended_variable_role_aliases_enabled' => true,
        'extended_variable_category_sans_enabled' => true,
        'extended_variable_category_serif_enabled' => true,
        'extended_variable_category_mono_enabled' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'block_editor_font_library_sync_enabled' => false,
        'training_wheels_off' => false,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [],
        'local_environment_notice' => [],
        'toasts' => [],
        'apply_everywhere' => false,
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();

    assertContainsValue('Extended Variable Controls', $output, 'Output Settings should render a nested submenu for extended variable controls.');
    assertContainsValue('Global weight tokens', $output, 'The submenu should include a granular weight-token toggle.');
    assertContainsValue('Role alias variables', $output, 'The submenu should include a granular role-alias toggle.');
    assertContainsValue('Sans alias', $output, 'The submenu should include a granular sans-category toggle.');
    assertContainsValue('Serif alias', $output, 'The submenu should include a granular serif-category toggle.');
    assertNotContainsValue('Mono alias', $output, 'The submenu should hide the mono-category toggle when the monospace feature is disabled.');
    assertNotContainsValue('--font-code', $output, 'The role-alias copy should not mention the code alias when the monospace feature is disabled.');
};

$tests['admin_page_renderer_shows_mono_extended_variable_controls_when_monospace_is_enabled'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => [],
        'roles' => [],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'per_variant_font_variables_enabled' => true,
        'extended_variable_weight_tokens_enabled' => true,
        'extended_variable_role_aliases_enabled' => true,
        'extended_variable_category_sans_enabled' => true,
        'extended_variable_category_serif_enabled' => true,
        'extended_variable_category_mono_enabled' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'block_editor_font_library_sync_enabled' => false,
        'training_wheels_off' => false,
        'monospace_role_enabled' => true,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [],
        'local_environment_notice' => [],
        'toasts' => [],
        'apply_everywhere' => false,
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();

    assertContainsValue('Mono alias', $output, 'The submenu should show the mono-category toggle when the monospace feature is enabled.');
    assertContainsValue('--font-code', $output, 'The role-alias copy should mention the code alias when the monospace feature is enabled.');
};

$tests['admin_page_renderer_outputs_migrate_shortcuts_for_cdn_deliveries'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'Inter',
        'slug' => 'inter',
        'delivery_filter_tokens' => ['google'],
        'publish_state' => 'published',
        'active_delivery_id' => 'google-cdn',
        'active_delivery' => [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular', '700'],
        ],
        'available_deliveries' => [
            [
                'id' => 'google-cdn',
                'label' => 'Google CDN',
                'provider' => 'google',
                'type' => 'cdn',
                'variants' => ['regular', '700'],
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'google',
                'files' => [],
                'paths' => [],
            ],
        ],
    ];

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFamilyRow',
        [
            $family,
            ['heading' => '', 'body' => ''],
            [],
            [],
            [
                ['value' => 'inherit', 'label' => 'Use plugin default'],
            ],
            'The quick brown fox jumps over the lazy dog.',
            [],
        ]
    );
    $output = (string) ob_get_clean();

    assertSameValue(1, substr_count($output, 'data-migrate-delivery'), 'CDN-backed library families should expose the self-host migration shortcut only in the saved delivery profile details.');
    assertContainsValue('data-migrate-provider="google"', $output, 'The migration shortcut should preserve the delivery provider.');
    assertContainsValue('data-migrate-family="Inter"', $output, 'The migration shortcut should preserve the family name for panel prefill.');
    assertContainsValue('data-migrate-variants="regular,700"', $output, 'The migration shortcut should preserve the saved variant tokens for self-hosting prefill.');
    assertNotContainsValue('tasty-fonts-font-actions-secondary', $output, 'Library cards should no longer render a dedicated migration action row above the detailed delivery profile actions.');
    assertNotContainsValue('Remote variants are managed by their delivery profile instead of being deleted individually.', $output, 'CDN-backed active faces should no longer be hard-disabled from individual deletion in the detail cards.');
};

$tests['admin_page_renderer_renders_copy_ready_face_variant_variables'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFaceDetailCard',
        [
            'Inter',
            'inter',
            '"Inter", sans-serif',
            'The quick brown fox jumps over the lazy dog.',
            2,
            ['body'],
            'sans-serif',
            ['--font-sans' => 'Inter'],
            ['enabled' => true, 'weight_tokens' => true, 'role_aliases' => true, 'category_sans' => true, 'category_serif' => true, 'category_mono' => true],
            ['provider' => 'local', 'type' => 'self_hosted'],
            [
                'weight' => '700',
                'style' => 'italic',
                'source' => 'local',
                'files' => ['woff2' => 'inter/Inter-700-italic.woff2'],
                'paths' => ['woff2' => 'inter/Inter-700-italic.woff2'],
            ],
            false,
        ]
    );
    $output = (string) ob_get_clean();

    assertContainsValue('>CSS<', $output, 'Face detail cards should render a dedicated CSS area.');
    assertContainsValue('data-copy-text="font-family: var(--font-inter);"', $output, 'Face detail cards should expose the family CSS declaration.');
    assertContainsValue('data-copy-text="font-weight: var(--weight-bold);"', $output, 'Face detail cards should expose the weight CSS declaration.');
    assertContainsValue('data-copy-text="font-style: italic;"', $output, 'Face detail cards should expose the style CSS declaration.');
    assertContainsValue('data-copy-text="font-family: var(--font-inter); font-weight: var(--weight-bold); font-style: italic;"', $output, 'Face detail cards should expose the full CSS snippet.');
    assertNotContainsValue('data-copy-text="--font-interface: var(--font-body);"', $output, 'Face detail cards should no longer expose family-level interface aliases.');
    assertNotContainsValue('data-copy-text="--font-ui: var(--font-body);"', $output, 'Face detail cards should no longer expose family-level UI aliases.');
    assertNotContainsValue('data-copy-text="--font-sans: var(--font-inter);"', $output, 'Face detail cards should no longer expose family-level category aliases.');
};

$tests['admin_page_renderer_renders_copy_ready_family_css_variables'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'JetBrains Mono',
        'slug' => 'jetbrains-mono',
        'font_category' => 'monospace',
        'font_category_tokens' => ['monospace'],
        'delivery_filter_tokens' => ['published'],
        'publish_state' => 'published',
        'active_delivery_id' => 'local-self-hosted',
        'active_delivery' => [
            'id' => 'local-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
        ],
        'available_deliveries' => [
            [
                'id' => 'local-self-hosted',
                'label' => 'Self-hosted',
                'provider' => 'local',
                'type' => 'self_hosted',
                'variants' => ['regular'],
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'local',
                'files' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
                'paths' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
            ],
        ],
    ];

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFamilyRow',
        [
            $family,
            ['heading' => '', 'body' => '', 'monospace' => 'JetBrains Mono', 'monospace_fallback' => 'monospace'],
            [],
            [],
            [
                ['value' => 'inherit', 'label' => 'Use plugin default'],
            ],
            'The quick brown fox jumps over the lazy dog.',
            ['--font-mono' => 'JetBrains Mono'],
            ['enabled' => true, 'weight_tokens' => true, 'role_aliases' => true, 'category_sans' => true, 'category_serif' => true, 'category_mono' => true],
            true,
        ]
    );
    $output = (string) ob_get_clean();

    assertContainsValue('>CSS Variables<', $output, 'Family details should render a dedicated CSS Variables section.');
    assertContainsValue('data-copy-text="--font-jetbrains-mono: &quot;JetBrains Mono&quot;, monospace;"', $output, 'Family details should expose the base family variable.');
    assertContainsValue('data-copy-text="--font-monospace: &quot;JetBrains Mono&quot;, monospace;"', $output, 'Family details should expose the role variable at the family level.');
    assertContainsValue('data-copy-text="--font-code: var(--font-monospace);"', $output, 'Family details should expose the code alias when the family is assigned to monospace.');
    assertContainsValue('data-copy-text="--font-mono: var(--font-jetbrains-mono);"', $output, 'Family details should expose the category monospace alias when the family resolves the mono token.');
};

$tests['admin_page_renderer_hides_extended_family_css_variables_when_disabled'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'JetBrains Mono',
        'slug' => 'jetbrains-mono',
        'font_category' => 'monospace',
        'font_category_tokens' => ['monospace'],
        'delivery_filter_tokens' => ['published'],
        'publish_state' => 'published',
        'active_delivery_id' => 'local-self-hosted',
        'active_delivery' => [
            'id' => 'local-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
        ],
        'available_deliveries' => [
            [
                'id' => 'local-self-hosted',
                'label' => 'Self-hosted',
                'provider' => 'local',
                'type' => 'self_hosted',
                'variants' => ['regular'],
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'local',
                'files' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
                'paths' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
            ],
        ],
    ];

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFamilyRow',
        [
            $family,
            ['heading' => '', 'body' => '', 'monospace' => 'JetBrains Mono', 'monospace_fallback' => 'monospace'],
            [],
            [],
            [
                ['value' => 'inherit', 'label' => 'Use plugin default'],
            ],
            'The quick brown fox jumps over the lazy dog.',
            ['--font-mono' => 'JetBrains Mono'],
            ['enabled' => false, 'weight_tokens' => true, 'role_aliases' => true, 'category_sans' => true, 'category_serif' => true, 'category_mono' => true],
            true,
        ]
    );
    $output = (string) ob_get_clean();

    assertContainsValue('data-copy-text="--font-jetbrains-mono: &quot;JetBrains Mono&quot;, monospace;"', $output, 'Family details should still expose the base family variable when extended output is disabled.');
    assertContainsValue('data-copy-text="--font-monospace: &quot;JetBrains Mono&quot;, monospace;"', $output, 'Family details should still expose the active role variable when extended output is disabled.');
    assertNotContainsValue('data-copy-text="--font-code: var(--font-monospace);"', $output, 'Family details should hide extended code aliases when extended output is disabled.');
    assertNotContainsValue('data-copy-text="--font-mono: var(--font-jetbrains-mono);"', $output, 'Family details should hide category aliases when extended output is disabled.');
};

$tests['admin_page_renderer_hides_mono_alias_when_monospace_feature_is_disabled'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'JetBrains Mono',
        'slug' => 'jetbrains-mono',
        'font_category' => 'monospace',
        'font_category_tokens' => ['monospace'],
        'delivery_filter_tokens' => ['published'],
        'publish_state' => 'published',
        'active_delivery_id' => 'local-self-hosted',
        'active_delivery' => [
            'id' => 'local-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
        ],
        'available_deliveries' => [
            [
                'id' => 'local-self-hosted',
                'label' => 'Self-hosted',
                'provider' => 'local',
                'type' => 'self_hosted',
                'variants' => ['regular'],
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'local',
                'files' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
                'paths' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
            ],
        ],
    ];

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFamilyRow',
        [
            $family,
            ['heading' => '', 'body' => '', 'monospace' => ''],
            [],
            [],
            [
                ['value' => 'inherit', 'label' => 'Use plugin default'],
            ],
            'The quick brown fox jumps over the lazy dog.',
            [],
            ['enabled' => true, 'weight_tokens' => true, 'role_aliases' => true, 'category_sans' => true, 'category_serif' => true, 'category_mono' => false],
            false,
        ]
    );
    $output = (string) ob_get_clean();

    assertNotContainsValue('data-copy-text="--font-mono: var(--font-jetbrains-mono);"', $output, 'Family details should not expose the mono alias when the monospace feature is disabled.');
    assertNotContainsValue('data-copy-text="--font-code: var(--font-monospace);"', $output, 'Family details should not expose the code alias when the monospace feature is disabled.');
};

$tests['admin_page_renderer_uses_raw_face_weights_when_extended_variables_are_disabled'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFaceDetailCard',
        [
            'Inter',
            'inter',
            '"Inter", sans-serif',
            'The quick brown fox jumps over the lazy dog.',
            2,
            ['body'],
            'sans-serif',
            ['--font-sans' => 'Inter'],
            ['enabled' => false, 'weight_tokens' => true, 'role_aliases' => true, 'category_sans' => true, 'category_serif' => true, 'category_mono' => true],
            ['provider' => 'local', 'type' => 'self_hosted'],
            [
                'weight' => '700',
                'style' => 'italic',
                'source' => 'local',
                'files' => ['woff2' => 'inter/Inter-700-italic.woff2'],
                'paths' => ['woff2' => 'inter/Inter-700-italic.woff2'],
            ],
            false,
        ]
    );
    $output = (string) ob_get_clean();

    assertContainsValue('data-copy-text="font-weight: 700;"', $output, 'Face detail cards should fall back to raw numeric weights when extended output is disabled.');
    assertContainsValue('data-copy-text="font-family: var(--font-inter); font-weight: 700; font-style: italic;"', $output, 'Combined face snippets should use raw font-weight values when extended output is disabled.');
    assertNotContainsValue('data-copy-text="font-weight: var(--weight-bold);"', $output, 'Face detail cards should not expose weight variables when extended output is disabled.');
};

$tests['admin_page_renderer_hides_category_alias_when_the_family_does_not_win_it'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFaceDetailCard',
        [
            'Inter',
            'inter',
            '"Inter", sans-serif',
            'The quick brown fox jumps over the lazy dog.',
            2,
            [],
            'sans-serif',
            ['--font-sans' => 'Noto Sans'],
            ['enabled' => true, 'weight_tokens' => true, 'role_aliases' => true, 'category_sans' => true, 'category_serif' => true, 'category_mono' => true],
            ['provider' => 'local', 'type' => 'self_hosted'],
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'local',
                'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            ],
            false,
        ]
    );
    $output = (string) ob_get_clean();

    assertNotContainsValue('data-copy-text="--font-sans: var(--font-inter);"', $output, 'Face detail cards should not show category aliases now that family-scoped variables live in the family detail section.');
};

$tests['admin_page_renderer_uses_category_aware_family_preview_fallbacks'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'JetBrains Mono',
        'slug' => 'jetbrains-mono',
        'font_category' => 'monospace',
        'font_category_tokens' => ['monospace'],
        'delivery_filter_tokens' => ['published'],
        'publish_state' => 'published',
        'active_delivery_id' => 'google-cdn',
        'active_delivery' => [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular'],
        ],
        'available_deliveries' => [
            [
                'id' => 'google-cdn',
                'label' => 'Google CDN',
                'provider' => 'google',
                'type' => 'cdn',
                'variants' => ['regular'],
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'google',
                'files' => [],
                'paths' => [],
            ],
        ],
    ];

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFamilyRow',
        [
            $family,
            ['heading' => '', 'body' => '', 'monospace' => ''],
            [],
            [],
            [
                ['value' => 'inherit', 'label' => 'Use plugin default'],
            ],
            'The quick brown fox jumps over the lazy dog.',
            [],
            ['enabled' => true, 'weight_tokens' => true, 'role_aliases' => true, 'category_sans' => true, 'category_serif' => true, 'category_mono' => true],
            true,
        ]
    );
    $output = (string) ob_get_clean();

    assertContainsValue('data-copy-text="&quot;JetBrains Mono&quot;, monospace"', $output, 'Family preview stacks should default monospace catalog families to the monospace generic fallback.');
};

$tests['admin_page_renderer_translates_stored_delivery_profile_labels_at_output'] = static function (): void {
    resetTestState();

    global $translationMap;

    $translationMap = [
        'Self-hosted (Google import)' => 'Import Google auto-heberge',
        'Adobe-hosted' => 'Heberge par Adobe',
        'Same-origin self-hosted files' => 'Fichiers auto-heberges meme origine',
        'Adobe-hosted project stylesheet' => 'Feuille de style de projet Adobe hebergee',
    ];

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'Inter',
        'slug' => 'inter',
        'delivery_filter_tokens' => ['google', 'adobe'],
        'publish_state' => 'published',
        'active_delivery_id' => 'google-self_hosted',
        'active_delivery' => [
            'id' => 'google-self_hosted',
            'label' => 'Self-hosted (Google import)',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular'],
        ],
        'available_deliveries' => [
            [
                'id' => 'google-self_hosted',
                'label' => 'Self-hosted (Google import)',
                'provider' => 'google',
                'type' => 'self_hosted',
                'variants' => ['regular'],
            ],
            [
                'id' => 'adobe-adobe_hosted',
                'label' => 'Adobe-hosted',
                'provider' => 'adobe',
                'type' => 'adobe_hosted',
                'variants' => [],
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'google',
                'files' => [],
                'paths' => [],
            ],
        ],
    ];

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFamilyRow',
        [
            $family,
            ['heading' => '', 'body' => ''],
            [],
            [],
            [
                ['value' => 'inherit', 'label' => 'Use plugin default'],
            ],
            'The quick brown fox jumps over the lazy dog.',
            [],
            ['enabled' => true, 'weight_tokens' => true, 'role_aliases' => true, 'category_sans' => true, 'category_serif' => true, 'category_mono' => true],
        ]
    );
    $output = (string) ob_get_clean();

    assertContainsValue('Import Google auto-heberge', $output, 'Stored English delivery labels should be translated when rendered in the family card.');
    assertContainsValue('Heberge par Adobe', $output, 'Stored English delivery labels should be translated in delivery lists and detail cards.');
    assertContainsValue('Fichiers auto-heberges meme origine', $output, 'Self-hosted request summaries should stay translatable at render time.');
    assertContainsValue('Feuille de style de projet Adobe hebergee', $output, 'Adobe request summaries should stay translatable at render time.');
    assertNotContainsValue('Self-hosted (Google import)', $output, 'The family card should not render the raw stored English Google self-hosted label once translated.');
    assertNotContainsValue('Adobe-hosted', $output, 'The family card should not render the raw stored English Adobe label once translated.');
};

$tests['admin_page_renderer_exposes_plugin_behavior_tab_and_can_hide_help_ui'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => [],
        'roles' => [],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'block_editor_font_library_sync_enabled' => false,
        'training_wheels_off' => true,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [],
        'local_environment_notice' => [],
        'toasts' => [],
        'apply_everywhere' => false,
        'role_deployment' => [
            'badge' => 'Live',
            'badge_class' => 'is-success',
            'title' => 'Live',
            'copy' => 'Current selections are being served sitewide.',
        ],
    ]);
    $output = (string) ob_get_clean();

    assertContainsValue('Plugin Behavior', $output, 'The advanced tools switcher should expose a dedicated Plugin Behavior tab.');
    assertSameValue(1, substr_count($output, 'Enable Block Editor Font Library Sync'), 'The Plugin Behavior panel should render the editor sync toggle exactly once.');
    assertSameValue(1, substr_count($output, 'Enable Monospace Role'), 'The Plugin Behavior panel should render the monospace toggle exactly once.');
    assertContainsValue('Training Wheels Off', $output, 'The Plugin Behavior tab should expose the training-wheels toggle.');
    assertContainsValue('Uninstall Settings', $output, 'The Plugin Behavior tab should group uninstall cleanup controls under an uninstall settings heading.');
    assertSameValue(1, substr_count($output, 'Delete uploaded fonts on uninstall'), 'The uninstall cleanup toggle should appear once in the Plugin Behavior panel instead of being duplicated elsewhere.');
    assertContainsValue('is-training-wheels-off', $output, 'Training Wheels Off should add the admin state class used to suppress descriptive copy.');
    assertContainsValue('Professional Typography Management For WordPress', $output, 'The hero tagline should remain rendered even when training wheels are turned off.');
    assertNotContainsValue('tasty-fonts-help-button', $output, 'Training Wheels Off should remove inline help buttons from the rendered admin UI.');
    assertNotContainsValue('data-help-tooltip=', $output, 'Training Wheels Off should omit passive hover help attributes from the rendered admin UI.');

    $adminCss = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/admin.css');
    assertNotContainsValue(
        '.tasty-fonts-admin.is-training-wheels-off .tasty-fonts-hero-text',
        $adminCss,
        'Training Wheels Off should not hide the hero tagline.'
    );
};

$tests['admin_page_renderer_restructures_role_toolbar_with_explicit_actions'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => ['Inter'],
        'roles' => [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'block_editor_font_library_sync_enabled' => false,
        'training_wheels_off' => false,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [],
        'local_environment_notice' => [],
        'toasts' => [],
        'apply_everywhere' => true,
        'role_deployment' => [
            'badge' => 'Live',
            'badge_class' => 'is-success',
            'title' => 'Live',
            'copy' => 'Current selections are being served sitewide.',
        ],
    ]);
    $output = (string) ob_get_clean();
    $deploymentPosition = strpos($output, 'Deployment Controls');
    $selectionPosition = strpos($output, 'Role Selection');
    $sectionStatusPosition = strpos($output, 'Sitewide on');
    $headingVariablePosition = strpos($output, 'data-role-variable-copy="heading"');
    $bodyVariablePosition = strpos($output, 'data-role-variable-copy="body"');

    assertContainsValue('Apply Sitewide', $output, 'The Font Roles form should expose an explicit apply sitewide action.');
    assertContainsValue('Switch off Sitewide', $output, 'The Font Roles form should expose an explicit switch-off sitewide action.');
    assertContainsValue('Update Live Roles', $output, 'The Font Roles form should keep a direct publish action in the role actions card.');
    assertContainsValue('data-disclosure-toggle="tasty-fonts-role-preview-panel"', $output, 'The utilities card should expose a dedicated preview disclosure button.');
    assertNotContainsValue('>Font Roles<', $output, 'The top panel should no longer render the obsolete Font Roles heading.');
    assertContainsValue('tasty-fonts-studio-section tasty-fonts-role-command-deck', $output, 'Deployment controls should use the shared studio section pattern.');
    assertContainsValue('tasty-fonts-studio-section tasty-fonts-role-selection', $output, 'Role selection should use the same shared studio section pattern as deployment controls.');
    assertContainsValue('tasty-fonts-studio-card tasty-fonts-role-box', $output, 'Role selection cards should use the shared studio card pattern.');
    assertContainsValue('tasty-fonts-pill tasty-fonts-pill--status tasty-fonts-pill--interactive tasty-fonts-pill--help tasty-fonts-role-command-status is-live', $output, 'The sitewide deployment state should use the shared help pill pattern.');
    assertContainsValue('tasty-fonts-pill tasty-fonts-pill--status tasty-fonts-pill--interactive tasty-fonts-pill--help tasty-fonts-role-status-pill', $output, 'The deployment status badge should use the shared help pill pattern.');
    assertContainsValue('tasty-fonts-pill tasty-fonts-pill--code tasty-fonts-pill--interactive tasty-fonts-pill--copy tasty-fonts-kbd tasty-fonts-role-stack-copy', $output, 'Role variable copy pills should use the shared copy pill pattern.');
    assertContainsValue('Role Selection', $output, 'The Font Roles form should expose a dedicated role selection section after the deployment controls.');
    assertNotContainsValue('Current Output', $output, 'The Font Roles form should no longer render the obsolete current output summary.');
    assertNotContainsValue('data-role-sitewide-toggle', $output, 'The Font Roles form should no longer render the legacy sitewide toggle control.');
    assertSameValue(true, $deploymentPosition !== false && $selectionPosition !== false && $deploymentPosition < $selectionPosition, 'The Font Roles workflow should surface deployment controls before role selection.');
    assertSameValue(true, $sectionStatusPosition !== false && $selectionPosition !== false && $sectionStatusPosition < $selectionPosition, 'The section status pill should stay in the deployment summary before role selection.');
    assertSameValue(true, $headingVariablePosition !== false && $selectionPosition !== false && $headingVariablePosition > $selectionPosition, 'The heading variable pill should live in the role selection summary.');
    assertSameValue(true, $bodyVariablePosition !== false && $selectionPosition !== false && $bodyVariablePosition > $selectionPosition, 'The body variable pill should live in the role selection summary.');
};

$tests['admin_page_renderer_only_highlights_update_live_roles_when_changes_are_pending'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => ['Inter', 'Lora'],
        'roles' => [
            'heading' => 'Inter',
            'body' => 'Lora',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        'applied_roles' => [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'training_wheels_off' => false,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [],
        'toasts' => [],
        'apply_everywhere' => true,
        'role_deployment' => [],
    ]);
    $pendingOutput = (string) ob_get_clean();

    assertContainsValue('button button-primary is-pending-live-change tasty-fonts-scope-button tasty-fonts-scope-button--apply', $pendingOutput, 'Update Live Roles should stay highlighted when the draft differs from the live applied roles.');
    assertContainsValue('data-role-apply-live aria-disabled="false"', $pendingOutput, 'Update Live Roles should remain active when there are live changes pending.');

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => ['Inter', 'Lora'],
        'roles' => [
            'heading' => 'Inter',
            'body' => 'Lora',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        'applied_roles' => [
            'heading' => 'Inter',
            'body' => 'Lora',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'training_wheels_off' => false,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [],
        'toasts' => [],
        'apply_everywhere' => true,
        'role_deployment' => [],
    ]);
    $matchedOutput = (string) ob_get_clean();

    assertContainsValue('class="button tasty-fonts-scope-button tasty-fonts-scope-button--apply"', $matchedOutput, 'Update Live Roles should fall back to the shared neutral button styling when the draft already matches the live applied roles.');
    assertContainsValue('data-role-apply-live aria-disabled="true" disabled', $matchedOutput, 'Update Live Roles should use the real disabled attribute when there is nothing new to publish.');
    assertContainsValue('No live role changes to publish.', $matchedOutput, 'The disabled Update Live Roles action should explain why it is unavailable.');
    assertContainsValue('data-role-save-draft aria-disabled="true" disabled', $matchedOutput, 'Save Roles should start disabled until the draft changes.');
    assertContainsValue('No draft changes to save.', $matchedOutput, 'The disabled Save Roles action should explain why it is unavailable.');
};

$tests['admin_page_renderer_renders_highlighted_snippet_panels_with_icon_copy_buttons'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => ['Inter'],
        'roles' => [
            'heading' => 'Inter',
            'body' => '',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => false,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'training_wheels_off' => false,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [
            [
                'key' => 'usage',
                'label' => 'Site Snippet',
                'target' => 'tasty-fonts-output-usage',
                'value' => ":root {\n    --font-heading: \"Inter\", sans-serif;\n}\nbody {\n    font-family: var(--font-heading);\n}",
                'active' => true,
            ],
        ],
        'generated_css_panel' => [
            'key' => 'generated',
            'label' => 'Generated CSS',
            'target' => 'tasty-fonts-output-generated',
            'value' => "@font-face {\n    font-family: \"Inter\";\n}",
        ],
        'preview_panels' => [],
        'toasts' => [],
        'apply_everywhere' => false,
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();

    assertContainsValue('class="button tasty-fonts-output-copy-button"', $output, 'Snippet panels should render the shared icon-only copy button.');
    assertContainsValue('data-copy-success="Snippet copied."', $output, 'Snippet panels should keep the shared copy feedback message.');
    assertContainsValue('<div class="tasty-fonts-code-panel-body" data-snippet-display>', $output, 'Snippet panels should wrap highlighted output in the shared code panel body.');
    assertContainsValue('<pre class="tasty-fonts-output" data-snippet-view="raw"><code id="tasty-fonts-output-usage" class="tasty-fonts-output-code">', $output, 'Snippet panels should render highlighted code blocks instead of textareas.');
    assertContainsValue('tasty-fonts-syntax-property', $output, 'Snippet panels should wrap CSS properties in syntax token markup.');
    assertContainsValue('tasty-fonts-syntax-string', $output, 'Snippet panels should wrap strings in syntax token markup.');
    assertNotContainsValue('<textarea id="tasty-fonts-output-usage"', $output, 'Snippet panels should no longer render plain textareas.');
};

$tests['admin_page_renderer_pretty_prints_minified_snippets_for_highlighted_display'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => ['Inter', 'Noto Sans', 'JetBrains Mono'],
        'roles' => [
            'heading' => 'Inter',
            'body' => 'Noto Sans',
            'monospace' => 'JetBrains Mono',
            'heading_fallback' => 'serif',
            'body_fallback' => 'sans-serif',
            'monospace_fallback' => 'monospace',
        ],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'training_wheels_off' => false,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [
            [
                'key' => 'usage',
                'label' => 'Site Snippet',
                'target' => 'tasty-fonts-output-usage',
                'value' => ':root{--font-heading:"Inter",serif;--font-body:"Noto Sans",sans-serif}body{font-family:var(--font-body)}',
                'active' => true,
            ],
        ],
        'generated_css_panel' => [],
        'preview_panels' => [],
        'toasts' => [],
        'apply_everywhere' => false,
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();

    assertContainsValue('<span class="tasty-fonts-syntax-selector">:root</span>', $output, 'Minified snippet selectors should still be highlighted after display formatting.');
    assertContainsValue('<span class="tasty-fonts-syntax-property">font-family</span>', $output, 'Minified snippet declarations should be split into lines so property highlighting still applies.');
    assertContainsValue('data-copy-text=":root{--font-heading:&quot;Inter&quot;,serif;--font-body:&quot;Noto Sans&quot;,sans-serif}body{font-family:var(--font-body)}"', $output, 'Display formatting should not change the copied snippet payload.');
};

$tests['admin_page_renderer_generated_css_defaults_to_actual_minified_output_with_readable_toggle'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => ['Inter'],
        'roles' => [
            'heading' => 'Inter',
            'body' => '',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'training_wheels_off' => false,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [
            'key' => 'generated',
            'label' => 'Generated CSS',
            'target' => 'tasty-fonts-output-generated',
            'value' => ':root{--font-heading:"Inter",serif}body{font-family:var(--font-heading)}',
        ],
        'preview_panels' => [],
        'toasts' => [],
        'apply_everywhere' => true,
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();

    assertContainsValue('data-snippet-display-toggle', $output, 'Generated CSS should expose a display-only toggle when minified output is enabled.');
    assertContainsValue('data-label-default="Readable preview"', $output, 'Generated CSS should offer a readable preview action from the actual output view.');
    assertContainsValue('data-label-active="Show actual output"', $output, 'Generated CSS should provide a way back to the actual saved output view.');
    assertContainsValue('<pre class="tasty-fonts-output" data-snippet-view="raw"><code id="tasty-fonts-output-generated" class="tasty-fonts-output-code">', $output, 'Generated CSS should render the actual output view as the default visible block.');
    assertContainsValue('data-snippet-view="readable" hidden', $output, 'Generated CSS should render a hidden readable view for toggling.');
    assertContainsValue('data-copy-text=":root{--font-heading:&quot;Inter&quot;,serif}body{font-family:var(--font-heading)}"', $output, 'Generated CSS copy payloads should stay on the true minified output.');
};

$tests['admin_page_renderer_generated_css_omits_readable_toggle_when_output_is_already_unminified'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => ['Inter'],
        'roles' => [
            'heading' => 'Inter',
            'body' => '',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => false,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'training_wheels_off' => false,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [
            'key' => 'generated',
            'label' => 'Generated CSS',
            'target' => 'tasty-fonts-output-generated',
            'value' => "@font-face {\n    font-family: \"Inter\";\n}",
        ],
        'preview_panels' => [],
        'toasts' => [],
        'apply_everywhere' => true,
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();

    assertNotContainsValue('data-snippet-display-toggle', $output, 'Generated CSS should not render a readable toggle when the saved output is already readable.');
    assertNotContainsValue('data-snippet-view="readable"', $output, 'Generated CSS should only render one view when no alternate preview is needed.');
};

$tests['admin_page_renderer_renders_local_environment_notice_below_activity_with_reminder_actions'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => [],
        'roles' => [],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'block_editor_font_library_sync_enabled' => false,
        'training_wheels_off' => false,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [],
        'local_environment_notice' => [
            'tone' => 'warning',
            'title' => 'Local environment detected',
            'message' => 'Turn this on when your local PHP/cURL setup trusts the site certificate.',
            'settings_label' => 'Open Plugin Behavior',
            'settings_url' => 'https://example.test/wp-admin/admin.php?page=tasty-custom-fonts&tf_advanced=1&tf_studio=plugin-behavior',
        ],
        'toasts' => [],
        'apply_everywhere' => false,
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();
    $activityPosition = strpos($output, 'No activity yet');
    $noticePosition = strpos($output, 'Local environment detected');

    assertContainsValue('Local environment detected', $output, 'The admin page should surface a dedicated notice for local environments.');
    assertContainsValue('Open Plugin Behavior', $output, 'The local-environment notice should include a direct action to open the Plugin Behavior panel.');
    assertContainsValue('Remind Tomorrow', $output, 'The local-environment notice should allow users to snooze the reminder until tomorrow.');
    assertContainsValue('Remind in 1 Week', $output, 'The local-environment notice should allow users to snooze the reminder for one week.');
    assertContainsValue('Never Show Again', $output, 'The local-environment notice should allow users to hide the reminder permanently for their account.');
    assertContainsValue('tf_studio=plugin-behavior', $output, 'The local-environment notice action should deep-link to the Plugin Behavior tab.');
    assertSameValue(true, $activityPosition !== false && $noticePosition !== false && $activityPosition < $noticePosition, 'The local-environment notice should render after the Activity section.');
};

$tests['admin_page_renderer_renders_activity_log_action_links'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => [],
        'roles' => [],
        'logs' => [[
            'time' => '2026-04-06 15:00:00',
            'message' => 'Block Editor Font Library sync failed.',
            'actor' => 'System',
            'action_label' => 'Open Plugin Behavior',
            'action_url' => 'https://example.test/wp-admin/admin.php?page=tasty-custom-fonts&tf_advanced=1&tf_studio=plugin-behavior',
        ]],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'block_editor_font_library_sync_enabled' => false,
        'training_wheels_off' => false,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [],
        'local_environment_notice' => [],
        'toasts' => [],
        'apply_everywhere' => false,
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();

    assertContainsValue('Open Plugin Behavior', $output, 'Activity log entries should render an inline action link when one is provided.');
    assertContainsValue('tasty-fonts-log-action', $output, 'Activity log action links should use the dedicated styling hook.');
};

$tests['admin_page_renderer_renders_monospace_role_ui_when_enabled'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => ['Inter', 'JetBrains Mono'],
        'roles' => [
            'heading' => 'Inter',
            'body' => 'Inter',
            'monospace' => '',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
            'monospace_fallback' => 'monospace',
        ],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'training_wheels_off' => false,
        'monospace_role_enabled' => true,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [['key' => 'editorial', 'label' => 'Specimen', 'active' => true]],
        'toasts' => [],
        'apply_everywhere' => false,
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();

    assertContainsValue('Monospace Font', $output, 'Enabled monospace support should render the third role box in the main Font Roles form.');
    assertContainsValue('tasty-fonts-role-grid is-three-columns', $output, 'Enabled monospace support should switch the role grid into the three-column layout modifier.');
    assertContainsValue('Use fallback only', $output, 'Enabled monospace support should render the fallback-only monospace family option.');
    assertContainsValue('var(--font-monospace)', $output, 'Enabled monospace support should expose the monospace role variable in the role UI.');
};

$tests['admin_page_renderer_allows_fallback_only_heading_and_body_roles'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => ['Inter', 'Lora'],
        'roles' => [
            'heading' => '',
            'body' => '',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'training_wheels_off' => false,
        'monospace_role_enabled' => false,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [['key' => 'editorial', 'label' => 'Specimen', 'active' => true]],
        'toasts' => [],
        'apply_everywhere' => false,
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();

    assertContainsValue('name="tasty_fonts_heading_font"', $output, 'The role form should render the heading family selector.');
    assertContainsValue('name="tasty_fonts_body_font"', $output, 'The role form should render the body family selector.');
    assertContainsValue('name="tasty_fonts_heading_font" id="tasty_fonts_heading_font"', $output, 'The heading family selector should keep its expected id.');
    assertContainsValue('name="tasty_fonts_body_font" id="tasty_fonts_body_font"', $output, 'The body family selector should keep its expected id.');
    assertSameValue(true, substr_count($output, 'Use fallback only') >= 3, 'Heading, body, and preview selectors should all expose fallback-only choices.');
    assertContainsValue('Fallback only (sans-serif)', $output, 'Fallback-only heading selections should render a readable preview label.');
    assertContainsValue('Fallback only (serif)', $output, 'Fallback-only body selections should render a readable preview label.');
};

$tests['admin_page_renderer_preview_workspace_defaults_to_live_sitewide_baseline'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => ['Inter', 'Lora', 'JetBrains Mono'],
        'roles' => [
            'heading' => 'Inter',
            'body' => 'Inter',
            'monospace' => 'JetBrains Mono',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
            'monospace_fallback' => 'monospace',
        ],
        'applied_roles' => [
            'heading' => 'Lora',
            'body' => 'Inter',
            'monospace' => 'JetBrains Mono',
            'heading_fallback' => 'serif',
            'body_fallback' => 'sans-serif',
            'monospace_fallback' => 'monospace',
        ],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'training_wheels_off' => false,
        'monospace_role_enabled' => true,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [['key' => 'editorial', 'label' => 'Specimen', 'active' => true]],
        'toasts' => [],
        'apply_everywhere' => true,
        'preview_baseline_source' => 'live_sitewide',
        'preview_baseline_label' => 'Live sitewide',
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();

    assertContainsValue('Previewing:', $output, 'The preview workspace should render a visible source label.');
    assertContainsValue('Live sitewide', $output, 'The preview workspace should disclose when it is seeded from the live sitewide roles.');
    assertContainsValue('data-preview-role-select="heading"', $output, 'The preview tray should expose a heading picker.');
    assertContainsValue('data-preview-role-select="body"', $output, 'The preview tray should expose a body picker.');
    assertContainsValue('data-preview-role-select="monospace"', $output, 'The preview tray should expose a monospace picker when the role is enabled.');
    assertContainsValue('Use current draft selections', $output, 'The live baseline preview should offer a quick way to compare against the current draft roles.');
    assertContainsValue('value="Lora" selected', $output, 'The preview tray should seed its live baseline selector values from the applied sitewide roles.');
};

$tests['admin_page_renderer_preview_workspace_defaults_to_draft_baseline'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => ['Inter', 'Lora'],
        'roles' => [
            'heading' => 'Inter',
            'body' => 'Lora',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        'applied_roles' => [
            'heading' => 'Lora',
            'body' => 'Inter',
            'heading_fallback' => 'serif',
            'body_fallback' => 'sans-serif',
        ],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'training_wheels_off' => false,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [['key' => 'editorial', 'label' => 'Specimen', 'active' => true]],
        'toasts' => [],
        'apply_everywhere' => false,
        'preview_baseline_source' => 'draft',
        'preview_baseline_label' => 'Current draft',
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();

    assertContainsValue('Current draft', $output, 'The preview workspace should disclose when it is seeded from the draft role selections.');
    assertContainsValue('Sync preview to role draft', $output, 'The draft baseline preview should offer a resync action for the current role controls.');
    assertNotContainsValue('data-preview-role-select="monospace"', $output, 'The preview tray should omit the monospace picker when the role is disabled.');
};

$tests['admin_page_renderer_uses_a_dedicated_code_preview_scene'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    $renderer->renderPage([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => ['Inter', 'JetBrains Mono'],
        'roles' => [
            'heading' => 'Inter',
            'body' => 'Inter',
            'monospace' => 'JetBrains Mono',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
            'monospace_fallback' => 'monospace',
        ],
        'logs' => [],
        'activity_actor_options' => [],
        'family_fallbacks' => [],
        'family_font_displays' => [],
        'family_font_display_options' => [],
        'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
        'preview_size' => 32,
        'font_display' => 'optional',
        'font_display_options' => [],
        'minify_css_output' => true,
        'preload_primary_fonts' => true,
        'remote_connection_hints' => true,
        'training_wheels_off' => false,
        'monospace_role_enabled' => true,
        'delete_uploaded_files_on_uninstall' => false,
        'diagnostic_items' => [],
        'overview_metrics' => [],
        'output_panels' => [],
        'generated_css_panel' => [],
        'preview_panels' => [
            ['key' => 'editorial', 'label' => 'Specimen', 'active' => false],
            ['key' => 'card', 'label' => 'Card', 'active' => false],
            ['key' => 'reading', 'label' => 'Reading', 'active' => false],
            ['key' => 'interface', 'label' => 'Interface', 'active' => false],
            ['key' => 'code', 'label' => 'Code', 'active' => true],
        ],
        'toasts' => [],
        'apply_everywhere' => false,
        'role_deployment' => [],
    ]);
    $output = (string) ob_get_clean();

    assertContainsValue('tasty-fonts-preview-scene--code', $output, 'The preview renderer should expose a dedicated Code scene.');
    assertContainsValue('data-tab-target="code"', $output, 'The preview tabs should include the new Code tab.');
    assertContainsValue('typography-preview.tsx', $output, 'The Code scene should render an editor-style file tab.');
    assertContainsValue('Published Code Block', $output, 'The Code scene should render a published code block surface.');
    assertContainsValue('tasty-fonts-preview-token-keyword', $output, 'The Code scene should render syntax-highlight token spans.');
    assertNotContainsValue('const fontRole', $output, 'Legacy inline monospace snippets should be removed from the older preview scenes.');
    assertNotContainsValue('Heading + Body + Mono', $output, 'The card preview should no longer render the old monospace inline sample.');
    assertNotContainsValue('$ wp option get tasty_fonts_settings', $output, 'The reading preview should no longer render the old command-line monospace sample.');
    assertNotContainsValue('npm run build -- --watch', $output, 'The specimen preview should no longer render the old monospace sample row.');
};

$tests['admin_page_renderer_family_cards_expose_monospace_assignments_and_variant_guards'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'JetBrains Mono',
        'slug' => 'jetbrains-mono',
        'delivery_filter_tokens' => ['local'],
        'publish_state' => 'published',
        'active_delivery_id' => 'local-self-hosted',
        'active_delivery' => [
            'id' => 'local-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
        ],
        'available_deliveries' => [
            [
                'id' => 'local-self-hosted',
                'label' => 'Self-hosted',
                'provider' => 'local',
                'type' => 'self_hosted',
                'variants' => ['regular'],
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'local',
                'files' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
                'paths' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
            ],
        ],
    ];

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFamilyRow',
        [
            $family,
            ['heading' => 'Inter', 'body' => 'Inter', 'monospace' => 'JetBrains Mono'],
            [],
            [],
            [
                ['value' => 'inherit', 'label' => 'Use plugin default'],
                ['value' => 'swap', 'label' => 'swap'],
            ],
            'The quick brown fox jumps over the lazy dog.',
            [],
            ['enabled' => true, 'weight_tokens' => true, 'role_aliases' => true, 'category_sans' => true, 'category_serif' => true, 'category_mono' => true],
            true,
        ]
    );
    $output = (string) ob_get_clean();

    assertContainsValue('data-role-assign="monospace"', $output, 'Enabled family cards should expose the monospace quick-assign control.');
    assertContainsValue('>Monospace<', $output, 'Enabled family cards should render a Monospace badge for the selected monospace family.');
    assertContainsValue('Code Preview', $output, 'Monospace family cards should switch their specimen label to a code-oriented preview.');
    assertContainsValue('tasty-fonts-font-inline-preview is-monospace', $output, 'Monospace library cards should render the inline preview with the monospace modifier class.');
    assertContainsValue('tasty-fonts-face-preview is-monospace', $output, 'Monospace face detail cards should render the preview with the monospace modifier class.');
    assertContainsValue('400 Regular', $output, 'Expanded face detail cards should pair numeric weights with a readable weight label.');
    assertContainsValue('>const font = &quot;JetBrains Mono&quot;;', $output, 'Monospace preview markup should not inject template indentation before the code sample text.');
    assertNotContainsValue('font-family: var(--font-monospace);', $output, 'Monospace card previews should now stay on a single code line instead of rendering multiline specimen copy.');
    assertContainsValue('currently assigned to monospace, and this is the last saved variant', $output, 'Last-variant delete guards should mention the monospace role when it protects the family.');
};
