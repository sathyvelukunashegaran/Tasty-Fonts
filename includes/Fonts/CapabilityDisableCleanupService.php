<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

/**
 * @phpstan-import-type DeliveryProfile from ImportRepository
 * @phpstan-import-type FaceRecord from ImportRepository
 */
final class CapabilityDisableCleanupService
{
    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly CatalogService $catalog,
        private readonly LogRepository $log
    ) {
    }

    /**
     * @return array<string, int>|WP_Error
     */
    public function removeVariableFontData(): array|WP_Error
    {
        $catalog = $this->catalog->getCatalog();
        $relativePaths = [];

        foreach ($catalog as $family) {
            foreach ($this->deliveryProfiles($family) as $profile) {
                foreach (FontUtils::normalizeFaceList($profile['faces'] ?? []) as $face) {
                    if (!$this->isVariableFace($face)) {
                        continue;
                    }

                    $relativePaths = array_merge($relativePaths, FontUtils::collectFaceRelativePaths($face));
                }
            }
        }

        $relativePaths = array_values(array_unique(array_filter($relativePaths, static fn (string $path): bool => $path !== '')));

        if (!$this->storage->deleteRelativeFiles($relativePaths)) {
            return new WP_Error(
                'tasty_fonts_variable_cleanup_failed',
                __('Could not remove one or more variable font files.', 'tasty-fonts')
            );
        }

        $library = $this->imports->allFamilies();
        $familiesRemoved = 0;
        $profilesRemoved = 0;
        $facesRemoved = 0;
        $activeDeliveriesChanged = 0;

        foreach ($library as $familySlug => $family) {
            $profiles = $family['delivery_profiles'];
            $activeDeliveryId = trim($family['active_delivery_id']);
            $activeDeliveryProvider = '';

            if ($activeDeliveryId !== '' && isset($profiles[$activeDeliveryId])) {
                $activeDeliveryProvider = strtolower(trim($profiles[$activeDeliveryId]['provider']));
            }

            /** @var array<string, DeliveryProfile> $keptProfiles */
            $keptProfiles = [];

            foreach ($profiles as $profileId => $profile) {
                $faces = $profile['faces'];
                /** @var list<FaceRecord> $keptFaces */
                $keptFaces = [];

                foreach ($faces as $face) {
                    if ($this->isVariableFace($face)) {
                        $facesRemoved += 1;
                        continue;
                    }

                    $keptFaces[] = $face;
                }

                if ($keptFaces === []) {
                    $profilesRemoved += 1;
                    continue;
                }

                $profile['faces'] = $keptFaces;
                $profile['format'] = 'static';
                $profile['variants'] = FontUtils::normalizeVariantTokens(
                    array_values(
                        array_filter(
                            array_map(
                                fn (array $face): string => $this->staticVariantTokenFromFace($face),
                                $keptFaces
                            ),
                            static fn (string $token): bool => $token !== ''
                        )
                    )
                );

                $keptProfiles[(string) $profileId] = $profile;
            }

            if ($keptProfiles === []) {
                unset($library[$familySlug]);
                $familiesRemoved += 1;
                continue;
            }

            if (!isset($keptProfiles[$activeDeliveryId])) {
                $fallback = $this->fallbackDeliveryId($keptProfiles, $activeDeliveryProvider);

                if ($fallback === '') {
                    unset($library[$familySlug]);
                    $familiesRemoved += 1;
                    continue;
                }

                if ($activeDeliveryId !== '' && $activeDeliveryId !== $fallback) {
                    $activeDeliveriesChanged += 1;
                }

                $family['active_delivery_id'] = $fallback;
            }

            $family['delivery_profiles'] = $keptProfiles;
            $library[$familySlug] = $family;
        }

        $this->imports->replaceLibrary($library);
        $this->catalog->invalidate();

        $summary = [
            'families_removed' => $familiesRemoved,
            'profiles_removed' => $profilesRemoved,
            'faces_removed' => $facesRemoved,
            'files_removed' => count($relativePaths),
            'active_deliveries_changed' => $activeDeliveriesChanged,
        ];

        $this->log->add(
            __('Removed variable font capability data.', 'tasty-fonts'),
            [
                'category' => LogRepository::CATEGORY_LIBRARY,
                'event' => 'capability_disable_variable_cleanup',
                'details' => [
                    ['label' => __('Families removed', 'tasty-fonts'), 'value' => (string) $familiesRemoved, 'kind' => 'count'],
                    ['label' => __('Profiles removed', 'tasty-fonts'), 'value' => (string) $profilesRemoved, 'kind' => 'count'],
                    ['label' => __('Faces removed', 'tasty-fonts'), 'value' => (string) $facesRemoved, 'kind' => 'count'],
                    ['label' => __('Files removed', 'tasty-fonts'), 'value' => (string) count($relativePaths), 'kind' => 'count'],
                    ['label' => __('Active deliveries changed', 'tasty-fonts'), 'value' => (string) $activeDeliveriesChanged, 'kind' => 'count'],
                ],
            ]
        );

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsOverrides(bool $disableVariableFonts, bool $disableMonospaceRole): array
    {
        $overrides = [];

        if ($disableVariableFonts) {
            $overrides['variable_fonts_enabled'] = false;
        }

        if ($disableMonospaceRole) {
            $overrides['monospace_role_enabled'] = false;
            $overrides['class_output_role_monospace_enabled'] = false;
            $overrides['class_output_role_alias_code_enabled'] = false;
            $overrides['class_output_category_mono_enabled'] = false;
            $overrides['extended_variable_category_mono_enabled'] = false;
        }

        return $overrides;
    }

    /**
     * @param array<string, mixed> $family
     * @return array<string, array<string, mixed>>
     */
    private function deliveryProfiles(array $family): array
    {
        $profiles = is_array($family['delivery_profiles'] ?? null)
            ? $family['delivery_profiles']
            : [];
        $normalized = [];

        foreach ($profiles as $profileId => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            /** @var array<string, mixed> $profile */
            $normalized[(string) $profileId] = $profile;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $face
     */
    private function isVariableFace(array $face): bool
    {
        if (FontUtils::faceIsVariable($face)) {
            return true;
        }

        $weight = trim(FontUtils::scalarStringValue($face['weight'] ?? ''));

        return preg_match('/^\d{1,4}(?:\.\.|\s+)\d{1,4}$/', $weight) === 1;
    }

    /**
     * @param array<string, mixed> $face
     */
    private function staticVariantTokenFromFace(array $face): string
    {
        $style = FontUtils::normalizeStyle(FontUtils::scalarStringValue($face['style'] ?? ''));
        $style = $style === 'italic' ? 'italic' : 'normal';

        $weight = FontUtils::normalizeWeight(FontUtils::scalarStringValue($face['weight'] ?? ''));

        if ($weight === '') {
            $weight = '400';
        }

        if ($weight === '400') {
            return $style === 'italic' ? 'italic' : 'regular';
        }

        return $style === 'italic' ? ($weight . 'italic') : $weight;
    }

    /**
     * @param array<string, DeliveryProfile> $profiles
     */
    private function fallbackDeliveryId(array $profiles, string $preferredProvider): string
    {
        if ($profiles === []) {
            return '';
        }

        if ($preferredProvider !== '') {
            foreach ($profiles as $profileId => $profile) {
                $profileProvider = strtolower(trim($profile['provider']));

                if ($profileProvider === $preferredProvider) {
                    return $profileId;
                }
            }
        }

        return array_key_first($profiles);
    }
}
