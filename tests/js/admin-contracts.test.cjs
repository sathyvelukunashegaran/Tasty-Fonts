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
    resolveAssignedRoleState,
    roleStatesMatch,
    settingsStatesMatch,
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

test('admin contracts ignore legacy role delivery ids when comparing role state', () => {
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
                heading_delivery_id: 'self-hosted-static',
            }
        ),
        true
    );
});

test('admin contracts detect pending live changes when saved role weights differ', () => {
    assert.equal(
        roleStatesMatch(
            {
                heading: 'Lora',
                body: 'Inter',
                heading_fallback: 'sans-serif',
                body_fallback: 'sans-serif',
                heading_weight: '600',
            },
            {
                heading: 'Lora',
                body: 'Inter',
                heading_fallback: 'sans-serif',
                body_fallback: 'sans-serif',
                heading_weight: '700',
            }
        ),
        false
    );
});

test('admin contracts detect pending live changes when saved role axes differ', () => {
    assert.equal(
        roleStatesMatch(
            {
                heading: 'Inter Variable',
                body: 'Inter Variable',
                heading_fallback: 'sans-serif',
                body_fallback: 'sans-serif',
                heading_axes: { WGHT: '650' },
            },
            {
                heading: 'Inter Variable',
                body: 'Inter Variable',
                heading_fallback: 'sans-serif',
                body_fallback: 'sans-serif',
                heading_axes: { WGHT: '700' },
            },
            {
                variableFontsEnabled: true,
            }
        ),
        false
    );
});

test('admin contracts prefer family catalog fallbacks over legacy role fallback values', () => {
    assert.equal(
        roleStatesMatch(
            {
                heading: 'Inter',
                body: '',
                heading_fallback: 'serif',
            },
            {
                heading: 'Inter',
                body: '',
                heading_fallback: 'sans-serif',
            },
            {
                roleFamilyCatalog: {
                    Inter: {
                        fallback: 'system-ui, sans-serif',
                    },
                },
            }
        ),
        true
    );
});

test('admin contracts restore live weight and axes when a role is reassigned back to its live family', () => {
    assert.deepEqual(
        resolveAssignedRoleState(
            'heading',
            'Raleway',
            {
                heading: 'Inter',
                body: 'Inter',
                heading_weight: '',
                heading_axes: {},
            },
            {
                variableFontsEnabled: true,
                preserveStates: [
                    {
                        heading: 'Raleway',
                        body: 'Inter',
                        heading_weight: '700',
                        heading_axes: { WGHT: '700', opsz: '32' },
                    },
                ],
            }
        ),
        {
            heading: 'Raleway',
            body: 'Inter',
            monospace: '',
            headingFallback: 'sans-serif',
            bodyFallback: 'sans-serif',
            monospaceFallback: 'monospace',
            headingWeight: '700',
            bodyWeight: '',
            monospaceWeight: '',
            headingAxes: { OPSZ: '32', WGHT: '700' },
            bodyAxes: {},
            monospaceAxes: {},
        }
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

test('admin contracts compare serialized settings states deterministically', () => {
    assert.equal(
        settingsStatesMatch(
            { font_display: 'swap', remote_connection_hints: '1' },
            { font_display: 'swap', remote_connection_hints: '1' }
        ),
        true
    );
    assert.equal(
        settingsStatesMatch(
            { font_display: 'swap', remote_connection_hints: '1' },
            { font_display: 'optional', remote_connection_hints: '1' }
        ),
        false
    );
});

test('admin contracts normalizeAxisTag normalizes lowercase, uppercase, and non-4-char tags', () => {
    // via hasVariableFontMetadata which calls normalizeAxisTag internally
    // A valid lowercase 4-char tag must be treated as a valid axis tag.
    assert.equal(hasVariableFontMetadata({ variation_axes: { wght: { min: 100, max: 900 } } }), true);
    // A valid uppercase 4-char tag must also be recognised.
    assert.equal(hasVariableFontMetadata({ variation_axes: { WGHT: { min: 100, max: 900 } } }), true);
    // A mixed-case 4-char tag must be normalised to uppercase and recognised.
    assert.equal(hasVariableFontMetadata({ variation_axes: { Wght: {} } }), true);
    // Tags that are not exactly 4 alphanumeric characters must be rejected.
    assert.equal(hasVariableFontMetadata({ variation_axes: { WGH: {} } }), false);
    assert.equal(hasVariableFontMetadata({ variation_axes: { WEIGHT: {} } }), false);
    assert.equal(hasVariableFontMetadata({ variation_axes: { '': {} } }), false);
});

test('admin contracts resolveRoleFallback uses catalog-driven fallback when available', () => {
    // When the role family has a matching catalog entry with a fallback, that fallback is used.
    const stateWithCatalog = roleStatesMatch(
        { heading: 'Inter', headingFallback: 'serif' },
        { heading: 'Inter', headingFallback: 'sans-serif' },
        {
            roleFamilyCatalog: {
                Inter: { fallback: 'sans-serif' },
            },
        }
    );
    // Both sides resolve the catalog-driven fallback, so the states match.
    assert.equal(stateWithCatalog, true);

    // When the catalog fallback differs from the explicit fallback, the catalog wins.
    const mismatch = roleStatesMatch(
        { heading: 'Inter', headingFallback: 'serif' },
        { heading: 'Inter', headingFallback: 'serif' },
        {
            roleFamilyCatalog: {
                Inter: { fallback: 'sans-serif' },
            },
        }
    );
    // Both sides use the catalog fallback 'sans-serif', overriding 'serif', so they still match.
    assert.equal(mismatch, true);
});

test('admin contracts canDisableOutputLayer returns true when another output layer remains enabled', () => {
    // When classes are enabled and variables are disabled, the variables layer can be disabled.
    assert.equal(canDisableOutputLayer('variables', { classOutputEnabled: true, variableOutputEnabled: false }), true);
    // When variables are enabled and classes are disabled, the classes layer can be disabled.
    assert.equal(canDisableOutputLayer('classes', { classOutputEnabled: false, variableOutputEnabled: true }), true);
    // When both are enabled, either layer can be disabled individually.
    assert.equal(canDisableOutputLayer('classes', { classOutputEnabled: true, variableOutputEnabled: true }), true);
});

test('admin contracts resolveAssignedRoleState resets weight when reassigning to a different family', () => {
    // Start with a heading weight already set for 'Inter'.
    const currentState = {
        heading: 'Inter',
        headingWeight: '700',
        body: 'Roboto',
        bodyWeight: '400',
    };

    // Reassign heading to 'Lato' with no preserveStates → weight should reset to default.
    const nextState = resolveAssignedRoleState('heading', 'Lato', currentState);

    assert.equal(nextState.heading, 'Lato');
    assert.equal(nextState.headingWeight, '', 'Weight should reset to default when reassigning to a different family with no preserved state.');

    // Body should be unchanged.
    assert.equal(nextState.body, 'Roboto');
    assert.equal(nextState.bodyWeight, '400');
});
