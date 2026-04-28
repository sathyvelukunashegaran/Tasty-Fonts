<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Maintenance\SupportBundleService;
use TastyFonts\Repository\LogRepository;
use WP_Error;

/**
 * @phpstan-type Payload array<string, mixed>
 */
final class SupportActions
{
    public function __construct(
        private readonly SupportBundleService $supportBundles,
        private readonly LogRepository $log
    ) {
    }

    /**
     * @param Payload $advancedToolsPayload
     * @return Payload|WP_Error
     */
    public function buildBundle(array $advancedToolsPayload): array|WP_Error
    {
        $bundle = $this->supportBundles->buildBundle($advancedToolsPayload);

        if (is_wp_error($bundle)) {
            return $bundle;
        }

        $message = __('Support bundle created.', 'tasty-fonts');
        $this->log->add($message, $this->activityLogContext(
            LogRepository::CATEGORY_MAINTENANCE,
            'support_bundle_created',
            [
                'outcome' => 'success',
                'status_label' => __('Created', 'tasty-fonts'),
                'source' => __('Support', 'tasty-fonts'),
            ]
        ));

        return [
            'message' => $message,
            'bundle' => $bundle,
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function activityLogContext(string $category, string $event, array $meta = []): array
    {
        return array_merge(
            [
                'category' => $category,
                'event' => $event,
            ],
            $meta
        );
    }
}
