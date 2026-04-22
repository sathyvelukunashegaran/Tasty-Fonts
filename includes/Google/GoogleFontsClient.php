<?php

declare(strict_types=1);

namespace TastyFonts\Google;

defined('ABSPATH') || exit;

use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\TransientKey;
use WP_Error;

final class GoogleFontsClient
{
    public const ACTION_REVALIDATE_API_KEY = 'tasty_fonts_revalidate_google_api_key';
    public const TRANSIENT_CATALOG = 'tasty_fonts_google_catalog_v2';
    public const LEGACY_TRANSIENT_CATALOG = 'tasty_fonts_google_catalog_v1';
    public const TRANSIENT_METADATA = 'tasty_fonts_google_metadata_v1';
    public const TRANSIENT_API_KEY_REVALIDATION_QUEUED = 'tasty_fonts_google_api_key_revalidation_queued_v1';
    private const CATALOG_TTL = 12 * HOUR_IN_SECONDS;
    private const METADATA_TTL = 12 * HOUR_IN_SECONDS;
    private const API_KEY_REVALIDATION_INTERVAL = DAY_IN_SECONDS;
    private const API_KEY_REVALIDATION_QUEUE_TTL = HOUR_IN_SECONDS;
    private const METADATA_URL = 'https://fonts.google.com/metadata/fonts';
    private const REQUEST_TIMEOUT = 20;

