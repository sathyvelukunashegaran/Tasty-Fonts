<?php

declare(strict_types=1);

use TastyFonts\Fonts\AssetService;
use TastyFonts\Plugin;
use TastyFonts\Support\Storage;

$tests['plugin_activation_creates_provider_index_files_and_generated_css'] = static function (): void {
    resetTestState();
    resetPluginSingleton();

    Plugin::activate();

    $storage = new Storage();
    $root = $storage->getRoot();
    $cssPath = $storage->getGeneratedCssPath();

    assertTrueValue(is_string($root) && $root !== '', 'Plugin activation should initialize the uploads/fonts storage root.');
    assertTrueValue(is_string($cssPath) && is_file($cssPath), 'Plugin activation should generate the runtime stylesheet file.');
    assertTrueValue(is_file($root . '/index.php'), 'Plugin activation should create the root index.php stub.');
    assertTrueValue(is_file($root . '/google/index.php'), 'Plugin activation should create the Google provider index.php stub.');
    assertTrueValue(is_file($root . '/bunny/index.php'), 'Plugin activation should create the Bunny provider index.php stub.');
    assertTrueValue(is_file($root . '/upload/index.php'), 'Plugin activation should create the local upload index.php stub.');
    assertTrueValue(is_file($root . '/adobe/index.php'), 'Plugin activation should create the Adobe provider index.php stub.');
    assertTrueValue(is_file($root . '/.generated/index.php'), 'Plugin activation should create the generated-assets index.php stub.');
    assertContainsValue('Silence is golden', (string) file_get_contents($root . '/index.php'), 'Plugin activation should write the silence-is-golden index stub.');

    resetPluginSingleton();
};

$tests['plugin_attachment_hooks_only_invalidate_catalog_for_files_within_font_storage'] = static function (): void {
    global $attachedFilePaths;
    global $optionStore;
    global $transientDeleted;
    global $transientStore;

    resetTestState();
    resetPluginSingleton();

    $storage = new Storage();
    $root = $storage->getRoot();

    Plugin::instance()->boot();

    $attachedFilePaths[100] = uniqueTestDirectory('outside-root') . '/inter.woff2';
    $transientStore['tasty_fonts_catalog_v2'] = ['cached' => true];
    do_action('add_attachment', 100);

    assertFalseValue(
        in_array('tasty_fonts_catalog_v2', $transientDeleted, true),
        'Attachment hooks should ignore media changes outside uploads/fonts.'
    );

    $attachedFilePaths[101] = $root . '/google/inter/inter-400-normal.woff2';
    $transientStore['tasty_fonts_catalog_v2'] = ['cached' => true];
    do_action('delete_attachment', 101);

    assertTrueValue(
        in_array('tasty_fonts_catalog_v2', $transientDeleted, true),
        'Attachment hooks should invalidate the cached catalog when a font attachment changes inside uploads/fonts.'
    );
    assertContainsValue(
        'Font attachment changed. Catalog cache cleared.',
        wp_json_encode($optionStore['tasty_fonts_log'] ?? []),
        'Attachment invalidation should leave an audit log entry for the cache clear.'
    );

    resetPluginSingleton();
};

$tests['local_upload_service_successfully_imported_rows_queue_generated_asset_refresh'] = static function (): void {
    global $scheduledEvents;
    global $transientStore;
    global $uploadedFilePaths;

    resetTestState();

    $tmpDirectory = uniqueTestDirectory('native-upload');
    wp_mkdir_p($tmpDirectory);

    $tmpFile = $tmpDirectory . '/inter-400-normal.woff2';
    file_put_contents($tmpFile, 'wOF2test-font');
    $uploadedFilePaths[] = $tmpFile;

    $services = makeServiceGraph();
    $result = $services['local_upload']->uploadRows([
        [
            'family' => 'Inter',
            'weight' => '400',
            'style' => 'normal',
            'fallback' => 'sans-serif',
            'file' => [
                'name' => 'Inter-Regular.woff2',
                'tmp_name' => $tmpFile,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmpFile),
            ],
        ],
    ]);

    assertArrayHasKeys(['message', 'rows', 'summary', 'families'], $result, 'Successful local uploads should return the expected response payload.');
    assertSameValue(['Inter'], $result['families'], 'Successful local uploads should report the imported family list.');
    assertSameValue(
        AssetService::ACTION_REGENERATE_CSS,
        $scheduledEvents[0]['hook'] ?? '',
        'Successful local uploads should queue generated CSS regeneration through AssetService.'
    );
    assertSameValue(
        ['log_write_result' => 1],
        $transientStore['tasty_fonts_regenerate_css_queued'] ?? null,
        'Successful local uploads should persist the deferred generated CSS write state.'
    );
};
