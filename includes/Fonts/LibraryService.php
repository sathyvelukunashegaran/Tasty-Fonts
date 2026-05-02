<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\RoleRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\Storage;
use WP_Error;

final class LibraryService
{
    private readonly LibraryFamilyDeletionAction $familyDeletion;
    private readonly LibraryDeliveryMutationAction $deliveryMutation;
    private readonly LibraryPublishStateAction $publishState;
    private readonly LibraryLiveRolePublishStateSynchronizer $liveRolePublishStateSync;
    private readonly LibraryFaceDeletionAction $faceDeletion;

    /**
     * Create the library service.
     *
     * @since 1.4.0
     *
     * @param Storage $storage Storage abstraction for library file operations.
     * @param CatalogService $catalog Catalog service used to inspect normalized family records.
     * @param ImportRepository $imports Repository used to persist delivery and publish-state changes.
     * @param AssetService $assets Asset service used to refresh generated CSS after mutations.
     * @param LogRepository $log Log repository used for audit entries.
     * @param SettingsRepository $settings Settings repository used to protect live role assignments.
     */
    public function __construct(
        private readonly Storage $storage,
        private readonly CatalogService $catalog,
        private readonly ImportRepository $imports,
        private readonly AssetService $assets,
        private readonly LogRepository $log,
        private readonly SettingsRepository $settings,
        private readonly RoleRepository $roleRepo,
    ) {
        $errorFactory = new LibraryMutationErrorFactory($this->log);
        $catalogResolver = new LibraryCatalogResolver($this->catalog, $errorFactory);
        $pathCollector = new LibraryPathCollector($catalogResolver);
        $roleProtection = new LibraryRoleProtectionPolicy($this->catalog, $this->settings, $this->roleRepo);

        $this->familyDeletion = new LibraryFamilyDeletionAction(
            $this->storage,
            $this->imports,
            $this->assets,
            $this->log,
            $catalogResolver,
            $pathCollector,
            $roleProtection,
            $errorFactory
        );
        $this->deliveryMutation = new LibraryDeliveryMutationAction(
            $this->storage,
            $this->imports,
            $this->assets,
            $this->log,
            $catalogResolver,
            $pathCollector,
            $roleProtection,
            $this->familyDeletion,
            $errorFactory
        );
        $this->publishState = new LibraryPublishStateAction(
            $this->imports,
            $this->assets,
            $this->log,
            $catalogResolver,
            $roleProtection,
            $errorFactory
        );
        $this->liveRolePublishStateSync = new LibraryLiveRolePublishStateSynchronizer(
            $this->catalog,
            $this->imports,
            $catalogResolver,
            $roleProtection
        );
        $this->faceDeletion = new LibraryFaceDeletionAction(
            $this->storage,
            $this->imports,
            $this->assets,
            $this->log,
            $catalogResolver,
            $pathCollector,
            $roleProtection,
            $errorFactory
        );
    }

    /**
     * Delete a family and all of its managed files from the library.
     *
     * @since 1.4.0
     *
     * @param string $familySlug Stored family slug to delete.
     * @return bool|WP_Error True when the family is deleted, or a WordPress error when deletion is blocked or fails.
     */
    public function deleteFamily(string $familySlug): bool|WP_Error
    {
        return $this->familyDeletion->delete($familySlug);
    }

    /**
     * Delete a single delivery profile from a family, or the full family when it is the last delivery.
     *
     * @since 1.4.0
     *
     * @param string $familySlug Stored family slug.
     * @param string $deliveryId Delivery profile identifier to remove.
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
        return $this->deliveryMutation->deleteDeliveryProfile($familySlug, $deliveryId);
    }

    /**
     * Switch the active live delivery profile for a family.
     *
     * @since 1.4.0
     *
     * @param string $familySlug Stored family slug.
     * @param string $deliveryId Delivery profile identifier to activate.
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
        return $this->deliveryMutation->saveFamilyDelivery($familySlug, $deliveryId);
    }

    /**
     * Save a manual published or paused state for a family.
     *
     * @since 1.4.0
     *
     * @param string $familySlug Stored family slug.
     * @param string $publishState Requested publish state (`published` or `library_only`).
     * @return array{
     *     family: string,
     *     family_slug: string,
     *     publish_state: string,
     *     message: string
     * }|WP_Error Result payload for the saved publish state, or a WordPress error when the change fails.
     */
    public function saveFamilyPublishState(string $familySlug, string $publishState): array|WP_Error
    {
        return $this->publishState->save($familySlug, $publishState);
    }

    /**
     * Sync stored family publish states to reflect the active role assignments.
     *
     * @since 1.4.0
     *
     * @param array{heading?: string, body?: string, monospace?: string} $liveRoles Currently applied live role families.
     * @param bool $sitewideEnabled Whether sitewide role application is enabled.
     * @return void
     */
    public function syncLiveRolePublishStates(array $liveRoles, bool $sitewideEnabled): void
    {
        $this->liveRolePublishStateSync->sync($liveRoles, $sitewideEnabled);
    }

    /**
     * Delete a single face variant from the active delivery profile.
     *
     * @since 1.4.0
     *
     * @param string $familySlug Stored family slug.
     * @param string $weight Requested face weight.
     * @param string $style Requested face style.
     * @param string $source Face source identifier.
     * @param string $unicodeRange Optional unicode range used to disambiguate local faces.
     * @return array{family: string, weight: string, style: string}|WP_Error Result payload for the deleted face, or a WordPress error when deletion is blocked or fails.
     */
    public function deleteFaceVariant(
        string $familySlug,
        string $weight,
        string $style,
        string $source = 'local',
        string $unicodeRange = ''
    ): array|WP_Error {
        return $this->faceDeletion->deleteFaceVariant($familySlug, $weight, $style, $source, $unicodeRange);
    }
}
