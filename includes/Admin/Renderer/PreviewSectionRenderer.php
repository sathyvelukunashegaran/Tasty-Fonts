<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

final class PreviewSectionRenderer extends AbstractSectionRenderer
{
    public function render(array $view): void
    {
        $this->renderTemplate('preview-section.php', $view);
    }

    public function renderPreviewScene(string $key, string $previewText, array $roles, bool $monospaceRoleEnabled = false, array $familyLabels = []): void
    {
        switch ($key) {
            case 'editorial':
                ?>
                <div class="tasty-fonts-preview-showcase">
                    <div class="tasty-fonts-preview-specimen-board">
                        <aside class="tasty-fonts-preview-specimen-rail">
                            <div class="tasty-fonts-preview-specimen-glyph" data-role-preview="heading">Aa</div>
                            <div class="tasty-fonts-preview-specimen-key">
                                <span class="tasty-fonts-preview-specimen-key-label"><?php esc_html_e('Heading Family', 'tasty-fonts'); ?></span>
                                <strong class="tasty-fonts-preview-specimen-key-value" data-role-preview="heading" data-role-preview-name="heading"><?php echo esc_html($this->previewRoleName('heading', $roles, $familyLabels)); ?></strong>
                            </div>
                            <div class="tasty-fonts-preview-specimen-key">
                                <span class="tasty-fonts-preview-specimen-key-label"><?php esc_html_e('Body Family', 'tasty-fonts'); ?></span>
                                <strong class="tasty-fonts-preview-specimen-key-value" data-role-preview="body" data-role-preview-name="body"><?php echo esc_html($this->previewRoleName('body', $roles, $familyLabels)); ?></strong>
                            </div>
                            <?php if ($monospaceRoleEnabled): ?>
                                <div class="tasty-fonts-preview-specimen-key">
                                    <span class="tasty-fonts-preview-specimen-key-label"><?php esc_html_e('Monospace', 'tasty-fonts'); ?></span>
                                    <strong class="tasty-fonts-preview-specimen-key-value" data-role-preview="monospace" data-role-preview-name="monospace"><?php echo esc_html($this->previewRoleName('monospace', $roles, $familyLabels)); ?></strong>
                                </div>
                            <?php endif; ?>
                        </aside>

                        <div class="tasty-fonts-preview-specimen-scale">
                            <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--1" data-role-preview="heading"><?php esc_html_e('Heading 1', 'tasty-fonts'); ?></div>
                            <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--2" data-role-preview="heading"><?php esc_html_e('Heading 2', 'tasty-fonts'); ?></div>
                            <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--3" data-role-preview="heading"><?php esc_html_e('Heading 3', 'tasty-fonts'); ?></div>
                            <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--4" data-role-preview="heading"><?php esc_html_e('Heading 4', 'tasty-fonts'); ?></div>
                            <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--5" data-role-preview="heading"><?php esc_html_e('Heading 5', 'tasty-fonts'); ?></div>
                            <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--6" data-role-preview="heading"><?php esc_html_e('Heading 6', 'tasty-fonts'); ?></div>
                        </div>

                        <div class="tasty-fonts-preview-specimen-copy">
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Lead', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <p class="tasty-fonts-preview-specimen-lead" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                                </div>
                            </div>
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Body / 16', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <p class="tasty-fonts-preview-specimen-body-large" data-role-preview="body"><?php esc_html_e('Apparently we had reached a great height in the atmosphere, for the sky was a dead black, and the stars had ceased to twinkle.', 'tasty-fonts'); ?></p>
                                </div>
                            </div>
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Body / 14', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <p class="tasty-fonts-preview-specimen-body" data-role-preview="body"><?php esc_html_e('Apparently we had reached a great height in the atmosphere, for the sky was a dead black, and the stars had ceased to twinkle.', 'tasty-fonts'); ?></p>
                                </div>
                            </div>
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Quote', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <blockquote class="tasty-fonts-preview-specimen-quote" data-role-preview="heading"><?php esc_html_e('“The sky was cloudless and of a deep dark blue.”', 'tasty-fonts'); ?></blockquote>
                                </div>
                            </div>
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Capitalized', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <p class="tasty-fonts-preview-specimen-caps" data-role-preview="body"><?php esc_html_e('Brainstorm alternative ideas', 'tasty-fonts'); ?></p>
                                </div>
                            </div>
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Small', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <p class="tasty-fonts-preview-specimen-small" data-role-preview="body"><?php esc_html_e('Value your time', 'tasty-fonts'); ?></p>
                                </div>
                            </div>
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Tiny', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <p class="tasty-fonts-preview-specimen-tiny" data-role-preview="body"><?php esc_html_e('Nothing is impossible', 'tasty-fonts'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tasty-fonts-preview-support-grid">
                        <article class="tasty-fonts-preview-support-card">
                            <span class="tasty-fonts-preview-support-label" data-role-preview="body"><?php esc_html_e('Hero Lockup', 'tasty-fonts'); ?></span>
                            <h3 class="tasty-fonts-preview-support-title" data-role-preview="heading"><?php esc_html_e('A Type Pairing That Feels Intentional at Every Scale', 'tasty-fonts'); ?></h3>
                            <p class="tasty-fonts-preview-support-copy" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                            <div class="tasty-fonts-preview-support-meta">
                                <span><?php esc_html_e('Landing Page', 'tasty-fonts'); ?></span>
                                <strong data-role-preview="heading"><?php esc_html_e('Ready', 'tasty-fonts'); ?></strong>
                            </div>
                        </article>

                        <article class="tasty-fonts-preview-support-card">
                            <span class="tasty-fonts-preview-support-label" data-role-preview="body"><?php esc_html_e('Feature Module', 'tasty-fonts'); ?></span>
                            <h3 class="tasty-fonts-preview-support-title" data-role-preview="heading"><?php esc_html_e('Clean Cards With Enough Contrast for Product Copy', 'tasty-fonts'); ?></h3>
                            <p class="tasty-fonts-preview-support-copy" data-role-preview="body"><?php esc_html_e('Use this sample to judge title tone, supporting copy rhythm, and whether the body face stays calm inside UI surfaces.', 'tasty-fonts'); ?></p>
                            <div class="tasty-fonts-preview-support-meta">
                                <span><?php esc_html_e('Surface Check', 'tasty-fonts'); ?></span>
                                <strong data-role-preview="heading"><?php esc_html_e('Balanced', 'tasty-fonts'); ?></strong>
                            </div>
                        </article>

                        <article class="tasty-fonts-preview-support-card">
                            <span class="tasty-fonts-preview-support-label" data-role-preview="body"><?php esc_html_e('Metrics Panel', 'tasty-fonts'); ?></span>
                            <div class="tasty-fonts-preview-support-stats">
                                <div class="tasty-fonts-preview-support-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Visitors', 'tasty-fonts'); ?></span>
                                    <strong data-role-preview="heading">12.4k</strong>
                                </div>
                                <div class="tasty-fonts-preview-support-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Conversion', 'tasty-fonts'); ?></span>
                                    <strong data-role-preview="heading">4.8%</strong>
                                </div>
                                <div class="tasty-fonts-preview-support-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Launch', 'tasty-fonts'); ?></span>
                                    <strong data-role-preview="heading"><?php esc_html_e('Soon', 'tasty-fonts'); ?></strong>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
                <?php
                return;

            case 'card':
                ?>
                <div class="tasty-fonts-preview-card-board">
                    <div class="tasty-fonts-preview-card-gallery">
                        <article class="tasty-fonts-preview-card-frame">
                            <div class="tasty-fonts-preview-card-media">
                                <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                            </div>
                            <div class="tasty-fonts-preview-card-body">
                                <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Feature Card', 'tasty-fonts'); ?></span>
                                <h3 class="tasty-fonts-preview-card-title" data-role-preview="heading"><?php esc_html_e('Title', 'tasty-fonts'); ?></h3>
                                <p class="tasty-fonts-preview-card-subtitle" data-role-preview="body"><?php esc_html_e('Subtitle', 'tasty-fonts'); ?></p>
                                <p class="tasty-fonts-preview-card-copy" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                                <div class="tasty-fonts-preview-card-actions">
                                    <span class="button" aria-hidden="true"><?php esc_html_e('Action', 'tasty-fonts'); ?></span>
                                    <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Action', 'tasty-fonts'); ?></span>
                                </div>
                            </div>
                        </article>

                        <article class="tasty-fonts-preview-card-frame">
                            <div class="tasty-fonts-preview-card-media">
                                <span class="dashicons dashicons-format-gallery" aria-hidden="true"></span>
                            </div>
                            <div class="tasty-fonts-preview-card-body">
                                <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Collection', 'tasty-fonts'); ?></span>
                                <h3 class="tasty-fonts-preview-card-title" data-role-preview="heading"><?php esc_html_e('Modern Layouts', 'tasty-fonts'); ?></h3>
                                <p class="tasty-fonts-preview-card-subtitle" data-role-preview="body"><?php esc_html_e('Structured and Calm', 'tasty-fonts'); ?></p>
                                <p class="tasty-fonts-preview-card-copy" data-role-preview="body"><?php esc_html_e('Compare how the chosen heading face holds attention while the body face keeps supporting detail easy to scan.', 'tasty-fonts'); ?></p>
                                <div class="tasty-fonts-preview-card-actions">
                                    <span class="button" aria-hidden="true"><?php esc_html_e('Review', 'tasty-fonts'); ?></span>
                                    <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Select', 'tasty-fonts'); ?></span>
                                </div>
                            </div>
                        </article>

                        <article class="tasty-fonts-preview-card-frame">
                            <div class="tasty-fonts-preview-card-media">
                                <span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
                            </div>
                            <div class="tasty-fonts-preview-card-body">
                                <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Product Card', 'tasty-fonts'); ?></span>
                                <h3 class="tasty-fonts-preview-card-title" data-role-preview="heading"><?php esc_html_e('System-Ready', 'tasty-fonts'); ?></h3>
                                <p class="tasty-fonts-preview-card-subtitle" data-role-preview="body"><?php esc_html_e('Useful in Real UI', 'tasty-fonts'); ?></p>
                                <p class="tasty-fonts-preview-card-copy" data-role-preview="body"><?php esc_html_e('This view is intentionally compact so you can judge hierarchy, spacing, and button copy without oversized demo content.', 'tasty-fonts'); ?></p>
                                <div class="tasty-fonts-preview-card-actions">
                                    <span class="button" aria-hidden="true"><?php esc_html_e('Later', 'tasty-fonts'); ?></span>
                                    <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Launch', 'tasty-fonts'); ?></span>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
                <?php
                return;

            case 'reading':
                ?>
                <article class="tasty-fonts-preview-reading-sheet">
                    <div class="tasty-fonts-preview-reading-head">
                        <span class="tasty-fonts-preview-reading-label" data-role-preview="body"><?php esc_html_e('Long-Form Reading', 'tasty-fonts'); ?></span>
                        <h3 class="tasty-fonts-preview-reading-title" data-role-preview="heading"><?php esc_html_e('Readable Paragraphs With Steady Rhythm', 'tasty-fonts'); ?></h3>
                    </div>
                    <p class="tasty-fonts-preview-reading-lead" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                    <div class="tasty-fonts-preview-reading-layout">
                        <div class="tasty-fonts-preview-reading-copy">
                            <p data-role-preview="body"><?php esc_html_e('Apparently we had reached a great height in the atmosphere, for the sky was a dead black, and the stars had ceased to twinkle.', 'tasty-fonts'); ?></p>
                            <p data-role-preview="body"><?php esc_html_e('A strong reading font should stay calm across longer passages and still leave enough contrast for section headings and pull quotes.', 'tasty-fonts'); ?></p>
                        </div>
                        <aside class="tasty-fonts-preview-reading-aside">
                            <h4 class="tasty-fonts-preview-reading-aside-title" data-role-preview="heading"><?php esc_html_e('Checklist', 'tasty-fonts'); ?></h4>
                            <ul class="tasty-fonts-preview-reading-list" data-role-preview="body">
                                <li><?php esc_html_e('Paragraph spacing', 'tasty-fonts'); ?></li>
                                <li><?php esc_html_e('Line length at body sizes', 'tasty-fonts'); ?></li>
                                <li><?php esc_html_e('Subheading emphasis', 'tasty-fonts'); ?></li>
                            </ul>
                        </aside>
                    </div>
                </article>
                <?php
                return;

            case 'code':
                $this->renderCodePreviewScene($previewText, $roles, $monospaceRoleEnabled, $familyLabels);
                return;

            case 'interface':
            default:
                ?>
                <div class="tasty-fonts-preview-ui-shell">
                    <div class="tasty-fonts-preview-ui-topbar">
                        <span class="tasty-fonts-preview-ui-topbar-label" data-role-preview="body"><?php esc_html_e('Workspace', 'tasty-fonts'); ?></span>
                        <span class="tasty-fonts-preview-ui-topbar-status"><?php esc_html_e('Live', 'tasty-fonts'); ?></span>
                    </div>
                    <div class="tasty-fonts-preview-ui-grid">
                        <div class="tasty-fonts-preview-ui-panel">
                            <span class="tasty-fonts-preview-ui-label" data-role-preview="body"><?php esc_html_e('Project Name', 'tasty-fonts'); ?></span>
                            <h3 class="tasty-fonts-preview-ui-title" data-role-preview="heading"><?php esc_html_e('Launch Planning', 'tasty-fonts'); ?></h3>
                            <p class="tasty-fonts-preview-ui-copy" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                        </div>
                        <div class="tasty-fonts-preview-ui-panel">
                            <span class="tasty-fonts-preview-ui-label" data-role-preview="body"><?php esc_html_e('Metrics', 'tasty-fonts'); ?></span>
                            <div class="tasty-fonts-preview-ui-stats">
                                <div class="tasty-fonts-preview-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Visitors', 'tasty-fonts'); ?></span>
                                    <strong data-role-preview="heading">12.4k</strong>
                                </div>
                                <div class="tasty-fonts-preview-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Signups', 'tasty-fonts'); ?></span>
                                    <strong data-role-preview="heading">318</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tasty-fonts-preview-ui-list">
                        <div class="tasty-fonts-preview-ui-list-row">
                            <span data-role-preview="body"><?php esc_html_e('Headline Lockup', 'tasty-fonts'); ?></span>
                            <strong data-role-preview="heading"><?php esc_html_e('Approved', 'tasty-fonts'); ?></strong>
                        </div>
                        <div class="tasty-fonts-preview-ui-list-row">
                            <span data-role-preview="body"><?php esc_html_e('Landing Page Copy', 'tasty-fonts'); ?></span>
                            <strong data-role-preview="heading"><?php esc_html_e('In Review', 'tasty-fonts'); ?></strong>
                        </div>
                    </div>
                    <div class="tasty-fonts-preview-ui-actions">
                        <span class="button" aria-hidden="true"><?php esc_html_e('Save Draft', 'tasty-fonts'); ?></span>
                        <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Publish', 'tasty-fonts'); ?></span>
                    </div>
                </div>
                <?php
                return;
        }
    }

