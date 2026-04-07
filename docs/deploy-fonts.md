# Deploy Fonts

Manage draft roles, preview them, and publish live typography.

## Use This Page When

- you are assigning heading and body fonts
- you want to compare draft output against the live site
- you need to understand `Save Draft` versus `Apply Sitewide`

## Steps

### 1. Choose Draft Roles

Use the role controls to assign:

- a `Heading` family
- a `Body` family
- an optional `Monospace` family when the monospace role is enabled

You can also clear a role back to its fallback stack instead of forcing a family selection.

### 2. Save Draft While Experimenting

`Save Draft` stores the current role pairing without changing live runtime output.

Use this when:

- you are comparing multiple families
- you want to pause before updating the site
- you want preview content to follow the current working draft

### 3. Use The Preview Workspace

The preview workspace is meant to answer “Does this pairing work everywhere?” before you publish it.

Use it to compare:

- draft roles versus live sitewide roles
- heading/body balance across multiple content scenes
- monospace readability in code-focused previews

### 4. Apply Sitewide

`Apply Sitewide` promotes the current draft roles to live output. After that, the plugin serves the updated role CSS on the frontend, in Gutenberg, and in Etch.

### 5. Review Snippet Panels

The Deploy Fonts page exposes code and snippet panels for:

- **Role usage snippets**: ready-to-paste CSS or PHP examples showing how to reference the current heading/body/monospace assignments. Use these when integrating the plugin's output with custom templates or theme stylesheets.
- **Role variables**: the CSS custom property declarations emitted by the plugin, such as `--font-heading`, `--font-body`, and `--font-monospace`. Copy these to verify the correct variable names before referencing them in your own CSS.
- **Role stack snippets**: the full resolved font-family stack for each role, including the configured fallback sequence. Use these when you need the exact stack string for a custom `font-family` declaration outside the plugin's generated output.
- **Class output**: when utility class generation is enabled in `Settings -> Output`, this panel shows the generated class names for role, alias, category, and family selectors. Use these to apply typography roles directly in HTML markup without writing additional CSS.

## Notes

- The live site reflects the applied roles, not just the latest saved draft.
- If the monospace role is disabled, the UI removes the third role path and related output.
- Role variables such as `--font-heading`, `--font-body`, and `--font-monospace` depend on the current output settings and saved role state.

## Related Docs

- [Getting Started](getting-started.md)
- [Settings](settings.md)
- [Font Library](font-library.md)
- [Generated CSS](troubleshooting/generated-css.md)
