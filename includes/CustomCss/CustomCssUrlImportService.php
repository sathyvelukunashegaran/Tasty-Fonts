<?php

declare(strict_types=1);

namespace TastyFonts\CustomCss;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Support\FontUtils;
use WP_Error;

/**
 * Builds the first normalized custom CSS URL dry-run plan without writing library data or font files.
 *
 * @phpstan-type DryRunFace array<string, mixed>
 * @phpstan-type DryRunFamily array<string, mixed>
 * @phpstan-type DryRunPlan array<string, mixed>
 * @phpstan-type FontSource array{url: string, format: string, supported: bool, warnings: list<string>}
 * @phpstan-type FontValidation array<string, mixed>
 */
final class CustomCssUrlImportService
{
    public function __construct(
        private readonly ?ImportRepository $imports = null,
        private readonly ?CustomCssFontValidator $validator = null
    ) {
    }

    private function getValidator(): CustomCssFontValidator
    {
        return $this->validator ?? new CustomCssFontValidator();
    }

    private const REQUEST_TIMEOUT = 10;
    private const CSS_SIZE_LIMIT_BYTES = 262144;
    private const FONT_SIZE_LIMIT_BYTES = 10485760;
    private const MAX_DETECTED_FACES = 50;
    private const MAX_UNIQUE_FONT_URLS = 50;
    private const SUPPORTED_FONT_FORMATS = ['woff2', 'woff'];

    /**
     * @return DryRunPlan|WP_Error
     */
    public function dryRun(string $stylesheetUrl): array|WP_Error
    {
        $stylesheetUrl = $this->normalizeStylesheetUrl($stylesheetUrl);

        if ($stylesheetUrl === '') {
            return new WP_Error(
                'tasty_fonts_custom_css_url_required',
                __('Enter a public HTTPS CSS stylesheet URL.', 'tasty-fonts')
            );
        }

        $urlSafetyError = $this->getValidator()->validatePublicHttpsUrl($stylesheetUrl, 'stylesheet', 'dry-run');

        if ($urlSafetyError instanceof WP_Error) {
            return $urlSafetyError;
        }

        $css = $this->fetchStylesheet($stylesheetUrl);

        if (is_wp_error($css)) {
            return $css;
        }

        $faces = $this->parseFaces($css, $stylesheetUrl);

        if (is_wp_error($faces)) {
            return $faces;
        }

        if ($faces === []) {
            return new WP_Error(
                'tasty_fonts_custom_css_no_faces',
                __('No @font-face rules with public HTTPS WOFF or WOFF2 font URLs were found in that stylesheet.', 'tasty-fonts')
            );
        }

        return $this->buildPlan($stylesheetUrl, $faces);
    }

    private function normalizeStylesheetUrl(string $stylesheetUrl): string
    {
        return esc_url_raw(trim($stylesheetUrl));
    }

    private function fetchStylesheet(string $stylesheetUrl): string|WP_Error
    {
        $args = [
            'timeout' => self::REQUEST_TIMEOUT,
            'redirection' => 3,
            'reject_unsafe_urls' => true,
            'limit_response_size' => self::CSS_SIZE_LIMIT_BYTES + 1,
            'headers' => [
                'Accept' => 'text/css,*/*;q=0.8',
            ],
        ];
        $filteredArgs = apply_filters('tasty_fonts_http_request_args', $args, $stylesheetUrl);
        $response = wp_remote_get($stylesheetUrl, FontUtils::normalizeHttpArgs(is_array($filteredArgs) ? $filteredArgs : $args));

        if (is_wp_error($response)) {
            $message = $response->get_error_message();

            if ($this->getValidator()->isTimeoutMessage($message)) {
                return new WP_Error(
                    'tasty_fonts_custom_css_fetch_timeout',
                    __('The stylesheet request timed out. Try a faster source or a smaller stylesheet.', 'tasty-fonts')
                );
            }

            return new WP_Error(
                'tasty_fonts_custom_css_fetch_failed',
                $message !== ''
                    ? $message
                    : __('The stylesheet could not be fetched.', 'tasty-fonts')
            );
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            return new WP_Error(
                'tasty_fonts_custom_css_fetch_failed',
                sprintf(
                    /* translators: %d is the HTTP status code returned by the stylesheet URL. */
                    __('The stylesheet request returned HTTP %d.', 'tasty-fonts'),
                    $status
                )
            );
        }

        $contentLength = (int) wp_remote_retrieve_header($response, 'content-length');

        if ($contentLength > self::CSS_SIZE_LIMIT_BYTES) {
            return $this->cssTooLargeError();
        }

        $body = wp_remote_retrieve_body($response);

        if (strlen($body) > self::CSS_SIZE_LIMIT_BYTES) {
            return $this->cssTooLargeError();
        }

        if (trim($body) === '') {
            return new WP_Error(
                'tasty_fonts_custom_css_empty',
                __('The stylesheet response was empty.', 'tasty-fonts')
            );
        }

        return $body;
    }

