<?php

declare(strict_types=1);

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;

// ---------------------------------------------------------------------------
// ImportRepository – getFamily / saveFamily
// ---------------------------------------------------------------------------

$tests['import_repository_saves_and_retrieves_a_family_by_slug'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();

    $repo->saveFamily([
        'family' => 'Inter',
        'slug'   => 'inter',
        'publish_state' => 'published',
        'active_delivery_id' => 'google-self-hosted',
        'delivery_profiles' => [
            'google-self-hosted' => [
                'id'       => 'google-self-hosted',
                'provider' => 'google',
                'type'     => 'self_hosted',
                'label'    => 'Self-hosted (Google import)',
                'variants' => ['regular'],
                'faces'    => [
                    [
                        'family' => 'Inter',
                        'slug'   => 'inter',
                        'source' => 'google',
                        'weight' => '400',
                        'style'  => 'normal',
                        'files'  => ['woff2' => 'google/inter/inter-400-normal.woff2'],
                        'paths'  => [],
                    ],
                ],
                'meta'     => ['category' => 'sans-serif'],
            ],
        ],
    ]);

    $family = $repo->getFamily('inter');

    assertSameValue('Inter', (string) ($family['family'] ?? ''), 'getFamily should return the saved family name.');
    assertSameValue('inter', (string) ($family['slug'] ?? ''), 'getFamily should return the saved family slug.');
    assertSameValue('published', (string) ($family['publish_state'] ?? ''), 'getFamily should return the saved publish state.');
    assertSameValue('google-self-hosted', (string) ($family['active_delivery_id'] ?? ''), 'getFamily should return the active delivery ID.');
    assertSameValue(1, count($family['delivery_profiles'] ?? []), 'getFamily should return all saved delivery profiles.');
};

$tests['import_repository_returns_null_for_unknown_family_slugs'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();

    assertSameValue(null, $repo->getFamily('does-not-exist'), 'getFamily should return null for slugs not present in the library.');
};

$tests['import_repository_ignores_save_calls_with_missing_slug'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();
    $repo->saveFamily(['family' => 'Inter']);

    assertSameValue([], $repo->allFamilies(), 'saveFamily should silently skip records that have no slug.');
};

// ---------------------------------------------------------------------------
// ImportRepository – deleteFamily
// ---------------------------------------------------------------------------

$tests['import_repository_deletes_an_existing_family_by_slug'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();
    $repo->saveFamily([
        'family' => 'Inter',
        'slug'   => 'inter',
        'publish_state'       => 'published',
        'active_delivery_id'  => 'local-self-hosted',
        'delivery_profiles'   => [
            'local-self-hosted' => [
                'id'       => 'local-self-hosted',
                'provider' => 'local',
                'type'     => 'self_hosted',
                'variants' => ['regular'],
                'faces'    => [
                    ['family' => 'Inter', 'slug' => 'inter', 'source' => 'local', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'upload/inter.woff2'], 'paths' => []],
                ],
                'meta'     => [],
            ],
        ],
    ]);

    $repo->deleteFamily('inter');

    assertSameValue(null, $repo->getFamily('inter'), 'deleteFamily should remove the family from the library.');
};

$tests['import_repository_delete_family_is_a_no_op_for_unknown_slugs'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();
    $repo->deleteFamily('non-existent');

    assertSameValue([], $repo->allFamilies(), 'deleteFamily should not throw when deleting a family that does not exist.');
};

// ---------------------------------------------------------------------------
// ImportRepository – ensureFamily
// ---------------------------------------------------------------------------

$tests['import_repository_ensure_family_creates_a_new_stub_record'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();
    $result = $repo->ensureFamily('Lora');

    assertSameValue('Lora', (string) ($result['family'] ?? ''), 'ensureFamily should set the family name from the provided string.');
    assertSameValue('lora', (string) ($result['slug'] ?? ''), 'ensureFamily should derive a slug from the family name.');
    assertSameValue('published', (string) ($result['publish_state'] ?? ''), 'ensureFamily should use "published" as the default publish state.');
};

