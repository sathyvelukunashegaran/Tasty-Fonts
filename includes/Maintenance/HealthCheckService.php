<?php

declare(strict_types=1);

namespace TastyFonts\Maintenance;

defined('ABSPATH') || exit;

use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type CatalogCounts from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type NormalizedSettings from SettingsRepository
 * @phpstan-type AssetStatus array<string, mixed>
 * @phpstan-type StorageState array<string, mixed>
 * @phpstan-type HealthCheckEvidence array{label: string, value: string}
 * @phpstan-type HealthCheckAction array{slug: string, label: string}
 * @phpstan-type HealthCheck array{
 *     slug: string,
 *     group: string,
 *     severity: 'ok'|'info'|'warning'|'critical',
 *     title: string,
 *     message: string,
 *     evidence: list<HealthCheckEvidence>,
 *     action: HealthCheckAction|null,
 *     actions: list<HealthCheckAction>
 * }
 */
final class HealthCheckService
{
    /**
     * @param AssetStatus $assetStatus
     * @param StorageState|null $storage
     * @param NormalizedSettings $settings
     * @param CatalogCounts $counts
     * @param array{available?: bool, message?: string} $transferCapability
     * @param array<string, mixed> $runtimeManifest
     * @param array<string, mixed> $googleAccess
     * @param array<string, mixed> $updateChannelStatus
     * @param array<string, mixed> $environment
     * @return list<HealthCheck>
     */
    public function build(
        array $assetStatus,
        ?array $storage,
        array $settings,
        array $counts,
        array $transferCapability = [],
        array $runtimeManifest = [],
        array $googleAccess = [],
        array $updateChannelStatus = [],
        array $environment = []
    ): array {
        return [
            $this->buildGeneratedCssCheck($assetStatus, $settings),
            $this->buildStorageCheck($storage),
            $this->buildSelfHostedFilesCheck($runtimeManifest),
            $this->buildExternalStylesheetCheck($runtimeManifest, $settings),
            $this->buildPreloadCheck($runtimeManifest, $settings),
            $this->buildBlockEditorSyncCheck($runtimeManifest, $environment),
            $this->buildTransferCheck($transferCapability),
            $this->buildGoogleAccessCheck($googleAccess),
            $this->buildUpdateChannelCheck($updateChannelStatus),
            $this->buildLibraryCheck($counts),
        ];
    }

    /**
     * @param list<HealthCheck> $checks
     * @return array{critical: int, warning: int, info: int, ok: int, total: int, status: string, label: string}
     */
    public function summarize(array $checks): array
    {
        $critical = 0;
        $warning = 0;
        $info = 0;
        $ok = 0;

        foreach ($checks as $check) {
            switch ($check['severity']) {
                case 'critical':
                    $critical++;
                    break;
                case 'warning':
                    $warning++;
                    break;
                case 'info':
                    $info++;
                    break;
                default:
                    $ok++;
                    break;
            }
        }

        $status = 'ok';
        $label = __('Clear', 'tasty-fonts');

        if ($critical > 0) {
            $status = 'critical';
            $label = sprintf(
                /* translators: %d: number of critical health checks */
                _n('%d critical', '%d critical', $critical, 'tasty-fonts'),
                $critical
            );
        } elseif ($warning > 0) {
            $status = 'warning';
            $label = sprintf(
                /* translators: %d: number of warning health checks */
                _n('%d warning', '%d warnings', $warning, 'tasty-fonts'),
                $warning
            );
        } elseif ($info > 0) {
            $status = 'info';
            $label = sprintf(
                /* translators: %d: number of informational health checks */
                _n('%d notice', '%d notices', $info, 'tasty-fonts'),
                $info
            );
        }

        return [
            'critical' => $critical,
            'warning' => $warning,
            'info' => $info,
            'ok' => $ok,
            'total' => count($checks),
            'status' => $status,
            'label' => $label,
        ];
    }

