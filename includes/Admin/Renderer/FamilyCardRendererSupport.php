<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;
use TastyFonts\Support\RoleUsageMessageFormatter;

/**
 * @phpstan-import-type CatalogFace from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type DeliveryProfile from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type RoleSet from \TastyFonts\Repository\SettingsRepository
 * @phpstan-type CategoryAliasOwners array<string, string>
 * @phpstan-type RendererFlagOptions array<string, mixed>
 * @phpstan-type SnippetMap array<string, string>
 */
trait FamilyCardRendererSupport
{
    use LibraryRenderValueHelpers;

    /**
     * @param list<string> $roleLabels
     */
    protected function buildDeleteBlockedMessage(string $familyName, array $roleLabels): string
    {
        return RoleUsageMessageFormatter::buildDeleteBlockedMessage($familyName, $roleLabels);
    }

    /**
     * @param list<string> $roleLabels
     */
    protected function buildDeleteLastVariantBlockedMessage(string $familyName, array $roleLabels): string
    {
        return RoleUsageMessageFormatter::buildDeleteLastVariantBlockedMessage($familyName, $roleLabels);
    }

    /**
     * @param list<string> $roleLabels
     * @return list<string>
     */
    protected function translateRoleLabels(array $roleLabels): array
    {
        return RoleUsageMessageFormatter::translateRoleLabels($roleLabels);
    }

    /**
     * @param list<string> $labels
     */
    protected function formatRoleLabelList(array $labels): string
    {
        return RoleUsageMessageFormatter::formatRoleLabelList($labels);
    }

    /**
     * @param list<string> $roleKeys
     */
    protected function buildRoleSelectionKey(array $roleKeys): string
    {
        $orderedKeys = [];

        foreach (['heading', 'body', 'monospace'] as $roleKey) {
            if (in_array($roleKey, $roleKeys, true)) {
                $orderedKeys[] = $roleKey;
            }
        }

        return implode('-', $orderedKeys);
    }

    /**
     * @param RendererFlagOptions $extendedVariableOptions
     * @return SnippetMap
     */
    protected function buildFaceCssCopySnippets(string $familyName, string $weight, string $style, array $extendedVariableOptions = []): array
    {
        $familyReference = $this->buildFontVariableReference($familyName);
        $weightReference = $this->buildWeightReference($weight, $extendedVariableOptions);
        $styleValue = FontUtils::normalizeStyle($style);
        $snippets = [];

        if ($familyReference !== '') {
            $snippets['family'] = 'font-family: ' . $familyReference . ';';
        }

        if ($weightReference !== '') {
            $snippets['weight'] = 'font-weight: ' . $weightReference . ';';
        }

        if ($styleValue !== '') {
            $snippets['style'] = 'font-style: ' . $styleValue . ';';
        }

        if ($snippets !== []) {
            $snippets['snippet'] = implode(' ', $snippets);
        }

        return $snippets;
    }

    /**
     * @param list<string> $assignedRoleKeys
     * @param RoleSet $roles
     * @param CategoryAliasOwners $categoryAliasOwners
     * @param RendererFlagOptions $extendedVariableOptions
     * @return SnippetMap
     */
    protected function buildFamilyCssVariableSnippets(
        string $familyName,
        string $defaultStack,
        array $assignedRoleKeys,
        array $roles,
        string $fontCategory,
        array $categoryAliasOwners = [],
        array $extendedVariableOptions = []
    ): array {
        $snippets = [];
        $familyVariable = FontUtils::fontVariableName($familyName);
        $familyReference = $this->buildFontVariableReference($familyName);

        if ($familyVariable !== '' && $defaultStack !== '') {
            $snippets['Family Variable'] = $familyVariable . ': ' . $defaultStack . ';';
        }

        if (in_array('heading', $assignedRoleKeys, true)) {
            $snippets['Heading Variable'] = '--font-heading: ' . $defaultStack . ';';
        }

        if (in_array('body', $assignedRoleKeys, true)) {
            $snippets['Body Variable'] = '--font-body: ' . $defaultStack . ';';

            if ($this->extendedVariableRoleAliasesEnabled($extendedVariableOptions)) {
                $snippets['Interface Alias'] = '--font-interface: var(--font-body);';
                $snippets['UI Alias'] = '--font-ui: var(--font-body);';
            }
        }

        if (in_array('monospace', $assignedRoleKeys, true)) {
            $snippets['Monospace Variable'] = '--font-monospace: ' . $defaultStack . ';';

            if ($this->extendedVariableRoleAliasesEnabled($extendedVariableOptions)) {
                $snippets['Code Alias'] = '--font-code: var(--font-monospace);';
            }
        }

        $categoryAliasProperty = $this->resolveCategoryAliasProperty($fontCategory);

        if (
            $this->extendedVariableCategoryAliasEnabled($extendedVariableOptions, $categoryAliasProperty)
            && $categoryAliasProperty !== ''
            && $familyReference !== ''
            && (($categoryAliasOwners[$categoryAliasProperty] ?? '') === $familyName)
        ) {
            $snippets['Category Alias'] = $categoryAliasProperty . ': ' . $familyReference . ';';
        }

        return $snippets;
    }

