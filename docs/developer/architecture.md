# Architecture

High-level orientation for how the plugin boots, stores data, and serves runtime typography.

## Use This Page When

- you are changing plugin behavior
- you need to find the right service layer
- you want the shortest path to understanding the runtime and admin architecture
- you need to understand how services communicate and where state lives

---

## Key Structure

### Plugin Lifecycle

- `plugin.php` defines plugin constants, registers the autoloader, and boots `TastyFonts\Plugin` on `plugins_loaded`
- `Plugin` wires the service graph and registers runtime, admin, REST, and catalog hooks
- activation ensures upload storage exists and generated CSS can be written
- deactivation clears known transients and scheduled CSS regeneration hooks

### Directory Layout

```
plugin.php               — entry point; constants, autoloader, boot
includes/
  Plugin.php             — service container and hook wiring
  Repository/            — options, transients, library state, activity logging
  Support/               — storage helpers, environment detection, font utilities
  Fonts/                 — catalog, CSS building, runtime planning, library mutations, asset handling
  Google/                — Google Fonts import and catalog logic
  Bunny/                 — Bunny Fonts import and catalog logic
  Adobe/                 — Adobe Fonts import and catalog logic
  Admin/                 — page controller, context builders, view builders, section renderers
  Api/                   — REST adapter over admin actions
  Updates/               — GitHub release updater integration
assets/                  — JS and CSS for admin UI
languages/               — POT template and translation files
tests/                   — PHP test cases and JS contract tests
bin/                     — release helper script
```

### Major Service Layers

- `Repository/`: options, transients, library state, and activity logging
- `Support/`: storage, environment detection, and font utility helpers
- `Fonts/`: catalog, CSS building, runtime planning, local uploads, library mutations, and generated asset handling
- provider namespaces: Google, Bunny, and Adobe import/catalog logic
- `Admin/`: controller, page context building, view building, and section rendering
- `Api/`: REST adapter over admin actions
- `Updates/`: GitHub release updater integration

### Service Container

Services are wired in `Plugin::boot()`. Each service class is registered by its FQCN. Retrieve a service:

```php
$asset_service = TastyFonts\Plugin::instance()->get_service( TastyFonts\Fonts\AssetService::class );
```

No external dependency injection container is used. The wiring is explicit and co-located in `Plugin.php`.

---

## Delivery Profile Model

Each family can store one or more delivery profiles. A profile carries:

- provider (string constant: `local`, `google`, `bunny`, `adobe`)
- delivery type (string constant: `self-hosted`, `cdn`, `adobe-hosted`)
- variants (array of variant records)
- faces (array of generated `@font-face` parameters)
- optional metadata (e.g., Google API response details)

The active delivery profile controls runtime output for that family. Profile data is stored serialized in a WordPress option via `Repository/ImportRepository.php`.

---

## Runtime Flow

```
plugins_loaded
  └─ Plugin::boot()
       ├─ Repository services registered
       ├─ Support services registered
       ├─ Fonts services registered
       └─ RuntimeService::register_hooks()
            ├─ wp_enqueue_scripts   → enqueue generated stylesheet (frontend)
            ├─ enqueue_block_assets → enqueue for block editor
            └─ etch_canvas_ready    → provide stylesheet URL to Etch canvas
```

When the generated stylesheet needs to be rebuilt:

```
CatalogService::build()
  └─ Merges library state with provider catalog data
       └─ RuntimeAssetPlanner::plan()
            └─ Decides which local files and remote stylesheets to serve
                 └─ CssBuilder::build()
                      └─ Generates @font-face rules + role variables + optional classes
                           └─ AssetService::write()
                                └─ Writes tasty-fonts.css to disk (or marks inline fallback)
```

---

## Block Editor Font Library Sync

The Block Editor Font Library sync is a **separate** code path from the plugin's own runtime CSS generation. It exists to mirror managed families into the WordPress site editor's native typography controls so they appear as choices inside Gutenberg's font picker.

How it works:

1. After a successful import or library mutation, the plugin queues a background sync.
2. The sync sends authenticated loopback requests from the server back to its own REST API to register font families with the WordPress Block Editor Font Library.
3. Registered families appear in the site editor's `Styles → Typography` panel alongside any other fonts the core Block Editor knows about.

**Why it is separate from runtime output**: the plugin's own generated stylesheet is always enqueued regardless of whether the Block Editor sync is enabled. Gutenberg editor presets (font-family CSS custom properties for the editor) are also registered independently. The Block Editor Font Library sync is an optional convenience layer on top of that.

