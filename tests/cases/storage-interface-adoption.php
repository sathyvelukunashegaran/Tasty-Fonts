<?php

declare(strict_types=1);

use TastyFonts\Adobe\AdobeCssParser;
use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Bunny\BunnyFontsClient;
use TastyFonts\Fonts\AdobeCatalogAdapter;
use TastyFonts\Fonts\AdobeStylesheetResolver;
use TastyFonts\Fonts\BunnyStylesheetResolver;
use TastyFonts\Fonts\CatalogBuilder;
use TastyFonts\Fonts\CatalogCache;
use TastyFonts\Fonts\CatalogEnricher;
use TastyFonts\Fonts\CatalogHydrator;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Fonts\FontFilenameParser;
use TastyFonts\Fonts\GeneratedStylesheetFile;
use TastyFonts\Fonts\GeneratedCssCache;
use TastyFonts\Fonts\GoogleStylesheetResolver;
use TastyFonts\Fonts\LocalCatalogScanner;
use TastyFonts\Fonts\RuntimeAssetPlanner;
use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Repository\AdobeProjectRepository;
use TastyFonts\Repository\FamilyMetadataRepository;
use TastyFonts\Repository\GoogleApiKeyRepository;
use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\RoleRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\Storage;
use TastyFonts\Support\StorageInterface;

/**
 * Virtual storage adapter that implements StorageInterface fully in memory so
 * GeneratedStylesheetFile can be exercised without relying on native file
 * inspection calls or real upload directories.
 */
final class VirtualStorage implements StorageInterface
{
    private string $rootDir;
    private string $rootUrl;
    private string $generatedCssPath;
    private string $generatedCssUrl;
    private bool $failWrites;
    /** @var array<string, string> */
    private array $files = [];
    /** @var array<string, true> */
    private array $directories = [];
    /** @var array<string, int> */
    private array $modifiedTimes = [];
    private int $clock = 1;
    public int $writeCount = 0;
    public int $writeAttempts = 0;

    public function __construct(
        string $rootDir,
        string $rootUrl,
        ?string $generatedCssPath = null,
        ?string $generatedCssUrl = null,
        bool $failWrites = false
    ) {
        $this->rootDir = rtrim(wp_normalize_path($rootDir), '/\\');
        $this->rootUrl = rtrim($rootUrl, '/');
        $this->generatedCssPath = $generatedCssPath ?? $this->rootDir . '/.generated/tasty-fonts.css';
        $this->generatedCssUrl = $generatedCssUrl ?? $this->rootUrl . '/.generated/tasty-fonts.css';
        $this->failWrites = $failWrites;
        $this->directories[$this->rootDir] = true;
    }

    public function ensureRootDirectory(): bool
    {
        $this->directories[$this->rootDir] = true;

        return true;
    }

    public function getLastFilesystemErrorMessage(): string
    {
        return $this->failWrites ? 'Virtual write failure.' : '';
    }

    public function get(): ?array
    {
        return [
            'dir' => $this->rootDir,
            'url' => $this->rootUrl,
            'url_full' => $this->rootUrl,
            'generated_dir' => $this->rootDir . '/.generated',
            'generated_url' => $this->rootUrl . '/.generated',
            'generated_url_full' => $this->rootUrl . '/.generated',
        ];
    }

    public function getRoot(): ?string
    {
        return $this->rootDir;
    }

    public function getRootUrl(): ?string
    {
        return $this->rootUrl;
    }

    public function getGoogleRoot(): ?string
    {
        return $this->rootDir . '/google';
    }

    public function getBunnyRoot(): ?string
    {
        return $this->rootDir . '/bunny';
    }

    public function getUploadRoot(): ?string
    {
        return $this->rootDir . '/upload';
    }

    public function getAdobeRoot(): ?string
    {
        return $this->rootDir . '/adobe';
    }

    public function getCustomRoot(): ?string
    {
        return $this->rootDir . '/custom';
    }

    public function getProviderRoot(string $provider): ?string
    {
        return $this->rootDir . '/' . trim($provider, '/');
    }

    public function getGeneratedCssPath(): ?string
    {
        return $this->generatedCssPath;
    }

