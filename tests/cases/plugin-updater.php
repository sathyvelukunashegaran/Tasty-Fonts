<?php

declare(strict_types=1);

use TastyFonts\Fonts\AssetService;
use TastyFonts\Plugin;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\Storage;
use TastyFonts\Updates\GitHubUpdater;

$tests['plugin_adds_a_settings_link_to_plugin_action_links'] = static function (): void {
    $links = Plugin::filterPluginActionLinks(['<a href="https://example.test/deactivate">Deactivate</a>']);

    assertContainsValue('admin.php?page=tasty-custom-fonts', $links[0] ?? '', 'Plugin action links should include a direct Settings link to the plugin admin page.');
    assertContainsValue('Settings', $links[0] ?? '', 'Plugin action links should label the direct admin link as Settings.');
};

$tests['plugin_adds_row_meta_links_for_releases_and_support'] = static function (): void {
    $links = Plugin::filterPluginRowMeta([], plugin_basename(TASTY_FONTS_FILE));

    assertContainsValue('/releases', $links[0] ?? '', 'Plugin row meta should expose the GitHub releases page.');
    assertContainsValue('GitHub Releases', $links[0] ?? '', 'Plugin row meta should label the releases link clearly.');
    assertContainsValue('/issues', $links[1] ?? '', 'Plugin row meta should expose the support issues page.');
    assertContainsValue('Support', $links[1] ?? '', 'Plugin row meta should label the support link clearly.');
};

$tests['plugin_row_meta_ignores_other_plugins'] = static function (): void {
    $links = Plugin::filterPluginRowMeta(['existing'], 'other-plugin/other-plugin.php');

    assertSameValue(['existing'], $links, 'Plugin row meta should not modify rows for unrelated plugins.');
};

$tests['plugin_boot_registers_plugin_row_meta_rest_and_font_library_sync_hooks'] = static function (): void {
    resetTestState();
    resetPluginSingleton();

    global $hookCallbacks;

    Plugin::instance()->boot();

    assertSameValue(true, isset($hookCallbacks['plugin_row_meta']), 'Boot should register the plugin row meta hook.');
    assertSameValue(true, isset($hookCallbacks['pre_set_site_transient_update_plugins']), 'Boot should register the plugin update transient hook.');
    assertSameValue(true, isset($hookCallbacks['plugins_api']), 'Boot should register the plugin information hook.');
    assertSameValue(true, isset($hookCallbacks['upgrader_process_complete']), 'Boot should register the upgrader completion hook.');
    assertSameValue(true, isset($hookCallbacks['rest_api_init']), 'Boot should register the REST API init hook.');
    assertSameValue(true, isset($hookCallbacks['tasty_fonts_after_import']), 'Boot should register the Block Editor Font Library sync hook.');
    assertSameValue(true, isset($hookCallbacks['tasty_fonts_after_delete_family']), 'Boot should register the Block Editor Font Library delete hook.');
    foreach (
        [
            'wp_ajax_tasty_fonts_search_google',
            'wp_ajax_tasty_fonts_get_google_family',
            'wp_ajax_tasty_fonts_search_bunny',
            'wp_ajax_tasty_fonts_get_bunny_family',
            'wp_ajax_tasty_fonts_import_bunny',
            'wp_ajax_tasty_fonts_import_google',
            'wp_ajax_tasty_fonts_upload_local',
            'wp_ajax_tasty_fonts_save_family_fallback',
            'wp_ajax_tasty_fonts_save_family_font_display',
            'wp_ajax_tasty_fonts_save_family_delivery',
            'wp_ajax_tasty_fonts_save_family_publish_state',
            'wp_ajax_tasty_fonts_delete_delivery_profile',
            'wp_ajax_tasty_fonts_save_role_draft',
        ] as $hookName
    ) {
        assertSameValue(false, isset($hookCallbacks[$hookName]), sprintf('Boot should not register the removed "%s" AJAX hook.', $hookName));
    }

    resetPluginSingleton();
};

