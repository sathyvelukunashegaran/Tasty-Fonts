<?php

declare(strict_types=1);

namespace EtchFonts\Fonts;

use EtchFonts\Repository\LogRepository;
use EtchFonts\Repository\SettingsRepository;
use EtchFonts\Support\Storage;

final class AssetService
{
    private const TRANSIENT_CSS = 'etch_fonts_css_v2';
    private const TRANSIENT_HASH = 'etch_fonts_css_hash_v2';

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
        $roles = $this->settings->getRoles($catalog);
        $settings = $this->settings->getSettings();

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

    public function ensureGeneratedCssFile(): bool
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

        $this->log->add(
            $written
                ? __('Generated CSS file written successfully.', ETCH_FONTS_TEXT_DOMAIN)
                : __('Could not write generated CSS file. Inline fallback will be used.', ETCH_FONTS_TEXT_DOMAIN)
        );

        return $written;
    }

    public function refreshGeneratedAssets(bool $invalidateCatalog = true): void
    {
        if ($invalidateCatalog) {
            $this->catalog->invalidate();
        }

        $this->invalidate();
        $this->ensureGeneratedCssFile();
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

    private function getVersionedCss(): string
    {
        return "/* Version: " . ETCH_FONTS_VERSION . " */\n" . $this->getCss();
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
