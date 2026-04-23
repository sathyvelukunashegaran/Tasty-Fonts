<?php

declare(strict_types=1);

namespace TastyFonts\Api;

defined('ABSPATH') || exit;

use TastyFonts\Admin\AdminAccessService;
use TastyFonts\Admin\SettingsSaveFields;
use TastyFonts\Admin\AdminController;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use WP_Error;
use WP_REST_Request;

/**
 * @phpstan-type RouteArgs array<string, array<string, mixed>>
 * @phpstan-type RestResultPayload array<string, mixed>
 * @phpstan-type TextArg array<string, mixed>
 * @phpstan-type NestedArg array<string, mixed>
 */
final class RestController
{
    public const API_NAMESPACE = 'tasty-fonts/v1';

    private const ROUTES = [
        'saveSettings' => 'settings',
        'searchGoogle' => 'google/search',
        'searchBunny' => 'bunny/search',
        'googleFamily' => 'google/family',
        'bunnyFamily' => 'bunny/family',
        'importGoogle' => 'google/import',
        'importBunny' => 'bunny/import',
        'validateSiteTransfer' => 'transfer/validate',
        'uploadLocal' => 'local/upload',
        'saveFamilyFallback' => 'families/fallback',
        'saveFamilyFontDisplay' => 'families/font-display',
        'saveRoleDraft' => 'roles/draft',
        'saveFamilyDelivery' => 'families/delivery',
        'saveFamilyPublishState' => 'families/publish-state',
        'deleteDeliveryProfile' => 'families/delivery-profile',
        'familyCard' => 'families/card',
    ];

    private readonly AdminAccessService $adminAccess;

    public function __construct(private readonly AdminController $admin, ?AdminAccessService $adminAccess = null)
    {
        $this->adminAccess = $adminAccess ?? new AdminAccessService(new SettingsRepository());
    }

