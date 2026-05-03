<?php

declare(strict_types=1);

namespace TastyFonts;

defined('ABSPATH') || exit;

use TastyFonts\Adobe\AdobeCssParser;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Admin\AdminAccessService;
use TastyFonts\Admin\AdminActionRunner;
use TastyFonts\Admin\AdminController;
use TastyFonts\Api\RestController;
use TastyFonts\Bunny\BunnyCssParser;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Bunny\BunnyImportService;
use TastyFonts\Cli\Command as CliCommand;
use TastyFonts\CustomCss\CustomCssFinalImportService;
use TastyFonts\CustomCss\CustomCssFontValidator;
use TastyFonts\CustomCss\CustomCssImportSnapshotService;
use TastyFonts\CustomCss\CustomCssUrlImportService;
use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\AdobeStylesheetResolver;
use TastyFonts\Fonts\BlockEditorFontLibraryService;
use TastyFonts\Fonts\BunnyStylesheetResolver;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\CapabilityDisableCleanupService;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Fonts\FontFilenameParser;
use TastyFonts\Fonts\GoogleStylesheetResolver;
use TastyFonts\Fonts\CdnImportStrategy;
use TastyFonts\Fonts\HostedImportVariantPlanner;
use TastyFonts\Fonts\HostedImportWorkflow;
use TastyFonts\Fonts\LibraryService;
use TastyFonts\Fonts\SelfHostedImportStrategy;
use TastyFonts\Fonts\LocalUploadService;
use TastyFonts\Fonts\NativeUploadedFileValidator;
use TastyFonts\Fonts\RoleFamilyCatalogBuilder;
use TastyFonts\Fonts\RuntimeAssetPlanner;
use TastyFonts\Fonts\RuntimeService;
use TastyFonts\Google\GoogleCssParser;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Google\GoogleImportService;
use TastyFonts\Integrations\AcssIntegrationService;
use TastyFonts\Integrations\BricksIntegrationService;
use TastyFonts\Integrations\OxygenIntegrationService;
use TastyFonts\Maintenance\DeveloperToolsService;
use TastyFonts\Maintenance\SnapshotService;
use TastyFonts\Maintenance\SiteTransferService;
use TastyFonts\Maintenance\SupportBundleService;
use TastyFonts\Repository\AdobeProjectRepository;
use TastyFonts\Repository\FamilyMetadataRepository;
use TastyFonts\Repository\GoogleApiKeyRepository;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\RoleRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\Storage;
use TastyFonts\Updates\GitHubUpdater;

final class Plugin
{
    private const REPOSITORY_URL = 'https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts';
    private const SUPPORT_URL = self::REPOSITORY_URL . '/issues';
    private const RELEASES_URL = self::REPOSITORY_URL . '/releases';

    private static ?self $instance = null;

    private bool $booted = false;

    private readonly Storage $storage;
    private readonly SettingsRepository $settings;
    private readonly AdminAccessService $adminAccess;
    private readonly ImportRepository $imports;
    private readonly LogRepository $log;
    private readonly CatalogService $catalog;
    private readonly CssBuilder $cssBuilder;
    private readonly RuntimeAssetPlanner $planner;
    private readonly AssetService $assets;
    private readonly HostedImportWorkflow $hostedImportWorkflow;
    private readonly LibraryService $library;
    private readonly CapabilityDisableCleanupService $capabilityCleanup;
    private readonly LocalUploadService $localUpload;
    private readonly AdobeProjectClient $adobe;
    private readonly BunnyFontsClient $bunnyClient;
    private readonly BunnyImportService $bunnyImport;
    private readonly GoogleFontsClient $googleClient;
    private readonly GoogleImportService $googleImport;
    private readonly AcssIntegrationService $acssIntegration;
    private readonly BricksIntegrationService $bricksIntegration;
    private readonly OxygenIntegrationService $oxygenIntegration;
    private readonly RuntimeService $runtime;
    private readonly BlockEditorFontLibraryService $blockEditorFontLibrary;
    private readonly CustomCssUrlImportService $customCssImport;
    private readonly CustomCssImportSnapshotService $customCssSnapshots;
    private readonly CustomCssFinalImportService $customCssFinalImport;
    private readonly DeveloperToolsService $developerTools;
    private readonly SiteTransferService $siteTransfer;
    private readonly SnapshotService $snapshots;
    private readonly SupportBundleService $supportBundles;
    private readonly AdminController $admin;
    private readonly RestController $rest;
    private readonly GitHubUpdater $updater;

