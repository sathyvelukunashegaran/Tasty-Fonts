<?php

declare(strict_types=1);

namespace EtchFonts\Google;

use EtchFonts\Fonts\AssetService;
use EtchFonts\Fonts\CatalogService;
use EtchFonts\Repository\ImportRepository;
use EtchFonts\Repository\LogRepository;
use EtchFonts\Support\FontUtils;
use EtchFonts\Support\Storage;
use WP_Error;

final class GoogleImportService
{
    private const MAX_FONT_FILE_BYTES = 10 * MB_IN_BYTES;

    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly GoogleFontsClient $client,
        private readonly GoogleCssParser $parser,
        private readonly CatalogService $catalog,
        private readonly AssetService $assets,
        private readonly LogRepository $log
    ) {
    }

    public function importFamily(string $familyName, array $variants): array|WP_Error
    {
        $familyName = trim(wp_strip_all_tags($familyName));

        if ($familyName === '') {
            return $this->error('etch_fonts_missing_family', __('Choose a Google Fonts family before importing.', ETCH_FONTS_TEXT_DOMAIN));
        }

        $familySlug = FontUtils::slugify($familyName);
        $variants = FontUtils::normalizeVariantTokens($variants);
        $existingCatalogFamily = $this->findCatalogFamily($familyName, $familySlug);
        $existingImport = $this->imports->get($familySlug);

        if ($existingCatalogFamily !== null && !in_array('google', (array) ($existingCatalogFamily['sources'] ?? []), true)) {
            return $this->error(
                'etch_fonts_family_already_exists',
                sprintf(
                    __('%s already exists in the library as a local family. Remove or rename the existing files before importing it from Google Fonts.', ETCH_FONTS_TEXT_DOMAIN),
                    $familyName
                )
            );
        }

        $variantPlan = $this->buildVariantPlan($variants, $existingImport);

        if ($variantPlan['import'] === []) {
            $message = sprintf(
                __('%s already exists in the library for the selected variants.', ETCH_FONTS_TEXT_DOMAIN),
                $familyName
            );

            $this->log->add($message);

            return [
                'status' => 'skipped',
                'message' => $message,
                'family' => $familyName,
                'faces' => 0,
                'files' => 0,
                'variants' => $variants,
                'imported_variants' => [],
                'skipped_variants' => $variantPlan['skipped'],
            ];
        }

        $metadata = $this->client->getFamily($familyName);
        $css = $this->client->fetchCss($familyName, $variantPlan['import']);

        if (is_wp_error($css)) {
            return $this->error($css->get_error_code(), $css->get_error_message());
        }

        $faces = $this->selectPreferredFaces(
            $this->parser->parse($css, $familyName),
            $variantPlan['import']
        );

        if ($faces === []) {
            return $this->error(
                'etch_fonts_google_no_faces',
                __('No downloadable WOFF2 faces were returned for that family.', ETCH_FONTS_TEXT_DOMAIN)
            );
        }

        $target = $this->resolveImportTarget($familyName);

        if (is_wp_error($target)) {
            return $target;
        }

        $familyDirectory = $target['directory'];
        $provider = $this->buildProviderMetadata($metadata, $variantPlan['import']);
        $newManifestFaces = [];
        $downloadedFiles = 0;

        foreach ($faces as $face) {
            $manifestFace = $this->buildManifestFace(
                $familyName,
                $familySlug,
                $familyDirectory,
                $face,
                $provider,
                $downloadedFiles
            );

            if (is_wp_error($manifestFace)) {
                return $manifestFace;
            }

            if ($manifestFace !== null) {
                $newManifestFaces[] = $manifestFace;
            }
        }

        if ($newManifestFaces === []) {
            return $this->error(
                'etch_fonts_google_empty_manifest',
                __('No local font files were saved from that import.', ETCH_FONTS_TEXT_DOMAIN)
            );
        }

        $mergedFaces = $this->mergeManifestFaces(
            is_array($existingImport['faces'] ?? null) ? (array) $existingImport['faces'] : [],
            $newManifestFaces
        );
        $allVariants = array_values(
            array_unique(
                array_merge(
                    is_array($existingImport['variants'] ?? null) ? (array) $existingImport['variants'] : [],
                    $variantPlan['import']
                )
            )
        );

        $this->imports->upsert(
            [
                'family' => $familyName,
                'slug' => $familySlug,
                'provider' => 'google',
                'category' => (string) ($metadata['category'] ?? ''),
                'variants' => $allVariants,
                'imported_at' => current_time('mysql'),
                'faces' => $mergedFaces,
            ]
        );

        $this->assets->refreshGeneratedAssets();

        $faceCount = count($newManifestFaces);
        $skipCount = count($variantPlan['skipped']);
        $message = sprintf(
            __('Imported %1$s (%2$d variant%3$s, %4$d file%5$s).', ETCH_FONTS_TEXT_DOMAIN),
            $familyName,
            $faceCount,
            $faceCount === 1 ? '' : 's',
            $downloadedFiles,
            $downloadedFiles === 1 ? '' : 's'
        );

        if ($skipCount > 0) {
            $message .= ' ' . sprintf(
                __('%d variant%s already existed.', ETCH_FONTS_TEXT_DOMAIN),
                $skipCount,
                $skipCount === 1 ? '' : 's'
            );
        }

        $this->log->add($message);

        return [
            'status' => 'imported',
            'message' => $message,
            'family' => $familyName,
            'faces' => $faceCount,
            'files' => $downloadedFiles,
            'variants' => $allVariants,
            'imported_variants' => $variantPlan['import'],
            'skipped_variants' => $variantPlan['skipped'],
        ];
    }

    private function resolveImportTarget(string $familyName): array|WP_Error
    {
        if (!$this->storage->ensureRootDirectory()) {
            return $this->error(
                'etch_fonts_storage_unavailable',
                __('The uploads/fonts storage directory could not be created.', ETCH_FONTS_TEXT_DOMAIN)
            );
        }

        $googleRoot = $this->storage->getGoogleRoot();

        if (!$googleRoot) {
            return $this->error(
                'etch_fonts_storage_unavailable',
                __('The uploads/fonts storage directory could not be created.', ETCH_FONTS_TEXT_DOMAIN)
            );
        }

        $familySlug = FontUtils::slugify($familyName);
        $familyDirectory = trailingslashit($googleRoot) . $familySlug;

        if (!$this->storage->ensureDirectory($familyDirectory)) {
            return $this->error(
                'etch_fonts_google_family_dir_failed',
                __('The Google Fonts import directory could not be created.', ETCH_FONTS_TEXT_DOMAIN)
            );
        }

        return [
            'slug' => $familySlug,
            'directory' => $familyDirectory,
        ];
    }

    private function buildLocalFilename(string $familyName, array $face): string
    {
        $weight = preg_replace('/[^0-9]+/', '-', (string) ($face['weight'] ?? '400')) ?: '400';
        $style = FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal'));

        return implode(
            '-',
            [
                FontUtils::slugify($familyName),
                trim($weight, '-'),
                $style,
            ]
        ) . '.woff2';
    }

    private function buildManifestFace(
        string $familyName,
        string $familySlug,
        string $familyDirectory,
        array $face,
        array $provider,
        int &$downloadedFiles
    ): array|WP_Error|null {
        $relativeFiles = [];

        foreach ((array) $face['files'] as $format => $url) {
            if ($format !== 'woff2') {
                continue;
            }

            $filename = $this->buildLocalFilename($familyName, $face);
            $absolutePath = wp_normalize_path(trailingslashit($familyDirectory) . $filename);
            $relativePath = $this->storage->relativePath($absolutePath);

            if (is_file($absolutePath) && filesize($absolutePath) > 0) {
                $relativeFiles[$format] = $relativePath;
                continue;
            }

            $download = $this->downloadFontFile((string) $url, $absolutePath);

            if (is_wp_error($download)) {
                return $download;
            }

            $relativeFiles[$format] = $relativePath;
            $downloadedFiles++;
        }

        if ($relativeFiles === []) {
            return null;
        }

        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'source' => 'google',
            'weight' => (string) $face['weight'],
            'style' => (string) $face['style'],
            'unicode_range' => (string) ($face['unicode_range'] ?? ''),
            'files' => $relativeFiles,
            'provider' => $provider,
        ];
    }

    private function downloadFontFile(string $url, string $targetPath): bool|WP_Error
    {
        $validated = $this->validateRemoteFontUrl($url);

        if (is_wp_error($validated)) {
            return $validated;
        }

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 30,
                'headers' => [
                    'Accept' => 'font/woff2,*/*;q=0.1',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                ],
            ]
        );

        if (is_wp_error($response)) {
            return $this->error($response->get_error_code(), $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            return $this->error(
                'etch_fonts_google_download_failed',
                sprintf(__('Font download failed with status %d.', ETCH_FONTS_TEXT_DOMAIN), $status)
            );
        }

        $body = wp_remote_retrieve_body($response);

        if (!is_string($body) || $body === '') {
            return $this->error(
                'etch_fonts_google_empty_file',
                __('Google Fonts returned an empty font file.', ETCH_FONTS_TEXT_DOMAIN)
            );
        }

        if (strlen($body) > self::MAX_FONT_FILE_BYTES) {
            return $this->error(
                'etch_fonts_google_file_too_large',
                __('The downloaded font file exceeded the safety size limit.', ETCH_FONTS_TEXT_DOMAIN)
            );
        }

        $contentType = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));

        if (
            $contentType !== ''
            && !str_contains($contentType, 'woff2')
            && !str_contains($contentType, 'font')
            && !str_contains($contentType, 'octet-stream')
        ) {
            return $this->error(
                'etch_fonts_google_invalid_type',
                __('The downloaded file was not returned as a WOFF2 font.', ETCH_FONTS_TEXT_DOMAIN)
            );
        }

        if (!$this->storage->writeAbsoluteFile($targetPath, $body)) {
            return $this->error(
                'etch_fonts_google_write_failed',
                __('The imported font file could not be written to uploads/fonts.', ETCH_FONTS_TEXT_DOMAIN)
            );
        }

        return true;
    }

    private function validateRemoteFontUrl(string $url): bool|WP_Error
    {
        $parts = wp_parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));

        if ($host !== 'fonts.gstatic.com') {
            return $this->error(
                'etch_fonts_google_invalid_host',
                __('Google font downloads must come from fonts.gstatic.com.', ETCH_FONTS_TEXT_DOMAIN)
            );
        }

        if (!str_ends_with($path, '.woff2')) {
            return $this->error(
                'etch_fonts_google_invalid_extension',
                __('Only WOFF2 files can be imported from Google Fonts.', ETCH_FONTS_TEXT_DOMAIN)
            );
        }

        return true;
    }

    private function error(string $code, string $message): WP_Error
    {
        $this->log->add($message);

        return new WP_Error($code, $message);
    }

    private function buildProviderMetadata(?array $metadata, array $variants): array
    {
        return [
            'type' => 'google',
            'category' => (string) ($metadata['category'] ?? ''),
            'variants' => $variants,
            'lastModified' => (string) ($metadata['lastModified'] ?? ''),
            'version' => (string) ($metadata['version'] ?? ''),
        ];
    }

    private function findCatalogFamily(string $familyName, string $familySlug): ?array
    {
        foreach ($this->catalog->getCatalog() as $family) {
            $catalogName = (string) ($family['family'] ?? '');
            $catalogSlug = (string) ($family['slug'] ?? '');

            if ($catalogSlug === $familySlug || strcasecmp($catalogName, $familyName) === 0) {
                return $family;
            }
        }

        return null;
    }

    private function buildVariantPlan(array $requestedVariants, ?array $existingImport): array
    {
        $existingKeys = [];

        foreach ((array) ($existingImport['faces'] ?? []) as $face) {
            if (!is_array($face)) {
                continue;
            }

            $existingKeys[$this->faceKeyFromFace($face)] = true;
        }

        $toImport = [];
        $skipped = [];

        foreach ($requestedVariants as $variant) {
            $faceKey = $this->faceKeyFromVariant($variant);

            if ($faceKey === null) {
                continue;
            }

            if (isset($existingKeys[$faceKey])) {
                $skipped[] = $variant;
                continue;
            }

            $toImport[] = $variant;
        }

        return [
            'import' => array_values(array_unique($toImport)),
            'skipped' => array_values(array_unique($skipped)),
        ];
    }

    private function selectPreferredFaces(array $faces, array $requestedVariants): array
    {
        $allowedKeys = [];

        foreach ($requestedVariants as $variant) {
            $faceKey = $this->faceKeyFromVariant($variant);

            if ($faceKey !== null) {
                $allowedKeys[$faceKey] = true;
            }
        }

        $selected = [];

        foreach ($faces as $face) {
            if (!is_array($face)) {
                continue;
            }

            $faceKey = $this->faceKeyFromFace($face);

            if ($allowedKeys !== [] && !isset($allowedKeys[$faceKey])) {
                continue;
            }

            if (!isset($selected[$faceKey]) || $this->preferredFaceScore($face) >= $this->preferredFaceScore($selected[$faceKey])) {
                $selected[$faceKey] = $face;
            }
        }

        uasort($selected, [FontUtils::class, 'compareFacesByWeightAndStyle']);

        return array_values($selected);
    }

    private function mergeManifestFaces(array $existingFaces, array $newFaces): array
    {
        $merged = [];

        foreach ($existingFaces as $face) {
            if (!is_array($face)) {
                continue;
            }

            $merged[$this->faceKeyFromFace($face)] = $face;
        }

        foreach ($newFaces as $face) {
            $merged[$this->faceKeyFromFace($face)] = $face;
        }

        uasort($merged, [FontUtils::class, 'compareFacesByWeightAndStyle']);

        return array_values($merged);
    }

    private function faceKeyFromVariant(string $variant): ?string
    {
        $axis = FontUtils::googleVariantToAxis($variant);

        if ($axis === null) {
            return null;
        }

        return FontUtils::faceAxisKey($axis['weight'], $axis['style']);
    }

    private function faceKeyFromFace(array $face): string
    {
        return FontUtils::faceAxisKey(
            (string) ($face['weight'] ?? '400'),
            (string) ($face['style'] ?? 'normal')
        );
    }

    private function preferredFaceScore(array $face): int
    {
        $range = strtoupper((string) ($face['unicode_range'] ?? ''));

        if ($range === '') {
            return 1000;
        }

        $score = 0;

        if (str_contains($range, 'U+0000-00FF')) {
            $score += 500;
        }

        if (str_contains($range, 'U+0100-024F')) {
            $score += 220;
        }

        if (str_contains($range, 'U+1E00-1EFF')) {
            $score += 80;
        }

        if (str_contains($range, 'U+20AC')) {
            $score += 30;
        }

        $score -= min(strlen($range), 300);

        return $score;
    }
}