    public function previewRoleName(string $roleKey, array $roles, array $familyLabels = []): string
    {
        $familyName = trim((string) ($roles[$roleKey] ?? ''));

        if ($familyName !== '') {
            return (string) ($familyLabels[$familyName] ?? $familyName);
        }

        return sprintf(
            __('Fallback only (%s)', 'tasty-fonts'),
            FontUtils::sanitizeFallback(
                (string) match ($roleKey) {
                    'heading' => $roles['heading_fallback'] ?? 'sans-serif',
                    'body' => $roles['body_fallback'] ?? 'sans-serif',
                    default => $roles['monospace_fallback'] ?? 'monospace',
                }
            )
        );
    }

    public function renderPreviewRolePicker(
        string $roleKey,
        string $label,
        array $availableFamilyOptions,
        array $previewRoles,
        array $draftRoles,
        bool $allowFallbackOnly = false
    ): void {
        $selectedFamily = trim((string) ($previewRoles[$roleKey] ?? ''));
        $draftFamily = trim((string) ($draftRoles[$roleKey] ?? ''));
        $selectedDeliveryId = trim((string) ($previewRoles[$roleKey . '_delivery_id'] ?? ''));
        $fallbackValue = match ($roleKey) {
            'heading' => (string) ($previewRoles['heading_fallback'] ?? 'sans-serif'),
            'body' => (string) ($previewRoles['body_fallback'] ?? 'sans-serif'),
            default => (string) ($previewRoles['monospace_fallback'] ?? 'monospace'),
        };
        ?>
        <div class="tasty-fonts-preview-role-picker" data-preview-role-picker="<?php echo esc_attr($roleKey); ?>">
            <label class="tasty-fonts-stack-field tasty-fonts-preview-tray-field">
                <?php $this->renderFieldLabel($label); ?>
                <span class="tasty-fonts-select-field<?php echo $allowFallbackOnly ? ' tasty-fonts-select-field--clearable' : ''; ?>">
                    <select
                        data-preview-role-select="<?php echo esc_attr($roleKey); ?>"
                        data-preview-draft-family="<?php echo esc_attr($draftFamily); ?>"
                        data-preview-fallback="<?php echo esc_attr($fallbackValue); ?>"
                    >
                        <?php if ($allowFallbackOnly): ?>
                            <option value="" <?php selected($selectedFamily, ''); ?>><?php esc_html_e('Use Fallback Only', 'tasty-fonts'); ?></option>
                        <?php endif; ?>
                        <?php foreach ($availableFamilyOptions as $option): ?>
                            <?php if (!is_array($option)) { continue; } ?>
                            <?php $familyName = (string) ($option['value'] ?? ''); ?>
                            <?php $familyLabel = (string) ($option['label'] ?? $familyName); ?>
                            <option value="<?php echo esc_attr($familyName); ?>" <?php selected($selectedFamily, $familyName); ?>><?php echo esc_html($familyLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($allowFallbackOnly): ?>
                        <button
                            type="button"
                            class="tasty-fonts-select-clear"
                            data-clear-select-button
                            data-clear-value=""
                            aria-label="<?php echo esc_attr(sprintf(__('Clear %s', 'tasty-fonts'), $label)); ?>"
                            hidden
                        >
                            <span aria-hidden="true">&times;</span>
                        </button>
                    <?php endif; ?>
                </span>
            </label>
            <div class="tasty-fonts-role-weight-editor tasty-fonts-preview-role-editor" data-preview-delivery-editor="<?php echo esc_attr($roleKey); ?>" hidden>
                <label class="tasty-fonts-stack-field tasty-fonts-preview-tray-field tasty-fonts-role-weight-field">
                    <?php $this->renderFieldLabel(__('Delivery', 'tasty-fonts')); ?>
                    <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                        <select
                            data-preview-delivery-select="<?php echo esc_attr($roleKey); ?>"
                            data-preview-selected-delivery="<?php echo esc_attr($selectedDeliveryId); ?>"
                        ></select>
                        <button
                            type="button"
                            class="tasty-fonts-select-clear"
                            data-clear-select-button
                            data-clear-value=""
                            data-clear-affordance="always"
                            aria-label="<?php echo esc_attr(sprintf(__('Clear %s delivery', 'tasty-fonts'), $label)); ?>"
                            hidden
                        >
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </span>
                </label>
            </div>
            <div class="tasty-fonts-role-weight-editor tasty-fonts-preview-role-editor" data-preview-weight-editor="<?php echo esc_attr($roleKey); ?>" hidden>
                <label class="tasty-fonts-stack-field tasty-fonts-preview-tray-field tasty-fonts-role-weight-field">
                    <?php $this->renderFieldLabel(__('Role Weight', 'tasty-fonts')); ?>
                    <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                        <select data-preview-weight-select="<?php echo esc_attr($roleKey); ?>"></select>
                        <button
                            type="button"
                            class="tasty-fonts-select-clear"
                            data-clear-select-button
                            data-clear-value=""
                            data-clear-affordance="always"
                            aria-label="<?php echo esc_attr(sprintf(__('Clear %s weight', 'tasty-fonts'), $label)); ?>"
                            hidden
                        >
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </span>
                </label>
            </div>
            <div class="tasty-fonts-role-axis-editor tasty-fonts-preview-role-editor" data-preview-axis-editor="<?php echo esc_attr($roleKey); ?>" hidden>
                <div class="tasty-fonts-stack-field tasty-fonts-preview-tray-field">
                    <?php $this->renderFieldLabel(__('Variable Axes', 'tasty-fonts')); ?>
                    <div class="tasty-fonts-role-axis-fields" data-preview-axis-fields="<?php echo esc_attr($roleKey); ?>"></div>
                </div>
            </div>
        </div>
        <?php
    }

    public function renderCodePreviewScene(string $previewText, array $roles, bool $monospaceRoleEnabled, array $familyLabels = []): void
    {
        $editorPreviewHeadingId = 'tasty-fonts-preview-code-editor-heading';
        $blockPreviewHeadingId = 'tasty-fonts-preview-code-block-heading';
        ?>
        <div class="tasty-fonts-preview-code-workspace">
            <aside class="tasty-fonts-preview-code-overview">
                <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Code Preview', 'tasty-fonts'); ?></span>
                <h3 class="tasty-fonts-preview-code-title" data-role-preview="heading"><?php esc_html_e('Inspect How Your Code Reads in an Editor and Published Block', 'tasty-fonts'); ?></h3>
                <p class="tasty-fonts-preview-code-copy" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                <div class="tasty-fonts-preview-code-meta">
                    <div class="tasty-fonts-preview-code-meta-item">
                        <span class="tasty-fonts-preview-code-meta-label"><?php esc_html_e('Code Face', 'tasty-fonts'); ?></span>
                        <strong class="tasty-fonts-preview-code-meta-value" data-role-preview="monospace" data-role-preview-name="monospace"><?php echo esc_html($this->previewRoleName('monospace', $roles, $familyLabels ?? [])); ?></strong>
                    </div>
                    <div class="tasty-fonts-preview-code-meta-item">
                        <span class="tasty-fonts-preview-code-meta-label"><?php esc_html_e('Headings', 'tasty-fonts'); ?></span>
                        <strong class="tasty-fonts-preview-code-meta-value" data-role-preview="heading" data-role-preview-name="heading"><?php echo esc_html($this->previewRoleName('heading', $roles, $familyLabels ?? [])); ?></strong>
                    </div>
                    <div class="tasty-fonts-preview-code-meta-item">
                        <span class="tasty-fonts-preview-code-meta-label"><?php esc_html_e('Annotations', 'tasty-fonts'); ?></span>
                        <strong class="tasty-fonts-preview-code-meta-value" data-role-preview="body" data-role-preview-name="body"><?php echo esc_html($this->previewRoleName('body', $roles, $familyLabels ?? [])); ?></strong>
                    </div>
                </div>
                <div class="tasty-fonts-preview-code-inline">
                    <span class="tasty-fonts-preview-code-inline-label" data-role-preview="body"><?php esc_html_e('Inline token', 'tasty-fonts'); ?></span>
                    <code class="tasty-fonts-preview-code-inline-sample" data-role-preview="monospace">var(--font-monospace)</code>
                </div>
                <div class="tasty-fonts-preview-code-chip-row">
                    <span class="tasty-fonts-preview-code-chip"><?php echo esc_html($monospaceRoleEnabled ? __('Monospace Role Enabled', 'tasty-fonts') : __('Fallback Stack Preview', 'tasty-fonts')); ?></span>
                    <span class="tasty-fonts-preview-code-chip"><?php esc_html_e('Syntax Highlighting', 'tasty-fonts'); ?></span>
                </div>
            </aside>

            <div class="tasty-fonts-preview-code-surfaces">
                <section class="tasty-fonts-preview-code-window">
                    <div class="tasty-fonts-preview-code-window-topbar">
                        <div class="tasty-fonts-preview-code-window-dots" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <div class="tasty-fonts-preview-code-window-tab">
                            <span class="dashicons dashicons-media-code" aria-hidden="true"></span>
                            <span id="<?php echo esc_attr($editorPreviewHeadingId); ?>" data-role-preview="body">typography-preview.tsx</span>
                        </div>
                        <div class="tasty-fonts-preview-code-window-tools">
                            <span class="tasty-fonts-preview-code-badge">TSX</span>
                            <span class="tasty-fonts-preview-code-badge"><?php echo esc_html($monospaceRoleEnabled ? __('Role Live', 'tasty-fonts') : __('Fallback', 'tasty-fonts')); ?></span>
                        </div>
                    </div>

                    <div class="tasty-fonts-preview-code-panel tasty-fonts-preview-code-panel--editor" data-role-preview="monospace">
                        <div class="tasty-fonts-preview-code-lines" aria-label="<?php esc_attr_e('Editor preview', 'tasty-fonts'); ?>" aria-labelledby="<?php echo esc_attr($editorPreviewHeadingId); ?>">
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">01</span>
                                <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-comment">// Typography tokens wired into the UI preview</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">02</span>
                                <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-keyword">const</span> <span class="tasty-fonts-preview-token-variable">fontRoles</span> <span class="tasty-fonts-preview-token-operator">=</span> <span class="tasty-fonts-preview-token-punctuation">{</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">03</span>
                                <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">heading</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-string">&quot;var(--font-heading)&quot;</span><span class="tasty-fonts-preview-token-punctuation">,</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">04</span>
                                <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">body</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-string">&quot;var(--font-body)&quot;</span><span class="tasty-fonts-preview-token-punctuation">,</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">05</span>
                                <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">code</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-string">&quot;var(--font-monospace)&quot;</span><span class="tasty-fonts-preview-token-punctuation">,</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">06</span>
                                <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-punctuation">};</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">07</span>
                                <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-keyword">export</span> <span class="tasty-fonts-preview-token-keyword">function</span> <span class="tasty-fonts-preview-token-function">PreviewChip</span><span class="tasty-fonts-preview-token-punctuation">()</span> <span class="tasty-fonts-preview-token-punctuation">{</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">08</span>
                                <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-keyword">return</span> <span class="tasty-fonts-preview-token-tag">&lt;code</span> <span class="tasty-fonts-preview-token-attr">style</span><span class="tasty-fonts-preview-token-operator">=</span><span class="tasty-fonts-preview-token-punctuation">{</span><span class="tasty-fonts-preview-token-punctuation">{</span> <span class="tasty-fonts-preview-token-property">fontFamily</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-variable">fontRoles</span><span class="tasty-fonts-preview-token-punctuation">.</span><span class="tasty-fonts-preview-token-property">code</span> <span class="tasty-fonts-preview-token-punctuation">}</span><span class="tasty-fonts-preview-token-punctuation">}</span><span class="tasty-fonts-preview-token-tag">&gt;</span><span class="tasty-fonts-preview-token-string">12px baseline grid</span><span class="tasty-fonts-preview-token-tag">&lt;/code&gt;</span><span class="tasty-fonts-preview-token-punctuation">;</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">09</span>
                                <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-punctuation">}</span></span>
                            </div>
                        </div>
                    </div>

