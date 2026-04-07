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
$blockEditorFontLibrary = new \TastyFonts\Fonts\BlockEditorFontLibraryService(
    $storage,
    $importRepository,
    $settingsRepository,
    $logRepository
);

$handler = new \TastyFonts\Uninstall\UninstallHandler(
    is_array($settings) ? $settings : [],
    $storage,
    $blockEditorFontLibrary
);

$handler->run();
