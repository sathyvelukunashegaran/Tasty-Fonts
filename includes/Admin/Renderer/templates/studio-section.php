                    <section class="tasty-fonts-card tasty-fonts-studio-card tasty-fonts-top-panel" id="tasty-fonts-roles-studio">
                        <?php $roleFormId = 'tasty-fonts-role-form'; ?>
                        <form method="post" class="tasty-fonts-top-panel-form" data-role-form id="<?php echo esc_attr($roleFormId); ?>">
                            <input type="hidden" name="tasty_fonts_action_type" value="save" data-role-action-type>
                            <?php wp_nonce_field('tasty_fonts_save_roles'); ?>
                            <input type="hidden" name="tasty_fonts_save_roles" value="1">
                        </form>

                            <div class="tasty-fonts-role-toolbar">
                                <div class="tasty-fonts-studio-section tasty-fonts-role-command-deck">
                                    <div class="tasty-fonts-studio-section-summary tasty-fonts-role-command-summary">
                                        <div class="tasty-fonts-studio-section-summary-copy tasty-fonts-role-command-summary-copy">
                                            <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Workflow', 'tasty-fonts'); ?></span>
                                            <h2 class="tasty-fonts-studio-section-title"><?php esc_html_e('Select Roles, Review, Publish.', 'tasty-fonts'); ?></h2>
                                        </div>
                                        <div class="tasty-fonts-role-command-summary-meta">
                                            <div class="tasty-fonts-sitewide-status <?php echo esc_attr($roleDeploymentBadgeClass); ?>" data-role-deployment aria-live="polite" aria-atomic="true">
                                                <div class="tasty-fonts-role-stacks tasty-fonts-role-stacks--summary">
                                                    <span class="tasty-fonts-role-stack tasty-fonts-role-deployment <?php echo esc_attr($roleDeploymentBadgeClass); ?>">
                                                        <span class="tasty-fonts-role-stack-label"><?php esc_html_e('Sitewide delivery', 'tasty-fonts'); ?></span>
                                                        <button
                                                            type="button"
                                                            class="tasty-fonts-pill tasty-fonts-pill--status tasty-fonts-pill--interactive tasty-fonts-pill--help tasty-fonts-role-command-status tasty-fonts-role-status-pill <?php echo esc_attr($roleDeploymentBadgeClass); ?>"
                                                            data-role-deployment-pill
                                                            <?php $this->renderPassiveHelpAttributes($roleDeploymentTooltip, $roleDeploymentAnnouncementId . ' tasty-fonts-help-tooltip-layer'); ?>
                                                            aria-label="<?php esc_attr_e('Sitewide delivery status', 'tasty-fonts'); ?>"
                                                        >
                                                            <span data-role-deployment-badge><?php echo esc_html($roleDeploymentBadge); ?></span>
                                                        </button>
                                                    </span>
                                                </div>
                                                <span id="<?php echo esc_attr($roleDeploymentAnnouncementId); ?>" class="screen-reader-text" data-role-deployment-announcement><?php echo esc_html($roleDeploymentTooltip); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tasty-fonts-studio-card-grid tasty-fonts-role-actions">
                                        <div class="tasty-fonts-studio-card tasty-fonts-role-command-card tasty-fonts-role-command-card--sitewide<?php echo $applyEverywhere ? ' is-live' : ' is-draft'; ?>">
                                            <div class="tasty-fonts-studio-card-head tasty-fonts-role-command-card-head">
                                                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Sitewide', 'tasty-fonts'); ?></span>
                                                    <h3><?php esc_html_e('Sitewide Delivery', 'tasty-fonts'); ?></h3>
                                                </div>
                                            </div>
                                            <p class="tasty-fonts-studio-card-copy tasty-fonts-role-command-card-copy">
                                                <?php
                                                echo esc_html(
                                                    $applyEverywhere
                                                        ? __('Live roles are active across the site, editors, and Etch.', 'tasty-fonts')
                                                        : __('Keep role changes in draft until the pairing is ready.', 'tasty-fonts')
                                                );
                                                ?>
                                            </p>
                                            <div class="tasty-fonts-role-command-actions">
                                                <button
                                                    type="submit"
                                                    class="button button-primary tasty-fonts-scope-button tasty-fonts-scope-button--apply"
                                                    name="tasty_fonts_action_type"
                                                    value="apply"
                                                    form="<?php echo esc_attr($roleFormId); ?>"
                                                    data-role-sitewide-enable
                                                    aria-disabled="<?php echo $applyEverywhere ? 'true' : 'false'; ?>"
                                                    <?php disabled($applyEverywhere); ?>
                                                >
                                                    <span class="tasty-fonts-scope-button-title"><?php esc_html_e('Enable', 'tasty-fonts'); ?></span>
                                                </button>
                                                <button
                                                    type="submit"
                                                    class="button tasty-fonts-scope-button tasty-fonts-scope-button--save"
                                                    name="tasty_fonts_action_type"
                                                    value="disable"
                                                    form="<?php echo esc_attr($roleFormId); ?>"
                                                    data-role-sitewide-disable
                                                    aria-disabled="<?php echo $applyEverywhere ? 'false' : 'true'; ?>"
                                                    <?php disabled(!$applyEverywhere); ?>
                                                >
                                                    <span class="tasty-fonts-scope-button-title"><?php esc_html_e('Disable', 'tasty-fonts'); ?></span>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="tasty-fonts-studio-card tasty-fonts-role-command-card tasty-fonts-role-command-card--actions">
                                            <div class="tasty-fonts-studio-card-head tasty-fonts-role-command-card-head">
                                                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Changes', 'tasty-fonts'); ?></span>
                                                    <h3><?php esc_html_e('Save or Publish', 'tasty-fonts'); ?></h3>
                                                </div>
                                            </div>
                                            <p class="tasty-fonts-studio-card-copy tasty-fonts-role-command-card-copy"><?php esc_html_e('Save drafts while testing. Publish when ready.', 'tasty-fonts'); ?></p>
                                            <div class="tasty-fonts-role-command-actions">
                                                <div
                                                    class="tasty-fonts-role-command-action<?php echo !$hasPendingLiveRoleChanges ? ' has-disabled-reason' : ''; ?>"
                                                    data-role-apply-live-wrap
                                                    tabindex="<?php echo !$hasPendingLiveRoleChanges && !$trainingWheelsOff ? '0' : '-1'; ?>"
                                                    <?php if (!$trainingWheelsOff): ?>
                                                        data-help-tooltip="<?php echo esc_attr($hasPendingLiveRoleChanges ? '' : $applyLiveDisabledCopy); ?>"
                                                        data-help-passive="1"
                                                        aria-label="<?php echo esc_attr($hasPendingLiveRoleChanges ? '' : $applyLiveDisabledCopy); ?>"
                                                    <?php endif; ?>
                                                >
                                                    <button type="submit" class="button<?php echo $hasPendingLiveRoleChanges ? ' button-primary is-pending-live-change' : ''; ?> tasty-fonts-scope-button tasty-fonts-scope-button--apply" name="tasty_fonts_action_type" value="apply" form="<?php echo esc_attr($roleFormId); ?>" data-role-apply-live aria-disabled="<?php echo $hasPendingLiveRoleChanges ? 'false' : 'true'; ?>" <?php disabled(!$hasPendingLiveRoleChanges); ?>>
                                                        <span class="tasty-fonts-scope-button-title"><?php esc_html_e('Publish Roles', 'tasty-fonts'); ?></span>
                                                    </button>
                                                </div>
                                                <div
                                                    class="tasty-fonts-role-command-action has-disabled-reason"
                                                    data-role-save-draft-wrap
                                                    tabindex="<?php echo !$trainingWheelsOff ? '0' : '-1'; ?>"
                                                    <?php if (!$trainingWheelsOff): ?>
                                                        data-help-tooltip="<?php echo esc_attr($saveDraftDisabledCopy); ?>"
                                                        data-help-passive="1"
                                                        aria-label="<?php echo esc_attr($saveDraftDisabledCopy); ?>"
                                                    <?php endif; ?>
                                                >
                                                    <button type="submit" class="button tasty-fonts-scope-button tasty-fonts-scope-button--save" name="tasty_fonts_action_type" value="save" form="<?php echo esc_attr($roleFormId); ?>" data-role-save-draft aria-disabled="true" disabled>
                                                        <span class="tasty-fonts-scope-button-title"><?php esc_html_e('Save Draft', 'tasty-fonts'); ?></span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="tasty-fonts-studio-card tasty-fonts-role-command-card tasty-fonts-role-command-card--utilities">
                                            <div class="tasty-fonts-studio-card-head tasty-fonts-role-command-card-head">
                                                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Review', 'tasty-fonts'); ?></span>
                                                    <h3><?php esc_html_e('Preview and Snippets', 'tasty-fonts'); ?></h3>
                                                </div>
                                            </div>
                                            <p class="tasty-fonts-studio-card-copy tasty-fonts-role-command-card-copy"><?php esc_html_e('Review the pairing or copy variables, stacks, and class snippets.', 'tasty-fonts'); ?></p>
                                            <div class="tasty-fonts-role-command-actions">
                                                <button
                                                    type="button"
                                                    class="button tasty-fonts-disclosure-button tasty-fonts-disclosure-button--preview tasty-fonts-scope-button tasty-fonts-scope-button--advanced"
                                                    data-disclosure-toggle="tasty-fonts-role-preview-panel"
                                                    data-expanded-label="<?php echo esc_attr__('Preview', 'tasty-fonts'); ?>"
                                                    data-collapsed-label="<?php echo esc_attr__('Preview', 'tasty-fonts'); ?>"
                                                    aria-expanded="false"
                                                    aria-controls="tasty-fonts-role-preview-panel"
                                                >
                                                    <?php esc_html_e('Preview', 'tasty-fonts'); ?>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="button tasty-fonts-disclosure-button tasty-fonts-disclosure-button--preview tasty-fonts-scope-button tasty-fonts-scope-button--advanced"
                                                    data-disclosure-toggle="tasty-fonts-role-snippets-panel"
                                                    data-expanded-label="<?php echo esc_attr__('Snippets', 'tasty-fonts'); ?>"
                                                    data-collapsed-label="<?php echo esc_attr__('Snippets', 'tasty-fonts'); ?>"
                                                    aria-expanded="false"
                                                    aria-controls="tasty-fonts-role-snippets-panel"
                                                >
                                                    <?php esc_html_e('Snippets', 'tasty-fonts'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($embeddedPreviewSection) || !empty($embeddedToolsSection)): ?>
                                <div class="tasty-fonts-studio-disclosures">
                                    <?php echo $embeddedPreviewSection ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted renderer output ?>
                                    <?php echo $embeddedToolsSection ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted renderer output ?>
                                </div>
                            <?php endif; ?>

                            <div class="tasty-fonts-studio-section tasty-fonts-role-selection">
                                <div class="tasty-fonts-studio-section-summary tasty-fonts-role-selection-summary">
                                    <div class="tasty-fonts-studio-section-summary-copy tasty-fonts-role-selection-summary-copy">
                                        <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Roles', 'tasty-fonts'); ?></span>
                                        <h2 class="tasty-fonts-studio-section-title"><?php esc_html_e('Choose Families.', 'tasty-fonts'); ?></h2>
                                    </div>
                                    <div class="tasty-fonts-role-selection-summary-meta">
                                        <div class="tasty-fonts-role-stacks tasty-fonts-role-stacks--selection">
                                            <span class="tasty-fonts-role-stack">
                                                <span class="tasty-fonts-role-stack-label"><?php esc_html_e('Heading', 'tasty-fonts'); ?></span>
                                                <button
                                                    type="button"
                                                    class="tasty-fonts-pill tasty-fonts-pill--code tasty-fonts-pill--interactive tasty-fonts-pill--copy tasty-fonts-kbd tasty-fonts-role-stack-copy"
                                                    id="tasty-fonts-role-heading-stack"
                                                    data-role-variable-copy="heading"
                                                    data-copy-text="<?php echo esc_attr($headingVariable); ?>"
                                                    data-copy-success="<?php esc_attr_e('Heading variable copied.', 'tasty-fonts'); ?>"
                                                    data-copy-static-label="1"
                                                    aria-label="<?php echo esc_attr(sprintf(__('Heading font variable: %1$s. Resolved stack: %2$s', 'tasty-fonts'), $headingVariable, $headingStack)); ?>"
                                                    title="<?php echo esc_attr(sprintf(__('Heading font variable: %1$s. Resolved stack: %2$s', 'tasty-fonts'), $headingVariable, $headingStack)); ?>"
                                                >
                                                    <?php echo esc_html($headingVariable); ?>
                                                </button>
                                            </span>
                                            <span class="tasty-fonts-role-stack">
                                                <span class="tasty-fonts-role-stack-label"><?php esc_html_e('Body', 'tasty-fonts'); ?></span>
                                                <button
                                                    type="button"
                                                    class="tasty-fonts-pill tasty-fonts-pill--code tasty-fonts-pill--interactive tasty-fonts-pill--copy tasty-fonts-kbd tasty-fonts-role-stack-copy"
                                                    id="tasty-fonts-role-body-stack"
                                                    data-role-variable-copy="body"
                                                    data-copy-text="<?php echo esc_attr($bodyVariable); ?>"
                                                    data-copy-success="<?php esc_attr_e('Body variable copied.', 'tasty-fonts'); ?>"
                                                    data-copy-static-label="1"
                                                    aria-label="<?php echo esc_attr(sprintf(__('Body font variable: %1$s. Resolved stack: %2$s', 'tasty-fonts'), $bodyVariable, $bodyStack)); ?>"
                                                    title="<?php echo esc_attr(sprintf(__('Body font variable: %1$s. Resolved stack: %2$s', 'tasty-fonts'), $bodyVariable, $bodyStack)); ?>"
                                                >
                                                    <?php echo esc_html($bodyVariable); ?>
                                                </button>
                                            </span>
                                            <?php if ($monospaceRoleEnabled): ?>
                                                <span class="tasty-fonts-role-stack">
                                                    <span class="tasty-fonts-role-stack-label"><?php esc_html_e('Monospace', 'tasty-fonts'); ?></span>
                                                    <button
                                                        type="button"
                                                        class="tasty-fonts-pill tasty-fonts-pill--code tasty-fonts-pill--interactive tasty-fonts-pill--copy tasty-fonts-kbd tasty-fonts-role-stack-copy"
                                                        id="tasty-fonts-role-monospace-stack"
                                                        data-role-variable-copy="monospace"
                                                        data-copy-text="<?php echo esc_attr($monospaceVariable); ?>"
                                                        data-copy-success="<?php esc_attr_e('Monospace variable copied.', 'tasty-fonts'); ?>"
                                                        data-copy-static-label="1"
                                                        aria-label="<?php echo esc_attr(sprintf(__('Monospace font variable: %1$s. Resolved stack: %2$s', 'tasty-fonts'), $monospaceVariable, $monospaceStack)); ?>"
                                                        title="<?php echo esc_attr(sprintf(__('Monospace font variable: %1$s. Resolved stack: %2$s', 'tasty-fonts'), $monospaceVariable, $monospaceStack)); ?>"
                                                    >
                                                        <?php echo esc_html($monospaceVariable); ?>
                                                    </button>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="tasty-fonts-studio-card-grid tasty-fonts-role-grid<?php echo $monospaceRoleEnabled ? ' is-three-columns' : ''; ?>">
                                    <?php
                                    $roleCards = [
                                        [
                                            'role_key' => 'heading',
                                            'kicker' => __('Role', 'tasty-fonts'),
                                            'title' => __('Heading', 'tasty-fonts'),
                                            'family_variable' => $headingFamilyVariable,
                                            'copy_success' => __('Heading family variable copied.', 'tasty-fonts'),
                                            'copy_label' => $headingFamily !== ''
                                                ? sprintf(__('Heading family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $headingFamilyVariable, $headingVariable, $headingStack)
                                                : sprintf(__('Heading uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'), $headingStack, $headingVariable),
                                            'description' => __('Choose a heading family or use the fallback stack directly.', 'tasty-fonts'),
                                            'preview_text' => $previewText,
                                            'preview_stack' => $headingStack,
                                            'family_input_name' => 'tasty_fonts_heading_font',
                                            'family_select_id' => 'tasty_fonts_heading_font',
                                            'clear_family_label' => __('Clear Heading Family', 'tasty-fonts'),
                                            'weight_input_name' => 'tasty_fonts_heading_weight',
                                            'weight_select_id' => 'tasty_fonts_heading_weight',
                                            'clear_weight_label' => __('Reset Heading Weight', 'tasty-fonts'),
                                            'weight_screen_reader_label' => __('Heading weight', 'tasty-fonts'),
                                        ],
                                        [
                                            'role_key' => 'body',
                                            'kicker' => __('Role', 'tasty-fonts'),
                                            'title' => __('Body', 'tasty-fonts'),
                                            'family_variable' => $bodyFamilyVariable,
                                            'copy_success' => __('Body family variable copied.', 'tasty-fonts'),
                                            'copy_label' => $bodyFamily !== ''
                                                ? sprintf(__('Body family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $bodyFamilyVariable, $bodyVariable, $bodyStack)
                                                : sprintf(__('Body uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'), $bodyStack, $bodyVariable),
                                            'description' => __('Choose a body family or use the fallback stack directly.', 'tasty-fonts'),
                                            'preview_text' => $previewText,
                                            'preview_stack' => $bodyStack,
                                            'family_input_name' => 'tasty_fonts_body_font',
                                            'family_select_id' => 'tasty_fonts_body_font',
                                            'clear_family_label' => __('Clear Body Family', 'tasty-fonts'),
                                            'weight_input_name' => 'tasty_fonts_body_weight',
                                            'weight_select_id' => 'tasty_fonts_body_weight',
                                            'clear_weight_label' => __('Reset Body Weight', 'tasty-fonts'),
                                            'weight_screen_reader_label' => __('Body weight', 'tasty-fonts'),
                                        ],
                                    ];

                                    if ($monospaceRoleEnabled) {
                                        $roleCards[] = [
                                            'role_key' => 'monospace',
                                            'kicker' => __('Role', 'tasty-fonts'),
                                            'title' => __('Monospace', 'tasty-fonts'),
                                            'family_variable' => $monospaceFamilyVariable,
                                            'copy_success' => __('Monospace value copied.', 'tasty-fonts'),
                                            'copy_label' => $monospaceFamily !== ''
                                                ? sprintf(__('Monospace family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $monospaceFamilyVariable, $monospaceVariable, $monospaceStack)
                                                : sprintf(__('Monospace uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'), $monospaceStack, $monospaceVariable),
                                            'description' => __('Choose a monospace family or use the fallback stack directly.', 'tasty-fonts'),
                                            'preview_text' => $previewText,
                                            'preview_stack' => $monospaceStack,
                                            'family_input_name' => 'tasty_fonts_monospace_font',
                                            'family_select_id' => 'tasty_fonts_monospace_font',
                                            'clear_family_label' => __('Clear Monospace Family', 'tasty-fonts'),
                                            'weight_input_name' => 'tasty_fonts_monospace_weight',
                                            'weight_select_id' => 'tasty_fonts_monospace_weight',
                                            'clear_weight_label' => __('Reset Monospace Weight', 'tasty-fonts'),
                                            'weight_screen_reader_label' => __('Monospace weight', 'tasty-fonts'),
                                        ];
                                    }

                                    foreach ($roleCards as $roleCard) {
                                        $this->renderRoleSelectionCard($roleCard, $roles, $availableFamilyOptions, $roleFormId);
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php if ($localEnvironmentNotice !== []): ?>
                                <div class="tasty-fonts-role-command-notice">
                                    <?php $this->renderEnvironmentNotice($localEnvironmentNotice); ?>
                                </div>
                            <?php endif; ?>
                    </section>
