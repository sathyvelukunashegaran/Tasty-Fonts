<?php

declare(strict_types=1);

if (!defined('TASTY_FONTS_VERSION')) {
    $pluginVersion = '6.0.1';
    $pluginFile = dirname(__DIR__, 2) . '/plugin.php';
    $pluginContents = is_readable($pluginFile) ? file_get_contents($pluginFile) : false;

    if (
        is_string($pluginContents)
        && preg_match("/^Version:\\s*(.+)$/m", $pluginContents, $matches) === 1
        && !empty($matches[1])
    ) {
        $pluginVersion = trim((string) $matches[1]);
    }

    define('TASTY_FONTS_VERSION', $pluginVersion);
}

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}

if (!defined('TASTY_FONTS_URL')) {
    define('TASTY_FONTS_URL', 'https://example.test/wp-content/plugins/tasty-fonts/');
}

if (!defined('TASTY_FONTS_DIR')) {
    define('TASTY_FONTS_DIR', dirname(__DIR__, 2) . '/');
}

if (!defined('TASTY_FONTS_FILE')) {
    define('TASTY_FONTS_FILE', dirname(__DIR__, 2) . '/plugin.php');
}

if (!defined('AUTH_KEY')) {
    define('AUTH_KEY', 'test-auth-key');
}

if (!defined('SECURE_AUTH_KEY')) {
    define('SECURE_AUTH_KEY', 'test-secure-auth-key');
}

if (!defined('LOGGED_IN_KEY')) {
    define('LOGGED_IN_KEY', 'test-logged-in-key');
}

if (!defined('NONCE_KEY')) {
    define('NONCE_KEY', 'test-nonce-key');
}

if (!defined('AUTH_SALT')) {
    define('AUTH_SALT', 'test-auth-salt');
}

if (!defined('SECURE_AUTH_SALT')) {
    define('SECURE_AUTH_SALT', 'test-secure-auth-salt');
}

if (!defined('LOGGED_IN_SALT')) {
    define('LOGGED_IN_SALT', 'test-logged-in-salt');
}

if (!defined('NONCE_SALT')) {
    define('NONCE_SALT', 'test-nonce-salt');
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('FS_CHMOD_DIR')) {
    define('FS_CHMOD_DIR', 0755);
}

if (!defined('FS_CHMOD_FILE')) {
    define('FS_CHMOD_FILE', 0644);
}

if (!defined('MB_IN_BYTES')) {
    define('MB_IN_BYTES', 1048576);
}

if (!defined('BRICKS_DB_THEME_STYLES')) {
    define('BRICKS_DB_THEME_STYLES', 'bricks_theme_styles');
}

if (!defined('CT_VERSION')) {
    define('CT_VERSION', '4.0.0');
}

require_once dirname(__DIR__) . '/bootstrap.php';

use TastyFonts\Adobe\AdobeCssParser;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Admin\AdminAccessService;
use TastyFonts\Admin\AdminController;
use TastyFonts\Admin\AdminPageRenderer;
use TastyFonts\Api\RestController;
use TastyFonts\Bunny\BunnyCssParser;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Bunny\BunnyImportService;
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
use TastyFonts\Fonts\CdnImportStrategy;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Fonts\FontFilenameParser;
use TastyFonts\Fonts\HostedImportSupport;
use TastyFonts\Fonts\HostedImportVariantPlanner;
use TastyFonts\Fonts\HostedImportWorkflow;
use TastyFonts\Fonts\LibraryService;
use TastyFonts\Fonts\GoogleStylesheetResolver;
use TastyFonts\Fonts\SelfHostedImportStrategy;
use TastyFonts\Fonts\LocalUploadService;
use TastyFonts\Fonts\RoleFamilyCatalogBuilder;
use TastyFonts\Fonts\UploadedFileValidatorInterface;
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
use TastyFonts\Plugin;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\AdobeProjectRepository;
use TastyFonts\Repository\FamilyMetadataRepository;
use TastyFonts\Repository\GoogleApiKeyRepository;
use TastyFonts\Repository\RoleRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use TastyFonts\Updates\GitHubUpdater;

if (!class_exists('WP_Error')) {
    class WP_Error extends RuntimeException
    {
        public function __construct(
            private readonly string $errorCode = '',
            string $message = '',
            private mixed $errorData = null
        )
        {
            parent::__construct($message);
        }

        public function get_error_message(): string
        {
            return $this->getMessage();
        }

        public function get_error_code(): string
        {
            return $this->errorCode;
        }

        public function get_error_data(): mixed
        {
            return $this->errorData;
        }

        public function add_data(mixed $data): void
        {
            $this->errorData = $data;
        }
    }
}

if (!class_exists('WpDieException')) {
    class WpDieException extends RuntimeException {}
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public function __construct(private mixed $data = null, private int $status = 200)
        {
        }

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        public function set_status(int $status): void
        {
            $this->status = $status;
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private array $params = [];
        private array $fileParams = [];

        public function __construct(private readonly string $method = 'GET', private readonly string $route = '')
        {
        }

        public function get_method(): string
        {
            return $this->method;
        }

        public function get_route(): string
        {
            return $this->route;
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }

        public function get_params(): array
        {
            return $this->params;
        }

        public function set_param(string $key, mixed $value): void
        {
            $this->params[$key] = $value;
        }

        public function set_query_params(array $params): void
        {
            $this->params = array_replace($this->params, $params);
        }

        public function set_body_params(array $params): void
        {
            $this->params = array_replace($this->params, $params);
        }

        public function set_file_params(array $params): void
        {
            $this->fileParams = $params;
        }

        public function get_file_params(): array
        {
            return $this->fileParams;
        }
    }
}

if (!class_exists('Automatic_Upgrader_Skin')) {
    class Automatic_Upgrader_Skin
    {
        private ?WP_Error $error = null;

        public function set_error(WP_Error $error): void
        {
            $this->error = $error;
        }

        public function get_error(): ?WP_Error
        {
            return $this->error;
        }
    }
}

