<?php

declare(strict_types=1);

namespace TastyFonts\Integrations;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;
use WP_Error;

/**
 * @phpstan-import-type NormalizedSettings from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type CatalogFamily from \TastyFonts\Fonts\CatalogService
 * @phpstan-type SyncVariablesState array{applied: bool}
 * @phpstan-type SyncThemeStylesState array{
 *     applied: bool,
 *     previous_styles: ThemeStyleMap,
 *     target_mode: string,
 *     target_style_id: string,
 *     managed_style_created: bool
 * }
 * @phpstan-type SyncGoogleFontsState array{
 *     applied: bool,
 *     previous_disabled_google_fonts: bool|null
 * }
 * @phpstan-type SyncState array{
 *     variables: SyncVariablesState,
 *     theme_styles: SyncThemeStylesState,
 *     google_fonts: SyncGoogleFontsState
 * }
 * @phpstan-type RuntimeFamilyList list<CatalogFamily>
 * @phpstan-type ReadState array{
 *     available: bool,
 *     enabled: bool,
 *     configured: bool,
 *     status: string,
 *     selectors: array<string, mixed>,
 *     builder_preview: array<string, mixed>,
 *     theme_styles: array<string, mixed>,
 *     google_fonts: array<string, mixed>
 * }
 * @phpstan-type ThemeStyle array<string, mixed>
 * @phpstan-type ThemeStyleMap array<string, ThemeStyle>
 * @phpstan-type ThemeStyleChoiceMap array<string, string>
 * @phpstan-type TypographyMap array<string, array<string, mixed>>
 * @phpstan-type TypographyValueMap array<string, string>
 * @phpstan-type VariableEntry array<string, mixed>
 * @phpstan-type VariableList list<VariableEntry>
 * @phpstan-type VariableCategoryList list<array<string, mixed>>
 */
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
        'variables' => [
            'applied' => false,
        ],
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

    /**
     * @param NormalizedSettings $settings
     * @return ReadState
     */
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
        $themeStylesSynced = $this->normalizeThemeStyleTargetMode($syncState['theme_styles']['target_mode']) === $targetMode
            && $this->normalizeThemeStyleTargetId($syncState['theme_styles']['target_style_id']) === $targetStyleId
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
                $syncState['theme_styles']['applied'],
                $themeStylesSynced,
                $currentThemeStyles,
                $this->desiredThemeStyleValues()
            ), [
                'summary' => $themeStyleSummary,
            ]),
            'google_fonts' => [
                'enabled' => $available && $masterEnabled && $this->disableGoogleFontsEnabled($settings),
                'configured' => !empty($settings[self::FEATURE_DISABLE_GOOGLE_FONTS]),
                'applied' => $syncState['google_fonts']['applied'],
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

    /**
     * @param NormalizedSettings $settings
     */
    public function selectorsEnabled(array $settings): bool
    {
        return $this->masterEnabled($settings);
    }

    /**
     * @param NormalizedSettings $settings
     */
    public function builderPreviewEnabled(array $settings): bool
    {
        return $this->masterEnabled($settings);
    }

    /**
     * @param NormalizedSettings $settings
     */
    public function themeStylesSyncEnabled(array $settings): bool
    {
        return $this->masterEnabled($settings) && !empty($settings[self::FEATURE_THEME_STYLES]);
    }

    /**
     * @param NormalizedSettings $settings
     */
    public function disableGoogleFontsEnabled(array $settings): bool
    {
        return $this->masterEnabled($settings) && !empty($settings[self::FEATURE_DISABLE_GOOGLE_FONTS]);
    }

    /**
     * @param NormalizedSettings $settings
     */
    public function managedEditorStylesActive(array $settings): bool
    {
        if (!$this->themeStylesSyncEnabled($settings) || empty($settings['auto_apply_roles']) || !$this->isAvailable()) {
            return false;
        }

        $syncState = $this->getSyncState();
        $targetStyleId = $this->resolveThemeStyleTargetId($settings, $syncState);

        return $syncState['theme_styles']['applied'] && $this->managedThemeStylesMatchDesired($targetStyleId, $this->resolveThemeStyleTargetMode($settings, $syncState));
    }

    /**
     * @param NormalizedSettings $settings
     */
    public function managedFrontendStylesActive(array $settings): bool
    {
        if (!$this->themeStylesSyncEnabled($settings) || empty($settings['auto_apply_roles']) || !$this->isAvailable()) {
            return false;
        }

        $syncState = $this->getSyncState();

        return $syncState['theme_styles']['applied'];
    }

    /**
     * @return list<string>
     */
    public function getManagedEditorStyles(): array
    {
        return [
            'body{font-family:var(--font-body);font-weight:var(--font-body-weight);}',
            self::EDITOR_HEADING_SELECTOR . '{font-family:var(--font-heading);font-weight:var(--font-heading-weight);}',
        ];
    }

    /**
     * @return list<string>
     */
    public function getManagedFrontendStyles(): array
    {
        return [
            'body,.bricks-type-lead{font-family:var(--font-body);font-weight:var(--font-body-weight);}',
            'h1,h2,h3,h4,h5,h6,.bricks-type-hero{font-family:var(--font-heading);font-weight:var(--font-heading-weight);}',
        ];
    }

    /**
     * @param list<string> $fonts
     * @param RuntimeFamilyList $runtimeFamilies
     * @return list<string>
     */
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
            $name = trim($font);

            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $merged[] = $name;
        }

        return $merged;
    }

    /**
     * @param RuntimeFamilyList $runtimeFamilies
     * @return list<string>
     */
    public function getSelectorFamilyNames(array $runtimeFamilies): array
    {
        return $this->runtimeFamilyNames($runtimeFamilies);
    }

    /**
     * @param RuntimeFamilyList $runtimeFamilies
     * @return list<string>
     */
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
        $typography = $this->themeStyleTypography($settings);
        $bodyFamily = $this->managedFamilyName(
            $this->themeStyleTypographyValue($typography, 'typographyBody', 'font-family'),
            $runtimeLookup
        );

        if ($bodyFamily !== '') {
            $styles[] = $this->buildEditorRule('body', $bodyFamily);
        }

        foreach (['H1', 'H2', 'H3', 'H4', 'H5', 'H6'] as $headingLevel) {
            $familyName = $this->managedFamilyName(
                $this->themeStyleTypographyValue($typography, 'typographyHeading' . $headingLevel, 'font-family'),
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

    /**
     * @return TypographyValueMap|WP_Error
     */
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
                    $styles[(string) $styleKey] = $this->applyManagedTypographyToStyle($style);
                }
            }
        } elseif ($targetStyleId === self::MANAGED_THEME_STYLE_ID) {
            $style = $styles[self::MANAGED_THEME_STYLE_ID] ?? $this->defaultManagedThemeStyle();
            $styles[self::MANAGED_THEME_STYLE_ID] = $this->applyManagedTypographyToStyle($style, true);
        } else {
            if (!isset($styles[$targetStyleId])) {
                return new WP_Error(
                    'tasty_fonts_bricks_missing_theme_style',
                    __('Choose a valid Bricks Theme Style before syncing typography.', 'tasty-fonts')
                );
            }

            $styles[$targetStyleId] = $this->applyManagedTypographyToStyle($styles[$targetStyleId]);
        }

        $this->saveThemeStyles($styles);
        $state['theme_styles']['applied'] = true;
        $state['theme_styles']['target_mode'] = $targetMode;
        $state['theme_styles']['target_style_id'] = $targetStyleId;
        $this->saveSyncState($state);

        return $this->desiredThemeStyleValues();
    }

    /**
     * @return TypographyValueMap|WP_Error
     */
    public function restoreThemeStylesSync(): array|WP_Error
    {
        if (!$this->isAvailable()) {
            return new WP_Error(
                'tasty_fonts_bricks_unavailable',
                __('Bricks is not active, so its previous Theme Styles could not be restored.', 'tasty-fonts')
            );
        }

        $state = $this->getSyncState();

        if ($state['theme_styles']['applied']) {
            $styles = $this->getThemeStyles();
            $backups = $state['theme_styles']['previous_styles'];

            foreach ($backups as $styleId => $style) {
                $styles[(string) $styleId] = $style;
            }

            if ($state['theme_styles']['managed_style_created'] && !isset($backups[self::MANAGED_THEME_STYLE_ID])) {
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

    /**
     * @return array{google_fonts_disabled: bool}|WP_Error
     */
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

        if ($state['google_fonts']['previous_disabled_google_fonts'] === null) {
            $state['google_fonts']['previous_disabled_google_fonts'] = isset($globalSettings['disableGoogleFonts']);
        }

        $globalSettings['disableGoogleFonts'] = true;
        $this->saveGlobalSettings($globalSettings);

        $state['google_fonts']['applied'] = true;
        $this->saveSyncState($state);

        return ['google_fonts_disabled' => true];
    }

    /**
     * @return array{google_fonts_disabled: bool}|WP_Error
     */
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
        $previousDisabled = $state['google_fonts']['previous_disabled_google_fonts'];

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
                if (!$this->themeStyleMatchesDesired($style)) {
                    return false;
                }
            }

            return true;
        }

        $targetStyleId = $this->normalizeThemeStyleTargetId($targetStyleId);

        if (!isset($styles[$targetStyleId])) {
            return false;
        }

        return $this->themeStyleMatchesDesired($styles[$targetStyleId]);
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

    /**
     * @return ThemeStyleChoiceMap
     */
    public function getThemeStyleChoices(): array
    {
        $choices = [];

        foreach ($this->getThemeStyles() as $styleId => $style) {
            if ((string) $styleId === self::MANAGED_THEME_STYLE_ID) {
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

    /**
     * @return SyncState
     */
    public function getSyncState(): array
    {
        $state = get_option(self::OPTION_SYNC_STATE, []);

        if (!is_array($state)) {
            $state = [];
        }

        $variables = $this->optionMap($state['variables'] ?? []);
        $themeStyles = $this->optionMap($state['theme_styles'] ?? []);
        $googleFonts = $this->optionMap($state['google_fonts'] ?? []);

        return [
            'variables' => [
                'applied' => !empty($variables['applied']),
            ],
            'theme_styles' => [
                'applied' => !empty($themeStyles['applied']),
                'previous_styles' => $this->normalizeThemeStyleMap($themeStyles['previous_styles'] ?? []),
                'target_mode' => $this->normalizeThemeStyleTargetMode($this->stringValue($themeStyles, 'target_mode')),
                'target_style_id' => $this->normalizeThemeStyleTargetId($this->stringValue($themeStyles, 'target_style_id')),
                'managed_style_created' => !empty($themeStyles['managed_style_created']),
            ],
            'google_fonts' => [
                'applied' => !empty($googleFonts['applied']),
                'previous_disabled_google_fonts' => array_key_exists('previous_disabled_google_fonts', $googleFonts)
                    ? ($googleFonts['previous_disabled_google_fonts'] === null ? null : !empty($googleFonts['previous_disabled_google_fonts']))
                    : null,
            ],
        ];
    }

    /**
     * @param SyncState $state
     */
    private function saveSyncState(array $state): void
    {
        update_option(self::OPTION_SYNC_STATE, array_replace_recursive(self::STATE_TEMPLATE, $state), false);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFeatureState(bool $available, bool $masterEnabled, bool $featureEnabled): array
    {
        return [
            'enabled' => $available && $masterEnabled && $featureEnabled,
            'status' => !$available ? 'unavailable' : (!$masterEnabled || !$featureEnabled ? 'disabled' : 'active'),
        ];
    }

    /**
     * @param TypographyValueMap $current
     * @param TypographyValueMap $desired
     * @return array<string, mixed>
     */
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

    /**
     * @param NormalizedSettings $settings
     * @param SyncState|null $syncState
     * @return array<string, mixed>
     */
    private function getThemeStyleSummary(array $settings = [], ?array $syncState = null): array
    {
        $styles = $this->getThemeStyles();
        $styleLabels = [];

        foreach ($styles as $styleId => $style) {
            $styleLabels[(string) $styleId] = $this->themeStyleLabel($style, (string) $styleId);
        }

        $syncState = $syncState ?? $this->getSyncState();
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

    /**
     * @param NormalizedSettings $settings
     * @param SyncState $syncState
     */
    private function resolveThemeStyleTargetMode(array $settings, array $syncState): string
    {
        if (array_key_exists('bricks_theme_style_target_mode', $settings)) {
            return $this->normalizeThemeStyleTargetMode($this->stringValue($settings, 'bricks_theme_style_target_mode'));
        }

        if ($syncState['theme_styles']['applied']) {
            return $this->normalizeThemeStyleTargetMode($syncState['theme_styles']['target_mode']);
        }

        return self::TARGET_MODE_MANAGED;
    }

    /**
     * @param NormalizedSettings $settings
     * @param SyncState $syncState
     * @param ThemeStyleMap|null $styles
     */
    private function resolveThemeStyleTargetId(array $settings, array $syncState, ?array $styles = null, ?string $targetMode = null): string
    {
        $styles = $styles ?? $this->getThemeStyles();
        $targetMode = $targetMode !== null ? $this->normalizeThemeStyleTargetMode($targetMode) : $this->resolveThemeStyleTargetMode($settings, $syncState);
        $hasRequestedTarget = array_key_exists('bricks_theme_style_target_id', $settings);

        if ($targetMode === self::TARGET_MODE_MANAGED || $targetMode === self::TARGET_MODE_ALL) {
            return self::MANAGED_THEME_STYLE_ID;
        }

        if ($hasRequestedTarget) {
            $requestedTargetId = $this->resolveRequestedThemeStyleTargetId(
                $this->stringValue($settings, 'bricks_theme_style_target_id'),
                $styles,
                $targetMode
            );

            if (isset($styles[$requestedTargetId])) {
                return $requestedTargetId;
            }
        }

        if ($syncState['theme_styles']['applied']) {
            $appliedTargetId = $this->resolveRequestedThemeStyleTargetId(
                $syncState['theme_styles']['target_style_id'],
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

    /**
     * @param ThemeStyleMap $styles
     */
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
            if ((string) $styleId === self::MANAGED_THEME_STYLE_ID) {
                continue;
            }

            $availableChoices[(string) $styleId] = true;
        }

        return array_key_first($availableChoices) ?: self::MANAGED_THEME_STYLE_ID;
    }

    /**
     * @param ThemeStyle $style
     */
    private function themeStyleLabel(array $style, string $styleId): string
    {
        $label = trim($this->stringValue($style, 'label'));

        return $label !== '' ? $label : $styleId;
    }

    /**
     * @return TypographyValueMap
     */
    private function desiredThemeStyleValues(): array
    {
        return [
            'body_family' => self::DESIRED_BODY_VALUE,
            'body_weight' => self::DESIRED_BODY_WEIGHT_VALUE,
            'heading_family' => self::DESIRED_HEADING_VALUE,
            'heading_weight' => self::DESIRED_HEADING_WEIGHT_VALUE,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getLegacyManagedVariableValues(): array
    {
        $values = [];

        foreach ($this->getGlobalVariables() as $variable) {
            $name = trim($this->stringValue($variable, 'name'));

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

            $values[$name] = trim($this->stringValue($variable, 'value'));
        }

        ksort($values, SORT_STRING);

        return $values;
    }

    /**
     * @param VariableList $variables
     * @return VariableList
     */
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
                fn (array $variable): bool => !in_array(trim($this->stringValue($variable, 'name')), $managedNames, true)
            )
        );
    }

    /**
     * @param VariableCategoryList $categories
     * @return VariableCategoryList
     */
    private function removeManagedVariableCategory(array $categories): array
    {
        return array_values(
            array_filter(
                $categories,
                fn (array $category): bool => trim($this->stringValue($category, 'id')) !== self::VARIABLE_CATEGORY_ID
            )
        );
    }

    /**
     * @return ThemeStyle
     */
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

    /**
     * @param TypographyMap $typography
     * @return TypographyMap
     */
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

    /**
     * @param ThemeStyle $style
     * @return ThemeStyle
     */
    private function applyManagedTypographyToStyle(array $style, bool $ensureDefaultConditions = false): array
    {
        $settings = $this->normalizeThemeStyleSettings($style['settings'] ?? []);
        $settings['typography'] = $this->applyManagedTypographyToMap($this->themeStyleTypography($settings));

        if ($ensureDefaultConditions && !isset($settings['conditions'])) {
            $settings['conditions'] = $this->defaultManagedThemeStyleConditions();
        }

        $style['settings'] = $settings;

        return $style;
    }

    /**
     * @return array{conditions: list<array{id: string, key: string, main: string, compare: string, value: string, priority: int}>}
     */
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

    /**
     * @param SyncState $state
     * @param ThemeStyleMap $styles
     * @return SyncState
     */
    private function captureThemeStyleRestoreState(array $state, array $styles, string $targetMode, string $targetStyleId): array
    {
        $backups = $state['theme_styles']['previous_styles'];

        if ($targetMode === self::TARGET_MODE_ALL) {
            if ($styles === []) {
                $state['theme_styles']['managed_style_created'] = true;
            } else {
                foreach ($styles as $styleId => $style) {
                    if (isset($backups[(string) $styleId])) {
                        continue;
                    }

                    $backups[(string) $styleId] = $style;
                }
            }
        } elseif ($targetStyleId === self::MANAGED_THEME_STYLE_ID) {
            if (isset($styles[self::MANAGED_THEME_STYLE_ID])) {
                if (!isset($backups[self::MANAGED_THEME_STYLE_ID])) {
                    $backups[self::MANAGED_THEME_STYLE_ID] = $styles[self::MANAGED_THEME_STYLE_ID];
                }
            } else {
                $state['theme_styles']['managed_style_created'] = true;
            }
        } elseif (isset($styles[$targetStyleId]) && !isset($backups[$targetStyleId])) {
            $backups[$targetStyleId] = $styles[$targetStyleId];
        }

        $state['theme_styles']['previous_styles'] = $backups;

        return $state;
    }

    /**
     * @return TypographyValueMap
     */
    private function getTargetThemeStyleTypographyValues(string $targetStyleId): array
    {
        $settings = $this->getTargetThemeStyleSettings($targetStyleId);
        $typography = $this->themeStyleTypography($settings);

        return [
            'body_family' => $this->themeStyleTypographyValue($typography, 'typographyBody', 'font-family'),
            'body_weight' => $this->themeStyleTypographyValue($typography, 'typographyBody', 'font-weight'),
            'heading_family' => $this->firstThemeStyleTypographyValue($typography, ['typographyHeadingH1', 'typographyHeadings'], 'font-family'),
            'heading_weight' => $this->firstThemeStyleTypographyValue($typography, ['typographyHeadingH1', 'typographyHeadings'], 'font-weight'),
        ];
    }

    /**
     * @return TypographyValueMap
     */
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
                $value = trim($styleValues[$key] ?? '');

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

    /**
     * @return array<string, mixed>
     */
    private function getTargetThemeStyleSettings(string $targetStyleId): array
    {
        if (!$this->isAvailable()) {
            return ['typography' => []];
        }

        $styles = $this->getThemeStyles();
        $targetStyleId = $this->normalizeThemeStyleTargetId($targetStyleId);

        return isset($styles[$targetStyleId])
            ? $this->normalizeThemeStyleSettings($styles[$targetStyleId]['settings'] ?? [])
            : ['typography' => []];
    }

    /**
     * @param ThemeStyle $style
     */
    private function themeStyleMatchesDesired(array $style): bool
    {
        $settings = $this->normalizeThemeStyleSettings($style['settings'] ?? []);
        $typography = $this->themeStyleTypography($settings);

        foreach (self::BODY_TYPOGRAPHY_KEYS as $key) {
            if (
                $this->themeStyleTypographyValue($typography, $key, 'font-family') !== self::DESIRED_BODY_VALUE
                || $this->themeStyleTypographyValue($typography, $key, 'font-weight') !== self::DESIRED_BODY_WEIGHT_VALUE
            ) {
                return false;
            }
        }

        foreach (self::HEADING_TYPOGRAPHY_KEYS as $key) {
            if (
                $this->themeStyleTypographyValue($typography, $key, 'font-family') !== self::DESIRED_HEADING_VALUE
                || $this->themeStyleTypographyValue($typography, $key, 'font-weight') !== self::DESIRED_HEADING_WEIGHT_VALUE
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return ThemeStyleMap
     */
    private function getThemeStyles(): array
    {
        $styles = [];

        foreach ($this->getOptionArray(defined('BRICKS_DB_THEME_STYLES') ? (string) constant('BRICKS_DB_THEME_STYLES') : 'bricks_theme_styles') as $styleId => $style) {
            $styles[(string) $styleId] = $this->normalizeThemeStyle($style);
        }

        return $styles;
    }

    /**
     * @param ThemeStyleMap $styles
     */
    private function saveThemeStyles(array $styles): void
    {
        update_option(defined('BRICKS_DB_THEME_STYLES') ? (string) constant('BRICKS_DB_THEME_STYLES') : 'bricks_theme_styles', $styles, false);
    }

    /**
     * @return VariableList
     */
    private function getGlobalVariables(): array
    {
        $variables = [];

        foreach ($this->getOptionArray(defined('BRICKS_DB_GLOBAL_VARIABLES') ? (string) constant('BRICKS_DB_GLOBAL_VARIABLES') : self::OPTION_GLOBAL_VARIABLES) as $variable) {
            $variables[] = $this->normalizeVariableEntry($variable);
        }

        return $variables;
    }

    /**
     * @param VariableList $variables
     */
    private function saveGlobalVariables(array $variables): void
    {
        update_option(defined('BRICKS_DB_GLOBAL_VARIABLES') ? (string) constant('BRICKS_DB_GLOBAL_VARIABLES') : self::OPTION_GLOBAL_VARIABLES, $variables, false);
    }

    /**
     * @return VariableCategoryList
     */
    private function getGlobalVariableCategories(): array
    {
        $categories = [];

        foreach ($this->getOptionArray(defined('BRICKS_DB_GLOBAL_VARIABLES_CATEGORIES') ? (string) constant('BRICKS_DB_GLOBAL_VARIABLES_CATEGORIES') : self::OPTION_GLOBAL_VARIABLE_CATEGORIES) as $category) {
            $categories[] = $this->normalizeVariableCategoryEntry($category);
        }

        return $categories;
    }

    /**
     * @param VariableCategoryList $categories
     */
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

    /**
     * @return array<string, mixed>
     */
    private function getGlobalSettings(): array
    {
        return $this->normalizeOptionMap($this->getOptionArray(self::OPTION_GLOBAL_SETTINGS));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function saveGlobalSettings(array $settings): void
    {
        update_option(self::OPTION_GLOBAL_SETTINGS, $settings, false);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function getOptionArray(string $optionName): array
    {
        $value = get_option($optionName, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function normalizeOptionMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                continue;
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function optionMap(mixed $value): array
    {
        return $this->normalizeOptionMap($value);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * @param mixed $style
     * @return ThemeStyle
     */
    private function normalizeThemeStyle(mixed $style): array
    {
        $normalized = $this->optionMap($style);
        $normalized['label'] = $this->stringValue($normalized, 'label');
        $normalized['settings'] = $this->normalizeThemeStyleSettings($normalized['settings'] ?? []);

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return ThemeStyleMap
     */
    private function normalizeThemeStyleMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $styleId => $style) {
            $normalized[(string) $styleId] = $this->normalizeThemeStyle($style);
        }

        return $normalized;
    }

    /**
     * @param mixed $settings
     * @return array<string, mixed>
     */
    private function normalizeThemeStyleSettings(mixed $settings): array
    {
        $normalized = $this->optionMap($settings);
        $normalized['typography'] = $this->normalizeTypographyMap($normalized['typography'] ?? []);

        if (array_key_exists('conditions', $normalized) && !is_array($normalized['conditions'])) {
            unset($normalized['conditions']);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $settings
     * @return TypographyMap
     */
    private function themeStyleTypography(array $settings): array
    {
        return $this->normalizeTypographyMap($settings['typography'] ?? []);
    }

    /**
     * @param mixed $typography
     * @return TypographyMap
     */
    private function normalizeTypographyMap(mixed $typography): array
    {
        if (!is_array($typography)) {
            return [];
        }

        $normalized = [];

        foreach ($typography as $key => $entry) {
            if (!is_string($key)) {
                continue;
            }

            $normalized[$key] = $this->normalizeTypographyEntry($entry);
        }

        return $normalized;
    }

    /**
     * @param mixed $entry
     * @return array<string, mixed>
     */
    private function normalizeTypographyEntry(mixed $entry): array
    {
        $normalized = $this->optionMap($entry);

        if (array_key_exists('font-family', $normalized)) {
            $normalized['font-family'] = $this->stringValue($normalized, 'font-family');
        }

        if (array_key_exists('font-weight', $normalized)) {
            $normalized['font-weight'] = $this->stringValue($normalized, 'font-weight');
        }

        return $normalized;
    }

    /**
     * @param TypographyMap $typography
     * @param 'font-family'|'font-weight' $valueKey
     */
    private function themeStyleTypographyValue(array $typography, string $entryKey, string $valueKey): string
    {
        $entry = $this->optionMap($typography[$entryKey] ?? []);

        return $this->stringValue($entry, $valueKey);
    }

    /**
     * @param TypographyMap $typography
     * @param list<string> $entryKeys
     * @param 'font-family'|'font-weight' $valueKey
     */
    private function firstThemeStyleTypographyValue(array $typography, array $entryKeys, string $valueKey): string
    {
        foreach ($entryKeys as $entryKey) {
            $value = $this->themeStyleTypographyValue($typography, $entryKey, $valueKey);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param mixed $variable
     * @return VariableEntry
     */
    private function normalizeVariableEntry(mixed $variable): array
    {
        $normalized = $this->optionMap($variable);

        if (array_key_exists('name', $normalized)) {
            $normalized['name'] = $this->stringValue($normalized, 'name');
        }

        if (array_key_exists('value', $normalized)) {
            $normalized['value'] = $this->stringValue($normalized, 'value');
        }

        return $normalized;
    }

    /**
     * @param mixed $category
     * @return array<string, mixed>
     */
    private function normalizeVariableCategoryEntry(mixed $category): array
    {
        $normalized = $this->optionMap($category);

        if (array_key_exists('id', $normalized)) {
            $normalized['id'] = $this->stringValue($normalized, 'id');
        }

        return $normalized;
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function masterEnabled(array $settings): bool
    {
        return ($settings['bricks_integration_enabled'] ?? null) !== false;
    }

    private function buildEditorRule(string $selector, string $familyName): string
    {
        return $selector . '{font-family:' . FontUtils::buildFontStack($familyName) . ';}';
    }

    /**
     * @param RuntimeFamilyList $runtimeFamilies
     * @return list<string>
     */
    private function runtimeFamilyNames(array $runtimeFamilies): array
    {
        $names = array_keys($this->runtimeFamilyLookup($runtimeFamilies));
        natcasesort($names);

        return array_values($names);
    }

    /**
     * @param RuntimeFamilyList $runtimeFamilies
     * @return array<string, CatalogFamily>
     */
    private function runtimeFamilyLookup(array $runtimeFamilies): array
    {
        $lookup = [];

        foreach ($runtimeFamilies as $family) {
            $name = is_scalar($family['family'] ?? null) ? trim((string) $family['family']) : '';

            if ($name === '') {
                continue;
            }

            $lookup[$name] = $family;
        }

        return $lookup;
    }

    /**
     * @param array<string, CatalogFamily> $runtimeLookup
     */
    private function managedFamilyName(mixed $value, array $runtimeLookup): string
    {
        $familyName = is_scalar($value) ? trim((string) $value) : '';

        return $familyName !== '' && isset($runtimeLookup[$familyName]) ? $familyName : '';
    }
}
