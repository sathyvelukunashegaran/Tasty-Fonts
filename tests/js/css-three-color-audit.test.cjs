/**
 * Three-Color Restraint audit.
 *
 * The plugin uses ONLY Tasty Blue (primary), Warm Amber (secondary),
 * and Soft Cream (tertiary) — plus neutrals. Green, red, and the
 * previous warning-amber #9a6700 are forbidden. This test scans both
 * `tokens.css` and `admin.css` for any color value not on the approved
 * list and fails CI if drift is detected.
 *
 * What's audited:
 *   - Every hex literal (`#rgb`, `#rrggbb`, `#rrggbbaa`).
 *   - Every CSS color name keyword (`red`, `green`, `orange`, etc.).
 *   - Every rgb()/rgba()/hsl()/hsla() literal that doesn't compose
 *     from a `var(--tasty-*-rgb)` triplet (rgba(15, 111, 183, .5) is
 *     forbidden; rgba(var(--tasty-brand-blue-rgb), .5) is fine).
 *
 * What's allowed:
 *   - The three palette hex values + their hover/deep variants.
 *   - Tasty Cyan (a tinted blue used for glows, edge lighting, and
 *     dark-theme syntax keywords — explicitly approved as part of the
 *     primary blue family).
 *   - Ink Navy / Ink Soft (text color anchors).
 *   - Slate + the gray scale (neutral chrome).
 *   - White and `transparent`.
 *   - SVG fill="…" inside data: URIs (those are inline images, not theme).
 */

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const repoRoot = path.resolve(__dirname, '../..');
const cssDir = path.join(repoRoot, 'assets/css');

/**
 * The complete set of approved hex colors. Anything outside this set
 * appearing as a hex literal in tokens.css or admin.css is a violation.
 *
 * Each entry is normalized to lowercase 6- or 8-digit form before
 * comparison, so the set includes all the variants we permit.
 */
const APPROVED_HEX = new Set([
  // Three-color palette (primary / secondary / tertiary).
  '#0f6fb7', // Tasty Blue — primary
  '#e89a3c', // Warm Amber — secondary
  '#faf7f2', // Soft Cream — tertiary

  // Hover/active/deep variants of the three primaries.
  '#0b5f9e', // Tasty Blue Hover
  '#084a7d', // Tasty Blue Active
  '#c97c1f', // Warm Amber Deep
  '#f5f0e6', // Paper Deep

  // Tasty Cyan — explicitly approved as a tinted blue family member,
  // used for glows, edge lighting, and dark-theme syntax keywords.
  '#32c5f4',

  // Neutrals — ink, slate, gray scale, white.
  '#0a2540', // Ink Navy
  '#1c2e44', // Ink Soft
  '#425466', // Workbench Slate (also gray-700)
  '#ffffff', // White (also gray-0)
  '#f6f9fc', // gray-50 / surface-soft
  '#eef3f8', // gray-100 / surface-muted
  '#e7edf4', // gray-150
  '#d9e2ec', // gray-300
  '#d6e0ea', // ctrl-border
  '#9fb1c3'  // ctrl-border-strong
]);

/**
 * CSS color-name keywords that should never appear in our stylesheets.
 * `transparent` and `currentColor` are explicitly NOT in this list
 * because they are valid neutrals.
 */
const FORBIDDEN_COLOR_NAMES = new Set([
  'red', 'green', 'blue', 'yellow', 'orange', 'purple', 'magenta',
  'teal', 'olive', 'lime', 'navy', 'maroon', 'silver', 'aqua', 'fuchsia',
  'pink', 'cyan', 'gold', 'crimson', 'salmon', 'tomato', 'coral',
  'turquoise', 'indigo', 'violet', 'khaki', 'plum', 'chocolate'
]);

/**
 * Normalize a hex string for set lookup. Accepts `#abc`, `#aabbcc`,
 * `#aabbccdd`. Returns lowercase 6- or 8-digit form. Drops the alpha
 * channel when normalizing 8-digit values so the approval check matches
 * its 6-digit twin (`#0f6fb7ff` is the same color as `#0f6fb7`).
 */
