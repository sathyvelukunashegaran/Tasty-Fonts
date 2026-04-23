<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Admin\FontTypeHelper;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Integrations\AcssIntegrationService;
use TastyFonts\Integrations\BricksIntegrationService;
use TastyFonts\Integrations\OxygenIntegrationService;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\SiteEnvironment;
use TastyFonts\Support\Storage;
use TastyFonts\Support\TransientKey;
use TastyFonts\Updates\GitHubUpdater;

/**
 * @phpstan-import-type CatalogCounts from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type CatalogFamily from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type CatalogMap from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type AdminAccessRoleSlugList from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type AdminAccessUserIdList from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type NormalizedSettings from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type RoleSet from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type FamilyFallbackMap from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type FamilyFontDisplayMap from \TastyFonts\Repository\SettingsRepository
 * @phpstan-type PageContext array<string, mixed>
 * @phpstan-type DiagnosticItem array<string, mixed>
 * @phpstan-type UpdateChannelOption array<string, string>
 * @phpstan-type UpdateChannelStatus array<string, mixed>
 * @phpstan-type OverviewMetric array<string, mixed>
 * @phpstan-type OutputPanel array<string, mixed>
 * @phpstan-type GeneratedCssPanel array<string, mixed>
 * @phpstan-type PreviewPanel array<string, mixed>
 * @phpstan-type NoticeToast array<string, mixed>
 * @phpstan-type ActivityLogEntry array<string, mixed>
 * @phpstan-type StorageState array<string, mixed>
 * @phpstan-type AssetStatus array<string, mixed>
 * @phpstan-type IntegrationContext array<string, mixed>
 */
final class AdminPageContextBuilder
{
    private const BASE_ROLE_KEYS = ['heading', 'body'];
    private const DOWNLOAD_ACTION = 'tasty_fonts_download_generated_css';

    public function __construct(
        private readonly Storage $storage,
        private readonly SettingsRepository $settings,
        private readonly LogRepository $log,
        private readonly CatalogService $catalog,
        private readonly AssetService $assets,
        private readonly CssBuilder $cssBuilder,
        private readonly AdobeProjectClient $adobe,
        private readonly GoogleFontsClient $googleClient,
        private readonly AcssIntegrationService $acssIntegration,
        private readonly BricksIntegrationService $bricksIntegration,
        private readonly OxygenIntegrationService $oxygenIntegration,
        private readonly ?GitHubUpdater $updater = null
    ) {
    }

    /**
     * @return PageContext
     */
    public function build(): array
    {
        $storage = $this->storage->get();
        $settings = $this->settings->getSettings();
        $catalog = $this->catalog->getCatalog();
        $rawLogs = $this->log->all();
        $logs = $this->normalizeActivityLogEntries($rawLogs);
        $counts = $this->catalog->getCounts();
        $assetStatus = $this->assets->getStatus();
        $familyFallbacks = FontUtils::normalizeStringMap($settings['family_fallbacks'] ?? []);
        $familyFontDisplays = FontUtils::normalizeStringMap($settings['family_font_displays'] ?? []);
        $applyEverywhere = !empty($settings['auto_apply_roles']);
        $previewContext = $this->buildPreviewContext($settings);
        $adobeAccessContext = $this->buildAdobeAccessContext();
        $availableFamilies = $this->buildSelectableFamilyNames($catalog);
        $availableFamilyOptions = $this->buildSelectableFamilyOptions($catalog, $availableFamilies);
        $roles = $this->settings->getRoles($availableFamilies);
        $appliedRoles = $this->settings->getAppliedRoles($availableFamilies);
        $googleAccessContext = $this->buildGoogleAccessContext();
        $roleDeploymentContext = $this->buildRoleDeploymentContext($roles, $appliedRoles, $applyEverywhere, $settings);
        $localEnvironmentNotice = $this->buildLocalEnvironmentNotice($settings);
        $gutenbergIntegration = $this->buildGutenbergIntegrationContext($settings);
        $etchIntegration = $this->buildEtchIntegrationContext();
        $acssIntegration = $this->buildAcssIntegrationContext($settings);
        $bricksIntegration = $this->buildBricksIntegrationContext($settings);
        $oxygenIntegration = $this->buildOxygenIntegrationContext($settings);
        $updateChannel = $this->stringValue($settings, 'update_channel', SettingsRepository::UPDATE_CHANNEL_STABLE);
        $updateChannelStatus = $this->buildUpdateChannelStatus($updateChannel);
        $adminAccessCustomEnabled = !empty($settings['admin_access_custom_enabled']);
        $adminAccessRoleSlugs = $this->normalizeAdminAccessRoleSlugs($settings['admin_access_role_slugs'] ?? []);
        $adminAccessUserIds = $this->normalizeAdminAccessUserIds($settings['admin_access_user_ids'] ?? []);
        $adminAccessRoleOptions = $this->buildAdminAccessRoleOptions();
        $adminAccessUserOptions = $this->buildAdminAccessUserOptions();
        $adminAccessImplicitAdminLabels = $this->buildAdminAccessImplicitAdminLabels();
        $adminAccessSummary = $this->buildAdminAccessSummary($adminAccessRoleSlugs, $adminAccessUserIds, $adminAccessRoleOptions, $adminAccessImplicitAdminLabels);
        $developerToolStatuses = $this->buildDeveloperToolStatuses($rawLogs, $assetStatus, $counts);
        $previewBaselineSource = $applyEverywhere ? 'live_sitewide' : 'draft';
        $previewBaselineLabel = $applyEverywhere
            ? __('Live sitewide', 'tasty-fonts')
            : __('Current draft', 'tasty-fonts');

        return [
            'storage' => $storage,
            'catalog' => $catalog,
            'available_families' => $availableFamilies,
            'available_family_options' => $availableFamilyOptions,
            'roles' => $roles,
            'applied_roles' => $appliedRoles,
            'preview_baseline_source' => $previewBaselineSource,
            'preview_baseline_label' => $previewBaselineLabel,
            'apply_everywhere' => $applyEverywhere,
            'role_deployment' => $roleDeploymentContext,
            'logs' => $logs,
            'activity_actor_options' => $this->buildActivityActorOptions($logs),
            'family_fallbacks' => $familyFallbacks,
            'family_font_displays' => $familyFontDisplays,
            'family_font_display_options' => $this->buildFamilyFontDisplayOptions($this->stringValue($settings, 'font_display', 'swap')),
            'preview_text' => $previewContext['preview_text'],
            'preview_size' => $previewContext['preview_size'],
            'google_api_state' => $googleAccessContext['google_api_state'],
            'google_api_enabled' => $googleAccessContext['google_api_enabled'],
            'google_api_saved' => $googleAccessContext['google_api_saved'],
            'google_access_expanded' => $googleAccessContext['google_access_expanded'],
            'google_status_label' => $googleAccessContext['google_status_label'],
            'google_status_class' => $googleAccessContext['google_status_class'],
            'google_access_copy' => $googleAccessContext['google_access_copy'],
            'google_search_disabled_copy' => $googleAccessContext['google_search_disabled_copy'],
            'adobe_project_state' => $adobeAccessContext['adobe_project_state'],
            'adobe_project_enabled' => $adobeAccessContext['adobe_project_enabled'],
            'adobe_project_saved' => $adobeAccessContext['adobe_project_saved'],
            'adobe_access_expanded' => $adobeAccessContext['adobe_access_expanded'],
            'adobe_project_id' => $adobeAccessContext['adobe_project_id'],
            'adobe_status_label' => $adobeAccessContext['adobe_status_label'],
            'adobe_status_class' => $adobeAccessContext['adobe_status_class'],
            'adobe_access_copy' => $adobeAccessContext['adobe_access_copy'],
            'adobe_project_link' => $adobeAccessContext['adobe_project_link'],
            'adobe_detected_families' => $adobeAccessContext['adobe_detected_families'],
            'css_delivery_mode' => $this->stringValue($settings, 'css_delivery_mode', 'file'),
            'css_delivery_mode_options' => $this->buildCssDeliveryModeOptions(),
            'font_display' => $this->stringValue($settings, 'font_display', 'swap'),
            'font_display_options' => $this->buildFontDisplayOptions(),
            'unicode_range_mode' => $this->stringValue($settings, 'unicode_range_mode', FontUtils::UNICODE_RANGE_MODE_OFF),
            'unicode_range_custom_value' => $this->stringValue($settings, 'unicode_range_custom_value'),
            'unicode_range_mode_options' => $this->buildUnicodeRangeModeOptions(),
            'unicode_range_custom_visible' => FontUtils::normalizeUnicodeRangeMode($this->stringValue($settings, 'unicode_range_mode')) === FontUtils::UNICODE_RANGE_MODE_CUSTOM,
            'output_quick_mode_preference' => $this->stringValue($settings, 'output_quick_mode_preference', 'minimal'),
            'class_output_enabled' => !empty($settings['class_output_enabled']),
            'class_output_role_heading_enabled' => !empty($settings['class_output_role_heading_enabled']),
            'class_output_role_body_enabled' => !empty($settings['class_output_role_body_enabled']),
            'class_output_role_monospace_enabled' => !empty($settings['class_output_role_monospace_enabled']),
            'class_output_role_alias_interface_enabled' => !empty($settings['class_output_role_alias_interface_enabled']),
            'class_output_role_alias_ui_enabled' => !empty($settings['class_output_role_alias_ui_enabled']),
            'class_output_role_alias_code_enabled' => !empty($settings['class_output_role_alias_code_enabled']),
            'class_output_category_sans_enabled' => !empty($settings['class_output_category_sans_enabled']),
            'class_output_category_serif_enabled' => !empty($settings['class_output_category_serif_enabled']),
            'class_output_category_mono_enabled' => !empty($settings['class_output_category_mono_enabled']),
            'class_output_families_enabled' => !empty($settings['class_output_families_enabled']),
            'minify_css_output' => !empty($settings['minify_css_output']),
            'role_usage_font_weight_enabled' => !empty($settings['role_usage_font_weight_enabled']),
            'per_variant_font_variables_enabled' => !empty($settings['per_variant_font_variables_enabled']),
            'minimal_output_preset_enabled' => !empty($settings['minimal_output_preset_enabled']),
            'extended_variable_role_weight_vars_enabled' => !empty($settings['extended_variable_role_weight_vars_enabled']),
            'extended_variable_weight_tokens_enabled' => !empty($settings['extended_variable_weight_tokens_enabled']),
            'extended_variable_role_aliases_enabled' => !empty($settings['extended_variable_role_aliases_enabled']),
            'extended_variable_category_sans_enabled' => !empty($settings['extended_variable_category_sans_enabled']),
            'extended_variable_category_serif_enabled' => !empty($settings['extended_variable_category_serif_enabled']),
            'extended_variable_category_mono_enabled' => !empty($settings['extended_variable_category_mono_enabled']),
            'preload_primary_fonts' => !empty($settings['preload_primary_fonts']),
            'remote_connection_hints' => !empty($settings['remote_connection_hints']),
            'update_channel' => $updateChannel,
            'update_channel_options' => $this->buildUpdateChannelOptions(),
            'update_channel_status' => $updateChannelStatus,
            'admin_access_custom_enabled' => $adminAccessCustomEnabled,
            'admin_access_role_slugs' => $adminAccessRoleSlugs,
            'admin_access_role_options' => $adminAccessRoleOptions,
            'admin_access_user_ids' => $adminAccessUserIds,
            'admin_access_user_options' => $adminAccessUserOptions,
            'admin_access_summary' => $adminAccessSummary,
            'developer_tool_statuses' => $developerToolStatuses,
            'block_editor_font_library_sync_enabled' => !empty($settings['block_editor_font_library_sync_enabled']),
            'training_wheels_off' => !empty($settings['training_wheels_off']),
            'monospace_role_enabled' => !empty($settings['monospace_role_enabled']),
            'variable_fonts_enabled' => !empty($settings['variable_fonts_enabled']),
            'delete_uploaded_files_on_uninstall' => !empty($settings['delete_uploaded_files_on_uninstall']),
            'diagnostic_items' => $this->buildDiagnosticItems($assetStatus, $storage, $settings, $counts),
            'overview_metrics' => $this->buildOverviewMetrics($counts),
            'output_panels' => $this->buildOutputPanels($roles, $settings, $catalog, $appliedRoles),
            'generated_css_panel' => $this->buildGeneratedCssPanel($settings),
            'preview_panels' => $this->buildPreviewPanels(),
            'local_environment_notice' => $localEnvironmentNotice,
            'gutenberg_integration' => $gutenbergIntegration,
            'etch_integration' => $etchIntegration,
            'acss_integration' => $acssIntegration,
            'bricks_integration' => $bricksIntegration,
            'oxygen_integration' => $oxygenIntegration,
            'toasts' => $this->buildNoticeToasts(),
        ];
    }

