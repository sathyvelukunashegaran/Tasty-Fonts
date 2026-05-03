<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Bunny\BunnyFontsClient;

final class BunnyStylesheetResolver implements ProviderStylesheetResolverInterface
{
    use ProviderStylesheetDescriptorHelpers;

    private const PROVIDER_KEY = 'bunny';
    private const DELIVERY_TYPE = 'cdn';
    private const PRECONNECT_ORIGIN = 'https://fonts.bunny.net';

    public function __construct(private readonly BunnyFontsClient $client)
    {
    }

    public function buildStylesheetDescriptor(array $delivery, string $familyName, string $familySlug, string $displayOverride): ?array
    {
        $url = $this->client->buildCssUrl($familyName, $this->normalizeVariantTokenList($delivery['variants'] ?? []), $displayOverride);

        return $url === '' ? null : $this->descriptor($familySlug, 'cdn', $url);
    }


}
