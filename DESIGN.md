# Tasty Foundry Design System

Tasty Foundry is the admin design system for Tasty Custom Fonts. It is a WordPress-native workspace for managing font libraries, delivery profiles, role previews, diagnostics, and settings. The system borrows Stripe's product discipline: crisp typography, aligned work surfaces, precise borders, restrained depth, and compact controls. The visual accent is Tasty Blue from the logo, supported by quiet cyan edge lighting and neutral slate surfaces.

Related docs: [`AGENTS.md`](AGENTS.md) for agent workflow, [`ARCHITECTURE.md`](ARCHITECTURE.md) for repo-local architecture, and [`.agents/README.md`](.agents/README.md) for shared agent context.

## Principles

- **Font work first:** imported families, role pairings, delivery profiles, generated CSS, and diagnostics must stay easier to scan than the surrounding chrome.
- **WordPress-native:** use WordPress button semantics, accessible focus states, and the existing `.tasty-fonts-admin` namespace while layering a more polished SaaS surface treatment on top.
- **Token-first:** `assets/css/tokens.css` is the source of truth for colors, spacing, typography, radius, shadows, motion, and component constants.
- **Stripe-like, not copied:** use Stripe-inspired structure, dense grids, white surfaces, disciplined spacing, and restrained depth without copying proprietary assets, exact layouts, or bundled fonts.
- **Confident contrast:** page identity, primary actions, cards, and code views should feel more premium than a default WordPress settings screen.

## Palette — Three-Color Restraint

The plugin uses **only three colors** plus neutrals. No green. No red. No external semantic palette. The whole product reads as one calm, deliberate system.

### Primary palette

| Role | Token | Value | Use |
| --- | --- | --- | --- |
| **Primary** — Tasty Blue | `--tasty-brand-blue` | `#0f6fb7` | Primary actions, selected states, focus accents, info, brand mark, structural code tokens |
| **Secondary** — Warm Amber | `--tasty-warm-amber` | `#e89a3c` | Caution / warning, secondary accents, value tokens in code, the single colored survivor in semantic state work |
| **Tertiary** — Soft Cream | `--tasty-paper` | `#faf7f2` | Hero surfaces (masthead, specimen board), warm text on dark code panels |

### Hover / contrast partners

| Role | Token | Value | Use |
| --- | --- | --- | --- |
| Tasty Blue Hover | `--tasty-brand-blue-hover` | `#0b5f9e` | Hover state for primary, light-theme syntax keywords |
| Tasty Blue Active | `--tasty-brand-blue-active` | `#084a7d` | Pressed primary, light-theme variable tokens |
| Tasty Cyan | `--tasty-brand-cyan` | `#32c5f4` | Faint edge lighting, border highlights, dark-theme syntax keywords (a tinted variant of blue, not a fourth color) |
| Warm Amber Deep | `--tasty-warm-amber-deep` | `#c97c1f` | Hover/contrast for Warm Amber, light-theme value syntax tokens |
| Paper Deep | `--tasty-paper-deep` | `#f5f0e6` | Inner board edges on paper surfaces |

### Neutrals (allowed, not "colors")

| Role | Token | Value | Use |
| --- | --- | --- | --- |
| Ink Navy | `--tasty-ink` | `#0a2540` | Headings, strong text, high-contrast UI |
| Ink Soft | `--tasty-ink-soft` | `#1c2e44` | Body ink on hero surfaces |
| Workbench Slate | `--tasty-slate` | `#425466` | Secondary text, quiet labels, code punctuation |
| Surface | `--tasty-gray-0` | `#ffffff` | Cards, controls, primary panels |
| Surface Soft | `--tasty-gray-50` | `#f6f9fc` | Shell background, preview workspace |
| Surface Muted | `--tasty-gray-100` | `#eef3f8` | Inset panels, toolbar wells |
| Gray scale | `--tasty-gray-150 / -300 / -700` | — | Borders, dividers, muted text |

### Forbidden

- **Green** in any form (the previous `--tasty-success-raw: #138a4b` is removed; success is now a *role*, not a color).
- **Red** in any form (the previous `--tasty-danger-raw: #c8323a` is removed; danger lives in chrome — bolder weight + inset shadow + icons — not in color).
- **Warning amber `#9a6700`** (collapsed into Warm Amber `#e89a3c` — warning is what amber is for).
- **All syntax-rainbow colors** in the dark code window.
- **Integration brand colors** as theme tokens (the brand logo image inside the tile is the brand identifier; the framing chrome is neutral).
- **Any hex / rgb literal** in `admin.css` that doesn't resolve through a token.

Raw colors belong in `tokens.css` first. `admin.css` consumes semantic tokens only.

### Semantic role names — preserved API, neutralized identity

The legacy semantic tokens `--tasty-success`, `--tasty-warning`, `--tasty-danger` and their derivatives (`-soft`, `-rgb`, `--tasty-surface-*`, `--tasty-border-*`, `--tasty-status-dot-shadow-*`) are **kept as role names** so the 124 existing consumers in `admin.css` keep working. They no longer carry their original color identity:

- `--tasty-success` → `--tasty-ink` (state shown via icon + filled chrome)
- `--tasty-warning` → `--tasty-warm-amber` (the only colored survivor — warning is what amber is for)
- `--tasty-danger` → `--tasty-ink` (state shown via icon + sunken chrome with `--tasty-shadow-pressed`)

This is intentional: components that say "I want danger styling" still get it, but the styling delivery shifts from color to chrome (weight, shadow, icon). New components should not introduce raw color for state — use the semantic tokens or the State-Without-Color recipes below.

### State Without Color (icon + surface tone)

State signal comes from **icon + chrome**, not color:

- **Live / Active / On / Done** — filled neutral pill + ✓ icon
- **Draft / Pending / Idle** — outlined neutral pill + ◯ icon (or no icon)
- **Caution / Warning / Advisory** — Warm Amber pill + ⚠ icon (the only place a colored pill survives)
- **Failed / Error / Attention** — filled neutral pill with `--tasty-shadow-pressed` (sunken chrome) + ✕ icon

Status dots: drop semantic colors. Dots become:

- Filled blue (info / active / running)
- Filled amber (caution)
- Hollow ring (idle / not started)
- Filled gray (done — paired with checkmark icon next to the row label)

### Destructive Actions Without Red

