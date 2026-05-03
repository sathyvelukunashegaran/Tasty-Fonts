<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Adobe\AdobeProjectClient;
final class AdobeStylesheetResolver implements ProviderStylesheetResolverInterface
{
    use ProviderStylesheetDescriptorHelpers;

    private const PROVIDER_KEY = 'adobe';
    private const DELIVERY_TYPE = 'adobe_hosted';
    private const PRECONNECT_ORIGIN = 'https://use.typekit.net';

    public function __construct(private readonly AdobeProjectClient $client)
    {
    }

    public function buildStylesheetDescriptor(array $delivery, string $familyName, string $familySlug, string $displayOverride): ?array
    {
        unset($familyName, $displayOverride);

        $projectId = sanitize_text_field($this->deliveryMetaStringValue($delivery, 'project_id', $this->client->getProjectId()));
        $url = $projectId === '' ? '' : $this->client->getStylesheetUrl($projectId);

        return $url === '' ? null : $this->descriptor($familySlug, 'adobe_hosted', $url);
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

}
