# Google Fonts

Import Google Fonts as self-hosted files or keep them on the Google CDN.

## Use This Page When

- you want to import a Google family into the plugin library
- you need to decide between self-hosted and CDN delivery
- you are troubleshooting Google API key or catalog access issues

## Steps

### 1. Save A Google API Key

Live Google search requires a valid Google Fonts API key saved in the plugin settings path used for Google access.

**How to get a Google Fonts API key (step by step):**

1. Go to [console.cloud.google.com](https://console.cloud.google.com).
2. Create a new project or select an existing one.
3. In the search bar, search for **Web Fonts Developer API** and enable it.
4. Go to **Credentials → Create Credentials → API Key**.
5. Copy the key.
6. In the plugin, open the add-font flow and select `Google Fonts`. Paste the key into the Google API key field and save.

> **Security tip:** restrict the API key to the Web Fonts API only in the Google Cloud Console to prevent misuse if the key is exposed.

If search is unavailable, open the Google key settings from the add-font workflow and validate the key first.

### 2. Search For A Family

Use the Google add-font flow to search for the family you want and review available variants. When Variable Font Support is enabled in `Settings → Output`, families that offer a variable version are marked with a **Variable** badge in the search results.

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

When Variable Font Support is enabled and the family has a variable version, the import also:

- stores the axis metadata (e.g., `wght` range) from the Google API response
- marks the delivery profile as variable so the Font Library badge and Deploy Fonts axis controls reflect the correct design space

When you choose **CDN** delivery, no files are downloaded. The plugin enqueues the Google-hosted stylesheet at runtime. The family still participates in role assignments and admin previews.

If the effective `font-display` setting resolves to `optional`, the live frontend runtime promotes the Google CDN stylesheet request to `swap`. This avoids a first-visit fallback render getting stuck on the fallback face when the remote stylesheet lands just after the browser's optional-font window.

The **Google Fonts API key** is required only for live catalog search in the add-font workflow. If you already imported a family via CDN or self-hosted delivery, the stored delivery profile remains valid even if the API key is removed later.

## Common Issues

**Search returns no results** — confirm a valid Google Fonts API key is saved in the plugin. The key needs the Fonts API enabled in the Google Cloud Console.

**Self-hosted import downloads no files** — the plugin fetches variant URLs from the Google API response. If the API key is missing or invalid at import time, the variant list may be empty. Re-run the import after confirming the key is valid.

**CDN delivery stops working on the frontend** — this usually means the family is published but not assigned to a live role, or the draft was not applied sitewide. Confirm the role assignment and apply sitewide if needed. If the family is live but still looks wrong, inspect the active delivery profile and per-family `font-display` override next.

**Runtime output looks stale after switching from CDN to self-hosted** — use `Advanced Tools -> Generated CSS` to confirm the stylesheet has been refreshed. If it has not, the plugin may need a delivery-mode or output-settings change to trigger a regeneration.

## Notes

- Self-hosted Google imports are stored under `wp-content/uploads/fonts/google/<family-slug>/`.
- CDN deliveries remain remote at runtime, but the family still participates in previews, selectors, and live role assignments.
- The plugin uses modern remote CSS handling and normalizes imported faces into its delivery profile model.

## Related Docs

- [Font Library](Font-Library)
- [Settings](Settings)
- [Imports And Deliveries](Troubleshooting-Imports-and-Deliveries)
- [Concepts](Concepts)
- [FAQ](FAQ)
