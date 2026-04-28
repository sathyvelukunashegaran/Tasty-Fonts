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
            $familyName = $this->stringValue($family, 'family');
            $familySlug = $this->stringValue($family, 'slug', FontUtils::slugify($familyName));

            if ($familyName === '' || $familySlug === '') {
                continue;
            }

            $faces = [];

            foreach ($this->deliveryFaceList(['faces' => $family['faces'] ?? []]) as $face) {
                $axes = $this->normalizeAxes($face['axes'] ?? []);

                $faces[] = [
                    'family' => $familyName,
                    'slug' => $familySlug,
                    'source' => 'adobe',
                    'weight' => FontUtils::normalizeWeight($this->stringValue($face, 'weight', '400')),
                    'style' => FontUtils::normalizeStyle($this->stringValue($face, 'style', 'normal')),
                    'unicode_range' => '',
                    'files' => [],
                    'paths' => [],
                    'provider' => ['type' => 'adobe', 'project_id' => $projectId],
                    'is_variable' => !empty($face['is_variable']),
                    'axes' => $axes,
                    'variation_defaults' => $this->normalizeVariationDefaults($face['variation_defaults'] ?? [], $axes),
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

    /**
     * @return array<string, array<string, float|int|string>>
     */
    private function normalizeAxes(mixed $axes): array
    {
        return is_array($axes) ? FontUtils::normalizeAxesMap($axes) : [];
    }

    /**
     * @param array<string, array<string, float|int|string>> $axes
     * @return VariationDefaults
     */
    private function normalizeVariationDefaults(mixed $variationDefaults, array $axes): array
    {
        return is_array($variationDefaults) ? FontUtils::normalizeVariationDefaults($variationDefaults, $axes) : [];
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $value = self::scalarStringValue($values[$key]);

        return $value !== '' ? $value : $default;
    }

    private static function scalarStringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $value === true ? '1' : '';
    }
}
