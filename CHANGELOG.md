# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added

- Added Advanced Tools bulk cleanup actions for deleting all rollback snapshots and all retained site-transfer export bundles, with confirmation phrases, admin POST handlers, activity logs, and guardrails that block export deletion while any bundle is locked.
- Added a URL Import source filter for the Font Library so custom CSS URL import families can be isolated in library views while still retaining their self-hosted or external-request delivery filters.

### Changed

- Moved font import workflow controls into Settings > Behavior, keeping Google Fonts, Bunny Fonts, and custom uploads enabled by default while Adobe Fonts and URL imports remain opt-in.
- Moved Show Onboarding Hints and Show Activity Log into the Advanced Tools Developer tab so Settings > Behavior focuses on import workflows, font capabilities, cleanup, and access.
- Simplified Advanced Tools developer controls so the release rail appears first and saves channel changes immediately without a separate save button.
- Refined Advanced Tools transfer/activity surfaces, hidden activity-log messaging, destructive tool summaries, and snapshot rows so retained exports, locked exports, font files, and storage files are easier to understand.
- Polished admin masthead, top-panel rails, transfer actions, select clears, log toggles, copy/download buttons, and compact icon chrome using shared design tokens.
- Bumped the catalog cache transient namespace and tagged custom CSS URL import delivery profiles with URL Import filter tokens.

### Fixed

- Added end-to-end workflow gating so disabled font import workflows are reflected in Add Fonts UI panels and rejected consistently by REST/admin actions.
- Fixed hydrated family details so fallback, display, delivery, and publish-state controls bind only once while still initializing correctly after AJAX replacement.
- Fixed bulk export deletion paths so locked site-transfer bundles are preserved and blocked actions expose disabled-state messaging to both the UI and controller responses.

## [1.15.0-beta.2] - 2026-04-27

### Added

- Added a gated Custom CSS URL import workflow for expert users, including server-side dry runs, font-face review, duplicate handling, and final import support for one public HTTPS stylesheet at a time.
- Added Custom CSS provider services for URL inspection, snapshot persistence, self-hosted downloads, remote delivery profiles, and source metadata.
- Added live admin UI support for From URL imports, including disabled developer-gate messaging, delivery-mode selection, selectable face rows, warning details, and inline errors.
- Added documentation and wiki guidance for Custom CSS imports, provider limitations, privacy/licensing considerations, troubleshooting, and end-to-end testing.

### Changed

- Added an Advanced Tools developer gate for Custom CSS URL Imports so the expert From URL workflow stays disabled by default until deliberately enabled.
- Expanded library, catalog, runtime, storage, and settings flows so custom CSS delivery profiles participate in generated CSS, deployment planning, diagnostics, and managed storage consistently with other providers.
- Updated README/readme feature copy to include custom CSS URL imports alongside local, Google, Bunny, and Adobe workflows.

### Fixed

- Improved hosted-font URL resolution so relative @font-face sources from CDN stylesheets, including Fontsource CSS, resolve correctly during dry runs and imports.
- Added regression coverage for dry-run validation, warning states, duplicate behavior, self-hosted and remote final imports, gated admin rendering, JS contracts, generated CSS, and runtime planning.

## [1.15.0-beta.1] - 2026-04-27

### Changed

- Added a Marketing preview scene with CTA, signup, purchase, and social-follow samples so role selections can be reviewed against campaign-style content.
- Refined admin preview and diagnostics sample copy to use TastyWP branding and consistent sample attribution.
- Reworked compact admin control sizing so help buttons stay visually small while icon-only controls preserve larger hit targets.
- Updated admin UI color tokens, OKLCH fallbacks, badges, hover states, and preview treatments to keep amber usage readable within the Tasty palette.
- Improved help tooltip positioning and import/search result interaction markup for steadier admin UI behavior.
- Updated CSS lint output and CSS audit coverage for compact controls and scoped OKLCH token overrides.

### Fixed

- Fixed the card preview pricing flag so the "Most Popular" label remains readable on the dark pricing tile.
- Renamed verified diagnostics states to "Working Fine" / "OKAY" so passing health checks do not imply external verification.

## [1.14.1] - 2026-04-26

### Added

- Added refreshed admin UI screenshots for the released Tasty Fonts documentation set.

## [1.14.0] - 2026-04-26

- Promoted the validated `1.14.0` beta line to stable.

### Changed

