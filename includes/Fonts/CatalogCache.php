<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

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
final class CatalogCache
{
    use CatalogRecordHelpers;

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

    public function __construct(
        private readonly CatalogBuilder $builder,
        private readonly CatalogHydrator $hydrator,
        private readonly CatalogEnricher $enricher,
        private readonly Storage $storage,
        private readonly LogRepository $log,
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
        $raw = $this->builder->build();
        $hydrated = $this->hydrator->hydrate($raw);
        $enriched = $this->enricher->enrich($hydrated);

        return [
            'families' => $enriched,
            'counts' => $this->countCatalogFamilies($enriched),
        ];
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
            || !$this->isValidCachedCounts($cached['counts'])
        ) {
            return false;
        }

        foreach ($cached['families'] as $family) {
            if (!$this->isValidCachedFamily($family)) {
                return false;
            }
        }

        $this->catalog = $this->pruneUndeliverableFamilies(
            $this->normalizeCatalogMap($cached['families']),
            false
        );
        $this->counts = $this->countCatalogFamilies($this->catalog);

        return true;
    }

    private function isValidCachedCounts(mixed $counts): bool
    {
        if (!is_array($counts)) {
            return false;
        }

        foreach (array_keys(self::DEFAULT_COUNTS) as $key) {
            if (!array_key_exists($key, $counts) || !is_int($counts[$key]) || $counts[$key] < 0) {
                return false;
            }
        }

        return true;
    }

    private function isValidCachedFamily(mixed $family): bool
    {
        if (
            !is_array($family)
            || !array_key_exists('delivery_filter_tokens', $family)
            || !array_key_exists('font_category', $family)
            || !array_key_exists('font_category_tokens', $family)
            || !is_array($family['delivery_filter_tokens'])
            || !is_array($family['font_category_tokens'])
            || !is_scalar($family['font_category'])
            || !$this->familyHasDeliveryProfiles(FontUtils::normalizeStringKeyedMap($family))
        ) {
            return false;
        }

        if (!$this->isScalarList($family['delivery_filter_tokens']) || !$this->isScalarList($family['font_category_tokens'])) {
            return false;
        }

        if (isset($family['formats']) && !$this->isValidFamilyFormats($family['formats'])) {
            return false;
        }

        if (isset($family['delivery_badges']) && !$this->isValidDeliveryBadges($family['delivery_badges'])) {
            return false;
        }

        return true;
    }

    private function isValidFamilyFormats(mixed $formats): bool
    {
        if (!is_array($formats)) {
            return false;
        }

        foreach ($formats as $format => $entry) {
            if (!is_string($format) || !is_array($entry) || !is_scalar($entry['label'] ?? null)) {
                return false;
            }

            if (
                !array_key_exists('available', $entry)
                || !array_key_exists('source_only', $entry)
                || !is_bool($entry['available'])
                || !is_bool($entry['source_only'])
            ) {
                return false;
            }
        }

        return true;
    }

    private function isValidDeliveryBadges(mixed $badges): bool
    {
        if (!is_array($badges)) {
            return false;
        }

        foreach ($badges as $badge) {
            if (
                !is_array($badge)
                || !is_scalar($badge['label'] ?? null)
                || !is_scalar($badge['class'] ?? null)
                || !is_scalar($badge['copy'] ?? null)
            ) {
                return false;
            }
        }

        return true;
    }

    private function isScalarList(mixed $values): bool
    {
        if (!is_array($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (!is_scalar($value)) {
                return false;
            }
        }

        return true;
    }

    /**
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
                    $this->builder->deleteFamilySlug($familySlug);
                }
            }

            unset($families[$familyName]);
        }

        return $families;
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
     * @param DeliveryProfile|CatalogFamily $values
     * @return DeliveryProfile
     */
    private function profileValue(array $values, string $key): array
    {
        $value = $values[$key] ?? null;

        return FontUtils::normalizeStringKeyedMap($value);
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
}
