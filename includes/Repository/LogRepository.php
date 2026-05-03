<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

/**
 * @phpstan-type LogContext array<string, mixed>
 * @phpstan-type LogEntry array<string, string>
 * @phpstan-type LogEntryList list<LogEntry>
 */
class LogRepository implements ActivityLogRepositoryInterface
{
    public const OPTION_LOG = 'tasty_fonts_log';
    public const CATEGORY_TRANSFER = 'transfer';
    public const CATEGORY_SETTINGS = 'settings';
    public const CATEGORY_ROLES = 'roles';
    public const CATEGORY_LIBRARY = 'library';
    public const CATEGORY_IMPORT = 'import';
    public const CATEGORY_INTEGRATION = 'integration';
    public const CATEGORY_MAINTENANCE = 'maintenance';
    public const CATEGORY_UPDATE = 'update';
    public const EVENT_SITE_TRANSFER_EXPORT = 'site_transfer_export';
    public const EVENT_SITE_TRANSFER_IMPORT_SUCCESS = 'site_transfer_import_success';
    public const EVENT_SITE_TRANSFER_IMPORT_FAILURE = 'site_transfer_import_failure';
    private const MAX_ENTRIES = 100;
    private const MAX_DETAIL_ROWS = 16;
    private const MAX_DETAIL_VALUE_LENGTH = 1000;

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
        $summary = $this->textValue($context, 'summary');
        $outcome = $this->outcomeValue($context);
        $statusLabel = $this->textValue($context, 'status_label');
        $source = $this->textValue($context, 'source');
        $entityType = sanitize_key($this->stringValue($context, 'entity_type'));
        $entityId = $this->textValue($context, 'entity_id');
        $entityName = $this->textValue($context, 'entity_name');
        $errorCode = sanitize_key($this->stringValue($context, 'error_code'));
        $detailsJson = $this->detailsJson($context['details'] ?? null);
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

        foreach (
            [
                'summary' => $summary,
                'outcome' => $outcome,
                'status_label' => $statusLabel,
                'source' => $source,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'entity_name' => $entityName,
                'error_code' => $errorCode,
                'details_json' => $detailsJson,
            ] as $key => $value
        ) {
            if ($value === '') {
                continue;
            }

            $entry['schema_version'] = '2';
            $entry[$key] = $value;
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

        return [];
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
        if (defined('WP_CLI') && WP_CLI) {
            return __('WP-CLI', 'tasty-fonts');
        }

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
     * @param array<mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function textValue(array $values, string $key): string
    {
        $value = sanitize_text_field($this->stringValue($values, $key));

        return $this->truncate($value, self::MAX_DETAIL_VALUE_LENGTH);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function outcomeValue(array $values): string
    {
        $outcome = sanitize_key($this->stringValue($values, 'outcome'));

        return in_array($outcome, ['success', 'info', 'warning', 'error', 'danger'], true)
            ? $outcome
            : '';
    }

    private function detailsJson(mixed $details): string
    {
        if (!is_array($details)) {
            return '';
        }

        $rows = [];

        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $label = sanitize_text_field($this->stringValue($detail, 'label'));
            $value = sanitize_text_field($this->stringValue($detail, 'value'));

            if ($label === '' || $value === '') {
                continue;
            }

            $kind = sanitize_key($this->stringValue($detail, 'kind', 'text'));
            $rows[] = [
                'label' => $this->truncate($label, 120),
                'value' => $this->truncate($value, self::MAX_DETAIL_VALUE_LENGTH),
                'kind' => $kind !== '' ? $kind : 'text',
            ];

            if (count($rows) >= self::MAX_DETAIL_ROWS) {
                break;
            }
        }

        if ($rows === []) {
            return '';
        }

        $encoded = wp_json_encode($rows);

        return is_string($encoded) ? $encoded : '';
    }

    private function truncate(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return rtrim(substr($value, 0, max(0, $maxLength - 1))) . '…';
    }
}
