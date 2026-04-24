# Settings

Control the plugin’s output model, integrations, behavior defaults, and maintenance tools.

## Use This Page When

- you need to change CSS delivery or output format
- you need to configure integrations with Gutenberg, Automatic.css, Bricks, or Oxygen
- you want to enable or disable behavior features
- you need to understand what the autosaving settings panels affect
- you need to reset cached or detected plugin state during development or support work

## Recommended Starting Settings For New Users

If you just activated the plugin and are not sure where to begin, start with these defaults:

| Setting | Recommended value | Why |
|---|---|---|
| CSS Delivery | File (default) | Best performance — browser caches the file independently |
| Output Preset | Minimal | Outputs only `--font-heading` and `--font-body` — enough for most themes |
| font-display | optional (default for generated `@font-face`) | Good self-hosted default; live Google/Bunny CDN stylesheets promote `optional` to `swap` so the custom face still appears |
| Minify Generated CSS | On (default) | Smaller file size in production |
| Preload Primary Fonts | On (if using self-hosted WOFF2 above the fold) | Improves LCP |
| Block Editor Sync | Off on local, On on staging/production | Avoids loopback TLS failures on local environments |
| Monospace Role | Off (default) | Enable only if your site displays code content |

Once you have a working font deployment, revisit Settings and adjust from these defaults if needed.

---

## Settings Tabs At A Glance

The Settings page is split into five tabs:

- `Output`: what CSS gets generated and how it is delivered
- `Integrations`: how the plugin cooperates with editor and builder ecosystems
- `Behavior`: plugin-level feature defaults and uninstall behavior
- `Transfer`: portable export and import bundles for cross-site migrations
- `Developer`: maintenance and reset workflows intended for advanced users, testers, and support

## Steps

### 1. Use The Output Tab

The `Output` tab controls how the plugin generates and serves typography output.

Core controls include:

- `CSS Delivery`
- default `font-display`
- `Unicode Range Output`
- `Minify Generated CSS`
- `Preload Primary Heading and Body Fonts`
- `Remote Connection Hints`

Advanced output controls also cover:

- the `Minimal` output preset
- `Emit Role Font Weights`
- `Variable Font Support`
- utility class generation
- class sub-controls by role, alias, category, and family
- variable generation
- variable sub-controls such as role aliases, category aliases, and weight tokens

#### Output Preset

The quick preset selector is the fastest way to choose the overall output model:

- `Minimal`: emit only the core role variables by default
- `Variables only`: emit the variable layer without utility classes
- `Classes only`: emit the utility class layer without the larger variable surface
- `Custom`: open the detailed controls and manage each output feature directly

Use `Minimal` when your theme or builder only needs the core role variables and you want the plugin to keep the runtime output surface as small as possible. On Etch frontends, Tasty also adds a small live role bridge so the applied Heading and Body roles still map onto the rendered page.

#### Emit Role Font Weights

When enabled, the plugin adds `font-weight` declarations to generated `body` and heading usage rules.

Leave this off when your theme or builder already controls type weights. Turn it on when you want the plugin to define not just the family assignments but also the intended regular and bold usage defaults.

#### CSS Delivery

**File** (default): the plugin writes generated CSS to `wp-content/uploads/fonts/.generated/tasty-fonts.css` and enqueues it as an external stylesheet. This is the best option for production — the browser can cache the file independently.

**Inline**: generated CSS is injected directly into the page `<head>`. Use inline delivery when file write permissions are unavailable or when you need to debug output without a disk-write step. Inline delivery bypasses browser caching for the generated stylesheet.

#### Default `font-display`

Controls the `font-display` descriptor in generated `@font-face` rules.

- `optional` (default): the browser uses the font only if it loads within a very short window. Eliminates layout shift at the cost of potentially showing a fallback on the first visit. Best for performance-sensitive sites.
- `swap`: always shows a fallback until the font loads, then swaps. Good for content-heavy sites where the correct typeface matters more than avoiding a visible swap.
- `fallback`: similar to `swap` but with a shorter invisible period. A balanced middle ground.
- `block`: hides text until the font loads. Avoid for large or slow fonts.
- `auto`: defers to the browser default.

For live Google and Bunny CDN deliveries, the runtime planner promotes `optional` to `swap`. This keeps remote CDN families from staying on fallback fonts after the first paint while still letting self-hosted `@font-face` output use the saved default.

Per-family overrides from the library take precedence over this global default.

#### Unicode Range Output

Controls how `unicode-range` is emitted for generated `@font-face` output and matching editor font-face payloads.

