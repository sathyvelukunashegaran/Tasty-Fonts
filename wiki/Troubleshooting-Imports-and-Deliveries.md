# Imports And Deliveries

Troubleshoot provider imports, runtime visibility, and delivery profile confusion.

## Use This Page When

- a family imports successfully but does not appear live
- a family is visible in the library but not on the frontend
- you are unsure which delivery profile or publish state is in effect

## Diagnostic Checklist

Work through this checklist in order. Most issues are resolved by step 3 or 4.

### 1. Confirm The Family Exists In The Library

Open `Font Library` and search for the family.

Check whether the family is present and whether it is marked:

- `In Use` — assigned to a live role; CSS is being served
- `Published` — runtime-visible; CSS is being served but not via a role
- `In Library Only` — **not served**; no CSS is generated for this family

`In Library Only` keeps the family stored but out of runtime delivery. If your family is stuck here, change its state to `Published`.

### 2. Confirm The Active Delivery Profile

If a family has multiple delivery profiles, the active delivery profile controls what runtime uses. Confirm that the profile you expect is actually selected.

Look for the **active** indicator on the correct profile. If the wrong profile is active, switch it.

### 3. Confirm The Role Assignment

Even a valid published family will not affect live output unless:

- it is assigned to the relevant draft role
- **and** that draft has been applied sitewide

Go to `Deploy Fonts`, confirm the family is assigned to the correct role (Heading or Body), and check that `Apply Sitewide` was used after the assignment.

> **Common mistake:** saving a draft and assuming the site updated. Draft saves do not change live output. Use `Apply Sitewide` to publish the change.

### 4. Confirm Provider-Specific Preconditions

| Provider | What to check |
|---|---|
| Google | Valid API key is saved in the plugin; the key has the Web Fonts API enabled |
| Bunny | Download URLs are reachable from the server (no firewall blocking Bunny.net) |
| Adobe | Web project ID is correct and the project is published (not in draft or paused) |
| Local | File format is supported (WOFF2, WOFF, TTF, OTF) and file is writable after upload |

### 5. Check The Activity Log

Go to `Advanced Tools` and review recent activity for any import errors, delivery failures, or sync issues. The activity log captures per-event details that are not visible in the main UI.

### 6. Inspect Generated CSS

Go to `Advanced Tools → Generated CSS` and confirm the runtime stylesheet contains:

- the correct `@font-face` rules for self-hosted families
- the correct `--font-heading` and `--font-body` variable values

If the CSS looks stale, go to `Settings → Developer` and use `Clear Cache` to force regeneration.

### 7. Check Runtime `font-display` Expectations

For self-hosted deliveries, the generated `@font-face` rules use your saved global or per-family `font-display` value directly.

For live Google and Bunny CDN deliveries, the runtime planner promotes `optional` to `swap`. If a CDN family still does not render as expected, inspect whether the family has a per-family `font-display` override such as `fallback` or `block`.

---

## Notes

- Families can stay in the library for later use without becoming live immediately.
- Remote CDN deliveries and self-hosted deliveries both work within the same delivery profile model, but they produce different runtime asset behavior.
- Google and Bunny CDN deliveries intentionally avoid live `display=optional` requests because that can leave first-visit renders stuck on fallback fonts.
- If the library state looks correct but runtime still looks stale, continue with the generated CSS checks.

## Related Docs

- [Font Library](Font-Library)
- [Generated CSS](Troubleshooting-Generated-CSS)
- [Google Fonts](Provider-Google-Fonts)
- [Bunny Fonts](Provider-Bunny-Fonts)
- [Adobe Fonts](Provider-Adobe-Fonts)
- [FAQ](FAQ)
