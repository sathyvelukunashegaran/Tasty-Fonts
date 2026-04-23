<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Admin\AdminController;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\SiteEnvironment;
use TastyFonts\Support\Storage;
use WP_Error;

/**
 * @phpstan-import-type AxesMap from \TastyFonts\Support\FontUtils
 * @phpstan-import-type CatalogFamily from CatalogService
 * @phpstan-import-type CatalogFace from CatalogService
 * @phpstan-import-type DeliveryProfile from CatalogService
 * @phpstan-type ImportSyncResult array<string, mixed>
 * @phpstan-type RestEntity array<string, mixed>
 * @phpstan-type RestDecoded array<int|string, mixed>
 * @phpstan-type BlockEditorFamilySettings array{name: string, slug: string, fontFamily: string}
 * @phpstan-type BlockEditorFaceSettings array{fontFamily: string, src: list<string>, fontWeight: string, fontStyle: string, fontDisplay: string, unicodeRange?: string, fontVariationSettings?: string}
 * @phpstan-type RestRequestArgs array{method?: string, timeout?: float, headers?: array<string, string>, body?: array<string, string>}
 * @phpstan-type IntegrationsLogAction array<string, string>
 */
final class BlockEditorFontLibraryService
{
    private const REST_NAMESPACE = 'wp/v2';
    private const REST_FAMILIES_PATH = 'font-families';
    private const MANAGED_SLUG_PREFIX = 'tasty-fonts-';
    private const REQUEST_TIMEOUT = 20;
    private const THEME_JSON_VERSION = 3;
    private const SUPPORTED_DISPLAY_VALUES = ['auto', 'block', 'swap', 'fallback', 'optional'];
    private const SUPPORTED_FILE_FORMATS = ['woff2', 'woff', 'ttf', 'otf'];

