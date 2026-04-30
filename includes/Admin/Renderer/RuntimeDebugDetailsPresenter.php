<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

use TastyFonts\Support\FontUtils;

defined('ABSPATH') || exit;

/**
 * Prepares runtime support facts for the Advanced Tools Debug Details table.
 *
 * @phpstan-type RuntimeDebugItem array{label: string, value: string, copyable: bool, kind: string, help: string}
 * @phpstan-type RuntimeDebugGroup array{slug: string, title: string, items: list<RuntimeDebugItem>}
 * @phpstan-type RuntimeDebugFamily array{family: string, provider: string, type: string, format: string, faces: int, variants: list<string>, axes: list<string>, missing_files: list<string>}
 * @phpstan-type RuntimeDebugDetails array{
 *     title: string,
 *     description: string,
 *     generated_facts: list<RuntimeDebugItem>,
 *     runtime_families: list<RuntimeDebugFamily>,
 *     copy_groups: list<RuntimeDebugGroup>,
 *     copyable_count: int
 * }
 */
final class RuntimeDebugDetailsPresenter
{
    /**
     * @param array<mixed> $advancedTools
     * @param list<mixed> $diagnosticItems
     * @param array<mixed> $view
     * @return RuntimeDebugDetails
     */
    public function details(array $advancedTools, array $diagnosticItems, array $view = []): array
    {
        $runtimeManifest = $this->arrayValue($advancedTools, 'runtime_manifest');
        $delivery = $this->arrayValue($runtimeManifest, 'delivery');
        $generatedCss = $this->arrayValue($runtimeManifest, 'generated_css');
        $deliveryMode = $this->stringValue($delivery, 'css_delivery_mode', $this->stringValue($view, 'css_delivery_mode', 'file'));
        $copyItems = $this->dedupeCopyableItems(array_merge(
            $this->copyableItems($diagnosticItems),
            $this->generatedCopyableItems($generatedCss)
        ));
        $copyableCount = count($copyItems);
        $generatedState = $this->generatedStateLabel($generatedCss);
        $cssSize = $this->generatedSizeLabel($generatedCss, $diagnosticItems);

        return [
            'title' => __('Debug Details', 'tasty-fonts'),
            'description' => __('Copy exact runtime CSS paths, URLs, and file facts for support without mixing them into health triage.', 'tasty-fonts'),
            'generated_facts' => $this->generatedFacts($generatedCss, $deliveryMode, $generatedState, $cssSize),
            'runtime_families' => $this->runtimeFamilies($runtimeManifest),
            'copy_groups' => $this->copyGroups($copyItems),
            'copyable_count' => $copyableCount,
        ];
    }

    /**
     * @param array<mixed> $generatedCss
     * @return list<RuntimeDebugItem>
     */
    private function generatedFacts(array $generatedCss, string $deliveryMode, string $generatedState, string $cssSize): array
    {
        $path = $this->stringValue($generatedCss, 'path');
        $url = $this->stringValue($generatedCss, 'url');
        $writePath = $this->stringValue($generatedCss, 'write_path');
        $expectedVersion = $this->stringValue($generatedCss, 'expected_version');
        $facts = [
            $this->item(__('Delivery Mode', 'tasty-fonts'), $this->deliveryModeLabel($deliveryMode), false, 'value', __('Shows how Tasty Fonts serves runtime CSS on the frontend, either as a generated file or inline output.', 'tasty-fonts')),
            $this->item(__('Generated State', 'tasty-fonts'), $generatedState, false, 'value', __('Shows whether the managed CSS file exists and matches the current saved font settings.', 'tasty-fonts')),
            $this->item(__('CSS Size', 'tasty-fonts'), $cssSize, false, 'value', __('Shows the size WordPress reports for the generated runtime stylesheet.', 'tasty-fonts')),
            $this->item(__('CSS Filesystem Path', 'tasty-fonts'), $path !== '' ? $path : __('Not available', 'tasty-fonts'), false, 'path', __('Server path where WordPress expects the generated CSS file.', 'tasty-fonts')),
            $this->item(__('CSS Request URL', 'tasty-fonts'), $url !== '' ? $url : __('Not available', 'tasty-fonts'), false, 'url', __('Public URL browsers request when generated-file delivery is active.', 'tasty-fonts')),
        ];

        if ($writePath !== '' && $writePath !== $path) {
            $facts[] = $this->item(__('CSS Write Path', 'tasty-fonts'), $writePath, false, 'path', __('Actual server path Tasty Fonts writes to; this can differ from the public path on some hosts.', 'tasty-fonts'));
        }

        if ($expectedVersion !== '') {
            $facts[] = $this->item(__('Expected Version', 'tasty-fonts'), $expectedVersion, false, 'value', __('Cache-busting version Tasty Fonts expects for the current generated CSS.', 'tasty-fonts'));
        }

        return $facts;
    }

