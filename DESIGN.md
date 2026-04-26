---
name: "Tasty Foundry"
description: "A precise WordPress-native admin design system for managing fonts, delivery profiles, runtime CSS, and publishing state."
colors:
  primary-blue: "#0f6fb7"
  primary-blue-hover: "#0b5f9e"
  primary-blue-active: "#084a7d"
  primary-cyan: "#32c5f4"
  secondary-amber: "#e89a3c"
  secondary-amber-deep: "#c97c1f"
  tertiary-paper: "#faf7f2"
  tertiary-paper-deep: "#f5f0e6"
  neutral-bg: "#f6f9fc"
  neutral-muted: "#eef3f8"
  neutral-border: "#d9e2ec"
  neutral-slate: "#425466"
  neutral-ink: "#0a2540"
  neutral-ink-soft: "#1c2e44"
typography:
  display:
    fontFamily: "-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Oxygen-Sans, Ubuntu, Cantarell, Helvetica Neue, sans-serif"
    fontSize: "clamp(24px, 1.45vw, 30px)"
    fontWeight: 700
    lineHeight: 1.02
    letterSpacing: "-0.02em"
  headline:
    fontFamily: "-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Oxygen-Sans, Ubuntu, Cantarell, Helvetica Neue, sans-serif"
    fontSize: "clamp(18px, 1.25vw, 22px)"
    fontWeight: 600
    lineHeight: 1.12
  title:
    fontFamily: "-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Oxygen-Sans, Ubuntu, Cantarell, Helvetica Neue, sans-serif"
    fontSize: "clamp(17px, 1.05vw, 19px)"
    fontWeight: 600
    lineHeight: 1.18
  body:
    fontFamily: "-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Oxygen-Sans, Ubuntu, Cantarell, Helvetica Neue, sans-serif"
    fontSize: "13px"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Oxygen-Sans, Ubuntu, Cantarell, Helvetica Neue, sans-serif"
    fontSize: "12px"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "0.08em"
  mono:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, monospace"
    fontSize: "12.5px"
    fontWeight: 400
    lineHeight: 1.75
rounded:
  s: "4px"
  control: "6px"
  card: "8px"
  hero: "12px"
  shell: "14px"
  pill: "999px"
spacing:
  space-1: "4px"
  space-2: "8px"
  space-3: "12px"
  space-4: "16px"
  space-5: "20px"
  space-6: "24px"
  space-8: "32px"
  space-10: "40px"
components:
  button-primary:
    backgroundColor: "{colors.primary-blue}"
    textColor: "{colors.tertiary-paper}"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    padding: "8px 14px"
    height: "32px"
  button-primary-hover:
    backgroundColor: "{colors.primary-blue-hover}"
    textColor: "{colors.tertiary-paper}"
    rounded: "{rounded.control}"
  button-secondary:
    backgroundColor: "{colors.neutral-bg}"
    textColor: "{colors.neutral-ink}"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    padding: "8px 14px"
    height: "32px"
  field:
    backgroundColor: "{colors.neutral-bg}"
    textColor: "{colors.neutral-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.control}"
    padding: "8px 12px"
    height: "38px"
  surface-card:
    backgroundColor: "{colors.neutral-bg}"
    textColor: "{colors.neutral-ink}"
    rounded: "{rounded.card}"
    padding: "24px"
  pill-status:
    backgroundColor: "{colors.neutral-bg}"
    textColor: "{colors.neutral-ink}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "4px 10px"
    height: "24px"
  nav-tab-active:
    backgroundColor: "{colors.neutral-bg}"
    textColor: "{colors.primary-blue-hover}"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    height: "34px"
---

# Design System: Tasty Foundry

## 1. Overview

**Creative North Star: "The Typography Workbench"**

Tasty Foundry is a workbench for serious WordPress typography: compact, ordered, and tactile enough to make font operations feel safe. The interface serves site builders who are choosing families, delivery profiles, role assignments, generated CSS, integrations, and publishing state under real client pressure. It should feel like a precise admin tool that happens to be beautifully made, not a marketing surface that happens to contain settings.

The dominant scene is a site implementer reviewing typography inside WordPress during daylight work, often with another admin tab, a builder canvas, and a client request nearby. That scene forces a light, high-contrast, quiet surface: neutral workbench field, white to off-white panels, crisp borders, restrained navy depth, and one dependable blue action language. The system rejects generic WordPress option dumps, bloated font-plugin clutter, noisy SaaS dashboards, decorative AI-looking glow, and red or green state dependency.