    /**
     * Create the Block Editor Font Library sync service.
     *
     * @since 1.4.0
     *
     * @param Storage $storage Storage abstraction for converting relative font paths into public URLs.
     * @param ImportRepository $imports Repository used to look up saved family records when hook payloads are incomplete.
     * @param SettingsRepository $settings Settings repository used to mirror font-display overrides.
     * @param LogRepository $log Log repository used to record sync failures.
     */
    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly SettingsRepository $settings,
        private readonly LogRepository $log
    ) {
    }

    /**
     * Sync a plugin-managed import into the core Block Editor Font Library.
     *
     * @since 1.4.0
     *
     * @param ImportSyncResult $result Import result payload from the hosted import services.
     * @param string $provider Provider identifier passed by the import hook.
     * @return void
     */
    public function syncImportedFamily(array $result, string $provider): void
    {
        if (!$this->shouldSync($result, $provider)) {
            return;
        }

        $family = $this->resolveFamilyRecord($result);

        if ($family === null) {
            return;
        }

        $familyName = trim($this->stringValue($family, 'family', $this->stringValue($result, 'family')));

        if ($familyName === '') {
            return;
        }

        $profile = $this->resolveSyncProfile($family, $result);

        if ($profile === null) {
            return;
        }

        $familyPayload = $this->buildFamilySettings($family, $profile, $familyName);
        $facePayloads = $this->buildFaceSettingsList($profile, $familyName);

        if ($facePayloads === []) {
            return;
        }

        $existingFamily = $this->findExistingFontFamily($familyPayload['slug']);
        $familyId = $existingFamily !== null ? $this->intValue($existingFamily, 'id') : null;

        if ($familyId === null) {
            $createdFamily = $this->createFontFamily($familyPayload);

            if (is_wp_error($createdFamily)) {
                $this->logSyncFailure($familyName, $createdFamily);
                return;
            }

            $familyId = $this->intValue($createdFamily, 'id');
        } else {
            $updatedFamily = $this->updateFontFamily($familyId, $familyPayload);

            if (is_wp_error($updatedFamily)) {
                $this->logSyncFailure($familyName, $updatedFamily);
                return;
            }

            $deleted = $this->deleteExistingFontFaces($familyId, $this->normalizeFontFaceIdList($existingFamily['font_faces'] ?? []));

            if (is_wp_error($deleted)) {
                $this->logSyncFailure($familyName, $deleted);
                return;
            }
        }

        if ($familyId === null || $familyId <= 0) {
            return;
        }

        foreach ($facePayloads as $facePayload) {
            $createdFace = $this->createFontFace($familyId, $facePayload);

            if (is_wp_error($createdFace)) {
                $this->logSyncFailure($familyName, $createdFace);
                return;
            }
        }
    }

    /**
     * Remove a previously synced managed family from the core Block Editor Font Library.
     *
     * @since 1.4.0
     *
     * @param string $familySlug Plugin library slug for the deleted family.
     * @param string $familyName Optional family name used for log messages.
     * @return void
     */
    public function deleteSyncedFamily(string $familySlug, string $familyName = '', bool $force = false): void
    {
        $result = [
            'status' => 'deleted',
            'family' => $familyName,
            'family_slug' => $familySlug,
        ];

        if (!$this->shouldSync($result, 'delete', $force)) {
            return;
        }

        $managedSlug = self::MANAGED_SLUG_PREFIX . FontUtils::slugify($familySlug);
        $existingFamily = $this->findExistingFontFamily($managedSlug);
        $familyId = $existingFamily !== null ? $this->intValue($existingFamily, 'id') : null;

        if ($familyId === null || $familyId <= 0) {
            return;
        }

        $response = wp_remote_request(
            add_query_arg('force', 'true', trailingslashit($this->restBaseUrl()) . $familyId),
            $this->restRequestArgs(['method' => 'DELETE'])
        );
        $decoded = $this->decodeRestResponse($response);

        if (is_wp_error($decoded)) {
            $name = trim($familyName) !== '' ? $familyName : $familySlug;
            $this->logSyncFailure($name, $decoded);
        }
    }

    /**
     * Remove all plugin-managed families from the core Block Editor Font Library.
     *
     * @since 1.5.1
     *
     * @return void
     */
    public function deleteAllSyncedFamilies(bool $force = false): void
    {
        foreach ($this->imports->allFamilies() as $family) {
            $familySlug = $family['slug'];
            $familyName = $family['family'];

            if ($familySlug === '') {
                continue;
            }

            $this->deleteSyncedFamily($familySlug, $familyName, $force);
        }
    }

    /**
     * @param ImportSyncResult $result
     */
    private function shouldSync(array $result, string $provider, bool $force = false): bool
    {
        if (!$force && !$this->settings->isBlockEditorFontLibrarySyncEnabled()) {
            return false;
        }

        if (!apply_filters('tasty_fonts_sync_block_editor_font_library', true, $result, $provider)) {
            return false;
        }

        if (
            !function_exists('rest_url')
            || !function_exists('wp_remote_post')
            || !function_exists('wp_remote_request')
            || !function_exists('post_type_exists')
        ) {
            return false;
        }

        return post_type_exists('wp_font_family') && post_type_exists('wp_font_face');
    }

    /**
     * @param ImportSyncResult $result
     * @return CatalogFamily|null
     */
    private function resolveFamilyRecord(array $result): ?array
    {
        if (is_array($result['family_record'] ?? null)) {
            return $this->normalizeRestEntity($result['family_record']);
        }

        $familyName = trim($this->stringValue($result, 'family'));

        if ($familyName === '') {
            return null;
        }

        return $this->imports->getFamily(FontUtils::slugify($familyName));
    }

    /**
     * @param CatalogFamily $family
     * @param ImportSyncResult $result
     * @return DeliveryProfile|null
     */
    private function resolveSyncProfile(array $family, array $result): ?array
    {
        $profiles = $this->profileMap($family['delivery_profiles'] ?? []);
        $deliveryId = $this->stringValue($family, 'active_delivery_id');

        if ($deliveryId !== '' && isset($profiles[$deliveryId])) {
            return $profiles[$deliveryId];
        }

        $resultDeliveryId = $this->stringValue($result, 'delivery_id');

        if ($resultDeliveryId !== '' && isset($profiles[$resultDeliveryId])) {
            return $profiles[$resultDeliveryId];
        }

        $firstProfile = reset($profiles);

        return $firstProfile === false ? null : $firstProfile;
    }

    /**
     * @param CatalogFamily $family
     * @param DeliveryProfile $profile
     * @return BlockEditorFamilySettings
     */
    private function buildFamilySettings(array $family, array $profile, string $familyName): array
    {
        $familySlug = FontUtils::slugify($this->stringValue($family, 'slug', $familyName));

        return [
            'name' => $familyName,
            'slug' => self::MANAGED_SLUG_PREFIX . $familySlug,
            'fontFamily' => FontUtils::buildFontStack($familyName, $this->resolveFallback($profile)),
        ];
    }

    /**
     * @param DeliveryProfile $profile
     * @return list<BlockEditorFaceSettings>
     */
    private function buildFaceSettingsList(array $profile, string $familyName): array
    {
        $payloads = [];
        $settings = $this->settings->getSettings();
        $fontDisplay = $this->resolveFontDisplay($familyName);
        $quotedFamily = '"' . FontUtils::escapeFontFamily($familyName) . '"';
        $variableFontsEnabled = !empty($settings['variable_fonts_enabled']);

        foreach (FontUtils::normalizeFaceList($profile['faces'] ?? []) as $face) {
            if (!$variableFontsEnabled && FontUtils::faceIsVariable($face)) {
                continue;
            }

            $src = $this->buildFaceSources($face);

            if ($src === []) {
                continue;
            }

            $axes = $this->normalizeAxes($face['axes'] ?? []);
            $payload = [
                'fontFamily' => $quotedFamily,
                'src' => $src,
                'fontWeight' => $this->blockEditorFontWeight($this->stringValue($face, 'weight', '400'), $axes),
                'fontStyle' => FontUtils::normalizeStyle($this->stringValue($face, 'style', 'normal')),
                'fontDisplay' => $fontDisplay,
            ];

            $unicodeRange = FontUtils::resolveFaceUnicodeRange($face, $settings);

            if ($unicodeRange !== '') {
                $payload['unicodeRange'] = $unicodeRange;
            }

            if ($variableFontsEnabled) {
                $variationSettings = FontUtils::buildFontVariationSettings(
                    FontUtils::faceLevelVariationDefaults($face['variation_defaults'] ?? [], $axes)
                );

                if ($variationSettings !== 'normal') {
                    $payload['fontVariationSettings'] = $variationSettings;
                }
            }

            $payloads[] = $payload;
        }

        return $payloads;
    }

    /**
     * @param AxesMap $axes
     */
    private function blockEditorFontWeight(string $weight, array $axes = []): string
    {
        return FontUtils::blockEditorFontWeightValue($weight, $axes);
    }

    /**
     * @param CatalogFace $face
     * @return list<string>
     */
    private function buildFaceSources(array $face): array
    {
        $sources = [];
        $files = FontUtils::normalizeStringMap($face['files'] ?? []);
        $paths = FontUtils::normalizeStringMap($face['paths'] ?? []);

        foreach (self::SUPPORTED_FILE_FORMATS as $format) {
            $value = $files[$format] ?? $paths[$format] ?? null;

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $sources[] = $this->normalizeFaceSourceUrl($value);
        }

        return array_values(array_filter($sources, static fn (string $source): bool => $source !== ''));
    }

    private function normalizeFaceSourceUrl(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        $rootUrl = $this->storage->getRootUrlFull();

        if ($rootUrl === null || $rootUrl === '') {
            return '';
        }

        $segments = explode('/', trim($value, '/'));
        $encodedSegments = array_map('rawurlencode', array_filter($segments, static fn (string $segment): bool => $segment !== ''));

        return untrailingslashit($rootUrl) . '/' . implode('/', $encodedSegments);
    }

    /**
     * @param DeliveryProfile $profile
     */
    private function resolveFallback(array $profile): string
    {
        $category = strtolower(trim($this->profileMetaString($profile, 'category')));

        return match ($category) {
            'serif' => 'serif',
            'monospace' => 'monospace',
            'handwriting' => 'cursive',
            default => 'sans-serif',
        };
    }

    private function resolveFontDisplay(string $familyName): string
    {
        $settings = $this->settings->getSettings();
        $familyDisplays = FontUtils::normalizeStringMap($settings['family_font_displays'] ?? []);
        $display = strtolower(trim($familyDisplays[$familyName] ?? $this->stringValue($settings, 'font_display', 'swap')));

        return in_array($display, self::SUPPORTED_DISPLAY_VALUES, true)
            ? $display
            : 'swap';
    }

    /**
     * @return RestEntity|null
     */
    private function findExistingFontFamily(string $managedSlug): ?array
    {
        if ($managedSlug === '') {
            return null;
        }

        $url = add_query_arg(
            [
                'slug' => $managedSlug,
                'context' => 'edit',
            ],
            $this->restBaseUrl()
        );
        $response = wp_remote_get($url, $this->restRequestArgs());
        $decoded = $this->decodeRestResponse($response);

        if (is_wp_error($decoded)) {
            return null;
        }

        if (!isset($decoded[0]) || !is_array($decoded[0])) {
            return null;
        }

        return $this->normalizeRestEntity($decoded[0]);
    }

    /**
     * @param BlockEditorFamilySettings $settings
     * @return RestEntity|WP_Error
     */
    private function createFontFamily(array $settings): array|WP_Error
    {
        $response = wp_remote_post(
            $this->restBaseUrl(),
            $this->restRequestArgs(
                [
                    'body' => [
                        'font_family_settings' => $this->jsonBodyString($settings),
                        'theme_json_version' => (string) self::THEME_JSON_VERSION,
                    ],
                ]
            )
        );

        return $this->decodeRestEntity($response);
    }

    /**
     * @param BlockEditorFamilySettings $settings
     * @return RestEntity|WP_Error
     */
    private function updateFontFamily(int $familyId, array $settings): array|WP_Error
    {
        $response = wp_remote_post(
            trailingslashit($this->restBaseUrl()) . $familyId,
            $this->restRequestArgs(
                [
                    'body' => [
                        'font_family_settings' => $this->jsonBodyString($settings),
                        'theme_json_version' => (string) self::THEME_JSON_VERSION,
                    ],
                ]
            )
        );

        return $this->decodeRestEntity($response);
    }

    /**
     * @param list<int|string> $fontFaceIds
     */
    private function deleteExistingFontFaces(int $familyId, array $fontFaceIds): bool|WP_Error
    {
        foreach ($fontFaceIds as $fontFaceId) {
            $fontFaceId = (int) $fontFaceId;

            if ($fontFaceId <= 0) {
                continue;
            }

            $response = wp_remote_request(
                add_query_arg(
                    'force',
                    'true',
                    trailingslashit($this->restBaseUrl()) . $familyId . '/font-faces/' . $fontFaceId
                ),
                $this->restRequestArgs(['method' => 'DELETE'])
            );
            $decoded = $this->decodeRestResponse($response);

            if (is_wp_error($decoded)) {
                return $decoded;
            }
        }

        return true;
    }

    /**
     * @param BlockEditorFaceSettings $settings
     * @return RestEntity|WP_Error
     */
    private function createFontFace(int $familyId, array $settings): array|WP_Error
    {
        $response = wp_remote_post(
            trailingslashit($this->restBaseUrl()) . $familyId . '/font-faces',
            $this->restRequestArgs(
                [
                    'body' => [
                        'font_face_settings' => $this->jsonBodyString($settings),
                        'theme_json_version' => (string) self::THEME_JSON_VERSION,
                    ],
                ]
            )
        );

        return $this->decodeRestEntity($response);
    }

    /**
     * @return RestEntity|WP_Error
     */
    private function decodeRestEntity(mixed $response): array|WP_Error
    {
        $decoded = $this->decodeRestResponse($response);

        if (is_wp_error($decoded)) {
            return $decoded;
        }

        return $this->normalizeRestEntity($decoded);
    }

    /**
     * @return RestDecoded|WP_Error
     */
    private function decodeRestResponse(mixed $response): array|WP_Error
    {
        if (is_wp_error($response)) {
            return $response;
        }

        if (!is_array($response)) {
            return new WP_Error(
                'tasty_fonts_block_editor_font_library_sync_failed',
                __('Block Editor Font Library request did not return a valid REST response.', 'tasty-fonts')
            );
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($status < 200 || $status >= 300) {
            $message = is_array($body) ? trim($this->stringValue($body, 'message')) : '';

            return new WP_Error(
                'tasty_fonts_block_editor_font_library_sync_failed',
                $message !== ''
                    ? $message
                    : sprintf(
                        __('Block Editor Font Library request failed with status %d.', 'tasty-fonts'),
                        $status
                    )
            );
        }

        return is_array($body) ? $body : [];
    }

    /**
     * @param mixed $entity
     * @return RestEntity
     */
    private function normalizeRestEntity(mixed $entity): array
    {
        if (!is_array($entity)) {
            return [];
        }

        $normalized = [];

        foreach ($entity as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    /**
     * @param mixed $fontFaceIds
     * @return list<int|string>
     */
    private function normalizeFontFaceIdList(mixed $fontFaceIds): array
    {
        if (!is_array($fontFaceIds)) {
            return [];
        }

        $normalized = [];

        foreach ($fontFaceIds as $fontFaceId) {
            if (!is_scalar($fontFaceId)) {
                continue;
            }

            $normalized[] = is_int($fontFaceId) ? $fontFaceId : (string) $fontFaceId;
        }

        return $normalized;
    }

    /**
     * @param RestRequestArgs $args
     * @return RestRequestArgs
     */
    private function restRequestArgs(array $args = []): array
    {
        $headers = [
            'Accept' => 'application/json',
            'X-WP-Nonce' => wp_create_nonce('wp_rest'),
        ];
        $cookieHeader = $this->buildAuthCookieHeader();

        if ($cookieHeader !== '') {
            $headers['Cookie'] = $cookieHeader;
        }

        $defaults = [
            'timeout' => (float) self::REQUEST_TIMEOUT,
            'headers' => $headers,
        ];

        $normalized = FontUtils::normalizeStringKeyedMap(array_replace_recursive($defaults, $args));
        $requestArgs = [];

        $method = FontUtils::scalarStringValue($normalized['method'] ?? '');

        if ($method !== '') {
            $requestArgs['method'] = $method;
        }

        $timeout = FontUtils::scalarFloatValue($normalized['timeout'] ?? self::REQUEST_TIMEOUT, (float) self::REQUEST_TIMEOUT);

        if ($timeout > 0) {
            $requestArgs['timeout'] = $timeout;
        }

        $headers = FontUtils::normalizeStringMap($normalized['headers'] ?? []);

        if ($headers !== []) {
            $requestArgs['headers'] = $headers;
        }

        $body = FontUtils::normalizeStringMap($normalized['body'] ?? []);

        if ($body !== []) {
            $requestArgs['body'] = $body;
        }

        return $requestArgs;
    }

    private function buildAuthCookieHeader(): string
    {
        $cookies = [];

        foreach (['AUTH_COOKIE', 'SECURE_AUTH_COOKIE', 'LOGGED_IN_COOKIE'] as $cookieConstant) {
            if (!defined($cookieConstant)) {
                continue;
            }

            $name = FontUtils::scalarStringValue(constant($cookieConstant));

            if ($name === '' || !isset($_COOKIE[$name])) {
                continue;
            }

            $cookieValue = FontUtils::scalarStringValue($_COOKIE[$name]);

            if ($cookieValue === '') {
                continue;
            }

            $cookies[] = rawurlencode($name) . '=' . rawurlencode($cookieValue);
        }

        return implode('; ', $cookies);
    }

    private function restBaseUrl(): string
    {
        return rest_url(self::REST_NAMESPACE . '/' . self::REST_FAMILIES_PATH);
    }

    private function logSyncFailure(string $familyName, WP_Error $error): void
    {
        $message = trim($error->get_error_message());

        if ($message === '') {
            return;
        }

        if (SiteEnvironment::isLoopbackTlsTrustError($message)) {
            $this->log->add(
                sprintf(
                    __('Block Editor Font Library sync failed for %1$s because PHP could not verify this site\'s HTTPS certificate during the editor sync request. Open Integrations to turn this feature off for local development, or turn it back on after PHP/cURL trusts the site certificate.', 'tasty-fonts'),
                    $familyName
                ),
                $this->buildIntegrationsLogAction()
            );

            return;
        }

        $context = SiteEnvironment::isLikelyLocalEnvironment($this->restBaseUrl(), SiteEnvironment::currentEnvironmentType())
            ? $this->buildIntegrationsLogAction()
            : [];

        $this->log->add(
            sprintf(
                __('Block Editor Font Library sync failed for %1$s: %2$s', 'tasty-fonts'),
                $familyName,
                $message
            ),
            $context
        );
    }

    /**
     * @return IntegrationsLogAction
     */
    private function buildIntegrationsLogAction(): array
    {
        if (!function_exists('admin_url')) {
            return [];
        }

        return [
            'action_label' => __('Open Integrations', 'tasty-fonts'),
            'action_url' => add_query_arg(
                [
                    'page' => AdminController::MENU_SLUG,
                    'tf_page' => AdminController::PAGE_SETTINGS,
                    'tf_studio' => 'integrations',
                ],
                admin_url('admin.php')
            ),
        ];
    }

    /**
     * @param mixed $profiles
     * @return array<string, DeliveryProfile>
     */
    private function profileMap(mixed $profiles): array
    {
        if (!is_array($profiles)) {
            return [];
        }

        $normalized = [];

        foreach ($profiles as $profileId => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $id = $this->stringValue($profile, 'id', is_string($profileId) ? $profileId : '');

            if ($id === '') {
                continue;
            }

            $normalized[$id] = FontUtils::normalizeStringKeyedMap($profile);
        }

        return $normalized;
    }

    /**
     * @return AxesMap
     */
    private function normalizeAxes(mixed $axes): array
    {
        return is_array($axes) ? FontUtils::normalizeAxesMap($axes) : [];
    }

    /**
     * @param DeliveryProfile $profile
     */
    private function profileMetaString(array $profile, string $key): string
    {
        $meta = $profile['meta'] ?? null;

        if (!is_array($meta)) {
            return '';
        }

        return $this->stringValue($meta, $key);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function jsonBodyString(array $settings): string
    {
        $encoded = wp_json_encode($settings);

        return is_string($encoded) ? $encoded : '{}';
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
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
    private function intValue(array $values, string $key): ?int
    {
        if (!array_key_exists($key, $values)) {
            return null;
        }

        $value = $values[$key];

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '' && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return null;
    }
}
