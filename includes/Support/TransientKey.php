<?php

declare(strict_types=1);

namespace TastyFonts\Support;

defined('ABSPATH') || exit;

final class TransientKey
{
    public static function forSite(string $key): string
    {
        return 'blog_' . self::currentBlogId() . '_' . ltrim($key, '_');
    }

    public static function prefixForSite(string $prefix): string
    {
        return self::forSite($prefix);
    }

    private static function currentBlogId(): int
    {
        if (function_exists('get_current_blog_id')) {
            return max(1, (int) get_current_blog_id());
        }

        return 1;
    }
}
