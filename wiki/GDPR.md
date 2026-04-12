# GDPR And Font Privacy

Understand what each delivery method sends to third-party servers and how to configure Tasty Custom Fonts for a GDPR-compliant setup.

> **Beginner tip:** GDPR (and similar laws such as the UK GDPR and ePrivacy Directive) care about whether your site sends visitor data — specifically IP addresses — to servers outside your control without consent. Font delivery is one of the most commonly overlooked areas. This page explains exactly what each delivery method does and how to minimise third-party data exposure.

> **Legal disclaimer:** this page explains the technical behaviour of Tasty Custom Fonts' delivery options. It is not legal advice. Your obligations depend on your specific jurisdiction, site audience, and data processing agreements. Consult a qualified data protection expert or lawyer for legal guidance specific to your situation.

---

## The Core Question: Who Receives Your Visitors' IP Addresses?

Every time a browser loads a resource — whether that is a font file or a CSS stylesheet — it sends an HTTP request to the host serving that resource. That request includes the visitor's IP address.

When fonts are **self-hosted on your own server**, that request stays on your infrastructure.

When fonts are **delivered via a third-party CDN** (Google, Bunny, or Adobe), the visitor's IP address is sent to that third-party's servers with every page load.

Under GDPR, transferring personal data (IP addresses are personal data under GDPR) to third-party processors or controllers — especially in non-EEA jurisdictions — may require either a lawful basis, a Data Processing Agreement, or explicit user consent.

---

## Delivery Method Comparison

| Delivery method | Third-party data exposure | GDPR risk level | Notes |
|---|---|---|---|
| **Self-hosted (Local upload)** | None | Lowest | All requests stay on your server |
| **Self-hosted (Google Fonts download)** | None at runtime | Lowest | Files are downloaded once to your server; visitors never contact Google |
| **Self-hosted (Bunny Fonts download)** | None at runtime | Lowest | Files are downloaded once; visitors never contact Bunny.net |
| **Bunny CDN** | Bunny.net (EU infrastructure) | Lower | Visitor IPs reach Bunny servers; Bunny is GDPR-friendly but still third-party |
| **Google CDN** | Google (US infrastructure) | Higher | Visitor IPs are sent to Google servers on every page load |
| **Adobe Fonts (hosted)** | Adobe (US infrastructure) | Higher | Visitor IPs are sent to Adobe CDN on every page load |

---

## Self-Hosted Delivery (The Privacy-First Option)

When you self-host fonts — regardless of whether the source was a local upload, a Google Fonts import, or a Bunny Fonts import — **no visitor data reaches any third-party server at font-load time**.

The plugin downloads font files to `wp-content/uploads/fonts/` on your server. At runtime, the plugin emits `@font-face` rules pointing to those local paths. The visitor's browser fetches the font directly from your domain.

### How to achieve fully self-hosted delivery

1. **For fonts you own:** upload them via the Local Fonts flow. See [Local Fonts](Provider-Local-Fonts).
2. **For Google Fonts:** import with `Self-hosted` mode (not `CDN`). The plugin downloads the WOFF2 files to your server. See [Google Fonts](Provider-Google-Fonts).
3. **For Bunny Fonts:** import with `Self-hosted` mode. The plugin downloads files from Bunny.net once and serves them locally afterwards. See [Bunny Fonts](Provider-Bunny-Fonts).
4. **Disable Remote Connection Hints** in `Settings → Output` if all your active deliveries are self-hosted. This stops the plugin emitting `<link rel="preconnect">` tags to CDN origins.

> **Beginner tip:** after switching from CDN to self-hosted delivery, go to `Advanced Tools → Generated CSS` and verify the `@font-face` rules point to your domain (`/wp-content/uploads/fonts/...`), not to `fonts.googleapis.com` or `fonts.bunny.net`.

---

## Google CDN Delivery And GDPR

When you use Google Fonts CDN delivery (rather than self-hosted), the visitor's browser contacts `fonts.googleapis.com` and `fonts.gstatic.com` directly on every page load. This sends the visitor's IP address to Google's servers, which are operated under US law.

**Under GDPR, this may require:**
- A Data Processing Agreement (DPA) with Google — Google Workspace terms include a DPA, but a personal API key may not automatically carry a DPA.
- User consent if your legal basis for processing requires it.
- A disclosure in your Privacy Policy about third-party font delivery.

**What the plugin emits for Google CDN:**
- A `<link>` tag loading the Google Fonts CSS stylesheet (`fonts.googleapis.com/css2?...`)
- Optionally a `<link rel="preconnect">` to `https://fonts.googleapis.com` and `https://fonts.gstatic.com` when Remote Connection Hints is enabled

**To eliminate Google data exposure:** switch all Google families to self-hosted delivery in the Font Library.

---

## Bunny CDN Delivery And GDPR

Bunny.net operates its font CDN from European infrastructure and positions itself as a GDPR-friendly alternative to Google Fonts. Bunny does not log individual font requests in a way that ties them to visitor identities, and operates under EU data protection law.

That said, **visitor IP addresses are still sent to Bunny.net's servers** on every page load when using CDN delivery. Whether this satisfies your specific legal obligations depends on your jurisdiction, your users' expectations, and whether Bunny.net's data processing terms meet your requirements.

