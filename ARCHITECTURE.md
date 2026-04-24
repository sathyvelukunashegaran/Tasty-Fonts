# Tasty Fonts Architecture

This is the repo-local architecture entry point for contributors and AI agents. The published wiki page remains the deeper reference: [`wiki/Architecture.md`](wiki/Architecture.md).

For adjacent context, read [`AGENTS.md`](AGENTS.md) for agent workflow, [`DESIGN.md`](DESIGN.md) for admin UI rules, and [`.agents/README.md`](.agents/README.md) for shared agent notes.

## System Shape

- Single WordPress plugin with no runtime build step.
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
includes/Fonts/          Catalog, CSS building, runtime planning, assets
includes/Google/         Google Fonts import and catalog logic
includes/Bunny/          Bunny Fonts import and catalog logic
includes/Adobe/          Adobe Fonts project import logic
includes/Admin/          Admin controller, contexts, renderers, templates
includes/Api/            Internal REST adapter for admin actions
includes/Updates/        GitHub release updater integration
assets/css/              Admin design tokens and CSS
assets/js/               Admin UI behavior and JS contracts
tests/                   PHP harness and JS contract tests
wiki/                    Published project documentation staging
```

## Core Runtime Flow

```text
plugins_loaded
  -> TastyFonts\Plugin::boot()
     -> service graph is registered
     -> runtime/admin/API hooks are attached
     -> RuntimeService enqueues generated CSS for frontend, editor, and integrations
```

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
- Templates live in `includes/Admin/Renderer/templates/`.
- Shared admin behavior lives in `assets/js/admin.js`.
- DOM-free JS contract helpers live in `assets/js/admin-contracts.js`.
- Admin mutations go through `includes/Api/RestController.php`; endpoints are internal, authenticated, and not treated as a public versioned API.

## State Storage

- Settings: `tasty_fonts_settings`
- Google API key: `tasty_fonts_google_api_key_data`
- Font library: `tasty_fonts_library`
- Draft roles: `tasty_fonts_roles`
- Applied runtime roles: `applied_roles` inside `tasty_fonts_settings`
- Activity log: `tasty_fonts_log`
- Generated CSS cache: `tasty_fonts_css_v2` and `tasty_fonts_css_hash_v2`

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
