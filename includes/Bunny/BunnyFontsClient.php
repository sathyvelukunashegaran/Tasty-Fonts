<?php

declare(strict_types=1);

namespace TastyFonts\Bunny;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;
use TastyFonts\Support\TransientKey;
use WP_Error;

/**
 * @phpstan-type CatalogEntry array{slug: string, family: string}
 * @phpstan-type CatalogEntryList list<CatalogEntry>
 * @phpstan-type FamilyRecord array<string, mixed>
 * @phpstan-type FamilySearchResultList list<FamilyRecord>
 * @phpstan-type VariantTokenList list<string>
 * @phpstan-type HttpArgs array{
 *   method?: string,
 *   timeout?: float,
 *   redirection?: int,
 *   httpversion?: string,
 *   user-agent?: string,
 *   reject_unsafe_urls?: bool,
 *   blocking?: bool,
 *   headers?: array<string, string>|string,
 *   ...
 * }
 */
final class BunnyFontsClient
{
    public const TRANSIENT_CATALOG = 'tasty_fonts_bunny_catalog_v2';
    public const TRANSIENT_FAMILY_PREFIX = 'tasty_fonts_bunny_family_v2_';
    private const CATALOG_TTL = 7 * DAY_IN_SECONDS;
    private const FAMILY_TTL = 7 * DAY_IN_SECONDS;
    private const CATALOG_URL = 'https://fonts.bunny.net/sitemap.xml';
    private const FAMILY_URL = 'https://fonts.bunny.net/family/';
    private const REQUEST_TIMEOUT = 20;

    /**
     * @return FamilySearchResultList
     */
    public function searchFamilies(string $query, int $limit = 20): array
    {
        $query = strtolower(trim($query));

        if ($query === '') {
            return [];
        }

        $results = [];

        foreach ($this->fetchCatalogEntries() as $item) {
            if (!$this->matchesSearchQuery($item, $query)) {
                continue;
            }

            $results[] = $item;
        }

        usort(
            $results,
            static function (array $left, array $right) use ($query): int {
                $leftFamily = strtolower($left['family']);
                $rightFamily = strtolower($right['family']);
                $leftSlug = strtolower($left['slug']);
                $rightSlug = strtolower($right['slug']);
                $leftStarts = str_starts_with($leftFamily, $query) || str_starts_with($leftSlug, $query);
                $rightStarts = str_starts_with($rightFamily, $query) || str_starts_with($rightSlug, $query);

                if ($leftStarts !== $rightStarts) {
                    return $leftStarts ? -1 : 1;
                }

                return strcmp($left['family'], $right['family']);
            }
        );

        $items = [];

        foreach (array_slice($results, 0, $limit) as $item) {
            $slug = $item['slug'];
            $family = $slug !== '' ? $this->fetchFamilyBySlug($slug) : null;
            $items[] = $family ?? $this->buildFallbackFamilyRecord($slug, $item['family']);
        }

        return $items;
    }

    /**
     * @return FamilyRecord|null
     */
    public function getFamily(string $familyName): ?array
    {
        $familyName = trim($familyName);

        if ($familyName === '') {
            return null;
        }

        return $this->fetchFamilyBySlug(FontUtils::slugify($familyName));
    }

