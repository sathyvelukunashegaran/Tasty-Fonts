# Deploy Fonts

Manage draft roles, preview them, and publish live typography.

## Use This Page When

- you are assigning heading and body fonts
- you want to compare draft output against the live site
- you need to understand `Save Draft` versus `Apply Sitewide`

## The Big Idea

Think of this page as a staging area for typography. You can try different font pairings, save them as drafts, and preview them across multiple content types — all without affecting what visitors see. When you are happy with a pairing, one click pushes it live.

This workflow protects you from accidentally serving a broken or mismatched pairing on production while you are still experimenting.

---

## Steps

### 1. Choose Draft Roles

Use the role controls to assign:

- a `Heading` family — appears on titles, headings, and display text
- a `Body` family — appears on paragraphs, descriptions, and reading content
- an optional `Monospace` family when the monospace role is enabled (enable it in `Settings → Behavior`)

You can also clear a role back to its fallback stack instead of forcing a family selection.

> **Beginner tip:** if you only want to change the heading font and leave the body unchanged, you can update just the Heading role and apply sitewide. Each role is independent.

### 1a. Set Per-Role Variable Font Controls (Variable Fonts Only)

> **This section applies only when Variable Font Support is enabled in `Settings → Output`.**

When a role is assigned to a variable font family, the role controls expand to show:

- **Axis controls**: pin specific axis values for this role. For example, set the heading role to `wght: 600` while the body role stays at `wght: 400`.
- **Static weight override**: when you want the role to use a fixed weight rather than the full variable range, enter a weight value here. This generates a `font-weight` declaration in the role CSS rules rather than relying on the variable `wght` axis.

If a role is assigned to a static (non-variable) font, these controls are hidden. Axis controls only appear for families that have variable delivery profiles with known axis metadata.

### 2. Save Draft While Experimenting

`Save Draft` stores the current role pairing without changing live runtime output.

Use this when:

- you are comparing multiple families
- you want to pause before updating the site
- you want preview content to follow the current working draft

Your draft is safe until you explicitly apply it or overwrite it with a new one.

### 3. Use The Preview Workspace

The preview workspace is meant to answer "Does this pairing work everywhere?" before you publish it.

Use it to compare:

- draft roles versus live sitewide roles
- heading/body balance across multiple content scenes
- monospace readability in code-focused previews

The five preview scenes are:
- **Editorial** — headline-heavy content with subheadings and body copy
- **Card** — short-text elements like tiles or teasers
- **Reading** — long-form prose layout
- **Interface** — labels, buttons, and UI text
- **Code** — code blocks and `pre` elements (most useful when the monospace role is enabled)

> **Note:** previews always force `font-display: swap` for safety, even when your live output uses a different value. This ensures you see the font rather than a blank period during testing.

### 4. Apply Sitewide

`Apply Sitewide` promotes the current draft roles to live output. After that, the plugin serves the updated role CSS on the frontend, in Gutenberg, and in Etch.

> **What applies sitewide actually does:** the plugin regenerates `tasty-fonts.css` with the new `--font-heading` and `--font-body` variable values, updates any associated Gutenberg typography presets, and notifies the Etch canvas of the change. Visitors see the updated fonts on their next page load.

### 5. Review Snippet Panels

The Deploy Fonts page exposes code and snippet panels for:

- **Role usage snippets**: ready-to-paste CSS or PHP examples showing how to reference the current heading/body/monospace assignments. Use these when integrating the plugin's output with custom templates or theme stylesheets.
- **Role variables**: the CSS custom property declarations emitted by the plugin, such as `--font-heading`, `--font-body`, and `--font-monospace`. Copy these to verify the correct variable names before referencing them in your own CSS.
- **Role stack snippets**: the full resolved font-family stack for each role, including the configured fallback sequence. Use these when you need the exact stack string for a custom `font-family` declaration outside the plugin's generated output.
- **Class output**: when utility class generation is enabled in `Settings → Output`, this panel shows the generated class names for role, alias, category, and family selectors. Use these to apply typography roles directly in HTML markup without writing additional CSS.

---

## Notes

- The live site reflects the applied roles, not just the latest saved draft.
- If the monospace role is disabled, the UI removes the third role path and related output.
- Role variables such as `--font-heading`, `--font-body`, and `--font-monospace` depend on the current output settings and saved role state.
- Per-role variable font axis controls only appear when Variable Font Support is enabled and the assigned family has a variable delivery profile.

## Related Docs

- [Getting Started](getting-started.md)
- [Settings](settings.md)
- [Font Library](font-library.md)
- [Generated CSS](troubleshooting/generated-css.md)
- [FAQ](faq.md)
