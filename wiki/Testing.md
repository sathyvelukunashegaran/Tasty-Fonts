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

PHP test cases live in `tests/cases/*.php`. Each case file registers one or more closures on the shared `$tests` array, then `tests/run.php` loads every case file and passes that array into `runTestSuite( $tests )`.

**Basic structure:**

```php
<?php

declare(strict_types=1);

namespace {
    use TastyFonts\Support\FontUtils;

    $tests['font_utils_normalizes_font_weight_tokens'] = static function (): void {
        assertSameValue(
            '700',
            FontUtils::normalizeWeight('bold'),
            'FontUtils should normalize named weight aliases to their stored numeric token.'
        );
    };
}
```

**Available harness helpers:**

| Helper | What it does |
|---|---|
| `assertSameValue( $expected, $actual, $message )` | Fails if values are not strictly identical |
| `assertTrueValue( $actual, $message )` | Fails if the value is not `true` |
| `assertFalseValue( $actual, $message )` | Fails if the value is not `false` |
| `assertContainsValue( $needle, $haystack, $message )` | Fails if a string does not contain the expected substring |
| `assertNotContainsValue( $needle, $haystack, $message )` | Fails if a string contains an unexpected substring |
| `assertArrayHasKeys( $expectedKeys, $actual, $message )` | Fails if an array is missing required keys |
| `assertWpErrorCode( $expectedCode, $actual, $message )` | Fails unless the value is a `WP_Error` with the expected error code |
| `assertMatchesPattern( $pattern, $subject, $message )` | Fails unless the subject matches the regex pattern |
| `resetTestState()` | Clears global and option-like state between tests; call this before a test that touches stored state |

Helpers are defined in `tests/bootstrap.php` and `tests/support/wordpress-harness.php`.

**Example with state reset:**

```php
<?php

declare(strict_types=1);

namespace {
    $tests['settings_repository_persists_css_delivery_updates'] = static function (): void {
        resetTestState();

        update_option('tasty_fonts_settings', ['css_delivery' => 'inline']);

        assertSameValue(
            'inline',
            get_option('tasty_fonts_settings')['css_delivery'] ?? '',
            'Settings updates should persist the selected CSS delivery mode.'
        );
    };
}
```

**Adding the file to the runner:**

If you add a new `tests/cases/` file, include it at the bottom of `tests/run.php`:

```php
require_once __DIR__ . '/cases/my-feature.php';
```

**Useful harness utilities from the support layer:**

- `makeServiceGraph()` builds a wired service container for integration-style tests.
- `makeAdminControllerTestInstance()` instantiates `AdminController` without running its constructor.
- `invokePrivateMethod()` helps target private methods in focused unit tests.
- `resetPluginSingleton()` is available when you need to test the `Plugin` boot lifecycle cleanly.

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

- [Architecture](Architecture)
- [Release Process](Release-Process)
