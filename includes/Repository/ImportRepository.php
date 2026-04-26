<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type AxesMap from \TastyFonts\Support\FontUtils
 * @phpstan-import-type VariationDefaults from \TastyFonts\Support\FontUtils
 * @phpstan-type ProfileMetaValue string|list<string>
 * @phpstan-type FaceRecord array{
 *     family: string,
 *     slug: string,
 *     source: string,
 *     weight: string,
 *     style: string,
 *     unicode_range: string,
 *     files: array<string, string>,
 *     paths: array<string, string>,
 *     provider: array<string, mixed>,
 *     is_variable: bool,
 *     axes: AxesMap,
 *     variation_defaults: VariationDefaults
 * }
 * @phpstan-type DeliveryProfile array{
 *     id: string,
 *     provider: string,
 *     type: string,
 *     format: string,
 *     label: string,
 *     variants: list<string>,
 *     faces: list<FaceRecord>,
 *     meta: array<string, ProfileMetaValue>
 * }
 * @phpstan-type LibraryRecord array{
 *     family: string,
 *     slug: string,
 *     publish_state: string,
 *     manual_publish_state: string,
 *     active_delivery_id: string,
 *     delivery_profiles: array<string, DeliveryProfile>
 * }
 * @phpstan-type LibraryMap array<string, LibraryRecord>
 */
final class ImportRepository
{
    public const OPTION_LIBRARY = 'tasty_fonts_library';
    private const SUPPORTED_PUBLISH_STATES = ['library_only', 'published', 'role_active'];
    private const SUPPORTED_DELIVERY_TYPES = ['self_hosted', 'cdn', 'adobe_hosted'];
    private const SUPPORTED_PROVIDERS = ['local', 'google', 'bunny', 'adobe', 'custom'];
    private const SUPPORTED_FORMATS = ['static', 'variable'];

    /**
     * @return LibraryMap
     */
    public function all(): array
    {
        return $this->allFamilies();
    }

    /**
     * @return LibraryMap
     */
    public function allFamilies(): array
    {
        return $this->getLibrary();
    }

    /**
     * @return LibraryRecord|null
     */
    public function get(string $slug): ?array
    {
        return $this->getFamily($slug);
    }

    /**
     * @return LibraryRecord|null
     */
    public function getFamily(string $slug): ?array
    {
        $slug = FontUtils::slugify($slug);

        if ($slug === '') {
            return null;
        }

        $library = $this->getLibrary();

        return $library[$slug] ?? null;
    }

    /**
     * @param array<string, mixed> $family
     */
    public function upsert(array $family): void
    {
        $this->saveFamily($family);
    }

    /**
     * @param array<string, mixed> $family
     */
    public function saveFamily(array $family): void
    {
        $rawSlug = $this->stringValue($family, 'slug');

        if ($rawSlug === '') {
            return;
        }

        $slug = FontUtils::slugify($rawSlug);

        $library = $this->getLibrary();
        $existing = $library[$slug] ?? null;
        $normalizedFamily = $this->normalizeFamilyRecord($family, $existing);

        if ($normalizedFamily === []) {
            return;
        }

        $library[$slug] = $normalizedFamily;
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

    /**
     * @return LibraryRecord|array{}
     */
    public function ensureFamily(
        string $familyName,
        ?string $familySlug = null,
        string $defaultPublishState = 'published',
        ?string $activeDeliveryId = null,
        ?string $defaultManualPublishState = null
    ): array {
        $familyName = sanitize_text_field($familyName);
        $familySlug = FontUtils::slugify($familySlug ?? $familyName);

        if ($familyName === '' || $familySlug === '') {
            return [];
        }

        $library = $this->getLibrary();
        $existing = $library[$familySlug] ?? null;

        if ($existing !== null) {
            return $existing;
        }

        $family = $this->normalizeFamilyRecord(
            [
                'family' => $familyName,
                'slug' => $familySlug,
                'publish_state' => $defaultPublishState,
                'manual_publish_state' => $defaultManualPublishState,
                'active_delivery_id' => $activeDeliveryId ?? '',
                'delivery_profiles' => [],
            ],
            null
        );

        if ($family === []) {
            return [];
        }

        $library[$familySlug] = $family;
        $this->persistLibrary($library);

        return $family;
    }

    /**
     * @param array<string, mixed> $profile
     * @return LibraryRecord|array{}
     */
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
        $familyInput = [
            'family' => $familyName,
            'slug' => $familySlug,
            'publish_state' => $defaultPublishState,
            'delivery_profiles' => [$normalizedProfile['id'] => $normalizedProfile],
        ];

        if ($activate) {
            $familyInput['active_delivery_id'] = $normalizedProfile['id'];
        }

        $family = $this->normalizeFamilyRecord($familyInput, $existing);

        if ($family === []) {
            return [];
        }

        $profiles = $family['delivery_profiles'];
        $profiles[$normalizedProfile['id']] = $normalizedProfile;
        $family['delivery_profiles'] = $profiles;

        if ($activate || trim($family['active_delivery_id']) === '') {
            $family['active_delivery_id'] = $normalizedProfile['id'];
        }

        $normalizedFamily = $this->normalizeFamilyRecord($family, $family);

        if ($normalizedFamily === []) {
            return [];
        }

        $library[$familySlug] = $normalizedFamily;
        $this->persistLibrary($library);

        return $library[$familySlug];
    }

