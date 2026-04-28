/**
 * Surface System audit.
 *
 * The plugin uses ONE container chrome contract: the surface system.
 * Three modes (.tasty-fonts-surface resting, .is-interactive, .is-priority)
 * plus a separate .tasty-fonts-banner component for inline notifications.
 * See the surface system notes.
 *
 * Every card-like selector in admin.css MUST consume the surface tokens
 * (`--tasty-surface-card-*` for cards, `--tasty-surface-banner-*` for
 * banners) instead of hand-rolling background + border + border-radius +
 * padding + box-shadow. This audit fails CI when a new selector
 * declares all four of those properties without composing from the
 * surface tokens.
 *
 * What's audited:
 *   - Every rule whose selector ends in a card-like name
 *     (`*-card`, `*-card-*`, `*-box`).
 *   - For each, we scan the rule body. If the rule sets all four of
 *     `background`, `border` (or `border-radius`), `padding`, and
 *     `box-shadow` directly without referencing the surface tokens,
 *     it's a violation.
 *
 * What's allowed:
 *   - Selectors on the COMPOSITIONAL allowlist below — these are
 *     intentionally chrome-less because their parent owns the chrome
 *     (e.g. .tasty-fonts-studio-card sits inside .tasty-fonts-role-actions
 *     which has the actual border + radius).
 *   - Selectors that DO consume the surface tokens — those are exactly
 *     what the system is supposed to look like.
 *   - Modifier selectors (`.foo.is-active`, `.foo--variant`) which only
 *     adjust state on top of an already-token-driven base.
 */

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const repoRoot = path.resolve(__dirname, '../..');
const adminCssPath = path.join(repoRoot, 'assets/css/admin.css');

/**
 * Compositional cells — selectors that intentionally have no chrome
 * because their parent rail/grid owns the border + radius. These
 * appear in the surface migration table as "Compositional".
 */
const COMPOSITIONAL_SELECTORS = new Set([
  '.tasty-fonts-studio-card',
  '.tasty-fonts-role-box',
  '.tasty-fonts-code-card'
]);

/**
 * Selectors that are part of the surface contract definition itself
 * (the canonical .tasty-fonts-surface rules, the banner rules) — these
 * ARE the system, so they don't get audited as "drift".
 */
const SYSTEM_DEFINING_SELECTORS = [
  /^\.tasty-fonts-surface(\.|:|$|\s)/,
  /^\.tasty-fonts-admin\s+\.tasty-fonts-banner/,
  /^\.tasty-fonts-banner/
];

/**
 * Properties that, when set together on a single rule, constitute
 * "this rule defines its own card chrome". The audit fails when these
 * appear together without the corresponding surface tokens.
 */
const CARD_CHROME_PROPERTIES = new Set([
  'background',
  'border',
  'border-radius',
  'padding',
  'box-shadow'
]);

const SURFACE_TOKEN_HINTS = [
  '--tasty-surface-card',
  '--tasty-surface-banner',
  // .tasty-fonts-surface itself + the rise-* shadow scale used by .is-interactive
  // are part of the system; rules that compose from them are compliant.
  '--tasty-shadow-rise-',
  '--tasty-shadow-flat',
  '--tasty-transition-card'
];

function isCardLikeSelector(selector) {
  // Match anything ending in -card, -card-{suffix}, or -box (with or
  // without a trailing modifier or pseudo-class).
  return /-card(-[a-z]+)?(\.[a-z][\w-]*|::?[a-z-]+|--[\w-]+)?\s*$/i.test(selector)
    || /-box(\.[a-z][\w-]*|::?[a-z-]+|--[\w-]+)?\s*$/i.test(selector);
}

function isSystemDefiningSelector(selector) {
  return SYSTEM_DEFINING_SELECTORS.some((rx) => rx.test(selector));
}

function isCompositionalSelector(selector) {
  for (const candidate of COMPOSITIONAL_SELECTORS) {
    // Match either an exact equality or a selector that begins with the
    // compositional class plus a state/modifier (.tasty-fonts-role-box.is-priority).
    const escaped = candidate.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const rx = new RegExp(`(^|\\s)${escaped}(\\.[\\w-]+|:[\\w()-]+|--[\\w-]+)?(\\s|$)`);
    if (rx.test(selector)) {
      return true;
    }
  }
  return false;
}

/**
 * Walk admin.css and yield each top-level rule as
 * { selector, body, lineNumber }. Comments and at-rules are skipped.
 * Nested at-rules (`@media { ... }`) are descended into so the rules
 * they contain still get audited.
 */
