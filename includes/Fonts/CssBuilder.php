<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type CatalogMap from CatalogService
 * @phpstan-import-type CatalogFamily from CatalogService
 * @phpstan-import-type CatalogFace from CatalogService
 * @phpstan-import-type NormalizedSettings from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type RoleSet from \TastyFonts\Repository\SettingsRepository
 * @phpstan-type VariableFamilyMap array<int|string, CatalogFamily>
 * @phpstan-type DeclarationValue string|int|float
 * @phpstan-type DeclarationMap array<string, DeclarationValue>
 * @phpstan-type DeclarationLines list<string>
 * @phpstan-type DeclarationGroups array<string, DeclarationMap>
 * @phpstan-type CommentedRuleBlock array{comment: string, lines: list<string>}
 * @phpstan-type CommentedRuleBlocks list<CommentedRuleBlock>
 * @phpstan-type ClassOutputBlock array{comment: string, css: string}
 */
final class CssBuilder
{
    private const FORMAT_ORDER = ['woff2', 'woff', 'ttf', 'otf'];

    /**
     * @param CatalogMap $catalog
     * @param RoleSet $roles
     * @param NormalizedSettings $settings
     * @param VariableFamilyMap $variableFamilies
     */
    public function build(array $catalog, array $roles, array $settings, array $variableFamilies = []): string
    {
        return $this->buildCss($catalog, $roles, $settings, true, $variableFamilies);
    }

    /**
     * @param CatalogMap $catalog
     * @param NormalizedSettings $settings
     */
    public function buildFontFaceOnly(array $catalog, array $settings, string $displayOverride = ''): string
    {
        return $this->buildCss($catalog, [], $settings, false, [], $displayOverride);
    }

    /**
     * @param CatalogMap $catalog
     * @param RoleSet $roles
     * @param NormalizedSettings $settings
     * @param VariableFamilyMap $variableFamilies
     */
    private function buildCss(
        array $catalog,
        array $roles,
        array $settings,
        bool $includeRoleUsage,
        array $variableFamilies = [],
        string $displayOverride = ''
    ): string {
        $blocks = [];
        $defaultDisplay = $this->resolveFontDisplay((string) ($settings['font_display'] ?? 'swap'));
        $familyDisplays = is_array($settings['family_font_displays'] ?? null) ? $settings['family_font_displays'] : [];
        $includeMonospace = !empty($settings['monospace_role_enabled']);

        foreach ($catalog as $family) {
            $familyName = (string) ($family['family'] ?? '');
            $display = $displayOverride !== ''
                ? $this->resolveFontDisplay($displayOverride)
                : $this->resolveFamilyFontDisplay($familyName, $familyDisplays, $defaultDisplay);

            foreach ((array) ($family['faces'] ?? []) as $face) {
                $rule = $this->buildFaceRule($face, $display, $settings);

                if ($rule !== '') {
                    $blocks[] = $rule;
                }
            }
        }

        if ($includeRoleUsage && !empty($settings['auto_apply_roles'])) {
            $variableCss = $this->buildRoleVariableSnippet($roles, $includeMonospace, $variableFamilies, $settings);

            if ($variableCss !== '') {
                $blocks[] = $variableCss;
            }

            $usageCss = $this->buildRoleUsageRulesSnippet($roles, $includeMonospace, $settings);

            if ($usageCss !== '') {
                $blocks[] = $usageCss;
            }
        }

        $classCss = $this->buildClassOutputSnippet($roles, $includeMonospace, $variableFamilies, $settings);

        if ($classCss !== '') {
            $blocks[] = $classCss;
        }

        $css = implode("\n", $blocks);

        if (!empty($settings['minify_css_output'])) {
            $css = $this->minify($css);
        }

        return trim($css);
    }

    /**
     * @param RoleSet $roles
     * @param VariableFamilyMap $variableFamilies
     * @param NormalizedSettings $settings
     */
    public function buildRoleUsageSnippet(
        array $roles,
        bool $includeMonospace = false,
        array $variableFamilies = [],
        array $settings = [],
        bool $includeComments = false
    ): string {
        $variableSnippet = $this->buildRoleVariableSnippet($roles, $includeMonospace, $variableFamilies, $settings, $includeComments);
        $usageRules = $this->buildRoleUsageRulesSnippet($roles, $includeMonospace, $settings, $includeComments);

        if ($variableSnippet === '' && $usageRules === '') {
            return '';
        }

        if ($variableSnippet === '') {
            return $usageRules;
        }

        if ($usageRules === '') {
            return $variableSnippet;
        }

        return $variableSnippet . "\n\n" . $usageRules;
    }

    /**
     * @param RoleSet $roles
     * @param VariableFamilyMap $variableFamilies
     * @param NormalizedSettings $settings
     */
    public function buildRoleVariableSnippet(
        array $roles,
        bool $includeMonospace = false,
        array $variableFamilies = [],
        array $settings = [],
        bool $includeComments = false
    ): string {
        $variableLines = $this->buildRoleVariableLines($roles, $includeMonospace, $variableFamilies, $settings, $includeComments);

        if ($variableLines === []) {
            return '';
        }

        return implode("\n", $variableLines);
    }

    /**
     * @param RoleSet $roles
     * @param VariableFamilyMap $variableFamilies
     * @param NormalizedSettings $settings
     */
    public function buildRoleVariableDeclarationsSnippet(
        array $roles,
        bool $includeMonospace = false,
        array $variableFamilies = [],
        array $settings = [],
        bool $includeComments = false
    ): string {
        $variableLines = $this->buildRoleVariableLines($roles, $includeMonospace, $variableFamilies, $settings, $includeComments);

        if ($variableLines === []) {
            return '';
        }

        if ($variableLines[0] === ':root {' && $variableLines[count($variableLines) - 1] === '}') {
            $variableLines = array_slice($variableLines, 1, -1);
        }

        return implode("\n", $variableLines);
    }

