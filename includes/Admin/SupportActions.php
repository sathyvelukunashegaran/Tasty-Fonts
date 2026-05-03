<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

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
        private readonly AdminActionRunner $runner
    ) {
    }

    /**
     * @param Payload $advancedToolsPayload
     * @return Payload|WP_Error
     */
    public function buildBundle(array $advancedToolsPayload): array|WP_Error
    {
        return $this->runner->run(
            function () use ($advancedToolsPayload): array {
                $bundle = $this->supportBundles->buildBundle($advancedToolsPayload);

                return ['bundle' => $bundle];
            },
            [
                'category' => LogRepository::CATEGORY_MAINTENANCE,
                'event' => 'support_bundle_created',
                'status_label' => __('Created', 'tasty-fonts'),
                'source' => __('Support', 'tasty-fonts'),
                'message' => __('Support bundle created.', 'tasty-fonts'),
            ]
        );
    }
}
