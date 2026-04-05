<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Fonts\LibraryService;
use TastyFonts\Fonts\LocalUploadService;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Google\GoogleImportService;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;

final class AdminController
{
    public const MENU_SLUG = 'tasty-custom-fonts';
    private const NOTICE_TTL = 300;
    private const NOTICE_TRANSIENT_PREFIX = 'tasty_fonts_admin_notices_';
    private const NOTICE_MESSAGES = [
        'settings_saved' => 'Plugin settings saved.',
        'adobe_project_saved' => 'Adobe Fonts project saved.',
        'adobe_project_removed' => 'Adobe Fonts project removed.',
        'adobe_project_resynced' => 'Adobe Fonts project resynced.',
        'google_key_saved' => 'Google Fonts API key saved and validated.',
        'google_key_cleared' => 'Google Fonts API key removed.',
        'fallback_saved' => 'Font fallback saved.',
        'roles_saved' => 'Font roles saved.',
        'rescan' => 'Fonts rescanned.',
        'log_cleared' => 'Activity log cleared.',
        'family_deleted' => 'Font family deleted.',
        'variant_deleted' => 'Font variant deleted.',
    ];
    private const ROLE_KEYS = ['heading', 'body'];

    private readonly AdminPageRenderer $renderer;

    public function __construct(
        private readonly Storage $storage,
        private readonly SettingsRepository $settings,
        private readonly LogRepository $log,
        private readonly CatalogService $catalog,
        private readonly AssetService $assets,
        private readonly LibraryService $library,
        private readonly LocalUploadService $localUpload,
        private readonly CssBuilder $cssBuilder,
        private readonly AdobeProjectClient $adobe,
        private readonly GoogleFontsClient $googleClient,
        private readonly GoogleImportService $googleImport
    ) {
        $this->renderer = new AdminPageRenderer($this->storage);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('Tasty Custom Fonts', 'tasty-fonts'),
            __('Tasty Fonts', 'tasty-fonts'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderPage'],
            'dashicons-editor-textcolor',
            999
        );
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        if (!self::isPluginAdminHook($hookSuffix)) {
            return;
        }

        $googleApiStatus = $this->googleClient->getApiKeyStatus();
        $googleSearchEnabled = $this->googleClient->canSearch();

        wp_enqueue_style(
            'tasty-fonts-admin-tokens',
            TASTY_FONTS_URL . 'assets/css/tokens.css',
            [],
            $this->assetVersionFor('assets/css/tokens.css')
        );

        wp_enqueue_style(
            'tasty-fonts-admin',
            TASTY_FONTS_URL . 'assets/css/admin.css',
            ['tasty-fonts-admin-tokens'],
            $this->assetVersionFor('assets/css/admin.css')
        );

        wp_enqueue_script(
            'tasty-fonts-admin',
            TASTY_FONTS_URL . 'assets/js/admin.js',
            [],
            $this->assetVersionFor('assets/js/admin.js'),
            true
        );

        wp_localize_script(
            'tasty-fonts-admin',
            'TastyFontsAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'searchNonce' => wp_create_nonce('tasty_fonts_search_google'),
                'importNonce' => wp_create_nonce('tasty_fonts_import_google'),
                'uploadNonce' => wp_create_nonce('tasty_fonts_upload_local'),
                'saveFallbackNonce' => wp_create_nonce('tasty_fonts_save_family_fallback'),
                'saveRolesNonce' => wp_create_nonce('tasty_fonts_save_role_draft'),
                'googleApiEnabled' => $googleSearchEnabled,
                'strings' => $this->buildAdminStrings($this->buildSearchDisabledMessage($googleApiStatus)),
            ]
        );
    }

    public function handleAdminActions(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if ($this->handleClearLogAction()) {
            return;
        }

        if ($this->handleRescanFontsAction()) {
            return;
        }

        if ($this->handleSaveSettingsAction()) {
            return;
        }

        if ($this->handleSaveAdobeProjectAction()) {
            return;
        }

        if ($this->handleSaveFamilyFallbackAction()) {
            return;
        }

        if ($this->handleDeleteVariantAction()) {
            return;
        }

        if ($this->handleDeleteFamilyAction()) {
            return;
        }

        $this->handleSaveRolesAction();
    }

    public function ajaxSearchGoogle(): void
    {
        $this->assertManageOptionsAjax(__('You are not allowed to search Google Fonts.', 'tasty-fonts'));
        check_ajax_referer('tasty_fonts_search_google', 'nonce');

        if (!$this->googleClient->canSearch()) {
            $this->sendAjaxError(__('Search is unavailable until a Google Fonts API key is saved.', 'tasty-fonts'), 400);
        }

        wp_send_json_success(['items' => $this->googleClient->searchFamilies($this->getPostedText('query'), 20)]);
    }

    public function ajaxImportGoogle(): void
    {
        $this->assertManageOptionsAjax(__('You are not allowed to import Google Fonts.', 'tasty-fonts'));
        check_ajax_referer('tasty_fonts_import_google', 'nonce');

        $result = $this->googleImport->importFamily(
            $this->getPostedText('family'),
            $this->getPostedGoogleVariants()
        );

        if (is_wp_error($result)) {
            $this->sendAjaxError($result->get_error_message(), 400);
        }

        wp_send_json_success($result);
    }

    public function ajaxUploadLocal(): void
    {
        $this->assertManageOptionsAjax(__('You are not allowed to upload local fonts.', 'tasty-fonts'));
        check_ajax_referer('tasty_fonts_upload_local', 'nonce');

        $rows = $this->getPostedUploadRows();

        if ($rows === []) {
            $this->sendAjaxError(__('Add at least one upload row before submitting.', 'tasty-fonts'), 400);
        }

        $result = $this->localUpload->uploadRows($rows);

        if (is_wp_error($result)) {
            $this->sendAjaxError($result->get_error_message(), 400);
        }

        wp_send_json_success($result);
    }

    public function ajaxSaveFamilyFallback(): void
    {
        $this->assertManageOptionsAjax(__('You are not allowed to update font fallback settings.', 'tasty-fonts'));
        check_ajax_referer('tasty_fonts_save_family_fallback', 'nonce');

        $family = $this->getPostedText('family');
        $fallback = $this->getPostedFallback('fallback');

        if ($family === '') {
            $this->sendAjaxError(__('A font family is required before saving its fallback.', 'tasty-fonts'), 400);
        }

        $this->settings->saveFamilyFallback($family, $fallback);

        wp_send_json_success(
            [
                'family' => $family,
                'fallback' => $fallback,
                'stack' => FontUtils::buildFontStack($family, $fallback),
                'message' => sprintf(
                    __('Saved fallback for %s.', 'tasty-fonts'),
                    $family
                ),
            ]
        );
    }

    public function ajaxSaveRoleDraft(): void
    {
        $this->assertManageOptionsAjax(__('You are not allowed to update font roles.', 'tasty-fonts'));
        check_ajax_referer('tasty_fonts_save_role_draft', 'nonce');

        $availableFamilies = $this->buildSelectableFamilyNames($this->catalog->getCatalog());
        $settings = $this->settings->getSettings();

        if (!empty($settings['auto_apply_roles'])) {
            $this->settings->ensureAppliedRolesInitialized($availableFamilies);
        }

        $roles = $this->settings->saveRoles(
            [
                'heading' => $this->getPostedText('heading'),
                'body' => $this->getPostedText('body'),
                'heading_fallback' => $this->getPostedFallback('heading_fallback'),
                'body_fallback' => $this->getPostedFallback('body_fallback'),
            ],
            $availableFamilies
        );

        $settings = $this->settings->getSettings();
        $applyEverywhere = !empty($settings['auto_apply_roles']);
        $appliedRoles = $this->settings->getAppliedRoles($availableFamilies);

        $this->assets->refreshGeneratedAssets(false);

        $message = $this->buildRolesSavedMessage('save', $roles, $appliedRoles, $applyEverywhere);
        $this->log->add($message);

        wp_send_json_success(
            [
                'message' => $message,
                'roles' => $roles,
                'role_deployment' => $this->buildRoleDeploymentContext($roles, $appliedRoles, $applyEverywhere),
            ]
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->renderer->renderPage($this->buildPageContext());
    }

    private function handleClearLogAction(): bool
    {
        if (!isset($_POST['tasty_fonts_clear_log'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_clear_log');
        $this->log->clear();
        $this->log->add(__('Activity log cleared. Older entries removed.', 'tasty-fonts'));
        $this->redirectWithNoticeKey('log_cleared');
    }

    private function handleRescanFontsAction(): bool
    {
        if (!isset($_POST['tasty_fonts_rescan_fonts'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_rescan_fonts');
        $this->assets->refreshGeneratedAssets(true, false);
        $this->log->add(__('Fonts rescanned.', 'tasty-fonts'));
        $this->redirectWithNoticeKey('rescan');
    }

    private function handleSaveSettingsAction(): bool
    {
        if (!isset($_POST['tasty_fonts_save_settings'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_save_settings');

        $previousSettings = $this->settings->getSettings();
        $submittedGoogleKey = $this->getPostedText('google_api_key');
        $clearGoogleKey = !empty($_POST['tasty_fonts_clear_google_api_key']);

        $settingsInput = [];

        foreach (
            [
                'google_api_key',
                'tasty_fonts_clear_google_api_key',
                'css_delivery_mode',
                'font_display',
                'preview_sentence',
            ] as $field
        ) {
            if (array_key_exists($field, $_POST)) {
                $settingsInput[$field] = wp_unslash($_POST[$field]);
            }
        }

        foreach (['minify_css_output', 'delete_uploaded_files_on_uninstall'] as $field) {
            if (array_key_exists($field, $_POST)) {
                $settingsInput[$field] = $_POST[$field];
            }
        }

        $savedSettings = $this->settings->saveSettings($settingsInput);
        $this->googleClient->clearCatalogCache();

        if ($clearGoogleKey) {
            $this->settings->saveGoogleApiKeyStatus('empty');
            $this->log->add(__('Google Fonts API key removed.', 'tasty-fonts'));
            $this->redirectWithNoticeKey('google_key_cleared');
        }

        if ($submittedGoogleKey !== '') {
            $validation = $this->googleClient->validateApiKey($submittedGoogleKey);

            $this->settings->saveGoogleApiKeyStatus(
                (string) ($validation['state'] ?? 'unknown'),
                (string) ($validation['message'] ?? '')
            );

            if (($validation['state'] ?? 'unknown') === 'valid') {
                $this->log->add(__('Google Fonts API key validated.', 'tasty-fonts'));
                $this->redirectWithNoticeKey('google_key_saved');
            }

            $this->log->add(__('Google Fonts API key validation failed.', 'tasty-fonts'));
            $this->redirectWithError(
                (string) (
                    $validation['message']
                    ?? __('Google Fonts API key could not be validated.', 'tasty-fonts')
                )
            );
        }

        $settingsMessage = $this->buildSettingsSavedMessage($previousSettings, $savedSettings);

        $this->log->add($settingsMessage);
        $this->redirectWithSuccess($settingsMessage);
    }

    private function handleSaveFamilyFallbackAction(): bool
    {
        if (!isset($_POST['tasty_fonts_save_family_fallback'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_save_family_fallback');

        $family = $this->getPostedText('tasty_fonts_family_name');
        $fallback = $this->getPostedFallback('tasty_fonts_family_fallback');

        if ($family !== '') {
            $this->settings->saveFamilyFallback($family, $fallback);
            $this->log->add(
                sprintf(
                    __('Saved fallback for %1$s: %2$s.', 'tasty-fonts'),
                    $family,
                    $fallback
                )
            );
        }

        $this->redirectWithNoticeKey('fallback_saved');
    }

    private function handleDeleteFamilyAction(): bool
    {
        if (!isset($_POST['tasty_fonts_delete_family'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_delete_family');

        $result = $this->library->deleteFamily($this->getPostedText('tasty_fonts_family_slug'));

        if (is_wp_error($result)) {
            $this->redirectWithError($result->get_error_message());
        }

        $this->redirectWithNoticeKey('family_deleted');
    }

    private function handleDeleteVariantAction(): bool
    {
        if (!isset($_POST['tasty_fonts_delete_variant'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_delete_variant');

        $result = $this->library->deleteFaceVariant(
            $this->getPostedText('tasty_fonts_family_slug'),
            $this->getPostedText('tasty_fonts_face_weight'),
            $this->getPostedText('tasty_fonts_face_style'),
            $this->getPostedText('tasty_fonts_face_source', 'local'),
            $this->getPostedText('tasty_fonts_face_unicode_range')
        );

        if (is_wp_error($result)) {
            $this->redirectWithError($result->get_error_message());
        }

        $message = sprintf(
            __('Variant deleted: %1$s %2$s %3$s.', 'tasty-fonts'),
            (string) ($result['family'] ?? __('Font', 'tasty-fonts')),
            (string) ($result['weight'] ?? '400'),
            (string) ($result['style'] ?? 'normal')
        );

        $this->redirectWithSuccess($message);
    }

    private function handleSaveRolesAction(): void
    {
        if (!isset($_POST['tasty_fonts_save_roles'])) {
            return;
        }

        check_admin_referer('tasty_fonts_save_roles');

        $catalog = $this->catalog->getCatalog();
        $availableFamilies = $this->buildSelectableFamilyNames($catalog);
        $actionType = $this->getPostedText('tasty_fonts_action_type', 'save');
        $settings = $this->settings->getSettings();
        $wasAppliedSitewide = !empty($settings['auto_apply_roles']);

        if ($actionType !== 'apply' && $wasAppliedSitewide) {
            $this->settings->ensureAppliedRolesInitialized($availableFamilies);
        }

        $roles = $this->settings->saveRoles(
            [
                'heading' => $this->getPostedText('tasty_fonts_heading_font'),
                'body' => $this->getPostedText('tasty_fonts_body_font'),
                'heading_fallback' => $this->getPostedFallback('tasty_fonts_heading_fallback'),
                'body_fallback' => $this->getPostedFallback('tasty_fonts_body_fallback'),
            ],
            $availableFamilies
        );

        $settings = $this->settings->getSettings();
        $appliedRoles = $this->settings->getAppliedRoles($availableFamilies);

        if ($actionType === 'apply') {
            $this->settings->saveAppliedRoles($roles, $availableFamilies);
            $this->settings->setAutoApplyRoles(true);
        } elseif ($actionType === 'disable') {
            $this->settings->setAutoApplyRoles(false);
        }

        $this->assets->refreshGeneratedAssets(false);

        $message = $this->buildRolesSavedMessage(
            $actionType,
            $roles,
            $appliedRoles,
            $wasAppliedSitewide
        );

        $this->log->add($message);

        $this->redirectWithSuccess($message);
    }

    private function buildPageContext(): array
    {
        $storage = $this->storage->get();
        $settings = $this->settings->getSettings();
        $catalog = $this->catalog->getCatalog();
        $logs = $this->log->all();
        $counts = $this->catalog->getCounts();
        $assetStatus = $this->assets->getStatus();
        $familyFallbacks = is_array($settings['family_fallbacks'] ?? null) ? $settings['family_fallbacks'] : [];
        $applyEverywhere = !empty($settings['auto_apply_roles']);
        $previewContext = $this->buildPreviewContext($settings);
        $adobeAccessContext = $this->buildAdobeAccessContext();
        $availableFamilies = $this->buildSelectableFamilyNames($catalog);
        $roles = $this->settings->getRoles($availableFamilies);
        $appliedRoles = $this->settings->getAppliedRoles($availableFamilies);
        $googleAccessContext = $this->buildGoogleAccessContext();
        $roleDeploymentContext = $this->buildRoleDeploymentContext($roles, $appliedRoles, $applyEverywhere);

        return [
            'storage' => $storage,
            'catalog' => $catalog,
            'available_families' => $availableFamilies,
            'roles' => $roles,
            'applied_roles' => $appliedRoles,
            'apply_everywhere' => $applyEverywhere,
            'role_deployment' => $roleDeploymentContext,
            'logs' => $logs,
            'activity_actor_options' => $this->buildActivityActorOptions($logs),
            'family_fallbacks' => $familyFallbacks,
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
            'minify_css_output' => !empty($settings['minify_css_output']),
            'delete_uploaded_files_on_uninstall' => !empty($settings['delete_uploaded_files_on_uninstall']),
            'diagnostic_items' => $this->buildDiagnosticItems($assetStatus, $storage, $settings, $counts),
            'overview_metrics' => $this->buildOverviewMetrics($counts, $applyEverywhere, $assetStatus),
            'output_panels' => $this->buildOutputPanels($roles),
            'preview_panels' => $this->buildPreviewPanels(),
            'toasts' => $this->buildNoticeToasts(),
        ];
    }

    private function buildDiagnosticItems(array $assetStatus, ?array $storage, array $settings, array $counts): array
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
                'label' => __('Library Inventory', 'tasty-fonts'),
                'value' => sprintf(
                    __('%1$d families / %2$d files', 'tasty-fonts'),
                    (int) ($counts['families'] ?? 0),
                    (int) ($counts['files'] ?? 0)
                ),
                'code' => false,
            ],
            [
                'label' => __('Delivery Mode', 'tasty-fonts'),
                'value' => (string) ($settings['css_delivery_mode'] ?? 'file'),
                'code' => false,
            ],
            [
                'label' => __('Font Display', 'tasty-fonts'),
                'value' => (string) ($settings['font_display'] ?? 'swap'),
                'code' => false,
            ],
        ];
    }

    private function buildOverviewMetrics(array $counts, bool $applyEverywhere, array $assetStatus): array
    {
        $cssExists = !empty($assetStatus['exists']);
        $cssSize = $cssExists
            ? size_format((int) ($assetStatus['size'] ?? 0))
            : __('Not generated', 'tasty-fonts');

        return [
            [
                'label' => __('Font Families', 'tasty-fonts'),
                'value' => (string) ($counts['families'] ?? 0),
            ],
            [
                'label' => __('Font Files', 'tasty-fonts'),
                'value' => (string) ($counts['files'] ?? 0),
            ],
            [
                'label' => __('Apply Everywhere', 'tasty-fonts'),
                'value' => $applyEverywhere ? __('On', 'tasty-fonts') : __('Off', 'tasty-fonts'),
            ],
            [
                'label' => __('Final CSS', 'tasty-fonts'),
                'value' => $cssSize,
            ],
        ];
    }

    private function buildOutputPanels(array $roles): array
    {
        return [
            [
                'key' => 'usage',
                'label' => __('Site Snippet', 'tasty-fonts'),
                'target' => 'tasty-fonts-output-usage',
                'value' => $this->cssBuilder->buildRoleUsageSnippet($roles),
                'active' => true,
            ],
            [
                'key' => 'variables',
                'label' => __('CSS Variables', 'tasty-fonts'),
                'target' => 'tasty-fonts-output-vars',
                'value' => $this->cssBuilder->buildRoleVariableSnippet($roles),
                'active' => false,
            ],
            [
                'key' => 'stacks',
                'label' => __('Font Stacks', 'tasty-fonts'),
                'target' => 'tasty-fonts-output-stacks',
                'value' => $this->cssBuilder->buildRoleStackSnippet($roles),
                'active' => false,
            ],
            [
                'key' => 'names',
                'label' => __('Font Names', 'tasty-fonts'),
                'target' => 'tasty-fonts-output-names',
                'value' => $this->cssBuilder->buildRoleNameSnippet($roles),
                'active' => false,
            ],
        ];
    }

    private function buildPreviewPanels(): array
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
        ];
    }

    private function buildNoticeToasts(): array
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

    private function buildSettingsSavedMessage(array $before, array $after): string
    {
        $changes = [];

        if (($before['css_delivery_mode'] ?? '') !== ($after['css_delivery_mode'] ?? '')) {
            $changes[] = sprintf(
                __('delivery mode set to %s', 'tasty-fonts'),
                $this->formatCssDeliveryModeLabel((string) ($after['css_delivery_mode'] ?? 'file'))
            );
        }

        if (($before['font_display'] ?? '') !== ($after['font_display'] ?? '')) {
            $changes[] = sprintf(
                __('font-display set to %s', 'tasty-fonts'),
                (string) ($after['font_display'] ?? 'swap')
            );
        }

        if (!empty($before['minify_css_output']) !== !empty($after['minify_css_output'])) {
            $changes[] = !empty($after['minify_css_output'])
                ? __('CSS minification enabled', 'tasty-fonts')
                : __('CSS minification disabled', 'tasty-fonts');
        }

        if (($before['preview_sentence'] ?? '') !== ($after['preview_sentence'] ?? '')) {
            $changes[] = __('preview text updated', 'tasty-fonts');
        }

        if (!empty($before['delete_uploaded_files_on_uninstall']) !== !empty($after['delete_uploaded_files_on_uninstall'])) {
            $changes[] = !empty($after['delete_uploaded_files_on_uninstall'])
                ? __('uninstall file cleanup enabled', 'tasty-fonts')
                : __('uninstall file cleanup disabled', 'tasty-fonts');
        }

        if ($changes === []) {
            return __('Plugin settings saved.', 'tasty-fonts');
        }

        return sprintf(
            __('Plugin settings saved: %s.', 'tasty-fonts'),
            implode(', ', $changes)
        );
    }

    private function formatCssDeliveryModeLabel(string $mode): string
    {
        return match ($mode) {
            'inline' => __('inline CSS', 'tasty-fonts'),
            default => __('generated file', 'tasty-fonts'),
        };
    }

    private function assetVersionFor(string $relativePath): string
    {
        return TASTY_FONTS_VERSION;
    }

    private function buildAdminStrings(string $searchDisabledMessage): array
    {
        return [
            'previewFallback' => __('The quick brown fox jumps over the lazy dog. 1234567890', 'tasty-fonts'),
            'importPreviewSample' => __("Aa Bb Cc Dd Ee Ff Gg Hh\n0123456789", 'tasty-fonts'),
            'searching' => __('Searching Google Fonts…', 'tasty-fonts'),
            'searchEmpty' => __('No Google Fonts families matched that search.', 'tasty-fonts'),
            'searchDisabled' => $searchDisabledMessage,
            'selectFamily' => __('Select a family from search results or type one manually.', 'tasty-fonts'),
            'importFamilyEmpty' => __('Choose a Google family or type one manually.', 'tasty-fonts'),
            'importPreviewEmpty' => __('Preview appears here after you choose a family.', 'tasty-fonts'),
            'importing' => __('Importing and self-hosting selected files…', 'tasty-fonts'),
            'importSuccess' => __('Font imported successfully. Reloading…', 'tasty-fonts'),
            'importError' => __('The Google Fonts import failed.', 'tasty-fonts'),
            'importProgress' => __('Importing %1$s: %2$d of %3$d (%4$s)…', 'tasty-fonts'),
            'importSummary' => __('Imported %1$d variant%2$s. %3$d skipped. Reloading…', 'tasty-fonts'),
            'importAlreadyExists' => __('%s already exists in the library for the selected variants.', 'tasty-fonts'),
            'importNoVariants' => __('Select at least one variant to import.', 'tasty-fonts'),
            'importButtonIdle' => __('Import and Self-Host', 'tasty-fonts'),
            'importButtonBusy' => __('Importing…', 'tasty-fonts'),
            'importEstimateFiles' => __('%1$d File%2$s Selected', 'tasty-fonts'),
            'importEstimateSize' => __('Approx. +%1$s WOFF2', 'tasty-fonts'),
            'importSelectionSummaryEmpty' => __('0 Variants Selected', 'tasty-fonts'),
            'importSelectionSummaryAvailable' => __('%1$d of %2$d Variants Selected', 'tasty-fonts'),
            'importSelectionSummaryManual' => __('%1$d Variant%2$s Selected', 'tasty-fonts'),
            'uploadReady' => __('Upload WOFF2, WOFF, TTF, or OTF files. Each row imports one face.', 'tasty-fonts'),
            'uploadSubmitting' => __('Uploading font files…', 'tasty-fonts'),
            'uploadProgress' => __('Uploading files… %1$d%%', 'tasty-fonts'),
            'uploadSuccess' => __('Upload complete. Refreshing the library…', 'tasty-fonts'),
            'uploadError' => __('The font upload failed.', 'tasty-fonts'),
            'uploadNoFile' => __('No file chosen', 'tasty-fonts'),
            'uploadButtonIdle' => __('Upload to Library', 'tasty-fonts'),
            'uploadButtonBusy' => __('Uploading…', 'tasty-fonts'),
            'uploadRowQueued' => __('Queued', 'tasty-fonts'),
            'uploadRowUploading' => __('Uploading…', 'tasty-fonts'),
            'uploadRowImported' => __('Imported', 'tasty-fonts'),
            'uploadRowSkipped' => __('Skipped', 'tasty-fonts'),
            'uploadRowError' => __('Error', 'tasty-fonts'),
            'uploadAddFace' => __('Add Face', 'tasty-fonts'),
            'uploadAddFamily' => __('Add Another Family', 'tasty-fonts'),
            'uploadUseDetected' => __('Use Detected Values', 'tasty-fonts'),
            'uploadDetectedSummary' => __('Detected: %1$s / %2$s / %3$s', 'tasty-fonts'),
            'uploadDetectedWeightStyle' => __('Detected: %1$s / %2$s', 'tasty-fonts'),
            'uploadRemoveRow' => __('Remove Row', 'tasty-fonts'),
            'uploadRequiresRows' => __('Add at least one upload row before submitting.', 'tasty-fonts'),
            'rolesDraftSaving' => __('Saving role draft…', 'tasty-fonts'),
            'rolesDraftSaved' => __('Role draft saved.', 'tasty-fonts'),
            'rolesDraftSaveError' => __('The role draft could not be saved.', 'tasty-fonts'),
            'fallbackSaving' => __('Saving fallback…', 'tasty-fonts'),
            'deleteConfirm' => __('Delete "%s" and remove its files from uploads/fonts?', 'tasty-fonts'),
            'fallbackSaved' => __('Saved fallback for %1$s.', 'tasty-fonts'),
            'fallbackSaveError' => __('The fallback could not be saved.', 'tasty-fonts'),
            'copied' => __('Copied', 'tasty-fonts'),
            'copy' => __('Copy', 'tasty-fonts'),
            'activityCountSingle' => __('%1$d entry', 'tasty-fonts'),
            'activityCountMultiple' => __('%1$d entries', 'tasty-fonts'),
            'activityCountFilteredSingle' => __('%1$d of %2$d entry', 'tasty-fonts'),
            'activityCountFilteredMultiple' => __('%1$d of %2$d entries', 'tasty-fonts'),
            'activityNoMatches' => __('No activity matches the current filters.', 'tasty-fonts'),
        ];
    }

    private function buildRoleDeploymentContext(array $draftRoles, array $appliedRoles, bool $applyEverywhere): array
    {
        if (!$applyEverywhere) {
            return [
                'badge' => __('Saved Only', 'tasty-fonts'),
                'badge_class' => 'is-warning',
                'title' => __('Draft Saved Only', 'tasty-fonts'),
                'copy' => sprintf(
                    __('Sitewide roles are off. Apply Heading %1$s / Body %2$s when you want the frontend, editor, and Etch to use this pair.', 'tasty-fonts'),
                    (string) ($draftRoles['heading'] ?? ''),
                    (string) ($draftRoles['body'] ?? '')
                ),
            ];
        }

        if ($this->roleSetsMatch($draftRoles, $appliedRoles)) {
            return [
                'badge' => __('Live', 'tasty-fonts'),
                'badge_class' => 'is-success',
                'title' => __('Live Pair Active', 'tasty-fonts'),
                'copy' => sprintf(
                    __('Frontend, editor, and Etch are using Heading %1$s / Body %2$s.', 'tasty-fonts'),
                    (string) ($draftRoles['heading'] ?? ''),
                    (string) ($draftRoles['body'] ?? '')
                ),
            ];
        }

        return [
            'badge' => __('Pending', 'tasty-fonts'),
            'badge_class' => 'is-warning',
            'title' => __('Draft Differs From Live', 'tasty-fonts'),
            'copy' => sprintf(
                __('Draft %1$s / %2$s. Live %3$s / %4$s. Apply Sitewide to publish the draft pair.', 'tasty-fonts'),
                (string) ($draftRoles['heading'] ?? ''),
                (string) ($draftRoles['body'] ?? ''),
                (string) ($appliedRoles['heading'] ?? ''),
                (string) ($appliedRoles['body'] ?? '')
            ),
        ];
    }

    private function roleSetsMatch(array $left, array $right): bool
    {
        foreach (['heading', 'body', 'heading_fallback', 'body_fallback'] as $key) {
            if ((string) ($left[$key] ?? '') !== (string) ($right[$key] ?? '')) {
                return false;
            }
        }

        return true;
    }

    private function buildRolesSavedMessage(string $actionType, array $roles, array $appliedRoles, bool $wasAppliedSitewide): string
    {
        return match ($actionType) {
            'apply' => sprintf(
                __('Role pair applied sitewide. Heading: %1$s; Body: %2$s.', 'tasty-fonts'),
                (string) ($roles['heading'] ?? ''),
                (string) ($roles['body'] ?? '')
            ),
            'disable' => sprintf(
                __('Sitewide role CSS turned off. Draft kept as Heading: %1$s; Body: %2$s.', 'tasty-fonts'),
                (string) ($roles['heading'] ?? ''),
                (string) ($roles['body'] ?? '')
            ),
            default => $wasAppliedSitewide
                ? sprintf(
                    __('Role draft saved. Live site still uses Heading: %1$s; Body: %2$s until you apply the draft.', 'tasty-fonts'),
                    (string) ($appliedRoles['heading'] ?? ''),
                    (string) ($appliedRoles['body'] ?? '')
                )
                : sprintf(
                    __('Role draft saved. Sitewide roles stay off until you apply Heading: %1$s; Body: %2$s.', 'tasty-fonts'),
                    (string) ($roles['heading'] ?? ''),
                    (string) ($roles['body'] ?? '')
                ),
        };
    }

    private function buildActivityActorOptions(array $logs): array
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

    private function buildSearchDisabledMessage(array $googleApiStatus): string
    {
        return match ((string) ($googleApiStatus['state'] ?? 'empty')) {
            'invalid' => __('Search is disabled because the saved Google Fonts API key is invalid. Replace it above to re-enable catalog search.', 'tasty-fonts'),
            'unknown' => __('Search is unavailable until the saved Google Fonts API key is validated. Re-save or replace the key above to continue.', 'tasty-fonts'),
            default => __('Add a Google Fonts API key above to enable search, or use manual import below.', 'tasty-fonts'),
        };
    }

    private function buildPreviewContext(array $settings): array
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

    private function buildAdobeAccessContext(): array
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

    private function buildGoogleAccessContext(): array
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
            default => __('Enable live family search with a Google Fonts Developer API key. Imported files are still downloaded and stored locally after import.', 'tasty-fonts'),
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

    private function buildSelectableFamilyNames(array $catalog): array
    {
        $families = array_keys($catalog);

        foreach ($this->adobe->getConfiguredFamilies() as $family) {
            $familyName = trim((string) ($family['family'] ?? ''));

            if ($familyName === '') {
                continue;
            }

            $families[] = $familyName;
        }

        $storedRoles = $this->settings->getRoles([]);

        foreach (self::ROLE_KEYS as $roleKey) {
            $familyName = trim((string) ($storedRoles[$roleKey] ?? ''));

            if ($familyName !== '') {
                $families[] = $familyName;
            }
        }

        $families = array_values(array_unique($families));
        natcasesort($families);

        return array_values($families);
    }

    private function handleSaveAdobeProjectAction(): bool
    {
        $isRemove = isset($_POST['tasty_fonts_remove_adobe_project']);
        $isResync = isset($_POST['tasty_fonts_resync_adobe_project']);
        $isSave = isset($_POST['tasty_fonts_save_adobe_project']);

        if (!$isSave && !$isRemove && !$isResync) {
            return false;
        }

        check_admin_referer('tasty_fonts_save_adobe_project');

        if ($isRemove) {
            $existingProjectId = $this->settings->getAdobeProjectId();

            $this->settings->clearAdobeProject();
            $this->adobe->clearProjectCache($existingProjectId);
            $this->log->add(__('Adobe Fonts project removed.', 'tasty-fonts'));
            $this->redirectWithNoticeKey('adobe_project_removed');
        }

        $projectId = $isResync
            ? $this->settings->getAdobeProjectId()
            : $this->getPostedText('adobe_project_id');
        $enabled = $isResync
            ? $this->settings->isAdobeEnabled()
            : !empty($_POST['adobe_enabled']);
        $existingProjectId = $this->settings->getAdobeProjectId();

        if (!$isResync) {
            $this->settings->saveAdobeProject($projectId, $enabled);
        }

        if ($projectId === '') {
            $this->adobe->clearProjectCache($existingProjectId);
            $this->log->add(__('Adobe Fonts project cleared.', 'tasty-fonts'));
            $this->redirectWithNoticeKey('adobe_project_removed');
        }

        $this->adobe->clearProjectCache($projectId);
        $validation = $this->adobe->validateProject($projectId);

        $this->settings->saveAdobeProjectStatus(
            (string) ($validation['state'] ?? 'unknown'),
            (string) ($validation['message'] ?? '')
        );

        if (($validation['state'] ?? 'unknown') !== 'valid') {
            $this->log->add(__('Adobe Fonts project validation failed.', 'tasty-fonts'));
            $this->redirectWithError(
                (string) (
                    $validation['message']
                    ?? __('Adobe Fonts project could not be validated.', 'tasty-fonts')
                )
            );
        }

        $this->log->add($isResync
            ? __('Adobe Fonts project resynced.', 'tasty-fonts')
            : __('Adobe Fonts project saved.', 'tasty-fonts'));
        $this->redirectWithNoticeKey($isResync ? 'adobe_project_resynced' : 'adobe_project_saved');
    }

    private function assertManageOptionsAjax(string $message): void
    {
        if (!current_user_can('manage_options')) {
            $this->sendAjaxError($message, 403);
        }
    }

    private function getPostedText(string $key, string $default = ''): string
    {
        if (!isset($_POST[$key])) {
            return $default;
        }

        return sanitize_text_field(wp_unslash((string) $_POST[$key]));
    }

    private function getPostedFallback(string $key, string $default = 'sans-serif'): string
    {
        return FontUtils::sanitizeFallback($this->getPostedText($key, $default));
    }

    private function getPostedGoogleVariants(): array
    {
        $variants = isset($_POST['variants'])
            ? array_map('sanitize_text_field', (array) wp_unslash($_POST['variants']))
            : [];

        if ($variants !== []) {
            return $variants;
        }

        $tokens = $this->getPostedText('variant_tokens');

        if ($tokens === '') {
            return [];
        }

        return array_map('trim', explode(',', $tokens));
    }

    private function getPostedUploadRows(): array
    {
        $postedRows = isset($_POST['rows']) && is_array($_POST['rows'])
            ? wp_unslash($_POST['rows'])
            : [];
        $uploadedFiles = $this->normalizeUploadedFiles($_FILES['files'] ?? []);
        $rows = [];

        foreach ($postedRows as $index => $row) {
            $rows[] = [
                'family' => sanitize_text_field((string) ($row['family'] ?? '')),
                'weight' => sanitize_text_field((string) ($row['weight'] ?? '400')),
                'style' => sanitize_text_field((string) ($row['style'] ?? 'normal')),
                'fallback' => sanitize_text_field((string) ($row['fallback'] ?? 'sans-serif')),
                'file' => $uploadedFiles[$index] ?? [],
            ];
        }

        return $rows;
    }

    private function normalizeUploadedFiles(mixed $rawFiles): array
    {
        if (
            !is_array($rawFiles)
            || !isset($rawFiles['name'], $rawFiles['tmp_name'], $rawFiles['error'], $rawFiles['size'])
            || !is_array($rawFiles['name'])
        ) {
            return [];
        }

        $normalized = [];

        foreach ($rawFiles['name'] as $index => $name) {
            $normalized[$index] = [
                'name' => is_string($name) ? $name : '',
                'type' => is_array($rawFiles['type'] ?? null) ? (string) ($rawFiles['type'][$index] ?? '') : '',
                'tmp_name' => is_array($rawFiles['tmp_name'] ?? null) ? (string) ($rawFiles['tmp_name'][$index] ?? '') : '',
                'error' => is_array($rawFiles['error'] ?? null) ? (int) ($rawFiles['error'][$index] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE,
                'size' => is_array($rawFiles['size'] ?? null) ? (int) ($rawFiles['size'][$index] ?? 0) : 0,
            ];
        }

        return $normalized;
    }

    private function sendAjaxError(string $message, int $status): never
    {
        wp_send_json_error(['message' => $message], $status);
    }

    private function queueNoticeToast(string $tone, string $message, string $role): void
    {
        $transientKey = $this->getPendingNoticeTransientKey();
        $storedToasts = get_transient($transientKey);
        $toasts = is_array($storedToasts) ? $storedToasts : [];

        $toasts[] = [
            'tone' => $tone === 'error' ? 'error' : 'success',
            'message' => sanitize_text_field($message),
            'role' => $role === 'alert' ? 'alert' : 'status',
        ];

        set_transient($transientKey, $toasts, self::NOTICE_TTL);
    }

    private function getPendingNoticeTransientKey(): string
    {
        return self::NOTICE_TRANSIENT_PREFIX . max(0, (int) get_current_user_id());
    }

    private function redirectWithSuccess(string $message): never
    {
        $this->queueNoticeToast('success', $message, 'status');
        $this->redirect();
    }

    private function redirectWithError(string $message): never
    {
        $this->queueNoticeToast('error', $message, 'alert');
        $this->redirect();
    }

    private function redirectWithNoticeKey(string $key): never
    {
        $message = self::NOTICE_MESSAGES[$key] ?? '';

        if ($message === '') {
            $this->redirect();
        }

        $this->redirectWithSuccess(__($message, 'tasty-fonts'));
    }

    private function buildAdminPageUrl(): string
    {
        return add_query_arg(['page' => self::MENU_SLUG], admin_url('admin.php'));
    }

    private function redirect(): never
    {
        wp_safe_redirect($this->buildAdminPageUrl());
        exit;
    }

    public static function isPluginAdminHook(string $hookSuffix): bool
    {
        return $hookSuffix === 'toplevel_page_' . self::MENU_SLUG;
    }
}
