# Custom CSS Font Import — Vertical Slice Plan

## Parent PRD

- [ ] Yes — Future agent has read the parent PRD
- [ ] No — Future agent has not read the parent PRD

Parent PRD: [`docs/prd/custom-css-font-import.md`](../../docs/prd/custom-css-font-import.md)

This plan breaks the PRD into independently grabbable tracer-bullet slices. Each slice should be demoable or verifiable on its own and should cut through the relevant layers end-to-end: service behavior, REST contract, admin UI where applicable, persistence/storage where applicable, generated output where applicable, and tests.

Do not create GitHub issues from this file unless separately instructed later.

## Durable Architectural Decisions

- [ ] Yes — Reviewed before implementation
- [ ] No — Not reviewed before implementation

1. The feature is phased. Phase 1 covers custom CSS stylesheet URL imports only.
2. Phase 1 accepts one public HTTPS CSS URL per dry run.
3. Phase 1 supports WOFF2 and WOFF only.
4. Direct font-file URL imports are deferred.
5. Multiple CSS URLs in one dry run are deferred.
6. The import flow is mandatory dry run followed by explicit final confirmation.
7. The dry run stores a short-lived, single-use, server-side normalized plan snapshot.
8. The final import must trust only the server-side snapshot, selected face IDs, and explicit user choices.
9. The browser must not be trusted for font URLs, family metadata, weights, styles, or file paths during final import.
10. Tasty Fonts generates its own `@font-face` CSS from saved face metadata.
11. The original third-party stylesheet is never enqueued as runtime CSS.
12. Custom imports use the existing family + delivery-profile model.
13. Custom delivery profiles use an internal custom provider key.
14. Custom self-hosted files live under a custom provider storage root.
15. Remote-serving profiles save validated absolute remote font URLs.
16. Relative font URLs are resolved against the source CSS URL and revalidated.
17. Font URLs may use a different public host than the CSS URL if each URL passes validation.
18. Source `font-display` is ignored; existing Tasty Fonts font-display settings remain authoritative.
19. Source `unicode-range` is preserved.
20. Custom subset faces must remain distinct when weight/style/format match but unicode ranges differ.
21. Duplicate/replacement matching for custom faces includes family, weight, style, format, delivery type, unicode range, and variable axes where applicable.
22. Duplicates are skipped by default.
23. Replacement is advanced and applies only to matching custom CSS faces/profiles.
24. Known provider and local upload profiles are protected from custom replacement cleanup.
25. Cleanup deletes old custom files only after successful replacement and only if no remaining profile references the file.
26. New custom profiles do not become active by default for existing published families.
27. New custom families are library-only by default.
28. Partial success is allowed for batch imports.
29. Generated assets refresh after any successful final import.
30. Custom CSS source URL metadata is stored on custom profiles and shown only for custom profiles.
31. Source URL editing/replacement is deferred and must require compatibility verification when implemented.
32. Security defaults are product requirements: HTTPS-only, block private/internal targets, enforce request size/time limits, validate file signatures, and avoid server overload.
33. CORS is a best-effort warning for remote serving, not a hard block.
34. Remote-serving shows privacy, licensing, availability, and CORS warnings.
35. No custom authentication headers, cookies, or private credentials are supported in Phase 1.

---

# Phase 1 — Custom CSS URL Import

## Slice 0 — Advanced developer feature gate

- [x] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [x] AFK
- [ ] HITL

**User stories covered**

- Governance/security prerequisite for Phase 1 URL imports. This slice gates the entire custom CSS URL import workflow before Slice 1 becomes available in production UI.

**What to build**

Add a default-off expert feature gate for custom CSS URL imports. Because URL imports can fetch remote CSS, download or save fonts, and create custom delivery profiles, the workflow must be explicitly enabled by an advanced user before the From URL UI or REST endpoints can be used.

**Placement decision**

- Put the toggle in **Advanced Tools → Developer**, not regular Behavior settings. This keeps the feature alongside other advanced/developer-only capabilities and makes the risk/expert expectation clear.

**Acceptance criteria**

- [x] A persisted setting exists for enabling custom CSS URL imports.
- [x] The setting defaults to OFF for existing and new installs.
- [x] The setting is surfaced in Advanced Tools → Developer with clear expert/security copy.
- [x] When OFF, the From URL UI is hidden or disabled with a clear explanation and path to Advanced Tools → Developer.
- [x] When OFF, custom CSS dry-run REST requests are rejected with a useful error.
- [x] When OFF, custom CSS final-import REST requests are rejected with a useful error.
- [x] When ON, the From URL dry-run UI is available.
- [x] When ON, dry-run and final-import REST flows work through the existing safety/snapshot contracts.
- [x] The gate does not weaken URL safety, snapshot, duplicate, or final-import validation.
- [x] Tests cover default-off behavior, enabled behavior, UI visibility/disabled state, dry-run REST rejection, final-import REST rejection, and no regression to enabled imports.

**Test checklist**

- [x] PHP tests for default-off setting value.
- [x] PHP/admin renderer tests for the disabled/hidden From URL UI state.
- [x] REST tests for dry-run rejection when disabled.
- [x] REST tests for final-import rejection when disabled.
- [x] REST/service tests proving enabled dry-run still succeeds.
- [x] REST/service tests proving enabled final import still succeeds.
- [x] JS contract tests for gated UI copy/state, if the gate is rendered client-side.
- [x] `php tests/run.php`.
- [x] `node --test tests/js/*.test.cjs`.
- [x] `composer phpstan`.
- [x] PHPStan level 10/max check if repo config supports it.
- [x] `npm run lint:css` if CSS changes.
- [x] `bin/run-jscpd` duplication check.
- [x] `git diff --check`.

**Live verification checklist**

- [x] Create a rollback snapshot before enabling/importing.
- [x] Confirm From URL is unavailable or clearly gated while the setting is OFF.
- [x] Enable the setting in Advanced Tools → Developer.
- [x] Confirm From URL becomes available.
- [x] Run a live dry run for `https://unpkg.com/@fontsource/inter@5.0.0/index.css` or another supported public CSS URL.
- [x] Confirm the review shows selectable WOFF2/WOFF faces and warnings.
- [x] Run a live self-hosted final import.
- [x] Confirm imported custom profile appears in the library with source history and local custom storage paths.
- [x] Run a live remote-serving final import.
- [x] Confirm imported custom remote profile appears in the library with source history and remote delivery mode.
- [x] Confirm generated CSS contains Tasty-generated `@font-face` rules and does not enqueue the original third-party stylesheet.
- [x] Restore the rollback snapshot or otherwise confirm rollback path is available after mutation testing.

**Dependencies**

- None as a governance prerequisite. Slices 1–10 must respect this gate once implemented.

**Orchestrator progress**

