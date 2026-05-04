<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\FamilyMetadataRepositoryInterface;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

/**
 * @phpstan-import-type AxesMap from \TastyFonts\Support\FontUtils
 * @phpstan-import-type CatalogFace from CatalogCache
 * @phpstan-type UploadRowInput array<string, mixed>
 * @phpstan-type NormalizedUploadRow array<string, mixed>
 * @phpstan-type FamilyLookup array<string, string>
 * @phpstan-type ValidatedUploadFile array{tmp_name: string, extension: string, size: int}
 * @phpstan-type WrittenUploadFile array{path: string, relative_path: string}
 * @phpstan-type RowResult array{index: int, status: string, message: string}
 * @phpstan-type UploadSummary array{imported: int, skipped: int, errors: int}
 * @phpstan-type UploadResult array{message: string, rows: list<RowResult>, summary: UploadSummary, families: list<string>}
 */
final class LocalUploadService
{
    private const ALLOWED_EXTENSIONS = ['woff2', 'woff', 'ttf', 'otf'];
    private const ALLOWED_WEIGHTS = ['100', '200', '300', '400', '500', '600', '700', '800', '900'];
    private const ALLOWED_STYLES = ['normal', 'italic', 'oblique'];

    /**
     * Create the local upload service.
     *
     * @since 1.4.0
     *
     * @param Storage $storage Storage abstraction for uploads/fonts paths and writes.
     * @param CatalogCache $catalog Catalog service used to inspect existing families and faces.
     * @param AssetService $assets Asset service used to refresh generated CSS after imports.
     * @param SettingsRepository $settings Settings repository used to persist family fallbacks.
     * @param LogRepository $log Log repository used for audit entries.
     * @param UploadedFileValidatorInterface $uploadedFileValidator Uploaded-file validator for HTTP upload checks.
     */
    public function __construct(
        private readonly Storage $storage,
        private readonly CatalogCache $catalog,
        private readonly ImportRepository $imports,
        private readonly AssetService $assets,
        SettingsRepository $settings,
        private readonly LogRepository $log,
        private readonly UploadedFileValidatorInterface $uploadedFileValidator,
        private readonly FamilyMetadataRepositoryInterface $familyMetadataRepo,
    ) {
        unset($settings);
    }

