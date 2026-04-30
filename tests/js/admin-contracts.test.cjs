const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const contracts = require('../../assets/js/admin-contracts.js');

const adminRuntimePath = path.join(__dirname, '../../assets/js/admin.js');

function readAdminRuntimeSource() {
    return fs.readFileSync(adminRuntimePath, 'utf8');
}

const {
    buildCustomCssDryRunRequest,
    buildCustomCssFinalImportRequest,
    buildCustomCssImportErrorMessage,
    buildSettingsDirtyState,
    buildVariationSettings,
    canDisableOutputLayer,
    changedSettingsKeys,
    cssAxisTag,
    defaultRoleFallback,
    defaultRoleWeight,
    describeFontType,
    deriveExactOutputQuickMode,
    escapeFontFamily,
    getTabNavigationTargetIndex,
    hasExplicitRoleWeight,
    hasStaticFontMetadata,
    hasVariableFontMetadata,
    isTrustedHostedStylesheetUrl,
    logEntryMatchesFilters,
    normalizeAxisSettings,
    normalizeAxisTag,
    normalizeAxisValue,
    normalizeCustomCssDryRunPlan,
    normalizeSettingsFieldName,
    normalizeOutputQuickModePreference,
    normalizeRoleState,
    normalizeRoleWeightValue,
    parsePhpIniSizeToBytes,
    rowMatchesLibraryFilters,
    resolveLogPagination,
    resolveRoleFallback,
    resolveRoleWeight,
    resolveStatusAnnouncement,
    resolveSitewideDeliveryButtonStates,
    resolveAssignedRoleState,
    renderCustomCssDryRunErrorHtml,
    renderCustomCssDryRunReviewHtml,
    resolveSettingsSaveShellState,
    roleStatesMatch,
    serializeSettingsFormEntries,
    settingsStatesMatch,
    shouldDisableFieldDuringSiteTransferSubmit,
    sanitizeFallback,
    sanitizeOutputQuickModePreference,
    shouldHydrateFamilyDetails,
    slugify,
} = contracts;

const requiredAdminContractExports = [
    'buildCustomCssDryRunRequest',
    'buildCustomCssFinalImportRequest',
    'buildCustomCssImportErrorMessage',
    'buildSettingsDirtyState',
    'buildVariationSettings',
    'canDisableOutputLayer',
    'changedSettingsKeys',
    'cssAxisTag',
    'defaultRoleFallback',
    'defaultRoleWeight',
    'deriveExactOutputQuickMode',
    'describeFontType',
    'escapeFontFamily',
    'getTabNavigationTargetIndex',
    'hasExplicitRoleWeight',
    'hasStaticFontMetadata',
    'hasVariableFontMetadata',
    'isTrustedHostedStylesheetUrl',
    'logEntryMatchesFilters',
    'normalizeAxisSettings',
    'normalizeAxisTag',
    'normalizeAxisValue',
    'normalizeCustomCssDryRunPlan',
    'normalizeOutputQuickModePreference',
    'normalizeRoleState',
    'normalizeRoleWeightValue',
    'normalizeSettingsFieldName',
    'parsePhpIniSizeToBytes',
    'renderCustomCssDryRunErrorHtml',
    'renderCustomCssDryRunReviewHtml',
    'resolveAssignedRoleState',
    'resolveSettingsSaveShellState',
    'resolveLogPagination',
    'resolveRoleFallback',
    'resolveRoleWeight',
    'resolveStatusAnnouncement',
    'resolveSitewideDeliveryButtonStates',
    'roleStatesMatch',
    'rowMatchesLibraryFilters',
    'sanitizeFallback',
    'sanitizeOutputQuickModePreference',
    'serializeSettingsFormEntries',
    'settingsStatesMatch',
    'shouldDisableFieldDuringSiteTransferSubmit',
    'shouldHydrateFamilyDetails',
    'slugify',
];

test('admin contracts expose the required flat CommonJS and browser surface', () => {
    assert.equal(globalThis.TastyFontsAdminContracts, contracts);

    requiredAdminContractExports.forEach((exportName) => {
        assert.equal(typeof contracts[exportName], 'function', `${exportName} should be exported as a function`);
    });
});