- `Off` (default): omit `unicode-range` entirely unless you explicitly opt in
- `Keep Imported Ranges`: preserve the raw range stored on each face
- `Basic Latin`: force the plugin's compact Latin-focused preset for every emitted face
- `Latin Extended`: force a broader Latin preset that includes Latin Extended and additional accents
- `Custom`: emit the same custom comma-separated list for every emitted face

Custom values must be a comma-separated list of `U+XXXX`, `U+XXXX-YYYY`, or `U+XX?` tokens.

This control affects emitted output only. The plugin keeps the original face metadata in the library so advanced users can opt back into `Keep Imported Ranges` or a custom range at any time.

#### Minify Generated CSS

When enabled (default), the plugin writes compressed CSS without whitespace. Turn this off temporarily when you need to read the generated output by hand — for example, when debugging `@font-face` rule details in `Advanced Tools -> Generated CSS`.

#### Preload Primary Heading and Body Fonts

When enabled, the plugin emits `<link rel="preload" as="font" type="font/woff2">` tags for the WOFF2 files used by the live heading and body roles. This can improve Largest Contentful Paint (LCP) scores for above-the-fold text.

Only applies to self-hosted WOFF2 variants. CDN and Adobe deliveries use remote connection hints instead.

#### Remote Connection Hints

When enabled, the plugin emits `<link rel="preconnect">` tags for active Google, Bunny, and Adobe CDN origins. This tells the browser to open TCP/TLS connections to those origins early, reducing latency for the first CDN stylesheet request. Disable if all your active deliveries are self-hosted.

#### Variable Font Support

When enabled, the plugin activates variable font features across uploads, imports, and runtime delivery.

- **Off** (default): the plugin operates in static-only mode. No variable-font UI, axis controls, or `font-variation-settings` are emitted. Variable font files can still be uploaded and will work, but no axis metadata is stored or used.
- **On**: unlocks variable font features:
  - axis metadata (e.g., `wght`, `wdth`, `ital`) is stored on imported faces from Google, Bunny, and local uploads
  - the Upload Files builder shows a variable column and an axis editor per file
  - each role (Heading, Body, Monospace) gains per-role axis controls and a static weight override in the Deploy Fonts interface
  - the generated runtime CSS includes `font-variation-settings` where variable faces are active
  - editor font presets and Block Editor sync payloads carry variation metadata
  - the Font Library shows `Variable` family type badges and a variable filter

> **When to enable this:** turn it on only if you are using actual variable font files (fonts with a `wght` or other design axis). For static fonts (separate files per weight/style), leave it off — the static workflow is simpler and the output is identical.

> **Beginner tip:** if you are not sure whether your font is a variable font, check the filename. Variable fonts often include `VariableFont` or `VF` in the filename (e.g., `Inter-VariableFont_wght.woff2`). You can also check the Google Fonts or Bunny Fonts catalog — variable families are marked with a `Variable` badge in search results when variable font support is enabled.

### 2. Use The Integrations Tab

The `Integrations` tab controls how Tasty Fonts cooperates with other editing and builder systems.

Key controls include:

- `Block Editor Font Library Sync`
- `Automatic.css Font Role Sync`
- `Bricks Builder`
- `Oxygen Builder`

#### Block Editor Font Library Sync

When enabled, the plugin mirrors managed families into the core WordPress Block Editor Font Library so they appear in the site editor's typography controls. The sync depends on background loopback requests from the server back to itself.

On local development environments, TLS certificate trust issues commonly block these loopback requests. The plugin defaults this off on likely local environments. See [Local Development](Troubleshooting-Local-Development) for guidance.

#### Automatic.css Font Role Sync

When enabled, Tasty Fonts maps Automatic.css heading and text font-family settings to `var(--font-heading)` and `var(--font-body)`.

This keeps ACSS aligned with the plugin's live role model instead of splitting font ownership across two different systems.

When sitewide role delivery is active and Automatic.css is installed, Tasty also mirrors the live ACSS runtime stylesheet into Gutenberg and supported builder canvases for editor parity. That runtime mirror still happens when this sync toggle is off, but in that state Tasty does not rewrite Automatic.css typography settings. Turning the sync toggle on adds the direct ACSS font-family mapping layer on top of the mirrored runtime stylesheet.

#### Bricks Builder

When enabled on a site where Bricks is active, Tasty Fonts can expose published runtime families inside Bricks selectors and mirror matching Bricks typography choices into Gutenberg editor styles.

If Bricks is not active, the integration remains unavailable and the plugin preserves that undecided state so it can still auto-enable later when detection becomes possible.

#### Oxygen Builder

When enabled on a site where Oxygen is active, Tasty Fonts can expose published runtime families through the compatibility shim expected by Oxygen ecosystems and mirror matching Oxygen global font choices into Gutenberg editor styles.

Like Bricks, the plugin keeps unavailable detection states intact so later activation can still bootstrap a sensible default.

