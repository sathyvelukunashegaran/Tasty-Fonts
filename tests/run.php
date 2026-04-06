<?php

declare(strict_types=1);

if (!defined('TASTY_FONTS_VERSION')) {
    define('TASTY_FONTS_VERSION', '6.0.1');
}

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('TASTY_FONTS_URL')) {
    define('TASTY_FONTS_URL', 'https://example.test/wp-content/plugins/etch-fonts/');
}

if (!defined('TASTY_FONTS_DIR')) {
    define('TASTY_FONTS_DIR', dirname(__DIR__) . '/');
}

if (!defined('TASTY_FONTS_FILE')) {
    define('TASTY_FONTS_FILE', dirname(__DIR__) . '/plugin.php');
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

require_once __DIR__ . '/bootstrap.php';

use TastyFonts\Adobe\AdobeCssParser;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Admin\AdminController;
use TastyFonts\Admin\AdminPageRenderer;
use TastyFonts\Api\RestController;
use TastyFonts\Bunny\BunnyCssParser;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Bunny\BunnyImportService;
use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\BlockEditorFontLibraryService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Fonts\FontFilenameParser;
use TastyFonts\Fonts\HostedImportSupport;
use TastyFonts\Fonts\LibraryService;
use TastyFonts\Fonts\LocalUploadService;
use TastyFonts\Fonts\UploadedFileValidatorInterface;
use TastyFonts\Fonts\RuntimeAssetPlanner;
use TastyFonts\Fonts\RuntimeService;
use TastyFonts\Google\GoogleCssParser;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Google\GoogleImportService;
use TastyFonts\Plugin;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;

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

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        global $translationMap;

        return is_array($translationMap) && array_key_exists($text, $translationMap)
            ? (string) $translationMap[$text]
            : $text;
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
        return '2026-04-03 10:00:00';
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return false;
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
$uploadBaseDir = sys_get_temp_dir() . '/tasty-fonts-tests/uploads';
$uploadedFilePaths = [];
$currentUserId = 1;
$wpdb = null;
$wpdbQueries = [];
$wp_filesystem = null;
$remoteGetResponses = [];
$remoteGetCalls = [];
$remoteRequestResponses = [];
$remoteRequestCalls = [];
$enqueuedStyles = [];
$registeredStyles = [];
$inlineStyles = [];
$enqueuedScripts = [];
$localizedScripts = [];
$scriptTranslations = [];
$redirectLocation = '';
$isAdminRequest = false;
$hookCallbacks = [];
$actionCounts = [];
$actionCalls = [];
$registeredRestRoutes = [];
$scheduledEvents = [];
$clearedScheduledHooks = [];
$supportedPostTypes = ['wp_font_family', 'wp_font_face'];

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

if (!function_exists('check_admin_referer')) {
    function check_admin_referer(string $action = '', string $name = '_wpnonce'): bool
    {
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return true;
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

if (!function_exists('post_type_exists')) {
    function post_type_exists(string $postType): bool
    {
        global $supportedPostTypes;

        return in_array($postType, $supportedPostTypes, true);
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

final class TestWpFilesystem
{
    public array $mkdirCalls = [];
    public array $writeCalls = [];

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

        return is_dir($path) || mkdir($path, $chmod, true);
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
    global $filesystemMethod;
    global $enqueuedScripts;
    global $enqueuedStyles;
    global $hookCallbacks;
    global $inlineStyles;
    global $isAdminRequest;
    global $localizedScripts;
    global $currentUserId;
    global $optionAutoload;
    global $optionDeleted;
    global $optionStore;
    global $redirectLocation;
    global $registeredStyles;
    global $registeredRestRoutes;
    global $remoteGetCalls;
    global $remoteGetResponses;
    global $remoteRequestCalls;
    global $remoteRequestResponses;
    global $scheduledEvents;
    global $clearedScheduledHooks;
    global $scriptTranslations;
    global $supportedPostTypes;
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

    $filesystemMethod = 'direct';
    $optionAutoload = [];
    $optionStore = [];
    $optionDeleted = [];
    $transientStore = [];
    $transientDeleted = [];
    $transientSet = [];
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
    $inlineStyles = [];
    $enqueuedScripts = [];
    $localizedScripts = [];
    $scriptTranslations = [];
    $redirectLocation = '';
    $isAdminRequest = false;
    $hookCallbacks = [];
    $actionCounts = [];
    $actionCalls = [];
    $registeredRestRoutes = [];
    $supportedPostTypes = ['wp_font_family', 'wp_font_face'];
    $uploadBaseDir = uniqueTestDirectory('uploads');
    $uploadedFilePaths = [];
    $currentUserId = 1;
    $wpdb = new TestWpdb();
    $wpdbQueries = [];
    $wp_filesystem = new TestWpFilesystem();
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
    $settings = new SettingsRepository();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeCssParser());
    $bunny = new BunnyFontsClient();
    $google = new GoogleFontsClient($settings);
    $catalog = new CatalogService($storage, $imports, new FontFilenameParser(), $log, $adobe);
    $planner = new RuntimeAssetPlanner($catalog, $settings, $google, $bunny, $adobe);
    $assets = new AssetService($storage, $catalog, $settings, new CssBuilder(), $planner, $log);
    $library = new LibraryService($storage, $catalog, $imports, $assets, $log, $settings);
    $localUpload = new LocalUploadService(
        $storage,
        $catalog,
        $assets,
        $settings,
        $log,
        new StubUploadedFileValidator()
    );
    $bunnyImport = new BunnyImportService($storage, $imports, $bunny, new BunnyCssParser(), $catalog, $assets, $log);
    $googleImport = new GoogleImportService($storage, $imports, $google, new GoogleCssParser(), $catalog, $assets, $log);
    $blockEditorFontLibrary = new BlockEditorFontLibraryService($storage, $imports, $settings, $log);
    $controller = new AdminController(
        $storage,
        $settings,
        $log,
        $catalog,
        $assets,
        $library,
        $localUpload,
        new CssBuilder(),
        $adobe,
        $bunny,
        $bunnyImport,
        $google,
        $googleImport
    );
    $rest = new RestController($controller);
    $runtime = new RuntimeService($planner, $assets, $adobe);

    return [
        'storage' => $storage,
        'settings' => $settings,
        'imports' => $imports,
        'log' => $log,
        'catalog' => $catalog,
        'planner' => $planner,
        'assets' => $assets,
        'library' => $library,
        'local_upload' => $localUpload,
        'adobe' => $adobe,
        'bunny' => $bunny,
        'bunny_import' => $bunnyImport,
        'google' => $google,
        'google_import' => $googleImport,
        'block_editor_font_library' => $blockEditorFontLibrary,
        'controller' => $controller,
        'rest' => $rest,
        'runtime' => $runtime,
    ];
}

function resetPluginSingleton(): void
{
    $reflection = new ReflectionClass(Plugin::class);
    $property = $reflection->getProperty('instance');
    $property->setAccessible(true);
    $property->setValue(null, null);
}

$tests['font_filename_parser_detects_weight_and_style'] = static function (): void {
    $parser = new FontFilenameParser();
    $parsed = $parser->parse('Inter-ExtraBoldItalic');

    assertSameValue('Inter', $parsed['family'], 'Parser should remove suffixes from the family name.');
    assertSameValue('800', $parsed['weight'], 'Parser should detect extra-bold weight.');
    assertSameValue('italic', $parsed['style'], 'Parser should detect italic style.');
    assertSameValue(false, $parsed['is_variable'], 'Static fonts should not be marked variable.');
};

$tests['font_filename_parser_preserves_oblique_style'] = static function (): void {
    $parser = new FontFilenameParser();
    $parsed = $parser->parse('Satoshi-500Oblique');

    assertSameValue('Satoshi', $parsed['family'], 'Parser should keep the family name when reading oblique files.');
    assertSameValue('500', $parsed['weight'], 'Parser should detect the numeric weight for oblique files.');
    assertSameValue('oblique', $parsed['style'], 'Parser should preserve oblique instead of collapsing it to italic.');
};

$tests['font_utils_builds_static_upload_filename'] = static function (): void {
    $filename = FontUtils::buildStaticFontFilename('Satoshi Display', '700', 'italic', 'woff2');

    assertSameValue('Satoshi Display-700-italic.woff2', $filename, 'Uploaded static files should use deterministic scanner-friendly filenames.');
};

$tests['font_utils_normalizes_google_variants'] = static function (): void {
    $variants = FontUtils::normalizeVariantTokens(['700italic', 'regular', '700italic', 'bogus']);

    assertSameValue(['regular', '700italic'], $variants, 'Variant normalization should dedupe and discard unsupported tokens.');
};

$tests['font_utils_preserves_custom_fallback_stacks'] = static function (): void {
    $fallback = FontUtils::sanitizeFallback('-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif');
    $stack = FontUtils::buildFontStack('Inter', $fallback);

    assertSameValue('-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif', $fallback, 'Fallback sanitizer should allow custom browser/system font stacks.');
    assertSameValue('"Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif', $stack, 'Font stack builder should preserve sanitized custom fallback stacks.');
};

$tests['font_utils_builds_face_axis_keys'] = static function (): void {
    $axisKey = FontUtils::faceAxisKey(400, 'ITALIC');

    assertSameValue('400|italic', $axisKey, 'Face axis keys should normalize weight and style before composing the dedupe key.');
};

$tests['font_utils_detects_remote_urls'] = static function (): void {
    assertSameValue(true, FontUtils::isRemoteUrl('https://example.com/fonts/inter.woff2'), 'Remote URL detection should recognize HTTPS URLs.');
    assertSameValue(true, FontUtils::isRemoteUrl('//fonts.bunny.net/inter.woff2'), 'Remote URL detection should recognize protocol-relative CDN URLs.');
    assertSameValue(false, FontUtils::isRemoteUrl('google/inter/inter-400-normal.woff2'), 'Remote URL detection should treat relative storage paths as local.');
};

$tests['hosted_import_support_builds_variants_from_faces'] = static function (): void {
    $variants = HostedImportSupport::variantsFromFaces([
        ['weight' => '400', 'style' => 'normal'],
        ['weight' => '700', 'style' => 'normal'],
        ['weight' => '400', 'style' => 'italic'],
        ['weight' => '700', 'style' => 'italic'],
        ['weight' => '700', 'style' => 'italic'],
        'invalid-face',
    ]);

    assertSameValue(
        ['regular', '700', 'italic', '700italic'],
        $variants,
        'Hosted face variant synthesis should mirror the existing catalog and library token format exactly.'
    );
};

$tests['font_utils_compares_faces_by_weight_and_style'] = static function (): void {
    $faces = [
        ['weight' => '700', 'style' => 'normal'],
        ['weight' => '400', 'style' => 'normal'],
        ['weight' => '400', 'style' => 'italic'],
    ];

    usort($faces, [FontUtils::class, 'compareFacesByWeightAndStyle']);

    assertSameValue(
        [
            ['weight' => '400', 'style' => 'italic'],
            ['weight' => '400', 'style' => 'normal'],
            ['weight' => '700', 'style' => 'normal'],
        ],
        $faces,
        'Face sorting should remain stable across shared catalog/import comparators.'
    );
};

$tests['google_css_parser_extracts_woff2_faces_and_unicode_ranges'] = static function (): void {
    $css = <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/inter/v18/u-4k0qWljRw-PfU81xCK.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Inter';
  font-style: italic;
  font-weight: 700;
  src: url(https://fonts.gstatic.com/s/inter/v18/u-4i0qWljRw.woff2) format('woff2');
  unicode-range: U+0100-024F;
}
CSS;

    $parser = new GoogleCssParser();
    $faces = $parser->parse($css, 'Inter');

    assertSameValue(2, count($faces), 'Google CSS parser should return one face per @font-face block.');
    assertSameValue('400', $faces[0]['weight'], 'Google CSS parser should capture the weight.');
    assertSameValue('italic', $faces[1]['style'], 'Google CSS parser should capture style.');
    assertSameValue('U+0100-024F', $faces[1]['unicode_range'], 'Google CSS parser should preserve unicode-range.');
    assertSameValue('https://fonts.gstatic.com/s/inter/v18/u-4k0qWljRw-PfU81xCK.woff2', $faces[0]['files']['woff2'], 'Google CSS parser should keep the remote WOFF2 URL.');
};

$tests['bunny_fonts_client_builds_css2_urls'] = static function (): void {
    $client = new BunnyFontsClient();

    assertSameValue(
        'https://fonts.bunny.net/css2?family=Inter:ital,wght@0,400;1,700&display=swap',
        $client->buildCssUrl('Inter', ['regular', '700italic']),
        'Bunny Fonts CSS URLs should use the css2 endpoint and Google-compatible axis syntax.'
    );
};

$tests['bunny_fonts_client_searches_sitemap_catalog_and_hydrates_family_details'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $client = new BunnyFontsClient();
    $remoteGetResponses['https://fonts.bunny.net/sitemap.xml'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'application/xml'],
        'body' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://fonts.bunny.net/family/inter</loc></url>
  <url><loc>https://fonts.bunny.net/family/ibm-plex-sans</loc></url>
</urlset>
XML,
    ];
    $remoteGetResponses['https://fonts.bunny.net/family/inter'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/html'],
        'body' => <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Inter | Bunny Fonts</title>
</head>
<body>
    <div class="family"><h3>Sans Serif</h3></div>
    <div class="styles">18 styles</div>
    <div class="card-main"><h1>Inter</h1></div>
    <link href="https://fonts.bunny.net/css?family=inter:100,400,700,400i,700i," rel="stylesheet" />
</body>
</html>
HTML,
    ];

    $results = $client->searchFamilies('int', 5);
    $first = $results[0] ?? [];

    assertSameValue(1, count($results), 'Bunny search should filter sitemap entries by the query before hydrating family details.');
    assertSameValue('Inter', (string) ($first['family'] ?? ''), 'Bunny search should hydrate the exact family name from the public family page.');
    assertSameValue('inter', (string) ($first['slug'] ?? ''), 'Bunny search should preserve the Bunny family slug.');
    assertSameValue('sans-serif', (string) ($first['category'] ?? ''), 'Bunny search should normalize public category labels for preview usage.');
    assertSameValue('Sans Serif', (string) ($first['category_label'] ?? ''), 'Bunny search should keep the display category label for the admin cards.');
    assertSameValue(18, (int) ($first['style_count'] ?? 0), 'Bunny search should expose the public style count for the family card.');
    assertSameValue(
        ['100', 'regular', '700', 'italic', '700italic'],
        $first['variants'] ?? [],
        'Bunny search should normalize public variant tokens into the plugin token format.'
    );
};

$tests['bunny_fonts_client_get_family_parses_public_variant_tokens'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $client = new BunnyFontsClient();
    $remoteGetResponses['https://fonts.bunny.net/family/alegreya-sans'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/html'],
        'body' => <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Alegreya Sans | Bunny Fonts</title>
</head>
<body>
    <div class="family"><h3>Sans Serif</h3></div>
    <div class="styles">4 styles</div>
    <div class="card-main"><h1>Alegreya Sans</h1></div>
    <link href="https://fonts.bunny.net/css?family=alegreya-sans:400,700,400i,700i," rel="stylesheet" />
</body>
</html>
HTML,
    ];

    $family = $client->getFamily('Alegreya Sans');

    assertSameValue('Alegreya Sans', (string) ($family['family'] ?? ''), 'Bunny family lookup should resolve the exact public family name.');
    assertSameValue(
        ['regular', '700', 'italic', '700italic'],
        $family['variants'] ?? [],
        'Bunny family lookup should convert Bunny public variant markers into Google-style plugin tokens.'
    );
};

$tests['google_fonts_client_uses_compact_catalog_cache_for_search_and_refetches_full_family_metadata_on_demand'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;
    global $transientStore;

    $settings = new SettingsRepository();
    $settings->saveSettings(['google_api_key' => 'api-key']);
    $settings->saveGoogleApiKeyStatus('valid', 'Ready');
    $client = new GoogleFontsClient($settings);
    $catalogUrl = 'https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key=api-key';
    $remoteGetResponses[$catalogUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'application/json'],
        'body' => json_encode(
            [
                'items' => [
                    [
                        'family' => 'Inter',
                        'category' => 'sans-serif',
                        'variants' => ['regular', '700'],
                        'subsets' => ['latin'],
                        'version' => 'v18',
                        'lastModified' => '2024-01-01',
                    ],
                    [
                        'family' => 'Lora',
                        'category' => 'serif',
                        'variants' => ['regular'],
                        'subsets' => ['latin'],
                        'version' => 'v35',
                        'lastModified' => '2024-01-02',
                    ],
                ],
            ]
        ),
    ];

    $results = $client->searchFamilies('int', 5);
    $resultsAgain = $client->searchFamilies('int', 5);
    $family = (new GoogleFontsClient($settings))->getFamily('Inter');

    assertSameValue(
        [
            [
                'family' => 'Inter',
                'slug' => 'inter',
                'category' => 'sans-serif',
                'variants_count' => 2,
            ],
        ],
        $results,
        'Google search should return compact search items from the cached catalog index.'
    );
    assertSameValue($results, $resultsAgain, 'Repeated Google searches in the same request should reuse the in-memory compact catalog index.');
    assertSameValue(
        [
            'inter' => [
                'family' => 'Inter',
                'category' => 'sans-serif',
                'variants_count' => 2,
            ],
            'lora' => [
                'family' => 'Lora',
                'category' => 'serif',
                'variants_count' => 1,
            ],
        ],
        $transientStore['tasty_fonts_google_catalog_v1'] ?? null,
        'The Google catalog transient should only store the compact search index.'
    );
    assertSameValue(2, count($remoteGetCalls), 'Google family metadata lookups should refetch the full catalog when only the compact search index is cached.');
    assertSameValue(['regular', '700'], $family['variants'] ?? null, 'Google family lookups should still return full variant metadata on demand.');
    assertSameValue('v18', (string) ($family['version'] ?? ''), 'Google family lookups should still return full catalog metadata on demand.');
};

$tests['bunny_css_parser_extracts_woff2_faces_and_unicode_ranges'] = static function (): void {
    $css = <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Inter';
  font-style: italic;
  font-weight: 700;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-700-italic.woff2) format('woff2');
  unicode-range: U+0100-024F;
}
CSS;

    $parser = new BunnyCssParser();
    $faces = $parser->parse($css, 'Inter');

    assertSameValue(2, count($faces), 'Bunny CSS parser should return one face per @font-face block.');
    assertSameValue('bunny', $faces[0]['source'], 'Bunny CSS parser should tag parsed faces with the bunny source.');
    assertSameValue('italic', $faces[1]['style'], 'Bunny CSS parser should capture style.');
    assertSameValue('U+0100-024F', $faces[1]['unicode_range'], 'Bunny CSS parser should preserve unicode-range.');
    assertSameValue('https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2', $faces[0]['files']['woff2'], 'Bunny CSS parser should keep the remote WOFF2 URL.');
};

