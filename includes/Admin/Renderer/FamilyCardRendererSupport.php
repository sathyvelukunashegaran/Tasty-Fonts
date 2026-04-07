<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

trait FamilyCardRendererSupport
{
    use LibraryRenderValueHelpers;

    protected function buildDeleteBlockedMessage(string $familyName, array $roleLabels): string
    {
        $translatedLabels = $this->translateRoleLabels($roleLabels);

        if ($translatedLabels === []) {
            return '';
        }

        if (count($translatedLabels) === 1) {
            return sprintf(
                __('%1$s is currently assigned as the %2$s font. Choose a different %2$s font before deleting it.', 'tasty-fonts'),
                $familyName,
                $translatedLabels[0]
            );
        }

        return sprintf(
            __('%1$s is currently assigned to %2$s. Choose different role fonts before deleting it.', 'tasty-fonts'),
            $familyName,
            $this->formatRoleLabelList($translatedLabels)
        );
    }

    protected function buildDeleteLastVariantBlockedMessage(string $familyName, array $roleLabels): string
    {
        $translatedLabels = $this->translateRoleLabels($roleLabels);

        if ($translatedLabels === []) {
            return '';
        }

        if (count($translatedLabels) === 1) {
            return sprintf(
                __('%1$s is currently assigned to %2$s, and this is the last saved variant. Choose a different %2$s font before deleting it.', 'tasty-fonts'),
                $familyName,
                $translatedLabels[0]
            );
        }

        return sprintf(
            __('%1$s is currently assigned to %2$s, and this is the last saved variant. Choose different role fonts before deleting it.', 'tasty-fonts'),
            $familyName,
            $this->formatRoleLabelList($translatedLabels)
        );
    }

    protected function translateRoleLabels(array $roleLabels): array
    {
        return array_map(
            static fn (string $label): string => match ($label) {
                'heading' => __('heading', 'tasty-fonts'),
                'body' => __('body', 'tasty-fonts'),
                'monospace' => __('monospace', 'tasty-fonts'),
                default => $label,
            },
            $roleLabels
        );
    }

    protected function formatRoleLabelList(array $labels): string
    {
        $labels = array_values(array_filter($labels, 'strlen'));

        if ($labels === []) {
            return '';
        }

        if (count($labels) === 1) {
            return $labels[0];
        }

        $lastLabel = array_pop($labels);

        return implode(', ', $labels) . __(' and ', 'tasty-fonts') . $lastLabel;
    }

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
            $snippets['Heading Variable'] = '--font-heading: ' . FontUtils::buildFontStack(
                $familyName,
                (string) ($roles['heading_fallback'] ?? 'sans-serif')
            ) . ';';
        }

        if (in_array('body', $assignedRoleKeys, true)) {
            $snippets['Body Variable'] = '--font-body: ' . FontUtils::buildFontStack(
                $familyName,
                (string) ($roles['body_fallback'] ?? 'sans-serif')
            ) . ';';

            if ($this->extendedVariableRoleAliasesEnabled($extendedVariableOptions)) {
                $snippets['Interface Alias'] = '--font-interface: var(--font-body);';
                $snippets['UI Alias'] = '--font-ui: var(--font-body);';
            }
        }

        if (in_array('monospace', $assignedRoleKeys, true)) {
            $snippets['Monospace Variable'] = '--font-monospace: ' . FontUtils::buildFontStack(
                $familyName,
                (string) ($roles['monospace_fallback'] ?? 'monospace')
            ) . ';';

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

    protected function extendedVariableOutputEnabled(array $options): bool
    {
        return !array_key_exists('enabled', $options) || !empty($options['enabled']);
    }

    protected function extendedVariableWeightTokensEnabled(array $options): bool
    {
        return $this->extendedVariableOutputEnabled($options)
            && (!array_key_exists('weight_tokens', $options) || !empty($options['weight_tokens']));
    }

    protected function extendedVariableRoleAliasesEnabled(array $options): bool
    {
        return $this->extendedVariableOutputEnabled($options)
            && (!array_key_exists('role_aliases', $options) || !empty($options['role_aliases']));
    }

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

    protected function buildProfileRequestSummary(array $profile): string
    {
        $provider = strtolower(trim((string) ($profile['provider'] ?? '')));
        $type = strtolower(trim((string) ($profile['type'] ?? '')));

        return match ($provider . ':' . $type) {
            'google:cdn' => __('External request via Google Fonts', 'tasty-fonts'),
            'bunny:cdn' => __('External request via Bunny Fonts', 'tasty-fonts'),
            'adobe:adobe_hosted' => __('Adobe-hosted project stylesheet', 'tasty-fonts'),
            default => __('Self-hosted files', 'tasty-fonts'),
        };
    }

    protected function isMigratableCdnProfile(array $profile): bool
    {
        $provider = strtolower(trim((string) ($profile['provider'] ?? '')));
        $type = strtolower(trim((string) ($profile['type'] ?? '')));

        return $type === 'cdn' && in_array($provider, ['google', 'bunny'], true);
    }

    protected function buildFamilyFaceSummaryLabels(array $faces): array
    {
        $items = [];

        foreach ($faces as $face) {
            $weight = preg_replace('/[^0-9]/', '', (string) ($face['weight'] ?? '400'));
            $weight = $weight !== '' ? $weight : '400';
            $style = FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal'));
            $key = FontUtils::faceAxisKey($weight, $style);

            if (isset($items[$key])) {
                continue;
            }

            $items[$key] = [
                'weight' => (int) $weight,
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
                $weightComparison = ($left['weight'] ?? 0) <=> ($right['weight'] ?? 0);

                if ($weightComparison !== 0) {
                    return $weightComparison;
                }

                if (($left['style'] ?? 'normal') === ($right['style'] ?? 'normal')) {
                    return 0;
                }

                return ($left['style'] ?? 'normal') === 'normal' ? -1 : 1;
            }
        );

        return array_values(array_map(static fn (array $item): string => (string) ($item['label'] ?? ''), $items));
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

    protected function canDeleteFaceVariant(array $activeDelivery): bool
    {
        $provider = strtolower(trim((string) ($activeDelivery['provider'] ?? '')));
        $type = strtolower(trim((string) ($activeDelivery['type'] ?? '')));

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

        return wp_trim_words($normalized, 6, '…');
    }

    protected function buildMonospacePreviewText(string $familyName, bool $multiline = false): string
    {
        $familyName = trim($familyName) !== '' ? trim($familyName) : 'Monospace';
        $literal = str_replace(['\\', '"'], ['\\\\', '\\"'], $familyName);

        return sprintf('const font = "%s";', $literal);
    }

    protected function buildFaceStorageSummary(array $face): string
    {
        $relativePaths = array_filter(
            array_map(
                static fn (mixed $path): string => is_string($path) ? trim($path) : '',
                (array) ($face['paths'] ?? [])
            ),
            'strlen'
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
