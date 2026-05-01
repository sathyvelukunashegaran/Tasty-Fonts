                    <?php
                    $showSettingsDescriptions = !$trainingWheelsOff;
                    $settingsHeadings = [
	                        'output-settings' => [
	                            'title' => __('Output', 'tasty-fonts'),
	                            'description' => __('Control stylesheet delivery, font-face rules, loading hints, and generated token or class layers.', 'tasty-fonts'),
	                        ],
                        'integrations' => [
                            'title' => __('Integrations', 'tasty-fonts'),
                            'description' => __('Sync typography with Etch, builders, the WordPress font library, and Automatic.css.', 'tasty-fonts'),
                        ],
	                        'plugin-behavior' => [
	                            'title' => __('Behavior', 'tasty-fonts'),
								'description' => __('Configure import workflows, font capabilities, cleanup, and admin access.', 'tasty-fonts'),
	                        ],
	                    ];
	                    $outputDependencyStates = is_array($outputDependencyStates ?? null) ? $outputDependencyStates : [];
	                    $outputDependencyState = static function (string $key) use ($outputDependencyStates): array {
	                        $state = is_array($outputDependencyStates[$key] ?? null) ? $outputDependencyStates[$key] : [];

	                        return [
	                            'available' => !empty($state['available']),
	                            'help' => trim((string) ($state['help'] ?? '')),
	                        ];
	                    };
                            $outputTogglePreviews = is_array($outputTogglePreviews ?? null) ? $outputTogglePreviews : [];
                            $renderOutputTogglePreview = function (string $name) use ($outputTogglePreviews): void {
                                $preview = is_array($outputTogglePreviews[$name] ?? null) ? $outputTogglePreviews[$name] : [];

                                if ($preview === []) {
                                    return;
                                }

                                $label = trim((string) ($preview['label'] ?? __('Preview CSS', 'tasty-fonts')));
                                $css = (string) ($preview['css'] ?? '');
                                $emptyMessage = trim((string) ($preview['empty_message'] ?? __('No CSS is available for this option yet.', 'tasty-fonts')));
                                $isEmpty = !empty($preview['is_empty']) || trim($css) === '';
                                $safeName = preg_replace('/[^a-z0-9_-]+/', '-', strtolower(str_replace('_', '-', $name)));
                                $safeName = is_string($safeName) && $safeName !== '' ? trim($safeName, '-') : 'preview';
                                $codeId = 'tasty-fonts-output-toggle-preview-' . $safeName;
                                $summaryContext = preg_replace('/\s+CSS$/i', '', $label);
                                $summaryContext = is_string($summaryContext) && trim($summaryContext) !== '' ? trim($summaryContext) : $label;
                                $summaryLabel = sprintf(
                                    /* translators: %s: output setting name. */
                                    __('View CSS for %s', 'tasty-fonts'),
                                    $summaryContext
                                );
                                ?>
                                <details
                                    class="tasty-fonts-output-toggle-preview"
                                    data-output-toggle-preview="<?php echo esc_attr($name); ?>"
                                >
                                    <summary class="tasty-fonts-output-toggle-preview-summary" aria-label="<?php echo esc_attr($summaryLabel); ?>">
                                        <span class="tasty-fonts-output-toggle-preview-title"><?php esc_html_e('View CSS', 'tasty-fonts'); ?></span>
                                    </summary>
                                    <div class="tasty-fonts-output-toggle-preview-body">
                                        <p class="tasty-fonts-output-toggle-preview-empty" data-output-toggle-preview-empty<?php echo $isEmpty ? '' : ' hidden'; ?>><?php echo esc_html($emptyMessage); ?></p>
                                        <pre class="tasty-fonts-output tasty-fonts-output-toggle-preview-code-surface"<?php echo $isEmpty ? ' hidden' : ''; ?>><code
                                            id="<?php echo esc_attr($codeId); ?>"
                                            class="tasty-fonts-output-code"
                                            data-output-toggle-preview-code="<?php echo esc_attr($name); ?>"
                                        ><?php $this->renderHighlightedSnippet($css); ?></code></pre>
                                    </div>
                                </details>
                                <?php
                            };
	                    $renderOutputDependencyToggle = static function (
	                        string $name,
	                        bool $savedPreference,
	                        string $title,
	                        string $description,
	                        string $dependencyKey,
	                        string $descriptionId = ''
	                    ) use ($outputDependencyState, $renderOutputTogglePreview): void {
	                        $dependency = $outputDependencyState($dependencyKey);
	                        $available = !empty($dependency['available']);
	                        $help = trim((string) ($dependency['help'] ?? ''));
	                        $tooltip = $available || $help === '' ? $description : $help;
	                        $id = $descriptionId !== '' ? $descriptionId : 'tasty-fonts-' . str_replace('_', '-', $name) . '-dependency';
	                        $hiddenValue = !$available && $savedPreference ? '1' : '0';
	                        $labelClasses = 'tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested'
	                            . ($available ? '' : ' is-disabled is-disabled-by-dependency');
	                        ?>
	                        <label
	                            class="<?php echo esc_attr($labelClasses); ?>"
	                            data-output-dependency-row
	                            data-output-dependency-key="<?php echo esc_attr($dependencyKey); ?>"
	                            data-output-dependency-static-available="<?php echo $available ? '1' : '0'; ?>"
	                            data-settings-help-tooltip="<?php echo esc_attr($tooltip); ?>"
	                            <?php if ($help !== ''): ?>
	                                data-settings-dependency-tooltip="<?php echo esc_attr($help); ?>"
	                            <?php endif; ?>
	                        >
	                            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($hiddenValue); ?>" data-output-preference-hidden>
	                            <input
	                                type="checkbox"
	                                class="tasty-fonts-toggle-input"
	                                name="<?php echo esc_attr($name); ?>"
	                                value="1"
	                                data-output-dependency-control
	                                data-output-dependency-key="<?php echo esc_attr($dependencyKey); ?>"
	                                data-output-dependency-static-available="<?php echo $available ? '1' : '0'; ?>"
	                                data-settings-dependency-description="<?php echo esc_attr($id); ?>"
	                                <?php echo $available ? '' : 'aria-describedby="' . esc_attr($id) . '"'; ?>
	                                <?php checked($available && $savedPreference); ?>
	                                <?php disabled(!$available); ?>
	                            >
	                            <span id="<?php echo esc_attr($id); ?>" class="screen-reader-text"><?php echo esc_html($help); ?></span>
	                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
	                            <span class="tasty-fonts-toggle-copy">
	                                <span class="tasty-fonts-toggle-title"><?php echo esc_html($title); ?></span>
	                            </span>
	                        </label>
                                                <?php $renderOutputTogglePreview($name); ?>
	                        <?php
	                    };
	                    ?>
                    <section class="tasty-fonts-card tasty-fonts-settings-card" id="tasty-fonts-settings-page" aria-labelledby="tasty-fonts-settings-panel-title">
                        <div class="tasty-fonts-card-head tasty-fonts-card-head--activity">
                            <div class="tasty-fonts-diagnostics-context tasty-fonts-settings-context">
                                <h2 class="screen-reader-text" id="tasty-fonts-settings-panel-title"><?php esc_html_e('Settings', 'tasty-fonts'); ?></h2>
                                <?php foreach ($settingsHeadings as $headingKey => $heading): ?>
                                    <?php $isActiveHeading = $headingKey === 'output-settings'; ?>
                                    <div
                                        class="tasty-fonts-diagnostics-context-item tasty-fonts-settings-context-item <?php echo $isActiveHeading ? 'is-active' : ''; ?>"
                                        data-tab-heading-group="settings"
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
                                    <button type="button" class="button tasty-fonts-tab-button tasty-fonts-settings-clear-button" data-settings-clear-button data-settings-form-id="tasty-fonts-settings-form" disabled><?php esc_html_e('Clear changes', 'tasty-fonts'); ?></button>
                                    <button type="submit" class="button tasty-fonts-tab-button tasty-fonts-settings-save-button" form="tasty-fonts-settings-form" data-settings-save-button disabled><?php esc_html_e('Save changes', 'tasty-fonts'); ?></button>
                                </div>
                            </div>
                        </div>

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
	                                    <article class="tasty-fonts-health-board tasty-fonts-settings-board tasty-fonts-settings-board--output">
	                                        <section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Stylesheet delivery settings', 'tasty-fonts'); ?>">
	                                            <div class="tasty-fonts-health-group-head">
	                                                <h4><?php esc_html_e('Stylesheet Delivery', 'tasty-fonts'); ?></h4>
	                                                <span class="tasty-fonts-health-group-count"><?php esc_html_e('File or Inline', 'tasty-fonts'); ?></span>
	                                            </div>
	                                            <div class="tasty-fonts-output-settings-list tasty-fonts-settings-board-list">
                                            <div class="tasty-fonts-output-settings-quick tasty-fonts-output-settings-choice">
                                                <div class="tasty-fonts-output-settings-submenu-copy">
                                                    <h4><?php esc_html_e('CSS Delivery', 'tasty-fonts'); ?></h4>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <p><?php esc_html_e('Choose whether the generated stylesheet loads as a file or is printed inline in the page head.', 'tasty-fonts'); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="tasty-fonts-output-quick-options tasty-fonts-output-quick-options--proxy-source" role="radiogroup" aria-label="<?php esc_attr_e('CSS delivery', 'tasty-fonts'); ?>">
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
                                                <span class="tasty-fonts-select-field tasty-fonts-settings-row-select">
                                                    <select class="tasty-fonts-select" data-settings-radio-proxy="css_delivery_mode" aria-label="<?php esc_attr_e('CSS delivery', 'tasty-fonts'); ?>">
                                                        <?php foreach ($cssDeliveryModeOptions as $option): ?>
                                                            <?php $optionValue = (string) ($option['value'] ?? ''); ?>
                                                            <option value="<?php echo esc_attr($optionValue); ?>" <?php selected($cssDeliveryMode, $optionValue); ?>>
                                                                <?php echo esc_html((string) ($option['label'] ?? '')); ?>
                                                            </option>
                                                        <?php endforeach; ?>
	                                                    </select>
	                                                </span>
	                                            </div>
	                                            </div>
	                                        </section>
	                                        <section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Font face rule settings', 'tasty-fonts'); ?>">
	                                            <div class="tasty-fonts-health-group-head">
	                                                <h4><?php esc_html_e('Font Face Rules', 'tasty-fonts'); ?></h4>
	                                                <span class="tasty-fonts-health-group-count"><?php esc_html_e('Rendering Defaults', 'tasty-fonts'); ?></span>
	                                            </div>
	                                            <div class="tasty-fonts-output-settings-list tasty-fonts-settings-board-list">
	                                            <div class="tasty-fonts-output-settings-quick tasty-fonts-output-settings-choice">
	                                                <div class="tasty-fonts-output-settings-submenu-copy">
                                                    <h4><?php esc_html_e('Default Font Display', 'tasty-fonts'); ?></h4>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <p><?php esc_html_e('Default font-display value for generated @font-face rules.', 'tasty-fonts'); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="tasty-fonts-output-quick-options tasty-fonts-output-quick-options--proxy-source" role="radiogroup" aria-label="<?php esc_attr_e('Default font display', 'tasty-fonts'); ?>">
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
                                                <span class="tasty-fonts-select-field tasty-fonts-settings-row-select">
                                                    <select class="tasty-fonts-select" data-settings-radio-proxy="font_display" aria-label="<?php esc_attr_e('Default font display', 'tasty-fonts'); ?>">
                                                        <?php foreach ($fontDisplayOptions as $option): ?>
                                                            <?php $optionValue = (string) ($option['value'] ?? ''); ?>
                                                            <option value="<?php echo esc_attr($optionValue); ?>" <?php selected($fontDisplay, $optionValue); ?>>
                                                                <?php echo esc_html((string) ($option['label'] ?? '')); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </span>
                                            </div>
                                            <div class="tasty-fonts-output-settings-quick tasty-fonts-output-settings-choice">
                                                <div class="tasty-fonts-output-settings-submenu-copy">
                                                    <h4><?php esc_html_e('Unicode Range Output', 'tasty-fonts'); ?></h4>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <p><?php esc_html_e('Controls how unicode-range is emitted in generated output. This affects emitted CSS and editor payloads only, not the stored library metadata.', 'tasty-fonts'); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="tasty-fonts-output-quick-options tasty-fonts-output-quick-options--proxy-source" role="radiogroup" aria-label="<?php esc_attr_e('Unicode range output', 'tasty-fonts'); ?>">
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
                                                <span class="tasty-fonts-select-field tasty-fonts-settings-row-select">
                                                    <select class="tasty-fonts-select" data-settings-radio-proxy="unicode_range_mode" aria-label="<?php esc_attr_e('Unicode range output', 'tasty-fonts'); ?>">
                                                        <?php foreach ($unicodeRangeModeOptions as $option): ?>
                                                            <?php $optionValue = (string) ($option['value'] ?? ''); ?>
                                                            <option value="<?php echo esc_attr($optionValue); ?>" <?php selected($unicodeRangeMode, $optionValue); ?>>
                                                                <?php echo esc_html((string) ($option['label'] ?? '')); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </span>
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
	                                            </div>
	                                        </section>
											<section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Fallback font settings', 'tasty-fonts'); ?>">
												<div class="tasty-fonts-health-group-head">
													<h4><?php esc_html_e('Fallback Fonts', 'tasty-fonts'); ?></h4>
													<span class="tasty-fonts-health-group-count"><?php esc_html_e('Global Stacks', 'tasty-fonts'); ?></span>
												</div>
												<div class="tasty-fonts-output-settings-list tasty-fonts-settings-board-list">
													<div class="tasty-fonts-output-settings-text-field">
														<label class="tasty-fonts-output-settings-text-label" for="tasty-fonts-fallback-heading">
															<span class="tasty-fonts-output-settings-text-title"><?php esc_html_e('Heading Fallback', 'tasty-fonts'); ?></span>
															<?php if ($showSettingsDescriptions): ?>
																<span class="tasty-fonts-output-settings-text-description"><?php esc_html_e('Used by generated heading role variables and classes unless a family has its own fallback.', 'tasty-fonts'); ?></span>
															<?php endif; ?>
														</label>
														<?php $this->renderFallbackInput('fallback_heading', (string) $fallbackHeading, [
															'id' => 'tasty-fonts-fallback-heading',
															'placeholder' => \TastyFonts\Support\FontUtils::DEFAULT_ROLE_SANS_FALLBACK,
															'wrapper_class' => 'tasty-fonts-settings-row-select',
														]); ?>
													</div>
													<div class="tasty-fonts-output-settings-text-field">
														<label class="tasty-fonts-output-settings-text-label" for="tasty-fonts-fallback-body">
															<span class="tasty-fonts-output-settings-text-title"><?php esc_html_e('Body Fallback', 'tasty-fonts'); ?></span>
															<?php if ($showSettingsDescriptions): ?>
																<span class="tasty-fonts-output-settings-text-description"><?php esc_html_e('Used by generated body role variables, body classes, and default imported sans families.', 'tasty-fonts'); ?></span>
															<?php endif; ?>
														</label>
														<?php $this->renderFallbackInput('fallback_body', (string) $fallbackBody, [
															'id' => 'tasty-fonts-fallback-body',
															'placeholder' => \TastyFonts\Support\FontUtils::DEFAULT_ROLE_SANS_FALLBACK,
															'wrapper_class' => 'tasty-fonts-settings-row-select',
														]); ?>
													</div>
													<?php if ($monospaceRoleEnabled): ?>
														<div class="tasty-fonts-output-settings-text-field">
															<label class="tasty-fonts-output-settings-text-label" for="tasty-fonts-fallback-monospace">
																<span class="tasty-fonts-output-settings-text-title"><?php esc_html_e('Monospace Fallback', 'tasty-fonts'); ?></span>
																<?php if ($showSettingsDescriptions): ?>
																	<span class="tasty-fonts-output-settings-text-description"><?php esc_html_e('Used by generated monospace role variables and code-oriented imported families.', 'tasty-fonts'); ?></span>
																<?php endif; ?>
															</label>
															<?php $this->renderFallbackInput('fallback_monospace', (string) $fallbackMonospace, [
																'id' => 'tasty-fonts-fallback-monospace',
																'placeholder' => \TastyFonts\Support\FontUtils::DEFAULT_ROLE_MONOSPACE_FALLBACK,
																'wrapper_class' => 'tasty-fonts-settings-row-select',
															]); ?>
														</div>
													<?php else: ?>
														<input type="hidden" name="fallback_monospace" value="<?php echo esc_attr((string) $fallbackMonospace); ?>">
													<?php endif; ?>
													<?php $this->renderFallbackSuggestionList(); ?>
												</div>
											</section>
	                                        <section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Runtime loading settings', 'tasty-fonts'); ?>">
	                                            <div class="tasty-fonts-health-group-head">
	                                                <h4><?php esc_html_e('Runtime Loading', 'tasty-fonts'); ?></h4>
	                                                <span class="tasty-fonts-health-group-count"><?php esc_html_e('Performance Hints', 'tasty-fonts'); ?></span>
	                                            </div>
	                                            <div class="tasty-fonts-output-settings-list tasty-fonts-settings-board-list">
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
	                                            </div>
	                                        </section>
	                                        <section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Output layer settings', 'tasty-fonts'); ?>">
	                                            <div class="tasty-fonts-health-group-head">
	                                                <h4><?php esc_html_e('Output Layers', 'tasty-fonts'); ?></h4>
	                                                <span class="tasty-fonts-health-group-count"><?php esc_html_e('Variables & Classes', 'tasty-fonts'); ?></span>
	                                            </div>
	                                            <div class="tasty-fonts-output-settings-list tasty-fonts-settings-board-list">
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
                                                <div class="tasty-fonts-output-quick-options tasty-fonts-output-quick-options--proxy-source" role="radiogroup" aria-label="<?php esc_attr_e('Output quick mode', 'tasty-fonts'); ?>">
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
                                                <span class="tasty-fonts-select-field tasty-fonts-settings-row-select">
                                                    <select class="tasty-fonts-select" data-settings-radio-proxy="tasty_fonts_output_quick_mode" aria-label="<?php esc_attr_e('Output quick mode', 'tasty-fonts'); ?>">
                                                        <?php foreach ([
                                                            'minimal' => __('Minimal', 'tasty-fonts'),
                                                            'variables' => __('Variables only', 'tasty-fonts'),
                                                            'classes' => __('Classes only', 'tasty-fonts'),
                                                            'custom' => __('Custom', 'tasty-fonts'),
                                                        ] as $quickModeValue => $quickModeLabel): ?>
                                                            <option value="<?php echo esc_attr($quickModeValue); ?>" <?php selected($outputQuickMode, $quickModeValue); ?>>
                                                                <?php echo esc_html($quickModeLabel); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </span>
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
                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output" data-settings-help-tooltip="<?php esc_attr_e('Adds font-weight to generated body, heading, and code rules. Utility classes stay separate.', 'tasty-fonts'); ?>">
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
                                                        </span>
                                                </label>
												<?php $renderOutputTogglePreview('role_usage_font_weight_enabled'); ?>
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
                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Generates .font-* helper classes. Alias, category, and per-family helpers appear only when their source family exists.', 'tasty-fonts'); ?></span>
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
                                                        <details open class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Role Classes', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php echo esc_html($monospaceRoleEnabled ? __('.font-heading, .font-body, .font-monospace', 'tasty-fonts') : __('.font-heading, .font-body', 'tasty-fonts')); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
                                                                <input type="hidden" name="class_output_role_heading_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested" data-settings-help-tooltip="<?php esc_attr_e('Controls .font-heading.', 'tasty-fonts'); ?>">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_heading_enabled" value="1" <?php checked($classOutputRoleHeadingEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Heading Class', 'tasty-fonts'); ?></span>
                                                                    </span>
                                                                </label>
                                                                <?php $renderOutputTogglePreview('class_output_role_heading_enabled'); ?>
                                                                <input type="hidden" name="class_output_role_body_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested" data-settings-help-tooltip="<?php esc_attr_e('Controls .font-body.', 'tasty-fonts'); ?>">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="class_output_role_body_enabled" value="1" <?php checked($classOutputRoleBodyEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Body Class', 'tasty-fonts'); ?></span>
                                                                    </span>
                                                                </label>
                                                                <?php $renderOutputTogglePreview('class_output_role_body_enabled'); ?>
	                                                                <?php $renderOutputDependencyToggle('class_output_role_monospace_enabled', $classOutputRoleMonospaceEnabled, __('Monospace Class', 'tasty-fonts'), __('Controls .font-monospace.', 'tasty-fonts'), 'monospace-enabled', 'tasty-fonts-class-output-role-monospace-dependency'); ?>
                                                            </div>
                                                        </details>
                                                        <?php
                                                        $classOutputRoleStylesAvailable = $classOutputRoleHeadingEnabled && $classOutputRoleBodyEnabled;
                                                        $classOutputRoleStylesHelp = __('Turn on Heading Class and Body Class before adding role weights to class output.', 'tasty-fonts');
                                                        $classOutputRoleStylesDescription = __('Adds saved heading and body weights to .font-heading and .font-body. Different from sitewide role weights.', 'tasty-fonts');
                                                        ?>
                                                        <details open class="tasty-fonts-output-settings-details tasty-fonts-output-settings-submenu-group--role-styling" data-output-role-class-styles-option <?php echo ($outputQuickMode === 'classes' || ($outputQuickMode === 'custom' && $classOutputEnabled)) ? '' : 'hidden'; ?>>
                                                            <summary>
                                                                <span><?php esc_html_e('Role Styling', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php esc_html_e('Weights and variation settings in role classes', 'tasty-fonts'); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
                                                                <input type="hidden" name="class_output_role_styles_enabled" value="0" data-output-role-class-styles-hidden>
                                                                <label
                                                                    class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested<?php echo $classOutputRoleStylesAvailable ? '' : ' is-disabled is-disabled-by-dependency'; ?>"
                                                                    data-output-role-class-styles-row
                                                                    data-settings-dependency-tooltip="<?php echo esc_attr($classOutputRoleStylesHelp); ?>"
                                                                    data-settings-help-tooltip="<?php echo esc_attr($classOutputRoleStylesAvailable ? $classOutputRoleStylesDescription : $classOutputRoleStylesHelp); ?>"
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        class="tasty-fonts-toggle-input"
                                                                        name="class_output_role_styles_enabled"
                                                                        value="1"
                                                                        data-output-role-class-styles-control
                                                                        data-settings-dependency-description="tasty-fonts-class-output-role-styles-dependency"
                                                                        <?php checked($classOutputRoleStylesAvailable && $classOutputRoleStylesEnabled); ?>
                                                                        <?php disabled(!$classOutputRoleStylesAvailable); ?>
                                                                        <?php echo $classOutputRoleStylesAvailable ? '' : 'aria-describedby="tasty-fonts-class-output-role-styles-dependency"'; ?>
                                                                    >
                                                                    <span id="tasty-fonts-class-output-role-styles-dependency" class="screen-reader-text"><?php echo esc_html($classOutputRoleStylesHelp); ?></span>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Role Weights in Classes', 'tasty-fonts'); ?></span>
                                                                    </span>
                                                                </label>
                                                                <?php $renderOutputTogglePreview('class_output_role_styles_enabled'); ?>
                                                            </div>
                                                        </details>
                                                        <details open class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Role Alias Classes', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php echo esc_html($monospaceRoleEnabled ? __('.font-interface, .font-ui, .font-code', 'tasty-fonts') : __('.font-interface, .font-ui', 'tasty-fonts')); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
	                                                                <?php $renderOutputDependencyToggle('class_output_role_alias_interface_enabled', $classOutputRoleAliasInterfaceEnabled, __('Interface Alias', 'tasty-fonts'), __('Controls .font-interface when the Body role has an assigned family.', 'tasty-fonts'), 'body-family', 'tasty-fonts-class-output-role-alias-interface-dependency'); ?>
	                                                                <?php $renderOutputDependencyToggle('class_output_role_alias_ui_enabled', $classOutputRoleAliasUiEnabled, __('UI Alias', 'tasty-fonts'), __('Controls .font-ui when the Body role has an assigned family.', 'tasty-fonts'), 'body-family', 'tasty-fonts-class-output-role-alias-ui-dependency'); ?>
	                                                                <?php $renderOutputDependencyToggle('class_output_role_alias_code_enabled', $classOutputRoleAliasCodeEnabled, __('Code Alias', 'tasty-fonts'), __('Controls .font-code when the Monospace role has an assigned family.', 'tasty-fonts'), 'monospace-family', 'tasty-fonts-class-output-role-alias-code-dependency'); ?>
                                                            </div>
                                                        </details>
                                                        <details open class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Category Classes', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php echo esc_html($monospaceRoleEnabled ? __('.font-sans, .font-serif, .font-mono', 'tasty-fonts') : __('.font-sans, .font-serif', 'tasty-fonts')); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
	                                                                <?php $renderOutputDependencyToggle('class_output_category_sans_enabled', $classOutputCategorySansEnabled, __('Sans Class', 'tasty-fonts'), __('Controls .font-sans when a sans family is available.', 'tasty-fonts'), 'category-sans', 'tasty-fonts-class-output-category-sans-dependency'); ?>
	                                                                <?php $renderOutputDependencyToggle('class_output_category_serif_enabled', $classOutputCategorySerifEnabled, __('Serif Class', 'tasty-fonts'), __('Controls .font-serif when a serif family is available.', 'tasty-fonts'), 'category-serif', 'tasty-fonts-class-output-category-serif-dependency'); ?>
	                                                                <?php $renderOutputDependencyToggle('class_output_category_mono_enabled', $classOutputCategoryMonoEnabled, __('Mono Class', 'tasty-fonts'), __('Controls .font-mono when a monospace family is available.', 'tasty-fonts'), 'category-mono', 'tasty-fonts-class-output-category-mono-dependency'); ?>
                                                            </div>
                                                        </details>
                                                        <details open class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Family Classes', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php esc_html_e('Per-family .font-* helpers', 'tasty-fonts'); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
	                                                                <?php $renderOutputDependencyToggle('class_output_families_enabled', $classOutputFamiliesEnabled, __('Per-Family Classes', 'tasty-fonts'), __('Controls selectors like .font-inter and .font-ibm-plex-serif for available library families.', 'tasty-fonts'), 'family-library', 'tasty-fonts-class-output-families-dependency'); ?>
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
                                                            <span class="tasty-fonts-toggle-description"><?php esc_html_e('Generates --font-* tokens for roles, aliases, categories, and weights. Alias and category tokens appear only when their source family exists.', 'tasty-fonts'); ?></span>
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
                                                        <details open class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Role Weight Variables', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php echo esc_html($monospaceRoleEnabled ? __('--font-heading-weight, --font-body-weight, --font-monospace-weight', 'tasty-fonts') : __('--font-heading-weight, --font-body-weight', 'tasty-fonts')); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
                                                                <?php
                                                                $roleWeightVariableDescription = $monospaceRoleEnabled
                                                                    ? __('Controls variables like --font-heading-weight, --font-body-weight, and --font-monospace-weight.', 'tasty-fonts')
                                                                    : __('Controls variables like --font-heading-weight and --font-body-weight.', 'tasty-fonts');

                                                                if ($roleWeightVariablesLocked) {
                                                                    $roleWeightVariableDescription .= ' ' . __('Required while Automatic.css sync is on.', 'tasty-fonts');
                                                                }
                                                                ?>
																<input type="hidden" name="extended_variable_role_weight_vars_enabled" value="<?php echo $roleWeightVariablesLocked ? '1' : '0'; ?>">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested" data-settings-help-tooltip="<?php echo esc_attr($roleWeightVariableDescription); ?>">
																	<input type="checkbox" class="tasty-fonts-toggle-input" name="extended_variable_role_weight_vars_enabled" value="1" <?php checked($extendedVariableRoleWeightVarsEnabled); ?> <?php disabled($roleWeightVariablesLocked); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Role Weight Variables', 'tasty-fonts'); ?></span>
                                                                    </span>
                                                                </label>
                                                                <?php $renderOutputTogglePreview('extended_variable_role_weight_vars_enabled'); ?>
                                                            </div>
                                                        </details>
                                                        <details open class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Global Weight Tokens', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php esc_html_e('--weight-400, --weight-bold', 'tasty-fonts'); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
                                                                <input type="hidden" name="extended_variable_weight_tokens_enabled" value="0">
                                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--nested" data-settings-help-tooltip="<?php esc_attr_e('Controls variables like --weight-400 and --weight-bold plus matching weight-based snippets.', 'tasty-fonts'); ?>">
                                                                    <input type="checkbox" class="tasty-fonts-toggle-input" name="extended_variable_weight_tokens_enabled" value="1" <?php checked($extendedVariableWeightTokensEnabled); ?>>
                                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                                    <span class="tasty-fonts-toggle-copy">
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Global Weight Tokens', 'tasty-fonts'); ?></span>
                                                                    </span>
                                                                </label>
                                                                <?php $renderOutputTogglePreview('extended_variable_weight_tokens_enabled'); ?>
                                                            </div>
                                                        </details>
                                                        <details open class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Role Alias Variables', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php echo esc_html($monospaceRoleEnabled ? __('--font-interface, --font-ui, --font-code', 'tasty-fonts') : __('--font-interface, --font-ui', 'tasty-fonts')); ?></span>
                                                            </summary>
	                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
	                                                                <?php $renderOutputDependencyToggle('extended_variable_role_alias_interface_enabled', $extendedVariableRoleAliasInterfaceEnabled, __('Interface Alias', 'tasty-fonts'), __('Controls --font-interface when the Body role has an assigned family.', 'tasty-fonts'), 'body-family', 'tasty-fonts-extended-variable-role-alias-interface-dependency'); ?>
	                                                                <?php $renderOutputDependencyToggle('extended_variable_role_alias_ui_enabled', $extendedVariableRoleAliasUiEnabled, __('UI Alias', 'tasty-fonts'), __('Controls --font-ui when the Body role has an assigned family.', 'tasty-fonts'), 'body-family', 'tasty-fonts-extended-variable-role-alias-ui-dependency'); ?>
	                                                                <?php $renderOutputDependencyToggle('extended_variable_role_alias_code_enabled', $extendedVariableRoleAliasCodeEnabled, __('Code Alias', 'tasty-fonts'), __('Controls --font-code when the Monospace role has an assigned family.', 'tasty-fonts'), 'monospace-family', 'tasty-fonts-extended-variable-role-alias-code-dependency'); ?>
	                                                            </div>
                                                        </details>
                                                        <details open class="tasty-fonts-output-settings-details">
                                                            <summary>
                                                                <span><?php esc_html_e('Category Aliases', 'tasty-fonts'); ?></span>
                                                                <span class="tasty-fonts-output-settings-details-meta"><?php echo esc_html($monospaceRoleEnabled ? __('--font-sans, --font-serif, --font-mono', 'tasty-fonts') : __('--font-sans, --font-serif', 'tasty-fonts')); ?></span>
                                                            </summary>
                                                            <div class="tasty-fonts-output-settings-submenu-group-list tasty-fonts-output-settings-details-body">
	                                                                <?php $renderOutputDependencyToggle('extended_variable_category_sans_enabled', $extendedVariableCategorySansEnabled, __('Sans Alias', 'tasty-fonts'), __('Controls --font-sans when a sans family is available.', 'tasty-fonts'), 'category-sans', 'tasty-fonts-extended-variable-category-sans-dependency'); ?>
	                                                                <?php $renderOutputDependencyToggle('extended_variable_category_serif_enabled', $extendedVariableCategorySerifEnabled, __('Serif Alias', 'tasty-fonts'), __('Controls --font-serif when a serif family is available.', 'tasty-fonts'), 'category-serif', 'tasty-fonts-extended-variable-category-serif-dependency'); ?>
	                                                                <?php $renderOutputDependencyToggle('extended_variable_category_mono_enabled', $extendedVariableCategoryMonoEnabled, __('Mono Alias', 'tasty-fonts'), __('Controls --font-mono when a monospace family is available.', 'tasty-fonts'), 'category-mono', 'tasty-fonts-extended-variable-category-mono-dependency'); ?>
                                                            </div>
                                                        </details>
                                                    </div>
                                                </div>
                                            </div>
                                                </div>
                                            </div>
                                            </div>
                                    </section>
                                </article>
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
							$integrationControlDisabled = static fn (array $integration): bool => array_key_exists('control_disabled', $integration)
								? !empty($integration['control_disabled'])
								: (($integration['available'] ?? true) !== true);
							$integrationControlChecked = static fn (array $integration): bool => array_key_exists('control_checked', $integration)
								? !empty($integration['control_checked'])
								: (($integration['enabled'] ?? false) === true);
							$integrationSubmittedValue = static fn (array $integration): string => (string) ($integration['submitted_enabled_value'] ?? (!empty($integration['enabled']) ? '1' : '0'));
							$integrationHiddenFallbackValue = static fn (array $integration): string => $integrationControlDisabled($integration)
								? ($integrationSubmittedValue($integration) === '1' ? '1' : '0')
								: '0';

							$gutenbergStatus = (string) ($gutenbergIntegration['status'] ?? (!empty($gutenbergIntegration['enabled']) ? 'active' : 'disabled'));
							$gutenbergStatusClass = $gutenbergStatus === 'waiting_for_sitewide_roles'
								? ' is-warning'
								: (!empty($gutenbergIntegration['enabled']) ? ' is-success' : '');
                            $bricksStatus = (string) ($bricksIntegration['status'] ?? 'disabled');
							$bricksStatusBadgeClass = $bricksStatus === 'waiting_for_sitewide_roles' ? ' is-warning' : ($bricksStatus === 'active' ? ' is-success' : '');
							$bricksStatusHelp = trim((string) ($bricksIntegration['status_copy'] ?? ''));
							$bricksBadgeClass = 'tasty-fonts-badge' . $bricksStatusBadgeClass;
							$bricksBadgeInteractive = !$trainingWheelsOff && $bricksStatusHelp !== '';
							if ($bricksBadgeInteractive) {
								$bricksBadgeClass .= ' tasty-fonts-badge--interactive tasty-fonts-badge--help';
							}
                            $oxygenStatus = (string) ($oxygenIntegration['status'] ?? 'disabled');
							$oxygenStatusBadgeClass = $oxygenStatus === 'waiting_for_sitewide_roles' ? ' is-warning' : ($oxygenStatus === 'active' ? ' is-success' : '');
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

                            $etchStatusHelp = trim((string) ($etchIntegration['status_copy'] ?? ''));
                            $etchStatus = (string) ($etchIntegration['status'] ?? 'disabled');
							$etchStatusBadgeClass = $etchStatus === 'waiting_for_sitewide_roles' ? ' is-warning' : ($etchStatus === 'active' ? ' is-success' : '');
                            $etchBadgeClass = 'tasty-fonts-badge' . $etchStatusBadgeClass;
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
                            $acssControlDisabled = array_key_exists('control_disabled', $acssIntegration)
                                ? !empty($acssIntegration['control_disabled'])
                                : (($acssIntegration['available'] ?? true) !== true);
                            $acssControlChecked = array_key_exists('control_checked', $acssIntegration)
                                ? !empty($acssIntegration['control_checked'])
                                : (($acssIntegration['enabled'] ?? false) === true);
                            $acssSubmittedEnabledValue = (string) ($acssIntegration['submitted_enabled_value'] ?? '0');
                            $acssHiddenEnabledValue = $acssControlDisabled && $acssSubmittedEnabledValue === '1' ? '1' : '0';
                            $acssDependencyDescriptionId = 'tasty-fonts-acss-sitewide-dependency';
                            ?>
                            <div class="tasty-fonts-output-settings-panel tasty-fonts-integrations-panel">
                                <div class="tasty-fonts-output-settings-copy">
                                    <h3><?php esc_html_e('Integrations', 'tasty-fonts'); ?></h3>
                                </div>

                                <article class="tasty-fonts-health-board tasty-fonts-settings-board tasty-fonts-settings-board--integrations">
                                    <section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Builder integrations', 'tasty-fonts'); ?>">
                                        <div class="tasty-fonts-health-group-head">
                                            <h4><?php esc_html_e('Builders', 'tasty-fonts'); ?></h4>
                                            <span class="tasty-fonts-health-group-count"><?php esc_html_e('Canvas + Theme Controls', 'tasty-fonts'); ?></span>
                                        </div>
                                        <div class="tasty-fonts-output-settings-form tasty-fonts-integrations-form">
                                            <div class="tasty-fonts-output-settings-list tasty-fonts-integrations-list tasty-fonts-settings-board-list">
                                            <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration<?php echo $integrationControlDisabled($etchIntegration) ? ' is-disabled-by-dependency' : ''; ?>">
                                                <input type="hidden" name="etch_integration_enabled" value="<?php echo esc_attr($integrationHiddenFallbackValue($etchIntegration)); ?>">
                                                <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration" data-settings-help-tooltip="<?php echo esc_attr($etchStatusHelp); ?>">
                                                    <input
                                                        type="checkbox"
                                                        class="tasty-fonts-toggle-input"
                                                        name="etch_integration_enabled"
                                                        value="1"
                                                        <?php checked($integrationControlChecked($etchIntegration)); ?>
                                                        <?php disabled($integrationControlDisabled($etchIntegration)); ?>
                                                        <?php echo $integrationControlDisabled($etchIntegration) && $etchStatus === 'waiting_for_sitewide_roles' ? 'aria-describedby="tasty-fonts-etch-sitewide-dependency"' : ''; ?>
                                                    >
                                                    <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                    <span class="tasty-fonts-toggle-copy">
                                                        <span class="tasty-fonts-toggle-title-line">
                                                            <span class="tasty-fonts-toggle-title tasty-fonts-integration-title">
                                                                <span class="tasty-fonts-integration-mark tasty-fonts-integration-mark--etch" aria-hidden="true"></span>
                                                                <span><?php echo esc_html((string) ($etchIntegration['title'] ?? __('Etch Canvas Bridge', 'tasty-fonts'))); ?></span>
                                                            </span>
                                                            <?php if ($etchBadgeInteractive): ?>
                                                                <button
                                                                    type="button"
                                                                    class="<?php echo esc_attr($etchBadgeClass); ?>"
                                                                    <?php $this->renderPassiveHelpAttributes($etchStatusHelp); ?>
                                                                    aria-label="<?php esc_attr_e('Etch integration status', 'tasty-fonts'); ?>"
                                                                >
                                                                    <?php echo esc_html((string) ($etchIntegration['status_label'] ?? __('Off', 'tasty-fonts'))); ?>
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="<?php echo esc_attr($etchBadgeClass); ?>">
                                                                    <?php echo esc_html((string) ($etchIntegration['status_label'] ?? __('Off', 'tasty-fonts'))); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </span>
                                                        <?php if ($showSettingsDescriptions && (string) ($etchIntegration['description'] ?? '') !== ''): ?>
                                                            <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($etchIntegration['description'] ?? '')); ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                                <?php if ($integrationControlDisabled($etchIntegration) && $etchStatus === 'waiting_for_sitewide_roles'): ?>
                                                    <span id="tasty-fonts-etch-sitewide-dependency" class="screen-reader-text"><?php esc_html_e('Turn on Sitewide Delivery before Etch Canvas Bridge can load Tasty fonts.', 'tasty-fonts'); ?></span>
                                                <?php endif; ?>
												<?php
												$etchQuickPanelEnabled = !empty($etchIntegration['quick_panel_enabled']);
												$etchQuickPanelDisabled = $integrationControlDisabled($etchIntegration);
												$etchQuickPanelHiddenValue = $etchQuickPanelDisabled && $etchQuickPanelEnabled ? '1' : '0';
												$etchQuickPanelDescription = (string) ($etchIntegration['quick_panel_description'] ?? __('Shows the Tasty Fonts Aa panel in Etch’s left toolbar for immediate Heading, Body, and Monospace changes.', 'tasty-fonts'));
												$etchQuickPanelHelp = $etchQuickPanelDisabled ? $etchStatusHelp : $etchQuickPanelDescription;
												?>
												<div class="tasty-fonts-output-settings-submenu tasty-fonts-output-settings-submenu--integration<?php echo $etchQuickPanelDisabled ? ' is-inactive' : ''; ?>">
													<div class="tasty-fonts-output-settings-submenu-list tasty-fonts-output-settings-submenu-list--integration">
														<div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration-nested<?php echo $etchQuickPanelDisabled ? ' is-disabled-by-dependency' : ''; ?>" data-settings-help-tooltip="<?php echo esc_attr($etchQuickPanelHelp); ?>">
															<input type="hidden" name="etch_quick_roles_panel_enabled" value="<?php echo esc_attr($etchQuickPanelHiddenValue); ?>">
															<label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration">
																<input
																	type="checkbox"
																	class="tasty-fonts-toggle-input"
																	name="etch_quick_roles_panel_enabled"
																	value="1"
																	<?php checked($etchQuickPanelEnabled); ?>
																	<?php disabled($etchQuickPanelDisabled); ?>
																>
																<span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
																<span class="tasty-fonts-toggle-copy">
																	<span class="tasty-fonts-toggle-title-line">
																		<span class="tasty-fonts-toggle-title"><?php echo esc_html((string) ($etchIntegration['quick_panel_title'] ?? __('Quick role panel', 'tasty-fonts'))); ?></span>
																	</span>
																	<?php if ($showSettingsDescriptions): ?>
																		<span class="tasty-fonts-toggle-description"><?php echo esc_html($etchQuickPanelDescription); ?></span>
																	<?php endif; ?>
																</span>
															</label>
														</div>
													</div>
												</div>
                                            </div>

                                        <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration<?php echo $integrationControlDisabled($bricksIntegration) ? ' is-disabled-by-dependency' : ''; ?>">
                                            <input type="hidden" name="bricks_integration_enabled" value="<?php echo esc_attr($integrationHiddenFallbackValue($bricksIntegration)); ?>">
                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration" data-settings-help-tooltip="<?php echo esc_attr($bricksStatusHelp); ?>">
                                                <input
                                                    type="checkbox"
                                                    class="tasty-fonts-toggle-input"
                                                    name="bricks_integration_enabled"
                                                    value="1"
                                                    <?php checked($integrationControlChecked($bricksIntegration)); ?>
                                                    <?php disabled($integrationControlDisabled($bricksIntegration)); ?>
                                                    <?php echo $integrationControlDisabled($bricksIntegration) && $bricksStatus === 'waiting_for_sitewide_roles' ? 'aria-describedby="tasty-fonts-bricks-sitewide-dependency"' : ''; ?>
                                                >
                                                <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                <span class="tasty-fonts-toggle-copy">
                                                    <span class="tasty-fonts-toggle-title-line">
                                                        <span class="tasty-fonts-toggle-title tasty-fonts-integration-title">
                                                            <span class="tasty-fonts-integration-mark tasty-fonts-integration-mark--bricks" aria-hidden="true"></span>
                                                            <span><?php echo esc_html((string) ($bricksIntegration['title'] ?? __('Bricks Builder', 'tasty-fonts'))); ?></span>
                                                        </span>
                                                        <span class="<?php echo esc_attr($bricksBadgeClass); ?>" <?php $this->renderPassiveHelpAttributes($bricksStatusHelp); ?>>
                                                            <?php echo esc_html((string) ($bricksIntegration['status_label'] ?? __('Off', 'tasty-fonts'))); ?>
                                                        </span>
                                                    </span>
                                                    <?php if ($showSettingsDescriptions && (string) ($bricksIntegration['description'] ?? '') !== ''): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php echo esc_html((string) ($bricksIntegration['description'] ?? '')); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                            <?php if ($integrationControlDisabled($bricksIntegration) && $bricksStatus === 'waiting_for_sitewide_roles'): ?>
                                                <span id="tasty-fonts-bricks-sitewide-dependency" class="screen-reader-text"><?php esc_html_e('Turn on Sitewide Delivery before Bricks can receive Tasty typography.', 'tasty-fonts'); ?></span>
                                            <?php endif; ?>

                                            <?php if (($bricksIntegration['available'] ?? false) === true && !$integrationControlDisabled($bricksIntegration)): ?>
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
                                                                                class="button button-small tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--generate tasty-fonts-create-theme-style-button"
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
                                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Use Tasty Fonts in Bricks Pickers', 'tasty-fonts'); ?></span>
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
                                                            <?php esc_html_e('Enable Theme Style sync or Use Tasty Fonts in Bricks Pickers to see the current Bricks state here.', 'tasty-fonts'); ?>
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
                                                                        class="button button-secondary button-small tasty-fonts-button-danger tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--delete"
                                                                        name="bricks_delete_theme_style"
                                                                        value="1"
                                                                        data-settings-force-submit
                                                                    >
                                                                        <?php esc_html_e('Delete Tasty Theme Style', 'tasty-fonts'); ?>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button
                                                                    type="submit"
                                                                    class="button button-secondary button-small tasty-fonts-advanced-row-action tasty-fonts-advanced-row-action--restore"
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

										<div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration<?php echo $integrationControlDisabled($oxygenIntegration) ? ' is-disabled-by-dependency' : ''; ?>">
											<input type="hidden" name="oxygen_integration_enabled" value="<?php echo esc_attr($integrationHiddenFallbackValue($oxygenIntegration)); ?>">
                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration" data-settings-help-tooltip="<?php echo esc_attr($oxygenStatusHelp); ?>">
                                                <input
                                                    type="checkbox"
                                                    class="tasty-fonts-toggle-input"
                                                    name="oxygen_integration_enabled"
                                                    value="1"
													<?php checked($integrationControlChecked($oxygenIntegration)); ?>
													<?php disabled($integrationControlDisabled($oxygenIntegration)); ?>
													<?php echo $integrationControlDisabled($oxygenIntegration) && $oxygenStatus === 'waiting_for_sitewide_roles' ? 'aria-describedby="tasty-fonts-oxygen-sitewide-dependency"' : ''; ?>
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
                                            <?php if ($integrationControlDisabled($oxygenIntegration) && $oxygenStatus === 'waiting_for_sitewide_roles'): ?>
                                                <span id="tasty-fonts-oxygen-sitewide-dependency" class="screen-reader-text"><?php esc_html_e('Turn on Sitewide Delivery before Oxygen can receive Tasty fonts.', 'tasty-fonts'); ?></span>
                                            <?php endif; ?>
                                        </div>

                                            </div>
                                        </div>
                                    </section>

                                    <section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('WordPress integrations', 'tasty-fonts'); ?>">
                                        <div class="tasty-fonts-health-group-head">
                                            <h4><?php esc_html_e('WordPress', 'tasty-fonts'); ?></h4>
                                            <span class="tasty-fonts-health-group-count"><?php esc_html_e('Font Library', 'tasty-fonts'); ?></span>
                                        </div>
                                        <div class="tasty-fonts-output-settings-form tasty-fonts-integrations-form">
                                            <div class="tasty-fonts-output-settings-list tasty-fonts-integrations-list tasty-fonts-settings-board-list">
										<div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration<?php echo $integrationControlDisabled($gutenbergIntegration) ? ' is-disabled-by-dependency' : ''; ?>">
											<input type="hidden" name="block_editor_font_library_sync_enabled" value="<?php echo esc_attr($integrationHiddenFallbackValue($gutenbergIntegration)); ?>">
                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration" data-settings-help-tooltip="<?php echo esc_attr($gutenbergStatusHelp); ?>">
                                                <input
                                                    type="checkbox"
                                                    class="tasty-fonts-toggle-input"
                                                    name="block_editor_font_library_sync_enabled"
                                                    value="1"
													<?php checked($integrationControlChecked($gutenbergIntegration)); ?>
													<?php disabled($integrationControlDisabled($gutenbergIntegration)); ?>
													<?php echo $integrationControlDisabled($gutenbergIntegration) && $gutenbergStatus === 'waiting_for_sitewide_roles' ? 'aria-describedby="tasty-fonts-gutenberg-sitewide-dependency"' : ''; ?>
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
                                            <?php if ($integrationControlDisabled($gutenbergIntegration) && $gutenbergStatus === 'waiting_for_sitewide_roles'): ?>
                                                <span id="tasty-fonts-gutenberg-sitewide-dependency" class="screen-reader-text"><?php esc_html_e('Turn on Sitewide Delivery before Gutenberg Font Library sync can publish Tasty fonts.', 'tasty-fonts'); ?></span>
                                            <?php endif; ?>
                                        </div>

                                            </div>
                                        </div>
                                    </section>

                                    <section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Framework integrations', 'tasty-fonts'); ?>">
                                        <div class="tasty-fonts-health-group-head">
                                            <h4><?php esc_html_e('Frameworks', 'tasty-fonts'); ?></h4>
                                            <span class="tasty-fonts-health-group-count"><?php esc_html_e('Role Mapping', 'tasty-fonts'); ?></span>
                                        </div>
                                        <div class="tasty-fonts-output-settings-form tasty-fonts-integrations-form">
                                            <div class="tasty-fonts-output-settings-list tasty-fonts-integrations-list tasty-fonts-settings-board-list">
                                        <div class="tasty-fonts-output-settings-detail-group tasty-fonts-output-settings-detail-group--integration<?php echo $acssControlDisabled ? ' is-disabled-by-dependency' : ''; ?>">
                                            <input type="hidden" name="acss_font_role_sync_enabled" value="<?php echo esc_attr($acssHiddenEnabledValue); ?>">
                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output tasty-fonts-toggle-field--integration" data-settings-help-tooltip="<?php echo esc_attr($acssStatusHelp); ?>">
                                                <input
                                                    type="checkbox"
                                                    class="tasty-fonts-toggle-input"
                                                    name="acss_font_role_sync_enabled"
                                                    value="1"
                                                    <?php checked($acssControlChecked); ?>
                                                    <?php disabled($acssControlDisabled); ?>
                                                    <?php echo $acssControlDisabled && $acssStatus === 'waiting_for_sitewide_roles' ? 'aria-describedby="' . esc_attr($acssDependencyDescriptionId) . '"' : ''; ?>
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
                                            <?php if ($acssControlDisabled && $acssStatus === 'waiting_for_sitewide_roles'): ?>
                                                <span id="<?php echo esc_attr($acssDependencyDescriptionId); ?>" class="screen-reader-text"><?php esc_html_e('Turn on Sitewide Delivery before Automatic.css can receive Tasty Fonts role output.', 'tasty-fonts'); ?></span>
                                            <?php endif; ?>

                                            <?php if (($acssIntegration['enabled'] ?? false) === true && ($acssIntegration['available'] ?? false) === true && !$acssControlDisabled): ?>
                                            <?php
                                            $acssCurrent = is_array($acssIntegration['current'] ?? null) ? $acssIntegration['current'] : [];
                                            $acssDesired = is_array($acssIntegration['desired'] ?? null) ? $acssIntegration['desired'] : [];
                                            $acssMappingRows = [
                                                [
                                                    'label' => __('Heading', 'tasty-fonts'),
                                                    'current' => (string) ($acssCurrent['heading'] ?? ''),
                                                    'target' => (string) ($acssDesired[\TastyFonts\Integrations\AcssIntegrationService::OPTION_HEADING_FONT_FAMILY] ?? 'var(--font-heading)'),
                                                ],
                                                [
                                                    'label' => __('Body', 'tasty-fonts'),
                                                    'current' => (string) ($acssCurrent['body'] ?? ''),
                                                    'target' => (string) ($acssDesired[\TastyFonts\Integrations\AcssIntegrationService::OPTION_TEXT_FONT_FAMILY] ?? 'var(--font-body)'),
                                                ],
                                                [
                                                    'label' => __('Heading Weight', 'tasty-fonts'),
                                                    'current' => (string) ($acssCurrent['heading_weight'] ?? ''),
                                                    'target' => (string) ($acssDesired[\TastyFonts\Integrations\AcssIntegrationService::OPTION_HEADING_FONT_WEIGHT] ?? 'var(--font-heading-weight)'),
                                                ],
                                                [
                                                    'label' => __('Body Weight', 'tasty-fonts'),
                                                    'current' => (string) ($acssCurrent['body_weight'] ?? ''),
                                                    'target' => (string) ($acssDesired[\TastyFonts\Integrations\AcssIntegrationService::OPTION_TEXT_FONT_WEIGHT] ?? 'var(--font-body-weight)'),
                                                ],
                                            ];
                                            $acssMappingOutOfSync = $acssStatus === 'out_of_sync';
                                            ?>
                                            <div class="tasty-fonts-integration-mapping tasty-fonts-acss-mapping<?php echo $acssMappingOutOfSync ? ' is-out-of-sync' : ''; ?>" aria-label="<?php esc_attr_e('Automatic.css font mapping', 'tasty-fonts'); ?>">
                                                <?php if ($acssMappingOutOfSync): ?>
                                                    <div class="tasty-fonts-integration-mapping-notice">
                                                        <span class="tasty-fonts-integration-mapping-notice-copy">
                                                            <strong><?php esc_html_e('Needs reapply.', 'tasty-fonts'); ?></strong>
                                                            <?php esc_html_e('Automatic.css has values that differ from the Tasty mapping.', 'tasty-fonts'); ?>
                                                        </span>
                                                        <button type="submit" class="button tasty-fonts-tab-button tasty-fonts-integration-reapply-button" form="tasty-fonts-settings-form" data-settings-force-submit>
                                                            <?php esc_html_e('Reapply Tasty mapping', 'tasty-fonts'); ?>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="tasty-fonts-integration-mapping-head">
                                                    <span><?php esc_html_e('Font mapping', 'tasty-fonts'); ?></span>
                                                    <span><?php esc_html_e('Automatic.css value', 'tasty-fonts'); ?></span>
                                                    <span><?php esc_html_e('Tasty target', 'tasty-fonts'); ?></span>
                                                </div>
                                                <div class="tasty-fonts-integration-mapping-list">
                                                    <?php foreach ($acssMappingRows as $acssMappingRow): ?>
                                                        <?php
                                                        $acssCurrentValue = trim((string) ($acssMappingRow['current'] ?? ''));
                                                        $acssTargetValue = trim((string) ($acssMappingRow['target'] ?? ''));
                                                        $acssRowSynced = $acssCurrentValue === $acssTargetValue;
                                                        ?>
                                                        <div class="tasty-fonts-integration-mapping-row<?php echo $acssRowSynced ? ' is-synced' : ' is-out-of-sync'; ?>">
                                                            <span class="tasty-fonts-integration-mapping-label"><?php echo esc_html((string) $acssMappingRow['label']); ?></span>
                                                            <span class="tasty-fonts-integration-mapping-values" data-mobile-label="<?php esc_attr_e('Automatic.css value', 'tasty-fonts'); ?>">
                                                                <span class="tasty-fonts-integration-code<?php echo $acssRowSynced ? '' : ' is-out-of-sync'; ?>"><?php echo esc_html($acssCurrentValue !== '' ? $acssCurrentValue : __('Not set', 'tasty-fonts')); ?></span>
                                                                <?php if (!$acssRowSynced): ?>
                                                                    <span class="screen-reader-text"><?php esc_html_e('Does not match the Tasty target.', 'tasty-fonts'); ?></span>
                                                                <?php endif; ?>
                                                            </span>
                                                            <span class="tasty-fonts-integration-mapping-values" data-mobile-label="<?php esc_attr_e('Tasty target', 'tasty-fonts'); ?>">
                                                                <span class="tasty-fonts-integration-code"><?php echo esc_html($acssTargetValue); ?></span>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                            </div>
                                        </div>
                                    </section>
                                </article>
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
                                <article class="tasty-fonts-health-board tasty-fonts-settings-board tasty-fonts-settings-board--behavior">
                                    <section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Font capabilities', 'tasty-fonts'); ?>">
                                        <div class="tasty-fonts-health-group-head">
                                            <h4><?php esc_html_e('Font Capabilities', 'tasty-fonts'); ?></h4>
                                            <span class="tasty-fonts-health-group-count"><?php esc_html_e('Roles & Axes', 'tasty-fonts'); ?></span>
                                        </div>
                                        <div class="tasty-fonts-output-settings-list tasty-fonts-settings-board-list tasty-fonts-settings-behavior-stack">
                                        <?php
                                        $monospaceDisableWarning = __('Turning this off creates a rollback snapshot, clears monospace role assignments and monospace output choices, and removes monospace runtime CSS.', 'tasty-fonts');
                                        $variableDisableWarning = __('Turning this off creates a rollback snapshot, removes variable font deliveries/files, and clears saved axis settings.', 'tasty-fonts');
                                        ?>
                                        <input type="hidden" name="monospace_role_enabled" value="0">
                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output<?php echo $monospaceRoleEnabled ? ' is-risky' : ''; ?>" data-settings-help-tooltip="<?php echo esc_attr($monospaceRoleEnabled ? $monospaceDisableWarning : __('Adds a monospace role for code and pre.', 'tasty-fonts')); ?>">
                                            <input type="checkbox" class="tasty-fonts-toggle-input" name="monospace_role_enabled" value="1" <?php checked($monospaceRoleEnabled); ?>>
                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                            <span class="tasty-fonts-toggle-copy">
                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable Monospace Role', 'tasty-fonts'); ?></span>
                                                <?php if ($showSettingsDescriptions): ?>
                                                    <span class="tasty-fonts-toggle-description"><?php echo esc_html($monospaceRoleEnabled ? $monospaceDisableWarning : __('Adds a monospace role for code and pre.', 'tasty-fonts')); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                        <input type="hidden" name="variable_fonts_enabled" value="0">
                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output<?php echo !empty($variableFontsEnabled) ? ' is-risky' : ''; ?>" data-settings-help-tooltip="<?php echo esc_attr(!empty($variableFontsEnabled) ? $variableDisableWarning : __('Allows variable font uploads and axis controls.', 'tasty-fonts')); ?>">
                                            <input type="checkbox" class="tasty-fonts-toggle-input" name="variable_fonts_enabled" value="1" <?php checked(!empty($variableFontsEnabled)); ?>>
                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                            <span class="tasty-fonts-toggle-copy">
                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable Variable Fonts', 'tasty-fonts'); ?></span>
                                                <?php if ($showSettingsDescriptions): ?>
                                                    <span class="tasty-fonts-toggle-description"><?php echo esc_html(!empty($variableFontsEnabled) ? $variableDisableWarning : __('Allows variable font uploads and axis controls.', 'tasty-fonts')); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                        </div>
                                    </section>
									<section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Font import workflows', 'tasty-fonts'); ?>">
                                        <div class="tasty-fonts-health-group-head">
											<h4><?php esc_html_e('Font Import Workflows', 'tasty-fonts'); ?></h4>
											<span class="tasty-fonts-health-group-count"><?php esc_html_e('Provider Sources', 'tasty-fonts'); ?></span>
                                        </div>
                                        <div class="tasty-fonts-output-settings-list tasty-fonts-settings-board-list tasty-fonts-settings-behavior-stack">
										<input type="hidden" name="google_font_imports_enabled" value="0">
										<label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
											<input type="checkbox" class="tasty-fonts-toggle-input" name="google_font_imports_enabled" value="1" <?php checked(!empty($googleFontImportsEnabled)); ?>>
											<span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
											<span class="tasty-fonts-toggle-copy">
												<span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable Google Fonts Imports', 'tasty-fonts'); ?></span>
												<?php if ($showSettingsDescriptions): ?>
													<span class="tasty-fonts-toggle-description"><?php esc_html_e('Allows searching and importing Google Fonts families from Add Fonts.', 'tasty-fonts'); ?></span>
												<?php endif; ?>
											</span>
										</label>
										<input type="hidden" name="bunny_font_imports_enabled" value="0">
										<label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
											<input type="checkbox" class="tasty-fonts-toggle-input" name="bunny_font_imports_enabled" value="1" <?php checked(!empty($bunnyFontImportsEnabled)); ?>>
											<span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
											<span class="tasty-fonts-toggle-copy">
												<span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable Bunny Fonts Imports', 'tasty-fonts'); ?></span>
												<?php if ($showSettingsDescriptions): ?>
													<span class="tasty-fonts-toggle-description"><?php esc_html_e('Allows searching and importing Bunny Fonts families from Add Fonts.', 'tasty-fonts'); ?></span>
												<?php endif; ?>
											</span>
										</label>
										<input type="hidden" name="adobe_font_imports_enabled" value="0">
										<label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
											<input type="checkbox" class="tasty-fonts-toggle-input" name="adobe_font_imports_enabled" value="1" <?php checked(!empty($adobeFontImportsEnabled)); ?>>
											<span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
											<span class="tasty-fonts-toggle-copy">
												<span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable Adobe Fonts Imports', 'tasty-fonts'); ?></span>
												<?php if ($showSettingsDescriptions): ?>
													<span class="tasty-fonts-toggle-description"><?php esc_html_e('Allows connecting or resyncing an Adobe Fonts project from Add Fonts.', 'tasty-fonts'); ?></span>
												<?php endif; ?>
											</span>
										</label>
										<input type="hidden" name="local_font_uploads_enabled" value="0">
										<label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
											<input type="checkbox" class="tasty-fonts-toggle-input" name="local_font_uploads_enabled" value="1" <?php checked(!empty($localFontUploadsEnabled)); ?>>
											<span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
											<span class="tasty-fonts-toggle-copy">
												<span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable Custom Uploads', 'tasty-fonts'); ?></span>
												<?php if ($showSettingsDescriptions): ?>
													<span class="tasty-fonts-toggle-description"><?php esc_html_e('Allows uploading local WOFF and WOFF2 font files from Add Fonts.', 'tasty-fonts'); ?></span>
												<?php endif; ?>
											</span>
										</label>
                                        <?php if (!empty($customCssUrlImportsAvailable)): ?>
                                            <input type="hidden" name="custom_css_url_imports_enabled" value="0">
                                            <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output">
                                                <input type="checkbox" class="tasty-fonts-toggle-input" name="custom_css_url_imports_enabled" value="1" <?php checked(!empty($customCssUrlImportsEnabled)); ?>>
                                                <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                                <span class="tasty-fonts-toggle-copy">
                                                    <span class="tasty-fonts-toggle-title-line">
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable URL Imports', 'tasty-fonts'); ?></span>
                                                        <span class="tasty-fonts-badge is-warning tasty-fonts-badge--interactive tasty-fonts-badge--help" <?php $this->renderPassiveHelpAttributes($customCssUrlImportsExperimentalHelp); ?>><?php esc_html_e('Experimental', 'tasty-fonts'); ?></span>
                                                    </span>
                                                    <?php if ($showSettingsDescriptions): ?>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Allows inspecting custom CSS stylesheets from Add Fonts. Save and reload before using it.', 'tasty-fonts'); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                        <?php endif; ?>
                                        </div>
                                    </section>
                                    <section class="tasty-fonts-health-group" aria-label="<?php esc_attr_e('Cleanup and access', 'tasty-fonts'); ?>">
                                        <div class="tasty-fonts-health-group-head">
                                            <h4><?php esc_html_e('Cleanup & Access', 'tasty-fonts'); ?></h4>
                                            <span class="tasty-fonts-health-group-count"><?php esc_html_e('Files & Permissions', 'tasty-fonts'); ?></span>
                                        </div>
                                        <div class="tasty-fonts-output-settings-list tasty-fonts-settings-board-list tasty-fonts-settings-behavior-stack">
                                        <input type="hidden" name="delete_uploaded_files_on_uninstall" value="1">
                                        <label class="tasty-fonts-toggle-field tasty-fonts-toggle-field--output<?php echo $deleteUploadedFilesOnUninstall ? ' is-risky' : ''; ?>" data-settings-help-tooltip="<?php echo esc_attr__('Review before turning this off. When disabled, uninstall removes plugin-managed uploaded font files.', 'tasty-fonts'); ?>">
                                            <input type="checkbox" class="tasty-fonts-toggle-input" name="delete_uploaded_files_on_uninstall" value="0" <?php checked(!$deleteUploadedFilesOnUninstall); ?>>
                                            <span class="tasty-fonts-toggle-switch" aria-hidden="true"></span>
                                            <span class="tasty-fonts-toggle-copy">
                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Keep Uploaded Fonts on Uninstall', 'tasty-fonts'); ?></span>
                                                <?php if ($showSettingsDescriptions): ?>
                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('Keeps plugin-managed font files in place when uninstalling.', 'tasty-fonts'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
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
                                                <span class="tasty-fonts-toggle-title"><?php esc_html_e('Enable Additional Access Rules', 'tasty-fonts'); ?></span>
                                                <?php if ($showSettingsDescriptions): ?>
                                                    <span class="tasty-fonts-admin-access-mode-copy"><?php esc_html_e('Grant Tasty Fonts access to extra roles and users. Administrators always keep access.', 'tasty-fonts'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                        <div class="tasty-fonts-admin-access-summary-bar<?php echo $showSettingsDescriptions ? '' : ' is-compact'; ?>">
                                            <p class="tasty-fonts-admin-access-summary-intro" data-admin-access-summary-state="disabled"<?php echo $adminAccessCustomEnabled ? ' hidden' : ''; ?>>
                                                <strong><?php esc_html_e('Administrators always have access.', 'tasty-fonts'); ?></strong>
                                                <?php if ($showSettingsDescriptions): ?>
                                                    <span><?php esc_html_e('Turn custom access on to grant additional roles or users.', 'tasty-fonts'); ?></span>
                                                <?php endif; ?>
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
                                                        <?php if ($showSettingsDescriptions): ?>
                                                            <p class="tasty-fonts-admin-access-note"><?php esc_html_e('Grant access to everyone in selected roles.', 'tasty-fonts'); ?></p>
                                                        <?php endif; ?>
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
                                                        <?php if ($showSettingsDescriptions): ?>
                                                            <p class="tasty-fonts-admin-access-note"><?php esc_html_e('Grant access to selected users only.', 'tasty-fonts'); ?></p>
                                                        <?php endif; ?>
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
                                    </section>
                                </article>
                                </div>
                            </div>
                        </section>
                        </form>
                    </section>
