                    <?php
                    $diagnosticItems = isset($diagnosticItems) && is_array($diagnosticItems) ? $diagnosticItems : [];
                    $overviewMetrics = isset($overviewMetrics) && is_array($overviewMetrics) ? $overviewMetrics : [];
                    $developerToolStatuses = isset($developerToolStatuses) && is_array($developerToolStatuses) ? $developerToolStatuses : [];
                    $generatedCssPanel = isset($generatedCssPanel) && is_array($generatedCssPanel) ? $generatedCssPanel : [];
                    $advancedTools = isset($advancedTools) && is_array($advancedTools) ? $advancedTools : [];
                    $healthSummary = is_array($advancedTools['health_summary'] ?? null) ? $advancedTools['health_summary'] : [];
                    $siteTransfer = isset($siteTransfer) && is_array($siteTransfer) ? $siteTransfer : [];
                    $logs = isset($logs) && is_array($logs) ? $logs : [];
                    $activityActorOptions = isset($activityActorOptions) && is_array($activityActorOptions) ? $activityActorOptions : [];
                    $showSettingsDescriptions = empty($trainingWheelsOff);
                    $showActivityLog = !empty($showActivityLog);
                    $generatedCssAvailable = trim((string) ($generatedCssPanel['value'] ?? '')) !== '';
                    $generatedCssDownloadUrl = trim((string) ($generatedCssPanel['download_url'] ?? ''));
                    $systemIssueCount = 0;

                    foreach ($diagnosticItems as $item) {
                        $value = strtolower(trim((string) ($item['value'] ?? '')));

                        if ($value === '' || str_contains($value, 'not available') || str_contains($value, 'not generated')) {
                            $systemIssueCount++;
                        }
                    }

                    $healthSummaryLabel = trim((string) ($healthSummary['label'] ?? ''));

                    $commandCenterStats = [
                        [
                            'label' => __('Generated CSS', 'tasty-fonts'),
                            'value' => $generatedCssAvailable ? __('Ready', 'tasty-fonts') : __('Unavailable', 'tasty-fonts'),
                        ],
                        [
                            'label' => __('System Checks', 'tasty-fonts'),
                            'value' => $healthSummaryLabel !== ''
                                ? $healthSummaryLabel
                                : ($systemIssueCount > 0
                                ? sprintf(
                                    /* translators: %d: number of diagnostic warnings */
                                    _n('%d warning', '%d warnings', $systemIssueCount, 'tasty-fonts'),
                                    $systemIssueCount
                                )
                                : __('Clear', 'tasty-fonts')),
                        ],
                        [
                            'label' => __('Activity', 'tasty-fonts'),
                            'value' => sprintf(
                                /* translators: %d: number of activity log entries */
                                _n('%d event', '%d events', count($logs), 'tasty-fonts'),
                                count($logs)
                            ),
                        ],
                    ];

                    foreach ($overviewMetrics as $metric) {
                        $commandCenterStats[] = [
                            'label' => (string) ($metric['label'] ?? ''),
                            'value' => (string) ($metric['value'] ?? ''),
                        ];
                    }

                    $developerToolGroups = [
                        [
                            'slug' => 'maintenance',
                            'title' => __('Maintenance', 'tasty-fonts'),
                            'description' => '',
                            'actions' => [
                                [
                                    'slug' => 'clear_plugin_caches',
                                    'title' => __('Clear caches and rebuild assets', 'tasty-fonts'),
                                    'description' => __('Clears plugin caches and rebuilds generated CSS.', 'tasty-fonts'),
                                    'nonce' => 'tasty_fonts_clear_plugin_caches',
                                    'action_name' => 'tasty_fonts_clear_plugin_caches',
                                    'button_label' => __('Clear Caches & Rebuild', 'tasty-fonts'),
                                    'button_class' => 'button button-small tasty-fonts-developer-action-button',
                                ],
                                [
                                    'slug' => 'regenerate_css',
                                    'title' => __('Regenerate CSS file', 'tasty-fonts'),
                                    'description' => __('Rebuilds the generated stylesheet only.', 'tasty-fonts'),
                                    'nonce' => 'tasty_fonts_regenerate_css',
                                    'action_name' => 'tasty_fonts_regenerate_css',
                                    'button_label' => __('Regenerate CSS File', 'tasty-fonts'),
                                    'button_class' => 'button button-small tasty-fonts-developer-action-button',
                                ],
                                [
                                    'slug' => 'reset_integration_detection_state',
                                    'title' => __('Run integration scan', 'tasty-fonts'),
                                    'description' => __('Re-detects supported integrations on the next admin load.', 'tasty-fonts'),
                                    'nonce' => 'tasty_fonts_reset_integration_detection_state',
                                    'action_name' => 'tasty_fonts_reset_integration_detection_state',
                                    'button_label' => __('Run Integration Scan', 'tasty-fonts'),
                                    'button_class' => 'button button-small tasty-fonts-developer-action-button',
                                    'confirm_message' => __('Reset stored integration detection and ACSS bookkeeping?', 'tasty-fonts'),
                                ],
                                [
                                    'slug' => 'reset_suppressed_notices',
                                    'title' => __('Restore notices', 'tasty-fonts'),
                                    'description' => __('Shows dismissed onboarding and reminder notices again.', 'tasty-fonts'),
                                    'nonce' => 'tasty_fonts_reset_suppressed_notices',
                                    'action_name' => 'tasty_fonts_reset_suppressed_notices',
                                    'button_label' => __('Restore Notices', 'tasty-fonts'),
                                    'button_class' => 'button button-small tasty-fonts-developer-action-button',
                                ],
                            ],
                        ],
                        [
                            'slug' => 'danger',
                            'title' => __('Danger Zone', 'tasty-fonts'),
                            'description' => '',
                            'group_class' => 'tasty-fonts-developer-tool-group--danger',
                            'actions' => [
                                [
                                    'slug' => 'reset_plugin_settings',
                                    'title' => __('Reset plugin settings only', 'tasty-fonts'),
                                    'description' => __('Resets settings and access rules. Keeps your managed library and files.', 'tasty-fonts'),
                                    'nonce' => 'tasty_fonts_reset_plugin_settings',
                                    'action_name' => 'tasty_fonts_reset_plugin_settings',
                                    'button_label' => __('Reset Settings Only', 'tasty-fonts'),
                                    'button_class' => 'button button-small tasty-fonts-button-danger tasty-fonts-developer-action-button',
                                    'card_class' => 'tasty-fonts-developer-tool-card--danger',
                                    'confirm_message' => __('Reset plugin settings to defaults while keeping the font library and files?', 'tasty-fonts'),
                                    'confirm_phrase' => 'RESET SETTINGS',
                                ],
                                [
                                    'slug' => 'wipe_managed_font_library',
                                    'title' => __('Delete managed font library', 'tasty-fonts'),
                                    'description' => __('Deletes managed fonts and rebuilds empty storage.', 'tasty-fonts'),
                                    'nonce' => 'tasty_fonts_wipe_managed_font_library',
                                    'action_name' => 'tasty_fonts_wipe_managed_font_library',
                                    'button_label' => __('Delete Managed Library', 'tasty-fonts'),
                                    'button_class' => 'button button-small tasty-fonts-button-danger tasty-fonts-developer-action-button',
                                    'card_class' => 'tasty-fonts-developer-tool-card--danger',
                                    'confirm_message' => __('Wipe the managed font library, remove managed files, and rebuild empty storage?', 'tasty-fonts'),
                                    'confirm_phrase' => 'WIPE LIBRARY',
                                ],
                            ],
                        ],
                    ];

                    $siteTransferAvailable = !empty($siteTransfer['available']);
                    $siteTransferMessage = trim((string) ($siteTransfer['message'] ?? ''));
                    $siteTransferExportUrl = (string) ($siteTransfer['export_url'] ?? '');
                    $siteTransferImportFileField = (string) ($siteTransfer['import_file_field'] ?? 'tasty_fonts_site_transfer_bundle');
                    $siteTransferImportStageTokenField = (string) ($siteTransfer['import_stage_token_field'] ?? 'tasty_fonts_site_transfer_stage_token');
                    $siteTransferImportGoogleField = (string) ($siteTransfer['import_google_api_key_field'] ?? 'tasty_fonts_import_google_api_key');
                    $siteTransferImportAction = (string) ($siteTransfer['import_action_field'] ?? 'tasty_fonts_import_site_transfer_bundle');
                    $siteTransferImportStatus = is_array($siteTransfer['import_status'] ?? null) ? $siteTransfer['import_status'] : [];
                    $siteTransferLogs = is_array($siteTransfer['logs'] ?? null) ? $siteTransfer['logs'] : [];
                    $siteTransferActorOptions = is_array($siteTransfer['actor_options'] ?? null) ? $siteTransfer['actor_options'] : [];
                    $siteTransferUploadLimitLabel = trim((string) ($siteTransfer['effective_upload_limit_label'] ?? ''));
                    $advancedToolHeadings = [
                        'overview' => [
                            'title' => __('Overview', 'tasty-fonts'),
                            'description' => __('Runtime health, asset paths, and library status in one place.', 'tasty-fonts'),
                        ],
                        'generated' => [
                            'title' => __('Generated CSS', 'tasty-fonts'),
                            'description' => __('Inspect the exact stylesheet Tasty Fonts is serving at runtime.', 'tasty-fonts'),
                        ],
                        'maintenance' => [
                            'title' => __('Developer', 'tasty-fonts'),
                            'description' => __('Run guarded maintenance, recovery, and reset actions.', 'tasty-fonts'),
                        ],
                        'transfer' => [
                            'title' => __('Site Transfer', 'tasty-fonts'),
                            'description' => __('Move a Tasty Fonts setup between sites without copying runtime cache, logs, or secrets.', 'tasty-fonts'),
                        ],
                        'activity' => [
                            'title' => __('Activity', 'tasty-fonts'),
                            'description' => __('Review recent scans, imports, deletes, and asset refreshes.', 'tasty-fonts'),
                        ],
                    ];
                    ?>
                    <section class="tasty-fonts-card tasty-fonts-diagnostics-card" id="tasty-fonts-diagnostics-page">
                        <div class="tasty-fonts-card-head tasty-fonts-card-head--activity">
                            <div class="tasty-fonts-diagnostics-context">
                                <?php foreach ($advancedToolHeadings as $headingKey => $heading): ?>
                                    <?php $isActiveHeading = $headingKey === 'overview'; ?>
                                    <div
                                        class="tasty-fonts-diagnostics-context-item <?php echo $isActiveHeading ? 'is-active' : ''; ?>"
                                        data-tab-heading-group="diagnostics"
                                        data-tab-heading="<?php echo esc_attr($headingKey); ?>"
                                        <?php if (!$isActiveHeading): ?>hidden<?php endif; ?>
                                    >
                                        <h2><?php echo esc_html((string) ($heading['title'] ?? '')); ?></h2>
                                        <?php if ($showSettingsDescriptions && !empty($heading['description'])): ?>
                                            <p><?php echo esc_html((string) $heading['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="tasty-fonts-activity-head-actions tasty-fonts-diagnostics-head-actions">
                                <div class="tasty-fonts-studio-switcher tasty-fonts-tab-list tasty-fonts-diagnostics-switcher" role="tablist" aria-label="<?php esc_attr_e('Advanced Tools sections', 'tasty-fonts'); ?>" aria-orientation="horizontal">
                                    <?php
                                    $advancedToolTabs = [
                                        'overview' => __('Overview', 'tasty-fonts'),
                                        'generated' => __('Generated CSS', 'tasty-fonts'),
                                        'maintenance' => __('Developer', 'tasty-fonts'),
                                        'transfer' => __('Transfer', 'tasty-fonts'),
                                        'activity' => __('Activity', 'tasty-fonts'),
                                    ];
                                    ?>
                                    <?php foreach ($advancedToolTabs as $tabKey => $tabLabel): ?>
                                        <?php $isActiveTab = $tabKey === 'overview'; ?>
                                        <button
                                            type="button"
                                            class="tasty-fonts-studio-tab tasty-fonts-tab-button <?php echo $isActiveTab ? 'is-active' : ''; ?>"
                                            id="tasty-fonts-diagnostics-tab-<?php echo esc_attr($tabKey); ?>"
                                            data-tab-group="diagnostics"
                                            data-tab-target="<?php echo esc_attr($tabKey); ?>"
                                            aria-selected="<?php echo $isActiveTab ? 'true' : 'false'; ?>"
                                            tabindex="<?php echo $isActiveTab ? '0' : '-1'; ?>"
                                            aria-controls="tasty-fonts-diagnostics-panel-<?php echo esc_attr($tabKey); ?>"
                                            role="tab"
                                        >
                                            <?php echo esc_html($tabLabel); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="tasty-fonts-code-card tasty-fonts-code-card--embedded tasty-fonts-advanced-tools-workbench">
                            <section
                                id="tasty-fonts-diagnostics-panel-overview"
                                class="tasty-fonts-studio-panel is-active"
                                data-tab-group="diagnostics"
                                data-tab-panel="overview"
                                role="tabpanel"
                                aria-labelledby="tasty-fonts-diagnostics-tab-overview"
                            >
                                <div class="tasty-fonts-advanced-overview-grid">
                                    <?php foreach ($commandCenterStats as $metric): ?>
                                        <?php
                                        $metricLabel = trim((string) ($metric['label'] ?? ''));
                                        $metricValue = trim((string) ($metric['value'] ?? ''));

                                        if ($metricLabel === '' || $metricValue === '') {
                                            continue;
                                        }
                                        ?>
                                        <div class="tasty-fonts-diagnostic-item">
                                            <div class="tasty-fonts-diagnostic-label"><?php echo esc_html($metricLabel); ?></div>
                                            <div class="tasty-fonts-diagnostic-value"><?php echo esc_html($metricValue); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="tasty-fonts-system-details-panel">
                                    <div class="tasty-fonts-output-settings-copy">
                                        <h3><?php esc_html_e('System Details', 'tasty-fonts'); ?></h3>
                                        <?php if ($showSettingsDescriptions): ?>
                                            <p><?php esc_html_e('Copy exact runtime paths, URLs, and status values when debugging generated assets.', 'tasty-fonts'); ?></p>
                                        <?php endif; ?>
                                    </div>
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

                            <section
                                id="tasty-fonts-diagnostics-panel-generated"
                                class="tasty-fonts-studio-panel"
                                data-tab-group="diagnostics"
                                data-tab-panel="generated"
                                role="tabpanel"
                                aria-labelledby="tasty-fonts-diagnostics-tab-generated"
                                hidden
                            >
                                <div class="tasty-fonts-code-panel is-active">
                                    <?php
                                    $toolsRenderer->renderCodeEditor($generatedCssPanel, [
                                        'preserve_display_format' => true,
                                        'allow_readable_toggle' => !empty($minifyCssOutput),
                                        'download_url' => $generatedCssDownloadUrl,
                                        'download_label' => __('Download Generated CSS', 'tasty-fonts'),
                                    ]);
                                    ?>
                                </div>
                            </section>

                            <section
                                id="tasty-fonts-diagnostics-panel-maintenance"
                                class="tasty-fonts-studio-panel"
                                data-tab-group="diagnostics"
                                data-tab-panel="maintenance"
                                role="tabpanel"
                                aria-labelledby="tasty-fonts-diagnostics-tab-maintenance"
                                hidden
                            >
                                <div class="tasty-fonts-output-settings-panel tasty-fonts-developer-panel">
                                    <div class="tasty-fonts-page-notice tasty-fonts-inline-note tasty-fonts-developer-tools-note">
                                        <strong><?php esc_html_e('Maintenance actions', 'tasty-fonts'); ?></strong>
                                        <span><?php esc_html_e('These tools run immediately and do not use Save changes.', 'tasty-fonts'); ?></span>
                                    </div>
                                    <div class="tasty-fonts-page-notice tasty-fonts-inline-note tasty-fonts-inline-note--warning tasty-fonts-developer-tools-note" data-developer-dirty-notice hidden>
                                        <strong><?php esc_html_e('Save settings first', 'tasty-fonts'); ?></strong>
                                        <span><?php esc_html_e('Pending settings changes temporarily disable developer actions.', 'tasty-fonts'); ?></span>
                                    </div>
                                    <div class="tasty-fonts-output-settings-list tasty-fonts-developer-tool-sections">
                                        <?php foreach ($developerToolGroups as $developerToolGroup): ?>
                                            <?php
                                            $developerToolGroupSlug = isset($developerToolGroup['slug']) ? sanitize_html_class((string) $developerToolGroup['slug']) : 'group';
                                            $developerToolGroupClass = 'tasty-fonts-developer-tool-group';

                                            if (!empty($developerToolGroup['group_class'])) {
                                                $developerToolGroupClass .= ' ' . trim((string) $developerToolGroup['group_class']);
                                            }
                                            ?>
                                            <section class="<?php echo esc_attr($developerToolGroupClass); ?>" aria-labelledby="tasty-fonts-advanced-developer-group-<?php echo esc_attr($developerToolGroupSlug); ?>">
                                                <div class="tasty-fonts-developer-tool-group-header">
                                                    <h3 id="tasty-fonts-advanced-developer-group-<?php echo esc_attr($developerToolGroupSlug); ?>"><?php echo esc_html((string) $developerToolGroup['title']); ?></h3>
                                                    <?php if ($showSettingsDescriptions && !empty($developerToolGroup['description'])): ?>
                                                        <p><?php echo esc_html((string) $developerToolGroup['description']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="tasty-fonts-developer-tool-group-list">
                                                    <?php foreach (($developerToolGroup['actions'] ?? []) as $developerToolAction): ?>
                                                        <?php
                                                        $developerToolCardClass = 'tasty-fonts-developer-tool-card';
                                                        $developerToolSlug = sanitize_key((string) ($developerToolAction['slug'] ?? ''));
                                                        $developerToolStatus = $developerToolSlug !== '' && isset($developerToolStatuses[$developerToolSlug]) && is_array($developerToolStatuses[$developerToolSlug])
                                                            ? $developerToolStatuses[$developerToolSlug]
                                                            : [];
                                                        $developerToolSummary = trim((string) ($developerToolStatus['summary'] ?? ''));
                                                        $developerToolLastRun = trim((string) ($developerToolStatus['last_run'] ?? ''));
                                                        $developerToolConfirmPhrase = trim((string) ($developerToolAction['confirm_phrase'] ?? ''));

                                                        if (!empty($developerToolAction['card_class'])) {
                                                            $developerToolCardClass .= ' ' . trim((string) $developerToolAction['card_class']);
                                                        }
                                                        ?>
                                                        <div class="<?php echo esc_attr($developerToolCardClass); ?>">
                                                            <div class="tasty-fonts-developer-tool-card-copy">
                                                                <div class="tasty-fonts-developer-tool-title-row">
                                                                    <p class="tasty-fonts-developer-tool-card-title"><?php echo esc_html((string) $developerToolAction['title']); ?></p>
                                                                </div>
                                                                <?php if ($showSettingsDescriptions && !empty($developerToolAction['description'])): ?>
                                                                    <p><?php echo esc_html((string) $developerToolAction['description']); ?></p>
                                                                <?php endif; ?>
                                                                <?php if ($developerToolSummary !== '' || $developerToolLastRun !== ''): ?>
                                                                    <div class="tasty-fonts-developer-tool-meta">
                                                                        <?php if ($developerToolSummary !== ''): ?>
                                                                            <p class="tasty-fonts-developer-tool-meta-summary"><?php echo esc_html($developerToolSummary); ?></p>
                                                                        <?php endif; ?>
                                                                        <?php if ($developerToolLastRun !== ''): ?>
                                                                            <p class="tasty-fonts-developer-tool-meta-last-run"><?php echo esc_html($developerToolLastRun); ?></p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <form
                                                                method="post"
                                                                class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-developer-tool-card-actions"
                                                                data-developer-tool-form
                                                                <?php if (!empty($developerToolAction['confirm_message'])): ?>data-developer-confirm-message="<?php echo esc_attr((string) $developerToolAction['confirm_message']); ?>"<?php endif; ?>
                                                                <?php if ($developerToolConfirmPhrase !== ''): ?>data-developer-confirm-input="<?php echo esc_attr($developerToolConfirmPhrase); ?>"<?php endif; ?>
                                                            >
                                                                <?php wp_nonce_field((string) $developerToolAction['nonce']); ?>
                                                                <div class="tasty-fonts-developer-action-row">
                                                                    <button type="submit" class="<?php echo esc_attr((string) $developerToolAction['button_class']); ?>" name="<?php echo esc_attr((string) $developerToolAction['action_name']); ?>" value="1" data-developer-submit><?php echo esc_html((string) $developerToolAction['button_label']); ?></button>
                                                                </div>
                                                                <?php if ($developerToolConfirmPhrase !== ''): ?>
                                                                    <div class="tasty-fonts-developer-confirm-lock" data-developer-confirm-lock hidden>
                                                                        <label class="tasty-fonts-developer-confirm-label" for="<?php echo esc_attr('tasty-fonts-advanced-developer-confirm-' . $developerToolSlug); ?>">
                                                                            <?php
                                                                            echo esc_html(
                                                                                sprintf(
                                                                                    __('Type %s to unlock this action.', 'tasty-fonts'),
                                                                                    $developerToolConfirmPhrase
                                                                                )
                                                                            );
                                                                            ?>
                                                                        </label>
                                                                        <div class="tasty-fonts-developer-confirm-input-row">
                                                                            <input
                                                                                type="text"
                                                                                id="<?php echo esc_attr('tasty-fonts-advanced-developer-confirm-' . $developerToolSlug); ?>"
                                                                                class="tasty-fonts-text-control tasty-fonts-developer-confirm-input"
                                                                                data-developer-confirm-field
                                                                                autocomplete="off"
                                                                                spellcheck="false"
                                                                            >
                                                                            <button type="button" class="button button-secondary tasty-fonts-developer-confirm-cancel" data-developer-confirm-cancel><?php esc_html_e('Cancel', 'tasty-fonts'); ?></button>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </form>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </section>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </section>

                            <section
                                id="tasty-fonts-diagnostics-panel-transfer"
                                class="tasty-fonts-studio-panel"
                                data-tab-group="diagnostics"
                                data-tab-panel="transfer"
                                role="tabpanel"
                                aria-labelledby="tasty-fonts-diagnostics-tab-transfer"
                                hidden
                            >
                                <div class="tasty-fonts-output-settings-panel tasty-fonts-developer-panel">
                                    <section class="tasty-fonts-site-transfer-panel">
                                        <div class="tasty-fonts-site-transfer-grid">
                                            <article class="tasty-fonts-site-transfer-card tasty-fonts-site-transfer-card--export">
                                                <div class="tasty-fonts-site-transfer-card-top">
                                                    <div class="tasty-fonts-site-transfer-card-intro">
                                                        <div class="tasty-fonts-developer-tool-title-row">
                                                            <h4><?php esc_html_e('Export Site Transfer Bundle', 'tasty-fonts'); ?></h4>
                                                        </div>
                                                        <?php if ($showSettingsDescriptions): ?>
                                                            <p><?php esc_html_e('Download one bundle for another Tasty Fonts site.', 'tasty-fonts'); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="tasty-fonts-site-transfer-card-meta">
                                                        <ul class="tasty-fonts-site-transfer-points" aria-label="<?php esc_attr_e('Export bundle contents', 'tasty-fonts'); ?>">
                                                            <li><?php esc_html_e('Includes saved settings, live roles, library metadata, and managed font files.', 'tasty-fonts'); ?></li>
                                                            <li><?php esc_html_e('Excludes Google API keys, generated CSS, logs, and transient runtime state.', 'tasty-fonts'); ?></li>
                                                        </ul>
                                                        <?php if (!$siteTransferAvailable && $siteTransferMessage !== ''): ?>
                                                            <p class="tasty-fonts-site-transfer-note tasty-fonts-site-transfer-note--muted"><?php echo esc_html($siteTransferMessage); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="tasty-fonts-site-transfer-card-foot">
                                                    <?php if ($siteTransferAvailable && $siteTransferExportUrl !== ''): ?>
                                                        <a class="button tasty-fonts-developer-action-button tasty-fonts-site-transfer-button" href="<?php echo esc_url($siteTransferExportUrl); ?>">
                                                            <?php esc_html_e('Export Bundle', 'tasty-fonts'); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <button type="button" class="button tasty-fonts-developer-action-button tasty-fonts-site-transfer-button" disabled>
                                                            <?php esc_html_e('Export Bundle', 'tasty-fonts'); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </article>

                                            <article class="tasty-fonts-site-transfer-card tasty-fonts-site-transfer-card--import">
                                            <form
                                                id="tasty-fonts-site-transfer-form"
                                                method="post"
                                                enctype="multipart/form-data"
                                                class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-site-transfer-form"
                                                data-developer-confirm-message="<?php echo esc_attr__('Replace the current Tasty Fonts settings, library, and managed files with the uploaded site transfer bundle?', 'tasty-fonts'); ?>"
                                                data-site-transfer-form
                                                data-site-transfer-max-bytes="<?php echo esc_attr((string) ($siteTransfer['effective_upload_limit_bytes'] ?? '')); ?>"
                                                data-site-transfer-max-label="<?php echo esc_attr((string) ($siteTransfer['effective_upload_limit_label'] ?? '')); ?>"
                                            >
                                                <?php wp_nonce_field($siteTransferImportAction); ?>
                                                <input type="hidden" name="<?php echo esc_attr($siteTransferImportAction); ?>" value="1">
                                                <input type="hidden" name="<?php echo esc_attr($siteTransferImportStageTokenField); ?>" value="" data-site-transfer-stage-token>
                                                <div class="tasty-fonts-site-transfer-card-top">
                                                    <div class="tasty-fonts-site-transfer-card-intro">
                                                        <div class="tasty-fonts-developer-tool-title-row">
                                                            <h4><?php esc_html_e('Dry Run Bundle', 'tasty-fonts'); ?></h4>
                                                        </div>
                                                        <p class="tasty-fonts-site-transfer-intro-copy"><?php esc_html_e('Validate first, then import to replace this site’s Tasty setup.', 'tasty-fonts'); ?></p>
                                                    </div>
                                                    <div class="tasty-fonts-site-transfer-import-stack">
                                                        <div class="tasty-fonts-site-transfer-import-grid">
                                                            <div class="tasty-fonts-site-transfer-field">
                                                                <span class="tasty-fonts-field-label"><?php esc_html_e('Transfer Bundle', 'tasty-fonts'); ?></span>
                                                                <div class="tasty-fonts-site-transfer-file-picker">
                                                                    <input
                                                                        type="file"
                                                                        id="tasty-fonts-advanced-site-transfer-bundle-upload"
                                                                        class="screen-reader-text"
                                                                        name="<?php echo esc_attr($siteTransferImportFileField); ?>"
                                                                        accept=".zip,application/zip"
                                                                        data-site-transfer-file-input
                                                                        <?php disabled(!$siteTransferAvailable); ?>
                                                                    >
                                                                    <div class="tasty-fonts-site-transfer-file-copy">
                                                                        <span class="tasty-fonts-site-transfer-file-label"><?php esc_html_e('ZIP bundle', 'tasty-fonts'); ?></span>
                                                                        <span class="tasty-fonts-site-transfer-file-name" data-site-transfer-file-name><?php esc_html_e('No bundle selected', 'tasty-fonts'); ?></span>
                                                                    </div>
                                                                    <label for="tasty-fonts-advanced-site-transfer-bundle-upload" class="button tasty-fonts-site-transfer-picker-trigger<?php echo !$siteTransferAvailable ? ' is-disabled' : ''; ?>">
                                                                        <?php esc_html_e('Choose File', 'tasty-fonts'); ?>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <label for="tasty-fonts-advanced-site-transfer-google-api-key" class="tasty-fonts-site-transfer-field tasty-fonts-site-transfer-field--secret">
                                                                <span class="tasty-fonts-field-label"><?php esc_html_e('Google Fonts API Key', 'tasty-fonts'); ?></span>
                                                                <input
                                                                    type="text"
                                                                    id="tasty-fonts-advanced-site-transfer-google-api-key"
                                                                    class="regular-text tasty-fonts-text-control tasty-fonts-site-transfer-input"
                                                                    name="<?php echo esc_attr($siteTransferImportGoogleField); ?>"
                                                                    value=""
                                                                    placeholder="<?php echo esc_attr__('Optional on the destination site', 'tasty-fonts'); ?>"
                                                                    <?php disabled(!$siteTransferAvailable); ?>
                                                                >
                                                            </label>
                                                        </div>
                                                        <div class="tasty-fonts-site-transfer-summary-wrap">
                                                            <div class="tasty-fonts-site-transfer-summary" aria-label="<?php esc_attr_e('Import readiness', 'tasty-fonts'); ?>">
                                                                <div class="tasty-fonts-site-transfer-summary-item" data-state="neutral">
                                                                    <span class="tasty-fonts-site-transfer-summary-label"><?php esc_html_e('Bundle', 'tasty-fonts'); ?></span>
                                                                    <span class="tasty-fonts-site-transfer-summary-value" data-site-transfer-summary="bundle"><?php esc_html_e('No bundle selected', 'tasty-fonts'); ?></span>
                                                                </div>
                                                                <div class="tasty-fonts-site-transfer-summary-item" data-state="neutral">
                                                                    <span class="tasty-fonts-site-transfer-summary-label"><?php esc_html_e('Upload limit', 'tasty-fonts'); ?></span>
                                                                    <span class="tasty-fonts-site-transfer-summary-value" data-site-transfer-summary="limit">
                                                                        <?php
                                                                        echo esc_html(
                                                                            $siteTransferUploadLimitLabel !== ''
                                                                                ? sprintf(__('Ready to validate against the %s upload limit.', 'tasty-fonts'), $siteTransferUploadLimitLabel)
                                                                                : __('Ready to validate the selected bundle before import.', 'tasty-fonts')
                                                                        );
                                                                        ?>
                                                                    </span>
                                                                </div>
                                                                <div class="tasty-fonts-site-transfer-summary-item" data-state="neutral">
                                                                    <span class="tasty-fonts-site-transfer-summary-label"><?php esc_html_e('Google API key', 'tasty-fonts'); ?></span>
                                                                    <span class="tasty-fonts-site-transfer-summary-value" data-site-transfer-summary="google"><?php esc_html_e('Optional - not provided', 'tasty-fonts'); ?></span>
                                                                </div>
                                                            </div>
                                                            <?php if ($siteTransferImportStatus !== []): ?>
                                                                <?php
                                                                $siteTransferStatusTone = (string) ($siteTransferImportStatus['tone'] ?? '') === 'success' ? 'success' : 'warning';
                                                                $siteTransferStatusTitle = trim((string) ($siteTransferImportStatus['title'] ?? ''));
                                                                $siteTransferStatusMessage = trim((string) ($siteTransferImportStatus['message'] ?? ''));
                                                                $siteTransferStatusCode = trim((string) ($siteTransferImportStatus['code'] ?? ''));
                                                                ?>
                                                                <div
                                                                    class="tasty-fonts-page-notice tasty-fonts-inline-note<?php echo $siteTransferStatusTone === 'warning' ? ' tasty-fonts-inline-note--warning' : ''; ?> tasty-fonts-site-transfer-inline-status"
                                                                    role="<?php echo esc_attr($siteTransferStatusTone === 'warning' ? 'alert' : 'status'); ?>"
                                                                    aria-live="<?php echo esc_attr($siteTransferStatusTone === 'warning' ? 'assertive' : 'polite'); ?>"
                                                                    aria-atomic="true"
                                                                >
                                                                    <?php if ($siteTransferStatusTitle !== ''): ?>
                                                                        <strong><?php echo esc_html($siteTransferStatusTitle); ?></strong>
                                                                    <?php endif; ?>
                                                                    <?php if ($siteTransferStatusMessage !== ''): ?>
                                                                        <span><?php echo esc_html($siteTransferStatusMessage); ?></span>
                                                                    <?php endif; ?>
                                                                    <?php if ($siteTransferStatusCode !== ''): ?>
                                                                        <span class="tasty-fonts-site-transfer-inline-code">
                                                                            <?php echo esc_html(sprintf(__('Error code: %s', 'tasty-fonts'), $siteTransferStatusCode)); ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="tasty-fonts-site-transfer-card-foot tasty-fonts-site-transfer-card-foot--import">
                                                    <div class="tasty-fonts-site-transfer-action-cluster">
                                                        <button
                                                            type="button"
                                                            class="button tasty-fonts-developer-action-button tasty-fonts-site-transfer-button"
                                                            data-site-transfer-validate-submit
                                                            data-idle-label="<?php echo esc_attr__('Dry Run Bundle', 'tasty-fonts'); ?>"
                                                            data-busy-label="<?php echo esc_attr__('Validating Bundle...', 'tasty-fonts'); ?>"
                                                            disabled
                                                        >
                                                            <?php esc_html_e('Dry Run Bundle', 'tasty-fonts'); ?>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="tasty-fonts-import-status tasty-fonts-site-transfer-status" data-site-transfer-status></div>
                                            </form>
                                        </article>
                                    </div>

                                    <article class="tasty-fonts-card tasty-fonts-activity-card tasty-fonts-site-transfer-log-card" data-log-filter-root>
                                        <div class="tasty-fonts-card-head tasty-fonts-card-head--activity">
                                            <?php $this->renderSectionHeading('h4', __('Transfer Activity', 'tasty-fonts')); ?>
                                            <div class="tasty-fonts-activity-head-actions tasty-fonts-site-transfer-activity-head-actions">
                                                <?php if ($siteTransferLogs !== []): ?>
                                                    <div class="tasty-fonts-activity-toolbar" role="group" aria-label="<?php esc_attr_e('Transfer activity filters', 'tasty-fonts'); ?>">
                                                        <label class="screen-reader-text" for="tasty-fonts-advanced-transfer-log-actor-filter"><?php esc_html_e('Filter transfer activity by account', 'tasty-fonts'); ?></label>
                                                        <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable tasty-fonts-activity-select">
                                                            <select id="tasty-fonts-advanced-transfer-log-actor-filter" data-log-actor-filter>
                                                                <option value=""><?php esc_html_e('All Accounts', 'tasty-fonts'); ?></option>
                                                                <?php foreach ($siteTransferActorOptions as $actor): ?>
                                                                    <option value="<?php echo esc_attr((string) $actor); ?>"><?php echo esc_html((string) $actor); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <?php $this->renderClearSelectButton(__('Clear account filter', 'tasty-fonts'), 'tasty-fonts-advanced-transfer-log-actor-filter'); ?>
                                                        </span>
                                                        <label class="screen-reader-text" for="tasty-fonts-advanced-transfer-log-search"><?php esc_html_e('Search transfer activity', 'tasty-fonts'); ?></label>
                                                        <span class="tasty-fonts-search-field--compact tasty-fonts-search-field--activity">
                                                            <input
                                                                type="search"
                                                                id="tasty-fonts-advanced-transfer-log-search"
                                                                placeholder="<?php esc_attr_e('Search transfer activity', 'tasty-fonts'); ?>"
                                                                autocomplete="off"
                                                                data-log-search
                                                            >
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($siteTransferLogs === []): ?>
                                            <div class="tasty-fonts-empty-state tasty-fonts-empty-state--rich tasty-fonts-empty-state--activity tasty-fonts-activity-empty">
                                                <div class="tasty-fonts-empty-state-body">
                                                    <h5 class="tasty-fonts-empty-state-title"><?php esc_html_e('No Transfer Activity Yet', 'tasty-fonts'); ?></h5>
                                                    <p class="tasty-fonts-empty-state-copy"><?php esc_html_e('Exports, imports, and transfer-specific recovery messages will appear here after you use the portable transfer tools.', 'tasty-fonts'); ?></p>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="tasty-fonts-activity-shell">
                                                <div id="tasty-fonts-advanced-transfer-log-empty-filtered" class="tasty-fonts-empty tasty-fonts-empty--panel tasty-fonts-activity-empty" data-log-empty-filtered hidden><?php esc_html_e('No transfer activity matches the current filters.', 'tasty-fonts'); ?></div>
                                            <?php $this->renderLogList($siteTransferLogs); ?>
                                        </div>
                                    <?php endif; ?>
                                </article>
                                </section>
                                </div>
                            </section>

                            <section
                                id="tasty-fonts-diagnostics-panel-activity"
                                class="tasty-fonts-studio-panel"
                                data-tab-group="diagnostics"
                                data-tab-panel="activity"
                                role="tabpanel"
                                aria-labelledby="tasty-fonts-diagnostics-tab-activity"
                                hidden
                            >
                                <?php if (!$showActivityLog): ?>
                                    <div class="tasty-fonts-empty-state tasty-fonts-empty-state--rich tasty-fonts-empty-state--activity tasty-fonts-activity-empty">
                                        <div class="tasty-fonts-empty-state-body">
                                            <h3 class="tasty-fonts-empty-state-title"><?php esc_html_e('Activity Log Hidden', 'tasty-fonts'); ?></h3>
                                            <p class="tasty-fonts-empty-state-copy"><?php esc_html_e('Enable Show Activity Log in Settings -> Behavior to review the full event timeline here.', 'tasty-fonts'); ?></p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php $logCount = count($logs); ?>
                                    <?php if ($logs !== []): ?>
                                        <div class="tasty-fonts-advanced-activity-head">
                                            <div class="tasty-fonts-activity-head-actions">
                                                <div class="tasty-fonts-activity-toolbar" role="group" aria-label="<?php esc_attr_e('Activity filters', 'tasty-fonts'); ?>">
                                                    <span
                                                        id="tasty-fonts-advanced-activity-count"
                                                        class="tasty-fonts-badge"
                                                        data-activity-count
                                                        data-total-count="<?php echo esc_attr((string) $logCount); ?>"
                                                    >
                                                        <?php echo esc_html(sprintf($logCount === 1 ? __('%d entry', 'tasty-fonts') : __('%d entries', 'tasty-fonts'), $logCount)); ?>
                                                    </span>
                                                    <label class="screen-reader-text" for="tasty-fonts-advanced-activity-actor-filter"><?php esc_html_e('Filter activity by account', 'tasty-fonts'); ?></label>
                                                    <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable tasty-fonts-activity-select">
                                                        <select id="tasty-fonts-advanced-activity-actor-filter" data-activity-actor-filter>
                                                            <option value=""><?php esc_html_e('All Accounts', 'tasty-fonts'); ?></option>
                                                            <?php foreach ($activityActorOptions as $actor): ?>
                                                                <option value="<?php echo esc_attr((string) $actor); ?>"><?php echo esc_html((string) $actor); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <?php $this->renderClearSelectButton(__('Clear account filter', 'tasty-fonts'), 'tasty-fonts-advanced-activity-actor-filter'); ?>
                                                    </span>
                                                    <label class="screen-reader-text" for="tasty-fonts-advanced-activity-search"><?php esc_html_e('Search activity', 'tasty-fonts'); ?></label>
                                                    <span class="tasty-fonts-search-field--compact tasty-fonts-search-field--activity">
                                                        <input
                                                            type="search"
                                                            id="tasty-fonts-advanced-activity-search"
                                                            placeholder="<?php esc_attr_e('Search activity', 'tasty-fonts'); ?>"
                                                            autocomplete="off"
                                                            data-activity-search
                                                        >
                                                    </span>
                                                    <form method="post">
                                                        <?php wp_nonce_field('tasty_fonts_clear_log'); ?>
                                                        <button
                                                            type="submit"
                                                            class="button tasty-fonts-button-danger tasty-fonts-font-action-button--icon tasty-fonts-activity-clear-button"
                                                            name="tasty_fonts_clear_log"
                                                            value="1"
                                                            aria-label="<?php esc_attr_e('Clear Log', 'tasty-fonts'); ?>"
                                                            title="<?php esc_attr_e('Clear Log', 'tasty-fonts'); ?>"
                                                        >
                                                            <span class="screen-reader-text"><?php esc_html_e('Clear Log', 'tasty-fonts'); ?></span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($logs === []): ?>
                                        <div class="tasty-fonts-empty-state tasty-fonts-empty-state--rich tasty-fonts-empty-state--activity tasty-fonts-activity-empty">
                                            <div class="tasty-fonts-empty-state-body">
                                                <h3 class="tasty-fonts-empty-state-title"><?php esc_html_e('No Activity Yet', 'tasty-fonts'); ?></h3>
                                                <p class="tasty-fonts-empty-state-copy"><?php esc_html_e('Imports, deletes, scans, and CSS refreshes will appear here.', 'tasty-fonts'); ?></p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="tasty-fonts-activity-shell">
                                            <div id="tasty-fonts-advanced-activity-empty-filtered" class="tasty-fonts-empty tasty-fonts-empty--panel tasty-fonts-activity-empty" data-activity-empty-filtered hidden><?php esc_html_e('No activity matches the current filters.', 'tasty-fonts'); ?></div>
                                            <?php $this->renderLogList($logs); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </section>
                        </div>
                    </section>
