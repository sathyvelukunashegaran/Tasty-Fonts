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
 *     action: HealthCheckAction|null
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
     * @return list<HealthCheck>
     */
    public function build(
        array $assetStatus,
        ?array $storage,
        array $settings,
        array $counts,
        array $transferCapability = []
    ): array {
        return [
            $this->buildGeneratedCssCheck($assetStatus, $settings),
            $this->buildStorageCheck($storage),
            $this->buildTransferCheck($transferCapability),
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
        $sizeValue = $assetStatus['size'] ?? 0;
        $size = is_numeric($sizeValue) ? max(0, (int) $sizeValue) : 0;

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
            ],
            $action
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
        ];
    }
}
