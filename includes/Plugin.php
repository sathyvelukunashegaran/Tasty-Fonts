<?php

declare(strict_types=1);

namespace EtchFonts;

use EtchFonts\Admin\AdminController;
use EtchFonts\Fonts\AssetService;
use EtchFonts\Fonts\CatalogService;
use EtchFonts\Fonts\CssBuilder;
use EtchFonts\Fonts\FontFilenameParser;
use EtchFonts\Fonts\LibraryService;
use EtchFonts\Fonts\LocalUploadService;
use EtchFonts\Fonts\RuntimeService;
use EtchFonts\Google\GoogleCssParser;
use EtchFonts\Google\GoogleFontsClient;
use EtchFonts\Google\GoogleImportService;
use EtchFonts\Repository\ImportRepository;
use EtchFonts\Repository\LogRepository;
use EtchFonts\Repository\SettingsRepository;
use EtchFonts\Support\Storage;

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
    private readonly AssetService $assets;
    private readonly LibraryService $library;
    private readonly LocalUploadService $localUpload;
    private readonly GoogleFontsClient $googleClient;
    private readonly GoogleImportService $googleImport;
    private readonly RuntimeService $runtime;
    private readonly AdminController $admin;

    private function __construct()
    {
        $parser = new FontFilenameParser();
        $googleCssParser = new GoogleCssParser();

        $this->storage = new Storage();
        $this->settings = new SettingsRepository();
        $this->imports = new ImportRepository();
        $this->log = new LogRepository();
        $this->catalog = new CatalogService(
            $this->storage,
            $this->imports,
            $parser,
            $this->log
        );
        $this->cssBuilder = new CssBuilder();
        $this->assets = new AssetService(
            $this->storage,
            $this->catalog,
            $this->settings,
            $this->cssBuilder,
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
        $this->googleClient = new GoogleFontsClient($this->settings);
        $this->googleImport = new GoogleImportService(
            $this->storage,
            $this->imports,
            $this->googleClient,
            $googleCssParser,
            $this->catalog,
            $this->assets,
            $this->log
        );
        $this->runtime = new RuntimeService($this->catalog, $this->assets);
        $this->admin = new AdminController(
            $this->storage,
            $this->settings,
            $this->log,
            $this->catalog,
            $this->assets,
            $this->library,
            $this->localUpload,
            $this->cssBuilder,
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
            ETCH_FONTS_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(ETCH_FONTS_FILE)) . '/languages'
        );
    }

    private function registerRuntimeHooks(): void
    {
        add_action('plugins_loaded', [$this, 'loadTextdomain']);
        add_action('wp_enqueue_scripts', [$this->runtime, 'enqueueFrontend']);
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
        add_action('wp_ajax_etch_fonts_search_google', [$this->admin, 'ajaxSearchGoogle']);
        add_action('wp_ajax_etch_fonts_import_google', [$this->admin, 'ajaxImportGoogle']);
        add_action('wp_ajax_etch_fonts_upload_local', [$this->admin, 'ajaxUploadLocal']);
        add_action('wp_ajax_etch_fonts_save_family_fallback', [$this->admin, 'ajaxSaveFamilyFallback']);
    }

    private function registerCatalogHooks(): void
    {
        add_action('add_attachment', [$this->catalog, 'maybeInvalidateFromAttachment']);
        add_action('delete_attachment', [$this->catalog, 'maybeInvalidateFromAttachment']);
    }

    private function onActivate(): void
    {
        $this->storage->get();
        $this->assets->ensureGeneratedCssFile();
    }
}
