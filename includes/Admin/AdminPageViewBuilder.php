<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Admin\FontTypeHelper;
use TastyFonts\Admin\Renderer\SharedRenderHelpers;
use TastyFonts\Admin\Renderer\LibraryRenderValueHelpers;
use TastyFonts\Integrations\BricksIntegrationService;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;

/**
 * @phpstan-import-type PageContext from AdminPageContextBuilder
 * @phpstan-import-type CatalogMap from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type FamilyFallbackMap from \TastyFonts\Repository\SettingsRepository
 */
final class AdminPageViewBuilder
{
    use SharedRenderHelpers;
    use LibraryRenderValueHelpers;

    private bool $trainingWheelsOff = false;

    public function __construct(private readonly Storage $storage)
    {
    }

    /**
     * @param PageContext $context
     * @return array<string, mixed>
     */
    public function build(array $context): array
    {
        $this->trainingWheelsOff = !empty($context['training_wheels_off']);
        $storage = $this->mapValue($context, 'storage');
        $currentPage = $this->stringValue($context, 'current_page', AdminController::PAGE_ROLES);
        $currentPageSlug = $this->stringValue($context, 'current_page_slug');
        $pageUrls = $this->stringMapValue($context, 'page_urls');
        $catalog = $this->catalogMapValue($context, 'catalog');
        $libraryCategoryOptions = $this->buildLibraryCategoryOptions();
        $availableFamilies = $this->stringListValue($context, 'available_families', array_keys($catalog));
        $availableFamilyOptions = $this->selectorOptionListValue($context, 'available_family_options');

        if ($availableFamilyOptions === []) {
            $availableFamilyOptions = array_map(
            static function ($familyName) use ($catalog): array {
                $name = trim($familyName);
                $catalogEntry = $catalog[$name] ?? null;
                $descriptor = $catalogEntry !== null ? FontTypeHelper::describeEntry($catalogEntry) : null;

                return [
                    'value' => $name,
                    'label' => FontTypeHelper::buildSelectorOptionLabel($name, $catalogEntry),
                    'type' => $descriptor !== null ? $descriptor['type'] : '',
                ];
            },
            $availableFamilies
        );
        }
        $availableFamilyLabels = [];

        foreach ($availableFamilyOptions as $option) {
            $optionValue = trim($option['value']);

            if ($optionValue === '') {
                continue;
            }

            $availableFamilyLabels[$optionValue] = $option['label'] !== '' ? $option['label'] : $optionValue;
        }
        $roles = $this->mapValue($context, 'roles');
        $appliedRoles = $this->mapValue($context, 'applied_roles');
        $logs = $this->listOfMapsValue($context, 'logs');
        $activityActorOptions = $this->stringListValue($context, 'activity_actor_options');
        $familyFallbacks = $this->stringMapValue($context, 'family_fallbacks');
        $familyFontDisplays = $this->stringMapValue($context, 'family_font_displays');
        $familyFontDisplayOptions = $this->valueLabelOptionListValue($context, 'family_font_display_options');
        $previewText = $this->stringValue($context, 'preview_text');
        $previewSize = $this->intValue($context, 'preview_size', 32);
        $googleApiState = $this->stringValue($context, 'google_api_state', 'empty');
        $googleApiEnabled = !empty($context['google_api_enabled']);
        $googleApiSaved = !empty($context['google_api_saved']);
        $googleAccessExpanded = !empty($context['google_access_expanded']);
        $adobeProjectState = $this->stringValue($context, 'adobe_project_state', 'empty');
        $googleStatusLabel = $this->stringValue($context, 'google_status_label');
        $googleStatusClass = $this->stringValue($context, 'google_status_class');
        $googleAccessCopy = $this->stringValue($context, 'google_access_copy');
        $googleSearchDisabledCopy = $this->stringValue($context, 'google_search_disabled_copy');
        $adobeProjectEnabled = !empty($context['adobe_project_enabled']);
        $adobeProjectSaved = !empty($context['adobe_project_saved']);
        $adobeAccessExpanded = !empty($context['adobe_access_expanded']);
        $adobeProjectId = $this->stringValue($context, 'adobe_project_id');
        $adobeStatusLabel = $this->stringValue($context, 'adobe_status_label');
        $adobeStatusClass = $this->stringValue($context, 'adobe_status_class');
        $adobeAccessCopy = $this->stringValue($context, 'adobe_access_copy');
        $adobeProjectLink = $this->stringValue($context, 'adobe_project_link', 'https://fonts.adobe.com/');
        $adobeDetectedFamilies = $this->stringListValue($context, 'adobe_detected_families');
        $bunnyCatalogLink = 'https://fonts.bunny.net/';
        $googleAccessButtonLabel = $googleApiEnabled ? __('Edit Key', 'tasty-fonts') : __('Key Settings', 'tasty-fonts');
        $adobeAccessButtonLabel = $adobeProjectSaved ? __('Project Settings', 'tasty-fonts') : __('Add Project', 'tasty-fonts');
        $cssDeliveryMode = $this->stringValue($context, 'css_delivery_mode', 'file');
        $cssDeliveryModeOptions = $this->valueLabelOptionListValue($context, 'css_delivery_mode_options');
        $fontDisplay = $this->stringValue($context, 'font_display', 'swap');
        $fontDisplayOptions = $this->valueLabelOptionListValue($context, 'font_display_options');
        $unicodeRangeMode = $this->stringValue($context, 'unicode_range_mode', FontUtils::UNICODE_RANGE_MODE_OFF);
        $unicodeRangeCustomValue = $this->stringValue($context, 'unicode_range_custom_value');
        $unicodeRangeModeOptions = $this->valueLabelOptionListValue($context, 'unicode_range_mode_options');
        $unicodeRangeCustomVisible = !empty($context['unicode_range_custom_visible']);
        $outputQuickModePreference = $this->stringValue($context, 'output_quick_mode_preference');
        $classOutputEnabled = !empty($context['class_output_enabled']);
        $classOutputRoleHeadingEnabled = !array_key_exists('class_output_role_heading_enabled', $context)
            || !empty($context['class_output_role_heading_enabled']);
        $classOutputRoleBodyEnabled = !array_key_exists('class_output_role_body_enabled', $context)
            || !empty($context['class_output_role_body_enabled']);
        $classOutputRoleMonospaceEnabled = !array_key_exists('class_output_role_monospace_enabled', $context)
            || !empty($context['class_output_role_monospace_enabled']);
        $classOutputRoleAliasInterfaceEnabled = !array_key_exists('class_output_role_alias_interface_enabled', $context)
            || !empty($context['class_output_role_alias_interface_enabled']);
        $classOutputRoleAliasUiEnabled = !array_key_exists('class_output_role_alias_ui_enabled', $context)
            || !empty($context['class_output_role_alias_ui_enabled']);
        $classOutputRoleAliasCodeEnabled = !array_key_exists('class_output_role_alias_code_enabled', $context)
            || !empty($context['class_output_role_alias_code_enabled']);
        $classOutputCategorySansEnabled = !array_key_exists('class_output_category_sans_enabled', $context)
            || !empty($context['class_output_category_sans_enabled']);
        $classOutputCategorySerifEnabled = !array_key_exists('class_output_category_serif_enabled', $context)
            || !empty($context['class_output_category_serif_enabled']);
        $classOutputCategoryMonoEnabled = !array_key_exists('class_output_category_mono_enabled', $context)
            || !empty($context['class_output_category_mono_enabled']);
        $classOutputFamiliesEnabled = !array_key_exists('class_output_families_enabled', $context)
            || !empty($context['class_output_families_enabled']);
        $classOutputRoleStylesEnabled = !empty($context['class_output_role_styles_enabled']);
        $minifyCssOutput = !empty($context['minify_css_output']);
        $roleUsageFontWeightEnabled = !empty($context['role_usage_font_weight_enabled']);
        $perVariantFontVariablesEnabled = !array_key_exists('per_variant_font_variables_enabled', $context)
            || !empty($context['per_variant_font_variables_enabled']);
        $minimalOutputPresetEnabled = !empty($context['minimal_output_preset_enabled']);
        $extendedVariableRoleWeightVarsEnabled = !array_key_exists('extended_variable_role_weight_vars_enabled', $context)
            || !empty($context['extended_variable_role_weight_vars_enabled']);
        $extendedVariableWeightTokensEnabled = !array_key_exists('extended_variable_weight_tokens_enabled', $context)
            || !empty($context['extended_variable_weight_tokens_enabled']);
        $extendedVariableRoleAliasesEnabled = !array_key_exists('extended_variable_role_aliases_enabled', $context)
            || !empty($context['extended_variable_role_aliases_enabled']);
        $extendedVariableCategorySansEnabled = !array_key_exists('extended_variable_category_sans_enabled', $context)
            || !empty($context['extended_variable_category_sans_enabled']);
        $extendedVariableCategorySerifEnabled = !array_key_exists('extended_variable_category_serif_enabled', $context)
            || !empty($context['extended_variable_category_serif_enabled']);
        $extendedVariableCategoryMonoEnabled = !array_key_exists('extended_variable_category_mono_enabled', $context)
            || !empty($context['extended_variable_category_mono_enabled']);
        $preloadPrimaryFonts = !empty($context['preload_primary_fonts']);
        $remoteConnectionHints = !empty($context['remote_connection_hints']);
        $updateChannel = $this->stringValue($context, 'update_channel', 'stable');
        $updateChannelOptions = $this->valueLabelOptionListValue($context, 'update_channel_options');
        $updateChannelStatus = $this->mapValue($context, 'update_channel_status');
        $adminAccessCustomEnabled = !empty($context['admin_access_custom_enabled']);
        $adminAccessRoleSlugs = $this->stringListValue($context, 'admin_access_role_slugs');
        $adminAccessRoleOptions = $this->listOfMapsValue($context, 'admin_access_role_options');
        $adminAccessUserIds = $this->stringListValue($context, 'admin_access_user_ids');
        $adminAccessUserOptions = $this->listOfMapsValue($context, 'admin_access_user_options');
        $adminAccessSummary = $this->mapValue($context, 'admin_access_summary');
        $developerToolStatuses = $this->mapValue($context, 'developer_tool_statuses');
        $blockEditorFontLibrarySyncEnabled = !empty($context['block_editor_font_library_sync_enabled']);
        $trainingWheelsOff = $this->trainingWheelsOff;
        $variableFontsEnabled = !empty($context['variable_fonts_enabled']);
        $deleteUploadedFilesOnUninstall = !empty($context['delete_uploaded_files_on_uninstall']);
        $diagnosticItems = $this->listOfMapsValue($context, 'diagnostic_items');
        $overviewMetrics = $this->listOfMapsValue($context, 'overview_metrics');
        $outputPanels = $this->listOfMapsValue($context, 'output_panels');
        $generatedCssPanel = $this->mapValue($context, 'generated_css_panel');
        $previewPanels = $this->listOfMapsValue($context, 'preview_panels');
        $localEnvironmentNotice = $this->mapValue($context, 'local_environment_notice');
        $siteTransfer = $this->mapValue($context, 'site_transfer');
        $gutenbergIntegration = $this->mapValue($context, 'gutenberg_integration');
        $etchIntegration = $this->mapValue($context, 'etch_integration');
        $acssIntegration = $this->mapValue($context, 'acss_integration');
        $bricksIntegration = $this->mapValue($context, 'bricks_integration');
        $bricksIntegration = $this->buildBricksIntegrationView($bricksIntegration);
        $oxygenIntegration = $this->mapValue($context, 'oxygen_integration');
        $toasts = $this->listOfMapsValue($context, 'toasts');
        $applyEverywhere = !empty($context['apply_everywhere']);
        $previewBaselineSource = $this->stringValue($context, 'preview_baseline_source', $applyEverywhere ? 'live_sitewide' : 'draft');
        $previewBaselineLabel = $this->stringValue($context, 'preview_baseline_label', $applyEverywhere ? __('Live sitewide', 'tasty-fonts') : __('Current draft', 'tasty-fonts'));
        $roleDeployment = $this->mapValue($context, 'role_deployment');
        $monospaceRoleEnabled = !empty($context['monospace_role_enabled']);
        $previewRoles = $previewBaselineSource === 'live_sitewide' && $appliedRoles !== []
            ? $appliedRoles
            : $roles;
        $hasPendingLiveRoleChanges = $applyEverywhere && !$this->roleSetsMatch($roles, $appliedRoles, $monospaceRoleEnabled, $catalog, $variableFontsEnabled, $familyFallbacks);
        $previewHasDraftRoleChanges = !$this->roleSetsMatch($previewRoles, $roles, $monospaceRoleEnabled, $catalog, $variableFontsEnabled, $familyFallbacks);
        $previewHasPendingLiveRoleChanges = $applyEverywhere && !$this->roleSetsMatch($previewRoles, $appliedRoles, $monospaceRoleEnabled, $catalog, $variableFontsEnabled, $familyFallbacks);
        $previewHeadingFallback = $this->resolveEffectiveRoleFallback('heading', $previewRoles, $catalog, $familyFallbacks);
        $previewBodyFallback = $this->resolveEffectiveRoleFallback('body', $previewRoles, $catalog, $familyFallbacks);
        $previewMonospaceFallback = $this->resolveEffectiveRoleFallback('monospace', $previewRoles, $catalog, $familyFallbacks);
        $previewHeadingStack = FontUtils::buildFontStack(
            $this->stringValue($previewRoles, 'heading'),
            $previewHeadingFallback
        );
        $previewBodyStack = FontUtils::buildFontStack(
            $this->stringValue($previewRoles, 'body'),
            $previewBodyFallback
        );
        $previewMonospaceStack = FontUtils::buildFontStack(
            $this->stringValue($previewRoles, 'monospace'),
            $previewMonospaceFallback
        );
        $saveDraftDisabledCopy = __('No draft changes to save.', 'tasty-fonts');
        $applyLiveDisabledCopy = !$applyEverywhere
            ? __('Sitewide delivery is off. Turn it on before publishing role changes.', 'tasty-fonts')
            : __('No live role changes to publish.', 'tasty-fonts');
        $headingFamily = $this->stringValue($roles, 'heading');
        $bodyFamily = $this->stringValue($roles, 'body');
        $monospaceFamily = $this->stringValue($roles, 'monospace');
        $headingFallback = $this->resolveEffectiveRoleFallback('heading', $roles, $catalog, $familyFallbacks);
        $bodyFallback = $this->resolveEffectiveRoleFallback('body', $roles, $catalog, $familyFallbacks);
        $monospaceFallback = $this->resolveEffectiveRoleFallback('monospace', $roles, $catalog, $familyFallbacks);
        $headingStack = FontUtils::buildFontStack($headingFamily, $headingFallback);
        $bodyStack = FontUtils::buildFontStack($bodyFamily, $bodyFallback);
        $monospaceStack = FontUtils::buildFontStack($monospaceFamily, $monospaceFallback);
        $headingVariable = 'var(--font-heading)';
        $bodyVariable = 'var(--font-body)';
        $monospaceVariable = 'var(--font-monospace)';
        $headingFamilyVariable = $headingFamily !== '' ? $this->buildFontVariableReference($headingFamily) : $headingStack;
        $bodyFamilyVariable = $bodyFamily !== '' ? $this->buildFontVariableReference($bodyFamily) : $bodyStack;
        $monospaceFamilyVariable = $monospaceFamily !== '' ? $this->buildFontVariableReference($monospaceFamily) : $monospaceStack;
        $pluginVersion = defined('TASTY_FONTS_VERSION') ? (string) TASTY_FONTS_VERSION : '';
        $pluginRepositoryUrl = 'https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts';
        $pluginVersionUrl = $pluginVersion !== '' && !str_contains($pluginVersion, '-dev')
            ? $pluginRepositoryUrl . '/releases/tag/' . rawurlencode($pluginVersion)
            : $pluginRepositoryUrl . '/releases';
        $pluginVersionChannelLabel = $this->buildPluginVersionChannelLabel($pluginVersion, $updateChannelStatus);
        $pluginVersionStateLabel = $this->buildPluginVersionStateLabel($updateChannelStatus);
        $pluginVersionMeta = $this->buildPluginVersionMeta($pluginVersionChannelLabel, $pluginVersionStateLabel);
        $pluginVersionBadgeClass = $this->buildPluginVersionBadgeClass($updateChannelStatus);
        $pluginVersionTooltip = $this->buildPluginVersionTooltip(
            $pluginVersion,
            $pluginVersionChannelLabel,
            $pluginVersionStateLabel,
            $this->stringValue($updateChannelStatus, 'latest_version')
        );
        $pluginVersionAriaLabel = $this->buildPluginVersionAriaLabel(
            $pluginVersion,
            $pluginVersionChannelLabel,
            $pluginVersionStateLabel
        );
        $roleDeploymentBadge = $this->stringValue($roleDeployment, 'badge');
        $roleDeploymentBadgeClass = $this->stringValue($roleDeployment, 'badge_class');
        $roleDeploymentTitle = $this->stringValue($roleDeployment, 'title');
        $roleDeploymentCopy = $this->stringValue($roleDeployment, 'copy');
        $roleDeploymentTooltip = trim(
            $roleDeploymentTitle . ($roleDeploymentTitle !== '' && $roleDeploymentCopy !== '' ? '. ' : '') . $roleDeploymentCopy
        );
        $sitewideStatusTooltip = $applyEverywhere
            ? __('These role selections are currently being served on the frontend, editor, and Etch.', 'tasty-fonts')
            : __('These role selections are saved as a draft and are not yet being served sitewide.', 'tasty-fonts');
        $roleDeploymentAnnouncementId = 'tasty-fonts-role-deployment-announcement';
        $storageErrorMessage = trim($this->storage->getLastFilesystemErrorMessage());
        $categoryAliasOwners = $this->buildCategoryAliasOwners($catalog, $roles, $monospaceRoleEnabled);
        $extendedVariableOptions = [
            'enabled' => $perVariantFontVariablesEnabled,
            'minimal' => $minimalOutputPresetEnabled,
            'role_weight_vars' => $extendedVariableRoleWeightVarsEnabled,
            'weight_tokens' => $extendedVariableWeightTokensEnabled,
            'role_aliases' => $extendedVariableRoleAliasesEnabled,
            'category_sans' => $extendedVariableCategorySansEnabled,
            'category_serif' => $extendedVariableCategorySerifEnabled,
        ];
        if ($monospaceRoleEnabled) {
            $extendedVariableOptions['category_mono'] = $extendedVariableCategoryMonoEnabled;
        }
        $classOutputOptions = [
            'enabled' => $classOutputEnabled,
            'role_heading' => $classOutputRoleHeadingEnabled,
            'role_body' => $classOutputRoleBodyEnabled,
            'role_alias_interface' => $classOutputRoleAliasInterfaceEnabled,
            'role_alias_ui' => $classOutputRoleAliasUiEnabled,
            'category_sans' => $classOutputCategorySansEnabled,
            'category_serif' => $classOutputCategorySerifEnabled,
            'families' => $classOutputFamiliesEnabled,
        ];
        if ($monospaceRoleEnabled) {
            $classOutputOptions['role_monospace'] = $classOutputRoleMonospaceEnabled;
            $classOutputOptions['role_alias_code'] = $classOutputRoleAliasCodeEnabled;
            $classOutputOptions['category_mono'] = $classOutputCategoryMonoEnabled;
        }
        $outputQuickMode = $this->deriveOutputQuickMode(
            $outputQuickModePreference,
            $classOutputOptions,
            $extendedVariableOptions,
            $roleUsageFontWeightEnabled
        );
        if ($outputQuickMode === 'custom') {
            $classOutputEnabled = true;
            $perVariantFontVariablesEnabled = true;
        }
        $advancedOutputControlsExpanded = in_array($outputQuickMode, ['variables', 'classes', 'custom'], true);

        $view = get_defined_vars();
        unset($view['context']);

        return $view;
    }

