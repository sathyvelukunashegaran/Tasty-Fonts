<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Admin\Renderer\AbstractSectionRenderer;
use TastyFonts\Admin\Renderer\DiagnosticsSectionRenderer;
use TastyFonts\Admin\Renderer\FamilyCardRenderer;
use TastyFonts\Admin\Renderer\LibrarySectionRenderer;
use TastyFonts\Admin\Renderer\PreviewSectionRenderer;
use TastyFonts\Admin\Renderer\SettingsSectionRenderer;
use TastyFonts\Admin\Renderer\StudioSectionRenderer;
use TastyFonts\Admin\Renderer\ToolsSectionRenderer;
use TastyFonts\Support\Storage;

/**
 * @phpstan-import-type PageContext from AdminPageContextBuilder
 * @phpstan-import-type CatalogFamily from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type CatalogFace from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type DeliveryProfile from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type RoleSet from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type FamilyFallbackMap from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type FamilyFontDisplayMap from \TastyFonts\Repository\SettingsRepository
 */
final class AdminPageRenderer extends AbstractSectionRenderer
{
    private readonly AdminPageViewBuilder $viewBuilder;
    private readonly StudioSectionRenderer $studioRenderer;
    private readonly PreviewSectionRenderer $previewRenderer;
    private readonly ToolsSectionRenderer $toolsRenderer;
    private readonly SettingsSectionRenderer $settingsRenderer;
    private readonly DiagnosticsSectionRenderer $diagnosticsRenderer;
    private readonly FamilyCardRenderer $familyCardRenderer;
    private readonly LibrarySectionRenderer $libraryRenderer;

    public function __construct(
        Storage $storage,
        ?AdminPageViewBuilder $viewBuilder = null,
        ?StudioSectionRenderer $studioRenderer = null,
        ?PreviewSectionRenderer $previewRenderer = null,
        ?ToolsSectionRenderer $toolsRenderer = null,
        ?SettingsSectionRenderer $settingsRenderer = null,
        ?DiagnosticsSectionRenderer $diagnosticsRenderer = null,
        ?FamilyCardRenderer $familyCardRenderer = null,
        ?LibrarySectionRenderer $libraryRenderer = null
    ) {
        parent::__construct($storage);
        $this->viewBuilder = $viewBuilder ?? new AdminPageViewBuilder($storage);
        $this->previewRenderer = $previewRenderer ?? new PreviewSectionRenderer($storage);
        $this->toolsRenderer = $toolsRenderer ?? new ToolsSectionRenderer($storage);
        $this->settingsRenderer = $settingsRenderer ?? new SettingsSectionRenderer($storage);
        $this->diagnosticsRenderer = $diagnosticsRenderer ?? new DiagnosticsSectionRenderer($storage, $this->toolsRenderer);
        $this->familyCardRenderer = $familyCardRenderer ?? new FamilyCardRenderer($storage);
        $this->studioRenderer = $studioRenderer ?? new StudioSectionRenderer($storage, $this->previewRenderer, $this->toolsRenderer);
        $this->libraryRenderer = $libraryRenderer ?? new LibrarySectionRenderer($storage, $this->familyCardRenderer);
    }

    /**
     * @param array<string, mixed> $view
     */
    public function render(array $view): void
    {
        $this->renderPage($view);
    }

