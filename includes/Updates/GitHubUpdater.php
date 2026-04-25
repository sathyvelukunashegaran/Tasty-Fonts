<?php

declare(strict_types=1);

namespace TastyFonts\Updates;

defined('ABSPATH') || exit;

use TastyFonts\Admin\AdminAccessService;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\TransientKey;
use WP_Error;
use stdClass;

/**
 * @phpstan-type PluginMetadata array<string, string>
 * @phpstan-type NormalizedRelease array{version: string, channel: string, package_url: string, checksum_url: string, body: string, published_at: string}
 * @phpstan-type ReleaseManifest array{
 *   latest_by_type: array<string, NormalizedRelease|null>,
 *   latest_for_channel: array<string, NormalizedRelease|null>
 * }
 * @phpstan-type ChannelOverview array{
 *   selected_channel: string,
 *   installed_version: string,
 *   latest_available: NormalizedRelease|null,
 *   state: string,
 *   can_reinstall: bool
 * }
 * @phpstan-type ReinstallResult array{channel: string, version: string, package_url: string}
 */
final class GitHubUpdater
{
    public const CHANNEL_STABLE = SettingsRepository::UPDATE_CHANNEL_STABLE;
    public const CHANNEL_BETA = SettingsRepository::UPDATE_CHANNEL_BETA;
    public const CHANNEL_NIGHTLY = SettingsRepository::UPDATE_CHANNEL_NIGHTLY;

    private const PLUGIN_SLUG = 'tasty-fonts';
    private const REPOSITORY = 'sathyvelukunashegaran/Tasty-Custom-Fonts';
    private const REPOSITORY_URL = 'https://github.com/' . self::REPOSITORY;
    private const API_RELEASES_URL = 'https://api.github.com/repos/' . self::REPOSITORY . '/releases';
    private const TRANSIENT_RELEASE_MANIFEST = 'tasty_fonts_github_release_manifest_v1';
    private const TRANSIENT_INSTALLED_VERSION = 'tasty_fonts_github_release_version_v1';
    private const TRANSIENT_RELEASE_BACKOFF = 'tasty_fonts_github_release_backoff_v1';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;
    private const INSTALLED_VERSION_TTL = 30 * DAY_IN_SECONDS;
    private const API_BACKOFF_TTL = 900;
    private const REQUEST_TIMEOUT = 20;
    private const PACKAGE_NAME_PATTERN = 'tasty-fonts-%s.zip';
    private const PACKAGE_CHECKSUM_NAME_PATTERN = 'tasty-fonts-%s.zip.sha256';

    /** @var PluginMetadata|null */
    private ?array $pluginMetadata = null;
    private ?string $pluginBasename = null;
    private ?string $installedVersion = null;
    private ?AdminAccessService $adminAccess = null;

    public function __construct(private readonly ?SettingsRepository $settings = null, ?AdminAccessService $adminAccess = null)
    {
        $this->adminAccess = $adminAccess;
    }

    public function registerHooks(): void
    {
        $this->maybeRefreshInstalledVersion();

        add_filter('pre_set_site_transient_update_plugins', [$this, 'filterUpdateTransient']);
        add_filter('plugins_api', [$this, 'filterPluginInformation'], 10, 3);
        add_filter('upgrader_pre_download', [$this, 'filterUpgraderPreDownload'], 10, 4);
        add_action('upgrader_process_complete', [$this, 'handleUpgraderProcessComplete'], 10, 2);
    }

