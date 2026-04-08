<?php

declare(strict_types=1);

namespace TastyFonts;

defined('ABSPATH') || exit;

use TastyFonts\Adobe\AdobeCssParser;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Admin\AdminController;
use TastyFonts\Api\RestController;
use TastyFonts\Bunny\BunnyCssParser;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Bunny\BunnyImportService;
use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\BlockEditorFontLibraryService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Fonts\FontFilenameParser;
use TastyFonts\Fonts\LibraryService;
use TastyFonts\Fonts\LocalUploadService;
use TastyFonts\Fonts\NativeUploadedFileValidator;
use TastyFonts\Fonts\RuntimeAssetPlanner;
use TastyFonts\Fonts\RuntimeService;
use TastyFonts\Google\GoogleCssParser;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Google\GoogleImportService;
use TastyFonts\Integrations\AcssIntegrationService;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\Storage;
use TastyFonts\Updates\GitHubUpdater;

final class Plugin
{
    private const REPOSITORY_URL = 'https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts';
    private const SUPPORT_URL = self::REPOSITORY_URL . '/issues';
    private const RELEASES_URL = self::REPOSITORY_URL . '/releases';
    private const TRANSIENT_KEYS = [
        CatalogService::TRANSIENT_CATALOG,
        AssetService::TRANSIENT_CSS,
        AssetService::TRANSIENT_HASH,
        AssetService::TRANSIENT_REGENERATE_CSS_QUEUED,
        GoogleFontsClient::TRANSIENT_CATALOG,
        BunnyFontsClient::TRANSIENT_CATALOG,
        'tasty_fonts_github_release_v1',
        'tasty_fonts_github_release_version_v1',
    ];

    private static ?self $instance = null;

    private bool $booted = false;

    private readonly Storage $storage;
    private readonly SettingsRepository $settings;
    private readonly ImportRepository $imports;
    private readonly LogRepository $log;
    private readonly CatalogService $catalog;
    private readonly CssBuilder $cssBuilder;
    private readonly RuntimeAssetPlanner $planner;
    private readonly AssetService $assets;
    private readonly LibraryService $library;
    private readonly LocalUploadService $localUpload;
    private readonly AdobeProjectClient $adobe;
    private readonly BunnyFontsClient $bunnyClient;
    private readonly BunnyImportService $bunnyImport;
    private readonly GoogleFontsClient $googleClient;
    private readonly GoogleImportService $googleImport;
    private readonly AcssIntegrationService $acssIntegration;
    private readonly RuntimeService $runtime;
    private readonly BlockEditorFontLibraryService $blockEditorFontLibrary;
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
        $this->settings = new SettingsRepository();
        $this->imports = new ImportRepository();
        $this->log = new LogRepository();
        $this->adobe = new AdobeProjectClient($this->settings, $adobeCssParser);
        $this->bunnyClient = new BunnyFontsClient();
        $this->googleClient = new GoogleFontsClient($this->settings);
        $this->catalog = new CatalogService(
            $this->storage,
            $this->imports,
            $parser,
            $this->log,
            $this->adobe
        );
        $this->cssBuilder = new CssBuilder();
        $this->planner = new RuntimeAssetPlanner(
            $this->catalog,
            $this->settings,
            $this->googleClient,
            $this->bunnyClient,
            $this->adobe
        );
        $this->assets = new AssetService(
            $this->storage,
            $this->catalog,
            $this->settings,
            $this->cssBuilder,
            $this->planner,
            $this->log
        );
        $this->library = new LibraryService(
            $this->storage,
            $this->catalog,
            $this->imports,
            $this->assets,
            $this->log,
            $this->settings
        );
        $this->localUpload = new LocalUploadService(
            $this->storage,
            $this->catalog,
            $this->assets,
            $this->settings,
            $this->log,
            new NativeUploadedFileValidator()
        );
        $this->bunnyImport = new BunnyImportService(
            $this->storage,
            $this->imports,
            $this->bunnyClient,
            $bunnyCssParser,
            $this->catalog,
            $this->assets,
            $this->log
        );
        $this->googleImport = new GoogleImportService(
            $this->storage,
            $this->imports,
            $this->googleClient,
            $googleCssParser,
            $this->catalog,
            $this->assets,
            $this->log
        );
        $this->acssIntegration = new AcssIntegrationService();
        $this->runtime = new RuntimeService($this->planner, $this->assets, $this->adobe);
        $this->blockEditorFontLibrary = new BlockEditorFontLibraryService(
            $this->storage,
            $this->imports,
            $this->settings,
            $this->log
        );
        $this->updater = new GitHubUpdater();
        $this->admin = new AdminController(
            $this->storage,
            $this->settings,
            $this->log,
            $this->catalog,
            $this->assets,
            $this->library,
            $this->localUpload,
            $this->cssBuilder,
            $this->adobe,
            $this->bunnyClient,
            $this->bunnyImport,
            $this->googleClient,
            $this->googleImport,
            $this->acssIntegration
        );
        $this->rest = new RestController($this->admin);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void
    {
        self::instance()->onActivate();
    }

    public static function deactivate(): void
    {
        foreach (self::TRANSIENT_KEYS as $transientKey) {
            delete_transient($transientKey);
        }

        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(AssetService::ACTION_REGENERATE_CSS);
        }
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
        add_action(AssetService::ACTION_REGENERATE_CSS, [$this->assets, 'ensureGeneratedCssFile']);
        add_action('wp_enqueue_scripts', [$this->runtime, 'enqueueFrontend']);
        add_action('wp_head', [$this->runtime, 'outputPreloadHints'], 1);
        add_action('etch/canvas/enqueue_assets', [$this->runtime, 'enqueueEtchCanvas']);
        add_action('enqueue_block_editor_assets', [$this->runtime, 'enqueueBlockEditor']);
        add_action('enqueue_block_assets', [$this->runtime, 'enqueueBlockEditorContent']);
        add_action('admin_enqueue_scripts', [$this->runtime, 'enqueueAdminScreenFonts']);
        add_filter('wp_theme_json_data_theme', [$this->runtime, 'injectEditorFontPresets']);
        add_filter('style_loader_tag', [$this->runtime, 'filterExternalStylesheetTag'], 10, 4);
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
        $this->storage->get();
        $this->ensureIndexFiles();
        $this->assets->ensureGeneratedCssFile();
    }

    private function ensureIndexFiles(): void
    {
        $root = $this->storage->getRoot();

        if (!$root) {
            return;
        }

        $stub = '<?php // Silence is golden.';

        foreach (
            [
                trailingslashit($root) . 'index.php',
                trailingslashit($root) . 'google/index.php',
                trailingslashit($root) . 'bunny/index.php',
                trailingslashit($root) . 'upload/index.php',
                trailingslashit($root) . 'adobe/index.php',
                trailingslashit($root) . '.generated/index.php',
            ] as $path
        ) {
            if (!file_exists($path)) {
                $this->storage->writeAbsoluteFile($path, $stub);
            }
        }
    }
}
