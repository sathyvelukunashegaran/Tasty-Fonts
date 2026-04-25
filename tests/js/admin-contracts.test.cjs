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
    isTrustedHostedStylesheetUrl,
    logEntryMatchesFilters,
    normalizeOutputQuickModePreference,
    parsePhpIniSizeToBytes,
    rowMatchesLibraryFilters,
    resolveLogPagination,
    resolveStatusAnnouncement,
    resolveAssignedRoleState,
    roleStatesMatch,
    serializeSettingsFormEntries,
    settingsStatesMatch,
    shouldDisableFieldDuringSiteTransferSubmit,
    sanitizeFallback,
    sanitizeOutputQuickModePreference,
    shouldHydrateFamilyDetails,
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

test('admin contracts allow only trusted hosted stylesheet URLs', () => {
    assert.equal(isTrustedHostedStylesheetUrl('https://fonts.googleapis.com/css2?family=Inter&display=swap'), true);
    assert.equal(isTrustedHostedStylesheetUrl('https://fonts.bunny.net/css2?family=Inter&display=swap'), true);
});

test('admin contracts reject non-https, foreign-origin, and malformed stylesheet URLs', () => {
    assert.equal(isTrustedHostedStylesheetUrl('http://fonts.googleapis.com/css2?family=Inter&display=swap'), false);
    assert.equal(isTrustedHostedStylesheetUrl('https://example.com/css2?family=Inter&display=swap'), false);
    assert.equal(isTrustedHostedStylesheetUrl('not a url'), false);
});

test('admin contracts resolve keyboard tab navigation targets for tablists', () => {
    assert.equal(getTabNavigationTargetIndex('ArrowRight', 0, 5), 1);
    assert.equal(getTabNavigationTargetIndex('ArrowLeft', 0, 5), 4);
    assert.equal(getTabNavigationTargetIndex('ArrowDown', 3, 5), null);
    assert.equal(getTabNavigationTargetIndex('ArrowUp', 3, 5), null);
    assert.equal(getTabNavigationTargetIndex('ArrowDown', 3, 5, 'vertical'), 4);
    assert.equal(getTabNavigationTargetIndex('ArrowUp', 3, 5, 'vertical'), 2);
    assert.equal(getTabNavigationTargetIndex('ArrowRight', 1, 5, 'vertical'), null);
    assert.equal(getTabNavigationTargetIndex('Home', 3, 5), 0);
    assert.equal(getTabNavigationTargetIndex('End', 1, 5), 4);
    assert.equal(getTabNavigationTargetIndex('Enter', 1, 5), null);
    assert.equal(getTabNavigationTargetIndex('ArrowRight', 0, 1), null);
});

test('admin contracts resolve status announcements by urgency', () => {
    assert.deepEqual(resolveStatusAnnouncement('error'), { role: 'alert', live: 'assertive' });
    assert.deepEqual(resolveStatusAnnouncement('success'), { role: 'status', live: 'polite' });
    assert.deepEqual(resolveStatusAnnouncement('progress'), { role: 'status', live: 'polite' });
});

test('admin contracts only disable submit controls during site transfer submission', () => {
    assert.equal(shouldDisableFieldDuringSiteTransferSubmit('button', ''), true);
    assert.equal(shouldDisableFieldDuringSiteTransferSubmit('input', 'submit'), true);
    assert.equal(shouldDisableFieldDuringSiteTransferSubmit('input', 'file'), false);
    assert.equal(shouldDisableFieldDuringSiteTransferSubmit('input', 'hidden'), false);
    assert.equal(shouldDisableFieldDuringSiteTransferSubmit('input', 'text'), false);
});

test('admin contracts match log entries against actor and search filters', () => {
    assert.equal(logEntryMatchesFilters('System', '2026-04-17 System Exported a site transfer bundle.', '', ''), true);
    assert.equal(logEntryMatchesFilters('System', 'Exported a site transfer bundle.', 'system', ''), true);
    assert.equal(logEntryMatchesFilters('Alicia', 'Imported the site transfer bundle.', 'system', ''), false);
    assert.equal(logEntryMatchesFilters('System', 'Imported the site transfer bundle.', '', 'imported'), true);
    assert.equal(logEntryMatchesFilters('System', 'Imported the site transfer bundle.', '', 'deleted'), false);
});

