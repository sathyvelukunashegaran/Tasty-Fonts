<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * @phpstan-type GoogleApiKeyStatus array{state: string, message: string, checked_at: int}
 * @phpstan-type GoogleApiKeyData array{
 *     google_api_key: string,
 *     google_api_key_status: string,
 *     google_api_key_status_message: string,
 *     google_api_key_checked_at: int
 * }
 * @phpstan-type StoredGoogleApiKeyData array{
 *     google_api_key_status: string,
 *     google_api_key_status_message: string,
 *     google_api_key_checked_at: int,
 *     google_api_key?: string,
 *     google_api_key_encrypted?: string
 * }
 */
final class GoogleApiKeyRepository implements GoogleApiKeyRepositoryInterface
{
    use RepositoryHelpers;
    public const OPTION_GOOGLE_API_KEY_DATA = 'tasty_fonts_google_api_key_data';
    private const GOOGLE_API_KEY_ENCRYPTED_FIELD = 'google_api_key_encrypted';
    private const GOOGLE_API_KEY_CIPHER_PREFIX = 'secretbox:';

    private const DEFAULT_GOOGLE_API_KEY_DATA = [
        'google_api_key' => '',
        'google_api_key_status' => 'empty',
        'google_api_key_status_message' => '',
        'google_api_key_checked_at' => 0,
    ];

    /**
     * Check whether a Google API key is stored.
     */
    public function has(): bool
    {
        $googleApiKeyData = $this->getGoogleApiKeyDataFromOptions();
        return trim($googleApiKeyData['google_api_key']) !== '';
    }

    /**
     * Return the decrypted Google API key, or an empty string when none is stored.
     */
    public function getApiKey(): string
    {
        $googleApiKeyData = $this->getGoogleApiKeyDataFromOptions();

        return $this->stringValue($googleApiKeyData, 'google_api_key');
    }

    /**
     * Persist a Google API key and reset its validation status to unknown.
     *
     * @return GoogleApiKeyStatus
     */
    public function saveApiKey(string $apiKey): array
    {
        $googleApiKeyData = $this->persistGoogleApiKeyData([
            'google_api_key' => trim(sanitize_text_field($apiKey)),
            'google_api_key_status' => 'unknown',
            'google_api_key_status_message' => '',
            'google_api_key_checked_at' => 0,
        ]);

        return [
            'state' => $googleApiKeyData['google_api_key_status'],
            'message' => $googleApiKeyData['google_api_key_status_message'],
            'checked_at' => $googleApiKeyData['google_api_key_checked_at'],
        ];
    }

    /**
     * Get the current API key status.
     *
     * @return GoogleApiKeyStatus
     */
    public function getStatus(): array
    {
        $googleApiKeyData = $this->getGoogleApiKeyDataFromOptions();
        return [
            'state' => $this->stringValue($googleApiKeyData, 'google_api_key_status', 'empty'),
            'message' => $this->stringValue($googleApiKeyData, 'google_api_key_status_message'),
            'checked_at' => $this->intValue($googleApiKeyData, 'google_api_key_checked_at'),
        ];
    }

    /**
     * Save an API key status value.
     *
     * @return GoogleApiKeyStatus
     */
    public function saveStatus(string $state, string $message = ''): array
    {
        $googleApiKeyData = $this->getGoogleApiKeyDataFromOptions();
        $normalizedState = $this->normalizeGoogleApiKeyStatus($state, $googleApiKeyData['google_api_key']);
        $googleApiKeyData['google_api_key_status'] = $normalizedState;
        $googleApiKeyData['google_api_key_status_message'] = $normalizedState === 'empty'
            ? ''
            : sanitize_text_field($message);
        $googleApiKeyData['google_api_key_checked_at'] = $normalizedState === 'empty' ? 0 : time();
        $googleApiKeyData = $this->persistGoogleApiKeyData($googleApiKeyData);
        return [
            'state' => $googleApiKeyData['google_api_key_status'],
            'message' => $googleApiKeyData['google_api_key_status_message'],
            'checked_at' => $googleApiKeyData['google_api_key_checked_at'],
        ];
    }