    /**
     * @param array<string, mixed> $integration
     * @return array<string, mixed>
     */
    private function buildBricksIntegrationView(array $integration): array
    {
        $featureDescriptions = $this->mapValue($integration, 'feature_descriptions');
        $themeStyles = $this->buildBricksFeatureView(
            'bricks_theme_styles_sync_enabled',
            $this->mapValue($integration, 'theme_styles'),
            $this->stringValue($featureDescriptions, 'theme_styles')
        );
        $googleFonts = $this->buildBricksFeatureView(
            'bricks_disable_google_fonts_enabled',
            $this->mapValue($integration, 'google_fonts'),
            $this->stringValue($featureDescriptions, 'google_fonts')
        );
        $ui = $this->mapValue($themeStyles, 'ui');

        $ui['targeting'] = $this->buildBricksThemeStyleTargetView($themeStyles);
        $themeStyles['ui'] = $ui;
        $ui['details'] = $this->buildBricksMappingDetailsView($themeStyles, $googleFonts);
        $themeStyles['ui'] = $ui;
        $integration['theme_styles'] = $themeStyles;
        $integration['google_fonts'] = $googleFonts;
        $integration['maintenance'] = [
            'title' => __('Maintenance', 'tasty-fonts'),
            'copy' => __('Remove the managed style or restore Bricks defaults.', 'tasty-fonts'),
        ];

        return $integration;
    }

