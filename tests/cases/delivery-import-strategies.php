<?php

declare(strict_types=1);

use TastyFonts\Fonts\CdnImportStrategy;
use TastyFonts\Fonts\DeliveryImportStrategy;
use TastyFonts\Fonts\HostedImportProviderConfig;
use TastyFonts\Fonts\HostedImportRequest;
use TastyFonts\Fonts\HostedImportVariantPlanner;
use TastyFonts\Fonts\HostedImportWorkflow;
use TastyFonts\Fonts\ImportFacesResult;
use TastyFonts\Fonts\SelfHostedImportStrategy;
use TastyFonts\Support\Storage;

$tests['cdn_import_strategy_supports_cdn_only'] = static function (): void {
    $strategy = new CdnImportStrategy();

    assertTrueValue($strategy->supports('cdn'), 'CdnImportStrategy should support cdn mode.');
    assertFalseValue($strategy->supports('self_hosted'), 'CdnImportStrategy should not support self_hosted mode.');
};

$tests['cdn_import_strategy_transforms_faces_and_returns_zero_files'] = static function (): void {
    $strategy = new CdnImportStrategy();
    $face = [
        'family' => 'Test',
        'weight' => '400',
        'style' => 'normal',
        'unicode_range' => 'U+0000-00FF',
        'files' => ['woff2' => 'https://example.com/font.woff2'],
    ];
    $config = new HostedImportProviderConfig(
        'bunny',
        'bunny',
        'example.com',
        10 * MB_IN_BYTES,
        'tasty_fonts_bunny_family_dir_failed',
        'The Bunny Fonts import directory could not be created.',
        'tasty_fonts_bunny_invalid_host',
        'Bunny font downloads must come from fonts.bunny.net.',
        'tasty_fonts_bunny_invalid_extension',
        'Only WOFF2 files can be imported from Bunny Fonts.',
        'tasty_fonts_bunny_download_failed',
        'Font download failed with status %d.',
        'tasty_fonts_bunny_empty_file',
        'Bunny Fonts returned an empty font file.',
        'tasty_fonts_bunny_file_too_large',
        'The downloaded font file exceeded the safety size limit.',
        'tasty_fonts_bunny_invalid_type',
        'The downloaded file was not returned as a WOFF2 font.',
        'tasty_fonts_bunny_write_failed',
        'The imported font file could not be written to uploads/fonts.',
        'tasty_fonts_bunny_no_faces',
        'No usable Bunny Fonts faces were returned for that family.',
        'tasty_fonts_bunny_empty_manifest',
        'No local font files were saved from that import.',
        'tasty_fonts_missing_family',
        'Choose a Bunny Fonts family before importing.',
        '%s already exists in the library for the selected variants.',
        'Bunny CDN delivery for %s already includes the selected variants.',
        'Added %1$s as a self-hosted Bunny delivery (%2$d variant%3$s, %4$d file%5$s).',
        'Added %1$s as a Bunny CDN delivery (%2$d variant%3$s).'
    );

    $result = $strategy->importFaces('Test', 'test', [$face], ['type' => 'bunny'], $config);

    assertFalseValue(is_wp_error($result), 'CdnImportStrategy should return a result.');
    assertTrueValue($result instanceof ImportFacesResult, 'CdnImportStrategy should return ImportFacesResult.');
    assertSameValue(1, count($result->faces), 'CdnImportStrategy should return one face.');
    assertSameValue('https://example.com/font.woff2', (string) ($result->faces[0]['files']['woff2'] ?? ''), 'CdnImportStrategy should preserve file URLs.');
    assertSameValue(0, $result->files, 'CdnImportStrategy should report 0 downloaded files.');
    assertSameValue('bunny', (string) ($result->faces[0]['source'] ?? ''), 'CdnImportStrategy should set the source from config.');
};