$tests['bunny_import_service_imports_and_catalogs_bunny_faces'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = $services['bunny']->buildCssUrl('Inter', ['regular', '700']);
    $greekUrl = 'https://fonts.bunny.net/inter/files/inter-greek-400-normal.woff2';
    $latinUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $boldUrl = 'https://fonts.bunny.net/inter/files/inter-latin-700-normal.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-greek-400-normal.woff2) format('woff2');
  unicode-range: U+0370-03FF;
}
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 700;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-700-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$greekUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'greek-font-data',
    ];
    $remoteGetResponses[$latinUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];
    $remoteGetResponses[$boldUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'bold-font-data',
    ];

    $importProvider = '';
    $importStatus = '';
    add_action(
        'tasty_fonts_after_import',
        static function (array $result, string $provider) use (&$importProvider, &$importStatus): void {
            $importProvider = $provider;
            $importStatus = (string) ($result['status'] ?? '');
        },
        10,
        2
    );

    $result = $services['bunny_import']->importFamily('Inter', ['regular', '700']);
    $import = $services['imports']->get('inter');
    $profile = (array) (($import['delivery_profiles']['bunny-self_hosted'] ?? null) ?: []);
    $catalog = $services['catalog']->getCatalog();
    $catalogFamily = $catalog['Inter'] ?? null;
    $downloadUrls = array_map(static fn (array $call): string => (string) ($call['url'] ?? ''), $remoteGetCalls);
    $savedRegularPath = $services['storage']->pathForRelativePath('bunny/inter/inter-400-normal.woff2');
    $savedBoldPath = $services['storage']->pathForRelativePath('bunny/inter/inter-700-normal.woff2');

    assertSameValue('imported', (string) ($result['status'] ?? ''), 'Bunny imports should report an imported result.');
    assertSameValue('bunny', (string) ($profile['provider'] ?? ''), 'Bunny imports should persist the bunny provider on the saved delivery profile.');
    assertSameValue('bunny', (string) ($profile['faces'][0]['provider']['type'] ?? ''), 'Bunny imports should persist bunny provider metadata per face.');
    assertSameValue(['bunny'], (array) ($catalogFamily['sources'] ?? []), 'Catalog entries created from Bunny imports should expose the bunny source.');
    assertSameValue('bunny', (string) ($catalogFamily['faces'][0]['source'] ?? ''), 'Catalog faces created from Bunny imports should retain the bunny source.');
    assertSameValue(true, is_string($savedRegularPath) && file_exists($savedRegularPath), 'Bunny regular faces should be written under uploads/fonts/bunny.');
    assertSameValue(true, is_string($savedBoldPath) && file_exists($savedBoldPath), 'Bunny bold faces should be written under uploads/fonts/bunny.');
    assertContainsValue($latinUrl, implode("\n", $downloadUrls), 'Bunny imports should download the preferred latin regular face.');
    assertContainsValue($boldUrl, implode("\n", $downloadUrls), 'Bunny imports should download the requested bold face.');
    assertNotContainsValue($greekUrl, implode("\n", $downloadUrls), 'Bunny imports should skip lower-priority subset faces for the same axis.');
    assertSameValue(1, did_action('tasty_fonts_after_import'), 'Bunny imports should fire the tasty_fonts_after_import action.');
    assertSameValue('bunny', $importProvider, 'Bunny imports should identify the provider when firing tasty_fonts_after_import.');
    assertSameValue('imported', $importStatus, 'Bunny imports should pass the import result payload to tasty_fonts_after_import.');
};

$tests['google_import_service_fires_after_import_action'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = $services['google']->buildCssUrl('Inter', ['regular']);
    $fontUrl = 'https://fonts.gstatic.com/s/inter/v18/inter-400-normal.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/inter/v18/inter-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];

    $importProvider = '';
    $importResult = [];
    add_action(
        'tasty_fonts_after_import',
        static function (array $result, string $provider) use (&$importProvider, &$importResult): void {
            $importProvider = $provider;
            $importResult = $result;
        },
        10,
        2
    );

    $result = $services['google_import']->importFamily('Inter', ['regular']);

    assertSameValue('imported', (string) ($result['status'] ?? ''), 'Google imports should still succeed when the after-import action is registered.');
    assertSameValue(1, did_action('tasty_fonts_after_import'), 'Google imports should fire the tasty_fonts_after_import action.');
    assertSameValue('google', $importProvider, 'Google imports should identify the provider when firing tasty_fonts_after_import.');
    assertSameValue('imported', (string) ($importResult['status'] ?? ''), 'Google imports should pass the import result payload to tasty_fonts_after_import.');
};

$tests['bunny_import_service_reports_direct_filesystem_requirement'] = static function (): void {
    resetTestState();

    global $filesystemMethod;
    global $remoteGetResponses;
    global $wpFilesystemInitCalls;

    $filesystemMethod = 'ftpext';

    $services = makeServiceGraph();
    $cssUrl = $services['bunny']->buildCssUrl('Inter', ['regular']);
    $latinUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$latinUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];

    $result = $services['bunny_import']->importFamily('Inter', ['regular']);

    assertSameValue(true, is_wp_error($result), 'Bunny imports should fail with a WP_Error when direct filesystem access is unavailable.');
    assertContainsValue(
        'Direct filesystem access is unavailable',
        $result->get_error_message(),
        'Bunny imports should surface the direct filesystem requirement instead of a generic write failure.'
    );
    assertSameValue(0, count($wpFilesystemInitCalls), 'Bunny imports should not initialize WP_Filesystem when the direct method is unavailable.');
};

$tests['bunny_import_service_skips_existing_variants_and_can_coexist_with_local_faces'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = $services['bunny']->buildCssUrl('Inter', ['regular']);
    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];

    $firstImport = $services['bunny_import']->importFamily('Inter', ['regular']);
    $secondImport = $services['bunny_import']->importFamily('Inter', ['regular']);

    assertSameValue('imported', (string) ($firstImport['status'] ?? ''), 'The initial Bunny import should succeed.');
    assertSameValue('skipped', (string) ($secondImport['status'] ?? ''), 'Re-importing an existing Bunny variant should be skipped.');

    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2'), 'font-data');
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];

    $coexistingImport = $services['bunny_import']->importFamily('Inter', ['regular']);
    $catalogFamily = $services['catalog']->getCatalog()['Inter'] ?? [];

    assertSameValue('imported', (string) ($coexistingImport['status'] ?? ''), 'Bunny imports should still succeed when a local family already exists.');
    assertSameValue(['local', 'bunny'], (array) ($catalogFamily['sources'] ?? []), 'Families should be able to keep both local/self-hosted and Bunny delivery profiles.');
};

$tests['library_service_deletes_bunny_import_families_cleanly'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $cssUrl = $services['bunny']->buildCssUrl('Inter', ['regular']);
    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $remoteGetResponses[$cssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS,
    ];
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];

    $deletedFamilySlug = '';
    $deletedFamilyName = '';
    add_action(
        'tasty_fonts_after_delete_family',
        static function (string $familySlug, string $familyName) use (&$deletedFamilySlug, &$deletedFamilyName): void {
            $deletedFamilySlug = $familySlug;
            $deletedFamilyName = $familyName;
        },
        10,
        2
    );

    $services['bunny_import']->importFamily('Inter', ['regular']);
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('lora/Lora-400-normal.woff2'), 'font-data');
    $services['settings']->saveRoles(
        [
            'heading' => 'Lora',
            'body' => 'Lora',
            'heading_fallback' => 'serif',
            'body_fallback' => 'serif',
        ],
        ['Inter', 'Lora']
    );
    $services['settings']->setAutoApplyRoles(false);
    $familyDirectory = $services['storage']->pathForRelativePath('bunny/inter');
    $result = $services['library']->deleteFamily('inter');

    assertSameValue(true, $result, 'Bunny-imported families should be deletable from the library.');
    assertSameValue(null, $services['imports']->get('inter'), 'Deleting a Bunny family should remove its import manifest entry.');
    assertSameValue(false, is_string($familyDirectory) && file_exists($familyDirectory), 'Deleting a Bunny family should remove its provider directory from uploads/fonts.');
    assertSameValue(1, did_action('tasty_fonts_after_delete_family'), 'Deleting a family should fire the tasty_fonts_after_delete_family action.');
    assertSameValue('inter', $deletedFamilySlug, 'Deleting a family should pass the deleted slug to tasty_fonts_after_delete_family.');
    assertSameValue('Inter', $deletedFamilyName, 'Deleting a family should pass the deleted name to tasty_fonts_after_delete_family.');
};

$tests['adobe_css_parser_groups_families_and_dedupes_faces'] = static function (): void {
    $css = <<<'CSS'
@font-face {
  font-family: "ff-tisa-web-pro";
  font-style: normal;
  font-weight: 400;
  src: url("https://use.typekit.net/af/abc123/000000000000000000000000/30/l?primer=1") format("woff2");
}
@font-face {
  font-family: "ff-tisa-web-pro";
  font-style: normal;
  font-weight: 400;
  src: url("https://use.typekit.net/af/duplicate/000000000000000000000000/30/l?primer=1") format("woff2");
}
@font-face {
  font-family: "mr-eaves-xl-modern";
  font-style: italic;
  font-weight: 700;
  src: url("https://use.typekit.net/af/def456/000000000000000000000000/30/l?primer=1") format("woff2");
}
CSS;

    $parser = new AdobeCssParser();
    $families = $parser->parseFamilies($css);

    assertSameValue(2, count($families), 'Adobe CSS parser should return one entry per unique family.');
    assertSameValue('ff-tisa-web-pro', $families[0]['family'], 'Adobe CSS parser should preserve CSS family names for the project.');
    assertSameValue(1, count($families[0]['faces']), 'Adobe CSS parser should dedupe duplicate axis pairs.');
    assertSameValue('700', $families[1]['faces'][0]['weight'], 'Adobe CSS parser should capture weight from @font-face blocks.');
    assertSameValue('italic', $families[1]['faces'][0]['style'], 'Adobe CSS parser should capture style from @font-face blocks.');
};

$tests['css_builder_generates_font_face_and_role_variables'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                    ],
                ],
            ],
        ],
    ];
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'serif',
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => true,
        'minify_css_output' => false,
    ];

    $css = $builder->build($catalog, $roles, $settings);

    assertContainsValue('@font-face', $css, 'CSS builder should emit @font-face rules.');
    assertContainsValue('font-family:"Inter"', $css, 'CSS builder should include the family name.');
    assertContainsValue('--font-heading', $css, 'CSS builder should emit the heading role variable.');
    assertContainsValue('font-family: var(--font-body);', $css, 'CSS builder should emit the body usage rule.');
};

$tests['css_builder_can_generate_font_faces_without_role_usage_rules'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                    ],
                ],
            ],
        ],
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => true,
        'minify_css_output' => false,
    ];

    $css = $builder->buildFontFaceOnly($catalog, $settings);

    assertContainsValue('@font-face', $css, 'Font-face-only CSS should still emit @font-face rules.');
    assertNotContainsValue('--font-heading', $css, 'Font-face-only CSS should not emit role variables.');
    assertNotContainsValue('font-family: var(--font-body);', $css, 'Font-face-only CSS should not emit body usage rules.');
};

