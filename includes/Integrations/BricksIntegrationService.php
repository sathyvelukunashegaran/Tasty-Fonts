<?php

declare(strict_types=1);

namespace TastyFonts\Integrations;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;
use WP_Error;

final class BricksIntegrationService
{
    public const OPTION_SYNC_STATE = 'tasty_fonts_bricks_sync_state_v1';
    public const OPTION_GLOBAL_SETTINGS = 'bricks_global_settings';
    public const OPTION_GLOBAL_VARIABLES = 'bricks_global_variables';
    public const OPTION_GLOBAL_VARIABLE_CATEGORIES = 'bricks_global_variables_categories';
    public const FEATURE_SELECTORS = 'bricks_selector_fonts_enabled';
    public const FEATURE_BUILDER_PREVIEW = 'bricks_builder_preview_enabled';
    public const FEATURE_THEME_STYLES = 'bricks_theme_styles_sync_enabled';
    public const FEATURE_DISABLE_GOOGLE_FONTS = 'bricks_disable_google_fonts_enabled';
    public const VARIABLE_CATEGORY_ID = 'tasty-fonts';
    public const VARIABLE_NAME_HEADING = 'tasty-font-heading';
    public const VARIABLE_NAME_BODY = 'tasty-font-body';
    public const VARIABLE_NAME_HEADING_WEIGHT = 'tasty-font-heading-weight';
    public const VARIABLE_NAME_BODY_WEIGHT = 'tasty-font-body-weight';
    public const DESIRED_HEADING_VALUE = 'var(--font-heading)';
    public const DESIRED_BODY_VALUE = 'var(--font-body)';
    public const DESIRED_HEADING_WEIGHT_VALUE = 'var(--font-heading-weight)';
    public const DESIRED_BODY_WEIGHT_VALUE = 'var(--font-body-weight)';
    public const MANAGED_THEME_STYLE_ID = 'tasty-fonts-managed';
    public const MANAGED_THEME_STYLE_LABEL = 'Tasty Fonts';
    public const TARGET_MODE_MANAGED = 'managed';
    public const TARGET_MODE_SELECTED = 'selected';
    public const TARGET_MODE_ALL = 'all';
    private const STATE_TEMPLATE = [
        'theme_styles' => [
            'applied' => false,
            'previous_styles' => [],
            'target_mode' => 'managed',
            'target_style_id' => '',
            'managed_style_created' => false,
        ],
        'google_fonts' => [
            'applied' => false,
            'previous_disabled_google_fonts' => null,
        ],
    ];
    private const BODY_TYPOGRAPHY_KEYS = ['typographyBody', 'typographyLead'];
    private const HEADING_TYPOGRAPHY_KEYS = [
        'typographyHeadings',
        'typographyHeadingH1',
        'typographyHeadingH2',
        'typographyHeadingH3',
        'typographyHeadingH4',
        'typographyHeadingH5',
        'typographyHeadingH6',
        'typographyHero',
    ];
    private const EDITOR_HEADING_SELECTOR = 'body :is(h1, h2, h3, h4, h5, h6, .editor-post-title, .wp-block-post-title)';

    public function isAvailable(): bool
    {
        $available = class_exists(\Bricks\Database::class) && defined('BRICKS_DB_THEME_STYLES');

        if (function_exists('apply_filters')) {
            $available = (bool) apply_filters('tasty_fonts_bricks_integration_available', $available);
        }

        return $available;
    }

    public function readState(array $settings): array
    {
        $available = $this->isAvailable();
        $masterEnabled = $this->masterEnabled($settings);
        $sitewideRolesEnabled = !empty($settings['auto_apply_roles']);
        $syncState = $this->getSyncState();
        $targetMode = $this->resolveThemeStyleTargetMode($settings, $syncState);
        $targetStyleId = $this->resolveThemeStyleTargetId($settings, $syncState, null, $targetMode);
        $currentThemeStyles = $targetMode === self::TARGET_MODE_ALL
            ? $this->getAllThemeStyleTypographyValues()
            : $this->getTargetThemeStyleTypographyValues($targetStyleId);
        $themeStyleSummary = $this->getThemeStyleSummary($settings, $syncState);
        $googleFontsDisabled = $this->isGoogleFontsDisabled();
        $themeStylesSynced = $this->normalizeThemeStyleTargetMode((string) ($syncState['theme_styles']['target_mode'] ?? '')) === $targetMode
            && $this->normalizeThemeStyleTargetId((string) ($syncState['theme_styles']['target_style_id'] ?? '')) === $targetStyleId
            && $this->managedThemeStylesMatchDesired($targetStyleId, $targetMode);

        return [
            'available' => $available,
            'enabled' => $available && $masterEnabled,
            'configured' => ($settings['bricks_integration_enabled'] ?? null) !== null,
            'status' => !$available ? 'unavailable' : ($masterEnabled ? 'active' : 'disabled'),
            'selectors' => $this->buildFeatureState(
                $available,
                $masterEnabled,
                $this->selectorsEnabled($settings)
            ),
            'builder_preview' => $this->buildFeatureState(
                $available,
                $masterEnabled,
                $this->builderPreviewEnabled($settings)
            ),
            'theme_styles' => array_merge($this->buildManagedFeatureState(
                $available,
                $masterEnabled,
                $sitewideRolesEnabled,
                $this->themeStylesSyncEnabled($settings),
                !empty($syncState['theme_styles']['applied']),
                $themeStylesSynced,
                $currentThemeStyles,
                $this->desiredThemeStyleValues()
            ), [
                'summary' => $themeStyleSummary,
            ]),
            'google_fonts' => [
                'enabled' => $available && $masterEnabled && $this->disableGoogleFontsEnabled($settings),
                'configured' => !empty($settings[self::FEATURE_DISABLE_GOOGLE_FONTS]),
                'applied' => !empty($syncState['google_fonts']['applied']),
                'status' => !$available
                    ? 'unavailable'
                    : (!$masterEnabled || !$this->disableGoogleFontsEnabled($settings)
                        ? 'disabled'
                        : ($googleFontsDisabled ? 'synced' : 'ready')),
                'current' => [
                    'google_fonts_disabled' => $googleFontsDisabled,
                ],
            ],
        ];
    }

