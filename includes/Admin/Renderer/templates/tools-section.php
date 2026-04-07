                            <div id="tasty-fonts-role-advanced-panel" class="tasty-fonts-role-advanced-panel" hidden>
                                <div class="tasty-fonts-studio-switcher tasty-fonts-tab-list" role="tablist" aria-label="<?php esc_attr_e('Advanced Tools', 'tasty-fonts'); ?>" aria-orientation="horizontal">
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button is-active"
                                        id="tasty-fonts-studio-tab-snippets"
                                        data-tab-group="studio"
                                        data-tab-target="snippets"
                                        aria-selected="true"
                                        tabindex="0"
                                        aria-controls="tasty-fonts-studio-panel-snippets"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Snippets', 'tasty-fonts'); ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button"
                                        id="tasty-fonts-studio-tab-generated"
                                        data-tab-group="studio"
                                        data-tab-target="generated"
                                        aria-selected="false"
                                        tabindex="-1"
                                        aria-controls="tasty-fonts-studio-panel-generated"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Generated CSS', 'tasty-fonts'); ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button"
                                        id="tasty-fonts-studio-tab-system"
                                        data-tab-group="studio"
                                        data-tab-target="system"
                                        aria-selected="false"
                                        tabindex="-1"
                                        aria-controls="tasty-fonts-studio-panel-system"
                                        role="tab"
                                    >
                                        <?php esc_html_e('System Details', 'tasty-fonts'); ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button"
                                        id="tasty-fonts-studio-tab-output-settings"
                                        data-tab-group="studio"
                                        data-tab-target="output-settings"
                                        aria-selected="false"
                                        tabindex="-1"
                                        aria-controls="tasty-fonts-studio-panel-output-settings"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Output Settings', 'tasty-fonts'); ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button"
                                        id="tasty-fonts-studio-tab-plugin-behavior"
                                        data-tab-group="studio"
                                        data-tab-target="plugin-behavior"
                                        aria-selected="false"
                                        tabindex="-1"
                                        aria-controls="tasty-fonts-studio-panel-plugin-behavior"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Plugin Behavior', 'tasty-fonts'); ?>
                                    </button>
                                </div>

                                <section
                                    id="tasty-fonts-studio-panel-snippets"
                                    class="tasty-fonts-studio-panel is-active"
                                    data-tab-group="studio"
                                    data-tab-panel="snippets"
                                    role="tabpanel"
                                    aria-labelledby="tasty-fonts-studio-tab-snippets"
                                >
                                    <div class="tasty-fonts-code-card tasty-fonts-code-card--embedded">
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
                                </section>

                                <section
                                    id="tasty-fonts-studio-panel-generated"
                                    class="tasty-fonts-studio-panel"
                                    data-tab-group="studio"
                                    data-tab-panel="generated"
                                    role="tabpanel"
                                    aria-labelledby="tasty-fonts-studio-tab-generated"
                                    hidden
                                >
                                    <div class="tasty-fonts-code-card tasty-fonts-code-card--embedded">
                                        <div class="tasty-fonts-code-panel is-active">
                                            <?php
                                            $this->renderCodeEditor($generatedCssPanel, [
                                                'preserve_display_format' => true,
                                                'allow_readable_toggle' => $minifyCssOutput,
                                            ]);
                                            ?>
                                        </div>
                                    </div>
                                </section>

                                <section
                                    id="tasty-fonts-studio-panel-system"
                                    class="tasty-fonts-studio-panel"
                                    data-tab-group="studio"
                                    data-tab-panel="system"
                                    role="tabpanel"
                                    aria-labelledby="tasty-fonts-studio-tab-system"
                                    hidden
                                >
                                    <div class="tasty-fonts-system-details-panel">
                                        <div class="tasty-fonts-diagnostics-grid">
                                            <?php foreach ($diagnosticItems as $item): ?>
                                                <div class="tasty-fonts-diagnostic-item">
                                                    <div class="tasty-fonts-diagnostic-label"><?php echo esc_html((string) ($item['label'] ?? '')); ?></div>
                                                    <div class="<?php echo !empty($item['code']) ? 'tasty-fonts-diagnostic-value tasty-fonts-code' : 'tasty-fonts-diagnostic-value'; ?>">
                                                        <?php echo esc_html((string) ($item['value'] ?? '')); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>

                                <section
                                    id="tasty-fonts-studio-panel-output-settings"
                                    class="tasty-fonts-studio-panel"
                                    data-tab-group="studio"
                                    data-tab-panel="output-settings"
                                    role="tabpanel"
                                    aria-labelledby="tasty-fonts-studio-tab-output-settings"
                                    hidden
                                >
                                    <div class="tasty-fonts-output-settings-panel">
                                        <div class="tasty-fonts-output-settings-copy">
                                            <h3><?php esc_html_e('Output Settings', 'tasty-fonts'); ?></h3>
                                            <p class="tasty-fonts-muted"><?php esc_html_e('Choose how generated CSS is delivered. Role assignments stay unchanged.', 'tasty-fonts'); ?></p>
                                        </div>
                                        <form method="post" class="tasty-fonts-output-settings-form">
                                            <?php wp_nonce_field('tasty_fonts_save_settings'); ?>
                                            <input type="hidden" name="tasty_fonts_save_settings" value="1">
                                            <div class="tasty-fonts-output-settings-list">
                                                <label class="tasty-fonts-stack-field tasty-fonts-stack-field--output">
                                                    <?php $this->renderFieldLabel(__('CSS Delivery', 'tasty-fonts')); ?>
                                                    <span class="tasty-fonts-select-field">
                                                        <select id="tasty-fonts-css-delivery-mode" name="css_delivery_mode">
                                                            <?php foreach ($cssDeliveryModeOptions as $option): ?>
                                                                <option value="<?php echo esc_attr((string) ($option['value'] ?? '')); ?>" <?php selected($cssDeliveryMode, (string) ($option['value'] ?? '')); ?>>
                                                                    <?php echo esc_html((string) ($option['label'] ?? '')); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </span>
                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Choose whether the generated stylesheet loads as a file or is printed inline in the page head.', 'tasty-fonts'); ?></span>
                                                </label>
                                                <label class="tasty-fonts-stack-field tasty-fonts-stack-field--output">
                                                    <?php $this->renderFieldLabel(__('Default Font Display', 'tasty-fonts')); ?>
                                                    <span class="tasty-fonts-select-field">
                                                        <select id="tasty-fonts-font-display" name="font_display">
                                                            <?php foreach ($fontDisplayOptions as $option): ?>
                                                                <option value="<?php echo esc_attr((string) ($option['value'] ?? '')); ?>" <?php selected($fontDisplay, (string) ($option['value'] ?? '')); ?>>
                                                                    <?php echo esc_html((string) ($option['label'] ?? '')); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </span>
                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Default font-display value for generated @font-face rules.', 'tasty-fonts'); ?></span>
                                                </label>
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
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Minify generated CSS', 'tasty-fonts'); ?></span>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Keep on unless you need readable CSS while debugging.', 'tasty-fonts'); ?></span>
                                                    </span>
                                                </label>
                                                <div class="tasty-fonts-output-settings-quick" data-output-quick-mode-wrap>
                                                    <div class="tasty-fonts-output-settings-submenu-copy">
                                                        <h4><?php esc_html_e('Quick Mode', 'tasty-fonts'); ?></h4>
                                                        <p><?php esc_html_e('Use a preset for the common cases, or fine-tune classes and variables below.', 'tasty-fonts'); ?></p>
                                                    </div>
                                                    <div class="tasty-fonts-output-quick-options" role="radiogroup" aria-label="<?php esc_attr_e('Output quick mode', 'tasty-fonts'); ?>">
                                                        <?php foreach ([
                                                            'all' => __('All', 'tasty-fonts'),
                                                            'variables' => __('Variables only', 'tasty-fonts'),
                                                            'classes' => __('Classes only', 'tasty-fonts'),
                                                            'custom' => __('Custom', 'tasty-fonts'),
                                                        ] as $quickModeValue => $quickModeLabel): ?>
                                                            <label class="tasty-fonts-output-quick-option<?php echo $outputQuickMode === $quickModeValue ? ' is-active' : ''; ?>">
                                                                <input
                                                                    type="radio"
                                                                    name="tasty_fonts_output_quick_mode"
                                                                    value="<?php echo esc_attr($quickModeValue); ?>"
                                                                    data-output-quick-mode
                                                                    <?php checked($outputQuickMode, $quickModeValue); ?>
                                                                >
                                                                <span><?php echo esc_html($quickModeLabel); ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
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
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Emit font utility classes', 'tasty-fonts'); ?></span>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Adds direct utility classes like .font-heading, .font-sans, and per-family selectors.', 'tasty-fonts'); ?></span>
                                                    </span>
                                                </label>
                                                <div class="tasty-fonts-output-settings-submenu<?php echo $classOutputEnabled ? '' : ' is-inactive'; ?>" data-output-panel="classes">
                                                    <div class="tasty-fonts-output-settings-submenu-copy">
                                                        <h4><?php esc_html_e('Class Controls', 'tasty-fonts'); ?></h4>
                                                        <p><?php esc_html_e('Choose exactly which utility class groups should be generated.', 'tasty-fonts'); ?></p>
                                                    </div>
                                                    <div class="tasty-fonts-output-settings-submenu-list">
                                                        <div class="tasty-fonts-output-settings-submenu-group">
                                                            <span class="tasty-fonts-output-settings-submenu-group-title"><?php esc_html_e('Role classes', 'tasty-fonts'); ?></span>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list">
                                                                <input type="hidden" name="class_output_role_heading_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_heading_enabled" value="1" <?php checked($classOutputRoleHeadingEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Heading class', 'tasty-fonts'); ?></span>
                                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls .font-heading.', 'tasty-fonts'); ?></span>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="class_output_role_body_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_body_enabled" value="1" <?php checked($classOutputRoleBodyEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Body class', 'tasty-fonts'); ?></span>
                                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls .font-body.', 'tasty-fonts'); ?></span>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="class_output_role_monospace_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested<?php echo $monospaceRoleEnabled ? '' : ' is-disabled'; ?>">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_monospace_enabled" value="1" data-output-mono-dependent <?php checked($classOutputRoleMonospaceEnabled); ?> <?php disabled(!$monospaceRoleEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Monospace class', 'tasty-fonts'); ?></span>
                                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html($monospaceRoleEnabled ? __('Controls .font-monospace.', 'tasty-fonts') : __('Enable the monospace role to use .font-monospace.', 'tasty-fonts')); ?></span>
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="tasty-fonts-output-settings-submenu-group">
                                                            <span class="tasty-fonts-output-settings-submenu-group-title"><?php esc_html_e('Role alias classes', 'tasty-fonts'); ?></span>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list">
                                                                <input type="hidden" name="class_output_role_alias_interface_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_alias_interface_enabled" value="1" <?php checked($classOutputRoleAliasInterfaceEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Interface alias', 'tasty-fonts'); ?></span>
                                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls .font-interface.', 'tasty-fonts'); ?></span>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="class_output_role_alias_ui_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_alias_ui_enabled" value="1" <?php checked($classOutputRoleAliasUiEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('UI alias', 'tasty-fonts'); ?></span>
                                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls .font-ui.', 'tasty-fonts'); ?></span>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="class_output_role_alias_code_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested<?php echo $monospaceRoleEnabled ? '' : ' is-disabled'; ?>">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_alias_code_enabled" value="1" data-output-mono-dependent <?php checked($classOutputRoleAliasCodeEnabled); ?> <?php disabled(!$monospaceRoleEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Code alias', 'tasty-fonts'); ?></span>
                                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html($monospaceRoleEnabled ? __('Controls .font-code.', 'tasty-fonts') : __('Enable the monospace role to use .font-code.', 'tasty-fonts')); ?></span>
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="tasty-fonts-output-settings-submenu-group">
                                                            <span class="tasty-fonts-output-settings-submenu-group-title"><?php esc_html_e('Category classes', 'tasty-fonts'); ?></span>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list">
                                                                <input type="hidden" name="class_output_category_sans_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_category_sans_enabled" value="1" <?php checked($classOutputCategorySansEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Sans class', 'tasty-fonts'); ?></span>
                                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls .font-sans.', 'tasty-fonts'); ?></span>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="class_output_category_serif_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_category_serif_enabled" value="1" <?php checked($classOutputCategorySerifEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Serif class', 'tasty-fonts'); ?></span>
                                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls .font-serif.', 'tasty-fonts'); ?></span>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="class_output_category_mono_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested<?php echo $monospaceRoleEnabled ? '' : ' is-disabled'; ?>">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_category_mono_enabled" value="1" data-output-mono-dependent <?php checked($classOutputCategoryMonoEnabled); ?> <?php disabled(!$monospaceRoleEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Mono class', 'tasty-fonts'); ?></span>
                                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html($monospaceRoleEnabled ? __('Controls .font-mono.', 'tasty-fonts') : __('Enable the monospace role to use .font-mono.', 'tasty-fonts')); ?></span>
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <input type="hidden" name="class_output_families_enabled" value="0">
                                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                            <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_families_enabled" value="1" <?php checked($classOutputFamiliesEnabled); ?>>
                                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                            <span class="tasty-fonts-toggle-copy">
                                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Per-family classes', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls selectors like .font-inter and .font-ibm-plex-serif.', 'tasty-fonts'); ?></span>
                                                            </span>
                                                        </label>
                                                    </div>
                                                </div>
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
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Emit font variables', 'tasty-fonts'); ?></span>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Adds semantic variables, role aliases, category aliases, and reusable global weight tokens.', 'tasty-fonts'); ?></span>
                                                    </span>
                                                </label>
                                                <div class="tasty-fonts-output-settings-submenu<?php echo $perVariantFontVariablesEnabled ? '' : ' is-inactive'; ?>" data-output-panel="variables">
                                                    <div class="tasty-fonts-output-settings-submenu-copy">
                                                        <h4><?php esc_html_e('Variable Controls', 'tasty-fonts'); ?></h4>
                                                        <p><?php esc_html_e('Keep the main toggle on, then disable only the variable groups you do not want emitted.', 'tasty-fonts'); ?></p>
                                                    </div>
                                                    <div class="tasty-fonts-output-settings-submenu-list">
                                                        <input type="hidden" name="extended_variable_weight_tokens_enabled" value="0">
                                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                            <input
                                                                type="checkbox"
                                                                class="tasty-fonts-toggle-input"
                                                                name="extended_variable_weight_tokens_enabled"
                                                                value="1"
                                                                <?php checked($extendedVariableWeightTokensEnabled); ?>
                                                            >
                                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                            <span class="tasty-fonts-toggle-copy">
                                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Global weight tokens', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls variables like --weight-400 and --weight-bold plus matching weight-based snippets.', 'tasty-fonts'); ?></span>
                                                            </span>
                                                        </label>
                                                        <input type="hidden" name="extended_variable_role_aliases_enabled" value="0">
                                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                            <input
                                                                type="checkbox"
                                                                class="tasty-fonts-toggle-input"
                                                                name="extended_variable_role_aliases_enabled"
                                                                value="1"
                                                                <?php checked($extendedVariableRoleAliasesEnabled); ?>
                                                            >
                                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                            <span class="tasty-fonts-toggle-copy">
                                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Role alias variables', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-toggle-description">
                                                                    <?php echo esc_html($monospaceRoleEnabled
                                                                        ? __('Controls aliases like --font-interface, --font-ui, and --font-code.', 'tasty-fonts')
                                                                        : __('Controls aliases like --font-interface and --font-ui.', 'tasty-fonts')); ?>
                                                                </span>
                                                            </span>
                                                        </label>
                                                        <div class="tasty-fonts-output-settings-submenu-group">
                                                            <span class="tasty-fonts-output-settings-submenu-group-title"><?php esc_html_e('Category aliases', 'tasty-fonts'); ?></span>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list">
                                                                <input type="hidden" name="extended_variable_category_sans_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input
                                                                        type="checkbox"
                                                                        class="tasty-fonts-toggle-input"
                                                                        name="extended_variable_category_sans_enabled"
                                                                        value="1"
                                                                        <?php checked($extendedVariableCategorySansEnabled); ?>
                                                                    >
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Sans alias', 'tasty-fonts'); ?></span>
                                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls --font-sans.', 'tasty-fonts'); ?></span>
                                                                    </span>
                                                                </label>
                                                                <input type="hidden" name="extended_variable_category_serif_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                    <input
                                                                        type="checkbox"
                                                                        class="tasty-fonts-toggle-input"
                                                                        name="extended_variable_category_serif_enabled"
                                                                        value="1"
                                                                        <?php checked($extendedVariableCategorySerifEnabled); ?>
                                                                    >
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Serif alias', 'tasty-fonts'); ?></span>
                                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls --font-serif.', 'tasty-fonts'); ?></span>
                                                                    </span>
                                                                </label>
                                                                <?php if ($monospaceRoleEnabled): ?>
                                                                    <input type="hidden" name="extended_variable_category_mono_enabled" value="0">
                                                                    <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested">
                                                                        <input
                                                                            type="checkbox"
                                                                            class="tasty-fonts-toggle-input"
                                                                            name="extended_variable_category_mono_enabled"
                                                                            value="1"
                                                                            data-output-mono-dependent
                                                                            <?php checked($extendedVariableCategoryMonoEnabled); ?>
                                                                        >
                                                                        <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                        <span class="tasty-fonts-toggle-copy">
                                                                            <span class="tasty-fonts-toggle-title"><?php esc_html_e('Mono alias', 'tasty-fonts'); ?></span>
                                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Controls --font-mono.', 'tasty-fonts'); ?></span>
                                                                        </span>
                                                                    </label>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
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
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Preload primary heading and body fonts', 'tasty-fonts'); ?></span>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Loads the active self-hosted heading and body fonts earlier for faster text rendering.', 'tasty-fonts'); ?></span>
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
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Remote connection hints', 'tasty-fonts'); ?></span>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Adds preconnect hints for live Google, Bunny, and Adobe deliveries.', 'tasty-fonts'); ?></span>
                                                    </span>
                                                </label>
                                            </div>
                                            <p class="tasty-fonts-output-settings-footnote tasty-fonts-muted"><strong><?php esc_html_e('After saving:', 'tasty-fonts'); ?></strong> <?php esc_html_e('Applies on the next CSS refresh.', 'tasty-fonts'); ?></p>
                                            <div class="tasty-fonts-output-settings-actions">
                                                <button type="submit" class="button"><?php esc_html_e('Save Output Settings', 'tasty-fonts'); ?></button>
                                            </div>
                                        </form>
                                    </div>
                                </section>

                                <section
                                    id="tasty-fonts-studio-panel-plugin-behavior"
                                    class="tasty-fonts-studio-panel"
                                    data-tab-group="studio"
                                    data-tab-panel="plugin-behavior"
                                    role="tabpanel"
                                    aria-labelledby="tasty-fonts-studio-tab-plugin-behavior"
                                    hidden
                                >
                                    <div class="tasty-fonts-output-settings-panel">
                                        <div class="tasty-fonts-output-settings-copy">
                                            <h3><?php esc_html_e('Plugin Behavior', 'tasty-fonts'); ?></h3>
                                            <p class="tasty-fonts-muted"><?php esc_html_e('Control editor sync, optional roles, guidance, and uninstall cleanup.', 'tasty-fonts'); ?></p>
                                        </div>
                                        <form method="post" class="tasty-fonts-output-settings-form">
                                            <?php wp_nonce_field('tasty_fonts_save_settings'); ?>
                                            <input type="hidden" name="tasty_fonts_save_settings" value="1">
                                            <div class="tasty-fonts-output-settings-list">
                                                <div class="tasty-fonts-output-settings-subsection">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Editor Integrations', 'tasty-fonts'); ?></span>
                                                </div>
                                                <input type="hidden" name="block_editor_font_library_sync_enabled" value="0">
                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                                    <input
                                                        type="checkbox"
                                                        class="tasty-fonts-toggle-input"
                                                        name="block_editor_font_library_sync_enabled"
                                                        value="1"
                                                        <?php checked($blockEditorFontLibrarySyncEnabled); ?>
                                                    >
                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                    <span class="tasty-fonts-toggle-copy">
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable Block Editor Font Library Sync', 'tasty-fonts'); ?></span>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Mirrors imported families into WordPress typography controls. Leave it off on local sites if editor sync is unreliable.', 'tasty-fonts'); ?></span>
                                                    </span>
                                                </label>
                                                <div class="tasty-fonts-output-settings-subsection">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Role Options', 'tasty-fonts'); ?></span>
                                                </div>
                                                <input type="hidden" name="monospace_role_enabled" value="0">
                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                                    <input
                                                        type="checkbox"
                                                        class="tasty-fonts-toggle-input"
                                                        name="monospace_role_enabled"
                                                        value="1"
                                                        <?php checked($monospaceRoleEnabled); ?>
                                                    >
                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                    <span class="tasty-fonts-toggle-copy">
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable Monospace Role', 'tasty-fonts'); ?></span>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Adds a saved role for code and pre, exposed as --font-monospace.', 'tasty-fonts'); ?></span>
                                                    </span>
                                                </label>
                                                <input type="hidden" name="training_wheels_off" value="0">
                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                                    <input
                                                        type="checkbox"
                                                        class="tasty-fonts-toggle-input"
                                                        name="training_wheels_off"
                                                        value="1"
                                                        <?php checked($trainingWheelsOff); ?>
                                                    >
                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                    <span class="tasty-fonts-toggle-copy">
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Hide Onboarding Hints', 'tasty-fonts'); ?></span>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Hides helper tips and extra info buttons.', 'tasty-fonts'); ?></span>
                                                    </span>
                                                </label>
                                                <div class="tasty-fonts-output-settings-subsection">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Uninstall Settings', 'tasty-fonts'); ?></span>
                                                </div>
                                                <input type="hidden" name="delete_uploaded_files_on_uninstall" value="0">
                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                                    <input
                                                        type="checkbox"
                                                        class="tasty-fonts-toggle-input"
                                                        name="delete_uploaded_files_on_uninstall"
                                                        value="1"
                                                        <?php checked($deleteUploadedFilesOnUninstall); ?>
                                                    >
                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                    <span class="tasty-fonts-toggle-copy">
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Delete uploaded fonts on uninstall', 'tasty-fonts'); ?></span>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Removes plugin-managed font files when the plugin is deleted.', 'tasty-fonts'); ?></span>
                                                    </span>
                                                </label>
                                            </div>
                                            <p class="tasty-fonts-output-settings-footnote tasty-fonts-muted"><strong><?php esc_html_e('After saving:', 'tasty-fonts'); ?></strong> <?php esc_html_e('Behavior changes apply on the next page load.', 'tasty-fonts'); ?></p>
                                            <div class="tasty-fonts-output-settings-actions">
                                                <button type="submit" class="button"><?php esc_html_e('Save Plugin Behavior', 'tasty-fonts'); ?></button>
                                            </div>
                                        </form>
                                    </div>
                                </section>
                            </div>
