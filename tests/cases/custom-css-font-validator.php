<?php

declare(strict_types=1);

use TastyFonts\CustomCss\CustomCssFontValidator;
use TastyFonts\CustomCss\ValidationResult;

$tests['custom_css_font_validator_detects_woff2_signature'] = static function (): void {
    $validator = new CustomCssFontValidator();
    assertTrueValue($validator->fontSignatureMatches('wOF2' . 'test', 'woff2'), 'woff2 signature should match.');
};

$tests['custom_css_font_validator_detects_woff_signature'] = static function (): void {
    $validator = new CustomCssFontValidator();
    assertTrueValue($validator->fontSignatureMatches('wOFF' . 'test', 'woff'), 'woff signature should match.');
};

$tests['custom_css_font_validator_rejects_unknown_signatures'] = static function (): void {
    $validator = new CustomCssFontValidator();
    assertFalseValue($validator->fontSignatureMatches('NOTF', 'woff2'), 'Unknown signature should not match woff2.');
    assertFalseValue($validator->fontSignatureMatches('NOTF', 'woff'), 'Unknown signature should not match woff.');
    assertFalseValue($validator->fontSignatureMatches('', 'woff2'), 'Empty body should not match.');
    assertFalseValue($validator->fontSignatureMatches('abc', 'woff2'), 'Truncated body should not match.');
};

$tests['custom_css_font_validator_rejects_non_supported_formats_for_signatures'] = static function (): void {
    $validator = new CustomCssFontValidator();
    assertFalseValue($validator->fontSignatureMatches('wOF2', 'ttf'), 'ttf should not be supported by signature match.');
    assertFalseValue($validator->fontSignatureMatches('wOF2', 'otf'), 'otf should not be supported by signature match.');
    assertFalseValue($validator->fontSignatureMatches('wOF2', 'eot'), 'eot should not be supported by signature match.');
    assertFalseValue($validator->fontSignatureMatches('wOF2', 'svg'), 'svg should not be supported by signature match.');
};

$tests['custom_css_font_validator_blocked_host_list'] = static function (): void {
    $validator = new CustomCssFontValidator();
    assertTrueValue($validator->isBlockedHost('localhost'), 'localhost should be blocked.');
    assertTrueValue($validator->isBlockedHost('127.0.0.1'), 'loopback IPv4 should be blocked.');
    assertTrueValue($validator->isBlockedHost('0.0.0.0'), '0.0.0.0 should be blocked.');
    assertTrueValue($validator->isBlockedHost('::1'), 'loopback IPv6 should be blocked.');
    assertTrueValue($validator->isBlockedHost('10.0.0.1'), 'private 10.x should be blocked.');
    assertTrueValue($validator->isBlockedHost('172.16.0.1'), 'private 172.16.x should be blocked.');
    assertTrueValue($validator->isBlockedHost('172.31.255.255'), 'private 172.31.x should be blocked.');
    assertTrueValue($validator->isBlockedHost('192.168.1.1'), 'private 192.168.x should be blocked.');
    assertTrueValue($validator->isBlockedHost(''), 'empty host should be blocked.');
    assertTrueValue($validator->isBlockedHost('169.254.1.1'), 'link-local should be blocked.');
    assertTrueValue($validator->isBlockedHost('fonts.test'), '.test suffix should be blocked.');
    assertTrueValue($validator->isBlockedHost('fonts.local'), '.local suffix should be blocked.');
    assertFalseValue($validator->isBlockedHost('fonts.example.com'), 'public domain should not be blocked.');
    assertFalseValue($validator->isBlockedHost('8.8.8.8'), 'public IP should not be blocked.');
};

$tests['custom_css_font_validator_url_safety_checks'] = static function (): void {
    $validator = new CustomCssFontValidator();

    $error = $validator->validatePublicHttpsUrl('https://fonts.example.com/font.woff2');
    assertSameValue(null, $error, 'Valid HTTPS URL should pass.');

    $error = $validator->validatePublicHttpsUrl('http://fonts.example.com/font.woff2');
    assertWpErrorCode('tasty_fonts_custom_css_url_invalid', $error, 'HTTP URL should be invalid.');

    $error = $validator->validatePublicHttpsUrl('not-a-url');
    assertWpErrorCode('tasty_fonts_custom_css_url_invalid', $error, 'Non-URL should be invalid.');

    $error = $validator->validatePublicHttpsUrl('');
    assertWpErrorCode('tasty_fonts_custom_css_url_invalid', $error, 'Empty string should be invalid.');

    $error = $validator->validatePublicHttpsUrl('https://user:pass@fonts.example.com/font.woff2');
    assertWpErrorCode('tasty_fonts_custom_css_url_invalid', $error, 'Credential URL should be invalid.');
};

