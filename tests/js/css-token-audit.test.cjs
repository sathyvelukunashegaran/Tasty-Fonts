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