    /**
     * @return LibraryRecord|array{}|null
     */
    public function deleteProfile(string $familySlug, string $deliveryId): ?array
    {
        return $this->mutateFamilyDelivery(
            $familySlug,
            $deliveryId,
            function (array &$library, string $normalizedFamilySlug, array &$family, array &$profiles, string $normalizedDeliveryId): ?array {
                unset($profiles[$normalizedDeliveryId]);

                if ($profiles === []) {
                    unset($library[$normalizedFamilySlug]);
                    return null;
                }

                $family['delivery_profiles'] = $profiles;

                if ($family['active_delivery_id'] === $normalizedDeliveryId) {
                    $family['active_delivery_id'] = (string) array_key_first($profiles);
                }

                return $family;
            }
        );
    }

    /**
     * @return LibraryRecord|array{}|null
     */
    public function setActiveDelivery(string $familySlug, string $deliveryId, ?string $publishState = null): ?array
    {
        return $this->mutateFamilyDelivery(
            $familySlug,
            $deliveryId,
            static function (array &$library, string $normalizedFamilySlug, array &$family, array &$profiles, string $normalizedDeliveryId) use ($publishState): array {
                $family['active_delivery_id'] = $normalizedDeliveryId;

                if ($publishState !== null) {
                    $family['publish_state'] = $publishState;

                    if ($publishState !== 'role_active') {
                        $family['manual_publish_state'] = $publishState;
                    }
                }

                return $family;
            }
        );
    }

    /**
     * @return LibraryRecord|array{}|null
     */
    public function setPublishState(string $familySlug, string $publishState): ?array
    {
        $familySlug = FontUtils::slugify($familySlug);

        if ($familySlug === '') {
            return null;
        }

        $library = $this->getLibrary();
        $family = $library[$familySlug] ?? null;

        if ($family === null) {
            return null;
        }

        $family['publish_state'] = $publishState;

        if ($publishState !== 'role_active') {
            $family['manual_publish_state'] = $publishState;
        }

        $normalizedFamily = $this->normalizeFamilyRecord($family, $family);

        if ($normalizedFamily === []) {
            return [];
        }

        $library[$familySlug] = $normalizedFamily;
        $this->persistLibrary($library);

        return $library[$familySlug];
    }

    /**
     * @param callable(LibraryMap, string, LibraryRecord, array<string, DeliveryProfile>, string): (LibraryRecord|null) $mutator
     * @return LibraryRecord|array{}|null
     */
    private function mutateFamilyDelivery(string $familySlug, string $deliveryId, callable $mutator): ?array
    {
        $familySlug = FontUtils::slugify($familySlug);
        $deliveryId = $this->normalizeDeliveryId($deliveryId);

        if ($familySlug === '' || $deliveryId === '') {
            return null;
        }

        $library = $this->getLibrary();
        $family = $library[$familySlug] ?? null;

        if ($family === null) {
            return null;
        }

        $profiles = $family['delivery_profiles'];

        if (!isset($profiles[$deliveryId])) {
            return null;
        }

        $mutatedFamily = $mutator($library, $familySlug, $family, $profiles, $deliveryId);

        if ($mutatedFamily === null) {
            $this->persistLibrary($library);
            return null;
        }

        $normalizedFamily = $this->normalizeFamilyRecord($mutatedFamily, $mutatedFamily);

        if ($normalizedFamily === []) {
            return [];
        }

        $library[$familySlug] = $normalizedFamily;
        $this->persistLibrary($library);

        return $library[$familySlug];
    }