    public function getGeneratedCssUrl(): ?string
    {
        return $this->generatedCssUrl;
    }

    public function getRootUrlFull(): ?string
    {
        return $this->rootUrl;
    }

    public function relativePath(string $absolutePath): string
    {
        $relative = str_replace(trailingslashit($this->rootDir), '', wp_normalize_path($absolutePath));

        return ltrim($relative, '/');
    }

    public function pathForRelativePath(string $relativePath): ?string
    {
        return $this->rootDir . '/' . ltrim($relativePath, '/');
    }

    public function urlForRelativePath(string $relativePath): ?string
    {
        return $this->rootUrl . '/' . ltrim($relativePath, '/');
    }

    public function listFileMetadata(?callable $include = null, bool $requireChecksum = false): array
    {
        $files = [];

        foreach ($this->files as $absolutePath => $contents) {
            $relativePath = $this->relativePath($absolutePath);

            if ($relativePath === '') {
                continue;
            }

            $files[] = [
                'relative_path' => $relativePath,
                'size' => strlen($contents),
                'sha256' => hash('sha256', $contents),
            ];
        }

        usort($files, static fn (array $left, array $right): int => strcmp($left['relative_path'], $right['relative_path']));

        return $files;
    }

    public function getAbsoluteFileState(string $path): array
    {
        $path = wp_normalize_path($path);
        $contents = $this->files[$path] ?? null;

        if (!is_string($contents)) {
            return [
                'exists' => false,
                'size' => 0,
                'last_modified' => 0,
                'sha256' => '',
            ];
        }

        return [
            'exists' => true,
            'size' => strlen($contents),
            'last_modified' => $this->modifiedTimes[$path] ?? 0,
            'sha256' => hash('sha256', $contents),
        ];
    }

    public function ensureDirectory(string $path): bool
    {
        $this->directories[wp_normalize_path($path)] = true;

        return true;
    }

    public function writeAbsoluteFile(string $path, string $contents): bool
    {
        $this->writeAttempts++;
        $path = wp_normalize_path($path);

        if ($this->failWrites || $path === '' || !$this->isWithinRoot($path)) {
            return false;
        }

        $this->ensureDirectory(dirname($path));
        $this->files[$path] = $contents;
        $this->modifiedTimes[$path] = $this->clock++;
        $this->writeCount++;

        return true;
    }

    public function copyAbsoluteFile(string $sourcePath, string $targetPath): bool
    {
        $sourcePath = wp_normalize_path($sourcePath);
        $targetPath = wp_normalize_path($targetPath);

        if (!isset($this->files[$sourcePath])) {
            return false;
        }

        $this->files[$targetPath] = $this->files[$sourcePath];
        $this->modifiedTimes[$targetPath] = $this->clock++;

        return true;
    }

    public function deleteRelativeFiles(array $relativePaths): bool
    {
        foreach ($relativePaths as $relativePath) {
            $absolutePath = $this->pathForRelativePath($relativePath);

            if ($absolutePath !== null) {
                unset($this->files[wp_normalize_path($absolutePath)], $this->modifiedTimes[wp_normalize_path($absolutePath)]);
            }
        }

        return true;
    }

    public function deleteRelativeDirectory(string $relativePath): bool
    {
        $absolutePath = $this->pathForRelativePath($relativePath);

        return $absolutePath === null ? false : $this->deleteAbsolutePath($absolutePath);
    }

    public function deleteAbsolutePath(string $path): bool
    {
        $path = wp_normalize_path($path);

        foreach (array_keys($this->files) as $absolutePath) {
            if ($absolutePath === $path || str_starts_with($absolutePath, trailingslashit($path))) {
                unset($this->files[$absolutePath], $this->modifiedTimes[$absolutePath]);
            }
        }

        unset($this->directories[$path]);

        return true;
    }

    public function isWithinRoot(string $path): bool
    {
        $root = wp_normalize_path($this->rootDir);
        $path = wp_normalize_path($path);

        return $path === $root || str_starts_with($path, trailingslashit($root));
    }

