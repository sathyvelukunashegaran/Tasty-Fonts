<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Admin\FontTypeHelper;
use TastyFonts\Support\FontUtils;

trait SharedRenderHelpers
{
    public function renderLogList(array $entries, string $className = 'tasty-fonts-log-list'): void
    {
        ?>
        <ol class="<?php echo esc_attr($className); ?>" data-activity-list>
            <?php foreach ($entries as $entry): ?>
                <?php
                $time = (string) ($entry['time'] ?? '');
                $actor = trim((string) ($entry['actor'] ?? ''));
                $message = (string) ($entry['message'] ?? '');
                $actionLabel = trim((string) ($entry['action_label'] ?? ''));
                $actionUrl = trim((string) ($entry['action_url'] ?? ''));
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

    public function renderNotices(array $toasts): void
    {
        if ($toasts === []) {
            return;
        }
        ?>
        <div class="tasty-fonts-toast-stack" aria-live="polite" aria-atomic="true">
            <?php foreach ($toasts as $toast): ?>
                <div
                    class="tasty-fonts-toast is-<?php echo esc_attr((string) ($toast['tone'] ?? 'success')); ?>"
                    data-toast
                    data-toast-tone="<?php echo esc_attr((string) ($toast['tone'] ?? 'success')); ?>"
                    role="<?php echo esc_attr((string) ($toast['role'] ?? 'status')); ?>"
                >
                    <div class="tasty-fonts-toast-message"><?php echo esc_html((string) ($toast['message'] ?? '')); ?></div>
                    <button type="button" class="tasty-fonts-toast-dismiss" data-toast-dismiss aria-label="<?php esc_attr_e('Dismiss notification', 'tasty-fonts'); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function renderEnvironmentNotice(array $notice): void
    {
        if ($notice === []) {
            return;
        }

        $title = trim((string) ($notice['title'] ?? ''));
        $message = trim((string) ($notice['message'] ?? ''));
        $settingsLabel = trim((string) ($notice['settings_label'] ?? ''));
        $settingsUrl = trim((string) ($notice['settings_url'] ?? ''));
        $toneClass = (string) ($notice['tone'] ?? '') === 'warning'
            ? ' tasty-fonts-inline-note--warning'
            : '';

        if ($title === '' && $message === '') {
            return;
        }
        ?>
        <div
            class="tasty-fonts-page-notice tasty-fonts-inline-note<?php echo esc_attr($toneClass); ?>"
            role="<?php echo esc_attr((string) ($notice['tone'] ?? '') === 'warning' ? 'alert' : 'status'); ?>"
            aria-live="<?php echo esc_attr((string) ($notice['tone'] ?? '') === 'warning' ? 'assertive' : 'polite'); ?>"
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

    public function buildFontTypeDescriptor(array $entry, string $context = 'library'): array
    {
        return FontTypeHelper::describeEntry($entry, $context);
    }

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

    public function renderFallbackInput(string $name, string $value, array $attributes = []): void
    {
        $className = 'regular-text';

        if (!empty($attributes['class']) && is_string($attributes['class'])) {
            $className .= ' ' . trim($attributes['class']);
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

        echo '<span class="tasty-fonts-combobox-field">';
        echo '<input';

        foreach ($inputAttributes as $key => $attributeValue) {
            if ($attributeValue === false || $attributeValue === null || $attributeValue === '') {
                continue;
            }

            if ($attributeValue === true) {
                echo ' ' . esc_attr((string) $key);
                continue;
            }

            echo ' ' . esc_attr((string) $key) . '="' . esc_attr((string) $attributeValue) . '"';
        }

        echo '>';
        echo '</span>';
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
}
