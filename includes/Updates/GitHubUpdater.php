<?php

declare(strict_types=1);

namespace TastyFonts\Updates;

defined('ABSPATH') || exit;

use stdClass;

final class GitHubUpdater
{
    private const PLUGIN_SLUG = 'etch-fonts';
    private const REPOSITORY = 'sathyvelukunashegaran/Tasty-Custom-Fonts';
    private const REPOSITORY_URL = 'https://github.com/' . self::REPOSITORY;
    private const API_RELEASES_URL = 'https://api.github.com/repos/' . self::REPOSITORY . '/releases';
    private const TRANSIENT_RELEASE = 'tasty_fonts_github_release_v1';
    private const TRANSIENT_INSTALLED_VERSION = 'tasty_fonts_github_release_version_v1';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;
    private const INSTALLED_VERSION_TTL = 30 * DAY_IN_SECONDS;
    private const REQUEST_TIMEOUT = 20;
    private const PACKAGE_NAME_PATTERN = 'tasty-fonts-%s.zip';

    private ?array $pluginMetadata = null;

    public function registerHooks(): void
    {
        $this->maybeRefreshInstalledVersion();

        add_filter('pre_set_site_transient_update_plugins', [$this, 'filterUpdateTransient']);
        add_filter('plugins_api', [$this, 'filterPluginInformation'], 10, 3);
        add_action('upgrader_process_complete', [$this, 'handleUpgraderProcessComplete'], 10, 2);
    }

    public function filterUpdateTransient(mixed $transient): mixed
    {
        if (!is_object($transient)) {
            $transient = new stdClass();
        }

        $release = $this->getLatestStableRelease();

        if ($release === null || !$this->hasNewerVersion((string) ($release['version'] ?? ''))) {
            return $transient;
        }

        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }

        if (isset($transient->no_update) && is_array($transient->no_update)) {
            unset($transient->no_update[$this->pluginBasename()]);
        }

        $metadata = $this->getPluginMetadata();
        $transient->response[$this->pluginBasename()] = (object) [
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

        return $transient;
    }

    public function filterPluginInformation(mixed $result, string $action, mixed $args): mixed
    {
        if ($action !== 'plugin_information' || !is_object($args) || (($args->slug ?? '') !== self::PLUGIN_SLUG)) {
            return $result;
        }

        $release = $this->getLatestStableRelease();

        if ($release === null) {
            return $result;
        }

        $metadata = $this->getPluginMetadata();

        return (object) [
            'name' => (string) ($metadata['name'] ?? 'Tasty Custom Fonts'),
            'slug' => self::PLUGIN_SLUG,
            'version' => (string) ($release['version'] ?? TASTY_FONTS_VERSION),
            'author' => (string) ($metadata['author'] ?? ''),
            'homepage' => (string) ($metadata['plugin_uri'] ?? self::REPOSITORY_URL),
            'requires' => (string) ($metadata['requires'] ?? ''),
            'requires_php' => (string) ($metadata['requires_php'] ?? ''),
            'tested' => (string) ($metadata['tested'] ?? ''),
            'last_updated' => (string) ($release['published_at'] ?? ''),
            'download_link' => (string) ($release['package_url'] ?? ''),
            'external' => true,
            'sections' => [
                'description' => $this->renderSection((string) ($metadata['description'] ?? '')),
                'changelog' => $this->renderSection((string) ($release['body'] ?? '')),
            ],
            'current_version' => $this->installedVersion(),
            'installed_version' => $this->installedVersion(),
            'banners' => [],
            'icons' => [],
        ];
    }

    public function handleUpgraderProcessComplete(mixed $upgrader, array $hookExtra): void
    {
        if (($hookExtra['action'] ?? '') !== 'update' || ($hookExtra['type'] ?? '') !== 'plugin') {
            return;
        }

        $plugins = $hookExtra['plugins'] ?? [];
        $plugin = (string) ($hookExtra['plugin'] ?? '');

        if (
            $plugin !== $this->pluginBasename()
            && (!is_array($plugins) || !in_array($this->pluginBasename(), $plugins, true))
        ) {
            return;
        }

        $this->clearReleaseCache();
        set_transient(self::TRANSIENT_INSTALLED_VERSION, $this->installedVersion(), self::INSTALLED_VERSION_TTL);
    }

    private function maybeRefreshInstalledVersion(): void
    {
        $cachedVersion = get_transient(self::TRANSIENT_INSTALLED_VERSION);
        $installedVersion = $this->installedVersion();

        if (!is_string($cachedVersion) || $cachedVersion !== $installedVersion) {
            $this->clearReleaseCache();
            set_transient(self::TRANSIENT_INSTALLED_VERSION, $installedVersion, self::INSTALLED_VERSION_TTL);
        }
    }

    private function clearReleaseCache(): void
    {
        delete_transient(self::TRANSIENT_RELEASE);
    }

    private function getLatestStableRelease(): ?array
    {
        $cached = get_transient(self::TRANSIENT_RELEASE);

        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(
            self::API_RELEASES_URL,
            [
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'Tasty-Custom-Fonts/' . TASTY_FONTS_VERSION,
                ],
            ]
        );

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);

        if (!is_array($payload)) {
            return null;
        }

        foreach ($payload as $release) {
            if (!is_array($release) || ($release['draft'] ?? false) || ($release['prerelease'] ?? false)) {
                continue;
            }

            $normalized = $this->normalizeRelease($release);

            if ($normalized !== null) {
                set_transient(self::TRANSIENT_RELEASE, $normalized, self::CACHE_TTL);
            }

            return $normalized;
        }

        return null;
    }

    private function normalizeRelease(array $release): ?array
    {
        $version = $this->normalizeVersion((string) ($release['tag_name'] ?? ''));
        $packageUrl = $this->findPackageUrl($version, $release['assets'] ?? []);

        if ($version === '' || $packageUrl === '') {
            return null;
        }

        return [
            'version' => $version,
            'package_url' => $packageUrl,
            'body' => (string) ($release['body'] ?? ''),
            'published_at' => (string) ($release['published_at'] ?? ''),
        ];
    }

    private function findPackageUrl(string $version, mixed $assets): string
    {
        if (!is_array($assets)) {
            return '';
        }

        $expectedName = strtolower(sprintf(self::PACKAGE_NAME_PATTERN, $version));

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $name = strtolower((string) ($asset['name'] ?? ''));
            $url = trim((string) ($asset['browser_download_url'] ?? ''));
            $state = strtolower((string) ($asset['state'] ?? 'uploaded'));

            if ($name !== $expectedName || $url === '' || $state !== 'uploaded') {
                continue;
            }

            return $url;
        }

        return '';
    }

    private function hasNewerVersion(string $version): bool
    {
        return $version !== '' && version_compare($version, $this->installedVersion(), '>');
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
        return plugin_basename(TASTY_FONTS_FILE);
    }

    private function installedVersion(): string
    {
        $metadata = $this->getPluginMetadata();

        return (string) ($metadata['version'] ?? TASTY_FONTS_VERSION);
    }
}
