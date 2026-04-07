# Concepts

Understand the three core ideas that underpin everything Tasty Custom Fonts does before diving into the task guides.

## Use This Page When

- you are new to the plugin and want a mental model before reading the task guides
- you want to understand why draft and live roles are separate
- you need to understand how the plugin generates and serves CSS
- you are choosing between font providers

## Delivery Profile Model

A **delivery profile** describes how a font family should be served at runtime. It carries:

- the provider (local, Google, Bunny, or Adobe)
- the delivery type (self-hosted or CDN/remote)
- the font variants and faces that belong to this delivery arrangement
- optional provider-specific metadata

A single family can hold **more than one delivery profile**. For example, you might keep a self-hosted profile and a Google CDN profile on the same family so you can switch between them without losing either configuration.

The **active delivery profile** is what the plugin uses at runtime. Switching the active profile immediately changes what gets served — no re-import needed.

If a family has no active delivery profile, it remains in the library but is not served.

## Draft/Live Role Model

The plugin separates **role assignment** from **live deployment**.

- A **draft role** is a working selection stored as a pending state. Saving a draft does not change live output.
- The **applied (live) roles** are what the plugin actually serves on the frontend, in Gutenberg, and in Etch.
- `Apply Sitewide` promotes the current draft roles to live output. Until that action is used, the live site is unaffected by draft changes.

This separation means you can freely experiment, preview, and compare multiple pairings before committing any change to the site.

The three role slots are:

- `Heading` — always available
- `Body` — always available
- `Monospace` — available when enabled in `Settings -> Behavior`

Each role slot can also be set to **fallback-only mode** (no family forced), which outputs the configured fallback stack without a family binding.

## Runtime CSS Pipeline

When the plugin needs to produce or refresh the generated stylesheet, these services run in sequence:

1. **`CatalogService`** — builds the unified family catalog from all provider sources and library state.
2. **`RuntimeAssetPlanner`** — decides which local and remote assets need to load based on the current live roles, active delivery profiles, and output settings.
3. **`CssBuilder`** — generates `@font-face` rules for self-hosted deliveries, plus role variables, optional family/category variables, weight tokens, and utility classes.
4. **`AssetService`** — writes the generated CSS to disk when file delivery is available, manages the cache state, and falls back to inline delivery if needed.
5. **`RuntimeService`** — enqueues the generated stylesheet for the frontend, Gutenberg, Etch, and admin preview paths; handles preloads and remote connection hints.

The canonical generated stylesheet is written to:

`wp-content/uploads/fonts/.generated/tasty-fonts.css`

If file delivery is disabled or unavailable, the plugin falls back to injecting CSS inline in the page `<head>`.

## Choosing a Provider

| Provider | Files downloaded? | API key needed? | Best for |
|---|---|---|---|
| Local files | Yes (you upload them) | No | Fonts you already own or licensed separately |
| Google Fonts | Optional (self-hosted) | Yes, for live search | Large open catalog, self-hosting for privacy |
| Bunny Fonts | Optional (self-hosted) | No | GDPR-friendly alternative to Google CDN |
| Adobe Fonts | No (Adobe-hosted) | No (project ID only) | Existing Adobe CC subscriptions with web projects |

Providers are not exclusive. You can mix sources — for example, use a self-hosted local upload for headings and a Bunny CDN delivery for body text.

## Related Docs

- [Getting Started](getting-started.md)
- [Deploy Fonts](deploy-fonts.md)
- [Font Library](font-library.md)
- [Settings](settings.md)
- [Architecture](developer/architecture.md)
