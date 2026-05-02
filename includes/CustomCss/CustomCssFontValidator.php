<?php

declare(strict_types=1);

namespace TastyFonts\CustomCss;

use TastyFonts\Support\FontUtils;
use WP_Error;

/**
 * Owns all remote font URL validation logic for the custom CSS import pipeline.
 */
final class CustomCssFontValidator
{
    private const REQUEST_TIMEOUT = 10;
    private const FONT_SIZE_LIMIT_BYTES = 10485760;
    private const FONT_SIGNATURE_RANGE = 'bytes=0-15';
    private const FONT_SIGNATURE_LIMIT_BYTES = 17;
    private const INTERNAL_HOST_SUFFIXES = [
        '.home',
        '.internal',
        '.invalid',
        '.lan',
        '.local',
        '.localhost',
        '.test',
    ];

    /**
     * Validate a remote font URL via HEAD + range GET.
     *
     * @param 'dry-run'|'import' $mode
     */
    public function validateFontUrl(string $fontUrl, string $format, string $mode = 'dry-run'): ValidationResult
    {
        $urlError = $this->validatePublicHttpsUrl($fontUrl, 'font', $mode);

        if ($urlError instanceof WP_Error) {
            $code = match ($urlError->get_error_code()) {
                'tasty_fonts_custom_css_font_url_blocked',
                'tasty_fonts_custom_css_url_blocked' => ValidationResult::BLOCKED_HOST,
                default => ValidationResult::INVALID_URL,
            };

            return new ValidationResult(
                status: ValidationResult::STATUS_INVALID,
                code: $code,
                method: 'URL validation',
            );
        }

        $notes = [];
        $warnings = [];
        $method = 'HEAD + range GET';
        $headers = [];
        $contentType = '';
        $contentLength = 0;
        $httpStatus = 0;

        $headResponse = $this->requestFontHead($fontUrl);

        if (is_wp_error($headResponse)) {
            $notes[] = __('HEAD check was unavailable; used a capped GET fallback.', 'tasty-fonts');
            $method = 'capped GET fallback';
        } else {
            $headStatus = (int) wp_remote_retrieve_response_code($headResponse);
            $httpStatus = $headStatus;

            if (!$this->isSuccessfulHttpStatus($headStatus)) {
                if (!$this->canFallbackAfterHeadStatus($headStatus)) {
                    $headHeaders = $this->headersFromResponse($headResponse);

                    return new ValidationResult(
                        status: ValidationResult::STATUS_INVALID,
                        code: ValidationResult::HEAD_FAILED,
                        method: 'HEAD',
                        headers: $headHeaders,
                        contentType: $this->headerValue($headHeaders, 'content-type'),
                        contentLength: $this->contentLengthFromHeaders($headHeaders),
                        httpStatus: $headStatus,
                    );
                }

                $notes[] = sprintf(
                    /* translators: %d is the HTTP status code returned to a HEAD request. */
                    __('HEAD returned HTTP %d; used a capped GET fallback.', 'tasty-fonts'),
                    $headStatus
                );
                $method = 'capped GET fallback';
            } else {
                $headers = $this->headersFromResponse($headResponse);
                $contentType = $this->headerValue($headers, 'content-type');
                $contentLength = $this->contentLengthFromHeaders($headers);

                if ($contentLength > self::FONT_SIZE_LIMIT_BYTES) {
                    return new ValidationResult(
                        status: ValidationResult::STATUS_INVALID,
                        code: ValidationResult::TOO_LARGE,
                        method: 'HEAD',
                        headers: $headers,
                        contentType: $contentType,
                        contentLength: $contentLength,
                    );
                }

                $contentTypeCode = $this->validateContentType($contentType, $format);

                if ($contentTypeCode !== null) {
                    return new ValidationResult(
                        status: ValidationResult::STATUS_INVALID,
                        code: $contentTypeCode,
                        method: 'HEAD',
                        headers: $headers,
                        contentType: $contentType,
                        contentLength: $contentLength,
                    );
                }
            }
        }

        $signatureResponse = $this->requestFontSignature($fontUrl);

        if (is_wp_error($signatureResponse)) {
            $message = $signatureResponse->get_error_message();

            if ($this->isTimeoutMessage($message)) {
                return new ValidationResult(
                    status: ValidationResult::STATUS_INVALID,
                    code: ValidationResult::TIMEOUT,
                    method: $method,
                    headers: $headers,
                    contentType: $contentType,
                    contentLength: $contentLength,
                );
            }

            $notes[] = $message !== ''
                ? $message
                : __('The font URL could not be reached for signature validation.', 'tasty-fonts');

            return new ValidationResult(
                status: ValidationResult::STATUS_INVALID,
                code: ValidationResult::REQUEST_FAILED,
                method: $method,
                headers: $headers,
                contentType: $contentType,
                contentLength: $contentLength,
                notes: $notes,
            );
        }

        $signatureStatus = (int) wp_remote_retrieve_response_code($signatureResponse);
        $httpStatus = $signatureStatus;

        if (!$this->isSuccessfulHttpStatus($signatureStatus)) {
            return new ValidationResult(
                status: ValidationResult::STATUS_INVALID,
                code: ValidationResult::HTTP_ERROR,
                method: $method,
                headers: $this->headersFromResponse($signatureResponse),
                contentType: $contentType,
                contentLength: $contentLength,
                httpStatus: $signatureStatus,
            );
        }

        $signatureHeaders = $this->headersFromResponse($signatureResponse);
        $headers = array_replace($headers, $signatureHeaders);
        $contentType = $contentType !== '' ? $contentType : $this->headerValue($headers, 'content-type');
        $signatureLength = $this->contentLengthFromHeaders($signatureHeaders);
        $rangeTotal = $this->contentRangeTotalFromHeaders($signatureHeaders);
        $contentLength = $contentLength > 0
            ? $contentLength
            : ($rangeTotal > 0 ? $rangeTotal : $signatureLength);

        $maxDetectedSize = max($contentLength, $rangeTotal);

        if (
            $maxDetectedSize > self::FONT_SIZE_LIMIT_BYTES
            || strlen(wp_remote_retrieve_body($signatureResponse)) > self::FONT_SIZE_LIMIT_BYTES
        ) {
            return new ValidationResult(
                status: ValidationResult::STATUS_INVALID,
                code: ValidationResult::TOO_LARGE,
                method: $method,
                headers: $headers,
                contentType: $contentType,
                contentLength: $contentLength,
            );
        }

        $contentTypeCode = $this->validateContentType($contentType, $format);

        if ($contentTypeCode !== null) {
            return new ValidationResult(
                status: ValidationResult::STATUS_INVALID,
                code: $contentTypeCode,
                method: $method,
                headers: $headers,
                contentType: $contentType,
                contentLength: $contentLength,
            );
        }

        $body = wp_remote_retrieve_body($signatureResponse);

        if (!$this->fontSignatureMatches($body, $format)) {
            return new ValidationResult(
                status: ValidationResult::STATUS_INVALID,
                code: ValidationResult::SIGNATURE_MISMATCH,
                method: $method,
                headers: $headers,
                contentType: $contentType,
                contentLength: $contentLength,
            );
        }

        if ($contentLength <= 0) {
            $warnings[] = __('The font response did not expose a reliable size. Final import will recheck before saving.', 'tasty-fonts');
        }

        $corsWarning = $this->corsWarningForFontUrl($fontUrl, $headers);

        if ($corsWarning !== '') {
            $warnings[] = $corsWarning;
        }

        if ((string) wp_parse_url($fontUrl, PHP_URL_QUERY) !== '') {
            $warnings[] = __('This font URL includes query parameters. If it is signed or temporary, remote serving may stop working when it expires.', 'tasty-fonts');
        }

        return new ValidationResult(
            status: $warnings === [] ? ValidationResult::STATUS_VALID : ValidationResult::STATUS_WARNING,
            method: $method,
            headers: $headers,
            contentType: $contentType,
            contentLength: $contentLength,
            warnings: $warnings,
            notes: $notes,
        );
    }