    public function contentsForAbsolutePath(string $path): string
    {
        return $this->files[wp_normalize_path($path)] ?? '';
    }
}

/**
 * Concrete Storage stub for collaborators that have not adopted StorageInterface
 * yet. It avoids the production upload directory while keeping this slice
 * focused on GeneratedStylesheetFile.
 */
final class GeneratedCssCacheStorageStub extends Storage
{
    private string $rootDir = '/virtual-generated-css-cache';
    private string $rootUrl = 'https://example.test/virtual-generated-css-cache';

    public function ensureRootDirectory(): bool { return true; }
    public function getLastFilesystemErrorMessage(): string { return ''; }
    public function get(): ?array { return ['dir' => $this->rootDir, 'url' => $this->rootUrl, 'url_full' => $this->rootUrl]; }
    public function getRoot(): ?string { return $this->rootDir; }
    public function getRootUrl(): ?string { return $this->rootUrl; }
    public function getGoogleRoot(): ?string { return $this->rootDir . '/google'; }
    public function getBunnyRoot(): ?string { return $this->rootDir . '/bunny'; }
    public function getUploadRoot(): ?string { return $this->rootDir . '/upload'; }
    public function getAdobeRoot(): ?string { return $this->rootDir . '/adobe'; }
    public function getCustomRoot(): ?string { return $this->rootDir . '/custom'; }
    public function getProviderRoot(string $provider): ?string { return $this->rootDir . '/' . trim($provider, '/'); }
    public function getGeneratedCssPath(): ?string { return $this->rootDir . '/.generated/tasty-fonts.css'; }
    public function getGeneratedCssUrl(): ?string { return $this->rootUrl . '/.generated/tasty-fonts.css'; }
    public function getRootUrlFull(): ?string { return $this->rootUrl; }
    public function relativePath(string $absolutePath): string { return ltrim(str_replace(trailingslashit($this->rootDir), '', wp_normalize_path($absolutePath)), '/'); }
    public function pathForRelativePath(string $relativePath): ?string { return $this->rootDir . '/' . ltrim($relativePath, '/'); }
    public function urlForRelativePath(string $relativePath): ?string { return $this->rootUrl . '/' . ltrim($relativePath, '/'); }
    public function listFileMetadata(?callable $include = null, bool $requireChecksum = false): array { return []; }
    public function getAbsoluteFileState(string $path): array { return ['exists' => false, 'size' => 0, 'last_modified' => 0, 'sha256' => '']; }
    public function ensureDirectory(string $path): bool { return true; }
    public function writeAbsoluteFile(string $path, string $contents): bool { return true; }
    public function copyAbsoluteFile(string $sourcePath, string $targetPath): bool { return true; }
    public function deleteRelativeFiles(array $relativePaths): bool { return true; }
    public function deleteRelativeDirectory(string $relativePath): bool { return true; }
    public function deleteAbsolutePath(string $path): bool { return true; }
    public function isWithinRoot(string $path): bool
    {
        $root = wp_normalize_path($this->rootDir);
        $path = wp_normalize_path($path);

        return $path === $root || str_starts_with($path, trailingslashit($root));
    }
}

/**
 * Build a minimal real GeneratedCssCache using the test harness so the
 * GeneratedStylesheetFile consumer can be exercised with virtual storage.
 */