    public function filterUpdateTransient(mixed $transient): mixed
    {
        if (!is_object($transient)) {
            $transient = new stdClass();
        }

        if (!$transient instanceof stdClass) {
            $transient = (object) get_object_vars($transient);
        }

        $release = $this->getLatestReleaseForChannel($this->selectedChannel());

        if ($release === null || !$this->hasNewerVersion($release['version'])) {
            return $transient;
        }

        $transientVars = get_object_vars($transient);
        $response = isset($transientVars['response']) && is_array($transientVars['response'])
            ? $transientVars['response']
            : [];

        if (isset($transientVars['no_update']) && is_array($transientVars['no_update'])) {
            $noUpdate = $transientVars['no_update'];
            unset($noUpdate[$this->pluginBasename()]);
            $transient->no_update = $noUpdate;
        }

        $metadata = $this->getPluginMetadata();
        $response[$this->pluginBasename()] = (object) [
            'id' => self::REPOSITORY_URL,
            'slug' => self::PLUGIN_SLUG,
            'plugin' => $this->pluginBasename(),
            'url' => (string) ($metadata['plugin_uri'] ?? self::REPOSITORY_URL),
            'package' => (string) $release['package_url'],
            'new_version' => (string) $release['version'],
            'requires' => (string) ($metadata['requires'] ?? ''),
            'requires_php' => (string) ($metadata['requires_php'] ?? ''),
            'tested' => (string) ($metadata['tested'] ?? ''),
            'icons' => [],
            'banners' => [],
            'banners_rtl' => [],
            'compatibility' => new stdClass(),
        ];
        $transient->response = $response;

        return $transient;
    }

    public function filterPluginInformation(mixed $result, string $action, mixed $args): mixed
    {
        if ($action !== 'plugin_information' || !is_object($args) || (($args->slug ?? '') !== self::PLUGIN_SLUG)) {
            return $result;
        }

        $release = $this->getLatestReleaseForChannel($this->selectedChannel());

        if ($release === null) {
            return $result;
        }

        $metadata = $this->getPluginMetadata();

        return (object) [
            'name' => (string) ($metadata['name'] ?? 'Tasty Custom Fonts'),
            'slug' => self::PLUGIN_SLUG,
            'version' => $release['version'],
            'author' => (string) ($metadata['author'] ?? ''),
            'homepage' => (string) ($metadata['plugin_uri'] ?? self::REPOSITORY_URL),
            'requires' => (string) ($metadata['requires'] ?? ''),
            'requires_php' => (string) ($metadata['requires_php'] ?? ''),
            'tested' => (string) ($metadata['tested'] ?? ''),
            'last_updated' => $release['published_at'],
            'download_link' => $release['package_url'],
            'external' => true,
            'sections' => [
                'description' => $this->renderSection((string) ($metadata['description'] ?? '')),
                'changelog' => $this->renderSection($release['body']),
            ],
            'current_version' => $this->installedVersion(),
            'installed_version' => $this->installedVersion(),
            'banners' => [],
            'icons' => [],
        ];
    }

    /**
     * @return ChannelOverview
     */
    public function getChannelOverview(?string $channel = null): array
    {
        $channel = $this->resolveChannel($channel);
        $installedVersion = $this->installedVersion();
        $latestRelease = $this->getLatestReleaseForChannel($channel);
        $state = 'unavailable';

        if ($latestRelease !== null) {
            $comparison = version_compare($latestRelease['version'], $installedVersion);

            if ($comparison > 0) {
                $state = 'upgrade';
            } elseif ($comparison < 0) {
                $state = 'rollback';
            } else {
                $state = 'current';
            }
        }

        return [
            'selected_channel' => $channel,
            'installed_version' => $installedVersion,
            'latest_available' => $latestRelease,
            'state' => $state,
            'can_reinstall' => $state === 'rollback',
        ];
    }

