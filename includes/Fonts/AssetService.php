<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;

final class AssetService
{
    private const TRANSIENT_CSS = 'tasty_fonts_css_v2';
    private const TRANSIENT_HASH = 'tasty_fonts_css_hash_v2';

    private ?string $css = null;
    private ?string $hash = null;

    public function __construct(
        private readonly Storage $storage,
        private readonly CatalogService $catalog,
        private readonly SettingsRepository $settings,
        private readonly CssBuilder $cssBuilder,
        private readonly LogRepository $log
    ) {
    }

    public function invalidate(): void
    {
        delete_transient(self::TRANSIENT_CSS);
        delete_transient(self::TRANSIENT_HASH);
        $this->css = null;
        $this->hash = null;
    }

    public function getCss(): string
    {
        if (is_string($this->css)) {
            return $this->css;
        }

        $cachedCss = get_transient(self::TRANSIENT_CSS);
        $cachedHash = get_transient(self::TRANSIENT_HASH);

        if (is_string($cachedCss) && is_string($cachedHash)) {
            $this->css = $cachedCss;
            $this->hash = $cachedHash;

            return $this->css;
        }

        $catalog = $this->catalog->getCatalog();
        $settings = $this->settings->getSettings();
        $roles = !empty($settings['auto_apply_roles'])
            ? $this->settings->getAppliedRoles($catalog)
            : $this->settings->getRoles($catalog);

        $this->css = $this->cssBuilder->build($catalog, $roles, $settings);
        $this->hash = hash('crc32b', $this->css);

        set_transient(self::TRANSIENT_CSS, $this->css, DAY_IN_SECONDS);
        set_transient(self::TRANSIENT_HASH, $this->hash, DAY_IN_SECONDS);

        return $this->css;
    }

    public function getCssHash(): string
    {
        if ($this->hash !== null) {
            return $this->hash;
        }

        $this->getCss();

        return (string) $this->hash;
    }

    public function expectedFileHash(): string
    {
        return hash('crc32b', $this->getVersionedCss());
    }

    public function ensureGeneratedCssFile(bool $logWriteResult = true): bool
    {
        if (!$this->isFileDeliveryEnabled()) {
            return false;
        }

        $state = $this->getGeneratedStylesheetState();
        $path = (string) $state['path'];

        if ($path === '') {
            return false;
        }

        if (!empty($state['is_current'])) {
            return true;
        }

        $written = $this->storage->writeAbsoluteFile($path, $this->getVersionedCss());

        if ($logWriteResult) {
            $this->log->add(
                $written
                    ? __('Generated CSS file written successfully.', 'tasty-fonts')
                    : __('Could not write generated CSS file. Inline fallback will be used.', 'tasty-fonts')
            );
        }

        return $written;
    }

    public function refreshGeneratedAssets(bool $invalidateCatalog = true, bool $logWriteResult = true): void
    {
        if ($invalidateCatalog) {
            $this->catalog->invalidate();
        }

        $this->invalidate();
        $this->ensureGeneratedCssFile($logWriteResult);
    }

    public function enqueue(string $handle): void
    {
        $css = $this->getCss();
        $state = $this->getGeneratedStylesheetState();
        $url = (string) $state['url'];
        $expectedHash = (string) $state['expected_hash'];

        if (!empty($state['is_current']) && $url !== '') {
            wp_enqueue_style($handle, $url, [], $expectedHash);
            return;
        }

        wp_register_style($handle, false);
        wp_enqueue_style($handle);

        if ($css !== '') {
            wp_add_inline_style($handle, $css);
        }

        $this->ensureGeneratedCssFile();
    }

    public function enqueueFontFacesOnly(string $handle): void
    {
        $catalog = $this->catalog->getCatalog();
        $settings = $this->settings->getSettings();
        $css = $this->cssBuilder->buildFontFaceOnly($catalog, $settings);

        wp_register_style($handle, false);
        wp_enqueue_style($handle);

        if ($css !== '') {
            wp_add_inline_style($handle, $css);
        }
    }

    public function getStatus(): array
    {
        $state = $this->getGeneratedStylesheetState();

        return [
            'path' => $state['path'],
            'url' => $state['url'],
            'exists' => $state['exists'],
            'size' => $state['size'],
            'expected_hash' => $state['expected_hash'],
        ];
    }

    public function getVersionedStylesheetUrl(): ?string
    {
        $state = $this->getGeneratedStylesheetState();
        $url = (string) $state['url'];

        if ($url === '') {
            return null;
        }

        return add_query_arg('ver', (string) $state['expected_hash'], $url);
    }

    public function getPrimaryFontPreloadUrls(): array
    {
        $settings = $this->settings->getSettings();

        if (empty($settings['preload_primary_fonts']) || empty($settings['auto_apply_roles'])) {
            return [];
        }

        $catalog = $this->catalog->getCatalog();
        $roles = $this->settings->getAppliedRoles($catalog);
        $urls = [];

        // Favor the weights most likely to drive early text rendering.
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

    private function getVersionedCss(): string
    {
        return "/* Version: " . TASTY_FONTS_VERSION . " */\n" . $this->getCss();
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

    private function isFileDeliveryEnabled(): bool
    {
        $settings = $this->settings->getSettings();

        return ($settings['css_delivery_mode'] ?? 'file') === 'file';
    }

    private function getGeneratedStylesheetState(): array
    {
        $path = $this->storage->getGeneratedCssPath() ?? '';
        $url = $this->storage->getGeneratedCssUrl() ?? '';
        $exists = $path !== '' && file_exists($path);
        $expectedHash = $this->expectedFileHash();
        $currentHash = $exists ? (string) hash_file('crc32b', $path) : '';

        return [
            'path' => $path,
            'url' => $url,
            'exists' => $exists,
            'size' => $exists ? (int) filesize($path) : 0,
            'expected_hash' => $expectedHash,
            'current_hash' => $currentHash,
            'is_current' => $exists && $currentHash === $expectedHash,
        ];
    }
}