function makeMinimalGeneratedCssCache(): GeneratedCssCache
{
    $storage = new GeneratedCssCacheStorageStub();
    $googleApiKeyRepo = new GoogleApiKeyRepository();
    $adobeProjectRepo = new AdobeProjectRepository();
    $roleRepo = new RoleRepository();
    $familyMetadataRepo = new FamilyMetadataRepository();
    $settings = new SettingsRepository(
        $googleApiKeyRepo,
        $adobeProjectRepo,
        $roleRepo,
        $familyMetadataRepo
    );
    $imports = new ImportRepository();
    $log = new LogRepository();
    $adobe = new AdobeProjectClient($adobeProjectRepo, new AdobeCssParser());
    $bunny = new BunnyFontsClient();
    $google = new GoogleFontsClient($googleApiKeyRepo);
    $localScanner = new LocalCatalogScanner($storage, new FontFilenameParser());
    $adobeAdapter = new AdobeCatalogAdapter($adobe);
    $builder = new CatalogBuilder($imports, $localScanner, $adobeAdapter);
    $hydrator = new CatalogHydrator($storage);
    $enricher = new CatalogEnricher();
    $catalog = new CatalogCache($builder, $hydrator, $enricher, $storage, $log);
    $planner = new RuntimeAssetPlanner(
        $catalog,
        $settings,
        [new GoogleStylesheetResolver($google), new BunnyStylesheetResolver($bunny), new AdobeStylesheetResolver($adobe)],
        $roleRepo,
        $familyMetadataRepo
    );
    $cssBuilder = new CssBuilder();

    return new GeneratedCssCache(
        $catalog,
        $settings,
        $cssBuilder,
        $planner,
        $roleRepo,
        'css',
        'hash'
    );
}

// ---------------------------------------------------------------------------
// GeneratedStylesheetFile through virtual StorageInterface
// ---------------------------------------------------------------------------

$tests['generated_stylesheet_file_uses_storage_interface_for_path_and_url'] = static function (): void {
    resetTestState();

    $tempDir = sys_get_temp_dir() . '/tasty-fonts-virtual-' . uniqid();
    $storage = new VirtualStorage($tempDir, 'https://example.test/fonts');
    $cssCache = makeMinimalGeneratedCssCache();
    $log = new LogRepository();
    $file = new GeneratedStylesheetFile($storage, $cssCache, $log);

    $state = $file->getState();

    assertSameValue($storage->getGeneratedCssPath(), $state['path'], 'GeneratedStylesheetFile should resolve the CSS path through StorageInterface.');
    assertSameValue($storage->getGeneratedCssUrl(), $state['url'], 'GeneratedStylesheetFile should resolve the CSS URL through StorageInterface.');
    assertSameValue($state['path'], $state['write_path'], 'GeneratedStylesheetFile write_path should match the resolved path.');

    // Clean up
    $storage->deleteAbsolutePath($tempDir);
};

$tests['generated_stylesheet_file_writes_versioned_css_through_virtual_storage'] = static function (): void {
    resetTestState();

    $tempDir = sys_get_temp_dir() . '/tasty-fonts-virtual-' . uniqid();
    $storage = new VirtualStorage($tempDir, 'https://example.test/fonts');
    $cssCache = makeMinimalGeneratedCssCache();
    $log = new LogRepository();
    $file = new GeneratedStylesheetFile($storage, $cssCache, $log);

    $state = $file->getState();
    $written = $file->writeFile($state);

    assertTrueValue($written, 'GeneratedStylesheetFile should write successfully through the virtual StorageInterface adapter.');

    $path = (string) $storage->getGeneratedCssPath();
    $contents = $storage->contentsForAbsolutePath($path);
    $expectedVersionedCss = $cssCache->getVersionedCss();

    assertSameValue($expectedVersionedCss, $contents, 'Written file contents should match the versioned CSS returned by the cache.');

    // Clean up
    $storage->deleteAbsolutePath($tempDir);
};

$tests['generated_stylesheet_file_ensure_file_writes_when_stale'] = static function (): void {
    resetTestState();

    $tempDir = sys_get_temp_dir() . '/tasty-fonts-virtual-' . uniqid();
    $storage = new VirtualStorage($tempDir, 'https://example.test/fonts');
    $cssCache = makeMinimalGeneratedCssCache();
    $log = new LogRepository();
    $file = new GeneratedStylesheetFile($storage, $cssCache, $log);

    $result = $file->ensureFile(false);

    assertTrueValue($result, 'ensureFile should return true when writing through the virtual StorageInterface adapter.');
    assertTrueValue($storage->getAbsoluteFileState((string) $storage->getGeneratedCssPath())['exists'], 'ensureFile should create the generated CSS file on the virtual adapter.');

    // Clean up
    $storage->deleteAbsolutePath($tempDir);
};

