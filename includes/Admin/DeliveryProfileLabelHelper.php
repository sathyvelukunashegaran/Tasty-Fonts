<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

final class DeliveryProfileLabelHelper
{
    public const FORMAT_NEVER = 'never';
    public const FORMAT_AUTO = 'auto';
    public const FORMAT_ALWAYS = 'always';

    public static function baseLabel(string $storedLabel): string
    {
        $normalized = trim($storedLabel);

        if ($normalized === '') {
            return '';
        }

        return match ($normalized) {
            'Self-hosted' => __('Self-hosted', 'tasty-fonts'),
            'Self-hosted (Google import)' => __('Self-hosted (Google import)', 'tasty-fonts'),
            'Google CDN' => __('Google CDN', 'tasty-fonts'),
            'Self-hosted (Bunny import)' => __('Self-hosted (Bunny import)', 'tasty-fonts'),
            'Bunny CDN' => __('Bunny CDN', 'tasty-fonts'),
            'Adobe-hosted' => __('Adobe-hosted', 'tasty-fonts'),
            default => $normalized,
        };
    }

    /**
     * @param array<string, mixed> $profile
     */
    public static function formatLabel(array $profile): string
    {
        return self::formatToken($profile) === 'variable'
            ? __('Variable', 'tasty-fonts')
            : __('Static', 'tasty-fonts');
    }

    /**
     * @param array<string, mixed> $profile
     * @param list<array<string, mixed>> $siblingProfiles
     */
    public static function displayLabel(array $profile, array $siblingProfiles = [], string $formatPolicy = self::FORMAT_AUTO): string
    {
        $baseLabel = self::baseLabel(FontUtils::scalarStringValue($profile['label'] ?? ''));

        if ($baseLabel === '') {
            return '';
        }

        if ($formatPolicy === self::FORMAT_NEVER) {
            return $baseLabel;
        }

        if ($formatPolicy === self::FORMAT_ALWAYS || self::needsFormatDisambiguation($profile, $siblingProfiles)) {
            return trim($baseLabel . ' · ' . self::formatLabel($profile));
        }

        return $baseLabel;
    }

    /**
     * @param array<string, mixed> $profile
     */
    private static function formatToken(array $profile): string
    {
        return FontUtils::resolveProfileFormat($profile) === 'variable' ? 'variable' : 'static';
    }

    /**
     * @param array<string, mixed> $profile
     * @param list<array<string, mixed>> $siblingProfiles
     */
    private static function needsFormatDisambiguation(array $profile, array $siblingProfiles): bool
    {
        $baseLabel = self::baseLabel(FontUtils::scalarStringValue($profile['label'] ?? ''));

        if ($baseLabel === '') {
            return false;
        }

        $formats = [];

        foreach ($siblingProfiles as $siblingProfile) {
            if (self::baseLabel(FontUtils::scalarStringValue($siblingProfile['label'] ?? '')) !== $baseLabel) {
                continue;
            }

            $formats[self::formatToken($siblingProfile)] = true;
        }

        return count($formats) > 1;
    }
}