- Reworked Advanced Tools into a power-user command center with Overview, Generated CSS, Developer, Transfer, and Activity tabs while preserving existing guarded maintenance and transfer workflows.
- Redesigned the Advanced Tools Overview around consistent board-and-row patterns for next-step guidance, health status, runtime details, and copyable debug paths.
- Moved generated CSS download controls into the Generated CSS panel and aligned diagnostics tab headers, spacing, typography, and button placement across the Advanced Tools dashboard.
- Expanded Advanced Tools health checks with clearer status logic, plain-language guidance, knowledge-base links, and hover help so warnings, advisories, and verified checks are easier to scan.
- Changed optional Google Fonts API readiness so a missing saved API key is reported as an advisory instead of a passing verified check.
- Expanded Advanced Tools with runtime manifest inspection and REST endpoints for refreshing diagnostics and running safe maintenance actions.
- Reworked Settings into shared row-board layouts for Output, Integrations, and Behavior, with contextual tab headers, compact select proxies, centralized clear/save actions, and shared passive help placement.
- Refined Advanced Tools Transfer & Recovery around a single row-action system for exports, imports, snapshots, support bundles, recovery notices, and transfer activity.
- Expanded site-transfer dry runs with import diff details, Google API key validation state, and clear notice that a rollback snapshot will be created before import.
- Documented the Settings row, status-dot, and Advanced Tools row-action design contracts so future admin UI work uses the same tokenized layout rules.
- Added compatibility aliases to structured health checks and guarded snapshot restores with the shared Advanced Tools confirmation flow.
- Exposed structured Advanced Tools action descriptors and added safe REST/admin actions for library rescans and storage scaffold repair.
- Restyled the Advanced Tools Activity and Transfer logs around the shared row-board design, including cleaner filters, card-style entries, footer controls, and consistent destructive-action styling.
- Reworded settings controls toward positive enabled-state labels, including onboarding hints, uploaded font retention, and related Behavior settings.
- Removed v1 migration-only and backward-support paths for legacy option keys, old import/log storage, retired generated CSS paths, role delivery overrides, class output aliases, stale Automatic.css recovery, and retired Bricks alias cleanup.
- Refined the admin UI around the shared surface system, warmed page-header/preview/snippet treatments, simplified collapsed library family cards to a single source pill, and moved update-channel controls into the Advanced Tools Developer release rail.
- Reworked the admin CSS token set around the Tasty Blue, Warm Amber, and Soft Cream palette, removed dead or hand-rolled card CSS, and documented the surface/three-color rules in the design guide.
- Centralized managed-storage file metadata scanning for rollback snapshots and support bundles, removing the actionable duplicate-code reported by jscpd.
- Moved Bunny Fonts cache reads to a fresh v2 transient namespace so stale pre-v2 cached family metadata is ignored.
- Removed the deprecated plaintext WP-CLI `--google-api-key=<key>` input path; use `--prompt-google-api-key` or `--google-api-key-stdin` instead.
- Removed AI-agent instruction files from Git tracking while keeping them local-only via ignore rules, and expanded ignores for local Playwright, prompt-export, report, zip, and research screenshot artifacts.

### Fixed

- Removed redundant Advanced Tools overview content, duplicate evidence lines, and repeated status details so Overview, Health, Runtime Details, and Copyable Paths do not restate the same information.
- Fixed legacy Settings links for Developer and Transfer so those workflows live under Advanced Tools instead of remaining duplicated under Settings.
- Fixed custom access rules detail spacing so expanded role and user controls use the shared Settings row gutter instead of sitting flush to the edge.
- Removed unused admin CSS and tightened Stylelint so admin spacing properties reject hardcoded numeric values in favor of Tasty design tokens.
- Added automatic rollback snapshots before settings reset, managed library deletion, transfer imports, and snapshot restores so destructive maintenance paths have a local recovery point.
- Fixed the Advanced Tools snapshots REST route so it returns persisted rollback snapshots.
- Fixed support bundles so list-shaped diagnostics such as health checks are preserved while secrets are still removed.
- Fixed Advanced Tools health badges, CLI command help, activity pagination spacing, and settings row control alignment so the admin UI stays consistent with the current design system.
- Fixed Advanced Tools self-hosted font diagnostics so hydrated upload URLs resolve back to managed relative paths before reporting missing files.
- Fixed generated CSS fallback handling so explicit role fallback stacks are preserved when a family fallback changes.
- Fixed managed-file cleanup so it also clears retained transfer export bundles and rollback snapshots while preserving unrelated settings.
- Fixed CLI and activity-log output so Google API keys and other secret-like payload fields are redacted from JSON responses.

### Added

- Added rollback snapshots for Tasty Fonts settings, role assignments, library metadata, generated CSS, and managed font files, including manual create, rename, restore, delete, retention, and automatic pre-destructive-operation checkpoints.
- Added saved site-transfer export bundle management with download, rename, protect, delete, and retention controls.
- Added sanitized support bundles for diagnostics, generated CSS, storage metadata, activity, settings, and library state without exposing Google API keys.
- Added WP-CLI parity for Advanced Tools diagnostics, generated CSS regeneration, cache clears, library rescans, site-transfer export/import, support bundles, and rollback snapshots.
- Added an Advanced Tools CLI tab with copy-ready WP-CLI commands for diagnostics, maintenance, transfers, support bundles, and snapshots.
- Added paginated Advanced Tools activity logs with account filters, search, page controls, and selectable 5, 10, 25, or 100 entry page sizes.
- Added a repo-local `bin/lint-css` Stylelint wrapper and wired the beta release helper to run CSS linting when npm dependencies are installed.
- Added CSS surface-system and Three-Color palette audits, alongside broader CSS token audit coverage, so admin CSS stays on approved design tokens.
- Added WP-CLI commands for Google API key status/save, settings reset, and plugin-managed file deletion, with redacted JSON output and prompt/stdin secret input.
- Added richer structured activity metadata for settings, roles, library actions, imports, uploads, integrations, updates, maintenance, and transfers so Advanced Tools Activity rows can be filtered, searched, and expanded with useful details.

## [1.14.0-beta.4] - 2026-04-26

### Added

- Added a repo-local `bin/lint-css` Stylelint wrapper and wired the beta release helper to run CSS linting when npm dependencies are installed.
- Added CSS surface-system and Three-Color palette audits, alongside broader CSS token audit coverage, so admin CSS stays on approved design tokens.
- Added WP-CLI commands for Google API key status/save, settings reset, and plugin-managed file deletion, with redacted JSON output and prompt/stdin secret input.
- Added richer structured activity metadata for settings, roles, library actions, imports, uploads, integrations, updates, maintenance, and transfers so Advanced Tools Activity rows can be filtered, searched, and expanded with useful details.

### Changed

