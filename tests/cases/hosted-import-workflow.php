<?php

declare(strict_types=1);

use TastyFonts\Fonts\CdnImportStrategy;
use TastyFonts\Fonts\HostedImportProviderAdapterInterface;
use TastyFonts\Fonts\HostedImportProviderConfig;
use TastyFonts\Fonts\HostedImportRequest;
use TastyFonts\Fonts\HostedImportVariantPlanner;
use TastyFonts\Fonts\HostedImportWorkflow;
use TastyFonts\Fonts\SelfHostedImportStrategy;
use TastyFonts\Support\FontUtils;
use TastyFonts\Repository\LogRepository;

$tests['hosted_import_variant_planner_skips_existing_faces'] = static function (): void {
    $planner = new HostedImportVariantPlanner();

    $plan = $planner->plan(
        ['regular', 'bold', '700', 'bogus', 'regular'],
        [
            'faces' => [
                ['weight' => '400', 'style' => 'normal'],
                'ignore',
                [0 => 'ignore numeric-key-only rows'],
            ],
        ]
    );

    assertSameValue(
        ['700'],
        $plan['import'],
        'Planner should import normalized requested variants that are not already represented by existing faces.'
    );
    assertSameValue(
        ['regular'],
        $plan['skipped'],
        'Planner should skip requested variants whose face axis already exists in the profile.'
    );
};

$tests['hosted_import_variant_planner_returns_empty_import_when_all_faces_exist'] = static function (): void {
    $planner = new HostedImportVariantPlanner();

    $plan = $planner->plan(
        ['regular', 'italic'],
        [
            'faces' => [
                ['weight' => '400', 'style' => 'normal'],
                ['weight' => '400', 'style' => 'italic'],
            ],
        ]
    );

    assertSameValue([], $plan['import'], 'Planner should not import variants already available in the existing profile.');
    assertSameValue(['regular', 'italic'], $plan['skipped'], 'Planner should report skipped variants in normalized order.');
};

$tests['hosted_import_variant_planner_collapses_variable_requests_by_style'] = static function (): void {
    $planner = new HostedImportVariantPlanner();

    $plan = $planner->plan(
        ['100', '700italic', '900italic'],
        [
            'faces' => [
                ['weight' => '400', 'style' => 'italic'],
            ],
        ],
        static function (array $requestedVariants): array {
            $styles = [];

            foreach ($requestedVariants as $variant) {
                $axis = FontUtils::googleVariantToAxis($variant);

                if ($axis === null) {
                    continue;
                }

                $style = $axis['style'] === 'italic' ? 'italic' : 'normal';
                $styles[$style] = $style === 'italic' ? 'italic' : 'regular';
            }

            return $styles === [] ? ['regular'] : array_values($styles);
        }
    );

    assertSameValue(['regular'], $plan['import'], 'Variable planning should collapse requested weights to a normal-style request.');
    assertSameValue(['italic'], $plan['skipped'], 'Variable planning should skip a collapsed italic request when an italic face already exists.');
};

$tests['hosted_import_variant_planner_normalizes_before_custom_callback'] = static function (): void {
    $planner = new HostedImportVariantPlanner();
    $callbackInput = null;

    $plan = $planner->plan(
        ['bold', 'extra-bold italic', 'not-a-variant'],
        null,
        static function (array $requestedVariants) use (&$callbackInput): array {
            $callbackInput = $requestedVariants;

            return $requestedVariants;
        }
    );

    assertSameValue(['700', '800italic'], $callbackInput, 'Custom variant normalizers should receive canonical requested variants.');
    assertSameValue(['700', '800italic'], $plan['import'], 'Planner should keep custom-normalized import variants canonical and unique.');
    assertSameValue([], $plan['skipped'], 'Planner should not report skipped variants without an existing profile.');
};

final class HostedImportWorkflowStubAdapter implements HostedImportProviderAdapterInterface
{
    /** @var list<array<string, mixed>> */
    public array $faces;
    public int $cssFetches = 0;
    public int $metadataFetches = 0;