- 2026-04-27: Added after user explicitly required the custom CSS URL import workflow to be advanced/developer-enabled rather than shipping automatically. Implementation delegated to sub-agent `EDEDCAA9-6460-4A51-B009-363FB6391C56`; final import UI wiring was handled by sub-agent `E0FCDB68-F85B-4B1B-A0B0-67AC01F6E7B7`.
- 2026-04-27: Completed Slice 0 verification. Static/test coverage includes default-off setting, gated renderer state, REST rejection while disabled, enabled dry-run/final-import regression, JS gated/error UI contracts, `php tests/run.php`, `node --test tests/js/*.test.cjs`, `composer phpstan`, PHPStan level 10, CSS lint, jscpd, and `git diff --check`.
- 2026-04-27: Live WP verification completed with rollback snapshot `snapshot-20260426-221512-50532712`: From URL was gated while OFF, enabled via Advanced Tools → Developer, dry-run succeeded for `https://unpkg.com/@fontsource/roboto@5.0.0/400.css` with seven WOFF2 faces, self-hosted import created local custom storage paths, remote import created a remote custom delivery profile, generated CSS used Tasty-generated `@font-face` rules without the original stylesheet URL, and the snapshot restore returned the local site to the pre-test four-family/gated state.

---

## Slice 1 — From URL dry-run tracer path

- [x] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [x] AFK
- [ ] HITL

**User stories covered**

- 1, 2, 5, 6, 7, 64, 66, 69, 70

**What to build**

Create the first end-to-end dry-run path for a custom CSS URL. A user can open the Add Fonts “From URL” panel, enter a public HTTPS CSS URL, submit a dry run, and see a normalized review result for a simple stylesheet containing valid `@font-face` rules. This slice establishes the service boundary, REST route, UI entry point, and tests for the smallest successful dry-run loop.

**Acceptance criteria**

- [x] A “From URL” entry point exists under Add Fonts.
- [x] The panel clearly explains that Phase 1 expects a CSS stylesheet containing `@font-face`.
- [x] A dry-run REST endpoint accepts one CSS URL.
- [x] A custom CSS import service can fetch a controlled test stylesheet and return a normalized plan.
- [x] A simple valid stylesheet with one family and one WOFF2 or WOFF face appears in the UI review area.
- [x] No library data is saved during dry run.
- [x] No font files are written during dry run.
- [x] The dry-run response has a stable shape suitable for later final import.
- [x] PHP tests cover a successful dry-run service result.
- [x] REST tests cover dry-run success and basic failure response shape.
- [x] JS contract tests cover submitting a URL and rendering a dry-run result.

**Dependencies**

- None — can start immediately.

**Orchestrator progress**

- 2026-04-27: Implemented by sub-agent `C9824AC3-4C67-44B0-AB7D-FD18D8BCA446`. Acceptance criteria reviewed and ticked by orchestrator from implementation evidence plus targeted verification.
- Evidence by acceptance criterion:
  - From URL entry point: `tests/cases/admin-renderer.php` asserts `id="tasty-fonts-add-font-tab-url"`; live admin smoke also showed the From URL tab under Add Family.
  - Phase 1 CSS `@font-face` explanation: `tests/cases/admin-renderer.php` asserts the panel copy: “Run a dry run on a public HTTPS CSS stylesheet that contains @font-face rules.”
  - Dry-run REST endpoint accepts one CSS URL: `includes/Api/RestController.php` registers `POST custom-css/dry-run`; `tests/cases/admin-controller-rest.php` covers route reference and successful request body with one `url`.
  - Service fetches controlled CSS and returns normalized plan: `tests/cases/imports-library.php` test `custom_css_url_import_service_returns_normalized_dry_run_plan_without_writes` mocks a controlled stylesheet and asserts `status`, `source`, `families`, `counts`, face metadata, and source/face hosts.
  - Simple one-family WOFF2/WOFF review result: PHP service tests cover one WOFF2 and one WOFF face; JS contract test `admin contracts render custom CSS dry-run review HTML safely` covers review HTML for one family/face.
  - No library data saved during dry run: service tests assert `$services['imports']->all()` remains empty after successful and limit/error dry runs.
  - No font files written during dry run: implementation only fetches stylesheet CSS in Slice 1 and does not call storage/write paths; service tests assert only stylesheet fetch behavior and no import records.
  - Stable dry-run response shape: service/REST/JS tests assert `status`, `message`, `plan.source`, `plan.families`, `plan.counts`, face IDs, selected/status fields, and normalized client shape.
  - PHP tests cover success: `tests/cases/imports-library.php` includes successful WOFF2 and WOFF dry-run tests.
  - REST tests cover success/failure: `tests/cases/admin-controller-rest.php` includes `rest_controller_custom_css_dry_run_returns_review_plan` and `rest_controller_custom_css_dry_run_returns_basic_failure_shape`.
  - JS contract tests cover submit/render: `tests/js/admin-contracts.test.cjs` covers `buildCustomCssDryRunRequest`, `normalizeCustomCssDryRunPlan`, and `renderCustomCssDryRunReviewHtml`.
- Verification run by sub-agent: `php tests/run.php`, `node --test tests/js/*.test.cjs`, `composer phpstan`, `npm run lint:css`, and new-service PHP lint passed.
- Verification rerun by orchestrator: `php tests/run.php && node --test tests/js/*.test.cjs` passed; orchestrator spot-checked service/REST/template/JS/CSS and live-smoked the admin UI.
- Caveat: live admin confirmed the From URL panel renders and reports errors. A live successful browser dry-run was not proven because the local HTTPS fixture fetch was blocked by local CA trust; successful review rendering is covered by deterministic PHP/REST/JS tests. Fontsource relative URLs are intentionally deferred to Slice 3.

---

## Slice 2 — URL safety and workload limits in dry run

- [x] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [x] AFK
- [ ] HITL

**User stories covered**

- 56, 57, 58, 63, 64, 65, 69

**What to build**

Harden the dry-run URL and fetch pipeline so unsafe or excessive inputs fail before any import plan can be used. This slice turns the first tracer into a safe public-URL-only workflow with clear diagnostics.

**Acceptance criteria**

- [x] HTTP URLs are rejected.
- [x] Localhost URLs are rejected.
- [x] Private IP URLs are rejected.
- [x] Link-local and internal network targets are rejected.
- [x] Malformed URLs are rejected with useful errors.
- [x] CSS responses over the configured size cap are rejected.
- [x] Stylesheets with too many detected faces are rejected or capped with a clear diagnostic.
- [x] Unique font URL validation count is capped.
- [x] Request timeout behavior produces a clear user-facing error.
- [x] Errors appear in the From URL UI without saving data.
- [x] Tests cover allowed public HTTPS URL behavior.
- [x] Tests cover blocked URL classes and oversized/excessive inputs.

**Dependencies**

- Slice 1.

**Orchestrator progress**

