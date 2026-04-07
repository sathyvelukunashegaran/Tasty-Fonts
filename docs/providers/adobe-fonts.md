# Adobe Fonts

Use an Adobe Fonts web project as a hosted family source inside the same role and preview workflow.

## Use This Page When

- you already manage fonts through Adobe Fonts
- you want Adobe families available in selectors and previews without self-hosting them
- you need to understand the Adobe-specific delivery model

## Steps

### 1. Add A Web Project ID

Use the Adobe add-font workflow to save the project ID from your Adobe Fonts web project.

### 2. Sync Detected Families

After validation, the plugin reads the project stylesheet and exposes the detected families in the shared library and selector workflow.

### 3. Use Adobe Families In Roles

Once synced, Adobe families can be:

- previewed in the Deploy Fonts workspace
- selected for draft roles
- applied sitewide like other sources

## Variants and File Details

Adobe Fonts delivers entirely through Adobe's own hosting infrastructure. The plugin never downloads font files locally.

When you save a valid web project ID, the plugin:

1. Fetches the project stylesheet from `use.typekit.net`.
2. Parses the stylesheet to detect the families and variants the project exposes.
3. Adds detected families to the shared library so they appear in role selectors and admin previews.

The runtime delivery of Adobe families works by enqueuing the Adobe project stylesheet (for example, `https://use.typekit.net/<project-id>.css`) on the frontend, Gutenberg, and the Etch canvas. Adobe generates this stylesheet from your project definition — the plugin does not control what variants it contains.

To change which variants are available, update your Adobe Fonts web project in the Adobe dashboard, then use the **Re-sync** action in the plugin to refresh the detected family list.

## Common Issues

**Project ID saves but no families are detected** — confirm the project ID matches an active, published Adobe Fonts web project. Draft or paused projects do not expose a usable stylesheet. Verify the project is published in your Adobe account before re-syncing.

**Families disappear after a re-sync** — this happens when the Adobe project no longer includes those families (for example, after you edited the project in Adobe's dashboard). The plugin re-reads the stylesheet on each sync and only exposes what the current project contains.

**Adobe stylesheet does not load on the frontend** — confirm the family is assigned to a live role and that the draft has been applied sitewide. Also confirm the project ID stored in the plugin still matches the intended project.

**Re-sync returns an error** — the plugin fetches the Adobe stylesheet over HTTPS. On local development environments, TLS trust issues can block this request. If the request fails, check the activity log for the specific error and confirm the project ID is correct.

## Notes

- Adobe remains hosted remotely. The plugin does not download Adobe font files into local storage.
- Adobe families still participate in runtime stylesheet planning, Gutenberg presets, and Etch delivery where appropriate.
- If the project ID is invalid or inaccessible, the plugin will not expose the family list.

## Related Docs

- [Getting Started](../getting-started.md)
- [Font Library](../font-library.md)
- [Imports And Deliveries](../troubleshooting/imports-and-deliveries.md)
- [Concepts](../concepts.md)
