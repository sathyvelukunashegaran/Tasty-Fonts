<?php

declare(strict_types=1);

require_once __DIR__ . '/support/wordpress-harness.php';
require_once __DIR__ . '/support/test-runner.php';
require_once __DIR__ . '/cases/core-coverage.php';
require_once __DIR__ . '/cases/font-foundation.php';
require_once __DIR__ . '/cases/provider-clients.php';
require_once __DIR__ . '/cases/imports-library.php';
require_once __DIR__ . '/cases/css-storage-catalog.php';
require_once __DIR__ . '/cases/storage-context-regression.php';
require_once __DIR__ . '/cases/settings-assets-runtime.php';
require_once __DIR__ . '/cases/admin-renderer.php';
require_once __DIR__ . '/cases/admin-controller-rest.php';
require_once __DIR__ . '/cases/plugin-updater.php';
require_once __DIR__ . '/cases/hook-regressions.php';
require_once __DIR__ . '/cases/expanded-utilities.php';
require_once __DIR__ . '/cases/repository-core.php';

runTestSuite($tests);
