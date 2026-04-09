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

    return {
        describeFontType,
        escapeFontFamily,
        getTabNavigationTargetIndex,
        hasStaticFontMetadata,
        hasVariableFontMetadata,
        sanitizeFallback,
        slugify,
    };
});
