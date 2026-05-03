<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type CatalogFace from CatalogCache
 * @phpstan-import-type DeliveryProfile from CatalogCache
 * @phpstan-import-type CatalogFamily from CatalogCache
 * @phpstan-import-type CatalogMap from CatalogCache
 * @phpstan-import-type CatalogCounts from CatalogCache
 * @phpstan-import-type DeliveryFormatMap from CatalogCache
 */
final class CatalogEnricher
{
    use CatalogRecordHelpers;

    /**
     * @var callable(string, string): string
     */
    private $translate;

    /**
     * @param callable(string, string): string|null $translate
     */
    public function __construct(?callable $translate = null)
    {
        $this->translate = $translate ?? static fn (string $text, string $domain): string => $text;
    }

    /**
     * Transform hydrated families into enriched families by computing delivery
     * badges, filter tokens, categories, formats, and rankings.
     *
     * @param CatalogMap $hydratedFamilies
     * @return CatalogMap
     */
    public function enrich(array $hydratedFamilies): array
    {
        $enriched = [];

        foreach ($hydratedFamilies as $familyName => $family) {
            $enriched[$familyName] = $this->enrichSingle($family);
        }

        return $enriched;
    }

    /**
     * Enrich a single family record without rebuilding the full catalog.
     *
     * @param CatalogFamily $family
     * @return CatalogFamily
     */
    public function enrichSingle(array $family): array
    {
        $profiles = $this->deliveryProfiles($family['delivery_profiles'] ?? []);
        $activeDeliveryId = $this->stringValue($family, 'active_delivery_id');

        if ($activeDeliveryId === '' || !isset($profiles[$activeDeliveryId])) {
            $activeDeliveryId = $profiles !== [] ? (string) array_key_first($profiles) : '';
        }

        $activeDelivery = ($activeDeliveryId !== '' && isset($profiles[$activeDeliveryId])) ? $profiles[$activeDeliveryId] : [];
        $availableDeliveries = array_values($profiles);
        usort($availableDeliveries, [$this, 'compareDeliveryProfiles']);

        $sources = array_values(
            array_unique(
                array_filter(
                    array_map(fn (array $profile): string => strtolower(trim($this->stringValue($profile, 'provider'))), $availableDeliveries),
                    static fn (string $source): bool => $source !== ''
                )
            )
        );

        $hasVariableFaces = $this->familyHasVariableFaces($availableDeliveries);
        $hasStaticFaces = $this->familyHasStaticFaces($availableDeliveries);
        $formats = $this->buildFamilyFormats($availableDeliveries);

        return [
            'family' => $this->stringValue($family, 'family'),
            'slug' => $this->stringValue($family, 'slug', FontUtils::slugify($this->stringValue($family, 'family'))),
            'publish_state' => $this->stringValue($family, 'publish_state', 'published'),
            'active_delivery_id' => $activeDeliveryId,
            'active_delivery' => $activeDelivery,
            'available_deliveries' => $availableDeliveries,
            'delivery_profiles' => $profiles,
            'sources' => $sources,
            'faces' => $this->deliveryFaceList($activeDelivery),
            'delivery_badges' => $this->buildDeliveryBadges($family, $activeDelivery),
            'delivery_filter_tokens' => $this->buildDeliveryFilterTokens($family, $activeDelivery),
            'font_category' => $this->resolveFamilyCategory($family, $activeDelivery, $availableDeliveries),
            'font_category_tokens' => $this->buildFamilyCategoryTokens(
                $this->resolveFamilyCategory($family, $activeDelivery, $availableDeliveries),
                $hasVariableFaces
            ),
            'formats' => $formats,
            'has_static_faces' => $hasStaticFaces,
            'has_variable_faces' => $hasVariableFaces,
            'variation_axes' => $this->collectFamilyVariationAxes($availableDeliveries),
        ];
    }

