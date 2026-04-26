# Tasty Custom Fonts Documentation

This is the versioned documentation hub for the current plugin codebase.

## Current Release Track

`1.14.0` is the current stable release. If you want to test upcoming prerelease builds, set `Advanced Tools → Developer → Update Channel` to `Beta` or `Nightly`.

These docs describe the current plugin behavior in full, covering the shipping features, release channels, variable font controls, Site Transfer, rollback snapshots, WP-CLI, and the full integration surface.

---

## What's New In 1.14.0

- **[Reworked Advanced Tools](Advanced-Tools)** — six-tab power-user command center: Overview, Generated CSS, Developer, Transfer, Activity, and CLI. Developer maintenance and Transfer workflows moved here from Settings.
- **[Rollback Snapshots](Advanced-Tools#rollback-snapshots)** — automatic safety checkpoints are created before every destructive operation (settings reset, import, snapshot restore). You can also create, rename, restore, and delete snapshots manually from the Developer tab.
- **[Support Bundles](Advanced-Tools#support-bundles)** — one-click sanitized export of diagnostics, generated CSS, activity, settings, and library state for debugging and agency handoffs. Google API keys are never included.
- **[Full WP-CLI Parity](Advanced-Tools#wp-cli)** — commands for diagnostics, CSS regeneration, cache clears, library rescans, site-transfer export/import, support bundles, rollback snapshots, and Google API key management.
- **[Paginated Activity Log](Advanced-Tools#activity)** — search by text or event type, filter by actor, and choose page sizes of 5, 10, 25, or 100 entries in Advanced Tools → Activity.
- **[Settings streamlined to 3 tabs](Settings)** — Output, Integrations, and Behavior. Update Channel moved to `Advanced Tools → Developer`.
- **[Improved health checks](Advanced-Tools#overview)** — plain-language guidance, knowledge-base links, and hover help across all health check rows.

---

## What's New In 1.13.0

- **[Admin Access Controls](Settings#admin-access)** — administrators can now delegate Tasty Fonts access to selected non-administrator roles and specific users from `Settings → Behavior → Admin Access`.
- **[Dry-Run Transfer Validation](Site-Transfer#step-2-dry-run-the-bundle)** — the Transfer import flow now requires a successful dry-run validation before the destructive import step can be armed, protecting destination sites from bad bundles.
- **[Fallback-Only Role Controls](Deploy-Fonts#set-per-role-fallback)** — Heading, Body, and Monospace roles now have editable fallback stack controls in Studio so a role can output an intentional font-family stack without forcing a library family.
- **[Role Weights in Classes opt-in](Settings#role-weights-in-classes)** — a new setting lets `.font-heading`, `.font-body`, `.font-monospace`, and alias classes include role weight and variation settings in their output.
- **[Output Presets reworked](Settings#output-preset)** — Output Settings now offer four focused presets: Minimal, Variables only, Classes only, and Custom, each with grouped disclosure sub-controls.
- **[Show Activity Log behavior setting](Settings#show-activity-log)** — Advanced Tools can now hide the full diagnostics activity log by default while still recording events behind the scenes.
- **Refreshed Tasty Foundry admin UI** — denser workspace treatment across library cards, import panels, Studio controls, and preview surfaces.
- Default Heading and Body fallback stacks changed from `sans-serif` to `system-ui, sans-serif`.

---

## Quick Paths By Audience

### I'm brand new to this plugin
1. Read [Concepts](Concepts) to build a mental model.
2. Follow [Getting Started](Getting-Started) for a first-run walkthrough.
3. Browse the [FAQ](FAQ) for common beginner questions.
4. Keep the [Glossary](Glossary) handy for any unfamiliar terms.

### I'm a WordPress user who knows what I'm doing
- Jump straight to [Getting Started](Getting-Started) for the install and activation steps.
- Use [Deploy Fonts](Deploy-Fonts) and [Font Library](Font-Library) as daily reference guides.
- See [Settings](Settings) for the full output and integration controls.

### I'm a developer who wants to understand or extend the plugin
- Start with [Contributing](Contributing) for setup, verification, and PR expectations.
- Read [Architecture](Architecture) for a service-layer overview.
- Read [Local Setup](Local-Setup) for local WordPress environment notes and common pitfalls.
- Read [Testing](Testing) to run and add tests.
- Check the [FAQ — Developer Questions](FAQ#developer-questions) section for code-level answers.
- See [Release Process](Release-Process) and [Translations](Translations) for contribution workflows.

### I'm troubleshooting a problem
- [FAQ](FAQ) covers the most common issues inline.
- [Imports And Deliveries](Troubleshooting-Imports-and-Deliveries) — font shows in library but not on site.
- [Generated CSS](Troubleshooting-Generated-CSS) — runtime output looks stale or missing.
- [Local Development](Troubleshooting-Local-Development) — Block Editor sync failing on a local environment.

---

## User Guides

- [Concepts](Concepts) — the three core ideas the whole plugin is built around
- [Getting Started](Getting-Started) — install, activate, and publish your first font pairing
- [Deploy Fonts](Deploy-Fonts) — draft roles, preview workspace, and applying changes sitewide
- [Font Library](Font-Library) — browse families, switch delivery profiles, and manage publish state
- [Settings](Settings) — output model, integrations, behavior, and developer maintenance
- [Site Transfer](Site-Transfer) — move your full Tasty Fonts setup between sites with one portable bundle
- [Advanced Tools](Advanced-Tools) — inspect generated CSS, system details, and activity history

## Guides

- [GDPR And Font Privacy](GDPR) — understand what each delivery method sends to third parties and how to achieve a compliant setup
- [Caching And Font Loading](Caching-And-Font-Loading) — get the best font delivery performance using every control the plugin provides

## Font Sources

- [Local Fonts](Provider-Local-Fonts) — upload files or rescan the uploads directory
- [Custom CSS URL Imports](Provider-Custom-CSS) — import WOFF2/WOFF faces from a public HTTPS `@font-face` stylesheet
- [Google Fonts](Provider-Google-Fonts) — self-hosted or CDN delivery with API key search
- [Bunny Fonts](Provider-Bunny-Fonts) — GDPR-friendly alternative, no API key needed
- [Adobe Fonts](Provider-Adobe-Fonts) — use an existing Adobe CC web project

## Troubleshooting

- [Imports And Deliveries](Troubleshooting-Imports-and-Deliveries)
- [Generated CSS](Troubleshooting-Generated-CSS)
- [Local Development](Troubleshooting-Local-Development)

## Reference

- [FAQ](FAQ) — frequently asked questions for beginners and developers
- [Glossary](Glossary) — definitions for every key term used across the docs

## Developer Docs

- [Architecture](Architecture) — service layers, runtime flow, and extension points
- [Testing](Testing) — run and write PHP and JavaScript tests
- [Local Setup](Local-Setup) — local WordPress options, manual installs, and development pitfalls
- [Release Process](Release-Process) — tag, build, and publish a release
- [Translations](Translations) — maintain the POT template and text domain

## Contributing

- [Contributing](Contributing) — contributor workflow, verification commands, and good-first entry points
- [Security Policy](Security) — private vulnerability reporting and scope
- [Code Of Conduct](Code-of-Conduct) — community expectations for contributors
- [Local Setup](Local-Setup) — local development environments and troubleshooting

## What's New

- [Changelog](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/blob/main/CHANGELOG.md)

## Notes

- The docs in `main` always describe the current plugin behavior.
- The docs cover the `1.14.0` release, including the reworked Advanced Tools, rollback snapshots, support bundles, WP-CLI parity, Site Transfer, Google Fonts API key encryption, variable-font axis-default cleanup, custom CSS URL imports, and all existing Google, Bunny, Adobe, and integration workflows.
- User-facing screenshots can be added later under the repo `screenshots/` directory.
- The top-level [`README.md`](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/blob/main/README.md) stays shorter and links here for full guidance.
