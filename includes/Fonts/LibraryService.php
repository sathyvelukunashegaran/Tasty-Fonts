<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

final class LibraryService
{
    private const MANAGED_IMPORT_SOURCES = ['google', 'bunny'];
    private const MANUAL_PUBLISH_STATES = ['library_only', 'published'];

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
        private readonly SettingsRepository $settings
    ) {
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
        $familySlug = FontUtils::slugify($familySlug);
        $family = $this->findFamilyBySlug($familySlug);

        if ($family === null) {
            return $this->error(
                'tasty_fonts_family_not_found',
                __('That font family could not be found in the library.', 'tasty-fonts')
            );
        }

        $familyName = (string) ($family['family'] ?? $familySlug);
        $roleLabels = $this->getProtectedRoleLabels($familyName);

        if ($roleLabels !== []) {
            return $this->error(
                'tasty_fonts_family_in_use',
                $this->buildDeleteFamilyBlockedMessage($familyName, $roleLabels)
            );
        }

        $relativePaths = $this->collectFamilyRelativePaths($family);

        if (!$this->storage->deleteRelativeFiles($relativePaths)) {
            return $this->error(
                'tasty_fonts_delete_failed',
                $this->storageErrorMessage(__('The font files could not be deleted from the font storage directory.', 'tasty-fonts'))
            );
        }

        foreach ($this->managedImportSourcesForFamily($family) as $source) {
            $this->storage->deleteRelativeDirectory($source . '/' . $familySlug);
        }

        $this->imports->deleteFamily($familySlug);
        $this->assets->refreshGeneratedAssets();
        do_action('tasty_fonts_after_delete_family', $familySlug, $familyName);

        $fileCount = count($relativePaths);
        $this->log->add(
            sprintf(
                _n(
                    'Font family deleted: %1$s (%2$d file removed).',
                    'Font family deleted: %1$s (%2$d files removed).',
                    $fileCount,
                    'tasty-fonts'
                ),
                $familyName,
                $fileCount
            )
        );

        return true;
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
        $familySlug = FontUtils::slugify($familySlug);
        $deliveryId = FontUtils::slugify($deliveryId);
        $family = $this->findFamilyBySlug($familySlug);

        if ($family === null) {
            return $this->error(
                'tasty_fonts_family_not_found',
                __('That font family could not be found in the library.', 'tasty-fonts')
            );
        }

        $profile = $this->findDeliveryProfile($family, $deliveryId);

        if ($profile === null) {
            return $this->error(
                'tasty_fonts_delivery_not_found',
                __('That delivery profile could not be found for the selected family.', 'tasty-fonts')
            );
        }

        $familyName = (string) ($family['family'] ?? $familySlug);
        $isActiveDelivery = (string) ($family['active_delivery_id'] ?? '') === $deliveryId;

        if ($isActiveDelivery && $this->isLiveRoleFamily($familyName)) {
            return $this->error(
                'tasty_fonts_delivery_in_use',
                __('Switch the live delivery or remove the family from the active roles before deleting this delivery profile.', 'tasty-fonts')
            );
        }

        if (count((array) ($family['available_deliveries'] ?? [])) <= 1) {
            $result = $this->deleteFamily($familySlug);

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

        $relativePaths = $this->collectProfileRelativePaths($profile);

        if (!$this->storage->deleteRelativeFiles($relativePaths)) {
            return $this->error(
                'tasty_fonts_delete_failed',
                $this->storageErrorMessage(__('The files for that delivery profile could not be removed from the font storage directory.', 'tasty-fonts'))
            );
        }

        $storedFamily = $this->imports->getFamily($familySlug);

        if ($storedFamily !== null && isset(($storedFamily['delivery_profiles'] ?? [])[$deliveryId])) {
            $this->imports->deleteProfile($familySlug, $deliveryId);
        } elseif ($isActiveDelivery) {
            $fallbackProfile = $this->firstStoredAlternativeProfile($family, $deliveryId);

            if ($fallbackProfile !== null) {
                $this->persistProfile($family, $fallbackProfile, false);
                $this->imports->setActiveDelivery($familySlug, (string) ($fallbackProfile['id'] ?? ''));
            }
        }

        $provider = strtolower(trim((string) ($profile['provider'] ?? '')));

        if ($this->isManagedImportSource($provider)) {
            $this->storage->deleteRelativeDirectory($provider . '/' . $familySlug);
        }

        $this->assets->refreshGeneratedAssets();

        $label = (string) ($profile['label'] ?? ucfirst($provider));
        $message = sprintf(
            __('Removed %1$s from %2$s.', 'tasty-fonts'),
            $label,
            $familyName
        );
        $this->log->add($message);

        return [
            'family' => $familyName,
            'family_slug' => $familySlug,
            'delivery_id' => $deliveryId,
            'message' => $message,
            'deleted_family' => false,
        ];
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
        $familySlug = FontUtils::slugify($familySlug);
        $deliveryId = FontUtils::slugify($deliveryId);
        $family = $this->findFamilyBySlug($familySlug);

        if ($family === null) {
            return $this->error(
                'tasty_fonts_family_not_found',
                __('That font family could not be found in the library.', 'tasty-fonts')
            );
        }

        $profile = $this->findDeliveryProfile($family, $deliveryId);

        if ($profile === null) {
            return $this->error(
                'tasty_fonts_delivery_not_found',
                __('That delivery profile could not be found for the selected family.', 'tasty-fonts')
            );
        }

        $this->persistProfile($family, $profile, false);

        $saved = $this->imports->setActiveDelivery($familySlug, $deliveryId);

        if ($saved === null) {
            return $this->error(
                'tasty_fonts_delivery_save_failed',
                __('The active delivery could not be updated.', 'tasty-fonts')
            );
        }

        $this->assets->refreshGeneratedAssets();

        $familyName = (string) ($family['family'] ?? $familySlug);
        $message = sprintf(
            __('Live delivery for %1$s switched to %2$s.', 'tasty-fonts'),
            $familyName,
            (string) ($profile['label'] ?? __('the selected profile', 'tasty-fonts'))
        );
        $this->log->add($message);

        return [
            'family' => $familyName,
            'family_slug' => $familySlug,
            'delivery_id' => $deliveryId,
            'delivery_label' => (string) ($profile['label'] ?? ''),
            'message' => $message,
        ];
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
        $familySlug = FontUtils::slugify($familySlug);
        $publishState = strtolower(trim($publishState));
        $family = $this->findFamilyBySlug($familySlug);

        if ($family === null) {
            return $this->error(
                'tasty_fonts_family_not_found',
                __('That font family could not be found in the library.', 'tasty-fonts')
            );
        }

        if (!in_array($publishState, self::MANUAL_PUBLISH_STATES, true)) {
            return $this->error(
                'tasty_fonts_publish_state_invalid',
                __('Choose either Published or In Library Only.', 'tasty-fonts')
            );
        }

        $familyName = (string) ($family['family'] ?? $familySlug);

        if ($publishState === 'library_only' && $this->isLiveRoleFamily($familyName)) {
            return $this->error(
                'tasty_fonts_family_live',
                __('This family is live through the current active roles. Switch roles or turn off sitewide usage before pausing it.', 'tasty-fonts')
            );
        }

        $storedFamily = $this->imports->getFamily($familySlug);

        if ($storedFamily === null) {
            $this->imports->ensureFamily(
                $familyName,
                $familySlug,
                (string) ($family['publish_state'] ?? 'published')
            );
        }

        $saved = $this->imports->setPublishState($familySlug, $publishState);

        if ($saved === null) {
            return $this->error(
                'tasty_fonts_publish_state_failed',
                __('The family publish state could not be updated.', 'tasty-fonts')
            );
        }

        $this->assets->refreshGeneratedAssets();

        $message = $publishState === 'library_only'
            ? sprintf(__('%s is now In Library Only.', 'tasty-fonts'), $familyName)
            : sprintf(__('%s is now Published.', 'tasty-fonts'), $familyName);
        $this->log->add($message);

        return [
            'family' => $familyName,
            'family_slug' => $familySlug,
            'publish_state' => $publishState,
            'message' => $message,
        ];
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
        $liveFamilies = [];

        if ($sitewideEnabled) {
            foreach ($this->liveRoleKeys() as $key) {
                $familyName = trim((string) ($liveRoles[$key] ?? ''));

                if ($familyName !== '') {
                    $liveFamilies[$familyName] = FontUtils::slugify($familyName);
                }
            }
        }

        foreach ($this->imports->allFamilies() as $storedFamily) {
            if (!is_array($storedFamily)) {
                continue;
            }

            $familyName = trim((string) ($storedFamily['family'] ?? ''));
            $familySlug = FontUtils::slugify((string) ($storedFamily['slug'] ?? $familyName));

            if ($familyName === '' || $familySlug === '') {
                continue;
            }

            $targetState = isset($liveFamilies[$familyName]) ? 'role_active' : 'published';

            if ((string) ($storedFamily['publish_state'] ?? 'published') !== $targetState) {
                $this->imports->setPublishState($familySlug, $targetState);
            }
        }

        foreach ($liveFamilies as $familyName => $familySlug) {
            $storedFamily = $this->imports->getFamily($familySlug);

            if ($storedFamily === null) {
                $this->imports->ensureFamily($familyName, $familySlug, 'role_active');
                continue;
            }

            if ((string) ($storedFamily['publish_state'] ?? 'published') !== 'role_active') {
                $this->imports->setPublishState($familySlug, 'role_active');
            }
        }

        $this->catalog->invalidate();
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
        $familySlug = FontUtils::slugify($familySlug);
        $family = $this->findFamilyBySlug($familySlug);

        if ($family === null) {
            return $this->error(
                'tasty_fonts_family_not_found',
                __('That font family could not be found in the library.', 'tasty-fonts')
            );
        }

        $activeDelivery = is_array($family['active_delivery'] ?? null) ? $family['active_delivery'] : [];

        $normalizedWeight = FontUtils::normalizeWeight($weight);
        $normalizedStyle = FontUtils::normalizeStyle($style);
        $normalizedSource = trim($source) !== '' ? strtolower(trim($source)) : 'local';
        $normalizedUnicodeRange = $this->isManagedImportSource($normalizedSource) ? '' : trim($unicodeRange);
        $faces = is_array($activeDelivery['faces'] ?? null) ? (array) $activeDelivery['faces'] : [];
        $faceIndex = $this->findMatchingFaceIndex($faces, $normalizedWeight, $normalizedStyle, $normalizedSource, $normalizedUnicodeRange);

        if ($faceIndex === null) {
            return $this->error(
                'tasty_fonts_variant_not_found',
                __('That font variant could not be found in the library.', 'tasty-fonts')
            );
        }

        $face = $faces[$faceIndex];
        $familyName = (string) ($family['family'] ?? $familySlug);
        $roleLabels = $this->getProtectedRoleLabels($familyName);

        if (count($faces) <= 1 && $roleLabels !== []) {
            return $this->error(
                'tasty_fonts_variant_in_use',
                $this->buildDeleteLastVariantBlockedMessage($familyName, $roleLabels)
            );
        }

        $relativePaths = $this->collectFaceRelativePaths($face);
        $storedFamily = $this->imports->getFamily($familySlug);
        $deliveryId = (string) ($activeDelivery['id'] ?? '');
        $hasStoredActiveProfile = $storedFamily !== null
            && $deliveryId !== ''
            && isset(($storedFamily['delivery_profiles'] ?? [])[$deliveryId]);

        if ($relativePaths === [] && !$hasStoredActiveProfile) {
            return $this->error(
                'tasty_fonts_variant_delete_unsupported',
                __('That font variant cannot be deleted individually from this delivery.', 'tasty-fonts')
            );
        }

        if ($relativePaths !== [] && !$this->storage->deleteRelativeFiles($relativePaths)) {
            return $this->error(
                'tasty_fonts_delete_failed',
                __('The font files for that variant could not be deleted from the font storage directory.', 'tasty-fonts')
            );
        }

        if ($hasStoredActiveProfile) {
            unset($faces[$faceIndex]);

            $profile = (array) $storedFamily['delivery_profiles'][$deliveryId];
            $profile['faces'] = array_values($faces);
            $profile['variants'] = $this->buildVariantsFromFaces($profile['faces']);
            $this->imports->saveProfile(
                $familyName,
                $familySlug,
                $profile,
                (string) ($storedFamily['publish_state'] ?? 'published'),
                (string) ($storedFamily['active_delivery_id'] ?? '') === $deliveryId
            );
        }

        $this->assets->refreshGeneratedAssets();

        $fileCount = count($relativePaths);
        $this->log->add(
            sprintf(
                _n(
                    'Font variant deleted: %1$s %2$s %3$s (%4$d file removed).',
                    'Font variant deleted: %1$s %2$s %3$s (%4$d files removed).',
                    $fileCount,
                    'tasty-fonts'
                ),
                $familyName,
                $normalizedWeight,
                $normalizedStyle,
                $fileCount
            )
        );

        return [
            'family' => $familyName,
            'weight' => $normalizedWeight,
            'style' => $normalizedStyle,
        ];
    }

    private function findFamilyBySlug(string $familySlug): ?array
    {
        foreach ($this->catalog->getCatalog() as $family) {
            if (!is_array($family)) {
                continue;
            }

            $slug = is_string($family['slug'] ?? null) ? $family['slug'] : '';

            if ($slug === $familySlug) {
                return $family;
            }
        }

        return null;
    }

    private function findDeliveryProfile(array $family, string $deliveryId): ?array
    {
        foreach ((array) ($family['available_deliveries'] ?? []) as $profile) {
            if (is_array($profile) && (string) ($profile['id'] ?? '') === $deliveryId) {
                return $profile;
            }
        }

        return null;
    }

    private function collectFamilyRelativePaths(array $family): array
    {
        $paths = [];

        foreach ((array) ($family['available_deliveries'] ?? []) as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $paths = array_merge($paths, $this->collectProfileRelativePaths($profile));
        }

        return array_values(array_unique(array_filter($paths, 'strlen')));
    }

    private function collectProfileRelativePaths(array $profile): array
    {
        $paths = [];

        foreach ((array) ($profile['faces'] ?? []) as $face) {
            if (!is_array($face)) {
                continue;
            }

            $paths = array_merge($paths, $this->collectFaceRelativePaths($face));
        }

        return array_values(array_unique(array_filter($paths, 'strlen')));
    }

    private function collectFaceRelativePaths(array $face): array
    {
        $paths = [];

        foreach ((array) ($face['paths'] ?? []) as $path) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            $paths[] = trim($path);
        }

        foreach ((array) ($face['files'] ?? []) as $file) {
            if (!is_string($file) || trim($file) === '' || FontUtils::isRemoteUrl($file)) {
                continue;
            }

            $paths[] = trim($file);
        }

        return array_values(array_unique($paths));
    }

    private function findMatchingFaceIndex(
        array $faces,
        string $weight,
        string $style,
        string $source,
        string $unicodeRange
    ): ?int {
        foreach ($faces as $index => $face) {
            if (!is_array($face)) {
                continue;
            }

            if ($this->faceMatches($face, $weight, $style, $source, $unicodeRange)) {
                return is_int($index) ? $index : null;
            }
        }

        return null;
    }

    private function faceMatches(array $face, string $weight, string $style, string $source, string $unicodeRange): bool
    {
        $faceSource = strtolower(trim((string) ($face['source'] ?? 'local')));
        $faceUnicodeRange = $this->isManagedImportSource($faceSource) ? '' : trim((string) ($face['unicode_range'] ?? ''));

        return $faceSource === $source
            && FontUtils::normalizeWeight((string) ($face['weight'] ?? '400')) === $weight
            && FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal')) === $style
            && $faceUnicodeRange === $unicodeRange;
    }

    private function buildVariantsFromFaces(array $faces): array
    {
        return HostedImportSupport::variantsFromFaces($faces);
    }

    private function managedImportSourcesForFamily(array $family): array
    {
        $sources = [];

        foreach ((array) ($family['available_deliveries'] ?? []) as $profile) {
            if (!is_array($profile) || !$this->isSelfHostedProfile($profile)) {
                continue;
            }

            $provider = strtolower(trim((string) ($profile['provider'] ?? '')));

            if ($this->isManagedImportSource($provider)) {
                $sources[] = $provider;
            }
        }

        return array_values(array_unique($sources));
    }

    private function isManagedImportSource(string $source): bool
    {
        return in_array(strtolower(trim($source)), self::MANAGED_IMPORT_SOURCES, true);
    }

    private function isSelfHostedProfile(array $profile): bool
    {
        return strtolower(trim((string) ($profile['type'] ?? ''))) === 'self_hosted';
    }

    private function isLiveRoleFamily(string $familyName): bool
    {
        if (empty($this->settings->getSettings()['auto_apply_roles'])) {
            return false;
        }

        $catalog = $this->catalog->getCatalog();
        $liveRoles = $this->settings->getAppliedRoles($catalog);

        foreach ($this->liveRoleKeys() as $roleKey) {
            if (($liveRoles[$roleKey] ?? '') === $familyName) {
                return true;
            }
        }

        return false;
    }

    private function persistProfile(array $family, array $profile, bool $activate): void
    {
        $familyName = (string) ($family['family'] ?? '');
        $familySlug = (string) ($family['slug'] ?? FontUtils::slugify($familyName));

        if ($familyName === '' || $familySlug === '') {
            return;
        }

        $storedFamily = $this->imports->getFamily($familySlug);
        $deliveryId = (string) ($profile['id'] ?? '');

        if (
            $deliveryId !== ''
            && $storedFamily !== null
            && isset(($storedFamily['delivery_profiles'] ?? [])[$deliveryId])
        ) {
            return;
        }

        $this->imports->saveProfile(
            $familyName,
            $familySlug,
            $profile,
            (string) ($family['publish_state'] ?? 'published'),
            $activate
        );
    }

    private function firstStoredAlternativeProfile(array $family, string $excludedDeliveryId): ?array
    {
        foreach ((array) ($family['available_deliveries'] ?? []) as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $profileId = (string) ($profile['id'] ?? '');

            if ($profileId === '' || $profileId === $excludedDeliveryId) {
                continue;
            }

            return $profile;
        }

        return null;
    }

    private function getProtectedRoleLabels(string $familyName): array
    {
        $catalog = $this->catalog->getCatalog();
        $roleSets = [$this->settings->getRoles($catalog)];

        if (!empty($this->settings->getSettings()['auto_apply_roles'])) {
            $roleSets[] = $this->settings->getAppliedRoles($catalog);
        }

        $roleLabels = [];

        foreach ($roleSets as $roles) {
            foreach ($this->liveRoleKeys() as $roleKey) {
                if (($roles[$roleKey] ?? '') === $familyName) {
                    $roleLabels[] = $roleKey;
                }
            }
        }

        return array_values(array_unique($roleLabels));
    }

    private function translateRoleLabels(array $roleLabels): array
    {
        return array_map(
            static fn (string $label): string => match ($label) {
                'heading' => __('heading', 'tasty-fonts'),
                'body' => __('body', 'tasty-fonts'),
                'monospace' => __('monospace', 'tasty-fonts'),
                default => $label,
            },
            $roleLabels
        );
    }

    private function buildDeleteFamilyBlockedMessage(string $familyName, array $roleLabels): string
    {
        $translatedLabels = $this->translateRoleLabels($roleLabels);

        if ($translatedLabels === []) {
            return '';
        }

        if (count($translatedLabels) === 1) {
            return sprintf(
                __('%1$s is currently assigned as the %2$s font. Choose a different %2$s font before deleting it.', 'tasty-fonts'),
                $familyName,
                $translatedLabels[0]
            );
        }

        return sprintf(
            __('%1$s is currently assigned to %2$s. Choose different role fonts before deleting it.', 'tasty-fonts'),
            $familyName,
            $this->formatRoleLabelList($translatedLabels)
        );
    }

    private function buildDeleteLastVariantBlockedMessage(string $familyName, array $roleLabels): string
    {
        $translatedLabels = $this->translateRoleLabels($roleLabels);

        if ($translatedLabels === []) {
            return '';
        }

        if (count($translatedLabels) === 1) {
            return sprintf(
                __('%1$s is currently assigned to %2$s, and this is the last saved variant. Choose a different %2$s font before deleting it.', 'tasty-fonts'),
                $familyName,
                $translatedLabels[0]
            );
        }

        return sprintf(
            __('%1$s is currently assigned to %2$s, and this is the last saved variant. Choose different role fonts before deleting it.', 'tasty-fonts'),
            $familyName,
            $this->formatRoleLabelList($translatedLabels)
        );
    }

    private function formatRoleLabelList(array $labels): string
    {
        $labels = array_values(array_filter($labels, 'strlen'));

        if ($labels === []) {
            return '';
        }

        if (count($labels) === 1) {
            return $labels[0];
        }

        $lastLabel = array_pop($labels);

        return implode(', ', $labels) . __(' and ', 'tasty-fonts') . $lastLabel;
    }

    private function liveRoleKeys(): array
    {
        $keys = ['heading', 'body'];

        if (!empty($this->settings->getSettings()['monospace_role_enabled'])) {
            $keys[] = 'monospace';
        }

        return $keys;
    }

    private function storageErrorMessage(string $fallback): string
    {
        $message = trim($this->storage->getLastFilesystemErrorMessage());

        return $message !== '' ? $message : $fallback;
    }

    private function error(string $code, string $message): WP_Error
    {
        $this->log->add($message);

        return new WP_Error($code, $message);
    }
}