Tasty Foundry may borrow Stripe-like product discipline, but it must not copy Stripe, Linear, Figma, Notion, Raycast, or WordPress admin. Those products are quality references for alignment, density, focus states, restrained feedback, and interaction discipline only.

**Key Characteristics:**

- WordPress-native product UI with a more refined surface treatment than default admin chrome.
- Restrained three-color palette: Tasty Blue, Warm Amber, Soft Cream, plus neutrals.
- Font work stays visually louder than the shell around it.
- State is shown through icons, copy, surface tone, filled or outlined chrome, and focus treatment, not red or green alone.
- Components are familiar on purpose: buttons, fields, tabs, cards, rows, chips, and diagnostics should disappear into the workflow.

## 2. Colors

The palette is restrained and operational: Tasty Blue handles action and selection, Warm Amber handles caution and claimable copy moments, Soft Cream handles hero paper surfaces, and neutrals carry almost everything else.

### Primary

- **Tasty Blue** (`--tasty-brand-blue`, frontmatter `primary-blue`): primary actions, selected tabs, active controls, focus accents, information state, and structural syntax tokens.
- **Tasty Blue Hover** (`--tasty-brand-blue-hover`, frontmatter `primary-blue-hover`): hover and elevated active text states where the base blue needs more contrast.
- **Tasty Blue Active** (`--tasty-brand-blue-active`, frontmatter `primary-blue-active`): pressed primary states and high-contrast variable or token references.
- **Tasty Cyan** (`--tasty-brand-cyan`, frontmatter `primary-cyan`): faint edge lighting, hairline participation, and dark code structural tokens. Treat it as a blue-family tint, not a separate accent.

### Secondary

- **Warm Amber** (`--tasty-warm-amber`, frontmatter `secondary-amber`): caution, warning, the masthead dot, active family warmth, copy-hover affordance, code values, and rare next-action emphasis.
- **Warm Amber Deep** (`--tasty-warm-amber-deep`, frontmatter `secondary-amber-deep`): hover contrast for copy affordances and light-theme code values.

### Tertiary

- **Soft Cream Paper** (`--tasty-paper`, frontmatter `tertiary-paper`): masthead paper, preview specimen board, integration mark chrome, and warm hero surfaces.
- **Soft Cream Deep** (`--tasty-paper-deep`, frontmatter `tertiary-paper-deep`): inner paper edges, specimen board borders, and warm surface detail.

### Neutral

- **Workbench Field** (`--tasty-gray-50`, frontmatter `neutral-bg`): admin shell background and preview workspace field.
- **Muted Surface** (`--tasty-gray-100`, frontmatter `neutral-muted`): toolbar wells, segmented-control wells, and inset panels.
- **Soft Border** (`--tasty-gray-300`, frontmatter `neutral-border`): default borders, row dividers, control edges.
- **Workbench Slate** (`--tasty-slate`, frontmatter `neutral-slate`): secondary text, quiet labels, code punctuation.
- **Ink Navy** (`--tasty-ink`, frontmatter `neutral-ink`): headings, strong text, high-contrast UI, and neutralized success or danger roles.
- **Ink Soft** (`--tasty-ink-soft`, frontmatter `neutral-ink-soft`): warm body ink on paper surfaces and masthead kicker text.

### Named Rules

**The Three-Color Restraint Rule.** The product uses Tasty Blue, Warm Amber, Soft Cream, and neutrals. Green and red are prohibited in the admin theme. Success and danger remain semantic role names, but they resolve through ink, icons, filled or outlined chrome, and inset shadow.

**The Amber Permission Rule.** Warm Amber is not decoration. Valid uses are caution, warning, copy-hover affordance, active family warmth, dirty settings state, preview specimen paper, masthead punctuation, code values, and rare next-action emphasis. Add any new amber use to this document before adding it to CSS.

**The Neutral Workbench Rule.** The shell field stays neutral. Do not reintroduce blue radials, diagonal beams, or full-surface color washes that flatten the cream masthead, amber cues, or lifted card language.

## 3. Typography

**Display Font:** WordPress/admin system stack with optical tracking refinements.
**Body Font:** WordPress/admin system stack.
**Label/Mono Font:** System UI for labels, `ui-monospace` stack for generated CSS, diagnostics, and code preview.

**Character:** Typography is compact and trustworthy. Hierarchy comes from weight, line height, spacing, and restrained scale, not decorative font pairing. Managed font families may dominate specimen scenes; product UI around them stays system-font.

### Hierarchy

