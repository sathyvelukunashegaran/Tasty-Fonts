<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

interface ProviderStylesheetResolverInterface
{
    public function supports(string $provider, string $type): bool;

    /**
     * @param array<string, mixed> $delivery
     * @return array{handle: string, url: string, provider: string, type: string}|null
     */
    public function buildStylesheetDescriptor(array $delivery, string $familyName, string $familySlug, string $displayOverride): ?array;

    public function preconnectOrigin(): ?string;

    public function getProviderKey(): string;
}
