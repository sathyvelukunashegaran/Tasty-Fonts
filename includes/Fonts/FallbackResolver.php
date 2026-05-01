<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type CatalogFamily from CatalogService
 * @phpstan-import-type CatalogMap from CatalogService
 * @phpstan-import-type FamilyFallbackMap from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type NormalizedSettings from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type RoleSet from \TastyFonts\Repository\SettingsRepository
 */
final class FallbackResolver
{
    private function __construct()
    {
    }

    /**
     * @param array<int|string, mixed>|RoleSet $roles
     * @param array<int|string, mixed>|NormalizedSettings $settings
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     */
    public static function roleFallback(string $roleKey, array $roles, array $settings, array $families = []): string
    {
        $default = self::defaultFallbackForRole($roleKey);
        $familyName = trim(self::stringValue($roles, $roleKey));

        if ($familyName !== '') {
            $configuredFallback = self::familyFallbackOverride($familyName, $settings);

            if ($configuredFallback !== '') {
                return $configuredFallback;
            }
        }

        $globalFallback = self::globalFallbackForRole($roleKey, $settings);

        if ($globalFallback !== '') {
            return $globalFallback;
        }

        if ($familyName !== '') {
            $family = self::findFamilyByName($familyName, $families);

            if ($family !== null) {
                return self::familyFallback($family, $settings);
            }
        }

        return $default;
    }

    /**
     * @param CatalogFamily|array<string, mixed> $family
     * @param array<int|string, mixed>|NormalizedSettings $settings
     */
    public static function familyFallback(array $family, array $settings): string
    {
        $familyName = trim(self::stringValue($family, 'family'));
        $configuredFallback = $familyName !== '' ? self::familyFallbackOverride($familyName, $settings) : '';

        if ($configuredFallback !== '') {
            return $configuredFallback;
        }

        $category = strtolower(trim(self::stringValue($family, 'font_category')));

        if ($category === '' && is_array($family['active_delivery'] ?? null) && is_array($family['active_delivery']['meta'] ?? null)) {
            $category = strtolower(trim(FontUtils::scalarStringValue($family['active_delivery']['meta']['category'] ?? '')));
        }

        return self::globalFallbackForCategory($category, $settings);
    }

    /**
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     * @return CatalogFamily|null
     */
    public static function findFamilyByName(string $familyName, array $families): ?array
    {
        $exactFamily = $families[$familyName] ?? null;

        if ($exactFamily !== null) {
            return $exactFamily;
        }

        foreach ($families as $family) {
            if (trim(self::stringValue($family, 'family')) === $familyName) {
                return $family;
            }
        }

        return null;
    }

    /**
     * @param array<int|string, mixed>|NormalizedSettings $settings
     */
    public static function globalFallbackForRole(string $roleKey, array $settings): string
    {
        $field = match ($roleKey) {
            'heading' => 'fallback_heading',
            'body' => 'fallback_body',
            'monospace' => 'fallback_monospace',
            default => '',
        };

        if ($field === '') {
            return '';
        }

        $fallback = FontUtils::sanitizeOptionalFallback(self::stringValue($settings, $field));

        return $fallback !== '' ? $fallback : self::defaultFallbackForRole($roleKey);
    }

    /**
     * @param array<int|string, mixed>|NormalizedSettings $settings
     */
    public static function globalFallbackForCategory(string $category, array $settings): string
    {
        return match (strtolower(trim($category))) {
            'serif' => self::globalFallbackForRole('heading', $settings),
            'monospace' => self::globalFallbackForRole('monospace', $settings),
            default => self::globalFallbackForRole('body', $settings),
        };
    }

    public static function defaultFallbackForRole(string $roleKey): string
    {
        return $roleKey === 'monospace'
            ? FontUtils::DEFAULT_ROLE_MONOSPACE_FALLBACK
            : FontUtils::DEFAULT_ROLE_SANS_FALLBACK;
    }

    /**
     * @param array<int|string, mixed>|NormalizedSettings $settings
     */
    public static function familyHasFallbackOverride(string $familyName, array $settings): bool
    {
        $fallbacks = FontUtils::normalizeStringMap($settings['family_fallbacks'] ?? []);

        return array_key_exists($familyName, $fallbacks)
            && FontUtils::sanitizeOptionalFallback($fallbacks[$familyName]) !== '';
    }

    /**
     * @param array<int|string, mixed>|NormalizedSettings $settings
     */
    private static function familyFallbackOverride(string $familyName, array $settings): string
    {
        $fallbacks = FontUtils::normalizeStringMap($settings['family_fallbacks'] ?? []);

        if (!array_key_exists($familyName, $fallbacks)) {
            return '';
        }

        return FontUtils::sanitizeOptionalFallback($fallbacks[$familyName]);
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private static function stringValue(array $values, int|string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values) || !is_scalar($values[$key])) {
            return $default;
        }

        return (string) $values[$key];
    }
}