    private ?array $catalogIndex = null;
    private ?array $catalogItems = null;
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
        delete_transient(TransientKey::forSite(self::LEGACY_TRANSIENT_CATALOG));
        delete_transient(TransientKey::forSite(self::TRANSIENT_METADATA));
    }

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
            $errorMessage = trim((string) ($body['error']['message'] ?? ''));
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

    public function revalidateStoredApiKeyStatus(): array
    {
        delete_transient(TransientKey::forSite(self::TRANSIENT_API_KEY_REVALIDATION_QUEUED));

        $apiKey = $this->getApiKey();

        if ($apiKey === '') {
            return $this->settings->saveGoogleApiKeyStatus('empty');
        }

        $validation = $this->validateApiKey($apiKey);

        return $this->settings->saveGoogleApiKeyStatus(
            (string) ($validation['state'] ?? 'unknown'),
            (string) ($validation['message'] ?? '')
        );
    }

    public function searchFamilies(string $query, int $limit = 20): array
    {
        $query = strtolower(trim($query));

        if ($query === '' || !$this->canSearch()) {
            return [];
        }

        $results = [];

        foreach ($this->fetchCatalogIndex() as $slug => $item) {
            $family = (string) ($item['family'] ?? '');

            if (!$this->matchesSearchQuery($family, $query)) {
                continue;
            }

            $results[] = $this->normalizeSearchResultItem((string) $slug, $item);
        }

        usort(
            $results,
            static function (array $left, array $right) use ($query): int {
                $leftStarts = str_starts_with(strtolower((string) $left['family']), $query);
                $rightStarts = str_starts_with(strtolower((string) $right['family']), $query);

                if ($leftStarts !== $rightStarts) {
                    return $leftStarts ? -1 : 1;
                }

                return strcmp((string) $left['family'], (string) $right['family']);
            }
        );

        return array_slice($results, 0, $limit);
    }

    public function getFamily(string $familyName): ?array
    {
        if (!$this->canSearch()) {
            return null;
        }

        foreach ($this->fetchCatalogItems() as $item) {
            if ($this->isMatchingFamilyName((string) ($item['family'] ?? ''), $familyName)) {
                return $this->normalizeCatalogItem($item);
            }
        }

        return null;
    }

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

    private function fetchCatalogIndex(): array
    {
        if (is_array($this->catalogIndex)) {
            return $this->catalogIndex;
        }

        $cached = get_transient(TransientKey::forSite(self::TRANSIENT_CATALOG));

        if ($this->isCatalogIndex($cached)) {
            $this->catalogIndex = $cached;

            return $this->catalogIndex;
        }

        if ($this->isLegacyCatalogItemsCache($cached)) {
            $this->catalogItems = $cached;
            $this->catalogIndex = $this->buildCatalogIndex($cached);
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

    private function normalizeCatalogItem(array $item): array
    {
        $item = $this->enrichCatalogItemWithMetadata($item);
        $axes = $this->normalizeCatalogAxes((array) ($item['axes'] ?? []));
        $formats = $this->buildImportOptions($axes);

        return [
            'family' => (string) ($item['family'] ?? ''),
            'category' => (string) ($item['category'] ?? ''),
            'variants' => FontUtils::normalizeVariantTokens((array) ($item['variants'] ?? [])),
            'subsets' => array_values(array_filter((array) ($item['subsets'] ?? []), 'is_string')),
            'version' => (string) ($item['version'] ?? ''),
            'lastModified' => (string) ($item['lastModified'] ?? ''),
            'is_variable' => $axes !== [],
            'axes' => $axes,
            'formats' => $formats,
            'import_options' => $formats,
        ];
    }

    private function normalizeSearchResultItem(string $slug, array $item): array
    {
        $item = $this->enrichCatalogItemWithMetadata($item);
        $axes = FontUtils::normalizeAxesMap((array) ($item['axes'] ?? []));
        $formats = $this->buildImportOptions($axes);

        return [
            'family' => (string) ($item['family'] ?? ''),
            'slug' => $slug,
            'category' => (string) ($item['category'] ?? ''),
            'variants_count' => max(0, (int) ($item['variants_count'] ?? 0)),
            'variants' => FontUtils::normalizeVariantTokens((array) ($item['variants'] ?? [])),
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

    private function remoteGet(string $url, array $args = []): mixed
    {
        $filteredArgs = apply_filters('tasty_fonts_http_request_args', $args, $url);

        return wp_remote_get($url, is_array($filteredArgs) ? $filteredArgs : $args);
    }

    private function buildCssAxes(array $variants): array
    {
        return FontUtils::buildHostedCssAxes($variants);
    }

    private function sanitizeDisplay(string $display): string
    {
        return FontUtils::sanitizeHostedCssDisplay($display);
    }

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
        return trim((string) $this->settings->getSettings()['google_api_key']);
    }

    private function maybeScheduleApiKeyRevalidation(array $status): void
    {
        if (!$this->hasApiKey()) {
            return;
        }

        $checkedAt = (int) ($status['checked_at'] ?? 0);

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

        return $body['items'];
    }

    private function buildCatalogIndex(array $items): array
    {
        $index = [];
        $metadataIndex = $this->fetchMetadataIndex();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item = $this->enrichCatalogItemWithMetadata($item, $metadataIndex);
            $family = trim((string) ($item['family'] ?? ''));
            $slug = FontUtils::slugify($family);
            $axes = $this->normalizeCatalogAxes((array) ($item['axes'] ?? []));

            if ($family === '' || $slug === '') {
                continue;
            }

            $index[$slug] = [
                'family' => $family,
                'category' => (string) ($item['category'] ?? ''),
                'variants_count' => count(FontUtils::normalizeVariantTokens((array) ($item['variants'] ?? []))),
                'variants' => FontUtils::normalizeVariantTokens((array) ($item['variants'] ?? [])),
                'is_variable' => $axes !== [],
                'axes' => $axes,
            ];
        }

        return $index;
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

    private function isLegacyCatalogItemsCache(mixed $cached): bool
    {
        return false;
    }

    private function fetchMetadataIndex(): array
    {
        if (is_array($this->metadataIndex)) {
            return $this->metadataIndex;
        }

        $cached = get_transient(TransientKey::forSite(self::TRANSIENT_METADATA));

        if ($this->isMetadataIndex($cached)) {
            $this->metadataIndex = $cached;

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
        $familyMetadata = is_array($decoded['familyMetadataList'] ?? null)
            ? (array) $decoded['familyMetadataList']
            : [];
        $index = [];

        foreach ($familyMetadata as $item) {
            if (!is_array($item)) {
                continue;
            }

            $family = trim((string) ($item['family'] ?? ''));
            $slug = FontUtils::slugify($family);
            $axes = $this->normalizeCatalogAxes((array) ($item['axes'] ?? []));

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

        return is_array($decoded) ? $decoded : [];
    }

    private function enrichCatalogItemWithMetadata(array $item, ?array $metadataIndex = null): array
    {
        $axes = $this->normalizeCatalogAxes((array) ($item['axes'] ?? []));

        if ($axes !== []) {
            $item['axes'] = $axes;
            $item['is_variable'] = true;

            return $item;
        }

        $family = trim((string) ($item['family'] ?? ''));
        $slug = FontUtils::slugify($family);

        if ($family === '' || $slug === '') {
            $item['axes'] = [];
            $item['is_variable'] = false;

            return $item;
        }

        $metadataIndex = is_array($metadataIndex) ? $metadataIndex : $this->fetchMetadataIndex();
        $metadata = is_array($metadataIndex[$slug] ?? null) ? (array) $metadataIndex[$slug] : [];
        $metadataAxes = $this->normalizeCatalogAxes((array) ($metadata['axes'] ?? []));

        $item['axes'] = $metadataAxes;
        $item['is_variable'] = $metadataAxes !== [];

        return $item;
    }

    private function buildCatalogRequestUrl(string $apiKey): string
    {
        return 'https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key=' . rawurlencode($apiKey);
    }

    private function normalizeCatalogAxes(array $axes): array
    {
        $normalizedHostedAxes = FontUtils::normalizeHostedAxisList($axes);

        if ($normalizedHostedAxes !== []) {
            return $normalizedHostedAxes;
        }

        return FontUtils::normalizeAxesMap($axes);
    }

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

            $requestedStyles[FontUtils::normalizeStyle((string) ($axis['style'] ?? 'normal'))] = true;
        }

        if ($requestedStyles === []) {
            $requestedStyles['normal'] = true;
        }

        $rows = [];

        foreach ($faces as $face) {
            if (!is_array($face) || empty($face['is_variable'])) {
                continue;
            }

            $style = FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal'));

            if (!isset($requestedStyles[$style])) {
                continue;
            }

            $rowAxes = FontUtils::normalizeAxesMap((array) ($face['axes'] ?? []));
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
                $axesByTag[$tag] = FontUtils::normalizeAxesMap([$tag => $definition])[$tag] ?? null;
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
            ? array_merge(['ital'], array_map('strtolower', $axisTags))
            : array_map('strtolower', $axisTags);
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
}
