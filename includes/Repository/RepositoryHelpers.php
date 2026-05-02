<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * Shared helper methods extracted from repositories to eliminate duplication.
 */
trait RepositoryHelpers
{
    private function mixedStringValue(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeInputMap(mixed $value): array
    {
        return FontUtils::normalizeStringKeyedMap($value);
    }
}
