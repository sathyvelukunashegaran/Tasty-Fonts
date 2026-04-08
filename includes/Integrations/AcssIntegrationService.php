<?php

declare(strict_types=1);

namespace TastyFonts\Integrations;

defined('ABSPATH') || exit;

use WP_Error;

final class AcssIntegrationService
{
    public const OPTION_HEADING_FONT_FAMILY = 'heading-font-family';
    public const OPTION_TEXT_FONT_FAMILY = 'text-font-family';
    public const DESIRED_HEADING_VALUE = 'var(--font-heading)';
    public const DESIRED_TEXT_VALUE = 'var(--font-body)';

    public function isAvailable(): bool
    {
        $available = class_exists(\Automatic_CSS\API::class);

        if (function_exists('apply_filters')) {
            $available = (bool) apply_filters('tasty_fonts_acss_integration_available', $available);
        }

        return $available;
    }

    public function readState(bool $sitewideRolesEnabled, bool $syncEnabled, bool $syncApplied): array
    {
        $available = $this->isAvailable();
        $current = $available ? $this->getCurrentSettings() : $this->emptySettings();
        $desired = $this->desiredSettings();
        $synced = $available
            && $current['heading'] === (string) ($desired[self::OPTION_HEADING_FONT_FAMILY] ?? '')
            && $current['body'] === (string) ($desired[self::OPTION_TEXT_FONT_FAMILY] ?? '');

        return [
            'available' => $available,
            'enabled' => $syncEnabled,
            'applied' => $syncApplied,
            'sitewide_roles_enabled' => $sitewideRolesEnabled,
            'current' => $current,
            'desired' => $desired,
            'synced' => $synced,
            'status' => $this->resolveStatus($available, $syncEnabled, $syncApplied, $sitewideRolesEnabled, $synced),
        ];
    }

    public function applyRoleVariableSync(): array|WP_Error
    {
        if (!$this->isAvailable()) {
            return new WP_Error(
                'tasty_fonts_acss_unavailable',
                __('Automatic.css is not active, so its font settings could not be updated.', 'tasty-fonts')
            );
        }

        return $this->updateSettings($this->desiredSettings());
    }

    public function restoreFontSettings(string $headingValue, string $bodyValue): array|WP_Error
    {
        if (!$this->isAvailable()) {
            return new WP_Error(
                'tasty_fonts_acss_unavailable',
                __('Automatic.css is not active, so its previous font settings could not be restored.', 'tasty-fonts')
            );
        }

        return $this->updateSettings(
            [
                self::OPTION_HEADING_FONT_FAMILY => $headingValue,
                self::OPTION_TEXT_FONT_FAMILY => $bodyValue,
            ]
        );
    }

    public function desiredSettings(): array
    {
        return [
            self::OPTION_HEADING_FONT_FAMILY => self::DESIRED_HEADING_VALUE,
            self::OPTION_TEXT_FONT_FAMILY => self::DESIRED_TEXT_VALUE,
        ];
    }

    public function getCurrentSettings(): array
    {
        if (!$this->isAvailable()) {
            return $this->emptySettings();
        }

        try {
            return [
                'heading' => $this->normalizeValue(\Automatic_CSS\API::get_setting(self::OPTION_HEADING_FONT_FAMILY)),
                'body' => $this->normalizeValue(\Automatic_CSS\API::get_setting(self::OPTION_TEXT_FONT_FAMILY)),
            ];
        } catch (\Throwable) {
            return $this->emptySettings();
        }
    }

    private function updateSettings(array $settings): array|WP_Error
    {
        try {
            \Automatic_CSS\API::update_settings($settings, ['regenerate_css' => true]);
        } catch (\Throwable $error) {
            return new WP_Error(
                'tasty_fonts_acss_sync_failed',
                sprintf(
                    __('Automatic.css settings could not be updated: %s', 'tasty-fonts'),
                    $error->getMessage()
                )
            );
        }

        return [
            'heading' => (string) ($settings[self::OPTION_HEADING_FONT_FAMILY] ?? ''),
            'body' => (string) ($settings[self::OPTION_TEXT_FONT_FAMILY] ?? ''),
        ];
    }

    private function resolveStatus(
        bool $available,
        bool $syncEnabled,
        bool $syncApplied,
        bool $sitewideRolesEnabled,
        bool $synced
    ): string {
        if (!$available) {
            return 'unavailable';
        }

        if (!$syncEnabled) {
            return 'disabled';
        }

        if (!$sitewideRolesEnabled) {
            return 'waiting_for_sitewide_roles';
        }

        if ($syncApplied && $synced) {
            return 'synced';
        }

        if ($syncApplied && !$synced) {
            return 'out_of_sync';
        }

        return 'ready';
    }

    private function normalizeValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function emptySettings(): array
    {
        return [
            'heading' => '',
            'body' => '',
        ];
    }
}