$tests['plugin_deactivation_flushes_known_transients_and_clears_css_regeneration_hook'] = static function (): void {
    resetTestState();

    global $clearedScheduledHooks;

    foreach ([
        'tasty_fonts_catalog_v2',
        'tasty_fonts_css_v2',
        'tasty_fonts_css_hash_v2',
        'tasty_fonts_regenerate_css_queued',
        'tasty_fonts_google_catalog_v1',
        'tasty_fonts_bunny_catalog_v1',
        'tasty_fonts_github_release_v1',
        'tasty_fonts_github_release_version_v1',
    ] as $transientKey) {
        set_transient($transientKey, 'cached', DAY_IN_SECONDS);
    }

    Plugin::deactivate();

    foreach ([
        'tasty_fonts_catalog_v2',
        'tasty_fonts_css_v2',
        'tasty_fonts_css_hash_v2',
        'tasty_fonts_regenerate_css_queued',
        'tasty_fonts_google_catalog_v1',
        'tasty_fonts_bunny_catalog_v1',
        'tasty_fonts_github_release_v1',
        'tasty_fonts_github_release_version_v1',
    ] as $transientKey) {
        assertSameValue(false, get_transient($transientKey), 'Deactivation should clear known plugin transients.');
    }

    assertSameValue(
        true,
        in_array(AssetService::ACTION_REGENERATE_CSS, $clearedScheduledHooks, true),
        'Deactivation should clear any queued CSS regeneration cron hook.'
    );
};

$tests['github_updater_injects_a_plugin_update_from_the_latest_stable_release'] = static function (): void {
    resetTestState();
    resetPluginSingleton();

    global $remoteGetResponses;

    $remoteGetResponses['https://api.github.com/repos/sathyvelukunashegaran/Tasty-Custom-Fonts/releases'] = [
        'response' => ['code' => 200],
        'body' => json_encode(
            [
                [
                    'tag_name' => '1.6.0',
                    'draft' => false,
                    'prerelease' => false,
                    'body' => "## What's Changed\n\n- Added updater support.",
                    'published_at' => '2026-04-08T00:00:00Z',
                    'assets' => [
                        [
                            'name' => 'tasty-fonts-1.6.0.zip',
                            'browser_download_url' => 'https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/releases/download/1.6.0/tasty-fonts-1.6.0.zip',
                            'state' => 'uploaded',
                        ],
                    ],
                ],
            ]
        ),
    ];

    Plugin::instance()->boot();

    $transient = (object) [
        'checked' => [plugin_basename(TASTY_FONTS_FILE) => TASTY_FONTS_VERSION],
        'response' => [],
        'no_update' => [plugin_basename(TASTY_FONTS_FILE) => (object) ['new_version' => TASTY_FONTS_VERSION]],
    ];
    $result = apply_filters('pre_set_site_transient_update_plugins', $transient);
    $update = $result->response[plugin_basename(TASTY_FONTS_FILE)] ?? null;

    assertSameValue('1.6.0', $update->new_version ?? '', 'Updater should expose the newer GitHub release version to WordPress.');
    assertSameValue(
        'https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/releases/download/1.6.0/tasty-fonts-1.6.0.zip',
        $update->package ?? '',
        'Updater should use the attached GitHub release ZIP as the install package.'
    );
    assertSameValue(false, isset($result->no_update[plugin_basename(TASTY_FONTS_FILE)]), 'Updater should remove stale no-update entries for this plugin.');
};

$tests['github_updater_skips_same_or_older_releases'] = static function (): void {
    resetTestState();
    resetPluginSingleton();

    global $remoteGetResponses;

    $endpoint = 'https://api.github.com/repos/sathyvelukunashegaran/Tasty-Custom-Fonts/releases';
    $transient = (object) [
        'checked' => [plugin_basename(TASTY_FONTS_FILE) => TASTY_FONTS_VERSION],
        'response' => [],
    ];

    $remoteGetResponses[$endpoint] = [
        'response' => ['code' => 200],
        'body' => json_encode(
            [
                [
                    'tag_name' => '1.5.1',
                    'draft' => false,
                    'prerelease' => false,
                    'body' => '',
                    'published_at' => '2026-04-08T00:00:00Z',
                    'assets' => [
                        [
                            'name' => 'tasty-fonts-1.5.1.zip',
                            'browser_download_url' => 'https://example.test/current.zip',
                            'state' => 'uploaded',
                        ],
                    ],
                ],
            ]
        ),
    ];

    Plugin::instance()->boot();
    $sameVersion = apply_filters('pre_set_site_transient_update_plugins', $transient);

    assertSameValue([], $sameVersion->response ?? [], 'Updater should not inject an update when the latest stable release matches the installed version.');

    resetTestState();
    resetPluginSingleton();

    $remoteGetResponses[$endpoint] = [
        'response' => ['code' => 200],
        'body' => json_encode(
            [
                [
                    'tag_name' => '1.4.9',
                    'draft' => false,
                    'prerelease' => false,
                    'body' => '',
                    'published_at' => '2026-04-01T00:00:00Z',
                    'assets' => [
                        [
                            'name' => 'tasty-fonts-1.4.9.zip',
                            'browser_download_url' => 'https://example.test/older.zip',
                            'state' => 'uploaded',
                        ],
                    ],
                ],
            ]
        ),
    ];

    Plugin::instance()->boot();
    $olderVersion = apply_filters('pre_set_site_transient_update_plugins', $transient);

    assertSameValue([], $olderVersion->response ?? [], 'Updater should not inject an update when the latest stable release is older than the installed version.');
};