$tests['import_repository_ensure_family_returns_existing_record_unchanged'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();
    $repo->saveFamily([
        'family'             => 'Lora',
        'slug'               => 'lora',
        'publish_state'      => 'library_only',
        'active_delivery_id' => '',
        'delivery_profiles'  => [],
    ]);

    $existing = $repo->ensureFamily('Lora');

    assertSameValue('library_only', (string) ($existing['publish_state'] ?? ''), 'ensureFamily should return the existing record without modifying its publish state.');
};

$tests['import_repository_ensure_family_returns_empty_array_for_empty_name'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();

    assertSameValue([], $repo->ensureFamily(''), 'ensureFamily should return an empty array when the family name is empty.');
};

// ---------------------------------------------------------------------------
// ImportRepository – saveProfile
// ---------------------------------------------------------------------------

$tests['import_repository_save_profile_activates_first_profile_automatically'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();
    $result = $repo->saveProfile(
        'Nunito',
        'nunito',
        [
            'id'       => 'google-cdn',
            'provider' => 'google',
            'type'     => 'cdn',
            'variants' => ['regular', '700'],
            'faces'    => [],
            'meta'     => ['category' => 'sans-serif'],
        ],
        'library_only',
        true
    );

    assertSameValue('Nunito', (string) ($result['family'] ?? ''), 'saveProfile should persist the family name.');
    assertSameValue('google-cdn', (string) ($result['active_delivery_id'] ?? ''), 'saveProfile should activate the new profile when activate=true.');
    assertSameValue(1, count($result['delivery_profiles'] ?? []), 'saveProfile should store the delivery profile.');
};

$tests['import_repository_save_profile_adds_second_profile_alongside_first'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();

    // Add a first profile and make it active.
    $repo->saveProfile(
        'Nunito',
        'nunito',
        [
            'id'       => 'google-cdn',
            'provider' => 'google',
            'type'     => 'cdn',
            'variants' => ['regular'],
            'faces'    => [],
            'meta'     => [],
        ],
        'library_only',
        true
    );

    // Add a second profile without explicitly activating it.
    $result = $repo->saveProfile(
        'Nunito',
        'nunito',
        [
            'id'       => 'google-self-hosted',
            'provider' => 'google',
            'type'     => 'self_hosted',
            'variants' => ['regular'],
            'faces'    => [
                ['family' => 'Nunito', 'slug' => 'nunito', 'source' => 'google', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'google/nunito/nunito-400-normal.woff2'], 'paths' => []],
            ],
            'meta'     => [],
        ],
        'library_only',
        false
    );

    assertSameValue(2, count($result['delivery_profiles'] ?? []), 'saveProfile should add a second delivery profile without removing the first.');
    assertTrueValue(isset($result['delivery_profiles']['google-cdn']), 'saveProfile should preserve the first delivery profile when a second profile is added.');
    assertTrueValue(isset($result['delivery_profiles']['google-self-hosted']), 'saveProfile should include the newly added profile in the delivery profiles.');
};

$tests['import_repository_save_profile_returns_empty_array_for_unsupported_provider'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();
    $result = $repo->saveProfile(
        'Inter',
        'inter',
        [
            'id'       => 'unknown-cdn',
            'provider' => 'unsupported',
            'type'     => 'cdn',
            'variants' => ['regular'],
            'faces'    => [],
            'meta'     => [],
        ]
    );

    assertSameValue([], $result, 'saveProfile should return an empty array when the delivery profile has an unsupported provider.');
};

// ---------------------------------------------------------------------------
// ImportRepository – deleteProfile
// ---------------------------------------------------------------------------

$tests['import_repository_delete_profile_removes_the_family_when_its_last_profile_is_deleted'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();
    $repo->saveProfile(
        'Cabin',
        'cabin',
        [
            'id'       => 'bunny-self-hosted',
            'provider' => 'bunny',
            'type'     => 'self_hosted',
            'variants' => ['regular'],
            'faces'    => [
                ['family' => 'Cabin', 'slug' => 'cabin', 'source' => 'bunny', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'bunny/cabin/cabin-400-normal.woff2'], 'paths' => []],
            ],
            'meta'     => [],
        ],
        'published',
        true
    );

    $remaining = $repo->deleteProfile('cabin', 'bunny-self-hosted');

    assertSameValue(null, $remaining, 'deleteProfile should return null when the last profile is deleted.');
    assertSameValue(null, $repo->getFamily('cabin'), 'deleteProfile should remove the family entirely when its last delivery profile is deleted.');
};