function normalizeHex(rawHex) {
  let hex = rawHex.toLowerCase().replace(/^#/, '');

  if (hex.length === 3) {
    hex = hex.split('').map((ch) => ch + ch).join('');
  }

  if (hex.length === 8) {
    hex = hex.slice(0, 6);
  }

  return `#${hex}`;
}

/**
 * Strip data-URI content from a CSS source. data: URIs commonly carry
 * SVG markup with inline `fill="#xxxxxx"` attributes — those colors
 * belong to inline images and are not theme tokens, so we exempt them
 * from the audit. We also strip CSS comment **bodies** while keeping
 * their newlines so commentary about removed colors doesn't trigger
 * false positives AND every reported line number still matches the
 * original file (collapsing multi-line comments would shift indices).
 */
function stripExemptions(source) {
  return source
    .replace(/url\("data:[^"]*"\)/g, 'url("data-uri")')
    .replace(/url\('data:[^']*'\)/g, "url('data-uri')")
    .replace(/\/\*[\s\S]*?\*\//g, (block) => {
      // Replace each character of the comment with a space, but preserve
      // newlines so downstream line counts stay accurate.
      return block.replace(/[^\n]/g, ' ');
    });
}

function readCssSources() {
  return fs
    .readdirSync(cssDir)
    .filter((file) => file === 'admin.css' || file === 'tokens.css')
    .map((file) => ({
      file,
      source: fs.readFileSync(path.join(cssDir, file), 'utf8')
    }));
}

test('CSS uses only the approved Three-Color palette (no green / red / off-palette colors)', () => {
  const violations = [];

  for (const { file, source } of readCssSources()) {
    const cleaned = stripExemptions(source);
    const lines = cleaned.split(/\r?\n/);

    lines.forEach((line, index) => {
      const lineNumber = index + 1;

      for (const match of line.matchAll(/#([0-9a-fA-F]{3,8})\b/g)) {
        const literal = match[0];
        const normalized = normalizeHex(literal);

        if (!APPROVED_HEX.has(normalized)) {
          violations.push(
            `${file}:${lineNumber}: forbidden hex ${literal} (normalized ${normalized}) — not in the Three-Color palette`
          );
        }
      }
    });
  }

  assert.deepEqual(violations, [], violations.join('\n'));
});

test('CSS does not use raw rgb()/rgba()/hsl() literals (must compose from --tasty-*-rgb tokens)', () => {
  const violations = [];

  for (const { file, source } of readCssSources()) {
    const cleaned = stripExemptions(source);
    const lines = cleaned.split(/\r?\n/);

    lines.forEach((line, index) => {
      const lineNumber = index + 1;

      // Find every rgb()/rgba()/hsl()/hsla() invocation. The simple
      // `\([^)]*\)` won't work because the args may contain nested
      // var() calls that have their own parens, so we walk the line
      // and balance parens manually.
      const rePos = /\b(rgb|rgba|hsl|hsla)\(/gi;
      let match;
      while ((match = rePos.exec(line)) !== null) {
        const fn = match[1];
        const start = match.index + match[0].length; // index of first arg char
        let depth = 1;
        let cursor = start;
        while (cursor < line.length && depth > 0) {
          const ch = line[cursor];
          if (ch === '(') {
            depth += 1;
          } else if (ch === ')') {
            depth -= 1;
          }
          cursor += 1;
        }
        if (depth !== 0) {
          // Unbalanced — likely the call spans multiple lines. Treat
          // as composing-from-token (we can't analyze without a full
          // parser) to avoid noisy false positives. Per-line audits
          // already cover the well-formed common case.
          continue;
        }

        const args = line.slice(start, cursor - 1);
        const composesFromVar = /\bvar\(\s*--tasty-/i.test(args);

        if (!composesFromVar) {
          violations.push(
            `${file}:${lineNumber}: raw ${fn}() literal — must compose from a --tasty-*-rgb token`
          );
        }
      }
    });
  }

  assert.deepEqual(violations, [], violations.join('\n'));
});

test('CSS does not use forbidden CSS color name keywords (red / green / orange / etc.)', () => {
  const violations = [];

  for (const { file, source } of readCssSources()) {
    const cleaned = stripExemptions(source);
    const lines = cleaned.split(/\r?\n/);

    lines.forEach((line, index) => {
      const lineNumber = index + 1;

      const declaration = line.match(/^\s*[a-z-]+\s*:\s*([^;]+);/i);
      if (!declaration) {
        return;
      }

      const value = declaration[1];
      const colorTokens = value.split(/[\s,()]+/).filter(Boolean);

      for (const token of colorTokens) {
        if (FORBIDDEN_COLOR_NAMES.has(token.toLowerCase())) {
          violations.push(
            `${file}:${lineNumber}: forbidden color name "${token}" — use a --tasty-* token instead`
          );
        }
      }
    });
  }

  assert.deepEqual(violations, [], violations.join('\n'));
});

test('OKLCH primitive overrides stay scoped to tokens.css and .tasty-fonts-admin', () => {
  const sources = readCssSources();
  const tokens = sources.find(({ file }) => file === 'tokens.css');
  const violations = [];

  assert.ok(tokens, 'tokens.css should be available for OKLCH governance.');

  for (const { file, source } of sources) {
    if (file !== 'tokens.css' && /oklch\(/i.test(stripExemptions(source))) {
      violations.push(`${file}: raw oklch() is only allowed in tokens.css`);
    }
  }

  assert.match(
    tokens.source,
    /@supports\s*\(\s*color:\s*oklch\([^)]*\)\s*\)\s*\{\s*\.tasty-fonts-admin\s*\{/,
    'OKLCH overrides should live under an @supports block scoped to .tasty-fonts-admin.'
  );

  let inOklchSupports = false;
  let supportDepth = 0;
  let inAdminScope = false;
  let adminDepth = 0;

  tokens.source.split(/\r?\n/).forEach((line, index) => {
    const lineNumber = index + 1;
    const startsOklchSupports = /@supports\s*\(\s*color:\s*oklch\(/i.test(line);
    const startsAdminScope = inOklchSupports && /\.tasty-fonts-admin\s*\{/i.test(line);

    if (startsOklchSupports) {
      inOklchSupports = true;
      supportDepth = 0;
    }

    if (startsAdminScope) {
      inAdminScope = true;
      adminDepth = 0;
    }

    if (/oklch\(/i.test(line) && !startsOklchSupports) {
      if (!inOklchSupports || !inAdminScope) {
        violations.push(`tokens.css:${lineNumber}: oklch() declaration is not scoped to .tasty-fonts-admin @supports`);
      }

      if (!/^\s*--tasty-[a-z0-9_-]+\s*:\s*oklch\(/i.test(line)) {
        violations.push(`tokens.css:${lineNumber}: oklch() may only override primitive --tasty-* tokens`);
      }
    }

    const braceDelta = (line.match(/\{/g) || []).length - (line.match(/\}/g) || []).length;

    if (inAdminScope) {
      adminDepth += braceDelta;
      if (adminDepth <= 0 && !startsAdminScope) {
        inAdminScope = false;
      }
    }

    if (inOklchSupports) {
      supportDepth += braceDelta;
      if (supportDepth <= 0 && !startsOklchSupports) {
        inOklchSupports = false;
      }
    }
  });

  assert.deepEqual(violations, [], violations.join('\n'));
});

test('the previous warning-amber #9a6700 is fully removed from active CSS values', () => {
  const violations = [];

  for (const { file, source } of readCssSources()) {
    const cleaned = stripExemptions(source); // strips comments and data: URIs
    if (/#9a6700\b/i.test(cleaned)) {
      violations.push(`${file}: still references the legacy warning-amber #9a6700 (collapsed into Warm Amber #e89a3c)`);
    }
  }

  assert.deepEqual(violations, [], violations.join('\n'));
});

test('the previous success-green #138a4b is fully removed from active CSS values', () => {
  const violations = [];

  for (const { file, source } of readCssSources()) {
    const cleaned = stripExemptions(source);
    if (/#138a4b\b/i.test(cleaned)) {
      violations.push(`${file}: still references the legacy success-green #138a4b (state shown via icon + chrome instead)`);
    }
  }

  assert.deepEqual(violations, [], violations.join('\n'));
});

test('the previous danger-red #c8323a is fully removed from active CSS values', () => {
  const violations = [];

  for (const { file, source } of readCssSources()) {
    const cleaned = stripExemptions(source);
    if (/#c8323a\b/i.test(cleaned)) {
      violations.push(`${file}: still references the legacy danger-red #c8323a (destructive shown via weight + inset shadow + icon instead)`);
    }
  }

  assert.deepEqual(violations, [], violations.join('\n'));
});

test('semantic success and danger tokens resolve through neutral ink, not green or red', () => {
  const tokens = readCssSources().find(({ file }) => file === 'tokens.css');
  assert.ok(tokens, 'tokens.css should be available for semantic status token governance.');

  const cleaned = stripExemptions(tokens.source);

  assert.match(cleaned, /--tasty-success-raw:\s*var\(--tasty-ink\);/);
  assert.match(cleaned, /--tasty-success-rgb:\s*var\(--tasty-ink-rgb\);/);
  assert.match(cleaned, /--tasty-danger-raw:\s*var\(--tasty-ink\);/);
  assert.match(cleaned, /--tasty-danger-rgb:\s*var\(--tasty-ink-rgb\);/);
  assert.match(cleaned, /--tasty-warning-raw:\s*var\(--tasty-warm-amber\);/);
});