    /**
     * @param AssetStatus $assetStatus
     * @param NormalizedSettings $settings
     * @return HealthCheck
     */
    private function buildGeneratedCssCheck(array $assetStatus, array $settings): array
    {
        $deliveryMode = FontUtils::scalarStringValue($settings['css_delivery_mode'] ?? 'file');
        $deliveryMode = $deliveryMode !== '' ? $deliveryMode : 'file';
        $path = FontUtils::scalarStringValue($assetStatus['path'] ?? '');
        $url = FontUtils::scalarStringValue($assetStatus['url'] ?? '');
        $exists = !empty($assetStatus['exists']);
        $isCurrent = array_key_exists('is_current', $assetStatus) ? !empty($assetStatus['is_current']) : true;
        $sizeValue = $assetStatus['size'] ?? 0;
        $size = is_numeric($sizeValue) ? max(0, (int) $sizeValue) : 0;
        $writePath = FontUtils::scalarStringValue($assetStatus['write_path'] ?? $path);
        $writeDirectory = $writePath !== '' ? dirname($writePath) : '';
        $writeable = $writeDirectory !== '' && (!is_dir($writeDirectory) || is_writable($writeDirectory));

        $severity = 'ok';
        $message = __('Generated CSS is available for runtime delivery.', 'tasty-fonts');
        $action = null;

        if ($deliveryMode === 'file' && !$exists) {
            $severity = 'warning';
            $message = __('Generated CSS file delivery is selected, but the stylesheet has not been written yet.', 'tasty-fonts');
            $action = [
                'slug' => 'regenerate_css',
                'label' => __('Regenerate CSS File', 'tasty-fonts'),
            ];
        } elseif (!$isCurrent) {
            $severity = 'warning';
            $message = __('Generated CSS exists, but it does not match the current runtime plan.', 'tasty-fonts');
            $action = [
                'slug' => 'regenerate_css',
                'label' => __('Regenerate CSS File', 'tasty-fonts'),
            ];
        } elseif (!$writeable) {
            $severity = 'warning';
            $message = __('The generated stylesheet folder is not writable.', 'tasty-fonts');
            $action = [
                'slug' => 'clear_plugin_caches',
                'label' => __('Clear Caches & Rebuild', 'tasty-fonts'),
            ];
        } elseif ($path === '' || $url === '') {
            $severity = 'warning';
            $message = __('The generated stylesheet path or public URL is unavailable.', 'tasty-fonts');
            $action = [
                'slug' => 'clear_plugin_caches',
                'label' => __('Clear Caches & Rebuild', 'tasty-fonts'),
            ];
        }

        return $this->check(
            'generated_css',
            'runtime',
            $severity,
            __('Generated CSS', 'tasty-fonts'),
            $message,
            [
                ['label' => __('Delivery', 'tasty-fonts'), 'value' => $deliveryMode],
                ['label' => __('Path', 'tasty-fonts'), 'value' => $path !== '' ? $path : __('Not available', 'tasty-fonts')],
                ['label' => __('URL', 'tasty-fonts'), 'value' => $url !== '' ? $url : __('Not available', 'tasty-fonts')],
                ['label' => __('Size', 'tasty-fonts'), 'value' => $exists ? size_format($size) : __('Not generated', 'tasty-fonts')],
                ['label' => __('Current', 'tasty-fonts'), 'value' => $isCurrent ? __('Yes', 'tasty-fonts') : __('No', 'tasty-fonts')],
                ['label' => __('Writable', 'tasty-fonts'), 'value' => $writeable ? __('Yes', 'tasty-fonts') : __('No', 'tasty-fonts')],
            ],
            $action
        );
    }

