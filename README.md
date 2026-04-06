# Tasty Custom Fonts

Typography management for Etch, Gutenberg, and the frontend. Import Google or Bunny Fonts, upload local font files, rescan `wp-content/uploads/fonts/`, or connect an Adobe Fonts web project while the plugin handles generated CSS, runtime loading, editor presets, and preview tooling.

![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![WordPress 6.1+](https://img.shields.io/badge/WordPress-6.1%2B-21759B?logo=wordpress&logoColor=white)
![License: GPLv2+](https://img.shields.io/badge/License-GPLv2%2B-green)
![Dependencies: none](https://img.shields.io/badge/dependencies-none-brightgreen)

**Works especially well with [EtchWP](https://etch.com) and [Automatic CSS](https://automaticcss.com).**

## What It Does

- Upload `WOFF2`, `WOFF`, `TTF`, and `OTF` files directly from the dashboard.
- Rescan fonts placed anywhere under `wp-content/uploads/fonts/`.
- Import Google Fonts as self-hosted files or keep them on the Google CDN.
- Import Bunny Fonts as self-hosted files or keep them on the Bunny CDN.
- Connect an Adobe Fonts web project and use Adobe-hosted families in the same role and preview workflow.
- Save multiple delivery profiles per family, then switch which delivery is live.
- Keep families `Published`, park them as `Paused`, or let active role selections mark them `In Use`.
- Generate runtime CSS, typography variables, role snippets, editor presets, preloads, and connection hints without a build step.

## Current Feature Set

### Multiple font sources, one library

- Local uploads and rescans feed the same library used by Google, Bunny, and Adobe imports.
- Google and Bunny imports support both `Self-hosted` and `CDN` delivery modes.
- Adobe stays Adobe-hosted by design, but its detected families still appear in selectors, previews, Gutenberg presets, and Etch.
- Families can carry multiple delivery profiles, so a single family can keep a local profile and a remote CDN profile side by side.

### Draft, publish, and preview workflow

- Role selection is built around explicit draft vs live sitewide delivery.
- `Apply Sitewide` serves the current role CSS on the frontend, in Gutenberg, and in Etch.
- `Save Draft` stores role changes without changing live output.
- The preview workspace includes editorial, card, reading, interface, and code scenes.
- An optional monospace role can expose `--font-monospace` for `code` and `pre`.

### Output and delivery controls

- Generates `@font-face` rules for self-hosted deliveries and enqueues remote stylesheets for Google CDN, Bunny CDN, and Adobe.
- Writes `wp-content/uploads/fonts/tasty-fonts.css` when file delivery is available and falls back to inline CSS when it is not.
- Supports global `font-display`, CSS minification, same-origin WOFF2 preloads for the active heading/body pair, and remote preconnect hints.
- Supports per-family font-display overrides and per-family live-delivery switching from the library UI.

### WordPress-aware integration

- Registers runtime families as Block Editor typography presets.
- Can sync managed families into the core Block Editor Font Library when the site environment supports loopback requests.
- Loads the right styles in the frontend, block editor, admin previews, and Etch canvas.
- Keeps admin previews safe by loading font faces and remote preview styles with `font-display: swap`.

### Admin tooling

- Filter the library by source, delivery state, category, and search text.
- Save per-family fallback stacks.
- Delete individual variants, delete individual delivery profiles, or remove whole families.
- Review activity history for scans, imports, delivery changes, asset refreshes, and settings changes.
- Control plugin behavior for Block Editor sync, training wheels, monospace role support, and uninstall cleanup.

## Source And Delivery Model

| Source | Delivery choices | Notes |
| --- | --- | --- |
| Local files | Self-hosted | Upload from the dashboard or rescan `uploads/fonts/`. |
| Google Fonts | Self-hosted or Google CDN | Live catalog search is available when a valid Google API key is saved. |
| Bunny Fonts | Self-hosted or Bunny CDN | Search from the plugin or paste a family name manually. |
| Adobe Fonts | Adobe-hosted | Uses an existing Adobe web project; no file downloads. |

Each family in the library stores one or more delivery profiles. The active delivery profile controls what is served at runtime.

## Requirements

| Component | Requirement |
| --- | --- |
| WordPress | 6.1+ |
| PHP | 8.1+ |
| Etch | Optional |

The plugin works without Etch, but the Etch canvas bridge is most useful when you build with Etch.

## Installation

### Install from GitHub

1. Download the latest release ZIP from [GitHub Releases](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/releases).
2. In WordPress, go to `Plugins -> Add New Plugin -> Upload Plugin`.
3. Upload the ZIP, install it, and activate `Tasty Custom Fonts`.
4. Open `Tasty Fonts` in the WordPress admin menu.

The packaged plugin directory remains `etch-fonts/` so existing installs can update cleanly.
GitHub-installed copies can also pick up future stable releases from the normal WordPress `Plugins` screen because the plugin now advertises updates from the attached GitHub Release ZIP.

### Manual install

1. Clone or download this repository.
2. Copy the `etch-fonts` folder into `wp-content/plugins/`.
3. Activate the plugin from the WordPress `Plugins` screen.
4. Open `Tasty Fonts` in the WordPress admin menu.

## Getting Started

### 1. Add fonts to the library

Choose the source that fits the job:

- `Upload files`: add local font files from the dashboard.
- `Rescan fonts`: pick up files already placed under `wp-content/uploads/fonts/`.
- `Google Fonts`: import selected variants as self-hosted files or a Google CDN delivery.
- `Bunny Fonts`: search or enter a family name, then import selected variants as self-hosted files or a Bunny CDN delivery.
- `Adobe Fonts`: save a web project ID and sync its detected families.

Google self-hosted files are saved under `wp-content/uploads/fonts/google/<family-slug>/`. Bunny self-hosted files are saved under `wp-content/uploads/fonts/bunny/<family-slug>/`.

### 2. Choose live delivery per family

In the library, each family can expose one or more delivery profiles. Use the family controls to:

- switch the active runtime delivery for that family
- mark a family `Published` or `Paused`
- keep extra delivery profiles for later without deleting the family

`Paused` keeps the family in the library without serving it at runtime. Families selected in active roles are treated as `In Use`.

### 3. Set roles and publish when ready

1. Choose `Heading` and `Body` families.
2. Optionally enable the `Monospace` role in `Plugin Behavior`.
3. Set fallback stacks for each role.
4. Use `Save Draft` while experimenting.
5. Use `Apply Sitewide` when you want the generated role CSS served everywhere.

The plugin exposes role aliases such as `--font-heading`, `--font-body`, and, when enabled, `--font-monospace`.

### 4. Tune output settings

Use `Output Settings` to control:

- file vs inline CSS delivery
- default `font-display`
- CSS minification
- same-origin WOFF2 preloads for the active heading/body pair
- remote preconnect hints for Google, Bunny, and Adobe deliveries

## Why Self-Host

Self-hosting keeps font requests on your own domain, reduces third-party runtime dependencies, and lets you control caching and generated output directly from WordPress.

Tasty Custom Fonts can still keep a remote CDN delivery when that is the better operational choice. The library is built to let you switch between delivery profiles instead of forcing one model for every family.

Adobe Fonts is the exception: Adobe web-font delivery stays on Adobe-hosted stylesheets.

## Development

There is no build step, no Composer install, and no npm install.

Useful commands:

```bash
php tests/run.php
find . -name '*.php' -not -path './output/*' -print0 | xargs -0 -n1 php -l
```

## Translation

Tasty Custom Fonts is translation-ready and uses the `tasty-fonts` text domain.

A POT template is included at `languages/tasty-fonts.pot`. You can use that file with tools like [Poedit](https://poedit.net/) or [Loco Translate](https://localise.biz/wordpress/plugin) to build `.po` and `.mo` translations for your site.

## Screenshots

Screenshots coming soon.

## Contributing

Pull requests are welcome. For larger changes, open an issue first so the direction is clear before implementation starts.

- Match the WordPress coding conventions already used in the plugin.
- Run `php tests/run.php` before submitting changes.
- Update this README when user-facing behavior or requirements change.

## License

Tasty Custom Fonts is licensed under the [GNU General Public License v2 or later](LICENSE).

See [LICENSE](LICENSE) for the full text.