    /**
     * Delete the stored API key data and clear the in-memory cache.
     */
    public function clear(): void
    {
        delete_option(self::OPTION_GOOGLE_API_KEY_DATA);
    }

    /**
     * @return GoogleApiKeyData
     */
    public function getData(): array
    {
        return $this->getGoogleApiKeyDataFromOptions();
    }

    /**
     * @param array<string, mixed> $googleApiKeyData
     * @return GoogleApiKeyData
     */
    public function saveData(array $googleApiKeyData): array
    {
        return $this->persistGoogleApiKeyData($googleApiKeyData);
    }

    /**
     * @return GoogleApiKeyData
     */
    private function getGoogleApiKeyDataFromOptions(): array
    {
        $googleApiKeyData = get_option(self::OPTION_GOOGLE_API_KEY_DATA, null);

        if (!is_array($googleApiKeyData)) {
            $googleApiKeyData = self::DEFAULT_GOOGLE_API_KEY_DATA;
        }

        $normalizedGoogleApiKeyData = $this->normalizeGoogleApiKeyData($googleApiKeyData);

        if ($this->buildStoredGoogleApiKeyData($normalizedGoogleApiKeyData) !== $googleApiKeyData) {
            $normalizedGoogleApiKeyData = $this->persistGoogleApiKeyData($normalizedGoogleApiKeyData);
        }

        return $normalizedGoogleApiKeyData;
    }

    /**
     * @param array<string, mixed> $googleApiKeyData
     * @return GoogleApiKeyData
     */
    private function persistGoogleApiKeyData(array $googleApiKeyData): array
    {
        $googleApiKeyData = $this->normalizeGoogleApiKeyData($googleApiKeyData);

        update_option(self::OPTION_GOOGLE_API_KEY_DATA, $this->buildStoredGoogleApiKeyData($googleApiKeyData), false);

        return $googleApiKeyData;
    }

    /**
     * @return GoogleApiKeyData
     */
    private function normalizeGoogleApiKeyData(mixed $value): array
    {
        $googleApiKeyData = $this->normalizeInputMap($value);
        $googleApiKeyData = $this->normalizeInputMap(wp_parse_args($googleApiKeyData, self::DEFAULT_GOOGLE_API_KEY_DATA));
        $plaintextApiKey = trim(sanitize_text_field($this->stringValue($googleApiKeyData, 'google_api_key')));
        $encryptedApiKey = trim(sanitize_text_field($this->stringValue($googleApiKeyData, self::GOOGLE_API_KEY_ENCRYPTED_FIELD)));

        if ($plaintextApiKey === '' && $encryptedApiKey !== '') {
            $plaintextApiKey = $this->decryptGoogleApiKey($encryptedApiKey);
        }

        $googleApiKeyData['google_api_key'] = $plaintextApiKey;
        unset($googleApiKeyData[self::GOOGLE_API_KEY_ENCRYPTED_FIELD]);
        $googleApiKeyData['google_api_key_status'] = $this->normalizeGoogleApiKeyStatus(
            $this->stringValue($googleApiKeyData, 'google_api_key_status', 'empty'),
            $googleApiKeyData['google_api_key']
        );
        $googleApiKeyData['google_api_key_status_message'] = $this->sanitizeStatusMessage($googleApiKeyData['google_api_key_status_message'] ?? '');
        $googleApiKeyData['google_api_key_checked_at'] = $this->normalizeTimestamp($googleApiKeyData['google_api_key_checked_at'] ?? 0);

        return $googleApiKeyData;
    }

