<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

use TastyFonts\Repository\LogRepository;
use WP_Error;

/**
 * @phpstan-type RunnerContext array{
 *     category: string,
 *     event: string,
 *     status_label: string,
 *     source: string,
 *     outcome?: string,
 *     details?: list<array<string, mixed>>,
 *     meta?: array<string, mixed>,
 *     message?: string
 * }
 * @phpstan-type Payload array<string, mixed>
 */
final class AdminActionRunner
{
    public function __construct(
        private readonly LogRepository $log
    ) {
    }

    /**
     * @param RunnerContext $context
     * @return Payload|WP_Error
     */
    public function run(callable $action, array $context): array|WP_Error
    {
        $result = $action();

        if ($result instanceof WP_Error) {
            $this->logError($result, $context);

            return $result;
        }

        $message = $context['message'] ?? $this->defaultMessage($context);

        $this->logSuccess($message, $context);

        $resultArray = [];

        if (is_array($result)) {
            foreach ($result as $key => $value) {
                if (is_string($key)) {
                    $resultArray[$key] = $value;
                }
            }
        }

        return ['message' => $message] + $resultArray;
    }

    /**
     * @param RunnerContext $context
     */
    private function logError(WP_Error $error, array $context): void
    {
        $this->log->add(
            $error->get_error_message(),
            $this->buildLogContext($context, 'error')
        );
    }

    /**
     * @param RunnerContext $context
     */
    private function logSuccess(string $message, array $context): void
    {
        $this->log->add(
            $message,
            $this->buildLogContext($context, $context['outcome'] ?? 'success')
        );
    }

    /**
     * @param RunnerContext $context
     * @return array<string, mixed>
     */
    private function buildLogContext(array $context, string $outcome): array
    {
        $logContext = [
            'category' => $context['category'],
            'event' => $context['event'],
            'outcome' => $outcome,
            'status_label' => $context['status_label'],
            'source' => $context['source'],
        ];

        if (!empty($context['details'])) {
            $logContext['details'] = $context['details'];
        }

        if (!empty($context['meta'])) {
            $logContext = array_merge($logContext, $context['meta']);
        }

        return $logContext;
    }

    /**
     * @param RunnerContext $context
     */
    private function defaultMessage(array $context): string
    {
        $event = str_replace('_', ' ', $context['event']);

        return sprintf(
            /* translators: %s: event identifier */
            __('Operation completed: %s.', 'tasty-fonts'),
            ucfirst($event)
        );
    }
}
