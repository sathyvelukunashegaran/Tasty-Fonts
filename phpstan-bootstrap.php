<?php

declare(strict_types=1);

$pluginFile = __DIR__ . '/plugin.php';
$pluginDirectory = __DIR__ . '/';
$pluginVersion = '0.0.0';

if (is_readable($pluginFile)) {
    $pluginContents = file_get_contents($pluginFile);

    if (
        is_string($pluginContents)
        && preg_match("/^Version:\\s*(.+)$/m", $pluginContents, $matches) === 1
        && !empty($matches[1])
    ) {
        $pluginVersion = trim((string) $matches[1]);
    }
}

if (!defined('ABSPATH')) {
    define('ABSPATH', $pluginDirectory);
}

if (!defined('TASTY_FONTS_VERSION')) {
    define('TASTY_FONTS_VERSION', $pluginVersion);
}

if (!defined('TASTY_FONTS_FILE')) {
    define('TASTY_FONTS_FILE', $pluginFile);
}

if (!defined('TASTY_FONTS_DIR')) {
    define('TASTY_FONTS_DIR', $pluginDirectory);
}

if (!defined('TASTY_FONTS_URL')) {
    define('TASTY_FONTS_URL', 'https://example.test/wp-content/plugins/tasty-fonts/');
}

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'TastyFonts\\';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = TASTY_FONTS_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    }
);
