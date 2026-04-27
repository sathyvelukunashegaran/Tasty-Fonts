<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use TastyFonts\Support\TransientKey;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * @phpstan-import-type ParsedFilenameResult from \TastyFonts\Fonts\FontFilenameParser
 * @phpstan-import-type VariationDefaults from \TastyFonts\Support\FontUtils
 * @phpstan-type CatalogFace array<string, mixed>
 * @phpstan-type DeliveryProfile array<string, mixed>
 * @phpstan-type CatalogFamily array<string, mixed>
 * @phpstan-type CatalogMap array<string, CatalogFamily>
 * @phpstan-type DeliveryFormatMap array<string, array{label: string, available: bool, source_only: bool}>
 * @phpstan-type CatalogCounts array{
 *     families: int,
 *     files: int,
 *     published_families: int,
 *     library_only_families: int,
 *     local_families: int,
 *     remote_families: int
 * }
 * @phpstan-type BuiltCatalog array{families: CatalogMap, counts: CatalogCounts}
 */
final class CatalogService
{
    public const TRANSIENT_CATALOG = 'tasty_fonts_catalog_v3';
    private const LOCAL_FORMATS = ['woff2', 'woff', 'ttf', 'otf'];
    private const IMPORTED_SOURCES = ['google', 'bunny', 'custom'];
    private const DEFAULT_COUNTS = [
        'families' => 0,
        'files' => 0,
        'published_families' => 0,
        'library_only_families' => 0,
        'local_families' => 0,
        'remote_families' => 0,
    ];

    /** @var CatalogMap|null */
    private ?array $catalog = null;
    /** @var CatalogCounts */
    private array $counts = self::DEFAULT_COUNTS;

