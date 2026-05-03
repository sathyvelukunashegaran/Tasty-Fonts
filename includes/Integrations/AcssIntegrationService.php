<?php

declare(strict_types=1);

namespace TastyFonts\Integrations;

defined('ABSPATH') || exit;

use WP_Error;

/**
 * @phpstan-type FontSettings array<string, string>
 * @phpstan-type IntegrationState array<string, bool|string|FontSettings>
 * @phpstan-type EditorStyleList list<string>
 */
final class AcssIntegrationService implements EditorIntegrationInterface
{
    public const RUNTIME_STYLESHEET_HANDLE = 'automaticcss-core-css';
    public const OPTION_HEADING_FONT_FAMILY = 'heading-font-family';
    public const OPTION_TEXT_FONT_FAMILY = 'text-font-family';
    public const OPTION_HEADING_FONT_WEIGHT = 'heading-weight';
    public const OPTION_TEXT_FONT_WEIGHT = 'text-font-weight';
    public const DESIRED_HEADING_VALUE = 'var(--font-heading)';
    public const DESIRED_TEXT_VALUE = 'var(--font-body)';
    public const DESIRED_HEADING_WEIGHT_VALUE = 'var(--font-heading-weight)';
    public const DESIRED_TEXT_WEIGHT_VALUE = 'var(--font-body-weight)';
    private const EDITOR_HEADING_SELECTOR = 'body :is(h1, h2, h3, h4, h5, h6, .editor-post-title, .wp-block-post-title)';

    public function isAvailable(): bool
    {
        $available = class_exists(\Automatic_CSS\API::class);

        if (function_exists('apply_filters')) {
            $available = (bool) apply_filters('tasty_fonts_acss_integration_available', $available);
        }

        return $available;
    }

    /**
     * @return IntegrationState
     */
    public function readState(array $settings): array
    {
        $available = $this->isAvailable();
        $sitewideRolesEnabled = !empty($settings['auto_apply_roles']);
        $syncEnabled = ($settings['acss_font_role_sync_enabled'] ?? null) === true;
        $syncApplied = !empty($settings['acss_font_role_sync_applied']);
        $effectiveEnabled = $available && $syncEnabled;
        $current = $available ? $this->getCurrentSettings() : $this->emptySettings();
        $desired = $this->desiredSettings();
        $synced = $available
            && $current['heading'] === (string) ($desired[self::OPTION_HEADING_FONT_FAMILY] ?? '')
            && $current['body'] === (string) ($desired[self::OPTION_TEXT_FONT_FAMILY] ?? '')
            && $current['heading_weight'] === (string) ($desired[self::OPTION_HEADING_FONT_WEIGHT] ?? '')
            && $current['body_weight'] === (string) ($desired[self::OPTION_TEXT_FONT_WEIGHT] ?? '');

        return array_merge(IntegrationStatus::fromState(
            $available,
            $syncEnabled,
            $synced,
            $syncApplied,
            $sitewideRolesEnabled
        )->toArray(), [
            'enabled' => $effectiveEnabled,
            'sitewide_roles_enabled' => $sitewideRolesEnabled,
            'current' => $current,
            'desired' => $desired,
            'status' => $this->resolveStatus($available, $effectiveEnabled, $syncApplied, $sitewideRolesEnabled, $synced),
        ]);
    }

    /**
     * @return FontSettings|WP_Error
     */
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

