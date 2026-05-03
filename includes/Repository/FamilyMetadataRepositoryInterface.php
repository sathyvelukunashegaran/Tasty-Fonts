<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

/**
 * @phpstan-type FamilyFallbackMap array<string, string>
 * @phpstan-type FamilyFontDisplayMap array<string, string>
 */
interface FamilyMetadataRepositoryInterface
{
    public function getFallback(string $familySlug, string $default = 'sans-serif'): string;

    /**
     * @return FamilyFallbackMap
     */
    public function saveFallback(string $familySlug, string $fallback): array;

    public function getFontDisplay(string $familySlug, string $default = ''): string;

    /**
     * @return FamilyFontDisplayMap
     */
    public function saveFontDisplay(string $familySlug, string $display): array;

    /**
     * @return array<string, mixed>
     */
    public function resetFallbacks(): array;

    /**
     * @return array<string, mixed>
     */
    public function resetAll(): array;
}
