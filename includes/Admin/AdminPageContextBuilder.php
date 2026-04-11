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

    public function build(): array
    {
        $storage = $this->storage->get();
        $settings = $this->settings->getSettings();
        $catalog = $this->catalog->getCatalog();
        $logs = $this->log->all();
        $counts = $this->catalog->getCounts();
        $assetStatus = $this->assets->getStatus();
        $familyFallbacks = is_array($settings['family_fallbacks'] ?? null) ? $settings['family_fallbacks'] : [];
        $familyFontDisplays = is_array($settings['family_font_displays'] ?? null) ? $settings['family_font_displays'] : [];
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
        $updateChannel = (string) ($settings['update_channel'] ?? SettingsRepository::UPDATE_CHANNEL_STABLE);
        $updateChannelStatus = $this->buildUpdateChannelStatus($updateChannel);
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
            'family_font_display_options' => $this->buildFamilyFontDisplayOptions((string) ($settings['font_display'] ?? 'optional')),
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
            'css_delivery_mode' => (string) ($settings['css_delivery_mode'] ?? 'file'),
            'css_delivery_mode_options' => $this->buildCssDeliveryModeOptions(),
            'font_display' => (string) ($settings['font_display'] ?? 'optional'),
            'font_display_options' => $this->buildFontDisplayOptions(),
            'unicode_range_mode' => (string) ($settings['unicode_range_mode'] ?? FontUtils::UNICODE_RANGE_MODE_OFF),
            'unicode_range_custom_value' => (string) ($settings['unicode_range_custom_value'] ?? ''),
            'unicode_range_mode_options' => $this->buildUnicodeRangeModeOptions(),
            'unicode_range_custom_visible' => FontUtils::normalizeUnicodeRangeMode((string) ($settings['unicode_range_mode'] ?? '')) === FontUtils::UNICODE_RANGE_MODE_CUSTOM,
            'output_quick_mode_preference' => (string) ($settings['output_quick_mode_preference'] ?? 'minimal'),
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

    public function buildDiagnosticItems(array $assetStatus, ?array $storage, array $settings, array $counts): array
    {
        $cssPath = (string) ($assetStatus['path'] ?? '');
        $cssUrl = (string) ($assetStatus['url'] ?? '');
        $cssExists = !empty($assetStatus['exists']);
        $cssSize = (int) ($assetStatus['size'] ?? 0);
        $cssLastModified = (int) ($assetStatus['last_modified'] ?? 0);

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
                    ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $cssLastModified)
                    : __('Not available', 'tasty-fonts'),
                'code' => false,
            ],
            [
                'label' => __('Fonts Directory', 'tasty-fonts'),
                'value' => is_array($storage) ? (string) ($storage['dir'] ?? __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => is_array($storage) && !empty($storage['dir']),
            ],
            [
                'label' => __('Fonts Public URL', 'tasty-fonts'),
                'value' => is_array($storage)
                    ? (string) ($storage['url_full'] ?? $storage['url'] ?? __('Not available', 'tasty-fonts'))
                    : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => is_array($storage) && (!empty($storage['url_full']) || !empty($storage['url'])),
            ],
            [
                'label' => __('Google Import Folder', 'tasty-fonts'),
                'value' => is_array($storage) ? (string) ($storage['google_dir'] ?? __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => is_array($storage) && !empty($storage['google_dir']),
            ],
            [
                'label' => __('Bunny Import Folder', 'tasty-fonts'),
                'value' => is_array($storage) ? (string) ($storage['bunny_dir'] ?? __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => is_array($storage) && !empty($storage['bunny_dir']),
            ],
            [
                'label' => __('Local Upload Folder', 'tasty-fonts'),
                'value' => is_array($storage) ? (string) ($storage['upload_dir'] ?? __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => is_array($storage) && !empty($storage['upload_dir']),
            ],
            [
                'label' => __('Adobe Folder', 'tasty-fonts'),
                'value' => is_array($storage) ? (string) ($storage['adobe_dir'] ?? __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
                'copyable' => is_array($storage) && !empty($storage['adobe_dir']),
            ],
            [
                'label' => __('Library Inventory', 'tasty-fonts'),
                'value' => sprintf(
                    __('%1$d families / %2$d files', 'tasty-fonts'),
                    (int) ($counts['families'] ?? 0),
                    (int) ($counts['files'] ?? 0)
                ),
                'code' => false,
            ],
            [
                'label' => __('Generated CSS Delivery', 'tasty-fonts'),
                'value' => $this->formatCssDeliveryModeLabel((string) ($settings['css_delivery_mode'] ?? 'file')),
                'code' => false,
            ],
            [
                'label' => __('Default Font Display', 'tasty-fonts'),
                'value' => (string) ($settings['font_display'] ?? 'optional'),
                'code' => false,
            ],
        ];
    }

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

    private function buildUpdateChannelStatus(string $selectedChannel): array
    {
        $installedVersion = defined('TASTY_FONTS_VERSION')
            ? (string) TASTY_FONTS_VERSION
            : '';
        $latestAvailable = null;
        $state = 'unavailable';

        if ($this->updater instanceof GitHubUpdater) {
            $overview = $this->updater->getChannelOverview($selectedChannel);
            $installedVersion = (string) ($overview['installed_version'] ?? $installedVersion);
            $latestAvailable = is_array($overview['latest_available'] ?? null) ? $overview['latest_available'] : null;
            $state = (string) ($overview['state'] ?? $state);
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
            'latest_version' => is_array($latestAvailable) ? (string) ($latestAvailable['version'] ?? '') : '',
            'latest_channel' => is_array($latestAvailable) ? (string) ($latestAvailable['channel'] ?? '') : '',
            'latest_channel_label' => is_array($latestAvailable)
                ? $this->formatUpdateChannelLabel((string) ($latestAvailable['channel'] ?? SettingsRepository::UPDATE_CHANNEL_STABLE))
                : '',
            'state' => $state,
            'state_label' => $stateLabel,
            'state_class' => $stateClass,
            'state_copy' => $stateCopy,
            'can_reinstall' => $state === 'rollback' && is_array($latestAvailable),
        ];
    }

    public function buildOverviewMetrics(array $counts): array
    {
        return [
            [
                'label' => __('Families', 'tasty-fonts'),
                'value' => (string) ($counts['families'] ?? 0),
            ],
            [
                'label' => __('Published', 'tasty-fonts'),
                'value' => (string) ($counts['published_families'] ?? 0),
            ],
            [
                'label' => __('In Library Only', 'tasty-fonts'),
                'value' => (string) ($counts['library_only_families'] ?? 0),
            ],
            [
                'label' => __('Self-hosted', 'tasty-fonts'),
                'value' => (string) ($counts['local_families'] ?? 0),
            ],
        ];
    }

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

            $message = sanitize_text_field((string) ($toast['message'] ?? ''));

            if ($message === '') {
                continue;
            }

            $tone = (string) ($toast['tone'] ?? 'success');
            $role = (string) ($toast['role'] ?? ($tone === 'error' ? 'alert' : 'status'));

            $toasts[] = [
                'tone' => $tone === 'error' ? 'error' : 'success',
                'message' => $message,
                'role' => $role === 'alert' ? 'alert' : 'status',
            ];
        }

        return $toasts;
    }

    public function buildFontDisplayOptions(): array
    {
        return [
            ['value' => 'optional', 'label' => $this->formatFontDisplayLabel('optional')],
            ['value' => 'swap', 'label' => $this->formatFontDisplayLabel('swap')],
            ['value' => 'fallback', 'label' => $this->formatFontDisplayLabel('fallback')],
            ['value' => 'block', 'label' => $this->formatFontDisplayLabel('block')],
            ['value' => 'auto', 'label' => $this->formatFontDisplayLabel('auto')],
        ];
    }

    public function buildCssDeliveryModeOptions(): array
    {
        return [
            ['value' => 'file', 'label' => __('Generated file', 'tasty-fonts')],
            ['value' => 'inline', 'label' => __('Inline in page head', 'tasty-fonts')],
        ];
    }

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
            ['value' => 'optional', 'label' => $this->formatFontDisplayLabel('optional')],
            ['value' => 'swap', 'label' => $this->formatFontDisplayLabel('swap')],
            ['value' => 'fallback', 'label' => $this->formatFontDisplayLabel('fallback')],
            ['value' => 'block', 'label' => $this->formatFontDisplayLabel('block')],
            ['value' => 'auto', 'label' => $this->formatFontDisplayLabel('auto')],
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

    public function roleSetsMatch(array $left, array $right, ?array $settings = null, ?array $catalog = null): bool
    {
        $settings = $settings ?? $this->settings->getSettings();
        $catalog = $catalog ?? $this->catalog->getCatalog();

        foreach ($this->effectiveRoleKeys($settings) as $roleKey) {
            if (trim((string) ($left[$roleKey] ?? '')) !== trim((string) ($right[$roleKey] ?? ''))) {
                return false;
            }

            if ($this->resolveEffectiveRoleFallback($roleKey, $left, $catalog, $settings) !== $this->resolveEffectiveRoleFallback($roleKey, $right, $catalog, $settings)) {
                return false;
            }

            if (
                trim((string) ($left[$roleKey . '_weight'] ?? ''))
                !== trim((string) ($right[$roleKey . '_weight'] ?? ''))
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

    public function buildActivityActorOptions(array $logs): array
    {
        $actors = [];

        foreach ($logs as $entry) {
            $actor = trim((string) ($entry['actor'] ?? ''));

            if ($actor === '') {
                continue;
            }

            $actors[$actor] = $actor;
        }

        natcasesort($actors);

        return array_values($actors);
    }

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

    public function buildGutenbergIntegrationContext(array $settings): array
    {
        $enabled = !empty($settings['block_editor_font_library_sync_enabled']);
        $isLocal = SiteEnvironment::isLikelyLocalEnvironment(rest_url(''), SiteEnvironment::currentEnvironmentType());

        return [
            'enabled' => $enabled,
            'is_local' => $isLocal,
            'title' => __('Gutenberg Font Library', 'tasty-fonts'),
            'description' => __('Mirror imported families into WordPress typography controls so the block editor and site editor can use the same fonts managed by Tasty Fonts.', 'tasty-fonts'),
            'status_label' => $enabled ? __('On', 'tasty-fonts') : __('Off', 'tasty-fonts'),
            'status_copy' => $enabled
                ? ($isLocal
                    ? __('Sync is on. If this local site hits loopback SSL or certificate errors, turn it off here until PHP/cURL trusts the site certificate again.', 'tasty-fonts')
                    : __('Sync is on. Imported families will be mirrored into the WordPress Font Library when core font-library support is available.', 'tasty-fonts'))
                : __('Sync is off. Tasty Fonts will keep Gutenberg font library writes disabled until you enable them again here.', 'tasty-fonts'),
        ];
    }

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
                ? __('Etch is active. Tasty Fonts will keep its generated and remote font stylesheets mirrored into Etch canvas iframes automatically.', 'tasty-fonts')
                : __('Etch is not active on this site. If you install Etch later, the canvas bridge will turn on automatically.', 'tasty-fonts'),
        ];
    }

    public function buildAcssIntegrationContext(array $settings): array
    {
        $enabled = ($settings['acss_font_role_sync_enabled'] ?? null) === true;
        $applied = !empty($settings['acss_font_role_sync_applied']);
        $sitewideRolesEnabled = !empty($settings['auto_apply_roles']);
        $state = $this->acssIntegration->readState($sitewideRolesEnabled, $enabled, $applied);

        return array_merge(
            $state,
            [
                'title' => __('Automatic.css', 'tasty-fonts'),
                'description' => __('Sync ACSS heading and body font-family settings to Tasty Fonts role variables for clean interoperability.', 'tasty-fonts'),
                'status_label' => $this->buildAcssIntegrationStatusLabel((string) ($state['status'] ?? 'disabled')),
                'status_copy' => $this->buildAcssIntegrationStatusCopy((string) ($state['status'] ?? 'disabled'), $state),
            ]
        );
    }

    public function buildBricksIntegrationContext(array $settings): array
    {
        $state = $this->bricksIntegration->readState($settings['bricks_integration_enabled'] ?? null);

        return array_merge(
            $state,
            [
                'title' => __('Bricks Builder', 'tasty-fonts'),
                'description' => __('Expose published Tasty Fonts families inside Bricks selectors and mirror Bricks theme font families into Gutenberg.', 'tasty-fonts'),
                'status_label' => $this->buildBuilderIntegrationStatusLabel((string) ($state['status'] ?? 'disabled')),
                'status_copy' => $this->buildBricksIntegrationStatusCopy((string) ($state['status'] ?? 'disabled')),
            ]
        );
    }

    public function buildOxygenIntegrationContext(array $settings): array
    {
        $state = $this->oxygenIntegration->readState($settings['oxygen_integration_enabled'] ?? null);

        return array_merge(
            $state,
            [
                'title' => __('Oxygen Builder', 'tasty-fonts'),
                'description' => __('Expose published Tasty Fonts families through Oxygen’s custom-font compatibility layer and mirror Oxygen global font families into Gutenberg.', 'tasty-fonts'),
                'status_label' => $this->buildBuilderIntegrationStatusLabel((string) ($state['status'] ?? 'disabled')),
                'status_copy' => $this->buildOxygenIntegrationStatusCopy((string) ($state['status'] ?? 'disabled')),
            ]
        );
    }

    public function buildPreviewContext(array $settings): array
    {
        $previewText = isset($_GET['preview_text'])
            ? wp_strip_all_tags(sanitize_text_field(wp_unslash((string) $_GET['preview_text'])))
            : (string) ($settings['preview_sentence'] ?? '');
        $previewSize = isset($_GET['preview_size']) ? absint($_GET['preview_size']) : 32;
        $previewText = $previewText !== ''
            ? $previewText
            : __('The quick brown fox jumps over the lazy dog. 1234567890', 'tasty-fonts');

        return [
            'preview_text' => $previewText,
            'preview_size' => $previewSize > 0 ? $previewSize : 32,
        ];
    }

    public function buildAdobeAccessContext(): array
    {
        $projectId = $this->adobe->getProjectId();
        $projectSaved = $projectId !== '';
        $projectEnabled = $this->adobe->isEnabled();
        $projectStatus = $this->adobe->getProjectStatus();
        $projectState = (string) ($projectStatus['state'] ?? 'empty');
        $projectStatusMessage = (string) ($projectStatus['message'] ?? '');
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

    public function buildGoogleAccessContext(): array
    {
        $googleApiEnabled = $this->googleClient->canSearch();
        $googleApiSaved = $this->googleClient->hasApiKey();
        $googleApiStatus = $this->googleClient->getApiKeyStatus();
        $googleApiState = (string) ($googleApiStatus['state'] ?? 'empty');
        $googleApiStatusMessage = (string) ($googleApiStatus['message'] ?? '');

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

    public function buildSelectableFamilyNames(array $catalog): array
    {
        $families = array_keys($catalog);
        $storedRoles = $this->settings->getRoles([]);

        foreach ($this->effectiveRoleKeys() as $roleKey) {
            $familyName = trim((string) ($storedRoles[$roleKey] ?? ''));

            if ($familyName !== '') {
                $families[] = $familyName;
            }
        }

        $families = array_values(array_unique($families));
        natcasesort($families);

        return array_values($families);
    }

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
                'type' => is_array($descriptor) ? (string) ($descriptor['type'] ?? '') : '',
            ];
        }

        return $options;
    }

    public function buildRoleDeliverySummary(array $roles, ?array $settings = null): string
    {
        $parts = [];
        $settings = $settings ?? $this->settings->getSettings();

        foreach ($this->effectiveRoleKeys($settings) as $roleKey) {
            $familyName = trim((string) ($roles[$roleKey] ?? ''));
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

    public function buildRoleTextSummary(array $roles, ?array $settings = null): string
    {
        $settings = $settings ?? $this->settings->getSettings();
        $parts = [];

        foreach ($this->effectiveRoleKeys($settings) as $roleKey) {
            $familyName = trim((string) ($roles[$roleKey] ?? ''));
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

    private function filterRuntimeVisibleFamilies(array $catalog): array
    {
        $families = [];

        foreach ($catalog as $key => $family) {
            if (!is_array($family) || (string) ($family['publish_state'] ?? 'published') === 'library_only') {
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

    private function buildAcssIntegrationStatusCopy(string $status, array $state): string
    {
        $current = is_array($state['current'] ?? null) ? $state['current'] : ['heading' => '', 'body' => ''];

        return match ($status) {
            'synced' => __('Automatic.css is using `var(--font-heading)` and `var(--font-body)` now. Tasty Fonts will restore the previous ACSS values if sitewide role delivery is turned off.', 'tasty-fonts'),
            'ready' => __('Automatic.css is active and the sync is enabled. Save or re-open settings to apply the variable mapping.', 'tasty-fonts'),
            'out_of_sync' => __('Automatic.css is active, but its current font-family values differ from the managed Tasty Fonts mapping. Re-save the integration to reapply it.', 'tasty-fonts'),
            'waiting_for_sitewide_roles' => __('Automatic.css sync is enabled, but Tasty Fonts only exposes `--font-heading` and `--font-body` when sitewide role delivery is on. Publish roles sitewide first, then the sync will apply.', 'tasty-fonts'),
            'unavailable' => __('Automatic.css is not active on this site, so there is nothing to sync yet.', 'tasty-fonts'),
            default => sprintf(
                __('Automatic.css currently uses heading `%1$s` and text `%2$s`.', 'tasty-fonts'),
                $current['heading'] !== '' ? $current['heading'] : __('empty', 'tasty-fonts'),
                $current['body'] !== '' ? $current['body'] : __('empty', 'tasty-fonts')
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
            'active' => __('Bricks is active. Published Tasty Fonts families will appear in Bricks selectors, and matching Bricks theme font families will be mirrored into Gutenberg.', 'tasty-fonts'),
            'unavailable' => __('Bricks is not active on this site yet. If you install or reactivate Bricks later, this integration can turn on automatically.', 'tasty-fonts'),
            default => __('Bricks integration is off. Tasty Fonts will stay out of Bricks selectors and will not mirror Bricks font-family choices into Gutenberg.', 'tasty-fonts'),
        };
    }

    private function buildOxygenIntegrationStatusCopy(string $status): string
    {
        return match ($status) {
            'active' => __('Oxygen is active. Published Tasty Fonts families are exposed through the compatibility shim, and matching Oxygen global font families will be mirrored into Gutenberg.', 'tasty-fonts'),
            'unavailable' => __('Oxygen is not active on this site yet. If you install or reactivate Oxygen later, this integration can turn on automatically.', 'tasty-fonts'),
            default => __('Oxygen integration is off. Tasty Fonts will not register the Oxygen compatibility shim or mirror Oxygen font-family choices into Gutenberg.', 'tasty-fonts'),
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

        return (int) ($preference['hidden_until'] ?? 0) <= time();
    }

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
            'hidden_until' => max(0, (int) ($preference['hidden_until'] ?? 0)),
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
        $label = strtolower(trim((string) ($delivery['label'] ?? '')));

        if ($label === '') {
            return $familyName;
        }

        return sprintf(__('%1$s via %2$s', 'tasty-fonts'), $familyName, $label);
    }

    private function resolveEffectiveRoleFallback(string $roleKey, array $roles, array $catalog, array $settings): string
    {
        $default = $this->defaultRoleFallback($roleKey);
        $familyName = trim((string) ($roles[$roleKey] ?? ''));

        if ($familyName !== '') {
            $familyFallbacks = is_array($settings['family_fallbacks'] ?? null) ? $settings['family_fallbacks'] : [];

            if (array_key_exists($familyName, $familyFallbacks)) {
                $configuredFallback = trim((string) $familyFallbacks[$familyName]);

                if ($configuredFallback !== '') {
                    return FontUtils::sanitizeFallback($configuredFallback);
                }
            }

            $family = $this->findCatalogFamilyByName($familyName, $catalog);

            if (is_array($family)) {
                return FontUtils::defaultFallbackForCategory($this->resolveFamilyCategory($family));
            }
        }

        $fallback = trim((string) ($roles[$roleKey . '_fallback'] ?? ''));

        return $fallback !== '' ? FontUtils::sanitizeFallback($fallback) : $default;
    }

    private function findCatalogFamilyByName(string $familyName, array $catalog): ?array
    {
        if (is_array($catalog[$familyName] ?? null)) {
            return $catalog[$familyName];
        }

        foreach ($catalog as $family) {
            if (!is_array($family)) {
                continue;
            }

            if (trim((string) ($family['family'] ?? '')) === $familyName) {
                return $family;
            }
        }

        return null;
    }

    private function resolveFamilyCategory(array $family): string
    {
        $category = trim((string) ($family['font_category'] ?? ''));

        if ($category === '' && is_array($family['active_delivery'] ?? null) && is_array($family['active_delivery']['meta'] ?? null)) {
            $category = trim((string) ($family['active_delivery']['meta']['category'] ?? ''));
        }

        return $category;
    }

    private function roleComparisonKeys(array $settings): array
    {
        $keys = [];

        foreach ($this->effectiveRoleKeys($settings) as $roleKey) {
            $keys[] = $roleKey;
            $keys[] = $roleKey . '_fallback';
            $keys[] = $roleKey . '_weight';

            if (!empty($settings['variable_fonts_enabled'])) {
                $keys[] = $roleKey . '_axes';
            }
        }

        return $keys;
    }

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

    private function buildPluginBehaviorUrl(): string
    {
        return add_query_arg(
            [
                'page' => AdminController::MENU_SLUG,
                'tf_page' => AdminController::PAGE_SETTINGS,
                'tf_studio' => 'plugin-behavior',
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
