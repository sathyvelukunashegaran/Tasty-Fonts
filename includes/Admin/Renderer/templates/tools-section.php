                            <div id="tasty-fonts-role-snippets-panel" class="tasty-fonts-role-advanced-panel" hidden>
                                <div class="tasty-fonts-snippets-canvas">
                                    <div class="tasty-fonts-snippets-toolbar">
                                        <div class="tasty-fonts-snippets-toolbar-copy">
                                            <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Snippets Workspace', 'tasty-fonts'); ?></span>
                                        </div>
                                    </div>

                                    <div class="tasty-fonts-snippets-tabs-shell">
                                        <div class="tasty-fonts-code-tabs tasty-fonts-tab-list" role="tablist" aria-label="<?php esc_attr_e('Font output panels', 'tasty-fonts'); ?>" aria-orientation="horizontal">
                                            <?php foreach ($outputPanels as $panel): ?>
                                                <?php $buttonId = 'tasty-fonts-output-tab-' . $panel['key']; ?>
                                                <?php $panelId = 'tasty-fonts-output-panel-' . $panel['key']; ?>
                                                <button
                                                    type="button"
                                                    class="tasty-fonts-code-tab tasty-fonts-tab-button <?php echo !empty($panel['active']) ? 'is-active' : ''; ?>"
                                                    id="<?php echo esc_attr($buttonId); ?>"
                                                    data-tab-group="output"
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
                                    </div>

                                    <?php foreach ($outputPanels as $panel): ?>
                                        <?php $buttonId = 'tasty-fonts-output-tab-' . $panel['key']; ?>
                                        <?php $panelId = 'tasty-fonts-output-panel-' . $panel['key']; ?>
                                        <section
                                            id="<?php echo esc_attr($panelId); ?>"
                                            class="tasty-fonts-code-panel <?php echo !empty($panel['active']) ? 'is-active' : ''; ?>"
                                            data-tab-group="output"
                                            data-tab-panel="<?php echo esc_attr((string) $panel['key']); ?>"
                                            role="tabpanel"
                                            aria-labelledby="<?php echo esc_attr($buttonId); ?>"
                                            <?php echo !empty($panel['active']) ? '' : 'hidden'; ?>
                                        >
                                            <?php $this->renderCodeEditor($panel); ?>
                                        </section>
                                    <?php endforeach; ?>
                                </div>
                            </div>
