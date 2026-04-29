<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\AssetService;
use TastyFonts\Maintenance\DeveloperToolsService;
use TastyFonts\Maintenance\SnapshotService;
use TastyFonts\Maintenance\SiteTransferService;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\Storage;
use WP_Error;

/**
 * @phpstan-type Payload array<string, mixed>
 */
final class MaintenanceActions
{
    public function __construct(
        private readonly Storage $storage,
        private readonly AssetService $assets,
        private readonly DeveloperToolsService $developerTools,
        private readonly SiteTransferService $siteTransfer,
        private readonly SnapshotService $snapshots,
        private readonly LogRepository $log
    ) {
    }

    /**
     * @return Payload|WP_Error
     */
    public function resetPluginSettingsToDefaults(): array|WP_Error
    {
        $snapshot = $this->snapshots->createSnapshot('before_reset_settings');

        if (is_wp_error($snapshot)) {
            return $snapshot;
        }

        $settings = $this->developerTools->resetPluginSettings();

        if (is_wp_error($settings)) {
            return $settings;
        }

        $message = __('Plugin settings reset to defaults. Font library preserved.', 'tasty-fonts');
        $this->log->add($message, $this->activityLogContext(
            LogRepository::CATEGORY_MAINTENANCE,
            'plugin_settings_reset',
            [
                'outcome' => 'success',
                'status_label' => __('Reset', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Preserved', 'tasty-fonts'), 'value' => __('Managed font library and files', 'tasty-fonts')],
                    ['label' => __('Reset', 'tasty-fonts'), 'value' => __('Settings, roles, access rules, and stored Google key data', 'tasty-fonts')],
                ],
            ]
        ));

        return [
            'message' => $message,
            'settings' => $settings,
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function wipeManagedFontLibrary(): array|WP_Error
    {
        $snapshot = $this->snapshots->createSnapshot('before_wipe_library');

        if (is_wp_error($snapshot)) {
            return $snapshot;
        }

        $settings = $this->developerTools->wipeManagedFontLibrary();

        if (is_wp_error($settings)) {
            return $settings;
        }

        $message = __('Managed font library wiped. Storage reset to an empty scaffold.', 'tasty-fonts');
        $this->log->add($message, $this->activityLogContext(
            LogRepository::CATEGORY_MAINTENANCE,
            'managed_font_library_wiped',
            [
                'outcome' => 'danger',
                'status_label' => __('Deleted', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Deleted', 'tasty-fonts'), 'value' => __('Managed font library and font storage files', 'tasty-fonts')],
                    ['label' => __('Recreated', 'tasty-fonts'), 'value' => __('Empty storage scaffold', 'tasty-fonts')],
                ],
            ]
        ));

        return [
            'message' => $message,
            'settings' => $settings,
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function deletePluginManagedFiles(): array|WP_Error
    {
        $settings = $this->developerTools->wipeManagedFontLibrary();

        if (is_wp_error($settings)) {
            return $settings;
        }

        $generatedCssPath = $this->storage->getGeneratedCssPath();

        if (is_string($generatedCssPath) && $generatedCssPath !== '' && file_exists($generatedCssPath) && !$this->storage->deleteAbsolutePath($generatedCssPath)) {
            return new WP_Error(
                'tasty_fonts_managed_files_delete_failed',
                __('Generated CSS could not be deleted after managed file cleanup.', 'tasty-fonts')
            );
        }

        $exportCleanup = $this->siteTransfer->deleteAllExportBundles();
        $snapshotCleanup = $this->snapshots->deleteAllSnapshots();

        $message = __('Plugin-managed files deleted. Storage reset to an empty scaffold.', 'tasty-fonts');
        $this->log->add($message, $this->activityLogContext(
            LogRepository::CATEGORY_MAINTENANCE,
            'plugin_managed_files_deleted',
            [
                'outcome' => 'danger',
                'status_label' => __('Deleted', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Transfer exports deleted', 'tasty-fonts'), 'value' => (string) $this->intValue($exportCleanup, 'deleted_export_bundles'), 'kind' => 'count'],
                    ['label' => __('Rollback snapshots deleted', 'tasty-fonts'), 'value' => (string) $this->intValue($snapshotCleanup, 'deleted_snapshots'), 'kind' => 'count'],
                    ['label' => __('Storage', 'tasty-fonts'), 'value' => __('Managed font files and generated CSS removed; scaffold recreated', 'tasty-fonts')],
                ],
            ]
        ));

        return [
            'message' => $message,
            'settings' => $settings,
            'deleted_export_bundles' => $this->intValue($exportCleanup, 'deleted_export_bundles'),
            'deleted_export_files' => $this->intValue($exportCleanup, 'deleted_export_files'),
            'deleted_snapshots' => $this->intValue($snapshotCleanup, 'deleted_snapshots'),
            'deleted_snapshot_files' => $this->intValue($snapshotCleanup, 'deleted_snapshot_files'),
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function clearPluginCachesAndRegenerateAssets(): array|WP_Error
    {
        if (!$this->developerTools->clearPluginCachesAndRegenerateAssets()) {
            return new WP_Error(
                'tasty_fonts_maintenance_failed',
                __('Plugin caches were cleared, but generated assets could not be rebuilt.', 'tasty-fonts')
            );
        }

        $message = __('Plugin caches cleared and generated assets refreshed.', 'tasty-fonts');
        $this->log->add($message, $this->activityLogContext(
            LogRepository::CATEGORY_MAINTENANCE,
            'plugin_caches_refreshed',
            [
                'outcome' => 'success',
                'status_label' => __('Refreshed', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Affected assets', 'tasty-fonts'), 'value' => __('Caches, generated CSS, and runtime assets', 'tasty-fonts')],
                ],
            ]
        ));

        return ['message' => $message];
    }

    /**
     * @return Payload|WP_Error
     */
    public function regenerateCss(): array|WP_Error
    {
        if (!$this->developerTools->regenerateCss()) {
            return new WP_Error(
                'tasty_fonts_regenerate_css_failed',
                __('Generated CSS could not be rebuilt.', 'tasty-fonts')
            );
        }

        $message = __('Generated CSS regenerated.', 'tasty-fonts');
        $this->log->add($message, $this->activityLogContext(
            LogRepository::CATEGORY_MAINTENANCE,
            'generated_css_regenerated',
            [
                'outcome' => 'success',
                'status_label' => __('Regenerated', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Affected asset', 'tasty-fonts'), 'value' => __('Generated CSS', 'tasty-fonts')],
                ],
            ]
        ));

        return ['message' => $message];
    }

    /**
     * @return Payload
     */
    public function rescanFontLibrary(): array
    {
        $this->assets->refreshGeneratedAssets(true, false);
        $message = __('Fonts rescanned.', 'tasty-fonts');
        $this->log->add($message, $this->activityLogContext(
            LogRepository::CATEGORY_MAINTENANCE,
            'font_library_rescanned',
            [
                'outcome' => 'success',
                'status_label' => __('Rescanned', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Affected area', 'tasty-fonts'), 'value' => __('Font library and generated assets', 'tasty-fonts')],
                ],
            ]
        ));

        return ['message' => $message];
    }

    /**
     * @return Payload|WP_Error
     */
    public function repairStorageScaffold(): array|WP_Error
    {
        if (!$this->developerTools->ensureStorageScaffolding()) {
            return new WP_Error(
                'tasty_fonts_storage_scaffold_repair_failed',
                __('Storage scaffold could not be repaired.', 'tasty-fonts')
            );
        }

        $message = __('Storage scaffold repaired.', 'tasty-fonts');
        $this->log->add($message, $this->activityLogContext(
            LogRepository::CATEGORY_MAINTENANCE,
            'storage_scaffold_repaired',
            [
                'outcome' => 'success',
                'status_label' => __('Repaired', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Affected area', 'tasty-fonts'), 'value' => __('Storage directories and index files', 'tasty-fonts')],
                ],
            ]
        ));

        return ['message' => $message];
    }

    /**
     * @return Payload
     */
    public function resetIntegrationDetectionState(): array
    {
        $settings = $this->developerTools->resetIntegrationDetectionState();
        $message = __('Integration detection state reset.', 'tasty-fonts');
        $this->log->add($message, $this->activityLogContext(
            LogRepository::CATEGORY_MAINTENANCE,
            'integration_detection_reset',
            [
                'outcome' => 'success',
                'status_label' => __('Reset', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
            ]
        ));

        return [
            'message' => $message,
            'settings' => $settings,
        ];
    }

    /**
     * @return Payload
     */
    public function resetSuppressedNotices(): array
    {
        $this->developerTools->resetSuppressedNotices();
        $message = __('Suppressed notices reset. Hidden reminders can appear again.', 'tasty-fonts');
        $this->log->add($message, $this->activityLogContext(
            LogRepository::CATEGORY_MAINTENANCE,
            'suppressed_notices_reset',
            [
                'outcome' => 'success',
                'status_label' => __('Reset', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
            ]
        ));

        return ['message' => $message];
    }

    /**
     * @return Payload
     */
    public function deleteAllHistory(): array
    {
        $deletedEntries = count($this->log->all());
        $this->log->clear();

        return [
            'message' => __('Activity history deleted.', 'tasty-fonts'),
            'deleted_history_entries' => $deletedEntries,
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function runAdvancedToolsAction(string $action): array|WP_Error
    {
        return match ($action) {
            'clear_plugin_caches' => $this->clearPluginCachesAndRegenerateAssets(),
            'regenerate_css' => $this->regenerateCss(),
            'rescan_font_library' => $this->rescanFontLibrary(),
            'repair_storage_scaffold' => $this->repairStorageScaffold(),
            'reset_integration_detection_state' => $this->resetIntegrationDetectionState(),
            'reset_suppressed_notices' => $this->resetSuppressedNotices(),
            default => new WP_Error(
                'tasty_fonts_invalid_tools_action',
                __('Unknown advanced tools action.', 'tasty-fonts')
            ),
        };
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function activityLogContext(string $category, string $event, array $meta = []): array
    {
        return array_merge(
            [
                'category' => $category,
                'event' => $event,
            ],
            $meta
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function intValue(array $values, int|string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $values) || !is_scalar($values[$key])) {
            return $default;
        }

        return (int) $values[$key];
    }
}