$tests['generated_stylesheet_file_returns_versioned_url_with_hash'] = static function (): void {
    resetTestState();

    $tempDir = sys_get_temp_dir() . '/tasty-fonts-virtual-' . uniqid();
    $storage = new VirtualStorage($tempDir, 'https://example.test/fonts');
    $cssCache = makeMinimalGeneratedCssCache();
    $log = new LogRepository();
    $file = new GeneratedStylesheetFile($storage, $cssCache, $log);

    // Write the file so the versioned URL uses the file hash instead of the plugin version fallback.
    $file->ensureFile(false);

    $url = $file->getVersionedStylesheetUrl();
    $expectedHash = substr($cssCache->expectedFileHash(), 0, 16);

    assertTrueValue($url !== null, 'getVersionedStylesheetUrl should return a non-null URL when storage provides one.');
    assertContainsValue('ver=' . $expectedHash, (string) $url, 'Versioned URL should include the expected hash fragment.');

    // Clean up
    $storage->deleteAbsolutePath($tempDir);
};

$tests['generated_stylesheet_file_does_not_write_when_already_current'] = static function (): void {
    resetTestState();

    $tempDir = sys_get_temp_dir() . '/tasty-fonts-virtual-' . uniqid();
    $storage = new VirtualStorage($tempDir, 'https://example.test/fonts');
    $cssCache = makeMinimalGeneratedCssCache();
    $log = new LogRepository();
    $file = new GeneratedStylesheetFile($storage, $cssCache, $log);

    // First write
    $file->ensureFile(false);

    assertSameValue(1, $storage->writeCount, 'First ensureFile should perform exactly one write.');

    // Second call should see the file as current and skip the write
    $result = $file->ensureFile(false);

    assertTrueValue($result, 'ensureFile should return true when the file is already current.');
    assertSameValue(1, $storage->writeCount, 'ensureFile should not perform a second write when the file is already current.');

    // Clean up
    $storage->deleteAbsolutePath($tempDir);
};

$tests['generated_stylesheet_file_preserves_filesystem_invariants_with_virtual_adapter'] = static function (): void {
    resetTestState();

    $tempDir = sys_get_temp_dir() . '/tasty-fonts-virtual-' . uniqid();
    $storage = new VirtualStorage($tempDir, 'https://example.test/fonts');
    $cssCache = makeMinimalGeneratedCssCache();
    $log = new LogRepository();
    $file = new GeneratedStylesheetFile($storage, $cssCache, $log);

    $file->ensureFile(false);

    $path = (string) $storage->getGeneratedCssPath();

    assertTrueValue(str_starts_with(wp_normalize_path($path), wp_normalize_path($tempDir) . '/'), 'Generated CSS path must remain within the virtual managed root.');
    assertTrueValue(str_contains($path, '.generated'), 'Generated CSS path must include the .generated directory segment.');
    assertTrueValue(str_ends_with($path, 'tasty-fonts.css'), 'Generated CSS filename must remain tasty-fonts.css.');

    // Clean up
    $storage->deleteAbsolutePath($tempDir);
};

$tests['generated_stylesheet_file_status_and_expected_hash_use_storage_file_state'] = static function (): void {
    resetTestState();

    $tempDir = sys_get_temp_dir() . '/tasty-fonts-virtual-' . uniqid();
    $storage = new VirtualStorage($tempDir, 'https://example.test/fonts');
    $cssCache = makeMinimalGeneratedCssCache();
    $log = new LogRepository();
    $file = new GeneratedStylesheetFile($storage, $cssCache, $log);

    $file->ensureFile(false);
    $status = $file->getStatus();
    $expectedHash = $cssCache->expectedFileHash();

    assertSameValue($expectedHash, $file->expectedFileHash(), 'GeneratedStylesheetFile should delegate expectedFileHash to the generated CSS cache.');
    assertSameValue($expectedHash, $status['expected_hash'], 'Status should expose the expected generated file hash.');
    assertSameValue($expectedHash, $status['current_hash'], 'Status should read the current hash from StorageInterface file state.');
    assertTrueValue($status['is_current'], 'Status should mark the virtual generated CSS file current after a successful write.');
    assertSameValue(strlen($cssCache->getVersionedCss()), $status['size'], 'Status should read size from StorageInterface file state.');
};