$tests['css_builder_ignores_eot_and_svg_sources'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'eot' => 'https://example.com/fonts/inter.eot',
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                        'svg' => 'https://example.com/fonts/inter.svg',
                    ],
                ],
            ],
        ],
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => false,
        'minify_css_output' => false,
    ];

    $css = $builder->buildFontFaceOnly($catalog, $settings);

    assertContainsValue('format("woff2")', $css, 'CSS builder should continue to emit supported modern formats.');
    assertNotContainsValue('embedded-opentype', $css, 'CSS builder should not emit legacy EOT sources.');
    assertNotContainsValue('inter.svg', $css, 'CSS builder should not emit deprecated SVG font sources.');
};

$tests['css_builder_preserves_raw_query_strings_in_source_urls'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['google'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'google',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2?display=swap&subset=latin',
                    ],
                ],
            ],
        ],
    ];

    $css = $builder->buildFontFaceOnly($catalog, ['minify_css_output' => false]);

    assertContainsValue(
        'url("https://example.com/fonts/inter.woff2?display=swap&subset=latin") format("woff2")',
        $css,
        'CSS builder should preserve raw query-string separators inside font source URLs.'
    );
    assertNotContainsValue('&#038;', $css, 'CSS builder should not HTML-escape ampersands inside CSS source URLs.');
};

$tests['css_builder_minifies_generated_css_without_leaving_layout_whitespace'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                    ],
                ],
            ],
        ],
    ];
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $settings = [
        'font_display' => 'swap',
        'auto_apply_roles' => true,
        'minify_css_output' => true,
    ];

    $css = $builder->build($catalog, $roles, $settings);

    assertSameValue(false, str_contains($css, "\n"), 'Minified CSS should not leave newline characters in the generated output.');
    assertSameValue(false, str_contains($css, "\t"), 'Minified CSS should not leave tab characters in the generated output.');
    assertContainsValue('@font-face{font-family:"Inter";font-weight:400;font-style:normal;', $css, 'Minified CSS should collapse @font-face declarations into a compact form.');
    assertContainsValue('body{font-family:var(--font-body)}', $css, 'Minified CSS should collapse role usage rules into a compact form.');
};

$tests['css_builder_format_output_respects_minify_flag'] = static function (): void {
    $builder = new CssBuilder();
    $snippet = ":root {\n  --font-heading: var(--font-lora);\n}\n";

    assertSameValue($snippet, $builder->formatOutput($snippet, false), 'Formatted output should preserve readable snippets when minification is disabled.');
    assertSameValue(':root{--font-heading:var(--font-lora)}', $builder->formatOutput($snippet, true), 'Formatted output should minify snippets when requested.');
};

$tests['css_builder_defaults_font_display_to_optional'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                    ],
                ],
            ],
        ],
    ];

    $css = $builder->buildFontFaceOnly($catalog, ['minify_css_output' => false]);

    assertContainsValue('font-display:optional;', $css, 'Generated font-face CSS should default to font-display optional when no explicit setting is stored.');
};

$tests['css_builder_uses_per_family_font_display_overrides'] = static function (): void {
    $builder = new CssBuilder();
    $catalog = [
        'Inter' => [
            'family' => 'Inter',
            'slug' => 'inter',
            'sources' => ['local'],
            'faces' => [
                [
                    'family' => 'Inter',
                    'slug' => 'inter',
                    'source' => 'local',
                    'weight' => '400',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/inter.woff2',
                    ],
                ],
            ],
        ],
        'Lora' => [
            'family' => 'Lora',
            'slug' => 'lora',
            'sources' => ['google'],
            'faces' => [
                [
                    'family' => 'Lora',
                    'slug' => 'lora',
                    'source' => 'google',
                    'weight' => '700',
                    'style' => 'normal',
                    'unicode_range' => '',
                    'files' => [
                        'woff2' => 'https://example.com/fonts/lora.woff2',
                    ],
                ],
            ],
        ],
    ];

    $css = $builder->buildFontFaceOnly(
        $catalog,
        [
            'font_display' => 'optional',
            'family_font_displays' => ['Inter' => 'swap'],
            'minify_css_output' => false,
        ]
    );

    assertContainsValue("font-family:\"Inter\";\n  font-weight:400;\n  font-style:normal;\n  src:url(\"https://example.com/fonts/inter.woff2\") format(\"woff2\");\n  font-display:swap;", $css, 'Per-family overrides should change the font-display value for the matching family.');
    assertContainsValue("font-family:\"Lora\";\n  font-weight:700;\n  font-style:normal;\n  src:url(\"https://example.com/fonts/lora.woff2\") format(\"woff2\");\n  font-display:optional;", $css, 'Families without an override should continue using the global font-display default.');
};

$tests['storage_returns_absolute_generated_css_url'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $url = $storage->getGeneratedCssUrl();

    assertSameValue(
        'https://example.test/wp-content/uploads/fonts/tasty-fonts.css',
        $url,
        'Generated CSS URL should stay absolute so Etch can pass it to new URL(...).'
    );
};

$tests['catalog_service_applies_catalog_filter_before_returning_results'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2'), 'font-data');

    add_filter(
        'tasty_fonts_catalog',
        static function (array $catalog): array {
            unset($catalog['Inter']);

            return $catalog;
        }
    );

    $catalog = $services['catalog']->getCatalog();
    $counts = $services['catalog']->getCounts();

    assertSameValue(false, isset($catalog['Inter']), 'Catalog filters should be able to remove families before getCatalog() returns.');
    assertSameValue(0, (int) ($counts['families'] ?? -1), 'Catalog counts should reflect the filtered catalog payload.');
};

$tests['catalog_service_ignores_eot_and_svg_files_during_local_scan'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $storage->ensureRootDirectory();
    $storage->writeAbsoluteFile((string) $storage->pathForRelativePath('inter/Inter-400-normal.woff2'), 'font-data');
    $storage->writeAbsoluteFile((string) $storage->pathForRelativePath('legacy/Legacy-400-normal.eot'), 'font-data');
    $storage->writeAbsoluteFile((string) $storage->pathForRelativePath('vector/Vector-400-normal.svg'), 'font-data');

    $settings = new SettingsRepository();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeCssParser());
    $catalog = new CatalogService($storage, $imports, new FontFilenameParser(), $log, $adobe);
    $families = $catalog->getCatalog();

    assertSameValue(['Inter'], array_values(array_keys($families)), 'Catalog scanning should ignore local EOT and SVG files so the scanned formats match the upload allowlist.');
};

$tests['storage_writes_absolute_files_via_wp_filesystem'] = static function (): void {
    resetTestState();

    global $wpFilesystemInitCalls;
    global $wp_filesystem;

    $storage = new Storage();
    $targetPath = uniqueTestDirectory('storage-write') . '/families/inter/inter-400.woff2';
    $written = $storage->writeAbsoluteFile($targetPath, 'font-data');

    assertSameValue(true, $written, 'Storage should write absolute files through the shared filesystem bridge.');
    assertSameValue('font-data', (string) file_get_contents($targetPath), 'Storage writes should persist the provided file contents.');
    assertSameValue(true, in_array(dirname($targetPath), $wp_filesystem->mkdirCalls, true), 'Storage writes should create missing parent directories before writing.');
    assertSameValue(1, count($wpFilesystemInitCalls), 'Storage writes should initialize the shared filesystem bridge once per write.');
};

$tests['storage_skips_wp_filesystem_when_direct_method_is_unavailable'] = static function (): void {
    resetTestState();

    global $filesystemMethod;
    global $wpFilesystemInitCalls;

    $filesystemMethod = 'ftpext';

    $storage = new Storage();
    $targetPath = uniqueTestDirectory('storage-no-direct') . '/families/inter/inter-400.woff2';
    $written = $storage->writeAbsoluteFile($targetPath, 'font-data');

    assertSameValue(false, $written, 'Storage writes should fail fast when WordPress cannot use the direct filesystem method.');
    assertSameValue(0, count($wpFilesystemInitCalls), 'Storage should not bootstrap WP_Filesystem when the direct method is unavailable.');
    assertContainsValue(
        'Direct filesystem access is unavailable',
        $storage->getLastFilesystemErrorMessage(),
        'Storage should expose a clear error message when direct filesystem access is unavailable.'
    );
};

$tests['storage_can_copy_absolute_files_without_buffering_contents'] = static function (): void {
    resetTestState();

    $storage = new Storage();
    $sourcePath = uniqueTestDirectory('storage-copy-source') . '/inter-400.woff2';
    $targetPath = uniqueTestDirectory('storage-copy-target') . '/families/inter/inter-400.woff2';

    mkdir(dirname($sourcePath), FS_CHMOD_DIR, true);
    file_put_contents($sourcePath, 'font-data');

    $copied = $storage->copyAbsoluteFile($sourcePath, $targetPath);

    assertSameValue(true, $copied, 'Storage should copy uploaded files into the target directory without reading the whole file into PHP memory first.');
    assertSameValue('font-data', (string) file_get_contents($targetPath), 'Copied files should preserve the original contents.');
};

$tests['google_fonts_client_clears_catalog_cache'] = static function (): void {
    resetTestState();

    global $transientDeleted;
    global $transientStore;

    $transientStore['tasty_fonts_google_catalog_v1'] = ['family' => 'Inter'];

    $client = new GoogleFontsClient(new SettingsRepository());
    $client->clearCatalogCache();

    assertSameValue(false, array_key_exists('tasty_fonts_google_catalog_v1', $transientStore), 'Google catalog cache clearing should remove the cached catalog transient.');
    assertSameValue(true, in_array('tasty_fonts_google_catalog_v1', $transientDeleted, true), 'Google catalog cache clearing should delete the expected transient key.');
};

$tests['provider_clients_apply_http_request_args_filters'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;

    add_filter(
        'tasty_fonts_http_request_args',
        static function (array $args, string $url): array {
            $host = (string) (wp_parse_url($url, PHP_URL_HOST) ?? '');
            $headers = is_array($args['headers'] ?? null) ? $args['headers'] : [];
            $headers['X-Tasty-Test'] = $host;
            $args['headers'] = $headers;
            $args['timeout'] = 99;

            return $args;
        },
        10,
        2
    );

    $settings = new SettingsRepository();
    $google = new GoogleFontsClient($settings);
    $bunny = new BunnyFontsClient();
    $adobe = new AdobeProjectClient($settings, new AdobeCssParser());
    $googleCatalogUrl = 'https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key=api-key';
    $googleCssUrl = $google->buildCssUrl('Inter', ['regular']);
    $bunnyFamilyUrl = 'https://fonts.bunny.net/family/inter';
    $bunnyCssUrl = $bunny->buildCssUrl('Inter', ['regular']);
    $adobeUrl = 'https://use.typekit.net/abc1234.css';

    $remoteGetResponses[$googleCatalogUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'application/json'],
        'body' => '{"items":[]}',
    ];
    $remoteGetResponses[$googleCssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face{font-family:"Inter";font-style:normal;font-weight:400;src:url(https://fonts.gstatic.com/s/inter/v1/inter.woff2) format("woff2");}',
    ];
    $remoteGetResponses[$bunnyFamilyUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/html'],
        'body' => <<<'HTML'
<!doctype html>
<html>
<head>
    <title>Inter | Bunny Fonts</title>
</head>
<body>
    <div class="family"><h3>Sans Serif</h3></div>
    <div class="styles">1 style</div>
    <div class="card-main"><h1>Inter</h1></div>
    <link href="https://fonts.bunny.net/css?family=inter:400," rel="stylesheet" />
</body>
</html>
HTML,
    ];
    $remoteGetResponses[$bunnyCssUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => '@font-face{font-family:"Inter";font-style:normal;font-weight:400;src:url(https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2) format("woff2");}',
    ];
    $remoteGetResponses[$adobeUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: "ff-tisa-web-pro";
  font-style: normal;
  font-weight: 400;
  src: url("https://use.typekit.net/af/abc123/000000000000000000000000/30/l?primer=1") format("woff2");
}
CSS,
    ];

    $google->validateApiKey('api-key');
    $google->fetchCss('Inter', ['regular']);
    $bunny->getFamily('Inter');
    $bunny->fetchCss('Inter', ['regular']);
    $adobe->validateProject('abc1234');

    assertSameValue(5, count($remoteGetCalls), 'The HTTP args filter test should exercise each provider client.');

    foreach ($remoteGetCalls as $call) {
        $url = (string) ($call['url'] ?? '');
        $args = (array) ($call['args'] ?? []);
        $headers = is_array($args['headers'] ?? null) ? $args['headers'] : [];

        assertSameValue(99, (int) ($args['timeout'] ?? 0), 'HTTP request args filters should be able to override timeouts for ' . $url . '.');
        assertSameValue(true, isset($headers['X-Tasty-Test']) && $headers['X-Tasty-Test'] !== '', 'HTTP request args filters should be able to inject headers for ' . $url . '.');
    }
};

$tests['adobe_project_client_validates_project_and_reuses_cached_families'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;

    $projectId = 'abc1234';
    $url = 'https://use.typekit.net/' . $projectId . '.css';
    $remoteGetResponses[$url] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: "ff-tisa-web-pro";
  font-style: normal;
  font-weight: 400;
  src: url("https://use.typekit.net/af/abc123/000000000000000000000000/30/l?primer=1") format("woff2");
}
@font-face {
  font-family: "mr-eaves-xl-modern";
  font-style: italic;
  font-weight: 700;
  src: url("https://use.typekit.net/af/def456/000000000000000000000000/30/l?primer=1") format("woff2");
}
CSS,
    ];

    $client = new AdobeProjectClient(new SettingsRepository(), new AdobeCssParser());
    $validation = $client->validateProject('ABC-1234');
    $families = $client->getProjectFamilies($projectId);

    assertSameValue('valid', $validation['state'], 'Adobe project validation should mark a parseable 200 stylesheet as valid.');
    assertContainsValue('2 famil', (string) $validation['message'], 'Adobe project validation should report the detected family count.');
    assertSameValue(2, count($families), 'Adobe project metadata should expose parsed family records.');
    assertSameValue('ff-tisa-web-pro', $families[0]['family'], 'Adobe project family metadata should preserve parsed CSS family names.');
    assertSameValue(1, count($remoteGetCalls), 'Adobe project families should come from the cache after a successful validation fetch.');
};

$tests['adobe_project_client_maps_invalid_and_unknown_responses'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $invalidUrl = 'https://use.typekit.net/invalid01.css';
    $unknownUrl = 'https://use.typekit.net/unknown01.css';
    $remoteGetResponses[$invalidUrl] = [
        'response' => ['code' => 404],
        'headers' => ['content-type' => 'text/css'],
        'body' => '',
    ];
    $remoteGetResponses[$unknownUrl] = new WP_Error('http_request_failed', 'Timed out');

    $client = new AdobeProjectClient(new SettingsRepository(), new AdobeCssParser());
    $invalid = $client->validateProject('invalid01');
    $unknown = $client->validateProject('unknown01');

    assertSameValue('invalid', $invalid['state'], 'Adobe project validation should treat rejected project IDs as invalid.');
    assertSameValue('unknown', $unknown['state'], 'Adobe project validation should treat transport failures as unknown.');
};

