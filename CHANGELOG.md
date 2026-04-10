# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## [1.8.0] - 2026-04-11

### Changed

- Promoted the validated `1.8.0` beta line to the stable release rail after the final beta verification pass.

## [1.8.0-beta.2] - 2026-04-11

### Changed

- Updated the local release helper and release-process documentation so beta promotion can start from the current `main` dev state without auto-advancing `main` to the next dev line.

### Fixed

- Fixed the Settings > Developer panel so destructive action rows keep their descriptions and buttons aligned without the oversized gaps shown in the previous layout.

## [1.8.0-beta.1] - 2026-04-11

### Added

- Added a global Unicode Range Output control in Settings so emitted `@font-face`, editor font-face payloads, and Block Editor sync can preserve provider subsets, force Latin presets, omit unicode-range entirely, or use a validated custom override without rewriting stored face metadata.
- Added opt-in variable font support across local uploads, Google imports, and runtime delivery, including stored axis metadata/defaults, role-specific axis controls, and static weight overrides for heading, body, and monospace roles.
- Added variable-font awareness throughout the library and import workflows, including family type badges/filtering, variable metadata in Google and Bunny search results, upload axis editors, and clearer source-only messaging for Bunny variable families.
- Added PHP and JavaScript coverage for variable font contracts, provider metadata, library rendering, runtime asset planning, and role/settings persistence.
- Added a three-rail GitHub release workflow with stable, beta, and nightly channels, including a Behavior-tab update-channel selector and rollback reinstall path for channel downgrades.
- Added bundled JavaScript translation assets and an RTL admin stylesheet so WordPress can load admin translations and directional overrides through the standard core mechanisms.

### Changed

- Updated catalog records, generated CSS, editor font presets, Block Editor sync payloads, and runtime planning to carry font-variation settings and range-based weights when variable fonts are enabled, while keeping static-only behavior as the default.
- Improved hosted CSS parsing and import merging so Google, Bunny, and Adobe faces share normalized axis metadata and imported profiles retain variable/static face details consistently.
- Split release automation into shared quality checks plus dedicated stable, beta, and nightly publishing workflows, and replaced the local release helper with channel-aware branch, beta, and stable commands.
- Streamlined Settings > Behavior so the update channel control, rollback reinstall action, and status messaging now share a flatter inline layout with improved responsive behavior.
- Simplified Settings > Developer maintenance actions by tightening the related action-row presentation and switching destructive actions to a two-step in-place confirmation pattern.
- Updated the quality workflow to use `actions/setup-node@v5` with Node.js 22.
- Bumped the plugin minimum supported WordPress version to 6.5 to align the header with the APIs used by the Font Library and editor integrations.

### Fixed

- New font families now start in the library across Google imports, Bunny imports, direct uploads, and raw uploads-folder scans, and families that were library-only return to that state after they stop being used by live roles.
- Hid the Upload Files variable column and toggle unless variable font support is enabled, so static-only installs no longer show inactive variable controls in the upload builder.
- Fixed preload and preview weight selection so saved role overrides and variable `WGHT` axis values choose the intended face instead of assuming hard-coded defaults.
- Fixed local upload duplicate detection and filename handling for variable fonts so self-hosted variable files can coexist cleanly with static faces in the same family.
- Fixed Google and Bunny CDN frontend requests so a saved `font-display: optional` no longer leaves live first-visit renders stuck on fallback fonts.
- Fixed Gutenberg editor typography on Automatic.css sites by mirroring the managed heading/body role variables into the editor canvas when ACSS sync is active.
- Improved nightly release notes generation so the workflow can fall back to the latest stable or beta tag when a previous nightly tag is unavailable.
- Fixed admin accessibility and plugin-compliance gaps across the dashboard, including REST route argument schemas, roving-tabindex behavior, real disabled states for blocked destructive actions, tooltip `aria-describedby` wiring, and translation-safe plural handling in both PHP and JavaScript.
- Fixed generated CSS downloads and Adobe project validation by sending byte-accurate `Content-Length` headers and rejecting invalid Adobe project IDs before remote validation requests run.

## [1.7.0] - 2026-04-09

### Added

