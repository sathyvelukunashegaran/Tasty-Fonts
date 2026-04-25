<?php

declare(strict_types=1);

namespace TastyFonts\Maintenance;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\BlockEditorFontLibraryService;
use TastyFonts\Fonts\LibraryService;
use TastyFonts\Fonts\UploadedFileValidatorInterface;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;
use ZipArchive;

/**
 * @phpstan-import-type AxesMap from \TastyFonts\Support\FontUtils
 * @phpstan-import-type LibraryMap from ImportRepository
 * @phpstan-import-type LibraryRecord from ImportRepository
 * @phpstan-import-type DeliveryProfile from ImportRepository
 * @phpstan-import-type FaceRecord from ImportRepository
 * @phpstan-import-type NormalizedSettings from SettingsRepository
 * @phpstan-import-type RoleSet from SettingsRepository
 * @phpstan-import-type VariationDefaults from \TastyFonts\Support\FontUtils
 * @phpstan-type TransferCapabilityStatus array{available: bool, message: string}
 * @phpstan-type TransferFileEntry array{relative_path: string, size: int, sha256: string}
 * @phpstan-type TransferFileList list<TransferFileEntry>
 * @phpstan-type TransferSecretRequirement array{key: string, label: string, required: bool, exported: bool}
 * @phpstan-type TransferManifest array{
 *     schema_version: int,
 *     plugin_version: string,
 *     exported_at: string,
 *     settings: NormalizedSettings,
 *     roles: RoleSet,
 *     applied_roles: RoleSet,
 *     library: LibraryMap,
 *     secret_requirements: list<TransferSecretRequirement>,
 *     files: TransferFileList
 * }
 * @phpstan-type TransferExportBundle array{
 *     path: string,
 *     filename: string,
 *     content_type: string,
 *     size: int,
 *     manifest: TransferManifest,
 *     retained?: bool
 * }
 * @phpstan-type TransferExportSummary array{
 *     id: string,
 *     created_at: string,
 *     plugin_version: string,
 *     families: int,
 *     files: int,
 *     size: int,
 *     label: string,
 *     filename: string,
 *     protected: bool,
 *     created_sequence: float,
 *     family_names: list<string>,
 *     role_families: list<string>
 * }
 * @phpstan-type PreparedUpload array{path: string, name: string}
 * @phpstan-type ValidatedImportBundle array{
 *     manifest: TransferManifest,
 *     files: TransferFileList,
 *     extract_dir: string,
 *     zip_path: string
 * }
 * @phpstan-type StagedBundleSummary array{
 *     bundle_name: string,
 *     plugin_version: string,
 *     exported_at: string,
 *     families: int,
 *     files: int,
 *     diff: array<string, mixed>
 * }
 * @phpstan-type StagedImportState array{
 *     token: string,
 *     path: string,
 *     bundle_name: string,
 *     plugin_version: string,
 *     exported_at: string,
 *     families: int,
 *     files: int,
 *     diff: array<string, mixed>
 * }
 * @phpstan-type StagedImportResult array{
 *     stage_token: string,
 *     bundle_name: string,
 *     plugin_version: string,
 *     exported_at: string,
 *     families: int,
 *     files: int,
 *     diff: array<string, mixed>
 * }
 * @phpstan-type ImportResult array{
 *     settings: NormalizedSettings,
 *     roles: RoleSet,
 *     library: LibraryMap,
 *     families: int,
 *     files: int,
 *     used_fresh_google_api_key: bool
 * }
 */
final class SiteTransferService
{
    public const SCHEMA_VERSION = 1;
    public const MANIFEST_FILENAME = 'tasty-fonts-export.json';
    public const OPTION_EXPORT_BUNDLES = 'tasty_fonts_site_transfer_export_bundles';
    public const MIN_EXPORT_RETENTION_LIMIT = 1;
    public const MAX_EXPORT_RETENTION_LIMIT = 10;
    public const DEFAULT_EXPORT_RETENTION_LIMIT = 5;
    private const ARCHIVE_FONTS_DIRECTORY = 'fonts/';
    private const EXPORT_DIRECTORY = 'tasty-fonts-export-bundles';
    private const GENERATED_CSS_RELATIVE_PATH = '.generated/tasty-fonts.css';
    private const TEMP_DIRECTORY_PREFIX = 'tasty-fonts-transfer-';
    private const TEMP_ZIP_PREFIX = 'tasty-fonts-transfer-';
    private const STAGED_IMPORT_TRANSIENT_PREFIX = 'tasty_fonts_transfer_stage_';
    private const STAGED_IMPORT_TTL = 3600;

    public function __construct(
        private readonly Storage $storage,
        private readonly SettingsRepository $settings,
        private readonly ImportRepository $imports,
        private readonly LogRepository $log,
        private readonly DeveloperToolsService $developerTools,
        private readonly LibraryService $library,
        private readonly BlockEditorFontLibraryService $blockEditorFontLibrary,
        private readonly UploadedFileValidatorInterface $uploadedFileValidator
    ) {
    }

    /**
     * @return TransferCapabilityStatus
     */
    public function getCapabilityStatus(): array
    {
        if ($this->zipSupported()) {
            return [
                'available' => true,
                'message' => '',
            ];
        }

        return [
            'available' => false,
            'message' => __('ZipArchive is unavailable on this server, so site transfer bundles cannot be created or imported.', 'tasty-fonts'),
        ];
    }

    /**
     * @return TransferExportBundle|WP_Error
     */
    public function buildExportBundle(bool $remember = false): array|WP_Error
    {
        if (!$this->zipSupported()) {
            return $this->error(
                'tasty_fonts_transfer_zip_unavailable',
                __('ZipArchive is unavailable on this server, so site transfer bundles cannot be created.', 'tasty-fonts')
            );
        }

        if (!$this->developerTools->ensureStorageScaffolding()) {
            return $this->error(
                'tasty_fonts_transfer_storage_unavailable',
                $this->storageErrorMessage(__('The managed fonts directory could not be prepared for export.', 'tasty-fonts'))
            );
        }

        $manifest = $this->buildManifest();
        $zipPath = $this->createTemporaryZipPath();

        if (is_wp_error($zipPath)) {
            return $zipPath;
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($openResult !== true) {
            @unlink($zipPath);

            return $this->error(
                'tasty_fonts_transfer_zip_open_failed',
                __('The export bundle could not be created.', 'tasty-fonts')
            );
        }

        $manifestJson = wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($manifestJson) || !$zip->addFromString(self::MANIFEST_FILENAME, $manifestJson)) {
            $zip->close();
            @unlink($zipPath);

            return $this->error(
                'tasty_fonts_transfer_manifest_write_failed',
                __('The export manifest could not be written into the bundle.', 'tasty-fonts')
            );
        }

        foreach ($manifest['files'] as $file) {
            $relativePath = $file['relative_path'];
            $absolutePath = $this->storage->pathForRelativePath($relativePath);

            if (!is_string($absolutePath) || !is_readable($absolutePath)) {
                $zip->close();
                @unlink($zipPath);

                return $this->error(
                    'tasty_fonts_transfer_missing_export_file',
                    __('A managed font file was missing while the export bundle was being built.', 'tasty-fonts')
                );
            }

            if (!$zip->addFile($absolutePath, self::ARCHIVE_FONTS_DIRECTORY . $relativePath)) {
                $zip->close();
                @unlink($zipPath);

                return $this->error(
                    'tasty_fonts_transfer_zip_write_failed',
                    __('A managed font file could not be added to the export bundle.', 'tasty-fonts')
                );
            }
        }

        $zip->close();

        $filename = $this->buildExportFilename();
        $size = is_file($zipPath) ? (int) filesize($zipPath) : 0;

        if ($remember) {
            $exportId = $this->newExportBundleId();
            $exportPath = $this->exportBundlePath($exportId);

            if (!$this->ensureExportBundleDirectory()) {
                @unlink($zipPath);

                return $this->error(
                    'tasty_fonts_transfer_export_storage_unavailable',
                    __('The export bundle could not be saved for later download.', 'tasty-fonts')
                );
            }

            if (!@rename($zipPath, $exportPath)) {
                if (!@copy($zipPath, $exportPath)) {
                    @unlink($zipPath);

                    return $this->error(
                        'tasty_fonts_transfer_export_persist_failed',
                        __('The export bundle could not be saved for later download.', 'tasty-fonts')
                    );
                }

                @unlink($zipPath);
            }

            $zipPath = $exportPath;
            $size = is_file($zipPath) ? (int) filesize($zipPath) : $size;
            $this->rememberExportBundle($this->buildExportSummary($exportId, $manifest, $filename, $size));
        }

        return [
            'path' => $zipPath,
            'filename' => $filename,
            'content_type' => 'application/zip',
            'size' => $size,
            'manifest' => $manifest,
            'retained' => $remember,
        ];
    }