$tests['github_updater_skips_latest_stable_releases_without_a_valid_zip_asset'] = static function (): void {
    resetTestState();
    resetPluginSingleton();

    global $remoteGetResponses;

    $remoteGetResponses['https://api.github.com/repos/sathyvelukunashegaran/Tasty-Custom-Fonts/releases'] = [
        'response' => ['code' => 200],
        'body' => json_encode(
            [
                [
                    'tag_name' => '1.6.0',
                    'draft' => false,
                    'prerelease' => false,
                    'body' => '',
                    'published_at' => '2026-04-08T00:00:00Z',
                    'assets' => [
                        [
                            'name' => 'source-code.zip',
                            'browser_download_url' => 'https://example.test/source.zip',
                            'state' => 'uploaded',
                        ],
                    ],
                ],
                [
                    'tag_name' => '1.5.1',
                    'draft' => false,
                    'prerelease' => false,
                    'body' => '',
                    'published_at' => '2026-04-07T00:00:00Z',
                    'assets' => [
                        [
                            'name' => 'tasty-fonts-1.5.1.zip',
                            'browser_download_url' => 'https://example.test/older-valid.zip',
                            'state' => 'uploaded',
                        ],
                    ],
                ],
            ]
        ),
    ];

    Plugin::instance()->boot();

    $result = apply_filters(
        'pre_set_site_transient_update_plugins',
        (object) [
            'checked' => [plugin_basename(TASTY_FONTS_FILE) => TASTY_FONTS_VERSION],
            'response' => [],
        ]
    );

    assertSameValue([], $result->response ?? [], 'Updater should ignore the latest stable release when it does not expose the expected install ZIP asset.');
};

$tests['github_updater_ignores_prereleases_and_drafts_when_finding_updates'] = static function (): void {
    resetTestState();
    resetPluginSingleton();

    global $remoteGetResponses;

    $remoteGetResponses['https://api.github.com/repos/sathyvelukunashegaran/Tasty-Custom-Fonts/releases'] = [
        'response' => ['code' => 200],
        'body' => json_encode(
            [
                [
                    'tag_name' => '1.7.0-beta.1',
                    'draft' => false,
                    'prerelease' => true,
                    'body' => '',
                    'published_at' => '2026-04-09T00:00:00Z',
                    'assets' => [
                        [
                            'name' => 'tasty-fonts-1.7.0-beta.1.zip',
                            'browser_download_url' => 'https://example.test/beta.zip',
                            'state' => 'uploaded',
                        ],
                    ],
                ],
                [
                    'tag_name' => '1.6.0',
                    'draft' => true,
                    'prerelease' => false,
                    'body' => '',
                    'published_at' => '2026-04-08T00:00:00Z',
                    'assets' => [
                        [
                            'name' => 'tasty-fonts-1.6.0.zip',
                            'browser_download_url' => 'https://example.test/draft.zip',
                            'state' => 'uploaded',
                        ],
                    ],
                ],
                [
                    'tag_name' => '1.5.2',
                    'draft' => false,
                    'prerelease' => false,
                    'body' => '',
                    'published_at' => '2026-04-07T00:00:00Z',
                    'assets' => [
                        [
                            'name' => 'tasty-fonts-1.5.2.zip',
                            'browser_download_url' => 'https://example.test/stable.zip',
                            'state' => 'uploaded',
                        ],
                    ],
                ],
            ]
        ),
    ];

    Plugin::instance()->boot();
    $result = apply_filters(
        'pre_set_site_transient_update_plugins',
        (object) [
            'checked' => [plugin_basename(TASTY_FONTS_FILE) => TASTY_FONTS_VERSION],
            'response' => [],
        ]
    );
    $update = $result->response[plugin_basename(TASTY_FONTS_FILE)] ?? null;

    assertSameValue('1.5.2', $update->new_version ?? '', 'Updater should skip prereleases and drafts and use the latest published stable release.');
};

