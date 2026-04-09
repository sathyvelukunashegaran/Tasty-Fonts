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
                    <div class="tasty-fonts-font-card-top tasty-fonts-font-card-overview-main">
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
                                            aria-label="<?php echo esc_attr(sprintf(__('Copy font stack for %1$s: %2$s', 'tasty-fonts'), $familyName, $defaultStack)); ?>"
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
                                        <span class="tasty-fonts-badge <?php echo esc_attr((string) ($fontTypeDescriptor['badge_class'] ?? '')); ?>">
                                            <?php echo esc_html((string) ($fontTypeDescriptor['label'] ?? '')); ?>
                                        </span>
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
                    </div>

                    <div class="tasty-fonts-font-card-sidecolumn">
                        <div class="tasty-fonts-font-sidebar">
                            <div class="tasty-fonts-family-meta">
                                <form method="post" class="tasty-fonts-family-publish-state-form" data-family-publish-state-form>
                                    <div class="tasty-fonts-inline-field-row">
                                        <label class="tasty-fonts-inline-field tasty-fonts-inline-field--select">
                                            <span class="tasty-fonts-field-label-row">
                                                <span class="tasty-fonts-field-label-text"><?php esc_html_e('Runtime State', 'tasty-fonts'); ?></span>
                                                <?php if (!$canChangePublishState): ?>
                                                    <span class="tasty-fonts-field-status tasty-fonts-field-status--auto" title="<?php esc_attr_e('Auto-managed', 'tasty-fonts'); ?>"><?php esc_html_e('Auto', 'tasty-fonts'); ?></span>
                                                <?php endif; ?>
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
                                                <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
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
                                                    <button
                                                        type="button"
                                                        class="tasty-fonts-select-clear"
                                                        data-clear-select-button
                                                        data-clear-value="inherit"
                                                        aria-label="<?php esc_attr_e('Clear font display override', 'tasty-fonts'); ?>"
                                                        hidden
                                                    >
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
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
                                        aria-disabled="<?php echo esc_attr($deleteBlockedMessage !== '' ? 'true' : 'false'); ?>"
                                        <?php disabled($deleteBlockedMessage !== ''); ?>
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

            <div id="<?php echo esc_attr($detailsId); ?>" class="tasty-fonts-family-details" <?php echo $isExpanded ? '' : 'hidden'; ?>>
                <div class="tasty-fonts-family-details-grid">
                    <div class="tasty-fonts-family-details-primary">
                        <section class="tasty-fonts-detail-group tasty-fonts-detail-group--profiles">
                            <div class="tasty-fonts-detail-group-head">
                                <div class="tasty-fonts-detail-group-copy">
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
                    </div>

                    <?php if ($familyCssVariableSnippets !== [] || $familyCssClassSnippets !== []): ?>
                        <div class="tasty-fonts-family-details-utilities">
                            <?php if ($familyCssVariableSnippets !== []): ?>
                                <section class="tasty-fonts-detail-group tasty-fonts-detail-group--variables tasty-fonts-detail-group--utility">
                                    <div class="tasty-fonts-detail-group-head">
                                        <div class="tasty-fonts-detail-group-copy">
                                            <h4><?php esc_html_e('CSS Variables', 'tasty-fonts'); ?></h4>
                                        </div>
                                        <span class="tasty-fonts-badge">
                                            <?php echo esc_html(sprintf(_n('%d variable', '%d variables', count($familyCssVariableSnippets), 'tasty-fonts'), count($familyCssVariableSnippets))); ?>
                                        </span>
                                    </div>
                                    <div class="tasty-fonts-detail-files tasty-fonts-detail-files--utility">
                                        <div class="tasty-fonts-role-stacks tasty-fonts-role-stacks--selection">
                                            <?php foreach ($familyCssVariableSnippets as $label => $snippet) : ?>
                                                <?php $this->renderFaceVariableCopyPill((string) $label, (string) $snippet, __('CSS variable copied.', 'tasty-fonts')); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            <?php endif; ?>

                            <?php if ($familyCssClassSnippets !== []): ?>
                                <section class="tasty-fonts-detail-group tasty-fonts-detail-group--classes tasty-fonts-detail-group--utility">
                                    <div class="tasty-fonts-detail-group-head">
                                        <div class="tasty-fonts-detail-group-copy">
                                            <h4><?php esc_html_e('Font Classes', 'tasty-fonts'); ?></h4>
                                        </div>
                                        <span class="tasty-fonts-badge">
                                            <?php echo esc_html(sprintf(_n('%d class', '%d classes', count($familyCssClassSnippets), 'tasty-fonts'), count($familyCssClassSnippets))); ?>
                                        </span>
                                    </div>
                                    <div class="tasty-fonts-detail-files tasty-fonts-detail-files--utility">
                                        <div class="tasty-fonts-role-stacks tasty-fonts-role-stacks--selection">
                                            <?php foreach ($familyCssClassSnippets as $label => $snippet) : ?>
                                                <?php $this->renderFaceVariableCopyPill((string) $label, (string) $snippet, __('Font class copied.', 'tasty-fonts')); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <section class="tasty-fonts-detail-group tasty-fonts-detail-group--faces">
                        <div class="tasty-fonts-detail-group-head">
                            <div class="tasty-fonts-detail-group-copy">
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
            </div>
        </article>
