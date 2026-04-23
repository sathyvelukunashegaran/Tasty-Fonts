<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * @phpstan-type FontEntry array<string, mixed>
 * @phpstan-type FontTypeDescriptor array{
 *     type: string,
 *     label: string,
 *     badge_class: string,
 *     has_variable: bool,
 *     has_static: bool,
 *     is_source_only: bool
 * }
 */
final class FontTypeHelper
{
    /**
     * @param FontEntry $entry
     * @return FontTypeDescriptor
     */
    public static function describeEntry(array $entry, string $context = 'library'): array
    {
        $formats = FontUtils::resolveFormatAvailability($entry);
        $hasStatic = !empty($formats['static']['available']);
        $hasVariable = !empty($formats['variable']['available']);

        return self::describe($hasVariable, $context, $hasStatic);
    }

    /**
     * @return FontTypeDescriptor
     */
    public static function describe(bool $hasVariableMetadata, string $context = 'library', bool $hasStaticMetadata = true): array
    {
        $normalizedContext = strtolower(trim($context));

        if ($hasVariableMetadata && $hasStaticMetadata) {
            return [
                'type' => 'mixed',
                'label' => __('Static + Variable', 'tasty-fonts'),
                'badge_class' => 'is-success',
                'has_variable' => true,
                'has_static' => true,
                'is_source_only' => false,
            ];
        }

        if (!$hasVariableMetadata) {
            return [
                'type' => 'static',
                'label' => __('Static', 'tasty-fonts'),
                'badge_class' => '',
                'has_variable' => false,
                'has_static' => true,
                'is_source_only' => false,
            ];
        }

        if ($normalizedContext === 'bunny') {
            return [
                'type' => 'variable',
                'label' => __('Variable Source', 'tasty-fonts'),
                'badge_class' => 'is-warning',
                'has_variable' => true,
                'has_static' => $hasStaticMetadata,
                'is_source_only' => true,
            ];
        }

        return [
            'type' => 'variable',
            'label' => __('Variable', 'tasty-fonts'),
            'badge_class' => 'is-role',
            'has_variable' => true,
            'has_static' => $hasStaticMetadata,
            'is_source_only' => false,
        ];
    }

    /**
     * @param FontEntry $entry
     */
    public static function entryHasVariableMetadata(array $entry): bool
    {
        if (!empty($entry['has_variable_faces'])) {
            return true;
        }

        if (FontUtils::normalizeAxesMap($entry['variation_axes'] ?? []) !== []) {
            return true;
        }

        if (!empty($entry['is_variable'])) {
            return true;
        }

        if (FontUtils::normalizeAxesMap($entry['axes'] ?? []) !== []) {
            return true;
        }

        foreach ((array) ($entry['faces'] ?? []) as $face) {
            if (!is_array($face)) {
                continue;
            }

            if (!empty($face['is_variable']) || FontUtils::normalizeAxesMap($face['axes'] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param FontEntry|null $entry
     */
    public static function buildSelectorOptionLabel(string $familyName, ?array $entry = null, string $context = 'library'): string
    {
        $trimmedFamilyName = trim($familyName);

        if ($trimmedFamilyName === '' || !is_array($entry) || $entry === []) {
            return $trimmedFamilyName;
        }

        $descriptor = self::describeEntry($entry, $context);

        return $trimmedFamilyName . ' · ' . $descriptor['label'];
    }
}