    /**
     * Validate that a URL is a safe public HTTPS URL.
     *
     * @param 'font'|'stylesheet' $kind
     * @param 'dry-run'|'import'  $mode
     */
    public function validatePublicHttpsUrl(string $url, string $kind = 'font', string $mode = 'dry-run'): ?WP_Error
    {
        if (!$this->hasHttpsSchemeAndHost($url)) {
            return new WP_Error(
                'tasty_fonts_custom_css_url_invalid',
                $kind === 'font'
                    ? __('Font URLs must be valid public HTTPS URLs.', 'tasty-fonts')
                    : __('Enter a valid public HTTPS CSS stylesheet URL.', 'tasty-fonts')
            );
        }

        $parts = wp_parse_url($url);
        $host = $this->normalizeHost((string) ($parts['host'] ?? ''));

        if ($host === '' || !empty($parts['user'] ?? '') || !empty($parts['pass'] ?? '')) {
            return new WP_Error(
                'tasty_fonts_custom_css_url_invalid',
                $kind === 'font'
                    ? __('Font URLs must be valid public HTTPS URLs.', 'tasty-fonts')
                    : __('Enter a valid public HTTPS CSS stylesheet URL.', 'tasty-fonts')
            );
        }

        if ($this->isBlockedHost($host) && !$this->isInternalUrlAllowed($url, $host, $mode, $kind)) {
            return new WP_Error(
                $kind === 'font' ? 'tasty_fonts_custom_css_font_url_blocked' : 'tasty_fonts_custom_css_url_blocked',
                $kind === 'font'
                    ? __('A font URL points to localhost, a private address, or an internal network target. Use public HTTPS font URLs only.', 'tasty-fonts')
                    : __('That stylesheet URL points to localhost, a private address, or an internal network target. Use a public HTTPS URL.', 'tasty-fonts')
            );
        }

        return null;
    }