    public function selectorsEnabled(array $settings): bool
    {
        return $this->masterEnabled($settings);
    }

    public function builderPreviewEnabled(array $settings): bool
    {
        return $this->masterEnabled($settings);
    }

    public function themeStylesSyncEnabled(array $settings): bool
    {
        return $this->masterEnabled($settings) && !empty($settings[self::FEATURE_THEME_STYLES]);
    }

    public function disableGoogleFontsEnabled(array $settings): bool
    {
        return $this->masterEnabled($settings) && !empty($settings[self::FEATURE_DISABLE_GOOGLE_FONTS]);
    }

    public function managedEditorStylesActive(array $settings): bool
    {
        if (!$this->themeStylesSyncEnabled($settings) || empty($settings['auto_apply_roles']) || !$this->isAvailable()) {
            return false;
        }

        $syncState = $this->getSyncState();
        $targetStyleId = $this->resolveThemeStyleTargetId($settings, $syncState);

        return !empty($syncState['theme_styles']['applied']) && $this->managedThemeStylesMatchDesired($targetStyleId, $this->resolveThemeStyleTargetMode($settings, $syncState));
    }

    public function managedFrontendStylesActive(array $settings): bool
    {
        if (!$this->themeStylesSyncEnabled($settings) || empty($settings['auto_apply_roles']) || !$this->isAvailable()) {
            return false;
        }

        $syncState = $this->getSyncState();

        return !empty($syncState['theme_styles']['applied']);
    }

    public function getManagedEditorStyles(): array
    {
        return [
            'body{font-family:var(--font-body);font-weight:var(--font-body-weight);}',
            self::EDITOR_HEADING_SELECTOR . '{font-family:var(--font-heading);font-weight:var(--font-heading-weight);}',
        ];
    }

    public function getManagedFrontendStyles(): array
    {
        return [
            'body,.bricks-type-lead{font-family:var(--font-body);font-weight:var(--font-body-weight);}',
            'h1,h2,h3,h4,h5,h6,.bricks-type-hero{font-family:var(--font-heading);font-weight:var(--font-heading-weight);}',
        ];
    }

    public function filterStandardFonts(array $fonts, array $runtimeFamilies): array
    {
        $merged = [];
        $seen = [];

        foreach ($this->getSelectorFamilyNames($runtimeFamilies) as $familyName) {
            if (isset($seen[$familyName])) {
                continue;
            }

            $seen[$familyName] = true;
            $merged[] = $familyName;
        }

        foreach ($fonts as $font) {
            $name = is_string($font) ? trim($font) : '';

            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $merged[] = $name;
        }

        return $merged;
    }

    public function getSelectorFamilyNames(array $runtimeFamilies): array
    {
        return $this->runtimeFamilyNames($runtimeFamilies);
    }

    public function getEditorStyles(array $runtimeFamilies): array
    {
        $runtimeLookup = $this->runtimeFamilyLookup($runtimeFamilies);

        if ($runtimeLookup === []) {
            return [];
        }

        $settings = $this->getTargetThemeStyleSettings($this->resolveThemeStyleTargetId([], $this->getSyncState()));

        if ($settings === []) {
            return [];
        }

        $styles = [];
        $bodyFamily = $this->managedFamilyName($settings['typography']['typographyBody']['font-family'] ?? '', $runtimeLookup);

        if ($bodyFamily !== '') {
            $styles[] = $this->buildEditorRule('body', $bodyFamily);
        }

        foreach (['H1', 'H2', 'H3', 'H4', 'H5', 'H6'] as $headingLevel) {
            $familyName = $this->managedFamilyName(
                $settings['typography']['typographyHeading' . $headingLevel]['font-family'] ?? '',
                $runtimeLookup
            );

            if ($familyName === '') {
                continue;
            }

            $selector = $headingLevel === 'H1'
                ? 'body :is(h1, .editor-post-title)'
                : 'body ' . strtolower($headingLevel);

            $styles[] = $this->buildEditorRule($selector, $familyName);
        }

        return array_values(array_unique($styles));
    }

