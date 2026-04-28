<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

/**
 * Coordinates active delivery switching and delivery-profile deletion mutations.
 *
 * @phpstan-import-type CatalogFamily from CatalogService
 * @phpstan-import-type DeliveryProfile from CatalogService
 */
final class LibraryDeliveryMutationAction
{
    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly AssetService $assets,
        private readonly LogRepository $log,
        private readonly LibraryCatalogResolver $resolver,
        private readonly LibraryPathCollector $paths,
        private readonly LibraryRoleProtectionPolicy $roles,
        private readonly LibraryFamilyDeletionAction $familyDeletion,
        private readonly LibraryMutationErrorFactory $errors
    ) {
    }

    /**
     * @return array{
     *     family: string,
     *     family_slug: string,
     *     delivery_id: string,
     *     delivery_label: string,
     *     message: string
     * }|WP_Error Result payload for the saved delivery switch, or a WordPress error when the change fails.
     */
    public function saveFamilyDelivery(string $familySlug, string $deliveryId): array|WP_Error
    {
        $selection = $this->resolver->resolveFamilyDeliverySelection($familySlug, $deliveryId);

        if (is_wp_error($selection)) {
            return $selection;
        }

        $selection = $this->resolver->normalizedSelectionPayload($selection);
        $familySlug = $selection['family_slug'];
        $deliveryId = $selection['delivery_id'];
        $family = $selection['family'];
        $profile = $selection['profile'];

        $this->persistProfile($family, $profile, false);

        $saved = $this->imports->setActiveDelivery($familySlug, $deliveryId);

        if ($saved === null) {
            return $this->errors->error(
                'tasty_fonts_delivery_save_failed',
                __('The active delivery could not be updated.', 'tasty-fonts')
            );
        }

        $this->assets->refreshGeneratedAssets();

        $familyName = $this->resolver->stringValue($family, 'family', $familySlug);
        $message = sprintf(
            __('Live delivery for %1$s switched to %2$s.', 'tasty-fonts'),
            $familyName,
            $this->resolver->stringValue($profile, 'label', __('the selected profile', 'tasty-fonts'))
        );
        $this->log->add($message, [
            'category' => LogRepository::CATEGORY_LIBRARY,
            'event' => 'active_delivery_changed',
            'outcome' => 'success',
            'status_label' => __('Switched', 'tasty-fonts'),
            'source' => __('Library', 'tasty-fonts'),
            'entity_type' => 'font_family',
            'entity_id' => $familySlug,
            'entity_name' => $familyName,
            'details' => [
                ['label' => __('Family', 'tasty-fonts'), 'value' => $familyName],
                ['label' => __('Live delivery', 'tasty-fonts'), 'value' => $this->resolver->stringValue($profile, 'label', $deliveryId)],
                ['label' => __('Delivery ID', 'tasty-fonts'), 'value' => $deliveryId],
            ],
        ]);

        return [
            'family' => $familyName,
            'family_slug' => $familySlug,
            'delivery_id' => $deliveryId,
            'delivery_label' => $this->resolver->stringValue($profile, 'label'),
            'message' => $message,
        ];
    }

    /**
     * @return array{
     *     family: string,
     *     family_slug: string,
     *     delivery_id: string,
     *     deleted_family: bool,
     *     message?: string
     * }|WP_Error Result payload for the deleted delivery, or a WordPress error when deletion is blocked or fails.
     */
    public function deleteDeliveryProfile(string $familySlug, string $deliveryId): array|WP_Error
    {
        $selection = $this->resolver->resolveFamilyDeliverySelection($familySlug, $deliveryId);

        if (is_wp_error($selection)) {
            return $selection;
        }

        $selection = $this->resolver->normalizedSelectionPayload($selection);
        $familySlug = $selection['family_slug'];
        $deliveryId = $selection['delivery_id'];
        $family = $selection['family'];
        $profile = $selection['profile'];

        $familyName = $this->resolver->stringValue($family, 'family', $familySlug);
        $isActiveDelivery = $this->resolver->stringValue($family, 'active_delivery_id') === $deliveryId;

        if ($isActiveDelivery && $this->roles->isLiveRoleFamily($familyName)) {
            return $this->errors->error(
                'tasty_fonts_delivery_in_use',
                __('Switch the live delivery or remove the family from the active roles before deleting this delivery profile.', 'tasty-fonts')
            );
        }

        if (count($this->resolver->availableDeliveries($family)) <= 1) {
            $result = $this->familyDeletion->delete($familySlug);

            if (is_wp_error($result)) {
                return $result;
            }

            return [
                'family' => $familyName,
                'family_slug' => $familySlug,
                'delivery_id' => $deliveryId,
                'deleted_family' => true,
            ];
        }

        $relativePaths = $this->paths->collectProfileRelativePaths($profile);

        if (!$this->storage->deleteRelativeFiles($relativePaths)) {
            return $this->errors->error(
                'tasty_fonts_delete_failed',
                $this->storageErrorMessage(__('The files for that delivery profile could not be removed from the font storage directory.', 'tasty-fonts'))
            );
        }

        $storedFamily = $this->imports->getFamily($familySlug);

        if ($storedFamily !== null && isset($storedFamily['delivery_profiles'][$deliveryId])) {
            $this->imports->deleteProfile($familySlug, $deliveryId);
        } elseif ($isActiveDelivery) {
            $fallbackProfile = $this->firstStoredAlternativeProfile($family, $deliveryId);

            if ($fallbackProfile !== null) {
                $this->persistProfile($family, $fallbackProfile, false);
                $this->imports->setActiveDelivery($familySlug, $this->resolver->stringValue($fallbackProfile, 'id'));
            }
        }

        $provider = strtolower(trim($this->resolver->stringValue($profile, 'provider')));

        if ($this->paths->isManagedImportSource($provider)) {
            $this->storage->deleteRelativeDirectory($provider . '/' . $familySlug);
        }

        $this->assets->refreshGeneratedAssets();

        $label = $this->resolver->stringValue($profile, 'label', ucfirst($provider));
        $message = sprintf(__('Removed %1$s from %2$s.', 'tasty-fonts'), $label, $familyName);
        $this->log->add($message, [
            'category' => LogRepository::CATEGORY_LIBRARY,
            'event' => 'delivery_profile_deleted',
            'outcome' => 'danger',
            'status_label' => __('Deleted', 'tasty-fonts'),
            'source' => __('Library', 'tasty-fonts'),
            'entity_type' => 'font_family',
            'entity_id' => $familySlug,
            'entity_name' => $familyName,
            'details' => [
                ['label' => __('Family', 'tasty-fonts'), 'value' => $familyName],
                ['label' => __('Delivery', 'tasty-fonts'), 'value' => $label],
                ['label' => __('Files removed', 'tasty-fonts'), 'value' => (string) count($relativePaths), 'kind' => 'count'],
            ],
        ]);

        return [
            'family' => $familyName,
            'family_slug' => $familySlug,
            'delivery_id' => $deliveryId,
            'message' => $message,
            'deleted_family' => false,
        ];
    }

    /**
     * @param CatalogFamily $family
     * @param DeliveryProfile $profile
     */
    private function persistProfile(array $family, array $profile, bool $activate): void
    {
        $familyName = $this->resolver->stringValue($family, 'family');
        $familySlug = $this->resolver->stringValue($family, 'slug', FontUtils::slugify($familyName));

        if ($familyName === '' || $familySlug === '') {
            return;
        }

        $storedFamily = $this->imports->getFamily($familySlug);
        $deliveryId = $this->resolver->stringValue($profile, 'id');

        if (
            $deliveryId !== ''
            && $storedFamily !== null
            && isset($storedFamily['delivery_profiles'][$deliveryId])
        ) {
            return;
        }

        $this->imports->saveProfile(
            $familyName,
            $familySlug,
            $profile,
            $this->resolver->stringValue($family, 'publish_state', 'published'),
            $activate
        );
    }

    /**
     * @param CatalogFamily $family
     * @return DeliveryProfile|null
     */
    private function firstStoredAlternativeProfile(array $family, string $excludedDeliveryId): ?array
    {
        foreach ($this->resolver->availableDeliveries($family) as $profile) {
            $profileId = $this->resolver->stringValue($profile, 'id');

            if ($profileId === '' || $profileId === $excludedDeliveryId) {
                continue;
            }

            return $profile;
        }

        return null;
    }

    private function storageErrorMessage(string $fallback): string
    {
        $message = trim($this->storage->getLastFilesystemErrorMessage());

        return $message !== '' ? $message : $fallback;
    }
}
