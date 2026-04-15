<?php

declare(strict_types=1);

use TastyFonts\Admin\AdminPageRenderer;
use TastyFonts\Admin\AdminPageViewBuilder;
use TastyFonts\Admin\Renderer\FamilyCardRenderer;
use TastyFonts\Admin\Renderer\PreviewSectionRenderer;
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
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('data-font-slug="inter"', $output, 'Library family rows should expose the normalized slug for client-side matching and post-import highlighting.');
    assertContainsValue('tasty-fonts-badges--library-inline', $output, 'Single-delivery families should render the compact inline badge treatment.');
    assertContainsValue('Google CDN', $output, 'The active single delivery label should stay visible on the family card.');
    assertContainsValue('aria-label="Copy font stack for Inter: &quot;Inter&quot;, sans-serif"', $output, 'Copyable font stacks should expose the copied stack value in the accessible name.');
    assertContainsValue('tasty-fonts-detail-group', $output, 'Family details should render as design-system card groups instead of plain WordPress tables.');
    assertNotContainsValue('Available delivery profiles', $output, 'Single-delivery families should not render the verbose available deliveries note.');
    assertNotContainsValue('Live delivery', $output, 'Single-delivery families should not render the verbose live delivery note.');
    assertNotContainsValue('widefat striped tasty-fonts-table', $output, 'Family details should no longer use widefat table markup.');
};

$tests['family_card_renderer_summary_rows_omit_heavy_details_until_hydrated'] = static function (): void {
    resetTestState();

    $renderer = new FamilyCardRenderer(new Storage());
    $family = [
        'family' => 'Inter',
        'slug' => 'inter',
        'delivery_filter_tokens' => ['published', 'same-origin'],
        'font_category_tokens' => ['sans-serif'],
        'publish_state' => 'published',
        'active_delivery_id' => 'local-self-hosted',
        'active_delivery' => [
            'id' => 'local-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular', '700'],
        ],
        'available_deliveries' => [
            [
                'id' => 'local-self-hosted',
                'label' => 'Self-hosted',
                'provider' => 'local',
                'type' => 'self_hosted',
                'variants' => ['regular', '700'],
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'local',
                'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            ],
        ],
    ];

    ob_start();
    try {
        $renderer->renderFamilySummaryRow(
            $family,
            ['heading' => 'Inter', 'body' => ''],
            [],
            [],
            [
                ['value' => 'inherit', 'label' => 'Use plugin default'],
            ],
            'The quick brown fox jumps over the lazy dog.',
            [],
            ['enabled' => true, 'weight_tokens' => true, 'role_aliases' => true, 'category_sans' => true, 'category_serif' => true],
            false,
            ['enabled' => true, 'role_heading' => true, 'role_body' => true, 'role_alias_interface' => true, 'role_alias_ui' => true, 'category_sans' => true, 'families' => true]
        );
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('data-family-details-loaded="false"', $output, 'Summary family cards should mark their details containers as not yet hydrated.');
    assertContainsValue('data-family-details-toggle', $output, 'Summary family cards should expose a dedicated details trigger for lazy hydration.');
    assertContainsValue('data-family-details-status', $output, 'Summary family cards should render a status region for async detail loading.');
    assertNotContainsValue('Delivery Profiles', $output, 'Summary family cards should omit heavy delivery markup until hydrated.');
    assertNotContainsValue('Font Faces', $output, 'Summary family cards should omit face detail markup until hydrated.');
};

$tests['admin_page_renderer_hides_collapsed_delivery_notes_for_multi_delivery_families'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'Inter',
        'slug' => 'inter',
        'delivery_filter_tokens' => ['google', 'published'],
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
                'id' => 'local-self-hosted',
                'label' => 'Self-hosted',
                'provider' => 'local',
                'type' => 'self_hosted',
                'variants' => ['regular', '700'],
            ],
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
        ],
    ];

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertNotContainsValue('Available delivery profiles', $output, 'Multi-delivery families should not repeat the saved delivery list in the collapsed card.');
    assertNotContainsValue('Live delivery', $output, 'Multi-delivery families should not repeat the active delivery summary in the collapsed card.');
    assertNotContainsValue('Google CDN · External request via Google Fonts', $output, 'Multi-delivery families should keep request-path details inside the expanded delivery cards only.');
};

$tests['admin_page_renderer_renders_library_type_filter_and_category_tokens'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'preview_panels' => [['key' => 'code', 'label' => 'Code', 'active' => true]],
            'local_environment_notice' => [],
            'toasts' => [],
            'apply_everywhere' => false,
            'role_deployment' => [],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('data-library-category-filter', $output, 'The Font Library toolbar should render a dedicated type filter control.');
    assertContainsValue('All Types', $output, 'The library type filter should include the All Types option.');
    assertContainsValue('>Variable<', $output, 'The library type filter should expose the Variable option.');
    assertContainsValue('Cursive / Script', $output, 'The library type filter should expose the combined cursive/script option.');
    assertContainsValue('data-font-categories="monospace"', $output, 'Library rows should expose normalized font category tokens for client-side filtering.');
    assertContainsValue('>Monospace<', $output, 'Library rows should display the normalized font category badge.');
};

