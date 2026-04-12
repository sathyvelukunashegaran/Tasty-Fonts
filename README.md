# Tasty Custom Fonts

Bring your entire WordPress typography system under one roof.

Tasty Custom Fonts gives designers, agencies, and WordPress professionals one place to upload, import, preview, and publish the fonts that power a site. Manage local fonts, Google Fonts, Bunny Fonts, and Adobe Fonts from a single dashboard, then push the right typography across the frontend, Gutenberg, and Etch without juggling disconnected tools.

![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![WordPress 6.5+](https://img.shields.io/badge/WordPress-6.5%2B-21759B?logo=wordpress&logoColor=white)
![License: GPLv2+](https://img.shields.io/badge/License-GPLv2%2B-green)
![Dependencies: none](https://img.shields.io/badge/dependencies-none-brightgreen)

**Works especially well with [EtchWP](https://etch.com), [Automatic CSS](https://automaticcss.com), Bricks, and Oxygen.**

These integrations are optional. Tasty Custom Fonts works on a standard WordPress site without requiring Etch, Automatic.css, Bricks, or Oxygen.

## Why Tasty Custom Fonts

- **One font workflow, not four.** Upload local files, self-host imports, use CDN delivery, or connect Adobe-hosted projects from the same UI.
- **Preview before you publish.** Build draft typography roles, compare them against the live site, and apply changes when you are ready.
- **Stay flexible at runtime.** Store multiple delivery profiles per family and switch which one is active without rebuilding your process from scratch.
- **Keep WordPress in sync.** Generate runtime CSS, editor presets, preloads, connection hints, and builder-friendly typography outputs from one settings surface.
- **Built for real production sites.** Manage fallback stacks, `font-display`, variable fonts, generated CSS, diagnostics, and activity history without adding a build step.

## What You Can Do

- Upload `WOFF2`, `WOFF`, `TTF`, and `OTF` files directly from the dashboard.
- Enable variable font support to unlock axis-aware controls and saved weight behavior per role.
- Rescan `wp-content/uploads/fonts/` for files added outside the plugin UI.
- Import Google Fonts as self-hosted files or serve them from Google CDN.
- Import Bunny Fonts as self-hosted files or serve them from Bunny CDN.
- Connect an Adobe Fonts web project and use Adobe-hosted families in the same workflow.
- Save multiple delivery profiles per family and choose which one is active on the live site.
- Build draft heading, body, and optional monospace roles before publishing them sitewide.
- Preview typography across editorial, interface, reading, card, and code scenes.
- Generate CSS variables, optional utility classes, editor presets, preloads, and connection hints from a single plugin.

## Built For The Way WordPress Teams Actually Work

### Deploy Fonts

Choose your draft heading, body, and optional monospace roles, compare draft versus live output, and publish only when the system feels right.

### Font Library

Browse every managed family in one place, switch active delivery profiles, set fallback stacks, tune `font-display`, and keep unused families available without serving them live.

### Settings

Control output style, delivery mode, unicode-range handling, preloads, integrations, variable font behavior, utility class generation, and plugin behavior from a single settings area.

### Advanced Tools

Inspect generated CSS, download the current runtime stylesheet, review system details, copy diagnostics, and trace changes through the activity log.

## Font Sources And Delivery Modes

| Source | Delivery choices | Notes |
| --- | --- | --- |
| Local files | Self-hosted | Upload from the dashboard or rescan `uploads/fonts/`. |
| Google Fonts | Self-hosted or Google CDN | Live search requires a valid Google API key. |
| Bunny Fonts | Self-hosted or Bunny CDN | Search is available in the dashboard. |
| Adobe Fonts | Adobe-hosted | Uses an existing Adobe web project and does not download files locally. |

Every family can store one or more delivery profiles. The active delivery profile controls what the plugin serves at runtime.

## A Better Typography Publishing Workflow

1. Add families to the library from local files, Google Fonts, Bunny Fonts, or Adobe Fonts.
2. Choose which delivery profile should be active for each family.
3. Assign draft roles for heading, body, and optional monospace output.
4. Preview the typography system before it touches the live site.
5. Apply the current draft sitewide across the frontend, Gutenberg, and Etch.

Self-hosted Google imports are stored under `wp-content/uploads/fonts/google/<family-slug>/`.
Self-hosted Bunny imports are stored under `wp-content/uploads/fonts/bunny/<family-slug>/`.

## Integrations

- Tasty Custom Fonts registers runtime families as editor typography presets.
- Published families can optionally sync into the core Block Editor Font Library.
- Automatic.css can map its heading/body font-family and font-weight settings to the managed Tasty Fonts role variables.
- Bricks integration can expose published Tasty Fonts families in builder selectors and mirror matching Bricks typography choices into Gutenberg editor styles.
- Oxygen integration can expose published Tasty Fonts families through the compatibility shim and mirror matching Oxygen typography choices into Gutenberg editor styles.
- Etch receives the same runtime stylesheet URLs and bridge CSS through the canvas bridge so preview typography stays aligned with the live site.

## Runtime Output That Stays Practical

- Self-hosted deliveries generate `@font-face` rules inside the runtime stylesheet.
- CDN and Adobe deliveries are enqueued only when they are active.
- Generated CSS is written to `wp-content/uploads/fonts/.generated/tasty-fonts.css` when file delivery is available.
- Inline CSS fallback is available when file delivery is disabled or unavailable.
- Same-origin WOFF2 preloads can be emitted for the live heading and body families.
- Remote preconnect hints can be emitted for active Google, Bunny, and Adobe deliveries.
- Admin previews force `font-display: swap` for preview safety, even when the live site uses another setting.

## Documentation

Full documentation lives on the [GitHub Wiki](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki):

- [Documentation Hub](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki)
- [Getting Started](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki/Getting-Started)
- [Deploy Fonts](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki/Deploy-Fonts)
- [Font Library](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki/Font-Library)
- [Settings](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki/Settings)
- [Advanced Tools](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki/Advanced-Tools)
- [Developer Docs](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki/Architecture)
- [FAQ](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki/FAQ)
- [Glossary](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki/Glossary)

## Install

### Install From GitHub Releases

1. Download the latest ZIP from [GitHub Releases](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/releases).
2. In WordPress, go to `Plugins -> Add New Plugin -> Upload Plugin`.
3. Upload the ZIP and activate `Tasty Custom Fonts`.
4. Open `Tasty Fonts` in the WordPress admin menu.

The packaged plugin directory is `tasty-fonts/`.

### Updates For GitHub Installs

The plugin advertises its GitHub repository through `Update URI` and includes a GitHub release updater. Sites installed from a GitHub release ZIP can follow the selected `Stable`, `Beta`, or `Nightly` rail from `Settings -> Behavior -> Update Channel`.

### Manual Install

1. Clone or download this repository.
2. Copy the `tasty-fonts` folder into `wp-content/plugins/`.
3. Activate the plugin from the WordPress `Plugins` screen.
4. Open `Tasty Fonts` in the WordPress admin menu.

## Release Channels

`1.10.0` is the current stable release.

Stable, beta, and nightly packages are published from GitHub. The release flow is intentionally linear so the upcoming line can be tested in public before it becomes the next stable rail.

## What We’re Preparing After 2.0.0 Stable

The 2.0.0 stable release is focused on locking in the current foundation. The work immediately after that release is aimed at making Tasty Custom Fonts even more capable for agencies, product teams, and high-volume WordPress operators.

Planned post-2.0 improvements include:

- richer operator tooling, including WP-CLI commands for maintenance, cache management, CSS regeneration, rescans, and diagnostics
- portable site-to-site workflows with secure export and import for settings and library metadata
- faster large-library management with bulk actions, broader search result flows, and better high-volume browsing
- more flexible ingestion paths, including remote CSS or URL-based import flows for custom hosted font sources
- smarter background upkeep such as scheduled Adobe project refreshes and deeper runtime diagnostics, including font-loading performance visibility and downloadable activity logs
- stronger platform alignment through Composer-based autoloading, evaluation of targeted Interactivity API adoption, and polished plugin screenshots for distribution surfaces

If there is a workflow you want to see prioritized after 2.0.0, open an issue or feature request on [GitHub Issues](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/issues). Feedback on real-world agency, client, and editorial use cases is especially useful when shaping the next release line.

## Requirements

| Component | Requirement |
| --- | --- |
| WordPress | 6.5+ |
| PHP | 8.1+ |
| Etch | Optional |

The plugin works without Etch, but the Etch canvas bridge is especially useful when you build with Etch.

## Multisite

Tasty Custom Fonts is intended for single-site use and per-site activation inside multisite networks. Network-wide activation is not supported because the plugin stores and generates font assets per site.

## Development

There is no build step, no Composer install, and no npm install.

Useful commands:

```bash
php tests/run.php
node --test tests/js/*.test.cjs
find . -name '*.php' -not -path './output/*' -print0 | xargs -0 -n1 php -l
```

## AI Usage

AI is used in this repository only to help generate test cases and documentation. Plugin code, architecture, and release behavior are authored and reviewed manually.

## Translation

Tasty Custom Fonts is translation-ready and uses the `tasty-fonts` text domain.

The translation template is included at `languages/tasty-fonts.pot`.

## Contributing

Contribution setup, verification commands, branch expectations, and good-first entry points live in [CONTRIBUTING.md](CONTRIBUTING.md).

For larger changes, open an issue first so the direction is clear before implementation starts. Feature ideas, workflow requests, and usability feedback are welcome through [GitHub Issues](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/issues).

## License

Tasty Custom Fonts is licensed under the [GNU General Public License v2 or later](LICENSE).
