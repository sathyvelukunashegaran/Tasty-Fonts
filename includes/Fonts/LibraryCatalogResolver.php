<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;
use WP_Error;

/**
 * Resolves normalized catalog families and delivery profiles for library mutations.
 *
 * @phpstan-import-type CatalogFamily from CatalogCache
 * @phpstan-import-type DeliveryProfile from CatalogCache
 */
final class LibraryCatalogResolver
{
    public function __construct(
        private readonly CatalogCache $catalog,
        private readonly LibraryMutationErrorFactory $errors
    ) {
    }

    /**
     * @return CatalogFamily|null
     */
    public function findFamilyBySlug(string $familySlug): ?array
    {
        $familySlug = FontUtils::slugify($familySlug);

        foreach ($this->catalog->getCatalog() as $family) {
            $slug = $this->stringValue($family, 'slug');

            if ($slug === $familySlug) {
                return $family;
            }
        }

        return null;
    }

    /**
     * @param CatalogFamily $family
     * @return DeliveryProfile|null
     */
    public function findDeliveryProfile(array $family, string $deliveryId): ?array
    {
        $deliveryId = FontUtils::slugify($deliveryId);

        foreach ($this->availableDeliveries($family) as $profile) {
            if ($this->stringValue($profile, 'id') === $deliveryId) {
                return $profile;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     family_slug: string,
     *     delivery_id: string,
     *     family: array<string, mixed>,
     *     profile: array<string, mixed>
     * }|WP_Error
     */
    public function resolveFamilyDeliverySelection(string $familySlug, string $deliveryId): array|WP_Error
    {
        $familySlug = FontUtils::slugify($familySlug);
        $deliveryId = FontUtils::slugify($deliveryId);
        $family = $this->findFamilyBySlug($familySlug);

        if ($family === null) {
            return $this->errors->error(
                'tasty_fonts_family_not_found',
                __('That font family could not be found in the library.', 'tasty-fonts')
            );
        }

        $profile = $this->findDeliveryProfile($family, $deliveryId);

        if ($profile === null) {
            return $this->errors->error(
                'tasty_fonts_delivery_not_found',
                __('That delivery profile could not be found for the selected family.', 'tasty-fonts')
            );
        }

        return [
            'family_slug' => $familySlug,
            'delivery_id' => $deliveryId,
            'family' => $family,
            'profile' => $profile,
        ];
    }

    /**
     * @param CatalogFamily $family
     * @return list<DeliveryProfile>
     */
    public function availableDeliveries(array $family): array
    {
        return FontUtils::normalizeFaceList($family['available_deliveries'] ?? []);
    }

    /**
     * @param array{
     *     family_slug: string,
     *     delivery_id: string,
     *     family: array<string, mixed>,
     *     profile: array<string, mixed>
     * } $selection
     * @return array{
     *     family_slug: string,
     *     delivery_id: string,
     *     family: array<string, mixed>,
     *     profile: array<string, mixed>
     * }
     */
    public function normalizedSelectionPayload(array $selection): array
    {
        return [
            'family_slug' => $this->stringValue($selection, 'family_slug'),
            'delivery_id' => $this->stringValue($selection, 'delivery_id'),
            'family' => (array) $selection['family'],
            'profile' => (array) $selection['profile'],
        ];
    }

    /**
     * @param array<int|string, mixed> $values
     */
    public function stringValue(array $values, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $value = FontUtils::scalarStringValue($values[$key]);

        return $value !== '' ? $value : $default;
    }
}