    private function __construct()
    {
        $parser = new FontFilenameParser();
        $adobeCssParser = new AdobeCssParser();
        $bunnyCssParser = new BunnyCssParser();
        $googleCssParser = new GoogleCssParser();

        $this->storage = new Storage();
        $googleApiKeyRepo = new GoogleApiKeyRepository();
        $adobeProjectRepo = new AdobeProjectRepository();
        $roleRepo = new RoleRepository();
        $familyMetadataRepo = new FamilyMetadataRepository();
        $this->settings = new SettingsRepository(
            $googleApiKeyRepo,
            $adobeProjectRepo,
            $roleRepo,
            $familyMetadataRepo
        );
        $this->adminAccess = new AdminAccessService($this->settings);
        $this->imports = new ImportRepository();
        $this->log = new LogRepository();
        $this->adobe = new AdobeProjectClient($this->settings, $adobeProjectRepo, $adobeCssParser);
        $this->bunnyClient = new BunnyFontsClient();
        $this->googleClient = new GoogleFontsClient($this->settings, $googleApiKeyRepo);
        $this->catalog = new CatalogService(
            $this->storage,
            $this->imports,
            $parser,
            $this->log,
            $this->adobe
        );
        $this->cssBuilder = new CssBuilder();
        $googleStylesheetResolver = new GoogleStylesheetResolver($this->googleClient);
        $bunnyStylesheetResolver = new BunnyStylesheetResolver($this->bunnyClient);
        $adobeStylesheetResolver = new AdobeStylesheetResolver($this->adobe);
        $this->planner = new RuntimeAssetPlanner(
            $this->catalog,
            $this->settings,
            [$googleStylesheetResolver, $bunnyStylesheetResolver, $adobeStylesheetResolver],
            $roleRepo,
            $familyMetadataRepo
        );
        $this->assets = new AssetService(
            $this->storage,
            $this->catalog,
            $this->settings,
            $this->cssBuilder,
            $this->planner,
            $this->log,
            $roleRepo
        );
        $this->hostedImportWorkflow = new HostedImportWorkflow(
            $this->imports,
            $this->assets,
            $this->log,
            new HostedImportVariantPlanner(),
            [
                'cdn' => new CdnImportStrategy(),
                'self_hosted' => new SelfHostedImportStrategy($this->storage),
            ]
        );
        $this->library = new LibraryService(
            $this->storage,
            $this->catalog,
            $this->imports,
            $this->assets,
            $this->log,
            $this->settings,
            $roleRepo
        );
        $this->capabilityCleanup = new CapabilityDisableCleanupService(
            $this->storage,
            $this->imports,
            $this->catalog,
            $this->log
        );
        $this->localUpload = new LocalUploadService(
            $this->storage,
            $this->catalog,
            $this->imports,
            $this->assets,
            $this->settings,
            $this->log,
            new NativeUploadedFileValidator(),
            $familyMetadataRepo
        );
        $this->bunnyImport = new BunnyImportService(
            $this->bunnyClient,
            $bunnyCssParser,
            $this->hostedImportWorkflow
        );
        $this->googleImport = new GoogleImportService(
            $this->googleClient,
            $googleCssParser,
            $this->hostedImportWorkflow
        );
        $this->acssIntegration = new AcssIntegrationService();
        $this->bricksIntegration = new BricksIntegrationService();
        $this->oxygenIntegration = new OxygenIntegrationService();
        $roleFamilyCatalogBuilder = new RoleFamilyCatalogBuilder();
        $this->runtime = new RuntimeService(
            $this->planner,
            $this->assets,
            $this->cssBuilder,
            $this->adobe,
            $this->settings,
            $roleRepo,
            $this->acssIntegration,
            $this->bricksIntegration,
            $this->oxygenIntegration,
            [$this->acssIntegration, $this->bricksIntegration, $this->oxygenIntegration],
            $this->catalog,
            $roleFamilyCatalogBuilder,
            $this->adminAccess
        );
        $this->blockEditorFontLibrary = new BlockEditorFontLibraryService(
            $this->storage,
            $this->imports,
            $this->settings,
            $this->log
        );
        $customCssValidator = new CustomCssFontValidator();
        $this->customCssImport = new CustomCssUrlImportService($this->imports, $customCssValidator);
        $this->customCssSnapshots = new CustomCssImportSnapshotService();
        $this->customCssFinalImport = new CustomCssFinalImportService(
            $this->storage,
            $this->imports,
            $familyMetadataRepo,
            $this->catalog,
            $this->assets,
            $this->log,
            $customCssValidator
        );
        $this->developerTools = new DeveloperToolsService(
            $this->storage,
            $this->settings,
            $this->imports,
            $this->catalog,
            $this->assets,
            $this->blockEditorFontLibrary,
            $this->googleClient,
            $familyMetadataRepo
        );
        $this->siteTransfer = new SiteTransferService(
            $this->storage,
            $this->settings,
            $this->imports,
            $this->log,
            $this->developerTools,
            $this->library,
            $this->blockEditorFontLibrary,
            new NativeUploadedFileValidator(),
            $roleRepo
        );
        $this->snapshots = new SnapshotService(
            $this->storage,
            $this->settings,
            $this->imports,
            $this->developerTools,
            $this->library,
            $this->blockEditorFontLibrary,
            $roleRepo
        );
        $this->supportBundles = new SupportBundleService(
            $this->storage,
            $this->settings,
            $this->imports,
            $this->log
        );
        $this->updater = new GitHubUpdater($this->settings, $this->adminAccess);
        $actionRunner = new AdminActionRunner($this->log);
        $this->admin = new AdminController(
            $this->storage,
            $this->settings,
            $this->log,
            $this->catalog,
            $this->assets,
            $this->library,
            $this->capabilityCleanup,
            $this->localUpload,
            $this->cssBuilder,
            $this->adobe,
            $this->bunnyClient,
            $this->bunnyImport,
            $this->googleClient,
            $this->googleImport,
            $this->acssIntegration,
            $this->bricksIntegration,
            $this->oxygenIntegration,
            $this->developerTools,
            $this->siteTransfer,
            $this->snapshots,
            $this->supportBundles,
            $this->updater,
            $this->adminAccess,
            $this->planner,
            $this->customCssImport,
            $this->customCssSnapshots,
            $this->customCssFinalImport,
            null,
            null,
            null,
            null,
            null,
            null,
            $roleFamilyCatalogBuilder,
            $googleApiKeyRepo,
            $roleRepo,
            $actionRunner
        );
        $this->rest = new RestController($this->admin, $this->adminAccess);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(bool $networkWide = false): void
    {
        if ($networkWide) {
            wp_die(
                esc_html__('Tasty Custom Fonts does not support network-wide activation. Activate it on each site that should manage its own font library.', 'tasty-fonts')
            );
        }

        self::instance()->onActivate();
    }

    public static function deactivate(): void
    {
        self::instance()->developerTools->clearDeactivationCaches();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;
        $this->loadTextdomain();
        $this->registerRuntimeHooks();
        $this->registerAdminHooks();
        $this->registerRestHooks();
        $this->registerCatalogHooks();
        $this->registerCliCommand();
    }

    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'tasty-fonts',
            false,
            dirname(plugin_basename(TASTY_FONTS_FILE)) . '/languages'
        );
    }

    private function registerRuntimeHooks(): void
    {
        add_action(AssetService::ACTION_REGENERATE_CSS, [$this, 'handleGeneratedCssRegeneration']);
        add_action(GoogleFontsClient::ACTION_REVALIDATE_API_KEY, [$this, 'handleGoogleApiKeyRevalidation']);
        add_action('wp_enqueue_scripts', [$this->runtime, 'enqueueFrontend']);
        add_action('wp_enqueue_scripts', [$this->runtime, 'enqueueBricksFrontendOverride'], 1000);
        add_action('wp_enqueue_scripts', [$this->runtime, 'enqueueEtchFrontendOverride'], 1000);
        add_action('wp_enqueue_scripts', [$this->runtime, 'enqueueBricksBuilder'], 100);
        add_action('wp_head', [$this->runtime, 'outputPreloadHints'], 1);
        add_action('etch/canvas/enqueue_assets', [$this->runtime, 'enqueueEtchCanvas']);
        add_action('enqueue_block_editor_assets', [$this->runtime, 'enqueueBlockEditor']);
        add_action('enqueue_block_assets', [$this->runtime, 'enqueueBlockEditorContent']);
        add_action('admin_enqueue_scripts', [$this->runtime, 'enqueueAdminScreenFonts']);
        add_filter('block_editor_settings_all', [$this->runtime, 'filterBlockEditorSettings'], 10, 2);
        add_filter('bricks/builder/standard_fonts', [$this->runtime, 'filterBricksStandardFonts']);
        add_filter('wp_theme_json_data_theme', [$this->runtime, 'injectEditorFontPresets']);
        add_filter('style_loader_tag', [$this->runtime, 'filterExternalStylesheetTag'], 10, 4);
        $this->runtime->registerOxygenCompatibilityShim();
    }

    private function registerAdminHooks(): void
    {
        add_action('admin_menu', [$this->admin, 'registerMenu']);
        add_action('admin_init', [$this->admin, 'handleAdminActions']);
        add_action('admin_enqueue_scripts', [$this->admin, 'enqueueAssets']);
        add_filter('plugin_action_links_' . plugin_basename(TASTY_FONTS_FILE), [self::class, 'filterPluginActionLinks']);
        add_filter('plugin_row_meta', [self::class, 'filterPluginRowMeta'], 10, 2);
        add_action('tasty_fonts_after_import', [$this->blockEditorFontLibrary, 'syncImportedFamily'], 10, 2);
        add_action('tasty_fonts_after_delete_family', [$this->blockEditorFontLibrary, 'deleteSyncedFamily'], 10, 2);
        $this->updater->registerHooks();
    }

    private function registerRestHooks(): void
    {
        add_action('rest_api_init', [$this->rest, 'registerRoutes']);
    }

    private function registerCliCommand(): void
    {
        if (!defined('WP_CLI') || !WP_CLI || !class_exists('\WP_CLI')) {
            return;
        }

        $cliCommand = new CliCommand($this->admin);

        \WP_CLI::add_command('tasty-fonts', $cliCommand);
        \WP_CLI::add_command('tasty-fonts google-api-key', [$cliCommand, 'googleApiKey']);
        \WP_CLI::add_command('tasty-fonts support-bundle', [$cliCommand, 'supportBundle']);
    }

    /**
     * @param list<string> $links
     * @return list<string>
     */
    public static function filterPluginActionLinks(array $links): array
    {
        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=' . AdminController::MENU_SLUG)),
                __('Settings', 'tasty-fonts')
            )
        );

        return $links;
    }

    /**
     * @param list<string> $links
     * @return list<string>
     */
    public static function filterPluginRowMeta(array $links, string $file): array
    {
        if ($file !== plugin_basename(TASTY_FONTS_FILE)) {
            return $links;
        }

        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url(self::RELEASES_URL),
            __('GitHub Releases', 'tasty-fonts')
        );
        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url(self::SUPPORT_URL),
            __('Support', 'tasty-fonts')
        );

        return $links;
    }

    private function registerCatalogHooks(): void
    {
        add_action('add_attachment', [$this->catalog, 'maybeInvalidateFromAttachment']);
        add_action('delete_attachment', [$this->catalog, 'maybeInvalidateFromAttachment']);
    }

    private function onActivate(): void
    {
        $this->developerTools->ensureStorageScaffolding();
        $this->assets->ensureGeneratedCssFile();
    }

    public function handleGeneratedCssRegeneration(): void
    {
        if (!$this->isCronExecutionContext()) {
            return;
        }

        $this->assets->ensureGeneratedCssFile();
    }

    public function handleGoogleApiKeyRevalidation(): void
    {
        if (!$this->isCronExecutionContext()) {
            return;
        }

        $this->googleClient->revalidateStoredApiKeyStatus();
    }

    private function isCronExecutionContext(): bool
    {
        return wp_doing_cron();
    }
}
