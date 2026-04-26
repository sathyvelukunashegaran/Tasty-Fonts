<?php

declare(strict_types=1);

namespace TastyFonts\CustomCss;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;
use TastyFonts\Support\TransientKey;
use WP_Error;

/**
 * Stores short-lived dry-run snapshots and validates final custom CSS import contracts.
 *
 * @phpstan-type Snapshot array<string, mixed>
 */
final class CustomCssImportSnapshotService
{
    public const SNAPSHOT_TTL_SECONDS = 900;
    private const SCHEMA_VERSION = 1;
    private const TRANSIENT_PREFIX = 'tasty_fonts_custom_css_snapshot_';
    private const LOCK_OPTION_PREFIX = 'tasty_fonts_custom_css_snapshot_lock_';
    private const TOKEN_BYTES = 32;
    private const ALLOWED_DELIVERY_MODES = ['self_hosted', 'remote', 'cdn'];
    private const ALLOWED_DUPLICATE_HANDLING = ['skip', 'replace_custom'];
    private const ALLOWED_FINAL_IMPORT_KEYS = [
        '_locale',
        'activate',
        'delivery_mode',
        'duplicate_handling',
        'family_fallbacks',
        'publish',
        'selected_face_ids',
        'snapshot_token',
        'token',
    ];

