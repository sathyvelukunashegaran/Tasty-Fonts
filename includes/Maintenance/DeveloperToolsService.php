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
use TastyFonts\Repository\FamilyMetadataRepository;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use TastyFonts\Support\TransientKey;
use WP_Error;

/**
 * @phpstan-import-type NormalizedSettings from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type LibraryMap from \TastyFonts\Repository\ImportRepository
 * @phpstan-type ScaffoldFileMap array<string, string>
 * @phpstan-type TransientPrefixList list<string>
 */
final class DeveloperToolsService
{
    private const STORAGE_INDEX_STUB = '<?php // Silence is golden.';
    private const STORAGE_HTACCESS_STUB = <<<'HTACCESS'
Options -Indexes
<FilesMatch "(?i)\.(php|phtml|phar|php\d*)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>
HTACCESS;
    private const UPDATER_RELEASE_TRANSIENT = 'tasty_fonts_github_release_manifest_v1';
    private const UPDATER_INSTALLED_VERSION_TRANSIENT = 'tasty_fonts_github_release_version_v1';

    public function __construct(
        private readonly Storage $storage,
        private readonly SettingsRepository $settings,
        private readonly ImportRepository $imports,
        CatalogService $catalog,
        private readonly AssetService $assets,
        private readonly BlockEditorFontLibraryService $blockEditorFontLibrary,
        private readonly GoogleFontsClient $googleClient,
        FamilyMetadataRepository $familyMetadataRepo,
    ) {
        unset($catalog, $familyMetadataRepo);
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

        foreach ($this->scaffoldStorageFiles($root) as $path => $contents) {
            if (file_exists($path)) {
                continue;
            }

            if (!$this->storage->writeAbsoluteFile($path, $contents)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return NormalizedSettings|WP_Error
     */
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

    /**
     * @return NormalizedSettings|WP_Error
     */
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

    /**
     * @return NormalizedSettings|WP_Error
     */
    public function resetFamilyFallbacksToGlobal(): array|WP_Error
    {
        $previousSettings = $this->settings->getSettings();

        do_action('tasty_fonts_before_reset_family_fallbacks_to_global', $previousSettings);

        $settings = $this->settings->resetFamilyFallbacks();

        if (!$this->clearPluginCachesAndRegenerateAssetsInternal($previousSettings)) {
            return $this->maintenanceError(__('Family fallback overrides were reset, but generated assets could not be rebuilt.', 'tasty-fonts'));
        }

        do_action('tasty_fonts_after_reset_family_fallbacks_to_global', $settings, $previousSettings);

        return $this->settings->getSettings();
    }

    /**
     * @return NormalizedSettings|WP_Error
     */
    public function resetAllFallbacksToDefaults(): array|WP_Error
    {
        $previousSettings = $this->settings->getSettings();

        do_action('tasty_fonts_before_reset_all_fallbacks_to_defaults', $previousSettings);

        $settings = $this->settings->resetAllFallbacksToDefaults();

        if (!$this->clearPluginCachesAndRegenerateAssetsInternal($previousSettings)) {
            return $this->maintenanceError(__('Fallback defaults were restored, but generated assets could not be rebuilt.', 'tasty-fonts'));
        }

        do_action('tasty_fonts_after_reset_all_fallbacks_to_defaults', $settings, $previousSettings);

        return $this->settings->getSettings();
    }

    public function clearPluginCachesAndRegenerateAssets(): bool
    {
        $settings = $this->settings->getSettings();

        do_action('tasty_fonts_before_clear_plugin_caches', $settings);

        $success = $this->clearPluginCachesAndRegenerateAssetsInternal($settings);

        do_action('tasty_fonts_after_clear_plugin_caches', $this->settings->getSettings(), $success);

        return $success;
    }

    public function regenerateCss(): bool
    {
        $settings = $this->settings->getSettings();

        do_action('tasty_fonts_before_regenerate_css', $settings);

        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(AssetService::ACTION_REGENERATE_CSS);
            wp_clear_scheduled_hook(GoogleFontsClient::ACTION_REVALIDATE_API_KEY);
        }

        $this->assets->refreshGeneratedAssets(true, false);
        $success = $this->assets->ensureGeneratedCssFile(false);

        do_action('tasty_fonts_after_regenerate_css', $this->settings->getSettings(), $success);

        return $success;
    }

    /**
     * @return NormalizedSettings
     */
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
            wp_clear_scheduled_hook(GoogleFontsClient::ACTION_REVALIDATE_API_KEY);
        }
    }

    /**
     * @param NormalizedSettings $settingsContext
     */
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
            wp_clear_scheduled_hook(GoogleFontsClient::ACTION_REVALIDATE_API_KEY);
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
                GoogleFontsClient::TRANSIENT_API_KEY_REVALIDATION_QUEUED,
                BunnyFontsClient::TRANSIENT_CATALOG,
                self::UPDATER_RELEASE_TRANSIENT,
                self::UPDATER_INSTALLED_VERSION_TRANSIENT,
            ] as $transientKey
        ) {
            delete_transient(TransientKey::forSite($transientKey));
        }
    }

    /**
     * @param TransientPrefixList $prefixes
     */
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
            if ($prefix === '') {
                continue;
            }

            $scopedPrefix = FontUtils::scalarStringValue(TransientKey::prefixForSite($prefix));

            if ($scopedPrefix === '') {
                continue;
            }

            $transientPattern = FontUtils::scalarStringValue($wpdb->esc_like('_transient_' . $scopedPrefix)) . '%';
            $timeoutPattern = FontUtils::scalarStringValue($wpdb->esc_like('_transient_timeout_' . $scopedPrefix)) . '%';
            $optionsTable = FontUtils::scalarStringValue($wpdb->options);

            if ($optionsTable === '') {
                continue;
            }

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$optionsTable} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $transientPattern,
                    $timeoutPattern
                )
            );
        }
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function deleteAdobeProjectTransient(array $settings): void
    {
        $projectId = $settings['adobe_project_id'] ?? '';
        $projectId = is_scalar($projectId) ? strtolower(trim((string) $projectId)) : '';
        $projectId = preg_replace('/[^a-z0-9]+/', '', $projectId) ?? '';

        if ($projectId === '') {
            return;
        }

        delete_transient(TransientKey::forSite(AdobeProjectClient::TRANSIENT_PREFIX . md5($projectId)));
    }

    /**
     * @return ScaffoldFileMap
     */
    private function scaffoldStorageFiles(string $root): array
    {
        $directories = [
            trailingslashit($root),
            trailingslashit($root) . 'google/',
            trailingslashit($root) . 'bunny/',
            trailingslashit($root) . 'upload/',
            trailingslashit($root) . 'adobe/',
            trailingslashit($root) . '.generated/',
        ];

        $files = [];

        foreach ($directories as $directory) {
            $files[$directory . 'index.php'] = self::STORAGE_INDEX_STUB;
            $files[$directory . '.htaccess'] = self::STORAGE_HTACCESS_STUB;
        }

        return $files;
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
