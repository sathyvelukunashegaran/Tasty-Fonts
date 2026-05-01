                    <?php
                    $showUploadVariableControls = !empty($variableFontsEnabled);
                    $googleFontImportsEnabled = !isset($googleFontImportsEnabled) || !empty($googleFontImportsEnabled);
                    $bunnyFontImportsEnabled = !isset($bunnyFontImportsEnabled) || !empty($bunnyFontImportsEnabled);
                    $adobeFontImportsEnabled = isset($adobeFontImportsEnabled) && !empty($adobeFontImportsEnabled);
                    $localFontUploadsEnabled = !isset($localFontUploadsEnabled) || !empty($localFontUploadsEnabled);
                    $customCssUrlImportsEnabled = !empty($customCssUrlImportsEnabled);
                    $fontImportWorkflowsSettingsUrl = add_query_arg(
                        [
                            'page' => 'tasty-custom-fonts',
                            'tf_page' => 'settings',
                            'tf_studio' => 'plugin-behavior',
                        ],
                        admin_url('admin.php')
                    );
                    ?>
                    <section class="tasty-fonts-card tasty-fonts-library-card" id="tasty-fonts-library" aria-labelledby="tasty-fonts-library-panel-title">
                        <div class="tasty-fonts-card-head tasty-fonts-card-head--library">
                            <h2 class="screen-reader-text" id="tasty-fonts-library-panel-title"><?php esc_html_e('Font Library', 'tasty-fonts'); ?></h2>
                            <div class="tasty-fonts-library-tools">
                                <div class="tasty-fonts-library-filterbar">
                                    <div class="tasty-fonts-search-field tasty-fonts-search-field--compact">
                                        <label class="screen-reader-text" for="tasty-fonts-library-search"><?php esc_html_e('Search library', 'tasty-fonts'); ?></label>
                                        <input
                                            type="search"
                                            id="tasty-fonts-library-search"
                                            class="regular-text tasty-fonts-text-control"
                                            placeholder="<?php esc_attr_e('Search library', 'tasty-fonts'); ?>"
                                            aria-label="<?php esc_attr_e('Search library', 'tasty-fonts'); ?>"
                                        >
                                    </div>
                                    <label class="screen-reader-text" for="tasty-fonts-library-source-filter"><?php esc_html_e('Filter fonts by source', 'tasty-fonts'); ?></label>
                                    <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable tasty-fonts-library-select">
                                        <select
                                            id="tasty-fonts-library-source-filter"
                                            data-library-source-filter
                                            aria-label="<?php esc_attr_e('Filter fonts by source', 'tasty-fonts'); ?>"
                                            title="<?php echo esc_attr__('Choose which font source to show in the library.', 'tasty-fonts'); ?>"
                                        >
                                            <option value="all"><?php esc_html_e('All', 'tasty-fonts'); ?></option>
                                            <option value="role_active"><?php esc_html_e('In Use', 'tasty-fonts'); ?></option>
                                            <option value="published"><?php esc_html_e('Published', 'tasty-fonts'); ?></option>
                                            <option value="library_only"><?php esc_html_e('In Library Only', 'tasty-fonts'); ?></option>
                                            <option value="same-origin"><?php esc_html_e('Self-hosted', 'tasty-fonts'); ?></option>
                                            <option value="url-import"><?php esc_html_e('URL Import', 'tasty-fonts'); ?></option>
                                            <option value="google-cdn"><?php esc_html_e('Google CDN', 'tasty-fonts'); ?></option>
                                            <option value="bunny-cdn"><?php esc_html_e('Bunny CDN', 'tasty-fonts'); ?></option>
                                            <option value="adobe-hosted"><?php esc_html_e('Adobe-hosted', 'tasty-fonts'); ?></option>
                                        </select>
                                        <?php $this->renderClearSelectButton(__('Clear source filter', 'tasty-fonts'), 'tasty-fonts-library-source-filter', 'all'); ?>
                                    </span>
                                    <label class="screen-reader-text" for="tasty-fonts-library-category-filter"><?php esc_html_e('Filter fonts by type', 'tasty-fonts'); ?></label>
                                    <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable tasty-fonts-library-select">
                                        <select
                                            id="tasty-fonts-library-category-filter"
                                            data-library-category-filter
                                            aria-label="<?php esc_attr_e('Filter fonts by type', 'tasty-fonts'); ?>"
                                            title="<?php echo esc_attr__('Choose which font type to show in the library.', 'tasty-fonts'); ?>"
                                        >
                                            <?php foreach ($libraryCategoryOptions as $option): ?>
                                                <option value="<?php echo esc_attr((string) ($option['value'] ?? '')); ?>"><?php echo esc_html((string) ($option['label'] ?? '')); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php $this->renderClearSelectButton(__('Clear type filter', 'tasty-fonts'), 'tasty-fonts-library-category-filter', 'all'); ?>
                                    </span>
                                </div>
                                <div class="tasty-fonts-actions tasty-fonts-actions--library">
                                    <form method="post">
                                        <?php wp_nonce_field('tasty_fonts_rescan_fonts'); ?>
                                        <button type="submit" class="button" name="tasty_fonts_rescan_fonts" value="1"><?php esc_html_e('Rescan', 'tasty-fonts'); ?></button>
                                    </form>
                                    <button
                                        type="button"
                                        id="tasty-fonts-add-font-panel-toggle"
                                        class="button button-primary tasty-fonts-disclosure-button tasty-fonts-disclosure-button--library"
                                        data-disclosure-toggle="tasty-fonts-add-font-panel"
                                        data-expanded-label="<?php echo esc_attr__('Add Family', 'tasty-fonts'); ?>"
                                        data-collapsed-label="<?php echo esc_attr__('Add Family', 'tasty-fonts'); ?>"
                                        aria-expanded="false"
                                        aria-controls="tasty-fonts-add-font-panel"
                                    >
                                        <?php esc_html_e('Add Family', 'tasty-fonts'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="tasty-fonts-add-font-panel" class="tasty-fonts-import-shell" hidden>
                            <div class="tasty-fonts-add-font-tabs tasty-fonts-tab-list" role="tablist" aria-label="<?php esc_attr_e('Add font source', 'tasty-fonts'); ?>" aria-orientation="horizontal">
                                <button type="button" class="button tasty-fonts-add-font-tab tasty-fonts-tab-button is-active" id="tasty-fonts-add-font-tab-google" data-tab-group="add-font" data-tab-target="google" aria-selected="true" tabindex="0" aria-controls="tasty-fonts-add-font-panel-google" role="tab"><?php esc_html_e('Google Fonts', 'tasty-fonts'); ?></button>
                                <button type="button" class="button tasty-fonts-add-font-tab tasty-fonts-tab-button" id="tasty-fonts-add-font-tab-bunny" data-tab-group="add-font" data-tab-target="bunny" aria-selected="false" tabindex="-1" aria-controls="tasty-fonts-add-font-panel-bunny" role="tab"><?php esc_html_e('Bunny Fonts', 'tasty-fonts'); ?></button>
                                <button type="button" class="button tasty-fonts-add-font-tab tasty-fonts-tab-button" id="tasty-fonts-add-font-tab-adobe" data-tab-group="add-font" data-tab-target="adobe" aria-selected="false" tabindex="-1" aria-controls="tasty-fonts-add-font-panel-adobe" role="tab"><?php esc_html_e('Adobe Fonts', 'tasty-fonts'); ?></button>
                                <button type="button" class="button tasty-fonts-add-font-tab tasty-fonts-tab-button" id="tasty-fonts-add-font-tab-upload" data-tab-group="add-font" data-tab-target="upload" aria-selected="false" tabindex="-1" aria-controls="tasty-fonts-add-font-panel-upload" role="tab"><?php esc_html_e('Upload Files', 'tasty-fonts'); ?></button>
                                <button type="button" class="button tasty-fonts-add-font-tab tasty-fonts-tab-button" id="tasty-fonts-add-font-tab-url" data-tab-group="add-font" data-tab-target="url" aria-selected="false" tabindex="-1" aria-controls="tasty-fonts-add-font-panel-url" role="tab"><?php esc_html_e('From URL', 'tasty-fonts'); ?></button>
                            </div>

                            <div class="tasty-fonts-add-font-panels">
                                <section class="tasty-fonts-add-font-panel is-active" id="tasty-fonts-add-font-panel-google" data-tab-group="add-font" data-tab-panel="google" role="tabpanel" aria-labelledby="tasty-fonts-add-font-tab-google">
                                    <div class="tasty-fonts-source-shell tasty-fonts-source-shell--google" data-source-state="<?php echo esc_attr($googleApiState); ?>">
                                        <?php
                                        $this->renderAddFontsSourceStatusCard(
                                            [
                                                'title' => __('Google Fonts', 'tasty-fonts'),
                                                'summary' => $googleAccessCopy,
                                                'enabled' => $googleFontImportsEnabled,
                                                'enabled_badge_label' => $googleStatusLabel,
                                                'enabled_badge_class' => $googleStatusClass,
                                                'card_class' => 'tasty-fonts-google-access',
                                                'enabled_actions' => static function () use ($googleAccessButtonLabel, $googleAccessExpanded): void {
                                                    ?>
                                                    <button
                                                        type="button"
                                                        class="button tasty-fonts-disclosure-button"
                                                        data-disclosure-toggle="tasty-fonts-google-access-panel"
                                                        data-expanded-label="<?php echo esc_attr($googleAccessButtonLabel); ?>"
                                                        data-collapsed-label="<?php echo esc_attr($googleAccessButtonLabel); ?>"
                                                        aria-expanded="<?php echo esc_attr($googleAccessExpanded ? 'true' : 'false'); ?>"
                                                        aria-controls="tasty-fonts-google-access-panel"
                                                    >
                                                        <?php echo esc_html($googleAccessButtonLabel); ?>
                                                    </button>
                                                    <?php
                                                },
                                                'body' => function (bool $enabled) use ($googleAccessExpanded, $googleApiSaved): void {
                                                    if (!$enabled) {
                                                        return;
                                                    }
                                                    ?>
                                                    <div id="tasty-fonts-google-access-panel" class="tasty-fonts-google-access-panel" <?php echo $googleAccessExpanded ? '' : 'hidden'; ?>>
                                                        <form method="post" class="tasty-fonts-google-access-form">
                                                        <?php wp_nonce_field('tasty_fonts_save_settings'); ?>
                                                        <input type="hidden" name="tasty_fonts_save_settings" value="1">
                                                        <input
                                                            type="text"
                                                            class="hidden"
                                                            name="tasty_fonts_google_access_username"
                                                            value="<?php echo esc_attr((string) wp_get_current_user()->user_login); ?>"
                                                            autocomplete="username"
                                                            tabindex="-1"
                                                            aria-hidden="true"
                                                        >
                                                        <div class="tasty-fonts-google-access-grid">
                                                            <label class="tasty-fonts-stack-field tasty-fonts-google-access-field">
                                                                <?php $this->renderFieldLabel(__('Google Fonts API Key', 'tasty-fonts')); ?>
                                                                <input
                                                                    type="password"
                                                                    class="regular-text tasty-fonts-text-control"
                                                                    name="google_api_key"
                                                                    value=""
                                                                    placeholder="<?php echo esc_attr($googleApiSaved ? __('Saved API key. Enter a new key to replace it.', 'tasty-fonts') : __('Paste your Google Fonts API key', 'tasty-fonts')); ?>"
                                                                    autocomplete="new-password"
                                                                    spellcheck="false"
                                                                >
                                                            </label>

                                                            <div class="tasty-fonts-google-access-footer">
                                                                <div class="tasty-fonts-settings-buttons">
                                                                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Key', 'tasty-fonts'); ?></button>
                                                                    <?php if ($googleApiSaved): ?>
                                                                        <button type="submit" class="button" name="tasty_fonts_clear_google_api_key" value="1"><?php esc_html_e('Remove Key', 'tasty-fonts'); ?></button>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="tasty-fonts-google-access-meta">
                                                                    <div class="tasty-fonts-access-note tasty-fonts-access-note--compact">
                                                                        <span class="tasty-fonts-access-note-label"><?php esc_html_e('Need an API key?', 'tasty-fonts'); ?></span>
                                                                        <a class="tasty-fonts-access-link" href="https://developers.google.com/fonts/docs/developer_api" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open Google API docs', 'tasty-fonts'); ?></a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        </form>
                                                    </div>
                                                    <?php
                                                },
                                            ]
                                        );
                                        ?>

                                        <?php if ($googleFontImportsEnabled): ?>
                                            <?php
                                            $this->renderHostedImportWorkflow(
                                            [
                                                'provider_key' => 'google',
                                                'workflow_class' => 'tasty-fonts-google-workflow',
                                                'search_label' => __('Search Google Fonts', 'tasty-fonts'),
                                                'search_input_id' => 'tasty-fonts-google-search',
                                                'search_placeholder' => $googleApiEnabled ? __('Search Google families', 'tasty-fonts') : __('Add an API key to search', 'tasty-fonts'),
                                                'search_disabled' => !$googleApiEnabled,
                                                'search_note_id' => 'tasty-fonts-google-search-note',
                                                'search_disabled_copy' => $googleSearchDisabledCopy,
                                                'results_id' => 'tasty-fonts-google-results',
                                                'import_panel_class' => 'tasty-fonts-source-card tasty-fonts-source-card--task tasty-fonts-import-panel tasty-fonts-import-panel--google tasty-fonts-workflow-step tasty-fonts-workflow-step--import',
                                                'import_step_title_id' => 'tasty-fonts-google-import-step-title',
                                                'manual_family_id' => 'tasty-fonts-manual-family',
                                                'manual_family_placeholder' => __('e.g. Inter', 'tasty-fonts'),
                                                'manual_variants_label_id' => 'tasty-fonts-google-manual-variants-label',
                                                'manual_variants_id' => 'tasty-fonts-manual-variants',
                                                'manual_variants_placeholder' => __('e.g. 400,700', 'tasty-fonts'),
                                                'format_fieldset_id' => 'tasty-fonts-google-delivery-choice',
                                                'format_choice_id' => 'tasty-fonts-google-format-choice',
                                                'delivery_input_name' => 'tasty_fonts_google_delivery_mode',
                                                'delivery_options' => [
                                                    ['value' => 'self_hosted', 'label' => __('Self-hosted', 'tasty-fonts')],
                                                    ['value' => 'cdn', 'label' => __('Use Google CDN', 'tasty-fonts')],
                                                ],
                                                'selected_family_id' => 'tasty-fonts-selected-family',
                                                'selected_family_default' => __('Choose or type a Google family.', 'tasty-fonts'),
                                                'selected_family_meta_id' => 'tasty-fonts-selected-family-meta',
                                                'selected_family_note_id' => 'tasty-fonts-selected-family-note',
                                                'selected_preview_id' => 'tasty-fonts-selected-family-preview',
                                                'selected_preview_default' => __('Choose a family to preview it.', 'tasty-fonts'),
                                                'selected_variants_label_id' => 'tasty-fonts-google-selected-variants-label',
                                                'selected_variants_note_id' => 'tasty-fonts-google-selected-variants-note',
                                                'selected_variants_note' => __('Use chips or type a comma-separated list.', 'tasty-fonts'),
                                                'selection_summary_id' => 'tasty-fonts-import-selection-summary',
                                                'variant_list_id' => 'tasty-fonts-google-variants',
                                                'import_status_id' => 'tasty-fonts-import-status',
                                                'size_estimate_id' => 'tasty-fonts-import-size-estimate',
                                                'submit_id' => 'tasty-fonts-import-submit',
                                            ]
                                            );
                                            ?>
                                        <?php else: ?>
                                            <section class="tasty-fonts-source-card tasty-fonts-source-card--task tasty-fonts-import-panel tasty-fonts-import-panel--google">
                                                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Font Import Workflow', 'tasty-fonts'); ?></span>
                                                    <h4><?php esc_html_e('Search and Import Google Fonts', 'tasty-fonts'); ?></h4>
                                                </div>
                                                <?php $this->renderAddFontsWorkflowDisabledNotice(__('Google Fonts', 'tasty-fonts'), $fontImportWorkflowsSettingsUrl); ?>
                                            </section>
                                        <?php endif; ?>
                                    </div>
                                </section>

                                <section class="tasty-fonts-add-font-panel" id="tasty-fonts-add-font-panel-bunny" data-tab-group="add-font" data-tab-panel="bunny" role="tabpanel" aria-labelledby="tasty-fonts-add-font-tab-bunny" hidden>
                                    <div class="tasty-fonts-source-shell tasty-fonts-source-shell--bunny">
                                        <?php
                                        $this->renderAddFontsSourceStatusCard(
                                            [
                                                'title' => __('Bunny Fonts', 'tasty-fonts'),
                                                'summary' => $variableFontsEnabled
                                                    ? __('Import from Bunny, then host the selected files locally or serve them from Bunny CDN. If you require variable fonts, use Google Fonts because Bunny\'s variable-font files are not optimized for variable font workflows.', 'tasty-fonts')
                                                    : __('Import from Bunny, then host the selected files locally or serve them from Bunny CDN.', 'tasty-fonts'),
                                                'enabled' => $bunnyFontImportsEnabled,
                                                'enabled_badge_label' => __('No Key Needed', 'tasty-fonts'),
                                                'enabled_badge_class' => 'is-success',
                                            ]
                                        );
                                        ?>

                                        <?php if ($bunnyFontImportsEnabled): ?>
                                            <?php
                                            $this->renderHostedImportWorkflow(
                                                [
                                                    'provider_key' => 'bunny',
                                                'workflow_class' => 'tasty-fonts-google-workflow',
                                                'search_label' => __('Search Bunny Fonts', 'tasty-fonts'),
                                                'search_input_id' => 'tasty-fonts-bunny-search',
                                                'search_placeholder' => __('Search Bunny families', 'tasty-fonts'),
                                                'results_id' => 'tasty-fonts-bunny-results',
                                                'import_panel_class' => 'tasty-fonts-source-card tasty-fonts-source-card--task tasty-fonts-import-panel tasty-fonts-import-panel--google tasty-fonts-import-panel--bunny tasty-fonts-workflow-step tasty-fonts-workflow-step--import',
                                                'import_step_title_id' => 'tasty-fonts-bunny-import-step-title',
                                                'manual_family_id' => 'tasty-fonts-bunny-family',
                                                'manual_family_placeholder' => __('e.g. Inter', 'tasty-fonts'),
                                                'manual_variants_label_id' => 'tasty-fonts-bunny-manual-variants-label',
                                                'manual_variants_id' => 'tasty-fonts-bunny-variants',
                                                'manual_variants_placeholder' => __('Optional, e.g. 400,700italic', 'tasty-fonts'),
                                                'format_fieldset_id' => 'tasty-fonts-bunny-delivery-choice',
                                                'format_choice_id' => 'tasty-fonts-bunny-format-choice',
                                                'delivery_input_name' => 'tasty_fonts_bunny_delivery_mode',
                                                'delivery_options' => [
                                                    ['value' => 'self_hosted', 'label' => __('Self-hosted', 'tasty-fonts')],
                                                    ['value' => 'cdn', 'label' => __('Use Bunny CDN', 'tasty-fonts')],
                                                ],
                                                'selected_family_id' => 'tasty-fonts-bunny-selected-family',
                                                'selected_family_default' => __('Choose or type a Bunny family.', 'tasty-fonts'),
                                                'selected_family_meta_id' => 'tasty-fonts-bunny-selected-family-meta',
                                                'selected_family_note_id' => 'tasty-fonts-bunny-selected-family-note',
                                                'selected_preview_id' => 'tasty-fonts-bunny-selected-family-preview',
                                                'selected_preview_default' => __('Choose a family to preview it.', 'tasty-fonts'),
                                                'selected_variants_label_id' => 'tasty-fonts-bunny-selected-variants-label',
                                                'selected_variants_note_id' => 'tasty-fonts-bunny-selected-variants-note',
                                                'selected_variants_note' => __('Use chips or type a comma-separated list.', 'tasty-fonts'),
                                                'selection_summary_id' => 'tasty-fonts-bunny-import-selection-summary',
                                                'variant_list_id' => 'tasty-fonts-bunny-variants-list',
                                                'import_status_id' => 'tasty-fonts-bunny-import-status',
                                                'size_estimate_id' => 'tasty-fonts-bunny-import-size-estimate',
                                                'submit_id' => 'tasty-fonts-bunny-import-submit',
                                            ]
                                            );
                                            ?>
                                        <?php else: ?>
                                            <section class="tasty-fonts-source-card tasty-fonts-source-card--task tasty-fonts-import-panel tasty-fonts-import-panel--bunny">
                                                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Font Import Workflow', 'tasty-fonts'); ?></span>
                                                    <h4><?php esc_html_e('Search and Import Bunny Fonts', 'tasty-fonts'); ?></h4>
                                                </div>
                                                <?php $this->renderAddFontsWorkflowDisabledNotice(__('Bunny Fonts', 'tasty-fonts'), $fontImportWorkflowsSettingsUrl); ?>
                                            </section>
                                        <?php endif; ?>
                                    </div>
                                </section>

                                <section class="tasty-fonts-add-font-panel" id="tasty-fonts-add-font-panel-adobe" data-tab-group="add-font" data-tab-panel="adobe" role="tabpanel" aria-labelledby="tasty-fonts-add-font-tab-adobe" hidden>
                                    <div class="tasty-fonts-source-shell tasty-fonts-source-shell--adobe" data-source-state="<?php echo esc_attr($adobeProjectState); ?>">
                                        <?php
                                        $this->renderAddFontsSourceStatusCard(
                                            [
                                                'title' => __('Adobe Fonts', 'tasty-fonts'),
                                                'summary' => $adobeAccessCopy,
                                                'enabled' => $adobeFontImportsEnabled,
                                                'enabled_badge_label' => $adobeStatusLabel,
                                                'enabled_badge_class' => $adobeStatusClass,
                                                'card_class' => 'tasty-fonts-adobe-access',
                                                'enabled_actions' => static function () use ($adobeAccessButtonLabel, $adobeAccessExpanded): void {
                                                    ?>
                                                    <button
                                                        type="button"
                                                        class="button tasty-fonts-disclosure-button"
                                                        data-disclosure-toggle="tasty-fonts-adobe-project-panel"
                                                        data-expanded-label="<?php echo esc_attr($adobeAccessButtonLabel); ?>"
                                                        data-collapsed-label="<?php echo esc_attr($adobeAccessButtonLabel); ?>"
                                                        aria-expanded="<?php echo esc_attr($adobeAccessExpanded ? 'true' : 'false'); ?>"
                                                        aria-controls="tasty-fonts-adobe-project-panel"
                                                    >
                                                        <?php echo esc_html($adobeAccessButtonLabel); ?>
                                                    </button>
                                                    <?php
                                                },
                                                'body' => function (bool $enabled) use ($adobeAccessExpanded, $adobeProjectId, $adobeProjectEnabled, $adobeProjectSaved, $adobeProjectLink): void {
                                                    if (!$enabled) {
                                                        return;
                                                    }
                                                    ?>
                                                    <div id="tasty-fonts-adobe-project-panel" class="tasty-fonts-google-access-panel" <?php echo $adobeAccessExpanded ? '' : 'hidden'; ?>>
                                                        <form method="post" class="tasty-fonts-google-access-form" data-adobe-project-form>
                                                        <?php wp_nonce_field('tasty_fonts_save_adobe_project'); ?>
                                                        <input type="hidden" name="tasty_fonts_save_adobe_project" value="1">
                                                        <div class="tasty-fonts-google-access-grid">
                                                            <label class="tasty-fonts-stack-field tasty-fonts-google-access-field">
                                                                <?php $this->renderFieldLabel(__('Adobe Fonts Project ID', 'tasty-fonts')); ?>
                                                                <input
                                                                    type="text"
                                                                    class="regular-text tasty-fonts-text-control"
                                                                    id="tasty-fonts-adobe-project-id"
                                                                    name="adobe_project_id"
                                                                    value="<?php echo esc_attr($adobeProjectId); ?>"
                                                                    placeholder="<?php esc_attr_e('Example: abc1def', 'tasty-fonts'); ?>"
                                                                    spellcheck="false"
                                                                >
                                                            </label>

                                                            <label class="tasty-fonts-inline-checkbox">
                                                                <input type="checkbox" name="adobe_enabled" value="1" <?php checked($adobeProjectEnabled); ?>>
                                                                <span><?php esc_html_e('Load this Adobe stylesheet across the site, editors, and Etch.', 'tasty-fonts'); ?></span>
                                                            </label>

                                                            <div class="tasty-fonts-google-access-footer">
                                                                <div class="tasty-fonts-settings-buttons">
                                                                    <button type="submit" class="button button-primary" data-adobe-project-action="save"><?php esc_html_e('Save Project', 'tasty-fonts'); ?></button>
                                                                    <button type="submit" class="button" name="tasty_fonts_resync_adobe_project" value="1" data-adobe-project-action="resync" <?php echo $adobeProjectSaved ? '' : 'hidden disabled'; ?>><?php esc_html_e('Resync Project', 'tasty-fonts'); ?></button>
                                                                    <button type="submit" class="button tasty-fonts-button-danger" name="tasty_fonts_remove_adobe_project" value="1" data-adobe-project-action="remove" <?php echo $adobeProjectSaved ? '' : 'hidden disabled'; ?>><?php esc_html_e('Remove Project', 'tasty-fonts'); ?></button>
                                                                </div>
                                                                <div class="tasty-fonts-google-access-meta">
                                                                    <div class="tasty-fonts-access-note tasty-fonts-access-note--external">
                                                                        <span class="tasty-fonts-access-note-label"><?php esc_html_e('Managed in Adobe Fonts', 'tasty-fonts'); ?></span>
                                                                        <p class="tasty-fonts-muted"><?php esc_html_e('Manage domains and enabled families in Adobe, then resync here.', 'tasty-fonts'); ?></p>
                                                                        <a class="tasty-fonts-access-link" href="<?php echo esc_url($adobeProjectLink); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open Adobe projects', 'tasty-fonts'); ?></a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="tasty-fonts-inline-feedback" data-adobe-project-feedback hidden aria-live="polite" aria-atomic="true"></div>
                                                        </div>
                                                        </form>
                                                    </div>
                                                    <?php
                                                },
                                            ]
                                        );
                                        ?>

                                        <section class="tasty-fonts-source-card tasty-fonts-source-card--secondary tasty-fonts-import-panel tasty-fonts-import-panel--detected">
                                            <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                <span class="tasty-fonts-panel-kicker"><?php echo esc_html($adobeFontImportsEnabled ? __('From the Project', 'tasty-fonts') : __('Font Import Workflow', 'tasty-fonts')); ?></span>
                                                <h4><?php echo esc_html($adobeFontImportsEnabled ? __('Detected Families', 'tasty-fonts') : __('Connect Adobe Fonts Project', 'tasty-fonts')); ?></h4>
                                            </div>

                                            <?php if (!$adobeFontImportsEnabled): ?>
                                                <?php $this->renderAddFontsWorkflowDisabledNotice(__('Adobe Fonts', 'tasty-fonts'), $fontImportWorkflowsSettingsUrl); ?>
                                            <?php elseif ($adobeDetectedFamilies === []): ?>
                                                <div class="tasty-fonts-empty tasty-fonts-empty--panel"><?php esc_html_e('No Adobe families detected yet.', 'tasty-fonts'); ?></div>
                                            <?php else: ?>
                                                <div class="tasty-fonts-adobe-family-list">
                                                    <?php foreach ($adobeDetectedFamilies as $family): ?>
                                                        <?php $familyCardRenderer->renderAdobeFamilyCard($family); ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </section>
                                    </div>
                                </section>

                                <section class="tasty-fonts-add-font-panel" id="tasty-fonts-add-font-panel-url" data-tab-group="add-font" data-tab-panel="url" role="tabpanel" aria-labelledby="tasty-fonts-add-font-tab-url" hidden>
                                    <div class="tasty-fonts-source-shell tasty-fonts-source-shell--url">
                                        <?php
                                        $this->renderAddFontsSourceStatusCard(
                                            [
                                                'title' => __('From URL', 'tasty-fonts'),
                                                'summary' => __('Inspect a public HTTPS CSS stylesheet that contains @font-face rules.', 'tasty-fonts'),
                                                'enabled' => $customCssUrlImportsEnabled,
                                                'enabled_badge_label' => __('Dry Run Only', 'tasty-fonts'),
                                            ]
                                        );
                                        ?>

                                        <section class="tasty-fonts-source-card tasty-fonts-source-card--task tasty-fonts-import-panel tasty-fonts-import-panel--url">
                                            <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                <span class="tasty-fonts-panel-kicker"><?php echo esc_html($customCssUrlImportsEnabled ? __('Review First', 'tasty-fonts') : __('Font Import Workflow', 'tasty-fonts')); ?></span>
                                                <h4><?php esc_html_e('Inspect a CSS Stylesheet', 'tasty-fonts'); ?></h4>
                                            </div>

                                            <?php if ($customCssUrlImportsEnabled): ?>
                                                <form id="tasty-fonts-url-dry-run-form" class="tasty-fonts-url-dry-run-form" novalidate>
                                                    <label class="tasty-fonts-stack-field" for="tasty-fonts-url-dry-run-input">
                                                        <?php $this->renderFieldLabel(__('CSS Stylesheet URL', 'tasty-fonts')); ?>
                                                        <input
                                                            type="url"
                                                            id="tasty-fonts-url-dry-run-input"
                                                            class="regular-text tasty-fonts-text-control"
                                                            placeholder="<?php esc_attr_e('https://example.com/fonts.css', 'tasty-fonts'); ?>"
                                                            autocomplete="url"
                                                            spellcheck="false"
                                                            aria-describedby="tasty-fonts-url-dry-run-help tasty-fonts-url-dry-run-status"
                                                            required
                                                        >
                                                    </label>
                                                    <p id="tasty-fonts-url-dry-run-help" class="tasty-fonts-muted"><?php esc_html_e('The dry run reads CSS and shows supported WOFF or WOFF2 faces. It does not save library data or write font files.', 'tasty-fonts'); ?></p>
                                                    <div class="tasty-fonts-upload-actions">
                                                        <button type="submit" class="button button-primary" id="tasty-fonts-url-dry-run-submit"><?php esc_html_e('Run Dry Run', 'tasty-fonts'); ?></button>
                                                    </div>
                                                    <div id="tasty-fonts-url-dry-run-status" class="tasty-fonts-import-status" aria-live="polite" aria-atomic="true"></div>
                                                </form>

                                                <div id="tasty-fonts-url-dry-run-review" class="tasty-fonts-url-dry-run-review" aria-live="polite" aria-atomic="false" hidden></div>
                                            <?php else: ?>
                                                <?php
                                                $this->renderAddFontsWorkflowDisabledNotice(
                                                    __('From URL', 'tasty-fonts'),
                                                    $fontImportWorkflowsSettingsUrl,
                                                    __('From URL is an expert import workflow. It is disabled until Enable URL Imports is turned on in Settings > Behavior.', 'tasty-fonts')
                                                );
                                                ?>
                                            <?php endif; ?>
                                        </section>
                                    </div>
                                </section>

                                <section class="tasty-fonts-add-font-panel" id="tasty-fonts-add-font-panel-upload" data-tab-group="add-font" data-tab-panel="upload" role="tabpanel" aria-labelledby="tasty-fonts-add-font-tab-upload" hidden>
                                    <div class="tasty-fonts-source-shell tasty-fonts-source-shell--upload">
                                        <?php
                                        $this->renderAddFontsSourceStatusCard(
                                            [
                                                'title' => __('Upload Files', 'tasty-fonts'),
                                                'summary' => $localFontUploadsEnabled
                                                    ? (
                                                        $showUploadVariableControls
                                                            ? __('Upload one typeface per family and keep its faces together. Clear filenames can prefill family, weight, and style. Variable uploads can include axis ranges and defaults.', 'tasty-fonts')
                                                            : __('Upload one typeface per family and keep its faces together. Clear filenames can prefill family, weight, and style. Enable variable fonts in Settings to configure axes.', 'tasty-fonts')
                                                    )
                                                    : __('Upload one typeface per family and keep its faces together.', 'tasty-fonts'),
                                                'enabled' => $localFontUploadsEnabled,
                                                'enabled_badge_label' => __('Auto-detect', 'tasty-fonts'),
                                            ]
                                        );
                                        ?>

                                        <section class="tasty-fonts-source-card tasty-fonts-source-card--task tasty-fonts-upload-builder">
                                            <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Family Builder', 'tasty-fonts'); ?></span>
                                                <h4><?php esc_html_e('Assemble Local Files', 'tasty-fonts'); ?></h4>
                                            </div>

                                            <?php if ($localFontUploadsEnabled): ?>
                                                <form id="tasty-fonts-upload-form" class="tasty-fonts-upload-form tasty-fonts-upload-form--builder" novalidate>
                                                    <div id="tasty-fonts-upload-groups" class="tasty-fonts-upload-groups">
                                                    <?php $this->renderUploadBuilderGroup($showUploadVariableControls); ?>
                                                </div>

                                                <template id="tasty-fonts-upload-group-template">
                                                    <?php $this->renderUploadBuilderGroup($showUploadVariableControls); ?>
                                                </template>

                                                <template id="tasty-fonts-upload-row-template">
                                                    <?php $this->renderUploadBuilderRow($showUploadVariableControls); ?>
                                                </template>

                                                <div class="tasty-fonts-upload-actions">
                                                    <div class="tasty-fonts-actions tasty-fonts-actions--upload-builder">
                                                        <button type="button" class="button" id="tasty-fonts-upload-add-family"><?php esc_html_e('Add Another Family', 'tasty-fonts'); ?></button>
                                                    </div>
                                                    <button type="submit" class="button button-primary" id="tasty-fonts-upload-submit"><?php esc_html_e('Upload to Library', 'tasty-fonts'); ?></button>
                                                </div>

                                                <div id="tasty-fonts-upload-status" class="tasty-fonts-import-status" aria-live="polite" aria-atomic="true"></div>
                                                </form>
                                            <?php else: ?>
                                                <?php $this->renderAddFontsWorkflowDisabledNotice(__('Custom Uploads', 'tasty-fonts'), $fontImportWorkflowsSettingsUrl); ?>
                                            <?php endif; ?>
                                        </section>
                                    </div>
                                </section>
                            </div>
                        </div>

                        <?php if ($catalog === []): ?>
                            <?php
                            $this->renderRichEmptyState(
                                __('No Fonts Yet', 'tasty-fonts'),
                                __('Add a hosted family, connect Adobe Fonts, or upload local files to start assigning roles.', 'tasty-fonts'),
                                [
                                    'class' => 'tasty-fonts-empty-state--library',
                                    'actions' => static function (): void {
                                        ?>
                                        <button type="button" class="button button-primary" data-open-add-fonts aria-controls="tasty-fonts-add-font-panel"><?php esc_html_e('Add Family', 'tasty-fonts'); ?></button>
                                        <?php
                                    },
                                ]
                            );
                            ?>
                            <div id="tasty-fonts-library-empty-filtered" class="tasty-fonts-empty tasty-fonts-empty-state" hidden><?php esc_html_e('No fonts match the current filters.', 'tasty-fonts'); ?></div>
                            <div class="tasty-fonts-library-grid" data-font-library-list></div>
                        <?php else: ?>
                            <div id="tasty-fonts-library-empty-filtered" class="tasty-fonts-empty tasty-fonts-empty-state" hidden><?php esc_html_e('No fonts match the current filters.', 'tasty-fonts'); ?></div>
                            <div class="tasty-fonts-library-grid" data-font-library-list>
                                <?php foreach ($catalog as $family): ?>
                                    <?php $familyCardRenderer->renderFamilySummaryRow($family, $roles, $familyFallbacks, $familyFontDisplays, $familyFontDisplayOptions, $previewText, $categoryAliasOwners, $extendedVariableOptions, $monospaceRoleEnabled, $classOutputOptions, $libraryRoleUsageRoles, $globalFallbackSettings); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
