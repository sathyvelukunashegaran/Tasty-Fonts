<?php

declare(strict_types=1);

namespace TastyFonts\Cli;

defined('ABSPATH') || exit;

use TastyFonts\Admin\AdminController;
use TastyFonts\Support\FontUtils;
use WP_Error;

/**
 * WP-CLI command adapter for Advanced Tools workflows.
 *
 * Commands intentionally route through AdminController so CLI and admin actions
 * share the same maintenance, snapshot, transfer, and support-bundle behavior.
 */
final class Command
{
    public function __construct(private readonly AdminController $admin)
    {
    }

    /**
     * Inspect structured health checks.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Use "summary" or "json". Default: summary.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function doctor(array $args, array $assocArgs): void
    {
        unset($args);

        $payload = $this->admin->buildAdvancedToolsPayload();
        $advancedTools = $this->map($payload['advanced_tools'] ?? []);
        $checks = $this->list($advancedTools['health_checks'] ?? []);
        $summary = $this->map($advancedTools['health_summary'] ?? []);

        if ($this->format($assocArgs) === 'json') {
            $this->json([
                'summary' => $summary,
                'checks' => $checks,
            ]);
            return;
        }

        $this->line('Tasty Fonts doctor: ' . $this->string($summary['label'] ?? 'Unknown'));

        foreach ($checks as $check) {
            if (!is_array($check)) {
                continue;
            }

            $severity = strtoupper($this->string($check['severity'] ?? 'info'));
            $label = $this->string($check['label'] ?? $check['title'] ?? $check['slug'] ?? 'Check');
            $message = $this->string($check['summary'] ?? $check['message'] ?? '');
            $this->line(sprintf('[%s] %s%s', $severity, $label, $message !== '' ? ': ' . $message : ''));
        }
    }

    /**
     * Manage generated CSS.
     *
     * ## OPTIONS
     *
     * <regenerate>
     * : Rebuild the generated runtime stylesheet.
     *
     * [--format=<format>]
     * : Use "summary" or "json". Default: summary.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function css(array $args, array $assocArgs): void
    {
        $this->requireSubcommand($args, ['regenerate'], 'Usage: wp tasty-fonts css regenerate');

        $this->finish($this->admin->regenerateCss(), $assocArgs);
    }

    /**
     * Manage plugin caches.
     *
     * ## OPTIONS
     *
     * <clear>
     * : Clear plugin caches and rebuild generated assets.
     *
     * [--format=<format>]
     * : Use "summary" or "json". Default: summary.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function cache(array $args, array $assocArgs): void
    {
        $this->requireSubcommand($args, ['clear'], 'Usage: wp tasty-fonts cache clear');

        $this->finish($this->admin->clearPluginCachesAndRegenerateAssets(), $assocArgs);
    }

    /**
     * Manage the font library.
     *
     * ## OPTIONS
     *
     * <rescan>
     * : Rescan local font storage and refresh the catalog view.
     *
     * [--format=<format>]
     * : Use "summary" or "json". Default: summary.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function library(array $args, array $assocArgs): void
    {
        $this->requireSubcommand($args, ['rescan'], 'Usage: wp tasty-fonts library rescan');

        $this->finish($this->admin->rescanFontLibrary(), $assocArgs);
    }

    /**
     * Export or import site transfer bundles.
     *
     * ## OPTIONS
     *
     * <export|import>
     * : Export a bundle or import an existing bundle path.
     *
     * [<bundle>]
     * : Bundle path for import.
     *
     * [--dry-run]
     * : Validate and diff an import bundle without changing state.
     *
     * [--google-api-key=<key>]
     * : Optional fresh Google Fonts API key for import.
     *
     * [--yes]
     * : Required for destructive imports.
     *
     * [--format=<format>]
     * : Use "summary" or "json". Default: summary.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function transfer(array $args, array $assocArgs): void
    {
        $subcommand = $this->string($args[0] ?? '');

        if ($subcommand === 'export') {
            $this->finish($this->admin->exportSiteTransferBundle(), $assocArgs, 'path');
            return;
        }

        if ($subcommand !== 'import') {
            $this->failMessage('Usage: wp tasty-fonts transfer export|import <bundle> [--dry-run] [--yes]');
        }

        $bundlePath = $this->string($args[1] ?? '');

        if ($bundlePath === '') {
            $this->failMessage('A bundle path is required for transfer import.');
        }

        $googleApiKey = $this->string($assocArgs['google-api-key'] ?? '');

        if (!empty($assocArgs['dry-run'])) {
            $this->finish($this->admin->dryRunSiteTransferBundlePath($bundlePath, $googleApiKey), $assocArgs);
            return;
        }

        $this->requireYes($assocArgs, 'Transfer import replaces current Tasty Fonts settings, library, and managed files.');
        $this->finish($this->admin->importSiteTransferBundlePath($bundlePath, $googleApiKey), $assocArgs);
    }

    /**
     * Build a sanitized support bundle.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Use "summary" or "json". Default: summary.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function support_bundle(array $args, array $assocArgs): void
    {
        unset($args);

        $this->finish($this->admin->buildSupportBundle(), $assocArgs, 'path');
    }

    /**
     * Hyphenated command alias registered explicitly as `support-bundle`.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function supportBundle(array $args, array $assocArgs): void
    {
        $this->support_bundle($args, $assocArgs);
    }

    /**
     * Manage rollback snapshots.
     *
     * ## OPTIONS
     *
     * <create|restore|list>
     * : Create, restore, or list snapshots.
     *
     * [<snapshot-id>]
     * : Snapshot id for restore.
     *
     * [--reason=<reason>]
     * : Snapshot reason when creating. Default: manual.
     *
     * [--yes]
     * : Required for destructive restores.
     *
     * [--format=<format>]
     * : Use "summary" or "json". Default: summary.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function snapshot(array $args, array $assocArgs): void
    {
        $subcommand = $this->string($args[0] ?? '');

        if ($subcommand === 'create') {
            $reason = $this->string($assocArgs['reason'] ?? 'manual');
            $this->finish($this->admin->createRollbackSnapshot($reason !== '' ? $reason : 'manual'), $assocArgs, 'snapshot.id');
            return;
        }

        if ($subcommand === 'restore') {
            $snapshotId = $this->string($args[1] ?? '');

            if ($snapshotId === '') {
                $this->failMessage('A snapshot id is required for snapshot restore.');
            }

            $this->requireYes($assocArgs, 'Snapshot restore replaces current Tasty Fonts settings, library, and managed files.');
            $this->finish($this->admin->restoreRollbackSnapshot($snapshotId), $assocArgs);
            return;
        }

        if ($subcommand === 'list') {
            $payload = $this->admin->listRollbackSnapshots();
            $snapshots = $this->list($payload['snapshots'] ?? []);

            if ($this->format($assocArgs) === 'json') {
                $this->json(['snapshots' => $snapshots]);
                return;
            }

            if ($snapshots === []) {
                $this->line('No rollback snapshots found.');
                return;
            }

            foreach ($snapshots as $snapshot) {
                if (!is_array($snapshot)) {
                    continue;
                }

                $this->line(sprintf(
                    '%s %s %d families %d files',
                    $this->string($snapshot['id'] ?? ''),
                    $this->string($snapshot['created_at'] ?? ''),
                    $this->integer($snapshot['families'] ?? 0),
                    $this->integer($snapshot['files'] ?? 0)
                ));
            }
            return;
        }

        $this->failMessage('Usage: wp tasty-fonts snapshot create|restore|list');
    }

    /**
     * @param array<string, mixed>|WP_Error $result
     * @param array<string, mixed> $assocArgs
     */
    private function finish(array|WP_Error $result, array $assocArgs, string $pathKey = ''): void
    {
        if (is_wp_error($result)) {
            $this->fail($result);
            return;
        }

        if ($this->format($assocArgs) === 'json') {
            $this->json($result);
            return;
        }

        $message = $this->string($result['message'] ?? '');

        if ($pathKey !== '') {
            $pathValue = $this->nestedValue($result, $pathKey);

            if (is_scalar($pathValue) && trim((string) $pathValue) !== '') {
                $message .= ($message !== '' ? ' ' : '') . trim((string) $pathValue);
            }
        }

        $this->success($message !== '' ? $message : 'Done.');
    }