    /**
     * @param array<string, mixed> $runtimeManifest
     * @return HealthCheck
     */
    private function buildSelfHostedFilesCheck(array $runtimeManifest): array
    {
        $families = is_array($runtimeManifest['families'] ?? null) ? $runtimeManifest['families'] : [];
        $missing = [];

        foreach ($families as $family) {
            if (!is_array($family)) {
                continue;
            }

            $familyName = FontUtils::scalarStringValue($family['family'] ?? __('Unknown family', 'tasty-fonts'));
            $files = is_array($family['missing_files'] ?? null) ? $family['missing_files'] : [];

            foreach ($files as $file) {
                if (!is_scalar($file)) {
                    continue;
                }

                $path = trim((string) $file);

                if ($path !== '') {
                    $missing[] = $familyName . ': ' . $path;
                }
            }
        }

        return $this->check(
            'self_hosted_files',
            'runtime',
            $missing === [] ? 'ok' : 'critical',
            __('Self-hosted Files', 'tasty-fonts'),
            $missing === []
                ? __('Active self-hosted delivery profiles have their font files available.', 'tasty-fonts')
                : __('One or more active self-hosted delivery profiles reference missing font files.', 'tasty-fonts'),
            [
                ['label' => __('Missing files', 'tasty-fonts'), 'value' => $missing === [] ? '0' : implode(', ', array_slice($missing, 0, 3))],
            ],
            $missing === [] ? null : ['slug' => 'clear_plugin_caches', 'label' => __('Clear Caches & Rebuild', 'tasty-fonts')]
        );
    }

    /**
     * @param array<string, mixed> $runtimeManifest
     * @param NormalizedSettings $settings
     * @return HealthCheck
     */
    private function buildExternalStylesheetCheck(array $runtimeManifest, array $settings): array
    {
        $stylesheets = is_array($runtimeManifest['external_stylesheets'] ?? null) ? $runtimeManifest['external_stylesheets'] : [];
        $origins = [];

        foreach ($stylesheets as $stylesheet) {
            if (!is_array($stylesheet)) {
                continue;
            }

            $url = FontUtils::scalarStringValue($stylesheet['url'] ?? '');
            $host = $url !== '' ? (string) parse_url($url, PHP_URL_HOST) : '';

            if ($host !== '') {
                $origins['https://' . $host] = 'https://' . $host;
            }
        }

        $hasThirdPartyOrigins = $origins !== [];

        return $this->check(
            'external_stylesheets',
            'runtime',
            $hasThirdPartyOrigins ? 'info' : 'ok',
            __('External Stylesheets', 'tasty-fonts'),
            $hasThirdPartyOrigins
                ? __('Runtime delivery includes third-party stylesheet origins.', 'tasty-fonts')
                : __('Runtime delivery is not using third-party stylesheet URLs.', 'tasty-fonts'),
            [
                ['label' => __('Stylesheets', 'tasty-fonts'), 'value' => (string) count($stylesheets)],
                ['label' => __('Connection hints', 'tasty-fonts'), 'value' => !empty($settings['remote_connection_hints']) ? __('On', 'tasty-fonts') : __('Off', 'tasty-fonts')],
                ['label' => __('Origins', 'tasty-fonts'), 'value' => $origins === [] ? __('None', 'tasty-fonts') : implode(', ', array_values($origins))],
            ],
            null
        );
    }

    /**
     * @param array<string, mixed> $runtimeManifest
     * @param NormalizedSettings $settings
     * @return HealthCheck
     */
    private function buildPreloadCheck(array $runtimeManifest, array $settings): array
    {
        $preloadEnabled = !empty($settings['preload_primary_fonts']);
        $preloadUrls = is_array($runtimeManifest['preload_urls'] ?? null) ? $runtimeManifest['preload_urls'] : [];

        if (!$preloadEnabled) {
            return $this->check(
                'font_preload',
                'runtime',
                'ok',
                __('Primary Font Preload', 'tasty-fonts'),
                __('Primary font preloading is disabled.', 'tasty-fonts'),
                [['label' => __('Preload URLs', 'tasty-fonts'), 'value' => '0']],
                null
            );
        }

        return $this->check(
            'font_preload',
            'runtime',
            $preloadUrls === [] ? 'info' : 'ok',
            __('Primary Font Preload', 'tasty-fonts'),
            $preloadUrls === []
                ? __('Primary font preloading is enabled, but no same-origin WOFF2 assets are currently preloadable.', 'tasty-fonts')
                : __('Primary font preloading can emit same-origin WOFF2 assets.', 'tasty-fonts'),
            [['label' => __('Preload URLs', 'tasty-fonts'), 'value' => (string) count($preloadUrls)]],
            null
        );
    }

