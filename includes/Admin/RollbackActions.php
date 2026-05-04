<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

use TastyFonts\Maintenance\SnapshotService;
use TastyFonts\Repository\ActivityLogVocabulary;
use WP_Error;

/**
 * @phpstan-type Payload array<array-key, mixed>
 */
final class RollbackActions
{
    use ActionValueHelpers;

    public function __construct(
        private readonly SnapshotService $snapshots,
        private readonly AdminActionRunner $runner
    ) {
    }

    /**
     * @return Payload|WP_Error
     */
    public function createSnapshot(string $reason = 'manual'): array|WP_Error
    {
        return $this->runner->run(
            function () use ($reason): array|WP_Error {
                $result = $this->snapshots->createSnapshot($reason);

                if (is_wp_error($result)) {
                    return $result;
                }

                return [
                    'snapshot' => $result['snapshot'],
                    'snapshots' => $this->snapshots->listSnapshots(),
                ];
            },
            [
                'category' => ActivityLogVocabulary::CATEGORY_TRANSFER,
                'event' => 'rollback_snapshot_created',
                'status_label' => __('Created', 'tasty-fonts'),
                'source' => __('Transfer', 'tasty-fonts'),
                'message' => __('Rollback snapshot created.', 'tasty-fonts'),
            ]
        );
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

        return $this->runner->run(
            function () use ($snapshotId, $preview): array|WP_Error {
                $result = $this->snapshots->restoreSnapshot($snapshotId);

                if (is_wp_error($result)) {
                    return $result;
                }

                return array_merge(
                    $result,
                    [
                        'preview' => $preview,
                        'snapshots' => $this->snapshots->listSnapshots(),
                    ]
                );
            },
            [
                'category' => ActivityLogVocabulary::CATEGORY_TRANSFER,
                'event' => 'rollback_snapshot_restored',
                'status_label' => __('Restored', 'tasty-fonts'),
                'source' => __('Transfer', 'tasty-fonts'),
                'message' => __('Rollback snapshot restored.', 'tasty-fonts'),
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function renameSnapshot(string $snapshotId, string $label): array|WP_Error
    {
        return $this->runner->run(
            function () use ($snapshotId, $label): array|WP_Error {
                $result = $this->snapshots->renameSnapshot($snapshotId, $label);

                if (is_wp_error($result)) {
                    return $result;
                }

                return [
                    'snapshot' => $result['snapshot'],
                    'snapshots' => $this->snapshots->listSnapshots(),
                ];
            },
            [
                'category' => ActivityLogVocabulary::CATEGORY_TRANSFER,
                'event' => 'rollback_snapshot_renamed',
                'status_label' => __('Renamed', 'tasty-fonts'),
                'source' => __('Transfer', 'tasty-fonts'),
                'message' => __('Rollback snapshot renamed.', 'tasty-fonts'),
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function deleteSnapshot(string $snapshotId): array|WP_Error
    {
        return $this->runner->run(
            function () use ($snapshotId): array|WP_Error {
                $result = $this->snapshots->deleteSnapshot($snapshotId);

                if (is_wp_error($result)) {
                    return $result;
                }

                return [
                    'snapshot' => $result['snapshot'],
                    'snapshots' => $this->snapshots->listSnapshots(),
                ];
            },
            [
                'category' => ActivityLogVocabulary::CATEGORY_TRANSFER,
                'event' => 'rollback_snapshot_deleted',
                'status_label' => __('Deleted', 'tasty-fonts'),
                'source' => __('Transfer', 'tasty-fonts'),
                'message' => __('Rollback snapshot deleted.', 'tasty-fonts'),
            ]
        );
    }

    /**
     * @return Payload|WP_Error
     */
    public function deleteAllSnapshots(): array|WP_Error
    {
        return $this->runner->run(
            function (): array {
                $result = $this->snapshots->deleteAllSnapshots();

                return [
                    'snapshots' => $this->snapshots->listSnapshots(),
                    'deleted_snapshots' => $this->intValue($result, 'deleted_snapshots'),
                    'deleted_snapshot_files' => $this->intValue($result, 'deleted_snapshot_files'),
                ];
            },
            [
                'category' => ActivityLogVocabulary::CATEGORY_TRANSFER,
                'event' => 'rollback_snapshots_deleted_all',
                'status_label' => __('Deleted', 'tasty-fonts'),
                'source' => __('Transfer', 'tasty-fonts'),
                'message' => __('All rollback snapshots deleted.', 'tasty-fonts'),
            ]
        );
    }
}
