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
                                        tabindex="0"
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
                                        tabindex="0"
                                        aria-controls="tasty-fonts-settings-panel-plugin-behavior"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Behavior', 'tasty-fonts'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <?php $showSettingsDescriptions = !$trainingWheelsOff; ?>

                        <section
                            id="tasty-fonts-settings-panel-output-settings"
                            class="tasty-fonts-studio-panel is-active"
                            data-tab-group="settings"
                            data-tab-panel="output-settings"
                            role="tabpanel"
                            aria-labelledby="tasty-fonts-settings-tab-output-settings"
                        >
                            <div class="tasty-fonts-output-settings-panel">
                                <form method="post" class="tasty-fonts-output-settings-form" data-settings-autosave="output">
                                    <?php wp_nonce_field('tasty_fonts_save_settings'); ?>
                                    <input type="hidden" name="tasty_fonts_save_settings" value="1">
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
                                                        <p><?php esc_html_e('Use a preset for the common cases. Choose custom to open the detailed controls below with both outputs enabled by default.', 'tasty-fonts'); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="tasty-fonts-output-quick-options" role="radiogroup" aria-label="<?php esc_attr_e('Output quick mode', 'tasty-fonts'); ?>">
                                                    <?php foreach ([
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
                                            </div>

                                            <div id="tasty-fonts-advanced-output-controls" class="tasty-fonts-settings-panel tasty-fonts-output-settings-advanced-panel" <?php echo $advancedOutputControlsExpanded ? '' : 'hidden'; ?>>
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
                                </form>
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
                                    <?php if ($showSettingsDescriptions): ?>
                                        <p class="tasty-fonts-muted"><?php esc_html_e('Keep builder and framework integrations aligned with Tasty Fonts role variables.', 'tasty-fonts'); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="tasty-fonts-output-settings-list tasty-fonts-integrations-list">
                                    <div class="tasty-fonts-output-settings-quick tasty-fonts-output-settings-choice">
                                        <div class="tasty-fonts-output-settings-submenu-copy">
                                            <h4><?php echo esc_html((string) ($etchIntegration['title'] ?? __('Etch Canvas Bridge', 'tasty-fonts'))); ?></h4>
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
                                                    aria-controls="tasty-fonts-help-tooltip-layer"
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

                                <form method="post" class="tasty-fonts-output-settings-form tasty-fonts-integrations-form" data-settings-autosave="integrations">
                                    <?php wp_nonce_field('tasty_fonts_save_settings'); ?>
                                    <input type="hidden" name="tasty_fonts_save_settings" value="1">
                                    <div class="tasty-fonts-output-settings-list tasty-fonts-integrations-list">
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
                                                        <span class="tasty-fonts-toggle-title"><?php echo esc_html((string) ($gutenbergIntegration['title'] ?? __('Gutenberg Font Library', 'tasty-fonts'))); ?></span>
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
                                                >
                                                <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                <span class="tasty-fonts-toggle-copy">
                                                    <span class="tasty-fonts-toggle-title-line">
                                                        <span class="tasty-fonts-toggle-title"><?php echo esc_html((string) ($acssIntegration['title'] ?? __('Automatic.css', 'tasty-fonts'))); ?></span>
                                                        <span class="<?php echo esc_attr('tasty-fonts-badge' . $acssStatusBadgeClass); ?>">
                                                            <?php echo esc_html((string) ($acssIntegration['status_label'] ?? __('Off', 'tasty-fonts'))); ?>
                                                        </span>
                                                    </span>
                                                    <?php if ($showSettingsDescriptions && (string) ($acssIntegration['description'] ?? '') !== ''): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($acssIntegration['description'] ?? '')); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>

                                            <div class="tasty-fonts-output-settings-submenu tasty-fonts-output-settings-submenu--integration">
                                                <div class="tasty-fonts-output-settings-submenu-copy">
                                                    <h4><?php esc_html_e('Managed Mapping', 'tasty-fonts'); ?></h4>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <p><?php esc_html_e('Review the current Automatic.css font-family values alongside the managed heading/body variable mapping.', 'tasty-fonts'); ?></p>
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
                                                    </dl>
                                                </section>

                                                <section class="tasty-fonts-integration-group" aria-label="<?php esc_attr_e('Desired mapping', 'tasty-fonts'); ?>">
                                                    <span class="tasty-fonts-integration-group-title"><?php esc_html_e('Desired Mapping', 'tasty-fonts'); ?></span>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <p class="tasty-fonts-integration-group-description"><?php esc_html_e('Tasty Fonts manages only the two base ACSS font-family settings needed for heading and body text.', 'tasty-fonts'); ?></p>
                                                    <?php endif; ?>
                                                    <dl class="tasty-fonts-integration-kv-list">
                                                        <div class="tasty-fonts-integration-kv">
                                                            <dt><?php esc_html_e('Heading', 'tasty-fonts'); ?></dt>
                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) ($acssIntegration['desired'][\TastyFonts\Integrations\AcssIntegrationService::OPTION_HEADING_FONT_FAMILY] ?? 'var(--font-heading)')); ?></span></dd>
                                                        </div>
                                                        <div class="tasty-fonts-integration-kv">
                                                            <dt><?php esc_html_e('Body Text', 'tasty-fonts'); ?></dt>
                                                            <dd><span class="tasty-fonts-integration-code"><?php echo esc_html((string) ($acssIntegration['desired'][\TastyFonts\Integrations\AcssIntegrationService::OPTION_TEXT_FONT_FAMILY] ?? 'var(--font-body)')); ?></span></dd>
                                                        </div>
                                                    </dl>
                                                </section>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
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
                                    <?php if ($showSettingsDescriptions): ?>
                                        <p class="tasty-fonts-muted"><?php esc_html_e('Control optional roles, guidance, and uninstall cleanup.', 'tasty-fonts'); ?></p>
                                    <?php endif; ?>
                                </div>
                                <form method="post" class="tasty-fonts-output-settings-form" data-settings-autosave="behavior">
                                    <?php wp_nonce_field('tasty_fonts_save_settings'); ?>
                                    <input type="hidden" name="tasty_fonts_save_settings" value="1">
                                    <div class="tasty-fonts-output-settings-list">
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
                                </form>
                            </div>
                        </section>
                    </section>
