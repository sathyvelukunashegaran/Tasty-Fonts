<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

use TastyFonts\Support\FontUtils;

final class ImportRepository
{
    public const OPTION_LIBRARY = 'tasty_fonts_library';
    public const OPTION_IMPORTS = 'tasty_fonts_imports';
    private const LEGACY_OPTION_IMPORTS = 'etch_fonts_imports';
    private const SUPPORTED_PUBLISH_STATES = ['library_only', 'published', 'role_active'];
    private const SUPPORTED_DELIVERY_TYPES = ['self_hosted', 'cdn', 'adobe_hosted'];
    private const SUPPORTED_PROVIDERS = ['local', 'google', 'bunny', 'adobe'];

    public function all(): array
    {
        return $this->allFamilies();
    }

    public function allFamilies(): array
    {
        return $this->getLibrary();
    }

    public function get(string $slug): ?array
    {
        return $this->getFamily($slug);
    }

    public function getFamily(string $slug): ?array
    {
        $slug = FontUtils::slugify($slug);

        if ($slug === '') {
            return null;
        }

        $library = $this->getLibrary();
        $family = $library[$slug] ?? null;

        return is_array($family) ? $family : null;
    }

    public function upsert(array $family): void
    {
        $this->saveFamily($family);
    }

    public function saveFamily(array $family): void
    {
        if (empty($family['slug']) || !is_string($family['slug'])) {
            return;
        }

        $library = $this->getLibrary();
        $slug = FontUtils::slugify((string) $family['slug']);
        $existing = $library[$slug] ?? null;
        $library[$slug] = $this->normalizeFamilyRecord($family, is_array($existing) ? $existing : null);
        $this->persistLibrary($library);
    }

    public function delete(string $slug): void
    {
        $this->deleteFamily($slug);
    }

    public function deleteFamily(string $slug): void
    {
        $slug = FontUtils::slugify($slug);

        if ($slug === '') {
            return;
        }

        $library = $this->getLibrary();

        if (!isset($library[$slug])) {
            return;
        }

        unset($library[$slug]);
        $this->persistLibrary($library);
    }

    public function ensureFamily(
        string $familyName,
        ?string $familySlug = null,
        string $defaultPublishState = 'published',
        ?string $activeDeliveryId = null
    ): array {
        $familyName = sanitize_text_field($familyName);
        $familySlug = FontUtils::slugify($familySlug ?? $familyName);

        if ($familyName === '' || $familySlug === '') {
            return [];
        }

        $library = $this->getLibrary();
        $existing = $library[$familySlug] ?? null;

        if (is_array($existing)) {
            return $existing;
        }

        $family = $this->normalizeFamilyRecord(
            [
                'family' => $familyName,
                'slug' => $familySlug,
                'publish_state' => $defaultPublishState,
                'active_delivery_id' => $activeDeliveryId ?? '',
                'delivery_profiles' => [],
            ],
            null
        );

        $library[$familySlug] = $family;
        $this->persistLibrary($library);

        return $family;
    }

    public function saveProfile(
        string $familyName,
        string $familySlug,
        array $profile,
        string $defaultPublishState = 'library_only',
        bool $activate = false
    ): array {
        $familyName = sanitize_text_field($familyName);
        $familySlug = FontUtils::slugify($familySlug !== '' ? $familySlug : $familyName);
        $normalizedProfile = $this->normalizeDeliveryProfile($profile);

        if ($familyName === '' || $familySlug === '' || $normalizedProfile === []) {
            return [];
        }

        $library = $this->getLibrary();
        $existing = $library[$familySlug] ?? null;
        $family = $this->normalizeFamilyRecord(
            [
                'family' => $familyName,
                'slug' => $familySlug,
                'publish_state' => $defaultPublishState,
                'active_delivery_id' => $activate ? (string) ($normalizedProfile['id'] ?? '') : '',
                'delivery_profiles' => [$normalizedProfile['id'] => $normalizedProfile],
            ],
            is_array($existing) ? $existing : null
        );

        $profiles = is_array($family['delivery_profiles'] ?? null) ? $family['delivery_profiles'] : [];
        $profiles[(string) $normalizedProfile['id']] = $normalizedProfile;
        $family['delivery_profiles'] = $profiles;

        if ($activate || trim((string) ($family['active_delivery_id'] ?? '')) === '') {
            $family['active_delivery_id'] = (string) $normalizedProfile['id'];
        }

        $library[$familySlug] = $this->normalizeFamilyRecord($family, $family);
        $this->persistLibrary($library);

        return $library[$familySlug];
    }

