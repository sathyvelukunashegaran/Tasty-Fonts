<?php

declare(strict_types=1);

namespace TastyFonts\CustomCss;

/**
 * Structured result of a remote font URL validation attempt.
 */
final class ValidationResult
{
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_WARNING = 'warning';

    public const INVALID_URL = 'INVALID_URL';
    public const BLOCKED_HOST = 'BLOCKED_HOST';
    public const IP_ADDRESS = 'IP_ADDRESS';
    public const TIMEOUT = 'TIMEOUT';
    public const SIGNATURE_MISMATCH = 'SIGNATURE_MISMATCH';
    public const CONTENT_TYPE_ERROR = 'CONTENT_TYPE_ERROR';
    public const TOO_LARGE = 'TOO_LARGE';
    public const HEAD_FAILED = 'HEAD_FAILED';
    public const CORS_MISSING = 'CORS_MISSING';
    public const HTTP_ERROR = 'HTTP_ERROR';
    public const REQUEST_FAILED = 'REQUEST_FAILED';

    /**
     * @param array<string, string> $headers
     * @param list<string> $warnings
     * @param list<string> $notes
     */
    public function __construct(
        public readonly string $status,
        public readonly string $code = '',
        public readonly string $method = '',
        public readonly array $headers = [],
        public readonly string $contentType = '',
        public readonly int $contentLength = 0,
        public readonly array $warnings = [],
        public readonly array $notes = [],
        public readonly int $httpStatus = 0,
    ) {
    }
}