- 2026-04-27: Implemented by sub-agent `B3A3EFCD-4EB3-4546-A3D1-4CE7A6CF447A`. Acceptance criteria reviewed and ticked by orchestrator from implementation evidence plus targeted verification.
- Evidence by acceptance criterion:
  - HTTP, malformed, localhost, private IP, link-local, single-label, `.test`, `.local`, and internal-style stylesheet targets are rejected by `CustomCssUrlImportService`; covered by `custom_css_url_import_service_rejects_unsafe_stylesheet_targets_before_fetching` and REST safety tests.
  - Unsafe absolute font URLs inside otherwise fetched CSS are rejected, including private/internal font hosts; covered by `custom_css_url_import_service_rejects_unsafe_absolute_font_urls_in_stylesheets` and `custom_css_url_import_service_rejects_private_font_urls_in_stylesheets`.
  - CSS response size cap is enforced at 256 KB plus WordPress `limit_response_size`; covered by oversized stylesheet service and REST tests.
  - Too many detected faces and too many unique font URLs are rejected with clear diagnostics; covered by `custom_css_url_import_service_rejects_excessive_face_counts` and `custom_css_url_import_service_caps_unique_font_url_safety_validation`.
  - Timeout behavior uses a dedicated error and REST 504 mapping; covered by `custom_css_url_import_service_reports_timeout_and_relative_url_limitations_clearly` plus REST safety tests.
  - From URL UI renders accessible inline errors without saving data; covered by JS contract test `admin contracts render custom CSS dry-run errors as accessible inline UI` and service no-save assertions.
  - Allowed public HTTPS behavior remains covered by Slice 1 success tests plus Slice 2 safety regression tests.
  - A default-deny local-development filter seam `tasty_fonts_custom_css_allow_internal_dry_run_url` was added; default denial is covered by `custom_css_url_import_service_keeps_internal_url_allow_filter_default_deny`.
- Verification rerun by orchestrator: custom CSS PHP harness tests visible as `[PASS]`, `node --test tests/js/admin-contracts.test.cjs` passed 34/34, `composer phpstan` passed, and PHP lint passed for `includes/CustomCss/CustomCssUrlImportService.php` and `tests/cases/imports-library.php`.
- Live local verification: created local-only MU helper at `/Users/sk-mbp/Herd/wp-etch-sk/wp-content/mu-plugins/tasty-fonts-local-http.php` to opt in same-host `.test` stylesheet fixtures and disable PHP cURL SSL verification only for that local host. Live admin dry-run of `https://wp-etch-sk.test/wp-content/uploads/tasty-fonts-fixtures/custom-css-dry-run.css` succeeded and rendered `Fixture Sans`, `1 family, 1 face`, and `cdn.example.com`.
- Deferred intentionally: relative URL resolution and Fontsource fixtures remain Slice 3; font file reachability/signature validation remains Slice 4.

---

## Slice 3 — CSS parsing, relative URL resolution, and face identity

- [x] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [x] AFK
- [ ] HITL

**User stories covered**

- 3, 4, 17, 18, 19, 20, 21, 52, 53, 54, 70

**What to build**

Expand the dry-run parser and plan builder to handle real custom CSS: multiple families, multiple faces, WOFF2 and WOFF sources, relative font URLs, cross-host font URLs, unicode ranges, and stable face identifiers that distinguish subset faces.

**Acceptance criteria**

- [x] Dry run detects multiple families from one stylesheet.
- [x] Dry run detects multiple faces per family.
- [x] WOFF2 sources are supported.
- [x] WOFF sources are supported.
- [x] Unsupported formats are surfaced as unsupported/disabled entries.
- [x] Relative font URLs are resolved against the CSS URL.
- [x] Resolved font URLs are revalidated after resolution.
- [x] Cross-host font URLs are allowed when independently safe.
- [x] `unicode-range` is preserved in the normalized plan.
- [x] Faces with the same family/weight/style/format but different unicode ranges remain distinct.
- [x] Stable face IDs are generated from normalized server-side data.
- [x] Tests cover WOFF2, WOFF, unsupported formats, relative URLs, cross-host URLs, multiple families, multiple faces, and unicode-range subset identity.

**Dependencies**

- Slice 2.

**Orchestrator progress**

- 2026-04-27: Implemented by sub-agent `30733D85-67B8-4D95-9865-3CA21690459E`; narrow follow-up in same session fixed dry-run summary pluralization. Acceptance criteria reviewed and ticked by orchestrator from implementation evidence plus targeted verification.
- Evidence by acceptance criterion:
  - Multiple families and multiple faces are covered by `custom_css_url_import_service_parses_fontsource_style_relative_subset_faces`, including Inter subset faces plus another family.
  - WOFF2 and WOFF support are covered by existing WOFF2/WOFF dry-run tests plus Slice 3 fixtures.
  - Unsupported formats are surfaced as disabled/unselected review rows; covered by `custom_css_url_import_service_surfaces_unsupported_formats_as_disabled_faces` and JS contract `admin contracts render custom CSS dry-run unsupported faces as disabled rows`.
  - Relative, root-relative, and protocol-relative URL resolution is implemented in `CustomCssUrlImportService`; Fontsource-style relative fixture and live Fontsource URL prove relative URL behavior.
  - Resolved URLs are revalidated; private/internal and credentialed font URL cases are rejected by Slice 2/3 safety tests after resolution.
  - Cross-host public font URLs remain allowed when independently safe; covered by fixtures using `cdn.example.com`.
  - `unicode-range` is preserved and subset faces with identical family/weight/style/format remain distinct; covered by Fontsource-style subset fixtures and live Fontsource result showing seven Inter subset rows with distinct unicode ranges.
  - Stable face IDs are generated from normalized server-side data; covered by reordered/equivalent CSS fixture assertions in `tests/cases/imports-library.php`.
  - JS review summary pluralization is covered by `admin contracts pluralize custom CSS dry-run review summaries`.
- Verification reported by sub-agent: `php tests/run.php`, `node --test tests/js/*.test.cjs`, `composer phpstan`, PHP lint for the service and imports tests, and a mocked harness check of `https://unpkg.com/@fontsource/inter@5.0.0/index.css` returning `families=1 faces=7 valid=7`.
- Verification rerun by orchestrator: `node --test tests/js/admin-contracts.test.cjs` passed 36/36 after pluralization fix. Live admin dry-run of `https://unpkg.com/@fontsource/inter@5.0.0/index.css` succeeded and rendered “Found 7 supported font faces”, “1 family, 7 faces”, `Inter`, seven WOFF2 subset rows, and their unicode ranges.
- Deferred intentionally: font URL reachability, content type, size, and WOFF/WOFF2 signature validation remain Slice 4.

---

## Slice 4 — Font URL validation and review warnings

- [x] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [x] AFK
- [ ] HITL

**User stories covered**

- 8, 9, 10, 40, 41, 42, 45, 46, 47, 59, 60, 71

**What to build**

Validate discovered font URLs during dry run and present valid, warning, and invalid states in the review UI. Use lightweight validation where possible and expose pro-friendly details without overwhelming the default view.

**Acceptance criteria**

- [x] Valid faces are selected by default.
- [x] Invalid faces are disabled and unselected.
- [x] Non-blocking warning faces remain visible and selectable where appropriate.
- [x] Font URL validation checks reachability, status, content type class, size limit, supported format, and WOFF/WOFF2 signature where possible.
- [x] Validation prefers lightweight checks when possible.
- [x] Validation can fall back to a capped GET when necessary.
- [x] Remote-serving mode shows privacy/licensing/availability warning copy.
- [x] Cross-origin remote font URLs show best-effort CORS warnings when headers look risky.
- [x] Self-hosted mode does not treat browser CORS as a blocking issue.
- [x] Review rows show host/domain by default.
- [x] Review details expose full resolved URL and validation notes.
- [x] Tests cover valid URLs, invalid signatures, unreachable URLs, oversized files, warning states, and UI rendering of validation states.

