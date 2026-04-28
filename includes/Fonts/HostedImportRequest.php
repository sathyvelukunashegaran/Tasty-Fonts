<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

/**
 * Immutable request passed from a provider adapter into the hosted import workflow.
 */
final class HostedImportRequest
{
    /**
     * @param list<string> $variants
     */
    public function __construct(
        public readonly string $familyName,
        public readonly array $variants,
        public readonly string $deliveryMode = 'self_hosted',
        public readonly string $formatMode = 'static'
    ) {}
}
