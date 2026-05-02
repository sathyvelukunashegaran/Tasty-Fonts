<?php

declare(strict_types=1);

use TastyFonts\Adobe\AdobeCssParser;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Repository\AdobeProjectRepository;
use TastyFonts\Repository\GoogleApiKeyRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\TransientKey;

$tests['google_fonts_client_builds_variable_css2_urls_when_axes_are_available'] = static function (): void {
    $settings = new SettingsRepository();
    $client = new GoogleFontsClient($settings, new GoogleApiKeyRepository());

    assertSameValue(
        'https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap',
        $client->buildCssUrl(
            'Inter',
            ['regular', 'italic'],
            'swap',
            [
                'axes' => [
                    'OPSZ' => ['min' => '14', 'default' => '14', 'max' => '32'],
                    'WGHT' => ['min' => '100', 'default' => '400', 'max' => '900'],
                ],
            ]
            ,
            'variable'
        ),
        'Google variable CSS URLs should preserve the style axis and available variation ranges.'
    );
};

$tests['google_fonts_client_preserves_custom_axis_case_in_variable_css2_urls'] = static function (): void {
    $settings = new SettingsRepository();
    $client = new GoogleFontsClient($settings, new GoogleApiKeyRepository());

    assertSameValue(
        'https://fonts.googleapis.com/css2?family=AR+One+Sans:ARRR,wght@10..60,400..700&display=swap',
        $client->buildCssUrl(
            'AR One Sans',
            ['regular'],
            'swap',
            [
                'axes' => [
                    'ARRR' => ['min' => '10', 'default' => '10', 'max' => '60'],
                    'WGHT' => ['min' => '400', 'default' => '400', 'max' => '700'],
                ],
            ],
            'variable'
        ),
        'Google variable CSS URLs should keep custom axes uppercase while mapping registered axes to lowercase.'
    );
};

$tests['bunny_fonts_client_builds_css2_urls'] = static function (): void {
    $client = new BunnyFontsClient();

    assertSameValue(
        'https://fonts.bunny.net/css2?family=Inter:ital,wght@0,400;1,700&display=swap',
        $client->buildCssUrl('Inter', ['regular', '700italic']),
        'Bunny Fonts CSS URLs should use the css2 endpoint and Google-compatible axis syntax.'
    );
};

$tests['bunny_fonts_client_searches_sitemap_catalog_and_hydrates_family_details'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $client = new BunnyFontsClient();
    $remoteGetResponses['https://fonts.bunny.net/sitemap.xml'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'application/xml'],
        'body' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://fonts.bunny.net/family/inter</loc></url>
  <url><loc>https://fonts.bunny.net/family/ibm-plex-sans</loc></url>
</urlset>
XML,
    ];
    $remoteGetResponses['https://fonts.bunny.net/family/inter'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/html'],
        'body' => <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Inter | Bunny Fonts</title>
</head>
<body>
    <div class="family"><h3>Sans Serif</h3></div>
    <div class="styles">18 styles</div>
    <div class="card-main"><h1>Inter</h1></div>
    <p class="license-paragraph">Inter-Italic[opsz,wght].ttf</p>
    <link href="https://fonts.bunny.net/css?family=inter:100,400,700,400i,700i," rel="stylesheet" />
</body>
</html>
HTML,
    ];

    $results = $client->searchFamilies('int', 5);
    $first = $results[0] ?? [];

    assertSameValue(1, count($results), 'Bunny search should filter sitemap entries by the query before hydrating family details.');
    assertSameValue('Inter', (string) ($first['family'] ?? ''), 'Bunny search should hydrate the exact family name from the public family page.');
    assertSameValue('inter', (string) ($first['slug'] ?? ''), 'Bunny search should preserve the Bunny family slug.');
    assertSameValue('sans-serif', (string) ($first['category'] ?? ''), 'Bunny search should normalize public category labels for preview usage.');
    assertSameValue('Sans Serif', (string) ($first['category_label'] ?? ''), 'Bunny search should keep the display category label for the admin cards.');
    assertSameValue(18, (int) ($first['style_count'] ?? 0), 'Bunny search should expose the public style count for the family card.');
    assertSameValue(false, !empty($first['is_variable']), 'Bunny search should stay static-only even if the public page mentions variable-source files.');
    assertSameValue([], $first['axis_tags'] ?? null, 'Bunny search should not expose variable axis tags now that Bunny imports are static-only.');
    assertSameValue([], $first['axes'] ?? null, 'Bunny search should not expose variable axis ranges now that Bunny imports are static-only.');
    assertSameValue(
        ['static' => ['label' => 'Static', 'available' => true, 'source_only' => false]],
        $first['formats'] ?? null,
        'Bunny search should only expose static delivery since Bunny does not deliver variable fonts via download or CDN.'
    );
    assertSameValue(
        ['100', 'regular', '700', 'italic', '700italic'],
        $first['variants'] ?? [],
        'Bunny search should normalize public variant tokens into the plugin token format.'
    );
};

