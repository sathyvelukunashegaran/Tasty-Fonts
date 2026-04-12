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

The Settings page is split into four tabs:

- `Output`: what CSS gets generated and how it is delivered
- `Integrations`: how the plugin cooperates with editor and builder ecosystems
- `Behavior`: plugin-level feature defaults and uninstall behavior
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

- `Minimal`: emit only `--font-heading` and `--font-body`
- `Variables only`: emit the variable layer without utility classes
- `Classes only`: emit the utility class layer without the larger variable surface
- `Custom`: open the detailed controls and manage each output feature directly

Use `Minimal` when your theme or builder only needs the core role variables and you want the plugin to keep the runtime output surface as small as possible.

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

#### Bricks Builder

When enabled on a site where Bricks is active, Tasty Fonts can expose published runtime families inside Bricks selectors and mirror matching Bricks typography choices into Gutenberg editor styles.

If Bricks is not active, the integration remains unavailable and the plugin preserves that undecided state so it can still auto-enable later when detection becomes possible.

#### Oxygen Builder

When enabled on a site where Oxygen is active, Tasty Fonts can expose published runtime families through the compatibility shim expected by Oxygen ecosystems and mirror matching Oxygen global font choices into Gutenberg editor styles.

Like Bricks, the plugin keeps unavailable detection states intact so later activation can still bootstrap a sensible default.

### 3. Use The Behavior Tab

The `Behavior` tab controls plugin-level features that are not primarily about generated CSS.

Key controls include:

- `Update Channel`
- `Enable Monospace Role`
- `Hide Onboarding Hints`
- `Delete uploaded fonts on uninstall`

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

### 4. Use The Developer Tab Carefully

The `Developer` tab is intended for maintenance, troubleshooting, and repeated testing workflows.

Available actions include:

- clearing plugin caches and regenerating assets
- resetting suppressed notices
- resetting plugin settings to defaults while preserving the library
- wiping the managed font library and rebuilding an empty storage scaffold
- resetting integration detection state

The destructive actions require explicit confirmation phrases. That is intentional and should stay that way.

### 5. Understand Autosave

The standard settings tabs autosave through the plugin REST API. That means:

- you do not need to submit a full page form for normal settings changes
- the UI updates the saved settings state directly
- some settings may still require a page reload to fully reflect the change in the admin UI

The main autosave path applies to standard settings toggles. The destructive Developer actions still use deliberate form submissions and confirmations.

### 6. Know What Changes Runtime Output

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

## Notes

- Per-family `font-display` overrides from the library take precedence over the global default for that family.
- If the monospace role is off, related output controls are disabled or hidden.
- Block Editor sync is intentionally cautious on local development environments because loopback TLS trust issues are common there.
- Variable Font Support is off by default. Enable it only when you are actively using variable font files so static-only installs stay clean.
- WordPress 6.5 or later is required. The minimum was raised from 6.1 to align with the Block Editor Font Library APIs used in this release.

## Related Docs

- [Deploy Fonts](Deploy-Fonts)
- [Font Library](Font-Library)
- [Local Development](Troubleshooting-Local-Development)
- [Generated CSS](Troubleshooting-Generated-CSS)
- [FAQ](FAQ)