- Refined the admin UI around the shared surface system, warmed page-header/preview/snippet treatments, simplified collapsed library family cards to a single source pill, and moved update-channel controls into the Advanced Tools Developer release rail.
- Reworked the admin CSS token set around the Tasty Blue, Warm Amber, and Soft Cream palette, removed dead or hand-rolled card CSS, and documented the surface/three-color rules in the design guide.
- Centralized managed-storage file metadata scanning for rollback snapshots and support bundles, removing the actionable duplicate-code reported by jscpd.
- Moved Bunny Fonts cache reads to a fresh v2 transient namespace so stale pre-v2 cached family metadata is ignored.
- Removed the deprecated plaintext WP-CLI `--google-api-key=<key>` input path; use `--prompt-google-api-key` or `--google-api-key-stdin` instead.
- Removed AI-agent instruction files from Git tracking while keeping them local-only via ignore rules, and expanded ignores for local Playwright, prompt-export, report, zip, and research screenshot artifacts.

### Fixed

- Fixed Advanced Tools self-hosted font diagnostics so hydrated upload URLs resolve back to managed relative paths before reporting missing files.
- Fixed generated CSS fallback handling so explicit role fallback stacks are preserved when a family fallback changes.
- Fixed managed-file cleanup so it also clears retained transfer export bundles and rollback snapshots while preserving unrelated settings.
- Fixed CLI and activity-log output so Google API keys and other secret-like payload fields are redacted from JSON responses.

## [1.14.0-beta.3] - 2026-04-25

### Added

- Added WP-CLI parity for Advanced Tools diagnostics, generated CSS regeneration, cache clears, library rescans, site-transfer export/import, support bundles, and rollback snapshots.
- Added an Advanced Tools CLI tab with copy-ready WP-CLI commands for diagnostics, maintenance, transfers, support bundles, and snapshots.
- Added paginated Advanced Tools activity logs with account filters, search, page controls, and selectable 5, 10, 25, or 100 entry page sizes.

### Changed

- Added compatibility aliases to structured health checks and guarded snapshot restores with the shared Advanced Tools confirmation flow.
- Exposed structured Advanced Tools action descriptors and added safe REST/admin actions for library rescans and storage scaffold repair.
- Restyled the Advanced Tools Activity and Transfer logs around the shared row-board design, including cleaner filters, card-style entries, footer controls, and consistent destructive-action styling.
- Reworded settings controls toward positive enabled-state labels, including onboarding hints, uploaded font retention, and related Behavior settings.
- Removed v1 migration-only and backward-support paths for legacy option keys, old import/log storage, retired generated CSS paths, role delivery overrides, class output aliases, stale Automatic.css recovery, and retired Bricks alias cleanup.

### Fixed

- Fixed the Advanced Tools snapshots REST route so it returns persisted rollback snapshots.
- Fixed support bundles so list-shaped diagnostics such as health checks are preserved while secrets are still removed.
- Fixed Advanced Tools health badges, CLI command help, activity pagination spacing, and settings row control alignment so the admin UI stays consistent with the current design system.

## [1.14.0-beta.2] - 2026-04-25

### Added

- Added rollback snapshots for Tasty Fonts settings, role assignments, library metadata, generated CSS, and managed font files, including manual create, rename, restore, delete, retention, and automatic pre-destructive-operation checkpoints.
- Added saved site-transfer export bundle management with download, rename, protect, delete, and retention controls.
- Added sanitized support bundles for diagnostics, generated CSS, storage metadata, activity, settings, and library state without exposing Google API keys.

### Changed

- Reworked Settings into shared row-board layouts for Output, Integrations, and Behavior, with contextual tab headers, compact select proxies, centralized clear/save actions, and shared passive help placement.
- Refined Advanced Tools Transfer & Recovery around a single row-action system for exports, imports, snapshots, support bundles, recovery notices, and transfer activity.
- Expanded site-transfer dry runs with import diff details, Google API key validation state, and clear notice that a rollback snapshot will be created before import.
- Documented the Settings row, status-dot, and Advanced Tools row-action design contracts so future admin UI work uses the same tokenized layout rules.

### Fixed

- Fixed custom access rules detail spacing so expanded role and user controls use the shared Settings row gutter instead of sitting flush to the edge.
- Removed unused admin CSS and tightened Stylelint so admin spacing properties reject hardcoded numeric values in favor of Tasty design tokens.
- Added automatic rollback snapshots before settings reset, managed library deletion, transfer imports, and snapshot restores so destructive maintenance paths have a local recovery point.

## [1.14.0-beta.1] - 2026-04-25

### Changed

- Reworked Advanced Tools into a power-user command center with Overview, Generated CSS, Developer, Transfer, and Activity tabs while preserving existing guarded maintenance and transfer workflows.
- Redesigned the Advanced Tools Overview around consistent board-and-row patterns for next-step guidance, health status, runtime details, and copyable debug paths.
- Moved generated CSS download controls into the Generated CSS panel and aligned diagnostics tab headers, spacing, typography, and button placement across the Advanced Tools dashboard.
- Expanded Advanced Tools health checks with clearer status logic, plain-language guidance, knowledge-base links, and hover help so warnings, advisories, and verified checks are easier to scan.
- Changed optional Google Fonts API readiness so a missing saved API key is reported as an advisory instead of a passing verified check.
- Expanded Advanced Tools with runtime manifest inspection and REST endpoints for refreshing diagnostics and running safe maintenance actions.

### Fixed

- Removed redundant Advanced Tools overview content, duplicate evidence lines, and repeated status details so Overview, Health, Runtime Details, and Copyable Paths do not restate the same information.
- Fixed legacy Settings links for Developer and Transfer so those workflows live under Advanced Tools instead of remaining duplicated under Settings.

## [1.13.0] - 2026-04-24

- Promoted the validated `1.13.0` beta line to stable.

### Changed

