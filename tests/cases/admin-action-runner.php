<?php

declare(strict_types=1);

use TastyFonts\Admin\AdminActionRunner;
use TastyFonts\Repository\LogRepository;

$tests['admin_action_runner_successful_callable_returns_payload_with_message'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $runner = new AdminActionRunner($log);

    $result = $runner->run(
        fn(): array => ['key' => 'value'],
        [
            'category' => LogRepository::CATEGORY_MAINTENANCE,
            'event' => 'test_event',
            'status_label' => 'Test',
            'source' => 'Test',
            'message' => 'Custom success message.',
        ]
    );

    assertSameValue(['message' => 'Custom success message.', 'key' => 'value'], $result, 'Successful callable should return payload with message merged.');
};

$tests['admin_action_runner_successful_callable_logs_success'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $runner = new AdminActionRunner($log);

    $runner->run(
        fn(): array => ['key' => 'value'],
        [
            'category' => LogRepository::CATEGORY_MAINTENANCE,
            'event' => 'test_event',
            'status_label' => 'Test',
            'source' => 'Test',
            'message' => 'Custom success message.',
        ]
    );

    $entries = $log->all();
    assertTrueValue(count($entries) === 1, 'Successful callable should create exactly one log entry.');
    assertSameValue('success', $entries[0]['outcome'] ?? '', 'Log entry should have outcome success.');
    assertSameValue('test_event', $entries[0]['event'] ?? '', 'Log entry should have correct event.');
    assertSameValue('maintenance', $entries[0]['category'] ?? '', 'Log entry should have correct category.');
    assertSameValue('Test', $entries[0]['status_label'] ?? '', 'Log entry should have correct status_label.');
    assertSameValue('Test', $entries[0]['source'] ?? '', 'Log entry should have correct source.');
    assertSameValue('Custom success message.', $entries[0]['message'] ?? '', 'Log entry should have correct message.');
};

$tests['admin_action_runner_wp_error_passes_through_and_logs_error'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $runner = new AdminActionRunner($log);
    $error = new WP_Error('test_error', 'Something went wrong.');

    $result = $runner->run(
        fn() => $error,
        [
            'category' => LogRepository::CATEGORY_SETTINGS,
            'event' => 'test_error_event',
            'status_label' => 'Failed',
            'source' => 'Test',
        ]
    );

    assertSameValue($error, $result, 'WP_Error should be passed through unchanged.');

    $entries = $log->all();
    assertTrueValue(count($entries) === 1, 'Failed callable should create exactly one log entry.');
    assertSameValue('error', $entries[0]['outcome'] ?? '', 'Log entry should have outcome error.');
    assertSameValue('test_error_event', $entries[0]['event'] ?? '', 'Log entry should have correct event.');
    assertSameValue('Something went wrong.', $entries[0]['message'] ?? '', 'Log entry message should be the WP_Error message.');
};

$tests['admin_action_runner_void_callable_uses_default_message'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $runner = new AdminActionRunner($log);

    $result = $runner->run(
        function (): void {
        },
        [
            'category' => LogRepository::CATEGORY_MAINTENANCE,
            'event' => 'my_custom_event',
            'status_label' => 'Done',
            'source' => 'Test',
        ]
    );

    assertSameValue(['message' => 'Operation completed: My custom event.'], $result, 'Void callable should use default message built from event.');

    $entries = $log->all();
    assertSameValue('Operation completed: My custom event.', $entries[0]['message'] ?? '', 'Log entry should use default message.');
};

$tests['admin_action_runner_explicit_message_overrides_default'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $runner = new AdminActionRunner($log);

    $result = $runner->run(
        fn(): array => [],
        [
            'category' => LogRepository::CATEGORY_MAINTENANCE,
            'event' => 'some_event',
            'status_label' => 'Done',
            'source' => 'Test',
            'message' => 'Explicit message here.',
        ]
    );

    assertSameValue(['message' => 'Explicit message here.'], $result, 'Explicit message should override default.');
};