$tests['settings_repository_persists_adobe_project_state'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $settings->saveAdobeProject(' AbC-123 ', true);
    $saved = $settings->getSettings();

    assertSameValue(true, $saved['adobe_enabled'], 'Saving an Adobe project should persist the enabled flag.');
    assertSameValue('abc123', $saved['adobe_project_id'], 'Saving an Adobe project should normalize the project ID.');
    assertSameValue('unknown', $saved['adobe_project_status'], 'Saving a non-empty Adobe project should reset status to unknown before validation.');

    $settings->saveAdobeProjectStatus('valid', 'Adobe project ready.');
    $status = $settings->getAdobeProjectStatus();

    assertSameValue('valid', $status['state'], 'Adobe project status updates should persist the normalized state.');
    assertSameValue('Adobe project ready.', $status['message'], 'Adobe project status updates should persist the status message.');
    assertSameValue(true, $status['checked_at'] > 0, 'Adobe project status updates should record a validation timestamp.');

    $settings->clearAdobeProject();
    $cleared = $settings->getSettings();

    assertSameValue(false, $cleared['adobe_enabled'], 'Clearing an Adobe project should disable remote loading.');
    assertSameValue('', $cleared['adobe_project_id'], 'Clearing an Adobe project should remove the saved project ID.');
    assertSameValue('empty', $cleared['adobe_project_status'], 'Clearing an Adobe project should reset the status to empty.');
};

$tests['settings_repository_persists_google_api_key_data_in_dedicated_option'] = static function (): void {
    resetTestState();

    global $optionAutoload;
    global $optionStore;

    $settings = new SettingsRepository();
    $saved = $settings->saveSettings(['google_api_key' => '  live-key  ']);

    assertSameValue('live-key', $saved['google_api_key'], 'Saving a Google API key should still expose the trimmed key through getSettings/saveSettings.');
    assertSameValue(
        false,
        array_key_exists('google_api_key', (array) ($optionStore[SettingsRepository::OPTION_SETTINGS] ?? [])),
        'The main settings option should no longer persist the Google API key.'
    );
    assertSameValue(
        [
            'google_api_key' => 'live-key',
            'google_api_key_status' => 'unknown',
            'google_api_key_status_message' => '',
            'google_api_key_checked_at' => 0,
        ],
        $optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA] ?? null,
        'Google API key data should be stored in its dedicated option row.'
    );
    assertSameValue(
        false,
        $optionAutoload[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA] ?? null,
        'The dedicated Google API key option should be saved with autoload disabled.'
    );
};

$tests['settings_repository_migrates_legacy_google_api_key_data_when_saving_other_settings'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'preview_sentence' => 'Legacy preview',
        'google_api_key' => 'legacy-key',
        'google_api_key_status' => 'valid',
        'google_api_key_status_message' => 'Ready',
        'google_api_key_checked_at' => 123,
    ];

    $settings = new SettingsRepository();
    $saved = $settings->saveSettings(['preview_sentence' => 'Updated preview']);

    assertSameValue('legacy-key', $saved['google_api_key'], 'Saving unrelated settings should preserve the existing Google API key during migration.');
    assertSameValue(
        false,
        array_key_exists('google_api_key', (array) ($optionStore[SettingsRepository::OPTION_SETTINGS] ?? [])),
        'Migrated main settings should no longer keep Google API key fields in the shared settings blob.'
    );
    assertSameValue('Updated preview', (string) ($optionStore[SettingsRepository::OPTION_SETTINGS]['preview_sentence'] ?? ''), 'Unrelated settings should still save into the main settings option.');
    assertSameValue(
        [
            'google_api_key' => 'legacy-key',
            'google_api_key_status' => 'valid',
            'google_api_key_status_message' => 'Ready',
            'google_api_key_checked_at' => 123,
        ],
        $optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA] ?? null,
        'Saving unrelated settings should migrate legacy Google API key data into the dedicated option.'
    );
};

$tests['settings_repository_updates_google_key_status_without_rewriting_main_settings'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'preview_sentence' => 'Keep this',
    ];
    $optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA] = [
        'google_api_key' => 'live-key',
        'google_api_key_status' => 'unknown',
        'google_api_key_status_message' => '',
        'google_api_key_checked_at' => 0,
    ];

    $settings = new SettingsRepository();
    $settings->saveGoogleApiKeyStatus('valid', 'Ready');

    assertSameValue(
        ['preview_sentence' => 'Keep this'],
        $optionStore[SettingsRepository::OPTION_SETTINGS] ?? null,
        'Updating Google API key validation state should not rewrite the main settings option.'
    );
    assertSameValue(
        'valid',
        (string) ($optionStore[SettingsRepository::OPTION_GOOGLE_API_KEY_DATA]['google_api_key_status'] ?? ''),
        'Updating Google API key validation state should only touch the dedicated option.'
    );
};

$tests['settings_repository_persists_delete_files_on_uninstall_preference'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $settings->saveSettings(['delete_uploaded_files_on_uninstall' => '1']);
    $saved = $settings->getSettings();

    assertSameValue(true, !empty($saved['delete_uploaded_files_on_uninstall']), 'Settings should persist the uninstall file cleanup preference when enabled.');

    $settings->saveSettings(['delete_uploaded_files_on_uninstall' => '0']);
    $saved = $settings->getSettings();

    assertSameValue(false, !empty($saved['delete_uploaded_files_on_uninstall']), 'Settings should persist the uninstall file cleanup preference when disabled.');
};

$tests['settings_repository_persists_preload_primary_fonts_preference'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $settings->saveSettings(['preload_primary_fonts' => '1']);
    $saved = $settings->getSettings();

    assertSameValue(true, !empty($saved['preload_primary_fonts']), 'Settings should persist the primary font preload preference when enabled.');

    $settings->saveSettings(['preload_primary_fonts' => '0']);
    $saved = $settings->getSettings();

    assertSameValue(false, !empty($saved['preload_primary_fonts']), 'Settings should persist the primary font preload preference when disabled.');
};

$tests['settings_repository_defaults_font_display_to_optional_and_normalizes_invalid_values'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue('optional', $settings->getSettings()['font_display'], 'Font display should default to optional for new installs.');

    $settings->saveSettings(['font_display' => 'block']);
    assertSameValue('block', $settings->getSettings()['font_display'], 'Settings should persist supported font-display values.');

    $settings->saveSettings(['font_display' => 'unsupported-value']);
    assertSameValue('optional', $settings->getSettings()['font_display'], 'Invalid saved font-display values should normalize back to optional.');
};

$tests['settings_repository_persists_family_font_display_overrides_and_unsets_inherit'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();

    assertSameValue([], $settings->getSettings()['family_font_displays'], 'Per-family font-display overrides should default to an empty map.');

    $settings->saveFamilyFontDisplay('Inter', 'swap');
    assertSameValue('swap', $settings->getFamilyFontDisplay('Inter'), 'Family font-display overrides should persist supported values.');

    $settings->saveFamilyFontDisplay('Lora', 'unsupported-value');
    assertSameValue('', $settings->getFamilyFontDisplay('Lora'), 'Unsupported family font-display values should be ignored instead of being persisted.');

    $settings->saveFamilyFontDisplay('Inter', 'inherit');
    assertSameValue('', $settings->getFamilyFontDisplay('Inter'), 'Saving inherit should remove the stored family font-display override.');
    assertSameValue([], $settings->getSettings()['family_font_displays'], 'Removing the only family font-display override should leave the stored map empty.');
};

$tests['settings_repository_keeps_boolean_output_settings_when_fields_are_absent'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $settings->saveSettings([
        'minify_css_output' => '0',
        'preload_primary_fonts' => '0',
        'delete_uploaded_files_on_uninstall' => '1',
    ]);
    $settings->saveSettings([
        'preview_sentence' => 'Updated preview',
    ]);
    $saved = $settings->getSettings();

    assertSameValue(false, $saved['minify_css_output'], 'Saving unrelated settings should not re-enable CSS minification.');
    assertSameValue(false, $saved['preload_primary_fonts'], 'Saving unrelated settings should not re-enable primary font preloads.');
    assertSameValue(true, $saved['delete_uploaded_files_on_uninstall'], 'Saving unrelated settings should not disable uninstall cleanup.');
};

$tests['settings_repository_bootstraps_applied_roles_before_draft_changes'] = static function (): void {
    resetTestState();

    $settings = new SettingsRepository();
    $catalog = ['Inter', 'Lora'];

    $settings->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        $catalog
    );
    $settings->setAutoApplyRoles(true);

    $bootstrapped = $settings->ensureAppliedRolesInitialized($catalog);

    assertSameValue('Inter', $bootstrapped['heading'], 'Applied roles should bootstrap from the current live heading before draft-only changes.');
    assertSameValue('Inter', $bootstrapped['body'], 'Applied roles should bootstrap from the current live body before draft-only changes.');

    $settings->saveRoles(
        [
            'heading' => 'Lora',
            'body' => 'Inter',
            'heading_fallback' => 'serif',
            'body_fallback' => 'sans-serif',
        ],
        $catalog
    );

    $appliedRoles = $settings->getAppliedRoles($catalog);
    $draftRoles = $settings->getRoles($catalog);

    assertSameValue('Inter', $appliedRoles['heading'], 'Draft-only saves should not replace the bootstrapped live heading.');
    assertSameValue('Inter', $appliedRoles['body'], 'Draft-only saves should not replace the bootstrapped live body.');
    assertSameValue('Lora', $draftRoles['heading'], 'Draft roles should still update independently after bootstrapping applied roles.');
};

$tests['repositories_migrate_legacy_option_keys'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore['etch_fonts_settings'] = [
        'preview_sentence' => 'Legacy preview',
        'google_api_key' => 'legacy-key',
    ];
    $optionStore['etch_fonts_roles'] = [
        'heading' => 'Inter',
        'body' => 'Lora',
        'heading_fallback' => 'serif',
        'body_fallback' => 'sans-serif',
    ];
    $optionStore['etch_fonts_imports'] = [
        'inter' => ['slug' => 'inter', 'family' => 'Inter', 'provider' => 'google'],
    ];
    $optionStore['etch_fonts_log'] = [
        ['time' => '2026-04-04 00:00:00', 'message' => 'Legacy log entry', 'actor' => 'System'],
    ];

    $settings = new SettingsRepository();
    $roles = $settings->getRoles(
        [
            ['family' => 'Inter'],
            ['family' => 'Lora'],
        ]
    );
    $imports = (new ImportRepository())->all();
    $log = (new LogRepository())->all();

    assertSameValue('Legacy preview', $settings->getSettings()['preview_sentence'], 'Settings should fall back to the legacy option key during upgrade.');
    assertSameValue('Inter', $roles['heading'], 'Role settings should migrate from the legacy option key during upgrade.');
    assertSameValue(true, isset($optionStore[SettingsRepository::OPTION_SETTINGS]), 'Settings migration should seed the renamed option key.');
    assertSameValue(true, isset($optionStore[SettingsRepository::OPTION_ROLES]), 'Role migration should seed the renamed option key.');
    assertSameValue(true, isset($optionStore[ImportRepository::OPTION_LIBRARY]), 'Import migration should seed the renamed option key.');
    assertSameValue(true, isset($optionStore[LogRepository::OPTION_LOG]), 'Log migration should seed the renamed option key.');
    assertSameValue('Inter', (string) ($imports['inter']['family'] ?? ''), 'Imports should remain available after migrating the option key.');
    assertSameValue('Legacy log entry', (string) ($log[0]['message'] ?? ''), 'Logs should remain available after migrating the option key.');
};

$tests['asset_service_refresh_generated_assets_invalidates_caches_and_queues_css_regeneration'] = static function (): void {
    resetTestState();

    global $optionStore;
    global $scheduledEvents;
    global $transientDeleted;
    global $transientStore;

    $optionStore[SettingsRepository::OPTION_SETTINGS] = [
        'auto_apply_roles' => false,
        'css_delivery_mode' => 'file',
        'font_display' => 'swap',
        'minify_css_output' => false,
        'preview_sentence' => '',
        'google_api_key' => '',
        'google_api_key_status' => 'empty',
        'google_api_key_status_message' => '',
        'google_api_key_checked_at' => 0,
        'family_fallbacks' => [],
    ];
    $optionStore[SettingsRepository::OPTION_ROLES] = [];
    $transientStore['tasty_fonts_catalog_v2'] = ['stale' => true];
    $transientStore['tasty_fonts_css_v2'] = 'stale-css';
    $transientStore['tasty_fonts_css_hash_v2'] = 'stale-hash';

    $storage = new Storage();
    $settings = new SettingsRepository();
    $imports = new ImportRepository();
    $log = new LogRepository();
    $adobe = new AdobeProjectClient($settings, new AdobeCssParser());
    $google = new GoogleFontsClient($settings);
    $bunny = new BunnyFontsClient();
    $catalog = new CatalogService($storage, $imports, new FontFilenameParser(), $log, $adobe);
    $planner = new RuntimeAssetPlanner($catalog, $settings, $google, $bunny, $adobe);
    $assets = new AssetService($storage, $catalog, $settings, new CssBuilder(), $planner, $log);

    $assets->refreshGeneratedAssets();

    $generatedPath = $storage->getGeneratedCssPath();

    assertSameValue(false, is_string($generatedPath) && file_exists($generatedPath), 'Refreshing generated assets should defer writing the generated CSS file.');
    assertSameValue(true, in_array('tasty_fonts_catalog_v2', $transientDeleted, true), 'Refreshing generated assets should invalidate the catalog cache first.');
    assertSameValue(true, in_array('tasty_fonts_css_v2', $transientDeleted, true), 'Refreshing generated assets should invalidate the cached CSS payload.');
    assertSameValue(true, in_array('tasty_fonts_css_hash_v2', $transientDeleted, true), 'Refreshing generated assets should invalidate the cached CSS hash.');
    assertSameValue(false, array_key_exists('tasty_fonts_css_v2', $transientStore), 'Refreshing generated assets should leave CSS transient regeneration to the next request.');
    assertSameValue(false, array_key_exists('tasty_fonts_css_hash_v2', $transientStore), 'Refreshing generated assets should leave CSS hash regeneration to the next request.');
    assertSameValue(
        [
            [
                'timestamp' => $scheduledEvents[0]['timestamp'] ?? null,
                'hook' => AssetService::ACTION_REGENERATE_CSS,
                'args' => [],
            ],
        ],
        array_map(
            static fn (array $event): array => [
                'timestamp' => $event['timestamp'] ?? null,
                'hook' => $event['hook'] ?? '',
                'args' => $event['args'] ?? [],
            ],
            $scheduledEvents
        ),
        'Refreshing generated assets should queue a single background CSS regeneration event.'
    );
    assertSameValue(
        ['log_write_result' => 1],
        $transientStore['tasty_fonts_regenerate_css_queued'] ?? null,
        'Refreshing generated assets should set a short-lived cron guard transient.'
    );
};

