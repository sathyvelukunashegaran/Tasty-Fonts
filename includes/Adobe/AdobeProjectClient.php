<?php

declare(strict_types=1);

namespace TastyFonts\Adobe;

defined('ABSPATH') || exit;

use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\TransientKey;
use WP_Error;

final class AdobeProjectClient
{
    public const TRANSIENT_PREFIX = 'tasty_fonts_adobe_project_v1_';
    private const CACHE_TTL = 12 * HOUR_IN_SECONDS;
    private const REQUEST_TIMEOUT = 20;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly AdobeCssParser $parser
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settings->isAdobeEnabled();
    }

    public function hasProjectId(): bool
    {
        return $this->getProjectId() !== '';
    }

    public function canEnqueue(): bool
    {
        $status = $this->getProjectStatus();

        return $this->isEnabled() && $this->hasProjectId() && ($status['state'] ?? 'empty') === 'valid';
    }

    public function getProjectId(): string
    {
        return $this->settings->getAdobeProjectId();
    }

    public function getProjectStatus(): array
    {
        return $this->settings->getAdobeProjectStatus();
    }

    public function validateProject(string $projectId): array
    {
        $projectId = $this->sanitizeProjectId($projectId);

        if ($projectId === '') {
            return [
                'state' => 'empty',
                'message' => '',
            ];
        }

        $result = $this->fetchProjectData($projectId, true);

        if (is_wp_error($result)) {
            return [
                'state' => $this->errorState($result),
                'message' => $result->get_error_message(),
            ];
        }

        $familyCount = count((array) ($result['families'] ?? []));

        return [
            'state' => 'valid',
            'message' => sprintf(
                __('Adobe Fonts project connected. %1$d %2$s detected.', 'tasty-fonts'),
                $familyCount,
                $familyCount === 1 ? __('family', 'tasty-fonts') : __('families', 'tasty-fonts')
            ),
        ];
    }

    public function getProjectFamilies(string $projectId): array
    {
        $projectId = $this->sanitizeProjectId($projectId);

        if ($projectId === '') {
            return [];
        }

        $result = $this->fetchProjectData($projectId);

        if (is_wp_error($result)) {
            return [];
        }

        return is_array($result['families'] ?? null) ? array_values($result['families']) : [];
    }

    public function getConfiguredFamilies(): array
    {
        if (!$this->hasProjectId()) {
            return [];
        }

        return $this->getProjectFamilies($this->getProjectId());
    }

    public function getStylesheetUrl(string $projectId): string
    {
        $projectId = $this->sanitizeProjectId($projectId);

        return $projectId === ''
            ? ''
            : 'https://use.typekit.net/' . rawurlencode($projectId) . '.css';
    }

    public function getEnqueueVersion(): string
    {
        $status = $this->getProjectStatus();
        $checkedAt = (int) ($status['checked_at'] ?? 0);

        return hash(
            'crc32b',
            implode('|', [$this->getProjectId(), (string) $checkedAt, $this->isEnabled() ? 'enabled' : 'disabled'])
        );
    }

    public function clearProjectCache(string $projectId): void
    {
        $projectId = $this->sanitizeProjectId($projectId);

        if ($projectId === '') {
            return;
        }

        delete_transient($this->transientKey($projectId));
    }

    private function fetchProjectData(string $projectId, bool $forceRefresh = false): array|WP_Error
    {
        $projectId = $this->sanitizeProjectId($projectId);

        if ($projectId === '') {
            return new WP_Error(
                'tasty_fonts_adobe_missing_project',
                __('Save an Adobe Fonts web project ID before using Adobe support.', 'tasty-fonts')
            );
        }

        $cacheKey = $this->transientKey($projectId);
        $cached = !$forceRefresh ? get_transient($cacheKey) : false;

        if (is_array($cached) && !empty($cached['families'])) {
            return $cached;
        }

        $response = $this->remoteGet(
            $this->getStylesheetUrl($projectId),
            [
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => [
                    'Accept' => 'text/css,*/*;q=0.1',
                    'User-Agent' => FontUtils::MODERN_USER_AGENT,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error(
                'tasty_fonts_adobe_project_unreachable',
                __('The Adobe Fonts project could not be validated right now. Try again in a moment.', 'tasty-fonts')
            );
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if (in_array($status, [401, 403, 404], true)) {
            return new WP_Error(
                'tasty_fonts_adobe_project_invalid',
                __('Adobe rejected that web project ID. Check the project ID and domain settings in Adobe Fonts.', 'tasty-fonts')
            );
        }

        if ($status !== 200) {
            return new WP_Error(
                'tasty_fonts_adobe_project_unknown',
                sprintf(__('Adobe Fonts returned an unexpected status (%d). Try again later.', 'tasty-fonts'), $status)
            );
        }

        $css = wp_remote_retrieve_body($response);

        if (trim($css) === '') {
            return new WP_Error(
                'tasty_fonts_adobe_project_invalid',
                __('Adobe Fonts returned an empty stylesheet for that project.', 'tasty-fonts')
            );
        }

        $families = $this->parser->parseFamilies($css);

        if ($families === []) {
            return new WP_Error(
                'tasty_fonts_adobe_project_invalid',
                __('No usable font families were detected in that Adobe Fonts project.', 'tasty-fonts')
            );
        }

        $result = [
            'project_id' => $projectId,
            'stylesheet_url' => $this->getStylesheetUrl($projectId),
            'families' => $families,
            'fetched_at' => time(),
        ];

        set_transient($cacheKey, $result, self::CACHE_TTL);

        return $result;
    }

    private function sanitizeProjectId(string $projectId): string
    {
        $projectId = strtolower(trim($projectId));
        $projectId = preg_replace('/[^a-z0-9]+/', '', $projectId) ?? '';

        return trim($projectId);
    }

    private function remoteGet(string $url, array $args = []): mixed
    {
        $filteredArgs = apply_filters('tasty_fonts_http_request_args', $args, $url);

        return wp_remote_get($url, is_array($filteredArgs) ? $filteredArgs : $args);
    }

    private function transientKey(string $projectId): string
    {
        return TransientKey::forSite(self::TRANSIENT_PREFIX . md5($projectId));
    }

    private function errorState(WP_Error $error): string
    {
        return match ($error->get_error_code()) {
            'tasty_fonts_adobe_project_invalid' => 'invalid',
            default => 'unknown',
        };
    }
}
