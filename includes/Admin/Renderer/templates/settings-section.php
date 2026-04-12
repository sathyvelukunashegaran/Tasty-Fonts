                    <section class="tasty-fonts-card tasty-fonts-settings-card" id="tasty-fonts-settings-page">
                        <div class="tasty-fonts-card-head tasty-fonts-card-head--activity">
                            <?php
                            $this->renderSectionHeading(
                                'h2',
                                __('Settings', 'tasty-fonts')
                            );
                            ?>
                            <div class="tasty-fonts-settings-head-actions">
                                <div class="tasty-fonts-studio-switcher tasty-fonts-tab-list" role="tablist" aria-label="<?php esc_attr_e('Settings sections', 'tasty-fonts'); ?>" aria-orientation="horizontal">
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button is-active"
                                        id="tasty-fonts-settings-tab-output-settings"
                                        data-tab-group="settings"
                                        data-tab-target="output-settings"
                                        aria-selected="true"
                                        tabindex="0"
                                        aria-controls="tasty-fonts-settings-panel-output-settings"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Output', 'tasty-fonts'); ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button"
                                        id="tasty-fonts-settings-tab-integrations"
                                        data-tab-group="settings"
                                        data-tab-target="integrations"
                                        aria-selected="false"
                                        tabindex="-1"
                                        aria-controls="tasty-fonts-settings-panel-integrations"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Integrations', 'tasty-fonts'); ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button"
                                        id="tasty-fonts-settings-tab-plugin-behavior"
                                        data-tab-group="settings"
                                        data-tab-target="plugin-behavior"
                                        aria-selected="false"
                                        tabindex="-1"
                                        aria-controls="tasty-fonts-settings-panel-plugin-behavior"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Behavior', 'tasty-fonts'); ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button"
                                        id="tasty-fonts-settings-tab-transfer"
                                        data-tab-group="settings"
                                        data-tab-target="transfer"
                                        aria-selected="false"
                                        tabindex="-1"
                                        aria-controls="tasty-fonts-settings-panel-transfer"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Transfer', 'tasty-fonts'); ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button"
                                        id="tasty-fonts-settings-tab-developer"
                                        data-tab-group="settings"
                                        data-tab-target="developer"
                                        aria-selected="false"
                                        tabindex="-1"
                                        aria-controls="tasty-fonts-settings-panel-developer"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Developer', 'tasty-fonts'); ?>
                                    </button>
                                </div>
                                <div class="tasty-fonts-settings-save-shell" data-settings-save-shell>
                                    <button type="submit" class="button tasty-fonts-tab-button tasty-fonts-settings-save-button" form="tasty-fonts-settings-form" data-settings-save-button disabled><?php esc_html_e('Save changes', 'tasty-fonts'); ?></button>
                                </div>
                            </div>
                        </div>

                        <?php $showSettingsDescriptions = !$trainingWheelsOff; ?>
                        <?php $updateChannel = isset($updateChannel) ? (string) $updateChannel : 'stable'; ?>
                        <?php $updateChannelOptions = isset($updateChannelOptions) && is_array($updateChannelOptions) ? $updateChannelOptions : []; ?>
                        <?php $updateChannelStatus = isset($updateChannelStatus) && is_array($updateChannelStatus) ? $updateChannelStatus : []; ?>

                        <form method="post" id="tasty-fonts-settings-form" class="tasty-fonts-settings-form" data-settings-form="settings">
                            <?php wp_nonce_field('tasty_fonts_save_settings'); ?>
                            <input type="hidden" name="tasty_fonts_save_settings" value="1">
                        <section
                            id="tasty-fonts-settings-panel-output-settings"
                            class="tasty-fonts-studio-panel is-active"
                            data-tab-group="settings"
                            data-tab-panel="output-settings"
                            role="tabpanel"
                            aria-labelledby="tasty-fonts-settings-tab-output-settings"
                        >
                            <div class="tasty-fonts-output-settings-panel tasty-fonts-developer-panel">
                                <div class="tasty-fonts-output-settings-form">
                                    <div class="tasty-fonts-output-settings-list">
                                            <div class="tasty-fonts-output-settings-quick tasty-fonts-output-settings-choice">
                                                <div class="tasty-fonts-output-settings-submenu-copy">
                                                    <h4><?php esc_html_e('CSS Delivery', 'tasty-fonts'); ?></h4>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <p><?php esc_html_e('Choose whether the generated stylesheet loads as a file or is printed inline in the page head.', 'tasty-fonts'); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="tasty-fonts-output-quick-options" role="radiogroup" aria-label="<?php esc_attr_e('CSS delivery', 'tasty-fonts'); ?>">
                                                    <?php foreach ($cssDeliveryModeOptions as $option): ?>
                                                        <?php $optionValue = (string) ($option['value'] ?? ''); ?>
                                                        <label class="tasty-fonts-output-quick-option<?php echo $cssDeliveryMode === $optionValue ? ' is-active' : ''; ?>" data-pill-option>
                                                            <input
                                                                type="radio"
                                                                id="tasty-fonts-css-delivery-mode-<?php echo esc_attr($optionValue); ?>"
                                                                name="css_delivery_mode"
                                                                value="<?php echo esc_attr($optionValue); ?>"
                                                                data-pill-option-input
                                                                <?php checked($cssDeliveryMode, $optionValue); ?>
                                                            >
                                                            <span><?php echo esc_html((string) ($option['label'] ?? '')); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="tasty-fonts-output-settings-quick tasty-fonts-output-settings-choice">
                                                <div class="tasty-fonts-output-settings-submenu-copy">
                                                    <h4><?php esc_html_e('Default Font Display', 'tasty-fonts'); ?></h4>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <p><?php esc_html_e('Default font-display value for generated @font-face rules.', 'tasty-fonts'); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="tasty-fonts-output-quick-options" role="radiogroup" aria-label="<?php esc_attr_e('Default font display', 'tasty-fonts'); ?>">
                                                    <?php foreach ($fontDisplayOptions as $option): ?>
                                                        <?php $optionValue = (string) ($option['value'] ?? ''); ?>
                                                        <label class="tasty-fonts-output-quick-option<?php echo $fontDisplay === $optionValue ? ' is-active' : ''; ?>" data-pill-option>
                                                            <input
                                                                type="radio"
                                                                id="tasty-fonts-font-display-<?php echo esc_attr($optionValue); ?>"
                                                                name="font_display"
                                                                value="<?php echo esc_attr($optionValue); ?>"
                                                                data-pill-option-input
                                                                <?php checked($fontDisplay, $optionValue); ?>
                                                            >
                                                            <span><?php echo esc_html((string) ($option['label'] ?? '')); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="tasty-fonts-output-settings-quick tasty-fonts-output-settings-choice">
                                                <div class="tasty-fonts-output-settings-submenu-copy">
                                                    <h4><?php esc_html_e('Unicode Range Output', 'tasty-fonts'); ?></h4>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <p><?php esc_html_e('Controls how unicode-range is emitted in generated output. This affects emitted CSS and editor payloads only, not the stored library metadata.', 'tasty-fonts'); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="tasty-fonts-output-quick-options" role="radiogroup" aria-label="<?php esc_attr_e('Unicode range output', 'tasty-fonts'); ?>">
                                                    <?php foreach ($unicodeRangeModeOptions as $option): ?>
                                                        <?php $optionValue = (string) ($option['value'] ?? ''); ?>
                                                        <label class="tasty-fonts-output-quick-option<?php echo $unicodeRangeMode === $optionValue ? ' is-active' : ''; ?>" data-pill-option>
                                                            <input
                                                                type="radio"
                                                                id="tasty-fonts-unicode-range-mode-<?php echo esc_attr($optionValue); ?>"
                                                                name="unicode_range_mode"
                                                                value="<?php echo esc_attr($optionValue); ?>"
                                                                data-pill-option-input
                                                                data-unicode-range-mode
                                                                <?php checked($unicodeRangeMode, $optionValue); ?>
                                                            >
                                                            <span><?php echo esc_html((string) ($option['label'] ?? '')); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="tasty-fonts-output-settings-text-field" data-unicode-range-custom-wrap <?php echo $unicodeRangeCustomVisible ? '' : 'hidden'; ?>>
                                                <label class="tasty-fonts-output-settings-text-label" for="tasty-fonts-unicode-range-custom-value">
                                                    <span class="tasty-fonts-output-settings-text-title"><?php esc_html_e('Custom Unicode Range', 'tasty-fonts'); ?></span>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <span class="tasty-fonts-output-settings-text-description"><?php esc_html_e('Use a comma-separated list of U+XXXX, U+XXXX-YYYY, or U+XX? tokens.', 'tasty-fonts'); ?></span>
                                                    <?php endif; ?>
                                                </label>
                                                <textarea
                                                    id="tasty-fonts-unicode-range-custom-value"
                                                    name="unicode_range_custom_value"
                                                    rows="3"
                                                    class="tasty-fonts-text-control tasty-fonts-output-settings-textarea"
                                                    data-unicode-range-custom
                                                ><?php echo esc_textarea($unicodeRangeCustomValue); ?></textarea>
                                            </div>
                                            <input type="hidden" name="minify_css_output" value="0">
                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                                <input
                                                    type="checkbox"
                                                    class="tasty-fonts-toggle-input"
                                                    name="minify_css_output"
                                                    value="1"
                                                    <?php checked($minifyCssOutput); ?>
                                                >
                                                <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                <span class="tasty-fonts-toggle-copy">
                                                    <span class="tasty-fonts-toggle-title"><?php esc_html_e('Minify Generated CSS', 'tasty-fonts'); ?></span>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Keep on unless you need readable CSS while debugging.', 'tasty-fonts'); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                            <input type="hidden" name="preload_primary_fonts" value="0">
                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                                <input
                                                    type="checkbox"
                                                    class="tasty-fonts-toggle-input"
                                                    name="preload_primary_fonts"
                                                    value="1"
                                                    <?php checked($preloadPrimaryFonts); ?>
                                                >
                                                <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                <span class="tasty-fonts-toggle-copy">
                                                    <span class="tasty-fonts-toggle-title"><?php esc_html_e('Preload Primary Heading and Body Fonts', 'tasty-fonts'); ?></span>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Loads the active self-hosted heading and body fonts earlier for faster text rendering.', 'tasty-fonts'); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                            <input type="hidden" name="remote_connection_hints" value="0">
                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                                <input
                                                    type="checkbox"
                                                    class="tasty-fonts-toggle-input"
                                                    name="remote_connection_hints"
                                                    value="1"
                                                    <?php checked($remoteConnectionHints); ?>
                                                >
                                                <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                <span class="tasty-fonts-toggle-copy">
                                                    <span class="tasty-fonts-toggle-title"><?php esc_html_e('Remote Connection Hints', 'tasty-fonts'); ?></span>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Adds preconnect hints for live Google, Bunny, and Adobe deliveries.', 'tasty-fonts'); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                        <div class="tasty-fonts-output-settings-section tasty-fonts-output-settings-section--advanced">
                                                <div class="tasty-fonts-output-settings-quick tasty-fonts-output-settings-choice tasty-fonts-output-settings-choice--advanced" data-output-quick-mode-wrap>
                                                <div class="tasty-fonts-output-settings-submenu-copy">
                                                    <h4><?php esc_html_e('Output Preset', 'tasty-fonts'); ?></h4>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <p><?php echo esc_html($monospaceRoleEnabled
                                                            ? __('Use a preset for the common cases. Minimal emits the core role variables, including --font-monospace when the monospace role is enabled. Choose custom to open the detailed controls below with both outputs enabled by default.', 'tasty-fonts')
                                                            : __('Use a preset for the common cases. Minimal emits the core role variables. Choose custom to open the detailed controls below with both outputs enabled by default.', 'tasty-fonts')); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <input type="hidden" name="minimal_output_preset_enabled" value="<?php echo $minimalOutputPresetEnabled ? '1' : '0'; ?>" data-output-minimal-preset>
                                                <input
                                                    type="hidden"
                                                    name="output_quick_mode_preference"
                                                    value="<?php echo esc_attr($outputQuickMode); ?>"
                                                    data-output-quick-mode-preference
                                                    data-output-quick-mode-saved-preference="<?php echo esc_attr($outputQuickModePreference); ?>"
                                                >
                                                <div class="tasty-fonts-output-quick-options" role="radiogroup" aria-label="<?php esc_attr_e('Output quick mode', 'tasty-fonts'); ?>">
                                                    <?php foreach ([
                                                        'minimal' => __('Minimal', 'tasty-fonts'),
                                                        'variables' => __('Variables only', 'tasty-fonts'),
                                                        'classes' => __('Classes only', 'tasty-fonts'),
                                                        'custom' => __('Custom', 'tasty-fonts'),
                                                    ] as $quickModeValue => $quickModeLabel): ?>
                                                        <label class="tasty-fonts-output-quick-option<?php echo $outputQuickMode === $quickModeValue ? ' is-active' : ''; ?>" data-pill-option>
                                                            <input
                                                                type="radio"
                                                                name="tasty_fonts_output_quick_mode"
                                                                value="<?php echo esc_attr($quickModeValue); ?>"
                                                                data-output-quick-mode
                                                                data-pill-option-input
                                                                <?php checked($outputQuickMode, $quickModeValue); ?>
                                                            >
                                                            <span><?php echo esc_html($quickModeLabel); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div
                                                    class="tasty-fonts-page-notice tasty-fonts-inline-note--warning tasty-fonts-output-quick-notice"
                                                    data-output-quick-mode-notice
                                                    role="status"
                                                    aria-live="polite"
                                                    hidden
                                                >
                                                    <strong><?php esc_html_e('At least one output layer must stay enabled.', 'tasty-fonts'); ?></strong>
                                                    <span><?php esc_html_e('Keep either font variables or utility classes on while customizing output.', 'tasty-fonts'); ?></span>
                                                </div>
                                            </div>

                                            <div id="tasty-fonts-advanced-output-controls" class="tasty-fonts-settings-panel tasty-fonts-output-settings-advanced-panel" <?php echo $advancedOutputControlsExpanded ? '' : 'hidden'; ?>>
                                                <div class="tasty-fonts-output-settings-detail-group">
                                                <input type="hidden" name="role_usage_font_weight_enabled" value="0">
                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                                    <input
                                                        type="checkbox"
                                                        class="tasty-fonts-toggle-input"
                                                        name="role_usage_font_weight_enabled"
                                                        value="1"
                                                        <?php checked($roleUsageFontWeightEnabled); ?>
                                                    >
                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                        <span class="tasty-fonts-toggle-copy">
                                                            <span class="tasty-fonts-toggle-title"><?php esc_html_e('Emit Role Font Weights', 'tasty-fonts'); ?></span>
                                                            <?php if ($showSettingsDescriptions): ?>
                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Adds font-weight declarations to generated body, heading, and monospace usage rules. Off by default so themes can keep controlling weights.', 'tasty-fonts'); ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                </label>
                                                </div>
                                                <div class="tasty-fonts-output-settings-detail-group">
                                                <input type="hidden" name="class_output_enabled" value="0">
                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                                    <input
                                                        type="checkbox"
                                                        class="tasty-fonts-toggle-input"
                                                        name="class_output_enabled"
                                                        value="1"
                                                        data-output-master="classes"
                                                        <?php checked($classOutputEnabled); ?>
                                                    >
                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                    <span class="tasty-fonts-toggle-copy">
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Emit Font Utility Classes', 'tasty-fonts'); ?></span>
                                                        <?php if ($showSettingsDescriptions): ?>
                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Adds direct utility classes like .font-heading, .font-sans, and per-family selectors.', 'tasty-fonts'); ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                                <div class="tasty-fonts-output-settings-submenu<?php echo $classOutputEnabled ? '' : ' is-inactive'; ?>" data-output-panel="classes">
                                                    <div class="tasty-fonts-output-settings-submenu-copy">
                                                        <h4><?php esc_html_e('Class Controls', 'tasty-fonts'); ?></h4>
                                                        <?php if ($showSettingsDescriptions): ?>
                                                            <p><?php esc_html_e('Choose exactly which utility class groups should be generated.', 'tasty-fonts'); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="tasty-fonts-output-settings-submenu-list">
                                                        <div class="tasty-fonts-output-settings-submenu-group">
                                                            <span class="tasty-fonts-output-settings-submenu-group-title"><?php esc_html_e('Role Classes', 'tasty-fonts'); ?></span>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list">
                                                                <input type="hidden" name="class_output_role_heading_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_heading_enabled" value="1" <?php checked($classOutputRoleHeadingEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Heading Class', 'tasty-fonts'); ?></span>
                                                                        <?php if ($showSettingsDescriptions): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls .font-heading.', 'tasty-fonts'); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="class_output_role_body_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_body_enabled" value="1" <?php checked($classOutputRoleBodyEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Body Class', 'tasty-fonts'); ?></span>
                                                                        <?php if ($showSettingsDescriptions): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls .font-body.', 'tasty-fonts'); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="class_output_role_monospace_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested<?php echo $monospaceRoleEnabled ? '' : ' is-disabled'; ?>">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_monospace_enabled" value="1" data-output-mono-dependent <?php checked($classOutputRoleMonospaceEnabled); ?> <?php disabled(!$monospaceRoleEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Monospace Class', 'tasty-fonts'); ?></span>
                                                                        <?php if ($showSettingsDescriptions): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php echo esc_html($monospaceRoleEnabled ? __('Controls .font-monospace.', 'tasty-fonts') : __('Enable the monospace role to use .font-monospace.', 'tasty-fonts')); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="tasty-fonts-output-settings-submenu-group">
                                                            <span class="tasty-fonts-output-settings-submenu-group-title"><?php esc_html_e('Role Alias Classes', 'tasty-fonts'); ?></span>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list">
                                                                <input type="hidden" name="class_output_role_alias_interface_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_alias_interface_enabled" value="1" <?php checked($classOutputRoleAliasInterfaceEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Interface Alias', 'tasty-fonts'); ?></span>
                                                                        <?php if ($showSettingsDescriptions): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls .font-interface.', 'tasty-fonts'); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="class_output_role_alias_ui_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_alias_ui_enabled" value="1" <?php checked($classOutputRoleAliasUiEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('UI Alias', 'tasty-fonts'); ?></span>
                                                                        <?php if ($showSettingsDescriptions): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls .font-ui.', 'tasty-fonts'); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="class_output_role_alias_code_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested<?php echo $monospaceRoleEnabled ? '' : ' is-disabled'; ?>">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_alias_code_enabled" value="1" data-output-mono-dependent <?php checked($classOutputRoleAliasCodeEnabled); ?> <?php disabled(!$monospaceRoleEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Code Alias', 'tasty-fonts'); ?></span>
                                                                        <?php if ($showSettingsDescriptions): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php echo esc_html($monospaceRoleEnabled ? __('Controls .font-code.', 'tasty-fonts') : __('Enable the monospace role to use .font-code.', 'tasty-fonts')); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="tasty-fonts-output-settings-submenu-group">
                                                            <span class="tasty-fonts-output-settings-submenu-group-title"><?php esc_html_e('Category Classes', 'tasty-fonts'); ?></span>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list">
                                                                <input type="hidden" name="class_output_category_sans_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_category_sans_enabled" value="1" <?php checked($classOutputCategorySansEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Sans Class', 'tasty-fonts'); ?></span>
                                                                        <?php if ($showSettingsDescriptions): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls .font-sans.', 'tasty-fonts'); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="class_output_category_serif_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_category_serif_enabled" value="1" <?php checked($classOutputCategorySerifEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Serif Class', 'tasty-fonts'); ?></span>
                                                                        <?php if ($showSettingsDescriptions): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls .font-serif.', 'tasty-fonts'); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="class_output_category_mono_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested<?php echo $monospaceRoleEnabled ? '' : ' is-disabled'; ?>">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_category_mono_enabled" value="1" data-output-mono-dependent <?php checked($classOutputCategoryMonoEnabled); ?> <?php disabled(!$monospaceRoleEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Mono Class', 'tasty-fonts'); ?></span>
                                                                        <?php if ($showSettingsDescriptions): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php echo esc_html($monospaceRoleEnabled ? __('Controls .font-mono.', 'tasty-fonts') : __('Enable the monospace role to use .font-mono.', 'tasty-fonts')); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="tasty-fonts-output-settings-submenu-group">
                                                            <span class="tasty-fonts-output-settings-submenu-group-title"><?php esc_html_e('Family Classes', 'tasty-fonts'); ?></span>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list">
                                                                <input type="hidden" name="class_output_families_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_families_enabled" value="1" <?php checked($classOutputFamiliesEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Per-Family Classes', 'tasty-fonts'); ?></span>
                                                                        <?php if ($showSettingsDescriptions): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls selectors like .font-inter and .font-ibm-plex-serif.', 'tasty-fonts'); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                </div>

                                                <div class="tasty-fonts-output-settings-detail-group">
                                                <input type="hidden" name="per_variant_font_variables_enabled" value="0">
                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                                    <input
                                                        type="checkbox"
                                                        class="tasty-fonts-toggle-input"
                                                        name="per_variant_font_variables_enabled"
                                                        value="1"
                                                        data-output-master="variables"
                                                        <?php checked($perVariantFontVariablesEnabled); ?>
                                                    >
                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                    <span class="tasty-fonts-toggle-copy">
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Emit Font Variables', 'tasty-fonts'); ?></span>
                                                        <?php if ($showSettingsDescriptions): ?>
                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Adds semantic variables, role aliases, category aliases, and reusable global weight tokens.', 'tasty-fonts'); ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                                <div class="tasty-fonts-output-settings-submenu<?php echo $perVariantFontVariablesEnabled ? '' : ' is-inactive'; ?>" data-output-panel="variables">
                                                    <div class="tasty-fonts-output-settings-submenu-copy">
                                                        <h4><?php esc_html_e('Variable Controls', 'tasty-fonts'); ?></h4>
                                                        <?php if ($showSettingsDescriptions): ?>
                                                            <p><?php esc_html_e('Keep the main toggle on, then disable only the variable groups you do not want emitted.', 'tasty-fonts'); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="tasty-fonts-output-settings-submenu-list">
                                                        <input type="hidden" name="extended_variable_role_weight_vars_enabled" value="0">
                                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                            <input type="checkbox" class="tasty-fonts-toggle-input" name="extended_variable_role_weight_vars_enabled" value="1" <?php checked($extendedVariableRoleWeightVarsEnabled); ?>>
                                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                            <span class="tasty-fonts-toggle-copy">
                                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Role Weight Variables', 'tasty-fonts'); ?></span>
                                                                <?php if ($showSettingsDescriptions): ?>
                                                                    <span class="tasty-fonts-toggle-description">
                                                                        <?php
                                                                        $roleWeightVariableDescription = $monospaceRoleEnabled
                                                                            ? __('Controls variables like --font-heading-weight, --font-body-weight, and --font-monospace-weight.', 'tasty-fonts')
                                                                            : __('Controls variables like --font-heading-weight and --font-body-weight.', 'tasty-fonts');

                                                                        if (!empty($acssIntegration['configured'])) {
                                                                            $roleWeightVariableDescription .= ' ' . __('Required while Automatic.css sync is on.', 'tasty-fonts');
                                                                        }

                                                                        echo esc_html($roleWeightVariableDescription);
                                                                        ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </label>
                                                        <input type="hidden" name="extended_variable_weight_tokens_enabled" value="0">
                                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                            <input type="checkbox" class="tasty-fonts-toggle-input" name="extended_variable_weight_tokens_enabled" value="1" <?php checked($extendedVariableWeightTokensEnabled); ?>>
                                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                            <span class="tasty-fonts-toggle-copy">
                                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Global Weight Tokens', 'tasty-fonts'); ?></span>
                                                                <?php if ($showSettingsDescriptions): ?>
                                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls variables like --weight-400 and --weight-bold plus matching weight-based snippets.', 'tasty-fonts'); ?></span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </label>
                                                        <input type="hidden" name="extended_variable_role_aliases_enabled" value="0">
                                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                            <input type="checkbox" class="tasty-fonts-toggle-input" name="extended_variable_role_aliases_enabled" value="1" <?php checked($extendedVariableRoleAliasesEnabled); ?>>
                                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                            <span class="tasty-fonts-toggle-copy">
                                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Role Alias Variables', 'tasty-fonts'); ?></span>
                                                                <?php if ($showSettingsDescriptions): ?>
                                                                    <span class="tasty-fonts-toggle-description"><?php echo esc_html($monospaceRoleEnabled ? __('Controls aliases like --font-interface, --font-ui, and --font-code.', 'tasty-fonts') : __('Controls aliases like --font-interface and --font-ui.', 'tasty-fonts')); ?></span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </label>
                                                        <div class="tasty-fonts-output-settings-submenu-group">
                                                            <span class="tasty-fonts-output-settings-submenu-group-title"><?php esc_html_e('Category Aliases', 'tasty-fonts'); ?></span>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list">
                                                                <input type="hidden" name="extended_variable_category_sans_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="extended_variable_category_sans_enabled" value="1" <?php checked($extendedVariableCategorySansEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Sans Alias', 'tasty-fonts'); ?></span>
                                                                        <?php if ($showSettingsDescriptions): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls --font-sans.', 'tasty-fonts'); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="extended_variable_category_serif_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="extended_variable_category_serif_enabled" value="1" <?php checked($extendedVariableCategorySerifEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Serif Alias', 'tasty-fonts'); ?></span>
                                                                        <?php if ($showSettingsDescriptions): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls --font-serif.', 'tasty-fonts'); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                                <?php if ($monospaceRoleEnabled): ?>
                                                                    <input type="hidden" name="extended_variable_category_mono_enabled" value="0">
                                                                    <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                        <input type="checkbox" class="tasty-fonts-toggle-input" name="extended_variable_category_mono_enabled" value="1" data-output-mono-dependent <?php checked($extendedVariableCategoryMonoEnabled); ?>>
                                                                        <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                        <span class="tasty-fonts-toggle-copy">
                                                                            <span class="tasty-fonts-toggle-title"><?php esc_html_e('Mono Alias', 'tasty-fonts'); ?></span>
                                                                            <?php if ($showSettingsDescriptions): ?>
                                                                                <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls --font-mono.', 'tasty-fonts'); ?></span>
                                                                            <?php endif; ?>
                                                                        </span>
                                                                    </label>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section
                            id="tasty-fonts-settings-panel-integrations"
                            class="tasty-fonts-studio-panel"
                            data-tab-group="settings"
                            data-tab-panel="integrations"
                            role="tabpanel"
                            aria-labelledby="tasty-fonts-settings-tab-integrations"
                            hidden
                        >
                            <?php
                            $etchAvailable = !empty($etchIntegration['available']);
                            $gutenbergStatusClass = !empty($gutenbergIntegration['enabled']) ? ' is-success' : '';
                            $bricksStatus = (string) ($bricksIntegration['status'] ?? 'disabled');
                            $bricksStatusBadgeClass = $bricksStatus === 'active' ? ' is-success' : '';
                            $oxygenStatus = (string) ($oxygenIntegration['status'] ?? 'disabled');
                            $oxygenStatusBadgeClass = $oxygenStatus === 'active' ? ' is-success' : '';
                            $acssStatus = (string) ($acssIntegration['status'] ?? 'disabled');
                            $acssStatusBadgeClass = match ($acssStatus) {
                                'synced' => ' is-success',
                                'ready' => ' is-role',
                                'out_of_sync', 'waiting_for_sitewide_roles' => ' is-warning',
                                default => '',
                            };

                            $etchStatusHelp = trim((string) ($etchIntegration['description'] ?? ''));
                            $etchBadgeClass = 'tasty-fonts-badge' . ($etchAvailable ? ' is-success' : '');
                            $etchBadgeInteractive = !$trainingWheelsOff && $etchStatusHelp !== '';
                            if ($etchBadgeInteractive) {
                                $etchBadgeClass .= ' tasty-fonts-badge--interactive tasty-fonts-badge--help';
                            }

                            $gutenbergStatusHelp = trim((string) ($gutenbergIntegration['status_copy'] ?? ''));
                            $gutenbergBadgeClass = 'tasty-fonts-badge' . esc_attr($gutenbergStatusClass);
                            $gutenbergBadgeInteractive = !$trainingWheelsOff && $gutenbergStatusHelp !== '';
                            if ($gutenbergBadgeInteractive) {
                                $gutenbergBadgeClass .= ' tasty-fonts-badge--interactive tasty-fonts-badge--help';
                            }

                            $acssStatusHelp = trim((string) ($acssIntegration['status_copy'] ?? ''));
                            $acssBadgeClass = 'tasty-fonts-badge' . esc_attr($acssStatusBadgeClass);
                            $acssBadgeInteractive = !$trainingWheelsOff && $acssStatusHelp !== '';
                            if ($acssBadgeInteractive) {
                                $acssBadgeClass .= ' tasty-fonts-badge--interactive tasty-fonts-badge--help';
                            }
                            ?>
                            <div class="tasty-fonts-output-settings-panel tasty-fonts-integrations-panel">
                                <div class="tasty-fonts-output-settings-copy">
                                    <h3><?php esc_html_e('Integrations', 'tasty-fonts'); ?></h3>
                                </div>

                                <div class="tasty-fonts-output-settings-list tasty-fonts-integrations-list">
                                    <div class="tasty-fonts-output-settings-quick tasty-fonts-output-settings-choice">
                                        <div class="tasty-fonts-output-settings-submenu-copy">
                                            <h4 class="tasty-fonts-integration-heading">
                                                <span class="tasty-fonts-integration-mark tasty-fonts-integration-mark--etch" aria-hidden="true"></span>
                                                <span><?php echo esc_html((string) ($etchIntegration['title'] ?? __('Etch Canvas Bridge', 'tasty-fonts'))); ?></span>
                                            </h4>
                                            <?php if ($showSettingsDescriptions && (string) ($etchIntegration['description'] ?? '') !== ''): ?>
                                                <p><?php echo esc_html((string) ($etchIntegration['description'] ?? '')); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="tasty-fonts-output-quick-options">
                                            <?php if ($etchBadgeInteractive): ?>
                                                <button
                                                    type="button"
                                                    class="<?php echo esc_attr($etchBadgeClass); ?>"
                                                    <?php $this->renderPassiveHelpAttributes($etchStatusHelp); ?>
                                                    aria-label="<?php esc_attr_e('Etch integration status', 'tasty-fonts'); ?>"
                                                >
                                                    <?php echo esc_html($etchAvailable ? __('Active', 'tasty-fonts') : __('Inactive', 'tasty-fonts')); ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="<?php echo esc_attr($etchBadgeClass); ?>">
                                                    <?php echo esc_html($etchAvailable ? __('Active', 'tasty-fonts') : __('Inactive', 'tasty-fonts')); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="tasty-fonts-output-settings-form tasty-fonts-integrations-form">
                                    <div class="tasty-fonts-output-settings-list tasty-fonts-integrations-list">
                                        <?php
                                        $showBricksStatusCopy = (($bricksIntegration['available'] ?? false) !== true) || (($bricksIntegration['enabled'] ?? false) !== true);
                                        ?>
                                        <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration">
                                            <input type="hidden" name="bricks_integration_enabled" value="0">
                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration">
                                                <input
                                                    type="checkbox"
                                                    class="tasty-fonts-toggle-input"
                                                    name="bricks_integration_enabled"
                                                    value="1"
                                                    <?php checked(($bricksIntegration['enabled'] ?? false) === true); ?>
                                                    <?php disabled(($bricksIntegration['available'] ?? true) !== true); ?>
                                                >
                                                <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                <span class="tasty-fonts-toggle-copy">
                                                    <span class="tasty-fonts-toggle-title-line">
                                                        <span class="tasty-fonts-toggle-title tasty-fonts-integration-title">
                                                            <span class="tasty-fonts-integration-mark tasty-fonts-integration-mark--bricks" aria-hidden="true"></span>
                                                            <span><?php echo esc_html((string) ($bricksIntegration['title'] ?? __('Bricks Builder', 'tasty-fonts'))); ?></span>
                                                        </span>
                                                        <span class="<?php echo esc_attr('tasty-fonts-badge' . $bricksStatusBadgeClass); ?>">
                                                            <?php echo esc_html((string) ($bricksIntegration['status_label'] ?? __('Off', 'tasty-fonts'))); ?>
                                                        </span>
                                                    </span>
                                                    <?php if ($showSettingsDescriptions && (string) ($bricksIntegration['description'] ?? '') !== ''): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($bricksIntegration['description'] ?? '')); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($showSettingsDescriptions && $showBricksStatusCopy && (string) ($bricksIntegration['status_copy'] ?? '') !== ''): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($bricksIntegration['status_copy'] ?? '')); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>

                                            <?php if (($bricksIntegration['available'] ?? false) === true): ?>
                                            <?php
                                            $bricksManagedControlsEnabled = !empty($bricksIntegration['theme_styles']['enabled'])
                                                || !empty($bricksIntegration['google_fonts']['enabled']);
                                            $bricksThemeStyleSummary = (array) (($bricksIntegration['theme_styles']['summary'] ?? null) ?: []);
                                            $bricksManagedThemeStyleLabel = trim((string) ($bricksThemeStyleSummary['managed_style_label'] ?? \TastyFonts\Integrations\BricksIntegrationService::MANAGED_THEME_STYLE_LABEL));
                                            $bricksAvailableThemeStyles = (array) ($bricksThemeStyleSummary['available_styles'] ?? []);
                                            $bricksHasThemeStyles = !empty($bricksThemeStyleSummary['has_theme_styles']);
                                            $bricksManagedThemeStyleExists = !empty($bricksThemeStyleSummary['managed_style_exists']);
                                            $bricksThemeStylesEnabled = !empty($bricksIntegration['theme_styles']['enabled']);
                                            $bricksThemeStylesApplied = !empty($bricksIntegration['theme_styles']['applied']);
                                            $bricksTargetMode = trim((string) ($bricksThemeStyleSummary['target_mode'] ?? \TastyFonts\Integrations\BricksIntegrationService::TARGET_MODE_MANAGED));
                                            $bricksTargetThemeStyleId = trim((string) ($bricksThemeStyleSummary['target_style_id'] ?? \TastyFonts\Integrations\BricksIntegrationService::MANAGED_THEME_STYLE_ID));
                                            $bricksTargetThemeStyleLabel = trim((string) ($bricksThemeStyleSummary['target_style_label'] ?? $bricksManagedThemeStyleLabel));
                                            $bricksTargetIsManaged = !empty($bricksThemeStyleSummary['target_is_managed']);
                                            $bricksTargetIsAll = !empty($bricksThemeStyleSummary['target_is_all']);
                                            ?>
                                            <div class="tasty-fonts-output-settings-submenu tasty-fonts-output-settings-submenu--integration">
                                                <div class="tasty-fonts-output-settings-submenu-copy tasty-fonts-output-settings-submenu-copy--compact">
                                                    <h4><?php esc_html_e('Bricks Controls', 'tasty-fonts'); ?></h4>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <p><?php esc_html_e('Bricks selectors and builder previews are included automatically. Use the controls below only for the deeper typography settings Tasty should manage.', 'tasty-fonts'); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="tasty-fonts-output-settings-submenu-list tasty-fonts-output-settings-submenu-list--integration tasty-fonts-output-settings-submenu-list--bricks">
                                                    <?php
                                                    $bricksFeatureBadgeClass = static function (string $status): string {
                                                        return 'tasty-fonts-badge' . match ($status) {
                                                            'synced', 'active' => ' is-success',
                                                            'waiting_for_sitewide_roles', 'ready' => ' is-warning',
                                                            'unavailable' => ' is-danger',
                                                            default => '',
                                                        };
                                                    };
                                                    $bricksFeatureStatusLabel = static function (string $status): string {
                                                        return match ($status) {
                                                            'synced' => __('Synced', 'tasty-fonts'),
                                                            'active' => __('On', 'tasty-fonts'),
                                                            'waiting_for_sitewide_roles' => __('Waiting', 'tasty-fonts'),
                                                            'ready' => __('Ready', 'tasty-fonts'),
                                                            'unavailable' => __('Not Active', 'tasty-fonts'),
                                                            default => __('Off', 'tasty-fonts'),
                                                        };
                                                    };
                                                    ?>

                                                    <div class="tasty-fonts-bricks-feature-grid">
                                                        <?php foreach (
                                                            [
                                                                'bricks_theme_styles_sync_enabled' => [
                                                                    'title' => __('Sync Bricks Theme Styles', 'tasty-fonts'),
                                                                    'state' => (array) ($bricksIntegration['theme_styles'] ?? []),
                                                                    'description' => (string) (($bricksIntegration['feature_descriptions']['theme_styles'] ?? '')),
                                                                ],
                                                                'bricks_disable_google_fonts_enabled' => [
                                                                    'title' => __('Disable Bricks Google Fonts', 'tasty-fonts'),
                                                                    'state' => (array) ($bricksIntegration['google_fonts'] ?? []),
                                                                    'description' => (string) (($bricksIntegration['feature_descriptions']['google_fonts'] ?? '')),
                                                                ],
                                                            ] as $fieldName => $feature
                                                        ): ?>
                                                            <?php
                                                            $featureState = (array) ($feature['state'] ?? []);
                                                            $featureStatus = (string) ($featureState['status'] ?? 'disabled');
                                                            ?>
                                                            <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration tasty-fonts-output-settings-detail-group--integration-card">
                                                                <input type="hidden" name="<?php echo esc_attr($fieldName); ?>" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration">
                                                                    <input
                                                                        type="checkbox"
                                                                        class="tasty-fonts-toggle-input"
                                                                        name="<?php echo esc_attr($fieldName); ?>"
                                                                        value="1"
                                                                        <?php checked(($featureState['enabled'] ?? false) === true); ?>
                                                                    >
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title-line">
                                                                            <span class="tasty-fonts-toggle-title"><?php echo esc_html((string) ($feature['title'] ?? '')); ?></span>
                                                                            <span class="<?php echo esc_attr($bricksFeatureBadgeClass($featureStatus)); ?>">
                                                                                <?php echo esc_html($bricksFeatureStatusLabel($featureStatus)); ?>
                                                                            </span>
                                                                        </span>
                                                                        <?php if ($showSettingsDescriptions && (string) ($feature['description'] ?? '') !== ''): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($feature['description'] ?? '')); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>

                                                    <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration tasty-fonts-output-settings-detail-group--integration-card">
                                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration">
                                                            <span class="tasty-fonts-toggle-copy">
                                                                <span class="tasty-fonts-toggle-title-line">
                                                                    <span class="tasty-fonts-toggle-title"><?php esc_html_e('Theme Style Target', 'tasty-fonts'); ?></span>
                                                                    <?php if ($bricksThemeStylesApplied && $bricksTargetThemeStyleLabel !== ''): ?>
                                                                        <span class="tasty-fonts-badge is-success"><?php echo esc_html($bricksTargetThemeStyleLabel); ?></span>
                                                                    <?php endif; ?>
                                                                </span>
                                                                <?php if ($showSettingsDescriptions): ?>
                                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Tasty only updates the font-family and font-weight fields on the selected Bricks Theme Style.', 'tasty-fonts'); ?></span>
                                                                <?php endif; ?>
                                                                <span class="tasty-fonts-toggle-description">
                                                                    <?php if (!$bricksHasThemeStyles && !$bricksManagedThemeStyleExists): ?>
                                                                        <?php esc_html_e('No Bricks Theme Style found yet. Tasty can create one for you.', 'tasty-fonts'); ?>
                                                                        <?php elseif ($bricksTargetIsAll): ?>
                                                                        <?php esc_html_e('Tasty will update every Bricks Theme Style to use the Tasty role variables.', 'tasty-fonts'); ?>
                                                                    <?php elseif ($bricksTargetIsManaged): ?>
                                                                        <?php
                                                                        echo esc_html(
                                                                            sprintf(
                                                                                /* translators: %s: theme style label */
                                                                                __('Tasty will use the managed Theme Style "%s".', 'tasty-fonts'),
                                                                                $bricksManagedThemeStyleLabel
                                                                            )
                                                                        );
                                                                        ?>
                                                                    <?php else: ?>
                                                                        <?php
                                                                        echo esc_html(
                                                                            sprintf(
                                                                                /* translators: %s: theme style label */
                                                                                __('Tasty will patch font fields on "%s".', 'tasty-fonts'),
                                                                                $bricksTargetThemeStyleLabel
                                                                            )
                                                                        );
                                                                        ?>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </span>
                                                        </label>
                                                        <div class="tasty-fonts-settings-inline-controls">
                                                            <div class="tasty-fonts-bricks-target-modes" role="radiogroup" aria-label="<?php esc_attr_e('Bricks Theme Style target mode', 'tasty-fonts'); ?>" data-bricks-theme-style-target-modes>
                                                                <?php foreach (
                                                                    [
                                                                        \TastyFonts\Integrations\BricksIntegrationService::TARGET_MODE_MANAGED => __('Use Tasty Theme Style', 'tasty-fonts'),
                                                                        \TastyFonts\Integrations\BricksIntegrationService::TARGET_MODE_SELECTED => __('Update One Existing Style', 'tasty-fonts'),
                                                                        \TastyFonts\Integrations\BricksIntegrationService::TARGET_MODE_ALL => __('Update All Theme Styles', 'tasty-fonts'),
                                                                    ] as $targetModeValue => $targetModeLabel
                                                                ): ?>
                                                                    <label class="tasty-fonts-bricks-target-mode">
                                                                        <input
                                                                            type="radio"
                                                                            name="bricks_theme_style_target_mode"
                                                                            value="<?php echo esc_attr($targetModeValue); ?>"
                                                                            data-bricks-theme-style-target-mode
                                                                            <?php checked($bricksTargetMode, $targetModeValue); ?>
                                                                        >
                                                                        <span><?php echo esc_html($targetModeLabel); ?></span>
                                                                    </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <select
                                                                name="bricks_theme_style_target_id"
                                                                class="tasty-fonts-select"
                                                                data-bricks-theme-style-target-select
                                                                <?php disabled($bricksTargetMode !== \TastyFonts\Integrations\BricksIntegrationService::TARGET_MODE_SELECTED); ?>
                                                            >
                                                                <?php if ($bricksAvailableThemeStyles === [] || (count($bricksAvailableThemeStyles) === 1 && isset($bricksAvailableThemeStyles[\TastyFonts\Integrations\BricksIntegrationService::MANAGED_THEME_STYLE_ID]))): ?>
                                                                    <option value="<?php echo esc_attr(\TastyFonts\Integrations\BricksIntegrationService::MANAGED_THEME_STYLE_ID); ?>">
                                                                        <?php esc_html_e('No existing Bricks Theme Styles yet', 'tasty-fonts'); ?>
                                                                    </option>
                                                                <?php endif; ?>
                                                                <?php foreach ($bricksAvailableThemeStyles as $styleId => $styleLabel): ?>
                                                                    <?php if ((string) $styleId === \TastyFonts\Integrations\BricksIntegrationService::MANAGED_THEME_STYLE_ID): ?>
                                                                        <?php continue; ?>
                                                                    <?php endif; ?>
                                                                    <option value="<?php echo esc_attr((string) $styleId); ?>" <?php selected($bricksTargetThemeStyleId, (string) $styleId); ?>>
                                                                        <?php echo esc_html((string) $styleLabel); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <?php if (!$bricksHasThemeStyles && !$bricksManagedThemeStyleExists): ?>
                                                                <button
                                                                    type="submit"
                                                                    class="button button-small tasty-fonts-create-theme-style-button"
                                                                    name="bricks_create_theme_style"
                                                                    value="1"
                                                                    data-settings-force-submit
                                                                >
                                                                    <?php esc_html_e('Create Tasty Theme Style', 'tasty-fonts'); ?>
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($bricksManagedThemeStyleExists): ?>
                                                                <button
                                                                    type="submit"
                                                                    class="button button-secondary button-small"
                                                                    name="bricks_delete_theme_style"
                                                                    value="1"
                                                                    data-settings-force-submit
                                                                >
                                                                    <?php esc_html_e('Delete Tasty Theme Style', 'tasty-fonts'); ?>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <?php if ($bricksManagedControlsEnabled): ?>
                                                        <details class="tasty-fonts-integration-details" open>
                                                            <summary><?php esc_html_e('Managed Mapping Details', 'tasty-fonts'); ?></summary>
                                                            <div class="tasty-fonts-integration-details-body tasty-fonts-integration-details-body--two-column">
                                                                <section class="tasty-fonts-integration-group" aria-label="<?php esc_attr_e('Current Bricks managed values', 'tasty-fonts'); ?>">
                                                                    <span class="tasty-fonts-integration-group-title"><?php esc_html_e('Current Bricks State', 'tasty-fonts'); ?></span>
                                                                    <div class="tasty-fonts-integration-inline-summary">
                                                                        <?php if (!$bricksHasThemeStyles): ?>
                                                                            <p><?php esc_html_e('No Bricks Theme Style found yet. Choose the managed target above if you want Tasty to create one automatically.', 'tasty-fonts'); ?></p>
                                                                        <?php elseif ($bricksTargetIsAll): ?>
                                                                            <p><?php esc_html_e('Tasty is set to update every Bricks Theme Style that exists on this site.', 'tasty-fonts'); ?></p>
                                                                        <?php elseif ($bricksTargetIsManaged && $bricksManagedThemeStyleExists): ?>
                                                                            <p>
                                                                                <?php
                                                                                echo esc_html(
                                                                                    sprintf(
                                                                                        /* translators: %s: theme style label */
                                                                                        __('Tasty created and manages the Bricks Theme Style "%s".', 'tasty-fonts'),
                                                                                        $bricksManagedThemeStyleLabel
                                                                                    )
                                                                                );
                                                                                ?>
                                                                            </p>
                                                                        <?php elseif ($bricksTargetThemeStyleLabel !== ''): ?>
                                                                            <p>
                                                                                <?php
                                                                                echo esc_html(
                                                                                    sprintf(
                                                                                        /* translators: %s: theme style label */
                                                                                        $bricksThemeStylesApplied
                                                                                            ? __('Tasty is applying Bricks font updates to "%s".', 'tasty-fonts')
                                                                                            : __('Tasty is ready to update the Theme Style "%s".', 'tasty-fonts'),
                                                                                        $bricksTargetThemeStyleLabel
                                                                                    )
                                                                                );
                                                                                ?>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <dl class="tasty-fonts-integration-kv-list">
                                                                        <div class="tasty-fonts-integration-kv">
                                                                            <dt><?php esc_html_e('Theme Style', 'tasty-fonts'); ?></dt>
                                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html($bricksTargetThemeStyleLabel !== '' ? $bricksTargetThemeStyleLabel : __('not selected', 'tasty-fonts')); ?></span></dd>
                                                                        </div>
                                                                        <div class="tasty-fonts-integration-kv">
                                                                            <dt><?php esc_html_e('Body', 'tasty-fonts'); ?></dt>
                                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) (($bricksIntegration['theme_styles']['current']['body_family'] ?? '') !== '' ? $bricksIntegration['theme_styles']['current']['body_family'] : __('empty', 'tasty-fonts'))); ?></span></dd>
                                                                        </div>
                                                                        <div class="tasty-fonts-integration-kv">
                                                                            <dt><?php esc_html_e('Heading', 'tasty-fonts'); ?></dt>
                                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) (($bricksIntegration['theme_styles']['current']['heading_family'] ?? '') !== '' ? $bricksIntegration['theme_styles']['current']['heading_family'] : __('empty', 'tasty-fonts'))); ?></span></dd>
                                                                        </div>
                                                                        <div class="tasty-fonts-integration-kv">
                                                                            <dt><?php esc_html_e('Google Fonts', 'tasty-fonts'); ?></dt>
                                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html(!empty($bricksIntegration['google_fonts']['current']['google_fonts_disabled']) ? __('disabled', 'tasty-fonts') : __('enabled', 'tasty-fonts')); ?></span></dd>
                                                                        </div>
                                                                    </dl>
                                                                </section>

                                                                <section class="tasty-fonts-integration-group" aria-label="<?php esc_attr_e('Target Bricks mapping', 'tasty-fonts'); ?>">
                                                                    <span class="tasty-fonts-integration-group-title"><?php esc_html_e('Target Mapping', 'tasty-fonts'); ?></span>
                                                                    <dl class="tasty-fonts-integration-kv-list">
                                                                        <div class="tasty-fonts-integration-kv">
                                                                            <dt><?php esc_html_e('Body', 'tasty-fonts'); ?></dt>
                                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) ($bricksIntegration['theme_styles']['desired']['body_family'] ?? \TastyFonts\Integrations\BricksIntegrationService::DESIRED_BODY_VALUE)); ?></span></dd>
                                                                        </div>
                                                                        <div class="tasty-fonts-integration-kv">
                                                                            <dt><?php esc_html_e('Heading', 'tasty-fonts'); ?></dt>
                                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) ($bricksIntegration['theme_styles']['desired']['heading_family'] ?? \TastyFonts\Integrations\BricksIntegrationService::DESIRED_HEADING_VALUE)); ?></span></dd>
                                                                        </div>
                                                                        <div class="tasty-fonts-integration-kv">
                                                                            <dt><?php esc_html_e('Body Weight', 'tasty-fonts'); ?></dt>
                                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) ($bricksIntegration['theme_styles']['desired']['body_weight'] ?? \TastyFonts\Integrations\BricksIntegrationService::DESIRED_BODY_WEIGHT_VALUE)); ?></span></dd>
                                                                        </div>
                                                                        <div class="tasty-fonts-integration-kv">
                                                                            <dt><?php esc_html_e('Heading Weight', 'tasty-fonts'); ?></dt>
                                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) ($bricksIntegration['theme_styles']['desired']['heading_weight'] ?? \TastyFonts\Integrations\BricksIntegrationService::DESIRED_HEADING_WEIGHT_VALUE)); ?></span></dd>
                                                                        </div>
                                                                    </dl>
                                                                </section>
                                                            </div>
                                                        </details>
                                                    <?php else: ?>
                                                        <div class="tasty-fonts-integration-note">
                                                            <?php esc_html_e('Enable Theme Style sync or Disable Bricks Google Fonts to see the current Bricks state here.', 'tasty-fonts'); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="tasty-fonts-settings-inline-controls tasty-fonts-settings-inline-controls--actions">
                                                        <button
                                                            type="submit"
                                                            class="button button-secondary button-small"
                                                            name="bricks_reset_integration"
                                                            value="1"
                                                            data-settings-force-submit
                                                        >
                                                            <?php esc_html_e('Reset Bricks Integration', 'tasty-fonts'); ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration">
                                            <input type="hidden" name="oxygen_integration_enabled" value="0">
                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration">
                                                <input
                                                    type="checkbox"
                                                    class="tasty-fonts-toggle-input"
                                                    name="oxygen_integration_enabled"
                                                    value="1"
                                                    <?php checked(($oxygenIntegration['enabled'] ?? false) === true); ?>
                                                    <?php disabled(($oxygenIntegration['available'] ?? true) !== true); ?>
                                                >
                                                <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                <span class="tasty-fonts-toggle-copy">
                                                    <span class="tasty-fonts-toggle-title-line">
                                                        <span class="tasty-fonts-toggle-title tasty-fonts-integration-title">
                                                            <span class="tasty-fonts-integration-mark tasty-fonts-integration-mark--oxygen" aria-hidden="true"></span>
                                                            <span><?php echo esc_html((string) ($oxygenIntegration['title'] ?? __('Oxygen Builder', 'tasty-fonts'))); ?></span>
                                                        </span>
                                                        <span class="<?php echo esc_attr('tasty-fonts-badge' . $oxygenStatusBadgeClass); ?>">
                                                            <?php echo esc_html((string) ($oxygenIntegration['status_label'] ?? __('Off', 'tasty-fonts'))); ?>
                                                        </span>
                                                    </span>
                                                    <?php if ($showSettingsDescriptions && (string) ($oxygenIntegration['description'] ?? '') !== ''): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($oxygenIntegration['description'] ?? '')); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($showSettingsDescriptions && (string) ($oxygenIntegration['status_copy'] ?? '') !== ''): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($oxygenIntegration['status_copy'] ?? '')); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                        </div>

                                        <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration">
                                            <input type="hidden" name="block_editor_font_library_sync_enabled" value="0">
                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration">
                                                <input
                                                    type="checkbox"
                                                    class="tasty-fonts-toggle-input"
                                                    name="block_editor_font_library_sync_enabled"
                                                    value="1"
                                                    <?php checked($blockEditorFontLibrarySyncEnabled); ?>
                                                >
                                                <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                <span class="tasty-fonts-toggle-copy">
                                                    <span class="tasty-fonts-toggle-title-line">
                                                        <span class="tasty-fonts-toggle-title tasty-fonts-integration-title">
                                                            <span class="tasty-fonts-integration-mark tasty-fonts-integration-mark--gutenberg" aria-hidden="true"></span>
                                                            <span><?php echo esc_html((string) ($gutenbergIntegration['title'] ?? __('Gutenberg Font Library', 'tasty-fonts'))); ?></span>
                                                        </span>
                                                        <span class="<?php echo esc_attr('tasty-fonts-badge' . $gutenbergStatusClass); ?>">
                                                            <?php echo esc_html((string) ($gutenbergIntegration['status_label'] ?? __('Off', 'tasty-fonts'))); ?>
                                                        </span>
                                                    </span>
                                                    <?php if ($showSettingsDescriptions && (string) ($gutenbergIntegration['description'] ?? '') !== ''): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($gutenbergIntegration['description'] ?? '')); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                        </div>

                                        <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration">
                                            <input type="hidden" name="acss_font_role_sync_enabled" value="0">
                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration">
                                                <input
                                                    type="checkbox"
                                                    class="tasty-fonts-toggle-input"
                                                    name="acss_font_role_sync_enabled"
                                                    value="1"
                                                    <?php checked(($acssIntegration['enabled'] ?? false) === true); ?>
                                                    <?php disabled(($acssIntegration['available'] ?? true) !== true); ?>
                                                >
                                                <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                <span class="tasty-fonts-toggle-copy">
                                                    <span class="tasty-fonts-toggle-title-line">
                                                        <span class="tasty-fonts-toggle-title tasty-fonts-integration-title">
                                                            <span class="tasty-fonts-integration-mark tasty-fonts-integration-mark--acss" aria-hidden="true"></span>
                                                            <span><?php echo esc_html((string) ($acssIntegration['title'] ?? __('Automatic.css', 'tasty-fonts'))); ?></span>
                                                        </span>
                                                        <span class="<?php echo esc_attr('tasty-fonts-badge' . $acssStatusBadgeClass); ?>">
                                                            <?php echo esc_html((string) ($acssIntegration['status_label'] ?? __('Off', 'tasty-fonts'))); ?>
                                                        </span>
                                                    </span>
                                                    <?php if ($showSettingsDescriptions && (string) ($acssIntegration['description'] ?? '') !== ''): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($acssIntegration['description'] ?? '')); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>

                                            <?php if (($acssIntegration['enabled'] ?? false) === true && ($acssIntegration['available'] ?? false) === true): ?>
                                            <div class="tasty-fonts-output-settings-submenu tasty-fonts-output-settings-submenu--integration">
                                                <div class="tasty-fonts-output-settings-submenu-copy">
                                                    <h4><?php esc_html_e('Managed Mapping', 'tasty-fonts'); ?></h4>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <p><?php esc_html_e('Review the current Automatic.css font-family and font-weight values alongside the managed heading/body variable mapping.', 'tasty-fonts'); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="tasty-fonts-output-settings-submenu-list tasty-fonts-output-settings-submenu-list--integration">
                                                <section class="tasty-fonts-integration-group" aria-label="<?php esc_attr_e('Current Automatic.css values', 'tasty-fonts'); ?>">
                                                    <span class="tasty-fonts-integration-group-title"><?php esc_html_e('Current Automatic.css Values', 'tasty-fonts'); ?></span>
                                                    <dl class="tasty-fonts-integration-kv-list">
                                                        <div class="tasty-fonts-integration-kv">
                                                            <dt><?php esc_html_e('Heading', 'tasty-fonts'); ?></dt>
                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) (($acssIntegration['current']['heading'] ?? '') !== '' ? $acssIntegration['current']['heading'] : __('empty', 'tasty-fonts'))); ?></span></dd>
                                                        </div>
                                                        <div class="tasty-fonts-integration-kv">
                                                            <dt><?php esc_html_e('Body Text', 'tasty-fonts'); ?></dt>
                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) (($acssIntegration['current']['body'] ?? '') !== '' ? $acssIntegration['current']['body'] : __('empty', 'tasty-fonts'))); ?></span></dd>
                                                        </div>
                                                        <div class="tasty-fonts-integration-kv">
                                                            <dt><?php esc_html_e('Heading Weight', 'tasty-fonts'); ?></dt>
                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) (($acssIntegration['current']['heading_weight'] ?? '') !== '' ? $acssIntegration['current']['heading_weight'] : __('empty', 'tasty-fonts'))); ?></span></dd>
                                                        </div>
                                                        <div class="tasty-fonts-integration-kv">
                                                            <dt><?php esc_html_e('Body Weight', 'tasty-fonts'); ?></dt>
                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) (($acssIntegration['current']['body_weight'] ?? '') !== '' ? $acssIntegration['current']['body_weight'] : __('empty', 'tasty-fonts'))); ?></span></dd>
                                                        </div>
                                                    </dl>
                                                </section>

                                                <section class="tasty-fonts-integration-group" aria-label="<?php esc_attr_e('Desired mapping', 'tasty-fonts'); ?>">
                                                    <span class="tasty-fonts-integration-group-title"><?php esc_html_e('Desired Mapping', 'tasty-fonts'); ?></span>
                                                    <dl class="tasty-fonts-integration-kv-list">
                                                        <div class="tasty-fonts-integration-kv">
                                                            <dt><?php esc_html_e('Heading', 'tasty-fonts'); ?></dt>
                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) ($acssIntegration['desired'][\TastyFonts\Integrations\AcssIntegrationService::OPTION_HEADING_FONT_FAMILY] ?? 'var(--font-heading)')); ?></span></dd>
                                                        </div>
                                                        <div class="tasty-fonts-integration-kv">
                                                            <dt><?php esc_html_e('Body Text', 'tasty-fonts'); ?></dt>
                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) ($acssIntegration['desired'][\TastyFonts\Integrations\AcssIntegrationService::OPTION_TEXT_FONT_FAMILY] ?? 'var(--font-body)')); ?></span></dd>
                                                        </div>
                                                        <div class="tasty-fonts-integration-kv">
                                                            <dt><?php esc_html_e('Heading Weight', 'tasty-fonts'); ?></dt>
                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) ($acssIntegration['desired'][\TastyFonts\Integrations\AcssIntegrationService::OPTION_HEADING_FONT_WEIGHT] ?? 'var(--font-heading-weight)')); ?></span></dd>
                                                        </div>
                                                        <div class="tasty-fonts-integration-kv">
                                                            <dt><?php esc_html_e('Body Weight', 'tasty-fonts'); ?></dt>
                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) ($acssIntegration['desired'][\TastyFonts\Integrations\AcssIntegrationService::OPTION_TEXT_FONT_WEIGHT] ?? 'var(--font-body-weight)')); ?></span></dd>
                                                        </div>
                                                    </dl>
                                                </section>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section
                            id="tasty-fonts-settings-panel-plugin-behavior"
                            class="tasty-fonts-studio-panel"
                            data-tab-group="settings"
                            data-tab-panel="plugin-behavior"
                            role="tabpanel"
                            aria-labelledby="tasty-fonts-settings-tab-plugin-behavior"
                            hidden
                        >
                            <div class="tasty-fonts-output-settings-panel">
                                <div class="tasty-fonts-output-settings-copy">
                                    <h3><?php esc_html_e('Behavior', 'tasty-fonts'); ?></h3>
                                </div>
                                <div class="tasty-fonts-output-settings-form tasty-fonts-settings-behavior-form">
                                <div class="tasty-fonts-output-settings-list">
                                    <div class="tasty-fonts-output-settings-detail-group tasty-fonts-settings-flat-row tasty-fonts-settings-flat-row--channel">
                                        <div class="tasty-fonts-output-settings-submenu-copy tasty-fonts-settings-flat-row-copy tasty-fonts-settings-flat-row-copy--channel">
                                            <h4><?php esc_html_e('Update Channel', 'tasty-fonts'); ?></h4>
                                            <?php if ($showSettingsDescriptions): ?>
                                                <p><?php esc_html_e('Choose which GitHub release rail this install should follow for plugin updates.', 'tasty-fonts'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="tasty-fonts-settings-flat-row-form tasty-fonts-settings-flat-row-form--channel-control">
                                            <div class="tasty-fonts-output-quick-options tasty-fonts-settings-flat-row-options" role="radiogroup" aria-label="<?php esc_attr_e('Update channel', 'tasty-fonts'); ?>">
                                                <?php foreach ($updateChannelOptions as $option): ?>
                                                    <?php $optionValue = (string) ($option['value'] ?? 'stable'); ?>
                                                    <label class="tasty-fonts-output-quick-option<?php echo $updateChannel === $optionValue ? ' is-active' : ''; ?>" data-pill-option>
                                                        <input
                                                            type="radio"
                                                            name="update_channel"
                                                            value="<?php echo esc_attr($optionValue); ?>"
                                                            data-pill-option-input
                                                            <?php checked($updateChannel, $optionValue); ?>
                                                        >
                                                        <span><?php echo esc_html((string) ($option['label'] ?? '')); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($updateChannelStatus['can_reinstall'])): ?>
                                            <div class="tasty-fonts-settings-flat-row-form tasty-fonts-settings-flat-row-form--channel-action">
                                                <div class="tasty-fonts-developer-action-row tasty-fonts-settings-flat-row-actions">
                                                    <button
                                                        type="submit"
                                                        class="button button-small tasty-fonts-developer-action-button"
                                                        form="tasty-fonts-reinstall-update-channel-form"
                                                        name="tasty_fonts_reinstall_update_channel"
                                                        value="1"
                                                        aria-label="<?php esc_attr_e('Reinstall selected channel', 'tasty-fonts'); ?>"
                                                        <?php $this->renderPassiveHelpAttributes((string) ($updateChannelStatus['state_copy'] ?? '')); ?>
                                                    ><?php esc_html_e('Reinstall', 'tasty-fonts'); ?></button>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="tasty-fonts-settings-flat-row-support tasty-fonts-settings-flat-row-support--channel">
                                            <div class="tasty-fonts-settings-flat-row-status tasty-fonts-settings-flat-row-status--channel">
                                                <span class="tasty-fonts-badge <?php echo esc_attr((string) ($updateChannelStatus['state_class'] ?? '')); ?>">
                                                    <?php echo esc_html((string) ($updateChannelStatus['state_label'] ?? __('Unavailable', 'tasty-fonts'))); ?>
                                                </span>
                                                <p class="tasty-fonts-settings-flat-row-summary">
                                                    <?php
                                                    echo esc_html(
                                                        sprintf(
                                                            __('Installed: %1$s. Latest for %2$s: %3$s.', 'tasty-fonts'),
                                                            (string) ($updateChannelStatus['installed_version'] ?? __('Unknown', 'tasty-fonts')),
                                                            (string) ($updateChannelStatus['selected_channel_label'] ?? __('Stable', 'tasty-fonts')),
                                                            (string) ($updateChannelStatus['latest_version'] ?? __('Unavailable', 'tasty-fonts'))
                                                        )
                                                    );
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tasty-fonts-settings-flat-row-form tasty-fonts-settings-flat-row-form--stack">
                                        <input type="hidden" name="monospace_role_enabled" value="0">
                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                            <input type="checkbox" class="tasty-fonts-toggle-input" name="monospace_role_enabled" value="1" <?php checked($monospaceRoleEnabled); ?>>
                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                            <span class="tasty-fonts-toggle-copy">
                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable Monospace Role', 'tasty-fonts'); ?></span>
                                                <?php if ($showSettingsDescriptions): ?>
                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Adds a saved role for code and pre, exposed as --font-monospace.', 'tasty-fonts'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                        <input type="hidden" name="variable_fonts_enabled" value="0">
                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                            <input type="checkbox" class="tasty-fonts-toggle-input" name="variable_fonts_enabled" value="1" <?php checked(!empty($variableFontsEnabled)); ?>>
                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                            <span class="tasty-fonts-toggle-copy">
                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable Variable Fonts', 'tasty-fonts'); ?></span>
                                                <?php if ($showSettingsDescriptions): ?>
                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Opt in to variable font uploads, axis controls, and Gutenberg variable metadata. Leave this off to keep static-only behavior.', 'tasty-fonts'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                        <input type="hidden" name="training_wheels_off" value="0">
                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                            <input type="checkbox" class="tasty-fonts-toggle-input" name="training_wheels_off" value="1" <?php checked($trainingWheelsOff); ?>>
                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                            <span class="tasty-fonts-toggle-copy">
                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Hide Onboarding Hints', 'tasty-fonts'); ?></span>
                                                <?php if ($showSettingsDescriptions): ?>
                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Hides helper tips and extra info buttons.', 'tasty-fonts'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                        <input type="hidden" name="delete_uploaded_files_on_uninstall" value="0">
                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                            <input type="checkbox" class="tasty-fonts-toggle-input" name="delete_uploaded_files_on_uninstall" value="1" <?php checked($deleteUploadedFilesOnUninstall); ?>>
                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                            <span class="tasty-fonts-toggle-copy">
                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Delete Uploaded Fonts on Uninstall', 'tasty-fonts'); ?></span>
                                                <?php if ($showSettingsDescriptions): ?>
                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Removes plugin-managed font files when the plugin is deleted.', 'tasty-fonts'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </section>
                        </form>
                        <?php if (!empty($updateChannelStatus['can_reinstall'])): ?>
                            <form id="tasty-fonts-reinstall-update-channel-form" method="post" class="tasty-fonts-settings-hidden-form">
                                <?php wp_nonce_field('tasty_fonts_reinstall_update_channel', '_tasty_fonts_reinstall_nonce'); ?>
                            </form>
                        <?php endif; ?>

                        <?php
                        $siteTransferAvailable = !empty($siteTransfer['available']);
                        $siteTransferMessage = trim((string) ($siteTransfer['message'] ?? ''));
                        $siteTransferExportUrl = (string) ($siteTransfer['export_url'] ?? '');
                        $siteTransferImportFileField = (string) ($siteTransfer['import_file_field'] ?? 'tasty_fonts_site_transfer_bundle');
                        $siteTransferImportGoogleField = (string) ($siteTransfer['import_google_api_key_field'] ?? 'tasty_fonts_import_google_api_key');
                        $siteTransferImportAction = (string) ($siteTransfer['import_action_field'] ?? 'tasty_fonts_import_site_transfer_bundle');
                        ?>
                        <section
                            id="tasty-fonts-settings-panel-transfer"
                            class="tasty-fonts-studio-panel"
                            data-tab-group="settings"
                            data-tab-panel="transfer"
                            role="tabpanel"
                            aria-labelledby="tasty-fonts-settings-tab-transfer"
                            hidden
                        >
                            <div class="tasty-fonts-output-settings-panel tasty-fonts-developer-panel">
                                <section class="tasty-fonts-site-transfer-panel" aria-labelledby="tasty-fonts-site-transfer-title">
                                    <div class="tasty-fonts-site-transfer-panel-head">
                                        <span class="tasty-fonts-eyebrow"><?php esc_html_e('Portable Setup', 'tasty-fonts'); ?></span>
                                        <div class="tasty-fonts-developer-tool-title-row tasty-fonts-site-transfer-title-row">
                                            <h3 id="tasty-fonts-site-transfer-title"><?php esc_html_e('Site Transfer', 'tasty-fonts'); ?></h3>
                                            <span class="tasty-fonts-badge"><?php esc_html_e('Settings + Fonts', 'tasty-fonts'); ?></span>
                                        </div>
                                        <?php if ($showSettingsDescriptions): ?>
                                            <p><?php esc_html_e('Move the full Tasty Fonts setup between sites with one portable bundle. Export from the source site, then import on the destination.', 'tasty-fonts'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tasty-fonts-site-transfer-grid">
                                        <article class="tasty-fonts-site-transfer-card tasty-fonts-site-transfer-card--export">
                                            <div class="tasty-fonts-site-transfer-card-top">
                                                <div class="tasty-fonts-developer-tool-title-row">
                                                    <h4><?php esc_html_e('Export Site Transfer Bundle', 'tasty-fonts'); ?></h4>
                                                    <span class="tasty-fonts-badge"><?php esc_html_e('Portable', 'tasty-fonts'); ?></span>
                                                </div>
                                                <?php if ($showSettingsDescriptions): ?>
                                                    <p><?php esc_html_e('Downloads a portable bundle you can import on another Tasty Fonts site.', 'tasty-fonts'); ?></p>
                                                <?php endif; ?>
                                                <ul class="tasty-fonts-site-transfer-points" aria-label="<?php esc_attr_e('Export bundle contents', 'tasty-fonts'); ?>">
                                                    <li><?php esc_html_e('Includes saved settings, live roles, library metadata, and managed font files.', 'tasty-fonts'); ?></li>
                                                    <li><?php esc_html_e('Excludes Google API keys, generated CSS, logs, and transient runtime state.', 'tasty-fonts'); ?></li>
                                                </ul>
                                                <?php if (!$siteTransferAvailable && $siteTransferMessage !== ''): ?>
                                                    <p class="tasty-fonts-site-transfer-note tasty-fonts-site-transfer-note--muted"><?php echo esc_html($siteTransferMessage); ?></p>
                                                <?php endif; ?>
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
                                                method="post"
                                                enctype="multipart/form-data"
                                                class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-site-transfer-form"
                                                data-developer-confirm-message="<?php echo esc_attr__('Replace the current Tasty Fonts settings, library, and managed files with the uploaded site transfer bundle?', 'tasty-fonts'); ?>"
                                                data-site-transfer-form
                                            >
                                                <?php wp_nonce_field($siteTransferImportAction); ?>
                                                <input type="hidden" name="<?php echo esc_attr($siteTransferImportAction); ?>" value="1">
                                                <div class="tasty-fonts-site-transfer-card-top">
                                                    <div class="tasty-fonts-developer-tool-title-row">
                                                        <h4><?php esc_html_e('Import Site Transfer Bundle', 'tasty-fonts'); ?></h4>
                                                    </div>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <p><?php esc_html_e('Imports a bundle exported from another Tasty Fonts site and replaces the current setup. Google Fonts API keys are never exported.', 'tasty-fonts'); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!$siteTransferAvailable && $siteTransferMessage !== ''): ?>
                                                        <p class="tasty-fonts-site-transfer-note tasty-fonts-site-transfer-note--muted"><?php echo esc_html($siteTransferMessage); ?></p>
                                                    <?php endif; ?>
                                                    <div class="tasty-fonts-site-transfer-import-grid">
                                                        <div class="tasty-fonts-site-transfer-field">
                                                            <span class="tasty-fonts-field-label"><?php esc_html_e('Transfer Bundle', 'tasty-fonts'); ?></span>
                                                            <div class="tasty-fonts-site-transfer-file-picker">
                                                                <input
                                                                    type="file"
                                                                    id="tasty-fonts-site-transfer-bundle-upload"
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
                                                                <label for="tasty-fonts-site-transfer-bundle-upload" class="button tasty-fonts-site-transfer-picker-trigger<?php echo !$siteTransferAvailable ? ' is-disabled' : ''; ?>">
                                                                    <?php esc_html_e('Choose File', 'tasty-fonts'); ?>
                                                                </label>
                                                            </div>
                                                            <span class="tasty-fonts-site-transfer-note"><?php esc_html_e('Choose a bundle exported from another Tasty Fonts site.', 'tasty-fonts'); ?></span>
                                                        </div>
                                                        <label for="tasty-fonts-site-transfer-google-api-key" class="tasty-fonts-site-transfer-field tasty-fonts-site-transfer-field--secret">
                                                            <span class="tasty-fonts-field-label"><?php esc_html_e('Google Fonts API Key', 'tasty-fonts'); ?></span>
                                                            <input
                                                                type="text"
                                                                id="tasty-fonts-site-transfer-google-api-key"
                                                                class="regular-text tasty-fonts-text-control tasty-fonts-site-transfer-input"
                                                                name="<?php echo esc_attr($siteTransferImportGoogleField); ?>"
                                                                value=""
                                                                placeholder="<?php echo esc_attr__('Optional on the destination site', 'tasty-fonts'); ?>"
                                                                <?php disabled(!$siteTransferAvailable); ?>
                                                            >
                                                            <span class="tasty-fonts-site-transfer-note"><?php esc_html_e('Add a key only if this site should keep Google search enabled.', 'tasty-fonts'); ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="tasty-fonts-site-transfer-card-foot tasty-fonts-site-transfer-card-foot--import">
                                                    <div class="tasty-fonts-site-transfer-danger-note">
                                                        <span><?php esc_html_e('Import replaces this site’s library, settings, and managed files.', 'tasty-fonts'); ?></span>
                                                    </div>
                                                    <button
                                                        type="submit"
                                                        class="button tasty-fonts-button-danger tasty-fonts-developer-action-button tasty-fonts-site-transfer-button"
                                                        data-site-transfer-submit
                                                        data-idle-label="<?php echo esc_attr__('Import Bundle', 'tasty-fonts'); ?>"
                                                        data-busy-label="<?php echo esc_attr__('Importing Bundle…', 'tasty-fonts'); ?>"
                                                        disabled
                                                    >
                                                        <?php esc_html_e('Import Bundle', 'tasty-fonts'); ?>
                                                    </button>
                                                </div>
                                                <div class="tasty-fonts-import-status tasty-fonts-site-transfer-status" data-site-transfer-status></div>
                                            </form>
                                        </article>
                                    </div>
                                </section>
                            </div>
                        </section>

                        <section
                            id="tasty-fonts-settings-panel-developer"
                            class="tasty-fonts-studio-panel"
                            data-tab-group="settings"
                            data-tab-panel="developer"
                            role="tabpanel"
                            aria-labelledby="tasty-fonts-settings-tab-developer"
                            hidden
                        >
                            <div class="tasty-fonts-output-settings-panel tasty-fonts-developer-panel">
                                <div class="tasty-fonts-output-settings-copy">
                                    <h3><?php esc_html_e('Developer Tools', 'tasty-fonts'); ?></h3>
                                </div>
                                <div class="tasty-fonts-output-settings-list tasty-fonts-developer-tool-sections">

                                    <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--developer tasty-fonts-settings-flat-row tasty-fonts-settings-flat-row--developer-inline">
                                        <div class="tasty-fonts-output-settings-submenu-copy tasty-fonts-settings-flat-row-copy">
                                            <div class="tasty-fonts-developer-tool-title-row">
                                                <h4><?php esc_html_e('Clear Plugin Caches and Regenerate Assets', 'tasty-fonts'); ?></h4>
                                                <span class="tasty-fonts-badge is-success"><?php esc_html_e('Maintenance', 'tasty-fonts'); ?></span>
                                            </div>
                                            <?php if ($showSettingsDescriptions): ?>
                                                <p><?php esc_html_e('Clears caches and rebuilds generated CSS.', 'tasty-fonts'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <form method="post" class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-settings-flat-row-form tasty-fonts-settings-flat-row-form--developer-inline">
                                            <?php wp_nonce_field('tasty_fonts_clear_plugin_caches'); ?>
                                            <div class="tasty-fonts-settings-flat-row-support tasty-fonts-settings-flat-row-support--developer">
                                                <div class="tasty-fonts-developer-action-row tasty-fonts-settings-flat-row-actions">
                                                    <button type="submit" class="button button-small tasty-fonts-developer-action-button" name="tasty_fonts_clear_plugin_caches" value="1"><?php esc_html_e('Clear Caches', 'tasty-fonts'); ?></button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--developer tasty-fonts-settings-flat-row tasty-fonts-settings-flat-row--developer-inline">
                                        <div class="tasty-fonts-output-settings-submenu-copy tasty-fonts-settings-flat-row-copy">
                                            <div class="tasty-fonts-developer-tool-title-row">
                                                <h4><?php esc_html_e('Regenerate Generated CSS', 'tasty-fonts'); ?></h4>
                                                <span class="tasty-fonts-badge is-success"><?php esc_html_e('Maintenance', 'tasty-fonts'); ?></span>
                                            </div>
                                            <?php if ($showSettingsDescriptions): ?>
                                                <p><?php esc_html_e('Rebuilds generated CSS without clearing caches.', 'tasty-fonts'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <form method="post" class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-settings-flat-row-form tasty-fonts-settings-flat-row-form--developer-inline">
                                            <?php wp_nonce_field('tasty_fonts_regenerate_css'); ?>
                                            <div class="tasty-fonts-settings-flat-row-support tasty-fonts-settings-flat-row-support--developer">
                                                <div class="tasty-fonts-developer-action-row tasty-fonts-settings-flat-row-actions">
                                                    <button type="submit" class="button button-small tasty-fonts-developer-action-button" name="tasty_fonts_regenerate_css" value="1"><?php esc_html_e('Regenerate CSS', 'tasty-fonts'); ?></button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--developer tasty-fonts-settings-flat-row tasty-fonts-settings-flat-row--developer-inline">
                                        <div class="tasty-fonts-output-settings-submenu-copy tasty-fonts-settings-flat-row-copy">
                                            <div class="tasty-fonts-developer-tool-title-row">
                                                <h4><?php esc_html_e('Reset Suppressed Notices', 'tasty-fonts'); ?></h4>
                                                <span class="tasty-fonts-badge"><?php esc_html_e('Safe', 'tasty-fonts'); ?></span>
                                            </div>
                                            <?php if ($showSettingsDescriptions): ?>
                                                <p><?php esc_html_e('Shows dismissed notices again.', 'tasty-fonts'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <form method="post" class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-settings-flat-row-form tasty-fonts-settings-flat-row-form--developer-inline">
                                            <?php wp_nonce_field('tasty_fonts_reset_suppressed_notices'); ?>
                                            <div class="tasty-fonts-settings-flat-row-support tasty-fonts-settings-flat-row-support--developer">
                                                <div class="tasty-fonts-developer-action-row tasty-fonts-settings-flat-row-actions">
                                                    <button type="submit" class="button button-small tasty-fonts-developer-action-button" name="tasty_fonts_reset_suppressed_notices" value="1"><?php esc_html_e('Reset Notices', 'tasty-fonts'); ?></button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--developer tasty-fonts-settings-flat-row tasty-fonts-settings-flat-row--developer-danger">
                                        <div class="tasty-fonts-output-settings-submenu-copy tasty-fonts-settings-flat-row-copy">
                                            <div class="tasty-fonts-developer-tool-title-row">
                                                <h4><?php esc_html_e('Reset Plugin Settings', 'tasty-fonts'); ?></h4>
                                                <span class="tasty-fonts-badge is-danger"><?php esc_html_e('Destructive', 'tasty-fonts'); ?></span>
                                            </div>
                                            <?php if ($showSettingsDescriptions): ?>
                                                <p><?php esc_html_e('Resets settings, roles, keys, and notices while keeping the library and files.', 'tasty-fonts'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <form method="post" class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-settings-flat-row-form tasty-fonts-settings-flat-row-form--developer-danger" data-developer-confirm-message="<?php echo esc_attr__('Reset plugin settings to defaults while keeping the font library and files?', 'tasty-fonts'); ?>">
                                            <?php wp_nonce_field('tasty_fonts_reset_plugin_settings'); ?>
                                            <div class="tasty-fonts-settings-flat-row-support tasty-fonts-settings-flat-row-support--developer tasty-fonts-settings-flat-row-support--developer-danger">
                                                <div class="tasty-fonts-developer-action-row tasty-fonts-settings-flat-row-actions">
                                                    <button type="submit" class="button button-small tasty-fonts-button-danger tasty-fonts-developer-action-button" name="tasty_fonts_reset_plugin_settings" value="1"><?php esc_html_e('Reset Settings', 'tasty-fonts'); ?></button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--developer tasty-fonts-settings-flat-row tasty-fonts-settings-flat-row--developer-danger">
                                        <div class="tasty-fonts-output-settings-submenu-copy tasty-fonts-settings-flat-row-copy">
                                            <div class="tasty-fonts-developer-tool-title-row">
                                                <h4><?php esc_html_e('Wipe Managed Font Library', 'tasty-fonts'); ?></h4>
                                                <span class="tasty-fonts-badge is-danger"><?php esc_html_e('Irreversible', 'tasty-fonts'); ?></span>
                                            </div>
                                            <?php if ($showSettingsDescriptions): ?>
                                                <p><?php esc_html_e('Deletes managed fonts, clears the library, and rebuilds empty font storage.', 'tasty-fonts'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <form method="post" class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-settings-flat-row-form tasty-fonts-settings-flat-row-form--developer-danger" data-developer-confirm-message="<?php echo esc_attr__('Wipe the managed font library, remove managed files, and rebuild empty storage?', 'tasty-fonts'); ?>">
                                            <?php wp_nonce_field('tasty_fonts_wipe_managed_font_library'); ?>
                                            <div class="tasty-fonts-settings-flat-row-support tasty-fonts-settings-flat-row-support--developer tasty-fonts-settings-flat-row-support--developer-danger">
                                                <div class="tasty-fonts-developer-action-row tasty-fonts-settings-flat-row-actions">
                                                    <button type="submit" class="button button-small tasty-fonts-button-danger tasty-fonts-developer-action-button" name="tasty_fonts_wipe_managed_font_library" value="1"><?php esc_html_e('Wipe Library', 'tasty-fonts'); ?></button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--developer tasty-fonts-settings-flat-row tasty-fonts-settings-flat-row--developer-danger">
                                        <div class="tasty-fonts-output-settings-submenu-copy tasty-fonts-settings-flat-row-copy">
                                            <div class="tasty-fonts-developer-tool-title-row">
                                                <h4><?php esc_html_e('Reset Integration Detection State', 'tasty-fonts'); ?></h4>
                                                <span class="tasty-fonts-badge is-warning"><?php esc_html_e('Re-bootstrap', 'tasty-fonts'); ?></span>
                                            </div>
                                            <?php if ($showSettingsDescriptions): ?>
                                                <p><?php esc_html_e('Clears integration and ACSS sync state for a fresh bootstrap on next admin load.', 'tasty-fonts'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <form method="post" class="tasty-fonts-output-settings-form tasty-fonts-developer-tool-form tasty-fonts-settings-flat-row-form tasty-fonts-settings-flat-row-form--developer-danger" data-developer-confirm-message="<?php echo esc_attr__('Reset stored integration detection and ACSS bookkeeping?', 'tasty-fonts'); ?>">
                                            <?php wp_nonce_field('tasty_fonts_reset_integration_detection_state'); ?>
                                            <div class="tasty-fonts-settings-flat-row-support tasty-fonts-settings-flat-row-support--developer tasty-fonts-settings-flat-row-support--developer-danger">
                                                <div class="tasty-fonts-developer-action-row tasty-fonts-settings-flat-row-actions">
                                                    <button type="submit" class="button button-small tasty-fonts-button-danger tasty-fonts-developer-action-button" name="tasty_fonts_reset_integration_detection_state" value="1"><?php esc_html_e('Reset Integrations', 'tasty-fonts'); ?></button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </section>
