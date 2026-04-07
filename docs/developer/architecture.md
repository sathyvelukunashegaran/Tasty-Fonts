# Architecture

High-level orientation for how the plugin boots, stores data, and serves runtime typography.

## Use This Page When

- you are changing plugin behavior
- you need to find the right service layer
- you want the shortest path to understanding the runtime and admin architecture

## Key Structure

### Plugin Lifecycle

- `plugin.php` defines plugin constants, registers the autoloader, and boots `TastyFonts\Plugin` on `plugins_loaded`
- `Plugin` wires the service graph and registers runtime, admin, REST, and catalog hooks
- activation ensures upload storage exists and generated CSS can be written
- deactivation clears known transients and scheduled CSS regeneration hooks

### Major Service Layers

- `Repository/`: options, transients, library state, and activity logging
- `Support/`: storage, environment detection, and font utility helpers
- `Fonts/`: catalog, CSS building, runtime planning, local uploads, library mutations, and generated asset handling
- provider namespaces: Google, Bunny, and Adobe import/catalog logic
- `Admin/`: controller, page context building, view building, and section rendering
- `Api/`: REST adapter over admin actions
- `Updates/`: GitHub release updater integration

### Delivery Profile Model

Each family can store one or more delivery profiles. A profile carries:

- provider
- delivery type
- variants
- faces
- optional metadata

The active delivery profile controls runtime output for that family.

### Runtime Flow

- `CatalogService` builds the unified family catalog
- `RuntimeAssetPlanner` decides which local and remote assets should load
- `CssBuilder` generates runtime CSS for local deliveries and variable/class output
- `AssetService` manages generated CSS caching, writing, fallback delivery, and status reporting
- `RuntimeService` enqueues runtime assets for the frontend, block editor, admin preview paths, and Etch

### Block Editor Font Library Sync

The Block Editor Font Library sync is a **separate** code path from the plugin's own runtime CSS generation. It exists to mirror managed families into the WordPress site editor's native typography controls so they appear as choices inside Gutenberg's font picker.

How it works:

1. After a successful import or library mutation, the plugin queues a background sync.
2. The sync sends authenticated loopback requests from the server back to its own REST API to register font families with the WordPress Block Editor Font Library.
3. Registered families appear in the site editor's `Styles -> Typography` panel alongside any other fonts the core Block Editor knows about.

**Why it is separate from runtime output**: the plugin's own generated stylesheet is always enqueued regardless of whether the Block Editor sync is enabled. Gutenberg editor presets (font-family CSS custom properties for the editor) are also registered independently. The Block Editor Font Library sync is an optional convenience layer on top of that.

**Why it defaults off on local environments**: loopback requests from the server to itself require a valid TLS certificate when the site is on HTTPS. Local development environments commonly use self-signed certificates that the server's own HTTP client does not trust. The plugin detects likely local environments and defaults the sync off to avoid repeated certificate verification failures. The plugin's own runtime output, admin previews, and Etch canvas delivery still work correctly when this sync is off.

See [Local Development](../troubleshooting/local-development.md) for troubleshooting guidance.

## Notes

- The canonical generated stylesheet path is `uploads/fonts/.generated/tasty-fonts.css`.
- Google and Bunny can be self-hosted or CDN-based; Adobe remains hosted remotely.
- Block Editor Font Library sync is separate from the plugin's own runtime output path.

## Related Docs

- [Testing](testing.md)
- [Release Process](release-process.md)
- [Translations](translations.md)
- [Local Development](../troubleshooting/local-development.md)
