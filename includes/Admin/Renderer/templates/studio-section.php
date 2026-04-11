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
                                            <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Publish Workflow', 'tasty-fonts'); ?></span>
                                            <h3 class="tasty-fonts-studio-section-title"><?php esc_html_e('Choose Fonts, Preview the Pairing, Then Publish When It Is Ready.', 'tasty-fonts'); ?></h3>
                                        </div>
                                        <div class="tasty-fonts-role-command-summary-meta">
                                            <button
                                                type="button"
                                                class="tasty-fonts-pill tasty-fonts-pill--status tasty-fonts-pill--interactive tasty-fonts-pill--help tasty-fonts-role-command-status<?php echo $applyEverywhere ? ' is-live' : ''; ?>"
                                                <?php $this->renderPassiveHelpAttributes($sitewideStatusTooltip); ?>
                                                aria-label="<?php esc_attr_e('Sitewide delivery status', 'tasty-fonts'); ?>"
                                            >
                                                <?php echo esc_html($applyEverywhere ? __('Sitewide on', 'tasty-fonts') : __('Draft only', 'tasty-fonts')); ?>
                                            </button>
                                            <?php if ($roleDeployment !== []): ?>
                                                <div class="tasty-fonts-role-stacks tasty-fonts-role-stacks--summary">
                                                    <span class="tasty-fonts-role-stack tasty-fonts-role-deployment <?php echo esc_attr($roleDeploymentBadgeClass); ?>" data-role-deployment aria-live="polite" aria-atomic="true">
                                                        <span class="tasty-fonts-role-stack-label"><?php esc_html_e('Status', 'tasty-fonts'); ?></span>
                                                        <button
                                                            type="button"
                                                            class="tasty-fonts-pill tasty-fonts-pill--status tasty-fonts-pill--interactive tasty-fonts-pill--help tasty-fonts-role-status-pill <?php echo esc_attr($roleDeploymentBadgeClass); ?>"
                                                            data-role-deployment-pill
                                                            <?php $this->renderPassiveHelpAttributes($roleDeploymentTooltip, $roleDeploymentAnnouncementId . ' tasty-fonts-help-tooltip-layer'); ?>
                                                            aria-label="<?php esc_attr_e('Role deployment status', 'tasty-fonts'); ?>"
                                                        >
                                                            <span data-role-deployment-badge><?php echo esc_html($roleDeploymentBadge); ?></span>
                                                        </button>
                                                        <span id="<?php echo esc_attr($roleDeploymentAnnouncementId); ?>" class="screen-reader-text" data-role-deployment-announcement><?php echo esc_html($roleDeploymentTooltip); ?></span>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="tasty-fonts-studio-card-grid tasty-fonts-role-actions">
                                        <div class="tasty-fonts-studio-card tasty-fonts-role-command-card tasty-fonts-role-command-card--sitewide<?php echo $applyEverywhere ? ' is-live' : ' is-draft'; ?>">
                                            <div class="tasty-fonts-studio-card-head tasty-fonts-role-command-card-head">
                                                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Sitewide', 'tasty-fonts'); ?></span>
                                                    <h4><?php esc_html_e('Control Live Font Delivery', 'tasty-fonts'); ?></h4>
                                                </div>
                                            </div>
                                            <p class="tasty-fonts-studio-card-copy tasty-fonts-role-command-card-copy">
                                                <?php
                                                echo esc_html(
                                                    $applyEverywhere
                                                        ? __('These roles are currently being served on the frontend, editor, and Etch.', 'tasty-fonts')
                                                        : __('Keep the current pairing local until you are ready to serve it across every surface.', 'tasty-fonts')
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
                                                    <?php disabled($applyEverywhere); ?>
                                                >
                                                    <span class="tasty-fonts-scope-button-title"><?php esc_html_e('Apply Sitewide', 'tasty-fonts'); ?></span>
                                                </button>
                                                <button
                                                    type="submit"
                                                    class="button tasty-fonts-scope-button tasty-fonts-scope-button--save"
                                                    name="tasty_fonts_action_type"
                                                    value="disable"
                                                    form="<?php echo esc_attr($roleFormId); ?>"
                                                    <?php disabled(!$applyEverywhere); ?>
                                                >
                                                    <span class="tasty-fonts-scope-button-title"><?php esc_html_e('Switch Off Sitewide', 'tasty-fonts'); ?></span>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="tasty-fonts-studio-card tasty-fonts-role-command-card tasty-fonts-role-command-card--actions">
                                            <div class="tasty-fonts-studio-card-head tasty-fonts-role-command-card-head">
                                                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Role Actions', 'tasty-fonts'); ?></span>
                                                    <h4><?php esc_html_e('Save or Publish Role Changes', 'tasty-fonts'); ?></h4>
                                                </div>
                                            </div>
                                            <p class="tasty-fonts-studio-card-copy tasty-fonts-role-command-card-copy"><?php esc_html_e('Publish Roles when the current selection is ready. Save Draft keeps the pairing as your working draft.', 'tasty-fonts'); ?></p>
                                            <div class="tasty-fonts-role-command-actions">
                                                <div
                                                    class="tasty-fonts-role-command-action<?php echo !$hasPendingLiveRoleChanges ? ' has-disabled-reason' : ''; ?>"
                                                    data-role-apply-live-wrap
                                                    tabindex="<?php echo !$hasPendingLiveRoleChanges && !$trainingWheelsOff ? '0' : '-1'; ?>"
                                                    <?php if (!$trainingWheelsOff): ?>
                                                        data-help-tooltip="<?php echo esc_attr($hasPendingLiveRoleChanges ? '' : $applyLiveDisabledCopy); ?>"
                                                        data-help-passive="1"
                                                        title="<?php echo esc_attr($hasPendingLiveRoleChanges ? '' : $applyLiveDisabledCopy); ?>"
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
                                                        title="<?php echo esc_attr($saveDraftDisabledCopy); ?>"
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
                                                    <h4><?php esc_html_e('Preview the Pairing or Open Snippets', 'tasty-fonts'); ?></h4>
                                                </div>
                                            </div>
                                            <p class="tasty-fonts-studio-card-copy tasty-fonts-role-command-card-copy"><?php esc_html_e('Use Preview for visual checks. Open Snippets when you need variables, stacks, and usage code.', 'tasty-fonts'); ?></p>
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
                                        <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Role Selection', 'tasty-fonts'); ?></span>
                                        <h3 class="tasty-fonts-studio-section-title"><?php esc_html_e('Choose the Family and Fallback for Each Saved Role.', 'tasty-fonts'); ?></h3>
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
                                    <section class="tasty-fonts-studio-card tasty-fonts-role-box">
                                        <div class="tasty-fonts-studio-card-head tasty-fonts-role-box-head">
                                            <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Heading', 'tasty-fonts'); ?></span>
                                                <h4><?php esc_html_e('Heading Font', 'tasty-fonts'); ?></h4>
                                            </div>
                                            <div class="tasty-fonts-role-box-meta">
                                                <span class="tasty-fonts-role-box-meta-label"><?php esc_html_e('Current Value', 'tasty-fonts'); ?></span>
                                                <button
                                                    type="button"
                                                    class="tasty-fonts-pill tasty-fonts-pill--code tasty-fonts-pill--interactive tasty-fonts-pill--copy tasty-fonts-kbd tasty-fonts-role-stack-copy tasty-fonts-role-box-copy"
                                                    data-role-family-variable-copy="heading"
                                                    data-copy-text="<?php echo esc_attr($headingFamilyVariable); ?>"
                                                    data-copy-success="<?php esc_attr_e('Heading family variable copied.', 'tasty-fonts'); ?>"
                                                    data-copy-static-label="1"
                                                    aria-label="<?php echo esc_attr($headingFamily !== '' ? sprintf(__('Heading family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $headingFamilyVariable, $headingVariable, $headingStack) : sprintf(__('Heading uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'), $headingStack, $headingVariable)); ?>"
                                                    title="<?php echo esc_attr($headingFamily !== '' ? sprintf(__('Heading family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $headingFamilyVariable, $headingVariable, $headingStack) : sprintf(__('Heading uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'), $headingStack, $headingVariable)); ?>"
                                                >
                                                    <span class="tasty-fonts-role-box-copy-label"><?php echo esc_html($headingFamilyVariable); ?></span>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="tasty-fonts-studio-card-copy tasty-fonts-role-box-description"><?php esc_html_e('Choose the saved family used for headings. Configure its fallback in the Font Library.', 'tasty-fonts'); ?></p>
                                        <div class="tasty-fonts-role-fields">
                                            <label class="tasty-fonts-stack-field">
                                                <?php $this->renderFieldLabel(__('Family', 'tasty-fonts')); ?>
                                                <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                                                    <select name="tasty_fonts_heading_font" id="tasty_fonts_heading_font" form="<?php echo esc_attr($roleFormId); ?>">
                                                        <option value="" <?php selected($roles['heading'] ?? '', ''); ?>><?php esc_html_e('Use Fallback Only', 'tasty-fonts'); ?></option>
                                                        <?php foreach ($availableFamilyOptions as $option): ?>
                                                            <?php if (!is_array($option)) { continue; } ?>
                                                            <?php $familyName = (string) ($option['value'] ?? ''); ?>
                                                            <?php $familyLabel = (string) ($option['label'] ?? $familyName); ?>
                                                            <option value="<?php echo esc_attr($familyName); ?>" <?php selected($roles['heading'] ?? '', $familyName); ?>><?php echo esc_html($familyLabel); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <?php $this->renderClearSelectButton(__('Clear Heading Family', 'tasty-fonts'), 'tasty_fonts_heading_font'); ?>
                                                </span>
                                            </label>
                                        </div>
                                        <div class="tasty-fonts-role-weight-editor" data-role-weight-editor="heading" hidden>
                                            <div class="tasty-fonts-role-axis-head">
                                                <span class="tasty-fonts-field-label-text"><?php esc_html_e('Role Weight', 'tasty-fonts'); ?></span>
                                                <span class="tasty-fonts-muted" data-role-weight-summary="heading"><?php esc_html_e('Choose a saved static weight when the selected family offers more than one.', 'tasty-fonts'); ?></span>
                                            </div>
                                            <label class="tasty-fonts-stack-field tasty-fonts-role-weight-field">
                                                <span class="screen-reader-text"><?php esc_html_e('Heading weight', 'tasty-fonts'); ?></span>
                                                <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                                                    <select
                                                        name="tasty_fonts_heading_weight"
                                                        id="tasty_fonts_heading_weight"
                                                        data-role-weight-select="heading"
                                                        form="<?php echo esc_attr($roleFormId); ?>"
                                                    ></select>
                                                    <?php $this->renderClearSelectButton(__('Reset Heading Weight', 'tasty-fonts'), 'tasty_fonts_heading_weight'); ?>
                                                </span>
                                            </label>
                                        </div>
                                        <div class="tasty-fonts-role-axis-editor" data-role-axis-editor="heading" hidden>
                                            <div class="tasty-fonts-role-axis-head">
                                                <span class="tasty-fonts-field-label-text"><?php esc_html_e('Variable Axes', 'tasty-fonts'); ?></span>
                                                <span class="tasty-fonts-muted" data-role-axis-summary="heading"><?php esc_html_e('Assign axis values when the selected family supports variable fonts.', 'tasty-fonts'); ?></span>
                                            </div>
                                            <div class="tasty-fonts-role-axis-fields" data-role-axis-fields="heading"></div>
                                        </div>
                                    </section>

                                    <section class="tasty-fonts-studio-card tasty-fonts-role-box">
                                        <div class="tasty-fonts-studio-card-head tasty-fonts-role-box-head">
                                            <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Body', 'tasty-fonts'); ?></span>
                                                <h4><?php esc_html_e('Body Font', 'tasty-fonts'); ?></h4>
                                            </div>
                                            <div class="tasty-fonts-role-box-meta">
                                                <span class="tasty-fonts-role-box-meta-label"><?php esc_html_e('Current Value', 'tasty-fonts'); ?></span>
                                                <button
                                                    type="button"
                                                    class="tasty-fonts-pill tasty-fonts-pill--code tasty-fonts-pill--interactive tasty-fonts-pill--copy tasty-fonts-kbd tasty-fonts-role-stack-copy tasty-fonts-role-box-copy"
                                                    data-role-family-variable-copy="body"
                                                    data-copy-text="<?php echo esc_attr($bodyFamilyVariable); ?>"
                                                    data-copy-success="<?php esc_attr_e('Body family variable copied.', 'tasty-fonts'); ?>"
                                                    data-copy-static-label="1"
                                                    aria-label="<?php echo esc_attr($bodyFamily !== '' ? sprintf(__('Body family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $bodyFamilyVariable, $bodyVariable, $bodyStack) : sprintf(__('Body uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'), $bodyStack, $bodyVariable)); ?>"
                                                    title="<?php echo esc_attr($bodyFamily !== '' ? sprintf(__('Body family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $bodyFamilyVariable, $bodyVariable, $bodyStack) : sprintf(__('Body uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'), $bodyStack, $bodyVariable)); ?>"
                                                >
                                                    <span class="tasty-fonts-role-box-copy-label"><?php echo esc_html($bodyFamilyVariable); ?></span>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="tasty-fonts-studio-card-copy tasty-fonts-role-box-description"><?php esc_html_e('Choose the saved family used for body copy. Configure its fallback in the Font Library.', 'tasty-fonts'); ?></p>
                                        <div class="tasty-fonts-role-fields">
                                            <label class="tasty-fonts-stack-field">
                                                <?php $this->renderFieldLabel(__('Family', 'tasty-fonts')); ?>
                                                <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                                                    <select name="tasty_fonts_body_font" id="tasty_fonts_body_font" form="<?php echo esc_attr($roleFormId); ?>">
                                                        <option value="" <?php selected($roles['body'] ?? '', ''); ?>><?php esc_html_e('Use Fallback Only', 'tasty-fonts'); ?></option>
                                                        <?php foreach ($availableFamilyOptions as $option): ?>
                                                            <?php if (!is_array($option)) { continue; } ?>
                                                            <?php $familyName = (string) ($option['value'] ?? ''); ?>
                                                            <?php $familyLabel = (string) ($option['label'] ?? $familyName); ?>
                                                            <option value="<?php echo esc_attr($familyName); ?>" <?php selected($roles['body'] ?? '', $familyName); ?>><?php echo esc_html($familyLabel); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <?php $this->renderClearSelectButton(__('Clear Body Family', 'tasty-fonts'), 'tasty_fonts_body_font'); ?>
                                                </span>
                                            </label>
                                        </div>
                                        <div class="tasty-fonts-role-weight-editor" data-role-weight-editor="body" hidden>
                                            <div class="tasty-fonts-role-axis-head">
                                                <span class="tasty-fonts-field-label-text"><?php esc_html_e('Role Weight', 'tasty-fonts'); ?></span>
                                                <span class="tasty-fonts-muted" data-role-weight-summary="body"><?php esc_html_e('Choose a saved static weight when the selected family offers more than one.', 'tasty-fonts'); ?></span>
                                            </div>
                                            <label class="tasty-fonts-stack-field tasty-fonts-role-weight-field">
                                                <span class="screen-reader-text"><?php esc_html_e('Body weight', 'tasty-fonts'); ?></span>
                                                <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                                                    <select
                                                        name="tasty_fonts_body_weight"
                                                        id="tasty_fonts_body_weight"
                                                        data-role-weight-select="body"
                                                        form="<?php echo esc_attr($roleFormId); ?>"
                                                    ></select>
                                                    <?php $this->renderClearSelectButton(__('Reset Body Weight', 'tasty-fonts'), 'tasty_fonts_body_weight'); ?>
                                                </span>
                                            </label>
                                        </div>
                                        <div class="tasty-fonts-role-axis-editor" data-role-axis-editor="body" hidden>
                                            <div class="tasty-fonts-role-axis-head">
                                                <span class="tasty-fonts-field-label-text"><?php esc_html_e('Variable Axes', 'tasty-fonts'); ?></span>
                                                <span class="tasty-fonts-muted" data-role-axis-summary="body"><?php esc_html_e('Assign axis values when the selected family supports variable fonts.', 'tasty-fonts'); ?></span>
                                            </div>
                                            <div class="tasty-fonts-role-axis-fields" data-role-axis-fields="body"></div>
                                        </div>
                                    </section>

                                <?php if ($monospaceRoleEnabled): ?>
                                    <section class="tasty-fonts-studio-card tasty-fonts-role-box">
                                        <div class="tasty-fonts-studio-card-head tasty-fonts-role-box-head">
                                            <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Monospace', 'tasty-fonts'); ?></span>
                                                <h4><?php esc_html_e('Monospace Font', 'tasty-fonts'); ?></h4>
                                            </div>
                                            <div class="tasty-fonts-role-box-meta">
                                                <span class="tasty-fonts-role-box-meta-label"><?php esc_html_e('Current Value', 'tasty-fonts'); ?></span>
                                                <button
                                                    type="button"
                                                    class="tasty-fonts-pill tasty-fonts-pill--code tasty-fonts-pill--interactive tasty-fonts-pill--copy tasty-fonts-kbd tasty-fonts-role-stack-copy tasty-fonts-role-box-copy"
                                                    data-role-family-variable-copy="monospace"
                                                    data-copy-text="<?php echo esc_attr($monospaceFamilyVariable); ?>"
                                                    data-copy-success="<?php esc_attr_e('Monospace value copied.', 'tasty-fonts'); ?>"
                                                    data-copy-static-label="1"
                                                    aria-label="<?php echo esc_attr($monospaceFamily !== '' ? sprintf(__('Monospace family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $monospaceFamilyVariable, $monospaceVariable, $monospaceStack) : sprintf(__('Monospace uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'), $monospaceStack, $monospaceVariable)); ?>"
                                                    title="<?php echo esc_attr($monospaceFamily !== '' ? sprintf(__('Monospace family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $monospaceFamilyVariable, $monospaceVariable, $monospaceStack) : sprintf(__('Monospace uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'), $monospaceStack, $monospaceVariable)); ?>"
                                                >
                                                    <span class="tasty-fonts-role-box-copy-label"><?php echo esc_html($monospaceFamilyVariable); ?></span>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="tasty-fonts-studio-card-copy tasty-fonts-role-box-description"><?php esc_html_e('Choose the saved family used for monospace text. Configure its fallback in the Font Library.', 'tasty-fonts'); ?></p>
                                        <div class="tasty-fonts-role-fields">
                                            <label class="tasty-fonts-stack-field">
                                                <?php $this->renderFieldLabel(__('Family', 'tasty-fonts')); ?>
                                                <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                                                    <select name="tasty_fonts_monospace_font" id="tasty_fonts_monospace_font" form="<?php echo esc_attr($roleFormId); ?>">
                                                        <option value="" <?php selected($roles['monospace'] ?? '', ''); ?>><?php esc_html_e('Use Fallback Only', 'tasty-fonts'); ?></option>
                                                        <?php foreach ($availableFamilyOptions as $option): ?>
                                                            <?php if (!is_array($option)) { continue; } ?>
                                                            <?php $familyName = (string) ($option['value'] ?? ''); ?>
                                                            <?php $familyLabel = (string) ($option['label'] ?? $familyName); ?>
                                                            <option value="<?php echo esc_attr($familyName); ?>" <?php selected($roles['monospace'] ?? '', $familyName); ?>><?php echo esc_html($familyLabel); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <?php $this->renderClearSelectButton(__('Clear Monospace Family', 'tasty-fonts'), 'tasty_fonts_monospace_font'); ?>
                                                </span>
                                            </label>
                                        </div>
                                        <div class="tasty-fonts-role-weight-editor" data-role-weight-editor="monospace" hidden>
                                            <div class="tasty-fonts-role-axis-head">
                                                <span class="tasty-fonts-field-label-text"><?php esc_html_e('Role Weight', 'tasty-fonts'); ?></span>
                                                <span class="tasty-fonts-muted" data-role-weight-summary="monospace"><?php esc_html_e('Choose a saved static weight when the selected family offers more than one.', 'tasty-fonts'); ?></span>
                                            </div>
                                            <label class="tasty-fonts-stack-field tasty-fonts-role-weight-field">
                                                <span class="screen-reader-text"><?php esc_html_e('Monospace weight', 'tasty-fonts'); ?></span>
                                                <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                                                    <select
                                                        name="tasty_fonts_monospace_weight"
                                                        id="tasty_fonts_monospace_weight"
                                                        data-role-weight-select="monospace"
                                                        form="<?php echo esc_attr($roleFormId); ?>"
                                                    ></select>
                                                    <?php $this->renderClearSelectButton(__('Reset Monospace Weight', 'tasty-fonts'), 'tasty_fonts_monospace_weight'); ?>
                                                </span>
                                            </label>
                                        </div>
                                        <div class="tasty-fonts-role-axis-editor" data-role-axis-editor="monospace" hidden>
                                            <div class="tasty-fonts-role-axis-head">
                                                <span class="tasty-fonts-field-label-text"><?php esc_html_e('Variable Axes', 'tasty-fonts'); ?></span>
                                                <span class="tasty-fonts-muted" data-role-axis-summary="monospace"><?php esc_html_e('Assign axis values when the selected family supports variable fonts.', 'tasty-fonts'); ?></span>
                                            </div>
                                            <div class="tasty-fonts-role-axis-fields" data-role-axis-fields="monospace"></div>
                                        </div>
                                    </section>
                                <?php endif; ?>
                            </div>
                            </div>
                            <?php if ($localEnvironmentNotice !== []): ?>
                                <div class="tasty-fonts-role-command-notice">
                                    <?php $this->renderEnvironmentNotice($localEnvironmentNotice); ?>
                                </div>
                            <?php endif; ?>
                    </section>
