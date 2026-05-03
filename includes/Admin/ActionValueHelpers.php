<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

trait ActionValueHelpers
{
    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, int|string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values) || !is_scalar($values[$key])) {
            return $default;
        }

        return (string) $values[$key];
    }

    /**
     * @param array<string, mixed> $values
     */
    private function intValue(array $values, int|string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $values) || !is_scalar($values[$key])) {
            return $default;
        }

        return (int) $values[$key];
    }
}
