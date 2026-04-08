<?php

declare(strict_types=1);

namespace TastyFonts\Maintenance;

defined('ABSPATH') || exit;

use TastyFonts\Admin\AdminController;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\BlockEditorFontLibraryService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\Storage;
use WP_Error;

final class DeveloperToolsService
{
    private const STORAGE_INDEX_STUB = '<?php // Silence is golden.';
    private const UPDATER_LEGACY_RELEASE_TRANSIENT = 'tasty_fonts_github_release_v1';
    private const UPDATER_RELEASE_TRANSIENT = 'tasty_fonts_github_release_manifest_v1';
    private const UPDATER_INSTALLED_VERSION_TRANSIENT = 'tasty_fonts_github_release_version_v1';

    public function __construct(
        private readonly Storage $storage,
        private readonly SettingsRepository $settings,
        private readonly ImportRepository $imports,
        private readonly CatalogService $catalog,
        private readonly AssetService $assets,
        private readonly BlockEditorFontLibraryService $blockEditorFontLibrary,
        private readonly GoogleFontsClient $googleClient
    ) {
    }

    public function ensureStorageScaffolding(): bool
    {
        $root = $this->storage->getRoot();

        if (!is_string($root) || $root === '') {
            return false;
        }

        $generatedPath = $this->storage->getGeneratedCssPath();
        $directories = array_filter(
            [
                $root,
                $this->storage->getGoogleRoot(),
                $this->storage->getBunnyRoot(),
                $this->storage->getUploadRoot(),
                $this->storage->getAdobeRoot(),
                is_string($generatedPath) ? dirname($generatedPath) : '',
            ],
            static fn (mixed $path): bool => is_string($path) && $path !== ''
        );

        foreach ($directories as $directory) {
            if (!$this->storage->ensureDirectory($directory)) {
                return false;
            }
        }

        foreach ($this->scaffoldIndexPaths($root) as $path) {
            if (file_exists($path)) {
                continue;
            }

            if (!$this->storage->writeAbsoluteFile($path, self::STORAGE_INDEX_STUB)) {
                return false;
            }
        }

        return true;
    }

    public function resetPluginSettings(): array|WP_Error
    {
        $previousSettings = $this->settings->getSettings();

        do_action('tasty_fonts_before_reset_settings', $previousSettings);

        $this->settings->resetStoredSettingsToDefaults();
        delete_option(AdminController::LOCAL_ENV_NOTICE_OPTION);

        if (!$this->clearPluginCachesAndRegenerateAssetsInternal($previousSettings)) {
            return $this->maintenanceError(__('Plugin settings were reset, but generated assets could not be rebuilt.', 'tasty-fonts'));
        }

        $settings = $this->settings->getSettings();

        do_action('tasty_fonts_after_reset_settings', $settings, $previousSettings);

        return $settings;
    }

    public function wipeManagedFontLibrary(): array|WP_Error
    {
        $previousSettings = $this->settings->getSettings();
        $library = $this->imports->allFamilies();

        do_action('tasty_fonts_before_wipe_font_library', $previousSettings, $library);

        $this->blockEditorFontLibrary->deleteAllSyncedFamilies(true);

        $root = $this->storage->getRoot();

        if (is_string($root) && $root !== '' && file_exists($root) && !$this->storage->deleteAbsolutePath($root)) {
            return $this->maintenanceError(__('The managed font storage directory could not be removed.', 'tasty-fonts'));
        }

        $this->imports->clearLibrary();
        $this->settings->resetLibraryStateAfterWipe();

        if (!$this->ensureStorageScaffolding()) {
            return $this->maintenanceError(__('The empty font storage scaffold could not be recreated.', 'tasty-fonts'));
        }

        if (!$this->clearPluginCachesAndRegenerateAssetsInternal($previousSettings)) {
            return $this->maintenanceError(__('The font library was wiped, but generated assets could not be rebuilt.', 'tasty-fonts'));
        }

        $settings = $this->settings->getSettings();

        do_action('tasty_fonts_after_wipe_font_library', $settings, $previousSettings);

        return $settings;
    }

    public function clearPluginCachesAndRegenerateAssets(): bool
    {
        $settings = $this->settings->getSettings();

        do_action('tasty_fonts_before_clear_plugin_caches', $settings);

        $success = $this->clearPluginCachesAndRegenerateAssetsInternal($settings);

        do_action('tasty_fonts_after_clear_plugin_caches', $this->settings->getSettings(), $success);

        return $success;
    }

