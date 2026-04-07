<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

final class LogRepository
{
    public const OPTION_LOG = 'tasty_fonts_log';
    public const LEGACY_OPTION_LOG = 'etch_fonts_log';
    private const MAX_ENTRIES = 100;

    public function add(string $message, array $context = []): void
    {
        $log = $this->all();
        $actionLabel = sanitize_text_field((string) ($context['action_label'] ?? ''));
        $actionUrl = esc_url_raw((string) ($context['action_url'] ?? ''));
        $entry = [
            'time' => current_time('mysql', true),
            'message' => $message,
            'actor' => $this->getActorLabel(),
        ];

        if ($actionLabel !== '' && $actionUrl !== '') {
            $entry['action_label'] = $actionLabel;
            $entry['action_url'] = $actionUrl;
        }

        array_unshift(
            $log,
            $entry
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
        $value = get_option($option, null);

        if (is_array($value)) {
            return $value;
        }

        $legacyValue = get_option(self::LEGACY_OPTION_LOG, null);

        if (!is_array($legacyValue)) {
            return [];
        }

        update_option($option, $legacyValue, false);

        return $legacyValue;
    }

    private function getActorLabel(): string
    {
        if (!is_user_logged_in()) {
            return __('System', 'tasty-fonts');
        }

        $user = wp_get_current_user();

        if (!$user instanceof \WP_User || !$user->exists()) {
            return __('System', 'tasty-fonts');
        }

        $displayName = trim((string) $user->display_name);

        if ($displayName !== '') {
            return $displayName;
        }

        $userLogin = trim((string) $user->user_login);

        if ($userLogin !== '') {
            return $userLogin;
        }

        return __('System', 'tasty-fonts');
    }
}