    /**
     * Import one or more uploaded local font rows into the library.
     *
     * @since 1.4.0
     *
     * @param array<int|string, UploadRowInput> $rows Raw upload rows assembled from the admin request.
     * @return UploadResult|WP_Error Import summary on success, or a WordPress error when validation fails before any row can be processed.
     */
    public function uploadRows(array $rows): array|WP_Error
    {
        if (!$this->storage->ensureRootDirectory()) {
            return $this->error(
                'tasty_fonts_upload_storage_unavailable',
                $this->storageErrorMessage(__('The font storage directory could not be created.', 'tasty-fonts'))
            );
        }

        $familyLookup = $this->buildFamilyLookup();
        $existingKeys = $this->buildExistingVariantFormatKeys();
        $normalizedRows = [];
        $results = [];
        $uploadedFacesByFamily = [];

        foreach ($rows as $index => $row) {
            $normalizedRows[] = $this->normalizeRow($row, is_int($index) ? $index : count($normalizedRows), $familyLookup);
        }

        $conflictedFamilySlugs = $this->detectFallbackConflicts($normalizedRows);
        $importedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $importedFamilies = [];
        $batchKeys = [];

        foreach ($normalizedRows as $row) {
            $index = $this->intValue($row, 'index');

            if (!empty($row['error'])) {
                $results[] = $this->buildRowResult($index, 'error', $this->stringValue($row, 'error'));
                $errorCount++;
                continue;
            }

            $familySlug = $this->stringValue($row, 'family_slug');
            $familyName = $this->stringValue($row, 'family');
            $weight = $this->stringValue($row, 'weight', '400');
            $style = $this->stringValue($row, 'style', 'normal');
            $isVariable = !empty($row['is_variable']);
            $fallback = $this->stringValue($row, 'fallback', 'sans-serif');
            $axes = $this->arrayValue($row, 'axes');
            $variationDefaults = $this->arrayValue($row, 'variation_defaults');
            $file = $this->arrayValue($row, 'file');

            if (isset($conflictedFamilySlugs[$familySlug])) {
                $results[] = $this->buildRowResult(
                    $index,
                    'error',
                    __('Use one fallback per family in a single upload batch.', 'tasty-fonts')
                );
                $errorCount++;
                continue;
            }

            $validatedFile = $this->validateUploadedFile($file);

            if (is_wp_error($validatedFile)) {
                $results[] = $this->buildRowResult($index, 'error', $validatedFile->get_error_message());
                $errorCount++;
                continue;
            }

            $extension = (string) $validatedFile['extension'];
            $duplicateKey = $this->buildDuplicateKey($familySlug, $weight, $style, $extension, $isVariable);

            if (isset($existingKeys[$duplicateKey]) || isset($batchKeys[$duplicateKey])) {
                $results[] = $this->buildRowResult(
                    $index,
                    'skipped',
                    sprintf(
                        __('%1$s %2$s %3$s already exists as a %4$s file.', 'tasty-fonts'),
                        $familyName,
                        $weight,
                        $style,
                        strtoupper($extension)
                    )
                );
                $skippedCount++;
                continue;
            }

            $write = $this->writeUploadedFontFile($familyName, $familySlug, $weight, $style, $validatedFile, $isVariable);

            if (is_wp_error($write)) {
                $results[] = $this->buildRowResult($index, 'error', $write->get_error_message());
                $errorCount++;
                continue;
            }

            $batchKeys[$duplicateKey] = true;
            $importedFamilies[$familyName] = $fallback;
            $uploadedFacesByFamily[$familyName][] = [
                'family' => $familyName,
                'slug' => $familySlug,
                'source' => 'local',
                'weight' => $weight,
                'style' => $style,
                'unicode_range' => '',
                'files' => [
                    $extension => $write['relative_path'],
                ],
                'paths' => [
                    $extension => $write['relative_path'],
                ],
                'provider' => ['type' => 'local'],
                'is_variable' => $isVariable,
                'axes' => $axes,
                'variation_defaults' => $variationDefaults,
            ];
            $results[] = $this->buildRowResult(
                $index,
                'imported',
                sprintf(
                    __('Saved %1$s %2$s %3$s as %4$s.', 'tasty-fonts'),
                    $familyName,
                    $weight,
                    $style,
                    strtoupper($extension)
                )
            );
            $importedCount++;
        }

        if ($importedFamilies !== []) {
            foreach ($importedFamilies as $familyName => $fallback) {
                $this->familyMetadataRepo->saveFallback((string) $familyName, (string) $fallback);
                $this->saveLocalProfile(
                    (string) $familyName,
                    FontUtils::slugify((string) $familyName),
                    $uploadedFacesByFamily[$familyName] ?? []
                );
            }

            $this->assets->refreshGeneratedAssets();
        }

        $summaryMessage = $this->buildSummaryMessage($importedCount, $skippedCount, $errorCount, array_keys($importedFamilies));

        if ($results !== []) {
            $this->log->add($summaryMessage, [
                'category' => LogRepository::CATEGORY_IMPORT,
                'event' => 'local_upload_finished',
                'outcome' => $errorCount > 0 ? 'warning' : ($importedCount > 0 ? 'success' : 'info'),
                'status_label' => $errorCount > 0 ? __('Review', 'tasty-fonts') : ($importedCount > 0 ? __('Uploaded', 'tasty-fonts') : __('Skipped', 'tasty-fonts')),
                'source' => __('Upload', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Imported rows', 'tasty-fonts'), 'value' => (string) $importedCount, 'kind' => 'count'],
                    ['label' => __('Skipped rows', 'tasty-fonts'), 'value' => (string) $skippedCount, 'kind' => 'count'],
                    ['label' => __('Rows with errors', 'tasty-fonts'), 'value' => (string) $errorCount, 'kind' => 'count'],
                    ['label' => __('Families affected', 'tasty-fonts'), 'value' => $importedFamilies === [] ? __('None', 'tasty-fonts') : implode(', ', array_keys($importedFamilies))],
                ],
            ]);
        }

        return [
            'message' => $summaryMessage,
            'rows' => $results,
            'summary' => [
                'imported' => $importedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
            ],
            'families' => array_keys($importedFamilies),
        ];
    }

