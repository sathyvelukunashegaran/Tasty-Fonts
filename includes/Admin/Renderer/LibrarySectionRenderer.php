<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

final class LibrarySectionRenderer extends AbstractSectionRenderer
{
    private readonly FamilyCardRenderer $familyCardRenderer;

    public function __construct(
        \TastyFonts\Support\Storage $storage,
        ?FamilyCardRenderer $familyCardRenderer = null
    ) {
        parent::__construct($storage);
        $this->familyCardRenderer = $familyCardRenderer ?? new FamilyCardRenderer($storage);
    }

    public function render(array $view): void
    {
        $this->familyCardRenderer->setTrainingWheelsOff(!empty($view['trainingWheelsOff']));
        $view['familyCardRenderer'] = $this->familyCardRenderer;
        $this->renderTemplate('library-section.php', $view);
    }
}
