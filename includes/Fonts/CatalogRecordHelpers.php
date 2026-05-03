<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * Shared catalog record normalization helpers used by the catalog pipeline.
 *
 * @phpstan-import-type CatalogFace from CatalogCache
 * @phpstan-import-type DeliveryProfile from CatalogCache
 * @phpstan-import-type CatalogFamily from CatalogCache
 * @phpstan-import-type VariationDefaults from \TastyFonts\Support\FontUtils
 */
trait CatalogRecordHelpers
{
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
     * @param CatalogFamily $family
     */
    public function countFamilyFiles(array $family): int
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
     * @param mixed $faces
     * @return list<CatalogFace>
     */
    private function normalizeCatalogFaceList(mixed $faces): array
    {
        return FontUtils::normalizeFaceList($faces);
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
}
