<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

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

    public function renderAdobeFamilyCard(array $family): void
    {
        $familyName = (string) ($family['family'] ?? '');

        if ($familyName === '') {
            return;
        }

        $faceSummaryLabels = $this->buildFamilyFaceSummaryLabels((array) ($family['faces'] ?? []));
        ?>
        <article class="tasty-fonts-adobe-family-card">
            <div class="tasty-fonts-adobe-family-head">
                <strong><?php echo esc_html($familyName); ?></strong>
                <span class="tasty-fonts-badge"><?php esc_html_e('Adobe', 'tasty-fonts'); ?></span>
            </div>
            <?php if ($faceSummaryLabels !== []): ?>
                <div class="tasty-fonts-face-pills">
                    <?php foreach ($faceSummaryLabels as $label): ?>
                        <span class="tasty-fonts-face-pill"><?php echo esc_html($label); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    public function renderFamilyRow(
        array $family,
        array $roles,
        array $familyFallbacks,
        array $familyFontDisplays,
        array $familyFontDisplayOptions,
        string $previewText,
        array $categoryAliasOwners = [],
        array $extendedVariableOptions = [],
        bool $monospaceRoleEnabled = false
    ): void
    {
        $familyName = (string) ($family['family'] ?? '');
        $familySlug = (string) ($family['slug'] ?? FontUtils::slugify($familyName));
        $isHeading = ($roles['heading'] ?? '') === $familyName;
        $isBody = ($roles['body'] ?? '') === $familyName;
        $isMonospace = $monospaceRoleEnabled && (($roles['monospace'] ?? '') === $familyName);
        $assignedRoleKeys = array_values(
            array_filter(
                [
                    $isHeading ? 'heading' : null,
                    $isBody ? 'body' : null,
                    $isMonospace ? 'monospace' : null,
                ]
            )
        );
        $isRoleFamily = $assignedRoleKeys !== [];
        $sourceTokens = array_values(array_unique(array_filter((array) ($family['delivery_filter_tokens'] ?? []), 'strlen')));
        $categoryTokens = array_values(array_unique(array_filter((array) ($family['font_category_tokens'] ?? []), 'strlen')));
        $fontCategoryLabel = $this->formatLibraryCategoryLabel((string) ($family['font_category'] ?? ''));
        $deleteBlockedMessage = $this->buildDeleteBlockedMessage($familyName, $assignedRoleKeys);
        $deleteBlockedMessages = [];
        $deleteBlockedSelections = [
            ['heading'],
            ['body'],
            ['heading', 'body'],
        ];

        if ($monospaceRoleEnabled) {
            $deleteBlockedSelections[] = ['monospace'];
            $deleteBlockedSelections[] = ['heading', 'monospace'];
            $deleteBlockedSelections[] = ['body', 'monospace'];
            $deleteBlockedSelections[] = ['heading', 'body', 'monospace'];
        }

        foreach ($deleteBlockedSelections as $selection) {
            $deleteBlockedMessages[$this->buildRoleSelectionKey($selection)] = $this->buildDeleteBlockedMessage($familyName, $selection);
        }
        $savedFallback = array_key_exists($familyName, $familyFallbacks)
            ? FontUtils::sanitizeFallback((string) $familyFallbacks[$familyName])
            : FontUtils::defaultFallbackForCategory((string) ($family['font_category'] ?? ''));
        $savedFontDisplay = (string) ($familyFontDisplays[$familyName] ?? '');
        $currentFontDisplay = $savedFontDisplay !== '' ? $savedFontDisplay : 'inherit';
        $publishState = (string) ($family['publish_state'] ?? 'published');
        $activeDelivery = is_array($family['active_delivery'] ?? null) ? $family['active_delivery'] : [];
        $activeDeliveryId = (string) ($family['active_delivery_id'] ?? '');
        $availableDeliveries = is_array($family['available_deliveries'] ?? null) ? (array) $family['available_deliveries'] : [];
        $deliveryCount = count($availableDeliveries);
        $activeDeliveryLabel = $this->translateProfileLabel((string) ($activeDelivery['label'] ?? ''));
        $activeDeliveryLabel = $activeDeliveryLabel !== '' ? $activeDeliveryLabel : __('Unavailable', 'tasty-fonts');
        $availableDeliveryLabels = array_values(
            array_filter(
                array_map(
                    fn (mixed $profile): string => is_array($profile)
                        ? $this->translateProfileLabel((string) ($profile['label'] ?? ''))
                        : '',
                    $availableDeliveries
                ),
                'strlen'
            )
        );
        $supportsFontDisplayOverride = strtolower(trim((string) ($activeDelivery['provider'] ?? ''))) !== 'adobe';
        $defaultStack = FontUtils::buildFontStack($familyName, $savedFallback);
        $previewLabel = $isMonospace ? __('Code Preview', 'tasty-fonts') : __('Preview', 'tasty-fonts');
        $inlinePreviewText = $this->buildFacePreviewText($previewText, $familyName, $isMonospace, false);
        $facePreviewText = $this->buildFacePreviewText($previewText, $familyName, $isMonospace, true);
        $faceSummaryLabels = $this->buildFamilyFaceSummaryLabels((array) ($family['faces'] ?? []));
        $visibleFaceSummaryLabels = array_slice($faceSummaryLabels, 0, 4);
        $hiddenFaceSummaryCount = max(0, count($faceSummaryLabels) - count($visibleFaceSummaryLabels));
        $faceCount = count((array) ($family['faces'] ?? []));
        $activeFaces = is_array($family['faces'] ?? null) ? (array) $family['faces'] : [];
        $familyCssVariableSnippets = $this->buildFamilyCssVariableSnippets(
            $familyName,
            $defaultStack,
            $assignedRoleKeys,
            $roles,
            (string) ($family['font_category'] ?? ''),
            $categoryAliasOwners,
            $extendedVariableOptions
        );
        $canChangePublishState = $publishState !== 'role_active';
        $isExpanded = false;
        $detailsId = 'tasty-fonts-family-details-' . sanitize_html_class($familySlug !== '' ? $familySlug : FontUtils::slugify($familyName));
        ?>
        <article
            class="tasty-fonts-row tasty-fonts-font-card <?php echo $isRoleFamily ? 'is-active' : ''; ?> <?php echo $isExpanded ? 'is-expanded' : ''; ?>"
            data-font-row
            data-font-name="<?php echo esc_attr(strtolower($familyName)); ?>"
            data-font-family="<?php echo esc_attr($familyName); ?>"
            data-font-slug="<?php echo esc_attr($familySlug); ?>"
            data-font-sources="<?php echo esc_attr(implode(' ', $sourceTokens)); ?>"
            data-font-categories="<?php echo esc_attr(implode(' ', $categoryTokens)); ?>"
        >
            <div class="tasty-fonts-row-head">
                <div class="tasty-fonts-font-card-main">
                            <div class="tasty-fonts-font-card-top">
                                <div class="tasty-fonts-font-primary">
                                    <div class="tasty-fonts-font-identity">
                                        <div class="tasty-fonts-font-identity-top">
                                            <div class="tasty-fonts-font-title-row">
                                        <h3><?php echo esc_html($familyName); ?></h3>
                                        <button
                                            type="button"
                                            class="tasty-fonts-stack-copy tasty-fonts-pill--interactive tasty-fonts-pill--copy"
                                            data-copy-text="<?php echo esc_attr($defaultStack); ?>"
                                            data-copy-success="<?php esc_attr_e('Font stack copied.', 'tasty-fonts'); ?>"
                                            data-copy-static-label="1"
                                            aria-label="<?php echo esc_attr(sprintf(__('Copy font stack for %s', 'tasty-fonts'), $familyName)); ?>"
                                            title="<?php echo esc_attr($defaultStack); ?>"
                                        >
                                            <?php echo esc_html($defaultStack); ?>
                                        </button>
                                    </div>
                                    <div class="tasty-fonts-badges">
                                        <?php foreach ((array) ($family['delivery_badges'] ?? []) as $badge): ?>
                                            <?php if (!is_array($badge)) { continue; } ?>
                                            <span
                                                class="tasty-fonts-badge <?php echo esc_attr((string) ($badge['class'] ?? '')); ?>"
                                                title="<?php echo esc_attr((string) ($badge['copy'] ?? '')); ?>"
                                            ><?php echo esc_html((string) ($badge['label'] ?? '')); ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($fontCategoryLabel !== ''): ?>
                                            <span class="tasty-fonts-badge"><?php echo esc_html($fontCategoryLabel); ?></span>
                                        <?php endif; ?>
                                        <?php if ($isHeading): ?>
                                            <span class="tasty-fonts-badge is-role"><?php esc_html_e('Heading', 'tasty-fonts'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($isBody): ?>
                                            <span class="tasty-fonts-badge is-role"><?php esc_html_e('Body', 'tasty-fonts'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($isMonospace): ?>
                                            <span class="tasty-fonts-badge is-role"><?php esc_html_e('Monospace', 'tasty-fonts'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($deliveryCount > 1): ?>
                                <div class="tasty-fonts-inline-note tasty-fonts-inline-note--compact">
                                    <strong><?php esc_html_e('Available delivery profiles', 'tasty-fonts'); ?></strong>
                                    <span><?php echo esc_html($availableDeliveryLabels !== [] ? implode(', ', $availableDeliveryLabels) : __('None saved yet.', 'tasty-fonts')); ?></span>
                                </div>
                                <div class="tasty-fonts-inline-note tasty-fonts-inline-note--compact">
                                    <strong><?php esc_html_e('Live delivery', 'tasty-fonts'); ?></strong>
                                    <span><?php echo esc_html($activeDeliveryLabel . ' · ' . $this->buildProfileRequestSummary($activeDelivery)); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($visibleFaceSummaryLabels !== [] || $deliveryCount === 1): ?>
                                <div class="tasty-fonts-font-loaded <?php echo $deliveryCount === 1 ? 'tasty-fonts-font-loaded--inline' : ''; ?>">
                                    <?php if ($visibleFaceSummaryLabels !== []): ?>
                                    <div class="tasty-fonts-face-pills">
                                        <?php foreach ($visibleFaceSummaryLabels as $label): ?>
                                            <span class="tasty-fonts-face-pill"><?php echo esc_html($label); ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($hiddenFaceSummaryCount > 0): ?>
                                            <span class="tasty-fonts-face-pill is-muted">
                                                <?php echo esc_html(sprintf(__('+%d more', 'tasty-fonts'), $hiddenFaceSummaryCount)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($deliveryCount === 1): ?>
                                        <div class="tasty-fonts-badges tasty-fonts-badges--library-inline">
                                            <span
                                                class="tasty-fonts-badge"
                                                title="<?php echo esc_attr($this->buildProfileRequestSummary($activeDelivery)); ?>"
                                            >
                                                <?php echo esc_html($activeDeliveryLabel); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="tasty-fonts-font-inline-preview <?php echo $isMonospace ? 'is-monospace' : ''; ?>" role="group" aria-label="<?php echo esc_attr(sprintf(__('Preview for %s', 'tasty-fonts'), $familyName)); ?>">
                                <span class="tasty-fonts-font-inline-preview-label"><?php echo esc_html($previewLabel); ?></span>
                                <div
                                    class="tasty-fonts-font-inline-preview-text <?php echo $isMonospace ? 'is-monospace' : ''; ?>"
                                    data-font-preview-family="<?php echo esc_attr($familyName); ?>"
                                    style="font-family:<?php echo esc_attr($defaultStack); ?>;"
                                ><?php echo esc_html($inlinePreviewText); ?></div>
                            </div>
                            <div class="tasty-fonts-font-specimen <?php echo $isMonospace ? 'is-monospace' : ''; ?>" role="group" aria-label="<?php echo esc_attr(sprintf(__('Preview for %s', 'tasty-fonts'), $familyName)); ?>">
                                <span class="tasty-fonts-font-specimen-label"><?php echo esc_html($previewLabel); ?></span>
                                <div
                                    class="tasty-fonts-font-specimen-display <?php echo $isMonospace ? 'is-monospace' : ''; ?>"
                                    data-font-preview-family="<?php echo esc_attr($familyName); ?>"
                                    style="font-family:<?php echo esc_attr($defaultStack); ?>;"
                                ><?php echo esc_html($facePreviewText); ?></div>
                            </div>
                        </div>

                            <div class="tasty-fonts-font-sidebar">
                                <div class="tasty-fonts-family-meta">
                                    <?php
                                    $runtimeStateHelp = $canChangePublishState
                                        ? __('Published serves this family at runtime. In Library Only keeps it in the library without loading it on the site.', 'tasty-fonts')
                                        : __('This family is live through the current active roles, so its runtime state is managed automatically.', 'tasty-fonts');
                                    ?>
                                    <form method="post" class="tasty-fonts-family-publish-state-form" data-family-publish-state-form>
                                        <div class="tasty-fonts-inline-field-row">
                                            <label class="tasty-fonts-inline-field tasty-fonts-inline-field--select">
                                                <span class="tasty-fonts-field-label-row">
                                                    <span class="tasty-fonts-field-label-text"><?php esc_html_e('Runtime State', 'tasty-fonts'); ?></span>
                                                    <?php if (!$canChangePublishState): ?>
                                                        <span class="tasty-fonts-field-status tasty-fonts-field-status--auto" title="<?php esc_attr_e('Auto-managed', 'tasty-fonts'); ?>"><?php esc_html_e('Auto', 'tasty-fonts'); ?></span>
                                                    <?php endif; ?>
                                                    <?php $this->renderHelpTip($runtimeStateHelp, __('Runtime State', 'tasty-fonts')); ?>
                                                </span>
                                                <span class="tasty-fonts-select-field">
                                                    <select
                                                        class="tasty-fonts-family-publish-state-selector"
                                                        data-family-slug="<?php echo esc_attr($familySlug); ?>"
                                                        data-saved-value="<?php echo esc_attr($publishState === 'library_only' ? 'library_only' : 'published'); ?>"
                                                        <?php disabled(!$canChangePublishState); ?>
                                                    >
                                                        <option value="published" <?php selected($publishState !== 'library_only'); ?>><?php esc_html_e('Published', 'tasty-fonts'); ?></option>
                                                        <option value="library_only" <?php selected($publishState === 'library_only'); ?>><?php esc_html_e('In Library Only', 'tasty-fonts'); ?></option>
                                                    </select>
                                                </span>
                                            </label>
                                            <button
                                                type="submit"
                                                class="button tasty-fonts-family-save-button tasty-fonts-family-publish-state-save"
                                                data-family-publish-state-save
                                                <?php $this->renderPassiveHelpAttributes(__('Save runtime state changes.', 'tasty-fonts')); ?>
                                                aria-label="<?php echo esc_attr__('Save runtime state', 'tasty-fonts'); ?>"
                                                <?php disabled(!$canChangePublishState); ?>
                                            >
                                                <span class="screen-reader-text"><?php esc_html_e('Save State', 'tasty-fonts'); ?></span>
                                            </button>
                                        </div>
                                        <p class="tasty-fonts-family-publish-state-feedback" data-family-publish-state-feedback aria-live="polite" hidden></p>
                                    </form>

                                    <?php if (count($availableDeliveries) > 1): ?>
                                        <form method="post" class="tasty-fonts-family-delivery-form" data-family-delivery-form>
                                            <div class="tasty-fonts-inline-field-row">
                                                <label class="tasty-fonts-inline-field tasty-fonts-inline-field--select">
                                                    <span class="tasty-fonts-field-label"><?php esc_html_e('Live Delivery', 'tasty-fonts'); ?></span>
                                                    <span class="tasty-fonts-select-field">
                                                        <select
                                                            class="tasty-fonts-family-delivery-selector"
                                                            data-family-slug="<?php echo esc_attr($familySlug); ?>"
                                                            data-saved-value="<?php echo esc_attr($activeDeliveryId); ?>"
                                                        >
                                                            <?php foreach ($availableDeliveries as $profile): ?>
                                                                <?php if (!is_array($profile)) { continue; } ?>
                                                                <option value="<?php echo esc_attr((string) ($profile['id'] ?? '')); ?>" <?php selected($activeDeliveryId, (string) ($profile['id'] ?? '')); ?>>
                                                                    <?php echo esc_html($this->translateProfileLabel((string) ($profile['label'] ?? ''))); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </span>
                                                </label>
                                                <button
                                                    type="submit"
                                                    class="button tasty-fonts-family-delivery-save"
                                                    data-family-delivery-save
                                                >
                                                    <?php esc_html_e('Switch Delivery', 'tasty-fonts'); ?>
                                                </button>
                                            </div>
                                            <p class="tasty-fonts-family-delivery-feedback" data-family-delivery-feedback aria-live="polite" hidden></p>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" class="tasty-fonts-family-fallback-form" data-family-fallback-form>
                                        <?php wp_nonce_field('tasty_fonts_save_family_fallback'); ?>
                                        <input type="hidden" name="tasty_fonts_save_family_fallback" value="1">
                                    <input type="hidden" name="tasty_fonts_family_name" value="<?php echo esc_attr($familyName); ?>">
                                    <div class="tasty-fonts-inline-field-row">
                                        <label class="tasty-fonts-inline-field tasty-fonts-inline-field--select">
                                            <span class="tasty-fonts-field-label"><?php esc_html_e('Fallback', 'tasty-fonts'); ?></span>
                                            <?php
                                            $this->renderFallbackInput(
                                                'tasty_fonts_family_fallback',
                                                $savedFallback,
                                                [
                                                    'class' => 'tasty-fonts-fallback-selector',
                                                    'data-font-family' => $familyName,
                                                    'data-saved-value' => $savedFallback,
                                                    'placeholder' => __('Example: system-ui, sans-serif', 'tasty-fonts'),
                                                ]
                                            );
                                            ?>
                                        </label>
                                        <button
                                            type="submit"
                                            class="button tasty-fonts-family-save-button tasty-fonts-family-fallback-save"
                                            data-family-fallback-save
                                            <?php $this->renderPassiveHelpAttributes(__('Save fallback stack changes.', 'tasty-fonts')); ?>
                                            aria-label="<?php echo esc_attr__('Save fallback', 'tasty-fonts'); ?>"
                                        >
                                            <span class="screen-reader-text"><?php esc_html_e('Save Fallback', 'tasty-fonts'); ?></span>
                                        </button>
                                    </div>
                                    <p class="tasty-fonts-family-fallback-feedback" data-family-fallback-feedback aria-live="polite" hidden></p>
                                </form>

                                <?php if ($supportsFontDisplayOverride): ?>
                                    <form method="post" class="tasty-fonts-family-font-display-form" data-family-font-display-form>
                                        <?php wp_nonce_field('tasty_fonts_save_family_font_display'); ?>
                                        <input type="hidden" name="tasty_fonts_save_family_font_display" value="1">
                                        <input type="hidden" name="tasty_fonts_family_name" value="<?php echo esc_attr($familyName); ?>">
                                        <div class="tasty-fonts-inline-field-row">
                                            <label class="tasty-fonts-inline-field tasty-fonts-inline-field--select">
                                                <span class="tasty-fonts-field-label"><?php esc_html_e('Font Display', 'tasty-fonts'); ?></span>
                                                <span class="tasty-fonts-select-field">
                                                    <select
                                                        name="tasty_fonts_family_font_display"
                                                        class="tasty-fonts-font-display-selector"
                                                        data-font-family="<?php echo esc_attr($familyName); ?>"
                                                        data-saved-value="<?php echo esc_attr($currentFontDisplay); ?>"
                                                    >
                                                        <?php foreach ($familyFontDisplayOptions as $option): ?>
                                                            <option value="<?php echo esc_attr((string) ($option['value'] ?? '')); ?>" <?php selected($currentFontDisplay, (string) ($option['value'] ?? '')); ?>>
                                                                <?php echo esc_html((string) ($option['label'] ?? '')); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </span>
                                            </label>
                                            <button
                                                type="submit"
                                                class="button tasty-fonts-family-save-button tasty-fonts-family-font-display-save"
                                                data-family-font-display-save
                                                <?php $this->renderPassiveHelpAttributes(__('Save font display changes.', 'tasty-fonts')); ?>
                                                aria-label="<?php echo esc_attr__('Save font display', 'tasty-fonts'); ?>"
                                            >
                                                <span class="screen-reader-text"><?php esc_html_e('Save Display', 'tasty-fonts'); ?></span>
                                            </button>
                                        </div>
                                        <p class="tasty-fonts-family-font-display-feedback" data-family-font-display-feedback aria-live="polite" hidden></p>
                                    </form>
                                <?php else: ?>
                                    <div class="tasty-fonts-inline-note tasty-fonts-inline-note--warning">
                                        <strong><?php esc_html_e('Font Display unavailable', 'tasty-fonts'); ?></strong>
                                        <span><?php esc_html_e('Adobe-hosted web fonts follow Adobe’s hosted stylesheet behavior and cannot be overridden from this plugin.', 'tasty-fonts'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="tasty-fonts-font-actions">
                                <div class="tasty-fonts-font-actions-primary <?php echo $monospaceRoleEnabled ? 'has-monospace-role' : ''; ?>">
                                    <button
                                        type="button"
                                        class="button tasty-fonts-role-assign-button tasty-fonts-role-assign-button--icon-only tasty-fonts-font-action-button--icon <?php echo $isHeading ? 'is-current' : ''; ?>"
                                        data-role-assign="heading"
                                        data-font-family="<?php echo esc_attr($familyName); ?>"
                                        data-active-label="<?php echo esc_attr__('Heading selected', 'tasty-fonts'); ?>"
                                        data-idle-label="<?php echo esc_attr__('Select heading', 'tasty-fonts'); ?>"
                                        data-active-help="<?php echo esc_attr__('This family is currently selected for the heading role.', 'tasty-fonts'); ?>"
                                        data-idle-help="<?php echo esc_attr__('Assign this family to the heading role and save the updated roles immediately. Apply sitewide when you want the live CSS updated.', 'tasty-fonts'); ?>"
                                        <?php $this->renderPassiveHelpAttributes($isHeading ? __('This family is currently selected for the heading role.', 'tasty-fonts') : __('Assign this family to the heading role and save the updated roles immediately. Apply sitewide when you want the live CSS updated.', 'tasty-fonts')); ?>
                                        aria-label="<?php echo esc_attr($isHeading ? __('Heading selected', 'tasty-fonts') : __('Select heading', 'tasty-fonts')); ?>"
                                        aria-pressed="<?php echo esc_attr($isHeading ? 'true' : 'false'); ?>"
                                    >
                                        <span class="screen-reader-text tasty-fonts-role-assign-label"><?php echo $isHeading ? esc_html__('Heading selected', 'tasty-fonts') : esc_html__('Select heading', 'tasty-fonts'); ?></span>
                                    </button>
                                    <button
                                        type="button"
                                        class="button tasty-fonts-role-assign-button tasty-fonts-role-assign-button--icon-only tasty-fonts-font-action-button--icon <?php echo $isBody ? 'is-current' : ''; ?>"
                                        data-role-assign="body"
                                        data-font-family="<?php echo esc_attr($familyName); ?>"
                                        data-active-label="<?php echo esc_attr__('Body selected', 'tasty-fonts'); ?>"
                                        data-idle-label="<?php echo esc_attr__('Select body', 'tasty-fonts'); ?>"
                                        data-active-help="<?php echo esc_attr__('This family is currently selected for the body role.', 'tasty-fonts'); ?>"
                                        data-idle-help="<?php echo esc_attr__('Assign this family to the body role and save the updated roles immediately. Apply sitewide when you want the live CSS updated.', 'tasty-fonts'); ?>"
                                        <?php $this->renderPassiveHelpAttributes($isBody ? __('This family is currently selected for the body role.', 'tasty-fonts') : __('Assign this family to the body role and save the updated roles immediately. Apply sitewide when you want the live CSS updated.', 'tasty-fonts')); ?>
                                        aria-label="<?php echo esc_attr($isBody ? __('Body selected', 'tasty-fonts') : __('Select body', 'tasty-fonts')); ?>"
                                        aria-pressed="<?php echo esc_attr($isBody ? 'true' : 'false'); ?>"
                                    >
                                        <span class="screen-reader-text tasty-fonts-role-assign-label"><?php echo $isBody ? esc_html__('Body selected', 'tasty-fonts') : esc_html__('Select body', 'tasty-fonts'); ?></span>
                                    </button>
                                    <?php if ($monospaceRoleEnabled): ?>
                                        <button
                                            type="button"
                                            class="button tasty-fonts-role-assign-button tasty-fonts-role-assign-button--icon-only tasty-fonts-font-action-button--icon <?php echo $isMonospace ? 'is-current' : ''; ?>"
                                            data-role-assign="monospace"
                                            data-font-family="<?php echo esc_attr($familyName); ?>"
                                            data-active-label="<?php echo esc_attr__('Monospace selected', 'tasty-fonts'); ?>"
                                            data-idle-label="<?php echo esc_attr__('Select monospace', 'tasty-fonts'); ?>"
                                            data-active-help="<?php echo esc_attr__('This family is currently selected for the monospace role.', 'tasty-fonts'); ?>"
                                            data-idle-help="<?php echo esc_attr__('Assign this family to the monospace role and save the updated roles immediately. Apply sitewide when you want the live CSS updated.', 'tasty-fonts'); ?>"
                                            <?php $this->renderPassiveHelpAttributes($isMonospace ? __('This family is currently selected for the monospace role.', 'tasty-fonts') : __('Assign this family to the monospace role and save the updated roles immediately. Apply sitewide when you want the live CSS updated.', 'tasty-fonts')); ?>
                                            aria-label="<?php echo esc_attr($isMonospace ? __('Monospace selected', 'tasty-fonts') : __('Select monospace', 'tasty-fonts')); ?>"
                                            aria-pressed="<?php echo esc_attr($isMonospace ? 'true' : 'false'); ?>"
                                        >
                                            <span class="screen-reader-text tasty-fonts-role-assign-label"><?php echo $isMonospace ? esc_html__('Monospace selected', 'tasty-fonts') : esc_html__('Select monospace', 'tasty-fonts'); ?></span>
                                        </button>
                                    <?php endif; ?>
                                    <button
                                        type="button"
                                        class="button tasty-fonts-disclosure-button tasty-fonts-disclosure-button--card tasty-fonts-font-action-button--details"
                                        data-disclosure-toggle="<?php echo esc_attr($detailsId); ?>"
                                        data-expanded-label="<?php echo esc_attr__('Details', 'tasty-fonts'); ?>"
                                        data-collapsed-label="<?php echo esc_attr__('Details', 'tasty-fonts'); ?>"
                                        aria-expanded="<?php echo esc_attr($isExpanded ? 'true' : 'false'); ?>"
                                        aria-controls="<?php echo esc_attr($detailsId); ?>"
                                        aria-label="<?php echo esc_attr__('Details', 'tasty-fonts'); ?>"
                                    >
                                        <?php esc_html_e('Details', 'tasty-fonts'); ?>
                                    </button>
                                    <form method="post" class="tasty-fonts-delete-form">
                                        <?php wp_nonce_field('tasty_fonts_delete_family'); ?>
                                        <input type="hidden" name="tasty_fonts_delete_family" value="1">
                                        <input type="hidden" name="tasty_fonts_family_slug" value="<?php echo esc_attr($familySlug); ?>">
                                        <button
                                            type="submit"
                                            class="button tasty-fonts-button-danger tasty-fonts-font-action-button--icon <?php echo $isRoleFamily ? 'is-disabled' : ''; ?>"
                                            data-delete-family="<?php echo esc_attr($familyName); ?>"
                                            data-delete-ready-title="<?php echo esc_attr(__('Delete this family and remove its managed files from the site library.', 'tasty-fonts')); ?>"
                                            <?php foreach ($deleteBlockedMessages as $key => $message): ?>
                                                data-delete-blocked-<?php echo esc_attr($key); ?>="<?php echo esc_attr($message); ?>"
                                            <?php endforeach; ?>
                                            <?php $this->renderPassiveHelpAttributes($deleteBlockedMessage !== '' ? $deleteBlockedMessage : __('Delete this family and remove its managed files from the site library.', 'tasty-fonts')); ?>
                                            aria-label="<?php echo esc_attr__('Delete family', 'tasty-fonts'); ?>"
                                            aria-disabled="<?php echo esc_attr($isRoleFamily ? 'true' : 'false'); ?>"
                                            <?php if ($deleteBlockedMessage !== '') : ?>
                                                data-delete-blocked="<?php echo esc_attr($deleteBlockedMessage); ?>"
                                            <?php endif; ?>
                                        >
                                            <span class="screen-reader-text"><?php esc_html_e('Delete family', 'tasty-fonts'); ?></span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="<?php echo esc_attr($detailsId); ?>" class="tasty-fonts-family-details<?php echo $familyCssVariableSnippets !== [] ? ' has-family-css-variables' : ''; ?>" <?php echo $isExpanded ? '' : 'hidden'; ?>>
                <section class="tasty-fonts-detail-group tasty-fonts-detail-group--profiles">
                    <div class="tasty-fonts-detail-group-head">
                        <div class="tasty-fonts-detail-group-copy">
                            <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Saved', 'tasty-fonts'); ?></span>
                            <h4><?php esc_html_e('Delivery Profiles', 'tasty-fonts'); ?></h4>
                        </div>
                        <span class="tasty-fonts-badge">
                            <?php echo esc_html(sprintf(_n('%d profile', '%d profiles', count($availableDeliveries), 'tasty-fonts'), count($availableDeliveries))); ?>
                        </span>
                    </div>
                    <div class="tasty-fonts-detail-card-list tasty-fonts-detail-card-list--deliveries">
                        <?php foreach ($availableDeliveries as $profile): ?>
                            <?php if (!is_array($profile)) { continue; } ?>
                            <?php $this->renderDeliveryProfileCard($familyName, $familySlug, $activeDeliveryId, $publishState, $profile); ?>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php if ($familyCssVariableSnippets !== []): ?>
                    <section class="tasty-fonts-detail-group tasty-fonts-detail-group--variables">
                        <div class="tasty-fonts-detail-group-head">
                            <div class="tasty-fonts-detail-group-copy">
                                <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Saved', 'tasty-fonts'); ?></span>
                                <h4><?php esc_html_e('CSS Variables', 'tasty-fonts'); ?></h4>
                            </div>
                            <span class="tasty-fonts-badge">
                                <?php echo esc_html(sprintf(_n('%d variable', '%d variables', count($familyCssVariableSnippets), 'tasty-fonts'), count($familyCssVariableSnippets))); ?>
                            </span>
                        </div>
                        <div class="tasty-fonts-detail-files">
                            <div class="tasty-fonts-role-stacks tasty-fonts-role-stacks--selection">
                                <?php foreach ($familyCssVariableSnippets as $label => $snippet) : ?>
                                    <?php $this->renderFaceVariableCopyPill(__($label, 'tasty-fonts'), (string) $snippet, __('CSS variable copied.', 'tasty-fonts')); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="tasty-fonts-detail-group tasty-fonts-detail-group--faces">
                    <div class="tasty-fonts-detail-group-head">
                        <div class="tasty-fonts-detail-group-copy">
                            <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Saved', 'tasty-fonts'); ?></span>
                            <h4><?php esc_html_e('Font Faces', 'tasty-fonts'); ?></h4>
                        </div>
                        <span class="tasty-fonts-badge">
                            <?php echo esc_html(sprintf(_n('%d face', '%d faces', $faceCount, 'tasty-fonts'), $faceCount)); ?>
                        </span>
                    </div>
                    <div class="tasty-fonts-detail-card-list tasty-fonts-detail-card-list--faces">
                        <?php foreach ($activeFaces as $face): ?>
                            <?php if (!is_array($face)) { continue; } ?>
                            <?php $this->renderFaceDetailCard($familyName, $familySlug, $defaultStack, $facePreviewText, $faceCount, $assignedRoleKeys, (string) ($family['font_category'] ?? ''), $categoryAliasOwners, $extendedVariableOptions, $activeDelivery, $face, $isMonospace); ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </article>
        <?php
    }

    public function buildDeleteBlockedMessage(string $familyName, array $roleLabels): string
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

    public function buildDeleteLastVariantBlockedMessage(string $familyName, array $roleLabels): string
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

    public function translateRoleLabels(array $roleLabels): array
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

    public function formatRoleLabelList(array $labels): string
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

    public function roleSetsMatch(array $left, array $right, bool $includeMonospace): bool
    {
        $keys = ['heading', 'body', 'heading_fallback', 'body_fallback'];

        if ($includeMonospace) {
            $keys[] = 'monospace';
            $keys[] = 'monospace_fallback';
        }

        foreach ($keys as $key) {
            if (trim((string) ($left[$key] ?? '')) !== trim((string) ($right[$key] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    public function buildRoleSelectionKey(array $roleKeys): string
    {
        $orderedKeys = [];

        foreach (['heading', 'body', 'monospace'] as $roleKey) {
            if (in_array($roleKey, $roleKeys, true)) {
                $orderedKeys[] = $roleKey;
            }
        }

        return implode('-', $orderedKeys);
    }

    public function buildFontVariableReference(string $familyName): string
    {
        return FontUtils::fontVariableReference($familyName);
    }

    public function buildFaceCssCopySnippets(string $familyName, string $weight, string $style, array $extendedVariableOptions = []): array
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

    public function buildFamilyCssVariableSnippets(
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

    public function buildCategoryAliasOwners(array $families, array $roles, bool $includeMonospace): array
    {
        $owners = [];
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
                if (!is_array($family) || isset($usedKeys[$familyKey])) {
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
            if (!is_array($family) || isset($usedKeys[$familyKey])) {
                continue;
            }

            $orderedFamilies[] = $family;
        }

        foreach ($orderedFamilies as $family) {
            $property = $this->resolveCategoryAliasProperty(
                $this->resolveFamilyCategory((array) $family)
            );

            if (
                $property === ''
                || (!$includeMonospace && $property === '--font-mono')
                || isset($owners[$property])
            ) {
                continue;
            }

            $owners[$property] = trim((string) ($family['family'] ?? ''));
        }

        return $owners;
    }

    public function resolveFamilyCategory(array $family): string
    {
        $category = trim((string) ($family['font_category'] ?? ''));

        if ($category === '' && is_array($family['active_delivery'] ?? null) && is_array($family['active_delivery']['meta'] ?? null)) {
            $category = trim((string) ($family['active_delivery']['meta']['category'] ?? ''));
        }

        return $category;
    }

    public function resolveCategoryAliasProperty(string $category): string
    {
        return match (strtolower(trim($category))) {
            'sans-serif', 'sans serif' => '--font-sans',
            'serif', 'slab-serif', 'slab serif' => '--font-serif',
            'monospace' => '--font-mono',
            default => '',
        };
    }

    public function buildWeightReference(string $weight, array $extendedVariableOptions = []): string
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

    public function extendedVariableOutputEnabled(array $options): bool
    {
        return !array_key_exists('enabled', $options) || !empty($options['enabled']);
    }

    public function extendedVariableWeightTokensEnabled(array $options): bool
    {
        return $this->extendedVariableOutputEnabled($options)
            && (!array_key_exists('weight_tokens', $options) || !empty($options['weight_tokens']));
    }

    public function extendedVariableRoleAliasesEnabled(array $options): bool
    {
        return $this->extendedVariableOutputEnabled($options)
            && (!array_key_exists('role_aliases', $options) || !empty($options['role_aliases']));
    }

    public function extendedVariableCategoryAliasEnabled(array $options, string $categoryAliasProperty): bool
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
                aria-label="<?php echo esc_attr(sprintf(__('Copy %s', 'tasty-fonts'), $label)); ?>"
                title="<?php echo esc_attr($value); ?>"
            >
                <?php echo esc_html($value); ?>
            </button>
        </span>
        <?php
    }

    public function buildFamilySourceTokens(array $sources, bool $isRoleFamily = false): array
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

    public function buildFamilySourceLabel(string $source): string
    {
        return match (strtolower(trim($source))) {
            'local' => __('Local', 'tasty-fonts'),
            'google' => __('Google', 'tasty-fonts'),
            'bunny' => __('Bunny', 'tasty-fonts'),
            'adobe' => __('Adobe', 'tasty-fonts'),
            default => ucfirst(trim($source)),
        };
    }

    public function translateProfileLabel(string $label): string
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

    public function buildProfileRequestSummary(array $profile): string
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

    public function isMigratableCdnProfile(array $profile): bool
    {
        $provider = strtolower(trim((string) ($profile['provider'] ?? '')));
        $type = strtolower(trim((string) ($profile['type'] ?? '')));

        return $type === 'cdn' && in_array($provider, ['google', 'bunny'], true);
    }

    public function renderMigrateDeliveryButton(string $familyName, array $profile, string $className = 'button'): void
    {
        $provider = strtolower(trim((string) ($profile['provider'] ?? '')));
        $variants = array_values(
            array_filter(
                array_map(static fn (mixed $variant): string => is_scalar($variant) ? trim((string) $variant) : '', (array) ($profile['variants'] ?? [])),
                'strlen'
            )
        );

        ?>
        <button
            type="button"
            class="<?php echo esc_attr(trim($className)); ?>"
            data-migrate-delivery
            data-migrate-provider="<?php echo esc_attr($provider); ?>"
            data-migrate-family="<?php echo esc_attr($familyName); ?>"
            data-migrate-variants="<?php echo esc_attr(implode(',', $variants)); ?>"
            title="<?php echo esc_attr__('Open the import panel with this CDN delivery pre-filled for self-hosting.', 'tasty-fonts'); ?>"
        >
            <?php esc_html_e('Migrate to Self-hosted', 'tasty-fonts'); ?>
        </button>
        <?php
    }

    public function renderDeliveryProfileCard(
        string $familyName,
        string $familySlug,
        string $activeDeliveryId,
        string $publishState,
        array $profile
    ): void {
        $profileId = (string) ($profile['id'] ?? '');
        $profileLabel = $this->translateProfileLabel((string) ($profile['label'] ?? ''));
        $profileProvider = (string) ($profile['provider'] ?? '');
        $profileIsActive = $profileId === $activeDeliveryId;
        $profileDeleteBlocked = $profileIsActive && $publishState === 'role_active'
            ? __('Switch the live delivery or remove this family from the active roles before deleting this delivery profile.', 'tasty-fonts')
            : '';
        ?>
        <article class="tasty-fonts-detail-card tasty-fonts-detail-card--delivery">
            <div class="tasty-fonts-detail-card-head">
                <div class="tasty-fonts-detail-card-copy">
                    <div class="tasty-fonts-detail-card-title-row">
                        <h5 class="tasty-fonts-detail-card-title"><?php echo esc_html($profileLabel); ?></h5>
                        <?php if ($profileIsActive): ?>
                            <span class="tasty-fonts-badge is-success"><?php esc_html_e('Live', 'tasty-fonts'); ?></span>
                        <?php else: ?>
                            <span class="tasty-fonts-badge"><?php esc_html_e('Saved', 'tasty-fonts'); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="tasty-fonts-detail-card-summary"><?php echo esc_html($this->buildProfileRequestSummary($profile)); ?></p>
                </div>
                <div class="tasty-fonts-detail-actions">
                    <?php if ($this->isMigratableCdnProfile($profile)): ?>
                        <?php $this->renderMigrateDeliveryButton($familyName, $profile, 'button button-small'); ?>
                    <?php endif; ?>
                    <button
                        type="button"
                        class="button button-small tasty-fonts-button-danger <?php echo $profileDeleteBlocked !== '' ? 'is-disabled' : ''; ?>"
                        data-delete-delivery-profile
                        data-family-slug="<?php echo esc_attr($familySlug); ?>"
                        data-family-name="<?php echo esc_attr($familyName); ?>"
                        data-delivery-id="<?php echo esc_attr($profileId); ?>"
                        data-delivery-label="<?php echo esc_attr($profileLabel); ?>"
                        aria-disabled="<?php echo esc_attr($profileDeleteBlocked !== '' ? 'true' : 'false'); ?>"
                        title="<?php echo esc_attr($profileDeleteBlocked !== '' ? $profileDeleteBlocked : __('Delete only this delivery profile and keep the family.', 'tasty-fonts')); ?>"
                        <?php if ($profileDeleteBlocked !== '') : ?>
                            data-delete-blocked="<?php echo esc_attr($profileDeleteBlocked); ?>"
                        <?php endif; ?>
                    >
                        <?php esc_html_e('Delete Delivery', 'tasty-fonts'); ?>
                    </button>
                </div>
            </div>

            <dl class="tasty-fonts-detail-meta">
                <div class="tasty-fonts-detail-meta-item">
                    <dt><?php esc_html_e('Provider', 'tasty-fonts'); ?></dt>
                    <dd><?php echo esc_html($this->buildFamilySourceLabel($profileProvider)); ?></dd>
                </div>
                <div class="tasty-fonts-detail-meta-item">
                    <dt><?php esc_html_e('Request Path', 'tasty-fonts'); ?></dt>
                    <dd><?php echo esc_html($this->buildProfileRequestSummary($profile)); ?></dd>
                </div>
                <div class="tasty-fonts-detail-meta-item">
                    <dt><?php esc_html_e('Variants', 'tasty-fonts'); ?></dt>
                    <dd><?php echo esc_html(sprintf(_n('%d variant', '%d variants', count((array) ($profile['variants'] ?? [])), 'tasty-fonts'), count((array) ($profile['variants'] ?? [])))); ?></dd>
                </div>
            </dl>
        </article>
        <?php
    }

    public function renderFaceDetailCard(
        string $familyName,
        string $familySlug,
        string $defaultStack,
        string $facePreviewText,
        int $faceCount,
        array $assignedRoleKeys,
        string $fontCategory,
        array $categoryAliasOwners,
        array $extendedVariableOptions,
        array $activeDelivery,
        array $face,
        bool $isMonospace = false
    ): void {
        $faceWeight = (string) ($face['weight'] ?? '400');
        $faceStyle = (string) ($face['style'] ?? 'normal');
        $faceSource = (string) ($face['source'] ?? 'local');
        $faceUnicodeRange = (string) ($face['unicode_range'] ?? '');
        $faceStorageSummary = $this->buildFaceStorageSummary($face);
        $canDeleteVariant = $this->canDeleteFaceVariant($activeDelivery);
        $deleteVariantBlockedMessage = ($faceCount <= 1 && $assignedRoleKeys !== [])
            ? $this->buildDeleteLastVariantBlockedMessage($familyName, $assignedRoleKeys)
            : '';
        $formats = array_keys((array) ($face['files'] ?? []));
        $paths = (array) ($face['paths'] ?? []);
        $faceTitle = $this->buildFaceTitle($faceWeight, $faceStyle);
        $faceCssSnippets = $this->buildFaceCssCopySnippets($familyName, $faceWeight, $faceStyle, $extendedVariableOptions);
        ?>
        <article class="tasty-fonts-detail-card tasty-fonts-detail-card--face">
            <div class="tasty-fonts-detail-card-head">
                <div class="tasty-fonts-detail-card-copy">
                    <div class="tasty-fonts-detail-card-title-row">
                        <h5 class="tasty-fonts-detail-card-title"><?php echo esc_html($faceTitle); ?></h5>
                        <span class="tasty-fonts-badge"><?php echo esc_html($this->buildFamilySourceLabel($faceSource)); ?></span>
                    </div>
                    <p class="tasty-fonts-detail-card-summary"><?php echo esc_html($faceStorageSummary); ?></p>
                </div>
                <div class="tasty-fonts-detail-actions">
                    <form method="post" class="tasty-fonts-delete-form tasty-fonts-delete-form--variant">
                        <?php wp_nonce_field('tasty_fonts_delete_variant'); ?>
                        <input type="hidden" name="tasty_fonts_delete_variant" value="1">
                        <input type="hidden" name="tasty_fonts_family_slug" value="<?php echo esc_attr($familySlug); ?>">
                        <input type="hidden" name="tasty_fonts_face_weight" value="<?php echo esc_attr($faceWeight); ?>">
                        <input type="hidden" name="tasty_fonts_face_style" value="<?php echo esc_attr($faceStyle); ?>">
                        <input type="hidden" name="tasty_fonts_face_source" value="<?php echo esc_attr($faceSource); ?>">
                        <input type="hidden" name="tasty_fonts_face_unicode_range" value="<?php echo esc_attr($faceUnicodeRange); ?>">
                        <button
                            type="submit"
                            class="button button-small tasty-fonts-button-danger <?php echo $deleteVariantBlockedMessage !== '' || !$canDeleteVariant ? 'is-disabled' : ''; ?>"
                            data-delete-variant="1"
                            data-delete-family-name="<?php echo esc_attr($familyName); ?>"
                            data-delete-face-weight="<?php echo esc_attr($faceWeight); ?>"
                            data-delete-face-style="<?php echo esc_attr($faceStyle); ?>"
                            aria-disabled="<?php echo esc_attr($deleteVariantBlockedMessage !== '' || !$canDeleteVariant ? 'true' : 'false'); ?>"
                            title="<?php echo esc_attr($deleteVariantBlockedMessage !== '' ? $deleteVariantBlockedMessage : ($canDeleteVariant ? __('Delete this variant from the active delivery and keep the rest of the family.', 'tasty-fonts') : __('Adobe-hosted variants are managed by Adobe Fonts and cannot be deleted individually here.', 'tasty-fonts'))); ?>"
                            <?php if ($deleteVariantBlockedMessage !== '' || !$canDeleteVariant) : ?>
                                data-delete-blocked="<?php echo esc_attr($deleteVariantBlockedMessage !== '' ? $deleteVariantBlockedMessage : __('Adobe-hosted variants are managed by Adobe Fonts and cannot be deleted individually here.', 'tasty-fonts')); ?>"
                            <?php endif; ?>
                        >
                            <?php esc_html_e('Delete Variant', 'tasty-fonts'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <div
                class="tasty-fonts-face-preview <?php echo $isMonospace ? 'is-monospace' : ''; ?>"
                data-font-preview-family="<?php echo esc_attr($familyName); ?>"
                style="font-family:<?php echo esc_attr($defaultStack); ?>; font-weight:<?php echo esc_attr($faceWeight); ?>; font-style:<?php echo esc_attr($faceStyle); ?>;"
            ><?php echo esc_html($facePreviewText); ?></div>

            <dl class="tasty-fonts-detail-meta">
                <div class="tasty-fonts-detail-meta-item">
                    <dt><?php esc_html_e('Style', 'tasty-fonts'); ?></dt>
                    <dd><?php echo esc_html(ucfirst(FontUtils::normalizeStyle($faceStyle))); ?></dd>
                </div>
                <div class="tasty-fonts-detail-meta-item">
                    <dt><?php esc_html_e('Storage', 'tasty-fonts'); ?></dt>
                    <dd><?php echo esc_html($faceStorageSummary); ?></dd>
                </div>
                <div class="tasty-fonts-detail-meta-item">
                    <dt><?php esc_html_e('Formats', 'tasty-fonts'); ?></dt>
                    <dd class="tasty-fonts-detail-chip-row">
                        <?php foreach ($formats as $format): ?>
                            <span class="tasty-fonts-chip"><?php echo esc_html(strtoupper((string) $format)); ?></span>
                        <?php endforeach; ?>
                    </dd>
                </div>
            </dl>

            <div class="tasty-fonts-detail-files tasty-fonts-detail-files--css">
                <span class="tasty-fonts-detail-files-label"><?php esc_html_e('CSS', 'tasty-fonts'); ?></span>
                <div class="tasty-fonts-role-stacks tasty-fonts-role-stacks--selection">
                    <?php $this->renderFaceVariableCopyPill(__('Family', 'tasty-fonts'), (string) ($faceCssSnippets['family'] ?? ''), __('Family CSS copied.', 'tasty-fonts')); ?>
                    <?php $this->renderFaceVariableCopyPill(__('Weight', 'tasty-fonts'), (string) ($faceCssSnippets['weight'] ?? ''), __('Weight CSS copied.', 'tasty-fonts')); ?>
                    <?php $this->renderFaceVariableCopyPill(__('Style', 'tasty-fonts'), (string) ($faceCssSnippets['style'] ?? ''), __('Style CSS copied.', 'tasty-fonts')); ?>
                    <?php $this->renderFaceVariableCopyPill(__('Snippet', 'tasty-fonts'), (string) ($faceCssSnippets['snippet'] ?? ''), __('CSS snippet copied.', 'tasty-fonts')); ?>
                </div>
            </div>

            <?php if ($paths !== []): ?>
                <div class="tasty-fonts-detail-files tasty-fonts-detail-files--paths">
                    <span class="tasty-fonts-detail-files-label"><?php esc_html_e('Files', 'tasty-fonts'); ?></span>
                    <div class="tasty-fonts-detail-file-list">
                        <?php foreach ($paths as $format => $path): ?>
                            <div class="tasty-fonts-file-path">
                                <strong><?php echo esc_html(strtoupper((string) $format)); ?>:</strong>
                                <div class="tasty-fonts-code"><?php echo esc_html(FontUtils::compactRelativePath((string) $path)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    public function buildFamilyFaceSummaryLabels(array $faces): array
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

    public function buildFaceTitle(string $weight, string $style): string
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

    public function canDeleteFaceVariant(array $activeDelivery): bool
    {
        $provider = strtolower(trim((string) ($activeDelivery['provider'] ?? '')));
        $type = strtolower(trim((string) ($activeDelivery['type'] ?? '')));

        return $activeDelivery !== [] && $provider !== 'adobe' && $type !== 'adobe_hosted';
    }

    public function buildWeightLabel(string $weight): string
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

    public function buildFacePreviewText(
        string $previewText,
        string $familyName = '',
        bool $isMonospace = false,
        bool $multiline = false
    ): string
    {
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

    public function buildMonospacePreviewText(string $familyName, bool $multiline = false): string
    {
        $familyName = trim($familyName) !== '' ? trim($familyName) : 'Monospace';
        $literal = str_replace(['\\', '"'], ['\\\\', '\\"'], $familyName);

        return sprintf('const font = "%s";', $literal);
    }

    public function buildFaceStorageSummary(array $face): string
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

    public function renderUploadFamilyGroup(): void
    {
        ?>
        <section class="tasty-fonts-upload-group" data-upload-group>
            <div class="tasty-fonts-upload-group-head">
                <div class="tasty-fonts-upload-group-fields">
                    <label class="tasty-fonts-stack-field">
                        <?php $this->renderFieldLabel(__('Family Name', 'tasty-fonts')); ?>
                        <input
                            type="text"
                            class="regular-text"
                            data-upload-group-field="family"
                            placeholder="<?php esc_attr_e('Example: Satoshi', 'tasty-fonts'); ?>"
                        >
                    </label>

                    <label class="tasty-fonts-stack-field">
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

            <div class="tasty-fonts-upload-face-shell">
                <div class="tasty-fonts-upload-face-headings" aria-hidden="true">
                    <span><?php esc_html_e('Font File', 'tasty-fonts'); ?></span>
                    <span><?php esc_html_e('Weight', 'tasty-fonts'); ?></span>
                    <span><?php esc_html_e('Style', 'tasty-fonts'); ?></span>
                    <span><?php esc_html_e('Action', 'tasty-fonts'); ?></span>
                </div>

                <div class="tasty-fonts-upload-face-list" data-upload-face-list>
                    <?php $this->renderUploadFaceRow(); ?>
                </div>
            </div>

            <div class="tasty-fonts-upload-group-actions">
                <button type="button" class="button" data-upload-add-face><?php esc_html_e('Add Face', 'tasty-fonts'); ?></button>
            </div>
        </section>
        <?php
    }

    public function renderUploadFaceRow(): void
    {
        ?>
        <div class="tasty-fonts-upload-face-row" data-upload-row>
            <div class="tasty-fonts-upload-face-grid">
                <label class="tasty-fonts-stack-field tasty-fonts-upload-file-field">
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

                <label class="tasty-fonts-stack-field">
                    <span class="screen-reader-text"><?php esc_html_e('Weight', 'tasty-fonts'); ?></span>
                    <select data-upload-field="weight">
                        <?php foreach (range(100, 900, 100) as $weight): ?>
                            <option value="<?php echo esc_attr((string) $weight); ?>" <?php selected((string) $weight, '400'); ?>><?php echo esc_html((string) $weight); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="tasty-fonts-stack-field">
                    <span class="screen-reader-text"><?php esc_html_e('Style', 'tasty-fonts'); ?></span>
                    <select data-upload-field="style">
                        <option value="normal"><?php esc_html_e('Normal', 'tasty-fonts'); ?></option>
                        <option value="italic"><?php esc_html_e('Italic', 'tasty-fonts'); ?></option>
                        <option value="oblique"><?php esc_html_e('Oblique', 'tasty-fonts'); ?></option>
                    </select>
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
                <button type="button" class="button tasty-fonts-upload-detected" data-upload-detected-apply hidden></button>
                <div class="tasty-fonts-upload-row-status" data-upload-row-status></div>
            </div>
        </div>
        <?php
    }

    public function renderPreviewScene(string $key, string $previewText, array $roles, bool $monospaceRoleEnabled = false): void
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
                                <strong class="tasty-fonts-preview-specimen-key-value" data-role-preview="heading" data-role-preview-name="heading"><?php echo esc_html($this->previewRoleName('heading', $roles)); ?></strong>
                            </div>
                            <div class="tasty-fonts-preview-specimen-key">
                                <span class="tasty-fonts-preview-specimen-key-label"><?php esc_html_e('Body Family', 'tasty-fonts'); ?></span>
                                <strong class="tasty-fonts-preview-specimen-key-value" data-role-preview="body" data-role-preview-name="body"><?php echo esc_html($this->previewRoleName('body', $roles)); ?></strong>
                            </div>
                            <?php if ($monospaceRoleEnabled): ?>
                                <div class="tasty-fonts-preview-specimen-key">
                                    <span class="tasty-fonts-preview-specimen-key-label"><?php esc_html_e('Monospace', 'tasty-fonts'); ?></span>
                                    <strong class="tasty-fonts-preview-specimen-key-value" data-role-preview="monospace" data-role-preview-name="monospace"><?php echo esc_html($this->previewRoleName('monospace', $roles)); ?></strong>
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
                            <h3 class="tasty-fonts-preview-support-title" data-role-preview="heading"><?php esc_html_e('A type pairing that feels intentional at every scale', 'tasty-fonts'); ?></h3>
                            <p class="tasty-fonts-preview-support-copy" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                            <div class="tasty-fonts-preview-support-meta">
                                <span><?php esc_html_e('Landing Page', 'tasty-fonts'); ?></span>
                                <strong data-role-preview="heading"><?php esc_html_e('Ready', 'tasty-fonts'); ?></strong>
                            </div>
                        </article>

                        <article class="tasty-fonts-preview-support-card">
                            <span class="tasty-fonts-preview-support-label" data-role-preview="body"><?php esc_html_e('Feature Module', 'tasty-fonts'); ?></span>
                            <h3 class="tasty-fonts-preview-support-title" data-role-preview="heading"><?php esc_html_e('Clean cards with enough contrast for product copy', 'tasty-fonts'); ?></h3>
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
                                <p class="tasty-fonts-preview-card-subtitle" data-role-preview="body"><?php esc_html_e('Structured and calm', 'tasty-fonts'); ?></p>
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
                                <h3 class="tasty-fonts-preview-card-title" data-role-preview="heading"><?php esc_html_e('System-ready', 'tasty-fonts'); ?></h3>
                                <p class="tasty-fonts-preview-card-subtitle" data-role-preview="body"><?php esc_html_e('Useful in real UI', 'tasty-fonts'); ?></p>
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
                        <h3 class="tasty-fonts-preview-reading-title" data-role-preview="heading"><?php esc_html_e('Readable paragraphs with steady rhythm', 'tasty-fonts'); ?></h3>
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
                $this->renderCodePreviewScene($previewText, $roles, $monospaceRoleEnabled);
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

    public function previewRoleName(string $roleKey, array $roles): string
    {
        $familyName = trim((string) ($roles[$roleKey] ?? ''));

        if ($familyName !== '') {
            return $familyName;
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
        array $availableFamilies,
        array $previewRoles,
        array $draftRoles,
        bool $allowFallbackOnly = false
    ): void {
        $selectedFamily = trim((string) ($previewRoles[$roleKey] ?? ''));
        $draftFamily = trim((string) ($draftRoles[$roleKey] ?? ''));
        $fallbackValue = match ($roleKey) {
            'heading' => (string) ($previewRoles['heading_fallback'] ?? 'sans-serif'),
            'body' => (string) ($previewRoles['body_fallback'] ?? 'sans-serif'),
            default => (string) ($previewRoles['monospace_fallback'] ?? 'monospace'),
        };
        ?>
        <label class="tasty-fonts-stack-field tasty-fonts-preview-tray-field">
            <?php $this->renderFieldLabel($label); ?>
            <select
                data-preview-role-select="<?php echo esc_attr($roleKey); ?>"
                data-preview-draft-family="<?php echo esc_attr($draftFamily); ?>"
                data-preview-fallback="<?php echo esc_attr($fallbackValue); ?>"
            >
                <?php if ($allowFallbackOnly): ?>
                    <option value="" <?php selected($selectedFamily, ''); ?>><?php esc_html_e('Use fallback only', 'tasty-fonts'); ?></option>
                <?php endif; ?>
                <?php foreach ($availableFamilies as $familyName): ?>
                    <option value="<?php echo esc_attr((string) $familyName); ?>" <?php selected($selectedFamily, $familyName); ?>><?php echo esc_html((string) $familyName); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php
    }

    public function renderCodePreviewScene(string $previewText, array $roles, bool $monospaceRoleEnabled): void
    {
        $editorPreviewHeadingId = 'tasty-fonts-preview-code-editor-heading';
        $blockPreviewHeadingId = 'tasty-fonts-preview-code-block-heading';
        ?>
        <div class="tasty-fonts-preview-code-workspace">
            <aside class="tasty-fonts-preview-code-overview">
                <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Code Preview', 'tasty-fonts'); ?></span>
                <h3 class="tasty-fonts-preview-code-title" data-role-preview="heading"><?php esc_html_e('Inspect how your code reads in an editor and published block', 'tasty-fonts'); ?></h3>
                <p class="tasty-fonts-preview-code-copy" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                <div class="tasty-fonts-preview-code-meta">
                    <div class="tasty-fonts-preview-code-meta-item">
                        <span class="tasty-fonts-preview-code-meta-label"><?php esc_html_e('Code Face', 'tasty-fonts'); ?></span>
                        <strong class="tasty-fonts-preview-code-meta-value" data-role-preview="monospace" data-role-preview-name="monospace"><?php echo esc_html($this->previewRoleName('monospace', $roles)); ?></strong>
                    </div>
                    <div class="tasty-fonts-preview-code-meta-item">
                        <span class="tasty-fonts-preview-code-meta-label"><?php esc_html_e('Headings', 'tasty-fonts'); ?></span>
                        <strong class="tasty-fonts-preview-code-meta-value" data-role-preview="heading" data-role-preview-name="heading"><?php echo esc_html($this->previewRoleName('heading', $roles)); ?></strong>
                    </div>
                    <div class="tasty-fonts-preview-code-meta-item">
                        <span class="tasty-fonts-preview-code-meta-label"><?php esc_html_e('Annotations', 'tasty-fonts'); ?></span>
                        <strong class="tasty-fonts-preview-code-meta-value" data-role-preview="body" data-role-preview-name="body"><?php echo esc_html($this->previewRoleName('body', $roles)); ?></strong>
                    </div>
                </div>
                <div class="tasty-fonts-preview-code-inline">
                    <span class="tasty-fonts-preview-code-inline-label" data-role-preview="body"><?php esc_html_e('Inline token', 'tasty-fonts'); ?></span>
                    <code class="tasty-fonts-preview-code-inline-sample" data-role-preview="monospace">var(--font-monospace)</code>
                </div>
                <div class="tasty-fonts-preview-code-chip-row">
                    <span class="tasty-fonts-preview-code-chip"><?php echo esc_html($monospaceRoleEnabled ? __('Monospace role enabled', 'tasty-fonts') : __('Fallback stack preview', 'tasty-fonts')); ?></span>
                    <span class="tasty-fonts-preview-code-chip"><?php esc_html_e('Syntax highlighting', 'tasty-fonts'); ?></span>
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
                            <h4 id="<?php echo esc_attr($blockPreviewHeadingId); ?>" class="tasty-fonts-preview-code-block-title" data-role-preview="heading"><?php esc_html_e('Front-end snippet with readable line height and punctuation', 'tasty-fonts'); ?></h4>
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

    public function renderHelpTip(string $copy, string $label = ''): void
    {
        $copy = trim($copy);
        $label = trim($label);

        if ($this->trainingWheelsOff || $copy === '') {
            return;
        }

        $ariaLabel = $label !== ''
            ? sprintf(__('More information about %s', 'tasty-fonts'), $label)
            : __('More information', 'tasty-fonts');
        ?>
        <span class="tasty-fonts-help-wrap">
            <button
                type="button"
                class="tasty-fonts-help-button"
                data-help-tooltip="<?php echo esc_attr($copy); ?>"
                aria-label="<?php echo esc_attr($ariaLabel); ?>"
                aria-controls="tasty-fonts-help-tooltip-layer"
                aria-expanded="false"
            >
                <span class="tasty-fonts-help-glyph" aria-hidden="true">
                    <svg viewBox="0 0 16 16" focusable="false" aria-hidden="true">
                        <circle cx="8" cy="8" r="6.25"></circle>
                        <path d="M6.6 6.2a1.8 1.8 0 0 1 3.1 1.3c0 1.3-1.3 1.7-1.7 2.5"></path>
                        <circle class="tasty-fonts-help-glyph-dot" cx="8" cy="11.45" r="0.8"></circle>
                    </svg>
                </span>
            </button>
        </span>
        <?php
    }

    public function buildLibraryCategoryOptions(): array
    {
        return [
            ['value' => 'all', 'label' => __('All Types', 'tasty-fonts')],
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

    public function renderPassiveHelpAttributes(string $copy): void
    {
        $copy = trim($copy);

        if ($this->trainingWheelsOff || $copy === '') {
            return;
        }

        echo ' data-help-tooltip="' . esc_attr($copy) . '"';
        echo ' data-help-passive="1"';
        echo ' title="' . esc_attr($copy) . '"';
    }

    public function renderSectionHeading(string $tag, string $title, string $help, string $copy = ''): void
    {
        ?>
        <div class="tasty-fonts-section-heading">
            <div class="tasty-fonts-section-title-row">
                <<?php echo esc_html($tag); ?> class="tasty-fonts-section-title"><?php echo esc_html($title); ?></<?php echo esc_html($tag); ?>>
                <?php if ($help !== '') : ?>
                    <?php $this->renderHelpTip($help, $title); ?>
                <?php endif; ?>
            </div>
            <?php if ($copy !== ''): ?>
                <p class="tasty-fonts-section-copy"><?php echo esc_html($copy); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function renderFieldLabel(string $label, string $help = ''): void
    {
        ?>
        <span class="tasty-fonts-field-label-row">
            <span class="tasty-fonts-field-label-text"><?php echo esc_html($label); ?></span>
            <?php if ($help !== ''): ?>
                <?php $this->renderHelpTip($help, $label); ?>
            <?php endif; ?>
        </span>
        <?php
    }

    public function renderCodeEditor(array $panel, array $options = []): void
    {
        $label = (string) ($panel['label'] ?? '');
        $target = (string) ($panel['target'] ?? '');
        $headingId = $target !== '' ? $this->buildElementId($target . '-label', 'tasty-fonts-code-panel-label') : 'tasty-fonts-code-panel-label';
        $value = (string) ($panel['value'] ?? '');
        $preserveDisplayFormat = !empty($options['preserve_display_format']);
        $displayValue = $preserveDisplayFormat ? $value : $this->formatSnippetForDisplay($value);
        $readableDisplayValue = $this->formatSnippetForDisplay($value);
        $canToggleReadableView = !empty($options['allow_readable_toggle'])
            && $this->looksLikeCssSnippet(trim($value))
            && $readableDisplayValue !== $displayValue;
        $readableTarget = $target !== '' ? $target . '-readable' : '';
        ?>
        <div class="tasty-fonts-code-panel-head">
            <span id="<?php echo esc_attr($headingId); ?>"><?php echo esc_html($label); ?></span>
            <div class="tasty-fonts-code-panel-actions">
                <?php if ($canToggleReadableView): ?>
                    <button
                        type="button"
                        class="button tasty-fonts-output-display-toggle"
                        data-snippet-display-toggle
                        data-label-default="<?php esc_attr_e('Readable preview', 'tasty-fonts'); ?>"
                        data-label-active="<?php esc_attr_e('Show actual output', 'tasty-fonts'); ?>"
                        aria-pressed="false"
                        aria-controls="<?php echo esc_attr(trim($target . ' ' . $readableTarget)); ?>"
                    >
                        <?php esc_html_e('Readable preview', 'tasty-fonts'); ?>
                    </button>
                <?php endif; ?>
                <button
                    type="button"
                    class="button tasty-fonts-output-copy-button"
                    data-copy-text="<?php echo esc_attr($value); ?>"
                    data-copy-static-label="1"
                    data-copy-success="<?php esc_attr_e('Snippet copied.', 'tasty-fonts'); ?>"
                    aria-label="<?php echo esc_attr(sprintf(__('Copy %s snippet', 'tasty-fonts'), $label)); ?>"
                ></button>
            </div>
        </div>
        <div class="tasty-fonts-code-panel-body" data-snippet-display aria-labelledby="<?php echo esc_attr($headingId); ?>">
            <pre class="tasty-fonts-output" data-snippet-view="raw" aria-labelledby="<?php echo esc_attr($headingId); ?>"><code id="<?php echo esc_attr($target); ?>" class="tasty-fonts-output-code"><?php $this->renderHighlightedSnippet($displayValue); ?></code></pre>
            <?php if ($canToggleReadableView): ?>
                <pre class="tasty-fonts-output" data-snippet-view="readable" aria-labelledby="<?php echo esc_attr($headingId); ?>" hidden><code id="<?php echo esc_attr($readableTarget); ?>" class="tasty-fonts-output-code"><?php $this->renderHighlightedSnippet($readableDisplayValue); ?></code></pre>
            <?php endif; ?>
        </div>
        <?php
    }

    public function buildElementId(string $value, string $fallback): string
    {
        $sanitized = strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value) ?? '', '-'));

        return $sanitized !== '' ? $sanitized : $fallback;
    }

    public function formatSnippetForDisplay(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '' || preg_match("/\r\n|\n|\r/", $trimmed) === 1 || !$this->looksLikeCssSnippet($trimmed)) {
            return $value;
        }

        return $this->prettyPrintCssSnippet($trimmed);
    }

    public function looksLikeCssSnippet(string $value): bool
    {
        return str_contains($value, '{')
            && str_contains($value, '}')
            && str_contains($value, ':');
    }

    public function prettyPrintCssSnippet(string $value): string
    {
        $lines = [];
        $current = '';
        $indentLevel = 0;
        $quote = null;
        $escapeNext = false;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];
            $next = $index + 1 < $length ? $value[$index + 1] : null;

            if ($quote !== null) {
                $current .= $character;

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

            if ($character === '"' || $character === '\'') {
                $quote = $character;
                $current .= $character;
                continue;
            }

            if ($character === '/' && $next === '*') {
                $commentEnd = strpos($value, '*/', $index + 2);

                if ($commentEnd === false) {
                    $current .= substr($value, $index);
                    break;
                }

                $this->appendFormattedCssLine($lines, $current, $indentLevel);
                $comment = trim(substr($value, $index, ($commentEnd - $index) + 2));

                if ($comment !== '') {
                    $lines[] = str_repeat('  ', $indentLevel) . $comment;
                }

                $current = '';
                $index = $commentEnd + 1;
                continue;
            }

            if (preg_match('/\s/', $character) === 1) {
                if ($current !== '' && !preg_match('/\s$/', $current)) {
                    $current .= ' ';
                }

                continue;
            }

            if ($character === '{') {
                $selector = trim($current);
                $line = $selector !== '' ? $selector . ' {' : '{';
                $lines[] = str_repeat('  ', $indentLevel) . $line;
                $current = '';
                $indentLevel++;
                continue;
            }

            if ($character === ';') {
                $current .= ';';
                $this->appendFormattedCssLine($lines, $current, $indentLevel);
                $current = '';
                continue;
            }

            if ($character === '}') {
                $this->appendFormattedCssLine($lines, $current, $indentLevel);
                $current = '';
                $indentLevel = max(0, $indentLevel - 1);
                $lines[] = str_repeat('  ', $indentLevel) . '}';
                continue;
            }

            $current .= $character;
        }

        $this->appendFormattedCssLine($lines, $current, $indentLevel);

        return implode("\n", $lines);
    }

    public function appendFormattedCssLine(array &$lines, string $line, int $indentLevel): void
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return;
        }

        $lines[] = str_repeat('  ', $indentLevel) . $trimmed;
    }

    public function renderHighlightedSnippet(string $value): void
    {
        $lines = preg_split("/\r\n|\n|\r/", $value);

        if ($lines === false) {
            echo esc_html($value);

            return;
        }

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                echo "\n";
            }

            echo $this->highlightSnippetLine($line);
        }
    }

    public function highlightSnippetLine(string $line): string
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^\s*(\/\*.*\*\/|\*\/|\*)/', $line) === 1) {
            return '<span class="tasty-fonts-syntax-comment">' . esc_html($line) . '</span>';
        }

        if (preg_match('/^(\s*)(@[\w-]+)(\s+[^{};]+)?(\s*\{?\s*;?\s*)$/', $line, $matches) === 1) {
            return esc_html($matches[1])
                . '<span class="tasty-fonts-syntax-at-rule">' . esc_html($matches[2]) . '</span>'
                . $this->highlightSnippetValue((string) ($matches[3] ?? ''))
                . '<span class="tasty-fonts-syntax-punctuation">' . esc_html((string) ($matches[4] ?? '')) . '</span>';
        }

        if (preg_match('/^(\s*)([^{}]+)(\s*\{\s*)$/', $line, $matches) === 1) {
            return esc_html($matches[1])
                . '<span class="tasty-fonts-syntax-selector">' . esc_html(trim((string) $matches[2])) . '</span>'
                . '<span class="tasty-fonts-syntax-punctuation">' . esc_html((string) $matches[3]) . '</span>';
        }

        if (preg_match('/^(\s*)(--[\w-]+|[\w-]+)(\s*:\s*)(.+?)(\s*[;,]?\s*)$/', $line, $matches) === 1) {
            return esc_html($matches[1])
                . '<span class="tasty-fonts-syntax-property">' . esc_html($matches[2]) . '</span>'
                . '<span class="tasty-fonts-syntax-punctuation">' . esc_html($matches[3]) . '</span>'
                . $this->highlightSnippetValue($matches[4])
                . '<span class="tasty-fonts-syntax-punctuation">' . esc_html((string) $matches[5]) . '</span>';
        }

        if (preg_match('/^(\s*)([{}])(\s*)$/', $line, $matches) === 1) {
            return esc_html($matches[1])
                . '<span class="tasty-fonts-syntax-punctuation">' . esc_html($matches[2]) . '</span>'
                . esc_html((string) $matches[3]);
        }

        return esc_html($line);
    }

    public function highlightSnippetValue(string $value): string
    {
        $parts = preg_split('/(".*?"|\'.*?\')/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return esc_html($value);
        }

        $highlighted = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (($part[0] === '"' && str_ends_with($part, '"')) || ($part[0] === '\'' && str_ends_with($part, '\''))) {
                $highlighted .= '<span class="tasty-fonts-syntax-string">' . esc_html($part) . '</span>';
                continue;
            }

            $escaped = esc_html($part);
            $escaped = preg_replace('/(var\()(--[\w-]+)(\))/', '<span class="tasty-fonts-syntax-function">$1</span><span class="tasty-fonts-syntax-variable">$2</span><span class="tasty-fonts-syntax-punctuation">$3</span>', $escaped);
            $escaped = preg_replace('/(?<![\w-])(--[\w-]+)/', '<span class="tasty-fonts-syntax-variable">$1</span>', (string) $escaped);
            $escaped = preg_replace('/(?<![\w-])(#[0-9a-fA-F]{3,8})/', '<span class="tasty-fonts-syntax-number">$1</span>', (string) $escaped);
            $escaped = preg_replace('/(?<![\w-])(\d+(?:\.\d+)?(?:px|rem|em|vh|vw|%|fr|ms|s)?)/', '<span class="tasty-fonts-syntax-number">$1</span>', (string) $escaped);
            $escaped = preg_replace('/(?<![\w-])(optional|swap|fallback|block|auto|none|normal|italic|inherit|initial|unset|serif|sans-serif|monospace)(?![\w-])/i', '<span class="tasty-fonts-syntax-keyword">$1</span>', (string) $escaped);

            $highlighted .= (string) $escaped;
        }

        return $highlighted;
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