    public function deleteProfile(string $familySlug, string $deliveryId): ?array
    {
        $familySlug = FontUtils::slugify($familySlug);
        $deliveryId = $this->normalizeDeliveryId($deliveryId);

        if ($familySlug === '' || $deliveryId === '') {
            return null;
        }

        $library = $this->getLibrary();
        $family = $library[$familySlug] ?? null;

        if (!is_array($family)) {
            return null;
        }

        $profiles = is_array($family['delivery_profiles'] ?? null) ? $family['delivery_profiles'] : [];

        if (!isset($profiles[$deliveryId])) {
            return null;
        }

        unset($profiles[$deliveryId]);

        if ($profiles === []) {
            unset($library[$familySlug]);
            $this->persistLibrary($library);

            return null;
        }

        $family['delivery_profiles'] = $profiles;

        if ((string) ($family['active_delivery_id'] ?? '') === $deliveryId) {
            $family['active_delivery_id'] = (string) array_key_first($profiles);
        }

        $library[$familySlug] = $this->normalizeFamilyRecord($family, $family);
        $this->persistLibrary($library);

        return $library[$familySlug];
    }

    public function setActiveDelivery(string $familySlug, string $deliveryId, ?string $publishState = null): ?array
    {
        $familySlug = FontUtils::slugify($familySlug);
        $deliveryId = $this->normalizeDeliveryId($deliveryId);

        if ($familySlug === '' || $deliveryId === '') {
            return null;
        }

        $library = $this->getLibrary();
        $family = $library[$familySlug] ?? null;

        if (!is_array($family)) {
            return null;
        }

        $profiles = is_array($family['delivery_profiles'] ?? null) ? $family['delivery_profiles'] : [];

        if (!isset($profiles[$deliveryId])) {
            return null;
        }

        $family['active_delivery_id'] = $deliveryId;

        if ($publishState !== null) {
            $family['publish_state'] = $publishState;
        }

        $library[$familySlug] = $this->normalizeFamilyRecord($family, $family);
        $this->persistLibrary($library);

        return $library[$familySlug];
    }

    public function setPublishState(string $familySlug, string $publishState): ?array
    {
        $familySlug = FontUtils::slugify($familySlug);

        if ($familySlug === '') {
            return null;
        }

        $library = $this->getLibrary();
        $family = $library[$familySlug] ?? null;

        if (!is_array($family)) {
            return null;
        }

        $family['publish_state'] = $publishState;
        $library[$familySlug] = $this->normalizeFamilyRecord($family, $family);
        $this->persistLibrary($library);

        return $library[$familySlug];
    }

    private function getLibrary(): array
    {
        $value = get_option(self::OPTION_LIBRARY, null);

        if (is_array($value)) {
            return $this->normalizeLibrary($value);
        }

        $legacyImports = get_option(self::OPTION_IMPORTS, null);

        if (!is_array($legacyImports)) {
            $legacyImports = get_option(self::LEGACY_OPTION_IMPORTS, null);
        }

        if (!is_array($legacyImports)) {
            return [];
        }

        $library = $this->migrateLegacyImports($legacyImports);
        $this->persistLibrary($library);

        return $library;
    }

    private function persistLibrary(array $library): void
    {
        update_option(self::OPTION_LIBRARY, $this->normalizeLibrary($library), false);
    }

    private function normalizeLibrary(array $library): array
    {
        $normalized = [];

        foreach ($library as $slug => $family) {
            if (!is_array($family)) {
                continue;
            }

            $normalizedFamily = $this->normalizeFamilyRecord($family, null);

            if ($normalizedFamily === []) {
                continue;
            }

            $normalized[(string) $normalizedFamily['slug']] = $normalizedFamily;
        }

        uasort(
            $normalized,
            static fn (array $left, array $right): int => strcmp((string) ($left['family'] ?? ''), (string) ($right['family'] ?? ''))
        );

        return $normalized;
    }