- **Display** (700, `clamp(24px, 1.45vw, 30px)`, 1.02, `-0.02em`): page H1 only. It gives the workspace identity without becoming a marketing hero.
- **Headline** (600, `clamp(18px, 1.25vw, 22px)`, 1.12): major admin sections and high-level panels.
- **Title** (600, `clamp(17px, 1.05vw, 19px)`, 1.18): grouped settings, health boards, and workflow panel titles.
- **Card Title** (600, `clamp(16px, 0.95vw, 18px)`, 1.15): font cards, role cards, and repeated inventory objects.
- **Body** (400, 13px, 1.5): default UI reading text. Long explanatory prose should stay within 65 to 75 characters when possible.
- **Body Large** (400, 14px, 1.5): summaries and short descriptions.
- **Label** (600, 12px, 1.2, selected tokenized tracking): pills, badges, meta labels, tabs, compact controls, and status chips.
- **Code** (400 to 500, 12.5px, 1.75): generated CSS, diagnostics, and code preview windows.

### Named Rules

**The System Stack Rule.** Product UI uses the system sans stack. Do not introduce a remote or proprietary UI font.

**The Display Quarantine Rule.** `--tasty-font-family-display` is reserved for the page H1 and hero specimen scenes only. Do not use display treatment in labels, buttons, tables, data, or form controls.

**The Twelve Pixel Floor Rule.** No readable UI text goes below 12px. Dense interfaces get compact through line height, weight, alignment, and iconography, not smaller text.

## 4. Elevation

Tasty Foundry uses structural depth: faint borders, precise radius, navy shadow falloff, top hairlines, and occasional inset paper treatment. Surfaces are calm at rest. Lift appears when a card is interactive, selected, focused, or temporarily asking for attention. Glow is rare and must prove a workflow purpose.

### Shadow Vocabulary

- **Flat** (`--tasty-shadow-flat`): default for quiet rows, segmented wells, and controls that should not float.
- **Rise 1** (`--tasty-shadow-rise-1`): hairline lift for resting cards on hover or elevated active tabs.
- **Rise 2** (`--tasty-shadow-rise-2`): standard interactive card hover and dirty settings save shell.
- **Rise 3** (`--tasty-shadow-rise-3`): selected or applied card emphasis.
- **Popover** (`--tasty-shadow-popover`): overlays, tooltips, menus, and diagnostics layers.
- **Spotlight** (`--tasty-shadow-spotlight`): dual cyan and amber attention. Maximum once per page, currently for preview specimen or pending publish intent.
- **Inset Rail** (`--tasty-shadow-inset-rail`): paper and code panels that should feel recessed rather than floating.
- **Pressed** (`--tasty-shadow-pressed`): destructive and active button feedback that moves into the surface.
- **Amber Halo** (`--tasty-shadow-amber-halo`): copy-hover affordance and future transient celebrations only.
- **Focus** (`--tasty-shadow-focus`): keyboard-visible focus ring around interactive controls.

### Named Rules

**The Flat-Until-Interactive Rule.** Static surfaces do not float for decoration. If a shadow appears, it must communicate hover, selection, focus, overlay, pending action, or pressed state.

**The One Spotlight Rule.** `--tasty-shadow-spotlight` appears at most once on a page. Two spotlights means no spotlight.

**The No Decorative Glow Rule.** Glow must be tied to a state or a tiny atmospheric role already named here. Do not add glass, blur, bloom, or ambient gradients because a screen feels empty.

## 5. Components

Components should feel familiar, dense, and reliable. They use standard product affordances, stable dimensions, keyboard-visible focus, and tokenized state changes.

### Buttons

- **Shape:** compact rounded rectangle (`--tasty-radius-control`, 6px). Never pill-shaped for normal buttons.
- **Primary:** Tasty Blue surface, paper-toned text, compact height (`32px`) or WordPress button height when inherited, tokenized lift through primary button shadows.
- **Hover / Focus:** darker blue on hover, `--tasty-shadow-focus` on keyboard focus, `--tasty-transition-control` for color, border, shadow, and transform.
- **Secondary:** neutral raised surface, slate border, ink text. It should read as a tool, not a ghost link.
- **Destructive:** no red. Use icon prefix, bolder label, pressed inset hover/focus/active feedback, and the existing two-step confirmation pattern.

### Chips

- **Style:** pills are for status, counts, role chips, copied states, and compact inline tokens only.
- **State:** active and live can use filled neutral or blue state. Warning uses Warm Amber. Idle uses outlined or hollow treatment. Done uses gray plus checkmark.
- **Copy chips:** all copy controls use the shared Warm Amber hover contract: background, border, color, and shadow together.

### Cards / Containers