$tests['import_repository_delete_profile_reassigns_active_delivery_when_active_profile_is_deleted'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();
    $repo->saveProfile(
        'Cabin',
        'cabin',
        [
            'id'       => 'bunny-cdn',
            'provider' => 'bunny',
            'type'     => 'cdn',
            'variants' => ['regular'],
            'faces'    => [],
            'meta'     => [],
        ],
        'published',
        true
    );
    $repo->saveProfile(
        'Cabin',
        'cabin',
        [
            'id'       => 'bunny-self-hosted',
            'provider' => 'bunny',
            'type'     => 'self_hosted',
            'variants' => ['regular'],
            'faces'    => [
                ['family' => 'Cabin', 'slug' => 'cabin', 'source' => 'bunny', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'bunny/cabin/cabin-400-normal.woff2'], 'paths' => []],
            ],
            'meta'     => [],
        ],
        'published',
        false
    );

    // Explicitly ensure bunny-cdn is the active delivery before deleting it.
    $repo->setActiveDelivery('cabin', 'bunny-cdn');
    $beforeDelete = $repo->getFamily('cabin');
    assertSameValue('bunny-cdn', (string) ($beforeDelete['active_delivery_id'] ?? ''), 'Test setup should confirm bunny-cdn is the active delivery before it is deleted.');

    // Delete the active profile.
    $remaining = $repo->deleteProfile('cabin', 'bunny-cdn');

    assertSameValue(true, is_array($remaining), 'deleteProfile should return the updated family record when a non-last profile is deleted.');
    assertSameValue('bunny-self-hosted', (string) ($remaining['active_delivery_id'] ?? ''), 'deleteProfile should reassign the active delivery to the remaining profile.');
    assertSameValue(1, count($remaining['delivery_profiles'] ?? []), 'deleteProfile should leave exactly one profile after removing the active one.');
};

$tests['import_repository_delete_profile_returns_null_for_non_existent_family_or_profile'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();

    assertSameValue(null, $repo->deleteProfile('does-not-exist', 'any-delivery'), 'deleteProfile should return null when the family does not exist.');

    $repo->saveProfile(
        'Cabin',
        'cabin',
        [
            'id'       => 'bunny-self-hosted',
            'provider' => 'bunny',
            'type'     => 'self_hosted',
            'variants' => ['regular'],
            'faces'    => [
                ['family' => 'Cabin', 'slug' => 'cabin', 'source' => 'bunny', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'bunny/cabin/cabin-400-normal.woff2'], 'paths' => []],
            ],
            'meta'     => [],
        ],
        'published',
        true
    );

    assertSameValue(null, $repo->deleteProfile('cabin', 'non-existent-delivery'), 'deleteProfile should return null when the delivery profile does not exist on the family.');
};

// ---------------------------------------------------------------------------
// ImportRepository – setActiveDelivery
// ---------------------------------------------------------------------------

$tests['import_repository_set_active_delivery_updates_the_active_profile'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();
    $repo->saveProfile(
        'Oswald',
        'oswald',
        [
            'id'       => 'google-cdn',
            'provider' => 'google',
            'type'     => 'cdn',
            'variants' => ['regular'],
            'faces'    => [],
            'meta'     => [],
        ],
        'published',
        true
    );
    $repo->saveProfile(
        'Oswald',
        'oswald',
        [
            'id'       => 'google-self-hosted',
            'provider' => 'google',
            'type'     => 'self_hosted',
            'variants' => ['regular'],
            'faces'    => [
                ['family' => 'Oswald', 'slug' => 'oswald', 'source' => 'google', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'google/oswald/oswald-400-normal.woff2'], 'paths' => []],
            ],
            'meta'     => [],
        ],
        'published',
        false
    );

    $updated = $repo->setActiveDelivery('oswald', 'google-self-hosted');

    assertSameValue('google-self-hosted', (string) ($updated['active_delivery_id'] ?? ''), 'setActiveDelivery should update the active delivery ID.');
};

