<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use TastyFonts\Support\TransientKey;

/**
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
     * @param FontFilenameParser $parser Parser used by the default local catalog scanner.
     * @param LogRepository $log Log repository used when attachment changes invalidate the catalog.
     * @param AdobeProjectClient $adobe Adobe project client used by the default Adobe catalog adapter.
     */
    private readonly LocalCatalogScanner $localScanner;
    private readonly AdobeCatalogAdapter $adobeAdapter;

    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        FontFilenameParser $parser,
        private readonly LogRepository $log,
        AdobeProjectClient $adobe,
        ?LocalCatalogScanner $localScanner = null,
        ?AdobeCatalogAdapter $adobeAdapter = null
    ) {
        $this->localScanner = $localScanner ?? new LocalCatalogScanner($this->storage, $parser);
        $this->adobeAdapter = $adobeAdapter ?? new AdobeCatalogAdapter($adobe);
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

        foreach ($this->localScanner->scan() as $family) {
            $this->mergeSyntheticFamily($families, $family, 'library_only');
        }

        foreach ($this->adobeAdapter->families() as $family) {
            $this->mergeSyntheticFamily($families, $family, 'published');
        }

        $families = $this->pruneUndeliverableFamilies($families, true);

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

        $this->catalog = $this->pruneUndeliverableFamilies(
            $this->normalizeCatalogMap($cached['families']),
            true
        );
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

            $normalizedProfile = FontUtils::normalizeStringKeyedMap($profile);
            $id = $this->stringValue($normalizedProfile, 'id', is_string($profileId) ? $profileId : '');

            if ($id === '') {
                continue;
            }

            $normalized[$id] = $normalizedProfile;
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
     * Remove families that have no usable delivery data. These records otherwise
     * render as ambiguous "Unavailable" library cards after capability cleanup or
     * live-role changes have removed their last profile.
     *
     * @param CatalogMap $families
     * @return CatalogMap
     */
    private function pruneUndeliverableFamilies(array $families, bool $deleteStoredRecord): array
    {
        foreach ($families as $familyName => $family) {
            if ($this->familyHasDeliveryProfiles($family)) {
                continue;
            }

            if ($deleteStoredRecord) {
                $familySlug = $this->stringValue($family, 'slug', FontUtils::slugify($this->stringValue($family, 'family')));

                if ($familySlug !== '') {
                    $this->imports->deleteFamily($familySlug);
                }
            }

            unset($families[$familyName]);
        }

        return $families;
    }

    /**
     * @param CatalogFamily $family
     */
    private function familyHasDeliveryProfiles(array $family): bool
    {
        if ($this->deliveryProfiles($family['delivery_profiles'] ?? []) !== []) {
            return true;
        }

        return FontUtils::normalizeFaceList($family['available_deliveries'] ?? []) !== [];
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
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $value = self::scalarStringValue($values[$key]);

        return $value !== '' ? $value : $default;
    }

    /**
     * @param array<string, mixed> $values
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