$tests['cdn_import_strategy_returns_face_without_files'] = static function (): void {
    $strategy = new CdnImportStrategy();
    $face = [
        'family' => 'Test',
        'weight' => '400',
        'style' => 'normal',
    ];
    $config = new HostedImportProviderConfig(
        'bunny',
        'bunny',
        'example.com',
        10 * MB_IN_BYTES,
        'tasty_fonts_bunny_family_dir_failed',
        'The Bunny Fonts import directory could not be created.',
        'tasty_fonts_bunny_invalid_host',
        'Bunny font downloads must come from fonts.bunny.net.',
        'tasty_fonts_bunny_invalid_extension',
        'Only WOFF2 files can be imported from Bunny Fonts.',
        'tasty_fonts_bunny_download_failed',
        'Font download failed with status %d.',
        'tasty_fonts_bunny_empty_file',
        'Bunny Fonts returned an empty font file.',
        'tasty_fonts_bunny_file_too_large',
        'The downloaded font file exceeded the safety size limit.',
        'tasty_fonts_bunny_invalid_type',
        'The downloaded file was not returned as a WOFF2 font.',
        'tasty_fonts_bunny_write_failed',
        'The imported font file could not be written to uploads/fonts.',
        'tasty_fonts_bunny_no_faces',
        'No usable Bunny Fonts faces were returned for that family.',
        'tasty_fonts_bunny_empty_manifest',
        'No local font files were saved from that import.',
        'tasty_fonts_missing_family',
        'Choose a Bunny Fonts family before importing.',
        '%s already exists in the library for the selected variants.',
        'Bunny CDN delivery for %s already includes the selected variants.',
        'Added %1$s as a self-hosted Bunny delivery (%2$d variant%3$s, %4$d file%5$s).',
        'Added %1$s as a Bunny CDN delivery (%2$d variant%3$s).'
    );

    $result = $strategy->importFaces('Test', 'test', [$face], ['type' => 'bunny'], $config);

    assertFalseValue(is_wp_error($result), 'CdnImportStrategy should return a result for face without files.');
    assertSameValue(1, count($result->faces), 'CdnImportStrategy should still produce a stored-face entry.');
    assertSameValue([], $result->faces[0]['files'] ?? null, 'CdnImportStrategy should produce empty files map when no files are present.');
};

$tests['cdn_import_strategy_returns_multiple_faces'] = static function (): void {
    $strategy = new CdnImportStrategy();
    $faces = [
        ['family' => 'Test', 'weight' => '400', 'style' => 'normal', 'files' => ['woff2' => 'https://a/1.woff2']],
        ['family' => 'Test', 'weight' => '700', 'style' => 'italic', 'files' => ['woff2' => 'https://a/2.woff2']],
    ];
    $config = new HostedImportProviderConfig(
        'bunny',
        'bunny',
        'example.com',
        10 * MB_IN_BYTES,
        'tasty_fonts_bunny_family_dir_failed',
        'The Bunny Fonts import directory could not be created.',
        'tasty_fonts_bunny_invalid_host',
        'Bunny font downloads must come from fonts.bunny.net.',
        'tasty_fonts_bunny_invalid_extension',
        'Only WOFF2 files can be imported from Bunny Fonts.',
        'tasty_fonts_bunny_download_failed',
        'Font download failed with status %d.',
        'tasty_fonts_bunny_empty_file',
        'Bunny Fonts returned an empty font file.',
        'tasty_fonts_bunny_file_too_large',
        'The downloaded font file exceeded the safety size limit.',
        'tasty_fonts_bunny_invalid_type',
        'The downloaded file was not returned as a WOFF2 font.',
        'tasty_fonts_bunny_write_failed',
        'The imported font file could not be written to uploads/fonts.',
        'tasty_fonts_bunny_no_faces',
        'No usable Bunny Fonts faces were returned for that family.',
        'tasty_fonts_bunny_empty_manifest',
        'No local font files were saved from that import.',
        'tasty_fonts_missing_family',
        'Choose a Bunny Fonts family before importing.',
        '%s already exists in the library for the selected variants.',
        'Bunny CDN delivery for %s already includes the selected variants.',
        'Added %1$s as a self-hosted Bunny delivery (%2$d variant%3$s, %4$d file%5$s).',
        'Added %1$s as a Bunny CDN delivery (%2$d variant%3$s).'
    );

    $result = $strategy->importFaces('Test', 'test', $faces, ['type' => 'bunny'], $config);

    assertSameValue(2, count($result->faces), 'CdnImportStrategy should return correct face count.');
    assertSameValue(0, $result->files, 'CdnImportStrategy should report 0 files for multiple faces.');
};