    /**
     * @param list<string> $assignedRoleKeys
     * @param CategoryAliasOwners $categoryAliasOwners
     * @param RendererFlagOptions $classOutputOptions
     * @return SnippetMap
     */
    protected function buildFamilyCssClassSnippets(
        string $familyName,
        array $assignedRoleKeys,
        string $fontCategory,
        array $categoryAliasOwners = [],
        array $classOutputOptions = []
    ): array {
        if (!$this->classOutputEnabled($classOutputOptions)) {
            return [];
        }

        $snippets = [];
        $familyClassSelector = $this->buildFamilyClassSelector($familyName);

        if ($familyClassSelector !== '' && $this->classOutputFamiliesEnabled($classOutputOptions)) {
            $snippets['Family Class'] = $familyClassSelector;
        }

        if (in_array('heading', $assignedRoleKeys, true) && $this->classOutputRoleEnabled($classOutputOptions, 'heading')) {
            $snippets['Heading Class'] = '.font-heading';
        }

        if (in_array('body', $assignedRoleKeys, true)) {
            if ($this->classOutputRoleEnabled($classOutputOptions, 'body')) {
                $snippets['Body Class'] = '.font-body';
            }

            if ($this->classOutputRoleAliasEnabled($classOutputOptions, 'interface')) {
                $snippets['Interface Alias'] = '.font-interface';
            }

            if ($this->classOutputRoleAliasEnabled($classOutputOptions, 'ui')) {
                $snippets['UI Alias'] = '.font-ui';
            }
        }

        if (in_array('monospace', $assignedRoleKeys, true)) {
            if ($this->classOutputRoleEnabled($classOutputOptions, 'monospace')) {
                $snippets['Monospace Class'] = '.font-monospace';
            }

            if ($this->classOutputRoleAliasEnabled($classOutputOptions, 'code')) {
                $snippets['Code Alias'] = '.font-code';
            }
        }

        $categoryAliasProperty = $this->resolveCategoryAliasProperty($fontCategory);
        $categoryAliasSelector = $this->resolveCategoryAliasSelector($fontCategory);

        if (
            $categoryAliasProperty !== ''
            && $categoryAliasSelector !== ''
            && (($categoryAliasOwners[$categoryAliasProperty] ?? '') === $familyName)
            && $this->classOutputCategoryAliasEnabled($classOutputOptions, $categoryAliasProperty)
        ) {
            $snippets['Category Class'] = $categoryAliasSelector;
        }

        return $snippets;
    }

    /**
     * @param RendererFlagOptions $extendedVariableOptions
     */
    protected function buildWeightReference(string $weight, array $extendedVariableOptions = []): string
    {
        if ($this->extendedVariableWeightTokensEnabled($extendedVariableOptions)) {
            $reference = FontUtils::weightVariableReference($weight);

            if ($reference !== '') {
                return $reference;
            }
        }

        $normalized = FontUtils::normalizeWeight($weight);

        return preg_match('/^\d{1,4}$/', $normalized) === 1 || in_array($normalized, ['normal', 'bold', 'bolder', 'lighter'], true)
            ? $normalized
            : '';
    }

    /**
     * @param RendererFlagOptions $options
     */
    protected function extendedVariableOutputEnabled(array $options): bool
    {
        return !array_key_exists('enabled', $options) || !empty($options['enabled']);
    }

