<?php

declare(strict_types=1);

namespace TastyFonts\Google;

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
final class GoogleImportService implements HostedImportProviderAdapterInterface
{
    private const MAX_FONT_FILE_BYTES = 10 * MB_IN_BYTES;

    public function __construct(
        private readonly GoogleFontsClient $client,
        private readonly GoogleCssParser $parser,
        private readonly HostedImportWorkflow $workflow
    ) {}

    /**
     * @param list<string> $variants
     * @return ImportResult|WP_Error
     */
    public function importFamily(
        string $familyName,
        array $variants,
        string $deliveryMode = 'self_hosted',
        string $formatMode = 'static'
    ): array|WP_Error {
        return $this->workflow->import(
            new HostedImportRequest($familyName, $variants, $deliveryMode, $formatMode),
            $this
        );
    }

    public function providerKey(): string
    {
        return 'google';
    }

    public function config(): HostedImportProviderConfig
    {
        return new HostedImportProviderConfig(
            'google',
            'google',
            'fonts.gstatic.com',
            self::MAX_FONT_FILE_BYTES,
            'tasty_fonts_google_family_dir_failed',
            __('The Google Fonts import directory could not be created.', 'tasty-fonts'),
            'tasty_fonts_google_invalid_host',
            __('Google font downloads must come from fonts.gstatic.com.', 'tasty-fonts'),
            'tasty_fonts_google_invalid_extension',
            __('Only WOFF2 files can be imported from Google Fonts.', 'tasty-fonts'),
            'tasty_fonts_google_download_failed',
            __('Font download failed with status %d.', 'tasty-fonts'),
            'tasty_fonts_google_empty_file',
            __('Google Fonts returned an empty font file.', 'tasty-fonts'),
            'tasty_fonts_google_file_too_large',
            __('The downloaded font file exceeded the safety size limit.', 'tasty-fonts'),
            'tasty_fonts_google_invalid_type',
            __('The downloaded file was not returned as a WOFF2 font.', 'tasty-fonts'),
            'tasty_fonts_google_write_failed',
            __('The imported font file could not be written to uploads/fonts.', 'tasty-fonts'),
            'tasty_fonts_google_no_faces',
            __('No usable Google Fonts faces were returned for that family.', 'tasty-fonts'),
            'tasty_fonts_google_empty_manifest',
            __('No local font files were saved from that import.', 'tasty-fonts'),
            'tasty_fonts_missing_family',
            __('Choose a Google Fonts family before importing.', 'tasty-fonts'),
            __('%s already exists in the library for the selected variants.', 'tasty-fonts'),
            __('Google CDN delivery for %s already includes the selected variants.', 'tasty-fonts'),
            __('Added %1$s as a self-hosted Google delivery (%2$d variant%3$s, %4$d file%5$s).', 'tasty-fonts'),
            __('Added %1$s as a Google CDN delivery (%2$d variant%3$s).', 'tasty-fonts')
        );
    }

    public function normalizeFormatMode(string $formatMode): string
    {
        $formatMode = strtolower(trim($formatMode));

        return $formatMode === 'variable' ? 'variable' : 'static';
    }

    public function profileFormatFilter(string $formatMode): string
    {
        return $this->normalizeFormatMode($formatMode);
    }

