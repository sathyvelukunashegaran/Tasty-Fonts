<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use WP_Error;

/**
 * Adapter contract for hosted font providers used by the shared import workflow.
 *
 * @phpstan-type HostedImportContext array{
 *     family_name: string,
 *     family_slug: string,
 *     delivery_mode: string,
 *     format_mode: string,
 *     metadata: array<string, mixed>|null,
 *     existing_family: array<string, mixed>|null,
 *     existing_profile: array<string, mixed>|null,
 *     imported_variants: list<string>
 * }
 * @phpstan-type HostedProfileDraft array{
 *     profile: array<string, mixed>,
 *     face_provider: array<string, mixed>
 * }
 */
interface HostedImportProviderAdapterInterface
{
    public function providerKey(): string;

    public function config(): HostedImportProviderConfig;

    public function normalizeFormatMode(string $formatMode): string;

    public function profileFormatFilter(string $formatMode): string;

    /**
     * @param list<string> $requestedVariants
     * @return list<string>
     */
    public function normalizeRequestedVariants(array $requestedVariants, string $formatMode): array;

    /**
     * @return array<string, mixed>|null
     */
    public function fetchMetadata(string $familyName): ?array;

    /**
     * @param list<string> $variants
     * @param array<string, mixed>|null $metadata
     */
    public function fetchCss(
        string $familyName,
        array $variants,
        ?array $metadata,
        string $formatMode
    ): string|WP_Error;

    /**
     * @return list<array<string, mixed>>
     */
    public function parseFaces(string $css, string $familyName): array;

    /**
     * @param HostedImportContext $context
     * @return HostedProfileDraft
     */
    public function buildProfileDraft(array $context): array;
}