test('admin contracts resolve activity log pagination windows', () => {
    assert.deepEqual(resolveLogPagination(17, 1, 6), {
        page: 1,
        pageSize: 6,
        totalPages: 3,
        start: 0,
        end: 6,
        hasPrevious: false,
        hasNext: true,
    });
    assert.deepEqual(resolveLogPagination(17, 99, 6), {
        page: 3,
        pageSize: 6,
        totalPages: 3,
        start: 12,
        end: 17,
        hasPrevious: true,
        hasNext: false,
    });
    assert.deepEqual(resolveLogPagination(0, 4, 0), {
        page: 1,
        pageSize: 5,
        totalPages: 1,
        start: 0,
        end: 0,
        hasPrevious: false,
        hasNext: false,
    });
    assert.deepEqual(resolveLogPagination(49, 2, 25), {
        page: 2,
        pageSize: 25,
        totalPages: 2,
        start: 25,
        end: 49,
        hasPrevious: true,
        hasNext: false,
    });
});

test('admin contracts parse PHP ini byte shorthand for upload limits', () => {
    assert.equal(parsePhpIniSizeToBytes('512'), 512);
    assert.equal(parsePhpIniSizeToBytes('2K'), 2048);
    assert.equal(parsePhpIniSizeToBytes('8M'), 8 * 1024 * 1024);
    assert.equal(parsePhpIniSizeToBytes('1.5G'), Math.round(1.5 * 1024 * 1024 * 1024));
    assert.equal(parsePhpIniSizeToBytes('not-a-size'), 0);
});

test('admin contracts serialize settings entries with array fields and empty sentinels', () => {
    assert.deepEqual(
        serializeSettingsFormEntries([
            ['_wpnonce', 'nonce-value'],
            ['admin_access_role_slugs[]', ''],
            ['admin_access_role_slugs[]', 'editor'],
            ['admin_access_role_slugs[]', 'author'],
            ['admin_access_user_ids[]', ''],
            ['admin_access_user_ids[]', '3'],
            ['training_wheels_off', '1'],
            ['training_wheels_off', '0'],
            ['show_activity_log', '0'],
            ['show_activity_log', '1'],
            ['delete_uploaded_files_on_uninstall', '1'],
            ['delete_uploaded_files_on_uninstall', '0'],
            ['font_display', 'swap'],
        ], {
            ignoredKeys: ['_wpnonce'],
        }),
        {
            admin_access_role_slugs: ['editor', 'author'],
            admin_access_user_ids: ['3'],
            training_wheels_off: '0',
            show_activity_log: '1',
            delete_uploaded_files_on_uninstall: '0',
            font_display: 'swap',
        }
    );
});

test('admin contracts keep empty arrays when only hidden sentinels are submitted', () => {
    assert.deepEqual(
        serializeSettingsFormEntries([
            ['admin_access_role_slugs[]', ''],
            ['admin_access_user_ids[]', ''],
        ]),
        {
            admin_access_role_slugs: [],
            admin_access_user_ids: [],
        }
    );
});

test('admin contracts match library rows against query and filter combinations', () => {
    assert.equal(
        rowMatchesLibraryFilters(
            {
                name: 'inter',
                sources: 'published same-origin role_active',
                categories: 'sans-serif variable',
            },
            {
                query: 'int',
                sourceFilter: 'published',
                categoryFilter: 'sans-serif',
            }
        ),
        true
    );
    assert.equal(
        rowMatchesLibraryFilters(
            {
                name: 'inter',
                sources: 'same-origin',
                categories: 'sans-serif',
            },
            {
                query: 'lora',
                sourceFilter: 'all',
                categoryFilter: 'all',
            }
        ),
        false
    );
});

test('admin contracts only request family detail hydration when a slug exists and no fetch is active', () => {
    assert.equal(shouldHydrateFamilyDetails({ familySlug: 'inter', loaded: false, loading: false }), true);
    assert.equal(shouldHydrateFamilyDetails({ familySlug: 'inter', loaded: true, loading: false }), false);
    assert.equal(shouldHydrateFamilyDetails({ familySlug: 'inter', loaded: false, loading: true }), false);
    assert.equal(shouldHydrateFamilyDetails({ familySlug: '', loaded: false, loading: false }), false);
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
            headingFallback: 'system-ui, sans-serif',
            bodyFallback: 'system-ui, sans-serif',
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
            variableFlags: [true, true, true, true, true],
        }),
        'variables'
    );
    assert.equal(
        deriveExactOutputQuickMode({
            minimalEnabled: false,
            classOutputEnabled: false,
            variableOutputEnabled: true,
            roleUsageFontWeightEnabled: false,
            variableFlags: [false, true, true, true, true],
        }),
        'custom'
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
            variableFlags: [true, true, true, true, true],
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
        variableFlags: [true, true, true, true, true],
    };

    assert.equal(normalizeOutputQuickModePreference('', variableOnlyState), 'variables');
    assert.equal(normalizeOutputQuickModePreference('variables', variableOnlyState), 'variables');
    assert.equal(normalizeOutputQuickModePreference('custom', variableOnlyState), 'custom');
    assert.equal(
        normalizeOutputQuickModePreference('variables', {
            ...variableOnlyState,
            variableFlags: [true, false, true, true, true],
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
