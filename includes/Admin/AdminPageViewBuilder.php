<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Admin\FontTypeHelper;
use TastyFonts\Admin\Renderer\SharedRenderHelpers;
use TastyFonts\Admin\Renderer\LibraryRenderValueHelpers;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;

final class AdminPageViewBuilder
{
    use SharedRenderHelpers;
    use LibraryRenderValueHelpers;

    public function __construct(private readonly Storage $storage)
    {
    }

    public function build(array $context): array
    {
        $storage = is_array($context['storage'] ?? null) ? $context['storage'] : null;
        $currentPage = (string) ($context['current_page'] ?? AdminController::PAGE_ROLES);
        $currentPageSlug = (string) ($context['current_page_slug'] ?? '');
        $pageUrls = is_array($context['page_urls'] ?? null) ? $context['page_urls'] : [];
        $catalog = is_array($context['catalog'] ?? null) ? $context['catalog'] : [];
        $libraryCategoryOptions = $this->buildLibraryCategoryOptions();
        $availableFamilies = is_array($context['available_families'] ?? null) ? $context['available_families'] : array_keys($catalog);
        $availableFamilyOptions = is_array($context['available_family_options'] ?? null) ? $context['available_family_options'] : array_map(
            static function ($familyName) use ($catalog): array {
                $name = trim((string) $familyName);
                $catalogEntry = is_array($catalog[$name] ?? null) ? $catalog[$name] : null;
                $descriptor = $catalogEntry !== null ? FontTypeHelper::describeEntry($catalogEntry) : null;

                return [
                    'value' => $name,
                    'label' => FontTypeHelper::buildSelectorOptionLabel($name, $catalogEntry),
                    'type' => is_array($descriptor) ? (string) ($descriptor['type'] ?? '') : '',
                ];
            },
            $availableFamilies
        );
        $availableFamilyLabels = [];

        foreach ($availableFamilyOptions as $option) {
            if (!is_array($option)) {
                continue;
            }

            $optionValue = trim((string) ($option['value'] ?? ''));

            if ($optionValue === '') {
                continue;
            }

            $availableFamilyLabels[$optionValue] = (string) ($option['label'] ?? $optionValue);
        }
        $roles = is_array($context['roles'] ?? null) ? $context['roles'] : [];
        $appliedRoles = is_array($context['applied_roles'] ?? null) ? $context['applied_roles'] : [];
        $logs = is_array($context['logs'] ?? null) ? $context['logs'] : [];
        $activityActorOptions = is_array($context['activity_actor_options'] ?? null) ? $context['activity_actor_options'] : [];
        $familyFallbacks = is_array($context['family_fallbacks'] ?? null) ? $context['family_fallbacks'] : [];
        $familyFontDisplays = is_array($context['family_font_displays'] ?? null) ? $context['family_font_displays'] : [];
        $familyFontDisplayOptions = is_array($context['family_font_display_options'] ?? null) ? $context['family_font_display_options'] : [];
        $previewText = (string) ($context['preview_text'] ?? '');
        $previewSize = (int) ($context['preview_size'] ?? 32);
        $googleApiState = (string) ($context['google_api_state'] ?? 'empty');
        $googleApiEnabled = !empty($context['google_api_enabled']);
        $googleApiSaved = !empty($context['google_api_saved']);
        $googleAccessExpanded = !empty($context['google_access_expanded']);
        $adobeProjectState = (string) ($context['adobe_project_state'] ?? 'empty');
        $googleStatusLabel = (string) ($context['google_status_label'] ?? '');
        $googleStatusClass = (string) ($context['google_status_class'] ?? '');
        $googleAccessCopy = (string) ($context['google_access_copy'] ?? '');
        $googleSearchDisabledCopy = (string) ($context['google_search_disabled_copy'] ?? '');
        $adobeProjectEnabled = !empty($context['adobe_project_enabled']);
        $adobeProjectSaved = !empty($context['adobe_project_saved']);
        $adobeAccessExpanded = !empty($context['adobe_access_expanded']);
        $adobeProjectId = (string) ($context['adobe_project_id'] ?? '');
        $adobeStatusLabel = (string) ($context['adobe_status_label'] ?? '');
        $adobeStatusClass = (string) ($context['adobe_status_class'] ?? '');
        $adobeAccessCopy = (string) ($context['adobe_access_copy'] ?? '');
        $adobeProjectLink = (string) ($context['adobe_project_link'] ?? 'https://fonts.adobe.com/');
        $adobeDetectedFamilies = is_array($context['adobe_detected_families'] ?? null) ? $context['adobe_detected_families'] : [];
        $bunnyCatalogLink = 'https://fonts.bunny.net/';
        $googleAccessButtonLabel = $googleApiEnabled ? __('Edit Key', 'tasty-fonts') : __('Key Settings', 'tasty-fonts');
        $adobeAccessButtonLabel = $adobeProjectSaved ? __('Project Settings', 'tasty-fonts') : __('Add Project', 'tasty-fonts');
        $cssDeliveryMode = (string) ($context['css_delivery_mode'] ?? 'file');
        $cssDeliveryModeOptions = is_array($context['css_delivery_mode_options'] ?? null) ? $context['css_delivery_mode_options'] : [];
        $fontDisplay = (string) ($context['font_display'] ?? 'optional');
        $fontDisplayOptions = is_array($context['font_display_options'] ?? null) ? $context['font_display_options'] : [];
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
        $minifyCssOutput = !empty($context['minify_css_output']);
        $roleUsageFontWeightEnabled = !empty($context['role_usage_font_weight_enabled']);
        $perVariantFontVariablesEnabled = !array_key_exists('per_variant_font_variables_enabled', $context)
            || !empty($context['per_variant_font_variables_enabled']);
        $minimalOutputPresetEnabled = !empty($context['minimal_output_preset_enabled']);
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
        $updateChannel = (string) ($context['update_channel'] ?? 'stable');
        $updateChannelOptions = is_array($context['update_channel_options'] ?? null) ? $context['update_channel_options'] : [];
        $updateChannelStatus = is_array($context['update_channel_status'] ?? null) ? $context['update_channel_status'] : [];
        $blockEditorFontLibrarySyncEnabled = !empty($context['block_editor_font_library_sync_enabled']);
        $trainingWheelsOff = !empty($context['training_wheels_off']);
        $variableFontsEnabled = !empty($context['variable_fonts_enabled']);
        $deleteUploadedFilesOnUninstall = !empty($context['delete_uploaded_files_on_uninstall']);
        $diagnosticItems = is_array($context['diagnostic_items'] ?? null) ? $context['diagnostic_items'] : [];
        $overviewMetrics = is_array($context['overview_metrics'] ?? null) ? $context['overview_metrics'] : [];
        $outputPanels = is_array($context['output_panels'] ?? null) ? $context['output_panels'] : [];
        $generatedCssPanel = is_array($context['generated_css_panel'] ?? null) ? $context['generated_css_panel'] : [];
        $previewPanels = is_array($context['preview_panels'] ?? null) ? $context['preview_panels'] : [];
        $localEnvironmentNotice = is_array($context['local_environment_notice'] ?? null) ? $context['local_environment_notice'] : [];
        $gutenbergIntegration = is_array($context['gutenberg_integration'] ?? null) ? $context['gutenberg_integration'] : [];
        $etchIntegration = is_array($context['etch_integration'] ?? null) ? $context['etch_integration'] : [];
        $acssIntegration = is_array($context['acss_integration'] ?? null) ? $context['acss_integration'] : [];
        $bricksIntegration = is_array($context['bricks_integration'] ?? null) ? $context['bricks_integration'] : [];
        $oxygenIntegration = is_array($context['oxygen_integration'] ?? null) ? $context['oxygen_integration'] : [];
        $toasts = is_array($context['toasts'] ?? null) ? $context['toasts'] : [];
        $applyEverywhere = !empty($context['apply_everywhere']);
        $previewBaselineSource = (string) ($context['preview_baseline_source'] ?? ($applyEverywhere ? 'live_sitewide' : 'draft'));
        $previewBaselineLabel = (string) ($context['preview_baseline_label'] ?? ($applyEverywhere ? __('Live sitewide', 'tasty-fonts') : __('Current draft', 'tasty-fonts')));
        $roleDeployment = is_array($context['role_deployment'] ?? null) ? $context['role_deployment'] : [];
        $monospaceRoleEnabled = !empty($context['monospace_role_enabled']);
        $previewRoles = $previewBaselineSource === 'live_sitewide' && $appliedRoles !== []
            ? $appliedRoles
            : $roles;
        $hasPendingLiveRoleChanges = $applyEverywhere && !$this->roleSetsMatch($roles, $appliedRoles, $monospaceRoleEnabled, $catalog, $variableFontsEnabled);
        $previewHasDraftRoleChanges = !$this->roleSetsMatch($previewRoles, $roles, $monospaceRoleEnabled, $catalog, $variableFontsEnabled);
        $previewHasPendingLiveRoleChanges = $applyEverywhere && !$this->roleSetsMatch($previewRoles, $appliedRoles, $monospaceRoleEnabled, $catalog, $variableFontsEnabled);
        $previewHeadingStack = FontUtils::buildFontStack(
            (string) ($previewRoles['heading'] ?? ''),
            (string) ($previewRoles['heading_fallback'] ?? 'sans-serif')
        );
        $previewBodyStack = FontUtils::buildFontStack(
            (string) ($previewRoles['body'] ?? ''),
            (string) ($previewRoles['body_fallback'] ?? 'sans-serif')
        );
        $previewMonospaceStack = FontUtils::buildFontStack(
            (string) ($previewRoles['monospace'] ?? ''),
            (string) ($previewRoles['monospace_fallback'] ?? 'monospace')
        );
        $saveDraftDisabledCopy = __('No draft changes to save.', 'tasty-fonts');
        $applyLiveDisabledCopy = !$applyEverywhere
            ? __('Sitewide delivery is off. Turn it on before publishing role changes.', 'tasty-fonts')
            : __('No live role changes to publish.', 'tasty-fonts');
        $headingFamily = (string) ($roles['heading'] ?? '');
        $bodyFamily = (string) ($roles['body'] ?? '');
        $monospaceFamily = (string) ($roles['monospace'] ?? '');
        $headingFallback = (string) ($roles['heading_fallback'] ?? 'sans-serif');
        $bodyFallback = (string) ($roles['body_fallback'] ?? 'sans-serif');
        $monospaceFallback = (string) ($roles['monospace_fallback'] ?? 'monospace');
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
        $roleDeploymentBadge = (string) ($roleDeployment['badge'] ?? '');
        $roleDeploymentBadgeClass = (string) ($roleDeployment['badge_class'] ?? '');
        $roleDeploymentTitle = trim((string) ($roleDeployment['title'] ?? ''));
        $roleDeploymentCopy = trim((string) ($roleDeployment['copy'] ?? ''));
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
        $outputQuickMode = $this->deriveOutputQuickMode($classOutputOptions, $extendedVariableOptions);
        $advancedOutputControlsExpanded = $outputQuickMode === 'custom';

        $view = get_defined_vars();
        unset($view['context']);

        return $view;
    }

    private function deriveOutputQuickMode(array $classOutputOptions, array $extendedVariableOptions): string
    {
        $classEnabled = !empty($classOutputOptions['enabled']);
        $variableEnabled = !empty($extendedVariableOptions['enabled']);
        $minimalEnabled = !empty($extendedVariableOptions['minimal']);

        if ($minimalEnabled && $variableEnabled && !$classEnabled) {
            return 'minimal';
        }

        if ($variableEnabled && !$classEnabled) {
            return 'variables';
        }

        if ($classEnabled && !$variableEnabled) {
            return 'classes';
        }

        return 'custom';
    }
}