$tests['custom_css_font_validator_internal_url_allowlist_by_mode'] = static function (): void {
    $validator = new CustomCssFontValidator();

    assertFalseValue($validator->isInternalUrlAllowed('https://fonts.test/font.woff2', 'fonts.test', 'dry-run'), 'dry-run should deny internal by default.');
    assertFalseValue($validator->isInternalUrlAllowed('https://fonts.test/font.woff2', 'fonts.test', 'import'), 'import should deny internal by default.');

    add_filter(
        'tasty_fonts_custom_css_allow_internal_dry_run_url',
        static fn (): bool => true,
        10,
        4
    );
    assertTrueValue($validator->isInternalUrlAllowed('https://fonts.test/font.woff2', 'fonts.test', 'dry-run'), 'dry-run filter should allow when set.');
    assertFalseValue($validator->isInternalUrlAllowed('https://fonts.test/font.woff2', 'fonts.test', 'import'), 'import should still deny when only dry-run filter is set.');

    add_filter(
        'tasty_fonts_custom_css_allow_internal_final_import_url',
        static fn (): bool => true,
        10,
        4
    );
    assertTrueValue($validator->isInternalUrlAllowed('https://fonts.test/font.woff2', 'fonts.test', 'import'), 'import filter should allow when set.');

    resetTestState();
};

$tests['custom_css_font_validator_content_type_validation'] = static function (): void {
    $validator = new CustomCssFontValidator();

    assertSameValue(null, $validator->validateContentType('font/woff2', 'woff2'), 'font/woff2 should be valid.');
    assertSameValue(null, $validator->validateContentType('font/woff', 'woff'), 'font/woff should be valid.');
    assertSameValue(null, $validator->validateContentType('application/font-woff', 'woff'), 'application/font-woff should be valid.');
    assertSameValue(null, $validator->validateContentType('application/font-woff2', 'woff2'), 'application/font-woff2 should be valid.');
    assertSameValue(null, $validator->validateContentType('application/octet-stream', 'woff2'), 'application/octet-stream should be valid.');
    assertSameValue(null, $validator->validateContentType('application/x-font-woff', 'woff'), 'application/x-font-woff should be valid.');
    assertSameValue(null, $validator->validateContentType('application/x-font-woff2', 'woff2'), 'application/x-font-woff2 should be valid.');
    assertSameValue(null, $validator->validateContentType('', 'woff2'), 'Empty content-type should be valid.');

    $errorCode = $validator->validateContentType('text/html', 'woff2');
    assertSameValue(ValidationResult::CONTENT_TYPE_ERROR, $errorCode, 'text/html should be invalid for woff2.');

    $errorCode = $validator->validateContentType('image/png', 'woff');
    assertSameValue(ValidationResult::CONTENT_TYPE_ERROR, $errorCode, 'image/png should be invalid for woff.');
};

$tests['custom_css_font_validator_timeout_detection'] = static function (): void {
    $validator = new CustomCssFontValidator();

    assertTrueValue($validator->isTimeoutMessage('cURL error 28: Operation timed out'), 'cURL error 28 should be timeout.');
    assertTrueValue($validator->isTimeoutMessage('Request timed out after 5000ms'), 'timed out should be timeout.');
    assertTrueValue($validator->isTimeoutMessage('Connection timeout'), 'timeout should be timeout.');
    assertFalseValue($validator->isTimeoutMessage('404 Not Found'), '404 should not be timeout.');
    assertFalseValue($validator->isTimeoutMessage(''), 'empty should not be timeout.');
};

