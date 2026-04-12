# Concepts

Understand the three core ideas that underpin everything Tasty Custom Fonts does before diving into the task guides.

## Use This Page When

- you are new to the plugin and want a mental model before reading the task guides
- you want to understand why draft and live roles are separate
- you need to understand how the plugin generates and serves CSS
- you are choosing between font providers

---

## What This Plugin Actually Does

Tasty Custom Fonts is a typography management layer for WordPress. It lets you:

1. **Collect font families** from multiple sources (your own files, Google Fonts, Bunny Fonts, or Adobe Fonts) into a single managed library.
2. **Assign families to roles** — named slots like Heading, Body, and Monospace — that your theme or page builder can reference using CSS custom properties.
3. **Preview pairings** before publishing them live.
4. **Generate and serve CSS** that wires everything together on the frontend, in the block editor, and inside Etch.

You do not need to edit theme files, write `@font-face` rules by hand, or manage font file downloads manually. The plugin handles all of that.

---

## Delivery Profile Model

A **delivery profile** describes how a font family should be served at runtime. It carries:

- the provider (local, Google, Bunny, or Adobe)
- the delivery type (self-hosted or CDN/remote)
- the font variants and faces that belong to this delivery arrangement
- optional provider-specific metadata

A single family can hold **more than one delivery profile**. For example, you might keep a self-hosted profile and a Google CDN profile on the same family so you can switch between them without losing either configuration.

The **active delivery profile** is what the plugin uses at runtime. Switching the active profile immediately changes what gets served — no re-import needed.

If a family has no active delivery profile, it remains in the library but is not served.

### Why multiple profiles?

Think of delivery profiles as saved configurations, not font files. Keeping two profiles on one family means you can test CDN delivery for performance, then switch back to self-hosted if you want more control — without having to redo the import each time. Only the active profile affects runtime output.

---

## Draft/Live Role Model

The plugin separates **role assignment** from **live deployment**.

- A **draft role** is a working selection stored as a pending state. Saving a draft does not change live output.
- The **applied (live) roles** are what the plugin actually serves on the frontend, in Gutenberg, and in Etch.
- `Apply Sitewide` promotes the current draft roles to live output. Until that action is used, the live site is unaffected by draft changes.

This separation means you can freely experiment, preview, and compare multiple pairings before committing any change to the site.

### The three role slots

| Role | CSS variable | Always available? |
|---|---|---|
| Heading | `--font-heading` | Yes |
| Body | `--font-body` | Yes |
| Monospace | `--font-monospace` | Only when enabled in Settings → Behavior |

Each role slot can also be set to **fallback-only mode** (no family forced), which outputs the configured fallback stack without a family binding.

### Using role variables in your theme

Once roles are applied sitewide, the plugin emits something like:

```css
:root {
    --font-heading: 'Inter', sans-serif;
    --font-body: 'Source Serif 4', Georgia, serif;
}
```

You can reference these variables anywhere in your own CSS:

```css
h1, h2, h3 { font-family: var(--font-heading); }
p, li, td  { font-family: var(--font-body); }
```

Most themes and page builders that support CSS custom properties will pick these up automatically if you configure them to do so.

---

## Runtime CSS Pipeline

When the plugin needs to produce or refresh the generated stylesheet, these services run in sequence:

1. **`CatalogService`** — builds the unified family catalog from all provider sources and library state.
2. **`RuntimeAssetPlanner`** — decides which local and remote assets need to load based on the current live roles, active delivery profiles, and output settings.
3. **`CssBuilder`** — generates `@font-face` rules for self-hosted deliveries, plus role variables, optional family/category variables, weight tokens, and utility classes.
4. **`AssetService`** — writes the generated CSS to disk when file delivery is available, manages the cache state, and falls back to inline delivery if needed.
5. **`RuntimeService`** — enqueues the generated stylesheet for the frontend, Gutenberg, Etch, and admin preview paths; handles preloads and remote connection hints.

The canonical generated stylesheet is written to:

```
wp-content/uploads/fonts/.generated/tasty-fonts.css
```

If file delivery is disabled or unavailable, the plugin falls back to injecting CSS inline in the page `<head>`.

