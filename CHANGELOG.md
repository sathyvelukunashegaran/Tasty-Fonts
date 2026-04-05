# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

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
