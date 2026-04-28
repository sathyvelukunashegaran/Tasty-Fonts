<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Support\FontUtils;
use WP_Error;

/**
 * Saves manual publish-state transitions for library families.
 */
final class LibraryPublishStateAction
{
    private const MANUAL_PUBLISH_STATES = ['library_only', 'published'];

    public function __construct(
        private readonly ImportRepository $imports,
        private readonly AssetService $assets,
        private readonly LogRepository $log,
        private readonly LibraryCatalogResolver $resolver,
        private readonly LibraryRoleProtectionPolicy $roles,
        private readonly LibraryMutationErrorFactory $errors
    ) {
    }

    /**
     * @return array{
     *     family: string,
     *     family_slug: string,
     *     publish_state: string,
     *     message: string
     * }|WP_Error Result payload for the saved publish state, or a WordPress error when the change fails.
     */
    public function save(string $familySlug, string $publishState): array|WP_Error
    {
        $familySlug = FontUtils::slugify($familySlug);
        $publishState = strtolower(trim($publishState));
        $family = $this->resolver->findFamilyBySlug($familySlug);

        if ($family === null) {
            return $this->errors->error(
                'tasty_fonts_family_not_found',
                __('That font family could not be found in the library.', 'tasty-fonts')
            );
        }

        if (!in_array($publishState, self::MANUAL_PUBLISH_STATES, true)) {
            return $this->errors->error(
                'tasty_fonts_publish_state_invalid',
                __('Choose either Published or In Library Only.', 'tasty-fonts')
            );
        }

        $familyName = $this->resolver->stringValue($family, 'family', $familySlug);

        if ($publishState === 'library_only' && $this->roles->isLiveRoleFamily($familyName)) {
            return $this->errors->error(
                'tasty_fonts_family_live',
                __('This family is live through the current active roles. Switch roles or turn off sitewide usage before pausing it.', 'tasty-fonts')
            );
        }

        $storedFamily = $this->imports->getFamily($familySlug);

        if ($storedFamily === null) {
            $this->imports->ensureFamily(
                $familyName,
                $familySlug,
                $this->resolver->stringValue($family, 'publish_state', 'published'),
                $this->resolver->stringValue($family, 'active_delivery_id')
            );
        }

        $saved = $this->imports->setPublishState($familySlug, $publishState);

        if ($saved === null) {
            return $this->errors->error(
                'tasty_fonts_publish_state_failed',
                __('The family publish state could not be updated.', 'tasty-fonts')
            );
        }

        $this->assets->refreshGeneratedAssets();

        $message = $publishState === 'library_only'
            ? sprintf(__('%s is now In Library Only.', 'tasty-fonts'), $familyName)
            : sprintf(__('%s is now Published.', 'tasty-fonts'), $familyName);
        $this->log->add($message, [
            'category' => LogRepository::CATEGORY_LIBRARY,
            'event' => 'publish_state_changed',
            'outcome' => 'success',
            'status_label' => __('Updated', 'tasty-fonts'),
            'source' => __('Library', 'tasty-fonts'),
            'entity_type' => 'font_family',
            'entity_id' => $familySlug,
            'entity_name' => $familyName,
            'details' => [
                ['label' => __('Family', 'tasty-fonts'), 'value' => $familyName],
                ['label' => __('Publish state', 'tasty-fonts'), 'value' => $publishState === 'library_only' ? __('In Library Only', 'tasty-fonts') : __('Published', 'tasty-fonts')],
            ],
        ]);

        return [
            'family' => $familyName,
            'family_slug' => $familySlug,
            'publish_state' => $publishState,
            'message' => $message,
        ];
    }
}
