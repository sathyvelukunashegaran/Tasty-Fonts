<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Maintenance\SnapshotService;
use TastyFonts\Repository\LogRepository;
use WP_Error;

/**
 * @phpstan-type Payload array<string, mixed>
 */
final class RollbackActions
{
    use ActionValueHelpers;

    public function __construct(
        private readonly SnapshotService $snapshots,
        private readonly LogRepository $log
    ) {
    }

    /**
     * @return Payload|WP_Error
     */
    public function createSnapshot(string $reason = 'manual'): array|WP_Error
    {
        $result = $this->snapshots->createSnapshot($reason);

        if (is_wp_error($result)) {
            return $result;
        }

        $snapshot = $result['snapshot'];
        $message = $this->stringValue($result, 'message', __('Rollback snapshot created.', 'tasty-fonts'));
        $this->log->add($message, $this->logContext(LogRepository::CATEGORY_TRANSFER, 'rollback_snapshot_created'));

        return [
            'message' => $message,
            'snapshot' => $snapshot,
            'snapshots' => $this->snapshots->listSnapshots(),
        ];
    }

    /**
     * @return Payload
     */
    public function listSnapshots(): array
    {
        return ['snapshots' => $this->snapshots->listSnapshots()];
    }

    /**
     * @return Payload|WP_Error
     */
    public function restoreSnapshot(string $snapshotId): array|WP_Error
    {
        $preview = $this->snapshots->previewRestore($snapshotId);

        if (is_wp_error($preview)) {
            return $preview;
        }

        $safetySnapshot = $this->snapshots->createSnapshot('before_snapshot_restore');

        if (is_wp_error($safetySnapshot)) {
            return $safetySnapshot;
        }

        $result = $this->snapshots->restoreSnapshot($snapshotId);

        if (is_wp_error($result)) {
            return $result;
        }

        $message = $this->stringValue($result, 'message', __('Rollback snapshot restored.', 'tasty-fonts'));
        $this->log->add($message, $this->logContext(LogRepository::CATEGORY_TRANSFER, 'rollback_snapshot_restored'));

        return array_merge(
            $result,
            [
                'message' => $message,
                'preview' => $preview,
                'snapshots' => $this->snapshots->listSnapshots(),
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function renameSnapshot(string $snapshotId, string $label): array|WP_Error
    {
        $result = $this->snapshots->renameSnapshot($snapshotId, $label);

        if (is_wp_error($result)) {
            return $result;
        }

        $message = $this->stringValue($result, 'message', __('Rollback snapshot renamed.', 'tasty-fonts'));
        $this->log->add($message, $this->logContext(LogRepository::CATEGORY_TRANSFER, 'rollback_snapshot_renamed'));

        return [
            'message' => $message,
            'snapshot' => $result['snapshot'],
            'snapshots' => $this->snapshots->listSnapshots(),
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function deleteSnapshot(string $snapshotId): array|WP_Error
    {
        $result = $this->snapshots->deleteSnapshot($snapshotId);

        if (is_wp_error($result)) {
            return $result;
        }

        $message = $this->stringValue($result, 'message', __('Rollback snapshot deleted.', 'tasty-fonts'));
        $this->log->add($message, $this->logContext(LogRepository::CATEGORY_TRANSFER, 'rollback_snapshot_deleted'));

        return [
            'message' => $message,
            'snapshot' => $result['snapshot'],
            'snapshots' => $this->snapshots->listSnapshots(),
        ];
    }

    /**
     * @return Payload
     */
    public function deleteAllSnapshots(): array
    {
        $result = $this->snapshots->deleteAllSnapshots();
        $message = __('All rollback snapshots deleted.', 'tasty-fonts');
        $this->log->add($message, $this->logContext(LogRepository::CATEGORY_TRANSFER, 'rollback_snapshots_deleted_all'));

        return [
            'message' => $message,
            'snapshots' => $this->snapshots->listSnapshots(),
            'deleted_snapshots' => $this->intValue($result, 'deleted_snapshots'),
            'deleted_snapshot_files' => $this->intValue($result, 'deleted_snapshot_files'),
        ];
    }

}