$tests['self_hosted_import_strategy_supports_self_hosted_only'] = static function (): void {
    $strategy = new SelfHostedImportStrategy(new Storage());

    assertTrueValue($strategy->supports('self_hosted'), 'SelfHostedImportStrategy should support self_hosted mode.');
    assertFalseValue($strategy->supports('cdn'), 'SelfHostedImportStrategy should not support cdn mode.');
};

$tests['self_hosted_import_strategy_resolves_directory_and_downloads'] = static function (): void {
    resetTestState();
    global $remoteGetResponses;

    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];

    $storage = new Storage();
    $strategy = new SelfHostedImportStrategy($storage);
    $face = [
        'family' => 'Inter',
        'weight' => '400',
        'style' => 'normal',
        'files' => ['woff2' => $fontUrl],
    ];
    $config = new HostedImportProviderConfig(
        'bunny',
        'bunny',
        'fonts.bunny.net',
        10 * MB_IN_BYTES,
        'tasty_fonts_bunny_family_dir_failed',
        'The Bunny Fonts import directory could not be created.',
        'tasty_fonts_bunny_invalid_host',
        'Bunny font downloads must come from fonts.bunny.net.',
        'tasty_fonts_bunny_invalid_extension',
        'Only WOFF2 files can be imported from Bunny Fonts.',
        'tasty_fonts_bunny_download_failed',
        'Font download failed with status %d.',
        'tasty_fonts_bunny_empty_file',
        'Bunny Fonts returned an empty font file.',
        'tasty_fonts_bunny_file_too_large',
        'The downloaded font file exceeded the safety size limit.',
        'tasty_fonts_bunny_invalid_type',
        'The downloaded file was not returned as a WOFF2 font.',
        'tasty_fonts_bunny_write_failed',
        'The imported font file could not be written to uploads/fonts.',
        'tasty_fonts_bunny_no_faces',
        'No usable Bunny Fonts faces were returned for that family.',
        'tasty_fonts_bunny_empty_manifest',
        'No local font files were saved from that import.',
        'tasty_fonts_missing_family',
        'Choose a Bunny Fonts family before importing.',
        '%s already exists in the library for the selected variants.',
        'Bunny CDN delivery for %s already includes the selected variants.',
        'Added %1$s as a self-hosted Bunny delivery (%2$d variant%3$s, %4$d file%5$s).',
        'Added %1$s as a Bunny CDN delivery (%2$d variant%3$s).'
    );

    $result = $strategy->importFaces('Inter', 'inter', [$face], ['type' => 'bunny'], $config);

    assertFalseValue(is_wp_error($result), 'SelfHostedImportStrategy should return a result on success.');
    assertTrueValue($result instanceof ImportFacesResult, 'SelfHostedImportStrategy should return ImportFacesResult.');
    assertSameValue(1, count($result->faces), 'SelfHostedImportStrategy should return one face.');
    assertSameValue(1, $result->files, 'SelfHostedImportStrategy should report 1 downloaded file.');
    assertSameValue('bunny/inter/inter-400-normal.woff2', (string) ($result->faces[0]['files']['woff2'] ?? ''), 'SelfHostedImportStrategy should store relative path.');
    $storedPath = $storage->pathForRelativePath('bunny/inter/inter-400-normal.woff2');
    assertSameValue(true, is_string($storedPath) && file_exists($storedPath), 'SelfHostedImportStrategy should write the file to disk.');
};

