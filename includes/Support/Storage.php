<?php

declare(strict_types=1);

namespace EtchFonts\Support;

final class Storage
{
    private ?array $storage = null;

    public function ensureRootDirectory(): bool
    {
        return $this->getRoot() !== null;
    }

    public function get(): ?array
    {
        if ($this->storage !== null) {
            return $this->storage;
        }

        $uploads = wp_get_upload_dir();

        if (!empty($uploads['error'])) {
            return null;
        }

        $rootDir = wp_normalize_path(trailingslashit($uploads['basedir']) . 'fonts');
        $rootUrlFull = untrailingslashit($uploads['baseurl']) . '/fonts';
        $rootUrl = wp_make_link_relative($rootUrlFull);
        $googleDir = wp_normalize_path(trailingslashit($rootDir) . 'google');
        $googleUrl = $rootUrl . '/google';
        $googleUrlFull = $rootUrlFull . '/google';

        if (!$this->ensureDirectory($rootDir) || !is_dir($rootDir) || !is_readable($rootDir)) {
            return null;
        }

        $this->storage = [
            'dir' => $rootDir,
            'url' => $rootUrl,
            'url_full' => $rootUrlFull,
            'google_dir' => $googleDir,
            'google_url' => $googleUrl,
            'google_url_full' => $googleUrlFull,
        ];

        return $this->storage;
    }

    public function getRoot(): ?string
    {
        return $this->getStorageValue('dir');
    }

    public function getRootUrl(): ?string
    {
        return $this->getStorageValue('url');
    }

    public function getGoogleRoot(): ?string
    {
        $storage = $this->get();
        $googleDir = is_array($storage) ? ($storage['google_dir'] ?? null) : null;

        if (!is_string($googleDir)) {
            return null;
        }

        return $this->ensureDirectory($googleDir) ? $googleDir : null;
    }

    public function getGeneratedCssPath(): ?string
    {
        $root = $this->getRoot();

        return $root ? trailingslashit($root) . 'etch-fonts.css' : null;
    }

    public function getGeneratedCssUrl(): ?string
    {
        $url = $this->getRootUrlFull();

        return $url ? untrailingslashit($url) . '/etch-fonts.css' : null;
    }

    public function getRootUrlFull(): ?string
    {
        return $this->getStorageValue('url_full');
    }

    public function relativePath(string $absolutePath): string
    {
        $root = $this->getRoot();

        if (!$root) {
            return '';
        }

        $absolutePath = wp_normalize_path($absolutePath);
        $relative = str_replace(trailingslashit($root), '', $absolutePath);

        return trim(str_replace('\\', '/', $relative), '/');
    }

    public function pathForRelativePath(string $relativePath): ?string
    {
        $root = $this->getRoot();

        if (!$root) {
            return null;
        }

        return wp_normalize_path(trailingslashit($root) . $this->normalizeRelativePath($relativePath));
    }

    public function urlForRelativePath(string $relativePath): ?string
    {
        $rootUrl = $this->getRootUrl();

        if (!$rootUrl) {
            return null;
        }

        $segments = explode('/', $this->normalizeRelativePath($relativePath));
        $encodedSegments = array_map('rawurlencode', array_filter($segments, 'strlen'));

        return untrailingslashit($rootUrl) . '/' . implode('/', $encodedSegments);
    }

    public function ensureDirectory(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }

        return wp_mkdir_p($path);
    }

    public function writeAbsoluteFile(string $path, string $contents): bool
    {
        if ($path === '' || !$this->initializeFilesystem()) {
            return false;
        }

        global $wp_filesystem;

        $directory = dirname($path);

        if (!$wp_filesystem->is_dir($directory) && !$wp_filesystem->mkdir($directory, FS_CHMOD_DIR)) {
            return false;
        }

        return (bool) $wp_filesystem->put_contents($path, $contents, FS_CHMOD_FILE);
    }

    public function deleteRelativeFiles(array $relativePaths): bool
    {
        $paths = array_values(
            array_unique(
                array_filter(
                    array_map([$this, 'normalizeRelativePath'], $relativePaths),
                    'strlen'
                )
            )
        );

        if ($paths === []) {
            return true;
        }

        if (!$this->initializeFilesystem()) {
            return false;
        }

        global $wp_filesystem;

        $deleted = true;
        $directories = [];

        foreach ($paths as $relativePath) {
            $absolutePath = $this->pathForRelativePath($relativePath);

            if (!$absolutePath || !$this->isWithinRoot($absolutePath)) {
                $deleted = false;
                continue;
            }

            $directories[] = dirname($absolutePath);

            if (!$wp_filesystem->exists($absolutePath)) {
                continue;
            }

            if (!(bool) $wp_filesystem->delete($absolutePath, false, 'f')) {
                $deleted = false;
            }
        }

        $this->cleanupEmptyDirectories($directories);

        return $deleted;
    }

    public function deleteRelativeDirectory(string $relativePath): bool
    {
        $absolutePath = $this->pathForRelativePath($relativePath);

        if (!$absolutePath || !$this->isWithinRoot($absolutePath)) {
            return false;
        }

        if (!file_exists($absolutePath)) {
            return true;
        }

        if (!$this->initializeFilesystem()) {
            return false;
        }

        global $wp_filesystem;

        $deleted = (bool) $wp_filesystem->delete($absolutePath, true, 'd');

        if ($deleted) {
            $this->cleanupEmptyDirectories([dirname($absolutePath)]);
        }

        return $deleted;
    }

    public function isWithinRoot(string $path): bool
    {
        $root = $this->getRoot();

        if (!$root) {
            return false;
        }

        $path = wp_normalize_path($path);
        $root = trailingslashit(wp_normalize_path($root));

        return str_starts_with($path, $root);
    }

    private function getStorageValue(string $key): ?string
    {
        $storage = $this->get();

        if (!is_array($storage)) {
            return null;
        }

        $value = $storage[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private function normalizeRelativePath(string $relativePath): string
    {
        return ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    private function initializeFilesystem(): bool
    {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        return (bool) (WP_Filesystem() && $wp_filesystem);
    }

    private function cleanupEmptyDirectories(array $directories): void
    {
        $root = $this->getRoot();

        if (!$root) {
            return;
        }

        $root = untrailingslashit(wp_normalize_path($root));
        $directories = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (string $directory): string => wp_normalize_path($directory),
                        $directories
                    ),
                    'strlen'
                )
            )
        );

        usort(
            $directories,
            static fn (string $left, string $right): int => strlen($right) <=> strlen($left)
        );

        foreach ($directories as $directory) {
            $current = $directory;

            while (
                $current !== ''
                && $current !== '.'
                && $current !== DIRECTORY_SEPARATOR
                && str_starts_with(trailingslashit($current), trailingslashit($root))
                && $current !== $root
            ) {
                if (!is_dir($current) || !$this->isDirectoryEmpty($current)) {
                    break;
                }

                @rmdir($current);
                $current = wp_normalize_path(dirname($current));
            }
        }
    }

    private function isDirectoryEmpty(string $directory): bool
    {
        $entries = @scandir($directory);

        if (!is_array($entries)) {
            return false;
        }

        return count(array_diff($entries, ['.', '..'])) === 0;
    }
}
