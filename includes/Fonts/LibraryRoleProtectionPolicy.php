<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\RoleRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\RoleUsageMessageFormatter;

/**
 * Encapsulates role-based mutation guards for library families.
 */
final class LibraryRoleProtectionPolicy
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly SettingsRepository $settings,
        private readonly RoleRepository $roleRepo,
    ) {
    }

    public function isLiveRoleFamily(string $familyName): bool
    {
        if (empty($this->settings->getSettings()['auto_apply_roles'])) {
            return false;
        }

        $catalog = $this->catalog->getCatalog();
        $liveRoles = $this->roleRepo->getAppliedRoles($catalog);

        foreach ($this->liveRoleKeys() as $roleKey) {
            if (($liveRoles[$roleKey] ?? '') === $familyName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function getProtectedRoleLabels(string $familyName): array
    {
        $catalog = $this->catalog->getCatalog();
        $roleSets = [$this->roleRepo->getRoles($catalog)];

        if (!empty($this->settings->getSettings()['auto_apply_roles'])) {
            $roleSets[] = $this->roleRepo->getAppliedRoles($catalog);
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

    /**
     * @return list<string>
     */
    public function liveRoleKeys(): array
    {
        $keys = ['heading', 'body'];

        if (!empty($this->settings->getSettings()['monospace_role_enabled'])) {
            $keys[] = 'monospace';
        }

        return $keys;
    }

    /**
     * @param list<string> $roleLabels
     */
    public function buildDeleteFamilyBlockedMessage(string $familyName, array $roleLabels): string
    {
        return RoleUsageMessageFormatter::buildDeleteBlockedMessage($familyName, $roleLabels);
    }

    /**
     * @param list<string> $roleLabels
     */
    public function buildDeleteLastVariantBlockedMessage(string $familyName, array $roleLabels): string
    {
        return RoleUsageMessageFormatter::buildDeleteLastVariantBlockedMessage($familyName, $roleLabels);
    }
}
