<?php

declare(strict_types=1);

namespace EtchFonts\Fonts;

use EtchFonts\Repository\ImportRepository;
use EtchFonts\Repository\LogRepository;
use EtchFonts\Support\FontUtils;
use EtchFonts\Support\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CatalogService
{
    private const TRANSIENT_CATALOG = 'etch_fonts_catalog_v2';
    private const LOCAL_FORMATS = ['eot', 'woff2', 'woff', 'ttf', 'otf', 'svg'];
    private const DEFAULT_COUNTS = [
        'families' => 0,
        'files' => 0,
        'local_families' => 0,
        'google_families' => 0,
    ];

    private ?array $catalog = null;
    private array $counts = self::DEFAULT_COUNTS;

    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly FontFilenameParser $parser,
        private readonly LogRepository $log
    ) {
    }

    public function getCatalog(): array
    {
        if (is_array($this->catalog)) {
            return $this->catalog;
        }

        if ($this->hydrateFromCache(get_transient(self::TRANSIENT_CATALOG))) {
            return $this->catalog;
        }

        $built = $this->buildCatalog();
        $this->catalog = $built['families'];
        $this->counts = $built['counts'];
        $this->cacheCatalogState();

        return $this->catalog;
    }

    public function getCounts(): array
    {
        $this->getCatalog();

        return $this->counts;
    }

    public function invalidate(): void
    {
        delete_transient(self::TRANSIENT_CATALOG);
        $this->catalog = null;
        $this->counts = self::DEFAULT_COUNTS;
    }

    public function maybeInvalidateFromAttachment(int $attachmentId): void
    {
        $file = get_attached_file($attachmentId);

        if (!$file || !$this->storage->isWithinRoot($file)) {
            return;
        }

        $this->invalidate();
        $this->log->add(__('Font attachment changed. Catalog cache cleared.', ETCH_FONTS_TEXT_DOMAIN));
    }

    private function buildCatalog(): array
    {
        $families = [];
        $fileCount = 0;

        foreach ($this->scanLocalFamilies() as $family) {
            $this->mergeFamily($families, $family);
            $fileCount += $this->countFamilyFiles($family);
        }

        foreach ($this->loadImportedFamilies() as $family) {
            $this->mergeFamily($families, $family);
            $fileCount += $this->countFamilyFiles($family);
        }

        ksort($families, SORT_NATURAL | SORT_FLAG_CASE);

        $counts = [
            'families' => count($families),
            'files' => $fileCount,
            'local_families' => 0,
            'google_families' => 0,
        ];

        foreach ($families as &$family) {
            $this->sortFaces($family['faces']);

            if (in_array('local', $family['sources'], true)) {
                $counts['local_families']++;
            }

            if (in_array('google', $family['sources'], true)) {
                $counts['google_families']++;
            }
        }
        unset($family);

        return [
            'families' => $families,
            'counts' => $counts,
        ];
    }

    private function scanLocalFamilies(): array
    {
        $root = $this->storage->getRoot();
        $googleRootPrefix = $this->getGoogleRootPrefix();

        if (!$root || !is_dir($root) || !is_readable($root)) {
            return [];
        }

        $byFamily = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::UNIX_PATHS
            )
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$this->isScannableLocalFile($file, $googleRootPrefix)) {
                continue;
            }

            $this->addLocalFileToFamilies($byFamily, $file);
        }

        foreach ($byFamily as &$family) {
            $family['faces'] = array_values($family['faces']);
        }
        unset($family);

        return array_values($byFamily);
    }

    private function loadImportedFamilies(): array
    {
        $imports = $this->imports->all();
        $families = [];

        foreach ($imports as $import) {
            if (
                !is_array($import)
                || empty($import['family'])
                || empty($import['faces'])
                || !is_array($import['faces'])
            ) {
                continue;
            }

            $familyName = (string) $import['family'];
            $familySlug = is_string($import['slug'] ?? null) ? $import['slug'] : FontUtils::slugify($familyName);
            $faces = [];

            foreach ($import['faces'] as $face) {
                if (!is_array($face)) {
                    continue;
                }

                $hydrated = $this->hydrateImportedFace($familyName, $familySlug, $face);

                if ($hydrated !== null) {
                    $faces[] = $hydrated;
                }
            }

            if ($faces === []) {
                continue;
            }

            $families[] = [
                'family' => $familyName,
                'slug' => $familySlug,
                'faces' => $faces,
                'sources' => ['google'],
            ];
        }

        return $families;
    }

    private function hydrateImportedFace(string $familyName, string $familySlug, array $face): ?array
    {
        $files = [];
        $paths = [];

        foreach ((array) ($face['files'] ?? []) as $format => $relativePath) {
            if (!is_string($format) || !is_string($relativePath) || $relativePath === '') {
                continue;
            }

            $url = $this->storage->urlForRelativePath($relativePath);

            if (!$url) {
                continue;
            }

            $files[strtolower($format)] = $url;
            $paths[strtolower($format)] = $relativePath;
        }

        if ($files === []) {
            return null;
        }

        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'source' => 'google',
            'weight' => FontUtils::normalizeWeight((string) ($face['weight'] ?? '400')),
            'style' => FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal')),
            'unicode_range' => trim((string) ($face['unicode_range'] ?? '')),
            'files' => $files,
            'paths' => $paths,
            'provider' => is_array($face['provider'] ?? null) ? $face['provider'] : [],
        ];
    }

    private function mergeFamily(array &$catalog, array $family): void
    {
        $familyName = $family['family'];

        if (!isset($catalog[$familyName])) {
            $catalog[$familyName] = $family;
            $catalog[$familyName]['sources'] = array_values(array_unique($family['sources']));

            return;
        }

        $catalog[$familyName]['sources'] = array_values(
            array_unique(array_merge($catalog[$familyName]['sources'], $family['sources']))
        );

        foreach ($family['faces'] as $face) {
            $this->mergeFaceIntoFamily($catalog[$familyName]['faces'], $face);
        }
    }

    private function sortFaces(array &$faces): void
    {
        usort(
            $faces,
            static function (array $left, array $right): int {
                $comparison = FontUtils::compareFacesByWeightAndStyle($left, $right);

                if ($comparison !== 0) {
                    return $comparison;
                }

                return strcmp((string) ($left['unicode_range'] ?? ''), (string) ($right['unicode_range'] ?? ''));
            }
        );
    }

    private function countFamilyFiles(array $family): int
    {
        $count = 0;

        foreach ((array) ($family['faces'] ?? []) as $face) {
            $count += count((array) ($face['files'] ?? []));
        }

        return $count;
    }

    private function hydrateFromCache(mixed $cached): bool
    {
        if (
            !is_array($cached)
            || !isset($cached['families'], $cached['counts'])
            || !is_array($cached['families'])
            || !is_array($cached['counts'])
        ) {
            return false;
        }

        $this->catalog = $cached['families'];
        $this->counts = $cached['counts'];

        return true;
    }

    private function cacheCatalogState(): void
    {
        set_transient(
            self::TRANSIENT_CATALOG,
            [
                'families' => $this->catalog,
                'counts' => $this->counts,
            ],
            DAY_IN_SECONDS
        );
    }

    private function getGoogleRootPrefix(): string
    {
        $storage = $this->storage->get();
        $googleRoot = is_array($storage) ? ($storage['google_dir'] ?? null) : null;

        return is_string($googleRoot) ? trailingslashit(wp_normalize_path($googleRoot)) : '';
    }

    private function isScannableLocalFile(SplFileInfo $file, string $googleRootPrefix): bool
    {
        if (!$file->isFile()) {
            return false;
        }

        $absolutePath = wp_normalize_path($file->getPathname());

        if ($googleRootPrefix !== '' && str_starts_with($absolutePath, $googleRootPrefix)) {
            return false;
        }

        return in_array(strtolower($file->getExtension()), self::LOCAL_FORMATS, true);
    }

    private function addLocalFileToFamilies(array &$byFamily, SplFileInfo $file): void
    {
        $extension = strtolower($file->getExtension());
        $meta = $this->parser->parse($file->getBasename('.' . $extension));

        if ($meta['is_variable']) {
            return;
        }

        $familyName = $meta['family'];
        $familySlug = FontUtils::slugify($familyName);
        $variantKey = FontUtils::variantKey($meta['weight'], $meta['style']);

        if (!isset($byFamily[$familyName])) {
            $byFamily[$familyName] = [
                'family' => $familyName,
                'slug' => $familySlug,
                'faces' => [],
                'sources' => ['local'],
            ];
        }

        if (!isset($byFamily[$familyName]['faces'][$variantKey])) {
            $byFamily[$familyName]['faces'][$variantKey] = $this->buildLocalFace($familyName, $familySlug, $meta);
        }

        $absolutePath = wp_normalize_path($file->getPathname());
        $relativePath = $this->storage->relativePath($absolutePath);
        $url = $this->storage->urlForRelativePath($relativePath);

        if (!$url) {
            return;
        }

        $byFamily[$familyName]['faces'][$variantKey]['files'][$extension] = $url;
        $byFamily[$familyName]['faces'][$variantKey]['paths'][$extension] = $relativePath;
    }

    private function buildLocalFace(string $familyName, string $familySlug, array $meta): array
    {
        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'source' => 'local',
            'weight' => FontUtils::normalizeWeight((string) $meta['weight']),
            'style' => FontUtils::normalizeStyle((string) $meta['style']),
            'unicode_range' => '',
            'files' => [],
            'paths' => [],
            'provider' => [],
        ];
    }

    private function mergeFaceIntoFamily(array &$faces, array $face): void
    {
        $faceKey = $this->faceIdentityKey($face);

        foreach ($faces as &$existing) {
            if ($this->faceIdentityKey($existing) !== $faceKey) {
                continue;
            }

            if (($existing['source'] ?? 'local') === 'google' && ($face['source'] ?? 'local') === 'google') {
                $existing['files'] = $face['files'];
                $existing['paths'] = $face['paths'];
                $existing['unicode_range'] = trim((string) ($face['unicode_range'] ?? ''));
                $existing['provider'] = is_array($face['provider'] ?? null) ? $face['provider'] : [];
                return;
            }

            $existing['files'] = array_merge($existing['files'], $face['files']);
            $existing['paths'] = array_merge($existing['paths'], $face['paths']);
            return;
        }
        unset($existing);

        $faces[] = $face;
    }

    private function faceIdentityKey(array $face): string
    {
        $source = (string) ($face['source'] ?? 'local');
        $unicodeRange = $source === 'google' ? '' : (string) ($face['unicode_range'] ?? '');

        return FontUtils::variantKey(
            (string) ($face['weight'] ?? '400'),
            (string) ($face['style'] ?? 'normal'),
            $unicodeRange
        ) . '|' . $source;
    }
}