$tests['bunny_fonts_client_search_family_page_sets_has_more_without_hydrating_probe_row'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;

    $client = new BunnyFontsClient();
    $remoteGetResponses['https://fonts.bunny.net/sitemap.xml'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'application/xml'],
        'body' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://fonts.bunny.net/family/inter</loc></url>
  <url><loc>https://fonts.bunny.net/family/ibm-plex-sans</loc></url>
</urlset>
XML,
    ];
    $remoteGetResponses['https://fonts.bunny.net/family/inter'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/html'],
        'body' => <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Inter | Bunny Fonts</title>
</head>
<body>
    <div class="family"><h3>Sans Serif</h3></div>
    <div class="styles">18 styles</div>
    <div class="card-main"><h1>Inter</h1></div>
    <link href="https://fonts.bunny.net/css?family=inter:100,400,700,400i,700i," rel="stylesheet" />
</body>
</html>
HTML,
    ];

    $page = $client->searchFamilyPage('i', 1, 0);

    assertSameValue(true, !empty($page['has_more']), 'Bunny page search should expose has_more when additional catalog matches remain.');
    assertSameValue(1, (int) ($page['next_offset'] ?? 0), 'Bunny page search should advance next_offset by hydrated items only.');
    assertSameValue(1, count($page['items'] ?? []), 'Bunny page search should hydrate only the requested page size.');
    $requestedUrls = array_values(array_map(static fn (array $call): string => (string) ($call['url'] ?? ''), $remoteGetCalls));

    assertSameValue('https://fonts.bunny.net/sitemap.xml', (string) ($requestedUrls[0] ?? ''), 'Bunny page search should fetch the catalog first.');
    assertSameValue(2, count($requestedUrls), 'Bunny page search should avoid hydrating an extra hidden probe row for has_more checks.');
    assertSameValue(true, str_starts_with((string) ($requestedUrls[1] ?? ''), 'https://fonts.bunny.net/family/'), 'Bunny page search should hydrate only one family page for the visible result row.');
};

$tests['bunny_fonts_client_search_family_page_returns_empty_without_hydration_for_zero_limit'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;

    $client = new BunnyFontsClient();
    $remoteGetResponses['https://fonts.bunny.net/sitemap.xml'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'application/xml'],
        'body' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://fonts.bunny.net/family/inter</loc></url>
</urlset>
XML,
    ];

    $page = $client->searchFamilyPage('i', 0, 3);

    assertSameValue([], $page['items'] ?? null, 'Bunny page search should return no items when limit is non-positive.');
    assertSameValue(false, !empty($page['has_more']), 'Bunny page search should not expose has_more when limit is non-positive.');
    assertSameValue(3, (int) ($page['next_offset'] ?? 0), 'Bunny page search should preserve the normalized offset when limit is non-positive.');
    assertSameValue([], $remoteGetCalls, 'Bunny page search should avoid catalog/family hydration when limit is non-positive.');
};