$tests['admin_action_runner_includes_details_and_meta_in_log'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $runner = new AdminActionRunner($log);

    $runner->run(
        fn(): array => [],
        [
            'category' => LogRepository::CATEGORY_MAINTENANCE,
            'event' => 'test_event',
            'status_label' => 'Test',
            'source' => 'Test',
            'message' => 'Test message.',
            'details' => [
                ['label' => 'Key', 'value' => 'Value'],
            ],
            'meta' => [
                'summary' => 'Custom summary value',
            ],
        ]
    );

    $entries = $log->all();
    $detailsJson = $entries[0]['details_json'] ?? '';
    assertContainsValue('Key', $detailsJson, 'Log details should include label.');
    assertContainsValue('Value', $detailsJson, 'Log details should include value.');
    assertSameValue('Custom summary value', $entries[0]['summary'] ?? '', 'Log entry should include custom meta.');
};

$tests['admin_action_runner_preserves_callable_return_values'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $runner = new AdminActionRunner($log);

    $result = $runner->run(
        fn(): array => ['a' => 1, 'b' => 2],
        [
            'category' => LogRepository::CATEGORY_MAINTENANCE,
            'event' => 'test_event',
            'status_label' => 'Test',
            'source' => 'Test',
            'message' => 'Test.',
        ]
    );

    assertArrayHasKeys(['message', 'a', 'b'], $result, 'Result should preserve all callable return values plus message.');
    assertSameValue(1, $result['a'], 'Return value a should be preserved.');
    assertSameValue(2, $result['b'], 'Return value b should be preserved.');
};

$tests['admin_action_runner_uses_custom_outcome'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $runner = new AdminActionRunner($log);

    $runner->run(
        fn(): array => [],
        [
            'category' => LogRepository::CATEGORY_MAINTENANCE,
            'event' => 'test_event',
            'status_label' => 'Test',
            'source' => 'Test',
            'message' => 'Test.',
            'outcome' => 'danger',
        ]
    );

    $entries = $log->all();
    assertSameValue('danger', $entries[0]['outcome'] ?? '', 'Custom outcome should be used for successful calls.');
};

$tests['admin_action_runner_message_wins_over_result_message'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $runner = new AdminActionRunner($log);

    $result = $runner->run(
        fn(): array => ['message' => 'Result message.'],
        [
            'category' => LogRepository::CATEGORY_MAINTENANCE,
            'event' => 'test_event',
            'status_label' => 'Test',
            'source' => 'Test',
            'message' => 'Runner message.',
        ]
    );

    assertSameValue('Runner message.', $result['message'] ?? '', 'Runner message should win over result message.');
};

$tests['admin_action_runner_error_uses_context_message_when_provided'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $runner = new AdminActionRunner($log);
    $error = new WP_Error('test_error', 'Original WP_Error message.');

    $result = $runner->run(
        fn() => $error,
        [
            'category' => LogRepository::CATEGORY_SETTINGS,
            'event' => 'google_api_key_validation_failed',
            'status_label' => 'Validation failed',
            'source' => 'Settings',
            'message' => 'Google Fonts API key validation failed.',
        ]
    );

    assertSameValue($error, $result, 'WP_Error should still be returned unchanged when context message is provided.');

    $entries = $log->all();
    assertSameValue('Google Fonts API key validation failed.', $entries[0]['message'] ?? '', 'Error log should preserve the explicit context message when provided.');
};

$tests['admin_action_runner_preserves_numeric_top_level_result_keys'] = static function (): void {
    resetTestState();

    $log = new LogRepository();
    $runner = new AdminActionRunner($log);

    $result = $runner->run(
        fn(): array => [0 => 'zero', 'alpha' => 'a', 5 => 'five'],
        [
            'category' => LogRepository::CATEGORY_MAINTENANCE,
            'event' => 'test_event',
            'status_label' => 'Test',
            'source' => 'Test',
            'message' => 'Runner message.',
        ]
    );

    assertSameValue(
        ['message' => 'Runner message.', 0 => 'zero', 'alpha' => 'a', 5 => 'five'],
        $result,
        'Array payloads should preserve numeric and string top-level keys using the legacy union behavior.'
    );
};