$tests['asset_service_applies_generated_css_filter_before_caching'] = static function (): void {
    resetTestState();

    global $transientStore;

    $services = makeServiceGraph();
    $filterReceivedContext = false;
    add_filter(
        'tasty_fonts_generated_css',
        static function (string $css, array $localCatalog, array $roles, array $settings) use (&$filterReceivedContext): string {
            $filterReceivedContext = array_key_exists('css_delivery_mode', $settings)
                && is_array($localCatalog)
                && is_array($roles);

            return $css . "\nbody{letter-spacing:.02em;}";
        },
        10,
        4
    );

    $css = $services['assets']->getCss();

    assertSameValue(true, $filterReceivedContext, 'Generated CSS filters should receive the runtime catalog, roles, and settings context.');
    assertContainsValue('body{letter-spacing:.02em;}', $css, 'Generated CSS filters should be able to append CSS before the payload is returned.');
    assertContainsValue('body{letter-spacing:.02em;}', (string) ($transientStore['tasty_fonts_css_v2'] ?? ''), 'Generated CSS filters should run before the CSS transient is written.');
};

$tests['asset_service_can_refresh_generated_assets_without_logging_file_writes'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['assets']->refreshGeneratedAssets(true, false);
    $services['assets']->ensureGeneratedCssFile();
    $entries = $services['log']->all();

    assertSameValue(0, count($entries), 'Deferred CSS regeneration should honor the no-log file write option.');
};

$tests['asset_service_debounces_background_css_regeneration_events'] = static function (): void {
    resetTestState();

    global $scheduledEvents;
    global $transientStore;

    $services = makeServiceGraph();
    $services['assets']->refreshGeneratedAssets();
    $services['assets']->refreshGeneratedAssets();

    assertSameValue(1, count($scheduledEvents), 'Repeated asset invalidations in a short window should only queue one background CSS regeneration event.');
    assertSameValue(
        ['log_write_result' => 1],
        $transientStore['tasty_fonts_regenerate_css_queued'] ?? null,
        'Queued CSS regeneration should keep the short-lived guard transient until the write runs.'
    );
};

$tests['admin_controller_merges_adobe_families_into_selectable_role_names'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2'), 'font-data');
    $services['settings']->saveAdobeProject('abc1234', true);
    $services['settings']->saveAdobeProjectStatus('valid', 'Adobe project ready.');
    $remoteGetResponses['https://use.typekit.net/abc1234.css'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: "mr-eaves-xl-modern";
  font-style: normal;
  font-weight: 400;
  src: url("https://use.typekit.net/af/def456/000000000000000000000000/30/l?primer=1") format("woff2");
}
CSS,
    ];

    $catalog = $services['catalog']->getCatalog();
    $families = invokePrivateMethod($services['controller'], 'buildSelectableFamilyNames', [$catalog]);

    assertSameValue(
        ['Inter', 'mr-eaves-xl-modern'],
        $families,
        'Selectable role names should use the unified catalog, including Adobe project families.'
    );
};

$tests['runtime_service_enqueues_adobe_stylesheet_and_exposes_it_to_etch_canvas'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;
    global $localizedScripts;
    global $remoteGetResponses;

    $services = makeServiceGraph();
    $services['settings']->saveAdobeProject('abc1234', true);
    $services['settings']->saveAdobeProjectStatus('valid', 'Adobe project ready.');
    $remoteGetResponses['https://use.typekit.net/abc1234.css'] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'text/css'],
        'body' => <<<'CSS'
@font-face {
  font-family: "ff-tisa-web-pro";
  font-style: normal;
  font-weight: 400;
  src: url("https://use.typekit.net/af/abc123/000000000000000000000000/30/l?primer=1") format("woff2");
}
CSS,
    ];
    $_GET['etch'] = '1';

    $services['runtime']->enqueueFrontend();
    $editorFamilies = $services['planner']->getEditorFontFamilies();
    $familyNames = array_values(array_map(static fn (array $item): string => (string) ($item['name'] ?? ''), $editorFamilies));
    $styleUrls = array_values(array_map(static fn (array $style): string => (string) ($style['src'] ?? ''), $enqueuedStyles));
    $canvasStylesheetUrls = (array) ($localizedScripts['tasty-fonts-canvas']['data']['stylesheetUrls'] ?? []);

    assertSameValue(
        true,
        in_array('https://use.typekit.net/abc1234.css', $styleUrls, true),
        'Runtime should enqueue the Adobe project stylesheet as a separate frontend style handle.'
    );
    assertSameValue(
        true,
        in_array('ff-tisa-web-pro', $familyNames, true),
        'Runtime editor font families should include Adobe project families.'
    );
    assertSameValue(
        true,
        in_array('https://use.typekit.net/abc1234.css', $canvasStylesheetUrls, true),
        'Etch canvas runtime data should include the Adobe stylesheet URL.'
    );
};

$tests['runtime_service_outputs_primary_font_preloads_for_live_sitewide_roles'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-700.woff2'), 'font-data');
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('lora/Lora-400.woff2'), 'font-data');
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Lora',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        ['Inter', 'Lora']
    );
    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings(['preload_primary_fonts' => '1']);

    ob_start();
    $services['runtime']->outputPreloadHints();
    $output = (string) ob_get_clean();

    assertContainsValue('href="/wp-content/uploads/fonts/inter/Inter-700.woff2"', $output, 'Frontend preload output should include the primary heading WOFF2 file.');
    assertContainsValue('href="/wp-content/uploads/fonts/lora/Lora-400.woff2"', $output, 'Frontend preload output should include the primary body WOFF2 file.');
    assertContainsValue('type="font/woff2"', $output, 'Frontend preload output should declare the WOFF2 mime type.');
    assertContainsValue('crossorigin', $output, 'Frontend preload output should include crossorigin so the hint matches the font request mode.');
};

$tests['runtime_service_skips_font_preloads_when_setting_or_live_roles_are_disabled'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400.woff2'), 'font-data');
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        ['Inter']
    );

    ob_start();
    $services['runtime']->outputPreloadHints();
    $outputWithSitewideOff = (string) ob_get_clean();

    $services['settings']->setAutoApplyRoles(true);
    $services['settings']->saveSettings(['preload_primary_fonts' => '0']);

    ob_start();
    $services['runtime']->outputPreloadHints();
    $outputWithPreloadsOff = (string) ob_get_clean();

    assertSameValue('', $outputWithSitewideOff, 'Frontend preload output should stay empty while live sitewide role output is disabled.');
    assertSameValue('', $outputWithPreloadsOff, 'Frontend preload output should stay empty when the preload setting is turned off.');
};

$tests['rest_controller_falls_back_to_variant_tokens_when_variants_are_missing'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/google/import');
    $request->set_body_params(['variant_tokens' => 'regular, 700italic']);
    $variants = invokePrivateMethod($services['rest'], 'getVariantTokens', [$request]);

    assertSameValue(['regular', '700italic'], $variants, 'REST import requests should fall back to the comma-separated variant token field when an explicit variants array is absent.');
};

$tests['admin_controller_normalizes_uploaded_files_by_sparse_row_index'] = static function (): void {
    resetTestState();

    $postedRows = [
        7 => [
            'family' => 'Inter',
            'weight' => '400',
            'style' => 'italic',
            'fallback' => 'Arial, sans-serif',
        ],
    ];
    $rawFiles = [
        'name' => [7 => 'inter-400-italic.woff2'],
        'type' => [7 => 'font/woff2'],
        'tmp_name' => [7 => '/tmp/php-font'],
        'error' => [7 => UPLOAD_ERR_OK],
        'size' => [7 => 2048],
    ];

    $services = makeServiceGraph();
    $rows = $services['controller']->prepareUploadRows($postedRows, $rawFiles);

    assertSameValue('Inter', $rows[0]['family'], 'Uploaded row normalization should preserve the family name.');
    assertSameValue('italic', $rows[0]['style'], 'Uploaded row normalization should preserve the submitted style.');
    assertSameValue('Arial, sans-serif', $rows[0]['fallback'], 'Uploaded row normalization should preserve the submitted fallback.');
    assertSameValue('inter-400-italic.woff2', $rows[0]['file']['name'], 'Uploaded row normalization should attach the correct file payload to a sparse row index.');
    assertSameValue(2048, $rows[0]['file']['size'], 'Uploaded row normalization should preserve the uploaded file size.');
};

$tests['local_upload_service_rejects_unverified_tmp_files'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $tmpName = uniqueTestDirectory('tmp-upload-invalid') . '/inter-400-normal.woff2';
    mkdir(dirname($tmpName), FS_CHMOD_DIR, true);
    file_put_contents($tmpName, "wOF2test-font");

    $result = $services['local_upload']->uploadRows([
        [
            'family' => 'Inter',
            'weight' => '400',
            'style' => 'normal',
            'fallback' => 'sans-serif',
            'file' => [
                'name' => 'inter-400-normal.woff2',
                'tmp_name' => $tmpName,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmpName),
            ],
        ],
    ]);

    $savedPath = $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2');

    assertSameValue(1, (int) ($result['summary']['errors'] ?? 0), 'Uploads should error when PHP cannot verify the temp file as an HTTP upload.');
    assertContainsValue('could not be verified as a valid HTTP upload', (string) ($result['rows'][0]['message'] ?? ''), 'Uploads should explain when the temp file fails the PHP upload-origin guard.');
    assertSameValue(false, is_string($savedPath) && file_exists($savedPath), 'Uploads should not write font files when the temp file was not verified.');
};

$tests['local_upload_service_imports_verified_font_uploads'] = static function (): void {
    resetTestState();

    global $uploadedFilePaths;

    $services = makeServiceGraph();
    $tmpName = uniqueTestDirectory('tmp-upload-valid') . '/inter-400-italic.woff2';
    mkdir(dirname($tmpName), FS_CHMOD_DIR, true);
    file_put_contents($tmpName, "wOF2test-font");
    $uploadedFilePaths[] = $tmpName;

    $result = $services['local_upload']->uploadRows([
        [
            'family' => 'Inter',
            'weight' => '400',
            'style' => 'italic',
            'fallback' => 'Arial, sans-serif',
            'file' => [
                'name' => 'inter-400-italic.woff2',
                'tmp_name' => $tmpName,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmpName),
            ],
        ],
    ]);

    $savedPath = $services['storage']->pathForRelativePath('inter/Inter-400-italic.woff2');

    assertSameValue(1, (int) ($result['summary']['imported'] ?? 0), 'Verified HTTP uploads should be imported into the local library.');
    assertSameValue('imported', (string) ($result['rows'][0]['status'] ?? ''), 'Verified HTTP uploads should produce an imported row result.');
    assertSameValue(true, is_string($savedPath) && file_exists($savedPath), 'Verified HTTP uploads should be written into uploads/fonts.');
};

$tests['library_service_blocks_deleting_live_applied_family_when_draft_changed'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $fontPath = $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2');
    $services['storage']->writeAbsoluteFile((string) $fontPath, 'font-data');

    $catalog = ['Inter', 'Lora'];
    $services['settings']->saveRoles(
        [
            'heading' => 'Lora',
            'body' => 'Lora',
            'heading_fallback' => 'serif',
            'body_fallback' => 'serif',
        ],
        $catalog
    );
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Lora',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        $catalog
    );
    $services['settings']->setAutoApplyRoles(true);

    $result = $services['library']->deleteFamily('inter');

    assertSameValue(true, is_wp_error($result), 'Deleting a family should be blocked when it is still used by the live applied roles.');
    assertSameValue('tasty_fonts_family_in_use', $result->get_error_code(), 'Deleting a live applied role family should return the family-in-use error.');
    assertContainsValue('currently assigned as the heading font', $result->get_error_message(), 'The delete-family guard should explain that the family is still the live heading role.');
};

$tests['library_service_blocks_deleting_last_live_applied_variant_when_draft_changed'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $fontPath = $services['storage']->pathForRelativePath('inter/Inter-400-normal.woff2');
    $services['storage']->writeAbsoluteFile((string) $fontPath, 'font-data');

    $catalog = ['Inter', 'Lora'];
    $services['settings']->saveRoles(
        [
            'heading' => 'Lora',
            'body' => 'Lora',
            'heading_fallback' => 'serif',
            'body_fallback' => 'serif',
        ],
        $catalog
    );
    $services['settings']->saveAppliedRoles(
        [
            'heading' => 'Inter',
            'body' => 'Lora',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'serif',
        ],
        $catalog
    );
    $services['settings']->setAutoApplyRoles(true);

    $result = $services['library']->deleteFaceVariant('inter', '400', 'normal');

    assertSameValue(true, is_wp_error($result), 'Deleting the last variant should be blocked when the family is still used by the live applied roles.');
    assertSameValue('tasty_fonts_variant_in_use', $result->get_error_code(), 'Deleting the last live applied role variant should return the variant-in-use error.');
    assertContainsValue('currently assigned to heading', $result->get_error_message(), 'The delete-variant guard should explain that the family is still the live heading role.');
};

$tests['admin_controller_sanitizes_posted_fallback_values'] = static function (): void {
    resetTestState();

    $_POST['fallback'] = '  -apple-system, BlinkMacSystemFont, "Segoe UI", serif !@#$  ';

    $controller = makeAdminControllerTestInstance();
    $fallback = invokePrivateMethod($controller, 'getPostedFallback', ['fallback']);

    assertSameValue(
        '-apple-system, BlinkMacSystemFont, "Segoe UI", serif',
        $fallback,
        'Posted fallback values should be normalized through the controller before they reach settings storage.'
    );
};

$tests['admin_controller_builds_specific_settings_saved_message'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $message = invokePrivateMethod(
        $controller,
        'buildSettingsSavedMessage',
        [
            [
                'css_delivery_mode' => 'file',
                'font_display' => 'swap',
                'minify_css_output' => true,
                'preload_primary_fonts' => false,
                'preview_sentence' => 'Alpha',
            ],
            [
                'css_delivery_mode' => 'inline',
                'font_display' => 'optional',
                'minify_css_output' => false,
                'preload_primary_fonts' => true,
                'preview_sentence' => 'Beta',
            ],
        ]
    );

    assertContainsValue('delivery mode set to inline CSS', $message, 'Settings save messages should explain delivery-mode changes.');
    assertContainsValue('font-display set to optional', $message, 'Settings save messages should explain font-display changes.');
    assertContainsValue('CSS minification disabled', $message, 'Settings save messages should explain CSS minification changes.');
    assertContainsValue('primary font preloads enabled', $message, 'Settings save messages should explain preload setting changes.');
    assertContainsValue('preview text updated', $message, 'Settings save messages should explain preview text changes.');
};

$tests['admin_controller_exposes_all_font_display_options_with_optional_first'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $options = invokePrivateMethod($controller, 'buildFontDisplayOptions', []);

    assertSameValue('optional', (string) ($options[0]['value'] ?? ''), 'Optional should be the first font-display choice so the recommended default is selected first.');
    assertSameValue(
        ['optional', 'swap', 'fallback', 'block', 'auto'],
        array_values(array_map(static fn (array $option): string => (string) ($option['value'] ?? ''), $options)),
        'Output Settings should expose every supported font-display option.'
    );
};