    public function applyThemeStylesSync(string $targetMode = self::TARGET_MODE_MANAGED, string $targetStyleId = self::MANAGED_THEME_STYLE_ID): array|WP_Error
    {
        if (!$this->isAvailable()) {
            return new WP_Error(
                'tasty_fonts_bricks_unavailable',
                __('Bricks is not active, so its Theme Styles could not be updated.', 'tasty-fonts')
            );
        }

        $state = $this->getSyncState();

        $styles = $this->getThemeStyles();
        $targetMode = $this->normalizeThemeStyleTargetMode($targetMode);
        $targetStyleId = $this->resolveRequestedThemeStyleTargetId($targetStyleId, $styles, $targetMode);
        $state = $this->captureThemeStyleRestoreState($state, $styles, $targetMode, $targetStyleId);

        if ($targetMode === self::TARGET_MODE_ALL) {
            if ($styles === []) {
                $styles[self::MANAGED_THEME_STYLE_ID] = $this->applyManagedTypographyToStyle($this->defaultManagedThemeStyle(), true);
            } else {
                foreach ($styles as $styleKey => $style) {
                    if (!is_array($style)) {
                        continue;
                    }

                    $styles[(string) $styleKey] = $this->applyManagedTypographyToStyle((array) $style);
                }
            }
        } elseif ($targetStyleId === self::MANAGED_THEME_STYLE_ID) {
            $style = is_array($styles[self::MANAGED_THEME_STYLE_ID] ?? null)
                ? $styles[self::MANAGED_THEME_STYLE_ID]
                : $this->defaultManagedThemeStyle();
            $styles[self::MANAGED_THEME_STYLE_ID] = $this->applyManagedTypographyToStyle($style, true);
        } else {
            if (!is_array($styles[$targetStyleId] ?? null)) {
                return new WP_Error(
                    'tasty_fonts_bricks_missing_theme_style',
                    __('Choose a valid Bricks Theme Style before syncing typography.', 'tasty-fonts')
                );
            }

            $styles[$targetStyleId] = $this->applyManagedTypographyToStyle((array) $styles[$targetStyleId]);
        }

        $this->saveThemeStyles($styles);
        $state['theme_styles']['applied'] = true;
        $state['theme_styles']['target_mode'] = $targetMode;
        $state['theme_styles']['target_style_id'] = $targetStyleId;
        $this->saveSyncState($state);

        return $this->desiredThemeStyleValues();
    }

    public function restoreThemeStylesSync(): array|WP_Error
    {
        if (!$this->isAvailable()) {
            return new WP_Error(
                'tasty_fonts_bricks_unavailable',
                __('Bricks is not active, so its previous Theme Styles could not be restored.', 'tasty-fonts')
            );
        }

        $state = $this->getSyncState();

        if (!empty($state['theme_styles']['applied'])) {
            $styles = $this->getThemeStyles();
            $backups = is_array($state['theme_styles']['previous_styles'] ?? null)
                ? (array) $state['theme_styles']['previous_styles']
                : [];

            foreach ($backups as $styleId => $style) {
                if (!is_array($style)) {
                    continue;
                }

                $styles[(string) $styleId] = $style;
            }

            if (!empty($state['theme_styles']['managed_style_created']) && !isset($backups[self::MANAGED_THEME_STYLE_ID])) {
                unset($styles[self::MANAGED_THEME_STYLE_ID]);
            }

            $this->saveThemeStyles($styles);
        }

        $state['theme_styles']['applied'] = false;
        $state['theme_styles']['target_mode'] = self::TARGET_MODE_MANAGED;
        $state['theme_styles']['target_style_id'] = '';
        $state['theme_styles']['managed_style_created'] = false;
        $this->saveSyncState($state);

        return $this->getTargetThemeStyleTypographyValues($this->resolveThemeStyleTargetId([], $state));
    }

    public function applyGoogleFontsSetting(): array|WP_Error
    {
        if (!$this->isAvailable()) {
            return new WP_Error(
                'tasty_fonts_bricks_unavailable',
                __('Bricks is not active, so its Google font setting could not be updated.', 'tasty-fonts')
            );
        }

        $state = $this->getSyncState();
        $globalSettings = $this->getGlobalSettings();

        if (!array_key_exists('previous_disabled_google_fonts', $state['google_fonts']) || $state['google_fonts']['previous_disabled_google_fonts'] === null) {
            $state['google_fonts']['previous_disabled_google_fonts'] = isset($globalSettings['disableGoogleFonts']);
        }

        $globalSettings['disableGoogleFonts'] = true;
        $this->saveGlobalSettings($globalSettings);

        $state['google_fonts']['applied'] = true;
        $this->saveSyncState($state);

        return ['google_fonts_disabled' => true];
    }

