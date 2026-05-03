<?php

declare(strict_types=1);

namespace TastyFonts\Maintenance;

defined('ABSPATH') || exit;

use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type CatalogCounts from \TastyFonts\Fonts\CatalogCache
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
 *     guidance: string,
 *     help_url: string,
 *     help_label: string,
 *     evidence: list<HealthCheckEvidence>,
 *     action: HealthCheckAction|null,
 *     actions: list<HealthCheckAction>
 * }
 */
final class HealthCheckService
{
    private const DOCS_BASE_URL = 'https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki/';

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
            $this->buildSitewideDeliveryCheck($runtimeManifest, $settings),
            $this->buildStorageCheck($storage),
            $this->buildSelfHostedFilesCheck($runtimeManifest),
            $this->buildExternalStylesheetCheck($runtimeManifest, $settings),
            $this->buildPreloadCheck($runtimeManifest, $settings),
            $this->buildBlockEditorSyncCheck($runtimeManifest, $environment),
            $this->buildTransferCheck($transferCapability),
            $this->buildGoogleAccessCheck($googleAccess),
            $this->buildUpdateChannelCheck($updateChannelStatus),
            ...$this->buildIntegrationChecks($runtimeManifest),
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
        $message = __('The front-end stylesheet exists and can be served from the selected delivery mode.', 'tasty-fonts');
        $guidance = __('Regenerate CSS after changing roles, delivery profiles, or output settings so the runtime file stays aligned with saved settings.', 'tasty-fonts');
        $action = null;

