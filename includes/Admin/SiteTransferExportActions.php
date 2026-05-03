<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

use TastyFonts\Maintenance\SiteTransferService;
use TastyFonts\Repository\LogRepository;
use WP_Error;

/**
 * @phpstan-type Payload array<string, mixed>
 */
final class SiteTransferExportActions
{
    use ActionValueHelpers;

    public function __construct(
        private readonly SiteTransferService $siteTransfer,
        private readonly AdminActionRunner $runner
    ) {
    }

    /**
     * @return Payload|WP_Error
     */
    public function exportBundle(): array|WP_Error
    {
        return $this->siteTransfer->buildExportBundle(true);
    }

    /**
     * @return Payload|WP_Error
     */
    public function renameBundle(string $exportId, string $label): array|WP_Error
    {
        return $this->runner->run(
            function () use ($exportId, $label): array|WP_Error {
                $result = $this->siteTransfer->renameExportBundle($exportId, $label);

                if (is_wp_error($result)) {
                    return $result;
                }

                return [
                    'export_bundles' => $this->siteTransfer->listExportBundles(),
                ];
            },
            [
                'category' => LogRepository::CATEGORY_TRANSFER,
                'event' => 'site_transfer_export_renamed',
                'status_label' => __('Renamed', 'tasty-fonts'),
                'source' => __('Transfer', 'tasty-fonts'),
                'message' => __('Export bundle renamed.', 'tasty-fonts'),
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function setBundleProtected(string $exportId, bool $protected): array|WP_Error
    {
        return $this->runner->run(
            function () use ($exportId, $protected): array|WP_Error {
                $result = $this->siteTransfer->setExportBundleProtected($exportId, $protected);

                if (is_wp_error($result)) {
                    return $result;
                }

                return [
                    'export_bundles' => $this->siteTransfer->listExportBundles(),
                ];
            },
            [
                'category' => LogRepository::CATEGORY_TRANSFER,
                'event' => 'site_transfer_export_protection_changed',
                'status_label' => $protected ? __('Protected', 'tasty-fonts') : __('Unprotected', 'tasty-fonts'),
                'source' => __('Transfer', 'tasty-fonts'),
                'message' => $protected ? __('Export bundle protected.', 'tasty-fonts') : __('Export bundle unprotected.', 'tasty-fonts'),
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function deleteBundle(string $exportId): array|WP_Error
    {
        return $this->runner->run(
            function () use ($exportId): array|WP_Error {
                $result = $this->siteTransfer->deleteExportBundle($exportId);

                if (is_wp_error($result)) {
                    return $result;
                }

                return [
                    'export_bundles' => $this->siteTransfer->listExportBundles(),
                ];
            },
            [
                'category' => LogRepository::CATEGORY_TRANSFER,
                'event' => 'site_transfer_export_deleted',
                'status_label' => __('Deleted', 'tasty-fonts'),
                'source' => __('Transfer', 'tasty-fonts'),
                'message' => __('Export bundle deleted.', 'tasty-fonts'),
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function deleteAllBundles(): array|WP_Error
    {
        return $this->runner->run(
            function (): array|WP_Error {
                $result = $this->siteTransfer->deleteAllExportBundlesUnlessProtected();

                if (is_wp_error($result)) {
                    return $result;
                }

                return [
                    'export_bundles' => $this->siteTransfer->listExportBundles(),
                    'deleted_export_bundles' => $this->intValue($result, 'deleted_export_bundles'),
                    'deleted_export_files' => $this->intValue($result, 'deleted_export_files'),
                ];
            },
            [
                'category' => LogRepository::CATEGORY_TRANSFER,
                'event' => 'site_transfer_exports_deleted_all',
                'status_label' => __('Deleted', 'tasty-fonts'),
                'source' => __('Transfer', 'tasty-fonts'),
                'message' => __('All site transfer export bundles deleted.', 'tasty-fonts'),
            ]
        );
    }
}
