<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;
use WP_Error;

/**
 * Deletes a font family and coordinates storage, repository, assets, hooks, and logging.
 */
final class LibraryFamilyDeletionAction
{
    public function __construct(
        private readonly Storage $storage,
        private readonly ImportRepository $imports,
        private readonly AssetService $assets,
        private readonly LogRepository $log,
        private readonly LibraryCatalogResolver $resolver,
        private readonly LibraryPathCollector $paths,
        private readonly LibraryRoleProtectionPolicy $roles,
        private readonly LibraryMutationErrorFactory $errors
    ) {
    }

    /**
     * @return bool|WP_Error True when the family is deleted, or a WordPress error when deletion is blocked or fails.
     */
    public function delete(string $familySlug): bool|WP_Error
    {
        $familySlug = FontUtils::slugify($familySlug);
        $family = $this->resolver->findFamilyBySlug($familySlug);

        if ($family === null) {
            return $this->errors->error(
                'tasty_fonts_family_not_found',
                __('That font family could not be found in the library.', 'tasty-fonts')
            );
        }

        $familyName = $this->resolver->stringValue($family, 'family', $familySlug);
        $roleLabels = $this->roles->getProtectedRoleLabels($familyName);

        if ($roleLabels !== []) {
            return $this->errors->error(
                'tasty_fonts_family_in_use',
                $this->roles->buildDeleteFamilyBlockedMessage($familyName, $roleLabels)
            );
        }

        $relativePaths = $this->paths->collectFamilyRelativePaths($family);

        if (!$this->storage->deleteRelativeFiles($relativePaths)) {
            return $this->errors->error(
                'tasty_fonts_delete_failed',
                $this->storageErrorMessage(__('The font files could not be deleted from the font storage directory.', 'tasty-fonts'))
            );
        }

        foreach ($this->paths->managedImportSourcesForFamily($family) as $source) {
            $this->storage->deleteRelativeDirectory($source . '/' . $familySlug);
        }

        $this->imports->deleteFamily($familySlug);
        $this->assets->refreshGeneratedAssets();
        do_action('tasty_fonts_after_delete_family', $familySlug, $familyName);

        $fileCount = count($relativePaths);
        $this->log->add(
            sprintf(
                _n(
                    'Font family deleted: %1$s (%2$d file removed).',
                    'Font family deleted: %1$s (%2$d files removed).',
                    $fileCount,
                    'tasty-fonts'
                ),
                $familyName,
                $fileCount
            ),
            [
                'category' => LogRepository::CATEGORY_LIBRARY,
                'event' => 'family_deleted',
                'outcome' => 'danger',
                'status_label' => __('Deleted', 'tasty-fonts'),
                'source' => __('Library', 'tasty-fonts'),
                'entity_type' => 'font_family',
                'entity_id' => $familySlug,
                'entity_name' => $familyName,
                'details' => [
                    ['label' => __('Family', 'tasty-fonts'), 'value' => $familyName],
                    ['label' => __('Files removed', 'tasty-fonts'), 'value' => (string) $fileCount, 'kind' => 'count'],
                ],
            ]
        );

        return true;
    }

    private function storageErrorMessage(string $fallback): string
    {
        $message = trim($this->storage->getLastFilesystemErrorMessage());

        return $message !== '' ? $message : $fallback;
    }
}
