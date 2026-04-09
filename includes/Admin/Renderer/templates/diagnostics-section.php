                    <section class="tasty-fonts-card tasty-fonts-diagnostics-card" id="tasty-fonts-diagnostics-page">
                        <div class="tasty-fonts-card-head tasty-fonts-card-head--activity">
                            <?php
                            $this->renderSectionHeading(
                                'h2',
                                __('Diagnostics', 'tasty-fonts')
                            );
                            ?>
                            <div class="tasty-fonts-activity-head-actions tasty-fonts-diagnostics-head-actions">
                                <?php if (!empty($generatedCssPanel['download_url'] ?? '')): ?>
                                    <a class="button button-secondary" href="<?php echo esc_url((string) $generatedCssPanel['download_url']); ?>">
                                        <?php esc_html_e('Download Generated CSS', 'tasty-fonts'); ?>
                                    </a>
                                <?php endif; ?>
                                <div class="tasty-fonts-studio-switcher tasty-fonts-tab-list tasty-fonts-diagnostics-switcher" role="tablist" aria-label="<?php esc_attr_e('Diagnostics sections', 'tasty-fonts'); ?>" aria-orientation="horizontal">
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button is-active"
                                        id="tasty-fonts-diagnostics-tab-generated"
                                        data-tab-group="diagnostics"
                                        data-tab-target="generated"
                                        aria-selected="true"
                                        tabindex="0"
                                        aria-controls="tasty-fonts-diagnostics-panel-generated"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Generated CSS', 'tasty-fonts'); ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button"
                                        id="tasty-fonts-diagnostics-tab-system"
                                        data-tab-group="diagnostics"
                                        data-tab-target="system"
                                        aria-selected="false"
                                        tabindex="-1"
                                        aria-controls="tasty-fonts-diagnostics-panel-system"
                                        role="tab"
                                    >
                                        <?php esc_html_e('System Details', 'tasty-fonts'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="tasty-fonts-code-card tasty-fonts-code-card--embedded">
                            <section
                                id="tasty-fonts-diagnostics-panel-generated"
                                class="tasty-fonts-studio-panel is-active"
                                data-tab-group="diagnostics"
                                data-tab-panel="generated"
                                role="tabpanel"
                                aria-labelledby="tasty-fonts-diagnostics-tab-generated"
                            >
                                <div class="tasty-fonts-code-card tasty-fonts-code-card--embedded">
                                    <div class="tasty-fonts-code-panel is-active">
                                        <?php
                                        $toolsRenderer->renderCodeEditor($generatedCssPanel, [
                                            'preserve_display_format' => true,
                                            'allow_readable_toggle' => $minifyCssOutput,
                                        ]);
                                        ?>
                                    </div>
                                </div>
                            </section>

                            <section
                                id="tasty-fonts-diagnostics-panel-system"
                                class="tasty-fonts-studio-panel"
                                data-tab-group="diagnostics"
                                data-tab-panel="system"
                                role="tabpanel"
                                aria-labelledby="tasty-fonts-diagnostics-tab-system"
                                hidden
                            >
                                <div class="tasty-fonts-system-details-panel">
                                    <div class="tasty-fonts-diagnostics-grid">
                                        <?php foreach ($diagnosticItems as $item): ?>
                                                <?php
                                                $itemValue = (string) ($item['value'] ?? '');
                                                $isCopyable = !empty($item['copyable']) && $itemValue !== '';
                                                $valueClass = !empty($item['code'])
                                                    ? 'tasty-fonts-diagnostic-value tasty-fonts-code'
                                                    : 'tasty-fonts-diagnostic-value';
                                                $itemClass = $isCopyable
                                                    ? 'tasty-fonts-diagnostic-item tasty-fonts-diagnostic-item--copyable'
                                                    : 'tasty-fonts-diagnostic-item';
                                                $itemLabel = (string) ($item['label'] ?? 'value');
                                                ?>
                                            <div class="<?php echo esc_attr($itemClass); ?>">
                                                <div class="tasty-fonts-diagnostic-label"><?php echo esc_html($itemLabel); ?></div>
                                                <?php if ($isCopyable): ?>
                                                    <div class="tasty-fonts-diagnostic-item-actions">
                                                        <button
                                                            type="button"
                                                            class="button tasty-fonts-output-copy-button tasty-fonts-diagnostic-copy-button"
                                                            data-copy-text="<?php echo esc_attr($itemValue); ?>"
                                                            data-copy-success="<?php echo esc_attr(sprintf(__('%s copied.', 'tasty-fonts'), $itemLabel)); ?>"
                                                            data-copy-static-label="1"
                                                            aria-label="<?php echo esc_attr(sprintf(__('Copy %s', 'tasty-fonts'), $itemLabel)); ?>"
                                                        >
                                                            <span class="screen-reader-text"><?php echo esc_html(sprintf(__('Copy %s', 'tasty-fonts'), $itemLabel)); ?></span>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="<?php echo esc_attr($valueClass); ?>">
                                                    <?php echo esc_html($itemValue); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </section>
