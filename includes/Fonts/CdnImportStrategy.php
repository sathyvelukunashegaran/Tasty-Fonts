<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

final class CdnImportStrategy implements DeliveryImportStrategy
{
    public function supports(string $deliveryMode): bool
    {
        return $deliveryMode === 'cdn';
    }

    /**
     * @param list<array<string, mixed>> $faces
     * @param array<string, mixed> $provider
     * @return ImportFacesResult
     */
    public function importFaces(
        string $familyName,
        string $familySlug,
        array $faces,
        array $provider,
        HostedImportProviderConfig $config
    ): ImportFacesResult {
        $cdnFaces = [];

        foreach ($faces as $face) {
            $cdnFaces[] = StoredFaceBuilder::build(
                $familyName,
                $familySlug,
                $face,
                FontUtils::normalizeStringMap($face['files'] ?? []),
                $provider,
                $config->source
            );
        }

        return new ImportFacesResult($cdnFaces, 0);
    }

}
