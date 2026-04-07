<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Bunny\BunnyImportService;
use TastyFonts\Api\RestController;
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
use WP_Error;

final class AdminController
{
    public const MENU_SLUG = 'tasty-custom-fonts';
    private const ACTION_DOWNLOAD_GENERATED_CSS = 'tasty_fonts_download_generated_css';
    public const LOCAL_ENV_NOTICE_OPTION = 'tasty_fonts_local_environment_notice_preferences';
    private const LOCAL_ENV_NOTICE_FORM_FIELD = 'tasty_fonts_local_environment_notice';
    private const LOCAL_ENV_NOTICE_ACTION_FIELD = 'tasty_fonts_local_environment_notice_action';
    private const NOTICE_TTL = 300;
    public const NOTICE_TRANSIENT_PREFIX = 'tasty_fonts_admin_notices_';
    private const SEARCH_CACHE_TTL = 900;
    private const SEARCH_CACHE_TRANSIENT_PREFIX = 'tasty_fonts_search_cache_';
    private const SEARCH_COOLDOWN_TRANSIENT_PREFIX = 'tasty_fonts_search_cooldown_';
    private const SEARCH_COOLDOWN_WINDOW_SECONDS = 0.5;
    private const SEARCH_COOLDOWN_TRANSIENT_TTL = 1;
    private readonly AdminPageRenderer $renderer;
    private readonly AdminPageContextBuilder $pageContextBuilder;

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
        private readonly BunnyFontsClient $bunnyClient,
        private readonly BunnyImportService $bunnyImport,
        private readonly GoogleFontsClient $googleClient,
        private readonly GoogleImportService $googleImport
    ) {
        $this->renderer = new AdminPageRenderer($this->storage);
        $this->pageContextBuilder = new AdminPageContextBuilder(
            $this->storage,
            $this->settings,
            $this->log,
            $this->catalog,
            $this->assets,
            $this->cssBuilder,
            $this->adobe,
            $this->googleClient
        );
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

        $settings = $this->settings->getSettings();
        $catalog = $this->catalog->getCatalog();
        $availableFamilies = $this->buildSelectableFamilyNames($catalog);
        $roles = $this->settings->getRoles($availableFamilies);
        $appliedRoles = $this->settings->getAppliedRoles($availableFamilies);
        $applyEverywhere = !empty($settings['auto_apply_roles']);
        $googleApiStatus = $this->googleClient->getApiKeyStatus();
        $googleSearchEnabled = $this->googleClient->canSearch();

        wp_enqueue_style(
            'tasty-fonts-admin-tokens',
            TASTY_FONTS_URL . 'assets/css/tokens.css',
            [],
            $this->assetVersionFor()
        );

        wp_enqueue_style(
            'tasty-fonts-admin',
            TASTY_FONTS_URL . 'assets/css/admin.css',
            ['tasty-fonts-admin-tokens'],
            $this->assetVersionFor()
        );

        wp_enqueue_script(
            'tasty-fonts-admin-contracts',
            TASTY_FONTS_URL . 'assets/js/admin-contracts.js',
            [],
            $this->assetVersionFor(),
            true
        );

        wp_enqueue_script(
            'tasty-fonts-admin',
            TASTY_FONTS_URL . 'assets/js/admin.js',
            ['wp-i18n', 'tasty-fonts-admin-contracts'],
            $this->assetVersionFor(),
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(
                'tasty-fonts-admin',
                'tasty-fonts',
                TASTY_FONTS_DIR . 'languages'
            );
        }

        wp_localize_script(
            'tasty-fonts-admin',
            'TastyFontsAdmin',
            [
                'restUrl' => rest_url(RestController::API_NAMESPACE . '/'),
                'restNonce' => wp_create_nonce('wp_rest'),
                'routes' => RestController::routeMap(),
                'googleApiEnabled' => $googleSearchEnabled,
                'applyEverywhere' => $applyEverywhere,
                'trainingWheelsOff' => !empty($settings['training_wheels_off']),
                'monospaceRoleEnabled' => !empty($settings['monospace_role_enabled']),
                'previewBootstrap' => [
                    'roles' => $roles,
                    'appliedRoles' => $appliedRoles,
                    'baselineSource' => $applyEverywhere ? 'live_sitewide' : 'draft',
                    'baselineLabel' => $applyEverywhere
                        ? __('Live sitewide', 'tasty-fonts')
                        : __('Current draft', 'tasty-fonts'),
                ],
                'runtimeStrings' => [
                    'searchDisabled' => $this->buildSearchDisabledMessage($googleApiStatus),
                ],
            ]
        );
    }

    public function handleAdminActions(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if ($this->handleDownloadGeneratedCssAction()) {
            return;
        }

        if ($this->handleClearLogAction()) {
            return;
        }

        if ($this->handleRescanFontsAction()) {
            return;
        }

        if ($this->handleLocalEnvironmentNoticeAction()) {
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

        if ($this->handleSaveFamilyFontDisplayAction()) {
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

    public function searchGoogle(string $query): array|WP_Error
    {
        return $this->resolveRateLimitedSearch(
            'google',
            $query,
            fn (string $resolvedQuery): array|WP_Error => $this->searchGoogleResults($resolvedQuery)
        );
    }

    public function searchBunny(string $query): array|WP_Error
    {
        return $this->resolveRateLimitedSearch(
            'bunny',
            $query,
            fn (string $resolvedQuery): array|WP_Error => $this->searchBunnyResults($resolvedQuery)
        );
    }

    public function searchGoogleResults(string $query): array|WP_Error
    {
        if (!$this->googleClient->canSearch()) {
            return new WP_Error(
                'tasty_fonts_google_search_unavailable',
                __('Search is unavailable until a Google Fonts API key is saved.', 'tasty-fonts')
            );
        }

        return ['items' => $this->googleClient->searchFamilies(sanitize_text_field($query), 20)];
    }

    public function searchBunnyResults(string $query): array
    {
        return ['items' => $this->bunnyClient->searchFamilies(sanitize_text_field($query), 12)];
    }

    public function fetchGoogleFamily(string $familyName): array|WP_Error
    {
        $familyName = sanitize_text_field($familyName);

        if ($familyName === '') {
            return new WP_Error(
                'tasty_fonts_missing_google_family',
                __('A Google Fonts family name is required.', 'tasty-fonts')
            );
        }

        if (!$this->googleClient->canSearch()) {
            return new WP_Error(
                'tasty_fonts_google_search_unavailable',
                __('Search is unavailable until a Google Fonts API key is saved.', 'tasty-fonts')
            );
        }

        $result = $this->googleClient->getFamily($familyName);

        if ($result === null) {
            return new WP_Error(
                'tasty_fonts_google_family_not_found',
                __('No Google Fonts family matched that name.', 'tasty-fonts')
            );
        }

        return ['item' => $result];
    }

    public function fetchBunnyFamily(string $familyName): array|WP_Error
    {
        $familyName = sanitize_text_field($familyName);

        if ($familyName === '') {
            return new WP_Error(
                'tasty_fonts_missing_bunny_family',
                __('A Bunny Fonts family name is required.', 'tasty-fonts')
            );
        }

        $result = $this->bunnyClient->getFamily($familyName);

        if ($result === null) {
            return new WP_Error(
                'tasty_fonts_bunny_family_not_found',
                __('No Bunny Fonts family matched that name.', 'tasty-fonts')
            );
        }

        return ['item' => $result];
    }

    public function importGoogleFamily(string $familyName, array $variantTokens, string $deliveryMode = 'self_hosted'): array|WP_Error
    {
        return $this->googleImport->importFamily(
            sanitize_text_field($familyName),
            $this->sanitizeVariantTokens($variantTokens),
            $this->normalizeDeliveryMode($deliveryMode)
        );
    }

    public function importBunnyFamily(string $familyName, array $variantTokens, string $deliveryMode = 'self_hosted'): array|WP_Error
    {
        return $this->bunnyImport->importFamily(
            sanitize_text_field($familyName),
            $this->sanitizeVariantTokens($variantTokens),
            $this->normalizeDeliveryMode($deliveryMode)
        );
    }

    public function prepareUploadRows(array $postedRows, array $rawFiles): array
    {
        $uploadedFiles = $this->normalizeUploadedFiles($rawFiles);
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

    public function uploadLocalFontRows(array $rows): array|WP_Error
    {
        if ($rows === []) {
            return new WP_Error(
                'tasty_fonts_upload_requires_rows',
                __('Add at least one upload row before submitting.', 'tasty-fonts')
            );
        }

        return $this->localUpload->uploadRows($rows);
    }

    public function saveFamilyFallbackValue(string $family, string $fallback): array|WP_Error
    {
        $family = sanitize_text_field($family);
        $fallback = FontUtils::sanitizeFallback($fallback);

        if ($family === '') {
            return new WP_Error(
                'tasty_fonts_missing_family',
                __('A font family is required before saving its fallback.', 'tasty-fonts')
            );
        }

        $this->settings->saveFamilyFallback($family, $fallback);

        return [
            'family' => $family,
            'fallback' => $fallback,
            'stack' => FontUtils::buildFontStack($family, $fallback),
            'message' => sprintf(
                __('Saved fallback for %s.', 'tasty-fonts'),
                $family
            ),
        ];
    }

    public function saveFamilyFontDisplayValue(string $family, string $display): array|WP_Error
    {
        $family = sanitize_text_field($family);

        if ($family === '') {
            return new WP_Error(
                'tasty_fonts_missing_family',
                __('A font family is required before saving font-display.', 'tasty-fonts')
            );
        }

        $result = $this->saveFamilyFontDisplaySelection($family, $this->normalizeFamilyFontDisplay($display));

        return [
            'family' => $family,
            'font_display' => $result['font_display'],
            'effective_font_display' => $result['effective_font_display'],
            'message' => $result['message'],
        ];
    }

    public function saveRoleDraftValues(array $roleValues): array
    {
        $availableFamilies = $this->buildSelectableFamilyNames($this->catalog->getCatalog());
        $settings = $this->settings->getSettings();

        if (!empty($settings['auto_apply_roles'])) {
            $this->settings->ensureAppliedRolesInitialized($availableFamilies);
        }

        $roles = $this->settings->saveRoles(
            $this->sanitizeRoleValues($roleValues),
            $availableFamilies
        );

        $settings = $this->settings->getSettings();
        $applyEverywhere = !empty($settings['auto_apply_roles']);
        $appliedRoles = $this->settings->getAppliedRoles($availableFamilies);
        $this->library->syncLiveRolePublishStates($appliedRoles, $applyEverywhere);

        $this->assets->refreshGeneratedAssets(false, false);

        $message = $this->buildRolesSavedMessage('save', $roles, $appliedRoles, $applyEverywhere);
        $this->log->add($message);

        return [
            'message' => $message,
            'roles' => $roles,
            'role_deployment' => $this->buildRoleDeploymentContext($roles, $appliedRoles, $applyEverywhere, $settings),
        ];
    }

    public function saveFamilyDeliveryValue(string $familySlug, string $deliveryId): array|WP_Error
    {
        return $this->library->saveFamilyDelivery($familySlug, $deliveryId);
    }

    public function saveFamilyPublishStateValue(string $familySlug, string $publishState): array|WP_Error
    {
        return $this->library->saveFamilyPublishState($familySlug, $publishState);
    }

    public function deleteDeliveryProfileValue(string $familySlug, string $deliveryId): array|WP_Error
    {
        return $this->library->deleteDeliveryProfile($familySlug, $deliveryId);
    }

    public function statusForError(WP_Error $error, int $defaultStatus = 400): int
    {
        return match ($error->get_error_code()) {
            'tasty_fonts_google_family_not_found',
            'tasty_fonts_bunny_family_not_found',
            'tasty_fonts_family_not_found',
            'tasty_fonts_delivery_not_found' => 404,
            default => $defaultStatus,
        };
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

    private function handleLocalEnvironmentNoticeAction(): bool
    {
        if (!isset($_POST[self::LOCAL_ENV_NOTICE_FORM_FIELD], $_POST[self::LOCAL_ENV_NOTICE_ACTION_FIELD])) {
            return false;
        }

        check_admin_referer('tasty_fonts_local_environment_notice');

        $action = $this->getPostedText(self::LOCAL_ENV_NOTICE_ACTION_FIELD);
        $message = $this->applyLocalEnvironmentNoticeAction($action);

        if ($message === '') {
            $this->redirect();
        }

        $this->redirectWithSuccess($message);
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

        foreach (
            [
                'minify_css_output',
                'class_output_enabled',
                'class_output_role_heading_enabled',
                'class_output_role_body_enabled',
                'class_output_role_monospace_enabled',
                'class_output_role_alias_interface_enabled',
                'class_output_role_alias_ui_enabled',
                'class_output_role_alias_code_enabled',
                'class_output_category_sans_enabled',
                'class_output_category_serif_enabled',
                'class_output_category_mono_enabled',
                'class_output_families_enabled',
                'per_variant_font_variables_enabled',
                'extended_variable_weight_tokens_enabled',
                'extended_variable_role_aliases_enabled',
                'extended_variable_category_sans_enabled',
                'extended_variable_category_serif_enabled',
                'extended_variable_category_mono_enabled',
                'preload_primary_fonts',
                'remote_connection_hints',
                'block_editor_font_library_sync_enabled',
                'delete_uploaded_files_on_uninstall',
                'training_wheels_off',
            ] as $field
        ) {
            if (array_key_exists($field, $_POST)) {
                $settingsInput[$field] = $_POST[$field];
            }
        }

        if (array_key_exists('monospace_role_enabled', $_POST)) {
            $settingsInput['monospace_role_enabled'] = $_POST['monospace_role_enabled'];
        }

        $savedSettings = $this->settings->saveSettings($settingsInput);
        $this->googleClient->clearCatalogCache();

        if (($previousSettings['monospace_role_enabled'] ?? false) !== ($savedSettings['monospace_role_enabled'] ?? false)) {
            $availableFamilies = $this->buildSelectableFamilyNames($this->catalog->getCatalog());
            $sitewideEnabled = !empty($savedSettings['auto_apply_roles']);
            $liveRoles = $sitewideEnabled ? $this->settings->getAppliedRoles($availableFamilies) : [];
            $this->library->syncLiveRolePublishStates($liveRoles, $sitewideEnabled);
        }

        if ($this->settingsChangeRequiresAssetRefresh($previousSettings, $savedSettings)) {
            $this->assets->refreshGeneratedAssets(false, false);
        }

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

    private function handleSaveFamilyFontDisplayAction(): bool
    {
        if (!isset($_POST['tasty_fonts_save_family_font_display'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_save_family_font_display');

        $family = $this->getPostedText('tasty_fonts_family_name');
        $display = $this->getPostedFamilyFontDisplay('tasty_fonts_family_font_display');

        if ($family === '') {
            $this->redirectWithError(__('A font family is required before saving font-display.', 'tasty-fonts'));
        }

        $result = $this->saveFamilyFontDisplaySelection($family, $display);

        $this->redirectWithSuccess((string) ($result['message'] ?? __('Font display saved.', 'tasty-fonts')));
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
        $settings = $this->settings->getSettings();
        $wasAppliedSitewide = !empty($settings['auto_apply_roles']);
        $actionType = $this->resolveRoleFormActionType(
            $this->getPostedText('tasty_fonts_action_type', 'save'),
            $wasAppliedSitewide
        );

        if ($actionType !== 'apply' && $wasAppliedSitewide) {
            $this->settings->ensureAppliedRolesInitialized($availableFamilies);
        }

        $roles = $this->settings->saveRoles(
            $this->sanitizeRoleValues(
                array_filter(
                    [
                        'heading' => $this->getPostedText('tasty_fonts_heading_font'),
                        'body' => $this->getPostedText('tasty_fonts_body_font'),
                        'heading_fallback' => $this->getPostedFallback('tasty_fonts_heading_fallback'),
                        'body_fallback' => $this->getPostedFallback('tasty_fonts_body_fallback'),
                        'monospace' => isset($_POST['tasty_fonts_monospace_font'])
                            ? $this->getPostedText('tasty_fonts_monospace_font')
                            : null,
                        'monospace_fallback' => isset($_POST['tasty_fonts_monospace_fallback'])
                            ? $this->getPostedFallback('tasty_fonts_monospace_fallback', 'monospace')
                            : null,
                    ],
                    static fn (mixed $value): bool => $value !== null
                )
            ),
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

        $sitewideEnabled = !empty($this->settings->getSettings()['auto_apply_roles']);
        $liveRoles = $sitewideEnabled
            ? $this->settings->getAppliedRoles($availableFamilies)
            : [];
        $this->library->syncLiveRolePublishStates($liveRoles, $sitewideEnabled);

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

    private function resolveRoleFormActionType(string $requestedActionType, bool $wasAppliedSitewide): string
    {
        if ($requestedActionType === 'apply' || $requestedActionType === 'disable') {
            return $requestedActionType;
        }

        $sitewideEnabled = $this->getPostedText('tasty_fonts_sitewide_enabled', $wasAppliedSitewide ? '1' : '0') === '1';

        if ($sitewideEnabled && !$wasAppliedSitewide) {
            return 'apply';
        }

        if (!$sitewideEnabled && $wasAppliedSitewide) {
            return 'disable';
        }

        return 'save';
    }

    private function handleDownloadGeneratedCssAction(): bool
    {
        if (($this->getQueryText('page') !== self::MENU_SLUG) || !isset($_GET[self::ACTION_DOWNLOAD_GENERATED_CSS])) {
            return false;
        }

        check_admin_referer(self::ACTION_DOWNLOAD_GENERATED_CSS);

        $download = $this->buildGeneratedCssDownloadData($this->settings->getSettings());

        if (empty($download['downloadable']) || !is_string($download['content'] ?? null) || trim((string) ($download['content'] ?? '')) === '') {
            $this->redirectWithError(__('Generated CSS is unavailable until sitewide delivery is on.', 'tasty-fonts'));
        }

        $filename = sanitize_file_name((string) ($download['filename'] ?? 'tasty-fonts.css'));
        $content = (string) $download['content'];

        header('Content-Type: text/css; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));

        echo $content;
        exit;
    }

    private function buildPageContext(): array
    {
        return $this->pageContextBuilder->build();
    }

    private function buildDiagnosticItems(array $assetStatus, ?array $storage, array $settings, array $counts): array
    {
        return $this->pageContextBuilder->buildDiagnosticItems($assetStatus, $storage, $settings, $counts);
    }

    private function buildOverviewMetrics(array $counts): array
    {
        return $this->pageContextBuilder->buildOverviewMetrics($counts);
    }

    private function buildOutputPanels(
        array $roles,
        array $settings,
        array $catalog = [],
        array $appliedRoles = []
    ): array
    {
        return $this->pageContextBuilder->buildOutputPanels($roles, $settings, $catalog, $appliedRoles);
    }

    private function buildGeneratedCssPanel(array $settings): array
    {
        return $this->pageContextBuilder->buildGeneratedCssPanel($settings);
    }

    private function buildGeneratedCssDownloadData(array $settings): array
    {
        return $this->pageContextBuilder->buildGeneratedCssDownloadData($settings);
    }

    private function buildPreviewPanels(): array
    {
        return $this->pageContextBuilder->buildPreviewPanels();
    }

    private function buildNoticeToasts(): array
    {
        return $this->pageContextBuilder->buildNoticeToasts();
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
                (string) ($after['font_display'] ?? 'optional')
            );
        }

        if (!empty($before['class_output_enabled']) !== !empty($after['class_output_enabled'])) {
            $changes[] = !empty($after['class_output_enabled'])
                ? __('class output enabled', 'tasty-fonts')
                : __('class output disabled', 'tasty-fonts');
        }

        if ($this->classOutputSubsettingsDiffer($before, $after)) {
            $changes[] = __('class output settings updated', 'tasty-fonts');
        }

        if (!empty($before['minify_css_output']) !== !empty($after['minify_css_output'])) {
            $changes[] = !empty($after['minify_css_output'])
                ? __('CSS minification enabled', 'tasty-fonts')
                : __('CSS minification disabled', 'tasty-fonts');
        }

        if (!empty($before['per_variant_font_variables_enabled']) !== !empty($after['per_variant_font_variables_enabled'])) {
            $changes[] = !empty($after['per_variant_font_variables_enabled'])
                ? __('extended font output variables enabled', 'tasty-fonts')
                : __('extended font output variables disabled', 'tasty-fonts');
        }

        if ($this->extendedVariableSubsettingsDiffer($before, $after)) {
            $changes[] = __('extended variable subsettings updated', 'tasty-fonts');
        }

        if (!empty($before['preload_primary_fonts']) !== !empty($after['preload_primary_fonts'])) {
            $changes[] = !empty($after['preload_primary_fonts'])
                ? __('primary font preloads enabled', 'tasty-fonts')
                : __('primary font preloads disabled', 'tasty-fonts');
        }

        if (!empty($before['remote_connection_hints']) !== !empty($after['remote_connection_hints'])) {
            $changes[] = !empty($after['remote_connection_hints'])
                ? __('remote connection hints enabled', 'tasty-fonts')
                : __('remote connection hints disabled', 'tasty-fonts');
        }

        if (!empty($before['block_editor_font_library_sync_enabled']) !== !empty($after['block_editor_font_library_sync_enabled'])) {
            $changes[] = !empty($after['block_editor_font_library_sync_enabled'])
                ? __('Block Editor Font Library sync enabled', 'tasty-fonts')
                : __('Block Editor Font Library sync disabled', 'tasty-fonts');
        }

        if (!empty($before['training_wheels_off']) !== !empty($after['training_wheels_off'])) {
            $changes[] = !empty($after['training_wheels_off'])
                ? __('onboarding hints hidden', 'tasty-fonts')
                : __('onboarding hints shown', 'tasty-fonts');
        }

        if (!empty($before['monospace_role_enabled']) !== !empty($after['monospace_role_enabled'])) {
            $changes[] = !empty($after['monospace_role_enabled'])
                ? __('monospace role enabled', 'tasty-fonts')
                : __('monospace role disabled', 'tasty-fonts');
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

    private function settingsChangeRequiresAssetRefresh(array $before, array $after): bool
    {
        return ($before['css_delivery_mode'] ?? 'file') !== ($after['css_delivery_mode'] ?? 'file')
            || ($before['font_display'] ?? 'optional') !== ($after['font_display'] ?? 'optional')
            || !empty($before['class_output_enabled']) !== !empty($after['class_output_enabled'])
            || $this->classOutputSubsettingsDiffer($before, $after)
            || !empty($before['minify_css_output']) !== !empty($after['minify_css_output'])
            || !empty($before['per_variant_font_variables_enabled']) !== !empty($after['per_variant_font_variables_enabled'])
            || $this->extendedVariableSubsettingsDiffer($before, $after)
            || !empty($before['monospace_role_enabled']) !== !empty($after['monospace_role_enabled']);
    }

    private function classOutputSubsettingsDiffer(array $before, array $after): bool
    {
        foreach (
            [
                'class_output_role_heading_enabled',
                'class_output_role_body_enabled',
                'class_output_role_monospace_enabled',
                'class_output_role_alias_interface_enabled',
                'class_output_role_alias_ui_enabled',
                'class_output_role_alias_code_enabled',
                'class_output_category_sans_enabled',
                'class_output_category_serif_enabled',
                'class_output_category_mono_enabled',
                'class_output_families_enabled',
            ] as $field
        ) {
            if (!empty($before[$field]) !== !empty($after[$field])) {
                return true;
            }
        }

        return false;
    }

    private function extendedVariableSubsettingsDiffer(array $before, array $after): bool
    {
        foreach (
            [
                'extended_variable_weight_tokens_enabled',
                'extended_variable_role_aliases_enabled',
                'extended_variable_category_sans_enabled',
                'extended_variable_category_serif_enabled',
                'extended_variable_category_mono_enabled',
            ] as $field
        ) {
            if (!empty($before[$field]) !== !empty($after[$field])) {
                return true;
            }
        }

        return false;
    }

    private function buildFontDisplayOptions(): array
    {
        if (!isset($this->pageContextBuilder)) {
            return [
                ['value' => 'optional', 'label' => $this->formatFontDisplayLabel('optional', true)],
                ['value' => 'swap', 'label' => $this->formatFontDisplayLabel('swap')],
                ['value' => 'fallback', 'label' => $this->formatFontDisplayLabel('fallback')],
                ['value' => 'block', 'label' => $this->formatFontDisplayLabel('block')],
                ['value' => 'auto', 'label' => $this->formatFontDisplayLabel('auto')],
            ];
        }

        return $this->pageContextBuilder->buildFontDisplayOptions();
    }

    private function buildCssDeliveryModeOptions(): array
    {
        return $this->pageContextBuilder->buildCssDeliveryModeOptions();
    }

    private function buildFamilyFontDisplayOptions(string $globalDisplay): array
    {
        if (!isset($this->pageContextBuilder)) {
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

        return $this->pageContextBuilder->buildFamilyFontDisplayOptions($globalDisplay);
    }

    private function formatFontDisplayLabel(string $display, bool $recommended = false): string
    {
        if (!isset($this->pageContextBuilder)) {
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

        return $this->pageContextBuilder->formatFontDisplayLabel($display, $recommended);
    }

    private function formatCssDeliveryModeLabel(string $mode): string
    {
        if (!isset($this->pageContextBuilder)) {
            return match ($mode) {
                'inline' => __('inline CSS', 'tasty-fonts'),
                default => __('generated file', 'tasty-fonts'),
            };
        }

        return $this->pageContextBuilder->formatCssDeliveryModeLabel($mode);
    }

    private function assetVersionFor(): string
    {
        return TASTY_FONTS_VERSION;
    }

    private function buildRoleDeploymentContext(array $draftRoles, array $appliedRoles, bool $applyEverywhere, ?array $settings = null): array
    {
        return $this->pageContextBuilder->buildRoleDeploymentContext($draftRoles, $appliedRoles, $applyEverywhere, $settings);
    }

    private function roleSetsMatch(array $left, array $right, ?array $settings = null): bool
    {
        return $this->pageContextBuilder->roleSetsMatch($left, $right, $settings);
    }

    private function buildRolesSavedMessage(string $actionType, array $roles, array $appliedRoles, bool $wasAppliedSitewide): string
    {
        $settings = $this->settings->getSettings();
        $savedSummary = $this->buildRoleTextSummary($roles, $settings);
        $liveSummary = $this->buildRoleTextSummary($appliedRoles, $settings);

        return match ($actionType) {
            'apply' => sprintf(
                __('Roles applied sitewide. %s.', 'tasty-fonts'),
                $savedSummary
            ),
            'disable' => sprintf(
                __('Sitewide role CSS turned off. Saved roles kept as %s.', 'tasty-fonts'),
                $savedSummary
            ),
            default => $wasAppliedSitewide
                ? sprintf(
                    __('Roles saved. Live site still uses %s until you apply them sitewide.', 'tasty-fonts'),
                    $liveSummary
                )
                : sprintf(
                    __('Roles saved. Sitewide roles stay off until you apply %s.', 'tasty-fonts'),
                    $savedSummary
                ),
        };
    }

    private function buildActivityActorOptions(array $logs): array
    {
        if (!isset($this->pageContextBuilder)) {
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

        return $this->pageContextBuilder->buildActivityActorOptions($logs);
    }

    private function buildSearchDisabledMessage(array $googleApiStatus): string
    {
        return match ((string) ($googleApiStatus['state'] ?? 'empty')) {
            'invalid' => __('Search is disabled because the saved Google Fonts API key is invalid. Replace it above to re-enable catalog search.', 'tasty-fonts'),
            'unknown' => __('Search is unavailable until the saved Google Fonts API key is validated. Re-save or replace the key above to continue.', 'tasty-fonts'),
            default => __('Add a Google Fonts API key above to enable search, or use manual import below.', 'tasty-fonts'),
        };
    }

    private function buildLocalEnvironmentNotice(array $settings): array
    {
        return $this->pageContextBuilder->buildLocalEnvironmentNotice($settings);
    }

    private function applyLocalEnvironmentNoticeAction(string $action): string
    {
        return match ($action) {
            'remind_tomorrow' => $this->snoozeLocalEnvironmentNotice(DAY_IN_SECONDS, __('Local environment reminder hidden until tomorrow.', 'tasty-fonts')),
            'remind_week' => $this->snoozeLocalEnvironmentNotice(7 * DAY_IN_SECONDS, __('Local environment reminder hidden for 1 week.', 'tasty-fonts')),
            'dismiss_forever' => $this->dismissLocalEnvironmentNoticeForever(),
            default => '',
        };
    }

    private function snoozeLocalEnvironmentNotice(int $duration, string $message): string
    {
        $this->saveLocalEnvironmentNoticePreference([
            'hidden_until' => time() + max(0, $duration),
            'dismissed_forever' => false,
        ]);

        return $message;
    }

    private function dismissLocalEnvironmentNoticeForever(): string
    {
        $this->saveLocalEnvironmentNoticePreference([
            'hidden_until' => 0,
            'dismissed_forever' => true,
        ]);

        return __('Local environment reminder hidden permanently for this account.', 'tasty-fonts');
    }

    private function getLocalEnvironmentNoticePreference(): array
    {
        $preferences = get_option(self::LOCAL_ENV_NOTICE_OPTION, []);
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

    private function saveLocalEnvironmentNoticePreference(array $preference): void
    {
        $userId = max(0, (int) get_current_user_id());

        if ($userId <= 0) {
            return;
        }

        $preferences = get_option(self::LOCAL_ENV_NOTICE_OPTION, []);
        $preferences = is_array($preferences) ? $preferences : [];
        $normalized = [
            'hidden_until' => max(0, (int) ($preference['hidden_until'] ?? 0)),
            'dismissed_forever' => !empty($preference['dismissed_forever']),
        ];

        if (empty($normalized['dismissed_forever']) && (int) $normalized['hidden_until'] === 0) {
            unset($preferences[$userId]);
        } else {
            $preferences[$userId] = $normalized;
        }

        update_option(self::LOCAL_ENV_NOTICE_OPTION, $preferences, false);
    }

    private function buildPreviewContext(array $settings): array
    {
        return $this->pageContextBuilder->buildPreviewContext($settings);
    }

    private function buildAdobeAccessContext(): array
    {
        return $this->pageContextBuilder->buildAdobeAccessContext();
    }

    private function buildGoogleAccessContext(): array
    {
        return $this->pageContextBuilder->buildGoogleAccessContext();
    }

    private function buildSelectableFamilyNames(array $catalog): array
    {
        return $this->pageContextBuilder->buildSelectableFamilyNames($catalog);
    }

    private function buildRoleDeliverySummary(array $roles, ?array $settings = null): string
    {
        return $this->pageContextBuilder->buildRoleDeliverySummary($roles, $settings);
    }

    private function buildRoleTextSummary(array $roles, ?array $settings = null): string
    {
        return $this->pageContextBuilder->buildRoleTextSummary($roles, $settings);
    }

    private function sanitizeRoleValues(array $roleValues): array
    {
        $sanitized = [];

        foreach (['heading', 'body', 'monospace'] as $roleKey) {
            if (!array_key_exists($roleKey, $roleValues)) {
                continue;
            }

            $sanitized[$roleKey] = sanitize_text_field((string) $roleValues[$roleKey]);
        }

        foreach (
            [
                'heading_fallback' => 'sans-serif',
                'body_fallback' => 'sans-serif',
                'monospace_fallback' => 'monospace',
            ] as $roleKey => $defaultFallback
        ) {
            if (!array_key_exists($roleKey, $roleValues)) {
                continue;
            }

            $sanitized[$roleKey] = FontUtils::sanitizeFallback((string) $roleValues[$roleKey] ?: $defaultFallback);
        }

        return $sanitized;
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

    private function resolveRateLimitedSearch(string $provider, string $query, callable $resolver): array|WP_Error
    {
        $provider = strtolower(trim($provider));
        $query = sanitize_text_field($query);
        $cooldownKey = $this->getSearchCooldownTransientKey();
        $cacheKey = $this->getSearchCacheTransientKey($provider, $query);

        if ($this->isSearchCooldownActive($cooldownKey, $provider, $query)) {
            $cached = get_transient($cacheKey);

            if (is_array($cached) || $cached instanceof WP_Error) {
                return $cached;
            }
        }

        $result = $resolver($query);

        set_transient($cacheKey, $result, self::SEARCH_CACHE_TTL);
        set_transient(
            $cooldownKey,
            [
                'provider' => $provider,
                'query' => $query,
                'expires_at' => microtime(true) + self::SEARCH_COOLDOWN_WINDOW_SECONDS,
            ],
            self::SEARCH_COOLDOWN_TRANSIENT_TTL
        );

        return $result;
    }

    private function saveFamilyFontDisplaySelection(string $family, string $display): array
    {
        $this->settings->saveFamilyFontDisplay($family, $display);

        $settings = $this->settings->getSettings();
        $savedDisplay = $this->settings->getFamilyFontDisplay($family);
        $effectiveDisplay = $savedDisplay !== ''
            ? $savedDisplay
            : (string) ($settings['font_display'] ?? 'optional');

        $this->assets->refreshGeneratedAssets(false);

        $message = $this->buildFamilyFontDisplaySavedMessage($family, $savedDisplay, $effectiveDisplay);
        $this->log->add($message);

        return [
            'font_display' => $savedDisplay === '' ? 'inherit' : $savedDisplay,
            'effective_font_display' => $effectiveDisplay,
            'message' => $message,
        ];
    }

    private function sanitizeVariantTokens(array $variantTokens): array
    {
        return array_values(
            array_filter(
                array_map(
                    static fn (mixed $token): string => sanitize_text_field((string) $token),
                    $variantTokens
                ),
                'strlen'
            )
        );
    }

    private function normalizeDeliveryMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return in_array($mode, ['self_hosted', 'cdn'], true) ? $mode : 'self_hosted';
    }

    private function normalizeFamilyFontDisplay(string $display): string
    {
        $display = strtolower(trim($display));

        if ($display === 'inherit') {
            return 'inherit';
        }

        return $this->isSupportedFontDisplay($display) ? $display : 'inherit';
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
        $value = $this->getPostedText($key);

        if (trim($value) === '') {
            return $default;
        }

        return FontUtils::sanitizeFallback($value);
    }

    private function getPostedFamilyFontDisplay(string $key, string $default = 'inherit'): string
    {
        return $this->normalizeFamilyFontDisplay($this->getPostedText($key, $default));
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

    private function buildFamilyFontDisplaySavedMessage(string $family, string $savedDisplay, string $effectiveDisplay): string
    {
        if ($savedDisplay === '') {
            return sprintf(
                __('Font display for %1$s now inherits the global default (%2$s).', 'tasty-fonts'),
                $family,
                $this->formatFontDisplayLabel($effectiveDisplay)
            );
        }

        return sprintf(
            __('Saved font-display for %1$s: %2$s.', 'tasty-fonts'),
            $family,
            $this->formatFontDisplayLabel($effectiveDisplay)
        );
    }

    private function isSupportedFontDisplay(string $display): bool
    {
        return in_array($display, ['auto', 'block', 'swap', 'fallback', 'optional'], true);
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

    private function getSearchCooldownTransientKey(): string
    {
        return self::SEARCH_COOLDOWN_TRANSIENT_PREFIX . max(0, (int) get_current_user_id());
    }

    private function getSearchCacheTransientKey(string $provider, string $query): string
    {
        return self::SEARCH_CACHE_TRANSIENT_PREFIX
            . strtolower(trim($provider))
            . '_'
            . max(0, (int) get_current_user_id())
            . '_'
            . md5(strtolower(trim($query)));
    }

    private function isSearchCooldownActive(string $cooldownKey, string $provider, string $query): bool
    {
        $cooldown = get_transient($cooldownKey);

        if (!is_array($cooldown)) {
            return false;
        }

        $expiresAt = (float) ($cooldown['expires_at'] ?? 0);

        if ($expiresAt <= microtime(true)) {
            delete_transient($cooldownKey);
            return false;
        }

        return strtolower(trim((string) ($cooldown['provider'] ?? ''))) === strtolower(trim($provider))
            && strtolower(trim((string) ($cooldown['query'] ?? ''))) === strtolower(trim($query));
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

    private function buildNoticeMessage(string $key): string
    {
        return match ($key) {
            'settings_saved' => __('Plugin settings saved.', 'tasty-fonts'),
            'adobe_project_saved' => __('Adobe Fonts project saved.', 'tasty-fonts'),
            'adobe_project_removed' => __('Adobe Fonts project removed.', 'tasty-fonts'),
            'adobe_project_resynced' => __('Adobe Fonts project resynced.', 'tasty-fonts'),
            'google_key_saved' => __('Google Fonts API key saved and validated.', 'tasty-fonts'),
            'google_key_cleared' => __('Google Fonts API key removed.', 'tasty-fonts'),
            'fallback_saved' => __('Font fallback saved.', 'tasty-fonts'),
            'roles_saved' => __('Font roles saved.', 'tasty-fonts'),
            'rescan' => __('Fonts rescanned.', 'tasty-fonts'),
            'log_cleared' => __('Activity log cleared.', 'tasty-fonts'),
            'family_deleted' => __('Font family deleted.', 'tasty-fonts'),
            'variant_deleted' => __('Font variant deleted.', 'tasty-fonts'),
            default => '',
        };
    }

    private function redirectWithNoticeKey(string $key): never
    {
        $message = $this->buildNoticeMessage($key);

        if ($message === '') {
            $this->redirect();
        }

        $this->redirectWithSuccess($message);
    }

    private function buildAdminPageUrl(): string
    {
        return add_query_arg(
            array_merge(
                ['page' => self::MENU_SLUG],
                $this->buildTrackedUiQueryArgs()
            ),
            admin_url('admin.php')
        );
    }

    private function getQueryText(string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $_GET)) {
            return $default;
        }

        $rawValue = wp_unslash($_GET[$key]);

        return is_scalar($rawValue) ? sanitize_text_field((string) $rawValue) : $default;
    }

    private function buildTrackedUiQueryArgs(): array
    {
        $args = [];
        $advancedOpen = $this->getQueryText('tf_advanced') === '1';
        $studio = $this->getAllowedTrackedUiTabValue('tf_studio');
        $preview = $this->getAllowedTrackedUiTabValue('tf_preview');
        $output = $this->getAllowedTrackedUiTabValue('tf_output');
        $addFontsOpen = $this->getQueryText('tf_add_fonts') === '1';
        $source = $this->getAllowedTrackedUiTabValue('tf_source');
        $googleAccessOpen = $this->getQueryText('tf_google_access') === '1';
        $adobeProjectOpen = $this->getQueryText('tf_adobe_project') === '1';

        if ($advancedOpen) {
            $args['tf_advanced'] = '1';

            if ($studio !== '') {
                $args['tf_studio'] = $studio;
            }

            if ($studio === 'preview' && $preview !== '') {
                $args['tf_preview'] = $preview;
            }

            if ($studio === 'snippets' && $output !== '') {
                $args['tf_output'] = $output;
            }
        }

        if ($addFontsOpen) {
            $args['tf_add_fonts'] = '1';

            if ($source !== '') {
                $args['tf_source'] = $source;
            }

            if ($source === 'google' && $googleAccessOpen) {
                $args['tf_google_access'] = '1';
            }

            if ($source === 'adobe' && $adobeProjectOpen) {
                $args['tf_adobe_project'] = '1';
            }
        }

        return $args;
    }

    private function getAllowedTrackedUiTabValue(string $key): string
    {
        $value = $this->getQueryText($key);

        if ($value === '') {
            return '';
        }

        return match ($key) {
            'tf_studio' => in_array($value, ['preview', 'snippets', 'generated', 'system', 'output-settings', 'plugin-behavior'], true) ? $value : '',
            'tf_preview' => in_array($value, ['editorial', 'card', 'reading', 'interface', 'code'], true) ? $value : '',
            'tf_output' => in_array($value, ['usage', 'variables', 'stacks', 'names'], true) ? $value : '',
            'tf_source' => in_array($value, ['google', 'bunny', 'adobe', 'upload'], true) ? $value : '',
            default => '',
        };
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
