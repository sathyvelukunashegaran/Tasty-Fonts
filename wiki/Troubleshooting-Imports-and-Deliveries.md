# Imports And Deliveries

Troubleshoot provider imports, runtime visibility, and delivery profile confusion.

## Use This Page When

- a family imports successfully but does not appear live
- a family is visible in the library but not on the frontend
- you are unsure which delivery profile or publish state is in effect

## Diagnostic Checklist

Work through this checklist in order. Most issues are resolved by step 3 or 4.

### 1. Confirm The Family Exists In The Library

Open `Font Library` and search for the family.

Check whether the family is present and whether it is marked:

- `In Use` — assigned to a live role; CSS is being served
- `Published` — runtime-visible; CSS is being served but not via a role
- `In Library Only` — **not served**; no CSS is generated for this family

`In Library Only` keeps the family stored but out of runtime delivery. If your family is stuck here, change its state to `Published`.

### 2. Confirm The Active Delivery Profile

If a family has multiple delivery profiles, the active delivery profile controls what runtime uses. Confirm that the profile you expect is actually selected.

Look for the **active** indicator on the correct profile. If the wrong profile is active, switch it.

### 3. Confirm The Role Assignment

Even a valid published family will not affect live output unless:

- it is assigned to the relevant draft role
- **and** that draft has been applied sitewide

Go to `Deploy Fonts`, confirm the family is assigned to the correct role (Heading or Body), and check that `Apply Sitewide` was used after the assignment.

> **Common mistake:** saving a draft and assuming the site updated. Draft saves do not change live output. Use `Apply Sitewide` to publish the change.

### 4. Confirm Provider-Specific Preconditions

| Provider | What to check |
|---|---|
| Google | Valid API key is saved in the plugin; the key has the Web Fonts API enabled |
| Bunny | Download URLs are reachable from the server (no firewall blocking Bunny.net) |
| Adobe | Web project ID is correct and the project is published (not in draft or paused) |
| Local | File format is supported (WOFF2, WOFF, TTF, OTF) and file is writable after upload |

### 5. Check The Activity Log

Go to `Advanced Tools` and review recent activity for any import errors, delivery failures, or sync issues. The activity log captures per-event details that are not visible in the main UI.

### 6. Inspect Generated CSS

Go to `Advanced Tools → Generated CSS` and confirm the runtime stylesheet contains:

- the correct `@font-face` rules for self-hosted families
- the correct `--font-heading` and `--font-body` variable values

If the CSS looks stale, go to `Settings → Developer` and use `Clear Cache` to force regeneration.

### 7. Check Runtime `font-display` Expectations

For self-hosted deliveries, the generated `@font-face` rules use your saved global or per-family `font-display` value directly.

For live Google and Bunny CDN deliveries, the runtime planner promotes `optional` to `swap`. If a CDN family still does not render as expected, inspect whether the family has a per-family `font-display` override such as `fallback` or `block`.

---

## Notes

- Families can stay in the library for later use without becoming live immediately.
- Remote CDN deliveries and self-hosted deliveries both work within the same delivery profile model, but they produce different runtime asset behavior.
- Google and Bunny CDN deliveries intentionally avoid live `display=optional` requests because that can leave first-visit renders stuck on fallback fonts.
- If the library state looks correct but runtime still looks stale, continue with the generated CSS checks.

## Related Docs

- [Font Library](Font-Library)
- [Generated CSS](Troubleshooting-Generated-CSS)
- [Site Transfer](Site-Transfer)
- [Google Fonts](Provider-Google-Fonts)
- [Bunny Fonts](Provider-Bunny-Fonts)
- [Adobe Fonts](Provider-Adobe-Fonts)
- [FAQ](FAQ)

---

## Site Transfer Issues

### The Transfer tab shows "ZipArchive is unavailable"

**Cause:** PHP's ZipArchive extension is not installed or enabled on this server. The plugin cannot create or validate ZIP bundles without it.

**Fix:** contact your hosting provider and ask them to enable the `zip` PHP extension (sometimes referred to as `php-zip`). Most managed WordPress hosts include it by default. After it is enabled, reload the Transfer tab — the export and import controls will become available.

### Import fails with a permissions error

**Cause:** the WordPress uploads directory is not writable by the web server process.

**Fix:** check file system permissions on `wp-content/uploads/fonts/`. The directory and its subdirectories need to be writable by the user your web server runs as (often `www-data` or `apache`). Contact your host or use a file manager or FTP client to fix permissions.

### Fonts are not showing after a successful import

**Cause:** the plugin rebuilds generated CSS automatically on import, but a page caching layer may be serving a stale stylesheet.

**Fix:**
1. Go to `Settings → Developer` and use **Clear Plugin Caches** to force the generated stylesheet to regenerate.
2. Purge any page-caching plugin (WP Rocket, W3 Total Cache, LiteSpeed Cache, etc.) and any CDN cache in front of WordPress.
3. Hard-refresh your browser (Ctrl+Shift+R or Cmd+Shift+R).

### Google Fonts search is broken after import

**Cause:** Google Fonts API keys are excluded from transfer bundles by design. They are tied to the source site's Google Cloud project and are never exported.

**Fix:** add a Google Fonts API key for the destination site.

- **Option A:** re-import the bundle and paste a key into the optional Google Fonts API Key field in the Import card before confirming.
- **Option B:** go to `Settings → Output` and save a key there after the import.

Already-imported families continue to display and serve correctly without a key. The key is only needed for live catalog search.