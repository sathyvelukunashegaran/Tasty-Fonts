# Local Development

Troubleshoot local environment behavior, especially Block Editor Font Library sync and loopback/TLS issues.

## Use This Page When

- you are running the plugin on a local or private development site
- Block Editor Font Library sync fails or stays off by default
- you see certificate verification or loopback request problems

## What Works and What Does Not on Local Environments

Most of the plugin works perfectly on local development:

| Feature | Works on local? |
|---|---|
| Uploading font files | ✓ Yes |
| Importing Google / Bunny / Adobe | ✓ Yes (with internet access) |
| Frontend runtime CSS | ✓ Yes |
| Admin preview workspace | ✓ Yes |
| Etch canvas delivery | ✓ Yes |
| Block Editor Font Library Sync | ✗ Usually no (loopback TLS) |

Block Editor sync is the main problem area. Everything else runs normally.

---

## Steps

### 1. Check The Integrations Setting

Open `Settings → Integrations` and review `Block Editor Font Library Sync`.

On likely local environments, the plugin defaults this off because the sync depends on background requests back to the same site.

If the setting is off and you are on a local environment, that is the correct and expected state. Leave it off.

### 2. Understand Why Sync Fails On Local

The Block Editor sync sends authenticated HTTP requests from the server back to its own WordPress REST API. If the site runs on HTTPS locally, the server's own HTTP client must trust the local TLS certificate.

Most local development stacks (Local by Flywheel, Valet, Lando, MAMP, etc.) use self-signed or locally-issued TLS certificates. PHP's HTTP client (`wp_remote_request` / cURL) will reject these with a certificate verification error — typically **cURL error 60: SSL certificate problem**.

This is not a plugin bug. It is a local environment TLS trust issue.

### 3. Review Activity And Notices

Use `Advanced Tools` and on-page notices to check for:

- cURL error 60 or certificate verification errors
- loopback request failures
- sync attempts that fail only on local HTTPS setups

### 4. Fix Options For Local Environments

**Option A (recommended): Disable sync on local, enable on staging/production.**

Keep Block Editor Font Library Sync off locally. Enable it only on the staging or production environment where TLS is properly configured. The plugin's own runtime and preview flow continue working without it.

**Option B: Trust the local certificate in PHP.**

If you specifically need Block Editor sync to work locally (for example, to test the sync feature itself):

1. Export the local root CA certificate from your local development stack.
2. Add it to PHP's CA bundle (`curl.cainfo` in `php.ini`), or use a stack that already handles this (e.g., Lando's `trust-ca` command or LocalWP's built-in trust flow).
3. Restart the web server and PHP-FPM after updating the CA bundle.

### 5. Decide Whether To Keep Sync Enabled

Keep Block Editor sync enabled only when your local environment can successfully complete authenticated background requests back to itself without trust failures.

If it cannot, disable it locally and continue using the plugin's own runtime and preview flow.

---

## Notes

- Local development issues usually affect Block Editor Font Library sync more than the plugin's own runtime CSS generation.
- The plugin intentionally surfaces guidance for local environments where certificate trust is the real blocker.
- Frontend, preview, and Etch runtime output can still work correctly even when Block Editor sync is off.

## Related Docs

- [Settings](Settings)
- [Advanced Tools](Advanced-Tools)
- [Architecture](Architecture)
- [Local Setup](Local-Setup)
- [FAQ](FAQ)
