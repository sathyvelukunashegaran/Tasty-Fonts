                    <section class="tasty-fonts-card tasty-fonts-studio-card tasty-fonts-top-panel" id="tasty-fonts-roles-studio">
                        <div class="tasty-fonts-top-panel-intro">
                            <div class="tasty-fonts-overview-head tasty-fonts-top-panel-overview">
                                <div class="tasty-fonts-hero-copy">
                                    <div class="tasty-fonts-hero-title-row">
                                        <h1><?php esc_html_e('Tasty Custom Fonts', 'tasty-fonts'); ?></h1>
                                        <?php if ($pluginVersion !== ''): ?>
                                            <a
                                                class="tasty-fonts-version-link tasty-fonts-badge is-role"
                                                href="<?php echo esc_url($pluginVersionUrl); ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                aria-label="<?php echo esc_attr(sprintf(__('View GitHub changelog for version %s', 'tasty-fonts'), $pluginVersion)); ?>"
                                                title="<?php echo esc_attr(sprintf(__('View changelog for version %s on GitHub', 'tasty-fonts'), $pluginVersion)); ?>"
                                            >
                                                <?php echo esc_html(sprintf(__('v%s', 'tasty-fonts'), $pluginVersion)); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <p class="tasty-fonts-hero-text"><?php esc_html_e('Professional Typography Management For WordPress', 'tasty-fonts'); ?></p>
                                </div>
                            </div>

                            <div class="tasty-fonts-metrics tasty-fonts-metrics--top-panel">
                                <?php foreach ($overviewMetrics as $metric): ?>
                                    <article class="tasty-fonts-metric">
                                        <div class="tasty-fonts-metric-label"><?php echo esc_html((string) ($metric['label'] ?? '')); ?></div>
                                        <div class="tasty-fonts-metric-value"><?php echo esc_html((string) ($metric['value'] ?? '')); ?></div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>

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
                                            <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Deployment Controls', 'tasty-fonts'); ?></span>
                                            <h3 class="tasty-fonts-studio-section-title"><?php esc_html_e('Set delivery first, then choose the role pairing.', 'tasty-fonts'); ?></h3>
                                            <p><?php esc_html_e('Use these actions to control sitewide delivery, save draft assignments, and open the preview or deeper output tools.', 'tasty-fonts'); ?></p>
                                        </div>
                                        <div class="tasty-fonts-role-command-summary-meta">
                                            <button
                                                type="button"
                                                class="tasty-fonts-pill tasty-fonts-pill--status tasty-fonts-pill--interactive tasty-fonts-pill--help tasty-fonts-role-command-status<?php echo $applyEverywhere ? ' is-live' : ''; ?>"
                                                <?php $this->renderPassiveHelpAttributes($sitewideStatusTooltip); ?>
                                                aria-label="<?php esc_attr_e('Sitewide delivery status', 'tasty-fonts'); ?>"
                                                aria-controls="tasty-fonts-help-tooltip-layer"
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
                                                            <?php $this->renderPassiveHelpAttributes($roleDeploymentTooltip); ?>
                                                            aria-label="<?php esc_attr_e('Role deployment status', 'tasty-fonts'); ?>"
                                                            aria-describedby="<?php echo esc_attr($roleDeploymentAnnouncementId); ?>"
                                                            aria-controls="tasty-fonts-help-tooltip-layer"
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
                                                    <h4><?php esc_html_e('Control live font delivery', 'tasty-fonts'); ?></h4>
                                                </div>
                                                <?php $this->renderHelpTip(__('Turn live frontend, editor, and Etch role CSS on or off. Switching it on applies the current role selections immediately.', 'tasty-fonts'), __('Apply Sitewide', 'tasty-fonts')); ?>
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
                                                    <span class="tasty-fonts-scope-button-title"><?php esc_html_e('Switch off Sitewide', 'tasty-fonts'); ?></span>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="tasty-fonts-studio-card tasty-fonts-role-command-card tasty-fonts-role-command-card--actions">
                                            <div class="tasty-fonts-studio-card-head tasty-fonts-role-command-card-head">
                                                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Role Actions', 'tasty-fonts'); ?></span>
                                                    <h4><?php esc_html_e('Save or publish role changes', 'tasty-fonts'); ?></h4>
                                                </div>
                                                <?php $this->renderHelpTip(__('Save the current roles as a draft, or publish them live when sitewide delivery is on.', 'tasty-fonts'), __('Role Actions', 'tasty-fonts')); ?>
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
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Utilities', 'tasty-fonts'); ?></span>
                                                    <h4><?php esc_html_e('Open preview or deeper tools', 'tasty-fonts'); ?></h4>
                                                </div>
                                                <?php $this->renderHelpTip(__('Open the role preview workspace, or inspect snippets, generated CSS, system details, output settings, and plugin behavior.', 'tasty-fonts'), __('Utilities', 'tasty-fonts')); ?>
                                            </div>
                                            <p class="tasty-fonts-studio-card-copy tasty-fonts-role-command-card-copy"><?php esc_html_e('Use Preview for visual checks. Open Advanced Tools when you need output inspection or plugin settings.', 'tasty-fonts'); ?></p>
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
                                                    data-disclosure-toggle="tasty-fonts-role-advanced-panel"
                                                    data-expanded-label="<?php echo esc_attr__('Advanced Tools', 'tasty-fonts'); ?>"
                                                    data-collapsed-label="<?php echo esc_attr__('Advanced Tools', 'tasty-fonts'); ?>"
                                                    aria-expanded="false"
                                                    aria-controls="tasty-fonts-role-advanced-panel"
                                                >
                                                    <?php esc_html_e('Advanced Tools', 'tasty-fonts'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($embeddedPreviewSection) || !empty($embeddedToolsSection)): ?>
                                <div class="tasty-fonts-studio-disclosures">
                                    <?php echo $embeddedPreviewSection ?? ''; ?>
                                    <?php echo $embeddedToolsSection ?? ''; ?>
                                </div>
                            <?php endif; ?>

                            <div class="tasty-fonts-studio-section tasty-fonts-role-selection">
                                <div class="tasty-fonts-studio-section-summary tasty-fonts-role-selection-summary">
                                    <div class="tasty-fonts-studio-section-summary-copy tasty-fonts-role-selection-summary-copy">
                                        <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Role Selection', 'tasty-fonts'); ?></span>
                                        <h3 class="tasty-fonts-studio-section-title"><?php esc_html_e('Choose the family and fallback for each saved role.', 'tasty-fonts'); ?></h3>
                                        <p>
                                            <?php
                                            echo esc_html(
                                                $monospaceRoleEnabled
                                                    ? __('These cards define the saved heading, body, and optional monospace pairings used by the deployment controls above.', 'tasty-fonts')
                                                    : __('These cards define the saved heading and body pairings used by the deployment controls above.', 'tasty-fonts')
                                            );
                                            ?>
                                        </p>
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
                                                    aria-label="<?php esc_attr_e('Copy heading font variable', 'tasty-fonts'); ?>"
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
                                                    aria-label="<?php esc_attr_e('Copy body font variable', 'tasty-fonts'); ?>"
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
                                                        aria-label="<?php esc_attr_e('Copy monospace font variable', 'tasty-fonts'); ?>"
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
                                            <button
                                                type="button"
                                                class="tasty-fonts-pill tasty-fonts-pill--code tasty-fonts-pill--interactive tasty-fonts-pill--copy tasty-fonts-kbd tasty-fonts-role-stack-copy tasty-fonts-role-box-copy"
                                                data-role-family-variable-copy="heading"
                                                data-copy-text="<?php echo esc_attr($headingFamilyVariable); ?>"
                                                data-copy-success="<?php esc_attr_e('Heading family variable copied.', 'tasty-fonts'); ?>"
                                                data-copy-static-label="1"
                                                aria-label="<?php esc_attr_e('Copy heading family variable', 'tasty-fonts'); ?>"
                                                title="<?php echo esc_attr($headingFamily !== '' ? sprintf(__('Heading family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $headingFamilyVariable, $headingVariable, $headingStack) : sprintf(__('Heading uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'), $headingStack, $headingVariable)); ?>"
                                            >
                                                <?php echo esc_html($headingFamilyVariable); ?>
                                            </button>
                                        </div>
                                        <p class="tasty-fonts-studio-card-copy tasty-fonts-role-box-description"><?php esc_html_e('Choose the saved family and fallback stack used for headings.', 'tasty-fonts'); ?></p>
                                        <div class="tasty-fonts-role-fields">
                                            <label class="tasty-fonts-stack-field">
                                                <?php $this->renderFieldLabel(__('Family', 'tasty-fonts')); ?>
                                                <select name="tasty_fonts_heading_font" id="tasty_fonts_heading_font" form="<?php echo esc_attr($roleFormId); ?>">
                                                    <option value="" <?php selected($roles['heading'] ?? '', ''); ?>><?php esc_html_e('Use fallback only', 'tasty-fonts'); ?></option>
                                                    <?php foreach ($availableFamilies as $familyName): ?>
                                                        <option value="<?php echo esc_attr((string) $familyName); ?>" <?php selected($roles['heading'] ?? '', $familyName); ?>><?php echo esc_html((string) $familyName); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label class="tasty-fonts-stack-field">
                                                <?php $this->renderFieldLabel(__('Fallback', 'tasty-fonts')); ?>
                                                <?php
                                                $this->renderFallbackInput(
                                                    'tasty_fonts_heading_fallback',
                                                    (string) ($roles['heading_fallback'] ?? 'sans-serif'),
                                                    [
                                                        'id' => 'tasty_fonts_heading_fallback',
                                                        'form' => $roleFormId,
                                                        'placeholder' => __('Example: system-ui, sans-serif', 'tasty-fonts'),
                                                    ]
                                                );
                                                ?>
                                            </label>
                                        </div>
                                    </section>

                                    <section class="tasty-fonts-studio-card tasty-fonts-role-box">
                                        <div class="tasty-fonts-studio-card-head tasty-fonts-role-box-head">
                                            <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Body', 'tasty-fonts'); ?></span>
                                                <h4><?php esc_html_e('Body Font', 'tasty-fonts'); ?></h4>
                                            </div>
                                            <button
                                                type="button"
                                                class="tasty-fonts-pill tasty-fonts-pill--code tasty-fonts-pill--interactive tasty-fonts-pill--copy tasty-fonts-kbd tasty-fonts-role-stack-copy tasty-fonts-role-box-copy"
                                                data-role-family-variable-copy="body"
                                                data-copy-text="<?php echo esc_attr($bodyFamilyVariable); ?>"
                                                data-copy-success="<?php esc_attr_e('Body family variable copied.', 'tasty-fonts'); ?>"
                                                data-copy-static-label="1"
                                                aria-label="<?php esc_attr_e('Copy body family variable', 'tasty-fonts'); ?>"
                                                title="<?php echo esc_attr($bodyFamily !== '' ? sprintf(__('Body family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $bodyFamilyVariable, $bodyVariable, $bodyStack) : sprintf(__('Body uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'), $bodyStack, $bodyVariable)); ?>"
                                            >
                                                <?php echo esc_html($bodyFamilyVariable); ?>
                                            </button>
                                        </div>
                                        <p class="tasty-fonts-studio-card-copy tasty-fonts-role-box-description"><?php esc_html_e('Choose the saved family and fallback stack used for body copy.', 'tasty-fonts'); ?></p>
                                        <div class="tasty-fonts-role-fields">
                                            <label class="tasty-fonts-stack-field">
                                                <?php $this->renderFieldLabel(__('Family', 'tasty-fonts')); ?>
                                                <select name="tasty_fonts_body_font" id="tasty_fonts_body_font" form="<?php echo esc_attr($roleFormId); ?>">
                                                    <option value="" <?php selected($roles['body'] ?? '', ''); ?>><?php esc_html_e('Use fallback only', 'tasty-fonts'); ?></option>
                                                    <?php foreach ($availableFamilies as $familyName): ?>
                                                        <option value="<?php echo esc_attr((string) $familyName); ?>" <?php selected($roles['body'] ?? '', $familyName); ?>><?php echo esc_html((string) $familyName); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label class="tasty-fonts-stack-field">
                                                <?php $this->renderFieldLabel(__('Fallback', 'tasty-fonts')); ?>
                                                <?php
                                                $this->renderFallbackInput(
                                                    'tasty_fonts_body_fallback',
                                                    (string) ($roles['body_fallback'] ?? 'sans-serif'),
                                                    [
                                                        'id' => 'tasty_fonts_body_fallback',
                                                        'form' => $roleFormId,
                                                        'placeholder' => __('Example: system-ui, sans-serif', 'tasty-fonts'),
                                                    ]
                                                );
                                                ?>
                                            </label>
                                        </div>
                                    </section>

                                <?php if ($monospaceRoleEnabled): ?>
                                    <section class="tasty-fonts-studio-card tasty-fonts-role-box">
                                        <div class="tasty-fonts-studio-card-head tasty-fonts-role-box-head">
                                            <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Monospace', 'tasty-fonts'); ?></span>
                                                <h4><?php esc_html_e('Monospace Font', 'tasty-fonts'); ?></h4>
                                            </div>
                                            <button
                                                type="button"
                                                class="tasty-fonts-pill tasty-fonts-pill--code tasty-fonts-pill--interactive tasty-fonts-pill--copy tasty-fonts-kbd tasty-fonts-role-stack-copy tasty-fonts-role-box-copy"
                                                data-role-family-variable-copy="monospace"
                                                data-copy-text="<?php echo esc_attr($monospaceFamilyVariable); ?>"
                                                data-copy-success="<?php esc_attr_e('Monospace value copied.', 'tasty-fonts'); ?>"
                                                data-copy-static-label="1"
                                                aria-label="<?php esc_attr_e('Copy monospace value', 'tasty-fonts'); ?>"
                                                title="<?php echo esc_attr($monospaceFamily !== '' ? sprintf(__('Monospace family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $monospaceFamilyVariable, $monospaceVariable, $monospaceStack) : sprintf(__('Monospace uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'), $monospaceStack, $monospaceVariable)); ?>"
                                            >
                                                <?php echo esc_html($monospaceFamilyVariable); ?>
                                            </button>
                                        </div>
                                        <p class="tasty-fonts-studio-card-copy tasty-fonts-role-box-description"><?php esc_html_e('Choose the saved family or fallback stack used for code and monospace UI.', 'tasty-fonts'); ?></p>
                                        <div class="tasty-fonts-role-fields">
                                            <label class="tasty-fonts-stack-field">
                                                <?php $this->renderFieldLabel(__('Family', 'tasty-fonts')); ?>
                                                <select name="tasty_fonts_monospace_font" id="tasty_fonts_monospace_font" form="<?php echo esc_attr($roleFormId); ?>">
                                                    <option value="" <?php selected($roles['monospace'] ?? '', ''); ?>><?php esc_html_e('Use fallback only', 'tasty-fonts'); ?></option>
                                                    <?php foreach ($availableFamilies as $familyName): ?>
                                                        <option value="<?php echo esc_attr((string) $familyName); ?>" <?php selected($roles['monospace'] ?? '', $familyName); ?>><?php echo esc_html((string) $familyName); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label class="tasty-fonts-stack-field">
                                                <?php $this->renderFieldLabel(__('Fallback', 'tasty-fonts')); ?>
                                                <?php
                                                $this->renderFallbackInput(
                                                    'tasty_fonts_monospace_fallback',
                                                    (string) ($roles['monospace_fallback'] ?? 'monospace'),
                                                    [
                                                        'id' => 'tasty_fonts_monospace_fallback',
                                                        'form' => $roleFormId,
                                                        'placeholder' => __('Example: ui-monospace, monospace', 'tasty-fonts'),
                                                    ]
                                                );
                                                ?>
                                            </label>
                                        </div>
                                    </section>
                                <?php endif; ?>
                            </div>
                            </div>
                    </section>