    /**
     * @param array<mixed> $generatedCss
     * @return list<RuntimeDebugItem>
     */
    private function generatedCopyableItems(array $generatedCss): array
    {
        $items = [];
        $path = $this->stringValue($generatedCss, 'path');
        $url = $this->stringValue($generatedCss, 'url');
        $writePath = $this->stringValue($generatedCss, 'write_path');

        if ($url !== '') {
            $items[] = $this->item(__('CSS Request URL', 'tasty-fonts'), $url, true, 'url', __('Copy this public generated CSS URL when checking the browser request or sharing runtime details with support.', 'tasty-fonts'));
        }

        if ($path !== '') {
            $items[] = $this->item(__('CSS Filesystem Path', 'tasty-fonts'), $path, true, 'path', __('Copy this server path when verifying that the generated CSS file exists on disk.', 'tasty-fonts'));
        }

        if ($writePath !== '' && $writePath !== $path) {
            $items[] = $this->item(__('CSS Write Path', 'tasty-fonts'), $writePath, true, 'path', __('Copy this write target when debugging storage permissions or host-specific upload paths.', 'tasty-fonts'));
        }

        return $items;
    }

    /**
     * @param list<mixed> $diagnosticItems
     * @return list<RuntimeDebugItem>
     */
    private function copyableItems(array $diagnosticItems): array
    {
        $items = [];

        foreach ($diagnosticItems as $item) {
            if (!is_array($item) || empty($item['copyable'])) {
                continue;
            }

            $label = trim($this->stringValue($item, 'label', __('Value', 'tasty-fonts')));
            $value = trim($this->stringValue($item, 'value'));

            if ($label === '' || $value === '' || $value === __('Not available', 'tasty-fonts')) {
                continue;
            }

            $kind = $this->kindForLabelAndValue($label, $value);
            $items[] = $this->item($label, $value, true, $kind, $this->copyableItemHelp($label, $kind));
        }

        return $items;
    }

    /**
     * @param list<RuntimeDebugItem> $items
     * @return list<RuntimeDebugItem>
     */
    private function dedupeCopyableItems(array $items): array
    {
        $deduped = [];
        $seen = [];

        foreach ($items as $item) {
            $key = $item['kind'] . ':' . strtolower($item['value']);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $item;
        }

        return $deduped;
    }

    /**
     * @param list<RuntimeDebugItem> $copyItems
     * @return list<RuntimeDebugGroup>
     */
    private function copyGroups(array $copyItems): array
    {
        $urls = [];
        $paths = [];
        $values = [];

        foreach ($copyItems as $item) {
            if ($item['kind'] === 'url') {
                $urls[] = $item;
                continue;
            }

            if ($item['kind'] === 'path') {
                $paths[] = $item;
                continue;
            }

            $values[] = $item;
        }

        $groups = [];

        if ($urls !== []) {
            $groups[] = ['slug' => 'urls', 'title' => __('Public and Request URLs', 'tasty-fonts'), 'items' => $urls];
        }

        if ($paths !== []) {
            $groups[] = ['slug' => 'paths', 'title' => __('Filesystem Paths', 'tasty-fonts'), 'items' => $paths];
        }

        if ($values !== []) {
            $groups[] = ['slug' => 'values', 'title' => __('Other Support Values', 'tasty-fonts'), 'items' => $values];
        }

        return $groups;
    }