    /**
     * @return ReinstallResult|WP_Error
     */
    public function reinstallReleaseForChannel(?string $channel = null): array|WP_Error
    {
        if (!$this->adminAccess()->canCurrentUserAccess()) {
            return new WP_Error(
                'tasty_fonts_update_channel_forbidden',
                __('You do not have permission to reinstall this plugin.', 'tasty-fonts')
            );
        }

        $overview = $this->getChannelOverview($channel);
        $release = $overview['latest_available'];

        if ($release === null) {
            return new WP_Error(
                'tasty_fonts_release_unavailable',
                __('No installable release is available for the selected update channel.', 'tasty-fonts')
            );
        }

        if ($overview['state'] !== 'rollback') {
            return new WP_Error(
                'tasty_fonts_release_reinstall_not_needed',
                __('The selected update channel does not require a rollback reinstall right now.', 'tasty-fonts')
            );
        }

        $packageUrl = trim($release['package_url']);

        if ($packageUrl === '') {
            return new WP_Error(
                'tasty_fonts_release_package_missing',
                __('The selected release does not expose a valid install package.', 'tasty-fonts')
            );
        }

        $this->loadUpgraderDependencies();

        $skin = class_exists('Automatic_Upgrader_Skin') ? new \Automatic_Upgrader_Skin() : null;
        $upgrader = class_exists('Plugin_Upgrader') ? new \Plugin_Upgrader($skin) : null;

        if ($upgrader === null) {
            return new WP_Error(
                'tasty_fonts_upgrader_unavailable',
                __('The WordPress plugin upgrader is unavailable on this site.', 'tasty-fonts')
            );
        }

        $result = $upgrader->install(
            $packageUrl,
            [
                'clear_update_cache' => true,
                'overwrite_package' => true,
            ]
        );

        if (is_wp_error($result)) {
            return $result;
        }

        if ($result !== true) {
            $skinError = $this->extractUpgraderSkinError($skin);

            if ($skinError instanceof WP_Error) {
                return $skinError;
            }

            return new WP_Error(
                'tasty_fonts_release_install_failed',
                __('The selected release could not be reinstalled.', 'tasty-fonts')
            );
        }

        $this->clearReleaseCache();
        set_transient($this->installedVersionTransientKey(), $this->installedVersion(), self::INSTALLED_VERSION_TTL);

        return [
            'channel' => $overview['selected_channel'],
            'version' => $release['version'],
            'package_url' => $packageUrl,
        ];
    }

    private function adminAccess(): AdminAccessService
    {
        if ($this->adminAccess instanceof AdminAccessService) {
            return $this->adminAccess;
        }

        $this->adminAccess = new AdminAccessService($this->settings ?? new SettingsRepository());

        return $this->adminAccess;
    }

    /**
     * @param array<string, mixed> $hookExtra
     */
    public function handleUpgraderProcessComplete(mixed $upgrader, array $hookExtra): void
    {
        if (!in_array(($hookExtra['action'] ?? ''), ['update', 'install'], true) || ($hookExtra['type'] ?? '') !== 'plugin') {
            return;
        }

        $plugins = $hookExtra['plugins'] ?? [];
        $plugin = $this->arrayStringValue($hookExtra, 'plugin');

        if (
            $plugin !== $this->pluginBasename()
            && (!is_array($plugins) || !in_array($this->pluginBasename(), $plugins, true))
        ) {
            return;
        }

        $this->clearReleaseCache();
        set_transient($this->installedVersionTransientKey(), $this->installedVersion(), self::INSTALLED_VERSION_TTL);
    }

    /**
     * @param array<string, mixed> $hookExtra
     */
    public function filterUpgraderPreDownload(mixed $reply, string $package, mixed $upgrader, array $hookExtra = []): mixed
    {
        if ($reply !== false) {
            return $reply;
        }

        $release = $this->findReleaseByPackageUrl($package);

        if ($release === null) {
            return $reply;
        }

        $checksumUrl = trim($release['checksum_url']);

        if ($checksumUrl === '') {
            return new WP_Error(
                'tasty_fonts_release_checksum_missing',
                __('The selected release cannot be verified because its published checksum is missing.', 'tasty-fonts')
            );
        }

        $expectedChecksum = $this->fetchExpectedChecksum($checksumUrl);

        if ($expectedChecksum === '') {
            return new WP_Error(
                'tasty_fonts_release_checksum_unavailable',
                __('The selected release checksum could not be retrieved for verification.', 'tasty-fonts')
            );
        }

        $downloadedPackage = download_url($package, self::REQUEST_TIMEOUT, false);

        if (is_wp_error($downloadedPackage)) {
            return $downloadedPackage;
        }

        $actualChecksum = is_readable($downloadedPackage)
            ? strtolower((string) hash_file('sha256', $downloadedPackage))
            : '';

        if ($actualChecksum !== $expectedChecksum) {
            if ($downloadedPackage !== '' && file_exists($downloadedPackage)) {
                unlink($downloadedPackage);
            }

            return new WP_Error(
                'tasty_fonts_release_checksum_mismatch',
                __('The downloaded release package failed checksum verification.', 'tasty-fonts')
            );
        }

        return $downloadedPackage;
    }

