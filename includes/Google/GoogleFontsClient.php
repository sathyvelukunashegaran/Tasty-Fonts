<?php

declare(strict_types=1);

namespace TastyFonts\Google;

use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use WP_Error;

final class GoogleFontsClient
{
    private const TRANSIENT_CATALOG = 'tasty_fonts_google_catalog_v1';
    private const CATALOG_TTL = 12 * HOUR_IN_SECONDS;
    private const REQUEST_TIMEOUT = 20;

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function hasApiKey(): bool
    {
        return $this->settings->hasGoogleApiKey();
    }

    public function canSearch(): bool
    {
        $status = $this->settings->getGoogleApiKeyStatus();

        return $this->hasApiKey() && ($status['state'] ?? 'empty') === 'valid';
    }

    public function getApiKeyStatus(): array
    {
        return $this->settings->getGoogleApiKeyStatus();
    }

    public function clearCatalogCache(): void
    {
        delete_transient(self::TRANSIENT_CATALOG);
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

        $response = wp_remote_get($this->buildCatalogRequestUrl($apiKey), ['timeout' => self::REQUEST_TIMEOUT]);

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

    public function searchFamilies(string $query, int $limit = 20): array
    {
        $query = strtolower(trim($query));

        if ($query === '' || !$this->canSearch()) {
            return [];
        }

        $results = [];

        foreach ($this->fetchCatalogItems() as $item) {
            $family = (string) ($item['family'] ?? '');

            if (!$this->matchesSearchQuery($family, $query)) {
                continue;
            }

            $results[] = $this->normalizeCatalogItem($item);
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

    public function fetchCss(string $familyName, array $variants, string $display = 'swap'): string|WP_Error
    {
        $url = $this->buildCssUrl($familyName, $variants, $display);

        $response = wp_remote_get(
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

        if (!is_string($body) || trim($body) === '') {
            return new WP_Error(
                'tasty_fonts_google_css_empty',
                __('Google Fonts returned an empty CSS response.', 'tasty-fonts')
            );
        }

        return $body;
    }

    public function buildCssUrl(string $familyName, array $variants, string $display = 'swap'): string
    {
        $familyQuery = str_replace('%20', '+', rawurlencode($familyName));
        $url = 'https://fonts.googleapis.com/css2?family=' . $familyQuery;
        $axes = $this->buildCssAxes($variants);

        if ($axes !== []) {
            $url .= ':ital,wght@' . implode(';', $axes);
        }

        return $url . '&display=' . rawurlencode($this->sanitizeDisplay($display));
    }

    private function fetchCatalogItems(): array
    {
        $cached = get_transient(self::TRANSIENT_CATALOG);

        if (is_array($cached)) {
            return $cached;
        }

        $apiKey = $this->getApiKey();

        if ($apiKey === '') {
            return [];
        }

        $response = wp_remote_get($this->buildCatalogRequestUrl($apiKey), ['timeout' => self::REQUEST_TIMEOUT]);

        if (is_wp_error($response)) {
            return [];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($status !== 200 || !is_array($body) || !isset($body['items']) || !is_array($body['items'])) {
            return [];
        }

        set_transient(self::TRANSIENT_CATALOG, $body['items'], self::CATALOG_TTL);

        return $body['items'];
    }

    private function normalizeCatalogItem(array $item): array
    {
        return [
            'family' => (string) ($item['family'] ?? ''),
            'category' => (string) ($item['category'] ?? ''),
            'variants' => FontUtils::normalizeVariantTokens((array) ($item['variants'] ?? [])),
            'subsets' => array_values(array_filter((array) ($item['subsets'] ?? []), 'is_string')),
            'version' => (string) ($item['version'] ?? ''),
            'lastModified' => (string) ($item['lastModified'] ?? ''),
        ];
    }

    private function modernUserAgent(): string
    {
        return FontUtils::MODERN_USER_AGENT;
    }

    private function buildCssAxes(array $variants): array
    {
        $axes = [];

        foreach (FontUtils::normalizeVariantTokens($variants) as $token) {
            $axis = FontUtils::googleVariantToAxis($token);

            if ($axis === null) {
                continue;
            }

            $axes[] = ($axis['style'] === 'italic' ? '1' : '0') . ',' . $axis['weight'];
        }

        return array_values(array_unique($axes));
    }

    private function sanitizeDisplay(string $display): string
    {
        $display = strtolower(trim($display));

        return in_array($display, ['auto', 'block', 'swap', 'fallback', 'optional'], true)
            ? $display
            : 'swap';
    }

    private function getApiKey(): string
    {
        return trim((string) $this->settings->getSettings()['google_api_key']);
    }

    private function buildCatalogRequestUrl(string $apiKey): string
    {
        return 'https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key=' . rawurlencode($apiKey);
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
