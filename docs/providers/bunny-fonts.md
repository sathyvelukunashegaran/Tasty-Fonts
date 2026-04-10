# Bunny Fonts

Import Bunny Fonts as self-hosted files or keep them on the Bunny CDN.

Bunny Fonts mirrors the Google Fonts catalog and is a drop-in GDPR-friendly alternative. No API key is required. Traffic is routed through Bunny.net's European infrastructure instead of Google's servers.

## Use This Page When

- you want Bunny-hosted families in the plugin library
- you need to choose between self-hosted and CDN delivery
- you are concerned about GDPR and want to avoid Google CDN
- you are troubleshooting Bunny import behavior

## Steps

### 1. Search Or Select A Family

Use the Bunny add-font workflow to search the Bunny catalog and inspect the family details the plugin exposes. When Variable Font Support is enabled in `Settings → Output`, variable families are marked with a **Variable** badge in search results.

> **Note on Bunny variable fonts and self-hosted delivery:** Bunny's catalog includes variable fonts, but Bunny's download API currently provides static files for self-hosted imports even when the family has a variable version. For variable font delivery from Bunny, use CDN mode — Bunny's CDN stylesheet serves the variable font files directly. When self-hosted is selected for a variable family, the plugin stores the axis metadata from the catalog for reference, but the downloaded files will be static weight variants.

### 2. Choose A Delivery Mode

Bunny imports support:

- `Self-hosted`: download the imported files into the WordPress uploads directory
- `CDN`: keep runtime delivery on Bunny’s stylesheet infrastructure

### 3. Import And Review

After import:

- confirm the family appears in the `Font Library`
- review its variants and active delivery profile
- decide whether it should stay `Published` or `In Library Only`
- assign it to draft roles when ready

## Variants and File Details

Bunny Fonts does not require an API key. The plugin reads the Bunny catalog directly.

When you choose **self-hosted** delivery, the plugin:

1. Validates each variant download URL before writing anything.
2. Downloads WOFF2 files into `wp-content/uploads/fonts/bunny/<family-slug>/`.
3. Generates local `@font-face` rules pointing to the downloaded files.

When you choose **CDN** delivery, the plugin enqueues the Bunny-hosted stylesheet at runtime. No files are downloaded.

If the effective `font-display` setting resolves to `optional`, the live frontend runtime promotes the Bunny CDN stylesheet request to `swap`. This avoids a first-visit fallback render getting stuck on the fallback face when Bunny responds just after the browser's optional-font window.

Bunny Fonts is a GDPR-friendly alternative to the Google CDN. If you want CDN delivery without routing through Google infrastructure, Bunny CDN is a straightforward substitute for many of the same families.

## Common Issues

**Family does not appear in the Bunny catalog search** — the Bunny catalog is fetched directly from the Bunny API. If the catalog is unavailable, the search flow will return no results. Try again after a short delay.

**Self-hosted import fails silently** — the plugin validates each download URL before writing files. If a URL fails validation (for example, due to a temporary Bunny outage), the file is not written. Check the activity log for import entries and re-run the import when the provider is reachable.

**CDN delivery shows no output on the frontend** — confirm the family is assigned to a live role and that the draft has been applied sitewide. If the role is live but the page still looks wrong, inspect the active delivery profile and per-family `font-display` override next.

**Switching from CDN to self-hosted does not refresh the generated CSS** — use `Advanced Tools -> Generated CSS` to confirm the stylesheet reflects the new delivery mode. If needed, trigger a settings save to force a regeneration.

## Notes

- Self-hosted Bunny imports are stored under `wp-content/uploads/fonts/bunny/<family-slug>/`.
- CDN deliveries stay remote at runtime but still participate in previews and role selection.
- The plugin validates Bunny download URLs before writing self-hosted files.

## Related Docs

- [Font Library](../font-library.md)
- [Generated CSS](../troubleshooting/generated-css.md)
- [Imports And Deliveries](../troubleshooting/imports-and-deliveries.md)
- [Concepts](../concepts.md)
- [FAQ](../faq.md)
