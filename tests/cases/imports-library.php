<?php

declare(strict_types=1);

if (!function_exists('tastyFontsTestFontBytes')) {
    function tastyFontsTestFontBytes(string $format): string
    {
        return ($format === 'woff' ? 'wOFF' : 'wOF2') . 'test-font-bytes';
    }
}

if (!function_exists('tastyFontsMockCustomCssFont')) {
    function tastyFontsMockCustomCssFont(string $url, string $format = 'woff2', array $headers = [], string $body = ''): void
    {
        global $remoteGetResponses;
        global $remoteRequestResponses;

        $contentType = $format === 'woff' ? 'font/woff' : 'font/woff2';
        $body = $body !== '' ? $body : tastyFontsTestFontBytes($format);
        $headers = array_replace([
            'content-type' => $contentType,
            'content-length' => (string) strlen($body),
            'access-control-allow-origin' => '*',
        ], $headers);

        $remoteRequestResponses['HEAD ' . $url] = [
            'response' => ['code' => 200],
            'headers' => $headers,
            'body' => '',
        ];
        $remoteGetResponses[$url] = [
            'response' => ['code' => 206],
            'headers' => array_replace($headers, [
                'content-range' => 'bytes 0-15/' . strlen($body),
            ]),
            'body' => substr($body, 0, 16),
        ];
    }
}

if (!function_exists('tastyFontsMockCustomCssFinalFont')) {
    function tastyFontsMockCustomCssFinalFont(string $url, string $format = 'woff2', string $body = ''): string
    {
        global $remoteGetResponses;

        $contentType = $format === 'woff' ? 'font/woff' : 'font/woff2';
        $body = $body !== '' ? $body : tastyFontsTestFontBytes($format);
        $remoteGetResponses[$url] = [
            'response' => ['code' => 200],
            'headers' => [
                'content-type' => $contentType,
                'content-length' => (string) strlen($body),
            ],
            'body' => $body,
        ];

        return $body;
    }
}

$tests['custom_css_url_import_service_returns_normalized_dry_run_plan_without_writes'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;
    global $remoteRequestCalls;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/fonts.css';
    $fontUrl = 'https://cdn.example.com/fonts/foundry-sans-400.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Foundry Sans";
    font-style: normal;
    font-weight: 400;
    src: url("https://cdn.example.com/fonts/foundry-sans-400.woff2") format("woff2");
    unicode-range: U+000-5FF;
}
CSS,
    ];
    tastyFontsMockCustomCssFont($fontUrl);

    $result = $services['custom_css_import']->dryRun($cssUrl);

    assertFalseValue(is_wp_error($result), 'A valid custom CSS stylesheet should return a dry-run plan.');
    assertSameValue('dry_run', (string) ($result['status'] ?? ''), 'Dry runs should expose a stable dry_run status.');
    assertSameValue('custom_css_url', (string) ($result['plan']['source']['type'] ?? ''), 'The plan should identify the custom CSS URL source type.');
    assertSameValue('assets.example.com', (string) ($result['plan']['source']['host'] ?? ''), 'The plan should expose the source host for review.');
    assertSameValue(1, (int) ($result['plan']['counts']['families'] ?? 0), 'The plan should count detected families.');
    assertSameValue(1, (int) ($result['plan']['counts']['faces'] ?? 0), 'The plan should count detected faces.');

    $family = $result['plan']['families'][0] ?? [];
    $face = is_array($family) ? ($family['faces'][0] ?? []) : [];

    assertSameValue('Foundry Sans', (string) ($family['family'] ?? ''), 'The family should be normalized into the review plan.');
    assertSameValue('foundry-sans', (string) ($family['slug'] ?? ''), 'The family slug should be stable.');
    assertSameValue('400', (string) ($face['weight'] ?? ''), 'The face weight should be normalized.');
    assertSameValue('normal', (string) ($face['style'] ?? ''), 'The face style should be normalized.');
    assertSameValue('woff2', (string) ($face['format'] ?? ''), 'The face format should be captured.');
    assertSameValue($fontUrl, (string) ($face['url'] ?? ''), 'The face URL should be captured for review only.');
    assertSameValue('cdn.example.com', (string) ($face['host'] ?? ''), 'The face host should be captured for review.');
    assertSameValue('U+000-5FF', (string) ($face['unicode_range'] ?? ''), 'The dry-run plan should preserve unicode-range text.');
    assertSameValue('valid', (string) ($face['status'] ?? ''), 'Valid font URLs should be selected by default after validation.');
    assertSameValue(true, (bool) ($face['selected'] ?? false), 'Valid font URL rows should remain selected.');
    assertSameValue('HEAD + range GET', (string) ($face['validation']['method'] ?? ''), 'Dry-run validation should use a lightweight HEAD plus range GET check.');
    assertContainsValue('WOFF2 signature matched.', implode(' ', $face['validation']['notes'] ?? []), 'Validation notes should expose the matched WOFF2 signature.');
    assertSameValue([], $services['imports']->all(), 'Dry runs should not save library import records.');
    assertSameValue(2, count($remoteGetCalls), 'Dry runs should fetch the stylesheet and a capped font signature range.');
    assertSameValue(1, count($remoteRequestCalls), 'Dry runs should use a HEAD request for font metadata before range validation.');
    assertSameValue(262145, (int) ($remoteGetCalls[0]['args']['limit_response_size'] ?? 0), 'Dry-run fetches should pass the capped CSS response size to the WordPress HTTP client.');
    assertSameValue('bytes=0-15', (string) ($remoteGetCalls[1]['args']['headers']['Range'] ?? ''), 'Font signature validation should request only a small byte range.');
};

$tests['custom_css_url_import_service_accepts_simple_woff_faces'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/woff.css';
    $fontUrl = 'https://cdn.example.com/fonts/foundry-serif-700-italic.woff';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Foundry Serif";
    font-style: italic;
    font-weight: 700;
    src: url("https://cdn.example.com/fonts/foundry-serif-700-italic.woff") format("woff");
}
CSS,
    ];
    tastyFontsMockCustomCssFont($fontUrl, 'woff');

    $result = $services['custom_css_import']->dryRun($cssUrl);

    assertFalseValue(is_wp_error($result), 'A valid WOFF face should be accepted in the Slice 1 dry run.');
    assertSameValue('woff', (string) ($result['plan']['families'][0]['faces'][0]['format'] ?? ''), 'The plan should preserve WOFF format.');
    assertSameValue('italic', (string) ($result['plan']['families'][0]['faces'][0]['style'] ?? ''), 'The plan should preserve italic style.');
};

$tests['custom_css_url_import_service_parses_fontsource_style_relative_subset_faces'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://unpkg.com/@fontsource/inter@5.0.0/index.css';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Inter";
    font-style: normal;
    font-weight: 400;
    src: url("./files/inter-latin-400-normal.woff2") format("woff2"), url("./files/inter-latin-400-normal.woff") format("woff");
    unicode-range: U+0000-00FF;
}
@font-face {
    font-family: "Inter";
    font-style: normal;
    font-weight: 400;
    src: url("./files/inter-cyrillic-400-normal.woff2") format("woff2");
    unicode-range: U+0400-052F;
}
@font-face {
    font-family: "Foundry Serif";
    font-style: italic;
    font-weight: 700;
    src: url("https://cdn.example.com/foundry-serif-700-italic.woff") format("woff");
}
CSS,
    ];
    tastyFontsMockCustomCssFont('https://unpkg.com/@fontsource/inter@5.0.0/files/inter-latin-400-normal.woff2');
    tastyFontsMockCustomCssFont('https://unpkg.com/@fontsource/inter@5.0.0/files/inter-cyrillic-400-normal.woff2');
    tastyFontsMockCustomCssFont('https://cdn.example.com/foundry-serif-700-italic.woff', 'woff');

    $result = $services['custom_css_import']->dryRun($cssUrl);

    assertFalseValue(is_wp_error($result), 'Fontsource-style relative WOFF2 and WOFF subset CSS should return a dry-run plan.');
    assertSameValue(2, (int) ($result['plan']['counts']['families'] ?? 0), 'One stylesheet should be able to produce multiple families.');
    assertSameValue(3, (int) ($result['plan']['counts']['faces'] ?? 0), 'One stylesheet should be able to produce multiple faces.');
    assertSameValue(3, (int) ($result['plan']['counts']['valid_faces'] ?? 0), 'All WOFF2 and WOFF fixture faces should be valid.');

    $inter = $result['plan']['families'][0] ?? [];
    $latin = is_array($inter) ? ($inter['faces'][0] ?? []) : [];
    $cyrillic = is_array($inter) ? ($inter['faces'][1] ?? []) : [];
    $serif = $result['plan']['families'][1]['faces'][0] ?? [];

    assertSameValue('Inter', (string) ($inter['family'] ?? ''), 'The first family should be Inter.');
    assertSameValue('https://unpkg.com/@fontsource/inter@5.0.0/files/inter-latin-400-normal.woff2', (string) ($latin['url'] ?? ''), 'Relative Fontsource WOFF2 URLs should resolve against the CSS URL.');
    assertSameValue('woff2', (string) ($latin['format'] ?? ''), 'WOFF2 should be preferred when a face offers WOFF2 and WOFF sources.');
    assertSameValue('U+0000-00FF', (string) ($latin['unicode_range'] ?? ''), 'Latin unicode-range should be preserved.');
    assertSameValue('U+0400-052F', (string) ($cyrillic['unicode_range'] ?? ''), 'Cyrillic unicode-range should be preserved.');
    assertFalseValue((string) ($latin['id'] ?? '') === (string) ($cyrillic['id'] ?? ''), 'Subset faces with the same family, weight, style, and format should keep distinct face IDs.');
    assertSameValue('woff', (string) ($serif['format'] ?? ''), 'WOFF-only faces should remain supported.');
    assertSameValue('cdn.example.com', (string) ($serif['host'] ?? ''), 'Cross-host public font URLs should be allowed when independently safe.');
};

$tests['custom_css_url_import_service_surfaces_unsupported_formats_as_disabled_faces'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/unsupported-format.css';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Legacy Display";
    font-style: normal;
    font-weight: 400;
    src: url("./legacy-display.ttf") format("truetype");
}
@font-face {
    font-family: "Legacy Display";
    font-style: normal;
    font-weight: 700;
    src: url("./legacy-display-700.woff2") format("woff2");
}
CSS,
    ];
    tastyFontsMockCustomCssFont('https://assets.example.com/legacy-display-700.woff2');

    $result = $services['custom_css_import']->dryRun($cssUrl);

    assertFalseValue(is_wp_error($result), 'Unsupported format rows should be surfaced in the dry-run plan instead of aborting safe CSS.');
    assertSameValue(2, (int) ($result['plan']['counts']['faces'] ?? 0), 'Supported and unsupported sources should both be visible in the review plan.');
    assertSameValue(1, (int) ($result['plan']['counts']['valid_faces'] ?? 0), 'Only WOFF2 and WOFF rows should count as valid faces.');
    assertSameValue(1, (int) ($result['plan']['counts']['unsupported_faces'] ?? 0), 'Unsupported rows should be counted separately.');

    $unsupported = $result['plan']['families'][0]['faces'][0] ?? [];
    $supported = $result['plan']['families'][0]['faces'][1] ?? [];

    assertSameValue('ttf', (string) ($unsupported['format'] ?? ''), 'Unsupported truetype sources should keep a normalized format label.');
    assertSameValue('unsupported', (string) ($unsupported['status'] ?? ''), 'Unsupported sources should use a stable unsupported status.');
    assertSameValue(false, (bool) ($unsupported['selected'] ?? true), 'Unsupported sources should be disabled for import selection.');
    assertSameValue('https://assets.example.com/legacy-display.ttf', (string) ($unsupported['url'] ?? ''), 'Unsupported relative URLs should still resolve for review.');
    assertSameValue('valid', (string) ($supported['status'] ?? ''), 'Supported sibling sources should remain valid.');
};

$tests['custom_css_url_import_service_uses_latest_src_and_allows_final_declarations_without_semicolon'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/legacy-src.css';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Modern Cascade";
    font-style: normal;
    font-weight: 400;
    src: url("./modern-cascade.eot") format("embedded-opentype");
    src: url("./modern-cascade.woff2") format("woff2"), url("./modern-cascade.woff") format("woff");
    unicode-range: U+0100-024F
}
@font-face {
    font-family: "No Semicolon";
    font-style: normal;
    font-weight: 400;
    src: url("./no-semicolon.woff2") format("woff2")
}
CSS,
    ];
    tastyFontsMockCustomCssFont('https://assets.example.com/modern-cascade.woff2');
    tastyFontsMockCustomCssFont('https://assets.example.com/no-semicolon.woff2');

    $result = $services['custom_css_import']->dryRun($cssUrl);

    assertFalseValue(is_wp_error($result), 'CSS declarations without trailing semicolons should still parse.');
    assertSameValue(2, (int) ($result['plan']['counts']['faces'] ?? 0), 'Both final-declaration fixtures should produce faces.');
    assertSameValue('woff2', (string) ($result['plan']['families'][0]['faces'][0]['format'] ?? ''), 'The latest src declaration should win over a legacy EOT src declaration.');
    assertSameValue('https://assets.example.com/modern-cascade.woff2', (string) ($result['plan']['families'][0]['faces'][0]['url'] ?? ''), 'Latest src declaration URLs should resolve normally.');
    assertSameValue('U+0100-024F', (string) ($result['plan']['families'][0]['faces'][0]['unicode_range'] ?? ''), 'Final unicode-range declarations without semicolons should be preserved.');
    assertSameValue('https://assets.example.com/no-semicolon.woff2', (string) ($result['plan']['families'][1]['faces'][0]['url'] ?? ''), 'Final src declarations without semicolons should be parsed.');
};

$tests['custom_css_url_import_service_revalidates_resolved_font_urls'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/revalidate-relative.css';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Unsafe Relative";
    font-style: normal;
    font-weight: 400;
    src: url("//192.168.1.10/private.woff2") format("woff2");
}
CSS,
    ];

    $result = $services['custom_css_import']->dryRun($cssUrl);

    assertWpErrorCode('tasty_fonts_custom_css_font_url_blocked', $result, 'Protocol-relative font URLs should be resolved and revalidated before a plan is returned.');
};

$tests['custom_css_url_import_service_generates_stable_face_ids_from_normalized_data'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $firstCssUrl = 'https://assets.example.com/stable-a.css';
    $secondCssUrl = 'https://assets.example.com/stable-b.css';
    $remoteGetResponses[$firstCssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Stable Sans";
    font-weight: 400;
    font-style: normal;
    src: url("https://cdn.example.com/fonts/stable-sans.woff2") format("woff2");
    unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$secondCssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    src: url(https://cdn.example.com/fonts/./stable-sans.woff2) format('woff2');
    font-style: normal;
    unicode-range: U+0000-00FF;
    font-weight: 400;
    font-family: 'Stable Sans';
}
CSS,
    ];
    tastyFontsMockCustomCssFont('https://cdn.example.com/fonts/stable-sans.woff2');

    $first = $services['custom_css_import']->dryRun($firstCssUrl);
    $second = $services['custom_css_import']->dryRun($secondCssUrl);

    assertFalseValue(is_wp_error($first), 'The first stable ID fixture should parse.');
    assertFalseValue(is_wp_error($second), 'The second stable ID fixture should parse.');
    assertSameValue(
        (string) ($first['plan']['families'][0]['faces'][0]['id'] ?? ''),
        (string) ($second['plan']['families'][0]['faces'][0]['id'] ?? ''),
        'Semantically identical normalized server-side face data should produce the same face ID.'
    );
};

$tests['custom_css_url_import_service_rejects_unsafe_stylesheet_targets_before_fetching'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;

    $services = makeServiceGraph();
    $cases = [
        'http://assets.example.com/fonts.css' => 'tasty_fonts_custom_css_url_invalid',
        'not a url' => 'tasty_fonts_custom_css_url_invalid',
        'https://localhost/fonts.css' => 'tasty_fonts_custom_css_url_blocked',
        'https://internalhost/fonts.css' => 'tasty_fonts_custom_css_url_blocked',
        'https://10.0.0.5/fonts.css' => 'tasty_fonts_custom_css_url_blocked',
        'https://192.168.1.8/fonts.css' => 'tasty_fonts_custom_css_url_blocked',
        'https://169.254.10.20/fonts.css' => 'tasty_fonts_custom_css_url_blocked',
        'https://127.1/fonts.css' => 'tasty_fonts_custom_css_url_blocked',
        'https://[::1]/fonts.css' => 'tasty_fonts_custom_css_url_blocked',
        'https://fonts.internal/fonts.css' => 'tasty_fonts_custom_css_url_blocked',
        'https://fonts.test/fonts.css' => 'tasty_fonts_custom_css_url_blocked',
    ];

    foreach ($cases as $url => $expectedCode) {
        $result = $services['custom_css_import']->dryRun($url);

        assertWpErrorCode($expectedCode, $result, 'Unsafe custom CSS stylesheet URLs should fail before fetch: ' . $url);
    }

    assertSameValue([], $remoteGetCalls, 'Unsafe custom CSS stylesheet URLs should not be fetched.');
};