    /**
     * Create the catalog service.
     *
     * @since 1.4.0
     *
     * @param Storage $storage Storage abstraction for uploads/fonts discovery and URL resolution.
     * @param ImportRepository $imports Repository for stored imported family records.
     * @param FontFilenameParser $parser Parser used to infer family metadata from local filenames.
     * @param LogRepository $log Log repository used when attachment changes invalidate the catalog.
     * @param AdobeProjectClient $adobe Adobe project client used to merge hosted Adobe families into the catalog.
     */
    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly FontFilenameParser $parser,
        private readonly LogRepository $log,
        private readonly AdobeProjectClient $adobe
    ) {
    }

    /**
     * Return the merged font catalog used by the admin UI and runtime planners.
     *
     * @since 1.4.0
     *
     * @return CatalogMap Catalog entries keyed by family name.
     */
    public function getCatalog(): array
    {
        if (is_array($this->catalog)) {
            return $this->catalog;
        }

        if ($this->hydrateFromCache(get_transient(TransientKey::forSite(self::TRANSIENT_CATALOG)))) {
            return $this->applyCatalogFilter();
        }

        $built = $this->buildCatalog();
        $this->catalog = $built['families'];
        $this->counts = $built['counts'];
        $this->cacheCatalogState();

        return $this->applyCatalogFilter();
    }

    /**
     * Return catalog summary counts for the current cached catalog state.
     *
     * @since 1.4.0
     *
     * @return CatalogCounts Aggregate catalog counts used by the admin overview.
     */
    public function getCounts(): array
    {
        $this->getCatalog();

        return $this->counts;
    }

    /**
     * Invalidate the cached catalog and reset its derived counts.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function invalidate(): void
    {
        delete_transient(TransientKey::forSite(self::TRANSIENT_CATALOG));
        $this->catalog = null;
        $this->counts = self::DEFAULT_COUNTS;
    }

    /**
     * Invalidate the catalog when an uploaded font attachment under uploads/fonts changes.
     *
     * @since 1.4.0
     *
     * @param int $attachmentId WordPress attachment ID being added or removed.
     * @return void
     */
    public function maybeInvalidateFromAttachment(int $attachmentId): void
    {
        $file = get_attached_file($attachmentId);

        if (!$file || !$this->storage->isWithinRoot($file)) {
            return;
        }

        $this->invalidate();
        $this->log->add(__('Font attachment changed. Catalog cache cleared.', 'tasty-fonts'));
    }

    /**
     * @return BuiltCatalog
     */
    private function buildCatalog(): array
    {
        /** @var CatalogMap $families */
        $families = [];

        foreach ($this->imports->allFamilies() as $family) {
            $familyName = $family['family'];

            if ($familyName === '') {
                continue;
            }

            $families[$familyName] = $this->normalizeFamilyRecord($family);
        }

        foreach ($this->scanLocalFamilies() as $family) {
            $this->mergeSyntheticFamily($families, $family, 'library_only');
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

            if ($this->stringValue($family, 'publish_state', 'published') === 'library_only') {
                $counts['library_only_families']++;
            } else {
                $counts['published_families']++;
            }

            $activeType = strtolower(trim($this->stringValue($this->profileValue($family, 'active_delivery'), 'type')));

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

    /**
     * @param CatalogFamily $family
     * @return CatalogFamily
     */
    private function normalizeFamilyRecord(array $family): array
    {
        $familyName = $this->stringValue($family, 'family');
        $familySlug = $this->stringValue($family, 'slug', FontUtils::slugify($familyName));
        $publishState = $this->stringValue($family, 'publish_state', 'published');
        $activeDeliveryId = $this->stringValue($family, 'active_delivery_id');
        $deliveryProfiles = $this->deliveryProfiles($family['delivery_profiles'] ?? []);

        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'publish_state' => in_array($publishState, ['library_only', 'published', 'role_active'], true) ? $publishState : 'published',
            'active_delivery_id' => $activeDeliveryId,
            'delivery_profiles' => $deliveryProfiles,
        ];
    }

    /**
     * @param CatalogFamily $family
     * @return CatalogFamily
     */
    private function finalizeFamily(array $family): array
    {
        $profiles = [];

        foreach ($this->deliveryProfiles($family['delivery_profiles'] ?? []) as $profileId => $profile) {
            $profiles[$profileId] = $this->hydrateDeliveryProfile($family, $profile);
        }

        $activeDeliveryId = $this->stringValue($family, 'active_delivery_id');

        if ($activeDeliveryId === '' || !isset($profiles[$activeDeliveryId])) {
            $activeDeliveryId = $profiles !== [] ? (string) array_key_first($profiles) : '';
        }

        $activeDelivery = ($activeDeliveryId !== '' && isset($profiles[$activeDeliveryId])) ? $profiles[$activeDeliveryId] : [];
        $availableDeliveries = array_values($profiles);
        usort($availableDeliveries, [$this, 'compareDeliveryProfiles']);

        $sources = array_values(
            array_unique(
                array_filter(
                    array_map(fn (array $profile): string => strtolower(trim($this->stringValue($profile, 'provider'))), $availableDeliveries),
                    static fn (string $source): bool => $source !== ''
                )
            )
        );

        $hasVariableFaces = $this->familyHasVariableFaces($availableDeliveries);
        $hasStaticFaces = $this->familyHasStaticFaces($availableDeliveries);
        $formats = $this->buildFamilyFormats($availableDeliveries);

        return [
            'family' => $this->stringValue($family, 'family'),
            'slug' => $this->stringValue($family, 'slug', FontUtils::slugify($this->stringValue($family, 'family'))),
            'publish_state' => $this->stringValue($family, 'publish_state', 'published'),
            'active_delivery_id' => $activeDeliveryId,
            'active_delivery' => $activeDelivery,
            'available_deliveries' => $availableDeliveries,
            'delivery_profiles' => $profiles,
            'sources' => $sources,
            'faces' => $this->deliveryFaceList($activeDelivery),
            'delivery_badges' => $this->buildDeliveryBadges($family, $activeDelivery),
            'delivery_filter_tokens' => $this->buildDeliveryFilterTokens($family, $activeDelivery),
            'font_category' => $this->resolveFamilyCategory($family, $activeDelivery, $availableDeliveries),
            'font_category_tokens' => $this->buildFamilyCategoryTokens(
                $this->resolveFamilyCategory($family, $activeDelivery, $availableDeliveries),
                $hasVariableFaces
            ),
            'formats' => $formats,
            'has_static_faces' => $hasStaticFaces,
            'has_variable_faces' => $hasVariableFaces,
            'variation_axes' => $this->collectFamilyVariationAxes($availableDeliveries),
        ];
    }

    /**
     * @param CatalogFamily $family
     * @param DeliveryProfile $profile
     * @return DeliveryProfile
     */
    private function hydrateDeliveryProfile(array $family, array $profile): array
    {
        $provider = strtolower(trim($this->stringValue($profile, 'provider', 'local')));
        $type = strtolower(trim($this->stringValue($profile, 'type', 'self_hosted')));
        $faces = [];

        foreach ($this->deliveryFaceList($profile, $provider) as $face) {
            $faces[] = $this->hydrateFace(
                $this->stringValue($family, 'family'),
                $this->stringValue($family, 'slug'),
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

                return strcmp(
                    self::arrayStringValue($left, 'unicode_range'),
                    self::arrayStringValue($right, 'unicode_range')
                );
            }
        );

        return [
            'id' => $this->stringValue($profile, 'id'),
            'provider' => $provider,
            'type' => $type,
            'format' => FontUtils::resolveProfileFormat($profile),
            'label' => $this->stringValue($profile, 'label'),
            'variants' => $this->stringList($this->arrayValue($profile, 'variants')),
            'faces' => $faces,
            'meta' => $this->metaValueMap($profile),
        ];
    }

    /**
     * @param CatalogFace $face
     * @return CatalogFace
     */
    private function hydrateFace(string $familyName, string $familySlug, string $provider, string $type, array $face): array
    {
        $files = [];
        $paths = [];
        $axes = $this->normalizeAxes($face['axes'] ?? []);

        foreach ($this->stringMap($face['files'] ?? []) as $format => $value) {
            if (trim($value) === '') {
                continue;
            }

            $normalizedFormat = strtolower(trim($format));
            $normalizedValue = trim($value);
            $paths[$normalizedFormat] = $normalizedValue;

            if ($type === 'self_hosted' && !FontUtils::isRemoteUrl($normalizedValue)) {
                $relativePath = $normalizedValue;
                $url = $this->storage->urlForRelativePath($relativePath);

                if ($url === null) {
                    continue;
                }

                $files[$normalizedFormat] = $url;
                continue;
            }

            $files[$normalizedFormat] = $normalizedValue;

            if (FontUtils::isRemoteUrl($normalizedValue)) {
                unset($paths[$normalizedFormat]);
            }
        }

        foreach ($this->stringMap($face['paths'] ?? []) as $format => $value) {
            if (trim($value) === '') {
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
            'weight' => FontUtils::normalizeWeight($this->stringValue($face, 'weight', '400')),
            'style' => FontUtils::normalizeStyle($this->stringValue($face, 'style', 'normal')),
            'unicode_range' => trim($this->stringValue($face, 'unicode_range')),
            'files' => $files,
            'paths' => $paths,
            'provider' => $this->arrayValue($face, 'provider'),
            'is_variable' => !empty($face['is_variable']) || $axes !== [],
            'axes' => $axes,
            'variation_defaults' => $this->normalizeVariationDefaults($face['variation_defaults'] ?? [], $axes),
        ];
    }

    /**
     * @param CatalogMap $families
     * @param CatalogFamily $synthetic
     * @param-out CatalogMap $families
     */
    private function mergeSyntheticFamily(array &$families, array $synthetic, string $defaultPublishState): void
    {
        $familyName = $this->stringValue($synthetic, 'family');
        $familySlug = $this->stringValue($synthetic, 'slug', FontUtils::slugify($familyName));

        if ($familyName === '' || $familySlug === '') {
            return;
        }

        if (!isset($families[$familyName])) {
            $families[$familyName] = $this->buildCatalogFamilyRecord(
                $familyName,
                $familySlug,
                $defaultPublishState,
                $this->stringValue($synthetic, 'active_delivery_id'),
                $this->deliveryProfiles($synthetic['delivery_profiles'] ?? [])
            );

            return;
        }

        $existingProfiles = $this->deliveryProfiles($families[$familyName]['delivery_profiles'] ?? []);

        foreach ($this->deliveryProfiles($synthetic['delivery_profiles'] ?? []) as $profileId => $profile) {
            if (!isset($existingProfiles[$profileId])) {
                $existingProfiles[$profileId] = $profile;
                continue;
            }

            $existingFaces = $this->deliveryFaceList($existingProfiles[$profileId]);
            $syntheticFaces = $this->deliveryFaceList($profile);
            $existingProfiles[$profileId] = array_replace($profile, $existingProfiles[$profileId]);
            $existingProfiles[$profileId]['faces'] = HostedImportSupport::mergeManifestFaces($existingFaces, $syntheticFaces);
            $existingProfiles[$profileId]['variants'] = array_values(
                array_unique(
                    array_merge(
                        $this->stringList($existingProfiles[$profileId]['variants'] ?? []),
                        $this->stringList($profile['variants'] ?? [])
                    )
                )
            );
            $existingProfiles[$profileId]['meta'] = array_replace(
                $this->metaValueMap($profile),
                $this->metaValueMap($existingProfiles[$profileId])
            );
        }

        $families[$familyName]['delivery_profiles'] = $existingProfiles;

        if (trim($this->stringValue($families[$familyName], 'active_delivery_id')) === '') {
            $families[$familyName]['active_delivery_id'] = $this->stringValue($synthetic, 'active_delivery_id');
        }
    }

    /**
     * @return list<CatalogFamily>
     */
    private function scanLocalFamilies(): array
    {
        $root = $this->storage->getRoot();
        $importRootPrefixes = $this->getImportedRootPrefixes();

        if (!$root || !is_dir($root) || !is_readable($root)) {
            return [];
        }

        /** @var CatalogMap $byFamily */
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

            $byFamily = $this->addLocalFileToFamilies($byFamily, $file);
        }

        foreach ($byFamily as &$family) {
            $profileId = $this->localDeliveryId();
            $profiles = $this->deliveryProfiles($family['delivery_profiles'] ?? []);
            $profile = $profiles[$profileId] ?? [];
            $profile['faces'] = array_values($this->deliveryFaceMap($profile));
            $profiles[$profileId] = $profile;
            $family['delivery_profiles'] = $profiles;
        }
        unset($family);

        return array_values($byFamily);
    }

    /**
     * @param CatalogMap $byFamily
     * @return CatalogMap
     */
    private function addLocalFileToFamilies(array $byFamily, SplFileInfo $file): array
    {
        $extension = strtolower($file->getExtension());
        $meta = $this->parser->parse($file->getBasename('.' . $extension));

        if ($meta['is_variable'] && !$this->variableFontsEnabled()) {
            return $byFamily;
        }

        $familyName = $meta['family'];
        $familySlug = FontUtils::slugify($familyName);
        $variantKey = FontUtils::variantKey($meta['weight'], $meta['style']);
        $profileId = $this->localDeliveryId();

        if (!isset($byFamily[$familyName])) {
            $byFamily[$familyName] = $this->buildCatalogFamilyRecord(
                $familyName,
                $familySlug,
                'library_only',
                $profileId,
                [$profileId => $this->buildLocalScanDeliveryProfile($profileId)]
            );
        }

        $profiles = $this->deliveryProfiles($byFamily[$familyName]['delivery_profiles'] ?? []);
        $profile = $profiles[$profileId] ?? $this->buildLocalScanDeliveryProfile($profileId);
        $faces = $this->deliveryFaceMap($profile);

        if (!isset($faces[$variantKey])) {
            $faces[$variantKey] = $this->buildLocalFace($familyName, $familySlug, $meta);
        }

        $absolutePath = wp_normalize_path($file->getPathname());
        $relativePath = $this->storage->relativePath($absolutePath);
        $url = $this->storage->urlForRelativePath($relativePath);

        if ($url === null) {
            return $byFamily;
        }

        $face = $faces[$variantKey];
        $face['files'] = array_replace($this->stringMap($face['files'] ?? []), [$extension => $relativePath]);
        $face['paths'] = array_replace($this->stringMap($face['paths'] ?? []), [$extension => $relativePath]);
        $faces[$variantKey] = $face;
        $profile['faces'] = $faces;
        $profile['variants'] = array_values(array_unique([...$this->stringList($profile['variants'] ?? []), $this->variantTokenFromMeta($meta)]));
        $profiles[$profileId] = $profile;
        $byFamily[$familyName]['delivery_profiles'] = $profiles;

        return $byFamily;
    }

    /**
     * @param ParsedFilenameResult $meta
     * @return CatalogFace
     */
    private function buildLocalFace(string $familyName, string $familySlug, array $meta): array
    {
        $axes = $this->normalizeAxes($meta['axes']);

        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'source' => 'local',
            'weight' => FontUtils::normalizeWeight($meta['weight']),
            'style' => FontUtils::normalizeStyle($meta['style']),
            'unicode_range' => '',
            'files' => [],
            'paths' => [],
            'provider' => ['type' => 'local'],
            'is_variable' => !empty($meta['is_variable']),
            'axes' => $axes,
            'variation_defaults' => $this->normalizeVariationDefaults($meta['variation_defaults'], $axes),
        ];
    }

    /**
     * @param array<string, DeliveryProfile> $deliveryProfiles
     * @return CatalogFamily
     */
    private function buildCatalogFamilyRecord(
        string $familyName,
        string $familySlug,
        string $publishState,
        string $activeDeliveryId,
        array $deliveryProfiles
    ): array {
        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'publish_state' => $publishState,
            'active_delivery_id' => $activeDeliveryId,
            'delivery_profiles' => $deliveryProfiles,
        ];
    }

    /**
     * @return DeliveryProfile
     */
    private function buildLocalScanDeliveryProfile(string $profileId): array
    {
        return [
            'id' => $profileId,
            'provider' => 'local',
            'type' => 'self_hosted',
            'label' => __('Self-hosted', 'tasty-fonts'),
            'variants' => [],
            'faces' => [],
            'meta' => ['origin' => 'local_scan'],
        ];
    }

    private function variableFontsEnabled(): bool
    {
        $settings = get_option(SettingsRepository::OPTION_SETTINGS, []);

        return is_array($settings) && !empty($settings['variable_fonts_enabled']);
    }

    /**
     * @param list<DeliveryProfile> $profiles
     */
    private function familyHasVariableFaces(array $profiles): bool
    {
        foreach ($profiles as $profile) {
            foreach ($this->deliveryFaceList($profile) as $face) {
                if (FontUtils::faceIsVariable($face)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<DeliveryProfile> $profiles
     */
    private function familyHasStaticFaces(array $profiles): bool
    {
        foreach ($profiles as $profile) {
            foreach ($this->deliveryFaceList($profile) as $face) {
                if (FontUtils::faceIsVariable($face)) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * @param list<DeliveryProfile> $profiles
     * @return DeliveryFormatMap
     */
    private function buildFamilyFormats(array $profiles): array
    {
        $formats = [];

        foreach ($profiles as $profile) {
            $format = FontUtils::resolveProfileFormat($profile);
            $formats[$format] = [
                'label' => ucfirst($format),
                'available' => true,
                'source_only' => !empty($profile['source_only']),
            ];
        }

        if ($formats === []) {
            $formats['static'] = [
                'label' => 'Static',
                'available' => true,
                'source_only' => false,
            ];
        }

        return $formats;
    }

    /**
     * @param list<DeliveryProfile> $profiles
     * @return array<string, mixed>
     */
    private function collectFamilyVariationAxes(array $profiles): array
    {
        $axes = [];

        foreach ($profiles as $profile) {
            foreach ($this->deliveryFaceList($profile) as $face) {
                foreach ($this->normalizeAxes($face['axes'] ?? []) as $tag => $definition) {
                    if (!isset($axes[$tag])) {
                        $axes[$tag] = $definition;
                        continue;
                    }

                    $axes[$tag]['min'] = (string) min((float) $axes[$tag]['min'], (float) $definition['min']);
                    $axes[$tag]['max'] = (string) max((float) $axes[$tag]['max'], (float) $definition['max']);
                }
            }
        }

        ksort($axes, SORT_STRING);

        return $axes;
    }

    /**
     * @return list<CatalogFamily>
     */
    private function loadAdobeFamilies(): array
    {
        $projectState = $this->adobe->getProjectStatus()['state'];

        if (!$this->adobe->hasProjectId() || !in_array($projectState, ['valid', 'unknown'], true)) {
            return [];
        }

        $projectId = $this->adobe->getProjectId();
        $deliveryId = $this->adobeDeliveryId();
        $families = [];

        foreach ($this->adobe->getConfiguredFamilies() as $family) {
            $familyName = $this->stringValue($family, 'family');
            $familySlug = $this->stringValue($family, 'slug', FontUtils::slugify($familyName));

            if ($familyName === '' || $familySlug === '') {
                continue;
            }

            $faces = [];

            foreach ($this->deliveryFaceList(['faces' => $family['faces'] ?? []]) as $face) {
                $axes = $this->normalizeAxes($face['axes'] ?? []);

                $faces[] = [
                    'family' => $familyName,
                    'slug' => $familySlug,
                    'source' => 'adobe',
                    'weight' => FontUtils::normalizeWeight($this->stringValue($face, 'weight', '400')),
                    'style' => FontUtils::normalizeStyle($this->stringValue($face, 'style', 'normal')),
                    'unicode_range' => '',
                    'files' => [],
                    'paths' => [],
                    'provider' => ['type' => 'adobe', 'project_id' => $projectId],
                    'is_variable' => !empty($face['is_variable']),
                    'axes' => $axes,
                    'variation_defaults' => $this->normalizeVariationDefaults($face['variation_defaults'] ?? [], $axes),
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

    /**
     * @param list<CatalogFace> $faces
     * @return list<string>
     */
    private function variantsFromFaces(array $faces): array
    {
        return HostedImportSupport::variantsFromFaces($faces);
    }

    /**
     * @param ParsedFilenameResult $meta
     */
    private function variantTokenFromMeta(array $meta): string
    {
        $weight = FontUtils::normalizeWeight($meta['weight']);
        $style = FontUtils::normalizeStyle($meta['style']);

        if ($weight === '400' && $style === 'normal') {
            return 'regular';
        }

        if ($weight === '400' && $style === 'italic') {
            return 'italic';
        }

        return $weight . ($style === 'italic' ? 'italic' : '');
    }

    /**
     * @param CatalogFamily $family
     * @param DeliveryProfile $activeDelivery
     * @return list<array{label: string, class: string, copy: string}>
     */
    private function buildDeliveryBadges(array $family, array $activeDelivery): array
    {
        $publishState = $this->stringValue($family, 'publish_state', 'published');
        $provider = strtolower(trim($this->stringValue($activeDelivery, 'provider')));
        $type = strtolower(trim($this->stringValue($activeDelivery, 'type', 'self_hosted')));
        $badges = [];

        $badges[] = match ($publishState) {
            'library_only' => [
                'label' => __('In Library Only', 'tasty-fonts'),
                'class' => 'is-warning',
                'copy' => __('This family is saved for previews and configuration, but it is not enqueued on the frontend, editor, or Etch canvas.', 'tasty-fonts'),
            ],
            'role_active' => [
                'label' => __('In Use', 'tasty-fonts'),
                'class' => 'is-success',
                'copy' => __('This family is active through the current live roles.', 'tasty-fonts'),
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
            'custom:cdn' => [
                'label' => __('External Request', 'tasty-fonts'),
                'class' => '',
                'copy' => __('Visitors request this custom CSS delivery from the reviewed remote font URLs while Tasty Fonts generates the @font-face rules.', 'tasty-fonts'),
            ],
            default => [
                'label' => __('Self-hosted', 'tasty-fonts'),
                'class' => 'is-success',
                'copy' => __('The active delivery for this family stays on this WordPress site.', 'tasty-fonts'),
            ],
        };

        return $badges;
    }

    /**
     * @param CatalogFamily $family
     * @param DeliveryProfile $activeDelivery
     * @return list<string>
     */
    private function buildDeliveryFilterTokens(array $family, array $activeDelivery): array
    {
        $publishState = $this->stringValue($family, 'publish_state', 'published');
        $provider = strtolower(trim($this->stringValue($activeDelivery, 'provider')));
        $type = strtolower(trim($this->stringValue($activeDelivery, 'type', 'self_hosted')));
        $tokens = [$publishState];

        if ($publishState === 'role_active') {
            $tokens[] = 'published';
        }

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

        if ($this->isCustomCssUrlDelivery($activeDelivery, $provider)) {
            $tokens[] = 'url-import';
        }

        if ($provider === 'adobe') {
            $tokens[] = 'adobe-hosted';
        }

        return $this->uniqueTokens($tokens);
    }

    /**
     * @param DeliveryProfile $profile
     */
    private function isCustomCssUrlDelivery(array $profile, string $provider): bool
    {
        $sourceType = strtolower(trim($this->profileMetaString($profile, 'source_type')));
        $sourceUrl = trim($this->profileMetaString($profile, 'source_css_url'));

        return $provider === 'custom'
            && $sourceUrl !== ''
            && in_array($sourceType, ['', 'custom_css_url'], true);
    }

    /**
     * @param CatalogFamily $family
     * @param DeliveryProfile $activeDelivery
     * @param list<DeliveryProfile> $availableDeliveries
     */
    private function resolveFamilyCategory(array $family, array $activeDelivery, array $availableDeliveries): string
    {
        $candidates = [];

        $candidates[] = $this->profileMetaString($activeDelivery, 'category');

        foreach ($availableDeliveries as $profile) {
            $candidates[] = $this->profileMetaString($profile, 'category');
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeFamilyCategory($candidate);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return $this->normalizeFamilyCategory($this->stringValue($family, 'family'));
    }

    private function normalizeFamilyCategory(string $category): string
    {
        $normalized = strtolower(trim($category));

        if ($normalized === '') {
            return '';
        }

        if (str_contains($normalized, 'slab') && str_contains($normalized, 'serif')) {
            return 'slab-serif';
        }

        if (str_contains($normalized, 'mono')) {
            return 'monospace';
        }

        if (str_contains($normalized, 'sans')) {
            return 'sans-serif';
        }

        if (str_contains($normalized, 'serif')) {
            return 'serif';
        }

        if (str_contains($normalized, 'display') || str_contains($normalized, 'decorative')) {
            return 'display';
        }

        if (str_contains($normalized, 'script')) {
            return 'script';
        }

        if (str_contains($normalized, 'cursive')) {
            return 'cursive';
        }

        if (str_contains($normalized, 'hand')) {
            return 'handwriting';
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function buildFamilyCategoryTokens(string $category, bool $hasVariableFaces = false): array
    {
        $normalized = $this->normalizeFamilyCategory($category);

        $tokens = $normalized === '' ? ['uncategorized'] : [$normalized];

        if ($normalized === 'slab-serif') {
            $tokens[] = 'serif';
        }

        if (in_array($normalized, ['handwriting', 'script', 'cursive'], true)) {
            $tokens[] = 'handwriting';
            $tokens[] = 'script';
            $tokens[] = 'cursive';
        }

        if ($normalized === 'display') {
            $tokens[] = 'decorative';
        }

        if ($hasVariableFaces) {
            $tokens[] = 'variable';
        }

        return $this->uniqueTokens($tokens);
    }

    /**
     * @param CatalogFamily $family
     */
    private function countFamilyFiles(array $family): int
    {
        $count = 0;

        foreach ($this->familyAvailableDeliveries($family) as $profile) {
            foreach ($this->deliveryFaceList($profile) as $face) {
                $count += count($this->stringMap($face['files'] ?? []));
            }
        }

        return $count;
    }

    /**
     * @return CatalogMap
     */
    private function applyCatalogFilter(): array
    {
        $filtered = apply_filters('tasty_fonts_catalog', $this->catalog);
        $normalized = $this->normalizeCatalogMap($filtered);

        if (is_array($filtered)) {
            $this->catalog = $normalized;
            $this->counts = $this->countCatalogFamilies($this->catalog);
        }

        return $this->catalog ?? [];
    }

    /**
     * @param CatalogMap $families
     * @return CatalogCounts
     */
    private function countCatalogFamilies(array $families): array
    {
        $counts = self::DEFAULT_COUNTS;

        foreach ($families as $family) {
            $counts['families']++;
            $counts['files'] += $this->countFamilyFiles($family);

            if ($this->stringValue($family, 'publish_state', 'published') === 'library_only') {
                $counts['library_only_families']++;
            } else {
                $counts['published_families']++;
            }

            $activeType = strtolower(trim($this->stringValue($this->profileValue($family, 'active_delivery'), 'type')));

            if ($activeType === 'self_hosted') {
                $counts['local_families']++;
            } else {
                $counts['remote_families']++;
            }
        }

        return $counts;
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

        foreach ($cached['families'] as $family) {
            if (
                !is_array($family)
                || !array_key_exists('delivery_filter_tokens', $family)
                || !array_key_exists('font_category', $family)
                || !array_key_exists('font_category_tokens', $family)
            ) {
                return false;
            }
        }

        $this->catalog = $this->normalizeCatalogMap($cached['families']);
        $this->counts = $this->countCatalogFamilies($this->catalog);

        return true;
    }

    /**
     * @param mixed $profiles
     * @return array<string, DeliveryProfile>
     */
    private function deliveryProfiles(mixed $profiles): array
    {
        if (!is_array($profiles)) {
            return [];
        }

        $normalized = [];

        foreach ($profiles as $profileId => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $id = $this->stringValue($profile, 'id', is_string($profileId) ? $profileId : '');

            if ($id === '') {
                continue;
            }

            $normalized[$id] = FontUtils::normalizeStringKeyedMap($profile);
        }

        return $normalized;
    }

    /**
     * @param DeliveryProfile|CatalogFamily $values
     * @return DeliveryProfile
     */
    private function profileValue(array $values, string $key): array
    {
        $value = $values[$key] ?? null;

        return FontUtils::normalizeStringKeyedMap($value);
    }

    /**
     * @param DeliveryProfile $profile
     * @return array<string, CatalogFace>
     */
    private function deliveryFaceMap(array $profile, string $provider = ''): array
    {
        $normalized = [];
        $provider = strtolower(trim($provider !== '' ? $provider : $this->stringValue($profile, 'provider')));

        foreach ($this->normalizeCatalogFaceList($profile['faces'] ?? []) as $face) {
            $key = $provider === 'custom'
                ? $this->customFaceKeyFromFace($face)
                : HostedImportSupport::faceKeyFromFace($face);
            $normalized[$key] = $face;
        }

        return $normalized;
    }

    /**
     * @param DeliveryProfile $profile
     * @return list<CatalogFace>
     */
    private function deliveryFaceList(array $profile, string $provider = ''): array
    {
        return array_values($this->deliveryFaceMap($profile, $provider));
    }

    /**
     * @param CatalogFace $face
     */
    private function customFaceKeyFromFace(array $face): string
    {
        $files = $this->stringMap($face['files'] ?? []);
        $format = $files !== [] ? (string) array_key_first($files) : '';
        $path = $format !== '' ? ($files[$format] ?? '') : '';
        $provider = $this->arrayValue($face, 'provider');
        $originalUrl = self::scalarStringValue($provider['original_url'] ?? '');

        return implode('|', [
            HostedImportSupport::faceKeyFromFace($face),
            strtolower(trim($format)),
            trim($this->stringValue($face, 'unicode_range')),
            $originalUrl !== '' ? $originalUrl : trim($path),
        ]);
    }

    /**
     * @param CatalogFamily $family
     * @return list<DeliveryProfile>
     */
    private function familyAvailableDeliveries(array $family): array
    {
        $availableDeliveries = $family['available_deliveries'] ?? null;

        if (!is_array($availableDeliveries)) {
            return array_values($this->deliveryProfiles($family['delivery_profiles'] ?? []));
        }

        $normalized = [];

        foreach ($availableDeliveries as $profile) {
            $normalizedProfile = FontUtils::normalizeStringKeyedMap($profile);

            if ($normalizedProfile === []) {
                continue;
            }

            $normalized[] = $normalizedProfile;
        }

        return $normalized;
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
     * @return array<string, string>
     */
    private function stringMap(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $normalizedValue = self::scalarStringValue($value);

            if ($normalizedValue === '') {
                continue;
            }

            $normalized[$key] = $normalizedValue;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            $normalizedValue = self::scalarStringValue($value);

            if ($normalizedValue === '') {
                continue;
            }

            $normalized[] = $normalizedValue;
        }

        return $normalized;
    }

    /**
     * @param DeliveryProfile $profile
     * @return array<string, string|list<string>>
     */
    private function metaValueMap(array $profile): array
    {
        $meta = $profile['meta'] ?? null;

        if (!is_array($meta)) {
            return [];
        }

        $normalized = [];

        foreach ($meta as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_array($value)) {
                $normalizedList = $this->stringList($value);

                if ($normalizedList !== []) {
                    $normalized[$key] = $normalizedList;
                }

                continue;
            }

            $normalizedValue = self::scalarStringValue($value);

            if ($normalizedValue !== '') {
                $normalized[$key] = $normalizedValue;
            }
        }

        return $normalized;
    }

    /**
     * @param DeliveryProfile $profile
     */
    private function profileMetaString(array $profile, string $key): string
    {
        $value = $this->metaValueMap($profile)[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @return array<string, array<string, float|int|string>>
     */
    private function normalizeAxes(mixed $axes): array
    {
        return is_array($axes) ? FontUtils::normalizeAxesMap($axes) : [];
    }

    /**
     * @param array<string, array<string, float|int|string>> $axes
     * @return VariationDefaults
     */
    private function normalizeVariationDefaults(mixed $variationDefaults, array $axes): array
    {
        return is_array($variationDefaults) ? FontUtils::normalizeVariationDefaults($variationDefaults, $axes) : [];
    }

    /**
     * @return CatalogMap
     */
    private function normalizeCatalogMap(mixed $families): array
    {
        if (!is_array($families)) {
            return [];
        }

        $normalized = [];

        foreach ($families as $family) {
            $normalizedFamily = FontUtils::normalizeStringKeyedMap($family);
            $familyName = $this->stringValue($normalizedFamily, 'family');

            if ($familyName === '') {
                continue;
            }

            $normalized[$familyName] = $normalizedFamily;
        }

        return $normalized;
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
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        return self::arrayStringValue($values, $key, $default);
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private static function arrayStringValue(array $values, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $value = self::scalarStringValue($values[$key]);

        return $value !== '' ? $value : $default;
    }

    private static function scalarStringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $value === true ? '1' : '';
    }

    private function cacheCatalogState(): void
    {
        set_transient(
            TransientKey::forSite(self::TRANSIENT_CATALOG),
            [
                'families' => $this->catalog,
                'counts' => $this->counts,
            ],
            DAY_IN_SECONDS
        );
    }

    /**
     * @return list<string>
     */
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

    /**
     * @param list<string> $importRootPrefixes
     */
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

    private function localDeliveryId(): string
    {
        return FontUtils::slugify('local-self_hosted');
    }

    private function adobeDeliveryId(): string
    {
        return FontUtils::slugify('adobe-adobe_hosted');
    }

    /**
     * @param DeliveryProfile $left
     * @param DeliveryProfile $right
     */
    private function compareDeliveryProfiles(array $left, array $right): int
    {
        $leftRank = $this->deliveryProfileRank($left);
        $rightRank = $this->deliveryProfileRank($right);

        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        return strcmp($this->stringValue($left, 'label'), $this->stringValue($right, 'label'));
    }

    /**
     * @param DeliveryProfile $profile
     */
    private function deliveryProfileRank(array $profile): int
    {
        return match (strtolower(trim($this->stringValue($profile, 'provider'))) . ':' . strtolower(trim($this->stringValue($profile, 'type')))) {
            'local:self_hosted' => 10,
            'google:self_hosted' => 20,
            'google:cdn' => 30,
            'bunny:self_hosted' => 40,
            'bunny:cdn' => 50,
            'adobe:adobe_hosted' => 60,
            'custom:self_hosted' => 70,
            'custom:cdn' => 80,
            default => 99,
        };
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function uniqueTokens(array $tokens): array
    {
        $unique = [];

        foreach ($tokens as $token) {
            if ($token === '' || isset($unique[$token])) {
                continue;
            }

            $unique[$token] = $token;
        }

        return array_values($unique);
    }
}
