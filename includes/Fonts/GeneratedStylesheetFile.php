<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\StorageInterface;

/**
 * @phpstan-type GeneratedStylesheetState array{
 *     path: string,
 *     url: string,
 *     exists: bool,
 *     size: int,
 *     last_modified: int,
 *     expected_hash: string,
 *     expected_version: string,
 *     current_hash: string,
 *     is_current: bool,
 *     write_path: string
 * }
 */
final class GeneratedStylesheetFile
{
    private const VERSION_HASH_LENGTH = 16;

    public function __construct(
        private readonly StorageInterface $storage,
        private readonly GeneratedCssCache $cssCache,
        private readonly LogRepository $log
    ) {
    }

    /**
     * Return canonical filesystem and hash information for the generated stylesheet.
     *
     * @return GeneratedStylesheetState
     */
    public function getState(): array
    {
        $path = $this->storage->getGeneratedCssPath() ?? '';
        $url = $this->storage->getGeneratedCssUrl() ?? '';
        $writePath = $path;
        $state = $this->buildStylesheetStateForPath($path, $url);
        $expectedHash = $this->expectedFileHash();

        return [
            'path' => $state['path'],
            'url' => $state['url'],
            'exists' => $state['exists'],
            'size' => $state['size'],
            'last_modified' => $state['last_modified'],
            'expected_hash' => $expectedHash,
            'expected_version' => $this->versionTokenFromHash($expectedHash),
            'current_hash' => $state['current_hash'],
            'is_current' => $state['exists'] && $state['current_hash'] === $expectedHash,
            'write_path' => $writePath,
        ];
    }

    /**
     * Return the generated stylesheet status payload.
     *
     * @return GeneratedStylesheetState
     */
    public function getStatus(): array
    {
        $state = $this->getState();

        return [
            'path' => $state['path'],
            'url' => $state['url'],
            'exists' => $state['exists'],
            'size' => $state['size'],
            'last_modified' => $state['last_modified'],
            'expected_hash' => $state['expected_hash'],
            'expected_version' => $state['expected_version'],
            'current_hash' => $state['current_hash'],
            'is_current' => $state['is_current'],
            'write_path' => $state['write_path'],
        ];
    }

    /**
     * Return the hash expected for the versioned generated stylesheet file.
     */
    public function expectedFileHash(): string
    {
        return $this->cssCache->expectedFileHash();
    }

    /**
     * Ensure the generated stylesheet file exists and matches the current CSS payload.
     */
    public function ensureFile(bool $logWriteResult = true): bool
    {
        $state = $this->getState();
        $path = (string) $state['path'];

        if ($path === '') {
            return false;
        }

        if (!empty($state['is_current'])) {
            return true;
        }

        return $this->writeFile($state, $logWriteResult);
    }

    /**
     * Write the versioned generated CSS file when the supplied state is stale.
     *
     * @param GeneratedStylesheetState $state
     */
    public function writeFile(array $state, bool $logWriteResult = true): bool
    {
        $path = $state['write_path'];

        if ($path === '' || !empty($state['is_current'])) {
            return $path !== '' && !empty($state['is_current']);
        }

        $written = $this->storage->writeAbsoluteFile($path, $this->cssCache->getVersionedCss());

        if ($logWriteResult) {
            $this->log->add(
                $written
                    ? __('Generated CSS file written successfully.', 'tasty-fonts')
                    : __('Could not write generated CSS file. Inline fallback will be used.', 'tasty-fonts')
            );
        }

        return $written;
    }

    /**
     * Return the versioned public URL for the generated stylesheet file when available.
     */
    public function getVersionedStylesheetUrl(): ?string
    {
        $state = $this->getState();
        $url = (string) $state['url'];

        if ($url === '') {
            return null;
        }

        $version = !empty($state['exists']) ? (string) $state['expected_version'] : TASTY_FONTS_VERSION;

        return add_query_arg('ver', $version, $url);
    }

    /**
     * @return array{path: string, url: string, exists: bool, size: int, last_modified: int, current_hash: string}
     */
    private function buildStylesheetStateForPath(string $path, string $url): array
    {
        $fileState = $path !== ''
            ? $this->storage->getAbsoluteFileState($path)
            : [
                'exists' => false,
                'size' => 0,
                'last_modified' => 0,
                'sha256' => '',
            ];

        return [
            'path' => $path,
            'url' => $url,
            'exists' => $fileState['exists'],
            'size' => $fileState['size'],
            'last_modified' => $fileState['last_modified'],
            'current_hash' => $fileState['sha256'],
        ];
    }

    private function versionTokenFromHash(string $hash): string
    {
        return substr($hash, 0, self::VERSION_HASH_LENGTH);
    }
}