$tests['import_repository_set_active_delivery_can_update_publish_state_simultaneously'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();
    $repo->saveProfile(
        'Oswald',
        'oswald',
        [
            'id'       => 'google-cdn',
            'provider' => 'google',
            'type'     => 'cdn',
            'variants' => ['regular'],
            'faces'    => [],
            'meta'     => [],
        ],
        'library_only',
        true
    );

    $updated = $repo->setActiveDelivery('oswald', 'google-cdn', 'role_active');

    assertSameValue('role_active', (string) ($updated['publish_state'] ?? ''), 'setActiveDelivery should update the publish state when a state is provided.');
};

$tests['import_repository_set_active_delivery_returns_null_for_missing_family_or_profile'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();

    assertSameValue(null, $repo->setActiveDelivery('does-not-exist', 'any'), 'setActiveDelivery should return null when the family does not exist.');
};

// ---------------------------------------------------------------------------
// ImportRepository – setPublishState
// ---------------------------------------------------------------------------

$tests['import_repository_set_publish_state_updates_the_stored_state'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();
    $repo->saveFamily([
        'family'             => 'Raleway',
        'slug'               => 'raleway',
        'publish_state'      => 'library_only',
        'active_delivery_id' => '',
        'delivery_profiles'  => [],
    ]);

    $updated = $repo->setPublishState('raleway', 'role_active');

    assertSameValue('role_active', (string) ($updated['publish_state'] ?? ''), 'setPublishState should persist the new publish state.');
};

$tests['import_repository_set_publish_state_returns_null_for_missing_family'] = static function (): void {
    resetTestState();

    $repo = new ImportRepository();

    assertSameValue(null, $repo->setPublishState('does-not-exist', 'published'), 'setPublishState should return null when the family does not exist.');
};

// ---------------------------------------------------------------------------
// ImportRepository – clearLibrary
// ---------------------------------------------------------------------------

$tests['import_repository_clear_library_empties_all_stored_families'] = static function (): void {
    resetTestState();

    global $optionStore;

    $repo = new ImportRepository();
    $repo->saveFamily([
        'family'             => 'Inter',
        'slug'               => 'inter',
        'publish_state'      => 'published',
        'active_delivery_id' => '',
        'delivery_profiles'  => [],
    ]);

    $repo->clearLibrary();

    assertSameValue(false, isset($optionStore[ImportRepository::OPTION_LIBRARY]), 'clearLibrary should delete the primary library option.');
    assertSameValue(false, isset($optionStore[ImportRepository::OPTION_IMPORTS]), 'clearLibrary should delete the legacy imports option.');
    assertSameValue([], $repo->allFamilies(), 'allFamilies should return an empty array after clearLibrary.');
};

// ---------------------------------------------------------------------------
// ImportRepository – legacy import migration
// ---------------------------------------------------------------------------

$tests['import_repository_migrates_legacy_tasty_fonts_imports_option_on_first_read'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[ImportRepository::OPTION_IMPORTS] = [
        'inter' => [
            'family'      => 'Inter',
            'slug'        => 'inter',
            'provider'    => 'google',
            'variants'    => ['regular'],
            'faces'       => [
                ['family' => 'Inter', 'slug' => 'inter', 'source' => 'google', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'google/inter/inter-400-normal.woff2'], 'paths' => []],
            ],
            'category'    => 'sans-serif',
            'imported_at' => '2024-01-01 00:00:00',
        ],
    ];

    $repo = new ImportRepository();
    $families = $repo->allFamilies();

    assertSameValue(true, isset($families['inter']), 'Legacy tasty_fonts_imports data should be migrated to the new library format on first read.');
    assertSameValue('Inter', (string) ($families['inter']['family'] ?? ''), 'Migrated family should retain its original family name.');
    assertSameValue(true, isset($optionStore[ImportRepository::OPTION_LIBRARY]), 'Migration should persist the converted data to the new option key.');
};