function* iterateRules(source) {
  // Strip comments while preserving newlines for line numbers.
  const cleaned = source.replace(/\/\*[\s\S]*?\*\//g, (block) => block.replace(/[^\n]/g, ' '));

  let cursor = 0;
  let lineCursor = 1;

  // Helper: count newlines between two positions to keep lineCursor in sync.
  const advance = (target) => {
    while (cursor < target) {
      if (cleaned[cursor] === '\n') {
        lineCursor += 1;
      }
      cursor += 1;
    }
  };

  while (cursor < cleaned.length) {
    // Skip whitespace.
    if (/\s/.test(cleaned[cursor])) {
      advance(cursor + 1);
      continue;
    }

    // Handle @-rules. We descend into @media/@supports bodies; we skip
    // single-line @-rules (e.g. @charset, @import) wholesale.
    if (cleaned[cursor] === '@') {
      const semicolon = cleaned.indexOf(';', cursor);
      const brace = cleaned.indexOf('{', cursor);
      if (brace === -1 || (semicolon !== -1 && semicolon < brace)) {
        advance(semicolon + 1);
        continue;
      }
      // Walk past the @-rule prelude into its body.
      advance(brace + 1);
      // Recurse by yielding rules from the inner content. We keep a
      // depth counter to know when this @-rule body ends.
      let depth = 1;
      const innerStart = cursor;
      while (cursor < cleaned.length && depth > 0) {
        if (cleaned[cursor] === '{') depth += 1;
        else if (cleaned[cursor] === '}') depth -= 1;
        if (depth === 0) break;
        if (cleaned[cursor] === '\n') lineCursor += 1;
        cursor += 1;
      }
      const innerEnd = cursor;
      const innerSource = cleaned.slice(innerStart, innerEnd);
      // Compute the line offset for inner rules.
      const offsetLine = lineCursor - innerSource.split('\n').length + 1;
      for (const inner of iterateRules(innerSource)) {
        yield { ...inner, lineNumber: inner.lineNumber + offsetLine - 1 };
      }
      // Skip the closing brace.
      advance(cursor + 1);
      continue;
    }

    // Find the end of the selector.
    const brace = cleaned.indexOf('{', cursor);
    if (brace === -1) {
      break;
    }

    const selectorRaw = cleaned.slice(cursor, brace);
    const selectorLine = lineCursor;
    advance(brace + 1);

    // Find matching close brace, accounting for nesting.
    let depth = 1;
    const bodyStart = cursor;
    while (cursor < cleaned.length && depth > 0) {
      if (cleaned[cursor] === '{') depth += 1;
      else if (cleaned[cursor] === '}') depth -= 1;
      if (depth === 0) break;
      if (cleaned[cursor] === '\n') lineCursor += 1;
      cursor += 1;
    }
    const body = cleaned.slice(bodyStart, cursor);
    advance(cursor + 1);

    yield {
      selector: selectorRaw.trim().replace(/\s+/g, ' '),
      body,
      lineNumber: selectorLine
    };
  }
}

/**
 * A "chrome reset" rule sets all card properties to their no-chrome
 * sentinels (`0`, `none`, `transparent`). Those rules are intentionally
 * neutralizing inherited chrome on a nested element and don't count as
 * a self-defined card, so they're exempt from the audit.
 */
function isResetValue(value) {
  const normalized = value.trim().toLowerCase();
  return /^(0|0px|var\(--tasty-layout-0\)|none|transparent)$/.test(normalized);
}

function ruleSetsCardChrome(body) {
  // Count how many of the card-chrome properties are set in the body.
  // We look for "<prop>: ..." declarations. To avoid matching inside
  // values (e.g. `transition: background-color ...`) we anchor on the
  // start-of-declaration boundary: either the start of the body or a
  // semicolon followed by whitespace.
  const declarations = body.split(/;/).map((segment) => segment.trim()).filter(Boolean);

  const setProps = new Set();
  const propValues = new Map();
  let composesFromSurfaceTokens = false;

  for (const decl of declarations) {
    const match = decl.match(/^([a-z-]+)\s*:\s*([\s\S]+)$/i);
    if (!match) continue;

    const prop = match[1].toLowerCase();
    const value = match[2];

    if (CARD_CHROME_PROPERTIES.has(prop)) {
      setProps.add(prop);
      propValues.set(prop, value);
    }

    if (SURFACE_TOKEN_HINTS.some((hint) => value.includes(hint))) {
      composesFromSurfaceTokens = true;
    }
  }

  // The audit considers a rule a "self-defined card" only if it sets
  // REAL (non-reset) values for background AND box-shadow AND at least
  // one of border / border-radius. Those three together are the chrome-
  // defining shape — a rule that only declares padding is just a layout
  // cell, not a card. A rule that resets background/border to none is
  // neutralizing inherited chrome on a nested element.
  const isRealValue = (prop) => setProps.has(prop) && !isResetValue(propValues.get(prop));

  const hasRealBackground = isRealValue('background');
  const hasRealBorderShape = isRealValue('border') || isRealValue('border-radius');
  const hasRealBoxShadow = isRealValue('box-shadow');

  return {
    isSelfDefinedCardChrome:
      hasRealBackground && hasRealBorderShape && hasRealBoxShadow,
    composesFromSurfaceTokens
  };
}

test('every card-like selector in admin.css consumes the Surface System tokens', () => {
  const source = fs.readFileSync(adminCssPath, 'utf8');
  const violations = [];

  for (const { selector, body, lineNumber } of iterateRules(source)) {
    // Split comma-separated selector lists; if ANY individual selector
    // is card-like, the rule body has to comply (because the body
    // applies to all of them).
    const selectorList = selector.split(',').map((s) => s.trim()).filter(Boolean);
    const cardLike = selectorList.some(isCardLikeSelector);
    if (!cardLike) continue;

    // Skip system-defining selectors and compositional cells.
    if (selectorList.every(isSystemDefiningSelector)) continue;
    if (selectorList.every(isCompositionalSelector)) continue;

    const { isSelfDefinedCardChrome, composesFromSurfaceTokens } = ruleSetsCardChrome(body);

    if (isSelfDefinedCardChrome && !composesFromSurfaceTokens) {
      violations.push(
        `admin.css:${lineNumber}: selector "${selector}" defines its own card chrome ` +
        `(background + border/radius + padding + box-shadow) without consuming ` +
        `--tasty-surface-card-* tokens. Repoint to the Surface System.`
      );
    }
  }

  assert.deepEqual(violations, [], violations.join('\n'));
});

test('the canonical .tasty-fonts-surface base rule exists and consumes surface tokens', () => {
  const source = fs.readFileSync(adminCssPath, 'utf8');
  const baseRule = Array.from(iterateRules(source)).find(
    ({ selector }) => selector === '.tasty-fonts-surface'
  );

  assert.ok(baseRule, 'admin.css must define a base .tasty-fonts-surface rule');
  assert.match(
    baseRule.body,
    /var\(--tasty-surface-card(-[\w-]+)?\)/,
    '.tasty-fonts-surface base rule must consume --tasty-surface-card-* tokens'
  );
});

test('the .tasty-fonts-surface.is-interactive and .is-priority modes exist', () => {
  const source = fs.readFileSync(adminCssPath, 'utf8');
  const rules = Array.from(iterateRules(source)).map(({ selector }) => selector);

  assert.ok(
    rules.some((selector) => /\.tasty-fonts-surface\.is-interactive\b/.test(selector)),
    'admin.css must define .tasty-fonts-surface.is-interactive'
  );
  assert.ok(
    rules.some((selector) => /\.tasty-fonts-surface\.is-priority\b/.test(selector)),
    'admin.css must define .tasty-fonts-surface.is-priority'
  );
});

test('.tasty-fonts-banner is defined and consumes the banner tokens', () => {
  const source = fs.readFileSync(adminCssPath, 'utf8');
  const bannerRule = Array.from(iterateRules(source)).find(
    ({ selector }) =>
      /\.tasty-fonts-banner$/.test(selector)
      || /\.tasty-fonts-admin\s+\.tasty-fonts-banner$/.test(selector)
  );

  assert.ok(bannerRule, 'admin.css must define a .tasty-fonts-banner rule');
  assert.match(
    bannerRule.body,
    /var\(--tasty-surface-banner-[\w-]+\)/,
    '.tasty-fonts-banner must consume --tasty-surface-banner-* tokens'
  );
});

test('admin CSS does not use side-stripe accents', () => {
  const source = fs.readFileSync(adminCssPath, 'utf8');
  const violations = [];
  const stripeWidth = /\b(?:[2-9]\d*px|var\(--tasty-layout-[2-9]+px\))\b/i;
  const sideBorderProperty = /^\s*border-(left|right|inline-start|inline-end)(\s|-width)?\s*:/i;
  const anchoredToSide = /(?:left|right|inset-inline-start|inset-inline-end)\s*:\s*(0|var\(--tasty-layout-0\))/i;
  const anchoredToTop = /(?:top|inset-block-start)\s*:\s*(0|var\(--tasty-layout-0\))/i;
  const anchoredToBottom = /(?:bottom|inset-block-end)\s*:\s*(0|var\(--tasty-layout-0\))/i;
  const sideStripeSize = /(?:width|inline-size)\s*:\s*[^;]*(?:[2-9]\d*px|var\(--tasty-layout-[2-9]+px\))/i;

  for (const { selector, body, lineNumber } of iterateRules(source)) {
    const decls = body.split(';');
    for (const decl of decls) {
      if (sideBorderProperty.test(decl) && stripeWidth.test(decl)) {
        // Exempt rotated shapes (like checkmarks) which are constructed via borders.
        if (/transform\s*:[^;]*rotate/i.test(body) && /border-(top|bottom)\s*:/i.test(body)) {
          continue;
        }
        violations.push(`admin.css:${lineNumber}: forbidden side-stripe accent on "${selector}": ${decl.trim()}`);
      }
    }

    if (/::(?:before|after)/.test(selector) && /position\s*:\s*absolute/i.test(body)) {
       if (anchoredToSide.test(body) && anchoredToTop.test(body) && anchoredToBottom.test(body)) {
          if (sideStripeSize.test(body)) {
             violations.push(`admin.css:${lineNumber}: forbidden pseudo-element side-stripe accent on "${selector}"`);
          }
       }
    }
  }

  assert.deepEqual(violations, [], violations.join('\\n'));
});
