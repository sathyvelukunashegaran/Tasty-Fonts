<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

interface GoogleApiKeyValidationClient
{
    /**
     * @return array<string, mixed>
     */
    public function validateApiKey(string $apiKey): array;
}