    public function clearLibrary(): void
    {
        delete_option(self::OPTION_LIBRARY);
    }

    /**
     * @param LibraryMap $library
     * @return LibraryMap
     */
    public function replaceLibrary(array $library): array
    {
        $normalized = $this->normalizeLibrary($library);
        $this->persistLibrary($normalized);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $library
     * @return LibraryMap
     */
    public function replaceLibraryPreview(array $library): array
    {
        return $this->normalizeLibrary($library);
    }

    /**
     * @return LibraryMap
     */
    private function getLibrary(): array
    {
        $value = get_option(self::OPTION_LIBRARY, null);

        if (is_array($value)) {
            return $this->normalizeLibrary($value);
        }

        return [];
    }

    /**
     * @param LibraryMap $library
     */
    private function persistLibrary(array $library): void
    {
        update_option(self::OPTION_LIBRARY, $this->normalizeLibrary($library), false);
    }

    /**
     * @param array<int|string, mixed> $library
     * @return LibraryMap
     */
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

            $normalized[$normalizedFamily['slug']] = $normalizedFamily;
        }

        uasort(
            $normalized,
            static fn (array $left, array $right): int => strcmp($left['family'], $right['family'])
        );

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $family
     * @param LibraryRecord|null $existing
     * @return LibraryRecord|array{}
     */
    private function normalizeFamilyRecord(array $family, ?array $existing): array
    {
        $familyName = sanitize_text_field($this->stringValue($family, 'family', $existing !== null ? $existing['family'] : ''));
        $familySlug = FontUtils::slugify($this->stringValue($family, 'slug', $existing !== null ? $existing['slug'] : $familyName));

        if ($familyName === '' || $familySlug === '') {
            return [];
        }

        $existingProfiles = $existing !== null ? $existing['delivery_profiles'] : [];
        $inputProfiles = $this->arrayValue($family, 'delivery_profiles');
        $profiles = [];

        foreach ($existingProfiles as $key => $profile) {
            $normalizedProfile = $this->normalizeDeliveryProfile($profile + ['id' => $key]);

            if ($normalizedProfile === []) {
                continue;
            }

            $profiles[$normalizedProfile['id']] = $normalizedProfile;
        }

        foreach ($inputProfiles as $key => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $normalizedProfile = $this->normalizeDeliveryProfile($profile + ['id' => $key]);

            if ($normalizedProfile === []) {
                continue;
            }

            $profiles[$normalizedProfile['id']] = $normalizedProfile;
        }

        $activeDeliveryId = $this->normalizeDeliveryId(
            $this->stringValue($family, 'active_delivery_id', $existing !== null ? $existing['active_delivery_id'] : '')
        );

        if ($activeDeliveryId === '') {
            $activeDeliveryId = $profiles !== [] ? (string) array_key_first($profiles) : '';
        }

        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'publish_state' => $this->normalizePublishState(
                $this->stringValue($family, 'publish_state', $existing !== null ? $existing['publish_state'] : 'published')
            ),
            'manual_publish_state' => $this->normalizeManualPublishState(
                $this->stringValue($family, 'manual_publish_state'),
                $this->stringValue($family, 'publish_state'),
                $existing
            ),
            'active_delivery_id' => $activeDeliveryId,
            'delivery_profiles' => $profiles,
        ];
    }

    /**
     * @param array<array-key, mixed> $profile
     * @return DeliveryProfile|array{}
     */
    private function normalizeDeliveryProfile(array $profile): array
    {
        $provider = $this->normalizeProvider($this->stringValue($profile, 'provider'));
        $type = $this->normalizeDeliveryType($this->stringValue($profile, 'type'));
        $id = $this->normalizeDeliveryId($this->stringValue($profile, 'id'));

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
            'format' => $this->normalizeFormat($this->stringValue($profile, 'format'), $this->normalizeFaceList($profile['faces'] ?? [])),
            'label' => sanitize_text_field($this->stringValue($profile, 'label', $this->defaultProfileLabel($provider, $type))),
            'variants' => $this->normalizeVariantTokenList($profile['variants'] ?? []),
            'faces' => $this->normalizeFaces($this->normalizeFaceList($profile['faces'] ?? [])),
            'meta' => $this->normalizeMeta($this->arrayValue($profile, 'meta')),
        ];
    }

    /**
     * @param list<array<string, mixed>> $faces
     * @return list<FaceRecord>
     */
    private function normalizeFaces(array $faces): array
    {
        $normalized = [];

        foreach ($faces as $face) {
            $files = FontUtils::normalizeStringKeyedMap($face['files'] ?? null);
            $paths = FontUtils::normalizeStringKeyedMap($face['paths'] ?? null);
            $axes = FontUtils::normalizeAxesMap($face['axes'] ?? []);

            if ($files === [] && $paths === []) {
                continue;
            }

            $normalized[] = [
                'family' => sanitize_text_field($this->stringValue($face, 'family')),
                'slug' => FontUtils::slugify($this->stringValue($face, 'slug')),
                'source' => $this->normalizeProvider($this->stringValue($face, 'source')) ?: 'local',
                'weight' => FontUtils::normalizeWeight($this->stringValue($face, 'weight', '400')),
                'style' => FontUtils::normalizeStyle($this->stringValue($face, 'style', 'normal')),
                'unicode_range' => sanitize_text_field($this->stringValue($face, 'unicode_range')),
                'files' => $this->normalizeStringMap($files),
                'paths' => $this->normalizeStringMap($paths),
                'provider' => FontUtils::normalizeStringKeyedMap($face['provider'] ?? null),
                'is_variable' => !empty($face['is_variable']) || $axes !== [],
                'axes' => $axes,
                'variation_defaults' => FontUtils::normalizeVariationDefaults($face['variation_defaults'] ?? [], $axes),
            ];
        }

        uasort($normalized, [FontUtils::class, 'compareFacesByWeightAndStyle']);

        return array_values($normalized);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, ProfileMetaValue>
     */
    private function normalizeMeta(array $meta): array
    {
        $normalized = [];

        foreach ($meta as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = array_values(
                    array_filter(
                        array_map(static fn (mixed $item): string => is_string($item) ? sanitize_text_field($item) : '', $value),
                        static fn (string $item): bool => $item !== ''
                    )
                );
                continue;
            }

            $normalized[$key] = sanitize_text_field($this->mixedStringValue($value));
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function normalizeStringMap(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (!is_string($value)) {
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

    /**
     * @param LibraryRecord|null $existing
     */
    private function normalizeManualPublishState(string $state, string $publishState = '', ?array $existing = null): string
    {
        $state = sanitize_text_field($state);

        if (in_array($state, ['library_only', 'published'], true)) {
            return $state;
        }

        $existingState = $existing !== null ? sanitize_text_field($existing['manual_publish_state']) : '';

        if (in_array($existingState, ['library_only', 'published'], true)) {
            return $existingState;
        }

        $publishState = $this->normalizePublishState($publishState);

        if (in_array($publishState, ['library_only', 'published'], true)) {
            return $publishState;
        }

        return 'published';
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
        if (trim($deliveryId) === '') {
            return '';
        }

        return FontUtils::slugify($deliveryId);
    }

    /**
     * @param mixed $variants
     * @return list<string>
     */
    private function normalizeVariantTokenList(mixed $variants): array
    {
        if (!is_array($variants)) {
            return [];
        }

        $normalized = [];

        foreach ($variants as $variant) {
            if (!is_scalar($variant)) {
                continue;
            }

            $normalized[] = $this->mixedStringValue($variant);
        }

        return FontUtils::normalizeVariantTokens($normalized);
    }

    /**
     * @param mixed $faces
     * @return list<array<string, mixed>>
     */
    private function normalizeFaceList(mixed $faces): array
    {
        return FontUtils::normalizeFaceList($faces);
    }

    /**
     * @param list<array<string, mixed>> $faces
     */
    private function normalizeFormat(string $format, array $faces): string
    {
        $normalized = strtolower(trim($format));

        if (in_array($normalized, self::SUPPORTED_FORMATS, true)) {
            return $normalized;
        }

        return FontUtils::facesHaveVariableMetadata($faces) ? 'variable' : 'static';
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
            'custom:self_hosted' => 'Self-hosted custom CSS',
            'custom:cdn' => 'Remote custom CSS',
            default => ucfirst($provider),
        };
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $value = $values[$key];

        return $this->mixedStringValue($value, $default);
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private function arrayValue(array $values, string $key): array
    {
        $value = $values[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $nestedKey => $nestedValue) {
            $normalized[(string) $nestedKey] = $nestedValue;
        }

        return $normalized;
    }

    private function mixedStringValue(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return (string) $value;
        }

        return $default;
    }
}
