<?php

declare(strict_types=1);

namespace TastyFonts\Support;

defined('ABSPATH') || exit;

/**
 * @phpstan-type RoleLabelList list<string>
 */
final class RoleUsageMessageFormatter
{
    private function __construct()
    {
    }

    /**
     * @param RoleLabelList $roleLabels
     * @return RoleLabelList
     */
    public static function translateRoleLabels(array $roleLabels): array
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

    /**
     * @param RoleLabelList $labels
     */
    public static function formatRoleLabelList(array $labels): string
    {
        $labels = array_values(
            array_filter(
                $labels,
                static fn (string $label): bool => $label !== ''
            )
        );

        if ($labels === []) {
            return '';
        }

        if (count($labels) === 1) {
            return $labels[0];
        }

        $lastLabel = array_pop($labels);

        return implode(', ', $labels) . __(' and ', 'tasty-fonts') . $lastLabel;
    }

    /**
     * @param RoleLabelList $roleLabels
     */
    public static function buildDeleteBlockedMessage(string $familyName, array $roleLabels): string
    {
        $translatedLabels = self::translateRoleLabels($roleLabels);

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
            self::formatRoleLabelList($translatedLabels)
        );
    }

    /**
     * @param RoleLabelList $roleLabels
     */
    public static function buildDeleteLastVariantBlockedMessage(string $familyName, array $roleLabels): string
    {
        $translatedLabels = self::translateRoleLabels($roleLabels);

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
            self::formatRoleLabelList($translatedLabels)
        );
    }
}
