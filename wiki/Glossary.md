# Glossary

Definitions for every key term used across the Tasty Custom Fonts documentation.

## A

**Active delivery profile**
The delivery profile the plugin currently uses at runtime for a given font family. Only one profile per family can be active at a time. Switching the active profile changes what CSS the plugin generates without requiring a re-import.

**Adobe Fonts**
A hosted font service included with Adobe Creative Cloud subscriptions. The plugin connects to Adobe Fonts via a web project ID and enqueues the Adobe-hosted stylesheet at runtime. No font files are downloaded to your server.

**Apply Sitewide**
The action that promotes your current draft role assignments to live output. Until you apply sitewide, draft changes do not affect what visitors see.

**Axis (variable font)**
A named dimension of variation in a variable font file, such as `wght` (weight), `wdth` (width), or `ital` (italic). Axis values are stored in the plugin's library alongside imported variable font metadata. When Variable Font Support is enabled, per-role axis controls let you pin specific axis values for each role.

## B

**Block Editor Font Library**
A WordPress core feature (introduced in WordPress 6.5) that lets the Gutenberg site editor offer fonts through its built-in typography controls. Tasty Custom Fonts can optionally sync its managed families into this library so they appear inside the site editor's font picker.

**Body role**
The role slot assigned to the font family used for paragraph text, descriptions, and general reading content across the site. The plugin outputs this as `--font-body`.

**Bunny Fonts**
A GDPR-friendly font catalog hosted by Bunny.net. The plugin can import Bunny families as self-hosted files or keep them on Bunny's CDN. No API key is required.

## C

**CDN delivery**
A delivery mode where fonts are served from a remote content delivery network (Google, Bunny, or Adobe) rather than from your own server. The plugin enqueues the provider's external stylesheet at runtime.

**CSS custom property (CSS variable)**
A reusable value defined with a `--` prefix and referenced with `var()`. The plugin outputs role variables like `--font-heading` and `--font-body` that you can reference anywhere in your theme CSS.

**CSS Delivery**
The setting that controls whether the plugin writes generated CSS to a file (`File` mode) or injects it inline into the page `<head>` (`Inline` mode). File mode is preferred for production because browsers can cache the file independently.

**CssBuilder**
The internal service class responsible for assembling `@font-face` rules, CSS custom properties, optional utility classes, and other generated output into the final runtime stylesheet.

## D

**Delivery profile**
A record that describes how a specific font family should be served at runtime. It carries the provider (Local, Google, Bunny, or Adobe), the delivery type (self-hosted or CDN), the font variants, and any provider-specific metadata. One family can have multiple delivery profiles stored simultaneously.

**Draft role**
A role assignment saved as a working selection that does not affect live output. Use `Save Draft` to store a pairing while experimenting, then use `Apply Sitewide` when you are ready to go live.

**Draft/live separation**
The design principle that separates "what you are experimenting with" from "what the site is currently serving." Draft roles are your working state; applied roles are what visitors see.

## E

**Etch (EtchWP)**
A WordPress page-builder environment. The plugin integrates with Etch by providing runtime stylesheet URLs through the Etch canvas bridge, so preview typography inside the builder matches the live site.

**Encrypted option storage**
The storage model used since 1.12.0 for the Google Fonts API key. The key is stored in a dedicated WordPress option (`tasty_fonts_google_api_key_data`) using symmetric encryption, isolated from the main `tasty_fonts_settings` record. It is never serialised into Site Transfer bundles or any other portable export.

## F

**Fallback stack**
A comma-separated list of font families the browser should try in order if the primary font is unavailable. Example: `Georgia, serif`. The plugin allows you to set a custom fallback stack per family.

**Family slug**
A URL-safe identifier derived from the font family name. Used as a directory name under `wp-content/uploads/fonts/`. For example, `Inter` becomes `inter`.

**font-variation-settings**
A CSS property that explicitly sets one or more axis values for a variable font. Example: `font-variation-settings: 'wght' 650`. When variable font support is enabled and a role is assigned to a variable family, the plugin generates `font-variation-settings` in the appropriate CSS rules.

