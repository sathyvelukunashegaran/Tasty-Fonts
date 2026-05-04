<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

use TastyFonts\Fonts\AssetService;
use TastyFonts\Maintenance\DeveloperToolsService;
use TastyFonts\Maintenance\SnapshotService;
use TastyFonts\Maintenance\SiteTransferService;
use TastyFonts\Repository\ActivityLogRepositoryInterface;
use TastyFonts\Repository\ActivityLogVocabulary;
use TastyFonts\Support\Storage;
use WP_Error;

/**
 * @phpstan-type Payload array<array-key, mixed>
 */
final class MaintenanceActions
{
    public function __construct(
        private readonly Storage $storage,
        private readonly AssetService $assets,
        private readonly DeveloperToolsService $developerTools,
        private readonly SiteTransferService $siteTransfer,
        private readonly SnapshotService $snapshots,
        private readonly ActivityLogRepositoryInterface $log,
        private readonly AdminActionRunner $runner
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

        return $this->runner->run(
            fn(): array|WP_Error => $this->developerTools->resetPluginSettings(),
            [
                'category' => ActivityLogVocabulary::CATEGORY_MAINTENANCE,
                'event' => 'plugin_settings_reset',
                'status_label' => __('Reset', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'message' => __('Plugin settings reset to defaults. Font library preserved.', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Preserved', 'tasty-fonts'), 'value' => __('Managed font library and files', 'tasty-fonts')],
                    ['label' => __('Reset', 'tasty-fonts'), 'value' => __('Settings, roles, access rules, and stored Google key data', 'tasty-fonts')],
                ],
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function resetFamilyFallbacksToGlobal(): array|WP_Error
    {
        $snapshot = $this->snapshots->createSnapshot('before_reset_family_fallbacks');

        if (is_wp_error($snapshot)) {
            return $snapshot;
        }

        return $this->runner->run(
            fn(): array|WP_Error => $this->developerTools->resetFamilyFallbacksToGlobal(),
            [
                'category' => ActivityLogVocabulary::CATEGORY_MAINTENANCE,
                'event' => 'family_fallbacks_reset_to_global',
                'status_label' => __('Reset', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'message' => __('Family fallback overrides reset to the current global fallback settings.', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Reset', 'tasty-fonts'), 'value' => __('Per-family fallback overrides', 'tasty-fonts')],
                    ['label' => __('Preserved', 'tasty-fonts'), 'value' => __('Global Heading, Body, and Monospace fallback settings', 'tasty-fonts')],
                ],
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function resetFallbacksToPluginDefaults(): array|WP_Error
    {
        $snapshot = $this->snapshots->createSnapshot('before_reset_fallback_defaults');

        if (is_wp_error($snapshot)) {
            return $snapshot;
        }

        return $this->runner->run(
            fn(): array|WP_Error => $this->developerTools->resetAllFallbacksToDefaults(),
            [
                'category' => ActivityLogVocabulary::CATEGORY_MAINTENANCE,
                'event' => 'fallbacks_reset_to_plugin_defaults',
                'status_label' => __('Reset', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'message' => __('Fallback fonts reset to plugin defaults.', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Reset', 'tasty-fonts'), 'value' => __('Global fallback settings and per-family fallback overrides', 'tasty-fonts')],
                    ['label' => __('Default', 'tasty-fonts'), 'value' => __('Modern system UI fallback stacks', 'tasty-fonts')],
                ],
            ]
        );
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

        return $this->runner->run(
            fn(): array|WP_Error => $this->developerTools->wipeManagedFontLibrary(),
            [
                'category' => ActivityLogVocabulary::CATEGORY_MAINTENANCE,
                'event' => 'managed_font_library_wiped',
                'status_label' => __('Deleted', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'outcome' => 'danger',
                'message' => __('Managed font library wiped. Storage reset to an empty scaffold.', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Deleted', 'tasty-fonts'), 'value' => __('Managed font library and font storage files', 'tasty-fonts')],
                    ['label' => __('Recreated', 'tasty-fonts'), 'value' => __('Empty storage scaffold', 'tasty-fonts')],
                ],
            ]
        );
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

        return $this->runner->run(
            fn(): array => [
                'settings' => $settings,
                'deleted_export_bundles' => $this->intValue($exportCleanup, 'deleted_export_bundles'),
                'deleted_export_files' => $this->intValue($exportCleanup, 'deleted_export_files'),
                'deleted_snapshots' => $this->intValue($snapshotCleanup, 'deleted_snapshots'),
                'deleted_snapshot_files' => $this->intValue($snapshotCleanup, 'deleted_snapshot_files'),
            ],
            [
                'category' => ActivityLogVocabulary::CATEGORY_MAINTENANCE,
                'event' => 'plugin_managed_files_deleted',
                'status_label' => __('Deleted', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'outcome' => 'danger',
                'message' => __('Plugin-managed files deleted. Storage reset to an empty scaffold.', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Transfer exports deleted', 'tasty-fonts'), 'value' => (string) $this->intValue($exportCleanup, 'deleted_export_bundles'), 'kind' => 'count'],
                    ['label' => __('Rollback snapshots deleted', 'tasty-fonts'), 'value' => (string) $this->intValue($snapshotCleanup, 'deleted_snapshots'), 'kind' => 'count'],
                    ['label' => __('Storage', 'tasty-fonts'), 'value' => __('Managed font files and generated CSS removed; scaffold recreated', 'tasty-fonts')],
                ],
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function clearPluginCachesAndRegenerateAssets(): array|WP_Error
    {
        return $this->runner->run(
            function (): array|WP_Error {
                if (!$this->developerTools->clearPluginCachesAndRegenerateAssets()) {
                    return new WP_Error(
                        'tasty_fonts_maintenance_failed',
                        __('Plugin caches were cleared, but generated assets could not be rebuilt.', 'tasty-fonts')
                    );
                }

                return [];
            },
            [
                'category' => ActivityLogVocabulary::CATEGORY_MAINTENANCE,
                'event' => 'plugin_caches_refreshed',
                'status_label' => __('Refreshed', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'message' => __('Plugin caches cleared and generated assets refreshed.', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Affected assets', 'tasty-fonts'), 'value' => __('Caches, generated CSS, and runtime assets', 'tasty-fonts')],
                ],
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function regenerateCss(): array|WP_Error
    {
        return $this->runner->run(
            function (): array|WP_Error {
                if (!$this->developerTools->regenerateCss()) {
                    return new WP_Error(
                        'tasty_fonts_regenerate_css_failed',
                        __('Generated CSS could not be rebuilt.', 'tasty-fonts')
                    );
                }

                return [];
            },
            [
                'category' => ActivityLogVocabulary::CATEGORY_MAINTENANCE,
                'event' => 'generated_css_regenerated',
                'status_label' => __('Regenerated', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'message' => __('Generated CSS regenerated.', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Affected asset', 'tasty-fonts'), 'value' => __('Generated CSS', 'tasty-fonts')],
                ],
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function rescanFontLibrary(): array|WP_Error
    {
        return $this->runner->run(
            function (): array {
                $this->assets->refreshGeneratedAssets(true, false);

                return [];
            },
            [
                'category' => ActivityLogVocabulary::CATEGORY_MAINTENANCE,
                'event' => 'font_library_rescanned',
                'status_label' => __('Rescanned', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'message' => __('Fonts rescanned.', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Affected area', 'tasty-fonts'), 'value' => __('Font library and generated assets', 'tasty-fonts')],
                ],
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function repairStorageScaffold(): array|WP_Error
    {
        return $this->runner->run(
            function (): array|WP_Error {
                if (!$this->developerTools->ensureStorageScaffolding()) {
                    return new WP_Error(
                        'tasty_fonts_storage_scaffold_repair_failed',
                        __('Storage scaffold could not be repaired.', 'tasty-fonts')
                    );
                }

                return [];
            },
            [
                'category' => ActivityLogVocabulary::CATEGORY_MAINTENANCE,
                'event' => 'storage_scaffold_repaired',
                'status_label' => __('Repaired', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'message' => __('Storage scaffold repaired.', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Affected area', 'tasty-fonts'), 'value' => __('Storage directories and index files', 'tasty-fonts')],
                ],
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function resetIntegrationDetectionState(): array|WP_Error
    {
        return $this->runner->run(
            fn(): array => $this->developerTools->resetIntegrationDetectionState(),
            [
                'category' => ActivityLogVocabulary::CATEGORY_MAINTENANCE,
                'event' => 'integration_detection_reset',
                'status_label' => __('Reset', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'message' => __('Integration detection state reset.', 'tasty-fonts'),
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function resetSuppressedNotices(): array|WP_Error
    {
        return $this->runner->run(
            function (): array {
                $this->developerTools->resetSuppressedNotices();

                return [];
            },
            [
                'category' => ActivityLogVocabulary::CATEGORY_MAINTENANCE,
                'event' => 'suppressed_notices_reset',
                'status_label' => __('Reset', 'tasty-fonts'),
                'source' => __('Developer', 'tasty-fonts'),
                'message' => __('Suppressed notices reset. Hidden reminders can appear again.', 'tasty-fonts'),
            ]
        );
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
            'reset_family_fallbacks_to_global' => $this->resetFamilyFallbacksToGlobal(),
            'reset_fallbacks_to_plugin_defaults' => $this->resetFallbacksToPluginDefaults(),
            default => new WP_Error(
                'tasty_fonts_invalid_tools_action',
                __('Unknown advanced tools action.', 'tasty-fonts')
            ),
        };
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
