<?php

declare(strict_types=1);

namespace TastyFonts\Integrations;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

final class OxygenIntegrationService
{
    private static array $compatibilityFamilies = [];

    public function isAvailable(): bool
    {
        $available = defined('CT_VERSION') || function_exists('ct_get_global_settings');

        if (function_exists('apply_filters')) {
            $available = (bool) apply_filters('tasty_fonts_oxygen_integration_available', $available);
        }

        return $available;
    }

    public function readState(?bool $enabled): array
    {
        $available = $this->isAvailable();
        $effectiveEnabled = $available && $enabled !== false;

        return [
            'available' => $available,
            'enabled' => $effectiveEnabled,
            'configured' => $enabled !== null,
            'status' => !$available ? 'unavailable' : ($effectiveEnabled ? 'active' : 'disabled'),
        ];
    }

    public function registerCompatibilityShim(array $runtimeFamilies): void
    {
        self::$compatibilityFamilies = $this->runtimeFamilyNames($runtimeFamilies);

        if (!$this->isAvailable() || class_exists('\ECF_Plugin', false)) {
            return;
        }

        eval(<<<'PHP'
class ECF_Plugin
{
    public static function get_font_families()
    {
        return \TastyFonts\Integrations\OxygenIntegrationService::compatibilityFamilies();
    }
}
PHP);

        $GLOBALS['ECF_Plugin'] = new \ECF_Plugin();
    }

    public function getEditorStyles(array $runtimeFamilies): array
    {
        if (!function_exists('ct_get_global_settings')) {
            return [];
        }

        $runtimeLookup = $this->runtimeFamilyLookup($runtimeFamilies);

        if ($runtimeLookup === []) {
            return [];
        }

        $settings = ct_get_global_settings();
        $styles = [];
        $bodyFamily = $this->managedFamilyName($settings['fonts']['Text'] ?? '', $runtimeLookup);

        if ($bodyFamily !== '') {
            $styles[] = $this->buildEditorRule('body', $bodyFamily);
        }

        $displayFamily = $this->managedFamilyName($settings['fonts']['Display'] ?? '', $runtimeLookup);

        if ($displayFamily !== '') {
            $styles[] = $this->buildEditorRule('body :is(h1, h2, h3, h4, h5, h6, .editor-post-title)', $displayFamily);
        }

        return array_values(array_unique($styles));
    }

    public static function compatibilityFamilies(): array
    {
        return self::$compatibilityFamilies;
    }

    private function buildEditorRule(string $selector, string $familyName): string
    {
        return $selector . '{font-family:' . FontUtils::buildFontStack($familyName) . ';}';
    }

    private function runtimeFamilyNames(array $runtimeFamilies): array
    {
        $names = array_keys($this->runtimeFamilyLookup($runtimeFamilies));
        natcasesort($names);

        return array_values($names);
    }

    private function runtimeFamilyLookup(array $runtimeFamilies): array
    {
        $lookup = [];

        foreach ($runtimeFamilies as $family) {
            $name = '';

            if (is_string($family)) {
                $name = trim($family);
            } elseif (is_array($family)) {
                $name = trim((string) ($family['family'] ?? ''));
            }

            if ($name === '') {
                continue;
            }

            $lookup[$name] = true;
        }

        return $lookup;
    }

    private function managedFamilyName(mixed $value, array $runtimeLookup): string
    {
        $familyName = is_scalar($value) ? trim((string) $value) : '';

        return $familyName !== '' && isset($runtimeLookup[$familyName]) ? $familyName : '';
    }
}