### 3. Use The Behavior Tab

The `Behavior` tab controls plugin-level features that are not primarily about generated CSS.

Key controls include:

- `Admin Access`
- `Update Channel`
- `Enable Monospace Role`
- `Show Activity Log`
- `Hide Onboarding Hints`
- `Delete uploaded fonts on uninstall`

#### Admin Access

By default, only WordPress administrators can open the Tasty Fonts admin pages and REST endpoints. The Admin Access panel lets you expand that access to selected non-administrator roles and specific users.

**How it works:**

- Toggle **Enable custom access** to open the role and user selectors.
- Under **Additional Roles**, check any non-administrator role whose members should have full Tasty Fonts access (for example, `Editor`).
- Under **Specific Users**, search and add individual users by name. These grants are site-local and are never exported in transfer bundles.
- Save changes. The access change takes effect immediately — no page reload required.

**Important notes:**

- Administrator access is always preserved. You cannot accidentally lock yourself out.
- User-level grants added on the source site are stripped when a site transfer bundle is imported on a destination site. Role-level grants are retained.
- Delegated users and roles can perform all Tasty Fonts tasks including applying sitewide roles, running developer maintenance actions, and exporting or importing transfer bundles.

> **Beginner tip:** if you are managing a client site where the client needs to change fonts but should not be a site administrator, add the client's WordPress user under Specific Users instead of granting them admin access to the whole site.

#### Update Channel

Choose which GitHub release rail the updater should follow:

- `Stable` only accepts final `X.Y.Z` releases
- `Beta` accepts stable releases plus `X.Y.Z-beta.N` prereleases
- `Nightly` accepts stable, beta, and stamped nightly builds from `main`

If the selected channel points to an older version than the one currently installed, the Behavior tab exposes a rollback reinstall button so you can switch channels immediately instead of waiting for the lower channel to catch up.

#### Enable Monospace Role

Turns on the third role slot (`Monospace`). When enabled, the role selector, output variables (`--font-monospace`), and related class/alias controls become available. Disable this if you do not need a managed monospace family.

#### Delete Uploaded Fonts On Uninstall

When enabled, uninstalling the plugin also removes plugin-managed font files from `wp-content/uploads/fonts/`. Disable this if you want to keep the files for use with other plugins or after reinstallation.

#### Show Activity Log

When enabled, the full diagnostics activity log is visible in Advanced Tools. When disabled, the activity log panel is hidden from the Advanced Tools page by default — but the plugin continues to record events in the background. Re-enable this setting to reveal the log whenever you need to review past activity.

> **Tip:** turning this off keeps Advanced Tools cleaner on production sites where you do not need a persistent log view. Events are still recorded so you can turn it back on and inspect history at any time.

### 4. Use The Developer Tab Carefully

The `Developer` tab is intended for maintenance, troubleshooting, and repeated testing workflows.

Available actions include:

- clearing plugin caches and regenerating assets
- resetting suppressed notices
- resetting plugin settings to defaults while preserving the library
- wiping the managed font library and rebuilding an empty storage scaffold
- resetting integration detection state

The destructive actions require explicit confirmation phrases. That is intentional and should stay that way.

### 5. Use The Transfer Tab

The `Transfer` tab lets you move your complete Tasty Fonts setup between WordPress sites using a single portable ZIP bundle — without manually re-importing fonts or reconfiguring settings on the destination.

> **Beginner tip:** the most common use cases are pushing a finished font setup from staging to production, handing off a client build, and quickly cloning your typography to a second site.

> **Prerequisite:** PHP's ZipArchive extension must be installed and enabled on both the source and destination servers. Most managed WordPress hosts include it by default. If the tab shows an unavailability warning, contact your host.

#### Export Card

The **Export Site Transfer Bundle** card generates a downloadable ZIP that contains:

- all managed font files from `wp-content/uploads/fonts/`
- a manifest (`tasty-fonts-export.json`) recording library data, settings, role assignments, and file checksums
- **does not include** the Google Fonts API key, WordPress user accounts, theme or plugin files, or the `.generated/tasty-fonts.css` file (which is regenerated on import)

To export:

1. Open the `Transfer` tab.
2. Confirm the export action is available (not disabled).
3. Click **Export Bundle**. Your browser downloads the ZIP.

#### Import Card

> ⚠️ **Warning:** importing a bundle **replaces** the current Tasty Fonts library, settings, and role assignments on the destination site. This cannot be undone from within the plugin. Take a database backup before importing onto a live production site.

The import flow is protected by a mandatory **dry-run validation step** before the destructive import can be armed. This two-phase approach lets the plugin verify the bundle before anything is changed.

**Phase 1 — Dry Run:**

