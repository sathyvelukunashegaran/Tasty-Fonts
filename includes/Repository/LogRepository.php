<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

/**
 * @phpstan-type LogContext array<string, mixed>
 * @phpstan-type LogEntry array<string, string>
 * @phpstan-type LogEntryList list<LogEntry>
 */
final class LogRepository
{
    public const OPTION_LOG = 'tasty_fonts_log';
    public const LEGACY_OPTION_LOG = 'etch_fonts_log';
    public const CATEGORY_TRANSFER = 'transfer';
    public const EVENT_SITE_TRANSFER_EXPORT = 'site_transfer_export';
    public const EVENT_SITE_TRANSFER_IMPORT_SUCCESS = 'site_transfer_import_success';
    public const EVENT_SITE_TRANSFER_IMPORT_FAILURE = 'site_transfer_import_failure';
    private const MAX_ENTRIES = 100;

    /**
     * @param LogContext $context
     */
    public function add(string $message, array $context = []): void
    {
        $log = $this->all();
        $actionLabel = sanitize_text_field($this->stringValue($context, 'action_label'));
        $actionUrl = esc_url_raw($this->stringValue($context, 'action_url'));
        $category = sanitize_key($this->stringValue($context, 'category'));
        $event = sanitize_key($this->stringValue($context, 'event'));
        $entry = [
            'time' => current_time('mysql', true),
            'message' => $message,
            'actor' => $this->getActorLabel(),
        ];

        if ($category !== '') {
            $entry['category'] = $category;
        }

        if ($event !== '') {
            $entry['event'] = $event;
        }

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

    /**
     * @return LogEntryList
     */
    public function all(): array
    {
        return $this->getOptionArray(self::OPTION_LOG);
    }

    public function clear(): void
    {
        update_option(self::OPTION_LOG, [], false);
    }

    /**
     * @return LogEntryList
     */
    private function getOptionArray(string $option): array
    {
        $value = get_option($option, null);

        if (is_array($value)) {
            return $this->normalizeLogEntryList($value);
        }

        $legacyValue = get_option(self::LEGACY_OPTION_LOG, null);

        if (!is_array($legacyValue)) {
            return [];
        }

        update_option($option, $legacyValue, false);

        return $this->normalizeLogEntryList($legacyValue);
    }

    /**
     * @param mixed $entries
     * @return LogEntryList
     */
    private function normalizeLogEntryList(mixed $entries): array
    {
        if (!is_array($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $normalizedEntry = [];

            foreach ($entry as $key => $value) {
                if (!is_string($key) || !is_scalar($value)) {
                    continue;
                }

                $normalizedEntry[$key] = (string) $value;
            }

            if ($normalizedEntry !== []) {
                $normalized[] = $normalizedEntry;
            }
        }

        return $normalized;
    }

    private function getActorLabel(): string
    {
        if (!is_user_logged_in()) {
            return __('System', 'tasty-fonts');
        }

        $user = wp_get_current_user();

        try {
            if (!$user->exists()) {
                return __('System', 'tasty-fonts');
            }
        } catch (\Throwable) {
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

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }
}