**Font display**
The CSS `font-display` descriptor controls how the browser handles a period when the font has not yet loaded. Options include `swap`, `optional`, `fallback`, `block`, and `auto`. The plugin lets you set a global default and per-family overrides.

**Unicode Range Output**
A global Output setting that controls whether the plugin preserves imported `unicode-range` descriptors, forces one of the built-in Latin presets, omits the descriptor entirely, or emits a custom range string. This changes emitted CSS and editor payloads only; it does not rewrite stored library face metadata.

**Font Library (plugin page)**
The admin page that lists every font family the plugin manages. Use it to browse families, switch delivery profiles, set fallback stacks, and control per-family options. Not to be confused with the WordPress Block Editor Font Library.

**Font role**
A named slot (`Heading`, `Body`, or `Monospace`) the plugin uses to connect a font family to specific CSS output. Roles produce CSS custom properties (`--font-heading`, `--font-body`, `--font-monospace`) that your theme or page builder can reference.

## G

**Google Fonts**
A free web-font service run by Google. The plugin can import Google families as self-hosted files (downloaded to your server) or keep them on Google's CDN. Live catalog search requires a Google Fonts API key.

**Google Fonts API key**
A credential from the Google Cloud Console that gives the plugin access to the Google Fonts API for live catalog search. Required only for the add-font search flow; not required to serve a family that is already imported.

**Gutenberg**
The WordPress block editor. The plugin registers managed families as editor typography presets so they are available in the block editor's typography controls.

## H

**Heading role**
The role slot assigned to the font family used for page titles, section headings, and display text. The plugin outputs this as `--font-heading`.

## I

**Inline CSS delivery**
A fallback delivery mode where the plugin injects generated CSS directly into the page `<head>` rather than serving it as a separate file. Useful when file-write permissions are unavailable.

**In Library Only**
A publish state indicating the family is stored in the plugin's library but is not served in runtime CSS. Use this to stage a family for future use without making it live.

**In Use**
A publish state indicating the family is actively assigned to a live applied role. This state is set automatically — you do not set it manually.

## L

**LCP (Largest Contentful Paint)**
A Core Web Vitals metric that measures how long it takes for the largest visible element on the page to render. Preloading WOFF2 font files can improve LCP for above-the-fold text.

**Local fonts**
Font files you upload directly from the WordPress dashboard or place in `wp-content/uploads/fonts/` for the plugin to discover on rescan. Supported formats: `WOFF2`, `WOFF`, `TTF`, `OTF`.

**Loopback request**
An HTTP request a WordPress server makes back to itself to call its own REST API. The Block Editor Font Library sync feature relies on loopback requests, which can fail on local development environments due to TLS certificate trust issues.

## M

**Minify Generated CSS**
A setting that strips whitespace from the generated stylesheet to reduce file size. Enabled by default. Turn it off temporarily when you need to read the generated `@font-face` rules by hand.

**Monospace role**
An optional third role slot for a font family used in `code` and `pre` elements. Disabled by default. Enable it in `Settings -> Behavior`. Outputs `--font-monospace`.

## O

**OTF (OpenType Font)**
A cross-platform font format. Supported for local upload. WOFF2 is preferred for web delivery; OTF is typically used when WOFF2 is not available.

**Output preset**
A quick setting in `Settings -> Output` that lets you choose the overall variable and class surface the plugin generates: `Minimal`, `Variables only`, `Classes only`, or `Custom`.

## P

**Preconnect hint**
An HTML `<link rel="preconnect">` tag that tells the browser to open a TCP/TLS connection to a remote origin early. The plugin can emit preconnect hints for active Google, Bunny, and Adobe CDN origins.

**Preload**
An HTML `<link rel="preload" as="font">` tag that instructs the browser to fetch a font file before it is discovered in CSS. The plugin can emit preloads for the WOFF2 files used by the live heading and body roles.

**Published**
A publish state indicating the family is part of active runtime output. A Published family can be used in custom CSS, theme templates, or builder selectors without being assigned to a role.

## R

