<?php

declare(strict_types=1);

namespace TastyFonts\Uninstall;

defined('ABSPATH') || exit;

use TastyFonts\Admin\AdminController;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Fonts\BlockEditorFontLibraryService;
use TastyFonts\Maintenance\DeveloperToolsService;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\Storage;
use TastyFonts\Support\TransientKey;

final class UninstallHandler
{
    /**
     * @param array<string, mixed> $settings Saved plugin settings captured before option deletion.
     */
    public function __construct(
        private readonly array $settings,
        private readonly Storage $storage,
        private readonly BlockEditorFontLibraryService $blockEditorFontLibrary,
        private readonly DeveloperToolsService $developerTools
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

        $this->blockEditorFontLibrary->deleteAllSyncedFamilies(true);
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
        $this->developerTools->clearDeactivationCaches();
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
            $scopedPrefix = \TastyFonts\Support\FontUtils::scalarStringValue(TransientKey::prefixForSite($prefix));

            if ($scopedPrefix === '') {
                continue;
            }

            $transientPattern = \TastyFonts\Support\FontUtils::scalarStringValue($wpdb->esc_like('_transient_' . $scopedPrefix)) . '%';
            $timeoutPattern = \TastyFonts\Support\FontUtils::scalarStringValue($wpdb->esc_like('_transient_timeout_' . $scopedPrefix)) . '%';
            $optionsTable = \TastyFonts\Support\FontUtils::scalarStringValue($wpdb->options);

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

    private function deleteAdobeProjectTransient(): void
    {
        $projectId = $this->settings['adobe_project_id'] ?? '';
        $projectId = is_scalar($projectId) ? strtolower(trim((string) $projectId)) : '';
        $projectId = preg_replace('/[^a-z0-9]+/', '', $projectId) ?? '';

        if ($projectId === '') {
            return;
        }

        delete_transient(TransientKey::forSite(AdobeProjectClient::TRANSIENT_PREFIX . md5($projectId)));
    }
}