$tests['custom_css_font_validator_head_request_behavior'] = static function (): void {
    resetTestState();

    global $remoteRequestResponses;
    global $remoteGetResponses;
    $validator = new CustomCssFontValidator();
    $url = 'https://cdn.example.com/font.woff2';

    $remoteRequestResponses['HEAD ' . $url] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2', 'content-length' => '1024'],
        'body' => '',
    ];
    $remoteGetResponses[$url] = [
        'response' => ['code' => 206],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => '16',
            'content-range' => 'bytes 0-15/1024',
            'access-control-allow-origin' => '*',
        ],
        'body' => 'wOF2' . str_repeat('x', 12),
    ];

    $result = $validator->validateFontUrl($url, 'woff2');
    assertSameValue(ValidationResult::STATUS_VALID, $result->status, 'Successful HEAD should lead to valid result.');

    resetTestState();
    $remoteRequestResponses['HEAD ' . $url] = [
        'response' => ['code' => 404],
        'headers' => ['content-type' => 'text/html'],
        'body' => '',
    ];
    $result = $validator->validateFontUrl($url, 'woff2');
    assertSameValue(ValidationResult::STATUS_INVALID, $result->status, '404 HEAD should lead to invalid result.');
    assertSameValue(ValidationResult::HEAD_FAILED, $result->code, '404 HEAD should set HEAD_FAILED code.');
    assertSameValue(404, $result->httpStatus, '404 HEAD should preserve HTTP status.');

    resetTestState();
    $remoteRequestResponses['HEAD ' . $url] = new WP_Error('http_request_failed', 'cURL error 28: Operation timed out');
    $remoteGetResponses[$url] = [
        'response' => ['code' => 206],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => '16',
            'content-range' => 'bytes 0-15/1024',
            'access-control-allow-origin' => '*',
        ],
        'body' => 'wOF2' . str_repeat('x', 12),
    ];
    $result = $validator->validateFontUrl($url, 'woff2');
    assertSameValue(ValidationResult::STATUS_VALID, $result->status, 'HEAD timeout should fallback to capped GET and still validate.');

    resetTestState();
    $remoteRequestResponses['HEAD ' . $url] = [
        'response' => ['code' => 405],
        'headers' => ['content-type' => 'text/plain'],
        'body' => '',
    ];
    $remoteGetResponses[$url] = [
        'response' => ['code' => 206],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => '1024',
            'content-range' => 'bytes 0-15/1024',
        ],
        'body' => 'wOF2' . str_repeat('x', 12),
    ];
    $result = $validator->validateFontUrl($url, 'woff2');
    assertSameValue(ValidationResult::STATUS_WARNING, $result->status, 'HEAD 405 fallback should validate with capped GET but warn about missing CORS.');
    assertSameValue('capped GET fallback', $result->method, 'HEAD 405 should trigger capped GET fallback.');
    assertTrueValue(
        count($result->warnings) > 0 && str_contains($result->warnings[0], 'CORS'),
        'Missing Access-Control-Allow-Origin should produce a CORS warning.'
    );
};

$tests['custom_css_font_validator_range_get_behavior'] = static function (): void {
    resetTestState();

    global $remoteRequestResponses;
    global $remoteGetResponses;
    $validator = new CustomCssFontValidator();
    $url = 'https://cdn.example.com/font.woff2';

    $remoteRequestResponses['HEAD ' . $url] = [
        'response' => ['code' => 405],
        'headers' => ['content-type' => 'text/plain'],
        'body' => '',
    ];
    $remoteGetResponses[$url] = [
        'response' => ['code' => 206],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => '16',
            'content-range' => 'bytes 0-15/1024',
            'access-control-allow-origin' => '*',
        ],
        'body' => 'wOF2' . str_repeat('x', 12),
    ];

    $result = $validator->validateFontUrl($url, 'woff2');
    assertSameValue(ValidationResult::STATUS_VALID, $result->status, 'Partial content range GET should validate.');

    resetTestState();
    $remoteRequestResponses['HEAD ' . $url] = [
        'response' => ['code' => 405],
        'headers' => ['content-type' => 'text/plain'],
        'body' => '',
    ];
    $remoteGetResponses[$url] = [
        'response' => ['code' => 200],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => '16',
            'access-control-allow-origin' => '*',
        ],
        'body' => 'wOF2' . str_repeat('x', 12),
    ];
    $result = $validator->validateFontUrl($url, 'woff2');
    assertSameValue(ValidationResult::STATUS_VALID, $result->status, 'Full 200 GET fallback should also validate.');

    resetTestState();
    $remoteRequestResponses['HEAD ' . $url] = [
        'response' => ['code' => 405],
        'headers' => ['content-type' => 'text/plain'],
        'body' => '',
    ];
    $remoteGetResponses[$url] = [
        'response' => ['code' => 206],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => '2',
            'content-range' => 'bytes 0-1/1024',
        ],
        'body' => 'wo',
    ];
    $result = $validator->validateFontUrl($url, 'woff2');
    assertSameValue(ValidationResult::STATUS_INVALID, $result->status, 'Truncated range GET body should fail signature match.');
    assertSameValue(ValidationResult::SIGNATURE_MISMATCH, $result->code, 'Truncated body should set SIGNATURE_MISMATCH.');
};

