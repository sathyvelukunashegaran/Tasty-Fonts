# Settings

Control the plugin’s output model and behavior defaults.

## Use This Page When

- you need to change CSS delivery or output format
- you want to enable or disable behavior features
- you need to understand what the autosaving settings panels affect

## Steps

### 1. Use The Output Tab

The `Output` tab controls how the plugin generates and serves typography output.

Core controls include:

- `CSS Delivery`
- default `font-display`
- `Minify Generated CSS`
- `Preload Primary Heading and Body Fonts`
- `Remote Connection Hints`

Advanced output controls also cover:

- utility class generation
- class sub-controls by role, alias, category, and family
- variable generation
- variable sub-controls such as role aliases, category aliases, and weight tokens

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

Per-family overrides from the library take precedence over this global default.

#### Minify Generated CSS

When enabled (default), the plugin writes compressed CSS without whitespace. Turn this off temporarily when you need to read the generated output by hand — for example, when debugging `@font-face` rule details in `Advanced Tools -> Generated CSS`.

#### Preload Primary Heading and Body Fonts

When enabled, the plugin emits `<link rel="preload" as="font" type="font/woff2">` tags for the WOFF2 files used by the live heading and body roles. This can improve Largest Contentful Paint (LCP) scores for above-the-fold text.

Only applies to self-hosted WOFF2 variants. CDN and Adobe deliveries use remote connection hints instead.

#### Remote Connection Hints

When enabled, the plugin emits `<link rel="preconnect">` tags for active Google, Bunny, and Adobe CDN origins. This tells the browser to open TCP/TLS connections to those origins early, reducing latency for the first CDN stylesheet request. Disable if all your active deliveries are self-hosted.

### 2. Use The Behavior Tab

The `Behavior` tab controls plugin-level features that are not primarily about generated CSS.

Key controls include:

- `Enable Block Editor Font Library Sync`
- `Enable Monospace Role`
- `Hide Onboarding Hints`
- `Delete uploaded fonts on uninstall`

#### Enable Block Editor Font Library Sync

When enabled, the plugin mirrors managed families into the core WordPress Block Editor Font Library so they appear in the site editor's typography controls. The sync depends on background loopback requests from the server back to itself.

On local development environments, TLS certificate trust issues commonly block these loopback requests. The plugin defaults this off on likely local environments. See [Local Development](troubleshooting/local-development.md) for guidance.

#### Enable Monospace Role

Turns on the third role slot (`Monospace`). When enabled, the role selector, output variables (`--font-monospace`), and related class/alias controls become available. Disable this if you do not need a managed monospace family.

#### Delete Uploaded Fonts On Uninstall

When enabled, uninstalling the plugin also removes plugin-managed font files from `wp-content/uploads/fonts/`. Disable this if you want to keep the files for use with other plugins or after reinstallation.

### 3. Understand Autosave

Both settings panels autosave through the plugin REST API. That means:

- you do not need to submit a full page form for normal settings changes
- the UI updates the saved settings state directly
- some settings may still require a page reload to fully reflect the change in the admin UI

### 4. Know What Changes Runtime Output

Output settings can affect:

- whether generated CSS is served from a file or inline
- what `font-display` is emitted for generated `@font-face` rules
- whether minified CSS is written
- whether runtime preloads and remote connection hints are emitted
- what variable and utility-class output is available

Behavior settings can affect:

- whether monospace-specific roles and output exist
- whether imported families are mirrored into the WordPress Block Editor Font Library
- whether onboarding hints remain visible
- whether plugin-managed uploaded files are removed during uninstall

## Notes

- Per-family `font-display` overrides from the library take precedence over the global default for that family.
- If the monospace role is off, related output controls are disabled or hidden.
- Block Editor sync is intentionally cautious on local development environments because loopback TLS trust issues are common there.

## Related Docs

- [Deploy Fonts](deploy-fonts.md)
- [Font Library](font-library.md)
- [Local Development](troubleshooting/local-development.md)
- [Generated CSS](troubleshooting/generated-css.md)