$tests['admin_controller_exposes_family_font_display_options_with_inherit_first'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $options = invokePrivateMethod($controller, 'buildFamilyFontDisplayOptions', ['swap']);

    assertSameValue('inherit', (string) ($options[0]['value'] ?? ''), 'Per-family font-display controls should offer inherit as the first option.');
    assertContainsValue('Swap', (string) ($options[0]['label'] ?? ''), 'The inherit option should explain which global font-display value will be used.');
    assertSameValue(
        ['inherit', 'optional', 'swap', 'fallback', 'block', 'auto'],
        array_values(array_map(static fn (array $option): string => (string) ($option['value'] ?? ''), $options)),
        'Per-family font-display controls should expose inherit plus every supported override option.'
    );
};

$tests['admin_controller_detects_which_setting_changes_require_asset_refresh'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresAssetRefresh',
            [
                ['minify_css_output' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
                ['minify_css_output' => false, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
            ]
        ),
        'Disabling CSS minification should trigger a generated asset refresh.'
    );

    assertSameValue(
        true,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresAssetRefresh',
            [
                ['minify_css_output' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file'],
                ['minify_css_output' => true, 'font_display' => 'optional', 'css_delivery_mode' => 'file'],
            ]
        ),
        'Changing font-display should trigger a generated asset refresh.'
    );

    assertSameValue(
        false,
        invokePrivateMethod(
            $controller,
            'settingsChangeRequiresAssetRefresh',
            [
                ['minify_css_output' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file', 'preload_primary_fonts' => true],
                ['minify_css_output' => true, 'font_display' => 'swap', 'css_delivery_mode' => 'file', 'preload_primary_fonts' => false],
            ]
        ),
        'Preload-only changes should not force a generated CSS refresh.'
    );
};

$tests['admin_controller_versions_admin_assets_from_plugin_version'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $version = invokePrivateMethod($controller, 'assetVersionFor');

    assertSameValue(TASTY_FONTS_VERSION, $version, 'Admin asset versioning should reuse the plugin version instead of hashing shipped files on every request.');
};

$tests['admin_controller_builds_reordered_overview_metrics'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $metrics = invokePrivateMethod(
        $controller,
        'buildOverviewMetrics',
        [[
            'families' => 12,
            'published_families' => 7,
            'library_only_families' => 3,
            'local_families' => 9,
        ]]
    );

    assertSameValue(
        ['Families', 'Published', 'Paused', 'Self-hosted'],
        array_values(array_map(static fn (array $metric): string => (string) ($metric['label'] ?? ''), $metrics)),
        'The overview metrics should be reordered around library state and self-hosted counts instead of file size.'
    );
    assertSameValue(
        ['12', '7', '3', '9'],
        array_values(array_map(static fn (array $metric): string => (string) ($metric['value'] ?? ''), $metrics)),
        'The overview metrics should preserve the expected family counts after reordering.'
    );
};

$tests['admin_page_renderer_uses_inline_delivery_badge_for_single_delivery_families'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'Inter',
        'slug' => 'inter',
        'delivery_filter_tokens' => ['google'],
        'publish_state' => 'published',
        'active_delivery_id' => 'google-cdn',
        'active_delivery' => [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular', '700'],
        ],
        'available_deliveries' => [
            [
                'id' => 'google-cdn',
                'label' => 'Google CDN',
                'provider' => 'google',
                'type' => 'cdn',
                'variants' => ['regular', '700'],
            ],
        ],
        'delivery_badges' => [
            [
                'label' => 'Google CDN',
                'class' => '',
                'copy' => 'Google CDN',
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'google',
                'files' => [],
                'paths' => [],
            ],
        ],
    ];

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFamilyRow',
        [
            $family,
            ['heading' => '', 'body' => ''],
            [],
            [],
            [
                ['value' => 'inherit', 'label' => 'Use plugin default'],
                ['value' => 'swap', 'label' => 'swap'],
            ],
            'The quick brown fox jumps over the lazy dog.',
        ]
    );
    $output = (string) ob_get_clean();

    assertContainsValue('data-font-slug="inter"', $output, 'Library family rows should expose the normalized slug for client-side matching and post-import highlighting.');
    assertContainsValue('tasty-fonts-badges--library-inline', $output, 'Single-delivery families should render the compact inline badge treatment.');
    assertContainsValue('Google CDN', $output, 'The active single delivery label should stay visible on the family card.');
    assertContainsValue('tasty-fonts-detail-group', $output, 'Family details should render as design-system card groups instead of plain WordPress tables.');
    assertNotContainsValue('Available delivery profiles', $output, 'Single-delivery families should not render the verbose available deliveries note.');
    assertNotContainsValue('Live delivery', $output, 'Single-delivery families should not render the verbose live delivery note.');
    assertNotContainsValue('widefat striped tasty-fonts-table', $output, 'Family details should no longer use widefat table markup.');
};

$tests['admin_page_renderer_outputs_migrate_shortcuts_for_cdn_deliveries'] = static function (): void {
    resetTestState();

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'Inter',
        'slug' => 'inter',
        'delivery_filter_tokens' => ['google'],
        'publish_state' => 'published',
        'active_delivery_id' => 'google-cdn',
        'active_delivery' => [
            'id' => 'google-cdn',
            'label' => 'Google CDN',
            'provider' => 'google',
            'type' => 'cdn',
            'variants' => ['regular', '700'],
        ],
        'available_deliveries' => [
            [
                'id' => 'google-cdn',
                'label' => 'Google CDN',
                'provider' => 'google',
                'type' => 'cdn',
                'variants' => ['regular', '700'],
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'google',
                'files' => [],
                'paths' => [],
            ],
        ],
    ];

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFamilyRow',
        [
            $family,
            ['heading' => '', 'body' => ''],
            [],
            [],
            [
                ['value' => 'inherit', 'label' => 'Use plugin default'],
            ],
            'The quick brown fox jumps over the lazy dog.',
        ]
    );
    $output = (string) ob_get_clean();

    assertSameValue(2, substr_count($output, 'data-migrate-delivery'), 'CDN-backed library families should expose a self-host migration shortcut in both the card actions and delivery table.');
    assertContainsValue('data-migrate-provider="google"', $output, 'The migration shortcut should preserve the delivery provider.');
    assertContainsValue('data-migrate-family="Inter"', $output, 'The migration shortcut should preserve the family name for panel prefill.');
    assertContainsValue('data-migrate-variants="regular,700"', $output, 'The migration shortcut should preserve the saved variant tokens for self-hosting prefill.');
};

$tests['font_utils_modern_user_agent_tracks_a_recent_chrome_release'] = static function (): void {
    assertContainsValue('Chrome/146.0.0.0', FontUtils::MODERN_USER_AGENT, 'The modern browser user agent should stay current enough to trigger Google Fonts CSS2 WOFF2 responses.');
};

$tests['admin_page_renderer_translates_stored_delivery_profile_labels_at_output'] = static function (): void {
    resetTestState();

    global $translationMap;

    $translationMap = [
        'Self-hosted (Google import)' => 'Import Google auto-heberge',
        'Adobe-hosted' => 'Heberge par Adobe',
        'Same-origin self-hosted files' => 'Fichiers auto-heberges meme origine',
        'Adobe-hosted project stylesheet' => 'Feuille de style de projet Adobe hebergee',
    ];

    $renderer = new AdminPageRenderer(new Storage());
    $family = [
        'family' => 'Inter',
        'slug' => 'inter',
        'delivery_filter_tokens' => ['google', 'adobe'],
        'publish_state' => 'published',
        'active_delivery_id' => 'google-self_hosted',
        'active_delivery' => [
            'id' => 'google-self_hosted',
            'label' => 'Self-hosted (Google import)',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular'],
        ],
        'available_deliveries' => [
            [
                'id' => 'google-self_hosted',
                'label' => 'Self-hosted (Google import)',
                'provider' => 'google',
                'type' => 'self_hosted',
                'variants' => ['regular'],
            ],
            [
                'id' => 'adobe-adobe_hosted',
                'label' => 'Adobe-hosted',
                'provider' => 'adobe',
                'type' => 'adobe_hosted',
                'variants' => [],
            ],
        ],
        'faces' => [
            [
                'weight' => '400',
                'style' => 'normal',
                'source' => 'google',
                'files' => [],
                'paths' => [],
            ],
        ],
    ];

    ob_start();
    invokePrivateMethod(
        $renderer,
        'renderFamilyRow',
        [
            $family,
            ['heading' => '', 'body' => ''],
            [],
            [],
            [
                ['value' => 'inherit', 'label' => 'Use plugin default'],
            ],
            'The quick brown fox jumps over the lazy dog.',
        ]
    );
    $output = (string) ob_get_clean();

    assertContainsValue('Import Google auto-heberge', $output, 'Stored English delivery labels should be translated when rendered in the family card.');
    assertContainsValue('Heberge par Adobe', $output, 'Stored English delivery labels should be translated in delivery lists and detail cards.');
    assertContainsValue('Fichiers auto-heberges meme origine', $output, 'Self-hosted request summaries should stay translatable at render time.');
    assertContainsValue('Feuille de style de projet Adobe hebergee', $output, 'Adobe request summaries should stay translatable at render time.');
    assertNotContainsValue('Self-hosted (Google import)', $output, 'The family card should not render the raw stored English Google self-hosted label once translated.');
    assertNotContainsValue('Adobe-hosted', $output, 'The family card should not render the raw stored English Adobe label once translated.');
};

$tests['admin_controller_excludes_generated_css_from_snippet_output_panels'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400.woff2'), 'font-data');
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $services['settings']->saveRoles($roles, ['Inter']);
    $services['settings']->setAutoApplyRoles(true);

    $panels = invokePrivateMethod(
        $services['controller'],
        'buildOutputPanels',
        [$roles, $services['settings']->getSettings()]
    );

    assertSameValue(
        ['usage', 'variables', 'stacks', 'names'],
        array_values(array_map(static fn (array $panel): string => (string) ($panel['key'] ?? ''), $panels)),
        'The Snippets panel should only expose the role snippet tabs after Generated CSS is moved to the top tab bar.'
    );
};

$tests['admin_controller_exposes_generated_css_as_a_top_level_panel'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400.woff2'), 'font-data');
    $roles = [
        'heading' => 'Inter',
        'body' => 'Inter',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'sans-serif',
    ];
    $services['settings']->saveRoles($roles, ['Inter']);
    $services['settings']->setAutoApplyRoles(true);

    $panel = invokePrivateMethod(
        $services['controller'],
        'buildGeneratedCssPanel',
        [$services['settings']->getSettings()]
    );

    assertSameValue('generated', (string) ($panel['key'] ?? ''), 'Generated CSS should be exposed through a dedicated top-level panel key.');
    assertContainsValue('@font-face', (string) ($panel['value'] ?? ''), 'The top-level Generated CSS panel should include the current stylesheet output.');
};

$tests['admin_controller_marks_top_level_generated_css_panel_unavailable_when_sitewide_is_off'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $panel = invokePrivateMethod(
        $services['controller'],
        'buildGeneratedCssPanel',
        [$services['settings']->getSettings()]
    );

    assertSameValue(
        'Not generated while Apply Sitewide is off.',
        (string) ($panel['value'] ?? ''),
        'The top-level Generated CSS panel should explain that there is no live sitewide output while Apply Sitewide is off.'
    );
};

$tests['admin_controller_reuses_cached_search_results_during_search_cooldown'] = static function (): void {
    resetTestState();

    global $currentUserId;

    $currentUserId = 42;
    $services = makeServiceGraph();
    $resolverCalls = 0;

    $first = invokePrivateMethod(
        $services['controller'],
        'resolveRateLimitedSearch',
        [
            'google',
            'Inter',
            static function (string $query) use (&$resolverCalls): array {
                $resolverCalls += 1;

                return ['items' => [['family' => $query, 'call' => $resolverCalls]]];
            },
        ]
    );
    $second = invokePrivateMethod(
        $services['controller'],
        'resolveRateLimitedSearch',
        [
            'google',
            'Inter',
            static function (string $query) use (&$resolverCalls): array {
                $resolverCalls += 1;

                return ['items' => [['family' => $query, 'call' => $resolverCalls]]];
            },
        ]
    );
    $third = invokePrivateMethod(
        $services['controller'],
        'resolveRateLimitedSearch',
        [
            'google',
            'Lora',
            static function (string $query) use (&$resolverCalls): array {
                $resolverCalls += 1;

                return ['items' => [['family' => $query, 'call' => $resolverCalls]]];
            },
        ]
    );

    assertSameValue(2, $resolverCalls, 'Repeated search calls in the cooldown window should reuse the cached result, while new queries should still execute.');
    assertSameValue($first, $second, 'Repeated search calls in the cooldown window should return the cached response payload.');
    assertSameValue('Lora', (string) ($third['items'][0]['family'] ?? ''), 'Search cooldown should not block a different query for the same user.');
    assertSameValue(
        true,
        is_array(get_transient('tasty_fonts_search_cooldown_42')),
        'Search cooldown should be stored in a per-user transient.'
    );
};

$tests['admin_controller_enqueues_tokens_before_admin_styles'] = static function (): void {
    resetTestState();

    global $enqueuedStyles;

    $services = makeServiceGraph();
    $services['controller']->enqueueAssets('toplevel_page_' . AdminController::MENU_SLUG);

    assertSameValue(
        TASTY_FONTS_URL . 'assets/css/tokens.css',
        (string) ($enqueuedStyles['tasty-fonts-admin-tokens']['src'] ?? ''),
        'The plugin admin page should enqueue the standalone token stylesheet.'
    );

    assertSameValue(
        ['tasty-fonts-admin-tokens'],
        $enqueuedStyles['tasty-fonts-admin']['deps'] ?? null,
        'The admin stylesheet should depend on the token stylesheet so custom properties load first.'
    );
};

$tests['admin_controller_localizes_rest_transport_config'] = static function (): void {
    resetTestState();

    global $localizedScripts;

    $services = makeServiceGraph();
    $services['controller']->enqueueAssets('toplevel_page_' . AdminController::MENU_SLUG);

    assertSameValue(
        'https://example.test/wp-json/tasty-fonts/v1/',
        (string) ($localizedScripts['tasty-fonts-admin']['data']['restUrl'] ?? ''),
        'Admin scripts should receive the REST base URL used by the bundled admin client.'
    );
    assertSameValue(
        'nonce:wp_rest',
        (string) ($localizedScripts['tasty-fonts-admin']['data']['restNonce'] ?? ''),
        'Admin scripts should receive the WordPress REST nonce for authenticated requests.'
    );
    assertSameValue(
        'google/search',
        (string) ($localizedScripts['tasty-fonts-admin']['data']['routes']['searchGoogle'] ?? ''),
        'Admin scripts should receive the Google search REST route path.'
    );
    assertSameValue(
        'local/upload',
        (string) ($localizedScripts['tasty-fonts-admin']['data']['routes']['uploadLocal'] ?? ''),
        'Admin scripts should receive the local upload REST route path.'
    );
};

