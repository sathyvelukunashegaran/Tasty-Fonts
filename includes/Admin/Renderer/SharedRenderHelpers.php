<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Admin\FontTypeHelper;
use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type ActivityLogEntry from \TastyFonts\Admin\AdminPageContextBuilder
 * @phpstan-import-type NoticeToast from \TastyFonts\Admin\AdminPageContextBuilder
 */
trait SharedRenderHelpers
{
    /**
     * @param list<ActivityLogEntry> $entries
     */
    public function renderLogList(array $entries, string $className = 'tasty-fonts-log-list'): void
    {
        ?>
        <ol class="<?php echo esc_attr($className); ?>" data-activity-list>
            <?php foreach ($entries as $entry): ?>
                <?php
                $time = $this->stringValue($entry, 'time');
                $actor = $this->stringValue($entry, 'actor');
                $message = $this->stringValue($entry, 'message');
                $actionLabel = $this->stringValue($entry, 'action_label');
                $actionUrl = $this->stringValue($entry, 'action_url');
                $searchValue = trim(implode(' ', array_filter([$time, $actor, $message], static fn ($value): bool => $value !== '')));
                ?>
                <li
                    class="tasty-fonts-log-item"
                    data-activity-entry
                    data-activity-actor="<?php echo esc_attr($actor); ?>"
                    data-activity-search="<?php echo esc_attr($searchValue); ?>"
                >
                    <span class="tasty-fonts-log-marker" aria-hidden="true"></span>
                    <div class="tasty-fonts-log-content">
                        <div class="tasty-fonts-log-message-row">
                            <div class="tasty-fonts-log-message"><?php echo esc_html($message); ?></div>
                            <?php if ($actionLabel !== '' && $actionUrl !== ''): ?>
                                <a class="button button-small tasty-fonts-log-action" href="<?php echo esc_url($actionUrl); ?>">
                                    <?php echo esc_html($actionLabel); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="tasty-fonts-log-meta">
                            <span class="tasty-fonts-log-time"><?php echo esc_html($time); ?></span>
                            <?php if ($actor !== ''): ?>
                                <span class="tasty-fonts-log-actor"><?php echo esc_html($actor); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php
    }

    public function renderFaceVariableCopyPill(string $label, string $value, string $successMessage): void
    {
        if ($value === '') {
            return;
        }
        ?>
        <span class="tasty-fonts-role-stack">
            <span class="tasty-fonts-role-stack-label"><?php echo esc_html($label); ?></span>
            <button
                type="button"
                class="tasty-fonts-pill tasty-fonts-pill--code tasty-fonts-pill--interactive tasty-fonts-pill--copy tasty-fonts-kbd tasty-fonts-role-stack-copy"
                data-copy-text="<?php echo esc_attr($value); ?>"
                data-copy-success="<?php echo esc_attr($successMessage); ?>"
                data-copy-static-label="1"
                aria-label="<?php echo esc_attr(sprintf(__('Copy %1$s: %2$s', 'tasty-fonts'), $label, $value)); ?>"
                title="<?php echo esc_attr($value); ?>"
            >
                <?php echo esc_html($value); ?>
            </button>
        </span>
        <?php
    }