if (!class_exists('Plugin_Upgrader')) {
    class Plugin_Upgrader
    {
        public function __construct(public mixed $skin = null)
        {
        }

        public function install(string $package, array $args = []): mixed
        {
            global $pluginUpgraderInstallCalls;
            global $pluginUpgraderInstallResult;

            $pluginUpgraderInstallCalls[] = [
                'package' => $package,
                'args' => $args,
            ];

            if ($pluginUpgraderInstallResult instanceof WP_Error && $this->skin instanceof Automatic_Upgrader_Skin) {
                $this->skin->set_error($pluginUpgraderInstallResult);
            }

            return $pluginUpgraderInstallResult;
        }
    }
}

if (!function_exists('download_url')) {
    function download_url(string $url, int $timeout = 300, bool $signatureVerification = false): string|WP_Error
    {
        global $downloadUrlCalls;
        global $downloadUrlResponses;

        $downloadUrlCalls[] = [
            'url' => $url,
            'timeout' => $timeout,
            'signature_verification' => $signatureVerification,
        ];

        $response = $downloadUrlResponses[$url] ?? null;

        if ($response instanceof WP_Error) {
            return $response;
        }

        if (is_array($response) && is_string($response['path'] ?? null) && $response['path'] !== '') {
            return $response['path'];
        }

        if (is_array($response) && array_key_exists('body', $response)) {
            $response = (string) $response['body'];
        }

        if (!is_string($response)) {
            return new WP_Error('missing_mock', 'No download_url mock response for ' . $url);
        }

        $directory = uniqueTestDirectory('downloads');
        wp_mkdir_p($directory);
        $filename = $directory . '/' . md5($url . '|' . $response) . '.tmp';

        if (file_put_contents($filename, $response) === false) {
            return new WP_Error('download_write_failed', 'Could not write the downloaded mock payload.');
        }

        return $filename;
    }
}

