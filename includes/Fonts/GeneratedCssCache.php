<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\RoleRepository;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use TastyFonts\Support\TransientKey;

/**
 * Builds and caches the generated CSS payload for the active runtime catalog.
 */
final class GeneratedCssCache
{
    private const CONTENT_HASH_ALGORITHM = 'sha256';

    private ?string $css = null;
    private ?string $hash = null;

    public function __construct(
        private readonly CatalogService $catalog,
        private readonly SettingsRepository $settings,
        private readonly CssBuilder $cssBuilder,
        private readonly RuntimeAssetPlanner $planner,
        private readonly RoleRepository $roleRepo,
        private readonly string $cssTransientKey,
        private readonly string $hashTransientKey
    ) {
    }

    /**
     * Clear cached generated CSS and hash data from memory and transients.
     */
    public function invalidate(): void
    {
        delete_transient(TransientKey::forSite($this->cssTransientKey));
        delete_transient(TransientKey::forSite($this->hashTransientKey));
        $this->css = null;
        $this->hash = null;
    }

    /**
     * Return the generated CSS payload for the current runtime catalog.
     */
    public function getCss(): string
    {
        if (is_string($this->css)) {
            return $this->css;
        }

        $cachedCss = get_transient(TransientKey::forSite($this->cssTransientKey));
        $cachedHash = get_transient(TransientKey::forSite($this->hashTransientKey));

        if (is_string($cachedCss) && is_string($cachedHash)) {
            $this->css = $cachedCss;
            $this->hash = $cachedHash;

            return $this->css;
        }

        $catalog = $this->catalog->getCatalog();
        $localCatalog = $this->planner->getLocalRuntimeCatalog();
        $variableFamilies = $this->planner->getRuntimeVariableFamilies();
        $settings = $this->settings->getSettings();
        $roles = !empty($settings['auto_apply_roles'])
            ? $this->roleRepo->getAppliedRoles($catalog)
            : $this->roleRepo->getRoles($catalog);

        $this->css = $this->cssBuilder->build($localCatalog, $roles, $settings, $variableFamilies);
        $this->css = FontUtils::scalarStringValue(apply_filters('tasty_fonts_generated_css', $this->css, $localCatalog, $roles, $settings));
        $this->hash = $this->hashContents($this->css);

        set_transient(TransientKey::forSite($this->cssTransientKey), $this->css, DAY_IN_SECONDS);
        set_transient(TransientKey::forSite($this->hashTransientKey), $this->hash, DAY_IN_SECONDS);

        return $this->css;
    }

    /**
     * Return the cached hash for the generated CSS payload.
     */
    public function getHash(): string
    {
        if ($this->hash !== null) {
            return $this->hash;
        }

        $this->getCss();

        return (string) $this->hash;
    }

    /**
     * Return generated CSS with the plugin version header used for file writes.
     */
    public function getVersionedCss(): string
    {
        return "/* Version: " . TASTY_FONTS_VERSION . " */\n" . $this->getCss();
    }

    /**
     * Return the hash expected for the versioned generated stylesheet file.
     */
    public function expectedFileHash(): string
    {
        return $this->hashContents($this->getVersionedCss());
    }

    /**
     * Hash arbitrary generated CSS/file contents using the canonical algorithm.
     */
    public function hashContents(string $contents): string
    {
        return hash(self::CONTENT_HASH_ALGORITHM, $contents);
    }
}
