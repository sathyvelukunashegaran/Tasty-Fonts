<?php

declare(strict_types=1);

namespace TastyFonts\CustomCss;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\HostedImportSupport;
use TastyFonts\Fonts\AssetService;
use TastyFonts\Fonts\CatalogService;
use TastyFonts\Repository\FamilyMetadataRepository;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

/**
 * Executes final imports from validated custom CSS dry-run snapshots.
 *
 * @phpstan-type ValidatedContract array<string, mixed>
 * @phpstan-type ImportFamily array<string, mixed>
 * @phpstan-type ImportFace array<string, mixed>
 * @phpstan-type DownloadResult array{body: string, size: int, sha256: string, content_type: string}
 * @phpstan-type RevalidationResult array{method: string, content_type: string, content_length: int, notes: list<string>, warnings: list<string>}
 */
final class CustomCssFinalImportService
{
    private const FONT_SIZE_LIMIT_BYTES = 10485760;
    private const REQUEST_TIMEOUT = 10;
    private const SUPPORTED_FORMATS = ['woff2', 'woff'];

    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly FamilyMetadataRepository $familyMetadata,
        private readonly CatalogService $catalog,
        private readonly AssetService $assets,
        private readonly LogRepository $log,
        private readonly ?CustomCssFontValidator $validator = null
    ) {
    }

    private function getValidator(): CustomCssFontValidator
    {
        return $this->validator ?? new CustomCssFontValidator();
    }

    /**
     * @param ValidatedContract $contract
     * @return array<string, mixed>|WP_Error
     */
    public function importSelfHosted(array $contract): array|WP_Error
    {
        $deliveryMode = strtolower(trim(FontUtils::scalarStringValue($contract['delivery_mode'] ?? 'self_hosted')));

        if ($deliveryMode === 'remote') {
            $deliveryMode = 'cdn';
        }

        if ($deliveryMode === 'cdn') {
            return $this->importRemote($contract);
        }

        if ($deliveryMode !== 'self_hosted') {
            return new WP_Error(
                'tasty_fonts_custom_css_delivery_invalid',
                __('Choose self-hosted or remote serving for the custom CSS import.', 'tasty-fonts')
            );
        }

        $source = FontUtils::normalizeStringKeyedMap($contract['source'] ?? null);
        $sourceUrl = FontUtils::scalarStringValue($source['url'] ?? '');
        $sourceHost = FontUtils::scalarStringValue($source['host'] ?? '');
        $options = FontUtils::normalizeStringKeyedMap($contract['options'] ?? null);
        $activate = !empty($options['activate']);
        $publish = !empty($options['publish']);
        $duplicateHandling = $this->normalizeDuplicateHandling(FontUtils::scalarStringValue($contract['duplicate_handling'] ?? 'skip'));
        $families = FontUtils::normalizeListOfStringKeyedMaps($contract['families'] ?? null);

        if ($families === []) {
            return new WP_Error(
                'tasty_fonts_custom_css_no_selected_faces',
                __('Select at least one validated font face before importing.', 'tasty-fonts')
            );
        }

        $root = $this->customStorageRoot();

        if (is_wp_error($root)) {
            return $root;
        }

        $catalogBySlug = $this->catalogFamiliesBySlug();
        $importedFamilies = [];
        $importedFaces = [];
        $failedFaces = [];
        $skippedFaces = [];
        $replacedFaces = [];
        $writtenFileCount = 0;
        $deletedFileCount = 0;

        foreach ($families as $family) {
            $familyName = sanitize_text_field(FontUtils::scalarStringValue($family['family'] ?? ''));
            $familySlug = FontUtils::slugify(FontUtils::scalarStringValue($family['slug'] ?? $familyName));
            $existingFamily = $catalogBySlug[$familySlug] ?? null;

            if (is_array($existingFamily)) {
                $canonicalName = sanitize_text_field(FontUtils::scalarStringValue($existingFamily['family'] ?? ''));
                $familyName = $canonicalName !== '' ? $canonicalName : $familyName;
            }

            $fallback = FontUtils::sanitizeFallback(FontUtils::scalarStringValue($family['fallback'] ?? 'sans-serif'));

            if ($familyName === '' || $familySlug === '') {
                continue;
            }

            $deliveryId = $this->buildDeliveryId($familySlug, $family, 'self_hosted');
            $familyDirectory = wp_normalize_path(trailingslashit($root) . $familySlug . '/' . $deliveryId);
            $profileFaces = [];
            $familyWrittenPaths = [];
            $familyImportedFaces = [];
            $familyReplacementFaces = [];
            $familyWrittenFileCount = 0;

            foreach (FontUtils::normalizeListOfStringKeyedMaps($family['faces'] ?? null) as $face) {
                $duplicateResolution = $this->resolveDuplicateResolution($familySlug, $face, 'self_hosted', $duplicateHandling);

                if ($duplicateResolution['action'] === 'skip') {
                    $skippedFaces[] = $this->skippedFacePayload($familyName, $face, $duplicateResolution['message']);
                    continue;
                }

                $download = $this->downloadFace($face);

                if (is_wp_error($download)) {
                    $failedFaces[] = $this->failedFacePayload($familyName, $face, $download);
                    continue;
                }

                $format = strtolower(FontUtils::scalarStringValue($face['format'] ?? ''));
                $filename = $this->buildFilename($familySlug, $face, $download['sha256']);
                $absolutePath = wp_normalize_path(trailingslashit($familyDirectory) . $filename);
                $relativePath = $this->storage->relativePath($absolutePath);

                if ($relativePath === '') {
                    $failedFaces[] = $this->failedFacePayload(
                        $familyName,
                        $face,
                        new WP_Error(
                            'tasty_fonts_custom_css_storage_path_invalid',
                            __('The target font storage path could not be prepared.', 'tasty-fonts')
                        )
                    );
                    continue;
                }

                if (!$this->storage->writeAbsoluteFile($absolutePath, $download['body'])) {
                    $failedFaces[] = $this->failedFacePayload(
                        $familyName,
                        $face,
                        new WP_Error(
                            'tasty_fonts_custom_css_write_failed',
                            $this->storageErrorMessage(__('The downloaded font file could not be written to storage.', 'tasty-fonts'))
                        )
                    );
                    continue;
                }

                $familyWrittenPaths[] = $relativePath;
                $familyWrittenFileCount++;
                $profileFace = $this->buildProfileFace($familyName, $familySlug, $face, $format, $relativePath, $download, $sourceUrl, $sourceHost);
                $profileFaces[] = $profileFace;
                $familyImportedFaces[] = [
                    'id' => sanitize_key(FontUtils::scalarStringValue($face['id'] ?? '')),
                    'family' => $familyName,
                    'weight' => FontUtils::normalizeWeight(FontUtils::scalarStringValue($face['weight'] ?? '400')),
                    'style' => FontUtils::normalizeStyle(FontUtils::scalarStringValue($face['style'] ?? 'normal')),
                    'format' => $format,
                    'path' => $relativePath,
                ];

                if ($duplicateResolution['action'] === 'replace') {
                    $familyReplacementFaces[] = $profileFace;
                }
            }

            if ($profileFaces === []) {
                continue;
            }

            $publishState = $this->resolvePublishState($existingFamily, $familySlug, $publish);
            $this->ensureExistingFamilyRecord($familyName, $familySlug, $existingFamily, $publishState);
            $profile = $this->buildProfile($deliveryId, $profileFaces, $sourceUrl, $sourceHost, 'self_hosted');
            $savedFamily = $this->imports->saveProfile($familyName, $familySlug, $profile, $publishState, $activate);

            if ($savedFamily === []) {
                $this->storage->deleteRelativeFiles($familyWrittenPaths);
                $failedFaces[] = [
                    'family' => $familyName,
                    'id' => '',
                    'url' => '',
                    'message' => __('The custom delivery profile could not be saved.', 'tasty-fonts'),
                    'code' => 'tasty_fonts_custom_css_profile_save_failed',
                ];
                continue;
            }

            if ($publish && !$activate && is_array($existingFamily) && $this->profileStringValue($existingFamily, 'active_delivery_id') !== '') {
                $this->imports->setPublishState($familySlug, $publishState);
            }

            if ($familyReplacementFaces !== []) {
                $cleanup = $this->replaceMatchingCustomFaces($familySlug, $deliveryId, 'self_hosted', $familyReplacementFaces);
                $deletedFileCount += $cleanup['files_deleted'];
                $replacedFaces = array_merge($replacedFaces, $cleanup['faces_replaced']);
            }

            array_push($importedFaces, ...$familyImportedFaces);
            $writtenFileCount += $familyWrittenFileCount;
            $this->familyMetadata->saveFallback($familyName, $fallback);
            $importedFamilies[] = [
                'family' => $familyName,
                'slug' => $familySlug,
                'delivery_id' => $deliveryId,
                'delivery_type' => 'self_hosted',
                'provider' => 'custom',
                'fallback' => $fallback,
                'faces' => count($profileFaces),
                'publish_state' => $savedFamily['publish_state'],
                'active_delivery_id' => $savedFamily['active_delivery_id'],
            ];
        }

        if ($importedFamilies === [] && $skippedFaces !== []) {
            return $this->skippedImportResult('self_hosted', $skippedFaces, $failedFaces, $writtenFileCount, $deletedFileCount);
        }

        if ($importedFamilies === []) {
            return new WP_Error(
                'tasty_fonts_custom_css_import_failed',
                $failedFaces !== []
                    ? __('No custom font faces could be imported. Review the failed face messages and run the dry run again.', 'tasty-fonts')
                    : __('No custom font faces could be imported from the selected snapshot.', 'tasty-fonts'),
                ['failed_faces' => $failedFaces]
            );
        }

        $this->assets->refreshGeneratedAssets();
        do_action('tasty_fonts_after_import', [
            'families' => $importedFamilies,
            'faces' => $importedFaces,
            'delivery_mode' => 'self_hosted',
        ], 'custom');

        $status = $failedFaces === [] && $skippedFaces === [] ? 'imported' : 'partial';
        $message = $status === 'imported'
            ? sprintf(
                /* translators: 1: number of families, 2: number of font faces. */
                __('Imported %1$d custom font family/families with %2$d self-hosted face(s).', 'tasty-fonts'),
                count($importedFamilies),
                count($importedFaces)
            )
            : sprintf(
                /* translators: 1: number of imported faces, 2: number of failed faces. */
                __('Imported %1$d custom font face(s); %2$d face(s) failed and %3$d duplicate face(s) were skipped.', 'tasty-fonts'),
                count($importedFaces),
                count($failedFaces),
                count($skippedFaces)
            );

        $this->log->add($message, [
            'category' => LogRepository::CATEGORY_IMPORT,
            'event' => 'custom_css_self_hosted_import',
            'outcome' => $status === 'imported' ? 'success' : 'warning',
            'status_label' => $status === 'imported' ? __('Imported', 'tasty-fonts') : __('Partial', 'tasty-fonts'),
            'source' => __('Custom CSS', 'tasty-fonts'),
            'details' => [
                ['label' => __('Families imported', 'tasty-fonts'), 'value' => (string) count($importedFamilies), 'kind' => 'count'],
                ['label' => __('Faces imported', 'tasty-fonts'), 'value' => (string) count($importedFaces), 'kind' => 'count'],
                ['label' => __('Faces failed', 'tasty-fonts'), 'value' => (string) count($failedFaces), 'kind' => 'count'],
                ['label' => __('Faces skipped', 'tasty-fonts'), 'value' => (string) count($skippedFaces), 'kind' => 'count'],
                ['label' => __('Faces replaced', 'tasty-fonts'), 'value' => (string) count($replacedFaces), 'kind' => 'count'],
                ['label' => __('Files written', 'tasty-fonts'), 'value' => (string) $writtenFileCount, 'kind' => 'count'],
                ['label' => __('Old files deleted', 'tasty-fonts'), 'value' => (string) $deletedFileCount, 'kind' => 'count'],
            ],
        ]);

        return [
            'status' => $status,
            'message' => $message,
            'delivery_mode' => 'self_hosted',
            'families' => $importedFamilies,
            'faces' => [
                'imported' => $importedFaces,
                'skipped' => $skippedFaces,
                'replaced' => $replacedFaces,
                'failed' => $failedFaces,
            ],
            'counts' => [
                'families_imported' => count($importedFamilies),
                'faces_imported' => count($importedFaces),
                'faces_skipped' => count($skippedFaces),
                'faces_replaced' => count($replacedFaces),
                'faces_failed' => count($failedFaces),
                'files_written' => $writtenFileCount,
                'old_files_deleted' => $deletedFileCount,
            ],
        ];
    }

    /**
     * @param ValidatedContract $contract
     * @return array<string, mixed>|WP_Error
     */
    private function importRemote(array $contract): array|WP_Error
    {
        $source = FontUtils::normalizeStringKeyedMap($contract['source'] ?? null);
        $sourceUrl = FontUtils::scalarStringValue($source['url'] ?? '');
        $sourceHost = FontUtils::scalarStringValue($source['host'] ?? '');
        $options = FontUtils::normalizeStringKeyedMap($contract['options'] ?? null);
        $activate = !empty($options['activate']);
        $publish = !empty($options['publish']);
        $duplicateHandling = $this->normalizeDuplicateHandling(FontUtils::scalarStringValue($contract['duplicate_handling'] ?? 'skip'));
        $families = FontUtils::normalizeListOfStringKeyedMaps($contract['families'] ?? null);

        if ($families === []) {
            return new WP_Error(
                'tasty_fonts_custom_css_no_selected_faces',
                __('Select at least one validated font face before importing.', 'tasty-fonts')
            );
        }

        $catalogBySlug = $this->catalogFamiliesBySlug();
        $importedFamilies = [];
        $importedFaces = [];
        $failedFaces = [];
        $skippedFaces = [];
        $replacedFaces = [];

        foreach ($families as $family) {
            $familyName = sanitize_text_field(FontUtils::scalarStringValue($family['family'] ?? ''));
            $familySlug = FontUtils::slugify(FontUtils::scalarStringValue($family['slug'] ?? $familyName));
            $existingFamily = $catalogBySlug[$familySlug] ?? null;

            if (is_array($existingFamily)) {
                $canonicalName = sanitize_text_field(FontUtils::scalarStringValue($existingFamily['family'] ?? ''));
                $familyName = $canonicalName !== '' ? $canonicalName : $familyName;
            }

            $fallback = FontUtils::sanitizeFallback(FontUtils::scalarStringValue($family['fallback'] ?? 'sans-serif'));

            if ($familyName === '' || $familySlug === '') {
                continue;
            }

            $deliveryId = $this->buildDeliveryId($familySlug, $family, 'cdn');
            $profileFaces = [];
            $familyImportedFaces = [];
            $familyReplacementFaces = [];

            foreach (FontUtils::normalizeListOfStringKeyedMaps($family['faces'] ?? null) as $face) {
                $duplicateResolution = $this->resolveDuplicateResolution($familySlug, $face, 'remote', $duplicateHandling);

                if ($duplicateResolution['action'] === 'skip') {
                    $skippedFaces[] = $this->skippedFacePayload($familyName, $face, $duplicateResolution['message']);
                    continue;
                }

                $revalidation = $this->revalidateRemoteFace($face);

                if (is_wp_error($revalidation)) {
                    $failedFaces[] = $this->failedFacePayload($familyName, $face, $revalidation);
                    continue;
                }

                $format = strtolower(FontUtils::scalarStringValue($face['format'] ?? ''));
                $fontUrl = esc_url_raw(FontUtils::scalarStringValue($face['url'] ?? ''));
                $profileFace = $this->buildRemoteProfileFace($familyName, $familySlug, $face, $format, $fontUrl, $revalidation, $sourceUrl, $sourceHost);
                $profileFaces[] = $profileFace;
                $familyImportedFaces[] = [
                    'id' => sanitize_key(FontUtils::scalarStringValue($face['id'] ?? '')),
                    'family' => $familyName,
                    'weight' => FontUtils::normalizeWeight(FontUtils::scalarStringValue($face['weight'] ?? '400')),
                    'style' => FontUtils::normalizeStyle(FontUtils::scalarStringValue($face['style'] ?? 'normal')),
                    'format' => $format,
                    'url' => $fontUrl,
                ];

                if ($duplicateResolution['action'] === 'replace') {
                    $familyReplacementFaces[] = $profileFace;
                }
            }

            if ($profileFaces === []) {
                continue;
            }

            $publishState = $this->resolvePublishState($existingFamily, $familySlug, $publish);
            $this->ensureExistingFamilyRecord($familyName, $familySlug, $existingFamily, $publishState);
            $profile = $this->buildProfile($deliveryId, $profileFaces, $sourceUrl, $sourceHost, 'cdn');
            $savedFamily = $this->imports->saveProfile($familyName, $familySlug, $profile, $publishState, $activate);

            if ($savedFamily === []) {
                $failedFaces[] = [
                    'family' => $familyName,
                    'id' => '',
                    'url' => '',
                    'message' => __('The custom remote delivery profile could not be saved.', 'tasty-fonts'),
                    'code' => 'tasty_fonts_custom_css_profile_save_failed',
                ];
                continue;
            }

            if ($publish && !$activate && is_array($existingFamily) && $this->profileStringValue($existingFamily, 'active_delivery_id') !== '') {
                $this->imports->setPublishState($familySlug, $publishState);
            }

            if ($familyReplacementFaces !== []) {
                $cleanup = $this->replaceMatchingCustomFaces($familySlug, $deliveryId, 'remote', $familyReplacementFaces);
                $replacedFaces = array_merge($replacedFaces, $cleanup['faces_replaced']);
            }

            array_push($importedFaces, ...$familyImportedFaces);
            $this->familyMetadata->saveFallback($familyName, $fallback);
            $importedFamilies[] = [
                'family' => $familyName,
                'slug' => $familySlug,
                'delivery_id' => $deliveryId,
                'delivery_type' => 'remote',
                'provider' => 'custom',
                'fallback' => $fallback,
                'faces' => count($profileFaces),
                'publish_state' => $savedFamily['publish_state'],
                'active_delivery_id' => $savedFamily['active_delivery_id'],
            ];
        }

        if ($importedFamilies === [] && $skippedFaces !== []) {
            return $this->skippedImportResult('remote', $skippedFaces, $failedFaces, 0, 0);
        }

        if ($importedFamilies === []) {
            return new WP_Error(
                'tasty_fonts_custom_css_import_failed',
                $failedFaces !== []
                    ? __('No custom remote font faces could be imported. Review the failed face messages and run the dry run again.', 'tasty-fonts')
                    : __('No custom remote font faces could be imported from the selected snapshot.', 'tasty-fonts'),
                ['failed_faces' => $failedFaces]
            );
        }

        $this->assets->refreshGeneratedAssets();
        do_action('tasty_fonts_after_import', [
            'families' => $importedFamilies,
            'faces' => $importedFaces,
            'delivery_mode' => 'remote',
        ], 'custom');

        $status = $failedFaces === [] && $skippedFaces === [] ? 'imported' : 'partial';
        $message = $status === 'imported'
            ? sprintf(
                /* translators: 1: number of families, 2: number of font faces. */
                __('Imported %1$d custom font family/families with %2$d remote face(s).', 'tasty-fonts'),
                count($importedFamilies),
                count($importedFaces)
            )
            : sprintf(
                /* translators: 1: number of imported faces, 2: number of failed faces. */
                __('Imported %1$d custom remote font face(s); %2$d face(s) failed and %3$d duplicate face(s) were skipped.', 'tasty-fonts'),
                count($importedFaces),
                count($failedFaces),
                count($skippedFaces)
            );

        $this->log->add($message, [
            'category' => LogRepository::CATEGORY_IMPORT,
            'event' => 'custom_css_remote_import',
            'outcome' => $status === 'imported' ? 'success' : 'warning',
            'status_label' => $status === 'imported' ? __('Imported', 'tasty-fonts') : __('Partial', 'tasty-fonts'),
            'source' => __('Custom CSS', 'tasty-fonts'),
            'details' => [
                ['label' => __('Families imported', 'tasty-fonts'), 'value' => (string) count($importedFamilies), 'kind' => 'count'],
                ['label' => __('Faces imported', 'tasty-fonts'), 'value' => (string) count($importedFaces), 'kind' => 'count'],
                ['label' => __('Faces failed', 'tasty-fonts'), 'value' => (string) count($failedFaces), 'kind' => 'count'],
                ['label' => __('Faces skipped', 'tasty-fonts'), 'value' => (string) count($skippedFaces), 'kind' => 'count'],
                ['label' => __('Faces replaced', 'tasty-fonts'), 'value' => (string) count($replacedFaces), 'kind' => 'count'],
                ['label' => __('Remote URLs saved', 'tasty-fonts'), 'value' => (string) count($importedFaces), 'kind' => 'count'],
            ],
        ]);

        return [
            'status' => $status,
            'message' => $message,
            'delivery_mode' => 'remote',
            'families' => $importedFamilies,
            'faces' => [
                'imported' => $importedFaces,
                'skipped' => $skippedFaces,
                'replaced' => $replacedFaces,
                'failed' => $failedFaces,
            ],
            'counts' => [
                'families_imported' => count($importedFamilies),
                'faces_imported' => count($importedFaces),
                'faces_skipped' => count($skippedFaces),
                'faces_replaced' => count($replacedFaces),
                'faces_failed' => count($failedFaces),
                'files_written' => 0,
                'remote_urls_saved' => count($importedFaces),
            ],
        ];
    }

    private function customStorageRoot(): string|WP_Error
    {
        if (!$this->storage->ensureRootDirectory()) {
            return new WP_Error(
                'tasty_fonts_storage_unavailable',
                $this->storageErrorMessage(__('The uploads/fonts storage directory could not be created.', 'tasty-fonts'))
            );
        }

        $root = $this->storage->getProviderRoot('custom');

        if (!is_string($root) || $root === '') {
            return new WP_Error(
                'tasty_fonts_storage_unavailable',
                $this->storageErrorMessage(__('The uploads/fonts/custom storage directory could not be created.', 'tasty-fonts'))
            );
        }

        return $root;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalogFamiliesBySlug(): array
    {
        $families = [];

        foreach ($this->catalog->getCatalog() as $family) {
            $family = FontUtils::normalizeStringKeyedMap($family);
            $slug = FontUtils::slugify(FontUtils::scalarStringValue($family['slug'] ?? $family['family'] ?? ''));

            if ($slug !== '') {
                $families[$slug] = $family;
            }
        }

        return $families;
    }

    /**
     * @param ImportFamily $family
     */
    private function buildDeliveryId(string $familySlug, array $family, string $deliveryMode): string
    {
        $faceIds = [];

        foreach (FontUtils::normalizeListOfStringKeyedMaps($family['faces'] ?? null) as $face) {
            $faceIds[] = sanitize_key(FontUtils::scalarStringValue($face['id'] ?? ''));
        }

        $hash = substr(hash('sha256', $familySlug . '|' . implode(',', $faceIds) . '|' . microtime(true)), 0, 10);

        $prefix = $deliveryMode === 'cdn' ? 'custom-remote-' : 'custom-self-hosted-';

        return FontUtils::slugify($prefix . gmdate('Ymd-His') . '-' . $hash);
    }

    /**
     * @param ImportFace $face
     * @return DownloadResult|WP_Error
     */
    private function downloadFace(array $face): array|WP_Error
    {
        $url = esc_url_raw(FontUtils::scalarStringValue($face['url'] ?? ''));
        $format = strtolower(FontUtils::scalarStringValue($face['format'] ?? ''));

        if (!in_array($format, self::SUPPORTED_FORMATS, true)) {
            return new WP_Error(
                'tasty_fonts_custom_css_format_unsupported',
                __('Only WOFF2 and WOFF font files can be imported from custom CSS in this phase.', 'tasty-fonts')
            );
        }

        $safetyError = $this->getValidator()->validatePublicHttpsUrl($url, 'font', 'import');

        if ($safetyError instanceof WP_Error) {
            return $safetyError;
        }

        $args = [
            'timeout' => self::REQUEST_TIMEOUT,
            'redirection' => 3,
            'reject_unsafe_urls' => true,
            'limit_response_size' => self::FONT_SIZE_LIMIT_BYTES + 1,
            'headers' => [
                'Accept' => 'font/woff2,font/woff,application/font-woff2,application/font-woff,*/*;q=0.5',
                'User-Agent' => FontUtils::MODERN_USER_AGENT,
            ],
        ];
        $filteredArgs = apply_filters('tasty_fonts_http_request_args', $args, $url);
        $response = wp_remote_get($url, FontUtils::normalizeHttpArgs(is_array($filteredArgs) ? $filteredArgs : $args));

        if (is_wp_error($response)) {
            return new WP_Error(
                'tasty_fonts_custom_css_download_failed',
                $response->get_error_message() !== ''
                    ? $response->get_error_message()
                    : __('The selected font file could not be downloaded.', 'tasty-fonts')
            );
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status !== 200 || FontUtils::scalarStringValue(wp_remote_retrieve_header($response, 'content-range')) !== '') {
            return new WP_Error(
                'tasty_fonts_custom_css_download_failed',
                sprintf(
                    /* translators: %d is an HTTP status code. */
                    __('The selected font file must return a complete HTTP 200 response during final import; received HTTP %d.', 'tasty-fonts'),
                    $status
                )
            );
        }

        $body = wp_remote_retrieve_body($response);
        $size = strlen($body);

        if ($body === '') {
            return new WP_Error(
                'tasty_fonts_custom_css_empty_file',
                __('The selected font file was empty during final import.', 'tasty-fonts')
            );
        }

        if ($size > self::FONT_SIZE_LIMIT_BYTES) {
            return new WP_Error(
                'tasty_fonts_custom_css_file_too_large',
                sprintf(
                    /* translators: %s is the maximum font response size. */
                    __('The selected font file is larger than the %s final import limit.', 'tasty-fonts'),
                    size_format(self::FONT_SIZE_LIMIT_BYTES)
                )
            );
        }

        $contentType = strtolower(trim(FontUtils::scalarStringValue(wp_remote_retrieve_header($response, 'content-type'))));
        $contentTypeCode = $this->getValidator()->validateContentType($contentType, $format);

        if ($contentTypeCode !== null) {
            return new WP_Error(
                'tasty_fonts_custom_css_invalid_content_type',
                sprintf(
                    /* translators: 1: returned content type, 2: expected font format label. */
                    __('The final font download returned %1$s instead of %2$s font content.', 'tasty-fonts'),
                    $contentType,
                    strtoupper($format)
                )
            );
        }

        if (!$this->getValidator()->fontSignatureMatches($body, $format)) {
            return new WP_Error(
                'tasty_fonts_custom_css_invalid_signature',
                sprintf(
                    /* translators: %s is a font format label. */
                    __('The final download did not match the reviewed %s font signature.', 'tasty-fonts'),
                    strtoupper($format)
                )
            );
        }

        $materialDifference = $this->materialDifferenceError($face, $body);

        if ($materialDifference instanceof WP_Error) {
            return $materialDifference;
        }

        return [
            'body' => $body,
            'size' => $size,
            'sha256' => hash('sha256', $body),
            'content_type' => $contentType,
        ];
    }

    /**
     * @param ImportFace $face
     * @return RevalidationResult|WP_Error
     */
    private function revalidateRemoteFace(array $face): array|WP_Error
    {
        $url = esc_url_raw(FontUtils::scalarStringValue($face['url'] ?? ''));
        $format = strtolower(FontUtils::scalarStringValue($face['format'] ?? ''));

        if (!in_array($format, self::SUPPORTED_FORMATS, true)) {
            return new WP_Error(
                'tasty_fonts_custom_css_format_unsupported',
                __('Only WOFF2 and WOFF font files can be imported from custom CSS in this phase.', 'tasty-fonts')
            );
        }

        $safetyError = $this->getValidator()->validatePublicHttpsUrl($url, 'font', 'import');

        if ($safetyError instanceof WP_Error) {
            return $safetyError;
        }

        $result = $this->getValidator()->validateFontUrl($url, $format, 'import');

        if ($result->status === ValidationResult::STATUS_INVALID) {
            $message = match ($result->code) {
                ValidationResult::HEAD_FAILED, ValidationResult::HTTP_ERROR => sprintf(
                    /* translators: %d is the HTTP status code returned by the font URL. */
                    __('The remote font URL returned HTTP %d during final import revalidation. Run the dry run again before importing this face.', 'tasty-fonts'),
                    $result->httpStatus
                ),
                ValidationResult::TOO_LARGE => sprintf(
                    /* translators: %s is the maximum font response size. */
                    __('The selected remote font file is larger than the %s final import limit.', 'tasty-fonts'),
                    size_format(self::FONT_SIZE_LIMIT_BYTES)
                ),
                ValidationResult::CONTENT_TYPE_ERROR => sprintf(
                    /* translators: 1: returned content type, 2: expected font format label. */
                    __('The final font download returned %1$s instead of %2$s font content.', 'tasty-fonts'),
                    $result->contentType,
                    strtoupper($format)
                ),
                ValidationResult::SIGNATURE_MISMATCH => sprintf(
                    /* translators: %s is a font format label. */
                    __('The final remote check did not match the reviewed %s font signature.', 'tasty-fonts'),
                    strtoupper($format)
                ),
                ValidationResult::TIMEOUT => __('The remote font URL request timed out during final import revalidation.', 'tasty-fonts'),
                default => $result->notes[0] ?? __('The remote font URL could not be reached during final import revalidation.', 'tasty-fonts'),
            };

            return new WP_Error('tasty_fonts_custom_css_remote_revalidation_failed', $message);
        }

        $revalidation = [
            'method' => $result->method,
            'content_type' => $result->contentType,
            'content_length' => $result->contentLength,
            'notes' => array_values(array_filter(array_merge(
                $result->notes,
                [sprintf(__('%s signature matched during final import revalidation.', 'tasty-fonts'), strtoupper($format))]
            ))),
            'warnings' => $result->warnings,
        ];

        $materialDifference = $this->remoteMaterialDifferenceError($face, $result->contentLength);

        if ($materialDifference instanceof WP_Error) {
            return $materialDifference;
        }

        $revalidation['warnings'] = array_values(array_unique(array_filter(array_merge(
            is_array($face['warnings'] ?? null)
                ? array_map(static fn (mixed $warning): string => FontUtils::scalarStringValue($warning), $face['warnings'])
                : [],
            $revalidation['warnings']
        ), static fn (string $warning): bool => $warning !== '')));

        return $revalidation;
    }

    /**
     * @param ImportFace $face
     */
    private function remoteMaterialDifferenceError(array $face, int $contentLength): ?WP_Error
    {
        if ($contentLength <= 0) {
            return null;
        }

        $validation = FontUtils::normalizeStringKeyedMap($face['validation'] ?? null);
        $reviewedLength = FontUtils::scalarIntValue($validation['content_length'] ?? 0, 0);

        if ($reviewedLength > 0 && $reviewedLength !== $contentLength) {
            return new WP_Error(
                'tasty_fonts_custom_css_material_difference',
                __('The remote font response size differs from the reviewed dry-run data. Run the dry run again before importing this face.', 'tasty-fonts')
            );
        }

        return null;
    }

    /**
     * @param ImportFace $face
     */
    private function materialDifferenceError(array $face, string $body): ?WP_Error
    {
        $validation = FontUtils::normalizeStringKeyedMap($face['validation'] ?? null);
        $reviewedLength = FontUtils::scalarIntValue($validation['content_length'] ?? 0, 0);
        $actualLength = strlen($body);

        if ($reviewedLength > 0 && $reviewedLength !== $actualLength) {
            return new WP_Error(
                'tasty_fonts_custom_css_material_difference',
                __('The final font download size differs from the reviewed dry-run data. Run the dry run again before importing this face.', 'tasty-fonts')
            );
        }

        $hash = $this->reviewedHash($validation);

        if ($hash !== '' && !hash_equals($hash, hash('sha256', $body))) {
            return new WP_Error(
                'tasty_fonts_custom_css_hash_mismatch',
                __('The final font download hash differs from the reviewed dry-run data. Run the dry run again before importing this face.', 'tasty-fonts')
            );
        }

        return null;
    }

    /**
     * @param array<string, mixed> $validation
     */
    private function reviewedHash(array $validation): string
    {
        foreach (['sha256', 'full_sha256', 'hash', 'full_hash'] as $key) {
            $value = strtolower(trim(FontUtils::scalarStringValue($validation[$key] ?? '')));

            if (preg_match('/^[a-f0-9]{64}$/', $value) === 1) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param ImportFace $face
     */
    private function buildFilename(string $familySlug, array $face, string $hash): string
    {
        $weight = preg_replace('/[^0-9a-z._-]+/i', '-', FontUtils::normalizeWeight(FontUtils::scalarStringValue($face['weight'] ?? '400'))) ?: '400';
        $style = FontUtils::normalizeStyle(FontUtils::scalarStringValue($face['style'] ?? 'normal'));
        $format = strtolower(FontUtils::scalarStringValue($face['format'] ?? 'woff2'));
        $faceId = sanitize_key(FontUtils::scalarStringValue($face['id'] ?? 'face'));
        $parts = array_filter([
            $familySlug,
            trim($weight, '-'),
            $style,
            substr($faceId, 0, 24),
            substr($hash, 0, 10),
        ], static fn (string $part): bool => $part !== '');

        return implode('-', $parts) . '.' . (in_array($format, self::SUPPORTED_FORMATS, true) ? $format : 'woff2');
    }

    /**
     * @param ImportFace $face
     * @param DownloadResult $download
     * @return array<string, mixed>
     */
    private function buildProfileFace(
        string $familyName,
        string $familySlug,
        array $face,
        string $format,
        string $relativePath,
        array $download,
        string $sourceUrl,
        string $sourceHost
    ): array {
        $fontUrl = esc_url_raw(FontUtils::scalarStringValue($face['url'] ?? ''));
        $axes = FontUtils::normalizeAxesMap($face['axes'] ?? []);
        $variationDefaults = FontUtils::normalizeVariationDefaults($face['variation_defaults'] ?? [], $axes);

        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'source' => 'custom',
            'weight' => FontUtils::normalizeWeight(FontUtils::scalarStringValue($face['weight'] ?? '400')),
            'style' => FontUtils::normalizeStyle(FontUtils::scalarStringValue($face['style'] ?? 'normal')),
            'unicode_range' => sanitize_text_field(FontUtils::scalarStringValue($face['unicode_range'] ?? '')),
            'files' => [$format => $relativePath],
            'paths' => [$format => $relativePath],
            'provider' => [
                'type' => 'custom_css',
                'source_url' => $sourceUrl,
                'source_host' => $sourceHost,
                'original_url' => $fontUrl,
                'original_host' => $this->hostForUrl($fontUrl),
                'format' => $format,
                'sha256' => $download['sha256'],
                'size' => (string) $download['size'],
                'content_type' => $download['content_type'],
            ],
            'is_variable' => $axes !== [] || !empty($face['is_variable']),
            'axes' => $axes,
            'variation_defaults' => $variationDefaults,
        ];
    }

    /**
     * @param ImportFace $face
     * @param RevalidationResult $revalidation
     * @return array<string, mixed>
     */
    private function buildRemoteProfileFace(
        string $familyName,
        string $familySlug,
        array $face,
        string $format,
        string $fontUrl,
        array $revalidation,
        string $sourceUrl,
        string $sourceHost
    ): array {
        $axes = FontUtils::normalizeAxesMap($face['axes'] ?? []);
        $variationDefaults = FontUtils::normalizeVariationDefaults($face['variation_defaults'] ?? [], $axes);

        return [
            'family' => $familyName,
            'slug' => $familySlug,
            'source' => 'custom',
            'weight' => FontUtils::normalizeWeight(FontUtils::scalarStringValue($face['weight'] ?? '400')),
            'style' => FontUtils::normalizeStyle(FontUtils::scalarStringValue($face['style'] ?? 'normal')),
            'unicode_range' => sanitize_text_field(FontUtils::scalarStringValue($face['unicode_range'] ?? '')),
            'files' => [$format => $fontUrl],
            'paths' => [],
            'provider' => [
                'type' => 'custom_css',
                'source_url' => $sourceUrl,
                'source_host' => $sourceHost,
                'original_url' => $fontUrl,
                'original_host' => $this->hostForUrl($fontUrl),
                'remote_url' => $fontUrl,
                'remote_host' => $this->hostForUrl($fontUrl),
                'format' => $format,
                'content_type' => $revalidation['content_type'],
                'size' => (string) $revalidation['content_length'],
                'last_verified_at' => gmdate('c'),
                'validation_method' => $revalidation['method'],
            ],
            'is_variable' => $axes !== [] || !empty($face['is_variable']),
            'axes' => $axes,
            'variation_defaults' => $variationDefaults,
        ];
    }

    /**
     * @param list<array<string, mixed>> $profileFaces
     * @return array<string, mixed>
     */
    private function buildProfile(string $deliveryId, array $profileFaces, string $sourceUrl, string $sourceHost, string $deliveryMode): array
    {
        $date = gmdate('Y-m-d');
        $hostLabel = $sourceHost !== '' ? $sourceHost : __('custom source', 'tasty-fonts');
        $type = $deliveryMode === 'cdn' ? 'cdn' : 'self_hosted';
        $remote = $type === 'cdn';

        return [
            'id' => $deliveryId,
            'provider' => 'custom',
            'type' => $type,
            'format' => 'static',
            'label' => $remote
                ? sprintf(
                    /* translators: 1: source host, 2: import date. */
                    __('Remote custom CSS (%1$s, %2$s)', 'tasty-fonts'),
                    $hostLabel,
                    $date
                )
                : sprintf(
                    /* translators: 1: source host, 2: import date. */
                    __('Self-hosted custom CSS (%1$s, %2$s)', 'tasty-fonts'),
                    $hostLabel,
                    $date
                ),
            'variants' => HostedImportSupport::variantsFromFaces($profileFaces),
            'faces' => $profileFaces,
            'meta' => [
                'source_type' => 'custom_css_url',
                'source_css_url' => $sourceUrl,
                'source_host' => $sourceHost,
                'delivery_mode' => $remote ? 'remote' : 'self_hosted',
                'imported_at' => gmdate('c'),
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $existingFamily
     */
    private function resolvePublishState(?array $existingFamily, string $familySlug, bool $publish): string
    {
        $stored = $this->imports->getFamily($familySlug);
        $existingState = is_array($stored)
            ? FontUtils::scalarStringValue($stored['publish_state'])
            : (is_array($existingFamily) ? $this->profileStringValue($existingFamily, 'publish_state', 'published') : '');

        if ($publish && $existingState !== 'role_active') {
            return 'published';
        }

        if (in_array($existingState, ['library_only', 'published', 'role_active'], true)) {
            return $existingState;
        }

        return is_array($existingFamily) ? 'published' : 'library_only';
    }

    /**
     * @param array<string, mixed>|null $existingFamily
     */
    private function ensureExistingFamilyRecord(string $familyName, string $familySlug, ?array $existingFamily, string $publishState): void
    {
        if (!is_array($existingFamily) || $this->imports->getFamily($familySlug) !== null) {
            return;
        }

        $this->imports->ensureFamily(
            $familyName,
            $familySlug,
            $publishState,
            $this->profileStringValue($existingFamily, 'active_delivery_id'),
            $publishState !== 'role_active' ? $publishState : null
        );
    }

    /**
     * @param ImportFace $face
     * @return array{action: string, message: string}
     */
    private function resolveDuplicateResolution(string $familySlug, array $face, string $deliveryType, string $duplicateHandling): array
    {
        $matches = $this->duplicateMatchesForFace($familySlug, $face, $deliveryType);

        if ($matches === []) {
            return ['action' => 'import', 'message' => ''];
        }

        $replaceable = array_values(array_filter($matches, static fn (array $match): bool => !empty($match['replaceable'])));

        if ($duplicateHandling === 'replace_custom' && $replaceable !== []) {
            return ['action' => 'replace', 'message' => ''];
        }

        $hasProtected = count($replaceable) < count($matches);

        return [
            'action' => 'skip',
            'message' => $hasProtected && $duplicateHandling === 'replace_custom'
                ? __('A matching protected provider or local upload face already exists, so this duplicate was skipped instead of replaced.', 'tasty-fonts')
                : __('A matching font face already exists, so this duplicate was skipped by default.', 'tasty-fonts'),
        ];
    }

    /**
     * @param ImportFace $face
     * @return list<array<string, mixed>>
     */
    private function duplicateMatchesForFace(string $familySlug, array $face, string $deliveryType): array
    {
        $familySlug = FontUtils::slugify($familySlug);
        $incomingIdentity = $this->duplicateIdentityForFace($face, $deliveryType);
        $matches = [];

        if ($familySlug === '') {
            return [];
        }

        foreach ($this->imports->all() as $family) {
            $storedFamilySlug = FontUtils::slugify($this->profileStringValue($family, 'slug', $this->profileStringValue($family, 'family')));

            if ($storedFamilySlug !== $familySlug) {
                continue;
            }

            foreach ($family['delivery_profiles'] as $deliveryId => $profile) {
                if ($this->profileDeliveryType($profile) !== $deliveryType) {
                    continue;
                }

                foreach ($profile['faces'] as $existingFace) {
                    $format = $this->matchingFormat($face, $existingFace);

                    if ($format === '') {
                        continue;
                    }

                    if ($this->duplicateIdentityForFace($existingFace + ['format' => $format], $deliveryType) !== $incomingIdentity) {
                        continue;
                    }

                    $customCss = $this->isCustomCssProfile($profile, $existingFace);
                    $matches[] = [
                        'family_slug' => $storedFamilySlug,
                        'delivery_id' => sanitize_key((string) $deliveryId),
                        'provider' => $this->profileStringValue($profile, 'provider'),
                        'delivery_type' => $deliveryType,
                        'format' => $format,
                        'replaceable' => $customCss,
                        'protected' => !$customCss,
                    ];
                }
            }
        }

        return $matches;
    }

    /**
     * @param list<array<string, mixed>> $replacementFaces
     * @return array{faces_replaced: list<array<string, string>>, files_deleted: int}
     */
    private function replaceMatchingCustomFaces(string $familySlug, string $newDeliveryId, string $deliveryType, array $replacementFaces): array
    {
        $familySlug = FontUtils::slugify($familySlug);
        $library = $this->imports->all();
        $family = $library[$familySlug] ?? null;

        if ($family === null) {
            return ['faces_replaced' => [], 'files_deleted' => 0];
        }

        $profiles = $family['delivery_profiles'];
        $replacementIdentities = [];

        foreach ($replacementFaces as $replacementFace) {
            $replacementIdentities[] = $this->duplicateIdentityForFace($replacementFace, $deliveryType);
        }

        $removedPaths = [];
        $facesReplaced = [];
        $removedActiveDelivery = false;

        foreach ($profiles as $deliveryId => $profile) {
            $deliveryId = sanitize_key((string) $deliveryId);

            if ($deliveryId === $newDeliveryId) {
                continue;
            }

            if ($this->profileDeliveryType($profile) !== $deliveryType) {
                continue;
            }

            $remainingFaces = [];
            $removedFromProfile = false;

            foreach ($profile['faces'] as $existingFace) {
                if (!$this->isCustomCssProfile($profile, $existingFace)) {
                    $remainingFaces[] = $existingFace;
                    continue;
                }

                $matched = false;
                $matchedFormat = '';

                foreach ($replacementIdentities as $identity) {
                    $format = FontUtils::scalarStringValue($identity['format'] ?? '');

                    if ($format !== '' && $this->duplicateIdentityForFace($existingFace + ['format' => $format], $deliveryType) === $identity) {
                        $matched = true;
                        $matchedFormat = $format;
                        break;
                    }
                }

                if (!$matched) {
                    $remainingFaces[] = $existingFace;
                    continue;
                }

                $removedFromProfile = true;
                $removedPaths = array_merge($removedPaths, $this->localCustomPathsForFace($existingFace));
                $facesReplaced[] = [
                    'family_slug' => $familySlug,
                    'delivery_id' => $deliveryId,
                    'weight' => FontUtils::normalizeWeight(FontUtils::scalarStringValue($existingFace['weight'])),
                    'style' => FontUtils::normalizeStyle(FontUtils::scalarStringValue($existingFace['style'])),
                    'format' => $matchedFormat,
                ];
            }

            if (!$removedFromProfile) {
                continue;
            }

            if ($remainingFaces === []) {
                unset($profiles[$deliveryId]);

                if ($this->profileStringValue($family, 'active_delivery_id') === $deliveryId) {
                    $removedActiveDelivery = true;
                }

                continue;
            }

            $profile['faces'] = $remainingFaces;
            $profile['variants'] = HostedImportSupport::variantsFromFaces($profile['faces']);
            $profiles[$deliveryId] = $profile;
        }

        if ($facesReplaced === []) {
            return ['faces_replaced' => [], 'files_deleted' => 0];
        }

        $family['delivery_profiles'] = $profiles;

        if ($removedActiveDelivery && isset($profiles[$newDeliveryId])) {
            $family['active_delivery_id'] = $newDeliveryId;
        }

        if ($profiles === []) {
            unset($library[$familySlug]);
        } else {
            $library[$familySlug] = $family;
        }

        $this->imports->replaceLibrary($library);
        $deletedFiles = $this->deleteUnreferencedCustomFiles($removedPaths);

        return [
            'faces_replaced' => $facesReplaced,
            'files_deleted' => $deletedFiles,
        ];
    }

    /**
     * @param list<string> $relativePaths
     */
    private function deleteUnreferencedCustomFiles(array $relativePaths): int
    {
        $paths = [];

        foreach ($relativePaths as $relativePath) {
            $relativePath = $this->normalizeRelativeFontPath($relativePath);

            if ($relativePath !== '' && $this->relativePathIsWithinCustomRoot($relativePath)) {
                $paths[$relativePath] = true;
            }
        }

        if ($paths === []) {
            return 0;
        }

        $referencedPaths = $this->referencedRelativePaths();
        $deletePaths = array_values(array_filter(
            array_keys($paths),
            static fn (string $path): bool => !isset($referencedPaths[$path])
        ));

        if ($deletePaths === []) {
            return 0;
        }

        $this->storage->deleteRelativeFiles($deletePaths);

        return count(array_filter($deletePaths, fn (string $path): bool => !file_exists((string) $this->storage->pathForRelativePath($path))));
    }

    /**
     * @return array<string, true>
     */
    private function referencedRelativePaths(): array
    {
        $paths = [];

        foreach ($this->imports->all() as $family) {
            foreach ($family['delivery_profiles'] as $profile) {
                foreach ($profile['faces'] as $face) {
                    foreach ($this->localCustomPathsForFace($face) as $relativePath) {
                        $paths[$relativePath] = true;
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * @param array<string, mixed> $face
     * @return list<string>
     */
    private function localCustomPathsForFace(array $face): array
    {
        $paths = [];

        foreach ([FontUtils::normalizeStringKeyedMap($face['paths'] ?? null), FontUtils::normalizeStringKeyedMap($face['files'] ?? null)] as $map) {
            foreach ($map as $path) {
                $path = $this->normalizeRelativeFontPath(FontUtils::scalarStringValue($path));

                if ($path !== '' && !str_starts_with($path, 'http') && $this->relativePathIsWithinCustomRoot($path)) {
                    $paths[$path] = true;
                }
            }
        }

        return array_keys($paths);
    }

    /**
     * @param array<string, mixed> $incomingFace
     * @param array<string, mixed> $existingFace
     */
    private function matchingFormat(array $incomingFace, array $existingFace): string
    {
        $format = strtolower(FontUtils::scalarStringValue($incomingFace['format'] ?? ''));

        if ($format === '') {
            return '';
        }

        $files = FontUtils::normalizeStringKeyedMap($existingFace['files'] ?? null);
        $paths = FontUtils::normalizeStringKeyedMap($existingFace['paths'] ?? null);

        return array_key_exists($format, $files) || array_key_exists($format, $paths) ? $format : '';
    }

    /**
     * @param array<string, mixed> $face
     * @return array<string, mixed>
     */
    private function duplicateIdentityForFace(array $face, string $deliveryType): array
    {
        $format = strtolower(FontUtils::scalarStringValue($face['format'] ?? ''));

        if ($format === '') {
            $files = FontUtils::normalizeStringKeyedMap($face['files'] ?? null);
            $paths = FontUtils::normalizeStringKeyedMap($face['paths'] ?? null);
            $format = strtolower((string) (array_key_first($files) ?? array_key_first($paths) ?? ''));
        }

        return [
            'family_slug' => FontUtils::slugify(FontUtils::scalarStringValue($face['slug'] ?? $face['family'] ?? '')),
            'weight' => FontUtils::normalizeWeight(FontUtils::scalarStringValue($face['weight'] ?? '400')),
            'style' => FontUtils::normalizeStyle(FontUtils::scalarStringValue($face['style'] ?? 'normal')),
            'format' => $format,
            'delivery_type' => $deliveryType,
            'unicode_range' => $this->normalizeDuplicateUnicodeRange(FontUtils::scalarStringValue($face['unicode_range'] ?? '')),
            'axes' => $this->normalizeDuplicateAxes($face['axes'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $face
     */
    private function isCustomCssProfile(array $profile, array $face): bool
    {
        $faceProvider = FontUtils::normalizeStringKeyedMap($face['provider'] ?? null);
        $profileMeta = FontUtils::normalizeStringKeyedMap($profile['meta'] ?? null);

        return $this->profileStringValue($profile, 'provider') === 'custom'
            && FontUtils::scalarStringValue($face['source'] ?? '') === 'custom'
            && FontUtils::scalarStringValue($profileMeta['source_type'] ?? '') === 'custom_css_url'
            && FontUtils::scalarStringValue($faceProvider['type'] ?? '') === 'custom_css';
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function profileDeliveryType(array $profile): string
    {
        return match ($this->profileStringValue($profile, 'type')) {
            'self_hosted' => 'self_hosted',
            'cdn' => 'remote',
            default => '',
        };
    }

    private function normalizeDuplicateHandling(string $duplicateHandling): string
    {
        $duplicateHandling = strtolower(sanitize_key($duplicateHandling));

        return $duplicateHandling === 'replace_custom' ? 'replace_custom' : 'skip';
    }

    private function normalizeDuplicateUnicodeRange(string $unicodeRange): string
    {
        return preg_replace('/\s+/', '', strtoupper(trim($unicodeRange))) ?: '';
    }

    private function normalizeDuplicateAxes(mixed $axes): string
    {
        $normalized = FontUtils::normalizeAxesMap($axes);

        if ($normalized === []) {
            return '';
        }

        return (string) wp_json_encode($normalized);
    }

    private function normalizeRelativeFontPath(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }

    private function relativePathIsWithinCustomRoot(string $relativePath): bool
    {
        $absolutePath = $this->storage->pathForRelativePath($relativePath);
        $customRoot = $this->storage->getCustomRoot();

        return is_string($absolutePath)
            && is_string($customRoot)
            && str_starts_with(wp_normalize_path($absolutePath), trailingslashit(wp_normalize_path($customRoot)));
    }

    /**
     * @param list<array{family: string, id: string, url: string, message: string, code: string}> $skippedFaces
     * @param list<array{family: string, id: string, url: string, message: string, code: string}> $failedFaces
     * @return array<string, mixed>
     */
    private function skippedImportResult(string $deliveryMode, array $skippedFaces, array $failedFaces, int $writtenFileCount, int $deletedFileCount): array
    {
        $hasFailures = $failedFaces !== [];
        $status = $hasFailures ? 'partial' : 'skipped';
        $message = $hasFailures
            ? sprintf(
                /* translators: 1: number of skipped faces, 2: number of failed faces. */
                __('Skipped %1$d duplicate custom font face(s); %2$d selected face(s) failed.', 'tasty-fonts'),
                count($skippedFaces),
                count($failedFaces)
            )
            : sprintf(
                /* translators: %d is the number of duplicate faces skipped. */
                __('Skipped %d duplicate custom font face(s). No library data was changed.', 'tasty-fonts'),
                count($skippedFaces)
            );

        return [
            'status' => $status,
            'message' => $message,
            'delivery_mode' => $deliveryMode,
            'families' => [],
            'faces' => [
                'imported' => [],
                'skipped' => $skippedFaces,
                'replaced' => [],
                'failed' => $failedFaces,
            ],
            'counts' => [
                'families_imported' => 0,
                'faces_imported' => 0,
                'faces_skipped' => count($skippedFaces),
                'faces_replaced' => 0,
                'faces_failed' => count($failedFaces),
                'files_written' => $writtenFileCount,
                'old_files_deleted' => $deletedFileCount,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $face
     * @return array{family: string, id: string, url: string, message: string, code: string}
     */
    private function failedFacePayload(string $familyName, array $face, WP_Error $error): array
    {
        return [
            'family' => $familyName,
            'id' => sanitize_key(FontUtils::scalarStringValue($face['id'] ?? '')),
            'url' => esc_url_raw(FontUtils::scalarStringValue($face['url'] ?? '')),
            'message' => $error->get_error_message(),
            'code' => FontUtils::scalarStringValue($error->get_error_code()),
        ];
    }

    /**
     * @param ImportFace $face
     * @return array{family: string, id: string, url: string, message: string, code: string}
     */
    private function skippedFacePayload(string $familyName, array $face, string $message): array
    {
        return [
            'family' => $familyName,
            'id' => sanitize_key(FontUtils::scalarStringValue($face['id'] ?? '')),
            'url' => esc_url_raw(FontUtils::scalarStringValue($face['url'] ?? '')),
            'message' => $message,
            'code' => 'tasty_fonts_custom_css_duplicate_skipped',
        ];
    }

    private function hostForUrl(string $url): string
    {
        return strtolower((string) (wp_parse_url($url, PHP_URL_HOST) ?: ''));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function profileStringValue(array $values, string $key, string $default = ''): string
    {
        $value = FontUtils::scalarStringValue($values[$key] ?? '');

        return $value !== '' ? $value : $default;
    }

    private function storageErrorMessage(string $fallback): string
    {
        $message = trim($this->storage->getLastFilesystemErrorMessage());

        return $message !== '' ? $message : $fallback;
    }
}
