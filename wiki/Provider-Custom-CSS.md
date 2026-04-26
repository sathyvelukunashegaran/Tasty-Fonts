# Custom CSS URL Imports

Import fonts from a public CSS stylesheet that contains `@font-face` rules.

## Use This Page When

- a client, agency, CDN, or brand system gives you a CSS stylesheet URL instead of font files
- you want Tasty Fonts to parse many faces from one stylesheet before saving anything
- you need to choose between downloading the detected fonts to your server or serving the validated remote URLs
- you need to understand Phase 1 limits before troubleshooting an import

---

## What Phase 1 Supports

The **From URL** flow accepts one public HTTPS CSS stylesheet URL per dry run. The stylesheet must contain `@font-face` rules that point to WOFF2 or WOFF font files.

Supported in Phase 1:

- one CSS stylesheet URL per dry run
- public `https://` stylesheet URLs only
- WOFF2 and WOFF font sources
- relative, root-relative, protocol-relative, same-host, and cross-host font URLs when each resolved font URL passes validation
- multiple families and multiple faces in the same stylesheet
- raw `unicode-range` preservation, including subset faces that share family/weight/style/format
- self-hosted final import into Tasty Fonts' custom storage root
- remote-serving final import using Tasty Fonts-generated `@font-face` CSS
- duplicate detection, skip-by-default behavior, and advanced replacement for matching custom CSS profiles only
- read-only source URL history on custom CSS delivery profiles

Deferred to later phases:

- direct font-file URL imports
- TTF and OTF imports through the URL flow
- multiple CSS URLs in a single dry run
- editing or replacing a saved source URL
- a manual **Verify now** action for already-imported custom profiles
- automatic routing suggestions for Google, Bunny, or Adobe provider URLs
- friendly subset labels for common unicode ranges
- private URLs, authenticated URLs, custom headers, cookies, or bearer tokens

---

## Step-By-Step

### 1. Start A Dry Run

1. Open `Tasty Fonts → Font Library`.
2. Open the add-font flow and choose **From URL**.
3. Paste a public HTTPS CSS stylesheet URL.
4. Run the dry run.

The dry run validates the stylesheet URL, fetches a capped response, parses `@font-face` rules, resolves font URLs, validates WOFF2/WOFF files, and creates a short-lived server-side review snapshot. It does **not** save library data or write font files.

### 2. Review Detected Families And Faces

The review screen groups faces by family. Valid faces are selected by default. Unsupported or invalid faces remain visible with their reason but cannot be imported.

Check:

- family name
- weight and style
- format (`woff2` or `woff`)
- source host/domain
- `unicode-range` when present
- validation notes and warnings in the row details
- duplicate matches and duplicate handling choices when the family already has matching custom faces

Warnings are not always blockers. For example, remote-serving CORS, licensing, privacy, and availability warnings are meant to help you choose the right delivery mode.

### 3. Choose Delivery Mode

Choose one delivery mode for the import batch.

| Delivery mode | What happens | Best for |
|---|---|---|
| **Self-hosted** | Tasty Fonts downloads selected font files, validates them again, writes them under `wp-content/uploads/fonts/custom/`, and generates `@font-face` rules pointing to your site. | Privacy-first production use, GDPR-sensitive sites, and long-term control. |
| **Remote serving** | Tasty Fonts saves validated absolute remote font URLs and generates its own `@font-face` rules that point to those URLs. It does not enqueue the original stylesheet. | Cases where the external host must remain the source of truth or files cannot be stored locally. |

Self-hosted imports are not blocked by browser CORS warnings because visitors will load the downloaded copy from your site. Remote-serving imports should be reviewed more carefully: visitor browsers will request fonts from the remote host, and browser CORS policy can still affect whether fonts load.

### 4. Confirm The Final Import

Final import uses the server-side dry-run snapshot, selected face IDs, delivery choice, fallback choices, duplicate handling, and optional publish/activation choices. The browser does not provide trusted font URLs, family metadata, or file paths during final import.

