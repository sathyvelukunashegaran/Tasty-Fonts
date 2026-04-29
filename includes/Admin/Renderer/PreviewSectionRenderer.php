<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type RoleSet from \TastyFonts\Repository\SettingsRepository
 * @phpstan-type PreviewView array<string, mixed>
 * @phpstan-type FamilyLabelMap array<string, string>
 * @phpstan-type FamilyOption array{value: string, label: string, type?: string}
 * @phpstan-type FamilyOptionList list<FamilyOption>
 */
final class PreviewSectionRenderer extends AbstractSectionRenderer
{
    /**
     * @param PreviewView $view
     */
    public function render(array $view): void
    {
        $this->renderTemplate('preview-section.php', $view);
    }

    /**
     * @param RoleSet $roles
     * @param FamilyLabelMap $familyLabels
     */
    public function renderPreviewScene(string $key, string $previewText, array $roles, bool $monospaceRoleEnabled = false, array $familyLabels = []): void
    {
        switch ($key) {
            case 'editorial':
                ?>
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
                                    <dl class="tasty-fonts-preview-specimen-poster-keys">
                                        <div class="tasty-fonts-preview-specimen-poster-key">
                                            <dt><?php esc_html_e('Heading Family', 'tasty-fonts'); ?></dt>
                                            <dd data-role-preview="heading" data-role-preview-name="heading"><?php echo esc_html($this->previewRoleName('heading', $roles, $familyLabels)); ?></dd>
                                        </div>
                                        <div class="tasty-fonts-preview-specimen-poster-key">
                                            <dt><?php esc_html_e('Body Family', 'tasty-fonts'); ?></dt>
                                            <dd data-role-preview="body" data-role-preview-name="body"><?php echo esc_html($this->previewRoleName('body', $roles, $familyLabels)); ?></dd>
                                        </div>
                                        <?php if ($monospaceRoleEnabled): ?>
                                            <div class="tasty-fonts-preview-specimen-poster-key">
                                                <dt><?php esc_html_e('Monospace', 'tasty-fonts'); ?></dt>
                                                <dd data-role-preview="monospace" data-role-preview-name="monospace"><?php echo esc_html($this->previewRoleName('monospace', $roles, $familyLabels)); ?></dd>
                                            </div>
                                        <?php endif; ?>
                                    </dl>
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
                <?php
                return;

            case 'card':
                ?>
                <div class="tasty-fonts-preview-card-board">
                    <div class="tasty-fonts-preview-card-gallery">
                        <article class="tasty-fonts-preview-article-tile">
                            <div class="tasty-fonts-preview-article-cover" aria-hidden="true">
                                <span class="tasty-fonts-preview-article-cover-mark" data-role-preview="heading">A</span>
                                <span class="tasty-fonts-preview-article-cover-orbit"></span>
                                <span class="tasty-fonts-preview-article-cover-chip" data-role-preview="body"><?php esc_html_e('Cover Story', 'tasty-fonts'); ?></span>
                            </div>
                            <div class="tasty-fonts-preview-article-body">
                                <div class="tasty-fonts-preview-article-meta">
                                    <span class="tasty-fonts-preview-article-kicker" data-role-preview="body"><?php esc_html_e('Editorial', 'tasty-fonts'); ?></span>
                                    <span class="tasty-fonts-preview-article-dot" aria-hidden="true">&middot;</span>
                                    <span data-role-preview="body"><?php esc_html_e('5 min read', 'tasty-fonts'); ?></span>
                                </div>
                                <h3 class="tasty-fonts-preview-article-title" data-role-preview="heading"><?php esc_html_e('The Quiet Craft of Choosing Typefaces That Feel Inevitable.', 'tasty-fonts'); ?></h3>
                                <p class="tasty-fonts-preview-article-excerpt" data-role-preview="body"><?php esc_html_e('Inside the studio, every curve, counter, and line break has to earn its place. The right pairing gives a story rhythm before the reader notices the system underneath.', 'tasty-fonts'); ?></p>
                                <div class="tasty-fonts-preview-article-byline">
                                    <span class="tasty-fonts-preview-article-avatar" aria-hidden="true" data-role-preview="heading">SK</span>
                                    <span class="tasty-fonts-preview-article-byline-copy">
                                        <strong data-role-preview="heading"><?php esc_html_e('Sathyvelu Kunashegaran', 'tasty-fonts'); ?></strong>
                                        <span data-role-preview="body"><?php esc_html_e('Design Director &middot; Apr 24', 'tasty-fonts'); ?></span>
                                    </span>
                                </div>
                                <div class="tasty-fonts-preview-article-tags" data-role-preview="body" aria-hidden="true">
                                    <span class="tasty-fonts-preview-article-tag"><?php esc_html_e('Typography', 'tasty-fonts'); ?></span>
                                    <span class="tasty-fonts-preview-article-tag"><?php esc_html_e('Branding', 'tasty-fonts'); ?></span>
                                    <span class="tasty-fonts-preview-article-tag"><?php esc_html_e('Process', 'tasty-fonts'); ?></span>
                                </div>
                            </div>
                        </article>

                        <article class="tasty-fonts-preview-pricing-tile">
                            <div class="tasty-fonts-preview-pricing-head">
                                <span class="tasty-fonts-preview-pricing-eyebrow" data-role-preview="body"><?php esc_html_e('Studio Plan', 'tasty-fonts'); ?></span>
                                <span class="tasty-fonts-preview-pricing-flag" data-role-preview="body" aria-hidden="true"><?php esc_html_e('Most Popular', 'tasty-fonts'); ?></span>
                            </div>
                            <h3 class="tasty-fonts-preview-pricing-name" data-role-preview="heading"><?php esc_html_e('Pro', 'tasty-fonts'); ?></h3>
                            <div class="tasty-fonts-preview-pricing-amount" data-role-preview="heading" aria-label="<?php esc_attr_e('Twenty-four dollars per month', 'tasty-fonts'); ?>">
                                <span class="tasty-fonts-preview-pricing-currency">$</span>
                                <span class="tasty-fonts-preview-pricing-value">24</span>
                                <span class="tasty-fonts-preview-pricing-period" data-role-preview="body">/mo</span>
                            </div>
                            <p class="tasty-fonts-preview-pricing-summary" data-role-preview="body"><?php esc_html_e('Everything you need to ship a confident type system &mdash; multi-role libraries, role variables, and live previews.', 'tasty-fonts'); ?></p>
                            <ul class="tasty-fonts-preview-pricing-list" data-role-preview="body">
                                <li><?php esc_html_e('Unlimited managed font families', 'tasty-fonts'); ?></li>
                                <li><?php esc_html_e('Role variables &amp; preview rooms', 'tasty-fonts'); ?></li>
                                <li><?php esc_html_e('Bricks &amp; Oxygen sync, Block Editor', 'tasty-fonts'); ?></li>
                                <li><?php esc_html_e('Priority support &amp; CLI tooling', 'tasty-fonts'); ?></li>
                            </ul>
                            <div class="tasty-fonts-preview-pricing-actions">
                                <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Start free trial', 'tasty-fonts'); ?></span>
                                <span class="tasty-fonts-preview-pricing-finish" data-role-preview="body"><?php esc_html_e('Cancel any time', 'tasty-fonts'); ?></span>
                            </div>
                        </article>

                        <article class="tasty-fonts-preview-testimonial-tile">
                            <span class="tasty-fonts-preview-testimonial-mark" data-role-preview="heading" aria-hidden="true">&ldquo;</span>
                            <blockquote class="tasty-fonts-preview-testimonial-quote" data-role-preview="heading"><?php esc_html_e('The pairing finally feels inevitable. Headings carry confidence, body copy stays calm, and the whole product reads as one voice.', 'tasty-fonts'); ?></blockquote>
                            <div class="tasty-fonts-preview-testimonial-rating" aria-label="<?php esc_attr_e('Five out of five rating', 'tasty-fonts'); ?>" data-role-preview="heading">&#9733; &#9733; &#9733; &#9733; &#9733;</div>
                            <div class="tasty-fonts-preview-testimonial-author">
                                <span class="tasty-fonts-preview-testimonial-avatar" data-role-preview="heading" aria-hidden="true">SK</span>
                                <span class="tasty-fonts-preview-testimonial-author-copy">
                                    <strong data-role-preview="heading"><?php esc_html_e('Sathyvelu Kunashegaran', 'tasty-fonts'); ?></strong>
                                    <span data-role-preview="body"><?php esc_html_e('Head of Design, TastyWP', 'tasty-fonts'); ?></span>
                                </span>
                            </div>
                            <div class="tasty-fonts-preview-testimonial-footer" data-role-preview="body" aria-hidden="true">
                                <span class="tasty-fonts-preview-testimonial-source"><?php esc_html_e('Verified review', 'tasty-fonts'); ?></span>
                                <span class="tasty-fonts-preview-testimonial-date"><?php esc_html_e('Two weeks ago', 'tasty-fonts'); ?></span>
                            </div>
                        </article>
                    </div>
                </div>
                <?php
                return;

            case 'reading':
                ?>
                <article class="tasty-fonts-preview-reading-sheet">
                    <header class="tasty-fonts-preview-reading-masthead">
                        <div class="tasty-fonts-preview-reading-masthead-copy">
                            <div class="tasty-fonts-preview-reading-meta-row" data-role-preview="body" aria-hidden="true">
                                <span class="tasty-fonts-preview-reading-eyebrow"><?php esc_html_e('Field Notes', 'tasty-fonts'); ?></span>
                                <span class="tasty-fonts-preview-reading-meta-dot" aria-hidden="true">&middot;</span>
                                <span class="tasty-fonts-preview-reading-issue"><?php esc_html_e('Issue No. 14', 'tasty-fonts'); ?></span>
                                <span class="tasty-fonts-preview-reading-meta-dot" aria-hidden="true">&middot;</span>
                                <span class="tasty-fonts-preview-reading-date"><?php esc_html_e('April 2026', 'tasty-fonts'); ?></span>
                            </div>
                            <h3 class="tasty-fonts-preview-reading-title" data-role-preview="heading"><?php esc_html_e('A Quiet Manifesto for Typography That Disappears Into the Reading.', 'tasty-fonts'); ?></h3>
                            <p class="tasty-fonts-preview-reading-standfirst" data-role-preview="body"><?php esc_html_e('Great body type does not announce itself. It carries the reader through long passages without ever asking to be admired &mdash; and that is exactly why it is so hard to choose well.', 'tasty-fonts'); ?></p>
                            <div class="tasty-fonts-preview-reading-byline">
                                <span class="tasty-fonts-preview-reading-avatar" data-role-preview="heading" aria-hidden="true">SK</span>
                                <span class="tasty-fonts-preview-reading-byline-copy">
                                    <strong data-role-preview="heading"><?php esc_html_e('Sathyvelu Kunashegaran', 'tasty-fonts'); ?></strong>
                                    <span data-role-preview="body"><?php esc_html_e('Senior Type Editor &middot; 8 min read', 'tasty-fonts'); ?></span>
                                </span>
                            </div>
                        </div>
                        <figure class="tasty-fonts-preview-reading-feature" aria-label="<?php esc_attr_e('Feature image placeholder', 'tasty-fonts'); ?>">
                            <span class="tasty-fonts-preview-reading-feature-label" data-role-preview="body"><?php esc_html_e('Type Study', 'tasty-fonts'); ?></span>
                            <span class="tasty-fonts-preview-reading-feature-mark" data-role-preview="heading" aria-hidden="true">Aa</span>
                            <figcaption class="tasty-fonts-preview-reading-feature-caption" data-role-preview="body"><?php esc_html_e('Measured margins, honest spacing, steady rhythm.', 'tasty-fonts'); ?></figcaption>
                        </figure>
                    </header>

                    <div class="tasty-fonts-preview-reading-rule" aria-hidden="true"></div>

                    <div class="tasty-fonts-preview-reading-spread">
                        <div class="tasty-fonts-preview-reading-prose" data-role-preview="body">
                            <p class="tasty-fonts-preview-reading-lead"><span class="tasty-fonts-preview-reading-dropcap" data-role-preview="heading">T</span><?php esc_html_e('ypography becomes invisible only after the page has done a great deal of quiet work. Margins set the pace, paragraphs hold the breath, and every heading gives the reader a place to land before moving on.', 'tasty-fonts'); ?></p>
                            <p><?php esc_html_e('Apparently we had reached a great height in the atmosphere, for the sky was a dead black, and the stars had ceased to twinkle. We watched the world fall away and felt the page steady under our hands.', 'tasty-fonts'); ?></p>
                            <p><?php esc_html_e('A strong reading face stays calm across long passages and still leaves enough contrast for section headings and pull quotes. The eye should never have to argue with the page.', 'tasty-fonts'); ?></p>

                            <aside class="tasty-fonts-preview-reading-pullquote">
                                <span class="tasty-fonts-preview-reading-pullquote-mark" data-role-preview="heading" aria-hidden="true">&ldquo;</span>
                                <p data-role-preview="heading"><?php esc_html_e('Body type carries trust. Get it wrong and every other choice has to apologise for it.', 'tasty-fonts'); ?></p>
                            </aside>

                            <h4 class="tasty-fonts-preview-reading-subhead" data-role-preview="heading"><?php esc_html_e('Three habits of calm reading type', 'tasty-fonts'); ?></h4>
                            <p><?php esc_html_e('Calm reading faces share three habits. They keep their x-height honest, their punctuation visible, and their numerals tabular when the page asks for it &mdash; and they hand the personality back to the headlines.', 'tasty-fonts'); ?></p>
                            <p><?php esc_html_e('Pair them with a confident heading face and the page does the work for you. Stop adjusting line height for the third time and let the type breathe.', 'tasty-fonts'); ?></p>
                        </div>

                        <aside class="tasty-fonts-preview-reading-aside">
                            <h4 class="tasty-fonts-preview-reading-aside-title" data-role-preview="heading"><?php esc_html_e('Reader checklist', 'tasty-fonts'); ?></h4>
                            <p class="tasty-fonts-preview-reading-aside-copy" data-role-preview="body"><?php esc_html_e('Run through these before shipping a body face into long-form layouts.', 'tasty-fonts'); ?></p>
                            <ul class="tasty-fonts-preview-reading-list" data-role-preview="body">
                                <li><?php esc_html_e('Paragraph spacing reads as a system, not a habit', 'tasty-fonts'); ?></li>
                                <li><?php esc_html_e('Line length stays inside 60 to 75 characters', 'tasty-fonts'); ?></li>
                                <li><?php esc_html_e('Subheadings carry weight without shouting', 'tasty-fonts'); ?></li>
                                <li><?php esc_html_e('Italics and small caps both look intentional', 'tasty-fonts'); ?></li>
                            </ul>
                            <div class="tasty-fonts-preview-reading-aside-footer">
                                <span class="tasty-fonts-preview-reading-aside-tag" data-role-preview="body"><?php esc_html_e('Bookmark', 'tasty-fonts'); ?></span>
                                <span class="tasty-fonts-preview-reading-aside-meta" data-role-preview="body"><?php esc_html_e('Saved &middot; Shared 142&times;', 'tasty-fonts'); ?></span>
                            </div>
                        </aside>
                    </div>

                    <footer class="tasty-fonts-preview-reading-footer">
                        <span class="tasty-fonts-preview-reading-footer-label" data-role-preview="body"><?php esc_html_e('Up next', 'tasty-fonts'); ?></span>
                        <strong class="tasty-fonts-preview-reading-footer-title" data-role-preview="heading"><?php esc_html_e('How to Ship a Body Face That Holds Up at Every Reading Size', 'tasty-fonts'); ?></strong>
                        <span class="tasty-fonts-preview-reading-footer-meta" data-role-preview="body"><?php esc_html_e('6 min read &middot; Continue', 'tasty-fonts'); ?></span>
                    </footer>
                </article>
                <?php
                return;

            case 'marketing':
                ?>
                <div class="tasty-fonts-preview-marketing-board">
                    <section class="tasty-fonts-preview-marketing-cta" aria-label="<?php esc_attr_e('Marketing CTA sample', 'tasty-fonts'); ?>">
                        <span class="tasty-fonts-preview-marketing-kicker" data-role-preview="body"><?php esc_html_e('CTA section', 'tasty-fonts'); ?></span>
                        <h3 class="tasty-fonts-preview-marketing-title" data-role-preview="heading"><?php esc_html_e('Turn scattered font choices into one clear system.', 'tasty-fonts'); ?></h3>
                        <p class="tasty-fonts-preview-marketing-copy" data-role-preview="body"><?php esc_html_e('Preview the headline, supporting copy, and action labels before a campaign page goes live.', 'tasty-fonts'); ?></p>
                        <div class="tasty-fonts-preview-marketing-actions" aria-hidden="true">
                            <span class="button button-primary" data-role-preview="body"><?php esc_html_e('Start free', 'tasty-fonts'); ?></span>
                            <span class="button" data-role-preview="body"><?php esc_html_e('See examples', 'tasty-fonts'); ?></span>
                        </div>
                    </section>

                    <div class="tasty-fonts-preview-marketing-grid">
                        <section class="tasty-fonts-preview-marketing-snippet tasty-fonts-preview-marketing-snippet--signup" aria-label="<?php esc_attr_e('Email signup sample', 'tasty-fonts'); ?>">
                            <span class="tasty-fonts-preview-marketing-kicker" data-role-preview="body"><?php esc_html_e('Email signup', 'tasty-fonts'); ?></span>
                            <h3 class="tasty-fonts-preview-marketing-subtitle" data-role-preview="heading"><?php esc_html_e('Get the type checklist', 'tasty-fonts'); ?></h3>
                            <p class="tasty-fonts-preview-marketing-copy" data-role-preview="body"><?php esc_html_e('A practical list for checking headings, forms, and small print before launch.', 'tasty-fonts'); ?></p>
                            <div class="tasty-fonts-preview-marketing-field" aria-hidden="true">
                                <span data-role-preview="body"><?php esc_html_e('you@example.com', 'tasty-fonts'); ?></span>
                                <span class="button button-primary" data-role-preview="body"><?php esc_html_e('Join', 'tasty-fonts'); ?></span>
                            </div>
                            <span class="tasty-fonts-preview-marketing-note" data-role-preview="body"><?php esc_html_e('Sent to 2,400 designers each Friday.', 'tasty-fonts'); ?></span>
                        </section>

                        <section class="tasty-fonts-preview-marketing-snippet tasty-fonts-preview-marketing-snippet--buy" aria-label="<?php esc_attr_e('Buy now sample', 'tasty-fonts'); ?>">
                            <span class="tasty-fonts-preview-marketing-kicker" data-role-preview="body"><?php esc_html_e('Buy now', 'tasty-fonts'); ?></span>
                            <h3 class="tasty-fonts-preview-marketing-subtitle" data-role-preview="heading"><?php esc_html_e('Launch Kit', 'tasty-fonts'); ?></h3>
                            <div class="tasty-fonts-preview-marketing-price" data-role-preview="heading" aria-label="<?php esc_attr_e('Forty-nine dollars', 'tasty-fonts'); ?>">
                                <span class="tasty-fonts-preview-marketing-currency">$</span>
                                <span class="tasty-fonts-preview-marketing-price-value">49</span>
                            </div>
                            <p class="tasty-fonts-preview-marketing-copy" data-role-preview="body"><?php esc_html_e('Includes 12 landing page sections, pricing rows, and button states.', 'tasty-fonts'); ?></p>
                            <span class="button button-primary" data-role-preview="body" aria-hidden="true"><?php esc_html_e('Buy now', 'tasty-fonts'); ?></span>
                        </section>

                        <section class="tasty-fonts-preview-marketing-snippet tasty-fonts-preview-marketing-snippet--social" aria-label="<?php esc_attr_e('Social follow sample', 'tasty-fonts'); ?>">
                            <span class="tasty-fonts-preview-marketing-kicker" data-role-preview="body"><?php esc_html_e('Follow social', 'tasty-fonts'); ?></span>
                            <div class="tasty-fonts-preview-marketing-profile">
                                <span class="tasty-fonts-preview-marketing-avatar" data-role-preview="heading" aria-hidden="true">TW</span>
                                <span class="tasty-fonts-preview-marketing-profile-copy">
                                    <strong data-role-preview="heading"><?php esc_html_e('TastyWP Studio', 'tasty-fonts'); ?></strong>
                                    <span data-role-preview="body"><?php esc_html_e('@TastyWP', 'tasty-fonts'); ?></span>
                                </span>
                            </div>
                            <p class="tasty-fonts-preview-marketing-copy" data-role-preview="body"><?php esc_html_e('Follow for weekly type notes, launch teardown posts, and field examples.', 'tasty-fonts'); ?></p>
                            <div class="tasty-fonts-preview-marketing-social-row">
                                <span class="tasty-fonts-preview-marketing-social-stat">
                                    <span class="tasty-fonts-preview-marketing-social-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" focusable="false">
                                            <rect x="4.5" y="4.5" width="15" height="15" rx="4.2"></rect>
                                            <circle cx="12" cy="12" r="3.4"></circle>
                                            <circle cx="16.4" cy="7.6" r="1"></circle>
                                        </svg>
                                    </span>
                                    <strong data-role-preview="heading">12.8k</strong>
                                    <span class="tasty-fonts-preview-marketing-social-label" data-role-preview="body"><?php esc_html_e('followers', 'tasty-fonts'); ?></span>
                                </span>
                                <span class="button" data-role-preview="body" aria-hidden="true"><?php esc_html_e('Follow', 'tasty-fonts'); ?></span>
                            </div>
                        </section>
                    </div>
                </div>
                <?php
                return;

            case 'code':
                $this->renderCodePreviewScene($previewText, $roles, $monospaceRoleEnabled, $familyLabels);
                return;

            case 'snippet':
                $this->renderSnippetPreviewScene($roles, $monospaceRoleEnabled, $familyLabels);
                return;

            case 'interface':
            default:
                ?>
                <div class="tasty-fonts-preview-ui-shell">
                    <header class="tasty-fonts-preview-ui-topbar">
                        <div class="tasty-fonts-preview-ui-topbar-brand">
                            <span class="tasty-fonts-preview-ui-topbar-mark" data-role-preview="heading" aria-hidden="true">N</span>
                            <span class="tasty-fonts-preview-ui-topbar-copy">
                                <span class="tasty-fonts-preview-ui-topbar-label" data-role-preview="body"><?php esc_html_e('TastyWP &rsaquo; Marketing', 'tasty-fonts'); ?></span>
                                <strong class="tasty-fonts-preview-ui-topbar-title" data-role-preview="heading"><?php esc_html_e('Spring Launch Workspace', 'tasty-fonts'); ?></strong>
                            </span>
                        </div>
                        <div class="tasty-fonts-preview-ui-topbar-tools">
                            <span class="tasty-fonts-preview-ui-topbar-status" data-role-preview="body"><?php esc_html_e('Live', 'tasty-fonts'); ?></span>
                            <span class="button" aria-hidden="true"><?php esc_html_e('View', 'tasty-fonts'); ?></span>
                            <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Edit', 'tasty-fonts'); ?></span>
                        </div>
                    </header>

                    <section class="tasty-fonts-preview-ui-hero" aria-label="<?php esc_attr_e('Workspace headline metric', 'tasty-fonts'); ?>">
                        <div class="tasty-fonts-preview-ui-hero-copy">
                            <span class="tasty-fonts-preview-ui-label" data-role-preview="body"><?php esc_html_e('Active campaign', 'tasty-fonts'); ?></span>
                            <h3 class="tasty-fonts-preview-ui-title" data-role-preview="heading"><?php esc_html_e('Launch Planning', 'tasty-fonts'); ?></h3>
                            <p class="tasty-fonts-preview-ui-copy" data-role-preview="body"><?php esc_html_e('Coordinate the final launch sequence, review campaign assets, and keep every team update moving through one shared workspace.', 'tasty-fonts'); ?></p>
                            <div class="tasty-fonts-preview-ui-hero-chips">
                                <span class="tasty-fonts-preview-ui-chip tasty-fonts-preview-ui-chip--ok" data-role-preview="body"><?php esc_html_e('Schedule on track', 'tasty-fonts'); ?></span>
                                <span class="tasty-fonts-preview-ui-chip" data-role-preview="body"><?php esc_html_e('14 contributors', 'tasty-fonts'); ?></span>
                                <span class="tasty-fonts-preview-ui-chip" data-role-preview="body"><?php esc_html_e('Updated just now', 'tasty-fonts'); ?></span>
                            </div>
                        </div>
                        <div class="tasty-fonts-preview-ui-hero-metric">
                            <span class="tasty-fonts-preview-ui-label" data-role-preview="body"><?php esc_html_e('Signups this week', 'tasty-fonts'); ?></span>
                            <div class="tasty-fonts-preview-ui-hero-number" data-role-preview="heading">
                                <span class="tasty-fonts-preview-ui-hero-value">12,438</span>
                                <span class="tasty-fonts-preview-ui-hero-delta" data-role-preview="body">&#9650; 8.2%</span>
                            </div>
                            <svg class="tasty-fonts-preview-ui-sparkline" viewBox="0 0 200 56" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                <polyline points="0,42 22,38 44,40 66,30 88,34 110,22 132,26 154,16 176,20 200,8"></polyline>
                            </svg>
                            <span class="tasty-fonts-preview-ui-hero-foot" data-role-preview="body"><?php esc_html_e('vs. 11,498 last week', 'tasty-fonts'); ?></span>
                        </div>
                    </section>

                    <section class="tasty-fonts-preview-ui-stats-row" aria-label="<?php esc_attr_e('Workspace metrics', 'tasty-fonts'); ?>">
                        <div class="tasty-fonts-preview-ui-stat">
                            <span data-role-preview="body"><?php esc_html_e('Visitors', 'tasty-fonts'); ?></span>
                            <strong data-role-preview="heading">12.4k</strong>
                            <span class="tasty-fonts-preview-ui-stat-delta tasty-fonts-preview-ui-stat-delta--up" data-role-preview="body">&uarr; 6.1%</span>
                        </div>
                        <div class="tasty-fonts-preview-ui-stat">
                            <span data-role-preview="body"><?php esc_html_e('Signups', 'tasty-fonts'); ?></span>
                            <strong data-role-preview="heading">318</strong>
                            <span class="tasty-fonts-preview-ui-stat-delta tasty-fonts-preview-ui-stat-delta--up" data-role-preview="body">&uarr; 12.4%</span>
                        </div>
                        <div class="tasty-fonts-preview-ui-stat">
                            <span data-role-preview="body"><?php esc_html_e('Conversion', 'tasty-fonts'); ?></span>
                            <strong data-role-preview="heading">4.8%</strong>
                            <span class="tasty-fonts-preview-ui-stat-delta tasty-fonts-preview-ui-stat-delta--down" data-role-preview="body">&darr; 0.3%</span>
                        </div>
                        <div class="tasty-fonts-preview-ui-stat">
                            <span data-role-preview="body"><?php esc_html_e('NPS', 'tasty-fonts'); ?></span>
                            <strong data-role-preview="heading">62</strong>
                            <span class="tasty-fonts-preview-ui-stat-delta" data-role-preview="body"><?php esc_html_e('Steady', 'tasty-fonts'); ?></span>
                        </div>
                    </section>

                    <section class="tasty-fonts-preview-ui-feed" aria-label="<?php esc_attr_e('Recent activity', 'tasty-fonts'); ?>">
                        <header class="tasty-fonts-preview-ui-feed-head">
                            <h4 class="tasty-fonts-preview-ui-feed-title" data-role-preview="heading"><?php esc_html_e('Recent activity', 'tasty-fonts'); ?></h4>
                            <span class="tasty-fonts-preview-ui-feed-meta" data-role-preview="body"><?php esc_html_e('Today &middot; 4 updates', 'tasty-fonts'); ?></span>
                        </header>
                        <div class="tasty-fonts-preview-ui-feed-list">
                            <article class="tasty-fonts-preview-ui-feed-row">
                                <span class="tasty-fonts-preview-ui-feed-avatar" data-role-preview="heading" aria-hidden="true">SK</span>
                                <span class="tasty-fonts-preview-ui-feed-copy">
                                    <strong data-role-preview="heading"><?php esc_html_e('Headline lockup', 'tasty-fonts'); ?></strong>
                                    <span data-role-preview="body"><?php esc_html_e('Sathyvelu Kunashegaran approved the final hero pairing.', 'tasty-fonts'); ?></span>
                                </span>
                                <span class="tasty-fonts-preview-ui-feed-pill tasty-fonts-preview-ui-feed-pill--ok" data-role-preview="body"><?php esc_html_e('Approved', 'tasty-fonts'); ?></span>
                                <span class="tasty-fonts-preview-ui-feed-time" data-role-preview="body"><?php esc_html_e('2m', 'tasty-fonts'); ?></span>
                            </article>
                            <article class="tasty-fonts-preview-ui-feed-row">
                                <span class="tasty-fonts-preview-ui-feed-avatar" data-role-preview="heading" aria-hidden="true">SK</span>
                                <span class="tasty-fonts-preview-ui-feed-copy">
                                    <strong data-role-preview="heading"><?php esc_html_e('Landing page copy', 'tasty-fonts'); ?></strong>
                                    <span data-role-preview="body"><?php esc_html_e('Sathyvelu Kunashegaran shared revisions for the second pass.', 'tasty-fonts'); ?></span>
                                </span>
                                <span class="tasty-fonts-preview-ui-feed-pill tasty-fonts-preview-ui-feed-pill--review" data-role-preview="body"><?php esc_html_e('In Review', 'tasty-fonts'); ?></span>
                                <span class="tasty-fonts-preview-ui-feed-time" data-role-preview="body"><?php esc_html_e('14m', 'tasty-fonts'); ?></span>
                            </article>
                            <article class="tasty-fonts-preview-ui-feed-row">
                                <span class="tasty-fonts-preview-ui-feed-avatar" data-role-preview="heading" aria-hidden="true">SK</span>
                                <span class="tasty-fonts-preview-ui-feed-copy">
                                    <strong data-role-preview="heading"><?php esc_html_e('Pricing module', 'tasty-fonts'); ?></strong>
                                    <span data-role-preview="body"><?php esc_html_e('Sathyvelu Kunashegaran staged the variant for tomorrow&rsquo;s review.', 'tasty-fonts'); ?></span>
                                </span>
                                <span class="tasty-fonts-preview-ui-feed-pill tasty-fonts-preview-ui-feed-pill--draft" data-role-preview="body"><?php esc_html_e('Draft', 'tasty-fonts'); ?></span>
                                <span class="tasty-fonts-preview-ui-feed-time" data-role-preview="body"><?php esc_html_e('48m', 'tasty-fonts'); ?></span>
                            </article>
                        </div>
                    </section>

                    <aside class="tasty-fonts-preview-ui-toast" role="status">
                        <span class="tasty-fonts-preview-ui-toast-mark" data-role-preview="heading" aria-hidden="true">&#10003;</span>
                        <div class="tasty-fonts-preview-ui-toast-copy">
                            <strong data-role-preview="heading"><?php esc_html_e('Roles published', 'tasty-fonts'); ?></strong>
                            <span data-role-preview="body"><?php esc_html_e('Heading and Body roles are live across the workspace.', 'tasty-fonts'); ?></span>
                        </div>
                        <span class="tasty-fonts-preview-ui-toast-action" data-role-preview="body" aria-hidden="true"><?php esc_html_e('View', 'tasty-fonts'); ?></span>
                    </aside>

                    <footer class="tasty-fonts-preview-ui-actions">
                        <span class="tasty-fonts-preview-ui-actions-meta" data-role-preview="body"><?php esc_html_e('Auto-saved &middot; 2 minutes ago', 'tasty-fonts'); ?></span>
                        <div class="tasty-fonts-preview-ui-actions-buttons">
                            <span class="button" aria-hidden="true"><?php esc_html_e('Save Draft', 'tasty-fonts'); ?></span>
                            <span class="button button-primary" aria-hidden="true"><?php esc_html_e('Publish', 'tasty-fonts'); ?></span>
                        </div>
                    </footer>
                </div>
                <?php
                return;
        }
    }

