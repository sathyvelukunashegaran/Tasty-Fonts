# Tasty Foundry Design System

Tasty Foundry is the admin design system for Tasty Custom Fonts. It is a WordPress-native workspace for managing font libraries, delivery profiles, role previews, diagnostics, and settings. The system borrows Stripe's product discipline: crisp typography, aligned work surfaces, precise borders, restrained depth, and compact controls. The visual accent is Tasty Blue from the logo, supported by quiet cyan edge lighting and neutral slate surfaces.

Related docs: [`AGENTS.md`](AGENTS.md) for agent workflow, [`ARCHITECTURE.md`](ARCHITECTURE.md) for repo-local architecture, and [`.agents/README.md`](.agents/README.md) for shared agent context.

## Principles

- **Font work first:** imported families, role pairings, delivery profiles, generated CSS, and diagnostics must stay easier to scan than the surrounding chrome.
- **WordPress-native:** use WordPress button semantics, accessible focus states, and the existing `.tasty-fonts-admin` namespace while layering a more polished SaaS surface treatment on top.
- **Token-first:** `assets/css/tokens.css` is the source of truth for colors, spacing, typography, radius, shadows, motion, and component constants.
- **Stripe-like, not copied:** use Stripe-inspired structure, dense grids, white surfaces, disciplined spacing, and restrained depth without copying proprietary assets, exact layouts, or bundled fonts.
- **Confident contrast:** page identity, primary actions, cards, and code views should feel more premium than a default WordPress settings screen.

## Palette

| Role | Token | Value | Use |
| --- | --- | --- | --- |
| Ink Navy | `--tasty-ink` | `#0a2540` | Headings, strong text, high-contrast UI |
| Tasty Blue | `--tasty-brand-blue` | `#0f6fb7` | Primary actions, selected states, focus accents |
| Tasty Blue Hover | `--tasty-brand-blue-hover` | `#0b5f9e` | Hover and active gradient depth |
| Tasty Cyan | `--tasty-brand-cyan` | `#32c5f4` | Faint edge lighting and border highlights |
| Blue Wash | `--tasty-brand-blue-soft` | `#e7f3fb` | Soft accent surfaces |
| Workbench Slate | `--tasty-slate` | `#425466` | Secondary text and quiet labels |
| Surface | `--tasty-gray-0` | `#ffffff` | Cards, controls, primary panels |
| Surface Soft | `--tasty-gray-50` | `#f6f9fc` | Shell background and preview workspace |
| Surface Muted | `--tasty-gray-100` | `#eef3f8` | Inset panels, toolbar wells |
| Mint | `--tasty-success` | `#138a4b` | Success badges and copied states |
| Amber | `--tasty-warning` | `#9a6700` | Warnings and cautionary settings |
| Ruby | `--tasty-danger` | `#c8323a` | Destructive actions and danger states |

Raw colors belong in `tokens.css` first. `admin.css` should consume semantic tokens, including self-contained code-preview palettes and integration-brand marks.

## Typography

Product UI uses an optional Stripe-like local stack first, then the WordPress/admin system stack. No proprietary or remote font is bundled.

```css
--tasty-font-family-sans: Inter, "Sohne", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
--tasty-font-family-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
```

| Role | Token | Size | Weight | Notes |
| --- | --- | --- | --- | --- |
| Page title | `--tasty-type-page-title-size` | `28px-36px` | `700` | Confident workspace identity without marketing-page scale |
| Section title | `--tasty-type-section-title-size` | `18px-22px` | `600` | Major admin panels |
| Panel title | `--tasty-type-panel-title-size` | `17px-19px` | `600` | Grouped settings and workflow panels |
| Card title | `--tasty-type-card-title-size` | `16px-18px` | `600` | Font cards, role cards, status cards |
| Body | `--tasty-type-body-size` | `13px` | `400` | Default admin reading text |
| Body large | `--tasty-type-body-large-size` | `14px` | `400` | Summaries and short descriptions |
| Caption | `--tasty-type-caption-size` | `12px` | `400` | Helper text, diagnostics metadata |
| Meta | `--tasty-type-meta-size` | `11px` | `600` | Uppercase labels only |
| Dense label | `--tasty-type-label-dense-size` | `10px` | `600-700` | Version pills, tight badges, tiny status labels |
| Micro label | `--tasty-type-label-micro-size` | `9px` | `700` | Exceptional diagnostic count chips only |
| Code output | `--tasty-type-code-output-size` | `12.5px` | `400-500` | Generated CSS and diagnostics viewers |

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
| Shell | `--tasty-radius-xl` | `14px` | The outer admin workspace |
| Pill | `--tasty-radius-pill` | `999px` | Badges and inline status tokens only |