    /**
     * @param GoogleApiKeyData $googleApiKeyData
     * @return StoredGoogleApiKeyData
     */
    private function buildStoredGoogleApiKeyData(array $googleApiKeyData): array
    {
        $storedGoogleApiKeyData = [
            'google_api_key_status' => $googleApiKeyData['google_api_key_status'],
            'google_api_key_status_message' => $googleApiKeyData['google_api_key_status_message'],
            'google_api_key_checked_at' => $googleApiKeyData['google_api_key_checked_at'],
        ];
        $googleApiKey = trim($googleApiKeyData['google_api_key']);

        if ($googleApiKey === '') {
            return $storedGoogleApiKeyData;
        }

        $encryptedGoogleApiKey = $this->encryptGoogleApiKey($googleApiKey);

        if ($encryptedGoogleApiKey !== '') {
            $storedGoogleApiKeyData[self::GOOGLE_API_KEY_ENCRYPTED_FIELD] = $encryptedGoogleApiKey;

            return $storedGoogleApiKeyData;
        }

        $storedGoogleApiKeyData['google_api_key'] = $googleApiKey;

        return $storedGoogleApiKeyData;
    }

    private function encryptGoogleApiKey(string $googleApiKey): string
    {
        $googleApiKey = trim($googleApiKey);
        $key = $this->deriveGoogleApiKeyEncryptionKey();

        if (
            $googleApiKey === ''
            || $key === ''
            || !function_exists('sodium_crypto_secretbox')
            || !defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES')
        ) {
            return '';
        }

        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        } catch (\Throwable) {
            return '';
        }

        $ciphertext = sodium_crypto_secretbox($googleApiKey, $nonce, $key);

        return self::GOOGLE_API_KEY_CIPHER_PREFIX . base64_encode($nonce . $ciphertext);
    }

    private function decryptGoogleApiKey(string $encryptedGoogleApiKey): string
    {
        if (
            !str_starts_with($encryptedGoogleApiKey, self::GOOGLE_API_KEY_CIPHER_PREFIX)
            || !function_exists('sodium_crypto_secretbox_open')
            || !defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES')
        ) {
            return '';
        }

        $key = $this->deriveGoogleApiKeyEncryptionKey();

        if ($key === '') {
            return '';
        }

        $payload = base64_decode(substr($encryptedGoogleApiKey, strlen(self::GOOGLE_API_KEY_CIPHER_PREFIX)), true);

        if (!is_string($payload) || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }

        $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $googleApiKey = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if (!is_string($googleApiKey)) {
            return '';
        }

        return trim(sanitize_text_field($googleApiKey));
    }

    private function deriveGoogleApiKeyEncryptionKey(): string
    {
        $saltMaterial = [];

        if (function_exists('wp_salt')) {
            foreach (['auth', 'secure_auth', 'logged_in', 'nonce'] as $scheme) {
                $salt = wp_salt($scheme);

                if ($salt !== '') {
                    $saltMaterial[] = $salt;
                }
            }
        }

        foreach (
            [
                'AUTH_KEY',
                'SECURE_AUTH_KEY',
                'LOGGED_IN_KEY',
                'NONCE_KEY',
                'AUTH_SALT',
                'SECURE_AUTH_SALT',
                'LOGGED_IN_SALT',
                'NONCE_SALT',
            ] as $constant
        ) {
            if (defined($constant)) {
                $value = constant($constant);

                if (is_string($value) && $value !== '') {
                    $saltMaterial[] = $value;
                }
            }
        }

        $saltMaterial = array_values(array_unique($saltMaterial));

        if ($saltMaterial === []) {
            return '';
        }

        return hash(
            'sha256',
            implode('|', $saltMaterial) . '|' . self::OPTION_GOOGLE_API_KEY_DATA . '|google_api_key',
            true
        );
    }

    private function normalizeGoogleApiKeyStatus(string $state, string $apiKey): string
    {
        if (trim($apiKey) === '') {
            return 'empty';
        }

        return in_array($state, ['unknown', 'valid', 'invalid'], true) ? $state : 'unknown';
    }

    private function normalizeTimestamp(mixed $value): int
    {
        if (!is_scalar($value) && $value !== null) {
            return 0;
        }

        return max(0, absint($value));
    }

    private function sanitizeStatusMessage(mixed $message): string
    {
        return sanitize_text_field($this->mixedStringValue($message));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        return $this->mixedStringValue($values[$key], $default);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function intValue(array $values, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        return $this->normalizeTimestamp($values[$key]);
    }
}