**Dependencies**

- Slice 3.

**Orchestrator progress**

- 2026-04-27: Implemented by sub-agent `987C4742-147E-45DF-9D64-3F03D4D2E4CD`. Acceptance criteria reviewed and ticked by orchestrator from implementation evidence plus targeted verification and live admin smoke.
- Evidence by acceptance criterion:
  - Valid faces default selected; invalid/unsupported faces disabled and unselected; warning faces remain selected/selectable. Covered by `CustomCssUrlImportService::buildFace()`, `normalizeCustomCssDryRunPlan()`, `renderCustomCssDryRunReviewHtml()`, `custom_css_url_import_service_marks_invalid_signature_faces_disabled`, `custom_css_url_import_service_marks_unreachable_font_urls_disabled`, `custom_css_url_import_service_marks_oversized_font_urls_disabled`, and JS validation-state rendering tests.
  - Font URL validation covers reachability/status, content type class, size limit, supported WOFF/WOFF2 formats, and WOFF/WOFF2 signatures. Implementation prefers HEAD metadata, then validates bytes with a range/capped GET; HEAD-unavailable/405-style responses fall back to capped GET.
  - Remote-serving privacy, licensing, availability, and CORS warnings are included as non-blocking plan/face warnings; self-hosted imports are explicitly called out as not blocked by browser CORS.
  - Review rows show host/domain by default while details expose resolved URL, method, content type, size, signature notes, and warnings.
  - Tests cover valid URLs, invalid signatures, unreachable URLs, oversized files, warning states, capped fallback, and UI rendering of validation states.
- Verification reported by sub-agent: `php tests/run.php`, `node --test tests/js/*.test.cjs`, `composer phpstan`, `npm run lint:css`, `node bin/lint-design`, and changed-file PHP lint passed; an Oracle review was run and addressed same-host CORS warning/default selection concerns.
- Verification rerun by orchestrator: `php tests/run.php`, `node --test tests/js/admin-contracts.test.cjs`, `composer phpstan`, `npm run lint:css`, and PHP lint for `includes/CustomCss/CustomCssUrlImportService.php`, `tests/cases/imports-library.php`, and `tests/cases/admin-controller-rest.php` passed.
- Live admin verification: dry-run of `https://unpkg.com/@fontsource/inter@5.0.0/index.css` rendered `Found 7 selectable font faces`, `1 family, 7 faces`, `Inter`, seven checked WOFF2 rows, host `unpkg.com`, unicode ranges, warning labels, and details showing resolved URL, `HEAD + range GET`, `WOFF2 signature matched`, size recheck caveat, and remote-serving/CORS warning copy.
- `/impeccable audit` spot-check: product-register UI remains restrained and WordPress-native, no blocking a11y/performance/theming/anti-pattern issues found, and a narrow viewport check showed no document-level horizontal overflow.

---

## Slice 5 — Snapshot token and tamper-resistant final import contract

- [x] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [x] AFK
- [ ] HITL

**User stories covered**

- 5, 61, 62, 63, 71

**What to build**

Persist dry-run review plans as short-lived, single-use, server-side snapshots and make the final import contract consume only snapshot token, selected face IDs, delivery choice, fallback choices, duplicate handling, and optional activation/publish choices.

**Acceptance criteria**

- [x] Dry run stores a normalized server-side snapshot.
- [x] Snapshot does not store raw CSS unless strictly necessary.
- [x] Snapshot token is returned to the browser.
- [x] Snapshot expires after approximately fifteen minutes.
- [x] Snapshot is single-use.
- [x] Snapshot is scoped to current site and current user/admin context.
- [x] Final import rejects missing, expired, reused, or mismatched tokens.
- [x] Final import rejects selected face IDs not present in the snapshot.
- [x] Final import ignores or rejects browser-submitted untrusted face metadata.
- [x] Tests cover token creation, expiry, single use, user/site scoping, tampered selected IDs, and untrusted browser payloads.

**Implementation note**

- 2026-04-27: Added server-side dry-run snapshot storage with 15-minute single-use tokens, current site/user/admin-context scoping, a minimal final-import validation endpoint/service boundary, and targeted PHP/JS coverage for token creation, expiry, single use, scoping, tampered selected IDs, and untrusted payload rejection. No Slice 6 self-hosted downloads or Slice 7 remote persistence were implemented.

**Orchestrator progress**

- 2026-04-27: Implemented by sub-agent `4FC65BE6-45DB-4330-83B1-3ED35711F0FB`. Acceptance criteria reviewed and ticked by orchestrator from code/test evidence plus live REST smoke.
- Evidence by acceptance criterion:
  - Dry run stores a normalized server-side snapshot through `CustomCssImportSnapshotService`, keyed by an HMAC-derived site-scoped transient token; snapshot stores normalized plan/message/scope and omits raw stylesheet CSS.
  - Dry-run REST responses return `snapshot_token`, `snapshot_expires_at`, and `snapshot_ttl_seconds`; TTL is `900` seconds.
  - Snapshot scope includes site ID, user ID, role slugs, and admin capabilities hashed with `wp_salt('auth')`; final import rejects user/site/admin-context mismatches.
  - Final import consumes a snapshot once after successful contract validation and rejects missing, expired, reused, mismatched, tampered, non-selectable, or untrusted payloads.
  - Final import reads selected face metadata from the server snapshot and rejects browser-submitted face/source metadata using an allowlisted request shape.
  - Tests cover token creation, no raw CSS storage, expiry, single use, user/site scoping, selected ID mismatch, non-selectable faces, and untrusted browser payloads.
- Verification reported by sub-agent: `php tests/run.php`, `node --test tests/js/admin-contracts.test.cjs`, `composer phpstan`, and changed-file PHP lint passed; Oracle review flagged allowlist enforcement, which was fixed.
- Verification rerun by orchestrator: `php tests/run.php`, `node --test tests/js/admin-contracts.test.cjs`, `composer phpstan`, and PHP lint for `includes/CustomCss/CustomCssImportSnapshotService.php`, `includes/Admin/AdminController.php`, `includes/Api/RestController.php`, and `tests/cases/admin-controller-rest.php` passed.
- Live REST verification: dry-run of `https://unpkg.com/@fontsource/inter@5.0.0/index.css` returned HTTP 200 with `snapshot_token`, `snapshot_expires_at`, `snapshot_ttl_seconds: 900`, one `Inter` family, seven selectable warning faces, and the expected normalized review data.

**Dependencies**

- Slice 4.

---

## Slice 6 — Self-hosted final import for selected custom faces

- [x] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [x] AFK
- [ ] HITL

**User stories covered**

- 11, 12, 13, 20, 22, 23, 24, 25, 26, 27, 28, 35, 36, 60, 63, 66, 67

**What to build**

Implement final self-hosted import from the snapshot. Selected faces are downloaded, validated, written to the custom provider storage root, saved as custom self-hosted delivery profiles, merged into existing families where appropriate, and reflected in generated assets.

**Acceptance criteria**

