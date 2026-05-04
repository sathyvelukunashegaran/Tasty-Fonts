<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\FallbackResolver;
use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type RoleSet from \TastyFonts\Repository\SettingsRepository
 * @phpstan-type PreviewView array<string, mixed>
 * @phpstan-type FamilyLabelMap array<string, string>
 * @phpstan-type GlobalFallbackSettings array<string, string>
 * @phpstan-type FamilyOption array{value: string, label: string, type?: string}
 * @phpstan-type FamilyOptionList list<FamilyOption>
 */
final class PreviewSectionRenderer extends AbstractSectionRenderer
{
    private const SCENE_TEMPLATES = [
        'editorial'  => 'preview-editorial.php',
        'card'       => 'preview-card.php',
        'reading'    => 'preview-reading.php',
        'marketing'  => 'preview-marketing.php',
        'code'       => 'preview-code.php',
        'snippet'    => 'preview-snippet.php',
        'interface'  => 'preview-interface.php',
    ];

    /**
     * @param PreviewView $view
     */
    public function render(array $view): void
    {
        $this->renderTemplate('preview-section.php', $view);
    }

    /**
     * @param RoleSet $roles
     * @param FamilyLabelMap $familyLabels
     * @param GlobalFallbackSettings $globalFallbackSettings
     */
    public function renderPreviewScene(
        string $key,
        string $previewText,
        array $roles,
        bool $monospaceRoleEnabled = false,
        array $familyLabels = [],
        array $globalFallbackSettings = []
    ): void
    {
        $template = self::SCENE_TEMPLATES[$key] ?? 'preview-editorial.php';
        $this->renderTemplate($template, [
            'key' => $key,
            'previewText' => $previewText,
            'roles' => $roles,
            'monospaceRoleEnabled' => $monospaceRoleEnabled,
            'familyLabels' => $familyLabels,
            'globalFallbackSettings' => $globalFallbackSettings,
        ]);
    }

    /**
     * @param RoleSet $roles
     * @param FamilyLabelMap $familyLabels
     * @param GlobalFallbackSettings $globalFallbackSettings
     */
    public function previewRoleName(string $roleKey, array $roles, array $familyLabels = [], array $globalFallbackSettings = []): string
    {
        $familyName = trim($this->roleStringValue($roles, $roleKey));

        if ($familyName !== '') {
            return (string) ($familyLabels[$familyName] ?? $familyName);
        }

        return sprintf(
            __('Fallback only (%s)', 'tasty-fonts'),
            FontUtils::sanitizeFallback($this->roleFallbackValue($roles, $roleKey, $globalFallbackSettings))
        );
    }

    /**
     * @param RoleSet $roles
     * @param FamilyLabelMap $familyLabels
     * @param GlobalFallbackSettings $globalFallbackSettings
     */
    protected function renderPreviewRoleList(
        array $roles,
        array $familyLabels,
        bool $includeMonospace,
        string $listClass,
        string $itemClass,
        bool $longLabels = false,
        array $globalFallbackSettings = []
    ): void {
        $items = [
            'heading' => $longLabels ? __('Heading Family', 'tasty-fonts') : __('Heading', 'tasty-fonts'),
            'body' => $longLabels ? __('Body Family', 'tasty-fonts') : __('Body', 'tasty-fonts'),
        ];

        if ($includeMonospace) {
            $items['monospace'] = __('Monospace', 'tasty-fonts');
        }
        ?>
        <dl class="<?php echo esc_attr($listClass); ?>">
            <?php foreach ($items as $roleKey => $label): ?>
                <div class="<?php echo esc_attr($itemClass); ?>">
                    <dt><?php echo esc_html($label); ?></dt>
                    <dd data-role-preview="<?php echo esc_attr($roleKey); ?>" data-role-preview-name="<?php echo esc_attr($roleKey); ?>"><?php echo esc_html($this->previewRoleName($roleKey, $roles, $familyLabels, $globalFallbackSettings)); ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <?php
    }