$tests['github_updater_returns_plugin_information_for_the_details_modal'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $remoteGetResponses['https://api.github.com/repos/sathyvelukunashegaran/Tasty-Custom-Fonts/releases'] = [
        'response' => ['code' => 200],
        'body' => json_encode(
            [
                [
                    'tag_name' => '1.6.0',
                    'draft' => false,
                    'prerelease' => false,
                    'body' => "Release notes line one.\nRelease notes line two.",
                    'published_at' => '2026-04-08T00:00:00Z',
                    'assets' => [
                        [
                            'name' => 'tasty-fonts-1.6.0.zip',
                            'browser_download_url' => 'https://example.test/release.zip',
                            'state' => 'uploaded',
                        ],
                    ],
                ],
            ]
        ),
    ];

    $updater = new GitHubUpdater();
    $updater->registerHooks();

    $result = apply_filters('plugins_api', false, 'plugin_information', (object) ['slug' => 'tasty-fonts']);

    assertSameValue('Tasty Custom Fonts', $result->name ?? '', 'Plugin details should expose the plugin name.');
    assertSameValue('tasty-fonts', $result->slug ?? '', 'Plugin details should use the current plugin slug.');
    assertSameValue('1.6.0', $result->version ?? '', 'Plugin details should report the latest stable release version.');
    assertSameValue('1.5.1', $result->current_version ?? '', 'Plugin details should include the installed plugin version.');
    assertSameValue('https://example.test/release.zip', $result->download_link ?? '', 'Plugin details should expose the release ZIP download link.');
    assertContainsValue('Release notes line one.', $result->sections['changelog'] ?? '', 'Plugin details should render release notes from the GitHub release body.');

    $ignored = apply_filters('plugins_api', false, 'plugin_information', (object) ['slug' => 'other-plugin']);

    assertSameValue(false, $ignored, 'Plugin details should ignore requests for other plugin slugs.');
};

$tests['github_updater_reuses_cached_release_metadata_and_clears_cache_after_upgrade'] = static function (): void {
    resetTestState();
    resetPluginSingleton();

    global $remoteGetCalls;
    global $remoteGetResponses;

    $endpoint = 'https://api.github.com/repos/sathyvelukunashegaran/Tasty-Custom-Fonts/releases';
    $remoteGetResponses[$endpoint] = [
        'response' => ['code' => 200],
        'body' => json_encode(
            [
                [
                    'tag_name' => '1.6.0',
                    'draft' => false,
                    'prerelease' => false,
                    'body' => '',
                    'published_at' => '2026-04-08T00:00:00Z',
                    'assets' => [
                        [
                            'name' => 'tasty-fonts-1.6.0.zip',
                            'browser_download_url' => 'https://example.test/release.zip',
                            'state' => 'uploaded',
                        ],
                    ],
                ],
            ]
        ),
    ];

    Plugin::instance()->boot();

    $transient = (object) [
        'checked' => [plugin_basename(TASTY_FONTS_FILE) => TASTY_FONTS_VERSION],
        'response' => [],
    ];

    apply_filters('pre_set_site_transient_update_plugins', $transient);
    apply_filters('pre_set_site_transient_update_plugins', $transient);

    assertSameValue(1, count($remoteGetCalls), 'Updater should reuse cached release metadata between update checks.');

    do_action(
        'upgrader_process_complete',
        null,
        [
            'action' => 'update',
            'type' => 'plugin',
            'plugins' => [plugin_basename(TASTY_FONTS_FILE)],
        ]
    );

    assertSameValue(false, get_transient('tasty_fonts_github_release_v1'), 'Updater should clear cached release metadata after a successful plugin upgrade.');

    apply_filters('pre_set_site_transient_update_plugins', $transient);

    assertSameValue(2, count($remoteGetCalls), 'Updater should fetch fresh metadata after the upgrader cache reset.');
};