$tests['custom_css_url_import_service_keeps_internal_url_allow_filter_default_deny'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.test/fonts.css';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Local Fixture";
    font-style: normal;
    font-weight: 400;
    src: url("https://cdn.test/local-fixture.woff2") format("woff2");
}
CSS,
    ];
    tastyFontsMockCustomCssFont('https://cdn.test/local-fixture.woff2');

    $defaultResult = $services['custom_css_import']->dryRun($cssUrl);

    assertWpErrorCode('tasty_fonts_custom_css_url_blocked', $defaultResult, 'Internal .test stylesheet URLs should remain blocked by default.');

    add_filter(
        'tasty_fonts_custom_css_allow_internal_dry_run_url',
        static function (bool $allowed, string $url, string $host, string $kind): bool {
            unset($allowed, $url);

            return ($kind === 'stylesheet' && $host === 'assets.test')
                || ($kind === 'font' && $host === 'cdn.test');
        },
        10,
        4
    );

    $allowedResult = $services['custom_css_import']->dryRun($cssUrl);

    assertFalseValue(is_wp_error($allowedResult), 'A narrow local development filter should be able to opt in a controlled .test dry-run fixture.');
    assertSameValue('Local Fixture', (string) ($allowedResult['plan']['families'][0]['family'] ?? ''), 'The local-only allow filter should still require a normal dry-run plan.');
};

$tests['custom_css_url_import_service_rejects_oversized_stylesheets'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/large.css';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => str_repeat('a', 262145),
    ];

    $result = $services['custom_css_import']->dryRun($cssUrl);

    assertWpErrorCode('tasty_fonts_custom_css_too_large', $result, 'Stylesheets over the dry-run CSS size cap should be rejected.');
    assertContainsValue('dry-run limit', $result instanceof WP_Error ? $result->get_error_message() : '', 'Oversized stylesheet errors should explain the workload limit.');
    assertSameValue([], $services['imports']->all(), 'Oversized dry runs should not save library import records.');
};

$tests['custom_css_url_import_service_rejects_excessive_face_counts'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/too-many-faces.css';
    $blocks = [];

    for ($index = 0; $index < 51; $index++) {
        $blocks[] = sprintf(
            '@font-face { font-family: "Face %1$d"; font-style: normal; font-weight: 400; src: url("https://cdn.example.com/face-%1$d.woff2") format("woff2"); }',
            $index
        );
    }

    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => implode("\n", $blocks),
    ];

    $result = $services['custom_css_import']->dryRun($cssUrl);

    assertWpErrorCode('tasty_fonts_custom_css_too_many_faces', $result, 'Stylesheets with too many detected faces should be rejected.');
    assertContainsValue('no more than 50 faces', $result instanceof WP_Error ? $result->get_error_message() : '', 'Too-many-face errors should tell users how to split the workload.');
    assertSameValue([], $services['imports']->all(), 'Excessive dry runs should not save library import records.');
};

$tests['custom_css_url_import_service_caps_unique_font_url_safety_validation'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/too-many-font-urls.css';
    $sources = [];

    for ($index = 0; $index < 51; $index++) {
        $sources[] = sprintf('url("https://cdn.example.com/source-%d.woff2") format("woff2")', $index);
    }

    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Many Sources"; font-style: normal; font-weight: 400; src: ' . implode(', ', $sources) . '; }',
    ];

    $result = $services['custom_css_import']->dryRun($cssUrl);

    assertWpErrorCode('tasty_fonts_custom_css_too_many_font_urls', $result, 'Stylesheets with too many unique font URLs should be rejected during dry run.');
    assertContainsValue('no more than 50 font URLs', $result instanceof WP_Error ? $result->get_error_message() : '', 'Too-many-font-URL errors should tell users how to split the workload.');
};

$tests['custom_css_url_import_service_rejects_unsafe_absolute_font_urls_in_stylesheets'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/http-font-url.css';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Unsafe Font";
    font-style: normal;
    font-weight: 400;
    src: url("http://cdn.example.com/unsafe.woff2") format("woff2");
}
CSS,
    ];

    $httpResult = $services['custom_css_import']->dryRun($cssUrl);

    assertWpErrorCode('tasty_fonts_custom_css_url_invalid', $httpResult, 'HTTP font URLs discovered in CSS should be rejected instead of silently skipped.');

    $mixedUrl = 'https://assets.example.com/mixed-font-urls.css';
    $remoteGetResponses[$mixedUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Mixed Unsafe";
    font-style: normal;
    font-weight: 400;
    src: url("http://cdn.example.com/unsafe.woff2") format("woff2"), url("https://cdn.example.com/safe.woff2") format("woff2");
}
CSS,
    ];

    $mixedResult = $services['custom_css_import']->dryRun($mixedUrl);

    assertWpErrorCode('tasty_fonts_custom_css_url_invalid', $mixedResult, 'A safe fallback source should not hide an unsafe absolute font URL in the same src list.');

    $credentialUrl = 'https://assets.example.com/credential-font-url.css';
    $remoteGetResponses[$credentialUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Credential Font";
    font-style: normal;
    font-weight: 400;
    src: url("https://user:pass@cdn.example.com/credential.woff2") format("woff2");
}
CSS,
    ];

    $credentialResult = $services['custom_css_import']->dryRun($credentialUrl);

    assertWpErrorCode('tasty_fonts_custom_css_url_invalid', $credentialResult, 'Font URLs with credentials should be rejected instead of normalized into public URLs.');
};

$tests['custom_css_url_import_service_rejects_private_font_urls_in_stylesheets'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/private-font-url.css';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Unsafe Font";
    font-style: normal;
    font-weight: 400;
    src: url("https://192.168.1.10/unsafe.woff2") format("woff2");
}
CSS,
    ];

    $result = $services['custom_css_import']->dryRun($cssUrl);

    assertWpErrorCode('tasty_fonts_custom_css_font_url_blocked', $result, 'Private font URLs discovered in CSS should be rejected before a plan is usable.');
    assertSameValue([], $services['imports']->all(), 'Unsafe font URL dry runs should not save library import records.');
};

$tests['custom_css_url_import_service_reports_timeout_and_resolves_relative_font_urls'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $timeoutUrl = 'https://assets.example.com/timeout.css';
    $remoteGetResponses[$timeoutUrl] = new WP_Error('http_request_failed', 'cURL error 28: Operation timed out after 10001 milliseconds');

    $timeoutResult = $services['custom_css_import']->dryRun($timeoutUrl);

    assertWpErrorCode('tasty_fonts_custom_css_fetch_timeout', $timeoutResult, 'Timeouts should use a dedicated dry-run error code.');
    assertContainsValue('timed out', $timeoutResult instanceof WP_Error ? $timeoutResult->get_error_message() : '', 'Timeout errors should be clear for REST and UI display.');

    $relativeUrl = 'https://assets.example.com/fontsource-style.css';
    $remoteGetResponses[$relativeUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
    font-family: "Inter";
    font-style: normal;
    font-weight: 400;
    src: url("./files/inter-latin-400-normal.woff2") format("woff2");
}
CSS,
    ];
    tastyFontsMockCustomCssFont('https://assets.example.com/files/inter-latin-400-normal.woff2');

    $relativeResult = $services['custom_css_import']->dryRun($relativeUrl);

    assertFalseValue(is_wp_error($relativeResult), 'Relative font URLs should resolve successfully in Slice 3.');
    assertSameValue('https://assets.example.com/files/inter-latin-400-normal.woff2', (string) ($relativeResult['plan']['families'][0]['faces'][0]['url'] ?? ''), 'Relative font URLs should resolve against the stylesheet URL.');
    assertSameValue([], $services['imports']->all(), 'Dry runs should not save library import records.');
};

$tests['custom_css_url_import_service_marks_invalid_signature_faces_disabled'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/invalid-signature.css';
    $fontUrl = 'https://assets.example.com/fonts/not-really.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Bad Bytes"; font-style: normal; font-weight: 400; src: url("' . $fontUrl . '") format("woff2"); }',
    ];
    tastyFontsMockCustomCssFont($fontUrl, 'woff2', [], 'NOTFtest-font-bytes');

    $result = $services['custom_css_import']->dryRun($cssUrl);
    $face = is_array($result) ? ($result['plan']['families'][0]['faces'][0] ?? []) : [];

    assertFalseValue(is_wp_error($result), 'Invalid font signatures should not abort the whole dry run.');
    assertSameValue('invalid', (string) ($face['status'] ?? ''), 'Invalid signatures should mark the face invalid.');
    assertSameValue(false, (bool) ($face['selected'] ?? true), 'Invalid signature rows should be unselected.');
    assertSameValue(1, (int) ($result['plan']['counts']['invalid_faces'] ?? 0), 'Invalid signature rows should be counted for review.');
    assertContainsValue('signature', implode(' ', $face['validation']['notes'] ?? []), 'Validation notes should explain signature failures.');
};

$tests['custom_css_url_import_service_marks_unreachable_font_urls_disabled'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;
    global $remoteRequestResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/unreachable-font.css';
    $fontUrl = 'https://assets.example.com/fonts/missing.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Missing Font"; font-style: normal; font-weight: 400; src: url("' . $fontUrl . '") format("woff2"); }',
    ];
    $remoteRequestResponses['HEAD ' . $fontUrl] = [
        'response' => ['code' => 404],
        'headers' => ['content-type' => 'text/html'],
        'body' => '',
    ];

    $result = $services['custom_css_import']->dryRun($cssUrl);
    $face = is_array($result) ? ($result['plan']['families'][0]['faces'][0] ?? []) : [];

    assertFalseValue(is_wp_error($result), 'Unreachable font URLs should remain visible in the review.');
    assertSameValue('invalid', (string) ($face['status'] ?? ''), 'Unreachable font URLs should mark the face invalid.');
    assertSameValue(false, (bool) ($face['selected'] ?? true), 'Unreachable font URL rows should be unselected.');
    assertContainsValue('HTTP 404', implode(' ', $face['validation']['notes'] ?? []), 'Validation notes should expose the unreachable HTTP status.');
};

$tests['custom_css_url_import_service_marks_oversized_font_urls_disabled'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;
    global $remoteRequestResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/oversized-font.css';
    $fontUrl = 'https://assets.example.com/fonts/huge.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Huge Font"; font-style: normal; font-weight: 400; src: url("' . $fontUrl . '") format("woff2"); }',
    ];
    $remoteRequestResponses['HEAD ' . $fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2', 'content-length' => '10485761', 'access-control-allow-origin' => '*'],
        'body' => '',
    ];

    $result = $services['custom_css_import']->dryRun($cssUrl);
    $face = is_array($result) ? ($result['plan']['families'][0]['faces'][0] ?? []) : [];

    assertFalseValue(is_wp_error($result), 'Oversized font URLs should remain visible in the review.');
    assertSameValue('invalid', (string) ($face['status'] ?? ''), 'Oversized font URLs should mark the face invalid.');
    assertSameValue(false, (bool) ($face['selected'] ?? true), 'Oversized font URL rows should be unselected.');
    assertContainsValue('10.0 MB', implode(' ', $face['validation']['notes'] ?? []), 'Validation notes should explain the font size limit.');
};

$tests['custom_css_url_import_service_keeps_warning_faces_selectable_and_notes_remote_risks'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;
    global $remoteRequestResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/warning-font.css';
    $fontUrl = 'https://assets.example.com/fonts/warning.woff2?token=temporary';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Warning Font"; font-style: normal; font-weight: 400; src: url("' . $fontUrl . '") format("woff2"); }',
    ];
    $remoteRequestResponses['HEAD ' . $fontUrl] = [
        'response' => ['code' => 405],
        'headers' => ['content-type' => 'text/plain'],
        'body' => '',
    ];
    tastyFontsMockCustomCssFont($fontUrl, 'woff2', ['access-control-allow-origin' => ''], tastyFontsTestFontBytes('woff2'));
    $remoteRequestResponses['HEAD ' . $fontUrl] = [
        'response' => ['code' => 405],
        'headers' => ['content-type' => 'text/plain'],
        'body' => '',
    ];

    $result = $services['custom_css_import']->dryRun($cssUrl);
    $face = is_array($result) ? ($result['plan']['families'][0]['faces'][0] ?? []) : [];

    assertFalseValue(is_wp_error($result), 'Warning font URL states should not abort the dry run.');
    assertSameValue('warning', (string) ($face['status'] ?? ''), 'CORS and temporary URL concerns should produce a non-blocking warning state.');
    assertSameValue(true, (bool) ($face['selected'] ?? false), 'Warning rows should remain selected and selectable.');
    assertSameValue('capped GET fallback', (string) ($face['validation']['method'] ?? ''), 'HEAD failures that allow fallback should use capped GET validation.');
    assertContainsValue('Self-hosted imports are not blocked by browser CORS', implode(' ', $face['warnings'] ?? []), 'CORS warning copy should clarify self-hosted mode is not blocked.');
    assertContainsValue('licensing, visitor privacy, and source availability', implode(' ', $result['plan']['warnings'] ?? []), 'Plan warnings should show remote-serving privacy, licensing, and availability copy.');
};

$tests['custom_css_final_import_downloads_selected_woff2_and_woff_to_custom_storage'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;
    global $scheduledEvents;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/final-import.css';
    $woff2Url = 'https://cdn.example.com/final-sans-400.woff2';
    $woffUrl = 'https://cdn.example.com/final-sans-700.woff';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Final Sans"; font-weight: 400; font-style: normal; src: url("' . $woff2Url . '") format("woff2"); }' . "\n"
            . '@font-face { font-family: "Final Sans"; font-weight: 700; font-style: italic; src: url("' . $woffUrl . '") format("woff"); }',
    ];
    $woff2Bytes = tastyFontsTestFontBytes('woff2');
    $woffBytes = tastyFontsTestFontBytes('woff');
    tastyFontsMockCustomCssFont($woff2Url, 'woff2', [], $woff2Bytes);
    tastyFontsMockCustomCssFont($woffUrl, 'woff', [], $woffBytes);

    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    assertFalseValue(is_wp_error($dryRun), 'A final-import fixture should dry-run successfully.');
    $snapshot = $services['custom_css_snapshots']->createSnapshot($dryRun);
    assertFalseValue(is_wp_error($snapshot), 'A final-import fixture should store a snapshot.');
    $faceIds = array_map(
        static fn (array $face): string => (string) ($face['id'] ?? ''),
        (array) ($dryRun['plan']['families'][0]['faces'] ?? [])
    );
    tastyFontsMockCustomCssFinalFont($woff2Url, 'woff2', $woff2Bytes);
    tastyFontsMockCustomCssFinalFont($woffUrl, 'woff', $woffBytes);

    $validated = $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'],
        'selected_face_ids' => $faceIds,
        'delivery_mode' => 'self_hosted',
        'family_fallbacks' => ['final-sans' => 'serif'],
    ]);
    assertFalseValue(is_wp_error($validated), 'The selected fixture faces should validate against the snapshot.');
    $result = $services['custom_css_final_import']->importSelfHosted($validated);

    assertFalseValue(is_wp_error($result), 'Selected WOFF2 and WOFF faces should import successfully.');
    assertSameValue('imported', (string) ($result['status'] ?? ''), 'All successful final imports should report imported status.');
    assertSameValue(2, (int) ($result['counts']['faces_imported'] ?? 0), 'Both selected font faces should be imported.');
    assertSameValue(true, is_dir((string) $services['storage']->getCustomRoot()), 'The custom provider storage root should be created.');

    $library = $services['imports']->all();
    $family = $library['final-sans'] ?? [];
    $profiles = is_array($family) ? (array) ($family['delivery_profiles'] ?? []) : [];
    $profile = reset($profiles);
    $profile = is_array($profile) ? $profile : [];
    $faces = (array) ($profile['faces'] ?? []);
    $firstPath = (string) ($faces[0]['files']['woff2'] ?? '');
    $secondPath = (string) ($faces[1]['files']['woff'] ?? '');

    assertSameValue('library_only', (string) ($family['publish_state'] ?? ''), 'New custom families should default to library-only.');
    assertSameValue('custom', (string) ($profile['provider'] ?? ''), 'Custom self-hosted profiles should use the custom provider key.');
    assertSameValue('self_hosted', (string) ($profile['type'] ?? ''), 'Custom final imports should save self-hosted delivery profiles.');
    assertSameValue($cssUrl, (string) ($profile['meta']['source_css_url'] ?? ''), 'Custom profiles should store source CSS URL metadata.');
    assertContainsValue('custom/final-sans/', $firstPath, 'Saved WOFF2 faces should use local relative paths under the custom root.');
    assertContainsValue('custom/final-sans/', $secondPath, 'Saved WOFF faces should use local relative paths under the custom root.');
    assertFalseValue(str_starts_with($firstPath, 'http'), 'Saved face paths should be relative, not remote URLs.');
    assertSameValue($woff2Bytes, (string) file_get_contents((string) $services['storage']->pathForRelativePath($firstPath)), 'The selected WOFF2 file should be downloaded during final import.');
    assertSameValue($woffBytes, (string) file_get_contents((string) $services['storage']->pathForRelativePath($secondPath)), 'The selected WOFF file should be downloaded during final import.');
    assertSameValue('serif', $services['family_metadata_repo']->getFallback('Final Sans'), 'Per-family fallback choices should be persisted by family name.');
    assertSameValue(true, in_array('tasty_fonts_regenerate_css', array_column($scheduledEvents, 'hook'), true), 'Generated assets should be refreshed after a successful final import.');
    assertSameValue('', (string) ($remoteGetCalls[count($remoteGetCalls) - 1]['args']['headers']['Range'] ?? ''), 'Final imports should download the full font file, not only the dry-run range.');
};

