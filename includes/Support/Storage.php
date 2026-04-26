<?php

declare(strict_types=1);

namespace TastyFonts\Support;

defined('ABSPATH') || exit;

/**
 * @phpstan-type StorageState array<string, string>
 * @phpstan-type RelativePathList list<string>
 * @phpstan-type DirectoryList list<string>
 * @phpstan-type StorageFileMetadata array{relative_path: string, size: int, sha256: string}
 */
final class Storage
{
    /** @var StorageState|null */
    private ?array $storage = null;
    private string $lastFilesystemErrorMessage = '';

    public function ensureRootDirectory(): bool
    {
        return $this->getRoot() !== null;
    }

    public function getLastFilesystemErrorMessage(): string
    {
        return $this->lastFilesystemErrorMessage;
    }

    /**
     * @return StorageState|null
     */
    public function get(): ?array
    {
        if ($this->storage !== null) {
            return $this->storage;
        }

        $uploads = wp_get_upload_dir();

        if (!empty($uploads['error'])) {
            return null;
        }

        $uploads = FontUtils::normalizeStringKeyedMap($uploads);
        $baseDir = FontUtils::scalarStringValue($uploads['basedir'] ?? '');
        $baseUrl = FontUtils::scalarStringValue($uploads['baseurl'] ?? '');

        if ($baseDir === '' || $baseUrl === '') {
            return null;
        }

        $rootDir = wp_normalize_path(trailingslashit($baseDir) . 'fonts');
        $rootUrlFull = untrailingslashit($baseUrl) . '/fonts';
        $rootUrl = wp_make_link_relative($rootUrlFull);
        $googleDir = wp_normalize_path(trailingslashit($rootDir) . 'google');
        $googleUrl = $rootUrl . '/google';
        $googleUrlFull = $rootUrlFull . '/google';
        $bunnyDir = wp_normalize_path(trailingslashit($rootDir) . 'bunny');
        $bunnyUrl = $rootUrl . '/bunny';
        $bunnyUrlFull = $rootUrlFull . '/bunny';
        $uploadDir = wp_normalize_path(trailingslashit($rootDir) . 'upload');
        $uploadUrl = $rootUrl . '/upload';
        $uploadUrlFull = $rootUrlFull . '/upload';
        $adobeDir = wp_normalize_path(trailingslashit($rootDir) . 'adobe');
        $adobeUrl = $rootUrl . '/adobe';
        $adobeUrlFull = $rootUrlFull . '/adobe';
        $customDir = wp_normalize_path(trailingslashit($rootDir) . 'custom');
        $customUrl = $rootUrl . '/custom';
        $customUrlFull = $rootUrlFull . '/custom';
        $generatedDir = wp_normalize_path(trailingslashit($rootDir) . '.generated');
        $generatedUrl = $rootUrl . '/.generated';
        $generatedUrlFull = $rootUrlFull . '/.generated';

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
            'bunny_dir' => $bunnyDir,
            'bunny_url' => $bunnyUrl,
            'bunny_url_full' => $bunnyUrlFull,
            'upload_dir' => $uploadDir,
            'upload_url' => $uploadUrl,
            'upload_url_full' => $uploadUrlFull,
            'adobe_dir' => $adobeDir,
            'adobe_url' => $adobeUrl,
            'adobe_url_full' => $adobeUrlFull,
            'custom_dir' => $customDir,
            'custom_url' => $customUrl,
            'custom_url_full' => $customUrlFull,
            'generated_dir' => $generatedDir,
            'generated_url' => $generatedUrl,
            'generated_url_full' => $generatedUrlFull,
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
        return $this->getProviderRoot('google');
    }

    public function getBunnyRoot(): ?string
    {
        return $this->getProviderRoot('bunny');
    }

    public function getUploadRoot(): ?string
    {
        return $this->getProviderRoot('upload');
    }

    public function getAdobeRoot(): ?string
    {
        return $this->getProviderRoot('adobe');
    }

    public function getCustomRoot(): ?string
    {
        return $this->getProviderRoot('custom');
    }

    public function getProviderRoot(string $provider): ?string
    {
        $provider = strtolower(trim($provider));

        if ($provider === '') {
            return null;
        }

        $providerDir = $this->getStorageValue($provider . '_dir');

        if ($providerDir === null) {
            return null;
        }

        return $this->ensureDirectory($providerDir) ? $providerDir : null;
    }

    public function getGeneratedCssPath(): ?string
    {
        $generatedDir = $this->getStorageValue('generated_dir');

        return $generatedDir ? trailingslashit($generatedDir) . 'tasty-fonts.css' : null;
    }

    public function getGeneratedCssUrl(): ?string
    {
        $url = $this->getStorageValue('generated_url_full');

        return $url ? untrailingslashit($url) . '/tasty-fonts.css' : null;
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
        $encodedSegments = array_map('rawurlencode', array_filter($segments, static fn (string $segment): bool => $segment !== ''));

        return untrailingslashit($rootUrl) . '/' . implode('/', $encodedSegments);
    }