**Why it defaults off on local environments**: loopback requests from the server to itself require a valid TLS certificate when the site is on HTTPS. Local development environments commonly use self-signed certificates that the server's own HTTP client does not trust. The plugin detects likely local environments and defaults the sync off to avoid repeated certificate verification failures. The plugin's own runtime output, admin previews, and Etch canvas delivery still work correctly when this sync is off.

See [Local Development](../troubleshooting/local-development.md) for troubleshooting guidance.

---

## REST API

The admin UI operates entirely through a plugin REST API adapter (`Api/RestController.php`). All settings saves, library mutations, and developer actions go through REST endpoints rather than traditional form submissions.

- All REST endpoints require admin-level authentication (WordPress nonce or application password).
- The endpoints are not versioned as a public API. They are internal to the admin UI and may change between releases.
- To inspect available routes, open the browser console on any plugin admin page and review the `fetch()` calls.

---

## State Storage

| What | Where |
|---|---|
| Settings | `get_option('tasty_fonts_settings')` — see `Repository/SettingsRepository.php` |
| Font library | `get_option('tasty_fonts_library')` — see `Repository/ImportRepository.php` |
| Draft roles | `get_option('tasty_fonts_roles')` — see `SettingsRepository::OPTION_ROLES` |
| Applied (live) roles | Stored under `applied_roles` within `tasty_fonts_settings`; used by `CssBuilder` at runtime |
| Activity log | `get_option('tasty_fonts_log')` — see `Repository/LogRepository.php` |
| Generated CSS cache | Transients: `tasty_fonts_css_v2` (stylesheet) and `tasty_fonts_css_hash_v2` (content hash) — see `AssetService::TRANSIENT_CSS` / `TRANSIENT_HASH` |
| Integration detection | Stored in settings; reset via `Settings → Developer` |

---

## Extension Points For Developers

The plugin exposes several WordPress filters. These are internal hooks used by the plugin's own systems; they are not versioned as a stable public API and may change between releases. Use with that in mind.

| Filter | Signature | What it does |
|---|---|---|
| `tasty_fonts_generated_css` | `( string $css, array $localCatalog, array $roles, array $settings )` | Filters the generated stylesheet string after `CssBuilder` assembles it and before it is cached and written to disk. Use this to append or rewrite rules. |
| `tasty_fonts_catalog` | `( array $catalog )` | Filters the unified family catalog built by `CatalogService`. |
| `tasty_fonts_http_request_args` | `( array $args, string $url )` | Filters `wp_remote_request` args used by Google, Bunny, and Adobe provider HTTP clients. |
| `tasty_fonts_sync_block_editor_font_library` | `( bool $should_sync, array $result, string $provider )` | Controls whether Block Editor Font Library sync runs after an import. |
| `tasty_fonts_bricks_integration_available` | `( bool $available )` | Overrides whether the Bricks integration considers itself available. |
| `tasty_fonts_oxygen_integration_available` | `( bool $available )` | Overrides whether the Oxygen integration considers itself available. |
| `tasty_fonts_acss_integration_available` | `( bool $available )` | Overrides whether the ACSS integration considers itself available. |
| `tasty_fonts_etch_integration_available` | `( bool $available )` | Overrides whether the Etch integration considers itself available. |

**Example — appending a custom rule to the generated stylesheet:**

```php
add_filter( 'tasty_fonts_generated_css', function ( string $css, array $catalog, array $roles, array $settings ): string {
    return $css . ':root { --my-brand-font: var(--font-heading); }';
}, 10, 4 );
```

If you need behavior that is not covered by the existing hooks:

- **Custom CSS additions**: add your own stylesheet that loads after the plugin's enqueued stylesheet (`RuntimeService` registers the handle).
- **Settings access**: use `get_option('tasty_fonts_settings')` to read settings and `update_option()` to modify them (the plugin will react on next request).
- **Service access**: retrieve services via `Plugin::instance()->get_service()` after `plugins_loaded`.
- **Custom provider support**: not yet supported by a public API. Open an issue to discuss roadmap fit.

---

## Notes

- The canonical generated stylesheet path is `uploads/fonts/.generated/tasty-fonts.css`.
- Google and Bunny can be self-hosted or CDN-based; Adobe remains hosted remotely.
- Block Editor Font Library sync is separate from the plugin's own runtime output path.
- Variable font support is opt-in. When enabled, `CatalogService`, `CssBuilder`, and `RuntimeAssetPlanner` carry axis metadata and emit `font-variation-settings` where variable faces are active. Disabling it reverts all paths to static-only behavior.

## Related Docs

- [Testing](testing.md)
- [Release Process](release-process.md)
- [Translations](translations.md)
- [Local Development](../troubleshooting/local-development.md)
- [FAQ — Developer Questions](../faq.md#developer-questions)