**What the plugin emits for Bunny CDN:**
- A `<link>` tag loading the Bunny Fonts CSS stylesheet (`fonts.bunny.net/css?...`)
- Optionally a `<link rel="preconnect">` to `https://fonts.bunny.net` when Remote Connection Hints is enabled

**For maximum privacy:** switch Bunny families to self-hosted delivery. Files are downloaded once to your server; visitors never contact Bunny.net after that.

**For a practical GDPR-friendly CDN choice (if self-hosting is not possible):** Bunny CDN is the lowest-risk hosted font CDN option the plugin supports. Review Bunny.net's privacy policy and DPA terms for your use case.

---

## Adobe Fonts Delivery And GDPR

Adobe Fonts are always delivered from Adobe's CDN (`use.typekit.net`). The plugin never downloads Adobe font files locally — this is a fundamental limitation of the Adobe Fonts platform.

**What this means for GDPR:**
- Every page load sends visitor IP addresses to Adobe's servers.
- Adobe is a US company. You may need a Standard Contractual Clause (SCC) or rely on Adobe's Privacy Shield successor arrangements.
- Adobe Fonts is typically covered under Adobe Creative Cloud agreements, which include data processing terms — check your organisation's agreement.

**If you need zero third-party exposure:** you cannot use Adobe Fonts delivery and achieve it. Consider migrating those families to self-hosted delivery using equivalent open-source alternatives from Google or Bunny (self-hosted mode), or via local upload.

---

## Remote Connection Hints And GDPR

Remote Connection Hints (the `<link rel="preconnect">` tags the plugin emits) instruct the visitor's browser to **open a TCP/TLS connection to a remote origin early** — before the font stylesheet has even been parsed.

This means that even if a font is ultimately served from your server, if the plugin emits a `preconnect` hint for a CDN origin, the visitor's browser will still contact that CDN.

**Setting location:** `Settings → Output → Remote Connection Hints`

**Best practice:** disable Remote Connection Hints if all your active deliveries are self-hosted. The hints are only useful when you have active CDN or Adobe deliveries.

> **Beginner tip:** if you want to audit exactly what the plugin emits, go to `Advanced Tools → Generated CSS` and also inspect your page's `<head>` HTML in your browser's dev tools. Look for `<link rel="preconnect">` tags — any domain listed there is a third-party connection your visitors' browsers will make.

---

## Practical GDPR Configuration Guide

### Goal: No third-party font data exposure

1. **Use self-hosted delivery** for all active families:
   - Local uploads → always self-hosted
   - Google Fonts → choose `Self-hosted` (not `CDN`) on import
   - Bunny Fonts → choose `Self-hosted` (not `CDN`) on import
   - Adobe Fonts → not possible; Adobe requires remote delivery
2. **Disable Remote Connection Hints** in `Settings → Output`.
3. Verify `Advanced Tools → Generated CSS` shows only `@font-face` rules pointing to `/wp-content/uploads/fonts/...`.
4. Inspect your page `<head>` in a browser and confirm no `<link rel="preconnect">` tags point to external font origins.

### Goal: Bunny CDN only (GDPR-friendly CDN)

1. Import all families via Bunny Fonts CDN delivery.
2. Ensure Remote Connection Hints is enabled (for preconnect to `fonts.bunny.net`).
3. Disable Google Fonts and Adobe Fonts deliveries.
4. Add Bunny.net to your Privacy Policy as a third-party font service.

### Goal: Consent-gated CDN delivery

If you must use Google or Adobe CDN and want to gate delivery behind user consent (for example via a consent management platform):

1. Keep the plugin's Remote Connection Hints **disabled** so preconnects are not sent before consent.
2. Use your consent platform to conditionally load the font stylesheet after consent is given.
3. This is an advanced use case beyond the plugin's built-in controls. The `tasty_fonts_generated_css` filter and the plugin's Runtime CSS pipeline can be modified by a developer to support deferred delivery. See [Architecture](Architecture) for extension points.

---

## Privacy Policy Recommendations

Regardless of which delivery methods you use, document your font setup in your site's Privacy Policy. At minimum, state:

- **Which providers deliver fonts** to your visitors (Google, Bunny, Adobe, or self-hosted)
- **What data is collected** by each provider (typically: IP address, browser user-agent, referring URL)
- **The legal basis** for processing that data
- **Where data is transferred** (e.g., US for Google and Adobe; EU for Bunny)
- **A link to the provider's privacy policy** so users can read their terms

---

## Notes

- Switching from CDN to self-hosted delivery requires a re-import of the affected family. The plugin cannot retroactively download files for families that were imported in CDN mode.
- After switching delivery modes, go to `Settings → Developer → Clear Plugin Caches` to force the generated stylesheet to refresh.
- The plugin does not set any browser cookies or use local storage. Font delivery is purely HTTP request-based.
- Block Editor Font Library Sync uses loopback requests from your server to itself — it does not contact any third-party service.

## Related Docs

- [Concepts](Concepts) — delivery profiles and the CSS pipeline
- [Provider: Google Fonts](Provider-Google-Fonts)
- [Provider: Bunny Fonts](Provider-Bunny-Fonts)
- [Provider: Adobe Fonts](Provider-Adobe-Fonts)
- [Provider: Local Fonts](Provider-Local-Fonts)
- [Settings](Settings) — Remote Connection Hints and other output controls
- [Caching And Font Loading](Caching-And-Font-Loading) — performance practices alongside privacy choices
- [Glossary](Glossary)