- [x] Custom provider storage root exists and is used for self-hosted custom imports.
- [x] Selected WOFF2 and WOFF files download during final import.
- [x] Final download validates format, signature, size, and URL safety before writing.
- [x] If dry run captured a full hash, final download must match it.
- [x] If the final file materially differs from reviewed data, that face fails with a clear message.
- [x] Saved faces use local relative paths.
- [x] Custom self-hosted delivery profiles are created with custom provider metadata.
- [x] Source CSS URL is stored in custom profile metadata.
- [x] Per-family fallback choices are persisted.
- [x] Existing families receive an additional delivery profile rather than a duplicate family.
- [x] New custom families default to library-only.
- [x] Existing families do not switch active delivery unless explicitly requested.
- [x] Generated assets refresh when anything succeeds.
- [x] Partial success saves successful faces/families and reports failures.
- [x] Tests cover storage writes, profile persistence, fallback persistence, family merge behavior, library-only defaults, active-delivery defaults, partial success, and generated asset refresh.

**Orchestrator progress**

- 2026-04-27: Implemented by sub-agent `3574F242-B29B-4739-9E2E-4D914C9D27FB`. Acceptance criteria reviewed and ticked by orchestrator from code/test evidence and targeted verification.
- Evidence by acceptance criterion:
  - `Storage` now exposes an `uploads/fonts/custom` provider root and `CustomCssFinalImportService` writes self-hosted custom files there.
  - Final import downloads selected WOFF2/WOFF files with full GETs, no range header, and validates public HTTPS safety, complete HTTP 200 responses, no `Content-Range`, content type, size, and WOFF/WOFF2 signatures before writing.
  - Reviewed size/hash material-difference checks fail changed faces with clear errors; full hash matching is supported when the dry-run validation data includes a hash.
  - Saved profile faces use local relative `files`/`paths`, custom provider metadata, original remote URL/host metadata, and custom profile `meta.source_css_url`/`meta.source_host`.
  - Per-family fallbacks persist; existing families receive an added delivery profile rather than a duplicate family; new custom families default to `library_only`; existing active delivery remains unchanged unless `activate` is explicitly requested.
  - Generated assets refresh after any successful import; partial success persists successful faces/families and reports failed faces.
  - Tests cover storage writes, WOFF2/WOFF persistence, profile metadata, fallback persistence, family merge behavior, library-only defaults, active-delivery defaults/activation override, partial success, material-difference failures, generated asset refresh, and custom subset CSS output.
- Verification reported by sub-agent: PHP syntax checks for Slice 6 files, `php tests/run.php`, `composer phpstan`, and `node --test tests/js/*.test.cjs` passed.
- Verification rerun by orchestrator: `php tests/run.php`, `node --test tests/js/*.test.cjs`, `composer phpstan`, and PHP lint for `includes/CustomCss/CustomCssFinalImportService.php`, `includes/CustomCss/CustomCssImportSnapshotService.php`, `includes/Support/Storage.php`, `includes/Repository/ImportRepository.php`, `includes/Fonts/CatalogService.php`, `tests/cases/imports-library.php`, and `tests/cases/admin-controller-rest.php` passed.
- 2026-04-27 follow-up: added explicit full-hash mismatch coverage via `custom_css_final_import_rejects_full_hash_mismatches`.
- Live note updated 2026-04-27: browser self-hosted final import was run against `https://unpkg.com/@fontsource/roboto@5.0.0/400.css` after creating a rollback snapshot. It imported Roboto as `Self-hosted custom CSS (unpkg.com, 2026-04-26)`, showed read-only source history, wrote seven local `custom/roboto/...woff2` paths, and was later rolled back via `snapshot-20260426-221512-50532712`. The live run exposed a recursive directory creation gap in `Storage::writeAbsoluteFile()`, which is now fixed and covered by `custom_css_final_import_self_hosted_writes_faces_when_filesystem_mkdir_is_non_recursive`.

**Dependencies**

- Slice 5.

---

## Slice 7 — Remote-serving final import for selected custom faces

- [x] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [x] AFK
- [ ] HITL

**User stories covered**

- 11, 12, 14, 15, 16, 18, 19, 24, 25, 27, 28, 39, 40, 45, 46, 47, 59, 66, 67

**What to build**

Implement final remote-serving import from the snapshot. Selected faces are saved as custom remote delivery profiles using validated absolute remote font URLs, and generated Tasty Fonts CSS emits controlled `@font-face` rules without enqueueing the original stylesheet.

**Acceptance criteria**

- [x] Remote-serving final import saves selected faces with absolute remote font URLs.
- [x] Final import performs a lightweight revalidation where appropriate.
- [x] The original third-party stylesheet is not enqueued.
- [x] Generated CSS contains Tasty Fonts-generated `@font-face` rules for remote URLs.
- [x] Existing font-display settings control emitted rules.
- [x] `unicode-range` is emitted when present.
- [x] Remote warnings are visible before confirmation.
- [x] Custom remote profile metadata includes source CSS URL and source host.
- [x] Existing family merge and library-only/active-delivery defaults match self-hosted behavior.
- [x] Tests cover saved remote profiles, generated CSS output, font-display behavior, unicode-range output, warnings, and no original stylesheet enqueue behavior.

**Orchestrator progress**

- 2026-04-27: Slice 7 was preflighted by read-only explore agent `66506E07-4532-497C-84F3-5306988978EA`, then implemented by sub-agent `F41FDC38-9948-419B-BEF0-E82786C5B7D7`. Acceptance criteria reviewed and ticked by orchestrator from implementation evidence plus targeted verification.
- Evidence by acceptance criterion:
  - Final import accepts `remote`/`cdn`, performs HEAD plus capped range/signature revalidation, and saves `custom:cdn` delivery profiles with absolute remote font URLs in `files` and empty local `paths`.
  - Custom remote profile metadata stores `source_css_url`, `source_host`, `delivery_mode: remote`, and per-face remote URL/host, content type, size, last verification timestamp, and validation method.
  - Runtime planning includes `custom:cdn` profiles in generated Tasty Fonts font-face catalogs but not external stylesheet descriptors, so the original third-party CSS stylesheet is not enqueued.
  - Generated CSS emits controlled `@font-face` rules for remote URLs and continues to honor existing font-display and unicode-range settings.
  - Remote warnings remain visible before confirmation through the Slice 4 dry-run review warnings and are carried into remote revalidation metadata where applicable.
  - Existing family merge, library-only defaults, explicit publish/activate behavior, fallback persistence, and active-delivery defaults match the self-hosted final-import behavior.
  - Tests cover remote profile persistence, lightweight revalidation, material revalidation differences, REST remote final import, generated CSS remote URL/font-display/unicode-range output, preconnect origin behavior, and no original stylesheet enqueue behavior.
