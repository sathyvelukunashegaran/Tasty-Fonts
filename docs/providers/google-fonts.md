# Google Fonts

Import Google Fonts as self-hosted files or keep them on the Google CDN.

## Use This Page When

- you want to import a Google family into the plugin library
- you need to decide between self-hosted and CDN delivery
- you are troubleshooting Google API key or catalog access issues

## Steps

### 1. Save A Google API Key

Live Google search requires a valid Google Fonts API key saved in the plugin settings path used for Google access.

If search is unavailable, open the Google key settings from the add-font workflow and validate the key first.

### 2. Search For A Family

Use the Google add-font flow to search for the family you want and review available variants.

### 3. Choose A Delivery Mode

Google imports support two delivery models:

- `Self-hosted`: download supported files into the WordPress uploads directory
- `CDN`: keep runtime delivery on Google’s stylesheet infrastructure

### 4. Import And Review

After import:

- confirm the family appears in the `Font Library`
- review its delivery profile
- choose whether it should be runtime-visible
- assign it to a role when ready

## Variants and File Details

When you choose **self-hosted** delivery, the plugin fetches the remote CSS from the Google Fonts API using a modern browser user-agent (so the API returns WOFF2-first responses). It then:

1. Parses the returned `@font-face` rules to identify individual variant URLs.
2. Downloads each WOFF2 file into `wp-content/uploads/fonts/google/<family-slug>/`.
3. Rewrites the `@font-face` rules to point to the local paths.

When you choose **CDN** delivery, no files are downloaded. The plugin enqueues the Google-hosted stylesheet at runtime. The family still participates in role assignments and admin previews.

The **Google Fonts API key** is required only for live catalog search in the add-font workflow. If you already imported a family via CDN or self-hosted delivery, the stored delivery profile remains valid even if the API key is removed later.

## Common Issues

**Search returns no results** — confirm a valid Google Fonts API key is saved in the plugin. The key needs the Fonts API enabled in the Google Cloud Console.

**Self-hosted import downloads no files** — the plugin fetches variant URLs from the Google API response. If the API key is missing or invalid at import time, the variant list may be empty. Re-run the import after confirming the key is valid.

**CDN delivery stops working on the frontend** — this usually means the family is published but not assigned to a live role, or the draft was not applied sitewide. Confirm the role assignment and apply sitewide if needed.

**Runtime output looks stale after switching from CDN to self-hosted** — use `Advanced Tools -> Generated CSS` to confirm the stylesheet has been refreshed. If it has not, the plugin may need a delivery-mode or output-settings change to trigger a regeneration.

## Notes

- Self-hosted Google imports are stored under `wp-content/uploads/fonts/google/<family-slug>/`.
- CDN deliveries remain remote at runtime, but the family still participates in previews, selectors, and live role assignments.
- The plugin uses modern remote CSS handling and normalizes imported faces into its delivery profile model.

## Related Docs

- [Font Library](../font-library.md)
- [Settings](../settings.md)
- [Imports And Deliveries](../troubleshooting/imports-and-deliveries.md)
- [Concepts](../concepts.md)
