# Advanced Tools

Inspect the generated stylesheet, review system details, and use built-in diagnostics.

In the current `1.7.0` beta line, this page works alongside the new `Settings -> Developer` tab. Advanced Tools is primarily for inspection and review. Developer is where you intentionally reset or rebuild state.

## Use This Page When

- you need to inspect what the plugin is actually serving
- you want storage and generated asset details without leaving WordPress
- you need an audit trail of imports, settings changes, and runtime refreshes

## Steps

### 1. Review Generated CSS

The `Generated CSS` panel shows the current runtime stylesheet output. Use it to:

- confirm that sitewide delivery is producing the expected rules
- inspect minified versus readable output
- copy snippets for debugging

If sitewide delivery is off, generated runtime CSS may not be available yet.

### 2. Download Generated CSS

Use the built-in download action when you want the current generated stylesheet as a file for review or comparison.

### 3. Inspect System Details

The `System Details` panel surfaces diagnostics such as:

- storage paths
- generated CSS request URL
- generated CSS size and timestamp
- delivery mode and related runtime metadata

Copyable diagnostic values can be copied directly from the UI.

### 4. Review Activity

The activity log helps explain what the plugin has done recently, including:

- uploads and rescans
- provider imports
- delivery changes
- settings changes
- generated asset refreshes
- Block Editor sync issues

### 5. Know When To Use Developer Instead

If you need to change state rather than inspect it, move to `Settings -> Developer`.

That tab is now the home for:

- clearing plugin caches and forcing generated asset rebuilds
- resetting suppressed notices
- resetting integration detection
- restoring plugin settings defaults
- wiping the managed font library during testing or support cleanup

## Notes

- The generated stylesheet is normally written to `wp-content/uploads/fonts/.generated/tasty-fonts.css` when file delivery is available.
- The plugin can fall back to inline CSS if file delivery is disabled or unavailable.
- Diagnostics are especially useful when runtime output looks stale or provider imports appear correct in the library but not on the site.
- In the `1.7.x` beta cycle, Advanced Tools and Developer are intentionally separated so read-only inspection stays safer than maintenance and reset actions.

## Related Docs

- [Settings](settings.md)
- [Generated CSS](troubleshooting/generated-css.md)
- [Imports And Deliveries](troubleshooting/imports-and-deliveries.md)
