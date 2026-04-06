<?php

declare(strict_types=1);

namespace TastyFonts\Api;

defined('ABSPATH') || exit;

use TastyFonts\Admin\AdminController;
use WP_Error;
use WP_REST_Request;

final class RestController
{
    public const API_NAMESPACE = 'tasty-fonts/v1';

    private const ROUTES = [
        'searchGoogle' => 'google/search',
        'searchBunny' => 'bunny/search',
        'googleFamily' => 'google/family',
        'bunnyFamily' => 'bunny/family',
        'importGoogle' => 'google/import',
        'importBunny' => 'bunny/import',
        'uploadLocal' => 'local/upload',
        'saveFamilyFallback' => 'families/fallback',
        'saveFamilyFontDisplay' => 'families/font-display',
        'saveRoleDraft' => 'roles/draft',
        'saveFamilyDelivery' => 'families/delivery',
        'saveFamilyPublishState' => 'families/publish-state',
        'deleteDeliveryProfile' => 'families/delivery-profile',
    ];

    public function __construct(private readonly AdminController $admin)
    {
    }

    public static function routeMap(): array
    {
        return self::ROUTES;
    }

    public static function routeReference(string $key): string
    {
        $path = self::ROUTES[$key] ?? '';

        return '/' . self::API_NAMESPACE . '/' . ltrim($path, '/');
    }

    public function registerRoutes(): void
    {
        $this->registerRoute(self::ROUTES['searchGoogle'], 'GET', [$this, 'searchGoogle']);
        $this->registerRoute(self::ROUTES['searchBunny'], 'GET', [$this, 'searchBunny']);
        $this->registerRoute(self::ROUTES['googleFamily'], 'GET', [$this, 'fetchGoogleFamily']);
        $this->registerRoute(self::ROUTES['bunnyFamily'], 'GET', [$this, 'fetchBunnyFamily']);
        $this->registerRoute(self::ROUTES['importGoogle'], 'POST', [$this, 'importGoogleFamily']);
        $this->registerRoute(self::ROUTES['importBunny'], 'POST', [$this, 'importBunnyFamily']);
        $this->registerRoute(self::ROUTES['uploadLocal'], 'POST', [$this, 'uploadLocalFonts']);
        $this->registerRoute(self::ROUTES['saveFamilyFallback'], 'PATCH', [$this, 'saveFamilyFallback']);
        $this->registerRoute(self::ROUTES['saveFamilyFontDisplay'], 'PATCH', [$this, 'saveFamilyFontDisplay']);
        $this->registerRoute(self::ROUTES['saveRoleDraft'], 'PATCH', [$this, 'saveRoleDraft']);
        $this->registerRoute(self::ROUTES['saveFamilyDelivery'], 'PATCH', [$this, 'saveFamilyDelivery']);
        $this->registerRoute(self::ROUTES['saveFamilyPublishState'], 'PATCH', [$this, 'saveFamilyPublishState']);
        $this->registerRoute(self::ROUTES['deleteDeliveryProfile'], 'DELETE', [$this, 'deleteDeliveryProfile']);
    }

    public function canManageOptions(): bool
    {
        return current_user_can('manage_options');
    }

    public function searchGoogle(WP_REST_Request $request): mixed
    {
        return $this->restResult($this->admin->searchGoogle($this->getTextParam($request, 'query')));
    }

    public function searchBunny(WP_REST_Request $request): mixed
    {
        return $this->restResult($this->admin->searchBunny($this->getTextParam($request, 'query')));
    }

    public function fetchGoogleFamily(WP_REST_Request $request): mixed
    {
        return $this->restResult($this->admin->fetchGoogleFamily($this->getTextParam($request, 'family')));
    }

    public function fetchBunnyFamily(WP_REST_Request $request): mixed
    {
        return $this->restResult($this->admin->fetchBunnyFamily($this->getTextParam($request, 'family')));
    }

    public function importGoogleFamily(WP_REST_Request $request): mixed
    {
        return $this->restResult(
            $this->admin->importGoogleFamily(
                $this->getTextParam($request, 'family'),
                $this->getVariantTokens($request),
                $this->getTextParam($request, 'delivery_mode', 'self_hosted')
            )
        );
    }

