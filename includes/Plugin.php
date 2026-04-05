<?php

declare(strict_types=1);

namespace TastyFonts;

use TastyFonts\Adobe\AdobeCssParser;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Admin\AdminController;
use TastyFonts\Bunny\BunnyCssParser;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Bunny\BunnyImportService;
use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Fonts\FontFilenameParser;
use TastyFonts\Fonts\LibraryService;
use TastyFonts\Fonts\LocalUploadService;
use TastyFonts\Fonts\RuntimeAssetPlanner;
use TastyFonts\Fonts\RuntimeService;
use TastyFonts\Google\GoogleCssParser;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Google\GoogleImportService;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\Storage;

final class Plugin
{
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
    private readonly RuntimeService $runtime;
    private readonly AdminController $admin;

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
            $this->log
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
        $this->runtime = new RuntimeService($this->planner, $this->assets, $this->adobe);
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
            $this->googleImport
        );
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

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;
        $this->registerRuntimeHooks();
        $this->registerAdminHooks();
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
        add_action('plugins_loaded', [$this, 'loadTextdomain']);
        add_action('wp_enqueue_scripts', [$this->runtime, 'enqueueFrontend']);
        add_action('wp_head', [$this->runtime, 'outputPreloadHints'], 1);
        add_action('etch/canvas/enqueue_assets', [$this->runtime, 'enqueueEtchCanvas']);
        add_action('enqueue_block_editor_assets', [$this->runtime, 'enqueueBlockEditor']);
        add_action('admin_enqueue_scripts', [$this->runtime, 'enqueueAdminScreenFonts']);
        add_filter('wp_theme_json_data_theme', [$this->runtime, 'injectEditorFontPresets']);
    }

    private function registerAdminHooks(): void
    {
        add_action('admin_menu', [$this->admin, 'registerMenu']);
        add_action('admin_init', [$this->admin, 'handleAdminActions']);
        add_action('admin_enqueue_scripts', [$this->admin, 'enqueueAssets']);
        add_filter('plugin_action_links_' . plugin_basename(TASTY_FONTS_FILE), [self::class, 'filterPluginActionLinks']);
        add_action('wp_ajax_tasty_fonts_search_google', [$this->admin, 'ajaxSearchGoogle']);
        add_action('wp_ajax_tasty_fonts_search_bunny', [$this->admin, 'ajaxSearchBunny']);
        add_action('wp_ajax_tasty_fonts_get_bunny_family', [$this->admin, 'ajaxGetBunnyFamily']);
        add_action('wp_ajax_tasty_fonts_import_bunny', [$this->admin, 'ajaxImportBunny']);
        add_action('wp_ajax_tasty_fonts_import_google', [$this->admin, 'ajaxImportGoogle']);
        add_action('wp_ajax_tasty_fonts_upload_local', [$this->admin, 'ajaxUploadLocal']);
        add_action('wp_ajax_tasty_fonts_save_family_fallback', [$this->admin, 'ajaxSaveFamilyFallback']);
        add_action('wp_ajax_tasty_fonts_save_family_font_display', [$this->admin, 'ajaxSaveFamilyFontDisplay']);
        add_action('wp_ajax_tasty_fonts_save_family_delivery', [$this->admin, 'ajaxSaveFamilyDelivery']);
        add_action('wp_ajax_tasty_fonts_save_family_publish_state', [$this->admin, 'ajaxSaveFamilyPublishState']);
        add_action('wp_ajax_tasty_fonts_delete_delivery_profile', [$this->admin, 'ajaxDeleteDeliveryProfile']);
        add_action('wp_ajax_tasty_fonts_save_role_draft', [$this->admin, 'ajaxSaveRoleDraft']);
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
            ] as $path
        ) {
            if (!file_exists($path)) {
                $this->storage->writeAbsoluteFile($path, $stub);
            }
        }
    }
}