$tests['github_updater_ignores_unrelated_upgrader_events'] = static function (): void {
    resetTestState();
    resetPluginSingleton();

    global $remoteGetResponses;

    $endpoint = 'https://api.github.com/repos/sathyvelukunashegaran/Tasty-Custom-Fonts/releases';
    $remoteGetResponses[$endpoint] = [
        'response' => ['code' => 200],
        'body' => json_encode(
            [
                [
                    'tag_name' => '1.6.0',
                    'draft' => false,
                    'prerelease' => false,
                    'published_at' => '2026-04-08T12:00:00Z',
                    'body' => "## What's Changed\n\n- Added more reliability coverage.",
                    'assets' => [
                        [
                            'name' => 'tasty-fonts-1.6.0.zip',
                            'browser_download_url' => 'https://example.test/tasty-fonts-1.6.0.zip',
                            'state' => 'uploaded',
                        ],
                    ],
                ],
            ]
        ),
    ];

    Plugin::instance()->boot();

    $transient = (object) [
        'checked' => [plugin_basename(TASTY_FONTS_FILE) => TASTY_FONTS_VERSION],
        'response' => [],
    ];

    apply_filters('pre_set_site_transient_update_plugins', $transient);

    assertSameValue(true, is_array(get_transient('tasty_fonts_github_release_v1')), 'Updater should populate the release cache before exercising upgrader hook boundaries.');

    do_action(
        'upgrader_process_complete',
        null,
        [
            'action' => 'update',
            'type' => 'theme',
            'themes' => ['twentytwentysix'],
        ]
    );

    assertSameValue(true, is_array(get_transient('tasty_fonts_github_release_v1')), 'Updater should ignore non-plugin upgrader events.');

    do_action(
        'upgrader_process_complete',
        null,
        [
            'action' => 'update',
            'type' => 'plugin',
            'plugins' => ['other-plugin/other-plugin.php'],
        ]
    );

    assertSameValue(true, is_array(get_transient('tasty_fonts_github_release_v1')), 'Updater should ignore plugin upgrades for other plugins.');
};

$tests['github_updater_handles_network_and_response_failures_quietly'] = static function (): void {
    resetTestState();
    resetPluginSingleton();

    global $remoteGetResponses;

    $endpoint = 'https://api.github.com/repos/sathyvelukunashegaran/Tasty-Custom-Fonts/releases';
    $transient = (object) [
        'checked' => [plugin_basename(TASTY_FONTS_FILE) => TASTY_FONTS_VERSION],
        'response' => [],
    ];

    $remoteGetResponses[$endpoint] = new WP_Error('http_failed', 'GitHub unavailable');

    Plugin::instance()->boot();
    $errorResult = apply_filters('pre_set_site_transient_update_plugins', $transient);

    assertSameValue([], $errorResult->response ?? [], 'Updater should leave the update transient unchanged when GitHub cannot be reached.');

    resetTestState();
    resetPluginSingleton();

    $remoteGetResponses[$endpoint] = [
        'response' => ['code' => 200],
        'body' => '{not-json',
    ];

    Plugin::instance()->boot();
    $malformedResult = apply_filters('pre_set_site_transient_update_plugins', $transient);

    assertSameValue([], $malformedResult->response ?? [], 'Updater should leave the update transient unchanged when GitHub returns malformed JSON.');
};

$tests['github_updater_clears_cached_release_data_when_the_installed_version_changes'] = static function (): void {
    resetTestState();

    set_transient('tasty_fonts_github_release_v1', ['version' => '1.6.0'], HOUR_IN_SECONDS);
    set_transient('tasty_fonts_github_release_version_v1', '1.4.0', DAY_IN_SECONDS);

    $updater = new GitHubUpdater();
    $updater->registerHooks();

    assertSameValue(false, get_transient('tasty_fonts_github_release_v1'), 'Updater should clear cached release metadata when the installed plugin version changes.');
    assertSameValue('1.5.1', get_transient('tasty_fonts_github_release_version_v1'), 'Updater should persist the current installed version after clearing stale updater caches.');
};