    /**
     * @param VariantTokenList $variants
     */
    public function fetchCss(string $familyName, array $variants, string $display = 'swap'): string|WP_Error
    {
        $url = $this->buildCssUrl($familyName, $variants, $display);

        $response = $this->remoteGet(
            $url,
            [
                'timeout' => (float) self::REQUEST_TIMEOUT,
                'headers' => [
                    'Accept' => 'text/css,*/*;q=0.1',
                    'User-Agent' => FontUtils::MODERN_USER_AGENT,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            return new WP_Error(
                'tasty_fonts_bunny_css_fetch_failed',
                sprintf(__('Bunny Fonts CSS request failed with status %d.', 'tasty-fonts'), $status)
            );
        }

        $body = wp_remote_retrieve_body($response);

        if (trim($body) === '') {
            return new WP_Error(
                'tasty_fonts_bunny_css_empty',
                __('Bunny Fonts returned an empty CSS response.', 'tasty-fonts')
            );
        }

        return $body;
    }

    /**
     * @param VariantTokenList $variants
     */
    public function buildCssUrl(string $familyName, array $variants, string $display = 'swap'): string
    {
        $familyQuery = str_replace('%20', '+', rawurlencode($familyName));
        $url = 'https://fonts.bunny.net/css2?family=' . $familyQuery;
        $axes = FontUtils::buildHostedCssAxes($variants);

        if ($axes !== []) {
            $url .= ':ital,wght@' . implode(';', $axes);
        }

        return $url . '&display=' . rawurlencode(FontUtils::sanitizeHostedCssDisplay($display));
    }

    /**
     * @return CatalogEntryList
     */
    private function fetchCatalogEntries(): array
    {
        $cached = get_transient(TransientKey::forSite(self::TRANSIENT_CATALOG));

        if (is_array($cached)) {
            return $this->normalizeCatalogEntries($cached);
        }

        $response = $this->request(self::CATALOG_URL, 'application/xml,text/xml,*/*;q=0.1');

        if (is_wp_error($response)) {
            return [];
        }

        $items = $this->parseCatalogEntries((string) wp_remote_retrieve_body($response));

        if ($items !== []) {
            set_transient(TransientKey::forSite(self::TRANSIENT_CATALOG), $items, self::CATALOG_TTL);
        }

        return $this->normalizeCatalogEntries($items);
    }

    /**
     * @param mixed $entries
     * @return CatalogEntryList
     */
    private function normalizeCatalogEntries(mixed $entries): array
    {
        if (!is_array($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $slug = trim(FontUtils::scalarStringValue($entry['slug'] ?? null));
            $family = trim(FontUtils::scalarStringValue($entry['family'] ?? null));

            if ($slug === '' || $family === '') {
                continue;
            }

            $normalized[] = [
                'slug' => $slug,
                'family' => $family,
            ];
        }

        return $normalized;
    }

    /**
     * @return FamilyRecord|null
     */
    private function fetchFamilyBySlug(string $slug): ?array
    {
        $slug = FontUtils::slugify($slug);

        if ($slug === '') {
            return null;
        }

        $transientKey = $this->familyTransientKey($slug);
        $cached = get_transient($transientKey);

        $cachedFamily = $this->normalizeCachedFamilyRecord($cached);

        if ($cachedFamily !== null) {
            return $cachedFamily;
        }

        $response = $this->request(
            self::FAMILY_URL . rawurlencode($slug),
            'text/html,application/xhtml+xml,*/*;q=0.1'
        );

        if (is_wp_error($response)) {
            return null;
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $family = $this->parseFamilyPage((string) wp_remote_retrieve_body($response), $slug);

        if ($family === null) {
            return null;
        }

        set_transient($transientKey, $family, self::FAMILY_TTL);

        return $family;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function request(string $url, string $accept): array|WP_Error
    {
        return $this->remoteGet(
            $url,
            [
                'timeout' => (float) self::REQUEST_TIMEOUT,
                'headers' => [
                    'Accept' => $accept,
                    'User-Agent' => FontUtils::MODERN_USER_AGENT,
                ],
            ]
        );
    }

    /**
     * @param HttpArgs $args
     */
    /**
     * @param HttpArgs $args
     * @return array<string, mixed>|WP_Error
     */
    private function remoteGet(string $url, array $args = []): array|WP_Error
    {
        $filteredArgs = apply_filters('tasty_fonts_http_request_args', $args, $url);

        return wp_remote_get($url, FontUtils::normalizeHttpArgs(is_array($filteredArgs) ? $filteredArgs : $args));
    }

    /**
     * @return CatalogEntryList
     */
    private function parseCatalogEntries(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        preg_match_all('~<loc>\s*https://fonts\.bunny\.net/family/([^<\s]+)\s*</loc>~i', $xml, $matches);

        $entries = [];

        foreach ($matches[1] as $match) {
            $slug = FontUtils::slugify(rawurldecode((string) $match));

            if ($slug === '' || isset($entries[$slug])) {
                continue;
            }

            $entries[$slug] = [
                'slug' => $slug,
                'family' => $this->humanizeSlug($slug),
            ];
        }

        return array_values($entries);
    }

    /**
     * @return FamilyRecord|null
     */
    private function parseFamilyPage(string $html, string $slug): ?array
    {
        if (trim($html) === '') {
            return null;
        }

        $familyName = $this->extractHtmlText($html, '~<title>\s*(.*?)\s*\|\s*Bunny Fonts\s*</title>~is');

        if ($familyName === '') {
            $familyName = $this->extractHtmlText($html, '~<h1[^>]*>(.*?)</h1>~is');
        }

        if ($familyName === '') {
            $familyName = $this->humanizeSlug($slug);
        }

        $categoryLabel = $this->extractHtmlText($html, '~<div class="family"><h3>(.*?)</h3>~is');
        $styleCount = 0;

        if (preg_match('~<div class="styles">\s*(\d+)\s+styles?</div>~i', $html, $matches) === 1) {
            $styleCount = max(0, (int) $matches[1]);
        }

        $variants = $this->extractFamilyVariants($html, $slug);
        $formats = [
            'static' => [
                'label' => 'Static',
                'available' => true,
                'source_only' => false,
            ],
        ];

        return [
            'family' => $familyName,
            'slug' => $slug,
            'category' => $this->normalizeCategory($categoryLabel),
            'category_label' => $categoryLabel !== '' ? $categoryLabel : 'Bunny Fonts',
            'variants' => $variants,
            'style_count' => $styleCount > 0 ? $styleCount : count($variants),
            'is_variable' => false,
            'axes' => [],
            'axis_tags' => [],
            'formats' => $formats,
            'import_options' => $formats,
        ];
    }

    private function extractHtmlText(string $html, string $pattern): string
    {
        if (preg_match($pattern, $html, $matches) !== 1) {
            return '';
        }

        $text = html_entity_decode(wp_strip_all_tags((string) ($matches[1] ?? '')), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        return trim($text);
    }

    /**
     * @return VariantTokenList
     */
    private function extractFamilyVariants(string $html, string $slug): array
    {
        preg_match_all('~https://fonts\.bunny\.net/css\?family=([^"\']+)~i', $html, $matches);

        foreach ($matches[1] as $familyQuery) {
            $decodedQuery = html_entity_decode((string) $familyQuery, ENT_QUOTES, 'UTF-8');
            $familySegments = explode('|', str_replace('+', ' ', rawurldecode($decodedQuery)));

            foreach ($familySegments as $segment) {
                $segment = trim($segment);

                if ($segment === '') {
                    continue;
                }

                [$segmentFamily, $rawVariants] = array_pad(explode(':', $segment, 2), 2, '');

                if (FontUtils::slugify($segmentFamily) !== $slug) {
                    continue;
                }

                return $this->normalizeBunnyVariantTokens(explode(',', $rawVariants));
            }
        }

        return ['regular'];
    }

    /**
     * @param VariantTokenList $rawTokens
     * @return VariantTokenList
     */
    private function normalizeBunnyVariantTokens(array $rawTokens): array
    {
        $normalized = [];

        foreach ($rawTokens as $token) {
            $variant = $this->normalizeBunnyVariantToken((string) $token);

            if ($variant === null) {
                continue;
            }

            $normalized[] = $variant;
        }

        return FontUtils::normalizeVariantTokens($normalized);
    }

    private function normalizeBunnyVariantToken(string $token): ?string
    {
        $token = strtolower(trim($token));

        if ($token === '') {
            return null;
        }

        if ($token === 'regular' || $token === 'italic') {
            return $token;
        }

        if (preg_match('/^([1-9]00)$/', $token, $matches) === 1) {
            return $matches[1] === '400' ? 'regular' : $matches[1];
        }

        if (preg_match('/^([1-9]00)i$/', $token, $matches) === 1) {
            return $matches[1] === '400' ? 'italic' : $matches[1] . 'italic';
        }

        return null;
    }

    private function normalizeCategory(string $label): string
    {
        $normalized = strtolower(trim($label));

        if (str_contains($normalized, 'mono')) {
            return 'monospace';
        }

        if (str_contains($normalized, 'hand') || str_contains($normalized, 'script')) {
            return 'handwriting';
        }

        if (str_contains($normalized, 'display') || str_contains($normalized, 'decorative')) {
            return 'display';
        }

        if (str_contains($normalized, 'sans')) {
            return 'sans-serif';
        }

        if (str_contains($normalized, 'serif')) {
            return 'serif';
        }

        return 'sans-serif';
    }

    /**
     * @return FamilyRecord
     */
    private function buildFallbackFamilyRecord(string $slug, string $familyName = ''): array
    {
        $familyName = trim($familyName) !== '' ? trim($familyName) : $this->humanizeSlug($slug);

        return [
            'family' => $familyName,
            'slug' => $slug,
            'category' => 'sans-serif',
            'category_label' => 'Bunny Fonts',
            'variants' => ['regular'],
            'style_count' => 1,
            'is_variable' => false,
            'axes' => [],
            'axis_tags' => [],
            'formats' => [
                'static' => [
                    'label' => 'Static',
                    'available' => true,
                    'source_only' => false,
                ],
            ],
            'import_options' => [
                'static' => [
                    'label' => 'Static',
                    'available' => true,
                    'source_only' => false,
                ],
            ],
        ];
    }

    /**
     * @return FamilyRecord|null
     */
    private function normalizeCachedFamilyRecord(mixed $cached): ?array
    {
        if (!$this->isCachedFamilyRecord($cached) || !is_array($cached)) {
            return null;
        }

        $normalized = [];

        foreach ($cached as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function isCachedFamilyRecord(mixed $cached): bool
    {
        if (!is_array($cached)) {
            return false;
        }

        $formats = is_array($cached['formats'] ?? null) ? $cached['formats'] : [];
        $importOptions = is_array($cached['import_options'] ?? null) ? $cached['import_options'] : [];
        $axisTags = is_array($cached['axis_tags'] ?? null)
            ? array_filter(
                $cached['axis_tags'],
                static fn ($tag): bool => is_scalar($tag) && preg_match('/^[A-Z0-9]{4}$/i', (string) $tag) === 1
            )
            : [];

        return array_key_exists('family', $cached)
            && array_key_exists('slug', $cached)
            && array_key_exists('category', $cached)
            && array_key_exists('variants', $cached)
            && array_key_exists('style_count', $cached)
            && array_key_exists('is_variable', $cached)
            && array_key_exists('axes', $cached)
            && array_key_exists('axis_tags', $cached)
            && array_key_exists('formats', $cached)
            && array_key_exists('static', $formats)
            && !array_key_exists('variable', $formats)
            && (!array_key_exists('import_options', $cached) || (array_key_exists('static', $importOptions) && !array_key_exists('variable', $importOptions)))
            && empty($cached['is_variable'])
            && FontUtils::normalizeAxesMap($cached['axes']) === []
            && $axisTags === [];
    }

    private function familyTransientKey(string $slug): string
    {
        return TransientKey::forSite(self::TRANSIENT_FAMILY_PREFIX . substr(md5($slug), 0, 12));
    }

    private function humanizeSlug(string $slug): string
    {
        $words = preg_split('/[-_]+/', $slug) ?: [];
        $words = array_map(
            static function (string $word): string {
                return preg_match('/^\d+$/', $word) === 1 ? $word : ucfirst($word);
            },
            array_filter($words, static fn (string $word): bool => $word !== '')
        );

        return $words !== [] ? implode(' ', $words) : 'Bunny Font';
    }

    /**
     * @param CatalogEntry $item
     */
    private function matchesSearchQuery(array $item, string $query): bool
    {
        $family = strtolower($item['family']);
        $slug = strtolower($item['slug']);

        return ($family !== '' && str_contains($family, $query))
            || ($slug !== '' && str_contains($slug, $query));
    }

}