    /**
     * @param RoleSet $roles
     * @param FamilyLabelMap $familyLabels
     */
    public function previewRoleName(string $roleKey, array $roles, array $familyLabels = []): string
    {
        $familyName = trim($this->roleStringValue($roles, $roleKey));

        if ($familyName !== '') {
            return (string) ($familyLabels[$familyName] ?? $familyName);
        }

        return sprintf(
            __('Fallback only (%s)', 'tasty-fonts'),
            FontUtils::sanitizeFallback($this->roleFallbackValue($roles, $roleKey))
        );
    }

    /**
     * @param FamilyOptionList $availableFamilyOptions
     * @param RoleSet $previewRoles
     * @param RoleSet $draftRoles
     */
    public function renderPreviewRolePicker(
        string $roleKey,
        string $label,
        array $availableFamilyOptions,
        array $previewRoles,
        array $draftRoles,
        bool $allowFallbackOnly = false
    ): void {
        $selectedFamily = trim($this->roleStringValue($previewRoles, $roleKey));
        $draftFamily = trim($this->roleStringValue($draftRoles, $roleKey));
        $fallbackValue = $this->roleFallbackValue($previewRoles, $roleKey);
        $selectId = 'tasty-fonts-preview-' . sanitize_html_class($roleKey) . '-family';
        ?>
        <div class="tasty-fonts-preview-role-picker" data-preview-role-picker="<?php echo esc_attr($roleKey); ?>">
            <div class="tasty-fonts-stack-field tasty-fonts-preview-tray-field">
                <span class="tasty-fonts-field-label-row">
                    <label class="tasty-fonts-field-label-text" for="<?php echo esc_attr($selectId); ?>"><?php echo esc_html($label); ?></label>
                    <button
                        type="button"
                        class="tasty-fonts-badge tasty-fonts-badge--interactive tasty-fonts-badge--help tasty-fonts-help-trigger"
                        aria-label="<?php esc_attr_e('Explain role family delivery', 'tasty-fonts'); ?>"
                        <?php $this->renderPassiveHelpAttributes(__('Choose the delivery method in the Font Library. Role selectors use the family’s active delivery profile.', 'tasty-fonts')); ?>
                    >?</button>
                </span>
                <span class="tasty-fonts-select-field<?php echo $allowFallbackOnly ? ' tasty-fonts-select-field--clearable' : ''; ?>">
                    <select
                        id="<?php echo esc_attr($selectId); ?>"
                        data-preview-role-select="<?php echo esc_attr($roleKey); ?>"
                        data-preview-draft-family="<?php echo esc_attr($draftFamily); ?>"
                        data-preview-fallback="<?php echo esc_attr($fallbackValue); ?>"
                    >
                        <?php if ($allowFallbackOnly): ?>
                            <option value="" <?php selected($selectedFamily, ''); ?>><?php esc_html_e('Use Fallback Only', 'tasty-fonts'); ?></option>
                        <?php endif; ?>
                        <?php foreach ($availableFamilyOptions as $option): ?>
                            <?php $familyName = $option['value']; ?>
                            <?php $familyLabel = $option['label']; ?>
                            <option value="<?php echo esc_attr($familyName); ?>" <?php selected($selectedFamily, $familyName); ?>><?php echo esc_html($familyLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($allowFallbackOnly): ?>
                        <?php $this->renderClearSelectButton(sprintf(__('Clear %s', 'tasty-fonts'), $label)); ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="tasty-fonts-role-weight-editor tasty-fonts-preview-role-editor" data-preview-weight-editor="<?php echo esc_attr($roleKey); ?>" hidden>
                <label class="tasty-fonts-stack-field tasty-fonts-preview-tray-field tasty-fonts-role-weight-field">
                    <?php $this->renderFieldLabel(__('Role Weight', 'tasty-fonts')); ?>
                    <span class="tasty-fonts-select-field tasty-fonts-select-field--clearable">
                        <select data-preview-weight-select="<?php echo esc_attr($roleKey); ?>"></select>
                        <?php $this->renderClearSelectButton(sprintf(__('Clear %s weight', 'tasty-fonts'), $label), '', '', true); ?>
                    </span>
                </label>
            </div>
            <div class="tasty-fonts-role-axis-editor tasty-fonts-preview-role-editor" data-preview-axis-editor="<?php echo esc_attr($roleKey); ?>" hidden>
                <div class="tasty-fonts-stack-field tasty-fonts-preview-tray-field">
                    <span class="tasty-fonts-field-label" data-preview-axis-heading="<?php echo esc_attr($roleKey); ?>"><?php esc_html_e('Variable Axes', 'tasty-fonts'); ?></span>
                    <div class="tasty-fonts-role-axis-fields" data-preview-axis-fields="<?php echo esc_attr($roleKey); ?>"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param RoleSet $roles
     * @param FamilyLabelMap $familyLabels
     */
    public function renderSnippetPreviewScene(array $roles, bool $monospaceRoleEnabled, array $familyLabels = []): void
    {
        $snippet = $this->buildPreviewSnippetCss($roles, $monospaceRoleEnabled);
        $lineCount = substr_count($snippet, "\n") + 1;
        ?>
        <div class="tasty-fonts-preview-snippet-workspace">
            <section class="tasty-fonts-preview-snippet-brief" aria-label="<?php esc_attr_e('Preview snippet explanation', 'tasty-fonts'); ?>">
                <span class="tasty-fonts-preview-card-label" data-role-preview="body"><?php esc_html_e('Preview Snippet', 'tasty-fonts'); ?></span>
                <h3 class="tasty-fonts-preview-snippet-title" data-role-preview="heading"><?php esc_html_e('Copy the exact pairing you are previewing.', 'tasty-fonts'); ?></h3>
                <p class="tasty-fonts-preview-snippet-copy" data-role-preview="body"><?php esc_html_e('This CSS is generated from the current Preview Workspace selection, including draft changes that are not published yet. Use it when you want this same font combo in custom CSS, a child theme, or a builder field.', 'tasty-fonts'); ?></p>

                <dl class="tasty-fonts-preview-snippet-role-list">
                    <div class="tasty-fonts-preview-snippet-role">
                        <dt><?php esc_html_e('Heading', 'tasty-fonts'); ?></dt>
                        <dd data-role-preview="heading" data-role-preview-name="heading"><?php echo esc_html($this->previewRoleName('heading', $roles, $familyLabels)); ?></dd>
                    </div>
                    <div class="tasty-fonts-preview-snippet-role">
                        <dt><?php esc_html_e('Body', 'tasty-fonts'); ?></dt>
                        <dd data-role-preview="body" data-role-preview-name="body"><?php echo esc_html($this->previewRoleName('body', $roles, $familyLabels)); ?></dd>
                    </div>
                    <?php if ($monospaceRoleEnabled): ?>
                        <div class="tasty-fonts-preview-snippet-role">
                            <dt><?php esc_html_e('Monospace', 'tasty-fonts'); ?></dt>
                            <dd data-role-preview="monospace" data-role-preview-name="monospace"><?php echo esc_html($this->previewRoleName('monospace', $roles, $familyLabels)); ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

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
        <?php
    }

    /**
     * @param RoleSet $roles
     * @param FamilyLabelMap $familyLabels
     */
    public function renderCodePreviewScene(string $previewText, array $roles, bool $monospaceRoleEnabled, array $familyLabels = []): void
    {
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
                        <strong class="tasty-fonts-preview-code-meta-value" data-role-preview="monospace" data-role-preview-name="monospace"><?php echo esc_html($this->previewRoleName('monospace', $roles, $familyLabels)); ?></strong>
                    </div>
                    <div class="tasty-fonts-preview-code-meta-item">
                        <span class="tasty-fonts-preview-code-meta-label"><?php esc_html_e('Headings', 'tasty-fonts'); ?></span>
                        <strong class="tasty-fonts-preview-code-meta-value" data-role-preview="heading" data-role-preview-name="heading"><?php echo esc_html($this->previewRoleName('heading', $roles, $familyLabels)); ?></strong>
                    </div>
                    <div class="tasty-fonts-preview-code-meta-item">
                        <span class="tasty-fonts-preview-code-meta-label"><?php esc_html_e('Annotations', 'tasty-fonts'); ?></span>
                        <strong class="tasty-fonts-preview-code-meta-value" data-role-preview="body" data-role-preview-name="body"><?php echo esc_html($this->previewRoleName('body', $roles, $familyLabels)); ?></strong>
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
                                <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">heading</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-string">&quot;var(--font-heading)&quot;</span><span class="tasty-fonts-preview-token-punctuation">,</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">04</span>
                                <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">body</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-string">&quot;var(--font-body)&quot;</span><span class="tasty-fonts-preview-token-punctuation">,</span></span>
                            </div>
                            <div class="tasty-fonts-preview-code-line">
                                <span class="tasty-fonts-preview-code-line-number">05</span>
                                <span class="tasty-fonts-preview-code-line-content">  <span class="tasty-fonts-preview-token-property">code</span><span class="tasty-fonts-preview-token-punctuation">:</span> <span class="tasty-fonts-preview-token-string">&quot;var(--font-monospace)&quot;</span><span class="tasty-fonts-preview-token-punctuation">,</span></span>
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
        <?php
    }

    /**
     * @param array<int|string, mixed> $roles
     */
    private function roleStringValue(array $roles, string $key): string
    {
        $value = $roles[$key] ?? '';

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @param array<int|string, mixed> $roles
     */
    private function roleFallbackValue(array $roles, string $roleKey): string
    {
        $fallback = match ($roleKey) {
            'heading' => $this->roleStringValue($roles, 'heading_fallback'),
            'body' => $this->roleStringValue($roles, 'body_fallback'),
            default => $this->roleStringValue($roles, 'monospace_fallback'),
        };

        if ($fallback !== '') {
            return $fallback;
        }

        return $roleKey === 'monospace' ? 'monospace' : FontUtils::DEFAULT_ROLE_SANS_FALLBACK;
    }

    /**
     * @param RoleSet $roles
     */
    private function buildPreviewSnippetCss(array $roles, bool $includeMonospace): string
    {
        $lines = [':root {'];

        $this->appendRolePreviewSnippetVariables($lines, $roles, 'heading');
        $this->appendRolePreviewSnippetVariables($lines, $roles, 'body');

        if ($includeMonospace) {
            $this->appendRolePreviewSnippetVariables($lines, $roles, 'monospace');
        }

        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'body {';
        $lines[] = '  font-family: var(--font-body);';
        $lines[] = '  font-variation-settings: var(--font-body-settings);';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'h1, h2, h3, h4, h5, h6 {';
        $lines[] = '  font-family: var(--font-heading);';
        $lines[] = '  font-variation-settings: var(--font-heading-settings);';
        $lines[] = '}';

        if ($includeMonospace) {
            $lines[] = '';
            $lines[] = 'code, pre, kbd, samp {';
            $lines[] = '  font-family: var(--font-monospace);';
            $lines[] = '  font-variation-settings: var(--font-monospace-settings);';
            $lines[] = '}';
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $lines
     * @param RoleSet $roles
     */
    private function appendRolePreviewSnippetVariables(array &$lines, array $roles, string $roleKey): void
    {
        $family = trim($this->roleStringValue($roles, $roleKey));
        $fallback = $this->roleFallbackValue($roles, $roleKey);
        $stack = FontUtils::buildFontStack($family, $fallback);

        if ($family !== '') {
            $slug = FontUtils::slugify($family);
            $lines[] = sprintf('  --font-%s: %s;', $slug, $stack);
            $lines[] = sprintf('  --font-%s: var(--font-%s);', $roleKey, $slug);
        } else {
            $lines[] = sprintf('  --font-%s: %s;', $roleKey, $stack);
        }

        $lines[] = sprintf('  --font-%s-settings: normal;', $roleKey);
    }
}