$tests['self_hosted_import_strategy_returns_error_on_download_failure'] = static function (): void {
    resetTestState();
    global $remoteGetResponses;

    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $remoteGetResponses[$fontUrl] = new WP_Error('http_request_failed', 'Connection timed out.');

    $strategy = new SelfHostedImportStrategy(new Storage());
    $face = [
        'family' => 'Inter',
        'weight' => '400',
        'style' => 'normal',
        'files' => ['woff2' => $fontUrl],
    ];
    $config = new HostedImportProviderConfig(
        'bunny',
        'bunny',
        'fonts.bunny.net',
        10 * MB_IN_BYTES,
        'tasty_fonts_bunny_family_dir_failed',
        'The Bunny Fonts import directory could not be created.',
        'tasty_fonts_bunny_invalid_host',
        'Bunny font downloads must come from fonts.bunny.net.',
        'tasty_fonts_bunny_invalid_extension',
        'Only WOFF2 files can be imported from Bunny Fonts.',
        'tasty_fonts_bunny_download_failed',
        'Font download failed with status %d.',
        'tasty_fonts_bunny_empty_file',
        'Bunny Fonts returned an empty font file.',
        'tasty_fonts_bunny_file_too_large',
        'The downloaded font file exceeded the safety size limit.',
        'tasty_fonts_bunny_invalid_type',
        'The downloaded file was not returned as a WOFF2 font.',
        'tasty_fonts_bunny_write_failed',
        'The imported font file could not be written to uploads/fonts.',
        'tasty_fonts_bunny_no_faces',
        'No usable Bunny Fonts faces were returned for that family.',
        'tasty_fonts_bunny_empty_manifest',
        'No local font files were saved from that import.',
        'tasty_fonts_missing_family',
        'Choose a Bunny Fonts family before importing.',
        '%s already exists in the library for the selected variants.',
        'Bunny CDN delivery for %s already includes the selected variants.',
        'Added %1$s as a self-hosted Bunny delivery (%2$d variant%3$s, %4$d file%5$s).',
        'Added %1$s as a Bunny CDN delivery (%2$d variant%3$s).'
    );

    $result = $strategy->importFaces('Inter', 'inter', [$face], ['type' => 'bunny'], $config);

    assertTrueValue(is_wp_error($result), 'SelfHostedImportStrategy should return WP_Error on download failure.');
    assertSameValue('http_request_failed', $result->get_error_code(), 'SelfHostedImportStrategy should preserve error code.');
};

