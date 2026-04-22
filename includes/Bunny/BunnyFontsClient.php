<?php

declare(strict_types=1);

namespace TastyFonts\Bunny;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;
use TastyFonts\Support\TransientKey;
use WP_Error;

final class BunnyFontsClient
{
    public const TRANSIENT_CATALOG = 'tasty_fonts_bunny_catalog_v1';
    public const TRANSIENT_FAMILY_PREFIX = 'tasty_fonts_bunny_family_';
    private const CATALOG_TTL = 7 * DAY_IN_SECONDS;
    private const FAMILY_TTL = 7 * DAY_IN_SECONDS;
    private const CATALOG_URL = 'https://fonts.bunny.net/sitemap.xml';
    private const FAMILY_URL = 'https://fonts.bunny.net/family/';
    private const REQUEST_TIMEOUT = 20;

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
                $leftFamily = strtolower((string) ($left['family'] ?? ''));
                $rightFamily = strtolower((string) ($right['family'] ?? ''));
                $leftSlug = strtolower((string) ($left['slug'] ?? ''));
                $rightSlug = strtolower((string) ($right['slug'] ?? ''));
                $leftStarts = str_starts_with($leftFamily, $query) || str_starts_with($leftSlug, $query);
                $rightStarts = str_starts_with($rightFamily, $query) || str_starts_with($rightSlug, $query);

                if ($leftStarts !== $rightStarts) {
                    return $leftStarts ? -1 : 1;
                }

                return strcmp((string) ($left['family'] ?? ''), (string) ($right['family'] ?? ''));
            }
        );

        $items = [];

        foreach (array_slice($results, 0, $limit) as $item) {
            $slug = (string) ($item['slug'] ?? '');
            $family = $slug !== '' ? $this->fetchFamilyBySlug($slug) : null;
            $items[] = $family ?? $this->buildFallbackFamilyRecord($slug, (string) ($item['family'] ?? ''));
        }

        return $items;
    }

    public function getFamily(string $familyName): ?array
    {
        $familyName = trim($familyName);

        if ($familyName === '') {
            return null;
        }

        return $this->fetchFamilyBySlug(FontUtils::slugify($familyName));
    }

    public function fetchCss(string $familyName, array $variants, string $display = 'swap'): string|WP_Error
    {
        $url = $this->buildCssUrl($familyName, $variants, $display);

        $response = $this->remoteGet(
            $url,
            [
                'timeout' => self::REQUEST_TIMEOUT,
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

        if (!is_string($body) || trim($body) === '') {
            return new WP_Error(
                'tasty_fonts_bunny_css_empty',
                __('Bunny Fonts returned an empty CSS response.', 'tasty-fonts')
            );
        }

        return $body;
    }

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

    private function fetchCatalogEntries(): array
    {
        $cached = get_transient(TransientKey::forSite(self::TRANSIENT_CATALOG));

        if (is_array($cached)) {
            return $cached;
        }

        $response = $this->request(self::CATALOG_URL, 'application/xml,text/xml,*/*;q=0.1');

        if (is_wp_error($response)) {
            return [];
        }

        $items = $this->parseCatalogEntries((string) wp_remote_retrieve_body($response));

        if ($items !== []) {
            set_transient(TransientKey::forSite(self::TRANSIENT_CATALOG), $items, self::CATALOG_TTL);
        }

        return $items;
    }

    private function fetchFamilyBySlug(string $slug): ?array
    {
        $slug = FontUtils::slugify($slug);

        if ($slug === '') {
            return null;
        }

        $transientKey = $this->familyTransientKey($slug);
        $cached = get_transient($transientKey);

        if ($this->isCachedFamilyRecord($cached)) {
            return $cached;
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

    private function request(string $url, string $accept): array|WP_Error
    {
        return $this->remoteGet(
            $url,
            [
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => [
                    'Accept' => $accept,
                    'User-Agent' => FontUtils::MODERN_USER_AGENT,
                ],
            ]
        );
    }

    private function remoteGet(string $url, array $args = []): mixed
    {
        $filteredArgs = apply_filters('tasty_fonts_http_request_args', $args, $url);

        return wp_remote_get($url, is_array($filteredArgs) ? $filteredArgs : $args);
    }

    private function parseCatalogEntries(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        preg_match_all('~<loc>\s*https://fonts\.bunny\.net/family/([^<\s]+)\s*</loc>~i', $xml, $matches);

        if (!isset($matches[1]) || !is_array($matches[1])) {
            return [];
        }

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
            $styleCount = max(0, (int) ($matches[1] ?? 0));
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

    private function extractFamilyVariants(string $html, string $slug): array
    {
        preg_match_all('~https://fonts\.bunny\.net/css\?family=([^"\']+)~i', $html, $matches);

        if (!isset($matches[1]) || !is_array($matches[1])) {
            return ['regular'];
        }

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

    private function extractVariableAxisTags(string $html): array
    {
        preg_match_all('/\[((?:[A-Za-z0-9]{4},?)+)\]\.(?:ttf|otf|woff2?|ttc)/i', $html, $matches);

        if (!isset($matches[1]) || !is_array($matches[1])) {
            return [];
        }

        $tags = [];

        foreach ($matches[1] as $group) {
            foreach (explode(',', (string) $group) as $tag) {
                $normalizedTag = FontUtils::normalizeAxisTag($tag);

                if ($normalizedTag === '') {
                    continue;
                }

                $tags[$normalizedTag] = $normalizedTag;
            }
        }

        ksort($tags, SORT_STRING);

        return array_values($tags);
    }

    private function buildVariableAxesFromVariants(array $variants, array $axisTags): array
    {
        if ($axisTags === []) {
            return [];
        }

        $weights = [];

        foreach ($variants as $variant) {
            $axis = FontUtils::googleVariantToAxis((string) $variant);

            if ($axis === null) {
                continue;
            }

            $weight = FontUtils::normalizeWeight((string) ($axis['weight'] ?? '400'));

            if (preg_match('/^\d+$/', $weight) === 1) {
                $weights[] = (int) $weight;
            }
        }

        $axes = [];

        foreach ($axisTags as $tag) {
            if ($tag === 'WGHT' && $weights !== []) {
                sort($weights, SORT_NUMERIC);
                $axes[$tag] = [
                    'min' => (string) $weights[0],
                    'default' => in_array(400, $weights, true) ? '400' : (string) $weights[0],
                    'max' => (string) $weights[count($weights) - 1],
                ];
            }
        }

        return FontUtils::normalizeAxesMap($axes);
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
                static fn ($tag): bool => preg_match('/^[A-Z0-9]{4}$/i', (string) $tag) === 1
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
            array_filter($words, 'strlen')
        );

        return $words !== [] ? implode(' ', $words) : 'Bunny Font';
    }

    private function matchesSearchQuery(array $item, string $query): bool
    {
        $family = strtolower((string) ($item['family'] ?? ''));
        $slug = strtolower((string) ($item['slug'] ?? ''));

        return ($family !== '' && str_contains($family, $query))
            || ($slug !== '' && str_contains($slug, $query));
    }
}