$tests['generated_stylesheet_file_returns_empty_state_when_storage_has_no_generated_path_or_url'] = static function (): void {
    resetTestState();

    $storage = new VirtualStorage('/tmp/fonts-root', 'https://example.test/fonts', '', '');
    $cssCache = makeMinimalGeneratedCssCache();
    $log = new LogRepository();
    $file = new GeneratedStylesheetFile($storage, $cssCache, $log);

    $state = $file->getState();

    assertSameValue('', $state['path'], 'Generated stylesheet state should preserve the empty generated path from storage.');
    assertSameValue('', $state['url'], 'Generated stylesheet state should preserve the empty generated URL from storage.');
    assertFalseValue($state['exists'], 'Generated stylesheet state should not report a file when storage has no path.');
    assertFalseValue($state['is_current'], 'Generated stylesheet state should not be current without a generated path.');
    assertFalseValue($file->ensureFile(false), 'ensureFile should fail without a generated path.');
    assertSameValue(null, $file->getVersionedStylesheetUrl(), 'Versioned stylesheet URL should be null without a generated URL.');
    assertSameValue(0, $storage->writeAttempts, 'ensureFile should not attempt a storage write without a generated path.');
};

$tests['generated_stylesheet_file_logs_and_reports_write_failures_through_storage_interface'] = static function (): void {
    resetTestState();

    $tempDir = sys_get_temp_dir() . '/tasty-fonts-virtual-' . uniqid();
    $storage = new VirtualStorage($tempDir, 'https://example.test/fonts', null, null, true);
    $cssCache = makeMinimalGeneratedCssCache();
    $log = new LogRepository();
    $file = new GeneratedStylesheetFile($storage, $cssCache, $log);

    $result = $file->ensureFile(true);
    $entries = $log->all();

    assertFalseValue($result, 'ensureFile should return false when the StorageInterface write fails.');
    assertSameValue(1, $storage->writeAttempts, 'GeneratedStylesheetFile should attempt one write when stale.');
    assertSameValue(0, $storage->writeCount, 'Failed writes should not be counted as successful virtual storage writes.');
    assertContainsValue('Could not write generated CSS file', $entries[0]['message'] ?? '', 'GeneratedStylesheetFile should log the inline fallback write failure message.');
};

$tests['generated_stylesheet_file_uses_storage_state_without_native_file_materialization'] = static function (): void {
    resetTestState();

    $tempDir = sys_get_temp_dir() . '/tasty-fonts-virtual-' . uniqid();
    $storage = new VirtualStorage($tempDir, 'https://example.test/fonts');
    $cssCache = makeMinimalGeneratedCssCache();
    $log = new LogRepository();
    $file = new GeneratedStylesheetFile($storage, $cssCache, $log);

    $file->ensureFile(false);
    $path = (string) $storage->getGeneratedCssPath();
    $state = $file->getState();

    assertFalseValue(file_exists($path), 'Virtual storage should not materialize the generated stylesheet on disk.');
    assertTrueValue($state['exists'], 'GeneratedStylesheetFile should still see the virtual file through StorageInterface state.');
    assertSameValue($cssCache->expectedFileHash(), $state['current_hash'], 'GeneratedStylesheetFile should use StorageInterface state instead of hashing a native file.');
};

$tests['virtual_storage_adapter_rejects_paths_outside_managed_root'] = static function (): void {
    $storage = new VirtualStorage('/tmp/fonts-root', 'https://example.test/fonts');

    assertTrueValue($storage->isWithinRoot('/tmp/fonts-root/.generated/tasty-fonts.css'), 'Paths inside the managed root should be accepted.');
    assertTrueValue($storage->isWithinRoot('/tmp/fonts-root'), 'The root path itself should be accepted.');
    assertFalseValue($storage->isWithinRoot('/tmp/fonts-root-sibling/tasty-fonts.css'), 'Paths outside the managed root should be rejected.');
    assertFalseValue($storage->isWithinRoot('/tmp'), 'Parent directories should be rejected.');
    assertFalseValue($storage->isWithinRoot(''), 'Empty paths should be rejected.');
};
