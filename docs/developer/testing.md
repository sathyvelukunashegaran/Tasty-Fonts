# Testing

Run and extend the local test suite for the plugin.

## Use This Page When

- you are changing plugin behavior
- you need the standard local verification commands
- you want to add PHP or JavaScript coverage

---

## Running The Tests

### 1. Run The PHP Test Suite

```bash
php tests/run.php
```

This is the main local PHP harness. It does not require PHPUnit, Composer, or external services.

Each test prints `[PASS] <name>` on success. On failure it prints `[FAIL] <name>` followed by the assertion message.

### 2. Run The JavaScript Contract Tests

```bash
node --test tests/js/*.test.cjs
```

These tests cover shared browser/Node-compatible admin and canvas helper contracts.

### 3. Run The PHP Syntax Sweep

```bash
find . -name '*.php' -not -path './output/*' -print0 | xargs -0 -n1 php -l
```

This catches syntax errors across every PHP file in the repository. Run this as a quick sanity check before opening a pull request.

### 4. Run All Checks Together

```bash
find . -name '*.php' -not -path './output/*' -print0 | xargs -0 -n1 php -l && php tests/run.php && node --test tests/js/*.test.cjs
```

This is the same verification sequence the release helper runs before tagging.

---

## Adding Tests

### PHP Tests

PHP test cases live in `tests/cases/*.php`. Each file contains one or more test closures.

**Basic structure:**

```php
<?php
// tests/cases/my-feature.php

use function TastyFonts\Tests\run_test;
use function TastyFonts\Tests\assert_equals;

run_test( 'my feature returns expected value', function () {
    $result = some_function_under_test();
    assert_equals( 'expected', $result );
} );
```

**Available harness helpers:**

| Helper | What it does |
|---|---|
| `run_test( $name, $fn )` | Registers and runs a named test closure |
| `assert_equals( $expected, $actual )` | Fails if values are not equal |
| `assert_true( $value )` | Fails if value is not truthy |
| `assert_false( $value )` | Fails if value is not falsy |
| `assert_contains( $needle, $haystack )` | Fails if needle is not in haystack |
| `resetTestState()` | Clears global and option-like state between tests; call this when your test touches stored state |

Helpers are defined in `tests/bootstrap.php` and `tests/support/wordpress-harness.php`.

**Example with state reset:**

```php
run_test( 'settings save persists correctly', function () {
    resetTestState();

    update_tasty_option( 'css_delivery', 'inline' );
    $settings = get_tasty_settings();

    assert_equals( 'inline', $settings['css_delivery'] );

    resetTestState();
} );
```

**Adding the file to the runner:**

If you add a new `tests/cases/` file, include it at the bottom of `tests/run.php`:

```php
require_once __DIR__ . '/cases/my-feature.php';
```

### JavaScript Tests

JavaScript tests live in `tests/js/*.test.cjs`. They use Node's built-in `node:test` module and `node:assert`.

**Basic structure:**

```javascript
// tests/js/my-feature.test.cjs
'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');

test('my feature returns expected value', () => {
    const result = myFunction();
    assert.strictEqual(result, 'expected');
});
```

The test runner discovers all `*.test.cjs` files automatically via the glob pattern `tests/js/*.test.cjs`.

---

## Notes

- There is no Composer install step and no npm install step for the current repo workflow.
- The shared release quality workflow runs the PHP syntax sweep, the PHP suite, and the JS contract tests before any stable, beta, or nightly package is published.

## Related Docs

- [Architecture](architecture.md)
- [Release Process](release-process.md)
