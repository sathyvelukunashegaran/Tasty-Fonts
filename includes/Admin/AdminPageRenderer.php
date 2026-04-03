<?php

declare(strict_types=1);

namespace EtchFonts\Admin;

use EtchFonts\Support\FontUtils;
use EtchFonts\Support\Storage;

final class AdminPageRenderer
{
    public function __construct(private readonly Storage $storage)
    {
    }

    public function renderPage(array $context): void
    {
        $storage = is_array($context['storage'] ?? null) ? $context['storage'] : null;
        $catalog = is_array($context['catalog'] ?? null) ? $context['catalog'] : [];
        $roles = is_array($context['roles'] ?? null) ? $context['roles'] : [];
        $logs = is_array($context['logs'] ?? null) ? $context['logs'] : [];
        $visibleLogs = is_array($context['visible_logs'] ?? null) ? $context['visible_logs'] : [];
        $olderLogs = is_array($context['older_logs'] ?? null) ? $context['older_logs'] : [];
        $familyFallbacks = is_array($context['family_fallbacks'] ?? null) ? $context['family_fallbacks'] : [];
        $previewText = (string) ($context['preview_text'] ?? '');
        $previewSize = (int) ($context['preview_size'] ?? 32);
        $googleApiEnabled = !empty($context['google_api_enabled']);
        $googleApiSaved = !empty($context['google_api_saved']);
        $googleAccessExpanded = !empty($context['google_access_expanded']);
        $googleStatusLabel = (string) ($context['google_status_label'] ?? '');
        $googleStatusClass = (string) ($context['google_status_class'] ?? '');
        $googleAccessCopy = (string) ($context['google_access_copy'] ?? '');
        $googleSearchDisabledCopy = (string) ($context['google_search_disabled_copy'] ?? '');
        $diagnosticItems = is_array($context['diagnostic_items'] ?? null) ? $context['diagnostic_items'] : [];
        $overviewMetrics = is_array($context['overview_metrics'] ?? null) ? $context['overview_metrics'] : [];
        $outputPanels = is_array($context['output_panels'] ?? null) ? $context['output_panels'] : [];
        $previewPanels = is_array($context['preview_panels'] ?? null) ? $context['preview_panels'] : [];
        $toasts = is_array($context['toasts'] ?? null) ? $context['toasts'] : [];
        ?>
        <div class="wrap etch-fonts-admin">
            <?php $this->renderNotices($toasts); ?>

            <?php if (!$storage): ?>
                <div class="notice notice-error"><p><?php esc_html_e('The uploads/fonts directory could not be initialized.', ETCH_FONTS_TEXT_DOMAIN); ?></p></div>
            <?php else: ?>
                <div class="etch-fonts-shell">
                    <section class="etch-fonts-card etch-fonts-overview-card">
                        <div class="etch-fonts-overview-head">
                            <div class="etch-fonts-hero-copy">
                                <h1><?php esc_html_e('Etch Custom Fonts', ETCH_FONTS_TEXT_DOMAIN); ?></h1>
                                <p class="etch-fonts-hero-text"><?php esc_html_e('Self-hosted typography for Etch, Gutenberg, and the frontend.', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                            </div>
                        </div>

                        <div class="etch-fonts-metrics">
                            <?php foreach ($overviewMetrics as $metric): ?>
                                <article class="etch-fonts-metric">
                                    <div class="etch-fonts-metric-label"><?php echo esc_html((string) ($metric['label'] ?? '')); ?></div>
                                    <div class="etch-fonts-metric-value"><?php echo esc_html((string) ($metric['value'] ?? '')); ?></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="etch-fonts-card etch-fonts-studio-card" id="etch-fonts-roles-studio">
                        <div class="etch-fonts-card-head">
                            <?php
                            $this->renderSectionHeading(
                                'h2',
                                __('Font Roles', ETCH_FONTS_TEXT_DOMAIN),
                                '',
                                __('Choose the heading and body pairing used for saved output and optional sitewide typography.', ETCH_FONTS_TEXT_DOMAIN)
                            );
                            ?>
                        </div>
                        <form method="post">
                            <?php wp_nonce_field('etch_fonts_save_roles'); ?>
                            <input type="hidden" name="etch_fonts_save_roles" value="1">
                            <input type="hidden" id="etch_fonts_action_type" name="etch_fonts_action_type" value="save">
                            <div class="etch-fonts-role-grid">
                                <section class="etch-fonts-role-box">
                                    <div class="etch-fonts-role-box-head">
                                        <?php $this->renderSectionHeading('h3', __('Heading font', ETCH_FONTS_TEXT_DOMAIN), ''); ?>
                                    </div>
                                    <div class="etch-fonts-role-fields">
                                        <label class="etch-fonts-stack-field">
                                            <?php $this->renderFieldLabel(__('Family', ETCH_FONTS_TEXT_DOMAIN)); ?>
                                            <select name="etch_fonts_heading_font" id="etch_fonts_heading_font">
                                                <?php foreach (array_keys($catalog) as $familyName): ?>
                                                    <option value="<?php echo esc_attr((string) $familyName); ?>" <?php selected($roles['heading'] ?? '', $familyName); ?>><?php echo esc_html((string) $familyName); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label class="etch-fonts-stack-field">
                                            <?php $this->renderFieldLabel(__('Fallback', ETCH_FONTS_TEXT_DOMAIN)); ?>
                                            <?php
                                            $this->renderFallbackInput(
                                                'etch_fonts_heading_fallback',
                                                (string) ($roles['heading_fallback'] ?? 'sans-serif'),
                                                [
                                                    'id' => 'etch_fonts_heading_fallback',
                                                    'placeholder' => __('Example: system-ui, sans-serif', ETCH_FONTS_TEXT_DOMAIN),
                                                ]
                                            );
                                            ?>
                                        </label>
                                    </div>
                                </section>

                                <section class="etch-fonts-role-box">
                                    <div class="etch-fonts-role-box-head">
                                        <?php $this->renderSectionHeading('h3', __('Body font', ETCH_FONTS_TEXT_DOMAIN), ''); ?>
                                    </div>
                                    <div class="etch-fonts-role-fields">
                                        <label class="etch-fonts-stack-field">
                                            <?php $this->renderFieldLabel(__('Family', ETCH_FONTS_TEXT_DOMAIN)); ?>
                                            <select name="etch_fonts_body_font" id="etch_fonts_body_font">
                                                <?php foreach (array_keys($catalog) as $familyName): ?>
                                                    <option value="<?php echo esc_attr((string) $familyName); ?>" <?php selected($roles['body'] ?? '', $familyName); ?>><?php echo esc_html((string) $familyName); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label class="etch-fonts-stack-field">
                                            <?php $this->renderFieldLabel(__('Fallback', ETCH_FONTS_TEXT_DOMAIN)); ?>
                                            <?php
                                            $this->renderFallbackInput(
                                                'etch_fonts_body_fallback',
                                                (string) ($roles['body_fallback'] ?? 'sans-serif'),
                                                [
                                                    'id' => 'etch_fonts_body_fallback',
                                                    'placeholder' => __('Example: system-ui, sans-serif', ETCH_FONTS_TEXT_DOMAIN),
                                                ]
                                            );
                                            ?>
                                        </label>
                                    </div>
                                </section>
                            </div>

                            <div class="etch-fonts-role-toolbar">
                                <div class="etch-fonts-role-actions">
                                    <button type="submit" class="button button-primary" onclick="document.getElementById('etch_fonts_action_type').value='apply';"><?php esc_html_e('Save and apply everywhere', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                                    <button type="submit" class="button" onclick="document.getElementById('etch_fonts_action_type').value='save';"><?php esc_html_e('Save roles only', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                                    <button
                                        type="button"
                                        class="button etch-fonts-disclosure-button etch-fonts-disclosure-button--preview"
                                        data-disclosure-toggle="etch-fonts-role-advanced-panel"
                                        data-expanded-label="<?php echo esc_attr__('Hide advanced tools', ETCH_FONTS_TEXT_DOMAIN); ?>"
                                        data-collapsed-label="<?php echo esc_attr__('Open advanced tools', ETCH_FONTS_TEXT_DOMAIN); ?>"
                                        aria-expanded="false"
                                        aria-controls="etch-fonts-role-advanced-panel"
                                    >
                                        <?php esc_html_e('Open advanced tools', ETCH_FONTS_TEXT_DOMAIN); ?>
                                    </button>
                                </div>
                                <div class="etch-fonts-role-stacks">
                                    <span class="etch-fonts-role-stack">
                                        <span class="etch-fonts-role-stack-label"><?php esc_html_e('Heading', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                        <span class="etch-fonts-kbd" id="etch-fonts-role-heading-stack"><?php echo esc_html(FontUtils::buildFontStack((string) ($roles['heading'] ?? ''), (string) ($roles['heading_fallback'] ?? 'sans-serif'))); ?></span>
                                    </span>
                                    <span class="etch-fonts-role-stack">
                                        <span class="etch-fonts-role-stack-label"><?php esc_html_e('Body', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                        <span class="etch-fonts-kbd" id="etch-fonts-role-body-stack"><?php echo esc_html(FontUtils::buildFontStack((string) ($roles['body'] ?? ''), (string) ($roles['body_fallback'] ?? 'sans-serif'))); ?></span>
                                    </span>
                                </div>
                            </div>

                            <div id="etch-fonts-role-advanced-panel" class="etch-fonts-role-advanced-panel" hidden>
                                <div class="etch-fonts-studio-switcher" role="tablist" aria-label="<?php esc_attr_e('Advanced tools', ETCH_FONTS_TEXT_DOMAIN); ?>">
                                    <button
                                        type="button"
                                        class="etch-fonts-studio-tab is-active"
                                        id="etch-fonts-studio-tab-preview"
                                        data-studio-tab="preview"
                                        aria-selected="true"
                                        aria-controls="etch-fonts-studio-panel-preview"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Preview', ETCH_FONTS_TEXT_DOMAIN); ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="etch-fonts-studio-tab"
                                        id="etch-fonts-studio-tab-snippets"
                                        data-studio-tab="snippets"
                                        aria-selected="false"
                                        aria-controls="etch-fonts-studio-panel-snippets"
                                        role="tab"
                                    >
                                        <?php esc_html_e('Snippets', ETCH_FONTS_TEXT_DOMAIN); ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="etch-fonts-studio-tab"
                                        id="etch-fonts-studio-tab-system"
                                        data-studio-tab="system"
                                        aria-selected="false"
                                        aria-controls="etch-fonts-studio-panel-system"
                                        role="tab"
                                    >
                                        <?php esc_html_e('System details', ETCH_FONTS_TEXT_DOMAIN); ?>
                                    </button>
                                </div>

                                <section
                                    id="etch-fonts-studio-panel-preview"
                                    class="etch-fonts-studio-panel is-active"
                                    data-studio-panel="preview"
                                    role="tabpanel"
                                    aria-labelledby="etch-fonts-studio-tab-preview"
                                >
                                    <div
                                        class="etch-fonts-preview-canvas"
                                        id="etch-fonts-preview-canvas"
                                        style="--etch-preview-base: <?php echo esc_attr((string) $previewSize); ?>px;"
                                    >
                                        <div class="etch-fonts-preview-tabs" role="tablist" aria-label="<?php esc_attr_e('Preview scenarios', ETCH_FONTS_TEXT_DOMAIN); ?>">
                                            <?php foreach ($previewPanels as $panel): ?>
                                                <?php $buttonId = 'etch-fonts-preview-tab-' . $panel['key']; ?>
                                                <?php $panelId = 'etch-fonts-preview-panel-' . $panel['key']; ?>
                                                <button
                                                    type="button"
                                                    class="etch-fonts-preview-tab <?php echo !empty($panel['active']) ? 'is-active' : ''; ?>"
                                                    id="<?php echo esc_attr($buttonId); ?>"
                                                    data-preview-tab="<?php echo esc_attr((string) $panel['key']); ?>"
                                                    aria-selected="<?php echo !empty($panel['active']) ? 'true' : 'false'; ?>"
                                                    aria-controls="<?php echo esc_attr($panelId); ?>"
                                                    role="tab"
                                                >
                                                    <?php echo esc_html((string) ($panel['label'] ?? '')); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>

                                        <?php foreach ($previewPanels as $panel): ?>
                                            <?php $buttonId = 'etch-fonts-preview-tab-' . $panel['key']; ?>
                                            <?php $panelId = 'etch-fonts-preview-panel-' . $panel['key']; ?>
                                            <section
                                                id="<?php echo esc_attr($panelId); ?>"
                                                class="etch-fonts-preview-scene etch-fonts-preview-scene--<?php echo esc_attr((string) $panel['key']); ?> <?php echo !empty($panel['active']) ? 'is-active' : ''; ?>"
                                                data-preview-panel="<?php echo esc_attr((string) $panel['key']); ?>"
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
                                    id="etch-fonts-studio-panel-snippets"
                                    class="etch-fonts-studio-panel"
                                    data-studio-panel="snippets"
                                    role="tabpanel"
                                    aria-labelledby="etch-fonts-studio-tab-snippets"
                                    hidden
                                >
                                    <div class="etch-fonts-code-card etch-fonts-code-card--embedded">
                                        <div class="etch-fonts-code-tabs" role="tablist" aria-label="<?php esc_attr_e('Font snippet outputs', ETCH_FONTS_TEXT_DOMAIN); ?>">
                                            <?php foreach ($outputPanels as $panel): ?>
                                                <?php $buttonId = 'etch-fonts-output-tab-' . $panel['key']; ?>
                                                <?php $panelId = 'etch-fonts-output-panel-' . $panel['key']; ?>
                                                <button
                                                    type="button"
                                                    class="etch-fonts-code-tab <?php echo !empty($panel['active']) ? 'is-active' : ''; ?>"
                                                    id="<?php echo esc_attr($buttonId); ?>"
                                                    data-output-tab="<?php echo esc_attr((string) $panel['key']); ?>"
                                                    aria-selected="<?php echo !empty($panel['active']) ? 'true' : 'false'; ?>"
                                                    aria-controls="<?php echo esc_attr($panelId); ?>"
                                                    role="tab"
                                                >
                                                    <?php echo esc_html((string) ($panel['label'] ?? '')); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>

                                        <?php foreach ($outputPanels as $panel): ?>
                                            <?php $buttonId = 'etch-fonts-output-tab-' . $panel['key']; ?>
                                            <?php $panelId = 'etch-fonts-output-panel-' . $panel['key']; ?>
                                            <section
                                                id="<?php echo esc_attr($panelId); ?>"
                                                class="etch-fonts-code-panel <?php echo !empty($panel['active']) ? 'is-active' : ''; ?>"
                                                data-output-panel="<?php echo esc_attr((string) $panel['key']); ?>"
                                                role="tabpanel"
                                                aria-labelledby="<?php echo esc_attr($buttonId); ?>"
                                                <?php echo !empty($panel['active']) ? '' : 'hidden'; ?>
                                            >
                                                <div class="etch-fonts-code-panel-head">
                                                    <span><?php echo esc_html((string) ($panel['label'] ?? '')); ?></span>
                                                    <button type="button" class="button button-small" data-copy-target="<?php echo esc_attr((string) ($panel['target'] ?? '')); ?>"><?php esc_html_e('Copy', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                                                </div>
                                                <textarea id="<?php echo esc_attr((string) ($panel['target'] ?? '')); ?>" class="etch-fonts-output" readonly><?php echo esc_textarea((string) ($panel['value'] ?? '')); ?></textarea>
                                            </section>
                                        <?php endforeach; ?>
                                    </div>
                                </section>

                                <section
                                    id="etch-fonts-studio-panel-system"
                                    class="etch-fonts-studio-panel"
                                    data-studio-panel="system"
                                    role="tabpanel"
                                    aria-labelledby="etch-fonts-studio-tab-system"
                                    hidden
                                >
                                    <div class="etch-fonts-system-details-panel">
                                        <div class="etch-fonts-diagnostics-grid">
                                            <?php foreach ($diagnosticItems as $item): ?>
                                                <div class="etch-fonts-diagnostic-item">
                                                    <div class="etch-fonts-diagnostic-label"><?php echo esc_html((string) ($item['label'] ?? '')); ?></div>
                                                    <div class="<?php echo !empty($item['code']) ? 'etch-fonts-diagnostic-value etch-fonts-code' : 'etch-fonts-diagnostic-value'; ?>">
                                                        <?php echo esc_html((string) ($item['value'] ?? '')); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </form>
                    </section>

                    <section class="etch-fonts-card etch-fonts-library-card" id="etch-fonts-library">
                        <div class="etch-fonts-card-head">
                            <?php
                            $this->renderSectionHeading(
                                'h2',
                                __('Local Library', ETCH_FONTS_TEXT_DOMAIN),
                                __('Browse every self-hosted family, assign roles, inspect files, or add fonts from Google and direct uploads.', ETCH_FONTS_TEXT_DOMAIN)
                            );
                            ?>
                            <div class="etch-fonts-library-tools">
                                <div class="etch-fonts-search-field etch-fonts-search-field--compact">
                                    <label class="screen-reader-text" for="etch-fonts-library-search"><?php esc_html_e('Library filter', ETCH_FONTS_TEXT_DOMAIN); ?></label>
                                    <input
                                        type="search"
                                        id="etch-fonts-library-search"
                                        class="regular-text"
                                        placeholder="<?php esc_attr_e('Filter self-hosted fonts by name', ETCH_FONTS_TEXT_DOMAIN); ?>"
                                        aria-label="<?php esc_attr_e('Filter self-hosted fonts by name', ETCH_FONTS_TEXT_DOMAIN); ?>"
                                    >
                                </div>
                                <div class="etch-fonts-actions etch-fonts-actions--library">
                                    <form method="post">
                                        <?php wp_nonce_field('etch_fonts_rescan_fonts'); ?>
                                        <button type="submit" class="button" name="etch_fonts_rescan_fonts" value="1"><?php esc_html_e('Rescan fonts', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                                    </form>
                                    <button
                                        type="button"
                                        class="button button-primary"
                                        data-disclosure-toggle="etch-fonts-add-font-panel"
                                        data-expanded-label="<?php echo esc_attr__('Hide add font', ETCH_FONTS_TEXT_DOMAIN); ?>"
                                        data-collapsed-label="<?php echo esc_attr__('Add Font', ETCH_FONTS_TEXT_DOMAIN); ?>"
                                        aria-expanded="false"
                                        aria-controls="etch-fonts-add-font-panel"
                                    >
                                        <?php esc_html_e('Add Font', ETCH_FONTS_TEXT_DOMAIN); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="etch-fonts-add-font-panel" class="etch-fonts-import-shell" hidden>
                            <div class="etch-fonts-import-head">
                                <div class="etch-fonts-import-copy">
                                    <h3><?php esc_html_e('Add fonts', ETCH_FONTS_TEXT_DOMAIN); ?></h3>
                                    <p class="etch-fonts-muted"><?php esc_html_e('Search Google Fonts or upload your own files directly into uploads/fonts.', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                                </div>
                            </div>
                            <div class="etch-fonts-add-font-tabs" role="tablist" aria-label="<?php esc_attr_e('Add font source', ETCH_FONTS_TEXT_DOMAIN); ?>">
                                <button type="button" class="button etch-fonts-add-font-tab is-active" data-add-font-tab="google" aria-selected="true"><?php esc_html_e('Google Fonts', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                                <button type="button" class="button etch-fonts-add-font-tab" data-add-font-tab="upload" aria-selected="false"><?php esc_html_e('Upload files', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                            </div>

                            <div class="etch-fonts-add-font-panels">
                                <section class="etch-fonts-add-font-panel is-active" data-add-font-panel="google">
                                    <div class="etch-fonts-google-shell">
                                        <div class="etch-fonts-google-access">
                                            <div class="etch-fonts-google-access-head">
                                                <div class="etch-fonts-google-access-title-row">
                                                    <h4><?php esc_html_e('Google search access', ETCH_FONTS_TEXT_DOMAIN); ?></h4>
                                                    <div class="etch-fonts-google-access-head-actions">
                                                        <span class="etch-fonts-badge <?php echo esc_attr($googleStatusClass); ?>">
                                                            <?php echo esc_html($googleStatusLabel); ?>
                                                        </span>
                                                        <button
                                                            type="button"
                                                            class="button etch-fonts-disclosure-button"
                                                            data-disclosure-toggle="etch-fonts-google-access-panel"
                                                            data-expanded-label="<?php echo esc_attr__('Hide key settings', ETCH_FONTS_TEXT_DOMAIN); ?>"
                                                            data-collapsed-label="<?php echo esc_attr__('Manage key', ETCH_FONTS_TEXT_DOMAIN); ?>"
                                                            aria-expanded="<?php echo $googleAccessExpanded ? 'true' : 'false'; ?>"
                                                            aria-controls="etch-fonts-google-access-panel"
                                                        >
                                                            <?php echo $googleAccessExpanded ? esc_html__('Hide key settings', ETCH_FONTS_TEXT_DOMAIN) : esc_html__('Manage key', ETCH_FONTS_TEXT_DOMAIN); ?>
                                                        </button>
                                                    </div>
                                                </div>
                                                <p class="etch-fonts-muted"><?php echo esc_html($googleAccessCopy); ?></p>
                                            </div>

                                            <div id="etch-fonts-google-access-panel" class="etch-fonts-google-access-panel" <?php echo $googleAccessExpanded ? '' : 'hidden'; ?>>
                                                <form method="post" class="etch-fonts-google-access-form">
                                                    <?php wp_nonce_field('etch_fonts_save_settings'); ?>
                                                    <input type="hidden" name="etch_fonts_save_settings" value="1">
                                                    <input
                                                        type="text"
                                                        class="hidden"
                                                        name="etch_fonts_google_access_username"
                                                        value="<?php echo esc_attr((string) wp_get_current_user()->user_login); ?>"
                                                        autocomplete="username"
                                                        tabindex="-1"
                                                        aria-hidden="true"
                                                    >
                                                    <div class="etch-fonts-google-access-grid">
                                                        <label class="etch-fonts-stack-field etch-fonts-google-access-field">
                                                            <?php $this->renderFieldLabel(__('Google Fonts API key', ETCH_FONTS_TEXT_DOMAIN)); ?>
                                                            <input
                                                                type="password"
                                                                class="regular-text"
                                                                name="google_api_key"
                                                                value=""
                                                                placeholder="<?php echo esc_attr($googleApiSaved ? __('Saved API key. Enter a new key to replace it.', ETCH_FONTS_TEXT_DOMAIN) : __('Paste your Google Fonts API key', ETCH_FONTS_TEXT_DOMAIN)); ?>"
                                                                autocomplete="new-password"
                                                                spellcheck="false"
                                                            >
                                                        </label>

                                                        <div class="etch-fonts-google-access-footer">
                                                            <div class="etch-fonts-settings-buttons">
                                                                <button type="submit" class="button button-primary"><?php esc_html_e('Save key', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                                                                <?php if ($googleApiSaved): ?>
                                                                    <button type="submit" class="button" name="etch_fonts_clear_google_api_key" value="1"><?php esc_html_e('Remove key', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="etch-fonts-google-access-meta">
                                                                <p class="etch-fonts-muted etch-fonts-settings-link">
                                                                    <a href="https://developers.google.com/fonts/docs/developer_api" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Get a Google Fonts API key from the Google Fonts Developer API docs.', ETCH_FONTS_TEXT_DOMAIN); ?></a>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="etch-fonts-google-workflow">
                                            <div class="etch-fonts-search-shell">
                                                <div class="etch-fonts-panel-head">
                                                    <h4><?php esc_html_e('Search the catalog', ETCH_FONTS_TEXT_DOMAIN); ?></h4>
                                                </div>

                                                <label class="etch-fonts-stack-field">
                                                    <span class="screen-reader-text"><?php esc_html_e('Search Google Fonts', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                                    <input
                                                        type="search"
                                                        id="etch-fonts-google-search"
                                                        class="regular-text"
                                                        placeholder="<?php esc_attr_e('Search Google Fonts families', ETCH_FONTS_TEXT_DOMAIN); ?>"
                                                        <?php disabled(!$googleApiEnabled); ?>
                                                    >
                                                </label>
                                                <?php if (!$googleApiEnabled): ?>
                                                    <p class="etch-fonts-muted etch-fonts-import-note"><?php echo esc_html($googleSearchDisabledCopy); ?></p>
                                                <?php endif; ?>
                                                <div id="etch-fonts-google-results" class="etch-fonts-search-results" aria-live="polite">
                                                    <?php if (!$googleApiEnabled): ?>
                                                        <div class="etch-fonts-empty"><?php esc_html_e('Google search is currently disabled.', ETCH_FONTS_TEXT_DOMAIN); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="etch-fonts-import-panel etch-fonts-import-panel--google">
                                                <div class="etch-fonts-panel-head">
                                                    <h4><?php esc_html_e('Choose what to import', ETCH_FONTS_TEXT_DOMAIN); ?></h4>
                                                </div>

                                                <div class="etch-fonts-import-manual-grid">
                                                    <label class="etch-fonts-stack-field">
                                                        <?php $this->renderFieldLabel(__('Family name', ETCH_FONTS_TEXT_DOMAIN)); ?>
                                                        <input type="text" id="etch-fonts-manual-family" class="regular-text" placeholder="<?php esc_attr_e('Example: Inter', ETCH_FONTS_TEXT_DOMAIN); ?>">
                                                    </label>
                                                    <label class="etch-fonts-stack-field">
                                                        <?php $this->renderFieldLabel(__('Manual variants', ETCH_FONTS_TEXT_DOMAIN)); ?>
                                                        <input type="text" id="etch-fonts-manual-variants" class="regular-text" value="regular,700">
                                                    </label>
                                                </div>

                                                <div class="etch-fonts-selected-wrap etch-fonts-selected-wrap--import">
                                                    <div class="etch-fonts-selected-card">
                                                        <?php $this->renderFieldLabel(__('Selected family', ETCH_FONTS_TEXT_DOMAIN)); ?>
                                                        <div id="etch-fonts-selected-family" class="etch-fonts-kbd"><?php esc_html_e('None selected yet', ETCH_FONTS_TEXT_DOMAIN); ?></div>
                                                    </div>
                                                    <div class="etch-fonts-selected-card">
                                                        <?php $this->renderFieldLabel(__('Variants to import', ETCH_FONTS_TEXT_DOMAIN)); ?>
                                                        <div id="etch-fonts-google-variants" class="etch-fonts-variant-list"></div>
                                                    </div>
                                                </div>

                                                <div id="etch-fonts-import-status" class="etch-fonts-import-status" aria-live="polite"></div>

                                                <div class="etch-fonts-actions etch-fonts-actions--import">
                                                    <button type="button" class="button button-primary" id="etch-fonts-import-submit"><?php esc_html_e('Import and self-host', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <section class="etch-fonts-add-font-panel" data-add-font-panel="upload" hidden>
                                    <div class="etch-fonts-upload-shell">
                                        <div class="etch-fonts-upload-intro">
                                            <p class="etch-fonts-muted"><?php esc_html_e('Use one box per family. Add multiple faces inside the same family, or add another family when you want to upload a separate typeface.', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                                            <div class="etch-fonts-upload-tip">
                                                <strong><?php esc_html_e('Quick detect:', ETCH_FONTS_TEXT_DOMAIN); ?></strong>
                                                <span><?php esc_html_e('If a filename follows a pattern like Abel-400.woff2 or Inter-700-italic.woff2, the plugin can suggest the family name, weight, and style automatically.', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                            </div>
                                        </div>

                                        <form id="etch-fonts-upload-form" class="etch-fonts-upload-form" novalidate>
                                            <div id="etch-fonts-upload-groups" class="etch-fonts-upload-groups">
                                                <?php $this->renderUploadFamilyGroup(); ?>
                                            </div>

                                            <template id="etch-fonts-upload-group-template">
                                                <?php $this->renderUploadFamilyGroup(); ?>
                                            </template>

                                            <template id="etch-fonts-upload-row-template">
                                                <?php $this->renderUploadFaceRow(); ?>
                                            </template>

                                            <div class="etch-fonts-upload-actions">
                                                <div class="etch-fonts-actions etch-fonts-actions--upload-builder">
                                                    <button type="button" class="button" id="etch-fonts-upload-add-family"><?php esc_html_e('Add another family', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                                                </div>
                                                <button type="submit" class="button button-primary" id="etch-fonts-upload-submit"><?php esc_html_e('Upload to library', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                                            </div>

                                            <div id="etch-fonts-upload-status" class="etch-fonts-import-status" aria-live="polite"></div>
                                        </form>
                                    </div>
                                </section>
                            </div>
                        </div>

                        <?php if ($catalog === []): ?>
                            <div class="etch-fonts-empty etch-fonts-empty-state"><?php esc_html_e('No supported font files were found yet in uploads/fonts.', ETCH_FONTS_TEXT_DOMAIN); ?></div>
                        <?php else: ?>
                            <div class="etch-fonts-library-grid">
                                <?php foreach ($catalog as $family): ?>
                                    <?php $this->renderFamilyRow($family, $roles, $familyFallbacks, $previewText); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="etch-fonts-card etch-fonts-activity-card">
                        <div class="etch-fonts-card-head">
                            <?php
                            $this->renderSectionHeading(
                                'h2',
                                __('Activity', ETCH_FONTS_TEXT_DOMAIN),
                                __('Recent scans, imports, deletes, and asset refreshes. Newest entries appear first.', ETCH_FONTS_TEXT_DOMAIN)
                            );
                            ?>
                            <form method="post">
                                <?php wp_nonce_field('etch_fonts_clear_log'); ?>
                                <button type="submit" class="button" name="etch_fonts_clear_log" value="1"><?php esc_html_e('Clear log', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                            </form>
                        </div>

                        <?php if ($logs === []): ?>
                            <div class="etch-fonts-empty etch-fonts-empty-state"><?php esc_html_e('No log entries yet.', ETCH_FONTS_TEXT_DOMAIN); ?></div>
                        <?php else: ?>
                            <?php $this->renderLogList($visibleLogs); ?>
                            <?php if ($olderLogs !== []): ?>
                                <div class="etch-fonts-disclosure etch-fonts-activity-more">
                                    <button
                                        type="button"
                                        class="button etch-fonts-disclosure-button"
                                        data-disclosure-toggle="etch-fonts-activity-older"
                                        data-expanded-label="<?php echo esc_attr__('Hide older activity', ETCH_FONTS_TEXT_DOMAIN); ?>"
                                        data-collapsed-label="<?php echo esc_attr(sprintf(__('Show older activity (%d)', ETCH_FONTS_TEXT_DOMAIN), count($olderLogs))); ?>"
                                        aria-expanded="false"
                                        aria-controls="etch-fonts-activity-older"
                                    >
                                        <?php echo esc_html(sprintf(__('Show older activity (%d)', ETCH_FONTS_TEXT_DOMAIN), count($olderLogs))); ?>
                                    </button>
                                    <div id="etch-fonts-activity-older" hidden>
                                        <?php $this->renderLogList($olderLogs, 'etch-fonts-log-list etch-fonts-log-list--older'); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>
                    <?php $this->renderFallbackSuggestionList(); ?>
                    <div id="etch-fonts-help-tooltip-layer" class="etch-fonts-help-tooltip-layer" role="tooltip" hidden></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderLogList(array $entries, string $className = 'etch-fonts-log-list'): void
    {
        ?>
        <ol class="<?php echo esc_attr($className); ?>">
            <?php foreach ($entries as $entry): ?>
                <li class="etch-fonts-log-item">
                    <span class="etch-fonts-log-marker" aria-hidden="true"></span>
                    <div class="etch-fonts-log-content">
                        <div class="etch-fonts-log-meta">
                            <span class="etch-fonts-log-time"><?php echo esc_html((string) ($entry['time'] ?? '')); ?></span>
                            <?php if (!empty($entry['actor'])): ?>
                                <span class="etch-fonts-log-actor"><?php echo esc_html((string) $entry['actor']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div><?php echo esc_html((string) ($entry['message'] ?? '')); ?></div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php
    }

    private function renderFamilyRow(array $family, array $roles, array $familyFallbacks, string $previewText): void
    {
        $familyName = (string) ($family['family'] ?? '');
        $familySlug = (string) ($family['slug'] ?? FontUtils::slugify($familyName));
        $isHeading = ($roles['heading'] ?? '') === $familyName;
        $isBody = ($roles['body'] ?? '') === $familyName;
        $isRoleFamily = $isHeading || $isBody;
        $deleteBlockedMessage = $this->buildDeleteBlockedMessage($familyName, $isHeading, $isBody);
        $savedFallback = FontUtils::sanitizeFallback((string) ($familyFallbacks[$familyName] ?? 'sans-serif'));
        $defaultStack = FontUtils::buildFontStack($familyName, $savedFallback);
        $facePreviewText = $this->buildFacePreviewText($previewText);
        $faceSummaryLabels = $this->buildFamilyFaceSummaryLabels((array) ($family['faces'] ?? []));
        $visibleFaceSummaryLabels = array_slice($faceSummaryLabels, 0, 4);
        $hiddenFaceSummaryCount = max(0, count($faceSummaryLabels) - count($visibleFaceSummaryLabels));
        $isExpanded = false;
        $detailsId = 'etch-fonts-family-details-' . sanitize_html_class($familySlug !== '' ? $familySlug : FontUtils::slugify($familyName));
        ?>
        <article
            class="etch-fonts-row etch-fonts-font-card <?php echo $isRoleFamily ? 'is-active' : ''; ?> <?php echo $isExpanded ? 'is-expanded' : ''; ?>"
            data-font-row
            data-font-name="<?php echo esc_attr(strtolower($familyName)); ?>"
            data-font-family="<?php echo esc_attr($familyName); ?>"
        >
            <div class="etch-fonts-row-head">
                <div class="etch-fonts-font-card-main">
                    <div class="etch-fonts-font-card-top">
                        <div class="etch-fonts-font-primary">
                            <div class="etch-fonts-font-identity">
                                <div class="etch-fonts-font-identity-top">
                                    <h3><?php echo esc_html($familyName); ?></h3>
                                    <div class="etch-fonts-badges">
                                        <?php foreach ((array) ($family['sources'] ?? []) as $source): ?>
                                            <span class="etch-fonts-badge"><?php echo esc_html(ucfirst((string) $source)); ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($isHeading): ?>
                                            <span class="etch-fonts-badge is-role"><?php esc_html_e('Heading', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                        <?php endif; ?>
                                        <?php if ($isBody): ?>
                                            <span class="etch-fonts-badge is-role"><?php esc_html_e('Body', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($visibleFaceSummaryLabels !== []): ?>
                                <div class="etch-fonts-font-loaded">
                                    <div class="etch-fonts-face-pills">
                                        <?php foreach ($visibleFaceSummaryLabels as $label): ?>
                                            <span class="etch-fonts-face-pill"><?php echo esc_html($label); ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($hiddenFaceSummaryCount > 0): ?>
                                            <span class="etch-fonts-face-pill is-muted">
                                                <?php echo esc_html(sprintf(__('+%d more', ETCH_FONTS_TEXT_DOMAIN), $hiddenFaceSummaryCount)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="etch-fonts-font-sidebar">
                            <div class="etch-fonts-family-meta">
                                <form method="post" class="etch-fonts-family-fallback-form" data-family-fallback-form>
                                    <?php wp_nonce_field('etch_fonts_save_family_fallback'); ?>
                                    <input type="hidden" name="etch_fonts_save_family_fallback" value="1">
                                    <input type="hidden" name="etch_fonts_family_name" value="<?php echo esc_attr($familyName); ?>">
                                    <div class="etch-fonts-inline-field-row">
                                        <label class="etch-fonts-inline-field etch-fonts-inline-field--select">
                                            <span class="etch-fonts-field-label"><?php esc_html_e('Fallback', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                            <?php
                                            $this->renderFallbackInput(
                                                'etch_fonts_family_fallback',
                                                $savedFallback,
                                                [
                                                    'class' => 'etch-fonts-fallback-selector',
                                                    'data-font-family' => $familyName,
                                                    'data-saved-value' => $savedFallback,
                                                    'placeholder' => __('Example: system-ui, sans-serif', ETCH_FONTS_TEXT_DOMAIN),
                                                ]
                                            );
                                            ?>
                                        </label>
                                        <button
                                            type="submit"
                                            class="button etch-fonts-family-fallback-save"
                                            data-family-fallback-save
                                        >
                                            <?php esc_html_e('Save', ETCH_FONTS_TEXT_DOMAIN); ?>
                                        </button>
                                    </div>
                                </form>
                                <div class="etch-fonts-stack-chip">
                                    <span class="etch-fonts-field-label"><?php esc_html_e('Stack', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                    <span class="etch-fonts-kbd" data-stack-preview="<?php echo esc_attr($familyName); ?>"><?php echo esc_html($defaultStack); ?></span>
                                </div>
                            </div>

                            <div class="etch-fonts-font-actions">
                                <div class="etch-fonts-font-actions-primary">
                                    <button type="button" class="button button-small" data-role-assign="heading" data-font-family="<?php echo esc_attr($familyName); ?>"><?php esc_html_e('Set heading', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                                    <button type="button" class="button button-small" data-role-assign="body" data-font-family="<?php echo esc_attr($familyName); ?>"><?php esc_html_e('Set body', ETCH_FONTS_TEXT_DOMAIN); ?></button>
                                </div>
                                <div class="etch-fonts-font-actions-secondary">
                                    <button
                                        type="button"
                                        class="button button-small etch-fonts-disclosure-button etch-fonts-disclosure-button--card"
                                        data-disclosure-toggle="<?php echo esc_attr($detailsId); ?>"
                                        data-expanded-label="<?php echo esc_attr__('Hide details', ETCH_FONTS_TEXT_DOMAIN); ?>"
                                        data-collapsed-label="<?php echo esc_attr__('View details', ETCH_FONTS_TEXT_DOMAIN); ?>"
                                        aria-expanded="<?php echo $isExpanded ? 'true' : 'false'; ?>"
                                        aria-controls="<?php echo esc_attr($detailsId); ?>"
                                    >
                                        <?php echo $isExpanded ? esc_html__('Hide details', ETCH_FONTS_TEXT_DOMAIN) : esc_html__('View details', ETCH_FONTS_TEXT_DOMAIN); ?>
                                    </button>
                                    <form method="post" class="etch-fonts-delete-form">
                                        <?php wp_nonce_field('etch_fonts_delete_family'); ?>
                                        <input type="hidden" name="etch_fonts_delete_family" value="1">
                                        <input type="hidden" name="etch_fonts_family_slug" value="<?php echo esc_attr($familySlug); ?>">
                                        <button
                                            type="submit"
                                            class="button button-small etch-fonts-button-danger"
                                            data-delete-family="<?php echo esc_attr($familyName); ?>"
                                            <?php if ($deleteBlockedMessage !== '') : ?>
                                                data-delete-blocked="<?php echo esc_attr($deleteBlockedMessage); ?>"
                                            <?php endif; ?>
                                        >
                                            <?php esc_html_e('Delete', ETCH_FONTS_TEXT_DOMAIN); ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="<?php echo esc_attr($detailsId); ?>" class="etch-fonts-family-details" <?php echo $isExpanded ? '' : 'hidden'; ?>>
                <table class="widefat striped etch-fonts-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Weight', ETCH_FONTS_TEXT_DOMAIN); ?></th>
                            <th><?php esc_html_e('Style', ETCH_FONTS_TEXT_DOMAIN); ?></th>
                            <th><?php esc_html_e('Preview', ETCH_FONTS_TEXT_DOMAIN); ?></th>
                            <th><?php esc_html_e('Source', ETCH_FONTS_TEXT_DOMAIN); ?></th>
                            <th><?php esc_html_e('Storage', ETCH_FONTS_TEXT_DOMAIN); ?></th>
                            <th><?php esc_html_e('Formats', ETCH_FONTS_TEXT_DOMAIN); ?></th>
                            <th><?php esc_html_e('Files', ETCH_FONTS_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ((array) ($family['faces'] ?? []) as $face): ?>
                            <?php
                            $faceWeight = (string) ($face['weight'] ?? '400');
                            $faceStyle = (string) ($face['style'] ?? 'normal');
                            ?>
                            <tr>
                                <td><?php echo esc_html($faceWeight); ?></td>
                                <td><?php echo esc_html($faceStyle); ?></td>
                                <td class="etch-fonts-face-preview-cell">
                                    <div
                                        class="etch-fonts-face-preview"
                                        data-font-preview-family="<?php echo esc_attr($familyName); ?>"
                                        style="font-family:<?php echo esc_attr($defaultStack); ?>; font-weight:<?php echo esc_attr($faceWeight); ?>; font-style:<?php echo esc_attr($faceStyle); ?>;"
                                    >
                                        <?php echo esc_html($facePreviewText); ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html((string) ucfirst((string) ($face['source'] ?? 'local'))); ?></td>
                                <td><?php echo esc_html($this->buildFaceStorageSummary((array) $face)); ?></td>
                                <td>
                                    <?php foreach (array_keys((array) ($face['files'] ?? [])) as $format): ?>
                                        <span class="etch-fonts-chip"><?php echo esc_html(strtoupper((string) $format)); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php foreach ((array) ($face['paths'] ?? []) as $format => $path): ?>
                                        <div class="etch-fonts-file-path">
                                            <strong><?php echo esc_html(strtoupper((string) $format)); ?>:</strong>
                                            <div class="etch-fonts-code"><?php echo esc_html(FontUtils::compactRelativePath((string) $path)); ?></div>
                                        </div>
                                    <?php endforeach; ?>
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
                __('%s is currently used for both heading and body. Choose different role fonts before deleting it.', ETCH_FONTS_TEXT_DOMAIN),
                $familyName
            );
        }

        if ($isHeading) {
            return sprintf(
                __('%s is currently used as the heading font. Choose a different heading font before deleting it.', ETCH_FONTS_TEXT_DOMAIN),
                $familyName
            );
        }

        if ($isBody) {
            return sprintf(
                __('%s is currently used as the body font. Choose a different body font before deleting it.', ETCH_FONTS_TEXT_DOMAIN),
                $familyName
            );
        }

        return '';
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
            return __('The quick brown fox…', ETCH_FONTS_TEXT_DOMAIN);
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
                _n('%d file', '%d files', $fileCount, ETCH_FONTS_TEXT_DOMAIN),
                $fileCount
            );
        }

        return sprintf(
            _n('%1$d file · %2$s', '%1$d files · %2$s', $fileCount, ETCH_FONTS_TEXT_DOMAIN),
            $fileCount,
            size_format($bytes)
        );
    }

    private function renderUploadFamilyGroup(): void
    {
        ?>
        <section class="etch-fonts-upload-group" data-upload-group>
            <div class="etch-fonts-upload-group-head">
                <div class="etch-fonts-upload-group-fields">
                    <label class="etch-fonts-stack-field">
                        <?php $this->renderFieldLabel(__('Family name', ETCH_FONTS_TEXT_DOMAIN)); ?>
                        <input
                            type="text"
                            class="regular-text"
                            data-upload-group-field="family"
                            placeholder="<?php esc_attr_e('Example: Satoshi', ETCH_FONTS_TEXT_DOMAIN); ?>"
                        >
                    </label>

                    <label class="etch-fonts-stack-field">
                        <?php $this->renderFieldLabel(__('Fallback', ETCH_FONTS_TEXT_DOMAIN)); ?>
                        <?php
                        $this->renderFallbackInput(
                            '',
                            'sans-serif',
                            [
                                'data-upload-group-field' => 'fallback',
                                'placeholder' => __('Example: system-ui, sans-serif', ETCH_FONTS_TEXT_DOMAIN),
                            ]
                        );
                        ?>
                    </label>
                </div>

                <button
                    type="button"
                    class="button etch-fonts-upload-group-remove"
                    data-upload-remove-group
                >
                    <?php esc_html_e('Remove family', ETCH_FONTS_TEXT_DOMAIN); ?>
                </button>
            </div>

            <div class="etch-fonts-upload-face-headings" aria-hidden="true">
                <span><?php esc_html_e('Font file', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                <span><?php esc_html_e('Weight', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                <span><?php esc_html_e('Style', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                <span><?php esc_html_e('Action', ETCH_FONTS_TEXT_DOMAIN); ?></span>
            </div>

            <div class="etch-fonts-upload-face-list" data-upload-face-list>
                <?php $this->renderUploadFaceRow(); ?>
            </div>

            <div class="etch-fonts-upload-group-actions">
                <button type="button" class="button" data-upload-add-face><?php esc_html_e('Add face', ETCH_FONTS_TEXT_DOMAIN); ?></button>
            </div>
        </section>
        <?php
    }

    private function renderUploadFaceRow(): void
    {
        ?>
        <div class="etch-fonts-upload-face-row" data-upload-row>
            <div class="etch-fonts-upload-face-grid">
                <label class="etch-fonts-stack-field etch-fonts-upload-file-field">
                    <span class="screen-reader-text"><?php esc_html_e('Font file', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                    <span class="etch-fonts-upload-file-picker">
                        <input
                            type="file"
                            class="etch-fonts-upload-native-file"
                            data-upload-field="file"
                            accept=".woff2,.woff,.ttf,.otf"
                        >
                        <span class="etch-fonts-upload-file-button"><?php esc_html_e('Select font', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                        <span class="etch-fonts-upload-file-name" data-upload-file-name><?php esc_html_e('No file chosen', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                    </span>
                </label>

                <label class="etch-fonts-stack-field">
                    <span class="screen-reader-text"><?php esc_html_e('Weight', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                    <select data-upload-field="weight">
                        <?php foreach (range(100, 900, 100) as $weight): ?>
                            <option value="<?php echo esc_attr((string) $weight); ?>" <?php selected((string) $weight, '400'); ?>><?php echo esc_html((string) $weight); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="etch-fonts-stack-field">
                    <span class="screen-reader-text"><?php esc_html_e('Style', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                    <select data-upload-field="style">
                        <option value="normal"><?php esc_html_e('Normal', ETCH_FONTS_TEXT_DOMAIN); ?></option>
                        <option value="italic"><?php esc_html_e('Italic', ETCH_FONTS_TEXT_DOMAIN); ?></option>
                        <option value="oblique"><?php esc_html_e('Oblique', ETCH_FONTS_TEXT_DOMAIN); ?></option>
                    </select>
                </label>

                <button
                    type="button"
                    class="button etch-fonts-upload-row-remove"
                    data-upload-remove
                    aria-label="<?php esc_attr_e('Remove row', ETCH_FONTS_TEXT_DOMAIN); ?>"
                >
                    <?php esc_html_e('Remove', ETCH_FONTS_TEXT_DOMAIN); ?>
                </button>
            </div>

            <div class="etch-fonts-upload-row-foot">
                <button type="button" class="button etch-fonts-upload-detected" data-upload-detected-apply hidden></button>
                <div class="etch-fonts-upload-row-status" data-upload-row-status></div>
            </div>
        </div>
        <?php
    }

    private function renderPreviewScene(string $key, string $previewText, array $roles): void
    {
        switch ($key) {
            case 'editorial':
                ?>
                <div class="etch-fonts-preview-showcase">
                    <div class="etch-fonts-preview-specimen-board">
                        <aside class="etch-fonts-preview-specimen-rail">
                            <div class="etch-fonts-preview-specimen-glyph" data-role-preview="heading">Aa</div>
                            <div class="etch-fonts-preview-specimen-key">
                                <span class="etch-fonts-preview-specimen-key-label"><?php esc_html_e('Heading family', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <strong class="etch-fonts-preview-specimen-key-value" data-role-preview="heading"><?php echo esc_html((string) ($roles['heading'] ?? '')); ?></strong>
                            </div>
                            <div class="etch-fonts-preview-specimen-key">
                                <span class="etch-fonts-preview-specimen-key-label"><?php esc_html_e('Body family', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <strong class="etch-fonts-preview-specimen-key-value" data-role-preview="body"><?php echo esc_html((string) ($roles['body'] ?? '')); ?></strong>
                            </div>
                        </aside>

                        <div class="etch-fonts-preview-specimen-scale">
                            <div class="etch-fonts-preview-specimen-scale-item etch-fonts-preview-specimen-scale-item--1" data-role-preview="heading"><?php esc_html_e('Heading 1', ETCH_FONTS_TEXT_DOMAIN); ?></div>
                            <div class="etch-fonts-preview-specimen-scale-item etch-fonts-preview-specimen-scale-item--2" data-role-preview="heading"><?php esc_html_e('Heading 2', ETCH_FONTS_TEXT_DOMAIN); ?></div>
                            <div class="etch-fonts-preview-specimen-scale-item etch-fonts-preview-specimen-scale-item--3" data-role-preview="heading"><?php esc_html_e('Heading 3', ETCH_FONTS_TEXT_DOMAIN); ?></div>
                            <div class="etch-fonts-preview-specimen-scale-item etch-fonts-preview-specimen-scale-item--4" data-role-preview="heading"><?php esc_html_e('Heading 4', ETCH_FONTS_TEXT_DOMAIN); ?></div>
                            <div class="etch-fonts-preview-specimen-scale-item etch-fonts-preview-specimen-scale-item--5" data-role-preview="heading"><?php esc_html_e('Heading 5', ETCH_FONTS_TEXT_DOMAIN); ?></div>
                            <div class="etch-fonts-preview-specimen-scale-item etch-fonts-preview-specimen-scale-item--6" data-role-preview="heading"><?php esc_html_e('Heading 6', ETCH_FONTS_TEXT_DOMAIN); ?></div>
                        </div>

                        <div class="etch-fonts-preview-specimen-copy">
                            <div class="etch-fonts-preview-specimen-copy-row">
                                <span class="etch-fonts-preview-specimen-copy-label"><?php esc_html_e('Lead', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <div class="etch-fonts-preview-specimen-copy-body">
                                    <p class="etch-fonts-preview-specimen-lead" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                                </div>
                            </div>
                            <div class="etch-fonts-preview-specimen-copy-row">
                                <span class="etch-fonts-preview-specimen-copy-label"><?php esc_html_e('Body / 16', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <div class="etch-fonts-preview-specimen-copy-body">
                                    <p class="etch-fonts-preview-specimen-body-large" data-role-preview="body"><?php esc_html_e('Apparently we had reached a great height in the atmosphere, for the sky was a dead black, and the stars had ceased to twinkle.', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                                </div>
                            </div>
                            <div class="etch-fonts-preview-specimen-copy-row">
                                <span class="etch-fonts-preview-specimen-copy-label"><?php esc_html_e('Body / 14', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <div class="etch-fonts-preview-specimen-copy-body">
                                    <p class="etch-fonts-preview-specimen-body" data-role-preview="body"><?php esc_html_e('Apparently we had reached a great height in the atmosphere, for the sky was a dead black, and the stars had ceased to twinkle.', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                                </div>
                            </div>
                            <div class="etch-fonts-preview-specimen-copy-row">
                                <span class="etch-fonts-preview-specimen-copy-label"><?php esc_html_e('Quote', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <div class="etch-fonts-preview-specimen-copy-body">
                                    <blockquote class="etch-fonts-preview-specimen-quote" data-role-preview="heading"><?php esc_html_e('“The sky was cloudless and of a deep dark blue.”', ETCH_FONTS_TEXT_DOMAIN); ?></blockquote>
                                </div>
                            </div>
                            <div class="etch-fonts-preview-specimen-copy-row">
                                <span class="etch-fonts-preview-specimen-copy-label"><?php esc_html_e('Capitalized', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <div class="etch-fonts-preview-specimen-copy-body">
                                    <p class="etch-fonts-preview-specimen-caps" data-role-preview="body"><?php esc_html_e('Brainstorm alternative ideas', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                                </div>
                            </div>
                            <div class="etch-fonts-preview-specimen-copy-row">
                                <span class="etch-fonts-preview-specimen-copy-label"><?php esc_html_e('Small', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <div class="etch-fonts-preview-specimen-copy-body">
                                    <p class="etch-fonts-preview-specimen-small" data-role-preview="body"><?php esc_html_e('Value your time', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                                </div>
                            </div>
                            <div class="etch-fonts-preview-specimen-copy-row">
                                <span class="etch-fonts-preview-specimen-copy-label"><?php esc_html_e('Tiny', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <div class="etch-fonts-preview-specimen-copy-body">
                                    <p class="etch-fonts-preview-specimen-tiny" data-role-preview="body"><?php esc_html_e('Nothing is impossible', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="etch-fonts-preview-support-grid">
                        <article class="etch-fonts-preview-support-card">
                            <span class="etch-fonts-preview-support-label" data-role-preview="body"><?php esc_html_e('Hero lockup', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                            <h3 class="etch-fonts-preview-support-title" data-role-preview="heading"><?php esc_html_e('A type pairing that feels intentional at every scale', ETCH_FONTS_TEXT_DOMAIN); ?></h3>
                            <p class="etch-fonts-preview-support-copy" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                            <div class="etch-fonts-preview-support-meta">
                                <span><?php esc_html_e('Landing page', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <strong data-role-preview="heading"><?php esc_html_e('Ready', ETCH_FONTS_TEXT_DOMAIN); ?></strong>
                            </div>
                        </article>

                        <article class="etch-fonts-preview-support-card">
                            <span class="etch-fonts-preview-support-label" data-role-preview="body"><?php esc_html_e('Feature module', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                            <h3 class="etch-fonts-preview-support-title" data-role-preview="heading"><?php esc_html_e('Clean cards with enough contrast for product copy', ETCH_FONTS_TEXT_DOMAIN); ?></h3>
                            <p class="etch-fonts-preview-support-copy" data-role-preview="body"><?php esc_html_e('Use this sample to judge title tone, supporting copy rhythm, and whether the body face stays calm inside UI surfaces.', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                            <div class="etch-fonts-preview-support-meta">
                                <span><?php esc_html_e('Surface check', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <strong data-role-preview="heading"><?php esc_html_e('Balanced', ETCH_FONTS_TEXT_DOMAIN); ?></strong>
                            </div>
                        </article>

                        <article class="etch-fonts-preview-support-card">
                            <span class="etch-fonts-preview-support-label" data-role-preview="body"><?php esc_html_e('Metrics panel', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                            <div class="etch-fonts-preview-support-stats">
                                <div class="etch-fonts-preview-support-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Visitors', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                    <strong data-role-preview="heading">12.4k</strong>
                                </div>
                                <div class="etch-fonts-preview-support-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Conversion', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                    <strong data-role-preview="heading">4.8%</strong>
                                </div>
                                <div class="etch-fonts-preview-support-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Launch', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                    <strong data-role-preview="heading"><?php esc_html_e('Soon', ETCH_FONTS_TEXT_DOMAIN); ?></strong>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
                <?php
                return;

            case 'card':
                ?>
                <div class="etch-fonts-preview-card-board">
                    <div class="etch-fonts-preview-card-gallery">
                        <article class="etch-fonts-preview-card-frame">
                            <div class="etch-fonts-preview-card-media">
                                <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                            </div>
                            <div class="etch-fonts-preview-card-body">
                                <span class="etch-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Feature card', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <h3 class="etch-fonts-preview-card-title" data-role-preview="heading"><?php esc_html_e('Title', ETCH_FONTS_TEXT_DOMAIN); ?></h3>
                                <p class="etch-fonts-preview-card-subtitle" data-role-preview="body"><?php esc_html_e('Subtitle', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                                <p class="etch-fonts-preview-card-copy" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                                <div class="etch-fonts-preview-card-actions">
                                    <span class="button" aria-hidden="true"><?php esc_html_e('Action', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                    <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Action', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                </div>
                            </div>
                        </article>

                        <article class="etch-fonts-preview-card-frame">
                            <div class="etch-fonts-preview-card-media">
                                <span class="dashicons dashicons-format-gallery" aria-hidden="true"></span>
                            </div>
                            <div class="etch-fonts-preview-card-body">
                                <span class="etch-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Collection', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <h3 class="etch-fonts-preview-card-title" data-role-preview="heading"><?php esc_html_e('Modern layouts', ETCH_FONTS_TEXT_DOMAIN); ?></h3>
                                <p class="etch-fonts-preview-card-subtitle" data-role-preview="body"><?php esc_html_e('Structured and calm', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                                <p class="etch-fonts-preview-card-copy" data-role-preview="body"><?php esc_html_e('Compare how the chosen heading face holds attention while the body face keeps supporting detail easy to scan.', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                                <div class="etch-fonts-preview-card-actions">
                                    <span class="button" aria-hidden="true"><?php esc_html_e('Review', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                    <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Select', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                </div>
                            </div>
                        </article>

                        <article class="etch-fonts-preview-card-frame">
                            <div class="etch-fonts-preview-card-media">
                                <span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
                            </div>
                            <div class="etch-fonts-preview-card-body">
                                <span class="etch-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Product card', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                <h3 class="etch-fonts-preview-card-title" data-role-preview="heading"><?php esc_html_e('System-ready', ETCH_FONTS_TEXT_DOMAIN); ?></h3>
                                <p class="etch-fonts-preview-card-subtitle" data-role-preview="body"><?php esc_html_e('Useful in real UI', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                                <p class="etch-fonts-preview-card-copy" data-role-preview="body"><?php esc_html_e('This view is intentionally compact so you can judge hierarchy, spacing, and button copy without oversized demo content.', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                                <div class="etch-fonts-preview-card-actions">
                                    <span class="button" aria-hidden="true"><?php esc_html_e('Later', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                    <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Launch', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
                <?php
                return;

            case 'reading':
                ?>
                <article class="etch-fonts-preview-reading-sheet">
                    <div class="etch-fonts-preview-reading-head">
                        <span class="etch-fonts-preview-reading-label" data-role-preview="body"><?php esc_html_e('Long-form reading', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                        <h3 class="etch-fonts-preview-reading-title" data-role-preview="heading"><?php esc_html_e('Readable paragraphs with steady rhythm', ETCH_FONTS_TEXT_DOMAIN); ?></h3>
                    </div>
                    <p class="etch-fonts-preview-reading-lead" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                    <div class="etch-fonts-preview-reading-layout">
                        <div class="etch-fonts-preview-reading-copy">
                            <p data-role-preview="body"><?php esc_html_e('Apparently we had reached a great height in the atmosphere, for the sky was a dead black, and the stars had ceased to twinkle.', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                            <p data-role-preview="body"><?php esc_html_e('A strong reading font should stay calm across longer passages and still leave enough contrast for section headings and pull quotes.', ETCH_FONTS_TEXT_DOMAIN); ?></p>
                        </div>
                        <aside class="etch-fonts-preview-reading-aside">
                            <h4 class="etch-fonts-preview-reading-aside-title" data-role-preview="heading"><?php esc_html_e('Checklist', ETCH_FONTS_TEXT_DOMAIN); ?></h4>
                            <ul class="etch-fonts-preview-reading-list" data-role-preview="body">
                                <li><?php esc_html_e('Paragraph spacing', ETCH_FONTS_TEXT_DOMAIN); ?></li>
                                <li><?php esc_html_e('Line length at body sizes', ETCH_FONTS_TEXT_DOMAIN); ?></li>
                                <li><?php esc_html_e('Subheading emphasis', ETCH_FONTS_TEXT_DOMAIN); ?></li>
                            </ul>
                        </aside>
                    </div>
                </article>
                <?php
                return;

            case 'interface':
            default:
                ?>
                <div class="etch-fonts-preview-ui-shell">
                    <div class="etch-fonts-preview-ui-topbar">
                        <span class="etch-fonts-preview-ui-topbar-label" data-role-preview="body"><?php esc_html_e('Workspace', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                        <span class="etch-fonts-preview-ui-topbar-status"><?php esc_html_e('Live', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                    </div>
                    <div class="etch-fonts-preview-ui-grid">
                        <div class="etch-fonts-preview-ui-panel">
                            <span class="etch-fonts-preview-ui-label" data-role-preview="body"><?php esc_html_e('Project name', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                            <h3 class="etch-fonts-preview-ui-title" data-role-preview="heading"><?php esc_html_e('Launch planning', ETCH_FONTS_TEXT_DOMAIN); ?></h3>
                            <p class="etch-fonts-preview-ui-copy" data-role-preview="body" data-preview-dynamic-text><?php echo esc_html($previewText); ?></p>
                        </div>
                        <div class="etch-fonts-preview-ui-panel">
                            <span class="etch-fonts-preview-ui-label" data-role-preview="body"><?php esc_html_e('Metrics', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                            <div class="etch-fonts-preview-ui-stats">
                                <div class="etch-fonts-preview-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Visitors', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                    <strong data-role-preview="heading">12.4k</strong>
                                </div>
                                <div class="etch-fonts-preview-stat">
                                    <span data-role-preview="body"><?php esc_html_e('Signups', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                                    <strong data-role-preview="heading">318</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="etch-fonts-preview-ui-list">
                        <div class="etch-fonts-preview-ui-list-row">
                            <span data-role-preview="body"><?php esc_html_e('Headline lockup', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                            <strong data-role-preview="heading"><?php esc_html_e('Approved', ETCH_FONTS_TEXT_DOMAIN); ?></strong>
                        </div>
                        <div class="etch-fonts-preview-ui-list-row">
                            <span data-role-preview="body"><?php esc_html_e('Landing page copy', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                            <strong data-role-preview="heading"><?php esc_html_e('In review', ETCH_FONTS_TEXT_DOMAIN); ?></strong>
                        </div>
                    </div>
                    <div class="etch-fonts-preview-ui-actions">
                        <span class="button" aria-hidden="true"><?php esc_html_e('Save draft', ETCH_FONTS_TEXT_DOMAIN); ?></span>
                        <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Publish', ETCH_FONTS_TEXT_DOMAIN); ?></span>
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
        <div class="etch-fonts-toast-stack" aria-live="polite" aria-atomic="true">
            <?php foreach ($toasts as $toast): ?>
                <div
                    class="etch-fonts-toast is-<?php echo esc_attr((string) ($toast['tone'] ?? 'success')); ?>"
                    data-toast
                    data-toast-tone="<?php echo esc_attr((string) ($toast['tone'] ?? 'success')); ?>"
                    role="<?php echo esc_attr((string) ($toast['role'] ?? 'status')); ?>"
                >
                    <div class="etch-fonts-toast-message"><?php echo esc_html((string) ($toast['message'] ?? '')); ?></div>
                    <button type="button" class="etch-fonts-toast-dismiss" data-toast-dismiss aria-label="<?php esc_attr_e('Dismiss notification', ETCH_FONTS_TEXT_DOMAIN); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function renderHelpTip(string $copy, string $label = ''): void
    {
        $tooltipId = wp_unique_id('etch-fonts-help-');
        $ariaLabel = $label !== ''
            ? sprintf(
                /* translators: %s: UI label */
                __('More information about %s', ETCH_FONTS_TEXT_DOMAIN),
                $label
            )
            : __('More information', ETCH_FONTS_TEXT_DOMAIN);
        ?>
        <span class="etch-fonts-help-wrap">
            <button
                type="button"
                class="etch-fonts-help-button"
                aria-label="<?php echo esc_attr($ariaLabel); ?>"
                aria-describedby="<?php echo esc_attr($tooltipId); ?>"
                data-help-tooltip="<?php echo esc_attr($copy); ?>"
            >
                <span class="etch-fonts-help-glyph" aria-hidden="true">i</span>
            </button>
            <span id="<?php echo esc_attr($tooltipId); ?>" class="screen-reader-text"><?php echo esc_html($copy); ?></span>
        </span>
        <?php
    }

    private function renderSectionHeading(string $tag, string $title, string $help, string $copy = ''): void
    {
        ?>
        <div class="etch-fonts-section-heading">
            <div class="etch-fonts-section-title-row">
                <<?php echo esc_html($tag); ?> class="etch-fonts-section-title"><?php echo esc_html($title); ?></<?php echo esc_html($tag); ?>>
                <?php if ($help !== '') : ?>
                    <?php $this->renderHelpTip($help, $title); ?>
                <?php endif; ?>
            </div>
            <?php if ($copy !== ''): ?>
                <p class="etch-fonts-section-copy"><?php echo esc_html($copy); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderFieldLabel(string $label, string $help = ''): void
    {
        ?>
        <span class="etch-fonts-field-label-row">
            <span class="etch-fonts-field-label-text"><?php echo esc_html($label); ?></span>
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
                'list' => 'etch-fonts-fallback-options',
                'value' => FontUtils::sanitizeFallback($value),
                'class' => $className,
                'spellcheck' => 'false',
                'autocomplete' => 'off',
            ],
            $attributes
        );

        if ($name !== '') {
            $inputAttributes['name'] = $name;
        }

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
    }

    private function renderFallbackSuggestionList(): void
    {
        ?>
        <datalist id="etch-fonts-fallback-options">
            <?php foreach (FontUtils::FALLBACK_SUGGESTIONS as $fallback): ?>
                <option value="<?php echo esc_attr($fallback); ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <?php
    }
}