- Verification reported by sub-agent: PHP lint on modified Slice 7 files, `php tests/run.php`, and `composer phpstan` passed; Oracle review flagged production header-object handling, which was fixed with coverage.
- Verification rerun by orchestrator: `php tests/run.php`, `composer phpstan`, `node --test tests/js/*.test.cjs`, and PHP lint for `includes/CustomCss/CustomCssFinalImportService.php`, `includes/Fonts/RuntimeAssetPlanner.php`, `includes/Fonts/CatalogService.php`, `tests/cases/imports-library.php`, `tests/cases/css-storage-catalog.php`, `tests/cases/settings-assets-runtime.php`, and `tests/cases/admin-controller-rest.php` passed.
- Live note updated 2026-04-27: browser remote-serving final import was run against `https://unpkg.com/@fontsource/roboto@5.0.0/400.css` after creating a rollback snapshot. The Roboto family showed both `Self-hosted custom CSS` and `Remote custom CSS` delivery profiles, remote mode/source history was visible, generated CSS emitted Tasty-controlled `@font-face` rules with reviewed remote font URLs when the remote profile was active, and the original third-party stylesheet URL was not included. The local site was restored afterward via `snapshot-20260426-221512-50532712`.

**Dependencies**

- Slice 5.

---

## Slice 8 — Duplicate detection, custom replacement, and safe cleanup

- [x] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [x] AFK
- [ ] HITL

**User stories covered**

- 29, 30, 31, 32, 33, 34, 53, 54, 55, 72

**What to build**

Add duplicate detection and optional replacement for matching custom CSS faces. Default behavior skips duplicates. Advanced replacement applies only to custom CSS entries and cleans up old custom files only when safe.

**Acceptance criteria**

- [x] Dry-run or final review identifies matching existing custom faces.
- [x] Duplicate matching includes family, weight, style, format, delivery type, unicode range, and variable axes where applicable.
- [x] Default duplicate behavior is skip.
- [x] UI offers an advanced replace option for matching custom CSS faces/profiles.
- [x] Replacement does not target Google, Bunny, Adobe, or local upload profiles.
- [x] Replacement downloads/saves new files before updating metadata.
- [x] Old files are deleted only after successful replacement.
- [x] Old files are deleted only from the custom provider storage root.
- [x] Old files are not deleted if any remaining profile references the same path.
- [x] Failed replacements leave old working profiles/files intact.
- [x] Tests cover duplicate detection, skip default, custom-only replacement, protected non-custom profiles, safe file deletion, shared-path protection, and failure rollback behavior.

**Orchestrator progress**

- 2026-04-27: Implemented by sub-agent `9002AA79-8824-4F4A-91E8-80F7B38ED8EC`. Acceptance criteria reviewed and ticked by orchestrator from implementation evidence plus combined verification after Slice 9 completed.
- Evidence by acceptance criterion:
  - Dry-run review annotates existing duplicates with `duplicate_matches`, `duplicate_summary`, and duplicate counts; covered by `custom_css_dry_run_identifies_existing_duplicate_faces_for_review`.
  - Duplicate identity includes family, weight, style, format, delivery type, unicode range, and variable axes; covered by `custom_css_final_import_duplicate_matching_includes_variable_axes` and duplicate review assertions.
  - Final import skips duplicates by default; covered by `custom_css_final_import_skips_duplicate_custom_faces_by_default`.
  - Review UI exposes advanced duplicate handling with `skip` default and `replace_custom`; covered by JS contract `admin contracts surface duplicate matches and advanced custom replacement controls`.
  - Replacement targets only matching custom CSS profiles and skips protected Google/Bunny/Adobe/local upload profiles; covered by `custom_css_final_import_replace_custom_skips_protected_non_custom_profiles`.
  - Replacement writes/saves new profiles before removing old metadata/files; old files are deleted only after successful replacement, only from custom storage, and only when unreferenced; covered by replacement, shared-path, and failure rollback tests.
  - Failed replacements leave old profiles/files intact; covered by `custom_css_final_import_failed_replacements_leave_old_profiles_and_files_intact`.
  - Remote duplicates are covered by `custom_css_remote_final_import_replaces_matching_custom_remote_profiles`.
- Verification reported by sub-agent: PHP lint for Slice 8 files, focused duplicate/replacement PHP harness tests, `composer phpstan`, `node --test tests/js/admin-contracts.test.cjs`, and Oracle review passed.
- Verification rerun by orchestrator after Slice 8 and 9 completed: `php tests/run.php`, `node --test tests/js/*.test.cjs`, `composer phpstan`, `npm run lint:css`, and PHP lint for 19 modified PHP files passed.

**Dependencies**

- Slice 6 for self-hosted cleanup behavior.
- Slice 7 for remote duplicate behavior.

---

## Slice 9 — Library card source metadata and verify-now readout

- [x] Yes — Slice completed for source metadata/readout scope; Verify now deferred as noted below
- [ ] No — Slice not completed

**AFK or HITL classification**

- [x] AFK
- [ ] HITL

**User stories covered**

- 37, 38, 39, 40, 44, 48, 49, 50, 51

**What to build**

Expose custom CSS source metadata in expanded family/library card details and, if reusable validation is ready, add a manual verify action or readout for custom profiles. Known providers should not display this custom source URL treatment.

**Acceptance criteria**

- [x] Custom CSS profiles display the original source CSS URL in expanded library details.
- [x] The source URL display is scoped to custom CSS profiles only.
- [x] Known provider profiles do not show custom CSS source metadata.
- [x] The source URL is presented as read-only history.
- [x] The UI clarifies whether the custom profile is self-hosted or remote-serving.
- [x] Last verified timestamp is shown when available.
- [ ] If included in Phase 1, a Verify now action checks the existing custom profile source and selected face URLs without mutating the profile. Deferred; see implementation note.
- [ ] Verify now reports reachable, missing, changed, and warning states clearly. Deferred with the Verify now action; see implementation note.
- [x] Tests cover rendering custom source metadata, excluding known providers, and verify-now behavior if implemented.

**Implementation note**

- 2026-04-27: Slice 9 implementation added read-only custom CSS source metadata to expanded delivery profile details for custom profiles only, including the original source CSS URL, self-hosted vs remote-serving delivery readout, and last verified timestamp when saved on remote face metadata. Verify now was intentionally deferred: current validation/revalidation logic is embedded in dry-run/final-import internals, and extracting a no-mutation verifier would be more invasive than Slice 9 and could overlap the concurrent Slice 8 replacement/cleanup work. Follow-up should extract a shared read-only verifier before adding the button/REST action.

**Orchestrator progress**

- 2026-04-27: Implemented by sub-agent `C1BB3D9D-DD82-4066-B276-A5399DBD1A15`. Acceptance criteria reviewed by orchestrator; completed source metadata/readout criteria were ticked after combined verification. Verify Now criteria remain intentionally unticked/deferred rather than claimed.
- Evidence by acceptance criterion:
  - Expanded custom delivery profile details render the original source CSS URL, read-only history copy, delivery mode readout, self-hosted/remote-serving explanatory copy, and last verified timestamp when saved in face metadata.
  - Known provider profiles do not render custom CSS source URL metadata.
  - Coverage is in `family_card_renderer_shows_custom_css_source_history_for_custom_profiles_only`.
- Verification reported by sub-agent: PHP lint for renderer files/tests, targeted admin renderer test, targeted PHPStan for renderer files, `npm run lint:css`, `node --test tests/js/*.test.cjs`, `npm run lint:design`, and Oracle review passed. Broad PHP tests/PHPStan were blocked at that moment by concurrent Slice 8 edits.
- Verification rerun by orchestrator after Slice 8 completed: `php tests/run.php`, `node --test tests/js/*.test.cjs`, `composer phpstan`, `npm run lint:css`, and PHP lint for 19 modified PHP files passed.