$tests['custom_css_font_validator_enforces_filesize_limit'] = static function (): void {
    resetTestState();

    global $remoteRequestResponses;
    $validator = new CustomCssFontValidator();
    $url = 'https://cdn.example.com/huge.woff2';

    $remoteRequestResponses['HEAD ' . $url] = [
        'response' => ['code' => 200],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => '10485761',
        ],
        'body' => '',
    ];

    $result = $validator->validateFontUrl($url, 'woff2');
    assertSameValue(ValidationResult::STATUS_INVALID, $result->status, 'Oversized HEAD content-length should be invalid.');
    assertSameValue(ValidationResult::TOO_LARGE, $result->code, 'Oversized file should set TOO_LARGE.');

    resetTestState();
    $remoteRequestResponses['HEAD ' . $url] = new WP_Error('http_request_failed', 'Unavailable');
    global $remoteGetResponses;
    $remoteGetResponses[$url] = [
        'response' => ['code' => 206],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => '16',
            'content-range' => 'bytes 0-15/10485761',
        ],
        'body' => 'wOF2' . str_repeat('x', 12),
    ];
    $result = $validator->validateFontUrl($url, 'woff2');
    assertSameValue(ValidationResult::STATUS_INVALID, $result->status, 'Oversized range GET should be invalid.');
    assertSameValue(ValidationResult::TOO_LARGE, $result->code, 'Oversized range GET should set TOO_LARGE.');
};

$tests['custom_css_font_validator_cors_warning_collection'] = static function (): void {
    resetTestState();

    global $remoteRequestResponses;
    global $remoteGetResponses;
    $validator = new CustomCssFontValidator();
    $url = 'https://cdn.example.com/font.woff2';

    $remoteRequestResponses['HEAD ' . $url] = [
        'response' => ['code' => 405],
        'headers' => ['content-type' => 'text/plain'],
        'body' => '',
    ];
    $remoteGetResponses[$url] = [
        'response' => ['code' => 206],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => '1024',
            'content-range' => 'bytes 0-15/1024',
        ],
        'body' => 'wOF2' . str_repeat('x', 12),
    ];

    $result = $validator->validateFontUrl($url, 'woff2');
    assertContainsValue(
        'Access-Control-Allow-Origin',
        implode(' ', $result->warnings),
        'Missing CORS header should produce a warning.'
    );

    resetTestState();
    $remoteRequestResponses['HEAD ' . $url] = [
        'response' => ['code' => 405],
        'headers' => ['content-type' => 'text/plain'],
        'body' => '',
    ];
    $remoteGetResponses[$url] = [
        'response' => ['code' => 206],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => '1024',
            'content-range' => 'bytes 0-15/1024',
            'access-control-allow-origin' => '*',
        ],
        'body' => 'wOF2' . str_repeat('x', 12),
    ];
    $result = $validator->validateFontUrl($url, 'woff2');
    assertSameValue(ValidationResult::STATUS_VALID, $result->status, 'Valid CORS should produce valid status.');
    assertSameValue([], $result->warnings, 'Wildcard CORS should not produce a warning.');
};

$tests['custom_css_font_validator_blocks_unsafe_urls_end_to_end'] = static function (): void {
    resetTestState();

    $validator = new CustomCssFontValidator();

    $result = $validator->validateFontUrl('https://192.168.1.1/font.woff2', 'woff2');
    assertSameValue(ValidationResult::STATUS_INVALID, $result->status, 'Private IP should be blocked before any HTTP request.');
    assertSameValue(ValidationResult::BLOCKED_HOST, $result->code, 'Private IP should set BLOCKED_HOST code.');

    $result = $validator->validateFontUrl('https://localhost/font.woff2', 'woff2');
    assertSameValue(ValidationResult::STATUS_INVALID, $result->status, 'localhost should be blocked.');
    assertSameValue(ValidationResult::BLOCKED_HOST, $result->code, 'localhost should set BLOCKED_HOST code.');

    $result = $validator->validateFontUrl('http://example.com/font.woff2', 'woff2');
    assertSameValue(ValidationResult::STATUS_INVALID, $result->status, 'Non-HTTPS should be blocked.');
    assertSameValue(ValidationResult::INVALID_URL, $result->code, 'Non-HTTPS should set INVALID_URL code.');
};