    /**
     * @return list<DryRunFace>|WP_Error
     */
    private function parseFaces(string $css, string $stylesheetUrl): array|WP_Error
    {
        $matchCount = preg_match_all('/@font-face\s*\{(.*?)\}/si', $css, $matches);

        if ($matchCount === false || empty($matches[1])) {
            return [];
        }

        if ($matchCount > self::MAX_DETECTED_FACES) {
            return new WP_Error(
                'tasty_fonts_custom_css_too_many_faces',
                sprintf(
                    /* translators: %d is the maximum number of font faces allowed in one dry run. */
                    __('That stylesheet contains too many @font-face rules. Split it into smaller stylesheets with no more than %d faces each.', 'tasty-fonts'),
                    self::MAX_DETECTED_FACES
                )
            );
        }

        $faces = [];
        $validatedFontUrls = [];
        $fontValidationCache = [];

        foreach ($matches[1] as $block) {
            $face = $this->buildFace((string) $block, $stylesheetUrl, $validatedFontUrls, $fontValidationCache);

            if (is_wp_error($face)) {
                return $face;
            }

            if ($face !== null) {
                $faces[] = $face;
            }
        }

        return $faces;
    }

    /**
     * @param array<string, true> $validatedFontUrls
     * @param array<string, FontValidation> $fontValidationCache
     * @return DryRunFace|WP_Error|null
     */
    private function buildFace(string $block, string $stylesheetUrl, array &$validatedFontUrls, array &$fontValidationCache): array|WP_Error|null
    {
        $family = $this->trimCssString($this->propertyValue($block, 'font-family'));

        if ($family === '') {
            return null;
        }

        $source = $this->extractPreferredSource($this->propertyValue($block, 'src'), $stylesheetUrl, $validatedFontUrls);

        if (is_wp_error($source)) {
            return $source;
        }

        if ($source === null) {
            return null;
        }

        $fontUrl = $source['url'];
        $weight = FontUtils::normalizeWeight($this->propertyValue($block, 'font-weight') ?: '400');
        $style = FontUtils::normalizeStyle($this->propertyValue($block, 'font-style') ?: 'normal');
        $unicodeRange = trim($this->propertyValue($block, 'unicode-range'));
        $format = $source['format'];
        $slug = FontUtils::slugify($family);
        $supported = $source['supported'];
        $validation = $supported
            ? $this->cachedFontValidation($fontUrl, $format, $fontValidationCache)
            : $this->unsupportedFontValidation($format);
        $status = $supported ? $this->stringValue($validation, 'status') : 'unsupported';
        $warnings = array_values(array_filter(array_merge(
            $source['warnings'],
            is_array($validation['warnings'] ?? null) ? $validation['warnings'] : []
        ), static fn (mixed $warning): bool => is_scalar($warning) && trim((string) $warning) !== ''));
        $selected = in_array($status, ['valid', 'warning'], true);
        $id = $this->buildFaceId($family, $weight, $style, $format, $fontUrl, $unicodeRange);

        return [
            'id' => $id,
            'family' => $family,
            'slug' => $slug,
            'weight' => $weight,
            'style' => $style,
            'format' => $format,
            'url' => $fontUrl,
            'host' => $this->hostForUrl($fontUrl),
            'unicode_range' => $unicodeRange,
            'status' => $status,
            'selected' => $selected,
            'warnings' => $warnings,
            'validation' => $validation,
        ];
    }