    /**
     * @param RoleSet $roles
     * @param NormalizedSettings $settings
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     */
    public function buildRoleStackSnippet(array $roles, bool $includeMonospace = false, array $settings = [], array $families = []): string
    {
        $stacks = [
            FontUtils::buildFontStack((string) ($roles['heading'] ?? ''), $this->resolveRoleFallback('heading', $roles, $settings, $families)),
            FontUtils::buildFontStack((string) ($roles['body'] ?? ''), $this->resolveRoleFallback('body', $roles, $settings, $families)),
        ];

        if ($includeMonospace) {
            $stacks[] = FontUtils::buildFontStack(
                (string) ($roles['monospace'] ?? ''),
                $this->resolveRoleFallback('monospace', $roles, $settings, $families)
            );
        }

        return implode("\n", $stacks);
    }

    /**
     * @param RoleSet $roles
     */
    public function buildRoleNameSnippet(array $roles, bool $includeMonospace = false): string
    {
        $names = [(string) ($roles['heading'] ?? ''), (string) ($roles['body'] ?? '')];

        if ($includeMonospace) {
            $names[] = (string) ($roles['monospace'] ?? '');
        }

        return implode("\n", $names);
    }

    /**
     * @param RoleSet $roles
     * @param NormalizedSettings $settings
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     */
    public function buildRoleClassSnippet(array $roles, bool $includeMonospace = false, array $settings = [], array $families = []): string
    {
        $blocks = [];

        if ($this->classOutputRoleEnabled($settings, 'heading')) {
            $blocks[] = $this->buildClassRule('.font-heading', (string) ($roles['heading'] ?? ''), $this->resolveRoleFallback('heading', $roles, $settings, $families));
        }

        if ($this->classOutputRoleEnabled($settings, 'body')) {
            $blocks[] = $this->buildClassRule('.font-body', (string) ($roles['body'] ?? ''), $this->resolveRoleFallback('body', $roles, $settings, $families));
        }

        if ($includeMonospace && $this->classOutputRoleEnabled($settings, 'monospace')) {
            $blocks[] = $this->buildClassRule(
                '.font-monospace',
                (string) ($roles['monospace'] ?? ''),
                $this->resolveRoleFallback('monospace', $roles, $settings, $families)
            );
        }

        return implode("\n\n", array_filter($blocks, static fn (string $block): bool => $block !== ''));
    }

    /**
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     * @param NormalizedSettings $settings
     */
    public function buildFamilyClassSnippet(array $families, array $settings = []): string
    {
        $lines = [];
        $seenSelectors = [];

        foreach ($families as $family) {
            $familyName = trim((string) ($family['family'] ?? ''));
            $selector = $this->familyClassSelector($familyName);

            if ($familyName === '' || $selector === '' || isset($seenSelectors[$selector])) {
                continue;
            }

            $lines = [
                ...$lines,
                $selector . ' {',
                '  font-family: ' . FontUtils::buildFontStack($familyName, $this->resolveFamilyFallback($family, $settings)) . ';',
                '}',
                '',
            ];
            $seenSelectors[$selector] = true;
        }

        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return implode("\n", $lines);
    }

    /**
     * @param RoleSet $roles
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     * @param NormalizedSettings $settings
     */
    public function buildClassOutputSnippet(
        array $roles,
        bool $includeMonospace = false,
        array $families = [],
        array $settings = []
    ): string {
        $blocks = $this->buildClassOutputBlocks($roles, $includeMonospace, $families, $settings);

        return implode(
            "\n\n",
            array_values(
                array_filter(
                    array_map(
                        static fn (array $block): string => $block['css'],
                        $blocks
                    ),
                    static fn (string $css): bool => $css !== ''
                )
            )
        );
    }