    public function restoreGoogleFontsSetting(): array|WP_Error
    {
        if (!$this->isAvailable()) {
            return new WP_Error(
                'tasty_fonts_bricks_unavailable',
                __('Bricks is not active, so its Google font setting could not be restored.', 'tasty-fonts')
            );
        }

        $state = $this->getSyncState();
        $globalSettings = $this->getGlobalSettings();
        $previousDisabled = $state['google_fonts']['previous_disabled_google_fonts'] ?? null;

        if ($previousDisabled) {
            $globalSettings['disableGoogleFonts'] = true;
        } else {
            unset($globalSettings['disableGoogleFonts']);
        }

        $this->saveGlobalSettings($globalSettings);
        $state['google_fonts']['applied'] = false;
        $state['google_fonts']['previous_disabled_google_fonts'] = null;
        $this->saveSyncState($state);

        return ['google_fonts_disabled' => isset($globalSettings['disableGoogleFonts'])];
    }

    public function managedThemeStylesMatchDesired(string $targetStyleId = self::MANAGED_THEME_STYLE_ID, string $targetMode = self::TARGET_MODE_MANAGED): bool
    {
        $styles = $this->getThemeStyles();
        $targetMode = $this->normalizeThemeStyleTargetMode($targetMode);

        if ($styles === []) {
            return false;
        }

        if ($targetMode === self::TARGET_MODE_ALL) {
            foreach ($styles as $style) {
                if (!is_array($style) || !$this->themeStyleMatchesDesired($style)) {
                    return false;
                }
            }

            return true;
        }

        $targetStyleId = $this->normalizeThemeStyleTargetId($targetStyleId);
        $style = is_array($styles[$targetStyleId] ?? null)
            ? $styles[$targetStyleId]
            : [];

        if ($style === []) {
            return false;
        }

        return $this->themeStyleMatchesDesired($style);
    }

    public function hasLegacyManagedVariables(): bool
    {
        return $this->getLegacyManagedVariableValues() !== [];
    }

    public function removeLegacyManagedVariables(): void
    {
        $this->saveGlobalVariables($this->removeManagedVariables($this->getGlobalVariables()));
        $this->saveGlobalVariableCategories($this->removeManagedVariableCategory($this->getGlobalVariableCategories()));
    }

    public function getThemeStyleChoices(): array
    {
        $choices = [];

        foreach ($this->getThemeStyles() as $styleId => $style) {
            if (!is_array($style) || (string) $styleId === self::MANAGED_THEME_STYLE_ID) {
                continue;
            }

            $choices[(string) $styleId] = $this->themeStyleLabel($style, (string) $styleId);
        }

        return $choices;
    }

    public function deleteManagedThemeStyle(): bool|WP_Error
    {
        if (!$this->isAvailable()) {
            return new WP_Error(
                'tasty_fonts_bricks_unavailable',
                __('Bricks is not active, so the Tasty Theme Style could not be deleted.', 'tasty-fonts')
            );
        }

        $styles = $this->getThemeStyles();

        if (!isset($styles[self::MANAGED_THEME_STYLE_ID])) {
            return true;
        }

        unset($styles[self::MANAGED_THEME_STYLE_ID]);
        $this->saveThemeStyles($styles);

        return true;
    }

    public function isGoogleFontsDisabled(): bool
    {
        $settings = $this->getGlobalSettings();

        return isset($settings['disableGoogleFonts']);
    }

    public function getSyncState(): array
    {
        $state = get_option(self::OPTION_SYNC_STATE, []);

        if (!is_array($state)) {
            $state = [];
        }

        $state = array_replace_recursive(self::STATE_TEMPLATE, $state);
        $state['variables']['applied'] = !empty($state['variables']['applied']);
        $state['theme_styles']['applied'] = !empty($state['theme_styles']['applied']);
        $state['theme_styles']['target_mode'] = $this->normalizeThemeStyleTargetMode((string) ($state['theme_styles']['target_mode'] ?? ''));
        $state['theme_styles']['target_style_id'] = $this->normalizeThemeStyleTargetId((string) ($state['theme_styles']['target_style_id'] ?? ''));
        $state['theme_styles']['managed_style_created'] = !empty($state['theme_styles']['managed_style_created']);
        $state['google_fonts']['applied'] = !empty($state['google_fonts']['applied']);
        $state['google_fonts']['previous_disabled_google_fonts'] = array_key_exists('previous_disabled_google_fonts', $state['google_fonts'])
            ? (($state['google_fonts']['previous_disabled_google_fonts'] ?? null) === null ? null : !empty($state['google_fonts']['previous_disabled_google_fonts']))
            : null;

        return $state;
    }