$tests['self_hosted_import_strategy_returns_empty_faces_for_non_woff2'] = static function (): void {
    resetTestState();
    $strategy = new SelfHostedImportStrategy(new Storage());
    $face = [
        'family' => 'Inter',
        'weight' => '400',
        'style' => 'normal',
        'files' => ['woff' => 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff'],
    ];
    $config = new HostedImportProviderConfig(
        'bunny',
        'bunny',
        'fonts.bunny.net',
        10 * MB_IN_BYTES,
        'tasty_fonts_bunny_family_dir_failed',
        'The Bunny Fonts import directory could not be created.',
        'tasty_fonts_bunny_invalid_host',
        'Bunny font downloads must come from fonts.bunny.net.',
        'tasty_fonts_bunny_invalid_extension',
        'Only WOFF2 files can be imported from Bunny Fonts.',
        'tasty_fonts_bunny_download_failed',
        'Font download failed with status %d.',
        'tasty_fonts_bunny_empty_file',
        'Bunny Fonts returned an empty font file.',
        'tasty_fonts_bunny_file_too_large',
        'The downloaded font file exceeded the safety size limit.',
        'tasty_fonts_bunny_invalid_type',
        'The downloaded file was not returned as a WOFF2 font.',
        'tasty_fonts_bunny_write_failed',
        'The imported font file could not be written to uploads/fonts.',
        'tasty_fonts_bunny_no_faces',
        'No usable Bunny Fonts faces were returned for that family.',
        'tasty_fonts_bunny_empty_manifest',
        'No local font files were saved from that import.',
        'tasty_fonts_missing_family',
        'Choose a Bunny Fonts family before importing.',
        '%s already exists in the library for the selected variants.',
        'Bunny CDN delivery for %s already includes the selected variants.',
        'Added %1$s as a self-hosted Bunny delivery (%2$d variant%3$s, %4$d file%5$s).',
        'Added %1$s as a Bunny CDN delivery (%2$d variant%3$s).'
    );

    $result = $strategy->importFaces('Inter', 'inter', [$face], ['type' => 'bunny'], $config);

    assertTrueValue($result instanceof ImportFacesResult, 'SelfHostedImportStrategy should return ImportFacesResult even for empty manifest.');
    assertSameValue([], $result->faces, 'SelfHostedImportStrategy should return empty faces when no woff2 files.');
    assertSameValue(0, $result->files, 'SelfHostedImportStrategy should report 0 files for empty manifest.');
};

$tests['self_hosted_import_strategy_skips_existing_files'] = static function (): void {
    resetTestState();
    global $remoteGetResponses;

    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];

    $storage = new Storage();
    $strategy = new SelfHostedImportStrategy($storage);
    $face = [
        'family' => 'Inter',
        'weight' => '400',
        'style' => 'normal',
        'files' => ['woff2' => $fontUrl],
    ];
    $config = new HostedImportProviderConfig(
        'bunny',
        'bunny',
        'fonts.bunny.net',
        10 * MB_IN_BYTES,
        'tasty_fonts_bunny_family_dir_failed',
        'The Bunny Fonts import directory could not be created.',
        'tasty_fonts_bunny_invalid_host',
        'Bunny font downloads must come from fonts.bunny.net.',
        'tasty_fonts_bunny_invalid_extension',
        'Only WOFF2 files can be imported from Bunny Fonts.',
        'tasty_fonts_bunny_download_failed',
        'Font download failed with status %d.',
        'tasty_fonts_bunny_empty_file',
        'Bunny Fonts returned an empty font file.',
        'tasty_fonts_bunny_file_too_large',
        'The downloaded font file exceeded the safety size limit.',
        'tasty_fonts_bunny_invalid_type',
        'The downloaded file was not returned as a WOFF2 font.',
        'tasty_fonts_bunny_write_failed',
        'The imported font file could not be written to uploads/fonts.',
        'tasty_fonts_bunny_no_faces',
        'No usable Bunny Fonts faces were returned for that family.',
        'tasty_fonts_bunny_empty_manifest',
        'No local font files were saved from that import.',
        'tasty_fonts_missing_family',
        'Choose a Bunny Fonts family before importing.',
        '%s already exists in the library for the selected variants.',
        'Bunny CDN delivery for %s already includes the selected variants.',
        'Added %1$s as a self-hosted Bunny delivery (%2$d variant%3$s, %4$d file%5$s).',
        'Added %1$s as a Bunny CDN delivery (%2$d variant%3$s).'
    );

    $first = $strategy->importFaces('Inter', 'inter', [$face], ['type' => 'bunny'], $config);
    assertSameValue(1, $first->files, 'First import should download 1 file.');

    $second = $strategy->importFaces('Inter', 'inter', [$face], ['type' => 'bunny'], $config);
    assertSameValue(0, $second->files, 'Second import should skip existing file and report 0 downloads.');
    assertSameValue(1, count($second->faces), 'Second import should still return the face.');
};

$tests['workflow_selects_correct_strategy_by_delivery_mode'] = static function (): void {
    resetTestState();
    global $remoteGetResponses;

    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];
    $services = makeHostedImportWorkflowTestGraph();
    $adapter = new HostedImportWorkflowStubAdapter([hostedImportWorkflowRegularFace($fontUrl)]);

    $cdnResult = $services['hosted_import_workflow']->import(
        new HostedImportRequest('Inter', ['regular'], 'cdn'),
        $adapter
    );
    assertSameValue('saved', (string) ($cdnResult['status'] ?? ''), 'CDN delivery mode should produce saved status.');

    resetTestState();
    global $remoteGetResponses;
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];
    $services2 = makeHostedImportWorkflowTestGraph();
    $adapter2 = new HostedImportWorkflowStubAdapter([hostedImportWorkflowRegularFace($fontUrl)]);
    $selfHostedResult = $services2['hosted_import_workflow']->import(
        new HostedImportRequest('Inter', ['regular'], 'self_hosted'),
        $adapter2
    );
    assertSameValue('imported', (string) ($selfHostedResult['status'] ?? ''), 'Self-hosted delivery mode should produce imported status.');
};

