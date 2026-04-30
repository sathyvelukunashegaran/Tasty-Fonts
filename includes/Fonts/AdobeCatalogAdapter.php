<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type VariationDefaults from \TastyFonts\Support\FontUtils
 * @phpstan-import-type CatalogFace from CatalogService
 * @phpstan-import-type CatalogFamily from CatalogService
 */
final class AdobeCatalogAdapter
{
    public function __construct(private readonly AdobeProjectClient $adobe)
    {
    }

    /**
     * @return list<CatalogFamily>
     */
    public function families(): array
    {
        $projectState = $this->adobe->getProjectStatus()['state'];

        if (!$this->adobe->hasProjectId() || !in_array($projectState, ['valid', 'unknown'], true)) {
            return [];
        }

        $projectId = $this->adobe->getProjectId();
        $deliveryId = $this->adobeDeliveryId();
        $families = [];

        foreach ($this->adobe->getConfiguredFamilies() as $family) {
            $familyName = FontUtils::stringValue($family, 'family');
            $familySlug = FontUtils::stringValue($family, 'slug', FontUtils::slugify($familyName));

            if ($familyName === '' || $familySlug === '') {
                continue;
            }

            $faces = [];

            foreach ($this->deliveryFaceList(['faces' => $family['faces'] ?? []]) as $face) {
                $axes = FontUtils::normalizeAxesValue($face['axes'] ?? []);

                $faces[] = [
                    'family' => $familyName,
                    'slug' => $familySlug,
                    'source' => 'adobe',
                    'weight' => FontUtils::normalizeWeight(FontUtils::stringValue($face, 'weight', '400')),
                    'style' => FontUtils::normalizeStyle(FontUtils::stringValue($face, 'style', 'normal')),
                    'unicode_range' => '',
                    'files' => [],
                    'paths' => [],
                    'provider' => ['type' => 'adobe', 'project_id' => $projectId],
                    'is_variable' => !empty($face['is_variable']),
                    'axes' => $axes,
                    'variation_defaults' => FontUtils::normalizeVariationDefaultsValue($face['variation_defaults'] ?? [], $axes),
                ];
            }

            $families[] = [
                'family' => $familyName,
                'slug' => $familySlug,
                'publish_state' => 'published',
                'active_delivery_id' => $deliveryId,
                'delivery_profiles' => [
                    $deliveryId => [
                        'id' => $deliveryId,
                        'provider' => 'adobe',
                        'type' => 'adobe_hosted',
                        'label' => __('Adobe-hosted', 'tasty-fonts'),
                        'variants' => $this->variantsFromFaces($faces),
                        'faces' => $faces,
                        'meta' => ['project_id' => $projectId],
                    ],
                ],
            ];
        }

        return $families;
    }

    /**
     * @param list<CatalogFace> $faces
     * @return list<string>
     */
    private function variantsFromFaces(array $faces): array
    {
        return HostedImportSupport::variantsFromFaces($faces);
    }

    private function adobeDeliveryId(): string
    {
        return FontUtils::slugify('adobe-adobe_hosted');
    }

    /**
     * @param array<string, mixed> $profile
     * @return list<CatalogFace>
     */
    private function deliveryFaceList(array $profile): array
    {
        return FontUtils::normalizeFaceList($profile['faces'] ?? []);
    }

}