    /**
     * @param null|callable(string, string, \SplFileInfo): bool $include
     * @return list<StorageFileMetadata>
     */
    public function listFileMetadata(?callable $include = null, bool $requireChecksum = false): array
    {
        $root = $this->getRoot();

        if (!is_string($root) || $root === '' || !is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                continue;
            }

            $absolutePath = wp_normalize_path($item->getPathname());
            $relativePath = $this->relativePath($absolutePath);

            if ($relativePath === '' || ($include !== null && !$include($relativePath, $absolutePath, $item))) {
                continue;
            }

            $checksum = hash_file('sha256', $absolutePath);

            if (!is_string($checksum) && $requireChecksum) {
                continue;
            }

            $files[] = [
                'relative_path' => $relativePath,
                'size' => (int) $item->getSize(),
                'sha256' => is_string($checksum) ? $checksum : '',
            ];
        }

        usort($files, static fn (array $left, array $right): int => strcmp($left['relative_path'], $right['relative_path']));

        return $files;
    }

    public function ensureDirectory(string $path): bool
    {
        $this->clearFilesystemError();

        if (is_dir($path)) {
            return true;
        }

        if (wp_mkdir_p($path)) {
            return true;
        }

        if (!$this->supportsDirectFilesystem($path)) {
            return false;
        }

        return false;
    }

    public function writeAbsoluteFile(string $path, string $contents): bool
    {
        $path = wp_normalize_path($path);

        if ($path === '') {
            return false;
        }

        $directory = wp_normalize_path(dirname($path));

        if (!$this->ensureDirectory($directory) || !$this->initializeFilesystem($directory)) {
            return false;
        }

        global $wp_filesystem;

        if (
            !is_object($wp_filesystem)
            || !method_exists($wp_filesystem, 'is_dir')
            || !method_exists($wp_filesystem, 'mkdir')
            || !method_exists($wp_filesystem, 'put_contents')
        ) {
            return false;
        }

        $filesystem = $wp_filesystem;

        $directoryPermissions = $this->directoryPermissions();
        $filePermissions = $this->filePermissions();

        if (!$filesystem->is_dir($directory) && !$filesystem->mkdir($directory, $directoryPermissions)) {
            return false;
        }

        return (bool) $filesystem->put_contents($path, $contents, $filePermissions);
    }

    public function copyAbsoluteFile(string $sourcePath, string $targetPath): bool
    {
        if ($sourcePath === '' || $targetPath === '') {
            return false;
        }

        $directory = dirname($targetPath);

        if (!$this->ensureDirectory($directory) || !is_readable($sourcePath)) {
            return false;
        }

        $copied = move_uploaded_file($sourcePath, $targetPath);

        if (!$copied) {
            $copied = copy($sourcePath, $targetPath);
        }

        if (!$copied) {
            return false;
        }

        chmod($targetPath, $this->filePermissions());

        return true;
    }

    /**
     * @param RelativePathList $relativePaths
     */
    public function deleteRelativeFiles(array $relativePaths): bool
    {
        $paths = array_values(
            array_unique(
                array_filter(
                    array_map([$this, 'normalizeRelativePath'], $relativePaths),
                    static fn (string $path): bool => $path !== ''
                )
            )
        );

        if ($paths === []) {
            return true;
        }

        $contextPath = $this->pathForRelativePath($paths[0]);
        $context = is_string($contextPath) ? dirname($contextPath) : null;

        if (!$this->initializeFilesystem($context)) {
            return false;
        }

        global $wp_filesystem;

        if (
            !is_object($wp_filesystem)
            || !method_exists($wp_filesystem, 'exists')
            || !method_exists($wp_filesystem, 'delete')
        ) {
            return false;
        }

        $filesystem = $wp_filesystem;

        $deleted = true;
        $directories = [];

        foreach ($paths as $relativePath) {
            $absolutePath = $this->pathForRelativePath($relativePath);

            if (!$absolutePath || !$this->isWithinRoot($absolutePath)) {
                $deleted = false;
                continue;
            }

            $directories[] = dirname($absolutePath);

            if (!$filesystem->exists($absolutePath)) {
                continue;
            }

            if (!(bool) $filesystem->delete($absolutePath, false, 'f')) {
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

        if (!$this->initializeFilesystem(dirname($absolutePath))) {
            return false;
        }

        global $wp_filesystem;

        if (!is_object($wp_filesystem) || !method_exists($wp_filesystem, 'delete')) {
            return false;
        }

        $filesystem = $wp_filesystem;

        $deleted = (bool) $filesystem->delete($absolutePath, true, 'd');

        if ($deleted) {
            $this->cleanupEmptyDirectories([dirname($absolutePath)]);
        }

        return $deleted;
    }

    public function deleteAbsolutePath(string $path): bool
    {
        $path = wp_normalize_path($path);

        if ($path === '' || !$this->isWithinRoot($path)) {
            return false;
        }

        if (!file_exists($path)) {
            return true;
        }

        if ($this->initializeFilesystem(dirname($path))) {
            global $wp_filesystem;

            if (!is_object($wp_filesystem) || !method_exists($wp_filesystem, 'delete')) {
                return false;
            }

            $filesystem = $wp_filesystem;

            $deleted = (bool) $filesystem->delete($path, is_dir($path), is_dir($path) ? 'd' : 'f');

            if ($deleted) {
                $this->cleanupEmptyDirectories([dirname($path)]);
            }

            return $deleted;
        }

        $deleted = $this->deleteAbsolutePathDirectly($path);

        if ($deleted) {
            $this->clearFilesystemError();
            $this->cleanupEmptyDirectories([dirname($path)]);
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
        $root = wp_normalize_path($root);

        return $path === $root || str_starts_with($path, trailingslashit($root));
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

    private function initializeFilesystem(?string $context = null): bool
    {
        global $wp_filesystem;

        $this->clearFilesystemError();

        if (!$this->supportsDirectFilesystem($context)) {
            return false;
        }

        if (!function_exists('WP_Filesystem') || !function_exists('get_filesystem_method')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $context = $this->filesystemContext($context);

        if (!(WP_Filesystem(false, $context, false) && $wp_filesystem)) {
            $this->setFilesystemErrorMessage(
                __('Direct filesystem access could not be initialized. Tasty Fonts cannot manage font files until WordPress can use the direct filesystem method.', 'tasty-fonts')
            );

            return false;
        }

        return true;
    }

    private function supportsDirectFilesystem(?string $context = null): bool
    {
        if (!function_exists('WP_Filesystem') || !function_exists('get_filesystem_method')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!function_exists('get_filesystem_method')) {
            $this->setFilesystemErrorMessage(
                __('Direct filesystem access could not be verified. Tasty Fonts cannot manage font files until WordPress can use the direct filesystem method.', 'tasty-fonts')
            );

            return false;
        }

        if (get_filesystem_method([], $this->filesystemContext($context), false) === 'direct') {
            return true;
        }

        $this->setFilesystemErrorMessage(
            __('Direct filesystem access is unavailable on this host. Tasty Fonts cannot write imported font files or generated stylesheets until WordPress can use the direct filesystem method.', 'tasty-fonts')
        );

        return false;
    }

    private function filesystemContext(?string $context = null): string
    {
        if (is_string($context) && trim($context) !== '') {
            $context = wp_normalize_path($context);

            if (file_exists($context) && !is_dir($context)) {
                $context = wp_normalize_path(dirname($context));
            }

            while ($context !== '' && !file_exists($context)) {
                $parent = wp_normalize_path(dirname($context));

                if ($parent === $context) {
                    break;
                }

                $context = $parent;
            }

            if ($context !== '') {
                return $context;
            }
        }

        $root = $this->getRoot();

        if (is_string($root) && $root !== '') {
            return wp_normalize_path($root);
        }

        $uploads = FontUtils::normalizeStringKeyedMap(wp_get_upload_dir());
        $baseDir = FontUtils::scalarStringValue($uploads['basedir'] ?? '');

        if ($baseDir !== '') {
            return wp_normalize_path($baseDir);
        }

        return '.';
    }

    private function clearFilesystemError(): void
    {
        $this->lastFilesystemErrorMessage = '';
    }

    private function directoryPermissions(): int
    {
        return \defined('FS_CHMOD_DIR') ? (int) \constant('FS_CHMOD_DIR') : 0755;
    }

    private function filePermissions(): int
    {
        return \defined('FS_CHMOD_FILE') ? (int) \constant('FS_CHMOD_FILE') : 0644;
    }

    private function setFilesystemErrorMessage(string $message): void
    {
        $this->lastFilesystemErrorMessage = trim($message);
    }

    /**
     * @param DirectoryList $directories
     */
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
                    static fn (string $directory): bool => $directory !== ''
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

                if (is_writable($current)) {
                    rmdir($current);
                }

                $current = wp_normalize_path(dirname($current));
            }
        }
    }

    private function isDirectoryEmpty(string $directory): bool
    {
        if (!is_readable($directory)) {
            return false;
        }

        $entries = scandir($directory);

        if (!is_array($entries)) {
            return false;
        }

        return count(array_diff($entries, ['.', '..'])) === 0;
    }

    private function deleteAbsolutePathDirectly(string $path): bool
    {
        if (!file_exists($path)) {
            return true;
        }

        if (is_dir($path)) {
            $entries = scandir($path);

            if (!is_array($entries)) {
                return false;
            }

            foreach (array_diff($entries, ['.', '..']) as $entry) {
                if (!$this->deleteAbsolutePathDirectly(wp_normalize_path($path . DIRECTORY_SEPARATOR . $entry))) {
                    return false;
                }
            }

            return rmdir($path);
        }

        return unlink($path);
    }
}
