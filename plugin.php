<?php
/*
Plugin Name: Etch Custom Fonts
Description: Self-host local and Google Fonts for Etch, Gutenberg, and the frontend.
Author: Tasty WP
Version: 1.0.1
Text Domain: etch-fonts
*/

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('ETCH_FONTS_VERSION', '1.0.1');
define('ETCH_FONTS_FILE', __FILE__);
define('ETCH_FONTS_DIR', plugin_dir_path(__FILE__));
define('ETCH_FONTS_URL', plugin_dir_url(__FILE__));
define('ETCH_FONTS_TEXT_DOMAIN', 'etch-fonts');

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'EtchFonts\\';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = ETCH_FONTS_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    }
);

register_activation_hook(__FILE__, ['EtchFonts\\Plugin', 'activate']);

EtchFonts\Plugin::instance()->boot();