1. Open the `Transfer` tab on the **destination site**.
2. In the **Import Site Transfer Bundle** card, click the file picker and choose the ZIP you exported.
3. (Optional) Paste a **Google Fonts API key** into the key field if this destination site needs Google catalog search.
4. Click **Dry Run Bundle**.
5. The plugin validates the bundle, checks file checksums, and surfaces any warnings or errors in the Transfer activity log — without making any changes to your site.
6. If the dry run passes, the **Import Bundle** button becomes available.

**Phase 2 — Destructive Import (after a successful dry run):**

1. Click **Import Bundle** and use the two-step arm/confirm button flow to confirm the destructive action.
2. The plugin extracts and verifies font files, restores library data, applies settings and role assignments, and rebuilds the generated CSS.

> **Why two phases?** The dry-run step catches problems like corrupted files, version mismatches, and oversized uploads before they touch your live database or font files. If the dry run reports any errors, fix them at the source and export a new bundle before retrying.

#### Why Google API Keys Are Excluded

Google Fonts API keys are never bundled because they are tied to a specific Google Cloud project on the source site. Including a key in a portable bundle would risk sharing source-site credentials and quota with the destination site owner. Since 1.12.0, keys are stored in a dedicated **encrypted** WordPress option (`tasty_fonts_google_api_key_data`) that is isolated from the main settings record and excluded from all exports.

To restore Google Fonts search access on the destination, supply a fresh key in the Import card's optional key field before importing, or add one later in `Settings → Output`.

> **Beginner tip:** if you are not planning to use Google Fonts search on the destination, you can skip the key entirely. Transferred fonts that were already imported will still load and display correctly.

See [Site Transfer](Site-Transfer) for the full step-by-step guide and troubleshooting reference.

### 6. Understand Autosave

The standard settings tabs autosave through the plugin REST API. That means:

- you do not need to submit a full page form for normal settings changes
- the UI updates the saved settings state directly
- some settings may still require a page reload to fully reflect the change in the admin UI

The main autosave path applies to standard settings toggles. The destructive Developer actions still use deliberate form submissions and confirmations.

### 7. Know What Changes Runtime Output

Output settings can affect:

- whether generated CSS is served from a file or inline
- what `font-display` is emitted for generated `@font-face` rules
- what `unicode-range` is emitted for generated `@font-face` rules and editor font-face payloads
- whether minified CSS is written
- whether the minimal preset suppresses the broader variable and class surface
- whether heading/body usage rules include explicit font-weight declarations
- whether runtime preloads and remote connection hints are emitted
- what variable and utility-class output is available

Integration settings can affect:

- whether WordPress editor font libraries are synced
- whether Automatic.css uses the plugin’s role variables
- whether Bricks and Oxygen see published runtime families
- whether Gutenberg editor styles mirror builder typography selections

Behavior settings can affect:

- which release rail the GitHub updater follows
- whether a rollback reinstall action is exposed for channel switches
- whether monospace-specific roles and output exist
- whether onboarding hints remain visible
- whether plugin-managed uploaded files are removed during uninstall

Developer actions can affect:

- whether cached transients and generated assets are rebuilt
- whether stored integration detection state is reset
- whether saved notices remain hidden
- whether plugin settings or the entire managed library are reset

Transfer actions can affect:

- the entire managed font library (replaced by the bundle's library data)
- all plugin settings (replaced by the bundle's settings)
- live and draft role assignments (replaced by the bundle's role data)
- managed font files on disk (replaced by the bundle's font files)
- the Google Fonts API key on the destination (if a fresh key was supplied during import)

## Notes

- Per-family `font-display` overrides from the library take precedence over the global default for that family.
- If the monospace role is off, related output controls are disabled or hidden.
- Block Editor sync is intentionally cautious on local development environments because loopback TLS trust issues are common there.
- Variable Font Support is off by default. Enable it only when you are actively using variable font files so static-only installs stay clean.
- WordPress 6.5 or later is required. The minimum was raised from 6.1 to align with the Block Editor Font Library APIs used in this release.
- The Transfer tab requires PHP's ZipArchive extension. If the tab shows an unavailability warning, contact your hosting provider to enable the `zip` PHP extension.
- Google Fonts API keys supplied during a bundle import are stored using the same encrypted option storage as keys saved manually in `Settings → Output`.

## Related Docs

- [Deploy Fonts](Deploy-Fonts)
- [Font Library](Font-Library)
- [Site Transfer](Site-Transfer)
- [Local Development](Troubleshooting-Local-Development)
- [Generated CSS](Troubleshooting-Generated-CSS)
- [Caching And Font Loading](Caching-And-Font-Loading)
- [GDPR And Font Privacy](GDPR)
- [FAQ](FAQ)
