<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Support\FontUtils;

final class BunnyStylesheetResolver implements ProviderStylesheetResolverInterface
{
    public function __construct(private readonly BunnyFontsClient $client)
    {
    }

    public function supports(string $provider, string $type): bool
    {
        return strtolower(trim($provider)) === $this->getProviderKey()
            && strtolower(trim($type)) === 'cdn';
    }

    public function buildStylesheetDescriptor(array $delivery, string $familyName, string $familySlug, string $displayOverride): ?array
    {
        $url = $this->client->buildCssUrl($familyName, $this->normalizeVariantTokenList($delivery['variants'] ?? []), $displayOverride);

        return $url === '' ? null : $this->descriptor($familySlug, 'cdn', $url);
    }

    public function preconnectOrigin(): string
    {
        return 'https://fonts.bunny.net';
    }

    public function getProviderKey(): string
    {
        return 'bunny';
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
