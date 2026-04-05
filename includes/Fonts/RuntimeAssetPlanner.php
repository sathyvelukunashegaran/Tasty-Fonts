<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;

final class RuntimeAssetPlanner
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly SettingsRepository $settings,
        private readonly GoogleFontsClient $google,
        private readonly BunnyFontsClient $bunny,
        private readonly AdobeProjectClient $adobe
    ) {
    }

    public function getRuntimeFamilies(): array
    {
        return $this->filterFamiliesByPublishState($this->catalog->getCatalog(), false);
    }

    public function getPreviewFamilies(): array
    {
        return $this->catalog->getCatalog();
    }

    public function getLocalRuntimeCatalog(): array
    {
        return $this->buildSelfHostedCatalog($this->getRuntimeFamilies());
    }

    public function getLocalPreviewCatalog(): array
    {
        return $this->buildSelfHostedCatalog($this->getPreviewFamilies());
    }

    public function getExternalStylesheets(): array
    {
        return $this->buildExternalStylesheets($this->getRuntimeFamilies());
    }

    public function getAdminPreviewStylesheets(): array
    {
        return $this->buildExternalStylesheets($this->getPreviewFamilies());
    }

    public function getEditorFontFamilies(): array
    {
        $fontFamilies = [];

        foreach ($this->getRuntimeFamilies() as $family) {
            $familyName = (string) ($family['family'] ?? '');

            if ($familyName === '') {
                continue;
            }

            $fontFamilies[$familyName] = [
                'name' => $familyName,
                'slug' => (string) ($family['slug'] ?? FontUtils::slugify($familyName)),
                'fontFamily' => FontUtils::buildFontStack(
                    $familyName,
                    $this->settings->getFamilyFallback($familyName, 'sans-serif')
                ),
            ];
        }

        return array_values($fontFamilies);
    }

    public function getPrimaryFontPreloadUrls(): array
    {
        $settings = $this->settings->getSettings();

        if (empty($settings['preload_primary_fonts']) || empty($settings['auto_apply_roles'])) {
            return [];
        }

        $catalog = $this->getLocalRuntimeCatalog();
        $roles = $this->settings->getAppliedRoles($catalog);
        $urls = [];

        foreach (
            [
                ['family' => (string) ($roles['heading'] ?? ''), 'weight' => 700],
                ['family' => (string) ($roles['body'] ?? ''), 'weight' => 400],
            ] as $target
        ) {
            $face = $this->findBestPreloadFace($catalog, $target['family'], (int) $target['weight']);

            if ($face === null) {
                continue;
            }

            $url = $this->getSameOriginWoff2Url($face);

            if ($url === '') {
                continue;
            }

            $urls[$url] = $url;
        }

        return array_values($urls);
    }

    public function getPreconnectOrigins(): array
    {
        $settings = $this->settings->getSettings();

        if (empty($settings['remote_connection_hints'])) {
            return [];
        }

        $origins = [];

        foreach ($this->getRuntimeFamilies() as $family) {
            $activeDelivery = is_array($family['active_delivery'] ?? null) ? $family['active_delivery'] : [];
            $provider = strtolower(trim((string) ($activeDelivery['provider'] ?? '')));
            $type = strtolower(trim((string) ($activeDelivery['type'] ?? '')));

            if ($type === 'self_hosted') {
                continue;
            }

            $origin = match ($provider . ':' . $type) {
                'google:cdn' => 'https://fonts.googleapis.com',
                'bunny:cdn' => 'https://fonts.bunny.net',
                'adobe:adobe_hosted' => 'https://use.typekit.net',
                default => '',
            };

            if ($origin !== '') {
                $origins[$origin] = $origin;
            }
        }

        return array_values($origins);
    }

    private function filterFamiliesByPublishState(array $families, bool $includeLibraryOnly): array
    {
        $filtered = [];

        foreach ($families as $key => $family) {
            if (!is_array($family)) {
                continue;
            }

            $publishState = (string) ($family['publish_state'] ?? 'published');

            if (!$includeLibraryOnly && $publishState === 'library_only') {
                continue;
            }

            $filtered[$key] = $family;
        }

        return $filtered;
    }

    private function buildSelfHostedCatalog(array $families): array
    {
        $catalog = [];

        foreach ($families as $key => $family) {
            if (!is_array($family)) {
                continue;
            }

            $activeDelivery = is_array($family['active_delivery'] ?? null) ? $family['active_delivery'] : [];

            if (!$this->isSelfHostedDelivery($activeDelivery)) {
                continue;
            }

            $catalog[$key] = [
                'family' => (string) ($family['family'] ?? ''),
                'slug' => (string) ($family['slug'] ?? ''),
                'publish_state' => (string) ($family['publish_state'] ?? 'published'),
                'active_delivery_id' => (string) ($family['active_delivery_id'] ?? ''),
                'active_delivery' => $activeDelivery,
                'available_deliveries' => (array) ($family['available_deliveries'] ?? []),
                'delivery_badges' => (array) ($family['delivery_badges'] ?? []),
                'delivery_filter_tokens' => (array) ($family['delivery_filter_tokens'] ?? []),
                'sources' => [$activeDelivery['provider'] ?? 'local'],
                'faces' => is_array($activeDelivery['faces'] ?? null) ? $activeDelivery['faces'] : [],
            ];
        }

        return $catalog;
    }

    private function buildExternalStylesheets(array $families): array
    {
        $stylesheets = [];

        foreach ($families as $family) {
            if (!is_array($family)) {
                continue;
            }

            $activeDelivery = is_array($family['active_delivery'] ?? null) ? $family['active_delivery'] : [];
            $stylesheet = $this->buildStylesheetDescriptor(
                (string) ($family['family'] ?? ''),
                (string) ($family['slug'] ?? ''),
                $activeDelivery
            );

            if ($stylesheet === null) {
                continue;
            }

            $stylesheets[$stylesheet['url']] = $stylesheet;
        }

        return array_values($stylesheets);
    }

    private function buildStylesheetDescriptor(string $familyName, string $familySlug, array $delivery): ?array
    {
        $provider = strtolower(trim((string) ($delivery['provider'] ?? '')));
        $type = strtolower(trim((string) ($delivery['type'] ?? '')));

        if ($provider === '' || $type === '' || $type === 'self_hosted') {
            return null;
        }

        $url = match ($provider . ':' . $type) {
            'google:cdn' => $this->google->buildCssUrl($familyName, (array) ($delivery['variants'] ?? []), $this->effectiveFontDisplay($familyName)),
            'bunny:cdn' => $this->bunny->buildCssUrl($familyName, (array) ($delivery['variants'] ?? []), $this->effectiveFontDisplay($familyName)),
            'adobe:adobe_hosted' => $this->adobeStylesheetUrl($delivery),
            default => '',
        };

        if ($url === '') {
            return null;
        }

        return [
            'handle' => 'tasty-fonts-' . FontUtils::slugify($provider . '-' . $familySlug . '-' . $type),
            'url' => $url,
            'provider' => $provider,
            'type' => $type,
        ];
    }

    private function adobeStylesheetUrl(array $delivery): string
    {
        $projectId = sanitize_text_field((string) (($delivery['meta']['project_id'] ?? '') ?: $this->adobe->getProjectId()));

        return $projectId === '' ? '' : $this->adobe->getStylesheetUrl($projectId);
    }

    private function effectiveFontDisplay(string $familyName): string
    {
        $settings = $this->settings->getSettings();
        $saved = $this->settings->getFamilyFontDisplay($familyName);

        return $saved !== '' ? $saved : (string) ($settings['font_display'] ?? 'optional');
    }

    private function isSelfHostedDelivery(array $delivery): bool
    {
        return strtolower(trim((string) ($delivery['type'] ?? ''))) === 'self_hosted';
    }

    private function findBestPreloadFace(array $catalog, string $familyName, int $targetWeight): ?array
    {
        $family = $this->findCatalogFamily($catalog, $familyName);

        if ($family === null) {
            return null;
        }

        $bestFace = null;
        $bestScore = null;

        foreach ((array) ($family['faces'] ?? []) as $face) {
            if (
                !is_array($face)
                || FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal')) !== 'normal'
                || !is_string($face['files']['woff2'] ?? null)
                || $this->getSameOriginWoff2Url($face) === ''
            ) {
                continue;
            }

            $score = $this->preloadFaceScore($face, $targetWeight);

            if ($bestScore !== null && $this->comparePreloadFaceScores($score, $bestScore) >= 0) {
                continue;
            }

            $bestFace = $face;
            $bestScore = $score;
        }

        return $bestFace;
    }

    private function findCatalogFamily(array $catalog, string $familyName): ?array
    {
        if ($familyName === '') {
            return null;
        }

        if (isset($catalog[$familyName]) && is_array($catalog[$familyName])) {
            return $catalog[$familyName];
        }

        foreach ($catalog as $family) {
            if (!is_array($family) || ($family['family'] ?? '') !== $familyName) {
                continue;
            }

            return $family;
        }

        return null;
    }

    private function getSameOriginWoff2Url(array $face): string
    {
        $url = trim((string) ($face['files']['woff2'] ?? ''));

        if ($url === '' || !$this->isSameOriginFontUrl($url)) {
            return '';
        }

        return $url;
    }

    private function isSameOriginFontUrl(string $url): bool
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');

        if ($host === '') {
            return !str_starts_with($url, '//');
        }

        $uploadBaseUrl = (string) (wp_get_upload_dir()['baseurl'] ?? '');
        $uploadHost = (string) (parse_url($uploadBaseUrl, PHP_URL_HOST) ?: '');

        if ($uploadHost === '') {
            return false;
        }

        return strtolower($host) === strtolower($uploadHost);
    }

    private function preloadFaceScore(array $face, int $targetWeight): array
    {
        $weight = FontUtils::normalizeWeight((string) ($face['weight'] ?? '400'));
        $isVariable = str_contains($weight, '..');
        $distance = $this->weightDistanceFromTarget($weight, $targetWeight);
        $referenceWeight = $this->weightReferenceValue($weight, $targetWeight);

        return [$distance, $isVariable ? 1 : 0, $referenceWeight];
    }

    private function comparePreloadFaceScores(array $left, array $right): int
    {
        foreach ([0, 1, 2] as $index) {
            $comparison = ((int) ($left[$index] ?? 0)) <=> ((int) ($right[$index] ?? 0));

            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }

    private function weightDistanceFromTarget(string $weight, int $targetWeight): int
    {
        if (preg_match('/^(\d{1,4})\.\.(\d{1,4})$/', $weight, $matches) === 1) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];

            if ($targetWeight < $start) {
                return $start - $targetWeight;
            }

            if ($targetWeight > $end) {
                return $targetWeight - $end;
            }

            return 0;
        }

        return abs(FontUtils::weightSortValue($weight) - $targetWeight);
    }

    private function weightReferenceValue(string $weight, int $targetWeight): int
    {
        if (preg_match('/^(\d{1,4})\.\.(\d{1,4})$/', $weight, $matches) === 1) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];

            return max($start, min($targetWeight, $end));
        }

        return FontUtils::weightSortValue($weight);
    }
}
