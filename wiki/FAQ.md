# FAQ

Answers to common questions from beginners setting up the plugin for the first time and from experienced developers customizing its output.

---

## Getting Started

### Do I need to know how to code to use Tasty Custom Fonts?

No. The entire workflow — uploading fonts, importing from Google or Bunny, assigning roles, previewing pairings, and publishing — happens through the WordPress admin UI. You do not need to write any CSS or PHP unless you want to reference the generated variables in your own code.

### What is the minimum I need to do to see a font change on my site?

1. Add a font family to the library (upload, import, or connect a provider).
2. Go to `Deploy Fonts` and assign it to the `Heading` or `Body` role.
3. Click `Apply Sitewide`.

Your site's frontend, Gutenberg editor, and Etch canvas will immediately reflect the updated roles.

### What is the difference between "Save Draft" and "Apply Sitewide"?

`Save Draft` stores your current role selections in a safe holding area. Nothing on the live site changes.

`Apply Sitewide` publishes the current draft roles to the frontend, editor, and canvas. Visitors will see the updated fonts immediately after you apply.

Think of it like a blog post workflow: Save Draft = save without publishing; Apply Sitewide = publish.

### Can I try a font without affecting the live site?

Yes. Assign it to a draft role, click `Save Draft`, and then use the preview workspace on the Deploy Fonts page. The preview shows how the draft pairing looks across multiple content scenes without touching the live output.

### Which font format should I upload?

Use `WOFF2` whenever possible. It is the most compressed format and is natively supported by every modern browser. If you only have `TTF` or `OTF` files, upload those — the plugin accepts them and they will work, but they will produce slightly larger file sizes. `WOFF` is a valid fallback but less common today.

Variable fonts in any of these formats are also accepted. See the next question for details on variable fonts.

### What is a variable font and should I use one?

A **variable font** is a single font file that contains a continuous range of design variations — most commonly weight (`wght`), but also width, slant, and others — rather than separate files per weight. One variable WOFF2 file can replace eight separate static weight files.

**Use a variable font when:**
- you want smooth weight interpolation (any weight value from 100–900, not just standard stops)
- you want to reduce total file count for a multi-weight family
- the variable version of the family you want is available

**Use static fonts when:**
- you only need one or two specific weights and want the simplest possible setup
- your theme or builder already specifies weights through its own variables

To enable variable font features in the plugin, go to `Settings → Output → Variable Font Support` and turn it on. See [Settings](Settings) for full details.

---

## Adding Fonts

### How do I get fonts from Google Fonts?