### What this means in practice

- The generated stylesheet contains all `@font-face` rules for self-hosted families and all role variable declarations.
- CDN and Adobe deliveries are loaded as separate `<link>` tags rather than being embedded in the generated stylesheet.
- If you open `Advanced Tools → Generated CSS`, you are looking at the exact output of this pipeline.

---

## Variable Fonts (Opt-In)

A **variable font** is a single font file that contains a continuous range of design variations (weight, width, slant, etc.) encoded as named **axes**. The most common axis is `wght` (weight), which lets you specify any weight from 100–900 in a single file instead of needing separate files per weight.

**Why this matters:** with a variable font, one WOFF2 file can do the work of eight separate weight files. That means fewer HTTP requests, smaller total download in many cases, and more flexibility to use in-between weights.

### Variable font support in the plugin

Variable font support is **opt-in**. It is disabled by default so static-only installs stay simple and unaffected.

When you enable it in `Settings → Output → Variable Font Support`:

- **Library**: families that have variable delivery profiles display a `Variable` badge and can be filtered by type.
- **Upload flow**: an axis editor appears per uploaded variable file so you can review detected axes and set axis defaults before saving.
- **Google and Bunny search**: variable families are marked in search results so you can spot them at a glance.
- **Deploy Fonts**: each role (Heading, Body, Monospace) gains per-role axis controls. You can pin a specific axis value (e.g., `wght: 650`) or a weight range override for that role.
- **Generated CSS**: `font-variation-settings` is included in `@font-face` and usage rules where variable faces are active, but **only when a role axis value differs from the font's registered default**. Axes already at their registered default are omitted to keep generated output clean and non-redundant. Weight ranges are expressed as `<number> <number>` in `font-weight` descriptors.

  > **Why this matters:** a variable font's axis defaults are already the "do nothing" values. Emitting them in `font-variation-settings` adds bytes without changing rendering. Omitting them produces a smaller, cleaner stylesheet without any visible difference.
- **Editor presets and Block Editor sync**: variation metadata travels with the family so the block editor and site editor reflect the correct design space.

### Static fallback

When variable font support is disabled, the plugin treats every font as static. Variable font files that were already uploaded still work — they are just served without any axis metadata or `font-variation-settings`. Re-enabling variable support restores axis metadata and per-role controls.

### Decision guide

- **You have separate weight files (e.g., Inter-Regular.woff2, Inter-Bold.woff2)** → use static mode (default). No change needed.
- **You have a variable font file (e.g., Inter-VariableFont_wght.woff2)** → enable Variable Font Support in `Settings → Output`.
- **You imported from Google Fonts and want the variable version** → enable Variable Font Support, then re-import the family. Google Fonts provides variable versions where available.

---

| Provider | Files downloaded? | API key needed? | Variable font support? | Best for |
|---|---|---|---|---|
| Local files | Yes (you upload them) | No | Yes | Fonts you already own or licensed separately |
| Google Fonts | Optional (self-hosted) | Yes, for live search | Yes | Large open catalog, self-hosting for privacy |
| Bunny Fonts | Optional (self-hosted) | No | Source metadata only (CDN serves variable, self-hosted serves static files) | GDPR-friendly alternative to Google CDN |
| Adobe Fonts | No (Adobe-hosted) | No (project ID only) | Depends on project | Existing Adobe CC subscriptions with web projects |

Providers are not exclusive. You can mix sources — for example, use a self-hosted local upload for headings and a Bunny CDN delivery for body text.

### Decision guide

- **You already have font files** → use Local.
- **You want access to a large free catalog** → use Google or Bunny.
- **Your site handles EU user data and you want to avoid Google CDN** → use Bunny.
- **You have an Adobe CC subscription and want premium typefaces** → use Adobe.
- **You are not sure** → start with Bunny Fonts (no API key, GDPR-friendly, large catalog).

---

## Related Docs

- [Getting Started](Getting-Started)
- [Deploy Fonts](Deploy-Fonts)
- [Font Library](Font-Library)
- [Settings](Settings)
- [Architecture](Architecture)
- [Glossary](Glossary)
- [FAQ](FAQ)