$tests['custom_css_final_import_rejects_partial_final_downloads'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/partial-download.css';
    $fontUrl = 'https://cdn.example.com/partial-download.woff2';
    $bytes = tastyFontsTestFontBytes('woff2');
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Partial Download"; font-weight: 400; font-style: normal; src: url("' . $fontUrl . '") format("woff2"); }',
    ];
    tastyFontsMockCustomCssFont($fontUrl, 'woff2', [], $bytes);
    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceId = (string) ($dryRun['plan']['families'][0]['faces'][0]['id'] ?? '');
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 206],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => (string) strlen($bytes),
            'content-range' => 'bytes 0-15/' . strlen($bytes),
        ],
        'body' => $bytes,
    ];
    $validated = $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'self_hosted',
    ]);
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;

    assertSameValue(true, is_wp_error($result), 'Final import should reject partial/range font responses instead of saving them.');
    assertSameValue('tasty_fonts_custom_css_import_failed', $result->get_error_code(), 'A sole partial response should make the final import fail.');
    assertSameValue([], $services['imports']->all(), 'Rejected partial final downloads should not persist import records.');
};

$tests['custom_css_final_import_rejects_full_hash_mismatches'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/hash-mismatch.css';
    $fontUrl = 'https://cdn.example.com/hash-mismatch.woff2';
    $reviewedBytes = tastyFontsTestFontBytes('woff2');
    $changedBytes = substr($reviewedBytes, 0, -1) . (substr($reviewedBytes, -1) === 'x' ? 'y' : 'x');
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Hash Mismatch"; font-weight: 400; font-style: normal; src: url("' . $fontUrl . '") format("woff2"); }',
    ];
    tastyFontsMockCustomCssFont($fontUrl, 'woff2', [], $reviewedBytes);

    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    assertFalseValue(is_wp_error($dryRun), 'Hash mismatch fixtures should dry-run successfully before final import.');
    $dryRun['plan']['families'][0]['faces'][0]['validation']['full_hash'] = hash('sha256', $reviewedBytes);
    $snapshot = $services['custom_css_snapshots']->createSnapshot($dryRun);
    $faceId = (string) ($dryRun['plan']['families'][0]['faces'][0]['id'] ?? '');
    tastyFontsMockCustomCssFinalFont($fontUrl, 'woff2', $changedBytes);

    $validated = $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'self_hosted',
    ]);
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;

    assertSameValue(true, is_wp_error($result), 'Final import should reject same-size files whose full hash differs from the reviewed snapshot.');
    assertSameValue('tasty_fonts_custom_css_import_failed', $result->get_error_code(), 'A sole full-hash mismatch should make the final import fail.');
    assertSameValue('tasty_fonts_custom_css_hash_mismatch', (string) ($result->get_error_data()['failed_faces'][0]['code'] ?? ''), 'Full-hash mismatches should surface the dedicated hash mismatch code.');
    assertSameValue([], $services['imports']->all(), 'Hash mismatch failures should not persist import records.');
};

$tests['custom_css_final_import_merges_existing_families_and_preserves_active_delivery_by_default'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $services['imports']->saveProfile('Merge Sans', 'merge-sans', [
        'id' => 'local-self-hosted',
        'provider' => 'local',
        'type' => 'self_hosted',
        'label' => 'Existing local delivery',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Merge Sans',
            'slug' => 'merge-sans',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => '',
            'files' => ['woff2' => 'merge-sans/existing.woff2'],
            'paths' => ['woff2' => 'merge-sans/existing.woff2'],
            'provider' => ['type' => 'local'],
        ]],
    ], 'published', true);
    $services['catalog']->invalidate();

    $cssUrl = 'https://assets.example.com/merge.css';
    $fontUrl = 'https://cdn.example.com/merge-sans.woff2';
    $bytes = tastyFontsTestFontBytes('woff2');
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "merge sans"; font-weight: 700; font-style: normal; src: url("' . $fontUrl . '") format("woff2"); }',
    ];
    tastyFontsMockCustomCssFont($fontUrl, 'woff2', [], $bytes);
    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceId = (string) ($dryRun['plan']['families'][0]['faces'][0]['id'] ?? '');
    tastyFontsMockCustomCssFinalFont($fontUrl, 'woff2', $bytes);
    $validated = $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'self_hosted',
    ]);
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;

    assertFalseValue(is_wp_error($result), 'Final custom imports should merge into matching existing families.');
    $family = $services['imports']->getFamily('merge-sans') ?? [];
    assertSameValue(2, count((array) ($family['delivery_profiles'] ?? [])), 'Existing families should receive an additional delivery profile instead of a duplicate family.');
    assertSameValue('Merge Sans', (string) ($family['family'] ?? ''), 'Slug-matched imports should use the existing canonical family name.');
    assertSameValue('Merge Sans', (string) ($result['families'][0]['family'] ?? ''), 'Final import responses should report the canonical family name for slug matches.');
    assertSameValue('local-self-hosted', (string) ($family['active_delivery_id'] ?? ''), 'Existing families should not switch active delivery unless requested.');
    assertSameValue('published', (string) ($family['publish_state'] ?? ''), 'Existing family publish state should be preserved by default.');

    resetTestState();
    $services = makeServiceGraph();
    $services['imports']->saveProfile('Merge Sans', 'merge-sans', [
        'id' => 'local-self-hosted',
        'provider' => 'local',
        'type' => 'self_hosted',
        'label' => 'Existing local delivery',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Merge Sans',
            'slug' => 'merge-sans',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => '',
            'files' => ['woff2' => 'merge-sans/existing.woff2'],
            'paths' => ['woff2' => 'merge-sans/existing.woff2'],
            'provider' => ['type' => 'local'],
        ]],
    ], 'published', true);
    $services['catalog']->invalidate();
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "merge sans"; font-weight: 700; font-style: normal; src: url("' . $fontUrl . '") format("woff2"); }',
    ];
    tastyFontsMockCustomCssFont($fontUrl, 'woff2', [], $bytes);
    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceId = (string) ($dryRun['plan']['families'][0]['faces'][0]['id'] ?? '');
    tastyFontsMockCustomCssFinalFont($fontUrl, 'woff2', $bytes);
    $validated = $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'self_hosted',
        'activate' => true,
    ]);
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;
    $family = $services['imports']->getFamily('merge-sans') ?? [];

    assertFalseValue(is_wp_error($result), 'Explicit activation should be allowed after successful custom import.');
    assertFalseValue((string) ($family['active_delivery_id'] ?? '') === 'local-self-hosted', 'Existing families should switch active delivery when explicitly requested.');
};

$tests['custom_css_final_import_partially_succeeds_and_rejects_material_differences'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/partial.css';
    $goodUrl = 'https://cdn.example.com/partial-good.woff2';
    $changedUrl = 'https://cdn.example.com/partial-changed.woff2';
    $goodBytes = tastyFontsTestFontBytes('woff2');
    $reviewedChangedBytes = tastyFontsTestFontBytes('woff2') . '-reviewed';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Partial Sans"; font-weight: 400; font-style: normal; src: url("' . $goodUrl . '") format("woff2"); }' . "\n"
            . '@font-face { font-family: "Partial Sans"; font-weight: 700; font-style: normal; src: url("' . $changedUrl . '") format("woff2"); }',
    ];
    tastyFontsMockCustomCssFont($goodUrl, 'woff2', [], $goodBytes);
    tastyFontsMockCustomCssFont($changedUrl, 'woff2', [], $reviewedChangedBytes);
    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceIds = array_map(
        static fn (array $face): string => (string) ($face['id'] ?? ''),
        (array) ($dryRun['plan']['families'][0]['faces'] ?? [])
    );
    tastyFontsMockCustomCssFinalFont($goodUrl, 'woff2', $goodBytes);
    tastyFontsMockCustomCssFinalFont($changedUrl, 'woff2', tastyFontsTestFontBytes('woff2'));
    $validated = $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => $faceIds,
        'delivery_mode' => 'self_hosted',
    ]);
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;

    assertFalseValue(is_wp_error($result), 'A material difference in one face should not block unrelated selected faces.');
    assertSameValue('partial', (string) ($result['status'] ?? ''), 'Partial success should report partial status.');
    assertSameValue(1, (int) ($result['counts']['faces_imported'] ?? 0), 'Only the unchanged face should import.');
    assertSameValue(1, (int) ($result['counts']['faces_failed'] ?? 0), 'The changed face should fail with a material-difference message.');
    assertSameValue('tasty_fonts_custom_css_material_difference', (string) ($result['faces']['failed'][0]['code'] ?? ''), 'Final import should reject material differences from reviewed size data.');
    $family = $services['imports']->getFamily('partial-sans') ?? [];
    $profiles = is_array($family) ? (array) ($family['delivery_profiles'] ?? []) : [];
    $profile = reset($profiles);
    $profile = is_array($profile) ? $profile : [];
    assertSameValue(1, count((array) ($profile['faces'] ?? [])), 'Partial success should persist only successful faces.');
};

$tests['custom_css_final_import_preserves_custom_subset_faces_in_generated_css'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/subsets.css';
    $latinUrl = 'https://cdn.example.com/subset-latin.woff2';
    $cyrillicUrl = 'https://cdn.example.com/subset-cyrillic.woff2';
    $latinBytes = tastyFontsTestFontBytes('woff2') . '-latin';
    $cyrillicBytes = tastyFontsTestFontBytes('woff2') . '-cyrillic';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Subset Sans"; font-weight: 400; font-style: normal; src: url("' . $latinUrl . '") format("woff2"); unicode-range: U+0000-00FF; }' . "\n"
            . '@font-face { font-family: "Subset Sans"; font-weight: 400; font-style: normal; src: url("' . $cyrillicUrl . '") format("woff2"); unicode-range: U+0400-052F; }',
    ];
    tastyFontsMockCustomCssFont($latinUrl, 'woff2', [], $latinBytes);
    tastyFontsMockCustomCssFont($cyrillicUrl, 'woff2', [], $cyrillicBytes);
    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceIds = array_map(
        static fn (array $face): string => (string) ($face['id'] ?? ''),
        (array) ($dryRun['plan']['families'][0]['faces'] ?? [])
    );
    tastyFontsMockCustomCssFinalFont($latinUrl, 'woff2', $latinBytes);
    tastyFontsMockCustomCssFinalFont($cyrillicUrl, 'woff2', $cyrillicBytes);
    $validated = $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => $faceIds,
        'delivery_mode' => 'self_hosted',
        'publish' => true,
    ]);
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;

    assertFalseValue(is_wp_error($result), 'Subset custom faces should import successfully.');
    $services['settings']->saveSettings(['unicode_range_mode' => \TastyFonts\Support\FontUtils::UNICODE_RANGE_MODE_PRESERVE]);
    $services['assets']->refreshGeneratedAssets(false, false);
    $catalog = $services['catalog']->getCatalog();
    $family = $catalog['Subset Sans'] ?? [];
    assertSameValue(2, count((array) ($family['faces'] ?? [])), 'Custom subset faces with identical weight/style should remain distinct in the catalog.');
    $css = $services['assets']->getCss();
    assertContainsValue('unicode-range:U+0000-00FF', $css, 'Generated CSS should include the latin custom subset unicode range.');
    assertContainsValue('unicode-range:U+0400-052F', $css, 'Generated CSS should include the cyrillic custom subset unicode range.');
};

$tests['custom_css_remote_final_import_saves_remote_profiles_and_generates_controlled_css'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;
    global $remoteRequestCalls;
    global $scheduledEvents;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/remote-final.css';
    $fontUrl = 'https://cdn.example.com/fonts/remote-sans-latin.woff2?token=temporary';
    $fontBytes = tastyFontsTestFontBytes('woff2') . '-remote';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Remote Sans"; font-weight: 400; font-style: normal; src: url("' . $fontUrl . '") format("woff2"); unicode-range: U+0000-00FF; }',
    ];
    tastyFontsMockCustomCssFont($fontUrl, 'woff2', ['access-control-allow-origin' => ''], $fontBytes);

    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    assertFalseValue(is_wp_error($dryRun), 'Remote final import fixtures should dry-run successfully.');
    $face = (array) ($dryRun['plan']['families'][0]['faces'][0] ?? []);
    assertSameValue('warning', (string) ($face['status'] ?? ''), 'Remote warning faces should remain visible before confirmation.');
    assertContainsValue('licensing, visitor privacy, and source availability', implode(' ', (array) ($dryRun['plan']['warnings'] ?? [])), 'Remote-serving warnings should remain in the review plan before confirmation.');

    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceId = (string) ($face['id'] ?? '');
    $validated = is_array($snapshot) ? $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'remote',
        'family_fallbacks' => ['remote-sans' => 'serif'],
        'publish' => true,
    ]) : $snapshot;
    assertFalseValue(is_wp_error($validated), 'Remote final import contracts should validate against the server snapshot.');

    $remoteGetCalls = [];
    $remoteRequestCalls = [];
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;

    assertFalseValue(is_wp_error($result), 'Selected remote-serving faces should save successfully.');
    assertSameValue('remote', (string) ($result['delivery_mode'] ?? ''), 'Remote final import results should report remote delivery mode.');
    assertSameValue(1, (int) ($result['counts']['remote_urls_saved'] ?? 0), 'Remote final imports should report saved remote URLs.');
    assertSameValue(0, (int) ($result['counts']['files_written'] ?? -1), 'Remote final imports should not write local font files.');
    assertSameValue(true, isset($remoteRequestCalls[0]) && (string) ($remoteRequestCalls[0]['method'] ?? '') === 'HEAD', 'Remote final import should perform lightweight HEAD revalidation.');
    assertSameValue('bytes=0-15', (string) ($remoteGetCalls[0]['args']['headers']['Range'] ?? ''), 'Remote final import should revalidate signatures with a capped range request.');

    $family = $services['imports']->getFamily('remote-sans') ?? [];
    $profiles = is_array($family) ? (array) ($family['delivery_profiles'] ?? []) : [];
    $profile = reset($profiles);
    $profile = is_array($profile) ? $profile : [];
    $faces = (array) ($profile['faces'] ?? []);
    $savedFace = (array) ($faces[0] ?? []);

    assertSameValue('published', (string) ($family['manual_publish_state'] ?? ''), 'New custom remote families should match the self-hosted manual publish state when explicitly published.');
    assertSameValue('published', (string) ($family['publish_state'] ?? ''), 'Explicit publish should publish the new custom remote family after a successful import.');
    assertSameValue('custom', (string) ($profile['provider'] ?? ''), 'Remote custom CSS profiles should use the custom provider key.');
    assertSameValue('cdn', (string) ($profile['type'] ?? ''), 'Remote custom CSS profiles should use the remote/cdn delivery type.');
    assertSameValue('remote', (string) ($profile['meta']['delivery_mode'] ?? ''), 'Remote custom CSS profile metadata should identify remote serving.');
    assertSameValue($cssUrl, (string) ($profile['meta']['source_css_url'] ?? ''), 'Remote custom CSS profiles should store the source CSS URL.');
    assertSameValue('assets.example.com', (string) ($profile['meta']['source_host'] ?? ''), 'Remote custom CSS profiles should store the source CSS host.');
    assertSameValue($fontUrl, (string) ($savedFace['files']['woff2'] ?? ''), 'Remote custom CSS faces should save the absolute remote font URL.');
    assertSameValue([], (array) ($savedFace['paths'] ?? []), 'Remote custom CSS faces should not save local relative paths.');
    assertSameValue($fontUrl, (string) ($savedFace['provider']['remote_url'] ?? ''), 'Remote custom face metadata should retain the reviewed remote URL.');
    assertSameValue('serif', $services['family_metadata_repo']->getFallback('Remote Sans'), 'Remote imports should persist per-family fallback choices.');
    assertSameValue(true, in_array('tasty_fonts_regenerate_css', array_column($scheduledEvents, 'hook'), true), 'Generated assets should refresh after a successful remote import.');

    $services['settings']->saveSettings([
        'font_display' => 'optional',
        'unicode_range_mode' => \TastyFonts\Support\FontUtils::UNICODE_RANGE_MODE_PRESERVE,
    ]);
    $services['settings']->saveFamilyFontDisplay('Remote Sans', 'fallback');
    $services['assets']->refreshGeneratedAssets(false, false);
    $css = $services['assets']->getCss();
    $externalStylesheets = $services['planner']->getExternalStylesheets();

    assertContainsValue('@font-face', $css, 'Remote custom CSS profiles should emit Tasty Fonts-generated @font-face rules.');
    assertContainsValue('url("' . $fontUrl . '") format("woff2")', $css, 'Generated CSS should point to the saved remote font URL.');
    assertContainsValue('font-display:fallback', $css, 'Existing per-family font-display settings should control remote custom CSS output.');
    assertContainsValue('unicode-range:U+0000-00FF;', $css, 'Generated CSS should preserve custom remote unicode-range values.');
    assertNotContainsValue($cssUrl, $css, 'Generated CSS should not include or import the original source stylesheet URL.');
    assertSameValue([], $externalStylesheets, 'Custom remote CSS profiles should not enqueue the original third-party stylesheet.');
};