    /**
     * @param RendererFlagOptions $options
     */
    protected function extendedVariableWeightTokensEnabled(array $options): bool
    {
        return $this->extendedVariableOutputEnabled($options)
            && (!array_key_exists('weight_tokens', $options) || !empty($options['weight_tokens']));
    }

    /**
     * @param RendererFlagOptions $options
     */
    protected function extendedVariableRoleAliasesEnabled(array $options): bool
    {
        return $this->extendedVariableOutputEnabled($options)
            && (!array_key_exists('role_aliases', $options) || !empty($options['role_aliases']));
    }

    /**
     * @param RendererFlagOptions $options
     */
    protected function extendedVariableCategoryAliasEnabled(array $options, string $categoryAliasProperty): bool
    {
        if (!$this->extendedVariableOutputEnabled($options)) {
            return false;
        }

        $field = match ($categoryAliasProperty) {
            '--font-sans' => 'category_sans',
            '--font-serif' => 'category_serif',
            '--font-mono' => 'category_mono',
            default => '',
        };

        return $field !== ''
            && (!array_key_exists($field, $options) || !empty($options[$field]));
    }

    protected function buildFamilyClassSelector(string $familyName): string
    {
        $slug = FontUtils::slugify($familyName);

        return $slug !== '' ? '.font-' . $slug : '';
    }

    protected function resolveCategoryAliasSelector(string $category): string
    {
        return match (strtolower(trim($category))) {
            'sans-serif', 'sans serif' => '.font-sans',
            'serif', 'slab-serif', 'slab serif' => '.font-serif',
            'monospace' => '.font-mono',
            default => '',
        };
    }

    /**
     * @param RendererFlagOptions $options
     */
    protected function classOutputEnabled(array $options): bool
    {
        return !empty($options['enabled']);
    }

    /**
     * @param RendererFlagOptions $options
     */
    protected function classOutputFamiliesEnabled(array $options): bool
    {
        return $this->classOutputEnabled($options)
            && (!array_key_exists('families', $options) || !empty($options['families']));
    }

    /**
     * @param RendererFlagOptions $options
     */
    protected function classOutputRoleEnabled(array $options, string $roleKey): bool
    {
        if (!$this->classOutputEnabled($options)) {
            return false;
        }

        $field = match ($roleKey) {
            'heading' => 'role_heading',
            'body' => 'role_body',
            'monospace' => 'role_monospace',
            default => '',
        };

        return $field !== ''
            && (!array_key_exists($field, $options) || !empty($options[$field]));
    }

    /**
     * @param RendererFlagOptions $options
     */
    protected function classOutputRoleAliasEnabled(array $options, string $aliasKey): bool
    {
        if (!$this->classOutputEnabled($options)) {
            return false;
        }

        $field = match ($aliasKey) {
            'interface' => 'role_alias_interface',
            'ui' => 'role_alias_ui',
            'code' => 'role_alias_code',
            default => '',
        };

        return $field !== ''
            && (!array_key_exists($field, $options) || !empty($options[$field]));
    }

    /**
     * @param RendererFlagOptions $options
     */
    protected function classOutputCategoryAliasEnabled(array $options, string $categoryAliasProperty): bool
    {
        if (!$this->classOutputEnabled($options)) {
            return false;
        }

        $field = match ($categoryAliasProperty) {
            '--font-sans' => 'category_sans',
            '--font-serif' => 'category_serif',
            '--font-mono' => 'category_mono',
            default => '',
        };

        return $field !== ''
            && (!array_key_exists($field, $options) || !empty($options[$field]));
    }

    /**
     * @param list<string> $sources
     * @return list<string>
     */
    protected function buildFamilySourceTokens(array $sources, bool $isRoleFamily = false): array
    {
        $tokens = [];

        foreach ($sources as $source) {
            $normalized = strtolower(trim((string) $source));

            if ($normalized === '') {
                continue;
            }

            $tokens[] = $normalized;

            if ($normalized === 'local') {
                $tokens[] = 'uploaded';
            }
        }

        if ($isRoleFamily) {
            $tokens[] = 'used';
        }

        return array_values(array_unique($tokens));
    }

