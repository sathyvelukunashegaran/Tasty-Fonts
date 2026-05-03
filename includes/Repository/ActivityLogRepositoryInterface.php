<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

/**
 * @phpstan-type LogContext array<string, mixed>
 * @phpstan-type LogEntry array<string, string>
 * @phpstan-type LogEntryList list<LogEntry>
 */
interface ActivityLogRepositoryInterface
{
    /**
     * @param LogContext $context
     */
    public function add(string $message, array $context = []): void;

    /**
     * @return LogEntryList
     */
    public function all(): array;

    public function clear(): void;
}