    /**
     * @return list<TransferExportSummary>
     */
    public function listExportBundles(): array
    {
        $bundles = [];
        $changed = false;

        foreach ($this->storedExportBundles() as $bundle) {
            if (!is_readable($this->exportBundlePath($bundle['id']))) {
                continue;
            }

            $hydrated = $this->hydrateExportBundleSummary($bundle);

            if ($hydrated !== $bundle) {
                $changed = true;
            }

            $bundle = $hydrated;
            $bundles[] = $bundle;
        }

        $this->sortExportBundles($bundles);

        if ($changed) {
            $this->storeExportBundles($bundles);
        }

        return $bundles;
    }

    /**
     * @return array{path: string, filename: string, content_type: string, size: int}|WP_Error
     */
    public function exportBundleDownload(string $exportId): array|WP_Error
    {
        $exportId = sanitize_key($exportId);

        foreach ($this->storedExportBundles() as $bundle) {
            if ($bundle['id'] !== $exportId) {
                continue;
            }

            $path = $this->exportBundlePath($exportId);

            if ($path === '' || !is_readable($path)) {
                return $this->error(
                    'tasty_fonts_transfer_export_missing',
                    __('The saved export bundle could not be found.', 'tasty-fonts')
                );
            }

            return [
                'path' => $path,
                'filename' => $bundle['filename'] !== '' ? $bundle['filename'] : $this->buildExportFilename(),
                'content_type' => 'application/zip',
                'size' => is_file($path) ? (int) filesize($path) : $bundle['size'],
            ];
        }

        return $this->error(
            'tasty_fonts_transfer_export_not_found',
            __('The saved export bundle could not be found.', 'tasty-fonts')
        );
    }

    public function retentionLimit(): int
    {
        return self::normalizeRetentionLimit($this->settings->getSettings()['site_transfer_export_retention_limit'] ?? self::DEFAULT_EXPORT_RETENTION_LIMIT);
    }

    public static function normalizeRetentionLimit(mixed $limit): int
    {
        $normalized = is_scalar($limit) || $limit === null ? absint($limit) : 0;

        return max(
            self::MIN_EXPORT_RETENTION_LIMIT,
            min(self::MAX_EXPORT_RETENTION_LIMIT, $normalized ?: self::DEFAULT_EXPORT_RETENTION_LIMIT)
        );
    }

    public function pruneExportBundlesToRetentionLimit(): void
    {
        $this->storeExportBundles($this->pruneExportBundles($this->storedExportBundles()));
    }

    /**
     * @return array{message: string}
     */
    public function renameExportBundle(string $exportId, string $label): array|WP_Error
    {
        $exportId = sanitize_key($exportId);
        $label = $this->normalizeExportLabel($label);
        $bundles = $this->storedExportBundles();
        $updated = false;

        foreach ($bundles as &$bundle) {
            if ($bundle['id'] !== $exportId) {
                continue;
            }

            $bundle['label'] = $label;
            $updated = true;
            break;
        }
        unset($bundle);

        if (!$updated) {
            return $this->error(
                'tasty_fonts_transfer_export_not_found',
                __('The saved export bundle could not be found.', 'tasty-fonts')
            );
        }

        $this->storeExportBundles($bundles);

        return [
            'message' => $label === ''
                ? __('Export bundle name cleared.', 'tasty-fonts')
                : __('Export bundle renamed.', 'tasty-fonts'),
        ];
    }

    /**
     * @return array{message: string}
     */
    public function setExportBundleProtected(string $exportId, bool $protected): array|WP_Error
    {
        $exportId = sanitize_key($exportId);
        $bundles = $this->storedExportBundles();
        $updated = false;

        foreach ($bundles as &$bundle) {
            if ($bundle['id'] !== $exportId) {
                continue;
            }

            $bundle['protected'] = $protected;
            $updated = true;
            break;
        }
        unset($bundle);

        if (!$updated) {
            return $this->error(
                'tasty_fonts_transfer_export_not_found',
                __('The saved export bundle could not be found.', 'tasty-fonts')
            );
        }

        $this->storeExportBundles($bundles);

        return [
            'message' => $protected
                ? __('Export bundle protected.', 'tasty-fonts')
                : __('Export bundle unprotected.', 'tasty-fonts'),
        ];
    }

    /**
     * @return array{message: string}
     */
    public function deleteExportBundle(string $exportId): array|WP_Error
    {
        $exportId = sanitize_key($exportId);
        $bundles = $this->storedExportBundles();
        $remaining = [];
        $found = false;
        $path = '';

        foreach ($bundles as $bundle) {
            if ($bundle['id'] !== $exportId) {
                $remaining[] = $bundle;
                continue;
            }

            $found = true;

            if ($bundle['protected']) {
                return $this->error(
                    'tasty_fonts_transfer_export_protected',
                    __('Unprotect this export bundle before deleting it.', 'tasty-fonts')
                );
            }

            $path = $this->exportBundlePath($exportId);
        }

        if (!$found) {
            return $this->error(
                'tasty_fonts_transfer_export_not_found',
                __('The saved export bundle could not be found.', 'tasty-fonts')
            );
        }

        if ($path !== '' && file_exists($path)) {
            @unlink($path);
        }

        $this->storeExportBundles($remaining);

        return [
            'message' => __('Export bundle deleted.', 'tasty-fonts'),
        ];
    }

    /**
     * @return ValidatedImportBundle|WP_Error
     */
    public function validateImportBundle(string $zipPath): array|WP_Error
    {
        if (!$this->zipSupported()) {
            return $this->error(
                'tasty_fonts_transfer_zip_unavailable',
                __('ZipArchive is unavailable on this server, so site transfer bundles cannot be imported.', 'tasty-fonts')
            );
        }

        $zipPath = wp_normalize_path($zipPath);

        if ($zipPath === '' || !is_readable($zipPath)) {
            return $this->error(
                'tasty_fonts_transfer_missing_bundle',
                __('The uploaded site transfer bundle was not readable on the server.', 'tasty-fonts')
            );
        }

        $extractDirectory = $this->createTemporaryDirectory();

        if (is_wp_error($extractDirectory)) {
            return $extractDirectory;
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath);

        if ($openResult !== true) {
            $this->deleteDirectory($extractDirectory);

            return $this->error(
                'tasty_fonts_transfer_invalid_zip',
                __('The uploaded file is not a valid Tasty Fonts transfer bundle.', 'tasty-fonts')
            );
        }

        $archiveFiles = [];
        $archiveHasManifest = false;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);
            $normalizedEntry = $this->normalizeArchiveEntryName($entryName);

