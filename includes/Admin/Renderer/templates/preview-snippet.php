<?php

defined('ABSPATH') || exit;

$snippet = $this->buildPreviewSnippetCss($roles, $monospaceRoleEnabled, $globalFallbackSettings);
$lineCount = substr_count($snippet, "\n") + 1;
?>
<div class="tasty-fonts-preview-snippet-workspace">
    <section class="tasty-fonts-preview-snippet-brief" aria-label="<?php esc_attr_e('Preview snippet explanation', 'tasty-fonts'); ?>">
        <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Preview Snippet', 'tasty-fonts'); ?></span>
        <h3 class="tasty-fonts-preview-snippet-title" data-role-preview="heading"><?php esc_html_e('Copy the exact pairing you are previewing.', 'tasty-fonts'); ?></h3>
        <p class="tasty-fonts-preview-snippet-copy" data-role-preview="body"><?php esc_html_e('This CSS is generated from the current Preview Workspace selection, including draft changes that are not published yet. Use it when you want this same font combo in custom CSS, a child theme, or a builder field.', 'tasty-fonts'); ?></p>

        <?php $this->renderPreviewRoleList($roles, $familyLabels, $monospaceRoleEnabled, 'tasty-fonts-preview-snippet-role-list', 'tasty-fonts-preview-snippet-role', false, $globalFallbackSettings); ?>

        <div class="tasty-fonts-preview-snippet-note" data-role-preview="body">
            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
            <span><?php esc_html_e('The Snippets workspace still has the full variable, class, stack, and name outputs. This tab is only for the preview pairing shown here.', 'tasty-fonts'); ?></span>
        </div>
    </section>

    <section class="tasty-fonts-preview-snippet-card" aria-labelledby="tasty-fonts-preview-snippet-heading">
        <div class="tasty-fonts-preview-snippet-card-head">
            <div class="tasty-fonts-preview-snippet-card-copy">
                <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Ready CSS', 'tasty-fonts'); ?></span>
                <h4 id="tasty-fonts-preview-snippet-heading" class="tasty-fonts-preview-snippet-card-title" data-role-preview="heading"><?php esc_html_e('Preview pairing CSS', 'tasty-fonts'); ?></h4>
            </div>
            <div class="tasty-fonts-preview-snippet-card-actions">
                <span class="tasty-fonts-preview-code-badge" data-preview-snippet-line-count><?php echo esc_html(sprintf(_n('%d line', '%d lines', $lineCount, 'tasty-fonts'), $lineCount)); ?></span>
                <button
                    type="button"
                    class="button tasty-fonts-preview-copy-css-button"
                    data-preview-snippet-copy
                    data-copy-text="<?php echo esc_attr($snippet); ?>"
                    data-copy-success="<?php esc_attr_e('Preview snippet copied.', 'tasty-fonts'); ?>"
                    data-copy-static-label="1"
                    <?php $this->renderPassiveHelpAttributes(__('Copy the CSS for the current preview pairing.', 'tasty-fonts')); ?>
                    aria-label="<?php esc_attr_e('Copy the CSS for the current preview pairing', 'tasty-fonts'); ?>"
                >
                    <span class="screen-reader-text"><?php esc_html_e('Copy preview snippet', 'tasty-fonts'); ?></span>
                </button>
            </div>
        </div>

        <div class="tasty-fonts-preview-code-panel tasty-fonts-preview-code-panel--block tasty-fonts-preview-snippet-code-panel">
            <pre class="tasty-fonts-output tasty-fonts-preview-snippet-output" aria-labelledby="tasty-fonts-preview-snippet-heading"><code id="tasty-fonts-preview-snippet-code" class="tasty-fonts-output-code" data-preview-snippet-code><?php echo esc_html($snippet); ?></code></pre>
        </div>

        <p class="tasty-fonts-preview-code-caption" data-role-preview="body"><?php esc_html_e('Copying this does not publish anything. It simply gives you the CSS for the current preview state.', 'tasty-fonts'); ?></p>
    </section>
</div>
