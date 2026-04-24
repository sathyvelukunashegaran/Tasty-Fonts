                    <section class="tasty-fonts-card tasty-fonts-settings-card" id="tasty-fonts-settings-page" aria-labelledby="tasty-fonts-settings-panel-title">
                        <div class="tasty-fonts-card-head tasty-fonts-card-head--activity">
                            <h2 class="screen-reader-text" id="tasty-fonts-settings-panel-title"><?php esc_html_e('Settings', 'tasty-fonts'); ?></h2>
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
                                </div>
                                <div class="tasty-fonts-settings-save-shell" data-settings-save-shell>
                                    <button type="submit" class="button tasty-fonts-tab-button tasty-fonts-settings-save-button" form="tasty-fonts-settings-form" data-settings-save-button disabled><?php esc_html_e('Save changes', 'tasty-fonts'); ?></button>
                                </div>
                            </div>
                        </div>

                        <?php $showSettingsDescriptions = !$trainingWheelsOff; ?>
                        <?php $updateChannel = isset($updateChannel) ? (string) $updateChannel : 'stable'; ?>
                        <?php $updateChannelOptions = isset($updateChannelOptions) && is_array($updateChannelOptions) ? $updateChannelOptions : []; ?>
                        <?php $classOutputRoleStylesEnabled = !empty($classOutputRoleStylesEnabled); ?>
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
                                                    <span class="tasty-fonts-toggle-title"><?php esc_html_e('Preload Primary Fonts', 'tasty-fonts'); ?></span>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Loads active self-hosted heading and body fonts earlier for faster text rendering.', 'tasty-fonts'); ?></span>
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
                                                            ? __('Pick a preset. Minimal outputs only role variables, including --font-monospace. Custom opens the grouped controls below.', 'tasty-fonts')
                                                            : __('Pick a preset. Minimal outputs only the core role variables. Custom opens the grouped controls below.', 'tasty-fonts')); ?></p>
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

                                            <div id="tasty-fonts-advanced-output-controls" class="tasty-fonts-settings-panel tasty-fonts-output-settings-advanced-panel<?php echo $outputQuickMode === 'classes' ? ' is-classes-only' : ''; ?><?php echo $outputQuickMode === 'variables' ? ' is-variables-only' : ''; ?>" <?php echo $advancedOutputControlsExpanded ? '' : 'hidden'; ?>>
                                                <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--sitewide" data-output-detail-group="sitewide" <?php echo $outputQuickMode === 'custom' ? '' : 'hidden'; ?>>
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
                                                            <span class="tasty-fonts-toggle-title"><?php esc_html_e('Sitewide Role Weights', 'tasty-fonts'); ?></span>
                                                            <?php if ($showSettingsDescriptions): ?>
                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Adds font-weight to generated body, heading, and code rules. Utility classes stay separate.', 'tasty-fonts'); ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                </label>
                                                </div>
                                                <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--classes" data-output-detail-group="classes" <?php echo in_array($outputQuickMode, ['custom', 'classes'], true) ? '' : 'hidden'; ?>>
                                                <input type="hidden" name="class_output_enabled" value="0">
                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output" data-output-layer-master-row="classes" hidden style="display: none;">
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
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Utility Classes', 'tasty-fonts'); ?></span>
                                                        <?php if ($showSettingsDescriptions): ?>
                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Generates .font-* helper classes.', 'tasty-fonts'); ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                                <div class="tasty-fonts-output-settings-submenu<?php echo $classOutputEnabled ? '' : ' is-inactive'; ?>" data-output-panel="classes">
                                                    <div class="tasty-fonts-output-settings-submenu-copy">
                                                        <h4><?php esc_html_e('Utility Class Groups', 'tasty-fonts'); ?></h4>
                                                        <?php if ($showSettingsDescriptions): ?>
                                                            <p><?php esc_html_e('Choose which class groups are generated.', 'tasty-fonts'); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="tasty-fonts-output-settings-submenu-list">
                                                        <details class="tasty-fonts-output-settings-details tasty-fonts-output-settings-submenu-group--role-styling" data-output-role-class-styles-option <?php echo ($outputQuickMode === 'classes' || ($outputQuickMode === 'custom' && $classOutputEnabled)) ? '' : 'hidden'; ?>>
                                                            <summary>
                                                                <span><?php esc_html_e('Role Styling', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php esc_html_e('Weights and variation settings in role classes', 'tasty-fonts'); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
                                                                <input type="hidden" name="class_output_role_styles_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_styles_enabled" value="1" <?php checked($classOutputRoleStylesEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Role Weights in Classes', 'tasty-fonts'); ?></span>
                                                                        <?php if ($showSettingsDescriptions): ?>
                                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Adds role weights and variation settings to class output. Different from sitewide role weights.', 'tasty-fonts'); ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </details>
                                                        <details class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Role Classes', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php echo esc_html($monospaceRoleEnabled ? __('.font-heading, .font-body, .font-monospace', 'tasty-fonts') : __('.font-heading, .font-body', 'tasty-fonts')); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
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
                                                        </details>
                                                        <details class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Role Alias Classes', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php echo esc_html($monospaceRoleEnabled ? __('.font-interface, .font-ui, .font-code', 'tasty-fonts') : __('.font-interface, .font-ui', 'tasty-fonts')); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
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
                                                        </details>
                                                        <details class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Category Classes', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php echo esc_html($monospaceRoleEnabled ? __('.font-sans, .font-serif, .font-mono', 'tasty-fonts') : __('.font-sans, .font-serif', 'tasty-fonts')); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
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
                                                        </details>
                                                        <details class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Family Classes', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php esc_html_e('Per-family .font-* helpers', 'tasty-fonts'); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
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
                                                        </details>
                                                    </div>
                                                </div>
                                                </div>

                                                <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--variables" data-output-detail-group="variables" <?php echo in_array($outputQuickMode, ['custom', 'variables'], true) ? '' : 'hidden'; ?>>
                                                <input type="hidden" name="per_variant_font_variables_enabled" value="0">
                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output" data-output-layer-master-row="variables" hidden style="display: none;">
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
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('CSS Variables', 'tasty-fonts'); ?></span>
                                                        <?php if ($showSettingsDescriptions): ?>
                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Generates --font-* tokens for roles, aliases, categories, and weights.', 'tasty-fonts'); ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                                <div class="tasty-fonts-output-settings-submenu<?php echo $perVariantFontVariablesEnabled ? '' : ' is-inactive'; ?>" data-output-panel="variables">
                                                    <div class="tasty-fonts-output-settings-submenu-copy">
                                                        <h4><?php esc_html_e('CSS Variable Groups', 'tasty-fonts'); ?></h4>
                                                        <?php if ($showSettingsDescriptions): ?>
                                                            <p><?php esc_html_e('Choose which variable groups are generated.', 'tasty-fonts'); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="tasty-fonts-output-settings-submenu-list">
                                                        <details class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Role Weight Variables', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php echo esc_html($monospaceRoleEnabled ? __('--font-heading-weight, --font-body-weight, --font-monospace-weight', 'tasty-fonts') : __('--font-heading-weight, --font-body-weight', 'tasty-fonts')); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
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
                                                            </div>
                                                        </details>
                                                        <details class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Global Weight Tokens', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php esc_html_e('--weight-400, --weight-bold', 'tasty-fonts'); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
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
                                                            </div>
                                                        </details>
                                                        <details class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Role Alias Variables', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php echo esc_html($monospaceRoleEnabled ? __('--font-interface, --font-ui, --font-code', 'tasty-fonts') : __('--font-interface, --font-ui', 'tasty-fonts')); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
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
                                                            </div>
                                                        </details>
                                                        <details class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Category Aliases', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php echo esc_html($monospaceRoleEnabled ? __('--font-sans, --font-serif, --font-mono', 'tasty-fonts') : __('--font-sans, --font-serif', 'tasty-fonts')); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
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
                                                        </details>
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
                            $oxygenStatusHelp = trim((string) ($oxygenIntegration['status_copy'] ?? ''));
                            $oxygenBadgeClass = 'tasty-fonts-badge' . $oxygenStatusBadgeClass;
                            if (!$trainingWheelsOff && $oxygenStatusHelp !== '') {
                                $oxygenBadgeClass .= ' tasty-fonts-badge--interactive tasty-fonts-badge--help';
                            }
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
                                    <div class="tasty-fonts-output-settings-quick tasty-fonts-output-settings-choice tasty-fonts-integration-row--readonly">
                                        <div class="tasty-fonts-output-settings-submenu-copy">
                                            <span class="tasty-fonts-toggle-title-line tasty-fonts-integration-heading">
                                                <span class="tasty-fonts-integration-mark tasty-fonts-integration-mark--etch" aria-hidden="true"></span>
                                                <span><?php echo esc_html((string) ($etchIntegration['title'] ?? __('Etch Canvas Bridge', 'tasty-fonts'))); ?></span>
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
                                            </span>
                                            <?php if ($showSettingsDescriptions && (string) ($etchIntegration['description'] ?? '') !== ''): ?>
                                                <p><?php echo esc_html((string) ($etchIntegration['description'] ?? '')); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="tasty-fonts-output-settings-form tasty-fonts-integrations-form">
                                    <div class="tasty-fonts-output-settings-list tasty-fonts-integrations-list">
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
                                                </span>
                                            </label>

                                            <?php if (($bricksIntegration['available'] ?? false) === true): ?>
                                            <?php
                                            $bricksManagedControlsEnabled = !empty($bricksIntegration['theme_styles']['enabled'])
                                                  || !empty($bricksIntegration['google_fonts']['enabled']);
                                            $bricksThemeStyles = is_array($bricksIntegration['theme_styles'] ?? null) ? $bricksIntegration['theme_styles'] : [];
                                            $bricksGoogleFonts = is_array($bricksIntegration['google_fonts'] ?? null) ? $bricksIntegration['google_fonts'] : [];
                                            $bricksMappingVisible = !empty($bricksThemeStyles['enabled']) || !empty($bricksThemeStyles['applied']);
                                            $bricksThemeStylesUi = is_array($bricksThemeStyles['ui'] ?? null) ? $bricksThemeStyles['ui'] : [];
                                            $bricksThemeStyleTargeting = is_array($bricksThemeStylesUi['targeting'] ?? null) ? $bricksThemeStylesUi['targeting'] : [];
                                            $bricksThemeStyleDetails = is_array($bricksThemeStylesUi['details'] ?? null) ? $bricksThemeStylesUi['details'] : [];
                                            $bricksThemeStyleModes = is_array($bricksThemeStyleTargeting['mode_options'] ?? null) ? $bricksThemeStyleTargeting['mode_options'] : [];
                                            $bricksMaintenance = is_array($bricksIntegration['maintenance'] ?? null) ? $bricksIntegration['maintenance'] : [];
                                            $bricksAvailableThemeStyles = is_array($bricksThemeStyleTargeting['available_styles'] ?? null) ? $bricksThemeStyleTargeting['available_styles'] : [];
                                            $bricksTargetMode = trim((string) ($bricksThemeStyleTargeting['mode'] ?? \TastyFonts\Integrations\BricksIntegrationService::TARGET_MODE_MANAGED));
                                            $bricksTargetThemeStyleId = trim((string) ($bricksThemeStyleTargeting['selected_style_id'] ?? \TastyFonts\Integrations\BricksIntegrationService::MANAGED_THEME_STYLE_ID));
                                            ?>
                                            <div class="tasty-fonts-output-settings-submenu tasty-fonts-output-settings-submenu--integration">
                                                <div class="tasty-fonts-output-settings-submenu-list tasty-fonts-output-settings-submenu-list--integration tasty-fonts-output-settings-submenu-list--bricks">
                                                    <div class="tasty-fonts-bricks-managed-group">
                                                        <?php
                                                        $themeStylesUi = is_array($bricksThemeStyles['ui'] ?? null) ? $bricksThemeStyles['ui'] : [];
                                                        $themeStylesStatusHelp = trim((string) ($themeStylesUi['status_help'] ?? ''));
                                                        $themeStylesBadgeClass = (string) ($themeStylesUi['status_badge_class'] ?? 'tasty-fonts-badge');
                                                        if (!$trainingWheelsOff && $themeStylesStatusHelp !== '') {
                                                            $themeStylesBadgeClass .= ' tasty-fonts-badge--interactive tasty-fonts-badge--help';
                                                        }
                                                        $googleFontsUi = is_array($bricksGoogleFonts['ui'] ?? null) ? $bricksGoogleFonts['ui'] : [];
                                                        $googleFontsStatusHelp = trim((string) ($googleFontsUi['status_help'] ?? ''));
                                                        $googleFontsBadgeClass = (string) ($googleFontsUi['status_badge_class'] ?? 'tasty-fonts-badge');
                                                        if (!$trainingWheelsOff && $googleFontsStatusHelp !== '') {
                                                            $googleFontsBadgeClass .= ' tasty-fonts-badge--interactive tasty-fonts-badge--help';
                                                        }
                                                        ?>
                                                        <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration tasty-fonts-output-settings-detail-group--integration-card">
                                                            <input type="hidden" name="bricks_theme_styles_sync_enabled" value="0">
                                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration">
                                                                <input
                                                                    type="checkbox"
                                                                    class="tasty-fonts-toggle-input"
                                                                    name="bricks_theme_styles_sync_enabled"
                                                                    value="1"
                                                                    <?php checked(($bricksThemeStyles['enabled'] ?? false) === true); ?>
                                                                >
                                                                <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                <span class="tasty-fonts-toggle-copy">
                                                                    <span class="tasty-fonts-toggle-title-line">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Sync Bricks Theme Styles', 'tasty-fonts'); ?></span>
                                                                        <span class="<?php echo esc_attr($themeStylesBadgeClass); ?>" <?php $this->renderPassiveHelpAttributes($themeStylesStatusHelp); ?>>
                                                                            <?php echo esc_html((string) ($themeStylesUi['status_label'] ?? __('Off', 'tasty-fonts'))); ?>
                                                                        </span>
                                                                    </span>
                                                                    <?php if ($showSettingsDescriptions && (string) ($bricksThemeStyles['description'] ?? '') !== ''): ?>
                                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($bricksThemeStyles['description'] ?? '')); ?></span>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </label>

                                                            <section class="tasty-fonts-bricks-target-group" aria-label="<?php echo esc_attr((string) ($bricksThemeStyleTargeting['title'] ?? __('Theme Style', 'tasty-fonts'))); ?>">
                                                                <div class="tasty-fonts-bricks-target-copy">
                                                                    <span class="tasty-fonts-bricks-section-title"><?php echo esc_html((string) ($bricksThemeStyleTargeting['title'] ?? __('Theme Style', 'tasty-fonts'))); ?></span>
                                                                    <?php if ($showSettingsDescriptions && (string) ($bricksThemeStyleTargeting['copy'] ?? '') !== ''): ?>
                                                                        <p class="tasty-fonts-integration-group-description"><?php echo esc_html((string) ($bricksThemeStyleTargeting['copy'] ?? '')); ?></p>
                                                                    <?php endif; ?>
                                                                    <?php if ((string) ($bricksThemeStyleTargeting['summary_copy'] ?? '') !== ''): ?>
                                                                        <p class="tasty-fonts-integration-group-description"><?php echo esc_html((string) ($bricksThemeStyleTargeting['summary_copy'] ?? '')); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="tasty-fonts-bricks-target-controls">
                                                                    <div class="tasty-fonts-bricks-target-modes" role="radiogroup" aria-label="<?php esc_attr_e('Bricks Theme Style target mode', 'tasty-fonts'); ?>" data-bricks-theme-style-target-modes>
                                                                        <?php foreach ($bricksThemeStyleModes as $targetModeOption): ?>
                                                                            <?php if (!is_array($targetModeOption)) { continue; } ?>
                                                                            <?php $targetModeValue = (string) ($targetModeOption['value'] ?? ''); ?>
                                                                            <?php $targetModeLabel = (string) ($targetModeOption['label'] ?? $targetModeValue); ?>
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
                                                                    <div class="tasty-fonts-bricks-target-select-wrap" data-bricks-theme-style-target-select-wrap <?php echo !empty($bricksThemeStyleTargeting['select_visible']) ? '' : 'hidden'; ?>>
                                                                        <label class="tasty-fonts-inline-field tasty-fonts-inline-field--select tasty-fonts-bricks-target-select-field">
                                                                            <span class="tasty-fonts-field-label"><?php echo esc_html((string) ($bricksThemeStyleTargeting['select_label'] ?? __('Choose Theme Style', 'tasty-fonts'))); ?></span>
                                                                            <span class="tasty-fonts-select-field">
                                                                                <select
                                                                                    name="bricks_theme_style_target_id"
                                                                                    class="tasty-fonts-select"
                                                                                    data-bricks-theme-style-target-select
                                                                                    <?php disabled($bricksTargetMode !== \TastyFonts\Integrations\BricksIntegrationService::TARGET_MODE_SELECTED); ?>
                                                                                >
                                                                                    <?php if (empty($bricksThemeStyleTargeting['has_selectable_styles'])): ?>
                                                                                        <option value="<?php echo esc_attr(\TastyFonts\Integrations\BricksIntegrationService::MANAGED_THEME_STYLE_ID); ?>">
                                                                                            <?php echo esc_html((string) ($bricksThemeStyleTargeting['empty_select_label'] ?? __('No existing Bricks Theme Styles yet', 'tasty-fonts'))); ?>
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
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <?php if (!empty($bricksThemeStyleTargeting['show_create_action'])): ?>
                                                                        <div class="tasty-fonts-bricks-target-actions">
                                                                            <button
                                                                                type="submit"
                                                                                class="button button-small tasty-fonts-create-theme-style-button"
                                                                                name="bricks_create_theme_style"
                                                                                value="1"
                                                                                data-settings-force-submit
                                                                            >
                                                                                <?php esc_html_e('Create Tasty Theme Style', 'tasty-fonts'); ?>
                                                                            </button>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </section>
                                                        </div>

                                                        <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration tasty-fonts-output-settings-detail-group--integration-card">
                                                            <input type="hidden" name="bricks_disable_google_fonts_enabled" value="0">
                                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration">
                                                                <input
                                                                    type="checkbox"
                                                                    class="tasty-fonts-toggle-input"
                                                                    name="bricks_disable_google_fonts_enabled"
                                                                    value="1"
                                                                    <?php checked(($bricksGoogleFonts['enabled'] ?? false) === true); ?>
                                                                >
                                                                <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                <span class="tasty-fonts-toggle-copy">
                                                                    <span class="tasty-fonts-toggle-title-line">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Disable Bricks Google Fonts', 'tasty-fonts'); ?></span>
                                                                        <span class="<?php echo esc_attr($googleFontsBadgeClass); ?>" <?php $this->renderPassiveHelpAttributes($googleFontsStatusHelp); ?>>
                                                                            <?php echo esc_html((string) ($googleFontsUi['status_label'] ?? __('Off', 'tasty-fonts'))); ?>
                                                                        </span>
                                                                    </span>
                                                                    <?php if ($showSettingsDescriptions && (string) ($bricksGoogleFonts['description'] ?? '') !== ''): ?>
                                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($bricksGoogleFonts['description'] ?? '')); ?></span>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <?php if ($bricksMappingVisible): ?>
                                                        <details class="tasty-fonts-integration-details">
                                                            <summary><?php echo esc_html((string) ($bricksThemeStyleDetails['summary_label'] ?? __('Current vs managed values', 'tasty-fonts'))); ?></summary>
                                                            <div class="tasty-fonts-integration-details-body tasty-fonts-integration-details-body--two-column">
                                                                <section class="tasty-fonts-integration-group" aria-label="<?php esc_attr_e('Current Bricks managed values', 'tasty-fonts'); ?>">
                                                                    <span class="tasty-fonts-bricks-section-title"><?php echo esc_html((string) ($bricksThemeStyleDetails['current_title'] ?? __('Current values', 'tasty-fonts'))); ?></span>
                                                                    <?php if ((string) ($bricksThemeStyleDetails['current_intro'] ?? '') !== ''): ?>
                                                                        <div class="tasty-fonts-integration-inline-summary">
                                                                            <p><?php echo esc_html((string) ($bricksThemeStyleDetails['current_intro'] ?? '')); ?></p>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <dl class="tasty-fonts-integration-kv-list">
                                                                        <?php foreach ((array) ($bricksThemeStyleDetails['current_rows'] ?? []) as $currentRow): ?>
                                                                            <?php if (!is_array($currentRow)) { continue; } ?>
                                                                            <div class="tasty-fonts-integration-kv">
                                                                                <dt><?php echo esc_html((string) ($currentRow['label'] ?? '')); ?></dt>
                                                                                <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) ($currentRow['value'] ?? '')); ?></span></dd>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </dl>
                                                                </section>

                                                                <section class="tasty-fonts-integration-group" aria-label="<?php esc_attr_e('Target Bricks mapping', 'tasty-fonts'); ?>">
                                                                    <span class="tasty-fonts-bricks-section-title"><?php echo esc_html((string) ($bricksThemeStyleDetails['target_title'] ?? __('Target values', 'tasty-fonts'))); ?></span>
                                                                    <dl class="tasty-fonts-integration-kv-list">
                                                                        <?php foreach ((array) ($bricksThemeStyleDetails['desired_rows'] ?? []) as $desiredRow): ?>
                                                                            <?php if (!is_array($desiredRow)) { continue; } ?>
                                                                            <div class="tasty-fonts-integration-kv">
                                                                                <dt><?php echo esc_html((string) ($desiredRow['label'] ?? '')); ?></dt>
                                                                                <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) ($desiredRow['value'] ?? '')); ?></span></dd>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </dl>
                                                                </section>
                                                            </div>
                                                        </details>
                                                    <?php elseif (!$bricksManagedControlsEnabled): ?>
                                                        <div class="tasty-fonts-integration-note">
                                                            <?php esc_html_e('Enable Theme Style sync or Disable Bricks Google Fonts to see the current Bricks state here.', 'tasty-fonts'); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <details class="tasty-fonts-integration-details tasty-fonts-bricks-maintenance">
                                                        <summary><?php echo esc_html((string) ($bricksMaintenance['title'] ?? __('Maintenance', 'tasty-fonts'))); ?></summary>
                                                        <div class="tasty-fonts-bricks-maintenance-copy">
                                                            <?php if ($showSettingsDescriptions && (string) ($bricksMaintenance['copy'] ?? '') !== ''): ?>
                                                                <p class="tasty-fonts-integration-group-description"><?php echo esc_html((string) ($bricksMaintenance['copy'] ?? '')); ?></p>
                                                            <?php endif; ?>
                                                            <div class="tasty-fonts-bricks-maintenance-actions">
                                                                <?php if (!empty($bricksThemeStyleTargeting['show_delete_action'])): ?>
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
                                                    </details>
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
                                                        <span class="<?php echo esc_attr($oxygenBadgeClass); ?>" <?php $this->renderPassiveHelpAttributes($oxygenStatusHelp); ?>>
                                                            <?php echo esc_html((string) ($oxygenIntegration['status_label'] ?? __('Off', 'tasty-fonts'))); ?>
                                                        </span>
                                                    </span>
                                                    <?php if ($showSettingsDescriptions && (string) ($oxygenIntegration['description'] ?? '') !== ''): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($oxygenIntegration['description'] ?? '')); ?></span>
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
                                                        <span class="<?php echo esc_attr($gutenbergBadgeClass); ?>" <?php $this->renderPassiveHelpAttributes($gutenbergStatusHelp); ?>>
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
                                                        <span class="<?php echo esc_attr($acssBadgeClass); ?>" <?php $this->renderPassiveHelpAttributes($acssStatusHelp); ?>>
                                                            <?php echo esc_html((string) ($acssIntegration['status_label'] ?? __('Off', 'tasty-fonts'))); ?>
                                                        </span>
                                                    </span>
                                                    <?php if ($showSettingsDescriptions && (string) ($acssIntegration['description'] ?? '') !== ''): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($acssIntegration['description'] ?? '')); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>

                                            <?php if (($acssIntegration['enabled'] ?? false) === true && ($acssIntegration['available'] ?? false) === true): ?>
                                            <details class="tasty-fonts-integration-details tasty-fonts-acss-details">
                                                <summary><?php esc_html_e('Font mapping', 'tasty-fonts'); ?></summary>
                                                <div class="tasty-fonts-integration-details-body tasty-fonts-integration-details-body--two-column">
                                                    <section class="tasty-fonts-integration-group" aria-label="<?php esc_attr_e('Current Automatic.css values', 'tasty-fonts'); ?>">
                                                        <span class="tasty-fonts-integration-group-title"><?php esc_html_e('Current values', 'tasty-fonts'); ?></span>
                                                        <dl class="tasty-fonts-integration-kv-list">
                                                            <div class="tasty-fonts-integration-kv">
                                                                <dt><?php esc_html_e('Heading', 'tasty-fonts'); ?></dt>
                                                                <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) (($acssIntegration['current']['heading'] ?? '') !== '' ? $acssIntegration['current']['heading'] : __('empty', 'tasty-fonts'))); ?></span></dd>
                                                            </div>
                                                            <div class="tasty-fonts-integration-kv">
                                                                <dt><?php esc_html_e('Body', 'tasty-fonts'); ?></dt>
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

                                                    <section class="tasty-fonts-integration-group" aria-label="<?php esc_attr_e('Automatic.css target mapping', 'tasty-fonts'); ?>">
                                                        <span class="tasty-fonts-integration-group-title"><?php esc_html_e('Target values', 'tasty-fonts'); ?></span>
                                                        <dl class="tasty-fonts-integration-kv-list">
                                                            <div class="tasty-fonts-integration-kv">
                                                                <dt><?php esc_html_e('Heading', 'tasty-fonts'); ?></dt>
                                                                <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) ($acssIntegration['desired'][\TastyFonts\Integrations\AcssIntegrationService::OPTION_HEADING_FONT_FAMILY] ?? 'var(--font-heading)')); ?></span></dd>
                                                            </div>
                                                            <div class="tasty-fonts-integration-kv">
                                                                <dt><?php esc_html_e('Body', 'tasty-fonts'); ?></dt>
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
                                            </details>
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
                                <div class="tasty-fonts-output-settings-form tasty-fonts-settings-behavior-form<?php echo $showSettingsDescriptions ? '' : ' is-compact'; ?>">
                                <div class="tasty-fonts-output-settings-list">
                                    <div class="tasty-fonts-output-settings-detail-group tasty-fonts-settings-flat-row tasty-fonts-settings-flat-row--channel">
                                        <div class="tasty-fonts-output-settings-submenu-copy tasty-fonts-settings-flat-row-copy tasty-fonts-settings-flat-row-copy--channel">
                                            <h4><?php esc_html_e('Update Channel', 'tasty-fonts'); ?></h4>
                                            <?php if ($showSettingsDescriptions): ?>
                                                <p><?php esc_html_e('Choose which GitHub release rail this site follows.', 'tasty-fonts'); ?></p>
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
                                    <div class="tasty-fonts-settings-flat-row-form tasty-fonts-settings-flat-row-form--stack tasty-fonts-settings-behavior-stack">
                                        <input type="hidden" name="monospace_role_enabled" value="0">
                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                            <input type="checkbox" class="tasty-fonts-toggle-input" name="monospace_role_enabled" value="1" <?php checked($monospaceRoleEnabled); ?>>
                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                            <span class="tasty-fonts-toggle-copy">
                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable Monospace Role', 'tasty-fonts'); ?></span>
                                                <?php if ($showSettingsDescriptions): ?>
                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Adds a monospace role for code and pre.', 'tasty-fonts'); ?></span>
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
                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Allows variable font uploads and axis controls.', 'tasty-fonts'); ?></span>
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
                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Hides helper tips and info buttons.', 'tasty-fonts'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                        <input type="hidden" name="show_activity_log" value="0">
                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                            <input type="checkbox" class="tasty-fonts-toggle-input" name="show_activity_log" value="1" <?php checked($showActivityLog); ?>>
                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                            <span class="tasty-fonts-toggle-copy">
                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Show Activity Log', 'tasty-fonts'); ?></span>
                                                <?php if ($showSettingsDescriptions): ?>
                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Adds the full activity log to Advanced Tools. Events are still recorded when hidden.', 'tasty-fonts'); ?></span>
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
                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Deletes plugin-managed font files when uninstalling.', 'tasty-fonts'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    </div>
                                    <?php
                                    $adminAccessSummary = isset($adminAccessSummary) && is_array($adminAccessSummary) ? $adminAccessSummary : [];
                                    $adminAccessCustomEnabled = !empty($adminAccessCustomEnabled);
                                    $adminAccessRoleCount = max(0, (int) ($adminAccessSummary['role_count'] ?? count($adminAccessRoleSlugs)));
                                    $adminAccessRoleImpact = max(0, (int) ($adminAccessSummary['role_user_impact'] ?? 0));
                                    $adminAccessUserCount = max(0, (int) ($adminAccessSummary['user_count'] ?? count($adminAccessUserIds)));
                                    $adminAccessImplicitAdminCount = max(0, (int) ($adminAccessSummary['implicit_admin_count'] ?? 0));
                                    ?>
                                    <div class="tasty-fonts-output-settings-detail-group tasty-fonts-admin-access-panel<?php echo $showSettingsDescriptions ? '' : ' is-compact'; ?>">
                                        <input type="hidden" name="admin_access_custom_enabled" value="0" form="tasty-fonts-settings-form">
                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-admin-access-mode-toggle">
                                            <input
                                                type="checkbox"
                                                class="tasty-fonts-toggle-input"
                                                name="admin_access_custom_enabled"
                                                value="1"
                                                form="tasty-fonts-settings-form"
                                                data-admin-access-toggle
                                                aria-controls="tasty-fonts-admin-access-managed"
                                                aria-expanded="<?php echo $adminAccessCustomEnabled ? 'true' : 'false'; ?>"
                                                <?php checked($adminAccessCustomEnabled); ?>
                                            >
                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                            <span class="tasty-fonts-toggle-copy">
                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable custom access rules', 'tasty-fonts'); ?></span>
                                                <span class="tasty-fonts-admin-access-mode-copy"><?php esc_html_e('Off: only administrators can open Tasty Fonts. On: choose extra roles and users below.', 'tasty-fonts'); ?></span>
                                            </span>
                                        </label>
                                        <div class="tasty-fonts-admin-access-summary-bar<?php echo $showSettingsDescriptions ? '' : ' is-compact'; ?>">
                                            <p class="tasty-fonts-admin-access-summary-intro" data-admin-access-summary-state="disabled"<?php echo $adminAccessCustomEnabled ? ' hidden' : ''; ?>>
                                                <strong><?php esc_html_e('Administrators always have access.', 'tasty-fonts'); ?></strong>
                                                <span><?php esc_html_e('Turn custom access on to grant additional roles or users.', 'tasty-fonts'); ?></span>
                                            </p>
                                            <div class="tasty-fonts-admin-access-summary-intro" data-admin-access-summary-state="enabled"<?php echo $adminAccessCustomEnabled ? '' : ' hidden'; ?>>
                                                <span class="tasty-fonts-admin-access-summary-token tasty-fonts-admin-access-stat<?php echo $adminAccessRoleCount > 0 ? ' has-selection' : ''; ?>">
                                                    <strong data-admin-access-summary-count="roles"><?php echo esc_html(sprintf(_n('%d role', '%d roles', $adminAccessRoleCount, 'tasty-fonts'), $adminAccessRoleCount)); ?></strong>
                                                    <span class="tasty-fonts-admin-access-summary-token-label" data-admin-access-summary-copy="roles"><?php echo esc_html(sprintf(_n('%d user', '%d users', $adminAccessRoleImpact, 'tasty-fonts'), $adminAccessRoleImpact)); ?></span>
                                                </span>
                                                <span class="tasty-fonts-admin-access-summary-token tasty-fonts-admin-access-stat<?php echo $adminAccessUserCount > 0 ? ' has-selection' : ''; ?>">
                                                    <strong data-admin-access-summary-count="users"><?php echo esc_html(sprintf(_n('%d user', '%d users', $adminAccessUserCount, 'tasty-fonts'), $adminAccessUserCount)); ?></strong>
                                                    <span class="tasty-fonts-admin-access-summary-token-label"><?php esc_html_e('specific users', 'tasty-fonts'); ?></span>
                                                </span>
                                                <?php if ($adminAccessImplicitAdminCount > 0): ?>
                                                    <span class="tasty-fonts-admin-access-summary-token">
                                                        <strong><?php echo esc_html(sprintf(_n('%d admin', '%d admins', $adminAccessImplicitAdminCount, 'tasty-fonts'), $adminAccessImplicitAdminCount)); ?></strong>
                                                        <span class="tasty-fonts-admin-access-summary-token-label"><?php esc_html_e('implicit administrators', 'tasty-fonts'); ?></span>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div id="tasty-fonts-admin-access-managed" data-admin-access-managed<?php echo $adminAccessCustomEnabled ? '' : ' hidden'; ?>>
                                        <div class="tasty-fonts-admin-access-grid tasty-fonts-admin-access-edit-grid">
                                            <section class="tasty-fonts-admin-access-field" aria-labelledby="tasty-fonts-admin-access-roles-label" data-admin-access-group="roles">
                                                <div class="tasty-fonts-admin-access-field-head">
                                                    <div class="tasty-fonts-admin-access-copy">
                                                        <h5 id="tasty-fonts-admin-access-roles-label"><?php esc_html_e('Additional Roles', 'tasty-fonts'); ?></h5>
                                                        <p class="tasty-fonts-admin-access-note"><?php esc_html_e('Grant access to everyone in selected roles.', 'tasty-fonts'); ?></p>
                                                    </div>
                                                    <div class="tasty-fonts-admin-access-field-controls">
                                                        <p class="tasty-fonts-admin-access-field-summary" data-admin-access-selected-summary="roles"><?php echo esc_html(sprintf(_n('%1$d role selected · reaches %2$d user.', '%1$d roles selected · reaches %2$d users.', $adminAccessRoleCount, 'tasty-fonts'), $adminAccessRoleCount, $adminAccessRoleImpact)); ?></p>
                                                        <label class="tasty-fonts-admin-access-filter-toggle">
                                                            <input type="checkbox" data-admin-access-selected-only="roles">
                                                            <span><?php esc_html_e('Selected only', 'tasty-fonts'); ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="admin_access_role_slugs[]" value="" form="tasty-fonts-settings-form">
                                                <?php if ($adminAccessRoleOptions === []): ?>
                                                    <p class="tasty-fonts-admin-access-empty tasty-fonts-muted"><?php esc_html_e('No additional non-administrator roles are available on this site yet.', 'tasty-fonts'); ?></p>
                                                <?php else: ?>
                                                    <div class="tasty-fonts-admin-access-list tasty-fonts-admin-access-list--roles" role="group" aria-labelledby="tasty-fonts-admin-access-roles-label" data-admin-access-group="roles">
                                                        <?php foreach ($adminAccessRoleOptions as $option): ?>
                                                            <?php
                                                            $optionValue = sanitize_key((string) ($option['value'] ?? ''));
                                                            $optionLabel = trim((string) ($option['label'] ?? $optionValue));
                                                            $optionCount = max(0, (int) ($option['count'] ?? 0));
                                                            $optionMeta = trim((string) ($option['meta'] ?? ''));
                                                            $optionDisabled = !empty($option['disabled']);

                                                            if ($optionValue === '' || $optionLabel === '') {
                                                                continue;
                                                            }
                                                            ?>
                                                            <label class="tasty-fonts-inline-checkbox tasty-fonts-admin-access-checkbox tasty-fonts-admin-access-checkbox--role<?php echo $optionDisabled ? ' is-disabled' : ''; ?>"<?php echo $optionDisabled ? '' : ' data-admin-access-item data-admin-access-option="roles" data-admin-access-impact="' . esc_attr((string) $optionCount) . '"'; ?>>
                                                                <input
                                                                    type="checkbox"
                                                                    name="admin_access_role_slugs[]"
                                                                    value="<?php echo esc_attr($optionValue); ?>"
                                                                    form="tasty-fonts-settings-form"
                                                                    <?php checked(!$optionDisabled && in_array($optionValue, $adminAccessRoleSlugs, true)); ?>
                                                                    <?php disabled($optionDisabled); ?>
                                                                >
                                                                <span class="tasty-fonts-admin-access-checkbox-copy">
                                                                    <span class="tasty-fonts-admin-access-checkbox-title"><?php echo esc_html($optionLabel); ?></span>
                                                                    <span class="tasty-fonts-admin-access-checkbox-meta"><?php echo esc_html($optionMeta !== '' ? $optionMeta : sprintf(_n('%d user', '%d users', $optionCount, 'tasty-fonts'), $optionCount)); ?></span>
                                                                </span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </section>
                                            <section class="tasty-fonts-admin-access-field" aria-labelledby="tasty-fonts-admin-access-users-label" data-admin-access-group="users">
                                                <div class="tasty-fonts-admin-access-field-head">
                                                    <div class="tasty-fonts-admin-access-copy">
                                                        <h5 id="tasty-fonts-admin-access-users-label"><?php esc_html_e('Specific Users', 'tasty-fonts'); ?></h5>
                                                        <p class="tasty-fonts-admin-access-note"><?php esc_html_e('Grant access to selected users only.', 'tasty-fonts'); ?></p>
                                                    </div>
                                                    <div class="tasty-fonts-admin-access-field-controls">
                                                        <p class="tasty-fonts-admin-access-field-summary" data-admin-access-selected-summary="users"><?php echo esc_html(sprintf(_n('%d user selected.', '%d users selected.', $adminAccessUserCount, 'tasty-fonts'), $adminAccessUserCount)); ?></p>
                                                        <label class="tasty-fonts-admin-access-filter-toggle">
                                                            <input type="checkbox" data-admin-access-selected-only="users">
                                                            <span><?php esc_html_e('Selected only', 'tasty-fonts'); ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="admin_access_user_ids[]" value="" form="tasty-fonts-settings-form">
                                                <?php if ($adminAccessUserOptions === []): ?>
                                                    <p class="tasty-fonts-admin-access-empty tasty-fonts-muted"><?php esc_html_e('No site users are available for individual admin-access grants yet.', 'tasty-fonts'); ?></p>
                                                <?php else: ?>
                                                    <label class="screen-reader-text" for="tasty-fonts-admin-access-user-search"><?php esc_html_e('Filter users by name, login, or role', 'tasty-fonts'); ?></label>
                                                    <input
                                                        type="search"
                                                        id="tasty-fonts-admin-access-user-search"
                                                        class="tasty-fonts-text-control tasty-fonts-admin-access-search"
                                                        placeholder="<?php echo esc_attr__('Filter users by name, login, or role', 'tasty-fonts'); ?>"
                                                        data-admin-access-search="users"
                                                        autocomplete="off"
                                                        spellcheck="false"
                                                    >
                                                    <div class="tasty-fonts-admin-access-list tasty-fonts-admin-access-list--users" role="group" aria-labelledby="tasty-fonts-admin-access-users-label">
                                                        <?php foreach ($adminAccessUserOptions as $option): ?>
                                                            <?php
                                                            $optionValue = trim((string) ($option['value'] ?? ''));
                                                            $optionLabel = trim((string) ($option['label'] ?? $optionValue));
                                                            $optionMeta = trim((string) ($option['meta'] ?? ''));
                                                            $optionDisabled = !empty($option['disabled']);
                                                            $optionSearch = trim((string) ($option['search_text'] ?? strtolower($optionLabel . ' ' . $optionMeta)));

                                                            if ($optionValue === '' || $optionLabel === '') {
                                                                continue;
                                                            }
                                                            ?>
                                                            <label class="tasty-fonts-inline-checkbox tasty-fonts-admin-access-checkbox tasty-fonts-admin-access-checkbox--user<?php echo $optionDisabled ? ' is-disabled' : ''; ?>" data-admin-access-item data-admin-access-option="users" data-admin-access-search-text="<?php echo esc_attr($optionSearch); ?>">
                                                                <input
                                                                    type="checkbox"
                                                                    name="admin_access_user_ids[]"
                                                                    value="<?php echo esc_attr($optionValue); ?>"
                                                                    form="tasty-fonts-settings-form"
                                                                    <?php checked(!$optionDisabled && in_array($optionValue, $adminAccessUserIds, true)); ?>
                                                                    <?php disabled($optionDisabled); ?>
                                                                >
                                                                <span class="tasty-fonts-admin-access-checkbox-copy">
                                                                    <span class="tasty-fonts-admin-access-checkbox-title"><?php echo esc_html($optionLabel); ?></span>
                                                                    <?php if ($optionMeta !== ''): ?>
                                                                        <span class="tasty-fonts-admin-access-checkbox-meta"><?php echo esc_html($optionMeta); ?></span>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <p class="tasty-fonts-admin-access-empty tasty-fonts-muted" data-admin-access-empty="users" hidden><?php esc_html_e('No users match this filter yet.', 'tasty-fonts'); ?></p>
                                                <?php endif; ?>
                                            </section>
                                        </div>
                                        </div>
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

                    </section>
