<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * @phpstan-type AdobeProjectStatus array{state: string, message: string, checked_at: int}
 */
final class AdobeProjectRepository
{
    private const OPTION_SETTINGS = 'tasty_fonts_settings';

    private const ADOBE_DEFAULTS = [
        'adobe_enabled' => false,
        'adobe_project_id' => '',
        'adobe_project_status' => 'empty',
        'adobe_project_status_message' => '',
        'adobe_project_checked_at' => 0,
    ];

    public function isEnabled(): bool
    {
        $settings = $this->readSettings();

        return !empty($settings['adobe_enabled'])
            && $this->sanitizeAdobeProjectId($this->stringValue($settings, 'adobe_project_id')) !== '';
    }

    public function getProjectId(): string
    {
        return $this->sanitizeAdobeProjectId($this->stringValue($this->readSettings(), 'adobe_project_id'));
    }

    /**
     * @return AdobeProjectStatus
     */
    public function getStatus(): array
    {
        $settings = $this->readSettings();
        $projectId = $this->sanitizeAdobeProjectId($this->stringValue($settings, 'adobe_project_id'));

        return [
            'state' => $this->normalizeAdobeProjectStatus(
                $this->stringValue($settings, 'adobe_project_status', 'empty'),
                $projectId
            ),
            'message' => $this->sanitizeStatusMessage($settings['adobe_project_status_message'] ?? ''),
            'checked_at' => $this->normalizeTimestamp($settings['adobe_project_checked_at'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function saveProject(string $projectId, bool $enabled): array
    {
        $settings = $this->readSettings();
        $settings['adobe_project_id'] = $this->sanitizeAdobeProjectId($projectId);
        $settings['adobe_enabled'] = $settings['adobe_project_id'] !== '' ? $enabled : false;
        $settings['adobe_project_status'] = $settings['adobe_project_id'] === '' ? 'empty' : 'unknown';
        $settings['adobe_project_status_message'] = '';
        $settings['adobe_project_checked_at'] = 0;

        return $this->persistSettings($settings);
    }

    /**
     * @return AdobeProjectStatus
     */
    public function saveStatus(string $state, string $message = ''): array
    {
        $settings = $this->readSettings();
        $normalizedState = $this->normalizeAdobeProjectStatus($state, $this->stringValue($settings, 'adobe_project_id'));

        $settings['adobe_project_status'] = $normalizedState;
        $settings['adobe_project_status_message'] = $normalizedState === 'empty'
            ? ''
            : $this->sanitizeStatusMessage($message);
        $settings['adobe_project_checked_at'] = $normalizedState === 'empty' ? 0 : time();

        $this->persistSettings($settings);

        return $this->getStatus();
    }

    /**
     * @return array<string, mixed>
     */
    public function clear(): array
    {
        $settings = $this->readSettings();
        $settings['adobe_enabled'] = false;
        $settings['adobe_project_id'] = '';
        $settings['adobe_project_status'] = 'empty';
        $settings['adobe_project_status_message'] = '';
        $settings['adobe_project_checked_at'] = 0;

        return $this->persistSettings($settings);
    }

    private function sanitizeAdobeProjectId(string $projectId): string
    {
        $projectId = strtolower(trim($projectId));
        $projectId = preg_replace('/[^a-z0-9]+/', '', $projectId) ?? '';

        return trim($projectId);
    }

    private function normalizeAdobeProjectStatus(string $state, string $projectId): string
    {
        $state = sanitize_text_field($state);

        if ($this->sanitizeAdobeProjectId($projectId) === '') {
            return 'empty';
        }

        return in_array($state, ['valid', 'invalid', 'unknown'], true) ? $state : 'unknown';
    }

    private function sanitizeStatusMessage(mixed $message): string
    {
        return sanitize_text_field($this->mixedStringValue($message));
    }

    private function normalizeTimestamp(mixed $value): int
    {
        if (!is_scalar($value) && $value !== null) {
            return 0;
        }

        return max(0, absint($value));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        return $this->mixedStringValue($values[$key], $default);
    }

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

    /**
     * @return array<string, mixed>
     */
    private function readSettings(): array
    {
        $value = get_option(self::OPTION_SETTINGS, null);
        $settings = is_array($value) ? $this->normalizeInputMap($value) : [];

        return $this->normalizeInputMap(wp_parse_args($settings, self::ADOBE_DEFAULTS));
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function persistSettings(array $settings): array
    {
        update_option(self::OPTION_SETTINGS, $settings, false);

        return $settings;
    }
}
