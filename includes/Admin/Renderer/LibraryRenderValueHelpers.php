<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\FallbackResolver;
use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type CatalogFamily from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type CatalogMap from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type RoleSet from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type FamilyFallbackMap from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type NormalizedSettings from \TastyFonts\Repository\SettingsRepository
 */
trait LibraryRenderValueHelpers
{
    /**
     * @param array<int|string, mixed> $left
     * @param array<int|string, mixed> $right
     * @param CatalogMap $catalog
     * @param FamilyFallbackMap $familyFallbacks
     */
    protected function roleSetsMatch(
        array $left,
        array $right,
        bool $includeMonospace,
        array $catalog = [],
        bool $includeVariableAxes = true,
        array $familyFallbacks = []
    ): bool
    {
        $roleKeys = ['heading', 'body'];

        if ($includeMonospace) {
            $roleKeys[] = 'monospace';
        }

        foreach ($roleKeys as $roleKey) {
            if (trim($this->roleStringValue($left, $roleKey)) !== trim($this->roleStringValue($right, $roleKey))) {
                return false;
            }

            if (
                $this->resolveEffectiveRoleFallback($roleKey, $left, $catalog, $familyFallbacks)
                !== $this->resolveEffectiveRoleFallback($roleKey, $right, $catalog, $familyFallbacks)
            ) {
                return false;
            }

            if (
                trim($this->roleStringValue($left, $roleKey . '_weight'))
                !== trim($this->roleStringValue($right, $roleKey . '_weight'))
            ) {
                return false;
            }

            if (
                $includeVariableAxes
                && FontUtils::normalizeVariationDefaults($left[$roleKey . '_axes'] ?? [])
                    !== FontUtils::normalizeVariationDefaults($right[$roleKey . '_axes'] ?? [])
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int|string, mixed> $roles
     * @param CatalogMap $catalog
     * @param FamilyFallbackMap $familyFallbacks
     * @param NormalizedSettings|array{} $settings
     */
    protected function resolveEffectiveRoleFallback(
        string $roleKey,
        array $roles,
        array $catalog = [],
        array $familyFallbacks = [],
        array $settings = []
    ): string
    {
        $settings['family_fallbacks'] = $familyFallbacks;

        return FallbackResolver::roleFallback($roleKey, $roles, $settings, $catalog);
    }

    /**
     * @param CatalogMap $catalog
     * @return CatalogFamily|null
     */
    protected function findCatalogFamilyByName(string $familyName, array $catalog): ?array
    {
        if (is_array($catalog[$familyName] ?? null)) {
            return $catalog[$familyName];
        }

        foreach ($catalog as $family) {
            if (trim($this->mapStringValue($family, 'family')) === $familyName) {
                return $family;
            }
        }

        return null;
    }

    protected function buildFontVariableReference(string $familyName): string
    {
        return FontUtils::fontVariableReference($familyName);
    }

    /**
     * @param CatalogMap $families
     * @param array<int|string, mixed> $roles
     * @return array<string, string>
     */
    protected function buildCategoryAliasOwners(array $families, array $roles, bool $includeMonospace): array
    {
        $owners = [];
        $orderedFamilies = [];
        $usedKeys = [];
        $priorityNames = [
            trim($this->roleStringValue($roles, 'heading')),
            trim($this->roleStringValue($roles, 'body')),
        ];

        if ($includeMonospace) {
            $priorityNames[] = trim($this->roleStringValue($roles, 'monospace'));
        }

        foreach ($priorityNames as $priorityName) {
            if ($priorityName === '') {
                continue;
            }

            foreach ($families as $familyKey => $family) {
                if (isset($usedKeys[$familyKey])) {
                    continue;
                }

                if (trim($this->mapStringValue($family, 'family')) !== $priorityName) {
                    continue;
                }

                $orderedFamilies[] = $family;
                $usedKeys[$familyKey] = true;
                break;
            }
        }

        foreach ($families as $familyKey => $family) {
            if (isset($usedKeys[$familyKey])) {
                continue;
            }

            $orderedFamilies[] = $family;
        }

        foreach ($orderedFamilies as $family) {
            $property = $this->resolveCategoryAliasProperty(
                $this->resolveFamilyCategory((array) $family)
            );

            if (
                $property === ''
                || (!$includeMonospace && $property === '--font-mono')
                || isset($owners[$property])
            ) {
                continue;
            }

            $owners[$property] = trim($this->mapStringValue($family, 'family'));
        }

        return $owners;
    }

    /**
     * @param CatalogFamily $family
     */
    protected function resolveFamilyCategory(array $family): string
    {
        $category = trim($this->mapStringValue($family, 'font_category'));

        if ($category === '' && is_array($family['active_delivery'] ?? null) && is_array($family['active_delivery']['meta'] ?? null)) {
            $category = trim($this->mapStringValue($family['active_delivery']['meta'], 'category'));
        }

        return $category;
    }

    protected function resolveCategoryAliasProperty(string $category): string
    {
        return match (strtolower(trim($category))) {
            'sans-serif', 'sans serif' => '--font-sans',
            'serif', 'slab-serif', 'slab serif' => '--font-serif',
            'monospace' => '--font-mono',
            default => '',
        };
    }

    /**
     * @param array<int|string, mixed> $roles
     */
    protected function roleStringValue(array $roles, string $key): string
    {
        $value = $roles[$key] ?? '';

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @param array<int|string, mixed> $values
     */
    protected function mapStringValue(array $values, int|string $key, string $default = ''): string
    {
        $value = $values[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return $default;
    }
}
