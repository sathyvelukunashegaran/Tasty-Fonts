<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Repository\SettingsRepository;

final class AdminAccessService
{
    public const IMPLICIT_ROLE = 'administrator';
    public const MENU_REGISTRATION_CAPABILITY = 'read';

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function canCurrentUserAccess(): bool
    {
        $user = wp_get_current_user();
        $userId = absint($user->ID);
        $roleSlugs = $this->normalizeRoleSlugs($user->roles);

        return $this->canUserAccess($userId, $roleSlugs);
    }

    /**
     * @param list<string> $roleSlugs
     */
    public function canUserAccess(int $userId, array $roleSlugs): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $normalizedRoleSlugs = $this->normalizeRoleSlugs($roleSlugs);

        if (in_array(self::IMPLICIT_ROLE, $normalizedRoleSlugs, true)) {
            return true;
        }

        $settings = $this->settings->getSettings();

        if (empty($settings['admin_access_custom_enabled'])) {
            return false;
        }
        $allowedRoleSlugs = $this->normalizeRoleSlugs($settings['admin_access_role_slugs'] ?? []);
        $allowedUserIds = is_array($settings['admin_access_user_ids'] ?? null)
            ? array_map('absint', $settings['admin_access_user_ids'])
            : [];

        if (in_array($userId, $allowedUserIds, true)) {
            return true;
        }

        return array_intersect($normalizedRoleSlugs, $allowedRoleSlugs) !== [];
    }

    /**
     * @param array<mixed> $roleSlugs
     * @return list<string>
     */
    private function normalizeRoleSlugs(array $roleSlugs): array
    {
        return array_values(
            array_filter(
                array_unique(
                    array_map(
                        static fn (mixed $role): string => sanitize_key(is_scalar($role) ? (string) $role : ''),
                        $roleSlugs
                    )
                ),
                static fn (string $role): bool => $role !== ''
            )
        );
    }

    public function menuRegistrationCapability(): string
    {
        return self::MENU_REGISTRATION_CAPABILITY;
    }
}