- Added an Automatic.css integration service plus a dedicated Settings > Integrations workflow for syncing ACSS heading/body font-family settings to `var(--font-heading)` and `var(--font-body)`, including status messaging, backup/restore handling, and managed-mapping visibility in the dashboard.
- Added renderer, REST, runtime, storage, and JavaScript contract coverage for the new integrations flow, Etch canvas stylesheet handling, and filesystem-context regression protection for generated CSS writes.
- Added Bricks Builder and Oxygen Builder integrations with auto-detection, settings toggles, published-family exposure in builder font controls, and Gutenberg editor style mirroring for matching builder typography choices.
- Added a Settings > Developer tab with guarded maintenance actions for clearing plugin caches, resetting suppressed notices, restoring default settings, wiping the managed font library, and resetting integration detection.

### Changed

- Positioned `1.7.0` as a beta milestone on the road to `2.0`, with a few more releases expected before the final major version is considered stable.
- Moved Gutenberg Font Library sync controls and related local-environment guidance into the dedicated Integrations tab, including updated deep links from notices and activity log actions.
- Simplified Settings > Integrations so Gutenberg Font Library and Automatic.css each render as a single primary settings row with inline status, reducing duplicated copy and visual repetition.
- Removed the horizontal separator treatment across the Settings surfaces in favor of spacing-only grouping for a cleaner layout.
- Added a minimal output preset plus an opt-in role font-weight toggle so generated CSS can stay token-focused by default while advanced installs can restore explicit body and heading weights.
- Updated output snippets and Advanced Tools formatting so CSS variable panels emit declaration lists without the surrounding `:root` wrapper and render those snippets cleanly.

### Fixed

- Made `Hide Onboarding Hints` suppress settings helper descriptions consistently at render time across Output, Integrations, and Behavior instead of relying only on CSS hiding.
- Improved generated CSS filesystem context resolution so writes can fall back to the nearest existing parent directory when the final target path does not exist yet.
- Added renderer regression coverage for the streamlined integrations layout and the no-descriptions settings state when onboarding hints are disabled.
- Preserved unavailable Bricks, Oxygen, and Automatic.css integration detection state during settings saves so companion plugins can still auto-enable later when they become available.
- Fixed Block Editor cleanup during managed-library wipes and uninstall so synced font families are removed even when Gutenberg sync is currently disabled.
- Recovered legacy Automatic.css detection state so activating ACSS later can still bootstrap the managed role-variable mapping correctly.

## [1.6.0] - 2026-04-08

### Added

- Added a maintainable multi-file local test harness layout while keeping `php tests/run.php` as the single entry point, plus new direct coverage for hosted CSS parsing, local-environment detection, native upload validation, activation hooks, and generated-asset refresh paths.
- Added a lightweight zero-dependency JavaScript contract test layer for shared admin helpers and the Etch canvas iframe stylesheet bridge.
- Added a pull request and branch CI workflow that runs PHP lint, the PHP test suite, and the JavaScript contract tests before changes reach release tagging.
- Added configurable extended font-output variable controls in Output Settings, including optional global weight tokens, role aliases, and category aliases for sans, serif, and mono stacks.
- Added a unified output-settings workflow with quick presets, separate class and variable master toggles, and granular class controls for roles, aliases, categories, and family utilities.
- Added dedicated admin context/view builders plus section renderers for the studio, preview, tools, settings, diagnostics, library, and activity areas so the dashboard no longer depends on one monolithic page-render method.
- Added first-class top-level admin tabs for Deploy Fonts, Font Library, Settings, and Advanced Tools, with canonical deep links and keyboard-friendly tab navigation across the dashboard.
- Added REST-backed autosave for the Output and Behavior settings panels so delivery, output, and plugin behavior changes save without full page submits.
- Added copy-to-clipboard actions for copyable diagnostics fields so generated stylesheet paths and other system details can be reused directly from the dashboard.
- Added a reusable uninstall handler and expanded storage/runtime coverage for generated CSS delivery, provider directories, and uninstall cleanup flows.

### Changed