    /**
     * @param array<string, mixed> $dryRunResult
     * @return array{token: string, expires_at: int, ttl_seconds: int}|WP_Error
     */
    public function createSnapshot(array $dryRunResult): array|WP_Error
    {
        $plan = FontUtils::normalizeStringKeyedMap($dryRunResult['plan'] ?? null);

        if ($plan === [] || !is_array($plan['families'] ?? null)) {
            return new WP_Error(
                'tasty_fonts_custom_css_snapshot_invalid_plan',
                __('The dry-run plan could not be stored. Run the dry run again before importing.', 'tasty-fonts')
            );
        }

        $now = $this->currentTimestamp();
        $token = $this->newToken();
        $snapshot = [
            'schema_version' => self::SCHEMA_VERSION,
            'created_at' => $now,
            'expires_at' => $now + self::SNAPSHOT_TTL_SECONDS,
            'scope' => $this->currentScope(),
            'status' => 'dry_run',
            'message' => FontUtils::scalarStringValue($dryRunResult['message'] ?? ''),
            'plan' => $this->normalizePlanSnapshot($plan),
        ];

        if (!set_transient($this->transientKeyForToken($token), $snapshot, self::SNAPSHOT_TTL_SECONDS)) {
            return new WP_Error(
                'tasty_fonts_custom_css_snapshot_store_failed',
                __('The dry-run snapshot could not be stored. Run the dry run again before importing.', 'tasty-fonts')
            );
        }

        return [
            'token' => $token,
            'expires_at' => $now + self::SNAPSHOT_TTL_SECONDS,
            'ttl_seconds' => self::SNAPSHOT_TTL_SECONDS,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|WP_Error
     */
    public function validateFinalImportContract(array $input): array|WP_Error
    {
        $untrustedKeys = $this->unexpectedFinalImportKeys($input);

        if ($untrustedKeys !== []) {
            return new WP_Error(
                'tasty_fonts_custom_css_untrusted_payload',
                __('Final import only accepts a snapshot token, selected face IDs, and explicit import choices. Run the dry run again if the review data changed.', 'tasty-fonts')
            );
        }

        $token = sanitize_text_field(FontUtils::scalarStringValue($input['snapshot_token'] ?? $input['token'] ?? ''));

        if ($token === '') {
            return new WP_Error(
                'tasty_fonts_custom_css_snapshot_token_missing',
                __('Run the dry run before importing. The import snapshot token is missing.', 'tasty-fonts')
            );
        }

        $snapshotKey = $this->transientKeyForToken($token);
        $lockKey = $this->lockKeyForToken($token);
        $lockAcquired = $this->acquireConsumeLock($lockKey);

        if (!$lockAcquired) {
            return new WP_Error(
                'tasty_fonts_custom_css_snapshot_in_use',
                __('That dry-run snapshot is already being imported. Wait for the import to finish or run the dry run again.', 'tasty-fonts')
            );
        }

        $snapshot = get_transient($snapshotKey);

        if (!is_array($snapshot)) {
            $this->releaseConsumeLock($lockKey);

            return $this->snapshotUnavailableError();
        }

        $snapshot = FontUtils::normalizeStringKeyedMap($snapshot);

        if (!$this->scopeMatches(FontUtils::normalizeStringKeyedMap($snapshot['scope'] ?? null), $this->currentScope())) {
            $this->releaseConsumeLock($lockKey);

            return new WP_Error(
                'tasty_fonts_custom_css_snapshot_scope_mismatch',
                __('That dry-run snapshot belongs to a different site, user, or admin context. Run the dry run again before importing.', 'tasty-fonts')
            );
        }

        if ($this->intValue($snapshot, 'expires_at') <= $this->currentTimestamp()) {
            delete_transient($snapshotKey);
            $this->releaseConsumeLock($lockKey);

            return $this->snapshotUnavailableError();
        }

        $plan = FontUtils::normalizeStringKeyedMap($snapshot['plan'] ?? null);
        $selectedFaceIds = $this->normalizeSelectedFaceIds($input['selected_face_ids'] ?? null);

        if ($selectedFaceIds === []) {
            $this->releaseConsumeLock($lockKey);

            return new WP_Error(
                'tasty_fonts_custom_css_no_selected_faces',
                __('Select at least one validated font face before importing.', 'tasty-fonts')
            );
        }

        $availableFaces = $this->availableSelectableFacesById($plan);
        $unknownFaceIds = array_values(array_diff($selectedFaceIds, array_keys($availableFaces)));

        if ($unknownFaceIds !== []) {
            $this->releaseConsumeLock($lockKey);

            return new WP_Error(
                'tasty_fonts_custom_css_selected_faces_mismatch',
                __('The selected font faces no longer match the dry-run snapshot. Run the dry run again before importing.', 'tasty-fonts')
            );
        }

        $deliveryMode = $this->normalizeDeliveryMode($input['delivery_mode'] ?? 'self_hosted');
        $duplicateHandling = $this->normalizeDuplicateHandling($input['duplicate_handling'] ?? 'skip');
        $selectedFaces = array_map(
            static fn (string $faceId): array => $availableFaces[$faceId],
            $selectedFaceIds
        );
        $families = $this->selectedFamiliesFromSnapshot(
            $plan,
            array_fill_keys($selectedFaceIds, true),
            $this->normalizeFamilyFallbacks($input['family_fallbacks'] ?? [])
        );

        delete_transient($snapshotKey);
        $this->releaseConsumeLock($lockKey);

        return [
            'status' => 'validated',
            'message' => __('Import contract validated.', 'tasty-fonts'),
            'delivery_mode' => $deliveryMode,
            'source' => FontUtils::normalizeStringKeyedMap($plan['source'] ?? null),
            'duplicate_handling' => $duplicateHandling,
            'selected_face_ids' => $selectedFaceIds,
            'selected_faces' => $selectedFaces,
            'families' => $families,
            'options' => [
                'activate' => $this->truthy($input['activate'] ?? false),
                'publish' => $this->truthy($input['publish'] ?? false),
            ],
            'snapshot_consumed' => true,
        ];
    }

    private function lockKeyForToken(string $token): string
    {
        return self::LOCK_OPTION_PREFIX . hash('sha256', $token);
    }

    private function acquireConsumeLock(string $lockKey): bool
    {
        $now = $this->currentTimestamp();
        $existing = get_option($lockKey, null);

        if (is_array($existing) && $this->intValue(FontUtils::normalizeStringKeyedMap($existing), 'expires_at') <= $now) {
            delete_option($lockKey);
        }

        $lock = [
            'created_at' => $now,
            'expires_at' => $now + 60,
        ];

        if (function_exists('add_option')) {
            return add_option($lockKey, $lock, '', false);
        }

        if (get_option($lockKey, null) !== null) {
            return false;
        }

        return update_option($lockKey, $lock, false);
    }

    private function releaseConsumeLock(string $lockKey): void
    {
        delete_option($lockKey);
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function normalizePlanSnapshot(array $plan): array
    {
        return [
            'source' => FontUtils::normalizeStringKeyedMap($plan['source'] ?? null),
            'families' => FontUtils::normalizeListOfStringKeyedMaps($plan['families'] ?? null),
            'counts' => FontUtils::normalizeStringKeyedMap($plan['counts'] ?? null),
            'warnings' => array_values(array_filter(
                array_map(
                    static fn (mixed $warning): string => FontUtils::scalarStringValue($warning),
                    is_array($plan['warnings'] ?? null) ? $plan['warnings'] : []
                ),
                static fn (string $warning): bool => $warning !== ''
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentScope(): array
    {
        $user = wp_get_current_user();
        $roles = array_values($user->roles);
        $roleSlugs = array_values(array_filter(array_unique(array_map(
            static fn (string $role): string => sanitize_key($role),
            $roles
        ))));
        sort($roleSlugs, SORT_STRING);
        $scope = [
            'site_id' => function_exists('get_current_blog_id') ? max(1, (int) get_current_blog_id()) : 1,
            'user_id' => absint($user->ID),
            'role_slugs' => $roleSlugs,
            'can_manage_options' => current_user_can('manage_options'),
            'can_read' => current_user_can('read'),
        ];
        $scope['hash'] = $this->scopeHash($scope);

        return $scope;
    }

    /**
     * @param array<string, mixed> $storedScope
     * @param array<string, mixed> $currentScope
     */
    private function scopeMatches(array $storedScope, array $currentScope): bool
    {
        return $this->intValue($storedScope, 'site_id') === $this->intValue($currentScope, 'site_id')
            && $this->intValue($storedScope, 'user_id') === $this->intValue($currentScope, 'user_id')
            && hash_equals(FontUtils::scalarStringValue($storedScope['hash'] ?? ''), FontUtils::scalarStringValue($currentScope['hash'] ?? ''));
    }

    /**
     * @param array<string, mixed> $scope
     */
    private function scopeHash(array $scope): string
    {
        $payload = wp_json_encode([
            'site_id' => $this->intValue($scope, 'site_id'),
            'user_id' => $this->intValue($scope, 'user_id'),
            'role_slugs' => is_array($scope['role_slugs'] ?? null) ? array_values($scope['role_slugs']) : [],
            'can_manage_options' => !empty($scope['can_manage_options']),
            'can_read' => !empty($scope['can_read']),
        ]);

        return hash_hmac('sha256', is_string($payload) ? $payload : '', wp_salt('auth'));
    }

    /**
     * @param array<string, mixed> $input
     * @return list<string>
     */
    private function unexpectedFinalImportKeys(array $input): array
    {
        $keys = [];

        foreach (array_keys($input) as $key) {
            $normalized = strtolower(trim($key));

            if (!in_array($normalized, self::ALLOWED_FINAL_IMPORT_KEYS, true)) {
                $keys[] = $normalized;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<string>
     */
    private function normalizeSelectedFaceIds(mixed $value): array
    {
        if (!is_array($value)) {
            $value = is_scalar($value) && trim((string) $value) !== '' ? explode(',', (string) $value) : [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $faceId): string => sanitize_key(is_scalar($faceId) ? (string) $faceId : ''),
            $value
        ))));
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, array<string, mixed>>
     */
    private function availableSelectableFacesById(array $plan): array
    {
        $facesById = [];

        foreach (FontUtils::normalizeListOfStringKeyedMaps($plan['families'] ?? null) as $family) {
            foreach (FontUtils::normalizeListOfStringKeyedMaps($family['faces'] ?? null) as $face) {
                $id = sanitize_key(FontUtils::scalarStringValue($face['id'] ?? ''));
                $status = strtolower(FontUtils::scalarStringValue($face['status'] ?? ''));

                if ($id === '' || !in_array($status, ['valid', 'warning'], true)) {
                    continue;
                }

                $face['family'] = FontUtils::scalarStringValue($face['family'] ?? $family['family'] ?? '');
                $face['slug'] = FontUtils::scalarStringValue($face['slug'] ?? $family['slug'] ?? '');
                $facesById[$id] = $face;
            }
        }

        return $facesById;
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, true> $selectedFaceIds
     * @param array<string, string> $fallbacksBySlug
     * @return list<array<string, mixed>>
     */
    private function selectedFamiliesFromSnapshot(array $plan, array $selectedFaceIds, array $fallbacksBySlug): array
    {
        $families = [];

        foreach (FontUtils::normalizeListOfStringKeyedMaps($plan['families'] ?? null) as $family) {
            $familySlug = FontUtils::slugify(FontUtils::scalarStringValue($family['slug'] ?? $family['family'] ?? ''));
            $selectedFaces = [];

            foreach (FontUtils::normalizeListOfStringKeyedMaps($family['faces'] ?? null) as $face) {
                $faceId = sanitize_key(FontUtils::scalarStringValue($face['id'] ?? ''));

                if ($faceId !== '' && isset($selectedFaceIds[$faceId])) {
                    $selectedFaces[] = $face;
                }
            }

            if ($selectedFaces === []) {
                continue;
            }

            $families[] = [
                'family' => FontUtils::scalarStringValue($family['family'] ?? ''),
                'slug' => $familySlug,
                'fallback' => $fallbacksBySlug[$familySlug]
                    ?? FontUtils::sanitizeFallback(FontUtils::scalarStringValue($family['fallback'] ?? 'sans-serif')),
                'faces' => $selectedFaces,
            ];
        }

        return $families;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeFamilyFallbacks(mixed $value): array
    {
        $fallbacks = [];

        if (!is_array($value)) {
            return $fallbacks;
        }

        foreach ($value as $familySlug => $fallback) {
            if (is_int($familySlug)) {
                continue;
            }

            $slug = FontUtils::slugify($familySlug);
            $fallbackValue = FontUtils::sanitizeFallback(FontUtils::scalarStringValue($fallback));

            if ($slug !== '' && $fallbackValue !== '') {
                $fallbacks[$slug] = $fallbackValue;
            }
        }

        return $fallbacks;
    }

    private function normalizeDeliveryMode(mixed $value): string
    {
        $mode = strtolower(sanitize_key(FontUtils::scalarStringValue($value)));

        return in_array($mode, self::ALLOWED_DELIVERY_MODES, true) ? $mode : 'self_hosted';
    }

    private function normalizeDuplicateHandling(mixed $value): string
    {
        $mode = strtolower(sanitize_key(FontUtils::scalarStringValue($value)));

        return in_array($mode, self::ALLOWED_DUPLICATE_HANDLING, true) ? $mode : 'skip';
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function snapshotUnavailableError(): WP_Error
    {
        return new WP_Error(
            'tasty_fonts_custom_css_snapshot_unavailable',
            __('That dry-run snapshot is missing, expired, or was already used. Run the dry run again before importing.', 'tasty-fonts')
        );
    }

    private function transientKeyForToken(string $token): string
    {
        return TransientKey::forSite(self::TRANSIENT_PREFIX . substr($this->tokenHash($token), 0, 40));
    }

    private function tokenHash(string $token): string
    {
        return hash_hmac('sha256', $token, wp_salt('auth'));
    }

    private function newToken(): string
    {
        try {
            return bin2hex(random_bytes(self::TOKEN_BYTES));
        } catch (\Throwable) {
            return hash('sha256', uniqid('tasty-fonts-custom-css-', true) . microtime(true) . wp_salt('nonce'));
        }
    }

    private function currentTimestamp(): int
    {
        return time();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function intValue(array $values, string $key): int
    {
        $value = $values[$key] ?? 0;

        return is_scalar($value) ? (int) $value : 0;
    }
}