The Delete / Reset / Uninstall affordance is delivered by:

1. **Icon** prefix in the button label (trash / refresh / power).
2. **Bolder weight** on the button label (`--tasty-font-weight-bold`, 700).
3. **Inset shadow** on hover/focus/active (`--tasty-shadow-pressed`) so the button reads as pressing INTO the surface — a tactile opposition to regular buttons that lift OFF.
4. **The existing two-step confirm pattern** (`.is-awaiting-confirmation` swaps the button label to "Confirm" until the second click).

### Where Amber Appears

Warm Amber is intentionally restrained. It is the only secondary accent in the plugin and it never replaces blue for primary actions, focus rings, or selected states. The complete list of valid uses:

1. **Universal card hairline.** Every card-like surface in the plugin (`.tasty-fonts-surface`, `.tasty-fonts-card`, `.tasty-fonts-font-card`, `.tasty-fonts-search-card`, `.tasty-fonts-source-card`, `.tasty-fonts-google-access`, `.tasty-fonts-search-shell`, `.tasty-fonts-import-panel`, `.tasty-fonts-preview-support-card`, `.tasty-fonts-preview-card-frame`, `.tasty-fonts-role-grid`, `.tasty-fonts-role-actions`, `.tasty-fonts-health-board`, `.tasty-fonts-settings-board`) carries a 1px transparent → cyan → blue → amber → transparent hairline along its top edge. Together with the page header's bottom hairline this is the canonical "this is a card" boundary line. Compositional cells (`.tasty-fonts-studio-card`, `.tasty-fonts-role-box`, `.tasty-fonts-code-card`) and `.tasty-fonts-banner` intentionally do not participate. (Previously the hairline was reserved for the masthead, the role rail, and the preview specimen board.)
2. *(Reserved for future use)* — the `--tasty-shadow-amber-halo` and `--tasty-shadow-amber-halo-pulse` tokens are kept for genuine state-change celebrations (e.g. a future `is-just-applied` class fired for ~2s after Sitewide turns on). The previous permanent halo on the steady-state "Sitewide on" pill has been removed because permanent celebration is decoration; the steady "on" state now uses the standard success-pill chrome so it visually peers with the LIVE pill next to it.
3. *(Folded into entry 1.)* — the role rail's top hairline is no longer a separate moment; it now matches every other card surface.
4. Font Library `is-active` family card — `--tasty-border-amber-soft` border + `--tasty-gradient-surface-card` cream wash + `--tasty-shadow-card-selected` rise-3 elevation (the "applied to a role" cue). The previous 2px amber left rail has been removed because the universal hairline now sits across the top of every card; the active card differentiates from neutral siblings through warmer border + elevation contrast instead.
5. The discovery empty state — `--tasty-gradient-amber-blob` behind the friendly copy + spotlight surface.
6. Settings save shell when the form is dirty — amber-soft border + rise-2 elevation (the "your changes are waiting" cue).
7. Preview specimen board — cream wash (`--tasty-gradient-role-priority`) + larger hero radius + Aa glyph rail (the hero specimen moment). The board's top hairline now comes from entry 1.
8. **Copy-action affordance.** Every copy control in the plugin shares one warm-amber hover language — `--tasty-border-amber-soft` border + `--tasty-warm-amber-deep` ink + `--tasty-shadow-amber-halo` glow. The shared selector list covers `.tasty-fonts-preview-copy-css-button`, `.tasty-fonts-output-copy-button`, `.tasty-fonts-diagnostic-copy-button`, `.tasty-fonts-stack-copy`, `.tasty-fonts-role-stack-copy`, and `.tasty-fonts-pill--copy`. Copy is the second authorized use of `--tasty-shadow-amber-halo` (alongside transient state-change celebrations).
9. The Publish Roles button when pending live changes — `--tasty-shadow-spotlight` (cyan + amber dual-layer; the "your next action lives here" cue).
10. The page-header status pill halo and the Sitewide-on celebrate flash.
11. The page-header masthead lower-right glow (`--tasty-header-glow-accent`) — the warm counterweight to the cyan glow upper-left, so the masthead reads as "blue + a touch of amber" rather than blue-on-blue.
12. The page-header kicker (`TYPOGRAPHY MANAGEMENT FOR PROS`) — small Warm Amber dot (`--tasty-status-dot-size-small` + `--tasty-status-dot-shadow-warm-amber`) prepended to the brand line. The kicker text itself uses `--tasty-ink-soft` so the cream paper backdrop reads as intentional.
13. **Warning state across the plugin** — `--tasty-warning` is now an alias for Warm Amber. Toasts, advisory pills, advisory health-group rails, caution status dots, and any "this needs your attention but is not broken" affordance all use this single color. (This is the largest single expansion of amber; it's deliberate — the previous warning-amber `#9a6700` collapsed into the brand amber so caution and the brand voice share the same temperature.)
14. **Value tokens in code preview windows** (strings, numbers, function names, attribute values) — both light and dark themes use Warm Amber / Warm Amber Deep for the "value half" of the syntax split. The "structural half" (keywords, properties, selectors, at-rules) uses Tasty Blue. Two roles, two colors.

If a use case is not in this list, it is not a warm-amber moment. Add new uses to the list before adding amber to the CSS.

## Typography

Product UI uses the WordPress/admin system stack. No proprietary or remote font is bundled.

```css
--tasty-font-family-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
--tasty-font-family-display: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
--tasty-font-family-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
```

`--tasty-font-family-display` is reserved for **two surfaces only**: the page H1 (`.tasty-fonts-header-title`) and the hero specimen scenes inside the preview workspace. All other UI typography uses `--tasty-font-family-sans`. The display token intentionally uses the same system stack as the product UI while keeping display-specific refinements isolated.

Optical refinements (`--tasty-type-page-title-tracking: -0.02em`, `--tasty-type-display-tracking: -0.018em`) are applied only to surfaces using the display family. `--tasty-type-display-features: "ss01" 1, "cv11" 1` is set as `font-feature-settings` on those surfaces; it has zero effect on system fonts that do not ship the features.

| Role | Token | Size | Weight | Notes |
| --- | --- | --- | --- | --- |
| Page title | `--tasty-type-page-title-size` | `28px-36px` | `700` | Confident workspace identity without marketing-page scale |
| Section title | `--tasty-type-section-title-size` | `18px-22px` | `600` | Major admin panels |
| Panel title | `--tasty-type-panel-title-size` | `17px-19px` | `600` | Grouped settings and workflow panels |
| Card title | `--tasty-type-card-title-size` | `16px-18px` | `600` | Font cards, role cards, status cards |
| Body | `--tasty-type-body-size` | `13px` | `400` | Default admin reading text |
| Body large | `--tasty-type-body-large-size` | `14px` | `400` | Summaries and short descriptions |
| Caption | `--tasty-type-caption-size` | `12px` | `400` | Helper text, diagnostics metadata |
| Meta | `--tasty-type-meta-size` | `12px` | `600` | Uppercase labels only |
| Dense label | `--tasty-type-label-dense-size` | `12px` | `600-700` | Version pills, tight badges, tiny status labels |
| Micro label | `--tasty-type-label-micro-size` | `12px` | `700` | Exceptional diagnostic count chips only |
| Code output | `--tasty-type-code-output-size` | `12.5px` | `400-500` | Generated CSS and diagnostics viewers |

The minimum readable UI text size is `12px` (`--tasty-font-size-minimum`). Even dense and micro labels resolve to that floor; compactness should come from weight, line-height, spacing, and iconography rather than smaller text.

Use tabular numerals for metrics, counts, diagnostics, activity timestamps, and version/status pills. Use zero letter-spacing for product UI; create precision through weight, line-height, scale, and color contrast. Letter spacing is tokenized for the few valid exceptions: compact meta, status pills, step labels, and preview overlines. Font previews are the exception to the system font stack because their purpose is to show managed families, but even preview sizes, weights, line-heights, and fallback stacks must flow through Tasty tokens.

Typography declarations in `admin.css` must use tokens for `font-family`, `font-size`, `font-weight`, `line-height`, and `letter-spacing`. Component typography should map to semantic roles rather than one-off values:

- Page identity: `--tasty-type-page-title-*`.
- Section and panel headings: `--tasty-type-section-title-*`, `--tasty-type-panel-title-*`, `--tasty-type-title-*`.
- Cards and inventory objects: `--tasty-type-card-title-*`, `--tasty-type-detail-title-*`.
- Controls, buttons, tabs, and segmented options: `--tasty-type-control-*`.
- Pills, badges, status chips, and compact metadata: `--tasty-type-label-*` plus `--tasty-type-letter-spacing-*`.
- Code and diagnostics: `--tasty-font-family-mono`, `--tasty-type-code-output-size`, and `--tasty-line-height-code`.

## Radius, Depth, And Surfaces

| Role | Token | Value | Use |
| --- | --- | --- | --- |
| Micro | `--tasty-radius-s` | `4px` | Small inner details |
| Control | `--tasty-radius-control` | `6px` | Buttons, inputs, selects |
| Card | `--tasty-radius-card` | `8px` | Top-level sections, repeated inventory cards |
| Panel | `--tasty-radius-panel` | `8px` | Tab wells and grouped panels |
| Hero | `--tasty-radius-hero` | `12px` | Spotlight cards, roles rail, preview specimen board |
| Shell | `--tasty-radius-xl` | `14px` | The outer admin workspace |
| Pill | `--tasty-radius-pill` | `999px` | Badges and inline status tokens only |

Depth is Stripe-like but operational: white cards, faint borders, a small shell fuse, and layered navy shadows. Avoid pastel bloom around normal cards.

The shadow tokens form an intentional **elevation scale**. New shadows must compose from this scale rather than introducing ad-hoc shadow values.

- `--tasty-shadow-flat`: `none` — named so transitions between elevations stay token-driven.
- `--tasty-shadow-rise-1`: hairline lift for inert rows on hover and resting cards.
- `--tasty-shadow-rise-2`: standard interactive card hover.
- `--tasty-shadow-rise-3`: pressed/selected card emphasis.
- `--tasty-shadow-popover`: refined overlay falloff for popovers, tooltips, dropdowns.
- `--tasty-shadow-spotlight`: dual-layer **cyan + amber** glow. **Maximum once per page.** Used for the hero specimen and the publish-pending intent flash.
- `--tasty-shadow-inset-rail`: paper / code panel inset (the preview specimen, code editors).
- `--tasty-shadow-pressed`: button `:active` feedback.
- `--tasty-shadow-amber-halo` / `--tasty-shadow-amber-halo-pulse`: reserved for genuine state-change celebrations (a future `is-just-applied` transient class). The tokens are not currently consumed by any steady-state component.
- `--tasty-shadow-card`, `--tasty-shadow-card-hover`, `--tasty-shadow-card-selected`, `--tasty-shadow-card-emphasized`: legacy card aliases — keep working but new code should prefer the `rise-*` scale.
- `--tasty-shadow-overlay`: popovers, diagnostics overlays, tooltips.
- `--tasty-shadow-focus`: keyboard focus ring.
- `--tasty-shadow-shell`, `--tasty-shadow-masthead`, `--tasty-shadow-hairline`: admin workspace structure.

Use gradients intentionally: the shell field stays neutral (gray-50 paper stripe, no blue radials, no diagonal beam), with one warm cream/amber `::before` highlight as a hint of light. The header carries cyan + amber glows as a pair. Cards and controls stay mostly white with subtle borders. Inner groups should use dividers, spacing, and typography instead of more boxes.

The shell + page header pick up two specific gradient tokens for atmosphere:

- `--tasty-gradient-page-beam`: *(retired)* — the token is preserved as a no-op transparent gradient to avoid breaking external references, but the workspace field no longer consumes it. The diagonal beam was removed when the shell field was neutralized; do not reintroduce it.
- `--tasty-gradient-header-hero`: cream paper base + cyan upper-left + amber whisper lower-right.
- `--tasty-gradient-hero-hairline`: 1px gradient transparent → cyan → blue → amber → transparent. This is the canonical "live boundary" line and is reused by the page header bottom edge, the Heading role-box top edge, and the preview specimen board top edge.
- `--tasty-gradient-role-priority`: quiet spotlight wash for the priority Heading role-box.
- `--tasty-gradient-amber-blob`: friendly amber blob for discovery empty states.

## Motion

All transitions and animations in `admin.css` must use the semantic motion vocabulary tokenized in `tokens.css`. Pick by intent, not by feel.

| Token | Duration | Use |
| --- | --- | --- |
| `--tasty-motion-instant` | `0s` | Structural toggles, reduced-motion fallback |
| `--tasty-motion-hairline` | `0.08s` | Color-only shifts (link hover, badge tint) |
| `--tasty-motion-tap` | `0.11s` | Button press feedback (translate / scale) |
| `--tasty-motion-hover` | `0.16s` | Standard control hover (alias of `duration-base`) |
| `--tasty-motion-reveal` | `0.24s` | Panels / disclosures opening, animated underline |
| `--tasty-motion-stage` | `0.32s` | Page-level transitions, tab swaps |
| `--tasty-motion-celebrate` | `0.52s` | Success flourishes (publish, copied, specimen rise) |

Easings:

- `--tasty-easing-emphasized`: `cubic-bezier(.2, 0, 0, 1)` — Material 3-style emphasized motion.
- `--tasty-easing-decel`: `cubic-bezier(0, 0, .2, 1)` — Apple-style decelerated enter.
- `--tasty-easing-overshoot`: `cubic-bezier(.2, 1.4, .4, 1)` — used only for celebrate moments.
- `--tasty-easing-spring`: legacy alias preserved for the existing toast and copy-pop keyframes.
- `--tasty-easing-base`: legacy alias for older controls.

Composite tokens bundle properties + durations + easings so component CSS can read `transition: var(--tasty-transition-card);` instead of re-declaring everything inline. The composite tokens are:

- `--tasty-transition-control` — buttons, toggles, links, tabs.
- `--tasty-transition-card` — cards, rails, panels.
- `--tasty-transition-rail` — top-level page sections.
- `--tasty-transition-fade` — opacity-only transitions.

**Reduced-motion contract.** Every animation must collapse to instant inside `prefers-reduced-motion: reduce`. The motion tokens themselves are the contract: use them, and reduced-motion is honored automatically. Do not use raw `ms`/`s` durations in `admin.css`.

## Components

### Workspace Shell

The `.tasty-fonts-shell` frames the admin experience. It uses a Stripe-like neutral `#f6f9fc` field with a quiet gray-50 → white → gray-50 paper stripe (`--tasty-gradient-page`), a 14px radius, a white edge, and layered depth. Critically, the field is **neutral, not blue-tinted** — earlier passes layered cyan/blue radials and a diagonal beam on top of the paper stripe, but the cumulative effect was a blue field that flattened every accent placed on top (cream masthead, amber moments, "lifted white card" language). Keep the field neutral so blue + amber + cream all have somewhere to register against.

The shell carries one small `::before` highlight using `--tasty-gradient-fuse` (warm cream/amber, very low opacity) — a hint of light that harmonizes with the masthead amber instead of duplicating the cyan brand color. Do not reintroduce blue radials or diagonal beams on the field.

### Header And Navigation

The page header is compact and three-column: logo, page identity, page navigation. Navigation uses the shared segmented-control recipe with 36px height, 6px radius, blue active states, raised white active tabs, and a faint blue/cyan border fuse. Header radial highlights must remain faint enough to read as lighting rather than decoration. Logo scale, glow positions, tab height, and switcher padding live in masthead tokens, not raw component values.

The header background is `--tasty-gradient-header-hero` — a Soft Cream paper base — plus two layered radial glows tokenized as `--tasty-header-glow-cyan` (upper-left, ~12%) and `--tasty-header-glow-accent` (lower-right, ~14%, **Warm Amber**, not a second blue shape). The two glows are tuned as a quieter pair so the masthead reads as ambient lighting rather than decoration, and so the dominant signal stays the brand mark + H1.

The masthead kicker line (`.tasty-fonts-page-header-kicker`) renders in `--tasty-ink-soft` instead of muted slate and prepends a small `--tasty-status-dot-size-small` Warm Amber dot via `::before` (using `--tasty-status-dot-shadow-warm-amber`). Same dot vocabulary used everywhere else in the plugin, repurposed as a brand-mark accent so the cream paper backdrop reads as intentional.

The bottom edge carries a 1px `--tasty-gradient-hero-hairline` (transparent → cyan → blue → amber → transparent) — the canonical "live boundary" line and one of the small set of authorized blue+amber moments.

The active page-nav tab uses the same raised-surface + blue-text recipe shared by every other "active" control in the plugin (segmented controls, role chips, filter buttons), with one additional `--tasty-shadow-rise-1` layered on top of the legacy `--tasty-shadow-tab-active` so the page-level tab sits a touch higher than its inline siblings — same vocabulary, more confidence. Inactive tabs lift to `--tasty-shadow-rise-1` on hover. Underlines are not used as an "active" cue anywhere in the plugin.

The page H1 uses `--tasty-font-family-display` plus `--tasty-type-page-title-tracking: -0.02em` and `--tasty-type-display-features` for optical refinement. This is the single largest perceived-quality change in the system.

**Responsive wrap.** Under `1100px` the segmented page nav drops to a row below the title block (`grid-template-areas: "logo brand" "nav nav"`). Preserves tap targets and avoids clipping the version pill.

### Buttons

- Primary: Tasty Blue to cyan gradient, white text, 6px radius, subtle inset highlight, and colored lift.
- Secondary: white/off-white surface, slate border, Ink Navy text.
- Destructive: ink-toned surface + soft border + ink text — same neutral chrome as a regular button **plus** bolder weight (`--tasty-font-weight-bold`, 700) and `--tasty-shadow-pressed` on hover/focus/active. The button presses INTO the surface instead of lifting OFF, the opposite tactile direction of a regular button. Templates also prefix destructive labels with a trash / power / reset icon. The existing two-step confirm pattern (`.is-awaiting-confirmation`) provides the safety gate. No red anywhere — destructive affordance is chrome, not color.
- Icon-only buttons must use stable square dimensions and accessible labels.
- Active, hover, disabled, and confirmation states consume button/control tokens. Avoid one-off colors or hand-tuned shadows in component selectors.

The Publish Roles button picks up `--tasty-shadow-spotlight` whenever it carries the `is-pending-live-change` modifier — the cyan + amber dual-layer flag for "your next action lives here." This is the only place a primary button uses the spotlight shadow.

Every copy control in the plugin shares one warm-amber hover language — amber border, Warm Amber Deep ink, and the soft `--tasty-shadow-amber-halo` glow. See "Where Amber Appears" item 8 for the canonical selector list and the Copy-Action Affordance entry under Preview Workspace.

### Cards And Panels

Cards are reserved for top-level page sections and repeated interactive inventory items. Use white surfaces, 8px radius, subtle borders, and restrained navy shadows there. Inside a card, prefer aligned rows, dividers, toolbars, and table-like grids. Do not create box-inside-box layouts for settings groups, command groups, preview controls, or diagnostics metadata.

**Priority hierarchy.** Repeated rails (the roles rail, the family card grid, settings boards) may use a `--tasty-radius-hero` outer radius and the new `--tasty-shadow-rise-*` scale. The roles rail itself carries one continuous horizontal wash (`--tasty-gradient-role-rail`) on the grid container plus a single top hairline (`--tasty-gradient-hero-hairline`) spanning the whole rail — Heading, Body, and Monospace are transparent inside that wash so the trio reads as one unified rail rather than three tinted cells. Other rails may grant priority emphasis to **at most one** entry: a quiet `--tasty-gradient-role-priority` wash + a top hairline using `--tasty-gradient-hero-hairline`. The other current authorized priority entry is the preview specimen board. Adding a new single-entry priority placement requires updating the "Where Amber Appears" appendix.

### Surface System

The surface system is the canonical "this is a container" contract for the plugin. Every card-like element must use one of the four components below — `admin.css` does not allow new selectors that hand-roll `background` + `border` + `border-radius` + `padding` outside this contract. The `tests/js/css-surface-audit.test.cjs` audit fails CI on drift.

**Universal hairline.** Every card-like surface (the resting, interactive, priority, and banner modes below, plus the legacy aliases listed in the migration table) carries a 1px transparent → cyan → blue → amber → transparent gradient (`--tasty-gradient-hero-hairline`) along its top edge. Together with the page header's bottom hairline, this is the canonical "this is a card" boundary line. The hairline is delivered through a single shared `::before` rule in `admin.css` (the unified surface hairline block; the banner has its own scoped `::before` because the `.tasty-fonts-admin .tasty-fonts-banner` selector lives at a different specificity, but the visual contract is identical) and is included by all card-like classes by default — components do NOT redeclare it. Compositional cells (`.tasty-fonts-studio-card`, `.tasty-fonts-role-box`, `.tasty-fonts-code-card`) intentionally do not participate.

**Four components.**

1. **`.tasty-fonts-surface`** — *Resting* (the default). Static container. White surface, 8px radius, faint border, no shadow, 24px padding, plus the universal top hairline. Used for top-level page sections and quiet panels.
2. **`.tasty-fonts-surface.is-interactive`** — *Interactive*. Adds the rise-1 → rise-2 hover lift, pointer cursor, and a `:focus-visible` ring. Use only when the card itself is the click target. Currently authorized: `.tasty-fonts-font-card` (and `.tasty-fonts-search-card` while it remains a button-like row). Adding a new `.is-interactive` use requires updating this appendix.
3. **`.tasty-fonts-surface.is-priority`** — *Priority*. Adds a cream wash (`--tasty-gradient-role-priority`), the warmer `--tasty-border-hero-soft` border, and the larger `--tasty-radius-hero` (12px). The priority hairline is no longer separate — it now comes from the universal hairline shared by every card. Currently authorized: the preview specimen board (the role rail uses the universal resting chrome since every role box is now equal-weight). Adding a new `.is-priority` use requires a DESIGN.md update + audit appendix entry.
4. **`.tasty-fonts-banner`** — *Inline notification*. Now folded into the card chrome — white surface, faint border, 8px radius, plus the universal top hairline. The previous Soft Cream paper + 4px Warm Amber/Ink left rail has been retired; banners now read as a compact card with a single horizontal `auto | 1fr | auto` row (title | message | action pills). Action pills are 24px tall, dense-label sized, single-line `nowrap` so the whole notice stays on one row. The `.is-advisory` and `.is-info` modifiers survive as semantic role classes for ARIA and future state work but no longer change the visual chrome — the message itself + the dense action pills are the read-once-and-dismiss cue. Currently used for the "Local environment detected" prompt; future inline notifications must use this class.

**Tokens.** The system locks card chrome to the following semantic aliases (defined in `tokens.css`): `--tasty-surface-card`, `--tasty-surface-card-border`, `--tasty-surface-card-border-strong`, `--tasty-surface-card-radius`, `--tasty-surface-card-padding`, `--tasty-surface-card-padding-tight`, `--tasty-surface-card-gap`. Banner chrome locks to the same `--tasty-surface-banner-*` token names (`-background`, `-border`, `-rail-advisory`, `-rail-info`, `-rail-width`, `-radius`, `-padding-block`, `-padding-inline`, `-gap`) but those tokens now alias the card surface values: `-background` → `--tasty-surface-card`, `-border` → `--tasty-surface-card-border`, `-rail-width` → `0`, `-rail-advisory` / `-rail-info` → `transparent`, `-radius` → `--tasty-surface-card-radius`. The banner-specific token names survive purely so the `tests/js/css-surface-audit.test.cjs` "banner consumes banner tokens" assertion stays green; component CSS continues to reference `var(--tasty-surface-banner-*)` and gets card values back.

**Migration mapping.** The five legacy card languages collapse to the new system without renaming markup classes — each existing class's primary chrome declaration consumes the surface tokens directly:

| Legacy class | Surface mode |
| --- | --- |
| `.tasty-fonts-card` | Resting |
| `.tasty-fonts-studio-card` (incl. `--sitewide`, `--utilities`) | Compositional cell inside `.tasty-fonts-role-actions` (no chrome of its own) |
| `.tasty-fonts-role-box` (Heading, Body, Monospace) | Compositional cell inside `.tasty-fonts-role-grid` (the rail itself owns the chrome and the hairline; each role box is equal-weight) |
| `.tasty-fonts-role-grid` | Resting (the sitewide-equivalent rail; carries the universal hairline) |
| `.tasty-fonts-role-actions` | Resting (sitewide workflow rail; carries the universal hairline) |
| `.tasty-fonts-font-card` | Interactive |
| `.tasty-fonts-search-card` | Interactive (selectable result row) |
| `.tasty-fonts-source-card`, `.tasty-fonts-google-access`, `.tasty-fonts-search-shell`, `.tasty-fonts-import-panel` | Resting |
| `.tasty-fonts-preview-support-card` | Resting |
| `.tasty-fonts-preview-card-frame` | Resting |
| `.tasty-fonts-health-board`, `.tasty-fonts-settings-board` | Resting (board surfaces; carry the universal hairline) |
| Preview specimen board | Priority (cream wash + larger radius + the universal hairline) |
| `.tasty-fonts-code-card` | Compositional (transparent, no chrome — embedded in a parent) |
| Local environment notice | Banner (`is-advisory` retained as a semantic role class; chrome is identical to a resting card with the universal hairline) |

### Enterprise Layout Rules

- One visual frame per workflow area.
- Nested content uses dividers, not shadows.
- Command decks are single segmented surfaces, not three separate cards.
- Metrics are inline statistic columns with vertical dividers.
- Settings panels are flat sections inside the settings surface.
- Preview controls sit on dividers around the specimen workspace.
- Repeated inventory/search results may remain card-like because they are selectable objects.

### Badges And Pills

Pills are only for status, counts, role chips, copied states, and compact inline tokens. Use semantic color roles for state. Avoid using pills for normal buttons or card containers. The default pill treatment is a quiet white-to-off-white surface with a faint inset highlight; accent pills add blue/cyan tint without becoming candy-colored.

### Status Dots

Small status dots use the `--tasty-status-dot-*` token family. They are 8px markers with a soft two-ring halo. Under the Three-Color Restraint, dot **fill colors** collapse to:

- **Filled blue** — info / active / running / live (was: success/accent)
- **Filled amber** — caution / warning / advisory
- **Hollow ring** — idle / not started / draft
- **Filled gray** — done / verified (paired with a checkmark icon next to the row label)

The semantic shadow tokens (`--tasty-status-dot-shadow-success / -warning / -danger / -accent / -muted / -warm-amber`) keep their names but their colors now resolve through the Three-Color rule: success/danger halos render in ink; warning halos render in Warm Amber; accent and warm-amber halos render in their respective brand colors. Inline brand-mark contexts (the masthead kicker) may use `--tasty-status-dot-size-small` (6px) so the dot reads as punctuation rather than status. Do not hand-roll dot sizes, raw shadows, or flat marker colors in component CSS; set the semantic background and matching `--tasty-status-dot-shadow-*` value instead.

The "Sitewide on" status pill no longer carries a permanent amber halo. It sits next to the LIVE status pill and both communicate the same fact ("sitewide is on" / "live roles active"), so they share the standard active-pill chrome and visually peer. Permanent celebration is decoration; the amber-halo tokens are reserved for a future transient `is-just-applied` class fired for ~2s after a real off→on state change, not for steady states.

### Forms And Controls

Inputs, selects, textareas, and segmented controls use 6px radius, 38px regular height, and tokenized focus shadows. Field surfaces use the shared control gradient, border-gradient, and shadow tokens so every form reads as one Stripe-like system. Segmented controls use `--tasty-segmented-*` tokens for the outer well and active options. Toggles use `--tasty-toggle-*` tokens with a blue primary-on state, restrained slate off state, and the same rectangular rounded-corner language as other controls rather than pill-shaped tracks. Labels use meta typography only when they behave like scan labels; otherwise use normal sentence case body text.

Toggle switches gain a tactile `transform: scale(0.97)` press state using `--tasty-motion-tap`. The thumb translation uses `--tasty-motion-reveal` with `--tasty-easing-emphasized` so the on/off transition feels deliberate rather than mechanical.

**Unified rounded-rectangle form language.** Every form field in the plugin — text inputs, search inputs (including the Library search), selects, and textareas — uses the same `--tasty-radius-control` (6px) rounded-rectangle radius. There is no pill-shaped input variant. The Library search field continues to mark itself as the "type to find" affordance through its leading magnifying-glass icon and a soft focus glow via `--tasty-shadow-focus`, not through a different shape. Pill-shaped chrome (`--tasty-radius-pill`, 999px) is reserved for status pills, badges, segmented controls, and inline status tokens — never for editable form fields. New form components MUST consume `--tasty-radius-control` so the entire dashboard reads as one consistent control framework.

### Settings Row Tables

Settings screens use the same compact row-board language as Advanced Tools Transfer. The Settings wrapper uses a contextual header with the active tab title and description on the left, plus the segmented tab group and paired clear/save actions on the right. Clear changes discards unsaved local edits and Save changes commits them; both stay disabled until the form is dirty. The contextual header must not have its own divider below it; the table board below keeps its normal full border and group-header rules. Each Settings tab renders as a `tasty-fonts-health-board` plus `tasty-fonts-settings-board`, with `tasty-fonts-health-group` headers, a white table surface, faint row dividers, semantic status dots, concise copy on the left, and controls aligned on the right. Output, Integrations, and Behavior must share this contract; do not style them as separate loose panels.

The Save shell lifts when the form is dirty: the rule
`#tasty-fonts-settings-page:has(.tasty-fonts-settings-form.has-unsaved-changes) .tasty-fonts-settings-save-shell`
applies `--tasty-border-amber-soft` plus `--tasty-shadow-rise-2` so the bar reads as the next action without changing position. The `:has()` selector means no JS change is required — the existing `has-unsaved-changes` class on the form drives the styling.

Settings help (`?`) buttons gain a `--tasty-shadow-rise-1` on hover/focus so they are easier to spot without competing with primary controls.

Option-heavy Settings rows should read like Transfer rows, not like wide control belts. Keep the submit-compatible radio inputs available when needed, but present the visible row control as a compact `tasty-fonts-select` proxy in the right control column. Use segmented controls only when the user is making an immediate mode switch in a small toolbar.

Output settings are grouped by the decision the user is making, not by implementation jargon. Use `Stylesheet Delivery` for file/inline loading, `Font Face Rules` for font-display and unicode-range behavior, `Runtime Loading` for minification/preload/preconnect performance controls, and `Output Layers` for variables/classes presets and their nested groups. Avoid one catch-all heading such as `Generated CSS`.

Behavior settings use the same grouped table logic. Use `Release Updates` for update rail selection, `Font Capabilities` for role and variable-font capabilities, `Admin Experience` for guidance and activity visibility, and `Cleanup & Access` for uninstall cleanup plus custom admin access. The custom access master toggle is a first-class table row: its dot, title, passive help, and switch align with every other Settings row, while expanded role/user controls live as quiet detail content underneath.

Integrations settings use categories that match the external surface being controlled, not a generic plugin bucket. Use `Etch Canvas` for the local preview bridge connection, `Builder Sync` for Bricks and Oxygen builder controls, `WordPress Font Library` for Gutenberg library publishing, and `Framework Tokens` for Automatic.css role-variable mapping. Each group keeps the same Transfer-style header: left side names the surface, right side names the action or state such as `Connection`, `Theme Controls`, `Library Sync`, or `Role Mapping`.

The custom access editor is one inset Settings sub-board, not a pair of nested cards. Role and user selectors use compact group headers, white table surfaces, row dividers, selected-only filters, and checkbox rows that share the same spacing and status language as the rest of Settings. Avoid blue-tinted panels or independent card chrome inside this editor.

Settings board spacing is tokenized in `tokens.css`:

- `--tasty-settings-board-row-min-height`
- `--tasty-settings-board-row-padding-block`
- `--tasty-settings-board-row-padding-inline`
- `--tasty-settings-board-row-gap`
- `--tasty-settings-board-detail-indent`
- `--tasty-settings-board-detail-padding-block`
- `--tasty-settings-board-detail-padding-inline`
- `--tasty-settings-board-control-min-width`
- `--tasty-settings-board-control-max-width`

Nested settings may use inset rows or quiet soft panels inside the board, but they should keep the same dividers, status-dot language, and control baselines. Row actions inside Settings use the Advanced Tools row action tokens when they behave like Transfer row actions.

Output layer details are not accordions. Variable and class group controls should flatten into the Settings row table with concise subsection headers, status dots, right-aligned toggles, and passive `?` help. Rows with passive help should not also show the same explanatory sentence inline; keep the copy available to the tooltip and leave the table row itself compact. Do not reintroduce nested disclosure chrome for generated token/class groups. Advanced output rows, including nested variable/class rows, must use the shared Settings row gutter tokens directly on the row surface; do not override them with submenu-specific zero-inline-padding rules.

### Preview Workspace

The preview workspace is a workbench inside the admin workspace. It should make font specimens prominent and keep role controls sticky, compact, and readable. Imported font families are allowed to dominate preview scenes; product UI around them stays system-font.

The specimen board (`.tasty-fonts-preview-specimen-board`) is the canonical "paper" surface in the plugin. It uses `--tasty-surface-paper` (Soft Cream), `--tasty-border-hero-soft`, `--tasty-radius-hero` (12px), and `--tasty-shadow-inset-rail`. A 1px `--tasty-gradient-hero-hairline` runs along the top edge — the second authorized blue+amber pairing in the system. The Aa glyph picks up `--tasty-type-display-tracking` and `--tasty-type-display-features`.

When the Specimen tab becomes active, its scale items animate in via the `tasty-fonts-specimen-rise` keyframe with a 6-step delay ramp drawn from the motion vocabulary (`--tasty-motion-instant` → `--tasty-motion-stage`). The animation is gated behind `prefers-reduced-motion: no-preference`.

**Copy-Action Affordance — tokenized contract.** Every copy control in the plugin consumes a single tokenized hover/focus-visible contract defined in `tokens.css`:

- `--tasty-copy-hover-background` — Warm Amber Soft wash. **Never accent blue.**
- `--tasty-copy-hover-border` — Warm Amber soft border.
- `--tasty-copy-hover-color` — Warm Amber Deep ink.
- `--tasty-copy-hover-shadow` — Soft amber halo glow.

The shared selector list (`admin.css`, "Copy-action affordance" block) covers `.tasty-fonts-preview-copy-css-button` (Preview Workspace), `.tasty-fonts-output-copy-button` (Snippets workspace), `.tasty-fonts-diagnostic-copy-button` (Diagnostics), `.tasty-fonts-stack-copy` (Family card stacks), `.tasty-fonts-role-stack-copy` (Role variable chips), and `.tasty-fonts-pill--copy` (generic copy pill marker). Any copy affordance reads as tactile and "claimable" the moment the cursor lands on it, without becoming a primary action.

**Rules — do not regress:**

- **Always set all four tokens together** on a copy hover state: `background`, `border-color`, `color`, AND `box-shadow`. Setting only three (e.g. omitting `background`) lets older generic-pill rules — including the previously-shipped accent-blue `.tasty-fonts-pill--copy:hover` background — bleed through. We hit this exact regression once; the hover painted blue instead of warm amber. The fix is the four-token discipline, not a one-off override.
- **Do not introduce per-component copy-hover overrides.** Add new copy controls to the shared "Copy-action affordance" selector list in `admin.css`. Do not duplicate the hover declarations under a more-specific selector like `#tasty-fonts-some-page .my-new-copy-button:hover { background: ... }`.
- **Hover background is Warm Amber, never Tasty Blue.** Copy is the second authorized use of `--tasty-shadow-amber-halo` (alongside transient state-change celebrations). If a copy-hover declaration ever references `--tasty-accent` or `--tasty-accent-rgb` for background/border/color, it is a bug.
- **Tokenize first.** If a future variant is needed (e.g. an emphasized copy hover), add a new `--tasty-copy-hover-*` token to `tokens.css` rather than hand-rolling values in `admin.css`.

### Diagnostics And Code

Diagnostics use a code-editor treatment with high contrast, readable 12px-13px monospace text, and clear copy buttons. Syntax palettes may define local raw colors because they are a self-contained code theme.

### Advanced Tools Row Interface

Advanced Tools rows use one compact action system across Overview, Transfer, Generated CSS, Developer, and Activity surfaces. Row actions use `tasty-fonts-advanced-row-action` plus a modifier for the icon intent, such as `--navigate`, `--download`, `--validate`, `--import`, `--snapshot`, `--restore`, or `--support`. Do not use the larger developer action button treatment inside health-board rows.

**Group identity rails.** Each Health group head carries a 4px semantic left rail strip that maps to its modifier:

- `.tasty-fonts-health-group--attention` — `--tasty-danger`
- `.tasty-fonts-health-group--advisory` — `--tasty-warning`
- `.tasty-fonts-health-group--verified` — `--tasty-success`

The strip lives on `::before` so existing background and divider rules continue working. Verified rows quietly mute their titles to `--tasty-text-muted`, drawing the eye to the rows that actually need action.

The shared row action contract is tokenized in `tokens.css`:

- `--tasty-advanced-row-action-height`
- `--tasty-advanced-row-action-min-width`
- `--tasty-advanced-row-action-padding-inline`
- `--tasty-advanced-row-action-gap`
- `--tasty-advanced-row-action-icon-size`
- `--tasty-advanced-row-help-size`
- `--tasty-advanced-row-status-height`
- `--tasty-advanced-row-status-padding-inline`

Help buttons, status chips, and row action buttons should align on the same row baseline and use these tokens for stable sizing. Settings rows use the same passive `?` help affordance as Advanced Tools, with hover/focus copy powered by `data-help-tooltip` rather than a separate tooltip system. Place Settings help in the right-side row action rail immediately before the row control, never inline with the title text. Training-wheels-off mode may hide helper affordances, but it must not change action button dimensions or row alignment.

## CSS Governance

- Keep `tokens.css` as the canonical design source.
- Keep `admin-rtl.css` directional-only.
- Prefer semantic tokens over component-specific one-offs.
- New custom properties must use `--tasty-*` kebab-case.
- New plugin classes and IDs must use the `tasty-fonts-*` namespace unless they are WordPress-provided classes or state classes like `is-active`.
- Avoid raw hex/rgb/hsl values in `admin.css`; define tokens first unless the value is embedded in a data URI. Token-derived `rgba(var(--tasty-*-rgb), …)` composition is allowed only when the source color is a Tasty token.
- Avoid raw typography values in `admin.css`; `font-family`, `font-size`, `font-weight`, `line-height`, and `letter-spacing` declarations must use `var(--tasty-*)`.
- Avoid raw numeric duration/delay values in `admin.css`; every `transition`, `animation-duration`, and `animation-delay` must use a `--tasty-motion-*` token or a composite `--tasty-transition-*` token. The reduced-motion contract relies on the token boundary.
- New shadows must compose from the elevation scale (`--tasty-shadow-flat`, `--tasty-shadow-rise-1/2/3`, `--tasty-shadow-popover`, `--tasty-shadow-spotlight`, `--tasty-shadow-inset-rail`, `--tasty-shadow-pressed`, `--tasty-shadow-amber-halo*`). Do not introduce ad-hoc one-off shadows in component selectors.
- `--tasty-shadow-spotlight` is the only dual-color (cyan + amber) shadow. **Maximum one use per page** — currently the hero specimen board top hairline frame and the Publish Roles pending button.
- `--tasty-font-family-display` is reserved for the page H1 and hero specimen scenes only. All other typography uses `--tasty-font-family-sans`.
- New uses of Warm Amber (`--tasty-warm-amber*`) must be added to the "Where Amber Appears" appendix before being added to `admin.css`. The list is the canonical "should I use amber here?" reference.
- **Three-Color Restraint.** The plugin uses only Tasty Blue (primary) + Warm Amber (secondary) + Soft Cream (tertiary), plus neutrals. Green, red, and the previous warning-amber `#9a6700` are forbidden. The legacy `--tasty-success / --tasty-warning / --tasty-danger` token names survive as semantic role aliases pointing to ink / Warm Amber / ink respectively — they no longer carry green / amber-warning / red identity. State signal in new components must come from icon + chrome (filled vs outlined vs sunken pill, bolder weight on destructive buttons, inset shadow on hover) — not from color. The audit test `tests/js/css-three-color-audit.test.cjs` enforces this at CI time.
- **Surface system.** Every card-like element must use one of `.tasty-fonts-surface` (resting), `.tasty-fonts-surface.is-interactive`, or `.tasty-fonts-surface.is-priority`. Inline notifications must use `.tasty-fonts-banner`. New components MUST NOT define their own `background` + `border` + `border-radius` + `padding` combination — they extend the surface system through the `--tasty-surface-card-*` and `--tasty-surface-banner-*` tokens. Adding a new `.is-interactive` use or a new `.is-priority` use requires updating the Surface System appendix in DESIGN.md before adding to `admin.css`. The audit test `tests/js/css-surface-audit.test.cjs` enforces this at CI time.
- **Universal card hairline.** The 1px transparent → cyan → blue → amber → transparent gradient (`--tasty-gradient-hero-hairline`) is part of the resting card chrome and is delivered through ONE shared `::before` rule in `admin.css` covering every card-like class. Components MUST NOT redeclare it. Adding a new card-like surface means adding the class to the shared selector list, not authoring a new `::before`.
- Settings row geometry is centralized in the transfer-aligned Settings row block in `admin.css`. Earlier Settings sections may style copy, marks, badges, detail bodies, and integration content, but must not re-declare row grids, row gutters, toggle sizing, help placement, or advanced-output nested row padding. Add new row surfaces to the shared selector list instead of creating a more specific submenu override.
- Use Stylelint with `npm run lint:css` for CSS syntax and convention checks.
- Use the Node CSS-token audit through `node --test tests/js/*.test.cjs` to catch cross-file `var(--tasty-*)` references, raw component color values, and raw typography declarations that Stylelint cannot fully validate across separate files.

## Stylelint

Stylelint is viable for this plugin as optional dev tooling. The repo has no runtime npm requirement and no build step, but CI now installs npm dev dependencies to lint committed CSS.

Current tooling:

```bash
npm ci
npm run lint:css
```

The configuration uses `stylelint@17.9.0` and `stylelint-config-standard@40.0.0`, both compatible with the repo's Node.js 22 requirement. Stylelint handles syntax, naming conventions, selector hygiene, and CSS consistency. Property-order linting was intentionally left out because the current CSS needs a separate cleanup before that rule would be a useful CI gate. The separate token audit handles the important limitation that Stylelint's built-in unknown custom-property rule only knows properties defined in the same source.

## Responsive Behavior

- Mobile under 640px: single-column panels, wrapped controls, full-width action groups, compact header logo.
- Tablet 640px-1024px: two-column layouts where content density remains readable.
- Desktop 1024px+: full role/library/settings layouts with compact toolbars.
- Wide desktop: preserve the max-width workspace; do not stretch scan rows into unreadable line lengths.

Stable dimensions are required for controls, tab buttons, icon buttons, preview trays, role cards, and font cards so hover states and dynamic labels do not shift layouts.