    /**
     * @param list<array<string, mixed>> $faces
     */
    public function __construct(array $faces)
    {
        $this->faces = $faces;
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
            10 * MB_IN_BYTES,
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

    public function normalizeRequestedVariants(array $requestedVariants, string $formatMode): array
    {
        return $requestedVariants;
    }

    public function fetchMetadata(string $familyName): ?array
    {
        $this->metadataFetches++;

        return ['category' => 'sans-serif'];
    }

    public function fetchCss(string $familyName, array $variants, ?array $metadata, string $formatMode): string|WP_Error
    {
        $this->cssFetches++;

        return 'stub css';
    }

    public function parseFaces(string $css, string $familyName): array
    {
        return $this->faces;
    }

    public function buildProfileDraft(array $context): array
    {
        $deliveryMode = (string) ($context['delivery_mode'] ?? 'self_hosted');
        $importedVariants = FontUtils::normalizeVariantTokens((array) ($context['imported_variants'] ?? []));
        $metadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];
        $timestampKey = $deliveryMode === 'cdn' ? 'saved_at' : 'imported_at';

        return [
            'profile' => [
                'id' => FontUtils::slugify('bunny-' . $deliveryMode),
                'provider' => 'bunny',
                'type' => $deliveryMode,
                'format' => 'static',
                'label' => $deliveryMode === 'cdn' ? __('Bunny CDN', 'tasty-fonts') : __('Self-hosted (Bunny import)', 'tasty-fonts'),
                'meta' => [
                    'category' => FontUtils::scalarStringValue($metadata['category'] ?? ''),
                    $timestampKey => current_time('mysql'),
                ],
            ],
            'face_provider' => [
                'type' => 'bunny',
                'category' => FontUtils::scalarStringValue($metadata['category'] ?? ''),
                'variants' => $importedVariants,
            ],
        ];
    }
}

/**
 * @return array<string, mixed>
 */
function makeHostedImportWorkflowTestGraph(): array
{
    $services = makeServiceGraph();
    $services['hosted_import_workflow'] = new HostedImportWorkflow(
        $services['imports'],
        $services['assets'],
        $services['log'],
        new HostedImportVariantPlanner(),
        [
            'cdn' => new CdnImportStrategy(),
            'self_hosted' => new SelfHostedImportStrategy($services['storage']),
        ]
    );

    return $services;
}

function hostedImportWorkflowRegularFace(string $url): array
{
    return [
        'family' => 'Inter',
        'weight' => '400',
        'style' => 'normal',
        'unicode_range' => 'U+0000-00FF',
        'files' => ['woff2' => $url],
    ];
}

$tests['hosted_import_workflow_saves_cdn_profile_without_downloading_files'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;

    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $services = makeHostedImportWorkflowTestGraph();
    $adapter = new HostedImportWorkflowStubAdapter([hostedImportWorkflowRegularFace($fontUrl)]);

    $result = $services['hosted_import_workflow']->import(
        new HostedImportRequest('Inter', ['regular'], 'cdn'),
        $adapter
    );
    $family = $services['imports']->getFamily('inter');
    $profile = (array) (($family['delivery_profiles']['bunny-cdn'] ?? null) ?: []);

    assertFalseValue(is_wp_error($result), 'Workflow CDN imports should return a result payload.');
    assertSameValue('saved', (string) ($result['status'] ?? ''), 'Workflow CDN imports should preserve the saved status.');
    assertSameValue(0, (int) ($result['files'] ?? -1), 'Workflow CDN imports should not report downloaded files.');
    assertSameValue([], $remoteGetCalls, 'Workflow CDN imports should not download remote font files.');
    assertSameValue('bunny', (string) ($profile['provider'] ?? ''), 'Workflow CDN imports should persist the provider profile.');
    assertSameValue($fontUrl, (string) ($profile['faces'][0]['files']['woff2'] ?? ''), 'Workflow CDN faces should keep the remote WOFF2 URL.');
    assertSameValue('bunny', (string) ($profile['faces'][0]['source'] ?? ''), 'Workflow CDN faces should keep the configured source.');
    assertSameValue(1, did_action('tasty_fonts_after_import'), 'Workflow CDN imports should fire the after-import hook on success.');
    assertSameValue(1, $adapter->cssFetches, 'Workflow CDN imports should fetch provider CSS once.');
};

