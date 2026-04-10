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

    function getTabNavigationTargetIndex(key, currentIndex, count) {
        if (typeof currentIndex !== 'number' || typeof count !== 'number' || count < 2 || currentIndex < 0 || currentIndex >= count) {
            return null;
        }

        switch (key) {
            case 'ArrowRight':
            case 'ArrowDown':
                return (currentIndex + 1) % count;
            case 'ArrowLeft':
            case 'ArrowUp':
                return (currentIndex - 1 + count) % count;
            case 'Home':
                return 0;
            case 'End':
                return count - 1;
            default:
                return null;
        }
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

    function normalizeRoleState(input = {}, options = {}) {
        const monospaceRoleEnabled = !!options.monospaceRoleEnabled;
        const variableFontsEnabled = !!options.variableFontsEnabled;

        return {
            heading: String(input.heading || '').trim(),
            body: String(input.body || '').trim(),
            monospace: monospaceRoleEnabled ? String(input.monospace || '').trim() : '',
            headingDeliveryId: String(input.headingDeliveryId || input.heading_delivery_id || '').trim(),
            bodyDeliveryId: String(input.bodyDeliveryId || input.body_delivery_id || '').trim(),
            monospaceDeliveryId: monospaceRoleEnabled ? String(input.monospaceDeliveryId || input.monospace_delivery_id || '').trim() : '',
            headingFallback: sanitizeFallback(input.headingFallback || input.heading_fallback, 'sans-serif'),
            bodyFallback: sanitizeFallback(input.bodyFallback || input.body_fallback, 'sans-serif'),
            monospaceFallback: sanitizeFallback(input.monospaceFallback || input.monospace_fallback, 'monospace'),
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

    function roleDeliveryOptionsForFamily(familyName, roleDeliveryCatalog = {}) {
        const entry = familyName && roleDeliveryCatalog && typeof roleDeliveryCatalog === 'object'
            ? roleDeliveryCatalog[familyName]
            : null;
        const deliveries = entry && Array.isArray(entry.deliveries) ? entry.deliveries : [];

        return deliveries.filter((option) => option && typeof option === 'object');
    }

    function resolveRoleDeliveryId(roleKey, state = {}, roleDeliveryCatalog = {}) {
        const familyName = String(state[roleKey] || '').trim();
        const savedValue = String(state[`${roleKey}DeliveryId`] || state[`${roleKey}_delivery_id`] || '').trim();
        const options = roleDeliveryOptionsForFamily(familyName, roleDeliveryCatalog);

        if (!familyName || !options.length) {
            return '';
        }

        if (savedValue && options.some((option) => String(option.id || '') === savedValue)) {
            return savedValue;
        }

        const familyEntry = roleDeliveryCatalog && typeof roleDeliveryCatalog === 'object'
            ? roleDeliveryCatalog[familyName]
            : null;
        const activeDeliveryId = familyEntry ? String(familyEntry.active_delivery_id || '').trim() : '';

        if (activeDeliveryId && options.some((option) => String(option.id || '') === activeDeliveryId)) {
            return activeDeliveryId;
        }

        return String(options[0].id || '').trim();
    }

    function roleStatesMatch(left = {}, right = {}, options = {}) {
        const monospaceRoleEnabled = !!options.monospaceRoleEnabled;
        const variableFontsEnabled = !!options.variableFontsEnabled;
        const roleDeliveryCatalog = options.roleDeliveryCatalog && typeof options.roleDeliveryCatalog === 'object'
            ? options.roleDeliveryCatalog
            : {};
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

            if (resolveRoleDeliveryId(roleKey, leftState, roleDeliveryCatalog) !== resolveRoleDeliveryId(roleKey, rightState, roleDeliveryCatalog)) {
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

    return {
        describeFontType,
        escapeFontFamily,
        getTabNavigationTargetIndex,
        hasStaticFontMetadata,
        hasVariableFontMetadata,
        roleStatesMatch,
        sanitizeFallback,
        slugify,
    };
});
