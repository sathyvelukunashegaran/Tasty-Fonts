<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\CatalogService;
use TastyFonts\Fonts\CssBuilder;
use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type CatalogFamily from CatalogService
 * @phpstan-import-type CatalogMap from CatalogService
 * @phpstan-import-type NormalizedSettings from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type RoleSet from \TastyFonts\Repository\SettingsRepository
 * @phpstan-type OutputTogglePreview array{
 *     key: string,
 *     label: string,
 *     description: string,
 *     css: string,
 *     empty_message: string,
 *     is_empty: bool,
 *     dependency_key?: string
 * }
 * @phpstan-type OutputTogglePreviewMap array<string, OutputTogglePreview>
 */
final class OutputTogglePreviewBuilder
{
    public function __construct(private readonly CssBuilder $cssBuilder)
    {
    }

    /**
     * @param RoleSet $roles
     * @param NormalizedSettings $settings
     * @param CatalogMap $catalog
     * @param RoleSet|array{} $appliedRoles
     * @return OutputTogglePreviewMap
     */
    public function build(array $roles, array $settings, array $catalog = [], array $appliedRoles = []): array
    {
        $snippetRoles = !empty($settings['auto_apply_roles']) && $appliedRoles !== []
            ? $appliedRoles
            : $roles;
        $includeMonospace = !empty($settings['monospace_role_enabled']);
        $runtimeFamilies = $this->filterRuntimeVisibleFamilies($catalog);
        $snippets = $this->cssBuilder->buildOutputTogglePreviewSnippets(
            $snippetRoles,
            $includeMonospace,
            $runtimeFamilies,
            $settings
        );
        $previews = [];

        foreach ($this->definitions() as $key => $definition) {
            $css = (string) ($snippets[$key] ?? '');
            $preview = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'css' => $css,
                'empty_message' => $definition['empty_message'],
                'is_empty' => trim($css) === '',
            ];

            if (($definition['dependency_key'] ?? '') !== '') {
                $preview['dependency_key'] = $definition['dependency_key'];
            }

            $previews[$key] = $preview;
        }

