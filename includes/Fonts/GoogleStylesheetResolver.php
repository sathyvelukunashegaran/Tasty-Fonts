<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Support\FontUtils;

final class GoogleStylesheetResolver implements ProviderStylesheetResolverInterface
{
    public function __construct(private readonly GoogleFontsClient $client)
    {
    }

    public function supports(string $provider, string $type): bool
    {
        return strtolower(trim($provider)) === $this->getProviderKey()
            && strtolower(trim($type)) === 'cdn';
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

    public function preconnectOrigin(): string
    {
        return 'https://fonts.googleapis.com';
    }

    public function getProviderKey(): string
    {
        return 'google';
    }

    /**
     * @param mixed $variants
     * @return list<string>
     */
    private function normalizeVariantTokenList(mixed $variants): array
    {
        if (!is_array($variants)) {
            return [];
        }

        $normalized = [];

        foreach ($variants as $variant) {
            if (is_scalar($variant)) {
                $normalized[] = (string) $variant;
            }
        }

        return FontUtils::normalizeVariantTokens($normalized);
    }

    /**
     * @param array<string, mixed> $delivery
     * @return list<array<string, mixed>>
     */
    private function deliveryFaces(array $delivery): array
    {
        return FontUtils::normalizeFaceList($delivery['faces'] ?? null);
    }

    /**
     * @return array{handle: string, url: string, provider: string, type: string}
     */
    private function descriptor(string $familySlug, string $type, string $url): array
    {
        return [
            'handle' => 'tasty-fonts-' . FontUtils::slugify($this->getProviderKey() . '-' . $familySlug . '-' . $type),
            'url' => $url,
            'provider' => $this->getProviderKey(),
            'type' => $type,
        ];
    }
}
