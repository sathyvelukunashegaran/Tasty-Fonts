<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Admin\AdminPageViewVariables;
use TastyFonts\Support\Storage;

abstract class AbstractSectionRenderer
{
    use SharedRenderHelpers;

    protected bool $trainingWheelsOff = false;

    public function __construct(protected readonly Storage $storage)
    {
    }

    /**
     * @param array<string, mixed> $view
     */
    abstract public function render(array $view): void;

    public function setTrainingWheelsOff(bool $trainingWheelsOff): void
    {
        $this->trainingWheelsOff = $trainingWheelsOff;
    }

    /**
     * @param AdminPageViewVariables|array<string, mixed> $view
     */
    protected function renderTemplate(string $template, AdminPageViewVariables|array $view): void
    {
        if ($view instanceof AdminPageViewVariables) {
            $view = $view->toArray();
        }

        $this->trainingWheelsOff = !empty($view['trainingWheelsOff']);
        extract($view, EXTR_SKIP);

        require __DIR__ . '/templates/' . $template;
    }
}
