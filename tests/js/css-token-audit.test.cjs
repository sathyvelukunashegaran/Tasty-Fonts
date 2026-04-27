const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const repoRoot = path.resolve(__dirname, '../..');
const cssDir = path.join(repoRoot, 'assets/css');

const runtimeTastyTokens = new Set([
  '--tasty-preview-base',
  '--tasty-preview-body-stack',
  '--tasty-preview-heading-stack',
  '--tasty-preview-monospace-stack'
]);

function readCssFiles() {
  return fs
    .readdirSync(cssDir)
    .filter((file) => file.endsWith('.css'))
    .map((file) => ({
      file,
      source: fs.readFileSync(path.join(cssDir, file), 'utf8')
    }));
}

function stripTokenFunctions(value) {
  let current = value;
  let next = current;

  do {
    current = next;
    next = current.replace(/var\([^()]*\)/g, 'var-token');
  } while (next !== current);

  return current;
}

function cssBlockForSelector(source, selector) {
  const selectorIndex = source.indexOf(selector);
  assert.notEqual(selectorIndex, -1, `Missing CSS selector: ${selector}`);

  const blockStart = source.indexOf('{', selectorIndex);
  assert.notEqual(blockStart, -1, `Missing CSS block for selector: ${selector}`);

  const blockEnd = source.indexOf('}', blockStart);
  assert.notEqual(blockEnd, -1, `Unclosed CSS block for selector: ${selector}`);

  return source.slice(blockStart + 1, blockEnd);
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function cssBlocksForExactSelector(source, selector) {
  const pattern = new RegExp(`${escapeRegExp(selector)}\\s*\\{([\\s\\S]*?)\\}`, 'g');
  const blocks = Array.from(source.matchAll(pattern), (match) => match[1]);
  assert.notEqual(blocks.length, 0, `Missing exact CSS selector: ${selector}`);

  return blocks;
}

test('admin CSS only references known Tasty design tokens', () => {
  const files = readCssFiles();
  const defined = new Set();
  const references = [];

  for (const { file, source } of files) {
    for (const match of source.matchAll(/(^|[;{\s])(--tasty-[a-z0-9_-]+)\s*:/gim)) {
      defined.add(match[2]);
    }

    for (const match of source.matchAll(/var\(\s*(--[a-z0-9_-]+)/gim)) {
      references.push({ file, token: match[1] });
    }
  }

  const unknown = references
    .filter(({ token }) => token.startsWith('--tasty-'))
    .filter(({ token }) => !defined.has(token))
    .filter(({ token }) => !runtimeTastyTokens.has(token))
    .map(({ file, token }) => `${file}: ${token}`);

  assert.deepEqual([...new Set(unknown)].sort(), []);
});

test('admin CSS keeps raw color values out of component styles', () => {
  const adminCss = fs.readFileSync(path.join(cssDir, 'admin.css'), 'utf8');
  const withoutDataUris = adminCss.replace(/url\("data:[^"]+"\)/g, 'url("data-uri")');
  const violations = [];

  for (const [lineNumber, line] of withoutDataUris.split(/\r?\n/).entries()) {
    if (/#[0-9a-f]{3,8}\b/i.test(line)) {
      violations.push(`${lineNumber + 1}: raw hex color`);
    }

    if (/\b(?:rgb|rgba|hsl|hsla)\(\s*(?!var\()/i.test(line)) {
      violations.push(`${lineNumber + 1}: raw color function`);
    }
  }

  assert.deepEqual(violations, []);
});

test('admin typography tokens enforce a readable 12px minimum', () => {
  const tokensCss = fs.readFileSync(path.join(cssDir, 'tokens.css'), 'utf8');
  const requiredAliases = [
    '--tasty-font-size-3xs',
    '--tasty-font-size-2xs',
    '--tasty-font-size-xs',
    '--tasty-font-size-s'
  ];

  assert.match(tokensCss, /--tasty-font-size-minimum:\s*12px;/);

  for (const token of requiredAliases) {
    const escaped = token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    assert.match(tokensCss, new RegExp(`${escaped}:\\s*var\\(--tasty-font-size-minimum\\);`));
  }
});

test('admin CSS keeps typography declarations on design tokens', () => {
  const adminCss = fs.readFileSync(path.join(cssDir, 'admin.css'), 'utf8');
  const typographyProperties = new Set([
    'font-family',
    'font-size',
    'font-weight',
    'letter-spacing',
    'line-height'
  ]);
  const violations = [];

  for (const [lineNumber, line] of adminCss.split(/\r?\n/).entries()) {
    const declaration = line.match(/^\s*([a-z-]+)\s*:\s*([^;]+);/i);

    if (!declaration || !typographyProperties.has(declaration[1])) {
      continue;
    }

    if (!/\bvar\(/.test(declaration[2])) {
      violations.push(`${lineNumber + 1}: ${declaration[1]} must use a token`);
    }
  }

  assert.deepEqual(violations, []);
});

test('admin CSS keeps numeric declaration values on design tokens', () => {
  const adminCss = fs.readFileSync(path.join(cssDir, 'admin.css'), 'utf8');
  const numericValuePattern =
    /(?<![-_a-zA-Z0-9])-?(?:\d*\.\d+|\d+)(?:px|rem|em|ch|vw|vh|%|deg|fr|ms|s)?(?![-_a-zA-Z0-9])/;
  const violations = [];

  for (const [lineNumber, line] of adminCss.split(/\r?\n/).entries()) {
    const declaration = line.match(/^\s*(--[a-z0-9_-]+|[a-z-]+)\s*:\s*([^;]+);/i);

    if (!declaration) {
      continue;
    }

    const value = stripTokenFunctions(
      declaration[2].replace(/url\("data:[^"]+"\)/g, 'url("data-uri")')
    );

    if (numericValuePattern.test(value)) {
      violations.push(`${lineNumber + 1}: ${declaration[1]} must use tokens for numeric values`);
    }
  }

  assert.deepEqual(violations, []);
});

test('admin compact icon controls use 40px targets and help controls stay visually compact', () => {
  const adminCss = fs.readFileSync(path.join(cssDir, 'admin.css'), 'utf8');
  const tokensCss = fs.readFileSync(path.join(cssDir, 'tokens.css'), 'utf8');
  const targetContracts = [
    { selector: '.tasty-fonts-toast-dismiss', token: '--tasty-icon-button-size' },
    { selector: '.tasty-fonts-toast.is-actionable .tasty-fonts-toast-dismiss', token: '--tasty-icon-button-size' },
    { selector: '.tasty-fonts-select-clear', token: '--tasty-clear-button-size' },
    { selector: '.tasty-fonts-log-toggle.button', token: '--tasty-icon-button-size' },
    { selector: '#tasty-fonts-settings-page .tasty-fonts-settings-row-help', token: '--tasty-help-button-size' },
    { selector: '#tasty-fonts-settings-page .tasty-fonts-settings-copy-with-help > .tasty-fonts-settings-row-help', token: '--tasty-help-button-size' },
    { selector: '#tasty-fonts-diagnostics-page .tasty-fonts-health-help-trigger', token: '--tasty-help-button-size' },
    { selector: '#tasty-fonts-diagnostics-page .tasty-fonts-diagnostic-copy-button.button', token: '--tasty-icon-button-size' },
    { selector: '#tasty-fonts-diagnostics-page .tasty-fonts-admin .tasty-fonts-output-copy-button.button', token: '--tasty-icon-button-size' },
    { selector: '#tasty-fonts-diagnostics-page .tasty-fonts-admin .tasty-fonts-output-download-button.button', token: '--tasty-icon-button-size' }
  ];

  assert.match(tokensCss, /--tasty-hit-target-min:\s*var\(--tasty-layout-40px\);/);
  assert.match(tokensCss, /--tasty-hit-target-comfort:\s*var\(--tasty-layout-44px\);/);
  assert.match(tokensCss, /--tasty-icon-button-size:\s*var\(--tasty-hit-target-min\);/);
  assert.match(tokensCss, /--tasty-icon-chrome-size:\s*var\(--tasty-control-height-compact\);/);
  assert.match(tokensCss, /--tasty-icon-chrome-inset:\s*calc\(\(var\(--tasty-icon-button-size\) - var\(--tasty-icon-chrome-size\)\) \/ var\(--tasty-layout-2\)\);/);
  assert.match(tokensCss, /--tasty-help-button-size:\s*var\(--tasty-pill-height-compact\);/);
  assert.match(tokensCss, /--tasty-clear-button-size:\s*var\(--tasty-hit-target-min\);/);
  assert.match(cssBlockForSelector(adminCss, '.tasty-fonts-log-toggle.button::after'), /var\(--tasty-icon-chrome-inset\)/);
  assert.match(cssBlockForSelector(adminCss, '#tasty-fonts-diagnostics-page .tasty-fonts-activity-toolbar .tasty-fonts-activity-clear-button::after'), /var\(--tasty-icon-chrome-inset\)/);

  for (const { selector, token } of targetContracts) {
    assert.match(
      cssBlockForSelector(adminCss, selector),
      new RegExp(`var\\(${escapeRegExp(token)}\\)`),
      `${selector} should consume ${token} for its effective hit target.`
    );
  }
});

test('admin CSS keeps settings row help and controls vertically centered', () => {
  const adminCss = fs.readFileSync(path.join(cssDir, 'admin.css'), 'utf8');
  const centeredControlSelectors = [
    '#tasty-fonts-settings-page .tasty-fonts-settings-row-help',
    '#tasty-fonts-settings-page .tasty-fonts-settings-board-list > .tasty-fonts-toggle-field--output > .tasty-fonts-toggle-switch',
    '#tasty-fonts-settings-page .tasty-fonts-output-settings-detail-group--integration > .tasty-fonts-toggle-field--integration > .tasty-fonts-toggle-switch',
    '#tasty-fonts-settings-page .tasty-fonts-settings-flat-row-form--channel-control',
    '#tasty-fonts-settings-page .tasty-fonts-settings-flat-row-form--channel-action',
    '#tasty-fonts-settings-page .tasty-fonts-admin-access-mode-toggle .tasty-fonts-toggle-switch'
  ];

  const missing = centeredControlSelectors.filter((selector) => !/align-self:\s*center;/.test(cssBlockForSelector(adminCss, selector)));

  assert.deepEqual(missing, []);
});

test('admin CSS keeps settings group headers in title case', () => {
  const adminCss = fs.readFileSync(path.join(cssDir, 'admin.css'), 'utf8');
  const block = cssBlockForSelector(
    adminCss,
    '#tasty-fonts-settings-page .tasty-fonts-output-settings-submenu > .tasty-fonts-output-settings-submenu-copy h4'
  );

  assert.match(block, /letter-spacing:\s*var\(--tasty-type-letter-spacing-none\);/);
  assert.match(block, /text-transform:\s*none;/);
});

test('admin CSS uses one tokenized gradient for library preview boxes', () => {
  const adminCss = fs.readFileSync(path.join(cssDir, 'admin.css'), 'utf8');
  const previewSelectors = [
    '.tasty-fonts-font-inline-preview',
    '.tasty-fonts-font-specimen',
    '.tasty-fonts-face-preview',
    '.tasty-fonts-detail-card--face .tasty-fonts-face-preview'
  ];

  for (const selector of previewSelectors) {
    const blocks = cssBlocksForExactSelector(adminCss, selector);

    assert.equal(
      blocks.some((block) => /background:\s*var\(--tasty-library-preview-background\);/.test(block)),
      true,
      `${selector} should use the shared preview box gradient token.`
    );
  }

  const monospaceBlock = cssBlockForSelector(
    adminCss,
    '.tasty-fonts-font-inline-preview.is-monospace,\n.tasty-fonts-font-specimen.is-monospace,\n.tasty-fonts-face-preview.is-monospace'
  );

  assert.doesNotMatch(monospaceBlock, /\bbackground\s*:/, 'Monospace previews should inherit the shared preview box gradient.');

  const inlineMonospaceTextBlocks = cssBlocksForExactSelector(adminCss, '.tasty-fonts-font-inline-preview-text.is-monospace');
  assert.equal(
    inlineMonospaceTextBlocks.some((block) => /font-size:\s*var\(--tasty-library-monospace-preview-size\);/.test(block)),
    true,
    'Collapsed monospace previews should use the balanced code preview size token.'
  );
});