- Updated GitHub Actions workflows to use `actions/checkout@v6` in line with the current upstream recommendation.
- Updated the release workflow and local release helper to run automated PHP and JavaScript quality gates before packaging or publishing a version.
- Extracted shared admin and canvas runtime helper contracts into standalone browser/Node-compatible scripts so the shipped assets and local smoke tests exercise the same logic.
- Split the legacy monolithic PHP test file into domain case files so the suite can keep growing without concentrating all coverage in `tests/run.php`.
- Refined the admin dashboard hero messaging and kept it visible when training wheels are disabled.
- Updated generated CSS, output snippets, and editor font stacks to emit family-level variables with category-aware fallback defaults, semantic aliases, and shared weight tokens when extended output is enabled.
- Replaced the legacy class output mode enum with normalized per-feature settings and aligned class generation with the existing variable alias/category model, including legacy setting migration on read.
- Expanded the PHP coverage for extended output-variable settings, renderer controls, category-aware fallback stacks, and runtime CSS generation.
- Refreshed plugin metadata and the generated translation template so WordPress headers, extracted strings, and the current plugin description stay aligned for version 1.6.0.
- Updated admin import summaries and search-result metadata to use translation-safe plural strings instead of English-only suffix assembly.
- Added request-scope settings caching and reused generated stylesheet state during stale-file fallback delivery to reduce repeated normalization and file-state work during admin requests.
- Refactored the admin page pipeline so `AdminController` delegates page-context construction, `AdminPageRenderer` acts as a thin shell, and renderer sections/templates own the dashboard composition in smaller files.
- Split the remaining oversized admin rendering helpers into focused library, preview, tools, settings, and diagnostics renderers with dedicated family-card templates so renderer files stay within the accepted size limits without changing dashboard output.
- Reworked font storage and generated stylesheet handling to use provider-specific upload directories, a hidden generated CSS directory, optional inline CSS delivery, and matching preload behavior.
- Standardized dashboard terminology and polished the admin CSS against the shared token system so deployment controls, library cards, activity filters, and notice surfaces stay visually aligned.
- Grouped the per-family utility class toggle with the other class-output controls and added direct view-builder coverage for output quick-mode selection across all preset states.
- Updated admin asset versioning in local environments to append file modification times so CSS and JavaScript changes refresh immediately without waiting on plugin version bumps.
- Updated generated stylesheet diagnostics and runtime enqueueing to recognize legacy `uploads/fonts/tasty-fonts.css` files, report their metadata, and migrate current files into the canonical `.generated` location when possible.
- Updated local-environment notices and related activity actions to deep-link into the new Settings > Behavior surface instead of the older advanced-tools query flow.
- Updated the local `bin/release` helper to match indented version constants in `plugin.php` so future releases can bump both version markers successfully.

### Fixed

- Restored the shared admin help-tip affordance so contextual help buttons render again across the dashboard instead of silently no-oping.
- Corrected the GitHub updater slug to `tasty-fonts` while keeping the packaged plugin directory unchanged for install/update continuity.
- Improved admin accessibility by associating visible labels with preview/snippet code blocks and announcing local-environment notices as live regions.
- Fixed dashboard section ordering and disclosure placement so Preview and Advanced Tools open inside Deployment Controls in the intended sequence.
- Fixed malformed admin wrapper markup that allowed the WordPress footer to overlap the dashboard content.
- Removed unreachable admin-controller fallback branches and softened local-environment notice copy so Plugin Behavior guidance uses plain-language site-request and certificate wording.
- Fixed uninstall cleanup so generated CSS, synced block-editor font families, and plugin-managed runtime transients are removed consistently.
- Aligned role alias utility classes with the variable-output guards so empty body or monospace assignments no longer emit fallback-only alias selectors.
- Fixed plugin boot sequencing so the `tasty-fonts` textdomain loads before runtime, admin, and REST hooks register, keeping translated strings available earlier in the request lifecycle.

## [1.5.1] - 2026-04-07

### Added

- Added a WordPress-native GitHub release updater so public GitHub installs can detect newer stable releases from the normal Plugins screen and install the attached release ZIP directly.
- Expanded the local PHP test harness to cover GitHub release discovery, details-modal metadata, updater caching, and cache invalidation after upgrades.

### Changed

- Advertised the GitHub repository as the plugin update source via `Update URI` and documented that GitHub-installed copies can update from attached stable release ZIPs in the normal WordPress plugins workflow.

## [1.5.0] - 2026-04-07

### Added

- Added a full role preview workspace with editorial, card, reading, interface, and code scenarios, plus draft/live baseline seeding, inline role pickers, preview CSS copy, and quick draft/save actions.
- Added a dedicated Plugin Behavior panel with controls for Block Editor Font Library sync, the optional monospace role, training wheels, and uninstall cleanup settings.
- Added normalized font category metadata and library type filters so families can be browsed by categories such as serif, sans-serif, script, display, and monospace.
- Added local-environment guidance for Block Editor Font Library sync, including dismissible notices and activity log actions that deep-link back to Plugin Behavior when loopback TLS trust fails.

