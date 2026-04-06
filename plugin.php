<?php
/*
Plugin Name: Tasty Custom Fonts
Plugin URI: https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts
Description: Self-host local, Google, and Bunny Fonts, with optional Adobe Fonts web project support for Etch, Gutenberg, and the frontend.
Version: 1.4.0
Author: Tasty WP
Author URI: https://github.com/sathyvelukunashegaran
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: tasty-fonts
Requires at least: 6.1
Requires PHP: 8.1
*/

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('TASTY_FONTS_VERSION', '1.4.0');
define('TASTY_FONTS_FILE', __FILE__);
define('TASTY_FONTS_DIR', plugin_dir_path(__FILE__));
define('TASTY_FONTS_URL', plugin_dir_url(__FILE__));

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

register_activation_hook(__FILE__, ['TastyFonts\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['TastyFonts\\Plugin', 'deactivate']);

add_action(
    'plugins_loaded',
    static function (): void {
        TastyFonts\Plugin::instance()->boot();
    },
    0
);