$tests['bunny_fonts_client_get_family_parses_public_variant_tokens'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $client = new BunnyFontsClient();
    $remoteGetResponses['https://fonts.bunny.net/family/alegreya-sans'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/html'],
        'body' => <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Alegreya Sans | Bunny Fonts</title>
</head>
<body>
    <div class="family"><h3>Sans Serif</h3></div>
    <div class="styles">4 styles</div>
    <div class="card-main"><h1>Alegreya Sans</h1></div>
    <link href="https://fonts.bunny.net/css?family=alegreya-sans:400,700,400i,700i," rel="stylesheet" />
</body>
</html>
HTML,
    ];

    $family = $client->getFamily('Alegreya Sans');

    assertSameValue('Alegreya Sans', (string) ($family['family'] ?? ''), 'Bunny family lookup should resolve the exact public family name.');
    assertSameValue(
        ['regular', '700', 'italic', '700italic'],
        $family['variants'] ?? [],
        'Bunny family lookup should convert Bunny public variant markers into Google-style plugin tokens.'
    );
};

$tests['bunny_fonts_client_ignores_v1_cached_family_records_after_namespace_bump'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;
    global $transientStore;

    $client = new BunnyFontsClient();
    $v1Key = TransientKey::forSite('tasty_fonts_bunny_family_' . substr(md5('inter'), 0, 12));
    $v2Key = TransientKey::forSite(TastyFonts\Bunny\BunnyFontsClient::TRANSIENT_FAMILY_PREFIX . substr(md5('inter'), 0, 12));
    $transientStore[$v1Key] = [
        'family' => 'Inter',
        'slug' => 'inter',
        'category' => 'sans-serif',
        'category_label' => 'Sans Serif',
        'variants' => ['regular'],
        'style_count' => 1,
    ];
    $remoteGetResponses['https://fonts.bunny.net/family/inter'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/html'],
        'body' => <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Inter | Bunny Fonts</title>
</head>
<body>
    <div class="family"><h3>Sans Serif</h3></div>
    <div class="styles">18 styles</div>
    <div class="card-main"><h1>Inter</h1></div>
    <p class="license-paragraph">Inter-Italic[opsz,wght].ttf</p>
    <link href="https://fonts.bunny.net/css?family=inter:100,400,700,400i,700i," rel="stylesheet" />
</body>
</html>
HTML,
    ];

    $family = $client->getFamily('Inter');

    assertSameValue(1, count($remoteGetCalls), 'Bunny family lookup should ignore old v1 family cache records and fetch fresh metadata.');
    assertSameValue(true, array_key_exists($v1Key, $transientStore), 'Ignoring the old Bunny family namespace should leave v1 cache rows to expire naturally.');
    assertSameValue(true, array_key_exists($v2Key, $transientStore), 'Fresh Bunny family metadata should be stored in the v2 family cache namespace.');
    assertSameValue(false, !empty($family['is_variable']), 'Refetched Bunny family records should stay in the static-only Bunny shape.');
    assertSameValue([], $family['axis_tags'] ?? null, 'Refetched Bunny family records should clear any legacy Bunny axis tags.');
    assertSameValue([], $family['axes'] ?? null, 'Refetched Bunny family records should clear any legacy Bunny axis ranges.');
    assertSameValue(
        ['100', 'regular', '700', 'italic', '700italic'],
        $family['variants'] ?? [],
        'Refetched Bunny family records should replace stale cached variants with the current normalized variant list.'
    );
    assertSameValue(
        ['static' => ['label' => 'Static', 'available' => true, 'source_only' => false]],
        $family['formats'] ?? null,
        'Refetched Bunny family records should expose only static delivery since Bunny does not deliver variable fonts via download or CDN.'
    );
};