Depth is Stripe-like but operational: white cards, faint borders, a small shell fuse, and layered navy shadows. Avoid pastel bloom around normal cards.

- `--tasty-shadow-card`: default card lift.
- `--tasty-shadow-card-hover`: interactive card hover lift.
- `--tasty-shadow-overlay`: popovers, diagnostics overlays, tooltips.
- `--tasty-shadow-focus`: keyboard focus ring.
- `--tasty-shadow-shell` and `--tasty-shadow-masthead`: admin workspace structure.

Use gradients intentionally: the shell and header may include faint blue/cyan fuse lighting, while cards and controls stay mostly white with subtle borders. Inner groups should use dividers, spacing, and typography instead of more boxes.

## Components

### Workspace Shell

The `.tasty-fonts-shell` frames the admin experience. It uses a Stripe-like `#f6f9fc` field, a 14px radius, a white edge, layered depth, and a faint blue/cyan fuse. It should feel like a premium SaaS console, not a plain WordPress metabox or a decorative gradient panel.

### Header And Navigation

The page header is compact and three-column: logo, page identity, page navigation. Navigation uses the shared segmented-control recipe with 36px height, 6px radius, blue active states, raised white active tabs, and a faint blue/cyan border fuse. Header radial highlights must remain faint enough to read as lighting rather than decoration. Logo scale, glow positions, tab height, and switcher padding live in masthead tokens, not raw component values.

### Buttons

- Primary: Tasty Blue to cyan gradient, white text, 6px radius, subtle inset highlight, and colored lift.
- Secondary: white/off-white surface, slate border, Ink Navy text.
- Danger: Ruby text and soft Ruby surface until confirmation states require stronger contrast.
- Icon-only buttons must use stable square dimensions and accessible labels.
- Active, hover, disabled, and confirmation states consume button/control tokens. Avoid one-off colors or hand-tuned shadows in component selectors.

### Cards And Panels

Cards are reserved for top-level page sections and repeated interactive inventory items. Use white surfaces, 8px radius, subtle borders, and restrained navy shadows there. Inside a card, prefer aligned rows, dividers, toolbars, and table-like grids. Do not create box-inside-box layouts for settings groups, command groups, preview controls, or diagnostics metadata.

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

Small status dots use the `--tasty-status-dot-*` token family. They are 8px markers with a soft two-ring halo, using semantic shadow tokens for accent, success, warning, danger, and muted states. Dense bullets may use `--tasty-status-dot-size-small`, but they should keep the same radius and halo language. Do not hand-roll dot sizes, raw shadows, or flat marker colors in component CSS; set the semantic background and matching `--tasty-status-dot-shadow-*` value instead.

### Forms And Controls

Inputs, selects, textareas, and segmented controls use 6px radius, 38px regular height, and tokenized focus shadows. Field surfaces use the shared control gradient, border-gradient, and shadow tokens so every form reads as one Stripe-like system. Segmented controls use `--tasty-segmented-*` tokens for the outer well and active options. Toggles use `--tasty-toggle-*` tokens with a blue primary-on state, restrained slate off state, and the same rectangular rounded-corner language as other controls rather than pill-shaped tracks. Labels use meta typography only when they behave like scan labels; otherwise use normal sentence case body text.

### Settings Row Tables

Settings screens use the same compact row-board language as Advanced Tools Transfer. The Settings wrapper uses a contextual header with the active tab title and description on the left, plus the segmented tab group and paired clear/save actions on the right. Clear changes discards unsaved local edits and Save changes commits them; both stay disabled until the form is dirty. The contextual header must not have its own divider below it; the table board below keeps its normal full border and group-header rules. Each Settings tab renders as a `tasty-fonts-health-board` plus `tasty-fonts-settings-board`, with `tasty-fonts-health-group` headers, a white table surface, faint row dividers, semantic status dots, concise copy on the left, and controls aligned on the right. Output, Integrations, and Behavior must share this contract; do not style them as separate loose panels.

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

### Diagnostics And Code

Diagnostics use a code-editor treatment with high contrast, readable 12px-13px monospace text, and clear copy buttons. Syntax palettes may define local raw colors because they are a self-contained code theme.

### Advanced Tools Row Interface

Advanced Tools rows use one compact action system across Overview, Transfer, Generated CSS, Developer, and Activity surfaces. Row actions use `tasty-fonts-advanced-row-action` plus a modifier for the icon intent, such as `--navigate`, `--download`, `--validate`, `--import`, `--snapshot`, `--restore`, or `--support`. Do not use the larger developer action button treatment inside health-board rows.

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