    private function normalizeFamilyRecord(array $family, ?array $existing): array
    {
        $familyName = sanitize_text_field((string) ($family['family'] ?? ($existing['family'] ?? '')));
        $familySlug = FontUtils::slugify((string) ($family['slug'] ?? ($existing['slug'] ?? $familyName)));

        if ($familyName === '' || $familySlug === '') {
            return [];
        }

        $existingProfiles = is_array($existing['delivery_profiles'] ?? null) ? $existing['delivery_profiles'] : [];
        $inputProfiles = $family['delivery_profiles'] ?? [];
        $profiles = [];

        if (is_array($inputProfiles)) {
            foreach ($inputProfiles as $key => $profile) {
                if (!is_array($profile)) {
                    continue;
                }

                $normalizedProfile = $this->normalizeDeliveryProfile($profile + ['id' => is_string($key) ? $key : '']);

                if ($normalizedProfile === []) {
                    continue;
                }

                $profiles[(string) $normalizedProfile['id']] = $normalizedProfile;
            }
        }

        foreach ($existingProfiles as $key => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $normalizedProfile = $this->normalizeDeliveryProfile($profile + ['id' => is_string($key) ? $key : '']);

            if ($normalizedProfile === []) {
                continue;
            }

            if (!isset($profiles[(string) $normalizedProfile['id']])) {
                $profiles[(string) $normalizedProfile['id']] = $normalizedProfile;
            }
        }

        $activeDeliveryId = $this->normalizeDeliveryId((string) ($family['active_delivery_id'] ?? ($existing['active_delivery_id'] ?? '')));

        if ($activeDeliveryId === '' || !isset($profiles[$activeDeliveryId])) {
            $activeDeliveryId = $profiles !== [] ? (string) array_key_first($profiles) : '';
        }

        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'publish_state' => $this->normalizePublishState((string) ($family['publish_state'] ?? ($existing['publish_state'] ?? 'published'))),
            'active_delivery_id' => $activeDeliveryId,
            'delivery_profiles' => $profiles,
        ];
    }

    private function normalizeDeliveryProfile(array $profile): array
    {
        $provider = $this->normalizeProvider((string) ($profile['provider'] ?? ''));
        $type = $this->normalizeDeliveryType((string) ($profile['type'] ?? ''));
        $id = $this->normalizeDeliveryId((string) ($profile['id'] ?? ''));

        if ($id === '') {
            $id = $this->defaultProfileId($provider, $type);
        }

        if ($provider === '' || $type === '' || $id === '') {
            return [];
        }

        return [
            'id' => $id,
            'provider' => $provider,
            'type' => $type,
            'label' => sanitize_text_field((string) ($profile['label'] ?? $this->defaultProfileLabel($provider, $type))),
            'variants' => FontUtils::normalizeVariantTokens((array) ($profile['variants'] ?? [])),
            'faces' => $this->normalizeFaces((array) ($profile['faces'] ?? [])),
            'meta' => $this->normalizeMeta((array) ($profile['meta'] ?? [])),
        ];
    }

    private function normalizeFaces(array $faces): array
    {
        $normalized = [];

        foreach ($faces as $face) {
            if (!is_array($face)) {
                continue;
            }

            $files = is_array($face['files'] ?? null) ? $face['files'] : [];
            $paths = is_array($face['paths'] ?? null) ? $face['paths'] : [];

            if ($files === [] && $paths === []) {
                continue;
            }

            $normalized[] = [
                'family' => sanitize_text_field((string) ($face['family'] ?? '')),
                'slug' => FontUtils::slugify((string) ($face['slug'] ?? '')),
                'source' => $this->normalizeProvider((string) ($face['source'] ?? '')) ?: 'local',
                'weight' => FontUtils::normalizeWeight((string) ($face['weight'] ?? '400')),
                'style' => FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal')),
                'unicode_range' => sanitize_text_field((string) ($face['unicode_range'] ?? '')),
                'files' => $this->normalizeStringMap($files),
                'paths' => $this->normalizeStringMap($paths),
                'provider' => is_array($face['provider'] ?? null) ? $face['provider'] : [],
            ];
        }

        uasort($normalized, [FontUtils::class, 'compareFacesByWeightAndStyle']);

        return array_values($normalized);
    }

    private function normalizeMeta(array $meta): array
    {
        $normalized = [];

        foreach ($meta as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_array($value)) {
                $normalized[$key] = array_values(
                    array_filter(
                        array_map(static fn (mixed $item): string => is_string($item) ? sanitize_text_field($item) : '', $value),
                        'strlen'
                    )
                );
                continue;
            }

            $normalized[$key] = sanitize_text_field((string) $value);
        }

        return $normalized;
    }

    private function normalizeStringMap(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            $normalized[strtolower(trim($key))] = trim($value);
        }

        return $normalized;
    }

    private function normalizePublishState(string $state): string
    {
        $state = sanitize_text_field($state);

        return in_array($state, self::SUPPORTED_PUBLISH_STATES, true) ? $state : 'published';
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));

        return in_array($provider, self::SUPPORTED_PROVIDERS, true) ? $provider : '';
    }

    private function normalizeDeliveryType(string $type): string
    {
        $type = strtolower(trim($type));

        return in_array($type, self::SUPPORTED_DELIVERY_TYPES, true) ? $type : '';
    }

    private function normalizeDeliveryId(string $deliveryId): string
    {
        return FontUtils::slugify($deliveryId);
    }

    private function defaultProfileId(string $provider, string $type): string
    {
        return FontUtils::slugify($provider . '-' . $type);
    }

    private function defaultProfileLabel(string $provider, string $type): string
    {
        return match ($provider . ':' . $type) {
            'local:self_hosted' => 'Self-hosted',
            'google:self_hosted' => 'Self-hosted (Google import)',
            'google:cdn' => 'Google CDN',
            'bunny:self_hosted' => 'Self-hosted (Bunny import)',
            'bunny:cdn' => 'Bunny CDN',
            'adobe:adobe_hosted' => 'Adobe-hosted',
            default => ucfirst($provider),
        };
    }

    private function migrateLegacyImports(array $imports): array
    {
        $library = [];

        foreach ($imports as $slug => $import) {
            if (!is_array($import)) {
                continue;
            }

            $familyName = sanitize_text_field((string) ($import['family'] ?? ''));
            $familySlug = FontUtils::slugify(is_string($slug) ? $slug : (string) ($import['slug'] ?? $familyName));
            $provider = $this->normalizeProvider((string) ($import['provider'] ?? ''));

            if ($provider === '') {
                $provider = $this->inferProviderFromFaces((array) ($import['faces'] ?? []));
            }

            if ($familyName === '' || $familySlug === '' || $provider === '') {
                continue;
            }

            $type = $provider === 'adobe' ? 'adobe_hosted' : 'self_hosted';
            $profileId = $this->defaultProfileId($provider, $type);

            $library[$familySlug] = $this->normalizeFamilyRecord(
                [
                    'family' => $familyName,
                    'slug' => $familySlug,
                    'publish_state' => 'published',
                    'active_delivery_id' => $profileId,
                    'delivery_profiles' => [
                        $profileId => [
                            'id' => $profileId,
                            'provider' => $provider,
                            'type' => $type,
                            'label' => $this->defaultProfileLabel($provider, $type),
                            'variants' => (array) ($import['variants'] ?? []),
                            'faces' => (array) ($import['faces'] ?? []),
                            'meta' => [
                                'category' => (string) ($import['category'] ?? ''),
                                'imported_at' => (string) ($import['imported_at'] ?? ''),
                            ],
                        ],
                    ],
                ],
                null
            );
        }

        return $library;
    }

    private function inferProviderFromFaces(array $faces): string
    {
        foreach ($faces as $face) {
            if (!is_array($face)) {
                continue;
            }

            $provider = $this->normalizeProvider((string) ($face['source'] ?? ''));

            if ($provider !== '') {
                return $provider;
            }
        }

        return '';
    }
}