$tests['bunny_fonts_client_refetches_cached_family_records_that_still_expose_variable_formats'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;
    global $transientStore;

    $client = new BunnyFontsClient();
    $transientStore[TransientKey::forSite(TastyFonts\Bunny\BunnyFontsClient::TRANSIENT_FAMILY_PREFIX . substr(md5('inter'), 0, 12))] = [
        'family' => 'Inter',
        'slug' => 'inter',
        'category' => 'sans-serif',
        'category_label' => 'Sans Serif',
        'variants' => ['regular'],
        'style_count' => 1,
        'is_variable' => true,
        'axes' => ['WGHT' => ['min' => '100', 'default' => '400', 'max' => '900']],
        'axis_tags' => ['WGHT'],
        'formats' => [
            'static' => ['label' => 'Static', 'available' => true, 'source_only' => false],
            'variable' => ['label' => 'Variable', 'available' => false, 'source_only' => true],
        ],
        'import_options' => [
            'static' => ['label' => 'Static', 'available' => true, 'source_only' => false],
            'variable' => ['label' => 'Variable', 'available' => false, 'source_only' => true],
        ],
    ];
    $remoteGetResponses['https://fonts.bunny.net/family/inter'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/html'],
        'body' => <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Inter | Bunny Fonts</title>
</head>
<body>
    <div class="family"><h3>Sans Serif</h3></div>
    <div class="styles">18 styles</div>
    <div class="card-main"><h1>Inter</h1></div>
    <p class="license-paragraph">Inter-Italic[opsz,wght].ttf</p>
    <link href="https://fonts.bunny.net/css?family=inter:100,400,700,400i,700i," rel="stylesheet" />
</body>
</html>
HTML,
    ];

    $family = $client->getFamily('Inter');

    assertSameValue(false, !empty($family['is_variable']), 'Cached Bunny family records with legacy variable flags should be refetched into the static-only shape.');
    assertSameValue([], $family['axis_tags'] ?? null, 'Refetched Bunny family records should clear stale variable axis tags.');
    assertSameValue([], $family['axes'] ?? null, 'Refetched Bunny family records should clear stale variable axis ranges.');
    assertSameValue(
        ['static' => ['label' => 'Static', 'available' => true, 'source_only' => false]],
        $family['formats'] ?? null,
        'Refetched Bunny family records should drop any legacy variable format metadata.'
    );
    assertSameValue(
        ['static' => ['label' => 'Static', 'available' => true, 'source_only' => false]],
        $family['import_options'] ?? null,
        'Refetched Bunny family records should drop any legacy variable import options.'
    );
};