        if ($deliveryMode === 'file' && !$exists) {
            $severity = 'warning';
            $message = __('File delivery is selected, but the front-end stylesheet has not been written yet.', 'tasty-fonts');
            $guidance = __('Run Regenerate CSS File. Until the file exists, visitors may not receive the current font variables and face rules.', 'tasty-fonts');
            $action = [
                'slug' => 'regenerate_css',
                'label' => __('Regenerate CSS File', 'tasty-fonts'),
            ];
        } elseif (!$isCurrent) {
            $severity = 'warning';
            $message = __('The stylesheet exists, but it was generated from an older runtime plan.', 'tasty-fonts');
            $guidance = __('Regenerate the CSS file so the live stylesheet matches the latest saved fonts, roles, and output settings.', 'tasty-fonts');
            $action = [
                'slug' => 'regenerate_css',
                'label' => __('Regenerate CSS File', 'tasty-fonts'),
            ];
        } elseif (!$writeable) {
            $severity = 'warning';
            $message = __('WordPress cannot write to the folder used for the generated stylesheet.', 'tasty-fonts');
            $guidance = __('Fix folder permissions first, then rebuild caches so Tasty Fonts can replace stale runtime files.', 'tasty-fonts');
            $action = [
                'slug' => 'clear_plugin_caches',
                'label' => __('Clear Caches & Rebuild', 'tasty-fonts'),
            ];
        } elseif ($path === '' || $url === '') {
            $severity = 'warning';
            $message = __('Tasty Fonts cannot resolve either the stylesheet file path or its public URL.', 'tasty-fonts');
            $guidance = __('Rebuild caches to refresh upload paths. If this remains unavailable, check the WordPress uploads configuration.', 'tasty-fonts');
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
            $action,
            $guidance,
            $this->docsUrl('Advanced-Tools')
        );
    }

    /**
     * @param array<string, mixed> $runtimeManifest
     * @param NormalizedSettings $settings
     * @return HealthCheck
     */
    private function buildSitewideDeliveryCheck(array $runtimeManifest, array $settings): array
    {
        $delivery = is_array($runtimeManifest['delivery'] ?? null) ? $runtimeManifest['delivery'] : [];
        $enabled = array_key_exists('auto_apply_roles', $delivery)
            ? !empty($delivery['auto_apply_roles'])
            : !empty($settings['auto_apply_roles']);

        return $this->check(
            'sitewide_delivery',
            'runtime',
            $enabled ? 'ok' : 'warning',
            __('Deploy Fonts', 'tasty-fonts'),
            $enabled
                ? __('Sitewide Delivery is on, so published role fonts can be served to the frontend.', 'tasty-fonts')
                : __('Sitewide Delivery is off, so saved role fonts are not deployed to the frontend.', 'tasty-fonts'),
            [
                ['label' => __('Sitewide Delivery', 'tasty-fonts'), 'value' => $enabled ? __('On', 'tasty-fonts') : __('Off', 'tasty-fonts')],
            ],
            $enabled ? null : ['slug' => 'deploy_fonts', 'label' => __('Deploy Fonts', 'tasty-fonts')],
            $enabled
                ? __('Use Deploy Fonts to review, save, or publish role assignments when they change.', 'tasty-fonts')
                : __('Open Deploy Fonts and enable Sitewide Delivery when role assignments are ready for the frontend.', 'tasty-fonts'),
            $this->docsUrl('Deploy-Fonts')
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
            $missing === [] ? null : ['slug' => 'clear_plugin_caches', 'label' => __('Clear Caches & Rebuild', 'tasty-fonts')],
            $missing === []
                ? __('This means the active local font files can be read from disk when the front end asks for them.', 'tasty-fonts')
                : __('Rebuild caches first. If the same files remain missing, re-import or re-upload the affected family.', 'tasty-fonts'),
            $this->docsUrl('Font-Library')
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
                ? __('At least one active family is loaded from a third-party stylesheet instead of a local file.', 'tasty-fonts')
                : __('No active runtime family is using a third-party stylesheet URL.', 'tasty-fonts'),
            [
                ['label' => __('Stylesheets', 'tasty-fonts'), 'value' => (string) count($stylesheets)],
                ['label' => __('Connection hints', 'tasty-fonts'), 'value' => !empty($settings['remote_connection_hints']) ? __('On', 'tasty-fonts') : __('Off', 'tasty-fonts')],
                ['label' => __('Origins', 'tasty-fonts'), 'value' => $origins === [] ? __('None', 'tasty-fonts') : implode(', ', array_values($origins))],
            ],
            null,
            $hasThirdPartyOrigins
                ? __('This is expected for CDN delivery. Use self-hosted delivery when privacy, performance control, or offline availability matters.', 'tasty-fonts')
                : __('All active runtime delivery is local or inline, so there are no third-party stylesheet requests to hint or audit here.', 'tasty-fonts'),
            $this->docsUrl('Deploy-Fonts')
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
                __('Primary font preloading is off, so Tasty Fonts will not add preload tags.', 'tasty-fonts'),
                [['label' => __('Preload URLs', 'tasty-fonts'), 'value' => '0']],
                null,
                __('Turn this on only when a sitewide heading or body font should be requested earlier by the browser.', 'tasty-fonts'),
                $this->docsUrl('Settings')
            );
        }

        return $this->check(
            'font_preload',
            'runtime',
            $preloadUrls === [] ? 'info' : 'ok',
            __('Primary Font Preload', 'tasty-fonts'),
            $preloadUrls === []
                ? __('Preloading is on, but no active same-origin WOFF2 font can be safely preloaded.', 'tasty-fonts')
                : __('Preloading can add same-origin WOFF2 font files for active primary roles.', 'tasty-fonts'),
            [['label' => __('Preload URLs', 'tasty-fonts'), 'value' => (string) count($preloadUrls)]],
            null,
            __('Preload only applies to local WOFF2 files used by published sitewide roles. Remote CSS, inactive families, and non-WOFF2 files are skipped on purpose.', 'tasty-fonts'),
            $this->docsUrl('Settings')
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
        $delivery = is_array($runtimeManifest['delivery'] ?? null) ? $runtimeManifest['delivery'] : [];
        $configured = !empty($editor['block_editor_sync_enabled']);
        $sitewideDeliveryEnabled = !empty($delivery['auto_apply_roles']);
        $enabled = $configured && $sitewideDeliveryEnabled;
        $isLocal = !empty($environment) || $this->isLocalRestUrl();

        return $this->check(
            'block_editor_sync',
            'integration',
            $enabled && $isLocal ? 'warning' : 'ok',
            __('Block Editor Sync', 'tasty-fonts'),
            $enabled && $isLocal
                ? __('Block Editor Font Library sync is on while this site looks local or self-signed.', 'tasty-fonts')
                : __('Block Editor sync does not show a local or self-signed REST risk.', 'tasty-fonts'),
            [
                ['label' => __('Sync', 'tasty-fonts'), 'value' => $enabled ? __('On', 'tasty-fonts') : __('Off', 'tasty-fonts')],
                ['label' => __('Environment', 'tasty-fonts'), 'value' => $isLocal ? __('Local', 'tasty-fonts') : __('Production-like', 'tasty-fonts')],
            ],
            null,
            $enabled && $isLocal
                ? __('On local HTTPS sites, WordPress may reject REST font uploads if the certificate is not trusted. Disable sync locally or trust the certificate.', 'tasty-fonts')
                : __('This check only warns when sync is enabled in an environment that is likely to block REST uploads.', 'tasty-fonts'),
            $this->docsUrl('Settings')
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
            !$saved ? 'info' : ($enabled ? 'ok' : 'warning'),
            __('Google Fonts API', 'tasty-fonts'),
            !$saved
                ? __('No Google Fonts API key is saved, so live Google search is using the bundled catalog only.', 'tasty-fonts')
                : ($enabled ? __('The saved Google Fonts API key is valid for live Google search.', 'tasty-fonts') : __('The saved Google Fonts API key is present, but Google search cannot use it.', 'tasty-fonts')),
            [
                ['label' => __('Key saved', 'tasty-fonts'), 'value' => $saved ? __('Yes', 'tasty-fonts') : __('No', 'tasty-fonts')],
                ['label' => __('State', 'tasty-fonts'), 'value' => $state !== '' ? $state : __('Not checked', 'tasty-fonts')],
            ],
            $saved && !$enabled ? ['slug' => 'reset_integration_detection_state', 'label' => __('Run Integration Scan', 'tasty-fonts')] : null,
            !$saved
                ? __('Add a key only when you need live Google search beyond the bundled catalog.', 'tasty-fonts')
                : ($enabled ? __('Live search can call Google with the saved key when importing from the Google provider.', 'tasty-fonts') : __('Replace the key or run the integration scan after fixing API access in Google Cloud.', 'tasty-fonts')),
            $this->docsUrl('Provider-Google-Fonts')
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
            null,
            __('Use Settings to change the selected channel, then install available packages through the normal WordPress update flow.', 'tasty-fonts'),
            $this->docsUrl('Settings')
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
                __('Tasty Fonts cannot find the managed fonts directory.', 'tasty-fonts'),
                [],
                ['slug' => 'clear_plugin_caches', 'label' => __('Clear Caches & Rebuild', 'tasty-fonts')],
                __('Confirm the WordPress uploads folder exists and is writable, then rebuild plugin caches.', 'tasty-fonts'),
                $this->docsUrl('Advanced-Tools')
            );
        }

        $root = FontUtils::scalarStringValue($storage['dir'] ?? '');
        $generatedDir = FontUtils::scalarStringValue($storage['generated_dir'] ?? '');
        $rootWritable = $root !== '' && is_dir($root) && is_writable($root);
        $generatedWritable = $generatedDir !== '' && (!is_dir($generatedDir) || is_writable($generatedDir));
        $severity = $rootWritable && $generatedWritable ? 'ok' : 'warning';
        $message = $severity === 'ok'
            ? __('WordPress can write to the managed fonts and generated CSS folders.', 'tasty-fonts')
            : __('WordPress may not be able to write font files or generated CSS.', 'tasty-fonts');

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
            $severity === 'ok' ? null : ['slug' => 'clear_plugin_caches', 'label' => __('Clear Caches & Rebuild', 'tasty-fonts')],
            $severity === 'ok'
                ? __('Imports, uploads, and generated stylesheet writes can use the expected plugin storage folders.', 'tasty-fonts')
                : __('Fix filesystem permissions before importing fonts or regenerating CSS, then rebuild plugin caches.', 'tasty-fonts'),
            $this->docsUrl('Advanced-Tools')
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
                ? __('This server can create and validate ZIP transfer bundles.', 'tasty-fonts')
                : ($message !== '' ? $message : __('ZipArchive is unavailable, so site transfer bundles cannot run on this server.', 'tasty-fonts')),
            [
                ['label' => __('ZipArchive', 'tasty-fonts'), 'value' => $available ? __('Available', 'tasty-fonts') : __('Unavailable', 'tasty-fonts')],
            ],
            null,
            $available
                ? __('Transfer bundles include settings, library metadata, and managed font files, but exclude runtime caches and secrets.', 'tasty-fonts')
                : __('Enable the PHP ZipArchive extension on this server before exporting or validating transfer bundles.', 'tasty-fonts'),
            $this->docsUrl('Advanced-Tools')
        );
    }

    /**
     * @param array<string, mixed> $runtimeManifest
     * @return list<HealthCheck>
     */
    private function buildIntegrationChecks(array $runtimeManifest): array
    {
        $integrations = is_array($runtimeManifest['integrations'] ?? null) ? $runtimeManifest['integrations'] : [];
        $checks = [];

        foreach ($integrations as $key => $integration) {
            if (!is_array($integration)) {
                continue;
            }

            $available = !array_key_exists('available', $integration) || !empty($integration['available']);
            $enabled = !empty($integration['enabled']);
            $status = FontUtils::scalarStringValue($integration['status'] ?? '');

            if (!$available || ($enabled && $status !== 'waiting_for_sitewide_roles')) {
                continue;
            }

            $title = FontUtils::scalarStringValue($integration['title'] ?? '');
            $title = $title !== '' ? $title : ucwords(str_replace('_', ' ', (string) $key));
            $statusLabel = FontUtils::scalarStringValue($integration['status_label'] ?? '');
            $statusCopy = FontUtils::scalarStringValue($integration['status_copy'] ?? '');

            $checks[] = $this->check(
                'integration_' . sanitize_key((string) $key),
                'integration',
                'info',
                $title,
                $statusCopy !== ''
                    ? $statusCopy
                    : __('This integration is available but is not currently distributing Tasty Fonts output.', 'tasty-fonts'),
                [
                    ['label' => __('Status', 'tasty-fonts'), 'value' => $statusLabel !== '' ? $statusLabel : __('Off', 'tasty-fonts')],
                ],
                ['slug' => 'review_integrations', 'label' => __('Review Integrations', 'tasty-fonts')],
                __('Review Integration settings if this connected tool should receive Tasty Fonts output.', 'tasty-fonts'),
                $this->docsUrl('Integrations')
            );
        }

        return $checks;
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
                ? __('The managed library contains font families that Tasty Fonts can deploy.', 'tasty-fonts')
                : __('The managed library is empty, so there are no fonts to deploy yet.', 'tasty-fonts'),
            [
                ['label' => __('Families', 'tasty-fonts'), 'value' => (string) $families],
                ['label' => __('Files', 'tasty-fonts'), 'value' => (string) $files],
            ],
            null,
            $families > 0
                ? __('Use Deploy Fonts to assign library families to sitewide roles and publish them to the front end.', 'tasty-fonts')
                : __('Import Google, Bunny, Adobe, or uploaded fonts before assigning sitewide roles.', 'tasty-fonts'),
            $this->docsUrl('Font-Library')
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
        ?array $action = null,
        string $guidance = '',
        string $helpUrl = '',
        string $helpLabel = ''
    ): array {
        return [
            'id' => $slug,
            'slug' => $slug,
            'group' => $group,
            'severity' => $severity,
            'label' => $title,
            'title' => $title,
            'summary' => $message,
            'message' => $message,
            'guidance' => $guidance,
            'help_url' => $helpUrl,
            'help_label' => $helpLabel !== '' ? $helpLabel : __('Open knowledge base', 'tasty-fonts'),
            'evidence' => $evidence,
            'action' => $action,
            'actions' => $action === null ? [] : [$action],
        ];
    }

    private function docsUrl(string $page): string
    {
        return self::DOCS_BASE_URL . rawurlencode($page);
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