- **Corner Style:** standard card radius (`8px`), hero surfaces (`12px`), shell (`14px`).
- **Background:** card-like surfaces consume `--tasty-surface-card` and related aliases. The frontmatter approximates the visible card system through neutral workbench tokens; CSS tokens remain canonical.
- **Shadow Strategy:** resting cards are quiet. Interactive cards get rise on hover. Selected cards use warmer border and rise. Priority cards use paper wash and larger radius.
- **Border:** faint neutral border by default. Active family cards may use `--tasty-border-amber-soft` because active-family warmth is an approved amber moment.
- **Internal Padding:** top-level surfaces use `24px`; tighter cards and library surfaces use `16px` to `20px` through token aliases.
- **Hairline:** the universal top hairline is a shared container boundary. Components must join the shared selector list instead of redefining their own hairline.

### Inputs / Fields

- **Style:** all text inputs, search inputs, selects, and textareas use the same 6px rounded rectangle, 38px regular height, white to off-white surface, faint border, and tokenized padding.
- **Focus:** blue active border plus `--tasty-shadow-focus`.
- **Disabled:** muted surface and text. Disabled fields must stay readable.
- **Error:** do not invent red. Use copy, icon, field association, and neutral or pressed chrome unless the state is advisory, in which case Warm Amber is allowed.

### Navigation

- **Style:** page navigation and segmented controls share one raised active-tab language: compact tab height, 6px radius, neutral well, white active surface, blue active text, and faint border fuse.
- **Hover / Active:** inactive tabs may lift slightly on hover. Active tabs are raised, not underlined.
- **Responsive:** under 1100px, the page nav wraps below the brand block. Under tablet and mobile breakpoints, panels stack, controls wrap, and actions become full-width only when needed for tap comfort.

### Signature Component: Preview Specimen Board

The specimen board is the canonical paper surface. It uses Soft Cream, 12px radius, inset rail shadow, a top hairline, and managed font families as the visual subject. Controls around it stay system-font and compact so the font specimen remains the hero.

### Signature Component: Settings Row Board

Settings screens use compact row boards, not loose option dumps. Group headers describe the decision, rows align label, passive help, state dot, and control, and nested content stays as inset detail inside the same board. Output, Integrations, Behavior, and access controls must share this table-like language.

### Signature Component: Code And Diagnostics Windows

Code windows use ink, paper, blue, amber, and neutrals only. Tasty Blue marks structure: selectors, properties, at-rules, keywords. Warm Amber marks values: strings, numbers, functions, attributes. Avoid syntax rainbows.

## 6. Do's and Don'ts

### Do:

- **Do** keep font work first: families, delivery profiles, role assignments, generated CSS, and diagnostics must be more scannable than surrounding chrome.
- **Do** preserve draft-before-live state. Make unsaved changes, pending publish state, live roles, and applied families visually unambiguous.
- **Do** use Tasty Blue for primary action, current selection, focus, and information state.
- **Do** use Warm Amber only for documented amber moments: warning, caution, copy-hover, dirty settings, active family warmth, specimen paper, masthead punctuation, code values, and rare next-action emphasis.
- **Do** use icons, labels, filled or outlined chrome, and pressed treatment for state. Color alone is insufficient.
- **Do** keep settings, transfer, diagnostics, and advanced tools row-based where the user is scanning decisions or actions.
- **Do** use tokenized typography, spacing, radius, shadows, and motion from `assets/css/tokens.css` before adding new values.
- **Do** collapse animations and transitions through the reduced-motion contract.
- **Do** make keyboard focus visible and preserve WordPress admin semantics.

### Don't:

- **Don't** build generic WordPress settings pages that feel like long option dumps.
- **Don't** create bloated font-plugin clutter that exposes implementation details before user intent.
- **Don't** use noisy SaaS dashboard tropes, decorative gradients, fake metrics, or empty excitement.
- **Don't** make the interface look AI-generated through repetitive cards, excess badges, decorative glow, or arbitrary color.
- **Don't** copy Stripe, Linear, Figma, Notion, Raycast, or WordPress admin directly. Use them only as quality references.
- **Don't** rely on red or green state color. Red and green are prohibited in the admin theme.
- **Don't** use side-stripe borders as card, row, alert, or callout accents. Use full borders, icons, dots, background tone, or copy instead.
- **Don't** use gradient text, default glassmorphism, modal-first workflows, or repeated identical icon-card grids.
- **Don't** add nested cards inside cards. Use rows, dividers, grouped tables, or inset detail regions.
- **Don't** introduce raw color, raw typography, raw motion duration, or ad-hoc shadow values in `admin.css` when a token exists.
- **Don't** use display typography for labels, buttons, tables, or data.
