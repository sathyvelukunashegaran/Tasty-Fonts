<?php

declare(strict_types=1);

namespace TastyFonts\Google;

defined('ABSPATH') || exit;

use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\TransientKey;
use WP_Error;

/**
 * @phpstan-import-type AdobeProjectStatus from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type GoogleApiKeyData from \TastyFonts\Repository\SettingsRepository
 * @phpstan-type CatalogItem array<string, mixed>
 * @phpstan-type CatalogIndex array<string, CatalogItem>
 * @phpstan-type MetadataIndex array<string, array<string, mixed>>
 * @phpstan-type SearchResultItem array<string, mixed>
 * @phpstan-type ApiStatus array<string, mixed>
 * @phpstan-type VariableCssRequest array{axis_list: string, rows: list<string>}
 * @phpstan-type ImportFormatMap array<string, array<string, bool|string>>
 * @phpstan-type RemoteGetArgs array{timeout?: float|int, headers?: array<string, string>}
 */
final class GoogleFontsClient
{
    public const ACTION_REVALIDATE_API_KEY = 'tasty_fonts_revalidate_google_api_key';
    public const TRANSIENT_CATALOG = 'tasty_fonts_google_catalog_v2';
    public const TRANSIENT_METADATA = 'tasty_fonts_google_metadata_v1';
    public const TRANSIENT_API_KEY_REVALIDATION_QUEUED = 'tasty_fonts_google_api_key_revalidation_queued_v1';
    private const CATALOG_TTL = 12 * HOUR_IN_SECONDS;
    private const METADATA_TTL = 12 * HOUR_IN_SECONDS;
    private const API_KEY_REVALIDATION_INTERVAL = DAY_IN_SECONDS;
    private const API_KEY_REVALIDATION_QUEUE_TTL = HOUR_IN_SECONDS;
    private const METADATA_URL = 'https://fonts.google.com/metadata/fonts';
    private const REQUEST_TIMEOUT = 20;

    /** @var CatalogIndex|null */
    private ?array $catalogIndex = null;
    /** @var list<CatalogItem>|null */
    private ?array $catalogItems = null;
    /** @var MetadataIndex|null */
    private ?array $metadataIndex = null;

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function hasApiKey(): bool
    {
        return $this->settings->hasGoogleApiKey();
    }

    public function canSearch(): bool
    {
        $status = $this->getApiKeyStatus();

        return $this->hasApiKey() && ($status['state'] ?? 'empty') === 'valid';
    }

    /**
     * @return ApiStatus
     */
    public function getApiKeyStatus(): array
    {
        $status = $this->settings->getGoogleApiKeyStatus();
        $this->maybeScheduleApiKeyRevalidation($status);

        return $status;
    }

    public function clearCatalogCache(): void
    {
        $this->catalogIndex = null;
        $this->catalogItems = null;
        $this->metadataIndex = null;
        delete_transient(TransientKey::forSite(self::TRANSIENT_CATALOG));
        delete_transient(TransientKey::forSite(self::TRANSIENT_METADATA));
    }

