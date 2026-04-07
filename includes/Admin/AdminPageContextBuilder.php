<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\SiteEnvironment;
use TastyFonts\Support\Storage;

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
        private readonly GoogleFontsClient $googleClient
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
        $roles = $this->settings->getRoles($availableFamilies);
        $appliedRoles = $this->settings->getAppliedRoles($availableFamilies);
        $googleAccessContext = $this->buildGoogleAccessContext();
        $roleDeploymentContext = $this->buildRoleDeploymentContext($roles, $appliedRoles, $applyEverywhere, $settings);
        $localEnvironmentNotice = $this->buildLocalEnvironmentNotice($settings);
        $previewBaselineSource = $applyEverywhere ? 'live_sitewide' : 'draft';
        $previewBaselineLabel = $applyEverywhere
            ? __('Live sitewide', 'tasty-fonts')
            : __('Current draft', 'tasty-fonts');

        return [
            'storage' => $storage,
            'catalog' => $catalog,
            'available_families' => $availableFamilies,
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
            'per_variant_font_variables_enabled' => !empty($settings['per_variant_font_variables_enabled']),
            'extended_variable_weight_tokens_enabled' => !empty($settings['extended_variable_weight_tokens_enabled']),
            'extended_variable_role_aliases_enabled' => !empty($settings['extended_variable_role_aliases_enabled']),
            'extended_variable_category_sans_enabled' => !empty($settings['extended_variable_category_sans_enabled']),
            'extended_variable_category_serif_enabled' => !empty($settings['extended_variable_category_serif_enabled']),
            'extended_variable_category_mono_enabled' => !empty($settings['extended_variable_category_mono_enabled']),
            'preload_primary_fonts' => !empty($settings['preload_primary_fonts']),
            'remote_connection_hints' => !empty($settings['remote_connection_hints']),
            'block_editor_font_library_sync_enabled' => !empty($settings['block_editor_font_library_sync_enabled']),
            'training_wheels_off' => !empty($settings['training_wheels_off']),
            'monospace_role_enabled' => !empty($settings['monospace_role_enabled']),
            'delete_uploaded_files_on_uninstall' => !empty($settings['delete_uploaded_files_on_uninstall']),
            'diagnostic_items' => $this->buildDiagnosticItems($assetStatus, $storage, $settings, $counts),
            'overview_metrics' => $this->buildOverviewMetrics($counts),
            'output_panels' => $this->buildOutputPanels($roles, $settings, $catalog, $appliedRoles),
            'generated_css_panel' => $this->buildGeneratedCssPanel($settings),
            'preview_panels' => $this->buildPreviewPanels(),
            'local_environment_notice' => $localEnvironmentNotice,
            'toasts' => $this->buildNoticeToasts(),
        ];
    }

    public function buildDiagnosticItems(array $assetStatus, ?array $storage, array $settings, array $counts): array
    {
        $cssPath = (string) ($assetStatus['path'] ?? '');
        $cssUrl = (string) ($assetStatus['url'] ?? '');
        $cssExists = !empty($assetStatus['exists']) && $cssPath !== '' && file_exists($cssPath);

        return [
            [
                'label' => __('Generated CSS File', 'tasty-fonts'),
                'value' => $cssPath !== '' ? $cssPath : __('Not available', 'tasty-fonts'),
                'code' => true,
            ],
            [
                'label' => __('CSS Request URL', 'tasty-fonts'),
                'value' => $cssUrl !== '' ? $cssUrl : __('Not available', 'tasty-fonts'),
                'code' => true,
            ],
            [
                'label' => __('Stylesheet Size', 'tasty-fonts'),
                'value' => $cssExists ? size_format((int) ($assetStatus['size'] ?? 0)) : __('Not generated', 'tasty-fonts'),
                'code' => false,
            ],
            [
                'label' => __('Last Generated', 'tasty-fonts'),
                'value' => $cssExists
                    ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) filemtime($cssPath))
                    : __('Not available', 'tasty-fonts'),
                'code' => false,
            ],
            [
                'label' => __('Fonts Directory', 'tasty-fonts'),
                'value' => is_array($storage) ? (string) ($storage['dir'] ?? __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
            ],
            [
                'label' => __('Fonts Public URL', 'tasty-fonts'),
                'value' => is_array($storage)
                    ? (string) ($storage['url_full'] ?? $storage['url'] ?? __('Not available', 'tasty-fonts'))
                    : __('Not available', 'tasty-fonts'),
                'code' => true,
            ],
            [
                'label' => __('Google Import Folder', 'tasty-fonts'),
                'value' => is_array($storage) ? (string) ($storage['google_dir'] ?? __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
            ],
            [
                'label' => __('Bunny Import Folder', 'tasty-fonts'),
                'value' => is_array($storage) ? (string) ($storage['bunny_dir'] ?? __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
            ],
            [
                'label' => __('Local Upload Folder', 'tasty-fonts'),
                'value' => is_array($storage) ? (string) ($storage['upload_dir'] ?? __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
            ],
            [
                'label' => __('Adobe Folder', 'tasty-fonts'),
                'value' => is_array($storage) ? (string) ($storage['adobe_dir'] ?? __('Not available', 'tasty-fonts')) : __('Not available', 'tasty-fonts'),
                'code' => true,
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

        return [
            [
                'key' => 'usage',
                'label' => __('Site Snippet', 'tasty-fonts'),
                'target' => 'tasty-fonts-output-usage',
                'value' => $this->cssBuilder->formatOutput(
                    $this->cssBuilder->buildRoleUsageSnippet($snippetRoles, $includeMonospace, $catalog, $settings),
                    $minifyOutput
                ),
                'active' => true,
            ],
            [
                'key' => 'variables',
                'label' => __('CSS Variables', 'tasty-fonts'),
                'target' => 'tasty-fonts-output-vars',
                'value' => $this->cssBuilder->formatOutput(
                    $this->cssBuilder->buildRoleVariableSnippet($snippetRoles, $includeMonospace, $catalog, $settings),
                    $minifyOutput
                ),
                'active' => false,
            ],
            [
                'key' => 'classes',
                'label' => __('Font Classes', 'tasty-fonts'),
                'target' => 'tasty-fonts-output-classes',
                'value' => $this->buildClassOutputPanelContent($snippetRoles, $settings, $runtimeFamilies, $includeMonospace),
                'active' => false,
            ],
            [
                'key' => 'stacks',
                'label' => __('Font Stacks', 'tasty-fonts'),
                'target' => 'tasty-fonts-output-stacks',
                'value' => $this->cssBuilder->buildRoleStackSnippet($snippetRoles, $includeMonospace),
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
            ['value' => 'optional', 'label' => $this->formatFontDisplayLabel('optional', true)],
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

    public function formatFontDisplayLabel(string $display, bool $recommended = false): string
    {
        return match ($display) {
            'auto' => __('Auto', 'tasty-fonts'),
            'block' => __('Block', 'tasty-fonts'),
            'swap' => __('Swap', 'tasty-fonts'),
            'fallback' => __('Fallback', 'tasty-fonts'),
            default => $recommended
                ? __('Optional (Recommended)', 'tasty-fonts')
                : __('Optional', 'tasty-fonts'),
        };
    }

    public function formatCssDeliveryModeLabel(string $mode): string
    {
        return match ($mode) {
            'inline' => __('inline CSS', 'tasty-fonts'),
            default => __('generated file', 'tasty-fonts'),
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

    public function roleSetsMatch(array $left, array $right, ?array $settings = null): bool
    {
        foreach ($this->roleComparisonKeys($settings ?? $this->settings->getSettings()) as $key) {
            if ((string) ($left[$key] ?? '') !== (string) ($right[$key] ?? '')) {
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
                ? __('Block Editor Font Library sync is enabled on this local site. Keep it on only when this site can complete background requests back to this site without SSL or certificate errors. If Activity shows cURL 60 or certificate verification failures, open Plugin Behavior and turn the sync back off for local development.', 'tasty-fonts')
                : __('Block Editor Font Library sync is off by default on local environments because editor sync uses background requests back to this site, and local HTTPS certificates often fail certificate verification. Turn it on when you want imported fonts mirrored into the core WordPress Font Library and your local setup trusts this site\'s certificate.', 'tasty-fonts'),
            'settings_label' => __('Open Plugin Behavior', 'tasty-fonts'),
            'settings_url' => $this->buildPluginBehaviorUrl(),
        ];
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

    public function buildRoleDeliverySummary(array $roles, ?array $settings = null): string
    {
        $parts = [];
        $settings = $settings ?? $this->settings->getSettings();

        foreach ($this->effectiveRoleKeys($settings) as $roleKey) {
            $familyName = trim((string) ($roles[$roleKey] ?? ''));
            $fallback = FontUtils::sanitizeFallback((string) ($roles[$roleKey . '_fallback'] ?? $this->defaultRoleFallback($roleKey)));

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
            $fallback = FontUtils::sanitizeFallback((string) ($roles[$roleKey . '_fallback'] ?? $this->defaultRoleFallback($roleKey)));

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
        bool $includeMonospace
    ): string {
        if (empty($settings['class_output_enabled'])) {
            return __('Class output is off. Turn on Classes in Output Settings to generate utility classes.', 'tasty-fonts');
        }

        $content = $this->cssBuilder->formatOutput(
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

    private function roleComparisonKeys(array $settings): array
    {
        $keys = [];

        foreach ($this->effectiveRoleKeys($settings) as $roleKey) {
            $keys[] = $roleKey;
            $keys[] = $roleKey . '_fallback';
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
        return AdminController::NOTICE_TRANSIENT_PREFIX . max(0, (int) get_current_user_id());
    }

    private function buildGeneratedCssDownloadUrl(): string
    {
        return add_query_arg(
            [
                'page' => AdminController::MENU_SLUG,
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
                'tf_advanced' => '1',
                'tf_studio' => 'plugin-behavior',
            ],
            admin_url('admin.php')
        );
    }
}