test('settings help hydration uses the canonical compact help trigger class', () => {
    const source = readAdminRuntimeSource();

    assert.match(
        source,
        /button\.className = '([^']*\btasty-fonts-help-trigger\b[^']*\btasty-fonts-settings-row-help\b[^']*)';/,
        'Settings help buttons should opt into the shared compact help-trigger chrome.'
    );
});

test('admin contracts strictly normalize axis tags, values, and variation settings', () => {
    assert.equal(normalizeAxisTag(' wght '), 'WGHT');
    assert.equal(normalizeAxisTag('WEIGHT'), '');
    assert.equal(cssAxisTag('WGHT'), 'wght');
    assert.equal(cssAxisTag('XPRN'), 'XPRN');
    assert.equal(normalizeAxisValue(' -12.5 '), '-12.5');
    assert.equal(normalizeAxisValue('12px'), '');

    const normalized = normalizeAxisSettings({
        wght: ' 650 ',
        WdTh: '100.5',
        slnt: '-8',
        ital: 0,
        XPRN: '0.25',
        opsz: '12px',
        WGH: '2',
    });

    assert.deepEqual(normalized, {
        ITAL: '0',
        SLNT: '-8',
        WDTH: '100.5',
        WGHT: '650',
        XPRN: '0.25',
    });
    assert.equal(buildVariationSettings(normalized), '"ital" 0, "slnt" -8, "wdth" 100.5, "wght" 650, "XPRN" 0.25');
    assert.equal(buildVariationSettings({ opsz: 'fluid' }), 'normal');
});

test('admin contracts normalize role weights and resolve explicit/default weights', () => {
    assert.equal(normalizeRoleWeightValue(' normal '), '400');
    assert.equal(normalizeRoleWeightValue('bold'), '700');
    assert.equal(normalizeRoleWeightValue(' 650 '), '650');
    assert.equal(normalizeRoleWeightValue('1000'), '1000');
    assert.equal(normalizeRoleWeightValue('0400'), '400');
    assert.equal(normalizeRoleWeightValue('0'), '');
    assert.equal(normalizeRoleWeightValue('1001'), '');
    assert.equal(normalizeRoleWeightValue('650.5'), '');
    assert.equal(defaultRoleWeight('heading'), '700');
    assert.equal(defaultRoleWeight('body'), '400');
    assert.equal(resolveRoleWeight('heading', {}), '700');
    assert.equal(resolveRoleWeight('body', { bodyWeight: 'bold' }), '700');
    assert.equal(resolveRoleWeight('body', { bodyAxes: { wght: '512' }, bodyWeight: '700' }), '512');
    assert.equal(hasExplicitRoleWeight('heading', {}), false);
    assert.equal(hasExplicitRoleWeight('heading', { headingWeight: 'normal' }), true);
    assert.equal(hasExplicitRoleWeight('heading', { headingAxes: { wght: '0' } }), true);
});

test('admin contracts normalize role state using monospace, variable, and family catalog options', () => {
    const options = {
        monospaceRoleEnabled: true,
        variableFontsEnabled: true,
        roleFamilyCatalog: {
            Inter: { fallback: 'Inter fallback, sans-serif' },
            Mono: { fallback: 'Mono fallback, monospace' },
        },
    };

    assert.deepEqual(
        normalizeRoleState({
            heading: ' Inter ',
            body: ' Body Sans ',
            monospace: ' Mono ',
            headingWeight: 'bold',
            body_weight: 'normal',
            monospaceWeight: 'invalid',
            headingAxes: { wght: '650', opsz: 'bad' },
            body_axes: { wdth: '98.5' },
            monospaceAxes: { slnt: '-4' },
        }, options),
        {
            heading: 'Inter',
            body: 'Body Sans',
            monospace: 'Mono',
            headingFallback: 'Inter fallback, sans-serif',
            bodyFallback: 'system-ui, sans-serif',
            monospaceFallback: 'Mono fallback, monospace',
            headingWeight: '700',
            bodyWeight: '400',
            monospaceWeight: '',
            headingAxes: { WGHT: '650' },
            bodyAxes: { WDTH: '98.5' },
            monospaceAxes: { SLNT: '-4' },
        }
    );

    assert.deepEqual(
        normalizeRoleState({
            monospace: 'Mono',
            monospaceFallback: 'Custom Mono, monospace',
            headingAxes: { wght: '650' },
            monospaceAxes: { wght: '450' },
        }, {
            ...options,
            monospaceRoleEnabled: false,
            variableFontsEnabled: false,
        }),
        {
            heading: '',
            body: '',
            monospace: '',
            headingFallback: 'system-ui, sans-serif',
            bodyFallback: 'system-ui, sans-serif',
            monospaceFallback: 'Custom Mono, monospace',
            headingWeight: '',
            bodyWeight: '',
            monospaceWeight: '',
            headingAxes: {},
            bodyAxes: {},
            monospaceAxes: {},
        }
    );

    assert.equal(resolveRoleFallback('heading', { heading: 'Inter' }, options), 'Inter fallback, sans-serif');
    assert.equal(resolveRoleFallback('heading', { heading: 'Inter', headingFallback: 'serif' }, options), 'Inter fallback, sans-serif');
    assert.equal(resolveRoleFallback('heading', { heading: '', headingFallback: 'serif' }, options), 'serif');
    assert.equal(defaultRoleFallback('monospace'), 'monospace');
});

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

