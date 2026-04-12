# Generated CSS

Understand where generated CSS lives, how it is served, and what to check when runtime output looks stale.

## Use This Page When

- the frontend does not reflect recent font changes
- you need to confirm whether the plugin is serving file-based CSS or inline CSS
- you want to verify the generated stylesheet path and current state

## Quick Checks

Before running through the full steps, try these:

1. **Hard-refresh the browser** (Ctrl+Shift+R / Cmd+Shift+R) to bypass browser cache.
2. **Confirm you applied sitewide** — `Save Draft` does not update the generated CSS. Only `Apply Sitewide` does.
3. **Purge any page-caching plugins or CDN caches** (WP Rocket, W3 Total Cache, Cloudflare, etc.) after applying sitewide.

---

## Steps

### 1. Check The Delivery Mode

Open `Settings → Output` and review `CSS Delivery`.

The plugin can serve runtime output as:

- **File** (default) — writes generated CSS to `wp-content/uploads/fonts/.generated/tasty-fonts.css` and enqueues it as an external stylesheet. The browser caches this file independently.
- **Inline** — injects CSS directly into the page `<head>`. No disk file is written. Changes take effect on the next page load without a cache purge for the stylesheet itself.

### 2. Check The Generated File Location

When file delivery is available, the canonical generated stylesheet lives at:

```
wp-content/uploads/fonts/.generated/tasty-fonts.css
```

> **Note:** the `.generated` directory name starts with a dot. Some FTP clients hide dot-directories by default. Enable "show hidden files" in your FTP client to see it.

If the file does not exist, it means either:
- the plugin has not yet generated it (no font has been applied sitewide yet), or
- file-write permissions prevent the plugin from writing to `wp-content/uploads/fonts/`.

Check that the `wp-content/uploads/fonts/` directory is writable by the web server process (typically `www-data` on Linux servers).

### 3. Inspect The Generated CSS Panel

Open `Advanced Tools → Generated CSS` and compare the current output against what you expect from the library and role settings.

Look for:
- correct `@font-face` rules for self-hosted families
- correct `--font-heading` and `--font-body` variable values in `:root {}`
- the file having a recent `last modified` timestamp

### 4. Check System Details

Use `Advanced Tools → System Details` to confirm:

- **Request URL**: the URL the browser uses to load the generated file
- **File size**: a file with zero bytes indicates a write issue
- **Last modified**: should be close to when you last applied sitewide
- **Delivery mode**: confirms whether file or inline delivery is active

### 5. Force Regeneration

If the generated CSS looks stale or missing:

1. Go to `Settings → Developer`.
2. Use **Clear Cache** to delete the cached stylesheet and transients.
3. Then apply sitewide again (or save any Output setting) to trigger a fresh generation.

---

## Notes

- The plugin can recognize and migrate a current legacy generated stylesheet from `wp-content/uploads/fonts/tasty-fonts.css` into the canonical `.generated` location.
- Inline delivery is the fallback path when file delivery is disabled or unavailable.
- Output-affecting settings such as `font-display`, unicode-range output, minification, variable generation, and utility class generation all change what appears in the generated runtime CSS.
- If the generated stylesheet shows an unexpected `unicode-range`, check `Settings -> Output -> Unicode Range Output` before assuming the imported face metadata is wrong.

## Related Docs

- [Advanced Tools](Advanced-Tools)
- [Settings](Settings)
- [Imports And Deliveries](Troubleshooting-Imports-and-Deliveries)
- [FAQ](FAQ)