$tests['block_editor_font_library_sync_registers_managed_font_families_after_import'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;
    global $remoteRequestCalls;
    global $remoteRequestResponses;

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['block_editor_font_library_sync_enabled' => '1']);
    $services['settings']->saveFamilyFontDisplay('Inter', 'swap');
    $family = $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-self-hosted',
            'provider' => 'google',
            'type' => 'self_hosted',
            'label' => 'Self-hosted (Google import)',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'google',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'google/inter/inter-400-normal.woff2'],
                'provider' => ['category' => 'sans-serif'],
            ]],
            'meta' => ['category' => 'sans-serif'],
        ],
        'published',
        true
    );

    $remoteGetResponses['https://example.test/wp-json/wp/v2/font-families?slug=tasty-fonts-inter&context=edit'] = [
        'response' => ['code' => 200],
        'body' => '[]',
    ];
    $remoteRequestResponses['POST https://example.test/wp-json/wp/v2/font-families'] = [
        'response' => ['code' => 201],
        'body' => json_encode(['id' => 321]),
    ];
    $remoteRequestResponses['POST https://example.test/wp-json/wp/v2/font-families/321/font-faces'] = [
        'response' => ['code' => 201],
        'body' => json_encode(['id' => 654]),
    ];

    $services['block_editor_font_library']->syncImportedFamily(
        [
            'status' => 'imported',
            'family' => 'Inter',
            'delivery_id' => 'google-self-hosted',
            'family_record' => $family,
        ],
        'google'
    );

    assertSameValue(
        'https://example.test/wp-json/wp/v2/font-families?slug=tasty-fonts-inter&context=edit',
        (string) ($remoteGetCalls[0]['url'] ?? ''),
        'Font Library sync should look up the managed family slug before creating it.'
    );
    assertSameValue(2, count($remoteRequestCalls), 'Font Library sync should create one family and one face for the imported profile.');

    $familyBody = json_decode((string) ($remoteRequestCalls[0]['args']['body']['font_family_settings'] ?? ''), true);
    $faceBody = json_decode((string) ($remoteRequestCalls[1]['args']['body']['font_face_settings'] ?? ''), true);

    assertSameValue('tasty-fonts-inter', (string) ($familyBody['slug'] ?? ''), 'Font Library sync should register plugin-managed family slugs to avoid collisions.');
    assertSameValue('Inter', (string) ($familyBody['name'] ?? ''), 'Font Library sync should preserve the family display name.');
    assertSameValue('"Inter", sans-serif', (string) ($familyBody['fontFamily'] ?? ''), 'Font Library sync should register the family stack for editor presets.');
    assertSameValue('"Inter"', (string) ($faceBody['fontFamily'] ?? ''), 'Font Library sync should use a quoted family name in font-face definitions.');
    assertSameValue('swap', (string) ($faceBody['fontDisplay'] ?? ''), 'Font Library sync should carry the plugin font-display setting into editor font faces.');
    assertSameValue(
        'https://example.test/wp-content/uploads/fonts/google/inter/inter-400-normal.woff2',
        (string) (($faceBody['src'][0] ?? '')),
        'Font Library sync should convert stored relative font paths into public upload URLs.'
    );
};

$tests['block_editor_font_library_sync_is_disabled_by_default_on_local_hosts'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteRequestCalls;

    $services = makeServiceGraph();
    $family = $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-self-hosted',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'google',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'google/inter/inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );

    $services['block_editor_font_library']->syncImportedFamily(
        [
            'status' => 'imported',
            'family' => 'Inter',
            'delivery_id' => 'google-self-hosted',
            'family_record' => $family,
        ],
        'google'
    );

    assertSameValue([], $remoteGetCalls, 'Local installs should leave Block Editor Font Library sync off until the user enables it.');
    assertSameValue([], $remoteRequestCalls, 'No Block Editor Font Library requests should run while the local default remains off.');
};

$tests['block_editor_font_library_sync_respects_opt_out_filter'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteRequestCalls;

    add_filter(
        'tasty_fonts_sync_block_editor_font_library',
        static function (): bool {
            return false;
        }
    );

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['block_editor_font_library_sync_enabled' => '1']);
    $family = $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-self-hosted',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'google',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'google/inter/inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );

    $services['block_editor_font_library']->syncImportedFamily(
        [
            'status' => 'imported',
            'family' => 'Inter',
            'delivery_id' => 'google-self-hosted',
            'family_record' => $family,
        ],
        'google'
    );

    assertSameValue([], $remoteGetCalls, 'The opt-out filter should skip Font Library lookup requests.');
    assertSameValue([], $remoteRequestCalls, 'The opt-out filter should skip Font Library write requests.');
};

$tests['block_editor_font_library_sync_logs_actionable_certificate_failures'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;
    global $remoteRequestResponses;

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['block_editor_font_library_sync_enabled' => '1']);
    $family = $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-self-hosted',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'google',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'google/inter/inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );

    $remoteGetResponses['https://example.test/wp-json/wp/v2/font-families?slug=tasty-fonts-inter&context=edit'] = [
        'response' => ['code' => 200],
        'body' => '[]',
    ];
    $remoteRequestResponses['POST https://example.test/wp-json/wp/v2/font-families'] = new WP_Error(
        'http_request_failed',
        'cURL error 60: SSL certificate OpenSSL verify result: unable to get local issuer certificate (20)'
    );

    $services['block_editor_font_library']->syncImportedFamily(
        [
            'status' => 'imported',
            'family' => 'Inter',
            'delivery_id' => 'google-self-hosted',
            'family_record' => $family,
        ],
        'google'
    );

    $entries = $services['log']->all();

    assertContainsValue('could not verify this site', (string) ($entries[0]['message'] ?? ''), 'TLS trust failures should be rewritten into actionable log messages.');
    assertSameValue('Open Plugin Behavior', (string) ($entries[0]['action_label'] ?? ''), 'TLS trust failures should include a direct action label for the settings panel.');
    assertContainsValue('tf_studio=plugin-behavior', (string) ($entries[0]['action_url'] ?? ''), 'TLS trust failures should deep-link to the Plugin Behavior tab.');
};