test('admin contracts build custom CSS dry-run requests and normalize review plans', () => {
    const payload = {
        status: 'dry_run',
        message: 'Found 1 supported font face.',
        snapshot_token: 'snapshot-token-123',
        snapshot_expires_at: 1770000900,
        snapshot_ttl_seconds: 900,
        plan: {
            source: { type: 'custom_css_url', url: 'https://assets.example.com/fonts.css', host: 'assets.example.com' },
            counts: { families: 1, faces: 1, valid_faces: 1 },
            families: [{
                family: 'Foundry Sans',
                slug: 'foundry-sans',
                faces: [{
                    id: 'face-abc',
                    weight: '400',
                    style: 'normal',
                    format: 'woff2',
                    url: 'https://cdn.example.com/foundry.woff2',
                    host: 'cdn.example.com',
                    unicode_range: 'U+000-5FF',
                    status: 'valid',
                    selected: true,
                }],
            }],
        },
    };

    assert.deepEqual(buildCustomCssDryRunRequest('  https://assets.example.com/fonts.css  '), {
        url: 'https://assets.example.com/fonts.css',
    });
    assert.deepEqual(normalizeCustomCssDryRunPlan(payload), {
        status: 'dry_run',
        message: 'Found 1 supported font face.',
        source: { type: 'custom_css_url', url: 'https://assets.example.com/fonts.css', host: 'assets.example.com' },
        families: [{
            family: 'Foundry Sans',
            slug: 'foundry-sans',
            fallback: 'sans-serif',
            faces: [{
                id: 'face-abc',
                weight: '400',
                style: 'normal',
                format: 'woff2',
                url: 'https://cdn.example.com/foundry.woff2',
                host: 'cdn.example.com',
                unicodeRange: 'U+000-5FF',
                status: 'valid',
                selected: true,
                warnings: [],
                duplicateMatches: [],
                duplicateSummary: { hasMatches: false, hasReplaceableCustomMatches: false, defaultAction: 'import', selfHostedMatches: 0, remoteMatches: 0 },
                validation: {
                    method: '',
                    contentType: '',
                    contentLength: 0,
                    notes: [],
                },
            }],
        }],
        counts: { families: 1, faces: 1, validFaces: 1, warningFaces: 0, invalidFaces: 0, unsupportedFaces: 0, duplicateFaces: 0, replaceableDuplicateFaces: 0 },
        warnings: [],
        snapshotToken: 'snapshot-token-123',
        snapshotExpiresAt: 1770000900,
        snapshotTtlSeconds: 900,
    });
});

test('admin contracts build final custom CSS import requests without face metadata', () => {
    assert.deepEqual(
        buildCustomCssFinalImportRequest(' token-123 ', ['face-a', 'face-b', 'face-a', ''], {
            deliveryMode: 'remote',
            familyFallbacks: {
                'Foundry Sans': ' serif ',
            },
            duplicateHandling: 'replace_custom',
            activate: true,
            publish: false,
            faces: [{ url: 'https://evil.example/font.woff2' }],
        }),
        {
            snapshot_token: 'token-123',
            selected_face_ids: ['face-a', 'face-b'],
            delivery_mode: 'remote',
            family_fallbacks: {
                'foundry-sans': 'serif',
            },
            duplicate_handling: 'replace_custom',
            activate: true,
            publish: false,
        }
    );
});

