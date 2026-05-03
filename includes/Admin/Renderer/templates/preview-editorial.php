<?php defined('ABSPATH') || exit; ?>
<div class="tasty-fonts-preview-showcase">
    <div class="tasty-fonts-preview-specimen-board">
        <section class="tasty-fonts-preview-specimen-panel tasty-fonts-preview-specimen-panel--identity" aria-label="<?php esc_attr_e('Type specimen identity', 'tasty-fonts'); ?>">
            <header class="tasty-fonts-preview-specimen-poster">
                <div class="tasty-fonts-preview-specimen-poster-glyph">
                    <span class="tasty-fonts-preview-specimen-glyph" data-role-preview="heading">Aa</span>
                    <span class="tasty-fonts-preview-specimen-poster-counter" data-role-preview="body" aria-hidden="true"><?php esc_html_e('No. 01 / Specimen', 'tasty-fonts'); ?></span>
                </div>
                <div class="tasty-fonts-preview-specimen-poster-meta">
                    <span class="tasty-fonts-preview-specimen-poster-eyebrow" data-role-preview="body"><?php esc_html_e('Type Specimen', 'tasty-fonts'); ?></span>
                    <h3 class="tasty-fonts-preview-specimen-poster-title" data-role-preview="heading"><?php esc_html_e('Hello, world &mdash; this is how your typography reads.', 'tasty-fonts'); ?></h3>
                    <?php $this->renderPreviewRoleList($roles, $familyLabels, $monospaceRoleEnabled, 'tasty-fonts-preview-specimen-poster-keys', 'tasty-fonts-preview-specimen-poster-key', true, $globalFallbackSettings); ?>
                </div>
            </header>

            <div class="tasty-fonts-preview-specimen-divider" aria-hidden="true"></div>

            <section class="tasty-fonts-preview-specimen-glyphs" aria-label="<?php esc_attr_e('Glyph showcase', 'tasty-fonts'); ?>">
                <span class="tasty-fonts-preview-specimen-section-eyebrow" data-role-preview="body"><?php esc_html_e('Glyphs &amp; Numerals', 'tasty-fonts'); ?></span>
                <div class="tasty-fonts-preview-specimen-glyph-row tasty-fonts-preview-specimen-glyph-row--upper" data-role-preview="heading" aria-hidden="true">ABCDEFGHIJKLMNOPQRSTUVWXYZ</div>
                <div class="tasty-fonts-preview-specimen-glyph-row tasty-fonts-preview-specimen-glyph-row--lower" data-role-preview="body" aria-hidden="true">abcdefghijklmnopqrstuvwxyz</div>
                <div class="tasty-fonts-preview-specimen-glyph-row tasty-fonts-preview-specimen-glyph-row--punctuation" data-role-preview="body" aria-hidden="true">0123456789 &mdash; &amp; &middot; &ldquo; &rdquo; ! ? @ # %</div>
            </section>

            <aside class="tasty-fonts-preview-specimen-pullquote" data-role-preview="heading">
                <span class="tasty-fonts-preview-specimen-pullquote-mark" aria-hidden="true">&ldquo;</span>
                <blockquote class="tasty-fonts-preview-specimen-quote"><?php esc_html_e('The sky was cloudless and of a deep dark blue.', 'tasty-fonts'); ?></blockquote>
                <cite class="tasty-fonts-preview-specimen-pullquote-cite" data-role-preview="body"><?php esc_html_e('Sathyvelu Kunashegaran', 'tasty-fonts'); ?></cite>
            </aside>
        </section>

        <section class="tasty-fonts-preview-specimen-panel tasty-fonts-preview-specimen-panel--usage" aria-label="<?php esc_attr_e('Type specimen usage', 'tasty-fonts'); ?>">
            <section class="tasty-fonts-preview-specimen-ladder" aria-label="<?php esc_attr_e('Heading scale', 'tasty-fonts'); ?>">
                <span class="tasty-fonts-preview-specimen-section-eyebrow" data-role-preview="body"><?php esc_html_e('Display Scale', 'tasty-fonts'); ?></span>
                <div class="tasty-fonts-preview-specimen-ladder-stack">
                    <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--1" data-role-preview="heading">
                        <span class="tasty-fonts-preview-specimen-scale-meta">H1 / 700</span>
                        <span class="tasty-fonts-preview-specimen-scale-text"><?php esc_html_e('A bold first impression', 'tasty-fonts'); ?></span>
                    </div>
                    <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--2" data-role-preview="heading">
                        <span class="tasty-fonts-preview-specimen-scale-meta">H2 / 700</span>
                        <span class="tasty-fonts-preview-specimen-scale-text"><?php esc_html_e('A confident second voice', 'tasty-fonts'); ?></span>
                    </div>
                    <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--3" data-role-preview="heading">
                        <span class="tasty-fonts-preview-specimen-scale-meta">H3 / 600</span>
                        <span class="tasty-fonts-preview-specimen-scale-text"><?php esc_html_e('Section headings stay legible', 'tasty-fonts'); ?></span>
                    </div>
                    <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--4" data-role-preview="heading">
                        <span class="tasty-fonts-preview-specimen-scale-meta">H4 / 600</span>
                        <span class="tasty-fonts-preview-specimen-scale-text"><?php esc_html_e('Subsections keep their rhythm', 'tasty-fonts'); ?></span>
                    </div>
                    <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--5" data-role-preview="heading">
                        <span class="tasty-fonts-preview-specimen-scale-meta">H5 / 600</span>
                        <span class="tasty-fonts-preview-specimen-scale-text"><?php esc_html_e('Callouts and inline labels', 'tasty-fonts'); ?></span>
                    </div>
                    <div class="tasty-fonts-preview-specimen-scale-item tasty-fonts-preview-specimen-scale-item--6" data-role-preview="heading">
                        <span class="tasty-fonts-preview-specimen-scale-meta">H6 / 600</span>
                        <span class="tasty-fonts-preview-specimen-scale-text"><?php esc_html_e('Smallest structural rung', 'tasty-fonts'); ?></span>
                    </div>
                </div>
            </section>

            <div class="tasty-fonts-preview-specimen-divider" aria-hidden="true"></div>

            <section class="tasty-fonts-preview-specimen-editorial" aria-label="<?php esc_attr_e('Editorial sample', 'tasty-fonts'); ?>">
                <div class="tasty-fonts-preview-specimen-editorial-flow">
                    <span class="tasty-fonts-preview-specimen-section-eyebrow" data-role-preview="body"><?php esc_html_e('In Use &middot; Editorial', 'tasty-fonts'); ?></span>
                    <h4 class="tasty-fonts-preview-specimen-editorial-title" data-role-preview="heading"><?php esc_html_e('A Type Pairing That Carries Real Editorial Weight at Every Reading Size.', 'tasty-fonts'); ?></h4>
                    <p class="tasty-fonts-preview-specimen-lead" data-role-preview="body"><?php esc_html_e('Inside a real editorial system, the type has to do more than look beautiful in isolation. It needs to carry a headline, settle into paragraphs, and keep every small detail feeling deliberate.', 'tasty-fonts'); ?></p>
                    <p class="tasty-fonts-preview-specimen-body-large" data-role-preview="body"><?php esc_html_e('A strong heading face guides the eye through hierarchy. The body face stays calm across long passages so readers settle in. Together, they keep tone, contrast, and rhythm consistent everywhere they ship.', 'tasty-fonts'); ?></p>
                    <ul class="tasty-fonts-preview-specimen-editorial-meta" data-role-preview="body">
                        <li><span class="tasty-fonts-preview-specimen-editorial-meta-label"><?php esc_html_e('Caps', 'tasty-fonts'); ?></span><span class="tasty-fonts-preview-specimen-caps"><?php esc_html_e('Brainstorm alternative ideas', 'tasty-fonts'); ?></span></li>
                        <li><span class="tasty-fonts-preview-specimen-editorial-meta-label"><?php esc_html_e('Small', 'tasty-fonts'); ?></span><span class="tasty-fonts-preview-specimen-small"><?php esc_html_e('Value your time. Edit ruthlessly.', 'tasty-fonts'); ?></span></li>
                        <li><span class="tasty-fonts-preview-specimen-editorial-meta-label"><?php esc_html_e('Tiny', 'tasty-fonts'); ?></span><span class="tasty-fonts-preview-specimen-tiny"><?php esc_html_e('Nothing is impossible &mdash; legal disclosures, captions, footnotes', 'tasty-fonts'); ?></span></li>
                    </ul>
                </div>
            </section>
        </section>
    </div>
</div>
