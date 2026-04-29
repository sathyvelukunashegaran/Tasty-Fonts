                    <?php
                    $diagnosticItems = isset($diagnosticItems) && is_array($diagnosticItems) ? $diagnosticItems : [];
                    $developerToolStatuses = isset($developerToolStatuses) && is_array($developerToolStatuses) ? $developerToolStatuses : [];
                    $generatedCssPanel = isset($generatedCssPanel) && is_array($generatedCssPanel) ? $generatedCssPanel : [];
                    $advancedTools = isset($advancedTools) && is_array($advancedTools) ? $advancedTools : [];
                    $healthChecks = is_array($advancedTools['health_checks'] ?? null) ? $advancedTools['health_checks'] : [];
                    $debugDetails = isset($debugDetails) && is_array($debugDetails) ? $debugDetails : [];
                    $siteTransfer = isset($siteTransfer) && is_array($siteTransfer) ? $siteTransfer : [];
                    $etchIntegration = isset($etchIntegration) && is_array($etchIntegration) ? $etchIntegration : [];
                    $gutenbergIntegration = isset($gutenbergIntegration) && is_array($gutenbergIntegration) ? $gutenbergIntegration : [];
                    $acssIntegration = isset($acssIntegration) && is_array($acssIntegration) ? $acssIntegration : [];
                    $bricksIntegration = isset($bricksIntegration) && is_array($bricksIntegration) ? $bricksIntegration : [];
                    $oxygenIntegration = isset($oxygenIntegration) && is_array($oxygenIntegration) ? $oxygenIntegration : [];
                    $rollbackSnapshots = is_array($siteTransfer['snapshots'] ?? null) ? $siteTransfer['snapshots'] : [];
                    $supportBundleUrl = trim((string) ($siteTransfer['support_bundle_url'] ?? ''));
                    $snapshotActionField = (string) ($siteTransfer['snapshot_action_field'] ?? 'tasty_fonts_create_rollback_snapshot');
                    $snapshotRestoreActionField = (string) ($siteTransfer['snapshot_restore_action_field'] ?? 'tasty_fonts_restore_rollback_snapshot');
                    $snapshotRenameActionField = (string) ($siteTransfer['snapshot_rename_action_field'] ?? 'tasty_fonts_rename_rollback_snapshot');
                    $snapshotDeleteActionField = (string) ($siteTransfer['snapshot_delete_action_field'] ?? 'tasty_fonts_delete_rollback_snapshot');
                    $snapshotDeleteAllActionField = (string) ($siteTransfer['snapshot_delete_all_action_field'] ?? 'tasty_fonts_delete_all_rollback_snapshots');
                    $exportDeleteAllActionField = (string) ($siteTransfer['export_delete_all_action_field'] ?? 'tasty_fonts_delete_all_site_transfer_export_bundles');
                    $exportDeleteAllBlocked = !empty($siteTransfer['export_delete_all_blocked']);
                    $exportDeleteAllBlockedMessage = trim((string) ($siteTransfer['export_delete_all_blocked_message'] ?? __('One or more export bundles are locked. Unprotect all export bundles before deleting all exports.', 'tasty-fonts')));
                    $snapshotRetentionLimit = max(1, (int) ($siteTransfer['snapshot_retention_limit'] ?? 5));
                    $snapshotRetentionMin = max(1, (int) ($siteTransfer['snapshot_retention_min'] ?? 1));
                    $snapshotRetentionMax = max($snapshotRetentionMin, (int) ($siteTransfer['snapshot_retention_max'] ?? 10));
                    $exportRetentionLimit = max(1, (int) ($siteTransfer['export_retention_limit'] ?? 5));
                    $exportRetentionMin = max(1, (int) ($siteTransfer['export_retention_min'] ?? 1));
                    $exportRetentionMax = max($exportRetentionMin, (int) ($siteTransfer['export_retention_max'] ?? 10));
                    $logs = isset($logs) && is_array($logs) ? $logs : [];
                    $activityActorOptions = isset($activityActorOptions) && is_array($activityActorOptions) ? $activityActorOptions : [];
                    $showSettingsDescriptions = empty($trainingWheelsOff);
                    $showActivityLog = !empty($showActivityLog);
					$updateChannel = isset($updateChannel) ? (string) $updateChannel : 'stable';
                    $updateChannelOptions = isset($updateChannelOptions) && is_array($updateChannelOptions) ? $updateChannelOptions : [];
                    $updateChannelStatus = isset($updateChannelStatus) && is_array($updateChannelStatus) ? $updateChannelStatus : [];
                    $generatedCssDownloadUrl = trim((string) ($generatedCssPanel['download_url'] ?? ''));
                    $generatedCssIsEmpty = $generatedCssPanel === [] || !empty($generatedCssPanel['is_empty']);
                    $healthTriageGroups = isset($healthTriageGroups) && is_array($healthTriageGroups) ? $healthTriageGroups : [];
                    $activityEventCount = count($logs);
                    $advancedToolsPageUrl = add_query_arg(['page' => 'tasty-custom-fonts', 'tf_page' => 'diagnostics'], admin_url('admin.php'));
                    $settingsPageUrl = add_query_arg(['page' => 'tasty-custom-fonts', 'tf_page' => 'settings'], admin_url('admin.php'));
                    $deployFontsPageUrl = add_query_arg(['page' => 'tasty-custom-fonts'], admin_url('admin.php'));
                    $fontLibraryPageUrl = add_query_arg(['page' => 'tasty-custom-fonts', 'tf_page' => 'library'], admin_url('admin.php'));
                    $googleFontsSourceUrl = add_query_arg(['tf_add_fonts' => '1', 'tf_source' => 'google', 'tf_google_access' => '1'], $fontLibraryPageUrl);
                    $overviewMaintenanceUrl = add_query_arg('tf_studio', 'maintenance', $advancedToolsPageUrl);
                    $integrationsSettingsUrl = add_query_arg('tf_studio', 'integrations', $settingsPageUrl);
                    $formatDeveloperToolMeta = static function (string $copy): string {
                        $copy = trim($copy);

                        if ($copy === '') {
                            return '';
                        }

                        $copy = rtrim($copy, '.');
                        $copy = preg_replace('/^Last run\s+/i', '', $copy) ?? $copy;
                        $copy = preg_replace('/\s+by\s+[^.]+$/i', '', $copy) ?? $copy;

                        return trim($copy);
                    };
	                    $developerIntegrationContexts = [
	                        'etch' => $etchIntegration,
	                        'gutenberg' => $gutenbergIntegration,
	                        'acss' => $acssIntegration,
	                        'bricks' => $bricksIntegration,
	                        'oxygen' => $oxygenIntegration,
	                    ];
	                    $developerIntegrationShortLabels = [
	                        'etch' => __('Etch', 'tasty-fonts'),
	                        'gutenberg' => __('Gutenberg', 'tasty-fonts'),
	                        'acss' => __('Automatic.css', 'tasty-fonts'),
	                        'bricks' => __('Bricks', 'tasty-fonts'),
	                        'oxygen' => __('Oxygen', 'tasty-fonts'),
	                    ];
	                    $developerDetectedIntegrations = [];
	                    $developerEnabledIntegrations = [];

                    foreach ($developerIntegrationContexts as $developerIntegrationSlug => $developerIntegrationContext) {
                        if (!is_array($developerIntegrationContext) || $developerIntegrationContext === []) {
                            continue;
                        }

                        $developerIntegrationTitle = trim((string) ($developerIntegrationContext['title'] ?? ''));

                        if ($developerIntegrationTitle === '') {
                            continue;
                        }

                        $developerIntegrationAvailable = !array_key_exists('available', $developerIntegrationContext)
                            || !empty($developerIntegrationContext['available']);
                        $developerIntegrationStatus = trim((string) ($developerIntegrationContext['status'] ?? ''));
                        if ($developerIntegrationStatus === 'waiting_for_sitewide_roles') {
                            $developerIntegrationEnabled = false;
                        } elseif (array_key_exists('control_checked', $developerIntegrationContext)) {
                            $developerIntegrationEnabled = !empty($developerIntegrationContext['control_checked']);
                        } else {
                            $developerIntegrationEnabled = !empty($developerIntegrationContext['enabled'])
                                || in_array($developerIntegrationStatus, ['active', 'synced', 'ready', 'out_of_sync'], true);
                        }
	                        $developerIntegrationEntry = [
	                            'slug' => $developerIntegrationSlug,
	                            'title' => $developerIntegrationTitle,
	                            'summary_title' => $developerIntegrationShortLabels[$developerIntegrationSlug] ?? $developerIntegrationTitle,
	                        ];

                        if ($developerIntegrationAvailable) {
                            $developerDetectedIntegrations[] = $developerIntegrationEntry;
                        }

                        if ($developerIntegrationAvailable && $developerIntegrationEnabled) {
                            $developerEnabledIntegrations[] = $developerIntegrationEntry;
                        }
                    }

	                    $developerDetectedIntegrationLabels = array_values(array_unique(array_map(
	                        static fn (array $integration): string => (string) ($integration['summary_title'] ?? $integration['title'] ?? ''),
	                        $developerDetectedIntegrations
	                    )));
	                    $developerEnabledIntegrationLabels = array_values(array_unique(array_map(
	                        static fn (array $integration): string => (string) ($integration['summary_title'] ?? $integration['title'] ?? ''),
	                        $developerEnabledIntegrations
	                    )));
                    $developerDetectedIntegrationSummary = $developerDetectedIntegrations !== []
                        ? implode(', ', $developerDetectedIntegrationLabels)
                        : __('None detected', 'tasty-fonts');
                    $developerEnabledIntegrationSummary = $developerEnabledIntegrations !== []
                        ? implode(', ', $developerEnabledIntegrationLabels)
                        : __('None on', 'tasty-fonts');
                    $resolveOverviewHealthAction = static function (array $healthCheck) use ($advancedToolsPageUrl, $settingsPageUrl, $deployFontsPageUrl, $fontLibraryPageUrl, $googleFontsSourceUrl, $overviewMaintenanceUrl): ?array {
                        $slug = sanitize_key((string) ($healthCheck['slug'] ?? ''));
                        $action = is_array($healthCheck['action'] ?? null) ? $healthCheck['action'] : null;
                        $actionSlug = $action !== null ? sanitize_key((string) ($action['slug'] ?? '')) : '';
                        if (in_array($actionSlug, ['clear_plugin_caches', 'regenerate_css', 'reset_integration_detection_state'], true)) {
                            return [
                                'label' => __('Open Developer Tools', 'tasty-fonts'),
                                'url' => $overviewMaintenanceUrl,
                                'external' => false,
                            ];
                        }

						if ($actionSlug === 'deploy_fonts') {
							return [
								'label' => __('Deploy Fonts', 'tasty-fonts'),
								'url' => $deployFontsPageUrl,
								'external' => false,
							];
						}

						if ($actionSlug === 'review_integrations') {
							return [
								'label' => __('Review Integrations', 'tasty-fonts'),
								'url' => add_query_arg('tf_studio', 'integrations', $settingsPageUrl),
								'external' => false,
							];
						}

                        return match ($slug) {
                            'generated_css' => [
                                'label' => __('Open Generated CSS', 'tasty-fonts'),
                                'url' => add_query_arg('tf_studio', 'generated', $advancedToolsPageUrl),
                                'external' => false,
                            ],
                            'storage_root' => [
                                'label' => __('Open Developer Tools', 'tasty-fonts'),
                                'url' => $overviewMaintenanceUrl,
                                'external' => false,
                            ],
                            'self_hosted_files' => [
                                'label' => __('Open Font Library', 'tasty-fonts'),
                                'url' => $fontLibraryPageUrl,
                                'external' => false,
                            ],
                            'external_stylesheets' => [
                                'label' => __('Review Deployments', 'tasty-fonts'),
                                'url' => $deployFontsPageUrl,
                                'external' => false,
                            ],
                            'font_preload' => [
                                'label' => __('Review Output Settings', 'tasty-fonts'),
                                'url' => add_query_arg('tf_studio', 'output-settings', $settingsPageUrl),
                                'external' => false,
                            ],
                            'block_editor_sync' => [
                                'label' => __('Review Sync Settings', 'tasty-fonts'),
                                'url' => add_query_arg('tf_studio', 'integrations', $settingsPageUrl),
                                'external' => false,
                            ],
                            'google_fonts_api' => [
                                'label' => __('Add API Key', 'tasty-fonts'),
                                'url' => $googleFontsSourceUrl,
                                'external' => false,
                            ],
                            'update_channel' => [
								'label' => __('Open Release Rail', 'tasty-fonts'),
								'url' => $overviewMaintenanceUrl,
                                'external' => false,
                            ],
                            'site_transfer' => [
                                'label' => __('Open Transfer', 'tasty-fonts'),
                                'url' => add_query_arg('tf_studio', 'transfer', $advancedToolsPageUrl),
                                'external' => false,
                            ],
                            'library_inventory' => [
                                'label' => __('Open Font Library', 'tasty-fonts'),
                                'url' => add_query_arg('tf_add_fonts', '1', $fontLibraryPageUrl),
                                'external' => false,
                            ],
							default => null,
                        };
                    };

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
                                    'button_class' => 'button tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--rebuild tasty-fonts-developer-action-button',
                                ],
                                [
                                    'slug' => 'regenerate_css',
                                    'title' => __('Regenerate CSS file', 'tasty-fonts'),
                                    'description' => __('Rebuilds the generated stylesheet only.', 'tasty-fonts'),
                                    'nonce' => 'tasty_fonts_regenerate_css',
                                    'action_name' => 'tasty_fonts_regenerate_css',
                                    'button_label' => __('Regenerate CSS File', 'tasty-fonts'),
                                    'button_class' => 'button tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--generate tasty-fonts-developer-action-button',
                                ],
                                [
                                    'slug' => 'rescan_font_library',
                                    'title' => __('Rescan font library', 'tasty-fonts'),
                                    'description' => __('Re-reads managed font metadata and refreshes generated assets.', 'tasty-fonts'),
                                    'nonce' => 'tasty_fonts_rescan_fonts',
                                    'action_name' => 'tasty_fonts_rescan_fonts',
                                    'button_label' => __('Rescan Library', 'tasty-fonts'),
                                    'button_class' => 'button tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--scan tasty-fonts-developer-action-button',
                                ],
                                [
                                    'slug' => 'repair_storage_scaffold',
                                    'title' => __('Repair storage scaffold', 'tasty-fonts'),
                                    'description' => __('Recreates required storage folders and baseline protection files.', 'tasty-fonts'),
                                    'nonce' => 'tasty_fonts_repair_storage_scaffold',
                                    'action_name' => 'tasty_fonts_repair_storage_scaffold',
                                    'button_label' => __('Repair Storage', 'tasty-fonts'),
                                    'button_class' => 'button tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--rebuild tasty-fonts-developer-action-button',
                                ],
                                [
                                    'slug' => 'reset_integration_detection_state',
                                    'title' => __('Run integration scan', 'tasty-fonts'),
                                    'description' => __('Re-detects supported integrations on the next admin load.', 'tasty-fonts'),
                                    'nonce' => 'tasty_fonts_reset_integration_detection_state',
                                    'action_name' => 'tasty_fonts_reset_integration_detection_state',
                                    'button_label' => __('Run Integration Scan', 'tasty-fonts'),
                                    'button_class' => 'button tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--scan tasty-fonts-developer-action-button',
                                    'confirm_message' => __('Reset stored integration detection and ACSS bookkeeping?', 'tasty-fonts'),
                                ],
                                [
                                    'slug' => 'reset_suppressed_notices',
                                    'title' => __('Restore notices', 'tasty-fonts'),
                                    'description' => __('Shows dismissed onboarding and reminder notices again.', 'tasty-fonts'),
                                    'nonce' => 'tasty_fonts_reset_suppressed_notices',
                                    'action_name' => 'tasty_fonts_reset_suppressed_notices',
                                    'button_label' => __('Restore Notices', 'tasty-fonts'),
                                    'button_class' => 'button tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--notices tasty-fonts-developer-action-button',
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
                                    'button_class' => 'button button-secondary tasty-fonts-button-danger tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--reset tasty-fonts-developer-action-button',
                                    'card_class' => 'tasty-fonts-developer-tool-card--danger',
                                    'confirm_message' => __('Reset plugin settings to defaults while keeping the font library and files?', 'tasty-fonts'),
                                ],
                                [
                                    'slug' => 'wipe_managed_font_library',
                                    'title' => __('Delete managed font library', 'tasty-fonts'),
                                    'description' => __('Deletes managed fonts and rebuilds empty storage.', 'tasty-fonts'),
                                    'nonce' => 'tasty_fonts_wipe_managed_font_library',
                                    'action_name' => 'tasty_fonts_wipe_managed_font_library',
                                    'button_label' => __('Delete Managed Library', 'tasty-fonts'),
                                    'button_class' => 'button button-secondary tasty-fonts-button-danger tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--delete tasty-fonts-developer-action-button',
                                    'card_class' => 'tasty-fonts-developer-tool-card--danger',
                                    'confirm_message' => __('Wipe the managed font library, remove managed files, and rebuild empty storage?', 'tasty-fonts'),
                                ],
                                [
                                    'slug' => 'delete_all_snapshots',
                                    'title' => __('Delete all snapshots', 'tasty-fonts'),
                                    'description' => __('Deletes every retained rollback snapshot for this site. Settings, roles, and font files stay untouched.', 'tasty-fonts'),
                                    'nonce' => $snapshotDeleteAllActionField,
                                    'action_name' => $snapshotDeleteAllActionField,
                                    'button_label' => __('Delete All Snapshots', 'tasty-fonts'),
                                    'button_class' => 'button button-secondary tasty-fonts-button-danger tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--delete tasty-fonts-developer-action-button',
                                    'card_class' => 'tasty-fonts-developer-tool-card--danger',
                                    'confirm_message' => __('Delete all rollback snapshots permanently?', 'tasty-fonts'),
                                ],
                                [
                                    'slug' => 'delete_all_exports',
                                    'title' => __('Delete all exports', 'tasty-fonts'),
                                    'description' => __('Deletes every retained site transfer export bundle. Locked exports must be unlocked first.', 'tasty-fonts'),
                                    'nonce' => $exportDeleteAllActionField,
                                    'action_name' => $exportDeleteAllActionField,
                                    'button_label' => __('Delete All Exports', 'tasty-fonts'),
                                    'button_class' => 'button button-secondary tasty-fonts-button-danger tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--delete tasty-fonts-developer-action-button',
                                    'card_class' => 'tasty-fonts-developer-tool-card--danger',
                                    'confirm_message' => __('Delete all site transfer export bundles permanently?', 'tasty-fonts'),
                                    'blocked' => $exportDeleteAllBlocked,
                                    'blocked_message' => $exportDeleteAllBlockedMessage,
                                ],
                                [
                                    'slug' => 'delete_all_history',
                                    'title' => __('Delete all history', 'tasty-fonts'),
                                    'description' => __('Deletes every retained activity log entry, including action history used by Last run chips. Settings, fonts, snapshots, and exports stay untouched.', 'tasty-fonts'),
                                    'nonce' => 'tasty_fonts_delete_all_history',
                                    'action_name' => 'tasty_fonts_delete_all_history',
                                    'button_label' => __('Delete All History', 'tasty-fonts'),
                                    'button_class' => 'button button-secondary tasty-fonts-button-danger tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--delete tasty-fonts-developer-action-button',
                                    'card_class' => 'tasty-fonts-developer-tool-card--danger',
                                    'confirm_message' => __('Delete all retained activity history permanently?', 'tasty-fonts'),
                                ],
                            ],
	                        ],
	                    ];
	                    $cliCommandGroups = [
	                        [
	                            'slug' => 'diagnostics',
	                            'title' => __('Diagnostics', 'tasty-fonts'),
	                            'commands' => [
	                                [
	                                    'label' => __('Doctor summary', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts doctor',
	                                    'description' => __('Runs Advanced Tools health checks and prints a readable pass, warning, and critical summary.', 'tasty-fonts'),
	                                ],
	                                [
	                                    'label' => __('Doctor JSON', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts doctor --format=json',
	                                    'description' => __('Runs the same doctor checks and returns JSON for scripts, support tooling, or CI logs.', 'tasty-fonts'),
	                                ],
	                            ],
	                        ],
	                        [
	                            'slug' => 'google-api-key',
	                            'title' => __('Google API Key', 'tasty-fonts'),
	                            'commands' => [
	                                [
	                                    'label' => __('Check key status', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts google-api-key status',
	                                    'description' => __('Reports whether a Google Fonts API key is stored without printing the key.', 'tasty-fonts'),
	                                ],
	                                [
	                                    'label' => __('Save key securely', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts google-api-key save',
	                                    'description' => __('Prompts for a Google Fonts API key, validates it, and saves it without exposing the key in shell history.', 'tasty-fonts'),
	                                ],
	                            ],
	                        ],
	                        [
	                            'slug' => 'generated-assets',
	                            'title' => __('Generated Assets', 'tasty-fonts'),
	                            'commands' => [
	                                [
	                                    'label' => __('Regenerate CSS', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts css regenerate',
	                                    'description' => __('Rebuilds the generated runtime stylesheet from the current published roles, library, and output settings.', 'tasty-fonts'),
	                                ],
	                                [
	                                    'label' => __('Clear caches', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts cache clear',
	                                    'description' => __('Clears plugin transients and forces generated assets to refresh without changing saved font settings.', 'tasty-fonts'),
	                                ],
	                                [
	                                    'label' => __('Rescan library', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts library rescan',
	                                    'description' => __('Re-reads managed font metadata, refreshes the catalog, and rebuilds generated assets.', 'tasty-fonts'),
	                                ],
	                            ],
	                        ],
	                        [
	                            'slug' => 'maintenance',
	                            'title' => __('Maintenance', 'tasty-fonts'),
	                            'commands' => [
	                                [
	                                    'label' => __('Reset settings', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts settings reset --yes',
	                                    'description' => __('Resets all Tasty Fonts settings and stored Google key data to defaults while keeping managed font files.', 'tasty-fonts'),
	                                ],
	                                [
	                                    'label' => __('Delete managed files', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts files delete --yes',
	                                    'description' => __('Deletes plugin-managed font files, generated CSS, retained transfer exports, and rollback snapshots, then rebuilds empty storage.', 'tasty-fonts'),
	                                ],
	                            ],
	                        ],
	                        [
	                            'slug' => 'transfer',
	                            'title' => __('Transfer', 'tasty-fonts'),
	                            'commands' => [
	                                [
	                                    'label' => __('Export bundle', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts transfer export',
	                                    'description' => __('Creates a portable transfer ZIP with settings, live roles, library metadata, and managed font files.', 'tasty-fonts'),
	                                ],
	                                [
	                                    'label' => __('Dry-run import', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts transfer import /path/to/tasty-fonts-transfer.zip --dry-run --prompt-google-api-key',
	                                    'description' => __('Validates a transfer ZIP, optionally prompts for a destination Google key, and previews the import diff without replacing current site data.', 'tasty-fonts'),
	                                ],
	                                [
	                                    'label' => __('Import bundle', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts transfer import /path/to/tasty-fonts-transfer.zip --yes --prompt-google-api-key',
	                                    'description' => __('Imports a validated transfer ZIP, prompts for an optional destination Google key, creates a rollback snapshot first, and replaces current Tasty Fonts data.', 'tasty-fonts'),
	                                ],
	                            ],
	                        ],
	                        [
	                            'slug' => 'support',
	                            'title' => __('Support', 'tasty-fonts'),
	                            'commands' => [
	                                [
	                                    'label' => __('Support bundle', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts support-bundle',
	                                    'description' => __('Creates a sanitized troubleshooting ZIP with diagnostics, storage metadata, generated CSS, and recent activity.', 'tasty-fonts'),
	                                ],
	                                [
	                                    'label' => __('Support bundle JSON', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts support-bundle --format=json',
	                                    'description' => __('Creates the same support ZIP and returns machine-readable bundle details for automation.', 'tasty-fonts'),
	                                ],
	                            ],
	                        ],
	                        [
	                            'slug' => 'snapshots',
	                            'title' => __('Snapshots', 'tasty-fonts'),
	                            'commands' => [
	                                [
	                                    'label' => __('Create snapshot', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts snapshot create',
	                                    'description' => __('Creates a local rollback point for settings, library metadata, and managed font files.', 'tasty-fonts'),
	                                ],
	                                [
	                                    'label' => __('Create named snapshot', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts snapshot create --reason=manual',
	                                    'description' => __('Creates a manual rollback point with an explicit reason label for later review.', 'tasty-fonts'),
	                                ],
	                                [
	                                    'label' => __('List snapshots', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts snapshot list --format=json',
	                                    'description' => __('Lists saved rollback snapshots as JSON so you can copy an ID before restoring.', 'tasty-fonts'),
	                                ],
	                                [
	                                    'label' => __('Restore snapshot', 'tasty-fonts'),
	                                    'command' => 'wp tasty-fonts snapshot restore <snapshot-id> --yes',
	                                    'description' => __('Restores a saved rollback point after creating a fresh safety snapshot of the current state.', 'tasty-fonts'),
	                                ],
	                            ],
	                        ],
	                    ];

	                    $siteTransferAvailable = !empty($siteTransfer['available']);
                    $siteTransferMessage = trim((string) ($siteTransfer['message'] ?? ''));
                    $siteTransferExportUrl = (string) ($siteTransfer['export_url'] ?? '');
                    $siteTransferExportBundles = is_array($siteTransfer['export_bundles'] ?? null) ? $siteTransfer['export_bundles'] : [];
                    $siteTransferExportRenameAction = (string) ($siteTransfer['export_rename_action_field'] ?? 'tasty_fonts_rename_site_transfer_export_bundle');
                    $siteTransferExportProtectAction = (string) ($siteTransfer['export_protect_action_field'] ?? 'tasty_fonts_protect_site_transfer_export_bundle');
                    $siteTransferExportDeleteAction = (string) ($siteTransfer['export_delete_action_field'] ?? 'tasty_fonts_delete_site_transfer_export_bundle');
                    $siteTransferImportFileField = (string) ($siteTransfer['import_file_field'] ?? 'tasty_fonts_site_transfer_bundle');
                    $siteTransferImportStageTokenField = (string) ($siteTransfer['import_stage_token_field'] ?? 'tasty_fonts_site_transfer_stage_token');
                    $siteTransferImportGoogleField = (string) ($siteTransfer['import_google_api_key_field'] ?? 'tasty_fonts_import_google_api_key');
                    $siteTransferImportAction = (string) ($siteTransfer['import_action_field'] ?? 'tasty_fonts_import_site_transfer_bundle');
                    $siteTransferImportStatus = is_array($siteTransfer['import_status'] ?? null) ? $siteTransfer['import_status'] : [];
                    $siteTransferLogs = is_array($siteTransfer['logs'] ?? null) ? $siteTransfer['logs'] : [];
                    $siteTransferActorOptions = is_array($siteTransfer['actor_options'] ?? null) ? $siteTransfer['actor_options'] : [];
                    $siteTransferUploadLimitLabel = trim((string) ($siteTransfer['effective_upload_limit_label'] ?? ''));
                    $rollbackSnapshotCount = count($rollbackSnapshots);
                    $siteTransferHelpCopy = [
                        'portable_status' => __('Portable transfer creates a ZIP that can move settings, role assignments, library metadata, and managed font files to another Tasty Fonts install. Imports must pass a dry run before they can replace anything.', 'tasty-fonts'),
                        'snapshot_status' => __('Snapshots are local restore points for this site. Tasty Fonts creates them before destructive transfer operations, and you can create one manually before making changes.', 'tasty-fonts'),
                        'support_status' => __('Support bundles are troubleshooting ZIPs. They include diagnostics, storage metadata, generated CSS, and recent activity, but leave out API keys and transfer secrets.', 'tasty-fonts'),
                        'export_bundle' => __('Use this when moving this site’s Tasty Fonts setup elsewhere. It does not include runtime cache, generated CSS, logs, or private keys.', 'tasty-fonts'),
                        'dry_run_bundle' => __('Choose a ZIP from another Tasty Fonts site, then run the dry run to inspect what would change. The import button unlocks only after validation succeeds.', 'tasty-fonts'),
                        'rollback_snapshot' => __('Create a local checkpoint before risky work. Restoring a snapshot rolls the Tasty Fonts settings and managed files back to that saved state.', 'tasty-fonts'),
                        'support_bundle' => __('Download this when you need to inspect or share plugin diagnostics without exposing API keys. It is separate from a transfer bundle and is not meant for importing.', 'tasty-fonts'),
                        'activity_log' => __('This log is limited to transfer and recovery events, so it does not duplicate the full Advanced Tools activity tab.', 'tasty-fonts'),
                    ];
                    $renderSiteTransferHelpAttributes = static function (string $copy, bool $force = false) use ($trainingWheelsOff): void {
                        $copy = trim($copy);

                        if ((!$force && !empty($trainingWheelsOff)) || $copy === '') {
                            return;
                        }

                        echo ' data-help-tooltip="' . esc_attr($copy) . '"';
                        echo ' data-help-passive="1"';
                        echo ' aria-describedby="tasty-fonts-help-tooltip-layer"';
                    };
                    $renderDebugHelpButton = static function (string $label, string $copy) use ($trainingWheelsOff): void {
                        $label = trim($label);
                        $copy = trim($copy);

                        if (!empty($trainingWheelsOff) || $label === '' || $copy === '') {
                            return;
                        }
                        ?>
                        <button
                            type="button"
                            class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger tasty-fonts-health-help-trigger"
                            aria-label="<?php echo esc_attr(sprintf(__('Explain %s', 'tasty-fonts'), $label)); ?>"
                            data-help-tooltip="<?php echo esc_attr($copy); ?>"
                            data-help-passive="1"
                            aria-describedby="tasty-fonts-help-tooltip-layer"
                        >?</button>
                        <?php
                    };
                    $snapshotReasonLabel = static function (string $reason): string {
                        $reason = sanitize_key($reason);

                        return match ($reason) {
                            'manual' => __('Manual snapshot', 'tasty-fonts'),
                            'before_transfer_import' => __('Before transfer import', 'tasty-fonts'),
                            'before_snapshot_restore' => __('Before snapshot restore', 'tasty-fonts'),
                            'before_reset_settings' => __('Before settings reset', 'tasty-fonts'),
                            'before_wipe_library' => __('Before library delete', 'tasty-fonts'),
                            default => ucwords(str_replace('_', ' ', $reason !== '' ? $reason : 'manual')),
                        };
                    };
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
	                        'cli' => [
	                            'title' => __('CLI', 'tasty-fonts'),
	                            'description' => __('Copy WP-CLI commands for diagnostics, maintenance, transfer, support, and snapshots.', 'tasty-fonts'),
	                        ],
	                        'transfer' => [
	                            'title' => __('Transfer & Recovery', 'tasty-fonts'),
	                            'description' => __('Move setups, create rollback points, and package sanitized support diagnostics.', 'tasty-fonts'),
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
	                                        'cli' => __('CLI', 'tasty-fonts'),
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
								<?php if ($healthChecks !== [] || $healthTriageGroups !== []): ?>
                                    <div class="tasty-fonts-overview-surface tasty-fonts-advanced-health-panel" id="tasty-fonts-overview-health">
                                        <div class="tasty-fonts-output-settings-copy">
                                            <h3><?php esc_html_e('Health', 'tasty-fonts'); ?></h3>
                                            <?php if ($showSettingsDescriptions): ?>
                                                <p><?php esc_html_e('One checklist for runtime, storage, provider, transfer, and update readiness.', 'tasty-fonts'); ?></p>
                                            <?php endif; ?>
                                        </div>
										<div class="tasty-fonts-health-board tasty-fonts-health-board--overview">
											<?php foreach ($healthTriageGroups as $healthGroup): ?>
                                                <?php
                                                $groupChecks = is_array($healthGroup['checks'] ?? null) ? $healthGroup['checks'] : [];
												$healthGroupSlug = sanitize_html_class((string) ($healthGroup['slug'] ?? 'group'));
												$healthGroupTitle = (string) ($healthGroup['title'] ?? '');
												$healthGroupHasChecks = $groupChecks !== [];
												$healthGroupIsActionNeeded = $healthGroupSlug === 'action-needed';

												if (!$healthGroupHasChecks && !$healthGroupIsActionNeeded) {
                                                    continue;
                                                }

												$healthGroupExpanded = !empty($healthGroup['expanded']) || ($healthGroupIsActionNeeded && !$healthGroupHasChecks);
                                                ?>
												<details class="tasty-fonts-health-group tasty-fonts-health-group--<?php echo esc_attr($healthGroupSlug); ?> <?php echo $healthGroupHasChecks ? 'has-checks' : 'is-empty'; ?>" aria-label="<?php echo esc_attr($healthGroupTitle); ?>"<?php echo $healthGroupExpanded ? ' open' : ''; ?>>
													<summary class="tasty-fonts-health-group-head">
														<span class="tasty-fonts-health-group-title"><?php echo esc_html($healthGroupTitle); ?></span>
                                                        <span class="tasty-fonts-health-group-count"><?php echo esc_html((string) ($healthGroup['summary'] ?? '')); ?></span>
													</summary>
                                                    <div class="tasty-fonts-health-list">
														<?php if (!$healthGroupHasChecks && $healthGroupIsActionNeeded): ?>
															<article class="tasty-fonts-health-row tasty-fonts-health-row--empty tasty-fonts-health-row--ok">
																<span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
																<div class="tasty-fonts-health-row-copy">
																	<div class="tasty-fonts-health-row-title">
																		<strong><?php esc_html_e('No action needed', 'tasty-fonts'); ?></strong>
																		<?php if ($showSettingsDescriptions): ?>
																			<span><?php esc_html_e('Nothing needs immediate recovery. Review summaries and debug facts remain available below.', 'tasty-fonts'); ?></span>
																		<?php endif; ?>
																	</div>
																</div>
															</article>
														<?php endif; ?>
                                                        <?php foreach ($groupChecks as $healthCheck): ?>
                                                            <?php
                                                            if (!is_array($healthCheck)) {
                                                                continue;
                                                            }

                                                            $healthSeverity = sanitize_html_class((string) ($healthCheck['severity'] ?? 'ok'));
                                                            $healthTitle = trim((string) ($healthCheck['title'] ?? ''));
                                                            $healthMessage = trim((string) ($healthCheck['message'] ?? ''));
                                                            $healthGuidance = trim((string) ($healthCheck['guidance'] ?? ''));
                                                            $healthHelpUrl = trim((string) ($healthCheck['help_url'] ?? ''));
                                                            $healthTooltip = trim($healthMessage . ' ' . $healthGuidance);
                                                            $healthPrimaryAction = $healthSeverity === 'ok' ? null : $resolveOverviewHealthAction($healthCheck);
                                                            $healthStatusLabel = match ($healthSeverity) {
                                                                'critical' => __('Critical', 'tasty-fonts'),
                                                                'warning' => __('Warning', 'tasty-fonts'),
                                                                'info' => __('Review', 'tasty-fonts'),
                                                                default => __('OK', 'tasty-fonts'),
                                                            };

                                                            if ($healthTitle === '') {
                                                                continue;
                                                            }
                                                            ?>
                                                            <article class="tasty-fonts-health-row tasty-fonts-health-row--<?php echo esc_attr($healthSeverity); ?>">
                                                                <span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
                                                                <div class="tasty-fonts-health-row-copy">
                                                                    <div class="tasty-fonts-health-row-title">
                                                                        <strong><?php echo esc_html($healthTitle); ?></strong>
                                                                        <?php if ($showSettingsDescriptions && $healthMessage !== ''): ?>
                                                                            <span><?php echo esc_html($healthMessage); ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                <div class="tasty-fonts-health-row-actions">
                                                                    <?php if (is_array($healthPrimaryAction) && !empty($healthPrimaryAction['url']) && !empty($healthPrimaryAction['label'])): ?>
																		<?php
																		$healthPrimaryActionClass = in_array($healthSeverity, ['critical', 'warning'], true)
																			? 'button button-primary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--navigate tasty-fonts-health-row-primary-action'
																			: 'button button-secondary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--navigate tasty-fonts-health-row-primary-action';
																		?>
                                                                        <a
																			class="<?php echo esc_attr($healthPrimaryActionClass); ?>"
                                                                            href="<?php echo esc_url((string) $healthPrimaryAction['url']); ?>"
                                                                            <?php if (!empty($healthPrimaryAction['external'])): ?>
                                                                                target="_blank"
                                                                                rel="noopener noreferrer"
                                                                            <?php endif; ?>
                                                                        >
                                                                            <?php echo esc_html((string) $healthPrimaryAction['label']); ?>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    <?php if ($healthHelpUrl !== ''): ?>
                                                                        <a
                                                                            class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger tasty-fonts-health-help-trigger"
                                                                            href="<?php echo esc_url($healthHelpUrl); ?>"
                                                                            target="_blank"
                                                                            rel="noopener noreferrer"
                                                                            aria-label="<?php echo esc_attr(sprintf(__('Open knowledge base for %s', 'tasty-fonts'), $healthTitle)); ?>"
                                                                            <?php $this->renderPassiveHelpAttributes($healthTooltip); ?>
                                                                        >
                                                                            <?php esc_html_e('?', 'tasty-fonts'); ?>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    <span class="tasty-fonts-health-badge tasty-fonts-health-badge--<?php echo esc_attr($healthSeverity); ?>"><?php echo esc_html($healthStatusLabel); ?></span>
                                                                </div>
                                                            </article>
                                                        <?php endforeach; ?>
                                                    </div>
												</details>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
				<?php
				$debugGeneratedFacts = is_array($debugDetails['generated_facts'] ?? null) ? $debugDetails['generated_facts'] : [];
				$debugRuntimeFamilies = is_array($debugDetails['runtime_families'] ?? null) ? $debugDetails['runtime_families'] : [];
				$debugCopyGroups = is_array($debugDetails['copy_groups'] ?? null) ? $debugDetails['copy_groups'] : [];
				$debugTitle = trim((string) ($debugDetails['title'] ?? __('Debug Details', 'tasty-fonts')));
				$debugDescription = trim((string) ($debugDetails['description'] ?? ''));
				$debugRuntimeFamilyCount = count($debugRuntimeFamilies);
				?>
				<section class="tasty-fonts-overview-surface tasty-fonts-runtime-inspector-panel tasty-fonts-overview-debug-panel tasty-fonts-debug-details" aria-label="<?php echo esc_attr($debugTitle); ?>">
					<div class="tasty-fonts-output-settings-copy">
						<h3 class="tasty-fonts-debug-details-title"><?php echo esc_html($debugTitle); ?></h3>
						<?php if ($showSettingsDescriptions && $debugDescription !== ''): ?>
							<p><?php echo esc_html($debugDescription); ?></p>
						<?php endif; ?>
					</div>
					<div class="tasty-fonts-health-board tasty-fonts-overview-reference-board tasty-fonts-debug-details-board">
						<section class="tasty-fonts-health-group tasty-fonts-health-group--debug-generated" aria-label="<?php esc_attr_e('Generated CSS facts', 'tasty-fonts'); ?>">
                                            <div class="tasty-fonts-health-group-head">
												<h4><?php esc_html_e('Generated CSS Facts', 'tasty-fonts'); ?></h4>
												<span class="tasty-fonts-health-group-count"><?php esc_html_e('Runtime File', 'tasty-fonts'); ?></span>
											</div>
											<div class="tasty-fonts-health-list">
												<?php foreach ($debugGeneratedFacts as $debugFact): ?>
													<?php
													if (!is_array($debugFact)) {
														continue;
													}

													$factLabel = trim((string) ($debugFact['label'] ?? ''));
									$factValue = trim((string) ($debugFact['value'] ?? ''));
									$factCopyable = !empty($debugFact['copyable']) && $factValue !== '';
									$factHelp = trim((string) ($debugFact['help'] ?? ''));

									if ($factLabel === '' || $factValue === '') {
										continue;
													}
													?>
													<article class="tasty-fonts-health-row tasty-fonts-health-row--reference">
														<span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
								<div class="tasty-fonts-health-row-copy">
									<div class="tasty-fonts-health-row-title">
										<strong><?php echo esc_html($factLabel); ?></strong>
										<span class="tasty-fonts-code"><?php echo esc_html($factValue); ?></span>
									</div>
								</div>
								<?php if ($factHelp !== '' || $factCopyable): ?>
									<div class="tasty-fonts-health-row-actions">
										<?php $renderDebugHelpButton($factLabel, $factHelp); ?>
										<?php if ($factCopyable): ?>
										<button
											type="button"
											class="button tasty-fonts-output-copy-button tasty-fonts-diagnostic-copy-button"
																	data-copy-text="<?php echo esc_attr($factValue); ?>"
																	data-copy-success="<?php echo esc_attr(sprintf(__('%s copied.', 'tasty-fonts'), $factLabel)); ?>"
																	data-copy-static-label="1"
																	aria-label="<?php echo esc_attr(sprintf(__('Copy %s', 'tasty-fonts'), $factLabel)); ?>"
										>
											<span class="screen-reader-text"><?php echo esc_html(sprintf(__('Copy %s', 'tasty-fonts'), $factLabel)); ?></span>
										</button>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</article>
												<?php endforeach; ?>
											</div>
										</section>
										<section class="tasty-fonts-health-group tasty-fonts-health-group--debug-families" aria-label="<?php esc_attr_e('Runtime families', 'tasty-fonts'); ?>">
											<div class="tasty-fonts-health-group-head">
												<h4><?php esc_html_e('Runtime Families', 'tasty-fonts'); ?></h4>
                                                <span class="tasty-fonts-health-group-count">
                                                    <?php
                                                    echo esc_html(
                                                        sprintf(
                                                            /* translators: %d: number of active runtime font families */
															_n('%d Family', '%d Families', $debugRuntimeFamilyCount, 'tasty-fonts'),
															$debugRuntimeFamilyCount
                                                        )
                                                    );
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="tasty-fonts-health-list">
												<?php if ($debugRuntimeFamilies !== []): ?>
													<?php foreach ($debugRuntimeFamilies as $runtimeFamily): ?>
														<?php
														if (!is_array($runtimeFamily)) {
															continue;
														}

														$runtimeFamilyName = trim((string) ($runtimeFamily['family'] ?? ''));
														$runtimeProvider = trim((string) ($runtimeFamily['provider'] ?? ''));
														$runtimeType = trim((string) ($runtimeFamily['type'] ?? ''));
									$runtimeFaces = (int) ($runtimeFamily['faces'] ?? 0);
									$runtimeVariants = is_array($runtimeFamily['variants'] ?? null) ? array_values(array_filter(array_map('strval', $runtimeFamily['variants']))) : [];
									$runtimeHelp = __('This family is included in the frontend runtime plan. The row shows provider, source type, face count, and the first variants Tasty Fonts will serve.', 'tasty-fonts');

														if ($runtimeFamilyName === '') {
															continue;
														}

														$runtimeMeta = [
															$runtimeProvider !== '' ? $runtimeProvider : __('Local', 'tasty-fonts'),
															$runtimeType !== '' ? $runtimeType : __('Default', 'tasty-fonts'),
															sprintf(
																/* translators: %d: number of font faces */
																_n('%d Face', '%d Faces', $runtimeFaces, 'tasty-fonts'),
																$runtimeFaces
															),
														];

														if ($runtimeVariants !== []) {
															$runtimeMeta[] = sprintf(
																/* translators: %s: comma-separated font variant labels */
																__('Variants: %s', 'tasty-fonts'),
																implode(', ', array_slice($runtimeVariants, 0, 6))
															);
														}
														?>
                                                        <article class="tasty-fonts-health-row tasty-fonts-health-row--reference">
                                                            <span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
                                                            <div class="tasty-fonts-health-row-copy">
                                                                <div class="tasty-fonts-health-row-title">
                                                                    <strong><?php echo esc_html($runtimeFamilyName); ?></strong>
																		<span><?php echo esc_html(implode(' / ', $runtimeMeta)); ?></span>
                                                                </div>
                                                            </div>
															<div class="tasty-fonts-health-row-actions">
																<?php $renderDebugHelpButton($runtimeFamilyName, $runtimeHelp); ?>
															</div>
                                                        </article>
													<?php endforeach; ?>
                                                <?php else: ?>
                                                    <article class="tasty-fonts-health-row tasty-fonts-health-row--info">
                                                        <span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
                                                        <div class="tasty-fonts-health-row-copy">
                                                            <div class="tasty-fonts-health-row-title">
																<strong><?php esc_html_e('No runtime families yet.', 'tasty-fonts'); ?></strong>
																	<span><?php esc_html_e('Publish your roles to deploy families into frontend CSS.', 'tasty-fonts'); ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="tasty-fonts-health-row-actions">
															<?php $renderDebugHelpButton(__('No runtime families yet.', 'tasty-fonts'), __('Runtime families appear after published sitewide roles produce frontend font CSS.', 'tasty-fonts')); ?>
                                                            <a
                                                                class="button button-secondary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--navigate tasty-fonts-runtime-empty-action"
                                                                href="<?php echo esc_url($deployFontsPageUrl); ?>"
                                                            >
                                                                <?php esc_html_e('Deploy Fonts', 'tasty-fonts'); ?>
                                                            </a>
                                                        </div>
                                                    </article>
                                                <?php endif; ?>
                                            </div>
                                        </section>
										<?php if ($debugCopyGroups !== []): ?>
											<?php foreach ($debugCopyGroups as $copyGroup): ?>
												<?php
												if (!is_array($copyGroup)) {
													continue;
												}

												$copyGroupItems = is_array($copyGroup['items'] ?? null) ? $copyGroup['items'] : [];
												$copyGroupTitle = trim((string) ($copyGroup['title'] ?? ''));
												$copyGroupSlug = sanitize_html_class((string) ($copyGroup['slug'] ?? 'values'));

												if ($copyGroupItems === [] || $copyGroupTitle === '') {
													continue;
												}
												?>
												<section class="tasty-fonts-health-group tasty-fonts-health-group--debug-<?php echo esc_attr($copyGroupSlug); ?>" aria-label="<?php echo esc_attr($copyGroupTitle); ?>">
													<div class="tasty-fonts-health-group-head">
														<h4><?php echo esc_html($copyGroupTitle); ?></h4>
														<span class="tasty-fonts-health-group-count">
															<?php
															echo esc_html(
																sprintf(
																	/* translators: %d: number of copyable debug values */
																	_n('%d Value', '%d Values', count($copyGroupItems), 'tasty-fonts'),
																	count($copyGroupItems)
																)
															);
															?>
														</span>
													</div>
													<div class="tasty-fonts-health-list">
														<?php foreach ($copyGroupItems as $copyItem): ?>
															<?php
															if (!is_array($copyItem)) {
																continue;
															}

											$itemValue = trim((string) ($copyItem['value'] ?? ''));
											$itemLabel = trim((string) ($copyItem['label'] ?? __('Value', 'tasty-fonts')));
											$itemHelp = trim((string) ($copyItem['help'] ?? ''));

											if ($itemValue === '' || $itemLabel === '') {
												continue;
															}
															?>
															<article class="tasty-fonts-health-row tasty-fonts-health-row--reference">
																<span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
																<div class="tasty-fonts-health-row-copy">
																	<div class="tasty-fonts-health-row-title">
																		<strong><?php echo esc_html($itemLabel); ?></strong>
																		<span class="tasty-fonts-code"><?php echo esc_html($itemValue); ?></span>
																	</div>
                                                                </div>
                                                                <div class="tasty-fonts-health-row-actions">
																	<?php $renderDebugHelpButton($itemLabel, $itemHelp); ?>
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
															</article>
														<?php endforeach; ?>
													</div>
												</section>
											<?php endforeach; ?>
										<?php else: ?>
											<section class="tasty-fonts-health-group tasty-fonts-health-group--debug-empty" aria-label="<?php esc_attr_e('Copy support values', 'tasty-fonts'); ?>">
												<div class="tasty-fonts-health-list">
									<article class="tasty-fonts-health-row tasty-fonts-health-row--reference">
										<span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
										<div class="tasty-fonts-health-row-copy">
											<div class="tasty-fonts-health-row-title">
												<strong><?php esc_html_e('No copyable support paths yet.', 'tasty-fonts'); ?></strong>
												<span><?php esc_html_e('Generated CSS paths and storage URLs appear here once WordPress reports them.', 'tasty-fonts'); ?></span>
											</div>
										</div>
										<div class="tasty-fonts-health-row-actions">
											<?php $renderDebugHelpButton(__('No copyable support paths yet.', 'tasty-fonts'), __('Copyable support paths appear after WordPress reports generated CSS URLs, filesystem paths, or other diagnostics that are useful for support.', 'tasty-fonts')); ?>
										</div>
									</article>
                                                </div>
                                            </section>
                                        <?php endif; ?>
                                    </div>
				</section>
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
                                <?php if ($generatedCssIsEmpty): ?>
                                    <?php
                                    $this->renderRichEmptyState(
                                        __('No Generated CSS Yet', 'tasty-fonts'),
                                        __('Publish sitewide roles to generate the runtime stylesheet Tasty Fonts serves to visitors, editors, and integrations.', 'tasty-fonts'),
                                        [
                                            'class' => 'tasty-fonts-empty-state--generated-css',
                                            'actions' => static function () use ($deployFontsPageUrl): void {
                                                ?>
                                                <a class="button button-primary" href="<?php echo esc_url($deployFontsPageUrl); ?>"><?php esc_html_e('Deploy Fonts', 'tasty-fonts'); ?></a>
                                                <?php
                                            },
                                        ]
                                    );
                                    ?>
                                <?php else: ?>
                                    <div class="tasty-fonts-code-panel is-active">
                                        <?php
                                        $toolsRenderer->renderCodeEditor($generatedCssPanel, [
                                            'preserve_display_format' => true,
                                            'allow_readable_toggle' => !empty($minifyCssOutput),
                                            'default_to_readable' => !empty($minifyCssOutput),
                                            'toggle_label_active' => __('Minified CSS', 'tasty-fonts'),
                                            'download_url' => $generatedCssDownloadUrl,
                                            'download_label' => __('Download Generated CSS', 'tasty-fonts'),
                                        ]);
                                        ?>
                                    </div>
                                <?php endif; ?>
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
                                <div class="tasty-fonts-site-transfer-panel tasty-fonts-developer-panel">
                                    <div class="tasty-fonts-page-notice tasty-fonts-inline-note tasty-fonts-inline-note--warning tasty-fonts-developer-tools-note" data-developer-dirty-notice hidden>
                                        <strong><?php esc_html_e('Save settings first', 'tasty-fonts'); ?></strong>
                                        <span><?php esc_html_e('Pending settings changes temporarily disable developer actions.', 'tasty-fonts'); ?></span>
                                    </div>
                                    <div class="tasty-fonts-health-board tasty-fonts-developer-board">
										<section class="tasty-fonts-health-group tasty-fonts-health-group--developer tasty-fonts-developer-tool-group tasty-fonts-developer-tool-group--admin-experience" aria-labelledby="tasty-fonts-advanced-developer-group-admin-experience">
											<div class="tasty-fonts-health-group-head">
												<h4 id="tasty-fonts-advanced-developer-group-admin-experience"><?php esc_html_e('Admin Experience', 'tasty-fonts'); ?></h4>
												<span class="tasty-fonts-health-group-count"><?php esc_html_e('Guidance & Logs', 'tasty-fonts'); ?></span>
											</div>
											<div class="tasty-fonts-health-list">
												<article class="tasty-fonts-health-row tasty-fonts-developer-row tasty-fonts-developer-row--admin-setting">
													<span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
													<div class="tasty-fonts-health-row-copy">
														<div class="tasty-fonts-health-row-title">
															<strong><?php esc_html_e('Compact Mode', 'tasty-fonts'); ?></strong>
															<?php if ($showSettingsDescriptions): ?>
																<span><?php esc_html_e('Hides helper copy and passive tips for a denser admin UI.', 'tasty-fonts'); ?></span>
															<?php endif; ?>
														</div>
													</div>
													<div class="tasty-fonts-health-row-actions">
														<button
															type="button"
															class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger tasty-fonts-health-help-trigger"
															aria-label="<?php esc_attr_e('Explain Compact Mode', 'tasty-fonts'); ?>"
															<?php $renderSiteTransferHelpAttributes(__('Hides helper copy and passive tips across the admin UI for a denser workspace.', 'tasty-fonts')); ?>
														>?</button>
														<form method="post" class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-developer-row-form tasty-fonts-developer-setting-form" data-auto-submit-on-change>
															<?php wp_nonce_field('tasty_fonts_save_settings'); ?>
															<input type="hidden" name="tasty_fonts_save_settings" value="1">
															<input type="hidden" name="training_wheels_off" value="0">
															<label class="tasty-fonts-toggle-field tasty-fonts-developer-setting-toggle">
																<input type="checkbox" class="tasty-fonts-toggle-input" name="training_wheels_off" value="1" aria-label="<?php esc_attr_e('Compact Mode', 'tasty-fonts'); ?>" <?php checked($trainingWheelsOff); ?>>
																<span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
															</label>
														</form>
													</div>
												</article>
												<article class="tasty-fonts-health-row tasty-fonts-developer-row tasty-fonts-developer-row--admin-setting">
													<span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
													<div class="tasty-fonts-health-row-copy">
														<div class="tasty-fonts-health-row-title">
															<strong><?php esc_html_e('Show Activity Log', 'tasty-fonts'); ?></strong>
															<?php if ($showSettingsDescriptions): ?>
																<span><?php esc_html_e('Adds the full activity log to Advanced Tools. Events are still recorded when hidden.', 'tasty-fonts'); ?></span>
															<?php endif; ?>
														</div>
													</div>
													<div class="tasty-fonts-health-row-actions">
														<button
															type="button"
															class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger tasty-fonts-health-help-trigger"
															aria-label="<?php esc_attr_e('Explain Show Activity Log', 'tasty-fonts'); ?>"
															<?php $renderSiteTransferHelpAttributes(__('Shows the full activity log in Advanced Tools. Tasty Fonts still records relevant events while this is hidden.', 'tasty-fonts')); ?>
														>?</button>
														<form method="post" class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-developer-row-form tasty-fonts-developer-setting-form" data-auto-submit-on-change>
															<?php wp_nonce_field('tasty_fonts_save_settings'); ?>
															<input type="hidden" name="tasty_fonts_save_settings" value="1">
															<input type="hidden" name="show_activity_log" value="0">
															<label class="tasty-fonts-toggle-field tasty-fonts-developer-setting-toggle">
																<input type="checkbox" class="tasty-fonts-toggle-input" name="show_activity_log" value="1" aria-label="<?php esc_attr_e('Show Activity Log', 'tasty-fonts'); ?>" <?php checked($showActivityLog); ?>>
																<span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
															</label>
														</form>
													</div>
												</article>
											</div>
										</section>
										<section class="tasty-fonts-health-group tasty-fonts-health-group--developer tasty-fonts-developer-tool-group tasty-fonts-developer-tool-group--release-rail" aria-labelledby="tasty-fonts-advanced-developer-group-release-rail">
											<div class="tasty-fonts-health-group-head">
												<h4 id="tasty-fonts-advanced-developer-group-release-rail"><?php esc_html_e('Release Rail', 'tasty-fonts'); ?></h4>
												<span class="tasty-fonts-health-group-count"><?php esc_html_e('Testing Channel', 'tasty-fonts'); ?></span>
											</div>
											<div class="tasty-fonts-health-list">
												<article class="tasty-fonts-health-row tasty-fonts-developer-row tasty-fonts-developer-row--release-rail">
													<span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
													<div class="tasty-fonts-health-row-copy">
														<div class="tasty-fonts-health-row-title">
															<strong><?php esc_html_e('Update Channel', 'tasty-fonts'); ?></strong>
															<?php if ($showSettingsDescriptions): ?>
																<span><?php esc_html_e('Choose which GitHub release rail this site follows. Stable is best for production; Beta and Nightly are for testing.', 'tasty-fonts'); ?></span>
															<?php endif; ?>
														</div>
													</div>
													<div class="tasty-fonts-health-row-actions">
														<button
															type="button"
															class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger tasty-fonts-health-help-trigger"
															aria-label="<?php esc_attr_e('Explain Update Channel', 'tasty-fonts'); ?>"
															<?php $renderSiteTransferHelpAttributes(__('Stable is recommended for production sites. Use Beta or Nightly only when you are testing release candidates or development builds.', 'tasty-fonts')); ?>
														>?</button>
														<form method="post" class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-developer-row-form tasty-fonts-release-rail-form" data-auto-submit-on-change>
															<?php wp_nonce_field('tasty_fonts_save_settings'); ?>
															<input type="hidden" name="tasty_fonts_save_settings" value="1">
															<div class="tasty-fonts-settings-flat-row-form tasty-fonts-settings-flat-row-form--channel-control">
																<span class="tasty-fonts-select-field tasty-fonts-settings-row-select">
																	<select class="tasty-fonts-select" name="update_channel" aria-label="<?php esc_attr_e('Update channel', 'tasty-fonts'); ?>">
																		<?php foreach ($updateChannelOptions as $option): ?>
																			<?php $optionValue = (string) ($option['value'] ?? 'stable'); ?>
																			<option value="<?php echo esc_attr($optionValue); ?>" <?php selected($updateChannel, $optionValue); ?>>
																				<?php echo esc_html((string) ($option['label'] ?? '')); ?>
																			</option>
																		<?php endforeach; ?>
																	</select>
																</span>
															</div>
														</form>
														<?php if (!empty($updateChannelStatus['can_reinstall'])): ?>
															<form method="post" class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-developer-row-form tasty-fonts-release-rail-reinstall-form">
																<?php wp_nonce_field('tasty_fonts_reinstall_update_channel', '_tasty_fonts_reinstall_nonce'); ?>
																<div class="tasty-fonts-developer-action-row tasty-fonts-settings-flat-row-actions">
																	<button
																		type="submit"
																		class="button button-small tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--restore tasty-fonts-developer-action-button"
																		name="tasty_fonts_reinstall_update_channel"
																		value="1"
																		aria-label="<?php esc_attr_e('Reinstall selected channel', 'tasty-fonts'); ?>"
																		<?php $renderSiteTransferHelpAttributes((string) ($updateChannelStatus['state_copy'] ?? '')); ?>
																	><?php esc_html_e('Reinstall', 'tasty-fonts'); ?></button>
																</div>
															</form>
														<?php endif; ?>
													</div>
												</article>
											</div>
										</section>
                                        <?php foreach ($developerToolGroups as $developerToolGroup): ?>
                                            <?php
                                            $developerToolGroupSlug = isset($developerToolGroup['slug']) ? sanitize_html_class((string) $developerToolGroup['slug']) : 'group';
                                            $developerToolGroupClass = 'tasty-fonts-health-group tasty-fonts-health-group--developer tasty-fonts-developer-tool-group';
                                            $developerToolActions = is_array($developerToolGroup['actions'] ?? null) ? $developerToolGroup['actions'] : [];

                                            if (!empty($developerToolGroup['group_class'])) {
                                                $developerToolGroupClass .= ' ' . trim((string) $developerToolGroup['group_class']);
                                            }
                                            ?>
                                            <section class="<?php echo esc_attr($developerToolGroupClass); ?>" aria-labelledby="tasty-fonts-advanced-developer-group-<?php echo esc_attr($developerToolGroupSlug); ?>">
                                                <div class="tasty-fonts-health-group-head">
                                                    <h4 id="tasty-fonts-advanced-developer-group-<?php echo esc_attr($developerToolGroupSlug); ?>"><?php echo esc_html((string) $developerToolGroup['title']); ?></h4>
                                                    <span class="tasty-fonts-health-group-count">
                                                        <?php
                                                        echo esc_html(
                                                            sprintf(
                                                                _n('%d Action', '%d Actions', count($developerToolActions), 'tasty-fonts'),
                                                                count($developerToolActions)
                                                            )
                                                        );
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="tasty-fonts-health-list">
                                                    <?php foreach ($developerToolActions as $developerToolAction): ?>
                                                        <?php
                                                        $developerToolRowClass = 'tasty-fonts-health-row tasty-fonts-developer-row';
                                                        $developerToolSlug = sanitize_key((string) ($developerToolAction['slug'] ?? ''));
                                                        $developerToolStatus = $developerToolSlug !== '' && isset($developerToolStatuses[$developerToolSlug]) && is_array($developerToolStatuses[$developerToolSlug])
                                                            ? $developerToolStatuses[$developerToolSlug]
                                                            : [];
                                                        $developerToolSummary = trim((string) ($developerToolStatus['summary'] ?? ''));
                                                        $developerToolLastRun = trim((string) ($developerToolStatus['last_run'] ?? ''));
                                                        $developerToolConfirmPhrase = trim((string) ($developerToolAction['confirm_phrase'] ?? ''));
                                                        $developerToolDescription = trim((string) ($developerToolAction['description'] ?? ''));
                                                        $developerToolBlocked = !empty($developerToolAction['blocked']);
                                                        $developerToolBlockedMessage = trim((string) ($developerToolAction['blocked_message'] ?? ''));
                                                        $developerToolSummaryDisplay = $formatDeveloperToolMeta($developerToolSummary);
                                                        $developerToolLastRunDisplay = $formatDeveloperToolMeta($developerToolLastRun);
                                                        $developerToolIsIntegrationScan = $developerToolSlug === 'reset_integration_detection_state';
                                                        $developerToolButtonClass = trim((string) ($developerToolAction['button_class'] ?? 'button'));
                                                        $developerToolHelpCopy = $developerToolDescription !== ''
                                                            ? $developerToolDescription
                                                            : (string) $developerToolAction['title'];

                                                        if ($developerToolSummary !== '') {
                                                            $developerToolHelpCopy .= ' ' . $developerToolSummary;
                                                        }

                                                        if ($developerToolLastRun !== '') {
                                                            $developerToolHelpCopy .= ' ' . $developerToolLastRun;
                                                        }

                                                        if ($developerToolBlocked && $developerToolBlockedMessage !== '') {
                                                            $developerToolHelpCopy .= ' ' . $developerToolBlockedMessage;
                                                            $developerToolButtonClass .= ' is-disabled';
                                                        }

                                                        if ($developerToolIsIntegrationScan) {
                                                            $developerToolHelpCopy .= ' ' . sprintf(
                                                                /* translators: 1: detected integrations, 2: enabled integrations */
                                                                __('Detected integrations: %1$s. Enabled integrations: %2$s.', 'tasty-fonts'),
                                                                $developerDetectedIntegrationSummary,
                                                                $developerEnabledIntegrationSummary
                                                            );
                                                        }

                                                        if (!empty($developerToolAction['card_class'])) {
                                                            $developerToolRowClass .= ' tasty-fonts-developer-row--danger';
                                                        }
                                                        ?>
                                                        <article class="<?php echo esc_attr($developerToolRowClass); ?>">
                                                            <span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
                                                            <div class="tasty-fonts-health-row-copy">
                                                                <div class="tasty-fonts-health-row-title">
                                                                    <strong><?php echo esc_html((string) $developerToolAction['title']); ?></strong>
                                                                </div>
                                                                <?php if ($developerToolIsIntegrationScan || $developerToolSummaryDisplay !== '' || $developerToolLastRunDisplay !== ''): ?>
                                                                    <div class="tasty-fonts-developer-tool-meta" aria-label="<?php esc_attr_e('Last action status', 'tasty-fonts'); ?>">
                                                                        <?php if ($developerToolIsIntegrationScan): ?>
                                                                            <span class="tasty-fonts-developer-tool-meta-pill tasty-fonts-developer-tool-meta-detected">
                                                                                <span class="tasty-fonts-developer-tool-meta-label"><?php esc_html_e('Detected', 'tasty-fonts'); ?></span>
                                                                                <?php if ($developerDetectedIntegrations !== []): ?>
                                                                                    <span class="tasty-fonts-developer-integration-icons" aria-label="<?php echo esc_attr(sprintf(__('Detected integrations: %s', 'tasty-fonts'), $developerDetectedIntegrationSummary)); ?>">
                                                                                        <?php foreach ($developerDetectedIntegrations as $developerIntegration): ?>
                                                                                            <?php
	                                                                                            $developerIntegrationSlug = sanitize_html_class((string) ($developerIntegration['slug'] ?? ''));
	                                                                                            $developerIntegrationTitle = trim((string) ($developerIntegration['summary_title'] ?? $developerIntegration['title'] ?? ''));

                                                                                            if ($developerIntegrationSlug === '' || $developerIntegrationTitle === '') {
                                                                                                continue;
                                                                                            }
                                                                                            ?>
                                                                                            <span
                                                                                                class="tasty-fonts-integration-mark tasty-fonts-integration-mark--<?php echo esc_attr($developerIntegrationSlug); ?>"
                                                                                                role="img"
                                                                                                tabindex="0"
                                                                                                aria-label="<?php echo esc_attr($developerIntegrationTitle); ?>"
                                                                                                <?php $renderSiteTransferHelpAttributes($developerIntegrationTitle); ?>
                                                                                            ></span>
                                                                                        <?php endforeach; ?>
                                                                                    </span>
                                                                                <?php else: ?>
                                                                                    <span><?php esc_html_e('None', 'tasty-fonts'); ?></span>
                                                                                <?php endif; ?>
                                                                            </span>
                                                                            <span class="tasty-fonts-developer-tool-meta-pill tasty-fonts-developer-tool-meta-enabled">
                                                                                <span class="tasty-fonts-developer-tool-meta-label"><?php esc_html_e('On', 'tasty-fonts'); ?></span>
                                                                                <?php if ($developerEnabledIntegrations !== []): ?>
                                                                                    <span class="tasty-fonts-developer-integration-icons" aria-label="<?php echo esc_attr(sprintf(__('Enabled integrations: %s', 'tasty-fonts'), $developerEnabledIntegrationSummary)); ?>">
                                                                                        <?php foreach ($developerEnabledIntegrations as $developerIntegration): ?>
                                                                                            <?php
	                                                                                            $developerIntegrationSlug = sanitize_html_class((string) ($developerIntegration['slug'] ?? ''));
	                                                                                            $developerIntegrationTitle = trim((string) ($developerIntegration['summary_title'] ?? $developerIntegration['title'] ?? ''));

                                                                                            if ($developerIntegrationSlug === '' || $developerIntegrationTitle === '') {
                                                                                                continue;
                                                                                            }
                                                                                            ?>
                                                                                            <span
                                                                                                class="tasty-fonts-integration-mark tasty-fonts-integration-mark--<?php echo esc_attr($developerIntegrationSlug); ?>"
                                                                                                role="img"
                                                                                                tabindex="0"
                                                                                                aria-label="<?php echo esc_attr($developerIntegrationTitle); ?>"
                                                                                                <?php $renderSiteTransferHelpAttributes($developerIntegrationTitle); ?>
                                                                                            ></span>
                                                                                        <?php endforeach; ?>
                                                                                    </span>
                                                                                <?php else: ?>
                                                                                    <span><?php esc_html_e('None', 'tasty-fonts'); ?></span>
                                                                                <?php endif; ?>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                        <?php if ($developerToolSummaryDisplay !== ''): ?>
                                                                            <span class="tasty-fonts-developer-tool-meta-pill tasty-fonts-developer-tool-meta-summary"><?php echo esc_html($developerToolSummaryDisplay); ?></span>
                                                                        <?php endif; ?>
                                                                        <?php if ($developerToolLastRunDisplay !== ''): ?>
                                                                            <span class="tasty-fonts-developer-tool-meta-pill tasty-fonts-developer-tool-meta-last-run">
                                                                                <?php if (stripos($developerToolLastRun, 'Last run') === 0): ?>
                                                                                    <span class="tasty-fonts-developer-tool-meta-label"><?php esc_html_e('Last run', 'tasty-fonts'); ?></span>
                                                                                <?php endif; ?>
                                                                                <span><?php echo esc_html($developerToolLastRunDisplay); ?></span>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="tasty-fonts-health-row-actions">
                                                                <button
                                                                    type="button"
                                                                    class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger tasty-fonts-health-help-trigger"
                                                                    aria-label="<?php echo esc_attr(sprintf(__('Explain %s', 'tasty-fonts'), (string) $developerToolAction['title'])); ?>"
                                                                    <?php $renderSiteTransferHelpAttributes($developerToolHelpCopy); ?>
                                                                >?</button>
                                                                <?php if ($developerToolIsIntegrationScan): ?>
                                                                    <a
                                                                        class="button tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--navigate tasty-fonts-developer-action-button tasty-fonts-developer-action-button--secondary"
                                                                        href="<?php echo esc_url($integrationsSettingsUrl); ?>"
                                                                    >
                                                                        <?php esc_html_e('Integrations', 'tasty-fonts'); ?>
                                                                    </a>
                                                                <?php endif; ?>
                                                                <form
                                                                    method="post"
                                                                    class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-developer-row-form"
                                                                    data-developer-tool-form
                                                                    <?php if ($developerToolBlocked && $developerToolBlockedMessage !== ''): ?>data-developer-blocked-message="<?php echo esc_attr($developerToolBlockedMessage); ?>"<?php endif; ?>
                                                                    <?php if (!empty($developerToolAction['confirm_message'])): ?>data-developer-confirm-message="<?php echo esc_attr((string) $developerToolAction['confirm_message']); ?>"<?php endif; ?>
                                                                    <?php if ($developerToolConfirmPhrase !== ''): ?>data-developer-confirm-input="<?php echo esc_attr($developerToolConfirmPhrase); ?>"<?php endif; ?>
                                                                >
                                                                    <?php wp_nonce_field((string) $developerToolAction['nonce']); ?>
                                                                    <div class="tasty-fonts-developer-action-row">
                                                                        <button
                                                                            type="submit"
                                                                            class="<?php echo esc_attr($developerToolButtonClass); ?>"
                                                                            name="<?php echo esc_attr((string) $developerToolAction['action_name']); ?>"
                                                                            value="1"
                                                                            data-developer-submit
                                                                            <?php if ($developerToolBlocked && $developerToolBlockedMessage !== ''): ?>
                                                                                data-delete-blocked="<?php echo esc_attr($developerToolBlockedMessage); ?>"
                                                                                aria-disabled="true"
                                                                                <?php $renderSiteTransferHelpAttributes($developerToolBlockedMessage, true); ?>
                                                                            <?php endif; ?>
                                                                        ><?php echo esc_html((string) $developerToolAction['button_label']); ?></button>
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
                                                        </article>
                                                    <?php endforeach; ?>
                                                </div>
                                            </section>
                                        <?php endforeach; ?>
                                    </div>
	                                </div>
	                            </section>

	                            <section
	                                id="tasty-fonts-diagnostics-panel-cli"
	                                class="tasty-fonts-studio-panel"
	                                data-tab-group="diagnostics"
	                                data-tab-panel="cli"
	                                role="tabpanel"
	                                aria-labelledby="tasty-fonts-diagnostics-tab-cli"
	                                hidden
	                            >
	                                <div class="tasty-fonts-site-transfer-panel tasty-fonts-cli-panel">
	                                    <div class="tasty-fonts-health-board tasty-fonts-cli-board">
	                                        <?php foreach ($cliCommandGroups as $cliCommandGroup): ?>
	                                            <?php
	                                            $cliCommandGroupSlug = isset($cliCommandGroup['slug']) ? sanitize_html_class((string) $cliCommandGroup['slug']) : 'group';
	                                            $cliCommands = is_array($cliCommandGroup['commands'] ?? null) ? $cliCommandGroup['commands'] : [];
	                                            ?>
	                                            <section class="tasty-fonts-health-group tasty-fonts-health-group--cli tasty-fonts-cli-command-group" aria-labelledby="tasty-fonts-advanced-cli-group-<?php echo esc_attr($cliCommandGroupSlug); ?>">
	                                                <div class="tasty-fonts-health-group-head">
	                                                    <h4 id="tasty-fonts-advanced-cli-group-<?php echo esc_attr($cliCommandGroupSlug); ?>"><?php echo esc_html((string) ($cliCommandGroup['title'] ?? '')); ?></h4>
	                                                    <span class="tasty-fonts-health-group-count">
	                                                        <?php
	                                                        echo esc_html(
	                                                            sprintf(
	                                                                _n('%d Command', '%d Commands', count($cliCommands), 'tasty-fonts'),
	                                                                count($cliCommands)
	                                                            )
	                                                        );
	                                                        ?>
	                                                    </span>
	                                                </div>
	                                                <div class="tasty-fonts-health-list">
	                                                    <?php foreach ($cliCommands as $cliCommand): ?>
	                                                        <?php
	                                                        if (!is_array($cliCommand)) {
	                                                            continue;
	                                                        }

	                                                        $cliCommandLabel = trim((string) ($cliCommand['label'] ?? ''));
	                                                        $cliCommandValue = trim((string) ($cliCommand['command'] ?? ''));
	                                                        $cliCommandDescription = trim((string) ($cliCommand['description'] ?? ''));

	                                                        if ($cliCommandLabel === '' || $cliCommandValue === '') {
	                                                            continue;
	                                                        }
	                                                        ?>
	                                                        <article class="tasty-fonts-health-row tasty-fonts-health-row--reference tasty-fonts-cli-row">
	                                                            <span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
	                                                            <div class="tasty-fonts-health-row-copy">
	                                                                <div class="tasty-fonts-health-row-title">
	                                                                    <strong><?php echo esc_html($cliCommandLabel); ?></strong>
	                                                                    <span class="tasty-fonts-code"><?php echo esc_html($cliCommandValue); ?></span>
	                                                                </div>
	                                                            </div>
	                                                            <div class="tasty-fonts-health-row-actions">
	                                                                <?php if ($cliCommandDescription !== ''): ?>
	                                                                    <button
	                                                                        type="button"
	                                                                        class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger tasty-fonts-health-help-trigger tasty-fonts-cli-help-trigger"
	                                                                        aria-label="<?php echo esc_attr(sprintf(__('Explain %s', 'tasty-fonts'), $cliCommandLabel)); ?>"
	                                                                        <?php $renderSiteTransferHelpAttributes($cliCommandDescription); ?>
	                                                                    >?</button>
	                                                                <?php endif; ?>
	                                                                <button
	                                                                    type="button"
	                                                                    class="button tasty-fonts-output-copy-button tasty-fonts-diagnostic-copy-button tasty-fonts-cli-copy-button"
	                                                                    data-copy-text="<?php echo esc_attr($cliCommandValue); ?>"
	                                                                    data-copy-success="<?php esc_attr_e('Command copied.', 'tasty-fonts'); ?>"
	                                                                    data-copy-static-label="1"
	                                                                    aria-label="<?php echo esc_attr(sprintf(__('Copy command: %s', 'tasty-fonts'), $cliCommandLabel)); ?>"
	                                                                >
	                                                                    <span class="screen-reader-text"><?php echo esc_html(sprintf(__('Copy command: %s', 'tasty-fonts'), $cliCommandLabel)); ?></span>
	                                                                </button>
	                                                            </div>
	                                                        </article>
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
                                    <section class="tasty-fonts-site-transfer-panel tasty-fonts-transfer-workbench" aria-label="<?php esc_attr_e('Transfer and recovery workbench', 'tasty-fonts'); ?>">
                                        <div class="tasty-fonts-health-board tasty-fonts-transfer-flow-board">
                                            <section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Portable transfer', 'tasty-fonts'); ?>">
                                                <div class="tasty-fonts-health-group-head">
                                                    <h4><?php esc_html_e('Portable Transfer', 'tasty-fonts'); ?></h4>
                                                    <span class="tasty-fonts-health-group-count"><?php esc_html_e('Export Or Validate', 'tasty-fonts'); ?></span>
                                                </div>
                                                <div class="tasty-fonts-health-list">
                                                    <article class="tasty-fonts-health-row tasty-fonts-transfer-row tasty-fonts-transfer-row--export">
                                                        <span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
                                                        <div class="tasty-fonts-health-row-copy">
                                                            <div class="tasty-fonts-health-row-title">
                                                                <strong><?php esc_html_e('Export Bundle', 'tasty-fonts'); ?></strong>
                                                                <?php if ($showSettingsDescriptions): ?>
                                                                    <span><?php esc_html_e('Downloads settings, live roles, library metadata, and managed font files. Runtime cache, generated CSS, logs, and API keys stay out.', 'tasty-fonts'); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if (!$siteTransferAvailable && $siteTransferMessage !== ''): ?>
                                                                <span class="tasty-fonts-site-transfer-note tasty-fonts-site-transfer-note--muted"><?php echo esc_html($siteTransferMessage); ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($siteTransferExportBundles !== []): ?>
                                                                <div class="tasty-fonts-snapshot-list tasty-fonts-export-bundle-list" aria-label="<?php esc_attr_e('Saved export bundles', 'tasty-fonts'); ?>">
                                                                    <?php foreach ($siteTransferExportBundles as $exportBundle): ?>
                                                                        <?php
                                                                        $exportId = sanitize_key((string) ($exportBundle['id'] ?? ''));
                                                                        $exportCreatedAt = trim((string) ($exportBundle['created_at'] ?? ''));
                                                                        $exportLabel = trim((string) ($exportBundle['label'] ?? ''));
                                                                        $exportFilename = trim((string) ($exportBundle['filename'] ?? ''));
                                                                        $exportDownloadUrl = (string) ($exportBundle['download_url'] ?? '');
                                                                        $exportFamilyCount = max(0, (int) ($exportBundle['families'] ?? 0));
                                                                        $exportFileCount = max(0, (int) ($exportBundle['files'] ?? 0));
                                                                        $exportSize = max(0, (int) ($exportBundle['size'] ?? 0));
                                                                        $exportVersion = trim((string) ($exportBundle['plugin_version'] ?? ''));
                                                                        $exportProtected = !empty($exportBundle['protected']);
                                                                        $exportFamilyNames = is_array($exportBundle['family_names'] ?? null) ? array_values(array_filter(array_map('strval', $exportBundle['family_names']))) : [];
                                                                        $exportRoleFamilies = is_array($exportBundle['role_families'] ?? null) ? array_values(array_filter(array_map('strval', $exportBundle['role_families']))) : [];
                                                                        $exportDisplayName = $exportLabel !== '' ? $exportLabel : __('Export bundle', 'tasty-fonts');
                                                                        $exportFamilyText = $exportFamilyNames !== []
                                                                            ? implode(', ', array_slice($exportFamilyNames, 0, 6))
                                                                            : __('No families captured', 'tasty-fonts');
                                                                        $exportRoleText = $exportRoleFamilies !== []
                                                                            ? implode(', ', array_slice($exportRoleFamilies, 0, 4))
                                                                            : __('No live roles', 'tasty-fonts');
                                                                        $exportRetentionText = sprintf(
                                                                            /* translators: %d: retained export bundle count */
                                                                            _n('Kept until %d newer unprotected export replaces it.', 'Kept until %d newer unprotected exports replace it.', $exportRetentionLimit, 'tasty-fonts'),
                                                                            $exportRetentionLimit
                                                                        );
                                                                        $exportMeta = [
                                                                            $exportCreatedAt !== '' ? $exportCreatedAt : __('No timestamp', 'tasty-fonts'),
                                                                            sprintf(
                                                                                /* translators: %d: font family count */
                                                                                _n('%d family', '%d families', $exportFamilyCount, 'tasty-fonts'),
                                                                                $exportFamilyCount
                                                                            ),
                                                                            sprintf(
                                                                                /* translators: %d: managed font file count */
                                                                                _n('%d file', '%d files', $exportFileCount, 'tasty-fonts'),
                                                                                $exportFileCount
                                                                            ),
                                                                        ];
                                                                        if ($exportSize > 0) {
                                                                            $exportMeta[] = sprintf(
                                                                                /* translators: %s: export bundle ZIP size */
                                                                                __('Bundle size: %s', 'tasty-fonts'),
                                                                                size_format($exportSize)
                                                                            );
                                                                        }
                                                                        if ($exportVersion !== '') {
                                                                            $exportMeta[] = sprintf(
                                                                                /* translators: %s: plugin version */
                                                                                __('v%s', 'tasty-fonts'),
                                                                                $exportVersion
                                                                            );
                                                                        }
                                                                        if ($exportProtected) {
                                                                            $exportMeta[] = __('Protected', 'tasty-fonts');
                                                                        }
                                                                        ?>
                                                                        <?php if ($exportId !== ''): ?>
                                                                            <div class="tasty-fonts-snapshot-row tasty-fonts-export-bundle-row<?php echo $exportProtected ? ' is-protected' : ''; ?>" aria-describedby="<?php echo esc_attr('tasty-fonts-export-details-' . $exportId); ?>">
                                                                                <span class="tasty-fonts-snapshot-row-copy">
                                                                                    <strong><?php echo esc_html($exportDisplayName); ?></strong>
                                                                                    <span class="tasty-fonts-snapshot-row-meta"><?php echo esc_html(implode(' · ', $exportMeta)); ?></span>
                                                                                    <span id="<?php echo esc_attr('tasty-fonts-export-details-' . $exportId); ?>" class="tasty-fonts-snapshot-row-details" role="tooltip">
                                                                                        <span class="tasty-fonts-snapshot-row-detail">
                                                                                            <?php
                                                                                            echo esc_html(sprintf(
                                                                                                /* translators: %s: comma-separated font family names */
                                                                                                __('Families: %s', 'tasty-fonts'),
                                                                                                $exportFamilyText
                                                                                            ));
                                                                                            ?>
                                                                                        </span>
                                                                                        <span class="tasty-fonts-snapshot-row-detail">
                                                                                            <?php
                                                                                            echo esc_html(sprintf(
                                                                                                /* translators: %s: comma-separated live role family names */
                                                                                                __('Live roles: %s', 'tasty-fonts'),
                                                                                                $exportRoleText
                                                                                            ));
                                                                                            ?>
                                                                                        </span>
                                                                                        <span class="tasty-fonts-snapshot-row-detail">
                                                                                            <?php
                                                                                            echo esc_html(sprintf(
                                                                                                /* translators: %s: export bundle filename */
                                                                                                __('File: %s', 'tasty-fonts'),
                                                                                                $exportFilename !== '' ? $exportFilename : __('Saved export bundle', 'tasty-fonts')
                                                                                            ));
                                                                                            ?>
                                                                                        </span>
                                                                                        <?php if ($exportSize > 0): ?>
                                                                                            <span class="tasty-fonts-snapshot-row-detail">
                                                                                                <?php
                                                                                                echo esc_html(sprintf(
                                                                                                    /* translators: %s: export bundle ZIP size */
                                                                                                    __('Bundle size: %s', 'tasty-fonts'),
                                                                                                    size_format($exportSize)
                                                                                                ));
                                                                                                ?>
                                                                                            </span>
                                                                                        <?php endif; ?>
                                                                                        <span class="tasty-fonts-snapshot-row-detail">
                                                                                            <?php echo esc_html($exportProtected ? __('Protected from pruning and deletion.', 'tasty-fonts') : $exportRetentionText); ?>
                                                                                        </span>
                                                                                    </span>
                                                                                </span>
                                                                                <div class="tasty-fonts-snapshot-row-actions">
                                                                                    <?php if ($exportDownloadUrl !== ''): ?>
                                                                                        <a
                                                                                            class="button button-secondary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--download tasty-fonts-snapshot-icon-button"
                                                                                            href="<?php echo esc_url($exportDownloadUrl); ?>"
                                                                                            title="<?php esc_attr_e('Download export bundle', 'tasty-fonts'); ?>"
                                                                                            aria-label="<?php esc_attr_e('Download export bundle', 'tasty-fonts'); ?>"
                                                                                        >
                                                                                            <span class="screen-reader-text"><?php esc_html_e('Download', 'tasty-fonts'); ?></span>
                                                                                        </a>
                                                                                    <?php endif; ?>
                                                                                    <button
                                                                                        type="button"
                                                                                        class="button button-secondary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--rename tasty-fonts-snapshot-icon-button"
                                                                                        title="<?php esc_attr_e('Rename export bundle', 'tasty-fonts'); ?>"
                                                                                        aria-label="<?php esc_attr_e('Rename export bundle', 'tasty-fonts'); ?>"
                                                                                        aria-expanded="false"
                                                                                        aria-controls="<?php echo esc_attr('tasty-fonts-export-rename-' . $exportId); ?>"
                                                                                        data-snapshot-rename-toggle
                                                                                    >
                                                                                        <span class="screen-reader-text"><?php esc_html_e('Rename', 'tasty-fonts'); ?></span>
                                                                                    </button>
                                                                                    <form
                                                                                        id="<?php echo esc_attr('tasty-fonts-export-rename-' . $exportId); ?>"
                                                                                        method="post"
                                                                                        class="tasty-fonts-snapshot-rename-form"
                                                                                        data-snapshot-rename-form
                                                                                        hidden
                                                                                    >
                                                                                        <?php wp_nonce_field($siteTransferExportRenameAction); ?>
                                                                                        <input type="hidden" name="<?php echo esc_attr($siteTransferExportRenameAction); ?>" value="1">
                                                                                        <input type="hidden" name="tasty_fonts_export_bundle_id" value="<?php echo esc_attr($exportId); ?>">
                                                                                        <label class="screen-reader-text" for="<?php echo esc_attr('tasty-fonts-export-label-' . $exportId); ?>"><?php esc_html_e('Export bundle name', 'tasty-fonts'); ?></label>
                                                                                        <input
                                                                                            id="<?php echo esc_attr('tasty-fonts-export-label-' . $exportId); ?>"
                                                                                            class="tasty-fonts-text-control tasty-fonts-snapshot-label-input"
                                                                                            type="text"
                                                                                            name="tasty_fonts_export_bundle_label"
                                                                                            value="<?php echo esc_attr($exportLabel); ?>"
                                                                                            data-snapshot-rename-input
                                                                                            data-original-value="<?php echo esc_attr($exportLabel); ?>"
                                                                                            maxlength="80"
                                                                                            placeholder="<?php esc_attr_e('Export bundle', 'tasty-fonts'); ?>"
                                                                                        >
                                                                                        <button type="submit" class="button button-secondary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--save tasty-fonts-snapshot-icon-button" title="<?php esc_attr_e('Save export bundle name', 'tasty-fonts'); ?>" aria-label="<?php esc_attr_e('Save export bundle name', 'tasty-fonts'); ?>">
                                                                                            <span class="screen-reader-text"><?php esc_html_e('Save export bundle name', 'tasty-fonts'); ?></span>
                                                                                        </button>
                                                                                        <button type="button" class="button button-secondary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--cancel tasty-fonts-snapshot-icon-button" title="<?php esc_attr_e('Cancel rename', 'tasty-fonts'); ?>" aria-label="<?php esc_attr_e('Cancel rename', 'tasty-fonts'); ?>" data-snapshot-rename-cancel>
                                                                                            <span class="screen-reader-text"><?php esc_html_e('Cancel rename', 'tasty-fonts'); ?></span>
                                                                                        </button>
                                                                                    </form>
                                                                                    <form method="post" class="tasty-fonts-snapshot-action-form">
                                                                                        <?php wp_nonce_field($siteTransferExportProtectAction); ?>
                                                                                        <input type="hidden" name="<?php echo esc_attr($siteTransferExportProtectAction); ?>" value="1">
                                                                                        <input type="hidden" name="tasty_fonts_export_bundle_id" value="<?php echo esc_attr($exportId); ?>">
                                                                                        <input type="hidden" name="tasty_fonts_export_bundle_protected" value="<?php echo esc_attr($exportProtected ? '0' : '1'); ?>">
                                                                                        <button type="submit" class="button button-secondary tasty-fonts-advanced-row-action <?php echo esc_attr($exportProtected ? 'tasty-fonts-advanced-row-action--unprotect' : 'tasty-fonts-advanced-row-action--protect'); ?> tasty-fonts-snapshot-icon-button" title="<?php echo esc_attr($exportProtected ? __('Unprotect export bundle', 'tasty-fonts') : __('Protect export bundle', 'tasty-fonts')); ?>" aria-label="<?php echo esc_attr($exportProtected ? __('Unprotect export bundle', 'tasty-fonts') : __('Protect export bundle', 'tasty-fonts')); ?>">
                                                                                            <span class="screen-reader-text"><?php echo esc_html($exportProtected ? __('Unprotect', 'tasty-fonts') : __('Protect', 'tasty-fonts')); ?></span>
                                                                                        </button>
                                                                                    </form>
                                                                                    <form
                                                                                        method="post"
                                                                                        class="tasty-fonts-snapshot-action-form"
                                                                                        data-developer-tool-form
                                                                                        data-developer-confirm-message="<?php echo esc_attr__('Delete this saved export bundle permanently?', 'tasty-fonts'); ?>"
                                                                                    >
                                                                                        <?php wp_nonce_field($siteTransferExportDeleteAction); ?>
                                                                                        <input type="hidden" name="<?php echo esc_attr($siteTransferExportDeleteAction); ?>" value="1">
                                                                                        <input type="hidden" name="tasty_fonts_export_bundle_id" value="<?php echo esc_attr($exportId); ?>">
                                                                                        <button type="submit" class="button button-secondary tasty-fonts-button-danger tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--delete tasty-fonts-snapshot-icon-button" title="<?php echo esc_attr($exportProtected ? __('Unprotect before deleting', 'tasty-fonts') : __('Delete export bundle', 'tasty-fonts')); ?>" aria-label="<?php echo esc_attr($exportProtected ? __('Unprotect before deleting', 'tasty-fonts') : __('Delete export bundle', 'tasty-fonts')); ?>" <?php disabled($exportProtected); ?>>
                                                                                            <span class="screen-reader-text"><?php esc_html_e('Delete', 'tasty-fonts'); ?></span>
                                                                                        </button>
                                                                                    </form>
                                                                                </div>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="tasty-fonts-snapshot-controls tasty-fonts-export-bundle-controls">
                                                                <form method="post" class="tasty-fonts-output-settings-form tasty-fonts-snapshot-retention-form">
                                                                    <?php wp_nonce_field('tasty_fonts_save_settings'); ?>
                                                                    <input type="hidden" name="tasty_fonts_save_settings" value="1">
                                                                    <label for="tasty-fonts-export-retention-limit" class="tasty-fonts-snapshot-retention-control">
                                                                        <span><?php esc_html_e('Keep latest', 'tasty-fonts'); ?></span>
                                                                        <input
                                                                            id="tasty-fonts-export-retention-limit"
                                                                            class="tasty-fonts-text-control tasty-fonts-snapshot-retention-input"
                                                                            type="number"
                                                                            name="site_transfer_export_retention_limit"
                                                                            value="<?php echo esc_attr((string) $exportRetentionLimit); ?>"
                                                                            min="<?php echo esc_attr((string) $exportRetentionMin); ?>"
                                                                            max="<?php echo esc_attr((string) $exportRetentionMax); ?>"
                                                                            step="1"
                                                                        >
                                                                        <span><?php esc_html_e('bundles', 'tasty-fonts'); ?></span>
                                                                    </label>
                                                                    <button type="submit" class="button button-secondary tasty-fonts-snapshot-retention-save" title="<?php esc_attr_e('Save export retention', 'tasty-fonts'); ?>">
                                                                        <?php esc_html_e('Save', 'tasty-fonts'); ?>
                                                                    </button>
                                                                </form>
                                                                <div class="tasty-fonts-health-row-actions tasty-fonts-export-bundle-primary-actions">
                                                                    <button
                                                                        type="button"
                                                                        class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger tasty-fonts-health-help-trigger"
                                                                        aria-label="<?php esc_attr_e('Explain export bundles', 'tasty-fonts'); ?>"
                                                                        <?php $renderSiteTransferHelpAttributes($siteTransferHelpCopy['export_bundle']); ?>
                                                                    >
                                                                        <?php esc_html_e('?', 'tasty-fonts'); ?>
                                                                    </button>
                                                                    <?php if ($siteTransferAvailable && $siteTransferExportUrl !== ''): ?>
                                                                        <a class="button tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--download tasty-fonts-site-transfer-button" href="<?php echo esc_url($siteTransferExportUrl); ?>">
                                                                            <?php esc_html_e('Export Bundle', 'tasty-fonts'); ?>
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <button type="button" class="button tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--download tasty-fonts-site-transfer-button" disabled>
                                                                            <?php esc_html_e('Export Bundle', 'tasty-fonts'); ?>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </article>
                                                    <form
                                                        id="tasty-fonts-site-transfer-form"
                                                        method="post"
                                                        enctype="multipart/form-data"
                                                        class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-site-transfer-form tasty-fonts-health-row tasty-fonts-transfer-row tasty-fonts-transfer-row--import"
                                                        data-developer-confirm-message="<?php echo esc_attr__('Replace the current Tasty Fonts settings, library, and managed files with the uploaded site transfer bundle?', 'tasty-fonts'); ?>"
                                                        data-site-transfer-form
                                                        data-site-transfer-max-bytes="<?php echo esc_attr((string) ($siteTransfer['effective_upload_limit_bytes'] ?? '')); ?>"
                                                        data-site-transfer-max-label="<?php echo esc_attr((string) ($siteTransfer['effective_upload_limit_label'] ?? '')); ?>"
                                                    >
                                                        <?php wp_nonce_field($siteTransferImportAction); ?>
                                                        <input type="hidden" name="<?php echo esc_attr($siteTransferImportAction); ?>" value="1">
                                                        <input type="hidden" name="<?php echo esc_attr($siteTransferImportStageTokenField); ?>" value="" data-site-transfer-stage-token>
                                                        <span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
                                                        <div class="tasty-fonts-health-row-copy">
                                                            <div class="tasty-fonts-health-row-title">
																<strong><?php esc_html_e('Import Bundle', 'tasty-fonts'); ?></strong>
                                                                <?php if ($showSettingsDescriptions): ?>
                                                                    <span><?php esc_html_e('Choose a transfer ZIP, validate the diff, then import only after the dry-run is clear.', 'tasty-fonts'); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if (!$siteTransferAvailable && $siteTransferMessage !== ''): ?>
                                                                <span class="tasty-fonts-site-transfer-note tasty-fonts-site-transfer-note--muted"><?php echo esc_html($siteTransferMessage); ?></span>
                                                            <?php endif; ?>
                                                            <div class="tasty-fonts-site-transfer-import-stack">
                                                                <div class="tasty-fonts-site-transfer-intake-panel">
                                                                    <div class="tasty-fonts-site-transfer-import-grid">
                                                                        <div class="tasty-fonts-site-transfer-field tasty-fonts-site-transfer-field--bundle">
                                                                            <div class="tasty-fonts-site-transfer-field-head">
                                                                                <span class="tasty-fonts-field-label"><?php esc_html_e('Bundle ZIP', 'tasty-fonts'); ?></span>
                                                                                <?php if ($showSettingsDescriptions): ?>
                                                                                    <span><?php esc_html_e('Required for dry run', 'tasty-fonts'); ?></span>
                                                                                <?php endif; ?>
                                                                            </div>
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
                                                                                    <span class="tasty-fonts-site-transfer-file-label"><?php esc_html_e('Selected bundle', 'tasty-fonts'); ?></span>
                                                                                    <span class="tasty-fonts-site-transfer-file-name" data-site-transfer-file-name><?php esc_html_e('No bundle selected', 'tasty-fonts'); ?></span>
                                                                                </div>
                                                                                <label for="tasty-fonts-advanced-site-transfer-bundle-upload" class="button tasty-fonts-site-transfer-picker-trigger<?php echo !$siteTransferAvailable ? ' is-disabled' : ''; ?>">
                                                                                    <?php esc_html_e('Choose File', 'tasty-fonts'); ?>
                                                                                </label>
                                                                            </div>
                                                                        </div>
                                                                        <label for="tasty-fonts-advanced-site-transfer-google-api-key" class="tasty-fonts-site-transfer-field tasty-fonts-site-transfer-field--secret">
                                                                            <span class="tasty-fonts-site-transfer-field-head">
                                                                                <span class="tasty-fonts-field-label"><?php esc_html_e('Google API Key', 'tasty-fonts'); ?></span>
                                                                                <?php if ($showSettingsDescriptions): ?>
                                                                                    <span><?php esc_html_e('Optional for Google font imports', 'tasty-fonts'); ?></span>
                                                                                <?php endif; ?>
                                                                            </span>
                                                                            <input
                                                                                type="text"
                                                                                id="tasty-fonts-advanced-site-transfer-google-api-key"
                                                                                class="regular-text tasty-fonts-text-control tasty-fonts-site-transfer-input"
                                                                                name="<?php echo esc_attr($siteTransferImportGoogleField); ?>"
                                                                                value=""
                                                                                placeholder="<?php echo $showSettingsDescriptions ? esc_attr__('Paste a Google Fonts API key if this bundle needs one', 'tasty-fonts') : ''; ?>"
                                                                                <?php disabled(!$siteTransferAvailable); ?>
                                                                            >
                                                                        </label>
                                                                    </div>
                                                                    <div class="tasty-fonts-site-transfer-summary-wrap">
                                                                        <div class="tasty-fonts-site-transfer-summary" aria-label="<?php esc_attr_e('Import readiness', 'tasty-fonts'); ?>">
                                                                            <div class="tasty-fonts-site-transfer-summary-item" data-state="neutral" aria-label="<?php esc_attr_e('Bundle: No bundle selected', 'tasty-fonts'); ?>"<?php echo $showSettingsDescriptions ? ' data-help-tooltip="' . esc_attr__('Bundle: No bundle selected', 'tasty-fonts') . '" data-help-passive="1"' : ''; ?>>
                                                                                <span class="tasty-fonts-site-transfer-summary-label"><?php esc_html_e('Bundle', 'tasty-fonts'); ?></span>
                                                                                <span class="tasty-fonts-site-transfer-summary-value" data-site-transfer-summary="bundle"><?php esc_html_e('No bundle selected', 'tasty-fonts'); ?></span>
                                                                            </div>
                                                                            <div class="tasty-fonts-site-transfer-summary-item" data-state="neutral" aria-label="<?php echo esc_attr($siteTransferUploadLimitLabel !== '' ? sprintf(__('Upload limit: %s Upload Limit', 'tasty-fonts'), $siteTransferUploadLimitLabel) : __('Upload limit: Upload Limit Available', 'tasty-fonts')); ?>"<?php echo $showSettingsDescriptions ? ' data-help-tooltip="' . esc_attr($siteTransferUploadLimitLabel !== '' ? sprintf(__('Upload limit: %s Upload Limit', 'tasty-fonts'), $siteTransferUploadLimitLabel) : __('Upload limit: Upload Limit Available', 'tasty-fonts')) . '" data-help-passive="1"' : ''; ?>>
	                                                                                <span class="tasty-fonts-site-transfer-summary-label"><?php esc_html_e('Upload limit', 'tasty-fonts'); ?></span>
                                                                                <span class="tasty-fonts-site-transfer-summary-value" data-site-transfer-summary="limit">
                                                                                    <?php
                                                                                    echo esc_html(
                                                                                        $siteTransferUploadLimitLabel !== ''
                                                                                            ? sprintf(__('%s Upload Limit', 'tasty-fonts'), $siteTransferUploadLimitLabel)
                                                                                            : __('Upload Limit Available', 'tasty-fonts')
                                                                                    );
                                                                                    ?>
                                                                                </span>
                                                                            </div>
                                                                            <div class="tasty-fonts-site-transfer-summary-item" data-state="neutral" aria-label="<?php esc_attr_e('Google API key: Optional', 'tasty-fonts'); ?>"<?php echo $showSettingsDescriptions ? ' data-help-tooltip="' . esc_attr__('Google API key: Optional', 'tasty-fonts') . '" data-help-passive="1"' : ''; ?>>
	                                                                                <span class="tasty-fonts-site-transfer-summary-label"><?php esc_html_e('Google API key', 'tasty-fonts'); ?></span>
                                                                                <span class="tasty-fonts-site-transfer-summary-value" data-site-transfer-summary="google"><?php esc_html_e('Optional', 'tasty-fonts'); ?></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="tasty-fonts-health-row-actions tasty-fonts-site-transfer-inline-actions">
                                                                        <button
                                                                            type="button"
                                                                            class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger tasty-fonts-health-help-trigger"
                                                                            aria-label="<?php esc_attr_e('Explain incoming bundle dry runs', 'tasty-fonts'); ?>"
                                                                            <?php $renderSiteTransferHelpAttributes($siteTransferHelpCopy['dry_run_bundle']); ?>
                                                                        >
                                                                            <?php esc_html_e('?', 'tasty-fonts'); ?>
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            class="button tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--validate tasty-fonts-site-transfer-button"
                                                                            data-site-transfer-validate-submit
                                                                            data-idle-label="<?php echo esc_attr__('Dry Run Bundle', 'tasty-fonts'); ?>"
                                                                            data-busy-label="<?php echo esc_attr__('Validating Bundle...', 'tasty-fonts'); ?>"
                                                                            disabled
                                                                        >
                                                                            <?php esc_html_e('Dry Run Bundle', 'tasty-fonts'); ?>
                                                                        </button>
                                                                        <button
                                                                            type="submit"
                                                                            class="button button-primary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--import tasty-fonts-site-transfer-button"
                                                                            data-site-transfer-import-submit
                                                                            data-idle-label="<?php echo esc_attr__('Import Bundle', 'tasty-fonts'); ?>"
                                                                            data-busy-label="<?php echo esc_attr__('Importing Bundle...', 'tasty-fonts'); ?>"
                                                                            disabled
                                                                        >
                                                                            <?php esc_html_e('Import Bundle', 'tasty-fonts'); ?>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <div class="tasty-fonts-site-transfer-summary-wrap">
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
                                                            <div class="tasty-fonts-import-status tasty-fonts-site-transfer-status" data-site-transfer-status></div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </section>
                                        </div>

                                        <div class="tasty-fonts-health-board tasty-fonts-transfer-recovery-board">
                                            <section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Snapshots and support', 'tasty-fonts'); ?>">
                                                <div class="tasty-fonts-health-group-head">
                                                    <h4><?php esc_html_e('Snapshots & Support', 'tasty-fonts'); ?></h4>
                                                    <span class="tasty-fonts-health-group-count"><?php esc_html_e('Recovery Tools', 'tasty-fonts'); ?></span>
                                                </div>
                                                <div class="tasty-fonts-health-list">
                                                    <article class="tasty-fonts-health-row tasty-fonts-transfer-row tasty-fonts-transfer-row--snapshot">
                                                        <span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
                                                        <div class="tasty-fonts-health-row-copy">
                                                            <div class="tasty-fonts-health-row-title">
                                                                <strong><?php esc_html_e('Rollback Snapshot', 'tasty-fonts'); ?></strong>
                                                                <?php if ($showSettingsDescriptions): ?>
                                                                    <span><?php esc_html_e('Create a local restore point before manual work. Destructive tools also create one automatically.', 'tasty-fonts'); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if ($rollbackSnapshots === []): ?>
                                                                <span class="tasty-fonts-transfer-muted-line"><?php esc_html_e('No rollback snapshots yet.', 'tasty-fonts'); ?></span>
                                                            <?php else: ?>
                                                                <div class="tasty-fonts-snapshot-list" aria-label="<?php esc_attr_e('Available rollback snapshots', 'tasty-fonts'); ?>">
                                                                    <?php foreach (array_slice($rollbackSnapshots, 0, $snapshotRetentionLimit) as $snapshot): ?>
                                                                        <?php
                                                                        $snapshotId = sanitize_key((string) ($snapshot['id'] ?? ''));
                                                                        $snapshotCreatedAt = trim((string) ($snapshot['created_at'] ?? ''));
                                                                        $snapshotReason = trim((string) ($snapshot['reason'] ?? 'manual'));
                                                                        $snapshotLabel = trim((string) ($snapshot['label'] ?? ''));
                                                                        $snapshotFamilyNames = is_array($snapshot['family_names'] ?? null) ? array_values(array_filter(array_map('strval', $snapshot['family_names']))) : [];
                                                                        $snapshotRoleFamilies = is_array($snapshot['role_families'] ?? null) ? array_values(array_filter(array_map('strval', $snapshot['role_families']))) : [];
                                                                        $snapshotFamilyCount = max(0, (int) ($snapshot['families'] ?? 0));
                                                                        $snapshotFontFileCount = max(0, (int) ($snapshot['font_files'] ?? 0));
                                                                        $snapshotStorageFileCount = max(0, (int) ($snapshot['storage_files'] ?? $snapshot['files'] ?? 0));
                                                                        $snapshotSize = max(0, (int) ($snapshot['size'] ?? 0));
                                                                        $snapshotVersion = trim((string) ($snapshot['plugin_version'] ?? ''));
                                                                        $snapshotDisplayName = $snapshotLabel !== '' ? $snapshotLabel : $snapshotReasonLabel($snapshotReason);
                                                                        $snapshotFamilyText = $snapshotFamilyNames !== []
                                                                            ? implode(', ', array_slice($snapshotFamilyNames, 0, 4))
                                                                            : __('No families captured', 'tasty-fonts');
                                                                        $snapshotRoleText = $snapshotRoleFamilies !== []
                                                                            ? implode(', ', array_slice($snapshotRoleFamilies, 0, 3))
                                                                            : __('No live roles', 'tasty-fonts');
                                                                        $snapshotMeta = [
                                                                            $snapshotReasonLabel($snapshotReason),
                                                                            $snapshotCreatedAt !== '' ? $snapshotCreatedAt : __('No timestamp', 'tasty-fonts'),
                                                                            sprintf(
                                                                                /* translators: %d: captured font file count */
                                                                                _n('%d font file', '%d font files', $snapshotFontFileCount, 'tasty-fonts'),
                                                                                $snapshotFontFileCount
                                                                            ),
                                                                            sprintf(
                                                                                /* translators: %d: captured storage file count */
                                                                                _n('%d storage file', '%d storage files', $snapshotStorageFileCount, 'tasty-fonts'),
                                                                                $snapshotStorageFileCount
                                                                            ),
																			sprintf(
																				/* translators: %d: font family count */
																				_n('%d font family', '%d font families', $snapshotFamilyCount, 'tasty-fonts'),
																				$snapshotFamilyCount
																			),
                                                                        ];
                                                                        if ($snapshotSize > 0) {
                                                                            $snapshotMeta[] = size_format($snapshotSize);
                                                                        }
                                                                        if ($snapshotVersion !== '') {
                                                                            $snapshotMeta[] = sprintf(
                                                                                /* translators: %s: plugin version */
                                                                                __('v%s', 'tasty-fonts'),
                                                                                $snapshotVersion
                                                                            );
                                                                        }
                                                                        ?>
                                                                        <?php if ($snapshotId !== ''): ?>
                                                                            <div class="tasty-fonts-snapshot-row" aria-describedby="<?php echo esc_attr('tasty-fonts-snapshot-details-' . $snapshotId); ?>">
                                                                                <span class="tasty-fonts-snapshot-row-copy">
                                                                                    <strong><?php echo esc_html($snapshotDisplayName); ?></strong>
                                                                                    <span class="tasty-fonts-snapshot-row-meta"><?php echo esc_html(implode(' · ', $snapshotMeta)); ?></span>
                                                                                    <span id="<?php echo esc_attr('tasty-fonts-snapshot-details-' . $snapshotId); ?>" class="tasty-fonts-snapshot-row-details" role="tooltip">
                                                                                        <span class="tasty-fonts-snapshot-row-detail">
                                                                                            <?php
                                                                                            echo esc_html(sprintf(
                                                                                                /* translators: %s: comma-separated font family names */
                                                                                                __('Families: %s', 'tasty-fonts'),
                                                                                                $snapshotFamilyText
                                                                                            ));
                                                                                            ?>
                                                                                        </span>
                                                                                        <span class="tasty-fonts-snapshot-row-detail">
                                                                                            <?php
                                                                                            echo esc_html(sprintf(
                                                                                                /* translators: %s: comma-separated live role family names */
                                                                                                __('Live roles: %s', 'tasty-fonts'),
                                                                                                $snapshotRoleText
                                                                                            ));
                                                                                            ?>
                                                                                        </span>
                                                                                    </span>
                                                                                </span>
                                                                                <div class="tasty-fonts-snapshot-row-actions">
                                                                                    <button
                                                                                        type="button"
                                                                                        class="button button-secondary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--rename tasty-fonts-snapshot-icon-button"
                                                                                        title="<?php esc_attr_e('Rename snapshot', 'tasty-fonts'); ?>"
                                                                                        aria-label="<?php esc_attr_e('Rename snapshot', 'tasty-fonts'); ?>"
                                                                                        aria-expanded="false"
                                                                                        aria-controls="<?php echo esc_attr('tasty-fonts-snapshot-rename-' . $snapshotId); ?>"
                                                                                        data-snapshot-rename-toggle
                                                                                    >
                                                                                        <span class="screen-reader-text"><?php esc_html_e('Rename', 'tasty-fonts'); ?></span>
                                                                                    </button>
                                                                                    <form
                                                                                        id="<?php echo esc_attr('tasty-fonts-snapshot-rename-' . $snapshotId); ?>"
                                                                                        method="post"
                                                                                        class="tasty-fonts-snapshot-rename-form"
                                                                                        data-snapshot-rename-form
                                                                                        hidden
                                                                                    >
                                                                                        <?php wp_nonce_field($snapshotRenameActionField); ?>
                                                                                        <input type="hidden" name="<?php echo esc_attr($snapshotRenameActionField); ?>" value="1">
                                                                                        <input type="hidden" name="tasty_fonts_snapshot_id" value="<?php echo esc_attr($snapshotId); ?>">
                                                                                        <label class="screen-reader-text" for="<?php echo esc_attr('tasty-fonts-snapshot-label-' . $snapshotId); ?>"><?php esc_html_e('Snapshot name', 'tasty-fonts'); ?></label>
                                                                                        <input
                                                                                            id="<?php echo esc_attr('tasty-fonts-snapshot-label-' . $snapshotId); ?>"
                                                                                            class="tasty-fonts-text-control tasty-fonts-snapshot-label-input"
                                                                                            type="text"
                                                                                            name="tasty_fonts_snapshot_label"
                                                                                            value="<?php echo esc_attr($snapshotLabel); ?>"
                                                                                            data-snapshot-rename-input
                                                                                            data-original-value="<?php echo esc_attr($snapshotLabel); ?>"
                                                                                            maxlength="80"
                                                                                            placeholder="<?php echo esc_attr($snapshotReasonLabel($snapshotReason)); ?>"
                                                                                        >
                                                                                        <button type="submit" class="button button-secondary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--save tasty-fonts-snapshot-icon-button" title="<?php esc_attr_e('Save snapshot name', 'tasty-fonts'); ?>" aria-label="<?php esc_attr_e('Save snapshot name', 'tasty-fonts'); ?>">
                                                                                            <span class="screen-reader-text"><?php esc_html_e('Save snapshot name', 'tasty-fonts'); ?></span>
                                                                                        </button>
                                                                                        <button type="button" class="button button-secondary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--cancel tasty-fonts-snapshot-icon-button" title="<?php esc_attr_e('Cancel rename', 'tasty-fonts'); ?>" aria-label="<?php esc_attr_e('Cancel rename', 'tasty-fonts'); ?>" data-snapshot-rename-cancel>
                                                                                            <span class="screen-reader-text"><?php esc_html_e('Cancel rename', 'tasty-fonts'); ?></span>
                                                                                        </button>
                                                                                    </form>
                                                                                    <form
                                                                                        method="post"
                                                                                        class="tasty-fonts-snapshot-action-form"
                                                                                        data-developer-tool-form
                                                                                        data-developer-confirm-message="<?php echo esc_attr__('Restore this rollback snapshot and replace the current Tasty Fonts settings, library, and managed files?', 'tasty-fonts'); ?>"
                                                                                    >
                                                                                        <?php wp_nonce_field($snapshotRestoreActionField); ?>
                                                                                        <input type="hidden" name="<?php echo esc_attr($snapshotRestoreActionField); ?>" value="1">
                                                                                        <input type="hidden" name="tasty_fonts_snapshot_id" value="<?php echo esc_attr($snapshotId); ?>">
                                                                                        <button type="submit" class="button button-secondary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--restore tasty-fonts-snapshot-icon-button" title="<?php esc_attr_e('Restore snapshot', 'tasty-fonts'); ?>" aria-label="<?php esc_attr_e('Restore snapshot', 'tasty-fonts'); ?>">
                                                                                            <span class="screen-reader-text"><?php esc_html_e('Restore', 'tasty-fonts'); ?></span>
                                                                                        </button>
                                                                                    </form>
                                                                                    <form
                                                                                        method="post"
                                                                                        class="tasty-fonts-snapshot-action-form"
                                                                                        data-developer-tool-form
                                                                                        data-developer-confirm-message="<?php echo esc_attr__('Delete this rollback snapshot permanently?', 'tasty-fonts'); ?>"
                                                                                    >
                                                                                        <?php wp_nonce_field($snapshotDeleteActionField); ?>
                                                                                        <input type="hidden" name="<?php echo esc_attr($snapshotDeleteActionField); ?>" value="1">
                                                                                        <input type="hidden" name="tasty_fonts_snapshot_id" value="<?php echo esc_attr($snapshotId); ?>">
                                                                                        <button type="submit" class="button button-secondary tasty-fonts-button-danger tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--delete tasty-fonts-snapshot-icon-button" title="<?php esc_attr_e('Delete snapshot', 'tasty-fonts'); ?>" aria-label="<?php esc_attr_e('Delete snapshot', 'tasty-fonts'); ?>">
                                                                                            <span class="screen-reader-text"><?php esc_html_e('Delete', 'tasty-fonts'); ?></span>
                                                                                        </button>
                                                                                    </form>
                                                                                </div>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="tasty-fonts-snapshot-controls tasty-fonts-snapshot-footer-controls">
                                                                <form method="post" class="tasty-fonts-output-settings-form tasty-fonts-snapshot-retention-form">
                                                                    <?php wp_nonce_field('tasty_fonts_save_settings'); ?>
                                                                    <input type="hidden" name="tasty_fonts_save_settings" value="1">
                                                                    <label for="tasty-fonts-snapshot-retention-limit" class="tasty-fonts-snapshot-retention-control">
                                                                        <span><?php esc_html_e('Keep latest', 'tasty-fonts'); ?></span>
                                                                        <input
                                                                            id="tasty-fonts-snapshot-retention-limit"
                                                                            class="tasty-fonts-text-control tasty-fonts-snapshot-retention-input"
                                                                            type="number"
                                                                            name="snapshot_retention_limit"
                                                                            value="<?php echo esc_attr((string) $snapshotRetentionLimit); ?>"
                                                                            min="<?php echo esc_attr((string) $snapshotRetentionMin); ?>"
                                                                            max="<?php echo esc_attr((string) $snapshotRetentionMax); ?>"
                                                                            step="1"
                                                                        >
                                                                        <span><?php esc_html_e('snapshots', 'tasty-fonts'); ?></span>
                                                                    </label>
                                                                    <button type="submit" class="button button-secondary tasty-fonts-snapshot-retention-save" title="<?php esc_attr_e('Save snapshot retention', 'tasty-fonts'); ?>">
                                                                        <?php esc_html_e('Save', 'tasty-fonts'); ?>
                                                                    </button>
                                                                </form>
                                                                <div class="tasty-fonts-health-row-actions tasty-fonts-snapshot-primary-actions">
                                                                    <button
                                                                        type="button"
                                                                        class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger tasty-fonts-health-help-trigger"
                                                                        aria-label="<?php esc_attr_e('Explain rollback snapshots', 'tasty-fonts'); ?>"
                                                                        <?php $renderSiteTransferHelpAttributes($siteTransferHelpCopy['rollback_snapshot']); ?>
                                                                    >
                                                                        <?php esc_html_e('?', 'tasty-fonts'); ?>
                                                                    </button>
                                                                    <form method="post" class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form">
                                                                        <?php wp_nonce_field($snapshotActionField); ?>
                                                                        <button type="submit" class="button tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--snapshot tasty-fonts-site-transfer-button" name="<?php echo esc_attr($snapshotActionField); ?>" value="1">
                                                                            <?php esc_html_e('Create Snapshot', 'tasty-fonts'); ?>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </article>
                                                    <article class="tasty-fonts-health-row tasty-fonts-transfer-row tasty-fonts-transfer-row--support">
                                                        <span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
                                                        <div class="tasty-fonts-health-row-copy">
                                                            <div class="tasty-fonts-health-row-title">
                                                                <strong><?php esc_html_e('Support Bundle', 'tasty-fonts'); ?></strong>
                                                                <?php if ($showSettingsDescriptions): ?>
                                                                    <span><?php esc_html_e('Downloads one sanitized ZIP for troubleshooting: diagnostics, runtime state, generated CSS, storage metadata, and activity.', 'tasty-fonts'); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="tasty-fonts-health-row-actions">
                                                            <button
                                                                type="button"
                                                                class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger tasty-fonts-health-help-trigger"
                                                                aria-label="<?php esc_attr_e('Explain support bundles', 'tasty-fonts'); ?>"
                                                                <?php $renderSiteTransferHelpAttributes($siteTransferHelpCopy['support_bundle']); ?>
                                                            >
                                                                <?php esc_html_e('?', 'tasty-fonts'); ?>
                                                            </button>
	                                                            <?php if ($supportBundleUrl !== ''): ?>
	                                                                <a class="button button-secondary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--support tasty-fonts-site-transfer-button" href="<?php echo esc_url($supportBundleUrl); ?>">
	                                                                    <?php esc_html_e('Download Support Bundle', 'tasty-fonts'); ?>
	                                                                </a>
	                                                            <?php else: ?>
	                                                                <button type="button" class="button button-secondary tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--support tasty-fonts-site-transfer-button" disabled>
	                                                                    <?php esc_html_e('Download Support Bundle', 'tasty-fonts'); ?>
	                                                                </button>
	                                                            <?php endif; ?>
                                                        </div>
                                                    </article>
                                                </div>
                                            </section>
                                        </div>

                                        <article class="tasty-fonts-health-board tasty-fonts-advanced-activity-board" data-log-filter-root>
                                            <section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Transfer activity', 'tasty-fonts'); ?>">
                                                <div class="tasty-fonts-health-group-head">
                                                    <h4><?php esc_html_e('Activity Log', 'tasty-fonts'); ?></h4>
                                                    <div class="tasty-fonts-activity-head-actions tasty-fonts-site-transfer-activity-head-actions">
                                                        <?php if (!$showActivityLog): ?>
                                                            <span class="tasty-fonts-health-group-count"><?php esc_html_e('Hidden', 'tasty-fonts'); ?></span>
                                                        <?php elseif ($siteTransferLogs === []): ?>
                                                            <span class="tasty-fonts-health-group-count"><?php esc_html_e('No Events Yet', 'tasty-fonts'); ?></span>
                                                        <?php else: ?>
                                                            <span class="tasty-fonts-health-group-count" data-log-count><?php echo esc_html(sprintf(_n('%d entry', '%d entries', count($siteTransferLogs), 'tasty-fonts'), count($siteTransferLogs))); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if (!$showActivityLog): ?>
                                                    <div class="tasty-fonts-health-list">
                                                        <article class="tasty-fonts-health-row tasty-fonts-health-row--reference">
                                                            <span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
                                                            <div class="tasty-fonts-health-row-copy">
                                                                <div class="tasty-fonts-health-row-title">
                                                                    <strong><?php esc_html_e('Activity Log Hidden', 'tasty-fonts'); ?></strong>
                                                                    <span><?php esc_html_e('Enable Show Activity Log in Settings -> Behavior to review the full event timeline here.', 'tasty-fonts'); ?></span>
                                                                </div>
                                                            </div>
                                                        </article>
                                                    </div>
                                                <?php elseif ($siteTransferLogs === []): ?>
                                                    <div class="tasty-fonts-health-list">
                                                        <article class="tasty-fonts-health-row tasty-fonts-health-row--reference">
                                                            <span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
                                                            <div class="tasty-fonts-health-row-copy">
                                                                <div class="tasty-fonts-health-row-title">
                                                                    <strong><?php esc_html_e('No transfer activity yet', 'tasty-fonts'); ?></strong>
                                                                    <?php if ($showSettingsDescriptions): ?>
                                                                        <span><?php esc_html_e('Exports, imports, snapshots, support bundles, and transfer recovery messages will appear here.', 'tasty-fonts'); ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <div class="tasty-fonts-health-row-actions">
                                                                <button
                                                                    type="button"
                                                                    class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger tasty-fonts-health-help-trigger"
                                                                    aria-label="<?php esc_attr_e('Explain transfer activity log', 'tasty-fonts'); ?>"
                                                                    <?php $renderSiteTransferHelpAttributes($siteTransferHelpCopy['activity_log']); ?>
                                                                >
                                                                    <?php esc_html_e('?', 'tasty-fonts'); ?>
                                                                </button>
                                                            </div>
                                                        </article>
                                                    </div>
                                                <?php else: ?>
                                                        <div class="tasty-fonts-activity-filterbar">
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
                                                        </div>
                                                        <div class="tasty-fonts-activity-shell">
                                                            <div id="tasty-fonts-advanced-transfer-log-empty-filtered" class="tasty-fonts-empty tasty-fonts-empty--panel tasty-fonts-activity-empty" data-log-empty-filtered hidden><?php esc_html_e('No transfer activity matches the current filters.', 'tasty-fonts'); ?></div>
                                                            <?php $this->renderLogList($siteTransferLogs, 'tasty-fonts-log-list', 5, 'transfer'); ?>
                                                        </div>
                                                    <?php endif; ?>
                                            </section>
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
                                <?php $logCount = count($logs); ?>
                                <article class="tasty-fonts-health-board tasty-fonts-advanced-activity-board" data-activity-filter-root>
                                    <section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Activity timeline', 'tasty-fonts'); ?>">
                                        <div class="tasty-fonts-health-group-head">
                                            <h4><?php esc_html_e('Activity Log', 'tasty-fonts'); ?></h4>
                                            <div class="tasty-fonts-activity-head-actions tasty-fonts-site-transfer-activity-head-actions">
                                                <?php if (!$showActivityLog): ?>
                                                    <span class="tasty-fonts-health-group-count"><?php esc_html_e('Hidden', 'tasty-fonts'); ?></span>
                                                <?php elseif ($logs === []): ?>
                                                    <span class="tasty-fonts-health-group-count"><?php esc_html_e('No Events Yet', 'tasty-fonts'); ?></span>
                                                <?php else: ?>
                                                    <span class="tasty-fonts-health-group-count" data-activity-count><?php echo esc_html(sprintf(_n('%d entry', '%d entries', $logCount, 'tasty-fonts'), $logCount)); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!$showActivityLog): ?>
                                            <div class="tasty-fonts-health-list">
                                                <article class="tasty-fonts-health-row tasty-fonts-health-row--reference">
                                                    <span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
                                                    <div class="tasty-fonts-health-row-copy">
                                                        <div class="tasty-fonts-health-row-title">
                                                            <strong><?php esc_html_e('Activity Log Hidden', 'tasty-fonts'); ?></strong>
                                                            <span><?php esc_html_e('Enable Show Activity Log in Settings -> Behavior to review the full event timeline here.', 'tasty-fonts'); ?></span>
                                                        </div>
                                                    </div>
                                                </article>
                                            </div>
                                        <?php elseif ($logs === []): ?>
                                            <div class="tasty-fonts-health-list">
                                                <article class="tasty-fonts-health-row tasty-fonts-health-row--reference">
                                                    <span class="tasty-fonts-health-row-marker" aria-hidden="true"></span>
                                                    <div class="tasty-fonts-health-row-copy">
                                                        <div class="tasty-fonts-health-row-title">
                                                            <strong><?php esc_html_e('No Activity Yet', 'tasty-fonts'); ?></strong>
                                                            <span><?php esc_html_e('Imports, deletes, scans, and CSS refreshes will appear here.', 'tasty-fonts'); ?></span>
                                                        </div>
                                                    </div>
                                                </article>
                                            </div>
                                        <?php else: ?>
                                            <div class="tasty-fonts-activity-filterbar">
                                                <div class="tasty-fonts-activity-toolbar" role="group" aria-label="<?php esc_attr_e('Activity filters', 'tasty-fonts'); ?>">
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
                                            <div class="tasty-fonts-activity-shell">
                                                <div id="tasty-fonts-advanced-activity-empty-filtered" class="tasty-fonts-empty tasty-fonts-empty--panel tasty-fonts-activity-empty" data-activity-empty-filtered hidden><?php esc_html_e('No activity matches the current filters.', 'tasty-fonts'); ?></div>
                                                <?php $this->renderLogList($logs, 'tasty-fonts-log-list', 5, 'activity'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </section>
                                </article>
                            </section>
                        </div>
                    </section>