                    <div class="tasty-fonts-preview-code-statusbar">
                        <span data-role-preview="body"><?php esc_html_e('Editor Surface', 'tasty-fonts'); ?></span>
                        <span data-role-preview="body"><?php echo esc_html($monospaceRoleEnabled ? __('var(--font-monospace) applied', 'tasty-fonts') : __('Generic monospace fallback applied', 'tasty-fonts')); ?></span>
                    </div>
                </section>

                <section class="tasty-fonts-preview-code-block-shell">
                    <div class="tasty-fonts-preview-code-block-head">
                        <div class="tasty-fonts-preview-code-block-head-copy">
                            <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Published Code Block', 'tasty-fonts'); ?></span>
                            <h4 id="<?php echo esc_attr($blockPreviewHeadingId); ?>" class="tasty-fonts-preview-code-block-title" data-role-preview="heading"><?php esc_html_e('Front-End Snippet With Readable Line Height and Punctuation', 'tasty-fonts'); ?></h4>
                        </div>
                        <span class="tasty-fonts-preview-code-badge tasty-fonts-preview-code-badge--light">CSS</span>
                    </div>

                    <div class="tasty-fonts-preview-code-panel tasty-fonts-preview-code-panel--block" data-role-preview="monospace">
                        <div class="tasty-fonts-preview-code-lines" aria-label="<?php esc_attr_e('Published code block preview', 'tasty-fonts'); ?>" aria-labelledby="<?php echo esc_attr($blockPreviewHeadingId); ?>">
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">01</span>
                                <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-selector">.wp-block-code code</span> <span class="tasty-fonts-preview-token-punctuation">{</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">02</span>
                                <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">font-family</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-function">var</span><span class="tasty-fonts-preview-token-punctuation">(</span><span class="tasty-fonts-preview-token-variable">--font-monospace</span><span class="tasty-fonts-preview-token-punctuation">)</span><span class="tasty-fonts-preview-token-punctuation">;</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">03</span>
                                <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">font-size</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-number">0.95rem</span><span class="tasty-fonts-preview-token-punctuation">;</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">04</span>
                                <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">line-height</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-number">1.65</span><span class="tasty-fonts-preview-token-punctuation">;</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">05</span>
                                <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">background</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-string">linear-gradient(180deg, rgba(255,255,255,0.96), rgba(248,250,252,0.98))</span><span class="tasty-fonts-preview-token-punctuation">;</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">06</span>
                                <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-punctuation">}</span></span>
                            </div>
                        </div>
                    </div>

                    <p class="tasty-fonts-preview-code-caption" data-role-preview="body"><?php esc_html_e('Check braces, punctuation, zeroes, and how the selected code face holds together in both an editor and a front-end code block.', 'tasty-fonts'); ?></p>
                </section>
            </div>
        </div>
        <?php
    }
}
