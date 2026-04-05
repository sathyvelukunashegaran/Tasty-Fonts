<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

const TASTY_FONTS_UNINSTALL_OPTION_SETTINGS = 'tasty_fonts_settings';
const TASTY_FONTS_UNINSTALL_OPTION_ROLES = 'tasty_fonts_roles';
const TASTY_FONTS_UNINSTALL_OPTION_LIBRARY = 'tasty_fonts_library';
const TASTY_FONTS_UNINSTALL_OPTION_IMPORTS = 'tasty_fonts_imports';
const TASTY_FONTS_UNINSTALL_OPTION_LOG = 'tasty_fonts_log';
const TASTY_FONTS_UNINSTALL_LEGACY_OPTION_SETTINGS = 'etch_fonts_settings';
const TASTY_FONTS_UNINSTALL_LEGACY_OPTION_ROLES = 'etch_fonts_roles';
const TASTY_FONTS_UNINSTALL_LEGACY_OPTION_IMPORTS = 'etch_fonts_imports';
const TASTY_FONTS_UNINSTALL_LEGACY_OPTION_LOG = 'etch_fonts_log';
const TASTY_FONTS_UNINSTALL_TRANSIENT_CATALOG = 'tasty_fonts_catalog_v2';
const TASTY_FONTS_UNINSTALL_TRANSIENT_CSS = 'tasty_fonts_css_v2';
const TASTY_FONTS_UNINSTALL_TRANSIENT_HASH = 'tasty_fonts_css_hash_v2';
const TASTY_FONTS_UNINSTALL_TRANSIENT_GOOGLE_CATALOG = 'tasty_fonts_google_catalog_v1';
const TASTY_FONTS_UNINSTALL_TRANSIENT_BUNNY_CATALOG = 'tasty_fonts_bunny_catalog_v1';
const TASTY_FONTS_UNINSTALL_TRANSIENT_BUNNY_FAMILY_PREFIX = 'tasty_fonts_bunny_family_';
const TASTY_FONTS_UNINSTALL_TRANSIENT_ADMIN_NOTICES_PREFIX = 'tasty_fonts_admin_notices_';
const TASTY_FONTS_UNINSTALL_TRANSIENT_ADOBE_PREFIX = 'tasty_fonts_adobe_project_v1_';

$settings = get_option(TASTY_FONTS_UNINSTALL_OPTION_SETTINGS, []);
$deleteUploadedFiles = !empty($settings['delete_uploaded_files_on_uninstall']);
$adobeProjectId = is_array($settings) ? trim((string) ($settings['adobe_project_id'] ?? '')) : '';

foreach (
    [
        TASTY_FONTS_UNINSTALL_OPTION_SETTINGS,
        TASTY_FONTS_UNINSTALL_OPTION_ROLES,
        TASTY_FONTS_UNINSTALL_OPTION_LIBRARY,
        TASTY_FONTS_UNINSTALL_OPTION_IMPORTS,
        TASTY_FONTS_UNINSTALL_OPTION_LOG,
        TASTY_FONTS_UNINSTALL_LEGACY_OPTION_SETTINGS,
        TASTY_FONTS_UNINSTALL_LEGACY_OPTION_ROLES,
        TASTY_FONTS_UNINSTALL_LEGACY_OPTION_IMPORTS,
        TASTY_FONTS_UNINSTALL_LEGACY_OPTION_LOG,
    ] as $optionKey
) {
    delete_option($optionKey);
}

foreach (
    [
        TASTY_FONTS_UNINSTALL_TRANSIENT_CATALOG,
        TASTY_FONTS_UNINSTALL_TRANSIENT_CSS,
        TASTY_FONTS_UNINSTALL_TRANSIENT_HASH,
        TASTY_FONTS_UNINSTALL_TRANSIENT_GOOGLE_CATALOG,
        TASTY_FONTS_UNINSTALL_TRANSIENT_BUNNY_CATALOG,
    ] as $transientKey
) {
    delete_transient($transientKey);
}

global $wpdb;

if (
    isset($wpdb)
    && is_object($wpdb)
    && isset($wpdb->options)
    && method_exists($wpdb, 'esc_like')
    && method_exists($wpdb, 'prepare')
    && method_exists($wpdb, 'query')
) {
    $bunnyFamilyTransientPattern = $wpdb->esc_like('_transient_' . TASTY_FONTS_UNINSTALL_TRANSIENT_BUNNY_FAMILY_PREFIX) . '%';
    $bunnyFamilyTimeoutPattern = $wpdb->esc_like('_transient_timeout_' . TASTY_FONTS_UNINSTALL_TRANSIENT_BUNNY_FAMILY_PREFIX) . '%';

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $bunnyFamilyTransientPattern,
            $bunnyFamilyTimeoutPattern
        )
    );

    $adminNoticeTransientPattern = $wpdb->esc_like('_transient_' . TASTY_FONTS_UNINSTALL_TRANSIENT_ADMIN_NOTICES_PREFIX) . '%';
    $adminNoticeTimeoutPattern = $wpdb->esc_like('_transient_timeout_' . TASTY_FONTS_UNINSTALL_TRANSIENT_ADMIN_NOTICES_PREFIX) . '%';

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $adminNoticeTransientPattern,
            $adminNoticeTimeoutPattern
        )
    );
}

if ($adobeProjectId !== '') {
    delete_transient(TASTY_FONTS_UNINSTALL_TRANSIENT_ADOBE_PREFIX . md5(preg_replace('/[^a-z0-9]+/', '', strtolower($adobeProjectId)) ?? ''));
}

if (!$deleteUploadedFiles) {
    return;
}

$uploads = wp_get_upload_dir();
$fontsRoot = is_array($uploads) ? ($uploads['basedir'] ?? '') : '';
$fontsRoot = is_string($fontsRoot) && $fontsRoot !== '' ? rtrim(str_replace('\\', '/', $fontsRoot), '/') . '/fonts' : '';

if ($fontsRoot === '' || !is_dir($fontsRoot)) {
    return;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($fontsRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($iterator as $item) {
    $path = $item->getPathname();

    if ($item->isDir()) {
        @rmdir($path);
        continue;
    }

    @unlink($path);
}

@rmdir($fontsRoot);
