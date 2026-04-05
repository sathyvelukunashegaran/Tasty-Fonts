<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

final class LibraryService
{
    private const IMPORTED_SOURCES = ['google', 'bunny'];

    public function __construct(
        private readonly Storage $storage,
        private readonly CatalogService $catalog,
        private readonly ImportRepository $imports,
        private readonly AssetService $assets,
        private readonly LogRepository $log,
        private readonly SettingsRepository $settings
    ) {
    }

    public function deleteFamily(string $familySlug): bool|WP_Error
    {
        $familySlug = FontUtils::slugify($familySlug);
        $family = $this->findFamilyBySlug($familySlug);

        if ($family === null) {
            return $this->error(
                'tasty_fonts_family_not_found',
                __('That font family could not be found in the library.', 'tasty-fonts')
            );
        }

        $familyName = (string) ($family['family'] ?? $familySlug);
        $roleLabels = $this->getProtectedRoleLabels($familyName);

        if ($roleLabels !== []) {
            return $this->error(
                'tasty_fonts_family_in_use',
                sprintf(
                    __('%1$s is currently assigned as the %2$s font. Choose a different heading/body font before deleting it.', 'tasty-fonts'),
                    $familyName,
                    implode(__(' and ', 'tasty-fonts'), $this->translateRoleLabels($roleLabels))
                )
            );
        }

        $relativePaths = $this->collectRelativePaths($family);

        if (!$this->storage->deleteRelativeFiles($relativePaths)) {
            return $this->error(
                'tasty_fonts_delete_failed',
                __('The font files could not be deleted from uploads/fonts.', 'tasty-fonts')
            );
        }

        foreach ($this->managedImportSourcesForFamily($family) as $source) {
            if ($this->storage->deleteRelativeDirectory($source . '/' . $familySlug)) {
                continue;
            }

            return $this->error(
                'tasty_fonts_delete_failed',
                sprintf(
                    __('The %s import folder could not be removed cleanly.', 'tasty-fonts'),
                    $this->importSourceLabel($source)
                )
            );
        }

        $this->imports->delete($familySlug);
        $this->assets->refreshGeneratedAssets();

        $fileCount = count($relativePaths);
        $this->log->add(
            sprintf(
                __('Font family deleted: %1$s (%2$d file%3$s removed).', 'tasty-fonts'),
                (string) ($family['family'] ?? $familySlug),
                $fileCount,
                $fileCount === 1 ? '' : 's'
            )
        );

        return true;
    }

    public function deleteFaceVariant(
        string $familySlug,
        string $weight,
        string $style,
        string $source = 'local',
        string $unicodeRange = ''
    ): array|WP_Error {
        $familySlug = FontUtils::slugify($familySlug);
        $family = $this->findFamilyBySlug($familySlug);

        if ($family === null) {
            return $this->error(
                'tasty_fonts_family_not_found',
                __('That font family could not be found in the library.', 'tasty-fonts')
            );
        }

        $normalizedWeight = FontUtils::normalizeWeight($weight);
        $normalizedStyle = FontUtils::normalizeStyle($style);
        $normalizedSource = trim($source) !== '' ? strtolower(trim($source)) : 'local';
        $normalizedUnicodeRange = $this->isManagedImportSource($normalizedSource) ? '' : trim($unicodeRange);
        $face = $this->findMatchingFace($family, $normalizedWeight, $normalizedStyle, $normalizedSource, $normalizedUnicodeRange);

        if ($face === null) {
            return $this->error(
                'tasty_fonts_variant_not_found',
                __('That font variant could not be found in the library.', 'tasty-fonts')
            );
        }

        $familyName = (string) ($family['family'] ?? $familySlug);
        $roleLabels = $this->getProtectedRoleLabels($familyName);
        $isHeading = in_array('heading', $roleLabels, true);
        $isBody = in_array('body', $roleLabels, true);
        $isLastFace = count((array) ($family['faces'] ?? [])) <= 1;

        if ($isLastFace && ($isHeading || $isBody)) {
            return $this->error(
                'tasty_fonts_variant_in_use',
                $this->buildDeleteLastVariantBlockedMessage($familyName, $isHeading, $isBody)
            );
        }

        $relativePaths = $this->collectFaceRelativePaths($face);

        if (!$this->storage->deleteRelativeFiles($relativePaths)) {
            return $this->error(
                'tasty_fonts_delete_failed',
                __('The font files for that variant could not be deleted from uploads/fonts.', 'tasty-fonts')
            );
        }

        if ($this->isManagedImportSource($normalizedSource)) {
            $updateResult = $this->deleteImportedFace($familySlug, $normalizedWeight, $normalizedStyle, $normalizedSource);

            if (is_wp_error($updateResult)) {
                return $updateResult;
            }
        }

        $this->assets->refreshGeneratedAssets();

        $fileCount = count($relativePaths);
        $this->log->add(
            sprintf(
                __('Font variant deleted: %1$s %2$s %3$s (%4$d file%5$s removed).', 'tasty-fonts'),
                $familyName,
                $normalizedWeight,
                $normalizedStyle,
                $fileCount,
                $fileCount === 1 ? '' : 's'
            )
        );

        return [
            'family' => $familyName,
            'weight' => $normalizedWeight,
            'style' => $normalizedStyle,
        ];
    }

    private function findFamilyBySlug(string $familySlug): ?array
    {
        foreach ($this->catalog->getCatalog() as $family) {
            $slug = is_string($family['slug'] ?? null) ? $family['slug'] : '';

            if ($slug === $familySlug) {
                return $family;
            }
        }

        return null;
    }

