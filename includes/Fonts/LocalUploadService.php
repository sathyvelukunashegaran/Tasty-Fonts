<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

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
     * @param CatalogService $catalog Catalog service used to inspect existing families and faces.
     * @param AssetService $assets Asset service used to refresh generated CSS after imports.
     * @param SettingsRepository $settings Settings repository used to persist family fallbacks.
     * @param LogRepository $log Log repository used for audit entries.
     * @param UploadedFileValidatorInterface $uploadedFileValidator Uploaded-file validator for HTTP upload checks.
     */
    public function __construct(
        private readonly Storage $storage,
        private readonly CatalogService $catalog,
        private readonly ImportRepository $imports,
        private readonly AssetService $assets,
        private readonly SettingsRepository $settings,
        private readonly LogRepository $log,
        private readonly UploadedFileValidatorInterface $uploadedFileValidator
    ) {
    }

    /**
     * Import one or more uploaded local font rows into the library.
     *
     * @since 1.4.0
     *
     * @param array<int|string, array<string, mixed>> $rows Raw upload rows assembled from the admin request.
     * @return array{
     *     message: string,
     *     rows: list<array{index: int, status: string, message: string}>,
     *     summary: array{imported: int, skipped: int, errors: int},
     *     families: list<string>
     * }|WP_Error Import summary on success, or a WordPress error when validation fails before any row can be processed.
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
            $index = (int) ($row['index'] ?? 0);

            if (!empty($row['error'])) {
                $results[] = $this->buildRowResult($index, 'error', (string) $row['error']);
                $errorCount++;
                continue;
            }

            $familySlug = (string) ($row['family_slug'] ?? '');
            $familyName = (string) ($row['family'] ?? '');
            $weight = (string) ($row['weight'] ?? '400');
            $style = (string) ($row['style'] ?? 'normal');
            $isVariable = !empty($row['is_variable']);
            $fallback = (string) ($row['fallback'] ?? 'sans-serif');
            $axes = is_array($row['axes'] ?? null) ? (array) $row['axes'] : [];
            $variationDefaults = is_array($row['variation_defaults'] ?? null) ? (array) $row['variation_defaults'] : [];
            $file = is_array($row['file'] ?? null) ? (array) $row['file'] : [];

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
                    $extension => (string) ($write['relative_path'] ?? ''),
                ],
                'paths' => [
                    $extension => (string) ($write['relative_path'] ?? ''),
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
                $this->settings->saveFamilyFallback((string) $familyName, (string) $fallback);
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
            $this->log->add($summaryMessage);
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

    private function normalizeRow(array $row, int $index, array $familyLookup): array
    {
        $familyInput = preg_replace('/\s+/', ' ', trim(wp_strip_all_tags((string) ($row['family'] ?? '')))) ?? '';
        $familySlug = FontUtils::slugify($familyInput);
        $familyName = $familyLookup[$familySlug] ?? $familyInput;
        $rawWeight = trim((string) ($row['weight'] ?? '400'));
        $weight = FontUtils::normalizeWeight($rawWeight);
        $style = FontUtils::normalizeStyle((string) ($row['style'] ?? 'normal'));
        $isVariable = !empty($row['is_variable']);
        $fallback = FontUtils::sanitizeFallback((string) ($row['fallback'] ?? 'sans-serif'));
        $axes = FontUtils::normalizeAxesMap($row['axes'] ?? []);
        $variationDefaults = FontUtils::normalizeVariationDefaults($row['variation_defaults'] ?? [], $axes);
        $file = is_array($row['file'] ?? null) ? (array) $row['file'] : [];
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

    private function detectFallbackConflicts(array $rows): array
    {
        $fallbacksByFamily = [];
        $conflicted = [];

        foreach ($rows as $row) {
            if (!empty($row['error'])) {
                continue;
            }

            $familySlug = (string) ($row['family_slug'] ?? '');
            $fallback = (string) ($row['fallback'] ?? 'sans-serif');

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

    private function validateUploadedFile(array $file): array|WP_Error
    {
        $name = trim((string) ($file['name'] ?? ''));
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $size = (int) ($file['size'] ?? 0);

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

        $originalExtension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

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

        $extension = (string) ($validatedFile['extension'] ?? '');
        $tmpName = (string) ($validatedFile['tmp_name'] ?? '');
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

    private function buildFamilyLookup(): array
    {
        $lookup = [];

        foreach ($this->catalog->getCatalog() as $family) {
            $familyName = (string) ($family['family'] ?? '');
            $familySlug = (string) ($family['slug'] ?? '');

            if ($familyName === '' || $familySlug === '') {
                continue;
            }

            $lookup[$familySlug] = $familyName;
        }

        return $lookup;
    }

    private function buildExistingVariantFormatKeys(): array
    {
        $keys = [];

        foreach ($this->catalog->getCatalog() as $family) {
            $familySlug = (string) ($family['slug'] ?? '');

            if ($familySlug === '') {
                continue;
            }

            foreach ((array) ($family['faces'] ?? []) as $face) {
                    $weight = (string) ($face['weight'] ?? '400');
                $style = (string) ($face['style'] ?? 'normal');
                $isVariable = !empty($face['is_variable']);

                foreach (array_keys((array) ($face['files'] ?? [])) as $format) {
                    $keys[$this->buildDuplicateKey($familySlug, $weight, $style, strtolower((string) $format), $isVariable)] = true;
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

    private function saveLocalProfile(string $familyName, string $familySlug, array $newFaces): void
    {
        if ($newFaces === []) {
            return;
        }

        $existingFamily = $this->imports->getFamily($familySlug);
        $profileId = FontUtils::slugify('local-self_hosted');
        $existingProfile = is_array($existingFamily['delivery_profiles'][$profileId] ?? null)
            ? (array) $existingFamily['delivery_profiles'][$profileId]
            : [];
        $existingFaces = is_array($existingProfile['faces'] ?? null) ? (array) $existingProfile['faces'] : [];
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
            $existingFamily === null ? 'library_only' : (string) ($existingFamily['publish_state'] ?? 'published'),
            $existingFamily === null
        );
    }
}
