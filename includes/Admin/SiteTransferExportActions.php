<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

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
        private readonly LogRepository $log
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
        $result = $this->siteTransfer->renameExportBundle($exportId, $label);

        if (is_wp_error($result)) {
            return $result;
        }

        $message = $this->stringValue($result, 'message', __('Export bundle renamed.', 'tasty-fonts'));
        $this->log->add($message, $this->logContext(LogRepository::CATEGORY_TRANSFER, 'site_transfer_export_renamed'));

        return [
            'message' => $message,
            'export_bundles' => $this->siteTransfer->listExportBundles(),
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function setBundleProtected(string $exportId, bool $protected): array|WP_Error
    {
        $result = $this->siteTransfer->setExportBundleProtected($exportId, $protected);

        if (is_wp_error($result)) {
            return $result;
        }

        $message = $this->stringValue($result, 'message', $protected ? __('Export bundle protected.', 'tasty-fonts') : __('Export bundle unprotected.', 'tasty-fonts'));
        $this->log->add($message, $this->logContext(LogRepository::CATEGORY_TRANSFER, 'site_transfer_export_protection_changed'));

        return [
            'message' => $message,
            'export_bundles' => $this->siteTransfer->listExportBundles(),
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function deleteBundle(string $exportId): array|WP_Error
    {
        $result = $this->siteTransfer->deleteExportBundle($exportId);

        if (is_wp_error($result)) {
            return $result;
        }

        $message = $this->stringValue($result, 'message', __('Export bundle deleted.', 'tasty-fonts'));
        $this->log->add($message, $this->logContext(LogRepository::CATEGORY_TRANSFER, 'site_transfer_export_deleted'));

        return [
            'message' => $message,
            'export_bundles' => $this->siteTransfer->listExportBundles(),
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function deleteAllBundles(): array|WP_Error
    {
        $result = $this->siteTransfer->deleteAllExportBundlesUnlessProtected();

        if (is_wp_error($result)) {
            return $result;
        }

        $message = __('All site transfer export bundles deleted.', 'tasty-fonts');
        $this->log->add($message, $this->logContext(LogRepository::CATEGORY_TRANSFER, 'site_transfer_exports_deleted_all'));

        return [
            'message' => $message,
            'export_bundles' => $this->siteTransfer->listExportBundles(),
            'deleted_export_bundles' => $this->intValue($result, 'deleted_export_bundles'),
            'deleted_export_files' => $this->intValue($result, 'deleted_export_files'),
        ];
    }

}
