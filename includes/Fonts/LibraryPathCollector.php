<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * Collects storage-relative paths and managed provider directories for library mutations.
 *
 * @phpstan-import-type CatalogFamily from CatalogCache
 * @phpstan-import-type CatalogFace from CatalogCache
 * @phpstan-import-type DeliveryProfile from CatalogCache
 */
final class LibraryPathCollector
{
    private const MANAGED_IMPORT_SOURCES = ['google', 'bunny'];

    public function __construct(private readonly LibraryCatalogResolver $resolver)
    {
    }

    /**
     * @param CatalogFamily $family
     * @return list<string>
     */
    public function collectFamilyRelativePaths(array $family): array
    {
        $paths = [];

        foreach ($this->resolver->availableDeliveries($family) as $profile) {
            $paths = array_merge($paths, $this->collectProfileRelativePaths($profile));
        }

        return array_values(array_unique(array_filter($paths, static fn (string $path): bool => $path !== '')));
    }

    /**
     * @param DeliveryProfile $profile
     * @return list<string>
     */
    public function collectProfileRelativePaths(array $profile): array
    {
        $paths = [];

        foreach (FontUtils::normalizeFaceList($profile['faces'] ?? []) as $face) {
            $paths = array_merge($paths, $this->collectFaceRelativePaths($face));
        }

        return array_values(array_unique(array_filter($paths, static fn (string $path): bool => $path !== '')));
    }

    /**
     * @param CatalogFace $face
     * @return list<string>
     */
    public function collectFaceRelativePaths(array $face): array
    {
        return FontUtils::collectFaceRelativePaths($face);
    }

    /**
     * @param CatalogFamily $family
     * @return list<string>
     */
    public function managedImportSourcesForFamily(array $family): array
    {
        $sources = [];

        foreach ($this->resolver->availableDeliveries($family) as $profile) {
            if (!$this->isSelfHostedProfile($profile)) {
                continue;
            }

            $provider = strtolower(trim($this->resolver->stringValue($profile, 'provider')));

            if ($this->isManagedImportSource($provider)) {
                $sources[] = $provider;
            }
        }

        return array_values(array_unique($sources));
    }

    public function isManagedImportSource(string $source): bool
    {
        return in_array(strtolower(trim($source)), self::MANAGED_IMPORT_SOURCES, true);
    }

    /**
     * @param DeliveryProfile $profile
     */
    public function isSelfHostedProfile(array $profile): bool
    {
        return strtolower(trim($this->resolver->stringValue($profile, 'type'))) === 'self_hosted';
    }
}
