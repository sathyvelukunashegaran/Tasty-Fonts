<?php

declare(strict_types=1);

function assertThrows(callable $callback, string $expectedClass, string $message = ''): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        if ($throwable instanceof $expectedClass) {
            return;
        }

        throw new RuntimeException($message !== '' ? $message : sprintf('Expected %s, got %s.', $expectedClass, $throwable::class));
    }

    throw new RuntimeException($message !== '' ? $message : sprintf('Expected %s to be thrown.', $expectedClass));
}

/**
 * @param array<string, callable> $tests
 */
function runTestSuite(array $tests): never
{
    $failures = 0;

    foreach ($tests as $name => $test) {
        try {
            $test();
            echo "[PASS] {$name}\n";
        } catch (Throwable $throwable) {
            $failures++;
            echo "[FAIL] {$name}\n";
            echo $throwable->getMessage() . "\n";
        }
    }

    exit($failures > 0 ? 1 : 0);
}