    /**
     * @param list<string> $requestedVariants
     * @return list<string>
     */
    public function normalizeRequestedVariants(array $requestedVariants, string $formatMode): array
    {
        if ($this->normalizeFormatMode($formatMode) !== 'variable') {
            return $requestedVariants;
        }

        return $this->normalizeVariableRequestVariants($requestedVariants);
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
        return $this->client->fetchCss(
            $familyName,
            $variants,
            'swap',
            is_array($metadata) ? $metadata : [],
            $this->normalizeFormatMode($formatMode)
        );
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
        $formatMode = $this->normalizeFormatMode($this->stringValue($context, 'format_mode', 'static'));
        $metadata = $this->normalizeMap($context['metadata'] ?? null);
        $existingFamily = is_array($context['existing_family'] ?? null)
            ? $this->normalizeMap($context['existing_family'])
            : null;
        $importedVariants = FontUtils::normalizeVariantTokens((array) $context['imported_variants']);
        $timestampKey = $deliveryMode === 'cdn' ? 'saved_at' : 'imported_at';

        return [
            'profile' => [
                'id' => $this->resolveProfileId($existingFamily, $deliveryMode, $formatMode),
                'provider' => 'google',
                'type' => $deliveryMode,
                'format' => $formatMode,
                'label' => $deliveryMode === 'cdn'
                    ? __('Google CDN', 'tasty-fonts')
                    : __('Self-hosted (Google import)', 'tasty-fonts'),
                'meta' => [
                    'category' => $this->stringValue($metadata, 'category'),
                    'lastModified' => $this->stringValue($metadata, 'lastModified'),
                    'version' => $this->stringValue($metadata, 'version'),
                    $timestampKey => current_time('mysql'),
                ],
            ],
            'face_provider' => $this->buildProviderMetadata($metadata, $importedVariants),
        ];
    }

    /**
     * @param list<string> $requestedVariants
     * @return list<string>
     */
    private function normalizeVariableRequestVariants(array $requestedVariants): array
    {
        $styles = [];

        foreach ($requestedVariants as $variant) {
            $axis = FontUtils::googleVariantToAxis((string) $variant);

            if ($axis === null) {
                continue;
            }

            $style = $axis['style'] === 'italic' ? 'italic' : 'normal';
            $styles[$style] = $style === 'italic' ? 'italic' : 'regular';
        }

        if ($styles === []) {
            return ['regular'];
        }

        return array_values($styles);
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<string> $variants
     * @return array<string, mixed>
     */
    private function buildProviderMetadata(array $metadata, array $variants): array
    {
        return [
            'type' => 'google',
            'category' => $this->stringValue($metadata, 'category'),
            'variants' => $variants,
            'lastModified' => $this->stringValue($metadata, 'lastModified'),
            'version' => $this->stringValue($metadata, 'version'),
        ];
    }

    private function profileId(string $deliveryMode): string
    {
        return FontUtils::slugify('google-' . $deliveryMode);
    }

    /**
     * @param array<string, mixed>|null $family
     */
    private function resolveProfileId(?array $family, string $deliveryMode, string $formatMode): string
    {
        $existing = $this->findDeliveryProfile($family, 'google', $deliveryMode, $formatMode);
        $existingId = $this->stringValue($existing ?? [], 'id');

        if ($existingId !== '') {
            return $existingId;
        }

        $baseId = $this->profileId($deliveryMode);

        if (!$this->findLegacyProfileConflict($family, 'google', $deliveryMode)) {
            return $baseId;
        }

        return FontUtils::slugify($baseId . '-' . $formatMode);
    }

    /**
     * @param array<string, mixed>|null $family
     */
    private function findLegacyProfileConflict(?array $family, string $provider, string $type): bool
    {
        return $this->findDeliveryProfile($family, $provider, $type) !== null;
    }

    /**
     * @param array<string, mixed>|null $family
     * @return array<string, mixed>|null
     */
    private function findDeliveryProfile(?array $family, string $provider, string $type, string $formatMode = ''): ?array
    {
        if (!is_array($family)) {
            return null;
        }

        $provider = strtolower(trim($provider));
        $type = strtolower(trim($type));

        foreach ((array) ($family['delivery_profiles'] ?? []) as $profile) {
            $profile = $this->normalizeMap($profile);

            if (
                $profile === []
                || strtolower($this->stringValue($profile, 'provider')) !== $provider
                || strtolower($this->stringValue($profile, 'type')) !== $type
            ) {
                continue;
            }

            if ($formatMode !== '' && FontUtils::resolveProfileFormat($profile) !== $formatMode) {
                continue;
            }

            return $profile;
        }

        return null;
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
