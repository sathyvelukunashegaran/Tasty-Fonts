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

    function isTrustedHostedStylesheetUrl(href) {
        const allowedOrigins = new Set([
            'https://fonts.googleapis.com',
            'https://fonts.bunny.net',
        ]);

        try {
            const url = new URL(String(href || ''));

            return url.protocol === 'https:'
                && allowedOrigins.has(url.origin)
                && url.pathname === '/css2';
        } catch (error) {
            return false;
        }
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

    function tokenizeAttributeValue(value) {
        return String(value || '').split(/\s+/).filter(Boolean);
    }

    function rowMatchesLibraryFilters(row = {}, filters = {}) {
        const query = String(filters.query || '').trim().toLowerCase();
        const sourceFilter = String(filters.sourceFilter || 'all').trim().toLowerCase();
        const categoryFilter = String(filters.categoryFilter || 'all').trim().toLowerCase();
        const name = String(row.name || '').trim().toLowerCase();
        const sourceTokens = tokenizeAttributeValue(row.sources || '');
        const categoryTokens = tokenizeAttributeValue(row.categories || '');
        const matchesQuery = !query || name.includes(query);
        const matchesSource = !sourceFilter
            || sourceFilter === 'all'
            || sourceTokens.includes(sourceFilter)
            || (sourceFilter === 'published' && sourceTokens.includes('role_active'));
        const matchesCategory = !categoryFilter
            || categoryFilter === 'all'
            || categoryTokens.includes(categoryFilter);

        return matchesQuery && matchesSource && matchesCategory;
    }

    function shouldHydrateFamilyDetails(target = {}) {
        if (!target || typeof target !== 'object') {
            return false;
        }

        return !target.loaded && !target.loading && String(target.familySlug || '').trim() !== '';
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

    function cssAxisTag(tag) {
        switch (normalizeAxisTag(tag)) {
            case 'WGHT':
                return 'wght';
            case 'WDTH':
                return 'wdth';
            case 'SLNT':
                return 'slnt';
            case 'ITAL':
                return 'ital';
            case 'OPSZ':
                return 'opsz';
            default:
                return normalizeAxisTag(tag);
        }
    }

    function normalizeAxisValue(value) {
        const normalized = String(value ?? '').trim();

        return /^-?\d+(?:\.\d+)?$/.test(normalized) ? normalized : '';
    }

    function normalizeAxisSettings(axes) {
        if (!axes || typeof axes !== 'object' || Array.isArray(axes)) {
            return {};
        }

        const normalized = {};

        Object.entries(axes).forEach(([tag, value]) => {
            const normalizedTag = normalizeAxisTag(tag);
            const normalizedValue = normalizeAxisValue(value);

            if (!normalizedTag || !normalizedValue) {
                return;
            }

            normalized[normalizedTag] = normalizedValue;
        });

        return Object.keys(normalized)
            .sort()
            .reduce((carry, tag) => {
                carry[tag] = normalized[tag];
                return carry;
            }, {});
    }

    function normalizeRoleWeightValue(weight) {
        const normalized = String(weight || '').trim().toLowerCase();

        if (!normalized) {
            return '';
        }

        if (normalized === 'normal') {
            return '400';
        }

        if (normalized === 'bold') {
            return '700';
        }

        if (!/^\d{1,4}$/.test(normalized)) {
            return '';
        }

        const numeric = Number.parseInt(normalized, 10);

        return numeric >= 1 && numeric <= 1000 ? String(numeric) : '';
    }

    function buildVariationSettings(settings = {}) {
        const normalized = normalizeAxisSettings(settings);
        const parts = Object.entries(normalized).map(([tag, value]) => `"${cssAxisTag(tag)}" ${value}`);

        return parts.length ? parts.join(', ') : 'normal';
    }

    function defaultRoleFallback(roleKey) {
        return roleKey === 'monospace' ? 'monospace' : 'system-ui, sans-serif';
    }

    function resolveRoleFallback(roleKey, input = {}, options = {}) {
        const defaultFallback = defaultRoleFallback(roleKey);
        const familyName = String(input[roleKey] || '').trim();
        const explicitFallback = String(input[`${roleKey}Fallback`] || input[`${roleKey}_fallback`] || '').trim();
        const familyCatalog = options.roleFamilyCatalog && typeof options.roleFamilyCatalog === 'object'
            ? options.roleFamilyCatalog
            : {};

        if (explicitFallback !== '') {
            return sanitizeFallback(explicitFallback, defaultFallback);
        }

        if (familyName && familyCatalog[familyName] && typeof familyCatalog[familyName] === 'object') {
            const familyFallback = String(familyCatalog[familyName].fallback || '').trim();

            if (familyFallback !== '') {
                return sanitizeFallback(familyFallback, defaultFallback);
            }
        }

        return defaultFallback;
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
            headingWeight: normalizeRoleWeightValue(input.headingWeight || input.heading_weight),
            bodyWeight: normalizeRoleWeightValue(input.bodyWeight || input.body_weight),
            monospaceWeight: normalizeRoleWeightValue(input.monospaceWeight || input.monospace_weight),
            headingAxes: variableFontsEnabled ? normalizeAxisSettings(input.headingAxes || input.heading_axes) : {},
            bodyAxes: variableFontsEnabled ? normalizeAxisSettings(input.bodyAxes || input.body_axes) : {},
            monospaceAxes: variableFontsEnabled && monospaceRoleEnabled
                ? normalizeAxisSettings(input.monospaceAxes || input.monospace_axes)
                : {},
        };
    }

    function defaultRoleWeight(roleKey) {
        return roleKey === 'heading' ? '700' : '400';
    }

    function resolveRoleWeight(roleKey, state = {}) {
        const axes = normalizeAxisSettings(state[`${roleKey}Axes`] || state[`${roleKey}_axes`] || {});

        if (axes.WGHT) {
            return axes.WGHT;
        }

        return normalizeRoleWeightValue(state[`${roleKey}Weight`] || state[`${roleKey}_weight`]) || defaultRoleWeight(roleKey);
    }

    function hasExplicitRoleWeight(roleKey, state = {}) {
        const axes = normalizeAxisSettings(state[`${roleKey}Axes`] || state[`${roleKey}_axes`] || {});

        if (axes.WGHT) {
            return true;
        }

        return normalizeRoleWeightValue(state[`${roleKey}Weight`] || state[`${roleKey}_weight`]) !== '';
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

    function normalizeSettingsFieldName(name = '') {
        return String(name || '').replace(/\[\]$/, '').trim();
    }

    function serializeSettingsFormEntries(entries = [], options = {}) {
        const ignoredKeys = new Set(
            Array.isArray(options.ignoredKeys)
                ? options.ignoredKeys.map((key) => String(key || ''))
                : []
        );
        const body = {};

        Array.from(entries || []).forEach((entry) => {
            if (!Array.isArray(entry) || entry.length < 2) {
                return;
            }

            const rawKey = String(entry[0] || '');
            const value = entry[1];

            if (rawKey === '' || ignoredKeys.has(rawKey) || typeof value !== 'string') {
                return;
            }

            const normalizedKey = normalizeSettingsFieldName(rawKey);

            if (normalizedKey === '') {
                return;
            }

            if (rawKey.endsWith('[]')) {
                if (!Array.isArray(body[normalizedKey])) {
                    body[normalizedKey] = [];
                }

                if (value !== '') {
                    body[normalizedKey].push(value);
                }

                return;
            }

            body[normalizedKey] = value;
        });

        return body;
    }

    function settingsStatesMatch(left = {}, right = {}) {
        return JSON.stringify(left || {}) === JSON.stringify(right || {});
    }

    function parsePhpIniSizeToBytes(value = '') {
        const normalized = String(value || '').trim().toLowerCase();

        if (!normalized) {
            return 0;
        }

        const match = normalized.match(/^(\d+(?:\.\d+)?)\s*([gmk])?b?$/);

        if (!match) {
            return 0;
        }

        const amount = Number.parseFloat(match[1] || '0');
        const unit = String(match[2] || '');

        if (!Number.isFinite(amount) || amount <= 0) {
            return 0;
        }

        switch (unit) {
            case 'g':
                return Math.round(amount * 1024 * 1024 * 1024);
            case 'm':
                return Math.round(amount * 1024 * 1024);
            case 'k':
                return Math.round(amount * 1024);
            default:
                return Math.round(amount);
        }
    }

    function shouldDisableFieldDuringSiteTransferSubmit(tagName = '', type = '') {
        const normalizedTagName = String(tagName || '').trim().toLowerCase();
        const normalizedType = String(type || '').trim().toLowerCase();

        if (normalizedTagName === 'button') {
            return true;
        }

        if (normalizedTagName === 'input') {
            return normalizedType === 'submit' || normalizedType === 'button';
        }

        return false;
    }

    function normalizeCustomCssDryRunPlan(payload = {}) {
        const plan = payload && payload.plan && typeof payload.plan === 'object' ? payload.plan : payload;
        const source = plan && plan.source && typeof plan.source === 'object' ? plan.source : {};
        const families = Array.isArray(plan && plan.families) ? plan.families : [];
        const normalizedFamilies = families
            .map((family) => {
                const faces = Array.isArray(family && family.faces) ? family.faces : [];
                const familyName = String(family && family.family || '').trim();
                const familySlug = String(family && family.slug || slugify(familyName)).trim();

                return {
                    family: familyName,
                    slug: familySlug || slugify(familyName),
                    fallback: String(family && family.fallback || 'sans-serif').trim() || 'sans-serif',
                    faces: faces.map((face) => {
                        const validation = face && face.validation && typeof face.validation === 'object' ? face.validation : {};
                        const status = String(face && face.status || 'valid').trim().toLowerCase();

                        return {
                            id: String(face && face.id || '').trim(),
                            weight: String(face && face.weight || '400').trim(),
                            style: String(face && face.style || 'normal').trim(),
                            format: String(face && face.format || '').trim().toLowerCase(),
                            url: String(face && face.url || '').trim(),
                            host: String(face && face.host || '').trim(),
                            unicodeRange: String(face && (face.unicode_range || face.unicodeRange) || '').trim(),
                            status,
                            selected: face && Object.prototype.hasOwnProperty.call(face, 'selected') ? !!face.selected : (status === 'valid' || status === 'warning'),
                            warnings: Array.isArray(face && face.warnings) ? face.warnings.map((warning) => String(warning || '').trim()).filter(Boolean) : [],
                            duplicateMatches: Array.isArray(face && face.duplicate_matches)
                                ? face.duplicate_matches.map((match) => ({
                                    family: String(match && match.family || '').trim(),
                                    familySlug: String(match && (match.family_slug || match.familySlug) || '').trim(),
                                    deliveryId: String(match && (match.delivery_id || match.deliveryId) || '').trim(),
                                    deliveryLabel: String(match && (match.delivery_label || match.deliveryLabel) || '').trim(),
                                    provider: String(match && match.provider || '').trim(),
                                    deliveryType: String(match && (match.delivery_type || match.deliveryType) || '').trim(),
                                    format: String(match && match.format || '').trim().toLowerCase(),
                                    replaceable: !!(match && match.replaceable),
                                    protected: !!(match && match.protected),
                                })).filter((match) => match.deliveryId)
                                : [],
                            duplicateSummary: face && face.duplicate_summary && typeof face.duplicate_summary === 'object'
                                ? {
                                    hasMatches: !!face.duplicate_summary.has_matches,
                                    hasReplaceableCustomMatches: !!face.duplicate_summary.has_replaceable_custom_matches,
                                    defaultAction: String(face.duplicate_summary.default_action || 'import').trim(),
                                    selfHostedMatches: Number.parseInt(face.duplicate_summary.self_hosted_matches, 10) || 0,
                                    remoteMatches: Number.parseInt(face.duplicate_summary.remote_matches, 10) || 0,
                                }
                                : { hasMatches: false, hasReplaceableCustomMatches: false, defaultAction: 'import', selfHostedMatches: 0, remoteMatches: 0 },
                            validation: {
                                method: String(validation.method || '').trim(),
                                contentType: String(validation.content_type || validation.contentType || '').trim(),
                                contentLength: Number.parseInt(validation.content_length || validation.contentLength, 10) || 0,
                                notes: Array.isArray(validation.notes) ? validation.notes.map((note) => String(note || '').trim()).filter(Boolean) : [],
                            },
                        };
                    }).filter((face) => face.id && face.url),
                };
            })
            .filter((family) => family.family && family.faces.length > 0);

        return {
            status: String(payload && payload.status || plan && plan.status || 'dry_run').trim() || 'dry_run',
            message: String(payload && payload.message || '').trim(),
            source: {
                type: String(source.type || 'custom_css_url').trim(),
                url: String(source.url || '').trim(),
                host: String(source.host || '').trim(),
            },
            families: normalizedFamilies,
            counts: {
                families: Number.parseInt(plan && plan.counts && plan.counts.families, 10) || normalizedFamilies.length,
                faces: Number.parseInt(plan && plan.counts && plan.counts.faces, 10) || normalizedFamilies.reduce((total, family) => total + family.faces.length, 0),
                validFaces: Number.parseInt(plan && plan.counts && (plan.counts.valid_faces || plan.counts.validFaces), 10) || normalizedFamilies.reduce((total, family) => total + family.faces.filter((face) => face.status === 'valid').length, 0),
                warningFaces: Number.parseInt(plan && plan.counts && (plan.counts.warning_faces || plan.counts.warningFaces), 10) || normalizedFamilies.reduce((total, family) => total + family.faces.filter((face) => face.status === 'warning').length, 0),
                invalidFaces: Number.parseInt(plan && plan.counts && (plan.counts.invalid_faces || plan.counts.invalidFaces), 10) || normalizedFamilies.reduce((total, family) => total + family.faces.filter((face) => face.status === 'invalid').length, 0),
                unsupportedFaces: Number.parseInt(plan && plan.counts && (plan.counts.unsupported_faces || plan.counts.unsupportedFaces), 10) || normalizedFamilies.reduce((total, family) => total + family.faces.filter((face) => face.status === 'unsupported').length, 0),
                duplicateFaces: Number.parseInt(plan && plan.counts && (plan.counts.duplicate_faces || plan.counts.duplicateFaces), 10) || normalizedFamilies.reduce((total, family) => total + family.faces.filter((face) => face.duplicateSummary.hasMatches).length, 0),
                replaceableDuplicateFaces: Number.parseInt(plan && plan.counts && (plan.counts.replaceable_duplicate_faces || plan.counts.replaceableDuplicateFaces), 10) || normalizedFamilies.reduce((total, family) => total + family.faces.filter((face) => face.duplicateSummary.hasReplaceableCustomMatches).length, 0),
            },
            warnings: Array.isArray(plan && plan.warnings) ? plan.warnings.map((warning) => String(warning || '').trim()).filter(Boolean) : [],
            snapshotToken: String(payload && (payload.snapshot_token || payload.snapshotToken) || plan && (plan.snapshot_token || plan.snapshotToken) || '').trim(),
            snapshotExpiresAt: Number.parseInt(payload && (payload.snapshot_expires_at || payload.snapshotExpiresAt) || plan && (plan.snapshot_expires_at || plan.snapshotExpiresAt), 10) || 0,
            snapshotTtlSeconds: Number.parseInt(payload && (payload.snapshot_ttl_seconds || payload.snapshotTtlSeconds) || plan && (plan.snapshot_ttl_seconds || plan.snapshotTtlSeconds), 10) || 0,
        };
    }

    function buildCustomCssDryRunRequest(url) {
        return {
            url: String(url || '').trim(),
        };
    }

    function buildCustomCssFinalImportRequest(snapshotToken, selectedFaceIds = [], options = {}) {
        const ids = Array.isArray(selectedFaceIds)
            ? selectedFaceIds
            : String(selectedFaceIds || '').split(',');
        const familyFallbacks = options && options.familyFallbacks && typeof options.familyFallbacks === 'object'
            ? Object.fromEntries(Object.entries(options.familyFallbacks)
                .map(([key, value]) => [slugify(key), sanitizeFallback(value)])
                .filter(([key, value]) => key && value))
            : {};
        const deliveryMode = ['self_hosted', 'remote', 'cdn'].includes(String(options.deliveryMode || '').trim())
            ? String(options.deliveryMode).trim()
            : 'self_hosted';
        const duplicateHandling = ['skip', 'replace_custom'].includes(String(options.duplicateHandling || '').trim())
            ? String(options.duplicateHandling).trim()
            : 'skip';

        return {
            snapshot_token: String(snapshotToken || '').trim(),
            selected_face_ids: Array.from(new Set(ids.map((id) => String(id || '').trim()).filter(Boolean))),
            delivery_mode: deliveryMode,
            family_fallbacks: familyFallbacks,
            duplicate_handling: duplicateHandling,
            activate: !!(options && options.activate),
            publish: !!(options && options.publish),
        };
    }

    function normalizeCustomCssFailedFaces(payload = {}) {
        const data = payload && payload.data && typeof payload.data === 'object' ? payload.data : {};
        const failedFaces = Array.isArray(data.failed_faces)
            ? data.failed_faces
            : (Array.isArray(payload.failed_faces) ? payload.failed_faces : []);

        return failedFaces
            .map((face) => {
                if (!face || typeof face !== 'object') {
                    return null;
                }

                return {
                    family: String(face.family || '').trim(),
                    weight: String(face.weight || '').trim(),
                    style: String(face.style || '').trim(),
                    message: String(face.message || '').trim(),
                };
            })
            .filter((face) => face && face.message !== '');
    }

    function buildCustomCssImportErrorMessage(payload = {}, fallback = 'The custom CSS import failed.') {
        const baseMessage = String(payload && payload.message || fallback).trim() || fallback;
        const failedFaces = normalizeCustomCssFailedFaces(payload);

        if (failedFaces.length === 0) {
            return baseMessage;
        }

        const details = failedFaces.slice(0, 3).map((face) => {
            const family = face.family || 'Unknown family';
            const variant = [face.weight, face.style].filter(Boolean).join(' ').trim();

            return variant ? `${family} (${variant}): ${face.message}` : `${family}: ${face.message}`;
        });
        const remaining = failedFaces.length - details.length;
        const remainingSuffix = remaining > 0 ? `; +${remaining} more` : '';

        return `${baseMessage} Failed faces: ${details.join('; ')}${remainingSuffix}.`;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderCustomCssDryRunErrorHtml(message = '') {
        const text = String(message || 'The stylesheet dry run failed.').trim() || 'The stylesheet dry run failed.';

        return `<div class="tasty-fonts-empty tasty-fonts-empty--panel" role="alert">${escapeHtml(text)}</div>`;
    }

    function formatBytesForReview(bytes = 0) {
        const amount = Number.parseInt(bytes, 10) || 0;

        if (amount <= 0) {
            return '';
        }

        if (amount >= 1048576) {
            return `${Math.round((amount / 1048576) * 10) / 10} MB`;
        }

        if (amount >= 1024) {
            return `${Math.round(amount / 1024)} KB`;
        }

        return `${amount} B`;
    }

    function customCssFaceStatusLabel(status = '') {
        switch (String(status || '').trim().toLowerCase()) {
            case 'warning':
                return 'Warning';
            case 'invalid':
                return 'Invalid';
            case 'unsupported':
                return 'Unsupported format';
            default:
                return 'Validated';
        }
    }

    function renderCustomCssDryRunReviewHtml(payload = {}) {
        const plan = normalizeCustomCssDryRunPlan(payload);

        if (plan.families.length === 0) {
            return '<div class="tasty-fonts-empty tasty-fonts-empty--panel">No importable WOFF or WOFF2 faces were found. Check that the stylesheet includes @font-face rules with WOFF2 or WOFF sources.</div>';
        }

        const sourceHost = plan.source.host ? `<p class="tasty-fonts-muted">Source: ${escapeHtml(plan.source.host)}</p>` : '';
        const familyLabel = plan.counts.families === 1 ? 'family' : 'families';
        const faceLabel = plan.counts.faces === 1 ? 'face' : 'faces';
        const warnings = plan.warnings.length > 0
            ? `<ul class="tasty-fonts-url-review-warnings">${plan.warnings.map((warning) => `<li>${escapeHtml(warning)}</li>`).join('')}</ul>`
            : '';
        const selectableFaceCount = plan.families.reduce((total, family) => total + family.faces.filter((face) => face.status !== 'invalid' && face.status !== 'unsupported').length, 0);
        const deliveryControls = `<fieldset class="tasty-fonts-url-delivery-controls"><legend>Delivery mode</legend><label><input type="radio" name="custom_css_delivery_mode" value="self_hosted" checked> Self-hosted</label><label><input type="radio" name="custom_css_delivery_mode" value="remote"> Remote serving</label><p class="tasty-fonts-muted">Self-hosted downloads selected files into this site. Remote serving saves validated third-party font URLs without copying files.</p></fieldset>`;
        const duplicateControls = plan.counts.duplicateFaces > 0
            ? `<fieldset class="tasty-fonts-url-duplicate-controls"><legend>Duplicate handling</legend><label><input type="radio" name="custom_css_duplicate_handling" value="skip" checked> Skip matching faces (recommended)</label>${plan.counts.replaceableDuplicateFaces > 0 ? `<label><input type="radio" name="custom_css_duplicate_handling" value="replace_custom"> Advanced: replace matching custom CSS faces</label><p class="tasty-fonts-muted">Replacement only targets custom CSS profiles. Google, Bunny, Adobe, and local upload profiles are protected.</p>` : ''}</fieldset>`
            : '';
        const familyRows = plan.families.map((family) => {
            const fallbackControl = `<label class="tasty-fonts-stack-field tasty-fonts-url-family-fallback"><span>Fallback stack</span><input type="text" class="regular-text tasty-fonts-text-control" data-custom-css-family-fallback data-family-slug="${escapeHtml(family.slug)}" value="${escapeHtml(family.fallback)}" aria-label="Fallback stack for ${escapeHtml(family.family)}" autocomplete="off" spellcheck="false"></label>`;

            const faceRows = family.faces.map((face) => {
                const subset = face.unicodeRange ? `<span class="tasty-fonts-muted">${escapeHtml(face.unicodeRange)}</span>` : '';
                const checked = face.selected ? ' checked' : '';
                const disabled = face.status === 'invalid' || face.status === 'unsupported' ? ' disabled' : '';
                const statusLabel = customCssFaceStatusLabel(face.status);
                const status = `<span class="tasty-fonts-url-face-status" data-state="${escapeHtml(face.status)}">${escapeHtml(statusLabel)}</span>`;
                const size = formatBytesForReview(face.validation.contentLength);
                const validationMeta = [
                    face.validation.method ? `Check: ${face.validation.method}` : '',
                    face.validation.contentType ? `Type: ${face.validation.contentType}` : '',
                    size ? `Size: ${size}` : '',
                ].filter(Boolean).map((item) => `<li>${escapeHtml(item)}</li>`).join('');
                const notes = face.validation.notes.concat(face.warnings).map((note) => `<li>${escapeHtml(note)}</li>`).join('');
                const duplicateMatches = face.duplicateMatches.length > 0
                    ? `<ul>${face.duplicateMatches.map((match) => `<li>${escapeHtml(match.deliveryLabel || match.deliveryId)} · ${escapeHtml(match.deliveryType)} · ${match.replaceable ? 'replaceable custom CSS' : 'protected provider/local profile'}</li>`).join('')}</ul>`
                    : '';
                const duplicateDetails = duplicateMatches ? `<dt>Duplicate matches</dt><dd>${duplicateMatches}</dd>` : '';
                const details = `<details class="tasty-fonts-url-face-details"><summary>Details</summary><dl><dt>Resolved URL</dt><dd><code>${escapeHtml(face.url)}</code></dd>${duplicateDetails}</dl>${validationMeta ? `<ul>${validationMeta}</ul>` : ''}${notes ? `<ul>${notes}</ul>` : ''}</details>`;
                const duplicateBadge = face.duplicateSummary.hasMatches ? '<span class="tasty-fonts-url-face-status" data-state="duplicate">Duplicate</span>' : '';
                const faceLabel = [
                    family.family,
                    `${face.weight} ${face.style}`,
                    face.format.toUpperCase(),
                    face.host ? `from ${face.host}` : '',
                    face.unicodeRange ? `unicode range ${face.unicodeRange}` : '',
                    statusLabel,
                ].filter(Boolean).join(', ');

                return `<li class="tasty-fonts-url-face-row" data-state="${escapeHtml(face.status)}"><label><input type="checkbox" value="${escapeHtml(face.id)}"${checked}${disabled} aria-label="${escapeHtml(faceLabel)}"> <span>${escapeHtml(face.weight)} ${escapeHtml(face.style)} (${escapeHtml(face.format.toUpperCase())})</span></label><span class="tasty-fonts-muted">${escapeHtml(face.host)}</span>${subset}${status}${duplicateBadge}${details}</li>`;
            }).join('');

            return `<section class="tasty-fonts-url-review-family"><h5>${escapeHtml(family.family)}</h5>${fallbackControl}<ul>${faceRows}</ul></section>`;
        }).join('');
        const importDisabled = selectableFaceCount > 0 && plan.snapshotToken ? '' : ' disabled';
        const importHelp = plan.snapshotToken
            ? 'Only selected validated or warning faces will be imported from the server-side dry-run snapshot.'
            : 'Run the dry run again before importing; this review is missing a snapshot token.';
        const finalControls = `<div class="tasty-fonts-url-final-controls"><p class="tasty-fonts-muted">${escapeHtml(importHelp)}</p><div class="tasty-fonts-upload-actions"><button type="submit" class="button button-primary" data-custom-css-import-submit${importDisabled}>Import Selected Faces</button></div><div class="tasty-fonts-import-status" data-custom-css-import-status aria-live="polite" aria-atomic="true"></div></div>`;

        return `<form class="tasty-fonts-url-final-import-form" data-custom-css-final-form novalidate><div class="tasty-fonts-url-review-summary"><strong>${escapeHtml(String(plan.counts.families))} ${familyLabel}, ${escapeHtml(String(plan.counts.faces))} ${faceLabel}</strong>${sourceHost}${warnings}</div>${deliveryControls}${duplicateControls}${familyRows}${finalControls}</form>`;
    }

    function logEntryMatchesFilters(actor = '', searchValue = '', actorFilter = '', query = '') {
        const normalizedActor = String(actor || '').trim().toLowerCase();
        const normalizedSearchValue = String(searchValue || '').trim().toLowerCase();
        const normalizedActorFilter = String(actorFilter || '').trim().toLowerCase();
        const normalizedQuery = String(query || '').trim().toLowerCase();

        const matchesActor = !normalizedActorFilter || normalizedActor === normalizedActorFilter;
        const matchesQuery = !normalizedQuery || normalizedSearchValue.includes(normalizedQuery);

        return matchesActor && matchesQuery;
    }

    function resolveLogPagination(visibleCount = 0, currentPage = 1, pageSize = 5) {
        const normalizedVisibleCount = Math.max(0, Number.parseInt(visibleCount, 10) || 0);
        const normalizedPageSize = Math.max(1, Number.parseInt(pageSize, 10) || 5);
        const totalPages = Math.max(1, Math.ceil(normalizedVisibleCount / normalizedPageSize));
        const normalizedPage = Math.max(1, Number.parseInt(currentPage, 10) || 1);
        const page = Math.min(normalizedPage, totalPages);
        const start = normalizedVisibleCount === 0 ? 0 : (page - 1) * normalizedPageSize;
        const end = normalizedVisibleCount === 0 ? 0 : Math.min(start + normalizedPageSize, normalizedVisibleCount);

        return {
            page,
            pageSize: normalizedPageSize,
            totalPages,
            start,
            end,
            hasPrevious: page > 1,
            hasNext: page < totalPages,
        };
    }

    return {
        buildCustomCssDryRunRequest,
        buildCustomCssFinalImportRequest,
        buildCustomCssImportErrorMessage,
        buildVariationSettings,
        canDisableOutputLayer,
        cssAxisTag,
        defaultRoleFallback,
        defaultRoleWeight,
        describeFontType,
        deriveExactOutputQuickMode,
        escapeFontFamily,
        getTabNavigationTargetIndex,
        hasStaticFontMetadata,
        hasVariableFontMetadata,
        isTrustedHostedStylesheetUrl,
        normalizeCustomCssDryRunPlan,
        hasExplicitRoleWeight,
        logEntryMatchesFilters,
        normalizeAxisSettings,
        normalizeAxisTag,
        normalizeAxisValue,
        normalizeOutputQuickModePreference,
        normalizeRoleState,
        normalizeRoleWeightValue,
        parsePhpIniSizeToBytes,
        rowMatchesLibraryFilters,
        normalizeSettingsFieldName,
        resolveLogPagination,
        resolveRoleFallback,
        resolveRoleWeight,
        resolveStatusAnnouncement,
        resolveAssignedRoleState,
        renderCustomCssDryRunErrorHtml,
        renderCustomCssDryRunReviewHtml,
        roleStatesMatch,
        serializeSettingsFormEntries,
        settingsStatesMatch,
        shouldDisableFieldDuringSiteTransferSubmit,
        sanitizeFallback,
        sanitizeOutputQuickModePreference,
        shouldHydrateFamilyDetails,
        slugify,
    };
});