    public function isBlockedHost(string $host): bool
    {
        if ($host === '' || $host === 'localhost') {
            return true;
        }

        if ($this->isIpAddress($host)) {
            return !$this->isPublicIpAddress($host);
        }

        if (preg_match('/^[0-9.]+$/', $host) === 1) {
            return true;
        }

        if (!str_contains($host, '.') || preg_match('/[^a-z0-9.-]/', $host) === 1) {
            return true;
        }

        foreach (self::INTERNAL_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    public function fontSignatureMatches(string $body, string $format): bool
    {
        if (strlen($body) < 4) {
            return false;
        }

        $signature = substr($body, 0, 4);

        return match ($format) {
            'woff2' => $signature === 'wOF2',
            'woff' => $signature === 'wOFF',
            default => false,
        };
    }

    /**
     * @param 'dry-run'|'import' $mode
     * @param 'font'|'stylesheet' $kind
     */
    public function isInternalUrlAllowed(string $url, string $host, string $mode, string $kind = 'font'): bool
    {
        $filter = match ($mode) {
            'import' => 'tasty_fonts_custom_css_allow_internal_final_import_url',
            default => 'tasty_fonts_custom_css_allow_internal_dry_run_url',
        };

        return apply_filters($filter, false, $url, $host, $kind) === true;
    }

    /**
     * Return an error code if the content type does not match the expected font format.
     */
    public function validateContentType(string $contentType, string $format): ?string
    {
        $contentType = strtolower(trim(strtok($contentType, ';') ?: $contentType));

        if ($contentType === '') {
            return null;
        }

        $accepted = [
            'application/font-woff',
            'application/font-woff2',
            'application/octet-stream',
            'application/x-font-woff',
            'application/x-font-woff2',
        ];

        if (str_starts_with($contentType, 'font/') || in_array($contentType, $accepted, true) || str_contains($contentType, 'woff')) {
            return null;
        }

        return ValidationResult::CONTENT_TYPE_ERROR;
    }

    public function isTimeoutMessage(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'curl error 28');
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function requestFontHead(string $fontUrl): array|WP_Error
    {
        $args = [
            'method' => 'HEAD',
            'timeout' => self::REQUEST_TIMEOUT,
            'redirection' => 3,
            'reject_unsafe_urls' => true,
            'headers' => [
                'Accept' => 'font/woff2,font/woff,application/font-woff2,application/font-woff,*/*;q=0.5',
                'User-Agent' => FontUtils::MODERN_USER_AGENT,
            ],
        ];
        $filteredArgs = apply_filters('tasty_fonts_http_request_args', $args, $fontUrl);

        return wp_remote_request($fontUrl, FontUtils::normalizeHttpArgs(is_array($filteredArgs) ? $filteredArgs : $args));
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function requestFontSignature(string $fontUrl): array|WP_Error
    {
        $args = [
            'timeout' => self::REQUEST_TIMEOUT,
            'redirection' => 3,
            'reject_unsafe_urls' => true,
            'limit_response_size' => self::FONT_SIGNATURE_LIMIT_BYTES,
            'headers' => [
                'Accept' => 'font/woff2,font/woff,application/font-woff2,application/font-woff,*/*;q=0.5',
                'Range' => self::FONT_SIGNATURE_RANGE,
                'User-Agent' => FontUtils::MODERN_USER_AGENT,
            ],
        ];
        $filteredArgs = apply_filters('tasty_fonts_http_request_args', $args, $fontUrl);

        return wp_remote_get($fontUrl, FontUtils::normalizeHttpArgs(is_array($filteredArgs) ? $filteredArgs : $args));
    }

    private function isSuccessfulHttpStatus(int $status): bool
    {
        return $status >= 200 && $status < 300;
    }

    private function canFallbackAfterHeadStatus(int $status): bool
    {
        return in_array($status, [0, 403, 405, 501], true);
    }

    /**
     * @return array<string, string>
     */
    private function headersFromResponse(mixed $response): array
    {
        $headers = [];

        if (!is_array($response)) {
            return $headers;
        }

        foreach (['content-type', 'content-length', 'content-range'] as $header) {
            $value = trim(FontUtils::scalarStringValue(wp_remote_retrieve_header($response, $header)));

            if ($value !== '') {
                $headers[$header] = $value;
            }
        }

        $rawHeaders = $response['headers'] ?? null;

        if (is_array($rawHeaders) || $rawHeaders instanceof \Traversable) {
            foreach ($rawHeaders as $key => $value) {
                if (!is_scalar($key)) {
                    continue;
                }

                $normalized = $this->headerScalarValue($value);

                if ($normalized !== '') {
                    $headers[strtolower((string) $key)] = $normalized;
                }
            }
        }

        return $headers;
    }

    private function headerScalarValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    return trim((string) $item);
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $headers
     */
    private function headerValue(array $headers, string $header): string
    {
        return trim((string) ($headers[strtolower($header)] ?? $headers[strtoupper($header)] ?? ''));
    }

    /**
     * @param array<string, string> $headers
     */
    private function contentLengthFromHeaders(array $headers): int
    {
        $length = trim($this->headerValue($headers, 'content-length'));

        if ($length === '' || preg_match('/^\d+$/', $length) !== 1) {
            return 0;
        }

        return (int) $length;
    }

    /**
     * @param array<string, string> $headers
     */
    private function contentRangeTotalFromHeaders(array $headers): int
    {
        $range = $this->headerValue($headers, 'content-range');

        if ($range === '' || preg_match('/\/(\d+)$/', $range, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function hasHttpsSchemeAndHost(string $url): bool
    {
        $parts = wp_parse_url($url);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && trim((string) ($parts['host'] ?? '')) !== '';
    }

    private function normalizeHost(string $host): string
    {
        return trim(strtolower(trim($host)), '.[]');
    }

    private function hostForUrl(string $url): string
    {
        return strtolower((string) (wp_parse_url($url, PHP_URL_HOST) ?: ''));
    }

    private function isIpAddress(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    private function isPublicIpAddress(string $host): bool
    {
        return filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * @param array<string, string> $headers
     */
    private function corsWarningForFontUrl(string $fontUrl, array $headers): string
    {
        $fontHost = $this->hostForUrl($fontUrl);

        if ($fontHost === '') {
            return '';
        }

        $allowOrigin = trim($this->headerValue($headers, 'access-control-allow-origin'));

        if ($allowOrigin === '') {
            return __('Remote serving warning: this cross-origin font response did not expose Access-Control-Allow-Origin. Self-hosted imports are not blocked by browser CORS.', 'tasty-fonts');
        }

        if ($allowOrigin !== '*') {
            return __('Remote serving warning: this font response allows a specific origin. Verify your site origin before choosing remote serving; self-hosted imports are not blocked by browser CORS.', 'tasty-fonts');
        }

        return '';
    }
}