    /**
     * @return FontSettings|WP_Error
     */
    public function restoreFontSettings(
        string $headingValue,
        string $bodyValue,
        string $headingWeightValue = '',
        string $bodyWeightValue = ''
    ): array|WP_Error
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
                self::OPTION_HEADING_FONT_WEIGHT => $headingWeightValue,
                self::OPTION_TEXT_FONT_WEIGHT => $bodyWeightValue,
            ]
        );
    }

    /**
     * Clear Tasty-managed Automatic.css font settings so Automatic.css can fall back to its defaults.
     *
     * @return FontSettings|WP_Error
     */
    public function restoreDefaultFontSettings(): array|WP_Error
    {
        if (!$this->isAvailable()) {
            return new WP_Error(
                'tasty_fonts_acss_unavailable',
                __('Automatic.css is not active, so its default font settings could not be restored.', 'tasty-fonts')
            );
        }

        return $this->updateSettings(
            [
                self::OPTION_HEADING_FONT_FAMILY => '',
                self::OPTION_TEXT_FONT_FAMILY => '',
                self::OPTION_HEADING_FONT_WEIGHT => '',
                self::OPTION_TEXT_FONT_WEIGHT => '',
            ]
        );
    }

    /**
     * @return array<string, string>
     */
    public function desiredSettings(): array
    {
        return [
            self::OPTION_HEADING_FONT_FAMILY => self::DESIRED_HEADING_VALUE,
            self::OPTION_TEXT_FONT_FAMILY => self::DESIRED_TEXT_VALUE,
            self::OPTION_HEADING_FONT_WEIGHT => self::DESIRED_HEADING_WEIGHT_VALUE,
            self::OPTION_TEXT_FONT_WEIGHT => self::DESIRED_TEXT_WEIGHT_VALUE,
        ];
    }

    /**
     * @return FontSettings
     */
    public function getCurrentSettings(): array
    {
        if (!$this->isAvailable()) {
            return $this->emptySettings();
        }

        try {
            return [
                'heading' => $this->normalizeValue(\Automatic_CSS\API::get_setting(self::OPTION_HEADING_FONT_FAMILY)),
                'body' => $this->normalizeValue(\Automatic_CSS\API::get_setting(self::OPTION_TEXT_FONT_FAMILY)),
                'heading_weight' => $this->normalizeValue(\Automatic_CSS\API::get_setting(self::OPTION_HEADING_FONT_WEIGHT)),
                'body_weight' => $this->normalizeValue(\Automatic_CSS\API::get_setting(self::OPTION_TEXT_FONT_WEIGHT)),
            ];
        } catch (\Throwable) {
            return $this->emptySettings();
        }
    }

    /**
     * @return EditorStyleList
     */
    public function getManagedEditorStyles(): array
    {
        return [
            'body{font-family:' . self::DESIRED_TEXT_VALUE . ';font-weight:' . self::DESIRED_TEXT_WEIGHT_VALUE . ';}',
            self::EDITOR_HEADING_SELECTOR . '{font-family:' . self::DESIRED_HEADING_VALUE . ';font-weight:' . self::DESIRED_HEADING_WEIGHT_VALUE . ';}',
        ];
    }

    /**
     * @return EditorStyleList
     */
    public function getManagedFrontendStyles(): array
    {
        return [];
    }

    /**
     * Resolve the frontend Automatic.css runtime stylesheet so editor canvases can mirror it.
     *
     * @since 1.10.0
     *
     * @return array{handle:string,url:string,ver:string}|array{}
     */
    public function getRuntimeStylesheet(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $registered = $this->getRegisteredStyles();
        $style = $registered[self::RUNTIME_STYLESHEET_HANDLE] ?? null;
        $url = '';
        $ver = '';

        if (is_array($style)) {
            $url = trim($this->stringValue($style, 'src'));
            $ver = trim($this->stringValue($style, 'ver'));
        }

        if (
            $url === ''
            && class_exists(\Automatic_CSS\Plugin::class)
        ) {
            $baseUrl = trim((string) \Automatic_CSS\Plugin::get_dynamic_css_url());

            if ($baseUrl !== '') {
                $url = trailingslashit($baseUrl) . 'automatic.css';
            }
        }

        if ($url === '') {
            return [];
        }

        return [
            'handle' => self::RUNTIME_STYLESHEET_HANDLE,
            'url' => $url,
            'ver' => $ver,
        ];
    }

    /**
     * @param array<string, string> $settings
     * @return FontSettings|WP_Error
     */
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
            'heading_weight' => (string) ($settings[self::OPTION_HEADING_FONT_WEIGHT] ?? ''),
            'body_weight' => (string) ($settings[self::OPTION_TEXT_FONT_WEIGHT] ?? ''),
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

        if ($syncApplied) {
            return $synced ? 'synced' : 'out_of_sync';
        }

        return 'ready';
    }

    private function normalizeValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @return FontSettings
     */
    private function emptySettings(): array
    {
        return [
            'heading' => '',
            'body' => '',
            'heading_weight' => '',
            'body_weight' => '',
        ];
    }

    /**
     * Read registered stylesheet metadata from WordPress or the test harness.
     *
     * @since 1.10.0
     *
     * @return array<string, array<string, mixed>>
     */
    private function getRegisteredStyles(): array
    {
        if (function_exists('wp_styles')) {
            $styles = wp_styles();

            $registered = [];

            foreach ($styles->registered as $handle => $style) {
                $registered[(string) $handle] = [
                    'src' => $style->src !== false ? (string) $style->src : '',
                    'ver' => (string) $style->ver,
                ];
            }

            return $registered;
        }

        $registered = [];

        foreach (['registeredStyles', 'enqueuedStyles'] as $globalKey) {
            $styles = $GLOBALS[$globalKey] ?? null;

            if (!is_array($styles)) {
                continue;
            }

            foreach ($styles as $handle => $style) {
                if (!is_array($style)) {
                    continue;
                }

                $registered[(string) $handle] = \TastyFonts\Support\FontUtils::normalizeStringKeyedMap($style);
            }
        }

        return $registered;
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
