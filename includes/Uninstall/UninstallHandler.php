<?php

declare(strict_types=1);

namespace TastyFonts\Uninstall;

defined('ABSPATH') || exit;

use TastyFonts\Admin\AdminController;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\BlockEditorFontLibraryService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\Storage;

final class UninstallHandler
{
    /**
     * @param array<string, mixed> $settings Saved plugin settings captured before option deletion.
     */
    public function __construct(
        private readonly array $settings,
        private readonly Storage $storage,
        private readonly BlockEditorFontLibraryService $blockEditorFontLibrary
    ) {
    }

    public function run(): void
    {
        $this->deleteManagedBlockEditorFamilies();
        $this->deleteGeneratedCssArtifacts();
        $this->deleteManagedFilesIfRequested();
        $this->deleteOptions();
        $this->deleteTransients();
        $this->deleteWildcardTransients();
        $this->deleteAdobeProjectTransient();
    }

    private function deleteManagedBlockEditorFamilies(): void
    {
        if (empty($this->settings['block_editor_font_library_sync_enabled'])) {
            return;
        }

        $this->blockEditorFontLibrary->deleteAllSyncedFamilies();
    }

    private function deleteGeneratedCssArtifacts(): void
    {
        $generatedPath = $this->storage->getGeneratedCssPath();

        if (is_string($generatedPath) && $generatedPath !== '') {
            $this->storage->deleteAbsolutePath($generatedPath);
        }

        $generatedDirectory = $this->storage->pathForRelativePath('.generated');

        if (is_string($generatedDirectory) && $generatedDirectory !== '') {
            $this->storage->deleteAbsolutePath($generatedDirectory);
        }
    }

    private function deleteManagedFilesIfRequested(): void
    {
        if (empty($this->settings['delete_uploaded_files_on_uninstall'])) {
            return;
        }

        $root = $this->storage->getRoot();

        if (is_string($root) && $root !== '') {
            $this->storage->deleteAbsolutePath($root);
        }
    }

    private function deleteOptions(): void
    {
        foreach (
            [
                SettingsRepository::OPTION_SETTINGS,
                SettingsRepository::OPTION_ROLES,
                SettingsRepository::OPTION_GOOGLE_API_KEY_DATA,
                ImportRepository::OPTION_LIBRARY,
                ImportRepository::OPTION_IMPORTS,
                LogRepository::OPTION_LOG,
                AdminController::LOCAL_ENV_NOTICE_OPTION,
                SettingsRepository::LEGACY_OPTION_SETTINGS,
                SettingsRepository::LEGACY_OPTION_ROLES,
                ImportRepository::LEGACY_OPTION_IMPORTS,
                LogRepository::LEGACY_OPTION_LOG,
            ] as $optionKey
        ) {
            delete_option($optionKey);
        }
    }

    private function deleteTransients(): void
    {
        foreach (
            [
                CatalogService::TRANSIENT_CATALOG,
                AssetService::TRANSIENT_CSS,
                AssetService::TRANSIENT_HASH,
                AssetService::TRANSIENT_REGENERATE_CSS_QUEUED,
                GoogleFontsClient::TRANSIENT_CATALOG,
                BunnyFontsClient::TRANSIENT_CATALOG,
            ] as $transientKey
        ) {
            delete_transient($transientKey);
        }
    }

    private function deleteWildcardTransients(): void
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

        foreach (
            [
                BunnyFontsClient::TRANSIENT_FAMILY_PREFIX,
                AdminController::NOTICE_TRANSIENT_PREFIX,
            ] as $prefix
        ) {
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

    private function deleteAdobeProjectTransient(): void
    {
        $projectId = strtolower(trim((string) ($this->settings['adobe_project_id'] ?? '')));
        $projectId = preg_replace('/[^a-z0-9]+/', '', $projectId) ?? '';

        if ($projectId === '') {
            return;
        }

        delete_transient(AdobeProjectClient::TRANSIENT_PREFIX . md5($projectId));
    }
}