- Centralized settings save validation and REST schema generation from a shared field-definition source.
- Split the library family card into a lightweight summary shell and an on-demand hydrated details fragment.
- Refined the Settings experience with a dedicated admin-access panel, improved transfer and developer-tool states, reusable log filtering, and polished header and settings layouts.
- Routed admin menu registration, REST permissions, and updater reinstalls through a shared admin-access policy.
- Refactored duplicated hosted-provider import logic into a shared trait used by the Google Fonts and Bunny Fonts import services, while consolidating related utility helpers across library, runtime, transfer, and updater code paths.
- Consolidated repeated admin renderer and messaging logic into shared helpers so the library and studio views reuse the same formatting and render support paths.
- Updated the shared quality workflow and local pre-commit hook to run PHPStan alongside the repo-local `bin/run-jscpd` entrypoint, and documented the local git-hook setup.
- Raised the repo's enforced PHPStan level from 9 to 10, backed by shared mixed-data normalization helpers and stricter internal contracts across admin, provider, runtime, updater, repository, integration, and filesystem code paths.
- Tightened release packaging so GitHub archive builds exclude repository-only tooling, hooks, agent metadata, and other development-only files from distributable plugin zip files.
- Reworked Output Settings into focused Minimal, Variables only, Classes only, and Custom flows with grouped disclosure controls for class and variable subfeatures.
- Changed default Heading and Body fallback-only stacks from `sans-serif` to `system-ui, sans-serif`, with a one-time migration for legacy fallback-only role defaults.
- Kept Classes only generated CSS previews annotated with a display-only note explaining why the retained `:root` role variables still exist for integrations and editor parity.
- Updated Automatic.css sync opt-out handling so explicitly disabled sync stays disabled and stale Tasty-managed ACSS font values can be cleared back to ACSS defaults when no restore backup exists.
- Updated caching/font-loading and settings docs to describe Minimal output behavior, Etch role bridging, and Automatic.css editor-parity mirroring.
- Refreshed the admin UI token system, shell, masthead, role workflow, library cards, import panels, integration rows, developer tools, and preview surfaces with a denser Tasty Foundry workspace treatment.
- Shortened Studio, Library, import, integration, transfer, updater, and developer-tool labels and helper copy so operational controls are easier to scan.
- Updated CI, local hook guidance, and contributor docs to run optional npm CSS lint tooling alongside PHPStan, JS contract tests, PHP syntax checks, and jscpd.

### Added

- Added a lazy-loaded `families/card` REST fragment for expanding library cards without rendering the full detail panel up front.
- Added optional Tasty Fonts admin-access controls so administrators can delegate plugin access to selected non-administrator roles and specific users.
- Added a dry-run site transfer validation flow that stages import bundles before destructive import and surfaces transfer activity directly in the Transfer tab.
- Added `jscpd` duplicate-code scanning to the quality workflow alongside a committed `.jscpd.json` baseline.
- Added a `tasty-canvas` JavaScript contract test plus shared agent-instruction files (`AGENTS.md`, `CLAUDE.md`, and `.agents/`) for repository-local Codex and Claude guidance.
- Added a shared `bin/run-jscpd` runner, a tracked `.githooks/pre-commit` hook, and a `bin/setup-git-hooks` installer so local clones can run the duplication scan automatically on commit.
- Added root Composer PHPStan tooling plus a WordPress-aware config/bootstrap so the repo can enforce static analysis consistently in CI and local hooks.
- Added editable Heading, Body, and Monospace role fallback controls in Studio so fallback-only roles can use intentional stacks without selecting a library family.
- Added an opt-in Role Weights in Classes setting so `.font-heading`, `.font-body`, `.font-monospace`, and related role alias classes can include role weights and variation settings.
- Added Minimal-output role bridges for Etch frontends, Etch canvas data, and Gutenberg editor styles so variable-only runtime output can still apply live sitewide roles in those surfaces.
- Added editor-parity mirroring for the live Automatic.css runtime stylesheet when sitewide roles are active, even if direct Tasty-to-ACSS font-setting sync is off.
- Added the Tasty Foundry admin design-system guide plus Stylelint and CSS-token audit coverage for the committed admin CSS.
- Added a Show Activity Log behavior setting so Advanced Tools can hide the full diagnostics activity log by default while continuing to record events.

### Fixed

- Improved keyboard focus, loading announcements, and async status handling for hydrated family-card content.
- Prevented transfer imports and destructive developer tools from running while settings changes are still unsaved, and kept bundle import disabled until a dry run succeeds.
- Fixed oversized site-transfer uploads to surface a clear inline error and transfer-log entry instead of failing silently.
- Cleared site-local individual-user grants from imported admin-access settings while preserving valid role-based access grants.
- Hardened mixed-data normalization across admin actions, renderer payloads, provider clients, transfer bundles, updater responses, and runtime font planning so static analysis and runtime behavior stay aligned without loosening WordPress-facing boundaries.
- Added focused regression coverage for the new normalization helpers and refreshed contributor docs so the PHPStan level 10 workflow is documented consistently in the repo and wiki.
- Fixed fallback-only role output so generated role variables and sitewide usage rules still emit when Heading, Body, or Monospace use only a fallback stack.
- Fixed role save/autosave requests to preserve fallback values for Heading, Body, and Monospace roles.
- Fixed local admin asset version checks to clear PHP's stat cache before reading local file mtimes and hashes.
- Fixed generated CSS panel display preparation so preformatted readable snippets are preserved instead of being reformatted unconditionally.
- Improved admin accessibility by removing misleading tooltip expanded states and adding explicit labels for detected upload metadata actions.

## [1.13.0-beta.5] - 2026-04-24

### Added

- Added the Tasty Foundry admin design-system guide plus Stylelint and CSS-token audit coverage for the committed admin CSS.
- Added a Show Activity Log behavior setting so Advanced Tools can hide the full diagnostics activity log by default while continuing to record events.

### Changed