$tests['import_repository_migrates_legacy_etch_fonts_imports_option_on_first_read'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[ImportRepository::LEGACY_OPTION_IMPORTS] = [
        'lora' => [
            'family'   => 'Lora',
            'slug'     => 'lora',
            'provider' => 'google',
            'variants' => ['regular'],
            'faces'    => [
                ['family' => 'Lora', 'slug' => 'lora', 'source' => 'google', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'google/lora/lora-400-normal.woff2'], 'paths' => []],
            ],
            'category' => 'serif',
            'imported_at' => '2024-01-01 00:00:00',
        ],
    ];

    $repo = new ImportRepository();
    $families = $repo->allFamilies();

    assertSameValue(true, isset($families['lora']), 'Legacy etch_fonts_imports data should be migrated to the new library format on first read.');
    assertSameValue('Lora', (string) ($families['lora']['family'] ?? ''), 'Migrated family from etch_fonts_imports should retain its original family name.');
};

// ---------------------------------------------------------------------------
// LogRepository – add / clear / prepend behavior
// ---------------------------------------------------------------------------

$tests['log_repository_add_prepends_entries_with_system_actor'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $log->add('First action');
    $log->add('Second action');

    $entries = $log->all();

    assertSameValue(2, count($entries), 'LogRepository should accumulate added entries.');
    assertSameValue('Second action', (string) ($entries[0]['message'] ?? ''), 'LogRepository should prepend entries so the newest appears first.');
    assertSameValue('First action', (string) ($entries[1]['message'] ?? ''), 'LogRepository should keep older entries in subsequent positions.');
    assertSameValue('System', (string) ($entries[0]['actor'] ?? ''), 'LogRepository should label unauthenticated log entries as System.');
};

$tests['log_repository_add_includes_action_link_when_both_label_and_url_are_provided'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $log->add('Imported Inter', [
        'action_label' => 'View library',
        'action_url'   => 'https://example.test/wp-admin/admin.php?page=tasty-fonts',
    ]);

    $entry = $log->all()[0] ?? [];

    assertSameValue('View library', (string) ($entry['action_label'] ?? ''), 'Log entries should include the action label when both label and URL are provided.');
    assertSameValue(true, isset($entry['action_url']) && (string) ($entry['action_url']) !== '', 'Log entries should include the action URL when both label and URL are provided.');
};

$tests['log_repository_add_omits_action_link_when_label_or_url_is_missing'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $log->add('Import without link', ['action_label' => 'View library']);
    $log->add('Import without label', ['action_url'   => 'https://example.test/']);

    $entries = $log->all();

    assertFalseValue(isset($entries[0]['action_label']), 'Log entry should not include action_label when action_url is absent.');
    assertFalseValue(isset($entries[1]['action_label']), 'Log entry should not include action_label when it is absent and action_url is present alone.');
};

$tests['log_repository_add_respects_maximum_entries_limit'] = static function (): void {
    resetTestState();

    $log = new LogRepository();

    // Add one more than the MAX_ENTRIES constant (100).
    for ($i = 1; $i <= 101; $i++) {
        $log->add('Entry ' . $i);
    }

    $entries = $log->all();

    assertSameValue(100, count($entries), 'LogRepository should cap stored entries at the maximum limit of 100.');
    assertSameValue('Entry 101', (string) ($entries[0]['message'] ?? ''), 'LogRepository should keep the most recent entry after truncation.');
    assertSameValue('Entry 2', (string) ($entries[99]['message'] ?? ''), 'LogRepository should discard the oldest entry when the limit is exceeded.');
};

$tests['log_repository_clear_removes_all_stored_entries'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $log->add('Some action');
    $log->add('Another action');
    $log->clear();

    assertSameValue([], $log->all(), 'LogRepository::clear should remove all stored log entries.');
};

// ---------------------------------------------------------------------------
// GoogleImportService – empty family name
// ---------------------------------------------------------------------------

