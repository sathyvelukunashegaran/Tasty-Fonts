<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

/**
 * @phpstan-type AdobeProjectStatus array{state: string, message: string, checked_at: int}
 */
interface AdobeProjectRepositoryInterface
{
    public function isEnabled(): bool;

    public function getProjectId(): string;

    /**
     * @return AdobeProjectStatus
     */
    public function getStatus(): array;

    /**
     * @return array<string, mixed>
     */
    public function saveProject(string $projectId, bool $enabled): array;

    /**
     * @return AdobeProjectStatus
     */
    public function saveStatus(string $state, string $message = ''): array;

    /**
     * @return array<string, mixed>
     */
    public function clear(): array;
}