- Refreshed the admin UI token system, shell, masthead, role workflow, library cards, import panels, integration rows, developer tools, and preview surfaces with a denser Tasty Foundry workspace treatment.
- Shortened Studio, Library, import, integration, transfer, updater, and developer-tool labels and helper copy so operational controls are easier to scan.
- Updated CI, local hook guidance, and contributor docs to run optional npm CSS lint tooling alongside PHPStan, JS contract tests, PHP syntax checks, and jscpd.

### Fixed

- Improved admin accessibility by removing misleading tooltip expanded states and adding explicit labels for detected upload metadata actions.

## [1.13.0-beta.4] - 2026-04-24

### Added

- Added editable Heading, Body, and Monospace role fallback controls in Studio so fallback-only roles can use intentional stacks without selecting a library family.
- Added an opt-in Role Weights in Classes setting so `.font-heading`, `.font-body`, `.font-monospace`, and related role alias classes can include role weights and variation settings.
- Added Minimal-output role bridges for Etch frontends, Etch canvas data, and Gutenberg editor styles so variable-only runtime output can still apply live sitewide roles in those surfaces.
- Added editor-parity mirroring for the live Automatic.css runtime stylesheet when sitewide roles are active, even if direct Tasty-to-ACSS font-setting sync is off.

### Changed

- Reworked Output Settings into focused Minimal, Variables only, Classes only, and Custom flows with grouped disclosure controls for class and variable subfeatures.
- Changed default Heading and Body fallback-only stacks from `sans-serif` to `system-ui, sans-serif`, with a one-time migration for legacy fallback-only role defaults.
- Kept Classes only generated CSS previews annotated with a display-only note explaining why the retained `:root` role variables still exist for integrations and editor parity.
- Updated Automatic.css sync opt-out handling so explicitly disabled sync stays disabled and stale Tasty-managed ACSS font values can be cleared back to ACSS defaults when no restore backup exists.
- Updated caching/font-loading and settings docs to describe Minimal output behavior, Etch role bridging, and Automatic.css editor-parity mirroring.

### Fixed

- Fixed fallback-only role output so generated role variables and sitewide usage rules still emit when Heading, Body, or Monospace use only a fallback stack.
- Fixed role save/autosave requests to preserve fallback values for Heading, Body, and Monospace roles.
- Fixed local admin asset version checks to clear PHP's stat cache before reading local file mtimes and hashes.
- Fixed generated CSS panel display preparation so preformatted readable snippets are preserved instead of being reformatted unconditionally.

## [1.13.0-beta.3] - 2026-04-23

### Added

- Added `jscpd` duplicate-code scanning to the quality workflow alongside a committed `.jscpd.json` baseline.
- Added a `tasty-canvas` JavaScript contract test plus shared agent-instruction files (`AGENTS.md`, `CLAUDE.md`, and `.agents/`) for repository-local Codex and Claude guidance.
- Added a shared `bin/run-jscpd` runner, a tracked `.githooks/pre-commit` hook, and a `bin/setup-git-hooks` installer so local clones can run the duplication scan automatically on commit.
- Added root Composer PHPStan tooling plus a WordPress-aware config/bootstrap so the repo can enforce static analysis consistently in CI and local hooks.

### Changed

- Refactored duplicated hosted-provider import logic into a shared trait used by the Google Fonts and Bunny Fonts import services, while consolidating related utility helpers across library, runtime, transfer, and updater code paths.
- Consolidated repeated admin renderer and messaging logic into shared helpers so the library and studio views reuse the same formatting and render support paths.
- Updated the shared quality workflow and local pre-commit hook to run PHPStan alongside the repo-local `bin/run-jscpd` entrypoint, and documented the local git-hook setup.
- Raised the repo's enforced PHPStan level from 9 to 10, backed by shared mixed-data normalization helpers and stricter internal contracts across admin, provider, runtime, updater, repository, integration, and filesystem code paths.
- Tightened release packaging so GitHub archive builds exclude repository-only tooling, hooks, agent metadata, and other development-only files from distributable plugin zip files.

### Fixed

- Hardened mixed-data normalization across admin actions, renderer payloads, provider clients, transfer bundles, updater responses, and runtime font planning so static analysis and runtime behavior stay aligned without loosening WordPress-facing boundaries.
- Added focused regression coverage for the new normalization helpers and refreshed contributor docs so the PHPStan level 10 workflow is documented consistently in the repo and wiki.

## [1.13.0-beta.2] - 2026-04-17

### Added

- Added optional Tasty Fonts admin-access controls so administrators can delegate plugin access to selected non-administrator roles and specific users.
- Added a dry-run site transfer validation flow that stages import bundles before destructive import and surfaces transfer activity directly in the Transfer tab.

### Changed

- Refined the Settings experience with a dedicated admin-access panel, improved transfer and developer-tool states, reusable log filtering, and polished header and settings layouts.
- Routed admin menu registration, REST permissions, and updater reinstalls through a shared admin-access policy.

### Fixed

- Prevented transfer imports and destructive developer tools from running while settings changes are still unsaved, and kept bundle import disabled until a dry run succeeds.
- Fixed oversized site-transfer uploads to surface a clear inline error and transfer-log entry instead of failing silently.
- Cleared site-local individual-user grants from imported admin-access settings while preserving valid role-based access grants.

## [1.13.0-beta.1] - 2026-04-16

### Changed

- Centralized settings save validation and REST schema generation from a shared field-definition source.
- Split the library family card into a lightweight summary shell and an on-demand hydrated details fragment.

### Added

- Added a lazy-loaded `families/card` REST fragment for expanding library cards without rendering the full detail panel up front.

### Fixed

- Improved keyboard focus, loading announcements, and async status handling for hydrated family-card content.

## [1.12.0] - 2026-04-16

### Changed