    public function importBunnyFamily(WP_REST_Request $request): mixed
    {
        return $this->restResult(
            $this->admin->importBunnyFamily(
                $this->getTextParam($request, 'family'),
                $this->getVariantTokens($request),
                $this->getTextParam($request, 'delivery_mode', 'self_hosted')
            )
        );
    }

    public function uploadLocalFonts(WP_REST_Request $request): mixed
    {
        $postedRows = $request->get_param('rows');
        $rawFiles = $request->get_file_params();

        return $this->restResult(
            $this->admin->uploadLocalFontRows(
                $this->admin->prepareUploadRows(
                    is_array($postedRows) ? $postedRows : [],
                    is_array($rawFiles['files'] ?? null) ? $rawFiles['files'] : []
                )
            )
        );
    }

    public function saveFamilyFallback(WP_REST_Request $request): mixed
    {
        return $this->restResult(
            $this->admin->saveFamilyFallbackValue(
                $this->getTextParam($request, 'family'),
                $this->getTextParam($request, 'fallback', 'sans-serif')
            )
        );
    }

    public function saveFamilyFontDisplay(WP_REST_Request $request): mixed
    {
        return $this->restResult(
            $this->admin->saveFamilyFontDisplayValue(
                $this->getTextParam($request, 'family'),
                $this->getTextParam($request, 'font_display', 'inherit')
            )
        );
    }

    public function saveRoleDraft(WP_REST_Request $request): mixed
    {
        return $this->restResult(
            $this->admin->saveRoleDraftValues(
                [
                    'heading' => $this->getTextParam($request, 'heading'),
                    'body' => $this->getTextParam($request, 'body'),
                    'heading_fallback' => $this->getTextParam($request, 'heading_fallback', 'sans-serif'),
                    'body_fallback' => $this->getTextParam($request, 'body_fallback', 'sans-serif'),
                ]
            )
        );
    }

    public function saveFamilyDelivery(WP_REST_Request $request): mixed
    {
        return $this->restResult(
            $this->admin->saveFamilyDeliveryValue(
                $this->getTextParam($request, 'family_slug'),
                $this->getTextParam($request, 'delivery_id')
            )
        );
    }

    public function saveFamilyPublishState(WP_REST_Request $request): mixed
    {
        return $this->restResult(
            $this->admin->saveFamilyPublishStateValue(
                $this->getTextParam($request, 'family_slug'),
                $this->getTextParam($request, 'publish_state')
            )
        );
    }

    public function deleteDeliveryProfile(WP_REST_Request $request): mixed
    {
        return $this->restResult(
            $this->admin->deleteDeliveryProfileValue(
                $this->getTextParam($request, 'family_slug'),
                $this->getTextParam($request, 'delivery_id')
            )
        );
    }

    private function registerRoute(string $path, string $methods, callable $callback): void
    {
        register_rest_route(
            self::API_NAMESPACE,
            '/' . ltrim($path, '/'),
            [
                'methods' => $methods,
                'callback' => $callback,
                'permission_callback' => [$this, 'canManageOptions'],
            ]
        );
    }

    private function restResult(array|WP_Error $result, int $defaultErrorStatus = 400): mixed
    {
        if (!is_wp_error($result)) {
            return rest_ensure_response($result);
        }

        return new WP_Error(
            $result->get_error_code(),
            $result->get_error_message(),
            ['status' => $this->admin->statusForError($result, $defaultErrorStatus)]
        );
    }

    private function getTextParam(WP_REST_Request $request, string $key, string $default = ''): string
    {
        $value = $request->get_param($key);

        if (!is_scalar($value) && $value !== null) {
            return $default;
        }

        return $value === null ? $default : (string) $value;
    }

    private function getVariantTokens(WP_REST_Request $request): array
    {
        $variants = $request->get_param('variants');

        if (is_array($variants) && $variants !== []) {
            return $variants;
        }

        $variantTokens = $this->getTextParam($request, 'variant_tokens');

        if ($variantTokens === '') {
            return [];
        }

        return array_map('trim', explode(',', $variantTokens));
    }
}
