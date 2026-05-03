<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Support\FontUtils;

final class AdobeStylesheetResolver implements ProviderStylesheetResolverInterface
{
    public function __construct(private readonly AdobeProjectClient $client)
    {
    }

    public function supports(string $provider, string $type): bool
    {
        return strtolower(trim($provider)) === $this->getProviderKey()
            && strtolower(trim($type)) === 'adobe_hosted';
    }

    public function buildStylesheetDescriptor(array $delivery, string $familyName, string $familySlug, string $displayOverride): ?array
    {
        unset($familyName, $displayOverride);

        $projectId = sanitize_text_field($this->deliveryMetaStringValue($delivery, 'project_id', $this->client->getProjectId()));
        $url = $projectId === '' ? '' : $this->client->getStylesheetUrl($projectId);

        return $url === '' ? null : $this->descriptor($familySlug, 'adobe_hosted', $url);
    }

    public function preconnectOrigin(): string
    {
        return 'https://use.typekit.net';
    }

    public function getProviderKey(): string
    {
        return 'adobe';
    }

    /**
     * @param array<string, mixed> $delivery
     */
    private function deliveryMetaStringValue(array $delivery, string $key, string $default = ''): string
    {
        $meta = $delivery['meta'] ?? null;

        if (!is_array($meta)) {
            return $default;
        }

        $value = $meta[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
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