$tests['google_fonts_client_uses_compact_catalog_cache_for_search_and_refetches_full_family_metadata_on_demand'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;
    global $transientStore;

    $settings = new SettingsRepository();
    $settings->saveSettings(['google_api_key' => 'api-key']);
    $settings->saveGoogleApiKeyStatus('valid', 'Ready');
    $client = new GoogleFontsClient($settings, new GoogleApiKeyRepository());
    $catalogUrl = 'https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key=api-key';
    $metadataUrl = 'https://fonts.google.com/metadata/fonts';
    $remoteGetResponses[$catalogUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'application/json'],
        'body' => json_encode(
            [
                'items' => [
                    [
                        'family' => 'Inter',
                        'category' => 'sans-serif',
                        'variants' => ['regular', 'italic'],
                        'subsets' => ['latin'],
                        'version' => 'v18',
                        'lastModified' => '2024-01-01',
                    ],
                    [
                        'family' => 'Lora',
                        'category' => 'serif',
                        'variants' => ['regular'],
                        'subsets' => ['latin'],
                        'version' => 'v35',
                        'lastModified' => '2024-01-02',
                    ],
                ],
            ]
        ),
    ];
    $remoteGetResponses[$metadataUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'application/json'],
        'body' => json_encode(
            [
                'familyMetadataList' => [
                    [
                        'family' => 'Inter',
                        'axes' => [
                            ['tag' => 'opsz', 'min' => 14, 'max' => 32, 'defaultValue' => 14],
                            ['tag' => 'wght', 'min' => 100, 'max' => 900, 'defaultValue' => 400],
                        ],
                    ],
                    [
                        'family' => 'Lora',
                        'axes' => [],
                    ],
                ],
            ]
        ),
    ];

    $results = $client->searchFamilies('int', 5);
    $resultsAgain = $client->searchFamilies('int', 5);
    $family = (new GoogleFontsClient($settings, new GoogleApiKeyRepository()))->getFamily('Inter');

    assertSameValue(
        [
            [
                'family' => 'Inter',
                'slug' => 'inter',
                'category' => 'sans-serif',
                'variants_count' => 2,
                'variants' => ['regular', 'italic'],
                'is_variable' => true,
                'axes' => [
                    'OPSZ' => ['min' => '14', 'default' => '14', 'max' => '32'],
                    'WGHT' => ['min' => '100', 'default' => '400', 'max' => '900'],
                ],
                'formats' => [
                    'static' => ['label' => 'Static', 'available' => true, 'source_only' => false],
                    'variable' => ['label' => 'Variable', 'available' => true, 'source_only' => false],
                ],
                'import_options' => [
                    'static' => ['label' => 'Static', 'available' => true, 'source_only' => false],
                    'variable' => ['label' => 'Variable', 'available' => true, 'source_only' => false],
                ],
            ],
        ],
        $results,
        'Google search should merge public Google Fonts metadata when the Webfonts API omits variable axes.'
    );
    assertSameValue($results, $resultsAgain, 'Repeated Google searches in the same request should reuse the in-memory compact catalog index.');
    assertSameValue(
        [
            'inter' => [
                'family' => 'Inter',
                'category' => 'sans-serif',
                'variants_count' => 2,
                'variants' => ['regular', 'italic'],
                'is_variable' => true,
                'axes' => [
                    'OPSZ' => ['min' => '14', 'default' => '14', 'max' => '32'],
                    'WGHT' => ['min' => '100', 'default' => '400', 'max' => '900'],
                ],
            ],
            'lora' => [
                'family' => 'Lora',
                'category' => 'serif',
                'variants_count' => 1,
                'variants' => ['regular'],
                'is_variable' => false,
                'axes' => [],
            ],
        ],
        $transientStore[TransientKey::forSite(GoogleFontsClient::TRANSIENT_CATALOG)] ?? null,
        'The Google catalog transient should only store the compact search index.'
    );
    assertSameValue(3, count($remoteGetCalls), 'Google family metadata lookups should refetch the full catalog while reusing the cached Google Fonts metadata fallback.');
    assertSameValue(['regular', 'italic'], $family['variants'] ?? null, 'Google family lookups should still return full variant metadata on demand.');
    assertSameValue('v18', (string) ($family['version'] ?? ''), 'Google family lookups should still return full catalog metadata on demand.');
    assertSameValue(
        [
            'OPSZ' => ['min' => '14', 'default' => '14', 'max' => '32'],
            'WGHT' => ['min' => '100', 'default' => '400', 'max' => '900'],
        ],
        $family['axes'] ?? null,
        'Google family lookups should recover variable axes from the public metadata catalog when the API response omits them.'
    );
};

$tests['google_fonts_client_schedules_api_key_revalidation_when_status_is_stale'] = static function (): void {
    resetTestState();

    global $optionStore;
    global $scheduledEvents;
    global $transientStore;

    $settings = new SettingsRepository();
    $settings->saveSettings(['google_api_key' => 'api-key']);
    $settings->saveGoogleApiKeyStatus('valid', 'Ready');
    $optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA]['google_api_key_checked_at'] = time() - (2 * DAY_IN_SECONDS);

    $client = new GoogleFontsClient($settings, new GoogleApiKeyRepository());
    $status = $client->getApiKeyStatus();

    assertSameValue('valid', (string) ($status['state'] ?? ''), 'Loading stale Google API key status should preserve the stored state while revalidation is queued.');
    assertSameValue(
        GoogleFontsClient::ACTION_REVALIDATE_API_KEY,
        (string) ($scheduledEvents[0]['hook'] ?? ''),
        'Stale Google API key status should queue a lightweight revalidation event.'
    );
    assertSameValue(
        ['queued' => 1],
        $transientStore[TransientKey::forSite(GoogleFontsClient::TRANSIENT_API_KEY_REVALIDATION_QUEUED)] ?? null,
        'Stale Google API key status should set a short-lived queue guard transient.'
    );
};

