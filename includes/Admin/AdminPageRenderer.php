<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Admin\Renderer\AbstractSectionRenderer;
use TastyFonts\Admin\Renderer\ActivitySectionRenderer;
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
    private readonly LibrarySectionRenderer $libraryRenderer;
    private readonly ActivitySectionRenderer $activityRenderer;

    public function __construct(
        Storage $storage,
        ?AdminPageViewBuilder $viewBuilder = null,
        ?StudioSectionRenderer $studioRenderer = null,
        ?PreviewSectionRenderer $previewRenderer = null,
        ?ToolsSectionRenderer $toolsRenderer = null,
        ?LibrarySectionRenderer $libraryRenderer = null,
        ?ActivitySectionRenderer $activityRenderer = null
    ) {
        parent::__construct($storage);
        $this->viewBuilder = $viewBuilder ?? new AdminPageViewBuilder($storage);
        $this->previewRenderer = $previewRenderer ?? new PreviewSectionRenderer($storage);
        $this->toolsRenderer = $toolsRenderer ?? new ToolsSectionRenderer($storage);
        $this->studioRenderer = $studioRenderer ?? new StudioSectionRenderer($storage, $this->previewRenderer, $this->toolsRenderer);
        $this->libraryRenderer = $libraryRenderer ?? new LibrarySectionRenderer($storage);
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
}
