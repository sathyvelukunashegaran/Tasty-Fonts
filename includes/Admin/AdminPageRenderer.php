<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Admin\Renderer\AbstractSectionRenderer;
use TastyFonts\Admin\Renderer\ActivitySectionRenderer;
use TastyFonts\Admin\Renderer\DiagnosticsSectionRenderer;
use TastyFonts\Admin\Renderer\FamilyCardRenderer;
use TastyFonts\Admin\Renderer\LibrarySectionRenderer;
use TastyFonts\Admin\Renderer\PreviewSectionRenderer;
use TastyFonts\Admin\Renderer\SettingsSectionRenderer;
use TastyFonts\Admin\Renderer\StudioSectionRenderer;
use TastyFonts\Admin\Renderer\ToolsSectionRenderer;
use TastyFonts\Support\Storage;

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
    private readonly ActivitySectionRenderer $activityRenderer;

    public function __construct(
        Storage $storage,
        ?AdminPageViewBuilder $viewBuilder = null,
        ?StudioSectionRenderer $studioRenderer = null,
        ?PreviewSectionRenderer $previewRenderer = null,
        ?ToolsSectionRenderer $toolsRenderer = null,
        ?SettingsSectionRenderer $settingsRenderer = null,
        ?DiagnosticsSectionRenderer $diagnosticsRenderer = null,
        ?FamilyCardRenderer $familyCardRenderer = null,
        ?LibrarySectionRenderer $libraryRenderer = null,
        ?ActivitySectionRenderer $activityRenderer = null
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
        $this->activityRenderer = $activityRenderer ?? new ActivitySectionRenderer($storage);
    }

    public function render(array $view): void
    {
        $this->renderPage($view);
    }

    public function renderPage(array $context): void
    {
        $view = $this->viewBuilder->build($context);
        $this->trainingWheelsOff = !empty($view['trainingWheelsOff']);
        $storage = $view['storage'] ?? null;
        $toasts = is_array($view['toasts'] ?? null) ? $view['toasts'] : [];
        $trainingWheelsOff = !empty($view['trainingWheelsOff']);
        $storageErrorMessage = (string) ($view['storageErrorMessage'] ?? '');
        $currentPage = (string) ($view['currentPage'] ?? AdminController::PAGE_ROLES);
        $pluginVersion = (string) ($view['pluginVersion'] ?? '');
        $pluginVersionUrl = (string) ($view['pluginVersionUrl'] ?? '');

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
        $this->activityRenderer->render($view);
        $diagnosticsSection = (string) ob_get_clean();
        ?>
        <div class="wrap tasty-fonts-admin<?php echo $trainingWheelsOff ? ' is-training-wheels-off' : ''; ?>">
            <?php $this->renderNotices($toasts); ?>

            <?php if (!$storage): ?>
                <div class="notice notice-error"><p><?php echo esc_html($storageErrorMessage !== '' ? $storageErrorMessage : __('The font storage directory could not be initialized.', 'tasty-fonts')); ?></p></div>
            <?php else: ?>
                <div class="tasty-fonts-shell" data-current-page="<?php echo esc_attr($currentPage); ?>">
                    <section class="tasty-fonts-card tasty-fonts-page-header">
                        <div class="tasty-fonts-page-header-brand">
                            <div class="tasty-fonts-hero-title-row">
                                <h1 class="tasty-fonts-header-title">
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
                                    <?php if ($pluginVersion !== ''): ?>
                                        <span class="screen-reader-text">
                                            <?php echo esc_html(sprintf(__('Version %s', 'tasty-fonts'), $pluginVersion)); ?>
                                        </span>
                                    <?php endif; ?>
                                </h1>
                                <?php if ($pluginVersion !== ''): ?>
                                    <a
                                        class="tasty-fonts-version-link tasty-fonts-badge is-role"
                                        href="<?php echo esc_url($pluginVersionUrl); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label="<?php echo esc_attr(sprintf(__('View GitHub changelog for version %s', 'tasty-fonts'), $pluginVersion)); ?>"
                                        title="<?php echo esc_attr(sprintf(__('View changelog for version %s on GitHub', 'tasty-fonts'), $pluginVersion)); ?>"
                                    >
                                        <?php echo esc_html(sprintf(__('v%s', 'tasty-fonts'), $pluginVersion)); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tasty-fonts-page-header-nav">
                            <div class="tasty-fonts-studio-switcher tasty-fonts-tab-list" role="tablist" aria-label="<?php esc_attr_e('Tasty Fonts sections', 'tasty-fonts'); ?>" aria-orientation="horizontal">
                                <?php foreach ([
                                    AdminController::PAGE_ROLES => __('Deploy Fonts', 'tasty-fonts'),
                                    AdminController::PAGE_LIBRARY => __('Font Library', 'tasty-fonts'),
                                    AdminController::PAGE_SETTINGS => __('Settings', 'tasty-fonts'),
                                    AdminController::PAGE_DIAGNOSTICS => __('Advanced Tools', 'tasty-fonts'),
                                ] as $pageKey => $pageLabel): ?>
                                    <?php $isActive = $currentPage === $pageKey; ?>
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button <?php echo $isActive ? 'is-active' : ''; ?>"
                                        id="tasty-fonts-page-tab-<?php echo esc_attr($pageKey); ?>"
                                        data-tab-group="page"
                                        data-tab-target="<?php echo esc_attr($pageKey); ?>"
                                        aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
                                        tabindex="<?php echo $isActive ? '0' : '-1'; ?>"
                                        aria-controls="tasty-fonts-page-panel-<?php echo esc_attr($pageKey); ?>"
                                        role="tab"
                                    >
                                        <?php echo esc_html($pageLabel); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
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

    protected function renderCodePreviewScene(string $previewText, array $roles, bool $monospaceRoleEnabled, array $familyLabels = []): void
    {
        $this->syncRendererState($this->previewRenderer);
        $this->previewRenderer->renderCodePreviewScene($previewText, $roles, $monospaceRoleEnabled, $familyLabels);
    }

    protected function renderCodeEditor(array $panel, array $options = []): void
    {
        $this->syncRendererState($this->toolsRenderer);
        $this->toolsRenderer->renderCodeEditor($panel, $options);
    }

    private function syncRendererState(AbstractSectionRenderer $renderer): void
    {
        $renderer->setTrainingWheelsOff($this->trainingWheelsOff);
    }
}