**Dependencies**

- Slice 6 or Slice 7.
- Verify now depends on reusable validation from Slice 4.

---

## Slice 10 — Phase 1 polish, docs, and full regression pass

- [x] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [ ] AFK
- [x] HITL

**User stories covered**

- All Phase 1 stories: 1–72 as applicable to custom CSS stylesheet imports.

**What to build**

Perform final UX copy polish, documentation updates, screenshots or wiki updates where appropriate, and broad verification. This slice is HITL because the final admin flow and warning copy benefit from human review before release.

**Acceptance criteria**

- [x] UI copy for From URL, dry-run errors, warnings, remote-serving risks, duplicate handling, and import summaries is reviewed. `/impeccable` product-register design audit completed with no blockers; human release review may still revisit minor copy suggestions.
- [x] Contributor-facing docs are updated if commands, workflows, or behavior changed.
- [x] User-facing docs or wiki notes explain custom CSS imports, limitations, and troubleshooting.
- [x] Full Phase 1 happy path is manually demoable: dry run, review, self-host import, remote import, source URL display. Live browser demo was run against the local WordPress site with a rollback snapshot and then restored.
- [x] Error states are manually reviewed for clarity. `/impeccable` product-register design audit reviewed dry-run error states with no blockers; automated PHP/JS error-state coverage also passed.
- [x] Targeted PHP tests pass.
- [x] Targeted JS contract tests pass.
- [x] PHP static analysis passes if the implementation changed analyzed code.
- [x] CSS lint is run if admin CSS changed.
- [x] PHP lint runs across modified PHP files or the standard repo lint command is run.
- [x] Duplication check is run if structural refactoring or repeated code movement occurred.

**Slice 10 evidence**

- 2026-04-27: Docs/regression pass completed for Slice 10 scope; later follow-up UI copy and final-import UI/error-detail fixes were implemented and verified.
- Documentation updates:
  - Added `wiki/Provider-Custom-CSS.md` covering the Phase 1 From URL workflow, supported/deferred scope, self-hosted vs remote-serving behavior, safety limits, troubleshooting, duplicate behavior, source URL history, and the deferred Verify Now/source replacement/direct-file/multi-URL items.
  - Linked the new guide from `wiki/_Sidebar.md`, `wiki/Home.md`, `wiki/Getting-Started.md`, `wiki/Font-Library.md`, `wiki/Troubleshooting-Imports-and-Deliveries.md`, `wiki/GDPR.md`, and `wiki/FAQ.md`.
  - Updated `README.md` and `readme.txt` to include custom CSS URL imports in supported sources, self-hosting/GDPR claims, install/getting-started language, and storage notes.
  - Updated contributor/developer-facing `wiki/Architecture.md` and `wiki/Testing.md` with the `CustomCss/` service area, dry-run snapshot storage, runtime behavior, and relevant test locations.
- Verification run in this pass:
  - `composer install` passed (`COMPOSER_INSTALL_STATUS:0`).
  - `npm ci` passed (`NPM_CI_STATUS:0`).
  - `composer phpstan` passed (`PHPSTAN_STATUS:0`, `[OK] No errors`).
  - `php tests/run.php` passed (`PHP_TEST_STATUS:0`).
  - `node --test tests/js/*.test.cjs` passed 68/68.
  - `npm run lint:css` passed (`CSS_LINT_STATUS:0`, 3 sources checked, 0 problems).
  - `npm run lint:design` passed (`DESIGN_LINT_STATUS:0`).
  - Standard PHP lint sweep passed (`PHP_LINT_STATUS:0`) using `find . -name '*.php' -not -path './output/*' -not -path './tmp/*' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l`.
  - `bash tests/bin/release-scripts.test.sh` passed 38/38.
  - `bin/run-jscpd` passed with `0 clones · 0.0% duplication` and wrote `report/jscpd-report.json`.
  - `git diff --check` passed (`DIFF_CHECK_STATUS:0`) after cleanup of markdown/test trailing whitespace.
  - Oracle docs review was run for the selected markdown changes; findings about the PRD link, Slice 10 status, and release-line wording were addressed.
- UI/design audit:
  - `/impeccable` product-register Slice 10 audit completed in `docs/reviews/slice-10-ui-polish-review.md` with no blockers.
  - Follow-up custom CSS UI audit completed in `docs/reviews/custom-css-ui-review.md` with no blockers. The actionable copy tweaks were applied: the From URL intro now says “Inspect a public HTTPS CSS stylesheet…” and the gated note points to the Developer tab of Advanced Tools without breadcrumb punctuation.
- Live browser demo:
  - Created rollback snapshot `snapshot-20260426-221512-50532712` before mutation testing.
  - Confirmed From URL is gated while Custom CSS URL Imports is OFF.
  - Enabled the gate in Advanced Tools → Developer and confirmed the dry-run UI appeared.
  - Ran dry-run for `https://unpkg.com/@fontsource/roboto@5.0.0/400.css`; the review showed seven selectable WOFF2 faces, remote-serving warnings, duplicate handling after the first import, and per-family fallback controls.
  - Ran self-hosted final import; fixed the discovered recursive storage-directory write blocker and verified Roboto imported with local `custom/roboto/...woff2` paths and source URL history.
  - Ran remote-serving final import; verified the same family has both self-hosted and remote custom CSS delivery profiles, with remote mode/source history visible.
  - Confirmed generated CSS contains Tasty-generated Roboto `@font-face` rules, uses reviewed remote font URLs when the remote profile is active, and does not include the original CSS stylesheet URL.
  - Restored rollback snapshot and confirmed the local site returned to four families with From URL gated OFF.
- HITL notes:
  - Verify Now remains deferred per Slice 9/plan note; no Phase 2/3 implementation was added.

**Dependencies**

- Slices 1–9.

---

# Phase 2 — Direct URL and Source Maintenance

## Slice 11 — Direct font-file URL import

- [ ] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [ ] AFK
- [x] HITL

**User stories covered**

- 20, 21, 56, 57, 58, 60, 64, 69, 70

**What to build**

Add direct font-file URL import under the From URL flow. Users can enter one WOFF2, WOFF, TTF, or OTF URL, provide missing metadata manually, dry-run validation occurs, and final import uses the same snapshot and safe final import machinery.

**Acceptance criteria**

- [ ] UI distinguishes CSS stylesheet URL import from direct font-file URL import.
- [ ] Direct file URL dry run validates public HTTPS and file signature.
- [ ] User can provide family, weight, style, fallback, and variable metadata where needed.
- [ ] Final import reuses snapshot protection.
- [ ] Direct file imports can self-host at minimum.
- [ ] Remote-serving direct file behavior is explicitly supported or intentionally deferred.
- [ ] Tests cover metadata validation, file signatures, successful import, invalid files, and tampered requests.

**Dependencies**

- Phase 1 snapshot, URL safety, and final import infrastructure.
- HITL decision may be needed for variable font metadata UI.