    /**
     * @param list<DeliveryProfile> $profiles
     */
    private function familyHasVariableFaces(array $profiles): bool
    {
        foreach ($profiles as $profile) {
            foreach ($this->deliveryFaceList($profile) as $face) {
                if (FontUtils::faceIsVariable($face)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<DeliveryProfile> $profiles
     */
    private function familyHasStaticFaces(array $profiles): bool
    {
        foreach ($profiles as $profile) {
            foreach ($this->deliveryFaceList($profile) as $face) {
                if (FontUtils::faceIsVariable($face)) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * @param list<DeliveryProfile> $profiles
     * @return DeliveryFormatMap
     */
    private function buildFamilyFormats(array $profiles): array
    {
        $formats = [];
        $variableProfiles = 0;

        foreach ($profiles as $profile) {
            $rawFormat = strtolower(trim($this->stringValue($profile, 'format')));
            $format = $rawFormat === 'variable-multi'
                ? 'variable-multi'
                : FontUtils::resolveProfileFormat($profile);
            $formats[$format] = [
                'label' => $this->formatLabel($format),
                'available' => true,
                'source_only' => !empty($profile['source_only']),
            ];

            if ($format === 'variable' || $format === 'variable-multi') {
                $variableProfiles++;
            }
        }

        if (!isset($formats['variable-multi']) && $variableProfiles > 1) {
            $formats['variable-multi'] = [
                'label' => 'Variable Multi',
                'available' => true,
                'source_only' => false,
            ];
        }

        if ($formats === []) {
            $formats['static'] = [
                'label' => 'Static',
                'available' => true,
                'source_only' => false,
            ];
        }

        return $formats;
    }

    private function formatLabel(string $format): string
    {
        return ucwords(str_replace('-', ' ', $format));
    }

    /**
     * @param list<DeliveryProfile> $profiles
     * @return array<string, mixed>
     */
    private function collectFamilyVariationAxes(array $profiles): array
    {
        $faces = [];

        foreach ($profiles as $profile) {
            array_push($faces, ...$this->deliveryFaceList($profile));
        }

        return FontUtils::collectVariationAxesFromFaces($faces);
    }

    /**
     * @param CatalogFamily $family
     * @param DeliveryProfile $activeDelivery
     * @return list<array{label: string, class: string, copy: string}>
     */
    private function buildDeliveryBadges(array $family, array $activeDelivery): array
    {
        $translate = $this->translate;
        $publishState = $this->stringValue($family, 'publish_state', 'published');
        $provider = strtolower(trim($this->stringValue($activeDelivery, 'provider')));
        $type = strtolower(trim($this->stringValue($activeDelivery, 'type', 'self_hosted')));
        $badges = [];

        $badges[] = match ($publishState) {
            'library_only' => [
                'label' => $translate('In Library Only', 'tasty-fonts'),
                'class' => 'is-warning',
                'copy' => $translate('This family is saved for previews and configuration, but it is not enqueued on the frontend, editor, or Etch canvas.', 'tasty-fonts'),
            ],
            'role_active' => [
                'label' => $translate('In Use', 'tasty-fonts'),
                'class' => 'is-success',
                'copy' => $translate('This family is active through the current live roles.', 'tasty-fonts'),
            ],
            default => [
                'label' => $translate('Published', 'tasty-fonts'),
                'class' => 'is-success',
                'copy' => $translate('This family is available on the frontend, editor, and Etch canvas for manual use.', 'tasty-fonts'),
            ],
        };

        $badges[] = match ($provider . ':' . $type) {
            'google:cdn' => [
                'label' => $translate('External Request', 'tasty-fonts'),
                'class' => '',
                'copy' => $translate('Visitors request this family from Google\'s web font API instead of your own uploads directory.', 'tasty-fonts'),
            ],
            'bunny:cdn' => [
                'label' => $translate('External Request', 'tasty-fonts'),
                'class' => '',
                'copy' => $translate('Visitors request this family from Bunny Fonts instead of your own uploads directory.', 'tasty-fonts'),
            ],
            'adobe:adobe_hosted' => [
                'label' => $translate('Adobe-hosted', 'tasty-fonts'),
                'class' => '',
                'copy' => $translate('Adobe web fonts stay hosted by Adobe and load from the project stylesheet defined in Adobe Fonts.', 'tasty-fonts'),
            ],
            'custom:cdn' => [
                'label' => $translate('External Request', 'tasty-fonts'),
                'class' => '',
                'copy' => $translate('Visitors request this custom CSS delivery from the reviewed remote font URLs while Tasty Fonts generates the @font-face rules.', 'tasty-fonts'),
            ],
            default => [
                'label' => $translate('Self-hosted', 'tasty-fonts'),
                'class' => 'is-success',
                'copy' => $translate('The active delivery for this family stays on this WordPress site.', 'tasty-fonts'),
            ],
        };

        return $badges;
    }

    /**
     * @param CatalogFamily $family
     * @param DeliveryProfile $activeDelivery
     * @return list<string>
     */
    private function buildDeliveryFilterTokens(array $family, array $activeDelivery): array
    {
        $publishState = $this->stringValue($family, 'publish_state', 'published');
        $provider = strtolower(trim($this->stringValue($activeDelivery, 'provider')));
        $type = strtolower(trim($this->stringValue($activeDelivery, 'type', 'self_hosted')));
        $tokens = [$publishState];

        if ($publishState === 'role_active') {
            $tokens[] = 'published';
        }

        if ($type === 'self_hosted') {
            $tokens[] = 'same-origin';
        } elseif ($provider === 'adobe') {
            $tokens[] = 'adobe-hosted';
        } else {
            $tokens[] = 'external-request';
        }

        if ($provider === 'google' && $type === 'cdn') {
            $tokens[] = 'google-cdn';
        }

        if ($provider === 'bunny' && $type === 'cdn') {
            $tokens[] = 'bunny-cdn';
        }

        if ($this->isCustomCssUrlDelivery($activeDelivery, $provider)) {
            $tokens[] = 'url-import';
        }

        if ($provider === 'adobe') {
            $tokens[] = 'adobe-hosted';
        }

        return $this->uniqueTokens($tokens);
    }

    /**
     * @param DeliveryProfile $profile
     */
    private function isCustomCssUrlDelivery(array $profile, string $provider): bool
    {
        $sourceType = strtolower(trim($this->profileMetaString($profile, 'source_type')));
        $sourceUrl = trim($this->profileMetaString($profile, 'source_css_url'));

        return $provider === 'custom'
            && $sourceUrl !== ''
            && in_array($sourceType, ['', 'custom_css_url'], true);
    }

    /**
     * @param CatalogFamily $family
     * @param DeliveryProfile $activeDelivery
     * @param list<DeliveryProfile> $availableDeliveries
     */
    private function resolveFamilyCategory(array $family, array $activeDelivery, array $availableDeliveries): string
    {
        $candidates = [];

        $candidates[] = $this->profileMetaString($activeDelivery, 'category');

        foreach ($availableDeliveries as $profile) {
            $candidates[] = $this->profileMetaString($profile, 'category');
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeFamilyCategory($candidate);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return $this->normalizeFamilyCategory($this->stringValue($family, 'family'));
    }

    private function normalizeFamilyCategory(string $category): string
    {
        $normalized = strtolower(trim($category));

        if ($normalized === '') {
            return '';
        }

        if (str_contains($normalized, 'slab') && str_contains($normalized, 'serif')) {
            return 'slab-serif';
        }

        if (str_contains($normalized, 'mono')) {
            return 'monospace';
        }

        if (str_contains($normalized, 'sans')) {
            return 'sans-serif';
        }

        if (str_contains($normalized, 'serif')) {
            return 'serif';
        }

        if (str_contains($normalized, 'display') || str_contains($normalized, 'decorative')) {
            return 'display';
        }

        if (str_contains($normalized, 'script')) {
            return 'script';
        }

        if (str_contains($normalized, 'cursive')) {
            return 'cursive';
        }

        if (str_contains($normalized, 'hand')) {
            return 'handwriting';
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function buildFamilyCategoryTokens(string $category, bool $hasVariableFaces = false): array
    {
        $normalized = $this->normalizeFamilyCategory($category);

        $tokens = $normalized === '' ? ['uncategorized'] : [$normalized];

        if ($normalized === 'slab-serif') {
            $tokens[] = 'serif';
        }

        if (in_array($normalized, ['handwriting', 'script', 'cursive'], true)) {
            $tokens[] = 'handwriting';
            $tokens[] = 'script';
            $tokens[] = 'cursive';
        }

        if ($normalized === 'display') {
            $tokens[] = 'decorative';
        }

        if ($hasVariableFaces) {
            $tokens[] = 'variable';
        }

        return $this->uniqueTokens($tokens);
    }

    /**
     * @param DeliveryProfile $left
     * @param DeliveryProfile $right
     */
    private function compareDeliveryProfiles(array $left, array $right): int
    {
        $leftRank = $this->deliveryProfileRank($left);
        $rightRank = $this->deliveryProfileRank($right);

        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        return strcmp($this->stringValue($left, 'label'), $this->stringValue($right, 'label'));
    }

    /**
     * @param DeliveryProfile $profile
     */
    private function deliveryProfileRank(array $profile): int
    {
        return match (strtolower(trim($this->stringValue($profile, 'provider'))) . ':' . strtolower(trim($this->stringValue($profile, 'type')))) {
            'local:self_hosted' => 10,
            'google:self_hosted' => 20,
            'google:cdn' => 30,
            'bunny:self_hosted' => 40,
            'bunny:cdn' => 50,
            'adobe:adobe_hosted' => 60,
            'custom:self_hosted' => 70,
            'custom:cdn' => 80,
            default => 99,
        };
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function uniqueTokens(array $tokens): array
    {
        $unique = [];

        foreach ($tokens as $token) {
            if ($token === '' || isset($unique[$token])) {
                continue;
            }

            $unique[$token] = $token;
        }

        return array_values($unique);
    }

}