    /**
     * @param array<mixed> $runtimeManifest
     * @return list<RuntimeDebugFamily>
     */
    private function runtimeFamilies(array $runtimeManifest): array
    {
        $families = [];
        $rawFamilies = is_array($runtimeManifest['families'] ?? null) ? $runtimeManifest['families'] : [];

        foreach ($rawFamilies as $family) {
            if (!is_array($family)) {
                continue;
            }

            $familyName = trim($this->stringValue($family, 'family'));

            if ($familyName === '') {
                continue;
            }

            $families[] = [
                'family' => $familyName,
                'provider' => $this->stringValue($family, 'provider', __('Local', 'tasty-fonts')),
                'type' => $this->stringValue($family, 'type', __('Default', 'tasty-fonts')),
                'format' => $this->stringValue($family, 'format'),
                'faces' => $this->intValue($family, 'faces'),
                'variants' => $this->stringListValue($family['variants'] ?? []),
                'axes' => $this->stringListValue($family['axes'] ?? []),
                'missing_files' => $this->stringListValue($family['missing_files'] ?? []),
            ];
        }

        return $families;
    }

    /**
     * @param array<mixed> $generatedCss
     */
    private function generatedStateLabel(array $generatedCss): string
    {
        if (empty($generatedCss['exists'])) {
            return __('Not Generated', 'tasty-fonts');
        }

        if (!empty($generatedCss['is_current'])) {
            return __('Generated and Current', 'tasty-fonts');
        }
        return __('Generated, Needs Refresh', 'tasty-fonts');
    }

    /**
     * @param array<mixed> $generatedCss
     * @param list<mixed> $diagnosticItems
     */
    private function generatedSizeLabel(array $generatedCss, array $diagnosticItems): string
    {
        if (!empty($generatedCss['exists'])) {
            $size = $this->intValue($generatedCss, 'size');

            return $size > 0 ? size_format($size) : __('Generated, Size Unknown', 'tasty-fonts');
        }

        foreach ($diagnosticItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = strtolower($this->stringValue($item, 'label'));

            if ($label === strtolower(__('Stylesheet Size', 'tasty-fonts'))) {
                $value = trim($this->stringValue($item, 'value'));

                return $value !== '' ? $value : __('Not Generated', 'tasty-fonts');
            }
        }

        return __('Not Generated', 'tasty-fonts');
    }

    private function deliveryModeLabel(string $mode): string
    {
        return match (sanitize_key($mode)) {
            'inline' => __('Inline in Page Head', 'tasty-fonts'),
            'file' => __('Generated File', 'tasty-fonts'),
            default => $mode !== '' ? ucwords(str_replace(['-', '_'], ' ', $mode)) : __('Generated File', 'tasty-fonts'),
        };
    }

    private function kindForLabelAndValue(string $label, string $value): string
    {
        if (FontUtils::isRemoteUrl($value) || str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return 'url';
        }

        $label = strtolower($label);

        if (str_contains($label, 'path') || str_contains($label, 'file') || str_contains($label, 'folder') || str_contains($label, 'directory') || $this->looksLikeFilesystemPath($value)) {
            return 'path';
        }

        return 'value';
    }

    private function looksLikeFilesystemPath(string $value): bool
    {
        return str_starts_with($value, '/')
            || preg_match('/^[A-Za-z]:\\\\/', $value) === 1
            || str_contains($value, 'wp-content/');
    }

    private function copyableItemHelp(string $label, string $kind): string
    {
        return match ($kind) {
            'url' => __('Copy this URL exactly when checking browser requests, public asset access, or support diagnostics.', 'tasty-fonts'),
            'path' => __('Copy this server path exactly when checking generated files, storage, or filesystem permissions.', 'tasty-fonts'),
            default => sprintf(
                /* translators: %s: debug value label */
                __('Copy the %s value exactly when sharing support diagnostics.', 'tasty-fonts'),
                $label
            ),
        };
    }

    /**
     * @return RuntimeDebugItem
     */
    private function item(string $label, string $value, bool $copyable, string $kind, string $help): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'copyable' => $copyable,
            'kind' => $kind,
            'help' => $help,
        ];
    }

    /**
     * @param array<mixed> $values
     * @return array<mixed>
     */
    private function arrayValue(array $values, string $key): array
    {
        return is_array($values[$key] ?? null) ? $values[$key] : [];
    }

    /**
     * @param array<mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param array<mixed> $values
     */
    private function intValue(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private function stringListValue(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
            $values
        )));
    }
}
