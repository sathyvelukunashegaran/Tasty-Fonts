<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Google\GoogleFontsClient;
use WP_Error;

final class GoogleApiKeyValidator
{
    private readonly GoogleApiKeyValidationClient $validationClient;

    public function __construct(private readonly GoogleFontsClient $googleClient, ?GoogleApiKeyValidationClient $validationClient = null)
    {
        $this->validationClient = $validationClient ?? new GoogleApiKeyValidationAdapter($this->googleClient);
    }

    /**
     * @return array{state: string, message: string}|WP_Error
     */
    public function validate(string $googleApiKey, string $errorCode, string $fallbackMessage): array|WP_Error
    {
        $validation = $this->fetchValidation($googleApiKey);
        $state = $this->stringValue($validation, 'state', 'unknown');
        $message = $this->stringValue($validation, 'message');

        if ($state !== 'valid') {
            return new WP_Error($errorCode, $message !== '' ? $message : $fallbackMessage);
        }

        return [
            'state' => $state,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchValidation(string $googleApiKey): array
    {
        return $this->validationClient->validateApiKey($googleApiKey);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, int|string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values) || !is_scalar($values[$key])) {
            return $default;
        }

        return (string) $values[$key];
    }
}
