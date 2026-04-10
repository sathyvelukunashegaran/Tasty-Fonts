# Font Library

Manage families, delivery profiles, publish state, fallback stacks, and per-family runtime overrides.

## Use This Page When

- you want to browse everything the plugin knows about
- you need to switch a family's active delivery profile
- you need to change publish state, fallback behavior, or per-family `font-display`

## Overview

The Font Library is your central inventory. Every font family added to the plugin — regardless of whether it came from a local upload, Google, Bunny, or Adobe — lives here. You do not assign roles from this page (that happens in Deploy Fonts), but you do control how each family is delivered and whether it participates in runtime output.

---

## Steps

### 1. Filter The Library

Use the library filters to narrow by:

- source (Local, Google, Bunny, Adobe)
- runtime state (In Use, Published, In Library Only)
- family type (Static, Variable) — available when Variable Font Support is enabled
- category (serif, sans-serif, monospace, display, handwriting)
- search text

This is the fastest way to isolate imported families, local uploads, CDN profiles, or families that are currently in use.

### 2. Understand Publish State

Families can appear in three main runtime states:

| State | What it means |
|---|---|
| **In Use** | The family is assigned to an active live role. Set automatically — you do not set this manually. |
| **Published** | The family is part of runtime output but not assigned to a specific role. Use this for families referenced in custom CSS or theme templates. |
| **In Library Only** | Stored for future use. No CSS is generated or served for this family right now. |

**Decision guide:**

- `In Use` is set automatically when the family is assigned to an applied sitewide role. You do not set this manually.
- Set a family to `Published` when it should be part of the available runtime output but is not yet assigned to a specific role. For example, a family used in custom CSS or a theme template can stay Published without needing a Heading/Body role assignment.
- Set a family to `In Library Only` when you want to keep it available for future use without serving any CSS for it right now. This is useful for staging imports before a design decision is made.

> **Beginner tip:** if you imported a family and your site is not showing it, check that it is set to `Published` or `In Use` (not `In Library Only`) and that it has an active delivery profile.

### 3. Switch Active Delivery Profiles

A family can keep multiple delivery profiles side by side. The active delivery profile controls what the plugin serves.

Common examples:

- local self-hosted delivery for production
- Google CDN or Bunny CDN delivery for testing or fallback comparison
- multiple historical profiles kept for later reuse

**Working with multiple delivery profiles:**

Keeping multiple profiles on one family lets you switch delivery modes without losing your previous configuration. For example, you can import a family as both self-hosted and CDN, keep both profiles saved, and toggle between them to compare performance or troubleshoot delivery issues — without re-importing anything. Only the active profile affects runtime output.

### 4. Save Fallback Stacks

Each family can store its own fallback stack. This affects how generated stacks resolve when that family is used in runtime output and snippets.

A fallback stack is a comma-separated list of font families the browser tries if your primary font is unavailable. Example:

```
Georgia, 'Times New Roman', serif
```

The plugin will append this fallback sequence to the family name in all generated `font-family` declarations. If you leave this blank, a generic fallback category (e.g., `sans-serif`) is used.

### 5. Save Per-Family Font Display

Each family can also store a per-family `font-display` override. Use this when a specific family should behave differently from the global default set in `Settings`.

For example, if your global default is `optional` for self-hosted output but you have a branding font that absolutely must avoid any ambiguity, set that family to `swap` — it will always request the more assertive `font-display` behavior instead of inheriting the global default.

Per-family settings always take precedence over the global default.

### 6. Variable Font Metadata

When Variable Font Support is enabled in `Settings → Output`, families that have variable delivery profiles display a **Variable** badge in the library. Selecting such a family shows its stored axis metadata — the axes the font exposes (e.g., `wght`, `wdth`) and the default values used in generated output.

Axis defaults and per-role axis overrides are managed from the Deploy Fonts page, not from here. The library shows what is stored; Deploy Fonts is where you tune per-role axis behavior.

### 7. Delete Carefully

The library lets you delete:

- a whole family
- a delivery profile
- individual variants

The plugin blocks destructive actions when doing so would break a live applied role or an active profile still required by runtime output.

---

## Notes

- Google and Bunny self-hosted imports become local files under provider-specific upload directories.
- Adobe stays hosted remotely, but Adobe families still participate in selectors, previews, and live role assignments.
- The library is also where you confirm whether a family should stay `Published` or remain `In Library Only`.
- All newly imported families start as `In Library Only` by default. Promote them to `Published` or assign them to a role when you are ready to serve them.
- When a family stops being used by live roles and has no remaining role assignments, it automatically returns to `In Library Only` state rather than disappearing from the library.

## Related Docs

- [Getting Started](getting-started.md)
- [Deploy Fonts](deploy-fonts.md)
- [Local Fonts](providers/local-fonts.md)
- [Google Fonts](providers/google-fonts.md)
- [Bunny Fonts](providers/bunny-fonts.md)
- [Adobe Fonts](providers/adobe-fonts.md)
- [Imports And Deliveries](troubleshooting/imports-and-deliveries.md)
- [FAQ](faq.md)
