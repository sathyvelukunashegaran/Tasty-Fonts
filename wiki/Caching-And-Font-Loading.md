# Caching And Font Loading

Get the best possible font delivery performance from Tasty Custom Fonts by understanding every control the plugin provides — and how to combine them effectively.

> **Beginner tip:** fonts are often the heaviest typography cost on a page. The right combination of file format, delivery mode, preloading, and `font-display` can meaningfully improve your Core Web Vitals scores — especially Largest Contentful Paint (LCP) and Cumulative Layout Shift (CLS) — without touching your theme.

---

## The Fastest Setup At A Glance

If you want maximum performance and are using self-hosted WOFF2 files:

| Setting | Value | Why |
|---|---|---|
| CSS Delivery | **File** | Browser caches the stylesheet independently |
| Minify Generated CSS | **On** | Smaller payload |
| Default font-display | **optional** | Eliminates layout shift on repeat visits; first visit uses fallback |
| Preload Primary Fonts | **On** | Fetches Heading and Body WOFF2s before CSS is parsed |
| Remote Connection Hints | **Off** (if fully self-hosted) | No external origins to warm up |
| Variable Font Support | **On** (if using variable fonts) | One file replaces multiple static weight files |
| Unicode Range Output | **Off** or **Basic Latin** | Omit unless you need multi-script subsetting |
| Output Preset | **Minimal** | Smallest CSS surface if you only need core role variables |

Adjust from these defaults once you understand your specific site's needs.

---

## CSS Delivery: File vs Inline

**Location:** `Settings → Output → CSS Delivery`

### File delivery (recommended for production)

The plugin writes the generated stylesheet to:

```
wp-content/uploads/fonts/.generated/tasty-fonts.css
```

This file is enqueued as a normal external stylesheet. The browser caches it using standard HTTP caching headers. On subsequent visits, the stylesheet is served from the browser cache without any server round-trip.

**Performance benefit:** the stylesheet payload is paid once per browser, not once per page. Combine with a long `Cache-Control: max-age` from your host or CDN for the best result.

**When it regenerates:** the file is rewritten any time you apply sitewide, change Output settings, or clear caches via `Settings → Developer`. Your page-caching plugin should be purged after each regeneration.

### Inline delivery

The plugin injects the generated CSS directly into the page `<head>`. The CSS is re-sent on every page load.

**When to use inline:** only if file write permissions are unavailable, or temporarily during debugging. Inline delivery prevents browser caching of the font stylesheet — avoid it in production.

---

## Font Format Priority

Always prefer **WOFF2**. It is the most compressed web font format and is natively supported by all modern browsers.

| Format | Relative size | Browser support |
|---|---|---|
| WOFF2 | Smallest | All modern browsers |
| WOFF | ~30% larger than WOFF2 | All modern + IE 9+ |
| TTF / OTF | ~2–3× larger than WOFF2 | All modern + IE 9+ |

The plugin prioritises WOFF2 files in preload candidates and `@font-face` ordering.