$tests['admin_page_renderer_renders_single_page_tabs_with_settings_active'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
        $renderer->renderPage([
            'storage' => ['root' => '/tmp/uploads/fonts'],
            'current_page' => 'settings',
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
            'css_delivery_mode' => 'file',
            'css_delivery_mode_options' => [
                ['value' => 'file', 'label' => 'Generated file'],
                ['value' => 'inline', 'label' => 'Inline in page head'],
            ],
            'font_display' => 'optional',
            'font_display_options' => [
                ['value' => 'optional', 'label' => 'Optional'],
                ['value' => 'swap', 'label' => 'Swap'],
            ],
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
            'output_panels' => [
                [
                    'key' => 'variables',
                    'label' => 'Variables',
                    'target' => 'tasty-fonts-output-vars',
                    'value' => ':root{--font-heading:"Inter",serif;}',
                    'active' => true,
                ],
                [
                    'key' => 'usage',
                    'label' => 'Usage',
                    'target' => 'tasty-fonts-output-usage',
                    'value' => 'body{font-family:var(--font-heading);}',
                    'active' => false,
                ],
            ],
            'generated_css_panel' => [],
            'preview_panels' => [
                ['key' => 'specimen', 'label' => 'Specimen', 'active' => true],
                ['key' => 'code', 'label' => 'Code', 'active' => false],
            ],
            'local_environment_notice' => [],
            'toasts' => [],
            'apply_everywhere' => false,
            'role_deployment' => [],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('data-tab-group="page"', $output, 'The unified admin page should render the top-level horizontal tab group.');
    assertContainsValue('tasty-fonts-page-tab-settings', $output, 'The unified admin page should include a Settings top-level tab.');
    assertContainsValue('<h2 class="tasty-fonts-section-title">Settings</h2>', $output, 'The Settings panel should keep a stable section heading regardless of tab activation order.');
    assertSameValue(4, substr_count($output, 'id="tasty-fonts-page-tab-'), 'The unified admin page should render four top-level page tabs.');
    assertSameValue(1, preg_match('/id="tasty-fonts-page-tab-library"[\s\S]*?tabindex="-1"/', $output), 'Inactive top-level page tabs should be removed from the normal keyboard tab order.');
    assertSameValue(1, preg_match('/id="tasty-fonts-settings-tab-integrations"[\s\S]*?tabindex="-1"/', $output), 'Inactive Settings sub-tabs should use roving tabindex.');
    assertSameValue(1, preg_match('/id="tasty-fonts-settings-tab-plugin-behavior"[\s\S]*?tabindex="-1"/', $output), 'Inactive Settings sub-tabs should use roving tabindex.');
    assertSameValue(1, preg_match('/id="tasty-fonts-settings-tab-transfer"[\s\S]*?tabindex="-1"/', $output), 'Inactive Settings sub-tabs should use roving tabindex.');
    assertSameValue(1, preg_match('/id="tasty-fonts-settings-tab-developer"[\s\S]*?tabindex="-1"/', $output), 'Inactive Settings sub-tabs should use roving tabindex.');
    assertSameValue(1, preg_match('/id="tasty-fonts-preview-tab-code"[\s\S]*?tabindex="-1"/', $output), 'Inactive preview tabs should be removed from the normal keyboard tab order.');
    assertSameValue(1, preg_match('/id="tasty-fonts-add-font-tab-bunny"[\s\S]*?tabindex="-1"/', $output), 'Inactive add-font tabs should be removed from the normal keyboard tab order.');
    assertSameValue(1, preg_match('/id="tasty-fonts-diagnostics-tab-system"[\s\S]*?tabindex="-1"/', $output), 'Inactive diagnostics tabs should be removed from the normal keyboard tab order.');
    assertSameValue(1, preg_match('/Version [0-9]+\.[0-9]+\.[0-9]+/', $output), 'The page heading should include the current plugin version for assistive technologies.');
    assertContainsValue('id="tasty-fonts-settings-form"', $output, 'The Settings tab should render one shared settings form for Output, Integrations, and Behavior.');
    assertContainsValue('data-settings-form="settings"', $output, 'The Settings tab should render shared explicit-save state tracking for the combined settings form.');
    assertNotContainsValue('data-settings-autosave="output"', $output, 'The Settings tab should no longer render autosave wiring for output settings.');
    assertNotContainsValue('data-settings-autosave="integrations"', $output, 'The Settings tab should no longer render autosave wiring for integrations settings.');
    assertNotContainsValue('data-settings-autosave="behavior"', $output, 'The Settings tab should no longer render autosave wiring for behavior settings.');
    assertContainsValue('form="tasty-fonts-settings-form"', $output, 'The shared Settings save button should submit the combined settings form from the header.');
    assertSameValue(1, substr_count($output, 'data-settings-save-button'), 'The shared Settings save control should render once in the header.');
    assertContainsValue('>Save changes<', $output, 'Settings sections should expose an explicit Save changes action.');
    assertContainsValue('>Font Library<', $output, 'The unified admin page should still include the library section inside its own tab panel.');
    assertContainsValue('Activity', $output, 'The unified admin page should keep diagnostics activity available in its diagnostics tab.');
    assertSameValue(1, substr_count($output, 'id="tasty-fonts-help-tooltip-layer"'), 'The shared help tooltip layer should be rendered once at the unified page shell level.');
};

$tests['admin_page_renderer_renders_single_page_library_tab_for_empty_and_populated_states'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
        $renderer->renderPage([
            'storage' => ['root' => '/tmp/uploads/fonts'],
            'current_page' => 'library',
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
            'local_environment_notice' => [],
            'toasts' => [],
            'apply_everywhere' => false,
            'role_deployment' => [],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $emptyOutput = (string) ob_get_clean();

    assertContainsValue('Your Library Is Empty', $emptyOutput, 'The unified Library tab should preserve the empty-library state.');
    assertContainsValue('<h2 class="tasty-fonts-section-title">Font Library</h2>', $emptyOutput, 'The library panel should keep a stable section heading regardless of tab activation order.');

    ob_start();
    try {
        $renderer->renderPage([
            'storage' => ['root' => '/tmp/uploads/fonts'],
            'current_page' => 'library',
            'catalog' => [
                'Inter' => [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'delivery_filter_tokens' => ['published', 'same-origin'],
                    'font_category' => 'sans-serif',
                    'font_category_tokens' => ['sans-serif'],
                    'publish_state' => 'published',
                    'active_delivery_id' => 'local-self-hosted',
                    'active_delivery' => [
                        'id' => 'local-self-hosted',
                        'label' => 'Self-hosted',
                        'provider' => 'local',
                        'type' => 'self_hosted',
                        'variants' => ['regular'],
                    ],
                    'available_deliveries' => [[
                        'id' => 'local-self-hosted',
                        'label' => 'Self-hosted',
                        'provider' => 'local',
                        'type' => 'self_hosted',
                        'variants' => ['regular'],
                    ]],
                    'delivery_badges' => [[
                        'label' => 'Published',
                        'class' => 'is-success',
                        'copy' => 'Published',
                    ]],
                    'faces' => [[
                        'weight' => '400',
                        'style' => 'normal',
                        'source' => 'local',
                        'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                        'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                    ]],
                ],
            ],
            'available_families' => ['Inter'],
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $populatedOutput = (string) ob_get_clean();

    assertContainsValue('data-font-slug="inter"', $populatedOutput, 'The unified Library tab should still render populated library cards.');
    assertNotContainsValue('Your Library Is Empty', $populatedOutput, 'The populated Library tab should not render the empty state.');
};

$tests['admin_page_renderer_only_shows_upload_variable_controls_when_variable_fonts_are_enabled'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $baseContext = [
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'current_page' => 'library',
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
        'local_environment_notice' => [],
        'toasts' => [],
        'apply_everywhere' => false,
        'role_deployment' => [],
    ];

    ob_start();
    try {
        $renderer->renderPage($baseContext + ['variable_fonts_enabled' => false]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $disabledOutput = (string) ob_get_clean();

    assertContainsValue('tasty-fonts-upload-face-shell--static-only', $disabledOutput, 'The upload builder should switch to the static-only layout when variable font support is off.');
    assertNotContainsValue('data-upload-field="is-variable"', $disabledOutput, 'The upload builder should not render variable upload toggles when variable font support is off.');

    ob_start();
    try {
        $renderer->renderPage($baseContext + ['variable_fonts_enabled' => true]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $enabledOutput = (string) ob_get_clean();

    assertNotContainsValue('tasty-fonts-upload-face-shell--static-only', $enabledOutput, 'The upload builder should keep the full upload grid when variable font support is on.');
    assertContainsValue('data-upload-field="is-variable"', $enabledOutput, 'The upload builder should render variable upload toggles when variable font support is on.');
};

$tests['admin_page_renderer_renders_extended_variable_submenu_controls'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('Variable Controls', $output, 'Output Settings should render a nested submenu for variable controls.');
    assertContainsValue('Global Weight Tokens', $output, 'The submenu should include a granular weight-token toggle.');
    assertContainsValue('Role Alias Variables', $output, 'The submenu should include a granular role-alias toggle.');
    assertContainsValue('Sans Alias', $output, 'The submenu should include a granular sans-category toggle.');
    assertContainsValue('Serif Alias', $output, 'The submenu should include a granular serif-category toggle.');
    assertNotContainsValue('Mono Alias', $output, 'The submenu should hide the mono-category toggle when the monospace feature is disabled.');
    assertNotContainsValue('--font-code', $output, 'The role-alias copy should not mention the code alias when the monospace feature is disabled.');
};

$tests['admin_page_renderer_renders_unified_output_controls'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'css_delivery_mode' => 'inline',
            'css_delivery_mode_options' => [
                ['value' => 'file', 'label' => 'Generated file'],
                ['value' => 'inline', 'label' => 'Inline in page head'],
            ],
            'font_display' => 'optional',
            'font_display_options' => [],
            'unicode_range_mode' => 'custom',
            'unicode_range_custom_value' => 'U+0000-00FF,U+0100-024F',
            'unicode_range_mode_options' => [
                ['value' => 'off', 'label' => 'Off'],
                ['value' => 'preserve', 'label' => 'Keep Imported Ranges'],
                ['value' => 'latin_basic', 'label' => 'Basic Latin'],
                ['value' => 'latin_extended', 'label' => 'Latin Extended'],
                ['value' => 'custom', 'label' => 'Custom'],
            ],
            'unicode_range_custom_visible' => true,
            'output_quick_mode_preference' => 'custom',
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('CSS Delivery', $output, 'Output Settings should render the CSS delivery control.');
    assertContainsValue('Unicode Range Output', $output, 'Output Settings should render the unicode-range output control.');
    assertContainsValue('name="unicode_range_mode"', $output, 'The unicode-range mode control should submit through the shared settings form.');
    assertContainsValue('name="unicode_range_custom_value"', $output, 'The custom unicode-range textarea should submit through the shared settings form.');
    assertSameValue(
        1,
        preg_match('/id="tasty-fonts-unicode-range-mode-off"[\s\S]*Keep Imported Ranges/', $output),
        'The Off option should render before the other unicode-range modes.'
    );
    assertSameValue(
        1,
        preg_match('/data-unicode-range-custom-wrap(?![^>]*hidden)/', $output),
        'The custom unicode-range textarea should be visible when custom mode is selected.'
    );
    assertContainsValue('name="css_delivery_mode"', $output, 'The CSS delivery control should submit through the shared settings form.');
    assertContainsValue('type="radio"', $output, 'Output Settings should render segmented radio controls for delivery and font display.');
    assertContainsValue('id="tasty-fonts-css-delivery-mode-inline"', $output, 'The CSS delivery inline option should render as a pill control.');
    assertSameValue(
        1,
        preg_match('/id="tasty-fonts-css-delivery-mode-inline"[\s\S]*checked="checked"/', $output),
        'The saved CSS delivery mode should remain selected in Output Settings.'
    );
    assertNotContainsValue('tasty-fonts-setting-pill-options', $output, 'Output Settings should use one shared segmented-control wrapper so CSS Delivery and Default Font Display match Output Preset.');
    assertContainsValue('Output Preset', $output, 'Output Settings should render the output quick-mode controls.');
    assertSameValue(
        0,
        preg_match('/name="tasty_fonts_output_quick_mode"[\s\S]*value="all"/', $output),
        'Output Settings should not render a separate all preset now that custom is the both-enabled baseline.'
    );
    assertContainsValue('value="minimal"', $output, 'The output quick-mode controls should expose the minimal preset.');
    assertContainsValue('value="classes"', $output, 'The output quick-mode controls should expose the classes-only option.');
    assertContainsValue('name="output_quick_mode_preference"', $output, 'The output quick-mode preference should submit through the shared settings form.');
    assertContainsValue('data-output-quick-mode-notice', $output, 'The output quick-mode section should render the zero-output guard notice.');
    assertContainsValue('name="class_output_enabled"', $output, 'The class output master toggle should submit through the shared settings form.');
    assertContainsValue('Family Classes', $output, 'Output Settings should group the per-family class toggle under its own submenu heading.');
    assertContainsValue('name="class_output_families_enabled"', $output, 'The granular family class toggle should submit through the shared settings form.');
    assertContainsValue('Emit Font Utility Classes', $output, 'Output Settings should render the class output master toggle.');
};

$tests['admin_page_view_builder_derives_output_quick_modes_from_explicit_flag_sets'] = static function (): void {
    $builder = new AdminPageViewBuilder(new Storage());

    $customAllEnabledMode = invokePrivateMethod(
        $builder,
        'deriveOutputQuickMode',
        [
            '',
            [
                'enabled' => true,
                'role_heading' => true,
                'role_body' => true,
                'role_monospace' => true,
                'role_alias_interface' => true,
                'role_alias_ui' => true,
                'role_alias_code' => true,
                'category_sans' => true,
                'category_serif' => true,
                'category_mono' => true,
                'families' => true,
            ],
            [
                'enabled' => true,
                'weight_tokens' => true,
                'role_aliases' => true,
                'category_sans' => true,
                'category_serif' => true,
                'category_mono' => true,
            ],
            false,
        ]
    );
    assertSameValue('custom', $customAllEnabledMode, 'Quick mode should resolve to custom when both class and variable output are enabled, even if every subgroup is on.');

    $minimalMode = invokePrivateMethod(
        $builder,
        'deriveOutputQuickMode',
        [
            '',
            [
                'enabled' => false,
                'role_heading' => false,
                'role_body' => false,
                'role_alias_interface' => false,
                'role_alias_ui' => false,
                'category_sans' => false,
                'category_serif' => false,
                'families' => false,
            ],
            [
                'enabled' => true,
                'minimal' => true,
                'weight_tokens' => false,
                'role_aliases' => false,
                'category_sans' => false,
                'category_serif' => false,
            ],
            false,
        ]
    );
    assertSameValue('minimal', $minimalMode, 'Quick mode should resolve to minimal when the minimal preset is enabled with variables on and classes off.');

    $variablesMode = invokePrivateMethod(
        $builder,
        'deriveOutputQuickMode',
        [
            '',
            [
                'enabled' => false,
                'role_heading' => false,
                'role_body' => false,
                'role_alias_interface' => false,
                'role_alias_ui' => false,
                'category_sans' => false,
                'category_serif' => false,
                'families' => false,
            ],
            [
                'enabled' => true,
                'minimal' => false,
                'weight_tokens' => true,
                'role_aliases' => true,
                'category_sans' => true,
                'category_serif' => true,
            ],
            false,
        ]
    );
    assertSameValue('variables', $variablesMode, 'Quick mode should resolve to variables only when the full preset baseline remains intact.');

    $classesMode = invokePrivateMethod(
        $builder,
        'deriveOutputQuickMode',
        [
            '',
            [
                'enabled' => true,
                'role_heading' => true,
                'role_body' => true,
                'role_alias_interface' => true,
                'role_alias_ui' => true,
                'category_sans' => true,
                'category_serif' => true,
                'families' => true,
            ],
            [
                'enabled' => false,
                'minimal' => false,
                'weight_tokens' => false,
                'role_aliases' => false,
                'category_sans' => false,
                'category_serif' => false,
            ],
            false,
        ]
    );
    assertSameValue('classes', $classesMode, 'Quick mode should resolve to classes only when the full preset baseline remains intact.');

    $customMode = invokePrivateMethod(
        $builder,
        'deriveOutputQuickMode',
        [
            '',
            [
                'enabled' => true,
                'role_heading' => true,
                'role_body' => true,
                'role_alias_interface' => true,
                'role_alias_ui' => false,
                'category_sans' => true,
                'category_serif' => true,
                'families' => true,
            ],
            [
                'enabled' => true,
                'minimal' => false,
                'weight_tokens' => true,
                'role_aliases' => true,
                'category_sans' => true,
                'category_serif' => true,
            ],
            false,
        ]
    );
    assertSameValue('custom', $customMode, 'Quick mode should resolve to custom when both outputs are enabled but one granular subgroup is disabled.');

    $stickyCustomMode = invokePrivateMethod(
        $builder,
        'deriveOutputQuickMode',
        [
            'custom',
            [
                'enabled' => false,
                'role_heading' => true,
                'role_body' => true,
                'role_alias_interface' => true,
                'role_alias_ui' => true,
                'category_sans' => true,
                'category_serif' => true,
                'families' => true,
            ],
            [
                'enabled' => true,
                'minimal' => false,
                'weight_tokens' => true,
                'role_aliases' => true,
                'category_sans' => true,
                'category_serif' => true,
            ],
            false,
        ]
    );
    assertSameValue('custom', $stickyCustomMode, 'Quick mode should stay custom when an explicit custom preference is saved, even if the output booleans match variables-only.');

    $staleVariablesMode = invokePrivateMethod(
        $builder,
        'deriveOutputQuickMode',
        [
            'variables',
            [
                'enabled' => false,
                'role_heading' => true,
                'role_body' => true,
                'role_alias_interface' => true,
                'role_alias_ui' => true,
                'category_sans' => true,
                'category_serif' => true,
                'families' => true,
            ],
            [
                'enabled' => true,
                'minimal' => false,
                'weight_tokens' => true,
                'role_aliases' => true,
                'category_sans' => true,
                'category_serif' => false,
            ],
            false,
        ]
    );
    assertSameValue('custom', $staleVariablesMode, 'Saved non-custom preferences should coerce to custom when the detailed output settings no longer match the preset baseline.');
};

$tests['admin_page_view_builder_builds_typed_family_selector_options'] = static function (): void {
    resetTestState();

    $builder = new AdminPageViewBuilder(new Storage());
    $view = $builder->build([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [
            'Inter' => [
                'family' => 'Inter',
                'has_variable_faces' => true,
            ],
            'Lora' => [
                'family' => 'Lora',
                'faces' => [
                    [
                        'weight' => '400',
                        'style' => 'normal',
                    ],
                ],
            ],
        ],
        'available_families' => ['Inter', 'Lora', 'Legacy Stack'],
    ]);

    assertSameValue('Inter · Variable', $view['availableFamilyOptions'][0]['label'] ?? '', 'Known variable families should receive a typed selector label.');
    assertSameValue('variable', $view['availableFamilyOptions'][0]['type'] ?? '', 'Variable selector options should expose their type token.');
    assertSameValue('Lora · Static', $view['availableFamilyOptions'][1]['label'] ?? '', 'Known static families should receive a typed selector label.');
    assertSameValue('static', $view['availableFamilyOptions'][1]['type'] ?? '', 'Static selector options should expose their type token.');
    assertSameValue('Legacy Stack', $view['availableFamilyOptions'][2]['label'] ?? '', 'Families missing from the current catalog should keep their plain selector label.');
    assertSameValue('Inter · Variable', $view['availableFamilyLabels']['Inter'] ?? '', 'The preview label lookup should reuse the typed selector label.');
    assertSameValue('Legacy Stack', $view['availableFamilyLabels']['Legacy Stack'] ?? '', 'Missing catalog families should stay selectable without a type suffix.');
};

$tests['admin_page_view_builder_prefers_saved_custom_output_quick_mode_on_reload'] = static function (): void {
    resetTestState();

    $builder = new AdminPageViewBuilder(new Storage());
    $view = $builder->build([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => [],
        'output_quick_mode_preference' => 'custom',
        'class_output_enabled' => false,
        'class_output_role_heading_enabled' => true,
        'class_output_role_body_enabled' => true,
        'class_output_role_alias_interface_enabled' => true,
        'class_output_role_alias_ui_enabled' => true,
        'class_output_category_sans_enabled' => true,
        'class_output_category_serif_enabled' => true,
        'class_output_families_enabled' => true,
        'per_variant_font_variables_enabled' => true,
        'minimal_output_preset_enabled' => false,
        'extended_variable_weight_tokens_enabled' => true,
        'extended_variable_role_aliases_enabled' => true,
        'extended_variable_category_sans_enabled' => true,
        'extended_variable_category_serif_enabled' => true,
        'role_usage_font_weight_enabled' => false,
    ]);

    assertSameValue('custom', (string) ($view['outputQuickMode'] ?? ''), 'A saved custom quick-mode preference should remain selected on reload even when the current booleans match variables-only.');
    assertSameValue(true, !empty($view['advancedOutputControlsExpanded']), 'A saved custom quick-mode preference should keep the advanced output controls expanded on reload.');
};

$tests['admin_page_renderer_balances_div_wrappers'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'local_environment_notice' => [],
            'toasts' => [],
            'apply_everywhere' => false,
            'role_deployment' => [],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertSameValue(substr_count($output, '<div'), substr_count($output, '</div>'), 'Admin page renderer should balance div wrappers so WordPress admin footer markup stays outside the plugin page shell.');
};

$tests['admin_page_renderer_shows_mono_extended_variable_controls_when_monospace_is_enabled'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('Mono Alias', $output, 'The submenu should show the mono-category toggle when the monospace feature is enabled.');
    assertContainsValue('--font-code', $output, 'The role-alias copy should mention the code alias when the monospace feature is enabled.');
};

$tests['admin_page_renderer_renders_font_classes_output_tab_content'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
        $renderer->renderPage([
            'storage' => ['root' => '/tmp/uploads/fonts'],
            'catalog' => [],
            'available_families' => ['Inter'],
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
            'class_output_enabled' => false,
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
                    'value' => 'body { font-family: var(--font-body); }',
                    'active' => true,
                ],
                [
                    'key' => 'classes',
                    'label' => 'Font Classes',
                    'target' => 'tasty-fonts-output-classes',
                    'value' => 'Class output is off. Turn on Classes in Output Settings to generate utility classes.',
                    'active' => false,
                ],
                [
                    'key' => 'classes-roles',
                    'label' => 'Font Classes Roles',
                    'target' => 'tasty-fonts-output-classes-roles',
                    'value' => ".font-heading {\n  font-family: \"Inter\", sans-serif;\n}",
                    'active' => false,
                ],
                [
                    'key' => 'classes-families',
                    'label' => 'Font Classes Families',
                    'target' => 'tasty-fonts-output-classes-families',
                    'value' => ".font-inter {\n  font-family: \"Inter\", sans-serif;\n}",
                    'active' => false,
                ],
                [
                    'key' => 'classes-all',
                    'label' => 'Font Classes All',
                    'target' => 'tasty-fonts-output-classes-all',
                    'value' => ".font-heading {\n  font-family: \"Inter\", sans-serif;\n}\n\n.font-inter {\n  font-family: \"Inter\", sans-serif;\n}",
                    'active' => false,
                ],
            ],
            'generated_css_panel' => [],
            'preview_panels' => [],
            'toasts' => [],
            'apply_everywhere' => false,
            'role_deployment' => [],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('>Font Classes<', $output, 'The snippets tab list should include the Font Classes output tab.');
    assertContainsValue('Class output is off. Turn on Classes in Output Settings to generate utility classes.', $output, 'The Font Classes tab should render the off-state message.');
    assertContainsValue('data-copy-text=".font-heading {', $output, 'The Font Classes tab should support copying role class snippets.');
    assertContainsValue('data-copy-text=".font-inter {', $output, 'The Font Classes tab should support copying family class snippets.');
    assertContainsValue('id="tasty-fonts-output-classes-all"', $output, 'The Font Classes tab should render a dedicated combined role-and-family snippet panel.');
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
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertSameValue(1, substr_count($output, 'data-migrate-delivery'), 'CDN-backed library families should expose the self-host migration shortcut only in the saved delivery profile details.');
    assertContainsValue('data-migrate-provider="google"', $output, 'The migration shortcut should preserve the delivery provider.');
    assertContainsValue('data-migrate-family="Inter"', $output, 'The migration shortcut should preserve the family name for panel prefill.');
    assertContainsValue('data-migrate-variants="regular,700"', $output, 'The migration shortcut should preserve the saved variant tokens for self-hosting prefill.');
    assertNotContainsValue('tasty-fonts-font-actions-secondary', $output, 'Library cards should no longer render a dedicated migration action row above the detailed delivery profile actions.');
    assertNotContainsValue('Remote variants are managed by their delivery profile instead of being deleted individually.', $output, 'CDN-backed active faces should no longer be hard-disabled from individual deletion in the detail cards.');
};

$tests['admin_page_renderer_marks_variable_library_families_with_type_badges'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'Inter Variable',
        'slug' => 'inter-variable',
        'delivery_filter_tokens' => ['google'],
        'publish_state' => 'published',
        'has_variable_faces' => true,
        'variation_axes' => [
            'WGHT' => ['min' => 100, 'max' => 900],
        ],
        'active_delivery_id' => 'google-cdn',
        'active_delivery' => [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular'],
            'has_variable_faces' => true,
            'variation_axes' => [
                'WGHT' => ['min' => 100, 'max' => 900],
            ],
        ],
        'available_deliveries' => [
            [
                'id' => 'google-cdn',
                'label' => 'Google CDN',
                'provider' => 'google',
                'type' => 'cdn',
                'variants' => ['regular'],
                'has_variable_faces' => true,
                'variation_axes' => [
                    'WGHT' => ['min' => 100, 'max' => 900],
                ],
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'google',
                'is_variable' => true,
                'axes' => [
                    'WGHT' => ['min' => 100, 'max' => 900],
                ],
                'files' => [],
                'paths' => [],
            ],
        ],
    ];

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertSameValue(1, preg_match('/class="tasty-fonts-badge is-role"[\s\S]*?Variable/', $output), 'Variable library family rows should expose a Variable badge in the saved library card.');
};

$tests['admin_page_renderer_renders_copy_ready_face_variant_variables'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('>CSS<', $output, 'Face detail cards should render a dedicated CSS area.');
    assertContainsValue('data-copy-text="font-family: var(--font-inter);"', $output, 'Face detail cards should expose the family CSS declaration.');
    assertContainsValue('data-copy-text="font-weight: var(--weight-bold);"', $output, 'Face detail cards should expose the weight CSS declaration.');
    assertContainsValue('data-copy-text="font-style: italic;"', $output, 'Face detail cards should expose the style CSS declaration.');
    assertContainsValue('data-copy-text="font-family: var(--font-inter); font-weight: var(--weight-bold); font-style: italic;"', $output, 'Face detail cards should expose the full CSS snippet.');
    assertContainsValue('aria-label="Copy Family: font-family: var(--font-inter);"', $output, 'Copyable CSS pills should expose the copied value in the accessible name.');
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
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('>CSS Variables<', $output, 'Family details should render a dedicated CSS Variables section.');
    assertContainsValue('tasty-fonts-family-details-grid', $output, 'Expanded family details should render a dedicated layout grid wrapper.');
    assertContainsValue('tasty-fonts-family-details-utilities', $output, 'Expanded family details should render a dedicated utility-card column when utility snippets are available.');
    assertNotContainsValue('>Font Classes<', $output, 'Family details should keep class selectors hidden when class output is not enabled for the card.');
    assertContainsValue('data-copy-text="--font-jetbrains-mono: &quot;JetBrains Mono&quot;, monospace;"', $output, 'Family details should expose the base family variable.');
    assertContainsValue('data-copy-text="--font-monospace: &quot;JetBrains Mono&quot;, monospace;"', $output, 'Family details should expose the role variable at the family level.');
    assertContainsValue('data-copy-text="--font-code: var(--font-monospace);"', $output, 'Family details should expose the code alias when the family is assigned to monospace.');
    assertContainsValue('data-copy-text="--font-mono: var(--font-jetbrains-mono);"', $output, 'Family details should expose the category monospace alias when the family resolves the mono token.');
};

$tests['admin_page_renderer_renders_copy_ready_family_font_classes'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'Inter',
        'slug' => 'inter',
        'font_category' => 'sans-serif',
        'font_category_tokens' => ['sans-serif'],
        'delivery_filter_tokens' => ['published'],
        'publish_state' => 'published',
        'active_delivery_id' => 'google-self-hosted',
        'active_delivery' => [
            'id' => 'google-self-hosted',
            'label' => 'Self-hosted (Google import)',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular'],
        ],
        'available_deliveries' => [
            [
                'id' => 'google-self-hosted',
                'label' => 'Self-hosted (Google import)',
                'provider' => 'google',
                'type' => 'self_hosted',
                'variants' => ['regular'],
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'google',
                'files' => ['woff2' => 'google/inter/inter-400-normal.woff2'],
                'paths' => ['woff2' => 'google/inter/inter-400-normal.woff2'],
            ],
        ],
    ];

    ob_start();
    try {
        invokePrivateMethod(
            $renderer,
            'renderFamilyRow',
            [
                $family,
                ['heading' => '', 'body' => 'Inter', 'body_fallback' => 'sans-serif'],
                [],
                [],
                [
                    ['value' => 'inherit', 'label' => 'Use plugin default'],
                ],
                'The quick brown fox jumps over the lazy dog.',
                ['--font-sans' => 'Inter'],
                ['enabled' => true, 'weight_tokens' => true, 'role_aliases' => true, 'category_sans' => true, 'category_serif' => true],
                false,
                ['enabled' => true, 'role_body' => true, 'role_alias_interface' => true, 'role_alias_ui' => true, 'category_sans' => true, 'families' => true],
            ]
        );
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('>Font Classes<', $output, 'Family details should render a dedicated Font Classes section when class output is enabled.');
    assertContainsValue('tasty-fonts-font-card-overview-main', $output, 'Expanded family cards should render a dedicated overview-content wrapper.');
    assertContainsValue('tasty-fonts-font-card-sidecolumn', $output, 'Expanded family cards should render a dedicated overview sidebar wrapper.');
    assertContainsValue('tasty-fonts-detail-group--utility', $output, 'Expanded family utility cards should share the unified utility-card treatment.');
    assertContainsValue('tasty-fonts-detail-files--utility', $output, 'Family utility snippets should use the shared utility file shell.');
    assertContainsValue('data-copy-text=".font-inter"', $output, 'Family details should expose the family utility class.');
    assertContainsValue('data-copy-text=".font-body"', $output, 'Family details should expose the active role class.');
    assertContainsValue('data-copy-text=".font-interface"', $output, 'Family details should expose enabled interface alias classes.');
    assertContainsValue('data-copy-text=".font-ui"', $output, 'Family details should expose enabled UI alias classes.');
    assertContainsValue('data-copy-text=".font-sans"', $output, 'Family details should expose the winning category utility class.');
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
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
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
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertNotContainsValue('data-copy-text="--font-mono: var(--font-jetbrains-mono);"', $output, 'Family details should not expose the mono alias when the monospace feature is disabled.');
    assertNotContainsValue('data-copy-text="--font-code: var(--font-monospace);"', $output, 'Family details should not expose the code alias when the monospace feature is disabled.');
};

$tests['admin_page_renderer_uses_raw_face_weights_when_extended_variables_are_disabled'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('data-copy-text="font-weight: 700;"', $output, 'Face detail cards should fall back to raw numeric weights when extended output is disabled.');
    assertContainsValue('data-copy-text="font-family: var(--font-inter); font-weight: 700; font-style: italic;"', $output, 'Combined face snippets should use raw font-weight values when extended output is disabled.');
    assertNotContainsValue('data-copy-text="font-weight: var(--weight-bold);"', $output, 'Face detail cards should not expose weight variables when extended output is disabled.');
};

$tests['admin_page_renderer_hides_category_alias_when_the_family_does_not_win_it'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
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
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('data-copy-text="&quot;JetBrains Mono&quot;, monospace"', $output, 'Family preview stacks should default monospace catalog families to the monospace generic fallback.');
};

$tests['admin_page_renderer_translates_stored_delivery_profile_labels_at_output'] = static function (): void {
    resetTestState();

    global $translationMap;

    $translationMap = [
        'Self-hosted (Google import)' => 'Import Google auto-heberge',
        'Adobe-hosted' => 'Heberge par Adobe',
        'Self-hosted files' => 'Fichiers auto-heberges',
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
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('Import Google auto-heberge', $output, 'Stored English delivery labels should be translated when rendered in the family card.');
    assertContainsValue('Heberge par Adobe', $output, 'Stored English delivery labels should be translated in delivery lists and detail cards.');
    assertContainsValue('Fichiers auto-heberges', $output, 'Self-hosted request summaries should stay translatable at render time.');
    assertContainsValue('Feuille de style de projet Adobe hebergee', $output, 'Adobe request summaries should stay translatable at render time.');
    assertNotContainsValue('Self-hosted (Google import)', $output, 'The family card should not render the raw stored English Google self-hosted label once translated.');
    assertNotContainsValue('Adobe-hosted', $output, 'The family card should not render the raw stored English Adobe label once translated.');
};

$tests['admin_page_renderer_renders_type_badges_for_adobe_delivery_and_face_cards'] = static function (): void {
    resetTestState();

    $renderer = new FamilyCardRenderer(new Storage());

    ob_start();
    try {
        $renderer->renderAdobeFamilyCard([
            'family' => 'Source Sans 3',
            'has_variable_faces' => true,
            'faces' => [
                [
                    'is_variable' => true,
                    'axes' => [
                        'WGHT' => ['min' => 200, 'max' => 900],
                    ],
                ],
            ],
        ]);
        $renderer->renderAdobeFamilyCard([
            'family' => 'Merriweather',
            'faces' => [
                [
                    'weight' => '400',
                    'style' => 'normal',
                ],
            ],
        ]);
        $renderer->renderDeliveryProfileCard(
            'Inter Variable',
            'inter-variable',
            'google-cdn',
            'published',
            [
                'id' => 'google-cdn',
                'label' => 'Google CDN',
                'provider' => 'google',
                'type' => 'cdn',
                'variants' => ['regular'],
                'has_variable_faces' => true,
            ]
        );
        $renderer->renderFaceDetailCard(
            'Lora',
            'lora',
            '"Lora", serif',
            'The quick brown fox jumps over the lazy dog.',
            1,
            [],
            'serif',
            [],
            ['enabled' => true, 'weight_tokens' => true, 'role_aliases' => true, 'category_sans' => true, 'category_serif' => true, 'category_mono' => true],
            ['provider' => 'local', 'type' => 'self_hosted'],
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'local',
                'files' => ['woff2' => 'lora/Lora-400-normal.woff2'],
                'paths' => ['woff2' => 'lora/Lora-400-normal.woff2'],
            ],
            false
        );
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertSameValue(1, preg_match('/Source Sans 3[\s\S]*?Variable/', $output), 'Adobe family cards should render Variable badges when their metadata indicates variable support.');
    assertSameValue(1, preg_match('/tasty-fonts-detail-card--delivery[\s\S]*?Variable/', $output), 'Delivery profile cards should render Variable badges when their metadata indicates variable support.');
    assertSameValue(1, preg_match('/Merriweather[\s\S]*?Static/', $output), 'Adobe family cards should render Static badges when their metadata indicates fixed styles only.');
    assertSameValue(1, preg_match('/tasty-fonts-detail-card--face[\s\S]*?Static/', $output), 'Face detail cards should render Static badges when their metadata indicates fixed styles only.');
    assertContainsValue('tasty-fonts-detail-card--delivery', $output, 'Delivery profile detail cards should be included in the type badge coverage.');
    assertContainsValue('tasty-fonts-detail-card--face', $output, 'Face detail cards should be included in the type badge coverage.');
};

$tests['family_card_renderer_counts_delivery_variants_from_faces_before_stale_profile_metadata'] = static function (): void {
    resetTestState();

    $renderer = new FamilyCardRenderer(new Storage());

    ob_start();
    try {
        $renderer->renderDeliveryProfileCard(
            'Lora',
            'lora',
            'local-self_hosted',
            'published',
            [
                'id' => 'local-self_hosted',
                'label' => 'Self-hosted',
                'provider' => 'local',
                'type' => 'self_hosted',
                'variants' => ['100', '200', '300', 'regular', '500', '600', '700'],
                'faces' => [
                    [
                        'weight' => '100..900',
                        'style' => 'normal',
                        'source' => 'local',
                        'is_variable' => true,
                        'axes' => [
                            'WGHT' => ['min' => '100', 'max' => '900', 'default' => '400'],
                        ],
                    ],
                ],
            ]
        );
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('1 variant', $output, 'Delivery profile cards should count variants from the actual face list when faces are available.');
    assertNotContainsValue('7 variants', $output, 'Delivery profile cards should not surface stale stored variant counts when a single variable face is present.');
};

$tests['admin_page_renderer_exposes_behavior_tab_and_can_hide_help_ui'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'update_channel' => 'beta',
            'update_channel_options' => [
                ['value' => 'stable', 'label' => 'Stable'],
                ['value' => 'beta', 'label' => 'Beta'],
                ['value' => 'nightly', 'label' => 'Nightly'],
            ],
            'update_channel_status' => [
                'selected_channel' => 'beta',
                'selected_channel_label' => 'Beta',
                'installed_version' => '1.7.0',
                'latest_version' => '1.7.1-beta.2',
                'state_label' => 'Upgrade Available',
                'state_class' => 'is-success',
                'state_copy' => 'A newer package is available for the selected channel through the normal WordPress updates flow.',
                'can_reinstall' => false,
            ],
            'block_editor_font_library_sync_enabled' => false,
            'training_wheels_off' => true,
            'variable_fonts_enabled' => true,
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('Behavior', $output, 'The settings switcher should expose the dedicated Behavior tab.');
    assertContainsValue('Transfer', $output, 'The settings switcher should expose the dedicated Transfer tab.');
    assertContainsValue('Developer', $output, 'The settings switcher should expose the dedicated Developer tab.');
    assertContainsValue('Integrations', $output, 'The settings switcher should expose the dedicated Integrations tab.');
    assertContainsValue('Gutenberg Font Library', $output, 'The Integrations tab should expose the Gutenberg integration card.');
    assertContainsValue('Automatic.css', $output, 'The Integrations tab should expose the Automatic.css integration card.');
    assertNotContainsValue('Sync to Gutenberg Font Library', $output, 'The Integrations tab should no longer render a duplicate Gutenberg sync row title.');
    assertNotContainsValue('Sync heading/body roles to Automatic.css', $output, 'The Integrations tab should no longer render a duplicate Automatic.css sync row title.');
    assertContainsValue('Hide Onboarding Hints', $output, 'The Behavior tab should expose the onboarding-hints toggle.');
    assertContainsValue('Update Channel', $output, 'The Behavior tab should expose the update channel selector.');
    assertContainsValue('Stable', $output, 'The Behavior tab should render the stable update channel option inline.');
    assertContainsValue('Beta', $output, 'The Behavior tab should render the beta update channel option inline.');
    assertContainsValue('Nightly', $output, 'The Behavior tab should render the nightly update channel option inline.');
    assertContainsValue('Upgrade Available', $output, 'The Behavior tab should render the current update channel status badge.');
    assertContainsValue('Installed: 1.7.0. Latest for Beta: 1.7.1-beta.2.', $output, 'The Behavior tab should render the update channel version summary inline with the field.');
    assertNotContainsValue('A newer package is available for the selected channel through the normal WordPress updates flow.', $output, 'The Behavior tab should omit the redundant update channel status copy.');
    assertNotContainsValue('Rollback Reinstall', $output, 'The Behavior tab should no longer render a separate rollback subsection title.');
    assertNotContainsValue('Enable Block Editor Font Library Sync', $output, 'The Behavior panel should no longer render the Gutenberg sync toggle after it moves into Integrations.');
    assertContainsValue('Enable Monospace Role', $output, 'The Behavior panel should still render the monospace toggle.');
    assertContainsValue('Enable Variable Fonts', $output, 'The Behavior panel should render the opt-in variable font toggle.');
    assertContainsValue('Delete Uploaded Fonts on Uninstall', $output, 'The Behavior panel should still render the uninstall cleanup toggle.');
    assertContainsValue('Reset Plugin Settings', $output, 'The Developer tab should expose the reset-settings action.');
    assertContainsValue('Wipe Managed Font Library', $output, 'The Developer tab should expose the library-wipe action.');
    assertContainsValue('Site Transfer', $output, 'The Transfer tab should render the dedicated site transfer panel.');
    assertContainsValue('Export Site Transfer Bundle', $output, 'The Transfer tab should expose the portable export action.');
    assertContainsValue('Import Site Transfer Bundle', $output, 'The Transfer tab should expose the portable import action.');
    assertContainsValue('data-site-transfer-form', $output, 'The site transfer panel should expose the dedicated import form hook for client-side state management.');
    assertSameValue(
        1,
        preg_match('/data-site-transfer-submit[\s\S]*disabled/', $output),
        'The site transfer import button should render disabled until a bundle file is selected.'
    );
    assertContainsValue('Clear Plugin Caches and Regenerate Assets', $output, 'The Developer tab should expose the cache-reset action.');
    assertContainsValue('Regenerate Generated CSS', $output, 'The Developer tab should expose the CSS-regeneration action.');
    assertContainsValue('Reset Integration Detection State', $output, 'The Developer tab should expose the integration-reset action.');
    assertContainsValue('Reset Suppressed Notices', $output, 'The Developer tab should expose the suppressed-notices reset action.');
    assertContainsValue('data-developer-confirm-message=', $output, 'Destructive developer actions should use browser-level confirm messages.');
    assertNotContainsValue('data-developer-confirm-input=', $output, 'Destructive developer actions should no longer render typed-confirmation inputs.');
    assertNotContainsValue('Editor Integrations', $output, 'The Behavior panel should no longer render the editor integrations subsection title.');
    assertNotContainsValue('Role Options', $output, 'The Behavior panel should no longer render the role options subsection title.');
    assertNotContainsValue('Uninstall Settings', $output, 'The Behavior panel should no longer render the uninstall settings subsection title.');
    assertNotContainsValue('Saved automatically:', $output, 'Settings panels should no longer render autosave footnotes.');
    assertContainsValue('tasty-fonts-settings-save-button', $output, 'The shared Settings form should render the pill-style save button in the header.');
    assertNotContainsValue('Unsaved changes', $output, 'The shared Settings header should rely on button state instead of a separate unsaved-changes message.');
    assertContainsValue('tasty-fonts-header-logo', $output, 'The masthead should render the branded logo in place of the plain text title.');
    assertContainsValue('https://tastywp.com/tastyfonts/', $output, 'The branded masthead logo should link to the Tasty Fonts product page.');
    assertContainsValue('screen-reader-text', $output, 'The branded masthead should keep an accessible text label for assistive technology.');
    assertContainsValue('tasty-fonts-version-link-meta', $output, 'The masthead version pill should render the channel and updater state meta line.');
    assertContainsValue('Beta', $output, 'The masthead version pill should disclose the selected update channel.');
    assertContainsValue('Beta · Update Available', $output, 'The masthead version pill should summarize channel and update status together.');
    assertContainsValue('Latest available: 1.7.1-beta.2.', $output, 'The masthead version pill tooltip should expose the latest package for the selected channel.');
    assertContainsValue('is-training-wheels-off', $output, 'Hide Onboarding Hints should add the admin state class used to suppress descriptive copy.');
    assertNotContainsValue('Typography Workspace', $output, 'The masthead should omit the eyebrow label in the streamlined header layout.');
    assertNotContainsValue('Professional Typography Management For WordPress', $output, 'The streamlined masthead should omit the legacy hero tagline.');
    assertNotContainsValue('Deploy fonts, manage your library, and fine-tune output from one polished workspace.', $output, 'The streamlined masthead should omit the supporting summary copy.');
    assertNotContainsValue('Choose whether the generated stylesheet loads as a file or is printed inline in the page head.', $output, 'Hide Onboarding Hints should omit output setting descriptions from the rendered HTML.');
    assertNotContainsValue('Keep builder and framework integrations aligned with Tasty Fonts role variables.', $output, 'Hide Onboarding Hints should omit integrations tab summary descriptions from the rendered HTML.');
    assertNotContainsValue('Tasty Fonts manages only the two base ACSS font-family settings needed for heading and body text.', $output, 'The Integrations tab should omit the redundant ACSS desired mapping description.');
    assertNotContainsValue('Control optional roles, guidance, and uninstall cleanup.', $output, 'The Behavior tab should omit the redundant summary description.');
    assertNotContainsValue('Manual reset and maintenance tools for plugin development, troubleshooting, and integration work.', $output, 'The Developer tab should omit the redundant summary description.');
    assertNotContainsValue('Hides helper tips and extra info buttons.', $output, 'Hide Onboarding Hints should omit behavior toggle descriptions from the rendered HTML.');
    assertNotContainsValue('tasty-fonts-toggle-description', $output, 'Hide Onboarding Hints should omit settings toggle description elements from the rendered HTML.');
    assertNotContainsValue('tasty-fonts-help-button', $output, 'Hide Onboarding Hints should remove inline help buttons from the rendered admin UI.');
    assertNotContainsValue('data-help-tooltip=', $output, 'Hide Onboarding Hints should omit passive hover help attributes from the rendered admin UI.');
};

$tests['admin_page_renderer_attaches_update_channel_rollback_action_to_the_field'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'update_channel' => 'stable',
            'update_channel_options' => [
                ['value' => 'stable', 'label' => 'Stable'],
                ['value' => 'beta', 'label' => 'Beta'],
                ['value' => 'nightly', 'label' => 'Nightly'],
            ],
            'update_channel_status' => [
                'selected_channel' => 'stable',
                'selected_channel_label' => 'Stable',
                'installed_version' => '1.8.0-dev',
                'latest_version' => '1.7.0',
                'state_label' => 'Rollback Available',
                'state_class' => 'is-warning',
                'state_copy' => 'The selected channel points to an older package than the one installed now. Use the reinstall action below to switch immediately.',
                'can_reinstall' => true,
            ],
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
            'role_deployment' => [
                'badge' => 'Live',
                'badge_class' => 'is-success',
                'title' => 'Live',
                'copy' => 'Current selections are being served sitewide.',
            ],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('Rollback Available', $output, 'The Behavior tab should render rollback state copy inline with the update channel field.');
    assertContainsValue('Installed: 1.8.0-dev. Latest for Stable: 1.7.0.', $output, 'The Behavior tab should render rollback version context inline.');
    assertContainsValue('data-help-tooltip="The selected channel points to an older package than the one installed now. Use the reinstall action below to switch immediately."', $output, 'The Behavior tab should expose rollback guidance through the shared passive help tooltip system.');
    assertContainsValue('Stable · Rollback Available', $output, 'The masthead version pill should expose rollback state alongside the selected channel.');
    assertContainsValue('Latest available: 1.7.0.', $output, 'The masthead version pill tooltip should expose the latest rollback target version.');
    assertNotContainsValue('aria-controls="tasty-fonts-help-tooltip-layer"', $output, 'Passive help triggers should not misuse aria-controls for tooltip relationships.');
    assertNotContainsValue('<p class="tasty-fonts-settings-flat-row-note tasty-fonts-settings-flat-row-note--channel">The selected channel points to an older package than the one installed now. Use the reinstall action below to switch immediately.</p>', $output, 'Rollback guidance should no longer render as an inline sentence when the reinstall action is available.');
    assertContainsValue('Reinstall', $output, 'The Behavior tab should attach the rollback action directly to the update channel field.');
    assertNotContainsValue('Rollback Reinstall', $output, 'Rollback should no longer render as a separate nested section heading.');
};

$tests['admin_page_renderer_keeps_integration_toggle_copy_single_line'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'block_editor_font_library_sync_enabled' => true,
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
            'gutenberg_integration' => [
                'title' => 'Gutenberg Font Library',
                'description' => 'Mirror imported families into WordPress typography controls so the block editor and site editor can use the same fonts managed by Tasty Fonts.',
                'status_label' => 'On',
                'enabled' => true,
            ],
            'acss_integration' => [
                'title' => 'Automatic.css',
                'description' => 'Sync ACSS heading and body font-family and font-weight settings to Tasty Fonts role variables for clean interoperability.',
                'status_label' => 'Synced',
                'available' => true,
                'enabled' => true,
                'current' => [
                    'heading' => 'Inter, sans-serif',
                    'body' => 'System UI, sans-serif',
                    'heading_weight' => 'var(--font-heading-weight)',
                    'body_weight' => 'var(--font-body-weight)',
                ],
                'desired' => [
                    \TastyFonts\Integrations\AcssIntegrationService::OPTION_HEADING_FONT_FAMILY => 'var(--font-heading)',
                    \TastyFonts\Integrations\AcssIntegrationService::OPTION_TEXT_FONT_FAMILY => 'var(--font-body)',
                    \TastyFonts\Integrations\AcssIntegrationService::OPTION_HEADING_FONT_WEIGHT => 'var(--font-heading-weight)',
                    \TastyFonts\Integrations\AcssIntegrationService::OPTION_TEXT_FONT_WEIGHT => 'var(--font-body-weight)',
                ],
            ],
            'role_deployment' => [
                'badge' => 'Live',
                'badge_class' => 'is-success',
                'title' => 'Live',
                'copy' => 'Current selections are being served sitewide.',
            ],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('Mirror imported families into WordPress typography controls so the block editor and site editor can use the same fonts managed by Tasty Fonts.', $output, 'The Gutenberg integration summary should still explain the integration.');
    assertContainsValue('On', $output, 'The Gutenberg integration row should still surface its current status.');
    assertNotContainsValue('Sync to Gutenberg Font Library', $output, 'The Gutenberg integration should no longer render a second row title.');
    assertSameValue(1, substr_count($output, 'Gutenberg Font Library'), 'The Gutenberg integration should render a single main title.');
    assertContainsValue('Sync ACSS heading and body font-family and font-weight settings to Tasty Fonts role variables for clean interoperability.', $output, 'The Automatic.css integration summary should still explain the integration.');
    assertContainsValue('Synced', $output, 'The Automatic.css integration row should still surface its current status.');
    assertContainsValue('Heading Weight', $output, 'The Automatic.css managed mapping should list the heading font-weight field.');
    assertContainsValue('Body Weight', $output, 'The Automatic.css managed mapping should list the body font-weight field.');
    assertContainsValue('var(--font-heading-weight)', $output, 'The Automatic.css managed mapping should expose the heading weight variable target.');
    assertContainsValue('var(--font-body-weight)', $output, 'The Automatic.css managed mapping should expose the body weight variable target.');
    assertNotContainsValue('Sync heading/body roles to Automatic.css', $output, 'The Automatic.css integration should no longer render a second row title.');
    assertNotContainsValue('Sets ACSS `heading-font-family` to `var(--font-heading)` and `text-font-family` to `var(--font-body)` while the integration is enabled.', $output, 'The Automatic.css integration should no longer render a second explanatory line.');
};

$tests['admin_page_renderer_keeps_dashboard_titles_and_buttons_in_title_case'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $renderer = new AdminPageRenderer($storage);
    $previewRenderer = new PreviewSectionRenderer($storage);

    ob_start();
    try {
        $renderer->renderPage([
            'storage' => ['root' => '/tmp/uploads/fonts'],
            'catalog' => [],
            'available_families' => ['Inter', 'Lora'],
            'roles' => [
                'heading' => 'Inter',
                'body' => '',
                'monospace' => '',
                'heading_fallback' => 'sans-serif',
                'body_fallback' => 'serif',
                'monospace_fallback' => 'monospace',
            ],
            'logs' => [],
            'activity_actor_options' => [],
            'family_fallbacks' => [],
            'family_font_displays' => [],
            'family_font_display_options' => [
                ['value' => 'inherit', 'label' => 'Use Plugin Default'],
            ],
            'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
            'preview_size' => 32,
            'font_display' => 'optional',
            'font_display_options' => [
                ['value' => 'optional', 'label' => 'Optional'],
                ['value' => 'swap', 'label' => 'Swap'],
            ],
            'minify_css_output' => true,
            'preload_primary_fonts' => true,
            'remote_connection_hints' => true,
            'block_editor_font_library_sync_enabled' => true,
            'monospace_role_enabled' => true,
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
            'local_environment_notice' => [],
            'toasts' => [],
            'apply_everywhere' => false,
            'role_deployment' => [],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    ob_start();
    try {
        $previewRenderer->renderPreviewScene('editorial', 'The quick brown fox jumps over the lazy dog. 1234567890', ['heading' => 'Inter', 'body' => 'Lora', 'monospace' => 'JetBrains Mono'], true);
        $previewRenderer->renderPreviewScene('card', 'The quick brown fox jumps over the lazy dog. 1234567890', ['heading' => 'Inter', 'body' => 'Lora', 'monospace' => 'JetBrains Mono'], true);
        $previewRenderer->renderPreviewScene('reading', 'The quick brown fox jumps over the lazy dog. 1234567890', ['heading' => 'Inter', 'body' => 'Lora', 'monospace' => 'JetBrains Mono'], true);
        $previewRenderer->renderCodePreviewScene('The quick brown fox jumps over the lazy dog. 1234567890', ['heading' => 'Inter', 'body' => 'Lora', 'monospace' => 'JetBrains Mono'], true);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output .= (string) ob_get_clean();

    foreach ([
        'Choose Fonts, Preview the Pairing, Then Publish When It Is Ready.',
        'Switch Off Sitewide',
        'Save or Publish Role Changes',
        'Preview the Pairing or Open Snippets',
        'Choose the Family and Fallback for Each Saved Role.',
        'Minify Generated CSS',
        'Preload Primary Heading and Body Fonts',
        'Remote Connection Hints',
        'Emit Font Utility Classes',
        'Role Classes',
        'Delete Uploaded Fonts on Uninstall',
        'Your Library Is Empty',
        'No Activity Yet',
        'Readable Preview',
        'Show Actual Output',
        'A Type Pairing That Feels Intentional at Every Scale',
        'Clean Cards With Enough Contrast for Product Copy',
        'Structured and Calm',
        'System-Ready',
        'Readable Paragraphs With Steady Rhythm',
        'Inspect How Your Code Reads in an Editor and Published Block',
        'Front-End Snippet With Readable Line Height and Punctuation',
        'Use Fallback Only',
    ] as $titleCaseLabel) {
        assertContainsValue($titleCaseLabel, $output, 'Dashboard titles and button labels should use Title Case.');
    }

    foreach ([
        'Choose fonts, preview the pairing, then publish when it is ready.',
        'Switch off Sitewide',
        'Save or publish role changes',
        'Preview the pairing or open snippets',
        'Choose the family and fallback for each saved role.',
        'Minify generated CSS',
        'Preload primary heading and body fonts',
        'Remote connection hints',
        'Emit font utility classes',
        'Role classes',
        'Delete uploaded fonts on uninstall',
        'Your library is empty',
        'No activity yet',
        'Readable preview',
        'Show actual output',
        'A type pairing that feels intentional at every scale',
        'Clean cards with enough contrast for product copy',
        'Structured and calm',
        'System-ready',
        'Readable paragraphs with steady rhythm',
        'Inspect how your code reads in an editor and published block',
        'Front-end snippet with readable line height and punctuation',
        'Use fallback only',
    ] as $sentenceCaseLabel) {
        assertNotContainsValue($sentenceCaseLabel, $output, 'Dashboard titles and button labels should not regress to sentence case.');
    }
};

$tests['admin_page_renderer_hides_acss_managed_mapping_when_sync_is_not_enabled'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'block_editor_font_library_sync_enabled' => true,
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
            'gutenberg_integration' => [
                'title' => 'Gutenberg Font Library',
                'description' => 'Mirror imported families into WordPress typography controls so the block editor and site editor can use the same fonts managed by Tasty Fonts.',
                'status_label' => 'On',
                'enabled' => true,
            ],
            'acss_integration' => [
                'title' => 'Automatic.css',
                'description' => 'Sync ACSS heading and body font-family settings to Tasty Fonts role variables for clean interoperability.',
                'status_label' => 'Off',
                'enabled' => false,
                'available' => false,
                'current' => [
                    'heading' => '',
                    'body' => '',
                ],
                'desired' => [
                    \TastyFonts\Integrations\AcssIntegrationService::OPTION_HEADING_FONT_FAMILY => 'var(--font-heading)',
                    \TastyFonts\Integrations\AcssIntegrationService::OPTION_TEXT_FONT_FAMILY => 'var(--font-body)',
                ],
            ],
            'role_deployment' => [
                'badge' => 'Draft',
                'badge_class' => '',
                'title' => 'Draft',
                'copy' => 'Current selections are not being served sitewide.',
            ],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('Automatic.css', $output, 'The Automatic.css integration row should still render when the integration is unavailable or disabled.');
    assertNotContainsValue('Managed Mapping', $output, 'The Automatic.css managed mapping section should stay hidden until the integration is enabled.');
    assertNotContainsValue('Current Automatic.css Values', $output, 'The Automatic.css current-values panel should stay hidden until the integration is enabled.');
    assertNotContainsValue('Desired Mapping', $output, 'The Automatic.css desired-mapping panel should stay hidden until the integration is enabled.');
};

$tests['admin_page_renderer_disables_unavailable_plugin_integrations'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'block_editor_font_library_sync_enabled' => true,
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
            'bricks_integration' => [
                'title' => 'Bricks Builder',
                'description' => 'Expose published Tasty Fonts families inside Bricks selectors and mirror Bricks theme font families into Gutenberg.',
                'status_label' => 'Not Active',
                'enabled' => false,
                'available' => false,
            ],
            'oxygen_integration' => [
                'title' => 'Oxygen Builder',
                'description' => 'Expose published Tasty Fonts families through Oxygen’s custom-font compatibility layer and mirror Oxygen global font families into Gutenberg.',
                'status_label' => 'Not Active',
                'enabled' => false,
                'available' => false,
            ],
            'gutenberg_integration' => [
                'title' => 'Gutenberg Font Library',
                'description' => 'Mirror imported families into WordPress typography controls so the block editor and site editor can use the same fonts managed by Tasty Fonts.',
                'status_label' => 'On',
                'enabled' => true,
            ],
            'acss_integration' => [
                'title' => 'Automatic.css',
                'description' => 'Sync ACSS heading and body font-family settings to Tasty Fonts role variables for clean interoperability.',
                'status_label' => 'Not Active',
                'enabled' => false,
                'available' => false,
                'current' => [
                    'heading' => '',
                    'body' => '',
                ],
                'desired' => [
                    \TastyFonts\Integrations\AcssIntegrationService::OPTION_HEADING_FONT_FAMILY => 'var(--font-heading)',
                    \TastyFonts\Integrations\AcssIntegrationService::OPTION_TEXT_FONT_FAMILY => 'var(--font-body)',
                ],
            ],
            'role_deployment' => [
                'badge' => 'Draft',
                'badge_class' => '',
                'title' => 'Draft',
                'copy' => 'Current selections are not being served sitewide.',
            ],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('name="bricks_integration_enabled"', $output, 'The Bricks integration toggle should still render when the plugin is unavailable.');
    assertContainsValue('name="oxygen_integration_enabled"', $output, 'The Oxygen integration toggle should still render when the plugin is unavailable.');
    assertContainsValue('name="acss_font_role_sync_enabled"', $output, 'The Automatic.css integration toggle should still render when the plugin is unavailable.');
    assertSameValue(1, preg_match('/name="bricks_integration_enabled"[^>]*disabled/', $output), 'The Bricks integration toggle should be disabled when Bricks is unavailable.');
    assertSameValue(1, preg_match('/name="oxygen_integration_enabled"[^>]*disabled/', $output), 'The Oxygen integration toggle should be disabled when Oxygen is unavailable.');
    assertSameValue(1, preg_match('/name="acss_font_role_sync_enabled"[^>]*disabled/', $output), 'The Automatic.css integration toggle should be disabled when Automatic.css is unavailable.');
    assertSameValue(0, preg_match('/name="bricks_integration_enabled"[^>]*checked/', $output), 'The Bricks integration toggle should render unchecked when Bricks is unavailable.');
    assertSameValue(0, preg_match('/name="oxygen_integration_enabled"[^>]*checked/', $output), 'The Oxygen integration toggle should render unchecked when Oxygen is unavailable.');
    assertSameValue(0, preg_match('/name="acss_font_role_sync_enabled"[^>]*checked/', $output), 'The Automatic.css integration toggle should render unchecked when Automatic.css is unavailable.');
};

$tests['admin_page_renderer_renders_staged_bricks_integration_controls'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'block_editor_font_library_sync_enabled' => true,
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
            'bricks_integration' => [
                'title' => 'Bricks Builder',
                'description' => 'Choose which Bricks controls Tasty Fonts should manage for selectors, builder previews, Theme Styles, and Bricks font settings.',
                'status_label' => 'On',
                'enabled' => true,
                'available' => true,
                'feature_descriptions' => [
                    'selectors' => 'Show published Tasty families directly inside Bricks font controls.',
                    'builder_preview' => 'Load the active Tasty delivery in Bricks builder previews for local, CDN, and Adobe fonts.',
                    'theme_styles' => 'Update only the font-family and font-weight fields on one selected Bricks Theme Style.',
                    'google_fonts' => 'Turn on Bricks’ own “disable Google Fonts” setting so Bricks pickers show only Tasty-supplied fonts.',
                ],
                'selectors' => ['enabled' => true, 'status' => 'active'],
                'builder_preview' => ['enabled' => true, 'status' => 'active'],
                'theme_styles' => [
                    'enabled' => true,
                    'applied' => true,
                    'status' => 'synced',
                    'current' => [
                        'body_family' => 'var(--font-body)',
                        'heading_family' => 'var(--font-heading)',
                    ],
                    'desired' => [
                        'body_family' => 'var(--font-body)',
                        'heading_family' => 'var(--font-heading)',
                        'body_weight' => 'var(--font-body-weight)',
                        'heading_weight' => 'var(--font-heading-weight)',
                    ],
                    'summary' => [
                        'has_theme_styles' => true,
                        'managed_style_exists' => true,
                        'managed_style_label' => 'Tasty Fonts',
                        'available_styles' => ['tasty-fonts-managed' => 'Tasty Fonts', 'sitewide-primary' => 'Sitewide Primary'],
                        'target_mode' => 'selected',
                        'target_style_id' => 'sitewide-primary',
                        'target_style_label' => 'Sitewide Primary',
                        'target_is_managed' => false,
                        'target_is_all' => false,
                    ],
                ],
                'google_fonts' => [
                    'enabled' => true,
                    'status' => 'synced',
                    'current' => [
                        'google_fonts_disabled' => true,
                    ],
                ],
            ],
            'oxygen_integration' => [
                'title' => 'Oxygen Builder',
                'description' => 'Expose published Tasty Fonts families through Oxygen’s custom-font compatibility layer and mirror Oxygen global font families into Gutenberg.',
                'status_label' => 'Off',
                'enabled' => false,
                'available' => true,
            ],
            'gutenberg_integration' => [
                'title' => 'Gutenberg Font Library',
                'description' => 'Mirror imported families into WordPress typography controls so the block editor and site editor can use the same fonts managed by Tasty Fonts.',
                'status_label' => 'On',
                'enabled' => true,
            ],
            'acss_integration' => [
                'title' => 'Automatic.css',
                'description' => 'Sync ACSS heading and body font-family settings to Tasty Fonts role variables for clean interoperability.',
                'status_label' => 'Off',
                'enabled' => false,
                'available' => true,
                'current' => [
                    'heading' => '',
                    'body' => '',
                ],
                'desired' => [
                    'heading-font-family' => 'var(--font-heading)',
                    'text-font-family' => 'var(--font-body)',
                ],
            ],
            'role_deployment' => [
                'badge' => 'Live',
                'badge_class' => 'is-success',
                'title' => 'Live',
                'copy' => 'Current selections are being served sitewide.',
            ],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('Bricks selectors and builder previews are included automatically', $output, 'The Bricks integration card should explain the baseline Bricks behavior inline instead of as a separate settings row.');
    assertNotContainsValue('name="bricks_selector_fonts_enabled"', $output, 'The Bricks integration card should no longer render a separate selector exposure toggle.');
    assertNotContainsValue('name="bricks_builder_preview_enabled"', $output, 'The Bricks integration card should no longer render a separate builder preview toggle.');
    assertContainsValue('name="bricks_theme_styles_sync_enabled"', $output, 'The Bricks integration card should render the Theme Style sync toggle.');
    assertContainsValue('name="bricks_theme_style_target_mode"', $output, 'The Bricks integration card should render the Theme Style target mode controls.');
    assertContainsValue('name="bricks_theme_style_target_id"', $output, 'The Bricks integration card should render the Theme Style target selector.');
    assertContainsValue('data-bricks-theme-style-target-mode', $output, 'The Bricks integration card should expose the Theme Style target mode radios for immediate client-side updates.');
    assertContainsValue('data-bricks-theme-style-target-select', $output, 'The Bricks integration card should expose the Theme Style target select for immediate client-side updates.');
    assertContainsValue('Delete Tasty Theme Style', $output, 'The Bricks integration card should render the delete action for the managed Tasty Theme Style.');
    assertContainsValue('name="bricks_disable_google_fonts_enabled"', $output, 'The Bricks integration card should render the Bricks Google font toggle.');
    assertContainsValue('Reset Bricks Integration', $output, 'The Bricks integration card should render the reset action.');
    assertContainsValue('Current Bricks State', $output, 'The Bricks integration card should render the current Bricks state summary.');
    assertContainsValue('Target Mapping', $output, 'The Bricks integration card should render the target Bricks mapping summary.');
    assertContainsValue('data-help-tooltip="Bricks Theme Style sync is active. Tasty is keeping the selected Theme Style mapped to the live sitewide role variables."', $output, 'The Bricks Theme Style status badge should explain the synced state on hover.');
    assertContainsValue('data-help-tooltip="Bricks Google Fonts are disabled in Bricks now, so Bricks pickers only show Tasty-supplied fonts."', $output, 'The Bricks Google Fonts status badge should explain the synced state on hover.');
    assertContainsValue('Tasty is applying Bricks font updates to &quot;Sitewide Primary&quot;.', $output, 'The Bricks integration card should explain which existing Theme Style Tasty is updating.');
    assertContainsValue('Theme Style Target', $output, 'The Bricks integration card should show the selected Theme Style target.');
    assertNotContainsValue('Body Role Variable', $output, 'The Bricks integration card should remove the redundant body role variable row from the managed mapping summary.');
    assertNotContainsValue('Heading Role Variable', $output, 'The Bricks integration card should remove the redundant heading role variable row from the managed mapping summary.');
};

$tests['admin_page_renderer_exposes_bricks_waiting_badge_help'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'block_editor_font_library_sync_enabled' => true,
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
            'bricks_integration' => [
                'title' => 'Bricks Builder',
                'description' => 'Choose which Bricks controls Tasty Fonts should manage for selectors, builder previews, Theme Styles, and Bricks font settings.',
                'status_label' => 'On',
                'enabled' => true,
                'available' => true,
                'feature_descriptions' => [
                    'selectors' => 'Show published Tasty families directly inside Bricks font controls.',
                    'builder_preview' => 'Load the active Tasty delivery in Bricks builder previews for local, CDN, and Adobe fonts.',
                    'theme_styles' => 'Update only the font-family and font-weight fields on one selected Bricks Theme Style.',
                    'google_fonts' => 'Turn on Bricks’ own “disable Google Fonts” setting so Bricks pickers show only Tasty-supplied fonts.',
                ],
                'selectors' => ['enabled' => true, 'status' => 'active'],
                'builder_preview' => ['enabled' => true, 'status' => 'active'],
                'theme_styles' => [
                    'enabled' => true,
                    'applied' => false,
                    'status' => 'waiting_for_sitewide_roles',
                    'current' => [
                        'body_family' => '',
                        'heading_family' => '',
                    ],
                    'desired' => [
                        'body_family' => 'var(--font-body)',
                        'heading_family' => 'var(--font-heading)',
                        'body_weight' => 'var(--font-body-weight)',
                        'heading_weight' => 'var(--font-heading-weight)',
                    ],
                    'summary' => [
                        'has_theme_styles' => true,
                        'managed_style_exists' => true,
                        'managed_style_label' => 'Tasty Fonts',
                        'available_styles' => ['tasty-fonts-managed' => 'Tasty Fonts'],
                        'target_mode' => 'managed',
                        'target_style_id' => 'tasty-fonts-managed',
                        'target_style_label' => 'Tasty Fonts',
                        'target_is_managed' => true,
                        'target_is_all' => false,
                    ],
                ],
                'google_fonts' => [
                    'enabled' => false,
                    'status' => 'disabled',
                    'current' => [
                        'google_fonts_disabled' => false,
                    ],
                ],
            ],
            'oxygen_integration' => [
                'title' => 'Oxygen Builder',
                'description' => 'Expose published Tasty Fonts families through Oxygen’s custom-font compatibility layer and mirror Oxygen global font families into Gutenberg.',
                'status_label' => 'Off',
                'enabled' => false,
                'available' => true,
            ],
            'gutenberg_integration' => [
                'title' => 'Gutenberg Font Library',
                'description' => 'Mirror imported families into WordPress typography controls so the block editor and site editor can use the same fonts managed by Tasty Fonts.',
                'status_label' => 'On',
                'enabled' => true,
            ],
            'acss_integration' => [
                'title' => 'Automatic.css',
                'description' => 'Sync ACSS heading and body font-family settings to Tasty Fonts role variables for clean interoperability.',
                'status_label' => 'Off',
                'enabled' => false,
                'available' => true,
                'current' => [
                    'heading' => '',
                    'body' => '',
                ],
                'desired' => [
                    'heading-font-family' => 'var(--font-heading)',
                    'text-font-family' => 'var(--font-body)',
                ],
            ],
            'role_deployment' => [
                'badge' => 'Draft only',
                'badge_class' => 'is-warning',
                'title' => 'Draft only',
                'copy' => 'Current selections are saved, but not published sitewide.',
            ],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('data-help-tooltip="Bricks Theme Style sync is enabled, but Tasty only applies it after sitewide role delivery is turned on."', $output, 'The Bricks waiting badge should explain that sitewide role delivery must be enabled first.');
    assertSameValue(1, preg_match('/data-help-tooltip="Bricks Theme Style sync is enabled, but Tasty only applies it after sitewide role delivery is turned on\\."[\s\S]*?>\s*Waiting\s*<\/span>/', $output), 'The Bricks Theme Style badge should still render the Waiting label.');
};

$tests['admin_page_renderer_offers_to_create_a_bricks_theme_style_when_none_exist'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'block_editor_font_library_sync_enabled' => true,
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
            'bricks_integration' => [
                'title' => 'Bricks Builder',
                'description' => 'Choose which Bricks controls Tasty Fonts should manage for selectors, builder previews, Theme Styles, and Bricks font settings.',
                'status_label' => 'On',
                'enabled' => true,
                'available' => true,
                'feature_descriptions' => [
                    'selectors' => 'Show published Tasty families directly inside Bricks font controls.',
                    'builder_preview' => 'Load the active Tasty delivery in Bricks builder previews for local, CDN, and Adobe fonts.',
                    'theme_styles' => 'Update only the font-family and font-weight fields on one selected Bricks Theme Style.',
                    'google_fonts' => 'Turn on Bricks’ own “disable Google Fonts” setting so Bricks pickers show only Tasty-supplied fonts.',
                ],
                'selectors' => ['enabled' => true, 'status' => 'active'],
                'builder_preview' => ['enabled' => true, 'status' => 'active'],
                'theme_styles' => [
                    'enabled' => false,
                    'applied' => false,
                    'status' => 'disabled',
                    'current' => [
                        'body_family' => '',
                        'heading_family' => '',
                    ],
                    'desired' => [
                        'body_family' => 'var(--font-body)',
                        'heading_family' => 'var(--font-heading)',
                        'body_weight' => 'var(--font-body-weight)',
                        'heading_weight' => 'var(--font-heading-weight)',
                    ],
                    'summary' => [
                        'has_theme_styles' => false,
                        'managed_style_exists' => false,
                        'managed_style_label' => 'Tasty Fonts',
                        'available_styles' => [],
                        'target_mode' => 'managed',
                        'target_style_id' => 'tasty-fonts-managed',
                        'target_style_label' => 'Tasty Fonts',
                        'target_is_managed' => true,
                        'target_is_all' => false,
                    ],
                ],
                'google_fonts' => [
                    'enabled' => false,
                    'status' => 'disabled',
                    'current' => [
                        'google_fonts_disabled' => false,
                    ],
                ],
            ],
            'oxygen_integration' => [
                'title' => 'Oxygen Builder',
                'description' => 'Expose published Tasty Fonts families through Oxygen’s custom-font compatibility layer and mirror Oxygen global font families into Gutenberg.',
                'status_label' => 'Off',
                'enabled' => false,
                'available' => true,
            ],
            'gutenberg_integration' => [
                'title' => 'Gutenberg Font Library',
                'description' => 'Mirror imported families into WordPress typography controls so the block editor and site editor can use the same fonts managed by Tasty Fonts.',
                'status_label' => 'On',
                'enabled' => true,
            ],
            'acss_integration' => [
                'title' => 'Automatic.css',
                'description' => 'Sync ACSS heading and body font-family settings to Tasty Fonts role variables for clean interoperability.',
                'status_label' => 'Off',
                'enabled' => false,
                'available' => true,
                'current' => [
                    'heading' => '',
                    'body' => '',
                ],
                'desired' => [
                    'heading-font-family' => 'var(--font-heading)',
                    'text-font-family' => 'var(--font-body)',
                ],
            ],
            'role_deployment' => [
                'badge' => 'Live',
                'badge_class' => 'is-success',
                'title' => 'Live',
                'copy' => 'Current selections are being served sitewide.',
            ],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('No Bricks Theme Style found yet.', $output, 'The Bricks integration card should say when no Theme Style exists yet.');
    assertContainsValue('No Bricks Theme Style found yet. Tasty can create one for you.', $output, 'The Bricks integration card should explain that Tasty can create a managed Theme Style.');
    assertContainsValue('Create Tasty Theme Style', $output, 'The Bricks integration card should offer a direct Theme Style creation action.');
};

$tests['admin_page_renderer_omits_deprecated_inline_help_buttons_when_training_wheels_are_enabled'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'local_environment_notice' => [],
            'toasts' => [],
            'apply_everywhere' => false,
            'role_deployment' => [],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertNotContainsValue('tasty-fonts-help-button', $output, 'The deprecated inline help buttons should no longer render when Training Wheels is on.');
    assertContainsValue('data-help-tooltip=', $output, 'Training Wheels On should still expose passive tooltip copy on functional controls.');
};

$tests['admin_page_renderer_restructures_role_toolbar_with_explicit_actions'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();
    $deploymentPosition = strpos($output, 'Publish Workflow');
    $selectionPosition = strpos($output, 'Role Selection');
    $sectionStatusPosition = strpos($output, 'Sitewide on');
    $headingVariablePosition = strpos($output, 'data-role-variable-copy="heading"');
    $bodyVariablePosition = strpos($output, 'data-role-variable-copy="body"');

    assertContainsValue('Apply Sitewide', $output, 'The Font Roles form should expose an explicit apply sitewide action.');
    assertContainsValue('Switch Off Sitewide', $output, 'The Font Roles form should expose an explicit switch-off sitewide action.');
    assertContainsValue('Publish Roles', $output, 'The Font Roles form should keep a direct publish action in the role actions card.');
    assertContainsValue('data-disclosure-toggle="tasty-fonts-role-preview-panel"', $output, 'The utilities card should expose a dedicated preview disclosure button.');
    assertContainsValue('data-disclosure-toggle="tasty-fonts-role-snippets-panel"', $output, 'The utilities card should expose a dedicated snippets disclosure button.');
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

$tests['admin_page_renderer_only_highlights_publish_roles_when_changes_are_pending'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $pendingOutput = (string) ob_get_clean();

    assertContainsValue('button button-primary is-pending-live-change tasty-fonts-scope-button tasty-fonts-scope-button--apply', $pendingOutput, 'Publish Roles should stay highlighted when the draft differs from the live applied roles.');
    assertContainsValue('data-role-apply-live aria-disabled="false"', $pendingOutput, 'Publish Roles should remain active when there are live changes pending.');

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $matchedOutput = (string) ob_get_clean();

    assertContainsValue('class="button tasty-fonts-scope-button tasty-fonts-scope-button--apply"', $matchedOutput, 'Publish Roles should fall back to the shared neutral button styling when the draft already matches the live applied roles.');
    assertContainsValue('data-role-apply-live aria-disabled="true" disabled', $matchedOutput, 'Publish Roles should use the real disabled attribute when there is nothing new to publish.');
    assertContainsValue('No live role changes to publish.', $matchedOutput, 'The disabled Publish Roles action should explain why it is unavailable.');
    assertContainsValue('data-role-save-draft aria-disabled="true" disabled', $matchedOutput, 'Save Draft should start disabled until the draft changes.');
    assertContainsValue('No draft changes to save.', $matchedOutput, 'The disabled Save Draft action should explain why it is unavailable.');
};

$tests['admin_page_renderer_ignores_legacy_delivery_ids_when_comparing_role_changes'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
        $renderer->renderPage([
            'storage' => ['root' => '/tmp/uploads/fonts'],
            'catalog' => [
                'Lora' => [
                    'family' => 'Lora',
                    'active_delivery_id' => 'bunny-cdn-static',
                    'available_deliveries' => [
                        [
                            'id' => 'bunny-cdn-static',
                            'label' => 'Bunny CDN',
                        ],
                        [
                            'id' => 'self-hosted-static',
                            'label' => 'Self-hosted',
                        ],
                    ],
                ],
                'Inter' => [
                    'family' => 'Inter',
                    'active_delivery_id' => 'inter-static',
                    'available_deliveries' => [
                        [
                            'id' => 'inter-static',
                            'label' => 'Self-hosted',
                        ],
                    ],
                ],
            ],
            'available_families' => ['Inter', 'Lora'],
            'roles' => [
                'heading' => 'Lora',
                'body' => 'Inter',
                'heading_fallback' => 'sans-serif',
                'body_fallback' => 'sans-serif',
                'heading_delivery_id' => 'bunny-cdn-static',
                'body_delivery_id' => 'inter-static',
            ],
            'applied_roles' => [
                'heading' => 'Lora',
                'body' => 'Inter',
                'heading_fallback' => 'sans-serif',
                'body_fallback' => 'sans-serif',
                'heading_delivery_id' => '',
                'body_delivery_id' => '',
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
            'variable_fonts_enabled' => true,
            'role_deployment' => [],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('data-role-apply-live aria-disabled="true" disabled', $output, 'Publish Roles should stay disabled when legacy role delivery IDs are the only difference from the live roles.');
    assertNotContainsValue('is-pending-live-change', $output, 'Publish Roles should not show a pending-live highlight when only ignored legacy delivery IDs differ.');
};

$tests['admin_page_renderer_keeps_deployment_and_role_selection_ahead_of_library_and_activity'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
        $renderer->renderPage([
            'storage' => ['root' => '/tmp/uploads/fonts'],
            'catalog' => [
                'Lora' => [
                    'family' => 'Lora',
                    'slug' => 'lora',
                    'faces' => [],
                    'sources' => ['local'],
                    'delivery_badges' => [],
                    'font_category' => 'serif',
                    'font_category_tokens' => ['serif'],
                    'delivery_filter_tokens' => ['published', 'same-origin'],
                    'publish_state' => 'published',
                ],
            ],
            'available_families' => ['Lora'],
            'roles' => [
                'heading' => 'Lora',
                'heading_fallback' => 'serif',
                'body' => '',
                'body_fallback' => 'sans-serif',
            ],
            'applied_roles' => [
                'heading' => 'Lora',
                'heading_fallback' => 'serif',
                'body' => '',
                'body_fallback' => 'sans-serif',
            ],
            'logs' => [
                ['time' => '2026-04-07 09:20:40', 'actor' => 'root', 'message' => 'Fonts rescanned.'],
            ],
            'activity_actor_options' => ['root'],
            'family_fallbacks' => [],
            'family_font_displays' => [],
            'family_font_display_options' => [],
            'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
            'preview_size' => 32,
            'google_api_state' => 'empty',
            'google_api_enabled' => false,
            'google_api_saved' => false,
            'google_access_expanded' => false,
            'adobe_project_state' => 'empty',
            'google_status_label' => '',
            'google_status_class' => '',
            'google_access_copy' => '',
            'google_search_disabled_copy' => '',
            'adobe_project_enabled' => false,
            'adobe_project_saved' => false,
            'adobe_access_expanded' => false,
            'adobe_project_id' => '',
            'adobe_status_label' => '',
            'adobe_status_class' => '',
            'adobe_access_copy' => '',
            'adobe_project_link' => 'https://fonts.adobe.com/',
            'adobe_detected_families' => [],
            'css_delivery_mode' => 'file',
            'css_delivery_mode_options' => [],
            'font_display' => 'optional',
            'font_display_options' => [],
            'class_output_enabled' => false,
            'minify_css_output' => true,
            'per_variant_font_variables_enabled' => true,
            'extended_variable_weight_tokens_enabled' => true,
            'extended_variable_role_aliases_enabled' => true,
            'extended_variable_category_sans_enabled' => true,
            'extended_variable_category_serif_enabled' => true,
            'extended_variable_category_mono_enabled' => false,
            'preload_primary_fonts' => true,
            'remote_connection_hints' => true,
            'block_editor_font_library_sync_enabled' => false,
            'training_wheels_off' => false,
            'monospace_role_enabled' => false,
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    $deploymentPosition = strpos($output, 'Publish Workflow');
    $selectionPosition = strpos($output, 'Choose the Family and Fallback for Each Saved Role.');
    $libraryPosition = strpos($output, 'id="tasty-fonts-library"');
    $activityPosition = strpos($output, 'class="tasty-fonts-card tasty-fonts-activity-card"');

    assertSameValue(true, $deploymentPosition !== false && $selectionPosition !== false && $deploymentPosition < $selectionPosition, 'Deployment controls should render before role selection.');
    assertSameValue(true, $selectionPosition !== false && $libraryPosition !== false && $selectionPosition < $libraryPosition, 'Role selection should render before the library section.');
    assertSameValue(true, $libraryPosition !== false && $activityPosition !== false && $libraryPosition < $activityPosition, 'The library should render before the activity section.');
};

$tests['admin_page_renderer_closes_the_shell_wrapper_after_rendering_sections'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'local_environment_notice' => [],
            'toasts' => [],
            'apply_everywhere' => false,
            'role_deployment' => [],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertSameValue(1, substr_count($output, '<div class="tasty-fonts-shell"'), 'The page should open the shell wrapper once.');
    assertSameValue(
        1,
        preg_match('/<div class="tasty-fonts-shell"[^>]*>.*<\/div>\s*<\/div>\s*$/s', $output),
        'The shell wrapper should close before the outer admin wrapper ends.'
    );
};

$tests['admin_page_renderer_renders_preview_and_advanced_panels_inside_deployment_controls'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
        $renderer->renderPage([
            'storage' => ['root' => '/tmp/uploads/fonts'],
            'catalog' => [],
            'available_families' => ['Lora', 'Noto Sans'],
            'roles' => [
                'heading' => 'Lora',
                'heading_fallback' => 'serif',
                'body' => 'Noto Sans',
                'body_fallback' => 'sans-serif',
            ],
            'logs' => [],
            'activity_actor_options' => [],
            'family_fallbacks' => [],
            'family_font_displays' => [],
            'family_font_display_options' => [],
            'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
            'preview_size' => 32,
            'preview_panels' => [
                ['key' => 'editorial', 'label' => 'Editorial', 'active' => true],
            ],
            'font_display' => 'optional',
            'font_display_options' => [],
            'minify_css_output' => true,
            'preload_primary_fonts' => true,
            'remote_connection_hints' => true,
            'block_editor_font_library_sync_enabled' => false,
            'training_wheels_off' => false,
            'monospace_role_enabled' => false,
            'delete_uploaded_files_on_uninstall' => false,
            'diagnostic_items' => [],
            'overview_metrics' => [],
            'output_panels' => [],
            'generated_css_panel' => [],
            'local_environment_notice' => [],
            'toasts' => [],
            'apply_everywhere' => false,
            'role_deployment' => [],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    $deploymentPosition = strpos($output, 'Publish Workflow');
    $previewPanelPosition = strpos($output, 'id="tasty-fonts-role-preview-panel"');
    $snippetsPanelPosition = strpos($output, 'id="tasty-fonts-role-snippets-panel"');
    $settingsTabPosition = strpos($output, 'id="tasty-fonts-page-tab-settings"');
    $diagnosticsTabPosition = strpos($output, 'id="tasty-fonts-page-tab-diagnostics"');
    $roleSelectionPosition = strpos($output, 'Choose the Family and Fallback for Each Saved Role.');
    $libraryPosition = strpos($output, 'id="tasty-fonts-library"');

    assertSameValue(true, $deploymentPosition !== false, 'The publish workflow heading should render.');
    assertSameValue(true, $previewPanelPosition !== false, 'The preview panel markup should render.');
    assertSameValue(true, $snippetsPanelPosition !== false, 'The snippets panel markup should render.');
    assertSameValue(true, $settingsTabPosition !== false, 'The Settings top-level tab should render.');
    assertSameValue(true, $diagnosticsTabPosition !== false, 'The Diagnostics top-level tab should render.');
    assertSameValue(true, $roleSelectionPosition !== false, 'The role selection heading should render.');
    assertSameValue(true, $libraryPosition !== false, 'The library section should render.');
    assertSameValue(true, $deploymentPosition < $previewPanelPosition, 'The preview panel should render within the deployment controls section.');
    assertSameValue(true, $deploymentPosition < $snippetsPanelPosition, 'The snippets panel should render within the deployment controls section.');
    assertSameValue(true, $deploymentPosition < $roleSelectionPosition, 'The publish workflow should still render before role selection.');
    assertSameValue(true, $roleSelectionPosition < $libraryPosition, 'Role selection should still render before the library.');
};

$tests['admin_page_renderer_keeps_library_identity_and_preview_ahead_of_family_controls'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'Lora',
        'slug' => 'lora',
        'delivery_filter_tokens' => ['published', 'same-origin'],
        'font_category' => 'serif',
        'font_category_tokens' => ['serif'],
        'publish_state' => 'published',
        'active_delivery_id' => 'google-self-hosted',
        'active_delivery' => [
            'id' => 'google-self-hosted',
            'label' => 'Self-hosted (Google import)',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular', '700'],
        ],
        'available_deliveries' => [
            [
                'id' => 'google-self-hosted',
                'label' => 'Self-hosted (Google import)',
                'provider' => 'google',
                'type' => 'self_hosted',
                'variants' => ['regular', '700'],
            ],
        ],
        'delivery_badges' => [
            [
                'label' => 'In Use',
                'class' => 'is-success',
                'copy' => 'In Use',
            ],
            [
                'label' => 'Self-hosted',
                'class' => '',
                'copy' => 'Self-hosted',
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'google',
                'files' => ['woff2' => 'google/lora/lora-400-normal.woff2'],
                'paths' => ['woff2' => 'google/lora/lora-400-normal.woff2'],
            ],
            [
                'weight' => '700',
                'style' => 'normal',
                'source' => 'google',
                'files' => ['woff2' => 'google/lora/lora-700-normal.woff2'],
                'paths' => ['woff2' => 'google/lora/lora-700-normal.woff2'],
            ],
        ],
    ];

    ob_start();
    try {
        invokePrivateMethod(
            $renderer,
            'renderFamilyRow',
            [
                $family,
                [
                    'heading' => 'Lora',
                    'body' => '',
                    'heading_fallback' => 'serif',
                    'body_fallback' => 'sans-serif',
                ],
                [],
                [],
                [
                    ['value' => 'inherit', 'label' => 'Inherit Global (Optional)'],
                    ['value' => 'swap', 'label' => 'swap'],
                ],
                'The quick brown fox jumps over the lazy dog.',
                [],
            ]
        );
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    $titlePosition = strpos($output, '<h3>Lora</h3>');
    $previewPosition = strpos($output, 'tasty-fonts-font-inline-preview');
    $runtimeStatePosition = strpos($output, 'Runtime State');
    $fallbackPosition = strpos($output, 'Fallback');
    $fontDisplayPosition = strpos($output, 'Font Display');

    assertSameValue(true, $titlePosition !== false, 'Library cards should render the family title.');
    assertSameValue(true, $previewPosition !== false, 'Library cards should render the preview block.');
    assertSameValue(true, $runtimeStatePosition !== false, 'Library cards should render the runtime state control.');
    assertSameValue(true, $fallbackPosition !== false, 'Library cards should render the fallback control.');
    assertSameValue(true, $fontDisplayPosition !== false, 'Library cards should render the font display control.');
    assertSameValue(true, $titlePosition < $runtimeStatePosition, 'Family identity should render before the runtime state controls.');
    assertSameValue(true, $previewPosition < $runtimeStatePosition, 'Preview content should render before the runtime state controls.');
    assertSameValue(true, $runtimeStatePosition < $fallbackPosition, 'Runtime state should render before fallback inside the family controls.');
    assertSameValue(true, $fallbackPosition < $fontDisplayPosition, 'Fallback should render before font display inside the family controls.');
    assertContainsValue('data-clear-value="inherit"', $output, 'Font display controls should render a clear button back to the inherit option.');
};

$tests['admin_page_renderer_renders_highlighted_snippet_panels_with_icon_copy_buttons'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('class="button tasty-fonts-output-copy-button"', $output, 'Snippet panels should render the shared icon-only copy button.');
    assertContainsValue('data-copy-success="Snippet copied."', $output, 'Snippet panels should keep the shared copy feedback message.');
    assertContainsValue('<div class="tasty-fonts-code-panel-body" data-snippet-display aria-labelledby=', $output, 'Snippet panels should wrap highlighted output in the shared code panel body.');
    assertContainsValue('<pre class="tasty-fonts-output" data-snippet-view="raw" aria-labelledby=', $output, 'Snippet panels should render highlighted code blocks instead of textareas.');
    assertContainsValue('<code id="tasty-fonts-output-usage" class="tasty-fonts-output-code">', $output, 'Snippet panels should keep stable code block IDs for copy and accessibility hooks.');
    assertSameValue(1, preg_match('/id="tasty-fonts-output-tab-usage"[\s\S]*?tabindex="-1"/', $output), 'Inactive output tabs should be removed from the normal keyboard tab order.');
    assertContainsValue('tasty-fonts-syntax-property', $output, 'Snippet panels should wrap CSS properties in syntax token markup.');
    assertContainsValue('tasty-fonts-syntax-string', $output, 'Snippet panels should wrap strings in syntax token markup.');
    assertNotContainsValue('<textarea id="tasty-fonts-output-usage"', $output, 'Snippet panels should no longer render plain textareas.');
};

$tests['admin_page_renderer_pretty_prints_minified_snippets_for_highlighted_display'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('<span class="tasty-fonts-syntax-selector">:root</span>', $output, 'Minified snippet selectors should still be highlighted after display formatting.');
    assertContainsValue('<span class="tasty-fonts-syntax-property">font-family</span>', $output, 'Minified snippet declarations should be split into lines so property highlighting still applies.');
    assertContainsValue('data-copy-text=":root{--font-heading:&quot;Inter&quot;,serif;--font-body:&quot;Noto Sans&quot;,sans-serif}body{font-family:var(--font-body)}"', $output, 'Display formatting should not change the copied snippet payload.');
};

$tests['admin_page_renderer_pretty_prints_minified_variable_declaration_lists_for_display'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
        invokePrivateMethod(
            $renderer,
            'renderCodeEditor',
            [[
                'label' => 'CSS Variables',
                'target' => 'tasty-fonts-output-vars',
                'value' => '--font-heading:"Inter",serif;--font-body:"Noto Sans",sans-serif;',
            ]]
        );
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue("<span class=\"tasty-fonts-syntax-property\">--font-heading</span>", $output, 'Minified variable lists should still highlight the first declaration.');
    assertContainsValue("\n<span class=\"tasty-fonts-syntax-property\">--font-body</span>", $output, 'Minified variable lists should be split into one declaration per line for display.');
    assertContainsValue('data-copy-text="--font-heading:&quot;Inter&quot;,serif;--font-body:&quot;Noto Sans&quot;,sans-serif;"', $output, 'Variable list copy payloads should remain minified.');
};

$tests['admin_page_renderer_prefers_explicit_display_values_for_snippet_panels'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
        invokePrivateMethod(
            $renderer,
            'renderCodeEditor',
            [[
                'label' => 'CSS Variables',
                'target' => 'tasty-fonts-output-vars',
                'value' => '--font-heading:"Inter",serif;--font-body:"Noto Sans",sans-serif;',
                'display_value' => "/* Role font stacks */\n--font-heading: \"Inter\", serif;\n--font-body: \"Noto Sans\", sans-serif;",
            ]]
        );
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('tasty-fonts-syntax-comment', $output, 'Snippet panels should render explicit display comments when provided.');
    assertContainsValue('Role font stacks', $output, 'Snippet panels should show the provided readable section labels.');
    assertContainsValue('data-copy-text="--font-heading:&quot;Inter&quot;,serif;--font-body:&quot;Noto Sans&quot;,sans-serif;"', $output, 'Explicit display values should not change the copied snippet payload.');
};

$tests['admin_page_renderer_generated_css_defaults_to_actual_minified_output_with_readable_toggle'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('data-snippet-display-toggle', $output, 'Generated CSS should expose a display-only toggle when minified output is enabled.');
    assertContainsValue('data-label-default="Readable Preview"', $output, 'Generated CSS should offer a readable preview action from the actual output view.');
    assertContainsValue('data-label-active="Show Actual Output"', $output, 'Generated CSS should provide a way back to the actual saved output view.');
    assertContainsValue('<pre class="tasty-fonts-output" data-snippet-view="raw" aria-labelledby=', $output, 'Generated CSS should render the actual output view as the default visible block.');
    assertContainsValue('<code id="tasty-fonts-output-generated" class="tasty-fonts-output-code">', $output, 'Generated CSS should keep the stable code block ID for the raw output view.');
    assertContainsValue('data-snippet-view="readable" aria-labelledby=', $output, 'Generated CSS should render a labelled readable view for toggling.');
    assertContainsValue('hidden><code id="tasty-fonts-output-generated-readable"', $output, 'Generated CSS should keep the readable view hidden until toggled on.');
    assertContainsValue('data-copy-text=":root{--font-heading:&quot;Inter&quot;,serif}body{font-family:var(--font-heading)}"', $output, 'Generated CSS copy payloads should stay on the true minified output.');
};

$tests['admin_page_renderer_generated_css_omits_readable_toggle_when_output_is_already_unminified'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertNotContainsValue('data-snippet-display-toggle', $output, 'Generated CSS should not render a readable toggle when the saved output is already readable.');
    assertNotContainsValue('data-snippet-view="readable"', $output, 'Generated CSS should only render one view when no alternate preview is needed.');
};

$tests['admin_page_renderer_makes_copyable_diagnostic_values_click_to_copy'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
            'diagnostic_items' => [
                [
                    'label' => 'CSS Request URL',
                    'value' => 'https://example.test/wp-content/uploads/fonts/tasty-fonts.css',
                    'code' => true,
                    'copyable' => true,
                ],
                [
                    'label' => 'Stylesheet Size',
                    'value' => '4 KB',
                    'code' => false,
                    'copyable' => false,
                ],
            ],
            'overview_metrics' => [],
            'output_panels' => [],
            'generated_css_panel' => [],
            'preview_panels' => [],
            'toasts' => [],
            'apply_everywhere' => false,
            'role_deployment' => [],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('class="tasty-fonts-diagnostic-item tasty-fonts-diagnostic-item--copyable"', $output, 'Copyable diagnostic values should mark the card so the copy action can be positioned cleanly.');
    assertContainsValue('class="button tasty-fonts-output-copy-button tasty-fonts-diagnostic-copy-button"', $output, 'Copyable diagnostic values should render an icon-only copy button in the diagnostics card.');
    assertContainsValue('data-copy-text="https://example.test/wp-content/uploads/fonts/tasty-fonts.css"', $output, 'Copyable diagnostic values should expose their full value for the shared clipboard handler.');
    assertContainsValue('data-copy-success="CSS Request URL copied."', $output, 'Copyable diagnostic values should report which field was copied in the shared toast message.');
    assertContainsValue('<div class="tasty-fonts-diagnostic-value tasty-fonts-code">', $output, 'Diagnostic values should remain plain readable text instead of turning the whole field into a button.');
    assertNotContainsValue('data-copy-text="4 KB"', $output, 'Plain diagnostic values should not become copy controls.');
};

$tests['admin_page_renderer_renders_local_environment_notice_at_the_end_of_deploy_fonts_with_reminder_actions'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
                'message' => 'Turn this on when your local setup trusts this site\'s certificate.',
                'settings_label' => 'Open Integrations',
                'settings_url' => 'https://example.test/wp-admin/admin.php?page=tasty-custom-fonts&tf_page=settings&tf_studio=integrations',
            ],
            'toasts' => [],
            'apply_everywhere' => false,
            'role_deployment' => [],
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();
    $noticePosition = strpos($output, 'Local environment detected');
    $deploymentPosition = strpos($output, 'Publish Workflow');
    $roleSelectionPosition = strpos($output, 'Choose the Family and Fallback for Each Saved Role.');
    $activityPosition = strpos($output, 'No Activity Yet');

    assertContainsValue('Local environment detected', $output, 'The admin page should surface a dedicated notice for local environments.');
    assertContainsValue('Open Integrations', $output, 'The local-environment notice should include a direct action to open the Integrations panel.');
    assertContainsValue('Remind Tomorrow', $output, 'The local-environment notice should allow users to snooze the reminder until tomorrow.');
    assertContainsValue('Remind in 1 Week', $output, 'The local-environment notice should allow users to snooze the reminder for one week.');
    assertContainsValue('Never Show Again', $output, 'The local-environment notice should allow users to hide the reminder permanently for their account.');
    assertContainsValue('page=tasty-custom-fonts', $output, 'The local-environment notice action should deep-link to the unified admin page.');
    assertContainsValue('tf_page=settings', $output, 'The local-environment notice action should activate the Settings tab.');
    assertContainsValue('tf_studio=integrations', $output, 'The local-environment notice action should deep-link to the Integrations tab.');
    assertContainsValue('role="alert"', $output, 'Warning notices should render as announced alert regions.');
    assertContainsValue('aria-live="assertive"', $output, 'Warning notices should use assertive live-region semantics.');
    assertSameValue(true, $noticePosition !== false && $deploymentPosition !== false && $deploymentPosition < $noticePosition, 'The local-environment notice should render after the Deploy Fonts workflow controls.');
    assertSameValue(true, $noticePosition !== false && $roleSelectionPosition !== false && $roleSelectionPosition < $noticePosition, 'The local-environment notice should render after the last Deploy Fonts section.');
    assertSameValue(true, $noticePosition !== false && $activityPosition !== false && $noticePosition < $activityPosition, 'The local-environment notice should no longer be buried after the Activity section.');
};

$tests['admin_page_renderer_associates_code_previews_and_snippet_panels_with_visible_labels'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
        invokePrivateMethod(
            $renderer,
            'renderCodePreviewScene',
            [
                'The quick brown fox jumps over the lazy dog. 1234567890',
                [
                    'heading' => 'Inter',
                    'body' => 'Inter',
                    'monospace' => 'JetBrains Mono',
                ],
                true,
            ]
        );
        invokePrivateMethod(
            $renderer,
            'renderCodeEditor',
            [
                [
                    'label' => 'CSS Variables',
                    'target' => 'tasty-fonts-output-vars',
                    'value' => '--font-body: "Inter", sans-serif;',
                ],
            ]
        );
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('id="tasty-fonts-preview-code-editor-heading"', $output, 'The editor preview should expose a stable visible heading ID.');
    assertContainsValue('aria-labelledby="tasty-fonts-preview-code-editor-heading"', $output, 'The editor preview code surface should reference its visible heading.');
    assertContainsValue('id="tasty-fonts-preview-code-block-heading"', $output, 'The published code block should expose a stable visible heading ID.');
    assertContainsValue('aria-labelledby="tasty-fonts-preview-code-block-heading"', $output, 'The published code block surface should reference its visible heading.');
    assertContainsValue('id="tasty-fonts-output-vars-label"', $output, 'Snippet panels should render a stable heading ID derived from the panel target.');
    assertContainsValue('data-snippet-display aria-labelledby="tasty-fonts-output-vars-label"', $output, 'Snippet panel bodies should reference the visible panel label.');
};

$tests['admin_page_renderer_renders_activity_log_action_links'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
        $renderer->renderPage([
            'storage' => ['root' => '/tmp/uploads/fonts'],
            'catalog' => [],
            'available_families' => [],
            'roles' => [],
            'logs' => [[
                'time' => '2026-04-06 15:00:00',
                'message' => 'Block Editor Font Library sync failed.',
                'actor' => 'System',
                'action_label' => 'Open Integrations',
                'action_url' => 'https://example.test/wp-admin/admin.php?page=tasty-custom-fonts&tf_page=settings&tf_studio=integrations',
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('Open Integrations', $output, 'Activity log entries should render an inline action link when one is provided.');
    assertContainsValue('tasty-fonts-log-action', $output, 'Activity log action links should use the dedicated styling hook.');
    assertContainsValue('class="button tasty-fonts-button-danger tasty-fonts-font-action-button--icon tasty-fonts-activity-clear-button"', $output, 'The activity toolbar should render the clear-log action as an icon-only destructive button.');
    assertContainsValue('<span class="screen-reader-text">Clear Log</span>', $output, 'The icon-only clear-log action should keep an accessible text label.');
};

$tests['admin_page_renderer_renders_monospace_role_ui_when_enabled'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('Monospace Font', $output, 'Enabled monospace support should render the third role box in the main Font Roles form.');
    assertContainsValue('tasty-fonts-role-grid is-three-columns', $output, 'Enabled monospace support should switch the role grid into the three-column layout modifier.');
    assertContainsValue('Use Fallback Only', $output, 'Enabled monospace support should render the fallback-only monospace family option.');
    assertContainsValue('var(--font-monospace)', $output, 'Enabled monospace support should expose the monospace role variable in the role UI.');
};

$tests['admin_page_renderer_allows_fallback_only_heading_and_body_roles'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('name="tasty_fonts_heading_font"', $output, 'The role form should render the heading family selector.');
    assertContainsValue('name="tasty_fonts_body_font"', $output, 'The role form should render the body family selector.');
    assertContainsValue('name="tasty_fonts_heading_weight"', $output, 'The role form should render the heading weight selector shell.');
    assertContainsValue('name="tasty_fonts_body_weight"', $output, 'The role form should render the body weight selector shell.');
    assertContainsValue('name="tasty_fonts_heading_font" id="tasty_fonts_heading_font"', $output, 'The heading family selector should keep its expected id.');
    assertContainsValue('name="tasty_fonts_body_font" id="tasty_fonts_body_font"', $output, 'The body family selector should keep its expected id.');
    assertContainsValue('data-clear-target="tasty_fonts_heading_font"', $output, 'The heading family selector should render a clear button.');
    assertContainsValue('data-role-weight-editor="heading"', $output, 'The role form should render the heading weight editor shell.');
    assertContainsValue('data-role-weight-editor="body"', $output, 'The role form should render the body weight editor shell.');
    assertNotContainsValue('data-role-delivery-select="heading"', $output, 'The role form should not render a heading delivery selector once delivery is locked to the library.');
    assertNotContainsValue('data-role-delivery-select="body"', $output, 'The role form should not render a body delivery selector once delivery is locked to the library.');
    assertNotContainsValue('name="tasty_fonts_heading_fallback"', $output, 'The role form should no longer render a heading fallback field once fallback management lives in the library.');
    assertNotContainsValue('name="tasty_fonts_body_fallback"', $output, 'The role form should no longer render a body fallback field once fallback management lives in the library.');
    assertSameValue(true, substr_count($output, 'Use Fallback Only') >= 3, 'Heading, body, and preview selectors should all expose fallback-only choices.');
    assertContainsValue('Fallback only (sans-serif)', $output, 'Fallback-only heading selections should render a readable preview label.');
    assertContainsValue('Fallback only (serif)', $output, 'Fallback-only body selections should render a readable preview label.');
};

$tests['admin_page_renderer_preview_workspace_defaults_to_live_sitewide_baseline'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('Previewing:', $output, 'The preview workspace should render a visible source label.');
    assertContainsValue('Live sitewide', $output, 'The preview workspace should disclose when it is seeded from the live sitewide roles.');
    assertContainsValue('data-preview-role-select="heading"', $output, 'The preview tray should expose a heading picker.');
    assertContainsValue('data-preview-role-select="body"', $output, 'The preview tray should expose a body picker.');
    assertContainsValue('data-preview-role-select="monospace"', $output, 'The preview tray should expose a monospace picker when the role is enabled.');
    assertContainsValue('data-preview-weight-editor="heading"', $output, 'The preview tray should render a heading weight editor shell.');
    assertContainsValue('data-preview-axis-editor="heading"', $output, 'The preview tray should render a heading variable-axis editor shell.');
    assertContainsValue('data-preview-weight-select="body"', $output, 'The preview tray should expose a body weight selector for dynamic preview control.');
    assertContainsValue('data-preview-axis-fields="monospace"', $output, 'The preview tray should expose a monospace axis container when the role is enabled.');
    assertNotContainsValue('data-preview-delivery-select="heading"', $output, 'The preview tray should not render a heading delivery selector once delivery is locked to the library.');
    assertNotContainsValue('data-preview-delivery-select="body"', $output, 'The preview tray should not render a body delivery selector once delivery is locked to the library.');
    assertSameValue(true, substr_count($output, 'data-clear-select-button') >= 3, 'The preview tray should render clear buttons for its role pickers.');
    assertContainsValue('Use current draft selections', $output, 'The live baseline preview should offer a quick way to compare against the current draft roles.');
    assertContainsValue('value="Lora" selected', $output, 'The preview tray should seed its live baseline selector values from the applied sitewide roles.');
    assertSameValue(1, preg_match('/data-preview-save-draft[\s\S]*aria-disabled="false"/', $output), 'The preview save-draft action should be enabled when the preview differs from the current draft.');
    assertSameValue(1, preg_match('/data-preview-apply-live[\s\S]*aria-disabled="true"/', $output), 'The preview publish action should stay disabled when the preview already matches the live sitewide roles.');
};

$tests['admin_page_renderer_preview_workspace_defaults_to_draft_baseline'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('Current draft', $output, 'The preview workspace should disclose when it is seeded from the draft role selections.');
    assertContainsValue('Sync preview to role draft', $output, 'The draft baseline preview should offer a resync action for the current role controls.');
    assertNotContainsValue('data-preview-role-select="monospace"', $output, 'The preview tray should omit the monospace picker when the role is disabled.');
    assertSameValue(1, preg_match('/data-preview-save-draft[\s\S]*aria-disabled="true"/', $output), 'The preview save-draft action should stay disabled when the preview already matches the current draft.');
    assertSameValue(1, preg_match('/data-preview-apply-live[\s\S]*aria-disabled="true"/', $output), 'The preview publish action should stay disabled when sitewide delivery is off.');
};

$tests['admin_page_renderer_renders_typed_family_labels_in_role_and_preview_selectors'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
        $renderer->renderPage([
            'storage' => ['root' => '/tmp/uploads/fonts'],
            'catalog' => [
                'Inter' => [
                    'family' => 'Inter',
                    'has_variable_faces' => true,
                ],
                'Lora' => [
                    'family' => 'Lora',
                    'faces' => [
                        [
                            'weight' => '400',
                            'style' => 'normal',
                        ],
                    ],
                ],
                'JetBrains Mono' => [
                    'family' => 'JetBrains Mono',
                    'faces' => [
                        [
                            'weight' => '400',
                            'style' => 'normal',
                        ],
                    ],
                ],
            ],
            'available_families' => ['Inter', 'Lora', 'JetBrains Mono', 'Legacy Stack'],
            'roles' => [
                'heading' => 'Inter',
                'body' => 'Lora',
                'monospace' => 'JetBrains Mono',
                'heading_fallback' => 'sans-serif',
                'body_fallback' => 'serif',
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('>Inter · Variable<', $output, 'Role and preview selectors should suffix variable families with a Variable label.');
    assertContainsValue('>Lora · Static<', $output, 'Role and preview selectors should suffix static families with a Static label.');
    assertContainsValue('>JetBrains Mono · Static<', $output, 'Monospace role selectors should reuse the typed family labels.');
    assertContainsValue('>Legacy Stack<', $output, 'Families that are not in the saved catalog should remain available without a type suffix.');
    assertSameValue(1, preg_match('/data-role-preview-name="heading">Inter · Variable</', $output), 'Inline preview family names should reuse the typed selector label for heading.');
    assertSameValue(1, preg_match('/data-role-preview-name="body">Lora · Static</', $output), 'Inline preview family names should reuse the typed selector label for body.');
    assertSameValue(1, preg_match('/data-role-preview-name="monospace">JetBrains Mono · Static</', $output), 'Inline preview family names should reuse the typed selector label for monospace.');
};

$tests['admin_page_renderer_uses_a_dedicated_code_preview_scene'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
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
    try {
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
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('data-role-assign="monospace"', $output, 'Enabled family cards should expose the monospace quick-assign control.');
    assertContainsValue('>Monospace<', $output, 'Enabled family cards should render a Monospace badge for the selected monospace family.');
    assertSameValue(1, preg_match('/data-font-family="JetBrains Mono"[\s\S]*?Static/', $output), 'Static library family rows should expose a Static badge in the saved library card.');
    assertContainsValue('Code Preview', $output, 'Monospace family cards should switch their specimen label to a code-oriented preview.');
    assertContainsValue('tasty-fonts-font-inline-preview is-monospace', $output, 'Monospace library cards should render the inline preview with the monospace modifier class.');
    assertContainsValue('tasty-fonts-face-preview is-monospace', $output, 'Monospace face detail cards should render the preview with the monospace modifier class.');
    assertContainsValue('400 Regular', $output, 'Expanded face detail cards should pair numeric weights with a readable weight label.');
    assertContainsValue('>const font = &quot;JetBrains Mono&quot;;', $output, 'Monospace preview markup should not inject template indentation before the code sample text.');
    assertNotContainsValue('font-family: var(--font-monospace);', $output, 'Monospace card previews should now stay on a single code line instead of rendering multiline specimen copy.');
    assertContainsValue('currently assigned to monospace, and this is the last saved variant', $output, 'Last-variant delete guards should mention the monospace role when it protects the family.');
    assertSameValue(1, preg_match('/data-delete-family="JetBrains Mono"[\s\S]*?aria-disabled="true"[\s\S]*?disabled/', $output), 'Blocked family delete actions should use the real disabled attribute as well as aria-disabled.');
};
