<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Admin\Renderer\ToolsSectionRenderer;
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
use TastyFonts\Integrations\AcssIntegrationService;
use TastyFonts\Integrations\BricksIntegrationService;
use TastyFonts\Integrations\OxygenIntegrationService;
use TastyFonts\Maintenance\DeveloperToolsService;
use TastyFonts\Maintenance\SiteTransferService;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\SiteEnvironment;
use TastyFonts\Support\Storage;
use TastyFonts\Support\TransientKey;
use TastyFonts\Updates\GitHubUpdater;
use WP_Error;

/**
 * @phpstan-import-type PageContext from AdminPageContextBuilder
 * @phpstan-import-type OutputPanel from AdminPageContextBuilder
 * @phpstan-import-type CatalogFamily from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type CatalogCounts from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type CatalogMap from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type AxesMap from \TastyFonts\Support\FontUtils
 * @phpstan-import-type FamilyFallbackMap from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type FamilyFontDisplayMap from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type NormalizedSettings from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type RoleSet from \TastyFonts\Repository\SettingsRepository
 * @phpstan-type Payload array<string, mixed>
 * @phpstan-type PayloadList list<array<string, mixed>>
 * @phpstan-type UploadRow array<string, mixed>
 * @phpstan-type UploadRowList list<UploadRow>
 * @phpstan-type SettingsInput array<string, mixed>
 * @phpstan-type SiteTransferStatus array<string, mixed>
 */