$tests['custom_css_remote_final_import_rejects_material_revalidation_differences'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;
    global $remoteRequestResponses;

    $services = makeServiceGraph();
    $cssUrl = 'https://assets.example.com/remote-changed.css';
    $fontUrl = 'https://cdn.example.com/fonts/remote-changed.woff2';
    $reviewedBytes = tastyFontsTestFontBytes('woff2') . '-reviewed';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Remote Changed"; font-weight: 400; font-style: normal; src: url("' . $fontUrl . '") format("woff2"); }',
    ];
    tastyFontsMockCustomCssFont($fontUrl, 'woff2', [], $reviewedBytes);
    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceId = (string) ($dryRun['plan']['families'][0]['faces'][0]['id'] ?? '');

    $changedBytes = tastyFontsTestFontBytes('woff2') . '-changed-materially';
    $remoteRequestResponses['HEAD ' . $fontUrl]['headers'] = new ArrayObject([
        'content-type' => 'font/woff2',
        'content-length' => (string) strlen($changedBytes),
        'access-control-allow-origin' => '*',
    ]);
    $remoteGetResponses[$fontUrl]['headers'] = new ArrayObject([
        'content-type' => 'font/woff2',
        'content-length' => '16',
        'content-range' => 'bytes 0-15/' . strlen($changedBytes),
        'access-control-allow-origin' => '*',
    ]);
    $validated = is_array($snapshot) ? $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'remote',
    ]) : $snapshot;
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;

    assertSameValue(true, is_wp_error($result), 'Remote final import should fail when the selected remote face materially differs from the reviewed snapshot.');
    assertSameValue('tasty_fonts_custom_css_import_failed', $result->get_error_code(), 'A sole changed remote face should make the final import fail.');
    assertSameValue('tasty_fonts_custom_css_material_difference', (string) ($result->get_error_data()['failed_faces'][0]['code'] ?? ''), 'Remote final import should report the material difference as the failed face reason.');
    assertSameValue([], $services['imports']->all(), 'Changed remote final imports should not persist library data.');
};

$tests['custom_css_dry_run_identifies_existing_duplicate_faces_for_review'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $services['imports']->saveProfile('Duplicate Sans', 'duplicate-sans', [
        'id' => 'custom-old-self-hosted',
        'provider' => 'custom',
        'type' => 'self_hosted',
        'label' => 'Existing custom CSS',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Duplicate Sans',
            'slug' => 'duplicate-sans',
            'source' => 'custom',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => 'U+0000-00FF',
            'files' => ['woff2' => 'custom/duplicate-sans/old/duplicate.woff2'],
            'paths' => ['woff2' => 'custom/duplicate-sans/old/duplicate.woff2'],
            'provider' => ['type' => 'custom_css'],
        ]],
        'meta' => ['source_type' => 'custom_css_url'],
    ], 'published', true);
    $services['imports']->saveProfile('Duplicate Sans', 'duplicate-sans', [
        'id' => 'local-self-hosted',
        'provider' => 'local',
        'type' => 'self_hosted',
        'label' => 'Local Upload',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Duplicate Sans',
            'slug' => 'duplicate-sans',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => 'U+0000-00FF',
            'files' => ['woff2' => 'upload/duplicate-sans/local.woff2'],
            'paths' => ['woff2' => 'upload/duplicate-sans/local.woff2'],
            'provider' => ['type' => 'local'],
        ]],
    ], 'published', false);
    $services['catalog']->invalidate();

    $cssUrl = 'https://assets.example.com/duplicates.css';
    $fontUrl = 'https://cdn.example.com/duplicate-sans.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Duplicate Sans"; font-weight: 400; font-style: normal; src: url("' . $fontUrl . '") format("woff2"); unicode-range: U+0000-00FF; }',
    ];
    tastyFontsMockCustomCssFont($fontUrl);

    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $face = is_array($dryRun) ? (array) ($dryRun['plan']['families'][0]['faces'][0] ?? []) : [];
    $matches = (array) ($face['duplicate_matches'] ?? []);

    assertFalseValue(is_wp_error($dryRun), 'Duplicate fixtures should still return a dry-run review plan.');
    assertSameValue(1, (int) ($dryRun['plan']['counts']['duplicate_faces'] ?? 0), 'Dry-run counts should identify duplicate faces.');
    assertSameValue(1, (int) ($dryRun['plan']['counts']['replaceable_duplicate_faces'] ?? 0), 'Dry-run counts should identify replaceable custom CSS matches.');
    assertSameValue('skip', (string) ($face['duplicate_summary']['default_action'] ?? ''), 'Duplicate review metadata should advertise skip as the default action.');
    assertSameValue(2, count($matches), 'The review face should list both custom and protected matching profiles.');
    assertSameValue(['custom-old-self-hosted', 'local-self-hosted'], array_column($matches, 'delivery_id'), 'Duplicate matching should include matching delivery profile IDs.');
    assertSameValue(['self_hosted', 'self_hosted'], array_column($matches, 'delivery_type'), 'Duplicate matching should include delivery type.');
    assertSameValue([true, false], array_column($matches, 'replaceable'), 'Only custom CSS matches should be marked replaceable.');
};

$tests['custom_css_final_import_skips_duplicate_custom_faces_by_default'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $services['imports']->saveProfile('Skip Sans', 'skip-sans', [
        'id' => 'custom-old-self-hosted',
        'provider' => 'custom',
        'type' => 'self_hosted',
        'label' => 'Existing custom CSS',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Skip Sans',
            'slug' => 'skip-sans',
            'source' => 'custom',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => '',
            'files' => ['woff2' => 'custom/skip-sans/old/skip.woff2'],
            'paths' => ['woff2' => 'custom/skip-sans/old/skip.woff2'],
            'provider' => ['type' => 'custom_css'],
        ]],
        'meta' => ['source_type' => 'custom_css_url'],
    ], 'published', true);
    $services['catalog']->invalidate();

    $cssUrl = 'https://assets.example.com/skip-duplicates.css';
    $fontUrl = 'https://cdn.example.com/skip-sans.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Skip Sans"; font-weight: 400; font-style: normal; src: url("' . $fontUrl . '") format("woff2"); }',
    ];
    tastyFontsMockCustomCssFont($fontUrl);
    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceId = (string) ($dryRun['plan']['families'][0]['faces'][0]['id'] ?? '');
    tastyFontsMockCustomCssFinalFont($fontUrl);
    $validated = is_array($snapshot) ? $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'self_hosted',
    ]) : $snapshot;
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;
    $family = $services['imports']->getFamily('skip-sans') ?? [];

    assertFalseValue(is_wp_error($result), 'Default duplicate handling should skip instead of failing.');
    assertSameValue('skipped', (string) ($result['status'] ?? ''), 'All-duplicate final imports should report skipped.');
    assertSameValue(1, (int) ($result['counts']['faces_skipped'] ?? 0), 'Default duplicate handling should report skipped faces.');
    assertSameValue(1, count((array) ($family['delivery_profiles'] ?? [])), 'Skipping duplicates should not add another delivery profile.');
};

$tests['custom_css_final_import_duplicate_matching_includes_variable_axes'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $axes = ['WGHT' => ['min' => '300', 'default' => '400', 'max' => '700']];
    $variationDefaults = ['WGHT' => '400'];
    $services['imports']->saveProfile('Variable Sans', 'variable-sans', [
        'id' => 'custom-old-variable',
        'provider' => 'custom',
        'type' => 'self_hosted',
        'label' => 'Existing variable custom CSS',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Variable Sans',
            'slug' => 'variable-sans',
            'source' => 'custom',
            'weight' => '300..700',
            'style' => 'normal',
            'unicode_range' => '',
            'files' => ['woff2' => 'custom/variable-sans/old/variable.woff2'],
            'paths' => ['woff2' => 'custom/variable-sans/old/variable.woff2'],
            'provider' => ['type' => 'custom_css'],
            'is_variable' => true,
            'axes' => $axes,
            'variation_defaults' => $variationDefaults,
        ]],
        'meta' => ['source_type' => 'custom_css_url'],
    ], 'published', true);
    $services['catalog']->invalidate();

    $matchingUrl = 'https://cdn.example.com/variable-sans-matching.woff2';
    tastyFontsMockCustomCssFinalFont($matchingUrl);
    $matching = $services['custom_css_final_import']->importSelfHosted([
        'delivery_mode' => 'self_hosted',
        'duplicate_handling' => 'skip',
        'source' => ['url' => 'https://assets.example.com/variable.css', 'host' => 'assets.example.com'],
        'families' => [[
            'family' => 'Variable Sans',
            'slug' => 'variable-sans',
            'fallback' => 'sans-serif',
            'faces' => [[
                'id' => 'variable-matching-face',
                'family' => 'Variable Sans',
                'slug' => 'variable-sans',
                'weight' => '300..700',
                'style' => 'normal',
                'format' => 'woff2',
                'url' => $matchingUrl,
                'unicode_range' => '',
                'is_variable' => true,
                'axes' => $axes,
                'variation_defaults' => $variationDefaults,
            ]],
        ]],
    ]);
    $matchingFamily = $services['imports']->getFamily('variable-sans') ?? [];

    assertFalseValue(is_wp_error($matching), 'Matching variable-axis duplicates should be skipped without failing.');
    assertSameValue('skipped', (string) ($matching['status'] ?? ''), 'Matching variable-axis duplicates should use the default skip behavior.');
    assertSameValue(1, count((array) ($matchingFamily['delivery_profiles'] ?? [])), 'Skipping a matching variable duplicate should not add a profile.');

    $mismatchUrl = 'https://cdn.example.com/variable-sans-mismatch.woff2';
    $mismatchAxes = ['WGHT' => ['min' => '300', 'default' => '500', 'max' => '700']];
    tastyFontsMockCustomCssFinalFont($mismatchUrl);
    $mismatch = $services['custom_css_final_import']->importSelfHosted([
        'delivery_mode' => 'self_hosted',
        'duplicate_handling' => 'skip',
        'source' => ['url' => 'https://assets.example.com/variable.css', 'host' => 'assets.example.com'],
        'families' => [[
            'family' => 'Variable Sans',
            'slug' => 'variable-sans',
            'fallback' => 'sans-serif',
            'faces' => [[
                'id' => 'variable-mismatch-face',
                'family' => 'Variable Sans',
                'slug' => 'variable-sans',
                'weight' => '300..700',
                'style' => 'normal',
                'format' => 'woff2',
                'url' => $mismatchUrl,
                'unicode_range' => '',
                'is_variable' => true,
                'axes' => $mismatchAxes,
                'variation_defaults' => ['WGHT' => '500'],
            ]],
        ]],
    ]);
    $mismatchFamily = $services['imports']->getFamily('variable-sans') ?? [];
    $profiles = (array) ($mismatchFamily['delivery_profiles'] ?? []);
    $newProfile = array_values(array_filter($profiles, static fn (mixed $profile): bool => is_array($profile) && ($profile['id'] ?? '') !== 'custom-old-variable'))[0] ?? [];
    $newFace = is_array($newProfile) ? (array) ($newProfile['faces'][0] ?? []) : [];

    assertFalseValue(is_wp_error($mismatch), 'A variable face with different axes should not be treated as a duplicate.');
    assertSameValue('imported', (string) ($mismatch['status'] ?? ''), 'Axis-mismatched variable faces should import normally.');
    assertSameValue(2, count($profiles), 'Axis-mismatched variable faces should add a separate profile.');
    assertSameValue('500', (string) ($newFace['variation_defaults']['WGHT'] ?? ''), 'Imported variable custom CSS faces should preserve variation defaults for future duplicate matching.');
};

$tests['custom_css_final_import_replaces_matching_custom_faces_and_deletes_old_unreferenced_files'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $oldRelativePath = 'custom/replace-sans/old/replace.woff2';
    $oldAbsolutePath = $services['storage']->pathForRelativePath($oldRelativePath);
    $services['storage']->writeAbsoluteFile((string) $oldAbsolutePath, tastyFontsTestFontBytes('woff2') . '-old');
    $services['imports']->saveProfile('Replace Sans', 'replace-sans', [
        'id' => 'custom-old-self-hosted',
        'provider' => 'custom',
        'type' => 'self_hosted',
        'label' => 'Existing custom CSS',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Replace Sans',
            'slug' => 'replace-sans',
            'source' => 'custom',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => 'U+0000-00FF',
            'files' => ['woff2' => $oldRelativePath],
            'paths' => ['woff2' => $oldRelativePath],
            'provider' => ['type' => 'custom_css'],
        ]],
        'meta' => ['source_type' => 'custom_css_url'],
    ], 'published', true);
    $services['catalog']->invalidate();

    $cssUrl = 'https://assets.example.com/replace.css';
    $fontUrl = 'https://cdn.example.com/replace-sans.woff2';
    $newBytes = tastyFontsTestFontBytes('woff2') . '-new';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Replace Sans"; font-weight: 400; font-style: normal; src: url("' . $fontUrl . '") format("woff2"); unicode-range: U+0000-00FF; }',
    ];
    tastyFontsMockCustomCssFont($fontUrl, 'woff2', [], $newBytes);
    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceId = (string) ($dryRun['plan']['families'][0]['faces'][0]['id'] ?? '');
    tastyFontsMockCustomCssFinalFont($fontUrl, 'woff2', $newBytes);
    $validated = is_array($snapshot) ? $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'self_hosted',
        'duplicate_handling' => 'replace_custom',
    ]) : $snapshot;
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;
    $family = $services['imports']->getFamily('replace-sans') ?? [];
    $profiles = (array) ($family['delivery_profiles'] ?? []);

    assertFalseValue(is_wp_error($result), 'Replace-custom final imports should succeed for matching custom CSS faces.');
    assertSameValue(1, (int) ($result['counts']['faces_replaced'] ?? 0), 'Replacement should report replaced custom faces.');
    assertSameValue(1, (int) ($result['counts']['old_files_deleted'] ?? 0), 'Old unreferenced custom files should be deleted after replacement succeeds.');
    assertSameValue(false, is_string($oldAbsolutePath) && file_exists($oldAbsolutePath), 'Old custom files should be deleted only after the new replacement profile is saved.');
    assertSameValue(false, isset($profiles['custom-old-self-hosted']), 'The fully replaced old custom profile should be removed from metadata.');
    assertSameValue(1, count($profiles), 'Replacement should leave only the new custom profile for this fixture.');
};