    private function maybeRefreshInstalledVersion(): void
    {
        $cachedVersion = get_transient($this->installedVersionTransientKey());
        $installedVersion = $this->installedVersion();

        if (!is_string($cachedVersion) || $cachedVersion !== $installedVersion) {
            $this->clearReleaseCache();
            set_transient($this->installedVersionTransientKey(), $installedVersion, self::INSTALLED_VERSION_TTL);
        }
    }

    private function clearReleaseCache(): void
    {
        delete_transient($this->releaseManifestTransientKey());
        delete_transient($this->releaseBackoffTransientKey());
    }

    /**
     * @return NormalizedRelease|null
     */
    private function getLatestReleaseForChannel(string $channel): ?array
    {
        $manifest = $this->getReleaseManifest();

        if (!is_array($manifest)) {
            return null;
        }

        $latestForChannel = $manifest['latest_for_channel'];

        return $latestForChannel[$channel] ?? null;
    }

    /**
     * @return ReleaseManifest|null
     */
    private function getReleaseManifest(): ?array
    {
        if ($this->isApiBackoffActive()) {
            return null;
        }

        $cached = get_transient($this->releaseManifestTransientKey());

        if (is_array($cached)) {
            $normalizedCached = $this->normalizeReleaseManifest($cached);

            if ($normalizedCached !== null) {
                return $normalizedCached;
            }
        }

        $response = $this->performGitHubGet(self::API_RELEASES_URL, 'application/vnd.github+json');

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            $this->cacheApiBackoffFromResponse($response);
            return null;
        }

