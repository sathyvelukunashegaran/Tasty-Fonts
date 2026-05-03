<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;

/**
 * @phpstan-import-type ParsedFilenameResult from \TastyFonts\Fonts\FontFilenameParser
 * @phpstan-import-type VariationDefaults from \TastyFonts\Support\FontUtils
 * @phpstan-import-type CatalogFace from CatalogCache
 * @phpstan-import-type DeliveryProfile from CatalogCache
 * @phpstan-import-type CatalogFamily from CatalogCache
 * @phpstan-import-type CatalogMap from CatalogCache
 */
final class LocalCatalogScanner
{
    private const LOCAL_FORMATS = ['woff2', 'woff', 'ttf', 'otf'];
    private const IMPORTED_SOURCES = ['google', 'bunny', 'custom'];

    public function __construct(
        private readonly Storage $storage,
        private readonly FontFilenameParser $parser
    ) {
    }

    /**
     * @return list<CatalogFamily>
     */
    public function scan(): array
    {
        $root = $this->storage->getRoot();
        $importRootPrefixes = $this->getImportedRootPrefixes();

        if (!$root || !is_dir($root) || !is_readable($root)) {
            return [];
        }

        /** @var CatalogMap $byFamily */
        $byFamily = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::UNIX_PATHS
            )
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$this->isScannableLocalFile($file, $importRootPrefixes)) {
                continue;
            }

            $byFamily = $this->addLocalFileToFamilies($byFamily, $file);
        }

        foreach ($byFamily as &$family) {
            $profileId = $this->localDeliveryId();
            $profiles = $this->deliveryProfiles($family['delivery_profiles'] ?? []);
            $profile = $profiles[$profileId] ?? [];
            $profile['faces'] = array_values($this->deliveryFaceMap($profile));
            $profiles[$profileId] = $profile;
            $family['delivery_profiles'] = $profiles;
        }
        unset($family);

        return array_values($byFamily);
    }

    /**
     * @param CatalogMap $byFamily
     * @return CatalogMap
     */
    private function addLocalFileToFamilies(array $byFamily, SplFileInfo $file): array
    {
        $extension = strtolower($file->getExtension());
        $meta = $this->parser->parse($file->getBasename('.' . $extension));

        if ($meta['is_variable'] && !$this->variableFontsEnabled()) {
            return $byFamily;
        }

        $familyName = $meta['family'];
        $familySlug = FontUtils::slugify($familyName);
        $variantKey = FontUtils::variantKey($meta['weight'], $meta['style']);
        $profileId = $this->localDeliveryId();

        if (!isset($byFamily[$familyName])) {
            $byFamily[$familyName] = $this->buildCatalogFamilyRecord(
                $familyName,
                $familySlug,
                'library_only',
                $profileId,
                [$profileId => $this->buildLocalScanDeliveryProfile($profileId)]
            );
        }

        $profiles = $this->deliveryProfiles($byFamily[$familyName]['delivery_profiles'] ?? []);
        $profile = $profiles[$profileId] ?? $this->buildLocalScanDeliveryProfile($profileId);
        $faces = $this->deliveryFaceMap($profile);

        if (!isset($faces[$variantKey])) {
            $faces[$variantKey] = $this->buildLocalFace($familyName, $familySlug, $meta);
        }

        $absolutePath = wp_normalize_path($file->getPathname());
        $relativePath = $this->storage->relativePath($absolutePath);
        $url = $this->storage->urlForRelativePath($relativePath);

        if ($url === null) {
            return $byFamily;
        }

        $face = $faces[$variantKey];
        $face['files'] = array_replace($this->stringMap($face['files'] ?? []), [$extension => $relativePath]);
        $face['paths'] = array_replace($this->stringMap($face['paths'] ?? []), [$extension => $relativePath]);
        $faces[$variantKey] = $face;
        $profile['faces'] = $faces;
        $profile['variants'] = array_values(array_unique([...$this->stringList($profile['variants'] ?? []), $this->variantTokenFromMeta($meta)]));
        $profiles[$profileId] = $profile;
        $byFamily[$familyName]['delivery_profiles'] = $profiles;

        return $byFamily;
    }

    /**
     * @param ParsedFilenameResult $meta
     * @return CatalogFace
     */
    private function buildLocalFace(string $familyName, string $familySlug, array $meta): array
    {
        $axes = FontUtils::normalizeAxesValue($meta['axes']);

        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'source' => 'local',
            'weight' => FontUtils::normalizeWeight($meta['weight']),
            'style' => FontUtils::normalizeStyle($meta['style']),
            'unicode_range' => '',
            'files' => [],
            'paths' => [],
            'provider' => ['type' => 'local'],
            'is_variable' => !empty($meta['is_variable']),
            'axes' => $axes,
            'variation_defaults' => FontUtils::normalizeVariationDefaultsValue($meta['variation_defaults'], $axes),
        ];
    }

    /**
     * @param array<string, DeliveryProfile> $deliveryProfiles
     * @return CatalogFamily
     */
    private function buildCatalogFamilyRecord(
        string $familyName,
        string $familySlug,
        string $publishState,
        string $activeDeliveryId,
        array $deliveryProfiles
    ): array {
        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'publish_state' => $publishState,
            'active_delivery_id' => $activeDeliveryId,
            'delivery_profiles' => $deliveryProfiles,
        ];
    }

    /**
     * @return DeliveryProfile
     */
    private function buildLocalScanDeliveryProfile(string $profileId): array
    {
        return [
            'id' => $profileId,
            'provider' => 'local',
            'type' => 'self_hosted',
            'label' => __('Self-hosted', 'tasty-fonts'),
            'variants' => [],
            'faces' => [],
            'meta' => ['origin' => 'local_scan'],
        ];
    }

    /**
     * @return list<string>
     */
    private function getImportedRootPrefixes(): array
    {
        $prefixes = [];

        foreach (self::IMPORTED_SOURCES as $source) {
            $root = $this->storage->getProviderRoot($source);

            if (!is_string($root) || $root === '') {
                continue;
            }

            $prefixes[] = trailingslashit(wp_normalize_path($root));
        }

        return $prefixes;
    }

    /**
     * @param list<string> $importRootPrefixes
     */
    private function isScannableLocalFile(SplFileInfo $file, array $importRootPrefixes): bool
    {
        if (!$file->isFile()) {
            return false;
        }

        $absolutePath = wp_normalize_path($file->getPathname());

        foreach ($importRootPrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($absolutePath, $prefix)) {
                return false;
            }
        }

        return in_array(strtolower($file->getExtension()), self::LOCAL_FORMATS, true);
    }

    private function variableFontsEnabled(): bool
    {
        $settings = get_option(SettingsRepository::OPTION_SETTINGS, []);

        return is_array($settings) && !empty($settings['variable_fonts_enabled']);
    }

    private function localDeliveryId(): string
    {
        return FontUtils::slugify('local-self_hosted');
    }

    /**
     * @param ParsedFilenameResult $meta
     */
    private function variantTokenFromMeta(array $meta): string
    {
        $weight = FontUtils::normalizeWeight($meta['weight']);
        $style = FontUtils::normalizeStyle($meta['style']);

        if ($weight === '400' && $style === 'normal') {
            return 'regular';
        }

        if ($weight === '400' && $style === 'italic') {
            return 'italic';
        }

        return $weight . ($style === 'italic' ? 'italic' : '');
    }

    /**
     * @param mixed $profiles
     * @return array<string, DeliveryProfile>
     */
    private function deliveryProfiles(mixed $profiles): array
    {
        if (!is_array($profiles)) {
            return [];
        }

        $normalized = [];

        foreach ($profiles as $profileId => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $normalizedProfile = FontUtils::normalizeStringKeyedMap($profile);
            $id = FontUtils::stringValue($normalizedProfile, 'id', is_string($profileId) ? $profileId : '');

            if ($id === '') {
                continue;
            }

            $normalized[$id] = $normalizedProfile;
        }

        return $normalized;
    }

    /**
     * @param DeliveryProfile $profile
     * @return array<string, CatalogFace>
     */
    private function deliveryFaceMap(array $profile): array
    {
        $normalized = [];

        foreach (FontUtils::normalizeFaceList($profile['faces'] ?? []) as $face) {
            $normalized[HostedImportSupport::faceKeyFromFace($face)] = $face;
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $normalizedValue = FontUtils::scalarStringValue($value);

            if ($normalizedValue === '') {
                continue;
            }

            $normalized[$key] = $normalizedValue;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            $normalizedValue = FontUtils::scalarStringValue($value);

            if ($normalizedValue === '') {
                continue;
            }

            $normalized[] = $normalizedValue;
        }

        return $normalized;
    }

}
