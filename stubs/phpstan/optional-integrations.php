<?php

declare(strict_types=1);

namespace Automatic_CSS {

    final class API
    {
        public static function get_setting(string $key): mixed
        {
            return null;
        }

        public static function update_settings(array $settings, array $args = []): void
        {
        }
    }

    final class Plugin
    {
        public static function get_dynamic_css_url(): string
        {
            return '';
        }
    }
}

namespace {

    class ECF_Plugin
    {
        public static function get_font_families(): array
        {
            return [];
        }
    }

    function ct_get_global_settings(): array
    {
        return [];
    }
}
