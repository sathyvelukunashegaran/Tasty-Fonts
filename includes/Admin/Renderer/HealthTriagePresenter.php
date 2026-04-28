<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

/**
 * Maps raw health severities into user-centered Advanced Tools triage buckets.
 *
 * @phpstan-type HealthTriageCheck array<mixed>
 * @phpstan-type HealthTriageGroup array{
 *     slug: string,
 *     title: string,
 *     checks: list<HealthTriageCheck>,
 *     summary: string,
 *     expanded: bool
 * }
 */
final class HealthTriagePresenter
{
    /**
     * @param list<HealthTriageCheck> $healthChecks
     * @return list<HealthTriageGroup>
     */
    public function groups(array $healthChecks): array
    {
        $actionNeeded = [];
        $worthReviewing = [];
        $checksPassed = [];

        foreach ($healthChecks as $healthCheck) {
            $severityValue = $healthCheck['severity'] ?? 'ok';
            $severity = is_scalar($severityValue) ? (string) $severityValue : 'ok';

            if ($severity === 'critical' || $severity === 'warning') {
                $actionNeeded[] = $healthCheck;

                continue;
            }

            if ($severity === 'info') {
                $worthReviewing[] = $healthCheck;

                continue;
            }

            $checksPassed[] = $healthCheck;
        }

        $hasActionNeeded = $actionNeeded !== [];

        return [
            [
                'slug' => 'action-needed',
                'title' => __('Action needed', 'tasty-fonts'),
                'checks' => $actionNeeded,
                'summary' => $this->countSummary($actionNeeded),
                'expanded' => $hasActionNeeded,
            ],
            [
                'slug' => 'worth-reviewing',
                'title' => __('Worth reviewing', 'tasty-fonts'),
                'checks' => $worthReviewing,
                'summary' => $this->countSummary($worthReviewing),
                'expanded' => !$hasActionNeeded && $worthReviewing !== [],
            ],
            [
                'slug' => 'checks-passed',
                'title' => __('Checks passed', 'tasty-fonts'),
                'checks' => $checksPassed,
                'summary' => $this->countSummary($checksPassed),
                'expanded' => false,
            ],
        ];
    }

    /**
     * @param list<HealthTriageCheck> $checks
     */
    private function countSummary(array $checks): string
    {
        $count = count($checks);

        return sprintf(
            /* translators: %d: number of health checks */
            _n('%d check', '%d checks', $count, 'tasty-fonts'),
            $count
        );
    }
}