- Renamed the packaged plugin directory from `etch-fonts/` to `tasty-fonts/` across release archives, install instructions, and test fixtures to match the current product branding.
- Moved Google Fonts API key material into dedicated encrypted option storage and excluded it from portable exports.
- Refined the Transfer settings UI with a dedicated tab, cleaner export and import cards, token-driven controls, and a polished bundle upload field.
- Always render the embedded Studio tools section so the Studio shell keeps its tools markup available on every page.
- Promoted the validated `1.12.0` beta line to stable.

### Added

- Added a portable Site Transfer workflow in Settings -> Transfer to export and import bundles containing managed fonts, library data, settings, and live role assignments.
- Added support for supplying a fresh Google Fonts API key during site-transfer imports so destination sites can restore search access without exporting secrets.
- Added a centralized admin design-token scale covering spacing, typography, motion, layers, component sizing, and syntax highlighting.

### Fixed

- Fixed variable-font output so registered axis defaults are omitted from generated CSS and Block Editor sync payloads when they would be redundant.
- Fixed CSS minification to preserve significant whitespace in output where collapsing it would change behavior.

## [1.12.0-beta.3] - 2026-04-16

### Added

- Added a centralized admin design-token scale covering spacing, typography, motion, layers, component sizing, and syntax highlighting.

### Changed

- Always render the embedded Studio tools section so the Studio shell keeps its tools markup available on every page.

## [1.12.0-beta.2] - 2026-04-13

### Added

- Added a portable Site Transfer workflow in Settings -> Transfer to export and import bundles containing managed fonts, library data, settings, and live role assignments.
- Added support for supplying a fresh Google Fonts API key during site-transfer imports so destination sites can restore search access without exporting secrets.

### Changed

- Moved Google Fonts API key material into dedicated encrypted option storage and excluded it from portable exports.
- Refined the Transfer settings UI with a dedicated tab, cleaner export and import cards, token-driven controls, and a polished bundle upload field.

### Fixed

- Fixed variable-font output so registered axis defaults are omitted from generated CSS and Block Editor sync payloads when they would be redundant.
- Fixed CSS minification to preserve significant whitespace in output where collapsing it would change behavior.

## [1.12.0-beta.1] - 2026-04-13

### Changed

- Renamed the packaged plugin directory from `etch-fonts/` to `tasty-fonts/` across release archives, install instructions, and test fixtures to match the current product branding.

## [1.11.0] - 2026-04-13

### Added

- Added deep Bricks Builder typography integration with managed Theme Style sync, Theme Style target modes for a Tasty-managed style, one selected style, or all Theme Styles, plus create, delete, and reset actions for the Tasty-managed Bricks Theme Style.
- Added direct Bricks control over the native `disableGoogleFonts` setting so Tasty Fonts can switch Bricks pickers into a Tasty-only font list.

### Changed

- Simplified the Bricks integration UI by folding selector exposure and builder preview loading into the main Bricks toggle, flattening the settings layout, and clarifying current Theme Style targeting and state reporting.
- Updated Bricks Theme Style sync to use the existing Tasty role variables directly for family and weight output instead of maintaining a separate Bricks alias-variable layer.
- Grouped Tasty runtime families into a dedicated top-level Bricks builder picker group so published Tasty fonts are easier to find than when mixed into Bricks standard fonts.
- Added contributor-facing repo scaffolding, including a standalone contributing guide, local setup documentation, GitHub issue and pull request templates, a security policy, a code of conduct, and an `.editorconfig`, while correcting the developer testing guide to match the current zero-dependency harness.
- Added explicit least-privilege `GITHUB_TOKEN` permissions to the shared quality and CI GitHub Actions workflows.
- Trimmed GitHub release archives down to runtime plugin files by excluding repo docs, screenshots, contributor metadata, test/build tooling, and translation source templates from `git archive` exports.
- Updated nightly release packaging to honor the same export rules as beta and stable release archives.

### Fixed

- Fixed Bricks frontend and builder canvas typography when Bricks quoted `var(...)` font-family values, including live canvas CSSOM repairs so Tasty-managed family and weight variables resolve correctly in preview and runtime output.
- Fixed Bricks Theme Style targeting, reset, and managed-style lifecycle behavior so switching between managed, selected, and all-style modes stays in sync and reset restores user Theme Styles without deleting them.
- Hardened admin hosted-font preview stylesheets by allowing only trusted Google Fonts and Bunny Fonts `https://.../css2` URLs before assigning preview `link` elements.
- Replaced admin Google and Bunny search empty-state, loading, and error rendering with DOM text nodes instead of HTML interpolation for error messages.

## [1.11.0-beta.3] - 2026-04-13

### Changed

- Added explicit least-privilege `GITHUB_TOKEN` permissions to the shared quality and CI GitHub Actions workflows.

### Fixed

- Hardened admin hosted-font preview stylesheets by allowing only trusted Google Fonts and Bunny Fonts `https://.../css2` URLs before assigning preview `link` elements.
- Replaced admin Google and Bunny search empty-state, loading, and error rendering with DOM text nodes instead of HTML interpolation for error messages.

## [1.11.0-beta.2] - 2026-04-13

### Changed

- Added contributor-facing repo scaffolding, including a standalone contributing guide, local setup documentation, GitHub issue and pull request templates, a security policy, a code of conduct, and an `.editorconfig`, while correcting the developer testing guide to match the current zero-dependency harness.

## [1.11.0-beta.1] - 2026-04-12

### Added

- Added deep Bricks Builder typography integration with managed Theme Style sync, Theme Style target modes for a Tasty-managed style, one selected style, or all Theme Styles, plus create, delete, and reset actions for the Tasty-managed Bricks Theme Style.
- Added direct Bricks control over the native `disableGoogleFonts` setting so Tasty Fonts can switch Bricks pickers into a Tasty-only font list.