    public function resetIntegrationDetectionState(): array
    {
        $previousSettings = $this->settings->getSettings();

        do_action('tasty_fonts_before_reset_integration_detection', $previousSettings);

        $settings = $this->settings->resetIntegrationDetectionState();

        do_action('tasty_fonts_after_reset_integration_detection', $settings, $previousSettings);

        return $settings;
    }

    public function resetSuppressedNotices(): void
    {
        $settings = $this->settings->getSettings();
        $preferences = get_option(AdminController::LOCAL_ENV_NOTICE_OPTION, []);

        do_action('tasty_fonts_before_reset_suppressed_notices', $preferences, $settings);

        delete_option(AdminController::LOCAL_ENV_NOTICE_OPTION);

        do_action('tasty_fonts_after_reset_suppressed_notices', $this->settings->getSettings(), $preferences);
    }

    public function clearDeactivationCaches(): void
    {
        $this->deleteFixedTransients();

        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(AssetService::ACTION_REGENERATE_CSS);
        }
    }

    private function clearPluginCachesAndRegenerateAssetsInternal(array $settingsContext): bool
    {
        $this->deleteFixedTransients();
        $this->deleteWildcardTransients(
            [
                BunnyFontsClient::TRANSIENT_FAMILY_PREFIX,
                AdminController::SEARCH_CACHE_TRANSIENT_PREFIX,
                AdminController::SEARCH_COOLDOWN_TRANSIENT_PREFIX,
            ]
        );
        $this->deleteAdobeProjectTransient($settingsContext);

        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(AssetService::ACTION_REGENERATE_CSS);
        }

        $this->assets->refreshGeneratedAssets(true, false);

        return $this->assets->ensureGeneratedCssFile(false);
    }

    private function deleteFixedTransients(): void
    {
        $this->googleClient->clearCatalogCache();

        foreach (
            [
                CatalogService::TRANSIENT_CATALOG,
                AssetService::TRANSIENT_CSS,
                AssetService::TRANSIENT_HASH,
                AssetService::TRANSIENT_REGENERATE_CSS_QUEUED,
                BunnyFontsClient::TRANSIENT_CATALOG,
                self::UPDATER_LEGACY_RELEASE_TRANSIENT,
                self::UPDATER_RELEASE_TRANSIENT,
                self::UPDATER_INSTALLED_VERSION_TRANSIENT,
            ] as $transientKey
        ) {
            delete_transient($transientKey);
        }
    }

    private function deleteWildcardTransients(array $prefixes): void
    {
        global $wpdb;

        if (
            !isset($wpdb)
            || !is_object($wpdb)
            || !isset($wpdb->options)
            || !method_exists($wpdb, 'esc_like')
            || !method_exists($wpdb, 'prepare')
            || !method_exists($wpdb, 'query')
        ) {
            return;
        }

        foreach ($prefixes as $prefix) {
            if (!is_string($prefix) || $prefix === '') {
                continue;
            }

            $transientPattern = $wpdb->esc_like('_transient_' . $prefix) . '%';
            $timeoutPattern = $wpdb->esc_like('_transient_timeout_' . $prefix) . '%';

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $transientPattern,
                    $timeoutPattern
                )
            );
        }
    }

    private function deleteAdobeProjectTransient(array $settings): void
    {
        $projectId = strtolower(trim((string) ($settings['adobe_project_id'] ?? '')));
        $projectId = preg_replace('/[^a-z0-9]+/', '', $projectId) ?? '';

        if ($projectId === '') {
            return;
        }

        delete_transient(AdobeProjectClient::TRANSIENT_PREFIX . md5($projectId));
    }

    private function scaffoldIndexPaths(string $root): array
    {
        return [
            trailingslashit($root) . 'index.php',
            trailingslashit($root) . 'google/index.php',
            trailingslashit($root) . 'bunny/index.php',
            trailingslashit($root) . 'upload/index.php',
            trailingslashit($root) . 'adobe/index.php',
            trailingslashit($root) . '.generated/index.php',
        ];
    }

    private function maintenanceError(string $fallbackMessage): WP_Error
    {
        $message = trim($this->storage->getLastFilesystemErrorMessage());

        return new WP_Error(
            'tasty_fonts_maintenance_failed',
            $message !== '' ? $message : $fallbackMessage
        );
    }
}
