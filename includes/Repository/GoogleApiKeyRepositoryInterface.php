<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

/**
 * @phpstan-type GoogleApiKeyStatus array{state: string, message: string, checked_at: int}
 */
interface GoogleApiKeyRepositoryInterface
{
    /**
     * Check whether a Google API key is stored.
     */
    public function has(): bool;

    /**
     * Return the decrypted Google API key, or an empty string when none is stored.
     */
    public function getApiKey(): string;

    /**
     * Persist a Google API key and reset its validation status to unknown.
     *
     * @return GoogleApiKeyStatus
     */
    public function saveApiKey(string $apiKey): array;

    /**
     * Get the current API key status.
     *
     * @return GoogleApiKeyStatus
     */
    public function getStatus(): array;

    /**
     * Save an API key status value.
     *
     * @return GoogleApiKeyStatus
     */
    public function saveStatus(string $state, string $message = ''): array;

    /**
     * Delete the stored API key data.
     */
    public function clear(): void;
}
