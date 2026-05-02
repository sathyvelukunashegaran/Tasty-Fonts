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
     * @param array{class?: string, id?: string, actions?: callable(): void} $options
     */
    public function renderRichEmptyState(string $title, string $copy, array $options = []): void
    {
        $extraClass = trim($this->stringValue($options, 'class'));
        $className = trim('tasty-fonts-empty-state tasty-fonts-empty-state--rich' . ($extraClass !== '' ? ' ' . $extraClass : ''));
        $id = trim($this->stringValue($options, 'id'));
        $actions = $options['actions'] ?? null;
        ?>
        <div<?php echo $id !== '' ? ' id="' . esc_attr($id) . '"' : ''; ?> class="<?php echo esc_attr($className); ?>">
            <div class="tasty-fonts-empty-state-body">
                <h3 class="tasty-fonts-empty-state-title"><?php echo esc_html($title); ?></h3>
                <p class="tasty-fonts-empty-state-copy"><?php echo esc_html($copy); ?></p>
            </div>
            <?php if (is_callable($actions)): ?>
                <div class="tasty-fonts-empty-state-actions">
                    <?php $actions(); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param list<ActivityLogEntry> $entries
     */
    public function renderLogList(array $entries, string $className = 'tasty-fonts-log-list', int $pageSize = 5, string $idPrefix = 'activity'): void
    {
        $pageSize = max(1, $pageSize);
        $pageSizeOptions = [5, 10, 25, 100];
        $idPrefix = sanitize_html_class($idPrefix !== '' ? $idPrefix : 'activity');

        if (!in_array($pageSize, $pageSizeOptions, true)) {
            $pageSizeOptions[] = $pageSize;
            sort($pageSizeOptions);
        }
        ?>
        <ol class="<?php echo esc_attr($className); ?>" data-activity-list data-activity-page-size="<?php echo esc_attr((string) $pageSize); ?>">
            <?php foreach ($entries as $index => $entry): ?>
                <?php
                $time = $this->stringValue($entry, 'time');
                $actor = $this->stringValue($entry, 'actor');
                $message = $this->stringValue($entry, 'message');
                $summary = $this->stringValue($entry, 'summary', $message);
                $outcome = $this->normalizeLogOutcome($this->stringValue($entry, 'outcome', 'info'));
                $statusLabel = $this->stringValue($entry, 'status_label', $this->defaultLogStatusLabel($outcome));
                $source = $this->stringValue($entry, 'source', __('Activity', 'tasty-fonts'));
                $actionLabel = $this->stringValue($entry, 'action_label');
                $actionUrl = $this->stringValue($entry, 'action_url');
                $details = $this->listOfMaps($entry['detail_items'] ?? []);
                $searchValue = $this->stringValue($entry, 'search_text');

                if ($searchValue === '') {
                    $searchParts = [$time, $actor, $message, $summary, $statusLabel, $source];

                    foreach ($details as $detail) {
                        $searchParts[] = $this->stringValue($detail, 'label');
                        $searchParts[] = $this->stringValue($detail, 'value');
                    }

                    $searchValue = trim(implode(' ', array_filter($searchParts, static fn ($value): bool => $value !== '')));
                }

                if ($details === [] && $message !== '') {
                    $details[] = [
                        'label' => __('Message', 'tasty-fonts'),
                        'value' => $message,
                        'kind' => 'message',
                    ];
                }

                $detailId = sprintf('tasty-fonts-%s-log-detail-%d', $idPrefix, $index + 1);
                ?>
                <li
                    class="tasty-fonts-log-item tasty-fonts-log-item--compact"
                    data-activity-entry
                    data-activity-actor="<?php echo esc_attr($actor); ?>"
                    data-activity-search="<?php echo esc_attr($searchValue); ?>"
                    data-activity-outcome="<?php echo esc_attr($outcome); ?>"
                >
                    <span class="tasty-fonts-log-marker" aria-hidden="true"></span>
                    <div class="tasty-fonts-log-content">
                        <div class="tasty-fonts-log-message-row tasty-fonts-log-summary-row">
                            <div class="tasty-fonts-log-primary">
                                <div class="tasty-fonts-log-message tasty-fonts-log-summary"><?php echo esc_html($summary); ?></div>
                                <div class="tasty-fonts-log-meta">
                                    <span class="tasty-fonts-log-time"><?php echo esc_html($time); ?></span>
                                    <?php if ($actor !== ''): ?>
                                        <span class="tasty-fonts-log-actor"><?php echo esc_html($actor); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="tasty-fonts-log-chips" aria-label="<?php esc_attr_e('Activity metadata', 'tasty-fonts'); ?>">
                                <span class="tasty-fonts-log-chip tasty-fonts-log-chip--status"><?php echo esc_html($statusLabel); ?></span>
                                <?php if ($source !== ''): ?>
                                    <span class="tasty-fonts-log-chip tasty-fonts-log-chip--source"><?php echo esc_html($source); ?></span>
                                <?php endif; ?>
                            </span>
                            <button
                                type="button"
                                class="button button-small tasty-fonts-log-toggle"
                                data-activity-detail-toggle
                                data-activity-detail-show-label="<?php echo esc_attr(sprintf(__('Show details for %s', 'tasty-fonts'), $summary)); ?>"
                                data-activity-detail-hide-label="<?php echo esc_attr(sprintf(__('Hide details for %s', 'tasty-fonts'), $summary)); ?>"
                                aria-expanded="false"
                                aria-controls="<?php echo esc_attr($detailId); ?>"
                                aria-label="<?php echo esc_attr(sprintf(__('Show details for %s', 'tasty-fonts'), $summary)); ?>"
                            >
                                <span class="tasty-fonts-log-toggle-icon" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e('Details', 'tasty-fonts'); ?></span>
                            </button>
                        </div>
                        <div
                            id="<?php echo esc_attr($detailId); ?>"
                            class="tasty-fonts-log-details"
                            data-activity-detail
                            hidden
                        >
                            <div class="tasty-fonts-log-details-inner">
                                <?php if ($details !== []): ?>
                                    <dl class="tasty-fonts-log-details-list">
                                        <?php foreach ($details as $detail): ?>
                                            <?php
                                            $detailLabel = $this->stringValue($detail, 'label');
                                            $detailValue = $this->stringValue($detail, 'value');

                                            if ($detailLabel === '' || $detailValue === '') {
                                                continue;
                                            }
                                            ?>
                                            <div class="tasty-fonts-log-detail-row">
                                                <dt><?php echo esc_html($detailLabel); ?></dt>
                                                <dd><?php echo esc_html($detailValue); ?></dd>
                                            </div>
                                        <?php endforeach; ?>
                                    </dl>
                                <?php endif; ?>
                                <?php if ($actionLabel !== '' && $actionUrl !== ''): ?>
                                    <a class="button button-small tasty-fonts-log-action" href="<?php echo esc_url($actionUrl); ?>">
                                        <?php echo esc_html($actionLabel); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <nav class="tasty-fonts-activity-pagination" data-activity-pagination hidden aria-label="<?php esc_attr_e('Activity log pages', 'tasty-fonts'); ?>">
            <span class="tasty-fonts-activity-range-status" data-activity-range-status aria-live="polite">
                <?php esc_html_e('Showing activity entries', 'tasty-fonts'); ?>
            </span>
            <span class="tasty-fonts-activity-pagination-actions">
                <label class="tasty-fonts-activity-page-size">
                    <span><?php esc_html_e('Rows', 'tasty-fonts'); ?></span>
                    <select data-activity-page-size-select aria-label="<?php esc_attr_e('Activity entries per page', 'tasty-fonts'); ?>">
                        <?php foreach ($pageSizeOptions as $option): ?>
                            <option value="<?php echo esc_attr((string) $option); ?>" <?php selected($pageSize, $option); ?>>
                                <?php echo esc_html((string) $option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="screen-reader-text"><?php esc_html_e('entries per page', 'tasty-fonts'); ?></span>
                </label>
                <span class="tasty-fonts-activity-page-controls">
                    <button type="button" class="button button-secondary tasty-fonts-activity-page-button" data-activity-page-previous aria-label="<?php esc_attr_e('Previous activity page', 'tasty-fonts'); ?>">
                        <span aria-hidden="true">&larr;</span>
                    </button>
                    <span class="tasty-fonts-activity-page-status" data-activity-page-status aria-live="polite">
                        <?php esc_html_e('Page 1 of 1', 'tasty-fonts'); ?>
                    </span>
                    <button type="button" class="button button-secondary tasty-fonts-activity-page-button" data-activity-page-next aria-label="<?php esc_attr_e('Next activity page', 'tasty-fonts'); ?>">
                        <span aria-hidden="true">&rarr;</span>
                    </button>
                </span>
            </span>
        </nav>
        <?php
    }

    private function normalizeLogOutcome(string $outcome): string
    {
        $outcome = sanitize_key($outcome);

        return in_array($outcome, ['success', 'info', 'warning', 'error', 'danger'], true)
            ? $outcome
            : 'info';
    }

    private function defaultLogStatusLabel(string $outcome): string
    {
        return match ($outcome) {
            'success' => __('Success', 'tasty-fonts'),
            'warning' => __('Warning', 'tasty-fonts'),
            'error' => __('Error', 'tasty-fonts'),
            'danger' => __('Deleted', 'tasty-fonts'),
            default => __('Info', 'tasty-fonts'),
        };
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
                    <div class="tasty-fonts-toast-body">
                        <div class="tasty-fonts-toast-message"><?php echo esc_html($this->stringValue($toast, 'message')); ?></div>
                    </div>
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
        $tone = $this->stringValue($notice, 'tone');
        $isWarning = $tone === 'warning';
        $toneClass = $isWarning ? ' tasty-fonts-inline-note--warning' : '';
        $bannerToneClass = $isWarning ? ' is-advisory' : ' is-info';

        if ($title === '' && $message === '') {
            return;
        }
        ?>
        <div
            class="tasty-fonts-banner<?php echo esc_attr($bannerToneClass); ?> tasty-fonts-page-notice tasty-fonts-inline-note<?php echo esc_attr($toneClass); ?>"
            role="<?php echo esc_attr($isWarning ? 'alert' : 'status'); ?>"
            aria-live="<?php echo esc_attr($isWarning ? 'assertive' : 'polite'); ?>"
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
                    <a class="button button-secondary tasty-fonts-page-notice-icon-action" href="<?php echo esc_url($settingsUrl); ?>" aria-label="<?php echo esc_attr($settingsLabel); ?>"<?php $this->renderPassiveHelpAttributes($settingsLabel); ?>>
                        <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php echo esc_html($settingsLabel); ?></span>
                    </a>
                <?php endif; ?>
                <form method="post" class="tasty-fonts-page-notice-form">
                    <?php wp_nonce_field('tasty_fonts_local_environment_notice'); ?>
                    <input type="hidden" name="tasty_fonts_local_environment_notice" value="1">
                    <button type="submit" class="button tasty-fonts-page-notice-icon-action" name="tasty_fonts_local_environment_notice_action" value="remind_tomorrow" aria-label="<?php esc_attr_e('Remind Tomorrow', 'tasty-fonts'); ?>"<?php $this->renderPassiveHelpAttributes(__('Remind Tomorrow', 'tasty-fonts')); ?>>
                        <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php esc_html_e('Remind Tomorrow', 'tasty-fonts'); ?></span>
                    </button>
                </form>
                <form method="post" class="tasty-fonts-page-notice-form">
                    <?php wp_nonce_field('tasty_fonts_local_environment_notice'); ?>
                    <input type="hidden" name="tasty_fonts_local_environment_notice" value="1">
                    <button type="submit" class="button tasty-fonts-page-notice-icon-action" name="tasty_fonts_local_environment_notice_action" value="remind_week" aria-label="<?php esc_attr_e('Remind in 1 Week', 'tasty-fonts'); ?>"<?php $this->renderPassiveHelpAttributes(__('Remind in 1 Week', 'tasty-fonts')); ?>>
                        <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php esc_html_e('Remind in 1 Week', 'tasty-fonts'); ?></span>
                    </button>
                </form>
                <form method="post" class="tasty-fonts-page-notice-form">
                    <?php wp_nonce_field('tasty_fonts_local_environment_notice'); ?>
                    <input type="hidden" name="tasty_fonts_local_environment_notice" value="1">
                    <button type="submit" class="button tasty-fonts-page-notice-icon-action" name="tasty_fonts_local_environment_notice_action" value="dismiss_forever" aria-label="<?php esc_attr_e('Never Show Again', 'tasty-fonts'); ?>"<?php $this->renderPassiveHelpAttributes(__('Never Show Again', 'tasty-fonts')); ?>>
                        <span class="dashicons dashicons-hidden" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php esc_html_e('Never Show Again', 'tasty-fonts'); ?></span>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function buildLibraryCategoryOptions(bool $variableFontsEnabled = true, bool $monospaceRoleEnabled = true): array
    {
        $options = [
            ['value' => 'all', 'label' => __('All Types', 'tasty-fonts')],
        ];

        if ($variableFontsEnabled) {
            $options[] = ['value' => 'variable', 'label' => __('Variable', 'tasty-fonts')];
        }

        $options[] = ['value' => 'sans-serif', 'label' => __('Sans-serif', 'tasty-fonts')];
        $options[] = ['value' => 'serif', 'label' => __('Serif', 'tasty-fonts')];

        if ($monospaceRoleEnabled) {
            $options[] = ['value' => 'monospace', 'label' => __('Monospace', 'tasty-fonts')];
        }

        $options[] = ['value' => 'display', 'label' => __('Display', 'tasty-fonts')];
        $options[] = ['value' => 'script', 'label' => __('Cursive / Script', 'tasty-fonts')];
        $options[] = ['value' => 'slab-serif', 'label' => __('Slab Serif', 'tasty-fonts')];
        $options[] = ['value' => 'uncategorized', 'label' => __('Uncategorized', 'tasty-fonts')];

        return $options;
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

    public function renderPassiveHelpAttributes(string $copy, string $describedBy = 'tasty-fonts-help-tooltip-layer', bool $force = false): void
    {
        $copy = trim($copy);
        $describedBy = trim($describedBy);

        if (($this->trainingWheelsOff && !$force) || $copy === '') {
            return;
        }

        echo ' data-help-tooltip="' . esc_attr($copy) . '"';
        echo ' data-help-passive="1"';
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

    public function renderSourceSetupCopy(string $title, string $summary, string $disclaimer = ''): void
    {
        ?>
        <div class="tasty-fonts-source-status-copy">
            <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Source', 'tasty-fonts'); ?></span>
            <div class="tasty-fonts-source-status-title-row">
                <h3><?php echo esc_html($title); ?></h3>
            </div>
            <p class="tasty-fonts-muted tasty-fonts-source-summary"><?php echo esc_html($summary); ?></p>
            <?php if (trim($disclaimer) !== ''): ?>
                <p class="tasty-fonts-muted tasty-fonts-source-disclaimer"><?php echo esc_html($disclaimer); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function renderAddFontsWorkflowDisabledNotice(string $workflowLabel, string $settingsUrl, string $copy = '', string $buttonLabel = ''): void
    {
        if ($copy === '') {
            $copy = sprintf(
                __('%s is disabled until its font import workflow is turned on in Settings > Behavior.', 'tasty-fonts'),
                $workflowLabel
            );
        }

        if ($buttonLabel === '') {
            $buttonLabel = __('Open Behavior Settings', 'tasty-fonts');
        }
        ?>
        <div class="tasty-fonts-empty tasty-fonts-empty--panel tasty-fonts-empty--workflow-disabled" role="note">
            <p><?php echo esc_html($copy); ?></p>
            <?php if ($settingsUrl !== ''): ?>
                <p><a class="button" href="<?php echo esc_url($settingsUrl); ?>"><?php echo esc_html($buttonLabel); ?></a></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $config
     */
    public function renderAddFontsSourceStatusCard(array $config): void
    {
        $title = $this->stringValue($config, 'title');
        $summary = $this->stringValue($config, 'summary');
        $enabled = !empty($config['enabled']);
        $enabledBadgeLabel = $this->stringValue($config, 'enabled_badge_label');
        $enabledBadgeClass = trim($this->stringValue($config, 'enabled_badge_class'));
        $disabledBadgeLabel = $this->stringValue($config, 'disabled_badge_label', __('Workflow Off', 'tasty-fonts'));
        $disabledBadgeClass = trim($this->stringValue($config, 'disabled_badge_class'));
        $cardClass = trim($this->stringValue($config, 'card_class'));
        $rowClass = trim($this->stringValue($config, 'row_class'));
        $enabledActions = $config['enabled_actions'] ?? null;
        $body = $config['body'] ?? null;
        $disclaimer = $this->stringValue($config, 'disclaimer');

        $sectionClass = 'tasty-fonts-source-card tasty-fonts-source-card--status';

        if ($cardClass !== '') {
            $sectionClass .= ' ' . $cardClass;
        }

        $statusRowClass = 'tasty-fonts-source-status-row';

        if ($rowClass !== '') {
            $statusRowClass .= ' ' . $rowClass;
        }

        $badgeLabel = $enabled ? $enabledBadgeLabel : $disabledBadgeLabel;
        $badgeClass = $enabled ? $enabledBadgeClass : $disabledBadgeClass;
        ?>
        <section class="<?php echo esc_attr($sectionClass); ?>">
            <div class="<?php echo esc_attr($statusRowClass); ?>">
                <?php $this->renderSourceSetupCopy($title, $summary, $disclaimer); ?>
                <div class="tasty-fonts-source-status-actions">
                    <span class="tasty-fonts-badge<?php echo $badgeClass !== '' ? ' ' . esc_attr($badgeClass) : ''; ?>"><?php echo esc_html($badgeLabel); ?></span>
                    <?php if ($enabled && is_callable($enabledActions)): ?>
                        <?php $enabledActions(); ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (is_callable($body)): ?>
                <?php $body($enabled); ?>
            <?php endif; ?>
        </section>
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
        $comboboxId = '';

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

        if ($inputId === '') {
            $inputId = wp_unique_id('tasty-fonts-fallback-');
            $attributes['id'] = $inputId;
        }

        $comboboxId = $inputId . '-options';

        if ($clearLabel !== '') {
            $wrapperClassName .= ' tasty-fonts-combobox-field--clearable';
        }

        $inputAttributes = array_merge(
            [
                'type' => 'text',
                'value' => FontUtils::sanitizeFallback($value),
                'class' => $className,
                'spellcheck' => 'false',
                'autocomplete' => 'off',
                'aria-autocomplete' => 'list',
                'aria-controls' => $comboboxId,
                'aria-expanded' => 'false',
            ],
            $attributes
        );

        if ($name !== '') {
            $inputAttributes['name'] = $name;
        }

        echo '<span class="' . esc_attr($wrapperClassName) . '" data-fallback-combobox>';
        echo '<span class="tasty-fonts-combobox-control">';
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
        ?>
        <button
            type="button"
            class="tasty-fonts-combobox-toggle"
            data-fallback-combobox-toggle
            aria-label="<?php esc_attr_e('Show fallback suggestions', 'tasty-fonts'); ?>"
            aria-controls="<?php echo esc_attr($comboboxId); ?>"
            aria-expanded="false"
        >
            <span aria-hidden="true"></span>
        </button>
        </span>
        <ul
            class="tasty-fonts-combobox-menu"
            id="<?php echo esc_attr($comboboxId); ?>"
            data-fallback-combobox-menu
            role="listbox"
            hidden
        >
            <?php foreach (array_values(array_unique(FontUtils::FALLBACK_SUGGESTIONS)) as $fallback): ?>
                <li role="option">
                    <button type="button" class="tasty-fonts-combobox-option" data-fallback-value="<?php echo esc_attr($fallback); ?>">
                        <?php echo esc_html($fallback); ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php

        if ($clearLabel !== '') {
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
                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Find', 'tasty-fonts'); ?></span>
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
                        <strong><?php esc_html_e('Search unavailable.', 'tasty-fonts'); ?></strong>
                        <span><?php echo esc_html($this->stringValue($config, 'search_disabled_copy')); ?></span>
                    </p>
                <?php endif; ?>
                <div id="<?php echo esc_attr($this->stringValue($config, 'results_id')); ?>" class="tasty-fonts-search-results" aria-live="polite"></div>
            </section>

            <section class="<?php echo esc_attr($this->stringValue($config, 'import_panel_class', 'tasty-fonts-source-card tasty-fonts-source-card--task tasty-fonts-import-panel tasty-fonts-workflow-step tasty-fonts-workflow-step--import')); ?>">
                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Import', 'tasty-fonts'); ?></span>
                    <h4 id="<?php echo esc_attr($this->stringValue($config, 'import_step_title_id')); ?>"><?php esc_html_e('Configure Import', 'tasty-fonts'); ?></h4>
                </div>

                <div class="tasty-fonts-import-manual-grid">
                    <label class="tasty-fonts-stack-field">
                        <?php $this->renderFieldLabel(__('Family', 'tasty-fonts')); ?>
                        <input type="text" id="<?php echo esc_attr($this->stringValue($config, 'manual_family_id')); ?>" class="regular-text tasty-fonts-text-control" placeholder="<?php echo esc_attr($this->stringValue($config, 'manual_family_placeholder')); ?>">
                    </label>
                    <label class="tasty-fonts-stack-field">
                        <span id="<?php echo esc_attr($this->stringValue($config, 'manual_variants_label_id')); ?>" class="tasty-fonts-field-label"><?php esc_html_e('Variants', 'tasty-fonts'); ?></span>
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
                            <span class="tasty-fonts-import-preview-label"><?php esc_html_e('Preview', 'tasty-fonts'); ?></span>
                            <div id="<?php echo esc_attr($this->stringValue($config, 'selected_preview_id')); ?>" class="tasty-fonts-import-selected-preview is-placeholder"><?php echo esc_html($this->stringValue($config, 'selected_preview_default')); ?></div>
                        </div>
                    </div>

                    <div class="tasty-fonts-selected-card tasty-fonts-selected-card--import-variants">
                        <div class="tasty-fonts-import-card-head">
                            <div class="tasty-fonts-import-card-copy">
                                <span id="<?php echo esc_attr($this->stringValue($config, 'selected_variants_label_id')); ?>" class="tasty-fonts-field-label"><?php esc_html_e('Selected Variants', 'tasty-fonts'); ?></span>
                                <p id="<?php echo esc_attr($this->stringValue($config, 'selected_variants_note_id')); ?>" class="tasty-fonts-import-variant-note tasty-fonts-muted"><?php echo esc_html($this->stringValue($config, 'selected_variants_note')); ?></p>
                            </div>
                            <div class="tasty-fonts-import-card-meta">
                                <div id="<?php echo esc_attr($this->stringValue($config, 'selection_summary_id')); ?>" class="tasty-fonts-import-selection-summary"><?php esc_html_e('0 selected', 'tasty-fonts'); ?></div>
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
                            <?php $this->renderPassiveHelpAttributes(__('Estimated WOFF2 transfer for the selected family and subset.', 'tasty-fonts')); ?>
                            aria-label="<?php esc_attr_e('Estimated transfer size information', 'tasty-fonts'); ?>"
                        ><?php esc_html_e('Approx. +0 KB WOFF2', 'tasty-fonts'); ?></button>
                        <button type="button" class="button button-primary" id="<?php echo esc_attr($this->stringValue($config, 'submit_id')); ?>"><?php esc_html_e('Add to Library', 'tasty-fonts'); ?></button>
                    </div>
                </div>
            </section>
        </div>
        <?php
    }

    public function renderUploadBuilderGroup(bool $variableUploadControlsEnabled, string $defaultFallback = ''): void
    {
        $fallbackValue = $defaultFallback !== '' ? $defaultFallback : FontUtils::DEFAULT_ROLE_SANS_FALLBACK;
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
                            $fallbackValue,
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

            <div class="tasty-fonts-upload-face-shell">
                <div class="tasty-fonts-upload-face-headings">
                    <span data-upload-heading="file"><?php esc_html_e('Font File', 'tasty-fonts'); ?></span>
                    <span data-upload-heading="weight"><?php esc_html_e('Weight', 'tasty-fonts'); ?></span>
                    <span data-upload-heading="style"><?php esc_html_e('Style', 'tasty-fonts'); ?></span>
                    <span data-upload-heading="variable"><?php esc_html_e('Variable', 'tasty-fonts'); ?></span>
                    <span data-upload-heading="action"><?php esc_html_e('Action', 'tasty-fonts'); ?></span>
                </div>

                <div class="tasty-fonts-upload-face-list" data-upload-face-list>
                    <?php $this->renderUploadBuilderRow($variableUploadControlsEnabled); ?>
                </div>
            </div>

            <div class="tasty-fonts-upload-group-actions">
                <button type="button" class="button" data-upload-add-face><?php esc_html_e('Add Face', 'tasty-fonts'); ?></button>
            </div>
        </section>
        <?php
    }

    public function renderUploadBuilderRow(bool $variableUploadControlsEnabled): void
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

                <label class="tasty-fonts-upload-variable-field" data-upload-field-label="variable">
                    <span class="screen-reader-text"><?php esc_html_e('Variable Font', 'tasty-fonts'); ?></span>
                    <span class="tasty-fonts-inline-checkbox tasty-fonts-upload-variable-toggle<?php echo $variableUploadControlsEnabled ? '' : ' is-disabled'; ?>"<?php if (!$variableUploadControlsEnabled): ?><?php $this->renderPassiveHelpAttributes(__('Enable variable fonts in Settings > Behavior to configure axes for local uploads.', 'tasty-fonts'), 'tasty-fonts-help-tooltip-layer', true); ?><?php endif; ?>>
                        <input type="checkbox" data-upload-field="is-variable"<?php echo $variableUploadControlsEnabled ? '' : ' disabled'; ?>>
                        <span><?php esc_html_e('Variable', 'tasty-fonts'); ?></span>
                    </span>
                </label>

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
                <button type="button" class="button tasty-fonts-upload-detected" data-upload-detected-apply aria-label="<?php esc_attr_e('Use detected font metadata', 'tasty-fonts'); ?>" hidden></button>
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
        $selectedFamily = $this->scalarStringValue($roles[$roleKey] ?? '');
        $fallbackValue = $this->scalarStringValue($roles[$roleKey . '_fallback'] ?? '', $this->defaultRoleFallback($roleKey));
        $usesMonospacePreview = $roleKey === 'monospace';
        $previewLabel = $usesMonospacePreview ? __('Code Preview', 'tasty-fonts') : __('Preview', 'tasty-fonts');
        $previewText = $this->buildRoleCardPreviewText(
            $this->stringValue($config, 'preview_text'),
            $selectedFamily,
            $usesMonospacePreview
        );
        $previewStack = $this->stringValue($config, 'preview_stack', $this->defaultRoleFallback($roleKey));
        ?>
        <section class="tasty-fonts-studio-card tasty-fonts-role-box" data-role-box="<?php echo esc_attr($roleKey); ?>">
            <div class="tasty-fonts-studio-card-head tasty-fonts-role-box-head">
                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                    <span class="tasty-fonts-role-box-icon" data-role-card-icon="<?php echo esc_attr($roleKey); ?>" aria-hidden="true"></span>
                    <span class="tasty-fonts-panel-kicker"><?php echo esc_html($this->stringValue($config, 'kicker')); ?></span>
                    <h3><?php echo esc_html($this->stringValue($config, 'title')); ?></h3>
                </div>
                <div class="tasty-fonts-role-box-meta">
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
            <div
                class="tasty-fonts-font-inline-preview tasty-fonts-role-inline-preview <?php echo $usesMonospacePreview ? 'is-monospace' : ''; ?>"
                data-role-inline-preview-card="<?php echo esc_attr($roleKey); ?>"
                role="group"
                aria-label="<?php echo esc_attr(sprintf(__('Preview for %s role', 'tasty-fonts'), $this->stringValue($config, 'title'))); ?>"
            >
                <div class="tasty-fonts-role-inline-preview-row tasty-fonts-role-inline-preview-row--live" data-role-live-preview-row="<?php echo esc_attr($roleKey); ?>" hidden>
                    <span class="tasty-fonts-font-inline-preview-label"><?php esc_html_e('Live now', 'tasty-fonts'); ?></span>
                    <div
                        class="tasty-fonts-font-inline-preview-text tasty-fonts-role-inline-preview-text tasty-fonts-role-inline-preview-text--live <?php echo $usesMonospacePreview ? 'is-monospace' : ''; ?>"
                        data-role-inline-preview-live="<?php echo esc_attr($roleKey); ?>"
                        style="font-family:<?php echo esc_attr($previewStack); ?>;"
                    ><?php echo esc_html($previewText); ?></div>
                </div>
                <div class="tasty-fonts-role-inline-preview-row tasty-fonts-role-inline-preview-row--draft">
                    <span class="tasty-fonts-font-inline-preview-label" data-role-inline-preview-label="<?php echo esc_attr($roleKey); ?>"><?php echo esc_html($previewLabel); ?></span>
                    <div
                        class="tasty-fonts-font-inline-preview-text tasty-fonts-role-inline-preview-text <?php echo $usesMonospacePreview ? 'is-monospace' : ''; ?>"
                        data-role-preview="<?php echo esc_attr($roleKey); ?>"
                        data-role-inline-preview-draft="<?php echo esc_attr($roleKey); ?>"
                        style="font-family:<?php echo esc_attr($previewStack); ?>;"
                    ><?php echo esc_html($previewText); ?></div>
                </div>
            </div>
            <div class="tasty-fonts-role-fields">
                <div class="tasty-fonts-stack-field">
                    <span class="tasty-fonts-field-label-row">
                        <label class="tasty-fonts-field-label-text" for="<?php echo esc_attr($this->stringValue($config, 'family_select_id')); ?>"><?php esc_html_e('Family', 'tasty-fonts'); ?></label>
                        <button
                            type="button"
                            class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger"
                            aria-label="<?php esc_attr_e('Explain role family delivery', 'tasty-fonts'); ?>"
                            <?php $this->renderPassiveHelpAttributes(__('Choose the delivery method in the Font Library. Role selectors use the family’s active delivery profile.', 'tasty-fonts')); ?>
                        >?</button>
                    </span>
                    <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                        <select name="<?php echo esc_attr($this->stringValue($config, 'family_input_name')); ?>" id="<?php echo esc_attr($this->stringValue($config, 'family_select_id')); ?>" form="<?php echo esc_attr($roleFormId); ?>" data-role-family-select="<?php echo esc_attr($roleKey); ?>">
                            <option value="" <?php selected($roles[$roleKey] ?? '', ''); ?>><?php esc_html_e('Use Fallback Only', 'tasty-fonts'); ?></option>
                            <?php $this->renderRoleFamilyOptions($availableFamilyOptions, $selectedFamily); ?>
                        </select>
                        <?php $this->renderClearSelectButton($this->stringValue($config, 'clear_family_label'), $this->stringValue($config, 'family_select_id')); ?>
                    </span>
                </div>
                <input
                    type="hidden"
                    name="<?php echo esc_attr($fallbackInputId); ?>"
                    id="<?php echo esc_attr($fallbackInputId); ?>"
                    value="<?php echo esc_attr($fallbackValue); ?>"
                    form="<?php echo esc_attr($roleFormId); ?>"
                    data-role-fallback-input="<?php echo esc_attr($roleKey); ?>"
                    data-role-fallback-default="<?php echo esc_attr($this->defaultRoleFallback($roleKey)); ?>"
                    data-role-fallback-stored-value="<?php echo esc_attr($fallbackValue); ?>"
                >
                <div class="tasty-fonts-role-weight-editor" data-role-weight-editor="<?php echo esc_attr($roleKey); ?>" hidden>
                    <label class="tasty-fonts-stack-field tasty-fonts-role-weight-field">
                        <?php $this->renderFieldLabel(__('Role Weight', 'tasty-fonts')); ?>
                        <span class="screen-reader-text" data-role-weight-summary="<?php echo esc_attr($roleKey); ?>"><?php esc_html_e('Choose a saved static weight when the selected family offers more than one.', 'tasty-fonts'); ?></span>
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
                    <div class="tasty-fonts-field-label-row tasty-fonts-role-axis-head">
                        <span class="tasty-fonts-field-label-text" data-role-axis-heading="<?php echo esc_attr($roleKey); ?>"><?php esc_html_e('Variable Axes', 'tasty-fonts'); ?></span>
                        <span class="tasty-fonts-muted" data-role-axis-summary="<?php echo esc_attr($roleKey); ?>"><?php esc_html_e('Assign axis values when the selected family supports variable fonts.', 'tasty-fonts'); ?></span>
                    </div>
                    <div class="tasty-fonts-role-axis-fields" data-role-axis-fields="<?php echo esc_attr($roleKey); ?>"></div>
                </div>
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

    private function buildRoleCardPreviewText(string $previewText, string $familyName, bool $isMonospace): string
    {
        if ($isMonospace) {
            $familyName = trim($familyName) !== '' ? trim($familyName) : 'Monospace';
            $literal = str_replace(['\\', '"'], ['\\\\', '\\"'], $familyName);

            return sprintf('const font = "%s";', $literal);
        }

        $normalized = preg_replace('/\s+/', ' ', trim($previewText));
        $normalized = is_string($normalized) ? $normalized : '';

        if ($normalized === '') {
            return __('The quick brown fox…', 'tasty-fonts');
        }

        return wp_trim_words($normalized, 9, '…');
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