$tests['custom_css_final_import_replace_custom_skips_protected_non_custom_profiles'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $protectedRelativePath = 'upload/protected-sans/protected.woff2';
    $protectedAbsolutePath = $services['storage']->pathForRelativePath($protectedRelativePath);
    $services['storage']->writeAbsoluteFile((string) $protectedAbsolutePath, tastyFontsTestFontBytes('woff2'));
    $services['imports']->saveProfile('Protected Sans', 'protected-sans', [
        'id' => 'local-self-hosted',
        'provider' => 'local',
        'type' => 'self_hosted',
        'label' => 'Local Upload',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Protected Sans',
            'slug' => 'protected-sans',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => '',
            'files' => ['woff2' => $protectedRelativePath],
            'paths' => ['woff2' => $protectedRelativePath],
            'provider' => ['type' => 'local'],
        ]],
    ], 'published', true);
    $services['catalog']->invalidate();

    $cssUrl = 'https://assets.example.com/protected.css';
    $fontUrl = 'https://cdn.example.com/protected-sans.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Protected Sans"; font-weight: 400; font-style: normal; src: url("' . $fontUrl . '") format("woff2"); }',
    ];
    tastyFontsMockCustomCssFont($fontUrl);
    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceId = (string) ($dryRun['plan']['families'][0]['faces'][0]['id'] ?? '');
    tastyFontsMockCustomCssFinalFont($fontUrl);
    $validated = is_array($snapshot) ? $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'self_hosted',
        'duplicate_handling' => 'replace_custom',
    ]) : $snapshot;
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;
    $family = $services['imports']->getFamily('protected-sans') ?? [];

    assertFalseValue(is_wp_error($result), 'Protected duplicate replacements should skip rather than mutate protected providers.');
    assertSameValue('skipped', (string) ($result['status'] ?? ''), 'A protected-only duplicate should be skipped even when replace custom is requested.');
    assertSameValue(['local-self-hosted'], array_keys((array) ($family['delivery_profiles'] ?? [])), 'Replacement must not target local upload profiles.');
    assertSameValue(true, is_string($protectedAbsolutePath) && file_exists($protectedAbsolutePath), 'Replacement must not delete local upload files.');
};

$tests['custom_css_final_import_keeps_shared_custom_files_when_remaining_profiles_reference_them'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $sharedRelativePath = 'custom/shared-sans/shared/shared.woff2';
    $sharedAbsolutePath = $services['storage']->pathForRelativePath($sharedRelativePath);
    $services['storage']->writeAbsoluteFile((string) $sharedAbsolutePath, tastyFontsTestFontBytes('woff2') . '-shared');
    $baseProfile = [
        'provider' => 'custom',
        'type' => 'self_hosted',
        'label' => 'Existing custom CSS',
        'variants' => ['regular'],
        'meta' => ['source_type' => 'custom_css_url'],
    ];
    $services['imports']->saveProfile('Shared Sans', 'shared-sans', $baseProfile + [
        'id' => 'custom-old-self-hosted',
        'faces' => [[
            'family' => 'Shared Sans',
            'slug' => 'shared-sans',
            'source' => 'custom',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => '',
            'files' => ['woff2' => $sharedRelativePath],
            'paths' => ['woff2' => $sharedRelativePath],
            'provider' => ['type' => 'custom_css'],
        ]],
    ], 'published', true);
    $services['imports']->saveProfile('Shared Sans', 'shared-sans', $baseProfile + [
        'id' => 'custom-shared-reference',
        'variants' => ['700'],
        'faces' => [[
            'family' => 'Shared Sans',
            'slug' => 'shared-sans',
            'source' => 'custom',
            'weight' => '700',
            'style' => 'normal',
            'unicode_range' => '',
            'files' => ['woff2' => $sharedRelativePath],
            'paths' => ['woff2' => $sharedRelativePath],
            'provider' => ['type' => 'custom_css'],
        ]],
    ], 'published', false);
    $services['catalog']->invalidate();

    $cssUrl = 'https://assets.example.com/shared.css';
    $fontUrl = 'https://cdn.example.com/shared-sans.woff2';
    $newBytes = tastyFontsTestFontBytes('woff2') . '-new-shared';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Shared Sans"; font-weight: 400; font-style: normal; src: url("' . $fontUrl . '") format("woff2"); }',
    ];
    tastyFontsMockCustomCssFont($fontUrl, 'woff2', [], $newBytes);
    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceId = (string) ($dryRun['plan']['families'][0]['faces'][0]['id'] ?? '');
    tastyFontsMockCustomCssFinalFont($fontUrl, 'woff2', $newBytes);
    $validated = is_array($snapshot) ? $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'self_hosted',
        'duplicate_handling' => 'replace_custom',
    ]) : $snapshot;
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;

    assertFalseValue(is_wp_error($result), 'Shared-path replacement fixtures should import successfully.');
    assertSameValue(0, (int) ($result['counts']['old_files_deleted'] ?? -1), 'Shared old custom files should not be deleted while another profile still references them.');
    assertSameValue(true, is_string($sharedAbsolutePath) && file_exists($sharedAbsolutePath), 'Shared old custom files should remain on disk.');
};

$tests['custom_css_final_import_failed_replacements_leave_old_profiles_and_files_intact'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $oldRelativePath = 'custom/rollback-sans/old/rollback.woff2';
    $oldAbsolutePath = $services['storage']->pathForRelativePath($oldRelativePath);
    $services['storage']->writeAbsoluteFile((string) $oldAbsolutePath, tastyFontsTestFontBytes('woff2') . '-old');
    $services['imports']->saveProfile('Rollback Sans', 'rollback-sans', [
        'id' => 'custom-old-self-hosted',
        'provider' => 'custom',
        'type' => 'self_hosted',
        'label' => 'Existing custom CSS',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Rollback Sans',
            'slug' => 'rollback-sans',
            'source' => 'custom',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => '',
            'files' => ['woff2' => $oldRelativePath],
            'paths' => ['woff2' => $oldRelativePath],
            'provider' => ['type' => 'custom_css'],
        ]],
        'meta' => ['source_type' => 'custom_css_url'],
    ], 'published', true);
    $services['catalog']->invalidate();

    $cssUrl = 'https://assets.example.com/rollback.css';
    $fontUrl = 'https://cdn.example.com/rollback-sans.woff2';
    $reviewedBytes = tastyFontsTestFontBytes('woff2') . '-reviewed';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Rollback Sans"; font-weight: 400; font-style: normal; src: url("' . $fontUrl . '") format("woff2"); }',
    ];
    tastyFontsMockCustomCssFont($fontUrl, 'woff2', [], $reviewedBytes);
    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceId = (string) ($dryRun['plan']['families'][0]['faces'][0]['id'] ?? '');
    tastyFontsMockCustomCssFinalFont($fontUrl, 'woff2', tastyFontsTestFontBytes('woff2') . '-changed');
    $validated = is_array($snapshot) ? $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'self_hosted',
        'duplicate_handling' => 'replace_custom',
    ]) : $snapshot;
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;
    $family = $services['imports']->getFamily('rollback-sans') ?? [];

    assertSameValue(true, is_wp_error($result), 'A sole failed replacement should fail the import.');
    assertSameValue(['custom-old-self-hosted'], array_keys((array) ($family['delivery_profiles'] ?? [])), 'Failed replacements should leave old custom profile metadata intact.');
    assertSameValue(true, is_string($oldAbsolutePath) && file_exists($oldAbsolutePath), 'Failed replacements should leave old custom files intact.');
};

$tests['custom_css_final_import_self_hosted_writes_faces_when_filesystem_mkdir_is_non_recursive'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;
    global $wp_filesystem;

    $services = makeServiceGraph();

    if ($wp_filesystem instanceof TestWpFilesystem) {
        $wp_filesystem->recursiveMkdir = false;
    }

    $cssUrl = 'https://assets.example.com/non-recursive-mkdir.css';
    $fontUrl = 'https://cdn.example.com/non-recursive-mkdir.woff2';
    $fontBytes = tastyFontsTestFontBytes('woff2') . '-nested';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Nested Sans"; font-weight: 400; font-style: normal; src: url("' . $fontUrl . '") format("woff2"); }',
    ];

    tastyFontsMockCustomCssFont($fontUrl, 'woff2', [], $fontBytes);
    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceId = (string) ($dryRun['plan']['families'][0]['faces'][0]['id'] ?? '');

    tastyFontsMockCustomCssFinalFont($fontUrl, 'woff2', $fontBytes);
    $validated = is_array($snapshot) ? $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'self_hosted',
    ]) : $snapshot;
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;

    $importedPath = (string) ($result['faces']['imported'][0]['path'] ?? '');
    $absoluteImportedPath = $services['storage']->pathForRelativePath($importedPath);

    assertFalseValue(is_wp_error($result), 'Self-hosted custom CSS final imports should not fail when filesystem mkdir is non-recursive.');
    assertSameValue('imported', (string) ($result['status'] ?? ''), 'Nested custom import paths should still report imported when storage write succeeds.');
    assertSameValue(1, (int) ($result['counts']['files_written'] ?? 0), 'Nested custom import paths should count written files.');
    assertSameValue(true, $importedPath !== '' && is_string($absoluteImportedPath) && file_exists($absoluteImportedPath), 'Nested custom delivery directories should be created so downloaded files persist.');
};

$tests['custom_css_remote_final_import_replaces_matching_custom_remote_profiles'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $oldRemoteUrl = 'https://cdn.example.com/remote-duplicate.woff2';
    $services['imports']->saveProfile('Remote Duplicate', 'remote-duplicate', [
        'id' => 'custom-old-remote',
        'provider' => 'custom',
        'type' => 'cdn',
        'label' => 'Remote custom CSS',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Remote Duplicate',
            'slug' => 'remote-duplicate',
            'source' => 'custom',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => 'U+0000-00FF',
            'files' => ['woff2' => $oldRemoteUrl],
            'paths' => [],
            'provider' => ['type' => 'custom_css', 'remote_url' => $oldRemoteUrl],
        ]],
        'meta' => ['source_type' => 'custom_css_url', 'delivery_mode' => 'remote'],
    ], 'published', true);
    $services['catalog']->invalidate();

    $cssUrl = 'https://assets.example.com/remote-duplicate.css';
    $newRemoteUrl = 'https://cdn.example.com/remote-duplicate-new.woff2';
    $bytes = tastyFontsTestFontBytes('woff2') . '-remote-replace';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face { font-family: "Remote Duplicate"; font-weight: 400; font-style: normal; src: url("' . $newRemoteUrl . '") format("woff2"); unicode-range: U+0000-00FF; }',
    ];
    tastyFontsMockCustomCssFont($newRemoteUrl, 'woff2', [], $bytes);
    $dryRun = $services['custom_css_import']->dryRun($cssUrl);
    $snapshot = is_array($dryRun) ? $services['custom_css_snapshots']->createSnapshot($dryRun) : [];
    $faceId = (string) ($dryRun['plan']['families'][0]['faces'][0]['id'] ?? '');
    $validated = is_array($snapshot) ? $services['custom_css_snapshots']->validateFinalImportContract([
        'snapshot_token' => $snapshot['token'] ?? '',
        'selected_face_ids' => [$faceId],
        'delivery_mode' => 'remote',
        'duplicate_handling' => 'replace_custom',
    ]) : $snapshot;
    $result = is_array($validated) ? $services['custom_css_final_import']->importSelfHosted($validated) : $validated;
    $family = $services['imports']->getFamily('remote-duplicate') ?? [];
    $profiles = (array) ($family['delivery_profiles'] ?? []);
    $newProfile = end($profiles);
    $newProfile = is_array($newProfile) ? $newProfile : [];

    assertFalseValue(is_wp_error($result), 'Remote replace-custom imports should replace matching custom remote profiles.');
    assertSameValue('remote', (string) ($result['delivery_mode'] ?? ''), 'Remote replacement results should keep remote delivery mode.');
    assertSameValue(1, (int) ($result['counts']['faces_replaced'] ?? 0), 'Remote replacement should report replaced custom faces.');
    assertSameValue(false, isset($profiles['custom-old-remote']), 'Remote replacement should remove the old matching custom remote profile.');
    assertSameValue($newRemoteUrl, (string) ($newProfile['faces'][0]['files']['woff2'] ?? ''), 'Remote replacement should save the new validated remote URL.');
};

$tests['bunny_import_service_imports_and_catalogs_bunny_faces'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = $services['bunny']->buildCssUrl('Inter', ['regular', '700']);
    $greekUrl = 'https://fonts.bunny.net/inter/files/inter-greek-400-normal.woff2';
    $latinUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $boldUrl = 'https://fonts.bunny.net/inter/files/inter-latin-700-normal.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-greek-400-normal.woff2) format('woff2');
  unicode-range: U+0370-03FF;
}
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 700;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-700-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$greekUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'greek-font-data',
    ];
    $remoteGetResponses[$latinUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];
    $remoteGetResponses[$boldUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'bold-font-data',
    ];

    $importProvider = '';
    $importStatus = '';
    add_action(
        'tasty_fonts_after_import',
        static function (array $result, string $provider) use (&$importProvider, &$importStatus): void {
            $importProvider = $provider;
            $importStatus = (string) ($result['status'] ?? '');
        },
        10,
        2
    );

    $result = $services['bunny_import']->importFamily('Inter', ['regular', '700']);
    $import = $services['imports']->get('inter');
    $profile = (array) (($import['delivery_profiles']['bunny-self_hosted'] ?? null) ?: []);
    $catalog = $services['catalog']->getCatalog();
    $catalogFamily = $catalog['Inter'] ?? null;
    $downloadUrls = array_map(static fn (array $call): string => (string) ($call['url'] ?? ''), $remoteGetCalls);
    $savedRegularPath = $services['storage']->pathForRelativePath('bunny/inter/inter-400-normal.woff2');
    $savedBoldPath = $services['storage']->pathForRelativePath('bunny/inter/inter-700-normal.woff2');

    assertSameValue('imported', (string) ($result['status'] ?? ''), 'Bunny imports should report an imported result.');
    assertSameValue('bunny', (string) ($profile['provider'] ?? ''), 'Bunny imports should persist the bunny provider on the saved delivery profile.');
    assertSameValue('bunny', (string) ($profile['faces'][0]['provider']['type'] ?? ''), 'Bunny imports should persist bunny provider metadata per face.');
    assertSameValue('library_only', (string) ($import['publish_state'] ?? ''), 'New Bunny imports should start in the library instead of being published immediately.');
    assertSameValue(['bunny'], (array) ($catalogFamily['sources'] ?? []), 'Catalog entries created from Bunny imports should expose the bunny source.');
    assertSameValue('bunny', (string) ($catalogFamily['faces'][0]['source'] ?? ''), 'Catalog faces created from Bunny imports should retain the bunny source.');
    assertSameValue(true, is_string($savedRegularPath) && file_exists($savedRegularPath), 'Bunny regular faces should be written under uploads/fonts/bunny.');
    assertSameValue(true, is_string($savedBoldPath) && file_exists($savedBoldPath), 'Bunny bold faces should be written under uploads/fonts/bunny.');
    assertContainsValue($latinUrl, implode("\n", $downloadUrls), 'Bunny imports should download the preferred latin regular face.');
    assertContainsValue($boldUrl, implode("\n", $downloadUrls), 'Bunny imports should download the requested bold face.');
    assertNotContainsValue($greekUrl, implode("\n", $downloadUrls), 'Bunny imports should skip lower-priority subset faces for the same axis.');
    assertSameValue(1, did_action('tasty_fonts_after_import'), 'Bunny imports should fire the tasty_fonts_after_import action.');
    assertSameValue('bunny', $importProvider, 'Bunny imports should identify the provider when firing tasty_fonts_after_import.');
    assertSameValue('imported', $importStatus, 'Bunny imports should pass the import result payload to tasty_fonts_after_import.');
};

$tests['google_import_service_fires_after_import_action'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = $services['google']->buildCssUrl('Inter', ['regular']);
    $fontUrl = 'https://fonts.gstatic.com/s/inter/v18/inter-400-normal.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/inter/v18/inter-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];

    $importProvider = '';
    $importResult = [];
    add_action(
        'tasty_fonts_after_import',
        static function (array $result, string $provider) use (&$importProvider, &$importResult): void {
            $importProvider = $provider;
            $importResult = $result;
        },
        10,
        2
    );

    $result = $services['google_import']->importFamily('Inter', ['regular']);
    $import = $services['imports']->get('inter');

    assertSameValue('imported', (string) ($result['status'] ?? ''), 'Google imports should still succeed when the after-import action is registered.');
    assertSameValue(1, did_action('tasty_fonts_after_import'), 'Google imports should fire the tasty_fonts_after_import action.');
    assertSameValue('google', $importProvider, 'Google imports should identify the provider when firing tasty_fonts_after_import.');
    assertSameValue('imported', (string) ($importResult['status'] ?? ''), 'Google imports should pass the import result payload to tasty_fonts_after_import.');
    assertSameValue('library_only', (string) ($import['publish_state'] ?? ''), 'New Google imports should start in the library instead of being published immediately.');
};

