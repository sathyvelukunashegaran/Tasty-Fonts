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
- rescan the font library and refresh generated assets
- repair the storage scaffold
- re-run integration detection
- restore dismissed notices
- reset plugin settings while preserving the managed font library
- wipe the managed font library during testing or support cleanup

Destructive actions require typed confirmation. Save pending settings before running developer tools.

### CLI

The `CLI` tab lists copy-ready WP-CLI commands for diagnostics, generated asset maintenance, transfer bundles, support bundles, and rollback snapshots.

### Transfer

The `Transfer` tab contains portable site transfer workflows:

- export a bundle containing settings, live roles, library metadata, and managed font files
- dry-run an import bundle before replacement
- import a validated bundle to replace the current Tasty Fonts state
- review transfer-specific activity

Google API keys, generated CSS, logs, and transient runtime state are excluded from transfer bundles.

### Activity

The `Activity` tab shows recent scans, imports, deletes, settings changes, generated asset refreshes, transfer dry-run results, import outcomes, and sync issues. Rows stay compact by default, then expand to show audit and troubleshooting details such as:

- the outcome and source area
- affected fonts, deliveries, settings, or assets
- counts for imported, skipped, removed, or refreshed items
- error codes and recovery links when an event is actionable

Use the account filter to review changes by actor, or search for names, event types, settings, error codes, and detail values. The log keeps the newest 100 entries.

If the Activity tab says the log is hidden, enable `Settings -> Behavior -> Show Activity Log`. The plugin records events regardless of whether the panel is visible, so you can re-enable the setting at any time to view past history.

## WP-CLI

Advanced Tools also exposes command-line equivalents for maintenance and support workflows. The same commands are available from the `CLI` tab with copy buttons.

```bash
wp tasty-fonts doctor
wp tasty-fonts doctor --format=json
wp tasty-fonts google-api-key status
wp tasty-fonts google-api-key save
wp tasty-fonts css regenerate
wp tasty-fonts cache clear
wp tasty-fonts library rescan
wp tasty-fonts settings reset --yes
wp tasty-fonts files delete --yes
wp tasty-fonts transfer export
wp tasty-fonts transfer import /path/to/tasty-fonts-transfer.zip --dry-run --prompt-google-api-key
wp tasty-fonts transfer import /path/to/tasty-fonts-transfer.zip --yes --prompt-google-api-key
wp tasty-fonts support-bundle
wp tasty-fonts support-bundle --format=json
wp tasty-fonts snapshot create
wp tasty-fonts snapshot list --format=json
wp tasty-fonts snapshot restore <snapshot-id> --yes
```

Destructive CLI actions require `--yes`. Transfer imports and snapshot restores still create rollback snapshots first, matching the admin UI safety behavior.

Google API key commands never print the stored key. Use `google-api-key save` or transfer import with `--prompt-google-api-key` for hidden interactive input. Automation can pipe a key with `--google-api-key-stdin`; the older direct `--google-api-key=<key>` flag has been removed because shells may store it in history.

`wp tasty-fonts files delete --yes` removes plugin-managed font files, generated CSS, retained transfer export bundles, and rollback snapshots, then recreates the empty storage scaffold. It does not scan arbitrary temporary directories or delete media outside Tasty Fonts storage.

## Notes

- The generated stylesheet is normally written to `wp-content/uploads/fonts/.generated/tasty-fonts.css` when file delivery is available.
- The plugin can fall back to inline CSS if file delivery is disabled or unavailable.
- Diagnostics are especially useful when runtime output looks stale or provider imports appear correct in the library but not on the site.
- REST clients can discover Advanced Tools actions from the structured `tool_actions` payload and run safe maintenance actions through `tools/action`.
- Maintenance actions are guarded but can change state immediately.

## Related Docs

- [Settings](Settings)
- [Site Transfer](Site-Transfer)
- [Generated CSS](Troubleshooting-Generated-CSS)
- [Imports And Deliveries](Troubleshooting-Imports-and-Deliveries)
- [FAQ](FAQ)