test('admin contracts include failed face details in custom CSS import errors when present', () => {
    const message = buildCustomCssImportErrorMessage({
        message: 'No custom font faces could be imported.',
        data: {
            status: 422,
            failed_faces: [{
                family: 'Roboto',
                weight: '400',
                style: 'normal',
                message: 'The font URL returned HTTP 404.',
            }, {
                family: 'Roboto',
                weight: '700',
                style: 'italic',
                message: 'The downloaded file did not pass WOFF2 signature validation.',
            }],
        },
    }, 'The custom CSS import failed.');

    assert.match(message, /No custom font faces could be imported\./);
    assert.match(message, /Failed faces:/);
    assert.match(message, /Roboto \(400 normal\): The font URL returned HTTP 404\./);
    assert.match(message, /Roboto \(700 italic\): The downloaded file did not pass WOFF2 signature validation\./);
});

test('admin contracts fall back to default custom CSS import error message when failed faces are missing', () => {
    assert.equal(
        buildCustomCssImportErrorMessage({}, 'The custom CSS import failed.'),
        'The custom CSS import failed.'
    );
});

test('admin contracts render custom CSS dry-run errors as accessible inline UI', () => {
    const html = renderCustomCssDryRunErrorHtml('Blocked <internal> URL');

    assert.match(html, /role="alert"/);
    assert.match(html, /Blocked &lt;internal&gt; URL/);
    assert.doesNotMatch(html, /<internal>/);
});

test('admin contracts render custom CSS dry-run empty state with a restrained next step', () => {
    const html = renderCustomCssDryRunReviewHtml({
        plan: {
            source: { host: 'assets.example.com' },
            families: [],
        },
    });

    assert.match(html, /No importable WOFF or WOFF2 faces were found\./);
    assert.match(html, /Check that the stylesheet includes @font-face rules with WOFF2 or WOFF sources\./);
});

test('admin contracts render custom CSS dry-run unsupported faces as disabled rows', () => {
    const html = renderCustomCssDryRunReviewHtml({
        plan: {
            source: { host: 'assets.example.com' },
            families: [{
                family: 'Legacy Display',
                faces: [{
                    id: 'face-ttf',
                    weight: '400',
                    style: 'normal',
                    format: 'ttf',
                    url: 'https://assets.example.com/legacy.ttf',
                    host: 'assets.example.com',
                    status: 'unsupported',
                }],
            }],
        },
    });

    assert.match(html, /400 normal \(TTF\)/);
    assert.match(html, /Unsupported format/);
    assert.match(html, /type="checkbox" value="face-ttf" disabled/);
    assert.doesNotMatch(html, /type="checkbox" value="face-ttf" checked disabled/);
});

test('admin contracts pluralize custom CSS dry-run review summaries', () => {
    const singularHtml = renderCustomCssDryRunReviewHtml({
        plan: {
            families: [{
                family: 'Single Sans',
                faces: [{
                    id: 'face-1',
                    weight: '400',
                    style: 'normal',
                    format: 'woff2',
                    url: 'https://cdn.example.com/single.woff2',
                    host: 'cdn.example.com',
                }],
            }],
        },
    });
    const pluralHtml = renderCustomCssDryRunReviewHtml({
        plan: {
            counts: { families: 1, faces: 7 },
            families: [{
                family: 'Inter',
                faces: Array.from({ length: 7 }, (_, index) => ({
                    id: `face-${index}`,
                    weight: '400',
                    style: 'normal',
                    format: 'woff2',
                    url: `https://cdn.example.com/inter-${index}.woff2`,
                    host: 'cdn.example.com',
                })),
            }],
        },
    });

    assert.match(singularHtml, />1 family, 1 face</);
    assert.match(pluralHtml, />1 family, 7 faces</);
    assert.doesNotMatch(pluralHtml, />1 family, 7 face</);
});

