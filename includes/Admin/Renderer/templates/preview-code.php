<?php

defined('ABSPATH') || exit;

$editorPreviewHeadingId = 'tasty-fonts-preview-code-editor-heading';
$blockPreviewHeadingId = 'tasty-fonts-preview-code-block-heading';
?>
<div class="tasty-fonts-preview-code-workspace">
    <div class="tasty-fonts-preview-code-overview">
        <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Code Preview', 'tasty-fonts'); ?></span>
        <h3 class="tasty-fonts-preview-code-title" data-role-preview="heading"><?php esc_html_e('Inspect How Your Code Reads in an Editor and Published Block', 'tasty-fonts'); ?></h3>
        <p class="tasty-fonts-preview-code-copy" data-role-preview="body"><?php esc_html_e('Review spacing, punctuation, and syntax rhythm in the places code actually appears: an editor surface, inline tokens, and a published front-end block.', 'tasty-fonts'); ?></p>
        <div class="tasty-fonts-preview-code-meta">
            <div class="tasty-fonts-preview-code-meta-item">
                <span class="tasty-fonts-preview-code-meta-label"><?php esc_html_e('Code Face', 'tasty-fonts'); ?></span>
                <strong class="tasty-fonts-preview-code-meta-value" data-role-preview="monospace" data-role-preview-name="monospace"><?php echo esc_html($this->previewRoleName('monospace', $roles, $familyLabels, $globalFallbackSettings)); ?></strong>
            </div>
            <div class="tasty-fonts-preview-code-meta-item">
                <span class="tasty-fonts-preview-code-meta-label"><?php esc_html_e('Headings', 'tasty-fonts'); ?></span>
                <strong class="tasty-fonts-preview-code-meta-value" data-role-preview="heading" data-role-preview-name="heading"><?php echo esc_html($this->previewRoleName('heading', $roles, $familyLabels, $globalFallbackSettings)); ?></strong>
            </div>
            <div class="tasty-fonts-preview-code-meta-item">
                <span class="tasty-fonts-preview-code-meta-label"><?php esc_html_e('Annotations', 'tasty-fonts'); ?></span>
                <strong class="tasty-fonts-preview-code-meta-value" data-role-preview="body" data-role-preview-name="body"><?php echo esc_html($this->previewRoleName('body', $roles, $familyLabels, $globalFallbackSettings)); ?></strong>
            </div>
        </div>
        <div class="tasty-fonts-preview-code-inline">
            <span class="tasty-fonts-preview-code-inline-label" data-role-preview="body"><?php esc_html_e('Inline token', 'tasty-fonts'); ?></span>
            <code class="tasty-fonts-preview-code-inline-sample" data-role-preview="monospace">var(--font-monospace)</code>
        </div>
        <div class="tasty-fonts-preview-code-chip-row">
            <span class="tasty-fonts-preview-code-chip"><?php echo esc_html($monospaceRoleEnabled ? __('Monospace Role Enabled', 'tasty-fonts') : __('Fallback Stack Preview', 'tasty-fonts')); ?></span>
            <span class="tasty-fonts-preview-code-chip"><?php esc_html_e('Syntax Highlighting', 'tasty-fonts'); ?></span>
        </div>
    </div>

    <div class="tasty-fonts-preview-code-surfaces">
        <section class="tasty-fonts-preview-code-window">
            <div class="tasty-fonts-preview-code-window-topbar">
                <div class="tasty-fonts-preview-code-window-dots" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <div class="tasty-fonts-preview-code-window-tab">
                    <span class="dashicons dashicons-media-code" aria-hidden="true"></span>
                    <span id="<?php echo esc_attr($editorPreviewHeadingId); ?>" data-role-preview="body">typography-preview.tsx</span>
                </div>
                <div class="tasty-fonts-preview-code-window-tools">
                    <span class="tasty-fonts-preview-code-badge">TSX</span>
                    <span class="tasty-fonts-preview-code-badge"><?php echo esc_html($monospaceRoleEnabled ? __('Role Live', 'tasty-fonts') : __('Fallback', 'tasty-fonts')); ?></span>
                </div>
            </div>

            <div class="tasty-fonts-preview-code-panel tasty-fonts-preview-code-panel--editor" data-role-preview="monospace">
                <div class="tasty-fonts-preview-code-lines" aria-label="<?php esc_attr_e('Editor preview', 'tasty-fonts'); ?>" aria-labelledby="<?php echo esc_attr($editorPreviewHeadingId); ?>">
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">01</span>
                        <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-comment">// Typography tokens wired into the UI preview</span></span>
                    </div>
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">02</span>
                        <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-keyword">const</span> <span class="tasty-fonts-preview-token-variable">fontRoles</span> <span class="tasty-fonts-preview-token-operator">=</span> <span class="tasty-fonts-preview-token-punctuation">{</span></span>
                    </div>
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">03</span>
                        <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">heading</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-string">"var(--font-heading)"</span><span class="tasty-fonts-preview-token-punctuation">,</span></span>
                    </div>
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">04</span>
                        <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">body</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-string">"var(--font-body)"</span><span class="tasty-fonts-preview-token-punctuation">,</span></span>
                    </div>
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">05</span>
                        <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">code</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-string">"var(--font-monospace)"</span><span class="tasty-fonts-preview-token-punctuation">,</span></span>
                    </div>
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">06</span>
                        <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-punctuation">};</span></span>
                    </div>
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">07</span>
                        <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-keyword">export</span> <span class="tasty-fonts-preview-token-keyword">function</span> <span class="tasty-fonts-preview-token-function">PreviewChip</span><span class="tasty-fonts-preview-token-punctuation">()</span> <span class="tasty-fonts-preview-token-punctuation">{</span></span>
                    </div>
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">08</span>
                        <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-keyword">return</span> <span class="tasty-fonts-preview-token-tag">&lt;code</span> <span class="tasty-fonts-preview-token-attr">style</span><span class="tasty-fonts-preview-token-operator">=</span><span class="tasty-fonts-preview-token-punctuation">{</span><span class="tasty-fonts-preview-token-punctuation">{</span> <span class="tasty-fonts-preview-token-property">fontFamily</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-variable">fontRoles</span><span class="tasty-fonts-preview-token-punctuation">.</span><span class="tasty-fonts-preview-token-property">code</span> <span class="tasty-fonts-preview-token-punctuation">}</span><span class="tasty-fonts-preview-token-punctuation">}</span><span class="tasty-fonts-preview-token-tag">&gt;</span><span class="tasty-fonts-preview-token-string">12px baseline grid</span><span class="tasty-fonts-preview-token-tag">&lt;/code&gt;</span><span class="tasty-fonts-preview-token-punctuation">;</span></span>
                    </div>
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">09</span>
                        <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-punctuation">}</span></span>
                    </div>
                </div>
            </div>

            <div class="tasty-fonts-preview-code-statusbar">
                <span data-role-preview="body"><?php esc_html_e('Editor Surface', 'tasty-fonts'); ?></span>
                <span data-role-preview="body"><?php echo esc_html($monospaceRoleEnabled ? __('var(--font-monospace) applied', 'tasty-fonts') : __('Generic monospace fallback applied', 'tasty-fonts')); ?></span>
            </div>
        </section>

        <section class="tasty-fonts-preview-code-block-shell">
            <div class="tasty-fonts-preview-code-block-head">
                <div class="tasty-fonts-preview-code-block-head-copy">
                    <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Published Code Block', 'tasty-fonts'); ?></span>
                    <h4 id="<?php echo esc_attr($blockPreviewHeadingId); ?>" class="tasty-fonts-preview-code-block-title" data-role-preview="heading"><?php esc_html_e('Front-End Snippet With Readable Line Height and Punctuation', 'tasty-fonts'); ?></h4>
                </div>
                <span class="tasty-fonts-preview-code-badge tasty-fonts-preview-code-badge--light">CSS</span>
            </div>

            <div class="tasty-fonts-preview-code-panel tasty-fonts-preview-code-panel--block" data-role-preview="monospace">
                <div class="tasty-fonts-preview-code-lines" aria-label="<?php esc_attr_e('Published code block preview', 'tasty-fonts'); ?>" aria-labelledby="<?php echo esc_attr($blockPreviewHeadingId); ?>">
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">01</span>
                        <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-selector">.wp-block-code code</span> <span class="tasty-fonts-preview-token-punctuation">{</span></span>
                    </div>
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">02</span>
                        <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">font-family</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-function">var</span><span class="tasty-fonts-preview-token-punctuation">(</span><span class="tasty-fonts-preview-token-variable">--font-monospace</span><span class="tasty-fonts-preview-token-punctuation">)</span><span class="tasty-fonts-preview-token-punctuation">;</span></span>
                    </div>
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">03</span>
                        <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">font-size</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-number">0.95rem</span><span class="tasty-fonts-preview-token-punctuation">;</span></span>
                    </div>
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">04</span>
                        <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">line-height</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-number">1.65</span><span class="tasty-fonts-preview-token-punctuation">;</span></span>
                    </div>
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">05</span>
                        <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">background</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-string">linear-gradient(180deg, rgba(255,255,255,0.96), rgba(248,250,252,0.98))</span><span class="tasty-fonts-preview-token-punctuation">;</span></span>
                    </div>
                    <div class="tasty-fonts-preview-code-line">
                        <span class="tasty-fonts-preview-code-line-number">06</span>
                        <span class="tasty-fonts-preview-code-line-content"><span class="tasty-fonts-preview-token-punctuation">}</span></span>
                    </div>
                </div>
            </div>

            <p class="tasty-fonts-preview-code-caption" data-role-preview="body"><?php esc_html_e('Check braces, punctuation, zeroes, and how the selected code face holds together in both an editor and a front-end code block.', 'tasty-fonts'); ?></p>
        </section>
    </div>
</div>
