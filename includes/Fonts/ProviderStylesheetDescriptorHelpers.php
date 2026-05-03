<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

trait ProviderStylesheetDescriptorHelpers
{
    public function supports(string $provider, string $type): bool
    {
        return strtolower(trim($provider)) === $this->getProviderKey()
            && strtolower(trim($type)) === static::DELIVERY_TYPE;
    }

    public function preconnectOrigin(): string
    {
        return static::PRECONNECT_ORIGIN;
    }

    public function getProviderKey(): string
    {
        return static::PROVIDER_KEY;
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
        $handleType = str_replace('_', '-', $type);

        return [
            'handle' => 'tasty-fonts-' . FontUtils::slugify($this->getProviderKey() . '-' . $familySlug . '-' . $handleType),
            'url' => $url,
            'provider' => $this->getProviderKey(),
            'type' => $type,
        ];
    }
}
