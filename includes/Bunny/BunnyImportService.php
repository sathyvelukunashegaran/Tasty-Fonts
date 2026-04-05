<?php

declare(strict_types=1);

namespace TastyFonts\Bunny;

use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\HostedImportSupport;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

final class BunnyImportService
{
    private const MAX_FONT_FILE_BYTES = 10 * MB_IN_BYTES;

    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly BunnyFontsClient $client,
        private readonly BunnyCssParser $parser,
        private readonly CatalogService $catalog,
        private readonly AssetService $assets,
        private readonly LogRepository $log
    ) {
    }

    public function importFamily(string $familyName, array $variants): array|WP_Error
    {
        $familyName = trim(wp_strip_all_tags($familyName));

        if ($familyName === '') {
            return $this->error('tasty_fonts_missing_family', __('Choose a Bunny Fonts family before importing.', 'tasty-fonts'));
        }

        $familySlug = FontUtils::slugify($familyName);
        $normalizedVariants = FontUtils::normalizeVariantTokens($variants);
        $requestedVariants = $normalizedVariants === [] ? ['regular'] : $normalizedVariants;
        $existingCatalogFamily = $this->findCatalogFamily($familyName, $familySlug);
        $existingImport = $this->imports->get($familySlug);

        if ($existingCatalogFamily !== null && !in_array('bunny', (array) ($existingCatalogFamily['sources'] ?? []), true)) {
            return $this->error(
                'tasty_fonts_family_already_exists',
                sprintf(
                    __('%s already exists in the library as another source. Remove or rename the existing family before importing it from Bunny Fonts.', 'tasty-fonts'),
                    $familyName
                )
            );
        }

        $variantPlan = $this->buildVariantPlan($requestedVariants, $existingImport);

        if ($variantPlan['import'] === []) {
            $message = sprintf(
                __('%s already exists in the library for the selected variants.', 'tasty-fonts'),
                $familyName
            );

            $this->log->add($message);

            return [
                'status' => 'skipped',
                'message' => $message,
                'family' => $familyName,
                'faces' => 0,
                'files' => 0,
                'variants' => $requestedVariants,
                'imported_variants' => [],
                'skipped_variants' => $variantPlan['skipped'],
            ];
        }

        $css = $this->client->fetchCss($familyName, $variantPlan['import']);

        if (is_wp_error($css)) {
            return $this->error($css->get_error_code(), $css->get_error_message());
        }

        $faces = HostedImportSupport::selectPreferredFaces(
            $this->parser->parse($css, $familyName),
            $variantPlan['import']
        );

        if ($faces === []) {
            return $this->error(
                'tasty_fonts_bunny_no_faces',
                __('No downloadable WOFF2 faces were returned for that family.', 'tasty-fonts')
            );
        }

        $familyDirectory = $this->resolveImportTarget($familySlug);

        if (is_wp_error($familyDirectory)) {
            return $familyDirectory;
        }

        $provider = $this->buildProviderMetadata($variantPlan['import']);
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
                'tasty_fonts_bunny_empty_manifest',
                __('No local font files were saved from that import.', 'tasty-fonts')
            );
        }

        $mergedFaces = HostedImportSupport::mergeManifestFaces(
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
                'provider' => 'bunny',
                'category' => '',
                'variants' => $allVariants,
                'imported_at' => current_time('mysql'),
                'faces' => $mergedFaces,
            ]
        );

        $this->assets->refreshGeneratedAssets();

        $faceCount = count($newManifestFaces);
        $skipCount = count($variantPlan['skipped']);
        $message = sprintf(
            __('Imported %1$s (%2$d variant%3$s, %4$d file%5$s).', 'tasty-fonts'),
            $familyName,
            $faceCount,
            $faceCount === 1 ? '' : 's',
            $downloadedFiles,
            $downloadedFiles === 1 ? '' : 's'
        );

        if ($skipCount > 0) {
            $message .= ' ' . sprintf(
                __('%d variant%s already existed.', 'tasty-fonts'),
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

    private function resolveImportTarget(string $familySlug): string|WP_Error
    {
        $bunnyRoot = $this->getBunnyRootDirectory();

        if (is_wp_error($bunnyRoot)) {
            return $bunnyRoot;
        }

        $familyDirectory = trailingslashit($bunnyRoot) . $familySlug;

        if (!$this->storage->ensureDirectory($familyDirectory)) {
            return $this->error(
                'tasty_fonts_bunny_family_dir_failed',
                __('The Bunny Fonts import directory could not be created.', 'tasty-fonts')
            );
        }

        return $familyDirectory;
    }

    private function getBunnyRootDirectory(): string|WP_Error
    {
        if (!$this->storage->ensureRootDirectory()) {
            return $this->error(
                'tasty_fonts_storage_unavailable',
                __('The uploads/fonts storage directory could not be created.', 'tasty-fonts')
            );
        }

        $bunnyRoot = $this->storage->getProviderRoot('bunny');

        if (!$bunnyRoot) {
            return $this->error(
                'tasty_fonts_storage_unavailable',
                __('The uploads/fonts storage directory could not be created.', 'tasty-fonts')
            );
        }

        return $bunnyRoot;
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

            $filename = HostedImportSupport::buildLocalFilename($familyName, $face);
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
            'source' => 'bunny',
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
                    'User-Agent' => FontUtils::MODERN_USER_AGENT,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return $this->error($response->get_error_code(), $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            return $this->error(
                'tasty_fonts_bunny_download_failed',
                sprintf(__('Font download failed with status %d.', 'tasty-fonts'), $status)
            );
        }

        $body = wp_remote_retrieve_body($response);

        if (!is_string($body) || $body === '') {
            return $this->error(
                'tasty_fonts_bunny_empty_file',
                __('Bunny Fonts returned an empty font file.', 'tasty-fonts')
            );
        }

        if (strlen($body) > self::MAX_FONT_FILE_BYTES) {
            return $this->error(
                'tasty_fonts_bunny_file_too_large',
                __('The downloaded font file exceeded the safety size limit.', 'tasty-fonts')
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
                'tasty_fonts_bunny_invalid_type',
                __('The downloaded file was not returned as a WOFF2 font.', 'tasty-fonts')
            );
        }

        if (!$this->storage->writeAbsoluteFile($targetPath, $body)) {
            return $this->error(
                'tasty_fonts_bunny_write_failed',
                __('The imported font file could not be written to uploads/fonts.', 'tasty-fonts')
            );
        }

        return true;
    }

    private function validateRemoteFontUrl(string $url): bool|WP_Error
    {
        $parts = wp_parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));

        if ($host !== 'fonts.bunny.net') {
            return $this->error(
                'tasty_fonts_bunny_invalid_host',
                __('Bunny font downloads must come from fonts.bunny.net.', 'tasty-fonts')
            );
        }

        if (!str_ends_with($path, '.woff2')) {
            return $this->error(
                'tasty_fonts_bunny_invalid_extension',
                __('Only WOFF2 files can be imported from Bunny Fonts.', 'tasty-fonts')
            );
        }

        return true;
    }

    private function buildProviderMetadata(array $variants): array
    {
        return [
            'type' => 'bunny',
            'variants' => $variants,
        ];
    }

    private function buildVariantPlan(array $requestedVariants, ?array $existingImport): array
    {
        $existingKeys = [];

        foreach ((array) ($existingImport['faces'] ?? []) as $face) {
            if (!is_array($face)) {
                continue;
            }

            $existingKeys[HostedImportSupport::faceKeyFromFace($face)] = true;
        }

        $toImport = [];
        $skipped = [];

        foreach ($requestedVariants as $variant) {
            $faceKey = HostedImportSupport::faceKeyFromVariant((string) $variant);

            if ($faceKey === null) {
                continue;
            }

            if (isset($existingKeys[$faceKey])) {
                $skipped[] = (string) $variant;
                continue;
            }

            $toImport[] = (string) $variant;
        }

        return [
            'import' => array_values(array_unique($toImport)),
            'skipped' => array_values(array_unique($skipped)),
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

    private function error(string $code, string $message): WP_Error
    {
        $this->log->add($message);

        return new WP_Error($code, $message);
    }
}
