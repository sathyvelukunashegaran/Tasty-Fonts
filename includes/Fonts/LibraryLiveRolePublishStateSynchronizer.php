<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\ImportRepository;
use TastyFonts\Support\FontUtils;

/**
 * Synchronizes stored publish states with currently live role assignments.
 */
final class LibraryLiveRolePublishStateSynchronizer
{
    private const MANUAL_PUBLISH_STATES = ['library_only', 'published'];

    public function __construct(
        private readonly CatalogService $catalog,
        private readonly ImportRepository $imports,
        private readonly LibraryCatalogResolver $resolver,
        private readonly LibraryRoleProtectionPolicy $roles
    ) {
    }

    /**
     * @param array{heading?: string, body?: string, monospace?: string} $liveRoles Currently applied live role families.
     */
    public function sync(array $liveRoles, bool $sitewideEnabled): void
    {
        $liveFamilies = [];

        if ($sitewideEnabled) {
            foreach ($this->roles->liveRoleKeys() as $key) {
                $familyName = trim((string) ($liveRoles[$key] ?? ''));

                if ($familyName !== '') {
                    $liveFamilies[$familyName] = FontUtils::slugify($familyName);
                }
            }
        }

        foreach ($this->imports->allFamilies() as $storedFamily) {
            $familyName = trim($storedFamily['family']);
            $familySlug = FontUtils::slugify($storedFamily['slug']);

            if ($familyName === '' || $familySlug === '') {
                continue;
            }

            $targetState = isset($liveFamilies[$familyName])
                ? 'role_active'
                : $this->manualPublishStateForFamily($storedFamily);

            if ($storedFamily['publish_state'] !== $targetState) {
                $this->imports->setPublishState($familySlug, $targetState);
            }
        }

        foreach ($liveFamilies as $familyName => $familySlug) {
            $storedFamily = $this->imports->getFamily($familySlug);

            if ($storedFamily === null) {
                $catalogFamily = $this->resolver->findFamilyBySlug($familySlug) ?? [];
                $this->imports->ensureFamily(
                    $familyName,
                    $familySlug,
                    'role_active',
                    $this->resolver->stringValue($catalogFamily, 'active_delivery_id'),
                    'library_only'
                );
                continue;
            }

            if ($storedFamily['publish_state'] !== 'role_active') {
                $this->imports->setPublishState($familySlug, 'role_active');
            }
        }

        $this->catalog->invalidate();
    }

    /**
     * @param array<string, mixed> $family
     */
    private function manualPublishStateForFamily(array $family): string
    {
        $manualState = strtolower(trim($this->resolver->stringValue($family, 'manual_publish_state')));

        if (in_array($manualState, self::MANUAL_PUBLISH_STATES, true)) {
            return $manualState;
        }

        $publishState = strtolower(trim($this->resolver->stringValue($family, 'publish_state', 'published')));

        return in_array($publishState, self::MANUAL_PUBLISH_STATES, true) ? $publishState : 'published';
    }
}