    private function saveSyncState(array $state): void
    {
        update_option(self::OPTION_SYNC_STATE, array_replace_recursive(self::STATE_TEMPLATE, $state), false);
    }

    private function buildFeatureState(bool $available, bool $masterEnabled, bool $featureEnabled): array
    {
        return [
            'enabled' => $available && $masterEnabled && $featureEnabled,
            'status' => !$available ? 'unavailable' : (!$masterEnabled || !$featureEnabled ? 'disabled' : 'active'),
        ];
    }

    private function buildManagedFeatureState(
        bool $available,
        bool $masterEnabled,
        bool $sitewideRolesEnabled,
        bool $featureEnabled,
        bool $applied,
        bool $synced,
        array $current,
        array $desired
    ): array {
        $status = 'disabled';

        if (!$available) {
            $status = 'unavailable';
        } elseif (!$masterEnabled || !$featureEnabled) {
            $status = 'disabled';
        } elseif (!$sitewideRolesEnabled) {
            $status = 'waiting_for_sitewide_roles';
        } elseif ($applied && $synced) {
            $status = 'synced';
        } else {
            $status = 'ready';
        }

        return [
            'enabled' => $available && $masterEnabled && $featureEnabled,
            'applied' => $applied,
            'status' => $status,
            'current' => $current,
            'desired' => $desired,
        ];
    }

    private function getThemeStyleSummary(array $settings = [], ?array $syncState = null): array
    {
        $styles = $this->getThemeStyles();
        $styleLabels = [];

        foreach ($styles as $styleId => $style) {
            if (!is_array($style)) {
                continue;
            }

            $styleLabels[(string) $styleId] = $this->themeStyleLabel($style, (string) $styleId);
        }

        $syncState = is_array($syncState) ? $syncState : $this->getSyncState();
        $targetMode = $this->resolveThemeStyleTargetMode($settings, $syncState);
        $targetStyleId = $this->resolveThemeStyleTargetId($settings, $syncState, $styles, $targetMode);
        $targetStyleLabel = $targetMode === self::TARGET_MODE_ALL
            ? __('All Theme Styles', 'tasty-fonts')
            : ($styleLabels[$targetStyleId] ?? '');

        return [
            'has_theme_styles' => $styleLabels !== [],
            'managed_style_exists' => isset($styleLabels[self::MANAGED_THEME_STYLE_ID]),
            'managed_style_label' => $styleLabels[self::MANAGED_THEME_STYLE_ID] ?? $this->managedThemeStyleLabel(),
            'available_styles' => $styleLabels,
            'target_mode' => $targetMode,
            'target_style_id' => $targetStyleId,
            'target_style_label' => $targetStyleLabel !== '' ? $targetStyleLabel : $this->managedThemeStyleLabel(),
            'target_is_managed' => $targetStyleId === self::MANAGED_THEME_STYLE_ID,
            'target_is_all' => $targetMode === self::TARGET_MODE_ALL,
        ];
    }

    private function managedThemeStyleLabel(): string
    {
        return __(self::MANAGED_THEME_STYLE_LABEL, 'tasty-fonts');
    }

    private function normalizeThemeStyleTargetId(string $targetStyleId): string
    {
        $normalized = trim(sanitize_text_field($targetStyleId));

        return $normalized !== '' ? $normalized : self::MANAGED_THEME_STYLE_ID;
    }

    private function normalizeThemeStyleTargetMode(string $targetMode): string
    {
        $normalized = trim(sanitize_text_field($targetMode));

        return in_array($normalized, [self::TARGET_MODE_MANAGED, self::TARGET_MODE_SELECTED, self::TARGET_MODE_ALL], true)
            ? $normalized
            : self::TARGET_MODE_MANAGED;
    }

    private function resolveThemeStyleTargetMode(array $settings, array $syncState): string
    {
        if (array_key_exists('bricks_theme_style_target_mode', $settings)) {
            return $this->normalizeThemeStyleTargetMode((string) ($settings['bricks_theme_style_target_mode'] ?? ''));
        }

        if (!empty($syncState['theme_styles']['applied'])) {
            return $this->normalizeThemeStyleTargetMode((string) ($syncState['theme_styles']['target_mode'] ?? ''));
        }

        return self::TARGET_MODE_MANAGED;
    }

