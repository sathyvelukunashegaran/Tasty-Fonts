# Advanced Tools

Inspect runtime output, run guarded maintenance, move site bundles, and review activity from one power-user command center.

> **Beginner context:** you do not need Advanced Tools for normal font management. Use it when you need to understand what Tasty Fonts is serving, repair stale output, or prepare a support/debug handoff.

## Use This Page When

- you need to inspect what the plugin is actually serving
- runtime output looks stale or different from the library state
- you want storage and generated asset details without leaving WordPress
- you need to clear caches, rebuild generated CSS, or re-run integration detection
- you need to export, dry-run, or import a site transfer bundle
- you need an audit trail of imports, settings changes, and runtime refreshes

## Sections

### Overview

The overview summarizes generated CSS readiness, diagnostic warnings, activity count, and library metrics. It also includes copyable system diagnostics such as:

- storage paths
- generated CSS request URL
- generated CSS size and timestamp
- delivery mode and related runtime metadata

Use it as the first stop before drilling into a specific tool.

### Generated CSS

The `Generated CSS` panel shows the current runtime stylesheet output. Use it to:

- confirm that sitewide delivery is producing the expected rules
- inspect minified versus readable output
- verify self-hosted `@font-face` rules point to your own uploads directory
- copy snippets for debugging
- download the generated stylesheet for review or comparison

If sitewide delivery is off, generated runtime CSS may not be available yet.

### Developer

The `Developer` tab contains guarded maintenance actions that run immediately:

- clear plugin caches and rebuild assets
- regenerate only the generated CSS file
- re-run integration detection
- restore dismissed notices
- reset plugin settings while preserving the managed font library
- wipe the managed font library during testing or support cleanup

Destructive actions require typed confirmation. Save pending settings before running developer tools.

### Transfer

The `Transfer` tab contains portable site transfer workflows:

- export a bundle containing settings, live roles, library metadata, and managed font files
- dry-run an import bundle before replacement
- import a validated bundle to replace the current Tasty Fonts state
- review transfer-specific activity

Google API keys, generated CSS, logs, and transient runtime state are excluded from transfer bundles.

### Activity

The `Activity` tab shows recent scans, imports, deletes, settings changes, generated asset refreshes, transfer dry-run results, import outcomes, and sync issues. The log can be searched and filtered when visible.

If the Activity tab says the log is hidden, enable `Settings -> Behavior -> Show Activity Log`. The plugin records events regardless of whether the panel is visible, so you can re-enable the setting at any time to view past history.

## Notes

- The generated stylesheet is normally written to `wp-content/uploads/fonts/.generated/tasty-fonts.css` when file delivery is available.
- The plugin can fall back to inline CSS if file delivery is disabled or unavailable.
- Diagnostics are especially useful when runtime output looks stale or provider imports appear correct in the library but not on the site.
- Maintenance actions are guarded but can change state immediately.

## Related Docs

- [Settings](Settings)
- [Site Transfer](Site-Transfer)
- [Generated CSS](Troubleshooting-Generated-CSS)
- [Imports And Deliveries](Troubleshooting-Imports-and-Deliveries)
- [FAQ](FAQ)