    /**
     * @param array<string, mixed> $runtimeManifest
     * @param array<string, mixed> $environment
     * @return HealthCheck
     */
    private function buildBlockEditorSyncCheck(array $runtimeManifest, array $environment): array
    {
        $editor = is_array($runtimeManifest['editor'] ?? null) ? $runtimeManifest['editor'] : [];
        $enabled = !empty($editor['block_editor_sync_enabled']);
        $isLocal = !empty($environment) || $this->isLocalRestUrl();

        return $this->check(
            'block_editor_sync',
            'integration',
            $enabled && $isLocal ? 'warning' : 'ok',
            __('Block Editor Sync', 'tasty-fonts'),
            $enabled && $isLocal
                ? __('Block Editor Font Library sync is enabled in a likely local or self-signed environment.', 'tasty-fonts')
                : __('Block Editor sync state does not show a local loopback risk.', 'tasty-fonts'),
            [
                ['label' => __('Sync', 'tasty-fonts'), 'value' => $enabled ? __('On', 'tasty-fonts') : __('Off', 'tasty-fonts')],
                ['label' => __('Environment', 'tasty-fonts'), 'value' => $isLocal ? __('Local', 'tasty-fonts') : __('Production-like', 'tasty-fonts')],
            ],
            null
        );
    }

    /**
     * @param array<string, mixed> $googleAccess
     * @return HealthCheck
     */
    private function buildGoogleAccessCheck(array $googleAccess): array
    {
        $saved = !empty($googleAccess['google_api_saved']);
        $enabled = !empty($googleAccess['google_api_enabled']);
        $state = FontUtils::scalarStringValue($googleAccess['google_api_state'] ?? '');

        return $this->check(
            'google_fonts_api',
            'provider',
            !$saved || $enabled ? 'ok' : 'warning',
            __('Google Fonts API', 'tasty-fonts'),
            !$saved
                ? __('Google search can use the bundled catalog until an API key is added.', 'tasty-fonts')
                : ($enabled ? __('The saved Google Fonts API key can be used for search.', 'tasty-fonts') : __('The saved Google Fonts API key is not currently valid for search.', 'tasty-fonts')),
            [
                ['label' => __('Key saved', 'tasty-fonts'), 'value' => $saved ? __('Yes', 'tasty-fonts') : __('No', 'tasty-fonts')],
                ['label' => __('State', 'tasty-fonts'), 'value' => $state !== '' ? $state : __('Not checked', 'tasty-fonts')],
            ],
            $saved && !$enabled ? ['slug' => 'reset_integration_detection_state', 'label' => __('Run Integration Scan', 'tasty-fonts')] : null
        );
    }

    /**
     * @param array<string, mixed> $updateChannelStatus
     * @return HealthCheck
     */
    private function buildUpdateChannelCheck(array $updateChannelStatus): array
    {
        $state = FontUtils::scalarStringValue($updateChannelStatus['state'] ?? '');
        $severity = match ($state) {
            'rollback' => 'warning',
            'upgrade' => 'info',
            'unavailable' => 'info',
            default => 'ok',
        };
        $copy = FontUtils::scalarStringValue($updateChannelStatus['state_copy'] ?? '');

        return $this->check(
            'update_channel',
            'updates',
            $severity,
            __('Update Channel', 'tasty-fonts'),
            $copy !== '' ? $copy : __('Update channel status is available.', 'tasty-fonts'),
            [
                ['label' => __('Selected', 'tasty-fonts'), 'value' => FontUtils::scalarStringValue($updateChannelStatus['selected_channel_label'] ?? __('Unknown', 'tasty-fonts'))],
                ['label' => __('Installed', 'tasty-fonts'), 'value' => FontUtils::scalarStringValue($updateChannelStatus['installed_version'] ?? '')],
                ['label' => __('Latest', 'tasty-fonts'), 'value' => FontUtils::scalarStringValue($updateChannelStatus['latest_version'] ?? __('Unavailable', 'tasty-fonts'))],
            ],
            null
        );
    }