    private function propertyValue(string $block, string $property): string
    {
        $matchCount = preg_match_all('/(?:^|;)\s*' . preg_quote($property, '/') . '\s*:\s*([^;]*)(?=;|$)/i', $block, $matches);

        if ($matchCount === false || $matchCount < 1 || empty($matches[1])) {
            return '';
        }

        return trim((string) end($matches[1]));
    }

    /**
     * @param array<string, true> $validatedFontUrls
     * @return FontSource|WP_Error|null
     */
    private function extractPreferredSource(string $src, string $stylesheetUrl, array &$validatedFontUrls): array|WP_Error|null
    {
        $sources = [];
        $matchCount = preg_match_all('/url\(([^)]+)\)\s*(?:format\(([^)]+)\))?/i', $src, $matches, PREG_SET_ORDER);

        if (!is_int($matchCount) || $matchCount < 1) {
            return null;
        }

        foreach ($matches as $match) {
            $url = $this->trimCssString($match[1]);

            if ($url === '') {
                continue;
            }

            $fontUrl = $this->normalizeFontUrl($url, $stylesheetUrl);

            if ($fontUrl === null) {
                continue;
            }

            if (is_wp_error($fontUrl)) {
                return $fontUrl;
            }

            $safetyError = $this->getValidator()->validatePublicHttpsUrl($fontUrl, 'font', 'dry-run');

            if ($safetyError instanceof WP_Error) {
                return $safetyError;
            }

            if (!isset($validatedFontUrls[$fontUrl])) {
                if (count($validatedFontUrls) >= self::MAX_UNIQUE_FONT_URLS) {
                    return new WP_Error(
                        'tasty_fonts_custom_css_too_many_font_urls',
                        sprintf(
                            /* translators: %d is the maximum number of unique font URLs allowed in one dry run. */
                            __('That stylesheet references too many unique font URLs. Split it into smaller stylesheets with no more than %d font URLs each.', 'tasty-fonts'),
                            self::MAX_UNIQUE_FONT_URLS
                        )
                    );
                }

                $validatedFontUrls[$fontUrl] = true;
            }

            $rawFormat = array_key_exists(2, $match) ? $match[2] : '';
            $format = $this->normalizeSourceFormat((string) $rawFormat, $fontUrl);
            $supported = in_array($format, self::SUPPORTED_FONT_FORMATS, true);

            $sources[] = [
                'url' => $fontUrl,
                'format' => $format,
                'supported' => $supported,
                'warnings' => $supported ? [] : [__('Only WOFF2 and WOFF font sources are supported in this import flow.', 'tasty-fonts')],
            ];
        }

        if ($sources === []) {
            return null;
        }

        $supportedSources = array_values(array_filter($sources, static fn (array $source): bool => $source['supported'] === true));

        if ($supportedSources === []) {
            return $sources[0];
        }

        usort(
            $supportedSources,
            static fn (array $left, array $right): int => ($left['format'] === 'woff2' ? 0 : 1) <=> ($right['format'] === 'woff2' ? 0 : 1)
        );

        return $supportedSources[0];
    }

    private function normalizeFontUrl(string $url, string $stylesheetUrl): string|WP_Error|null
    {
        $url = $this->trimCssString($url);

        if ($url === '' || str_starts_with(strtolower($url), 'data:')) {
            return null;
        }

        $resolvedUrl = $this->resolveFontUrl($url, $stylesheetUrl);

        if (is_wp_error($resolvedUrl)) {
            return $resolvedUrl;
        }

        $fontUrl = esc_url_raw($resolvedUrl);

        if ($fontUrl === '' || !$this->hasHttpsSchemeAndHost($fontUrl)) {
            return new WP_Error(
                'tasty_fonts_custom_css_url_invalid',
                __('Font URLs in the stylesheet must be valid public HTTPS URLs.', 'tasty-fonts')
            );
        }

        return $this->normalizeAbsoluteUrl($fontUrl);
    }

