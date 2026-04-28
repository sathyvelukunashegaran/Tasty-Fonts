<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Google\GoogleFontsClient;

final class GoogleApiKeyValidationAdapter implements GoogleApiKeyValidationClient
{
    public function __construct(private readonly GoogleFontsClient $googleClient)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function validateApiKey(string $apiKey): array
    {
        return $this->googleClient->validateApiKey($apiKey);
    }
}
