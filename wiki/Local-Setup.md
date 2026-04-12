# Local Setup

Set up Tasty Custom Fonts in a local WordPress environment and run the repository checks without extra tooling.

## Use This Page When

- you want to contribute code or docs
- you need a quick local WordPress setup option
- you want to run the repo checks without a full WordPress install
- you hit local-only issues around uploads, HTTPS, or loopback requests

---

## Recommended Local WordPress Setups

Any local WordPress stack is fine as long as you can place the plugin in `wp-content/plugins/tasty-fonts/`.

### `wp-env`

```bash
npx @wordpress/env start
```

### LocalWP

Create a local site, then symlink or copy this repository into that site's `app/public/wp-content/plugins/tasty-fonts/` directory.

### DDEV

```bash
ddev wp plugin activate tasty-fonts
```

If you use a different stack, follow the same manual install path and activate the plugin from the local admin.

---

## Manual Install Path

1. Clone this repository.
2. Copy the checkout to `wp-content/plugins/tasty-fonts/`.
3. Activate `Tasty Custom Fonts` in the WordPress admin.
4. Open `Tasty Fonts` from the admin menu.

There is no build step, no Composer install, and no npm install required for normal development.

---

## Run Tests Without WordPress

The automated checks do not require a full WordPress install.

```bash
find . -name '*.php' -not -path './output/*' -print0 | xargs -0 -n1 php -l
php tests/run.php
node --test tests/js/*.test.cjs
```

The PHP harness stubs the WordPress functions it needs, and the JavaScript tests use Node's built-in test runner.

---

## Common Local Pitfalls

### Upload Permissions

Self-hosted imports and generated CSS write under `wp-content/uploads/fonts/`. If uploads, imports, or generated CSS fail locally, verify that your local PHP process can create and write directories there.

### HTTPS And Loopback Requests

Most plugin features work normally on local HTTPS sites, but Block Editor Font Library sync depends on loopback REST requests. Local TLS trust problems can break those requests even when the rest of the plugin works.

See [Local Development Troubleshooting](Troubleshooting-Local-Development) for the detailed certificate and loopback guidance.

### Preload Hints On Local Sites

If you are testing preload or preconnect behavior locally, keep in mind that local hostnames, self-signed certificates, or mixed-content setups can produce misleading browser behavior that does not match staging or production.

---

## Related Docs

- [Contributing](Contributing)
- [Testing](Testing)
- [Architecture](Architecture)
- [Local Development Troubleshooting](Troubleshooting-Local-Development)