    private function normalizeSourceFormat(string $rawFormat, string $fontUrl): string
    {
        $format = strtolower($this->trimCssString($rawFormat));
        $format = preg_replace('/\s+/', '-', $format) ?: $format;

        if (in_array($format, ['woff2', 'woff2-variations'], true)) {
            return 'woff2';
        }

        if (in_array($format, ['woff', 'woff-variations'], true)) {
            return 'woff';
        }

        if (in_array($format, ['truetype', 'ttf'], true)) {
            return 'ttf';
        }

        if (in_array($format, ['opentype', 'otf'], true)) {
            return 'otf';
        }

        if (in_array($format, ['embedded-opentype', 'eot'], true)) {
            return 'eot';
        }

        if ($format === '') {
            $path = strtolower((string) wp_parse_url($fontUrl, PHP_URL_PATH));

            if (str_ends_with($path, '.woff2')) {
                return 'woff2';
            }

            if (str_ends_with($path, '.woff')) {
                return 'woff';
            }

            if (str_ends_with($path, '.ttf')) {
                return 'ttf';
            }

            if (str_ends_with($path, '.otf')) {
                return 'otf';
            }

            if (str_ends_with($path, '.eot')) {
                return 'eot';
            }

            if (str_ends_with($path, '.svg')) {
                return 'svg';
            }
        }

        $normalized = preg_replace('/[^a-z0-9._-]/', '', $format) ?: '';

        return $normalized !== '' ? $normalized : 'unknown';
    }

    private function resolveFontUrl(string $url, string $stylesheetUrl): string|WP_Error
    {
        if (str_starts_with($url, '//')) {
            $stylesheetParts = wp_parse_url($stylesheetUrl);
            $scheme = strtolower((string) ($stylesheetParts['scheme'] ?? 'https'));

            return $scheme . ':' . $url;
        }

        if ($this->isAbsoluteUrlReference($url)) {
            return $url;
        }

        $stylesheetParts = wp_parse_url($stylesheetUrl);

        if (!is_array($stylesheetParts) || strtolower((string) ($stylesheetParts['scheme'] ?? '')) !== 'https' || empty($stylesheetParts['host'])) {
            return new WP_Error(
                'tasty_fonts_custom_css_url_invalid',
                __('Font URLs in the stylesheet must be valid public HTTPS URLs.', 'tasty-fonts')
            );
        }

        $relativeParts = wp_parse_url($url);

        if (!is_array($relativeParts)) {
            return new WP_Error(
                'tasty_fonts_custom_css_url_invalid',
                __('Font URLs in the stylesheet must be valid public HTTPS URLs.', 'tasty-fonts')
            );
        }

        $relativePath = (string) ($relativeParts['path'] ?? '');
        $stylesheetPath = (string) ($stylesheetParts['path'] ?? '/');
        $basePath = str_ends_with($stylesheetPath, '/') ? $stylesheetPath : dirname($stylesheetPath) . '/';
        $path = str_starts_with($relativePath, '/') ? $relativePath : $basePath . $relativePath;
        $query = array_key_exists('query', $relativeParts) ? '?' . (string) $relativeParts['query'] : '';
        $fragment = array_key_exists('fragment', $relativeParts) ? '#' . (string) $relativeParts['fragment'] : '';
        $port = array_key_exists('port', $stylesheetParts) ? ':' . (string) $stylesheetParts['port'] : '';

        return 'https://' . $this->normalizeHost((string) $stylesheetParts['host']) . $port . $this->normalizeUrlPath($path) . $query . $fragment;
    }

    private function normalizeAbsoluteUrl(string $url): string|WP_Error
    {
        $parts = wp_parse_url($url);

        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || !empty($parts['user'] ?? '')
            || !empty($parts['pass'] ?? '')
        ) {
            return new WP_Error(
                'tasty_fonts_custom_css_url_invalid',
                __('Font URLs in the stylesheet must be valid public HTTPS URLs.', 'tasty-fonts')
            );
        }