    /**
     * @param StorageState|null $storage
     * @return HealthCheck
     */
    private function buildStorageCheck(?array $storage): array
    {
        if (!is_array($storage)) {
            return $this->check(
                'storage_root',
                'storage',
                'critical',
                __('Managed Storage', 'tasty-fonts'),
                __('The managed fonts directory is unavailable.', 'tasty-fonts'),
                [],
                ['slug' => 'clear_plugin_caches', 'label' => __('Clear Caches & Rebuild', 'tasty-fonts')]
            );
        }

        $root = FontUtils::scalarStringValue($storage['dir'] ?? '');
        $generatedDir = FontUtils::scalarStringValue($storage['generated_dir'] ?? '');
        $rootWritable = $root !== '' && is_dir($root) && is_writable($root);
        $generatedWritable = $generatedDir !== '' && (!is_dir($generatedDir) || is_writable($generatedDir));
        $severity = $rootWritable && $generatedWritable ? 'ok' : 'warning';
        $message = $severity === 'ok'
            ? __('Managed storage is writable.', 'tasty-fonts')
            : __('Managed storage may not be writable for font files or generated CSS.', 'tasty-fonts');

        return $this->check(
            'storage_root',
            'storage',
            $severity,
            __('Managed Storage', 'tasty-fonts'),
            $message,
            [
                ['label' => __('Root', 'tasty-fonts'), 'value' => $root !== '' ? $root : __('Not available', 'tasty-fonts')],
                ['label' => __('Generated folder', 'tasty-fonts'), 'value' => $generatedDir !== '' ? $generatedDir : __('Not available', 'tasty-fonts')],
            ],
            $severity === 'ok' ? null : ['slug' => 'clear_plugin_caches', 'label' => __('Clear Caches & Rebuild', 'tasty-fonts')]
        );
    }

    /**
     * @param array{available?: bool, message?: string} $transferCapability
     * @return HealthCheck
     */
    private function buildTransferCheck(array $transferCapability): array
    {
        $available = array_key_exists('available', $transferCapability)
            ? !empty($transferCapability['available'])
            : class_exists(\ZipArchive::class);
        $message = FontUtils::scalarStringValue($transferCapability['message'] ?? '');

        return $this->check(
            'site_transfer',
            'transfer',
            $available ? 'ok' : 'warning',
            __('Site Transfer', 'tasty-fonts'),
            $available
                ? __('Site transfer bundles can be exported and validated.', 'tasty-fonts')
                : ($message !== '' ? $message : __('ZipArchive is unavailable, so site transfer bundles cannot run on this server.', 'tasty-fonts')),
            [
                ['label' => __('ZipArchive', 'tasty-fonts'), 'value' => $available ? __('Available', 'tasty-fonts') : __('Unavailable', 'tasty-fonts')],
            ],
            null
        );
    }

    /**
     * @param CatalogCounts $counts
     * @return HealthCheck
     */
    private function buildLibraryCheck(array $counts): array
    {
        $families = max(0, $counts['families']);
        $files = max(0, $counts['files']);
        $severity = $families > 0 ? 'ok' : 'info';

        return $this->check(
            'library_inventory',
            'library',
            $severity,
            __('Library Inventory', 'tasty-fonts'),
            $families > 0
                ? __('Managed font families are available.', 'tasty-fonts')
                : __('No managed font families are currently available.', 'tasty-fonts'),
            [
                ['label' => __('Families', 'tasty-fonts'), 'value' => (string) $families],
                ['label' => __('Files', 'tasty-fonts'), 'value' => (string) $files],
            ],
            null
        );
    }

    /**
     * @param 'ok'|'info'|'warning'|'critical' $severity
     * @param list<HealthCheckEvidence> $evidence
     * @param HealthCheckAction|null $action
     * @return HealthCheck
     */
    private function check(
        string $slug,
        string $group,
        string $severity,
        string $title,
        string $message,
        array $evidence = [],
        ?array $action = null
    ): array {
        return [
            'slug' => $slug,
            'group' => $group,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'evidence' => $evidence,
            'action' => $action,
            'actions' => $action === null ? [] : [$action],
        ];
    }

    private function isLocalRestUrl(): bool
    {
        if (!function_exists('rest_url')) {
            return false;
        }

        $host = (string) parse_url(rest_url(''), PHP_URL_HOST);

        return $host === 'localhost'
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local')
            || str_starts_with($host, '127.')
            || $host === '::1';
    }
}