    /**
     * @param UploadRowInput $row
     * @param FamilyLookup $familyLookup
     * @return NormalizedUploadRow
     */
    private function normalizeRow(array $row, int $index, array $familyLookup): array
    {
        $familyInput = preg_replace('/\s+/', ' ', trim(wp_strip_all_tags($this->stringValue($row, 'family')))) ?? '';
        $familySlug = FontUtils::slugify($familyInput);
        $familyName = $familyLookup[$familySlug] ?? $familyInput;
        $rawWeight = trim($this->stringValue($row, 'weight', '400'));
        $weight = FontUtils::normalizeWeight($rawWeight);
        $style = FontUtils::normalizeStyle($this->stringValue($row, 'style', 'normal'));
        $isVariable = !empty($row['is_variable']);
        $fallback = FontUtils::sanitizeFallback($this->stringValue($row, 'fallback', 'sans-serif'));
        $axes = FontUtils::normalizeAxesMap($row['axes'] ?? []);
        $variationDefaults = FontUtils::normalizeVariationDefaults($row['variation_defaults'] ?? [], $axes);
        $file = $this->arrayValue($row, 'file');
        $error = '';

        if ($familyInput === '') {
            $error = __('Enter a font family name for each uploaded row.', 'tasty-fonts');
        } elseif (!$isVariable && !in_array($rawWeight, self::ALLOWED_WEIGHTS, true)) {
            $error = __('Choose a valid font weight before uploading.', 'tasty-fonts');
        } elseif (!in_array($style, self::ALLOWED_STYLES, true)) {
            $error = __('Choose a valid font style before uploading.', 'tasty-fonts');
        } elseif ($isVariable && $axes === []) {
            $error = __('Add at least one axis for each variable font upload.', 'tasty-fonts');
        }

        return [
            'index' => $index,
            'family' => $familyName,
            'family_slug' => $familySlug,
            'weight' => $isVariable ? $this->variableWeightFromAxes($weight, $axes) : $weight,
            'style' => $style,
            'is_variable' => $isVariable,
            'axes' => $axes,
            'variation_defaults' => $variationDefaults,
            'fallback' => $fallback,
            'file' => $file,
            'error' => $error,
        ];
    }

    /**
     * @param list<NormalizedUploadRow> $rows
     * @return array<string, bool>
     */
    private function detectFallbackConflicts(array $rows): array
    {
        $fallbacksByFamily = [];
        $conflicted = [];

        foreach ($rows as $row) {
            if (!empty($row['error'])) {
                continue;
            }

            $familySlug = $this->stringValue($row, 'family_slug');
            $fallback = $this->stringValue($row, 'fallback', 'sans-serif');

            if ($familySlug === '') {
                continue;
            }

            if (!isset($fallbacksByFamily[$familySlug])) {
                $fallbacksByFamily[$familySlug] = $fallback;
                continue;
            }

            if ($fallbacksByFamily[$familySlug] !== $fallback) {
                $conflicted[$familySlug] = true;
            }
        }

        return $conflicted;
    }

    /**
     * @param array<int|string, mixed> $file
     * @return ValidatedUploadFile|WP_Error
     */
    private function validateUploadedFile(array $file): array|WP_Error
    {
        $name = trim($this->stringValue($file, 'name'));
        $tmpName = $this->stringValue($file, 'tmp_name');
        $error = $this->intValue($file, 'error', UPLOAD_ERR_NO_FILE);
        $size = $this->intValue($file, 'size');

        if ($error === UPLOAD_ERR_NO_FILE || $name === '' || $tmpName === '') {
            return $this->error('tasty_fonts_upload_missing_file', __('Choose a font file to upload.', 'tasty-fonts'));
        }

        if ($error !== UPLOAD_ERR_OK) {
            return $this->error('tasty_fonts_upload_failed', __('The uploaded file could not be processed by WordPress.', 'tasty-fonts'));
        }

        if ($size <= 0) {
            return $this->error('tasty_fonts_upload_empty_file', __('The uploaded file was empty.', 'tasty-fonts'));
        }

        $uploadLimit = (int) wp_max_upload_size();

        if ($uploadLimit > 0 && $size > $uploadLimit) {
            return $this->error('tasty_fonts_upload_too_large', __('The uploaded file exceeded the current WordPress upload size limit.', 'tasty-fonts'));
        }

        if (!$this->isUploadedFile($tmpName)) {
            return $this->error(
                'tasty_fonts_upload_unverified_tmp',
                __('The uploaded font file could not be verified as a valid HTTP upload.', 'tasty-fonts')
            );
        }

        if (!is_readable($tmpName)) {
            return $this->error('tasty_fonts_upload_missing_tmp', __('The uploaded file was not readable on the server.', 'tasty-fonts'));
        }

        $originalExtension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($originalExtension === 'zip') {
            return $this->error(
                'tasty_fonts_upload_zip_not_supported',
                __('Upload a WOFF2, WOFF, TTF, or OTF file directly. ZIP archives are not supported.', 'tasty-fonts')
            );
        }

        if (!in_array($originalExtension, self::ALLOWED_EXTENSIONS, true)) {
            return $this->error(
                'tasty_fonts_upload_invalid_extension',
                __('Upload a WOFF2, WOFF, TTF, or OTF font file. Other formats are not accepted here.', 'tasty-fonts')
            );
        }

        $detectedExtension = $this->detectUploadedFontExtension($tmpName);

        if ($detectedExtension === 'zip') {
            return $this->error(
                'tasty_fonts_upload_zip_not_supported',
                __('Upload a WOFF2, WOFF, TTF, or OTF file directly. ZIP archives are not supported.', 'tasty-fonts')
            );
        }

        if ($detectedExtension === null || !in_array($detectedExtension, self::ALLOWED_EXTENSIONS, true)) {
            return $this->error(
                'tasty_fonts_upload_invalid_file',
                __('That file does not look like a supported font. Upload WOFF2, WOFF, TTF, or OTF only.', 'tasty-fonts')
            );
        }

        return [
            'tmp_name' => $tmpName,
            'extension' => $detectedExtension,
            'size' => $size,
        ];
    }