$tests['google_fonts_client_clears_catalog_cache'] = static function (): void {
    resetTestState();

    global $transientDeleted;
    global $transientStore;

    $transientStore[TransientKey::forSite(GoogleFontsClient::TRANSIENT_CATALOG)] = ['family' => 'Inter'];
    $transientStore[TransientKey::forSite(GoogleFontsClient::TRANSIENT_METADATA)] = ['inter' => ['family' => 'Inter', 'axes' => []]];

    $client = new GoogleFontsClient(new SettingsRepository(), new GoogleApiKeyRepository());
    $client->clearCatalogCache();

    assertSameValue(false, array_key_exists(TransientKey::forSite(GoogleFontsClient::TRANSIENT_CATALOG), $transientStore), 'Google catalog cache clearing should remove the cached catalog transient.');
    assertSameValue(false, array_key_exists(TransientKey::forSite(GoogleFontsClient::TRANSIENT_METADATA), $transientStore), 'Google catalog cache clearing should remove the cached Google Fonts metadata fallback.');
    assertSameValue(true, in_array(TransientKey::forSite(GoogleFontsClient::TRANSIENT_CATALOG), $transientDeleted, true), 'Google catalog cache clearing should delete the expected catalog transient key.');
    assertSameValue(true, in_array(TransientKey::forSite(GoogleFontsClient::TRANSIENT_METADATA), $transientDeleted, true), 'Google catalog cache clearing should delete the metadata fallback transient key.');
};

$tests['provider_clients_apply_http_request_args_filters'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;

    add_filter(
        'tasty_fonts_http_request_args',
        static function (array $args, string $url): array {
            $host = (string) (wp_parse_url($url, PHP_URL_HOST) ?? '');
            $headers = is_array($args['headers'] ?? null) ? $args['headers'] : [];
            $headers['X-Tasty-Test'] = $host;
            $args['headers'] = $headers;
            $args['timeout'] = 99;

            return $args;
        },
        10,
        2
    );

    $settings = new SettingsRepository();
    $google = new GoogleFontsClient($settings, new GoogleApiKeyRepository());
    $bunny = new BunnyFontsClient();
    $adobe = new AdobeProjectClient($settings, new AdobeProjectRepository(), new AdobeCssParser());
    $googleCatalogUrl = 'https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key=api-key';
    $googleCssUrl = $google->buildCssUrl('Inter', ['regular']);
    $bunnyFamilyUrl = 'https://fonts.bunny.net/family/inter';
    $bunnyCssUrl = $bunny->buildCssUrl('Inter', ['regular']);
    $adobeUrl = 'https://use.typekit.net/abc1234.css';

    $remoteGetResponses[$googleCatalogUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'application/json'],
        'body' => '{"items":[]}',
    ];
    $remoteGetResponses[$googleCssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face{font-family:"Inter";font-style:normal;font-weight:400;src:url(https://fonts.gstatic.com/s/inter/v1/inter.woff2) format("woff2");}',
    ];
    $remoteGetResponses[$bunnyFamilyUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/html'],
        'body' => <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Inter | Bunny Fonts</title>
</head>
<body>
    <div class="family"><h3>Sans Serif</h3></div>
    <div class="styles">1 style</div>
    <div class="card-main"><h1>Inter</h1></div>
    <p class="license-paragraph">Inter-Italic[opsz,wght].ttf</p>
    <link href="https://fonts.bunny.net/css?family=inter:400," rel="stylesheet" />
</body>
</html>
HTML,
    ];
    $remoteGetResponses[$bunnyCssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face{font-family:"Inter";font-style:normal;font-weight:400;src:url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format("woff2");}',
    ];
    $remoteGetResponses[$adobeUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: "ff-tisa-web-pro";
  font-style: normal;
  font-weight: 100 700;
  font-variation-settings: "opsz" 12;
  src: url("https://use.typekit.net/af/abc123/000000000000000000000000/30/l?primer=1") format("woff2");
}
CSS,
    ];

    $google->validateApiKey('api-key');
    $google->fetchCss('Inter', ['regular']);
    $bunny->getFamily('Inter');
    $bunny->fetchCss('Inter', ['regular']);
    $adobe->validateProject('abc1234');

    assertSameValue(5, count($remoteGetCalls), 'The HTTP args filter test should exercise each provider client.');

    foreach ($remoteGetCalls as $call) {
        $url = (string) ($call['url'] ?? '');
        $args = (array) ($call['args'] ?? []);
        $headers = is_array($args['headers'] ?? null) ? $args['headers'] : [];

        assertSameValue(99, (int) ($args['timeout'] ?? 0), 'HTTP request args filters should be able to override timeouts for ' . $url . '.');
        assertSameValue(true, isset($headers['X-Tasty-Test']) && $headers['X-Tasty-Test'] !== '', 'HTTP request args filters should be able to inject headers for ' . $url . '.');
    }
};

