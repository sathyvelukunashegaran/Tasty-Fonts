                            <div id="tasty-fonts-role-preview-panel" class="tasty-fonts-role-preview-panel" hidden>
                                <div
                                    class="tasty-fonts-preview-canvas"
                                    id="tasty-fonts-preview-canvas"
                                    style="--tasty-preview-base: <?php echo esc_attr((string) $previewSize); ?>px; --tasty-preview-heading-stack: <?php echo esc_attr($previewHeadingStack); ?>; --tasty-preview-body-stack: <?php echo esc_attr($previewBodyStack); ?>; --tasty-preview-monospace-stack: <?php echo esc_attr($previewMonospaceStack); ?>;"
                                >
                                    <div class="tasty-fonts-preview-toolbar">
                                        <div class="tasty-fonts-preview-toolbar-copy">
                                            <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Preview Workspace', 'tasty-fonts'); ?></span>
                                            <div class="tasty-fonts-preview-toolbar-meta">
                                                <span class="screen-reader-text" data-preview-source-label><?php echo esc_html(sprintf(__('Previewing: %s', 'tasty-fonts'), $previewBaselineLabel)); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tasty-fonts-preview-tabs-shell">
                                        <div class="tasty-fonts-preview-tabs tasty-fonts-tab-list" role="tablist" aria-label="<?php esc_attr_e('Preview scenarios', 'tasty-fonts'); ?>" aria-orientation="horizontal">
                                            <?php foreach ($previewPanels as $panel): ?>
                                                <?php $buttonId = 'tasty-fonts-preview-tab-' . $panel['key']; ?>
                                                <?php $panelId = 'tasty-fonts-preview-panel-' . $panel['key']; ?>
                                                <button
                                                    type="button"
                                                    class="tasty-fonts-preview-tab tasty-fonts-tab-button <?php echo !empty($panel['active']) ? 'is-active' : ''; ?>"
                                                    id="<?php echo esc_attr($buttonId); ?>"
                                                    data-tab-group="preview"
                                                    data-tab-target="<?php echo esc_attr((string) $panel['key']); ?>"
                                                    aria-selected="<?php echo !empty($panel['active']) ? 'true' : 'false'; ?>"
                                                    tabindex="<?php echo !empty($panel['active']) ? '0' : '-1'; ?>"
                                                    aria-controls="<?php echo esc_attr($panelId); ?>"
                                                    role="tab"
                                                >
                                                    <?php echo esc_html((string) ($panel['label'] ?? '')); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="tasty-fonts-preview-toolbar-actions">
                                            <button
                                                type="button"
                                                class="button button-secondary"
                                                data-preview-sync-draft
                                            >
                                                <?php echo esc_html($previewBaselineSource === 'live_sitewide' ? __('Use current draft selections', 'tasty-fonts') : __('Sync preview to role draft', 'tasty-fonts')); ?>
                                            </button>
                                            <button
                                                type="button"
                                                class="button"
                                                data-preview-reset
                                            >
                                                <?php esc_html_e('Reset preview', 'tasty-fonts'); ?>
                                            </button>
                                        </div>
                                    </div>

                                    <?php foreach ($previewPanels as $panel): ?>
                                        <?php $buttonId = 'tasty-fonts-preview-tab-' . $panel['key']; ?>
                                        <?php $panelId = 'tasty-fonts-preview-panel-' . $panel['key']; ?>
                                        <section
                                            id="<?php echo esc_attr($panelId); ?>"
                                            class="tasty-fonts-preview-scene tasty-fonts-preview-scene--<?php echo esc_attr((string) $panel['key']); ?> <?php echo !empty($panel['active']) ? 'is-active' : ''; ?>"
                                            data-tab-group="preview"
                                            data-tab-panel="<?php echo esc_attr((string) $panel['key']); ?>"
                                            role="tabpanel"
                                            aria-labelledby="<?php echo esc_attr($buttonId); ?>"
                                            <?php echo !empty($panel['active']) ? '' : 'hidden'; ?>
                                        >
                                            <?php $this->renderPreviewScene((string) $panel['key'], $previewText, $previewRoles, $monospaceRoleEnabled, $availableFamilyLabels); ?>
                                        </section>
                                    <?php endforeach; ?>

                                    <div
                                        class="tasty-fonts-preview-tray"
                                        data-preview-tray
                                        data-preview-baseline-source="<?php echo esc_attr($previewBaselineSource); ?>"
                                        data-preview-baseline-label="<?php echo esc_attr($previewBaselineLabel); ?>"
                                    >
                                        <div class="tasty-fonts-preview-tray-head">
                                            <div class="tasty-fonts-preview-tray-copy">
                                                <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Preview Roles', 'tasty-fonts'); ?></span>
                                            </div>
                                            <div class="tasty-fonts-preview-tray-actions">
                                                <button
                                                    type="button"
                                                    class="button tasty-fonts-preview-copy-css-button"
                                                    data-preview-copy-css
                                                    data-copy-text=""
                                                    data-copy-success="<?php esc_attr_e('Preview CSS copied.', 'tasty-fonts'); ?>"
                                                    data-copy-static-label="1"
                                                    <?php $this->renderPassiveHelpAttributes(__('Copy custom CSS for the current preview selection.', 'tasty-fonts')); ?>
                                                    aria-label="<?php esc_attr_e('Copy custom CSS for the current preview selection', 'tasty-fonts'); ?>"
                                                >
                                                    <span class="screen-reader-text"><?php esc_html_e('Copy CSS', 'tasty-fonts'); ?></span>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="button"
                                                    data-preview-save-draft
                                                    aria-disabled="<?php echo $previewHasDraftRoleChanges ? 'false' : 'true'; ?>"
                                                    <?php disabled(!$previewHasDraftRoleChanges); ?>
                                                >
                                                    <?php esc_html_e('Save Draft', 'tasty-fonts'); ?>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="button<?php echo $previewHasPendingLiveRoleChanges ? ' button-primary is-pending-live-change' : ''; ?>"
                                                    data-preview-apply-live
                                                    aria-disabled="<?php echo $previewHasPendingLiveRoleChanges ? 'false' : 'true'; ?>"
                                                    <?php disabled(!$previewHasPendingLiveRoleChanges); ?>
                                                >
                                                    <?php esc_html_e('Publish Roles', 'tasty-fonts'); ?>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="tasty-fonts-preview-tray-grid">
                                            <?php $this->renderPreviewRolePicker('heading', __('Heading', 'tasty-fonts'), $availableFamilyOptions, $previewRoles, $roles, true); ?>
                                            <?php $this->renderPreviewRolePicker('body', __('Body', 'tasty-fonts'), $availableFamilyOptions, $previewRoles, $roles, true); ?>
                                            <?php if ($monospaceRoleEnabled): ?>
                                                <?php $this->renderPreviewRolePicker('monospace', __('Monospace', 'tasty-fonts'), $availableFamilyOptions, $previewRoles, $roles, true); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
