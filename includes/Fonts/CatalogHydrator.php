<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;

/**
 * @phpstan-import-type VariationDefaults from \TastyFonts\Support\FontUtils
 * @phpstan-import-type CatalogFace from CatalogCache
 * @phpstan-import-type DeliveryProfile from CatalogCache
 * @phpstan-import-type CatalogFamily from CatalogCache
 * @phpstan-import-type CatalogMap from CatalogCache
 */
final class CatalogHydrator
{
    use CatalogRecordHelpers;

    public function __construct(
        private readonly Storage $storage,
    ) {
    }

    /**
     * Transform raw families into hydrated families by resolving delivery
     * profiles, normalizing faces, and collecting variation axes.
     *
     * @param CatalogMap $rawFamilies
     * @return CatalogMap
     */
    public function hydrate(array $rawFamilies): array
    {
        $hydrated = [];

        foreach ($rawFamilies as $familyName => $family) {
            $hydrated[$familyName] = $this->hydrateFamily($family);
        }

        return $hydrated;
    }

    /**
     * @param CatalogFamily $family
     * @return CatalogFamily
     */
    private function hydrateFamily(array $family): array
    {
        $profiles = [];

        foreach ($this->deliveryProfiles($family['delivery_profiles'] ?? []) as $profileId => $profile) {
            $profiles[$profileId] = $this->hydrateDeliveryProfile($family, $profile);
        }

        return [
            'family' => $this->stringValue($family, 'family'),
            'slug' => $this->stringValue($family, 'slug', FontUtils::slugify($this->stringValue($family, 'family'))),
            'publish_state' => $this->stringValue($family, 'publish_state', 'published'),
            'active_delivery_id' => $this->stringValue($family, 'active_delivery_id'),
            'delivery_profiles' => $profiles,
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

        $rawFormat = strtolower(trim($this->stringValue($profile, 'format')));
        $format = $rawFormat === 'variable-multi'
            ? 'variable-multi'
            : FontUtils::resolveProfileFormat($profile);

        return [
            'id' => $this->stringValue($profile, 'id'),
            'provider' => $provider,
            'type' => $type,
            'format' => $format,
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

}
