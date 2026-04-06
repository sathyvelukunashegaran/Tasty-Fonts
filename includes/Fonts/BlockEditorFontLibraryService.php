<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

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
     * @param array<string, mixed> $result Import result payload from the hosted import services.
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

        $familyName = trim((string) ($family['family'] ?? ($result['family'] ?? '')));

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

        $existingFamily = $this->findExistingFontFamily((string) ($familyPayload['slug'] ?? ''));
        $familyId = $existingFamily['id'] ?? null;

        if ($familyId === null) {
            $createdFamily = $this->createFontFamily($familyPayload);

            if (is_wp_error($createdFamily)) {
                $this->logSyncFailure($familyName, $createdFamily);
                return;
            }

            $familyId = isset($createdFamily['id']) ? (int) $createdFamily['id'] : null;
        } else {
            $updatedFamily = $this->updateFontFamily($familyId, $familyPayload);

            if (is_wp_error($updatedFamily)) {
                $this->logSyncFailure($familyName, $updatedFamily);
                return;
            }

            $deleted = $this->deleteExistingFontFaces($familyId, (array) ($existingFamily['font_faces'] ?? []));

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
    public function deleteSyncedFamily(string $familySlug, string $familyName = ''): void
    {
        $result = [
            'status' => 'deleted',
            'family' => $familyName,
            'family_slug' => $familySlug,
        ];

        if (!$this->shouldSync($result, 'delete')) {
            return;
        }

        $managedSlug = self::MANAGED_SLUG_PREFIX . FontUtils::slugify($familySlug);
        $existingFamily = $this->findExistingFontFamily($managedSlug);

        if (!is_array($existingFamily) || empty($existingFamily['id'])) {
            return;
        }

        $response = wp_remote_request(
            add_query_arg('force', 'true', trailingslashit($this->restBaseUrl()) . (int) $existingFamily['id']),
            $this->restRequestArgs(['method' => 'DELETE'])
        );
        $decoded = $this->decodeRestResponse($response);

        if (is_wp_error($decoded)) {
            $name = trim($familyName) !== '' ? $familyName : $familySlug;
            $this->logSyncFailure($name, $decoded);
        }
    }

    private function shouldSync(array $result, string $provider): bool
    {
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

    private function resolveFamilyRecord(array $result): ?array
    {
        if (is_array($result['family_record'] ?? null)) {
            return $result['family_record'];
        }

        $familyName = trim((string) ($result['family'] ?? ''));

        if ($familyName === '') {
            return null;
        }

        return $this->imports->getFamily(FontUtils::slugify($familyName));
    }

    private function resolveSyncProfile(array $family, array $result): ?array
    {
        $profiles = is_array($family['delivery_profiles'] ?? null) ? $family['delivery_profiles'] : [];
        $deliveryId = (string) ($family['active_delivery_id'] ?? '');

        if ($deliveryId !== '' && is_array($profiles[$deliveryId] ?? null)) {
            return $profiles[$deliveryId];
        }

        $resultDeliveryId = (string) ($result['delivery_id'] ?? '');

        if ($resultDeliveryId !== '' && is_array($profiles[$resultDeliveryId] ?? null)) {
            return $profiles[$resultDeliveryId];
        }

        $firstProfile = reset($profiles);

        return is_array($firstProfile) ? $firstProfile : null;
    }

    private function buildFamilySettings(array $family, array $profile, string $familyName): array
    {
        $familySlug = FontUtils::slugify((string) ($family['slug'] ?? $familyName));

        return [
            'name' => $familyName,
            'slug' => self::MANAGED_SLUG_PREFIX . $familySlug,
            'fontFamily' => FontUtils::buildFontStack($familyName, $this->resolveFallback($profile)),
        ];
    }

    private function buildFaceSettingsList(array $profile, string $familyName): array
    {
        $payloads = [];
        $fontDisplay = $this->resolveFontDisplay($familyName);
        $quotedFamily = '"' . FontUtils::escapeFontFamily($familyName) . '"';

        foreach ((array) ($profile['faces'] ?? []) as $face) {
            if (!is_array($face)) {
                continue;
            }

            $src = $this->buildFaceSources($face);

            if ($src === []) {
                continue;
            }

            $payload = [
                'fontFamily' => $quotedFamily,
                'src' => $src,
                'fontWeight' => FontUtils::normalizeWeight((string) ($face['weight'] ?? '400')),
                'fontStyle' => FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal')),
                'fontDisplay' => $fontDisplay,
            ];

            $unicodeRange = trim((string) ($face['unicode_range'] ?? ''));

            if ($unicodeRange !== '') {
                $payload['unicodeRange'] = $unicodeRange;
            }

            $payloads[] = $payload;
        }

        return $payloads;
    }

    private function buildFaceSources(array $face): array
    {
        $sources = [];
        $files = is_array($face['files'] ?? null) ? $face['files'] : [];
        $paths = is_array($face['paths'] ?? null) ? $face['paths'] : [];

        foreach (self::SUPPORTED_FILE_FORMATS as $format) {
            $value = $files[$format] ?? $paths[$format] ?? null;

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $sources[] = $this->normalizeFaceSourceUrl($value);
        }

        return array_values(array_filter($sources, 'strlen'));
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
        $encodedSegments = array_map('rawurlencode', array_filter($segments, 'strlen'));

        return untrailingslashit($rootUrl) . '/' . implode('/', $encodedSegments);
    }

    private function resolveFallback(array $profile): string
    {
        $category = strtolower(trim((string) (($profile['meta']['category'] ?? ''))));

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
        $familyDisplays = is_array($settings['family_font_displays'] ?? null) ? $settings['family_font_displays'] : [];
        $display = strtolower(trim((string) ($familyDisplays[$familyName] ?? ($settings['font_display'] ?? 'optional'))));

        return in_array($display, self::SUPPORTED_DISPLAY_VALUES, true)
            ? $display
            : 'optional';
    }

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

        if (!is_array($decoded) || !isset($decoded[0]) || !is_array($decoded[0])) {
            return null;
        }

        return $decoded[0];
    }

    private function createFontFamily(array $settings): array|WP_Error
    {
        $response = wp_remote_post(
            $this->restBaseUrl(),
            $this->restRequestArgs(
                [
                    'body' => [
                        'font_family_settings' => wp_json_encode($settings),
                        'theme_json_version' => (string) self::THEME_JSON_VERSION,
                    ],
                ]
            )
        );

        return $this->decodeRestResponse($response);
    }

    private function updateFontFamily(int $familyId, array $settings): array|WP_Error
    {
        $response = wp_remote_post(
            trailingslashit($this->restBaseUrl()) . $familyId,
            $this->restRequestArgs(
                [
                    'body' => [
                        'font_family_settings' => wp_json_encode($settings),
                        'theme_json_version' => (string) self::THEME_JSON_VERSION,
                    ],
                ]
            )
        );

        return $this->decodeRestResponse($response);
    }

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

    private function createFontFace(int $familyId, array $settings): array|WP_Error
    {
        $response = wp_remote_post(
            trailingslashit($this->restBaseUrl()) . $familyId . '/font-faces',
            $this->restRequestArgs(
                [
                    'body' => [
                        'font_face_settings' => wp_json_encode($settings),
                        'theme_json_version' => (string) self::THEME_JSON_VERSION,
                    ],
                ]
            )
        );

        return $this->decodeRestResponse($response);
    }

    private function decodeRestResponse(mixed $response): array|WP_Error
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($status < 200 || $status >= 300) {
            $message = is_array($body) ? trim((string) ($body['message'] ?? '')) : '';

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
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => $headers,
        ];

        return array_replace_recursive($defaults, $args);
    }

    private function buildAuthCookieHeader(): string
    {
        $cookies = [];

        foreach (['AUTH_COOKIE', 'SECURE_AUTH_COOKIE', 'LOGGED_IN_COOKIE'] as $cookieConstant) {
            if (!defined($cookieConstant)) {
                continue;
            }

            $name = (string) constant($cookieConstant);

            if (!isset($_COOKIE[$name])) {
                continue;
            }

            $cookies[] = rawurlencode($name) . '=' . rawurlencode((string) $_COOKIE[$name]);
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

        $this->log->add(
            sprintf(
                __('Block Editor Font Library sync failed for %1$s: %2$s', 'tasty-fonts'),
                $familyName,
                $message
            )
        );
    }
}
