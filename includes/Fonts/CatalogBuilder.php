<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepositoryInterface;
use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type CatalogFace from CatalogCache
 * @phpstan-import-type DeliveryProfile from CatalogCache
 * @phpstan-import-type CatalogFamily from CatalogCache
 * @phpstan-import-type CatalogMap from CatalogCache
 */
final class CatalogBuilder
{
    use CatalogRecordHelpers;

    /**
     * @param ImportRepositoryInterface $imports Repository for stored imported family records.
     * @param LocalCatalogScanner $localScanner Scanner for locally-uploaded font files.
     * @param AdobeCatalogAdapter $adobeAdapter Adapter for Adobe Fonts project families.
     */
    public function __construct(
        private readonly ImportRepositoryInterface $imports,
        private readonly LocalCatalogScanner $localScanner,
        private readonly AdobeCatalogAdapter $adobeAdapter,
    ) {
    }

    /**
     * Delete a stored family record by slug.
     */
    public function deleteFamilySlug(string $familySlug): void
    {
        $this->imports->deleteFamily($familySlug);
    }

    /**
     * Assemble raw family records from three provider sources.
     *
     * @return CatalogMap Raw families keyed by family name, before hydration or enrichment.
     */
    public function build(): array
    {
        /** @var CatalogMap $families */
        $families = [];

        foreach ($this->imports->allFamilies() as $family) {
            $familyName = $this->stringValue($family, 'family');

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

        return $families;
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

        $existingFamilyName = null;
        foreach ($families as $existingName => $existingFamily) {
            if ($this->stringValue($existingFamily, 'slug', FontUtils::slugify($existingName)) === $familySlug) {
                $existingFamilyName = $existingName;
                break;
            }
        }

        if ($existingFamilyName !== null && !$this->familiesShareFiles($families[$existingFamilyName], $synthetic)) {
            $existingFamilyName = null;
        }

        if ($existingFamilyName === null && !isset($families[$familyName])) {
            $families[$familyName] = $this->buildCatalogFamilyRecord(
                $familyName,
                $familySlug,
                $defaultPublishState,
                $this->stringValue($synthetic, 'active_delivery_id'),
                $this->deliveryProfiles($synthetic['delivery_profiles'] ?? [])
            );

            return;
        }

        $targetFamilyName = $existingFamilyName ?? $familyName;
        $existingProfiles = $this->deliveryProfiles($families[$targetFamilyName]['delivery_profiles'] ?? []);

        foreach ($this->deliveryProfiles($synthetic['delivery_profiles'] ?? []) as $profileId => $profile) {
            if (!isset($existingProfiles[$profileId])) {
                $existingProfiles[$profileId] = $profile;
                continue;
            }

            $existingFaces = $this->deliveryFaceList($existingProfiles[$profileId]);
            $syntheticFaces = $this->deliveryFaceList($profile);
            $existingProfiles[$profileId] = array_replace($profile, $existingProfiles[$profileId]);

            $existingFilePaths = [];
            foreach ($existingFaces as $existingFace) {
                foreach (array_values($this->stringMap($existingFace['files'] ?? [])) as $path) {
                    if (trim($path) !== '') {
                        $existingFilePaths[] = trim($path);
                    }
                }
                foreach (array_values($this->stringMap($existingFace['paths'] ?? [])) as $path) {
                    if (trim($path) !== '') {
                        $existingFilePaths[] = trim($path);
                    }
                }
            }
            $existingFilePaths = array_unique($existingFilePaths);

            $newSyntheticFaces = [];
            foreach ($syntheticFaces as $syntheticFace) {
                $syntheticPaths = [];
                foreach (array_values($this->stringMap($syntheticFace['files'] ?? [])) as $path) {
                    if (trim($path) !== '') {
                        $syntheticPaths[] = trim($path);
                    }
                }
                foreach (array_values($this->stringMap($syntheticFace['paths'] ?? [])) as $path) {
                    if (trim($path) !== '') {
                        $syntheticPaths[] = trim($path);
                    }
                }
                $syntheticPaths = array_unique($syntheticPaths);
                $allFilesExist = $syntheticPaths !== [] && array_diff($syntheticPaths, $existingFilePaths) === [];
                if ($allFilesExist) {
                    continue;
                }
                $newSyntheticFaces[] = $syntheticFace;
            }

            $mergedFaces = HostedImportSupport::mergeManifestFaces($existingFaces, $newSyntheticFaces);

            $existingFaceMap = [];
            foreach ($existingFaces as $existingFace) {
                $existingFaceMap[HostedImportSupport::faceKeyFromFace($existingFace)] = $existingFace;
            }
            foreach ($mergedFaces as &$mergedFace) {
                $key = HostedImportSupport::faceKeyFromFace($mergedFace);
                if (!isset($existingFaceMap[$key])) {
                    continue;
                }
                $existingAxes = is_array($existingFaceMap[$key]['axes'] ?? null) ? $existingFaceMap[$key]['axes'] : [];
                if ($existingAxes === []) {
                    continue;
                }
                $mergedAxes = is_array($mergedFace['axes'] ?? null) ? $mergedFace['axes'] : [];
                $mergedFace['axes'] = array_replace($mergedAxes, $existingAxes);
            }
            unset($mergedFace);

            $existingProfiles[$profileId]['faces'] = $mergedFaces;
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

        $families[$targetFamilyName]['delivery_profiles'] = $existingProfiles;

        if (trim($this->stringValue($families[$targetFamilyName], 'active_delivery_id')) === '') {
            $families[$targetFamilyName]['active_delivery_id'] = $this->stringValue($synthetic, 'active_delivery_id');
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
     * Remove families that have no usable delivery data.
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
     * @param CatalogFamily $left
     * @param CatalogFamily $right
     */
    private function familiesShareFiles(array $left, array $right): bool
    {
        $leftPaths = [];
        foreach ($this->deliveryProfiles($left['delivery_profiles'] ?? []) as $profile) {
            foreach ($this->deliveryFaceList($profile) as $face) {
                foreach (array_values($this->stringMap($face['files'] ?? [])) as $path) {
                    if (trim($path) !== '') {
                        $leftPaths[] = trim($path);
                    }
                }
                foreach (array_values($this->stringMap($face['paths'] ?? [])) as $path) {
                    if (trim($path) !== '') {
                        $leftPaths[] = trim($path);
                    }
                }
            }
        }
        $leftPaths = array_unique($leftPaths);
        if ($leftPaths === []) {
            return false;
        }

        foreach ($this->deliveryProfiles($right['delivery_profiles'] ?? []) as $profile) {
            foreach ($this->deliveryFaceList($profile) as $face) {
                foreach (array_values($this->stringMap($face['files'] ?? [])) as $path) {
                    if (trim($path) !== '' && in_array(trim($path), $leftPaths, true)) {
                        return true;
                    }
                }
                foreach (array_values($this->stringMap($face['paths'] ?? [])) as $path) {
                    if (trim($path) !== '' && in_array(trim($path), $leftPaths, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

}