$tests['hosted_import_workflow_downloads_and_writes_self_hosted_faces'] = static function (): void {
    resetTestState();

    global $remoteGetCalls;
    global $remoteGetResponses;

    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $remoteGetResponses[$fontUrl] = [
        'response' => ['code' => 200],
        'headers' => ['content-type' => 'font/woff2'],
        'body' => 'latin-font-data',
    ];
    $services = makeHostedImportWorkflowTestGraph();
    $adapter = new HostedImportWorkflowStubAdapter([hostedImportWorkflowRegularFace($fontUrl)]);

    $result = $services['hosted_import_workflow']->import(
        new HostedImportRequest('Inter', ['regular'], 'self_hosted'),
        $adapter
    );
    $family = $services['imports']->getFamily('inter');
    $profile = (array) (($family['delivery_profiles']['bunny-self_hosted'] ?? null) ?: []);
    $storedPath = $services['storage']->pathForRelativePath('bunny/inter/inter-400-normal.woff2');
    $downloadUrls = array_map(static fn (array $call): string => (string) ($call['url'] ?? ''), $remoteGetCalls);

    assertFalseValue(is_wp_error($result), 'Workflow self-hosted imports should return a result payload.');
    assertSameValue('imported', (string) ($result['status'] ?? ''), 'Workflow self-hosted imports should preserve the imported status.');
    assertSameValue(1, (int) ($result['files'] ?? 0), 'Workflow self-hosted imports should count newly downloaded files.');
    assertSameValue(true, is_string($storedPath) && file_exists($storedPath), 'Workflow self-hosted imports should write files below the provider directory.');
    assertContainsValue($fontUrl, implode("\n", $downloadUrls), 'Workflow self-hosted imports should download the selected WOFF2 URL.');
    assertSameValue('bunny/inter/inter-400-normal.woff2', (string) ($profile['faces'][0]['files']['woff2'] ?? ''), 'Workflow self-hosted faces should store relative WOFF2 paths.');
    assertSameValue('bunny', (string) ($profile['faces'][0]['provider']['type'] ?? ''), 'Workflow self-hosted faces should persist adapter provider metadata.');
    assertSameValue(1, did_action('tasty_fonts_after_import'), 'Workflow self-hosted imports should fire the after-import hook on success.');
};

$tests['hosted_import_workflow_skips_existing_variants_without_completion_side_effects'] = static function (): void {
    resetTestState();

    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $services = makeHostedImportWorkflowTestGraph();
    $adapter = new HostedImportWorkflowStubAdapter([hostedImportWorkflowRegularFace($fontUrl)]);

    $first = $services['hosted_import_workflow']->import(new HostedImportRequest('Inter', ['regular'], 'cdn'), $adapter);
    $second = $services['hosted_import_workflow']->import(new HostedImportRequest('Inter', ['regular'], 'cdn'), $adapter);
    $logs = $services['log']->all();

    assertSameValue('saved', (string) ($first['status'] ?? ''), 'The first workflow CDN import should save the profile.');
    assertSameValue('skipped', (string) ($second['status'] ?? ''), 'The second workflow import should skip already-present variants.');
    assertSameValue(['regular'], (array) ($second['skipped_variants'] ?? []), 'Skipped workflow imports should report skipped variants.');
    assertSameValue(1, did_action('tasty_fonts_after_import'), 'Skipped workflow imports should not fire the after-import hook.');
    assertSameValue(1, $adapter->cssFetches, 'Skipped workflow imports should not fetch provider CSS.');
    assertSameValue('hosted_import_skipped', (string) ($logs[0]['event'] ?? ''), 'Skipped workflow imports should log the skip event.');
    assertSameValue(LogRepository::CATEGORY_IMPORT, (string) ($logs[0]['category'] ?? ''), 'Skipped workflow imports should log under the import category.');
};

$tests['hosted_import_workflow_propagates_download_wp_error_codes'] = static function (): void {
    resetTestState();

    global $remoteGetResponses;

    $fontUrl = 'https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2';
    $remoteGetResponses[$fontUrl] = new WP_Error('http_request_failed', 'cURL error 28: Connection timed out.');
    $services = makeHostedImportWorkflowTestGraph();
    $adapter = new HostedImportWorkflowStubAdapter([hostedImportWorkflowRegularFace($fontUrl)]);

    $result = $services['hosted_import_workflow']->import(
        new HostedImportRequest('Inter', ['regular'], 'self_hosted'),
        $adapter
    );

    assertWpErrorCode('http_request_failed', $result, 'Workflow font downloads should preserve WP_Error codes from wp_remote_get.');
    assertSameValue(0, did_action('tasty_fonts_after_import'), 'Failed workflow imports should not fire the after-import hook.');
    assertSameValue(null, $services['imports']->getFamily('inter'), 'Failed workflow imports should not persist profiles.');
};
