# Site Transfer

Move your complete Tasty Fonts setup — managed font files, library data, settings, and live role assignments — between WordPress sites using a single portable ZIP bundle.

> **Beginner tip:** Site Transfer is the fastest way to replicate your typography setup to a staging site, hand off a finished project to a client, or restore a setup after a site rebuild. You do not need to manually re-import fonts or reconfigure settings on the destination.

## Use This Page When

- you are pushing a finished font setup from staging to production
- you are handing off a completed site build to a client who runs their own WordPress install
- you want to clone your typography configuration to a second site
- you need a lightweight backup of your Tasty Fonts configuration
- you are rebuilding a site and want to restore your previous font setup quickly

## What Gets Transferred

A Site Transfer bundle contains everything Tasty Fonts manages:

| What | Notes |
|---|---|
| Managed font files | All self-hosted WOFF2/WOFF/TTF/OTF files stored in `wp-content/uploads/fonts/` |
| Font library data | All families, delivery profiles, variant metadata, and per-family settings |
| Plugin settings | Output, Integrations, and Behavior tab settings |
| Live role assignments | The applied sitewide Heading, Body, and Monospace role selections |
| Draft role state | Your saved draft role selections |

## What Does NOT Get Transferred

| What | Why |
|---|---|
| Google Fonts API key | Tied to the source site's Google Cloud project; excluded to prevent credential sharing and quota exposure |
| WordPress user accounts | Outside the scope of the plugin |
| Theme and plugin files | Only Tasty Fonts–managed data is included |
| Non-font media files | Only font files under `wp-content/uploads/fonts/` are bundled |
| Generated CSS file | The `.generated/tasty-fonts.css` stylesheet is regenerated automatically on import; it is not bundled |

---

## Prerequisites

Before you begin, confirm all of the following:

1. **PHP's ZipArchive extension is installed and enabled** on both the source and destination servers. Most managed WordPress hosts include it by default. If the Transfer tab shows a warning about ZipArchive being unavailable, contact your host.
2. **Both sites are running Tasty Custom Fonts 1.12.0-beta.2 or later.** Bundle schema compatibility is not guaranteed across major schema versions.
3. **You have admin-level access** on both the source site (to export) and the destination site (to import).
4. **The destination site's `wp-content/uploads/fonts/` directory is writable** by the web server process.

---

## Step 1 — Export From the Source Site

1. Go to `Tasty Fonts → Settings` on the **source site**.
2. Click the **Transfer** tab.
3. In the **Export Site Transfer Bundle** card, confirm the export action is available (not greyed out). If it is greyed out, a ZipArchive error message will explain why.
4. Click **Export Bundle**.
5. Your browser downloads a ZIP file named something like `tasty-fonts-transfer-1.12.0-beta.2-20260412-221254.zip`.

> **What's in the ZIP:** the archive contains a manifest file (`tasty-fonts-export.json`) plus your managed font files under a `fonts/` subdirectory. The manifest records your library state, settings, roles, and a checksum for every included file.

Keep this ZIP somewhere safe — it is your portable bundle.

---

## Step 2 — Import On the Destination Site

> ⚠️ **Warning:** importing a bundle **replaces** the current Tasty Fonts library, settings, and role assignments on the destination site. This cannot be undone from within the plugin. Take a database backup before importing on a live production site.

