<?php

declare(strict_types=1);

namespace TastyFonts\Maintenance;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\BlockEditorFontLibraryService;
use TastyFonts\Fonts\LibraryService;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;
use ZipArchive;

/**
 * @phpstan-import-type LibraryMap from ImportRepository
 * @phpstan-import-type NormalizedSettings from SettingsRepository
 * @phpstan-import-type RoleSet from SettingsRepository
 * @phpstan-type SnapshotFileEntry array{relative_path: string, size: int, sha256: string}
 * @phpstan-type SnapshotManifest array{
 *     id: string,
 *     schema_version: int,
 *     plugin_version: string,
 *     created_at: string,
 *     reason: string,
 *     settings: NormalizedSettings,
 *     roles: RoleSet,
 *     applied_roles: RoleSet,
 *     library: LibraryMap,
 *     files: list<SnapshotFileEntry>
 * }
 * @phpstan-type SnapshotSummary array{id: string, created_at: string, reason: string, plugin_version: string, families: int, files: int, size: int, label: string, family_names: list<string>, role_families: list<string>}
 * @phpstan-type SnapshotCreateResult array{snapshot: SnapshotSummary, message: string}
 * @phpstan-type SnapshotRestorePreview array{id: string, created_at: string, reason: string, plugin_version: string, families: int, files: int, settings_changed: int, families_added: int, families_removed: int, families_changed: int}
 */
final class SnapshotService
{
    public const SCHEMA_VERSION = 1;
    public const OPTION_SNAPSHOTS = 'tasty_fonts_snapshots';
    public const MIN_SNAPSHOT_RETENTION_LIMIT = 1;
    public const MAX_SNAPSHOT_RETENTION_LIMIT = 10;
    public const DEFAULT_SNAPSHOT_RETENTION_LIMIT = 5;
    private const MANIFEST_FILENAME = 'tasty-fonts-snapshot.json';
    private const ARCHIVE_FONTS_DIRECTORY = 'fonts/';
    private const SNAPSHOT_DIRECTORY = 'tasty-fonts-snapshots';
    private const GENERATED_CSS_RELATIVE_PATH = '.generated/tasty-fonts.css';

    public function __construct(
        private readonly Storage $storage,
        private readonly SettingsRepository $settings,
        private readonly ImportRepository $imports,
        private readonly DeveloperToolsService $developerTools,
        private readonly LibraryService $library,
        private readonly BlockEditorFontLibraryService $blockEditorFontLibrary
    ) {
    }

    /**
     * @return list<SnapshotSummary>
     */
    public function listSnapshots(): array
    {
        $snapshots = $this->storedSnapshots();

        return array_values(
            array_map(
                fn (array $snapshot): array => $this->hydrateSnapshotSummary($snapshot),
                array_filter(
                    $snapshots,
                    fn (array $snapshot): bool => $this->snapshotPath($snapshot['id']) !== ''
                        && is_readable($this->snapshotPath($snapshot['id']))
                )
            )
        );
    }

    public function retentionLimit(): int
    {
        return self::normalizeRetentionLimit($this->settings->getSettings()['snapshot_retention_limit'] ?? self::DEFAULT_SNAPSHOT_RETENTION_LIMIT);
    }

    public static function normalizeRetentionLimit(mixed $limit): int
    {
        $normalized = is_scalar($limit) || $limit === null ? absint($limit) : 0;

        return max(
            self::MIN_SNAPSHOT_RETENTION_LIMIT,
            min(self::MAX_SNAPSHOT_RETENTION_LIMIT, $normalized ?: self::DEFAULT_SNAPSHOT_RETENTION_LIMIT)
        );
    }

    public function pruneSnapshotsToRetentionLimit(): void
    {
        $this->rememberSnapshots($this->storedSnapshots());
    }

