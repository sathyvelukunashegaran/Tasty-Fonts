<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

/**
 * @phpstan-import-type AxesMap from \TastyFonts\Support\FontUtils
 * @phpstan-import-type VariationDefaults from \TastyFonts\Support\FontUtils
 * @phpstan-type ProfileMetaValue string|list<string>
 * @phpstan-type FaceRecord array{
 *     family: string,
 *     slug: string,
 *     source: string,
 *     weight: string,
 *     style: string,
 *     unicode_range: string,
 *     files: array<string, string>,
 *     paths: array<string, string>,
 *     provider: array<string, mixed>,
 *     is_variable: bool,
 *     axes: AxesMap,
 *     variation_defaults: VariationDefaults
 * }
 * @phpstan-type DeliveryProfile array{
 *     id: string,
 *     provider: string,
 *     type: string,
 *     format: string,
 *     label: string,
 *     variants: list<string>,
 *     faces: list<FaceRecord>,
 *     meta: array<string, ProfileMetaValue>
 * }
 * @phpstan-type LibraryRecord array{
 *     family: string,
 *     slug: string,
 *     publish_state: string,
 *     manual_publish_state: string,
 *     active_delivery_id: string,
 *     delivery_profiles: array<string, DeliveryProfile>
 * }
 * @phpstan-type LibraryMap array<string, LibraryRecord>
 */
interface ImportRepositoryInterface
{
    /**
     * @return LibraryMap
     */
    public function all(): array;

    /**
     * @return LibraryMap
     */
    public function allFamilies(): array;

    /**
     * @return LibraryRecord|null
     */
    public function get(string $slug): ?array;

    /**
     * @return LibraryRecord|null
     */
    public function getFamily(string $slug): ?array;

    /**
     * @param array<string, mixed> $family
     */
    public function upsert(array $family): void;

    /**
     * @param array<string, mixed> $family
     */
    public function saveFamily(array $family): void;

    public function delete(string $slug): void;

    public function deleteFamily(string $slug): void;

    /**
     * @return LibraryRecord|array{}
     */
    public function ensureFamily(
        string $familyName,
        ?string $familySlug = null,
        string $defaultPublishState = 'published',
        ?string $activeDeliveryId = null,
        ?string $defaultManualPublishState = null
    ): array;

    /**
     * @param array<string, mixed> $profile
     * @return LibraryRecord|array{}
     */
    public function saveProfile(
        string $familyName,
        string $familySlug,
        array $profile,
        string $defaultPublishState = 'library_only',
        bool $activate = false
    ): array;

    /**
     * @return LibraryRecord|array{}|null
     */
    public function deleteProfile(string $familySlug, string $deliveryId): ?array;

    /**
     * @return LibraryRecord|array{}|null
     */
    public function setActiveDelivery(string $familySlug, string $deliveryId, ?string $publishState = null): ?array;

    /**
     * @return LibraryRecord|array{}|null
     */
    public function setPublishState(string $familySlug, string $publishState): ?array;

    public function clearLibrary(): void;

    /**
     * @param LibraryMap $library
     * @return LibraryMap
     */
    public function replaceLibrary(array $library): array;

    /**
     * @param array<string, mixed> $library
     * @return LibraryMap
     */
    public function replaceLibraryPreview(array $library): array;
}
