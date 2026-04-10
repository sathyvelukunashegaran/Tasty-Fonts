const test = require('node:test');
const assert = require('node:assert/strict');

const {
    canDisableOutputLayer,
    describeFontType,
    deriveExactOutputQuickMode,
    escapeFontFamily,
    getTabNavigationTargetIndex,
    hasStaticFontMetadata,
    hasVariableFontMetadata,
    normalizeOutputQuickModePreference,
    roleStatesMatch,
    sanitizeFallback,
    sanitizeOutputQuickModePreference,
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

test('admin contracts treat implicit and explicit active role deliveries as the same live state', () => {
    assert.equal(
        roleStatesMatch(
            {
                heading: 'Lora',
                body: 'Inter',
                heading_fallback: 'sans-serif',
                body_fallback: 'sans-serif',
                heading_delivery_id: 'bunny-cdn-static',
            },
            {
                heading: 'Lora',
                body: 'Inter',
                heading_fallback: 'sans-serif',
                body_fallback: 'sans-serif',
                heading_delivery_id: '',
            },
            {
                roleDeliveryCatalog: {
                    Lora: {
                        active_delivery_id: 'bunny-cdn-static',
                        deliveries: [
                            { id: 'bunny-cdn-static' },
                            { id: 'self-hosted-static' },
                        ],
                    },
                    Inter: {
                        active_delivery_id: 'inter-static',
                        deliveries: [{ id: 'inter-static' }],
                    },
                },
            }
        ),
        true
    );
});

test('admin contracts detect pending live changes when a role delivery really differs', () => {
    assert.equal(
        roleStatesMatch(
            {
                heading: 'Lora',
                body: 'Inter',
                heading_fallback: 'sans-serif',
                body_fallback: 'sans-serif',
                heading_delivery_id: 'self-hosted-static',
            },
            {
                heading: 'Lora',
                body: 'Inter',
                heading_fallback: 'sans-serif',
                body_fallback: 'sans-serif',
                heading_delivery_id: 'bunny-cdn-static',
            },
            {
                roleDeliveryCatalog: {
                    Lora: {
                        active_delivery_id: 'bunny-cdn-static',
                        deliveries: [
                            { id: 'bunny-cdn-static' },
                            { id: 'self-hosted-static' },
                        ],
                    },
                    Inter: {
                        active_delivery_id: 'inter-static',
                        deliveries: [{ id: 'inter-static' }],
                    },
                },
            }
        ),
        false
    );
});

test('admin contracts derive exact output quick modes from explicit output shapes', () => {
    assert.equal(
        deriveExactOutputQuickMode({
            minimalEnabled: true,
            classOutputEnabled: false,
            variableOutputEnabled: true,
            roleUsageFontWeightEnabled: false,
            classFlags: [false, false],
            variableFlags: [false, false],
        }),
        'minimal'
    );
    assert.equal(
        deriveExactOutputQuickMode({
            minimalEnabled: false,
            classOutputEnabled: false,
            variableOutputEnabled: true,
            roleUsageFontWeightEnabled: false,
            variableFlags: [true, true, true],
        }),
        'variables'
    );
    assert.equal(
        deriveExactOutputQuickMode({
            minimalEnabled: false,
            classOutputEnabled: true,
            variableOutputEnabled: false,
            roleUsageFontWeightEnabled: false,
            classFlags: [true, true, true],
        }),
        'classes'
    );
    assert.equal(
        deriveExactOutputQuickMode({
            minimalEnabled: false,
            classOutputEnabled: false,
            variableOutputEnabled: true,
            roleUsageFontWeightEnabled: true,
            variableFlags: [true, true, true],
        }),
        'custom'
    );
});

test('admin contracts keep custom sticky and coerce stale non-custom preferences', () => {
    const variableOnlyState = {
        minimalEnabled: false,
        classOutputEnabled: false,
        variableOutputEnabled: true,
        roleUsageFontWeightEnabled: false,
        variableFlags: [true, true, true],
    };

    assert.equal(normalizeOutputQuickModePreference('', variableOnlyState), 'variables');
    assert.equal(normalizeOutputQuickModePreference('variables', variableOnlyState), 'variables');
    assert.equal(normalizeOutputQuickModePreference('custom', variableOnlyState), 'custom');
    assert.equal(
        normalizeOutputQuickModePreference('variables', {
            ...variableOnlyState,
            variableFlags: [true, false, true],
        }),
        'custom'
    );
});

test('admin contracts block disabling the last remaining output layer', () => {
    assert.equal(canDisableOutputLayer('classes', { classOutputEnabled: true, variableOutputEnabled: false }), false);
    assert.equal(canDisableOutputLayer('variables', { classOutputEnabled: false, variableOutputEnabled: true }), false);
    assert.equal(canDisableOutputLayer('variables', { classOutputEnabled: true, variableOutputEnabled: true }), true);
});

test('admin contracts sanitize output quick mode preferences', () => {
    assert.equal(sanitizeOutputQuickModePreference(' Custom '), 'custom');
    assert.equal(sanitizeOutputQuickModePreference('variables'), 'variables');
    assert.equal(sanitizeOutputQuickModePreference('unsupported'), '');
});