    /**
     * @return SnapshotCreateResult|WP_Error
     */
    public function renameSnapshot(string $snapshotId, string $label): array|WP_Error
    {
        $snapshotId = sanitize_key($snapshotId);
        $label = $this->normalizeSnapshotLabel($label);
        $path = $this->snapshotPath($snapshotId);

        if ($snapshotId === '' || $path === '' || !is_readable($path)) {
            return $this->error(
                'tasty_fonts_snapshot_missing',
                __('The selected rollback snapshot is no longer available.', 'tasty-fonts')
            );
        }

        $snapshots = $this->storedSnapshots();
        $updatedSnapshot = null;

        foreach ($snapshots as &$snapshot) {
            if ($snapshot['id'] !== $snapshotId) {
                continue;
            }

            $snapshot['label'] = $label;
            $updatedSnapshot = $snapshot;
            break;
        }
        unset($snapshot);

        if ($updatedSnapshot === null) {
            return $this->error(
                'tasty_fonts_snapshot_missing',
                __('The selected rollback snapshot is no longer available.', 'tasty-fonts')
            );
        }

        update_option(self::OPTION_SNAPSHOTS, $snapshots, false);

        return [
            'snapshot' => $this->hydrateSnapshotSummary($updatedSnapshot),
            'message' => $label === ''
                ? __('Rollback snapshot name cleared.', 'tasty-fonts')
                : __('Rollback snapshot renamed.', 'tasty-fonts'),
        ];
    }

    /**
     * @return SnapshotCreateResult|WP_Error
     */
    public function deleteSnapshot(string $snapshotId): array|WP_Error
    {
        $snapshotId = sanitize_key($snapshotId);
        $path = $this->snapshotPath($snapshotId);

        if ($snapshotId === '' || $path === '' || !is_readable($path)) {
            return $this->error(
                'tasty_fonts_snapshot_missing',
                __('The selected rollback snapshot is no longer available.', 'tasty-fonts')
            );
        }

        $snapshots = $this->storedSnapshots();
        $summary = null;

        foreach ($snapshots as $snapshot) {
            if ($snapshot['id'] === $snapshotId) {
                $summary = $this->hydrateSnapshotSummary($snapshot);
                break;
            }
        }

        if ($summary === null) {
            return $this->error(
                'tasty_fonts_snapshot_missing',
                __('The selected rollback snapshot is no longer available.', 'tasty-fonts')
            );
        }

        if (is_file($path) && !@unlink($path)) {
            return $this->error(
                'tasty_fonts_snapshot_delete_failed',
                __('The selected rollback snapshot could not be deleted.', 'tasty-fonts')
            );
        }

        update_option(
            self::OPTION_SNAPSHOTS,
            array_values(
                array_filter(
                    $snapshots,
                    fn (array $snapshot): bool => $snapshot['id'] !== $snapshotId
                )
            ),
            false
        );

        return [
            'snapshot' => $summary,
            'message' => __('Rollback snapshot deleted.', 'tasty-fonts'),
        ];
    }

    /**
     * @return array{message: string, deleted_snapshots: int, deleted_snapshot_files: int}
     */
    public function deleteAllSnapshots(): array
    {
        $snapshots = $this->storedSnapshots();
        $deletedFiles = 0;

        foreach ($snapshots as $snapshot) {
            $path = $this->snapshotPath($snapshot['id']);

            if ($path !== '' && file_exists($path)) {
                $deletedFiles++;
            }
        }

        $this->deleteDirectory($this->snapshotDirectory());
        delete_option(self::OPTION_SNAPSHOTS);

        return [
            'message' => __('Rollback snapshots deleted.', 'tasty-fonts'),
            'deleted_snapshots' => count($snapshots),
            'deleted_snapshot_files' => $deletedFiles,
        ];
    }

    /**
     * @param SnapshotSummary $snapshot
     * @return SnapshotSummary
     */
    private function hydrateSnapshotSummary(array $snapshot): array
    {
        if ($snapshot['id'] === '') {
            return $snapshot;
        }

        $opened = $this->openSnapshot($snapshot['id']);

        if (is_wp_error($opened)) {
            return $snapshot;
        }

        $path = $opened['path'];
        $summary = $this->summaryFromManifest($opened['manifest'], is_file($path) ? (int) filesize($path) : $snapshot['size']);
        $summary['label'] = $snapshot['label'];

        return $summary;
    }