1. Go to `Settings` and save your Google Fonts API key in the Google key field.
2. On the `Font Library` page, open the add-font flow and select `Google Fonts`.
3. Search for the family you want and choose `Self-hosted` (downloads files to your server) or `CDN` (serves from Google's servers).

You need an API key only for the live search step. If you already have an imported family saved, it keeps working even if you remove the key later.

### How do I get a Google Fonts API key?

1. Go to [console.cloud.google.com](https://console.cloud.google.com).
2. Create a project (or select an existing one).
3. Enable the **Web Fonts Developer API** from the API Library.
4. Create a credential → API Key.
5. Copy the key and paste it into the Google key settings inside the plugin.

Restrict the key to the Web Fonts API to keep it secure.

### Do I need to pay for Google Fonts or Bunny Fonts?

No. Both are free to use. Google Fonts is a free service from Google. Bunny Fonts is a GDPR-friendly free alternative hosted by Bunny.net. Adobe Fonts requires an active Adobe Creative Cloud subscription.

### What is Bunny Fonts and why would I use it instead of Google Fonts?

Bunny Fonts is a privacy-focused alternative that hosts the same font catalog as Google Fonts but routes traffic through European infrastructure. If your site operates in the EU, GDPR or similar regulations may require you to avoid routing user data through Google servers. Using Bunny CDN (or self-hosting from Bunny) sidesteps that concern entirely.

Bunny Fonts does not require an API key — you can search and import directly.

### What is a "delivery profile" and why does a single family have more than one?

A delivery profile describes one specific way of serving a font family: which provider to use (local files, Google, Bunny, or Adobe), whether to use self-hosted files or a CDN, and which variants are included.

Having multiple profiles on one family means you can switch between them without losing your previous configuration. For example, you can keep a self-hosted profile and a CDN profile side by side. If you temporarily want to test CDN performance, just switch the active profile — no re-import needed.

### I uploaded a font but it doesn't appear in the Font Library — what should I try?

1. Confirm the file format is `WOFF2`, `WOFF`, `TTF`, or `OTF`. Other formats are not supported.
2. Check that the upload did not time out (large files can take a moment).
3. If you placed the file on the server manually (via FTP or SSH), use the `Rescan` action from the Font Library to discover it.
4. Check the activity log in `Advanced Tools` for any upload error messages.

---

## Variable Fonts

### How do I enable variable font support?

Go to `Settings → Output → Variable Font Support` and turn it on. Once enabled:

- the Font Library shows a `Variable` badge on families with variable delivery profiles and a variable filter tab
- the Upload Files builder shows an axis editor column for variable font files
- Google and Bunny search results mark variable families
- the Deploy Fonts role selectors expose per-role axis controls and weight overrides when the assigned family is variable

### What are font axes?

A font axis is a named dimension of variation in a variable font. The most common is `wght` (weight), which lets you specify any weight from 100 to 900. Other axes include `wdth` (width), `ital` (italic), and custom designer axes specific to a particular typeface.

Axis values are stored in the plugin's library and emitted as `font-variation-settings` in the generated CSS when variable font support is enabled.

### Can I mix variable and static fonts on the same site?

Yes. You can assign a variable font to the Heading role and a static font to the Body role (or vice versa). Variable font features only activate for roles assigned to families with variable delivery profiles. Static roles work exactly as before.

### My variable font axis controls do not appear in Deploy Fonts

Check:

1. Variable Font Support is **on** in `Settings → Output`.
2. The family assigned to the role has a variable delivery profile (confirmed by the `Variable` badge in the Font Library).
3. The family was imported or uploaded after Variable Font Support was enabled. If the family was already in the library before you enabled the setting, try re-importing it to capture axis metadata.

### Can I use a variable font file for self-hosted Google/Bunny delivery?

Google self-hosted imports will include the variable font WOFF2 files when Variable Font Support is enabled and the family has a variable version on Google Fonts.

For Bunny self-hosted imports, note that Bunny's download API provides static files even for variable families. To get variable font delivery from Bunny, use CDN mode — Bunny's CDN stylesheet serves the variable files directly.

---

### What is a "font role"?

A font role is a named slot that links a font family to a specific purpose on your site. The three roles are:

- **Heading** — used for titles, headings, and display text
- **Body** — used for paragraphs, body copy, and general reading content
- **Monospace** — used for code blocks (enable in `Settings -> Behavior`)

Each role produces a CSS custom property you can reference anywhere:

```css
h1 { font-family: var(--font-heading); }
p  { font-family: var(--font-body); }
code { font-family: var(--font-monospace); }
```

### What is the difference between "Published" and "In Use"?

- **Published** — the family is part of active runtime output but is not currently assigned to a role. Use this for families referenced in custom CSS or theme code.
- **In Use** — the family is assigned to an applied sitewide role. The plugin sets this automatically.
- **In Library Only** — stored for future use, not served in any CSS right now.

### What output preset should I start with?

- If you are new to the plugin or only need the core role variables: choose `Minimal`. It emits only `--font-heading` and `--font-body`.
- If you want CSS variables for all managed families and categories but no utility classes: choose `Variables only`.
- If you prefer HTML utility classes instead of variables: choose `Classes only`.
- If you need fine-grained control over exactly what gets emitted: choose `Custom` and configure each option in the Output tab.

### My font change is applied but the site still shows the old font — what do I do?

1. Confirm you clicked `Apply Sitewide` and not just `Save Draft`.
2. Hard-refresh your browser (Ctrl+Shift+R or Cmd+Shift+R).
3. Go to `Advanced Tools -> Generated CSS` and check that the stylesheet shows the updated font.
4. If the stylesheet looks stale, go to `Settings -> Developer` and use `Clear Cache` to force a regeneration.
5. If you use a caching plugin or CDN in front of WordPress, purge that cache as well.

---

## Settings

### What CSS delivery mode should I use?

Use **File** (the default) for all production sites. The browser caches the generated stylesheet as a separate file, which improves repeat-visit performance.

Use **Inline** only if your server cannot write to `wp-content/uploads/fonts/.generated/` or when debugging output that you need to inspect without a disk-write step.

### Should I enable "Preload Primary Heading and Body Fonts"?

Yes, if your heading and body fonts are self-hosted WOFF2 files and they appear above the fold. Preloading them can improve your Largest Contentful Paint (LCP) score by telling the browser to fetch the font file earlier.

Leave it off for CDN or Adobe deliveries (those use connection hints instead) or if your above-the-fold content does not show the role fonts.

### What does "Block Editor Font Library Sync" do and should I enable it?

When enabled, the plugin mirrors managed families into the WordPress Block Editor Font Library so they appear as choices inside Gutenberg's built-in font picker.

Enable it if you actively use the site editor's typography controls.

Leave it off if you are on a local development environment with self-signed TLS certificates, as loopback requests (which the sync requires) often fail in that setup.

### What is "Automatic.css Font Role Sync" and when do I need it?

If you use Automatic.css (ACSS) on the same site, enabling this setting maps ACSS's heading and text font-family settings to `var(--font-heading)` and `var(--font-body)`. This means Tasty Custom Fonts becomes the single source of truth for font choices, and ACSS reflects whatever role assignments you apply.

Leave it off if you do not use Automatic.css.

### What does "Enable Monospace Role" do?

It turns on a third role slot for code and `pre` elements. When enabled, you can assign a monospace font family (like Fira Code or JetBrains Mono) and the plugin outputs `--font-monospace` for you to reference in CSS. Most sites do not need this unless they display code heavily.

---

## Troubleshooting

### Block Editor Font Library Sync keeps failing on my local site

This is a known and expected behavior. The sync sends loopback requests from the server to its own REST API. On local development environments that use self-signed TLS certificates, those requests typically fail with a certificate verification error.

The fix: disable `Block Editor Font Library Sync` in `Settings -> Integrations` on your local environment. The plugin's runtime CSS, admin previews, and Etch canvas all continue to work correctly without it.

See [Local Development](Troubleshooting-Local-Development) for full guidance.

### The generated CSS file path doesn't exist — where is it?

The canonical path is:

```
wp-content/uploads/fonts/.generated/tasty-fonts.css
```

The `.generated` directory starts with a dot, so some FTP clients hide it by default. If the file does not exist:

1. Go to `Advanced Tools -> System Details` to confirm the delivery mode and generated file status.
2. Apply sitewide or trigger a settings save to force the plugin to write the file.
3. Check that `wp-content/uploads/fonts/` is writable by the web server process.

### I switched from CDN to self-hosted but the frontend still loads from the CDN

The generated stylesheet may not have been refreshed yet. Go to `Advanced Tools -> Generated CSS` and check that it reflects the new delivery mode. If it looks stale, go to `Settings -> Developer` and use `Clear Cache` to force a regeneration, or save any Output setting to trigger a refresh.

### The wrong font is showing on my site even after applying sitewide

1. Open `Advanced Tools -> Generated CSS` and confirm the `@font-face` rules and role variable values are correct.
2. Check for a conflicting CSS declaration in your theme or plugin that overrides `font-family` directly instead of using `var(--font-heading)` or `var(--font-body)`.
3. If you use a page builder that stores font settings independently (Bricks, Oxygen, Elementor), check whether that builder is overriding the CSS variable with a hard-coded value.

---

## Developer Questions

### How do I reference the plugin's output variables in my own CSS?

```css
/* In any stylesheet or theme CSS: */
body {
    font-family: var(--font-body);
}

h1, h2, h3, h4, h5, h6 {
    font-family: var(--font-heading);
}

code, pre {
    font-family: var(--font-monospace); /* only when the monospace role is enabled */
}
```

The plugin emits these variables inside `:root {}` in the generated runtime stylesheet. Any CSS that loads after the plugin stylesheet can use them.

### How do I hook into the plugin's generated CSS output?

The plugin exposes a `tasty_fonts_generated_css` filter that receives the fully assembled stylesheet string after `CssBuilder` generates it and before it is cached and written to disk. Use it to append or rewrite rules:

```php
add_filter( 'tasty_fonts_generated_css', function ( string $css, array $catalog, array $roles, array $settings ): string {
    // Append a custom rule after the generated output.
    return $css . ':root { --my-custom-var: var(--font-heading); }';
}, 10, 4 );
```

> **Stability note:** this filter is used internally and is not versioned as a stable public API. Verify its signature against `includes/Fonts/AssetService.php` when upgrading between minor releases.

### Where are plugin settings stored?

Settings are stored in WordPress options. Use `get_option('tasty_fonts_settings')` to read the full settings array. The structure is defined in `Repository/SettingsRepository.php`.

### How do I clear the plugin cache programmatically?

```php
// Delete the generated-asset transients directly.
delete_transient( 'tasty_fonts_css_v2' );
delete_transient( 'tasty_fonts_css_hash_v2' );

// Preferred: call AssetService::invalidate() via the service container.
// This clears both transients in one call.
TastyFonts\Plugin::instance()
    ->get_service( TastyFonts\Fonts\AssetService::class )
    ->invalidate();
```

To force a full regeneration (invalidate catalog + rebuild + write file):

```php
TastyFonts\Plugin::instance()
    ->get_service( TastyFonts\Fonts\AssetService::class )
    ->refreshGeneratedAssets();
```

### How do I run the test suite?

```bash
# PHP tests (no Composer or PHPUnit required):
php tests/run.php

# JavaScript contract tests:
node --test tests/js/*.test.cjs

# PHP syntax sweep:
find . -name '*.php' -not -path './output/*' -print0 | xargs -0 -n1 php -l
```

See [Testing](Testing) for details on adding new tests.

### Where do I find the service container and how do I get a service instance?

`Plugin::instance()` returns the booted plugin. Use `Plugin::instance()->get_service( ServiceClassName::class )` to retrieve any registered service.

### How do I add support for a custom font provider?

Custom provider support is not part of the current public API surface. The provider model is handled internally through `Google/`, `Bunny/`, and `Adobe/` namespaces. If you need custom provider behavior, open an issue to discuss whether a provider extension interface would fit the roadmap.

### Is there a REST API I can use to manage fonts programmatically?

Yes. The plugin exposes REST endpoints used by its own admin UI. These endpoints require authentication (admin-level nonce or application password). The API adapter is in `Api/` — review `Api/RestController.php` for available routes and parameters. Note: these endpoints are currently internal to the admin UI and are not versioned as a public API. They may change between releases.

---

## Related Docs

- [Getting Started](Getting-Started)
- [Concepts](Concepts)
- [Glossary](Glossary)
- [Troubleshooting: Imports and Deliveries](Troubleshooting-Imports-and-Deliveries)
- [Troubleshooting: Local Development](Troubleshooting-Local-Development)
- [Developer: Architecture](Architecture)