$tests['admin_controller_omits_ajax_transport_config_from_localized_admin_data'] = static function (): void {
    resetTestState();

    global $localizedScripts;

    $services = makeServiceGraph();
    $services['controller']->enqueueAssets('toplevel_page_' . AdminController::MENU_SLUG);

    $data = is_array($localizedScripts['tasty-fonts-admin']['data'] ?? null)
        ? $localizedScripts['tasty-fonts-admin']['data']
        : [];

    assertSameValue(false, isset($data['ajaxUrl']), 'Admin scripts should not receive an admin-ajax endpoint once the plugin is REST-only.');

    foreach (
        [
            'searchNonce',
            'bunnySearchNonce',
            'googleFamilyNonce',
            'bunnyFamilyNonce',
            'importNonce',
            'bunnyImportNonce',
            'uploadNonce',
            'saveFallbackNonce',
            'saveFontDisplayNonce',
            'saveRolesNonce',
            'saveFamilyDeliveryNonce',
            'saveFamilyPublishStateNonce',
            'deleteDeliveryProfileNonce',
        ] as $key
    ) {
        assertSameValue(false, isset($data[$key]), sprintf('Admin scripts should not receive the removed "%s" AJAX nonce field.', $key));
    }
};

$tests['admin_controller_enqueues_wp_i18n_and_script_translations'] = static function (): void {
    resetTestState();

    global $enqueuedScripts;
    global $scriptTranslations;

    $services = makeServiceGraph();
    $services['controller']->enqueueAssets('toplevel_page_' . AdminController::MENU_SLUG);

    assertSameValue(
        ['wp-i18n'],
        $enqueuedScripts['tasty-fonts-admin']['deps'] ?? null,
        'Admin scripts should depend on wp-i18n so script translations are available.'
    );
    assertSameValue(
        'tasty-fonts',
        (string) ($scriptTranslations['tasty-fonts-admin']['domain'] ?? ''),
        'Admin scripts should register WordPress script translations for the tasty-fonts domain.'
    );
    assertSameValue(
        TASTY_FONTS_DIR . 'languages',
        (string) ($scriptTranslations['tasty-fonts-admin']['path'] ?? ''),
        'Admin scripts should register the plugin languages directory for script translation JSON files.'
    );
};

$tests['admin_controller_localizes_runtime_admin_strings_only'] = static function (): void {
    resetTestState();

    global $localizedScripts;

    $services = makeServiceGraph();
    $services['controller']->enqueueAssets('toplevel_page_' . AdminController::MENU_SLUG);

    $runtimeStrings = is_array($localizedScripts['tasty-fonts-admin']['data']['runtimeStrings'] ?? null)
        ? $localizedScripts['tasty-fonts-admin']['data']['runtimeStrings']
        : [];

    assertSameValue(
        'Add a Google Fonts API key above to enable search, or use manual import below.',
        (string) ($runtimeStrings['searchDisabled'] ?? ''),
        'Admin scripts should still receive the runtime-only search-disabled message from PHP.'
    );
    assertSameValue(
        false,
        isset($localizedScripts['tasty-fonts-admin']['data']['strings']),
        'Admin scripts should not receive the legacy static string table once script translations are registered.'
    );
};

$tests['rest_controller_registers_expected_admin_routes'] = static function (): void {
    resetTestState();

    global $registeredRestRoutes;

    $services = makeServiceGraph();
    $services['rest']->registerRoutes();

    $expectedRoutes = [
        'tasty-fonts/v1/google/search' => 'GET',
        'tasty-fonts/v1/bunny/search' => 'GET',
        'tasty-fonts/v1/google/family' => 'GET',
        'tasty-fonts/v1/bunny/family' => 'GET',
        'tasty-fonts/v1/google/import' => 'POST',
        'tasty-fonts/v1/bunny/import' => 'POST',
        'tasty-fonts/v1/local/upload' => 'POST',
        'tasty-fonts/v1/families/fallback' => 'PATCH',
        'tasty-fonts/v1/families/font-display' => 'PATCH',
        'tasty-fonts/v1/roles/draft' => 'PATCH',
        'tasty-fonts/v1/families/delivery' => 'PATCH',
        'tasty-fonts/v1/families/publish-state' => 'PATCH',
        'tasty-fonts/v1/families/delivery-profile' => 'DELETE',
    ];

    assertSameValue(
        count($expectedRoutes),
        count($registeredRestRoutes),
        'The REST controller should register the full admin route surface.'
    );

    foreach ($expectedRoutes as $route => $method) {
        assertSameValue(
            $method,
            (string) ($registeredRestRoutes[$route]['args']['methods'] ?? ''),
            'Each REST route should register with the expected HTTP method.'
        );
        assertSameValue(
            true,
            is_callable($registeredRestRoutes[$route]['args']['callback'] ?? null),
            'Each REST route should register a callable endpoint handler.'
        );
        assertSameValue(
            true,
            is_callable($registeredRestRoutes[$route]['args']['permission_callback'] ?? null),
            'Each REST route should register a callable permission callback.'
        );
    }
};

$tests['rest_controller_returns_native_payloads_for_write_routes'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('PATCH', '/' . RestController::API_NAMESPACE . '/families/fallback');
    $request->set_body_params([
        'family' => 'Inter',
        'fallback' => 'serif',
    ]);

    $response = $services['rest']->saveFamilyFallback($request);

    assertSameValue(true, $response instanceof WP_REST_Response, 'REST write routes should return a native REST response object.');
    assertSameValue('Inter', (string) ($response->get_data()['family'] ?? ''), 'REST write routes should return the saved family in the response body.');
    assertSameValue('serif', (string) ($response->get_data()['fallback'] ?? ''), 'REST write routes should return the saved fallback in the response body.');
};

$tests['rest_controller_wraps_missing_family_errors_with_http_status'] = static function (): void {
    resetTestState();

    $services = makeServiceGraph();
    $request = new WP_REST_Request('GET', '/' . RestController::API_NAMESPACE . '/bunny/family');
    $request->set_query_params(['family' => 'Missing Bunny Family']);

    $response = $services['rest']->fetchBunnyFamily($request);

    assertSameValue(true, is_wp_error($response), 'REST read routes should return WP_Error objects when the underlying lookup fails.');
    assertSameValue('tasty_fonts_bunny_family_not_found', $response->get_error_code(), 'REST error responses should preserve the original plugin error code.');
    assertSameValue(404, (int) (($response->get_error_data()['status'] ?? 0)), 'REST error responses should expose the HTTP status expected by the client.');
};

$tests['rest_controller_upload_route_returns_native_payloads'] = static function (): void {
    resetTestState();

    global $uploadedFilePaths;

    $services = makeServiceGraph();
    $tmpName = uniqueTestDirectory('tmp-rest-upload-valid') . '/inter-400-italic.woff2';
    mkdir(dirname($tmpName), FS_CHMOD_DIR, true);
    file_put_contents($tmpName, "wOF2test-font");
    $uploadedFilePaths[] = $tmpName;

    $request = new WP_REST_Request('POST', '/' . RestController::API_NAMESPACE . '/local/upload');
    $request->set_param('rows', [[
        'family' => 'Inter',
        'weight' => '400',
        'style' => 'italic',
        'fallback' => 'Arial, sans-serif',
    ]]);
    $request->set_file_params([
        'files' => [
            'name' => [0 => 'inter-400-italic.woff2'],
            'type' => [0 => 'font/woff2'],
            'tmp_name' => [0 => $tmpName],
            'error' => [0 => UPLOAD_ERR_OK],
            'size' => [0 => filesize($tmpName)],
        ],
    ]);

    $response = $services['rest']->uploadLocalFonts($request);
    $data = $response instanceof WP_REST_Response ? $response->get_data() : [];

    assertSameValue(true, $response instanceof WP_REST_Response, 'The REST upload route should return a native REST response object.');
    assertSameValue('imported', (string) ($data['rows'][0]['status'] ?? ''), 'The REST upload route should return the imported row result directly in the response body.');
    assertSameValue(1, (int) ($data['summary']['imported'] ?? 0), 'The REST upload route should return the import summary directly in the response body.');
};

$tests['google_search_cooldown_cache_is_shared_between_repeated_rest_requests'] = static function (): void {
    resetTestState();

    global $currentUserId;

    $currentUserId = 42;
    $services = makeServiceGraph();
    $services['settings']->saveSettings(['google_api_key' => 'live-key']);
    $services['settings']->saveGoogleApiKeyStatus('valid');
    set_transient(
        'tasty_fonts_google_catalog_v1',
        [
            'inter' => [
                'family' => 'Inter',
                'category' => 'sans-serif',
                'variants_count' => 4,
            ],
        ],
        HOUR_IN_SECONDS
    );

    $request = new WP_REST_Request('GET', '/' . RestController::API_NAMESPACE . '/google/search');
    $request->set_query_params(['query' => 'Inter']);
    $firstResponse = $services['rest']->searchGoogle($request);
    $firstData = $firstResponse instanceof WP_REST_Response ? $firstResponse->get_data() : [];

    set_transient(
        'tasty_fonts_google_catalog_v1',
        [
            'inter' => [
                'family' => 'Inter',
                'category' => 'sans-serif',
                'variants_count' => 8,
            ],
        ],
        HOUR_IN_SECONDS
    );

    $secondRequest = new WP_REST_Request('GET', '/' . RestController::API_NAMESPACE . '/google/search');
    $secondRequest->set_query_params(['query' => 'Inter']);
    $secondResponse = $services['rest']->searchGoogle($secondRequest);
    $secondData = $secondResponse instanceof WP_REST_Response ? $secondResponse->get_data() : [];

    assertSameValue(4, (int) ($firstData['items'][0]['variants_count'] ?? 0), 'The first REST search response should reflect the initial cached search result.');
    assertSameValue(4, (int) ($secondData['items'][0]['variants_count'] ?? 0), 'The second REST search response should reuse the cooldown cache instead of re-reading the mutated catalog.');
};

$tests['admin_controller_family_font_display_changes_refresh_generated_assets'] = static function (): void {
    resetTestState();

    global $transientDeleted;

    $services = makeServiceGraph();
    $services['storage']->ensureRootDirectory();
    $services['storage']->writeAbsoluteFile((string) $services['storage']->pathForRelativePath('inter/Inter-400.woff2'), 'font-data');
    $services['settings']->saveRoles(
        [
            'heading' => 'Inter',
            'body' => 'Inter',
            'heading_fallback' => 'sans-serif',
            'body_fallback' => 'sans-serif',
        ],
        ['Inter']
    );
    $services['settings']->setAutoApplyRoles(true);

    $services['assets']->getCss();
    $transientDeleted = [];

    invokePrivateMethod($services['controller'], 'saveFamilyFontDisplaySelection', ['Inter', 'swap']);

    assertSameValue(true, in_array('tasty_fonts_css_v2', $transientDeleted, true), 'Saving a family font-display override should invalidate the cached CSS payload.');
    assertContainsValue('font-display:swap', $services['assets']->getCss(), 'Saving a family font-display override should rebuild the generated CSS with the new value.');
};

$tests['admin_controller_builds_notice_messages_from_known_keys'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();

    assertSameValue(
        'Google Fonts API key saved and validated.',
        invokePrivateMethod($controller, 'buildNoticeMessage', ['google_key_saved']),
        'Known notice keys should resolve to the translated message text.'
    );
    assertSameValue(
        'Font family deleted.',
        invokePrivateMethod($controller, 'buildNoticeMessage', ['family_deleted']),
        'Notice message lookup should cover delete messages that do not have another static translation call site.'
    );
    assertSameValue(
        '',
        invokePrivateMethod($controller, 'buildNoticeMessage', ['missing_notice_key']),
        'Unknown notice keys should return an empty string so the caller can fall back to a plain redirect.'
    );
};

$tests['admin_controller_reads_and_clears_transient_notice_toasts'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $transientKey = invokePrivateMethod($controller, 'getPendingNoticeTransientKey');

    set_transient(
        $transientKey,
        [[
            'tone' => 'success',
            'message' => 'Plugin settings saved: preview text updated.',
            'role' => 'status',
        ]],
        300
    );

    $toasts = invokePrivateMethod($controller, 'buildNoticeToasts');

    assertSameValue('Plugin settings saved: preview text updated.', $toasts[0]['message'] ?? '', 'Settings toasts should come from the per-user transient store.');
    assertSameValue(false, get_transient($transientKey), 'Notice toasts should be cleared after they are read for rendering.');
};

$tests['admin_controller_queues_redirect_toasts_for_the_current_user'] = static function (): void {
    resetTestState();

    global $transientSet;

    $controller = makeAdminControllerTestInstance();
    $transientKey = invokePrivateMethod($controller, 'getPendingNoticeTransientKey');

    invokePrivateMethod($controller, 'queueNoticeToast', ['error', 'Variant deleted: Inter 400 italic.', 'alert']);
    $toasts = get_transient($transientKey);

    assertSameValue(true, in_array($transientKey, $transientSet, true), 'Queued redirect toasts should be written into the current user transient.');
    assertSameValue('error', (string) ($toasts[0]['tone'] ?? ''), 'Queued redirect toasts should persist the toast tone.');
    assertSameValue('Variant deleted: Inter 400 italic.', (string) ($toasts[0]['message'] ?? ''), 'Queued redirect toasts should persist the rendered message.');
    assertSameValue('alert', (string) ($toasts[0]['role'] ?? ''), 'Queued redirect toasts should persist the ARIA role.');
};

$tests['admin_controller_builds_a_clean_plugin_redirect_url'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $url = invokePrivateMethod($controller, 'buildAdminPageUrl');

    assertSameValue('https://example.test/wp-admin/admin.php?page=tasty-custom-fonts', $url, 'Redirect URLs should stay on the plugin admin page without encoding toast data in the query string.');
};

$tests['plugin_adds_a_settings_link_to_plugin_action_links'] = static function (): void {
    $links = Plugin::filterPluginActionLinks(['<a href="https://example.test/deactivate">Deactivate</a>']);

    assertContainsValue('admin.php?page=tasty-custom-fonts', $links[0] ?? '', 'Plugin action links should include a direct Settings link to the plugin admin page.');
    assertContainsValue('Settings', $links[0] ?? '', 'Plugin action links should label the direct admin link as Settings.');
};

$tests['plugin_adds_row_meta_links_for_releases_and_support'] = static function (): void {
    $links = Plugin::filterPluginRowMeta([], plugin_basename(TASTY_FONTS_FILE));

    assertContainsValue('/releases', $links[0] ?? '', 'Plugin row meta should expose the GitHub releases page.');
    assertContainsValue('GitHub Releases', $links[0] ?? '', 'Plugin row meta should label the releases link clearly.');
    assertContainsValue('/issues', $links[1] ?? '', 'Plugin row meta should expose the support issues page.');
    assertContainsValue('Support', $links[1] ?? '', 'Plugin row meta should label the support link clearly.');
};

$tests['plugin_row_meta_ignores_other_plugins'] = static function (): void {
    $links = Plugin::filterPluginRowMeta(['existing'], 'other-plugin/other-plugin.php');

    assertSameValue(['existing'], $links, 'Plugin row meta should not modify rows for unrelated plugins.');
};