    private function normalizeSnapshotLabel(string $label): string
    {
        $label = trim(sanitize_text_field($label));

        if ($label === '') {
            return '';
        }

        return substr($label, 0, 80);
    }

    /**
     * @return SnapshotCreateResult|WP_Error
     */
    public function createSnapshot(string $reason = 'manual'): array|WP_Error
    {
        if (!class_exists(ZipArchive::class)) {
            return $this->error(
                'tasty_fonts_snapshot_zip_unavailable',
                __('ZipArchive is unavailable on this server, so rollback snapshots cannot be created.', 'tasty-fonts')
            );
        }

        if (!$this->ensureSnapshotDirectory()) {
            return $this->error(
                'tasty_fonts_snapshot_directory_unavailable',
                __('The rollback snapshot directory could not be prepared.', 'tasty-fonts')
            );
        }

        $manifest = $this->buildManifest($reason);
        $path = $this->snapshotPath($manifest['id']);
        $zip = new ZipArchive();

        if ($path === '' || $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->error(
                'tasty_fonts_snapshot_zip_create_failed',
                __('The rollback snapshot archive could not be created.', 'tasty-fonts')
            );
        }

        $manifestJson = wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($manifestJson) || !$zip->addFromString(self::MANIFEST_FILENAME, $manifestJson)) {
            $zip->close();
            @unlink($path);

            return $this->error(
                'tasty_fonts_snapshot_manifest_write_failed',
                __('The rollback snapshot manifest could not be written.', 'tasty-fonts')
            );
        }

        foreach ($manifest['files'] as $file) {
            $absolutePath = $this->storage->pathForRelativePath($file['relative_path']);

            if (!is_string($absolutePath) || !is_readable($absolutePath) || !$zip->addFile($absolutePath, self::ARCHIVE_FONTS_DIRECTORY . $file['relative_path'])) {
                $zip->close();
                @unlink($path);

                return $this->error(
                    'tasty_fonts_snapshot_file_write_failed',
                    __('A managed font file could not be added to the rollback snapshot.', 'tasty-fonts')
                );
            }
        }

        $zip->close();

        $summary = $this->summaryFromManifest($manifest, is_file($path) ? (int) filesize($path) : 0);
        $this->rememberSnapshot($summary);

