# Tasty Custom Fonts Documentation

This is the versioned documentation hub for the current plugin codebase.

## Current Release Track

`1.8.0` is the current stable release. `main` now tracks the next development line as `1.9.0-dev`.

These docs describe the stable `1.8.0` release in full, covering all shipping features including variable font support, the three-rail update channel, unicode range controls, and the complete settings surface.

---

## Quick Paths By Audience

### I'm brand new to this plugin
1. Read [Concepts](concepts.md) to build a mental model.
2. Follow [Getting Started](getting-started.md) for a first-run walkthrough.
3. Browse the [FAQ](faq.md) for common beginner questions.
4. Keep the [Glossary](glossary.md) handy for any unfamiliar terms.

### I'm a WordPress user who knows what I'm doing
- Jump straight to [Getting Started](getting-started.md) for the install and activation steps.
- Use [Deploy Fonts](deploy-fonts.md) and [Font Library](font-library.md) as daily reference guides.
- See [Settings](settings.md) for the full output and integration controls.

### I'm a developer who wants to understand or extend the plugin
- Read [Architecture](developer/architecture.md) for a service-layer overview.
- Read [Testing](developer/testing.md) to run and add tests.
- Check the [FAQ — Developer Questions](faq.md#developer-questions) section for code-level answers.
- See [Release Process](developer/release-process.md) and [Translations](developer/translations.md) for contribution workflows.

### I'm troubleshooting a problem
- [FAQ](faq.md) covers the most common issues inline.
- [Imports And Deliveries](troubleshooting/imports-and-deliveries.md) — font shows in library but not on site.
- [Generated CSS](troubleshooting/generated-css.md) — runtime output looks stale or missing.
- [Local Development](troubleshooting/local-development.md) — Block Editor sync failing on a local environment.

---

## User Guides

- [Concepts](concepts.md) — the three core ideas the whole plugin is built around
- [Getting Started](getting-started.md) — install, activate, and publish your first font pairing
- [Deploy Fonts](deploy-fonts.md) — draft roles, preview workspace, and applying changes sitewide
- [Font Library](font-library.md) — browse families, switch delivery profiles, and manage publish state
- [Settings](settings.md) — output model, integrations, behavior, and developer maintenance
- [Advanced Tools](advanced-tools.md) — inspect generated CSS, system details, and activity history

## Font Sources

- [Local Fonts](providers/local-fonts.md) — upload files or rescan the uploads directory
- [Google Fonts](providers/google-fonts.md) — self-hosted or CDN delivery with API key search
- [Bunny Fonts](providers/bunny-fonts.md) — GDPR-friendly alternative, no API key needed
- [Adobe Fonts](providers/adobe-fonts.md) — use an existing Adobe CC web project

## Troubleshooting

- [Imports And Deliveries](troubleshooting/imports-and-deliveries.md)
- [Generated CSS](troubleshooting/generated-css.md)
- [Local Development](troubleshooting/local-development.md)

## Reference

- [FAQ](faq.md) — frequently asked questions for beginners and developers
- [Glossary](glossary.md) — definitions for every key term used across the docs

## Developer Docs

- [Architecture](developer/architecture.md) — service layers, runtime flow, and extension points
- [Testing](developer/testing.md) — run and write PHP and JavaScript tests
- [Release Process](developer/release-process.md) — tag, build, and publish a release
- [Translations](developer/translations.md) — maintain the POT template and text domain

## What's New

- [Changelog](../CHANGELOG.md)

## Notes

- The docs in `main` always describe the current plugin behavior.
- The docs cover the stable `1.8.0` release, including all new features introduced in this line.
- User-facing screenshots can be added later under the repo `screenshots/` directory.
- The top-level [`README.md`](../README.md) stays shorter and links here for full guidance.