$tests['bunny_import_service_reports_direct_filesystem_requirement'] = static function (): void {
    resetTestState();

    global $filesystemMethod;
    global $remoteGetResponses;
    global $wpFilesystemInitCalls;

    $filesystemMethod = 'ftpext';

    $services = makeServiceGraph();
    $cssUrl = $services['bunny']->buildCssUrl('Inter', ['regular']);
    $latinUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$latinUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];

    $result = $services['bunny_import']->importFamily('Inter', ['regular']);

    assertSameValue(true, is_wp_error($result), 'Bunny imports should fail with a WP_Error when direct filesystem access is unavailable.');
    assertContainsValue(
        'Direct filesystem access is unavailable',
        $result->get_error_message(),
        'Bunny imports should surface the direct filesystem requirement instead of a generic write failure.'
    );
    assertSameValue(0, count($wpFilesystemInitCalls), 'Bunny imports should not initialize WP_Filesystem when the direct method is unavailable.');
};

$tests['bunny_import_service_skips_existing_variants_and_can_coexist_with_local_faces'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = $services['bunny']->buildCssUrl('Inter', ['regular']);
    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];

    $firstImport = $services['bunny_import']->importFamily('Inter', ['regular']);
    $secondImport = $services['bunny_import']->importFamily('Inter', ['regular']);

    assertSameValue('imported', (string) ($firstImport['status'] ?? ''), 'The initial Bunny import should succeed.');
    assertSameValue('skipped', (string) ($secondImport['status'] ?? ''), 'Re-importing an existing Bunny variant should be skipped.');

    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2'), 'font-data');
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];

    $coexistingImport = $services['bunny_import']->importFamily('Inter', ['regular']);
    $catalogFamily = $services['catalog']->getCatalog()['Inter'] ?? [];

    assertSameValue('imported', (string) ($coexistingImport['status'] ?? ''), 'Bunny imports should still succeed when a local family already exists.');
    assertSameValue(['local', 'bunny'], (array) ($catalogFamily['sources'] ?? []), 'Families should be able to keep both local/self-hosted and Bunny delivery profiles.');
};

$tests['library_service_deletes_bunny_import_families_cleanly'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = $services['bunny']->buildCssUrl('Inter', ['regular']);
    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];

    $deletedFamilySlug = '';
    $deletedFamilyName = '';
    add_action(
        'tasty_fonts_after_delete_family',
        static function (string $familySlug, string $familyName) use (&$deletedFamilySlug, &$deletedFamilyName): void {
            $deletedFamilySlug = $familySlug;
            $deletedFamilyName = $familyName;
        },
        10,
        2
    );

    $services['bunny_import']->importFamily('Inter', ['regular']);
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('lora/Lora-400-normal.woff2'), 'font-data');
    $services['settings']->saveRoles(
        [
            'heading' => 'Lora',
            'body' => 'Lora',
            'heading_fallback' => 'serif',
            'body_fallback' => 'serif',
        ],
        ['Inter', 'Lora']
    );
    $services['settings']->setAutoApplyRoles(false);
    $familyDirectory = $services['storage']->pathForRelativePath('bunny/inter');
    $result = $services['library']->deleteFamily('inter');

    assertSameValue(true, $result, 'Bunny-imported families should be deletable from the library.');
    assertSameValue(null, $services['imports']->get('inter'), 'Deleting a Bunny family should remove its import manifest entry.');
    assertSameValue(false, is_string($familyDirectory) && file_exists($familyDirectory), 'Deleting a Bunny family should remove its provider directory from uploads/fonts.');
    assertSameValue(1, did_action('tasty_fonts_after_delete_family'), 'Deleting a family should fire the tasty_fonts_after_delete_family action.');
    assertSameValue('inter', $deletedFamilySlug, 'Deleting a family should pass the deleted slug to tasty_fonts_after_delete_family.');
    assertSameValue('Inter', $deletedFamilyName, 'Deleting a family should pass the deleted name to tasty_fonts_after_delete_family.');
};

$tests['local_upload_service_rejects_unverified_tmp_files'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $tmpName = uniqueTestDirectory('tmp-upload-invalid') . '/inter-400-normal.woff2';
    mkdir(dirname($tmpName), FS_CHMOD_DIR, true);
    file_put_contents($tmpName, "wOF2test-font");

    $result = $services['local_upload']->uploadRows([
        [
            'family' => 'Inter',
            'weight' => '400',
            'style' => 'normal',
            'fallback' => 'sans-serif',
            'file' => [
                'name' => 'inter-400-normal.woff2',
                'tmp_name' => $tmpName,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmpName),
            ],
        ],
    ]);

    $savedPath = $services['storage']->pathForRelativePath('upload/inter/Inter-400-normal.woff2');

    assertSameValue(1, (int) ($result['summary']['errors'] ?? 0), 'Uploads should error when PHP cannot verify the temp file as an HTTP upload.');
    assertContainsValue('could not be verified as a valid HTTP upload', (string) ($result['rows'][0]['message'] ?? ''), 'Uploads should explain when the temp file fails the PHP upload-origin guard.');
    assertSameValue(false, is_string($savedPath) && file_exists($savedPath), 'Uploads should not write font files when the temp file was not verified.');
};

$tests['local_upload_service_imports_verified_font_uploads'] = static function (): void {
    resetTestState();

    global $uploadedFilePaths;

    $services = makeServiceGraph();
    $tmpName = uniqueTestDirectory('tmp-upload-valid') . '/inter-400-italic.woff2';
    mkdir(dirname($tmpName), FS_CHMOD_DIR, true);
    file_put_contents($tmpName, "wOF2test-font");
    $uploadedFilePaths[] = $tmpName;

    $result = $services['local_upload']->uploadRows([
        [
            'family' => 'Inter',
            'weight' => '400',
            'style' => 'italic',
            'fallback' => 'Arial, sans-serif',
            'file' => [
                'name' => 'inter-400-italic.woff2',
                'tmp_name' => $tmpName,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmpName),
            ],
        ],
    ]);

    $savedPath = $services['storage']->pathForRelativePath('upload/inter/Inter-400-italic.woff2');
    $family = $services['imports']->getFamily('inter');

    assertSameValue(1, (int) ($result['summary']['imported'] ?? 0), 'Verified HTTP uploads should be imported into the local library.');
    assertSameValue('imported', (string) ($result['rows'][0]['status'] ?? ''), 'Verified HTTP uploads should produce an imported row result.');
    assertSameValue(true, is_string($savedPath) && file_exists($savedPath), 'Verified HTTP uploads should be written into the dedicated local upload folder.');
    assertSameValue('library_only', (string) ($family['publish_state'] ?? ''), 'New direct uploads should start in the library instead of being published immediately.');
};

$tests['local_upload_service_reuses_orphaned_existing_upload_files'] = static function (): void {
    resetTestState();

    global $uploadedFilePaths;

    $services = makeServiceGraph();
    $tmpName = uniqueTestDirectory('tmp-upload-orphaned') . '/raleway-100.woff';
    mkdir(dirname($tmpName), FS_CHMOD_DIR, true);
    file_put_contents($tmpName, "wOFFtest-font");
    $uploadedFilePaths[] = $tmpName;
    $services['catalog']->getCatalog();

    $orphanedPath = $services['storage']->pathForRelativePath('upload/raleway/Raleway-100.woff');
    assertSameValue(true, is_string($orphanedPath), 'The orphaned-upload test should resolve the expected target path.');
    $services['storage']->writeAbsoluteFile((string) $orphanedPath, "wOFFexisting-font");

    $result = $services['local_upload']->uploadRows([
        [
            'family' => 'Raleway',
            'weight' => '100',
            'style' => 'normal',
            'fallback' => 'sans-serif',
            'file' => [
                'name' => 'raleway-100.woff',
                'tmp_name' => $tmpName,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmpName),
            ],
        ],
    ]);

    $family = $services['imports']->getFamily('raleway');
    $profile = (array) (($family['delivery_profiles']['local-self_hosted'] ?? null) ?: []);
    $face = (array) (($profile['faces'][0] ?? null) ?: []);

    assertSameValue(1, (int) ($result['summary']['imported'] ?? 0), 'A retry should import successfully when a previous failed upload already left the target file on disk.');
    assertSameValue('upload/raleway/Raleway-100.woff', (string) ($face['paths']['woff'] ?? ''), 'Retrying an orphaned upload should reuse the existing stored file path.');
};

$tests['local_upload_service_derives_variable_weight_ranges_from_wght_axes'] = static function (): void {
    resetTestState();

    global $uploadedFilePaths;

    $services = makeServiceGraph();
    $tmpName = uniqueTestDirectory('tmp-upload-variable') . '/inter-variable.woff2';
    mkdir(dirname($tmpName), FS_CHMOD_DIR, true);
    file_put_contents($tmpName, "wOF2test-font");
    $uploadedFilePaths[] = $tmpName;

    $result = $services['local_upload']->uploadRows([
        [
            'family' => 'Inter Variable',
            'weight' => '400',
            'style' => 'normal',
            'fallback' => 'sans-serif',
            'is_variable' => true,
            'axes' => [
                'WGHT' => ['min' => '300', 'default' => '450', 'max' => '700'],
                'OPSZ' => ['min' => '8', 'default' => '14', 'max' => '32'],
            ],
            'variation_defaults' => [
                'WGHT' => '450',
                'OPSZ' => '14',
            ],
            'file' => [
                'name' => 'inter-variable.woff2',
                'tmp_name' => $tmpName,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmpName),
            ],
        ],
    ]);

    $family = $services['imports']->getFamily('inter-variable');
    $profile = (array) (($family['delivery_profiles']['local-self_hosted'] ?? null) ?: []);
    $face = (array) (($profile['faces'][0] ?? null) ?: []);
    $savedPath = $services['storage']->pathForRelativePath('upload/inter-variable/Inter Variable-VariableFont.woff2');

    assertSameValue(1, (int) ($result['summary']['imported'] ?? 0), 'Variable uploads should still import successfully.');
    assertSameValue('300..700', (string) ($face['weight'] ?? ''), 'Variable uploads should derive their stored weight range from the normalized WGHT axis.');
    assertSameValue('450', (string) ($face['variation_defaults']['WGHT'] ?? ''), 'Variable uploads should keep the normalized WGHT default alongside the stored range.');
    assertSameValue(true, is_string($savedPath) && file_exists($savedPath), 'Variable uploads should use the VariableFont filename helper for the stored file.');
};

$tests['google_import_service_uses_variable_font_filenames_for_self_hosted_variable_faces'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = $services['google']->buildCssUrl('Inter Variable', ['regular']);
    $fontUrl = 'https://fonts.gstatic.com/s/inter/v18/inter-variable.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter Variable';
  font-style: normal;
  font-weight: 300 700;
  src: url(https://fonts.gstatic.com/s/inter/v18/inter-variable.woff2) format('woff2-variations');
}
CSS,
    ];
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'variable-font-data',
    ];

    $result = $services['google_import']->importFamily('Inter Variable', ['regular']);
    $savedPath = $services['storage']->pathForRelativePath('google/inter-variable/Inter Variable-VariableFont.woff2');
    $legacyPath = $services['storage']->pathForRelativePath('google/inter-variable/inter-variable-300-700-normal.woff2');

    assertSameValue('imported', (string) ($result['status'] ?? ''), 'Variable self-hosted Google imports should still succeed.');
    assertSameValue(true, is_string($savedPath) && file_exists($savedPath), 'Variable self-hosted Google imports should use the VariableFont filename helper for downloaded files.');
    assertSameValue(false, is_string($legacyPath) && file_exists($legacyPath), 'Variable self-hosted Google imports should not generate malformed range-based filenames.');
};

$tests['library_service_blocks_deleting_live_applied_family_when_draft_changed'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $fontPath = $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2');
    $services['storage']->writeAbsoluteFile((string) $fontPath, 'font-data');

    $catalog = ['Inter', 'Lora'];
    $services['settings']->saveRoles(
        [
            'heading' => 'Lora',
            'body' => 'Lora',
            'heading_fallback' => 'serif',
            'body_fallback' => 'serif',
        ],
        $catalog
    );
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Lora',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        $catalog
    );
    $services['settings']->setAutoApplyRoles(true);

    $result = $services['library']->deleteFamily('inter');

    assertSameValue(true, is_wp_error($result), 'Deleting a family should be blocked when it is still used by the live applied roles.');
    assertSameValue('tasty_fonts_family_in_use', $result->get_error_code(), 'Deleting a live applied role family should return the family-in-use error.');
    assertContainsValue('currently assigned as the heading font', $result->get_error_message(), 'The delete-family guard should explain that the family is still the live heading role.');
};

$tests['library_service_blocks_deleting_last_live_applied_variant_when_draft_changed'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $fontPath = $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2');
    $services['storage']->writeAbsoluteFile((string) $fontPath, 'font-data');

    $catalog = ['Inter', 'Lora'];
    $services['settings']->saveRoles(
        [
            'heading' => 'Lora',
            'body' => 'Lora',
            'heading_fallback' => 'serif',
            'body_fallback' => 'serif',
        ],
        $catalog
    );
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Lora',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        $catalog
    );
    $services['settings']->setAutoApplyRoles(true);

    $result = $services['library']->deleteFaceVariant('inter', '400', 'normal');

    assertSameValue(true, is_wp_error($result), 'Deleting the last variant should be blocked when the family is still used by the live applied roles.');
    assertSameValue('tasty_fonts_variant_in_use', $result->get_error_code(), 'Deleting the last live applied role variant should return the variant-in-use error.');
    assertContainsValue('currently assigned to heading', $result->get_error_message(), 'The delete-variant guard should explain that the family is still the live heading role.');
};

$tests['library_service_deletes_remote_variant_from_live_monospace_delivery_when_other_faces_remain'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['monospace_role_enabled' => '1']);
    $services['imports']->saveProfile(
        'JetBrains Mono',
        'jetbrains-mono',
        [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular', '700'],
            'faces' => [
                [
                    'family' => 'JetBrains Mono',
                    'slug' => 'jetbrains-mono',
                    'source' => 'google',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => ['woff2' => 'https://fonts.gstatic.com/s/jetbrainsmono/v1/jetbrainsmono-400-normal.woff2'],
                    'paths' => [],
                ],
                [
                    'family' => 'JetBrains Mono',
                    'slug' => 'jetbrains-mono',
                    'source' => 'google',
                    'weight' => '700',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => ['woff2' => 'https://fonts.gstatic.com/s/jetbrainsmono/v1/jetbrainsmono-700-normal.woff2'],
                    'paths' => [],
                ],
            ],
        ],
        'published',
        true
    );

    $catalog = ['Inter', 'JetBrains Mono'];
    $services['settings']->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'monospace' => 'JetBrains Mono',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
            'monospace_fallback' => 'monospace',
        ],
        $catalog
    );
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'monospace' => 'JetBrains Mono',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
            'monospace_fallback' => 'monospace',
        ],
        $catalog
    );
    $services['settings']->setAutoApplyRoles(true);

    $result = $services['library']->deleteFaceVariant('jetbrains-mono', '400', 'normal', 'google');
    $saved = $services['imports']->get('jetbrains-mono');
    $remainingFaces = (array) (($saved['delivery_profiles']['google-cdn']['faces'] ?? null) ?: []);
    $remainingVariants = array_values((array) (($saved['delivery_profiles']['google-cdn']['variants'] ?? null) ?: []));

    assertSameValue(false, is_wp_error($result), 'Deleting a remote CDN variant should be allowed when another live monospace face remains.');
    assertSameValue(1, count($remainingFaces), 'Deleting one remote CDN variant should keep the remaining faces on the active delivery.');
    assertSameValue('700', (string) ($remainingFaces[0]['weight'] ?? ''), 'Deleting the regular remote CDN face should leave the bold face behind.');
    assertSameValue(['700'], $remainingVariants, 'Deleting a remote CDN face should rebuild the stored variant token list.');
};