            if ($normalizedEntry === '') {
                continue;
            }

            if ($this->isDirectoryEntry($normalizedEntry)) {
                continue;
            }

            if (!$this->isSafeArchiveEntry($normalizedEntry)) {
                $zip->close();
                $this->deleteDirectory($extractDirectory);

                return $this->error(
                    'tasty_fonts_transfer_unsafe_archive',
                    __('The uploaded bundle contains an unsafe file path.', 'tasty-fonts')
                );
            }

            if ($normalizedEntry === self::MANIFEST_FILENAME) {
                $archiveHasManifest = true;
                continue;
            }

            if (!str_starts_with($normalizedEntry, self::ARCHIVE_FONTS_DIRECTORY)) {
                $zip->close();
                $this->deleteDirectory($extractDirectory);

                return $this->error(
                    'tasty_fonts_transfer_unexpected_archive_entry',
                    __('The uploaded bundle contains an unexpected file outside the managed fonts payload.', 'tasty-fonts')
                );
            }

            $relativePath = substr($normalizedEntry, strlen(self::ARCHIVE_FONTS_DIRECTORY));

            if (!$this->isSafeRelativePath($relativePath)) {
                $zip->close();
                $this->deleteDirectory($extractDirectory);

                return $this->error(
                    'tasty_fonts_transfer_unsafe_font_path',
                    __('The uploaded bundle contains an invalid managed font path.', 'tasty-fonts')
                );
            }