1. Go to `Tasty Fonts → Settings` on the **destination site**.
2. Click the **Transfer** tab.
3. In the **Import Site Transfer Bundle** card:
   - Click **Choose File** and select the ZIP you exported.
   - (Optional) If this destination site needs Google Fonts search access, paste a Google Fonts API key into the **Google Fonts API Key** field. See [Why Google API Keys Are Excluded](#why-google-fonts-api-keys-are-excluded) below.
4. Click **Import Bundle**.
5. When the destructive confirmation appears, use the two-step confirm button flow to arm the action and then click again to confirm. No typed confirmation phrase is required.
6. The plugin validates the bundle, extracts and verifies font files, restores library data, applies settings and role assignments, and then rebuilds the generated CSS.

> **After a successful import:** you will see a success notice. The generated runtime stylesheet is rebuilt automatically. Your Tasty Fonts setup on the destination site now matches the source.

---

## Why Google Fonts API Keys Are Excluded

Google Fonts API keys are intentionally never included in transfer bundles, for two reasons:

1. **A key is tied to a Google Cloud project on the source site.** Using the source site's key on a destination site puts both sites on the same quota and could expose the source site's credentials to the destination site owner.
2. **API keys are stored encrypted** in a dedicated WordPress option (`tasty_fonts_google_api_key_data`) that is isolated from the main settings record. They are never serialized into portable bundles.

**What this means for Google search access on the destination:**

- Families that were already imported and are included in the bundle **continue to work** — no key is needed to serve an already-imported self-hosted or CDN family.
- The Google Fonts catalog **search feature** on the destination site requires its own API key.
- You can supply a key during import (optional field in the Import card) or add it later via `Settings → Output → Google Fonts API Key`.

> **Beginner tip:** if you are not planning to use Google Fonts search on the destination site, you can skip the key entirely. Your transferred fonts will still load and display correctly.

---

## After a Successful Import

The plugin performs these actions automatically after a successful import:

- Extracts managed font files to `wp-content/uploads/fonts/` on the destination server
- Replaces the font library with the imported library data
- Applies imported settings (Output, Integrations, Behavior)
- Applies imported role assignments as the live sitewide roles
- Rebuilds the generated runtime stylesheet (`wp-content/uploads/fonts/.generated/tasty-fonts.css`)
- Attempts a Block Editor Font Library sync if that integration is enabled
- Records a transfer import event in the activity log (`Advanced Tools → Activity`)

---

## Troubleshooting

### The Transfer tab shows "ZipArchive is unavailable"

PHP's ZipArchive extension is not installed or not enabled on this server. You cannot create or import bundles without it.

**Fix:** contact your hosting provider and ask them to enable the `zip` PHP extension (sometimes called `php-zip`). Most managed WordPress hosts (WP Engine, Kinsta, Flywheel, etc.) include it by default.

### The Import button is disabled or the card shows an error

If the Transfer tab shows an unavailability message on the Import card, the same ZipArchive requirement applies. Check the message text for details.

### Import fails or times out

Large bundles (many self-hosted font files) can take longer to validate and extract. If the import times out:

1. Check your host's `max_execution_time` PHP setting (30 seconds may not be enough for large bundles). Increase it temporarily if possible, or ask your host.
2. Confirm `wp-content/uploads/fonts/` is writable.
3. Review the activity log in `Advanced Tools` for partial import error messages.

### Fonts are not showing after a successful import

The plugin rebuilds generated CSS on import, but a caching layer may be serving a stale stylesheet.

1. Go to `Settings → Developer` and use **Clear Plugin Caches** to force a regeneration.
2. Purge any page-caching plugin (WP Rocket, W3 Total Cache, etc.) and any CDN cache in front of WordPress.
3. Hard-refresh your browser (Ctrl+Shift+R or Cmd+Shift+R).

### The generated CSS file was not in my bundle

This is expected. The `.generated/tasty-fonts.css` file is excluded by design because it is always regenerated from library data on import. If the stylesheet is missing on the destination, trigger a regeneration via `Settings → Developer → Clear Plugin Caches` or save any Output setting.

### Google Fonts search is not working after import

API keys are excluded from bundles by design. To restore Google search access:

- Option A: paste a key into the Google Fonts API Key field in the Import card before importing.
- Option B: go to `Settings → Output` after import and save a Google Fonts API key there.

See [Google Fonts](Provider-Google-Fonts) for how to create an API key.

---

## Related Docs

- [Settings](Settings) — full reference for the Transfer tab and all other settings
- [Getting Started](Getting-Started) — first-run walkthrough including the Settings overview
- [Troubleshooting: Imports and Deliveries](Troubleshooting-Imports-and-Deliveries) — provider import and delivery issues
- [Google Fonts](Provider-Google-Fonts) — how to create and use a Google Fonts API key
- [Advanced Tools](Advanced-Tools) — activity log, generated CSS inspection, and system details
- [Glossary](Glossary) — definitions for Site Transfer, bundle, and related terms
