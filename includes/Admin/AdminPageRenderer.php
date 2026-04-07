<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Admin\Renderer\AbstractSectionRenderer;
use TastyFonts\Admin\Renderer\ActivitySectionRenderer;
use TastyFonts\Admin\Renderer\FamilyCardRenderer;
use TastyFonts\Admin\Renderer\LibrarySectionRenderer;
use TastyFonts\Admin\Renderer\PreviewSectionRenderer;
use TastyFonts\Admin\Renderer\StudioSectionRenderer;
use TastyFonts\Admin\Renderer\ToolsSectionRenderer;
use TastyFonts\Support\Storage;

final class AdminPageRenderer extends AbstractSectionRenderer
{
    private readonly AdminPageViewBuilder $viewBuilder;
    private readonly StudioSectionRenderer $studioRenderer;
    private readonly PreviewSectionRenderer $previewRenderer;
    private readonly ToolsSectionRenderer $toolsRenderer;
    private readonly FamilyCardRenderer $familyCardRenderer;
    private readonly LibrarySectionRenderer $libraryRenderer;
    private readonly ActivitySectionRenderer $activityRenderer;

    public function __construct(
        Storage $storage,
        ?AdminPageViewBuilder $viewBuilder = null,
        ?StudioSectionRenderer $studioRenderer = null,
        ?PreviewSectionRenderer $previewRenderer = null,
        ?ToolsSectionRenderer $toolsRenderer = null,
        ?FamilyCardRenderer $familyCardRenderer = null,
        ?LibrarySectionRenderer $libraryRenderer = null,
        ?ActivitySectionRenderer $activityRenderer = null
    ) {
        parent::__construct($storage);
        $this->viewBuilder = $viewBuilder ?? new AdminPageViewBuilder($storage);
        $this->previewRenderer = $previewRenderer ?? new PreviewSectionRenderer($storage);
        $this->toolsRenderer = $toolsRenderer ?? new ToolsSectionRenderer($storage);
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
        ?>
        <div class="wrap tasty-fonts-admin<?php echo $trainingWheelsOff ? ' is-training-wheels-off' : ''; ?>">
            <?php $this->renderNotices($toasts); ?>

            <?php if (!$storage): ?>
                <div class="notice notice-error"><p><?php echo esc_html($storageErrorMessage !== '' ? $storageErrorMessage : __('The font storage directory could not be initialized.', 'tasty-fonts')); ?></p></div>
            <?php else: ?>
                <div class="tasty-fonts-shell">
                    <?php $this->studioRenderer->render($view); ?>
                    <?php $this->libraryRenderer->render($view); ?>
                    <?php $this->activityRenderer->render($view); ?>
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
        bool $monospaceRoleEnabled = false
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
            $monospaceRoleEnabled
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

    protected function renderCodePreviewScene(string $previewText, array $roles, bool $monospaceRoleEnabled): void
    {
        $this->syncRendererState($this->previewRenderer);
        $this->previewRenderer->renderCodePreviewScene($previewText, $roles, $monospaceRoleEnabled);
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
