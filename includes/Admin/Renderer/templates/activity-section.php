                    <section class="tasty-fonts-card tasty-fonts-activity-card">
                        <div class="tasty-fonts-card-head tasty-fonts-card-head--activity">
                            <?php
                            $this->renderSectionHeading(
                                'h2',
                                __('Activity', 'tasty-fonts'),
                                __('Recent scans, imports, deletes, and asset refreshes. Newest entries appear first.', 'tasty-fonts')
                            );
                            $logCount = count($logs);
                            ?>
                            <div class="tasty-fonts-activity-head-actions">
                                <?php if ($logs !== []): ?>
                                    <div class="tasty-fonts-activity-toolbar" role="group" aria-label="<?php esc_attr_e('Activity filters', 'tasty-fonts'); ?>">
                                        <span
                                            id="tasty-fonts-activity-count"
                                            class="tasty-fonts-badge"
                                            data-activity-count
                                            data-total-count="<?php echo esc_attr((string) $logCount); ?>"
                                        >
                                            <?php echo esc_html(sprintf($logCount === 1 ? __('%d entry', 'tasty-fonts') : __('%d entries', 'tasty-fonts'), $logCount)); ?>
                                        </span>
                                        <label class="screen-reader-text" for="tasty-fonts-activity-actor-filter"><?php esc_html_e('Filter activity by account', 'tasty-fonts'); ?></label>
                                        <span class="tasty-fonts-select-field tasty-fonts-activity-select">
                                            <select id="tasty-fonts-activity-actor-filter" data-activity-actor-filter>
                                                <option value=""><?php esc_html_e('All Accounts', 'tasty-fonts'); ?></option>
                                                <?php foreach ($activityActorOptions as $actor): ?>
                                                    <option value="<?php echo esc_attr((string) $actor); ?>"><?php echo esc_html((string) $actor); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </span>
                                        <label class="screen-reader-text" for="tasty-fonts-activity-search"><?php esc_html_e('Search activity', 'tasty-fonts'); ?></label>
                                        <span class="tasty-fonts-search-field--compact tasty-fonts-search-field--activity">
                                            <input
                                                type="search"
                                                id="tasty-fonts-activity-search"
                                                placeholder="<?php esc_attr_e('Search activity', 'tasty-fonts'); ?>"
                                                autocomplete="off"
                                                data-activity-search
                                            >
                                        </span>
                                        <form method="post">
                                            <?php wp_nonce_field('tasty_fonts_clear_log'); ?>
                                            <button type="submit" class="button tasty-fonts-button-danger" name="tasty_fonts_clear_log" value="1"><?php esc_html_e('Clear Log', 'tasty-fonts'); ?></button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($logs === []): ?>
                            <div class="tasty-fonts-empty-state tasty-fonts-empty-state--rich tasty-fonts-empty-state--activity tasty-fonts-activity-empty">
                                <div class="tasty-fonts-empty-state-body">
                                    <h3 class="tasty-fonts-empty-state-title"><?php esc_html_e('No activity yet', 'tasty-fonts'); ?></h3>
                                    <p class="tasty-fonts-empty-state-copy"><?php esc_html_e('Scans, imports, deletes, and generated stylesheet refreshes will appear here after you start managing fonts.', 'tasty-fonts'); ?></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="tasty-fonts-activity-shell">
                                <div id="tasty-fonts-activity-empty-filtered" class="tasty-fonts-empty tasty-fonts-empty--panel tasty-fonts-activity-empty" hidden><?php esc_html_e('No activity matches the current filters.', 'tasty-fonts'); ?></div>
                                <?php $this->renderLogList($logs); ?>
                            </div>
                        <?php endif; ?>
                    </section>
                    <?php $this->renderEnvironmentNotice($localEnvironmentNotice); ?>
                    <?php $this->renderFallbackSuggestionList(); ?>
                    <div id="tasty-fonts-help-tooltip-layer" class="tasty-fonts-help-tooltip-layer" role="tooltip" hidden></div>