> **Beginner tip:** if you only have TTF or OTF files, consider converting them to WOFF2 before uploading using a tool like [Fonttools](https://github.com/fonttools/fonttools) (`fonttools ttLib`) or an online WOFF2 converter. The file size savings can be substantial for families with many weights.

---

## Variable Fonts: Fewer Requests, More Flexibility

**Location:** `Settings → Output → Variable Font Support`

A single variable WOFF2 file can encode a continuous range of weights (e.g., 100–900) in one file, replacing what would otherwise be up to eight separate static weight files.

**Performance impact:**
- Fewer HTTP requests (1 file vs 8)
- Smaller total download in many cases when multiple weights are in use
- Smooth weight interpolation without additional downloads

**When to enable:** only if you are actually using variable font files. For sites that only need one or two weights per family, static files may be smaller overall than a full variable file.

> **Axis-default optimisation (1.12.0+):** the plugin now omits `font-variation-settings` from generated CSS when a role axis value matches the font's registered default. This keeps the generated output clean and avoids redundant declarations.

See [Concepts → Variable Fonts](Concepts#variable-fonts-opt-in) for full details.

---

## `font-display` And Layout Shift

**Location:** `Settings → Output → Default font-display`

`font-display` controls what the browser shows while a font is loading. The choice directly affects Cumulative Layout Shift (CLS) and First Contentful Paint (FCP).

| Value | Behaviour | Best for |
|---|---|---|
| `optional` | Uses font only if it loads in a very short window (~100 ms). Shows fallback otherwise; no swap occurs after that window. | Performance-first sites; minimises CLS |
| `swap` | Shows fallback immediately; swaps to the custom font when it arrives. | Content-first sites where brand typography matters more than CLS |
| `fallback` | Short invisible period (~100 ms), then shows fallback. Swaps within ~3 s. No swap after that. | Balanced middle ground |
| `block` | Hides text until font loads. Risks invisible text on slow connections. | Avoid for body text and slow fonts |
| `auto` | Browser default (usually similar to `block`). | Rarely useful |

### Recommended choices

- **`optional` (default)** — best for most performance-sensitive sites. On first visit, the fallback font may show; on repeat visits, the font is usually already cached and loads within the optional window.
- **`swap`** — use when your brand typography must always render (e.g., a logo-style heading). Accept the potential CLS cost on first visits.

### How the plugin handles CDN deliveries

For live Google and Bunny CDN deliveries, the plugin automatically promotes `optional` to `swap` at runtime. This prevents a first-visit CDN response arriving just after the optional window and leaving visitors permanently on the fallback font.

### Per-family overrides

You can override `font-display` per-family in the Font Library. Family-level overrides take precedence over the global default. This lets you use `optional` for body text while using `swap` for a headline typeface.

---

## Preloading Primary Fonts

**Location:** `Settings → Output → Preload Primary Heading And Body Fonts`

When enabled, the plugin emits `<link rel="preload" as="font" type="font/woff2" crossorigin>` tags in the `<head>` for the WOFF2 files used by the live Heading and Body roles.

**Why this helps:** by default, the browser does not discover font files until it has parsed the CSS, built the render tree, and matched text elements to their styles. This can delay font loading significantly. A preload hint tells the browser to fetch the font file as early as possible — before any CSS has been parsed.

**Performance impact:** for above-the-fold text using the Heading or Body role, preloading can measurably improve LCP by ensuring the correct font is available when the browser paints the first contentful element.

**Only applies to self-hosted WOFF2 files.** CDN and Adobe deliveries use remote connection hints (preconnect) instead. The plugin selects the single best-matching WOFF2 face for each role based on target weight.

> **Beginner tip:** preloading is most valuable when your above-the-fold content uses a role font that is large enough to delay the first paint. If your site uses a very fast CDN or if the font is already cached in most visitors' browsers, the benefit may be smaller.

### HTTP Link header preloads (advanced)

By default, preload hints are emitted as HTML `<link>` tags in the page `<head>`. For advanced setups (e.g., when HTTP/2 Server Push or early hint headers are useful), the plugin supports switching to HTTP `Link` response headers via a filter:

```php
// Deliver preloads as HTTP Link headers instead of HTML tags.
add_filter( 'tasty_fonts_preload_link_delivery_mode', function (): string {
    return 'headers'; // 'html' (default), 'headers', or 'both'
} );
```

- **`html`** (default) — emits `<link rel="preload">` tags in the page `<head>`
- **`headers`** — sends `Link: <url>; rel=preload; as=font` HTTP response headers only
- **`both`** — emits both HTML tags and HTTP headers

HTTP Link header delivery requires that headers have not been sent before WordPress's `wp_head` action fires. This is typically the case on standard WordPress setups but can be blocked by output buffering issues.

---

## Remote Connection Hints (Preconnect)

**Location:** `Settings → Output → Remote Connection Hints`

When enabled, the plugin emits `<link rel="preconnect">` tags for active CDN origins (Google Fonts, Bunny Fonts, Adobe Fonts). Preconnect tells the browser to complete the TCP/TLS handshake with the remote origin early, reducing the latency of the first CDN stylesheet request.

**When to enable:** any time you have an active CDN or Adobe delivery. The preconnect eliminates the DNS + TCP + TLS setup cost for that first remote request.

**When to disable:** if all your active deliveries are self-hosted. Preconnect hints to origins that end up unused waste a browser connection slot.

> **Privacy note:** preconnect hints cause the browser to contact the remote CDN origin even before the font stylesheet is fetched. If GDPR compliance requires you to avoid third-party connections, disable Remote Connection Hints and use self-hosted delivery for all families. See [GDPR And Font Privacy](GDPR).

---

## Minification

**Location:** `Settings → Output → Minify Generated CSS`

Minification strips whitespace from the generated stylesheet, reducing file size. Enabled by default.

**Performance impact:** the savings depend on how many `@font-face` rules and CSS custom properties the plugin generates. For large setups with many families and utility classes, minification meaningfully reduces the stylesheet payload.

**When to turn off:** temporarily, when you need to read the generated CSS by hand to debug `@font-face` rule details in `Advanced Tools → Generated CSS`.

---

## Unicode Range Subsetting

**Location:** `Settings → Output → Unicode Range Output`

`unicode-range` descriptors in `@font-face` rules tell the browser to download a font face only when the page actually contains characters in that range. This enables **font subsetting** — loading only the character subsets needed for a given page.

| Setting | Effect |
|---|---|
| `Off` (default) | No `unicode-range` emitted; browser downloads the full font face for any matched element |
| `Keep Imported Ranges` | Preserves the `unicode-range` the provider returned at import time (Google Fonts uses this for script-based subsets) |
| `Basic Latin` | Forces a compact `U+0020-007F` range covering standard English characters |
| `Latin Extended` | Forces a broader range covering Latin Extended and additional accented characters |
| `Custom` | Emits the same custom comma-separated range string for every face |

**When this matters:** for multilingual sites or families with many script subsets, `unicode-range` can significantly reduce total font download size. For English-only sites, `Basic Latin` can cut download size for families that include Cyrillic, Greek, or CJK subsets.

**Caution:** if you set `Basic Latin` but your site content includes characters outside that range (e.g., accented vowels in French or German), those characters will fall back to the next font in the stack. Test on a representative content page before deploying to production.

---

## Output Preset: Keep The CSS Small

**Location:** `Settings → Output → Output Preset`

The output preset controls how much CSS the plugin generates. Less CSS = smaller stylesheet payload.

| Preset | What it generates | Best for |
|---|---|---|
| `Minimal` | Only `--font-heading` and `--font-body` CSS variables | Most themes and builders that only need role variables |
| `Variables only` | Full variable surface (roles, families, categories, aliases, weight tokens) | Builder/theme setups that use CSS variables extensively |
| `Classes only` | Utility classes without the variable surface | Setups that apply font roles via class names instead of variables |
| `Custom` | Exactly what you configure | Advanced setups that need precise control |

**Performance tip:** start with `Minimal`. Switch to `Variables only` or `Custom` only if your theme or builder explicitly needs the additional variables or classes.

---

## Interaction With Page Caching Plugins

Tasty Custom Fonts writes the generated stylesheet to disk (in `File` delivery mode) and enqueues it as a versioned URL. However, **page caching plugins cache the full HTML output** of each page — including the `<link>` tags, preload hints, and inline CSS.

### What can go wrong

If you change font roles, output settings, or delivery profiles **without purging your page cache**:
- Visitors may see the old cached HTML referencing the old stylesheet
- New stylesheet URL versions may not be reflected until the page cache expires

### Best practice

1. After applying sitewide or changing Output settings: **purge your page cache** (WP Rocket, W3 Total Cache, LiteSpeed Cache, etc.)
2. After clearing plugin caches via `Settings → Developer`: purge your page cache
3. If your CDN (Cloudflare, Fastly, etc.) caches full HTML: purge that layer too

Some page caching plugins offer hooks or automatic cache-bust triggers. Tasty Custom Fonts does not integrate with specific caching plugins — purge manually after significant font changes.

### Generated stylesheet caching

The plugin caches the generated CSS content internally using WordPress transients (`tasty_fonts_css_v2` and `tasty_fonts_css_hash_v2`). The transient cache is invalidated automatically when font roles, library state, or Output settings change. You can manually force a cache clear via `Settings → Developer → Clear Plugin Caches`.

---

## Recommended Setups By Goal

### Maximum Core Web Vitals performance

- CSS Delivery: **File**
- Minify: **On**
- font-display: **optional**
- Preload Primary Fonts: **On**
- Output Preset: **Minimal**
- Format: **WOFF2** self-hosted
- Variable fonts: use if the family is available in variable format and you need more than two weights
- Remote Connection Hints: **Off** (if fully self-hosted)

### Brand-correct typography (swap always on)

- font-display: **swap**
- Preload Primary Fonts: **On** (reduces the gap before the swap)
- Remote Connection Hints: **On** (if using any CDN delivery)
- Everything else: same as above

### Multilingual or multi-script site

- Unicode Range Output: **Keep Imported Ranges** (preserves Google/Bunny subset metadata)
- Variable fonts: check availability per family
- Output Preset: **Custom** — you may need full variable/class surface for per-script role assignments

### GDPR-compliant, no third-party connections

- All families: **Self-hosted** delivery (Local upload, or Google/Bunny self-hosted import)
- Remote Connection Hints: **Off**
- See [GDPR And Font Privacy](GDPR) for the full compliance guide

---

## Notes

- The generated stylesheet URL includes a cache-busting hash query string that changes whenever the stylesheet content changes. This ensures browsers pick up the new version automatically.
- Preload hints are only emitted for the live (applied) heading and body role WOFF2 files. Draft roles are not preloaded.
- If `font-display: optional` causes body text to occasionally render in the fallback font on first visits, this is expected behaviour — the font loads within the optional window on subsequent visits once it is cached.
- Inline CSS delivery bypasses file-level browser caching but can sometimes work better behind strict reverse proxies that cache files but not full HTML responses — a trade-off worth testing in unusual setups.

## Related Docs

- [Settings](Settings) — full Output settings reference
- [Concepts](Concepts) — delivery profiles and the CSS pipeline
- [Provider: Local Fonts](Provider-Local-Fonts) — upload best practices and format advice
- [Provider: Google Fonts](Provider-Google-Fonts)
- [Provider: Bunny Fonts](Provider-Bunny-Fonts)
- [GDPR And Font Privacy](GDPR) — privacy implications of each delivery method
- [Troubleshooting: Generated CSS](Troubleshooting-Generated-CSS)
- [FAQ](FAQ)