$tests['plugin_boot_registers_plugin_row_meta_rest_and_font_library_sync_hooks'] = static function (): void {
    resetTestState();
    resetPluginSingleton();

    global $hookCallbacks;

    Plugin::instance()->boot();

    assertSameValue(true, isset($hookCallbacks['plugin_row_meta']), 'Boot should register the plugin row meta hook.');
    assertSameValue(true, isset($hookCallbacks['rest_api_init']), 'Boot should register the REST API init hook.');
    assertSameValue(true, isset($hookCallbacks['tasty_fonts_after_import']), 'Boot should register the Block Editor Font Library sync hook.');
    assertSameValue(true, isset($hookCallbacks['tasty_fonts_after_delete_family']), 'Boot should register the Block Editor Font Library delete hook.');
    foreach (
        [
            'wp_ajax_tasty_fonts_search_google',
            'wp_ajax_tasty_fonts_get_google_family',
            'wp_ajax_tasty_fonts_search_bunny',
            'wp_ajax_tasty_fonts_get_bunny_family',
            'wp_ajax_tasty_fonts_import_bunny',
            'wp_ajax_tasty_fonts_import_google',
            'wp_ajax_tasty_fonts_upload_local',
            'wp_ajax_tasty_fonts_save_family_fallback',
            'wp_ajax_tasty_fonts_save_family_font_display',
            'wp_ajax_tasty_fonts_save_family_delivery',
            'wp_ajax_tasty_fonts_save_family_publish_state',
            'wp_ajax_tasty_fonts_delete_delivery_profile',
            'wp_ajax_tasty_fonts_save_role_draft',
        ] as $hookName
    ) {
        assertSameValue(false, isset($hookCallbacks[$hookName]), sprintf('Boot should not register the removed "%s" AJAX hook.', $hookName));
    }

    resetPluginSingleton();
};

$tests['plugin_deactivation_flushes_known_transients_and_clears_css_regeneration_hook'] = static function (): void {
    resetTestState();

    global $clearedScheduledHooks;

    foreach ([
        'tasty_fonts_catalog_v2',
        'tasty_fonts_css_v2',
        'tasty_fonts_css_hash_v2',
        'tasty_fonts_regenerate_css_queued',
        'tasty_fonts_google_catalog_v1',
        'tasty_fonts_bunny_catalog_v1',
    ] as $transientKey) {
        set_transient($transientKey, 'cached', DAY_IN_SECONDS);
    }

    Plugin::deactivate();

    foreach ([
        'tasty_fonts_catalog_v2',
        'tasty_fonts_css_v2',
        'tasty_fonts_css_hash_v2',
        'tasty_fonts_regenerate_css_queued',
        'tasty_fonts_google_catalog_v1',
        'tasty_fonts_bunny_catalog_v1',
    ] as $transientKey) {
        assertSameValue(false, get_transient($transientKey), 'Deactivation should clear known plugin transients.');
    }

    assertSameValue(
        true,
        in_array(AssetService::ACTION_REGENERATE_CSS, $clearedScheduledHooks, true),
        'Deactivation should clear any queued CSS regeneration cron hook.'
    );
};

$tests['block_editor_font_library_sync_registers_managed_font_families_after_import'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;
    global $remoteRequestCalls;
    global $remoteRequestResponses;

    $services = makeServiceGraph();
    $services['settings']->saveFamilyFontDisplay('Inter', 'swap');
    $family = $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-self-hosted',
            'provider' => 'google',
            'type' => 'self_hosted',
            'label' => 'Self-hosted (Google import)',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'google',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'google/inter/inter-400-normal.woff2'],
                'provider' => ['category' => 'sans-serif'],
            ]],
            'meta' => ['category' => 'sans-serif'],
        ],
        'published',
        true
    );

    $remoteGetResponses['https://example.test/wp-json/wp/v2/font-families?slug=tasty-fonts-inter&context=edit'] = [
        'response' => ['code' => 200],
        'body' => '[]',
    ];
    $remoteRequestResponses['POST https://example.test/wp-json/wp/v2/font-families'] = [
        'response' => ['code' => 201],
        'body' => json_encode(['id' => 321]),
    ];
    $remoteRequestResponses['POST https://example.test/wp-json/wp/v2/font-families/321/font-faces'] = [
        'response' => ['code' => 201],
        'body' => json_encode(['id' => 654]),
    ];

    $services['block_editor_font_library']->syncImportedFamily(
        [
            'status' => 'imported',
            'family' => 'Inter',
            'delivery_id' => 'google-self-hosted',
            'family_record' => $family,
        ],
        'google'
    );

    assertSameValue(
        'https://example.test/wp-json/wp/v2/font-families?slug=tasty-fonts-inter&context=edit',
        (string) ($remoteGetCalls[0]['url'] ?? ''),
        'Font Library sync should look up the managed family slug before creating it.'
    );
    assertSameValue(2, count($remoteRequestCalls), 'Font Library sync should create one family and one face for the imported profile.');

    $familyBody = json_decode((string) ($remoteRequestCalls[0]['args']['body']['font_family_settings'] ?? ''), true);
    $faceBody = json_decode((string) ($remoteRequestCalls[1]['args']['body']['font_face_settings'] ?? ''), true);

    assertSameValue('tasty-fonts-inter', (string) ($familyBody['slug'] ?? ''), 'Font Library sync should register plugin-managed family slugs to avoid collisions.');
    assertSameValue('Inter', (string) ($familyBody['name'] ?? ''), 'Font Library sync should preserve the family display name.');
    assertSameValue('"Inter", sans-serif', (string) ($familyBody['fontFamily'] ?? ''), 'Font Library sync should register the family stack for editor presets.');
    assertSameValue('"Inter"', (string) ($faceBody['fontFamily'] ?? ''), 'Font Library sync should use a quoted family name in font-face definitions.');
    assertSameValue('swap', (string) ($faceBody['fontDisplay'] ?? ''), 'Font Library sync should carry the plugin font-display setting into editor font faces.');
    assertSameValue(
        'https://example.test/wp-content/uploads/fonts/google/inter/inter-400-normal.woff2',
        (string) (($faceBody['src'][0] ?? '')),
        'Font Library sync should convert stored relative font paths into public upload URLs.'
    );
};

$tests['block_editor_font_library_sync_respects_opt_out_filter'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteRequestCalls;

    add_filter(
        'tasty_fonts_sync_block_editor_font_library',
        static function (): bool {
            return false;
        }
    );

    $services = makeServiceGraph();
    $family = $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-self-hosted',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'google',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'google/inter/inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );

    $services['block_editor_font_library']->syncImportedFamily(
        [
            'status' => 'imported',
            'family' => 'Inter',
            'delivery_id' => 'google-self-hosted',
            'family_record' => $family,
        ],
        'google'
    );

    assertSameValue([], $remoteGetCalls, 'The opt-out filter should skip Font Library lookup requests.');
    assertSameValue([], $remoteRequestCalls, 'The opt-out filter should skip Font Library write requests.');
};

$tests['block_editor_font_library_sync_skips_when_core_font_post_types_are_unavailable'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteRequestCalls;
    global $supportedPostTypes;

    $supportedPostTypes = [];

    $services = makeServiceGraph();
    $family = $services['imports']->saveProfile(
        'Inter',
        'inter',
        [
            'id' => 'google-self-hosted',
            'provider' => 'google',
            'type' => 'self_hosted',
            'variants' => ['regular'],
            'faces' => [[
                'family' => 'Inter',
                'slug' => 'inter',
                'source' => 'google',
                'weight' => '400',
                'style' => 'normal',
                'files' => ['woff2' => 'google/inter/inter-400-normal.woff2'],
            ]],
        ],
        'published',
        true
    );

    $services['block_editor_font_library']->syncImportedFamily(
        [
            'status' => 'imported',
            'family' => 'Inter',
            'delivery_id' => 'google-self-hosted',
            'family_record' => $family,
        ],
        'google'
    );

    assertSameValue([], $remoteGetCalls, 'Font Library sync should no-op on WordPress versions without the core font post types.');
    assertSameValue([], $remoteRequestCalls, 'Font Library sync should no-op on WordPress versions without the core font post types.');
};

$tests['block_editor_font_library_sync_removes_managed_family_records_on_delete'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;
    global $remoteGetCalls;
    global $remoteRequestCalls;
    global $remoteRequestResponses;

    $services = makeServiceGraph();
    $remoteGetResponses['https://example.test/wp-json/wp/v2/font-families?slug=tasty-fonts-inter&context=edit'] = [
        'response' => ['code' => 200],
        'body' => json_encode([['id' => 321]]),
    ];
    $remoteRequestResponses['DELETE https://example.test/wp-json/wp/v2/font-families/321?force=true'] = [
        'response' => ['code' => 200],
        'body' => json_encode(['deleted' => true]),
    ];

    $services['block_editor_font_library']->deleteSyncedFamily('inter', 'Inter');

    assertSameValue(
        'https://example.test/wp-json/wp/v2/font-families?slug=tasty-fonts-inter&context=edit',
        (string) ($remoteGetCalls[0]['url'] ?? ''),
        'Font Library delete sync should look up the managed family slug before deleting it.'
    );
    assertSameValue(
        'DELETE',
        (string) ($remoteRequestCalls[0]['method'] ?? ''),
        'Font Library delete sync should issue a DELETE request to the managed core font family.'
    );
    assertSameValue(
        'https://example.test/wp-json/wp/v2/font-families/321?force=true',
        (string) ($remoteRequestCalls[0]['url'] ?? ''),
        'Font Library delete sync should force-delete the managed core font family.'
    );
};

$tests['log_repository_can_reseed_audit_entry_after_clear'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $log->add('Fonts rescanned.');
    $log->clear();
    $log->add('Activity log cleared. Older entries removed.');

    $entries = $log->all();

    assertSameValue(1, count($entries), 'Clearing the log and reseeding the audit entry should leave exactly one retained entry.');
    assertSameValue('Activity log cleared. Older entries removed.', $entries[0]['message'] ?? '', 'The retained entry should explain that the older activity was removed.');
    assertSameValue('System', $entries[0]['actor'] ?? '', 'The retained clear-log audit entry should still record an actor label.');
};

$tests['admin_controller_builds_distinct_sorted_activity_actor_options'] = static function (): void {
    resetTestState();

    $controller = makeAdminControllerTestInstance();
    $actors = invokePrivateMethod(
        $controller,
        'buildActivityActorOptions',
        [[
            ['actor' => 'sathyvelukunashegaran'],
            ['actor' => 'System'],
            ['actor' => 'sathyvelukunashegaran'],
            ['actor' => 'Alicia'],
            ['actor' => ''],
        ]]
    );

    assertSameValue(
        ['Alicia', 'sathyvelukunashegaran', 'System'],
        $actors,
        'Activity actor options should be distinct, trimmed, and sorted for the filter dropdown.'
    );
};

$tests['admin_controller_preserves_only_allowed_tracked_ui_query_args_in_redirect_urls'] = static function (): void {
    resetTestState();

    $_GET = [
        'page' => AdminController::MENU_SLUG,
        'tf_advanced' => '1',
        'tf_studio' => 'preview',
        'tf_preview' => 'card',
        'tf_output' => 'names',
        'tf_add_fonts' => '1',
        'tf_source' => 'google',
        'tf_google_access' => '1',
        'tf_adobe_project' => '1',
        'invalid' => 'kept-out',
    ];

    $controller = makeAdminControllerTestInstance();
    $url = invokePrivateMethod($controller, 'buildAdminPageUrl');
    $parts = parse_url($url);
    $query = [];

    parse_str((string) ($parts['query'] ?? ''), $query);

    assertSameValue(
        [
            'page' => AdminController::MENU_SLUG,
            'tf_advanced' => '1',
            'tf_studio' => 'preview',
            'tf_preview' => 'card',
            'tf_add_fonts' => '1',
            'tf_source' => 'google',
            'tf_google_access' => '1',
        ],
        $query,
        'Redirect URLs should preserve only the canonical tracked UI query args for the current admin view.'
    );
};

$tests['uninstall_cleans_library_and_runtime_transients'] = static function (): void {
    resetTestState();

    global $optionDeleted;
    global $optionStore;
    global $transientDeleted;
    global $transientStore;
    global $wpdbQueries;

    if (!defined('WP_UNINSTALL_PLUGIN')) {
        define('WP_UNINSTALL_PLUGIN', 'etch-fonts/plugin.php');
    }

    $optionStore = [
        'tasty_fonts_settings' => [
            'delete_uploaded_files_on_uninstall' => false,
            'adobe_project_id' => '',
        ],
        'tasty_fonts_google_api_key_data' => [
            'google_api_key' => 'live-key',
            'google_api_key_status' => 'valid',
            'google_api_key_status_message' => 'Ready',
            'google_api_key_checked_at' => 123,
        ],
        'tasty_fonts_library' => ['Inter' => ['delivery_profiles' => []]],
        'tasty_fonts_imports' => ['legacy' => true],
    ];
    $transientStore = [
        'tasty_fonts_bunny_catalog_v1' => ['Inter'],
    ];

    require dirname(__DIR__) . '/uninstall.php';

    assertSameValue(true, in_array('tasty_fonts_library', $optionDeleted, true), 'Uninstall should delete the live library option key.');
    assertSameValue(true, in_array('tasty_fonts_google_api_key_data', $optionDeleted, true), 'Uninstall should delete the dedicated Google API key option.');
    assertSameValue(true, in_array('tasty_fonts_imports', $optionDeleted, true), 'Uninstall should continue deleting the legacy imports option key.');
    assertSameValue(true, in_array('tasty_fonts_bunny_catalog_v1', $transientDeleted, true), 'Uninstall should delete the Bunny catalog transient.');
    assertSameValue(2, count($wpdbQueries), 'Uninstall should issue wildcard cleanup queries for Bunny family and admin notice transients.');
    assertContainsValue('DELETE FROM wp_options WHERE option_name LIKE', $wpdbQueries[0] ?? '', 'Uninstall should target the options table when cleaning Bunny family transients.');
    assertContainsValue('tasty\\_fonts\\_bunny\\_family\\_', $wpdbQueries[0] ?? '', 'Uninstall should wildcard-match Bunny family transients.');
    assertContainsValue('timeout', $wpdbQueries[0] ?? '', 'Uninstall should also remove Bunny family transient timeout rows.');
    assertContainsValue('DELETE FROM wp_options WHERE option_name LIKE', $wpdbQueries[1] ?? '', 'Uninstall should target the options table when cleaning admin notice transients.');
    assertContainsValue('tasty\\_fonts\\_admin\\_notices\\_', $wpdbQueries[1] ?? '', 'Uninstall should wildcard-match per-user admin notice transients.');
    assertContainsValue('timeout', $wpdbQueries[1] ?? '', 'Uninstall should also remove admin notice transient timeout rows.');
};

$failures = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        echo "[PASS] {$name}\n";
    } catch (Throwable $throwable) {
        $failures++;
        echo "[FAIL] {$name}\n";
        echo $throwable->getMessage() . "\n";
    }
}

exit($failures > 0 ? 1 : 0);