    protected function buildFamilySourceLabel(string $source): string
    {
        return match (strtolower(trim($source))) {
            'local' => __('Local', 'tasty-fonts'),
            'google' => __('Google', 'tasty-fonts'),
            'bunny' => __('Bunny', 'tasty-fonts'),
            'adobe' => __('Adobe', 'tasty-fonts'),
            default => ucfirst(trim($source)),
        };
    }

    protected function translateProfileLabel(string $label): string
    {
        $normalized = trim($label);

        if ($normalized === '') {
            return '';
        }

        return match ($normalized) {
            'Self-hosted' => __('Self-hosted', 'tasty-fonts'),
            'Self-hosted (Google import)' => __('Self-hosted (Google import)', 'tasty-fonts'),
            'Google CDN' => __('Google CDN', 'tasty-fonts'),
            'Self-hosted (Bunny import)' => __('Self-hosted (Bunny import)', 'tasty-fonts'),
            'Bunny CDN' => __('Bunny CDN', 'tasty-fonts'),
            'Adobe-hosted' => __('Adobe-hosted', 'tasty-fonts'),
            default => $normalized,
        };
    }

    /**
     * @param DeliveryProfile $profile
     */
    protected function buildProfileRequestSummary(array $profile): string
    {
        $provider = strtolower(trim($this->mapStringValue($profile, 'provider')));
        $type = strtolower(trim($this->mapStringValue($profile, 'type')));

        return match ($provider . ':' . $type) {
            'google:cdn' => __('External request via Google Fonts', 'tasty-fonts'),
            'bunny:cdn' => __('External request via Bunny Fonts', 'tasty-fonts'),
            'adobe:adobe_hosted' => __('Adobe-hosted project stylesheet', 'tasty-fonts'),
            default => __('Self-hosted files', 'tasty-fonts'),
        };
    }

    /**
     * @param DeliveryProfile $profile
     */
    protected function isMigratableCdnProfile(array $profile): bool
    {
        $provider = strtolower(trim($this->mapStringValue($profile, 'provider')));
        $type = strtolower(trim($this->mapStringValue($profile, 'type')));

        return $type === 'cdn' && in_array($provider, ['google', 'bunny'], true);
    }

    /**
     * @param list<CatalogFace> $faces
     * @return list<string>
     */
    protected function buildFamilyFaceSummaryLabels(array $faces): array
    {
        $items = [];

        foreach ($faces as $face) {
            $normalizedWeight = FontUtils::normalizeWeight($this->mapStringValue($face, 'weight', '400'));
            $weight = preg_match('/^\d{1,4}\.\.\d{1,4}$/', $normalizedWeight) === 1
                ? $normalizedWeight
                : (preg_replace('/[^0-9]/', '', $normalizedWeight) ?: '400');
            $style = FontUtils::normalizeStyle($this->mapStringValue($face, 'style', 'normal'));
            $key = FontUtils::faceAxisKey($weight, $style);

            if (isset($items[$key])) {
                continue;
            }

            $items[$key] = [
                'weight_sort' => FontUtils::weightSortValue($normalizedWeight),
                'style' => $style,
                'label' => sprintf(
                    '%1$s%2$s',
                    $weight,
                    $style === 'italic' ? ' italic' : ''
                ),
            ];
        }

        usort(
            $items,
            static function (array $left, array $right): int {
                $weightComparison = $left['weight_sort'] <=> $right['weight_sort'];

                if ($weightComparison !== 0) {
                    return $weightComparison;
                }

                if ($left['style'] === $right['style']) {
                    return 0;
                }

                return $left['style'] === 'normal' ? -1 : 1;
            }
        );

        return array_map(static fn (array $item): string => $item['label'], $items);
    }

    /**
     * @param list<CatalogFace> $faces
     * @return list<string>
     */
    protected function buildVariationAxisSummaryLabels(array $faces): array
    {
        $labels = [];

        foreach ($faces as $face) {
            foreach (FontUtils::normalizeAxesMap($face['axes'] ?? []) as $tag => $definition) {
                $min = $this->mapStringValue($definition, 'min');
                $max = $this->mapStringValue($definition, 'max');
                $default = $this->mapStringValue($definition, 'default');
                $range = $min !== '' && $max !== '' && $min !== $max
                    ? $min . '..' . $max
                    : ($default !== '' ? $default : ($min !== '' ? $min : $max));

                if ($range === '') {
                    continue;
                }

                $labels[$tag] = strtolower($tag) . ' ' . $range;
            }
        }

        ksort($labels, SORT_STRING);

        return array_values($labels);
    }

