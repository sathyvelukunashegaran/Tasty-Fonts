# Tasty Fonts Architecture

This is the repo-local architecture entry point for contributors and AI agents. The published wiki page remains the deeper reference: [`wiki/Architecture.md`](wiki/Architecture.md).

For adjacent context, read [`AGENTS.md`](AGENTS.md) for agent workflow, [`DESIGN.md`](DESIGN.md) for admin UI rules, and [`.agents/README.md`](.agents/README.md) for shared agent notes.

## System Shape

- Single WordPress plugin with no runtime build step.
- Requires PHP 8.1+ and WordPress 6.5+.
- `plugin.php` defines constants, loads the autoloader, and boots `TastyFonts\Plugin` on `plugins_loaded`.
- `includes/Plugin.php` manually wires services. There is no DI container.
- Runtime output is generated CSS plus WordPress enqueue hooks.
- Admin screens are PHP-rendered templates enhanced by vanilla JavaScript and internal REST endpoints.

## Primary Directories

```text
plugin.php               Entry point, constants, autoloader, boot
includes/Plugin.php      Manual service registration and hook wiring
includes/Repository/     Options, transients, library state, activity log
includes/Support/        Storage, environment, font utilities
includes/Fonts/          Catalog, CSS building, runtime planning, assets, library mutations
includes/Google/         Google Fonts import and catalog logic
includes/Bunny/          Bunny Fonts import and catalog logic
includes/Adobe/          Adobe Fonts project import logic
includes/Integrations/   Page-builder and framework integrations (ACSS, Bricks, Oxygen)
includes/Maintenance/    Developer tooling, health checks, site transfer, snapshots, support bundles
includes/Cli/            WP-CLI command adapter
includes/Admin/          Admin controller, contexts, renderers, templates
includes/Api/            Internal REST adapter for admin actions
includes/Updates/        GitHub release updater integration
includes/Uninstall/      Clean-up handler invoked on plugin uninstall
assets/css/              Admin design tokens and CSS
assets/js/               Admin UI behavior and JS contracts
tests/                   PHP harness and JS contract tests
wiki/                    Published project documentation staging
```

## Core Runtime Flow

`Plugin::boot()` is called once on `plugins_loaded` and executes five registration steps:

```text
plugins_loaded
  -> TastyFonts\Plugin::boot()
     -> registerRuntimeHooks()   — frontend/editor enqueue, preload hints, cron regeneration,
                                   Google API key revalidation cron, Etch/Bricks/Oxygen shims
     -> registerAdminHooks()     — admin menu, admin_init, asset enqueue, Block Editor Font Library sync
     -> registerRestHooks()      — rest_api_init registers RestController routes
     -> registerCatalogHooks()   — attachment add/delete invalidates catalog cache
     -> registerCliCommand()     — registers wp tasty-fonts commands (only when WP_CLI is active)
```

Two background cron actions are registered inside `registerRuntimeHooks()`:

- `tasty_fonts_regenerate_css` → `Plugin::handleGeneratedCssRegeneration()` — rebuilds the generated stylesheet via cron
- `tasty_fonts_revalidate_google_api_key` → `Plugin::handleGoogleApiKeyRevalidation()` — re-checks the stored Google Fonts API key

Generated CSS rebuilds flow through the font layer:

```text
CatalogService
  -> RuntimeAssetPlanner
  -> CssBuilder
  -> AssetService
  -> uploads/fonts/.generated/tasty-fonts.css or inline fallback
```

## Central Model

The delivery-profile model is the key domain object. A family can have multiple delivery profiles, and `active_delivery_id` decides what runtime serves.

Profiles carry provider, delivery type, variants, generated face data, and provider metadata. Provider and storage changes often affect catalog output, generated CSS, preload/preconnect behavior, and external stylesheet enqueue behavior together.

## Admin And API Flow

- Admin entry points live under `includes/Admin/`.
- `AdminController` coordinates all admin actions and is the single entry point for both the admin UI and the WP-CLI adapter.
- `includes/Admin/Renderer/` contains named section renderers:
  - `LibrarySectionRenderer` — font library tab
  - `SettingsSectionRenderer` — settings tab (General, Behavior, Transfer)
  - `ToolsSectionRenderer` — advanced tools tab
  - `DiagnosticsSectionRenderer` — diagnostics tab
  - `StudioSectionRenderer` — studio/preview tab
  - `PreviewSectionRenderer` — preview rendering
  - `FamilyCardRenderer` + `FamilyCardRendererSupport` — per-family card UI
- Templates live in `includes/Admin/Renderer/templates/`.
- Shared admin behavior lives in `assets/js/admin.js`.
- DOM-free JS contract helpers live in `assets/js/admin-contracts.js`.
- Admin mutations go through `includes/Api/RestController.php`; endpoints are internal, authenticated, and not treated as a public versioned API.

## Maintenance Layer

`includes/Maintenance/` contains five services:

- **DeveloperToolsService** — cache clears, CSS regeneration, storage scaffolding, and integration-detection reset. Called on activation (`ensureStorageScaffolding`) and deactivation (`clearDeactivationCaches`).
- **HealthCheckService** — runs structured health checks grouped by severity (`ok` / `info` / `warning` / `critical`). Results are consumed by the Diagnostics admin tab and the `wp tasty-fonts doctor` WP-CLI command.
- **SiteTransferService** — portable ZIP export/import (introduced in 1.12.0). Bundles a JSON manifest and font files. Requires the PHP `ZipArchive` extension. Excludes the Google Fonts API key from bundles. Bundle compatibility is enforced by `SCHEMA_VERSION = 1`.
- **SnapshotService** — automated library snapshots stored in `tasty_fonts_snapshots`. Retains up to 10 snapshots (configurable, min 1). Requires `ZipArchive`. Supports preview-before-restore. Uses `SCHEMA_VERSION = 1`.
- **SupportBundleService** — assembles a sanitized diagnostic ZIP (settings summary, library summary, activity log, advanced-tools payload) for support submissions. Requires `ZipArchive`.