final class AdminController
{
    public const MENU_SLUG = 'tasty-custom-fonts';
    public const MENU_SLUG_LIBRARY = 'tasty-custom-fonts-library';
    public const MENU_SLUG_SETTINGS = 'tasty-custom-fonts-settings';
    public const MENU_SLUG_DIAGNOSTICS = 'tasty-custom-fonts-diagnostics';
    public const PAGE_ROLES = 'roles';
    public const PAGE_LIBRARY = 'library';
    public const PAGE_SETTINGS = 'settings';
    public const PAGE_DIAGNOSTICS = 'diagnostics';
    private const ACTION_DOWNLOAD_GENERATED_CSS = 'tasty_fonts_download_generated_css';
    private const ACTION_DOWNLOAD_SITE_TRANSFER_BUNDLE = 'tasty_fonts_download_site_transfer_bundle';
    private const ACTION_IMPORT_SITE_TRANSFER_BUNDLE = 'tasty_fonts_import_site_transfer_bundle';
    private const IMPORT_SITE_TRANSFER_FILE_FIELD = 'tasty_fonts_site_transfer_bundle';
    private const IMPORT_SITE_TRANSFER_STAGE_TOKEN_FIELD = 'tasty_fonts_site_transfer_stage_token';
    private const IMPORT_SITE_TRANSFER_GOOGLE_API_KEY_FIELD = 'tasty_fonts_import_google_api_key';
    public const LOCAL_ENV_NOTICE_OPTION = 'tasty_fonts_local_environment_notice_preferences';
    private const LOCAL_ENV_NOTICE_FORM_FIELD = 'tasty_fonts_local_environment_notice';
    private const LOCAL_ENV_NOTICE_ACTION_FIELD = 'tasty_fonts_local_environment_notice_action';
    private const NOTICE_TTL = 300;
    public const NOTICE_TRANSIENT_PREFIX = 'tasty_fonts_admin_notices_';
    private const SITE_TRANSFER_STATUS_TRANSIENT_PREFIX = 'tasty_fonts_site_transfer_status_';
    private const SEARCH_CACHE_TTL = 900;
    public const SEARCH_CACHE_TRANSIENT_PREFIX = 'tasty_fonts_search_cache_';
    public const SEARCH_COOLDOWN_TRANSIENT_PREFIX = 'tasty_fonts_search_cooldown_';
    private const SEARCH_COOLDOWN_WINDOW_SECONDS = 0.5;
    private const SEARCH_COOLDOWN_TRANSIENT_TTL = 1;
    public const ACTION_COOLDOWN_TRANSIENT_PREFIX = 'tasty_fonts_action_cooldown_';
    private const ACTION_COOLDOWN_DEFAULT_WINDOW_SECONDS = 2.0;
    private const SETTINGS_STUDIO_TABS = ['output-settings', 'integrations', 'plugin-behavior', 'transfer', 'developer'];
    private readonly AdminPageRenderer $renderer;
    private readonly AdminPageContextBuilder $pageContextBuilder;
    private readonly AdminAccessService $adminAccess;

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
        private readonly GoogleImportService $googleImport,
        private readonly AcssIntegrationService $acssIntegration,
        private readonly BricksIntegrationService $bricksIntegration,
        private readonly OxygenIntegrationService $oxygenIntegration,
        private readonly DeveloperToolsService $developerTools,
        private readonly SiteTransferService $siteTransfer,
        private readonly ?GitHubUpdater $updater = null,
        ?AdminAccessService $adminAccess = null
    ) {
        $this->renderer = new AdminPageRenderer($this->storage);
        $this->adminAccess = $adminAccess ?? new AdminAccessService($this->settings);
        $this->pageContextBuilder = new AdminPageContextBuilder(
            $this->storage,
            $this->settings,
            $this->log,
            $this->catalog,
            $this->assets,
            $this->cssBuilder,
            $this->adobe,
            $this->googleClient,
            $this->acssIntegration,
            $this->bricksIntegration,
            $this->oxygenIntegration,
            $this->updater
        );
    }

    public function registerMenu(): void
    {
        if (!$this->adminAccess->canCurrentUserAccess()) {
            return;
        }

        $capability = $this->adminAccess->menuRegistrationCapability();

        add_menu_page(
            __('Tasty Custom Fonts', 'tasty-fonts'),
            __('Tasty Fonts', 'tasty-fonts'),
            $capability,
            self::MENU_SLUG,
            [$this, 'renderPage'],
            TASTY_FONTS_URL . 'assets/images/tasty-sidebar-icon.svg',
            999
        );

        add_submenu_page(
            '',
            __('Font Library', 'tasty-fonts'),
            __('Font Library', 'tasty-fonts'),
            $capability,
            self::MENU_SLUG_LIBRARY,
            [$this, 'renderPage']
        );

        add_submenu_page(
            '',
            __('Settings', 'tasty-fonts'),
            __('Settings', 'tasty-fonts'),
            $capability,
            self::MENU_SLUG_SETTINGS,
            [$this, 'renderPage']
        );

        add_submenu_page(
            '',
            __('Advanced Tools', 'tasty-fonts'),
            __('Advanced Tools', 'tasty-fonts'),
            $capability,
            self::MENU_SLUG_DIAGNOSTICS,
            [$this, 'renderPage']
        );
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        $currentPageSlug = $this->getCurrentPageSlug();

        if (!self::isPluginAdminHook($hookSuffix) && !self::isPluginPageSlug($currentPageSlug)) {
            return;
        }

        if (!$this->adminAccess->canCurrentUserAccess()) {
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
        $variableFontsEnabled = !empty($settings['variable_fonts_enabled']);

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

        if (function_exists('wp_style_add_data')) {
            wp_style_add_data('tasty-fonts-admin', 'rtl', 'replace');
        }

        wp_enqueue_script(
            'tasty-fonts-admin-contracts',
            TASTY_FONTS_URL . 'assets/js/admin-contracts.js',
            [],
            $this->assetVersionFor('assets/js/admin-contracts.js'),
            true
        );

        wp_enqueue_script(
            'tasty-fonts-admin',
            TASTY_FONTS_URL . 'assets/js/admin.js',
            ['wp-i18n', 'tasty-fonts-admin-contracts'],
            $this->assetVersionFor('assets/js/admin.js'),
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
                'currentPage' => $this->resolveRequestedPageType(),
                'trainingWheelsOff' => !empty($settings['training_wheels_off']),
                'monospaceRoleEnabled' => !empty($settings['monospace_role_enabled']),
                'variableFontsEnabled' => $variableFontsEnabled,
                'roleFamilyCatalog' => $this->buildRoleFamilyCatalog($catalog),
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
        if (!is_admin() || !$this->adminAccess->canCurrentUserAccess()) {
            return;
        }

        if ($this->handleOversizedSiteTransferUploadRequest()) {
            return;
        }

        if ($this->handleDownloadGeneratedCssAction()) {
            return;
        }

        if ($this->handleDownloadSiteTransferBundleAction()) {
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

        if ($this->handleResetPluginSettingsAction()) {
            return;
        }

        if ($this->handleWipeManagedFontLibraryAction()) {
            return;
        }

        if ($this->handleClearPluginCachesAction()) {
            return;
        }

        if ($this->handleRegenerateCssAction()) {
            return;
        }

        if ($this->handleResetIntegrationDetectionStateAction()) {
            return;
        }

        if ($this->handleResetSuppressedNoticesAction()) {
            return;
        }

        if ($this->handleImportSiteTransferBundleAction()) {
            return;
        }

        if ($this->handleReinstallUpdateChannelAction()) {
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

    /**
     * @return Payload|WP_Error
     */
    public function searchGoogle(string $query): array|WP_Error
    {
        return $this->resolveRateLimitedSearch(
            'google',
            $query,
            fn (string $resolvedQuery): array => ['items' => $this->googleClient->searchFamilies(sanitize_text_field($resolvedQuery), 20)]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function searchBunny(string $query): array|WP_Error
    {
        return $this->resolveRateLimitedSearch(
            'bunny',
            $query,
            fn (string $resolvedQuery): array => $this->searchBunnyResults($resolvedQuery)
        );
    }

    /**
     * @return Payload|WP_Error
     */
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

    /**
     * @return Payload
     */
    public function searchBunnyResults(string $query): array
    {
        return ['items' => $this->bunnyClient->searchFamilies(sanitize_text_field($query), 12)];
    }

    /**
     * @return Payload|WP_Error
     */
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

        return $this->runRateLimitedAction(
            'google_family_fetch',
            function () use ($familyName): array|WP_Error {
                $result = $this->googleClient->getFamily($familyName);

                if ($result === null) {
                    return new WP_Error(
                        'tasty_fonts_google_family_not_found',
                        __('No Google Fonts family matched that name.', 'tasty-fonts')
                    );
                }

                return ['item' => $result];
            }
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function fetchBunnyFamily(string $familyName): array|WP_Error
    {
        $familyName = sanitize_text_field($familyName);

        if ($familyName === '') {
            return new WP_Error(
                'tasty_fonts_missing_bunny_family',
                __('A Bunny Fonts family name is required.', 'tasty-fonts')
            );
        }

        return $this->runRateLimitedAction(
            'bunny_family_fetch',
            function () use ($familyName): array|WP_Error {
                $result = $this->bunnyClient->getFamily($familyName);

                if ($result === null) {
                    return new WP_Error(
                        'tasty_fonts_bunny_family_not_found',
                        __('No Bunny Fonts family matched that name.', 'tasty-fonts')
                    );
                }

                return ['item' => $result];
            }
        );
    }

    /**
     * @param list<string> $variantTokens
     * @return Payload|WP_Error
     */
    public function importGoogleFamily(
        string $familyName,
        array $variantTokens,
        string $deliveryMode = 'self_hosted',
        string $formatMode = 'static'
    ): array|WP_Error
    {
        $familyName = sanitize_text_field($familyName);
        $variants = $this->sanitizeVariantTokens($variantTokens);
        $deliveryMode = $this->normalizeDeliveryMode($deliveryMode);
        $formatMode = $this->normalizeFormatMode($formatMode);

        return $this->runRateLimitedAction(
            'google_import',
            fn (): array|WP_Error => $this->googleImport->importFamily(
                $familyName,
                $variants,
                $deliveryMode,
                $formatMode
            )
        );
    }

    /**
     * @param list<string> $variantTokens
     * @return Payload|WP_Error
     */
    public function importBunnyFamily(
        string $familyName,
        array $variantTokens,
        string $deliveryMode = 'self_hosted'
    ): array|WP_Error
    {
        $familyName = sanitize_text_field($familyName);
        $variants = $this->sanitizeVariantTokens($variantTokens);
        $deliveryMode = $this->normalizeDeliveryMode($deliveryMode);

        return $this->runRateLimitedAction(
            'bunny_import',
            fn (): array|WP_Error => $this->bunnyImport->importFamily(
                $familyName,
                $variants,
                $deliveryMode
            )
        );
    }

    /**
     * @param array<int|string, array<string, mixed>> $postedRows
     * @param array<string, mixed> $rawFiles
     * @return UploadRowList
     */
    public function prepareUploadRows(array $postedRows, array $rawFiles): array
    {
        $uploadedFiles = $this->normalizeUploadedFiles($rawFiles);
        $rows = [];

        foreach ($postedRows as $index => $row) {
            $axes = $this->normalizeUploadAxesInput($row['axes'] ?? []);

            $rows[] = [
                'family' => sanitize_text_field($this->stringValue($row, 'family')),
                'weight' => sanitize_text_field($this->stringValue($row, 'weight', '400')),
                'style' => sanitize_text_field($this->stringValue($row, 'style', 'normal')),
                'fallback' => sanitize_text_field($this->stringValue($row, 'fallback', 'sans-serif')),
                'is_variable' => !empty($row['is_variable']),
                'axes' => $axes,
                'variation_defaults' => FontUtils::normalizeVariationDefaults($this->mapValue($row, 'variation_defaults'), $axes),
                'file' => $uploadedFiles[$index] ?? [],
            ];
        }

        return $rows;
    }

    /**
     * @param UploadRowList $rows
     * @return Payload|WP_Error
     */
    public function uploadLocalFontRows(array $rows): array|WP_Error
    {
        if ($rows === []) {
            return new WP_Error(
                'tasty_fonts_upload_requires_rows',
                __('Add at least one upload row before submitting.', 'tasty-fonts')
            );
        }

        return $this->runRateLimitedAction(
            'local_upload',
            fn (): array|WP_Error => $this->localUpload->uploadRows($rows)
        );
    }

    /**
     * @return Payload|WP_Error
     */
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
        $this->assets->refreshGeneratedAssets(false);

        return [
            'family' => $family,
            'fallback' => $fallback,
            'stack' => FontUtils::buildFontStack($family, $fallback),
            'generated_css_panel' => $this->buildGeneratedCssPanelPayload($this->settings->getSettings()),
            'message' => sprintf(
                __('Saved fallback for %s.', 'tasty-fonts'),
                $family
            ),
        ];
    }

    /**
     * @return Payload|WP_Error
     */
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

    /**
     * @param SettingsInput $roleValues
     * @return Payload
     */
    public function saveRoleDraftValues(array $roleValues): array
    {
        $catalog = $this->catalog->getCatalog();
        $availableFamilies = $this->buildSelectableFamilyNames($catalog);
        $settings = $this->settings->getSettings();

        if (!empty($settings['auto_apply_roles'])) {
            $this->settings->ensureAppliedRolesInitialized($availableFamilies);
        }

        $roles = $this->settings->saveRoles(
            $this->sanitizeRoleValues($roleValues, $catalog),
            $availableFamilies
        );

        $settings = $this->settings->getSettings();
        $applyEverywhere = !empty($settings['auto_apply_roles']);
        $appliedRoles = $this->settings->getAppliedRoles($availableFamilies);
        $this->library->syncLiveRolePublishStates($appliedRoles, $applyEverywhere);

        $this->assets->refreshGeneratedAssets(false, false);

        $message = $this->buildRolesSavedMessage('save', $roles, $appliedRoles, $applyEverywhere);
        $this->log->add($message, $this->buildTransferLogContext(LogRepository::EVENT_SITE_TRANSFER_IMPORT_SUCCESS));

        return [
            'message' => $message,
            'roles' => $roles,
            'applied_roles' => $appliedRoles,
            'role_deployment' => $this->buildRoleDeploymentContext($roles, $appliedRoles, $applyEverywhere, $settings),
        ];
    }

    /**
     * @param SettingsInput $submittedValues
     * @return Payload|WP_Error
     */
    public function saveSettingsValues(array $submittedValues): array|WP_Error
    {
        $previousSettings = $this->settings->getSettings();
        $submittedGoogleKey = sanitize_text_field($this->stringValue($submittedValues, 'google_api_key'));
        $clearGoogleKey = !empty($submittedValues['tasty_fonts_clear_google_api_key']);
        $settingsInput = $this->buildSettingsSaveInput($submittedValues);
        $settingsInput = $this->preserveUnavailableIntegrationSettings($settingsInput);
        $settingsInput = $this->applyBricksThemeStyleActions($settingsInput, $submittedValues);

        if (!empty($submittedValues['bricks_reset_integration'])) {
            return $this->resetBricksIntegration($previousSettings, $settingsInput);
        }

        if (!empty($submittedValues['bricks_delete_theme_style'])) {
            return $this->deleteManagedBricksThemeStyle($previousSettings, $settingsInput);
        }

        $unicodeRangeValidation = $this->validateUnicodeRangeSettingsInput($settingsInput, $previousSettings);

        if (is_wp_error($unicodeRangeValidation)) {
            return $unicodeRangeValidation;
        }

        $outputValidation = $this->validateOutputSettingsInput($settingsInput, $previousSettings);

        if (is_wp_error($outputValidation)) {
            return $outputValidation;
        }

        if (($settingsInput['acss_font_role_sync_enabled'] ?? null) === true) {
            $settingsInput['extended_variable_role_weight_vars_enabled'] = true;
        }

        $savedSettings = $this->settings->saveSettings($settingsInput);
        $variableFontsToggled = !empty($previousSettings['variable_fonts_enabled']) !== !empty($savedSettings['variable_fonts_enabled']);

        $this->googleClient->clearCatalogCache();

        if ($variableFontsToggled) {
            $this->catalog->invalidate();
        }

        if (($previousSettings['monospace_role_enabled'] ?? false) !== ($savedSettings['monospace_role_enabled'] ?? false)) {
            $availableFamilies = $this->buildSelectableFamilyNames($this->catalog->getCatalog());
            $sitewideEnabled = !empty($savedSettings['auto_apply_roles']);
            $liveRoles = $sitewideEnabled ? $this->settings->getAppliedRoles($availableFamilies) : [];
            $this->library->syncLiveRolePublishStates($liveRoles, $sitewideEnabled);
        }

        if ($this->settingsChangeRequiresAssetRefresh($previousSettings, $savedSettings)) {
            $this->assets->refreshGeneratedAssets(false, false);
        }

        $integrationMessage = $this->syncAcssIntegrationAfterSettingsSave($previousSettings, $savedSettings, $settingsInput);

        if (is_wp_error($integrationMessage)) {
            return $integrationMessage;
        }

        $bricksIntegrationMessage = $this->syncBricksIntegrationAfterSettingsSave($previousSettings, $savedSettings, $settingsInput);

        if (is_wp_error($bricksIntegrationMessage)) {
            return $bricksIntegrationMessage;
        }

        $savedSettings = $this->settings->getSettings();
        $reloadRequired = $this->settingsChangeRequiresReload($previousSettings, $savedSettings);
        $catalog = $this->catalog->getCatalog();
        $availableFamilies = $this->buildSelectableFamilyNames($catalog);
        $roles = $this->settings->getRoles($availableFamilies);
        $appliedRoles = $this->settings->getAppliedRoles($availableFamilies);
        $outputPanels = $this->buildOutputPanels($roles, $savedSettings, $catalog, $appliedRoles);

        if ($clearGoogleKey) {
            $this->settings->saveGoogleApiKeyStatus('empty');
            $message = $this->buildNoticeMessage('google_key_cleared');
            $this->log->add(__('Google Fonts API key removed.', 'tasty-fonts'));

            return [
                'message' => $message,
                'settings' => $this->settings->getSettings(),
                'reload_required' => $reloadRequired,
                'output_panels' => $outputPanels,
            ];
        }

        if ($submittedGoogleKey !== '') {
            $validation = $this->googleClient->validateApiKey($submittedGoogleKey);
            $validationState = $this->stringValue($validation, 'state', 'unknown');
            $validationMessage = $this->stringValue($validation, 'message');

            $this->settings->saveGoogleApiKeyStatus(
                $validationState,
                $validationMessage
            );

            if ($validationState === 'valid') {
                $message = $this->buildNoticeMessage('google_key_saved');
                $this->log->add(__('Google Fonts API key validated.', 'tasty-fonts'));

                return [
                    'message' => $message,
                    'settings' => $this->settings->getSettings(),
                    'reload_required' => $reloadRequired,
                    'output_panels' => $outputPanels,
                ];
            }

            $this->log->add(__('Google Fonts API key validation failed.', 'tasty-fonts'));

            return new WP_Error(
                'tasty_fonts_google_api_key_invalid',
                $validationMessage !== ''
                    ? $validationMessage
                    : __('Google Fonts API key could not be validated.', 'tasty-fonts')
            );
        }

        $settingsMessage = $this->buildSettingsSavedMessage($previousSettings, $savedSettings);

        foreach ([$integrationMessage, $bricksIntegrationMessage] as $messagePart) {
            if ($messagePart !== '') {
                $settingsMessage .= ' ' . $messagePart;
            }
        }

        $this->log->add($settingsMessage);

        return [
            'message' => $settingsMessage,
            'settings' => $savedSettings,
            'reload_required' => $reloadRequired,
            'output_panels' => $outputPanels,
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function resetPluginSettingsToDefaults(): array|WP_Error
    {
        $settings = $this->developerTools->resetPluginSettings();

        if (is_wp_error($settings)) {
            return $settings;
        }

        $message = __('Plugin settings reset to defaults. Font library preserved.', 'tasty-fonts');
        $this->log->add($message);

        return [
            'message' => $message,
            'settings' => $settings,
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function wipeManagedFontLibrary(): array|WP_Error
    {
        $settings = $this->developerTools->wipeManagedFontLibrary();

        if (is_wp_error($settings)) {
            return $settings;
        }

        $message = __('Managed font library wiped. Storage reset to an empty scaffold.', 'tasty-fonts');
        $this->log->add($message);

        return [
            'message' => $message,
            'settings' => $settings,
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function clearPluginCachesAndRegenerateAssets(): array|WP_Error
    {
        if (!$this->developerTools->clearPluginCachesAndRegenerateAssets()) {
            return new WP_Error(
                'tasty_fonts_maintenance_failed',
                __('Plugin caches were cleared, but generated assets could not be rebuilt.', 'tasty-fonts')
            );
        }

        $message = __('Plugin caches cleared and generated assets refreshed.', 'tasty-fonts');
        $this->log->add($message);

        return ['message' => $message];
    }

    /**
     * @return Payload|WP_Error
     */
    public function regenerateCss(): array|WP_Error
    {
        if (!$this->developerTools->regenerateCss()) {
            return new WP_Error(
                'tasty_fonts_regenerate_css_failed',
                __('Generated CSS could not be rebuilt.', 'tasty-fonts')
            );
        }

        $message = __('Generated CSS regenerated.', 'tasty-fonts');
        $this->log->add($message);

        return ['message' => $message];
    }

    /**
     * @return Payload
     */
    public function resetIntegrationDetectionState(): array
    {
        $settings = $this->developerTools->resetIntegrationDetectionState();
        $message = __('Integration detection state reset.', 'tasty-fonts');
        $this->log->add($message);

        return [
            'message' => $message,
            'settings' => $settings,
        ];
    }

    /**
     * @return Payload
     */
    public function resetSuppressedNotices(): array
    {
        $this->developerTools->resetSuppressedNotices();
        $message = __('Suppressed notices reset. Hidden reminders can appear again.', 'tasty-fonts');
        $this->log->add($message);

        return ['message' => $message];
    }

    /**
     * @return Payload|WP_Error
     */
    public function reinstallSelectedUpdateChannelRelease(): array|WP_Error
    {
        if (!$this->updater instanceof GitHubUpdater) {
            return new WP_Error(
                'tasty_fonts_updater_unavailable',
                __('The GitHub updater is unavailable in this environment.', 'tasty-fonts')
            );
        }

        $result = $this->updater->reinstallReleaseForChannel($this->settings->getUpdateChannel());

        if (is_wp_error($result)) {
            return $result;
        }

        $channel = $result['channel'];
        $version = $result['version'];
        $message = sprintf(
            __('Reinstalled the %1$s channel release (%2$s).', 'tasty-fonts'),
            $this->formatUpdateChannelLabel($channel),
            $version !== '' ? $version : __('latest available', 'tasty-fonts')
        );
        $this->log->add($message);

        return [
            'message' => $message,
            'channel' => $channel,
            'version' => $version,
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function exportSiteTransferBundle(): array|WP_Error
    {
        return $this->siteTransfer->buildExportBundle();
    }

    /**
     * @param array<string, mixed> $uploadedFile
     * @return Payload|WP_Error
     */
    public function stageSiteTransferBundle(array $uploadedFile): array|WP_Error
    {
        $result = $this->siteTransfer->stageImportBundle($uploadedFile);

        if (is_wp_error($result)) {
            return $result;
        }

        $familyCount = $result['families'];
        $fileCount = $result['files'];
        $message = sprintf(
            __('Dry run succeeded. Ready to import %1$d famil%2$s and %3$d file%4$s. Use Import Bundle in the top-right corner to continue.', 'tasty-fonts'),
            $familyCount,
            $familyCount === 1 ? 'y' : 'ies',
            $fileCount,
            $fileCount === 1 ? '' : 's'
        );

        return [
            'status' => 'validated',
            'message' => $message,
            'stage_token' => $result['stage_token'],
            'bundle_name' => $result['bundle_name'],
            'plugin_version' => $result['plugin_version'],
            'exported_at' => $result['exported_at'],
            'families' => $familyCount,
            'files' => $fileCount,
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function importStagedSiteTransferBundle(string $stageToken, string $freshGoogleApiKey = ''): array|WP_Error
    {
        return $this->finalizeImportedSiteTransferResult(
            $this->siteTransfer->importStagedBundle($stageToken, $freshGoogleApiKey)
        );
    }

    /**
     * @param array<string, mixed> $uploadedFile
     * @return Payload|WP_Error
     */
    public function importSiteTransferBundle(array $uploadedFile, string $freshGoogleApiKey = ''): array|WP_Error
    {
        return $this->finalizeImportedSiteTransferResult(
            $this->siteTransfer->importBundleReplacingCurrentState($uploadedFile, $freshGoogleApiKey)
        );
    }

    /**
     * @param Payload|WP_Error $result
     * @return Payload|WP_Error
     */
    private function finalizeImportedSiteTransferResult(array|WP_Error $result): array|WP_Error
    {
        if (is_wp_error($result)) {
            return $result;
        }

        $settings = FontUtils::normalizeStringKeyedMap($result['settings'] ?? null);

        if ($settings === []) {
            $settings = $this->settings->getSettings();
        }
        $messages = [];

        $acssMessage = $this->syncAcssIntegrationForRuntimeState($settings, true);

        if (is_wp_error($acssMessage)) {
            $messages[] = $acssMessage->get_error_message();
        } elseif (trim($acssMessage) !== '') {
            $messages[] = trim($acssMessage);
        }

        $bricksMessage = $this->syncBricksIntegrationForRuntimeState($settings, true);

        if (is_wp_error($bricksMessage)) {
            $messages[] = $bricksMessage->get_error_message();
        } elseif (trim($bricksMessage) !== '') {
            $messages[] = trim($bricksMessage);
        }

        $familyCount = $this->intValue($result, 'families');
        $fileCount = $this->intValue($result, 'files');
        $message = sprintf(
            __('Imported the site transfer bundle (%1$d famil%2$s, %3$d file%4$s).', 'tasty-fonts'),
            $familyCount,
            $familyCount === 1 ? 'y' : 'ies',
            $fileCount,
            $fileCount === 1 ? '' : 's'
        );

        if (!empty($result['used_fresh_google_api_key'])) {
            $message .= ' ' . __('A fresh Google Fonts API key was saved for this site.', 'tasty-fonts');
        } else {
            $message .= ' ' . __('Secrets were not imported. Add a fresh Google Fonts API key if this site needs one.', 'tasty-fonts');
        }

        if ($messages !== []) {
            $message .= ' ' . implode(' ', $messages);
        }

        $this->log->add($message);

        return [
            'message' => $message,
            'settings' => $settings,
            'roles' => $result['roles'] ?? [],
            'library' => $result['library'] ?? [],
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function saveFamilyDeliveryValue(string $familySlug, string $deliveryId): array|WP_Error
    {
        return $this->library->saveFamilyDelivery($familySlug, $deliveryId);
    }

    /**
     * @return Payload|WP_Error
     */
    public function saveFamilyPublishStateValue(string $familySlug, string $publishState): array|WP_Error
    {
        return $this->library->saveFamilyPublishState($familySlug, $publishState);
    }

    /**
     * @return Payload|WP_Error
     */
    public function deleteDeliveryProfileValue(string $familySlug, string $deliveryId): array|WP_Error
    {
        return $this->library->deleteDeliveryProfile($familySlug, $deliveryId);
    }

    /**
     * @return Payload|WP_Error
     */
    public function renderFamilyCardFragment(string $familySlug): array|WP_Error
    {
        $familySlug = FontUtils::slugify($familySlug);

        if ($familySlug === '') {
            return new WP_Error(
                'tasty_fonts_family_not_found',
                __('That font family could not be found in the library.', 'tasty-fonts')
            );
        }

        $context = array_merge(
            $this->pageContextBuilder->build(),
            ['current_page' => self::PAGE_LIBRARY]
        );
        $view = (new AdminPageViewBuilder($this->storage))->build($context);
        $catalog = FontUtils::normalizeListOfStringKeyedMaps($view['catalog'] ?? []);
        $family = null;

        foreach ($catalog as $candidate) {
            $candidateSlug = $this->stringValue($candidate, 'slug', $this->stringValue($candidate, 'family'));

            if (FontUtils::slugify($candidateSlug) !== $familySlug) {
                continue;
            }

            $family = $candidate;
            break;
        }

        if ($family === null) {
            return new WP_Error(
                'tasty_fonts_family_not_found',
                __('That font family could not be found in the library.', 'tasty-fonts')
            );
        }

        $renderer = new \TastyFonts\Admin\Renderer\FamilyCardRenderer($this->storage);
        $renderer->setTrainingWheelsOff(!empty($view['trainingWheelsOff']));

        $viewRoles = $this->sanitizeRoleValues(
            $this->payloadMapValue(FontUtils::normalizeStringKeyedMap($view['roles'] ?? null))
        );

        ob_start();
        $renderer->renderFamilyCardDetails(
            $this->payloadMapValue($family),
            $viewRoles,
            $this->stringMapValue($view['familyFallbacks'] ?? []),
            $this->stringMapValue($view['familyFontDisplays'] ?? []),
            $this->normalizePayloadList($view['familyFontDisplayOptions'] ?? []),
            $this->stringValue($view, 'previewText'),
            $this->stringMapValue($view['categoryAliasOwners'] ?? []),
            $this->payloadMapValue(FontUtils::normalizeStringKeyedMap($view['extendedVariableOptions'] ?? null)),
            !empty($view['monospaceRoleEnabled']),
            $this->payloadMapValue(FontUtils::normalizeStringKeyedMap($view['classOutputOptions'] ?? null))
        );
        $html = (string) ob_get_clean();

        return [
            'family_slug' => $familySlug,
            'html' => $html,
        ];
    }

    public function statusForError(WP_Error $error, int $defaultStatus = 400): int
    {
        return match ($error->get_error_code()) {
            'tasty_fonts_rest_action_rate_limited' => 429,
            'tasty_fonts_google_family_not_found',
            'tasty_fonts_bunny_family_not_found',
            'tasty_fonts_family_not_found',
            'tasty_fonts_delivery_not_found' => 404,
            default => $defaultStatus,
        };
    }

    public function renderPage(): void
    {
        if (!$this->adminAccess->canCurrentUserAccess()) {
            wp_die(
                __('You do not have permission to access Tasty Fonts.', 'tasty-fonts'),
                '',
                ['response' => 403]
            );
        }

        $this->initializeDetectedIntegrations();
        $this->reconcileAcssIntegrationDrift();
        $this->reconcileBricksIntegrationState();

        if ($this->shouldRedirectNonCanonicalPageRequest()) {
            wp_safe_redirect($this->buildAdminPageUrl($this->resolveRequestedPageType()));
            exit;
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

    private function handleResetPluginSettingsAction(): bool
    {
        if (!isset($_POST['tasty_fonts_reset_plugin_settings'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_reset_plugin_settings');

        $result = $this->resetPluginSettingsToDefaults();

        if (is_wp_error($result)) {
            $this->redirectWithError($result->get_error_message());
        }

        $this->redirectWithSuccess($this->stringValue($result, 'message', __('Plugin settings reset.', 'tasty-fonts')));
    }

    private function handleWipeManagedFontLibraryAction(): bool
    {
        if (!isset($_POST['tasty_fonts_wipe_managed_font_library'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_wipe_managed_font_library');

        $result = $this->wipeManagedFontLibrary();

        if (is_wp_error($result)) {
            $this->redirectWithError($result->get_error_message());
        }

        $this->redirectWithSuccess($this->stringValue($result, 'message', __('Managed font library wiped.', 'tasty-fonts')));
    }

    private function handleClearPluginCachesAction(): bool
    {
        if (!isset($_POST['tasty_fonts_clear_plugin_caches'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_clear_plugin_caches');

        $result = $this->clearPluginCachesAndRegenerateAssets();

        if (is_wp_error($result)) {
            $this->redirectWithError($result->get_error_message());
        }

        $this->redirectWithSuccess($this->stringValue($result, 'message', __('Plugin caches cleared.', 'tasty-fonts')));
    }

    private function handleRegenerateCssAction(): bool
    {
        if (!isset($_POST['tasty_fonts_regenerate_css'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_regenerate_css');

        $result = $this->regenerateCss();

        if (is_wp_error($result)) {
            $this->redirectWithError($result->get_error_message());
        }

        $this->redirectWithSuccess($this->stringValue($result, 'message', __('Generated CSS regenerated.', 'tasty-fonts')));
    }

    private function handleResetIntegrationDetectionStateAction(): bool
    {
        if (!isset($_POST['tasty_fonts_reset_integration_detection_state'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_reset_integration_detection_state');

        $result = $this->resetIntegrationDetectionState();
        $this->redirectWithSuccess($this->stringValue($result, 'message'));
    }

    private function handleResetSuppressedNoticesAction(): bool
    {
        if (!isset($_POST['tasty_fonts_reset_suppressed_notices'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_reset_suppressed_notices');

        $result = $this->resetSuppressedNotices();

        $this->redirectWithSuccess($this->stringValue($result, 'message', __('Suppressed notices reset.', 'tasty-fonts')));
    }

    private function handleImportSiteTransferBundleAction(): bool
    {
        if (!isset($_POST[self::ACTION_IMPORT_SITE_TRANSFER_BUNDLE])) {
            return false;
        }

        check_admin_referer(self::ACTION_IMPORT_SITE_TRANSFER_BUNDLE);

        $uploadedFile = is_array($_FILES[self::IMPORT_SITE_TRANSFER_FILE_FIELD] ?? null)
            ? $this->payloadMapValue($_FILES[self::IMPORT_SITE_TRANSFER_FILE_FIELD])
            : [];
        $stageToken = $this->getPostedText(self::IMPORT_SITE_TRANSFER_STAGE_TOKEN_FIELD);
        $freshGoogleApiKey = $this->getPostedText(self::IMPORT_SITE_TRANSFER_GOOGLE_API_KEY_FIELD);
        $result = $stageToken !== ''
            ? $this->importStagedSiteTransferBundle($stageToken, $freshGoogleApiKey)
            : $this->importSiteTransferBundle($uploadedFile, $freshGoogleApiKey);

        if (is_wp_error($result)) {
            $status = $this->buildSiteTransferStatusPayload($result, 'error');
            $this->queueSiteTransferStatus($status);
            $this->log->add(
                $this->formatSiteTransferStatusForActivityLog($status),
                $this->buildTransferLogContext(LogRepository::EVENT_SITE_TRANSFER_IMPORT_FAILURE)
            );
            $this->redirectWithError($this->stringValue($status, 'message', $result->get_error_message()));
        }

        $this->clearQueuedSiteTransferStatus();
        $this->redirectWithSuccess($this->stringValue($result, 'message', __('Site transfer bundle imported.', 'tasty-fonts')));
    }

    private function handleReinstallUpdateChannelAction(): bool
    {
        if (!isset($_POST['tasty_fonts_reinstall_update_channel'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_reinstall_update_channel', '_tasty_fonts_reinstall_nonce');

        $result = $this->reinstallSelectedUpdateChannelRelease();

        if (is_wp_error($result)) {
            $this->redirectWithError($result->get_error_message());
        }

        $this->redirectWithSuccess($this->stringValue($result, 'message', __('Selected channel release reinstalled.', 'tasty-fonts')));
    }

    private function handleSaveSettingsAction(): bool
    {
        if (!isset($_POST['tasty_fonts_save_settings'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_save_settings');

        $result = $this->saveSettingsValues($this->collectPostedSettingsSubmission());

        if (is_wp_error($result)) {
            $this->redirectWithError($result->get_error_message());
        }

        $this->redirectWithSuccess($this->stringValue($result, 'message', __('Plugin settings saved.', 'tasty-fonts')));
    }

    private function handleSaveFamilyFallbackAction(): bool
    {
        if (!isset($_POST['tasty_fonts_save_family_fallback'])) {
            return false;
        }

        check_admin_referer('tasty_fonts_save_family_fallback');

        $family = $this->getPostedText('tasty_fonts_family_name');
        $fallback = $this->getPostedFallback('tasty_fonts_family_fallback');

        $result = $this->saveFamilyFallbackValue($family, $fallback);

        if (is_wp_error($result)) {
            $this->redirectWithError($result->get_error_message());
        }

        $this->log->add(
            sprintf(
                __('Saved fallback for %1$s: %2$s.', 'tasty-fonts'),
                $this->stringValue($result, 'family', $family),
                $this->stringValue($result, 'fallback', $fallback)
            )
        );

        $this->redirectWithSuccess($this->stringValue($result, 'message', __('Font fallback saved.', 'tasty-fonts')));
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

        $this->redirectWithSuccess($this->stringValue($result, 'message'));
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
            $result['family'],
            $result['weight'],
            $result['style']
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
                        'heading_fallback' => isset($_POST['tasty_fonts_heading_fallback'])
                            ? $this->getPostedFallback('tasty_fonts_heading_fallback')
                            : null,
                        'body_fallback' => isset($_POST['tasty_fonts_body_fallback'])
                            ? $this->getPostedFallback('tasty_fonts_body_fallback')
                            : null,
                        'heading_weight' => isset($_POST['tasty_fonts_heading_weight']) ? $this->getPostedText('tasty_fonts_heading_weight') : null,
                        'body_weight' => isset($_POST['tasty_fonts_body_weight']) ? $this->getPostedText('tasty_fonts_body_weight') : null,
                        'heading_axes' => isset($_POST['tasty_fonts_heading_axes']) ? $this->getPostedArray('tasty_fonts_heading_axes') : null,
                        'body_axes' => isset($_POST['tasty_fonts_body_axes']) ? $this->getPostedArray('tasty_fonts_body_axes') : null,
                        'monospace' => isset($_POST['tasty_fonts_monospace_font'])
                            ? $this->getPostedText('tasty_fonts_monospace_font')
                            : null,
                        'monospace_fallback' => isset($_POST['tasty_fonts_monospace_fallback'])
                            ? $this->getPostedFallback('tasty_fonts_monospace_fallback', 'monospace')
                            : null,
                        'monospace_weight' => isset($_POST['tasty_fonts_monospace_weight']) ? $this->getPostedText('tasty_fonts_monospace_weight') : null,
                        'monospace_axes' => isset($_POST['tasty_fonts_monospace_axes']) ? $this->getPostedArray('tasty_fonts_monospace_axes') : null,
                    ],
                    static fn (mixed $value): bool => $value !== null
                ),
                $catalog
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

        $integrationMessage = '';

        if ($actionType === 'apply' || $actionType === 'disable') {
            $integrationSettings = $this->settings->getSettings();
            $integrationMessage = $this->syncAcssIntegrationForRuntimeState($integrationSettings);

            if (is_wp_error($integrationMessage)) {
                $this->redirectWithError($integrationMessage->get_error_message());
            }

            $bricksIntegrationMessage = $this->syncBricksIntegrationForRuntimeState($integrationSettings);

            if (is_wp_error($bricksIntegrationMessage)) {
                $this->redirectWithError($bricksIntegrationMessage->get_error_message());
            }

            if ($bricksIntegrationMessage !== '') {
                $integrationMessage = trim($integrationMessage . ' ' . $bricksIntegrationMessage);
            }
        }

        $this->assets->refreshGeneratedAssets(false);

        $message = $this->buildRolesSavedMessage(
            $actionType,
            $roles,
            $appliedRoles,
            $wasAppliedSitewide
        );

        if ($integrationMessage !== '') {
            $message .= ' ' . $integrationMessage;
        }

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
        if (!self::isPluginPageSlug($this->getQueryText('page', self::MENU_SLUG)) || !isset($_GET[self::ACTION_DOWNLOAD_GENERATED_CSS])) {
            return false;
        }

        check_admin_referer(self::ACTION_DOWNLOAD_GENERATED_CSS);

        $download = $this->buildGeneratedCssDownloadData($this->settings->getSettings());

        $content = (string) $download['content'];

        if (empty($download['downloadable']) || trim($content) === '') {
            $this->redirectWithError(__('Generated CSS is unavailable until sitewide delivery is on.', 'tasty-fonts'));
        }

        $filename = sanitize_file_name((string) ($download['filename'] ?? 'tasty-fonts.css'));

        header('Content-Type: text/css; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $this->byteLength($content));

        echo $content;
        exit;
    }

    private function handleDownloadSiteTransferBundleAction(): bool
    {
        if (!self::isPluginPageSlug($this->getQueryText('page', self::MENU_SLUG)) || !isset($_GET[self::ACTION_DOWNLOAD_SITE_TRANSFER_BUNDLE])) {
            return false;
        }

        check_admin_referer(self::ACTION_DOWNLOAD_SITE_TRANSFER_BUNDLE);

        $bundle = $this->exportSiteTransferBundle();

        if (is_wp_error($bundle)) {
            $this->redirectWithError($bundle->get_error_message());
        }

        $path = wp_normalize_path($this->stringValue($bundle, 'path'));

        if ($path === '' || !is_readable($path)) {
            $this->redirectWithError(__('The site transfer bundle could not be prepared for download.', 'tasty-fonts'));
        }

        $filename = sanitize_file_name($this->stringValue($bundle, 'filename', 'tasty-fonts-transfer.zip'));
        $contentType = trim($this->stringValue($bundle, 'content_type', 'application/zip'));
        $size = is_file($path) ? (int) filesize($path) : 0;
        $this->log->add(
            __('Exported a site transfer bundle.', 'tasty-fonts'),
            $this->buildTransferLogContext(LogRepository::EVENT_SITE_TRANSFER_EXPORT)
        );

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        if ($size > 0) {
            header('Content-Length: ' . $size);
        }

        readfile($path);
        @unlink($path);
        exit;
    }

    /**
     * @return PageContext
     */
    private function buildPageContext(): array
    {
        $pageType = $this->resolveRequestedPageType();
        $baseContext = $this->pageContextBuilder->build();
        $logs = $this->normalizePayloadList($baseContext['logs'] ?? []);

        return array_merge(
            $baseContext,
            [
                'current_page' => $pageType,
                'current_page_slug' => self::pageSlugForType($pageType),
                'page_urls' => [
                    self::PAGE_ROLES => $this->buildPageUrl(self::PAGE_ROLES),
                    self::PAGE_LIBRARY => $this->buildPageUrl(self::PAGE_LIBRARY),
                    self::PAGE_LIBRARY . '_add_fonts' => $this->buildPageUrl(self::PAGE_LIBRARY, [
                        'tf_add_fonts' => '1',
                        'tf_source' => 'google',
                    ]),
                    self::PAGE_SETTINGS => $this->buildPageUrl(self::PAGE_SETTINGS),
                    self::PAGE_SETTINGS . '_integrations' => $this->buildPageUrl(self::PAGE_SETTINGS, [
                        'tf_studio' => 'integrations',
                    ]),
                    self::PAGE_SETTINGS . '_behavior' => $this->buildPageUrl(self::PAGE_SETTINGS, [
                        'tf_studio' => 'plugin-behavior',
                    ]),
                    self::PAGE_SETTINGS . '_transfer' => $this->buildPageUrl(self::PAGE_SETTINGS, [
                        'tf_studio' => 'transfer',
                    ]),
                    self::PAGE_SETTINGS . '_developer' => $this->buildPageUrl(self::PAGE_SETTINGS, [
                        'tf_studio' => 'developer',
                    ]),
                    self::PAGE_DIAGNOSTICS => $this->buildPageUrl(self::PAGE_DIAGNOSTICS),
                ],
                'site_transfer' => $this->buildSiteTransferContext($logs),
            ]
        );
    }

    /**
     * @param RoleSet $roles
     * @param NormalizedSettings $settings
     * @param CatalogMap $catalog
     * @param RoleSet|array{} $appliedRoles
     * @return list<OutputPanel>
     */
    private function buildOutputPanels(
        array $roles,
        array $settings,
        array $catalog = [],
        array $appliedRoles = []
    ): array
    {
        return $this->pageContextBuilder->buildOutputPanels($roles, $settings, $catalog, $appliedRoles);
    }

    /**
     * @param NormalizedSettings $settings
     * @return array<string, mixed>
     */
    private function buildGeneratedCssPanel(array $settings): array
    {
        return $this->pageContextBuilder->buildGeneratedCssPanel($settings);
    }

    /**
     * @param NormalizedSettings $settings
     * @return array<string, mixed>
     */
    private function buildGeneratedCssPanelPayload(array $settings): array
    {
        $panel = $this->buildGeneratedCssPanel($settings);
        $toolsRenderer = new ToolsSectionRenderer($this->storage);
        $panelValue = $this->stringValue($panel, 'value');

        $panel['display_value'] = $panelValue;
        $panel['readable_display_value'] = $toolsRenderer->formatSnippetForDisplay($panelValue);

        return $panel;
    }

    /**
     * @param NormalizedSettings $settings
     * @return array<string, bool|string>
     */
    private function buildGeneratedCssDownloadData(array $settings): array
    {
        return $this->pageContextBuilder->buildGeneratedCssDownloadData($settings);
    }

    /**
     * @param PayloadList $logs
     * @return array<string, mixed>
     */
    private function buildSiteTransferContext(array $logs = []): array
    {
        $capability = $this->siteTransfer->getCapabilityStatus();
        $effectiveUploadLimitBytes = $this->getEffectiveSiteTransferUploadLimitBytes();
        $transferLogs = $this->pageContextBuilder->buildTransferLogEntries($logs);

        return [
            'available' => !empty($capability['available']),
            'message' => $capability['message'],
            'export_url' => $this->buildSiteTransferDownloadUrl(),
            'import_file_field' => self::IMPORT_SITE_TRANSFER_FILE_FIELD,
            'import_stage_token_field' => self::IMPORT_SITE_TRANSFER_STAGE_TOKEN_FIELD,
            'import_google_api_key_field' => self::IMPORT_SITE_TRANSFER_GOOGLE_API_KEY_FIELD,
            'import_action_field' => self::ACTION_IMPORT_SITE_TRANSFER_BUNDLE,
            'effective_upload_limit_bytes' => $effectiveUploadLimitBytes,
            'effective_upload_limit_label' => $effectiveUploadLimitBytes > 0 ? size_format($effectiveUploadLimitBytes) : '',
            'import_status' => $this->consumeQueuedSiteTransferStatus(),
            'logs' => $transferLogs,
            'actor_options' => $this->pageContextBuilder->buildActivityActorOptions($transferLogs),
        ];
    }

    /**
     * @param NormalizedSettings $before
     * @param NormalizedSettings $after
     */
    private function buildSettingsSavedMessage(array $before, array $after): string
    {
        $changes = [];

        if (($before['css_delivery_mode'] ?? '') !== ($after['css_delivery_mode'] ?? '')) {
            $changes[] = sprintf(
                __('delivery mode set to %s', 'tasty-fonts'),
                $this->formatCssDeliveryModeLabel($this->stringValue($after, 'css_delivery_mode', 'file'))
            );
        }

        if (($before['font_display'] ?? '') !== ($after['font_display'] ?? '')) {
            $changes[] = sprintf(
                __('font-display set to %s', 'tasty-fonts'),
                $this->stringValue($after, 'font_display', 'swap')
            );
        }

        if (($before['unicode_range_mode'] ?? FontUtils::UNICODE_RANGE_MODE_OFF) !== ($after['unicode_range_mode'] ?? FontUtils::UNICODE_RANGE_MODE_OFF)) {
            /* translators: %s is the selected unicode-range output mode label. */
            $changes[] = sprintf(
                __('unicode-range output set to %s', 'tasty-fonts'),
                $this->formatUnicodeRangeModeLabel($this->stringValue($after, 'unicode_range_mode', FontUtils::UNICODE_RANGE_MODE_OFF))
            );
        }

        if (($before['unicode_range_custom_value'] ?? '') !== ($after['unicode_range_custom_value'] ?? '')) {
            $changes[] = __('custom unicode-range updated', 'tasty-fonts');
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

        if (!empty($before['role_usage_font_weight_enabled']) !== !empty($after['role_usage_font_weight_enabled'])) {
            $changes[] = !empty($after['role_usage_font_weight_enabled'])
                ? __('role font-weight output enabled', 'tasty-fonts')
                : __('role font-weight output disabled', 'tasty-fonts');
        }

        if (!empty($before['minimal_output_preset_enabled']) !== !empty($after['minimal_output_preset_enabled'])) {
            $changes[] = !empty($after['minimal_output_preset_enabled'])
                ? __('minimal output preset enabled', 'tasty-fonts')
                : __('minimal output preset disabled', 'tasty-fonts');
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

        if (($before['update_channel'] ?? SettingsRepository::UPDATE_CHANNEL_STABLE) !== ($after['update_channel'] ?? SettingsRepository::UPDATE_CHANNEL_STABLE)) {
            $changes[] = sprintf(
                __('update channel set to %s', 'tasty-fonts'),
                $this->formatUpdateChannelLabel($this->stringValue($after, 'update_channel', SettingsRepository::UPDATE_CHANNEL_STABLE))
            );
        }

        if ($this->adminAccessSettingsDiffer($before, $after)) {
            $changes[] = __('admin access updated', 'tasty-fonts');
        }

        if (!empty($before['block_editor_font_library_sync_enabled']) !== !empty($after['block_editor_font_library_sync_enabled'])) {
            $changes[] = !empty($after['block_editor_font_library_sync_enabled'])
                ? __('Block Editor Font Library sync enabled', 'tasty-fonts')
                : __('Block Editor Font Library sync disabled', 'tasty-fonts');
        }

        if (($before['bricks_integration_enabled'] ?? null) !== ($after['bricks_integration_enabled'] ?? null)) {
            $changes[] = ($after['bricks_integration_enabled'] ?? null) === true
                ? __('Bricks integration enabled', 'tasty-fonts')
                : __('Bricks integration disabled', 'tasty-fonts');
        }

        foreach (
            [
                'bricks_theme_styles_sync_enabled' => __('Bricks Theme Style sync', 'tasty-fonts'),
                'bricks_disable_google_fonts_enabled' => __('Bricks Google fonts control', 'tasty-fonts'),
            ] as $field => $label
        ) {
            if (!empty($before[$field]) === !empty($after[$field])) {
                continue;
            }

            $changes[] = !empty($after[$field])
                ? sprintf(__('%s enabled', 'tasty-fonts'), $label)
                : sprintf(__('%s disabled', 'tasty-fonts'), $label);
        }

        if (($before['bricks_theme_style_target_mode'] ?? BricksIntegrationService::TARGET_MODE_MANAGED) !== ($after['bricks_theme_style_target_mode'] ?? BricksIntegrationService::TARGET_MODE_MANAGED)) {
            $changes[] = __('Bricks Theme Style mode updated', 'tasty-fonts');
        }

        if (($before['bricks_theme_style_target_id'] ?? 'managed') !== ($after['bricks_theme_style_target_id'] ?? 'managed')) {
            $changes[] = __('Bricks Theme Style target updated', 'tasty-fonts');
        }

        if (($before['oxygen_integration_enabled'] ?? null) !== ($after['oxygen_integration_enabled'] ?? null)) {
            $changes[] = ($after['oxygen_integration_enabled'] ?? null) === true
                ? __('Oxygen integration enabled', 'tasty-fonts')
                : __('Oxygen integration disabled', 'tasty-fonts');
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

        if (!empty($before['variable_fonts_enabled']) !== !empty($after['variable_fonts_enabled'])) {
            $changes[] = !empty($after['variable_fonts_enabled'])
                ? __('variable font support enabled', 'tasty-fonts')
                : __('variable font support disabled', 'tasty-fonts');
        }

        if (($before['acss_font_role_sync_enabled'] ?? null) !== ($after['acss_font_role_sync_enabled'] ?? null)) {
            $changes[] = ($after['acss_font_role_sync_enabled'] ?? null) === true
                ? __('Automatic.css font sync enabled', 'tasty-fonts')
                : __('Automatic.css font sync disabled', 'tasty-fonts');
        }

        if (($before['preview_sentence'] ?? '') !== ($after['preview_sentence'] ?? '')) {
            $changes[] = __('preview text updated', 'tasty-fonts');
        }

        if (!empty($before['delete_uploaded_files_on_uninstall']) !== !empty($after['delete_uploaded_files_on_uninstall'])) {
            $changes[] = !empty($after['delete_uploaded_files_on_uninstall'])
                ? __('uninstall file cleanup enabled', 'tasty-fonts')
                : __('uninstall file cleanup disabled', 'tasty-fonts');
        }

        $message = $changes === []
            ? __('Plugin settings saved.', 'tasty-fonts')
            : sprintf(
                __('Plugin settings saved: %s.', 'tasty-fonts'),
                implode(', ', $changes)
            );

        if ($this->settingsChangeRequiresReload($before, $after)) {
            $message .= ' ' . __('Reload the page to apply this change.', 'tasty-fonts');
        }

        return $message;
    }

    /**
     * @param NormalizedSettings $before
     * @param NormalizedSettings $after
     */
    private function settingsChangeRequiresAssetRefresh(array $before, array $after): bool
    {
        return ($before['css_delivery_mode'] ?? 'file') !== ($after['css_delivery_mode'] ?? 'file')
            || ($before['font_display'] ?? 'swap') !== ($after['font_display'] ?? 'swap')
            || ($before['unicode_range_mode'] ?? FontUtils::UNICODE_RANGE_MODE_OFF) !== ($after['unicode_range_mode'] ?? FontUtils::UNICODE_RANGE_MODE_OFF)
            || ($before['unicode_range_custom_value'] ?? '') !== ($after['unicode_range_custom_value'] ?? '')
            || !empty($before['class_output_enabled']) !== !empty($after['class_output_enabled'])
            || $this->classOutputSubsettingsDiffer($before, $after)
            || !empty($before['minify_css_output']) !== !empty($after['minify_css_output'])
            || !empty($before['role_usage_font_weight_enabled']) !== !empty($after['role_usage_font_weight_enabled'])
            || !empty($before['minimal_output_preset_enabled']) !== !empty($after['minimal_output_preset_enabled'])
            || !empty($before['per_variant_font_variables_enabled']) !== !empty($after['per_variant_font_variables_enabled'])
            || $this->extendedVariableSubsettingsDiffer($before, $after)
            || !empty($before['monospace_role_enabled']) !== !empty($after['monospace_role_enabled'])
            || !empty($before['variable_fonts_enabled']) !== !empty($after['variable_fonts_enabled']);
    }

    /**
     * @param NormalizedSettings $before
     * @param NormalizedSettings $after
     */
    private function settingsChangeRequiresReload(array $before, array $after): bool
    {
        return !empty($before['training_wheels_off']) !== !empty($after['training_wheels_off'])
            || !empty($before['monospace_role_enabled']) !== !empty($after['monospace_role_enabled'])
            || !empty($before['variable_fonts_enabled']) !== !empty($after['variable_fonts_enabled'])
            || $this->adminAccessSettingsDiffer($before, $after)
            || ($before['update_channel'] ?? SettingsRepository::UPDATE_CHANNEL_STABLE) !== ($after['update_channel'] ?? SettingsRepository::UPDATE_CHANNEL_STABLE)
            || !empty($before['block_editor_font_library_sync_enabled']) !== !empty($after['block_editor_font_library_sync_enabled'])
            || ($before['bricks_integration_enabled'] ?? null) !== ($after['bricks_integration_enabled'] ?? null)
            || !empty($before['bricks_theme_styles_sync_enabled']) !== !empty($after['bricks_theme_styles_sync_enabled'])
            || ($before['bricks_theme_style_target_mode'] ?? BricksIntegrationService::TARGET_MODE_MANAGED) !== ($after['bricks_theme_style_target_mode'] ?? BricksIntegrationService::TARGET_MODE_MANAGED)
            || ($before['bricks_theme_style_target_id'] ?? 'managed') !== ($after['bricks_theme_style_target_id'] ?? 'managed')
            || !empty($before['bricks_disable_google_fonts_enabled']) !== !empty($after['bricks_disable_google_fonts_enabled'])
            || ($before['oxygen_integration_enabled'] ?? null) !== ($after['oxygen_integration_enabled'] ?? null)
            || ($before['acss_font_role_sync_enabled'] ?? null) !== ($after['acss_font_role_sync_enabled'] ?? null)
            || !empty($before['acss_font_role_sync_applied']) !== !empty($after['acss_font_role_sync_applied']);
    }

    /**
     * @param NormalizedSettings $before
     * @param NormalizedSettings $after
     */
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

    /**
     * @param NormalizedSettings $before
     * @param NormalizedSettings $after
     */
    private function extendedVariableSubsettingsDiffer(array $before, array $after): bool
    {
        foreach (
            [
                'extended_variable_role_weight_vars_enabled',
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

    private function formatUnicodeRangeModeLabel(string $mode): string
    {
        $pageContextBuilder = $this->pageContextBuilderOrNull();

        if ($pageContextBuilder === null) {
            return match (FontUtils::normalizeUnicodeRangeMode($mode)) {
                FontUtils::UNICODE_RANGE_MODE_LATIN_BASIC => __('Basic Latin', 'tasty-fonts'),
                FontUtils::UNICODE_RANGE_MODE_LATIN_EXTENDED => __('Latin Extended', 'tasty-fonts'),
                FontUtils::UNICODE_RANGE_MODE_OFF => __('Off', 'tasty-fonts'),
                FontUtils::UNICODE_RANGE_MODE_CUSTOM => __('Custom', 'tasty-fonts'),
                default => __('Keep Imported Ranges', 'tasty-fonts'),
            };
        }

        return $pageContextBuilder->formatUnicodeRangeModeLabel($mode);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function buildFontDisplayOptions(): array
    {
        $pageContextBuilder = $this->pageContextBuilderOrNull();

        if ($pageContextBuilder === null) {
            return [
                ['value' => 'swap', 'label' => $this->formatFontDisplayLabel('swap')],
                ['value' => 'fallback', 'label' => $this->formatFontDisplayLabel('fallback')],
                ['value' => 'block', 'label' => $this->formatFontDisplayLabel('block')],
                ['value' => 'auto', 'label' => $this->formatFontDisplayLabel('auto')],
                ['value' => 'optional', 'label' => $this->formatFontDisplayLabel('optional')],
            ];
        }

        return $pageContextBuilder->buildFontDisplayOptions();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function buildFamilyFontDisplayOptions(string $globalDisplay): array
    {
        $pageContextBuilder = $this->pageContextBuilderOrNull();

        if ($pageContextBuilder === null) {
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

        return $pageContextBuilder->buildFamilyFontDisplayOptions($globalDisplay);
    }

    private function formatFontDisplayLabel(string $display): string
    {
        $pageContextBuilder = $this->pageContextBuilderOrNull();

        if ($pageContextBuilder === null) {
            return match ($display) {
                'auto' => __('Auto', 'tasty-fonts'),
                'block' => __('Block', 'tasty-fonts'),
                'swap' => __('Swap', 'tasty-fonts'),
                'fallback' => __('Fallback', 'tasty-fonts'),
                default => __('Optional', 'tasty-fonts'),
            };
        }

        return $pageContextBuilder->formatFontDisplayLabel($display);
    }

    private function formatCssDeliveryModeLabel(string $mode): string
    {
        $pageContextBuilder = $this->pageContextBuilderOrNull();

        if ($pageContextBuilder === null) {
            return match ($mode) {
                'inline' => __('inline CSS', 'tasty-fonts'),
                default => __('generated file', 'tasty-fonts'),
            };
        }

        return $pageContextBuilder->formatCssDeliveryModeLabel($mode);
    }

    private function formatUpdateChannelLabel(string $channel): string
    {
        $pageContextBuilder = $this->pageContextBuilderOrNull();

        if ($pageContextBuilder === null) {
            return match ($channel) {
                SettingsRepository::UPDATE_CHANNEL_BETA => __('Beta', 'tasty-fonts'),
                SettingsRepository::UPDATE_CHANNEL_NIGHTLY => __('Nightly', 'tasty-fonts'),
                default => __('Stable', 'tasty-fonts'),
            };
        }

        return $pageContextBuilder->formatUpdateChannelLabel($channel);
    }

    /**
     * @param SettingsInput $settingsInput
     * @param NormalizedSettings $previousSettings
     */
    private function validateUnicodeRangeSettingsInput(array &$settingsInput, array $previousSettings): ?WP_Error
    {
        $nextMode = array_key_exists('unicode_range_mode', $settingsInput)
            ? FontUtils::normalizeUnicodeRangeMode($this->stringValue($settingsInput, 'unicode_range_mode'))
            : FontUtils::normalizeUnicodeRangeMode($this->stringValue($previousSettings, 'unicode_range_mode', FontUtils::UNICODE_RANGE_MODE_OFF));
        $customValue = array_key_exists('unicode_range_custom_value', $settingsInput)
            ? $this->stringValue($settingsInput, 'unicode_range_custom_value')
            : $this->stringValue($previousSettings, 'unicode_range_custom_value');
        $customValue = FontUtils::normalizeUnicodeRangeValue($customValue);

        if ($customValue !== '' && !FontUtils::unicodeRangeValueIsValid($customValue)) {
            return new WP_Error(
                'tasty_fonts_invalid_unicode_range',
                __('Custom unicode-range values must be a comma-separated list of U+XXXX, U+XXXX-YYYY, or U+XX? tokens.', 'tasty-fonts')
            );
        }

        if ($nextMode === FontUtils::UNICODE_RANGE_MODE_CUSTOM && $customValue === '') {
            return new WP_Error(
                'tasty_fonts_unicode_range_required',
                __('Enter a custom unicode-range before selecting Custom output.', 'tasty-fonts')
            );
        }

        if (array_key_exists('unicode_range_mode', $settingsInput)) {
            $settingsInput['unicode_range_mode'] = $nextMode;
        }

        if (array_key_exists('unicode_range_custom_value', $settingsInput)) {
            $settingsInput['unicode_range_custom_value'] = $customValue;
        }

        return null;
    }

    /**
     * @param SettingsInput $settingsInput
     * @param NormalizedSettings $previousSettings
     */
    private function validateOutputSettingsInput(array $settingsInput, array $previousSettings): ?WP_Error
    {
        $nextSettings = $previousSettings;

        foreach (
            [
                'minimal_output_preset_enabled',
                'class_output_enabled',
                'per_variant_font_variables_enabled',
            ] as $field
        ) {
            if (array_key_exists($field, $settingsInput)) {
                $nextSettings[$field] = !empty($settingsInput[$field]);
            }
        }

        if (
            !array_key_exists('minimal_output_preset_enabled', $settingsInput)
            && $this->hasExplicitNonMinimalOutputInput($settingsInput)
        ) {
            $nextSettings['minimal_output_preset_enabled'] = false;
        }

        if (!empty($nextSettings['minimal_output_preset_enabled'])) {
            return null;
        }

        if (!empty($nextSettings['class_output_enabled']) || !empty($nextSettings['per_variant_font_variables_enabled'])) {
            return null;
        }

        return new WP_Error(
            'tasty_fonts_output_required',
            __('Keep either font variables or utility classes enabled before saving output settings.', 'tasty-fonts')
        );
    }

    /**
     * @param SettingsInput $settingsInput
     */
    private function hasExplicitNonMinimalOutputInput(array $settingsInput): bool
    {
        foreach (
            [
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
                'role_usage_font_weight_enabled',
                'per_variant_font_variables_enabled',
                'extended_variable_role_weight_vars_enabled',
                'extended_variable_weight_tokens_enabled',
                'extended_variable_role_aliases_enabled',
                'extended_variable_category_sans_enabled',
                'extended_variable_category_serif_enabled',
                'extended_variable_category_mono_enabled',
            ] as $field
        ) {
            if (array_key_exists($field, $settingsInput)) {
                return true;
            }
        }

        return false;
    }

    private function assetVersionFor(string $relativePath = ''): string
    {
        if ($relativePath !== '' && SiteEnvironment::isLikelyLocalEnvironment(rest_url(''), SiteEnvironment::currentEnvironmentType())) {
            $assetPath = TASTY_FONTS_DIR . ltrim($relativePath, '/');
            $modifiedAt = is_readable($assetPath) ? filemtime($assetPath) : false;
            $contentHash = is_readable($assetPath) ? md5_file($assetPath) : false;

            if (is_int($modifiedAt) && $modifiedAt > 0 && is_string($contentHash)) {
                return TASTY_FONTS_VERSION . '.' . $modifiedAt . '.' . substr($contentHash, 0, 12);
            }

            if (is_int($modifiedAt) && $modifiedAt > 0) {
                return TASTY_FONTS_VERSION . '.' . $modifiedAt;
            }
        }

        return TASTY_FONTS_VERSION;
    }

    /**
     * @param RoleSet $draftRoles
     * @param RoleSet $appliedRoles
     * @param NormalizedSettings|null $settings
     * @return array<string, mixed>
     */
    private function buildRoleDeploymentContext(array $draftRoles, array $appliedRoles, bool $applyEverywhere, ?array $settings = null): array
    {
        return $this->pageContextBuilder->buildRoleDeploymentContext($draftRoles, $appliedRoles, $applyEverywhere, $settings);
    }

    /**
     * @param CatalogCounts $counts
     * @return list<array<string, mixed>>
     */
    protected function buildOverviewMetrics(array $counts): array
    {
        return $this->pageContextBuilder->buildOverviewMetrics($counts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildPreviewPanels(): array
    {
        return $this->pageContextBuilder->buildPreviewPanels();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildNoticeToasts(): array
    {
        return $this->pageContextBuilder->buildNoticeToasts();
    }

    /**
     * @param RoleSet $roles
     * @param RoleSet $appliedRoles
     */
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

    private function pageContextBuilderOrNull(): ?AdminPageContextBuilder
    {
        static $property = null;

        if (!$property instanceof \ReflectionProperty) {
            $property = new \ReflectionProperty(self::class, 'pageContextBuilder');
        }

        if (!$property->isInitialized($this)) {
            return null;
        }

        $value = $property->getValue($this);

        return $value instanceof AdminPageContextBuilder ? $value : null;
    }

    /**
     * @param array<string, mixed> $googleApiStatus
     */
    private function buildSearchDisabledMessage(array $googleApiStatus): string
    {
        return match ($this->stringValue($googleApiStatus, 'state', 'empty')) {
            'invalid' => __('Search is disabled because the saved Google Fonts API key is invalid. Replace it above to re-enable catalog search.', 'tasty-fonts'),
            'unknown' => __('Search is unavailable until the saved Google Fonts API key is validated. Re-save or replace the key above to continue.', 'tasty-fonts'),
            default => __('Add a Google Fonts API key above to enable search, or use manual import below.', 'tasty-fonts'),
        };
    }

    /**
     * @param PayloadList $logs
     * @return list<string>
     */
    protected function buildActivityActorOptions(array $logs): array
    {
        $pageContextBuilder = $this->pageContextBuilderOrNull();

        if ($pageContextBuilder === null) {
            $actors = [];

            foreach ($logs as $entry) {
                $actor = trim($this->stringValue($entry, 'actor'));

                if ($actor === '') {
                    continue;
                }

                $actors[$actor] = $actor;
            }

            natcasesort($actors);

            return array_values($actors);
        }

        return $pageContextBuilder->buildActivityActorOptions($logs);
    }

    /**
     * @param NormalizedSettings $settings
     * @return array<string, mixed>
     */
    protected function buildLocalEnvironmentNotice(array $settings): array
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

    /**
     * @param array<string, mixed> $preference
     */
    private function saveLocalEnvironmentNoticePreference(array $preference): void
    {
        $userId = max(0, (int) get_current_user_id());

        if ($userId <= 0) {
            return;
        }

        $preferences = get_option(self::LOCAL_ENV_NOTICE_OPTION, []);
        $preferences = is_array($preferences) ? $preferences : [];
        $normalized = [
            'hidden_until' => max(0, $this->intValue($preference, 'hidden_until')),
            'dismissed_forever' => !empty($preference['dismissed_forever']),
        ];

        if (empty($normalized['dismissed_forever']) && (int) $normalized['hidden_until'] === 0) {
            unset($preferences[$userId]);
        } else {
            $preferences[$userId] = $normalized;
        }

        update_option(self::LOCAL_ENV_NOTICE_OPTION, $preferences, false);
    }

    /**
     * @param CatalogMap $catalog
     * @return list<string>
     */
    private function buildSelectableFamilyNames(array $catalog): array
    {
        return $this->pageContextBuilder->buildSelectableFamilyNames($catalog);
    }

    /**
     * @param RoleSet $roles
     * @param NormalizedSettings|null $settings
     */
    private function buildRoleTextSummary(array $roles, ?array $settings = null): string
    {
        return $this->pageContextBuilder->buildRoleTextSummary($roles, $settings);
    }

    /**
     * @param SettingsInput $roleValues
     * @param CatalogMap|null $catalog
     * @return RoleSet
     */
    private function sanitizeRoleValues(array $roleValues, ?array $catalog = null): array
    {
        $sanitized = [
            'heading' => '',
            'body' => '',
            'monospace' => '',
            'heading_delivery_id' => '',
            'body_delivery_id' => '',
            'monospace_delivery_id' => '',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
            'monospace_fallback' => 'monospace',
            'heading_weight' => '',
            'body_weight' => '',
            'monospace_weight' => '',
            'heading_axes' => [],
            'body_axes' => [],
            'monospace_axes' => [],
        ];
        $catalog = $catalog ?? $this->catalog->getCatalog();
        $variableFontsEnabled = !empty($this->settings->getSettings()['variable_fonts_enabled']);

        foreach (['heading', 'body', 'monospace'] as $roleKey) {
            if (!array_key_exists($roleKey, $roleValues)) {
                continue;
            }

            $sanitized[$roleKey] = sanitize_text_field(FontUtils::scalarStringValue($roleValues[$roleKey]));
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

            $sanitized[$roleKey] = FontUtils::sanitizeFallback(
                FontUtils::scalarStringValue($roleValues[$roleKey]) ?: $defaultFallback
            );
        }

        foreach (['heading', 'body', 'monospace'] as $roleKey) {
            $familyName = $sanitized[$roleKey];
            $profile = $this->roleProfileForFamily($familyName, $catalog);
            $deliveryKey = $roleKey . '_delivery_id';
            $weightKey = $roleKey . '_weight';
            $axisKey = $roleKey . '_axes';
            $supportsVariableAxes = $variableFontsEnabled && $this->profileVariationAxes($profile) !== [];
            $supportsStaticWeightOverride = $profile !== [] && !$this->profileHasWeightAxis($profile);

            if (array_key_exists($deliveryKey, $roleValues)) {
                $sanitized[$deliveryKey] = sanitize_text_field(FontUtils::scalarStringValue($roleValues[$deliveryKey]));
            }

            if (array_key_exists($weightKey, $roleValues)) {
                $sanitized[$weightKey] = $this->sanitizeRoleWeightValue(
                    $roleValues[$weightKey],
                    $familyName,
                    $catalog
                );
            } elseif (array_key_exists($roleKey, $roleValues) && !$supportsStaticWeightOverride) {
                $sanitized[$weightKey] = '';
            }

            if (array_key_exists($axisKey, $roleValues)) {
                $sanitized[$axisKey] = $this->sanitizeRoleAxisValues(
                    $roleValues[$axisKey],
                    $familyName,
                    $catalog
                );
            } elseif (array_key_exists($roleKey, $roleValues) && !$supportsVariableAxes) {
                $sanitized[$axisKey] = [];
            }
        }

        return [
            'heading' => FontUtils::scalarStringValue($sanitized['heading']),
            'body' => FontUtils::scalarStringValue($sanitized['body']),
            'monospace' => FontUtils::scalarStringValue($sanitized['monospace']),
            'heading_delivery_id' => FontUtils::scalarStringValue($sanitized['heading_delivery_id']),
            'body_delivery_id' => FontUtils::scalarStringValue($sanitized['body_delivery_id']),
            'monospace_delivery_id' => FontUtils::scalarStringValue($sanitized['monospace_delivery_id']),
            'heading_fallback' => FontUtils::scalarStringValue($sanitized['heading_fallback']),
            'body_fallback' => FontUtils::scalarStringValue($sanitized['body_fallback']),
            'monospace_fallback' => FontUtils::scalarStringValue($sanitized['monospace_fallback']),
            'heading_weight' => FontUtils::scalarStringValue($sanitized['heading_weight']),
            'body_weight' => FontUtils::scalarStringValue($sanitized['body_weight']),
            'monospace_weight' => FontUtils::scalarStringValue($sanitized['monospace_weight']),
            'heading_axes' => $this->normalizeRoleAxes($sanitized['heading_axes']),
            'body_axes' => $this->normalizeRoleAxes($sanitized['body_axes']),
            'monospace_axes' => $this->normalizeRoleAxes($sanitized['monospace_axes']),
        ];
    }

    /**
     * @param CatalogMap $catalog
     * @return array<string, mixed>
     */
    private function roleProfileForFamily(string $familyName, array $catalog): array
    {
        $familyName = trim($familyName);

        if ($familyName === '') {
            return [];
        }

        $family = $catalog[$familyName] ?? null;

        return FontUtils::normalizeStringKeyedMap($family['active_delivery'] ?? null);
    }

    private function normalizeFormatMode(string $formatMode): string
    {
        return strtolower(trim($formatMode)) === 'variable' ? 'variable' : 'static';
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
            : $this->normalizeAdobeProjectId($this->getPostedText('adobe_project_id'));
        $enabled = $isResync
            ? $this->settings->isAdobeEnabled()
            : !empty($_POST['adobe_enabled']);
        $existingProjectId = $this->settings->getAdobeProjectId();

        if ($projectId !== '' && !$this->isValidAdobeProjectId($projectId)) {
            $this->redirectWithError(__('Enter a valid Adobe Fonts project ID using 3 to 16 letters and numbers.', 'tasty-fonts'));
        }

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
            $this->stringValue($validation, 'state'),
            $this->stringValue($validation, 'message')
        );

        if ($this->stringValue($validation, 'state') !== 'valid') {
            $this->log->add(__('Adobe Fonts project validation failed.', 'tasty-fonts'));
            $this->redirectWithError($this->stringValue($validation, 'message'));
        }

        $this->log->add($isResync
            ? __('Adobe Fonts project resynced.', 'tasty-fonts')
            : __('Adobe Fonts project saved.', 'tasty-fonts'));
        $this->redirectWithNoticeKey($isResync ? 'adobe_project_resynced' : 'adobe_project_saved');
    }

    /**
     * @return Payload|WP_Error
     */
    private function resolveRateLimitedSearch(string $provider, string $query, callable $resolver): array|WP_Error
    {
        $provider = strtolower(trim($provider));
        $query = sanitize_text_field($query);
        $cooldownKey = $this->getSearchCooldownTransientKey();
        $cacheKey = $this->getSearchCacheTransientKey($provider, $query);

        if ($this->isSearchCooldownActive($cooldownKey, $provider, $query)) {
            $cached = get_transient($cacheKey);

            if ($cached instanceof WP_Error) {
                return $cached;
            }

            if (is_array($cached)) {
                return $this->payloadMapValue($cached);
            }
        }

        $result = $resolver($query);

        if (is_array($result)) {
            $result = $this->payloadMapValue($result);
        }

        if (!is_array($result) && !is_wp_error($result)) {
            return new WP_Error(
                'tasty_fonts_invalid_search_response',
                __('The provider returned an invalid search response.', 'tasty-fonts')
            );
        }

        set_transient(
            $cooldownKey,
            [
                'provider' => $provider,
                'query' => $query,
                'expires_at' => microtime(true) + self::SEARCH_COOLDOWN_WINDOW_SECONDS,
            ],
            self::SEARCH_COOLDOWN_TRANSIENT_TTL
        );

        if (is_array($result)) {
            $normalizedResult = $this->payloadMapValue($result);
            set_transient($cacheKey, $normalizedResult, self::SEARCH_CACHE_TTL);

            return $normalizedResult;
        }

        set_transient($cacheKey, $result, self::SEARCH_CACHE_TTL);

        return $result;
    }

    /**
     * @return Payload|WP_Error
     */
    private function runRateLimitedAction(string $action, callable $resolver): array|WP_Error
    {
        $cooldownError = $this->startRateLimitedActionCooldown($action);

        if (is_wp_error($cooldownError)) {
            return $cooldownError;
        }

        $result = $resolver();

        if (is_array($result)) {
            return $this->payloadMapValue($result);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_Error(
            'tasty_fonts_invalid_action_response',
            __('The action returned an invalid response.', 'tasty-fonts')
        );
    }

    /**
     * @return array{font_display: string, effective_font_display: string, message: string}
     */
    private function saveFamilyFontDisplaySelection(string $family, string $display): array
    {
        $this->settings->saveFamilyFontDisplay($family, $display);

        $settings = $this->settings->getSettings();
        $savedDisplay = $this->settings->getFamilyFontDisplay($family);
        $effectiveDisplay = $savedDisplay !== ''
            ? $savedDisplay
            : $this->stringValue($settings, 'font_display', 'swap');

        $this->assets->refreshGeneratedAssets(false);

        $message = $this->buildFamilyFontDisplaySavedMessage($family, $savedDisplay, $effectiveDisplay);
        $this->log->add($message);

        return [
            'font_display' => $savedDisplay === '' ? 'inherit' : $savedDisplay,
            'effective_font_display' => $effectiveDisplay,
            'message' => $message,
        ];
    }

    /**
     * @param list<string> $variantTokens
     * @return list<string>
     */
    private function sanitizeVariantTokens(array $variantTokens): array
    {
        return array_values(
            array_filter(
                array_map(
                    static fn (mixed $token): string => sanitize_text_field((string) $token),
                    $variantTokens
                ),
                static fn (string $token): bool => $token !== ''
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

    private function normalizeAdobeProjectId(string $projectId): string
    {
        $projectId = strtolower(trim($projectId));

        $normalized = preg_replace('/[^a-z0-9]+/', '', $projectId);

        return trim(is_string($normalized) ? $normalized : '');
    }

    private function isValidAdobeProjectId(string $projectId): bool
    {
        return (bool) preg_match('/^[a-z0-9]{3,16}$/', $projectId);
    }

    private function getPostedText(string $key, string $default = ''): string
    {
        if (!isset($_POST[$key])) {
            return $default;
        }

        $value = $_POST[$key];

        if (!is_scalar($value)) {
            return $default;
        }

        return sanitize_text_field(FontUtils::scalarStringValue(wp_unslash($value)));
    }

    private function byteLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, '8bit');
        }

        return strlen($value);
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

    /**
     * @param SettingsInput $submittedValues
     * @return SettingsInput
     */
    private function buildSettingsSaveInput(array $submittedValues): array
    {
        $settingsInput = [];

        foreach (SettingsSaveFields::definitions() as $definition) {
            $field = $definition['name'];
            $kind = $definition['kind'];

            if ($field === '' || !array_key_exists($field, $submittedValues)) {
                continue;
            }

            $value = $submittedValues[$field];

            switch ($kind) {
                case 'text':
                case 'enum':
                    if (!is_scalar($value) && $value !== null) {
                        continue 2;
                    }

                    $settingsInput[$field] = is_string($value) ? wp_unslash($value) : $value;
                    break;

                case 'toggle':
                    $settingsInput[$field] = $value;
                    break;

                case 'string_array':
                case 'int_array':
                    if (!is_array($value)) {
                        continue 2;
                    }

                    $settingsInput[$field] = $this->flattenSubmittedArrayLeaves(wp_unslash($value));
                    break;
            }
        }

        return $settingsInput;
    }

    /**
     * @return SettingsInput
     */
    private function collectPostedSettingsSubmission(): array
    {
        $submittedValues = [];

        foreach (SettingsSaveFields::definitions() as $definition) {
            $field = $definition['name'];
            $kind = $definition['kind'];

            if ($field === '' || !array_key_exists($field, $_POST)) {
                continue;
            }

            $value = $_POST[$field];

            switch ($kind) {
                case 'text':
                case 'enum':
                case 'toggle':
                    if (!is_scalar($value) && $value !== null) {
                        continue 2;
                    }

                    $submittedValues[$field] = $value;
                    break;

                case 'string_array':
                case 'int_array':
                    if (!is_array($value)) {
                        continue 2;
                    }

                    $submittedValues[$field] = array_values(
                        array_filter(
                            $value,
                            static fn (mixed $item): bool => is_scalar($item) || $item === null
                        )
                    );
                    break;
            }
        }

        return $submittedValues;
    }

    /**
     * @param NormalizedSettings $before
     * @param NormalizedSettings $after
     */
    private function adminAccessSettingsDiffer(array $before, array $after): bool
    {
        return !empty($before['admin_access_custom_enabled']) !== !empty($after['admin_access_custom_enabled'])
            || ($before['admin_access_role_slugs'] ?? []) !== ($after['admin_access_role_slugs'] ?? [])
            || ($before['admin_access_user_ids'] ?? []) !== ($after['admin_access_user_ids'] ?? []);
    }

    /**
     * @return array<int, scalar|null>
     */
    /**
     * @return list<scalar|null>
     */
    private function flattenSubmittedArrayLeaves(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $flattened = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $flattened = array_merge($flattened, $this->flattenSubmittedArrayLeaves($item));
                continue;
            }

            if (!is_scalar($item) && $item !== null) {
                continue;
            }

            $flattened[] = $item;
        }

        return $flattened;
    }

    /**
     * @param SettingsInput $settingsInput
     * @return SettingsInput
     */
    private function preserveUnavailableIntegrationSettings(array $settingsInput): array
    {
        if (!$this->bricksIntegration->isAvailable()) {
            unset($settingsInput['bricks_integration_enabled']);
            unset($settingsInput['bricks_theme_styles_sync_enabled']);
            unset($settingsInput['bricks_theme_style_target_mode']);
            unset($settingsInput['bricks_theme_style_target_id']);
            unset($settingsInput['bricks_disable_google_fonts_enabled']);
            unset($settingsInput['bricks_create_theme_style']);
            unset($settingsInput['bricks_delete_theme_style']);
            unset($settingsInput['bricks_reset_integration']);
        }

        if (!$this->oxygenIntegration->isAvailable()) {
            unset($settingsInput['oxygen_integration_enabled']);
        }

        if (!$this->acssIntegration->isAvailable()) {
            unset($settingsInput['acss_font_role_sync_enabled']);
        }

        return $settingsInput;
    }

    /**
     * @return array<int|string, array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
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

        $types = $this->mapValue($rawFiles, 'type');
        $tmpNames = $this->mapValue($rawFiles, 'tmp_name');
        $errors = $this->mapValue($rawFiles, 'error');
        $sizes = $this->mapValue($rawFiles, 'size');

        foreach ($rawFiles['name'] as $index => $name) {
            $normalized[$index] = [
                'name' => is_string($name) ? $name : '',
                'type' => $this->stringValue($types, $index),
                'tmp_name' => $this->stringValue($tmpNames, $index),
                'error' => $this->intValue($errors, $index, UPLOAD_ERR_NO_FILE),
                'size' => $this->intValue($sizes, $index),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function getPostedArray(string $key): array
    {
        if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
            return [];
        }

        $unslashed = wp_unslash($_POST[$key]);

        return $this->payloadMapValue($unslashed);
    }

    /**
     * @return AxesMap
     */
    private function normalizeUploadAxesInput(mixed $rawAxes): array
    {
        if (!is_array($rawAxes)) {
            return [];
        }

        $axes = [];

        foreach ($rawAxes as $key => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $tag = array_key_exists('tag', $definition)
                ? sanitize_text_field($this->stringValue($definition, 'tag'))
                : (is_string($key) ? $key : '');

            $axes[$tag] = [
                'min' => sanitize_text_field($this->stringValue($definition, 'min')),
                'default' => sanitize_text_field($this->stringValue($definition, 'default')),
                'max' => sanitize_text_field($this->stringValue($definition, 'max')),
            ];
        }

        return FontUtils::normalizeAxesMap($axes);
    }

    /**
     * @param CatalogMap $catalog
     * @return array<string, string>
     */
    private function sanitizeRoleAxisValues(mixed $rawValues, string $familyName, array $catalog): array
    {
        if (!is_array($rawValues) || trim($familyName) === '') {
            return [];
        }

        $family = $catalog[$familyName] ?? null;

        if (!is_array($family)) {
            return [];
        }

        $activeDelivery = $this->payloadMapValue(FontUtils::normalizeStringKeyedMap($family['active_delivery'] ?? null));
        $availableAxes = $this->profileVariationAxes($activeDelivery);

        if ($availableAxes === []) {
            return [];
        }

        $normalized = FontUtils::normalizeVariationDefaults($rawValues);
        $filtered = [];

        foreach ($normalized as $tag => $value) {
            if (!isset($availableAxes[$tag])) {
                continue;
            }

            $axis = $availableAxes[$tag];
            $numericValue = FontUtils::scalarFloatValue($value);
            $axisMin = $this->floatValue($axis, 'min');
            $axisMax = $this->floatValue($axis, 'max');

            if ($numericValue < $axisMin || $numericValue > $axisMax) {
                continue;
            }

            $filtered[$tag] = FontUtils::scalarStringValue($value);
        }

        return $filtered;
    }

    /**
     * @param mixed $value
     * @return array<string, string>
     */
    private function normalizeRoleAxes(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $tag => $axisValue) {
            if (!is_string($tag) || !is_scalar($axisValue)) {
                continue;
            }

            $normalized[$tag] = (string) $axisValue;
        }

        return $normalized;
    }

    /**
     * @param CatalogMap $catalog
     * @return array<string, mixed>
     */
    private function buildRoleFamilyCatalog(array $catalog): array
    {
        $map = [];
        $settings = $this->settings->getSettings();
        $familyFallbacks = $this->stringMapValue($settings['family_fallbacks'] ?? []);

        foreach ($catalog as $familyName => $family) {
            $activeDelivery = $this->payloadMapValue(FontUtils::normalizeStringKeyedMap($family['active_delivery'] ?? null));
            $deliveryId = sanitize_text_field($this->stringValue($activeDelivery, 'id'));

            if ($deliveryId === '') {
                continue;
            }

            $map[(string) $familyName] = [
                'activeDeliveryId' => $deliveryId,
                'activeDeliveryLabel' => trim(
                    $this->translateProfileLabel($this->stringValue($activeDelivery, 'label'))
                    . ' · '
                    . ucfirst(FontUtils::resolveProfileFormat($activeDelivery))
                ),
                'format' => FontUtils::resolveProfileFormat($activeDelivery),
                'weights' => $this->buildRoleWeightOptionsForProfile($activeDelivery),
                'axes' => $this->profileVariationAxes($activeDelivery),
                'hasWeightAxis' => $this->profileHasWeightAxis($activeDelivery),
                'fallback' => array_key_exists((string) $familyName, $familyFallbacks)
                    ? FontUtils::sanitizeFallback($familyFallbacks[(string) $familyName])
                    : FontUtils::defaultFallbackForCategory($this->stringValue($family, 'font_category')),
            ];
        }

        return $map;
    }

    /**
     * @param CatalogMap $catalog
     */
    private function sanitizeRoleWeightValue(mixed $weightValue, string $familyName, array $catalog): string
    {
        $weight = $this->resolveConcreteRoleWeight(FontUtils::scalarStringValue($weightValue));

        if ($weight === '' || trim($familyName) === '') {
            return '';
        }

        $family = $catalog[trim($familyName)] ?? null;
        $profile = $this->payloadMapValue(FontUtils::normalizeStringKeyedMap($family['active_delivery'] ?? null));

        if ($profile === [] || $this->profileHasWeightAxis($profile)) {
            return '';
        }

        foreach ($this->buildRoleWeightOptionsForProfile($profile) as $option) {
            if ((string) ($option['value'] ?? '') === $weight) {
                return $weight;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $profile
     * @return list<array<string, string>>
     */
    private function buildRoleWeightOptionsForProfile(array $profile): array
    {
        $weights = [];

        foreach ((array) ($profile['faces'] ?? []) as $face) {
            if (
                !is_array($face)
                || FontUtils::normalizeStyle($this->stringValue($face, 'style', 'normal')) !== 'normal'
            ) {
                continue;
            }

            $weight = $this->resolveConcreteRoleWeight($this->stringValue($face, 'weight'));

            if ($weight === '') {
                continue;
            }

            $weights[$weight] = [
                'value' => $weight,
                'label' => trim($weight . ' ' . $this->buildRoleWeightLabel($weight)),
            ];
        }

        uksort($weights, static fn (string $left, string $right): int => ((int) $left) <=> ((int) $right));

        return array_values($weights);
    }

    /**
     * @param array<string, mixed> $profile
     * @return AxesMap
     */
    private function profileVariationAxes(array $profile): array
    {
        $axes = [];

        foreach ((array) ($profile['faces'] ?? []) as $face) {
            if (!is_array($face)) {
                continue;
            }

            foreach (FontUtils::normalizeAxesMap($face['axes'] ?? []) as $tag => $definition) {
                if (!isset($axes[$tag])) {
                    $axes[$tag] = $definition;
                    continue;
                }

                $axes[$tag]['min'] = (string) min((float) $axes[$tag]['min'], (float) $definition['min']);
                $axes[$tag]['max'] = (string) max((float) $axes[$tag]['max'], (float) $definition['max']);
            }
        }

        ksort($axes, SORT_STRING);

        return $axes;
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function profileHasWeightAxis(array $profile): bool
    {
        $axes = $this->profileVariationAxes($profile);

        return isset($axes['WGHT']);
    }

    private function translateProfileLabel(string $label): string
    {
        return match (trim($label)) {
            'Self-hosted' => __('Self-hosted', 'tasty-fonts'),
            'Self-hosted (Google import)' => __('Self-hosted (Google import)', 'tasty-fonts'),
            'Google CDN' => __('Google CDN', 'tasty-fonts'),
            'Self-hosted (Bunny import)' => __('Self-hosted (Bunny import)', 'tasty-fonts'),
            'Bunny CDN' => __('Bunny CDN', 'tasty-fonts'),
            'Adobe-hosted' => __('Adobe-hosted', 'tasty-fonts'),
            default => trim($label),
        };
    }

    private function resolveConcreteRoleWeight(string $weight): string
    {
        if (trim($weight) === '') {
            return '';
        }

        $property = FontUtils::weightVariableName($weight);

        if ($property === '') {
            return '';
        }

        return substr($property, strlen('--weight-'));
    }

    private function buildRoleWeightLabel(string $weight): string
    {
        return match ($weight) {
            '100' => __('Thin', 'tasty-fonts'),
            '200' => __('Extra Light', 'tasty-fonts'),
            '300' => __('Light', 'tasty-fonts'),
            '400' => __('Regular', 'tasty-fonts'),
            '500' => __('Medium', 'tasty-fonts'),
            '600' => __('Semi Bold', 'tasty-fonts'),
            '700' => __('Bold', 'tasty-fonts'),
            '800' => __('Extra Bold', 'tasty-fonts'),
            '900' => __('Black', 'tasty-fonts'),
            '950' => __('Extra Black', 'tasty-fonts'),
            '1000' => __('Ultra Black', 'tasty-fonts'),
            default => '',
        };
    }

    private function initializeDetectedIntegrations(): void
    {
        $this->initializeDetectedBuilderIntegration(
            'bricks_integration_enabled',
            $this->bricksIntegration->isAvailable(),
            __('Bricks Builder detected. Bricks integration has been enabled in Integrations.', 'tasty-fonts'),
            __('Bricks integration detected. Enabled automatically.', 'tasty-fonts')
        );
        $this->initializeDetectedBuilderIntegration(
            'oxygen_integration_enabled',
            $this->oxygenIntegration->isAvailable(),
            __('Oxygen Builder detected. Oxygen integration has been enabled in Integrations.', 'tasty-fonts'),
            __('Oxygen integration detected. Enabled automatically.', 'tasty-fonts')
        );

        $settings = $this->settings->getSettings();

        if (!$this->acssIntegration->isAvailable()) {
            return;
        }

        $current = $this->acssIntegration->getCurrentSettings();

        if (
            ($settings['acss_font_role_sync_enabled'] ?? null) !== null
            && !$this->shouldRecoverLegacyAcssDetectionState($settings, $current)
        ) {
            return;
        }

        $settings = $this->settings->saveAcssFontRoleSyncState(
            true,
            false,
            $current['heading'],
            $current['body'],
            (string) ($current['heading_weight'] ?? ''),
            (string) ($current['body_weight'] ?? '')
        );
        $message = __('Automatic.css detected. Automatic.css font sync has been enabled in Integrations.', 'tasty-fonts');
        $syncMessage = $this->syncAcssIntegrationForRuntimeState($settings);

        if (is_wp_error($syncMessage)) {
            $message .= ' ' . $syncMessage->get_error_message();
            $this->queueNoticeToast('error', $message, 'alert');
            $this->log->add(__('Automatic.css integration was detected, but its font sync could not be completed automatically.', 'tasty-fonts'));
            return;
        }

        if ($syncMessage !== '') {
            $message .= ' ' . $syncMessage;
        }

        $this->queueNoticeToast('success', $message, 'status');
        $this->log->add(__('Automatic.css integration detected. Font sync enabled automatically.', 'tasty-fonts'));
    }

    private function initializeDetectedBuilderIntegration(
        string $settingsKey,
        bool $available,
        string $toastMessage,
        string $logMessage
    ): void {
        $settings = $this->settings->getSettings();

        if (($settings[$settingsKey] ?? null) !== null || !$available) {
            return;
        }

        $this->settings->saveSettings([$settingsKey => '1']);
        $this->queueNoticeToast('success', $toastMessage, 'status');
        $this->log->add($logMessage);
    }

    /**
     * @param NormalizedSettings $settings
     * @param NormalizedSettings $current
     */
    private function shouldRecoverLegacyAcssDetectionState(array $settings, array $current): bool
    {
        return ($settings['acss_font_role_sync_enabled'] ?? null) === false
            && empty($settings['acss_font_role_sync_applied'])
            && trim($this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_family')) === ''
            && trim($this->stringValue($settings, 'acss_font_role_sync_previous_text_font_family')) === ''
            && trim($this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_weight')) === ''
            && trim($this->stringValue($settings, 'acss_font_role_sync_previous_text_font_weight')) === ''
            && trim($this->stringValue($current, 'heading')) === ''
            && trim($this->stringValue($current, 'body')) === ''
            && trim($this->stringValue($current, 'heading_weight')) === ''
            && trim($this->stringValue($current, 'body_weight')) === '';
    }

    private function reconcileAcssIntegrationDrift(): void
    {
        $settings = $this->settings->getSettings();

        if (($settings['acss_font_role_sync_enabled'] ?? null) !== true) {
            return;
        }

        if (empty($settings['acss_font_role_sync_applied']) || !$this->acssIntegration->isAvailable()) {
            return;
        }

        $sitewideRolesEnabled = !empty($settings['auto_apply_roles']);
        $state = $this->acssIntegration->readState($sitewideRolesEnabled, true, true);

        if (($state['status'] ?? '') !== 'out_of_sync') {
            return;
        }

        $this->settings->saveAcssFontRoleSyncState(
            false,
            false,
            $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_family'),
            $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_family'),
            $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_weight'),
            $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_weight')
        );

        $message = __('Automatic.css font sync was turned off because its managed font settings no longer match the Tasty Fonts values. Enable it again to reapply the mapping.', 'tasty-fonts');
        $this->queueNoticeToast('success', $message, 'status');
        $this->log->add(__('Automatic.css sync turned off after its managed font settings changed outside Tasty Fonts.', 'tasty-fonts'));
    }

    private function reconcileBricksIntegrationState(): void
    {
        if (!$this->bricksIntegration->isAvailable()) {
            return;
        }

        $settings = $this->settings->getSettings();
        $masterEnabled = ($settings['bricks_integration_enabled'] ?? null) !== false;
        $sitewideRolesEnabled = !empty($settings['auto_apply_roles']);
        $themeStylesEnabled = !empty($settings[BricksIntegrationService::FEATURE_THEME_STYLES]);
        $targetMode = $this->stringValue($settings, 'bricks_theme_style_target_mode', BricksIntegrationService::TARGET_MODE_MANAGED);
        $targetStyleId = $this->stringValue($settings, 'bricks_theme_style_target_id', BricksIntegrationService::MANAGED_THEME_STYLE_ID);
        $state = $this->bricksIntegration->readState($settings);

        if ($this->bricksIntegration->hasLegacyManagedVariables()) {
            $this->bricksIntegration->removeLegacyManagedVariables();
            $this->log->add(__('Removed legacy Bricks alias variables so Bricks Theme Styles can use the Tasty role variables directly.', 'tasty-fonts'));
        }

        if (
            $masterEnabled
            && $sitewideRolesEnabled
            && $themeStylesEnabled
            && (($state['theme_styles']['status'] ?? '') === 'ready')
        ) {
            $result = $this->bricksIntegration->applyThemeStylesSync($targetMode, $targetStyleId);

            if (!is_wp_error($result)) {
                $this->log->add(__('Updated the selected Bricks Theme Style to use the Tasty role variables directly.', 'tasty-fonts'));
            }
        }
    }

    /**
     * @param NormalizedSettings $previousSettings
     * @param NormalizedSettings $savedSettings
     * @param SettingsInput $settingsInput
     */
    private function syncAcssIntegrationAfterSettingsSave(array $previousSettings, array $savedSettings, array $settingsInput): string|WP_Error
    {
        if (!array_key_exists('acss_font_role_sync_enabled', $settingsInput)) {
            return '';
        }

        $enabled = ($savedSettings['acss_font_role_sync_enabled'] ?? null) === true;
        $wasEnabled = ($previousSettings['acss_font_role_sync_enabled'] ?? null) === true;

        if (!$enabled && !$wasEnabled) {
            return '';
        }

        return $this->syncAcssIntegrationForRuntimeState($savedSettings, true);
    }

    /**
     * @param SettingsInput $settingsInput
     * @param SettingsInput $submittedValues
     * @return array<string, mixed>
     */
    private function applyBricksThemeStyleActions(array $settingsInput, array $submittedValues): array
    {
        $hasThemeStyleTargetSubmission = !empty($submittedValues['bricks_create_theme_style'])
            || array_key_exists(BricksIntegrationService::FEATURE_THEME_STYLES, $settingsInput)
            || array_key_exists('bricks_theme_style_target_mode', $settingsInput)
            || array_key_exists('bricks_theme_style_target_id', $settingsInput);

        if (!$hasThemeStyleTargetSubmission) {
            return $settingsInput;
        }

        if (!empty($submittedValues['bricks_create_theme_style'])) {
            $settingsInput['bricks_integration_enabled'] = true;
            $settingsInput[BricksIntegrationService::FEATURE_THEME_STYLES] = true;
            $settingsInput['bricks_theme_style_target_mode'] = BricksIntegrationService::TARGET_MODE_MANAGED;
            $settingsInput['bricks_theme_style_target_id'] = BricksIntegrationService::MANAGED_THEME_STYLE_ID;
        }

        $targetMode = $this->stringValue($settingsInput, 'bricks_theme_style_target_mode', BricksIntegrationService::TARGET_MODE_MANAGED);

        if (!in_array($targetMode, [
            BricksIntegrationService::TARGET_MODE_MANAGED,
            BricksIntegrationService::TARGET_MODE_SELECTED,
            BricksIntegrationService::TARGET_MODE_ALL,
        ], true)) {
            $targetMode = BricksIntegrationService::TARGET_MODE_MANAGED;
        }

        $settingsInput['bricks_theme_style_target_mode'] = $targetMode;

        if ($targetMode !== BricksIntegrationService::TARGET_MODE_SELECTED) {
            $settingsInput['bricks_theme_style_target_id'] = BricksIntegrationService::MANAGED_THEME_STYLE_ID;

            return $settingsInput;
        }

        $availableStyles = $this->bricksIntegration->getThemeStyleChoices();
        $fallbackTargetId = array_key_first($availableStyles) ?: BricksIntegrationService::MANAGED_THEME_STYLE_ID;
        $requestedTargetId = trim($this->stringValue($settingsInput, 'bricks_theme_style_target_id'));

        $settingsInput['bricks_theme_style_target_id'] = $requestedTargetId !== '' && isset($availableStyles[$requestedTargetId])
            ? $requestedTargetId
            : $fallbackTargetId;

        return $settingsInput;
    }

    /**
     * @param NormalizedSettings $previousSettings
     * @param SettingsInput $settingsInput
     * @return Payload|WP_Error
     */
    private function deleteManagedBricksThemeStyle(array $previousSettings, array $settingsInput): array|WP_Error
    {
        $deleted = $this->bricksIntegration->deleteManagedThemeStyle();

        if (is_wp_error($deleted)) {
            return $deleted;
        }

        $availableStyles = $this->bricksIntegration->getThemeStyleChoices();
        $fallbackTargetId = array_key_first($availableStyles) ?: BricksIntegrationService::MANAGED_THEME_STYLE_ID;
        $wasUsingManagedTarget = ($previousSettings['bricks_theme_style_target_mode'] ?? BricksIntegrationService::TARGET_MODE_MANAGED) === BricksIntegrationService::TARGET_MODE_MANAGED;

        $settingsInput['bricks_theme_style_target_mode'] = $wasUsingManagedTarget && $fallbackTargetId !== BricksIntegrationService::MANAGED_THEME_STYLE_ID
            ? BricksIntegrationService::TARGET_MODE_SELECTED
            : $this->stringValue($previousSettings, 'bricks_theme_style_target_mode', BricksIntegrationService::TARGET_MODE_MANAGED);
        $settingsInput['bricks_theme_style_target_id'] = $fallbackTargetId;

        if ($wasUsingManagedTarget && $fallbackTargetId === BricksIntegrationService::MANAGED_THEME_STYLE_ID) {
            $settingsInput[BricksIntegrationService::FEATURE_THEME_STYLES] = false;
        }

        $savedSettings = $this->settings->saveSettings($settingsInput);
        $bricksMessage = $this->syncBricksIntegrationAfterSettingsSave($previousSettings, $savedSettings, $settingsInput);

        if (is_wp_error($bricksMessage)) {
            return $bricksMessage;
        }

        $savedSettings = $this->settings->getSettings();
        $reloadRequired = $this->settingsChangeRequiresReload($previousSettings, $savedSettings);
        $catalog = $this->catalog->getCatalog();
        $availableFamilies = $this->buildSelectableFamilyNames($catalog);
        $roles = $this->settings->getRoles($availableFamilies);
        $appliedRoles = $this->settings->getAppliedRoles($availableFamilies);
        $outputPanels = $this->buildOutputPanels($roles, $savedSettings, $catalog, $appliedRoles);
        $message = __('The Tasty Fonts Theme Style was deleted from Bricks.', 'tasty-fonts');

        if ($bricksMessage !== '') {
            $message .= ' ' . $bricksMessage;
        }

        if ($reloadRequired) {
            $message .= ' ' . __('Reload the page to apply this change.', 'tasty-fonts');
        }

        return [
            'message' => $message,
            'settings' => $savedSettings,
            'reload_required' => $reloadRequired,
            'output_panels' => $outputPanels,
        ];
    }

    /**
     * @param NormalizedSettings $previousSettings
     * @param SettingsInput $settingsInput
     * @return Payload|WP_Error
     */
    private function resetBricksIntegration(array $previousSettings, array $settingsInput): array|WP_Error
    {
        $settingsInput['bricks_integration_enabled'] = false;
        $settingsInput[BricksIntegrationService::FEATURE_THEME_STYLES] = false;
        $settingsInput[BricksIntegrationService::FEATURE_DISABLE_GOOGLE_FONTS] = false;
        $settingsInput['bricks_theme_style_target_mode'] = BricksIntegrationService::TARGET_MODE_MANAGED;
        $settingsInput['bricks_theme_style_target_id'] = BricksIntegrationService::MANAGED_THEME_STYLE_ID;

        $savedSettings = $this->settings->saveSettings($settingsInput);

        $bricksResetMessage = $this->syncBricksIntegrationForReset();

        if (is_wp_error($bricksResetMessage)) {
            return $bricksResetMessage;
        }

        $savedSettings = $this->settings->getSettings();
        $reloadRequired = $this->settingsChangeRequiresReload($previousSettings, $savedSettings);
        $catalog = $this->catalog->getCatalog();
        $availableFamilies = $this->buildSelectableFamilyNames($catalog);
        $roles = $this->settings->getRoles($availableFamilies);
        $appliedRoles = $this->settings->getAppliedRoles($availableFamilies);
        $outputPanels = $this->buildOutputPanels($roles, $savedSettings, $catalog, $appliedRoles);
        $message = __('Bricks integration reset. Previous Theme Styles and Bricks font settings were restored.', 'tasty-fonts');

        if ($bricksResetMessage !== '') {
            $message .= ' ' . $bricksResetMessage;
        }

        if ($reloadRequired) {
            $message .= ' ' . __('Reload the page to apply this change.', 'tasty-fonts');
        }

        return [
            'message' => $message,
            'settings' => $savedSettings,
            'reload_required' => $reloadRequired,
            'output_panels' => $outputPanels,
        ];
    }

    private function syncBricksIntegrationForReset(): string|WP_Error
    {
        if (!$this->bricksIntegration->isAvailable()) {
            return '';
        }

        $messages = [];

        if ($this->bricksIntegration->hasLegacyManagedVariables()) {
            $this->bricksIntegration->removeLegacyManagedVariables();
            $messages[] = __('Legacy Bricks alias variables were removed.', 'tasty-fonts');
        }

        $state = $this->bricksIntegration->getSyncState();

        if (!empty($state['theme_styles']['applied'])) {
            $restore = $this->bricksIntegration->restoreThemeStylesSync();

            if (is_wp_error($restore)) {
                return $restore;
            }

            $messages[] = __('Bricks Theme Styles were restored.', 'tasty-fonts');
        }

        $state = $this->bricksIntegration->getSyncState();

        if (!empty($state['google_fonts']['applied'])) {
            $restore = $this->bricksIntegration->restoreGoogleFontsSetting();

            if (is_wp_error($restore)) {
                return $restore;
            }

            $messages[] = __('Bricks Google font settings were restored.', 'tasty-fonts');
        }

        return implode(' ', $messages);
    }

    /**
     * @param NormalizedSettings $previousSettings
     * @param NormalizedSettings $savedSettings
     * @param SettingsInput $settingsInput
     */
    private function syncBricksIntegrationAfterSettingsSave(array $previousSettings, array $savedSettings, array $settingsInput): string|WP_Error
    {
        foreach (
            [
                'bricks_integration_enabled',
                BricksIntegrationService::FEATURE_THEME_STYLES,
                'bricks_theme_style_target_mode',
                'bricks_theme_style_target_id',
                BricksIntegrationService::FEATURE_DISABLE_GOOGLE_FONTS,
                'auto_apply_roles',
            ] as $field
        ) {
            if (array_key_exists($field, $settingsInput)) {
                return $this->syncBricksIntegrationForRuntimeState($savedSettings, true);
            }
        }

        return '';
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function syncBricksIntegrationForRuntimeState(array $settings, bool $clearBackupWhenDisabled = false): string|WP_Error
    {
        if (!$this->bricksIntegration->isAvailable()) {
            return '';
        }

        $masterEnabled = ($settings['bricks_integration_enabled'] ?? null) !== false;
        $sitewideRolesEnabled = !empty($settings['auto_apply_roles']);
        $messages = [];
        $bricksState = $this->bricksIntegration->readState($settings);

        $themeStylesEnabled = !empty($settings[BricksIntegrationService::FEATURE_THEME_STYLES]);
        $disableGoogleFontsEnabled = !empty($settings[BricksIntegrationService::FEATURE_DISABLE_GOOGLE_FONTS]);
        $targetMode = $this->stringValue($settings, 'bricks_theme_style_target_mode', BricksIntegrationService::TARGET_MODE_MANAGED);
        $targetStyleId = $this->stringValue($settings, 'bricks_theme_style_target_id', BricksIntegrationService::MANAGED_THEME_STYLE_ID);

        if ($this->bricksIntegration->hasLegacyManagedVariables()) {
            $this->bricksIntegration->removeLegacyManagedVariables();
            $messages[] = __('Legacy Bricks alias variables were removed so Theme Styles can use the existing Tasty role variables directly.', 'tasty-fonts');
        }

        $bricksState = $this->bricksIntegration->readState($settings);

        if (!$masterEnabled || !$themeStylesEnabled || !$sitewideRolesEnabled) {
            if (!empty($bricksState['theme_styles']['applied'])) {
                $restore = $this->bricksIntegration->restoreThemeStylesSync();

                if (is_wp_error($restore)) {
                    return $restore;
                }

                if (!$masterEnabled) {
                    $messages[] = __('Bricks Theme Styles were restored because the Bricks integration is off.', 'tasty-fonts');
                } elseif (!$sitewideRolesEnabled) {
                    $messages[] = __('Bricks Theme Styles were restored because sitewide role delivery is off.', 'tasty-fonts');
                } else {
                    $messages[] = __('Bricks Theme Style sync was turned off and the previous Theme Styles were restored.', 'tasty-fonts');
                }
            }
        } elseif (($bricksState['theme_styles']['status'] ?? '') !== 'synced') {
            $result = $this->bricksIntegration->applyThemeStylesSync($targetMode, $targetStyleId);

            if (is_wp_error($result)) {
                return $result;
            }

            $messages[] = __('Bricks Theme Styles now use the Tasty Fonts sitewide typography variables.', 'tasty-fonts');
        }

        $bricksState = $this->bricksIntegration->readState($settings);

        if (!$masterEnabled || !$disableGoogleFontsEnabled) {
            if (!empty($bricksState['google_fonts']['applied'])) {
                $restore = $this->bricksIntegration->restoreGoogleFontsSetting();

                if (is_wp_error($restore)) {
                    return $restore;
                }

                $messages[] = !$masterEnabled
                    ? __('Bricks Google fonts control was restored because the Bricks integration is off.', 'tasty-fonts')
                    : __('Bricks Google fonts control was turned off and the previous Bricks setting was restored.', 'tasty-fonts');
            }
        } elseif (($bricksState['google_fonts']['status'] ?? '') !== 'synced') {
            $result = $this->bricksIntegration->applyGoogleFontsSetting();

            if (is_wp_error($result)) {
                return $result;
            }

            $messages[] = __('Bricks Google fonts are now disabled so Bricks font pickers show only Tasty-supplied fonts.', 'tasty-fonts');
        }

        return implode(' ', array_values(array_unique(array_filter($messages, static fn (string $message): bool => $message !== ''))));
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function syncAcssIntegrationForRuntimeState(array $settings, bool $clearBackupWhenDisabled = false): string|WP_Error
    {
        $enabled = ($settings['acss_font_role_sync_enabled'] ?? null) === true;
        $applied = !empty($settings['acss_font_role_sync_applied']);
        $sitewideRolesEnabled = !empty($settings['auto_apply_roles']);

        if ($enabled && empty($settings['extended_variable_role_weight_vars_enabled'])) {
            $settings = $this->settings->saveSettings(['extended_variable_role_weight_vars_enabled' => '1']);
        }

        if (!$enabled) {
            if ($applied && $this->acssIntegration->isAvailable()) {
                $restore = $this->restoreAcssIntegration($settings);

                if (is_wp_error($restore)) {
                    return $restore;
                }
            }

            $this->settings->saveAcssFontRoleSyncState(
                false,
                false,
                $clearBackupWhenDisabled ? '' : $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_family'),
                $clearBackupWhenDisabled ? '' : $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_family'),
                $clearBackupWhenDisabled ? '' : $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_weight'),
                $clearBackupWhenDisabled ? '' : $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_weight')
            );

            return $applied
                ? __('Previous Automatic.css font settings were restored.', 'tasty-fonts')
                : __('Automatic.css sync is off. Existing Automatic.css settings were left unchanged.', 'tasty-fonts');
        }

        if (!$this->acssIntegration->isAvailable()) {
            $this->settings->saveAcssFontRoleSyncState(
                true,
                false,
                $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_family'),
                $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_family'),
                $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_weight'),
                $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_weight')
            );

            return __('Automatic.css sync is enabled and will apply when Automatic.css is active on this site.', 'tasty-fonts');
        }

        if (!$sitewideRolesEnabled) {
            if ($applied) {
                $restore = $this->restoreAcssIntegration($settings);

                if (is_wp_error($restore)) {
                    return $restore;
                }
            }

            $this->settings->saveAcssFontRoleSyncState(
                true,
                false,
                $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_family'),
                $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_family'),
                $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_weight'),
                $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_weight')
            );

            return $applied
                ? __('Sitewide role delivery is off, so Automatic.css was restored to its previous font settings until those role variables are live again.', 'tasty-fonts')
                : __('Automatic.css sync is enabled and will apply after sitewide role delivery is turned on.', 'tasty-fonts');
        }

        $state = $this->acssIntegration->readState($sitewideRolesEnabled, $enabled, $applied);

        if ($applied && !empty($state['synced'])) {
            return '';
        }

        $settings = $this->captureAcssIntegrationBackupValues($settings);
        $result = $this->acssIntegration->applyRoleVariableSync();

        if (is_wp_error($result)) {
            return $result;
        }

        $this->settings->saveAcssFontRoleSyncState(
            true,
            true,
            $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_family'),
            $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_family'),
            $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_weight'),
            $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_weight')
        );

        return $applied
            ? __('Automatic.css font settings were reapplied.', 'tasty-fonts')
            : __('Automatic.css now uses Tasty Fonts role variables for heading and body typography.', 'tasty-fonts');
    }

    /**
     * @param NormalizedSettings $settings
     * @return array<string, string>
     */
    private function captureAcssIntegrationBackupValues(array $settings): array
    {
        $hasHeadingBackup = trim($this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_family')) !== '';
        $hasTextBackup = trim($this->stringValue($settings, 'acss_font_role_sync_previous_text_font_family')) !== '';
        $hasHeadingWeightBackup = trim($this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_weight')) !== '';
        $hasTextWeightBackup = trim($this->stringValue($settings, 'acss_font_role_sync_previous_text_font_weight')) !== '';

        if ($hasHeadingBackup || $hasTextBackup || $hasHeadingWeightBackup || $hasTextWeightBackup) {
            return [
                'acss_font_role_sync_previous_heading_font_family' => $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_family'),
                'acss_font_role_sync_previous_text_font_family' => $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_family'),
                'acss_font_role_sync_previous_heading_font_weight' => $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_weight'),
                'acss_font_role_sync_previous_text_font_weight' => $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_weight'),
            ];
        }

        $current = $this->acssIntegration->getCurrentSettings();

        $saved = $this->settings->saveAcssFontRoleSyncState(
            true,
            !empty($settings['acss_font_role_sync_applied']),
            $this->stringValue($current, 'heading'),
            $this->stringValue($current, 'body'),
            $this->stringValue($current, 'heading_weight'),
            $this->stringValue($current, 'body_weight')
        );

        return [
            'acss_font_role_sync_previous_heading_font_family' => $this->stringValue($saved, 'acss_font_role_sync_previous_heading_font_family'),
            'acss_font_role_sync_previous_text_font_family' => $this->stringValue($saved, 'acss_font_role_sync_previous_text_font_family'),
            'acss_font_role_sync_previous_heading_font_weight' => $this->stringValue($saved, 'acss_font_role_sync_previous_heading_font_weight'),
            'acss_font_role_sync_previous_text_font_weight' => $this->stringValue($saved, 'acss_font_role_sync_previous_text_font_weight'),
        ];
    }

    /**
     * @param NormalizedSettings $settings
     * @return Payload|WP_Error
     */
    private function restoreAcssIntegration(array $settings): array|WP_Error
    {
        return $this->acssIntegration->restoreFontSettings(
            $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_family'),
            $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_family'),
            $this->stringValue($settings, 'acss_font_role_sync_previous_heading_font_weight'),
            $this->stringValue($settings, 'acss_font_role_sync_previous_text_font_weight')
        );
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

    /**
     * @param SiteTransferStatus $status
     */
    private function queueSiteTransferStatus(array $status): void
    {
        $tone = $this->stringValue($status, 'tone') === 'success' ? 'success' : 'error';
        $title = sanitize_text_field($this->stringValue($status, 'title'));
        $message = sanitize_text_field($this->stringValue($status, 'message'));
        $code = $this->normalizeSiteTransferStatusCode($this->stringValue($status, 'code'));

        if ($message === '') {
            return;
        }

        set_transient(
            $this->getSiteTransferStatusTransientKey(),
            [
                'tone' => $tone,
                'title' => $title,
                'message' => $message,
                'code' => $code,
            ],
            self::NOTICE_TTL
        );
    }

    /**
     * @return SiteTransferStatus
     */
    private function consumeQueuedSiteTransferStatus(): array
    {
        $transientKey = $this->getSiteTransferStatusTransientKey();
        $storedStatus = get_transient($transientKey);

        if ($storedStatus === false) {
            return [];
        }

        delete_transient($transientKey);

        if (!is_array($storedStatus)) {
            return [];
        }

        $message = sanitize_text_field($this->stringValue($storedStatus, 'message'));

        if ($message === '') {
            return [];
        }

        return [
            'tone' => $this->stringValue($storedStatus, 'tone') === 'success' ? 'success' : 'error',
            'title' => sanitize_text_field($this->stringValue($storedStatus, 'title')),
            'message' => $message,
            'code' => $this->normalizeSiteTransferStatusCode($this->stringValue($storedStatus, 'code')),
        ];
    }

    private function clearQueuedSiteTransferStatus(): void
    {
        delete_transient($this->getSiteTransferStatusTransientKey());
    }

    /**
     * @return SiteTransferStatus
     */
    private function buildSiteTransferStatusPayload(\WP_Error $error, string $tone = 'error'): array
    {
        $errorMessage = sanitize_text_field($error->get_error_message());
        $errorCode = $this->normalizeSiteTransferStatusCode($this->stringifySiteTransferStatusCode($error->get_error_code()));

        return [
            'tone' => $tone === 'success' ? 'success' : 'error',
            'title' => __('Site transfer import failed.', 'tasty-fonts'),
            'message' => $errorMessage,
            'code' => $errorCode,
        ];
    }

    /**
     * @param SiteTransferStatus $status
     */
    private function formatSiteTransferStatusForActivityLog(array $status): string
    {
        $message = sanitize_text_field($this->stringValue($status, 'message'));
        $code = $this->normalizeSiteTransferStatusCode($this->stringValue($status, 'code'));

        if ($message === '') {
            return __('Site transfer import failed.', 'tasty-fonts');
        }

        if ($code !== '') {
            return sprintf(
                __('Site transfer import failed (%1$s): %2$s', 'tasty-fonts'),
                $code,
                $message
            );
        }

        return sprintf(
            __('Site transfer import failed: %s', 'tasty-fonts'),
            $message
        );
    }

    private function normalizeSiteTransferStatusCode(string $code): string
    {
        $normalized = strtolower(trim($code));
        $normalized = preg_replace('/[^a-z0-9_-]+/', '_', $normalized) ?? '';

        return trim($normalized, '_');
    }

    private function stringifySiteTransferStatusCode(int|string $code): string
    {
        return (string) $code;
    }

    /**
     * @param mixed $value
     * @return PayloadList
     */
    private function normalizePayloadList(mixed $value): array
    {
        return FontUtils::normalizeListOfStringKeyedMaps($value);
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<string, mixed>
     */
    private function payloadMapValue(array $values): array
    {
        return FontUtils::normalizeStringKeyedMap($values);
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int|string, mixed>
     */
    private function mapValue(array $values, int|string $key): array
    {
        $value = $values[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function stringValue(array $values, int|string $key, string $default = ''): string
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
    private function intValue(array $values, int|string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        return FontUtils::scalarIntValue($values[$key], $default);
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function floatValue(array $values, int|string $key, float $default = 0.0): float
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $value = $values[$key];

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        $normalized = FontUtils::scalarStringValue($value);

        return is_numeric($normalized) ? (float) $normalized : $default;
    }

    /**
     * @return array<string, string>
     */
    private function stringMapValue(mixed $value): array
    {
        return FontUtils::normalizeStringMap($value);
    }

    private function getPendingNoticeTransientKey(): string
    {
        return TransientKey::forSite(self::NOTICE_TRANSIENT_PREFIX . max(0, (int) get_current_user_id()));
    }

    private function getSiteTransferStatusTransientKey(): string
    {
        return TransientKey::forSite(self::SITE_TRANSFER_STATUS_TRANSIENT_PREFIX . max(0, (int) get_current_user_id()));
    }

    private function getActionCooldownTransientKey(string $action): string
    {
        return TransientKey::forSite(
            self::ACTION_COOLDOWN_TRANSIENT_PREFIX
            . strtolower(trim(preg_replace('/[^a-z0-9_-]+/i', '_', $action) ?? 'action'))
            . '_'
            . max(0, (int) get_current_user_id())
        );
    }

    private function getSearchCooldownTransientKey(): string
    {
        return TransientKey::forSite(self::SEARCH_COOLDOWN_TRANSIENT_PREFIX . max(0, (int) get_current_user_id()));
    }

    private function getSearchCacheTransientKey(string $provider, string $query): string
    {
        return TransientKey::forSite(
            self::SEARCH_CACHE_TRANSIENT_PREFIX
            . strtolower(trim($provider))
            . '_'
            . max(0, (int) get_current_user_id())
            . '_'
            . md5(strtolower(trim($query)))
        );
    }

    private function isSearchCooldownActive(string $cooldownKey, string $provider, string $query): bool
    {
        $cooldown = get_transient($cooldownKey);

        if (!is_array($cooldown)) {
            return false;
        }

        $expiresAt = $this->floatValue($cooldown, 'expires_at');

        if ($expiresAt <= microtime(true)) {
            delete_transient($cooldownKey);
            return false;
        }

        return strtolower(trim($this->stringValue($cooldown, 'provider'))) === strtolower(trim($provider))
            && strtolower(trim($this->stringValue($cooldown, 'query'))) === strtolower(trim($query));
    }

    private function startRateLimitedActionCooldown(string $action): bool|WP_Error
    {
        $window = FontUtils::scalarFloatValue(apply_filters(
            'tasty_fonts_rest_action_cooldown_window',
            self::ACTION_COOLDOWN_DEFAULT_WINDOW_SECONDS,
            $action,
            max(0, (int) get_current_user_id())
        ));

        if ($window <= 0) {
            return true;
        }

        $cooldownKey = $this->getActionCooldownTransientKey($action);
        $cooldown = get_transient($cooldownKey);
        $expiresAt = is_array($cooldown) ? $this->floatValue($cooldown, 'expires_at') : 0.0;

        if ($expiresAt > microtime(true)) {
            return new WP_Error(
                'tasty_fonts_rest_action_rate_limited',
                __('Please wait a moment before repeating that request.', 'tasty-fonts')
            );
        }

        set_transient(
            $cooldownKey,
            [
                'action' => strtolower(trim($action)),
                'expires_at' => microtime(true) + $window,
            ],
            max(1, (int) ceil($window))
        );

        return true;
    }

    private function handleOversizedSiteTransferUploadRequest(): bool
    {
        if (!$this->isOversizedSiteTransferUploadRequest()) {
            return false;
        }

        $limitLabel = $this->getEffectiveSiteTransferUploadLimitBytes();
        $message = $limitLabel > 0
            ? sprintf(
                __('The uploaded site transfer bundle was larger than this server allows in a single request (%s). Reduce the bundle size or raise the PHP upload limits before trying again.', 'tasty-fonts'),
                size_format($limitLabel)
            )
            : __('The uploaded site transfer bundle was larger than this server allows in a single request. Reduce the bundle size or raise the PHP upload limits before trying again.', 'tasty-fonts');

        $status = [
            'tone' => 'error',
            'title' => __('Site transfer import failed.', 'tasty-fonts'),
            'message' => $message,
            'code' => 'tasty_fonts_transfer_request_too_large',
        ];

        $this->queueSiteTransferStatus($status);
        $this->log->add(
            $this->formatSiteTransferStatusForActivityLog($status),
            $this->buildTransferLogContext(LogRepository::EVENT_SITE_TRANSFER_IMPORT_FAILURE)
        );
        $this->queueNoticeToast('error', $message, 'alert');

        return true;
    }

    private function isOversizedSiteTransferUploadRequest(): bool
    {
        if (strtoupper(FontUtils::scalarStringValue($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            return false;
        }

        if (FontUtils::scalarStringValue($_GET['page'] ?? '') !== self::MENU_SLUG) {
            return false;
        }

        if (!empty($_POST) || !empty($_FILES)) {
            return false;
        }

        $contentType = strtolower(trim(FontUtils::scalarStringValue($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '')));

        if ($contentType !== '' && !str_contains($contentType, 'multipart/form-data')) {
            return false;
        }

        $contentLength = max(0, FontUtils::scalarIntValue($_SERVER['CONTENT_LENGTH'] ?? 0));
        $postMaxSize = $this->parsePhpIniSizeToBytes((string) ini_get('post_max_size'));

        return $contentLength > 0 && $postMaxSize > 0 && $contentLength > $postMaxSize;
    }

    private function getEffectiveSiteTransferUploadLimitBytes(): int
    {
        $limits = array_filter([
            $this->parsePhpIniSizeToBytes((string) ini_get('upload_max_filesize')),
            $this->parsePhpIniSizeToBytes((string) ini_get('post_max_size')),
        ]);

        if ($limits === []) {
            return 0;
        }

        return (int) min($limits);
    }

    private function parsePhpIniSizeToBytes(string $value): int
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return 0;
        }

        if (!preg_match('/^(\d+(?:\.\d+)?)\s*([gmk])?b?$/', $normalized, $matches)) {
            return 0;
        }

        $bytes = (float) $matches[1];
        $unit = $matches[2] ?? '';

        return match ($unit) {
            'g' => (int) round($bytes * 1024 * 1024 * 1024),
            'm' => (int) round($bytes * 1024 * 1024),
            'k' => (int) round($bytes * 1024),
            default => (int) round($bytes),
        };
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

    private function buildAdminPageUrl(?string $pageType = null): string
    {
        $resolvedPageType = $pageType ?? $this->resolveRequestedPageType();

        return add_query_arg(
            array_merge(
                ['page' => self::MENU_SLUG],
                $this->buildTrackedUiQueryArgs($resolvedPageType)
            ),
            admin_url('admin.php')
        );
    }

    private function buildSiteTransferDownloadUrl(): string
    {
        return add_query_arg(
            [
                'page' => self::MENU_SLUG,
                'tf_page' => self::PAGE_SETTINGS,
                'tf_studio' => 'transfer',
                self::ACTION_DOWNLOAD_SITE_TRANSFER_BUNDLE => '1',
                '_wpnonce' => wp_create_nonce(self::ACTION_DOWNLOAD_SITE_TRANSFER_BUNDLE),
            ],
            admin_url('admin.php')
        );
    }

    /**
     * @return array<string, string>
     */
    private function buildTransferLogContext(string $event): array
    {
        return [
            'category' => LogRepository::CATEGORY_TRANSFER,
            'event' => $event,
        ];
    }

    /**
     * @param array<string, scalar|null> $queryArgs
     */
    private function buildPageUrl(string $pageType, array $queryArgs = []): string
    {
        return add_query_arg(
            array_merge(
                ['page' => self::MENU_SLUG],
                $pageType === self::PAGE_ROLES ? [] : ['tf_page' => $pageType],
                $queryArgs
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

    /**
     * @return array<string, string>
     */
    private function buildTrackedUiQueryArgs(string $pageType): array
    {
        $args = $pageType === self::PAGE_ROLES ? [] : ['tf_page' => $pageType];
        $advancedOpen = $this->getQueryText('tf_advanced') === '1';
        $studio = $this->getAllowedTrackedUiTabValue('tf_studio');
        $preview = $this->getAllowedTrackedUiTabValue('tf_preview');
        $output = $this->getAllowedTrackedUiTabValue('tf_output');
        $addFontsOpen = $this->getQueryText('tf_add_fonts') === '1';
        $source = $this->getAllowedTrackedUiTabValue('tf_source');
        $googleAccessOpen = $this->getQueryText('tf_google_access') === '1';
        $adobeProjectOpen = $this->getQueryText('tf_adobe_project') === '1';

        if ($pageType === self::PAGE_ROLES && ($advancedOpen || $studio === 'preview' || $studio === 'snippets' || $preview !== '' || $output !== '')) {
            $args['tf_advanced'] = '1';
            $args['tf_studio'] = $studio === 'snippets' || ($preview === '' && $output !== '') ? 'snippets' : 'preview';

            if ($args['tf_studio'] === 'preview' && $preview !== '') {
                $args['tf_preview'] = $preview;
            }

            if ($args['tf_studio'] === 'snippets' && $output !== '') {
                $args['tf_output'] = $output;
            }
        }

        if ($pageType === self::PAGE_SETTINGS && in_array($studio, self::SETTINGS_STUDIO_TABS, true)) {
            $args['tf_studio'] = $studio;
        }

        if ($pageType === self::PAGE_DIAGNOSTICS && in_array($studio, ['generated', 'system', 'activity'], true)) {
            $args['tf_studio'] = $studio;

        }

        if ($pageType === self::PAGE_ROLES && $studio === 'snippets' && $output !== '') {
            $args['tf_output'] = $output;
        }

        if ($pageType === self::PAGE_LIBRARY && ($addFontsOpen || $source !== '')) {
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
            'tf_page' => in_array($value, [self::PAGE_ROLES, self::PAGE_LIBRARY, self::PAGE_SETTINGS, self::PAGE_DIAGNOSTICS], true) ? $value : '',
            'tf_studio' => in_array($value, array_merge(['preview', 'snippets', 'generated', 'system', 'activity'], self::SETTINGS_STUDIO_TABS), true) ? $value : '',
            'tf_preview' => in_array($value, ['editorial', 'card', 'reading', 'interface', 'code'], true) ? $value : '',
            'tf_output' => in_array($value, ['usage', 'variables', 'stacks', 'names'], true) ? $value : '',
            'tf_source' => in_array($value, ['google', 'bunny', 'adobe', 'upload'], true) ? $value : '',
            default => '',
        };
    }

    private function getCurrentPageSlug(): string
    {
        $page = $this->getQueryText('page', self::MENU_SLUG);

        return self::isPluginPageSlug($page) ? $page : self::MENU_SLUG;
    }

    private function resolveRequestedPageType(): string
    {
        $pageSlug = $this->getCurrentPageSlug();

        if ($pageSlug !== self::MENU_SLUG) {
            return self::pageTypeForSlug($pageSlug);
        }

        $pageType = $this->getAllowedTrackedUiTabValue('tf_page');

        if ($pageType !== '') {
            return $pageType;
        }

        if ($this->getQueryText('tf_add_fonts') === '1' || $this->getAllowedTrackedUiTabValue('tf_source') !== '') {
            return self::PAGE_LIBRARY;
        }

        $studio = $this->getAllowedTrackedUiTabValue('tf_studio');

        if (in_array($studio, self::SETTINGS_STUDIO_TABS, true)) {
            return self::PAGE_SETTINGS;
        }

        if ($this->getQueryText('tf_advanced') === '1' && in_array($studio, ['preview', 'snippets'], true)) {
            return self::PAGE_ROLES;
        }

        if (in_array($studio, ['generated', 'system', 'activity'], true)) {
            return self::PAGE_DIAGNOSTICS;
        }

        return self::PAGE_ROLES;
    }

    private function shouldRedirectNonCanonicalPageRequest(): bool
    {
        return $this->getCurrentPageSlug() !== self::MENU_SLUG;
    }

    private function redirect(): never
    {
        wp_safe_redirect($this->buildAdminPageUrl());
        do_action('tasty_fonts_before_admin_redirect_exit');
        exit;
    }

    public static function isPluginAdminHook(string $hookSuffix): bool
    {
        foreach (self::pluginPageSlugs() as $pageSlug) {
            if ($hookSuffix === 'toplevel_page_' . $pageSlug) {
                return true;
            }

            if (str_ends_with($hookSuffix, '_page_' . $pageSlug)) {
                return true;
            }
        }

        return false;
    }

    public static function isPluginPageSlug(string $pageSlug): bool
    {
        return in_array($pageSlug, self::pluginPageSlugs(), true);
    }

    public static function pageSlugForType(string $pageType): string
    {
        return match ($pageType) {
            self::PAGE_LIBRARY => self::MENU_SLUG_LIBRARY,
            self::PAGE_SETTINGS => self::MENU_SLUG_SETTINGS,
            self::PAGE_DIAGNOSTICS => self::MENU_SLUG_DIAGNOSTICS,
            default => self::MENU_SLUG,
        };
    }

    public static function pageTypeForSlug(string $pageSlug): string
    {
        return match ($pageSlug) {
            self::MENU_SLUG_LIBRARY => self::PAGE_LIBRARY,
            self::MENU_SLUG_SETTINGS => self::PAGE_SETTINGS,
            self::MENU_SLUG_DIAGNOSTICS => self::PAGE_DIAGNOSTICS,
            default => self::PAGE_ROLES,
        };
    }

    /**
     * @return string[]
     */
    /**
     * @return list<string>
     */
    private static function pluginPageSlugs(): array
    {
        return [
            self::MENU_SLUG,
            self::MENU_SLUG_LIBRARY,
            self::MENU_SLUG_SETTINGS,
            self::MENU_SLUG_DIAGNOSTICS,
        ];
    }
}