$tests['block_editor_font_library_sync_skips_when_core_font_post_types_are_unavailable'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteRequestCalls;
    global $supportedPostTypes;

    $supportedPostTypes = [];

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['block_editor_font_library_sync_enabled' => '1']);
    $family = $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-self-hosted',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'google',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'google/inter/inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );

    $services['block_editor_font_library']->syncImportedFamily(
        [
            'status' => 'imported',
            'family' => 'Inter',
            'delivery_id' => 'google-self-hosted',
            'family_record' => $family,
        ],
        'google'
    );

    assertSameValue([], $remoteGetCalls, 'Font Library sync should no-op on WordPress versions without the core font post types.');
    assertSameValue([], $remoteRequestCalls, 'Font Library sync should no-op on WordPress versions without the core font post types.');
};

$tests['block_editor_font_library_sync_removes_managed_family_records_on_delete'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;
    global $remoteGetCalls;
    global $remoteRequestCalls;
    global $remoteRequestResponses;

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['block_editor_font_library_sync_enabled' => '1']);
    $remoteGetResponses['https://example.test/wp-json/wp/v2/font-families?slug=tasty-fonts-inter&context=edit'] = [
        'response' => ['code' => 200],
        'body' => json_encode([['id' => 321]]),
    ];
    $remoteRequestResponses['DELETE https://example.test/wp-json/wp/v2/font-families/321?force=true'] = [
        'response' => ['code' => 200],
        'body' => json_encode(['deleted' => true]),
    ];

    $services['block_editor_font_library']->deleteSyncedFamily('inter', 'Inter');

    assertSameValue(
        'https://example.test/wp-json/wp/v2/font-families?slug=tasty-fonts-inter&context=edit',
        (string) ($remoteGetCalls[0]['url'] ?? ''),
        'Font Library delete sync should look up the managed family slug before deleting it.'
    );
    assertSameValue(
        'DELETE',
        (string) ($remoteRequestCalls[0]['method'] ?? ''),
        'Font Library delete sync should issue a DELETE request to the managed core font family.'
    );
    assertSameValue(
        'https://example.test/wp-json/wp/v2/font-families/321?force=true',
        (string) ($remoteRequestCalls[0]['url'] ?? ''),
        'Font Library delete sync should force-delete the managed core font family.'
    );
};

$tests['log_repository_can_reseed_audit_entry_after_clear'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $log->add('Fonts rescanned.');
    $log->clear();
    $log->add('Activity log cleared. Older entries removed.');

    $entries = $log->all();

    assertSameValue(1, count($entries), 'Clearing the log and reseeding the audit entry should leave exactly one retained entry.');
    assertSameValue('Activity log cleared. Older entries removed.', $entries[0]['message'] ?? '', 'The retained entry should explain that the older activity was removed.');
    assertSameValue('System', $entries[0]['actor'] ?? '', 'The retained clear-log audit entry should still record an actor label.');
};

