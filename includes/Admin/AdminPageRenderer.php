<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

use TastyFonts\Support\FontUtils;
use TastyFonts\Support\Storage;

final class AdminPageRenderer
{
    public function __construct(private readonly Storage $storage)
    {
    }

    public function renderPage(array $context): void
    {
        $storage = is_array($context['storage'] ?? null) ? $context['storage'] : null;
        $catalog = is_array($context['catalog'] ?? null) ? $context['catalog'] : [];
        $availableFamilies = is_array($context['available_families'] ?? null) ? $context['available_families'] : array_keys($catalog);
        $roles = is_array($context['roles'] ?? null) ? $context['roles'] : [];
        $logs = is_array($context['logs'] ?? null) ? $context['logs'] : [];
        $activityActorOptions = is_array($context['activity_actor_options'] ?? null) ? $context['activity_actor_options'] : [];
        $familyFallbacks = is_array($context['family_fallbacks'] ?? null) ? $context['family_fallbacks'] : [];
        $previewText = (string) ($context['preview_text'] ?? '');
        $previewSize = (int) ($context['preview_size'] ?? 32);
        $googleApiState = (string) ($context['google_api_state'] ?? 'empty');
        $googleApiEnabled = !empty($context['google_api_enabled']);
        $googleApiSaved = !empty($context['google_api_saved']);
        $googleAccessExpanded = !empty($context['google_access_expanded']);
        $adobeProjectState = (string) ($context['adobe_project_state'] ?? 'empty');
        $googleStatusLabel = (string) ($context['google_status_label'] ?? '');
        $googleStatusClass = (string) ($context['google_status_class'] ?? '');
        $googleAccessCopy = (string) ($context['google_access_copy'] ?? '');
        $googleSearchDisabledCopy = (string) ($context['google_search_disabled_copy'] ?? '');
        $adobeProjectEnabled = !empty($context['adobe_project_enabled']);
        $adobeProjectSaved = !empty($context['adobe_project_saved']);
        $adobeAccessExpanded = !empty($context['adobe_access_expanded']);
        $adobeProjectId = (string) ($context['adobe_project_id'] ?? '');
        $adobeStatusLabel = (string) ($context['adobe_status_label'] ?? '');
        $adobeStatusClass = (string) ($context['adobe_status_class'] ?? '');
        $adobeAccessCopy = (string) ($context['adobe_access_copy'] ?? '');
        $adobeProjectLink = (string) ($context['adobe_project_link'] ?? 'https://fonts.adobe.com/');
        $adobeDetectedFamilies = is_array($context['adobe_detected_families'] ?? null) ? $context['adobe_detected_families'] : [];
        $googleAccessButtonLabel = $googleApiEnabled ? __('Edit Key', 'tasty-fonts') : __('Key Settings', 'tasty-fonts');
        $adobeAccessButtonLabel = $adobeProjectSaved ? __('Project Settings', 'tasty-fonts') : __('Add Project', 'tasty-fonts');
        $fontDisplay = (string) ($context['font_display'] ?? 'optional');
        $fontDisplayOptions = is_array($context['font_display_options'] ?? null) ? $context['font_display_options'] : [];
        $minifyCssOutput = !empty($context['minify_css_output']);
        $preloadPrimaryFonts = !empty($context['preload_primary_fonts']);
        $deleteUploadedFilesOnUninstall = !empty($context['delete_uploaded_files_on_uninstall']);
        $diagnosticItems = is_array($context['diagnostic_items'] ?? null) ? $context['diagnostic_items'] : [];
        $overviewMetrics = is_array($context['overview_metrics'] ?? null) ? $context['overview_metrics'] : [];
        $outputPanels = is_array($context['output_panels'] ?? null) ? $context['output_panels'] : [];
        $previewPanels = is_array($context['preview_panels'] ?? null) ? $context['preview_panels'] : [];
        $toasts = is_array($context['toasts'] ?? null) ? $context['toasts'] : [];
        $applyEverywhere = !empty($context['apply_everywhere']);
        $roleDeployment = is_array($context['role_deployment'] ?? null) ? $context['role_deployment'] : [];
        $roleSaveActionsClass = $applyEverywhere ? ' is-three-actions' : '';
        $headingFamily = (string) ($roles['heading'] ?? '');
        $bodyFamily = (string) ($roles['body'] ?? '');
        $headingFallback = (string) ($roles['heading_fallback'] ?? 'sans-serif');
        $bodyFallback = (string) ($roles['body_fallback'] ?? 'sans-serif');
        $headingStack = FontUtils::buildFontStack($headingFamily, $headingFallback);
        $bodyStack = FontUtils::buildFontStack($bodyFamily, $bodyFallback);
        $headingVariable = 'var(--font-heading)';
        $bodyVariable = 'var(--font-body)';
        $headingFamilyVariable = $this->buildFontVariableReference($headingFamily);
        $bodyFamilyVariable = $this->buildFontVariableReference($bodyFamily);
        $pluginVersion = defined('TASTY_FONTS_VERSION') ? (string) TASTY_FONTS_VERSION : '';
        $pluginRepositoryUrl = 'https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts';
        $pluginVersionUrl = $pluginVersion !== ''
            ? $pluginRepositoryUrl . '/releases/tag/' . rawurlencode($pluginVersion)
            : $pluginRepositoryUrl;
        $roleDeploymentBadge = (string) ($roleDeployment['badge'] ?? '');
        $roleDeploymentBadgeClass = (string) ($roleDeployment['badge_class'] ?? '');
        $roleDeploymentTitle = trim((string) ($roleDeployment['title'] ?? ''));
        $roleDeploymentCopy = trim((string) ($roleDeployment['copy'] ?? ''));
        $roleDeploymentTooltip = trim(
            $roleDeploymentTitle . ($roleDeploymentTitle !== '' && $roleDeploymentCopy !== '' ? '. ' : '') . $roleDeploymentCopy
        );
        $roleDeploymentAnnouncementId = 'tasty-fonts-role-deployment-announcement';
        ?>
        <div class="wrap tasty-fonts-admin">
            <?php $this->renderNotices($toasts); ?>

            <?php if (!$storage): ?>
                <div class="notice notice-error"><p><?php esc_html_e('The uploads/fonts directory could not be initialized.', 'tasty-fonts'); ?></p></div>
            <?php else: ?>
                <div class="tasty-fonts-shell">
                    <section class="tasty-fonts-card tasty-fonts-studio-card tasty-fonts-top-panel" id="tasty-fonts-roles-studio">
                        <div class="tasty-fonts-top-panel-intro">
                            <div class="tasty-fonts-overview-head tasty-fonts-top-panel-overview">
                                <div class="tasty-fonts-hero-copy">
                                    <div class="tasty-fonts-hero-title-row">
                                        <h1><?php esc_html_e('Tasty Custom Fonts', 'tasty-fonts'); ?></h1>
                                        <?php if ($pluginVersion !== ''): ?>
                                            <a
                                                class="tasty-fonts-version-link tasty-fonts-badge is-role"
                                                href="<?php echo esc_url($pluginVersionUrl); ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                aria-label="<?php echo esc_attr(sprintf(__('View GitHub changelog for version %s', 'tasty-fonts'), $pluginVersion)); ?>"
                                                title="<?php echo esc_attr(sprintf(__('View changelog for version %s on GitHub', 'tasty-fonts'), $pluginVersion)); ?>"
                                            >
                                                <?php echo esc_html(sprintf(__('v%s', 'tasty-fonts'), $pluginVersion)); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <p class="tasty-fonts-hero-text"><?php esc_html_e('Typography management for Etch, Gutenberg, and the frontend.', 'tasty-fonts'); ?></p>
                                </div>
                            </div>

                            <div class="tasty-fonts-metrics tasty-fonts-metrics--top-panel">
                                <?php foreach ($overviewMetrics as $metric): ?>
                                    <article class="tasty-fonts-metric">
                                        <div class="tasty-fonts-metric-label"><?php echo esc_html((string) ($metric['label'] ?? '')); ?></div>
                                        <div class="tasty-fonts-metric-value"><?php echo esc_html((string) ($metric['value'] ?? '')); ?></div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <form method="post" class="tasty-fonts-top-panel-form" data-role-form>
                            <div class="tasty-fonts-card-head tasty-fonts-top-panel-roles-head">
                                <?php
                                $this->renderSectionHeading(
                                    'h2',
                                    __('Font Roles', 'tasty-fonts'),
                                    __('Choose the heading and body pairing used for saved output and optional sitewide typography.', 'tasty-fonts')
                                );
                                ?>
                                <div class="tasty-fonts-role-stacks">
                                    <?php if ($roleDeployment !== []): ?>
                                        <span class="tasty-fonts-role-stack tasty-fonts-role-deployment <?php echo esc_attr($roleDeploymentBadgeClass); ?>" data-role-deployment aria-live="polite" aria-atomic="true">
                                            <span class="tasty-fonts-role-stack-label"><?php esc_html_e('Status', 'tasty-fonts'); ?></span>
                                            <button
                                                type="button"
                                                class="tasty-fonts-role-status-pill <?php echo esc_attr($roleDeploymentBadgeClass); ?>"
                                                data-role-deployment-pill
                                                data-help-tooltip="<?php echo esc_attr($roleDeploymentTooltip); ?>"
                                                data-help-passive="1"
                                                aria-label="<?php esc_attr_e('Role deployment status', 'tasty-fonts'); ?>"
                                                aria-describedby="<?php echo esc_attr($roleDeploymentAnnouncementId); ?>"
                                                aria-controls="tasty-fonts-help-tooltip-layer"
                                                title="<?php echo esc_attr($roleDeploymentTooltip); ?>"
                                            >
                                                <span data-role-deployment-badge><?php echo esc_html($roleDeploymentBadge); ?></span>
                                            </button>
                                            <span id="<?php echo esc_attr($roleDeploymentAnnouncementId); ?>" class="screen-reader-text" data-role-deployment-announcement><?php echo esc_html($roleDeploymentTooltip); ?></span>
                                        </span>
                                    <?php endif; ?>
                                    <span class="tasty-fonts-role-stack">
                                        <span class="tasty-fonts-role-stack-label"><?php esc_html_e('Heading', 'tasty-fonts'); ?></span>
                                        <button
                                            type="button"
                                            class="tasty-fonts-kbd tasty-fonts-role-stack-copy"
                                            id="tasty-fonts-role-heading-stack"
                                            data-role-variable-copy="heading"
                                            data-copy-text="<?php echo esc_attr($headingVariable); ?>"
                                            data-copy-success="<?php esc_attr_e('Heading variable copied.', 'tasty-fonts'); ?>"
                                            data-copy-static-label="1"
                                            aria-label="<?php esc_attr_e('Copy heading font variable', 'tasty-fonts'); ?>"
                                            title="<?php echo esc_attr(sprintf(__('Heading font variable: %1$s. Resolved stack: %2$s', 'tasty-fonts'), $headingVariable, $headingStack)); ?>"
                                        >
                                            <?php echo esc_html($headingVariable); ?>
                                        </button>
                                    </span>
                                    <span class="tasty-fonts-role-stack">
                                        <span class="tasty-fonts-role-stack-label"><?php esc_html_e('Body', 'tasty-fonts'); ?></span>
                                        <button
                                            type="button"
                                            class="tasty-fonts-kbd tasty-fonts-role-stack-copy"
                                            id="tasty-fonts-role-body-stack"
                                            data-role-variable-copy="body"
                                            data-copy-text="<?php echo esc_attr($bodyVariable); ?>"
                                            data-copy-success="<?php esc_attr_e('Body variable copied.', 'tasty-fonts'); ?>"
                                            data-copy-static-label="1"
                                            aria-label="<?php esc_attr_e('Copy body font variable', 'tasty-fonts'); ?>"
                                            title="<?php echo esc_attr(sprintf(__('Body font variable: %1$s. Resolved stack: %2$s', 'tasty-fonts'), $bodyVariable, $bodyStack)); ?>"
                                        >
                                            <?php echo esc_html($bodyVariable); ?>
                                        </button>
                                    </span>
                                </div>
                            </div>
                            <?php wp_nonce_field('tasty_fonts_save_roles'); ?>
                            <input type="hidden" name="tasty_fonts_save_roles" value="1">
                            <div class="tasty-fonts-role-grid">
                                <section class="tasty-fonts-role-box">
                                    <div class="tasty-fonts-role-box-head">
                                        <?php $this->renderSectionHeading('h3', __('Heading Font', 'tasty-fonts'), ''); ?>
                                        <button
                                            type="button"
                                            class="tasty-fonts-kbd tasty-fonts-role-stack-copy tasty-fonts-role-box-copy"
                                            data-role-family-variable-copy="heading"
                                            data-copy-text="<?php echo esc_attr($headingFamilyVariable); ?>"
                                            data-copy-success="<?php esc_attr_e('Heading family variable copied.', 'tasty-fonts'); ?>"
                                            data-copy-static-label="1"
                                            aria-label="<?php esc_attr_e('Copy heading family variable', 'tasty-fonts'); ?>"
                                            title="<?php echo esc_attr(sprintf(__('Heading family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $headingFamilyVariable, $headingVariable, $headingStack)); ?>"
                                        >
                                            <?php echo esc_html($headingFamilyVariable); ?>
                                        </button>
                                    </div>
                                    <div class="tasty-fonts-role-fields">
                                        <label class="tasty-fonts-stack-field">
                                            <?php $this->renderFieldLabel(__('Family', 'tasty-fonts')); ?>
                                            <select name="tasty_fonts_heading_font" id="tasty_fonts_heading_font">
                                                <?php foreach ($availableFamilies as $familyName): ?>
                                                    <option value="<?php echo esc_attr((string) $familyName); ?>" <?php selected($roles['heading'] ?? '', $familyName); ?>><?php echo esc_html((string) $familyName); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label class="tasty-fonts-stack-field">
                                            <?php $this->renderFieldLabel(__('Fallback', 'tasty-fonts')); ?>
                                            <?php
                                            $this->renderFallbackInput(
                                                'tasty_fonts_heading_fallback',
                                                (string) ($roles['heading_fallback'] ?? 'sans-serif'),
                                                [
                                                    'id' => 'tasty_fonts_heading_fallback',
                                                    'placeholder' => __('Example: system-ui, sans-serif', 'tasty-fonts'),
                                                ]
                                            );
                                            ?>
                                        </label>
                                    </div>
                                </section>

                                <section class="tasty-fonts-role-box">
                                    <div class="tasty-fonts-role-box-head">
                                        <?php $this->renderSectionHeading('h3', __('Body Font', 'tasty-fonts'), ''); ?>
                                        <button
                                            type="button"
                                            class="tasty-fonts-kbd tasty-fonts-role-stack-copy tasty-fonts-role-box-copy"
                                            data-role-family-variable-copy="body"
                                            data-copy-text="<?php echo esc_attr($bodyFamilyVariable); ?>"
                                            data-copy-success="<?php esc_attr_e('Body family variable copied.', 'tasty-fonts'); ?>"
                                            data-copy-static-label="1"
                                            aria-label="<?php esc_attr_e('Copy body family variable', 'tasty-fonts'); ?>"
                                            title="<?php echo esc_attr(sprintf(__('Body family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'), $bodyFamilyVariable, $bodyVariable, $bodyStack)); ?>"
                                        >
                                            <?php echo esc_html($bodyFamilyVariable); ?>
                                        </button>
                                    </div>
                                    <div class="tasty-fonts-role-fields">
                                        <label class="tasty-fonts-stack-field">
                                            <?php $this->renderFieldLabel(__('Family', 'tasty-fonts')); ?>
                                            <select name="tasty_fonts_body_font" id="tasty_fonts_body_font">
                                                <?php foreach ($availableFamilies as $familyName): ?>
                                                    <option value="<?php echo esc_attr((string) $familyName); ?>" <?php selected($roles['body'] ?? '', $familyName); ?>><?php echo esc_html((string) $familyName); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label class="tasty-fonts-stack-field">
                                            <?php $this->renderFieldLabel(__('Fallback', 'tasty-fonts')); ?>
                                            <?php
                                            $this->renderFallbackInput(
                                                'tasty_fonts_body_fallback',
                                                (string) ($roles['body_fallback'] ?? 'sans-serif'),
                                                [
                                                    'id' => 'tasty_fonts_body_fallback',
                                                    'placeholder' => __('Example: system-ui, sans-serif', 'tasty-fonts'),
                                                ]
                                            );
                                            ?>
                                        </label>
                                    </div>
                                </section>
                            </div>

                            <div class="tasty-fonts-role-toolbar">
                                <div class="tasty-fonts-role-actions">
                                    <div class="tasty-fonts-role-save-actions<?php echo esc_attr($roleSaveActionsClass); ?>">
                                        <div class="tasty-fonts-action-choice tasty-fonts-action-choice--primary">
                                            <div class="tasty-fonts-button-with-help">
                                                <button type="submit" class="button button-primary tasty-fonts-scope-button tasty-fonts-scope-button--apply" name="tasty_fonts_action_type" value="apply">
                                                    <span class="tasty-fonts-scope-button-title"><?php esc_html_e('Apply Sitewide', 'tasty-fonts'); ?></span>
                                                </button>
                                                <?php $this->renderHelpTip(__('Updates frontend CSS, the editor, and Etch immediately.', 'tasty-fonts'), __('Apply Sitewide', 'tasty-fonts')); ?>
                                            </div>
                                        </div>
                                        <div class="tasty-fonts-action-choice">
                                            <div class="tasty-fonts-button-with-help">
                                                <button type="submit" class="button tasty-fonts-scope-button tasty-fonts-scope-button--save" name="tasty_fonts_action_type" value="save">
                                                    <span class="tasty-fonts-scope-button-title"><?php esc_html_e('Save Draft', 'tasty-fonts'); ?></span>
                                                </button>
                                                <?php $this->renderHelpTip(__('Saves the current heading and body pair here without changing what the frontend, editor, or Etch are currently using.', 'tasty-fonts'), __('Save Draft', 'tasty-fonts')); ?>
                                            </div>
                                        </div>
                                        <?php if ($applyEverywhere): ?>
                                            <div class="tasty-fonts-action-choice">
                                                <div class="tasty-fonts-button-with-help">
                                                    <button type="submit" class="button tasty-fonts-scope-button tasty-fonts-scope-button--save" name="tasty_fonts_action_type" value="disable">
                                                        <span class="tasty-fonts-scope-button-title"><?php esc_html_e('Turn Off Sitewide', 'tasty-fonts'); ?></span>
                                                    </button>
                                                    <?php $this->renderHelpTip(__('Stops loading the applied role CSS on the frontend, editor, and Etch, while keeping the current fields saved as a draft.', 'tasty-fonts'), __('Turn Off Sitewide', 'tasty-fonts')); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tasty-fonts-role-secondary-actions">
                                        <div class="tasty-fonts-button-with-help">
                                            <button
                                                type="button"
                                                class="button tasty-fonts-disclosure-button tasty-fonts-disclosure-button--preview"
                                                data-disclosure-toggle="tasty-fonts-role-advanced-panel"
                                                data-expanded-label="<?php echo esc_attr__('Advanced Tools', 'tasty-fonts'); ?>"
                                                data-collapsed-label="<?php echo esc_attr__('Advanced Tools', 'tasty-fonts'); ?>"
                                                aria-expanded="false"
                                                aria-controls="tasty-fonts-role-advanced-panel"
                                            >
                                                <?php esc_html_e('Advanced Tools', 'tasty-fonts'); ?>
                                            </button>
                                            <?php $this->renderHelpTip(__('Open the preview, snippets, system details, and output settings panels for the current role pairing.', 'tasty-fonts'), __('Advanced Tools', 'tasty-fonts')); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                            <div id="tasty-fonts-role-advanced-panel" class="tasty-fonts-role-advanced-panel" hidden>
                                <div class="tasty-fonts-studio-switcher tasty-fonts-tab-list" role="tablist" aria-label="<?php esc_attr_e('Advanced Tools', 'tasty-fonts'); ?>" aria-orientation="horizontal">
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button is-active"
                                        id="tasty-fonts-studio-tab-preview"
                                        data-tab-group="studio"
                                        data-tab-target="preview"
                                        aria-selected="true"
                                        tabindex="0"
                                        aria-controls="tasty-fonts-studio-panel-preview"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Preview', 'tasty-fonts'); ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="tasty-fonts-studio-tab tasty-fonts-tab-button"
                                        id="tasty-fonts-studio-tab-snippets"
                                        data-tab-group="studio"
                                        data-tab-target="snippets"
                                        aria-selected="false"
                                        tabindex="-1"
                                        aria-controls="tasty-fonts-studio-panel-snippets"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Snippets', 'tasty-fonts'); ?>
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
                                </div>

                                <section
                                    id="tasty-fonts-studio-panel-preview"
                                    class="tasty-fonts-studio-panel is-active"
                                    data-tab-group="studio"
                                    data-tab-panel="preview"
                                    role="tabpanel"
                                    aria-labelledby="tasty-fonts-studio-tab-preview"
                                >
                                    <div
                                        class="tasty-fonts-preview-canvas"
                                        id="tasty-fonts-preview-canvas"
                                        style="--tasty-preview-base: <?php echo esc_attr((string) $previewSize); ?>px;"
                                    >
                                        <div class="tasty-fonts-preview-tabs tasty-fonts-tab-list" role="tablist" aria-label="<?php esc_attr_e('Preview scenarios', 'tasty-fonts'); ?>" aria-orientation="horizontal">
                                            <?php foreach ($previewPanels as $panel): ?>
                                                <?php $buttonId = 'tasty-fonts-preview-tab-' . $panel['key']; ?>
                                                <?php $panelId = 'tasty-fonts-preview-panel-' . $panel['key']; ?>
                                                <button
                                                    type="button"
                                                    class="tasty-fonts-preview-tab tasty-fonts-tab-button <?php echo !empty($panel['active']) ? 'is-active' : ''; ?>"
                                                    id="<?php echo esc_attr($buttonId); ?>"
                                                    data-tab-group="preview"
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

                                        <?php foreach ($previewPanels as $panel): ?>
                                            <?php $buttonId = 'tasty-fonts-preview-tab-' . $panel['key']; ?>
                                            <?php $panelId = 'tasty-fonts-preview-panel-' . $panel['key']; ?>
                                            <section
                                                id="<?php echo esc_attr($panelId); ?>"
                                                class="tasty-fonts-preview-scene tasty-fonts-preview-scene--<?php echo esc_attr((string) $panel['key']); ?> <?php echo !empty($panel['active']) ? 'is-active' : ''; ?>"
                                                data-tab-group="preview"
                                                data-tab-panel="<?php echo esc_attr((string) $panel['key']); ?>"
                                                role="tabpanel"
                                                aria-labelledby="<?php echo esc_attr($buttonId); ?>"
                                                <?php echo !empty($panel['active']) ? '' : 'hidden'; ?>
                                            >
                                                <?php $this->renderPreviewScene((string) $panel['key'], $previewText, $roles); ?>
                                            </section>
                                        <?php endforeach; ?>
                                    </div>
                                </section>

                                <section
                                    id="tasty-fonts-studio-panel-snippets"
                                    class="tasty-fonts-studio-panel"
                                    data-tab-group="studio"
                                    data-tab-panel="snippets"
                                    role="tabpanel"
                                    aria-labelledby="tasty-fonts-studio-tab-snippets"
                                    hidden
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
                                                <div class="tasty-fonts-code-panel-head">
                                                    <span><?php echo esc_html((string) ($panel['label'] ?? '')); ?></span>
                                                    <button type="button" class="button button-small" data-copy-target="<?php echo esc_attr((string) ($panel['target'] ?? '')); ?>"><?php esc_html_e('Copy', 'tasty-fonts'); ?></button>
                                                </div>
                                                <textarea id="<?php echo esc_attr((string) ($panel['target'] ?? '')); ?>" class="tasty-fonts-output" readonly><?php echo esc_textarea((string) ($panel['value'] ?? '')); ?></textarea>
                                            </section>
                                        <?php endforeach; ?>
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
                                            <p><?php esc_html_e('These controls decide how generated CSS is written, whether the active heading/body pair gets frontend preload hints, and how plugin-managed font files are handled during uninstall. They do not change your current heading and body assignments.', 'tasty-fonts'); ?></p>
                                            <p class="tasty-fonts-muted"><?php esc_html_e('Keep minified output on for production-facing delivery. Turn on preloads when you want the plugin to emit same-origin WOFF2 hints for the current heading and body role pair on live pages. Only enable uninstall cleanup if you want this plugin to remove the files it placed in uploads/fonts when the plugin is deleted.', 'tasty-fonts'); ?></p>
                                        </div>
                                        <form method="post" class="tasty-fonts-output-settings-form">
                                            <?php wp_nonce_field('tasty_fonts_save_settings'); ?>
                                            <input type="hidden" name="tasty_fonts_save_settings" value="1">
                                            <div class="tasty-fonts-output-settings-list">
                                                <label class="tasty-fonts-stack-field tasty-fonts-stack-field--output">
                                                    <?php $this->renderFieldLabel(__('Font Display', 'tasty-fonts')); ?>
                                                    <span class="tasty-fonts-select-field">
                                                        <select id="tasty-fonts-font-display" name="font_display">
                                                            <?php foreach ($fontDisplayOptions as $option): ?>
                                                                <option value="<?php echo esc_attr((string) ($option['value'] ?? '')); ?>" <?php selected($fontDisplay, (string) ($option['value'] ?? '')); ?>>
                                                                    <?php echo esc_html((string) ($option['label'] ?? '')); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </span>
                                                    <span class="tasty-fonts-toggle-description"><?php esc_html_e('This value is written into every generated @font-face rule. Optional is the default because it avoids late font swaps and is usually the best LCP choice when your fallback metrics are close; choose Swap when the branded face should replace the fallback as soon as it loads.', 'tasty-fonts'); ?></span>
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
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Compresses the generated stylesheet and the CSS shown in the Snippets tab to remove extra whitespace. Leave this on for production output; turn it off when you need readable CSS while auditing selectors, variables, or spacing.', 'tasty-fonts'); ?></span>
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
                                                        <span class="tasty-fonts-toggle-title"><?php esc_html_e('Preload primary heading and body fonts', 'tasty-fonts'); ?></span>
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Outputs same-origin WOFF2 preload tags for the live heading and body role pair so the primary text faces can start downloading earlier. Adobe-hosted fonts are skipped, and the preload tags are only emitted when Apply Sitewide is active.', 'tasty-fonts'); ?></span>
                                                    </span>
                                                </label>
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
                                                        <span class="tasty-fonts-toggle-description"><?php esc_html_e('Removes the plugin-managed font files stored in uploads/fonts when the plugin is deleted. Leave this off if you want those uploaded files to remain available after uninstalling; turn it on when you want a full cleanup of plugin-managed assets.', 'tasty-fonts'); ?></span>
                                                    </span>
                                                </label>
                                            </div>
                                            <div class="tasty-fonts-output-settings-note tasty-fonts-inline-note">
                                                <strong><?php esc_html_e('What happens after saving', 'tasty-fonts'); ?></strong>
                                                <span><?php esc_html_e('The next generated CSS refresh and frontend page load will use these settings. Your font library and role pairings stay exactly as they are.', 'tasty-fonts'); ?></span>
                                            </div>
                                            <div class="tasty-fonts-output-settings-actions">
                                                <button type="submit" class="button"><?php esc_html_e('Save Output Settings', 'tasty-fonts'); ?></button>
                                            </div>
                                        </form>
                                    </div>
                                </section>
                            </div>
                    </section>

                    <section class="tasty-fonts-card tasty-fonts-library-card" id="tasty-fonts-library">
                        <div class="tasty-fonts-card-head tasty-fonts-card-head--library">
                            <?php
                            $this->renderSectionHeading(
                                'h2',
                                __('Local Library', 'tasty-fonts'),
                                __('Browse every self-hosted family, assign roles, inspect files, or add fonts from Google and direct uploads.', 'tasty-fonts')
                            );
                            ?>
                            <div class="tasty-fonts-library-tools">
                                <div class="tasty-fonts-library-filterbar">
                                    <div class="tasty-fonts-search-field tasty-fonts-search-field--compact">
                                        <label class="screen-reader-text" for="tasty-fonts-library-search"><?php esc_html_e('Filter fonts', 'tasty-fonts'); ?></label>
                                        <input
                                            type="search"
                                            id="tasty-fonts-library-search"
                                            class="regular-text"
                                            placeholder="<?php esc_attr_e('Filter fonts', 'tasty-fonts'); ?>"
                                            aria-label="<?php esc_attr_e('Filter fonts', 'tasty-fonts'); ?>"
                                        >
                                    </div>
                                    <label class="screen-reader-text" for="tasty-fonts-library-source-filter"><?php esc_html_e('Filter fonts by source', 'tasty-fonts'); ?></label>
                                    <span class="tasty-fonts-select-field tasty-fonts-library-select">
                                        <select
                                            id="tasty-fonts-library-source-filter"
                                            data-library-source-filter
                                            aria-label="<?php esc_attr_e('Filter fonts by source', 'tasty-fonts'); ?>"
                                            title="<?php echo esc_attr__('Choose which font source to show in the library.', 'tasty-fonts'); ?>"
                                        >
                                            <option value="all"><?php esc_html_e('All Fonts', 'tasty-fonts'); ?></option>
                                            <option value="used"><?php esc_html_e('In Use', 'tasty-fonts'); ?></option>
                                            <option value="google"><?php esc_html_e('Google Fonts', 'tasty-fonts'); ?></option>
                                            <option value="uploaded"><?php esc_html_e('File Uploads', 'tasty-fonts'); ?></option>
                                            <option value="adobe"><?php esc_html_e('Adobe Fonts', 'tasty-fonts'); ?></option>
                                        </select>
                                    </span>
                                </div>
                                <div class="tasty-fonts-actions tasty-fonts-actions--library">
                                    <form method="post">
                                        <?php wp_nonce_field('tasty_fonts_rescan_fonts'); ?>
                                        <button type="submit" class="button" name="tasty_fonts_rescan_fonts" value="1"><?php esc_html_e('Rescan Fonts', 'tasty-fonts'); ?></button>
                                    </form>
                                    <button
                                        type="button"
                                        id="tasty-fonts-add-font-panel-toggle"
                                        class="button button-primary tasty-fonts-disclosure-button tasty-fonts-disclosure-button--library"
                                        data-disclosure-toggle="tasty-fonts-add-font-panel"
                                        data-expanded-label="<?php echo esc_attr__('Add Fonts', 'tasty-fonts'); ?>"
                                        data-collapsed-label="<?php echo esc_attr__('Add Fonts', 'tasty-fonts'); ?>"
                                        aria-expanded="false"
                                        aria-controls="tasty-fonts-add-font-panel"
                                    >
                                        <?php esc_html_e('Add Fonts', 'tasty-fonts'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="tasty-fonts-add-font-panel" class="tasty-fonts-import-shell" hidden>
                            <div class="tasty-fonts-add-font-tabs tasty-fonts-tab-list" role="tablist" aria-label="<?php esc_attr_e('Add font source', 'tasty-fonts'); ?>" aria-orientation="horizontal">
                                <button type="button" class="button tasty-fonts-add-font-tab tasty-fonts-tab-button is-active" id="tasty-fonts-add-font-tab-google" data-tab-group="add-font" data-tab-target="google" aria-selected="true" tabindex="0" aria-controls="tasty-fonts-add-font-panel-google" role="tab"><?php esc_html_e('Google Fonts', 'tasty-fonts'); ?></button>
                                <button type="button" class="button tasty-fonts-add-font-tab tasty-fonts-tab-button" id="tasty-fonts-add-font-tab-adobe" data-tab-group="add-font" data-tab-target="adobe" aria-selected="false" tabindex="-1" aria-controls="tasty-fonts-add-font-panel-adobe" role="tab"><?php esc_html_e('Adobe Fonts', 'tasty-fonts'); ?></button>
                                <button type="button" class="button tasty-fonts-add-font-tab tasty-fonts-tab-button" id="tasty-fonts-add-font-tab-upload" data-tab-group="add-font" data-tab-target="upload" aria-selected="false" tabindex="-1" aria-controls="tasty-fonts-add-font-panel-upload" role="tab"><?php esc_html_e('Upload Files', 'tasty-fonts'); ?></button>
                            </div>

                            <div class="tasty-fonts-add-font-panels">
                                <section class="tasty-fonts-add-font-panel is-active" id="tasty-fonts-add-font-panel-google" data-tab-group="add-font" data-tab-panel="google" role="tabpanel" aria-labelledby="tasty-fonts-add-font-tab-google">
                                    <div class="tasty-fonts-source-shell tasty-fonts-source-shell--google" data-source-state="<?php echo esc_attr($googleApiState); ?>">
                                        <section class="tasty-fonts-source-card tasty-fonts-source-card--status tasty-fonts-google-access">
                                            <div class="tasty-fonts-source-status-row">
                                                <div class="tasty-fonts-source-status-copy">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Source Setup', 'tasty-fonts'); ?></span>
                                                    <div class="tasty-fonts-source-status-title-row">
                                                        <h4><?php esc_html_e('Google Fonts', 'tasty-fonts'); ?></h4>
                                                    </div>
                                                    <p class="tasty-fonts-muted tasty-fonts-source-summary"><?php echo esc_html($googleAccessCopy); ?></p>
                                                </div>
                                                <div class="tasty-fonts-source-status-actions">
                                                    <span class="tasty-fonts-badge <?php echo esc_attr($googleStatusClass); ?>">
                                                        <?php echo esc_html($googleStatusLabel); ?>
                                                    </span>
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
                                                </div>
                                            </div>

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
                                                                class="regular-text"
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
                                                                    <a class="tasty-fonts-access-link" href="https://developers.google.com/fonts/docs/developer_api" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open the Google Fonts Developer API docs.', 'tasty-fonts'); ?></a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </section>

                                        <div class="tasty-fonts-google-workflow">
                                            <section class="tasty-fonts-source-card tasty-fonts-source-card--task tasty-fonts-search-shell">
                                                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Step 1', 'tasty-fonts'); ?></span>
                                                    <h4><?php esc_html_e('Find a Family', 'tasty-fonts'); ?></h4>
                                                </div>

                                                <label class="tasty-fonts-stack-field">
                                                    <span class="screen-reader-text"><?php esc_html_e('Search Google Fonts', 'tasty-fonts'); ?></span>
                                                    <input
                                                        type="search"
                                                        id="tasty-fonts-google-search"
                                                        class="regular-text"
                                                        placeholder="<?php echo esc_attr($googleApiEnabled ? __('Search Google Fonts families', 'tasty-fonts') : __('Add or verify a Google Fonts API key to search', 'tasty-fonts')); ?>"
                                                        <?php if (!$googleApiEnabled): ?>
                                                            aria-describedby="tasty-fonts-google-search-note"
                                                        <?php endif; ?>
                                                        <?php disabled(!$googleApiEnabled); ?>
                                                    >
                                                </label>
                                                <?php if (!$googleApiEnabled): ?>
                                                    <p id="tasty-fonts-google-search-note" class="tasty-fonts-inline-note tasty-fonts-inline-note--warning">
                                                        <strong><?php esc_html_e('Search disabled.', 'tasty-fonts'); ?></strong>
                                                        <span><?php echo esc_html($googleSearchDisabledCopy); ?></span>
                                                    </p>
                                                <?php endif; ?>
                                                <div id="tasty-fonts-google-results" class="tasty-fonts-search-results" aria-live="polite"></div>
                                            </section>

                                            <section class="tasty-fonts-source-card tasty-fonts-source-card--task tasty-fonts-import-panel tasty-fonts-import-panel--google">
                                                <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Step 2', 'tasty-fonts'); ?></span>
                                                    <h4><?php esc_html_e('Import Selected Files', 'tasty-fonts'); ?></h4>
                                                </div>

                                                <div class="tasty-fonts-import-manual-grid">
                                                    <label class="tasty-fonts-stack-field">
                                                        <?php $this->renderFieldLabel(__('Family Name', 'tasty-fonts')); ?>
                                                        <input type="text" id="tasty-fonts-manual-family" class="regular-text" placeholder="<?php esc_attr_e('e.g. Inter', 'tasty-fonts'); ?>">
                                                    </label>
                                                    <label class="tasty-fonts-stack-field">
                                                        <?php $this->renderFieldLabel(__('Manual Variants', 'tasty-fonts')); ?>
                                                        <input type="text" id="tasty-fonts-manual-variants" class="regular-text" placeholder="<?php esc_attr_e('e.g. regular,700', 'tasty-fonts'); ?>">
                                                    </label>
                                                </div>

                                                <div class="tasty-fonts-selected-wrap tasty-fonts-selected-wrap--import">
                                                    <div class="tasty-fonts-selected-card tasty-fonts-selected-card--import-family">
                                                        <div class="tasty-fonts-import-card-head">
                                                            <div class="tasty-fonts-import-card-copy">
                                                                <?php $this->renderFieldLabel(__('Selected Family', 'tasty-fonts')); ?>
                                                                <div id="tasty-fonts-selected-family" class="tasty-fonts-import-selected-name"><?php esc_html_e('Choose a Google family or type one manually.', 'tasty-fonts'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="tasty-fonts-import-preview-shell">
                                                            <span class="tasty-fonts-import-preview-label"><?php esc_html_e('Live Preview', 'tasty-fonts'); ?></span>
                                                            <div id="tasty-fonts-selected-family-preview" class="tasty-fonts-import-selected-preview is-placeholder"><?php esc_html_e('Preview appears here after you choose a family.', 'tasty-fonts'); ?></div>
                                                        </div>
                                                    </div>

                                                    <div class="tasty-fonts-selected-card tasty-fonts-selected-card--import-variants">
                                                        <div class="tasty-fonts-import-card-head">
                                                            <div class="tasty-fonts-import-card-copy">
                                                                <?php $this->renderFieldLabel(__('Variants to Import', 'tasty-fonts')); ?>
                                                                <p class="tasty-fonts-import-variant-note tasty-fonts-muted"><?php esc_html_e('Click chips or type a comma-separated list above. Both stay in sync.', 'tasty-fonts'); ?></p>
                                                            </div>
                                                            <div class="tasty-fonts-import-card-meta">
                                                                <div id="tasty-fonts-import-selection-summary" class="tasty-fonts-import-selection-summary"><?php esc_html_e('0 Variants Selected', 'tasty-fonts'); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="tasty-fonts-import-variant-toolbar">
                                                            <div class="tasty-fonts-import-variant-actions">
                                                                <button type="button" class="button tasty-fonts-filter-pill tasty-fonts-filter-pill--small tasty-fonts-variant-filter" data-google-variant-select="all"><?php esc_html_e('All', 'tasty-fonts'); ?></button>
                                                                <button type="button" class="button tasty-fonts-filter-pill tasty-fonts-filter-pill--small tasty-fonts-variant-filter" data-google-variant-select="normal"><?php esc_html_e('Normal', 'tasty-fonts'); ?></button>
                                                                <button type="button" class="button tasty-fonts-filter-pill tasty-fonts-filter-pill--small tasty-fonts-variant-filter" data-google-variant-select="italic"><?php esc_html_e('Italic', 'tasty-fonts'); ?></button>
                                                                <button type="button" class="button tasty-fonts-filter-pill tasty-fonts-filter-pill--small tasty-fonts-variant-filter" data-google-variant-select="clear"><?php esc_html_e('Clear', 'tasty-fonts'); ?></button>
                                                            </div>
                                                        </div>
                                                        <div id="tasty-fonts-google-variants" class="tasty-fonts-variant-list"></div>
                                                    </div>
                                                </div>

                                                <div class="tasty-fonts-import-footer">
                                                    <div id="tasty-fonts-import-status" class="tasty-fonts-import-status" aria-live="polite"></div>

                                                    <div class="tasty-fonts-actions tasty-fonts-actions--import">
                                                        <button
                                                            type="button"
                                                            id="tasty-fonts-import-size-estimate"
                                                            class="tasty-fonts-badge tasty-fonts-badge--interactive is-role"
                                                            data-help-tooltip="<?php echo esc_attr__('The estimated transfer size only varies by family and subset.', 'tasty-fonts'); ?>"
                                                            data-help-passive="1"
                                                            aria-label="<?php esc_attr_e('Estimated transfer size information', 'tasty-fonts'); ?>"
                                                            title="<?php echo esc_attr__('The estimated transfer size only varies by family and subset.', 'tasty-fonts'); ?>"
                                                        ><?php esc_html_e('Approx. +0 KB WOFF2', 'tasty-fonts'); ?></button>
                                                        <button type="button" class="button button-primary" id="tasty-fonts-import-submit"><?php esc_html_e('Import and Self-Host', 'tasty-fonts'); ?></button>
                                                    </div>
                                                </div>
                                            </section>
                                        </div>
                                    </div>
                                </section>

                                <section class="tasty-fonts-add-font-panel" id="tasty-fonts-add-font-panel-adobe" data-tab-group="add-font" data-tab-panel="adobe" role="tabpanel" aria-labelledby="tasty-fonts-add-font-tab-adobe" hidden>
                                    <div class="tasty-fonts-source-shell tasty-fonts-source-shell--adobe" data-source-state="<?php echo esc_attr($adobeProjectState); ?>">
                                        <section class="tasty-fonts-source-card tasty-fonts-source-card--status tasty-fonts-adobe-access">
                                            <div class="tasty-fonts-source-status-row">
                                                <div class="tasty-fonts-source-status-copy">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Source Setup', 'tasty-fonts'); ?></span>
                                                    <div class="tasty-fonts-source-status-title-row">
                                                        <h4><?php esc_html_e('Adobe Fonts', 'tasty-fonts'); ?></h4>
                                                    </div>
                                                    <p class="tasty-fonts-muted tasty-fonts-source-summary"><?php echo esc_html($adobeAccessCopy); ?></p>
                                                </div>
                                                <div class="tasty-fonts-source-status-actions">
                                                    <span class="tasty-fonts-badge <?php echo esc_attr($adobeStatusClass); ?>">
                                                        <?php echo esc_html($adobeStatusLabel); ?>
                                                    </span>
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
                                                </div>
                                            </div>

                                            <div id="tasty-fonts-adobe-project-panel" class="tasty-fonts-google-access-panel" <?php echo $adobeAccessExpanded ? '' : 'hidden'; ?>>
                                                <form method="post" class="tasty-fonts-google-access-form">
                                                    <?php wp_nonce_field('tasty_fonts_save_adobe_project'); ?>
                                                    <input type="hidden" name="tasty_fonts_save_adobe_project" value="1">
                                                    <div class="tasty-fonts-google-access-grid">
                                                        <label class="tasty-fonts-stack-field tasty-fonts-google-access-field">
                                                            <?php $this->renderFieldLabel(__('Adobe Fonts Project ID', 'tasty-fonts')); ?>
                                                            <input
                                                                type="text"
                                                                class="regular-text"
                                                                id="tasty-fonts-adobe-project-id"
                                                                name="adobe_project_id"
                                                                value="<?php echo esc_attr($adobeProjectId); ?>"
                                                                placeholder="<?php esc_attr_e('Example: abc1def', 'tasty-fonts'); ?>"
                                                                autocomplete="off"
                                                                spellcheck="false"
                                                            >
                                                        </label>

                                                        <label class="tasty-fonts-inline-checkbox">
                                                            <input type="checkbox" name="adobe_enabled" value="1" <?php checked($adobeProjectEnabled); ?>>
                                                            <span><?php esc_html_e('Load this Adobe-hosted stylesheet on the frontend, editor, and Etch canvas.', 'tasty-fonts'); ?></span>
                                                        </label>

                                                        <div class="tasty-fonts-google-access-footer">
                                                            <div class="tasty-fonts-settings-buttons">
                                                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Project', 'tasty-fonts'); ?></button>
                                                                <?php if ($adobeProjectSaved): ?>
                                                                    <button type="submit" class="button" name="tasty_fonts_resync_adobe_project" value="1"><?php esc_html_e('Resync Project', 'tasty-fonts'); ?></button>
                                                                    <button type="submit" class="button" name="tasty_fonts_remove_adobe_project" value="1"><?php esc_html_e('Remove Project', 'tasty-fonts'); ?></button>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="tasty-fonts-google-access-meta">
                                                                <aside class="tasty-fonts-access-note tasty-fonts-access-note--external">
                                                                    <span class="tasty-fonts-access-note-label"><?php esc_html_e('Managed in Adobe Fonts', 'tasty-fonts'); ?></span>
                                                                    <p class="tasty-fonts-muted"><?php esc_html_e('Domains and enabled families still live in Adobe Fonts. Resync here after changing the web project.', 'tasty-fonts'); ?></p>
                                                                    <a class="tasty-fonts-access-link" href="<?php echo esc_url($adobeProjectLink); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open Adobe Fonts Web Projects', 'tasty-fonts'); ?></a>
                                                                </aside>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </section>

                                        <section class="tasty-fonts-source-card tasty-fonts-source-card--secondary tasty-fonts-import-panel tasty-fonts-import-panel--detected">
                                            <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                <span class="tasty-fonts-panel-kicker"><?php esc_html_e('From the Project', 'tasty-fonts'); ?></span>
                                                <h4><?php esc_html_e('Detected Families', 'tasty-fonts'); ?></h4>
                                            </div>

                                            <?php if ($adobeDetectedFamilies === []): ?>
                                                <div class="tasty-fonts-empty tasty-fonts-empty--panel"><?php esc_html_e('No Adobe families detected yet.', 'tasty-fonts'); ?></div>
                                            <?php else: ?>
                                                <div class="tasty-fonts-adobe-family-list">
                                                    <?php foreach ($adobeDetectedFamilies as $family): ?>
                                                        <?php $this->renderAdobeFamilyCard($family); ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </section>
                                    </div>
                                </section>

                                <section class="tasty-fonts-add-font-panel" id="tasty-fonts-add-font-panel-upload" data-tab-group="add-font" data-tab-panel="upload" role="tabpanel" aria-labelledby="tasty-fonts-add-font-tab-upload" hidden>
                                    <div class="tasty-fonts-source-shell tasty-fonts-source-shell--upload">
                                        <section class="tasty-fonts-source-card tasty-fonts-source-card--status tasty-fonts-upload-brief">
                                            <div class="tasty-fonts-source-status-row tasty-fonts-source-status-row--upload">
                                                <div class="tasty-fonts-source-status-copy">
                                                    <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Source Setup', 'tasty-fonts'); ?></span>
                                                    <div class="tasty-fonts-source-status-title-row">
                                                        <h4><?php esc_html_e('Upload Files', 'tasty-fonts'); ?></h4>
                                                    </div>
                                                    <p class="tasty-fonts-muted tasty-fonts-source-summary"><?php esc_html_e('Build one family at a time, keep every face for that typeface together, and add another family only when you are uploading a separate typeface.', 'tasty-fonts'); ?></p>
                                                </div>
                                                <aside class="tasty-fonts-access-note tasty-fonts-access-note--external tasty-fonts-access-note--upload">
                                                    <span class="tasty-fonts-access-note-label"><?php esc_html_e('Auto-detect', 'tasty-fonts'); ?></span>
                                                    <p class="tasty-fonts-muted"><?php esc_html_e('Filenames like Abel-400.woff2 or Inter-700-italic.woff2 can suggest the family name, weight, and style automatically.', 'tasty-fonts'); ?></p>
                                                </aside>
                                            </div>
                                        </section>

                                        <section class="tasty-fonts-source-card tasty-fonts-source-card--task tasty-fonts-upload-builder">
                                            <div class="tasty-fonts-panel-head tasty-fonts-panel-head--workflow">
                                                <span class="tasty-fonts-panel-kicker"><?php esc_html_e('Family Builder', 'tasty-fonts'); ?></span>
                                                <h4><?php esc_html_e('Assemble Local Files', 'tasty-fonts'); ?></h4>
                                            </div>

                                            <form id="tasty-fonts-upload-form" class="tasty-fonts-upload-form tasty-fonts-upload-form--builder" novalidate>
                                                <div id="tasty-fonts-upload-groups" class="tasty-fonts-upload-groups">
                                                    <?php $this->renderUploadFamilyGroup(); ?>
                                                </div>

                                                <template id="tasty-fonts-upload-group-template">
                                                    <?php $this->renderUploadFamilyGroup(); ?>
                                                </template>

                                                <template id="tasty-fonts-upload-row-template">
                                                    <?php $this->renderUploadFaceRow(); ?>
                                                </template>

                                                <div class="tasty-fonts-upload-actions">
                                                    <div class="tasty-fonts-actions tasty-fonts-actions--upload-builder">
                                                        <button type="button" class="button" id="tasty-fonts-upload-add-family"><?php esc_html_e('Add Another Family', 'tasty-fonts'); ?></button>
                                                    </div>
                                                    <button type="submit" class="button button-primary" id="tasty-fonts-upload-submit"><?php esc_html_e('Upload to Library', 'tasty-fonts'); ?></button>
                                                </div>

                                                <div id="tasty-fonts-upload-status" class="tasty-fonts-import-status" aria-live="polite"></div>
                                            </form>
                                        </section>
                                    </div>
                                </section>
                            </div>
                        </div>

                        <?php if ($catalog === []): ?>
                            <div class="tasty-fonts-empty-state tasty-fonts-empty-state--rich tasty-fonts-empty-state--library">
                                <div class="tasty-fonts-empty-state-body">
                                    <h3 class="tasty-fonts-empty-state-title"><?php esc_html_e('Your library is empty', 'tasty-fonts'); ?></h3>
                                    <p class="tasty-fonts-empty-state-copy"><?php esc_html_e('Import a Google family, connect an Adobe Fonts project, or upload local files to start building your library and assigning heading and body roles.', 'tasty-fonts'); ?></p>
                                </div>
                                <div class="tasty-fonts-empty-state-actions">
                                    <button type="button" class="button button-primary" data-open-add-fonts aria-controls="tasty-fonts-add-font-panel"><?php esc_html_e('Add Fonts', 'tasty-fonts'); ?></button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div id="tasty-fonts-library-empty-filtered" class="tasty-fonts-empty tasty-fonts-empty-state" hidden><?php esc_html_e('No fonts match the current filters.', 'tasty-fonts'); ?></div>
                            <div class="tasty-fonts-library-grid">
                                <?php foreach ($catalog as $family): ?>
                                    <?php $this->renderFamilyRow($family, $roles, $familyFallbacks, $previewText); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

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
                                            <button type="submit" class="button" name="tasty_fonts_clear_log" value="1"><?php esc_html_e('Clear Log', 'tasty-fonts'); ?></button>
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
                    <?php $this->renderFallbackSuggestionList(); ?>
                    <div id="tasty-fonts-help-tooltip-layer" class="tasty-fonts-help-tooltip-layer" role="tooltip" hidden></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderLogList(array $entries, string $className = 'tasty-fonts-log-list'): void
    {
        ?>
        <ol class="<?php echo esc_attr($className); ?>" data-activity-list>
            <?php foreach ($entries as $entry): ?>
                <?php
                $time = (string) ($entry['time'] ?? '');
                $actor = trim((string) ($entry['actor'] ?? ''));
                $message = (string) ($entry['message'] ?? '');
                $searchValue = trim(implode(' ', array_filter([$time, $actor, $message], static fn ($value): bool => $value !== '')));
                ?>
                <li
                    class="tasty-fonts-log-item"
                    data-activity-entry
                    data-activity-actor="<?php echo esc_attr($actor); ?>"
                    data-activity-search="<?php echo esc_attr($searchValue); ?>"
                >
                    <span class="tasty-fonts-log-marker" aria-hidden="true"></span>
                    <div class="tasty-fonts-log-content">
                        <div class="tasty-fonts-log-message"><?php echo esc_html($message); ?></div>
                        <div class="tasty-fonts-log-meta">
                            <span class="tasty-fonts-log-time"><?php echo esc_html($time); ?></span>
                            <?php if ($actor !== ''): ?>
                                <span class="tasty-fonts-log-actor"><?php echo esc_html($actor); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php
    }

    private function renderAdobeFamilyCard(array $family): void
    {
        $familyName = (string) ($family['family'] ?? '');

        if ($familyName === '') {
            return;
        }

        $faceSummaryLabels = $this->buildFamilyFaceSummaryLabels((array) ($family['faces'] ?? []));
        ?>
        <article class="tasty-fonts-adobe-family-card">
            <div class="tasty-fonts-adobe-family-head">
                <strong><?php echo esc_html($familyName); ?></strong>
                <span class="tasty-fonts-badge"><?php esc_html_e('Adobe', 'tasty-fonts'); ?></span>
            </div>
            <?php if ($faceSummaryLabels !== []): ?>
                <div class="tasty-fonts-face-pills">
                    <?php foreach ($faceSummaryLabels as $label): ?>
                        <span class="tasty-fonts-face-pill"><?php echo esc_html($label); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    private function renderFamilyRow(array $family, array $roles, array $familyFallbacks, string $previewText): void
    {
        $familyName = (string) ($family['family'] ?? '');
        $familySlug = (string) ($family['slug'] ?? FontUtils::slugify($familyName));
        $isHeading = ($roles['heading'] ?? '') === $familyName;
        $isBody = ($roles['body'] ?? '') === $familyName;
        $isRoleFamily = $isHeading || $isBody;
        $sourceTokens = $this->buildFamilySourceTokens((array) ($family['sources'] ?? []), $isRoleFamily);
        $deleteBlockedMessage = $this->buildDeleteBlockedMessage($familyName, $isHeading, $isBody);
        $deleteBlockedMessageHeading = $this->buildDeleteBlockedMessage($familyName, true, false);
        $deleteBlockedMessageBody = $this->buildDeleteBlockedMessage($familyName, false, true);
        $deleteBlockedMessageBoth = $this->buildDeleteBlockedMessage($familyName, true, true);
        $savedFallback = FontUtils::sanitizeFallback((string) ($familyFallbacks[$familyName] ?? 'sans-serif'));
        $defaultStack = FontUtils::buildFontStack($familyName, $savedFallback);
        $facePreviewText = $this->buildFacePreviewText($previewText);
        $faceSummaryLabels = $this->buildFamilyFaceSummaryLabels((array) ($family['faces'] ?? []));
        $visibleFaceSummaryLabels = array_slice($faceSummaryLabels, 0, 4);
        $hiddenFaceSummaryCount = max(0, count($faceSummaryLabels) - count($visibleFaceSummaryLabels));
        $faceCount = count((array) ($family['faces'] ?? []));
        $isExpanded = false;
        $detailsId = 'tasty-fonts-family-details-' . sanitize_html_class($familySlug !== '' ? $familySlug : FontUtils::slugify($familyName));
        ?>
        <article
            class="tasty-fonts-row tasty-fonts-font-card <?php echo $isRoleFamily ? 'is-active' : ''; ?> <?php echo $isExpanded ? 'is-expanded' : ''; ?>"
            data-font-row
            data-font-name="<?php echo esc_attr(strtolower($familyName)); ?>"
            data-font-family="<?php echo esc_attr($familyName); ?>"
            data-font-sources="<?php echo esc_attr(implode(' ', $sourceTokens)); ?>"
        >
            <div class="tasty-fonts-row-head">
                <div class="tasty-fonts-font-card-main">
                    <div class="tasty-fonts-font-card-top">
                        <div class="tasty-fonts-font-primary">
                            <div class="tasty-fonts-font-identity">
                                <div class="tasty-fonts-font-identity-top">
                                    <div class="tasty-fonts-font-title-row">
                                        <h3><?php echo esc_html($familyName); ?></h3>
                                        <button
                                            type="button"
                                            class="tasty-fonts-stack-copy"
                                            data-copy-text="<?php echo esc_attr($defaultStack); ?>"
                                            data-copy-success="<?php esc_attr_e('Font stack copied.', 'tasty-fonts'); ?>"
                                            data-copy-static-label="1"
                                            aria-label="<?php echo esc_attr(sprintf(__('Copy font stack for %s', 'tasty-fonts'), $familyName)); ?>"
                                            title="<?php echo esc_attr($defaultStack); ?>"
                                        >
                                            <?php echo esc_html($defaultStack); ?>
                                        </button>
                                    </div>
                                    <div class="tasty-fonts-badges">
                                        <?php foreach ((array) ($family['sources'] ?? []) as $source): ?>
                                            <span class="tasty-fonts-badge"><?php echo esc_html($this->buildFamilySourceLabel((string) $source)); ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($isHeading): ?>
                                            <span class="tasty-fonts-badge is-role"><?php esc_html_e('Heading', 'tasty-fonts'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($isBody): ?>
                                            <span class="tasty-fonts-badge is-role"><?php esc_html_e('Body', 'tasty-fonts'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($visibleFaceSummaryLabels !== []): ?>
                                <div class="tasty-fonts-font-loaded">
                                    <div class="tasty-fonts-face-pills">
                                        <?php foreach ($visibleFaceSummaryLabels as $label): ?>
                                            <span class="tasty-fonts-face-pill"><?php echo esc_html($label); ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($hiddenFaceSummaryCount > 0): ?>
                                            <span class="tasty-fonts-face-pill is-muted">
                                                <?php echo esc_html(sprintf(__('+%d more', 'tasty-fonts'), $hiddenFaceSummaryCount)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="tasty-fonts-font-inline-preview" role="group" aria-label="<?php echo esc_attr(sprintf(__('Preview for %s', 'tasty-fonts'), $familyName)); ?>">
                                <span class="tasty-fonts-font-inline-preview-label"><?php esc_html_e('Preview', 'tasty-fonts'); ?></span>
                                <div
                                    class="tasty-fonts-font-inline-preview-text"
                                    data-font-preview-family="<?php echo esc_attr($familyName); ?>"
                                    style="font-family:<?php echo esc_attr($defaultStack); ?>;"
                                >
                                    <?php echo esc_html($facePreviewText); ?>
                                </div>
                            </div>
                            <div class="tasty-fonts-font-specimen" role="group" aria-label="<?php echo esc_attr(sprintf(__('Preview for %s', 'tasty-fonts'), $familyName)); ?>">
                                <span class="tasty-fonts-font-specimen-label"><?php esc_html_e('Preview', 'tasty-fonts'); ?></span>
                                <div
                                    class="tasty-fonts-font-specimen-display"
                                    data-font-preview-family="<?php echo esc_attr($familyName); ?>"
                                    style="font-family:<?php echo esc_attr($defaultStack); ?>;"
                                >
                                    <?php echo esc_html($facePreviewText); ?>
                                </div>
                            </div>
                        </div>

                        <div class="tasty-fonts-font-sidebar">
                            <div class="tasty-fonts-family-meta">
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
                                            class="button tasty-fonts-family-fallback-save"
                                            data-family-fallback-save
                                        >
                                            <?php esc_html_e('Save Fallback', 'tasty-fonts'); ?>
                                        </button>
                                    </div>
                                    <p class="tasty-fonts-family-fallback-feedback" data-family-fallback-feedback aria-live="polite" hidden></p>
                                </form>
                            </div>

                            <div class="tasty-fonts-font-actions">
                                <div class="tasty-fonts-font-actions-primary">
                                    <div class="tasty-fonts-button-with-help">
                                        <button
                                            type="button"
                                            class="button tasty-fonts-role-assign-button <?php echo $isHeading ? 'is-current' : ''; ?>"
                                            data-role-assign="heading"
                                            data-font-family="<?php echo esc_attr($familyName); ?>"
                                            data-active-label="<?php echo esc_attr__('Heading (Selected)', 'tasty-fonts'); ?>"
                                            data-idle-label="<?php echo esc_attr__('Select Heading', 'tasty-fonts'); ?>"
                                            aria-pressed="<?php echo esc_attr($isHeading ? 'true' : 'false'); ?>"
                                        >
                                            <span class="tasty-fonts-role-assign-label"><?php echo $isHeading ? esc_html__('Heading (Selected)', 'tasty-fonts') : esc_html__('Select Heading', 'tasty-fonts'); ?></span>
                                        </button>
                                        <?php $this->renderHelpTip(__('Assign this family to the heading role and save it as the new draft immediately. Apply sitewide when you want the live CSS updated.', 'tasty-fonts'), __('Select Heading', 'tasty-fonts')); ?>
                                    </div>
                                    <div class="tasty-fonts-button-with-help">
                                        <button
                                            type="button"
                                            class="button tasty-fonts-role-assign-button <?php echo $isBody ? 'is-current' : ''; ?>"
                                            data-role-assign="body"
                                            data-font-family="<?php echo esc_attr($familyName); ?>"
                                            data-active-label="<?php echo esc_attr__('Body (Selected)', 'tasty-fonts'); ?>"
                                            data-idle-label="<?php echo esc_attr__('Select Body', 'tasty-fonts'); ?>"
                                            aria-pressed="<?php echo esc_attr($isBody ? 'true' : 'false'); ?>"
                                        >
                                            <span class="tasty-fonts-role-assign-label"><?php echo $isBody ? esc_html__('Body (Selected)', 'tasty-fonts') : esc_html__('Select Body', 'tasty-fonts'); ?></span>
                                        </button>
                                        <?php $this->renderHelpTip(__('Assign this family to the body role and save it as the new draft immediately. Apply sitewide when you want the live CSS updated.', 'tasty-fonts'), __('Select Body', 'tasty-fonts')); ?>
                                    </div>
                                </div>
                                <div class="tasty-fonts-font-actions-secondary">
                                    <button
                                        type="button"
                                        class="button tasty-fonts-disclosure-button tasty-fonts-disclosure-button--card"
                                        data-disclosure-toggle="<?php echo esc_attr($detailsId); ?>"
                                        data-expanded-label="<?php echo esc_attr__('Details', 'tasty-fonts'); ?>"
                                        data-collapsed-label="<?php echo esc_attr__('Details', 'tasty-fonts'); ?>"
                                        aria-expanded="<?php echo esc_attr($isExpanded ? 'true' : 'false'); ?>"
                                        aria-controls="<?php echo esc_attr($detailsId); ?>"
                                    >
                                        <?php esc_html_e('Details', 'tasty-fonts'); ?>
                                    </button>
                                    <form method="post" class="tasty-fonts-delete-form">
                                        <?php wp_nonce_field('tasty_fonts_delete_family'); ?>
                                        <input type="hidden" name="tasty_fonts_delete_family" value="1">
                                        <input type="hidden" name="tasty_fonts_family_slug" value="<?php echo esc_attr($familySlug); ?>">
                                        <button
                                            type="submit"
                                            class="button tasty-fonts-button-danger <?php echo $isRoleFamily ? 'is-disabled' : ''; ?>"
                                            data-delete-family="<?php echo esc_attr($familyName); ?>"
                                            data-delete-ready-title="<?php echo esc_attr(__('Delete this family and remove its files from uploads/fonts.', 'tasty-fonts')); ?>"
                                            data-delete-blocked-heading="<?php echo esc_attr($deleteBlockedMessageHeading); ?>"
                                            data-delete-blocked-body="<?php echo esc_attr($deleteBlockedMessageBody); ?>"
                                            data-delete-blocked-both="<?php echo esc_attr($deleteBlockedMessageBoth); ?>"
                                            aria-disabled="<?php echo esc_attr($isRoleFamily ? 'true' : 'false'); ?>"
                                            title="<?php echo esc_attr($deleteBlockedMessage !== '' ? $deleteBlockedMessage : __('Delete this family and remove its files from uploads/fonts.', 'tasty-fonts')); ?>"
                                            <?php if ($deleteBlockedMessage !== '') : ?>
                                                data-delete-blocked="<?php echo esc_attr($deleteBlockedMessage); ?>"
                                            <?php endif; ?>
                                        >
                                            <?php esc_html_e('Delete', 'tasty-fonts'); ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="<?php echo esc_attr($detailsId); ?>" class="tasty-fonts-family-details" <?php echo $isExpanded ? '' : 'hidden'; ?>>
                <table class="widefat striped tasty-fonts-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Weight', 'tasty-fonts'); ?></th>
                            <th><?php esc_html_e('Style', 'tasty-fonts'); ?></th>
                            <th><?php esc_html_e('Preview', 'tasty-fonts'); ?></th>
                            <th><?php esc_html_e('Source', 'tasty-fonts'); ?></th>
                            <th><?php esc_html_e('Storage', 'tasty-fonts'); ?></th>
                            <th><?php esc_html_e('Formats', 'tasty-fonts'); ?></th>
                            <th><?php esc_html_e('Files', 'tasty-fonts'); ?></th>
                            <th><?php esc_html_e('Action', 'tasty-fonts'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ((array) ($family['faces'] ?? []) as $face): ?>
                            <?php
                            $faceWeight = (string) ($face['weight'] ?? '400');
                            $faceStyle = (string) ($face['style'] ?? 'normal');
                            $faceSource = (string) ($face['source'] ?? 'local');
                            $faceUnicodeRange = (string) ($face['unicode_range'] ?? '');
                            $deleteVariantBlockedMessage = ($faceCount <= 1 && $isRoleFamily)
                                ? $this->buildDeleteLastVariantBlockedMessage($familyName, $isHeading, $isBody)
                                : '';
                            ?>
                            <tr>
                                <td data-column-label="<?php esc_attr_e('Weight', 'tasty-fonts'); ?>"><?php echo esc_html($faceWeight); ?></td>
                                <td data-column-label="<?php esc_attr_e('Style', 'tasty-fonts'); ?>"><?php echo esc_html($faceStyle); ?></td>
                                <td class="tasty-fonts-face-preview-cell" data-column-label="<?php esc_attr_e('Preview', 'tasty-fonts'); ?>">
                                    <div
                                        class="tasty-fonts-face-preview"
                                        data-font-preview-family="<?php echo esc_attr($familyName); ?>"
                                        style="font-family:<?php echo esc_attr($defaultStack); ?>; font-weight:<?php echo esc_attr($faceWeight); ?>; font-style:<?php echo esc_attr($faceStyle); ?>;"
                                    >
                                        <?php echo esc_html($facePreviewText); ?>
                                    </div>
                                </td>
                                <td data-column-label="<?php esc_attr_e('Source', 'tasty-fonts'); ?>"><?php echo esc_html((string) ucfirst((string) ($face['source'] ?? 'local'))); ?></td>
                                <td data-column-label="<?php esc_attr_e('Storage', 'tasty-fonts'); ?>"><?php echo esc_html($this->buildFaceStorageSummary((array) $face)); ?></td>
                                <td data-column-label="<?php esc_attr_e('Formats', 'tasty-fonts'); ?>">
                                    <?php foreach (array_keys((array) ($face['files'] ?? [])) as $format): ?>
                                        <span class="tasty-fonts-chip"><?php echo esc_html(strtoupper((string) $format)); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td data-column-label="<?php esc_attr_e('Files', 'tasty-fonts'); ?>">
                                    <?php foreach ((array) ($face['paths'] ?? []) as $format => $path): ?>
                                        <div class="tasty-fonts-file-path">
                                            <strong><?php echo esc_html(strtoupper((string) $format)); ?>:</strong>
                                            <div class="tasty-fonts-code"><?php echo esc_html(FontUtils::compactRelativePath((string) $path)); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td data-column-label="<?php esc_attr_e('Action', 'tasty-fonts'); ?>">
                                    <form method="post" class="tasty-fonts-delete-form tasty-fonts-delete-form--variant">
                                        <?php wp_nonce_field('tasty_fonts_delete_variant'); ?>
                                        <input type="hidden" name="tasty_fonts_delete_variant" value="1">
                                        <input type="hidden" name="tasty_fonts_family_slug" value="<?php echo esc_attr($familySlug); ?>">
                                        <input type="hidden" name="tasty_fonts_face_weight" value="<?php echo esc_attr($faceWeight); ?>">
                                        <input type="hidden" name="tasty_fonts_face_style" value="<?php echo esc_attr($faceStyle); ?>">
                                        <input type="hidden" name="tasty_fonts_face_source" value="<?php echo esc_attr($faceSource); ?>">
                                        <input type="hidden" name="tasty_fonts_face_unicode_range" value="<?php echo esc_attr($faceUnicodeRange); ?>">
                                        <button
                                            type="submit"
                                            class="button button-small tasty-fonts-button-danger <?php echo $deleteVariantBlockedMessage !== '' ? 'is-disabled' : ''; ?>"
                                            data-delete-variant="1"
                                            data-delete-family-name="<?php echo esc_attr($familyName); ?>"
                                            data-delete-face-weight="<?php echo esc_attr($faceWeight); ?>"
                                            data-delete-face-style="<?php echo esc_attr($faceStyle); ?>"
                                            aria-disabled="<?php echo esc_attr($deleteVariantBlockedMessage !== '' ? 'true' : 'false'); ?>"
                                            title="<?php echo esc_attr($deleteVariantBlockedMessage !== '' ? $deleteVariantBlockedMessage : __('Delete only this saved variant and keep the rest of the family.', 'tasty-fonts')); ?>"
                                            <?php if ($deleteVariantBlockedMessage !== '') : ?>
                                                data-delete-blocked="<?php echo esc_attr($deleteVariantBlockedMessage); ?>"
                                            <?php endif; ?>
                                        >
                                            <?php esc_html_e('Delete Variant', 'tasty-fonts'); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
        <?php
    }

    private function buildDeleteBlockedMessage(string $familyName, bool $isHeading, bool $isBody): string
    {
        if ($isHeading && $isBody) {
            return sprintf(
                __('%s is currently used for both heading and body. Choose different role fonts before deleting it.', 'tasty-fonts'),
                $familyName
            );
        }

        if ($isHeading) {
            return sprintf(
                __('%s is currently used as the heading font. Choose a different heading font before deleting it.', 'tasty-fonts'),
                $familyName
            );
        }

        if ($isBody) {
            return sprintf(
                __('%s is currently used as the body font. Choose a different body font before deleting it.', 'tasty-fonts'),
                $familyName
            );
        }

        return '';
    }

    private function buildDeleteLastVariantBlockedMessage(string $familyName, bool $isHeading, bool $isBody): string
    {
        if ($isHeading && $isBody) {
            return sprintf(
                __('%s is currently assigned to both heading and body, and this is the last saved variant. Choose different role fonts before deleting it.', 'tasty-fonts'),
                $familyName
            );
        }

        if ($isHeading) {
            return sprintf(
                __('%s is currently assigned to heading, and this is the last saved variant. Choose a different heading font before deleting it.', 'tasty-fonts'),
                $familyName
            );
        }

        if ($isBody) {
            return sprintf(
                __('%s is currently assigned to body, and this is the last saved variant. Choose a different body font before deleting it.', 'tasty-fonts'),
                $familyName
            );
        }

        return '';
    }

    private function buildFontVariableReference(string $familyName): string
    {
        $slug = FontUtils::slugify($familyName);

        if ($slug === '') {
            return '';
        }

        return sprintf('var(--font-%s)', $slug);
    }

    private function buildFamilySourceTokens(array $sources, bool $isRoleFamily = false): array
    {
        $tokens = [];

        foreach ($sources as $source) {
            $normalized = strtolower(trim((string) $source));

            if ($normalized === '') {
                continue;
            }

            $tokens[] = $normalized;

            if ($normalized === 'local') {
                $tokens[] = 'uploaded';
            }
        }

        if ($isRoleFamily) {
            $tokens[] = 'used';
        }

        return array_values(array_unique($tokens));
    }

    private function buildFamilySourceLabel(string $source): string
    {
        return match (strtolower(trim($source))) {
            'local' => __('Uploaded', 'tasty-fonts'),
            'google' => __('Google', 'tasty-fonts'),
            'adobe' => __('Adobe', 'tasty-fonts'),
            default => ucfirst(trim($source)),
        };
    }

    private function buildFamilyFaceSummaryLabels(array $faces): array
    {
        $items = [];

        foreach ($faces as $face) {
            $weight = preg_replace('/[^0-9]/', '', (string) ($face['weight'] ?? '400'));
            $weight = $weight !== '' ? $weight : '400';
            $style = FontUtils::normalizeStyle((string) ($face['style'] ?? 'normal'));
            $key = FontUtils::faceAxisKey($weight, $style);

            if (isset($items[$key])) {
                continue;
            }

            $items[$key] = [
                'weight' => (int) $weight,
                'style' => $style,
                'label' => sprintf(
                    '%1$s%2$s',
                    $weight,
                    $style === 'italic' ? ' italic' : ''
                ),
            ];
        }

        usort(
            $items,
            static function (array $left, array $right): int {
                $weightComparison = ($left['weight'] ?? 0) <=> ($right['weight'] ?? 0);

                if ($weightComparison !== 0) {
                    return $weightComparison;
                }

                if (($left['style'] ?? 'normal') === ($right['style'] ?? 'normal')) {
                    return 0;
                }

                return ($left['style'] ?? 'normal') === 'normal' ? -1 : 1;
            }
        );

        return array_values(array_map(static fn (array $item): string => (string) ($item['label'] ?? ''), $items));
    }

    private function buildFacePreviewText(string $previewText): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($previewText));
        $normalized = is_string($normalized) ? $normalized : '';

        if ($normalized === '') {
            return __('The quick brown fox…', 'tasty-fonts');
        }

        return wp_trim_words($normalized, 6, '…');
    }

    private function buildFaceStorageSummary(array $face): string
    {
        $relativePaths = array_filter(
            array_map(
                static fn (mixed $path): string => is_string($path) ? trim($path) : '',
                (array) ($face['paths'] ?? [])
            ),
            'strlen'
        );

        $fileCount = count($relativePaths);

        if ($fileCount === 0) {
            return '—';
        }

        $bytes = 0;

        foreach ($relativePaths as $relativePath) {
            $absolutePath = $this->storage->pathForRelativePath($relativePath);

            if (!is_string($absolutePath) || !is_file($absolutePath)) {
                continue;
            }

            $size = filesize($absolutePath);

            if ($size !== false) {
                $bytes += (int) $size;
            }
        }

        if ($bytes <= 0) {
            return sprintf(
                _n('%d file', '%d files', $fileCount, 'tasty-fonts'),
                $fileCount
            );
        }

        return sprintf(
            _n('%1$d file · %2$s', '%1$d files · %2$s', $fileCount, 'tasty-fonts'),
            $fileCount,
            size_format($bytes)
        );
    }

    private function renderUploadFamilyGroup(): void
    {
        ?>
        <section class="tasty-fonts-upload-group" data-upload-group>
            <div class="tasty-fonts-upload-group-head">
                <div class="tasty-fonts-upload-group-fields">
                    <label class="tasty-fonts-stack-field">
                        <?php $this->renderFieldLabel(__('Family Name', 'tasty-fonts')); ?>
                        <input
                            type="text"
                            class="regular-text"
                            data-upload-group-field="family"
                            placeholder="<?php esc_attr_e('Example: Satoshi', 'tasty-fonts'); ?>"
                        >
                    </label>

                    <label class="tasty-fonts-stack-field">
                        <?php $this->renderFieldLabel(__('Fallback', 'tasty-fonts')); ?>
                        <?php
                        $this->renderFallbackInput(
                            '',
                            'sans-serif',
                            [
                                'data-upload-group-field' => 'fallback',
                                'placeholder' => __('Example: system-ui, sans-serif', 'tasty-fonts'),
                            ]
                        );
                        ?>
                    </label>
                </div>

                <button
                    type="button"
                    class="button tasty-fonts-upload-group-remove"
                    data-upload-remove-group
                >
                    <?php esc_html_e('Remove Family', 'tasty-fonts'); ?>
                </button>
            </div>

            <div class="tasty-fonts-upload-face-shell">
                <div class="tasty-fonts-upload-face-headings" aria-hidden="true">
                    <span><?php esc_html_e('Font File', 'tasty-fonts'); ?></span>
                    <span><?php esc_html_e('Weight', 'tasty-fonts'); ?></span>
                    <span><?php esc_html_e('Style', 'tasty-fonts'); ?></span>
                    <span><?php esc_html_e('Action', 'tasty-fonts'); ?></span>
                </div>

                <div class="tasty-fonts-upload-face-list" data-upload-face-list>
                    <?php $this->renderUploadFaceRow(); ?>
                </div>
            </div>

            <div class="tasty-fonts-upload-group-actions">
                <button type="button" class="button" data-upload-add-face><?php esc_html_e('Add Face', 'tasty-fonts'); ?></button>
            </div>
        </section>
        <?php
    }

    private function renderUploadFaceRow(): void
    {
        ?>
        <div class="tasty-fonts-upload-face-row" data-upload-row>
            <div class="tasty-fonts-upload-face-grid">
                <label class="tasty-fonts-stack-field tasty-fonts-upload-file-field">
                    <span class="screen-reader-text"><?php esc_html_e('Font File', 'tasty-fonts'); ?></span>
                    <span class="tasty-fonts-upload-file-picker">
                        <input
                            type="file"
                            class="tasty-fonts-upload-native-file"
                            data-upload-field="file"
                            accept=".woff2,.woff,.ttf,.otf"
                        >
                        <span class="tasty-fonts-upload-file-button"><?php esc_html_e('Select Font', 'tasty-fonts'); ?></span>
                        <span class="tasty-fonts-upload-file-name" data-upload-file-name><?php esc_html_e('No file chosen', 'tasty-fonts'); ?></span>
                    </span>
                </label>

                <label class="tasty-fonts-stack-field">
                    <span class="screen-reader-text"><?php esc_html_e('Weight', 'tasty-fonts'); ?></span>
                    <select data-upload-field="weight">
                        <?php foreach (range(100, 900, 100) as $weight): ?>
                            <option value="<?php echo esc_attr((string) $weight); ?>" <?php selected((string) $weight, '400'); ?>><?php echo esc_html((string) $weight); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="tasty-fonts-stack-field">
                    <span class="screen-reader-text"><?php esc_html_e('Style', 'tasty-fonts'); ?></span>
                    <select data-upload-field="style">
                        <option value="normal"><?php esc_html_e('Normal', 'tasty-fonts'); ?></option>
                        <option value="italic"><?php esc_html_e('Italic', 'tasty-fonts'); ?></option>
                        <option value="oblique"><?php esc_html_e('Oblique', 'tasty-fonts'); ?></option>
                    </select>
                </label>

                <button
                    type="button"
                    class="button tasty-fonts-upload-row-remove"
                    data-upload-remove
                    aria-label="<?php esc_attr_e('Remove row', 'tasty-fonts'); ?>"
                >
                    <?php esc_html_e('Remove', 'tasty-fonts'); ?>
                </button>
            </div>

            <div class="tasty-fonts-upload-row-foot">
                <button type="button" class="button tasty-fonts-upload-detected" data-upload-detected-apply hidden></button>
                <div class="tasty-fonts-upload-row-status" data-upload-row-status></div>
            </div>
        </div>
        <?php
    }

    private function renderPreviewScene(string $key, string $previewText, array $roles): void
    {
        switch ($key) {
            case 'editorial':
                ?>
                <div class="tasty-fonts-preview-showcase">
                    <div class="tasty-fonts-preview-specimen-board">
                        <aside class="tasty-fonts-preview-specimen-rail">
                            <div class="tasty-fonts-preview-specimen-glyph" data-role-preview="heading">Aa</div>
                            <div class="tasty-fonts-preview-specimen-key">
                                <span class="tasty-fonts-preview-specimen-key-label"><?php esc_html_e('Heading Family', 'tasty-fonts'); ?></span>
                                <strong class="tasty-fonts-preview-specimen-key-value" data-role-preview="heading"><?php echo esc_html((string) ($roles['heading'] ?? '')); ?></strong>
                            </div>
                            <div class="tasty-fonts-preview-specimen-key">
                                <span class="tasty-fonts-preview-specimen-key-label"><?php esc_html_e('Body Family', 'tasty-fonts'); ?></span>
                                <strong class="tasty-fonts-preview-specimen-key-value" data-role-preview="body"><?php echo esc_html((string) ($roles['body'] ?? '')); ?></strong>
                            </div>
                        </aside>

                        <div class="tasty-fonts-preview-specimen-scale">
                            <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--1" data-role-preview="heading"><?php esc_html_e('Heading 1', 'tasty-fonts'); ?></div>
                            <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--2" data-role-preview="heading"><?php esc_html_e('Heading 2', 'tasty-fonts'); ?></div>
                            <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--3" data-role-preview="heading"><?php esc_html_e('Heading 3', 'tasty-fonts'); ?></div>
                            <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--4" data-role-preview="heading"><?php esc_html_e('Heading 4', 'tasty-fonts'); ?></div>
                            <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--5" data-role-preview="heading"><?php esc_html_e('Heading 5', 'tasty-fonts'); ?></div>
                            <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--6" data-role-preview="heading"><?php esc_html_e('Heading 6', 'tasty-fonts'); ?></div>
                        </div>

                        <div class="tasty-fonts-preview-specimen-copy">
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Lead', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <p class="tasty-fonts-preview-specimen-lead" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                                </div>
                            </div>
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Body / 16', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <p class="tasty-fonts-preview-specimen-body-large" data-role-preview="body"><?php esc_html_e('Apparently we had reached a great height in the atmosphere, for the sky was a dead black, and the stars had ceased to twinkle.', 'tasty-fonts'); ?></p>
                                </div>
                            </div>
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Body / 14', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <p class="tasty-fonts-preview-specimen-body" data-role-preview="body"><?php esc_html_e('Apparently we had reached a great height in the atmosphere, for the sky was a dead black, and the stars had ceased to twinkle.', 'tasty-fonts'); ?></p>
                                </div>
                            </div>
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Quote', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <blockquote class="tasty-fonts-preview-specimen-quote" data-role-preview="heading"><?php esc_html_e('“The sky was cloudless and of a deep dark blue.”', 'tasty-fonts'); ?></blockquote>
                                </div>
                            </div>
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Capitalized', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <p class="tasty-fonts-preview-specimen-caps" data-role-preview="body"><?php esc_html_e('Brainstorm alternative ideas', 'tasty-fonts'); ?></p>
                                </div>
                            </div>
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Small', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <p class="tasty-fonts-preview-specimen-small" data-role-preview="body"><?php esc_html_e('Value your time', 'tasty-fonts'); ?></p>
                                </div>
                            </div>
                            <div class="tasty-fonts-preview-specimen-copy-row">
                                <span class="tasty-fonts-preview-specimen-copy-label"><?php esc_html_e('Tiny', 'tasty-fonts'); ?></span>
                                <div class="tasty-fonts-preview-specimen-copy-body">
                                    <p class="tasty-fonts-preview-specimen-tiny" data-role-preview="body"><?php esc_html_e('Nothing is impossible', 'tasty-fonts'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tasty-fonts-preview-support-grid">
                        <article class="tasty-fonts-preview-support-card">
                            <span class="tasty-fonts-preview-support-label" data-role-preview="body"><?php esc_html_e('Hero Lockup', 'tasty-fonts'); ?></span>
                            <h3 class="tasty-fonts-preview-support-title" data-role-preview="heading"><?php esc_html_e('A type pairing that feels intentional at every scale', 'tasty-fonts'); ?></h3>
                            <p class="tasty-fonts-preview-support-copy" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                            <div class="tasty-fonts-preview-support-meta">
                                <span><?php esc_html_e('Landing Page', 'tasty-fonts'); ?></span>
                                <strong data-role-preview="heading"><?php esc_html_e('Ready', 'tasty-fonts'); ?></strong>
                            </div>
                        </article>

                        <article class="tasty-fonts-preview-support-card">
                            <span class="tasty-fonts-preview-support-label" data-role-preview="body"><?php esc_html_e('Feature Module', 'tasty-fonts'); ?></span>
                            <h3 class="tasty-fonts-preview-support-title" data-role-preview="heading"><?php esc_html_e('Clean cards with enough contrast for product copy', 'tasty-fonts'); ?></h3>
                            <p class="tasty-fonts-preview-support-copy" data-role-preview="body"><?php esc_html_e('Use this sample to judge title tone, supporting copy rhythm, and whether the body face stays calm inside UI surfaces.', 'tasty-fonts'); ?></p>
                            <div class="tasty-fonts-preview-support-meta">
                                <span><?php esc_html_e('Surface Check', 'tasty-fonts'); ?></span>
                                <strong data-role-preview="heading"><?php esc_html_e('Balanced', 'tasty-fonts'); ?></strong>
                            </div>
                        </article>

                        <article class="tasty-fonts-preview-support-card">
                            <span class="tasty-fonts-preview-support-label" data-role-preview="body"><?php esc_html_e('Metrics Panel', 'tasty-fonts'); ?></span>
                            <div class="tasty-fonts-preview-support-stats">
                                <div class="tasty-fonts-preview-support-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Visitors', 'tasty-fonts'); ?></span>
                                    <strong data-role-preview="heading">12.4k</strong>
                                </div>
                                <div class="tasty-fonts-preview-support-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Conversion', 'tasty-fonts'); ?></span>
                                    <strong data-role-preview="heading">4.8%</strong>
                                </div>
                                <div class="tasty-fonts-preview-support-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Launch', 'tasty-fonts'); ?></span>
                                    <strong data-role-preview="heading"><?php esc_html_e('Soon', 'tasty-fonts'); ?></strong>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
                <?php
                return;

            case 'card':
                ?>
                <div class="tasty-fonts-preview-card-board">
                    <div class="tasty-fonts-preview-card-gallery">
                        <article class="tasty-fonts-preview-card-frame">
                            <div class="tasty-fonts-preview-card-media">
                                <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                            </div>
                            <div class="tasty-fonts-preview-card-body">
                                <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Feature Card', 'tasty-fonts'); ?></span>
                                <h3 class="tasty-fonts-preview-card-title" data-role-preview="heading"><?php esc_html_e('Title', 'tasty-fonts'); ?></h3>
                                <p class="tasty-fonts-preview-card-subtitle" data-role-preview="body"><?php esc_html_e('Subtitle', 'tasty-fonts'); ?></p>
                                <p class="tasty-fonts-preview-card-copy" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                                <div class="tasty-fonts-preview-card-actions">
                                    <span class="button" aria-hidden="true"><?php esc_html_e('Action', 'tasty-fonts'); ?></span>
                                    <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Action', 'tasty-fonts'); ?></span>
                                </div>
                            </div>
                        </article>

                        <article class="tasty-fonts-preview-card-frame">
                            <div class="tasty-fonts-preview-card-media">
                                <span class="dashicons dashicons-format-gallery" aria-hidden="true"></span>
                            </div>
                            <div class="tasty-fonts-preview-card-body">
                                <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Collection', 'tasty-fonts'); ?></span>
                                <h3 class="tasty-fonts-preview-card-title" data-role-preview="heading"><?php esc_html_e('Modern Layouts', 'tasty-fonts'); ?></h3>
                                <p class="tasty-fonts-preview-card-subtitle" data-role-preview="body"><?php esc_html_e('Structured and calm', 'tasty-fonts'); ?></p>
                                <p class="tasty-fonts-preview-card-copy" data-role-preview="body"><?php esc_html_e('Compare how the chosen heading face holds attention while the body face keeps supporting detail easy to scan.', 'tasty-fonts'); ?></p>
                                <div class="tasty-fonts-preview-card-actions">
                                    <span class="button" aria-hidden="true"><?php esc_html_e('Review', 'tasty-fonts'); ?></span>
                                    <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Select', 'tasty-fonts'); ?></span>
                                </div>
                            </div>
                        </article>

                        <article class="tasty-fonts-preview-card-frame">
                            <div class="tasty-fonts-preview-card-media">
                                <span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
                            </div>
                            <div class="tasty-fonts-preview-card-body">
                                <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Product Card', 'tasty-fonts'); ?></span>
                                <h3 class="tasty-fonts-preview-card-title" data-role-preview="heading"><?php esc_html_e('System-ready', 'tasty-fonts'); ?></h3>
                                <p class="tasty-fonts-preview-card-subtitle" data-role-preview="body"><?php esc_html_e('Useful in real UI', 'tasty-fonts'); ?></p>
                                <p class="tasty-fonts-preview-card-copy" data-role-preview="body"><?php esc_html_e('This view is intentionally compact so you can judge hierarchy, spacing, and button copy without oversized demo content.', 'tasty-fonts'); ?></p>
                                <div class="tasty-fonts-preview-card-actions">
                                    <span class="button" aria-hidden="true"><?php esc_html_e('Later', 'tasty-fonts'); ?></span>
                                    <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Launch', 'tasty-fonts'); ?></span>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
                <?php
                return;

            case 'reading':
                ?>
                <article class="tasty-fonts-preview-reading-sheet">
                    <div class="tasty-fonts-preview-reading-head">
                        <span class="tasty-fonts-preview-reading-label" data-role-preview="body"><?php esc_html_e('Long-Form Reading', 'tasty-fonts'); ?></span>
                        <h3 class="tasty-fonts-preview-reading-title" data-role-preview="heading"><?php esc_html_e('Readable paragraphs with steady rhythm', 'tasty-fonts'); ?></h3>
                    </div>
                    <p class="tasty-fonts-preview-reading-lead" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                    <div class="tasty-fonts-preview-reading-layout">
                        <div class="tasty-fonts-preview-reading-copy">
                            <p data-role-preview="body"><?php esc_html_e('Apparently we had reached a great height in the atmosphere, for the sky was a dead black, and the stars had ceased to twinkle.', 'tasty-fonts'); ?></p>
                            <p data-role-preview="body"><?php esc_html_e('A strong reading font should stay calm across longer passages and still leave enough contrast for section headings and pull quotes.', 'tasty-fonts'); ?></p>
                        </div>
                        <aside class="tasty-fonts-preview-reading-aside">
                            <h4 class="tasty-fonts-preview-reading-aside-title" data-role-preview="heading"><?php esc_html_e('Checklist', 'tasty-fonts'); ?></h4>
                            <ul class="tasty-fonts-preview-reading-list" data-role-preview="body">
                                <li><?php esc_html_e('Paragraph spacing', 'tasty-fonts'); ?></li>
                                <li><?php esc_html_e('Line length at body sizes', 'tasty-fonts'); ?></li>
                                <li><?php esc_html_e('Subheading emphasis', 'tasty-fonts'); ?></li>
                            </ul>
                        </aside>
                    </div>
                </article>
                <?php
                return;

            case 'interface':
            default:
                ?>
                <div class="tasty-fonts-preview-ui-shell">
                    <div class="tasty-fonts-preview-ui-topbar">
                        <span class="tasty-fonts-preview-ui-topbar-label" data-role-preview="body"><?php esc_html_e('Workspace', 'tasty-fonts'); ?></span>
                        <span class="tasty-fonts-preview-ui-topbar-status"><?php esc_html_e('Live', 'tasty-fonts'); ?></span>
                    </div>
                    <div class="tasty-fonts-preview-ui-grid">
                        <div class="tasty-fonts-preview-ui-panel">
                            <span class="tasty-fonts-preview-ui-label" data-role-preview="body"><?php esc_html_e('Project Name', 'tasty-fonts'); ?></span>
                            <h3 class="tasty-fonts-preview-ui-title" data-role-preview="heading"><?php esc_html_e('Launch Planning', 'tasty-fonts'); ?></h3>
                            <p class="tasty-fonts-preview-ui-copy" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                        </div>
                        <div class="tasty-fonts-preview-ui-panel">
                            <span class="tasty-fonts-preview-ui-label" data-role-preview="body"><?php esc_html_e('Metrics', 'tasty-fonts'); ?></span>
                            <div class="tasty-fonts-preview-ui-stats">
                                <div class="tasty-fonts-preview-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Visitors', 'tasty-fonts'); ?></span>
                                    <strong data-role-preview="heading">12.4k</strong>
                                </div>
                                <div class="tasty-fonts-preview-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Signups', 'tasty-fonts'); ?></span>
                                    <strong data-role-preview="heading">318</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tasty-fonts-preview-ui-list">
                        <div class="tasty-fonts-preview-ui-list-row">
                            <span data-role-preview="body"><?php esc_html_e('Headline Lockup', 'tasty-fonts'); ?></span>
                            <strong data-role-preview="heading"><?php esc_html_e('Approved', 'tasty-fonts'); ?></strong>
                        </div>
                        <div class="tasty-fonts-preview-ui-list-row">
                            <span data-role-preview="body"><?php esc_html_e('Landing Page Copy', 'tasty-fonts'); ?></span>
                            <strong data-role-preview="heading"><?php esc_html_e('In Review', 'tasty-fonts'); ?></strong>
                        </div>
                    </div>
                    <div class="tasty-fonts-preview-ui-actions">
                        <span class="button" aria-hidden="true"><?php esc_html_e('Save Draft', 'tasty-fonts'); ?></span>
                        <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Publish', 'tasty-fonts'); ?></span>
                    </div>
                </div>
                <?php
                return;
        }
    }

    private function renderNotices(array $toasts): void
    {
        if ($toasts === []) {
            return;
        }

        ?>
        <div class="tasty-fonts-toast-stack" aria-live="polite" aria-atomic="true">
            <?php foreach ($toasts as $toast): ?>
                <div
                    class="tasty-fonts-toast is-<?php echo esc_attr((string) ($toast['tone'] ?? 'success')); ?>"
                    data-toast
                    data-toast-tone="<?php echo esc_attr((string) ($toast['tone'] ?? 'success')); ?>"
                    role="<?php echo esc_attr((string) ($toast['role'] ?? 'status')); ?>"
                >
                    <div class="tasty-fonts-toast-message"><?php echo esc_html((string) ($toast['message'] ?? '')); ?></div>
                    <button type="button" class="tasty-fonts-toast-dismiss" data-toast-dismiss aria-label="<?php esc_attr_e('Dismiss notification', 'tasty-fonts'); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function renderHelpTip(string $copy, string $label = ''): void
    {
        $tooltipId = wp_unique_id('tasty-fonts-help-');
        $ariaLabel = $label !== ''
            ? sprintf(
                /* translators: %s: UI label */
                __('More information about %s', 'tasty-fonts'),
                $label
            )
            : __('More information', 'tasty-fonts');
        ?>
        <span class="tasty-fonts-help-wrap">
            <button
                type="button"
                class="tasty-fonts-help-button"
                aria-label="<?php echo esc_attr($ariaLabel); ?>"
                aria-describedby="<?php echo esc_attr($tooltipId); ?>"
                aria-controls="tasty-fonts-help-tooltip-layer"
                data-help-tooltip="<?php echo esc_attr($copy); ?>"
            >
                <span class="tasty-fonts-help-glyph" aria-hidden="true">
                    <svg viewBox="0 0 16 16" focusable="false">
                        <circle cx="8" cy="8" r="6.25"></circle>
                        <circle class="tasty-fonts-help-glyph-dot" cx="8" cy="4.5" r="1"></circle>
                        <path d="M8 7v4"></path>
                    </svg>
                </span>
            </button>
            <span id="<?php echo esc_attr($tooltipId); ?>" class="screen-reader-text"><?php echo esc_html($copy); ?></span>
        </span>
        <?php
    }

    private function renderSectionHeading(string $tag, string $title, string $help, string $copy = ''): void
    {
        ?>
        <div class="tasty-fonts-section-heading">
            <div class="tasty-fonts-section-title-row">
                <<?php echo esc_html($tag); ?> class="tasty-fonts-section-title"><?php echo esc_html($title); ?></<?php echo esc_html($tag); ?>>
                <?php if ($help !== '') : ?>
                    <?php $this->renderHelpTip($help, $title); ?>
                <?php endif; ?>
            </div>
            <?php if ($copy !== ''): ?>
                <p class="tasty-fonts-section-copy"><?php echo esc_html($copy); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderFieldLabel(string $label, string $help = ''): void
    {
        ?>
        <span class="tasty-fonts-field-label-row">
            <span class="tasty-fonts-field-label-text"><?php echo esc_html($label); ?></span>
            <?php if ($help !== ''): ?>
                <?php $this->renderHelpTip($help, $label); ?>
            <?php endif; ?>
        </span>
        <?php
    }

    private function renderFallbackInput(string $name, string $value, array $attributes = []): void
    {
        $className = 'regular-text';

        if (!empty($attributes['class']) && is_string($attributes['class'])) {
            $className .= ' ' . trim($attributes['class']);
        }

        $inputAttributes = array_merge(
            [
                'type' => 'text',
                'list' => 'tasty-fonts-fallback-options',
                'value' => FontUtils::sanitizeFallback($value),
                'class' => $className,
                'spellcheck' => 'false',
                'autocomplete' => 'off',
                'aria-autocomplete' => 'list',
            ],
            $attributes
        );

        if ($name !== '') {
            $inputAttributes['name'] = $name;
        }

        echo '<span class="tasty-fonts-combobox-field">';
        echo '<input';

        foreach ($inputAttributes as $key => $attributeValue) {
            if ($attributeValue === false || $attributeValue === null || $attributeValue === '') {
                continue;
            }

            if ($attributeValue === true) {
                echo ' ' . esc_attr((string) $key);
                continue;
            }

            echo ' ' . esc_attr((string) $key) . '="' . esc_attr((string) $attributeValue) . '"';
        }

        echo '>';
        echo '</span>';
    }

    private function renderFallbackSuggestionList(): void
    {
        ?>
        <datalist id="tasty-fonts-fallback-options">
            <?php foreach (FontUtils::FALLBACK_SUGGESTIONS as $fallback): ?>
                <option value="<?php echo esc_attr($fallback); ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <?php
    }
}
