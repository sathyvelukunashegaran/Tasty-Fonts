<?php

declare(strict_types=1);

namespace TastyFonts\Bunny;

use TastyFonts\Support\FontUtils;
use WP_Error;

final class BunnyFontsClient
{
    private const TRANSIENT_CATALOG = 'tasty_fonts_bunny_catalog_v1';
    private const TRANSIENT_FAMILY_PREFIX = 'tasty_fonts_bunny_family_';
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

        $response = wp_remote_get(
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
        $axes = $this->buildCssAxes($variants);

        if ($axes !== []) {
            $url .= ':ital,wght@' . implode(';', $axes);
        }

        return $url . '&display=' . rawurlencode($this->sanitizeDisplay($display));
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

    private function fetchCatalogEntries(): array
    {
        $cached = get_transient(self::TRANSIENT_CATALOG);

        if (is_array($cached)) {
            return $cached;
        }

        $response = $this->request(self::CATALOG_URL, 'application/xml,text/xml,*/*;q=0.1');

        if (is_wp_error($response)) {
            return [];
        }

        $items = $this->parseCatalogEntries((string) wp_remote_retrieve_body($response));

        if ($items !== []) {
            set_transient(self::TRANSIENT_CATALOG, $items, self::CATALOG_TTL);
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

        if (is_array($cached)) {
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
        return wp_remote_get(
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

        return [
            'family' => $familyName,
            'slug' => $slug,
            'category' => $this->normalizeCategory($categoryLabel),
            'category_label' => $categoryLabel !== '' ? $categoryLabel : 'Bunny Fonts',
            'variants' => $variants,
            'style_count' => $styleCount > 0 ? $styleCount : count($variants),
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
        ];
    }

    private function familyTransientKey(string $slug): string
    {
        return self::TRANSIENT_FAMILY_PREFIX . substr(md5($slug), 0, 12);
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
