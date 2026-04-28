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
    assertContainsValue('tasty-fonts-font-source-badge', $output, 'Family card headers should render one source pill.');
    assertContainsValue('title="Google CDN"', $output, 'The source pill should expose the active delivery label.');
    assertContainsValue('aria-label="Google CDN"', $output, 'The source pill should expose the active delivery label to assistive technology.');
    assertNotContainsValue('tasty-fonts-badges--library-inline', $output, 'Family card headers should not render secondary delivery metadata rows.');
    assertNotContainsValue('tasty-fonts-face-summary-badges', $output, 'Family card headers should not render face summary badge rows.');
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

$tests['family_card_renderer_summary_rows_omit_header_metadata_pills'] = static function (): void {
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
            'variants' => ['400', '700', '700italic'],
        ],
        'available_deliveries' => [
            [
                'id' => 'local-self-hosted',
                'label' => 'Self-hosted',
                'provider' => 'local',
                'type' => 'self_hosted',
                'variants' => ['400', '700', '700italic'],
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
            [
                'weight' => '700',
                'style' => 'normal',
                'source' => 'local',
                'files' => ['woff2' => 'inter/Inter-700-normal.woff2'],
                'paths' => ['woff2' => 'inter/Inter-700-normal.woff2'],
            ],
            [
                'weight' => '700',
                'style' => 'italic',
                'source' => 'local',
                'files' => ['woff2' => 'inter/Inter-700-italic.woff2'],
                'paths' => ['woff2' => 'inter/Inter-700-italic.woff2'],
            ],
        ],
    ];

    ob_start();
    try {
        $renderer->renderFamilySummaryRow(
            $family,
            ['heading' => '', 'body' => ''],
            [],
            [],
            [
                ['value' => 'inherit', 'label' => 'Use plugin default'],
            ],
            'The quick brown fox jumps over the lazy dog.',
            [],
            ['enabled' => true],
            false,
            []
        );
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('tasty-fonts-font-source-badge', $output, 'Collapsed family cards should still show one source pill.');
    assertContainsValue('title="Self-hosted"', $output, 'The source pill should expose the active delivery label.');
    assertNotContainsValue('tasty-fonts-face-summary-badges', $output, 'Collapsed family cards should not render face summary badge rows.');
    assertNotContainsValue('title="2 weights"', $output, 'Collapsed family cards should not render weight-count metadata pills.');
    assertNotContainsValue('title="2 styles"', $output, 'Collapsed family cards should not render style-count metadata pills.');
    assertNotContainsValue('>2 weights<', $output, 'Collapsed family cards should keep weight metadata out of the header.');
    assertNotContainsValue('>2 styles<', $output, 'Collapsed family cards should keep style metadata out of the header.');
    assertNotContainsValue('>700 italic<', $output, 'Collapsed family cards should not enumerate every saved face token.');
    assertNotContainsValue('+1 more', $output, 'Collapsed family cards should not render overflow face-token counters.');
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
            'show_activity_log' => true,
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
    assertContainsValue('<h2 class="screen-reader-text" id="tasty-fonts-settings-panel-title">Settings</h2>', $output, 'The Settings panel should keep an accessible stable section heading regardless of tab activation order.');
    assertSameValue(4, substr_count($output, 'id="tasty-fonts-page-tab-'), 'The unified admin page should render four top-level page tabs.');
    assertSameValue(1, preg_match('/id="tasty-fonts-page-tab-library"[\s\S]*?tabindex="-1"/', $output), 'Inactive top-level page tabs should be removed from the normal keyboard tab order.');
    assertSameValue(1, preg_match('/id="tasty-fonts-settings-tab-integrations"[\s\S]*?tabindex="-1"/', $output), 'Inactive Settings sub-tabs should use roving tabindex.');
    assertSameValue(1, preg_match('/id="tasty-fonts-settings-tab-plugin-behavior"[\s\S]*?tabindex="-1"/', $output), 'Inactive Settings sub-tabs should use roving tabindex.');
    assertNotContainsValue('id="tasty-fonts-settings-tab-transfer"', $output, 'Transfer should live under Advanced Tools instead of Settings.');
    assertNotContainsValue('id="tasty-fonts-settings-tab-developer"', $output, 'Developer tools should live under Advanced Tools instead of Settings.');
    assertSameValue(1, preg_match('/id="tasty-fonts-preview-tab-code"[\s\S]*?tabindex="-1"/', $output), 'Inactive preview tabs should be removed from the normal keyboard tab order.');
    assertSameValue(1, preg_match('/id="tasty-fonts-add-font-tab-bunny"[\s\S]*?tabindex="-1"/', $output), 'Inactive add-font tabs should be removed from the normal keyboard tab order.');
    assertSameValue(1, preg_match('/id="tasty-fonts-diagnostics-tab-generated"[\s\S]*?tabindex="-1"/', $output), 'Inactive diagnostics tabs should be removed from the normal keyboard tab order.');
    assertSameValue(1, preg_match('/Version [0-9]+\.[0-9]+\.[0-9]+/', $output), 'The page heading should include the current plugin version for assistive technologies.');
    assertContainsValue('id="tasty-fonts-settings-form"', $output, 'The Settings tab should render one shared settings form for Output, Integrations, and Behavior.');
    assertContainsValue('data-settings-form="settings"', $output, 'The Settings tab should render shared explicit-save state tracking for the combined settings form.');
    assertNotContainsValue('data-settings-autosave="output"', $output, 'The Settings tab should no longer render autosave wiring for output settings.');
    assertNotContainsValue('data-settings-autosave="integrations"', $output, 'The Settings tab should no longer render autosave wiring for integrations settings.');
    assertNotContainsValue('data-settings-autosave="behavior"', $output, 'The Settings tab should no longer render autosave wiring for behavior settings.');
    assertContainsValue('form="tasty-fonts-settings-form"', $output, 'The shared Settings save button should submit the combined settings form from the header.');
    assertSameValue(1, substr_count($output, 'data-settings-save-button'), 'The shared Settings save control should render once in the header.');
    assertSameValue(1, substr_count($output, 'data-settings-clear-button'), 'The shared Settings clear control should render once in the header.');
    assertContainsValue('>Save changes<', $output, 'Settings sections should expose an explicit Save changes action.');
    assertContainsValue('>Clear changes<', $output, 'Settings sections should expose an explicit way to discard unsaved settings changes.');
    assertContainsValue('>Font Library<', $output, 'The unified admin page should still include the library section inside its own tab panel.');
    assertContainsValue('Show Activity Log', $output, 'The Behavior panel should expose an activity log visibility setting.');
    assertContainsValue('Events are still recorded when hidden.', $output, 'The activity log visibility setting should explain that logging continues while hidden.');
    assertNotContainsValue('Recent scans, imports, deletes, and asset refreshes. Newest entries appear first.', $output, 'The diagnostics activity log should be hidden by default.');
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
            'show_activity_log' => true,
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

    assertContainsValue('No Fonts Yet', $emptyOutput, 'The unified Library tab should preserve the empty-library state.');
    assertContainsValue('<h2 class="screen-reader-text" id="tasty-fonts-library-panel-title">Font Library</h2>', $emptyOutput, 'The library panel should keep an accessible stable section heading regardless of tab activation order.');
	assertContainsValue('<option value="url-import">URL Import</option>', $emptyOutput, 'The library source filter should let users isolate fonts imported from URL CSS.');
	assertContainsValue('id="tasty-fonts-add-font-tab-url"', $emptyOutput, 'The Add Fonts panel should still expose a From URL entry point.');
	assertContainsValue('Workflow Off', $emptyOutput, 'The From URL panel should show the font import workflow state when URL imports are off.');
	assertContainsValue('Enable URL Imports is turned on in Settings &gt; Behavior', $emptyOutput, 'The From URL panel should explain where to enable the workflow.');
	assertNotContainsValue('id="tasty-fonts-url-dry-run-form"', $emptyOutput, 'The From URL panel should not render the dry-run form while the developer gate is off.');

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
            'show_activity_log' => true,
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
    assertNotContainsValue('No Fonts Yet', $populatedOutput, 'The populated Library tab should not render the empty state.');
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
        $renderer->renderPage($baseContext + ['variable_fonts_enabled' => true, 'custom_css_url_imports_enabled' => true]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $enabledOutput = (string) ob_get_clean();

    assertNotContainsValue('tasty-fonts-upload-face-shell--static-only', $enabledOutput, 'The upload builder should keep the full upload grid when variable font support is on.');
    assertContainsValue('data-upload-field="is-variable"', $enabledOutput, 'The upload builder should render variable upload toggles when variable font support is on.');
    assertContainsValue('id="tasty-fonts-url-dry-run-form"', $enabledOutput, 'The From URL panel should render the dry-run form when the custom CSS URL import gate is on.');
    assertContainsValue('The dry run reads CSS and shows supported WOFF or WOFF2 faces.', $enabledOutput, 'The enabled From URL panel should explain the dry-run review scope.');
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
            'show_activity_log' => true,
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

    assertContainsValue('CSS Variable Groups', $output, 'Output Settings should render grouped variable controls.');
    assertContainsValue('Role Weight Variables', $output, 'The submenu should include the role weight variable group.');
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
            'show_activity_log' => true,
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
    assertContainsValue('data-output-detail-group="classes"', $output, 'Output Settings should render the class output detail group.');
    assertContainsValue('data-output-detail-group="variables"', $output, 'Output Settings should render the variable output detail group.');
    assertContainsValue('data-output-detail-group="sitewide"', $output, 'Output Settings should render the sitewide output detail group.');
    assertContainsValue('Utility Class Groups', $output, 'Output Settings should render grouped utility class controls.');
    assertContainsValue('Family Classes', $output, 'Output Settings should group the per-family class toggle under its own disclosure.');
    assertContainsValue('name="class_output_families_enabled"', $output, 'The granular family class toggle should submit through the shared settings form.');
    assertContainsValue('name="class_output_role_styles_enabled"', $output, 'The role class style toggle should submit through the shared settings form.');
    assertContainsValue('Role Styling', $output, 'Output Settings should surface the shared role class styling section.');
    assertContainsValue('Role Weights in Classes', $output, 'Output Settings should explain the opt-in role class styling toggle.');
    assertContainsValue('Adds role weights and variation settings to class output.', $output, 'Output Settings should explain how role class styling differs from sitewide role weight output.');
    assertContainsValue('Sitewide Role Weights', $output, 'Output Settings should render the sitewide role weight setting.');
    assertContainsValue('Utility Classes', $output, 'Output Settings should render the class output master toggle.');
    assertContainsValue('CSS Variables', $output, 'Output Settings should render the variable output master toggle.');
};

$tests['admin_page_renderer_scopes_output_groups_to_quick_mode'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $render = static function (array $overrides) use ($renderer): string {
        ob_start();
        try {
            $renderer->renderPage($overrides + [
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

        return (string) ob_get_clean();
    };

    $variablesOutput = $render([
        'output_quick_mode_preference' => 'variables',
        'class_output_enabled' => false,
        'per_variant_font_variables_enabled' => true,
        'minimal_output_preset_enabled' => false,
        'extended_variable_role_weight_vars_enabled' => true,
        'extended_variable_weight_tokens_enabled' => true,
        'extended_variable_role_aliases_enabled' => true,
        'extended_variable_category_sans_enabled' => true,
        'extended_variable_category_serif_enabled' => true,
        'role_usage_font_weight_enabled' => false,
    ]);

    assertSameValue(1, preg_match('/tasty-fonts-output-settings-advanced-panel[^"]*is-variables-only/', $variablesOutput), 'Variables-only should render the focused variables layout before JS runs.');
    assertSameValue(1, preg_match('/data-output-detail-group="variables"(?![^>]*hidden)/', $variablesOutput), 'Variables-only should show the variables group.');
    assertSameValue(1, preg_match('/data-output-detail-group="classes"[^>]*hidden/', $variablesOutput), 'Variables-only should hide the classes group.');
    assertSameValue(1, preg_match('/data-output-detail-group="sitewide"[^>]*hidden/', $variablesOutput), 'Variables-only should hide the sitewide group.');
    assertSameValue(1, preg_match('/<label[^>]*data-output-layer-master-row="variables"[^>]*hidden[^>]*>.*?CSS Variables/s', $variablesOutput), 'Variables-only should hide the redundant variable layer master toggle.');

    $classesOutput = $render([
        'output_quick_mode_preference' => 'classes',
        'class_output_enabled' => true,
        'class_output_role_heading_enabled' => true,
        'class_output_role_body_enabled' => true,
        'class_output_role_alias_interface_enabled' => true,
        'class_output_role_alias_ui_enabled' => true,
        'class_output_category_sans_enabled' => true,
        'class_output_category_serif_enabled' => true,
        'class_output_families_enabled' => true,
        'per_variant_font_variables_enabled' => false,
        'minimal_output_preset_enabled' => false,
        'role_usage_font_weight_enabled' => false,
    ]);

    assertSameValue(1, preg_match('/tasty-fonts-output-settings-advanced-panel[^"]*is-classes-only/', $classesOutput), 'Classes-only should render the focused classes layout before JS runs.');
    assertSameValue(1, preg_match('/data-output-detail-group="classes"(?![^>]*hidden)/', $classesOutput), 'Classes-only should show the classes group.');
    assertSameValue(1, preg_match('/data-output-detail-group="variables"[^>]*hidden/', $classesOutput), 'Classes-only should hide the variables group.');
    assertSameValue(1, preg_match('/data-output-detail-group="sitewide"[^>]*hidden/', $classesOutput), 'Classes-only should hide the sitewide group.');
    assertSameValue(1, preg_match('/<label[^>]*data-output-layer-master-row="classes"[^>]*hidden[^>]*>.*?Utility Classes/s', $classesOutput), 'Classes-only should hide the redundant class layer master toggle.');

    $customOutput = $render([
        'output_quick_mode_preference' => 'custom',
        'class_output_enabled' => true,
        'per_variant_font_variables_enabled' => true,
        'minimal_output_preset_enabled' => false,
        'role_usage_font_weight_enabled' => true,
    ]);

    assertSameValue(1, preg_match('/data-output-detail-group="sitewide"(?![^>]*hidden)/', $customOutput), 'Custom should show the sitewide group.');
    assertSameValue(1, preg_match('/data-output-detail-group="classes"(?![^>]*hidden)/', $customOutput), 'Custom should show the classes group.');
    assertSameValue(1, preg_match('/data-output-detail-group="variables"(?![^>]*hidden)/', $customOutput), 'Custom should show the variables group.');
    assertSameValue(1, preg_match('/<label[^>]*data-output-layer-master-row="classes"[^>]*hidden[^>]*>.*?Utility Classes/s', $customOutput), 'Custom should hide the class layer master toggle because the grouped options carry the visible choice.');
    assertSameValue(1, preg_match('/<label[^>]*data-output-layer-master-row="variables"[^>]*hidden[^>]*>.*?CSS Variables/s', $customOutput), 'Custom should hide the variable layer master toggle because the grouped options carry the visible choice.');
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
                'role_weight_vars' => true,
                'weight_tokens' => true,
                'role_aliases' => true,
                'category_sans' => true,
                'category_serif' => true,
            ],
            false,
        ]
    );
    assertSameValue('variables', $variablesMode, 'Quick mode should resolve to variables only when the full preset baseline remains intact.');

    $customVariablesMode = invokePrivateMethod(
        $builder,
        'deriveOutputQuickMode',
        [
            'variables',
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
                'role_weight_vars' => false,
                'weight_tokens' => true,
                'role_aliases' => true,
                'category_sans' => true,
                'category_serif' => true,
            ],
            false,
        ]
    );
    assertSameValue('custom', $customVariablesMode, 'Quick mode should resolve to custom when a variables-only subgroup is disabled.');

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

    $styledClassesMode = invokePrivateMethod(
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
                'role_styles' => true,
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
    assertSameValue('classes', $styledClassesMode, 'Quick mode should stay classes only when the preset-specific role class styling option is enabled.');

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

$tests['admin_page_view_builder_casts_admin_access_user_ids_to_strings'] = static function (): void {
    resetTestState();

    $builder = new AdminPageViewBuilder(new Storage());
    $view = $builder->build([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => [],
        'admin_access_role_slugs' => ['editor'],
        'admin_access_role_options' => [
            ['value' => 'editor', 'label' => 'Editor'],
        ],
        'admin_access_user_ids' => [3],
        'admin_access_user_options' => [
            ['value' => '3', 'label' => 'Author User (author)'],
        ],
    ]);

    assertSameValue(['editor'], $view['adminAccessRoleSlugs'] ?? null, 'The view builder should pass through selected admin-access roles for the template.');
    assertSameValue(['3'], $view['adminAccessUserIds'] ?? null, 'The view builder should cast selected admin-access user IDs to strings for strict template comparisons.');
    assertSameValue('Author User (author)', $view['adminAccessUserOptions'][0]['label'] ?? '', 'The view builder should pass through admin-access user options for the template.');
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
    assertSameValue(true, !empty($view['classOutputEnabled']), 'Custom should treat the class layer as enabled because the visible grouped controls now own the choice.');
    assertSameValue(true, !empty($view['perVariantFontVariablesEnabled']), 'Custom should treat the variable layer as enabled because the visible grouped controls now own the choice.');

    $variablesView = $builder->build([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => [],
        'output_quick_mode_preference' => 'variables',
        'class_output_enabled' => false,
        'per_variant_font_variables_enabled' => true,
        'minimal_output_preset_enabled' => false,
        'extended_variable_role_weight_vars_enabled' => true,
        'extended_variable_weight_tokens_enabled' => true,
        'extended_variable_role_aliases_enabled' => true,
        'extended_variable_category_sans_enabled' => true,
        'extended_variable_category_serif_enabled' => true,
        'role_usage_font_weight_enabled' => false,
    ]);
    assertSameValue('variables', (string) ($variablesView['outputQuickMode'] ?? ''), 'A saved variables-only quick-mode preference should remain selected when its grouped controls match.');
    assertSameValue(true, !empty($variablesView['advancedOutputControlsExpanded']), 'Variables-only should expand its grouped output controls on initial render.');

    $classesView = $builder->build([
        'storage' => ['root' => '/tmp/uploads/fonts'],
        'catalog' => [],
        'available_families' => [],
        'output_quick_mode_preference' => 'classes',
        'class_output_enabled' => true,
        'class_output_role_heading_enabled' => true,
        'class_output_role_body_enabled' => true,
        'class_output_role_alias_interface_enabled' => true,
        'class_output_role_alias_ui_enabled' => true,
        'class_output_category_sans_enabled' => true,
        'class_output_category_serif_enabled' => true,
        'class_output_families_enabled' => true,
        'per_variant_font_variables_enabled' => false,
        'minimal_output_preset_enabled' => false,
        'role_usage_font_weight_enabled' => false,
    ]);
    assertSameValue('classes', (string) ($classesView['outputQuickMode'] ?? ''), 'A saved classes-only quick-mode preference should remain selected when its grouped controls match.');
    assertSameValue(true, !empty($classesView['advancedOutputControlsExpanded']), 'Classes-only should expand its grouped output controls on initial render.');
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
            'show_activity_log' => true,
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

    assertSameValue(1, preg_match('/class="tasty-fonts-badge is-role"[\s\S]*?Variable/', $output), 'Expanded detail metadata should expose a readable Variable badge.');
    assertContainsValue('tasty-fonts-font-source-badge', $output, 'The family card header should keep only the source pill visible.');
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

$tests['family_card_renderer_shows_custom_css_source_history_for_custom_profiles_only'] = static function (): void {
	resetTestState();

	$renderer = new FamilyCardRenderer(new Storage());

	ob_start();
	try {
		$renderer->renderDeliveryProfileCard(
			'Fixture Sans',
			'fixture-sans',
			'custom-self-hosted',
			'published',
			[
				'id' => 'custom-self-hosted',
				'label' => 'Self-hosted custom CSS (assets.example.com, 2026-04-27)',
				'provider' => 'custom',
				'type' => 'self_hosted',
				'variants' => ['400'],
				'meta' => [
					'source_type' => 'custom_css_url',
					'source_css_url' => 'https://assets.example.com/fonts.css',
					'source_host' => 'assets.example.com',
					'delivery_mode' => 'self_hosted',
				],
				'faces' => [
					[
						'weight' => '400',
						'style' => 'normal',
						'source' => 'custom',
						'files' => ['woff2' => 'custom/fixture-sans/fixture-sans.woff2'],
						'paths' => ['woff2' => 'custom/fixture-sans/fixture-sans.woff2'],
					],
				],
			]
		);
		$renderer->renderDeliveryProfileCard(
			'Fixture Sans',
			'fixture-sans',
			'custom-remote',
			'published',
			[
				'id' => 'custom-remote',
				'label' => 'Remote custom CSS (assets.example.com, 2026-04-27)',
				'provider' => 'custom',
				'type' => 'cdn',
				'variants' => ['700'],
				'meta' => [
					'source_type' => 'custom_css_url',
					'source_css_url' => 'https://assets.example.com/remote.css',
					'source_host' => 'assets.example.com',
					'delivery_mode' => 'remote',
				],
				'faces' => [
					[
						'weight' => '700',
						'style' => 'normal',
						'source' => 'custom',
						'files' => ['woff2' => 'https://cdn.example.com/fixture-sans-700.woff2'],
						'paths' => [],
						'provider' => [
							'type' => 'custom_css',
							'remote_url' => 'https://cdn.example.com/fixture-sans-700.woff2',
							'last_verified_at' => '2026-04-27T12:34:00+00:00',
						],
					],
				],
			]
		);
		$renderer->renderDeliveryProfileCard(
			'Inter',
			'inter',
			'google-cdn',
			'published',
			[
				'id' => 'google-cdn',
				'label' => 'Google CDN',
				'provider' => 'google',
				'type' => 'cdn',
				'variants' => ['regular'],
				'meta' => [
					'source_css_url' => 'https://fonts.googleapis.com/css2?family=Inter',
				],
			]
		);
	} catch (\Throwable $e) {
		ob_end_clean();
		throw $e;
	}
	$output = (string) ob_get_clean();

	assertContainsValue('Source CSS URL', $output, 'Custom CSS delivery details should label the read-only source URL history.');
	assertContainsValue('https://assets.example.com/fonts.css', $output, 'Custom self-hosted profiles should render the original source CSS URL.');
	assertContainsValue('https://assets.example.com/remote.css', $output, 'Custom remote-serving profiles should render the original source CSS URL.');
	assertContainsValue('Self-hosted custom CSS files', $output, 'Custom self-hosted profiles should clarify that files were copied locally.');
	assertContainsValue('Remote custom CSS font URLs', $output, 'Custom remote-serving profiles should clarify that generated CSS points at remote URLs.');
	assertContainsValue('Read-only source history from the original import.', $output, 'Custom CSS source URLs should be presented as read-only history.');
	assertContainsValue('Font files were copied to WordPress uploads. The original CSS URL is retained as read-only history.', $output, 'Self-hosted source history copy should be read-only and practical.');
	assertContainsValue('Visitors request the reviewed remote font URLs while Tasty Fonts generates the CSS.', $output, 'Remote-serving source history copy should clarify visitor requests.');
	assertContainsValue('Last Verified', $output, 'Custom remote profiles with verification metadata should show the last verified label.');
	assertContainsValue('datetime="2026-04-27T12:34:00+00:00"', $output, 'Last verified timestamps should keep the stored machine-readable value.');
	assertNotContainsValue('https://fonts.googleapis.com/css2?family=Inter', $output, 'Known provider profiles should not render custom CSS source URL metadata.');
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
            'admin_access_custom_enabled' => true,
            'admin_access_role_slugs' => ['editor'],
            'admin_access_role_options' => [
                ['value' => 'administrator', 'label' => 'Administrator', 'count' => 1, 'meta' => '1 user · always allowed', 'disabled' => true],
                ['value' => 'author', 'label' => 'Author'],
                ['value' => 'editor', 'label' => 'Editor'],
            ],
            'admin_access_user_ids' => [3],
            'admin_access_user_options' => [
                ['value' => '1', 'label' => 'Admin User (admin)', 'meta' => 'Administrator · already has access', 'disabled' => true],
                ['value' => '2', 'label' => 'Editor User (editor)'],
                ['value' => '3', 'label' => 'Author User (author)'],
            ],
	            'admin_access_summary' => [
	                'role_count' => 1,
	                'role_user_impact' => 3,
	                'user_count' => 1,
	                'implicit_admin_count' => 1,
	                'implicit_admin_labels' => ['Admin User (admin)'],
	            ],
	            'block_editor_font_library_sync_enabled' => false,
	            'show_activity_log' => true,
	            'training_wheels_off' => true,
	            'variable_fonts_enabled' => true,
	            'delete_uploaded_files_on_uninstall' => false,
	            'diagnostic_items' => [],
	            'overview_metrics' => [],
	            'site_transfer' => [
	                'available' => true,
	                'message' => '',
	                'export_url' => 'https://example.test/wp-admin/admin.php?action=tasty_fonts_download_site_transfer_bundle',
	                'import_file_field' => 'tasty_fonts_site_transfer_bundle',
	                'import_stage_token_field' => 'tasty_fonts_site_transfer_stage_token',
	                'import_google_api_key_field' => 'tasty_fonts_import_google_api_key',
	                'import_action_field' => 'tasty_fonts_import_site_transfer_bundle',
	                'effective_upload_limit_label' => '32 MB',
	                'snapshot_rename_action_field' => 'tasty_fonts_rename_rollback_snapshot',
	                'snapshot_delete_action_field' => 'tasty_fonts_delete_rollback_snapshot',
	                'snapshot_retention_limit' => 4,
	                'snapshot_retention_min' => 1,
	                'snapshot_retention_max' => 10,
	                'snapshots' => [
	                    [
	                        'id' => 'snapshot-20260424-170456-a1b2c3d4',
	                        'created_at' => '2026-04-24 17:04:56',
	                        'reason' => 'manual',
	                        'plugin_version' => '1.14.0-beta.1',
	                        'families' => 2,
	                        'files' => 6,
	'font_files' => 2,
	'storage_files' => 6,
	                        'size' => 4096,
	                        'label' => 'Before client review',
	                        'family_names' => ['Inter', 'Roboto Slab'],
	                        'role_families' => ['Inter'],
	                    ],
	                ],
	                'logs' => [],
	                'actor_options' => [],
	            ],
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
	assertNotContainsValue('Configure onboarding hints, import workflows, activity visibility, font capabilities, and admin access.', $output, 'The Behavior tab should no longer describe Advanced Tools guidance or activity visibility settings.');
    assertSameValue(1, preg_match('/id="tasty-fonts-diagnostics-panel-maintenance"[\s\S]*Show Onboarding Hints[\s\S]*Show Activity Log/', $output), 'The Advanced Tools Developer tab should expose onboarding and activity visibility settings together.');
    assertNotContainsValue('Hide Onboarding Hints', $output, 'The onboarding hints setting should not be framed as a hide setting.');
    assertSameValue(1, preg_match('/name="training_wheels_off" value="1"[\s\S]*name="training_wheels_off" value="0"/', $output), 'The positive onboarding hints toggle should still submit the stored inverse setting correctly.');
    assertSameValue(1, preg_match('/name="show_activity_log" value="0"[\s\S]*name="show_activity_log" value="1"/', $output), 'The activity log toggle should submit an explicit off value from Advanced Tools.');
    assertNotContainsValue('Advanced Import Gates', $output, 'The Advanced Tools Developer tab should no longer host font import workflow toggles.');
    assertContainsValue('Font Import Workflows', $output, 'The Behavior tab should group font import workflow toggles.');
    assertContainsValue('Enable Google Fonts Imports', $output, 'The Behavior tab should expose the Google Fonts import workflow toggle.');
    assertContainsValue('Enable Bunny Fonts Imports', $output, 'The Behavior tab should expose the Bunny Fonts import workflow toggle.');
    assertContainsValue('Enable Adobe Fonts Imports', $output, 'The Behavior tab should expose the Adobe Fonts import workflow toggle.');
    assertContainsValue('Enable Custom Uploads', $output, 'The Behavior tab should expose the custom upload workflow toggle.');
	assertSameValue(1, preg_match('/name="google_font_imports_enabled" value="1" checked="checked"/', $output), 'Google Fonts imports should render on by default.');
	assertSameValue(1, preg_match('/name="bunny_font_imports_enabled" value="1" checked="checked"/', $output), 'Bunny Fonts imports should render on by default.');
	assertSameValue(1, preg_match('/name="local_font_uploads_enabled" value="1" checked="checked"/', $output), 'Custom uploads should render on by default.');
	assertSameValue(1, preg_match('/name="adobe_font_imports_enabled" value="1"(?! checked="checked")/', $output), 'Adobe Fonts imports should render off by default.');
    assertContainsValue('name="custom_css_url_imports_enabled" value="0"', $output, 'The URL import workflow toggle should submit an explicit off value.');
	assertSameValue(1, preg_match('/name="custom_css_url_imports_enabled" value="1"(?! checked="checked")/', $output), 'URL imports should render off by default.');
    assertNotContainsValue('Save Gate', $output, 'The Developer tab should no longer show a save action for URL imports.');
    assertContainsValue('Release Rail', $output, 'The Advanced Tools Developer tab should group release-channel controls for testing workflows.');
    assertContainsValue('Testing Channel', $output, 'The Advanced Tools Developer tab should frame release-channel controls as a testing setting.');
    assertContainsValue('Update Channel', $output, 'The Advanced Tools Developer tab should expose the update channel selector.');
    assertContainsValue('Stable', $output, 'The Advanced Tools Developer tab should render the stable update channel option inline.');
    assertContainsValue('Beta', $output, 'The Advanced Tools Developer tab should render the beta update channel option inline.');
    assertContainsValue('Nightly', $output, 'The Advanced Tools Developer tab should render the nightly update channel option inline.');
    assertNotContainsValue('Save Channel', $output, 'The Advanced Tools Developer tab should autosave release-channel changes without a local save action.');
    assertNotContainsValue('Release Updates', $output, 'The Behavior tab should no longer group release-channel controls.');
    assertNotContainsValue('Update Rail', $output, 'The Behavior tab should no longer label release-channel controls.');
    assertNotContainsValue('tasty-fonts-settings-flat-row-status--channel', $output, 'The update channel row should not repeat update channel state inline.');
    assertNotContainsValue('Installed: 1.7.0. Latest for Beta: 1.7.1-beta.2.', $output, 'The update channel row should not repeat version context already shown in the masthead.');
    assertNotContainsValue('A newer package is available for the selected channel through the normal WordPress updates flow.', $output, 'The update channel row should omit the redundant update channel status copy.');
    assertNotContainsValue('Rollback Reinstall', $output, 'The update channel control should no longer render a separate rollback subsection title.');
    assertNotContainsValue('Enable Block Editor Font Library Sync', $output, 'The Behavior panel should no longer render the Gutenberg sync toggle after it moves into Integrations.');
    assertContainsValue('Font Capabilities', $output, 'The Behavior tab should group font-role options separately from admin-experience toggles.');
    assertContainsValue('Roles &amp; Axes', $output, 'The Behavior tab should explain that font capability settings cover roles and axes.');
    assertContainsValue('Admin Experience', $output, 'The Advanced Tools Developer tab should group guidance and activity visibility controls.');
    assertContainsValue('Guidance &amp; Logs', $output, 'The Advanced Tools Developer tab should label the helper/activity category consistently.');
    assertContainsValue('Cleanup &amp; Access', $output, 'The Behavior tab should group uninstall cleanup and admin access controls together.');
    assertContainsValue('Files &amp; Permissions', $output, 'The Behavior tab should label cleanup/access controls with their shared permission scope.');
    assertContainsValue('Enable Monospace Role', $output, 'The Behavior panel should still render the monospace toggle.');
    assertContainsValue('Enable Variable Fonts', $output, 'The Behavior panel should render the opt-in variable font toggle.');
    assertContainsValue('Keep Uploaded Fonts on Uninstall', $output, 'The Behavior panel should render the uninstall retention toggle with positive wording.');
    assertNotContainsValue('Configure onboarding hints, import workflows, activity visibility, font capabilities, and admin access.', $output, 'The old Behavior tab summary should not mention moved Advanced Tools settings.');
    assertNotContainsValue('Delete Uploaded Fonts on Uninstall', $output, 'The Behavior panel should not frame uploaded font retention as a delete setting.');
    assertSameValue(1, preg_match('/name="delete_uploaded_files_on_uninstall" value="1"[\s\S]*name="delete_uploaded_files_on_uninstall" value="0"/', $output), 'The positive uninstall retention toggle should still submit the stored inverse setting correctly.');
    assertContainsValue('Enable Additional Access Rules', $output, 'The admin-access panel should expose the master toggle for fine-grained access with positive wording.');
    assertNotContainsValue('Grant Tasty Fonts access to extra roles and users. Administrators always keep access.', $output, 'Show Onboarding Hints should omit admin-access toggle helper copy when turned off.');
    assertNotContainsValue('Turn custom access on to grant additional roles or users.', $output, 'Show Onboarding Hints should omit admin-access summary helper copy when turned off.');
    assertNotContainsValue('Grant access to everyone in selected roles.', $output, 'Show Onboarding Hints should omit admin-access role helper copy when turned off.');
    assertNotContainsValue('Grant access to selected users only.', $output, 'Show Onboarding Hints should omit admin-access user helper copy when turned off.');
    assertNotContainsValue('Custom access is on.', $output, 'The admin-access row should no longer render a separate status summary line.');
    assertNotContainsValue('Administrators:', $output, 'The admin-access row should no longer render a separate summary bar above the detailed controls.');
    assertNotContainsValue('Specific users:', $output, 'The admin-access row should no longer render the removed summary bar labels.');
    assertContainsValue('Admin User (admin)', $output, 'The admin-access panel should still show administrator users in the individual user list.');
    assertContainsValue('Administrator · already has access', $output, 'The admin-access panel should explain why administrator users are not selectable in the individual user list.');
    assertContainsValue('Additional Roles', $output, 'The admin-access panel should expose the additional roles field.');
    assertContainsValue('Administrator', $output, 'The admin-access panel should show the implicit administrator role in the role list.');
    assertContainsValue('1 user · always allowed', $output, 'The admin-access panel should explain that the administrator role is always allowed.');
    assertContainsValue('Specific Users', $output, 'The admin-access panel should expose the specific users field.');
    assertContainsValue('Filter users by name, login, or role', $output, 'The admin-access panel should replace the native multi-select with a searchable list.');
    assertContainsValue('data-admin-access-summary-count="roles"', $output, 'The admin-access panel should show role-grant impact metrics when custom access is enabled.');
    assertContainsValue('data-admin-access-summary-count="users"', $output, 'The admin-access panel should show specific-user grant metrics when custom access is enabled.');
    assertContainsValue('name="admin_access_custom_enabled"', $output, 'The admin-access mode toggle should submit through the shared settings form.');
    assertContainsValue('name="admin_access_role_slugs[]"', $output, 'The admin-access roles field should submit through the shared settings form.');
    assertContainsValue('name="admin_access_user_ids[]"', $output, 'The admin-access users field should submit through the shared settings form.');
    assertContainsValue('form="tasty-fonts-settings-form"', $output, 'Behavior-tab admin-access controls should stay associated with the shared settings form.');
    assertSameValue(1, preg_match('/<input(?=[^>]*name="admin_access_role_slugs\[\]")(?=[^>]*value="administrator")(?=[^>]*disabled="disabled")[^>]*>/', $output), 'Administrator role should render as a disabled entry in the role checklist.');
    assertSameValue(1, preg_match('/<input(?=[^>]*name="admin_access_role_slugs\[\]")(?=[^>]*value="editor")(?=[^>]*checked="checked")[^>]*>/', $output), 'Saved admin-access role grants should remain selected in the Behavior tab.');
    assertSameValue(1, preg_match('/<input(?=[^>]*name="admin_access_user_ids\[\]")(?=[^>]*value="1")(?=[^>]*disabled="disabled")[^>]*>/', $output), 'Administrator users should render as disabled entries in the searchable Behavior-tab checklist.');
    assertSameValue(1, preg_match('/<input(?=[^>]*name="admin_access_user_ids\[\]")(?=[^>]*value="3")(?=[^>]*checked="checked")[^>]*>/', $output), 'Saved admin-access user grants should remain selected in the searchable Behavior-tab checklist.');
    assertContainsValue('Reset plugin settings only', $output, 'The Developer tab should expose the explicit settings-reset action.');
    assertContainsValue('Delete managed font library', $output, 'The Developer tab should expose the explicit library-delete action.');
    assertContainsValue('id="tasty-fonts-diagnostics-tab-cli"', $output, 'The Advanced Tools switcher should expose the CLI tab.');
    assertContainsValue('id="tasty-fonts-diagnostics-panel-cli"', $output, 'The Advanced Tools page should render a dedicated CLI panel.');
    assertContainsValue('tasty-fonts-cli-command-group', $output, 'The CLI tab should group copyable WP-CLI commands.');
    assertContainsValue('wp tasty-fonts doctor --format=json', $output, 'The CLI tab should expose the doctor JSON command.');
    assertContainsValue('wp tasty-fonts google-api-key status', $output, 'The CLI tab should expose a safe Google API key status command.');
    assertContainsValue('wp tasty-fonts google-api-key save', $output, 'The CLI tab should expose a safe Google API key save prompt command.');
    assertContainsValue('wp tasty-fonts css regenerate', $output, 'The CLI tab should expose the generated CSS regeneration command.');
    assertContainsValue('wp tasty-fonts settings reset --yes', $output, 'The CLI tab should expose the guarded settings reset command.');
    assertContainsValue('wp tasty-fonts files delete --yes', $output, 'The CLI tab should expose the guarded managed files delete command.');
    assertContainsValue('wp tasty-fonts transfer import /path/to/tasty-fonts-transfer.zip --dry-run --prompt-google-api-key', $output, 'The CLI tab should expose the transfer dry-run command with safe optional key input.');
    assertContainsValue('wp tasty-fonts transfer import /path/to/tasty-fonts-transfer.zip --yes --prompt-google-api-key', $output, 'The CLI tab should expose the transfer import command with safe optional key input.');
    assertContainsValue('data-copy-text="wp tasty-fonts support-bundle --format=json"', $output, 'The CLI tab should make support bundle commands copy-ready.');
    assertContainsValue('data-copy-text="wp tasty-fonts snapshot restore &lt;snapshot-id&gt; --yes"', $output, 'The CLI tab should make destructive snapshot commands copy-ready.');
    assertContainsValue('Command copied.', $output, 'The CLI tab should use the shared copy feedback contract.');
    assertContainsValue('Transfer &amp; Recovery', $output, 'The Transfer tab should render the transfer and recovery workbench.');
    assertNotContainsValue('Workbench Status', $output, 'The Transfer tab should avoid a redundant status board above the actual tools.');
    assertContainsValue('Portable Transfer', $output, 'The Transfer tab should group export and import validation together.');
    assertContainsValue('Export Bundle', $output, 'The Transfer tab should expose the portable export action.');
	assertContainsValue('Import Bundle', $output, 'The Transfer tab should expose the import transfer action row.');
    assertContainsValue('tasty-fonts-site-transfer-intake-panel', $output, 'The transfer import controls should render as a compact intake panel inside the row UI.');
    assertContainsValue('Bundle ZIP', $output, 'The transfer import controls should label the required ZIP input without the old detached grid heading.');
    assertContainsValue('Google API Key', $output, 'The transfer import controls should label the optional Google credential clearly.');
    assertNotContainsValue('Destination Secret', $output, 'The transfer import controls should not use vague secret wording for the Google API key.');
    assertContainsValue('id="tasty-fonts-site-transfer-form"', $output, 'The site transfer import form should expose a stable id so the shared header action can target it.');
    assertContainsValue('data-site-transfer-form', $output, 'The site transfer panel should expose the dedicated import form hook for client-side state management.');
    assertContainsValue('data-site-transfer-stage-token', $output, 'The site transfer panel should keep a hidden staged-bundle token field for dry-run/import handoff.');
    assertContainsValue('data-site-transfer-import-submit', $output, 'The site transfer panel should render a local import button after the dry-run action.');
    assertContainsValue('tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--download tasty-fonts-site-transfer-button', $output, 'Transfer export actions should use the shared Advanced Tools row action contract.');
    assertContainsValue('tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--validate tasty-fonts-site-transfer-button', $output, 'Transfer dry-run actions should use the shared Advanced Tools row action contract.');
    assertContainsValue('tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--import tasty-fonts-site-transfer-button', $output, 'Transfer import actions should use the shared Advanced Tools row action contract.');
    assertContainsValue('tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--snapshot tasty-fonts-site-transfer-button', $output, 'Snapshot actions should use the shared Advanced Tools row action contract.');
    assertContainsValue('tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--support tasty-fonts-site-transfer-button', $output, 'Support bundle actions should use the shared Advanced Tools row action contract.');
    assertContainsValue('data-site-transfer-summary="bundle"', $output, 'The site transfer panel should expose the bundle readiness summary hook.');
    assertContainsValue('data-site-transfer-summary="limit"', $output, 'The site transfer panel should expose the upload limit readiness summary hook.');
    assertContainsValue('data-site-transfer-summary="google"', $output, 'The site transfer panel should expose the Google API key readiness summary hook.');
    assertSameValue(1, preg_match('/<div class="tasty-fonts-site-transfer-summary-item"[^>]*data-state="neutral"[^>]*>\s*<span class="tasty-fonts-site-transfer-summary-label">Upload limit<\/span>\s*<span class="tasty-fonts-site-transfer-summary-value" data-site-transfer-summary="limit">/s', $output), 'The upload limit summary should start neutral until a bundle is selected.');
    assertSameValue(1, preg_match('/<div class="tasty-fonts-site-transfer-summary-item"[^>]*data-state="neutral"[^>]*>\s*<span class="tasty-fonts-site-transfer-summary-label">Google API key<\/span>\s*<span class="tasty-fonts-site-transfer-summary-value" data-site-transfer-summary="google">Optional<\/span>/s', $output), 'The optional Google API key summary should start neutral instead of showing as a passing check.');
    assertContainsValue('Snapshots &amp; Support', $output, 'The Transfer tab should group rollback and support bundle tools.');
    assertContainsValue('Before client review', $output, 'Snapshot rows should expose friendly snapshot names.');
	assertContainsValue('2 font files · 6 storage files · 2 font families', $output, 'Snapshot rows should distinguish font files from captured storage files.');
    assertContainsValue('Families: Inter, Roboto Slab', $output, 'Snapshot rows should summarize the font families inside the restore point.');
    assertContainsValue('Live roles: Inter', $output, 'Snapshot rows should summarize the live role families inside the restore point.');
    assertContainsValue('name="tasty_fonts_snapshot_label"', $output, 'Snapshot rows should allow friendly renaming.');
    assertContainsValue('tasty_fonts_delete_rollback_snapshot', $output, 'Snapshot rows should expose a delete action for local cleanup.');
    assertContainsValue('Delete this rollback snapshot permanently?', $output, 'Snapshot delete actions should require a destructive confirmation.');
    assertContainsValue('name="snapshot_retention_limit"', $output, 'Snapshot recovery should expose a retention limit setting.');
    assertContainsValue('value="4"', $output, 'Snapshot retention should render the saved keep-latest limit.');
    assertContainsValue('Activity Log', $output, 'The Transfer tab should render a dedicated transfer activity panel.');
    assertNotContainsValue('transfer event recorded', $output, 'The Workbench Status summary should not duplicate the dedicated transfer activity panel.');
    assertContainsValue('No transfer activity yet', $output, 'The Transfer tab should render a transfer-specific empty state when no transfer history exists.');
    assertContainsValue('tasty-fonts-health-help-trigger', $output, 'The Transfer tab should include contextual help triggers for transfer and recovery rows.');
    assertSameValue(1, preg_match('/>\s*Dry Run Bundle\s*</', $output), 'The transfer card should expose a dry-run action before destructive import.');
    assertSameValue(1, preg_match('/>\s*Import Bundle\s*</', $output), 'The transfer card should expose an import action after a successful dry run.');
    assertSameValue(
        1,
        preg_match('/data-site-transfer-validate-submit[\s\S]*disabled/', $output),
        'The site transfer dry-run button should render disabled until a bundle file is selected.'
    );
    assertSameValue(
        1,
        preg_match('/data-site-transfer-import-submit[\s\S]*disabled/', $output),
        'The site transfer import button should render disabled until a bundle is dry-run validated.'
    );
    assertContainsValue('tasty-fonts-health-board tasty-fonts-developer-board', $output, 'The Developer tab should use the shared health board layout.');
    assertContainsValue('tasty-fonts-health-row tasty-fonts-developer-row', $output, 'The Developer tab should render each tool as a shared health row.');
    assertContainsValue('tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--rebuild tasty-fonts-developer-action-button', $output, 'Developer maintenance actions should use the shared Advanced Tools row action contract.');
    assertContainsValue('tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--generate tasty-fonts-developer-action-button', $output, 'Developer CSS actions should use the shared Advanced Tools row action contract.');
    assertContainsValue('tasty-fonts-button-danger tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--delete tasty-fonts-developer-action-button', $output, 'Developer destructive actions should use the shared danger row action contract.');
    assertNotContainsValue('These tools run immediately and do not use Save changes.', $output, 'Show Onboarding Hints should omit developer maintenance helper copy when turned off.');
    assertContainsValue('Maintenance', $output, 'The Developer tab should group routine cleanup tools into a maintenance section.');
    assertContainsValue('Danger Zone', $output, 'The Developer tab should separate destructive tools into a dedicated danger zone.');
    assertNotContainsValue('Immediate actions', $output, 'The Developer tab should remove the redundant immediate-actions heading.');
    assertNotContainsValue('Housekeeping', $output, 'The Developer tab should fold lightweight UI resets into the maintenance section.');
    assertContainsValue('Clear caches and rebuild assets', $output, 'The Developer tab should expose the cache-reset action.');
    assertContainsValue('Regenerate CSS file', $output, 'The Developer tab should expose the simplified CSS-regeneration action title.');
    assertContainsValue('Run integration scan', $output, 'The Developer tab should expose the simplified integration re-detect action title.');
    assertContainsValue('tasty-fonts-developer-tool-meta-detected', $output, 'The integration scan row should summarize detected integrations.');
    assertContainsValue('tasty-fonts-developer-tool-meta-enabled', $output, 'The integration scan row should summarize enabled integrations.');
    assertSameValue(1, preg_match('/tasty-fonts-advanced-row-action--navigate tasty-fonts-developer-action-button[\s\S]*?tf_studio=integrations[\s\S]*?>\s*Integrations\s*<\/a>/', $output), 'The integration scan row should include a direct link to Integration settings.');
    assertContainsValue('Restore notices', $output, 'The Developer tab should expose the simplified notices reset action title.');
    assertNotContainsValue('Cache + CSS', $output, 'The Developer tab should no longer render developer badge pills.');
    assertNotContainsValue('Check CSS', $output, 'The Developer tab should remove non-critical status badges from tool rows.');
    assertNotContainsValue('Needs rebuild', $output, 'The Developer tab should remove non-critical status badges from tool rows.');
    assertNotContainsValue('tasty-fonts-badge is-warning">Caution', $output, 'The Developer danger zone should not repeat risk with a redundant caution badge.');
    assertNotContainsValue('tasty-fonts-badge is-danger">Destructive', $output, 'The Developer danger zone should not repeat risk with a redundant destructive badge.');
    assertContainsValue('data-developer-confirm-message=', $output, 'Developer actions that need confirmation should expose confirm copy for client-side safeguards.');
    assertNotContainsValue('data-developer-confirm-input=', $output, 'Developer danger actions should use the compact destructive button confirmation instead of a typed unlock panel.');
    assertNotContainsValue('Editor Integrations', $output, 'The Behavior panel should no longer render the editor integrations subsection title.');
    assertNotContainsValue('Role Options', $output, 'The Behavior panel should no longer render the role options subsection title.');
    assertNotContainsValue('Uninstall Settings', $output, 'The Behavior panel should no longer render the uninstall settings subsection title.');
    assertNotContainsValue('Saved automatically:', $output, 'Settings panels should no longer render autosave footnotes.');
    assertContainsValue('tasty-fonts-settings-save-button', $output, 'The shared Settings form should render the pill-style save button in the header.');
    assertNotContainsValue('Unsaved changes', $output, 'The shared Settings header should rely on button state instead of a separate unsaved-changes message.');
    assertContainsValue('tasty-fonts-header-logo', $output, 'The masthead should render the branded logo in place of the plain text title.');
    assertContainsValue('https://tastywp.com/tastyfonts/', $output, 'The branded masthead logo should link to the Tasty Fonts product page.');
    assertContainsValue('screen-reader-text', $output, 'The branded masthead should keep an accessible text label for assistive technology.');
    assertContainsValue('tasty-fonts-page-header-kicker', $output, 'The refreshed masthead should render a compact product label beside the logo.');
    assertContainsValue('Pair role fonts, review, and publish.', $output, 'The refreshed masthead should render contextual summary copy for the active page.');
    assertContainsValue('tasty-fonts-version-link-meta', $output, 'The masthead version pill should render the channel and updater state meta line.');
    assertContainsValue('Beta', $output, 'The masthead version pill should disclose the selected update channel.');
    assertContainsValue('Beta · Update Available', $output, 'The masthead version pill should summarize channel and update status together.');
    assertContainsValue('Latest available: 1.7.1-beta.2.', $output, 'The masthead version pill tooltip should expose the latest package for the selected channel.');
    assertContainsValue('is-training-wheels-off', $output, 'Show Onboarding Hints should add the admin state class used to suppress descriptive copy when turned off.');
    assertNotContainsValue('Typography Workspace', $output, 'The masthead should omit the eyebrow label in the streamlined header layout.');
    assertNotContainsValue('Professional Typography Management For WordPress', $output, 'The streamlined masthead should omit the legacy hero tagline.');
    assertNotContainsValue('Deploy fonts, manage your library, and fine-tune output from one polished workspace.', $output, 'The streamlined masthead should omit the supporting summary copy.');
    assertNotContainsValue('Choose whether the generated stylesheet loads as a file or is printed inline in the page head.', $output, 'Show Onboarding Hints should omit output setting descriptions from the rendered HTML when turned off.');
    assertNotContainsValue('Keep builder and framework integrations aligned with Tasty Fonts role variables.', $output, 'Show Onboarding Hints should omit integrations tab summary descriptions from the rendered HTML when turned off.');
    assertNotContainsValue('Tasty Fonts manages only the two base ACSS font-family settings needed for heading and body text.', $output, 'The Integrations tab should omit the redundant ACSS desired mapping description.');
    assertNotContainsValue('Control optional roles, guidance, and uninstall cleanup.', $output, 'The Behavior tab should omit the redundant summary description.');
    assertNotContainsValue('Manual reset and maintenance tools for plugin development, troubleshooting, and integration work.', $output, 'The Developer tab should omit the redundant summary description.');
    assertNotContainsValue('Shows helper tips and info buttons across the admin UI.', $output, 'Show Onboarding Hints should omit behavior toggle descriptions from the rendered HTML when turned off.');
    assertNotContainsValue('tasty-fonts-toggle-description', $output, 'Show Onboarding Hints should omit settings toggle description elements from the rendered HTML when turned off.');
    assertNotContainsValue('tasty-fonts-help-button', $output, 'Show Onboarding Hints should remove inline help buttons from the rendered admin UI when turned off.');
    assertNotContainsValue('data-role-deployment-pill data-help-tooltip=', $output, 'Show Onboarding Hints should omit passive role-deployment hover help from the rendered admin UI when turned off.');
    assertNotContainsValue('data-help-tooltip=', $output, 'Show Onboarding Hints should omit passive hover help across every rendered panel when turned off.');
    assertNotContainsValue('Export this setup or dry-run an incoming bundle before anything is replaced.', $output, 'Show Onboarding Hints should omit transfer status helper copy when turned off.');
    assertNotContainsValue('Packages sanitized diagnostics, storage metadata, generated CSS, and recent activity without API keys.', $output, 'Show Onboarding Hints should omit transfer support helper copy when turned off.');
    assertNotContainsValue('Downloads settings, live roles, library metadata, and managed font files.', $output, 'Show Onboarding Hints should omit transfer export helper copy when turned off.');
    assertNotContainsValue('Choose a transfer ZIP, validate the diff, then import only after the dry-run is clear.', $output, 'Show Onboarding Hints should omit transfer dry-run helper copy when turned off.');
    assertNotContainsValue('Required for dry run', $output, 'Show Onboarding Hints should omit transfer field helper copy when turned off.');
    assertNotContainsValue('Optional for Google font imports', $output, 'Show Onboarding Hints should omit transfer field helper copy when turned off.');
    assertNotContainsValue('Paste a fresh Google API key for this site', $output, 'Show Onboarding Hints should omit transfer placeholder helper copy when turned off.');
    assertNotContainsValue('Paste a Google Fonts API key if this bundle needs one', $output, 'Show Onboarding Hints should omit transfer placeholder helper copy when turned off.');
    assertNotContainsValue('Create a local restore point before manual work.', $output, 'Show Onboarding Hints should omit snapshot helper copy when turned off.');
    assertNotContainsValue('Downloads one sanitized ZIP for troubleshooting', $output, 'Show Onboarding Hints should omit support bundle helper copy when turned off.');
    assertNotContainsValue('Exports, imports, snapshots, support bundles, and transfer recovery messages will appear here.', $output, 'Show Onboarding Hints should omit transfer activity helper copy when turned off.');
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

    assertNotContainsValue('tasty-fonts-settings-flat-row-status--channel', $output, 'The update channel field should not repeat rollback state inline.');
    assertNotContainsValue('Installed: 1.8.0-dev. Latest for Stable: 1.7.0.', $output, 'The update channel field should not render redundant rollback version context inline.');
    assertContainsValue('data-help-tooltip="The selected channel points to an older package than the one installed now. Use the reinstall action below to switch immediately."', $output, 'The Advanced Tools Developer tab should expose rollback guidance through the shared passive help tooltip system.');
    assertContainsValue('Stable · Rollback Available', $output, 'The masthead version pill should expose rollback state alongside the selected channel.');
    assertContainsValue('Latest available: 1.7.0.', $output, 'The masthead version pill tooltip should expose the latest rollback target version.');
    assertNotContainsValue('aria-controls="tasty-fonts-help-tooltip-layer"', $output, 'Passive help triggers should not misuse aria-controls for tooltip relationships.');
    assertNotContainsValue('<p class="tasty-fonts-settings-flat-row-note tasty-fonts-settings-flat-row-note--channel">The selected channel points to an older package than the one installed now. Use the reinstall action below to switch immediately.</p>', $output, 'Rollback guidance should no longer render as an inline sentence when the reinstall action is available.');
    assertContainsValue('Reinstall', $output, 'The Advanced Tools Developer tab should attach the rollback action directly to the update channel field.');
    assertNotContainsValue('Save Channel', $output, 'The Advanced Tools Developer tab should keep release-channel changes automatic.');
    assertNotContainsValue('tasty-fonts-reinstall-update-channel-form', $output, 'The rollback action should no longer depend on a hidden Settings-tab form.');
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
                'description' => 'Sync imported families into the WordPress Font Library.',
                'status_label' => 'On',
                'enabled' => true,
            ],
            'acss_integration' => [
                'title' => 'Automatic.css',
                'description' => 'Sync Automatic.css font settings with Tasty role variables.',
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

    assertContainsValue('Builders', $output, 'The Integrations tab should group Etch, Bricks, and Oxygen under builder integrations.');
    assertContainsValue('Canvas + Theme Controls', $output, 'The builder group should describe both Etch canvas loading and builder theme controls.');
    assertContainsValue('Etch Canvas Bridge', $output, 'The Etch bridge should render inside the builder integrations group.');
	assertSameValue(
		1,
		preg_match('/<h4>Builders<\/h4>[\s\S]*?<div class="tasty-fonts-output-settings-form tasty-fonts-integrations-form">\s*<div class="tasty-fonts-output-settings-list tasty-fonts-integrations-list tasty-fonts-settings-board-list">[\s\S]*?name="bricks_integration_enabled"/', $output),
		'Builder integration toggles should be wrapped in the integration form/list grid expected by the shared settings CSS.'
	);
    assertContainsValue('WordPress', $output, 'The Integrations tab should group Gutenberg under WordPress.');
    assertContainsValue('Font Library', $output, 'The WordPress group should identify the font library sync control.');
    assertContainsValue('Frameworks', $output, 'The Integrations tab should group Automatic.css as a framework integration.');
    assertContainsValue('Role Mapping', $output, 'Framework token integrations should identify role mapping as the action.');
    assertNotContainsValue('Builder Sync', $output, 'The Integrations tab should not keep a separate builder sync category after Etch is grouped with builders.');
    assertNotContainsValue('Framework Tokens', $output, 'The Integrations tab should use the cleaner Frameworks category label.');
    assertContainsValue('Sync imported families into the WordPress Font Library.', $output, 'The Gutenberg integration summary should render within the main toggle copy.');
    assertContainsValue('On', $output, 'The Gutenberg integration row should still surface its current status.');
    assertNotContainsValue('Sync to Gutenberg Font Library', $output, 'The Gutenberg integration should no longer render a second row title.');
    assertSameValue(1, substr_count($output, 'Gutenberg Font Library'), 'The Gutenberg integration should render a single main title.');
    assertContainsValue('Sync Automatic.css font settings with Tasty role variables.', $output, 'The Automatic.css integration summary should render within the main toggle copy.');
    assertContainsValue('Synced', $output, 'The Automatic.css integration row should still surface its current status.');
    assertContainsValue('tasty-fonts-integration-mapping tasty-fonts-acss-mapping', $output, 'The Automatic.css managed mapping should render as the shared settings mapping table.');
    assertContainsValue('Font mapping', $output, 'The Automatic.css managed mapping should use a compact table label.');
    assertContainsValue('Tasty role variable', $output, 'The Automatic.css managed mapping should label the destination variable clearly.');
    assertNotContainsValue('Current to Target', $output, 'The Automatic.css managed mapping should not describe the row as an abstract current-to-target comparison.');
    assertContainsValue('Heading Weight', $output, 'The Automatic.css managed mapping should list the heading font-weight field.');
    assertContainsValue('Body Weight', $output, 'The Automatic.css managed mapping should list the body font-weight field.');
    assertContainsValue('var(--font-heading-weight)', $output, 'The Automatic.css managed mapping should expose the heading weight variable target.');
    assertContainsValue('var(--font-body-weight)', $output, 'The Automatic.css managed mapping should expose the body weight variable target.');
    assertNotContainsValue('Inter, sans-serif', $output, 'The Automatic.css managed mapping should avoid showing old current values when the integration is synced.');
    assertNotContainsValue('System UI, sans-serif', $output, 'The Automatic.css managed mapping should avoid showing old current values when the integration is synced.');
    assertNotContainsValue('Sync heading/body roles to Automatic.css', $output, 'The Automatic.css integration should no longer render a second row title.');
    assertNotContainsValue('Managed Mapping', $output, 'The Automatic.css integration should now use the quieter Font mapping disclosure instead of a loud subsection heading.');
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
            'show_activity_log' => true,
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
        'Select Roles, Review, Publish.',
        'Turn Off Sitewide',
        'Save or Publish',
        'Preview and Snippets',
        'Choose Families and Fallbacks.',
        'Minify Generated CSS',
        'Preload Primary Fonts',
        'Remote Connection Hints',
        'Utility Classes',
        'Role Classes',
        'Keep Uploaded Fonts on Uninstall',
        'No Fonts Yet',
        'No Activity Yet',
        'Readable Preview',
        'Minified CSS',
        'A Type Pairing That Carries Real Editorial Weight at Every Reading Size.',
        'The Quiet Craft of Choosing Typefaces That Feel Inevitable.',
        'A Quiet Manifesto for Typography That Disappears Into the Reading.',
        'How to Ship a Body Face That Holds Up at Every Reading Size',
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
        'Keep uploaded fonts on uninstall',
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
                'description' => 'Sync imported families into the WordPress Font Library.',
                'status_label' => 'On',
                'enabled' => true,
            ],
            'acss_integration' => [
                'title' => 'Automatic.css',
                'description' => 'Enable this to sync Automatic.css font settings with Tasty role variables.',
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
                'description' => 'Manage Bricks typography with Tasty when Bricks is active.',
                'status_label' => 'Not Active',
                'enabled' => false,
                'available' => false,
            ],
            'oxygen_integration' => [
                'title' => 'Oxygen Builder',
                'description' => 'Show published Tasty fonts in Oxygen when Oxygen is active.',
                'status_label' => 'Not Active',
                'enabled' => false,
                'available' => false,
            ],
            'gutenberg_integration' => [
                'title' => 'Gutenberg Font Library',
                'description' => 'Sync imported families into the WordPress Font Library.',
                'status_label' => 'On',
                'enabled' => true,
            ],
            'acss_integration' => [
                'title' => 'Automatic.css',
                'description' => 'Sync Automatic.css font settings with Tasty role variables when Automatic.css is active.',
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
                'description' => 'Manage Bricks typography with Tasty. Selectors and previews stay automatic.',
                'status_label' => 'On',
                'enabled' => true,
                'available' => true,
                'feature_descriptions' => [
                    'selectors' => 'Show published Tasty families directly inside Bricks font controls.',
                    'builder_preview' => 'Load the active Tasty delivery in Bricks builder previews for local, CDN, and Adobe fonts.',
                    'theme_styles' => 'Sync Tasty role fonts to one Theme Style or all Theme Styles.',
                    'google_fonts' => 'Keep Bricks font pickers focused on Tasty-managed fonts.',
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
                'description' => 'Enable this to show published Tasty fonts in Oxygen and sync families to Gutenberg.',
                'status_label' => 'Off',
                'enabled' => false,
                'available' => true,
            ],
            'gutenberg_integration' => [
                'title' => 'Gutenberg Font Library',
                'description' => 'Sync imported families into the WordPress Font Library.',
                'status_label' => 'On',
                'enabled' => true,
            ],
            'acss_integration' => [
                'title' => 'Automatic.css',
                'description' => 'Enable this to sync Automatic.css font settings with Tasty role variables.',
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

    assertContainsValue('Manage Bricks typography with Tasty. Selectors and previews stay automatic.', $output, 'The Bricks integration card should combine the automatic selector and preview support into the main description.');
    assertNotContainsValue('tasty-fonts-bricks-support-copy', $output, 'The Bricks integration card should not render a separate baseline support paragraph.');
    assertNotContainsValue('name="bricks_selector_fonts_enabled"', $output, 'The Bricks integration card should no longer render a separate selector exposure toggle.');
    assertNotContainsValue('name="bricks_builder_preview_enabled"', $output, 'The Bricks integration card should no longer render a separate builder preview toggle.');
    assertContainsValue('name="bricks_theme_styles_sync_enabled"', $output, 'The Bricks integration card should render the Theme Style sync toggle.');
    assertContainsValue('name="bricks_theme_style_target_mode"', $output, 'The Bricks integration card should render the Theme Style target mode controls.');
    assertContainsValue('name="bricks_theme_style_target_id"', $output, 'The Bricks integration card should render the Theme Style target selector.');
    assertContainsValue('data-bricks-theme-style-target-mode', $output, 'The Bricks integration card should expose the Theme Style target mode radios for immediate client-side updates.');
    assertContainsValue('data-bricks-theme-style-target-select', $output, 'The Bricks integration card should expose the Theme Style target select for immediate client-side updates.');
    assertContainsValue('Delete Tasty Theme Style', $output, 'The Bricks integration card should render the delete action for the managed Tasty Theme Style.');
    assertContainsValue('name="bricks_disable_google_fonts_enabled"', $output, 'The Bricks integration card should render the Bricks Google font toggle.');
    assertContainsValue('Use Tasty Fonts in Bricks Pickers', $output, 'The Bricks integration card should frame the picker setting as a positive Tasty Fonts behavior.');
    assertNotContainsValue('Disable Bricks Google Fonts', $output, 'The Bricks integration card should not frame the picker setting as a disable action.');
    assertContainsValue('Reset Bricks Integration', $output, 'The Bricks integration card should render the reset action.');
    assertContainsValue('Theme Style', $output, 'The Bricks integration card should keep the Theme Style controls under a clearer heading.');
    assertContainsValue('Choose Theme Style', $output, 'The Bricks integration card should label the existing Theme Style selector clearly.');
    assertContainsValue('Current values', $output, 'The Bricks integration card should render the current Bricks state summary.');
    assertContainsValue('Target values', $output, 'The Bricks integration card should render the target Bricks mapping summary.');
    assertContainsValue('Font mapping', $output, 'The Bricks integration card should present the state comparison under a clearer details label.');
    assertContainsValue('Maintenance', $output, 'The Bricks integration card should group destructive actions into a quieter maintenance area.');
    assertNotContainsValue('Bricks Controls', $output, 'The Bricks integration card should remove the redundant internal Bricks heading.');
    assertContainsValue('data-help-tooltip="Bricks Theme Style sync is active."', $output, 'The Bricks Theme Style status badge should explain the synced state on hover.');
    assertContainsValue('data-help-tooltip="Bricks pickers are focused on Tasty Fonts."', $output, 'The Bricks picker status badge should explain the synced state on hover.');
    assertContainsValue('Updating &quot;Sitewide Primary&quot;.', $output, 'The Bricks integration card should explain which existing Theme Style Tasty is updating.');
    assertContainsValue('Managed Tasty style', $output, 'The Bricks integration card should render the managed Theme Style radio label.');
    assertContainsValue('One existing style', $output, 'The Bricks integration card should render the single-style target radio label.');
    assertContainsValue('All styles', $output, 'The Bricks integration card should render the all-styles target radio label.');
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
                'description' => 'Manage Bricks typography with Tasty. Selectors and previews stay automatic.',
                'status_label' => 'On',
                'enabled' => true,
                'available' => true,
                'feature_descriptions' => [
                    'selectors' => 'Show published Tasty families directly inside Bricks font controls.',
                    'builder_preview' => 'Load the active Tasty delivery in Bricks builder previews for local, CDN, and Adobe fonts.',
                    'theme_styles' => 'Update only the font-family and font-weight fields on one selected Bricks Theme Style.',
                    'google_fonts' => 'Keep Bricks pickers focused on Tasty-supplied fonts.',
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
                'description' => 'Enable this to show published Tasty fonts in Oxygen and sync families to Gutenberg.',
                'status_label' => 'Off',
                'enabled' => false,
                'available' => true,
            ],
            'gutenberg_integration' => [
                'title' => 'Gutenberg Font Library',
                'description' => 'Sync imported families into the WordPress Font Library.',
                'status_label' => 'On',
                'enabled' => true,
            ],
            'acss_integration' => [
                'title' => 'Automatic.css',
                'description' => 'Enable this to sync Automatic.css font settings with Tasty role variables.',
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

    assertContainsValue('data-help-tooltip="Turn on sitewide roles to apply Theme Style sync."', $output, 'The Bricks waiting badge should explain that sitewide role delivery must be enabled first.');
    assertSameValue(1, preg_match('/data-help-tooltip="Turn on sitewide roles to apply Theme Style sync\\."[\s\S]*?>\s*Waiting\s*<\/span>/', $output), 'The Bricks Theme Style badge should still render the Waiting label.');
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
                'description' => 'Manage Bricks typography with Tasty. Selectors and previews stay automatic.',
                'status_label' => 'On',
                'enabled' => true,
                'available' => true,
                'feature_descriptions' => [
                    'selectors' => 'Show published Tasty families directly inside Bricks font controls.',
                    'builder_preview' => 'Load the active Tasty delivery in Bricks builder previews for local, CDN, and Adobe fonts.',
                    'theme_styles' => 'Update only the font-family and font-weight fields on one selected Bricks Theme Style.',
                    'google_fonts' => 'Keep Bricks pickers focused on Tasty-supplied fonts.',
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
                'description' => 'Enable this to show published Tasty fonts in Oxygen and sync families to Gutenberg.',
                'status_label' => 'Off',
                'enabled' => false,
                'available' => true,
            ],
            'gutenberg_integration' => [
                'title' => 'Gutenberg Font Library',
                'description' => 'Sync imported families into the WordPress Font Library.',
                'status_label' => 'On',
                'enabled' => true,
            ],
            'acss_integration' => [
                'title' => 'Automatic.css',
                'description' => 'Enable this to sync Automatic.css font settings with Tasty role variables.',
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

    assertContainsValue('Create the managed style to start syncing.', $output, 'The Bricks integration card should explain that Tasty can create the managed Theme Style when none exist.');
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
    $deploymentPosition = strpos($output, 'Workflow');
    $selectionPosition = strpos($output, 'Choose Families and Fallbacks.');
    $sectionStatusPosition = strpos($output, 'Sitewide on');
    $headingVariablePosition = strpos($output, 'data-role-variable-copy="heading"');
    $bodyVariablePosition = strpos($output, 'data-role-variable-copy="body"');

    assertContainsValue('Turn On Sitewide', $output, 'The Font Roles form should expose an explicit apply sitewide action.');
    assertContainsValue('Turn Off Sitewide', $output, 'The Font Roles form should expose an explicit switch-off sitewide action.');
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
    assertContainsValue('Roles', $output, 'The Font Roles form should expose a dedicated role selection section after the deployment controls.');
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
    assertContainsValue('No role changes to publish.', $matchedOutput, 'The disabled Publish Roles action should explain why it is unavailable.');
    assertContainsValue('data-role-save-draft aria-disabled="true" disabled', $matchedOutput, 'Save Draft should start disabled until the draft changes.');
    assertContainsValue('No draft changes to save.', $matchedOutput, 'The disabled Save Draft action should explain why it is unavailable.');
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
            'show_activity_log' => true,
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

    $deploymentPosition = strpos($output, 'Workflow');
    $selectionPosition = strpos($output, 'Choose Families and Fallbacks.');
    $libraryPosition = strpos($output, 'id="tasty-fonts-library"');
    $activityPosition = strpos($output, 'id="tasty-fonts-diagnostics-panel-activity"');

    assertSameValue(true, $deploymentPosition !== false && $selectionPosition !== false && $deploymentPosition < $selectionPosition, 'Deployment controls should render before role selection.');
    assertSameValue(true, $selectionPosition !== false && $libraryPosition !== false && $selectionPosition < $libraryPosition, 'Role selection should render before the library section.');
    assertSameValue(true, $libraryPosition !== false && $activityPosition !== false && $libraryPosition < $activityPosition, 'The library should render before the Advanced Tools activity tab.');
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
            'show_activity_log' => true,
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

    $deploymentPosition = strpos($output, 'Workflow');
    $previewPanelPosition = strpos($output, 'id="tasty-fonts-role-preview-panel"');
    $snippetsPanelPosition = strpos($output, 'id="tasty-fonts-role-snippets-panel"');
    $settingsTabPosition = strpos($output, 'id="tasty-fonts-page-tab-settings"');
    $diagnosticsTabPosition = strpos($output, 'id="tasty-fonts-page-tab-diagnostics"');
    $roleSelectionPosition = strpos($output, 'Choose Families and Fallbacks.');
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
    $runtimeStatePosition = strpos($output, 'Publish State');
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

$tests['admin_page_renderer_generated_css_defaults_to_readable_output_with_minified_toggle'] = static function (): void {
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
    assertContainsValue('data-label-default="Readable Preview"', $output, 'Generated CSS should preserve the readable-preview label for returning from the minified view.');
    assertContainsValue('data-label-active="Minified CSS"', $output, 'Generated CSS should offer a minified view from the default readable output.');
    assertContainsValue('aria-pressed="true"', $output, 'Generated CSS should load with the readable view active.');
    assertSameValue(1, preg_match('/<button[^>]*data-snippet-display-toggle[\s\S]*?>\s*Minified CSS\s*<\/button>/', $output), 'Generated CSS should show the minified-toggle action first.');
    assertContainsValue('<pre class="tasty-fonts-output" data-snippet-view="raw" aria-labelledby="tasty-fonts-output-generated-label" hidden>', $output, 'Generated CSS should keep the actual minified output hidden until toggled on.');
    assertContainsValue('<code id="tasty-fonts-output-generated" class="tasty-fonts-output-code">', $output, 'Generated CSS should keep the stable code block ID for the raw output view.');
    assertContainsValue('<pre class="tasty-fonts-output" data-snippet-view="readable" aria-labelledby="tasty-fonts-output-generated-label"><code id="tasty-fonts-output-generated-readable"', $output, 'Generated CSS should render the readable view as the default visible code block.');
    assertContainsValue('tasty-fonts-syntax-selector', $output, 'Generated CSS readable output should use the shared snippet syntax highlighting.');
    assertContainsValue('tasty-fonts-syntax-property', $output, 'Generated CSS readable output should highlight CSS properties.');
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
            'show_activity_log' => true,
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

    assertContainsValue('class="tasty-fonts-health-row tasty-fonts-health-row--reference"', $output, 'Copyable diagnostic values should render inside the shared reference-row pattern.');
    assertContainsValue('class="button tasty-fonts-output-copy-button tasty-fonts-diagnostic-copy-button"', $output, 'Copyable diagnostic values should render an icon-only copy button in the reference row.');
    assertContainsValue('data-copy-text="https://example.test/wp-content/uploads/fonts/tasty-fonts.css"', $output, 'Copyable diagnostic values should expose their full value for the shared clipboard handler.');
    assertContainsValue('data-copy-success="CSS Request URL copied."', $output, 'Copyable diagnostic values should report which field was copied in the shared toast message.');
    assertContainsValue('<span class="tasty-fonts-code">', $output, 'Diagnostic values should remain plain readable text instead of turning the whole field into a button.');
    assertNotContainsValue('data-copy-text="4 KB"', $output, 'Plain diagnostic values should not become copy controls.');
};

$tests['admin_page_renderer_renders_advanced_tools_command_center_tabs'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());

    ob_start();
    try {
        $renderer->renderPage([
            'storage' => ['root' => '/tmp/uploads/fonts'],
            'catalog' => [],
            'available_families' => [],
            'roles' => [],
            'logs' => [
                ['time' => '2026-04-07 09:20:40', 'actor' => 'root', 'message' => 'Generated CSS regenerated.'],
            ],
            'activity_actor_options' => ['root'],
            'family_fallbacks' => [],
            'family_font_displays' => [],
            'family_font_display_options' => [],
            'preview_text' => 'The quick brown fox jumps over the lazy dog. 1234567890',
            'preview_size' => 32,
            'font_display' => 'swap',
            'font_display_options' => [],
            'minify_css_output' => true,
            'preload_primary_fonts' => true,
            'remote_connection_hints' => true,
            'block_editor_font_library_sync_enabled' => false,
            'training_wheels_off' => false,
            'show_activity_log' => true,
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
            'overview_metrics' => [
                ['label' => 'Families', 'value' => '2'],
            ],
            'developer_tool_statuses' => [
                'wipe_managed_font_library' => [
                    'summary' => '2 managed font families in the library.',
                    'last_run' => '',
                ],
                'delete_all_snapshots' => [
                    'summary' => '1 rollback snapshot retained.',
                    'last_run' => '',
                ],
                'delete_all_exports' => [
                    'summary' => '1 retained export bundle. 1 locked.',
                    'last_run' => '',
                ],
            ],
            'site_transfer' => [
                'available' => true,
                'message' => '',
                'export_url' => 'https://example.test/wp-admin/admin.php?action=tasty_fonts_download_site_transfer_bundle',
                'export_rename_action_field' => 'tasty_fonts_rename_site_transfer_export_bundle',
                'export_protect_action_field' => 'tasty_fonts_protect_site_transfer_export_bundle',
                'export_delete_action_field' => 'tasty_fonts_delete_site_transfer_export_bundle',
                'export_delete_all_action_field' => 'tasty_fonts_delete_all_site_transfer_export_bundles',
                'export_delete_all_blocked' => true,
                'export_delete_all_blocked_message' => 'One or more export bundles are locked. Unprotect all export bundles before deleting all exports.',
                'protected_export_count' => 1,
                'export_bundle_count' => 1,
                'export_retention_limit' => 5,
                'export_retention_min' => 1,
                'export_retention_max' => 10,
                'export_bundles' => [
                    [
                        'id' => 'export-20260424-190219-a1b2c3d4',
                        'created_at' => '2026-04-24 19:02:19',
                        'plugin_version' => '1.14.0-beta.1',
                        'families' => 2,
                        'files' => 14,
                        'size' => 53248,
                        'label' => 'Client handoff',
                        'filename' => 'tasty-fonts-transfer-20260424-190219.zip',
                        'protected' => true,
                        'family_names' => ['Inter', 'JetBrains Mono'],
                        'role_families' => ['Inter'],
                        'download_url' => 'https://example.test/wp-admin/admin.php?action=tasty_fonts_download_site_transfer_export_bundle',
                    ],
                ],
                'import_file_field' => 'tasty_fonts_site_transfer_bundle',
                'import_stage_token_field' => 'tasty_fonts_site_transfer_stage_token',
                'import_google_api_key_field' => 'tasty_fonts_import_google_api_key',
                'import_action_field' => 'tasty_fonts_import_site_transfer_bundle',
                'effective_upload_limit_label' => '32 MB',
                'snapshot_action_field' => 'tasty_fonts_create_rollback_snapshot',
                'snapshot_restore_action_field' => 'tasty_fonts_restore_rollback_snapshot',
                'snapshot_rename_action_field' => 'tasty_fonts_rename_rollback_snapshot',
                'snapshot_delete_action_field' => 'tasty_fonts_delete_rollback_snapshot',
                'snapshot_delete_all_action_field' => 'tasty_fonts_delete_all_rollback_snapshots',
                'snapshot_count' => 1,
                'snapshot_retention_limit' => 4,
                'snapshot_retention_min' => 1,
                'snapshot_retention_max' => 10,
                'snapshots' => [
                    [
                        'id' => 'snapshot-20260424-170456-a1b2c3d4',
                        'created_at' => '2026-04-24 17:04:56',
                        'reason' => 'manual',
                        'plugin_version' => '1.14.0-beta.1',
                        'families' => 2,
                        'files' => 6,
						'font_files' => 2,
						'storage_files' => 6,
                        'size' => 4096,
                        'label' => 'Before client review',
                        'family_names' => ['Inter', 'Roboto Slab'],
                        'role_families' => ['Inter'],
                    ],
                ],
                'logs' => [],
                'actor_options' => [],
            ],
            'output_panels' => [],
            'generated_css_panel' => [
                'key' => 'generated',
                'label' => 'Generated CSS',
                'target' => 'tasty-fonts-output-generated',
                'value' => ':root{--font-body:sans-serif}',
                'download_url' => 'https://example.test/wp-admin/admin.php?action=tasty_fonts_download_generated_css',
            ],
            'advanced_tools' => [
                'health_checks' => [
                    [
                        'slug' => 'generated_css',
                        'title' => 'Generated CSS',
                        'severity' => 'ok',
                        'message' => 'The front-end stylesheet exists and can be served from the selected delivery mode.',
                        'guidance' => 'Regenerate CSS after changing roles, delivery profiles, or output settings so the runtime file stays aligned with saved settings.',
                        'help_url' => 'https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki/Advanced-Tools',
                        'help_label' => 'Open knowledge base',
                    ],
                    [
                        'slug' => 'block_editor_sync',
                        'title' => 'Block Editor Sync',
                        'severity' => 'warning',
                        'message' => 'Block Editor Font Library sync is on while this site looks local or self-signed.',
                        'guidance' => 'On local HTTPS sites, WordPress may reject REST font uploads if the certificate is not trusted. Disable sync locally or trust the certificate.',
                        'evidence' => [
                            ['label' => 'Sync', 'value' => 'On'],
                            ['label' => 'Environment', 'value' => 'Local'],
                        ],
                        'help_url' => 'https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki/Settings',
                        'help_label' => 'Open knowledge base',
                    ],
                    [
                        'slug' => 'font_preload',
                        'title' => 'Primary Font Preload',
                        'severity' => 'info',
                        'message' => 'Preloading is on, but no active same-origin WOFF2 font can be safely preloaded.',
                        'guidance' => 'Preload only applies to local WOFF2 files used by published sitewide roles. Remote CSS, inactive families, and non-WOFF2 files are skipped on purpose.',
                        'help_url' => 'https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki/Settings',
                        'help_label' => 'Open knowledge base',
                    ],
                ],
                'runtime_manifest' => [
                    'families' => [],
                    'preload_urls' => [],
                    'preconnect_origins' => [],
                    'external_stylesheets' => [],
                ],
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

	    assertContainsValue('id="tasty-fonts-diagnostics-tab-overview"', $output, 'Advanced Tools should render the command center overview tab.');
	    assertNotContainsValue('id="tasty-fonts-diagnostics-tab-system"', $output, 'Advanced Tools should fold system details into Overview instead of rendering a redundant tab.');
	    assertContainsValue('id="tasty-fonts-diagnostics-tab-maintenance"', $output, 'Advanced Tools should expose maintenance as a first-class tab.');
    assertContainsValue('id="tasty-fonts-diagnostics-tab-cli"', $output, 'Advanced Tools should expose CLI as a first-class tab.');
    assertContainsValue('tasty-fonts-cli-help-trigger', $output, 'The CLI tab should expose contextual help triggers for command rows.');
    assertContainsValue('data-help-tooltip="Runs Advanced Tools health checks and prints a readable pass, warning, and critical summary."', $output, 'The CLI doctor command should explain what it does.');
    assertContainsValue('aria-label="Explain Doctor summary"', $output, 'The CLI command help trigger should be labelled by command.');
	assertContainsValue('data-help-tooltip="Imports a validated transfer ZIP, prompts for an optional destination Google key, creates a rollback snapshot first, and replaces current Tasty Fonts data."', $output, 'Riskier CLI commands should include specific help copy.');
	    assertContainsValue('id="tasty-fonts-diagnostics-tab-transfer"', $output, 'Advanced Tools should expose transfer as a first-class tab.');
    assertContainsValue('data-tab-heading-group="diagnostics"', $output, 'Advanced Tools should render contextual tab headings in the shared card header.');
    assertContainsValue('data-tab-heading="transfer"', $output, 'Advanced Tools should include the transfer contextual heading.');
    assertNotContainsValue('Command center for runtime inspection, maintenance, transfer, and activity review.', $output, 'Advanced Tools should avoid repeating the masthead copy inside the diagnostics card.');
    assertNotContainsValue('Next Step', $output, 'Overview should not render a redundant next-step row above health.');
    assertNotContainsValue('Fix Block Editor Sync', $output, 'Overview should leave health work inside the health checklist.');
    assertNotContainsValue('attention /', $output, 'Overview should not duplicate health counts in a separate next-step header.');
    assertNotContainsValue('Evidence', $output, 'Overview should not repeat low-level evidence in the health checklist.');
    assertNotContainsValue('Sync: On / Environment: Local', $output, 'Overview should not duplicate facts that are already stated in the health message.');
    assertContainsValue('Review Sync Settings', $output, 'Health rows should send Block Editor Sync warnings to the settings panel that controls them.');
    assertNotContainsValue('Review Health', $output, 'Overview should not render a vague anchor button for the health section.');
    assertNotContainsValue('tasty-fonts-overview-suggested-action', $output, 'Overview should not render a separate suggested action outside the health rows.');
    assertContainsValue('tasty-fonts-health-row-primary-action', $output, 'Needs-attention and advisory health rows should include a direct action button.');
    assertContainsValue('tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--navigate tasty-fonts-health-row-primary-action', $output, 'Overview health row actions should use the shared Advanced Tools row action contract.');
    assertContainsValue('tf_studio=integrations', $output, 'Block Editor Sync health rows should link directly to Integration settings.');
    assertContainsValue('Review Output Settings', $output, 'Preload advisories should expose a direct action to the Output Settings panel.');
    assertContainsValue('tf_studio=output-settings', $output, 'Preload advisory actions should link directly to Output Settings.');
    assertNotContainsValue('class="tasty-fonts-health-board tasty-fonts-overview-next-step-board"', $output, 'Overview should not render a separate recommendation board before health.');
    assertContainsValue('class="tasty-fonts-health-row tasty-fonts-health-row--warning"', $output, 'Overview should tone health rows from their health state.');
    assertContainsValue('class="tasty-fonts-health-board"', $output, 'Overview should present health checks in one consistent board.');
    assertContainsValue('class="tasty-fonts-health-row tasty-fonts-health-row--info"', $output, 'Overview should render advisory checks with the same row pattern as other health checks.');
    assertContainsValue('class="tasty-fonts-health-group tasty-fonts-health-group--ok"', $output, 'Overview should group checks that are working fine without calling them verified.');
    assertContainsValue('class="tasty-fonts-health-row tasty-fonts-health-row--ok"', $output, 'Overview should render okay checks with the same row pattern as other health checks.');
    assertContainsValue('Working Fine', $output, 'Overview should label passing checks as working fine instead of verified.');
    assertContainsValue('OKAY', $output, 'Overview should label passing check pills as okay.');
    assertNotContainsValue('Verified', $output, 'Overview should not call passing health checks verified.');
    assertNotContainsValue('Passing', $output, 'Overview should not describe okay health checks as passing.');
    assertContainsValue('The front-end stylesheet exists and can be served from the selected delivery mode.', $output, 'Passing checks should include a short explanation of what passed.');
    assertContainsValue('Regenerate CSS after changing roles, delivery profiles, or output settings so the runtime file stays aligned with saved settings.', $output, 'Passing checks should include practical guidance, not just a status label.');
    assertContainsValue('href="https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki/Advanced-Tools"', $output, 'Health checks should expose a knowledge-base link.');
    assertContainsValue('On local HTTPS sites, WordPress may reject REST font uploads if the certificate is not trusted.', $output, 'Warnings should explain the risk in plain language.');
    assertContainsValue('Preload only applies to local WOFF2 files used by published sitewide roles.', $output, 'Advisories should explain why the result appears.');
    assertNotContainsValue('tasty-fonts-overview-pass-strip', $output, 'Overview should not render the old unexplained passing-pill strip.');
    assertContainsValue('Runtime Details', $output, 'Overview should group runtime output under a clear debugging detail section.');
    assertContainsValue('Fonts in Frontend CSS', $output, 'Overview should label runtime facts as frontend CSS output details.');
    assertContainsValue('class="tasty-fonts-health-board tasty-fonts-overview-reference-board"', $output, 'Runtime details should use the same board pattern as health.');
    assertContainsValue('No families in frontend CSS yet.', $output, 'Runtime details should explain when no managed families are being served by CSS.');
    assertContainsValue('class="tasty-fonts-health-row tasty-fonts-health-row--info"', $output, 'Runtime idle should render as an actionable non-passing state.');
    assertContainsValue('tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--navigate tasty-fonts-runtime-empty-action', $output, 'Runtime idle should link to the place where sitewide roles are published.');
    assertSameValue(1, preg_match('/tasty-fonts-runtime-empty-action[\s\S]*?>\s*Deploy Fonts\s*<\/a>/', $output), 'Runtime idle should expose a clear Deploy Fonts action.');
    assertContainsValue('class="tasty-fonts-health-row tasty-fonts-health-row--reference"', $output, 'Runtime reference details should keep neutral reference rows.');
    assertContainsValue('Copyable Paths', $output, 'Overview should keep exact debug paths without repeating status details.');
    assertContainsValue('CSS Request URL', $output, 'Overview should include copyable runtime URLs for debugging.');
    assertNotContainsValue('System Details', $output, 'Overview should avoid the old redundant system-details block.');
    assertNotContainsValue('Runtime Inspector', $output, 'Overview should avoid the old redundant runtime-inspector block.');
    assertNotContainsValue('Stylesheet Size', $output, 'Overview should leave non-copyable status facts out of Copyable Paths.');
    assertNotContainsValue('class="tasty-fonts-generated-css-toolbar"', $output, 'Generated CSS should use the shared contextual header instead of a detached panel title.');
    assertSameValue(1, preg_match('/id="tasty-fonts-diagnostics-panel-generated"[\s\S]*?Download Generated CSS/', $output), 'The generated CSS download action should live inside the generated CSS tab panel.');
    assertContainsValue('class="button tasty-fonts-output-download-button"', $output, 'Generated CSS should render the download action as an icon-only code panel control.');
    assertNotContainsValue('class="button button-secondary" href="https://example.test/wp-admin/admin.php?action=tasty_fonts_download_generated_css"', $output, 'Generated CSS should not render a detached toolbar download button.');
    assertContainsValue('Clear Caches &amp; Rebuild', $output, 'The maintenance tab should render existing guarded cache rebuild action.');
    assertContainsValue('Wipe the managed font library, remove managed files, and rebuild empty storage?', $output, 'The maintenance tab should keep destructive confirmation copy for library wipes.');
    assertContainsValue('Delete all snapshots', $output, 'The danger zone should expose a bulk snapshot deletion action.');
    assertContainsValue('Delete all exports', $output, 'The danger zone should expose a guarded bulk export deletion action.');
    assertSameValue(1, preg_match('/name="tasty_fonts_delete_all_rollback_snapshots"[\s\S]*?value="1"/', $output), 'Bulk snapshot deletion should submit through its dedicated admin action field.');
    assertSameValue(1, preg_match('/name="tasty_fonts_delete_all_site_transfer_export_bundles"[\s\S]*?value="1"/', $output), 'Bulk export deletion should submit through its dedicated admin action field.');
    assertContainsValue('Delete all rollback snapshots permanently?', $output, 'Bulk snapshot deletion should render destructive confirmation copy.');
    assertContainsValue('Delete all site transfer export bundles permanently?', $output, 'Bulk export deletion should render destructive confirmation copy.');
    assertContainsValue('data-delete-blocked="One or more export bundles are locked. Unprotect all export bundles before deleting all exports."', $output, 'Bulk export deletion should expose a blocked-click reason while exports are locked.');
    assertContainsValue('aria-disabled="true"', $output, 'Bulk export deletion should communicate unavailable state while exports are locked.');
    assertContainsValue('data-help-tooltip="One or more export bundles are locked. Unprotect all export bundles before deleting all exports."', $output, 'Bulk export deletion should expose a hover help reason while exports are locked.');
    assertContainsValue('1 retained export bundle. 1 locked', $output, 'The danger-zone export summary should indicate locked retained exports.');
    assertNotContainsValue('data-developer-confirm-input="WIPE LIBRARY"', $output, 'The maintenance tab should not render the typed confirmation unlock panel.');
    assertContainsValue('Transfer &amp; Recovery', $output, 'The transfer tab should render the redesigned transfer and recovery workflow.');
    assertContainsValue('Export Bundle', $output, 'The transfer tab should render the existing export workflow.');
    assertContainsValue('Client handoff', $output, 'The transfer tab should render retained export bundle names.');
    assertContainsValue('tasty-fonts-export-bundle-list', $output, 'The transfer tab should render saved export bundles in the shared compact history pattern.');
    assertContainsValue('Families: Inter, JetBrains Mono', $output, 'Saved export bundles should expose captured font families on hover.');
    assertContainsValue('Live roles: Inter', $output, 'Saved export bundles should expose captured live role families on hover.');
    assertContainsValue('Bundle size:', $output, 'Saved export bundles should label the bundle ZIP size in compact metadata and hover details.');
    assertContainsValue('name="site_transfer_export_retention_limit"', $output, 'Saved export bundles should expose a retention setting.');
    assertContainsValue('bundles', $output, 'Saved export bundle retention should use bundle-specific copy.');
    assertContainsValue('name="tasty_fonts_rename_site_transfer_export_bundle" value="1"', $output, 'Saved export bundles should expose a rename action.');
    assertContainsValue('name="tasty_fonts_protect_site_transfer_export_bundle" value="1"', $output, 'Saved export bundles should expose a protect toggle action.');
    assertContainsValue('name="tasty_fonts_delete_site_transfer_export_bundle" value="1"', $output, 'Saved export bundles should expose a delete action.');
    assertContainsValue('Unprotect before deleting', $output, 'Protected export bundles should make deletion unavailable until unprotected.');
    assertContainsValue('data-site-transfer-import-submit', $output, 'The transfer tab should render the local import action for validated bundles.');
    assertContainsValue('data-site-transfer-form', $output, 'The transfer tab should keep the JS contract for dry-run imports.');
    assertNotContainsValue('tasty-fonts-site-transfer-headline', $output, 'Transfer should use the shared contextual header instead of a separate panel headline.');
    assertContainsValue('id="tasty-fonts-diagnostics-panel-activity"', $output, 'The activity log should now live inside the Advanced Tools command center.');
};

$tests['admin_page_renderer_renders_transfer_activity_hidden_state_when_activity_log_is_hidden'] = static function (): void {
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
            'font_display' => 'swap',
            'font_display_options' => [],
            'minify_css_output' => true,
            'preload_primary_fonts' => true,
            'remote_connection_hints' => true,
            'block_editor_font_library_sync_enabled' => false,
            'training_wheels_off' => false,
            'show_activity_log' => false,
            'diagnostic_items' => [],
            'overview_metrics' => [],
            'site_transfer' => [
                'available' => true,
                'message' => '',
                'logs' => [
                    [
                        'time' => '2026-04-27 21:00:00',
                        'actor' => 'admin',
                        'message' => 'Hidden transfer event should stay hidden.',
                        'category' => 'transfer',
                    ],
                ],
                'actor_options' => ['admin'],
            ],
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

    assertSameValue(2, substr_count($output, 'tasty-fonts-health-board tasty-fonts-advanced-activity-board'), 'Transfer and Activity tabs should use the same activity-board surface class.');
    assertNotContainsValue('tasty-fonts-site-transfer-log-card', $output, 'The Transfer tab should not use separate log-card CSS when mirroring the Activity tab.');
    assertContainsValue('Activity Log Hidden', $output, 'Hidden activity panels should explain that the activity log is hidden.');
    assertContainsValue('Enable Show Activity Log in Settings -&gt; Behavior to review the full event timeline here.', $output, 'The Transfer tab hidden state should use the same hidden-state copy as the Activity tab.');
    assertNotContainsValue('Hidden transfer event should stay hidden.', $output, 'Transfer-specific activity entries should stay hidden with the activity log preference off.');
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
            'show_activity_log' => true,
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
    $deploymentPosition = strpos($output, 'Workflow');
    $roleSelectionPosition = strpos($output, 'Choose Families and Fallbacks.');
    $activityPosition = strpos($output, 'No Activity Yet');

    assertContainsValue('Local environment detected', $output, 'The admin page should surface a dedicated notice for local environments.');
    assertContainsValue('Open Integrations', $output, 'The local-environment notice should include a direct action to open the Integrations panel.');
    assertContainsValue('aria-label="Open Integrations"', $output, 'The local-environment notice integration action should keep an accessible label while rendering icon-only.');
	assertContainsValue('data-help-tooltip="Open Integrations"', $output, 'The local-environment notice integration action should show the shared custom tooltip on hover and focus.');
	assertContainsValue('data-help-tooltip="Remind Tomorrow"', $output, 'The local-environment notice reminder actions should show shared custom tooltips on hover and focus.');
	assertContainsValue('data-help-tooltip="Remind in 1 Week"', $output, 'The local-environment notice one-week action should show the shared custom tooltip on hover and focus.');
	assertContainsValue('data-help-tooltip="Never Show Again"', $output, 'The local-environment notice dismiss action should show the shared custom tooltip on hover and focus.');
    assertContainsValue('tasty-fonts-page-notice-icon-action', $output, 'The local-environment notice actions should use the icon-only action styling hook.');
    assertContainsValue('dashicons-admin-plugins', $output, 'The local-environment notice integration action should show an integrations icon.');
    assertContainsValue('Remind Tomorrow', $output, 'The local-environment notice should allow users to snooze the reminder until tomorrow.');
    assertContainsValue('dashicons-clock', $output, 'The local-environment notice tomorrow action should show a clock icon.');
    assertContainsValue('Remind in 1 Week', $output, 'The local-environment notice should allow users to snooze the reminder for one week.');
    assertContainsValue('dashicons-calendar-alt', $output, 'The local-environment notice one-week action should show a calendar icon.');
    assertContainsValue('Never Show Again', $output, 'The local-environment notice should allow users to hide the reminder permanently for their account.');
    assertContainsValue('dashicons-hidden', $output, 'The local-environment notice dismiss action should show a hide icon.');
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
            'show_activity_log' => true,
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
	assertContainsValue('tasty-fonts-log-item--compact', $output, 'Activity rows should render in the compact expandable row layout.');
	assertContainsValue('data-activity-detail-toggle', $output, 'Activity rows should expose a details disclosure control.');
	assertContainsValue('tasty-fonts-log-toggle-icon', $output, 'Activity detail controls should render as caret-only disclosure buttons.');
	assertContainsValue('data-activity-detail-show-label', $output, 'Activity detail controls should expose a dynamic show label for the accordion trigger.');
	assertContainsValue('aria-expanded="false"', $output, 'Activity detail controls should start collapsed.');
	assertContainsValue('data-activity-detail', $output, 'Activity rows should render a detail panel for audit and troubleshooting context.');
	assertContainsValue('tasty-fonts-log-details-inner', $output, 'Activity detail panels should include an inner wrapper for accordion animation.');
	assertContainsValue('data-activity-outcome="info"', $output, 'Legacy activity rows should fall back to an informational outcome.');
	assertContainsValue('tasty-fonts-log-chip--status', $output, 'Activity rows should render an outcome chip.');
	assertContainsValue('data-activity-count', $output, 'The Activity tab should expose a visible count hook.');
    assertContainsValue('data-activity-page-size="5"', $output, 'Activity logs should expose the shared pagination page-size hook.');
    assertContainsValue('data-activity-page-size-select', $output, 'Activity logs should let admins choose how many entries appear per page.');
    assertContainsValue('<option value="100"', $output, 'Activity log page-size options should include the largest review batch.');
    assertContainsValue('data-activity-pagination', $output, 'Activity logs should render pagination controls instead of relying on an internal scrollbar.');
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

    assertContainsValue('>Monospace<', $output, 'Enabled monospace support should render the third role box in the main Font Roles form.');
    assertContainsValue('tasty-fonts-role-grid is-three-columns', $output, 'Enabled monospace support should switch the role grid into the three-column layout modifier.');
    assertContainsValue('Use Fallback Only', $output, 'Enabled monospace support should render the fallback-only monospace family option.');
    assertContainsValue('var(--font-monospace)', $output, 'Enabled monospace support should expose the monospace role variable in the role UI.');
};

$tests['admin_page_renderer_keeps_hosted_import_and_upload_builder_ids_after_dedupe'] = static function (): void {
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
            'variable_fonts_enabled' => true,
            'delete_uploaded_files_on_uninstall' => false,
            'diagnostic_items' => [],
            'overview_metrics' => [],
            'output_panels' => [],
            'generated_css_panel' => [],
            'preview_panels' => [['key' => 'editorial', 'label' => 'Specimen', 'active' => true]],
            'toasts' => [],
            'apply_everywhere' => false,
            'role_deployment' => [],
            'google_catalog_link' => 'https://fonts.google.com/',
            'bunny_catalog_link' => 'https://fonts.bunny.net/',
        ]);
    } catch (\Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $output = (string) ob_get_clean();

    assertContainsValue('id="tasty-fonts-google-search"', $output, 'The Google hosted import panel should keep its search field id after the renderer dedupe.');
    assertContainsValue('id="tasty-fonts-bunny-search"', $output, 'The Bunny hosted import panel should keep its search field id after the renderer dedupe.');
    assertContainsValue('id="tasty-fonts-upload-group-template"', $output, 'The upload builder should keep its group template id after the shared markup extraction.');
    assertContainsValue('id="tasty-fonts-upload-row-template"', $output, 'The upload builder should keep its row template id after the shared markup extraction.');
    assertContainsValue('data-role-weight-editor="heading"', $output, 'The heading role card should still render its weight editor target after the studio role-card dedupe.');
    assertContainsValue('data-role-weight-editor="body"', $output, 'The body role card should still render its weight editor target after the studio role-card dedupe.');
    assertContainsValue('data-role-weight-editor="monospace"', $output, 'The monospace role card should still render its weight editor target after the studio role-card dedupe.');
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
    assertContainsValue('name="tasty_fonts_heading_fallback"', $output, 'The role form should render the heading fallback combobox for fallback-only roles.');
    assertContainsValue('name="tasty_fonts_body_fallback"', $output, 'The role form should render the body fallback combobox for fallback-only roles.');
    assertContainsValue('data-clear-target="tasty_fonts_heading_font"', $output, 'The heading family selector should render a clear button.');
    assertContainsValue('data-clear-target="tasty_fonts_heading_fallback"', $output, 'The heading fallback combobox should render a clear button.');
    assertContainsValue('data-role-weight-editor="heading"', $output, 'The role form should render the heading weight editor shell.');
    assertContainsValue('data-role-weight-editor="body"', $output, 'The role form should render the body weight editor shell.');
    assertNotContainsValue('data-role-delivery-select="heading"', $output, 'The role form should not render a heading delivery selector once delivery is locked to the library.');
    assertNotContainsValue('data-role-delivery-select="body"', $output, 'The role form should not render a body delivery selector once delivery is locked to the library.');
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
    assertContainsValue('Use draft selections', $output, 'The live baseline preview should offer a quick way to compare against the current draft roles.');
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
                ['key' => 'marketing', 'label' => 'Marketing', 'active' => false],
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
    assertContainsValue('data-tab-target="marketing"', $output, 'The preview tabs should include the new Marketing tab.');
    assertContainsValue('data-tab-target="code"', $output, 'The preview tabs should include the new Code tab.');
    assertSameValue(1, preg_match('/data-tab-target="interface"[\s\S]*data-tab-target="marketing"[\s\S]*data-tab-target="code"/', $output), 'The Marketing tab should render after Interface and before Code.');
    assertContainsValue('tasty-fonts-preview-scene--marketing', $output, 'The preview renderer should expose a dedicated Marketing scene.');
    assertContainsValue('CTA section', $output, 'The Marketing scene should render a CTA snippet sample.');
    assertContainsValue('Email signup', $output, 'The Marketing scene should render an email signup sample.');
    assertContainsValue('Buy now', $output, 'The Marketing scene should render a buy-now sample.');
    assertContainsValue('Follow social', $output, 'The Marketing scene should render a social follow sample.');
    assertContainsValue('TastyWP Studio', $output, 'The Marketing social sample should use the TastyWP placeholder brand.');
    assertContainsValue('@TastyWP', $output, 'The Marketing social sample should use the TastyWP social handle.');
    assertContainsValue('Sathyvelu Kunashegaran', $output, 'Preview samples should use the requested sample author name.');
    assertNotContainsValue('Casey Sato', $output, 'Preview samples should not use the old Casey sample author.');
    assertNotContainsValue('Elena Marsh', $output, 'Preview samples should not use the old Elena sample author.');
    assertNotContainsValue('Jordan Kessler', $output, 'Preview samples should not use the old Jordan sample author.');
    assertNotContainsValue('H. G. Wells', $output, 'Preview samples should not use the old specimen cite name.');
    assertNotContainsValue('Northwind', $output, 'Preview samples should not use the old Northwind placeholder brand.');
    assertNotContainsValue('northwind', $output, 'Preview samples should not use the old Northwind social handle.');
    assertContainsValue('tasty-fonts-preview-marketing-social-icon', $output, 'The Marketing social sample should include a recognizable platform icon.');
    assertContainsValue('data-role-preview="heading">12.8k</strong>', $output, 'The Marketing scene should expose numeral rendering through heading role hooks.');
    assertContainsValue('Signups this week', $output, 'The Interface scene should keep its restored metric sample.');
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
    assertContainsValue('tasty-fonts-font-source-badge', $output, 'Family card headers should keep only the source pill visible.');
    assertContainsValue('title="Self-hosted"', $output, 'The source pill should expose the active delivery label.');
    assertSameValue(1, preg_match('/tasty-fonts-detail-card--face[\s\S]*?Static/', $output), 'Expanded face detail cards should still expose readable type metadata.');
    assertContainsValue('Code Preview', $output, 'Monospace family cards should switch their specimen label to a code-oriented preview.');
    assertContainsValue('tasty-fonts-font-inline-preview is-monospace', $output, 'Monospace library cards should render the inline preview with the monospace modifier class.');
    assertContainsValue('tasty-fonts-face-preview is-monospace', $output, 'Monospace face detail cards should render the preview with the monospace modifier class.');
    assertContainsValue('400 Regular', $output, 'Expanded face detail cards should pair numeric weights with a readable weight label.');
	assertSameValue(3, substr_count($output, 'const font = &quot;JetBrains Mono&quot;;'), 'Monospace family cards should render the code sample in inline, specimen, and face previews.');
    assertContainsValue('>const font = &quot;JetBrains Mono&quot;;', $output, 'Monospace preview markup should not inject template indentation before the code sample text.');
	assertMatchesPattern('/class="tasty-fonts-font-inline-preview-text is-monospace"[\s\S]*?>const font = &quot;JetBrains Mono&quot;;<\/div>/', $output, 'Collapsed monospace previews should show the code-style font sample.');
	assertMatchesPattern('/class="tasty-fonts-font-specimen-display is-monospace"[\s\S]*?>const font = &quot;JetBrains Mono&quot;;<\/div>/', $output, 'Expanded monospace specimen previews should show the code-style font sample.');
	assertMatchesPattern('/class="tasty-fonts-face-preview is-monospace"[\s\S]*?>const font = &quot;JetBrains Mono&quot;;<\/div>/', $output, 'Expanded monospace face previews should show the code-style font sample.');
    assertNotContainsValue('font-family: var(--font-monospace);', $output, 'Monospace card previews should now stay on a single code line instead of rendering multiline specimen copy.');
    assertContainsValue('currently assigned to monospace, and this is the last saved variant', $output, 'Last-variant delete guards should mention the monospace role when it protects the family.');
    assertSameValue(1, preg_match('/data-delete-family="JetBrains Mono"[\s\S]*?aria-disabled="true"[\s\S]*?disabled/', $output), 'Blocked family delete actions should use the real disabled attribute as well as aria-disabled.');
};

$tests['admin_page_renderer_family_cards_use_code_preview_for_monospace_category'] = static function (): void {
	resetTestState();

	$renderer = new AdminPageRenderer(new Storage());
	$family = [
		'family' => 'JetBrains Mono',
		'slug' => 'jetbrains-mono',
		'font_category' => 'monospace',
		'font_category_tokens' => ['monospace'],
		'delivery_filter_tokens' => ['external'],
		'publish_state' => 'published',
		'active_delivery_id' => 'bunny-cdn',
		'active_delivery' => [
			'id' => 'bunny-cdn',
			'label' => 'Bunny CDN',
			'provider' => 'bunny',
			'type' => 'cdn',
			'variants' => ['regular'],
		],
		'available_deliveries' => [
			[
				'id' => 'bunny-cdn',
				'label' => 'Bunny CDN',
				'provider' => 'bunny',
				'type' => 'cdn',
				'variants' => ['regular'],
			],
		],
		'faces' => [
			[
				'weight' => '400',
				'style' => 'normal',
				'source' => 'bunny',
				'files' => ['woff2' => 'https://fonts.bunny.net/jetbrains-mono.woff2'],
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
				['heading' => '', 'body' => 'Inter', 'monospace' => ''],
				[],
				[],
				[
					['value' => 'inherit', 'label' => 'Use plugin default'],
					['value' => 'swap', 'label' => 'swap'],
				],
				'The quick brown fox jumps over the lazy dog.',
				[],
				['enabled' => true],
				true,
			]
		);
	} catch (\Throwable $e) {
		ob_end_clean();
		throw $e;
	}
	$output = (string) ob_get_clean();

	assertContainsValue('Code Preview', $output, 'Monospace-category family cards should use a code-oriented preview label even before role assignment.');
	assertSameValue(3, substr_count($output, 'const font = &quot;JetBrains Mono&quot;;'), 'Monospace-category cards should render the code sample in inline, specimen, and face previews.');
	assertContainsValue('tasty-fonts-font-inline-preview is-monospace', $output, 'Monospace-category cards should use monospace preview styling while collapsed.');
	assertContainsValue('tasty-fonts-face-preview is-monospace', $output, 'Monospace-category cards should use monospace preview styling in expanded face previews.');
	assertNotContainsValue('class="tasty-fonts-badge is-role">Monospace', $output, 'Monospace-category cards should not claim the role badge until assigned to the monospace role.');
	assertContainsValue('aria-label="Select monospace"', $output, 'Unassigned monospace-category cards should keep the monospace quick-assign control idle.');
};