    /**
     * @param PageContext $context
     */
    public function renderPage(array $context): void
    {
        $view = $this->viewBuilder->build($context);
        $this->trainingWheelsOff = !empty($view['trainingWheelsOff']);
        $storage = $view['storage'] ?? null;
        $toasts = $this->normalizeToastList($view['toasts'] ?? []);
        $trainingWheelsOff = !empty($view['trainingWheelsOff']);
        $storageErrorMessage = $this->stringValue($view, 'storageErrorMessage');
        $currentPage = $this->stringValue($view, 'currentPage', AdminController::PAGE_ROLES);
        $pluginVersion = $this->stringValue($view, 'pluginVersion');
        $pluginVersionUrl = $this->stringValue($view, 'pluginVersionUrl');
        $pluginVersionMeta = $this->stringValue($view, 'pluginVersionMeta');
        $pluginVersionBadgeClass = $this->stringValue($view, 'pluginVersionBadgeClass', 'is-role');
        $pluginVersionTooltip = $this->stringValue($view, 'pluginVersionTooltip');
        $pluginVersionAriaLabel = $this->stringValue($view, 'pluginVersionAriaLabel');
        $pageTabs = self::pageTabs();
        $pageHeader = $pageTabs[$currentPage] ?? $pageTabs[AdminController::PAGE_ROLES];

        ob_start();
        $this->studioRenderer->render($view);
        $rolesSection = (string) ob_get_clean();

        ob_start();
        $this->libraryRenderer->render($view);
        $librarySection = (string) ob_get_clean();

        ob_start();
        $this->settingsRenderer->render($view);
        $settingsSection = (string) ob_get_clean();

        ob_start();
        $this->diagnosticsRenderer->render($view);
        $diagnosticsSection = (string) ob_get_clean();
        ?>
        <div class="wrap tasty-fonts-admin<?php echo $trainingWheelsOff ? ' is-training-wheels-off' : ''; ?>">
            <?php $this->renderNotices($toasts); ?>

            <?php if (!$storage): ?>
                <div class="notice notice-error"><p><?php echo esc_html($storageErrorMessage !== '' ? $storageErrorMessage : __('The font storage directory could not be initialized.', 'tasty-fonts')); ?></p></div>
            <?php else: ?>
                <div class="tasty-fonts-shell" data-current-page="<?php echo esc_attr($currentPage); ?>">
                    <section class="tasty-fonts-card tasty-fonts-page-header">
                        <div class="tasty-fonts-page-header-logo-wrap">
                            <a
                                class="tasty-fonts-header-logo-link"
                                href="https://tastywp.com/tastyfonts/"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="<?php esc_attr_e('Visit Tasty Fonts on TastyWP', 'tasty-fonts'); ?>"
                                title="<?php esc_attr_e('Visit Tasty Fonts on TastyWP', 'tasty-fonts'); ?>"
                            >
                                <span class="tasty-fonts-header-logo" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e('Tasty Custom Fonts', 'tasty-fonts'); ?></span>
                            </a>
                        </div>

                        <div class="tasty-fonts-page-header-brand">
                            <div class="tasty-fonts-page-header-copy">
                                <h1 class="tasty-fonts-header-title" data-page-header-title><?php echo esc_html($pageHeader['label']); ?></h1>
                                <p class="tasty-fonts-page-header-summary" data-page-header-summary><?php echo esc_html($pageHeader['summary']); ?></p>
                            </div>
                        </div>

                        <div class="tasty-fonts-page-header-nav">
                            <div class="tasty-fonts-page-header-title-row tasty-fonts-page-header-title-row--nav">
                                <p class="tasty-fonts-page-header-kicker"><?php esc_html_e('Typography Management for Pros', 'tasty-fonts'); ?></p>
                                <?php if ($pluginVersion !== ''): ?>
                                    <div class="tasty-fonts-page-header-meta tasty-fonts-page-header-meta--inline">
                                        <span class="screen-reader-text">
                                            <?php echo esc_html(sprintf(__('Version %s', 'tasty-fonts'), $pluginVersion)); ?>
                                        </span>
                                        <a
                                            class="tasty-fonts-version-link tasty-fonts-badge <?php echo esc_attr($pluginVersionBadgeClass); ?>"
                                            href="<?php echo esc_url($pluginVersionUrl); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label="<?php echo esc_attr($pluginVersionAriaLabel !== '' ? $pluginVersionAriaLabel : sprintf(__('View GitHub changelog for version %s', 'tasty-fonts'), $pluginVersion)); ?>"
                                            title="<?php echo esc_attr($pluginVersionTooltip !== '' ? $pluginVersionTooltip : sprintf(__('View changelog for version %s on GitHub', 'tasty-fonts'), $pluginVersion)); ?>"
                                        >
                                            <span class="tasty-fonts-version-link-primary"><?php echo esc_html(sprintf(__('v%s', 'tasty-fonts'), $pluginVersion)); ?></span>
                                            <?php if ($pluginVersionMeta !== ''): ?>
                                                <span class="tasty-fonts-version-link-meta"><?php echo esc_html($pluginVersionMeta); ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="tasty-fonts-studio-switcher tasty-fonts-tab-list" role="tablist" aria-label="<?php esc_attr_e('Tasty Fonts sections', 'tasty-fonts'); ?>" aria-orientation="horizontal">
                                <?php foreach ($pageTabs as $pageKey => $pageConfig): ?>
                                    <?php $isActive = $currentPage === $pageKey; ?>
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button <?php echo $isActive ? 'is-active' : ''; ?>"
                                        id="tasty-fonts-page-tab-<?php echo esc_attr($pageKey); ?>"
                                        data-tab-group="page"
                                        data-tab-target="<?php echo esc_attr($pageKey); ?>"
                                        data-page-label="<?php echo esc_attr($pageConfig['label']); ?>"
                                        data-page-summary="<?php echo esc_attr($pageConfig['summary']); ?>"
                                        aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
                                        tabindex="<?php echo $isActive ? '0' : '-1'; ?>"
                                        aria-controls="tasty-fonts-page-panel-<?php echo esc_attr($pageKey); ?>"
                                        role="tab"
                                    >
                                        <?php echo esc_html($pageConfig['label']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <span class="tasty-fonts-page-header-hairline" aria-hidden="true"></span>
                    </section>

                    <section id="tasty-fonts-page-panel-roles" class="tasty-fonts-page-panel <?php echo $currentPage === AdminController::PAGE_ROLES ? 'is-active' : ''; ?>" data-tab-group="page" data-tab-panel="<?php echo esc_attr(AdminController::PAGE_ROLES); ?>" role="tabpanel" aria-labelledby="tasty-fonts-page-tab-roles" <?php echo $currentPage === AdminController::PAGE_ROLES ? '' : 'hidden'; ?>>
                        <?php echo $rolesSection; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </section>
                    <section id="tasty-fonts-page-panel-library" class="tasty-fonts-page-panel <?php echo $currentPage === AdminController::PAGE_LIBRARY ? 'is-active' : ''; ?>" data-tab-group="page" data-tab-panel="<?php echo esc_attr(AdminController::PAGE_LIBRARY); ?>" role="tabpanel" aria-labelledby="tasty-fonts-page-tab-library" <?php echo $currentPage === AdminController::PAGE_LIBRARY ? '' : 'hidden'; ?>>
                        <?php echo $librarySection; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </section>
                    <section id="tasty-fonts-page-panel-settings" class="tasty-fonts-page-panel <?php echo $currentPage === AdminController::PAGE_SETTINGS ? 'is-active' : ''; ?>" data-tab-group="page" data-tab-panel="<?php echo esc_attr(AdminController::PAGE_SETTINGS); ?>" role="tabpanel" aria-labelledby="tasty-fonts-page-tab-settings" <?php echo $currentPage === AdminController::PAGE_SETTINGS ? '' : 'hidden'; ?>>
                        <?php echo $settingsSection; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </section>
                    <section id="tasty-fonts-page-panel-diagnostics" class="tasty-fonts-page-panel <?php echo $currentPage === AdminController::PAGE_DIAGNOSTICS ? 'is-active' : ''; ?>" data-tab-group="page" data-tab-panel="<?php echo esc_attr(AdminController::PAGE_DIAGNOSTICS); ?>" role="tabpanel" aria-labelledby="tasty-fonts-page-tab-diagnostics" <?php echo $currentPage === AdminController::PAGE_DIAGNOSTICS ? '' : 'hidden'; ?>>
                        <?php echo $diagnosticsSection; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </section>
                    <div id="tasty-fonts-help-tooltip-layer" class="tasty-fonts-help-tooltip-layer" role="tooltip" hidden></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param CatalogFamily $family
     * @param RoleSet $roles
     * @param FamilyFallbackMap $familyFallbacks
     * @param FamilyFontDisplayMap $familyFontDisplays
     * @param list<array<string, mixed>> $familyFontDisplayOptions
     * @param array<string, string> $categoryAliasOwners
     * @param array<string, mixed> $extendedVariableOptions
     * @param array<string, mixed> $classOutputOptions
     */
    protected function renderFamilyRow(
        array $family,
        array $roles,
        array $familyFallbacks,
        array $familyFontDisplays,
        array $familyFontDisplayOptions,
        string $previewText,
        array $categoryAliasOwners = [],
        array $extendedVariableOptions = [],
        bool $monospaceRoleEnabled = false,
        array $classOutputOptions = []
    ): void {
        $this->syncRendererState($this->familyCardRenderer);
        $this->familyCardRenderer->renderFamilyRow(
            $family,
            $roles,
            $familyFallbacks,
            $familyFontDisplays,
            $familyFontDisplayOptions,
            $previewText,
            $categoryAliasOwners,
            $extendedVariableOptions,
            $monospaceRoleEnabled,
            $classOutputOptions
        );
    }

    /**
     * @param mixed $value
     * @return list<array<string, mixed>>
     */
    private function normalizeToastList(mixed $value): array
    {
        return \TastyFonts\Support\FontUtils::normalizeListOfStringKeyedMaps($value);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? null;

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param list<string> $assignedRoleKeys
     * @param array<string, string> $categoryAliasOwners
     * @param array<string, mixed> $extendedVariableOptions
     * @param DeliveryProfile $activeDelivery
     * @param CatalogFace $face
     */
    protected function renderFaceDetailCard(
        string $familyName,
        string $familySlug,
        string $defaultStack,
        string $facePreviewText,
        int $faceCount,
        array $assignedRoleKeys,
        string $fontCategory,
        array $categoryAliasOwners,
        array $extendedVariableOptions,
        array $activeDelivery,
        array $face,
        bool $isMonospace = false
    ): void {
        $this->syncRendererState($this->familyCardRenderer);
        $this->familyCardRenderer->renderFaceDetailCard(
            $familyName,
            $familySlug,
            $defaultStack,
            $facePreviewText,
            $faceCount,
            $assignedRoleKeys,
            $fontCategory,
            $categoryAliasOwners,
            $extendedVariableOptions,
            $activeDelivery,
            $face,
            $isMonospace
        );
    }

    /**
     * @param RoleSet $roles
     * @param array<string, string> $familyLabels
     */
    protected function renderCodePreviewScene(string $previewText, array $roles, bool $monospaceRoleEnabled, array $familyLabels = []): void
    {
        $this->syncRendererState($this->previewRenderer);
        $this->previewRenderer->renderCodePreviewScene($previewText, $roles, $monospaceRoleEnabled, $familyLabels);
    }

    /**
     * @param array<string, mixed> $panel
     * @param array<string, mixed> $options
     */
    protected function renderCodeEditor(array $panel, array $options = []): void
    {
        $this->syncRendererState($this->toolsRenderer);
        $this->toolsRenderer->renderCodeEditor($panel, $options);
    }

    private function syncRendererState(AbstractSectionRenderer $renderer): void
    {
        $renderer->setTrainingWheelsOff($this->trainingWheelsOff);
    }

    /**
     * @return array<string, array{label: string, summary: string}>
     */
    private static function pageTabs(): array
    {
        return [
            AdminController::PAGE_ROLES => [
                'label' => __('Deploy Fonts', 'tasty-fonts'),
                'summary' => __('Pair role fonts, review, and publish.', 'tasty-fonts'),
            ],
            AdminController::PAGE_LIBRARY => [
                'label' => __('Font Library', 'tasty-fonts'),
                'summary' => __('Search, import, and manage families and delivery profiles.', 'tasty-fonts'),
            ],
            AdminController::PAGE_SETTINGS => [
                'label' => __('Settings', 'tasty-fonts'),
                'summary' => __('Configure output, integrations, behavior, and access.', 'tasty-fonts'),
            ],
            AdminController::PAGE_DIAGNOSTICS => [
                'label' => __('Advanced Tools', 'tasty-fonts'),
                'summary' => __('Inspect assets, caches, and maintenance.', 'tasty-fonts'),
            ],
        ];
    }
}