    private function detectUploadedFontExtension(string $tmpName): ?string
    {
        $header = file_get_contents($tmpName, false, null, 0, 4);

        if (!is_string($header) || $header === '') {
            return null;
        }

        if (str_starts_with($header, 'PK')) {
            return 'zip';
        }

        if ($header === 'wOF2') {
            return 'woff2';
        }

        if ($header === 'wOFF') {
            return 'woff';
        }

        if ($header === 'OTTO') {
            return 'otf';
        }

        if ($header === "\x00\x01\x00\x00" || $header === 'true') {
            return 'ttf';
        }

        return null;
    }

    private function isUploadedFile(string $tmpName): bool
    {
        return $this->uploadedFileValidator->isUploadedFile($tmpName);
    }

    /**
     * @param ValidatedUploadFile $validatedFile
     * @return WrittenUploadFile|WP_Error
     */
    private function writeUploadedFontFile(
        string $familyName,
        string $familySlug,
        string $weight,
        string $style,
        array $validatedFile,
        bool $isVariable = false
    ): array|WP_Error {
        $root = $this->storage->getUploadRoot();

        if (!$root) {
            return $this->error(
                'tasty_fonts_upload_storage_unavailable',
                $this->storageErrorMessage(__('The font storage directory could not be created.', 'tasty-fonts'))
            );
        }

        $familyDirectory = wp_normalize_path(trailingslashit($root) . $familySlug);

        if (!$this->storage->ensureDirectory($familyDirectory)) {
            return $this->error(
                'tasty_fonts_upload_family_directory_failed',
                $this->storageErrorMessage(__('The font family directory could not be created inside the local upload folder.', 'tasty-fonts'))
            );
        }

        $extension = $validatedFile['extension'];
        $tmpName = $validatedFile['tmp_name'];
        $filename = $isVariable
            ? FontUtils::buildVariableFontFilename($familyName, $style, $extension)
            : FontUtils::buildStaticFontFilename($familyName, $weight, $style, $extension);
        $targetPath = wp_normalize_path(trailingslashit($familyDirectory) . $filename);
        $relativePath = $this->storage->relativePath($targetPath);

        if (file_exists($targetPath)) {
            if (is_readable($targetPath) && $relativePath !== '') {
                return [
                    'path' => $targetPath,
                    'relative_path' => $relativePath,
                ];
            }

            return $this->error(
                'tasty_fonts_upload_duplicate_file',
                __('That font file already exists in the library.', 'tasty-fonts')
            );
        }

        if (!$this->storage->copyAbsoluteFile($tmpName, $targetPath)) {
            return $this->error(
                'tasty_fonts_upload_write_failed',
                $this->storageErrorMessage(__('The uploaded font file could not be copied into the local upload folder.', 'tasty-fonts'))
            );
        }

        return [
            'path' => $targetPath,
            'relative_path' => $relativePath,
        ];
    }

    /**
     * @param AxesMap $axes
     */
    private function variableWeightFromAxes(string $fallbackWeight, array $axes): string
    {
        $normalizedAxes = FontUtils::normalizeAxesMap($axes);
        $weightAxis = is_array($normalizedAxes['WGHT'] ?? null) ? $normalizedAxes['WGHT'] : null;

        if (!is_array($weightAxis)) {
            return $fallbackWeight !== '' ? $fallbackWeight : '400';
        }

        $min = (string) ($weightAxis['min'] ?? '');
        $max = (string) ($weightAxis['max'] ?? '');

        if ($min === '' || $max === '') {
            return $fallbackWeight !== '' ? $fallbackWeight : '400';
        }

        if ($min === $max) {
            return FontUtils::normalizeWeight($min);
        }

        return FontUtils::normalizeWeight($min . '..' . $max);
    }

    private function storageErrorMessage(string $fallback): string
    {
        $message = trim($this->storage->getLastFilesystemErrorMessage());

        return $message !== '' ? $message : $fallback;
    }