    /**
     * @return ApiStatus
     */
    public function validateApiKey(string $apiKey): array
    {
        $apiKey = trim($apiKey);

        if ($apiKey === '') {
            return [
                'state' => 'empty',
                'message' => '',
            ];
        }

        $response = $this->remoteGet($this->buildCatalogRequestUrl($apiKey), ['timeout' => self::REQUEST_TIMEOUT]);

        if (is_wp_error($response)) {
            return [
                'state' => 'unknown',
                'message' => __('The Google Fonts API key could not be validated right now. Save it again to retry.', 'tasty-fonts'),
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($status === 200 && is_array($body) && isset($body['items']) && is_array($body['items'])) {
            return [
                'state' => 'valid',
                'message' => __('Google Fonts API key validated. Live search is ready.', 'tasty-fonts'),
            ];
        }

        $errorMessage = '';

        if (is_array($body)) {
            $errorMessage = $this->stringValue($this->arrayValue($body, 'error'), 'message');
        }

        if ($status === 400 || $status === 403) {
            return [
                'state' => 'invalid',
                'message' => $errorMessage !== ''
                    ? sprintf(
                        __('Google Fonts API key is invalid: %s', 'tasty-fonts'),
                        $errorMessage
                    )
                    : __('Google Fonts API key is invalid or rejected by Google.', 'tasty-fonts'),
            ];
        }

        return [
            'state' => 'unknown',
            'message' => __('Google Fonts API key could not be verified because Google returned an unexpected response.', 'tasty-fonts'),
        ];
    }

    /**
     * @return AdobeProjectStatus
     */
    public function revalidateStoredApiKeyStatus(): array
    {
        delete_transient(TransientKey::forSite(self::TRANSIENT_API_KEY_REVALIDATION_QUEUED));

        $apiKey = $this->getApiKey();

        if ($apiKey === '') {
            return $this->settings->saveGoogleApiKeyStatus('empty');
        }

        $validation = $this->validateApiKey($apiKey);

        return $this->settings->saveGoogleApiKeyStatus(
            $this->stringValue($validation, 'state', 'unknown'),
            $this->stringValue($validation, 'message')
        );
    }

    /**
     * @return list<SearchResultItem>
     */
    public function searchFamilies(string $query, int $limit = 20, int $offset = 0): array
    {
        $query = strtolower(trim($query));
        $offset = max(0, $offset);

        if ($query === '' || !$this->canSearch()) {
            return [];
        }

        $results = [];

        foreach ($this->fetchCatalogIndex() as $slug => $item) {
            $family = $this->stringValue($item, 'family');

            if (!$this->matchesSearchQuery($family, $query)) {
                continue;
            }

            $results[] = $this->normalizeSearchResultItem((string) $slug, $item);
        }

        usort(
            $results,
            static function (array $left, array $right) use ($query): int {
                $leftFamily = is_scalar($left['family'] ?? null) ? (string) $left['family'] : '';
                $rightFamily = is_scalar($right['family'] ?? null) ? (string) $right['family'] : '';
                $leftStarts = str_starts_with(strtolower($leftFamily), $query);
                $rightStarts = str_starts_with(strtolower($rightFamily), $query);

                if ($leftStarts !== $rightStarts) {
                    return $leftStarts ? -1 : 1;
                }

                return strcmp($leftFamily, $rightFamily);
            }
        );

        return array_slice($results, $offset, $limit);
    }

    /**
     * @return CatalogItem|null
     */
    public function getFamily(string $familyName): ?array
    {
        if (!$this->canSearch()) {
            return null;
        }

        foreach ($this->fetchCatalogItems() as $item) {
            if ($this->isMatchingFamilyName($this->stringValue($item, 'family'), $familyName)) {
                return $this->normalizeCatalogItem($item);
            }
        }

        return null;
    }

    /**
     * @param CatalogItem $familyMetadata
     * @param list<string> $variants
     */
    public function fetchCss(
        string $familyName,
        array $variants,
        string $display = 'swap',
        array $familyMetadata = [],
        string $formatMode = 'static'
    ): string|WP_Error
    {
        $url = $this->buildCssUrl($familyName, $variants, $display, $familyMetadata, $formatMode);

        $response = $this->remoteGet(
            $url,
            [
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => [
                    'Accept' => 'text/css,*/*;q=0.1',
                    'User-Agent' => $this->modernUserAgent(),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            return new WP_Error(
                'tasty_fonts_google_css_fetch_failed',
                sprintf(__('Google Fonts CSS request failed with status %d.', 'tasty-fonts'), $status)
            );
        }

        $body = wp_remote_retrieve_body($response);

        if (trim($body) === '') {
            return new WP_Error(
                'tasty_fonts_google_css_empty',
                __('Google Fonts returned an empty CSS response.', 'tasty-fonts')
            );
        }

        return $body;
    }

    /**
     * @param list<string> $variants
     * @param CatalogItem $familyMetadata
     */
    public function buildCssUrl(
        string $familyName,
        array $variants,
        string $display = 'swap',
        array $familyMetadata = [],
        string $formatMode = 'static'
    ): string
    {
        $familyQuery = str_replace('%20', '+', rawurlencode($familyName));
        $url = 'https://fonts.googleapis.com/css2?family=' . $familyQuery;
        $variableAxes = $this->buildVariableCssRequest($variants, $familyMetadata, $formatMode);

        if ($variableAxes !== null) {
            $url .= ':' . $variableAxes['axis_list'] . '@' . implode(';', $variableAxes['rows']);
        } else {
            $axes = $this->buildCssAxes($variants);

            if ($axes !== []) {
                $url .= ':ital,wght@' . implode(';', $axes);
            }
        }

        return $url . '&display=' . rawurlencode($this->sanitizeDisplay($display));
    }

    /**
     * @return CatalogIndex
     */
    private function fetchCatalogIndex(): array
    {
        if (is_array($this->catalogIndex)) {
            return $this->catalogIndex;
        }

        $cached = get_transient(TransientKey::forSite(self::TRANSIENT_CATALOG));

        if ($this->isCatalogIndex($cached)) {
            $this->catalogIndex = $this->normalizedCatalogIndex($cached);
            set_transient(TransientKey::forSite(self::TRANSIENT_CATALOG), $this->catalogIndex, self::CATALOG_TTL);

            return $this->catalogIndex;
        }

        $items = $this->fetchRemoteCatalogItems();

        if ($items === []) {
            return [];
        }

        $this->catalogItems = $items;
        $this->catalogIndex = $this->buildCatalogIndex($items);
        set_transient(TransientKey::forSite(self::TRANSIENT_CATALOG), $this->catalogIndex, self::CATALOG_TTL);

        return $this->catalogIndex;
    }

    /**
     * @return list<CatalogItem>
     */
    private function fetchCatalogItems(): array
    {
        if (is_array($this->catalogItems)) {
            return $this->catalogItems;
        }

        $items = $this->fetchRemoteCatalogItems();

        if ($items === []) {
            return [];
        }

        $this->catalogItems = $items;

        if (!is_array($this->catalogIndex)) {
            $this->catalogIndex = $this->buildCatalogIndex($items);
            set_transient(TransientKey::forSite(self::TRANSIENT_CATALOG), $this->catalogIndex, self::CATALOG_TTL);
        }

        return $this->catalogItems;
    }

    /**
     * @param CatalogItem $item
     * @return CatalogItem
     */
    private function normalizeCatalogItem(array $item): array
    {
        $item = $this->enrichCatalogItemWithMetadata($item);
        $axes = $this->normalizeCatalogAxes($this->arrayValue($item, 'axes'));
        $formats = $this->buildImportOptions($axes);

        return [
            'family' => $this->stringValue($item, 'family'),
            'category' => $this->stringValue($item, 'category'),
            'variants' => $this->normalizeVariantTokenList($item['variants'] ?? []),
            'subsets' => array_values(array_filter((array) ($item['subsets'] ?? []), 'is_string')),
            'version' => $this->stringValue($item, 'version'),
            'lastModified' => $this->stringValue($item, 'lastModified'),
            'is_variable' => $axes !== [],
            'axes' => $axes,
            'formats' => $formats,
            'import_options' => $formats,
        ];
    }

    /**
     * @param CatalogItem $item
     * @return SearchResultItem
     */
    private function normalizeSearchResultItem(string $slug, array $item): array
    {
        $item = $this->enrichCatalogItemWithMetadata($item);
        $axes = FontUtils::normalizeAxesMap($this->arrayValue($item, 'axes'));
        $formats = $this->buildImportOptions($axes);

        return [
            'family' => $this->stringValue($item, 'family'),
            'slug' => $slug,
            'category' => $this->stringValue($item, 'category'),
            'variants_count' => max(0, $this->intValue($item, 'variants_count')),
            'variants' => $this->normalizeVariantTokenList($item['variants'] ?? []),
            'is_variable' => $axes !== [] || !empty($item['is_variable']),
            'axes' => $axes,
            'formats' => $formats,
            'import_options' => $formats,
        ];
    }

    private function modernUserAgent(): string
    {
        return FontUtils::MODERN_USER_AGENT;
    }

    /**
     * @param RemoteGetArgs $args
     * @return array<string, mixed>|WP_Error
     */
    private function remoteGet(string $url, array $args = []): array|WP_Error
    {
        $filteredArgs = apply_filters('tasty_fonts_http_request_args', $args, $url);

        return wp_remote_get($url, $this->normalizeRequestArgs($filteredArgs, $args));
    }

    /**
     * @param list<string> $variants
     * @return list<string>
     */
    private function buildCssAxes(array $variants): array
    {
        return FontUtils::buildHostedCssAxes($variants);
    }

    private function sanitizeDisplay(string $display): string
    {
        return FontUtils::sanitizeHostedCssDisplay($display);
    }

    /**
     * @param array<string, mixed> $axes
     * @return ImportFormatMap
     */
    private function buildImportOptions(array $axes): array
    {
        $formats = [
            'static' => [
                'label' => 'Static',
                'available' => true,
                'source_only' => false,
            ],
        ];

        if ($axes !== []) {
            $formats['variable'] = [
                'label' => 'Variable',
                'available' => true,
                'source_only' => false,
            ];
        }

        return $formats;
    }

    private function getApiKey(): string
    {
        return $this->stringValue($this->settings->getSettings(), 'google_api_key');
    }

    /**
     * @param ApiStatus $status
     */
    private function maybeScheduleApiKeyRevalidation(array $status): void
    {
        if (!$this->hasApiKey()) {
            return;
        }

        $checkedAt = $this->intValue($status, 'checked_at');

        if ($checkedAt > 0 && (time() - $checkedAt) < self::API_KEY_REVALIDATION_INTERVAL) {
            return;
        }

        if (get_transient(TransientKey::forSite(self::TRANSIENT_API_KEY_REVALIDATION_QUEUED)) !== false) {
            return;
        }

        set_transient(
            TransientKey::forSite(self::TRANSIENT_API_KEY_REVALIDATION_QUEUED),
            ['queued' => 1],
            self::API_KEY_REVALIDATION_QUEUE_TTL
        );

        $scheduled = wp_schedule_single_event(time(), self::ACTION_REVALIDATE_API_KEY);

        if ($scheduled === false) {
            delete_transient(TransientKey::forSite(self::TRANSIENT_API_KEY_REVALIDATION_QUEUED));
        }
    }

    /**
     * @return list<CatalogItem>
     */
    private function fetchRemoteCatalogItems(): array
    {
        $apiKey = $this->getApiKey();

        if ($apiKey === '') {
            return [];
        }

        $response = $this->remoteGet($this->buildCatalogRequestUrl($apiKey), ['timeout' => self::REQUEST_TIMEOUT]);

        if (is_wp_error($response)) {
            return [];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($status !== 200 || !is_array($body) || !isset($body['items']) || !is_array($body['items'])) {
            return [];
        }

        return $this->normalizeCatalogItemList($body['items']);
    }

    /**
     * @param list<CatalogItem> $items
     * @return CatalogIndex
     */
    private function buildCatalogIndex(array $items): array
    {
        $index = [];
        $metadataIndex = $this->fetchMetadataIndex();

        foreach ($items as $item) {
            $item = $this->enrichCatalogItemWithMetadata($item, $metadataIndex);
            $family = $this->stringValue($item, 'family');
            $slug = FontUtils::slugify($family);
            $axes = $this->normalizeCatalogAxes($this->arrayValue($item, 'axes'));

            if ($family === '' || $slug === '') {
                continue;
            }

            $index[$slug] = [
                'family' => $family,
                'category' => $this->stringValue($item, 'category'),
                'variants_count' => count($this->normalizeVariantTokenList($item['variants'] ?? [])),
                'variants' => $this->normalizeVariantTokenList($item['variants'] ?? []),
                'is_variable' => $axes !== [],
                'axes' => $axes,
            ];
        }

        return $index;
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

    /**
     * @param mixed $items
     * @return list<CatalogItem>
     */
    private function normalizeCatalogItemList(mixed $items): array
    {
        return $this->itemList($items);
    }

    /**
     * @param mixed $items
     * @return list<array<string, mixed>>
     */
    private function itemList(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalized[] = FontUtils::normalizeStringKeyedMap($item);
        }

        return $normalized;
    }

    /**
     * @param mixed $cached
     * @return CatalogIndex
     */
    private function normalizedCatalogIndex(mixed $cached): array
    {
        if (!is_array($cached)) {
            return [];
        }

        $normalized = [];

        foreach ($cached as $slug => $item) {
            if (!is_string($slug) || !is_array($item)) {
                continue;
            }

            $normalized[$slug] = FontUtils::normalizeStringKeyedMap($item);
        }

        return $normalized;
    }

    private function isCatalogIndex(mixed $cached): bool
    {
        if (!is_array($cached) || $cached === []) {
            return false;
        }

        foreach ($cached as $slug => $item) {
            if (
                !is_string($slug)
                || !is_array($item)
                || !array_key_exists('family', $item)
                || !array_key_exists('category', $item)
                || !array_key_exists('variants_count', $item)
                || !array_key_exists('variants', $item)
                || !array_key_exists('is_variable', $item)
                || !array_key_exists('axes', $item)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return MetadataIndex
     */
    private function fetchMetadataIndex(): array
    {
        if (is_array($this->metadataIndex)) {
            return $this->metadataIndex;
        }

        $cached = get_transient(TransientKey::forSite(self::TRANSIENT_METADATA));

        if ($this->isMetadataIndex($cached)) {
            $this->metadataIndex = $this->normalizedMetadataIndex($cached);

            return $this->metadataIndex;
        }

        $response = $this->remoteGet(
            self::METADATA_URL,
            [
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => [
                    'Accept' => 'application/json,text/plain,*/*;q=0.1',
                    'User-Agent' => $this->modernUserAgent(),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return [];
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $decoded = $this->decodeMetadataPayload((string) wp_remote_retrieve_body($response));
        $familyMetadata = $this->itemList($decoded['familyMetadataList'] ?? []);
        $index = [];

        foreach ($familyMetadata as $item) {
            $family = $this->stringValue($item, 'family');
            $slug = FontUtils::slugify($family);
            $axes = $this->normalizeCatalogAxes($this->arrayValue($item, 'axes'));

            if ($family === '' || $slug === '') {
                continue;
            }

            $index[$slug] = [
                'family' => $family,
                'axes' => $axes,
            ];
        }

        if ($index !== []) {
            $this->metadataIndex = $index;
            set_transient(TransientKey::forSite(self::TRANSIENT_METADATA), $this->metadataIndex, self::METADATA_TTL);
        }

        return $this->metadataIndex ?? [];
    }

    /**
     * @param mixed $cached
     * @return MetadataIndex
     */
    private function normalizedMetadataIndex(mixed $cached): array
    {
        if (!is_array($cached)) {
            return [];
        }

        $normalized = [];

        foreach ($cached as $slug => $item) {
            if (!is_string($slug) || !is_array($item)) {
                continue;
            }

            $normalized[$slug] = FontUtils::normalizeStringKeyedMap($item);
        }

        return $normalized;
    }

    private function isMetadataIndex(mixed $cached): bool
    {
        if (!is_array($cached) || $cached === []) {
            return false;
        }

        foreach ($cached as $slug => $item) {
            if (
                !is_string($slug)
                || !is_array($item)
                || !array_key_exists('family', $item)
                || !array_key_exists('axes', $item)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $maybeArgs
     * @param RemoteGetArgs $fallback
     * @return RemoteGetArgs
     */
    private function normalizeRequestArgs(mixed $maybeArgs, array $fallback): array
    {
        $source = is_array($maybeArgs) ? $maybeArgs : $fallback;
        $normalized = [];
        $timeout = $source['timeout'] ?? $fallback['timeout'] ?? null;

        if (is_int($timeout) || is_float($timeout)) {
            $normalized['timeout'] = $timeout;
        }

        $headers = $this->stringMap($source['headers'] ?? ($fallback['headers'] ?? []));

        if ($headers !== []) {
            $normalized['headers'] = $headers;
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        return FontUtils::normalizeStringMap($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMetadataPayload(string $payload): array
    {
        $payload = ltrim($payload);

        if (str_starts_with($payload, ")]}'")) {
            $newlinePosition = strpos($payload, "\n");

            if ($newlinePosition === false) {
                return [];
            }

            $payload = substr($payload, $newlinePosition + 1);
        }

        $decoded = json_decode($payload, true);

        return FontUtils::normalizeStringKeyedMap($decoded);
    }

    /**
     * @param CatalogItem $item
     * @param MetadataIndex|null $metadataIndex
     * @return CatalogItem
     */
    private function enrichCatalogItemWithMetadata(array $item, ?array $metadataIndex = null): array
    {
        $axes = $this->normalizeCatalogAxes($this->arrayValue($item, 'axes'));

        if ($axes !== []) {
            $item['axes'] = $axes;
            $item['is_variable'] = true;

            return $item;
        }

        $family = $this->stringValue($item, 'family');
        $slug = FontUtils::slugify($family);

        if ($family === '' || $slug === '') {
            $item['axes'] = [];
            $item['is_variable'] = false;

            return $item;
        }

        $metadataIndex = $metadataIndex ?? $this->fetchMetadataIndex();
        $metadata = $this->arrayValue($metadataIndex, $slug);
        $metadataAxes = $this->normalizeCatalogAxes($this->arrayValue($metadata, 'axes'));

        $item['axes'] = $metadataAxes;
        $item['is_variable'] = $metadataAxes !== [];

        return $item;
    }

    private function buildCatalogRequestUrl(string $apiKey): string
    {
        return 'https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key=' . rawurlencode($apiKey);
    }

    /**
     * @param array<int|string, mixed>|list<array<string, mixed>> $axes
     * @return array<string, array<string, float|int|string>>
     */
    private function normalizeCatalogAxes(array $axes): array
    {
        $normalizedHostedAxes = array_is_list($axes)
            ? FontUtils::normalizeHostedAxisList($axes)
            : [];

        if ($normalizedHostedAxes !== []) {
            return $normalizedHostedAxes;
        }

        return FontUtils::normalizeAxesMap($axes);
    }

    /**
     * @param list<string> $variants
     * @param CatalogItem $familyMetadata
     * @return VariableCssRequest|null
     */
    private function buildVariableCssRequest(array $variants, array $familyMetadata, string $formatMode = 'static'): ?array
    {
        if (strtolower(trim($formatMode)) !== 'variable') {
            return null;
        }

        $familyAxes = FontUtils::normalizeAxesMap((array) ($familyMetadata['axes'] ?? []));
        $faces = is_array($familyMetadata['faces'] ?? null) ? (array) $familyMetadata['faces'] : [];
        $hasVariableFaces = array_filter(
            $faces,
            static fn (mixed $face): bool => is_array($face) && !empty($face['is_variable'])
        ) !== [];

        if ($familyAxes === [] && !$hasVariableFaces) {
            return null;
        }

        $requestedStyles = [];

        foreach (FontUtils::normalizeVariantTokens($variants) as $variant) {
            $axis = FontUtils::googleVariantToAxis($variant);

            if ($axis === null) {
                continue;
            }

            $requestedStyles[FontUtils::normalizeStyle($axis['style'])] = true;
        }

        if ($requestedStyles === []) {
            $requestedStyles['normal'] = true;
        }

        $rows = [];

        foreach ($faces as $face) {
            if (!is_array($face) || empty($face['is_variable'])) {
                continue;
            }

            $style = FontUtils::normalizeStyle($this->stringValue($face, 'style', 'normal'));

            if (!isset($requestedStyles[$style])) {
                continue;
            }

            $rowAxes = FontUtils::normalizeAxesMap($this->arrayValue($face, 'axes'));
            $rows[$style] = [
                'style' => $style,
                'axes' => $rowAxes !== [] ? $rowAxes : $familyAxes,
            ];
        }

        if ($rows === []) {
            foreach (array_keys($requestedStyles) as $style) {
                $rows[$style] = [
                    'style' => $style,
                    'axes' => $familyAxes,
                ];
            }
        }

        $axesByTag = [];

        foreach ($rows as $row) {
            foreach ($row['axes'] as $tag => $definition) {
                $normalizedAxis = FontUtils::normalizeAxesMap([$tag => $definition]);
                $axesByTag[$tag] = $normalizedAxis[$tag] ?? null;
            }
        }

        $axesByTag = array_filter($axesByTag, 'is_array');

        if ($axesByTag === []) {
            return null;
        }

        $axisTags = array_keys($axesByTag);
        sort($axisTags, SORT_STRING);
        $includeItal = count($rows) > 1 || isset($rows['italic']);
        $axisList = $includeItal
            ? array_merge(['ital'], array_map([FontUtils::class, 'cssAxisTag'], $axisTags))
            : array_map([FontUtils::class, 'cssAxisTag'], $axisTags);
        $serializedRows = [];

        foreach ($rows as $row) {
            $values = [];

            if ($includeItal) {
                $values[] = $row['style'] === 'italic' ? '1' : '0';
            }

            foreach ($axisTags as $tag) {
                $definition = (array) ($row['axes'][$tag] ?? $axesByTag[$tag] ?? []);
                $values[] = $this->buildVariableAxisValue($definition);
            }

            $serializedRows[] = implode(',', $values);
        }

        $serializedRows = array_values(array_unique($serializedRows));

        return [
            'axis_list' => implode(',', $axisList),
            'rows' => $serializedRows,
        ];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function buildVariableAxisValue(array $definition): string
    {
        $min = FontUtils::normalizeAxisValue($definition['min'] ?? '');
        $default = FontUtils::normalizeAxisValue($definition['default'] ?? '');
        $max = FontUtils::normalizeAxisValue($definition['max'] ?? '');

        if ($min !== '' && $max !== '' && $min !== $max) {
            return $min . '..' . $max;
        }

        if ($default !== '') {
            return $default;
        }

        return $min !== '' ? $min : $max;
    }

    private function isMatchingFamilyName(string $candidate, string $familyName): bool
    {
        return strcasecmp($candidate, $familyName) === 0;
    }

    private function matchesSearchQuery(string $family, string $query): bool
    {
        return $family !== '' && str_contains(strtolower($family), $query);
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
     * @return array<int|string, mixed>
     */
    private function arrayValue(array $values, string $key): array
    {
        $value = $values[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function intValue(array $values, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $value = $values[$key];

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && $value !== '' && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }
}