$tests['google_import_service_rejects_empty_family_name_with_wp_error'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();

    $result = $services['google_import']->importFamily('', ['regular']);

    assertTrueValue(is_wp_error($result), 'importFamily should return a WP_Error when the family name is empty.');
    assertSameValue('tasty_fonts_missing_family', $result->get_error_code(), 'importFamily should use the missing_family error code for empty input.');
};

$tests['google_import_service_rejects_whitespace_only_family_name_with_wp_error'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();

    $result = $services['google_import']->importFamily('   ', ['regular']);

    assertTrueValue(is_wp_error($result), 'importFamily should return a WP_Error when the family name is only whitespace.');
};

// ---------------------------------------------------------------------------
// GoogleImportService – skip when all variants already exist
// ---------------------------------------------------------------------------

$tests['google_import_service_skips_import_when_all_requested_variants_already_exist'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();

    // Pre-seed the library with an existing self-hosted profile for Inter 400.
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id'       => 'google-self-hosted',
            'provider' => 'google',
            'type'     => 'self_hosted',
            'variants' => ['regular'],
            'faces'    => [
                [
                    'family'  => 'Inter',
                    'slug'    => 'inter',
                    'source'  => 'google',
                    'weight'  => '400',
                    'style'   => 'normal',
                    'unicode_range' => '',
                    'files'   => ['woff2' => 'google/inter/inter-400-normal.woff2'],
                    'paths'   => [],
                    'provider' => [],
                ],
            ],
            'meta'     => ['category' => 'sans-serif'],
        ],
        'published',
        true
    );

    $result = $services['google_import']->importFamily('Inter', ['regular'], 'self_hosted');

    assertSameValue('skipped', (string) ($result['status'] ?? ''), 'importFamily should return a "skipped" status when all requested variants already exist in the library.');
    assertSameValue('Inter', (string) ($result['family'] ?? ''), 'The skipped result should still include the family name.');
};

// ---------------------------------------------------------------------------
// GoogleImportService – CDN delivery mode
// ---------------------------------------------------------------------------

$tests['google_import_service_saves_cdn_delivery_profile_without_downloading_files'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;
    global $remoteGetCalls;

    $services = makeServiceGraph();
    $cssUrl = $services['google']->buildCssUrl('Inter', ['regular']);
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers'  => ['content-type' => 'text/css'],
        'body'     => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/inter/v18/inter-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];

    $result = $services['google_import']->importFamily('Inter', ['regular'], 'cdn');

    assertSameValue('saved', (string) ($result['status'] ?? ''), 'CDN importFamily should return status "saved" on success.');
    assertSameValue('cdn', (string) ($result['delivery_type'] ?? ''), 'CDN importFamily should record "cdn" as the delivery type.');
    assertSameValue(0, (int) ($result['files'] ?? -1), 'CDN importFamily should not download any font files.');
    assertSameValue(1, (int) ($result['faces'] ?? 0), 'CDN importFamily should count each parsed @font-face block as a face.');

    // Verify no font file download calls were made (only the CSS URL should be fetched).
    $fontFileCalls = array_filter(
        $remoteGetCalls,
        static fn (array $call): bool => str_contains((string) ($call['url'] ?? ''), 'fonts.gstatic.com')
    );
    assertSameValue(0, count($fontFileCalls), 'CDN importFamily should not make any requests to fonts.gstatic.com.');
};

// ---------------------------------------------------------------------------
// GoogleImportService – no CSS faces returned
// ---------------------------------------------------------------------------

$tests['google_import_service_returns_wp_error_when_css_yields_no_usable_faces'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = $services['google']->buildCssUrl('NoSuchFont', ['regular']);
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers'  => ['content-type' => 'text/css'],
        'body'     => '/* no @font-face rules */',
    ];

    $result = $services['google_import']->importFamily('NoSuchFont', ['regular']);

    assertTrueValue(is_wp_error($result), 'importFamily should return a WP_Error when the CSS response contains no usable @font-face rules.');
    assertSameValue('tasty_fonts_google_no_faces', $result->get_error_code(), 'importFamily should use the google_no_faces error code when no faces are parsed.');
};
