# Getting Started

Set up Tasty Custom Fonts, add your first families, and understand how the current admin workflow fits together.

## Who This Is For

- **Complete beginners**: if this is your first time managing fonts in WordPress, read every step below and follow the links when you need more context.
- **Experienced WordPress users**: skim the steps — each one is short. If you get stuck, jump to the [FAQ](faq.md) or the relevant provider guide.
- **Developers**: this page covers the admin workflow. For the internal architecture, go straight to [Architecture](developer/architecture.md).

## Use This Page When

- you just installed the plugin
- you want a first-run path through the current UI
- you need the quickest route from install to live typography

## Before You Start

Version `1.7.0` is the latest tagged beta step toward `2.0`, while `main` now tracks the next development line as `1.8.0-dev`. The tagged beta builds are intended for real use and real testing, but a few more releases are expected before the final `2.0` experience is considered settled.

**Tip for beginners:** if any term in this guide is unfamiliar, check the [Glossary](glossary.md). The [Concepts](concepts.md) page also explains the three big ideas — delivery profiles, draft/live roles, and the CSS pipeline — before you dive in.

---

## Steps

### 1. Install And Activate

Use one of these paths:

- **Via GitHub release** (recommended): download the latest ZIP from [GitHub Releases](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/releases), then go to WordPress `Plugins → Add New Plugin → Upload Plugin`, upload the ZIP, and activate.
- **Manual install**: copy the `etch-fonts/` directory into `wp-content/plugins/` and activate from the WordPress `Plugins` screen.

After activation, a new `Tasty Fonts` item will appear in the WordPress admin sidebar.

> **First-run note:** the plugin will create a `wp-content/uploads/fonts/` directory on activation. This is where font files and generated CSS are stored. No other install steps are needed — there is no `composer install` or `npm install`.

### 2. Learn The Four Top-Level Pages

Open `Tasty Fonts` and explore the four sections:

| Page | What it's for |
|---|---|
| **Deploy Fonts** | Assign fonts to roles (Heading, Body, Monospace), preview pairings, and publish sitewide |
| **Font Library** | See every managed family, switch delivery profiles, and control publish state |
| **Settings** | Control CSS output, integrations with builders and editors, behavior options, and developer tools |
| **Advanced Tools** | Inspect generated CSS, review system details, and read activity history |

Inside `Settings`, four tabs group related controls:

- `Output` — what CSS gets generated and how
- `Integrations` — Gutenberg, Automatic.css, Bricks, Oxygen
- `Behavior` — feature flags and uninstall behavior
- `Developer` — cache clearing, resets, and maintenance (proceed carefully)

### 3. Add Families To The Library

Pick the source that matches your workflow:

- **Upload local files** — use this if you have font files on your computer (`WOFF2`, `WOFF`, `TTF`, or `OTF`)
- **Rescan `wp-content/uploads/fonts/`** — use this if files were placed on the server directly (via FTP/SSH)
- **Import Google Fonts** — requires a Google Fonts API key; imports self-hosted files or keeps delivery on Google's CDN
- **Import Bunny Fonts** — no API key needed; GDPR-friendly; same workflow as Google
- **Connect an Adobe Fonts web project** — requires an active Adobe Creative Cloud subscription and a web project ID

> **Not sure which to pick?** Start with Bunny Fonts if you want a free catalog without API credentials, or upload your own files if you already have them. See [Concepts → Choosing a Provider](concepts.md#choosing-a-provider) for a comparison table.

Google self-hosted files are stored under `wp-content/uploads/fonts/google/<family-slug>/`.
Bunny self-hosted files are stored under `wp-content/uploads/fonts/bunny/<family-slug>/`.

### 4. Review Delivery Profiles

In the library, each family can keep one or more delivery profiles. Use the active delivery profile to decide what the runtime should serve.

Typical examples:

- keep a self-hosted profile live (files on your server, best for caching)
- keep a CDN profile saved for later comparison (served from provider's network)
- leave a family `In Library Only` until you are ready to use it

> **Beginner tip:** you only need one delivery profile to get started. Multiple profiles are useful when you want to switch between self-hosted and CDN without losing either configuration.

### 5. Set Draft Roles

Go to `Deploy Fonts` and choose draft role assignments for:

- `Heading` — titles, headings, display text
- `Body` — paragraphs, body copy, reading content
- `Monospace` — code and `pre` elements (only if you enabled the monospace role in `Settings → Behavior`)

Use `Save Draft` while experimenting. Your live site is not affected until you apply sitewide.

### 6. Preview Before Publishing

Use the preview workspace to compare draft and live output across:

- editorial content
- card layouts
- reading layouts
- interface text
- code previews

This is the best way to catch a pairing that looks great in isolation but clashes in the context of a full content layout.

### 7. Apply Sitewide

Use `Apply Sitewide` when the current draft roles are ready to become live. That updates the typography the plugin serves to the frontend, Gutenberg, and Etch.

> **What happens when you apply**: the plugin regenerates the runtime stylesheet (`wp-content/uploads/fonts/.generated/tasty-fonts.css`) with the new role assignments and refreshes all relevant caches. Visitors see the updated fonts on their next page load.

### 8. Revisit Settings After The First Successful Deploy

Once your first sitewide pairing is working, go back to `Settings` and review the newer controls:

- switch to the **Minimal output preset** if you only need the core role variables (`--font-heading` and `--font-body`)
- enable only the **integrations** that match the actual stack on the site
- review **Block Editor sync** behavior on local or staging environments (it often needs to be turned off on local)
- use the **Developer tab** when you need to reset caches or re-bootstrap integration detection during testing

---

## Notes

- Draft role changes do not affect live runtime output until you apply them sitewide.
- Admin previews force `font-display: swap` for preview safety, even if the live output uses another `font-display` value.
- GitHub-installed copies can detect future stable, beta, or nightly releases from the normal WordPress plugins screen through the bundled GitHub updater.
- Because `1.7.0` is part of the beta path toward `2.0`, it is worth rechecking the changelog and docs when upgrading within the `1.7.x` series.

## Related Docs

- [Concepts](concepts.md) — the mental model behind delivery profiles, roles, and the CSS pipeline
- [Deploy Fonts](deploy-fonts.md) — detailed guide to the draft/apply workflow
- [Font Library](font-library.md) — managing families, profiles, and publish state
- [Settings](settings.md) — full output and integration controls
- [Local Fonts](providers/local-fonts.md) — uploading your own font files
- [FAQ](faq.md) — answers to common beginner and developer questions