    private function resolveThemeStyleTargetId(array $settings, array $syncState, ?array $styles = null, ?string $targetMode = null): string
    {
        $styles = is_array($styles) ? $styles : $this->getThemeStyles();
        $targetMode = $targetMode !== null ? $this->normalizeThemeStyleTargetMode($targetMode) : $this->resolveThemeStyleTargetMode($settings, $syncState);
        $hasRequestedTarget = array_key_exists('bricks_theme_style_target_id', $settings);

        if ($targetMode === self::TARGET_MODE_MANAGED || $targetMode === self::TARGET_MODE_ALL) {
            return self::MANAGED_THEME_STYLE_ID;
        }

        if ($hasRequestedTarget) {
            $requestedTargetId = $this->resolveRequestedThemeStyleTargetId(
                (string) ($settings['bricks_theme_style_target_id'] ?? ''),
                $styles,
                $targetMode
            );

            if (isset($styles[$requestedTargetId])) {
                return $requestedTargetId;
            }
        }

        if (!empty($syncState['theme_styles']['applied'])) {
            $appliedTargetId = $this->resolveRequestedThemeStyleTargetId(
                (string) ($syncState['theme_styles']['target_style_id'] ?? ''),
                $styles,
                $targetMode
            );

            if (isset($styles[$appliedTargetId])) {
                return $appliedTargetId;
            }
        }

        $availableChoices = $this->getThemeStyleChoices();

        return array_key_first($availableChoices) ?: self::MANAGED_THEME_STYLE_ID;
    }

    private function resolveRequestedThemeStyleTargetId(string $targetStyleId, array $styles, string $targetMode): string
    {
        $targetMode = $this->normalizeThemeStyleTargetMode($targetMode);

        if ($targetMode === self::TARGET_MODE_MANAGED || $targetMode === self::TARGET_MODE_ALL) {
            return self::MANAGED_THEME_STYLE_ID;
        }

        $normalizedTargetId = $this->normalizeThemeStyleTargetId($targetStyleId);

        if ($normalizedTargetId !== self::MANAGED_THEME_STYLE_ID && isset($styles[$normalizedTargetId])) {
            return $normalizedTargetId;
        }

        $availableChoices = [];

        foreach ($styles as $styleId => $style) {
            if (!is_array($style) || (string) $styleId === self::MANAGED_THEME_STYLE_ID) {
                continue;
            }

            $availableChoices[(string) $styleId] = true;
        }

        return array_key_first($availableChoices) ?: self::MANAGED_THEME_STYLE_ID;
    }

    private function themeStyleLabel(array $style, string $styleId): string
    {
        $label = trim((string) ($style['label'] ?? ''));

        return $label !== '' ? $label : $styleId;
    }

    private function desiredThemeStyleValues(): array
    {
        return [
            'body_family' => self::DESIRED_BODY_VALUE,
            'body_weight' => self::DESIRED_BODY_WEIGHT_VALUE,
            'heading_family' => self::DESIRED_HEADING_VALUE,
            'heading_weight' => self::DESIRED_HEADING_WEIGHT_VALUE,
        ];
    }

    private function getLegacyManagedVariableValues(): array
    {
        $values = [];

        foreach ($this->getGlobalVariables() as $variable) {
            if (!is_array($variable)) {
                continue;
            }

            $name = trim((string) ($variable['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            if (!in_array($name, [
                self::VARIABLE_NAME_HEADING,
                self::VARIABLE_NAME_BODY,
                self::VARIABLE_NAME_HEADING_WEIGHT,
                self::VARIABLE_NAME_BODY_WEIGHT,
            ], true)) {
                continue;
            }

            $values[$name] = trim((string) ($variable['value'] ?? ''));
        }

        ksort($values, SORT_STRING);

        return $values;
    }

    private function removeManagedVariables(array $variables): array
    {
        $managedNames = [
            self::VARIABLE_NAME_HEADING,
            self::VARIABLE_NAME_BODY,
            self::VARIABLE_NAME_HEADING_WEIGHT,
            self::VARIABLE_NAME_BODY_WEIGHT,
        ];

        return array_values(
            array_filter(
                $variables,
                static fn (mixed $variable): bool => !is_array($variable)
                    || !in_array(trim((string) ($variable['name'] ?? '')), $managedNames, true)
            )
        );
    }

    private function removeManagedVariableCategory(array $categories): array
    {
        return array_values(
            array_filter(
                $categories,
                static fn (mixed $category): bool => !is_array($category)
                    || trim((string) ($category['id'] ?? '')) !== self::VARIABLE_CATEGORY_ID
            )
        );
    }

    private function defaultManagedThemeStyle(): array
    {
        return [
            'label' => $this->managedThemeStyleLabel(),
            'settings' => [
                'conditions' => $this->defaultManagedThemeStyleConditions(),
                'typography' => $this->applyManagedTypographyToMap([]),
            ],
        ];
    }

    private function applyManagedTypographyToMap(array $typography): array
    {
        foreach (self::BODY_TYPOGRAPHY_KEYS as $key) {
            $entry = is_array($typography[$key] ?? null) ? $typography[$key] : [];
            $entry['font-family'] = self::DESIRED_BODY_VALUE;
            $entry['font-weight'] = self::DESIRED_BODY_WEIGHT_VALUE;
            $typography[$key] = $entry;
        }

        foreach (self::HEADING_TYPOGRAPHY_KEYS as $key) {
            $entry = is_array($typography[$key] ?? null) ? $typography[$key] : [];
            $entry['font-family'] = self::DESIRED_HEADING_VALUE;
            $entry['font-weight'] = self::DESIRED_HEADING_WEIGHT_VALUE;
            $typography[$key] = $entry;
        }

        return $typography;
    }

