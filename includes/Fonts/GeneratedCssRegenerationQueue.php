<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\TransientKey;

/**
 * Debounces and schedules generated CSS file regeneration work.
 */
final class GeneratedCssRegenerationQueue
{
    public function __construct(
        private readonly string $actionHook,
        private readonly string $transientKey,
        private readonly int $ttl
    ) {
    }

    /**
     * Queue a single background regeneration event within the debounce window.
     */
    public function queue(bool $logWriteResult = true): void
    {
        $siteTransientKey = TransientKey::forSite($this->transientKey);

        if (get_transient($siteTransientKey) !== false) {
            return;
        }

        set_transient(
            $siteTransientKey,
            ['log_write_result' => $logWriteResult ? 1 : 0],
            $this->ttl
        );

        $scheduled = wp_schedule_single_event(time(), $this->actionHook);

        if ($scheduled === false) {
            delete_transient($siteTransientKey);
        }
    }

    /**
     * Resolve the write-log option from queued state while preserving explicit caller arguments.
     */
    public function resolveLogWriteResult(bool $default, bool $callerOmittedArgument): bool
    {
        $siteTransientKey = TransientKey::forSite($this->transientKey);
        $queuedState = get_transient($siteTransientKey);
        $logWriteResult = $default;

        if ($callerOmittedArgument && is_array($queuedState) && array_key_exists('log_write_result', $queuedState)) {
            $logWriteResult = !empty($queuedState['log_write_result']);
        }

        delete_transient($siteTransientKey);

        return $logWriteResult;
    }
}