**Rescan**
A plugin action that scans `wp-content/uploads/fonts/` for font files that were placed there outside the plugin's own upload UI. Useful for bulk migrations or manual FTP transfers.

**Role variable**
A CSS custom property output by the plugin representing a font role. The three role variables are `--font-heading`, `--font-body`, and `--font-monospace`.

**Runtime stylesheet**
The generated CSS file produced by the plugin and served to the site's frontend, Gutenberg, and Etch. Canonical path: `wp-content/uploads/fonts/.generated/tasty-fonts.css`.

## S

**Save Draft**
A button on the Deploy Fonts page that stores your current role selections without publishing them live. Drafts do not change what visitors see.

**Self-hosted delivery**
A delivery mode where downloaded font files live on your own server and are served from there. The plugin generates `@font-face` rules pointing to local file paths. Self-hosted delivery gives you full control over privacy, caching, and file availability.

**Site Transfer**
The portable bundle export and import feature in `Settings → Transfer` (added in 1.12.0). Use it to move the full Tasty Fonts setup — managed font files, library data, settings, and live role assignments — between WordPress sites with one ZIP bundle. Google Fonts API keys are excluded from bundles for security. See [Site Transfer](Site-Transfer) for the full guide.

**Site Transfer Bundle**
A ZIP file produced by the Site Transfer export action. It contains a manifest (`tasty-fonts-export.json`) that records library state, settings, role assignments, and file checksums, plus all managed font files under a `fonts/` subdirectory. Imported on the destination site to replace the current Tasty Fonts setup.

**Swap (font-display: swap)**
A `font-display` value that shows a fallback font until the custom font loads, then swaps in the custom font. Good for content-heavy sites where brand typography matters. Admin previews always force `swap` for safety.

## T

**Text domain**
The identifier used to namespace translatable strings in WordPress plugins. Tasty Custom Fonts uses the text domain `tasty-fonts`.

**Transfer Bundle**
Synonym for Site Transfer Bundle. A portable ZIP file containing a Tasty Fonts export that can be imported on any site running 1.12.0-beta.2 or later.

**Transfer Tab**
The fifth tab in the Settings page, added in 1.12.0. Contains the Export and Import cards for the Site Transfer workflow.

**TTF (TrueType Font)**
An older cross-platform font format. Supported for local upload. Modern browsers prefer WOFF2, but TTF works as a fallback.

## U

**Utility class**
A CSS class generated by the plugin that applies a font-role, family, category, or alias to an element. Example: `.font-heading { font-family: var(--font-heading); }`. Generated when the utility class output feature is enabled in Settings.

**Update URI**
A WordPress plugin header field the plugin uses to advertise its GitHub repository as the source for updates. This allows the WordPress Plugins screen to detect new GitHub releases.

## V

**Variable font**
A font file format that encodes a continuous range of design variations (such as weight, width, or slant) as named axes within a single file. A single variable WOFF2 file can replace multiple separate static weight files. Variable font support in the plugin is opt-in; enable it in `Settings → Output → Variable Font Support`.

**Variable Font Support**
The opt-in Output setting that activates variable font features across uploads, imports, library display, Deploy Fonts role controls, and generated CSS. Off by default to keep static-only installs simple and unaffected.

**Variant**
A single font file associated with a specific weight, style, or subset of a family. For example, `Inter 400 normal`, `Inter 700 italic`. The plugin stores variants within delivery profiles.

## W

**WOFF (Web Open Font Format)**
A compressed web font format. Broadly supported by browsers. WOFF2 is more compressed and preferred, but WOFF is a solid fallback.

**WOFF2 (Web Open Font Format 2)**
The modern web font format. Smaller file sizes and better performance than WOFF or TTF. The plugin prioritizes WOFF2 for preloads and self-hosted delivery.

**Web project (Adobe Fonts)**
An Adobe Fonts project configuration that specifies which families and variants are available for a particular website. The project generates a unique stylesheet URL (`use.typekit.net/<project-id>.css`) that the plugin enqueues at runtime.

## Related Docs

- [Concepts](Concepts)
- [Getting Started](Getting-Started)
- [FAQ](FAQ)