$tests['custom_css_font_validator_end_to_end_valid_font_url'] = static function (): void {
    resetTestState();

    global $remoteRequestResponses;
    global $remoteGetResponses;
    global $remoteRequestCalls;
    global $remoteGetCalls;
    $validator = new CustomCssFontValidator();
    $url = 'https://cdn.example.com/font.woff2';

    $remoteRequestResponses['HEAD ' . $url] = [
        'response' => ['code' => 200],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => '1024',
            'access-control-allow-origin' => '*',
        ],
        'body' => '',
    ];
    $remoteGetResponses[$url] = [
        'response' => ['code' => 206],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => '16',
            'content-range' => 'bytes 0-15/1024',
            'access-control-allow-origin' => '*',
        ],
        'body' => 'wOF2' . str_repeat('x', 12),
    ];

    $result = $validator->validateFontUrl($url, 'woff2');
    assertSameValue(ValidationResult::STATUS_VALID, $result->status, 'Valid font URL should return valid.');
    assertSameValue('HEAD + range GET', $result->method, 'Valid font should use HEAD + range GET.');
    assertSameValue('font/woff2', $result->contentType, 'Content type should be preserved.');
    assertSameValue(1024, $result->contentLength, 'Content length should be preserved.');
    assertSameValue([], $result->warnings, 'No warnings for a clean valid font.');

    assertTrueValue(count($remoteRequestCalls) === 1, 'Should make exactly one HEAD request.');
    assertSameValue('HEAD', $remoteRequestCalls[0]['method'], 'First request should be HEAD.');
    assertSameValue($url, $remoteRequestCalls[0]['url'], 'HEAD request should target the font URL.');
    assertTrueValue(($remoteRequestCalls[0]['args']['reject_unsafe_urls'] ?? false) === true, 'HEAD request should set reject_unsafe_urls.');
    assertTrueValue(($remoteRequestCalls[0]['args']['timeout'] ?? 0) == 10, 'HEAD request should set 10s timeout.');

    assertTrueValue(count($remoteGetCalls) === 1, 'Should make exactly one GET request.');
    assertSameValue($url, $remoteGetCalls[0]['url'], 'GET request should target the font URL.');
    assertTrueValue(($remoteGetCalls[0]['args']['reject_unsafe_urls'] ?? false) === true, 'GET request should set reject_unsafe_urls.');
    assertTrueValue(($remoteGetCalls[0]['args']['limit_response_size'] ?? 0) === 17, 'GET request should limit response size to 17 bytes.');
    assertTrueValue(str_contains($remoteGetCalls[0]['args']['headers']['Range'] ?? '', 'bytes=0-15'), 'GET request should include Range header.');
};

$tests['custom_css_font_validator_end_to_end_invalid_font_url'] = static function (): void {
    resetTestState();

    global $remoteRequestResponses;
    global $remoteGetResponses;
    $validator = new CustomCssFontValidator();
    $url = 'https://cdn.example.com/font.woff2';

    $remoteRequestResponses['HEAD ' . $url] = [
        'response' => ['code' => 404],
        'headers' => ['content-type' => 'text/html'],
        'body' => '',
    ];

    $result = $validator->validateFontUrl($url, 'woff2');
    assertSameValue(ValidationResult::STATUS_INVALID, $result->status, '404 font URL should return invalid.');
    assertSameValue(ValidationResult::HEAD_FAILED, $result->code, '404 should set HEAD_FAILED.');
    assertSameValue(404, $result->httpStatus, 'HTTP status should be 404.');
    assertSameValue('text/html', $result->contentType, 'HEAD failed result should preserve content type.');
    assertSameValue(0, $result->contentLength, 'HEAD failed result should preserve content length (0 when absent).');
};

$tests['custom_css_font_validator_warning_accumulation'] = static function (): void {
    resetTestState();

    global $remoteRequestResponses;
    global $remoteGetResponses;
    $validator = new CustomCssFontValidator();
    $url = 'https://cdn.example.com/font.woff2?token=abc';

    $remoteRequestResponses['HEAD ' . $url] = [
        'response' => ['code' => 405],
        'headers' => ['content-type' => 'text/plain'],
        'body' => '',
    ];
    $remoteGetResponses[$url] = [
        'response' => ['code' => 206],
        'headers' => [
            'content-type' => 'font/woff2',
            'content-length' => '1024',
            'content-range' => 'bytes 0-15/1024',
        ],
        'body' => 'wOF2' . str_repeat('x', 12),
    ];

    $result = $validator->validateFontUrl($url, 'woff2');
    assertSameValue(ValidationResult::STATUS_WARNING, $result->status, 'Query param and missing CORS should produce warning.');
    assertSameValue(2, count($result->warnings), 'Should accumulate multiple warnings.');
};