By default:

- duplicates are skipped
- new custom families are library-only
- custom profiles added to an existing published family do not become active unless you explicitly choose that behavior
- generated assets refresh after any successful import
- partial success is allowed, so one failed face does not necessarily block unrelated successful faces

### 5. Confirm The Profile In The Library

After import, expand the family in the Font Library. Custom CSS delivery profiles show read-only source history, including:

- original source CSS URL
- source host
- self-hosted vs remote-serving delivery mode
- last verified timestamp when validation metadata is available

The source URL is history in Phase 1. To change it, run a new import rather than editing metadata in place.

---

## Limits And Safety Rules

The URL importer is intentionally strict because WordPress servers should not be used to fetch arbitrary internal resources.

| Limit or rule | Phase 1 behavior |
|---|---|
| Protocol | CSS and font URLs must resolve to public HTTPS URLs. |
| Internal targets | Localhost, private IPs, link-local addresses, single-label/internal-style hosts, and local development TLDs are blocked by default. |
| CSS size | Stylesheets over roughly 256 KB are rejected. |
| Face count | Stylesheets with more than 50 detected `@font-face` rules are rejected. |
| Unique font URLs | Validation is capped at 50 unique font URLs per dry run. |
| Font size | Each discovered font file is capped at roughly 10 MB. |
| Timeout | CSS and font requests use short request timeouts and report timeout-specific errors. |
| Formats | Only WOFF2 and WOFF are importable from URL in Phase 1. |
| Authentication | No custom headers, cookies, OAuth, bearer tokens, or private repository credentials are supported. |

Local development fixtures can be enabled only by code-level filters for controlled testing. Do not relax public URL validation on production sites.

---

## Troubleshooting

### The stylesheet URL is rejected immediately

Confirm the URL starts with `https://`, is publicly reachable, and does not point to localhost, a private network, a `.test`/`.local` style host, or a URL with embedded credentials.

### The dry run says no supported faces were found

The stylesheet may not contain `@font-face` rules, or its rules may point only to unsupported formats such as TTF, OTF, EOT, or SVG. Phase 1 imports WOFF2 and WOFF only.

### The stylesheet is too large or has too many faces

Split the source into smaller stylesheets and run separate imports. Phase 1 accepts one CSS URL at a time and caps both stylesheet size and detected face count.

### A face is visible but disabled

Open the row details. Common causes include an unsupported format, unreachable font URL, oversized font file, invalid WOFF/WOFF2 signature, blocked private URL, or an HTTP status other than 200.

### A remote-serving face has warnings

Remote-serving keeps visitor browser requests pointed at the remote host. Review licensing, privacy, availability, and CORS warnings before confirming. If you want to avoid visitor requests to third-party font hosts, use self-hosted delivery instead.

### A self-hosted final import fails after a successful dry run

The remote file may have changed after review, become unreachable, exceeded the final download limit, or returned bytes that no longer match the reviewed validation metadata. Run the dry run again so the snapshot matches the current source.

### Imported fonts do not appear on the frontend

Open the family in the Font Library and confirm it is `Published` or `In Use`, not `In Library Only`. Also confirm the intended delivery profile is active. New custom families start as library-only by default unless you intentionally publish or activate them during import.

### I want to update a saved source URL or verify it later

That is deferred beyond Phase 1. For now, run a new From URL import from the current stylesheet. A future phase can add a compatibility-checked source replacement or no-mutation verification action.

---

## Related Docs

- [Font Library](Font-Library)
- [Imports And Deliveries](Troubleshooting-Imports-and-Deliveries)
- [GDPR And Font Privacy](GDPR)
- [Caching And Font Loading](Caching-And-Font-Loading)
- [Local Fonts](Provider-Local-Fonts)
- [Architecture](Architecture)
- [Testing](Testing)