        return [
            'snapshot' => $summary,
            'message' => __('Rollback snapshot created.', 'tasty-fonts'),
        ];
    }

    /**
     * @return SnapshotRestorePreview|WP_Error
     */
    public function previewRestore(string $snapshotId): array|WP_Error
    {
        $opened = $this->openSnapshot($snapshotId);

        if (is_wp_error($opened)) {
            return $opened;
        }

        $manifest = $opened['manifest'];
        $currentSettings = $this->settings->getSettings();
        $currentLibrary = $this->imports->allFamilies();
        $incomingLibrary = $manifest['library'];

        return [
            'id' => $manifest['id'],
            'created_at' => $manifest['created_at'],
            'reason' => $manifest['reason'],
            'plugin_version' => $manifest['plugin_version'],
            'families' => count($incomingLibrary),
            'files' => count($manifest['files']),
            'settings_changed' => $this->countChangedSettings($currentSettings, $manifest['settings']),
            'families_added' => count(array_diff_key($incomingLibrary, $currentLibrary)),
            'families_removed' => count(array_diff_key($currentLibrary, $incomingLibrary)),
            'families_changed' => $this->countChangedFamilies($currentLibrary, $incomingLibrary),
        ];
    }

    /**
     * @return SnapshotCreateResult|WP_Error
     */
    public function restoreSnapshot(string $snapshotId): array|WP_Error
    {
        $opened = $this->openSnapshot($snapshotId);

        if (is_wp_error($opened)) {
            return $opened;
        }

        $manifest = $opened['manifest'];
        $zipPath = $opened['path'];
        $extractDirectory = $this->createTemporaryDirectory();

        if (is_wp_error($extractDirectory)) {
            return $extractDirectory;
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true || !$zip->extractTo($extractDirectory)) {
            $zip->close();
            $this->deleteDirectory($extractDirectory);

            return $this->error(
                'tasty_fonts_snapshot_extract_failed',
                __('The rollback snapshot could not be extracted.', 'tasty-fonts')
            );
        }

        $zip->close();

        try {
            $root = $this->storage->getRoot();

            $this->blockEditorFontLibrary->deleteAllSyncedFamilies(true);

            if (is_string($root) && $root !== '' && file_exists($root) && !$this->storage->deleteAbsolutePath($root)) {
                return $this->error(
                    'tasty_fonts_snapshot_existing_storage_delete_failed',
                    __('The current managed fonts directory could not be removed before rollback.', 'tasty-fonts')
                );
            }

            $this->imports->clearLibrary();
            $this->settings->resetStoredSettingsToDefaults();

            if (!$this->developerTools->ensureStorageScaffolding()) {
                return $this->error(
                    'tasty_fonts_snapshot_storage_scaffold_failed',
                    __('The managed fonts directory could not be recreated for rollback.', 'tasty-fonts')
                );
            }

            if (!$this->restoreSnapshotFiles($extractDirectory, $manifest['files'])) {
                return $this->error(
                    'tasty_fonts_snapshot_restore_files_failed',
                    __('The managed font files could not be restored from the rollback snapshot.', 'tasty-fonts')
                );
            }

            $settings = $manifest['settings'];
            $settings['applied_roles'] = $manifest['applied_roles'];
            $savedSettings = $this->settings->replaceImportedSettings($settings);
            $savedRoles = $this->settings->replaceImportedRoles($manifest['roles']);
            $savedLibrary = $this->imports->replaceLibrary($manifest['library']);

            $this->library->syncLiveRolePublishStates(
                $this->normalizeAppliedRoles($savedSettings['applied_roles'] ?? []),
                !empty($savedSettings['auto_apply_roles'])
            );

            if (!$this->developerTools->clearPluginCachesAndRegenerateAssets()) {
                return $this->error(
                    'tasty_fonts_snapshot_assets_rebuild_failed',
                    __('The rollback restored state, but generated assets could not be rebuilt.', 'tasty-fonts')
                );
            }

            $summary = $this->summaryFromManifest($manifest, is_file($zipPath) ? (int) filesize($zipPath) : 0);

            return [
                'snapshot' => $summary,
                'message' => sprintf(
                    /* translators: %s: rollback snapshot creation time */
                    __('Rollback restored from the snapshot created at %s.', 'tasty-fonts'),
                    $manifest['created_at']
                ),
                'settings' => $savedSettings,
                'roles' => $savedRoles,
                'library' => $savedLibrary,
            ];
        } finally {
            $this->deleteDirectory($extractDirectory);
        }
    }

    /**
     * @return SnapshotManifest
     */
    private function buildManifest(string $reason): array
    {
        return [
            'id' => $this->newSnapshotId(),
            'schema_version' => self::SCHEMA_VERSION,
            'plugin_version' => defined('TASTY_FONTS_VERSION') ? (string) TASTY_FONTS_VERSION : '',
            'created_at' => current_time('mysql', true),
            'reason' => sanitize_key($reason) !== '' ? sanitize_key($reason) : 'manual',
            'settings' => $this->sanitizedSettings(),
            'roles' => $this->settings->getRoles([]),
            'applied_roles' => $this->settings->getAppliedRoles([]),
            'library' => $this->imports->allFamilies(),
            'files' => $this->managedFileManifest(),
        ];
    }

    /**
     * @return NormalizedSettings
     */
    private function sanitizedSettings(): array
    {
        $settings = $this->settings->getSettings();

        unset(
            $settings['google_api_key'],
            $settings['google_api_key_status'],
            $settings['google_api_key_status_message'],
            $settings['google_api_key_checked_at'],
            $settings['applied_roles']
        );

        return $settings;
    }

    /**
     * @return list<SnapshotFileEntry>
     */
    private function managedFileManifest(): array
    {
        return $this->storage->listFileMetadata(
            static fn (string $relativePath): bool => $relativePath !== self::GENERATED_CSS_RELATIVE_PATH
                && !str_starts_with($relativePath, '.snapshots/'),
            true
        );
    }

    /**
     * @param SnapshotManifest $manifest
     * @return SnapshotSummary
     */
    private function summaryFromManifest(array $manifest, int $size): array
    {
        $roleFamilies = $this->snapshotRoleFamilies($manifest['applied_roles']);

        if ($roleFamilies === []) {
            $roleFamilies = $this->snapshotRoleFamilies($manifest['roles']);
        }

        return [
            'id' => $manifest['id'],
            'created_at' => $manifest['created_at'],
            'reason' => $manifest['reason'],
            'plugin_version' => $manifest['plugin_version'],
            'families' => count($manifest['library']),
            'files' => count($manifest['files']),
            'size' => max(0, $size),
            'label' => '',
            'family_names' => $this->snapshotFamilyNames($manifest['library']),
            'role_families' => $roleFamilies,
        ];
    }

    /**
     * @param LibraryMap $library
     * @return list<string>
     */
    private function snapshotFamilyNames(array $library): array
    {
        $names = [];

        foreach ($library as $family) {
            $map = FontUtils::normalizeStringKeyedMap($family);
            $name = trim(FontUtils::scalarStringValue($map['family'] ?? $map['name'] ?? $map['label'] ?? ''));

            if ($name !== '') {
                $names[$name] = $name;
            }
        }

        natcasesort($names);

        return array_values(array_slice($names, 0, 8, true));
    }

    /**
     * @param RoleSet $roles
     * @return list<string>
     */
    private function snapshotRoleFamilies(array $roles): array
    {
        $families = [];

        foreach (['heading', 'body', 'monospace'] as $role) {
            $family = trim(FontUtils::scalarStringValue($roles[$role]));

            if ($family !== '') {
                $families[$family] = $family;
            }
        }

        return array_values($families);
    }

    /**
     * @return array{manifest: SnapshotManifest, path: string}|WP_Error
     */
    private function openSnapshot(string $snapshotId): array|WP_Error
    {
        $snapshotId = sanitize_key($snapshotId);
        $path = $this->snapshotPath($snapshotId);

        if ($snapshotId === '' || $path === '' || !is_readable($path)) {
            return $this->error(
                'tasty_fonts_snapshot_missing',
                __('The selected rollback snapshot is no longer available.', 'tasty-fonts')
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return $this->error(
                'tasty_fonts_snapshot_invalid',
                __('The selected rollback snapshot is not a readable ZIP archive.', 'tasty-fonts')
            );
        }

        $manifestJson = $zip->getFromName(self::MANIFEST_FILENAME);
        $zip->close();

        if (!is_string($manifestJson) || trim($manifestJson) === '') {
            return $this->error(
                'tasty_fonts_snapshot_manifest_missing',
                __('The selected rollback snapshot is missing its manifest.', 'tasty-fonts')
            );
        }

        $decoded = json_decode($manifestJson, true);

        if (!is_array($decoded)) {
            return $this->error(
                'tasty_fonts_snapshot_manifest_invalid',
                __('The selected rollback snapshot manifest is not valid JSON.', 'tasty-fonts')
            );
        }

        $manifest = $this->normalizeManifest(FontUtils::normalizeStringKeyedMap($decoded), $snapshotId);

        if (is_wp_error($manifest)) {
            return $manifest;
        }

        return [
            'manifest' => $manifest,
            'path' => $path,
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @return SnapshotManifest|WP_Error
     */
    private function normalizeManifest(array $manifest, string $expectedId): array|WP_Error
    {
        if (FontUtils::scalarIntValue($manifest['schema_version'] ?? null) !== self::SCHEMA_VERSION || sanitize_key(FontUtils::scalarStringValue($manifest['id'] ?? '')) !== $expectedId) {
            return $this->error(
                'tasty_fonts_snapshot_manifest_unsupported',
                __('The selected rollback snapshot uses an unsupported manifest.', 'tasty-fonts')
            );
        }

        $files = [];

        foreach (is_array($manifest['files'] ?? null) ? $manifest['files'] : [] as $file) {
            if (!is_array($file)) {
                continue;
            }

            $file = FontUtils::normalizeStringKeyedMap($file);
            $relativePath = trim(FontUtils::scalarStringValue($file['relative_path'] ?? ''));

            if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
                continue;
            }

            $files[] = [
                'relative_path' => $relativePath,
                'size' => max(0, FontUtils::scalarIntValue($file['size'] ?? 0)),
                'sha256' => trim(FontUtils::scalarStringValue($file['sha256'] ?? '')),
            ];
        }

        return [
            'id' => $expectedId,
            'schema_version' => self::SCHEMA_VERSION,
            'plugin_version' => FontUtils::scalarStringValue($manifest['plugin_version'] ?? ''),
            'created_at' => FontUtils::scalarStringValue($manifest['created_at'] ?? ''),
            'reason' => sanitize_key(FontUtils::scalarStringValue($manifest['reason'] ?? 'manual')),
            'settings' => FontUtils::normalizeStringKeyedMap($manifest['settings'] ?? []),
            'roles' => $this->settings->previewImportedRoles(FontUtils::normalizeStringKeyedMap($manifest['roles'] ?? [])),
            'applied_roles' => $this->settings->previewImportedRoles(FontUtils::normalizeStringKeyedMap($manifest['applied_roles'] ?? [])),
            'library' => $this->imports->replaceLibraryPreview(FontUtils::normalizeStringKeyedMap($manifest['library'] ?? [])),
            'files' => $files,
        ];
    }

    /**
     * @param list<SnapshotFileEntry> $files
     */
    private function restoreSnapshotFiles(string $extractDirectory, array $files): bool
    {
        foreach ($files as $file) {
            $source = wp_normalize_path($extractDirectory . DIRECTORY_SEPARATOR . self::ARCHIVE_FONTS_DIRECTORY . $file['relative_path']);
            $target = $this->storage->pathForRelativePath($file['relative_path']);

            if (!is_string($target) || !is_readable($source)) {
                return false;
            }

            if (!$this->storage->copyAbsoluteFile($source, $target)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param NormalizedSettings $current
     * @param NormalizedSettings $incoming
     */
    private function countChangedSettings(array $current, array $incoming): int
    {
        $changed = 0;

        foreach ($incoming as $key => $value) {
            if (($current[$key] ?? null) !== $value) {
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * @param LibraryMap $current
     * @param LibraryMap $incoming
     */
    private function countChangedFamilies(array $current, array $incoming): int
    {
        $changed = 0;

        foreach ($incoming as $slug => $family) {
            if (isset($current[$slug]) && $current[$slug] !== $family) {
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * @return array{heading?: string, body?: string, monospace?: string}
     */
    private function normalizeAppliedRoles(mixed $value): array
    {
        $roles = $this->settings->previewImportedRoles(is_array($value) ? FontUtils::normalizeStringKeyedMap($value) : []);
        $applied = [];

        foreach (['heading', 'body', 'monospace'] as $roleKey) {
            if ($roles[$roleKey] !== '') {
                $applied[$roleKey] = $roles[$roleKey];
            }
        }

        return $applied;
    }

    /**
     * @param SnapshotSummary $summary
     */
    private function rememberSnapshot(array $summary): void
    {
        $this->rememberSnapshots(array_merge([$summary], $this->storedSnapshots()));
    }

    /**
     * @param list<SnapshotSummary> $snapshots
     */
    private function rememberSnapshots(array $snapshots): void
    {
        $snapshots = array_values(
            array_filter(
                $snapshots,
                fn (array $snapshot): bool => $snapshot['id'] !== '' && $this->snapshotPath($snapshot['id']) !== ''
            )
        );
        $unique = [];

        foreach ($snapshots as $snapshot) {
            $unique[$snapshot['id']] = $snapshot;
        }

        $ordered = array_values($unique);
        usort($ordered, static fn (array $left, array $right): int => strcmp($right['created_at'], $left['created_at']));
        $kept = array_slice($ordered, 0, $this->retentionLimit());
        $keptIds = array_column($kept, 'id');

        foreach ($ordered as $snapshot) {
            if (!in_array($snapshot['id'], $keptIds, true)) {
                $path = $this->snapshotPath($snapshot['id']);

                if ($path !== '') {
                    @unlink($path);
                }
            }
        }

        update_option(self::OPTION_SNAPSHOTS, $kept, false);
    }

    /**
     * @return list<SnapshotSummary>
     */
    private function storedSnapshots(): array
    {
        $stored = get_option(self::OPTION_SNAPSHOTS, []);

        if (!is_array($stored)) {
            return [];
        }

        $snapshots = [];

        foreach ($stored as $snapshot) {
            if (!is_array($snapshot)) {
                continue;
            }

            $map = FontUtils::normalizeStringKeyedMap($snapshot);
            $id = sanitize_key(FontUtils::scalarStringValue($map['id'] ?? ''));

            if ($id === '') {
                continue;
            }

            $snapshots[] = [
                'id' => $id,
                'created_at' => FontUtils::scalarStringValue($map['created_at'] ?? ''),
                'reason' => sanitize_key(FontUtils::scalarStringValue($map['reason'] ?? 'manual')),
                'plugin_version' => FontUtils::scalarStringValue($map['plugin_version'] ?? ''),
                'families' => max(0, FontUtils::scalarIntValue($map['families'] ?? 0)),
                'files' => max(0, FontUtils::scalarIntValue($map['files'] ?? 0)),
                'size' => max(0, FontUtils::scalarIntValue($map['size'] ?? 0)),
                'label' => $this->normalizeSnapshotLabel(FontUtils::scalarStringValue($map['label'] ?? '')),
                'family_names' => array_values(
                    array_filter(
                        array_map(
                            static fn (mixed $family): string => trim(FontUtils::scalarStringValue($family)),
                            is_array($map['family_names'] ?? null) ? $map['family_names'] : []
                        ),
                        static fn (string $family): bool => $family !== ''
                    )
                ),
                'role_families' => array_values(
                    array_filter(
                        array_map(
                            static fn (mixed $family): string => trim(FontUtils::scalarStringValue($family)),
                            is_array($map['role_families'] ?? null) ? $map['role_families'] : []
                        ),
                        static fn (string $family): bool => $family !== ''
                    )
                ),
            ];
        }

        return $snapshots;
    }

    private function newSnapshotId(): string
    {
        return sanitize_key('snapshot-' . gmdate('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 8));
    }

    private function snapshotPath(string $snapshotId): string
    {
        $snapshotId = sanitize_key($snapshotId);

        if ($snapshotId === '') {
            return '';
        }

        return wp_normalize_path(trailingslashit($this->snapshotDirectory()) . $snapshotId . '.zip');
    }

    private function ensureSnapshotDirectory(): bool
    {
        return wp_mkdir_p($this->snapshotDirectory());
    }

    private function snapshotDirectory(): string
    {
        $uploads = FontUtils::normalizeStringKeyedMap(wp_get_upload_dir());
        $baseDir = FontUtils::scalarStringValue($uploads['basedir'] ?? '');

        if ($baseDir === '') {
            $baseDir = function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : ABSPATH;
        }

        return wp_normalize_path(trailingslashit($baseDir) . self::SNAPSHOT_DIRECTORY);
    }

    private function createTemporaryDirectory(): string|WP_Error
    {
        $directory = wp_normalize_path(trailingslashit($this->snapshotDirectory()) . 'restore-' . md5(uniqid('', true)));

        if (!wp_mkdir_p($directory)) {
            return $this->error(
                'tasty_fonts_snapshot_temp_directory_failed',
                __('A temporary directory could not be created for rollback.', 'tasty-fonts')
            );
        }

        return $directory;
    }

    private function deleteDirectory(string $directory): void
    {
        if ($directory === '' || !file_exists($directory)) {
            return;
        }

        if (is_dir($directory)) {
            $entries = scandir($directory);

            foreach (is_array($entries) ? array_diff($entries, ['.', '..']) : [] as $entry) {
                $this->deleteDirectory(wp_normalize_path($directory . DIRECTORY_SEPARATOR . $entry));
            }

            @rmdir($directory);
            return;
        }

        @unlink($directory);
    }

    private function error(string $code, string $message): WP_Error
    {
        return new WP_Error($code, $message);
    }
}