$tests['adobe_project_client_validates_project_and_reuses_cached_families'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;

    $projectId = 'abc1234';
    $url = 'https://use.typekit.net/' . $projectId . '.css';
    $remoteGetResponses[$url] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: "ff-tisa-web-pro";
  font-style: normal;
  font-weight: 100 700;
  font-variation-settings: "opsz" 12;
  src: url("https://use.typekit.net/af/abc123/000000000000000000000000/30/l?primer=1") format("woff2");
}
@font-face {
  font-family: "mr-eaves-xl-modern";
  font-style: italic;
  font-weight: 700;
  src: url("https://use.typekit.net/af/def456/000000000000000000000000/30/l?primer=1") format("woff2");
}
CSS,
    ];

    $client = new AdobeProjectClient(new SettingsRepository(), new AdobeProjectRepository(), new AdobeCssParser());
    $validation = $client->validateProject('ABC-1234');
    $families = $client->getProjectFamilies($projectId);

    assertSameValue('valid', $validation['state'], 'Adobe project validation should mark a parseable 200 stylesheet as valid.');
    assertContainsValue('2 famil', (string) $validation['message'], 'Adobe project validation should report the detected family count.');
    assertSameValue(2, count($families), 'Adobe project metadata should expose parsed family records.');
    assertSameValue('ff-tisa-web-pro', $families[0]['family'], 'Adobe project family metadata should preserve parsed CSS family names.');
    assertSameValue(true, !empty($families[0]['faces'][0]['is_variable']), 'Adobe project metadata should preserve variable-face markers from the hosted stylesheet.');
    assertSameValue('100', (string) ($families[0]['faces'][0]['axes']['WGHT']['min'] ?? ''), 'Adobe project metadata should preserve parsed weight-axis ranges.');
    assertSameValue(1, count($remoteGetCalls), 'Adobe project families should come from the cache after a successful validation fetch.');
};

$tests['adobe_project_client_maps_invalid_and_unknown_responses'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $invalidUrl = 'https://use.typekit.net/invalid01.css';
    $unknownUrl = 'https://use.typekit.net/unknown01.css';
    $remoteGetResponses[$invalidUrl] = [
        'response' => ['code' => 404],
        'headers' => ['content-type' => 'text/css'],
        'body' => '',
    ];
    $remoteGetResponses[$unknownUrl] = new WP_Error('http_request_failed', 'Timed out');

    $client = new AdobeProjectClient(new SettingsRepository(), new AdobeProjectRepository(), new AdobeCssParser());
    $invalid = $client->validateProject('invalid01');
    $unknown = $client->validateProject('unknown01');

    assertSameValue('invalid', $invalid['state'], 'Adobe project validation should treat rejected project IDs as invalid.');
    assertSameValue('unknown', $unknown['state'], 'Adobe project validation should treat transport failures as unknown.');
};