$tests['workflow_handles_strategy_wp_error'] = static function (): void {
    resetTestState();
    global $remoteGetResponses;

    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $remoteGetResponses[$fontUrl] = new WP_Error('http_request_failed', 'cURL error 28: Connection timed out.');
    $services = makeHostedImportWorkflowTestGraph();
    $adapter = new HostedImportWorkflowStubAdapter([hostedImportWorkflowRegularFace($fontUrl)]);

    $result = $services['hosted_import_workflow']->import(
        new HostedImportRequest('Inter', ['regular'], 'self_hosted'),
        $adapter
    );

    assertTrueValue(is_wp_error($result), 'Workflow should propagate strategy WP_Error.');
    assertSameValue('http_request_failed', $result->get_error_code(), 'Workflow should preserve strategy error code.');
    assertSameValue(0, did_action('tasty_fonts_after_import'), 'Failed workflow imports should not fire the after-import hook.');
    assertSameValue(null, $services['imports']->getFamily('inter'), 'Failed workflow imports should not persist profiles.');
};

$tests['workflow_handles_empty_faces_after_strategy'] = static function (): void {
    resetTestState();
    $services = makeHostedImportWorkflowTestGraph();
    $adapter = new HostedImportWorkflowStubAdapter([]);

    $result = $services['hosted_import_workflow']->import(
        new HostedImportRequest('Inter', ['regular'], 'cdn'),
        $adapter
    );

    assertTrueValue(is_wp_error($result), 'Workflow should return WP_Error when no faces are found.');
    assertSameValue('tasty_fonts_bunny_no_faces', $result->get_error_code(), 'Workflow should return no-faces error.');
};

$tests['workflow_works_with_test_double_strategy'] = static function (): void {
    resetTestState();
    $testStrategy = new class implements DeliveryImportStrategy {
        public bool $wasCalled = false;

        public function supports(string $deliveryMode): bool
        {
            return $deliveryMode === 'self_hosted';
        }

        public function importFaces(
            string $familyName,
            string $familySlug,
            array $faces,
            array $provider,
            HostedImportProviderConfig $config
        ): ImportFacesResult|WP_Error {
            $this->wasCalled = true;
            return new ImportFacesResult([
                [
                    'family' => $familyName,
                    'slug' => $familySlug,
                    'source' => 'test-double',
                    'weight' => '400',
                    'style' => 'normal',
                    'files' => ['woff2' => 'https://example.com/font.woff2'],
                    'provider' => $provider,
                ]
            ], 0);
        }
    };

    $services = makeHostedImportWorkflowTestGraph();
    $workflow = new HostedImportWorkflow(
        $services['imports'],
        $services['assets'],
        $services['log'],
        new HostedImportVariantPlanner(),
        [
            'cdn' => new CdnImportStrategy(),
            'self_hosted' => $testStrategy,
        ]
    );

    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $adapter = new HostedImportWorkflowStubAdapter([hostedImportWorkflowRegularFace($fontUrl)]);

    $result = $workflow->import(
        new HostedImportRequest('Inter', ['regular'], 'self_hosted'),
        $adapter
    );

    assertTrueValue($testStrategy->wasCalled, 'Test-double strategy should be invoked.');
    assertFalseValue(is_wp_error($result), 'Workflow with test-double should return a result.');
    assertSameValue('imported', (string) ($result['status'] ?? ''), 'Test-double self-hosted should produce imported status.');
    assertSameValue('https://example.com/font.woff2', (string) ($result['family_record']['delivery_profiles']['bunny-self_hosted']['faces'][0]['files']['woff2'] ?? ''), 'Test-double faces should use the test-double file URL.');
};
