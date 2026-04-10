<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

trait LibraryRenderValueHelpers
{
    protected function roleSetsMatch(
        array $left,
        array $right,
        bool $includeMonospace,
        array $catalog = [],
        bool $includeVariableAxes = true
    ): bool
    {
        $roleKeys = ['heading', 'body'];

        if ($includeMonospace) {
            $roleKeys[] = 'monospace';
        }

        foreach ($roleKeys as $roleKey) {
            if (trim((string) ($left[$roleKey] ?? '')) !== trim((string) ($right[$roleKey] ?? ''))) {
                return false;
            }

            if (
                $this->normalizeRoleFallbackForComparison($roleKey, $left)
                !== $this->normalizeRoleFallbackForComparison($roleKey, $right)
            ) {
                return false;
            }

            if (
                $this->resolveRoleDeliveryIdForComparison($roleKey, $left, $catalog)
                !== $this->resolveRoleDeliveryIdForComparison($roleKey, $right, $catalog)
            ) {
                return false;
            }

            if (
                trim((string) ($left[$roleKey . '_weight'] ?? ''))
                !== trim((string) ($right[$roleKey . '_weight'] ?? ''))
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

    protected function normalizeRoleFallbackForComparison(string $roleKey, array $roles): string
    {
        $default = $roleKey === 'monospace' ? 'monospace' : 'sans-serif';
        $fallback = trim((string) ($roles[$roleKey . '_fallback'] ?? ''));

        return $fallback !== '' ? FontUtils::sanitizeFallback($fallback) : $default;
    }

    protected function resolveRoleDeliveryIdForComparison(string $roleKey, array $roles, array $catalog): string
    {
        $familyName = trim((string) ($roles[$roleKey] ?? ''));
        $savedDeliveryId = trim((string) ($roles[$roleKey . '_delivery_id'] ?? ''));

        if ($familyName === '') {
            return '';
        }

        $family = $this->findCatalogFamilyByName($familyName, $catalog);

        if (!is_array($family)) {
            return $savedDeliveryId;
        }

        $deliveryIds = [];

        foreach ((array) ($family['available_deliveries'] ?? []) as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $deliveryId = trim((string) ($profile['id'] ?? ''));

            if ($deliveryId !== '') {
                $deliveryIds[] = $deliveryId;
            }
        }

        if ($savedDeliveryId !== '' && in_array($savedDeliveryId, $deliveryIds, true)) {
            return $savedDeliveryId;
        }

        $activeDeliveryId = trim((string) ($family['active_delivery_id'] ?? ''));

        if ($activeDeliveryId !== '' && in_array($activeDeliveryId, $deliveryIds, true)) {
            return $activeDeliveryId;
        }

        return $deliveryIds[0] ?? $savedDeliveryId;
    }

    protected function findCatalogFamilyByName(string $familyName, array $catalog): ?array
    {
        if (is_array($catalog[$familyName] ?? null)) {
            return $catalog[$familyName];
        }

        foreach ($catalog as $family) {
            if (!is_array($family)) {
                continue;
            }

            if (trim((string) ($family['family'] ?? '')) === $familyName) {
                return $family;
            }
        }

        return null;
    }

    protected function buildFontVariableReference(string $familyName): string
    {
        return FontUtils::fontVariableReference($familyName);
    }

    protected function buildCategoryAliasOwners(array $families, array $roles, bool $includeMonospace): array
    {
        $owners = [];
        $orderedFamilies = [];
        $usedKeys = [];
        $priorityNames = [
            trim((string) ($roles['heading'] ?? '')),
            trim((string) ($roles['body'] ?? '')),
        ];

        if ($includeMonospace) {
            $priorityNames[] = trim((string) ($roles['monospace'] ?? ''));
        }

        foreach ($priorityNames as $priorityName) {
            if ($priorityName === '') {
                continue;
            }

            foreach ($families as $familyKey => $family) {
                if (!is_array($family) || isset($usedKeys[$familyKey])) {
                    continue;
                }

                if (trim((string) ($family['family'] ?? '')) !== $priorityName) {
                    continue;
                }

                $orderedFamilies[] = $family;
                $usedKeys[$familyKey] = true;
                break;
            }
        }

        foreach ($families as $familyKey => $family) {
            if (!is_array($family) || isset($usedKeys[$familyKey])) {
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

            $owners[$property] = trim((string) ($family['family'] ?? ''));
        }

        return $owners;
    }

    protected function resolveFamilyCategory(array $family): string
    {
        $category = trim((string) ($family['font_category'] ?? ''));

        if ($category === '' && is_array($family['active_delivery'] ?? null) && is_array($family['active_delivery']['meta'] ?? null)) {
            $category = trim((string) ($family['active_delivery']['meta']['category'] ?? ''));
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
}