    protected function buildFaceTitle(string $weight, string $style): string
    {
        $normalizedWeight = FontUtils::normalizeWeight($weight);
        $normalizedStyle = FontUtils::normalizeStyle($style);
        $weightLabel = $this->buildWeightLabel($normalizedWeight);

        return trim(
            implode(
                ' ',
                array_filter(
                    [
                        $normalizedWeight,
                        $weightLabel,
                        $normalizedStyle !== 'normal' ? ucfirst($normalizedStyle) : null,
                    ],
                    static fn (?string $value): bool => is_string($value) && $value !== ''
                )
            )
        );
    }

    /**
     * @param DeliveryProfile $activeDelivery
     */
    protected function canDeleteFaceVariant(array $activeDelivery): bool
    {
        $provider = strtolower(trim($this->mapStringValue($activeDelivery, 'provider')));
        $type = strtolower(trim($this->mapStringValue($activeDelivery, 'type')));

        return $activeDelivery !== [] && $provider !== 'adobe' && $type !== 'adobe_hosted';
    }

    protected function buildWeightLabel(string $weight): string
    {
        return match ($weight) {
            '100' => __('Thin', 'tasty-fonts'),
            '200' => __('Extra Light', 'tasty-fonts'),
            '300' => __('Light', 'tasty-fonts'),
            '400', 'normal' => __('Regular', 'tasty-fonts'),
            '500' => __('Medium', 'tasty-fonts'),
            '600' => __('Semi Bold', 'tasty-fonts'),
            '700', 'bold' => __('Bold', 'tasty-fonts'),
            '800' => __('Extra Bold', 'tasty-fonts'),
            '900' => __('Black', 'tasty-fonts'),
            '950' => __('Extra Black', 'tasty-fonts'),
            '1000' => __('Ultra Black', 'tasty-fonts'),
            'bolder' => __('Bolder', 'tasty-fonts'),
            'lighter' => __('Lighter', 'tasty-fonts'),
            default => preg_match('/^\d{1,4}\.\.\d{1,4}$/', $weight) === 1
                ? __('Variable Range', 'tasty-fonts')
                : '',
        };
    }

    protected function buildFacePreviewText(
        string $previewText,
        string $familyName = '',
        bool $isMonospace = false,
        bool $multiline = false
    ): string {
        if ($isMonospace) {
            return $this->buildMonospacePreviewText($familyName, $multiline);
        }

        $normalized = preg_replace('/\s+/', ' ', trim($previewText));
        $normalized = is_string($normalized) ? $normalized : '';

        if ($normalized === '') {
            return __('The quick brown fox…', 'tasty-fonts');
        }

        return wp_trim_words($normalized, 9, '…');
    }

    protected function buildMonospacePreviewText(string $familyName, bool $multiline = false): string
    {
        $familyName = trim($familyName) !== '' ? trim($familyName) : 'Monospace';
        $literal = str_replace(['\\', '"'], ['\\\\', '\\"'], $familyName);

        return sprintf('const font = "%s";', $literal);
    }

    /**
     * @param CatalogFace $face
     */
    protected function buildFaceStorageSummary(array $face): string
    {
        $relativePaths = array_filter(
            array_map(
                static fn (mixed $path): string => is_string($path) ? trim($path) : '',
                (array) ($face['paths'] ?? [])
            ),
            static fn (string $path): bool => $path !== ''
        );

        $fileCount = count($relativePaths);

        if ($fileCount === 0) {
            return '—';
        }

        $bytes = 0;

        foreach ($relativePaths as $relativePath) {
            $absolutePath = $this->storage->pathForRelativePath($relativePath);

            if (!is_string($absolutePath) || !is_file($absolutePath)) {
                continue;
            }

            $size = filesize($absolutePath);

            if ($size !== false) {
                $bytes += (int) $size;
            }
        }

        if ($bytes <= 0) {
            return sprintf(
                _n('%d file', '%d files', $fileCount, 'tasty-fonts'),
                $fileCount
            );
        }

        return sprintf(
            _n('%1$d file · %2$s', '%1$d files · %2$s', $fileCount, 'tasty-fonts'),
            $fileCount,
            size_format($bytes)
        );
    }
}
