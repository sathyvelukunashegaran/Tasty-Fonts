<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/plugin.php';

$settings = get_option(\TastyFonts\Repository\SettingsRepository::OPTION_SETTINGS, []);
$storage = new \TastyFonts\Support\Storage();
$settingsRepository = new \TastyFonts\Repository\SettingsRepository();
$importRepository = new \TastyFonts\Repository\ImportRepository();
$logRepository = new \TastyFonts\Repository\LogRepository();
$adobeClient = new \TastyFonts\Adobe\AdobeProjectClient(
    $settingsRepository,
    new \TastyFonts\Repository\AdobeProjectRepository(),
    new \TastyFonts\Adobe\AdobeCssParser()
);
$googleClient = new \TastyFonts\Google\GoogleFontsClient(
    $settingsRepository,
    new \TastyFonts\Repository\GoogleApiKeyRepository()
);
$bunnyClient = new \TastyFonts\Bunny\BunnyFontsClient();
$catalog = new \TastyFonts\Fonts\CatalogService(
    $storage,
    $importRepository,
    new \TastyFonts\Fonts\FontFilenameParser(),
    $logRepository,
    $adobeClient
);
$planner = new \TastyFonts\Fonts\RuntimeAssetPlanner(
    $catalog,
    $settingsRepository,
    [
        new \TastyFonts\Fonts\GoogleStylesheetResolver($googleClient),
        new \TastyFonts\Fonts\BunnyStylesheetResolver($bunnyClient),
        new \TastyFonts\Fonts\AdobeStylesheetResolver($adobeClient),
    ],
    new \TastyFonts\Repository\RoleRepository(),
    new \TastyFonts\Repository\FamilyMetadataRepository()
);
$assetService = new \TastyFonts\Fonts\AssetService(
    $storage,
    $catalog,
    $settingsRepository,
    new \TastyFonts\Fonts\CssBuilder(),
    $planner,
    $logRepository,
    new \TastyFonts\Repository\RoleRepository()
);
$blockEditorFontLibrary = new \TastyFonts\Fonts\BlockEditorFontLibraryService(
    $storage,
    $importRepository,
    $settingsRepository,
    $logRepository
);
$developerTools = new \TastyFonts\Maintenance\DeveloperToolsService(
    $storage,
    $settingsRepository,
    $importRepository,
    $catalog,
    $assetService,
    $blockEditorFontLibrary,
    $googleClient,
    new \TastyFonts\Repository\FamilyMetadataRepository()
);

$handler = new \TastyFonts\Uninstall\UninstallHandler(
    is_array($settings) ? $settings : [],
    $storage,
    $blockEditorFontLibrary,
    $developerTools
);

$handler->run();