    /**
     * @param list<NoticeToast> $toasts
     */
    public function renderNotices(array $toasts): void
    {
        if ($toasts === []) {
            return;
        }
        ?>
        <div class="tasty-fonts-toast-stack" aria-live="polite" aria-atomic="true">
            <?php foreach ($toasts as $toast): ?>
                <div
                    class="tasty-fonts-toast is-<?php echo esc_attr($this->stringValue($toast, 'tone', 'success')); ?>"
                    data-toast
                    data-toast-tone="<?php echo esc_attr($this->stringValue($toast, 'tone', 'success')); ?>"
                    role="<?php echo esc_attr($this->stringValue($toast, 'role', 'status')); ?>"
                >
                    <div class="tasty-fonts-toast-message"><?php echo esc_html($this->stringValue($toast, 'message')); ?></div>
                    <button type="button" class="tasty-fonts-toast-dismiss" data-toast-dismiss aria-label="<?php esc_attr_e('Dismiss notification', 'tasty-fonts'); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $notice
     */
    public function renderEnvironmentNotice(array $notice): void
    {
        if ($notice === []) {
            return;
        }

        $title = $this->stringValue($notice, 'title');
        $message = $this->stringValue($notice, 'message');
        $settingsLabel = $this->stringValue($notice, 'settings_label');
        $settingsUrl = $this->stringValue($notice, 'settings_url');
        $toneClass = $this->stringValue($notice, 'tone') === 'warning'
            ? ' tasty-fonts-inline-note--warning'
            : '';

        if ($title === '' && $message === '') {
            return;
        }
        ?>
        <div
            class="tasty-fonts-page-notice tasty-fonts-inline-note<?php echo esc_attr($toneClass); ?>"
            role="<?php echo esc_attr($this->stringValue($notice, 'tone') === 'warning' ? 'alert' : 'status'); ?>"
            aria-live="<?php echo esc_attr($this->stringValue($notice, 'tone') === 'warning' ? 'assertive' : 'polite'); ?>"
            aria-atomic="true"
        >
            <?php if ($title !== ''): ?>
                <strong><?php echo esc_html($title); ?></strong>
            <?php endif; ?>
            <?php if ($message !== ''): ?>
                <span><?php echo esc_html($message); ?></span>
            <?php endif; ?>
            <div class="tasty-fonts-page-notice-actions">
                <?php if ($settingsLabel !== '' && $settingsUrl !== ''): ?>
                    <a class="button button-secondary" href="<?php echo esc_url($settingsUrl); ?>">
                        <?php echo esc_html($settingsLabel); ?>
                    </a>
                <?php endif; ?>
                <form method="post" class="tasty-fonts-page-notice-form">
                    <?php wp_nonce_field('tasty_fonts_local_environment_notice'); ?>
                    <input type="hidden" name="tasty_fonts_local_environment_notice" value="1">
                    <button type="submit" class="button" name="tasty_fonts_local_environment_notice_action" value="remind_tomorrow">
                        <?php esc_html_e('Remind Tomorrow', 'tasty-fonts'); ?>
                    </button>
                </form>
                <form method="post" class="tasty-fonts-page-notice-form">
                    <?php wp_nonce_field('tasty_fonts_local_environment_notice'); ?>
                    <input type="hidden" name="tasty_fonts_local_environment_notice" value="1">
                    <button type="submit" class="button" name="tasty_fonts_local_environment_notice_action" value="remind_week">
                        <?php esc_html_e('Remind in 1 Week', 'tasty-fonts'); ?>
                    </button>
                </form>
                <form method="post" class="tasty-fonts-page-notice-form">
                    <?php wp_nonce_field('tasty_fonts_local_environment_notice'); ?>
                    <input type="hidden" name="tasty_fonts_local_environment_notice" value="1">
                    <button type="submit" class="button" name="tasty_fonts_local_environment_notice_action" value="dismiss_forever">
                        <?php esc_html_e('Never Show Again', 'tasty-fonts'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function buildLibraryCategoryOptions(): array
    {
        return [
            ['value' => 'all', 'label' => __('All Types', 'tasty-fonts')],
            ['value' => 'variable', 'label' => __('Variable', 'tasty-fonts')],
            ['value' => 'sans-serif', 'label' => __('Sans-serif', 'tasty-fonts')],
            ['value' => 'serif', 'label' => __('Serif', 'tasty-fonts')],
            ['value' => 'monospace', 'label' => __('Monospace', 'tasty-fonts')],
            ['value' => 'display', 'label' => __('Display', 'tasty-fonts')],
            ['value' => 'script', 'label' => __('Cursive / Script', 'tasty-fonts')],
            ['value' => 'slab-serif', 'label' => __('Slab Serif', 'tasty-fonts')],
            ['value' => 'uncategorized', 'label' => __('Uncategorized', 'tasty-fonts')],
        ];
    }

    public function formatLibraryCategoryLabel(string $category): string
    {
        return match (strtolower(trim($category))) {
            'sans-serif' => __('Sans-serif', 'tasty-fonts'),
            'serif' => __('Serif', 'tasty-fonts'),
            'monospace' => __('Monospace', 'tasty-fonts'),
            'display' => __('Display', 'tasty-fonts'),
            'script', 'cursive', 'handwriting' => __('Cursive / Script', 'tasty-fonts'),
            'slab-serif' => __('Slab Serif', 'tasty-fonts'),
            'uncategorized' => __('Uncategorized', 'tasty-fonts'),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    public function buildFontTypeDescriptor(array $entry, string $context = 'library'): array
    {
        return FontTypeHelper::describeEntry($entry, $context);
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    public function buildFontTypeOptionLabel(string $familyName, ?array $entry = null, string $context = 'library'): string
    {
        return FontTypeHelper::buildSelectorOptionLabel($familyName, $entry, $context);
    }

    public function renderPassiveHelpAttributes(string $copy, string $describedBy = 'tasty-fonts-help-tooltip-layer'): void
    {
        $copy = trim($copy);
        $describedBy = trim($describedBy);

        if ($this->trainingWheelsOff || $copy === '') {
            return;
        }

        echo ' data-help-tooltip="' . esc_attr($copy) . '"';
        echo ' data-help-passive="1"';
        echo ' title="' . esc_attr($copy) . '"';
        if ($describedBy !== '') {
            echo ' aria-describedby="' . esc_attr($describedBy) . '"';
        }
    }

    public function renderSectionHeading(string $tag, string $title, string $copy = ''): void
    {
        ?>
        <div class="tasty-fonts-section-heading">
            <div class="tasty-fonts-section-title-row">
                <<?php echo esc_html($tag); ?> class="tasty-fonts-section-title"><?php echo esc_html($title); ?></<?php echo esc_html($tag); ?>>
            </div>
            <?php if ($copy !== ''): ?>
                <p class="tasty-fonts-section-copy"><?php echo esc_html($copy); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function renderSourceSetupCopy(string $title, string $summary): void
    {
        ?>
        <div class="tasty-fonts-source-status-copy">
            <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Source Setup', 'tasty-fonts'); ?></span>
            <div class="tasty-fonts-source-status-title-row">
                <h4><?php echo esc_html($title); ?></h4>
            </div>
            <p class="tasty-fonts-muted tasty-fonts-source-summary"><?php echo esc_html($summary); ?></p>
        </div>
        <?php
    }

    public function renderFieldLabel(string $label): void
    {
        ?>
        <span class="tasty-fonts-field-label-row">
            <span class="tasty-fonts-field-label-text"><?php echo esc_html($label); ?></span>
        </span>
        <?php
    }

    public function buildElementId(string $value, string $fallback): string
    {
        $sanitized = strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value) ?? '', '-'));

        return $sanitized !== '' ? $sanitized : $fallback;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderFallbackInput(string $name, string $value, array $attributes = []): void
    {
        $className = 'regular-text tasty-fonts-text-control';
        $wrapperClassName = 'tasty-fonts-combobox-field';
        $clearValue = null;
        $clearLabel = '';
        $inputId = '';

        if (!empty($attributes['class']) && is_string($attributes['class'])) {
            $className .= ' ' . trim($attributes['class']);
        }

        if (!empty($attributes['wrapper_class']) && is_string($attributes['wrapper_class'])) {
            $wrapperClassName .= ' ' . trim($attributes['wrapper_class']);
            unset($attributes['wrapper_class']);
        }

        if (array_key_exists('clear_value', $attributes)) {
            $clearValue = is_scalar($attributes['clear_value']) ? (string) $attributes['clear_value'] : '';
            unset($attributes['clear_value']);
        }

        if (!empty($attributes['clear_label']) && is_string($attributes['clear_label'])) {
            $clearLabel = trim($attributes['clear_label']);
            unset($attributes['clear_label']);
        }

        if (!empty($attributes['id']) && is_string($attributes['id'])) {
            $inputId = trim($attributes['id']);
        }

        if ($clearLabel !== '') {
            $wrapperClassName .= ' tasty-fonts-combobox-field--clearable';
        }

        $inputAttributes = array_merge(
            [
                'type' => 'text',
                'list' => 'tasty-fonts-fallback-options',
                'value' => FontUtils::sanitizeFallback($value),
                'class' => $className,
                'spellcheck' => 'false',
                'autocomplete' => 'off',
                'aria-autocomplete' => 'list',
            ],
            $attributes
        );

        if ($name !== '') {
            $inputAttributes['name'] = $name;
        }

        echo '<span class="' . esc_attr($wrapperClassName) . '">';
        echo '<input';

        foreach ($inputAttributes as $key => $attributeValue) {
            if ($attributeValue === false || $attributeValue === null || $attributeValue === '') {
                continue;
            }

            if ($attributeValue === true) {
                echo ' ' . esc_attr($key);
                continue;
            }

            echo ' ' . esc_attr($key) . '="' . esc_attr($this->scalarStringValue($attributeValue)) . '"';
        }

        echo '>';

        if ($clearLabel !== '' && $inputId !== '') {
            $this->renderClearSelectButton($clearLabel, $inputId, (string) $clearValue);
        }

        echo '</span>';
    }

    /**
     * Render the shared icon-only clear button used by select and filter controls.
     *
     * The visible glyph stays decorative while the control name and reset behavior
     * are carried by aria-label and data attributes for the admin client.
     *
     * @since 1.10.0
     *
     * @param string $label Accessible button label announced by assistive technology.
     * @param string $targetId Optional input/select id to reset when the button is activated.
     * @param string $clearValue Optional value that should be restored when clearing the control.
     * @param bool   $alwaysVisibleAffordance Whether the client should keep the affordance visible even when the control is empty.
     * @return void
     */
    public function renderClearSelectButton(string $label, string $targetId = '', string $clearValue = '', bool $alwaysVisibleAffordance = false): void
    {
        $label = trim($label);
        $targetId = trim($targetId);

        if ($label === '') {
            return;
        }
        ?>
        <button
            type="button"
            class="tasty-fonts-select-clear"
            data-clear-select-button
            <?php if ($targetId !== ''): ?>
                data-clear-target="<?php echo esc_attr($targetId); ?>"
            <?php endif; ?>
            data-clear-value="<?php echo esc_attr($clearValue); ?>"
            <?php if ($alwaysVisibleAffordance): ?>
                data-clear-affordance="always"
            <?php endif; ?>
            aria-label="<?php echo esc_attr($label); ?>"
            hidden
        >
            <?php // Clear buttons stay icon-only visually; the glyph is decorative and the control is named via aria-label. ?>
            <span aria-hidden="true">&times;</span>
        </button>
        <?php
    }

    public function renderFallbackSuggestionList(): void
    {
        ?>
        <datalist id="tasty-fonts-fallback-options">
            <?php foreach (FontUtils::FALLBACK_SUGGESTIONS as $fallback): ?>
                <option value="<?php echo esc_attr($fallback); ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <?php
    }

    /**
     * @param array<string, mixed> $config
     */
    public function renderHostedImportWorkflow(array $config): void
    {
        $providerKey = $this->stringValue($config, 'provider_key');
        ?>
        <div class="<?php echo esc_attr($this->stringValue($config, 'workflow_class', 'tasty-fonts-google-workflow')); ?>">
            <section class="tasty-fonts-source-card tasty-fonts-source-card--task tasty-fonts-search-shell tasty-fonts-workflow-step tasty-fonts-workflow-step--search">
                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Step 1', 'tasty-fonts'); ?></span>
                    <h4><?php esc_html_e('Find a Family', 'tasty-fonts'); ?></h4>
                </div>

                <label class="tasty-fonts-stack-field">
                    <span class="screen-reader-text"><?php echo esc_html($this->stringValue($config, 'search_label')); ?></span>
                    <input
                        type="search"
                        id="<?php echo esc_attr($this->stringValue($config, 'search_input_id')); ?>"
                        class="regular-text tasty-fonts-text-control"
                        placeholder="<?php echo esc_attr($this->stringValue($config, 'search_placeholder')); ?>"
                        <?php if (!empty($config['search_note_id']) && !empty($config['search_disabled'])): ?>
                            aria-describedby="<?php echo esc_attr($this->scalarStringValue($config['search_note_id'])); ?>"
                        <?php endif; ?>
                        <?php disabled(!empty($config['search_disabled'])); ?>
                    >
                </label>
                <?php if (!empty($config['search_note_id']) && !empty($config['search_disabled'])): ?>
                    <p id="<?php echo esc_attr($this->scalarStringValue($config['search_note_id'])); ?>" class="tasty-fonts-inline-note tasty-fonts-inline-note--warning">
                        <strong><?php esc_html_e('Search disabled.', 'tasty-fonts'); ?></strong>
                        <span><?php echo esc_html($this->stringValue($config, 'search_disabled_copy')); ?></span>
                    </p>
                <?php endif; ?>
                <div id="<?php echo esc_attr($this->stringValue($config, 'results_id')); ?>" class="tasty-fonts-search-results" aria-live="polite"></div>
            </section>

            <section class="<?php echo esc_attr($this->stringValue($config, 'import_panel_class', 'tasty-fonts-source-card tasty-fonts-source-card--task tasty-fonts-import-panel tasty-fonts-workflow-step tasty-fonts-workflow-step--import')); ?>">
                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Step 2', 'tasty-fonts'); ?></span>
                    <h4 id="<?php echo esc_attr($this->stringValue($config, 'import_step_title_id')); ?>"><?php esc_html_e('Choose Variants and Delivery', 'tasty-fonts'); ?></h4>
                </div>

                <div class="tasty-fonts-import-manual-grid">
                    <label class="tasty-fonts-stack-field">
                        <?php $this->renderFieldLabel(__('Family Name', 'tasty-fonts')); ?>
                        <input type="text" id="<?php echo esc_attr($this->stringValue($config, 'manual_family_id')); ?>" class="regular-text tasty-fonts-text-control" placeholder="<?php echo esc_attr($this->stringValue($config, 'manual_family_placeholder')); ?>">
                    </label>
                    <label class="tasty-fonts-stack-field">
                        <span id="<?php echo esc_attr($this->stringValue($config, 'manual_variants_label_id')); ?>" class="tasty-fonts-field-label"><?php esc_html_e('Manual Variants', 'tasty-fonts'); ?></span>
                        <input type="text" id="<?php echo esc_attr($this->stringValue($config, 'manual_variants_id')); ?>" class="regular-text tasty-fonts-text-control" placeholder="<?php echo esc_attr($this->stringValue($config, 'manual_variants_placeholder')); ?>">
                    </label>
                </div>

                <div class="tasty-fonts-import-choice-grid">
                    <fieldset class="tasty-fonts-source-delivery-choice tasty-fonts-import-choice-fieldset tasty-fonts-import-choice-fieldset--format" id="<?php echo esc_attr($this->stringValue($config, 'format_fieldset_id')); ?>">
                        <legend class="screen-reader-text"><?php esc_html_e('Format', 'tasty-fonts'); ?></legend>
                        <span class="tasty-fonts-field-label tasty-fonts-import-choice-label"><?php esc_html_e('Format', 'tasty-fonts'); ?></span>
                        <div id="<?php echo esc_attr($this->stringValue($config, 'format_choice_id')); ?>" class="tasty-fonts-output-quick-options tasty-fonts-import-choice-options tasty-fonts-import-choice-options--segmented" hidden></div>
                        <p class="tasty-fonts-muted tasty-fonts-import-choice-note" data-import-format-note="<?php echo esc_attr($providerKey); ?>" hidden></p>
                    </fieldset>

                    <fieldset class="tasty-fonts-source-delivery-choice tasty-fonts-import-choice-fieldset tasty-fonts-import-choice-fieldset--delivery">
                        <legend class="screen-reader-text"><?php esc_html_e('Delivery', 'tasty-fonts'); ?></legend>
                        <span class="tasty-fonts-field-label tasty-fonts-import-choice-label"><?php esc_html_e('Delivery', 'tasty-fonts'); ?></span>
                        <div class="tasty-fonts-output-quick-options tasty-fonts-import-choice-options tasty-fonts-import-choice-options--segmented" data-import-delivery-choice="<?php echo esc_attr($providerKey); ?>">
                            <?php foreach ($this->listOfMaps($config['delivery_options'] ?? []) as $index => $option): ?>
                                <label class="tasty-fonts-output-quick-option<?php echo $index === 0 ? ' is-active' : ''; ?>" data-pill-option>
                                    <input type="radio" name="<?php echo esc_attr($this->stringValue($config, 'delivery_input_name')); ?>" value="<?php echo esc_attr($this->stringValue($option, 'value')); ?>" <?php checked($index, 0); ?> data-pill-option-input>
                                    <span><?php echo esc_html($this->stringValue($option, 'label')); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>

                <div class="tasty-fonts-selected-wrap tasty-fonts-selected-wrap--import">
                    <div class="tasty-fonts-selected-card tasty-fonts-selected-card--import-family">
                        <div class="tasty-fonts-import-card-head">
                            <div class="tasty-fonts-import-card-copy">
                                <?php $this->renderFieldLabel(__('Selected Family', 'tasty-fonts')); ?>
                                <div id="<?php echo esc_attr($this->stringValue($config, 'selected_family_id')); ?>" class="tasty-fonts-import-selected-name"><?php echo esc_html($this->stringValue($config, 'selected_family_default')); ?></div>
                                <div id="<?php echo esc_attr($this->stringValue($config, 'selected_family_meta_id')); ?>" class="tasty-fonts-import-selected-meta" hidden></div>
                                <p id="<?php echo esc_attr($this->stringValue($config, 'selected_family_note_id')); ?>" class="tasty-fonts-import-selected-note tasty-fonts-muted" hidden></p>
                            </div>
                        </div>
                        <div class="tasty-fonts-import-preview-shell">
                            <span class="tasty-fonts-import-preview-label"><?php esc_html_e('Live Preview', 'tasty-fonts'); ?></span>
                            <div id="<?php echo esc_attr($this->stringValue($config, 'selected_preview_id')); ?>" class="tasty-fonts-import-selected-preview is-placeholder"><?php echo esc_html($this->stringValue($config, 'selected_preview_default')); ?></div>
                        </div>
                    </div>

                    <div class="tasty-fonts-selected-card tasty-fonts-selected-card--import-variants">
                        <div class="tasty-fonts-import-card-head">
                            <div class="tasty-fonts-import-card-copy">
                                <span id="<?php echo esc_attr($this->stringValue($config, 'selected_variants_label_id')); ?>" class="tasty-fonts-field-label"><?php esc_html_e('Variants to Import', 'tasty-fonts'); ?></span>
                                <p id="<?php echo esc_attr($this->stringValue($config, 'selected_variants_note_id')); ?>" class="tasty-fonts-import-variant-note tasty-fonts-muted"><?php echo esc_html($this->stringValue($config, 'selected_variants_note')); ?></p>
                            </div>
                            <div class="tasty-fonts-import-card-meta">
                                <div id="<?php echo esc_attr($this->stringValue($config, 'selection_summary_id')); ?>" class="tasty-fonts-import-selection-summary"><?php esc_html_e('0 Variants Selected', 'tasty-fonts'); ?></div>
                            </div>
                        </div>
                        <div class="tasty-fonts-import-variant-toolbar">
                            <div class="tasty-fonts-import-variant-actions">
                                <button type="button" class="button tasty-fonts-filter-pill tasty-fonts-filter-pill--small tasty-fonts-variant-filter" data-<?php echo esc_attr($providerKey); ?>-variant-select="all"><?php esc_html_e('All', 'tasty-fonts'); ?></button>
                                <button type="button" class="button tasty-fonts-filter-pill tasty-fonts-filter-pill--small tasty-fonts-variant-filter" data-<?php echo esc_attr($providerKey); ?>-variant-select="normal"><?php esc_html_e('Normal', 'tasty-fonts'); ?></button>
                                <button type="button" class="button tasty-fonts-filter-pill tasty-fonts-filter-pill--small tasty-fonts-variant-filter" data-<?php echo esc_attr($providerKey); ?>-variant-select="italic"><?php esc_html_e('Italic', 'tasty-fonts'); ?></button>
                                <button type="button" class="button tasty-fonts-filter-pill tasty-fonts-filter-pill--small tasty-fonts-variant-filter" data-<?php echo esc_attr($providerKey); ?>-variant-select="clear"><?php esc_html_e('Clear', 'tasty-fonts'); ?></button>
                            </div>
                        </div>
                        <div id="<?php echo esc_attr($this->stringValue($config, 'variant_list_id')); ?>" class="tasty-fonts-variant-list"></div>
                    </div>
                </div>

                <div class="tasty-fonts-import-footer">
                    <div id="<?php echo esc_attr($this->stringValue($config, 'import_status_id')); ?>" class="tasty-fonts-import-status" aria-live="polite" aria-atomic="true"></div>

                    <div class="tasty-fonts-actions tasty-fonts-actions--import">
                        <button
                            type="button"
                            id="<?php echo esc_attr($this->stringValue($config, 'size_estimate_id')); ?>"
                            class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help is-role"
                            <?php $this->renderPassiveHelpAttributes(__('The estimated transfer size only varies by family and subset.', 'tasty-fonts')); ?>
                            aria-label="<?php esc_attr_e('Estimated transfer size information', 'tasty-fonts'); ?>"
                        ><?php esc_html_e('Approx. +0 KB WOFF2', 'tasty-fonts'); ?></button>
                        <button type="button" class="button button-primary" id="<?php echo esc_attr($this->stringValue($config, 'submit_id')); ?>"><?php esc_html_e('Add to Library', 'tasty-fonts'); ?></button>
                    </div>
                </div>
            </section>
        </div>
        <?php
    }

    public function renderUploadBuilderGroup(bool $showUploadVariableControls): void
    {
        ?>
        <section class="tasty-fonts-upload-group" data-upload-group>
            <div class="tasty-fonts-upload-group-head">
                <div class="tasty-fonts-upload-group-fields">
                    <label class="tasty-fonts-stack-field" data-upload-group-label="family">
                        <?php $this->renderFieldLabel(__('Family Name', 'tasty-fonts')); ?>
                        <input
                            type="text"
                            class="regular-text tasty-fonts-text-control"
                            data-upload-group-field="family"
                            placeholder="<?php esc_attr_e('Example: Satoshi', 'tasty-fonts'); ?>"
                        >
                    </label>

                    <label class="tasty-fonts-stack-field" data-upload-group-label="fallback">
                        <?php $this->renderFieldLabel(__('Fallback', 'tasty-fonts')); ?>
                        <?php
                        $this->renderFallbackInput(
                            '',
                            'sans-serif',
                            [
                                'data-upload-group-field' => 'fallback',
                                'placeholder' => __('Example: system-ui, sans-serif', 'tasty-fonts'),
                            ]
                        );
                        ?>
                    </label>
                </div>

                <button
                    type="button"
                    class="button tasty-fonts-button-danger tasty-fonts-upload-group-remove"
                    data-upload-remove-group
                >
                    <?php esc_html_e('Remove Family', 'tasty-fonts'); ?>
                </button>
            </div>

            <div class="tasty-fonts-upload-face-shell<?php echo $showUploadVariableControls ? '' : ' tasty-fonts-upload-face-shell--static-only'; ?>">
                <div class="tasty-fonts-upload-face-headings">
                    <span data-upload-heading="file"><?php esc_html_e('Font File', 'tasty-fonts'); ?></span>
                    <span data-upload-heading="weight"><?php esc_html_e('Weight', 'tasty-fonts'); ?></span>
                    <span data-upload-heading="style"><?php esc_html_e('Style', 'tasty-fonts'); ?></span>
                    <?php if ($showUploadVariableControls): ?>
                        <span data-upload-heading="variable"><?php esc_html_e('Variable', 'tasty-fonts'); ?></span>
                    <?php endif; ?>
                    <span data-upload-heading="action"><?php esc_html_e('Action', 'tasty-fonts'); ?></span>
                </div>

                <div class="tasty-fonts-upload-face-list" data-upload-face-list>
                    <?php $this->renderUploadBuilderRow($showUploadVariableControls); ?>
                </div>
            </div>

            <div class="tasty-fonts-upload-group-actions">
                <button type="button" class="button" data-upload-add-face><?php esc_html_e('Add Face', 'tasty-fonts'); ?></button>
            </div>
        </section>
        <?php
    }

    public function renderUploadBuilderRow(bool $showUploadVariableControls): void
    {
        ?>
        <div class="tasty-fonts-upload-face-row" data-upload-row role="group">
            <span class="screen-reader-text" data-upload-row-label></span>
            <div class="tasty-fonts-upload-face-grid">
                <label class="tasty-fonts-stack-field tasty-fonts-upload-file-field" data-upload-field-label="file">
                    <span class="screen-reader-text"><?php esc_html_e('Font File', 'tasty-fonts'); ?></span>
                    <span class="tasty-fonts-upload-file-picker">
                        <input
                            type="file"
                            class="tasty-fonts-upload-native-file"
                            data-upload-field="file"
                            accept=".woff2,.woff,.ttf,.otf"
                        >
                        <span class="tasty-fonts-upload-file-button"><?php esc_html_e('Select Font', 'tasty-fonts'); ?></span>
                        <span class="tasty-fonts-upload-file-name" data-upload-file-name><?php esc_html_e('No file chosen', 'tasty-fonts'); ?></span>
                    </span>
                </label>

                <label class="tasty-fonts-stack-field" data-upload-field-label="weight">
                    <span class="screen-reader-text"><?php esc_html_e('Weight', 'tasty-fonts'); ?></span>
                    <span class="tasty-fonts-select-field">
                        <select data-upload-field="weight">
                            <?php foreach (range(100, 900, 100) as $weight): ?>
                                <option value="<?php echo esc_attr((string) $weight); ?>" <?php selected((string) $weight, '400'); ?>><?php echo esc_html((string) $weight); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                </label>

                <label class="tasty-fonts-stack-field" data-upload-field-label="style">
                    <span class="screen-reader-text"><?php esc_html_e('Style', 'tasty-fonts'); ?></span>
                    <span class="tasty-fonts-select-field">
                        <select data-upload-field="style">
                            <option value="normal"><?php esc_html_e('Normal', 'tasty-fonts'); ?></option>
                            <option value="italic"><?php esc_html_e('Italic', 'tasty-fonts'); ?></option>
                            <option value="oblique"><?php esc_html_e('Oblique', 'tasty-fonts'); ?></option>
                        </select>
                    </span>
                </label>

                <?php if ($showUploadVariableControls): ?>
                    <label class="tasty-fonts-stack-field tasty-fonts-upload-variable-field" data-upload-field-label="variable">
                        <span class="screen-reader-text"><?php esc_html_e('Variable Font', 'tasty-fonts'); ?></span>
                        <span class="tasty-fonts-upload-variable-toggle">
                            <input type="checkbox" data-upload-field="is-variable">
                            <span><?php esc_html_e('Variable', 'tasty-fonts'); ?></span>
                        </span>
                    </label>
                <?php endif; ?>

                <button
                    type="button"
                    class="button tasty-fonts-button-danger tasty-fonts-upload-row-remove"
                    data-upload-remove
                    aria-label="<?php esc_attr_e('Remove row', 'tasty-fonts'); ?>"
                >
                    <?php esc_html_e('Remove', 'tasty-fonts'); ?>
                </button>
            </div>

            <div class="tasty-fonts-upload-row-foot">
                <button type="button" class="button tasty-fonts-upload-detected" data-upload-detected-apply hidden></button>
                <div class="tasty-fonts-upload-row-status" data-upload-row-status></div>
            </div>

            <div class="tasty-fonts-upload-axis-shell" data-upload-axis-shell hidden>
                <div class="tasty-fonts-upload-axis-list" data-upload-axis-list></div>
                <div class="tasty-fonts-upload-axis-actions">
                    <button type="button" class="button button-small" data-upload-add-axis><?php esc_html_e('Add Axis', 'tasty-fonts'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $roles
     * @param list<array<string, mixed>> $availableFamilyOptions
     */
    public function renderRoleSelectionCard(array $config, array $roles, array $availableFamilyOptions, string $roleFormId): void
    {
        $roleKey = $this->stringValue($config, 'role_key');
        $fallbackInputId = 'tasty_fonts_' . $roleKey . '_fallback';
        $fallbackValue = $this->scalarStringValue($roles[$roleKey . '_fallback'] ?? '', $this->defaultRoleFallback($roleKey));
        ?>
        <section class="tasty-fonts-studio-card tasty-fonts-role-box">
            <div class="tasty-fonts-studio-card-head tasty-fonts-role-box-head">
                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                    <span class="tasty-fonts-panel-kicker"><?php echo esc_html($this->stringValue($config, 'kicker')); ?></span>
                    <h4><?php echo esc_html($this->stringValue($config, 'title')); ?></h4>
                </div>
                <div class="tasty-fonts-role-box-meta">
                    <span class="tasty-fonts-role-box-meta-label"><?php esc_html_e('Current Value', 'tasty-fonts'); ?></span>
                    <button
                        type="button"
                        class="tasty-fonts-pill tasty-fonts-pill--code tasty-fonts-pill--interactive tasty-fonts-pill--copy tasty-fonts-kbd tasty-fonts-role-stack-copy tasty-fonts-role-box-copy"
                        data-role-family-variable-copy="<?php echo esc_attr($roleKey); ?>"
                        data-copy-text="<?php echo esc_attr($this->stringValue($config, 'family_variable')); ?>"
                        data-copy-success="<?php echo esc_attr($this->stringValue($config, 'copy_success')); ?>"
                        data-copy-static-label="1"
                        aria-label="<?php echo esc_attr($this->stringValue($config, 'copy_label')); ?>"
                        title="<?php echo esc_attr($this->stringValue($config, 'copy_label')); ?>"
                    >
                        <span class="tasty-fonts-role-box-copy-label"><?php echo esc_html($this->stringValue($config, 'family_variable')); ?></span>
                    </button>
                </div>
            </div>
            <p class="tasty-fonts-studio-card-copy tasty-fonts-role-box-description"><?php echo esc_html($this->stringValue($config, 'description')); ?></p>
            <div class="tasty-fonts-role-fields">
                <label class="tasty-fonts-stack-field">
                    <?php $this->renderFieldLabel(__('Family', 'tasty-fonts')); ?>
                    <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                        <select name="<?php echo esc_attr($this->stringValue($config, 'family_input_name')); ?>" id="<?php echo esc_attr($this->stringValue($config, 'family_select_id')); ?>" form="<?php echo esc_attr($roleFormId); ?>">
                            <option value="" <?php selected($roles[$roleKey] ?? '', ''); ?>><?php esc_html_e('Use Fallback Only', 'tasty-fonts'); ?></option>
                            <?php $this->renderRoleFamilyOptions($availableFamilyOptions, $this->scalarStringValue($roles[$roleKey] ?? '')); ?>
                        </select>
                        <?php $this->renderClearSelectButton($this->stringValue($config, 'clear_family_label'), $this->stringValue($config, 'family_select_id')); ?>
                    </span>
                </label>
                <label class="tasty-fonts-stack-field">
                    <?php $this->renderFieldLabel(__('Fallback', 'tasty-fonts')); ?>
                    <?php
                    $this->renderFallbackInput(
                        $fallbackInputId,
                        $fallbackValue,
                        [
                            'id' => $fallbackInputId,
                            'form' => $roleFormId,
                            'clear_value' => $this->defaultRoleFallback($roleKey),
                            'clear_label' => sprintf(__('Reset %s fallback', 'tasty-fonts'), $this->stringValue($config, 'title')),
                            'placeholder' => __('Example: system-ui, sans-serif', 'tasty-fonts'),
                        ]
                    );
                    ?>
                </label>
            </div>
            <div class="tasty-fonts-role-weight-editor" data-role-weight-editor="<?php echo esc_attr($roleKey); ?>" hidden>
                <div class="tasty-fonts-role-axis-head">
                    <span class="tasty-fonts-field-label-text"><?php esc_html_e('Role Weight', 'tasty-fonts'); ?></span>
                    <span class="tasty-fonts-muted" data-role-weight-summary="<?php echo esc_attr($roleKey); ?>"><?php esc_html_e('Choose a saved static weight when the selected family offers more than one.', 'tasty-fonts'); ?></span>
                </div>
                <label class="tasty-fonts-stack-field tasty-fonts-role-weight-field">
                    <span class="screen-reader-text"><?php echo esc_html($this->stringValue($config, 'weight_screen_reader_label')); ?></span>
                    <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                        <select
                            name="<?php echo esc_attr($this->stringValue($config, 'weight_input_name')); ?>"
                            id="<?php echo esc_attr($this->stringValue($config, 'weight_select_id')); ?>"
                            data-role-weight-select="<?php echo esc_attr($roleKey); ?>"
                            form="<?php echo esc_attr($roleFormId); ?>"
                        ></select>
                        <?php $this->renderClearSelectButton($this->stringValue($config, 'clear_weight_label'), $this->stringValue($config, 'weight_select_id')); ?>
                    </span>
                </label>
            </div>
            <div class="tasty-fonts-role-axis-editor" data-role-axis-editor="<?php echo esc_attr($roleKey); ?>" hidden>
                <div class="tasty-fonts-role-axis-head">
                    <span class="tasty-fonts-field-label-text" data-role-axis-heading="<?php echo esc_attr($roleKey); ?>"><?php esc_html_e('Variable Axes', 'tasty-fonts'); ?></span>
                    <span class="tasty-fonts-muted" data-role-axis-summary="<?php echo esc_attr($roleKey); ?>"><?php esc_html_e('Assign axis values when the selected family supports variable fonts.', 'tasty-fonts'); ?></span>
                </div>
                <div class="tasty-fonts-role-axis-fields" data-role-axis-fields="<?php echo esc_attr($roleKey); ?>"></div>
            </div>
        </section>
        <?php
    }

    /**
     * @param list<array<string, mixed>> $availableFamilyOptions
     */
    private function renderRoleFamilyOptions(array $availableFamilyOptions, string $selectedFamily): void
    {
        foreach ($availableFamilyOptions as $option) {
            $familyName = $this->stringValue($option, 'value');
            $familyLabel = $this->stringValue($option, 'label', $familyName);
            ?>
            <option value="<?php echo esc_attr($familyName); ?>" <?php selected($selectedFamily, $familyName); ?>><?php echo esc_html($familyLabel); ?></option>
            <?php
        }
    }

    private function defaultRoleFallback(string $roleKey): string
    {
        return $roleKey === 'monospace' ? 'monospace' : FontUtils::DEFAULT_ROLE_SANS_FALLBACK;
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function stringValue(array $values, string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $value = FontUtils::scalarStringValue($values[$key]);

        return $value !== '' ? $value : $default;
    }

    private function scalarStringValue(mixed $value, string $default = ''): string
    {
        $normalized = FontUtils::scalarStringValue($value);

        return $normalized !== '' ? $normalized : $default;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listOfMaps(mixed $value): array
    {
        return FontUtils::normalizeListOfStringKeyedMaps($value);
    }
}
