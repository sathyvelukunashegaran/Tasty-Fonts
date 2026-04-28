<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

final class DiagnosticsSectionRenderer extends AbstractSectionRenderer
{
    private readonly HealthTriagePresenter $healthTriagePresenter;

    private readonly RuntimeDebugDetailsPresenter $runtimeDebugDetailsPresenter;

    public function __construct(
        \TastyFonts\Support\Storage $storage,
        private readonly ToolsSectionRenderer $toolsRenderer,
        ?HealthTriagePresenter $healthTriagePresenter = null,
        ?RuntimeDebugDetailsPresenter $runtimeDebugDetailsPresenter = null
    ) {
        parent::__construct($storage);
        $this->healthTriagePresenter = $healthTriagePresenter ?? new HealthTriagePresenter();
        $this->runtimeDebugDetailsPresenter = $runtimeDebugDetailsPresenter ?? new RuntimeDebugDetailsPresenter();
    }

    public function render(array $view): void
    {
        $advancedTools = is_array($view['advancedTools'] ?? null) ? $view['advancedTools'] : [];
        $rawHealthChecks = is_array($advancedTools['health_checks'] ?? null) ? $advancedTools['health_checks'] : [];
        $healthChecks = [];

        foreach ($rawHealthChecks as $healthCheck) {
            if (is_array($healthCheck)) {
                $healthChecks[] = $healthCheck;
            }
        }

        $diagnosticItems = [];
        $rawDiagnosticItems = is_array($view['diagnosticItems'] ?? null)
            ? $view['diagnosticItems']
            : (is_array($advancedTools['diagnostic_items'] ?? null) ? $advancedTools['diagnostic_items'] : []);

        foreach ($rawDiagnosticItems as $diagnosticItem) {
            if (is_array($diagnosticItem)) {
                $diagnosticItems[] = $diagnosticItem;
            }
        }

        $view['toolsRenderer'] = $this->toolsRenderer;
        $view['healthTriageGroups'] = $this->healthTriagePresenter->groups($healthChecks);
        $view['debugDetails'] = $this->runtimeDebugDetailsPresenter->details($advancedTools, $diagnosticItems, $view);
        $this->renderTemplate('diagnostics-section.php', $view);
    }
}
