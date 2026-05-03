<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Admin\DeliveryProfileLabelHelper;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;

/**
 * Builds the shared role-family metadata consumed by admin and builder clients.
 *
 * @phpstan-import-type CatalogMap from CatalogCache
 * @phpstan-import-type AxesMap from FontUtils
 * @phpstan-import-type NormalizedSettings from SettingsRepository
 */
final class RoleFamilyCatalogBuilder
{
    /**
     * @param CatalogMap $catalog
     * @param NormalizedSettings $settings
     * @return array<string, mixed>
     */
    public function build(array $catalog, array $settings): array
    {
        $map = [];

        foreach ($catalog as $familyName => $family) {
            $familyName = (string) $familyName;
            $family = FontUtils::normalizeStringKeyedMap($family);
            $activeDelivery = FontUtils::normalizeStringKeyedMap($family['active_delivery'] ?? null);
            $deliveryId = sanitize_text_field($this->stringValue($activeDelivery, 'id'));

            if ($deliveryId === '') {
                continue;
            }

            $publishState = $this->stringValue($family, 'publish_state', 'published');
            $map[$familyName] = [
                'activeDeliveryId' => $deliveryId,
                'activeDeliveryLabel' => DeliveryProfileLabelHelper::displayLabel(
                    $activeDelivery,
                    [],
                    DeliveryProfileLabelHelper::FORMAT_ALWAYS
                ),
                'format' => FontUtils::resolveProfileFormat($activeDelivery),
                'weights' => $this->buildRoleWeightOptionsForProfile($activeDelivery),
                'axes' => $this->profileVariationAxes($activeDelivery),
                'hasWeightAxis' => $this->profileHasWeightAxis($activeDelivery),
                'fallback' => FallbackResolver::familyFallback($family + ['family' => $familyName], $settings),
                'fallbackIsCustom' => FallbackResolver::familyHasFallbackOverride($familyName, $settings),
                'publishState' => in_array($publishState, ['library_only', 'published', 'role_active'], true)
                    ? $publishState
                    : 'published',
            ];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $profile
     * @return list<array<string, string>>
     */
    private function buildRoleWeightOptionsForProfile(array $profile): array
    {
        $weights = [];

        foreach ((array) ($profile['faces'] ?? []) as $face) {
            if (
                !is_array($face)
                || FontUtils::normalizeStyle($this->stringValue($face, 'style', 'normal')) !== 'normal'
            ) {
                continue;
            }

            $weight = $this->resolveConcreteRoleWeight($this->stringValue($face, 'weight'));

            if ($weight === '') {
                continue;
            }

            $weights[$weight] = [
                'value' => $weight,
                'label' => trim($weight . ' ' . $this->buildRoleWeightLabel($weight)),
            ];
        }

        uksort($weights, static fn (string $left, string $right): int => ((int) $left) <=> ((int) $right));

        return array_values($weights);
    }

    /**
     * @param array<string, mixed> $profile
     * @return AxesMap
     */
    private function profileVariationAxes(array $profile): array
    {
        return FontUtils::collectVariationAxesFromFaces($profile['faces'] ?? []);
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function profileHasWeightAxis(array $profile): bool
    {
        $axes = $this->profileVariationAxes($profile);

        return isset($axes['WGHT']);
    }

    private function resolveConcreteRoleWeight(string $weight): string
    {
        if (trim($weight) === '') {
            return '';
        }

        $property = FontUtils::weightVariableName($weight);

        if ($property === '') {
            return '';
        }

        return substr($property, strlen('--weight-'));
    }

    private function buildRoleWeightLabel(string $weight): string
    {
        return match ($weight) {
            '100' => __('Thin', 'tasty-fonts'),
            '200' => __('Extra Light', 'tasty-fonts'),
            '300' => __('Light', 'tasty-fonts'),
            '400' => __('Regular', 'tasty-fonts'),
            '500' => __('Medium', 'tasty-fonts'),
            '600' => __('Semi Bold', 'tasty-fonts'),
            '700' => __('Bold', 'tasty-fonts'),
            '800' => __('Extra Bold', 'tasty-fonts'),
            '900' => __('Black', 'tasty-fonts'),
            '950' => __('Extra Black', 'tasty-fonts'),
            '1000' => __('Ultra Black', 'tasty-fonts'),
            default => '',
        };
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function stringValue(array $values, int|string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        return FontUtils::scalarStringValue($values[$key]);
    }
}
