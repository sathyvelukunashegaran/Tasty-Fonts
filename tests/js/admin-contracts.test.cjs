const test = require('node:test');
const assert = require('node:assert/strict');

const {
    describeFontType,
    escapeFontFamily,
    getTabNavigationTargetIndex,
    hasStaticFontMetadata,
    hasVariableFontMetadata,
    sanitizeFallback,
    slugify,
} = require('../../assets/js/admin-contracts.js');

test('admin contracts slugify values into stable font slugs', () => {
    assert.equal(slugify('Satoshi Display'), 'satoshi-display');
    assert.equal(slugify('***'), 'font');
});

test('admin contracts sanitize fallback stacks and preserve safe tokens', () => {
    assert.equal(
        sanitizeFallback('  "Segoe UI" ,  serif  <script> '),
        '"Segoe UI", serif script'
    );
    assert.equal(sanitizeFallback('', 'system-ui'), 'system-ui');
});

test('admin contracts escape font family values for CSS usage', () => {
    assert.equal(
        escapeFontFamily('He said "Hello"\\World'),
        'He said \\"Hello\\"\\\\World'
    );
});

test('admin contracts resolve keyboard tab navigation targets for tablists', () => {
    assert.equal(getTabNavigationTargetIndex('ArrowRight', 0, 5), 1);
    assert.equal(getTabNavigationTargetIndex('ArrowLeft', 0, 5), 4);
    assert.equal(getTabNavigationTargetIndex('ArrowDown', 3, 5), 4);
    assert.equal(getTabNavigationTargetIndex('ArrowUp', 3, 5), 2);
    assert.equal(getTabNavigationTargetIndex('Home', 3, 5), 0);
    assert.equal(getTabNavigationTargetIndex('End', 1, 5), 4);
    assert.equal(getTabNavigationTargetIndex('Enter', 1, 5), null);
    assert.equal(getTabNavigationTargetIndex('ArrowRight', 0, 1), null);
});

test('admin contracts detect variable metadata from family and face entries', () => {
    assert.equal(hasVariableFontMetadata({ has_variable_faces: true }), true);
    assert.equal(hasVariableFontMetadata({ variation_axes: { WGHT: { min: 100, max: 900 } } }), true);
    assert.equal(hasVariableFontMetadata({ faces: [{ is_variable: true }] }), true);
    assert.equal(hasVariableFontMetadata({ faces: [{ weight: '400' }] }), false);
    assert.equal(hasStaticFontMetadata({ formats: { static: { available: true }, variable: { available: true } } }), true);
    assert.equal(hasStaticFontMetadata({ faces: [{ is_variable: true, axes: { WGHT: { min: 100, max: 900 } } }] }), false);
});

test('admin contracts describe font type with provider-aware nuance', () => {
    assert.deepEqual(
        describeFontType({ has_variable_faces: true, formats: { static: {}, variable: {} } }, 'library'),
        { type: 'static-variable', hasVariable: true, hasStatic: true, isSourceOnly: false }
    );
    assert.deepEqual(
        describeFontType({ variation_axes: { WGHT: { min: 100, max: 900 } }, formats: { static: {}, variable: { available: false, source_only: true } } }, 'bunny'),
        { type: 'static', hasVariable: false, hasStatic: true, isSourceOnly: false }
    );
    assert.deepEqual(
        describeFontType({ faces: [{ weight: '400' }] }, 'google'),
        { type: 'static', hasVariable: false, hasStatic: true, isSourceOnly: false }
    );
});
