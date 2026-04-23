<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type AxesMap from \TastyFonts\Support\FontUtils
 * @phpstan-import-type CatalogFamily from CatalogService
 * @phpstan-import-type CatalogMap from CatalogService
 * @phpstan-import-type CatalogFace from CatalogService
 * @phpstan-import-type NormalizedSettings from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type RoleSet from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type VariationDefaults from \TastyFonts\Support\FontUtils
 * @phpstan-type RuntimeFamilyList list<CatalogFamily>
 * @phpstan-type PreloadFaceScore array{0: int, 1: int, 2: int}
 * @phpstan-type StylesheetDescriptor array{handle: string, url: string, provider: string, type: string}
 */
final class RuntimeAssetPlanner
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly SettingsRepository $settings,
        private readonly GoogleFontsClient $google,
        private readonly BunnyFontsClient $bunny,
        private readonly AdobeProjectClient $adobe
    ) {
    }

    /**
     * @return RuntimeFamilyList
     */
    public function getRuntimeFamilies(): array
    {
        return $this->filterFamiliesByPublishState($this->catalog->getCatalog(), false);
    }

    /**
     * @return CatalogMap
     */
    public function getPreviewFamilies(): array
    {
        return $this->catalog->getCatalog();
    }

    /**
     * @return CatalogMap
     */
    public function getLocalRuntimeCatalog(): array
    {
        return $this->buildFontFaceCatalog($this->getRuntimeFamilies());
    }

    /**
     * @return RuntimeFamilyList
     */
    public function getRuntimeVariableFamilies(): array
    {
        return $this->getRuntimeFamilies();
    }

    /**
     * @return CatalogMap
     */
    public function getLocalPreviewCatalog(): array
    {
        return $this->buildFontFaceCatalog($this->getPreviewFamilies());
    }

    /**
     * @return CatalogMap
     */
    public function getPreviewVariableFamilies(): array
    {
        return $this->getPreviewFamilies();
    }

    /**
     * @return list<StylesheetDescriptor>
     */
    public function getExternalStylesheets(): array
    {
        return $this->buildExternalStylesheets($this->getRuntimeFamilies());
    }

    /**
     * @return list<StylesheetDescriptor>
     */
    public function getAdminPreviewStylesheets(): array
    {
        return $this->buildExternalStylesheets($this->getPreviewFamilies(), 'swap');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getEditorFontFamilies(): array
    {
        $fontFamilies = [];
        $settings = $this->settings->getSettings();
        $variableFontsEnabled = !empty($settings['variable_fonts_enabled']);

        foreach ($this->getRuntimeFamilies() as $family) {
            $familyName = $this->familyStringValue($family, 'family');

            if ($familyName === '') {
                continue;
            }

            $fontFamilies[$familyName] = [
                'name' => $familyName,
                'slug' => $this->familyStringValue($family, 'slug', FontUtils::slugify($familyName)),
                'fontFamily' => FontUtils::buildFontStack(
                    $familyName,
                    $this->resolveFamilyFallback($family)
                ),
            ];

            $fontFace = $this->buildEditorFontFaceList($family, $variableFontsEnabled);

            if ($fontFace !== []) {
                $fontFamilies[$familyName]['fontFace'] = $fontFace;
            }
        }

        return array_values($fontFamilies);
    }

    /**
     * @return list<string>
     */
    public function getPrimaryFontPreloadUrls(): array
    {
        $settings = $this->settings->getSettings();

        if (empty($settings['preload_primary_fonts']) || empty($settings['auto_apply_roles'])) {
            return [];
        }

        $catalog = $this->getLocalRuntimeCatalog();
        $roles = $this->settings->getAppliedRoles($catalog);
        $urls = [];

        foreach (
            [
                [
                    'family' => $roles['heading'],
                    'weight' => $this->resolvePrimaryRoleWeight($roles, 'heading', 700),
                ],
                [
                    'family' => $roles['body'],
                    'weight' => $this->resolvePrimaryRoleWeight($roles, 'body', 400),
                ],
            ] as $target
        ) {
            $face = $this->findBestPreloadFace(
                $catalog,
                $target['family'],
                (int) $target['weight']
            );

            if ($face === null) {
                continue;
            }

            $url = $this->getSameOriginWoff2Url($face);

            if ($url === '') {
                continue;
            }

            $urls[$url] = $url;
        }

        return array_values($urls);
    }

    /**
     * @return list<string>
     */
    public function getPreconnectOrigins(): array
    {
        $settings = $this->settings->getSettings();

        if (empty($settings['remote_connection_hints'])) {
            return [];
        }

        $origins = [];

        foreach ($this->getRuntimeFamilies() as $family) {
            $activeDelivery = $this->familyDelivery($family);
            $provider = strtolower(trim($this->deliveryStringValue($activeDelivery, 'provider')));
            $type = strtolower(trim($this->deliveryStringValue($activeDelivery, 'type')));

            if ($type === 'self_hosted') {
                continue;
            }

            $origin = match ($provider . ':' . $type) {
                'google:cdn' => 'https://fonts.googleapis.com',
                'bunny:cdn' => 'https://fonts.bunny.net',
                'adobe:adobe_hosted' => 'https://use.typekit.net',
                default => '',
            };

            if ($origin !== '') {
                $origins[$origin] = $origin;
            }
        }

        return array_values($origins);
    }

    /**
     * @param CatalogFamily $family
     */
    private function resolveFamilyFallback(array $family): string
    {
        $familyName = trim($this->familyStringValue($family, 'family'));

        if ($familyName === '') {
            return 'sans-serif';
        }

        $settings = $this->settings->getSettings();
        $savedFallbacks = is_array($settings['family_fallbacks'] ?? null) ? $settings['family_fallbacks'] : [];

        if (array_key_exists($familyName, $savedFallbacks) && is_scalar($savedFallbacks[$familyName])) {
            return FontUtils::sanitizeFallback((string) $savedFallbacks[$familyName]);
        }

        return FontUtils::defaultFallbackForCategory($this->familyStringValue($family, 'font_category'));
    }

    /**
     * @param CatalogMap $families
     * @return RuntimeFamilyList
     */
    private function filterFamiliesByPublishState(array $families, bool $includeLibraryOnly): array
    {
        $filtered = [];

        foreach ($families as $key => $family) {
            $publishState = $this->familyStringValue($family, 'publish_state', 'published');

            if (!$includeLibraryOnly && $publishState === 'library_only') {
                continue;
            }

            $filtered[$key] = $family;
        }

        return array_values($filtered);
    }

    /**
     * @param RuntimeFamilyList|CatalogMap $families
     * @return CatalogMap
     */
    private function buildFontFaceCatalog(array $families): array
    {
        $catalog = [];
        $settings = $this->settings->getSettings();
        $variableFontsEnabled = !empty($settings['variable_fonts_enabled']);

        foreach ($families as $family) {
            $delivery = $this->familyDelivery($family);
            $provider = strtolower(trim($this->deliveryStringValue($delivery, 'provider')));
            $type = strtolower(trim($this->deliveryStringValue($delivery, 'type')));
            $format = FontUtils::resolveProfileFormat($delivery);
            $deliveryId = $this->deliveryStringValue($delivery, 'id');
            $familyName = $this->familyStringValue($family, 'family');

            if ($deliveryId === '' || $provider === 'adobe' || $type !== 'self_hosted') {
                continue;
            }

            if (!$variableFontsEnabled && $format === 'variable') {
                continue;
            }

            $catalog[$this->catalogDeliveryKey($familyName, $deliveryId)] = [
                'family' => $familyName,
                'slug' => $this->familyStringValue($family, 'slug'),
                'publish_state' => $this->familyStringValue($family, 'publish_state', 'published'),
                'active_delivery_id' => $this->familyStringValue($family, 'active_delivery_id'),
                'delivery_id' => $deliveryId,
                'active_delivery' => $delivery,
                'available_deliveries' => $this->familyListValue($family, 'available_deliveries'),
                'delivery_badges' => $this->familyListValue($family, 'delivery_badges'),
                'delivery_filter_tokens' => $this->familyListValue($family, 'delivery_filter_tokens'),
                'sources' => [$provider !== '' ? $provider : 'local'],
                'faces' => $this->deliveryFaces($delivery),
            ];
        }

        return $catalog;
    }

    /**
     * @param RuntimeFamilyList|CatalogMap $families
     * @return list<StylesheetDescriptor>
     */
    private function buildExternalStylesheets(array $families, string $displayOverride = ''): array
    {
        $stylesheets = [];

        foreach ($families as $family) {
            $activeDelivery = $this->familyDelivery($family);
            $stylesheet = $this->buildStylesheetDescriptor(
                $this->familyStringValue($family, 'family'),
                $this->familyStringValue($family, 'slug'),
                $activeDelivery,
                $displayOverride
            );

            if ($stylesheet === null) {
                continue;
            }

            $stylesheets[$stylesheet['url']] = $stylesheet;
        }

        return array_values($stylesheets);
    }

    /**
     * @param array<string, mixed> $delivery
     * @return StylesheetDescriptor|null
     */
    private function buildStylesheetDescriptor(string $familyName, string $familySlug, array $delivery, string $displayOverride = ''): ?array
    {
        $provider = strtolower(trim($this->deliveryStringValue($delivery, 'provider')));
        $type = strtolower(trim($this->deliveryStringValue($delivery, 'type')));

        if ($provider === '' || $type === '' || $type === 'self_hosted') {
            return null;
        }

        $display = $this->runtimeStylesheetDisplay($familyName, $provider, $type, $displayOverride);
        $variants = $this->normalizeVariantTokenList($delivery['variants'] ?? []);

        $url = match ($provider . ':' . $type) {
            'google:cdn' => $this->google->buildCssUrl(
                $familyName,
                $variants,
                $display,
                ['faces' => $this->deliveryFaces($delivery)]
            ),
            'bunny:cdn' => $this->bunny->buildCssUrl($familyName, $variants, $display),
            'adobe:adobe_hosted' => $this->adobeStylesheetUrl($delivery),
            default => '',
        };

        if ($url === '') {
            return null;
        }

        return [
            'handle' => 'tasty-fonts-' . FontUtils::slugify($provider . '-' . $familySlug . '-' . $type),
            'url' => $url,
            'provider' => $provider,
            'type' => $type,
        ];
    }

    /**
     * @param array<string, mixed> $delivery
     */
    private function adobeStylesheetUrl(array $delivery): string
    {
        $projectId = sanitize_text_field($this->deliveryMetaStringValue($delivery, 'project_id', $this->adobe->getProjectId()));

        return $projectId === '' ? '' : $this->adobe->getStylesheetUrl($projectId);
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

            $normalized[] = (string) $variant;
        }

        return FontUtils::normalizeVariantTokens($normalized);
    }

    private function effectiveFontDisplay(string $familyName, string $displayOverride = ''): string
    {
        if ($displayOverride !== '') {
            return $displayOverride;
        }

        $settings = $this->settings->getSettings();
        $saved = $this->settings->getFamilyFontDisplay($familyName);

        return $saved !== '' ? $saved : $this->settingsStringValue($settings, 'font_display', 'swap');
    }

    private function runtimeStylesheetDisplay(string $familyName, string $provider, string $type, string $displayOverride = ''): string
    {
        $display = $this->effectiveFontDisplay($familyName, $displayOverride);

        if ($displayOverride !== '' || $display !== 'optional') {
            return $display;
        }

        return in_array($provider . ':' . $type, ['google:cdn', 'bunny:cdn'], true)
            ? 'swap'
            : $display;
    }

    /**
     * @param array<string, mixed> $delivery
     */
    private function isSelfHostedDelivery(array $delivery): bool
    {
        return strtolower(trim($this->deliveryStringValue($delivery, 'type'))) === 'self_hosted';
    }

    /**
     * @param CatalogFamily $family
     * @return list<array<string, mixed>>
     */
    private function buildEditorFontFaceList(array $family, bool $variableFontsEnabled): array
    {
        $delivery = $this->familyDelivery($family);
        $settings = $this->settings->getSettings();

        if (!$this->isSelfHostedDelivery($delivery)) {
            return [];
        }

        $faces = [];

        foreach ($this->deliveryFaces($delivery) as $face) {
            if (!$variableFontsEnabled && FontUtils::faceIsVariable($face)) {
                continue;
            }

            $src = $this->editorFontFaceSources($face);

            if ($src === []) {
                continue;
            }

            $entry = [
                'fontFamily' => '"' . FontUtils::escapeFontFamily($this->familyStringValue($family, 'family')) . '"',
                'fontStyle' => FontUtils::normalizeStyle($this->faceStringValue($face, 'style', 'normal')),
                'fontWeight' => $this->editorFontFaceWeight($this->faceStringValue($face, 'weight', '400'), $this->faceAxes($face)),
                'src' => $src,
            ];

            $unicodeRange = FontUtils::resolveFaceUnicodeRange($face, $settings);

            if ($unicodeRange !== '') {
                $entry['unicodeRange'] = $unicodeRange;
            }

            if ($variableFontsEnabled) {
                $variationSettings = FontUtils::buildFontVariationSettings(
                    FontUtils::faceLevelVariationDefaults($this->faceVariationDefaults($face), $this->faceAxes($face))
                );

                if ($variationSettings !== 'normal') {
                    $entry['fontVariationSettings'] = $variationSettings;
                }
            }

            $faces[] = $entry;
        }

        return $faces;
    }

    /**
     * @param CatalogFace $face
     * @return list<string>
     */
    private function editorFontFaceSources(array $face): array
    {
        $sources = [];
        $files = $this->faceFiles($face);

        foreach (['woff2', 'woff', 'ttf', 'otf'] as $format) {
            $value = $files[$format] ?? null;

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $sources[] = $value;
        }

        return $sources;
    }

    /**
     * @param array<string, mixed> $axes
     */
    private function editorFontFaceWeight(string $weight, array $axes = []): string
    {
        $normalizedAxes = FontUtils::normalizeAxesMap($axes);

        if (isset($normalizedAxes['WGHT']['min'], $normalizedAxes['WGHT']['max'])) {
            return (string) $normalizedAxes['WGHT']['min'] . ' ' . (string) $normalizedAxes['WGHT']['max'];
        }

        $normalizedWeight = FontUtils::normalizeWeight($weight);

        if (preg_match('/^(\d{1,4})\.\.(\d{1,4})$/', $normalizedWeight, $matches) === 1) {
            return $matches[1] . ' ' . $matches[2];
        }

        return $normalizedWeight;
    }

    /**
     * @param CatalogMap $catalog
     * @return CatalogFace|null
     */
    private function findBestPreloadFace(array $catalog, string $familyName, int $targetWeight): ?array
    {
        $family = $this->findCatalogFamily($catalog, $familyName);

        if ($family === null) {
            return null;
        }

        $bestFace = null;
        $bestScore = null;

        foreach ($this->familyFaces($family) as $face) {
            if (
                FontUtils::normalizeStyle($this->faceStringValue($face, 'style', 'normal')) !== 'normal'
                || $this->faceFileValue($face, 'woff2') === ''
                || $this->getSameOriginWoff2Url($face) === ''
            ) {
                continue;
            }

            $score = $this->preloadFaceScore($face, $targetWeight);

            if ($bestScore !== null && $this->comparePreloadFaceScores($score, $bestScore) >= 0) {
                continue;
            }

            $bestFace = $face;
            $bestScore = $score;
        }

        return $bestFace;
    }

    /**
     * @param CatalogMap $catalog
     * @return CatalogFamily|null
     */
    private function findCatalogFamily(array $catalog, string $familyName): ?array
    {
        if ($familyName === '') {
            return null;
        }

        foreach ($catalog as $family) {
            if ($this->familyStringValue($family, 'family') !== $familyName) {
                continue;
            }

            return $family;
        }

        return null;
    }

    /**
     * @param CatalogFace $face
     */
    private function getSameOriginWoff2Url(array $face): string
    {
        $url = trim($this->faceFileValue($face, 'woff2'));

        if ($url === '' || !$this->isSameOriginFontUrl($url)) {
            return '';
        }

        return $url;
    }

    /**
     * @param RoleSet $roles
     */
    private function resolvePrimaryRoleWeight(array $roles, string $roleKey, int $default): int
    {
        $axes = FontUtils::normalizeVariationDefaults($roles[$roleKey . '_axes'] ?? []);
        $axisWeight = trim((string) ($axes['WGHT'] ?? ''));

        if (preg_match('/^\d{1,4}$/', $axisWeight) === 1) {
            return (int) $axisWeight;
        }

        $weight = trim($this->roleStringValue($roles, $roleKey . '_weight'));

        if ($weight === 'normal') {
            return 400;
        }

        if ($weight === 'bold') {
            return 700;
        }

        return preg_match('/^\d{1,4}$/', $weight) === 1 ? (int) $weight : $default;
    }

    private function isSameOriginFontUrl(string $url): bool
    {
        $host = FontUtils::scalarStringValue(parse_url($url, PHP_URL_HOST) ?: '');

        if ($host === '') {
            return !str_starts_with($url, '//');
        }

        $uploads = FontUtils::normalizeStringKeyedMap(wp_get_upload_dir());
        $uploadBaseUrl = FontUtils::scalarStringValue($uploads['baseurl'] ?? '');
        $uploadHost = FontUtils::scalarStringValue(parse_url($uploadBaseUrl, PHP_URL_HOST) ?: '');

        if ($uploadHost === '') {
            return false;
        }

        return strtolower($host) === strtolower($uploadHost);
    }

    /**
     * @param CatalogFace $face
     * @return PreloadFaceScore
     */
    private function preloadFaceScore(array $face, int $targetWeight): array
    {
        $weight = FontUtils::normalizeWeight($this->faceStringValue($face, 'weight', '400'));
        $weightRange = FontUtils::weightRangeFromFace($face);
        $isVariable = $weightRange !== null;
        $distance = $weightRange !== null
            ? $this->weightDistanceFromRange($weightRange[0], $weightRange[1], $targetWeight)
            : $this->weightDistanceFromTarget($weight, $targetWeight);
        $referenceWeight = $weightRange !== null
            ? max($weightRange[0], min($targetWeight, $weightRange[1]))
            : $this->weightReferenceValue($weight, $targetWeight);

        return [$distance, $isVariable ? 1 : 0, $referenceWeight];
    }

    /**
     * @param PreloadFaceScore $left
     * @param PreloadFaceScore $right
     */
    private function comparePreloadFaceScores(array $left, array $right): int
    {
        foreach ([0, 1, 2] as $index) {
            $comparison = $left[$index] <=> $right[$index];

            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }

    private function weightDistanceFromTarget(string $weight, int $targetWeight): int
    {
        if (preg_match('/^(\d{1,4})\.\.(\d{1,4})$/', $weight, $matches) === 1) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];

            if ($targetWeight < $start) {
                return $start - $targetWeight;
            }

            if ($targetWeight > $end) {
                return $targetWeight - $end;
            }

            return 0;
        }

        return abs(FontUtils::weightSortValue($weight) - $targetWeight);
    }

    private function weightReferenceValue(string $weight, int $targetWeight): int
    {
        if (preg_match('/^(\d{1,4})\.\.(\d{1,4})$/', $weight, $matches) === 1) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];

            return max($start, min($targetWeight, $end));
        }

        return FontUtils::weightSortValue($weight);
    }

    private function weightDistanceFromRange(int $start, int $end, int $targetWeight): int
    {
        if ($targetWeight < $start) {
            return $start - $targetWeight;
        }

        if ($targetWeight > $end) {
            return $targetWeight - $end;
        }

        return 0;
    }

    private function catalogDeliveryKey(string $familyName, string $deliveryId): string
    {
        return FontUtils::slugify($familyName) . '::' . FontUtils::slugify($deliveryId);
    }

    /**
     * @param CatalogFamily $family
     * @return array<string, mixed>
     */
    private function familyDelivery(array $family): array
    {
        $delivery = $family['active_delivery'] ?? null;

        return FontUtils::normalizeStringKeyedMap($delivery);
    }

    /**
     * @param CatalogFamily $family
     * @return list<CatalogFace>
     */
    private function familyFaces(array $family): array
    {
        $faces = $family['faces'] ?? null;

        return FontUtils::normalizeFaceList($faces);
    }

    /**
     * @param CatalogFamily $family
     * @return list<mixed>
     */
    private function familyListValue(array $family, string $key): array
    {
        $value = $family[$key] ?? null;

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param CatalogFamily $family
     */
    private function familyStringValue(array $family, string $key, string $default = ''): string
    {
        $value = $family[$key] ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $delivery
     */
    private function deliveryStringValue(array $delivery, string $key, string $default = ''): string
    {
        $value = $delivery[$key] ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $delivery
     */
    private function deliveryMetaStringValue(array $delivery, string $key, string $default = ''): string
    {
        $meta = $delivery['meta'] ?? null;

        if (!is_array($meta)) {
            return $default;
        }

        $value = $meta[$key] ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $delivery
     * @return list<CatalogFace>
     */
    private function deliveryFaces(array $delivery): array
    {
        $faces = $delivery['faces'] ?? null;

        return FontUtils::normalizeFaceList($faces);
    }

    /**
     * @param CatalogFace $face
     */
    private function faceStringValue(array $face, string $key, string $default = ''): string
    {
        $value = $face[$key] ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param CatalogFace $face
     * @return array<string, string>
     */
    private function faceFiles(array $face): array
    {
        $files = $face['files'] ?? null;

        if (!is_array($files)) {
            return [];
        }

        $normalized = [];

        foreach ($files as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }

            $normalized[$key] = (string) $value;
        }

        return $normalized;
    }

    /**
     * @param CatalogFace $face
     */
    private function faceFileValue(array $face, string $key): string
    {
        return $this->faceFiles($face)[$key] ?? '';
    }

    /**
     * @param CatalogFace $face
     * @return AxesMap
     */
    private function faceAxes(array $face): array
    {
        $axes = $face['axes'] ?? null;

        if (!is_array($axes)) {
            return [];
        }

        return FontUtils::normalizeAxesMap($axes);
    }

    /**
     * @param CatalogFace $face
     * @return VariationDefaults
     */
    private function faceVariationDefaults(array $face): array
    {
        $defaults = $face['variation_defaults'] ?? null;

        if (!is_array($defaults)) {
            return [];
        }

        return FontUtils::normalizeVariationDefaults($defaults);
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function settingsStringValue(array $settings, string $key, string $default = ''): string
    {
        $value = $settings[$key] ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param array<int|string, mixed> $roles
     */
    private function roleStringValue(array $roles, string $key): string
    {
        $value = $roles[$key] ?? '';

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return '';
    }
}
