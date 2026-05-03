<?php

declare(strict_types=1);

namespace TastyFonts\Integrations;

defined('ABSPATH') || exit;

interface EditorIntegrationInterface
{
    public function isAvailable(): bool;

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function readState(array $settings): array;

    /**
     * @return list<string>
     */
    public function getManagedEditorStyles(): array;

    /**
     * @return list<string>
     */
    public function getManagedFrontendStyles(): array;
}