$tests['library_service_deletes_single_managed_self_hosted_variant_without_removing_sibling_files'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $regularRelativePath = 'google/inter/inter-400-normal.woff2';
    $boldRelativePath = 'google/inter/inter-700-normal.woff2';
    $regularPath = $services['storage']->pathForRelativePath($regularRelativePath);
    $boldPath = $services['storage']->pathForRelativePath($boldRelativePath);
    $services['storage']->writeAbsoluteFile((string) $regularPath, 'regular-font-data');
    $services['storage']->writeAbsoluteFile((string) $boldPath, 'bold-font-data');
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-self_hosted',
            'label' => 'Self-hosted (Google import)',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular', '700'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'google',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => ['woff2' => $regularRelativePath],
                    'paths' => ['woff2' => $regularRelativePath],
                ],
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'google',
                    'weight' => '700',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => ['woff2' => $boldRelativePath],
                    'paths' => ['woff2' => $boldRelativePath],
                ],
            ],
        ],
        'published',
        true
    );

    $result = $services['library']->deleteFaceVariant('inter', '400', 'normal', 'google');
    $saved = $services['imports']->get('inter');
    $remainingFaces = (array) (($saved['delivery_profiles']['google-self_hosted']['faces'] ?? null) ?: []);

    assertSameValue(false, is_wp_error($result), 'Deleting one managed self-hosted import face should succeed.');
    assertSameValue(false, is_string($regularPath) && file_exists($regularPath), 'Deleting one managed self-hosted import face should remove only that face file.');
    assertSameValue(true, is_string($boldPath) && file_exists($boldPath), 'Deleting one managed self-hosted import face should not remove sibling files.');
    assertSameValue(1, count($remainingFaces), 'Deleting one managed self-hosted import face should keep the remaining stored faces.');
    assertSameValue('700', (string) ($remainingFaces[0]['weight'] ?? ''), 'Deleting one managed self-hosted import face should keep the sibling face metadata.');
};

$tests['library_service_syncs_monospace_publish_state_only_when_feature_is_enabled'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $interProfile = [
        'id' => 'inter-self-hosted',
        'label' => 'Self-hosted',
        'provider' => 'local',
        'type' => 'self_hosted',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Inter',
            'slug' => 'inter',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => '',
            'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
        ]],
    ];
    $monoProfile = [
        'id' => 'jetbrains-mono-self-hosted',
        'label' => 'Self-hosted',
        'provider' => 'local',
        'type' => 'self_hosted',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'JetBrains Mono',
            'slug' => 'jetbrains-mono',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => '',
            'files' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
            'paths' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
        ]],
    ];

    $services['imports']->saveProfile('Inter', 'inter', $interProfile, 'published', true);
    $services['imports']->saveProfile('JetBrains Mono', 'jetbrains-mono', $monoProfile, 'published', true);
    $services['settings']->saveSettings(['monospace_role_enabled' => '1']);

    $services['library']->syncLiveRolePublishStates(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'monospace' => 'JetBrains Mono',
        ],
        true
    );

    assertSameValue(
        'role_active',
        (string) ($services['imports']->getFamily('jetbrains-mono')['publish_state'] ?? ''),
        'Enabled monospace support should promote the applied monospace family to role_active.'
    );

    $services['settings']->saveSettings(['monospace_role_enabled' => '0']);
    $services['library']->syncLiveRolePublishStates(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'monospace' => 'JetBrains Mono',
        ],
        true
    );

    assertSameValue(
        'published',
        (string) ($services['imports']->getFamily('jetbrains-mono')['publish_state'] ?? ''),
        'Disabled monospace support should ignore stored monospace selections when live publish states are synchronized.'
    );
};

$tests['library_service_restores_library_only_state_when_a_live_family_is_removed_from_roles'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $profile = [
        'id' => 'inter-self-hosted',
        'label' => 'Self-hosted',
        'provider' => 'local',
        'type' => 'self_hosted',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Inter',
            'slug' => 'inter',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => '',
            'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
        ]],
    ];

    $services['imports']->saveProfile('Inter', 'inter', $profile, 'library_only', true);
    $services['library']->syncLiveRolePublishStates(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
        ],
        true
    );

    assertSameValue('role_active', (string) ($services['imports']->getFamily('inter')['publish_state'] ?? ''), 'Live role sync should still promote the active family while it is assigned.');

    $services['library']->syncLiveRolePublishStates([], false);

    assertSameValue(
        'library_only',
        (string) ($services['imports']->getFamily('inter')['publish_state'] ?? ''),
        'Removing a live family from sitewide roles should restore its saved library-only state.'
    );
};

$tests['library_service_prunes_unavailable_family_after_live_role_moves_away'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $profile = [
        'id' => 'inter-self-hosted',
        'label' => 'Self-hosted',
        'provider' => 'local',
        'type' => 'self_hosted',
        'variants' => ['regular'],
        'faces' => [[
            'family' => 'Inter',
            'slug' => 'inter',
            'source' => 'local',
            'weight' => '400',
            'style' => 'normal',
            'unicode_range' => '',
            'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
        ]],
    ];

    $services['imports']->ensureFamily('Alan Sans', 'alan-sans', 'role_active', '', 'library_only');
    $services['imports']->saveProfile('Inter', 'inter', $profile, 'published', true);

    assertSameValue(true, $services['imports']->getFamily('alan-sans') !== null, 'Fixture should start with an unavailable stored family record.');

    $services['library']->syncLiveRolePublishStates(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
        ],
        true
    );

    assertSameValue(
        null,
        $services['imports']->getFamily('alan-sans'),
        'Moving live roles away from an unavailable family should remove the stale library record instead of rendering an Unavailable card.'
    );
    assertSameValue(
        'role_active',
        (string) ($services['imports']->getFamily('inter')['publish_state'] ?? ''),
        'The replacement live family should remain role_active.'
    );

    $catalogFamilies = array_map(
        static fn (array $family): string => (string) ($family['family'] ?? ''),
        $services['catalog']->getCatalog()
    );

    assertSameValue(
        false,
        in_array('Alan Sans', $catalogFamilies, true),
        'The unavailable family should disappear from the rendered catalog once pruned.'
    );
};

$tests['library_service_preserves_the_active_local_delivery_when_a_managed_delivery_is_added'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2'), 'font-data');

    $published = $services['library']->saveFamilyPublishState('inter', 'published');
    assertSameValue(true, !is_wp_error($published), 'Publishing a scanned family should succeed before adding another delivery.');
    assertSameValue(
        'local-self_hosted',
        (string) ($services['imports']->getFamily('inter')['active_delivery_id'] ?? ''),
        'Persisting a scanned family should keep its current local delivery as the active selection.'
    );

    $saved = $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular'],
            'faces' => [],
            'meta' => [],
        ],
        'published',
        false
    );
    $catalogFamily = $services['catalog']->getCatalog()['Inter'] ?? [];

    assertSameValue(
        'local-self_hosted',
        (string) ($saved['active_delivery_id'] ?? ''),
        'Adding a managed delivery should not switch the stored active delivery away from the existing local profile.'
    );
    assertSameValue(
        'local-self_hosted',
        (string) ($catalogFamily['active_delivery_id'] ?? ''),
        'Adding a managed delivery should not switch the live catalog delivery away from the existing local profile.'
    );
    assertTrueValue(
        isset($catalogFamily['delivery_profiles']['google-cdn']),
        'Adding a managed delivery should append the new profile alongside the existing local delivery.'
    );
};

$tests['library_service_blocks_deleting_live_monospace_family_when_enabled'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'inter-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'unicode_range' => '',
                'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'JetBrains Mono',
        'jetbrains-mono',
        [
            'id' => 'jetbrains-mono-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'JetBrains Mono',
                'slug' => 'jetbrains-mono',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'unicode_range' => '',
                'files' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
                'paths' => ['woff2' => 'jetbrains-mono/JetBrainsMono-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );

    $catalog = ['Inter', 'JetBrains Mono'];
    $services['settings']->saveSettings(['monospace_role_enabled' => '1']);
    $services['settings']->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'monospace' => 'JetBrains Mono',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
            'monospace_fallback' => 'monospace',
        ],
        $catalog
    );
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'monospace' => 'JetBrains Mono',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
            'monospace_fallback' => 'monospace',
        ],
        $catalog
    );
    $services['settings']->setAutoApplyRoles(true);

    $result = $services['library']->deleteFamily('jetbrains-mono');

    assertSameValue(true, is_wp_error($result), 'Deleting a live monospace family should be blocked while the feature is enabled.');
    assertSameValue('tasty_fonts_family_in_use', $result->get_error_code(), 'Deleting a live monospace family should return the family-in-use error.');
    assertContainsValue('currently assigned as the monospace font', $result->get_error_message(), 'The delete-family guard should explain that the family is still used for the monospace role.');
};

// ---------------------------------------------------------------------------
// LibraryService::saveFamilyDelivery – error path
// ---------------------------------------------------------------------------

$tests['library_service_save_family_delivery_returns_error_for_unknown_slug'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();

    $result = $services['library']->saveFamilyDelivery('no-such-font', 'google-cdn');

    assertTrueValue(is_wp_error($result), 'saveFamilyDelivery() should return a WP_Error when the given family slug is not in the library.');
    assertSameValue('tasty_fonts_family_not_found', $result->get_error_code(), 'saveFamilyDelivery() should use the family_not_found error code for an unknown slug.');
};

$tests['library_service_save_family_delivery_switches_the_active_profile_and_logs_the_change'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Local Upload',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular'],
            'faces' => [],
            'meta' => [],
        ],
        'published',
        false
    );

    $result = $services['library']->saveFamilyDelivery('inter', 'google-cdn');
    $saved = $services['imports']->getFamily('inter');
    $logEntries = $services['log']->all();

    assertFalseValue(is_wp_error($result), 'saveFamilyDelivery() should switch to a known delivery profile.');
    assertSameValue('inter', (string) ($result['family_slug'] ?? ''), 'saveFamilyDelivery() should return the saved family slug.');
    assertSameValue('google-cdn', (string) ($result['delivery_id'] ?? ''), 'saveFamilyDelivery() should return the newly active delivery id.');
    assertSameValue('Google CDN', (string) ($result['delivery_label'] ?? ''), 'saveFamilyDelivery() should return the saved delivery label.');
    assertSameValue('google-cdn', (string) ($saved['active_delivery_id'] ?? ''), 'saveFamilyDelivery() should persist the new active delivery on the stored family.');
    assertContainsValue('Live delivery for Inter switched to Google CDN.', (string) ($result['message'] ?? ''), 'saveFamilyDelivery() should describe the active delivery switch in its result payload.');
    assertContainsValue('Live delivery for Inter switched to Google CDN.', (string) ($logEntries[0]['message'] ?? ''), 'saveFamilyDelivery() should log the active delivery switch.');
};

$tests['library_service_save_family_delivery_returns_error_for_unknown_delivery'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Local Upload',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );

    $result = $services['library']->saveFamilyDelivery('inter', 'missing-delivery');

    assertTrueValue(is_wp_error($result), 'saveFamilyDelivery() should reject unknown delivery ids for an existing family.');
    assertSameValue('tasty_fonts_delivery_not_found', $result->get_error_code(), 'saveFamilyDelivery() should use the delivery-not-found error for missing profiles.');
};

// ---------------------------------------------------------------------------
// LibraryService::deleteDeliveryProfile – deletion policy and side effects
// ---------------------------------------------------------------------------

$tests['library_service_delete_delivery_profile_removes_a_stored_non_active_profile_files_and_log_entry'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $localPath = $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2');
    $customPath = $services['storage']->pathForRelativePath('custom/inter/inter-700-normal.woff2');
    $services['storage']->writeAbsoluteFile((string) $localPath, 'local-font-data');
    $services['storage']->writeAbsoluteFile((string) $customPath, 'custom-font-data');
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Local Upload',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'custom-self_hosted',
            'label' => 'Self-hosted custom CSS',
            'provider' => 'custom',
            'type' => 'self_hosted',
            'variants' => ['700'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'custom',
                'weight' => '700',
                'style' => 'normal',
                'files' => ['woff2' => 'custom/inter/inter-700-normal.woff2'],
                'paths' => ['woff2' => 'custom/inter/inter-700-normal.woff2'],
            ]],
        ],
        'published',
        false
    );

    $result = $services['library']->deleteDeliveryProfile('inter', 'custom-self_hosted');
    $saved = $services['imports']->getFamily('inter');
    $logEntries = $services['log']->all();

    assertFalseValue(is_wp_error($result), 'deleteDeliveryProfile() should delete a stored non-active delivery profile.');
    assertSameValue(false, (bool) ($result['deleted_family'] ?? true), 'Deleting one of several delivery profiles should not delete the family.');
    assertSameValue(false, isset($saved['delivery_profiles']['custom-self_hosted']), 'Deleting a stored delivery profile should remove only that profile from the import manifest.');
    assertSameValue('local-self_hosted', (string) ($saved['active_delivery_id'] ?? ''), 'Deleting a non-active delivery should keep the active delivery unchanged.');
    assertSameValue(false, is_string($customPath) && file_exists($customPath), 'Deleting a self-hosted delivery profile should remove its files.');
    assertSameValue(true, is_string($localPath) && file_exists($localPath), 'Deleting a sibling delivery profile should not remove active profile files.');
    assertSameValue('delivery_profile_deleted', (string) ($logEntries[0]['event'] ?? ''), 'Deleting a delivery profile should write the delivery_profile_deleted audit event.');
};

$tests['library_service_delete_delivery_profile_blocks_the_active_live_delivery'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Local Upload',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'google',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'https://fonts.gstatic.com/s/inter/v1/inter-400-normal.woff2'],
                'paths' => [],
            ]],
        ],
        'published',
        false
    );
    $catalog = ['Inter'];
    $services['settings']->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        $catalog
    );
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        $catalog
    );
    $services['settings']->setAutoApplyRoles(true);

    $result = $services['library']->deleteDeliveryProfile('inter', 'local-self_hosted');
    $saved = $services['imports']->getFamily('inter');

    assertTrueValue(is_wp_error($result), 'deleteDeliveryProfile() should block deleting the active delivery while the family is live.');
    assertSameValue('tasty_fonts_delivery_in_use', $result->get_error_code(), 'Deleting the active live delivery should return the delivery-in-use error.');
    assertTrueValue(isset($saved['delivery_profiles']['local-self_hosted']), 'Blocked active delivery deletion should leave the stored profile intact.');
};

$tests['library_service_delete_delivery_profile_deletes_the_family_when_it_is_the_only_delivery'] = static function (): void {
    resetTestState();

    $deletedFamilySlug = '';
    $deletedFamilyName = '';
    add_action(
        'tasty_fonts_after_delete_family',
        static function (string $familySlug, string $familyName) use (&$deletedFamilySlug, &$deletedFamilyName): void {
            $deletedFamilySlug = $familySlug;
            $deletedFamilyName = $familyName;
        },
        10,
        2
    );

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $fontPath = $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2');
    $services['storage']->writeAbsoluteFile((string) $fontPath, 'font-data');
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Local Upload',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );

    $result = $services['library']->deleteDeliveryProfile('inter', 'local-self_hosted');

    assertFalseValue(is_wp_error($result), 'deleteDeliveryProfile() should delegate to family deletion when the only profile is removed.');
    assertSameValue(true, (bool) ($result['deleted_family'] ?? false), 'Deleting the only delivery profile should mark the result as a deleted family.');
    assertSameValue(null, $services['imports']->getFamily('inter'), 'Deleting the only delivery profile should remove the family import manifest.');
    assertSameValue(false, is_string($fontPath) && file_exists($fontPath), 'Deleting the only delivery profile should remove the family files through family deletion.');
    assertSameValue('inter', $deletedFamilySlug, 'Delegated family deletion should still fire the family-delete hook with the deleted slug.');
    assertSameValue('Inter', $deletedFamilyName, 'Delegated family deletion should still fire the family-delete hook with the deleted family name.');
};

