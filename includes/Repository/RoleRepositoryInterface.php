<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

/**
 * @phpstan-type RoleAxes array<string, string>
 * @phpstan-type RoleSet array{
 *     heading: string,
 *     body: string,
 *     monospace: string,
 *     heading_fallback: string,
 *     body_fallback: string,
 *     monospace_fallback: string,
 *     heading_weight: string,
 *     body_weight: string,
 *     monospace_weight: string,
 *     heading_axes: RoleAxes,
 *     body_axes: RoleAxes,
 *     monospace_axes: RoleAxes
 * }
 */
interface RoleRepositoryInterface
{
    /**
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function getRoles(array $catalog): array;

    /**
     * @param array<int|string, mixed> $input
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function saveRoles(array $input, array $catalog): array;

    /**
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function getAppliedRoles(array $catalog): array;

    /**
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function ensureAppliedRolesInitialized(array $catalog): array;

    /**
     * @param RoleSet $roles
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function saveAppliedRoles(array $roles, array $catalog): array;

    /**
     * @param array<int|string, mixed> $catalog
     * @return array{roles: RoleSet, applied_roles: RoleSet}
     */
    public function clearDisabledCapabilityRoleData(
        bool $clearVariableAxes,
        bool $clearMonospaceRole,
        array $catalog
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function setAutoApplyRoles(bool $enabled): array;

    /**
     * @param array<int|string, mixed> $roles
     * @return RoleSet
     */
    public function previewImportedRoles(array $roles): array;

    /**
     * @param array<int|string, mixed> $roles
     * @return RoleSet
     */
    public function replaceImportedRoles(array $roles): array;
}
