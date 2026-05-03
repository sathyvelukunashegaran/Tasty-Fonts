<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Support\FontUtils;

final class GoogleStylesheetResolver implements ProviderStylesheetResolverInterface
{
    use ProviderStylesheetDescriptorHelpers;

    private const PROVIDER_KEY = 'google';
    private const DELIVERY_TYPE = 'cdn';
    private const PRECONNECT_ORIGIN = 'https://fonts.googleapis.com';

    public function __construct(private readonly GoogleFontsClient $client)
    {
    }

    public function buildStylesheetDescriptor(array $delivery, string $familyName, string $familySlug, string $displayOverride): ?array
    {
        $url = $this->client->buildCssUrl(
            $familyName,
            $this->normalizeVariantTokenList($delivery['variants'] ?? []),
            $displayOverride,
            ['faces' => $this->deliveryFaces($delivery)]
        );

        return $url === '' ? null : $this->descriptor($familySlug, 'cdn', $url);
    }


    /**
     * @param array<string, mixed> $delivery
     * @return list<array<string, mixed>>
     */
    private function deliveryFaces(array $delivery): array
    {
        return FontUtils::normalizeFaceList($delivery['faces'] ?? null);
    }

}
