<?php

declare(strict_types=1);

namespace TastyFonts\Bunny;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\HostedImportProviderAdapterInterface;
use TastyFonts\Fonts\HostedImportProviderConfig;
use TastyFonts\Fonts\HostedImportRequest;
use TastyFonts\Fonts\HostedImportWorkflow;
use TastyFonts\Support\FontUtils;
use WP_Error;

/**
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
 * @phpstan-type ImportResult array{
 *     status: string,
 *     message: string,
 *     family: string,
 *     delivery_type: string,
 *     faces: int,
 *     files: int,
 *     variants: list<string>,
 *     imported_variants: list<string>,
 *     skipped_variants: list<string>,
 *     family_record?: array<string, mixed>,
 *     delivery_id?: string
 * }
 */
final class BunnyImportService implements HostedImportProviderAdapterInterface
{
    private const MAX_FONT_FILE_BYTES = 10 * MB_IN_BYTES;

    public function __construct(
        private readonly BunnyFontsClient $client,
        private readonly BunnyCssParser $parser,
        private readonly HostedImportWorkflow $workflow
    ) {}

    /**
     * @param list<string> $variants
     * @return ImportResult|WP_Error
     */
    public function importFamily(
        string $familyName,
        array $variants,
        string $deliveryMode = 'self_hosted'
    ): array|WP_Error {
        return $this->workflow->import(
            new HostedImportRequest($familyName, $variants, $deliveryMode, 'static'),
            $this
        );
    }

    public function providerKey(): string
    {
        return 'bunny';
    }

    public function config(): HostedImportProviderConfig
    {
        return new HostedImportProviderConfig(
            'bunny',
            'bunny',
            'fonts.bunny.net',
            self::MAX_FONT_FILE_BYTES,
            'tasty_fonts_bunny_family_dir_failed',
            __('The Bunny Fonts import directory could not be created.', 'tasty-fonts'),
            'tasty_fonts_bunny_invalid_host',
            __('Bunny font downloads must come from fonts.bunny.net.', 'tasty-fonts'),
            'tasty_fonts_bunny_invalid_extension',
            __('Only WOFF2 files can be imported from Bunny Fonts.', 'tasty-fonts'),
            'tasty_fonts_bunny_download_failed',
            __('Font download failed with status %d.', 'tasty-fonts'),
            'tasty_fonts_bunny_empty_file',
            __('Bunny Fonts returned an empty font file.', 'tasty-fonts'),
            'tasty_fonts_bunny_file_too_large',
            __('The downloaded font file exceeded the safety size limit.', 'tasty-fonts'),
            'tasty_fonts_bunny_invalid_type',
            __('The downloaded file was not returned as a WOFF2 font.', 'tasty-fonts'),
            'tasty_fonts_bunny_write_failed',
            __('The imported font file could not be written to uploads/fonts.', 'tasty-fonts'),
            'tasty_fonts_bunny_no_faces',
            __('No usable Bunny Fonts faces were returned for that family.', 'tasty-fonts'),
            'tasty_fonts_bunny_empty_manifest',
            __('No local font files were saved from that import.', 'tasty-fonts'),
            'tasty_fonts_missing_family',
            __('Choose a Bunny Fonts family before importing.', 'tasty-fonts'),
            __('%s already exists in the library for the selected variants.', 'tasty-fonts'),
            __('Bunny CDN delivery for %s already includes the selected variants.', 'tasty-fonts'),
            __('Added %1$s as a self-hosted Bunny delivery (%2$d variant%3$s, %4$d file%5$s).', 'tasty-fonts'),
            __('Added %1$s as a Bunny CDN delivery (%2$d variant%3$s).', 'tasty-fonts')
        );
    }

    public function normalizeFormatMode(string $formatMode): string
    {
        return 'static';
    }

    public function profileFormatFilter(string $formatMode): string
    {
        return '';
    }

    /**
     * @param list<string> $requestedVariants
     * @return list<string>
     */
    public function normalizeRequestedVariants(array $requestedVariants, string $formatMode): array
    {
        return $requestedVariants;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchMetadata(string $familyName): ?array
    {
        return $this->client->getFamily($familyName);
    }

    /**
     * @param list<string> $variants
     * @param array<string, mixed>|null $metadata
     */
    public function fetchCss(
        string $familyName,
        array $variants,
        ?array $metadata,
        string $formatMode
    ): string|WP_Error {
        unset($metadata, $formatMode);

        return $this->client->fetchCss($familyName, $variants);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseFaces(string $css, string $familyName): array
    {
        return FontUtils::normalizeFaceList($this->parser->parse($css, $familyName));
    }

    /**
     * @param HostedImportContext $context
     * @return array{profile: array<string, mixed>, face_provider: array<string, mixed>}
     */
    public function buildProfileDraft(array $context): array
    {
        $deliveryMode = $this->stringValue($context, 'delivery_mode', 'self_hosted');
        $metadata = $this->normalizeMap($context['metadata'] ?? null);
        $existingProfile = $this->normalizeMap($context['existing_profile'] ?? null);
        $importedVariants = FontUtils::normalizeVariantTokens((array) $context['imported_variants']);
        $timestampKey = $deliveryMode === 'cdn' ? 'saved_at' : 'imported_at';

        $profileId = $this->stringValue($existingProfile, 'id');

        if ($profileId === '') {
            $profileId = $this->profileId($deliveryMode);
        }

        return [
            'profile' => [
                'id' => $profileId,
                'provider' => 'bunny',
                'type' => $deliveryMode,
                'format' => 'static',
                'label' => $deliveryMode === 'cdn'
                    ? __('Bunny CDN', 'tasty-fonts')
                    : __('Self-hosted (Bunny import)', 'tasty-fonts'),
                'meta' => [
                    'category' => $this->stringValue($metadata, 'category'),
                    $timestampKey => current_time('mysql'),
                ],
            ],
            'face_provider' => $this->buildProviderMetadata($metadata, $importedVariants),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<string> $variants
     * @return array<string, mixed>
     */
    private function buildProviderMetadata(array $metadata, array $variants): array
    {
        return [
            'type' => 'bunny',
            'category' => $this->stringValue($metadata, 'category'),
            'variants' => $variants,
        ];
    }

    private function profileId(string $deliveryMode): string
    {
        return FontUtils::slugify('bunny-' . $deliveryMode);
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function normalizeMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                continue;
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $value = FontUtils::scalarStringValue($values[$key]);

        return $value !== '' ? $value : $default;
    }
}