if (!class_exists('WP_Theme_JSON_Data')) {
    class WP_Theme_JSON_Data
    {
        private array $data;

        public function __construct(array $data = [])
        {
            $this->data = $data;
        }

        public function get_data(): array
        {
            return $this->data;
        }

        public function update_with(array $theme_json): static
        {
            $this->data = array_replace_recursive($this->data, $theme_json);

            return $this;
        }
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        global $translationMap;

        return is_array($translationMap) && array_key_exists($text, $translationMap)
            ? (string) $translationMap[$text]
            : $text;
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt(string $scheme = 'auth'): string
    {
        return match ($scheme) {
            'secure_auth' => SECURE_AUTH_KEY . SECURE_AUTH_SALT,
            'logged_in' => LOGGED_IN_KEY . LOGGED_IN_SALT,
            'nonce' => NONCE_KEY . NONCE_SALT,
            default => AUTH_KEY . AUTH_SALT,
        };
    }
}

if (!function_exists('size_format')) {
    function size_format(int $bytes): string
    {
        if ($bytes >= MB_IN_BYTES) {
            return number_format($bytes / MB_IN_BYTES, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}

if (!function_exists('wp_date')) {
    function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
    {
        $timestamp = is_int($timestamp) ? $timestamp : time();
        $timezone = $timezone ?? wp_timezone();

        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone($timezone)
            ->format($format);
    }
}

if (!function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone
    {
        $timezone = trim((string) get_option('timezone_string', ''));

        if ($timezone !== '') {
            try {
                return new DateTimeZone($timezone);
            } catch (Exception) {
            }
        }

        $offset = (float) get_option('gmt_offset', 0);
        $hours = (int) $offset;
        $minutes = (int) round(abs($offset - $hours) * 60);
        $sign = $offset < 0 ? '-' : '+';

        return new DateTimeZone(sprintf('%s%02d:%02d', $sign, abs($hours), $minutes));
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit(string $value): string
    {
        return rtrim($value, "/\\") . '/';
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit(string $value): string
    {
        return rtrim($value, "/\\");
    }
}

if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}

if (!function_exists('wp_make_link_relative')) {
    function wp_make_link_relative(string $url): string
    {
        $parts = parse_url($url);

        return (string) ($parts['path'] ?? '');
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1): array|string|int|null|false
    {
        return $component === -1 ? parse_url($url) : parse_url($url, $component);
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $path): bool
    {
        return is_dir($path) || mkdir($path, 0777, true);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n\t ]+/', ' ', $value) ?? $value;

        return trim($value);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        $key = strtolower((string) $key);

        return preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '';
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name(string $filename): string
    {
        $filename = trim(str_replace('\\', '/', $filename));
        $filename = basename($filename);
        $filename = preg_replace('/[\r\n\t ]+/', '-', $filename) ?? $filename;
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '', $filename) ?? $filename;
        $filename = preg_replace('/-+/', '-', $filename) ?? $filename;

        return trim($filename, '.-');
    }
}

if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class(string $class, string $fallback = ''): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', $class) ?? '';

        if ($sanitized !== '') {
            return $sanitized;
        }

        return preg_replace('/[^A-Za-z0-9_-]/', '', $fallback) ?? '';
    }
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words(string $text, int $numWords = 55, ?string $more = null): string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));

        if ($normalized === '') {
            return '';
        }

        $words = preg_split('/\s+/', $normalized) ?: [];

        if (count($words) <= $numWords) {
            return $normalized;
        }

        return implode(' ', array_slice($words, 0, max(0, $numWords))) . ($more ?? '&hellip;');
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        if (!is_string($value)) {
            return $value;
        }

        return stripslashes($value);
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        $sanitized = esc_url_raw($url);

        return str_replace(
            ['&', '"', "'"],
            ['&#038;', '&#034;', '&#039;'],
            $sanitized
        );
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e(string $text, string $domain = ''): void
    {
        echo esc_html(__($text, $domain));
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e(string $text, string $domain = ''): void
    {
        echo esc_attr(__($text, $domain));
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = ''): string
    {
        return esc_attr(__($text, $domain));
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = ''): string
    {
        return esc_html(__($text, $domain));
    }
}

if (!function_exists('_n')) {
    function _n(string $single, string $plural, int $number, string $domain = ''): string
    {
        return $number === 1 ? $single : $plural;
    }
}

if (!function_exists('selected')) {
    function selected(mixed $selectedValue, mixed $current = true, bool $echo = true): string
    {
        $result = $selectedValue === $current ? 'selected="selected"' : '';

        if ($echo) {
            echo $result;
        }

        return $result;
    }
}

if (!function_exists('disabled')) {
    function disabled(mixed $disabledValue, mixed $current = true, bool $echo = true): string
    {
        $result = $disabledValue === $current ? 'disabled="disabled"' : '';

        if ($echo) {
            echo $result;
        }

        return $result;
    }
}

if (!function_exists('checked')) {
    function checked(mixed $checkedValue, mixed $current = true, bool $echo = true): string
    {
        $result = $checkedValue === $current ? 'checked="checked"' : '';

        if ($echo) {
            echo $result;
        }

        return $result;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): string
    {
        $field = sprintf(
            '<input type="hidden" name="%s" value="%s">',
            esc_attr($name),
            esc_attr('nonce:' . $action)
        );

        if ($display) {
            echo $field;
        }

        return $field;
    }
}

if (!function_exists('wp_unique_id')) {
    function wp_unique_id(string $prefix = ''): string
    {
        static $counter = 0;

        $counter += 1;

        return $prefix . $counter;
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args(array $args, array $defaults = []): array
    {
        return array_merge($defaults, $args);
    }
}

if (!function_exists('absint')) {
    function absint(mixed $value): int
    {
        return abs((int) $value);
    }
}

if (!function_exists('wp_max_upload_size')) {
    function wp_max_upload_size(): int
    {
        return 16 * MB_IN_BYTES;
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false): string
    {
        $reference = strtotime('2026-04-03 10:00:00 UTC');

        if ($reference === false) {
            return '2026-04-03 10:00:00';
        }

        $timezone = $gmt ? new DateTimeZone('UTC') : wp_timezone();
        $dateTime = (new DateTimeImmutable('@' . $reference))->setTimezone($timezone);

        return match ($type) {
            'mysql' => $dateTime->format('Y-m-d H:i:s'),
            default => (string) $dateTime->getTimestamp(),
        };
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        global $isUserLoggedIn;
        global $currentUserId;

        if (is_bool($isUserLoggedIn ?? null)) {
            return $isUserLoggedIn;
        }

        return (int) ($currentUserId ?? 0) > 0;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $value): bool
    {
        return $value instanceof WP_Error;
    }
}

$tests = [];
$optionStore = [];
$optionDeleted = [];
$optionAutoload = [];
$transientStore = [];
$transientDeleted = [];
$transientSet = [];
$siteTransientStore = [];
$siteTransientDeleted = [];
$siteTransientSet = [];
$uploadBaseDir = sys_get_temp_dir() . '/tasty-fonts-tests/uploads';
$uploadedFilePaths = [];
$currentUserId = 1;
$currentUserRoles = null;
$currentUserCapabilities = ['manage_options' => true];
$registeredRoleNames = [
    'administrator' => 'Administrator',
    'editor' => 'Editor',
    'author' => 'Author',
    'contributor' => 'Contributor',
    'subscriber' => 'Subscriber',
];
$testUsers = [
    1 => ['ID' => 1, 'user_login' => 'admin', 'display_name' => 'Admin User', 'roles' => ['administrator']],
    2 => ['ID' => 2, 'user_login' => 'editor', 'display_name' => 'Editor User', 'roles' => ['editor']],
    3 => ['ID' => 3, 'user_login' => 'author', 'display_name' => 'Author User', 'roles' => ['author']],
    4 => ['ID' => 4, 'user_login' => 'contributor', 'display_name' => 'Contributor User', 'roles' => ['contributor']],
];
$pluginUpgraderInstallCalls = [];
$pluginUpgraderInstallResult = true;
$wpdb = null;
$wpdbQueries = [];
$wp_filesystem = null;
$remoteGetResponses = [];
$remoteGetCalls = [];
$remoteRequestResponses = [];
$remoteRequestCalls = [];
$enqueuedStyles = [];
$registeredStyles = [];
$styleData = [];
$inlineStyles = [];
$enqueuedScripts = [];
$inlineScripts = [];
$localizedScripts = [];
$scriptTranslations = [];
$loadedTextdomains = [];
$redirectLocation = '';
$isAdminRequest = false;
$isDoingCron = false;
$hookCallbacks = [];
$actionCounts = [];
$actionCalls = [];
$registeredRestRoutes = [];
$scheduledEvents = [];
$clearedScheduledHooks = [];
$supportedPostTypes = ['wp_font_family', 'wp_font_face'];
$attachedFilePaths = [];

if (!function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        global $optionStore;

        return $optionStore[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, mixed $value, bool $autoload = false): bool
    {
        global $optionAutoload;
        global $optionStore;

        $optionStore[$option] = $value;
        $optionAutoload[$option] = $autoload;

        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option(string $option, mixed $value = '', string $deprecated = '', bool $autoload = false): bool
    {
        global $optionAutoload;
        global $optionStore;

        if (array_key_exists($option, $optionStore)) {
            return false;
        }

        $optionStore[$option] = $value;
        $optionAutoload[$option] = $autoload;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool
    {
        global $optionAutoload;
        global $optionDeleted;
        global $optionStore;

        $optionDeleted[] = $option;
        unset($optionAutoload[$option]);
        unset($optionStore[$option]);

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient(string $key): mixed
    {
        global $transientStore;

        return $transientStore[$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $key, mixed $value, int $expiration): bool
    {
        global $transientStore;
        global $transientSet;

        $transientStore[$key] = $value;
        $transientSet[] = $key;

        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $key): bool
    {
        global $transientDeleted;
        global $transientStore;

        $transientDeleted[] = $key;
        unset($transientStore[$key]);

        return true;
    }
}

if (!function_exists('get_site_transient')) {
    function get_site_transient(string $key): mixed
    {
        global $siteTransientStore;

        return $siteTransientStore[$key] ?? false;
    }
}

if (!function_exists('set_site_transient')) {
    function set_site_transient(string $key, mixed $value, int $expiration): bool
    {
        global $siteTransientStore;
        global $siteTransientSet;

        $siteTransientStore[$key] = $value;
        $siteTransientSet[] = $key;

        return true;
    }
}

if (!function_exists('delete_site_transient')) {
    function delete_site_transient(string $key): bool
    {
        global $siteTransientDeleted;
        global $siteTransientStore;

        $siteTransientDeleted[] = $key;
        unset($siteTransientStore[$key]);

        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hookName, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        global $hookCallbacks;

        $hookCallbacks[$hookName][$priority][] = [
            'callback' => $callback,
            'accepted_args' => $acceptedArgs,
        ];

        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hookName, mixed $value, mixed ...$args): mixed
    {
        global $hookCallbacks;

        if (!isset($hookCallbacks[$hookName]) || !is_array($hookCallbacks[$hookName])) {
            return $value;
        }

        ksort($hookCallbacks[$hookName]);

        foreach ($hookCallbacks[$hookName] as $callbacks) {
            foreach ((array) $callbacks as $callback) {
                if (!is_array($callback) || !isset($callback['callback'])) {
                    continue;
                }

                $acceptedArgs = max(1, (int) ($callback['accepted_args'] ?? 1));
                $value = ($callback['callback'])(...array_slice([$value, ...$args], 0, $acceptedArgs));
            }
        }

        return $value;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hookName, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return add_filter($hookName, $callback, $priority, $acceptedArgs);
    }
}

if (!function_exists('add_menu_page')) {
    function add_menu_page(
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $menuSlug,
        ?callable $callback = null,
        string $iconUrl = '',
        int|float|null $position = null
    ): string {
        global $menuPageCalls;

        $menuPageCalls[] = [
            'page_title' => $pageTitle,
            'menu_title' => $menuTitle,
            'capability' => $capability,
            'menu_slug' => $menuSlug,
            'callback' => $callback,
            'icon_url' => $iconUrl,
            'position' => $position,
        ];

        return 'toplevel_page_' . $menuSlug;
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page(
        string $parentSlug,
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $menuSlug,
        ?callable $callback = null,
        int|float|null $position = null
    ): string {
        global $submenuPageCalls;

        $submenuPageCalls[] = [
            'parent_slug' => $parentSlug,
            'page_title' => $pageTitle,
            'menu_title' => $menuTitle,
            'capability' => $capability,
            'menu_slug' => $menuSlug,
            'callback' => $callback,
            'position' => $position,
        ];

        return $parentSlug . '_page_' . $menuSlug;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hookName, mixed ...$args): void
    {
        global $actionCalls;
        global $actionCounts;
        global $hookCallbacks;

        $actionCounts[$hookName] = (int) ($actionCounts[$hookName] ?? 0) + 1;
        $actionCalls[$hookName][] = $args;

        if (!isset($hookCallbacks[$hookName]) || !is_array($hookCallbacks[$hookName])) {
            return;
        }

        ksort($hookCallbacks[$hookName]);

        foreach ($hookCallbacks[$hookName] as $callbacks) {
            foreach ((array) $callbacks as $callback) {
                if (!is_array($callback) || !isset($callback['callback'])) {
                    continue;
                }

                $acceptedArgs = max(0, (int) ($callback['accepted_args'] ?? 1));
                ($callback['callback'])(...array_slice($args, 0, $acceptedArgs));
            }
        }
    }
}

if (!function_exists('did_action')) {
    function did_action(string $hookName): int
    {
        global $actionCounts;

        return (int) ($actionCounts[$hookName] ?? 0);
    }
}

if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event(int $timestamp, string $hookName, array $args = [], bool $wpError = false): bool|WP_Error
    {
        global $scheduledEvents;

        $scheduledEvents[] = [
            'timestamp' => $timestamp,
            'hook' => $hookName,
            'args' => $args,
        ];

        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook(string $hookName): int
    {
        global $clearedScheduledHooks;

        $clearedScheduledHooks[] = $hookName;

        return 1;
    }
}

if (!function_exists('WP_Filesystem')) {
    function WP_Filesystem(mixed $args = false, string|false $context = false, bool $allow_relaxed_file_ownership = false): bool
    {
        global $wpFilesystemInitCalls;
        global $wpFilesystemShouldInit;
        global $wp_filesystem;

        $wpFilesystemInitCalls[] = [
            'args' => $args,
            'context' => $context,
            'allow_relaxed_file_ownership' => $allow_relaxed_file_ownership,
        ];

        if (!$wp_filesystem instanceof TestWpFilesystem) {
            $wp_filesystem = new TestWpFilesystem();
            $wp_filesystem->recursiveMkdir = true;
        }

        return $wpFilesystemShouldInit;
    }
}

if (!function_exists('get_filesystem_method')) {
    function get_filesystem_method(mixed $args = [], string $context = '', bool $allow_relaxed_file_ownership = false): string
    {
        global $filesystemMethod;

        return is_string($filesystemMethod) && $filesystemMethod !== '' ? $filesystemMethod : 'direct';
    }
}

if (!function_exists('wp_get_upload_dir')) {
    function wp_get_upload_dir(): array
    {
        global $uploadBaseDir;

        return [
            'basedir' => $uploadBaseDir,
            'baseurl' => 'https://example.test/wp-content/uploads',
            'error' => false,
        ];
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get(string $url, array $args = []): mixed
    {
        global $remoteGetCalls;
        global $remoteGetResponses;

        $remoteGetCalls[] = ['url' => $url, 'args' => $args];

        return $remoteGetResponses[$url] ?? new WP_Error('missing_mock', 'No mock response for ' . $url);
    }
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request(string $url, array $args = []): mixed
    {
        global $remoteRequestCalls;
        global $remoteRequestResponses;

        $method = strtoupper((string) ($args['method'] ?? 'GET'));
        $remoteRequestCalls[] = [
            'method' => $method,
            'url' => $url,
            'args' => $args,
        ];

        return $remoteRequestResponses[$method . ' ' . $url]
            ?? $remoteRequestResponses[$url]
            ?? new WP_Error('missing_mock', 'No mock response for ' . $method . ' ' . $url);
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []): mixed
    {
        $args['method'] = 'POST';

        return wp_remote_request($url, $args);
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(mixed $response): int
    {
        return is_array($response) ? (int) ($response['response']['code'] ?? 0) : 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(mixed $response): string
    {
        return is_array($response) ? (string) ($response['body'] ?? '') : '';
    }
}

if (!function_exists('wp_remote_retrieve_header')) {
    function wp_remote_retrieve_header(mixed $response, string $header): string
    {
        if (!is_array($response) || !is_array($response['headers'] ?? null)) {
            return '';
        }

        $headers = $response['headers'];
        $header = strtolower($header);

        return (string) ($headers[$header] ?? $headers[strtoupper($header)] ?? '');
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, string|false $src = '', array $deps = [], string|bool|null $ver = false): void
    {
        global $enqueuedStyles;

        $enqueuedStyles[$handle] = [
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
        ];
    }
}

if (!function_exists('wp_register_style')) {
    function wp_register_style(string $handle, string|false $src = '', array $deps = [], string|bool|null $ver = false): void
    {
        global $registeredStyles;

        $registeredStyles[$handle] = [
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
        ];
    }
}

if (!function_exists('wp_style_add_data')) {
    function wp_style_add_data(string $handle, string $key, mixed $value): bool
    {
        global $styleData;

        if (!isset($styleData[$handle]) || !is_array($styleData[$handle])) {
            $styleData[$handle] = [];
        }

        $styleData[$handle][$key] = $value;

        return true;
    }
}

if (!function_exists('wp_add_inline_style')) {
    function wp_add_inline_style(string $handle, string $css): void
    {
        global $inlineStyles;

        $inlineStyles[$handle] = $css;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, bool $inFooter = false): void
    {
        global $enqueuedScripts;

        $enqueuedScripts[$handle] = [
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
            'in_footer' => $inFooter,
        ];
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script(string $handle, string $objectName, array $l10n): void
    {
        global $localizedScripts;

        $localizedScripts[$handle] = [
            'object_name' => $objectName,
            'data' => $l10n,
        ];
    }
}

if (!function_exists('wp_add_inline_script')) {
    function wp_add_inline_script(string $handle, string $data, string $position = 'after'): bool
    {
        global $inlineScripts;

        $inlineScripts[$handle] = $inlineScripts[$handle] ?? [];
        $inlineScripts[$handle][] = [
            'data' => $data,
            'position' => $position === 'before' ? 'before' : 'after',
        ];

        return true;
    }
}

if (!function_exists('wp_set_script_translations')) {
    function wp_set_script_translations(string $handle, string $domain = 'default', string $path = ''): bool
    {
        global $scriptTranslations;

        $scriptTranslations[$handle] = [
            'domain' => $domain,
            'path' => $path,
        ];

        return true;
    }
}

if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain(string $domain, bool $deprecated = false, string $pluginRelPath = ''): bool
    {
        global $loadedTextdomains;

        $loadedTextdomains[] = [
            'domain' => $domain,
            'deprecated' => $deprecated,
            'path' => $pluginRelPath,
        ];

        return true;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(mixed $key, mixed $value = null, string $url = ''): string
    {
        $args = is_array($key) ? $key : [(string) $key => $value];
        $targetUrl = is_array($key) ? (string) $value : $url;
        $parts = parse_url($targetUrl);
        $path = (string) ($parts['scheme'] ?? '') !== ''
            ? ($parts['scheme'] . '://' . ($parts['host'] ?? '') . (isset($parts['path']) ? $parts['path'] : ''))
            : $targetUrl;
        $query = [];

        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        foreach ($args as $argKey => $argValue) {
            $query[(string) $argKey] = (string) $argValue;
        }

        return $path . ($query === [] ? '' : '?' . http_build_query($query));
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename(string $file): string
    {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string
    {
        return trailingslashit(dirname($file));
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string
    {
        return 'https://example.test/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook(string $file, callable $callback): void
    {
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook(string $file, callable $callback): void
    {
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        return 'https://example.test/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = '-1'): string
    {
        return 'nonce:' . $action;
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect(string $location): bool
    {
        global $redirectLocation;

        $redirectLocation = $location;

        return true;
    }
}

if (!function_exists('wp_die')) {
    function wp_die(mixed $message = '', mixed $title = '', mixed $args = []): never
    {
        throw new WpDieException((string) $message);
    }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer(string $action = '', string $name = '_wpnonce'): bool
    {
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        global $currentUserCapabilities;

        if (is_array($currentUserCapabilities) && array_key_exists($capability, $currentUserCapabilities)) {
            return !empty($currentUserCapabilities[$capability]);
        }

        $user = wp_get_current_user();
        $roles = is_array($user->roles ?? null) ? $user->roles : [];

        if ($capability === 'manage_options') {
            return in_array('administrator', $roles, true);
        }

        if ($capability === 'read') {
            return (int) ($user->ID ?? 0) > 0;
        }

        return true;
    }
}

if (!function_exists('wp_doing_cron')) {
    function wp_doing_cron(): bool
    {
        global $isDoingCron;

        return !empty($isDoingCron);
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args = [], bool $override = false): bool
    {
        global $registeredRestRoutes;

        $registeredRestRoutes[$namespace . $route] = [
            'namespace' => $namespace,
            'route' => $route,
            'args' => $args,
            'override' => $override,
        ];

        return true;
    }
}

if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response(mixed $response): WP_REST_Response
    {
        if ($response instanceof WP_REST_Response) {
            return $response;
        }

        return new WP_REST_Response($response);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        global $currentUserId;

        return $currentUserId;
    }
}

if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id(): int
    {
        global $currentBlogId;

        return $currentBlogId;
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user(): object
    {
        global $currentUserId;
        global $currentUserRoles;
        global $testUsers;

        $currentUserId = absint($currentUserId);
        $user = is_array($testUsers[$currentUserId] ?? null)
            ? $testUsers[$currentUserId]
            : [
                'ID' => $currentUserId,
                'user_login' => $currentUserId > 0 ? 'user-' . $currentUserId : '',
                'display_name' => $currentUserId > 0 ? 'User ' . $currentUserId : '',
                'roles' => [],
            ];
        $roles = is_array($currentUserRoles)
            ? array_values($currentUserRoles)
            : (is_array($user['roles'] ?? null) ? array_values($user['roles']) : []);

        return (object) [
            'ID' => absint($user['ID'] ?? $currentUserId),
            'user_login' => (string) ($user['user_login'] ?? ''),
            'display_name' => (string) ($user['display_name'] ?? ''),
            'roles' => $roles,
        ];
    }
}

if (!class_exists('WP_Roles')) {
    class WP_Roles
    {
        public function __construct(public array $roles = [])
        {
        }
    }
}

if (!function_exists('wp_roles')) {
    function wp_roles(): WP_Roles
    {
        global $registeredRoleNames;

        $roles = [];

        foreach ((array) $registeredRoleNames as $roleSlug => $roleName) {
            $normalizedSlug = sanitize_key((string) $roleSlug);

            if ($normalizedSlug === '') {
                continue;
            }

            $roles[$normalizedSlug] = ['name' => (string) $roleName];
        }

        return new WP_Roles($roles);
    }
}

if (!function_exists('get_users')) {
    function get_users(array $args = []): array
    {
        global $testUsers;

        $users = is_array($testUsers) ? $testUsers : [];
        $include = is_array($args['include'] ?? null) ? array_map('absint', $args['include']) : [];

        if ($include !== []) {
            $users = array_intersect_key($users, array_flip($include));
        }

        $fields = $args['fields'] ?? null;

        if ($fields === 'ids') {
            return array_values(
                array_map(
                    static fn (array $user): int => absint($user['ID'] ?? 0),
                    $users
                )
            );
        }

        return array_values(
            array_map(
                static function (array $user): object {
                    return (object) [
                        'ID' => absint($user['ID'] ?? 0),
                        'display_name' => (string) ($user['display_name'] ?? ''),
                        'user_login' => (string) ($user['user_login'] ?? ''),
                        'roles' => is_array($user['roles'] ?? null) ? array_values($user['roles']) : [],
                    ];
                },
                $users
            )
        );
    }
}

if (!function_exists('post_type_exists')) {
    function post_type_exists(string $postType): bool
    {
        global $supportedPostTypes;

        return in_array($postType, $supportedPostTypes, true);
    }
}

if (!function_exists('get_attached_file')) {
    function get_attached_file(int $attachmentId): string|false
    {
        global $attachedFilePaths;

        return $attachedFilePaths[$attachmentId] ?? false;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
    {
        return json_encode($value, $flags, $depth);
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text): string
    {
        return strip_tags($text);
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        global $isAdminRequest;

        return $isAdminRequest;
    }
}

if (!function_exists('get_the_ID')) {
    function get_the_ID(): int
    {
        global $currentPostId;

        return (int) $currentPostId;
    }
}

if (!function_exists('ct_get_global_settings')) {
    function ct_get_global_settings(): array
    {
        global $oxygenGlobalSettings;

        return is_array($oxygenGlobalSettings) ? $oxygenGlobalSettings : [];
    }
}

if (!class_exists('Bricks\\Database')) {
    eval(<<<'PHP'
namespace Bricks;

class Database
{
    public static function screen_conditions($foundStyles, $styleId, $conditions, $postId, $context)
    {
        if (!is_array($foundStyles)) {
            $foundStyles = [];
        }

        if (is_array($conditions) && $conditions !== []) {
            $priority = isset($conditions[0]['priority']) ? (int) $conditions[0]['priority'] : (int) $styleId;
            $foundStyles[$priority] = (string) $styleId;
        }

        return $foundStyles;
    }
}
PHP);
}

final class TestWpFilesystem
{
    public array $mkdirCalls = [];
    public array $writeCalls = [];

    /**
     * Mirrors WP_Filesystem_Direct::mkdir() semantics by defaulting to recursive=false,
     * while allowing tests to opt into recursive behavior for legacy fixtures.
     */
    public bool $recursiveMkdir = false;

    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function is_dir(string $path): bool
    {
        return is_dir($path);
    }

    public function mkdir(string $path, int $chmod): bool
    {
        $this->mkdirCalls[] = $path;

        return is_dir($path) || mkdir($path, $chmod, $this->recursiveMkdir);
    }

    public function put_contents(string $path, string $contents, int $chmod): bool
    {
        $this->writeCalls[] = $path;

        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, FS_CHMOD_DIR, true) && !is_dir($directory)) {
            return false;
        }

        return file_put_contents($path, $contents) !== false;
    }

    public function delete(string $path, bool $recursive = false, string $type = ''): bool
    {
        if (!file_exists($path)) {
            return true;
        }

        if (is_dir($path)) {
            if (!$recursive) {
                return rmdir($path);
            }

            $entries = scandir($path);

            if (!is_array($entries)) {
                return false;
            }

            foreach (array_diff($entries, ['.', '..']) as $entry) {
                $childPath = $path . DIRECTORY_SEPARATOR . $entry;

                if (!$this->delete($childPath, true, is_dir($childPath) ? 'd' : 'f')) {
                    return false;
                }
            }

            return rmdir($path);
        }

        return unlink($path);
    }
}

final class StubUploadedFileValidator implements UploadedFileValidatorInterface
{
    public function isUploadedFile(string $tmpName): bool
    {
        global $uploadedFilePaths;

        return in_array($tmpName, $uploadedFilePaths, true);
    }
}

final class TestWpdb
{
    public string $options = 'wp_options';

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }

    public function prepare(string $query, mixed ...$args): string
    {
        foreach ($args as $arg) {
            $escaped = str_replace(['\\', '\''], ['\\\\', '\\\''], (string) $arg);
            $query = preg_replace('/%s/', "'" . $escaped . "'", $query, 1) ?? $query;
        }

        return $query;
    }

    public function query(string $query): int
    {
        global $wpdbQueries;

        $wpdbQueries[] = $query;

        return 1;
    }
}

function uniqueTestDirectory(string $name): string
{
    return sys_get_temp_dir() . '/tasty-fonts-tests/' . $name . '-' . uniqid('', true);
}

function resetTestState(): void
{
    global $actionCalls;
    global $actionCounts;
    global $attachedFilePaths;
    global $downloadUrlCalls;
    global $downloadUrlResponses;
    global $filesystemMethod;
    global $enqueuedScripts;
    global $enqueuedStyles;
    global $hookCallbacks;
    global $inlineStyles;
    global $inlineScripts;
    global $isAdminRequest;
    global $isUserLoggedIn;
    global $localizedScripts;
    global $loadedTextdomains;
    global $menuPageCalls;
    global $currentPostId;
    global $currentBlogId;
    global $currentUserId;
    global $currentUserRoles;
    global $currentUserCapabilities;
    global $oxygenGlobalSettings;
    global $optionAutoload;
    global $optionDeleted;
    global $optionStore;
    global $pluginUpgraderInstallCalls;
    global $pluginUpgraderInstallResult;
    global $redirectLocation;
    global $registeredStyles;
    global $styleData;
    global $registeredRestRoutes;
    global $remoteGetCalls;
    global $remoteGetResponses;
    global $remoteRequestCalls;
    global $remoteRequestResponses;
    global $scheduledEvents;
    global $clearedScheduledHooks;
    global $scriptTranslations;
    global $siteTransientDeleted;
    global $siteTransientSet;
    global $siteTransientStore;
    global $supportedPostTypes;
    global $submenuPageCalls;
    global $registeredRoleNames;
    global $testUsers;
    global $transientDeleted;
    global $transientSet;
    global $transientStore;
    global $translationMap;
    global $uploadedFilePaths;
    global $wpFilesystemInitCalls;
    global $wpFilesystemShouldInit;
    global $wpdb;
    global $wpdbQueries;
    global $wp_filesystem;
    global $uploadBaseDir;
    global $isDoingCron;

    $filesystemMethod = 'direct';
    $downloadUrlCalls = [];
    $downloadUrlResponses = [];
    $optionAutoload = [];
    $optionStore = [];
    $optionDeleted = [];
    $transientStore = [];
    $transientDeleted = [];
    $transientSet = [];
    $siteTransientStore = [];
    $siteTransientDeleted = [];
    $siteTransientSet = [];
    $translationMap = [];
    $remoteGetResponses = [];
    $remoteGetCalls = [];
    $remoteRequestResponses = [];
    $remoteRequestCalls = [];
    $scheduledEvents = [];
    $clearedScheduledHooks = [];
    $wpFilesystemInitCalls = [];
    $wpFilesystemShouldInit = true;
    $enqueuedStyles = [];
    $registeredStyles = [];
    $styleData = [];
    $inlineStyles = [];
    $enqueuedScripts = [];
    $inlineScripts = [];
    $localizedScripts = [];
    $loadedTextdomains = [];
    $menuPageCalls = [];
    $scriptTranslations = [];
    $redirectLocation = '';
    $isAdminRequest = false;
    $isDoingCron = false;
    $isUserLoggedIn = true;
    $hookCallbacks = [];
    $actionCounts = [];
    $actionCalls = [];
    $registeredRestRoutes = [];
    $supportedPostTypes = ['wp_font_family', 'wp_font_face'];
    $submenuPageCalls = [];
    $attachedFilePaths = [];
    $uploadBaseDir = uniqueTestDirectory('uploads');
    $uploadedFilePaths = [];
    $currentPostId = 0;
    $currentBlogId = 1;
    $currentUserId = 1;
    $currentUserRoles = null;
    $currentUserCapabilities = ['manage_options' => true];
    $registeredRoleNames = [
        'administrator' => 'Administrator',
        'editor' => 'Editor',
        'author' => 'Author',
        'contributor' => 'Contributor',
        'subscriber' => 'Subscriber',
    ];
    $testUsers = [
        1 => ['ID' => 1, 'user_login' => 'admin', 'display_name' => 'Admin User', 'roles' => ['administrator']],
        2 => ['ID' => 2, 'user_login' => 'editor', 'display_name' => 'Editor User', 'roles' => ['editor']],
        3 => ['ID' => 3, 'user_login' => 'author', 'display_name' => 'Author User', 'roles' => ['author']],
        4 => ['ID' => 4, 'user_login' => 'contributor', 'display_name' => 'Contributor User', 'roles' => ['contributor']],
    ];
    $oxygenGlobalSettings = [];
    $pluginUpgraderInstallCalls = [];
    $pluginUpgraderInstallResult = true;
    $wpdb = new TestWpdb();
    $wpdbQueries = [];
    $wp_filesystem = new TestWpFilesystem();
    $wp_filesystem->recursiveMkdir = true;
    $_GET = [];
    $_POST = [];
    $_FILES = [];
}

function invokePrivateMethod(object $object, string $methodName, array $arguments = []): mixed
{
    $reflection = new ReflectionMethod($object, $methodName);

    return $reflection->invokeArgs($object, $arguments);
}

function makeAdminControllerTestInstance(): AdminController
{
    $reflection = new ReflectionClass(AdminController::class);

    return $reflection->newInstanceWithoutConstructor();
}

function makeServiceGraph(): array
{
    $storage = new Storage();
    $googleApiKeyRepo = new GoogleApiKeyRepository();
    $adobeProjectRepo = new AdobeProjectRepository();
    $roleRepo = new RoleRepository();
    $familyMetadataRepo = new FamilyMetadataRepository();
    $settings = new SettingsRepository(
        $googleApiKeyRepo,
        $adobeProjectRepo,
        $roleRepo,
        $familyMetadataRepo
    );
    $imports = new ImportRepository();
    $log = new LogRepository();
    $adobe = new AdobeProjectClient($settings, $adobeProjectRepo, new AdobeCssParser());
    $bunny = new BunnyFontsClient();
    $google = new GoogleFontsClient($settings, $googleApiKeyRepo);
    $catalog = new CatalogService($storage, $imports, new FontFilenameParser(), $log, $adobe);
    $planner = new RuntimeAssetPlanner(
        $catalog,
        $settings,
        [new GoogleStylesheetResolver($google), new BunnyStylesheetResolver($bunny), new AdobeStylesheetResolver($adobe)],
        $roleRepo,
        $familyMetadataRepo
    );
    $cssBuilder = new CssBuilder();
    $assets = new AssetService($storage, $catalog, $settings, $cssBuilder, $planner, $log, $roleRepo);
    $hostedImportWorkflow = new HostedImportWorkflow(
        $imports,
        $assets,
        $log,
        new HostedImportVariantPlanner(),
        [
            'cdn' => new CdnImportStrategy(),
            'self_hosted' => new SelfHostedImportStrategy($storage),
        ]
    );
    $library = new LibraryService($storage, $catalog, $imports, $assets, $log, $settings, $roleRepo);
    $capabilityCleanup = new CapabilityDisableCleanupService($storage, $imports, $catalog, $log);
    $localUpload = new LocalUploadService(
        $storage,
        $catalog,
        $imports,
        $assets,
        $settings,
        $log,
        new StubUploadedFileValidator(),
        $familyMetadataRepo
    );
    $bunnyImport = new BunnyImportService($bunny, new BunnyCssParser(), $hostedImportWorkflow);
    $googleImport = new GoogleImportService($google, new GoogleCssParser(), $hostedImportWorkflow);
    $acssIntegration = new AcssIntegrationService();
    $bricksIntegration = new BricksIntegrationService();
    $oxygenIntegration = new OxygenIntegrationService();
    $blockEditorFontLibrary = new BlockEditorFontLibraryService($storage, $imports, $settings, $log);
    $customCssValidator = new CustomCssFontValidator();
    $customCssImport = new CustomCssUrlImportService($imports, $customCssValidator);
    $customCssSnapshots = new CustomCssImportSnapshotService();
    $customCssFinalImport = new CustomCssFinalImportService($storage, $imports, $familyMetadataRepo, $catalog, $assets, $log, $customCssValidator);
    $developerTools = new DeveloperToolsService(
        $storage,
        $settings,
        $imports,
        $catalog,
        $assets,
        $blockEditorFontLibrary,
        $google,
        $familyMetadataRepo
    );
    $siteTransfer = new SiteTransferService(
        $storage,
        $settings,
        $imports,
        $log,
        $developerTools,
        $library,
        $blockEditorFontLibrary,
        new StubUploadedFileValidator(),
        $roleRepo
    );
    $snapshots = new SnapshotService(
        $storage,
        $settings,
        $imports,
        $developerTools,
        $library,
        $blockEditorFontLibrary,
        $roleRepo
    );
    $supportBundles = new SupportBundleService($storage, $settings, $imports, $log);
    $adminAccess = new AdminAccessService($settings);
    $roleFamilyCatalogBuilder = new RoleFamilyCatalogBuilder();
    $updater = new GitHubUpdater($settings, $adminAccess);
    $actionRunner = new \TastyFonts\Admin\AdminActionRunner($log);
    $controller = new AdminController(
        $storage,
        $settings,
        $log,
        $catalog,
        $assets,
        $library,
        $capabilityCleanup,
        $localUpload,
        $cssBuilder,
        $adobe,
        $bunny,
        $bunnyImport,
        $google,
        $googleImport,
        $acssIntegration,
        $bricksIntegration,
        $oxygenIntegration,
        $developerTools,
        $siteTransfer,
        $snapshots,
        $supportBundles,
        $updater,
        $adminAccess,
        $planner,
        $customCssImport,
        $customCssSnapshots,
        $customCssFinalImport,
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
    $rest = new RestController($controller, $adminAccess);
    $runtime = new RuntimeService(
        $planner,
        $assets,
        $cssBuilder,
        $adobe,
        $settings,
        $roleRepo,
        $acssIntegration,
        $bricksIntegration,
        $oxygenIntegration,
        [$acssIntegration, $bricksIntegration, $oxygenIntegration],
        $catalog,
        $roleFamilyCatalogBuilder,
        $adminAccess
    );

    return [
        'storage' => $storage,
        'settings' => $settings,
        'imports' => $imports,
        'log' => $log,
        'catalog' => $catalog,
        'planner' => $planner,
        'assets' => $assets,
        'hosted_import_workflow' => $hostedImportWorkflow,
        'library' => $library,
        'capability_cleanup' => $capabilityCleanup,
        'local_upload' => $localUpload,
        'adobe' => $adobe,
        'bunny' => $bunny,
        'bunny_import' => $bunnyImport,
        'google' => $google,
        'google_import' => $googleImport,
        'acss_integration' => $acssIntegration,
        'bricks_integration' => $bricksIntegration,
        'oxygen_integration' => $oxygenIntegration,
        'block_editor_font_library' => $blockEditorFontLibrary,
        'custom_css_import' => $customCssImport,
        'custom_css_snapshots' => $customCssSnapshots,
        'custom_css_final_import' => $customCssFinalImport,
        'developer_tools' => $developerTools,
        'site_transfer' => $siteTransfer,
        'snapshots' => $snapshots,
        'support_bundles' => $supportBundles,
        'role_repo' => $roleRepo,
        'family_metadata_repo' => $familyMetadataRepo,
        'admin_access' => $adminAccess,
        'role_family_catalog_builder' => $roleFamilyCatalogBuilder,
        'updater' => $updater,
        'controller' => $controller,
        'rest' => $rest,
        'runtime' => $runtime,
    ];
}

function resetPluginSingleton(): void
{
    $reflection = new ReflectionClass(Plugin::class);
    $property = $reflection->getProperty('instance');
    $property->setValue(null, null);
}