### Changed

- Reworked the Font Roles dashboard into explicit deployment controls for draft-only versus sitewide delivery, with clearer status pills, utility actions, and preview-oriented workflows.
- Defaulted heading, body, and monospace role selectors to fallback-only mode until a saved family is chosen, instead of auto-selecting library entries.
- Forced admin preview font loading to use `font-display: swap` for both self-hosted previews and remote preview stylesheets so specimen text stays visible during preview refreshes.
- Updated library filtering so live role families still match Published views, and surfaced delivery/category badges more consistently across library cards and details.

### Fixed

- Improved local development behavior by defaulting Block Editor Font Library sync off on likely local hosts, while preserving the saved preference once a user explicitly changes it.
- Added more actionable sync failure logging for certificate-verification problems during authenticated loopback editor requests.
- Expanded the local PHP test harness to cover preview workspace behavior, category filtering, fallback-only roles, admin preview font-display overrides, and local-environment sync safeguards.

## [1.4.0] - 2026-04-06

### Added

- Added frontend WOFF2 preload hints for the live heading/body pair, plus an Output Settings toggle to control them.
- Added a Generated CSS panel under Advanced Tools so the live stylesheet can be inspected directly from the plugin dashboard.
- Added a Font Display selector to Output Settings with support for `optional`, `swap`, `fallback`, `block`, and `auto`, including contextual guidance for choosing the right trade-off.

### Changed

- Defaulted generated `@font-face` output to `font-display: optional`, while keeping the selected display mode reflected in the saved stylesheet and admin snippets.
- Refreshed generated CSS immediately when output-affecting settings change so minification and font-display selections take effect without stale file output.
- Updated the modern browser user-agent used for remote font CSS requests to a current Chrome release so Google Fonts continues returning modern WOFF2-first CSS responses.

## [1.3.0] - 2026-04-05

### Added

- Added a standalone admin token stylesheet with explicit enqueue ordering and regression coverage so shared design tokens load before the main admin stylesheet.
- Added a clickable dashboard version badge that links directly to the GitHub release notes for the running plugin version.
- Added a dedicated Output Settings tab with fuller guidance, plus richer empty states for the library and activity panels.

### Changed

- Strengthened the admin interface accessibility with clearer focus treatment, better tooltip announcement wiring, live role-status announcements, and reduced-motion and forced-colors support.
- Reworked the Font Roles workspace so live deployment status is now a compact top-row pill, publishing actions are separated from Advanced Tools, and output settings live in their own single-column advanced tab.
- Updated the dashboard hero copy to position Tasty Custom Fonts as typography management for Etch, Gutenberg, and the frontend.

## [1.2.1] - 2026-04-05

### Added

- Added `uninstall.php` cleanup for plugin options and transients, plus a settings toggle to optionally remove plugin-managed uploaded font files during uninstall.
- Added a direct Settings link from the WordPress plugins list to the Tasty Custom Fonts admin screen.
- Expanded the local PHP test harness to cover applied-role deletion guards, transient-backed admin notices, uninstall preferences, modern format filtering, streamed file copies, and admin asset versioning.

### Changed

- Switched local upload writes to streamed file moves/copies instead of buffering the full uploaded font into PHP memory, and now apply `FS_CHMOD_FILE` after successful copies.
- Simplified the Etch canvas bridge to rely on iframe `load` events and the existing `MutationObserver` without the extra polling interval.
- Simplified rescan logging so a manual rescan records the high-level `Fonts rescanned.` entry without also adding the lower-level generated CSS write message.
- Removed legacy EOT and SVG font handling from local catalog scans and generated `@font-face` output so scanned formats now match the upload allowlist.
- Reused `TASTY_FONTS_VERSION` directly for shipped admin asset versioning instead of hashing plugin files on every admin page load.

### Fixed

- Added explicit PHP upload-origin verification before reading uploaded font temp files and hardened the local upload flow around validated file metadata.
- Moved redirect-backed success and error notices off URL query strings into short-lived per-user transients.
- Protected family and variant deletion against live applied roles as well as draft role selections.
- Aligned the catalog scanner, upload validation, and generated CSS around the same modern local font formats only.

## [1.2.0] - 2026-04-05

### Added