            $archiveFiles[$relativePath] = true;
        }

        if (!$archiveHasManifest) {
            $zip->close();
            $this->deleteDirectory($extractDirectory);

            return $this->error(
                'tasty_fonts_transfer_missing_manifest',
                __('The uploaded bundle is missing its export manifest.', 'tasty-fonts')
            );
        }

        if (!$zip->extractTo($extractDirectory)) {
            $zip->close();
            $this->deleteDirectory($extractDirectory);

            return $this->error(
                'tasty_fonts_transfer_extract_failed',
                __('The uploaded bundle could not be extracted for validation.', 'tasty-fonts')
            );
        }

        $zip->close();

        $manifestPath = wp_normalize_path($extractDirectory . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME);
        $manifestJson = is_readable($manifestPath) ? file_get_contents($manifestPath) : false;

        if (!is_string($manifestJson) || trim($manifestJson) === '') {
            $this->deleteDirectory($extractDirectory);

            return $this->error(
                'tasty_fonts_transfer_manifest_unreadable',
                __('The uploaded bundle manifest could not be read after extraction.', 'tasty-fonts')
            );
        }

        $decodedManifest = json_decode($manifestJson, true);

        if (!is_array($decodedManifest)) {
            $this->deleteDirectory($extractDirectory);

            return $this->error(
                'tasty_fonts_transfer_manifest_invalid',
                __('The uploaded bundle manifest is not valid JSON.', 'tasty-fonts')
            );
        }

        $manifest = $this->normalizeTransferManifest($this->normalizeStringKeyedMap($decodedManifest));

        if (is_wp_error($manifest)) {
            $this->deleteDirectory($extractDirectory);

            return $manifest;
        }

        $manifestFiles = $manifest['files'];

        if (count($manifestFiles) !== count($archiveFiles)) {
            $this->deleteDirectory($extractDirectory);

            return $this->error(
                'tasty_fonts_transfer_file_count_mismatch',
                __('The uploaded bundle file list does not match the files stored in the archive.', 'tasty-fonts')
            );
        }

        foreach ($manifestFiles as $file) {
            $relativePath = $file['relative_path'];

            if (!isset($archiveFiles[$relativePath])) {
                $this->deleteDirectory($extractDirectory);

                return $this->error(
                    'tasty_fonts_transfer_missing_archive_file',
                    __('The uploaded bundle manifest references a managed font file that is missing from the archive.', 'tasty-fonts')
                );
            }

            $absolutePath = wp_normalize_path($extractDirectory . DIRECTORY_SEPARATOR . self::ARCHIVE_FONTS_DIRECTORY . $relativePath);

            if (!is_file($absolutePath) || !$this->isWithinExtractedFontsDirectory($absolutePath, $extractDirectory)) {
                $this->deleteDirectory($extractDirectory);

                return $this->error(
                    'tasty_fonts_transfer_invalid_extracted_file',
                    __('The uploaded bundle extracted a managed font file outside the expected directory.', 'tasty-fonts')
                );
            }

            $size = (int) filesize($absolutePath);
            $checksum = hash_file('sha256', $absolutePath);

            if ($size !== $file['size'] || !is_string($checksum) || $checksum !== $file['sha256']) {
                $this->deleteDirectory($extractDirectory);

                return $this->error(
                    'tasty_fonts_transfer_checksum_mismatch',
                    __('The uploaded bundle failed checksum validation.', 'tasty-fonts')
                );
            }
        }

        return [
            'manifest' => $manifest,
            'files' => $manifestFiles,
            'extract_dir' => $extractDirectory,
            'zip_path' => $zipPath,
        ];
    }

    /**
     * @param array<string, mixed> $uploadedFile
     * @return StagedImportResult|WP_Error
     */
    public function stageImportBundle(array $uploadedFile): array|WP_Error
    {
        $preparedUpload = $this->prepareUploadedBundle($uploadedFile);

        if (is_wp_error($preparedUpload)) {
            return $preparedUpload;
        }

        $validation = $this->validateImportBundle($preparedUpload['path']);

        if (is_wp_error($validation)) {
            @unlink($preparedUpload['path']);

            return $validation;
        }

        try {
            $manifest = $validation['manifest'];
            $files = $validation['files'];
            $stageToken = md5(uniqid('tasty-fonts-transfer-stage-', true));

            $this->clearStagedImportBundle();

            $summary = $this->buildStagedBundleSummary($preparedUpload, $manifest, $files);

            set_transient(
                $this->getStagedImportTransientKey(),
                ['token' => $stageToken, 'path' => $preparedUpload['path']] + $summary,
                self::STAGED_IMPORT_TTL
            );

            return ['stage_token' => $stageToken] + $summary;
        } finally {
            $this->deleteDirectory($validation['extract_dir']);
        }
    }

    /**
     * @return ImportResult|WP_Error
     */
    public function importStagedBundle(string $stageToken, string $freshGoogleApiKey = ''): array|WP_Error
    {
        $stagedBundle = $this->consumeStagedImportBundle($stageToken);

        if (is_wp_error($stagedBundle)) {
            return $stagedBundle;
        }

        return $this->importPreparedBundle($stagedBundle['path'], $freshGoogleApiKey, true);
    }

    /**
     * @param array<string, mixed> $uploadedFile
     * @return ImportResult|WP_Error
     */
    public function importBundleReplacingCurrentState(array $uploadedFile, string $freshGoogleApiKey = ''): array|WP_Error
    {
        $preparedUpload = $this->prepareUploadedBundle($uploadedFile);

        if (is_wp_error($preparedUpload)) {
            return $preparedUpload;
        }

        return $this->importPreparedBundle($preparedUpload['path'], $freshGoogleApiKey, true);
    }

    /**
     * @return StagedBundleSummary|WP_Error
     */
    public function previewImportBundlePath(string $zipPath): array|WP_Error
    {
        $zipPath = wp_normalize_path($zipPath);
        $validation = $this->validateImportBundle($zipPath);

        if (is_wp_error($validation)) {
            return $validation;
        }

        try {
            return $this->buildStagedBundleSummary(
                [
                    'path' => $zipPath,
                    'name' => basename($zipPath),
                ],
                $validation['manifest'],
                $validation['files']
            );
        } finally {
            $this->deleteDirectory($validation['extract_dir']);
        }
    }

    /**
     * @return ImportResult|WP_Error
     */
    public function importBundlePathReplacingCurrentState(string $zipPath, string $freshGoogleApiKey = ''): array|WP_Error
    {
        return $this->importPreparedBundle(wp_normalize_path($zipPath), $freshGoogleApiKey, false);
    }

    /**
     * @return ImportResult|WP_Error
     */
    private function importPreparedBundle(string $zipPath, string $freshGoogleApiKey = '', bool $deleteZipWhenDone = false): array|WP_Error
    {
        $validation = $this->validateImportBundle($zipPath);

        if (is_wp_error($validation)) {
            if ($deleteZipWhenDone) {
                @unlink($zipPath);
            }

            return $validation;
        }

        try {
            $root = $this->storage->getRoot();
            $manifest = $validation['manifest'];
            $settings = $manifest['settings'];
            $settings['applied_roles'] = $manifest['applied_roles'];
            $roles = $manifest['roles'];
            $library = $manifest['library'];
            $files = $validation['files'];

            $this->blockEditorFontLibrary->deleteAllSyncedFamilies(true);

            if (is_string($root) && $root !== '' && file_exists($root) && !$this->storage->deleteAbsolutePath($root)) {
                return $this->error(
                    'tasty_fonts_transfer_existing_storage_delete_failed',
                    __('The current managed fonts directory could not be removed before import.', 'tasty-fonts')
                );
            }

            $this->imports->clearLibrary();
            $this->settings->resetStoredSettingsToDefaults();
            $this->log->clear();

            if (!$this->developerTools->ensureStorageScaffolding()) {
                return $this->error(
                    'tasty_fonts_transfer_storage_scaffold_failed',
                    $this->storageErrorMessage(__('The managed fonts directory could not be recreated for import.', 'tasty-fonts'))
                );
            }

            if (!$this->restoreBundleFiles($validation['extract_dir'], $files)) {
                return $this->error(
                    'tasty_fonts_transfer_restore_files_failed',
                    __('The managed font files could not be restored from the uploaded bundle.', 'tasty-fonts')
                );
            }

            $savedSettings = $this->settings->replaceImportedSettings($settings);
            $savedRoles = $this->settings->replaceImportedRoles($roles);
            $savedLibrary = $this->imports->replaceLibrary($library);

            if (trim($freshGoogleApiKey) !== '') {
                $savedSettings = $this->settings->saveSettings(['google_api_key' => $freshGoogleApiKey]);
            }

            $this->library->syncLiveRolePublishStates(
                $this->normalizeAppliedRoles($savedSettings['applied_roles'] ?? []),
                !empty($savedSettings['auto_apply_roles'])
            );

            if (!$this->developerTools->clearPluginCachesAndRegenerateAssets()) {
                return $this->error(
                    'tasty_fonts_transfer_assets_rebuild_failed',
                    __('The bundle imported successfully, but generated assets could not be rebuilt.', 'tasty-fonts')
                );
            }

            $this->resyncBlockEditorFontLibrary();

            return [
                'settings' => $this->settings->getSettings(),
                'roles' => $savedRoles,
                'library' => $savedLibrary,
                'families' => count($savedLibrary),
                'files' => count($files),
                'used_fresh_google_api_key' => trim($freshGoogleApiKey) !== '',
            ];
        } finally {
            $this->deleteDirectory($validation['extract_dir']);

            if ($deleteZipWhenDone) {
                @unlink($zipPath);
            }
        }
    }

    /**
     * @return TransferManifest
     */
    private function buildManifest(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'plugin_version' => defined('TASTY_FONTS_VERSION') ? (string) TASTY_FONTS_VERSION : '',
            'exported_at' => current_time('mysql', true),
            'settings' => $this->buildPortableSettingsSnapshot(),
            'roles' => $this->settings->getRoles([]),
            'applied_roles' => $this->settings->getAppliedRoles([]),
            'library' => $this->imports->allFamilies(),
            'secret_requirements' => [
                [
                    'key' => 'google_api_key',
                    'label' => __('Google Fonts API key', 'tasty-fonts'),
                    'required' => false,
                    'exported' => false,
                ],
            ],
            'files' => $this->collectManagedFiles(),
        ];
    }

    /**
     * @param TransferManifest $manifest
     * @return TransferExportSummary
     */
    private function buildExportSummary(string $exportId, array $manifest, string $filename, int $size): array
    {
        return [
            'id' => sanitize_key($exportId),
            'created_at' => $manifest['exported_at'],
            'plugin_version' => $manifest['plugin_version'],
            'families' => count($manifest['library']),
            'files' => count($manifest['files']),
            'size' => max(0, $size),
            'label' => '',
            'filename' => sanitize_file_name($filename),
            'protected' => false,
            'created_sequence' => microtime(true),
            'family_names' => $this->manifestFamilyNames($manifest),
            'role_families' => $this->manifestRoleFamilies($manifest),
        ];
    }

    /**
     * @param TransferExportSummary $bundle
     * @return TransferExportSummary
     */
    private function hydrateExportBundleSummary(array $bundle): array
    {
        $path = $this->exportBundlePath($bundle['id']);

        if ($path === '' || !is_readable($path) || !$this->zipSupported()) {
            return $bundle;
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return $bundle;
        }

        $manifestJson = $zip->getFromName(self::MANIFEST_FILENAME);
        $zip->close();

        if (!is_string($manifestJson) || $manifestJson === '') {
            return $bundle;
        }

        $decoded = json_decode($manifestJson, true);

        if (!is_array($decoded)) {
            return $bundle;
        }

        $manifest = $this->normalizeTransferManifest($this->normalizeStringKeyedMap($decoded));

        if (is_wp_error($manifest)) {
            return $bundle;
        }

        $bundle['family_names'] = $this->manifestFamilyNames($manifest);
        $bundle['role_families'] = $this->manifestRoleFamilies($manifest);

        return $bundle;
    }

    /**
     * @param TransferManifest $manifest
     * @return list<string>
     */
    private function manifestFamilyNames(array $manifest): array
    {
        $names = [];

        foreach ($manifest['library'] as $slug => $family) {
            $familyName = trim($this->scalarStringValue($family, 'family'));
            $names[] = $familyName !== '' ? $familyName : (string) $slug;
        }

        $names = array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }

    /**
     * @param TransferManifest $manifest
     * @return list<string>
     */
    private function manifestRoleFamilies(array $manifest): array
    {
        $families = [];

        foreach ($manifest['applied_roles'] as $roleKey => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            if (
                str_ends_with($roleKey, '_fallback')
                || str_ends_with($roleKey, '_weight')
                || str_ends_with($roleKey, '_axes')
            ) {
                continue;
            }

            $family = trim((string) $value);

            if ($family !== '') {
                $families[] = $family;
            }
        }

        $families = array_values(array_unique($families));
        sort($families, SORT_NATURAL | SORT_FLAG_CASE);

        return $families;
    }

    /**
     * @param TransferExportSummary $bundle
     */
    private function rememberExportBundle(array $bundle): void
    {
        $bundles = array_values(
            array_filter(
                $this->storedExportBundles(),
                static fn (array $stored): bool => $stored['id'] !== $bundle['id']
            )
        );
        array_unshift($bundles, $bundle);

        $this->storeExportBundles($this->pruneExportBundles($bundles));
    }

    /**
     * @param list<TransferExportSummary> $bundles
     * @return list<TransferExportSummary>
     */
    private function pruneExportBundles(array $bundles): array
    {
        $this->sortExportBundles($bundles);

        $retained = [];
        $retainedUnprotected = 0;

        foreach ($bundles as $bundle) {
            if ($bundle['protected'] || $retainedUnprotected < $this->retentionLimit()) {
                $retained[] = $bundle;

                if (!$bundle['protected']) {
                    $retainedUnprotected++;
                }

                continue;
            }

            $path = $this->exportBundlePath($bundle['id']);

            if ($path !== '' && file_exists($path)) {
                @unlink($path);
            }
        }

        return $retained;
    }

    /**
     * @return list<TransferExportSummary>
     */
    private function storedExportBundles(): array
    {
        $stored = get_option(self::OPTION_EXPORT_BUNDLES, []);

        if (!is_array($stored)) {
            return [];
        }

        $bundles = [];

        foreach ($stored as $bundle) {
            if (!is_array($bundle)) {
                continue;
            }

            $map = $this->normalizeStringKeyedMap($bundle);
            $id = sanitize_key($this->scalarStringValue($map, 'id'));

            if ($id === '') {
                continue;
            }

            $createdSequence = $map['created_sequence'] ?? 0;

            $bundles[] = [
                'id' => $id,
                'created_at' => $this->scalarStringValue($map, 'created_at'),
                'plugin_version' => $this->scalarStringValue($map, 'plugin_version'),
                'families' => max(0, $this->scalarIntValue($map, 'families')),
                'files' => max(0, $this->scalarIntValue($map, 'files')),
                'size' => max(0, $this->scalarIntValue($map, 'size')),
                'label' => $this->normalizeExportLabel($this->scalarStringValue($map, 'label')),
                'filename' => sanitize_file_name($this->scalarStringValue($map, 'filename')),
                'protected' => !empty($map['protected']),
                'created_sequence' => is_numeric($createdSequence) ? (float) $createdSequence : 0.0,
                'family_names' => $this->normalizeStringList($map['family_names'] ?? []),
                'role_families' => $this->normalizeStringList($map['role_families'] ?? []),
            ];
        }

        $this->sortExportBundles($bundles);

        return $bundles;
    }

    /**
     * @param list<TransferExportSummary> $bundles
     */
    private function storeExportBundles(array $bundles): void
    {
        $this->sortExportBundles($bundles);
        update_option(self::OPTION_EXPORT_BUNDLES, $bundles, false);
    }

    /**
     * @param list<TransferExportSummary> $bundles
     */
    private function sortExportBundles(array &$bundles): void
    {
        usort(
            $bundles,
            static function (array $left, array $right): int {
                $leftSequence = $left['created_sequence'];
                $rightSequence = $right['created_sequence'];

                if ($leftSequence !== $rightSequence) {
                    return $rightSequence <=> $leftSequence;
                }

                $created = strcmp($right['created_at'], $left['created_at']);

                if ($created !== 0) {
                    return $created;
                }

                return strcmp($right['id'], $left['id']);
            }
        );
    }

    private function normalizeExportLabel(string $label): string
    {
        return substr(trim(sanitize_text_field($label)), 0, 80);
    }

    private function newExportBundleId(): string
    {
        return sanitize_key('export-' . gmdate('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 8));
    }

    private function exportBundlePath(string $exportId): string
    {
        $exportId = sanitize_key($exportId);

        if ($exportId === '') {
            return '';
        }

        return wp_normalize_path(trailingslashit($this->exportBundleDirectory()) . $exportId . '.zip');
    }

    private function ensureExportBundleDirectory(): bool
    {
        return wp_mkdir_p($this->exportBundleDirectory());
    }

    private function exportBundleDirectory(): string
    {
        $uploads = $this->normalizeStringKeyedMap(wp_get_upload_dir());
        $baseDir = $this->scalarStringValue($uploads, 'basedir');

        if ($baseDir === '') {
            $baseDir = function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : ABSPATH;
        }

        return wp_normalize_path(trailingslashit($baseDir) . self::EXPORT_DIRECTORY);
    }

    /**
     * @param PreparedUpload $preparedUpload
     * @param TransferManifest $manifest
     * @param TransferFileList $files
     * @return StagedBundleSummary
     */
    private function buildStagedBundleSummary(array $preparedUpload, array $manifest, array $files): array
    {
        return [
            'bundle_name' => $preparedUpload['name'],
            'plugin_version' => $manifest['plugin_version'],
            'exported_at' => $manifest['exported_at'],
            'families' => count($manifest['library']),
            'files' => count($files),
            'diff' => $this->buildImportDiff($manifest, $files),
        ];
    }

    /**
     * @param TransferManifest $manifest
     * @param TransferFileList $files
     * @return array<string, mixed>
     */
    private function buildImportDiff(array $manifest, array $files): array
    {
        $currentLibrary = $this->imports->allFamilies();
        $incomingLibrary = $manifest['library'];
        $currentSettings = $this->buildPortableSettingsSnapshot();
        $incomingSettings = $manifest['settings'];
        $secretRequirements = $manifest['secret_requirements'];
        $settingsChanged = 0;
        $secretsRequired = [];

        foreach ($incomingSettings as $key => $value) {
            if (($currentSettings[$key] ?? null) !== $value) {
                $settingsChanged++;
            }
        }

        foreach ($secretRequirements as $requirement) {
            if (!empty($requirement['required']) || empty($requirement['exported'])) {
                $secretsRequired[] = [
                    'key' => $requirement['key'],
                    'label' => $requirement['label'],
                    'required' => $requirement['required'],
                    'exported' => $requirement['exported'],
                ];
            }
        }

        return [
            'incoming_plugin_version' => $manifest['plugin_version'],
            'family_count' => count($incomingLibrary),
            'file_count' => count($files),
            'families_added' => array_values(array_diff(array_keys($incomingLibrary), array_keys($currentLibrary))),
            'families_removed' => array_values(array_diff(array_keys($currentLibrary), array_keys($incomingLibrary))),
            'families_changed' => $this->changedFamilySlugs($currentLibrary, $incomingLibrary),
            'settings_changed' => $settingsChanged,
            'secrets_required' => $secretsRequired,
            'snapshot_will_be_created' => true,
        ];
    }

    /**
     * @param LibraryMap $currentLibrary
     * @param LibraryMap $incomingLibrary
     * @return list<string>
     */
    private function changedFamilySlugs(array $currentLibrary, array $incomingLibrary): array
    {
        $changed = [];

        foreach ($incomingLibrary as $slug => $family) {
            if (isset($currentLibrary[$slug]) && $currentLibrary[$slug] !== $family) {
                $changed[] = $slug;
            }
        }

        sort($changed, SORT_NATURAL | SORT_FLAG_CASE);

        return $changed;
    }

    /**
     * @return NormalizedSettings
     */
    private function buildPortableSettingsSnapshot(): array
    {
        $settings = $this->settings->getSettings();

        unset(
            $settings['google_api_key'],
            $settings['google_api_key_status'],
            $settings['google_api_key_status_message'],
            $settings['google_api_key_checked_at'],
            $settings['applied_roles']
        );

        $settings['acss_font_role_sync_opted_out'] = false;
        $settings['acss_font_role_sync_applied'] = false;
        $settings['acss_font_role_sync_previous_heading_font_family'] = '';
        $settings['acss_font_role_sync_previous_text_font_family'] = '';
        $settings['acss_font_role_sync_previous_heading_font_weight'] = '';
        $settings['acss_font_role_sync_previous_text_font_weight'] = '';

        if (trim($this->scalarStringValue($settings, 'adobe_project_id')) === '') {
            $settings['adobe_enabled'] = false;
            $settings['adobe_project_status'] = 'empty';
        } else {
            $settings['adobe_project_status'] = 'unknown';
        }

        $settings['adobe_project_status_message'] = '';
        $settings['adobe_project_checked_at'] = 0;

        return $settings;
    }

    /**
     * @return TransferFileList
     */
    private function collectManagedFiles(): array
    {
        $root = $this->storage->getRoot();

        if (!is_string($root) || $root === '' || !is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                continue;
            }

            $absolutePath = wp_normalize_path($item->getPathname());
            $relativePath = $this->storage->relativePath($absolutePath);

            if ($relativePath === '' || $relativePath === self::GENERATED_CSS_RELATIVE_PATH) {
                continue;
            }

            $checksum = hash_file('sha256', $absolutePath);

            if ($checksum === false) {
                continue;
            }

            $files[] = [
                'relative_path' => $relativePath,
                'size' => (int) $item->getSize(),
                'sha256' => $checksum,
            ];
        }

        usort(
            $files,
            static fn (array $left, array $right): int => strcmp($left['relative_path'], $right['relative_path'])
        );

        return $files;
    }

    /**
     * @param array<string, mixed> $uploadedFile
     * @return PreparedUpload|WP_Error
     */
    private function prepareUploadedBundle(array $uploadedFile): array|WP_Error
    {
        $name = sanitize_file_name($this->scalarStringValue($uploadedFile, 'name'));
        $tmpName = $this->scalarStringValue($uploadedFile, 'tmp_name');
        $error = $this->scalarIntValue($uploadedFile, 'error', UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE || $name === '' || $tmpName === '') {
            return $this->error(
                'tasty_fonts_transfer_missing_upload',
                __('Choose a Tasty Fonts transfer bundle before importing.', 'tasty-fonts')
            );
        }

        if ($error !== UPLOAD_ERR_OK) {
            return $this->error(
                'tasty_fonts_transfer_upload_failed',
                __('The uploaded transfer bundle was incomplete or failed to upload.', 'tasty-fonts')
            );
        }

        if (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            return $this->error(
                'tasty_fonts_transfer_upload_not_zip',
                __('The uploaded transfer bundle must be a .zip file.', 'tasty-fonts')
            );
        }

        if (!$this->uploadedFileValidator->isUploadedFile($tmpName)) {
            return $this->error(
                'tasty_fonts_transfer_upload_unverified',
                __('The uploaded transfer bundle could not be verified as a valid upload.', 'tasty-fonts')
            );
        }

        if (!is_readable($tmpName)) {
            return $this->error(
                'tasty_fonts_transfer_upload_unreadable',
                __('The uploaded transfer bundle was not readable on the server.', 'tasty-fonts')
            );
        }

        $temporaryZipPath = $this->createTemporaryZipPath();

        if (is_wp_error($temporaryZipPath)) {
            return $temporaryZipPath;
        }

        if (!copy($tmpName, $temporaryZipPath)) {
            @unlink($temporaryZipPath);

            return $this->error(
                'tasty_fonts_transfer_upload_copy_failed',
                __('The uploaded transfer bundle could not be copied into temporary storage.', 'tasty-fonts')
            );
        }

        return [
            'path' => $temporaryZipPath,
            'name' => $name,
        ];
    }

    /**
     * @param TransferFileList $files
     */
    private function restoreBundleFiles(string $extractDirectory, array $files): bool
    {
        if ($extractDirectory === '' || !is_dir($extractDirectory)) {
            return false;
        }

        foreach ($files as $file) {
            $relativePath = $file['relative_path'];

            if (!$this->isSafeRelativePath($relativePath)) {
                return false;
            }

            $sourcePath = wp_normalize_path($extractDirectory . DIRECTORY_SEPARATOR . self::ARCHIVE_FONTS_DIRECTORY . $relativePath);
            $targetPath = $this->storage->pathForRelativePath($relativePath);

            if (!is_string($targetPath) || $targetPath === '' || !is_readable($sourcePath)) {
                return false;
            }

            if (!$this->storage->copyAbsoluteFile($sourcePath, $targetPath)) {
                return false;
            }
        }

        return true;
    }

    private function resyncBlockEditorFontLibrary(): void
    {
        foreach ($this->imports->allFamilies() as $family) {
            $profiles = $family['delivery_profiles'];
            $deliveryId = $family['active_delivery_id'];
            $profile = $deliveryId !== '' && is_array($profiles[$deliveryId] ?? null)
                ? $profiles[$deliveryId]
                : reset($profiles);

            if (!is_array($profile)) {
                continue;
            }

            $resolvedDeliveryId = $deliveryId !== '' ? $deliveryId : $profile['id'];
            $provider = trim($profile['provider']);

            $this->blockEditorFontLibrary->syncImportedFamily(
                [
                    'status' => 'imported',
                    'family' => $family['family'],
                    'delivery_id' => $resolvedDeliveryId,
                    'family_record' => $family,
                ],
                $provider !== '' ? $provider : 'import'
            );
        }
    }

    /**
     * @return TransferFileList|WP_Error
     */
    private function normalizeManifestFiles(mixed $files): array|WP_Error
    {
        if (!is_array($files)) {
            return $this->error(
                'tasty_fonts_transfer_manifest_files_missing',
                __('The uploaded bundle manifest is missing its managed font file list.', 'tasty-fonts')
            );
        }

        $normalized = [];

        foreach ($files as $file) {
            if (!is_array($file)) {
                return $this->error(
                    'tasty_fonts_transfer_manifest_file_invalid',
                    __('The uploaded bundle manifest contains an invalid file entry.', 'tasty-fonts')
                );
            }

            $normalizedFile = $this->normalizeStringKeyedMap($file);
            $relativePath = $this->scalarStringValue($normalizedFile, 'relative_path');
            $size = $this->scalarIntValue($normalizedFile, 'size', -1);
            $checksum = trim($this->scalarStringValue($normalizedFile, 'sha256'));

            if (!$this->isSafeRelativePath($relativePath) || $size < 0 || $checksum === '') {
                return $this->error(
                    'tasty_fonts_transfer_manifest_file_invalid',
                    __('The uploaded bundle manifest contains an invalid managed font file entry.', 'tasty-fonts')
                );
            }

            $normalized[$relativePath] = [
                'relative_path' => $relativePath,
                'size' => $size,
                'sha256' => $checksum,
            ];
        }

        ksort($normalized, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($normalized);
    }

    /**
     * @param array<string, mixed> $manifest
     * @return TransferManifest|WP_Error
     */
    private function normalizeTransferManifest(array $manifest): array|WP_Error
    {
        if ($this->scalarIntValue($manifest, 'schema_version') !== self::SCHEMA_VERSION) {
            return $this->error(
                'tasty_fonts_transfer_schema_unsupported',
                __('This transfer bundle uses an unsupported schema version.', 'tasty-fonts')
            );
        }

        if (!is_array($manifest['settings'] ?? null) || !is_array($manifest['roles'] ?? null) || !is_array($manifest['applied_roles'] ?? null) || !is_array($manifest['library'] ?? null)) {
            return $this->error(
                'tasty_fonts_transfer_manifest_shape_invalid',
                __('The uploaded bundle manifest is missing required settings, role, or library data.', 'tasty-fonts')
            );
        }

        $manifestFiles = $this->normalizeManifestFiles($manifest['files'] ?? null);

        if (is_wp_error($manifestFiles)) {
            return $manifestFiles;
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'plugin_version' => $this->scalarStringValue($manifest, 'plugin_version'),
            'exported_at' => $this->scalarStringValue($manifest, 'exported_at'),
            'settings' => $this->normalizeStringKeyedMap($manifest['settings']),
            'roles' => $this->normalizeRoleSet($manifest['roles']),
            'applied_roles' => $this->normalizeRoleSet($manifest['applied_roles']),
            'library' => $this->normalizeLibraryMap($manifest['library']),
            'secret_requirements' => $this->normalizeTransferSecretRequirements($manifest['secret_requirements'] ?? []),
            'files' => $manifestFiles,
        ];
    }

    /**
     * @param mixed $stored
     * @return StagedImportState
     */
    private function normalizeStagedImportState(mixed $stored): array
    {
        if (!is_array($stored)) {
            return [
                'token' => '',
                'path' => '',
                'bundle_name' => '',
                'plugin_version' => '',
                'exported_at' => '',
                'families' => 0,
                'files' => 0,
                'diff' => [],
            ];
        }

        $storedMap = $this->normalizeStringKeyedMap($stored);

        return [
            'token' => trim($this->scalarStringValue($storedMap, 'token')),
            'path' => wp_normalize_path($this->scalarStringValue($storedMap, 'path')),
            'bundle_name' => $this->scalarStringValue($storedMap, 'bundle_name'),
            'plugin_version' => $this->scalarStringValue($storedMap, 'plugin_version'),
            'exported_at' => $this->scalarStringValue($storedMap, 'exported_at'),
            'families' => max(0, $this->scalarIntValue($storedMap, 'families')),
            'files' => max(0, $this->scalarIntValue($storedMap, 'files')),
            'diff' => $this->normalizeStringKeyedMap($storedMap['diff'] ?? []),
        ];
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function normalizeStringKeyedMap(mixed $value): array
    {
        return FontUtils::normalizeStringKeyedMap($value);
    }

    /**
     * @param mixed $value
     * @return LibraryMap
     */
    private function normalizeLibraryMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!is_string($key) || !is_array($item)) {
                continue;
            }

            $normalized[$key] = $this->normalizeLibraryRecord($this->normalizeStringKeyedMap($item));
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return RoleSet
     */
    private function normalizeRoleSet(mixed $value): array
    {
        $roles = $this->normalizeStringKeyedMap($value);

        return [
            'heading' => $this->scalarStringValue($roles, 'heading'),
            'body' => $this->scalarStringValue($roles, 'body'),
            'monospace' => $this->scalarStringValue($roles, 'monospace'),
            'heading_fallback' => FontUtils::sanitizeFallback($this->scalarStringValue($roles, 'heading_fallback', FontUtils::DEFAULT_ROLE_SANS_FALLBACK)),
            'body_fallback' => FontUtils::sanitizeFallback($this->scalarStringValue($roles, 'body_fallback', FontUtils::DEFAULT_ROLE_SANS_FALLBACK)),
            'monospace_fallback' => FontUtils::sanitizeFallback($this->scalarStringValue($roles, 'monospace_fallback', 'monospace')),
            'heading_weight' => $this->scalarStringValue($roles, 'heading_weight'),
            'body_weight' => $this->scalarStringValue($roles, 'body_weight'),
            'monospace_weight' => $this->scalarStringValue($roles, 'monospace_weight'),
            'heading_axes' => $this->normalizeStringMap($roles['heading_axes'] ?? []),
            'body_axes' => $this->normalizeStringMap($roles['body_axes'] ?? []),
            'monospace_axes' => $this->normalizeStringMap($roles['monospace_axes'] ?? []),
        ];
    }

    /**
     * @param mixed $value
     * @return array{heading?: string, body?: string, monospace?: string}
     */
    private function normalizeAppliedRoles(mixed $value): array
    {
        $roles = $this->normalizeRoleSet($value);
        $applied = [];

        foreach (['heading', 'body', 'monospace'] as $roleKey) {
            $roleValue = $roles[$roleKey];

            if ($roleValue === '') {
                continue;
            }

            $applied[$roleKey] = $roleValue;
        }

        return $applied;
    }

    /**
     * @param array<string, mixed> $value
     * @return LibraryRecord
     */
    private function normalizeLibraryRecord(array $value): array
    {
        return [
            'family' => $this->scalarStringValue($value, 'family'),
            'slug' => $this->scalarStringValue($value, 'slug'),
            'publish_state' => $this->scalarStringValue($value, 'publish_state'),
            'manual_publish_state' => $this->scalarStringValue($value, 'manual_publish_state'),
            'active_delivery_id' => $this->scalarStringValue($value, 'active_delivery_id'),
            'delivery_profiles' => $this->normalizeDeliveryProfiles($value['delivery_profiles'] ?? []),
        ];
    }

    /**
     * @param mixed $value
     * @return array<string, DeliveryProfile>
     */
    private function normalizeDeliveryProfiles(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $profiles = [];

        foreach ($value as $key => $profile) {
            if (!is_string($key) || !is_array($profile)) {
                continue;
            }

            $profiles[$key] = $this->normalizeDeliveryProfile($this->normalizeStringKeyedMap($profile));
        }

        return $profiles;
    }

    /**
     * @param array<string, mixed> $value
     * @return DeliveryProfile
     */
    private function normalizeDeliveryProfile(array $value): array
    {
        return [
            'id' => $this->scalarStringValue($value, 'id'),
            'provider' => $this->scalarStringValue($value, 'provider'),
            'type' => $this->scalarStringValue($value, 'type'),
            'format' => $this->scalarStringValue($value, 'format'),
            'label' => $this->scalarStringValue($value, 'label'),
            'variants' => $this->normalizeStringList($value['variants'] ?? []),
            'faces' => $this->normalizeFaceList($value['faces'] ?? []),
            'meta' => $this->normalizeProfileMetaMap($value['meta'] ?? []),
        ];
    }

    /**
     * @param mixed $value
     * @return list<FaceRecord>
     */
    private function normalizeFaceList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $faces = [];

        foreach ($value as $face) {
            if (!is_array($face)) {
                continue;
            }

            $faces[] = $this->normalizeFaceRecord($this->normalizeStringKeyedMap($face));
        }

        return $faces;
    }

    /**
     * @param array<string, mixed> $value
     * @return FaceRecord
     */
    private function normalizeFaceRecord(array $value): array
    {
        return [
            'family' => $this->scalarStringValue($value, 'family'),
            'slug' => $this->scalarStringValue($value, 'slug'),
            'source' => $this->scalarStringValue($value, 'source'),
            'weight' => $this->scalarStringValue($value, 'weight'),
            'style' => $this->scalarStringValue($value, 'style'),
            'unicode_range' => $this->scalarStringValue($value, 'unicode_range'),
            'files' => $this->normalizeStringMap($value['files'] ?? []),
            'paths' => $this->normalizeStringMap($value['paths'] ?? []),
            'provider' => $this->normalizeStringKeyedMap($value['provider'] ?? []),
            'is_variable' => !empty($value['is_variable']),
            'axes' => $this->normalizeAxesMap($value['axes'] ?? []),
            'variation_defaults' => $this->normalizeVariationDefaultsMap($value['variation_defaults'] ?? []),
        ];
    }

    /**
     * @param mixed $value
     * @return array<string, string|list<string>>
     */
    private function normalizeProfileMetaMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $meta = [];

        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                continue;
            }

            if (is_array($item)) {
                $meta[$key] = $this->normalizeStringList($item);
                continue;
            }

            $meta[$key] = is_scalar($item) ? (string) $item : '';
        }

        return $meta;
    }

    /**
     * @param mixed $value
     * @return AxesMap
     */
    private function normalizeAxesMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $axes = [];

        foreach ($value as $key => $axis) {
            if (!is_string($key) || !is_array($axis)) {
                continue;
            }

            $normalizedAxis = [];

            foreach ($axis as $axisKey => $axisValue) {
                if (!is_string($axisKey) || !is_scalar($axisValue)) {
                    continue;
                }

                $normalizedAxis[$axisKey] = is_string($axisValue) ? $axisValue : $axisValue + 0;
            }

            $axes[$key] = $normalizedAxis;
        }

        return $axes;
    }

    /**
     * @param mixed $value
     * @return VariationDefaults
     */
    private function normalizeVariationDefaultsMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $defaults = [];

        foreach ($value as $key => $defaultValue) {
            if (!is_string($key) || !is_scalar($defaultValue)) {
                continue;
            }

            $defaults[$key] = is_string($defaultValue) ? $defaultValue : $defaultValue + 0;
        }

        return $defaults;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $values = [];

        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $normalizedItem = trim((string) $item);

            if ($normalizedItem === '') {
                continue;
            }

            $values[] = $normalizedItem;
        }

        return $values;
    }

    /**
     * @param mixed $value
     * @return array<string, string>
     */
    private function normalizeStringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!is_string($key) || !is_scalar($item)) {
                continue;
            }

            $normalized[$key] = (string) $item;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function scalarStringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function scalarIntValue(array $values, string $key, int $default = 0): int
    {
        $value = $values[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * @param mixed $requirements
     * @return list<TransferSecretRequirement>
     */
    private function normalizeTransferSecretRequirements(mixed $requirements): array
    {
        if (!is_array($requirements)) {
            return [];
        }

        $normalized = [];

        foreach ($requirements as $requirement) {
            if (!is_array($requirement)) {
                continue;
            }

            $normalized[] = [
                'key' => $this->scalarStringValue($this->normalizeStringKeyedMap($requirement), 'key'),
                'label' => $this->scalarStringValue($this->normalizeStringKeyedMap($requirement), 'label'),
                'required' => !empty($requirement['required']),
                'exported' => !empty($requirement['exported']),
            ];
        }

        return $normalized;
    }

    private function buildExportFilename(): string
    {
        $timestamp = gmdate('Ymd-His');
        $version = defined('TASTY_FONTS_VERSION') ? sanitize_file_name((string) TASTY_FONTS_VERSION) : 'bundle';

        return sprintf('tasty-fonts-transfer-%s-%s.zip', $version, $timestamp);
    }

    private function getStagedImportTransientKey(): string
    {
        return self::STAGED_IMPORT_TRANSIENT_PREFIX . (string) get_current_user_id();
    }

    private function clearStagedImportBundle(): void
    {
        $existing = get_transient($this->getStagedImportTransientKey());

        if (is_array($existing)) {
            $path = $this->normalizeStagedImportState($existing)['path'];

            if ($path !== '') {
                @unlink($path);
            }
        }

        delete_transient($this->getStagedImportTransientKey());
    }

    /**
     * @return StagedImportState|WP_Error
     */
    private function consumeStagedImportBundle(string $stageToken): array|WP_Error
    {
        $stageToken = trim($stageToken);
        $stored = get_transient($this->getStagedImportTransientKey());

        delete_transient($this->getStagedImportTransientKey());

        if (!is_array($stored)) {
            return $this->error(
                'tasty_fonts_transfer_stage_missing',
                __('Run the dry run again before importing this bundle.', 'tasty-fonts')
            );
        }

        $stored = $this->normalizeStagedImportState($stored);

        $storedToken = trim($stored['token']);
        $storedPath = $stored['path'];

        if ($stageToken === '' || !hash_equals($storedToken, $stageToken) || $storedPath === '' || !is_readable($storedPath)) {
            if ($storedPath !== '') {
                @unlink($storedPath);
            }

            return $this->error(
                'tasty_fonts_transfer_stage_missing',
                __('Run the dry run again before importing this bundle.', 'tasty-fonts')
            );
        }

        return $stored;
    }

    private function createTemporaryZipPath(): string|WP_Error
    {
        $path = tempnam($this->temporaryBaseDirectory(), self::TEMP_ZIP_PREFIX);

        if ($path === false) {
            return $this->error(
                'tasty_fonts_transfer_temp_file_failed',
                __('A temporary zip file could not be created for the transfer bundle.', 'tasty-fonts')
            );
        }

        return wp_normalize_path($path);
    }

    private function createTemporaryDirectory(): string|WP_Error
    {
        $directory = wp_normalize_path(
            trailingslashit($this->temporaryBaseDirectory()) . self::TEMP_DIRECTORY_PREFIX . md5(uniqid((string) mt_rand(), true))
        );

        if (is_dir($directory)) {
            $this->deleteDirectory($directory);
        }

        if (!wp_mkdir_p($directory)) {
            return $this->error(
                'tasty_fonts_transfer_temp_directory_failed',
                __('A temporary directory could not be created for the transfer bundle.', 'tasty-fonts')
            );
        }

        return $directory;
    }

    private function temporaryBaseDirectory(): string
    {
        $baseDirectory = function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : '';

        if (trim($baseDirectory) === '') {
            $baseDirectory = ABSPATH;
        }

        return wp_normalize_path($baseDirectory);
    }

    private function deleteDirectory(string $directory): void
    {
        $directory = wp_normalize_path($directory);

        if ($directory === '' || !file_exists($directory)) {
            return;
        }

        if (is_dir($directory)) {
            $entries = scandir($directory);

            if (is_array($entries)) {
                foreach (array_diff($entries, ['.', '..']) as $entry) {
                    $this->deleteDirectory(wp_normalize_path($directory . DIRECTORY_SEPARATOR . $entry));
                }
            }

            @rmdir($directory);

            return;
        }

        @unlink($directory);
    }

    private function normalizeArchiveEntryName(mixed $entryName): string
    {
        return ltrim(str_replace('\\', '/', is_string($entryName) ? $entryName : ''), '/');
    }

    private function isDirectoryEntry(string $entryName): bool
    {
        return str_ends_with($entryName, '/');
    }

    private function isSafeArchiveEntry(string $entryName): bool
    {
        if ($entryName === '' || str_contains($entryName, '../') || str_contains($entryName, '/..') || preg_match('#^[A-Za-z]:#', $entryName) === 1) {
            return false;
        }

        return true;
    }

    private function isSafeRelativePath(string $relativePath): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        if ($relativePath === '' || str_contains($relativePath, '../') || str_contains($relativePath, '/..') || preg_match('#^[A-Za-z]:#', $relativePath) === 1) {
            return false;
        }

        $segments = array_filter(explode('/', $relativePath), static fn (string $segment): bool => $segment !== '');

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function isWithinExtractedFontsDirectory(string $path, string $extractDirectory): bool
    {
        $fontsDirectory = wp_normalize_path($extractDirectory . DIRECTORY_SEPARATOR . self::ARCHIVE_FONTS_DIRECTORY);
        $realFontsDirectory = realpath($fontsDirectory);
        $realPath = realpath($path);

        if (!is_string($realFontsDirectory) || !is_string($realPath)) {
            return false;
        }

        $realFontsDirectory = wp_normalize_path($realFontsDirectory);
        $realPath = wp_normalize_path($realPath);

        return $realPath === $realFontsDirectory || str_starts_with($realPath, trailingslashit($realFontsDirectory));
    }

    private function zipSupported(): bool
    {
        return class_exists(ZipArchive::class);
    }

    private function storageErrorMessage(string $default): string
    {
        $message = trim($this->storage->getLastFilesystemErrorMessage());

        return $message !== '' ? $message : $default;
    }

    private function error(string $code, string $message): WP_Error
    {
        return new WP_Error($code, $message);
    }
}