    /**
     * @param array<string, mixed> $assocArgs
     */
    private function requireYes(array $assocArgs, string $message): void
    {
        if (!empty($assocArgs['yes'])) {
            return;
        }

        $this->failMessage($message . ' Re-run with --yes to continue.');
    }

    /**
     * @param list<string> $args
     * @param list<string> $allowed
     */
    private function requireSubcommand(array $args, array $allowed, string $usage): void
    {
        if (in_array($this->string($args[0] ?? ''), $allowed, true)) {
            return;
        }

        $this->failMessage($usage);
    }

    /**
     * @param array<string, mixed> $assocArgs
     */
    private function format(array $assocArgs): string
    {
        return $this->string($assocArgs['format'] ?? 'summary') === 'json' ? 'json' : 'summary';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function nestedValue(array $payload, string $path): mixed
    {
        $value = $payload;

        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    private function fail(WP_Error $error): void
    {
        $this->failMessage($error->get_error_message());
    }

    private function failMessage(string $message): void
    {
        $cliClass = 'WP_CLI';

        if (class_exists($cliClass) && is_callable([$cliClass, 'error'])) {
            $cliClass::error($message);
            return;
        }

        throw new \RuntimeException($message);
    }

    private function success(string $message): void
    {
        $cliClass = 'WP_CLI';

        if (class_exists($cliClass) && is_callable([$cliClass, 'success'])) {
            $cliClass::success($message);
            return;
        }

        $this->line('Success: ' . $message);
    }

    private function line(string $message): void
    {
        $cliClass = 'WP_CLI';

        if (class_exists($cliClass) && is_callable([$cliClass, 'line'])) {
            $cliClass::line($message);
            return;
        }

        echo $message . PHP_EOL;
    }

    /**
     * @param array<string, mixed>|list<mixed> $payload
     */
    private function json(array $payload): void
    {
        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->line(is_string($json) ? $json : '{}');
    }

    /**
     * @return array<string, mixed>
     */
    private function map(mixed $value): array
    {
        return FontUtils::normalizeStringKeyedMap($value);
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