    private function applyManagedTypographyToStyle(array $style, bool $ensureDefaultConditions = false): array
    {
        $settings = is_array($style['settings'] ?? null) ? $style['settings'] : [];
        $typography = is_array($settings['typography'] ?? null) ? $settings['typography'] : [];
        $settings['typography'] = $this->applyManagedTypographyToMap($typography);

        if ($ensureDefaultConditions && !is_array($settings['conditions'] ?? null)) {
            $settings['conditions'] = $this->defaultManagedThemeStyleConditions();
        }

        $style['settings'] = $settings;

        return $style;
    }

    private function defaultManagedThemeStyleConditions(): array
    {
        return [
            'conditions' => [
                [
                    'id' => 'tasty-fonts-main',
                    'key' => 'main',
                    'main' => 'any',
                    'compare' => '==',
                    'value' => 'any',
                    'priority' => 10,
                ],
            ],
        ];
    }

    private function captureThemeStyleRestoreState(array $state, array $styles, string $targetMode, string $targetStyleId): array
    {
        $backups = is_array($state['theme_styles']['previous_styles'] ?? null)
            ? (array) $state['theme_styles']['previous_styles']
            : [];

        if ($targetMode === self::TARGET_MODE_ALL) {
            if ($styles === []) {
                $state['theme_styles']['managed_style_created'] = true;
            } else {
                foreach ($styles as $styleId => $style) {
                    if (!is_array($style) || isset($backups[(string) $styleId])) {
                        continue;
                    }

                    $backups[(string) $styleId] = $style;
                }
            }
        } elseif ($targetStyleId === self::MANAGED_THEME_STYLE_ID) {
            if (is_array($styles[self::MANAGED_THEME_STYLE_ID] ?? null)) {
                if (!isset($backups[self::MANAGED_THEME_STYLE_ID])) {
                    $backups[self::MANAGED_THEME_STYLE_ID] = (array) $styles[self::MANAGED_THEME_STYLE_ID];
                }
            } else {
                $state['theme_styles']['managed_style_created'] = true;
            }
        } elseif (is_array($styles[$targetStyleId] ?? null) && !isset($backups[$targetStyleId])) {
            $backups[$targetStyleId] = (array) $styles[$targetStyleId];
        }

        $state['theme_styles']['previous_styles'] = $backups;

        return $state;
    }

    private function getTargetThemeStyleTypographyValues(string $targetStyleId): array
    {
        $settings = $this->getTargetThemeStyleSettings($targetStyleId);
        $typography = is_array($settings['typography'] ?? null) ? $settings['typography'] : [];

        return [
            'body_family' => (string) (($typography['typographyBody']['font-family'] ?? '')),
            'body_weight' => (string) (($typography['typographyBody']['font-weight'] ?? '')),
            'heading_family' => (string) (($typography['typographyHeadingH1']['font-family'] ?? ($typography['typographyHeadings']['font-family'] ?? ''))),
            'heading_weight' => (string) (($typography['typographyHeadingH1']['font-weight'] ?? ($typography['typographyHeadings']['font-weight'] ?? ''))),
        ];
    }

    private function getAllThemeStyleTypographyValues(): array
    {
        $styles = $this->getThemeStyles();

        if ($styles === []) {
            return [
                'body_family' => '',
                'body_weight' => '',
                'heading_family' => '',
                'heading_weight' => '',
            ];
        }

        $values = [
            'body_family' => [],
            'body_weight' => [],
            'heading_family' => [],
            'heading_weight' => [],
        ];

        foreach (array_keys($styles) as $styleId) {
            $styleValues = $this->getTargetThemeStyleTypographyValues((string) $styleId);

            foreach ($values as $key => $collected) {
                $value = trim((string) ($styleValues[$key] ?? ''));

                if ($value !== '') {
                    $values[$key][$value] = true;
                }
            }
        }

        return [
            'body_family' => count($values['body_family']) === 1 ? (string) array_key_first($values['body_family']) : __('mixed', 'tasty-fonts'),
            'body_weight' => count($values['body_weight']) === 1 ? (string) array_key_first($values['body_weight']) : __('mixed', 'tasty-fonts'),
            'heading_family' => count($values['heading_family']) === 1 ? (string) array_key_first($values['heading_family']) : __('mixed', 'tasty-fonts'),
            'heading_weight' => count($values['heading_weight']) === 1 ? (string) array_key_first($values['heading_weight']) : __('mixed', 'tasty-fonts'),
        ];
    }