    /**
     * @param RoleSet $roles
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     * @param NormalizedSettings $settings
     */
    public function buildCommentedClassOutputSnippet(
        array $roles,
        bool $includeMonospace = false,
        array $families = [],
        array $settings = []
    ): string {
        $lines = [];

        foreach ($this->buildClassOutputBlocks($roles, $includeMonospace, $families, $settings) as $block) {
            $css = trim($block['css']);

            if ($css === '') {
                continue;
            }

            if ($lines !== []) {
                $lines[] = '';
            }

            $comment = trim($block['comment']);

            if ($comment !== '') {
                $lines[] = '/* ' . $comment . ' */';
            }

            foreach (preg_split("/\r\n|\n|\r/", $css) ?: [$css] as $line) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    public function formatOutput(string $css, bool $minify = false): string
    {
        if (trim($css) === '') {
            return '';
        }

        return $minify ? $this->minify($css) : $css;
    }

    /**
     * @param RoleSet $roles
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     * @param NormalizedSettings $settings
     * @return list<ClassOutputBlock>
     */
    private function buildClassOutputBlocks(
        array $roles,
        bool $includeMonospace = false,
        array $families = [],
        array $settings = []
    ): array {
        $blocks = [];

        if ($this->classOutputEnabled($settings)) {
            $roleClasses = $this->buildRoleClassSnippet($roles, $includeMonospace, $settings, $families);
            $roleAliasClasses = $this->buildRoleAliasClassSnippet($roles, $includeMonospace, $settings, $families);
            $categoryClasses = $this->buildCategoryAliasClassSnippet($roles, $includeMonospace, $families, $settings);

            if ($roleClasses !== '') {
                $blocks[] = [
                    'comment' => 'Role classes',
                    'css' => $roleClasses,
                ];
            }

            if ($roleAliasClasses !== '') {
                $blocks[] = [
                    'comment' => 'Role alias classes',
                    'css' => $roleAliasClasses,
                ];
            }

            if ($categoryClasses !== '') {
                $blocks[] = [
                    'comment' => 'Category classes',
                    'css' => $categoryClasses,
                ];
            }
        }

        if ($this->classOutputFamiliesEnabled($settings)) {
            $familyClasses = $this->buildFamilyClassSnippet($families, $settings);

            if ($familyClasses !== '') {
                $blocks[] = [
                    'comment' => 'Family classes',
                    'css' => $familyClasses,
                ];
            }
        }

        return $blocks;
    }

    /**
     * @param CatalogFace $face
     * @param NormalizedSettings $settings
     */
    private function buildFaceRule(array $face, string $display, array $settings = []): string
    {
        $family = (string) ($face['family'] ?? '');
        $weight = FontUtils::normalizeWeight((string) ($face['weight'] ?? '400'));
        $style = FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal'));
        $unicodeRange = FontUtils::resolveFaceUnicodeRange($face, $settings);
        $files = is_array($face['files'] ?? null) ? $face['files'] : [];
        $axes = FontUtils::normalizeAxesMap($face['axes'] ?? []);
        $variationDefaults = FontUtils::faceLevelVariationDefaults($face['variation_defaults'] ?? [], $axes);

        if ($family === '' || $files === []) {
            return '';
        }

        $css = "@font-face{\n";
        $css .= '  font-family:"' . FontUtils::escapeFontFamily($family) . "\";\n";
        $css .= '  font-weight:' . $this->fontWeightDescriptor($weight, $axes) . ";\n";
        $css .= "  font-style:{$style};\n";

        $sources = [];

        foreach (self::FORMAT_ORDER as $format) {
            if (!isset($files[$format])) {
                continue;
            }

            $sources[] = $this->buildSourceEntry($format, (string) $files[$format]);
        }

        if ($sources !== []) {
            $css .= "  src:" . implode(",\n      ", $sources) . ";\n";
        }

        if ($unicodeRange !== '') {
            $css .= "  unicode-range:{$unicodeRange};\n";
        }

        $variationSettings = FontUtils::buildFontVariationSettings($variationDefaults);

        if ($variationSettings !== 'normal') {
            $css .= '  font-variation-settings:' . $variationSettings . ";\n";
        }

        $css .= '  font-display:' . $this->resolveFontDisplay($display) . ";\n";
        $css .= "}\n";

        return $css;
    }

    /**
     * @param array<string, string> $familyDisplays
     */
    private function resolveFamilyFontDisplay(string $family, array $familyDisplays, string $defaultDisplay): string
    {
        if ($family === '' || !array_key_exists($family, $familyDisplays)) {
            return $defaultDisplay;
        }

        return $this->resolveFontDisplay((string) $familyDisplays[$family]);
    }

    private function resolveFontDisplay(string $display): string
    {
        return $this->sanitizeKeyword(
            $display,
            ['auto', 'block', 'swap', 'fallback', 'optional'],
            'swap'
        );
    }

    private function minify(string $css): string
    {
        $css = trim($css);

        if ($css === '') {
            return '';
        }

        $result = '';
        $length = strlen($css);
        $quote = null;
        $escapeNext = false;

        for ($index = 0; $index < $length; $index++) {
            $character = $css[$index];
            $next = $index + 1 < $length ? $css[$index + 1] : null;

            if ($quote !== null) {
                $result .= $character;

                if ($escapeNext) {
                    $escapeNext = false;
                    continue;
                }

                if ($character === '\\') {
                    $escapeNext = true;
                    continue;
                }

                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '/' && $next === '*') {
                $commentEnd = strpos($css, '*/', $index + 2);

                if ($commentEnd === false) {
                    break;
                }

                $index = $commentEnd + 1;
                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $result .= $character;
                continue;
            }

            if (preg_match('/\s/', $character) === 1) {
                if ($result === '') {
                    continue;
                }

                $nextIndex = $index + 1;

                while ($nextIndex < $length && preg_match('/\s/', $css[$nextIndex]) === 1) {
                    $nextIndex++;
                }

                if ($nextIndex >= $length) {
                    continue;
                }

                $previousCharacter = $result[strlen($result) - 1] ?? '';
                $nextCharacter = $css[$nextIndex] ?? '';

                if ($this->minifyWhitespaceIsSignificant($previousCharacter, $nextCharacter)) {
                    $result .= ' ';
                }

                continue;
            }

            if ($character === ';' && $next === '}') {
                continue;
            }

            $result .= $character;
        }

        $result = str_replace(';}', '}', $result);

        return trim($result);
    }

    private function minifyWhitespaceIsSignificant(string $previousCharacter, string $nextCharacter): bool
    {
        if ($previousCharacter === '' || $nextCharacter === '') {
            return false;
        }

        if (str_contains('{}[:;,>+~(/', $previousCharacter)) {
            return false;
        }

        if (str_contains('{}:;,>+~)]/', $nextCharacter)) {
            return false;
        }

        return true;
    }

    /**
     * @param list<string> $allowed
     */
    private function sanitizeKeyword(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * @param RoleSet $roles
     * @param NormalizedSettings $settings
     */
    private function buildRoleUsageRulesSnippet(
        array $roles,
        bool $includeMonospace = false,
        array $settings = [],
        bool $includeComments = false
    ): string
    {
        if ($this->minimalOutputPresetEnabled($settings)) {
            return '';
        }

        if (trim((string) ($roles['heading'] ?? '')) === '' || trim((string) ($roles['body'] ?? '')) === '') {
            return '';
        }

        $includeExtendedVariables = $this->extendedVariableOutputEnabled($settings);
        $includeWeightTokens = $this->extendedVariableWeightTokensEnabled($settings);
        $includeRoleFontWeights = $this->roleUsageFontWeightEnabled($settings);
        $bodyWeight = $this->resolveRoleUsageWeightValue($roles, 'body');
        $headingWeight = $this->resolveRoleUsageWeightValue($roles, 'heading');
        $monospaceWeight = $this->resolveRoleUsageWeightValue($roles, 'monospace');
        $bodyWeightFromAxes = $this->roleUsageWeightComesFromAxis($roles, 'body');
        $headingWeightFromAxes = $this->roleUsageWeightComesFromAxis($roles, 'heading');
        $monospaceWeightFromAxes = $this->roleUsageWeightComesFromAxis($roles, 'monospace');

        $bodyLines = [
            'body {',
            '  font-family: var(--font-body);',
            '  font-variation-settings: var(--font-body-settings);',
        ];

        if ($includeRoleFontWeights) {
            $bodyWeightDeclaration = $this->buildRoleWeightDeclaration($bodyWeight, $includeWeightTokens, $includeExtendedVariables, $bodyWeightFromAxes);

            if ($bodyWeightDeclaration !== '') {
                $bodyLines[] = '  font-weight: ' . $bodyWeightDeclaration . ';';
            }
        }

        $bodyLines[] = '}';

        $headingLines = [
            'h1, h2, h3, h4, h5, h6 {',
            '  font-family: var(--font-heading);',
            '  font-variation-settings: var(--font-heading-settings);',
        ];

        if ($includeRoleFontWeights) {
            $headingWeightDeclaration = $this->buildRoleWeightDeclaration($headingWeight, $includeWeightTokens, $includeExtendedVariables, $headingWeightFromAxes);

            if ($headingWeightDeclaration !== '') {
                $headingLines[] = '  font-weight: ' . $headingWeightDeclaration . ';';
            }
        }

        $headingLines[] = '}';
        $blocks = [
            [
                'comment' => 'Body text',
                'lines' => $bodyLines,
            ],
            [
                'comment' => 'Headings',
                'lines' => $headingLines,
            ],
        ];

        if ($includeMonospace) {
            $monospaceLines = [
                'code, pre {',
                '  font-family: var(--font-monospace);',
                '  font-variation-settings: var(--font-monospace-settings);',
            ];

            if ($includeRoleFontWeights) {
                $monospaceWeightDeclaration = $this->buildRoleWeightDeclaration($monospaceWeight, $includeWeightTokens, $includeExtendedVariables, $monospaceWeightFromAxes);

                if ($monospaceWeightDeclaration !== '') {
                    $monospaceLines[] = '  font-weight: ' . $monospaceWeightDeclaration . ';';
                }
            }

            $monospaceLines[] = '}';
            $blocks[] = [
                'comment' => 'Code blocks',
                'lines' => $monospaceLines,
            ];
        }

        if ($includeComments) {
            return $this->buildCommentedRuleSnippet($blocks);
        }

        return implode(
            "\n\n",
            array_map(
                static fn (array $block): string => implode("\n", $block['lines']),
                $blocks
            )
        );
    }

    /**
     * @param RoleSet $roles
     * @param VariableFamilyMap $variableFamilies
     * @param NormalizedSettings $settings
     * @return DeclarationLines
     */
    private function buildRoleVariableLines(
        array $roles,
        bool $includeMonospace = false,
        array $variableFamilies = [],
        array $settings = [],
        bool $includeComments = false
    ): array {
        $headingFamily = trim((string) ($roles['heading'] ?? ''));
        $bodyFamily = trim((string) ($roles['body'] ?? ''));
        $monospaceFamily = trim((string) ($roles['monospace'] ?? ''));
        $headingFallback = $this->resolveRoleFallback('heading', $roles, $settings, $variableFamilies);
        $bodyFallback = $this->resolveRoleFallback('body', $roles, $settings, $variableFamilies);
        $monospaceFallback = $this->resolveRoleFallback('monospace', $roles, $settings, $variableFamilies);
        $roleDeclarations = [];
        $variationDeclarations = [];

        if ($this->minimalOutputPresetEnabled($settings)) {
            $roleDeclarations = [
                '--font-heading' => FontUtils::buildFontStack($headingFamily, $headingFallback),
                '--font-body' => FontUtils::buildFontStack($bodyFamily, $bodyFallback),
            ];

            if ($includeMonospace) {
                $roleDeclarations['--font-monospace'] = FontUtils::buildFontStack($monospaceFamily, $monospaceFallback);
            }

            $this->appendRoleVariationDeclarations($variationDeclarations, $roles, 'heading');
            $this->appendRoleVariationDeclarations($variationDeclarations, $roles, 'body');

            if ($this->extendedVariableRoleWeightVarsEnabled($settings)) {
                $roleDeclarations['--font-heading-weight'] = $this->resolveRoleUsageWeightValue($roles, 'heading');
                $roleDeclarations['--font-body-weight'] = $this->resolveRoleUsageWeightValue($roles, 'body');
            }

            if ($includeMonospace) {
                if ($this->extendedVariableRoleWeightVarsEnabled($settings)) {
                    $roleDeclarations['--font-monospace-weight'] = $this->resolveRoleUsageWeightValue($roles, 'monospace');
                }

                $this->appendRoleVariationDeclarations($variationDeclarations, $roles, 'monospace');
            }

            if (!$includeComments) {
                $declarations = [];
                $this->appendDeclarations($declarations, $roleDeclarations);
                $this->appendDeclarations($declarations, $variationDeclarations);

                return $this->buildRootDeclarationLines($declarations);
            }

            return $this->buildCommentedVariableLines([
                'Role font stacks' => $roleDeclarations,
                'Role variation settings' => $variationDeclarations,
            ]);
        }

        $includeExtendedVariables = $this->extendedVariableOutputEnabled($settings);
        $familyDeclarations = $this->buildFamilyVariableDeclarations($variableFamilies, $settings);
        $categoryAliasDeclarations = $includeExtendedVariables
            ? $this->buildCategoryAliasDeclarations($roles, $includeMonospace, $variableFamilies, $settings)
            : [];
        $roleAliasDeclarations = [];
        $weightDeclarations = [];

        if ($headingFamily !== '') {
            $roleDeclarations['--font-heading'] = FontUtils::buildFontStack($headingFamily, $headingFallback);
        }

        if ($bodyFamily !== '') {
            $roleDeclarations['--font-body'] = FontUtils::buildFontStack($bodyFamily, $bodyFallback);
        }

        if ($includeMonospace) {
            $roleDeclarations['--font-monospace'] = FontUtils::buildFontStack($monospaceFamily, $monospaceFallback);
        }

        $this->appendRoleVariationDeclarations($variationDeclarations, $roles, 'heading');
        $this->appendRoleVariationDeclarations($variationDeclarations, $roles, 'body');

        if ($this->extendedVariableRoleWeightVarsEnabled($settings)) {
            $roleDeclarations['--font-heading-weight'] = $this->resolveRoleUsageWeightValue($roles, 'heading');
            $roleDeclarations['--font-body-weight'] = $this->resolveRoleUsageWeightValue($roles, 'body');
        }

        if ($includeMonospace) {
            if ($this->extendedVariableRoleWeightVarsEnabled($settings)) {
                $roleDeclarations['--font-monospace-weight'] = $this->resolveRoleUsageWeightValue($roles, 'monospace');
            }

            $this->appendRoleVariationDeclarations($variationDeclarations, $roles, 'monospace');
        }

        if ($this->extendedVariableRoleAliasesEnabled($settings)) {
            if ($bodyFamily !== '') {
                $roleAliasDeclarations['--font-interface'] = 'var(--font-body)';
                $roleAliasDeclarations['--font-ui'] = 'var(--font-body)';
            }

            if ($includeMonospace && trim((string) ($roles['monospace'] ?? '')) !== '') {
                $roleAliasDeclarations['--font-code'] = 'var(--font-monospace)';
            }
        }

        if ($this->extendedVariableWeightTokensEnabled($settings)) {
            $weightDeclarations = $this->buildWeightVariableDeclarations($variableFamilies);
        }

        $declarations = [];
        $this->appendDeclarations($declarations, $familyDeclarations);
        $this->appendDeclarations($declarations, $categoryAliasDeclarations);
        $this->appendDeclarations($declarations, $roleDeclarations);
        $this->appendDeclarations($declarations, $variationDeclarations);
        $this->appendDeclarations($declarations, $roleAliasDeclarations);
        $this->appendDeclarations($declarations, $weightDeclarations);

        if (!$includeComments) {
            return $this->buildRootDeclarationLines($declarations);
        }

        return $this->buildCommentedVariableLines([
            'Family font stacks' => $familyDeclarations,
            'Category aliases' => $categoryAliasDeclarations,
            'Role font stacks' => $roleDeclarations,
            'Role variation settings' => $variationDeclarations,
            'Role aliases' => $roleAliasDeclarations,
            'Weight tokens' => $weightDeclarations,
        ]);
    }

    /**
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     * @param NormalizedSettings $settings
     * @return DeclarationMap
     */
    private function buildFamilyVariableDeclarations(array $families, array $settings): array
    {
        $declarations = [];

        foreach ($families as $family) {
            $familyName = trim((string) ($family['family'] ?? ''));
            $familyVariable = FontUtils::fontVariableName($familyName);

            if ($familyName === '' || $familyVariable === '') {
                continue;
            }

            $declarations[$familyVariable] = FontUtils::buildFontStack(
                $familyName,
                $this->resolveFamilyFallback($family, $settings)
            );
        }

        return $declarations;
    }

    /**
     * @param DeclarationMap $target
     * @param DeclarationMap $declarations
     */
    private function appendDeclarations(array &$target, array $declarations): void
    {
        foreach ($declarations as $property => $value) {
            if ($property === '' || isset($target[$property])) {
                continue;
            }

            $target[$property] = $value;
        }
    }

    /**
     * @param DeclarationMap $declarations
     * @return DeclarationLines
     */
    private function buildRootDeclarationLines(array $declarations): array
    {
        $lines = [':root {'];

        foreach ($declarations as $property => $value) {
            $lines[] = "  {$property}: {$value};";
        }

        $lines[] = '}';

        return $lines;
    }

    /**
     * @param DeclarationGroups $groups
     * @return DeclarationLines
     */
    private function buildCommentedVariableLines(array $groups): array
    {
        $lines = [':root {'];
        $hasContent = false;

        foreach ($groups as $label => $declarations) {
            if ($declarations === []) {
                continue;
            }

            if ($hasContent) {
                $lines[] = '';
            }

            $lines[] = '  /* ' . $label . ' */';

            foreach ($declarations as $property => $value) {
                $lines[] = "  {$property}: {$value};";
            }

            $hasContent = true;
        }

        $lines[] = '}';

        return $lines;
    }

    /**
     * @param CommentedRuleBlocks $blocks
     */
    private function buildCommentedRuleSnippet(array $blocks): string
    {
        $lines = [];

        foreach ($blocks as $block) {
            $ruleLines = $block['lines'];

            if ($ruleLines === []) {
                continue;
            }

            if ($lines !== []) {
                $lines[] = '';
            }

            $comment = trim($block['comment']);

            if ($comment !== '') {
                $lines[] = '/* ' . $comment . ' */';
            }

            foreach ($ruleLines as $line) {
                $lines[] = (string) $line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param RoleSet $roles
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     * @param NormalizedSettings $settings
     * @return DeclarationMap
     */
    private function buildCategoryAliasDeclarations(array $roles, bool $includeMonospace, array $families, array $settings = []): array
    {
        $categoryVariables = [
            'sans' => '--font-sans',
            'serif' => '--font-serif',
            'mono' => '--font-mono',
        ];
        $declarations = [];

        foreach ($this->orderedAliasFamilies($roles, $includeMonospace, $families) as $family) {
            $categoryKey = $this->resolveCategoryAliasKey($family);
            $property = $categoryVariables[$categoryKey] ?? '';

            if (
                $categoryKey === ''
                || ($categoryKey === 'mono' && !$includeMonospace)
                || !$this->extendedVariableCategoryAliasEnabled($settings, $categoryKey)
                || ($property !== '' && isset($declarations[$property]))
            ) {
                continue;
            }

            $reference = FontUtils::fontVariableReference((string) ($family['family'] ?? ''));

            if ($property === '' || $reference === '') {
                continue;
            }

            $declarations[$property] = $reference;
        }

        return $declarations;
    }

    /**
     * @param RoleSet $roles
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     * @param NormalizedSettings $settings
     */
    private function buildCategoryAliasClassSnippet(array $roles, bool $includeMonospace, array $families, array $settings = []): string
    {
        $blocks = [];
        $selectors = [
            'sans' => '.font-sans',
            'serif' => '.font-serif',
            'mono' => '.font-mono',
        ];

        foreach ($this->orderedAliasFamilies($roles, $includeMonospace, $families) as $family) {
            $categoryKey = $this->resolveCategoryAliasKey($family);
            $selector = $selectors[$categoryKey] ?? '';
            $familyName = trim((string) ($family['family'] ?? ''));

            if (
                $selector === ''
                || $familyName === ''
                || ($categoryKey === 'mono' && !$includeMonospace)
                || !$this->classOutputCategoryAliasEnabled($settings, $categoryKey)
            ) {
                continue;
            }

            if (isset($blocks[$selector])) {
                continue;
            }

            $blocks[$selector] = $this->buildClassRule(
                $selector,
                $familyName,
                $this->resolveFamilyFallback($family, $settings)
            );
        }

        return implode("\n\n", array_values($blocks));
    }

    /**
     * @param RoleSet $roles
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     * @return list<CatalogFamily>
     */
    private function orderedAliasFamilies(array $roles, bool $includeMonospace, array $families): array
    {
        $orderedFamilies = [];
        $usedKeys = [];
        $priorityNames = [
            trim((string) ($roles['heading'] ?? '')),
            trim((string) ($roles['body'] ?? '')),
        ];

        if ($includeMonospace) {
            $priorityNames[] = trim((string) ($roles['monospace'] ?? ''));
        }

        foreach ($priorityNames as $priorityName) {
            if ($priorityName === '') {
                continue;
            }

            foreach ($families as $familyKey => $family) {
                if (isset($usedKeys[$familyKey])) {
                    continue;
                }

                if (trim((string) ($family['family'] ?? '')) !== $priorityName) {
                    continue;
                }

                $orderedFamilies[] = $family;
                $usedKeys[$familyKey] = true;
                break;
            }
        }

        foreach ($families as $familyKey => $family) {
            if (isset($usedKeys[$familyKey])) {
                continue;
            }

            $orderedFamilies[] = $family;
        }

        return $orderedFamilies;
    }

    /**
     * @param CatalogFamily $family
     */
    private function resolveCategoryAliasKey(array $family): string
    {
        $category = strtolower(trim((string) ($family['font_category'] ?? '')));

        if ($category === '' && is_array($family['active_delivery'] ?? null) && is_array($family['active_delivery']['meta'] ?? null)) {
            $category = strtolower(trim((string) ($family['active_delivery']['meta']['category'] ?? '')));
        }

        return match ($category) {
            'sans-serif', 'sans serif' => 'sans',
            'serif', 'slab-serif', 'slab serif' => 'serif',
            'monospace' => 'mono',
            default => '',
        };
    }

    /**
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     * @return DeclarationMap
     */
    private function buildWeightVariableDeclarations(array $families): array
    {
        $weights = [
            '400' => true,
            '700' => true,
        ];

        foreach ($families as $family) {
            foreach ((array) ($family['faces'] ?? []) as $face) {
                if (!is_array($face)) {
                    continue;
                }

                $weight = $this->resolveConcreteWeightValue((string) ($face['weight'] ?? '400'));

                if ($weight === '') {
                    continue;
                }

                $weights[$weight] = true;
            }
        }

        $sortedWeights = array_keys($weights);
        usort(
            $sortedWeights,
            static fn (int|string $left, int|string $right): int => ((int) $left) <=> ((int) $right)
        );

        $declarations = [];

        foreach ($sortedWeights as $weight) {
            $numericProperty = FontUtils::weightVariableName($weight);
            $semanticProperty = FontUtils::weightSemanticVariableName($weight);

            if ($numericProperty === '') {
                continue;
            }

            $declarations[$numericProperty] = $weight;

            if ($semanticProperty !== '' && $semanticProperty !== $numericProperty) {
                $declarations[$semanticProperty] = 'var(' . $numericProperty . ')';
            }
        }

        return $declarations;
    }

    private function resolveConcreteWeightValue(string $weight): string
    {
        if (trim($weight) === '') {
            return '';
        }

        $numericProperty = FontUtils::weightVariableName($weight);

        if ($numericProperty === '') {
            return '';
        }

        return substr($numericProperty, strlen('--weight-'));
    }

    /**
     * @param RoleSet $roles
     */
    private function resolveRoleUsageWeightValue(array $roles, string $roleKey): string
    {
        $axes = FontUtils::normalizeVariationDefaults($roles[$roleKey . '_axes'] ?? []);
        $weightFromAxes = $this->resolveConcreteWeightValue((string) ($axes['WGHT'] ?? ''));

        if ($weightFromAxes !== '') {
            return $weightFromAxes;
        }

        $storedWeight = $this->resolveConcreteWeightValue((string) ($roles[$roleKey . '_weight'] ?? ''));

        if ($storedWeight !== '') {
            return $storedWeight;
        }

        return $roleKey === 'heading' ? '700' : '400';
    }

    /**
     * @param RoleSet $roles
     */
    private function roleUsageWeightComesFromAxis(array $roles, string $roleKey): bool
    {
        $axes = FontUtils::normalizeVariationDefaults($roles[$roleKey . '_axes'] ?? []);

        return $this->resolveConcreteWeightValue((string) ($axes['WGHT'] ?? '')) !== '';
    }

    private function buildRoleWeightDeclaration(string $weight, bool $includeWeightTokens, bool $includeExtendedVariables, bool $preferRaw = false): string
    {
        if ($includeWeightTokens && !$preferRaw) {
            $reference = FontUtils::weightVariableReference($weight);

            if ($reference !== '') {
                return $reference;
            }
        }

        if ($weight !== '') {
            return $weight;
        }

        return $includeExtendedVariables ? $weight : '';
    }

    /**
     * @param RoleSet $roles
     * @param NormalizedSettings $settings
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     */
    private function buildRoleAliasClassSnippet(array $roles, bool $includeMonospace = false, array $settings = [], array $families = []): string
    {
        $blocks = [];
        $bodyFamily = trim((string) ($roles['body'] ?? ''));
        $bodyFallback = $this->resolveRoleFallback('body', $roles, $settings, $families);
        $monospaceFamily = trim((string) ($roles['monospace'] ?? ''));
        $monospaceFallback = $this->resolveRoleFallback('monospace', $roles, $settings, $families);

        if ($bodyFamily !== '' && $this->classOutputRoleAliasEnabled($settings, 'interface')) {
            $blocks[] = $this->buildClassRule('.font-interface', $bodyFamily, $bodyFallback);
        }

        if ($bodyFamily !== '' && $this->classOutputRoleAliasEnabled($settings, 'ui')) {
            $blocks[] = $this->buildClassRule('.font-ui', $bodyFamily, $bodyFallback);
        }

        if ($includeMonospace && $monospaceFamily !== '' && $this->classOutputRoleAliasEnabled($settings, 'code')) {
            $blocks[] = $this->buildClassRule('.font-code', $monospaceFamily, $monospaceFallback);
        }

        return implode("\n\n", array_filter($blocks, static fn (string $block): bool => $block !== ''));
    }

    private function familyClassSelector(string $family): string
    {
        $slug = FontUtils::slugify($family);

        return $slug !== '' ? '.font-' . $slug : '';
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function extendedVariableOutputEnabled(array $settings): bool
    {
        return !array_key_exists('per_variant_font_variables_enabled', $settings)
            || !empty($settings['per_variant_font_variables_enabled']);
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function classOutputEnabled(array $settings): bool
    {
        return !$this->minimalOutputPresetEnabled($settings) && !empty($settings['class_output_enabled']);
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function minimalOutputPresetEnabled(array $settings): bool
    {
        return !empty($settings['minimal_output_preset_enabled']);
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function classOutputFamiliesEnabled(array $settings): bool
    {
        return $this->classOutputEnabled($settings)
            && (
                !array_key_exists('class_output_families_enabled', $settings)
                || !empty($settings['class_output_families_enabled'])
            );
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function classOutputRoleEnabled(array $settings, string $roleKey): bool
    {
        if (!$this->classOutputEnabled($settings)) {
            return false;
        }

        $field = match ($roleKey) {
            'heading' => 'class_output_role_heading_enabled',
            'body' => 'class_output_role_body_enabled',
            'monospace' => 'class_output_role_monospace_enabled',
            default => '',
        };

        return $field !== ''
            && (!array_key_exists($field, $settings) || !empty($settings[$field]));
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function classOutputRoleAliasEnabled(array $settings, string $aliasKey): bool
    {
        if (!$this->classOutputEnabled($settings)) {
            return false;
        }

        $field = match ($aliasKey) {
            'interface' => 'class_output_role_alias_interface_enabled',
            'ui' => 'class_output_role_alias_ui_enabled',
            'code' => 'class_output_role_alias_code_enabled',
            default => '',
        };

        return $field !== ''
            && (!array_key_exists($field, $settings) || !empty($settings[$field]));
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function classOutputCategoryAliasEnabled(array $settings, string $categoryKey): bool
    {
        if (!$this->classOutputEnabled($settings)) {
            return false;
        }

        $field = match ($categoryKey) {
            'sans' => 'class_output_category_sans_enabled',
            'serif' => 'class_output_category_serif_enabled',
            'mono' => 'class_output_category_mono_enabled',
            default => '',
        };

        return $field !== ''
            && (!array_key_exists($field, $settings) || !empty($settings[$field]));
    }

    private function buildClassRule(string $selector, string $family, string $fallback): string
    {
        return implode(
            "\n",
            [
                $selector . ' {',
                '  font-family: ' . FontUtils::buildFontStack($family, $fallback) . ';',
                '}',
            ]
        );
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function extendedVariableWeightTokensEnabled(array $settings): bool
    {
        return $this->extendedVariableOutputEnabled($settings)
            && (!array_key_exists('extended_variable_weight_tokens_enabled', $settings)
                || !empty($settings['extended_variable_weight_tokens_enabled']));
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function extendedVariableRoleWeightVarsEnabled(array $settings): bool
    {
        return !array_key_exists('extended_variable_role_weight_vars_enabled', $settings)
            || !empty($settings['extended_variable_role_weight_vars_enabled']);
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function roleUsageFontWeightEnabled(array $settings): bool
    {
        return !empty($settings['role_usage_font_weight_enabled']);
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function extendedVariableRoleAliasesEnabled(array $settings): bool
    {
        return $this->extendedVariableOutputEnabled($settings)
            && (!array_key_exists('extended_variable_role_aliases_enabled', $settings)
                || !empty($settings['extended_variable_role_aliases_enabled']));
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function extendedVariableCategoryAliasEnabled(array $settings, string $categoryKey): bool
    {
        if (!$this->extendedVariableOutputEnabled($settings)) {
            return false;
        }

        $field = match ($categoryKey) {
            'sans' => 'extended_variable_category_sans_enabled',
            'serif' => 'extended_variable_category_serif_enabled',
            'mono' => 'extended_variable_category_mono_enabled',
            default => '',
        };

        return $field !== ''
            && (!array_key_exists($field, $settings) || !empty($settings[$field]));
    }

    private function buildSourceEntry(string $format, string $url): string
    {
        $formatName = match ($format) {
            'otf' => 'opentype',
            'ttf' => 'truetype',
            default => $format,
        };
        $escapedUrl = esc_url_raw($url);

        return 'url("' . $escapedUrl . '") format("' . $formatName . '")';
    }

    /**
     * @param DeclarationMap $declarations
     * @param RoleSet $roles
     */
    private function appendRoleVariationDeclarations(array &$declarations, array $roles, string $roleKey): void
    {
        $variationDefaults = FontUtils::normalizeVariationDefaults($roles[$roleKey . '_axes'] ?? []);
        $declarations['--font-' . $roleKey . '-settings'] = FontUtils::buildFontVariationSettings($variationDefaults);

        foreach ($variationDefaults as $tag => $value) {
            $declarations['--font-' . $roleKey . '-axis-' . strtolower(FontUtils::cssAxisTag($tag))] = $value;
        }
    }

    /**
     * @param array<string, mixed> $axes
     */
    private function fontWeightDescriptor(string $weight, array $axes = []): string
    {
        if (isset($axes['WGHT']['min'], $axes['WGHT']['max'])) {
            return (string) $axes['WGHT']['min'] . ' ' . (string) $axes['WGHT']['max'];
        }

        if (preg_match('/^(\d{1,4})\.\.(\d{1,4})$/', $weight, $matches) === 1) {
            return $matches[1] . ' ' . $matches[2];
        }

        return $weight;
    }

    /**
     * @param RoleSet $roles
     * @param NormalizedSettings $settings
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     */
    private function resolveRoleFallback(string $roleKey, array $roles, array $settings, array $families = []): string
    {
        $default = $roleKey === 'monospace' ? 'monospace' : 'sans-serif';
        $familyName = trim((string) ($roles[$roleKey] ?? ''));

        if ($familyName !== '') {
            $family = $this->findFamilyByName($familyName, $families);

            if ($family !== null) {
                return $this->resolveFamilyFallback($family, $settings);
            }

            $fallbacks = is_array($settings['family_fallbacks'] ?? null) ? $settings['family_fallbacks'] : [];

            if (array_key_exists($familyName, $fallbacks)) {
                $configuredFallback = trim((string) $fallbacks[$familyName]);

                if ($configuredFallback !== '') {
                    return FontUtils::sanitizeFallback($configuredFallback);
                }
            }
        }

        $fallback = trim((string) ($roles[$roleKey . '_fallback'] ?? ''));

        return $fallback !== '' ? FontUtils::sanitizeFallback($fallback) : $default;
    }

    /**
     * @param CatalogMap|array<int|string, CatalogFamily> $families
     * @return CatalogFamily|null
     */
    private function findFamilyByName(string $familyName, array $families): ?array
    {
        $exactFamily = $families[$familyName] ?? null;

        if ($exactFamily !== null) {
            return $exactFamily;
        }

        foreach ($families as $family) {
            if (trim((string) ($family['family'] ?? '')) === $familyName) {
                return $family;
            }
        }

        return null;
    }

    /**
     * @param CatalogFamily $family
     * @param NormalizedSettings $settings
     */
    private function resolveFamilyFallback(array $family, array $settings): string
    {
        $familyName = trim((string) ($family['family'] ?? ''));
        $fallbacks = is_array($settings['family_fallbacks'] ?? null) ? $settings['family_fallbacks'] : [];

        if ($familyName !== '' && array_key_exists($familyName, $fallbacks)) {
            return FontUtils::sanitizeFallback((string) $fallbacks[$familyName]);
        }

        $category = strtolower(trim((string) ($family['font_category'] ?? '')));

        if ($category === '' && is_array($family['active_delivery'] ?? null) && is_array($family['active_delivery']['meta'] ?? null)) {
            $category = strtolower(trim((string) ($family['active_delivery']['meta']['category'] ?? '')));
        }

        return FontUtils::defaultFallbackForCategory($category);
    }
}