    private function collectRelativePaths(array $family): array
    {
        $paths = [];

        foreach ((array) ($family['faces'] ?? []) as $face) {
            foreach ((array) ($face['paths'] ?? []) as $path) {
                if (!is_string($path) || trim($path) === '') {
                    continue;
                }

                $paths[] = trim($path);
            }
        }

        return array_values(array_unique($paths));
    }

    private function collectFaceRelativePaths(array $face): array
    {
        $paths = [];

        foreach ((array) ($face['paths'] ?? []) as $path) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            $paths[] = trim($path);
        }

        return array_values(array_unique($paths));
    }

    private function findMatchingFace(
        array $family,
        string $weight,
        string $style,
        string $source,
        string $unicodeRange
    ): ?array {
        foreach ((array) ($family['faces'] ?? []) as $face) {
            if ($this->faceMatches($face, $weight, $style, $source, $unicodeRange)) {
                return $face;
            }
        }

        return null;
    }

    private function faceMatches(array $face, string $weight, string $style, string $source, string $unicodeRange): bool
    {
        $faceSource = strtolower(trim((string) ($face['source'] ?? 'local')));
        $faceUnicodeRange = $this->isManagedImportSource($faceSource) ? '' : trim((string) ($face['unicode_range'] ?? ''));

        return $faceSource === $source
            && FontUtils::normalizeWeight((string) ($face['weight'] ?? '400')) === $weight
            && FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal')) === $style
            && $faceUnicodeRange === $unicodeRange;
    }

    private function deleteImportedFace(string $familySlug, string $weight, string $style, string $source): bool|WP_Error
    {
        $import = $this->imports->get($familySlug);

        if ($import === null) {
            return true;
        }

        $remainingFaces = array_values(
            array_filter(
                (array) ($import['faces'] ?? []),
                fn (mixed $face): bool => !$this->importFaceMatches($face, $weight, $style)
            )
        );

        if ($remainingFaces === []) {
            $this->imports->delete($familySlug);

            if (!$this->storage->deleteRelativeDirectory($source . '/' . $familySlug)) {
                return $this->error(
                    'tasty_fonts_delete_failed',
                    sprintf(
                        __('The %s import folder could not be removed cleanly.', 'tasty-fonts'),
                        $this->importSourceLabel($source)
                    )
                );
            }

            return true;
        }

        $import['faces'] = $remainingFaces;
        $import['variants'] = $this->buildImportVariantsFromFaces($remainingFaces);
        $this->imports->upsert($import);

        return true;
    }

    private function importFaceMatches(mixed $face, string $weight, string $style): bool
    {
        if (!is_array($face)) {
            return false;
        }

        return FontUtils::normalizeWeight((string) ($face['weight'] ?? '400')) === $weight
            && FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal')) === $style;
    }

    private function buildImportVariantsFromFaces(array $faces): array
    {
        $variants = [];

        foreach ($faces as $face) {
            if (!is_array($face)) {
                continue;
            }

            $weight = FontUtils::normalizeWeight((string) ($face['weight'] ?? '400'));
            $style = FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal'));
            $variants[] = match (true) {
                $weight === '400' && $style === 'normal' => 'regular',
                $weight === '400' && $style === 'italic' => 'italic',
                $style === 'italic' => $weight . 'italic',
                default => $weight,
            };
        }

        return array_values(array_unique($variants));
    }

    private function managedImportSourcesForFamily(array $family): array
    {
        $sources = [];

        foreach ((array) ($family['sources'] ?? []) as $source) {
            $normalized = strtolower(trim((string) $source));

            if ($this->isManagedImportSource($normalized)) {
                $sources[] = $normalized;
            }
        }

        return array_values(array_unique($sources));
    }

    private function isManagedImportSource(string $source): bool
    {
        return in_array(strtolower(trim($source)), self::IMPORTED_SOURCES, true);
    }

    private function importSourceLabel(string $source): string
    {
        return match (strtolower(trim($source))) {
            'bunny' => __('Bunny Fonts', 'tasty-fonts'),
            default => __('Google Fonts', 'tasty-fonts'),
        };
    }

    private function getProtectedRoleLabels(string $familyName): array
    {
        $catalog = $this->catalog->getCatalog();
        $roleSets = [$this->settings->getRoles($catalog)];

        if (!empty($this->settings->getSettings()['auto_apply_roles'])) {
            $roleSets[] = $this->settings->getAppliedRoles($catalog);
        }

        $roleLabels = [];

        foreach ($roleSets as $roles) {
            if (($roles['heading'] ?? '') === $familyName) {
                $roleLabels[] = 'heading';
            }

            if (($roles['body'] ?? '') === $familyName) {
                $roleLabels[] = 'body';
            }
        }

        return array_values(array_unique($roleLabels));
    }

    private function translateRoleLabels(array $roleLabels): array
    {
        return array_map(
            static fn (string $label): string => match ($label) {
                'heading' => __('heading', 'tasty-fonts'),
                'body' => __('body', 'tasty-fonts'),
                default => $label,
            },
            $roleLabels
        );
    }

    private function buildDeleteLastVariantBlockedMessage(string $familyName, bool $isHeading, bool $isBody): string
    {
        if ($isHeading && $isBody) {
            return sprintf(
                __('%s is currently assigned to both heading and body, and this is the last saved variant. Choose different role fonts before deleting it.', 'tasty-fonts'),
                $familyName
            );
        }

        if ($isHeading) {
            return sprintf(
                __('%s is currently assigned to heading, and this is the last saved variant. Choose a different heading font before deleting it.', 'tasty-fonts'),
                $familyName
            );
        }

        return sprintf(
            __('%s is currently assigned to body, and this is the last saved variant. Choose a different body font before deleting it.', 'tasty-fonts'),
            $familyName
        );
    }

    private function error(string $code, string $message): WP_Error
    {
        $this->log->add($message);

        return new WP_Error($code, $message);
    }
}