- Added a draft-versus-live role workflow with explicit applied role tracking, inline deployment status, and immediate draft saves when assigning heading or body roles from library cards.
- Added compact activity filtering with account selection, text search, filtered counts, and a retained audit entry after clearing the log.
- Added per-variant deletion from family detail tables, with safeguards when a family is still assigned to a role.
- Added richer Google import tools including live search previews, variant quick actions, manual variant sync, selection counts, and approximate WOFF2 transfer estimates.

### Changed

- Reworked the entire admin dashboard into a flatter WordPress-style interface with tokenized spacing, consistent controls, compact headers, and a redesigned Font Roles workspace.
- Rebuilt the Add Fonts flow so Google, Adobe, and Upload panels share one consistent design language and a more compact, responsive layout.
- Redesigned the local library cards with inline previews, copyable variable pills, better role actions, dropdown source filtering, and stronger empty/filter states.
- Updated the generated admin font loading path so the plugin dashboard only loads font faces for previews instead of applying live sitewide role CSS to the admin chrome.
- Renamed the project assets, runtime bridge, translations, and documentation from the older Etch naming to Tasty Custom Fonts while keeping the plugin directory stable for upgrades.

### Fixed

- Fixed draft role saves so they no longer overwrite the currently applied sitewide role pair when live roles were already active.
- Fixed vague settings notices by surfacing more specific save messages, clearer activity entries, and consistent toast behavior across destructive and validation flows.
- Fixed Google search result truncation, Step 2 import sync issues, preview rendering glitches, and several spacing/alignment inconsistencies across the dashboard.
- Expanded the local PHP test suite to cover font-face-only admin CSS, activity option building, draft/live role persistence, and related admin regressions.

## [1.1.0] - 2026-04-04

### Added

- Added optional Adobe Fonts web project support using an existing project ID, with stylesheet validation, cached family detection, and separate remote enqueueing for the frontend, Gutenberg, and the Etch canvas.
- Added a dedicated Adobe Fonts admin tab with project save, resync, and remove actions plus detected-family summaries for role selection and previews.
- Expanded the local PHP test harness to cover Adobe CSS parsing, project validation state handling, merged role-family sources, and runtime Adobe stylesheet loading.

### Changed

- Merged Adobe project families into the heading and body role selectors and Gutenberg font presets without mixing Adobe metadata into the self-hosted local library inventory.
- Updated the admin canvas bridge to support multiple runtime stylesheets so the generated self-hosted CSS and optional Adobe stylesheet can load together in Etch.
- Updated the plugin description and README to document the difference between self-hosted Google/local fonts and Adobe-hosted web project delivery.

## [1.0.3] - 2026-04-03

### Added

- Added a GitHub-focused README, the full GPLv2 license text, and a screenshots placeholder directory for repository documentation.
- Added a lightweight local PHP test harness covering filename parsing, CSS generation, generated asset refreshes, and admin input normalization.

### Changed

- Committed the `tests/` source files and narrowed `.gitignore` so only generated test artifacts stay excluded.
- Completed the plugin header metadata with the repository URL, author URL, license fields, and minimum WordPress/PHP requirements.

## [1.0.2] - 2026-04-03

### Changed

- Replaced the GitHub release action with the GitHub CLI and upgraded `actions/checkout` to `v5` so the release workflow no longer relies on Node 20 JavaScript actions.
- Updated the release workflow to publish the matching `CHANGELOG.md` section as the GitHub release notes instead of only showing an auto-generated compare link.
- Consolidated the Google Fonts request user-agent string into a shared constant.

### Fixed

- Replaced dynamic text-domain constants with the literal `'tasty-fonts'` so WordPress i18n tooling can extract every translatable string, and added the generated POT file.
- Fixed settings persistence for CSS delivery mode, `font-display`, CSS minification, and the preview sentence in the admin settings form.
- Narrowed settings writes to allowlisted form fields and sanitized generated font URLs and `font-weight` values to avoid malformed CSS output.
- Removed suppressed filesystem calls during directory cleanup, preserved the active `theme.json` schema version for Block Editor font presets, and stored plugin log timestamps in UTC.
- Added `index.php` stubs to the plugin uploads directories on activation and aligned the local test harness with the current WordPress helper usage.

## [1.0.1] - 2026-04-03

### Added

- Added a GitHub Actions release workflow that creates a GitHub release from a version tag and uploads the installable WordPress plugin zip automatically.
- Added a `bin/release` helper to bump the plugin version, promote the changelog, commit, tag, and push a release from one command.

## [1.0.0] - 2026-04-03

### Added

- Initial public release of Tasty Custom Fonts.
