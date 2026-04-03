<?php

declare(strict_types=1);

namespace EtchFonts\Repository;

final class LogRepository
{
    public const OPTION_LOG = 'etch_fonts_log';
    private const MAX_ENTRIES = 100;

    public function add(string $message): void
    {
        $log = $this->all();

        array_unshift(
            $log,
            [
                'time' => current_time('mysql'),
                'message' => $message,
                'actor' => $this->getActorLabel(),
            ]
        );

        update_option(self::OPTION_LOG, array_slice($log, 0, self::MAX_ENTRIES), false);
    }

    public function all(): array
    {
        return $this->getOptionArray(self::OPTION_LOG);
    }

    public function clear(): void
    {
        update_option(self::OPTION_LOG, [], false);
    }

    private function getOptionArray(string $option): array
    {
        $value = get_option($option, []);

        return is_array($value) ? $value : [];
    }

    private function getActorLabel(): string
    {
        if (!is_user_logged_in()) {
            return __('System', ETCH_FONTS_TEXT_DOMAIN);
        }

        $user = wp_get_current_user();

        if (!$user instanceof \WP_User || !$user->exists()) {
            return __('System', ETCH_FONTS_TEXT_DOMAIN);
        }

        $displayName = trim((string) $user->display_name);

        if ($displayName !== '') {
            return $displayName;
        }

        $userLogin = trim((string) $user->user_login);

        if ($userLogin !== '') {
            return $userLogin;
        }

        return __('System', ETCH_FONTS_TEXT_DOMAIN);
    }
}
