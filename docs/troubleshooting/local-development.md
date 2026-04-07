# Local Development

Troubleshoot local environment behavior, especially Block Editor Font Library sync and loopback/TLS issues.

## Use This Page When

- you are running the plugin on a local or private development site
- Block Editor Font Library sync fails or stays off by default
- you see certificate verification or loopback request problems

## Steps

### 1. Check The Behavior Setting

Open `Settings -> Behavior` and review `Enable Block Editor Font Library Sync`.

On likely local environments, the plugin defaults this off because the sync depends on background requests back to the same site.

### 2. Review Activity And Notices

Use `Advanced Tools` and on-page notices to check for:

- cURL 60 or certificate verification errors
- loopback request failures
- sync attempts that fail only on local HTTPS setups

### 3. Decide Whether To Keep Sync Enabled

Keep Block Editor sync enabled only when your local environment can successfully complete authenticated background requests back to itself without trust failures.

If it cannot, disable it locally and continue using the plugin’s own runtime and preview flow.

## Notes

- Local development issues usually affect Block Editor Font Library sync more than the plugin’s own runtime CSS generation.
- The plugin intentionally surfaces guidance for local environments where certificate trust is the real blocker.
- Frontend, preview, and Etch runtime output can still work correctly even when Block Editor sync is off.

## Related Docs

- [Settings](../settings.md)
- [Advanced Tools](../advanced-tools.md)
- [Architecture](../developer/architecture.md)
