<?php

declare(strict_types=1);

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

    assertSameValue('imported', (string) ($result['status'] ?? ''), 'Google imports should still succeed when the after-import action is registered.');
    assertSameValue(1, did_action('tasty_fonts_after_import'), 'Google imports should fire the tasty_fonts_after_import action.');
    assertSameValue('google', $importProvider, 'Google imports should identify the provider when firing tasty_fonts_after_import.');
    assertSameValue('imported', (string) ($importResult['status'] ?? ''), 'Google imports should pass the import result payload to tasty_fonts_after_import.');
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

    assertSameValue(1, (int) ($result['summary']['imported'] ?? 0), 'Verified HTTP uploads should be imported into the local library.');
    assertSameValue('imported', (string) ($result['rows'][0]['status'] ?? ''), 'Verified HTTP uploads should produce an imported row result.');
    assertSameValue(true, is_string($savedPath) && file_exists($savedPath), 'Verified HTTP uploads should be written into the dedicated local upload folder.');
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
