<?php

declare(strict_types=1);

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Adobe\AdobeCssParser;
use TastyFonts\Fonts\AdobeCatalogAdapter;
use TastyFonts\Fonts\CatalogBuilder;
use TastyFonts\Fonts\CatalogCache;
use TastyFonts\Fonts\CatalogEnricher;
use TastyFonts\Fonts\CatalogHydrator;
use TastyFonts\Fonts\FontFilenameParser;
use TastyFonts\Fonts\LocalCatalogScanner;
use TastyFonts\Repository\AdobeProjectRepository;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\Storage;
use TastyFonts\Support\TransientKey;

// ---------------------------------------------------------------------------
// CatalogBuilder
// ---------------------------------------------------------------------------

$tests['catalog_builder_build_returns_empty_map_for_empty_sources'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder(
        $imports,
        new LocalCatalogScanner($storage, new FontFilenameParser()),
        new AdobeCatalogAdapter($adobe)
    );

    assertSameValue([], $builder->build(), 'CatalogBuilder should return an empty map when all sources are empty.');
};

$tests['catalog_builder_build_includes_import_based_families'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder(
        $imports,
        new LocalCatalogScanner($storage, new FontFilenameParser()),
        new AdobeCatalogAdapter($adobe)
    );

    $imports->saveFamily([
        'family' => 'Roboto',
        'slug' => 'roboto',
        'publish_state' => 'published',
        'delivery_profiles' => [
            'google_cdn' => [
                'id' => 'google_cdn',
                'provider' => 'google',
                'type' => 'cdn',
                'faces' => [
                    ['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'https://fonts.gstatic.com/roboto-400.woff2']],
                ],
            ],
        ],
    ]);

    $raw = $builder->build();

    assertSameValue(['Roboto'], array_keys($raw), 'CatalogBuilder should include import-based families.');
    assertSameValue('roboto', $raw['Roboto']['slug'], 'CatalogBuilder should preserve the family slug from imports.');
    assertSameValue('published', $raw['Roboto']['publish_state'], 'CatalogBuilder should preserve the publish state from imports.');
    assertTrueValue(isset($raw['Roboto']['delivery_profiles']['google_cdn']), 'CatalogBuilder should preserve delivery profiles from imports.');
};

$tests['catalog_builder_build_merges_local_scanner_synthetic_families'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $storage->ensureRootDirectory();
    $storage->writeAbsoluteFile((string) $storage->pathForRelativePath('loose/Loose-400-normal.woff2'), 'font-data');

    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder(
        $imports,
        new LocalCatalogScanner($storage, new FontFilenameParser()),
        new AdobeCatalogAdapter($adobe)
    );

    $raw = $builder->build();

    assertSameValue(['Loose'], array_keys($raw), 'CatalogBuilder should merge local scanner synthetic families.');
    assertSameValue('library_only', $raw['Loose']['publish_state'], 'Locally-scanned families should default to library_only.');
};

$tests['catalog_builder_build_merges_same_slug_local_synthetic_family_when_files_overlap'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $storage->ensureRootDirectory();
    $sharedPath = 'shared/Shared Slug-400-normal.woff2';
    $storage->writeAbsoluteFile((string) $storage->pathForRelativePath($sharedPath), 'font-data');

    $imports = new ImportRepository();
    $imports->saveFamily([
        'family' => 'Shared Alias',
        'slug' => 'shared-slug',
        'publish_state' => 'published',
        'active_delivery_id' => 'local-self_hosted',
        'delivery_profiles' => [
            'local-self_hosted' => [
                'id' => 'local-self_hosted',
                'provider' => 'local',
                'type' => 'self_hosted',
                'faces' => [
                    [
                        'weight' => '400',
                        'style' => 'normal',
                        'files' => ['woff2' => $sharedPath],
                        'paths' => ['woff2' => $sharedPath],
                    ],
                ],
            ],
        ],
    ]);

    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder(
        $imports,
        new LocalCatalogScanner($storage, new FontFilenameParser()),
        new AdobeCatalogAdapter($adobe)
    );

    $raw = $builder->build();

    assertSameValue(['Shared Alias'], array_keys($raw), 'Families with same slug and shared files should merge into the imported family.');
};

$tests['catalog_builder_build_keeps_same_slug_families_separate_when_files_do_not_overlap'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $storage->ensureRootDirectory();
    $storage->writeAbsoluteFile((string) $storage->pathForRelativePath('local/Same Slug-400-normal.woff2'), 'font-data');

    $imports = new ImportRepository();
    $imports->saveFamily([
        'family' => 'Import Alias',
        'slug' => 'same-slug',
        'publish_state' => 'published',
        'delivery_profiles' => [
            'local-self_hosted' => [
                'id' => 'local-self_hosted',
                'provider' => 'local',
                'type' => 'self_hosted',
                'faces' => [
                    [
                        'weight' => '400',
                        'style' => 'normal',
                        'files' => ['woff2' => 'imports/Import Alias-400-normal.woff2'],
                        'paths' => ['woff2' => 'imports/Import Alias-400-normal.woff2'],
                    ],
                ],
            ],
        ],
    ]);

    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder(
        $imports,
        new LocalCatalogScanner($storage, new FontFilenameParser()),
        new AdobeCatalogAdapter($adobe)
    );

    $raw = $builder->build();

    assertSameValue(['Import Alias', 'Same Slug'], array_keys($raw), 'Families with same slug but different files should remain separate.');
};

$tests['catalog_builder_build_merges_adobe_profile_when_import_family_name_matches'] = static function (): void {
    resetTestState();

    global $transientStore;

    $storage = new Storage();
    $imports = new ImportRepository();
    $settings = new SettingsRepository();
    $adobeRepo = new AdobeProjectRepository();
    $adobeRepo->saveProject('def456', true);
    $adobeRepo->saveStatus('valid', 'ok');

    $transientStore[TransientKey::forSite(AdobeProjectClient::TRANSIENT_PREFIX . md5('def456'))] = [
        'project_id' => 'def456',
        'stylesheet_url' => 'https://use.typekit.net/def456.css',
        'families' => [
            [
                'family' => 'Adobe Merge',
                'slug' => 'adobe-merge',
                'faces' => [
                    ['weight' => '400', 'style' => 'normal'],
                ],
            ],
        ],
        'fetched_at' => time(),
    ];

    $imports->saveFamily([
        'family' => 'Adobe Merge',
        'slug' => 'adobe-merge',
        'publish_state' => 'published',
        'delivery_profiles' => [
            'google_cdn' => [
                'id' => 'google_cdn',
                'provider' => 'google',
                'type' => 'cdn',
                'faces' => [
                    ['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'https://fonts.gstatic.com/adobe-merge.woff2']],
                ],
            ],
        ],
    ]);

    $builder = new CatalogBuilder(
        $imports,
        new LocalCatalogScanner($storage, new FontFilenameParser()),
        new AdobeCatalogAdapter(new AdobeProjectClient($settings, $adobeRepo, new AdobeCssParser()))
    );

    $raw = $builder->build();

    assertSameValue(['Adobe Merge'], array_keys($raw), 'Matching import and Adobe family names should stay merged into one family.');
    assertTrueValue(isset($raw['Adobe Merge']['delivery_profiles']['google_cdn']), 'Merged family should keep the import delivery profile.');
    assertTrueValue(isset($raw['Adobe Merge']['delivery_profiles']['adobe-adobe_hosted']), 'Merged family should include Adobe synthetic delivery profile.');
};

$tests['catalog_builder_prune_undeliverable_families_removes_families_with_zero_profiles'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder(
        $imports,
        new LocalCatalogScanner($storage, new FontFilenameParser()),
        new AdobeCatalogAdapter($adobe)
    );

    $imports->saveFamily([
        'family' => 'EmptyFamily',
        'slug' => 'empty-family',
        'publish_state' => 'published',
        'delivery_profiles' => [],
    ]);

    $raw = $builder->build();

    assertSameValue([], $raw, 'CatalogBuilder should prune families with no delivery profiles.');
};

// ---------------------------------------------------------------------------
// CatalogHydrator
// ---------------------------------------------------------------------------

$tests['catalog_hydrator_hydrate_resolves_self_hosted_file_urls'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $storage->ensureRootDirectory();
    $storage->writeAbsoluteFile((string) $storage->pathForRelativePath('test/Test-400-normal.woff2'), 'font-data');

    $hydrator = new CatalogHydrator($storage);

    $raw = [
        'Test' => [
            'family' => 'Test',
            'slug' => 'test',
            'publish_state' => 'published',
            'active_delivery_id' => 'local',
            'delivery_profiles' => [
                'local' => [
                    'id' => 'local',
                    'provider' => 'local',
                    'type' => 'self_hosted',
                    'faces' => [
                        [
                            'weight' => '400',
                            'style' => 'normal',
                            'files' => ['woff2' => 'test/Test-400-normal.woff2'],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $hydrated = $hydrator->hydrate($raw);
    $face = $hydrated['Test']['delivery_profiles']['local']['faces'][0] ?? [];

    assertSameValue($storage->urlForRelativePath('test/Test-400-normal.woff2'), $face['files']['woff2'] ?? '', 'CatalogHydrator should resolve self-hosted relative paths to public URLs.');
    assertSameValue('test/Test-400-normal.woff2', $face['paths']['woff2'] ?? '', 'CatalogHydrator should preserve the relative path in the paths map.');
};

$tests['catalog_hydrator_hydrate_preserves_remote_urls'] = static function (): void {
    resetTestState();

    $hydrator = new CatalogHydrator(new Storage());

    $raw = [
        'Remote' => [
            'family' => 'Remote',
            'slug' => 'remote',
            'publish_state' => 'published',
            'active_delivery_id' => 'google_cdn',
            'delivery_profiles' => [
                'google_cdn' => [
                    'id' => 'google_cdn',
                    'provider' => 'google',
                    'type' => 'cdn',
                    'faces' => [
                        [
                            'weight' => '400',
                            'style' => 'normal',
                            'files' => ['woff2' => 'https://fonts.gstatic.com/s/remote/v1/400.woff2'],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $hydrated = $hydrator->hydrate($raw);
    $face = $hydrated['Remote']['delivery_profiles']['google_cdn']['faces'][0] ?? [];

    assertSameValue('https://fonts.gstatic.com/s/remote/v1/400.woff2', $face['files']['woff2'] ?? '', 'CatalogHydrator should preserve remote URLs in the files map.');
};

$tests['catalog_hydrator_hydrate_normalizes_face_weight_and_style'] = static function (): void {
    resetTestState();

    $hydrator = new CatalogHydrator(new Storage());

    $raw = [
        'Norm' => [
            'family' => 'Norm',
            'slug' => 'norm',
            'publish_state' => 'published',
            'active_delivery_id' => 'p',
            'delivery_profiles' => [
                'p' => [
                    'id' => 'p',
                    'provider' => 'local',
                    'type' => 'self_hosted',
                    'faces' => [
                        ['weight' => 'bold', 'style' => 'ITALIC'],
                    ],
                ],
            ],
        ],
    ];

    $hydrated = $hydrator->hydrate($raw);
    $face = $hydrated['Norm']['delivery_profiles']['p']['faces'][0] ?? [];

    assertSameValue('bold', $face['weight'] ?? '', 'CatalogHydrator should preserve named weight values like bold.');
    assertSameValue('italic', $face['style'] ?? '', 'CatalogHydrator should normalize style to lowercase.');
};

$tests['catalog_hydrator_hydrate_detects_variable_faces_from_axes'] = static function (): void {
    resetTestState();

    $hydrator = new CatalogHydrator(new Storage());

    $raw = [
        'Var' => [
            'family' => 'Var',
            'slug' => 'var',
            'publish_state' => 'published',
            'active_delivery_id' => 'p',
            'delivery_profiles' => [
                'p' => [
                    'id' => 'p',
                    'provider' => 'local',
                    'type' => 'self_hosted',
                    'faces' => [
                        [
                            'weight' => '400',
                            'style' => 'normal',
                            'axes' => [
                                'wght' => ['tag' => 'wght', 'min' => '100', 'max' => '900', 'default' => '400'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $hydrated = $hydrator->hydrate($raw);
    $face = $hydrated['Var']['delivery_profiles']['p']['faces'][0] ?? [];

    assertTrueValue(!empty($face['is_variable']), 'CatalogHydrator should mark faces as variable when axes are present.');
    assertTrueValue(isset($face['axes']['WGHT']), 'CatalogHydrator should preserve axis definitions with normalized uppercase tag.');
};

$tests['catalog_hydrator_hydrate_preserves_explicit_variable_multi_format'] = static function (): void {
    resetTestState();

    $hydrator = new CatalogHydrator(new Storage());

    $raw = [
        'VariableMulti' => [
            'family' => 'VariableMulti',
            'slug' => 'variable-multi',
            'publish_state' => 'published',
            'active_delivery_id' => 'p',
            'delivery_profiles' => [
                'p' => [
                    'id' => 'p',
                    'provider' => 'local',
                    'type' => 'self_hosted',
                    'format' => 'variable-multi',
                    'faces' => [
                        ['weight' => '400', 'style' => 'normal', 'is_variable' => true, 'axes' => ['wght' => ['min' => '100', 'max' => '900']]],
                    ],
                ],
            ],
        ],
    ];

    $hydrated = $hydrator->hydrate($raw);

    assertSameValue('variable-multi', $hydrated['VariableMulti']['delivery_profiles']['p']['format'] ?? '', 'CatalogHydrator should preserve explicit variable-multi formats for enrichment.');
};

$tests['catalog_hydrator_hydrate_handles_missing_keys_with_defaults'] = static function (): void {
    resetTestState();

    $hydrator = new CatalogHydrator(new Storage());

    $raw = [
        'Minimal' => [
            'family' => 'Minimal',
            'slug' => 'minimal',
            'publish_state' => 'published',
            'active_delivery_id' => '',
            'delivery_profiles' => [
                'p' => [
                    'id' => 'p',
                    'provider' => 'local',
                    'type' => 'self_hosted',
                    'faces' => [
                        ['weight' => ''],
                    ],
                ],
            ],
        ],
    ];

    $hydrated = $hydrator->hydrate($raw);
    $face = $hydrated['Minimal']['delivery_profiles']['p']['faces'][0] ?? [];

    assertSameValue('400', $face['weight'] ?? '', 'CatalogHydrator should default missing/empty weight to 400.');
    assertSameValue('normal', $face['style'] ?? '', 'CatalogHydrator should default missing style to normal.');
    assertSameValue('', $face['unicode_range'] ?? 'not-set', 'CatalogHydrator should default missing unicode_range to empty string.');
    assertSameValue([], $face['axes'] ?? 'not-set', 'CatalogHydrator should default missing axes to empty array.');
};

// ---------------------------------------------------------------------------
// CatalogEnricher
// ---------------------------------------------------------------------------

$tests['catalog_enricher_enrich_computes_delivery_badges'] = static function (): void {
    $enricher = new CatalogEnricher();

    $hydrated = [
        'Test' => [
            'family' => 'Test',
            'slug' => 'test',
            'publish_state' => 'published',
            'active_delivery_id' => 'local',
            'delivery_profiles' => [
                'local' => [
                    'id' => 'local',
                    'provider' => 'local',
                    'type' => 'self_hosted',
                    'faces' => [
                        ['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => '/path.woff2']],
                    ],
                ],
            ],
        ],
    ];

    $enriched = $enricher->enrich($hydrated);
    $badges = $enriched['Test']['delivery_badges'] ?? [];

    assertSameValue(2, count($badges), 'CatalogEnricher should produce exactly two badges.');
    assertSameValue('Published', $badges[0]['label'] ?? '', 'CatalogEnricher should produce a Published badge for published families.');
    assertSameValue('Self-hosted', $badges[1]['label'] ?? '', 'CatalogEnricher should produce a Self-hosted badge for self-hosted deliveries.');
};

$tests['catalog_enricher_enrich_computes_filter_tokens'] = static function (): void {
    $enricher = new CatalogEnricher();

    $hydrated = [
        'GoogleCdn' => [
            'family' => 'GoogleCdn',
            'slug' => 'google-cdn',
            'publish_state' => 'published',
            'active_delivery_id' => 'g',
            'delivery_profiles' => [
                'g' => [
                    'id' => 'g',
                    'provider' => 'google',
                    'type' => 'cdn',
                    'faces' => [
                        ['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'https://example.com/f.woff2']],
                    ],
                ],
            ],
        ],
    ];

    $enriched = $enricher->enrich($hydrated);
    $tokens = $enriched['GoogleCdn']['delivery_filter_tokens'] ?? [];

    assertTrueValue(in_array('published', $tokens, true), 'CatalogEnricher should include published token.');
    assertTrueValue(in_array('external-request', $tokens, true), 'CatalogEnricher should include external-request token for CDN deliveries.');
    assertTrueValue(in_array('google-cdn', $tokens, true), 'CatalogEnricher should include google-cdn token for Google CDN deliveries.');
};

$tests['catalog_enricher_enrich_computes_family_category'] = static function (): void {
    $enricher = new CatalogEnricher();

    $hydrated = [
        'Slab' => [
            'family' => 'Slab',
            'slug' => 'slab',
            'publish_state' => 'published',
            'active_delivery_id' => 'p',
            'delivery_profiles' => [
                'p' => [
                    'id' => 'p',
                    'provider' => 'google',
                    'type' => 'cdn',
                    'meta' => ['category' => 'Slab Serif'],
                    'faces' => [
                        ['weight' => '400', 'style' => 'normal', 'files' => []],
                    ],
                ],
            ],
        ],
    ];

    $enriched = $enricher->enrich($hydrated);

    assertSameValue('slab-serif', $enriched['Slab']['font_category'] ?? '', 'CatalogEnricher should resolve slab-serif category from meta.');
    assertTrueValue(in_array('slab-serif', $enriched['Slab']['font_category_tokens'] ?? [], true), 'CatalogEnricher should include slab-serif in category tokens.');
    assertTrueValue(in_array('serif', $enriched['Slab']['font_category_tokens'] ?? [], true), 'CatalogEnricher should include serif as a fallback token for slab-serif.');
};

$tests['catalog_enricher_enrich_computes_formats'] = static function (): void {
    $enricher = new CatalogEnricher();

    $hydrated = [
        'Mixed' => [
            'family' => 'Mixed',
            'slug' => 'mixed',
            'publish_state' => 'published',
            'active_delivery_id' => 'p',
            'delivery_profiles' => [
                'p' => [
                    'id' => 'p',
                    'provider' => 'local',
                    'type' => 'self_hosted',
                    'format' => 'static',
                    'faces' => [
                        ['weight' => '400', 'style' => 'normal', 'files' => []],
                    ],
                ],
            ],
        ],
    ];

    $enriched = $enricher->enrich($hydrated);
    $formats = $enriched['Mixed']['formats'] ?? [];

    assertTrueValue(isset($formats['static']), 'CatalogEnricher should compute static format for static profiles.');
    assertSameValue('Static', $formats['static']['label'] ?? '', 'CatalogEnricher should label static format correctly.');
};

$tests['catalog_enricher_enrich_formats_include_variable_multi_when_profile_declares_it'] = static function (): void {
    $enricher = new CatalogEnricher();

    $hydrated = [
        'VariableMulti' => [
            'family' => 'VariableMulti',
            'slug' => 'variable-multi',
            'publish_state' => 'published',
            'active_delivery_id' => 'p',
            'delivery_profiles' => [
                'p' => [
                    'id' => 'p',
                    'provider' => 'local',
                    'type' => 'self_hosted',
                    'format' => 'variable-multi',
                    'faces' => [
                        [
                            'weight' => '400',
                            'style' => 'normal',
                            'is_variable' => true,
                            'axes' => ['wght' => ['min' => '100', 'max' => '900']],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $enriched = $enricher->enrich($hydrated);
    $formats = $enriched['VariableMulti']['formats'] ?? [];

    assertTrueValue(isset($formats['variable-multi']), 'CatalogEnricher should expose PRD variable-multi format when a profile declares it.');
    assertSameValue('Variable Multi', $formats['variable-multi']['label'] ?? '', 'CatalogEnricher should label variable-multi format clearly.');
};

$tests['catalog_enricher_enrich_detects_variable_and_static_faces'] = static function (): void {
    $enricher = new CatalogEnricher();

    $hydrated = [
        'Both' => [
            'family' => 'Both',
            'slug' => 'both',
            'publish_state' => 'published',
            'active_delivery_id' => 'p',
            'delivery_profiles' => [
                'p' => [
                    'id' => 'p',
                    'provider' => 'local',
                    'type' => 'self_hosted',
                    'faces' => [
                        ['weight' => '400', 'style' => 'normal', 'files' => [], 'is_variable' => true, 'axes' => ['wght' => ['min' => '100', 'max' => '900']]],
                        ['weight' => '700', 'style' => 'normal', 'files' => []],
                    ],
                ],
            ],
        ],
    ];

    $enriched = $enricher->enrich($hydrated);

    assertTrueValue($enriched['Both']['has_variable_faces'], 'CatalogEnricher should detect variable faces.');
    assertTrueValue($enriched['Both']['has_static_faces'], 'CatalogEnricher should detect static faces.');
    assertTrueValue(in_array('variable', $enriched['Both']['font_category_tokens'] ?? [], true), 'CatalogEnricher should add variable token when variable faces exist.');
};

$tests['catalog_enricher_enrich_single_enriches_one_family'] = static function (): void {
    $enricher = new CatalogEnricher();

    $family = [
        'family' => 'Single',
        'slug' => 'single',
        'publish_state' => 'library_only',
        'active_delivery_id' => 'p',
        'delivery_profiles' => [
            'p' => [
                'id' => 'p',
                'provider' => 'google',
                'type' => 'cdn',
                'faces' => [
                    ['weight' => '400', 'style' => 'normal', 'files' => []],
                ],
            ],
        ],
    ];

    $enriched = $enricher->enrichSingle($family);

    assertSameValue('In Library Only', $enriched['delivery_badges'][0]['label'] ?? '', 'enrichSingle should compute correct badge for library_only state.');
    assertTrueValue(isset($enriched['delivery_filter_tokens']), 'enrichSingle should compute filter tokens.');
    assertTrueValue(isset($enriched['font_category']), 'enrichSingle should compute font category.');
};

$tests['catalog_enricher_delivery_profile_rank_orders_correctly'] = static function (): void {
    $enricher = new CatalogEnricher();

    $profiles = [
        ['id' => 'google_cdn', 'provider' => 'google', 'type' => 'cdn', 'label' => 'Google CDN'],
        ['id' => 'local', 'provider' => 'local', 'type' => 'self_hosted', 'label' => 'Local'],
        ['id' => 'adobe', 'provider' => 'adobe', 'type' => 'adobe_hosted', 'label' => 'Adobe'],
    ];

    $hydrated = [
        'Rank' => [
            'family' => 'Rank',
            'slug' => 'rank',
            'publish_state' => 'published',
            'active_delivery_id' => 'google_cdn',
            'delivery_profiles' => $profiles,
        ],
    ];

    $enriched = $enricher->enrich($hydrated);
    $available = $enriched['Rank']['available_deliveries'] ?? [];
    $ids = array_map(static fn (array $p): string => $p['id'] ?? '', $available);

    assertSameValue(['local', 'google_cdn', 'adobe'], $ids, 'CatalogEnricher should rank deliveries in the expected order: local, google_cdn, adobe.');
};

// ---------------------------------------------------------------------------
// CatalogCache
// ---------------------------------------------------------------------------

$tests['catalog_cache_getCatalog_returns_cached_catalog_on_transient_hit'] = static function (): void {
    resetTestState();

    global $transientStore;

    $transientStore[TransientKey::forSite(CatalogCache::TRANSIENT_CATALOG)] = [
        'families' => [
            'Cached' => [
                'family' => 'Cached',
                'slug' => 'cached',
                'publish_state' => 'published',
                'active_delivery_id' => 'p',
                'active_delivery' => ['id' => 'p', 'provider' => 'local', 'type' => 'self_hosted'],
                'available_deliveries' => [],
                'delivery_profiles' => [
                    'p' => [
                        'id' => 'p',
                        'provider' => 'local',
                        'type' => 'self_hosted',
                        'faces' => [],
                    ],
                ],
                'sources' => ['local'],
                'faces' => [],
                'delivery_badges' => [],
                'delivery_filter_tokens' => ['published', 'same-origin'],
                'font_category' => 'sans-serif',
                'font_category_tokens' => ['sans-serif'],
                'formats' => ['static' => ['label' => 'Static', 'available' => true, 'source_only' => false]],
                'has_static_faces' => true,
                'has_variable_faces' => false,
                'variation_axes' => [],
            ],
        ],
        'counts' => [
            'families' => 1,
            'files' => 0,
            'published_families' => 1,
            'library_only_families' => 0,
            'local_families' => 1,
            'remote_families' => 0,
        ],
    ];

    $storage = new Storage();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder($imports, new LocalCatalogScanner($storage, new FontFilenameParser()), new AdobeCatalogAdapter($adobe));
    $cache = new CatalogCache($builder, new CatalogHydrator($storage), new CatalogEnricher(), $storage, $log);

    $catalog = $cache->getCatalog();

    assertSameValue(['Cached'], array_keys($catalog), 'CatalogCache should return the cached catalog when the transient is valid.');
    assertSameValue('cached', $catalog['Cached']['slug'] ?? '', 'CatalogCache should preserve cached family data.');
};

$tests['catalog_cache_getCatalog_builds_fresh_catalog_when_transient_is_expired'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder($imports, new LocalCatalogScanner($storage, new FontFilenameParser()), new AdobeCatalogAdapter($adobe));
    $cache = new CatalogCache($builder, new CatalogHydrator($storage), new CatalogEnricher(), $storage, $log);

    $imports->saveFamily([
        'family' => 'Fresh',
        'slug' => 'fresh',
        'publish_state' => 'published',
        'delivery_profiles' => [
            'p' => [
                'id' => 'p',
                'provider' => 'local',
                'type' => 'self_hosted',
                'faces' => [
                    ['weight' => '400', 'style' => 'normal', 'files' => []],
                ],
            ],
        ],
    ]);

    $catalog = $cache->getCatalog();

    assertSameValue(['Fresh'], array_keys($catalog), 'CatalogCache should build a fresh catalog when the transient is missing.');
};

$tests['catalog_cache_getCatalog_rebuilds_when_cached_data_fails_schema_validation'] = static function (): void {
    resetTestState();

    global $transientStore;

    $transientStore[TransientKey::forSite(CatalogCache::TRANSIENT_CATALOG)] = [
        'families' => [
            'Broken' => [
                'family' => 'Broken',
                'slug' => 'broken',
                'publish_state' => 'published',
            ],
        ],
        'counts' => ['families' => 1, 'files' => 0, 'published_families' => 1, 'library_only_families' => 0, 'local_families' => 1, 'remote_families' => 0],
    ];

    $storage = new Storage();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder($imports, new LocalCatalogScanner($storage, new FontFilenameParser()), new AdobeCatalogAdapter($adobe));
    $cache = new CatalogCache($builder, new CatalogHydrator($storage), new CatalogEnricher(), $storage, $log);

    $imports->saveFamily([
        'family' => 'Valid',
        'slug' => 'valid',
        'publish_state' => 'published',
        'delivery_profiles' => [
            'p' => [
                'id' => 'p',
                'provider' => 'local',
                'type' => 'self_hosted',
                'faces' => [
                    ['weight' => '400', 'style' => 'normal', 'files' => []],
                ],
            ],
        ],
    ]);

    $catalog = $cache->getCatalog();

    assertSameValue(['Valid'], array_keys($catalog), 'CatalogCache should rebuild when cached data fails schema validation.');
};

$tests['catalog_cache_getCatalog_applies_catalog_filter_and_recomputes_counts_from_filtered_families'] = static function (): void {
    resetTestState();

    add_filter('tasty_fonts_catalog', static function (array $catalog): array {
        $filtered = $catalog;
        unset($filtered['FilteredOut']);

        return $filtered;
    });

    $storage = new Storage();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder($imports, new LocalCatalogScanner($storage, new FontFilenameParser()), new AdobeCatalogAdapter($adobe));
    $cache = new CatalogCache($builder, new CatalogHydrator($storage), new CatalogEnricher(), $storage, $log);

    $imports->saveFamily([
        'family' => 'KeepMe',
        'slug' => 'keep-me',
        'publish_state' => 'published',
        'delivery_profiles' => [
            'p' => [
                'id' => 'p',
                'provider' => 'local',
                'type' => 'self_hosted',
                'faces' => [['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'keep.woff2']]],
            ],
        ],
    ]);

    $imports->saveFamily([
        'family' => 'FilteredOut',
        'slug' => 'filtered-out',
        'publish_state' => 'published',
        'delivery_profiles' => [
            'p' => [
                'id' => 'p',
                'provider' => 'local',
                'type' => 'self_hosted',
                'faces' => [['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'drop.woff2']]],
            ],
        ],
    ]);

    $catalog = $cache->getCatalog();
    $counts = $cache->getCounts();

    assertSameValue(['KeepMe'], array_keys($catalog), 'Catalog filter should run on returned enriched catalog entries.');
    assertSameValue(1, $counts['families'] ?? 0, 'Counts should be recomputed from filtered catalog families.');
    assertSameValue(1, $counts['files'] ?? 0, 'Filtered family files should not be counted after filter application.');
};

$tests['catalog_cache_getCatalog_rejects_cached_families_without_deleting_matching_imports'] = static function (): void {
    resetTestState();

    global $transientStore;

    $transientStore[TransientKey::forSite(CatalogCache::TRANSIENT_CATALOG)] = [
        'families' => [
            'CachedNoDelivery' => [
                'family' => 'CachedNoDelivery',
                'slug' => 'cached-no-delivery',
                'publish_state' => 'published',
                'delivery_filter_tokens' => ['published'],
                'font_category' => 'sans-serif',
                'font_category_tokens' => ['sans-serif'],
            ],
        ],
        'counts' => ['families' => 1, 'files' => 0, 'published_families' => 1, 'library_only_families' => 0, 'local_families' => 1, 'remote_families' => 0],
    ];

    $storage = new Storage();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder($imports, new LocalCatalogScanner($storage, new FontFilenameParser()), new AdobeCatalogAdapter($adobe));
    $cache = new CatalogCache($builder, new CatalogHydrator($storage), new CatalogEnricher(), $storage, $log);

    $imports->saveFamily([
        'family' => 'CachedNoDelivery',
        'slug' => 'cached-no-delivery',
        'publish_state' => 'published',
        'delivery_profiles' => [
            'p' => [
                'id' => 'p',
                'provider' => 'local',
                'type' => 'self_hosted',
                'faces' => [['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'safe.woff2']]],
            ],
        ],
    ]);

    $catalog = $cache->getCatalog();

    assertSameValue(['CachedNoDelivery'], array_keys($catalog), 'Cached families without delivery data should force a rebuild from imports.');
    assertTrueValue(is_array($imports->getFamily('cached-no-delivery')), 'Rejecting malformed cache data must not delete the stored import record.');
};

$tests['catalog_cache_getCatalog_rejects_cached_families_with_malformed_shape_even_when_counts_present'] = static function (): void {
    resetTestState();

    global $transientStore;

    $transientStore[TransientKey::forSite(CatalogCache::TRANSIENT_CATALOG)] = [
        'families' => [
            'BrokenShape' => [
                'family' => 'BrokenShape',
                'slug' => 'broken-shape',
                'publish_state' => 'published',
                'delivery_filter_tokens' => ['published'],
            ],
        ],
        'counts' => ['families' => 999, 'files' => 'nope', 'published_families' => 999],
    ];

    $storage = new Storage();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder($imports, new LocalCatalogScanner($storage, new FontFilenameParser()), new AdobeCatalogAdapter($adobe));
    $cache = new CatalogCache($builder, new CatalogHydrator($storage), new CatalogEnricher(), $storage, $log);

    $imports->saveFamily([
        'family' => 'FallbackFromBuild',
        'slug' => 'fallback-from-build',
        'publish_state' => 'published',
        'delivery_profiles' => [
            'p' => [
                'id' => 'p',
                'provider' => 'local',
                'type' => 'self_hosted',
                'faces' => [['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'one.woff2']]],
            ],
        ],
    ]);

    $catalog = $cache->getCatalog();
    $counts = $cache->getCounts();

    assertSameValue(['FallbackFromBuild'], array_keys($catalog), 'Malformed cached family shape should force rebuild instead of being hydrated.');
    assertSameValue(1, $counts['families'] ?? 0, 'Rebuild should recompute counts instead of trusting malformed cached counts.');
};

$tests['catalog_cache_getCatalog_rejects_cached_catalog_with_malformed_counts_payload'] = static function (): void {
    resetTestState();

    global $transientStore;

    $transientStore[TransientKey::forSite(CatalogCache::TRANSIENT_CATALOG)] = [
        'families' => [
            'Cached' => [
                'family' => 'Cached',
                'slug' => 'cached',
                'publish_state' => 'published',
                'active_delivery_id' => 'p',
                'active_delivery' => ['id' => 'p', 'provider' => 'local', 'type' => 'self_hosted'],
                'available_deliveries' => [],
                'delivery_profiles' => [
                    'p' => [
                        'id' => 'p',
                        'provider' => 'local',
                        'type' => 'self_hosted',
                        'faces' => [],
                    ],
                ],
                'sources' => ['local'],
                'faces' => [],
                'delivery_badges' => [],
                'delivery_filter_tokens' => ['published', 'same-origin'],
                'font_category' => 'sans-serif',
                'font_category_tokens' => ['sans-serif'],
                'formats' => ['static' => ['label' => 'Static', 'available' => true, 'source_only' => false]],
                'has_static_faces' => true,
                'has_variable_faces' => false,
                'variation_axes' => [],
            ],
        ],
        'counts' => [
            'families' => 'bad',
            'files' => 'bad',
            'published_families' => 'bad',
            'library_only_families' => 'bad',
            'local_families' => 'bad',
            'remote_families' => 'bad',
        ],
    ];

    $storage = new Storage();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder($imports, new LocalCatalogScanner($storage, new FontFilenameParser()), new AdobeCatalogAdapter($adobe));
    $cache = new CatalogCache($builder, new CatalogHydrator($storage), new CatalogEnricher(), $storage, $log);

    $imports->saveFamily([
        'family' => 'FreshAfterBadCounts',
        'slug' => 'fresh-after-bad-counts',
        'publish_state' => 'published',
        'delivery_profiles' => [
            'p' => [
                'id' => 'p',
                'provider' => 'local',
                'type' => 'self_hosted',
                'faces' => [['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'fresh.woff2']]],
            ],
        ],
    ]);

    $catalog = $cache->getCatalog();
    $counts = $cache->getCounts();

    assertSameValue(['FreshAfterBadCounts'], array_keys($catalog), 'Malformed cached counts payload should force a rebuild instead of using cached families.');
    assertSameValue(1, $counts['families'] ?? 0, 'Rebuilt catalog should recompute counts after rejecting malformed cached counts.');
};

$tests['catalog_cache_invalidate_deletes_transient'] = static function (): void {
    resetTestState();

    global $transientStore, $transientDeleted;

    $transientStore[TransientKey::forSite(CatalogCache::TRANSIENT_CATALOG)] = ['families' => [], 'counts' => []];

    $storage = new Storage();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder($imports, new LocalCatalogScanner($storage, new FontFilenameParser()), new AdobeCatalogAdapter($adobe));
    $cache = new CatalogCache($builder, new CatalogHydrator($storage), new CatalogEnricher(), $storage, $log);

    $cache->invalidate();

    assertTrueValue(in_array(TransientKey::forSite(CatalogCache::TRANSIENT_CATALOG), $transientDeleted, true), 'CatalogCache::invalidate() should delete the catalog transient.');
};

$tests['catalog_cache_getCounts_returns_correct_counts'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder($imports, new LocalCatalogScanner($storage, new FontFilenameParser()), new AdobeCatalogAdapter($adobe));
    $cache = new CatalogCache($builder, new CatalogHydrator($storage), new CatalogEnricher(), $storage, $log);

    $imports->saveFamily([
        'family' => 'CountTest',
        'slug' => 'count-test',
        'publish_state' => 'published',
        'delivery_profiles' => [
            'p' => [
                'id' => 'p',
                'provider' => 'local',
                'type' => 'self_hosted',
                'faces' => [
                    ['weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'test.woff2']],
                ],
            ],
        ],
    ]);

    $counts = $cache->getCounts();

    assertSameValue(1, $counts['families'] ?? 0, 'getCounts should return the correct family count.');
    assertSameValue(1, $counts['files'] ?? 0, 'getCounts should return the correct file count.');
    assertSameValue(1, $counts['published_families'] ?? 0, 'getCounts should return the correct published family count.');
    assertSameValue(0, $counts['library_only_families'] ?? 0, 'getCounts should return the correct library-only family count.');
    assertSameValue(1, $counts['local_families'] ?? 0, 'getCounts should return the correct local family count.');
    assertSameValue(0, $counts['remote_families'] ?? 0, 'getCounts should return the correct remote family count.');
};

$tests['catalog_cache_maybeInvalidateFromAttachment_invalidate_only_for_font_attachments'] = static function (): void {
    resetTestState();

    global $transientStore, $transientDeleted;

    $transientStore[TransientKey::forSite(CatalogCache::TRANSIENT_CATALOG)] = ['families' => [], 'counts' => []];

    $storage = new Storage();
    $storage->ensureRootDirectory();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $settings = new SettingsRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $builder = new CatalogBuilder($imports, new LocalCatalogScanner($storage, new FontFilenameParser()), new AdobeCatalogAdapter($adobe));
    $cache = new CatalogCache($builder, new CatalogHydrator($storage), new CatalogEnricher(), $storage, $log);

    $transientDeleted = [];
    $cache->maybeInvalidateFromAttachment(9999);

    assertFalseValue(in_array(TransientKey::forSite(CatalogCache::TRANSIENT_CATALOG), $transientDeleted, true), 'maybeInvalidateFromAttachment should skip attachments outside the font storage root.');
};
