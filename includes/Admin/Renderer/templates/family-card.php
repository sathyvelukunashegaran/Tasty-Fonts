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
                                    data-family-details-toggle
                                    data-family-slug="<?php echo esc_attr($familySlug); ?>"
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

            <div
                id="<?php echo esc_attr($detailsId); ?>"
                class="tasty-fonts-family-details"
                data-family-details
                data-family-slug="<?php echo esc_attr($familySlug); ?>"
                data-family-details-loaded="<?php echo !empty($includeDetails) ? 'true' : 'false'; ?>"
                aria-live="polite"
                <?php echo $isExpanded ? '' : 'hidden'; ?>
            >
                <?php if (!empty($includeDetails)): ?>
                    <?php require __DIR__ . '/family-card-details.php'; ?>
                <?php else: ?>
                    <div class="tasty-fonts-family-details-status" data-family-details-status role="status" hidden></div>
                <?php endif; ?>
            </div>
        </article>