    /**
     * @return FamilyLookup
     */
    private function buildFamilyLookup(): array
    {
        $lookup = [];

        foreach ($this->catalog->getCatalog() as $family) {
            $familyName = $this->stringValue($family, 'family');
            $familySlug = $this->stringValue($family, 'slug');

            if ($familyName === '' || $familySlug === '') {
                continue;
            }

            $lookup[$familySlug] = $familyName;
        }

        return $lookup;
    }

    /**
     * @return array<string, bool>
     */
    private function buildExistingVariantFormatKeys(): array
    {
        $keys = [];

        foreach ($this->catalog->getCatalog() as $family) {
            $familySlug = $this->stringValue($family, 'slug');

            if ($familySlug === '') {
                continue;
            }

            foreach ($this->normalizeCatalogFaceList($family['faces'] ?? []) as $face) {
                $weight = $this->stringValue($face, 'weight', '400');
                $style = $this->stringValue($face, 'style', 'normal');
                $isVariable = !empty($face['is_variable']);

                foreach (array_keys($this->arrayValue($face, 'files')) as $format) {
                    $keys[$this->buildDuplicateKey($familySlug, $weight, $style, strtolower(is_string($format) ? $format : ''), $isVariable)] = true;
                }
            }
        }

        return $keys;
    }

    private function buildDuplicateKey(string $familySlug, string $weight, string $style, string $extension, bool $isVariable = false): string
    {
        return implode(
            '|',
            [
                $familySlug,
                $isVariable ? 'variable' : 'static',
                FontUtils::normalizeWeight($weight),
                FontUtils::normalizeStyle($style),
                strtolower($extension),
            ]
        );
    }

    /**
     * @param list<string> $families
     */
    private function buildSummaryMessage(int $importedCount, int $skippedCount, int $errorCount, array $families): string
    {
        $familySummary = $families !== []
            ? ' ' . sprintf(
                __('Families: %s.', 'tasty-fonts'),
                implode(', ', $families)
            )
            : '';

        return sprintf(
            __('Local font upload finished: %1$d imported, %2$d skipped, %3$s.%4$s', 'tasty-fonts'),
            $importedCount,
            $skippedCount,
            sprintf(_n('%d error', '%d errors', $errorCount, 'tasty-fonts'), $errorCount),
            $familySummary
        );
    }

    /**
     * @return RowResult
     */
    private function buildRowResult(int $index, string $status, string $message): array
    {
        return [
            'index' => $index,
            'status' => $status,
            'message' => $message,
        ];
    }

    private function error(string $code, string $message): WP_Error
    {
        return new WP_Error($code, $message);
    }

    /**
     * @param list<CatalogFace> $newFaces
     */
    private function saveLocalProfile(string $familyName, string $familySlug, array $newFaces): void
    {
        if ($newFaces === []) {
            return;
        }

        $existingFamily = $this->imports->getFamily($familySlug);
        $profileId = FontUtils::slugify('local-self_hosted');
        $existingProfiles = $existingFamily !== null ? $existingFamily['delivery_profiles'] : [];
        $existingProfile = is_array($existingProfiles[$profileId] ?? null)
            ? (array) $existingProfiles[$profileId]
            : [];
        $existingFaces = $this->normalizeCatalogFaceList($existingProfile['faces'] ?? []);
        $mergedFaces = HostedImportSupport::mergeManifestFaces($existingFaces, $newFaces);

        $this->imports->saveProfile(
            $familyName,
            $familySlug,
            [
                'id' => $profileId,
                'provider' => 'local',
                'type' => 'self_hosted',
                'label' => __('Self-hosted', 'tasty-fonts'),
                'variants' => HostedImportSupport::variantsFromFaces($mergedFaces),
                'faces' => $mergedFaces,
                'meta' => [
                    'origin' => 'local_upload',
                    'imported_at' => current_time('mysql'),
                ],
            ],
            $existingFamily === null ? 'library_only' : $this->stringValue($existingFamily, 'publish_state', 'library_only'),
            $existingFamily === null
        );
    }

    /**
     * @param mixed $faces
     * @return list<CatalogFace>
     */
    private function normalizeCatalogFaceList(mixed $faces): array
    {
        return FontUtils::normalizeFaceList($faces);
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $value = FontUtils::scalarStringValue($values[$key]);

        return $value !== '' ? $value : $default;
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int|string, mixed>
     */
    private function arrayValue(array $values, string $key): array
    {
        $value = $values[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function intValue(array $values, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        return FontUtils::scalarIntValue($values[$key], $default);
    }
}