    /**
     * @param FamilyOptionList $availableFamilyOptions
     * @param RoleSet $previewRoles
     * @param RoleSet $draftRoles
     * @param GlobalFallbackSettings $globalFallbackSettings
     */
    public function renderPreviewRolePicker(
        string $roleKey,
        string $label,
        array $availableFamilyOptions,
        array $previewRoles,
        array $draftRoles,
        bool $allowFallbackOnly = false,
        array $globalFallbackSettings = []
    ): void {
        $selectedFamily = trim($this->roleStringValue($previewRoles, $roleKey));
        $draftFamily = trim($this->roleStringValue($draftRoles, $roleKey));
        $fallbackValue = $this->roleFallbackValue($previewRoles, $roleKey, $globalFallbackSettings);
        $selectId = 'tasty-fonts-preview-' . sanitize_html_class($roleKey) . '-family';
        ?>
        <div class="tasty-fonts-preview-role-picker" data-preview-role-picker="<?php echo esc_attr($roleKey); ?>">
            <div class="tasty-fonts-stack-field tasty-fonts-preview-tray-field">
                <span class="tasty-fonts-field-label-row">
                    <label class="tasty-fonts-field-label-text" for="<?php echo esc_attr($selectId); ?>"><?php echo esc_html($label); ?></label>
                    <button
                        type="button"
                        class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger"
                        aria-label="<?php esc_attr_e('Explain role family delivery', 'tasty-fonts'); ?>"
                        <?php $this->renderPassiveHelpAttributes(__('Choose the delivery method in the Font Library. Role selectors use the family’s active delivery profile.', 'tasty-fonts')); ?>
                    >?</button>
                </span>
                <span class="tasty-fonts-select-field<?php echo $allowFallbackOnly ? ' tasty-fonts-select-field--clearable' : ''; ?>">
                    <select
                        id="<?php echo esc_attr($selectId); ?>"
                        data-preview-role-select="<?php echo esc_attr($roleKey); ?>"
                        data-preview-draft-family="<?php echo esc_attr($draftFamily); ?>"
                        data-preview-fallback="<?php echo esc_attr($fallbackValue); ?>"
                    >
                        <?php if ($allowFallbackOnly): ?>
                            <option value="" <?php selected($selectedFamily, ''); ?>><?php esc_html_e('Use Fallback Only', 'tasty-fonts'); ?></option>
                        <?php endif; ?>
                        <?php foreach ($availableFamilyOptions as $option): ?>
                            <?php $familyName = $option['value']; ?>
                            <?php $familyLabel = $option['label']; ?>
                            <option value="<?php echo esc_attr($familyName); ?>" <?php selected($selectedFamily, $familyName); ?>><?php echo esc_html($familyLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($allowFallbackOnly): ?>
                        <?php $this->renderClearSelectButton(sprintf(__('Clear %s', 'tasty-fonts'), $label)); ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="tasty-fonts-role-weight-editor tasty-fonts-preview-role-editor" data-preview-weight-editor="<?php echo esc_attr($roleKey); ?>" hidden>
                <label class="tasty-fonts-stack-field tasty-fonts-preview-tray-field tasty-fonts-role-weight-field">
                    <?php $this->renderFieldLabel(__('Role Weight', 'tasty-fonts')); ?>
                    <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                        <select data-preview-weight-select="<?php echo esc_attr($roleKey); ?>"></select>
                        <?php $this->renderClearSelectButton(sprintf(__('Clear %s weight', 'tasty-fonts'), $label), '', '', true); ?>
                    </span>
                </label>
            </div>
            <div class="tasty-fonts-role-axis-editor tasty-fonts-preview-role-editor" data-preview-axis-editor="<?php echo esc_attr($roleKey); ?>" hidden>
                <div class="tasty-fonts-stack-field tasty-fonts-preview-tray-field">
                    <span class="tasty-fonts-field-label" data-preview-axis-heading="<?php echo esc_attr($roleKey); ?>"><?php esc_html_e('Variable Axes', 'tasty-fonts'); ?></span>
                    <div class="tasty-fonts-role-axis-fields" data-preview-axis-fields="<?php echo esc_attr($roleKey); ?>"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param RoleSet $roles
     * @param FamilyLabelMap $familyLabels
     * @param GlobalFallbackSettings $globalFallbackSettings
     */
    public function renderSnippetPreviewScene(array $roles, bool $monospaceRoleEnabled, array $familyLabels = [], array $globalFallbackSettings = []): void
    {
        $this->renderTemplate('preview-snippet.php', [
            'roles' => $roles,
            'monospaceRoleEnabled' => $monospaceRoleEnabled,
            'familyLabels' => $familyLabels,
            'globalFallbackSettings' => $globalFallbackSettings,
        ]);
    }

    /**
     * @param RoleSet $roles
     * @param FamilyLabelMap $familyLabels
     * @param GlobalFallbackSettings $globalFallbackSettings
     */
    public function renderCodePreviewScene(string $previewText, array $roles, bool $monospaceRoleEnabled, array $familyLabels = [], array $globalFallbackSettings = []): void
    {
        $this->renderTemplate('preview-code.php', [
            'previewText' => $previewText,
            'roles' => $roles,
            'monospaceRoleEnabled' => $monospaceRoleEnabled,
            'familyLabels' => $familyLabels,
            'globalFallbackSettings' => $globalFallbackSettings,
        ]);
    }

    /**
     * @param array<int|string, mixed> $roles
     */
    private function roleStringValue(array $roles, string $key): string
    {
        $value = $roles[$key] ?? '';

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @param array<int|string, mixed> $roles
     * @param GlobalFallbackSettings $globalFallbackSettings
     */
    private function roleFallbackValue(array $roles, string $roleKey, array $globalFallbackSettings = []): string
    {
        return FallbackResolver::roleFallback($roleKey, $roles, $globalFallbackSettings);
    }

    /**
     * @param RoleSet $roles
     * @param GlobalFallbackSettings $globalFallbackSettings
     */
    protected function buildPreviewSnippetCss(array $roles, bool $includeMonospace, array $globalFallbackSettings = []): string
    {
        $lines = [':root {'];

        $this->appendRolePreviewSnippetVariables($lines, $roles, 'heading', $globalFallbackSettings);
        $this->appendRolePreviewSnippetVariables($lines, $roles, 'body', $globalFallbackSettings);

        if ($includeMonospace) {
            $this->appendRolePreviewSnippetVariables($lines, $roles, 'monospace', $globalFallbackSettings);
        }

        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'body {';
        $lines[] = '  font-family: var(--font-body);';
        $lines[] = '  font-variation-settings: var(--font-body-settings);';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'h1, h2, h3, h4, h5, h6 {';
        $lines[] = '  font-family: var(--font-heading);';
        $lines[] = '  font-variation-settings: var(--font-heading-settings);';
        $lines[] = '}';

        if ($includeMonospace) {
            $lines[] = '';
            $lines[] = 'code, pre, kbd, samp {';
            $lines[] = '  font-family: var(--font-monospace);';
            $lines[] = '  font-variation-settings: var(--font-monospace-settings);';
            $lines[] = '}';
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $lines
     * @param RoleSet $roles
     * @param GlobalFallbackSettings $globalFallbackSettings
     */
    private function appendRolePreviewSnippetVariables(array &$lines, array $roles, string $roleKey, array $globalFallbackSettings = []): void
    {
        $family = trim($this->roleStringValue($roles, $roleKey));
        $fallback = $this->roleFallbackValue($roles, $roleKey, $globalFallbackSettings);
        $stack = FontUtils::buildFontStack($family, $fallback);

        if ($family !== '') {
            $slug = FontUtils::slugify($family);
            $lines[] = sprintf('  --font-%s: %s;', $slug, $stack);
            $lines[] = sprintf('  --font-%s: var(--font-%s);', $roleKey, $slug);
        } else {
            $lines[] = sprintf('  --font-%s: %s;', $roleKey, $stack);
        }

        $lines[] = sprintf('  --font-%s-settings: normal;', $roleKey);
    }
}
