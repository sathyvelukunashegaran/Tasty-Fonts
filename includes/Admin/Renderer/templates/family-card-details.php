                <div class="tasty-fonts-family-details-grid">
                    <div class="tasty-fonts-family-details-primary">
                        <div class="tasty-fonts-font-sidebar">
                            <div class="tasty-fonts-family-meta <?php echo $supportsFontDisplayOverride ? 'has-font-display-control' : ''; ?>">
                                <form method="post" class="tasty-fonts-family-publish-state-form" data-family-publish-state-form>
                                    <div class="tasty-fonts-inline-field-row">
                                        <label class="tasty-fonts-inline-field tasty-fonts-inline-field--select">
                                            <span class="tasty-fonts-field-label-row">
                                                <span class="tasty-fonts-field-label-text"><?php esc_html_e('Publish State', 'tasty-fonts'); ?></span>
                                                <?php if (!$canChangePublishState): ?>
                                                    <span class="tasty-fonts-field-status tasty-fonts-field-status--auto" title="<?php esc_attr_e('Auto-managed', 'tasty-fonts'); ?>"><?php esc_html_e('Auto', 'tasty-fonts'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="tasty-fonts-select-field">
                                                <select
                                                    class="tasty-fonts-family-publish-state-selector"
                                                    data-family-slug="<?php echo esc_attr($familySlug); ?>"
                                                    data-saved-value="<?php echo esc_attr($publishState === 'library_only' ? 'library_only' : 'published'); ?>"
                                                    <?php disabled(!$canChangePublishState); ?>
                                                >
                                                    <option value="published" <?php selected($publishState !== 'library_only'); ?>><?php esc_html_e('Published', 'tasty-fonts'); ?></option>
                                                    <option value="library_only" <?php selected($publishState === 'library_only'); ?>><?php esc_html_e('In Library Only', 'tasty-fonts'); ?></option>
                                                </select>
                                            </span>
                                        </label>
                                        <button
                                            type="submit"
                                            class="button tasty-fonts-family-save-button tasty-fonts-family-publish-state-save"
                                            data-family-publish-state-save
                                            <?php $this->renderPassiveHelpAttributes(__('Save runtime state changes.', 'tasty-fonts')); ?>
                                            aria-label="<?php echo esc_attr__('Save runtime state', 'tasty-fonts'); ?>"
                                            <?php disabled(!$canChangePublishState); ?>
                                        >
                                            <span class="screen-reader-text"><?php esc_html_e('Save State', 'tasty-fonts'); ?></span>
                                        </button>
                                    </div>
                                    <p class="tasty-fonts-family-publish-state-feedback" data-family-publish-state-feedback aria-live="polite" hidden></p>
                                </form>

                                <form method="post" class="tasty-fonts-family-fallback-form" data-family-fallback-form>
                                    <?php wp_nonce_field('tasty_fonts_save_family_fallback'); ?>
                                    <input type="hidden" name="tasty_fonts_save_family_fallback" value="1">
                                    <input type="hidden" name="tasty_fonts_family_name" value="<?php echo esc_attr($familyName); ?>">
                                    <div class="tasty-fonts-inline-field-row">
                                        <label class="tasty-fonts-inline-field tasty-fonts-inline-field--select">
                                            <span class="tasty-fonts-field-label"><?php esc_html_e('Fallback', 'tasty-fonts'); ?></span>
                                            <?php
                                            $this->renderFallbackInput(
                                                'tasty_fonts_family_fallback',
                                                $savedFallback,
                                                [
                                                    'class' => 'tasty-fonts-fallback-selector',
                                                    'data-font-family' => $familyName,
                                                    'data-saved-value' => $savedFallback,
                                                    'placeholder' => __('Example: system-ui, sans-serif', 'tasty-fonts'),
                                                ]
                                            );
                                            ?>
                                        </label>
                                        <button
                                            type="submit"
                                            class="button tasty-fonts-family-save-button tasty-fonts-family-fallback-save"
                                            data-family-fallback-save
                                            <?php $this->renderPassiveHelpAttributes(__('Save fallback stack changes.', 'tasty-fonts')); ?>
                                            aria-label="<?php echo esc_attr__('Save fallback', 'tasty-fonts'); ?>"
                                        >
                                            <span class="screen-reader-text"><?php esc_html_e('Save Fallback', 'tasty-fonts'); ?></span>
                                        </button>
                                    </div>
                                    <p class="tasty-fonts-family-fallback-feedback" data-family-fallback-feedback aria-live="polite" hidden></p>
                                </form>

                                <?php if ($supportsFontDisplayOverride): ?>
                                    <form method="post" class="tasty-fonts-family-font-display-form" data-family-font-display-form>
                                        <?php wp_nonce_field('tasty_fonts_save_family_font_display'); ?>
                                        <input type="hidden" name="tasty_fonts_save_family_font_display" value="1">
                                        <input type="hidden" name="tasty_fonts_family_name" value="<?php echo esc_attr($familyName); ?>">
                                        <div class="tasty-fonts-inline-field-row">
                                            <label class="tasty-fonts-inline-field tasty-fonts-inline-field--select">
                                                <span class="tasty-fonts-field-label"><?php esc_html_e('Font Display', 'tasty-fonts'); ?></span>
                                                <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                                                    <select
                                                        name="tasty_fonts_family_font_display"
                                                        class="tasty-fonts-font-display-selector"
                                                        data-font-family="<?php echo esc_attr($familyName); ?>"
                                                        data-saved-value="<?php echo esc_attr($currentFontDisplay); ?>"
                                                    >
                                                        <?php foreach ($familyFontDisplayOptions as $option): ?>
                                                            <option value="<?php echo esc_attr((string) ($option['value'] ?? '')); ?>" <?php selected($currentFontDisplay, (string) ($option['value'] ?? '')); ?>>
                                                                <?php echo esc_html((string) ($option['label'] ?? '')); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <?php $this->renderClearSelectButton(__('Clear font display override', 'tasty-fonts'), '', 'inherit'); ?>
                                                </span>
                                            </label>
                                            <button
                                                type="submit"
                                                class="button tasty-fonts-family-save-button tasty-fonts-family-font-display-save"
                                                data-family-font-display-save
                                                <?php $this->renderPassiveHelpAttributes(__('Save font display changes.', 'tasty-fonts')); ?>
                                                aria-label="<?php echo esc_attr__('Save font display', 'tasty-fonts'); ?>"
                                            >
                                                <span class="screen-reader-text"><?php esc_html_e('Save Display', 'tasty-fonts'); ?></span>
                                            </button>
                                        </div>
                                        <p class="tasty-fonts-family-font-display-feedback" data-family-font-display-feedback aria-live="polite" hidden></p>
                                    </form>
                                <?php else: ?>
                                    <div class="tasty-fonts-inline-note tasty-fonts-inline-note--warning">
                                        <strong><?php esc_html_e('Font Display unavailable', 'tasty-fonts'); ?></strong>
                                        <span><?php esc_html_e('Adobe-hosted fonts follow Adobe’s stylesheet behavior.', 'tasty-fonts'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <section class="tasty-fonts-detail-group tasty-fonts-detail-group--profiles">
                            <div class="tasty-fonts-detail-group-head">
                                <div class="tasty-fonts-detail-group-copy">
                                    <h4><?php esc_html_e('Delivery Profiles', 'tasty-fonts'); ?></h4>
                                </div>
                                <span class="tasty-fonts-badge">
                                    <?php echo esc_html(sprintf(_n('%d profile', '%d profiles', count($availableDeliveries), 'tasty-fonts'), count($availableDeliveries))); ?>
                                </span>
                            </div>
                            <div class="tasty-fonts-detail-card-list tasty-fonts-detail-card-list--deliveries">
                                <?php foreach ($availableDeliveries as $profile): ?>
                                    <?php if (!is_array($profile)) { continue; } ?>
                                    <?php $this->renderDeliveryProfileCard($familyName, $familySlug, $activeDeliveryId, $publishState, $profile, $availableDeliveries); ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </div>

                    <?php if ($familyCssVariableSnippets !== [] || $familyCssClassSnippets !== []): ?>
                        <div class="tasty-fonts-family-details-utilities">
                            <?php if ($familyCssVariableSnippets !== []): ?>
                                <section class="tasty-fonts-detail-group tasty-fonts-detail-group--variables tasty-fonts-detail-group--utility">
                                    <div class="tasty-fonts-detail-group-head">
                                        <div class="tasty-fonts-detail-group-copy">
                                            <h4><?php esc_html_e('CSS Variables', 'tasty-fonts'); ?></h4>
                                        </div>
                                        <span class="tasty-fonts-badge">
                                            <?php echo esc_html(sprintf(_n('%d variable', '%d variables', count($familyCssVariableSnippets), 'tasty-fonts'), count($familyCssVariableSnippets))); ?>
                                        </span>
                                    </div>
                                    <div class="tasty-fonts-detail-files tasty-fonts-detail-files--utility">
                                        <div class="tasty-fonts-role-stacks tasty-fonts-role-stacks--selection">
                                            <?php foreach ($familyCssVariableSnippets as $label => $snippet) : ?>
                                                <?php $this->renderFaceVariableCopyPill((string) $label, (string) $snippet, __('CSS variable copied.', 'tasty-fonts')); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            <?php endif; ?>

                            <?php if ($familyCssClassSnippets !== []): ?>
                                <section class="tasty-fonts-detail-group tasty-fonts-detail-group--classes tasty-fonts-detail-group--utility">
                                    <div class="tasty-fonts-detail-group-head">
                                        <div class="tasty-fonts-detail-group-copy">
                                            <h4><?php esc_html_e('Font Classes', 'tasty-fonts'); ?></h4>
                                        </div>
                                        <span class="tasty-fonts-badge">
                                            <?php echo esc_html(sprintf(_n('%d class', '%d classes', count($familyCssClassSnippets), 'tasty-fonts'), count($familyCssClassSnippets))); ?>
                                        </span>
                                    </div>
                                    <div class="tasty-fonts-detail-files tasty-fonts-detail-files--utility">
                                        <div class="tasty-fonts-role-stacks tasty-fonts-role-stacks--selection">
                                            <?php foreach ($familyCssClassSnippets as $label => $snippet) : ?>
                                                <?php $this->renderFaceVariableCopyPill((string) $label, (string) $snippet, __('Font class copied.', 'tasty-fonts')); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <section class="tasty-fonts-detail-group tasty-fonts-detail-group--faces">
                        <div class="tasty-fonts-detail-group-head">
                            <div class="tasty-fonts-detail-group-copy">
                                <h4><?php esc_html_e('Font Faces', 'tasty-fonts'); ?></h4>
                            </div>
                            <span class="tasty-fonts-badge">
                                <?php echo esc_html(sprintf(_n('%d face', '%d faces', $faceCount, 'tasty-fonts'), $faceCount)); ?>
                            </span>
                        </div>
                        <div class="tasty-fonts-detail-card-list tasty-fonts-detail-card-list--faces">
                            <?php foreach ($activeFaces as $face): ?>
                                <?php if (!is_array($face)) { continue; } ?>
                                <?php $this->renderFaceDetailCard($familyName, $familySlug, $defaultStack, $facePreviewText, $faceCount, $assignedRoleKeys, (string) ($family['font_category'] ?? ''), $categoryAliasOwners, $extendedVariableOptions, $activeDelivery, $face, $usesMonospacePreview); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
