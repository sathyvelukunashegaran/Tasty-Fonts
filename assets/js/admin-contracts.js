(function (global, factory) {
    const contracts = factory();

    global.TastyFontsAdminContracts = contracts;

    if (typeof module === 'object' && module.exports) {
        module.exports = contracts;
    }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    function slugify(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[^a-z0-9\-_]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'font';
    }

    function sanitizeFallback(fallback, defaultValue = 'sans-serif') {
        const sanitized = String(fallback || '')
            .trim()
            .replace(/[^a-zA-Z0-9,\- "'`]+/g, '')
            .replace(/\s*,\s*/g, ', ')
            .replace(/\s+/g, ' ')
            .replace(/^[,\s]+|[,\s]+$/g, '');

        return sanitized || defaultValue;
    }

    function escapeFontFamily(family) {
        return String(family || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function getTabNavigationTargetIndex(key, currentIndex, count, orientation = 'horizontal') {
        if (typeof currentIndex !== 'number' || typeof count !== 'number' || count < 2 || currentIndex < 0 || currentIndex >= count) {
            return null;
        }

        const normalizedOrientation = String(orientation || 'horizontal').trim().toLowerCase() === 'vertical'
            ? 'vertical'
            : 'horizontal';

        switch (key) {
            case 'ArrowRight':
                return normalizedOrientation === 'horizontal' ? (currentIndex + 1) % count : null;
            case 'ArrowDown':
                return normalizedOrientation === 'vertical' ? (currentIndex + 1) % count : null;
            case 'ArrowLeft':
                return normalizedOrientation === 'horizontal' ? (currentIndex - 1 + count) % count : null;
            case 'ArrowUp':
                return normalizedOrientation === 'vertical' ? (currentIndex - 1 + count) % count : null;
            case 'Home':
                return 0;
            case 'End':
                return count - 1;
            default:
                return null;
        }
    }

    function resolveStatusAnnouncement(type) {
        return String(type || '').trim().toLowerCase() === 'error'
            ? { role: 'alert', live: 'assertive' }
            : { role: 'status', live: 'polite' };
    }

    function normalizeAxisTag(tag) {
        const normalized = String(tag || '').trim().toUpperCase();

        return /^[A-Z0-9]{4}$/.test(normalized) ? normalized : '';
    }

    function hasVariableFontMetadata(entry) {
        if (!entry || typeof entry !== 'object') {
            return false;
        }

        const formats = entry.formats && typeof entry.formats === 'object'
            ? entry.formats
            : {};

        if (formats.variable && typeof formats.variable === 'object') {
            return true;
        }

        if (entry.has_variable_faces || entry.is_variable) {
            return true;
        }

        const variationAxes = entry.variation_axes && typeof entry.variation_axes === 'object'
            ? entry.variation_axes
            : {};

        if (Object.keys(variationAxes).some((tag) => normalizeAxisTag(tag) !== '')) {
            return true;
        }

        const axes = entry.axes && typeof entry.axes === 'object' ? entry.axes : {};

        if (Object.keys(axes).some((tag) => normalizeAxisTag(tag) !== '')) {
            return true;
        }

        const axisTags = Array.isArray(entry.axis_tags) ? entry.axis_tags : [];

        if (axisTags.some((tag) => normalizeAxisTag(tag) !== '')) {
            return true;
        }

        const faces = Array.isArray(entry.faces) ? entry.faces : [];

        return faces.some((face) => hasVariableFontMetadata(face));
    }

    function hasStaticFontMetadata(entry) {
        if (!entry || typeof entry !== 'object') {
            return false;
        }

        const formats = entry.formats && typeof entry.formats === 'object'
            ? entry.formats
            : {};

        if (formats.static && typeof formats.static === 'object') {
            return true;
        }

        if (entry.has_static_faces) {
            return true;
        }

        const faces = Array.isArray(entry.faces) ? entry.faces : [];

        if (faces.some((face) => face && typeof face === 'object' && !hasVariableFontMetadata(face))) {
            return true;
        }

        return !hasVariableFontMetadata(entry);
    }

    function describeFontType(entry, provider = 'library') {
        const hasVariable = hasVariableFontMetadata(entry);
        const hasStatic = hasStaticFontMetadata(entry);
        const normalizedProvider = String(provider || '').trim().toLowerCase();

        if (normalizedProvider === 'bunny') {
            return {
                type: 'static',
                hasVariable: false,
                hasStatic: true,
                isSourceOnly: false,
            };
        }

        const variableFormat = entry && entry.formats && typeof entry.formats === 'object'
            ? entry.formats.variable
            : null;
        const isSourceOnly = !!(
            hasVariable
            && (
                (variableFormat && typeof variableFormat === 'object' && variableFormat.source_only)
                || (normalizedProvider === 'bunny' && (!variableFormat || variableFormat.available === false))
            )
        );

        return {
            type: hasStatic && hasVariable
                ? 'static-variable'
                : (hasVariable ? 'variable' : 'static'),
            hasVariable,
            hasStatic,
            isSourceOnly,
        };
    }

    function normalizeRoleWeight(weight) {
        return String(weight || '').trim();
    }

    function normalizeAxisSettings(axes) {
        if (!axes || typeof axes !== 'object' || Array.isArray(axes)) {
            return {};
        }

        const normalized = {};

        Object.entries(axes)
            .map(([key, value]) => [normalizeAxisTag(key), String(value || '').trim()])
            .filter(([key, value]) => key !== '' && value !== '')
            .sort()
            .forEach(([key, value]) => {
                normalized[key] = value;
            });

        return normalized;
    }

    function defaultRoleFallback(roleKey) {
        return roleKey === 'monospace' ? 'monospace' : 'sans-serif';
    }

    function resolveRoleFallback(roleKey, input = {}, options = {}) {
        const defaultFallback = defaultRoleFallback(roleKey);
        const familyName = String(input[roleKey] || '').trim();
        const familyCatalog = options.roleFamilyCatalog && typeof options.roleFamilyCatalog === 'object'
            ? options.roleFamilyCatalog
            : {};

        if (familyName && familyCatalog[familyName] && typeof familyCatalog[familyName] === 'object') {
            const familyFallback = String(familyCatalog[familyName].fallback || '').trim();

            if (familyFallback !== '') {
                return sanitizeFallback(familyFallback, defaultFallback);
            }
        }

        return sanitizeFallback(input[`${roleKey}Fallback`] || input[`${roleKey}_fallback`], defaultFallback);
    }

    function normalizeRoleState(input = {}, options = {}) {
        const monospaceRoleEnabled = !!options.monospaceRoleEnabled;
        const variableFontsEnabled = !!options.variableFontsEnabled;

        return {
            heading: String(input.heading || '').trim(),
            body: String(input.body || '').trim(),
            monospace: monospaceRoleEnabled ? String(input.monospace || '').trim() : '',
            headingFallback: resolveRoleFallback('heading', input, options),
            bodyFallback: resolveRoleFallback('body', input, options),
            monospaceFallback: resolveRoleFallback('monospace', input, options),
            headingWeight: normalizeRoleWeight(input.headingWeight || input.heading_weight),
            bodyWeight: normalizeRoleWeight(input.bodyWeight || input.body_weight),
            monospaceWeight: normalizeRoleWeight(input.monospaceWeight || input.monospace_weight),
            headingAxes: variableFontsEnabled ? normalizeAxisSettings(input.headingAxes || input.heading_axes) : {},
            bodyAxes: variableFontsEnabled ? normalizeAxisSettings(input.bodyAxes || input.body_axes) : {},
            monospaceAxes: variableFontsEnabled && monospaceRoleEnabled
                ? normalizeAxisSettings(input.monospaceAxes || input.monospace_axes)
                : {},
        };
    }

    function roleStatesMatch(left = {}, right = {}, options = {}) {
        const monospaceRoleEnabled = !!options.monospaceRoleEnabled;
        const variableFontsEnabled = !!options.variableFontsEnabled;
        const leftState = normalizeRoleState(left, options);
        const rightState = normalizeRoleState(right, options);
        const roleKeys = monospaceRoleEnabled ? ['heading', 'body', 'monospace'] : ['heading', 'body'];

        return roleKeys.every((roleKey) => {
            if (leftState[roleKey] !== rightState[roleKey]) {
                return false;
            }

            if (leftState[`${roleKey}Fallback`] !== rightState[`${roleKey}Fallback`]) {
                return false;
            }

            if (leftState[`${roleKey}Weight`] !== rightState[`${roleKey}Weight`]) {
                return false;
            }

            if (!variableFontsEnabled) {
                return true;
            }

            return JSON.stringify(leftState[`${roleKey}Axes`] || {}) === JSON.stringify(rightState[`${roleKey}Axes`] || {});
        });
    }

    function resolveAssignedRoleState(roleKey, family, currentState = {}, options = {}) {
        const normalizedRoleKey = String(roleKey || '').trim();
        const nextFamily = String(family || '').trim();
        const nextState = normalizeRoleState(
            {
                ...currentState,
                [normalizedRoleKey]: nextFamily,
                [`${normalizedRoleKey}Weight`]: '',
                [`${normalizedRoleKey}Axes`]: {},
            },
            options
        );
        const preserveStates = Array.isArray(options.preserveStates) ? options.preserveStates : [];

        const matchingState = preserveStates
            .map((state) => normalizeRoleState(state, options))
            .find((state) => String(state[normalizedRoleKey] || '').trim() === nextFamily);

        if (!matchingState) {
            return nextState;
        }

        nextState[`${normalizedRoleKey}Weight`] = String(matchingState[`${normalizedRoleKey}Weight`] || '').trim();
        nextState[`${normalizedRoleKey}Axes`] = !!options.variableFontsEnabled
            ? normalizeAxisSettings(matchingState[`${normalizedRoleKey}Axes`] || {})
            : {};

        return nextState;
    }

    function sanitizeOutputQuickModePreference(value) {
        const normalized = String(value || '').trim().toLowerCase();

        return ['minimal', 'variables', 'classes', 'custom'].includes(normalized) ? normalized : '';
    }

    function normalizeToggleFlags(flags) {
        return Array.isArray(flags)
            ? flags.filter((value) => typeof value === 'boolean')
            : [];
    }

    function allToggleFlagsEnabled(flags) {
        return normalizeToggleFlags(flags).every((value) => value);
    }

    function deriveExactOutputQuickMode(state = {}) {
        const minimalEnabled = !!state.minimalEnabled;
        const classOutputEnabled = !!state.classOutputEnabled;
        const variableOutputEnabled = !!state.variableOutputEnabled;
        const roleUsageFontWeightEnabled = !!state.roleUsageFontWeightEnabled;
        const classFlags = normalizeToggleFlags(state.classFlags);
        const variableFlags = normalizeToggleFlags(state.variableFlags);

        if (minimalEnabled) {
            return 'minimal';
        }

        if (!roleUsageFontWeightEnabled && !classOutputEnabled && variableOutputEnabled && allToggleFlagsEnabled(variableFlags)) {
            return 'variables';
        }

        if (!roleUsageFontWeightEnabled && classOutputEnabled && !variableOutputEnabled && allToggleFlagsEnabled(classFlags)) {
            return 'classes';
        }

        return 'custom';
    }

    function normalizeOutputQuickModePreference(preference, state = {}) {
        const normalizedPreference = sanitizeOutputQuickModePreference(preference);
        const exactMode = deriveExactOutputQuickMode(state);

        if (normalizedPreference === 'custom') {
            return 'custom';
        }

        if (normalizedPreference === '') {
            return exactMode;
        }

        return exactMode === normalizedPreference ? normalizedPreference : 'custom';
    }

    function canDisableOutputLayer(layerKey, state = {}) {
        const normalizedLayerKey = String(layerKey || '').trim().toLowerCase();
        const classOutputEnabled = !!state.classOutputEnabled;
        const variableOutputEnabled = !!state.variableOutputEnabled;

        if (normalizedLayerKey === 'classes') {
            return !classOutputEnabled || variableOutputEnabled;
        }

        if (normalizedLayerKey === 'variables') {
            return !variableOutputEnabled || classOutputEnabled;
        }

        return true;
    }

    function settingsStatesMatch(left = {}, right = {}) {
        return JSON.stringify(left || {}) === JSON.stringify(right || {});
    }

    return {
        canDisableOutputLayer,
        describeFontType,
        deriveExactOutputQuickMode,
        escapeFontFamily,
        getTabNavigationTargetIndex,
        hasStaticFontMetadata,
        hasVariableFontMetadata,
        normalizeOutputQuickModePreference,
        resolveStatusAnnouncement,
        resolveAssignedRoleState,
        roleStatesMatch,
        settingsStatesMatch,
        sanitizeFallback,
        sanitizeOutputQuickModePreference,
        slugify,
    };
});