$tests['library_service_delete_delivery_profile_switches_active_synthetic_delivery_to_stored_fallback'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $localPath = $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2');
    $services['storage']->writeAbsoluteFile((string) $localPath, 'font-data');
    $services['imports']->saveFamily([
        'family' => 'Inter',
        'slug' => 'inter',
        'publish_state' => 'published',
        'active_delivery_id' => 'local-self_hosted',
        'delivery_profiles' => [
            'google-cdn' => [
                'id' => 'google-cdn',
                'label' => 'Google CDN',
                'provider' => 'google',
                'type' => 'cdn',
                'variants' => ['regular'],
                'faces' => [[
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'google',
                    'weight' => '400',
                    'style' => 'normal',
                    'files' => ['woff2' => 'https://fonts.gstatic.com/s/inter/v1/inter-400-normal.woff2'],
                    'paths' => [],
                ]],
            ],
        ],
    ]);

    $result = $services['library']->deleteDeliveryProfile('inter', 'local-self_hosted');
    $saved = $services['imports']->getFamily('inter');

    assertFalseValue(is_wp_error($result), 'deleteDeliveryProfile() should allow deleting an active synthetic profile when an alternative delivery exists.');
    assertSameValue(false, (bool) ($result['deleted_family'] ?? true), 'Deleting an active synthetic profile with alternatives should keep the family.');
    assertSameValue('google-cdn', (string) ($saved['active_delivery_id'] ?? ''), 'Deleting an active synthetic delivery should switch the stored family to the first alternative delivery.');
    assertTrueValue(isset($saved['delivery_profiles']['google-cdn']), 'The fallback delivery should remain stored after the synthetic active delivery is removed.');
    assertSameValue(false, is_string($localPath) && file_exists($localPath), 'Deleting the active synthetic local delivery should remove its scanned file.');
};

$tests['library_service_delete_delivery_profile_returns_error_when_profile_file_cleanup_fails'] = static function (): void {
    resetTestState();

    global $filesystemMethod;

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $localPath = $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2');
    $customPath = $services['storage']->pathForRelativePath('custom/inter/inter-700-normal.woff2');
    $services['storage']->writeAbsoluteFile((string) $localPath, 'local-font-data');
    $services['storage']->writeAbsoluteFile((string) $customPath, 'custom-font-data');
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Local Upload',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'custom-self_hosted',
            'label' => 'Self-hosted custom CSS',
            'provider' => 'custom',
            'type' => 'self_hosted',
            'variants' => ['700'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'custom',
                'weight' => '700',
                'style' => 'normal',
                'files' => ['woff2' => 'custom/inter/inter-700-normal.woff2'],
                'paths' => ['woff2' => 'custom/inter/inter-700-normal.woff2'],
            ]],
        ],
        'published',
        false
    );
    $filesystemMethod = 'ftpext';

    $result = $services['library']->deleteDeliveryProfile('inter', 'custom-self_hosted');
    $saved = $services['imports']->getFamily('inter');

    assertTrueValue(is_wp_error($result), 'deleteDeliveryProfile() should return a WP_Error when delivery file cleanup fails.');
    assertSameValue('tasty_fonts_delete_failed', $result->get_error_code(), 'File cleanup failures should use the delete_failed error code.');
    assertContainsValue('Direct filesystem access is unavailable', $result->get_error_message(), 'File cleanup failures should surface the storage filesystem error when available.');
    assertTrueValue(isset($saved['delivery_profiles']['custom-self_hosted']), 'Failed file cleanup should leave the delivery profile in the import manifest.');
    assertSameValue(true, is_string($customPath) && file_exists($customPath), 'Failed file cleanup should leave the delivery file in place.');
};

$tests['library_service_delete_delivery_profile_ignores_managed_provider_directory_cleanup_failure'] = static function (): void {
    resetTestState();

    global $wpFilesystemShouldInit;

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $providerDirectory = $services['storage']->pathForRelativePath('google/inter');
    mkdir((string) $providerDirectory, FS_CHMOD_DIR, true);
    file_put_contents((string) $providerDirectory . '/leftover.txt', 'leftover');
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'local-self_hosted',
            'label' => 'Local Upload',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-self_hosted',
            'label' => 'Self-hosted (Google import)',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [],
        ],
        'published',
        false
    );
    $wpFilesystemShouldInit = false;

    $result = $services['library']->deleteDeliveryProfile('inter', 'google-self_hosted');
    $saved = $services['imports']->getFamily('inter');

    assertFalseValue(is_wp_error($result), 'Managed provider directory cleanup failures should not fail delivery-profile deletion.');
    assertSameValue(false, (bool) ($result['deleted_family'] ?? true), 'A managed provider directory cleanup failure should still keep the family when other deliveries exist.');
    assertSameValue(false, isset($saved['delivery_profiles']['google-self_hosted']), 'The managed delivery profile should still be removed when provider directory cleanup fails.');
    assertSameValue(true, is_string($providerDirectory) && file_exists($providerDirectory), 'Failed best-effort provider directory cleanup may leave the provider directory behind.');
};

// ---------------------------------------------------------------------------
// LibraryService::saveFamilyPublishState – success and error paths
// ---------------------------------------------------------------------------

$tests['library_service_save_family_publish_state_returns_error_for_unknown_slug'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();

    $result = $services['library']->saveFamilyPublishState('no-such-font', 'published');

    assertTrueValue(is_wp_error($result), 'saveFamilyPublishState() should return a WP_Error when the given family slug is not in the library.');
    assertSameValue('tasty_fonts_family_not_found', $result->get_error_code(), 'saveFamilyPublishState() should use the family_not_found error code for an unknown slug.');
};

$tests['library_service_save_family_publish_state_rejects_invalid_states'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2'), 'font-data');

    $result = $services['library']->saveFamilyPublishState('inter', 'archived');

    assertTrueValue(is_wp_error($result), 'saveFamilyPublishState() should reject unsupported publish states.');
    assertSameValue('tasty_fonts_publish_state_invalid', $result->get_error_code(), 'saveFamilyPublishState() should use the invalid-state error code for unsupported values.');
};

$tests['library_service_save_family_publish_state_blocks_pausing_live_families'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'inter-self-hosted',
            'label' => 'Self-hosted',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'local',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'inter/Inter-400-normal.woff2'],
                'paths' => ['woff2' => 'inter/Inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );
    $catalog = ['Inter'];
    $services['settings']->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        $catalog
    );
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        $catalog
    );
    $services['settings']->setAutoApplyRoles(true);

    $result = $services['library']->saveFamilyPublishState('inter', 'library_only');

    assertTrueValue(is_wp_error($result), 'saveFamilyPublishState() should block pausing families that are currently live through sitewide roles.');
    assertSameValue('tasty_fonts_family_live', $result->get_error_code(), 'saveFamilyPublishState() should use the live-family error when attempting to pause an active family.');
};

$tests['library_service_save_family_publish_state_updates_stored_state_and_logs_the_change'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2'), 'font-data');

    $result = $services['library']->saveFamilyPublishState('inter', 'library_only');
    $saved = $services['imports']->getFamily('inter');
    $logEntries = $services['log']->all();

    assertFalseValue(is_wp_error($result), 'saveFamilyPublishState() should persist supported states for known families.');
    assertSameValue('library_only', (string) ($result['publish_state'] ?? ''), 'saveFamilyPublishState() should return the saved publish state.');
    assertSameValue('library_only', (string) ($saved['publish_state'] ?? ''), 'saveFamilyPublishState() should persist the new publish state.');
    assertContainsValue('Inter is now In Library Only.', (string) ($result['message'] ?? ''), 'saveFamilyPublishState() should return a human-readable status message.');
    assertContainsValue('Inter is now In Library Only.', (string) ($logEntries[0]['message'] ?? ''), 'saveFamilyPublishState() should log publish-state changes.');
};

// ---------------------------------------------------------------------------
// BunnyImportService – WP_Error during font file download
// ---------------------------------------------------------------------------

$tests['bunny_import_service_aborts_with_wp_error_when_font_download_fails'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $cssUrl = $services['bunny']->buildCssUrl('Inter', ['regular']);
    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';

    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$fontUrl] = new WP_Error('http_request_failed', 'cURL error 28: Connection timed out.');

    $result = $services['bunny_import']->importFamily('Inter', ['regular']);

    assertTrueValue(is_wp_error($result), 'BunnyImportService should return a WP_Error when wp_remote_get fails during font file download.');
    assertSameValue('http_request_failed', $result->get_error_code(), 'BunnyImportService should propagate the error code from the failed download response.');
};

$tests['capability_cleanup_service_removes_variable_only_family_profiles'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['variable_fonts_enabled' => '1']);
    $services['storage']->ensureRootDirectory();
    $relativePath = 'variable-only/VariableOnly-Variable.woff2';
    $absolutePath = (string) $services['storage']->pathForRelativePath($relativePath);
    $services['storage']->writeAbsoluteFile($absolutePath, 'font-data');

    $services['imports']->saveProfile(
        'Variable Only',
        'variable-only',
        [
            'id' => 'local-variable',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Variable Only',
                'weight' => '100..900',
                'style' => 'normal',
                'is_variable' => true,
                'files' => ['woff2' => $relativePath],
                'paths' => ['woff2' => $relativePath],
            ]],
        ],
        'published',
        true
    );

    $result = $services['capability_cleanup']->removeVariableFontData();

    assertFalseValue(is_wp_error($result), 'Capability cleanup should succeed when removing variable-only families.');
    assertSameValue(null, $services['imports']->getFamily('variable-only'), 'Capability cleanup should remove families that only contain variable faces.');
    assertSameValue(false, file_exists($absolutePath), 'Capability cleanup should remove managed variable font files from disk.');
};

$tests['capability_cleanup_service_keeps_static_data_in_mixed_profiles'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['variable_fonts_enabled' => '1']);
    $services['storage']->ensureRootDirectory();
    $staticPath = 'hybrid-family/Hybrid-400-normal.woff2';
    $variablePath = 'hybrid-family/Hybrid-Variable.woff2';
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath($staticPath), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath($variablePath), 'font-data');

    $services['imports']->saveProfile(
        'Hybrid Family',
        'hybrid-family',
        [
            'id' => 'local-mixed',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [
                [
                    'family' => 'Hybrid Family',
                    'weight' => '100..900',
                    'style' => 'normal',
                    'is_variable' => true,
                    'files' => ['woff2' => $variablePath],
                    'paths' => ['woff2' => $variablePath],
                ],
                [
                    'family' => 'Hybrid Family',
                    'weight' => '400',
                    'style' => 'normal',
                    'files' => ['woff2' => $staticPath],
                    'paths' => ['woff2' => $staticPath],
                ],
            ],
        ],
        'published',
        true
    );

    $result = $services['capability_cleanup']->removeVariableFontData();
    $saved = $services['imports']->getFamily('hybrid-family');
    $profile = is_array($saved) ? (($saved['delivery_profiles']['local-mixed'] ?? null)) : null;

    assertFalseValue(is_wp_error($result), 'Capability cleanup should succeed when mixed profiles include static faces.');
    assertSameValue(true, is_array($profile), 'Capability cleanup should keep mixed delivery profiles with static faces.');
    assertSameValue('static', (string) ($profile['format'] ?? ''), 'Capability cleanup should normalize mixed profiles to static format after variable faces are removed.');
    assertSameValue(['regular'], (array) ($profile['variants'] ?? []), 'Capability cleanup should rebuild profile variants from remaining static faces.');
};

$tests['capability_cleanup_service_repoints_active_delivery_to_static_profile_when_variable_active_is_removed'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['variable_fonts_enabled' => '1']);
    $services['storage']->ensureRootDirectory();
    $staticPath = 'fallback-family/Fallback-400-normal.woff2';
    $variablePath = 'fallback-family/Fallback-Variable.woff2';
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath($staticPath), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath($variablePath), 'font-data');

    $services['imports']->saveProfile(
        'Fallback Family',
        'fallback-family',
        [
            'id' => 'local-variable',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Fallback Family',
                'weight' => '100..900',
                'style' => 'normal',
                'is_variable' => true,
                'files' => ['woff2' => $variablePath],
                'paths' => ['woff2' => $variablePath],
            ]],
        ],
        'published',
        true
    );
    $services['imports']->saveProfile(
        'Fallback Family',
        'fallback-family',
        [
            'id' => 'local-static',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Fallback Family',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => $staticPath],
                'paths' => ['woff2' => $staticPath],
            ]],
        ],
        'published',
        false
    );

    $result = $services['capability_cleanup']->removeVariableFontData();
    $saved = $services['imports']->getFamily('fallback-family');

    assertFalseValue(is_wp_error($result), 'Capability cleanup should succeed when variable active deliveries can fall back to static profiles.');
    assertSameValue('local-static', (string) ($saved['active_delivery_id'] ?? ''), 'Capability cleanup should repoint active delivery ids to a same-family static profile when the variable active profile is removed.');
};

$tests['capability_cleanup_service_keeps_manifest_unchanged_when_file_deletion_fails'] = static function (): void {
    resetTestState();

    global $wpFilesystemShouldInit;

    $services = makeServiceGraph();
    $services['settings']->saveSettings(['variable_fonts_enabled' => '1']);
    $services['storage']->ensureRootDirectory();
    $relativePath = 'cleanup-failure/CleanupFailure-Variable.woff2';
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath($relativePath), 'font-data');

    $services['imports']->saveProfile(
        'Cleanup Failure',
        'cleanup-failure',
        [
            'id' => 'local-variable',
            'provider' => 'local',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Cleanup Failure',
                'weight' => '100..900',
                'style' => 'normal',
                'is_variable' => true,
                'files' => ['woff2' => $relativePath],
                'paths' => ['woff2' => $relativePath],
            ]],
        ],
        'published',
        true
    );

    $before = $services['imports']->allFamilies();
    $wpFilesystemShouldInit = false;
    $result = $services['capability_cleanup']->removeVariableFontData();
    $wpFilesystemShouldInit = true;
    $after = $services['imports']->allFamilies();

    assertTrueValue(is_wp_error($result), 'Capability cleanup should return a WP_Error when managed variable files cannot be deleted.');
    assertSameValue('tasty_fonts_variable_cleanup_failed', $result->get_error_code(), 'Capability cleanup should use the variable cleanup error code when file deletion fails.');
    assertSameValue($before, $after, 'Capability cleanup should leave the import manifest unchanged when file deletion fails.');
};

$tests['local_upload_service_preserves_multiple_variable_axes_for_uploaded_faces'] = static function (): void {
    resetTestState();

    global $uploadedFilePaths;

    $services = makeServiceGraph();
    $tmpName = uniqueTestDirectory('tmp-upload-variable') . '/jost-variable.woff2';
    mkdir(dirname($tmpName), FS_CHMOD_DIR, true);
    file_put_contents($tmpName, "wOF2test-font");
    $uploadedFilePaths[] = $tmpName;

    $result = $services['local_upload']->uploadRows([
        [
            'family' => 'Jost Variable',
            'weight' => '400',
            'style' => 'normal',
            'fallback' => 'sans-serif',
            'is_variable' => true,
            'axes' => [
                'WGHT' => ['min' => '100', 'default' => '400', 'max' => '900'],
                'ITAL' => ['min' => '0', 'default' => '0', 'max' => '1'],
            ],
            'variation_defaults' => [
                'WGHT' => '400',
                'ITAL' => '0',
            ],
            'file' => [
                'name' => 'jost-variable.woff2',
                'tmp_name' => $tmpName,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmpName),
            ],
        ],
    ]);

    $family = $services['imports']->getFamily('jost-variable');
    $profile = (array) (($family['delivery_profiles']['local-self_hosted'] ?? null) ?: []);
    $face = (array) (($profile['faces'][0] ?? null) ?: []);

    assertSameValue(1, (int) ($result['summary']['imported'] ?? 0), 'Variable uploads with multiple axes should still import successfully.');
    assertSameValue('100..900', (string) ($face['weight'] ?? ''), 'Variable uploads with multiple axes should derive their stored weight range from the normalized WGHT axis.');
    assertTrueValue(isset($face['axes']['WGHT']), 'Variable uploads should preserve the normalized WGHT axis.');
    assertTrueValue(isset($face['axes']['ITAL']), 'Variable uploads should preserve the normalized ITAL axis.');
    assertSameValue('100', (string) ($face['axes']['WGHT']['min'] ?? ''), 'The WGHT min should be preserved.');
    assertSameValue('400', (string) ($face['axes']['WGHT']['default'] ?? ''), 'The WGHT default should be preserved.');
    assertSameValue('900', (string) ($face['axes']['WGHT']['max'] ?? ''), 'The WGHT max should be preserved.');
    assertSameValue('0', (string) ($face['axes']['ITAL']['min'] ?? ''), 'The ITAL min should be preserved.');
    assertSameValue('0', (string) ($face['axes']['ITAL']['default'] ?? ''), 'The ITAL default should be preserved.');
    assertSameValue('1', (string) ($face['axes']['ITAL']['max'] ?? ''), 'The ITAL max should be preserved.');
    assertSameValue('400', (string) ($face['variation_defaults']['WGHT'] ?? ''), 'The WGHT variation default should be preserved.');
    assertSameValue('0', (string) ($face['variation_defaults']['ITAL'] ?? ''), 'The ITAL variation default should be preserved.');
};
