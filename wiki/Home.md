# Tasty Custom Fonts Documentation

This is the versioned documentation hub for the current plugin codebase.

## Current Release Track

`1.12.0-beta.2` is the current release. This is a pre-release beta — if you need a fully stable install for production, set `Settings → Behavior → Update Channel` to `Stable` and reinstall from that channel.

These docs describe the current plugin behavior in full, covering the shipping features, release channels, variable font controls, Site Transfer, and the full integration surface.

---

## What's New In 1.12.0 Beta

- **[Site Transfer](Site-Transfer)** — export your entire Tasty Fonts setup (library, settings, role assignments, and font files) as a portable ZIP bundle and import it on any other site running 1.12.0-beta.2 or later.
- **Google Fonts API key encryption** — the API key is now stored in a dedicated encrypted WordPress option (`tasty_fonts_google_api_key_data`) that is isolated from the main settings record and never exported in transfer bundles.
- **Variable font axis-default cleanup** — `font-variation-settings` is now omitted from generated CSS and Block Editor sync payloads when a role axis value matches the font's registered default, keeping output clean and non-redundant.
- **CSS minification fix** — significant whitespace in generated output is now preserved correctly so minification no longer collapses rules in ways that could change browser behavior.

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
- The docs cover the `1.12.0-beta.2` release, including Site Transfer, Google Fonts API key encryption, variable-font axis-default cleanup, and all existing Google, Bunny, Adobe, and integration workflows.
- User-facing screenshots can be added later under the repo `screenshots/` directory.
- The top-level [`README.md`](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/blob/main/README.md) stays shorter and links here for full guidance.