### Changed

- Simplified the Bricks integration UI by folding selector exposure and builder preview loading into the main Bricks toggle, flattening the settings layout, and clarifying current Theme Style targeting and state reporting.
- Updated Bricks Theme Style sync to use the existing Tasty role variables directly for family and weight output instead of maintaining a separate Bricks alias-variable layer.
- Grouped Tasty runtime families into a dedicated top-level Bricks builder picker group so published Tasty fonts are easier to find than when mixed into Bricks standard fonts.

### Fixed

- Fixed Bricks frontend and builder canvas typography when Bricks quoted `var(...)` font-family values, including live canvas CSSOM repairs so Tasty-managed family and weight variables resolve correctly in preview and runtime output.
- Fixed Bricks Theme Style targeting, reset, and managed-style lifecycle behavior so switching between managed, selected, and all-style modes stays in sync and reset restores user Theme Styles without deleting them.

## [1.10.0] - 2026-04-12

### Added

- Added Automatic.css font-weight sync alongside the managed heading/body font-family mapping, including stored restore points plus Gutenberg and Etch canvas bridges for the active Automatic.css runtime stylesheet and editor-safe inline CSS.
- Added role weight variable output controls so generated CSS can expose `--font-heading-weight`, `--font-body-weight`, and `--font-monospace-weight` alongside the existing role variables when variable font output is enabled.
- Added a WordPress-style `readme.txt` with distribution metadata, FAQs, screenshot references, and a clearer multisite stance for installs outside the GitHub README flow.
- Added scheduled Google Fonts API key revalidation so stale saved key status now queues a lightweight cron recheck instead of remaining indefinitely stale.

### Changed

- Improved Google and Bunny variable import flows so manual entries accept named weight aliases, switch between “variants” and “styles” terminology in variable mode, and keep manual fields plus chip selections synchronized with canonical stored tokens.
- Improved role-axis and preview-axis editors for single-axis variable fonts, tightened local upload row layout spacing, derived delivery-profile variant counts from actual face data, and updated the admin chrome with a custom sidebar icon plus linked Tasty Fonts masthead logo.
- Updated the Automatic.css integration panel to show current and desired font-family plus font-weight mappings, and keep the required role-weight variables enabled while sync is active.
- Hardened editor theme JSON preset injection so the plugin preserves valid incoming schema versions and stops forcing a fallback schema version when the source data omits one.
- Clarified optional integration messaging for Etch, Automatic.css, Bricks, and Oxygen, and explicitly blocked network-wide activation in favor of per-site activation on multisite installs.
- Added optional preload `Link` header generation behind a filter for advanced performance setups while keeping HTML preload hints as the default delivery mode.
- Updated the GitHub updater to support an optional token-backed API request header and to back off temporarily after GitHub rate-limit responses.
- Documented the shared clear-select button helper so icon-only reset controls keep a consistent accessibility and admin-client contract wherever the renderer reuses them.
- Rewrote the GitHub README and the WordPress-style `readme.txt` to better highlight the plugin’s typography workflow, integration value, and agency-friendly product benefits.
- Promoted the validated `1.10.0` beta line to the stable rail after the final Automatic.css, variable-import, and admin polish pass.

### Fixed

- Improved admin accessibility feedback by promoting blocking import and upload errors to assertive announcements while keeping success and progress updates polite.
- Improved upload-builder accessibility with explicit label associations for cloned family, face, and axis controls, plus clearer grouped row context for assistive technologies.
- Improved disclosure accessibility across the admin by labeling revealed panels, moving focus into newly opened content, and announcing expansions in a controlled live region.
- Improved muted control-text contrast in the admin token palette and removed `autocomplete="off"` from the Adobe Fonts Project ID field.
- Standardized the icon-only clear-button accessibility pattern behind a shared renderer helper so new filter and selector clear controls keep the same `aria-label` plus decorative glyph treatment.
- Restricted deferred generated CSS regeneration to cron execution contexts so the scheduled action no longer runs when triggered from unintended request paths.
- Restricted classic settings saves to an explicit allowlist of expected scalar and boolean fields before forwarding values into the settings save pipeline, so unrelated `$_POST` payload keys no longer leak into plugin settings handling.
- Cleared stale saved role-axis values when switching draft roles from variable families back to static families, and avoided duplicate canvas stylesheet injection when an equivalent runtime stylesheet is already present.

## [1.10.0-beta.5] - 2026-04-12

### Changed

- Reworked the WordPress-style `readme.txt` copy to better highlight the plugin's typography workflow, agency-friendly value, and product benefits while keeping the standard distribution format intact.

## [1.10.0-beta.4] - 2026-04-12

### Changed

- Rewrote the GitHub README to lead with product benefits, workflow clarity, and integration value so the project presents more like a polished plugin landing page than a developer-first repository overview.

## [1.10.0-beta.3] - 2026-04-12

### Changed

- Documented the shared clear-select button helper so icon-only reset controls keep a consistent accessibility and admin-client contract wherever the renderer reuses them.

### Fixed

- Restricted classic settings saves to an explicit allowlist of expected scalar and boolean fields before forwarding values into the settings save pipeline, so unrelated `$_POST` payload keys no longer leak into plugin settings handling.

## [1.10.0-beta.2] - 2026-04-11

### Added

- Added a WordPress-style `readme.txt` with distribution metadata, FAQs, screenshots references, and a clearer multisite stance for plugin installs outside the GitHub-only README flow.
- Added scheduled Google Fonts API key revalidation so stale saved key status now queues a lightweight cron recheck instead of remaining indefinitely stale.

### Changed