## Integrations

`includes/Integrations/` contains dedicated service classes for three page-builder / framework integrations:

- **AcssIntegrationService** (Automatic CSS) — detects ACSS presence, injects `--font-heading` and `--font-body` CSS custom property references into ACSS font-family and font-weight options. Filterable via `tasty_fonts_acss_integration_available`.
- **BricksIntegrationService** — detects Bricks Builder, filters `bricks/builder/standard_fonts` to expose managed families in the Bricks font picker. Filterable via `tasty_fonts_bricks_integration_available`.
- **OxygenIntegrationService** — detects Oxygen Builder and registers a compatibility shim. Filterable via `tasty_fonts_oxygen_integration_available`.

Etch is handled directly inside `RuntimeService` (not a separate Integrations class) via the `etch/canvas/enqueue_assets` action and the `tasty_fonts_etch_integration_available` filter.

## WP-CLI Interface

`Cli/Command.php` is registered as the `tasty-fonts` WP-CLI command group (only when `WP_CLI` is active). All commands route through `AdminController` so CLI and admin UI share identical behavior.

| Command | Purpose |
|---|---|
| `wp tasty-fonts doctor [--format=json]` | Print structured health-check results |
| `wp tasty-fonts css regenerate` | Rebuild the generated runtime stylesheet |
| `wp tasty-fonts cache clear` | Clear plugin caches and rebuild generated assets |
| `wp tasty-fonts library rescan` | Rescan local font storage and refresh the catalog |
| `wp tasty-fonts google-api-key status\|save [--google-api-key-stdin]` | Inspect or update the stored Google Fonts API key |
| `wp tasty-fonts settings reset --yes` | Reset all plugin settings to defaults |
| `wp tasty-fonts files delete --yes` | Delete plugin-managed font files, generated CSS, exports, and snapshots |
| `wp tasty-fonts transfer export` | Export a portable site-transfer bundle |
| `wp tasty-fonts transfer import <bundle> [--dry-run] [--yes]` | Import a transfer bundle (dry-run available) |
| `wp tasty-fonts snapshot create\|restore\|list` | Create, restore, or list rollback snapshots |
| `wp tasty-fonts support-bundle` | Generate a sanitized diagnostic ZIP |

## Extension Points For Developers

The plugin exposes several WordPress filters. These are internal hooks; they are not versioned as a stable public API and may change between releases.

| Filter | Signature | What it does |
|---|---|---|
| `tasty_fonts_generated_css` | `(string $css, array $localCatalog, array $roles, array $settings)` | Filters the generated stylesheet string after `CssBuilder` assembles it and before it is cached and written to disk. |
| `tasty_fonts_catalog` | `(array $catalog)` | Filters the unified family catalog built by `CatalogService`. |
| `tasty_fonts_http_request_args` | `(array $args, string $url)` | Filters `wp_remote_request` args used by Google, Bunny, and Adobe provider HTTP clients. |
| `tasty_fonts_sync_block_editor_font_library` | `(bool $should_sync, array $result, string $provider)` | Controls whether Block Editor Font Library sync runs after an import. |
| `tasty_fonts_bricks_integration_available` | `(bool $available)` | Overrides whether the Bricks integration considers itself available. |
| `tasty_fonts_oxygen_integration_available` | `(bool $available)` | Overrides whether the Oxygen integration considers itself available. |
| `tasty_fonts_acss_integration_available` | `(bool $available)` | Overrides whether the ACSS integration considers itself available. |
| `tasty_fonts_etch_integration_available` | `(bool $available)` | Overrides whether the Etch integration considers itself available. |

**Example — appending a custom rule to the generated stylesheet:**

```php
add_filter( 'tasty_fonts_generated_css', function ( string $css, array $catalog, array $roles, array $settings ): string {
    return $css . ':root { --my-brand-font: var(--font-heading); }';
}, 10, 4 );
```

## State Storage

| What | Option / Transient |
|---|---|
| Settings | `tasty_fonts_settings` |
| Google Fonts API key | `tasty_fonts_google_api_key_data` (encrypted, isolated, never exported in transfer bundles) |
| Font library | `tasty_fonts_library` |
| Draft roles | `tasty_fonts_roles` |
| Applied (live) roles | `applied_roles` key inside `tasty_fonts_settings` |
| Activity log | `tasty_fonts_log` |
| Rollback snapshots | `tasty_fonts_snapshots` |
| Generated CSS cache | Transients: `tasty_fonts_css_v2` (stylesheet) and `tasty_fonts_css_hash_v2` (content hash) |

## Verification Map

- General PHP behavior: `php tests/run.php`
- JS contracts: `node --test tests/js/*.test.cjs`
- Static PHP analysis: `composer phpstan`
- CSS conventions: `npm run lint:css`
- Release helpers: `bash tests/bin/release-scripts.test.sh`
- Copy-paste duplication: `bin/run-jscpd`

## Related Docs

- [`AGENTS.md`](AGENTS.md) — repo-specific agent workflow.
- [`.agents/README.md`](.agents/README.md) — shared agent workspace index.
- [`DESIGN.md`](DESIGN.md) — Tasty Foundry admin design system.
- [`wiki/Architecture.md`](wiki/Architecture.md) — full architecture page for the published wiki.
- [`wiki/Testing.md`](wiki/Testing.md) — test harness and verification details.
- [`wiki/Local-Setup.md`](wiki/Local-Setup.md) — local development setup.
