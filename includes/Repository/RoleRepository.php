<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

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
final class RoleRepository implements RoleRepositoryInterface
{
    use RepositoryHelpers;
    public const OPTION_ROLES = 'tasty_fonts_roles';
    private const OPTION_SETTINGS = 'tasty_fonts_settings';
    private const ROLE_FAMILY_KEYS = ['heading', 'body', 'monospace'];
    private const ROLE_WEIGHT_KEYS = ['heading_weight', 'body_weight', 'monospace_weight'];
    private const ROLE_AXIS_KEYS = ['heading_axes', 'body_axes', 'monospace_axes'];
    private const DEFAULT_ROLE_FALLBACKS = [
        'heading_fallback' => FontUtils::DEFAULT_ROLE_SANS_FALLBACK,
        'body_fallback' => FontUtils::DEFAULT_ROLE_SANS_FALLBACK,
        'monospace_fallback' => FontUtils::DEFAULT_ROLE_MONOSPACE_FALLBACK,
    ];

    /**
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function getRoles(array $catalog): array
    {
        return $this->normalizeRoleSet(
            $this->getOptionArray(self::OPTION_ROLES),
            $catalog
        );
    }

    /**
     * @param array<int|string, mixed> $input
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function saveRoles(array $input, array $catalog): array
    {
        $storedRoles = $this->normalizeRoleSet(
            $this->getOptionArray(self::OPTION_ROLES),
            $catalog
        );
        $roles = $storedRoles;

        foreach (self::ROLE_FAMILY_KEYS as $roleKey) {
            if (!array_key_exists($roleKey, $input)) {
                continue;
            }

            $roles[$roleKey] = $this->sanitizeTextValue($input[$roleKey]);
        }

        foreach (self::DEFAULT_ROLE_FALLBACKS as $roleKey => $defaultFallback) {
            if (!array_key_exists($roleKey, $input)) {
                continue;
            }

            $roles[$roleKey] = $this->normalizeRoleFallback($input[$roleKey], $defaultFallback);
        }

        foreach (self::ROLE_WEIGHT_KEYS as $roleKey) {
            if (!array_key_exists($roleKey, $input)) {
                continue;
            }

            $roles[$roleKey] = $this->normalizeRoleWeight($input[$roleKey]);
        }

        foreach (self::ROLE_AXIS_KEYS as $roleKey) {
            if (!array_key_exists($roleKey, $input)) {
                continue;
            }

            $roles[$roleKey] = $this->normalizeRoleAxes($input[$roleKey]);
        }

        $roles = $this->normalizeRoleSet($roles, $catalog);

        update_option(self::OPTION_ROLES, $roles, false);

        return $roles;
    }

    /**
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function getAppliedRoles(array $catalog): array
    {
        $settings = $this->readSettings();
        $storedAppliedRoles = is_array($settings['applied_roles'] ?? null)
            ? $settings['applied_roles']
            : [];

        if ($storedAppliedRoles === [] && !empty($settings['auto_apply_roles'])) {
            $storedAppliedRoles = $this->getOptionArray(self::OPTION_ROLES);
        }

        return $this->normalizeRoleSet($this->normalizeInputMap($storedAppliedRoles), $catalog);
    }

    /**
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function ensureAppliedRolesInitialized(array $catalog): array
    {
        $settings = $this->readSettings();
        $storedAppliedRoles = is_array($settings['applied_roles'] ?? null)
            ? $settings['applied_roles']
            : [];

        if ($storedAppliedRoles !== [] || empty($settings['auto_apply_roles'])) {
            return $this->normalizeRoleSet($this->normalizeInputMap($storedAppliedRoles), $catalog);
        }

        $currentRoles = $this->getRoles($catalog);
        $settings['applied_roles'] = $currentRoles;
        $this->persistSettings($settings);

        return $currentRoles;
    }

    /**
     * @param RoleSet $roles
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    public function saveAppliedRoles(array $roles, array $catalog): array
    {
        $settings = $this->readSettings();
        $existingRoles = is_array($settings['applied_roles'] ?? null)
            ? $this->normalizeRoleSet($this->normalizeInputMap($settings['applied_roles']), $catalog)
            : $this->getRoles($catalog);
        $normalizedRoles = $this->normalizeRoleSet(array_replace($existingRoles, $roles), $catalog);
        $settings['applied_roles'] = $normalizedRoles;
        $this->persistSettings($settings);

        return $normalizedRoles;
    }

    /**
     * @param array<int|string, mixed> $catalog
     * @return array{roles: RoleSet, applied_roles: RoleSet}
     */
    public function clearDisabledCapabilityRoleData(
        bool $clearVariableAxes,
        bool $clearMonospaceRole,
        array $catalog
    ): array {
        $availableFamilies = $this->normalizeAvailableRoleFamilies($catalog);
        $roles = $this->normalizeRoleSet(
            $this->getOptionArray(self::OPTION_ROLES),
            $availableFamilies
        );
        $settings = $this->readSettings();
        $appliedRolesInput = is_array($settings['applied_roles'] ?? null)
            ? $this->normalizeInputMap($settings['applied_roles'])
            : [];
        $appliedRoles = $this->normalizeRoleSet($appliedRolesInput, $availableFamilies);

        $roles = $this->clearCapabilityRoleSet($roles, $clearVariableAxes, $clearMonospaceRole, $availableFamilies);
        $appliedRoles = $this->clearCapabilityRoleSet($appliedRoles, $clearVariableAxes, $clearMonospaceRole, $availableFamilies);

        update_option(self::OPTION_ROLES, $roles, false);

        $settings['applied_roles'] = $appliedRoles;
        $this->persistSettings($settings);

        return [
            'roles' => $roles,
            'applied_roles' => $appliedRoles,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function setAutoApplyRoles(bool $enabled): array
    {
        $settings = $this->readSettings();
        $settings['auto_apply_roles'] = $enabled;

        return $this->persistSettings($settings);
    }

    /**
     * @param array<int|string, mixed> $roles
     * @return RoleSet
     */
    public function previewImportedRoles(array $roles): array
    {
        return $this->normalizeImportedRoles($roles);
    }

    /**
     * @param array<int|string, mixed> $roles
     * @return RoleSet
     */
    public function replaceImportedRoles(array $roles): array
    {
        $normalized = $this->normalizeImportedRoles($roles);
        update_option(self::OPTION_ROLES, $normalized, false);

        return $normalized;
    }

    // ─── Private helpers ────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function readSettings(): array
    {
        $value = get_option(self::OPTION_SETTINGS, null);

        if (!is_array($value)) {
            return [];
        }

        return $this->normalizeInputMap($value);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function persistSettings(array $settings): array
    {
        update_option(self::OPTION_SETTINGS, $settings, false);

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function getOptionArray(string $option): array
    {
        $value = get_option($option, null);

        if (is_array($value)) {
            return $this->normalizeInputMap($value);
        }

        return [];
    }

    /**
     * @param array<int|string, mixed> $roles
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    private function normalizeRoleSet(array $roles, array $catalog): array
    {
        $defaults = $this->getDefaultRoles($catalog);
        $normalizedRoles = wp_parse_args($roles, $defaults);
        $normalizedRoles['heading'] = $this->sanitizeTextValue($normalizedRoles['heading'] ?? '');
        $normalizedRoles['body'] = $this->sanitizeTextValue($normalizedRoles['body'] ?? '');
        $normalizedRoles['monospace'] = $this->sanitizeTextValue($normalizedRoles['monospace'] ?? '');
        $normalizedRoles['heading_fallback'] = $this->normalizeRoleFallback($normalizedRoles['heading_fallback'] ?? '', FontUtils::DEFAULT_ROLE_SANS_FALLBACK);
        $normalizedRoles['body_fallback'] = $this->normalizeRoleFallback($normalizedRoles['body_fallback'] ?? '', FontUtils::DEFAULT_ROLE_SANS_FALLBACK);
        $normalizedRoles['monospace_fallback'] = $this->normalizeRoleFallback($normalizedRoles['monospace_fallback'] ?? '', FontUtils::DEFAULT_ROLE_MONOSPACE_FALLBACK);
        $normalizedRoles['heading_weight'] = $this->normalizeRoleWeight($normalizedRoles['heading_weight'] ?? '');
        $normalizedRoles['body_weight'] = $this->normalizeRoleWeight($normalizedRoles['body_weight'] ?? '');
        $normalizedRoles['monospace_weight'] = $this->normalizeRoleWeight($normalizedRoles['monospace_weight'] ?? '');
        $normalizedRoles['heading_axes'] = $this->normalizeRoleAxes($normalizedRoles['heading_axes'] ?? []);
        $normalizedRoles['body_axes'] = $this->normalizeRoleAxes($normalizedRoles['body_axes'] ?? []);
        $normalizedRoles['monospace_axes'] = $this->normalizeRoleAxes($normalizedRoles['monospace_axes'] ?? []);

        return [
            'heading' => $normalizedRoles['heading'],
            'body' => $normalizedRoles['body'],
            'monospace' => $normalizedRoles['monospace'],
            'heading_fallback' => $normalizedRoles['heading_fallback'],
            'body_fallback' => $normalizedRoles['body_fallback'],
            'monospace_fallback' => $normalizedRoles['monospace_fallback'],
            'heading_weight' => $normalizedRoles['heading_weight'],
            'body_weight' => $normalizedRoles['body_weight'],
            'monospace_weight' => $normalizedRoles['monospace_weight'],
            'heading_axes' => $normalizedRoles['heading_axes'],
            'body_axes' => $normalizedRoles['body_axes'],
            'monospace_axes' => $normalizedRoles['monospace_axes'],
        ];
    }

    /**
     * @return RoleAxes
     */
    private function normalizeRoleAxes(mixed $axes): array
    {
        if (is_string($axes) && trim($axes) !== '') {
            $decoded = json_decode($axes, true);

            if (is_array($decoded)) {
                $axes = $decoded;
            }
        }

        $normalizedAxes = [];

        foreach (FontUtils::normalizeVariationDefaults(is_array($axes) ? $axes : []) as $tag => $value) {
            $normalizedAxes[$tag] = is_string($value) ? $value : (string) $value;
        }

        return $normalizedAxes;
    }

    private function normalizeRoleWeight(mixed $weight): string
    {
        $rawWeight = trim(wp_unslash($this->mixedStringValue($weight)));

        if ($rawWeight === '') {
            return '';
        }

        $property = FontUtils::weightVariableName($rawWeight);

        if ($property === '') {
            return '';
        }

        return substr($property, strlen('--weight-'));
    }

    private function normalizeRoleFallback(mixed $value, string $default): string
    {
        $rawValue = trim(wp_unslash($this->mixedStringValue($value)));

        if ($rawValue === '') {
            return $default;
        }

        return FontUtils::sanitizeFallback($rawValue);
    }

    private function sanitizeTextValue(mixed $value): string
    {
        return sanitize_text_field(wp_unslash($this->mixedStringValue($value)));
    }

    /**
     * @param array<int|string, mixed> $catalog
     * @return RoleSet
     */
    private function getDefaultRoles(array $catalog): array
    {
        return self::DEFAULT_ROLE_FALLBACKS + [
            'heading' => '',
            'body' => '',
            'monospace' => '',
            'heading_weight' => '',
            'body_weight' => '',
            'monospace_weight' => '',
            'heading_axes' => [],
            'body_axes' => [],
            'monospace_axes' => [],
        ];
    }

    /**
     * @param array<int|string, mixed> $catalog
     * @return list<string>
     */
    private function normalizeAvailableRoleFamilies(array $catalog): array
    {
        $families = [];

        foreach ($catalog as $key => $entry) {
            if (is_array($entry)) {
                $family = $this->sanitizeTextValue($entry['family'] ?? ($entry['name'] ?? ''));

                if ($family === '' && is_string($key)) {
                    $family = $this->sanitizeTextValue($key);
                }
            } elseif (is_string($entry)) {
                $family = $this->sanitizeTextValue($entry);
            } else {
                $family = is_string($key) ? $this->sanitizeTextValue($key) : '';
            }

            if ($family === '') {
                continue;
            }

            $families[$family] = $family;
        }

        return array_values($families);
    }

    /**
     * @param RoleSet $roles
     * @param list<string> $availableFamilies
     * @return RoleSet
     */
    private function clearCapabilityRoleSet(
        array $roles,
        bool $clearVariableAxes,
        bool $clearMonospaceRole,
        array $availableFamilies
    ): array {
        if ($clearVariableAxes) {
            $roles['heading_axes'] = [];
            $roles['body_axes'] = [];
            $roles['monospace_axes'] = [];
        }

        if ($clearMonospaceRole) {
            $roles['monospace'] = '';
            $roles['monospace_fallback'] = 'monospace';
            $roles['monospace_weight'] = '';
            $roles['monospace_axes'] = [];
        }

        $availableMap = array_fill_keys($availableFamilies, true);

        foreach (self::ROLE_FAMILY_KEYS as $roleKey) {
            $selectedFamily = $this->sanitizeTextValue($roles[$roleKey]);

            if ($selectedFamily !== '' && !isset($availableMap[$selectedFamily])) {
                $roles[$roleKey] = '';

                if ($roleKey === 'monospace') {
                    $roles['monospace_weight'] = '';
                    $roles['monospace_axes'] = [];
                }
            }
        }

        return $roles;
    }

    /**
     * @param array<int|string, mixed> $roles
     * @return RoleSet
     */
    private function normalizeImportedRoles(array $roles): array
    {
        return $this->normalizeRoleSet($roles, []);
    }
}