        delete_transient($this->releaseBackoffTransientKey());

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);

        if (!is_array($payload)) {
            return null;
        }

        $manifest = [
            'latest_by_type' => [
                self::CHANNEL_STABLE => null,
                self::CHANNEL_BETA => null,
                self::CHANNEL_NIGHTLY => null,
            ],
            'latest_for_channel' => [
                self::CHANNEL_STABLE => null,
                self::CHANNEL_BETA => null,
                self::CHANNEL_NIGHTLY => null,
            ],
        ];

        foreach ($payload as $release) {
            if (!is_array($release) || ($release['draft'] ?? false)) {
                continue;
            }

            $normalized = $this->normalizeRelease(FontUtils::normalizeStringKeyedMap($release));

            if ($normalized === null) {
                continue;
            }

            $channel = $normalized['channel'];
            $current = $manifest['latest_by_type'][$channel];
            $manifest['latest_by_type'][$channel] = $this->pickNewerRelease($current, $normalized);
        }

        $latestByType = $manifest['latest_by_type'];
        $stable = $latestByType[self::CHANNEL_STABLE];
        $beta = $latestByType[self::CHANNEL_BETA];
        $nightly = $latestByType[self::CHANNEL_NIGHTLY];

        $manifest['latest_for_channel'][self::CHANNEL_STABLE] = $stable;
        $manifest['latest_for_channel'][self::CHANNEL_BETA] = $this->pickNewerRelease($stable, $beta);
        $manifest['latest_for_channel'][self::CHANNEL_NIGHTLY] = $this->pickNewestRelease([$stable, $beta, $nightly]);

        set_transient($this->releaseManifestTransientKey(), $manifest, self::CACHE_TTL);

        return $manifest;
    }

    /**
     * @param array<string, mixed> $release
     * @return NormalizedRelease|null
     */
    private function normalizeRelease(array $release): ?array
    {
        $version = $this->normalizeVersion($this->arrayStringValue($release, 'tag_name'));
        $channel = $this->classifyReleaseChannel($version);
        $packageUrl = $this->findPackageUrl($version, $release['assets'] ?? []);
        $checksumUrl = $this->findChecksumUrl($version, $release['assets'] ?? []);

        return $this->buildNormalizedRelease(
            $version,
            $channel,
            $packageUrl,
            $checksumUrl,
            $release['body'] ?? '',
            $release['published_at'] ?? ''
        );
    }

    private function findPackageUrl(string $version, mixed $assets): string
    {
        return $this->findAssetDownloadUrlByName($assets, sprintf(self::PACKAGE_NAME_PATTERN, $version));
    }

    private function findChecksumUrl(string $version, mixed $assets): string
    {
        return $this->findAssetDownloadUrlByName($assets, sprintf(self::PACKAGE_CHECKSUM_NAME_PATTERN, $version));
    }

    private function findAssetDownloadUrlByName(mixed $assets, string $expectedName): string
    {
        if (!is_array($assets)) {
            return '';
        }

        $expectedName = strtolower($expectedName);

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $name = strtolower($this->arrayStringValue($asset, 'name'));
            $url = trim($this->arrayStringValue($asset, 'browser_download_url'));
            $state = strtolower($this->arrayStringValue($asset, 'state', 'uploaded'));

            if ($name !== $expectedName || $url === '' || $state !== 'uploaded') {
                continue;
            }

            return $url;
        }

        return '';
    }

    /**
     * @return NormalizedRelease|null
     */
    private function findReleaseByPackageUrl(string $packageUrl): ?array
    {
        $packageUrl = trim($packageUrl);

        if ($packageUrl === '') {
            return null;
        }

        $manifest = $this->getReleaseManifest();

        if (!is_array($manifest)) {
            return null;
        }

        foreach ($manifest['latest_by_type'] as $release) {
            if ($release !== null && $release['package_url'] === $packageUrl) {
                return $release;
            }
        }

        foreach ($manifest['latest_for_channel'] as $release) {
            if ($release !== null && $release['package_url'] === $packageUrl) {
                return $release;
            }
        }

        return null;
    }

    private function fetchExpectedChecksum(string $checksumUrl): string
    {
        $response = $this->performGitHubGet($checksumUrl, 'text/plain');

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }

        $body = trim((string) wp_remote_retrieve_body($response));

        if ($body === '' || preg_match('/\b([a-f0-9]{64})\b/i', $body, $matches) !== 1) {
            return '';
        }

        return strtolower((string) $matches[1]);
    }

    private function hasNewerVersion(string $version): bool
    {
        return $version !== '' && version_compare($version, $this->installedVersion(), '>');
    }

    private function classifyReleaseChannel(string $version): string
    {
        if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version) === 1) {
            return self::CHANNEL_STABLE;
        }

        if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+-beta\.[0-9]+$/', $version) === 1) {
            return self::CHANNEL_BETA;
        }

        if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+-dev\.[0-9]+$/', $version) === 1) {
            return self::CHANNEL_NIGHTLY;
        }

        return '';
    }

    private function normalizeVersion(string $version): string
    {
        $version = trim($version);

        if ($version === '') {
            return '';
        }

        return preg_replace('/^v(?=\\d)/i', '', $version) ?? '';
    }

    private function renderSection(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            return '';
        }

        return '<p>' . nl2br(esc_html($content)) . '</p>';
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function performGitHubGet(string $url, string $accept): array|WP_Error
    {
        return wp_remote_get(
            $url,
            [
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => $this->buildGitHubRequestHeaders($accept),
            ]
        );
    }

    /**
     * @return array<string, string>
     */
    private function buildGitHubRequestHeaders(string $accept): array
    {
        $headers = [
            'Accept' => $accept,
            'User-Agent' => 'Tasty-Custom-Fonts/' . TASTY_FONTS_VERSION,
        ];
        $token = trim(FontUtils::scalarStringValue(apply_filters('tasty_fonts_github_api_token', '')));

        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * @param mixed $manifest
     * @return ReleaseManifest|null
     */
    private function normalizeReleaseManifest(mixed $manifest): ?array
    {
        if (!is_array($manifest)) {
            return null;
        }

        $latestByType = $this->normalizeReleaseMap($manifest['latest_by_type'] ?? null);
        $latestForChannel = $this->normalizeReleaseMap($manifest['latest_for_channel'] ?? null);

        if ($latestByType === null || $latestForChannel === null) {
            return null;
        }

        return [
            'latest_by_type' => $latestByType,
            'latest_for_channel' => $latestForChannel,
        ];
    }

    /**
     * @param mixed $releases
     * @return array<string, NormalizedRelease|null>|null
     */
    private function normalizeReleaseMap(mixed $releases): ?array
    {
        if (!is_array($releases)) {
            return null;
        }

        $normalized = [];

        foreach ([self::CHANNEL_STABLE, self::CHANNEL_BETA, self::CHANNEL_NIGHTLY] as $channel) {
            $normalized[$channel] = $this->normalizeCachedRelease($releases[$channel] ?? null);
        }

        return $normalized;
    }

    /**
     * @param mixed $release
     * @return NormalizedRelease|null
     */
    private function normalizeCachedRelease(mixed $release): ?array
    {
        if (!is_array($release)) {
            return null;
        }

        return $this->buildNormalizedRelease(
            trim($this->arrayStringValue($release, 'version')),
            trim($this->arrayStringValue($release, 'channel')),
            trim($this->arrayStringValue($release, 'package_url')),
            trim($this->arrayStringValue($release, 'checksum_url')),
            $release['body'] ?? '',
            $release['published_at'] ?? ''
        );
    }

    /**
     * @return NormalizedRelease|null
     */
    private function buildNormalizedRelease(
        string $version,
        string $channel,
        string $packageUrl,
        string $checksumUrl,
        mixed $body,
        mixed $publishedAt
    ): ?array {
        if ($version === '' || $channel === '' || $packageUrl === '' || $checksumUrl === '') {
            return null;
        }

        return [
            'version' => $version,
            'channel' => $channel,
            'package_url' => $packageUrl,
            'checksum_url' => $checksumUrl,
            'body' => $this->scalarStringValue($body),
            'published_at' => $this->scalarStringValue($publishedAt),
        ];
    }

    /**
     * @param array<string, mixed>|WP_Error $response
     */
    private function normalizeResponseHeader(array|WP_Error $response, string $header): string
    {
        $value = wp_remote_retrieve_header($response, $header);

        return is_string($value) ? trim($value) : '';
    }

    private function isApiBackoffActive(): bool
    {
        return get_transient($this->releaseBackoffTransientKey()) !== false;
    }

    /**
     * @param array<string, mixed>|WP_Error $response
     */
    private function cacheApiBackoffFromResponse(array|WP_Error $response): void
    {
        if (is_wp_error($response)) {
            return;
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $remaining = $this->normalizeResponseHeader($response, 'x-ratelimit-remaining');

        if (!in_array($statusCode, [403, 429], true) && $remaining !== '0') {
            return;
        }

        $retryAfter = (int) $this->normalizeResponseHeader($response, 'retry-after');
        $ttl = $retryAfter > 0 ? max(60, $retryAfter) : self::API_BACKOFF_TTL;

        set_transient(
            $this->releaseBackoffTransientKey(),
            [
                'status_code' => $statusCode,
                'remaining' => $remaining,
            ],
            $ttl
        );
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function arrayStringValue(array $values, string $key, string $default = ''): string
    {
        return $this->scalarStringValue($values[$key] ?? null, $default);
    }

    private function scalarStringValue(mixed $value, string $default = ''): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @return PluginMetadata
     */
    private function getPluginMetadata(): array
    {
        if (is_array($this->pluginMetadata)) {
            return $this->pluginMetadata;
        }

        if (!is_readable(TASTY_FONTS_FILE)) {
            $this->pluginMetadata = [];

            return $this->pluginMetadata;
        }

        $contents = file_get_contents(TASTY_FONTS_FILE);

        if (!is_string($contents) || $contents === '') {
            $this->pluginMetadata = [];

            return $this->pluginMetadata;
        }

        $fields = [
            'Plugin Name' => 'name',
            'Plugin URI' => 'plugin_uri',
            'Description' => 'description',
            'Version' => 'version',
            'Author' => 'author',
            'Requires at least' => 'requires',
            'Requires PHP' => 'requires_php',
            'Tested up to' => 'tested',
        ];

        $metadata = [];

        foreach ($fields as $header => $key) {
            if (preg_match('/^' . preg_quote($header, '/') . ':\s*(.+)$/mi', $contents, $matches) === 1) {
                $metadata[$key] = trim((string) $matches[1]);
            }
        }

        $this->pluginMetadata = $metadata;

        return $this->pluginMetadata;
    }

    private function pluginBasename(): string
    {
        if (is_string($this->pluginBasename)) {
            return $this->pluginBasename;
        }

        $this->pluginBasename = plugin_basename(TASTY_FONTS_FILE);

        return $this->pluginBasename;
    }

    private function resolveChannel(?string $channel): string
    {
        $channel = strtolower(trim((string) $channel));

        return in_array($channel, [self::CHANNEL_STABLE, self::CHANNEL_BETA, self::CHANNEL_NIGHTLY], true)
            ? $channel
            : self::CHANNEL_STABLE;
    }

    private function selectedChannel(): string
    {
        if ($this->settings instanceof SettingsRepository) {
            return $this->resolveChannel($this->settings->getUpdateChannel());
        }

        return self::CHANNEL_STABLE;
    }

    /**
     * @param list<NormalizedRelease|null> $releases
     * @return NormalizedRelease|null
     */
    private function pickNewestRelease(array $releases): ?array
    {
        $winner = null;

        foreach ($releases as $release) {
            if (!is_array($release)) {
                continue;
            }

            $winner = $this->pickNewerRelease($winner, $release);
        }

        return $winner;
    }

    /**
     * @param NormalizedRelease|null $left
     * @param NormalizedRelease|null $right
     * @return NormalizedRelease|null
     */
    private function pickNewerRelease(?array $left, ?array $right): ?array
    {
        if ($left === null) {
            return $right;
        }

        if ($right === null) {
            return $left;
        }

        return version_compare($right['version'], $left['version'], '>')
            ? $right
            : $left;
    }

    private function loadUpgraderDependencies(): void
    {
        if (class_exists('Plugin_Upgrader')) {
            return;
        }

        foreach (
            [
                ABSPATH . 'wp-admin/includes/file.php',
                ABSPATH . 'wp-admin/includes/misc.php',
                ABSPATH . 'wp-admin/includes/class-wp-upgrader.php',
            ] as $path
        ) {
            if (is_readable($path)) {
                require_once $path;
            }
        }
    }

    private function extractUpgraderSkinError(mixed $skin): ?WP_Error
    {
        if (!is_object($skin)) {
            return null;
        }

        foreach (['get_errors', 'get_error'] as $method) {
            if (!method_exists($skin, $method)) {
                continue;
            }

            $error = $skin->{$method}();

            if ($error instanceof WP_Error) {
                return $error;
            }
        }

        return null;
    }

    private function installedVersion(): string
    {
        if (is_string($this->installedVersion)) {
            return $this->installedVersion;
        }

        $metadata = $this->getPluginMetadata();
        $this->installedVersion = (string) ($metadata['version'] ?? TASTY_FONTS_VERSION);

        return $this->installedVersion;
    }

    private function releaseManifestTransientKey(): string
    {
        return TransientKey::forSite(self::TRANSIENT_RELEASE_MANIFEST);
    }

    private function installedVersionTransientKey(): string
    {
        return TransientKey::forSite(self::TRANSIENT_INSTALLED_VERSION);
    }

    private function releaseBackoffTransientKey(): string
    {
        return TransientKey::forSite(self::TRANSIENT_RELEASE_BACKOFF);
    }
}