test('admin contracts render custom CSS dry-run review HTML safely', () => {
    const html = renderCustomCssDryRunReviewHtml({
        snapshot_token: 'snapshot-token-123',
        plan: {
            source: { host: 'assets.example.com' },
            families: [{
                family: 'Foundry <Sans>',
                slug: 'foundry-sans',
                fallback: 'system-ui, sans-serif',
                faces: [{
                    id: 'face-1',
                    weight: '400',
                    style: 'normal',
                    format: 'woff2',
                    url: 'https://cdn.example.com/foundry.woff2',
                    host: 'cdn.example.com',
                }],
            }],
        },
    });

    assert.match(html, /data-custom-css-final-form/);
    assert.match(html, /Delivery mode/);
    assert.match(html, /name="custom_css_delivery_mode" value="self_hosted" checked/);
    assert.match(html, /name="custom_css_delivery_mode" value="remote"/);
    assert.match(html, /data-custom-css-family-fallback data-family-slug="foundry-sans" value="system-ui, sans-serif" aria-label="Fallback stack for Foundry &lt;Sans&gt;"/);
    assert.match(html, /data-custom-css-import-submit/);
    assert.match(html, /Import Selected Faces/);
    assert.match(html, /data-custom-css-import-status aria-live="polite"/);
    assert.match(html, /Foundry &lt;Sans&gt;/);
    assert.match(html, /400 normal \(WOFF2\)/);
    assert.match(html, /cdn\.example\.com/);
    assert.match(html, /type="checkbox" value="face-1" checked aria-label="Foundry &lt;Sans&gt;, 400 normal, WOFF2, from cdn\.example\.com, Validated"/);
    assert.doesNotMatch(html, /type="checkbox" value="face-1" checked disabled/);
});

test('admin contracts render custom CSS validation states and details', () => {
    const html = renderCustomCssDryRunReviewHtml({
        plan: {
            source: { host: 'assets.example.com' },
            warnings: ['Remote serving uses third-party font URLs. Confirm licensing, visitor privacy, and source availability before choosing remote serving.'],
            families: [{
                family: 'Review Sans',
                faces: [{
                    id: 'face-warning',
                    weight: '400',
                    style: 'normal',
                    format: 'woff2',
                    url: 'https://cdn.example.com/review.woff2?token=temporary',
                    host: 'cdn.example.com',
                    status: 'warning',
                    selected: true,
                    warnings: ['Self-hosted imports are not blocked by browser CORS.'],
                    validation: {
                        method: 'capped GET fallback',
                        content_type: 'font/woff2',
                        content_length: 2048,
                        notes: ['WOFF2 signature matched.'],
                    },
                }, {
                    id: 'face-invalid',
                    weight: '700',
                    style: 'normal',
                    format: 'woff2',
                    url: 'https://cdn.example.com/bad.woff2',
                    host: 'cdn.example.com',
                    status: 'invalid',
                    selected: false,
                    validation: {
                        method: 'HEAD',
                        content_type: 'text/html',
                        notes: ['The font URL returned HTTP 404.'],
                    },
                }],
            }],
        },
    });

    assert.match(html, /data-state="warning"/);
    assert.match(html, /Warning/);
    assert.match(html, /type="checkbox" value="face-warning" checked aria-label="Review Sans, 400 normal, WOFF2, from cdn\.example\.com, Warning"/);
    assert.match(html, /type="checkbox" value="face-invalid" disabled/);
    assert.match(html, /Resolved URL/);
    assert.match(html, /https:\/\/cdn\.example\.com\/review\.woff2\?token=temporary/);
    assert.match(html, /Check: capped GET fallback/);
    assert.match(html, /Size: 2 KB/);
    assert.match(html, /Self-hosted imports are not blocked by browser CORS/);
    assert.match(html, /licensing, visitor privacy, and source availability/);
});