        $path = $this->normalizeUrlPath((string) ($parts['path'] ?? '/'));
        $query = array_key_exists('query', $parts) ? '?' . (string) $parts['query'] : '';
        $fragment = array_key_exists('fragment', $parts) ? '#' . (string) $parts['fragment'] : '';
        $port = array_key_exists('port', $parts) ? ':' . (string) $parts['port'] : '';

        return 'https://' . $this->normalizeHost((string) $parts['host']) . $port . $path . $query . $fragment;
    }

    private function normalizeUrlPath(string $path): string
    {
        $segments = [];
        $parts = explode('/', str_replace('\\', '/', $path));

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $part;
        }

        return '/' . implode('/', $segments);
    }

    private function buildFaceId(string $family, string $weight, string $style, string $format, string $fontUrl, string $unicodeRange): string
    {
        $identity = [
            trim($family),
            $weight,
            $style,
            $format,
            $fontUrl,
            preg_replace('/\s+/', ' ', trim($unicodeRange)) ?: '',
        ];

        return 'face-' . substr(hash('sha256', implode('|', $identity)), 0, 16);
    }

    /**
     * @param list<DryRunFace> $faces
     * @return list<DryRunFace>
     */
    private function annotateDuplicateMatches(array $faces): array
    {
        $repository = $this->imports ?? new ImportRepository();
        $library = $repository->all();

        if ($library === []) {
            return $faces;
        }

        foreach ($faces as $index => $face) {
            $matches = $this->duplicateMatchesForFace($face, $library);

            if ($matches === []) {
                $faces[$index]['duplicate_matches'] = [];
                $faces[$index]['duplicate_summary'] = [
                    'has_matches' => false,
                    'has_replaceable_custom_matches' => false,
                    'default_action' => 'import',
                    'self_hosted_matches' => 0,
                    'remote_matches' => 0,
                ];
                continue;
            }

            $replaceable = count(array_filter($matches, static fn (array $match): bool => !empty($match['replaceable'])));
            $selfHosted = count(array_filter($matches, static fn (array $match): bool => ($match['delivery_type'] ?? '') === 'self_hosted'));
            $remote = count(array_filter($matches, static fn (array $match): bool => ($match['delivery_type'] ?? '') === 'remote'));
            $faces[$index]['duplicate_matches'] = $matches;
            $faces[$index]['duplicate_summary'] = [
                'has_matches' => true,
                'has_replaceable_custom_matches' => $replaceable > 0,
                'default_action' => 'skip',
                'self_hosted_matches' => $selfHosted,
                'remote_matches' => $remote,
            ];
        }

        return $faces;
    }

    /**
     * @param DryRunFace $face
     * @param array<string, mixed> $library
     * @return list<array<string, mixed>>
     */
    private function duplicateMatchesForFace(array $face, array $library): array
    {
        $matches = [];
        $incomingSlug = FontUtils::slugify($this->stringValue($face, 'slug') ?: $this->stringValue($face, 'family'));
        $incomingIdentity = $this->duplicateIdentityForFace($face, '');

        if ($incomingSlug === '') {
            return [];
        }

        foreach ($library as $family) {
            if (!is_array($family)) {
                continue;
            }

            $family = FontUtils::normalizeStringKeyedMap($family);
            $familySlug = FontUtils::slugify($this->stringValue($family, 'slug') ?: $this->stringValue($family, 'family'));

            if ($familySlug !== $incomingSlug) {
                continue;
            }

            foreach (FontUtils::normalizeStringKeyedMap($family['delivery_profiles'] ?? null) as $deliveryId => $profile) {
                if (!is_array($profile)) {
                    continue;
                }

                $profile = FontUtils::normalizeStringKeyedMap($profile);
                $deliveryType = $this->normalizeDuplicateDeliveryType($this->stringValue($profile, 'type'));

                if ($deliveryType === '') {
                    continue;
                }

                foreach (FontUtils::normalizeListOfStringKeyedMaps($profile['faces'] ?? null) as $existingFace) {
                    $format = $this->matchingFormat($face, $existingFace);

                    if ($format === '') {
                        continue;
                    }

                    if ($this->duplicateIdentityForFace($existingFace, $format) !== $incomingIdentity) {
                        continue;
                    }

                    $customCss = $this->isCustomCssProfile($profile, $existingFace);
                    $matches[] = [
                        'family' => $this->stringValue($family, 'family'),
                        'family_slug' => $familySlug,
                        'delivery_id' => sanitize_key((string) $deliveryId),
                        'delivery_label' => $this->stringValue($profile, 'label'),
                        'provider' => $this->stringValue($profile, 'provider'),
                        'delivery_type' => $deliveryType,
                        'format' => $format,
                        'replaceable' => $customCss,
                        'protected' => !$customCss,
                    ];
                }
            }
        }

        return $matches;
    }

    /**
     * @param array<string, mixed> $face
     * @return array<string, mixed>
     */
    private function duplicateIdentityForFace(array $face, string $format): array
    {
        $format = $format !== '' ? $format : strtolower($this->stringValue($face, 'format'));

        return [
            'family_slug' => FontUtils::slugify($this->stringValue($face, 'slug') ?: $this->stringValue($face, 'family')),
            'weight' => FontUtils::normalizeWeight($this->stringValue($face, 'weight', '400')),
            'style' => FontUtils::normalizeStyle($this->stringValue($face, 'style', 'normal')),
            'format' => $format,
            'unicode_range' => $this->normalizeDuplicateUnicodeRange($this->stringValue($face, 'unicode_range')),
            'axes' => $this->normalizeDuplicateAxes($face['axes'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $incomingFace
     * @param array<string, mixed> $existingFace
     */
    private function matchingFormat(array $incomingFace, array $existingFace): string
    {
        $format = strtolower($this->stringValue($incomingFace, 'format'));

        if ($format === '') {
            return '';
        }

        $files = FontUtils::normalizeStringKeyedMap($existingFace['files'] ?? null);
        $paths = FontUtils::normalizeStringKeyedMap($existingFace['paths'] ?? null);

        return array_key_exists($format, $files) || array_key_exists($format, $paths) ? $format : '';
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $face
     */
    private function isCustomCssProfile(array $profile, array $face): bool
    {
        $faceProvider = FontUtils::normalizeStringKeyedMap($face['provider'] ?? null);
        $profileMeta = FontUtils::normalizeStringKeyedMap($profile['meta'] ?? null);

        return $this->stringValue($profile, 'provider') === 'custom'
            && $this->stringValue($face, 'source') === 'custom'
            && $this->stringValue($profileMeta, 'source_type') === 'custom_css_url'
            && $this->stringValue($faceProvider, 'type') === 'custom_css';
    }

    private function normalizeDuplicateDeliveryType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'self_hosted' => 'self_hosted',
            'cdn' => 'remote',
            default => '',
        };
    }

    private function normalizeDuplicateUnicodeRange(string $unicodeRange): string
    {
        return preg_replace('/\s+/', '', strtoupper(trim($unicodeRange))) ?: '';
    }

    private function normalizeDuplicateAxes(mixed $axes): string
    {
        $normalized = FontUtils::normalizeAxesMap($axes);

        if ($normalized === []) {
            return '';
        }

        return (string) wp_json_encode($normalized);
    }

    private function isAbsoluteUrlReference(string $url): bool
    {
        return str_starts_with($url, '//')
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1;
    }

    /**
     * @param array<string, FontValidation> $fontValidationCache
     * @return FontValidation
     */
    private function cachedFontValidation(string $fontUrl, string $format, array &$fontValidationCache): array
    {
        $cacheKey = $format . '|' . $fontUrl;

        if (!isset($fontValidationCache[$cacheKey])) {
            $fontValidationCache[$cacheKey] = $this->validateFontUrl($fontUrl, $format);
        }

        return $fontValidationCache[$cacheKey];
    }

    /**
     * @return FontValidation
     */
    private function unsupportedFontValidation(string $format): array
    {
        return [
            'status' => 'unsupported',
            'method' => 'format check',
            'content_type' => '',
            'content_length' => 0,
            'notes' => [sprintf(
                /* translators: %s is the unsupported font format label. */
                __('%s sources are visible for review, but only WOFF2 and WOFF can be imported in this flow.', 'tasty-fonts'),
                strtoupper($format !== '' ? $format : 'UNKNOWN')
            )],
            'warnings' => [],
        ];
    }

    /**
     * @return FontValidation
     */
    private function validateFontUrl(string $fontUrl, string $format): array
    {
        $result = $this->getValidator()->validateFontUrl($fontUrl, $format);

        if ($result->status === ValidationResult::STATUS_INVALID) {
            $message = match ($result->code) {
                ValidationResult::HEAD_FAILED,
                ValidationResult::HTTP_ERROR => sprintf(
                    /* translators: %d is the HTTP status code returned by the font URL. */
                    __('The font URL returned HTTP %d.', 'tasty-fonts'),
                    $result->httpStatus
                ),
                ValidationResult::TOO_LARGE => sprintf(
                    /* translators: %s is the maximum font response size. */
                    __('The font file is larger than the %s dry-run limit.', 'tasty-fonts'),
                    size_format(self::FONT_SIZE_LIMIT_BYTES)
                ),
                ValidationResult::CONTENT_TYPE_ERROR => sprintf(
                    /* translators: 1: returned content type, 2: expected font format label. */
                    __('The font URL returned %1$s instead of %2$s font content.', 'tasty-fonts'),
                    $result->contentType,
                    strtoupper($format)
                ),
                ValidationResult::SIGNATURE_MISMATCH => sprintf(
                    /* translators: %s is the expected font format label. */
                    __('The downloaded bytes do not match a %s font signature.', 'tasty-fonts'),
                    strtoupper($format)
                ),
                ValidationResult::TIMEOUT => __('The font URL request timed out.', 'tasty-fonts'),
                ValidationResult::REQUEST_FAILED => $result->notes[0] ?? __('The font URL could not be reached for signature validation.', 'tasty-fonts'),
                default => $result->notes[0] ?? __('The font URL could not be reached for signature validation.', 'tasty-fonts'),
            };

            return [
                'status' => 'invalid',
                'method' => $result->method,
                'content_type' => $result->contentType,
                'content_length' => $result->contentLength,
                'notes' => [$message],
                'warnings' => [],
            ];
        }

        $notes = $result->notes;
        $notes[] = sprintf(
            /* translators: %s is the font format label. */
            __('%s signature matched.', 'tasty-fonts'),
            strtoupper($format)
        );

        return [
            'status' => $result->status,
            'method' => $result->method,
            'content_type' => $result->contentType,
            'content_length' => $result->contentLength,
            'notes' => array_values(array_filter($notes)),
            'warnings' => $result->warnings,
        ];
    }

    private function hasHttpsSchemeAndHost(string $url): bool
    {
        $parts = wp_parse_url($url);

        if (!is_array($parts)) {
            return false;
        }

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && trim((string) ($parts['host'] ?? '')) !== '';
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = trim($host, '.');
        $host = trim($host, '[]');

        return $host;
    }

    private function cssTooLargeError(): WP_Error
    {
        return new WP_Error(
            'tasty_fonts_custom_css_too_large',
            sprintf(
                /* translators: %s is the maximum CSS response size. */
                __('That stylesheet is larger than the %s dry-run limit. Split it into a smaller CSS file and try again.', 'tasty-fonts'),
                size_format(self::CSS_SIZE_LIMIT_BYTES)
            )
        );
    }

    private function hostForUrl(string $url): string
    {
        return strtolower((string) (wp_parse_url($url, PHP_URL_HOST) ?: ''));
    }

    /**
     * @param list<DryRunFace> $faces
     * @return DryRunPlan
     */
    private function buildPlan(string $stylesheetUrl, array $faces): array
    {
        $faces = $this->annotateDuplicateMatches($faces);
        $families = [];

        foreach ($faces as $face) {
            $slug = $this->stringValue($face, 'slug');

            if (!isset($families[$slug])) {
                $families[$slug] = [
                    'family' => $this->stringValue($face, 'family'),
                    'slug' => $slug,
                    'fallback' => 'sans-serif',
                    'faces' => [],
                ];
            }

            $families[$slug]['faces'][] = $face;
        }

        $familyList = array_values($families);
        $validFaceCount = count(array_filter($faces, static fn (array $face): bool => ($face['status'] ?? '') === 'valid'));
        $warningFaceCount = count(array_filter($faces, static fn (array $face): bool => ($face['status'] ?? '') === 'warning'));
        $invalidFaceCount = count(array_filter($faces, static fn (array $face): bool => ($face['status'] ?? '') === 'invalid'));
        $unsupportedFaceCount = count(array_filter($faces, static fn (array $face): bool => ($face['status'] ?? '') === 'unsupported'));
        $duplicateFaceCount = 0;
        $replaceableDuplicateFaceCount = 0;

        foreach ($faces as $face) {
            $duplicateSummary = FontUtils::normalizeStringKeyedMap($face['duplicate_summary'] ?? null);

            if (!empty($duplicateSummary['has_matches'])) {
                $duplicateFaceCount++;
            }

            if (!empty($duplicateSummary['has_replaceable_custom_matches'])) {
                $replaceableDuplicateFaceCount++;
            }
        }
        $selectableFaceCount = $validFaceCount + $warningFaceCount;

        return [
            'status' => 'dry_run',
            'message' => sprintf(
                /* translators: %d is the number of selectable font faces detected in the dry run. */
                _n('Found %d selectable font face. Review warnings before importing.', 'Found %d selectable font faces. Review warnings before importing.', $selectableFaceCount, 'tasty-fonts'),
                $selectableFaceCount
            ),
            'plan' => [
                'source' => [
                    'type' => 'custom_css_url',
                    'url' => $stylesheetUrl,
                    'host' => $this->hostForUrl($stylesheetUrl),
                ],
                'families' => $familyList,
                'counts' => [
                    'families' => count($familyList),
                    'faces' => count($faces),
                    'valid_faces' => $validFaceCount,
                    'warning_faces' => $warningFaceCount,
                    'invalid_faces' => $invalidFaceCount,
                    'unsupported_faces' => $unsupportedFaceCount,
                    'duplicate_faces' => $duplicateFaceCount,
                    'replaceable_duplicate_faces' => $replaceableDuplicateFaceCount,
                ],
                'warnings' => $this->buildPlanWarnings($warningFaceCount, $invalidFaceCount, $unsupportedFaceCount, $duplicateFaceCount, $replaceableDuplicateFaceCount),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function buildPlanWarnings(int $warningFaceCount, int $invalidFaceCount, int $unsupportedFaceCount, int $duplicateFaceCount = 0, int $replaceableDuplicateFaceCount = 0): array
    {
        $warnings = [
            __('Remote serving uses third-party font URLs in generated CSS. Confirm licensing, visitor privacy, and source availability before choosing remote serving.', 'tasty-fonts'),
        ];

        if ($warningFaceCount > 0) {
            $warnings[] = __('Some font faces have non-blocking warnings and remain selectable for review.', 'tasty-fonts');
        }

        if ($invalidFaceCount > 0) {
            $warnings[] = __('Some font faces failed validation and are disabled.', 'tasty-fonts');
        }

        if ($unsupportedFaceCount > 0) {
            $warnings[] = __('Some font sources use unsupported formats and are disabled for this import flow.', 'tasty-fonts');
        }

        if ($duplicateFaceCount > 0) {
            $warnings[] = __('Matching faces already exist in the library. Duplicate handling defaults to skipping matching entries.', 'tasty-fonts');
        }

        if ($replaceableDuplicateFaceCount > 0) {
            $warnings[] = __('Advanced replacement is available only for matching custom CSS faces; Google, Bunny, Adobe, and local upload profiles remain protected.', 'tasty-fonts');
        }

        return $warnings;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;

        return is_scalar($value) ? (string) $value : '';
    }

    private function trimCssString(string $value): string
    {
        $value = trim($value);

        return trim($value, "\"'");
    }
}