$tests['uninstall_cleans_library_and_runtime_transients'] = static function (): void {
    resetTestState();

    global $optionDeleted;
    global $optionStore;
    global $transientDeleted;
    global $transientStore;
    global $wpdbQueries;

    if (!defined('WP_UNINSTALL_PLUGIN')) {
        define('WP_UNINSTALL_PLUGIN', 'etch-fonts/plugin.php');
    }

    $optionStore = [
        'tasty_fonts_settings' => [
            'delete_uploaded_files_on_uninstall' => false,
            'adobe_project_id' => '',
        ],
        'tasty_fonts_google_api_key_data' => [
            'google_api_key' => 'live-key',
            'google_api_key_status' => 'valid',
            'google_api_key_status_message' => 'Ready',
            'google_api_key_checked_at' => 123,
        ],
        'tasty_fonts_library' => ['Inter' => ['delivery_profiles' => []]],
        'tasty_fonts_imports' => ['legacy' => true],
        'tasty_fonts_local_environment_notice_preferences' => [1 => ['hidden_until' => 123456, 'dismissed_forever' => true]],
    ];
    $transientStore = [
        'tasty_fonts_bunny_catalog_v1' => ['Inter'],
    ];

    require dirname(__DIR__, 2) . '/uninstall.php';

    assertSameValue(true, in_array('tasty_fonts_library', $optionDeleted, true), 'Uninstall should delete the live library option key.');
    assertSameValue(true, in_array('tasty_fonts_google_api_key_data', $optionDeleted, true), 'Uninstall should delete the dedicated Google API key option.');
    assertSameValue(true, in_array('tasty_fonts_imports', $optionDeleted, true), 'Uninstall should continue deleting the legacy imports option key.');
    assertSameValue(true, in_array('tasty_fonts_local_environment_notice_preferences', $optionDeleted, true), 'Uninstall should delete persisted local-environment reminder preferences.');
    assertSameValue(true, in_array('tasty_fonts_bunny_catalog_v1', $transientDeleted, true), 'Uninstall should delete the Bunny catalog transient.');
    assertSameValue(2, count($wpdbQueries), 'Uninstall should issue wildcard cleanup queries for Bunny family and admin notice transients.');
    assertContainsValue('DELETE FROM wp_options WHERE option_name LIKE', $wpdbQueries[0] ?? '', 'Uninstall should target the options table when cleaning Bunny family transients.');
    assertContainsValue('tasty\\_fonts\\_bunny\\_family\\_', $wpdbQueries[0] ?? '', 'Uninstall should wildcard-match Bunny family transients.');
    assertContainsValue('timeout', $wpdbQueries[0] ?? '', 'Uninstall should also remove Bunny family transient timeout rows.');
    assertContainsValue('DELETE FROM wp_options WHERE option_name LIKE', $wpdbQueries[1] ?? '', 'Uninstall should target the options table when cleaning admin notice transients.');
    assertContainsValue('tasty\\_fonts\\_admin\\_notices\\_', $wpdbQueries[1] ?? '', 'Uninstall should wildcard-match per-user admin notice transients.');
    assertContainsValue('timeout', $wpdbQueries[1] ?? '', 'Uninstall should also remove admin notice transient timeout rows.');
};

$tests['uninstall_always_deletes_generated_css_and_removes_synced_block_editor_families'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;
    global $remoteRequestResponses;
    global $remoteGetCalls;
    global $remoteRequestCalls;
    global $optionStore;

    if (!defined('WP_UNINSTALL_PLUGIN')) {
        define('WP_UNINSTALL_PLUGIN', 'etch-fonts/plugin.php');
    }

    $storage = new Storage();
    $generatedPath = $storage->getGeneratedCssPath();

    if (is_string($generatedPath)) {
        mkdir(dirname($generatedPath), FS_CHMOD_DIR, true);
        file_put_contents($generatedPath, 'generated-css');
    }

    $optionStore = [
        'tasty_fonts_settings' => [
            'delete_uploaded_files_on_uninstall' => false,
            'block_editor_font_library_sync_enabled' => true,
            'adobe_project_id' => '',
        ],
        'tasty_fonts_library' => [
            'inter' => [
                'family' => 'Inter',
                'slug' => 'inter',
                'delivery_profiles' => [],
            ],
        ],
    ];
    $remoteGetResponses['https://example.test/wp-json/wp/v2/font-families?slug=tasty-fonts-inter&context=edit'] = [
        'response' => ['code' => 200],
        'body' => json_encode([['id' => 321]]),
    ];
    $remoteRequestResponses['DELETE https://example.test/wp-json/wp/v2/font-families/321?force=true'] = [
        'response' => ['code' => 200],
        'body' => json_encode(['deleted' => true]),
    ];

    require dirname(__DIR__, 2) . '/uninstall.php';

    assertSameValue(false, is_string($generatedPath) && file_exists($generatedPath), 'Uninstall should always delete the generated CSS file.');
    assertSameValue(
        'https://example.test/wp-json/wp/v2/font-families?slug=tasty-fonts-inter&context=edit',
        (string) ($remoteGetCalls[0]['url'] ?? ''),
        'Uninstall should remove managed Block Editor Font Library families when sync was enabled.'
    );
    assertSameValue(
        'https://example.test/wp-json/wp/v2/font-families/321?force=true',
        (string) ($remoteRequestCalls[0]['url'] ?? ''),
        'Uninstall should force-delete the managed Block Editor Font Library family.'
    );
};
