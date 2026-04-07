<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

trait LibraryRenderValueHelpers
{
    protected function roleSetsMatch(array $left, array $right, bool $includeMonospace): bool
    {
        $keys = ['heading', 'body', 'heading_fallback', 'body_fallback'];

        if ($includeMonospace) {
            $keys[] = 'monospace';
            $keys[] = 'monospace_fallback';
        }

        foreach ($keys as $key) {
            if (trim((string) ($left[$key] ?? '')) !== trim((string) ($right[$key] ?? ''))) {
                return false;
            }
        }

        return true;
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