    /**
     * @param AssetStatus $assetStatus
     * @param StorageState|null $storage
     * @param NormalizedSettings $settings
     * @param CatalogCounts $counts
     * @return list<DiagnosticItem>
     */
    public function buildDiagnosticItems(array $assetStatus, ?array $storage, array $settings, array $counts): array
    {
        $cssPath = $this->stringValue($assetStatus, 'path');
        $cssUrl = $this->stringValue($assetStatus, 'url');
        $cssExists = !empty($assetStatus['exists']);
        $cssSize = $this->intValue($assetStatus, 'size');
        $cssLastModified = $this->intValue($assetStatus, 'last_modified');

        return [
            [
                'label' => __('Generated CSS File', 'tasty-fonts'),
                'value' => $cssPath !== '' ? $cssPath : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => $cssPath !== '',
            ],
            [
                'label' => __('CSS Request URL', 'tasty-fonts'),
                'value' => $cssUrl !== '' ? $cssUrl : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => $cssUrl !== '',
            ],
            [
                'label' => __('Stylesheet Size', 'tasty-fonts'),
                'value' => $cssExists ? size_format($cssSize) : __('Not generated', 'tasty-fonts'),
                'code' => false,
            ],
            [
                'label' => __('Last Generated', 'tasty-fonts'),
                'value' => $cssExists && $cssLastModified > 0
                    ? wp_date($this->dateTimeFormat(), $cssLastModified)
                    : __('Not available', 'tasty-fonts'),
                'code' => false,
            ],
            [
                'label' => __('Fonts Directory', 'tasty-fonts'),
                'value' => is_array($storage) ? $this->stringValue($storage, 'dir', __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => is_array($storage) && !empty($storage['dir']),
            ],
            [
                'label' => __('Fonts Public URL', 'tasty-fonts'),
                'value' => is_array($storage)
                    ? $this->stringValue($storage, 'url_full', $this->stringValue($storage, 'url', __('Not available', 'tasty-fonts')))
                    : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => is_array($storage) && (!empty($storage['url_full']) || !empty($storage['url'])),
            ],
            [
                'label' => __('Google Import Folder', 'tasty-fonts'),
                'value' => is_array($storage) ? $this->stringValue($storage, 'google_dir', __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => is_array($storage) && !empty($storage['google_dir']),
            ],
            [
                'label' => __('Bunny Import Folder', 'tasty-fonts'),
                'value' => is_array($storage) ? $this->stringValue($storage, 'bunny_dir', __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => is_array($storage) && !empty($storage['bunny_dir']),
            ],
            [
                'label' => __('Local Upload Folder', 'tasty-fonts'),
                'value' => is_array($storage) ? $this->stringValue($storage, 'upload_dir', __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => is_array($storage) && !empty($storage['upload_dir']),
            ],
            [
                'label' => __('Adobe Folder', 'tasty-fonts'),
                'value' => is_array($storage) ? $this->stringValue($storage, 'adobe_dir', __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => is_array($storage) && !empty($storage['adobe_dir']),
            ],
            [
                'label' => __('Library Inventory', 'tasty-fonts'),
                'value' => sprintf(
                    __('%1$d families / %2$d files', 'tasty-fonts'),
                    $this->catalogCount($counts, 'families'),
                    $this->catalogCount($counts, 'files')
                ),
                'code' => false,
            ],
            [
                'label' => __('Generated CSS Delivery', 'tasty-fonts'),
                'value' => $this->formatCssDeliveryModeLabel($this->stringValue($settings, 'css_delivery_mode', 'file')),
                'code' => false,
            ],
            [
                'label' => __('Default Font Display', 'tasty-fonts'),
                'value' => $this->stringValue($settings, 'font_display', 'swap'),
                'code' => false,
            ],
        ];
    }

    /**
     * @return list<UpdateChannelOption>
     */
    public function buildUpdateChannelOptions(): array
    {
        return [
            ['value' => SettingsRepository::UPDATE_CHANNEL_STABLE, 'label' => $this->formatUpdateChannelLabel(SettingsRepository::UPDATE_CHANNEL_STABLE)],
            ['value' => SettingsRepository::UPDATE_CHANNEL_BETA, 'label' => $this->formatUpdateChannelLabel(SettingsRepository::UPDATE_CHANNEL_BETA)],
            ['value' => SettingsRepository::UPDATE_CHANNEL_NIGHTLY, 'label' => $this->formatUpdateChannelLabel(SettingsRepository::UPDATE_CHANNEL_NIGHTLY)],
        ];
    }

    public function formatUpdateChannelLabel(string $channel): string
    {
        return match ($channel) {
            SettingsRepository::UPDATE_CHANNEL_BETA => __('Beta', 'tasty-fonts'),
            SettingsRepository::UPDATE_CHANNEL_NIGHTLY => __('Nightly', 'tasty-fonts'),
            default => __('Stable', 'tasty-fonts'),
        };
    }

    /**
     * @return UpdateChannelStatus
     */
    private function buildUpdateChannelStatus(string $selectedChannel): array
    {
        $installedVersion = defined('TASTY_FONTS_VERSION')
            ? (string) TASTY_FONTS_VERSION
            : '';
        $latestAvailable = null;
        $state = 'unavailable';

        if ($this->updater instanceof GitHubUpdater) {
            $overview = $this->updater->getChannelOverview($selectedChannel);
            $installedVersion = $overview['installed_version'];
            $latestAvailable = $overview['latest_available'];
            $state = $overview['state'];
        }

        $stateLabel = match ($state) {
            'upgrade' => __('Upgrade Available', 'tasty-fonts'),
            'rollback' => __('Rollback Available', 'tasty-fonts'),
            'current' => __('Current', 'tasty-fonts'),
            default => __('Unavailable', 'tasty-fonts'),
        };
        $stateClass = match ($state) {
            'upgrade' => 'is-success',
            'rollback' => 'is-warning',
            'current' => 'is-role',
            default => '',
        };
        $stateCopy = match ($state) {
            'upgrade' => __('A newer package is available for the selected channel through the normal WordPress updates flow.', 'tasty-fonts'),
            'rollback' => __('The selected channel points to an older package than the one installed now. Use the reinstall action below to switch immediately.', 'tasty-fonts'),
            'current' => __('This channel is already aligned with the installed version.', 'tasty-fonts'),
            default => __('No installable package is available for the selected channel right now.', 'tasty-fonts'),
        };

        return [
            'selected_channel' => $selectedChannel,
            'selected_channel_label' => $this->formatUpdateChannelLabel($selectedChannel),
            'installed_version' => $installedVersion,
            'latest_version' => $latestAvailable !== null ? $latestAvailable['version'] : '',
            'latest_channel' => $latestAvailable !== null ? $latestAvailable['channel'] : '',
            'latest_channel_label' => $latestAvailable !== null
                ? $this->formatUpdateChannelLabel($latestAvailable['channel'])
                : '',
            'state' => $state,
            'state_label' => $stateLabel,
            'state_class' => $stateClass,
            'state_copy' => $stateCopy,
            'can_reinstall' => $state === 'rollback' && $latestAvailable !== null,
        ];
    }

    /**
     * @param CatalogCounts $counts
     * @return list<OverviewMetric>
     */
    public function buildOverviewMetrics(array $counts): array
    {
        return [
            [
                'label' => __('Families', 'tasty-fonts'),
                'value' => (string) $this->catalogCount($counts, 'families'),
            ],
            [
                'label' => __('Published', 'tasty-fonts'),
                'value' => (string) $counts['published_families'],
            ],
            [
                'label' => __('In Library Only', 'tasty-fonts'),
                'value' => (string) $counts['library_only_families'],
            ],
            [
                'label' => __('Self-hosted', 'tasty-fonts'),
                'value' => (string) $counts['local_families'],
            ],
        ];
    }

    /**
     * @param RoleSet $roles
     * @param NormalizedSettings $settings
     * @param CatalogMap $catalog
     * @param RoleSet|array{} $appliedRoles
     * @return list<OutputPanel>
     */
    public function buildOutputPanels(
        array $roles,
        array $settings,
        array $catalog = [],
        array $appliedRoles = []
    ): array {
        $minifyOutput = !empty($settings['minify_css_output']);
        $includeMonospace = !empty($settings['monospace_role_enabled']);
        $runtimeFamilies = $this->filterRuntimeVisibleFamilies($catalog);
        $snippetRoles = !empty($settings['auto_apply_roles']) && $appliedRoles !== []
            ? $appliedRoles
            : $roles;
        $usageValue = $this->cssBuilder->formatOutput(
            $this->cssBuilder->buildRoleUsageSnippet($snippetRoles, $includeMonospace, $catalog, $settings),
            $minifyOutput
        );
        $usageDisplayValue = $this->cssBuilder->buildRoleUsageSnippet($snippetRoles, $includeMonospace, $catalog, $settings, true);
        $variablesValue = $this->cssBuilder->formatOutput(
            $this->cssBuilder->buildRoleVariableDeclarationsSnippet($snippetRoles, $includeMonospace, $catalog, $settings),
            $minifyOutput
        );
        $variablesDisplayValue = $this->cssBuilder->buildRoleVariableDeclarationsSnippet($snippetRoles, $includeMonospace, $catalog, $settings, true);
        $classesValue = $this->buildClassOutputPanelContent($snippetRoles, $settings, $runtimeFamilies, $includeMonospace);
        $classesDisplayValue = $this->buildClassOutputPanelContent($snippetRoles, $settings, $runtimeFamilies, $includeMonospace, true);

        return [
            [
                'key' => 'usage',
                'label' => __('Site Snippet', 'tasty-fonts'),
                'target' => 'tasty-fonts-output-usage',
                'value' => $usageValue,
                'display_value' => $usageDisplayValue,
                'active' => true,
            ],
            [
                'key' => 'variables',
                'label' => __('CSS Variables', 'tasty-fonts'),
                'target' => 'tasty-fonts-output-vars',
                'value' => $variablesValue,
                'display_value' => $variablesDisplayValue,
                'active' => false,
            ],
            [
                'key' => 'classes',
                'label' => __('Font Classes', 'tasty-fonts'),
                'target' => 'tasty-fonts-output-classes',
                'value' => $classesValue,
                'display_value' => $classesDisplayValue,
                'active' => false,
            ],
            [
                'key' => 'stacks',
                'label' => __('Font Stacks', 'tasty-fonts'),
                'target' => 'tasty-fonts-output-stacks',
                'value' => $this->cssBuilder->buildRoleStackSnippet($snippetRoles, $includeMonospace, $settings, $catalog),
                'active' => false,
            ],
            [
                'key' => 'names',
                'label' => __('Font Names', 'tasty-fonts'),
                'target' => 'tasty-fonts-output-names',
                'value' => $this->cssBuilder->buildRoleNameSnippet($snippetRoles, $includeMonospace),
                'active' => false,
            ],
        ];
    }

    /**
     * @param NormalizedSettings $settings
     * @return GeneratedCssPanel
     */
    public function buildGeneratedCssPanel(array $settings): array
    {
        $download = $this->buildGeneratedCssDownloadData($settings);

        return [
            'key' => 'generated',
            'label' => __('Generated CSS', 'tasty-fonts'),
            'target' => 'tasty-fonts-output-generated',
            'value' => !empty($download['downloadable'])
                ? trim((string) ($download['content'] ?? ''))
                : __('Not generated while sitewide delivery is off.', 'tasty-fonts'),
            'download_url' => !empty($download['downloadable']) ? (string) ($download['url'] ?? '') : '',
            'download_filename' => (string) ($download['filename'] ?? 'tasty-fonts.css'),
            'active' => false,
        ];
    }

    /**
     * @param NormalizedSettings $settings
     * @return array<string, bool|string>
     */
    public function buildGeneratedCssDownloadData(array $settings): array
    {
        $filename = basename((string) ($this->storage->getGeneratedCssPath() ?? 'tasty-fonts.css'));

        if (empty($settings['auto_apply_roles'])) {
            return [
                'downloadable' => false,
                'filename' => $filename,
                'content' => '',
                'url' => '',
            ];
        }

        $content = trim($this->assets->getCss());

        if ($content === '') {
            return [
                'downloadable' => false,
                'filename' => $filename,
                'content' => '',
                'url' => '',
            ];
        }

        return [
            'downloadable' => true,
            'filename' => $filename,
            'content' => $content,
            'url' => $this->buildGeneratedCssDownloadUrl(),
        ];
    }

    /**
     * @return list<PreviewPanel>
     */
    public function buildPreviewPanels(): array
    {
        return [
            [
                'key' => 'editorial',
                'label' => __('Specimen', 'tasty-fonts'),
                'active' => true,
            ],
            [
                'key' => 'card',
                'label' => __('Card', 'tasty-fonts'),
                'active' => false,
            ],
            [
                'key' => 'reading',
                'label' => __('Reading', 'tasty-fonts'),
                'active' => false,
            ],
            [
                'key' => 'interface',
                'label' => __('Interface', 'tasty-fonts'),
                'active' => false,
            ],
            [
                'key' => 'code',
                'label' => __('Code', 'tasty-fonts'),
                'active' => false,
            ],
        ];
    }

    /**
     * @return list<NoticeToast>
     */
    public function buildNoticeToasts(): array
    {
        $transientKey = $this->getPendingNoticeTransientKey();
        $storedToasts = get_transient($transientKey);

        if ($storedToasts === false) {
            return [];
        }

        delete_transient($transientKey);

        if (!is_array($storedToasts)) {
            return [];
        }

        $toasts = [];

        foreach ($storedToasts as $toast) {
            if (!is_array($toast)) {
                continue;
            }

            $message = sanitize_text_field($this->stringValue($toast, 'message'));

            if ($message === '') {
                continue;
            }

            $tone = $this->stringValue($toast, 'tone', 'success');
            $role = $this->stringValue($toast, 'role', $tone === 'error' ? 'alert' : 'status');

            $toasts[] = [
                'tone' => $tone === 'error' ? 'error' : 'success',
                'message' => $message,
                'role' => $role === 'alert' ? 'alert' : 'status',
            ];
        }

        return $toasts;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function buildFontDisplayOptions(): array
    {
        return [
            ['value' => 'swap', 'label' => $this->formatFontDisplayLabel('swap')],
            ['value' => 'fallback', 'label' => $this->formatFontDisplayLabel('fallback')],
            ['value' => 'block', 'label' => $this->formatFontDisplayLabel('block')],
            ['value' => 'auto', 'label' => $this->formatFontDisplayLabel('auto')],
            ['value' => 'optional', 'label' => $this->formatFontDisplayLabel('optional')],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function buildCssDeliveryModeOptions(): array
    {
        return [
            ['value' => 'file', 'label' => __('Generated file', 'tasty-fonts')],
            ['value' => 'inline', 'label' => __('Inline in page head', 'tasty-fonts')],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function buildUnicodeRangeModeOptions(): array
    {
        return [
            ['value' => FontUtils::UNICODE_RANGE_MODE_OFF, 'label' => $this->formatUnicodeRangeModeLabel(FontUtils::UNICODE_RANGE_MODE_OFF)],
            ['value' => FontUtils::UNICODE_RANGE_MODE_PRESERVE, 'label' => $this->formatUnicodeRangeModeLabel(FontUtils::UNICODE_RANGE_MODE_PRESERVE)],
            ['value' => FontUtils::UNICODE_RANGE_MODE_LATIN_BASIC, 'label' => $this->formatUnicodeRangeModeLabel(FontUtils::UNICODE_RANGE_MODE_LATIN_BASIC)],
            ['value' => FontUtils::UNICODE_RANGE_MODE_LATIN_EXTENDED, 'label' => $this->formatUnicodeRangeModeLabel(FontUtils::UNICODE_RANGE_MODE_LATIN_EXTENDED)],
            ['value' => FontUtils::UNICODE_RANGE_MODE_CUSTOM, 'label' => $this->formatUnicodeRangeModeLabel(FontUtils::UNICODE_RANGE_MODE_CUSTOM)],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function buildFamilyFontDisplayOptions(string $globalDisplay): array
    {
        return [
            [
                'value' => 'inherit',
                'label' => sprintf(
                    __('Inherit Global (%s)', 'tasty-fonts'),
                    $this->formatFontDisplayLabel($globalDisplay)
                ),
            ],
            ['value' => 'swap', 'label' => $this->formatFontDisplayLabel('swap')],
            ['value' => 'fallback', 'label' => $this->formatFontDisplayLabel('fallback')],
            ['value' => 'block', 'label' => $this->formatFontDisplayLabel('block')],
            ['value' => 'auto', 'label' => $this->formatFontDisplayLabel('auto')],
            ['value' => 'optional', 'label' => $this->formatFontDisplayLabel('optional')],
        ];
    }

    public function formatFontDisplayLabel(string $display): string
    {
        return match ($display) {
            'auto' => __('Auto', 'tasty-fonts'),
            'block' => __('Block', 'tasty-fonts'),
            'swap' => __('Swap', 'tasty-fonts'),
            'fallback' => __('Fallback', 'tasty-fonts'),
            default => __('Optional', 'tasty-fonts'),
        };
    }

    public function formatCssDeliveryModeLabel(string $mode): string
    {
        return match ($mode) {
            'inline' => __('inline CSS', 'tasty-fonts'),
            default => __('generated file', 'tasty-fonts'),
        };
    }

    public function formatUnicodeRangeModeLabel(string $mode): string
    {
        return match (FontUtils::normalizeUnicodeRangeMode($mode)) {
            FontUtils::UNICODE_RANGE_MODE_LATIN_BASIC => __('Basic Latin', 'tasty-fonts'),
            FontUtils::UNICODE_RANGE_MODE_LATIN_EXTENDED => __('Latin Extended', 'tasty-fonts'),
            FontUtils::UNICODE_RANGE_MODE_OFF => __('Off', 'tasty-fonts'),
            FontUtils::UNICODE_RANGE_MODE_CUSTOM => __('Custom', 'tasty-fonts'),
            default => __('Keep Imported Ranges', 'tasty-fonts'),
        };
    }

    /**
     * @param RoleSet $draftRoles
     * @param RoleSet $appliedRoles
     * @param NormalizedSettings|null $settings
     * @return array<string, mixed>
     */
    public function buildRoleDeploymentContext(array $draftRoles, array $appliedRoles, bool $applyEverywhere, ?array $settings = null): array
    {
        $settings = $settings ?? $this->settings->getSettings();

        if (!$applyEverywhere) {
            return [
                'badge' => __('Saved Only', 'tasty-fonts'),
                'badge_class' => 'is-warning',
                'title' => __('Saved Roles Only', 'tasty-fonts'),
                'copy' => sprintf(
                    __('Sitewide roles are off. %s', 'tasty-fonts'),
                    $this->buildRoleDeliverySummary($draftRoles, $settings)
                ),
            ];
        }

        if ($this->roleSetsMatch($draftRoles, $appliedRoles, $settings)) {
            return [
                'badge' => __('Live', 'tasty-fonts'),
                'badge_class' => 'is-success',
                'title' => __('Live Roles Active', 'tasty-fonts'),
                'copy' => $this->buildRoleDeliverySummary($draftRoles, $settings),
            ];
        }

        return [
            'badge' => __('Pending', 'tasty-fonts'),
            'badge_class' => 'is-warning',
            'title' => __('Saved Roles Differ From Live', 'tasty-fonts'),
            'copy' => sprintf(
                __('Saved: %1$s. Live: %2$s. Publish Roles to make these changes live.', 'tasty-fonts'),
                $this->buildRoleDeliverySummary($draftRoles, $settings),
                $this->buildRoleDeliverySummary($appliedRoles, $settings)
            ),
        ];
    }

    /**
     * @param RoleSet $left
     * @param RoleSet $right
     * @param NormalizedSettings|null $settings
     * @param CatalogMap|null $catalog
     */
    public function roleSetsMatch(array $left, array $right, ?array $settings = null, ?array $catalog = null): bool
    {
        $settings = $settings ?? $this->settings->getSettings();
        $catalog = $catalog ?? $this->catalog->getCatalog();

        foreach ($this->effectiveRoleKeys($settings) as $roleKey) {
            if (trim($this->roleStringValue($left, $roleKey)) !== trim($this->roleStringValue($right, $roleKey))) {
                return false;
            }

            if ($this->resolveEffectiveRoleFallback($roleKey, $left, $catalog, $settings) !== $this->resolveEffectiveRoleFallback($roleKey, $right, $catalog, $settings)) {
                return false;
            }

            if (
                trim($this->roleStringValue($left, $roleKey . '_weight'))
                !== trim($this->roleStringValue($right, $roleKey . '_weight'))
            ) {
                return false;
            }

            if (
                !empty($settings['variable_fonts_enabled'])
                && FontUtils::normalizeVariationDefaults($left[$roleKey . '_axes'] ?? [])
                    !== FontUtils::normalizeVariationDefaults($right[$roleKey . '_axes'] ?? [])
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<ActivityLogEntry> $logs
     * @return list<string>
     */
    public function buildActivityActorOptions(array $logs): array
    {
        $actors = [];

        foreach ($logs as $entry) {
            $actor = $this->stringValue($entry, 'actor');

            if ($actor === '') {
                continue;
            }

            $actors[$actor] = $actor;
        }

        natcasesort($actors);

        return array_values($actors);
    }

    /**
     * @param list<ActivityLogEntry> $logs
     * @return list<ActivityLogEntry>
     */
    public function buildTransferLogEntries(array $logs): array
    {
        $entries = [];

        foreach ($logs as $entry) {
            if (!$this->isTransferLogEntry($entry)) {
                continue;
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @param list<ActivityLogEntry> $logs
     * @return list<ActivityLogEntry>
     */
    private function normalizeActivityLogEntries(array $logs): array
    {
        $entries = [];

        foreach ($logs as $entry) {
            $entry['time'] = $this->formatLogTimestamp($this->stringValue($entry, 'time'));
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @param ActivityLogEntry $entry
     */
    private function isTransferLogEntry(array $entry): bool
    {
        $category = strtolower($this->stringValue($entry, 'category'));

        if ($category === LogRepository::CATEGORY_TRANSFER) {
            return true;
        }

        $message = strtolower($this->stringValue($entry, 'message'));

        if ($message === '') {
            return false;
        }

        return str_contains($message, 'site transfer bundle')
            || str_starts_with($message, 'site transfer import failed')
            || str_starts_with($message, 'imported the site transfer bundle')
            || str_starts_with($message, 'exported a site transfer bundle');
    }

    /**
     * @param NormalizedSettings $settings
     * @return array<string, mixed>
     */
    public function buildLocalEnvironmentNotice(array $settings): array
    {
        if (!$this->shouldShowLocalEnvironmentNotice()) {
            return [];
        }

        $syncEnabled = !empty($settings['block_editor_font_library_sync_enabled']);

        return [
            'tone' => 'warning',
            'title' => __('Local environment detected', 'tasty-fonts'),
            'message' => $syncEnabled
                ? __('Gutenberg Font Library sync is enabled on this local site. Keep it on only when this site can complete background requests back to this site without SSL or certificate errors. If Activity shows cURL 60 or certificate verification failures, open Integrations and turn the Gutenberg sync off for local development.', 'tasty-fonts')
                : __('Gutenberg Font Library sync is off on this local site. Turn it back on in Integrations when you want imported fonts mirrored into WordPress typography controls and your local setup trusts this site\'s certificate.', 'tasty-fonts'),
            'settings_label' => __('Open Integrations', 'tasty-fonts'),
            'settings_url' => $this->buildIntegrationsUrl(),
        ];
    }

    /**
     * @param NormalizedSettings $settings
     * @return IntegrationContext
     */
    public function buildGutenbergIntegrationContext(array $settings): array
    {
        $enabled = !empty($settings['block_editor_font_library_sync_enabled']);
        $isLocal = SiteEnvironment::isLikelyLocalEnvironment(rest_url(''), SiteEnvironment::currentEnvironmentType());

        return [
            'enabled' => $enabled,
            'is_local' => $isLocal,
            'title' => __('Gutenberg Font Library', 'tasty-fonts'),
            'description' => $enabled
                ? ($isLocal
                    ? __('Sync imported families into the WordPress Font Library. Turn this off if local SSL or loopback errors block sync.', 'tasty-fonts')
                    : __('Sync imported families into the WordPress Font Library.', 'tasty-fonts'))
                : __('Enable this to sync imported families into the WordPress Font Library.', 'tasty-fonts'),
            'status_label' => $enabled ? __('On', 'tasty-fonts') : __('Off', 'tasty-fonts'),
            'status_copy' => $enabled
                ? ($isLocal
                    ? __('On. Turn this off if local SSL or loopback errors block sync.', 'tasty-fonts')
                    : __('On. Imported families sync to the WordPress Font Library.', 'tasty-fonts'))
                : __('Off. Tasty will not sync families to the WordPress Font Library.', 'tasty-fonts'),
        ];
    }

    /**
     * @return IntegrationContext
     */
    public function buildEtchIntegrationContext(): array
    {
        $available = class_exists(\Etch\Services\StylesheetService::class);

        if (function_exists('apply_filters')) {
            $available = (bool) apply_filters('tasty_fonts_etch_integration_available', $available);
        }

        return [
            'available' => $available,
            'status' => $available ? 'active' : 'inactive',
            'title' => __('Etch Canvas Bridge', 'tasty-fonts'),
            'description' => $available
                ? __('Mirrors Tasty fonts in Etch canvas previews.', 'tasty-fonts')
                : __('Turns on automatically when Etch is active.', 'tasty-fonts'),
        ];
    }

    /**
     * @param NormalizedSettings $settings
     * @return IntegrationContext
     */
    public function buildAcssIntegrationContext(array $settings): array
    {
        $enabled = ($settings['acss_font_role_sync_enabled'] ?? null) === true;
        $applied = !empty($settings['acss_font_role_sync_applied']);
        $sitewideRolesEnabled = !empty($settings['auto_apply_roles']);
        $state = $this->acssIntegration->readState($sitewideRolesEnabled, $enabled, $applied);

        $status = is_string($state['status'] ?? null) ? $state['status'] : 'disabled';

        return array_merge(
            $state,
            [
                'title' => __('Automatic.css', 'tasty-fonts'),
                'description' => match ($status) {
                    'synced' => __('Sync Automatic.css font settings with Tasty role variables.', 'tasty-fonts'),
                    'ready' => __('Save settings to sync Automatic.css font settings with Tasty role variables.', 'tasty-fonts'),
                    'out_of_sync' => __('Re-save to sync Automatic.css font settings with Tasty role variables again.', 'tasty-fonts'),
                    'waiting_for_sitewide_roles' => __('Enable sitewide role delivery to sync Automatic.css font settings with Tasty role variables.', 'tasty-fonts'),
                    'unavailable' => __('Sync Automatic.css font settings with Tasty role variables when Automatic.css is active.', 'tasty-fonts'),
                    default => __('Enable this to sync Automatic.css font settings with Tasty role variables.', 'tasty-fonts'),
                },
                'status_label' => $this->buildAcssIntegrationStatusLabel($status),
                'status_copy' => $this->buildAcssIntegrationStatusCopy($status, $state),
            ]
        );
    }

    /**
     * @param NormalizedSettings $settings
     * @return IntegrationContext
     */
    public function buildBricksIntegrationContext(array $settings): array
    {
        $state = $this->bricksIntegration->readState($settings);

        $status = $state['status'];

        return array_merge(
            $state,
            [
                'title' => __('Bricks Builder', 'tasty-fonts'),
                'description' => match ($status) {
                    'active' => __('Manage Bricks typography with Tasty. Selectors and previews stay automatic.', 'tasty-fonts'),
                    'unavailable' => __('Manage Bricks typography with Tasty when Bricks is active.', 'tasty-fonts'),
                    default => __('Enable this to let Tasty manage Bricks typography.', 'tasty-fonts'),
                },
                'status_label' => $this->buildBuilderIntegrationStatusLabel($status),
                'status_copy' => $this->buildBricksIntegrationStatusCopy($status),
                'feature_descriptions' => [
                    'selectors' => __('Show published Tasty families in Bricks font controls.', 'tasty-fonts'),
                    'builder_preview' => __('Load active Tasty fonts in Bricks previews.', 'tasty-fonts'),
                    'theme_styles' => __('Sync Tasty role fonts to one Theme Style or all Theme Styles.', 'tasty-fonts'),
                    'google_fonts' => __('Hide Bricks’ built-in Google Fonts so only Tasty fonts appear.', 'tasty-fonts'),
                ],
            ]
        );
    }

    /**
     * @param NormalizedSettings $settings
     * @return IntegrationContext
     */
    public function buildOxygenIntegrationContext(array $settings): array
    {
        $enabled = array_key_exists('oxygen_integration_enabled', $settings)
            ? $this->nullableBoolValue($settings['oxygen_integration_enabled'])
            : null;
        $state = $this->oxygenIntegration->readState($enabled);

        $status = $state['status'];

        return array_merge(
            $state,
            [
                'title' => __('Oxygen Builder', 'tasty-fonts'),
                'description' => match ($status) {
                    'active' => __('Show published Tasty fonts in Oxygen and sync families to Gutenberg.', 'tasty-fonts'),
                    'unavailable' => __('Show published Tasty fonts in Oxygen when Oxygen is active.', 'tasty-fonts'),
                    default => __('Enable this to show published Tasty fonts in Oxygen and sync families to Gutenberg.', 'tasty-fonts'),
                },
                'status_label' => $this->buildBuilderIntegrationStatusLabel($status),
                'status_copy' => $this->buildOxygenIntegrationStatusCopy($status),
            ]
        );
    }

    /**
     * @param NormalizedSettings $settings
     * @return array<string, mixed>
     */
    public function buildPreviewContext(array $settings): array
    {
        $previewText = isset($_GET['preview_text'])
            ? wp_strip_all_tags(sanitize_text_field(wp_unslash(FontUtils::scalarStringValue($_GET['preview_text']))))
            : $this->stringValue($settings, 'preview_sentence');
        $previewSize = isset($_GET['preview_size']) ? absint(FontUtils::scalarStringValue($_GET['preview_size'])) : 32;
        $previewText = $previewText !== ''
            ? $previewText
            : __('The quick brown fox jumps over the lazy dog. 1234567890', 'tasty-fonts');

        return [
            'preview_text' => $previewText,
            'preview_size' => $previewSize > 0 ? $previewSize : 32,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAdobeAccessContext(): array
    {
        $projectId = $this->adobe->getProjectId();
        $projectSaved = $projectId !== '';
        $projectEnabled = $this->adobe->isEnabled();
        $projectStatus = $this->adobe->getProjectStatus();
        $projectState = $projectStatus['state'];
        $projectStatusMessage = $projectStatus['message'];
        $detectedFamilies = $projectSaved && in_array($projectState, ['valid', 'unknown'], true)
            ? $this->adobe->getProjectFamilies($projectId)
            : [];

        return [
            'adobe_project_state' => $projectState,
            'adobe_project_enabled' => $projectEnabled,
            'adobe_project_saved' => $projectSaved,
            'adobe_access_expanded' => !$projectSaved || in_array($projectState, ['empty', 'invalid', 'unknown'], true),
            'adobe_project_id' => $projectId,
            'adobe_status_label' => $this->buildAdobeStatusLabel($projectState, $projectEnabled),
            'adobe_status_class' => $this->buildAdobeStatusClass($projectState, $projectEnabled),
            'adobe_access_copy' => $this->buildAdobeAccessCopy($projectState, $projectEnabled, $projectStatusMessage),
            'adobe_project_link' => 'https://fonts.adobe.com/',
            'adobe_detected_families' => $detectedFamilies,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildGoogleAccessContext(): array
    {
        $googleApiEnabled = $this->googleClient->canSearch();
        $googleApiSaved = $this->googleClient->hasApiKey();
        $googleApiStatus = $this->googleClient->getApiKeyStatus();
        $googleApiState = $this->stringValue($googleApiStatus, 'state', 'empty');
        $googleApiStatusMessage = $this->stringValue($googleApiStatus, 'message');

        return [
            'google_api_state' => $googleApiState,
            'google_api_enabled' => $googleApiEnabled,
            'google_api_saved' => $googleApiSaved,
            'google_access_expanded' => !$googleApiEnabled,
            'google_status_label' => $this->buildGoogleStatusLabel($googleApiState),
            'google_status_class' => $this->buildGoogleStatusClass($googleApiState),
            'google_access_copy' => $this->buildGoogleAccessCopy($googleApiState, $googleApiStatusMessage),
            'google_search_disabled_copy' => $this->buildGoogleSearchDisabledCopy($googleApiState),
        ];
    }

    /**
     * @param CatalogMap $catalog
     * @return list<string>
     */
    public function buildSelectableFamilyNames(array $catalog): array
    {
        $families = array_keys($catalog);
        $storedRoles = $this->settings->getRoles([]);

        foreach ($this->effectiveRoleKeys() as $roleKey) {
            $familyName = trim($this->roleStringValue($storedRoles, $roleKey));

            if ($familyName !== '') {
                $families[] = $familyName;
            }
        }

        $families = array_values(array_unique($families));
        natcasesort($families);

        return array_values($families);
    }

    /**
     * @param CatalogMap $catalog
     * @param list<string>|null $availableFamilies
     * @return list<array{value: string, label: string}>
     */
    public function buildSelectableFamilyOptions(array $catalog, ?array $availableFamilies = null): array
    {
        $families = is_array($availableFamilies) ? $availableFamilies : $this->buildSelectableFamilyNames($catalog);
        $options = [];

        foreach ($families as $familyName) {
            $name = trim((string) $familyName);

            if ($name === '') {
                continue;
            }

            $catalogEntry = is_array($catalog[$name] ?? null) ? $catalog[$name] : null;
            $descriptor = $catalogEntry !== null ? FontTypeHelper::describeEntry($catalogEntry) : null;

            $options[] = [
                'value' => $name,
                'label' => FontTypeHelper::buildSelectorOptionLabel($name, $catalogEntry),
                'type' => $descriptor !== null ? $descriptor['type'] : '',
            ];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string, count: int, disabled: bool, meta: string}>
     */
    private function buildAdminAccessRoleOptions(): array
    {
        $roles = wp_roles()->roles;
        $userCountsByRole = $this->countUsersByRole();
        $options = [];

        foreach ($roles as $roleSlug => $roleConfig) {
            $value = sanitize_key((string) $roleSlug);

            if ($value === '') {
                continue;
            }

            $label = trim(FontUtils::scalarStringValue($roleConfig['name'] ?? ''));
            $count = (int) ($userCountsByRole[$value] ?? 0);
            $isImplicitAdminRole = $value === AdminAccessService::IMPLICIT_ROLE;

            $options[] = [
                'value' => $value,
                'label' => $label !== '' ? $label : ucfirst(str_replace('_', ' ', $value)),
                'count' => $count,
                'disabled' => $isImplicitAdminRole,
                'meta' => $isImplicitAdminRole
                    ? sprintf(_n('%d user · always allowed', '%d users · always allowed', $count, 'tasty-fonts'), $count)
                    : '',
            ];
        }

        usort(
            $options,
            static fn (array $left, array $right): int => strnatcasecmp($left['label'], $right['label'])
        );

        return $options;
    }

    /**
     * @return list<array{value: string, label: string, meta: string, disabled: bool, search_text: string}>
     */
    private function buildAdminAccessUserOptions(): array
    {
        $options = [];
        $roleLabels = $this->roleLabelsBySlug();
        $users = get_users();

        foreach ($users as $user) {
            if (!is_object($user)) {
                continue;
            }

            $userId = absint(FontUtils::scalarStringValue($user->ID ?? 0));

            if ($userId <= 0) {
                continue;
            }

            $userLogin = FontUtils::scalarStringValue($user->user_login ?? '');
            $userRoles = $this->sanitizeKeyList($user->roles ?? []);
            $isImplicitAdmin = in_array(AdminAccessService::IMPLICIT_ROLE, $userRoles, true);
            $userRoleLabels = [];

            foreach ($userRoles as $roleSlug) {
                $userRoleLabels[] = (string) ($roleLabels[$roleSlug] ?? ucfirst(str_replace('_', ' ', $roleSlug)));
            }

            $label = $this->buildAdminAccessUserLabel($user);

            $options[] = [
                'value' => (string) $userId,
                'label' => $label,
                'meta' => $isImplicitAdmin
                    ? __('Administrator · already has access', 'tasty-fonts')
                    : ($userRoleLabels === []
                        ? __('No WordPress role assigned', 'tasty-fonts')
                        : implode(', ', $userRoleLabels)),
                'disabled' => $isImplicitAdmin,
                'search_text' => strtolower(trim(implode(' ', array_filter([$label, $userLogin, implode(' ', $userRoleLabels)])))),
            ];
        }

        usort(
            $options,
            static fn (array $left, array $right): int => strnatcasecmp($left['label'], $right['label'])
        );

        return $options;
    }

    /**
     * @return list<string>
     */
    private function buildAdminAccessImplicitAdminLabels(): array
    {
        $labels = [];
        $users = get_users();

        foreach ($users as $user) {
            if (!is_object($user)) {
                continue;
            }

            $userRoles = $this->sanitizeKeyList($user->roles ?? []);

            if (!in_array(AdminAccessService::IMPLICIT_ROLE, $userRoles, true)) {
                continue;
            }

            $label = $this->buildAdminAccessUserLabel($user);

            if ($label === '') {
                continue;
            }

            $labels[$label] = $label;
        }

        natcasesort($labels);

        return array_values($labels);
    }

    private function buildAdminAccessUserLabel(object $user): string
    {
        $userId = absint(FontUtils::scalarStringValue($user->ID ?? 0));
        $displayName = trim(FontUtils::scalarStringValue($user->display_name ?? ''));
        $userLogin = trim(FontUtils::scalarStringValue($user->user_login ?? ''));
        $label = $displayName;

        if ($userLogin !== '') {
            $label = $displayName !== ''
                ? sprintf('%1$s (%2$s)', $displayName, $userLogin)
                : $userLogin;
        }

        if ($label === '' && $userId > 0) {
            $label = sprintf(__('User #%d', 'tasty-fonts'), $userId);
        }

        return $label;
    }

    /**
     * @param list<string> $roleSlugs
     * @param list<int> $userIds
     * @param list<array{value: string, label: string, count: int, disabled: bool, meta: string}> $roleOptions
     * @param list<string> $implicitAdminLabels
     * @return array<string, mixed>
     */
    private function buildAdminAccessSummary(array $roleSlugs, array $userIds, array $roleOptions, array $implicitAdminLabels): array
    {
        $selectedRoleSlugs = array_values(array_filter(array_map('sanitize_key', $roleSlugs)));
        $selectedUserIds = array_values(array_filter(array_map('absint', $userIds)));
        $roleImpact = 0;

        foreach ($roleOptions as $option) {
            $roleSlug = sanitize_key($option['value']);

            if ($roleSlug === '' || !in_array($roleSlug, $selectedRoleSlugs, true)) {
                continue;
            }

            $roleImpact += max(0, $option['count']);
        }

        return [
            'enabled' => !empty($this->settings->getSettings()['admin_access_custom_enabled']),
            'role_count' => count($selectedRoleSlugs),
            'role_user_impact' => $roleImpact,
            'user_count' => count($selectedUserIds),
            'implicit_admin_count' => count($implicitAdminLabels),
            'implicit_admin_labels' => $implicitAdminLabels,
        ];
    }

    /**
     * @param mixed $value
     * @return AdminAccessRoleSlugList
     */
    private function normalizeAdminAccessRoleSlugs(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(
            array_filter(
                array_unique(
                    array_map(
                        static fn (mixed $roleSlug): string => sanitize_key(is_scalar($roleSlug) ? (string) $roleSlug : ''),
                        $value
                    )
                ),
                static fn (string $roleSlug): bool => $roleSlug !== ''
            )
        );
    }

    /**
     * @param mixed $value
     * @return AdminAccessUserIdList
     */
    private function normalizeAdminAccessUserIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(
            array_filter(
                array_unique(
                    array_map(
                        static fn (mixed $userId): int => absint(FontUtils::scalarStringValue($userId)),
                        $value
                    )
                ),
                static fn (int $userId): bool => $userId > 0
            )
        );
    }

    /**
     * @param list<ActivityLogEntry> $logs
     * @param AssetStatus $assetStatus
     * @param CatalogCounts $counts
     * @return array<string, mixed>
     */
    private function buildDeveloperToolStatuses(array $logs, array $assetStatus, array $counts): array
    {
        $libraryCount = max(0, $this->catalogCount($counts, 'families'));
        $cssReady = !empty($assetStatus['exists']) && !empty($assetStatus['url']);
        $statuses = [
            'clear_plugin_caches' => [
                'summary' => $cssReady
                    ? ''
                    : __('Generated CSS looks stale or unavailable.', 'tasty-fonts'),
                'last_run' => '',
            ],
            'regenerate_css' => [
                'summary' => $cssReady
                    ? ''
                    : __('Generated CSS needs a rebuild.', 'tasty-fonts'),
                'last_run' => '',
            ],
            'reset_integration_detection_state' => [
                'summary' => '',
                'last_run' => '',
            ],
            'reset_suppressed_notices' => [
                'summary' => '',
                'last_run' => '',
            ],
            'reset_plugin_settings' => [
                'summary' => '',
                'last_run' => '',
            ],
            'wipe_managed_font_library' => [
                'summary' => sprintf(
                    /* translators: %d: number of managed font families */
                    _n(
                        '%d managed font family in the library.',
                        '%d managed font families in the library.',
                        $libraryCount,
                        'tasty-fonts'
                    ),
                    $libraryCount
                ),
                'last_run' => '',
            ],
        ];
        $messageMap = [
            'clear_plugin_caches' => __('Plugin caches cleared and generated assets refreshed.', 'tasty-fonts'),
            'regenerate_css' => __('Generated CSS regenerated.', 'tasty-fonts'),
            'reset_integration_detection_state' => __('Integration detection state reset.', 'tasty-fonts'),
            'reset_suppressed_notices' => __('Suppressed notices reset. Hidden reminders can appear again.', 'tasty-fonts'),
            'reset_plugin_settings' => __('Plugin settings reset to defaults. Font library preserved.', 'tasty-fonts'),
            'wipe_managed_font_library' => __('Managed font library wiped. Storage reset to an empty scaffold.', 'tasty-fonts'),
        ];

        foreach ($messageMap as $slug => $message) {
            $statuses[$slug]['last_run'] = $this->buildDeveloperToolLastRunCopy($logs, $message);
        }

        return $statuses;
    }

    /**
     * @param array<string, mixed> $counts
     */
    private function catalogCount(array $counts, string $key): int
    {
        return isset($counts[$key]) ? max(0, FontUtils::scalarIntValue($counts[$key])) : 0;
    }

    /**
     * @param list<ActivityLogEntry> $logs
     */
    private function buildDeveloperToolLastRunCopy(array $logs, string $message): string
    {
        foreach ($logs as $entry) {
            if ($this->stringValue($entry, 'message') !== $message) {
                continue;
            }

            $timestamp = $this->stringValue($entry, 'time');
            $actor = $this->stringValue($entry, 'actor', __('System', 'tasty-fonts'));
            $formattedTime = $this->formatLogTimestamp($timestamp);

            if ($formattedTime === '') {
                return $actor !== ''
                    ? sprintf(__('Last run by %s.', 'tasty-fonts'), $actor)
                    : __('Previously run from this site.', 'tasty-fonts');
            }

            return sprintf(
                __('Last run %1$s by %2$s.', 'tasty-fonts'),
                $formattedTime,
                $actor !== '' ? $actor : __('System', 'tasty-fonts')
            );
        }

        return __('Not run recently.', 'tasty-fonts');
    }

    private function formatLogTimestamp(string $timestamp): string
    {
        $timestamp = trim($timestamp);

        if ($timestamp === '') {
            return '';
        }

        $unix = strtotime($timestamp . ' UTC');

        if ($unix === false) {
            $unix = strtotime($timestamp);
        }

        if ($unix === false) {
            return '';
        }

        $format = trim($this->dateTimeFormat());

        if ($format === '') {
            $format = 'M j, Y g:i a';
        }

        $formatted = wp_date($format, $unix);

        return is_string($formatted) ? $formatted : '';
    }

    /**
     * @return array<string, int>
     */
    private function countUsersByRole(): array
    {
        $counts = [];
        $users = get_users();

        foreach ($users as $user) {
            if (!is_object($user) || !is_array($user->roles ?? null)) {
                continue;
            }

            foreach ($user->roles as $roleSlug) {
                $normalizedSlug = sanitize_key(FontUtils::scalarStringValue($roleSlug));

                if ($normalizedSlug === '') {
                    continue;
                }

                $counts[$normalizedSlug] = (int) ($counts[$normalizedSlug] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, string>
     */
    private function roleLabelsBySlug(): array
    {
        $roles = wp_roles()->roles;
        $labels = [];

        foreach ($roles as $roleSlug => $roleConfig) {
            $normalizedSlug = sanitize_key((string) $roleSlug);

            if ($normalizedSlug === '') {
                continue;
            }

            $label = trim(FontUtils::scalarStringValue($roleConfig['name'] ?? ''));
            $labels[$normalizedSlug] = $label !== '' ? $label : ucfirst(str_replace('_', ' ', $normalizedSlug));
        }

        return $labels;
    }

    /**
     * @param RoleSet $roles
     * @param NormalizedSettings|null $settings
     */
    public function buildRoleDeliverySummary(array $roles, ?array $settings = null): string
    {
        $parts = [];
        $settings = $settings ?? $this->settings->getSettings();

        foreach ($this->effectiveRoleKeys($settings) as $roleKey) {
            $familyName = trim($this->roleStringValue($roles, $roleKey));
            $fallback = $this->resolveEffectiveRoleFallback($roleKey, $roles, $this->catalog->getCatalog(), $settings);

            if ($familyName === '' && $roleKey !== 'monospace') {
                continue;
            }

            if ($familyName === '') {
                $parts[] = sprintf(
                    __('%1$s: fallback only (%2$s)', 'tasty-fonts'),
                    $this->roleLabel($roleKey),
                    $fallback
                );
                continue;
            }

            $parts[] = sprintf(
                __('%1$s: %2$s', 'tasty-fonts'),
                $this->roleLabel($roleKey),
                $this->describeFamilyDelivery($familyName)
            );
        }

        return $parts === [] ? __('No roles selected yet.', 'tasty-fonts') : implode('; ', $parts) . '.';
    }

    /**
     * @param RoleSet $roles
     * @param NormalizedSettings|null $settings
     */
    public function buildRoleTextSummary(array $roles, ?array $settings = null): string
    {
        $settings = $settings ?? $this->settings->getSettings();
        $parts = [];

        foreach ($this->effectiveRoleKeys($settings) as $roleKey) {
            $familyName = trim($this->roleStringValue($roles, $roleKey));
            $fallback = $this->resolveEffectiveRoleFallback($roleKey, $roles, $this->catalog->getCatalog(), $settings);

            $parts[] = sprintf(
                __('%1$s: %2$s', 'tasty-fonts'),
                $this->roleLabel($roleKey),
                $familyName !== ''
                    ? $familyName
                    : sprintf(__('fallback only (%s)', 'tasty-fonts'), $fallback)
            );
        }

        return implode('; ', $parts);
    }

    /**
     * @param RoleSet $roles
     * @param NormalizedSettings $settings
     * @param CatalogMap $runtimeFamilies
     */
    private function buildClassOutputPanelContent(
        array $roles,
        array $settings,
        array $runtimeFamilies,
        bool $includeMonospace,
        bool $includeComments = false
    ): string {
        if (empty($settings['class_output_enabled'])) {
            return __('Class output is off. Turn on Classes in Output Settings to generate utility classes.', 'tasty-fonts');
        }

        $content = $includeComments
            ? $this->cssBuilder->buildCommentedClassOutputSnippet($roles, $includeMonospace, $runtimeFamilies, $settings)
            : $this->cssBuilder->formatOutput(
                $this->cssBuilder->buildClassOutputSnippet($roles, $includeMonospace, $runtimeFamilies, $settings),
                !empty($settings['minify_css_output'])
            );

        if ($content !== '') {
            return $content;
        }

        return __('No font classes are available for the current class output settings.', 'tasty-fonts');
    }

    /**
     * @param CatalogMap $catalog
     * @return CatalogMap
     */
    private function filterRuntimeVisibleFamilies(array $catalog): array
    {
        $families = [];

        foreach ($catalog as $key => $family) {
            if ($this->stringValue($family, 'publish_state', 'published') === 'library_only') {
                continue;
            }

            $families[$key] = $family;
        }

        return $families;
    }

    private function buildGoogleStatusLabel(string $googleApiState): string
    {
        return match ($googleApiState) {
            'valid' => __('Valid Key', 'tasty-fonts'),
            'invalid' => __('Invalid Key', 'tasty-fonts'),
            'unknown' => __('Needs Check', 'tasty-fonts'),
            default => __('API Key Needed', 'tasty-fonts'),
        };
    }

    private function buildAcssIntegrationStatusLabel(string $status): string
    {
        return match ($status) {
            'synced' => __('Synced', 'tasty-fonts'),
            'ready' => __('Ready to Apply', 'tasty-fonts'),
            'out_of_sync' => __('Needs Reapply', 'tasty-fonts'),
            'waiting_for_sitewide_roles' => __('Waiting for Sitewide Roles', 'tasty-fonts'),
            'unavailable' => __('Not Active', 'tasty-fonts'),
            default => __('Off', 'tasty-fonts'),
        };
    }

    /**
     * @param array<string, mixed> $state
     */
    private function buildAcssIntegrationStatusCopy(string $status, array $state): string
    {
        $current = is_array($state['current'] ?? null) ? $state['current'] : ['heading' => '', 'body' => ''];

        return match ($status) {
            'synced' => __('Automatic.css is using Tasty’s managed font and weight variables.', 'tasty-fonts'),
            'ready' => __('Automatic.css is active. Save settings to apply the mapping.', 'tasty-fonts'),
            'out_of_sync' => __('Automatic.css is active, but its font settings do not match Tasty’s mapping.', 'tasty-fonts'),
            'waiting_for_sitewide_roles' => __('Enable sitewide role delivery to apply Automatic.css sync.', 'tasty-fonts'),
            'unavailable' => __('Automatic.css is not active on this site yet.', 'tasty-fonts'),
            default => sprintf(
                __('Current values: heading `%1$s`, body `%2$s`.', 'tasty-fonts'),
                $this->stringValue($current, 'heading') !== '' ? $this->stringValue($current, 'heading') : __('empty', 'tasty-fonts'),
                $this->stringValue($current, 'body') !== '' ? $this->stringValue($current, 'body') : __('empty', 'tasty-fonts')
            ),
        };
    }

    private function buildBuilderIntegrationStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => __('On', 'tasty-fonts'),
            'unavailable' => __('Not Active', 'tasty-fonts'),
            default => __('Off', 'tasty-fonts'),
        };
    }

    private function buildBricksIntegrationStatusCopy(string $status): string
    {
        return match ($status) {
            'active' => __('Bricks is active. Published fonts already appear in selectors and previews.', 'tasty-fonts'),
            'unavailable' => __('Bricks is not active on this site yet.', 'tasty-fonts'),
            default => __('Bricks integration is off.', 'tasty-fonts'),
        };
    }

    private function buildOxygenIntegrationStatusCopy(string $status): string
    {
        return match ($status) {
            'active' => __('Oxygen is active. Published fonts are available in Oxygen and mirrored to Gutenberg.', 'tasty-fonts'),
            'unavailable' => __('Oxygen is not active on this site yet.', 'tasty-fonts'),
            default => __('Oxygen integration is off.', 'tasty-fonts'),
        };
    }

    private function buildGoogleStatusClass(string $googleApiState): string
    {
        return match ($googleApiState) {
            'valid' => 'is-success',
            'invalid' => 'is-danger',
            'unknown' => 'is-warning',
            default => '',
        };
    }

    private function buildGoogleAccessCopy(string $googleApiState, string $googleApiStatusMessage): string
    {
        if ($googleApiStatusMessage !== '') {
            return $googleApiStatusMessage;
        }

        return match ($googleApiState) {
            'valid' => __('Google search is ready. Open key settings only when you want to replace or remove the saved key.', 'tasty-fonts'),
            'invalid' => __('The saved Google Fonts API key was rejected. Update it to re-enable live search.', 'tasty-fonts'),
            'unknown' => __('This saved key has not been verified yet. Save it again to validate before using live search.', 'tasty-fonts'),
            default => __('Enable live family search with a Google Fonts Developer API key. The key is only needed for search, not for Google CDN delivery itself.', 'tasty-fonts'),
        };
    }

    private function buildGoogleSearchDisabledCopy(string $googleApiState): string
    {
        return match ($googleApiState) {
            'invalid' => __('Search is disabled because the saved API key is invalid. Update or remove it to continue.', 'tasty-fonts'),
            'unknown' => __('Search is disabled until the saved API key has been validated.', 'tasty-fonts'),
            default => __('Search is disabled until you save a Google Fonts API key.', 'tasty-fonts'),
        };
    }

    private function buildAdobeStatusLabel(string $projectState, bool $projectEnabled): string
    {
        if (!$projectEnabled && $projectState === 'valid') {
            return __('Saved Project', 'tasty-fonts');
        }

        return match ($projectState) {
            'valid' => __('Project Ready', 'tasty-fonts'),
            'invalid' => __('Invalid Project', 'tasty-fonts'),
            'unknown' => __('Needs Check', 'tasty-fonts'),
            default => __('Project ID Needed', 'tasty-fonts'),
        };
    }

    private function buildAdobeStatusClass(string $projectState, bool $projectEnabled): string
    {
        if (!$projectEnabled && $projectState === 'valid') {
            return 'is-warning';
        }

        return match ($projectState) {
            'valid' => 'is-success',
            'invalid' => 'is-danger',
            'unknown' => 'is-warning',
            default => '',
        };
    }

    private function buildAdobeAccessCopy(string $projectState, bool $projectEnabled, string $projectStatusMessage): string
    {
        if ($projectStatusMessage !== '') {
            if (!$projectEnabled && $projectState === 'valid') {
                return __('The Adobe project is saved and validated but currently disabled. Enable it to load Adobe-hosted fonts on the site and in editors.', 'tasty-fonts');
            }

            return $projectStatusMessage;
        }

        return match ($projectState) {
            'valid' => $projectEnabled
                ? __('This Adobe Fonts project will load remotely from use.typekit.net. Manage families and domains in Adobe Fonts, then resync here when needed.', 'tasty-fonts')
                : __('The Adobe project is saved, but remote loading is disabled until you enable it below.', 'tasty-fonts'),
            'invalid' => __('Adobe rejected the saved web project ID. Check the project ID and allowed domains in Adobe Fonts before saving again.', 'tasty-fonts'),
            'unknown' => __('This Adobe project has not been validated yet. Save or resync it to fetch the project stylesheet and detected families.', 'tasty-fonts'),
            default => __('Connect an existing Adobe Fonts web project to make its hosted families available for previews, roles, Gutenberg, and the Etch canvas.', 'tasty-fonts'),
        };
    }

    private function shouldShowLocalEnvironmentNotice(): bool
    {
        if (!SiteEnvironment::isLikelyLocalEnvironment(rest_url(''), SiteEnvironment::currentEnvironmentType())) {
            return false;
        }

        $preference = $this->getLocalEnvironmentNoticePreference();

        if (!empty($preference['dismissed_forever'])) {
            return false;
        }

        return FontUtils::scalarIntValue($preference['hidden_until'] ?? 0) <= time();
    }

    /**
     * @return array<string, mixed>
     */
    private function getLocalEnvironmentNoticePreference(): array
    {
        $preferences = get_option(AdminController::LOCAL_ENV_NOTICE_OPTION, []);
        $userId = max(0, (int) get_current_user_id());

        if ($userId <= 0 || !is_array($preferences)) {
            return [
                'hidden_until' => 0,
                'dismissed_forever' => false,
            ];
        }

        $preference = is_array($preferences[$userId] ?? null) ? $preferences[$userId] : [];

        return [
            'hidden_until' => max(0, FontUtils::scalarIntValue($preference['hidden_until'] ?? 0)),
            'dismissed_forever' => !empty($preference['dismissed_forever']),
        ];
    }

    private function describeFamilyDelivery(string $familyName): string
    {
        $catalog = $this->catalog->getCatalog();
        $family = $catalog[$familyName] ?? null;

        if (!is_array($family)) {
            return $familyName;
        }

        $delivery = is_array($family['active_delivery'] ?? null) ? $family['active_delivery'] : [];
        $label = strtolower($this->stringValue($delivery, 'label'));

        if ($label === '') {
            return $familyName;
        }

        return sprintf(__('%1$s via %2$s', 'tasty-fonts'), $familyName, $label);
    }

    /**
     * @param RoleSet $roles
     * @param CatalogMap $catalog
     * @param NormalizedSettings $settings
     */
    private function resolveEffectiveRoleFallback(string $roleKey, array $roles, array $catalog, array $settings): string
    {
        $default = $this->defaultRoleFallback($roleKey);
        $familyName = trim($this->roleStringValue($roles, $roleKey));

        if ($familyName !== '') {
                $familyFallbacks = FontUtils::normalizeStringMap($settings['family_fallbacks'] ?? []);

                if (array_key_exists($familyName, $familyFallbacks)) {
                    $configuredFallback = trim($familyFallbacks[$familyName]);

                    if ($configuredFallback !== '') {
                        return FontUtils::sanitizeFallback($configuredFallback);
                    }
                }

            $family = $this->findCatalogFamilyByName($familyName, $catalog);

            if (is_array($family)) {
                return FontUtils::defaultFallbackForCategory($this->resolveFamilyCategory($family));
            }
        }

        $fallback = trim($this->roleStringValue($roles, $roleKey . '_fallback'));

        return $fallback !== '' ? FontUtils::sanitizeFallback($fallback) : $default;
    }

    /**
     * @param CatalogMap $catalog
     * @return CatalogFamily|null
     */
    private function findCatalogFamilyByName(string $familyName, array $catalog): ?array
    {
        if (is_array($catalog[$familyName] ?? null)) {
            return $catalog[$familyName];
        }

        foreach ($catalog as $family) {
            if ($this->stringValue($family, 'family') === $familyName) {
                return $family;
            }
        }

        return null;
    }

    /**
     * @param CatalogFamily $family
     */
    private function resolveFamilyCategory(array $family): string
    {
        $category = $this->stringValue($family, 'font_category');

        if ($category === '' && is_array($family['active_delivery'] ?? null) && is_array($family['active_delivery']['meta'] ?? null)) {
            $category = $this->stringValue($family['active_delivery']['meta'], 'category');
        }

        return $category;
    }

    /**
     * @param NormalizedSettings|null $settings
     * @return list<string>
     */
    private function effectiveRoleKeys(?array $settings = null): array
    {
        $settings = $settings ?? $this->settings->getSettings();
        $keys = self::BASE_ROLE_KEYS;

        if (!empty($settings['monospace_role_enabled'])) {
            $keys[] = 'monospace';
        }

        return $keys;
    }

    private function defaultRoleFallback(string $roleKey): string
    {
        return $roleKey === 'monospace' ? 'monospace' : 'sans-serif';
    }

    /**
     * @param array<int|string, mixed> $values
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
     * @param array<int|string, mixed> $values
     */
    private function intValue(array $values, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        return FontUtils::scalarIntValue($values[$key], $default);
    }

    /**
     * @return list<string>
     */
    private function sanitizeKeyList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            $key = sanitize_key(FontUtils::scalarStringValue($item));

            if ($key === '') {
                continue;
            }

            $normalized[] = $key;
        }

        return $normalized;
    }

    private function nullableBoolValue(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(FontUtils::scalarStringValue($value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => null,
        };
    }

    private function dateTimeFormat(): string
    {
        return FontUtils::scalarStringValue(get_option('date_format'))
            . ' '
            . FontUtils::scalarStringValue(get_option('time_format'));
    }

    /**
     * @param array<int|string, mixed> $roles
     */
    private function roleStringValue(array $roles, string $key): string
    {
        $value = $roles[$key] ?? '';

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return '';
    }

    private function roleLabel(string $roleKey): string
    {
        return match ($roleKey) {
            'heading' => __('Heading', 'tasty-fonts'),
            'body' => __('Body', 'tasty-fonts'),
            'monospace' => __('Monospace', 'tasty-fonts'),
            default => ucfirst($roleKey),
        };
    }

    private function getPendingNoticeTransientKey(): string
    {
        return TransientKey::forSite(AdminController::NOTICE_TRANSIENT_PREFIX . max(0, (int) get_current_user_id()));
    }

    private function buildGeneratedCssDownloadUrl(): string
    {
        return add_query_arg(
            [
                'page' => AdminController::MENU_SLUG,
                'tf_page' => AdminController::PAGE_DIAGNOSTICS,
                self::DOWNLOAD_ACTION => '1',
                '_wpnonce' => wp_create_nonce(self::DOWNLOAD_ACTION),
            ],
            admin_url('admin.php')
        );
    }

    private function buildIntegrationsUrl(): string
    {
        return add_query_arg(
            [
                'page' => AdminController::MENU_SLUG,
                'tf_page' => AdminController::PAGE_SETTINGS,
                'tf_studio' => 'integrations',
            ],
            admin_url('admin.php')
        );
    }
}