---

## Slice 12 — Compatible source URL replacement

- [ ] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [x] AFK
- [ ] HITL

**User stories covered**

- 41, 42, 43, 44, 63, 64

**What to build**

Allow users to replace the stored source CSS URL on existing custom CSS profiles only when a dry-run compatibility check proves the new stylesheet still resolves the same imported faces.

**Acceptance criteria**

- [ ] Custom profile details expose a Replace/Verify source URL flow.
- [ ] New source URL goes through the same safety checks as Phase 1.
- [ ] Compatibility requires matching existing family/weight/style/format/subset/axes identities.
- [ ] Compatible replacement updates source URL metadata and last verified timestamp.
- [ ] Incompatible replacement is rejected with diagnostics and a recommendation to re-import.
- [ ] No font files or delivery metadata are changed during source URL metadata replacement unless explicitly part of a later re-sync flow.
- [ ] Tests cover compatible replacement, incompatible replacement, invalid URL rejection, and metadata updates.

**Dependencies**

- Slice 9.

---

## Slice 13 — Re-import and guided cleanup from updated source

- [ ] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [ ] AFK
- [x] HITL

**User stories covered**

- 31, 32, 33, 34, 35, 36, 41, 42, 43, 55

**What to build**

Add a guided re-import flow for custom CSS profiles when the source stylesheet changed. The flow shows existing matching faces, new faces, removed faces, and replacement/cleanup choices before final import.

**Acceptance criteria**

- [ ] Re-import starts from an existing custom CSS profile or source URL.
- [ ] Dry run compares new plan against existing custom profile faces.
- [ ] UI shows unchanged, new, missing, and changed faces.
- [ ] User can skip, add, or replace matching custom faces.
- [ ] Safe cleanup rules from Phase 1 are reused.
- [ ] Incompatible changes are handled through re-import, not source metadata replacement.
- [ ] Tests cover diffing, replacement, removal candidates, partial success, and cleanup safety.

**Dependencies**

- Slice 12.
- HITL review needed for re-import diff UX.

---

# Phase 3 — Advanced Provider Intelligence and Bulk Power

## Slice 14 — Provider-aware URL routing suggestions

- [ ] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [ ] AFK
- [x] HITL

**User stories covered**

- 68

**What to build**

Detect when a pasted URL appears to belong to a known provider or provider-like flow and guide users toward the dedicated Google, Bunny, or Adobe workflows where appropriate, while preserving the custom fallback path.

**Acceptance criteria**

- [ ] From URL dry run can identify obvious known-provider URLs or patterns.
- [ ] UI suggests the dedicated provider flow without blocking advanced custom import when appropriate.
- [ ] Known providers remain visually and behaviorally distinct from custom CSS imports.
- [ ] Tests cover detection suggestions and custom fallback behavior.

**Dependencies**

- Phase 1 From URL UI.
- HITL review needed for messaging and routing behavior.

---

## Slice 15 — Multiple CSS URLs in one import batch

- [ ] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [ ] AFK
- [x] HITL

**User stories covered**

- 3, 4, 35, 36, 50, 58, 65

**What to build**

Allow advanced users to provide multiple CSS URLs in one dry-run batch while preserving workload limits, source attribution, duplicate detection, and review clarity.

**Acceptance criteria**

- [ ] UI accepts multiple CSS URLs in an advanced mode.
- [ ] Dry run validates each source independently.
- [ ] Review UI groups families/faces by source URL when useful.
- [ ] Workload limits apply per URL and per batch.
- [ ] Source URL metadata remains accurate per imported delivery profile.
- [ ] Tests cover multiple-source success, partial source failure, duplicate faces across sources, and limit enforcement.

**Dependencies**

- Phase 1 dry-run planner and review UI.
- HITL review needed for batch UX.

---

## Slice 16 — Friendly subset labels and richer diagnostics

- [ ] Yes — Slice completed
- [ ] No — Slice not completed

**AFK or HITL classification**

- [x] AFK
- [ ] HITL

**User stories covered**

- 48, 54, 64, 65

**What to build**

Improve advanced review and troubleshooting by mapping common unicode ranges to friendly subset labels and expanding diagnostics for failed custom URL imports.

**Acceptance criteria**

- [ ] Common unicode-range patterns receive friendly labels where safe.
- [ ] Unknown ranges still show raw values.
- [ ] Diagnostics include actionable next steps for unsupported format, private URL, too many faces, unreachable URL, invalid signature, and CORS warning cases.
- [ ] Tests cover known subset labels, unknown raw fallback, and diagnostic messages.

**Dependencies**

- Phase 1 unicode-range preservation and validation errors.

---

# User Stories Covered

- [ ] Yes — Future agent confirmed story coverage
- [ ] No — Future agent has not confirmed story coverage

Phase 1 slices cover the core PRD user stories for custom CSS stylesheet imports: 1–72, excluding the portions explicitly deferred to Phase 2/3 such as direct font-file URL imports, compatible source URL replacement, multiple CSS URLs, provider detection, and friendly subset labels.

Phase 2 covers deferred maintenance and direct URL stories: source URL replacement, re-import cleanup, and direct font-file URL import.

Phase 3 covers advanced power and intelligence stories: known-provider routing suggestions, multi-URL batches, and friendlier subset/diagnostic polish.

# Cross-Slice Dependencies Summary

- [ ] Yes — Dependencies reviewed
- [ ] No — Dependencies not reviewed

1. Slice 1 has no blockers.
2. Slice 2 depends on Slice 1.
3. Slice 3 depends on Slice 2.
4. Slice 4 depends on Slice 3.
5. Slice 5 depends on Slice 4.
6. Slice 6 depends on Slice 5.
7. Slice 7 depends on Slice 5.
8. Slice 8 depends on Slice 6 and Slice 7.
9. Slice 9 depends on Slice 6 or Slice 7 and reuses Slice 4 validation.
10. Slice 10 depends on Slices 1–9.
11. Phase 2 depends on Phase 1 infrastructure.
12. Phase 3 depends on Phase 1 and selected Phase 2 outcomes depending on scope.

# AFK / HITL Summary

- [ ] Yes — Classification reviewed
- [ ] No — Classification not reviewed

## AFK slices

- Slice 1 — From URL dry-run tracer path
- Slice 2 — URL safety and workload limits in dry run
- Slice 3 — CSS parsing, relative URL resolution, and face identity
- Slice 4 — Font URL validation and review warnings
- Slice 5 — Snapshot token and tamper-resistant final import contract
- Slice 6 — Self-hosted final import for selected custom faces
- Slice 7 — Remote-serving final import for selected custom faces
- Slice 8 — Duplicate detection, custom replacement, and safe cleanup
- Slice 9 — Library card source metadata and verify-now readout
- Slice 12 — Compatible source URL replacement
- Slice 16 — Friendly subset labels and richer diagnostics

## HITL slices

- Slice 10 — Phase 1 polish, docs, and full regression pass
- Slice 11 — Direct font-file URL import
- Slice 13 — Re-import and guided cleanup from updated source
- Slice 14 — Provider-aware URL routing suggestions
- Slice 15 — Multiple CSS URLs in one import batch
