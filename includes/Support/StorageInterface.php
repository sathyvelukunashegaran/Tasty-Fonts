<?php

declare(strict_types=1);

namespace TastyFonts\Support;

defined('ABSPATH') || exit;

/**
 * @phpstan-type StorageState array<string, string>
 * @phpstan-type RelativePathList list<string>
 * @phpstan-type DirectoryList list<string>
 * @phpstan-type StorageFileMetadata array{relative_path: string, size: int, sha256: string}
 * @phpstan-type StorageAbsoluteFileState array{exists: bool, size: int, last_modified: int, sha256: string}
 */
interface StorageInterface
{
    public function ensureRootDirectory(): bool;

    public function getLastFilesystemErrorMessage(): string;

    /**
     * @return StorageState|null
     */
    public function get(): ?array;

    public function getRoot(): ?string;

    public function getRootUrl(): ?string;

    public function getGoogleRoot(): ?string;

    public function getBunnyRoot(): ?string;

    public function getUploadRoot(): ?string;

    public function getAdobeRoot(): ?string;

    public function getCustomRoot(): ?string;

    public function getProviderRoot(string $provider): ?string;

    public function getGeneratedCssPath(): ?string;

    public function getGeneratedCssUrl(): ?string;

    public function getRootUrlFull(): ?string;

    public function relativePath(string $absolutePath): string;

    public function pathForRelativePath(string $relativePath): ?string;

    public function urlForRelativePath(string $relativePath): ?string;

    /**
     * @param null|callable(string, string, \SplFileInfo): bool $include
     * @return list<StorageFileMetadata>
     */
    public function listFileMetadata(?callable $include = null, bool $requireChecksum = false): array;

    /**
     * @return StorageAbsoluteFileState
     */
    public function getAbsoluteFileState(string $path): array;

    public function ensureDirectory(string $path): bool;

    public function writeAbsoluteFile(string $path, string $contents): bool;

    public function copyAbsoluteFile(string $sourcePath, string $targetPath): bool;

    /**
     * @param RelativePathList $relativePaths
     */
    public function deleteRelativeFiles(array $relativePaths): bool;

    public function deleteRelativeDirectory(string $relativePath): bool;

    public function deleteAbsolutePath(string $path): bool;

    public function isWithinRoot(string $path): bool;
}