    private function getTargetThemeStyleSettings(string $targetStyleId): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $styles = $this->getThemeStyles();
        $targetStyleId = $this->normalizeThemeStyleTargetId($targetStyleId);

        return is_array($styles[$targetStyleId]['settings'] ?? null)
            ? $styles[$targetStyleId]['settings']
            : [];
    }

    private function themeStyleMatchesDesired(array $style): bool
    {
        $typography = $style['settings']['typography'] ?? [];

        if (!is_array($typography)) {
            return false;
        }

        foreach (self::BODY_TYPOGRAPHY_KEYS as $key) {
            $entry = is_array($typography[$key] ?? null) ? $typography[$key] : [];

            if (
                (string) ($entry['font-family'] ?? '') !== self::DESIRED_BODY_VALUE
                || (string) ($entry['font-weight'] ?? '') !== self::DESIRED_BODY_WEIGHT_VALUE
            ) {
                return false;
            }
        }

        foreach (self::HEADING_TYPOGRAPHY_KEYS as $key) {
            $entry = is_array($typography[$key] ?? null) ? $typography[$key] : [];

            if (
                (string) ($entry['font-family'] ?? '') !== self::DESIRED_HEADING_VALUE
                || (string) ($entry['font-weight'] ?? '') !== self::DESIRED_HEADING_WEIGHT_VALUE
            ) {
                return false;
            }
        }

        return true;
    }

    private function getThemeStyles(): array
    {
        return $this->getOptionArray(defined('BRICKS_DB_THEME_STYLES') ? (string) constant('BRICKS_DB_THEME_STYLES') : 'bricks_theme_styles');
    }

    private function saveThemeStyles(array $styles): void
    {
        update_option(defined('BRICKS_DB_THEME_STYLES') ? (string) constant('BRICKS_DB_THEME_STYLES') : 'bricks_theme_styles', $styles, false);
    }

    private function getGlobalVariables(): array
    {
        return $this->getOptionArray(defined('BRICKS_DB_GLOBAL_VARIABLES') ? (string) constant('BRICKS_DB_GLOBAL_VARIABLES') : self::OPTION_GLOBAL_VARIABLES);
    }

    private function saveGlobalVariables(array $variables): void
    {
        update_option(defined('BRICKS_DB_GLOBAL_VARIABLES') ? (string) constant('BRICKS_DB_GLOBAL_VARIABLES') : self::OPTION_GLOBAL_VARIABLES, $variables, false);
    }

    private function getGlobalVariableCategories(): array
    {
        return $this->getOptionArray(defined('BRICKS_DB_GLOBAL_VARIABLES_CATEGORIES') ? (string) constant('BRICKS_DB_GLOBAL_VARIABLES_CATEGORIES') : self::OPTION_GLOBAL_VARIABLE_CATEGORIES);
    }

    private function saveGlobalVariableCategories(array $categories): void
    {
        $optionName = defined('BRICKS_DB_GLOBAL_VARIABLES_CATEGORIES')
            ? (string) constant('BRICKS_DB_GLOBAL_VARIABLES_CATEGORIES')
            : self::OPTION_GLOBAL_VARIABLE_CATEGORIES;

        if ($categories === []) {
            delete_option($optionName);
            return;
        }

        update_option($optionName, $categories, false);
    }

    private function getGlobalSettings(): array
    {
        return $this->getOptionArray(self::OPTION_GLOBAL_SETTINGS);
    }

    private function saveGlobalSettings(array $settings): void
    {
        update_option(self::OPTION_GLOBAL_SETTINGS, $settings, false);
    }

    private function getOptionArray(string $optionName): array
    {
        $value = get_option($optionName, []);

        return is_array($value) ? $value : [];
    }

    private function masterEnabled(array $settings): bool
    {
        return ($settings['bricks_integration_enabled'] ?? null) !== false;
    }

    private function buildEditorRule(string $selector, string $familyName): string
    {
        return $selector . '{font-family:' . FontUtils::buildFontStack($familyName) . ';}';
    }

    private function runtimeFamilyNames(array $runtimeFamilies): array
    {
        $names = array_keys($this->runtimeFamilyLookup($runtimeFamilies));
        natcasesort($names);

        return array_values($names);
    }

    private function runtimeFamilyLookup(array $runtimeFamilies): array
    {
        $lookup = [];

        foreach ($runtimeFamilies as $family) {
            $name = '';

            if (is_string($family)) {
                $name = trim($family);
            } elseif (is_array($family)) {
                $name = trim((string) ($family['family'] ?? ''));
            }

            if ($name === '') {
                continue;
            }

            $lookup[$name] = true;
        }

        return $lookup;
    }

    private function managedFamilyName(mixed $value, array $runtimeLookup): string
    {
        $familyName = is_scalar($value) ? trim((string) $value) : '';

        return $familyName !== '' && isset($runtimeLookup[$familyName]) ? $familyName : '';
    }
}
