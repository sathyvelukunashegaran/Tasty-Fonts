<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use WP_Error;

interface DeliveryImportStrategy
{
    public function supports(string $deliveryMode): bool;

    /**
     * @param list<array<string, mixed>> $faces
     * @param array<string, mixed> $provider
     * @return ImportFacesResult|WP_Error
     */
    public function importFaces(
        string $familyName,
        string $familySlug,
        array $faces,
        array $provider,
        HostedImportProviderConfig $config
    ): ImportFacesResult|WP_Error;
}
