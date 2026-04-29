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
                                        <div
                                            class="tasty-fonts-font-usage"
                                            data-role-usage-summary
                                            aria-label="<?php echo esc_attr__('Font role usage', 'tasty-fonts'); ?>"
                                            <?php echo ($assignedRoleKeys === [] && $liveRoleKeys === []) ? 'hidden' : ''; ?>
                                        >
                                            <span class="tasty-fonts-badge is-role tasty-fonts-font-usage-chip" data-role-usage-chip="heading" data-role-usage-state="live" <?php echo $isLiveHeading ? '' : 'hidden'; ?>><?php esc_html_e('Heading Live', 'tasty-fonts'); ?></span>
                                            <span class="tasty-fonts-badge tasty-fonts-font-usage-chip" data-role-usage-chip="heading" data-role-usage-state="draft" <?php echo ($isHeading && !$isLiveHeading) ? '' : 'hidden'; ?>><?php esc_html_e('Heading Role', 'tasty-fonts'); ?></span>
                                            <span class="tasty-fonts-badge is-role tasty-fonts-font-usage-chip" data-role-usage-chip="body" data-role-usage-state="live" <?php echo $isLiveBody ? '' : 'hidden'; ?>><?php esc_html_e('Body Live', 'tasty-fonts'); ?></span>
                                            <span class="tasty-fonts-badge tasty-fonts-font-usage-chip" data-role-usage-chip="body" data-role-usage-state="draft" <?php echo ($isBody && !$isLiveBody) ? '' : 'hidden'; ?>><?php esc_html_e('Body Role', 'tasty-fonts'); ?></span>
                                            <?php if ($monospaceRoleEnabled): ?>
                                                <span class="tasty-fonts-badge is-role tasty-fonts-font-usage-chip" data-role-usage-chip="monospace" data-role-usage-state="live" <?php echo $isLiveMonospace ? '' : 'hidden'; ?>><?php esc_html_e('Monospace Live', 'tasty-fonts'); ?></span>
                                                <span class="tasty-fonts-badge tasty-fonts-font-usage-chip" data-role-usage-chip="monospace" data-role-usage-state="draft" <?php echo ($isMonospace && !$isLiveMonospace) ? '' : 'hidden'; ?>><?php esc_html_e('Monospace Role', 'tasty-fonts'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <span
                                            class="tasty-fonts-badge tasty-fonts-badge--help tasty-fonts-font-source-badge"
                                            data-help-tooltip="<?php echo esc_attr($activeDeliveryLabel); ?>"
                                            data-help-passive="1"
                                            data-help-persistent="1"
                                            title="<?php echo esc_attr($activeDeliveryLabel); ?>"
                                            tabindex="0"
                                            aria-label="<?php echo esc_attr($activeDeliveryLabel); ?>"
                                        ><?php echo esc_html($activeDeliveryLabel); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="tasty-fonts-font-inline-preview <?php echo $usesMonospacePreview ? 'is-monospace' : ''; ?>" role="group" aria-label="<?php echo esc_attr(sprintf(__('Preview for %s', 'tasty-fonts'), $familyName)); ?>">
                                <span class="tasty-fonts-font-inline-preview-label"><?php echo esc_html($previewLabel); ?></span>
                                <div
                                    class="tasty-fonts-font-inline-preview-text <?php echo $usesMonospacePreview ? 'is-monospace' : ''; ?>"
                                    data-font-preview-family="<?php echo esc_attr($familyName); ?>"
                                    style="font-family:<?php echo esc_attr($defaultStack); ?>;"
                                ><?php echo esc_html($inlinePreviewText); ?></div>
                            </div>
                            <div class="tasty-fonts-font-specimen <?php echo $usesMonospacePreview ? 'is-monospace' : ''; ?>" role="group" aria-label="<?php echo esc_attr(sprintf(__('Preview for %s', 'tasty-fonts'), $familyName)); ?>">
                                <span class="tasty-fonts-font-specimen-label"><?php echo esc_html($previewLabel); ?></span>
                                <div
                                    class="tasty-fonts-font-specimen-display <?php echo $usesMonospacePreview ? 'is-monospace' : ''; ?>"
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
                                    data-family-edit-action="role"
                                    data-font-family="<?php echo esc_attr($familyName); ?>"
                                    data-active-label="<?php echo esc_attr__('Heading selected', 'tasty-fonts'); ?>"
                                    data-idle-label="<?php echo esc_attr__('Select heading', 'tasty-fonts'); ?>"
                                    data-active-help="<?php echo esc_attr__('This family is currently selected for the heading role.', 'tasty-fonts'); ?>"
                                    data-idle-help="<?php echo esc_attr__('Assign this family to Heading and save the draft roles.', 'tasty-fonts'); ?>"
                                    <?php $this->renderPassiveHelpAttributes($isHeading ? __('This family is currently selected for the heading role.', 'tasty-fonts') : __('Assign this family to Heading and save the draft roles.', 'tasty-fonts')); ?>
                                    aria-label="<?php echo esc_attr($isHeading ? __('Heading selected', 'tasty-fonts') : __('Select heading', 'tasty-fonts')); ?>"
                                    aria-pressed="<?php echo esc_attr($isHeading ? 'true' : 'false'); ?>"
                                >
                                    <span class="screen-reader-text tasty-fonts-role-assign-label"><?php echo $isHeading ? esc_html__('Heading selected', 'tasty-fonts') : esc_html__('Select heading', 'tasty-fonts'); ?></span>
                                </button>
                                <button
                                    type="button"
                                    class="button tasty-fonts-role-assign-button tasty-fonts-role-assign-button--icon-only tasty-fonts-font-action-button--icon <?php echo $isBody ? 'is-current' : ''; ?>"
                                    data-role-assign="body"
                                    data-family-edit-action="role"
                                    data-font-family="<?php echo esc_attr($familyName); ?>"
                                    data-active-label="<?php echo esc_attr__('Body selected', 'tasty-fonts'); ?>"
                                    data-idle-label="<?php echo esc_attr__('Select body', 'tasty-fonts'); ?>"
                                    data-active-help="<?php echo esc_attr__('This family is currently selected for the body role.', 'tasty-fonts'); ?>"
                                    data-idle-help="<?php echo esc_attr__('Assign this family to Body and save the draft roles.', 'tasty-fonts'); ?>"
                                    <?php $this->renderPassiveHelpAttributes($isBody ? __('This family is currently selected for the body role.', 'tasty-fonts') : __('Assign this family to Body and save the draft roles.', 'tasty-fonts')); ?>
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
                                        data-family-edit-action="role"
                                        data-font-family="<?php echo esc_attr($familyName); ?>"
                                        data-active-label="<?php echo esc_attr__('Monospace selected', 'tasty-fonts'); ?>"
                                        data-idle-label="<?php echo esc_attr__('Select monospace', 'tasty-fonts'); ?>"
                                        data-active-help="<?php echo esc_attr__('This family is currently selected for the monospace role.', 'tasty-fonts'); ?>"
                                        data-idle-help="<?php echo esc_attr__('Assign this family to Monospace and save the draft roles.', 'tasty-fonts'); ?>"
                                        <?php $this->renderPassiveHelpAttributes($isMonospace ? __('This family is currently selected for the monospace role.', 'tasty-fonts') : __('Assign this family to Monospace and save the draft roles.', 'tasty-fonts')); ?>
                                        aria-label="<?php echo esc_attr($isMonospace ? __('Monospace selected', 'tasty-fonts') : __('Select monospace', 'tasty-fonts')); ?>"
                                        aria-pressed="<?php echo esc_attr($isMonospace ? 'true' : 'false'); ?>"
                                    >
                                        <span class="screen-reader-text tasty-fonts-role-assign-label"><?php echo $isMonospace ? esc_html__('Monospace selected', 'tasty-fonts') : esc_html__('Select monospace', 'tasty-fonts'); ?></span>
                                    </button>
                                <?php endif; ?>
                                <button
                                    type="button"
                                    class="button tasty-fonts-family-quick-action-button tasty-fonts-family-quick-publish-button tasty-fonts-font-action-button--icon <?php echo !empty($quickPublishAction['enabled']) ? '' : 'is-disabled'; ?>"
                                    data-family-quick-publish
                                    data-family-edit-action="publish-state"
                                    data-family-slug="<?php echo esc_attr($familySlug); ?>"
                                    data-family-name="<?php echo esc_attr($familyName); ?>"
                                    data-current-publish-state="<?php echo esc_attr((string) ($quickPublishAction['current_state'] ?? '')); ?>"
                                    data-target-publish-state="<?php echo esc_attr((string) ($quickPublishAction['target_state'] ?? '')); ?>"
                                    aria-label="<?php echo esc_attr((string) ($quickPublishAction['label'] ?? __('Quick publish unavailable', 'tasty-fonts'))); ?>"
                                    aria-disabled="<?php echo !empty($quickPublishAction['enabled']) ? 'false' : 'true'; ?>"
                                    title="<?php echo esc_attr((string) ($quickPublishAction['help'] ?? '')); ?>"
                                    <?php $this->renderPassiveHelpAttributes((string) (!empty($quickPublishAction['disabled_reason']) ? $quickPublishAction['disabled_reason'] : ($quickPublishAction['help'] ?? ''))); ?>
                                    <?php if (!empty($quickPublishAction['disabled_reason'])) : ?>
                                        data-disabled-reason="<?php echo esc_attr((string) $quickPublishAction['disabled_reason']); ?>"
                                    <?php endif; ?>
                                >
                                    <span class="screen-reader-text"><?php echo esc_html((string) ($quickPublishAction['label'] ?? __('Quick publish unavailable', 'tasty-fonts'))); ?></span>
                                </button>
                                <button
                                    type="button"
                                    class="button tasty-fonts-family-quick-action-button tasty-fonts-family-quick-delivery-button tasty-fonts-font-action-button--icon <?php echo !empty($quickDeliveryAction['enabled']) ? '' : 'is-disabled'; ?>"
                                    data-family-quick-delivery
                                    data-family-edit-action="delivery"
                                    data-family-slug="<?php echo esc_attr($familySlug); ?>"
                                    data-family-name="<?php echo esc_attr($familyName); ?>"
                                    data-current-delivery-id="<?php echo esc_attr($activeDeliveryId); ?>"
                                    data-target-delivery-id="<?php echo esc_attr((string) ($quickDeliveryAction['target_delivery_id'] ?? '')); ?>"
                                    data-target-delivery-label="<?php echo esc_attr((string) ($quickDeliveryAction['target_delivery_label'] ?? '')); ?>"
                                    data-target-delivery-method="<?php echo esc_attr((string) ($quickDeliveryAction['target_delivery_method'] ?? '')); ?>"
                                    aria-label="<?php echo esc_attr((string) ($quickDeliveryAction['label'] ?? __('Quick delivery unavailable', 'tasty-fonts'))); ?>"
                                    aria-disabled="<?php echo !empty($quickDeliveryAction['enabled']) ? 'false' : 'true'; ?>"
                                    title="<?php echo esc_attr((string) ($quickDeliveryAction['help'] ?? '')); ?>"
                                    <?php $this->renderPassiveHelpAttributes((string) (!empty($quickDeliveryAction['disabled_reason']) ? $quickDeliveryAction['disabled_reason'] : ($quickDeliveryAction['help'] ?? ''))); ?>
                                    <?php if (!empty($quickDeliveryAction['disabled_reason'])) : ?>
                                        data-disabled-reason="<?php echo esc_attr((string) $quickDeliveryAction['disabled_reason']); ?>"
                                    <?php endif; ?>
                                >
                                    <span class="screen-reader-text"><?php echo esc_html((string) ($quickDeliveryAction['label'] ?? __('Quick delivery unavailable', 'tasty-fonts'))); ?></span>
                                </button>
                                <button
                                    type="button"
                                    class="button tasty-fonts-disclosure-button tasty-fonts-disclosure-button--card tasty-fonts-font-action-button--details"
                                    data-disclosure-toggle="<?php echo esc_attr($detailsId); ?>"
                                    data-family-details-toggle
                                    data-family-card-primary-action
                                    data-family-slug="<?php echo esc_attr($familySlug); ?>"
                                    data-expanded-label="<?php echo esc_attr__('Details', 'tasty-fonts'); ?>"
                                    data-collapsed-label="<?php echo esc_attr__('Details', 'tasty-fonts'); ?>"
                                    aria-expanded="<?php echo esc_attr($isExpanded ? 'true' : 'false'); ?>"
                                    aria-controls="<?php echo esc_attr($detailsId); ?>"
                                    aria-label="<?php echo esc_attr__('Details', 'tasty-fonts'); ?>"
                                >
                                    <?php esc_html_e('Details', 'tasty-fonts'); ?>
                                </button>
                                <form method="post" class="tasty-fonts-delete-form" data-family-edit-action="delete-family">
                                    <?php wp_nonce_field('tasty_fonts_delete_family'); ?>
                                    <input type="hidden" name="tasty_fonts_delete_family" value="1">
                                    <input type="hidden" name="tasty_fonts_family_slug" value="<?php echo esc_attr($familySlug); ?>">
                                    <button
                                        type="submit"
                                        class="button tasty-fonts-button-danger tasty-fonts-font-action-button--icon <?php echo $isDraftRoleFamily ? 'is-disabled' : ''; ?>"
                                        data-delete-family="<?php echo esc_attr($familyName); ?>"
                                        data-delete-ready-title="<?php echo esc_attr(__('Delete this family and its managed files.', 'tasty-fonts')); ?>"
                                        <?php foreach ($deleteBlockedMessages as $key => $message): ?>
                                            data-delete-blocked-<?php echo esc_attr($key); ?>="<?php echo esc_attr($message); ?>"
                                        <?php endforeach; ?>
                                        <?php $this->renderPassiveHelpAttributes($deleteBlockedMessage !== '' ? $deleteBlockedMessage : __('Delete this family and its managed files.', 'tasty-fonts')); ?>
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
