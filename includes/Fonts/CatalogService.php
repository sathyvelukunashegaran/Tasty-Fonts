<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CatalogService
{
    private const TRANSIENT_CATALOG = 'tasty_fonts_catalog_v2';
    private const LOCAL_FORMATS = ['woff2', 'woff', 'ttf', 'otf'];
    private const IMPORTED_SOURCES = ['google', 'bunny'];
    private const DEFAULT_COUNTS = [
        'families' => 0,
        'files' => 0,
        'published_families' => 0,
        'library_only_families' => 0,
        'local_families' => 0,
        'remote_families' => 0,
    ];

    private ?array $catalog = null;
    private array $counts = self::DEFAULT_COUNTS;

    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly FontFilenameParser $parser,
        private readonly LogRepository $log,
        private readonly AdobeProjectClient $adobe
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
        $this->log->add(__('Font attachment changed. Catalog cache cleared.', 'tasty-fonts'));
    }

    private function buildCatalog(): array
    {
        $families = [];

        foreach ($this->imports->allFamilies() as $family) {
            if (!is_array($family)) {
                continue;
            }

            $familyName = (string) ($family['family'] ?? '');

            if ($familyName === '') {
                continue;
            }

            $families[$familyName] = $this->normalizeFamilyRecord($family);
        }

        foreach ($this->scanLocalFamilies() as $family) {
            $this->mergeSyntheticFamily($families, $family, 'published');
        }

        foreach ($this->loadAdobeFamilies() as $family) {
            $this->mergeSyntheticFamily($families, $family, 'published');
        }

        ksort($families, SORT_NATURAL | SORT_FLAG_CASE);

        $counts = self::DEFAULT_COUNTS;

        foreach ($families as &$family) {
            $family = $this->finalizeFamily($family);
            $counts['families']++;
            $counts['files'] += $this->countFamilyFiles($family);

            if (($family['publish_state'] ?? 'published') === 'library_only') {
                $counts['library_only_families']++;
            } else {
                $counts['published_families']++;
            }

            $activeDelivery = is_array($family['active_delivery'] ?? null) ? $family['active_delivery'] : [];
            $activeType = strtolower(trim((string) ($activeDelivery['type'] ?? '')));

            if ($activeType === 'self_hosted') {
                $counts['local_families']++;
            } else {
                $counts['remote_families']++;
            }
        }
        unset($family);

        return [
            'families' => $families,
            'counts' => $counts,
        ];
    }

    private function normalizeFamilyRecord(array $family): array
    {
        $familyName = (string) ($family['family'] ?? '');
        $familySlug = (string) ($family['slug'] ?? FontUtils::slugify($familyName));
        $publishState = (string) ($family['publish_state'] ?? 'published');
        $activeDeliveryId = (string) ($family['active_delivery_id'] ?? '');
        $deliveryProfiles = is_array($family['delivery_profiles'] ?? null) ? $family['delivery_profiles'] : [];

        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'publish_state' => in_array($publishState, ['library_only', 'published', 'role_active'], true) ? $publishState : 'published',
            'active_delivery_id' => $activeDeliveryId,
            'delivery_profiles' => $deliveryProfiles,
        ];
    }

    private function finalizeFamily(array $family): array
    {
        $profiles = [];

        foreach ((array) ($family['delivery_profiles'] ?? []) as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $profiles[(string) ($profile['id'] ?? '')] = $this->hydrateDeliveryProfile($family, $profile);
        }

        $activeDeliveryId = (string) ($family['active_delivery_id'] ?? '');

        if ($activeDeliveryId === '' || !isset($profiles[$activeDeliveryId])) {
            $activeDeliveryId = $profiles !== [] ? (string) array_key_first($profiles) : '';
        }

        $activeDelivery = ($activeDeliveryId !== '' && isset($profiles[$activeDeliveryId])) ? $profiles[$activeDeliveryId] : [];
        $availableDeliveries = array_values($profiles);
        usort($availableDeliveries, [$this, 'compareDeliveryProfiles']);

        $sources = array_values(
            array_unique(
                array_filter(
                    array_map(static fn (array $profile): string => strtolower(trim((string) ($profile['provider'] ?? ''))), $availableDeliveries),
                    'strlen'
                )
            )
        );

        return [
            'family' => (string) ($family['family'] ?? ''),
            'slug' => (string) ($family['slug'] ?? FontUtils::slugify((string) ($family['family'] ?? ''))),
            'publish_state' => (string) ($family['publish_state'] ?? 'published'),
            'active_delivery_id' => $activeDeliveryId,
            'active_delivery' => $activeDelivery,
            'available_deliveries' => $availableDeliveries,
            'delivery_profiles' => $profiles,
            'sources' => $sources,
            'faces' => is_array($activeDelivery['faces'] ?? null) ? $activeDelivery['faces'] : [],
            'delivery_badges' => $this->buildDeliveryBadges($family, $activeDelivery),
            'delivery_filter_tokens' => $this->buildDeliveryFilterTokens($family, $activeDelivery),
        ];
    }

    private function hydrateDeliveryProfile(array $family, array $profile): array
    {
        $provider = strtolower(trim((string) ($profile['provider'] ?? 'local')));
        $type = strtolower(trim((string) ($profile['type'] ?? 'self_hosted')));
        $faces = [];

        foreach ((array) ($profile['faces'] ?? []) as $face) {
            if (!is_array($face)) {
                continue;
            }

            $faces[] = $this->hydrateFace(
                (string) ($family['family'] ?? ''),
                (string) ($family['slug'] ?? ''),
                $provider,
                $type,
                $face
            );
        }

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

        return [
            'id' => (string) ($profile['id'] ?? ''),
            'provider' => $provider,
            'type' => $type,
            'label' => (string) ($profile['label'] ?? ''),
            'variants' => (array) ($profile['variants'] ?? []),
            'faces' => $faces,
            'meta' => is_array($profile['meta'] ?? null) ? $profile['meta'] : [],
        ];
    }

    private function hydrateFace(string $familyName, string $familySlug, string $provider, string $type, array $face): array
    {
        $files = [];
        $paths = [];

        foreach ((array) ($face['files'] ?? []) as $format => $value) {
            if (!is_string($format) || !is_string($value) || trim($value) === '') {
                continue;
            }

            $normalizedFormat = strtolower(trim($format));
            $normalizedValue = trim($value);
            $paths[$normalizedFormat] = $normalizedValue;

            if ($type === 'self_hosted' && !$this->isRemoteUrl($normalizedValue)) {
                $relativePath = $normalizedValue;
                $url = $this->storage->urlForRelativePath($relativePath);

                if ($url === null) {
                    continue;
                }

                $files[$normalizedFormat] = $url;
                continue;
            }

            $files[$normalizedFormat] = $normalizedValue;

            if ($this->isRemoteUrl($normalizedValue)) {
                unset($paths[$normalizedFormat]);
            }
        }

        foreach ((array) ($face['paths'] ?? []) as $format => $value) {
            if (!is_string($format) || !is_string($value) || trim($value) === '') {
                continue;
            }

            $normalizedFormat = strtolower(trim($format));
            $paths[$normalizedFormat] = trim($value);

            if (!isset($files[$normalizedFormat])) {
                $url = $this->storage->urlForRelativePath($paths[$normalizedFormat]);

                if ($url !== null) {
                    $files[$normalizedFormat] = $url;
                }
            }
        }

        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'source' => $provider,
            'weight' => FontUtils::normalizeWeight((string) ($face['weight'] ?? '400')),
            'style' => FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal')),
            'unicode_range' => trim((string) ($face['unicode_range'] ?? '')),
            'files' => $files,
            'paths' => $paths,
            'provider' => is_array($face['provider'] ?? null) ? $face['provider'] : [],
        ];
    }

    private function mergeSyntheticFamily(array &$families, array $synthetic, string $defaultPublishState): void
    {
        $familyName = (string) ($synthetic['family'] ?? '');
        $familySlug = (string) ($synthetic['slug'] ?? FontUtils::slugify($familyName));

        if ($familyName === '' || $familySlug === '') {
            return;
        }

        if (!isset($families[$familyName])) {
            $families[$familyName] = [
                'family' => $familyName,
                'slug' => $familySlug,
                'publish_state' => $defaultPublishState,
                'active_delivery_id' => (string) ($synthetic['active_delivery_id'] ?? ''),
                'delivery_profiles' => (array) ($synthetic['delivery_profiles'] ?? []),
            ];

            return;
        }

        $existingProfiles = is_array($families[$familyName]['delivery_profiles'] ?? null)
            ? $families[$familyName]['delivery_profiles']
            : [];

        foreach ((array) ($synthetic['delivery_profiles'] ?? []) as $profileId => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $existingProfiles[(string) $profileId] = $profile;
        }

        $families[$familyName]['delivery_profiles'] = $existingProfiles;

        if (trim((string) ($families[$familyName]['active_delivery_id'] ?? '')) === '') {
            $families[$familyName]['active_delivery_id'] = (string) ($synthetic['active_delivery_id'] ?? '');
        }
    }

    private function scanLocalFamilies(): array
    {
        $root = $this->storage->getRoot();
        $importRootPrefixes = $this->getImportedRootPrefixes();

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
            if (!$file instanceof SplFileInfo || !$this->isScannableLocalFile($file, $importRootPrefixes)) {
                continue;
            }

            $this->addLocalFileToFamilies($byFamily, $file);
        }

        foreach ($byFamily as &$family) {
            $profileId = $this->localDeliveryId();
            $profile = is_array($family['delivery_profiles'][$profileId] ?? null) ? $family['delivery_profiles'][$profileId] : [];
            $profile['faces'] = array_values((array) ($profile['faces'] ?? []));
            $family['delivery_profiles'][$profileId] = $profile;
        }
        unset($family);

        return array_values($byFamily);
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
        $profileId = $this->localDeliveryId();

        if (!isset($byFamily[$familyName])) {
            $byFamily[$familyName] = [
                'family' => $familyName,
                'slug' => $familySlug,
                'publish_state' => 'published',
                'active_delivery_id' => $profileId,
                'delivery_profiles' => [
                    $profileId => [
                        'id' => $profileId,
                        'provider' => 'local',
                        'type' => 'self_hosted',
                        'label' => __('Self-hosted', 'tasty-fonts'),
                        'variants' => [],
                        'faces' => [],
                        'meta' => ['origin' => 'local_scan'],
                    ],
                ],
            ];
        }

        if (!isset($byFamily[$familyName]['delivery_profiles'][$profileId]['faces'][$variantKey])) {
            $byFamily[$familyName]['delivery_profiles'][$profileId]['faces'][$variantKey] = $this->buildLocalFace($familyName, $familySlug, $meta);
        }

        $absolutePath = wp_normalize_path($file->getPathname());
        $relativePath = $this->storage->relativePath($absolutePath);
        $url = $this->storage->urlForRelativePath($relativePath);

        if ($url === null) {
            return;
        }

        $byFamily[$familyName]['delivery_profiles'][$profileId]['faces'][$variantKey]['files'][$extension] = $relativePath;
        $byFamily[$familyName]['delivery_profiles'][$profileId]['faces'][$variantKey]['paths'][$extension] = $relativePath;
        $byFamily[$familyName]['delivery_profiles'][$profileId]['variants'][] = $this->variantTokenFromMeta($meta);
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
            'provider' => ['type' => 'local'],
        ];
    }

    private function loadAdobeFamilies(): array
    {
        $projectState = (string) (($this->adobe->getProjectStatus()['state'] ?? 'empty'));

        if (!$this->adobe->hasProjectId() || !in_array($projectState, ['valid', 'unknown'], true)) {
            return [];
        }

        $projectId = $this->adobe->getProjectId();
        $deliveryId = $this->adobeDeliveryId();
        $families = [];

        foreach ($this->adobe->getConfiguredFamilies() as $family) {
            if (!is_array($family)) {
                continue;
            }

            $familyName = (string) ($family['family'] ?? '');
            $familySlug = (string) ($family['slug'] ?? FontUtils::slugify($familyName));

            if ($familyName === '' || $familySlug === '') {
                continue;
            }

            $faces = [];

            foreach ((array) ($family['faces'] ?? []) as $face) {
                if (!is_array($face)) {
                    continue;
                }

                $faces[] = [
                    'family' => $familyName,
                    'slug' => $familySlug,
                    'source' => 'adobe',
                    'weight' => FontUtils::normalizeWeight((string) ($face['weight'] ?? '400')),
                    'style' => FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal')),
                    'unicode_range' => '',
                    'files' => [],
                    'paths' => [],
                    'provider' => ['type' => 'adobe', 'project_id' => $projectId],
                ];
            }

            $families[] = [
                'family' => $familyName,
                'slug' => $familySlug,
                'publish_state' => 'published',
                'active_delivery_id' => $deliveryId,
                'delivery_profiles' => [
                    $deliveryId => [
                        'id' => $deliveryId,
                        'provider' => 'adobe',
                        'type' => 'adobe_hosted',
                        'label' => __('Adobe-hosted', 'tasty-fonts'),
                        'variants' => $this->variantsFromFaces($faces),
                        'faces' => $faces,
                        'meta' => ['project_id' => $projectId],
                    ],
                ],
            ];
        }

        return $families;
    }

    private function variantsFromFaces(array $faces): array
    {
        $variants = [];

        foreach ($faces as $face) {
            if (!is_array($face)) {
                continue;
            }

            $weight = FontUtils::normalizeWeight((string) ($face['weight'] ?? '400'));
            $style = FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal'));

            if ($weight === '400' && $style === 'normal') {
                $variants[] = 'regular';
                continue;
            }

            if ($weight === '400' && $style === 'italic') {
                $variants[] = 'italic';
                continue;
            }

            $variants[] = $weight . ($style === 'italic' ? 'italic' : '');
        }

        return FontUtils::normalizeVariantTokens($variants);
    }

    private function variantTokenFromMeta(array $meta): string
    {
        $weight = FontUtils::normalizeWeight((string) ($meta['weight'] ?? '400'));
        $style = FontUtils::normalizeStyle((string) ($meta['style'] ?? 'normal'));

        if ($weight === '400' && $style === 'normal') {
            return 'regular';
        }

        if ($weight === '400' && $style === 'italic') {
            return 'italic';
        }

        return $weight . ($style === 'italic' ? 'italic' : '');
    }

    private function buildDeliveryBadges(array $family, array $activeDelivery): array
    {
        $publishState = (string) ($family['publish_state'] ?? 'published');
        $provider = strtolower(trim((string) ($activeDelivery['provider'] ?? '')));
        $type = strtolower(trim((string) ($activeDelivery['type'] ?? 'self_hosted')));
        $badges = [];

        $badges[] = match ($publishState) {
            'library_only' => [
                'label' => __('Library Only', 'tasty-fonts'),
                'class' => 'is-warning',
                'copy' => __('This family is saved for previews and configuration, but it is not enqueued on the frontend, editor, or Etch canvas.', 'tasty-fonts'),
            ],
            'role_active' => [
                'label' => __('In Use', 'tasty-fonts'),
                'class' => 'is-success',
                'copy' => __('This family is active through the current live heading/body role pair.', 'tasty-fonts'),
            ],
            default => [
                'label' => __('Published', 'tasty-fonts'),
                'class' => 'is-success',
                'copy' => __('This family is available on the frontend, editor, and Etch canvas for manual use.', 'tasty-fonts'),
            ],
        };

        $badges[] = match ($provider . ':' . $type) {
            'google:cdn' => [
                'label' => __('External Request', 'tasty-fonts'),
                'class' => '',
                'copy' => __('Visitors request this family from Google’s web font API instead of your own uploads directory.', 'tasty-fonts'),
            ],
            'bunny:cdn' => [
                'label' => __('External Request', 'tasty-fonts'),
                'class' => '',
                'copy' => __('Visitors request this family from Bunny Fonts instead of your own uploads directory.', 'tasty-fonts'),
            ],
            'adobe:adobe_hosted' => [
                'label' => __('Adobe-hosted', 'tasty-fonts'),
                'class' => '',
                'copy' => __('Adobe web fonts stay hosted by Adobe and load from the project stylesheet defined in Adobe Fonts.', 'tasty-fonts'),
            ],
            default => [
                'label' => __('Same-origin', 'tasty-fonts'),
                'class' => 'is-success',
                'copy' => __('The active delivery for this family stays on your own WordPress uploads domain.', 'tasty-fonts'),
            ],
        };

        return $badges;
    }

    private function buildDeliveryFilterTokens(array $family, array $activeDelivery): array
    {
        $publishState = (string) ($family['publish_state'] ?? 'published');
        $provider = strtolower(trim((string) ($activeDelivery['provider'] ?? '')));
        $type = strtolower(trim((string) ($activeDelivery['type'] ?? 'self_hosted')));
        $tokens = [$publishState];

        if ($type === 'self_hosted') {
            $tokens[] = 'same-origin';
        } elseif ($provider === 'adobe') {
            $tokens[] = 'adobe-hosted';
        } else {
            $tokens[] = 'external-request';
        }

        if ($provider === 'google' && $type === 'cdn') {
            $tokens[] = 'google-cdn';
        }

        if ($provider === 'bunny' && $type === 'cdn') {
            $tokens[] = 'bunny-cdn';
        }

        if ($provider === 'adobe') {
            $tokens[] = 'adobe-hosted';
        }

        return array_values(array_unique(array_filter($tokens, 'strlen')));
    }

    private function countFamilyFiles(array $family): int
    {
        $count = 0;

        foreach ((array) ($family['available_deliveries'] ?? []) as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            foreach ((array) ($profile['faces'] ?? []) as $face) {
                $count += count((array) ($face['files'] ?? []));
            }
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

    private function getImportedRootPrefixes(): array
    {
        $prefixes = [];

        foreach (self::IMPORTED_SOURCES as $source) {
            $root = $this->storage->getProviderRoot($source);

            if (!is_string($root) || $root === '') {
                continue;
            }

            $prefixes[] = trailingslashit(wp_normalize_path($root));
        }

        return $prefixes;
    }

    private function isScannableLocalFile(SplFileInfo $file, array $importRootPrefixes): bool
    {
        if (!$file->isFile()) {
            return false;
        }

        $absolutePath = wp_normalize_path($file->getPathname());

        foreach ($importRootPrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($absolutePath, $prefix)) {
                return false;
            }
        }

        return in_array(strtolower($file->getExtension()), self::LOCAL_FORMATS, true);
    }

    private function isRemoteUrl(string $value): bool
    {
        $value = trim($value);

        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '//');
    }

    private function localDeliveryId(): string
    {
        return FontUtils::slugify('local-self_hosted');
    }

    private function adobeDeliveryId(): string
    {
        return FontUtils::slugify('adobe-adobe_hosted');
    }

    private function compareDeliveryProfiles(array $left, array $right): int
    {
        $leftRank = $this->deliveryProfileRank($left);
        $rightRank = $this->deliveryProfileRank($right);

        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
    }

    private function deliveryProfileRank(array $profile): int
    {
        return match (strtolower(trim((string) ($profile['provider'] ?? ''))) . ':' . strtolower(trim((string) ($profile['type'] ?? '')))) {
            'local:self_hosted' => 10,
            'google:self_hosted' => 20,
            'google:cdn' => 30,
            'bunny:self_hosted' => 40,
            'bunny:cdn' => 50,
            'adobe:adobe_hosted' => 60,
            default => 99,
        };
    }
}
