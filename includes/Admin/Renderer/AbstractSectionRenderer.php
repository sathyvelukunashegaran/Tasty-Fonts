<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Support\Storage;

abstract class AbstractSectionRenderer
{
    use SharedRenderHelpers;

    protected bool $trainingWheelsOff = false;

    public function __construct(protected readonly Storage $storage)
    {
    }

    abstract public function render(array $view): void;

    protected function renderTemplate(string $template, array $view): void
    {
        $this->trainingWheelsOff = !empty($view['trainingWheelsOff']);
        extract($view, EXTR_SKIP);

        require __DIR__ . '/templates/' . $template;
    }
}