    /**
     * @return array<string, string>
     */
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
        $this->registerRoute(self::ROUTES['saveSettings'], 'PATCH', [$this, 'saveSettings'], $this->settingsArgs());
        $this->registerRoute(self::ROUTES['searchGoogle'], 'GET', [$this, 'searchGoogle'], [
            'query' => $this->buildTextArg(true),
        ]);
        $this->registerRoute(self::ROUTES['searchBunny'], 'GET', [$this, 'searchBunny'], [
            'query' => $this->buildTextArg(true),
        ]);
        $this->registerRoute(self::ROUTES['googleFamily'], 'GET', [$this, 'fetchGoogleFamily'], [
            'family' => $this->buildTextArg(true),
        ]);
        $this->registerRoute(self::ROUTES['bunnyFamily'], 'GET', [$this, 'fetchBunnyFamily'], [
            'family' => $this->buildTextArg(true),
        ]);
        $this->registerRoute(self::ROUTES['importGoogle'], 'POST', [$this, 'importGoogleFamily'], $this->hostedImportArgs());
        $this->registerRoute(self::ROUTES['importBunny'], 'POST', [$this, 'importBunnyFamily'], $this->hostedImportArgs());
        $this->registerRoute(self::ROUTES['validateSiteTransfer'], 'POST', [$this, 'validateSiteTransferBundle']);
        $this->registerRoute(self::ROUTES['uploadLocal'], 'POST', [$this, 'uploadLocalFonts'], [
            'rows' => $this->buildNestedArrayArg(true),
        ]);
        $this->registerRoute(self::ROUTES['saveFamilyFallback'], 'PATCH', [$this, 'saveFamilyFallback'], [
            'family' => $this->buildTextArg(true),
            'fallback' => $this->buildTextArg(),
        ]);
        $this->registerRoute(self::ROUTES['saveFamilyFontDisplay'], 'PATCH', [$this, 'saveFamilyFontDisplay'], [
            'family' => $this->buildTextArg(true),
            'font_display' => $this->buildTextArg(false, ['inherit', 'auto', 'block', 'swap', 'fallback', 'optional']),
        ]);
        $this->registerRoute(self::ROUTES['saveRoleDraft'], 'PATCH', [$this, 'saveRoleDraft'], $this->roleDraftArgs());
        $this->registerRoute(self::ROUTES['saveFamilyDelivery'], 'PATCH', [$this, 'saveFamilyDelivery'], [
            'family_slug' => $this->buildTextArg(true),
            'delivery_id' => $this->buildTextArg(true),
        ]);
        $this->registerRoute(self::ROUTES['saveFamilyPublishState'], 'PATCH', [$this, 'saveFamilyPublishState'], [
            'family_slug' => $this->buildTextArg(true),
            'publish_state' => $this->buildTextArg(true, ['library_only', 'published', 'role_active']),
        ]);
        $this->registerRoute(self::ROUTES['deleteDeliveryProfile'], 'DELETE', [$this, 'deleteDeliveryProfile'], [
            'family_slug' => $this->buildTextArg(true),
            'delivery_id' => $this->buildTextArg(true),
        ]);
        $this->registerRoute(self::ROUTES['familyCard'], 'GET', [$this, 'renderFamilyCard'], [
            'family_slug' => $this->buildTextArg(true),
        ]);
    }

    public function canManageOptions(): bool
    {
        return $this->adminAccess->canCurrentUserAccess();
    }

    public function searchGoogle(WP_REST_Request $request): mixed
    {
        return $this->restResult($this->admin->searchGoogle($this->getTextParam($request, 'query')));
    }

    public function saveSettings(WP_REST_Request $request): mixed
    {
        return $this->restResult(
            $this->admin->saveSettingsValues($request->get_params())
        );
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
                $this->getTextParam($request, 'delivery_mode', 'self_hosted'),
                $this->getTextParam($request, 'format_mode', 'static')
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

    public function validateSiteTransferBundle(WP_REST_Request $request): mixed
    {
        $rawFiles = $request->get_file_params();

        return $this->restResult(
            $this->admin->stageSiteTransferBundle(
                is_array($rawFiles['bundle'] ?? null) ? $rawFiles['bundle'] : []
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
                    $this->normalizePostedUploadRows($postedRows),
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
            $this->admin->saveRoleDraftValues($this->getRoleDraftInput($request))
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

    public function renderFamilyCard(WP_REST_Request $request): mixed
    {
        return $this->restResult(
            $this->admin->renderFamilyCardFragment(
                $this->getTextParam($request, 'family_slug')
            ),
            404
        );
    }

    /**
     * @param RouteArgs $args
     */
    private function registerRoute(string $path, string $methods, callable $callback, array $args = []): void
    {
        register_rest_route(
            self::API_NAMESPACE,
            '/' . ltrim($path, '/'),
            [
                'methods' => $methods,
                'callback' => $callback,
                'permission_callback' => [$this, 'canManageOptions'],
                'args' => $args,
            ]
        );
    }

    /**
     * @param RestResultPayload|WP_Error $result
     */
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

    /**
     * @return list<string>
     */
    private function getVariantTokens(WP_REST_Request $request): array
    {
        $variants = $request->get_param('variants');

        if (is_array($variants) && $variants !== []) {
            return FontUtils::normalizeVariantTokens(
                array_values(
                    array_map(
                        static fn (mixed $variant): string => is_scalar($variant) ? (string) $variant : '',
                        $variants
                    )
                )
            );
        }

        $variantTokens = $this->getTextParam($request, 'variant_tokens');

        if ($variantTokens === '') {
            return [];
        }

        return FontUtils::normalizeVariantTokens(explode(',', $variantTokens));
    }

    /**
     * @return array<string, mixed>
     */
    private function getRoleDraftInput(WP_REST_Request $request): array
    {
        $params = $request->get_params();
        $input = [];

        foreach (
            [
                'heading' => '',
                'body' => '',
                'monospace' => '',
                'heading_fallback' => 'sans-serif',
                'body_fallback' => 'sans-serif',
                'monospace_fallback' => 'monospace',
                'heading_weight' => '',
                'body_weight' => '',
                'monospace_weight' => '',
            ] as $key => $default
        ) {
            if (!array_key_exists($key, $params)) {
                continue;
            }

            $input[$key] = $this->getTextParam($request, $key, $default);
        }

        foreach (['heading_axes', 'body_axes', 'monospace_axes'] as $key) {
            if (!array_key_exists($key, $params) || !is_array($params[$key])) {
                continue;
            }

            $input[$key] = $params[$key];
        }

        return $input;
    }

    /**
     * @return RouteArgs
     */
    private function settingsArgs(): array
    {
        $args = [];

        foreach (SettingsSaveFields::definitions() as $definition) {
            $name = $definition['name'];
            $kind = $definition['kind'];

            if ($name === '') {
                continue;
            }

            if ($kind === 'toggle') {
                $args[$name] = $this->buildToggleArg();
                continue;
            }

            if ($kind === 'string_array') {
                $args[$name] = $this->buildStringArrayArg();
                continue;
            }

            if ($kind === 'int_array') {
                $args[$name] = $this->buildIntegerArrayArg();
                continue;
            }

            $allowedValues = is_array($definition['values'] ?? null) ? array_values((array) $definition['values']) : null;
            $args[$name] = $this->buildTextArg(false, $allowedValues);
        }

        return $args;
    }

    /**
     * @return RouteArgs
     */
    private function hostedImportArgs(): array
    {
        return [
            'family' => $this->buildTextArg(true),
            'variants' => $this->buildStringArrayArg(),
            'variant_tokens' => $this->buildTextArg(),
            'delivery_mode' => $this->buildTextArg(false, ['self_hosted', 'cdn']),
            'format_mode' => $this->buildTextArg(false, ['static', 'variable']),
        ];
    }

    /**
     * @return RouteArgs
     */
    private function roleDraftArgs(): array
    {
        $args = [];

        foreach (
            [
                'heading',
                'body',
                'monospace',
                'heading_fallback',
                'body_fallback',
                'monospace_fallback',
                'heading_delivery_id',
                'body_delivery_id',
                'monospace_delivery_id',
                'heading_weight',
                'body_weight',
                'monospace_weight',
            ] as $key
        ) {
            $args[$key] = $this->buildTextArg();
        }

        foreach (['heading_axes', 'body_axes', 'monospace_axes'] as $key) {
            $args[$key] = $this->buildNestedArrayArg();
        }

        return $args;
    }

    /**
     * @param list<string>|null $allowedValues
     * @return TextArg
     */
    private function buildTextArg(bool $required = false, ?array $allowedValues = null): array
    {
        return [
            'type' => 'string',
            'required' => $required,
            'sanitize_callback' => [$this, 'sanitizeTextArg'],
            'validate_callback' => fn (mixed $value): bool => $this->validateTextArg($value, $required, $allowedValues),
        ];
    }

    /**
     * @return TextArg
     */
    private function buildToggleArg(): array
    {
        return [
            'sanitize_callback' => [$this, 'sanitizeToggleArg'],
            'validate_callback' => [$this, 'validateToggleArg'],
        ];
    }

    /**
     * @return TextArg
     */
    private function buildStringArrayArg(bool $required = false): array
    {
        return [
            'type' => 'array',
            'required' => $required,
            'items' => ['type' => 'string'],
            'sanitize_callback' => [$this, 'sanitizeStringArrayArg'],
            'validate_callback' => fn (mixed $value): bool => $this->validateStringArrayArg($value, $required),
        ];
    }

    /**
     * @return NestedArg
     */
    private function buildNestedArrayArg(bool $required = false): array
    {
        return [
            'type' => 'array',
            'required' => $required,
            'sanitize_callback' => [$this, 'sanitizeNestedArrayArg'],
            'validate_callback' => fn (mixed $value): bool => $this->validateNestedArrayArg($value, $required),
        ];
    }

    /**
     * @return TextArg
     */
    private function buildIntegerArrayArg(bool $required = false): array
    {
        return [
            'type' => 'array',
            'required' => $required,
            'items' => ['type' => 'integer'],
            'sanitize_callback' => [$this, 'sanitizeIntegerArrayArg'],
            'validate_callback' => fn (mixed $value): bool => $this->validateIntegerArrayArg($value, $required),
        ];
    }

    public function sanitizeTextArg(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        return sanitize_text_field((string) $value);
    }

    public function sanitizeToggleArg(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '0';
        }

        return !empty($value) ? '1' : '0';
    }

    /**
     * @return list<string>
     */
    public function sanitizeStringArrayArg(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    fn (mixed $item): string => $this->sanitizeTextArg($item),
                    $value
                ),
                static fn (string $item): bool => $item !== ''
            )
        );
    }

    /**
     * @return list<int>
     */
    public function sanitizeIntegerArrayArg(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sanitized = [];

        foreach ($value as $item) {
            if (!is_scalar($item) && $item !== null) {
                continue;
            }

            $item = absint($item);

            if ($item <= 0) {
                continue;
            }

            $sanitized[] = $item;
        }

        return $sanitized;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function sanitizeNestedArrayArg(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            $normalizedKey = is_int($key) ? $key : sanitize_text_field((string) $key);

            if (is_array($item)) {
                $sanitized[$normalizedKey] = $this->sanitizeNestedArrayArg($item);
                continue;
            }

            if (!is_scalar($item) && $item !== null) {
                continue;
            }

            $sanitized[$normalizedKey] = sanitize_text_field((string) $item);
        }

        return $sanitized;
    }

    public function validateToggleArg(mixed $value): bool
    {
        return is_scalar($value) || $value === null;
    }

    public function validateStringArrayArg(mixed $value, bool $required = false): bool
    {
        if ($value === null) {
            return !$required;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }

    public function validateIntegerArrayArg(mixed $value, bool $required = false): bool
    {
        if ($value === null) {
            return !$required;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }

    public function validateNestedArrayArg(mixed $value, bool $required = false): bool
    {
        if ($value === null) {
            return !$required;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (is_array($item)) {
                if (!$this->validateNestedArrayArg($item)) {
                    return false;
                }

                continue;
            }

            if (!is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string>|null $allowedValues
     */
    private function validateTextArg(mixed $value, bool $required = false, ?array $allowedValues = null): bool
    {
        if ($value === null) {
            return !$required;
        }

        if (!is_scalar($value)) {
            return false;
        }

        $normalized = trim(sanitize_text_field((string) $value));

        if ($normalized === '') {
            return !$required;
        }

        if ($allowedValues === null) {
            return true;
        }

        return in_array(strtolower($normalized), array_map('strtolower', $allowedValues), true);
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function normalizePostedUploadRows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $row) {
            $normalized[$key] = is_array($row) ? $row : [];
        }

        return $normalized;
    }
}
