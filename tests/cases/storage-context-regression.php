<?php

declare(strict_types=1);

$tests['storage_uses_existing_parent_directory_as_filesystem_context_for_missing_paths'] = static function (): void {
    resetTestState();

    $storage = new TastyFonts\Support\Storage();
    $root = uniqueTestDirectory('storage-context-parent');
    $targetPath = $root . '/.generated/tasty-fonts.css';

    wp_mkdir_p($root);

    $context = invokePrivateMethod($storage, 'filesystemContext', [$targetPath]);

    assertSameValue(
        wp_normalize_path($root),
        (string) $context,
        'Storage should resolve missing write targets to the nearest existing parent directory before checking the filesystem method.'
    );
};
