<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

/**
 * @phpstan-type AdminPageView array<string, mixed>
 */
final class AdminPageViewVariables
{
    /**
     * @param array<array-key, mixed> $storage
     * @param array<array-key, mixed> $pageUrls
     * @param array<array-key, mixed> $catalog
     * @param array<array-key, mixed> $availableFamilies
     * @param array<array-key, mixed> $availableFamilyOptions
     * @param array<array-key, mixed> $availableFamilyLabels
     * @param array<array-key, mixed> $roles
     * @param array<array-key, mixed> $appliedRoles
     * @param array<array-key, mixed> $logs
     * @param array<array-key, mixed> $activityActorOptions
     * @param array<array-key, mixed> $familyFallbacks
     * @param array<array-key, mixed> $familyFontDisplays
     * @param array<array-key, mixed> $familyFontDisplayOptions
     * @param array<array-key, mixed> $adobeDetectedFamilies
     * @param array<array-key, mixed> $cssDeliveryModeOptions
     * @param array<array-key, mixed> $fontDisplayOptions
     * @param array<array-key, mixed> $globalFallbackSettings
     * @param array<array-key, mixed> $unicodeRangeModeOptions
     * @param array<array-key, mixed> $updateChannelOptions
     * @param array<array-key, mixed> $updateChannelStatus
     * @param array<array-key, mixed> $adminAccessRoleSlugs
     * @param array<array-key, mixed> $adminAccessRoleOptions
     * @param array<array-key, mixed> $adminAccessUserIds
     * @param array<array-key, mixed> $adminAccessUserOptions
     * @param array<array-key, mixed> $adminAccessSummary
     * @param array<array-key, mixed> $developerToolStatuses
     * @param array<array-key, mixed> $advancedTools
     * @param array<array-key, mixed> $diagnosticItems
     * @param array<array-key, mixed> $overviewMetrics
     * @param array<array-key, mixed> $outputPanels
     * @param array<array-key, mixed> $outputTogglePreviews
     * @param array<array-key, mixed> $generatedCssPanel
     * @param array<array-key, mixed> $previewPanels
     * @param array<array-key, mixed> $localEnvironmentNotice
     * @param array<array-key, mixed> $siteTransfer
     * @param array<array-key, mixed> $gutenbergIntegration
     * @param array<array-key, mixed> $etchIntegration
     * @param array<array-key, mixed> $acssIntegration
     * @param array<array-key, mixed> $bricksIntegration
     * @param array<array-key, mixed> $oxygenIntegration
     * @param array<array-key, mixed> $toasts
     * @param array<array-key, mixed> $roleDeployment
     * @param array<array-key, mixed> $libraryCategoryOptions
     * @param array<array-key, mixed> $previewRoles
     * @param array<array-key, mixed> $libraryRoleUsageRoles
     * @param array<array-key, mixed> $categoryAliasOwners
     * @param array<array-key, mixed> $outputDependencyStates
     * @param array<array-key, mixed> $extendedVariableOptions
     * @param array<array-key, mixed> $classOutputOptions
     */
    public function __construct(
        // Core data.
        public readonly array $storage,
        public readonly string $currentPage,
        public readonly array $pageUrls,
        public readonly array $catalog,
        public readonly array $availableFamilies,
        public readonly array $availableFamilyOptions,
        public readonly array $availableFamilyLabels,
        public readonly array $roles,
        public readonly array $appliedRoles,
        public readonly array $logs,
        public readonly array $activityActorOptions,
        public readonly array $familyFallbacks,
        public readonly array $familyFontDisplays,
        public readonly array $familyFontDisplayOptions,

        // Provider and access settings.
        public readonly string $previewText,
        public readonly int $previewSize,
        public readonly string $googleApiState,
        public readonly bool $googleApiEnabled,
        public readonly bool $googleApiSaved,
        public readonly bool $googleAccessExpanded,
        public readonly string $adobeProjectState,
        public readonly string $googleStatusLabel,
        public readonly string $googleStatusClass,
        public readonly string $googleAccessCopy,
        public readonly string $googleSearchDisabledCopy,
        public readonly bool $adobeProjectEnabled,
        public readonly bool $adobeProjectSaved,
        public readonly bool $adobeAccessExpanded,
        public readonly string $adobeProjectId,
        public readonly string $adobeStatusLabel,
        public readonly string $adobeStatusClass,
        public readonly string $adobeAccessCopy,
        public readonly string $adobeProjectLink,
        public readonly array $adobeDetectedFamilies,
        public readonly string $googleAccessButtonLabel,
        public readonly string $adobeAccessButtonLabel,

        // Output settings.
        public readonly string $cssDeliveryMode,
        public readonly array $cssDeliveryModeOptions,
        public readonly string $fontDisplay,
        public readonly array $fontDisplayOptions,
        public readonly string $unicodeRangeMode,
        public readonly string $unicodeRangeCustomValue,
        public readonly string $fallbackHeading,
        public readonly string $fallbackBody,
        public readonly string $fallbackMonospace,
        public readonly array $globalFallbackSettings,
        public readonly array $unicodeRangeModeOptions,
        public readonly bool $unicodeRangeCustomVisible,
        public readonly string $outputQuickModePreference,
        public readonly bool $classOutputEnabled,
        public readonly bool $classOutputRoleHeadingEnabled,
        public readonly bool $classOutputRoleBodyEnabled,
        public readonly bool $classOutputRoleMonospaceEnabled,
        public readonly bool $classOutputRoleAliasInterfaceEnabled,
        public readonly bool $classOutputRoleAliasUiEnabled,
        public readonly bool $classOutputRoleAliasCodeEnabled,
        public readonly bool $classOutputCategorySansEnabled,
        public readonly bool $classOutputCategorySerifEnabled,
        public readonly bool $classOutputCategoryMonoEnabled,
        public readonly bool $classOutputFamiliesEnabled,
        public readonly bool $classOutputRoleStylesEnabled,
        public readonly bool $minifyCssOutput,
        public readonly bool $roleUsageFontWeightEnabled,
        public readonly bool $perVariantFontVariablesEnabled,
        public readonly bool $minimalOutputPresetEnabled,
        public readonly bool $extendedVariableRoleWeightVarsEnabled,
        public readonly bool $extendedVariableWeightTokensEnabled,
        public readonly bool $extendedVariableRoleAliasesEnabled,
        public readonly bool $extendedVariableRoleAliasInterfaceEnabled,
        public readonly bool $extendedVariableRoleAliasUiEnabled,
        public readonly bool $extendedVariableRoleAliasCodeEnabled,
        public readonly bool $extendedVariableCategorySansEnabled,
        public readonly bool $extendedVariableCategorySerifEnabled,
        public readonly bool $extendedVariableCategoryMonoEnabled,
        public readonly bool $preloadPrimaryFonts,
        public readonly bool $remoteConnectionHints,
        public readonly string $updateChannel,
        public readonly array $updateChannelOptions,
        public readonly array $updateChannelStatus,

        // Admin access and tooling.
        public readonly bool $adminAccessCustomEnabled,
        public readonly array $adminAccessRoleSlugs,
        public readonly array $adminAccessRoleOptions,
        public readonly array $adminAccessUserIds,
        public readonly array $adminAccessUserOptions,
        public readonly array $adminAccessSummary,
        public readonly array $developerToolStatuses,
        public readonly bool $blockEditorFontLibrarySyncEnabled,
        public readonly bool $showActivityLog,
        public readonly bool $trainingWheelsOff,
        public readonly bool $variableFontsEnabled,
        public readonly bool $googleFontImportsEnabled,
        public readonly bool $bunnyFontImportsEnabled,
        public readonly bool $adobeFontImportsEnabled,
        public readonly bool $localFontUploadsEnabled,
        public readonly bool $customCssUrlImportsAvailable,
        public readonly bool $customCssUrlImportsEnabled,
        public readonly string $customCssUrlImportsExperimentalHelp,
        public readonly bool $deleteUploadedFilesOnUninstall,
        public readonly array $advancedTools,
        public readonly array $diagnosticItems,
        public readonly array $overviewMetrics,
        public readonly array $outputPanels,
        public readonly array $outputTogglePreviews,
        public readonly array $generatedCssPanel,
        public readonly array $previewPanels,
        public readonly array $localEnvironmentNotice,
        public readonly array $siteTransfer,

        // Integration data.
        public readonly array $gutenbergIntegration,
        public readonly array $etchIntegration,
        public readonly array $acssIntegration,
        public readonly bool $roleWeightVariablesLocked,
        public readonly array $bricksIntegration,
        public readonly array $oxygenIntegration,

        // UI state and preview.
        public readonly array $toasts,
        public readonly bool $applyEverywhere,
        public readonly string $previewBaselineSource,
        public readonly string $previewBaselineLabel,
        public readonly array $roleDeployment,
        public readonly bool $monospaceRoleEnabled,
        public readonly array $libraryCategoryOptions,
        public readonly array $previewRoles,
        public readonly bool $hasPendingLiveRoleChanges,
        public readonly bool $previewHasDraftRoleChanges,
        public readonly bool $previewHasPendingLiveRoleChanges,
        public readonly array $libraryRoleUsageRoles,
        public readonly string $previewHeadingFallback,
        public readonly string $previewBodyFallback,
        public readonly string $previewMonospaceFallback,
        public readonly string $previewHeadingStack,
        public readonly string $previewBodyStack,
        public readonly string $previewMonospaceStack,
        public readonly string $saveDraftDisabledCopy,
        public readonly string $applyLiveDisabledCopy,

        // Derived typography views.
        public readonly string $headingFamily,
        public readonly string $bodyFamily,
        public readonly string $monospaceFamily,
        public readonly string $headingFallback,
        public readonly string $bodyFallback,
        public readonly string $monospaceFallback,
        public readonly string $headingStack,
        public readonly string $bodyStack,
        public readonly string $monospaceStack,
        public readonly string $headingVariable,
        public readonly string $bodyVariable,
        public readonly string $monospaceVariable,
        public readonly string $headingFamilyVariable,
        public readonly string $bodyFamilyVariable,
        public readonly string $monospaceFamilyVariable,

        // Plugin version and computed views.
        public readonly string $pluginVersion,
        public readonly string $pluginRepositoryUrl,
        public readonly string $pluginVersionUrl,
        public readonly string $pluginVersionChannelLabel,
        public readonly string $pluginVersionStateLabel,
        public readonly string $pluginVersionMeta,
        public readonly string $pluginVersionBadgeClass,
        public readonly string $pluginVersionTooltip,
        public readonly string $pluginVersionAriaLabel,
        public readonly string $roleDeploymentBadge,
        public readonly string $roleDeploymentBadgeClass,
        public readonly string $roleDeploymentTitle,
        public readonly string $roleDeploymentCopy,
        public readonly string $roleDeploymentTooltip,
        public readonly string $roleDeploymentAnnouncementId,
        public readonly string $storageErrorMessage,
        public readonly array $categoryAliasOwners,
        public readonly bool $bodyFamilyAssigned,
        public readonly bool $monospaceFamilyAssigned,
        public readonly array $outputDependencyStates,
        public readonly array $extendedVariableOptions,
        public readonly array $classOutputOptions,
        public readonly string $outputQuickMode,
        public readonly bool $advancedOutputControlsExpanded
    ) {
    }

    /**
     * @return AdminPageView
     */
    public function toArray(): array
    {
        $view = [];

        foreach (get_object_vars($this) as $key => $value) {
            $view[(string) $key] = $value;
        }

        return $view;
    }
}
