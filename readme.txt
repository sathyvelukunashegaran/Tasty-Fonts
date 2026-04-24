=== Tasty Custom Fonts ===
Tags: fonts, typography, google fonts, adobe fonts, bunny fonts
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.13.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Take control of your WordPress typography with one plugin for local fonts, Google Fonts, Bunny Fonts, Adobe Fonts, and builder-friendly runtime delivery.

== Description ==

Tasty Custom Fonts gives you one place to upload, import, preview, and publish the fonts that shape your site. Manage local fonts, Google Fonts, Bunny Fonts, and Adobe Fonts from a single dashboard, then push the right typography across the frontend, Gutenberg, and Etch without piecing together a fragmented workflow.

= Why site builders and agencies like it =

* One typography workflow for local files, hosted services, and CDN delivery.
* Draft-first publishing so you can preview changes before they touch the live site.
* Multiple delivery profiles per family, so switching how a font is served does not mean rebuilding your setup.
* Runtime CSS, editor presets, preloads, and connection hints generated from the same settings flow.
* Practical controls for fallback stacks, font-display, variable fonts, diagnostics, and activity history.

= Key features =

* Upload WOFF2, WOFF, TTF, and OTF files directly from the WordPress dashboard.
* Import Google Fonts as self-hosted files or serve them from Google CDN.
* Import Bunny Fonts as self-hosted files or serve them from Bunny CDN.
* Connect an Adobe Fonts web project and use Adobe-hosted families alongside local and imported fonts.
* Save multiple delivery profiles per family and choose which one is active on the live site.
* Preview heading, body, and optional monospace roles before applying them sitewide.
* Generate runtime CSS, editor presets, preload hints, and preconnect hints from the same workflow.
* Optionally sync published families into the core Block Editor Font Library.
* Enable variable font support for more flexible, modern typography systems.

= Optional integrations =

Tasty Custom Fonts does not require any companion plugins. Etch, Automatic.css, Bricks, and Oxygen support is optional and only activates when those tools are available on the site.

= Multisite =

The plugin is designed for single-site activation and per-site activation inside multisite networks. Network-wide activation is not supported.

= Release channels =

The latest stable release is 1.13.0. Beta and nightly builds are published from GitHub releases for teams that want early access to the upcoming line before it becomes the next stable release.

= What is planned after 1.13.0 stable? =

The post-1.13.0 line continues with power-user and agency improvements: command-line maintenance tools, stronger bulk library management, richer diagnostics, scheduled Adobe project refreshes, and more polished distribution surfaces.

If you want to help shape that roadmap, open a feature request or feedback issue on GitHub:

https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/issues

== Installation ==

1. Upload the `tasty-fonts` folder to `/wp-content/plugins/`, or install a packaged ZIP from GitHub releases.
2. Activate `Tasty Custom Fonts` from the Plugins screen on the site where you want to manage typography.
3. Open `Tasty Fonts` in the WordPress admin menu.
4. Add local, Google, Bunny, or Adobe families to your library and publish your live roles when ready.

== Frequently Asked Questions ==

= Do I need Etch or another builder plugin? =

No. The plugin works on standard WordPress sites. Etch, Automatic.css, Bricks, and Oxygen are optional enhancements, not requirements.

= Does this plugin require a Google Fonts API key? =

Only for live Google Fonts search inside the dashboard. Local uploads, Bunny Fonts, and Adobe Fonts workflows do not require a Google API key.

= Can I self-host imported fonts? =

Yes. Google Fonts and Bunny Fonts can both be imported into local storage so your runtime CSS can serve your own copies instead of relying on third-party CDN delivery.

= Does it support multisite? =

It supports activating the plugin on individual sites within a multisite network. Network-wide activation is not supported.

= Where are self-hosted font files stored? =

Generated assets and imported files live under `wp-content/uploads/fonts/`, with provider-specific subdirectories for Google and Bunny imports.

= Where should I send feedback or feature ideas? =

Open an issue on GitHub for feature requests, workflow feedback, or compatibility ideas:

https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/issues

== Screenshots ==

1. Deploy Fonts workspace for comparing draft and live typography before publishing.
2. Font Library view showing multiple delivery profiles and publish states in one place.
3. Settings screen for output controls, integrations, and runtime behavior.
4. Advanced Tools screen for generated CSS, diagnostics, and activity history.

== Changelog ==

= 1.13.0 =

* Added optional admin-access controls so administrators can delegate plugin access to selected non-administrator roles and specific users.
* Added a dry-run site-transfer validation flow that stages import bundles before the destructive import step and surfaces transfer activity in the Transfer tab.
* Added editable fallback controls for Heading, Body, and Monospace roles in Studio so fallback-only roles can use intentional stacks without requiring a library family.
* Added a Role Weights in Classes opt-in setting so role utility classes can include weight and variation settings alongside font-family declarations.
* Added Minimal-output role bridges for Etch frontends, canvas data, and Gutenberg editor styles.
* Added editor-parity mirroring for the live Automatic.css runtime stylesheet when sitewide roles are active.
* Added a Show Activity Log behavior setting so Advanced Tools can hide the full diagnostics log by default while still recording events.
* Reworked Output Settings into focused Minimal, Variables only, Classes only, and Custom presets with grouped sub-controls.
* Changed default Heading and Body fallback stacks from `sans-serif` to `system-ui, sans-serif`.
* Refreshed the admin UI token system, library cards, import panels, and preview surfaces with a denser Tasty Foundry workspace treatment.
* Raised PHPStan enforcement to level 10.

= 1.12.0 =

* Added a portable Site Transfer workflow in Settings → Transfer to export and import bundles containing managed fonts, library data, settings, and live role assignments.
* Moved Google Fonts API key material into dedicated encrypted option storage and excluded it from portable exports.
* Fixed variable-font output so registered axis defaults are omitted from generated CSS when they would be redundant.
* Fixed CSS minification to preserve significant whitespace where collapsing it would change browser behavior.
* Renamed packaged plugin directory from `etch-fonts/` to `tasty-fonts/`.

= 1.11.0 =

* Added deep Bricks Builder typography integration with managed Theme Style sync, Theme Style target modes, and a Tasty-managed Bricks Theme Style workflow.
* Added direct Bricks control over the native `disableGoogleFonts` setting.
* Fixed Bricks frontend and builder canvas typography when Bricks quoted `var(...)` font-family values.
* Added contributor scaffolding including a contributing guide, local setup docs, GitHub issue and PR templates, and a security policy.

= 1.10.0 =

* Added Automatic.css font-weight sync, dedicated role-weight variables, and matching Gutenberg plus Etch canvas bridge coverage.
* Improved Google and Bunny variable import flows with style-aware terminology, named weight aliases, and tighter role-axis cleanup when switching back to static families.
* Refined admin branding, upload-form polish, accessibility feedback, and the final release messaging for the 1.10.0 stable line.

= 1.9.0 =

* Current stable release line.

See `CHANGELOG.md` in the repository for the full project changelog.