test('admin contracts surface duplicate matches and advanced custom replacement controls', () => {
    const payload = {
        plan: {
            counts: { families: 1, faces: 1, duplicate_faces: 1, replaceable_duplicate_faces: 1 },
            source: { host: 'assets.example.com' },
            families: [{
                family: 'Review Sans',
                faces: [{
                    id: 'face-duplicate',
                    weight: '400',
                    style: 'normal',
                    format: 'woff2',
                    url: 'https://cdn.example.com/review.woff2',
                    host: 'cdn.example.com',
                    status: 'valid',
                    duplicate_summary: {
                        has_matches: true,
                        has_replaceable_custom_matches: true,
                        default_action: 'skip',
                        self_hosted_matches: 1,
                        remote_matches: 1,
                    },
                    duplicate_matches: [{
                        family: 'Review Sans',
                        family_slug: 'review-sans',
                        delivery_id: 'custom-self-hosted-old',
                        delivery_label: 'Custom CSS import',
                        provider: 'custom',
                        delivery_type: 'self_hosted',
                        format: 'woff2',
                        replaceable: true,
                        protected: false,
                    }, {
                        family: 'Review Sans',
                        family_slug: 'review-sans',
                        delivery_id: 'google-css2',
                        delivery_label: 'Google Fonts',
                        provider: 'google',
                        delivery_type: 'remote',
                        format: 'woff2',
                        replaceable: false,
                        protected: true,
                    }],
                }],
            }],
        },
    };
    const plan = normalizeCustomCssDryRunPlan(payload);
    const html = renderCustomCssDryRunReviewHtml(payload);

    assert.equal(plan.counts.duplicateFaces, 1);
    assert.equal(plan.counts.replaceableDuplicateFaces, 1);
    assert.deepEqual(plan.families[0].faces[0].duplicateSummary, {
        hasMatches: true,
        hasReplaceableCustomMatches: true,
        defaultAction: 'skip',
        selfHostedMatches: 1,
        remoteMatches: 1,
    });
    assert.equal(plan.families[0].faces[0].duplicateMatches[0].replaceable, true);
    assert.equal(plan.families[0].faces[0].duplicateMatches[1].protected, true);
    assert.match(html, /Duplicate handling/);
    assert.match(html, /name="custom_css_duplicate_handling" value="skip" checked/);
    assert.match(html, /name="custom_css_duplicate_handling" value="replace_custom"/);
    assert.match(html, /<span class="tasty-fonts-url-face-status" data-state="duplicate">Duplicate<\/span>/);
    assert.doesNotMatch(html, /Duplicate: skips by default/);
    assert.match(html, /Duplicate matches/);
    assert.match(html, /replaceable custom CSS/);
    assert.match(html, /protected provider\/local profile/);
    assert.match(html, /Google, Bunny, Adobe, and local upload profiles are protected/);
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
});