    /**
     * @param array<string, mixed> $featureState
     * @return array<string, mixed>
     */
    private function buildBricksFeatureView(string $featureKey, array $featureState, string $description): array
    {
        $status = $this->stringValue($featureState, 'status', 'disabled');

        $featureState['description'] = $description;
        $featureState['ui'] = [
            'status_label' => $this->buildBricksFeatureStatusLabel($status),
            'status_badge_class' => 'tasty-fonts-badge' . $this->buildBricksFeatureStatusBadgeClass($status),
            'status_help' => $this->buildBricksFeatureStatusHelp($featureKey, $status),
        ];

        return $featureState;
    }

    /**
     * @param array<string, mixed> $themeStyles
     * @return array<string, mixed>
     */
    private function buildBricksThemeStyleTargetView(array $themeStyles): array
    {
        $summary = $this->mapValue($themeStyles, 'summary');
        $managedThemeStyleLabel = $this->stringValue($summary, 'managed_style_label', BricksIntegrationService::MANAGED_THEME_STYLE_LABEL);
        $availableThemeStyles = $this->stringMapValue($summary, 'available_styles');
        $selectableThemeStyles = array_filter(
            $availableThemeStyles,
            static fn (string $label, string $styleId): bool => $styleId !== BricksIntegrationService::MANAGED_THEME_STYLE_ID,
            ARRAY_FILTER_USE_BOTH
        );
        $targetMode = $this->stringValue($summary, 'target_mode', BricksIntegrationService::TARGET_MODE_MANAGED);
        $targetStyleId = $this->stringValue($summary, 'target_style_id', BricksIntegrationService::MANAGED_THEME_STYLE_ID);
        $targetStyleLabel = $this->stringValue($summary, 'target_style_label', $managedThemeStyleLabel);
        $hasThemeStyles = !empty($summary['has_theme_styles']);
        $managedThemeStyleExists = !empty($summary['managed_style_exists']);
        $targetIsManaged = !empty($summary['target_is_managed']);
        $targetIsAll = !empty($summary['target_is_all']);

        if ($targetMode === BricksIntegrationService::TARGET_MODE_SELECTED && $selectableThemeStyles === []) {
            $targetMode = BricksIntegrationService::TARGET_MODE_MANAGED;
        }

        return [
            'title' => __('Theme Style', 'tasty-fonts'),
            'copy' => $this->buildBricksThemeStyleTargetCopy($summary),
            'summary_copy' => '',
            'mode' => $targetMode,
            'selected_style_id' => $targetStyleId,
            'selected_style_label' => $targetStyleLabel,
            'managed_style_label' => $managedThemeStyleLabel,
            'available_styles' => $availableThemeStyles,
            'has_theme_styles' => $hasThemeStyles,
            'managed_style_exists' => $managedThemeStyleExists,
            'target_is_managed' => $targetIsManaged,
            'target_is_all' => $targetIsAll,
            'has_selectable_styles' => $selectableThemeStyles !== [],
            'show_create_action' => !$hasThemeStyles && !$managedThemeStyleExists,
            'show_delete_action' => $managedThemeStyleExists,
            'select_visible' => $targetMode === BricksIntegrationService::TARGET_MODE_SELECTED,
            'empty_select_label' => __('No existing Bricks Theme Styles yet', 'tasty-fonts'),
            'select_label' => __('Choose Theme Style', 'tasty-fonts'),
            'mode_options' => [
                [
                    'value' => BricksIntegrationService::TARGET_MODE_MANAGED,
                    'label' => __('Managed Tasty style', 'tasty-fonts'),
                ],
                [
                    'value' => BricksIntegrationService::TARGET_MODE_SELECTED,
                    'label' => __('One existing style', 'tasty-fonts'),
                ],
                [
                    'value' => BricksIntegrationService::TARGET_MODE_ALL,
                    'label' => __('All styles', 'tasty-fonts'),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function buildBricksThemeStyleTargetCopy(array $summary): string
    {
        $managedThemeStyleLabel = $this->stringValue($summary, 'managed_style_label', BricksIntegrationService::MANAGED_THEME_STYLE_LABEL);
        $targetStyleLabel = $this->stringValue($summary, 'target_style_label', $managedThemeStyleLabel);

        if (empty($summary['has_theme_styles']) && empty($summary['managed_style_exists'])) {
            return __('Create the managed style to start syncing.', 'tasty-fonts');
        }

        if (!empty($summary['target_is_all'])) {
            return __('Only updates font-family and font-weight on all Theme Styles.', 'tasty-fonts');
        }

        if (!empty($summary['target_is_managed'])) {
            return sprintf(
                /* translators: %s: theme style label */
                __('Only updates font-family and font-weight on the managed style "%s".', 'tasty-fonts'),
                $managedThemeStyleLabel
            );
        }

        if ($targetStyleLabel !== '') {
            return sprintf(
                /* translators: %s: theme style label */
                __('Only updates font-family and font-weight on "%s".', 'tasty-fonts'),
                $targetStyleLabel
            );
        }

        return __('Only updates font-family and font-weight.', 'tasty-fonts');
    }

    /**
     * @param array<string, mixed> $themeStyles
     * @param array<string, mixed> $googleFonts
     * @return array<string, mixed>
     */
    private function buildBricksMappingDetailsView(array $themeStyles, array $googleFonts): array
    {
        $summary = $this->mapValue($themeStyles, 'summary');
        $targeting = $this->mapValue($this->mapValue($themeStyles, 'ui'), 'targeting');
        $current = $this->mapValue($themeStyles, 'current');
        $desired = $this->mapValue($themeStyles, 'desired');
        $googleCurrent = $this->mapValue($googleFonts, 'current');
        $managedThemeStyleLabel = $this->stringValue($targeting, 'managed_style_label', BricksIntegrationService::MANAGED_THEME_STYLE_LABEL);
        $targetStyleLabel = $this->stringValue($targeting, 'selected_style_label');
        $themeStylesApplied = !empty($themeStyles['applied']);
        $currentIntro = '';

        if (empty($summary['has_theme_styles']) && empty($summary['managed_style_exists'])) {
            $currentIntro = __('No Theme Styles yet.', 'tasty-fonts');
        } elseif (!empty($summary['target_is_all'])) {
            $currentIntro = __('All Theme Styles are being updated.', 'tasty-fonts');
        } elseif (!empty($summary['target_is_managed']) && !empty($summary['managed_style_exists'])) {
            $currentIntro = sprintf(
                /* translators: %s: theme style label */
                __('Managed style: "%s".', 'tasty-fonts'),
                $managedThemeStyleLabel
            );
        } elseif ($targetStyleLabel !== '') {
            $currentIntro = sprintf(
                /* translators: %s: theme style label */
                $themeStylesApplied
                    ? __('Updating "%s".', 'tasty-fonts')
                    : __('Ready to update "%s".', 'tasty-fonts'),
                $targetStyleLabel
            );
        }

        return [
            'summary_label' => __('Font mapping', 'tasty-fonts'),
            'current_title' => __('Current values', 'tasty-fonts'),
            'current_intro' => $currentIntro,
            'target_title' => __('Target values', 'tasty-fonts'),
            'current_rows' => [
                [
                    'label' => __('Body', 'tasty-fonts'),
                    'value' => $this->stringValue($current, 'body_family') !== ''
                        ? $this->stringValue($current, 'body_family')
                        : __('empty', 'tasty-fonts'),
                ],
                [
                    'label' => __('Heading', 'tasty-fonts'),
                    'value' => $this->stringValue($current, 'heading_family') !== ''
                        ? $this->stringValue($current, 'heading_family')
                        : __('empty', 'tasty-fonts'),
                ],
                [
                    'label' => __('Body Weight', 'tasty-fonts'),
                    'value' => $this->stringValue($current, 'body_weight') !== ''
                        ? $this->stringValue($current, 'body_weight')
                        : __('empty', 'tasty-fonts'),
                ],
                [
                    'label' => __('Heading Weight', 'tasty-fonts'),
                    'value' => $this->stringValue($current, 'heading_weight') !== ''
                        ? $this->stringValue($current, 'heading_weight')
                        : __('empty', 'tasty-fonts'),
                ],
            ],
            'desired_rows' => [
                [
                    'label' => __('Body', 'tasty-fonts'),
                    'value' => $this->stringValue($desired, 'body_family', BricksIntegrationService::DESIRED_BODY_VALUE),
                ],
                [
                    'label' => __('Heading', 'tasty-fonts'),
                    'value' => $this->stringValue($desired, 'heading_family', BricksIntegrationService::DESIRED_HEADING_VALUE),
                ],
                [
                    'label' => __('Body Weight', 'tasty-fonts'),
                    'value' => $this->stringValue($desired, 'body_weight', BricksIntegrationService::DESIRED_BODY_WEIGHT_VALUE),
                ],
                [
                    'label' => __('Heading Weight', 'tasty-fonts'),
                    'value' => $this->stringValue($desired, 'heading_weight', BricksIntegrationService::DESIRED_HEADING_WEIGHT_VALUE),
                ],
            ],
        ];
    }

    private function buildBricksFeatureStatusLabel(string $status): string
    {
        return match ($status) {
            'synced' => __('Synced', 'tasty-fonts'),
            'active' => __('On', 'tasty-fonts'),
            'waiting_for_sitewide_roles' => __('Waiting', 'tasty-fonts'),
            'ready' => __('Ready', 'tasty-fonts'),
            'unavailable' => __('Not Active', 'tasty-fonts'),
            default => __('Off', 'tasty-fonts'),
        };
    }

    private function buildBricksFeatureStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'synced', 'active' => ' is-success',
            'ready' => ' is-role',
            'waiting_for_sitewide_roles' => ' is-warning',
            'unavailable' => ' is-danger',
            default => '',
        };
    }

    private function buildBricksFeatureStatusHelp(string $featureKey, string $status): string
    {
        return match ($featureKey) {
            'bricks_theme_styles_sync_enabled' => match ($status) {
                'synced' => __('Bricks Theme Style sync is active. Tasty is keeping the selected Theme Style mapped to the live sitewide role variables.', 'tasty-fonts'),
                'waiting_for_sitewide_roles' => __('Bricks Theme Style sync is enabled, but Tasty only applies it after sitewide role delivery is turned on.', 'tasty-fonts'),
                'ready' => __('Bricks Theme Style sync is enabled and ready to apply the selected Theme Style mapping.', 'tasty-fonts'),
                'unavailable' => __('Bricks is not active on this site yet, so Tasty cannot update Bricks Theme Styles.', 'tasty-fonts'),
                default => __('Tasty is not managing Bricks Theme Styles right now.', 'tasty-fonts'),
            },
            'bricks_disable_google_fonts_enabled' => match ($status) {
                'synced' => __('Bricks Google Fonts are disabled in Bricks now, so Bricks pickers only show Tasty-supplied fonts.', 'tasty-fonts'),
                'ready' => __('Bricks Google font control is enabled and ready to update Bricks own disable Google Fonts setting.', 'tasty-fonts'),
                'unavailable' => __('Bricks is not active on this site yet, so Tasty cannot update Bricks font settings.', 'tasty-fonts'),
                default => __('Tasty is not managing the Bricks Google Fonts setting right now.', 'tasty-fonts'),
            },
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $updateChannelStatus
     */
    private function buildPluginVersionChannelLabel(string $pluginVersion, array $updateChannelStatus): string
    {
        $selectedChannelLabel = $this->stringValue($updateChannelStatus, 'selected_channel_label');

        if ($selectedChannelLabel !== '') {
            return $selectedChannelLabel;
        }

        if (str_contains($pluginVersion, '-beta.')) {
            return __('Beta', 'tasty-fonts');
        }

        if (str_contains($pluginVersion, '-dev')) {
            return __('Nightly', 'tasty-fonts');
        }

        return __('Stable', 'tasty-fonts');
    }

    /**
     * @param array<string, mixed> $updateChannelStatus
     */
    private function buildPluginVersionStateLabel(array $updateChannelStatus): string
    {
        $state = $this->resolvePluginVersionState($updateChannelStatus);

        return match ($state) {
            'current' => __('Latest', 'tasty-fonts'),
            'upgrade' => __('Update Available', 'tasty-fonts'),
            'rollback' => __('Rollback Available', 'tasty-fonts'),
            default => $this->stringValue($updateChannelStatus, 'state_label'),
        };
    }

    /**
     * @param array<string, mixed> $updateChannelStatus
     */
    private function buildPluginVersionBadgeClass(array $updateChannelStatus): string
    {
        $state = $this->resolvePluginVersionState($updateChannelStatus);

        return match ($state) {
            'upgrade', 'rollback' => 'is-danger',
            'current' => 'is-role',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $updateChannelStatus
     */
    private function resolvePluginVersionState(array $updateChannelStatus): string
    {
        $state = $this->stringValue($updateChannelStatus, 'state');

        if ($state !== '') {
            return $state;
        }

        $stateLabel = strtolower($this->stringValue($updateChannelStatus, 'state_label'));
        $stateClass = $this->stringValue($updateChannelStatus, 'state_class');

        if (str_contains($stateLabel, 'rollback')) {
            return 'rollback';
        }

        if (str_contains($stateLabel, 'upgrade') || str_contains($stateLabel, 'update')) {
            return 'upgrade';
        }

        if (str_contains($stateLabel, 'current') || str_contains($stateLabel, 'latest')) {
            return 'current';
        }

        return match ($stateClass) {
            'is-success' => 'upgrade',
            'is-warning' => 'rollback',
            'is-role' => 'current',
            default => '',
        };
    }

    private function buildPluginVersionMeta(string $channelLabel, string $stateLabel): string
    {
        if ($channelLabel !== '' && $stateLabel !== '') {
            return sprintf(__('%1$s · %2$s', 'tasty-fonts'), $channelLabel, $stateLabel);
        }

        return $channelLabel !== '' ? $channelLabel : $stateLabel;
    }

    private function buildPluginVersionTooltip(
        string $pluginVersion,
        string $channelLabel,
        string $stateLabel,
        string $latestVersion
    ): string {
        $parts = [];

        if ($pluginVersion !== '') {
            $parts[] = sprintf(__('View changelog for version %s on GitHub.', 'tasty-fonts'), $pluginVersion);
        }

        if ($channelLabel !== '') {
            $parts[] = $channelLabel . '.';
        }

        if ($stateLabel !== '' && $latestVersion !== '') {
            $parts[] = sprintf(__('%1$s Latest available: %2$s.', 'tasty-fonts'), $stateLabel, $latestVersion);
        } elseif ($stateLabel !== '') {
            $parts[] = $stateLabel . '.';
        }

        return trim(implode(' ', $parts));
    }

    private function buildPluginVersionAriaLabel(
        string $pluginVersion,
        string $channelLabel,
        string $stateLabel
    ): string {
        $parts = [];

        if ($pluginVersion !== '') {
            $parts[] = sprintf(__('View GitHub changelog for version %s', 'tasty-fonts'), $pluginVersion);
        }

        if ($channelLabel !== '') {
            $parts[] = $channelLabel;
        }

        if ($stateLabel !== '') {
            $parts[] = $stateLabel;
        }

        return implode('. ', $parts);
    }

    /**
     * @param array<string, bool> $classOutputOptions
     * @param array<string, bool> $extendedVariableOptions
     */
    private function deriveOutputQuickMode(
        string $preference,
        array $classOutputOptions,
        array $extendedVariableOptions,
        bool $roleUsageFontWeightEnabled
    ): string
    {
        $preference = $this->sanitizeOutputQuickModePreference($preference);
        $exactMode = $this->deriveExactOutputQuickMode($classOutputOptions, $extendedVariableOptions, $roleUsageFontWeightEnabled);

        if ($preference === 'custom') {
            return 'custom';
        }

        if ($preference === '') {
            return $exactMode;
        }

        return $exactMode === $preference
            ? $preference
            : 'custom';
    }

    /**
     * @param array<string, bool> $classOutputOptions
     * @param array<string, bool> $extendedVariableOptions
     */
    private function deriveExactOutputQuickMode(
        array $classOutputOptions,
        array $extendedVariableOptions,
        bool $roleUsageFontWeightEnabled
    ): string {
        if (!empty($extendedVariableOptions['minimal'])) {
            return 'minimal';
        }

        if (
            empty($classOutputOptions['enabled'])
            && !empty($extendedVariableOptions['enabled'])
            && !$roleUsageFontWeightEnabled
            && $this->allOutputFlagsEnabled($extendedVariableOptions, ['enabled', 'minimal'])
        ) {
            return 'variables';
        }

        if (
            !empty($classOutputOptions['enabled'])
            && empty($extendedVariableOptions['enabled'])
            && !$roleUsageFontWeightEnabled
            && $this->allOutputFlagsEnabled($classOutputOptions, ['enabled'])
        ) {
            return 'classes';
        }

        return 'custom';
    }

    /**
     * @param array<string, bool> $options
     * @param list<string> $ignoredKeys
     */
    private function allOutputFlagsEnabled(array $options, array $ignoredKeys = []): bool
    {
        foreach ($options as $key => $enabled) {
            if (in_array((string) $key, $ignoredKeys, true)) {
                continue;
            }

            if (!$enabled) {
                return false;
            }
        }

        return true;
    }

    private function sanitizeOutputQuickModePreference(string $preference): string
    {
        $preference = strtolower(trim($preference));
        $preference = preg_replace('/[^a-z0-9_-]+/', '', $preference);
        $preference = is_string($preference) ? $preference : '';

        return in_array($preference, ['minimal', 'variables', 'classes', 'custom'], true)
            ? $preference
            : '';
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function mapValue(array $values, string $key): array
    {
        return FontUtils::normalizeStringKeyedMap($values[$key] ?? null);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $value = FontUtils::scalarStringValue($values[$key]);

        return $value !== '' ? $value : $default;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function intValue(array $values, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        return FontUtils::scalarIntValue($values[$key], $default);
    }

    /**
     * @param array<string, mixed> $values
     * @return CatalogMap
     */
    private function catalogMapValue(array $values, string $key): array
    {
        $catalog = $this->mapValue($values, $key);
        $normalized = [];

        foreach ($catalog as $familyName => $family) {
            $normalizedFamily = FontUtils::normalizeStringKeyedMap($family);

            if ($normalizedFamily === []) {
                continue;
            }

            $normalized[$familyName] = $normalizedFamily;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $values
     * @return FamilyFallbackMap
     */
    private function stringMapValue(array $values, string $key): array
    {
        return FontUtils::normalizeStringMap($values[$key] ?? []);
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $default
     * @return list<string>
     */
    private function stringListValue(array $values, string $key, array $default = []): array
    {
        if (!array_key_exists($key, $values) || !is_array($values[$key])) {
            return $default;
        }

        $normalized = [];

        foreach ($values[$key] as $value) {
            $stringValue = FontUtils::scalarStringValue($value);

            if ($stringValue === '') {
                continue;
            }

            $normalized[] = $stringValue;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $values
     * @return list<array<string, mixed>>
     */
    private function listOfMapsValue(array $values, string $key): array
    {
        return FontUtils::normalizeListOfStringKeyedMaps($values[$key] ?? []);
    }

    /**
     * @param array<string, mixed> $values
     * @return list<array{value: string, label: string}>
     */
    private function valueLabelOptionListValue(array $values, string $key): array
    {
        $options = $this->listOfMapsValue($values, $key);
        $normalized = [];

        foreach ($options as $option) {
            $value = $this->stringValue($option, 'value');
            $label = $this->stringValue($option, 'label');

            if ($value === '' && $label === '') {
                continue;
            }

            $normalized[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $values
     * @return list<array{value: string, label: string, type: string}>
     */
    private function selectorOptionListValue(array $values, string $key): array
    {
        $options = $this->listOfMapsValue($values, $key);
        $normalized = [];

        foreach ($options as $option) {
            $value = $this->stringValue($option, 'value');
            $label = $this->stringValue($option, 'label');
            $type = $this->stringValue($option, 'type');

            if ($value === '' && $label === '' && $type === '') {
                continue;
            }

            $normalized[] = [
                'value' => $value,
                'label' => $label,
                'type' => $type,
            ];
        }

        return $normalized;
    }
}
