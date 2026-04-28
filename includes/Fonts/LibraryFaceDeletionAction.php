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
 * Deletes a single face variant from a family's active delivery profile.
 *
 * @phpstan-import-type CatalogFace from CatalogService
 */
final class LibraryFaceDeletionAction
{
    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly AssetService $assets,
        private readonly LogRepository $log,
        private readonly LibraryCatalogResolver $resolver,
        private readonly LibraryPathCollector $paths,
        private readonly LibraryRoleProtectionPolicy $roles,
        private readonly LibraryMutationErrorFactory $errors
    ) {
    }

    /**
     * @return array{family: string, weight: string, style: string}|WP_Error Result payload for the deleted face, or a WordPress error when deletion is blocked or fails.
     */
    public function deleteFaceVariant(
        string $familySlug,
        string $weight,
        string $style,
        string $source = 'local',
        string $unicodeRange = ''
    ): array|WP_Error {
        $familySlug = FontUtils::slugify($familySlug);
        $family = $this->resolver->findFamilyBySlug($familySlug);

        if ($family === null) {
            return $this->errors->error(
                'tasty_fonts_family_not_found',
                __('That font family could not be found in the library.', 'tasty-fonts')
            );
        }

        $activeDelivery = is_array($family['active_delivery'] ?? null) ? $family['active_delivery'] : [];

        $normalizedWeight = FontUtils::normalizeWeight($weight);
        $normalizedStyle = FontUtils::normalizeStyle($style);
        $normalizedSource = trim($source) !== '' ? strtolower(trim($source)) : 'local';
        $normalizedUnicodeRange = $this->paths->isManagedImportSource($normalizedSource) ? '' : trim($unicodeRange);
        $faces = FontUtils::normalizeFaceList($activeDelivery['faces'] ?? []);
        $faceIndex = $this->findMatchingFaceIndex($faces, $normalizedWeight, $normalizedStyle, $normalizedSource, $normalizedUnicodeRange);

        if ($faceIndex === null) {
            return $this->errors->error(
                'tasty_fonts_variant_not_found',
                __('That font variant could not be found in the library.', 'tasty-fonts')
            );
        }

        $face = $faces[$faceIndex];
        $familyName = $this->resolver->stringValue($family, 'family', $familySlug);
        $roleLabels = $this->roles->getProtectedRoleLabels($familyName);

        if (count($faces) <= 1 && $roleLabels !== []) {
            return $this->errors->error(
                'tasty_fonts_variant_in_use',
                $this->roles->buildDeleteLastVariantBlockedMessage($familyName, $roleLabels)
            );
        }

        $relativePaths = $this->paths->collectFaceRelativePaths($face);
        $storedFamily = $this->imports->getFamily($familySlug);
        $deliveryId = $this->resolver->stringValue($activeDelivery, 'id');
        $hasStoredActiveProfile = $storedFamily !== null
            && $deliveryId !== ''
            && isset($storedFamily['delivery_profiles'][$deliveryId]);

        if ($relativePaths === [] && !$hasStoredActiveProfile) {
            return $this->errors->error(
                'tasty_fonts_variant_delete_unsupported',
                __('That font variant cannot be deleted individually from this delivery.', 'tasty-fonts')
            );
        }

        if ($relativePaths !== [] && !$this->storage->deleteRelativeFiles($relativePaths)) {
            return $this->errors->error(
                'tasty_fonts_delete_failed',
                __('The font files for that variant could not be deleted from the font storage directory.', 'tasty-fonts')
            );
        }

        if ($hasStoredActiveProfile) {
            unset($faces[$faceIndex]);

            $profile = (array) $storedFamily['delivery_profiles'][$deliveryId];
            $profile['faces'] = array_values($faces);
            $profile['variants'] = HostedImportSupport::variantsFromFaces($profile['faces']);
            $this->imports->saveProfile(
                $familyName,
                $familySlug,
                $profile,
                $storedFamily['publish_state'],
                $storedFamily['active_delivery_id'] === $deliveryId
            );
        }

        $this->assets->refreshGeneratedAssets();

        $fileCount = count($relativePaths);
        $this->log->add(
            sprintf(
                _n(
                    'Font variant deleted: %1$s %2$s %3$s (%4$d file removed).',
                    'Font variant deleted: %1$s %2$s %3$s (%4$d files removed).',
                    $fileCount,
                    'tasty-fonts'
                ),
                $familyName,
                $normalizedWeight,
                $normalizedStyle,
                $fileCount
            ),
            [
                'category' => LogRepository::CATEGORY_LIBRARY,
                'event' => 'variant_deleted',
                'outcome' => 'danger',
                'status_label' => __('Deleted', 'tasty-fonts'),
                'source' => __('Library', 'tasty-fonts'),
                'entity_type' => 'font_family',
                'entity_id' => $familySlug,
                'entity_name' => $familyName,
                'details' => [
                    ['label' => __('Family', 'tasty-fonts'), 'value' => $familyName],
                    ['label' => __('Weight', 'tasty-fonts'), 'value' => $normalizedWeight],
                    ['label' => __('Style', 'tasty-fonts'), 'value' => $normalizedStyle],
                    ['label' => __('Source', 'tasty-fonts'), 'value' => $normalizedSource],
                    ['label' => __('Files removed', 'tasty-fonts'), 'value' => (string) $fileCount, 'kind' => 'count'],
                ],
            ]
        );

        return [
            'family' => $familyName,
            'weight' => $normalizedWeight,
            'style' => $normalizedStyle,
        ];
    }

    /**
     * @param list<CatalogFace> $faces
     */
    private function findMatchingFaceIndex(
        array $faces,
        string $weight,
        string $style,
        string $source,
        string $unicodeRange
    ): ?int {
        foreach ($faces as $index => $face) {
            if ($this->faceMatches($face, $weight, $style, $source, $unicodeRange)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param CatalogFace $face
     */
    private function faceMatches(array $face, string $weight, string $style, string $source, string $unicodeRange): bool
    {
        $faceSource = strtolower(trim($this->resolver->stringValue($face, 'source', 'local')));
        $faceUnicodeRange = $this->paths->isManagedImportSource($faceSource) ? '' : trim($this->resolver->stringValue($face, 'unicode_range'));

        return $faceSource === $source
            && FontUtils::normalizeWeight($this->resolver->stringValue($face, 'weight', '400')) === $weight
            && FontUtils::normalizeStyle($this->resolver->stringValue($face, 'style', 'normal')) === $style
            && $faceUnicodeRange === $unicodeRange;
    }
}