test('admin contracts resolve Sitewide delivery action button states for enabled/off/saving matrix', () => {
    assert.deepEqual(resolveSitewideDeliveryButtonStates({ applyEverywhere: true, isSaving: false }), {
        enableDisabled: true,
        disableDisabled: false,
    });
    assert.deepEqual(resolveSitewideDeliveryButtonStates({ applyEverywhere: false, isSaving: false }), {
        enableDisabled: false,
        disableDisabled: true,
    });
    assert.deepEqual(resolveSitewideDeliveryButtonStates({ applyEverywhere: true, isSaving: true }), {
        enableDisabled: true,
        disableDisabled: true,
    });
    assert.deepEqual(resolveSitewideDeliveryButtonStates({ applyEverywhere: false, isSaving: true }), {
        enableDisabled: true,
        disableDisabled: true,
    });
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
            ['training_wheels_off', '0'],
            ['training_wheels_off', '1'],
            ['show_activity_log', '0'],
            ['show_activity_log', '1'],
            ['delete_uploaded_files_on_uninstall', '1'],
            ['delete_uploaded_files_on_uninstall', '0'],
            ['google_font_imports_enabled', '0'],
            ['google_font_imports_enabled', '1'],
            ['bunny_font_imports_enabled', '0'],
            ['bunny_font_imports_enabled', '1'],
            ['local_font_uploads_enabled', '0'],
            ['local_font_uploads_enabled', '1'],
            ['adobe_font_imports_enabled', '0'],
            ['custom_css_url_imports_enabled', '0'],
            ['custom_css_url_imports_enabled', '1'],
            ['font_display', 'swap'],
        ], {
            ignoredKeys: ['_wpnonce'],
        }),
        {
            admin_access_role_slugs: ['editor', 'author'],
            admin_access_user_ids: ['3'],
            training_wheels_off: '1',
            show_activity_log: '1',
            delete_uploaded_files_on_uninstall: '0',
            google_font_imports_enabled: '1',
            bunny_font_imports_enabled: '1',
            local_font_uploads_enabled: '1',
            adobe_font_imports_enabled: '0',
            custom_css_url_imports_enabled: '1',
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

test('admin contracts normalize settings field names for baseline restoration', () => {
    assert.equal(normalizeSettingsFieldName('admin_access_role_slugs[]'), 'admin_access_role_slugs');
    assert.equal(normalizeSettingsFieldName(' output_quick_mode_preference '), 'output_quick_mode_preference');
    assert.equal(normalizeSettingsFieldName(''), '');
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
                name: 'foundry sans',
                sources: 'published same-origin url-import',
                categories: 'sans-serif',
            },
            {
                query: '',
                sourceFilter: 'url-import',
                categoryFilter: 'all',
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

test('admin contracts prefer family catalog fallbacks for selected families', () => {
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

test('admin contracts derive settings dirty keys and changed row indicators', () => {
    const initial = {
        font_display: 'swap',
        remote_connection_hints: '1',
        training_wheels_off: '0',
    };
    const current = {
        font_display: 'optional',
        remote_connection_hints: '1',
        training_wheels_off: '1',
    };

    assert.deepEqual(changedSettingsKeys(initial, current), ['font_display', 'training_wheels_off']);
    assert.deepEqual(
        buildSettingsDirtyState(initial, current, {
            delivery: ['css_delivery_mode'],
            display: ['font_display'],
            help: ['training_wheels_off'],
        }),
        {
            isDirty: true,
            changedKeys: ['font_display', 'training_wheels_off'],
            changedRows: ['display', 'help'],
        }
    );
});

test('admin contracts resolve settings save shell state for dirty, clear, and transfer modes', () => {
    assert.deepEqual(resolveSettingsSaveShellState({ hasUnsavedSettings: false }), {
        visible: true,
        showClear: true,
        clearDisabled: true,
        saveDisabled: true,
        saveTone: 'default',
    });
    assert.deepEqual(resolveSettingsSaveShellState({ hasUnsavedSettings: true }), {
        visible: true,
        showClear: true,
        clearDisabled: false,
        saveDisabled: false,
        saveTone: 'default',
    });
    assert.deepEqual(resolveSettingsSaveShellState({ isTransferTab: true, hasStagedBundle: false }), {
        visible: true,
        showClear: false,
        clearDisabled: true,
        saveDisabled: true,
        saveTone: 'danger',
    });
    assert.equal(resolveSettingsSaveShellState({ isTransferTab: true, hasStagedBundle: true }).saveDisabled, false);
    assert.equal(resolveSettingsSaveShellState({ isTransferTab: true, hasStagedBundle: true, isSubmitting: true }).saveDisabled, true);
});

test('admin runtime keeps settings dirty state wired to changed row data attributes', () => {
    const source = readAdminRuntimeSource();

    for (const hook of [
        '[data-settings-form]',
        '#tasty-fonts-settings-page',
        '[data-settings-save-shell]',
        '[data-settings-save-button]',
        '[data-settings-clear-button]',
        'has-unsaved-changes',
        'has-pending-toggle-change',
        'data-settings-row-changed',
        'data-settings-toggle-pending',
        'data-settings-pending-badge',
        'aria-describedby',
        'tasty-fonts-settings-pending-badge-',
        'settingsPendingSave',
        'data-has-unsaved-changes',
        'data-settings-group-changed',
    ]) {
        assert.equal(source.includes(hook), true, `${hook} should stay wired in admin.js`);
    }
});

test('admin runtime keeps disclosure toggles accessible and URL-trackable', () => {
    const source = readAdminRuntimeSource();

    for (const hook of [
        '[data-disclosure-toggle]',
        'tasty-fonts-role-preview-panel',
        'tasty-fonts-role-snippets-panel',
        'tasty-fonts-add-font-panel',
        'tasty-fonts-google-access-panel',
        'tasty-fonts-adobe-project-panel',
        'aria-expanded',
        'aria-labelledby',
        'data-expanded-label',
        'data-collapsed-label',
    ]) {
        assert.equal(source.includes(hook), true, `${hook} should stay wired in admin.js`);
    }

    assert.match(source, /\.hidden\s*=/, 'Disclosure targets should still be hidden and shown through the hidden property.');
});

test('admin runtime keeps copy feedback on shared data-copy attributes', () => {
    const source = readAdminRuntimeSource();

    for (const hook of [
        'navigator.clipboard.writeText',
        'is-copied',
        'Copied',
        'data-copy-text',
        'data-copy-success',
        'data-copy-static-label',
    ]) {
        assert.equal(source.includes(hook), true, `${hook} should stay wired in admin.js`);
    }
});

test('admin runtime keeps changed Slice 9 renderer data hooks in sync', () => {
    const source = readAdminRuntimeSource();

    for (const hook of [
        '[data-role-deployment-pill]',
        '[data-role-deployment-badge]',
        '[data-role-deployment-announcement]',
        'roleDeploymentBadge',
        'deployment.badge',
        'deployment.copy',
    ]) {
        assert.equal(source.includes(hook), true, `${hook} should stay wired in admin.js`);
    }
});

test('admin runtime keeps Sitewide delivery action sync hooks wired for draft-save reconciliation', () => {
    const source = readAdminRuntimeSource();

    for (const hook of [
        '[data-role-sitewide-enable]',
        '[data-role-sitewide-disable]',
        'syncSitewideDeliveryActionButtonStates',
        'apply_everywhere',
        'config.applyEverywhere',
    ]) {
        assert.equal(source.includes(hook), true, `${hook} should stay wired in admin.js`);
    }
});

test('admin runtime keeps family row quick action hooks wired', () => {
    const source = readAdminRuntimeSource();

    for (const hook of [
        '[data-family-quick-publish]',
        '[data-family-quick-delivery]',
        'handleQuickFamilyPublishClick',
        'handleQuickFamilyDeliveryClick',
        'persistFamilyPublishState',
        'persistFamilyDelivery',
        'saveFamilyPublishState',
        'saveFamilyDelivery',
    ]) {
        assert.equal(source.includes(hook), true, `${hook} should stay wired in admin.js`);
    }
});

test('admin runtime keeps library refresh orchestration hooks wired for import/upload success paths', () => {
    const source = readAdminRuntimeSource();

    for (const hook of [
        "getRoutePath('libraryRefresh', 'library/refresh')",
        'refreshLibraryViewAfterMutation',
        '[data-font-library-list]',
        'family_names',
        'expanded_family_slugs',
        'refresh_all',
        'payload.rows',
        'missing_family_slugs',
        'hideLibraryEmptyStateAfterRowInsert',
        "['family', 'family_name', 'name', 'slug', 'family_slug']",
        'role_family_catalog',
        'available_family_options',
        'resolveRoleFamilyOptionsForSelect',
        'Array.isArray(optionsSource)',
        'preview_bootstrap',
        'appliedRoles',
        'applied_roles',
        'baselineSource',
        'baseline_source',
        'baselineLabel',
        'baseline_label',
        'apply_everywhere',
        'rerenderHostedSearchLibraryBadges',
    ]) {
        assert.equal(source.includes(hook), true, `${hook} should stay wired in admin.js`);
    }
});

test('admin runtime keeps Adobe project form REST hooks wired for in-place library refresh', () => {
    const source = readAdminRuntimeSource();

    for (const hook of [
        '[data-adobe-project-form]',
        'data-adobe-project-action',
        '[data-adobe-project-feedback]',
        "getRoutePath('saveAdobeProject', 'adobe/project')",
        'refreshAllWhenUntargeted: true',
        'bindAdobeProjectControls',
    ]) {
        assert.equal(source.includes(hook), true, `${hook} should stay wired in admin.js`);
    }
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

test('admin contracts resolveRoleFallback scopes explicit fallback to fallback-only roles', () => {
    const stateWithCatalog = roleStatesMatch(
        { heading: 'Inter', headingFallback: 'serif' },
        { heading: 'Inter', headingFallback: 'sans-serif' },
        {
            roleFamilyCatalog: {
                Inter: { fallback: 'sans-serif' },
            },
        }
    );
    assert.equal(stateWithCatalog, true);

    const fallbackOnlyState = roleStatesMatch(
        { heading: '', headingFallback: 'serif' },
        { heading: '', headingFallback: 'sans-serif' },
        {
            roleFamilyCatalog: {
                Inter: { fallback: 'sans-serif' },
            },
        }
    );
    assert.equal(fallbackOnlyState, false);

    const legacyCatalogFallback = roleStatesMatch(
        { heading: 'Inter' },
        { heading: 'Inter' },
        {
            roleFamilyCatalog: {
                Inter: { fallback: 'sans-serif' },
            },
        }
    );
    assert.equal(legacyCatalogFallback, true);

    const preserveExplicitFallbackWithoutCatalogEntry = roleStatesMatch(
        { heading: 'Inter', headingFallback: 'serif' },
        { heading: 'Inter', headingFallback: 'sans-serif' },
        {
            roleFamilyCatalog: {
                Roboto: { fallback: 'sans-serif' },
            },
        }
    );
    assert.equal(preserveExplicitFallbackWithoutCatalogEntry, false);
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