- Hardened editor theme JSON preset injection so the plugin preserves valid incoming schema versions and stops forcing a fallback schema version when the source data omits one.
- Clarified optional integration messaging for Etch, Automatic.css, Bricks, and Oxygen, and explicitly blocked network-wide activation in favor of per-site activation on multisite installs.
- Added optional preload `Link` header generation behind a filter for advanced performance setups while keeping HTML preload hints as the default delivery mode.
- Updated the GitHub updater to support an optional token-backed API request header and to back off temporarily after GitHub rate-limit responses.

### Fixed

- Restricted deferred generated CSS regeneration to cron execution contexts so the scheduled action no longer runs when triggered from unintended request paths.

## [1.10.0-beta.1] - 2026-04-11

### Fixed

- Improved admin accessibility feedback by promoting blocking import and upload errors to assertive announcements while keeping success and progress updates polite.
- Improved upload-builder accessibility with explicit label associations for cloned family, face, and axis controls, plus clearer grouped row context for assistive technologies.
- Improved disclosure accessibility across the admin by labeling revealed panels, moving focus into newly opened content, and announcing expansions in a controlled live region.
- Improved muted control-text contrast in the admin token palette and removed `autocomplete="off"` from the Adobe Fonts Project ID field.
- Standardized the icon-only clear-button accessibility pattern behind a shared renderer helper so new filter and selector clear controls keep the same `aria-label` plus decorative glyph treatment.

## [1.9.0] - 2026-04-11

### Added

- Added per-user REST action cooldowns around Google/Bunny family lookups, provider imports, and local uploads so repeated admin actions now return a proper `429` instead of hammering remote services or duplicate upload flows.
- Added blog-scoped transient key handling for site-specific runtime, provider, updater, and admin caches so multisite and shared object-cache installs do not bleed plugin cache state across sites.
- Added Apache `.htaccess` hardening alongside the managed `uploads/fonts` scaffold and published SHA-256 checksum assets in the stable, beta, and nightly release workflows.
- Added transparent at-rest encryption for the stored Google Fonts API key using WordPress salt material and Sodium when the runtime supports it.
- Added CSP-friendly inline-style nonce hooks for generated runtime and admin preview CSS so stricter content-security-policy deployments can attach a nonce without forking core output.

### Changed

- Moved plugin boot from `plugins_loaded` priority `0` to the default priority so peer plugins and host integrations can initialize before Tasty Fonts wires itself up.
- Switched generated CSS file versioning from CRC32b to SHA-256-derived hashes, while keeping short version tokens for cache-busting URLs.
- Tightened Etch canvas query handling so the `etch` query parameter is only honored for logged-in users who can edit posts, and updated admin redirects to exit directly after a dedicated pre-exit action for test coverage.
- Updated the GitHub updater to require a published checksum asset for each release and verify the downloaded ZIP before WordPress installs it.
- Promoted the validated `1.9.0` security hardening line to the stable rail for general release availability, keeping the same runtime, updater, CSP, preview-sanitization, and admin-throttling protections published in `1.9.0-beta.1`.

### Fixed

- Fixed the public Etch canvas bridge path so anonymous requests can no longer trigger the extra runtime canvas assets by sending an arbitrary `etch` query parameter.
- Fixed preview sentence persistence by stripping tags, removing control characters, collapsing whitespace, and capping stored text length before the value is reused in admin and bootstrap payloads.
- Fixed admin and runtime compatibility on older supported PHP environments by avoiding literal `true|WP_Error` return types in shared controller code.

## [1.9.0-beta.1] - 2026-04-11

### Added

- Added per-user REST action cooldowns around Google/Bunny family lookups, provider imports, and local uploads so repeated admin actions now return a proper `429` instead of hammering remote services or duplicate upload flows.
- Added blog-scoped transient key handling for site-specific runtime, provider, updater, and admin caches so multisite and shared object-cache installs do not bleed plugin cache state across sites.
- Added Apache `.htaccess` hardening alongside the managed `uploads/fonts` scaffold and published SHA-256 checksum assets in the stable, beta, and nightly release workflows.
- Added transparent at-rest encryption for the stored Google Fonts API key using WordPress salt material and Sodium when the runtime supports it.
- Added CSP-friendly inline-style nonce hooks for generated runtime and admin preview CSS so stricter content-security-policy deployments can attach a nonce without forking core output.

### Changed

- Moved plugin boot from `plugins_loaded` priority `0` to the default priority so peer plugins and host integrations can initialize before Tasty Fonts wires itself up.
- Switched generated CSS file versioning from CRC32b to SHA-256-derived hashes, while keeping short version tokens for cache-busting URLs.
- Tightened Etch canvas query handling so the `etch` query parameter is only honored for logged-in users who can edit posts, and updated admin redirects to exit directly after a dedicated pre-exit action for test coverage.
- Updated the GitHub updater to require a published checksum asset for each release and verify the downloaded ZIP before WordPress installs it.

### Fixed

- Fixed the public Etch canvas bridge path so anonymous requests can no longer trigger the extra runtime canvas assets by sending an arbitrary `etch` query parameter.
- Fixed preview sentence persistence by stripping tags, removing control characters, collapsing whitespace, and capping stored text length before the value is reused in admin and bootstrap payloads.
- Fixed admin and runtime compatibility on older supported PHP environments by avoiding literal `true|WP_Error` return types in shared controller code.

## [1.8.0] - 2026-04-11

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
- Updated the local release helper and release-process documentation so beta promotion can start from the current `main` dev state without auto-advancing `main` to the next dev line.
- Promoted the validated `1.8.0` beta line to the stable release rail after the final beta verification pass.

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
- Fixed the Settings > Developer panel so destructive action rows keep their descriptions and buttons aligned without the oversized gaps shown in the previous layout.

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

- Made `Show Onboarding Hints` control settings helper descriptions consistently at render time across Output, Integrations, and Behavior instead of relying only on CSS hiding.
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