        return $previews;
    }

    /**
     * @return array<string, array{label: string, description: string, empty_message: string, dependency_key?: string}>
     */
    private function definitions(): array
    {
        return [
            'role_usage_font_weight_enabled' => [
                'label' => __('Sitewide Role Weights CSS', 'tasty-fonts'),
                'description' => __('Font-weight declarations added to the sitewide body, heading, and code rules.', 'tasty-fonts'),
                'empty_message' => __('No sitewide role weight CSS is available for the current role assignment.', 'tasty-fonts'),
            ],
            'class_output_role_heading_enabled' => [
                'label' => __('Heading Class CSS', 'tasty-fonts'),
                'description' => __('Generated selector for the heading role utility class.', 'tasty-fonts'),
                'empty_message' => __('No heading class CSS is available for the current role assignment.', 'tasty-fonts'),
            ],
            'class_output_role_body_enabled' => [
                'label' => __('Body Class CSS', 'tasty-fonts'),
                'description' => __('Generated selector for the body role utility class.', 'tasty-fonts'),
                'empty_message' => __('No body class CSS is available for the current role assignment.', 'tasty-fonts'),
            ],
            'class_output_role_monospace_enabled' => [
                'label' => __('Monospace Class CSS', 'tasty-fonts'),
                'description' => __('Generated selector for the monospace role utility class.', 'tasty-fonts'),
                'empty_message' => __('Enable the monospace role to preview monospace class CSS.', 'tasty-fonts'),
                'dependency_key' => 'monospace-role',
            ],
            'class_output_role_styles_enabled' => [
                'label' => __('Role Style Declaration CSS', 'tasty-fonts'),
                'description' => __('Font-weight and variation declarations added to enabled role and alias class selectors.', 'tasty-fonts'),
                'empty_message' => __('No enabled role or alias class selectors can receive style declarations right now.', 'tasty-fonts'),
            ],
            'class_output_role_alias_interface_enabled' => [
                'label' => __('Interface Alias Class CSS', 'tasty-fonts'),
                'description' => __('Generated selector for the interface alias utility class.', 'tasty-fonts'),
                'empty_message' => __('Assign a body font family to preview interface alias class CSS.', 'tasty-fonts'),
                'dependency_key' => 'body-family',
            ],
            'class_output_role_alias_ui_enabled' => [
                'label' => __('UI Alias Class CSS', 'tasty-fonts'),
                'description' => __('Generated selector for the UI alias utility class.', 'tasty-fonts'),
                'empty_message' => __('Assign a body font family to preview UI alias class CSS.', 'tasty-fonts'),
                'dependency_key' => 'body-family',
            ],
            'class_output_role_alias_code_enabled' => [
                'label' => __('Code Alias Class CSS', 'tasty-fonts'),
                'description' => __('Generated selector for the code alias utility class.', 'tasty-fonts'),
                'empty_message' => __('Enable and assign the monospace role to preview code alias class CSS.', 'tasty-fonts'),
                'dependency_key' => 'monospace-family',
            ],
            'class_output_category_sans_enabled' => [
                'label' => __('Sans Category Class CSS', 'tasty-fonts'),
                'description' => __('Generated selector for the first published sans-family category alias.', 'tasty-fonts'),
                'empty_message' => __('No published sans family is available to preview this class.', 'tasty-fonts'),
                'dependency_key' => 'category-sans',
            ],
            'class_output_category_serif_enabled' => [
                'label' => __('Serif Category Class CSS', 'tasty-fonts'),
                'description' => __('Generated selector for the first published serif-family category alias.', 'tasty-fonts'),
                'empty_message' => __('No published serif family is available to preview this class.', 'tasty-fonts'),
                'dependency_key' => 'category-serif',
            ],
            'class_output_category_mono_enabled' => [
                'label' => __('Mono Category Class CSS', 'tasty-fonts'),
                'description' => __('Generated selector for the first published monospace-family category alias.', 'tasty-fonts'),
                'empty_message' => __('Enable and assign the monospace role with a published monospace family to preview this class.', 'tasty-fonts'),
                'dependency_key' => 'category-mono',
            ],
            'class_output_families_enabled' => [
                'label' => __('Per-Family Classes CSS', 'tasty-fonts'),
                'description' => __('Generated selectors for published library families.', 'tasty-fonts'),
                'empty_message' => __('No family classes are available until at least one published library family exists.', 'tasty-fonts'),
                'dependency_key' => 'family-library',
            ],
            'extended_variable_role_weight_vars_enabled' => [
                'label' => __('Role Weight Variables CSS', 'tasty-fonts'),
                'description' => __('Custom property declarations for the active role font weights.', 'tasty-fonts'),
                'empty_message' => __('No role weight variables are available for the current role assignment.', 'tasty-fonts'),
            ],
            'extended_variable_weight_tokens_enabled' => [
                'label' => __('Weight Token Variables CSS', 'tasty-fonts'),
                'description' => __('Global numeric and semantic weight token declarations from published runtime families.', 'tasty-fonts'),
                'empty_message' => __('No published runtime family weights are available for token preview.', 'tasty-fonts'),
                'dependency_key' => 'family-library',
            ],
            'extended_variable_role_alias_interface_enabled' => [
                'label' => __('Interface Alias Variable CSS', 'tasty-fonts'),
                'description' => __('Custom property declaration that maps the interface alias to the body role.', 'tasty-fonts'),
                'empty_message' => __('Assign a body font family to preview the interface alias variable.', 'tasty-fonts'),
                'dependency_key' => 'body-family',
            ],
            'extended_variable_role_alias_ui_enabled' => [
                'label' => __('UI Alias Variable CSS', 'tasty-fonts'),
                'description' => __('Custom property declaration that maps the UI alias to the body role.', 'tasty-fonts'),
                'empty_message' => __('Assign a body font family to preview the UI alias variable.', 'tasty-fonts'),
                'dependency_key' => 'body-family',
            ],
            'extended_variable_role_alias_code_enabled' => [
                'label' => __('Code Alias Variable CSS', 'tasty-fonts'),
                'description' => __('Custom property declaration that maps the code alias to the monospace role.', 'tasty-fonts'),
                'empty_message' => __('Enable and assign the monospace role to preview the code alias variable.', 'tasty-fonts'),
                'dependency_key' => 'monospace-family',
            ],
            'extended_variable_category_sans_enabled' => [
                'label' => __('Sans Category Variable CSS', 'tasty-fonts'),
                'description' => __('Custom property declaration for the first published sans-family category alias.', 'tasty-fonts'),
                'empty_message' => __('No published sans family is available to preview this variable.', 'tasty-fonts'),
                'dependency_key' => 'category-sans',
            ],
            'extended_variable_category_serif_enabled' => [
                'label' => __('Serif Category Variable CSS', 'tasty-fonts'),
                'description' => __('Custom property declaration for the first published serif-family category alias.', 'tasty-fonts'),
                'empty_message' => __('No published serif family is available to preview this variable.', 'tasty-fonts'),
                'dependency_key' => 'category-serif',
            ],
            'extended_variable_category_mono_enabled' => [
                'label' => __('Mono Category Variable CSS', 'tasty-fonts'),
                'description' => __('Custom property declaration for the first published monospace-family category alias.', 'tasty-fonts'),
                'empty_message' => __('Enable and assign the monospace role with a published monospace family to preview this variable.', 'tasty-fonts'),
                'dependency_key' => 'category-mono',
            ],
        ];
    }

    /**
     * @param CatalogMap $catalog
     * @return CatalogMap
     */
    private function filterRuntimeVisibleFamilies(array $catalog): array
    {
        $families = [];

        foreach ($catalog as $key => $family) {
            if (FontUtils::scalarStringValue($family['publish_state'] ?? 'published') === 'library_only') {
                continue;
            }

            $families[$key] = $family;
        }

        return $families;
    }
}
