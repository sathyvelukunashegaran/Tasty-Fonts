(function () {
    // DOM references
    const config = window.TastyFontsAdmin || {};
    const adminContracts = window.TastyFontsAdminContracts || {};
    const runtimeStrings = {
        ...(config.strings || {}),
        ...(config.runtimeStrings || {}),
    };
    const trainingWheelsOff = !!config.trainingWheelsOff;
    const wpI18n = window.wp && window.wp.i18n ? window.wp.i18n : {};
    const __ = typeof wpI18n.__ === 'function' ? wpI18n.__ : (text) => text;
    const _n = typeof wpI18n._n === 'function'
        ? wpI18n._n
        : (single, plural, count) => (count === 1 ? single : plural);
    const wpSprintf = typeof wpI18n.sprintf === 'function' ? wpI18n.sprintf : null;
    const roleHeading = document.getElementById('tasty_fonts_heading_font');
    const roleBody = document.getElementById('tasty_fonts_body_font');
    const roleMonospace = document.getElementById('tasty_fonts_monospace_font');
    const roleHeadingFallback = document.getElementById('tasty_fonts_heading_fallback');
    const roleBodyFallback = document.getElementById('tasty_fonts_body_fallback');
    const roleMonospaceFallback = document.getElementById('tasty_fonts_monospace_fallback');
    const roleForm = document.querySelector('[data-role-form]');
    const roleFormId = roleForm ? String(roleForm.getAttribute('id') || '') : '';
    const roleActionTypeInput = document.querySelector('[data-role-action-type]');
    const roleFormSubmitButtons = roleFormId
        ? Array.from(document.querySelectorAll(`button[name="tasty_fonts_action_type"][form="${roleFormId}"]`))
        : (roleForm ? Array.from(roleForm.querySelectorAll('button[name="tasty_fonts_action_type"]')) : []);
    const roleApplyLiveButton = document.querySelector('[data-role-apply-live]');
    const roleApplyLiveWrap = document.querySelector('[data-role-apply-live-wrap]');
    const roleSaveDraftButton = document.querySelector('[data-role-save-draft]');
    const roleSaveDraftWrap = document.querySelector('[data-role-save-draft-wrap]');
    const roleStudio = document.getElementById('tasty-fonts-roles-studio');
    const roleDeployment = document.querySelector('[data-role-deployment]');
    const roleDeploymentBadge = document.querySelector('[data-role-deployment-badge]');
    const roleDeploymentPill = document.querySelector('[data-role-deployment-pill]');
    const roleDeploymentAnnouncement = document.querySelector('[data-role-deployment-announcement]');
    const monospaceRoleEnabled = !!config.monospaceRoleEnabled && !!roleMonospace && !!roleMonospaceFallback;
    const headingRoleVariableCopies = Array.from(document.querySelectorAll('[data-role-variable-copy="heading"]'));
    const bodyRoleVariableCopies = Array.from(document.querySelectorAll('[data-role-variable-copy="body"]'));
    const monospaceRoleVariableCopies = Array.from(document.querySelectorAll('[data-role-variable-copy="monospace"]'));
    const headingFamilyVariableCopies = Array.from(document.querySelectorAll('[data-role-family-variable-copy="heading"]'));
    const bodyFamilyVariableCopies = Array.from(document.querySelectorAll('[data-role-family-variable-copy="body"]'));
    const monospaceFamilyVariableCopies = Array.from(document.querySelectorAll('[data-role-family-variable-copy="monospace"]'));
    const previewCanvas = document.getElementById('tasty-fonts-preview-canvas');
    const previewTray = document.querySelector('[data-preview-tray]');
    const previewSourceLabel = document.querySelector('[data-preview-source-label]');
    const previewDirtyIndicator = document.querySelector('[data-preview-dirty-indicator]');
    const previewCopyCssButton = document.querySelector('[data-preview-copy-css]');
    const previewResetButton = document.querySelector('[data-preview-reset]');
    const previewSyncDraftButton = document.querySelector('[data-preview-sync-draft]');
    const previewSaveDraftButton = document.querySelector('[data-preview-save-draft]');
    const previewApplyLiveButton = document.querySelector('[data-preview-apply-live]');
    const previewRoleSelects = {
        heading: document.querySelector('[data-preview-role-select="heading"]'),
        body: document.querySelector('[data-preview-role-select="body"]'),
        monospace: document.querySelector('[data-preview-role-select="monospace"]'),
    };
    const previewTextInput = document.getElementById('tasty-fonts-preview-text');
    const previewSizeInput = document.getElementById('tasty-fonts-preview-size');
    const roleAssignButtons = Array.from(document.querySelectorAll('[data-role-assign]'));
    const deleteFamilyButtons = Array.from(document.querySelectorAll('[data-delete-family]'));
    const roleHeadingPreviews = Array.from(document.querySelectorAll('[data-role-preview="heading"]'));
    const roleBodyPreviews = Array.from(document.querySelectorAll('[data-role-preview="body"]'));
    const roleMonospacePreviews = Array.from(document.querySelectorAll('[data-role-preview="monospace"]'));
    const roleHeadingPreviewNames = Array.from(document.querySelectorAll('[data-role-preview-name="heading"]'));
    const roleBodyPreviewNames = Array.from(document.querySelectorAll('[data-role-preview-name="body"]'));
    const roleMonospacePreviewNames = Array.from(document.querySelectorAll('[data-role-preview-name="monospace"]'));
    const previewDynamicText = Array.from(document.querySelectorAll('[data-preview-dynamic-text], .tasty-fonts-preview-dynamic-text'));
    const tabButtons = Array.from(document.querySelectorAll('[data-tab-group][data-tab-target]'));
    const tabPanels = Array.from(document.querySelectorAll('[data-tab-group][data-tab-panel]'));
    const outputNames = document.getElementById('tasty-fonts-output-names');
    const outputStacks = document.getElementById('tasty-fonts-output-stacks');
    const outputVars = document.getElementById('tasty-fonts-output-vars');
    const outputUsage = document.getElementById('tasty-fonts-output-usage');
    const disclosureToggles = Array.from(document.querySelectorAll('[data-disclosure-toggle]'));
    const librarySearch = document.getElementById('tasty-fonts-library-search');
    const librarySourceFilter = document.querySelector('[data-library-source-filter]');
    const libraryCategoryFilter = document.querySelector('[data-library-category-filter]');
    const libraryFilteredEmpty = document.getElementById('tasty-fonts-library-empty-filtered');
    const googleSearch = document.getElementById('tasty-fonts-google-search');
    const bunnySearch = document.getElementById('tasty-fonts-bunny-search');
    const adobeProjectId = document.getElementById('tasty-fonts-adobe-project-id');
    const googleResults = document.getElementById('tasty-fonts-google-results');
    const bunnyResults = document.getElementById('tasty-fonts-bunny-results');
    const manualFamily = document.getElementById('tasty-fonts-manual-family');
    const manualVariants = document.getElementById('tasty-fonts-manual-variants');
    const googleDeliveryModes = Array.from(document.querySelectorAll('input[name="tasty_fonts_google_delivery_mode"]'));
    const selectedFamily = document.getElementById('tasty-fonts-selected-family');
    const selectedFamilyPreview = document.getElementById('tasty-fonts-selected-family-preview');
    const bunnyFamily = document.getElementById('tasty-fonts-bunny-family');
    const bunnyVariants = document.getElementById('tasty-fonts-bunny-variants');
    const bunnyDeliveryModes = Array.from(document.querySelectorAll('input[name="tasty_fonts_bunny_delivery_mode"]'));
    const bunnySelectedFamily = document.getElementById('tasty-fonts-bunny-selected-family');
    const bunnySelectedFamilyPreview = document.getElementById('tasty-fonts-bunny-selected-family-preview');
    const importFilesEstimate = document.getElementById('tasty-fonts-import-files-estimate');
    const importSizeEstimate = document.getElementById('tasty-fonts-import-size-estimate');
    const importSelectionSummary = document.getElementById('tasty-fonts-import-selection-summary');
    const variantsWrap = document.getElementById('tasty-fonts-google-variants');
    const variantQuickSelectButtons = Array.from(document.querySelectorAll('[data-google-variant-select]'));
    const bunnyImportSizeEstimate = document.getElementById('tasty-fonts-bunny-import-size-estimate');
    const bunnyImportSelectionSummary = document.getElementById('tasty-fonts-bunny-import-selection-summary');
    const bunnyVariantsWrap = document.getElementById('tasty-fonts-bunny-variants-list');
    const bunnyVariantQuickSelectButtons = Array.from(document.querySelectorAll('[data-bunny-variant-select]'));
    const importButton = document.getElementById('tasty-fonts-import-submit');
    const importStatus = document.getElementById('tasty-fonts-import-status');
    const bunnyImportButton = document.getElementById('tasty-fonts-bunny-import-submit');
    const bunnyImportStatus = document.getElementById('tasty-fonts-bunny-import-status');
    const uploadForm = document.getElementById('tasty-fonts-upload-form');
    const uploadGroupsWrap = document.getElementById('tasty-fonts-upload-groups');
    const uploadGroupTemplate = document.getElementById('tasty-fonts-upload-group-template');
    const uploadRowTemplate = document.getElementById('tasty-fonts-upload-row-template');
    const uploadAddFamily = document.getElementById('tasty-fonts-upload-add-family');
    const uploadSubmit = document.getElementById('tasty-fonts-upload-submit');
    const uploadStatus = document.getElementById('tasty-fonts-upload-status');
    const addFontsPanelToggle = document.getElementById('tasty-fonts-add-font-panel-toggle');
    const toastItems = Array.from(document.querySelectorAll('[data-toast]'));
    let helpButtons = [];
    const helpTooltipLayer = document.getElementById('tasty-fonts-help-tooltip-layer');
    const activityActorFilter = document.querySelector('[data-activity-actor-filter]');
    const activitySearch = document.querySelector('[data-activity-search]');
    const activityCount = document.querySelector('[data-activity-count]');
    const activityList = document.querySelector('[data-activity-list]');
    const activityFilteredEmpty = document.getElementById('tasty-fonts-activity-empty-filtered');

    let selectedSearchFamily = null;
    let searchResults = [];
    let searchTimer = 0;
    let googleFamilyLookupTimer = 0;
    let googleFamilyLookupToken = 0;
    let selectedBunnySearchFamily = null;
    let bunnySearchResults = [];
    let bunnySearchTimer = 0;
    let bunnyFamilyLookupTimer = 0;
    let bunnyFamilyLookupToken = 0;
    let importInFlight = false;
    let uploadInFlight = false;
    let activeHelpButton = null;
    let nextUploadGroupIndex = uploadGroupsWrap ? uploadGroupsWrap.querySelectorAll('[data-upload-group]').length : 0;
    let nextUploadRowIndex = uploadGroupsWrap ? uploadGroupsWrap.querySelectorAll('[data-upload-row]').length : 0;
    let activeLibrarySourceFilter = 'all';
    let activeLibraryCategoryFilter = 'all';
    let syncingGoogleVariants = false;
    let renderedGoogleVariantFamily = '';
    let syncingBunnyVariants = false;
    let renderedBunnyVariantFamily = '';
    let roleDraftSaveInFlight = false;
    let previewRoleState = null;
    let previewWorkspaceInitialized = false;
    let previewDirty = false;
    let previewFollowsDraft = false;
    let defaultTrackedUiState = null;
    const pendingUiStateKey = 'tastyFontsPendingUiState';
    const trackedUiQueryKeys = [
        'tf_advanced',
        'tf_studio',
        'tf_preview',
        'tf_output',
        'tf_add_fonts',
        'tf_source',
        'tf_google_access',
        'tf_adobe_project'
    ];
    const trackedUiTabGroups = new Set(['studio', 'preview', 'output', 'add-font']);
    const trackedUiDisclosureTargets = new Set([
        'tasty-fonts-role-preview-panel',
        'tasty-fonts-role-advanced-panel',
        'tasty-fonts-add-font-panel',
        'tasty-fonts-google-access-panel',
        'tasty-fonts-adobe-project-panel'
    ]);
    const staticStrings = {
        previewFallback: __('The quick brown fox jumps over the lazy dog. 1234567890', 'tasty-fonts'),
        importPreviewSample: __('Aa Bb Cc Dd Ee Ff Gg Hh\n0123456789', 'tasty-fonts'),
        searching: __('Searching Google Fonts…', 'tasty-fonts'),
        searchEmpty: __('No Google Fonts families matched that search.', 'tasty-fonts'),
        searchResultSelectLabel: __('Select %s', 'tasty-fonts'),
        searchResultInLibrary: __('In Library', 'tasty-fonts'),
        searchDisabled: __('Add a Google Fonts API key above to enable search, or use manual import below.', 'tasty-fonts'),
        bunnySearching: __('Searching Bunny Fonts…', 'tasty-fonts'),
        bunnySearchEmpty: __('No Bunny Fonts families matched that search.', 'tasty-fonts'),
        selectFamily: __('Select a family from search results or type one manually.', 'tasty-fonts'),
        bunnySelectFamily: __('Type a Bunny Fonts family name before importing.', 'tasty-fonts'),
        bunnyImportFamilyEmpty: __('Choose a Bunny family or type one manually.', 'tasty-fonts'),
        importFamilyEmpty: __('Choose a Google family or type one manually.', 'tasty-fonts'),
        importPreviewEmpty: __('Preview appears here after you choose a family.', 'tasty-fonts'),
        importing: __('Saving the selected Google delivery…', 'tasty-fonts'),
        importError: __('The Google Fonts import failed.', 'tasty-fonts'),
        bunnyImportError: __('The Bunny Fonts import failed.', 'tasty-fonts'),
        importNoVariants: __('Select at least one variant to import.', 'tasty-fonts'),
        bunnyImportSubmitting: __('Saving the selected Bunny delivery…', 'tasty-fonts'),
        bunnyImportPreviewEmpty: __('Preview appears here after you choose a Bunny family.', 'tasty-fonts'),
        bunnyImportBusy: __('Importing Bunny Fonts…', 'tasty-fonts'),
        bunnyImportSuccess: __('Bunny Fonts imported successfully. Reloading…', 'tasty-fonts'),
        importButtonIdle: __('Add to Library', 'tasty-fonts'),
        importButtonBusy: __('Importing…', 'tasty-fonts'),
        saveDeliverySelfHosted: __('Add Self-Hosted', 'tasty-fonts'),
        saveDeliveryGoogleCdn: __('Add Google CDN', 'tasty-fonts'),
        saveDeliveryBunnyCdn: __('Add Bunny CDN', 'tasty-fonts'),
        importEstimateSize: __('Approx. +%1$s WOFF2', 'tasty-fonts'),
        importSelectionSummaryEmpty: __('0 Variants Selected', 'tasty-fonts'),
        importSelectionSummaryAvailable: __('%1$d of %2$d Variants Selected', 'tasty-fonts'),
        uploadSubmitting: __('Uploading font files…', 'tasty-fonts'),
        uploadProgress: __('Uploading files… %1$d%%', 'tasty-fonts'),
        uploadSuccess: __('Upload complete. Refreshing the library…', 'tasty-fonts'),
        uploadError: __('The font upload failed.', 'tasty-fonts'),
        uploadNoFile: __('No file chosen', 'tasty-fonts'),
        uploadButtonIdle: __('Upload to Library', 'tasty-fonts'),
        uploadButtonBusy: __('Uploading…', 'tasty-fonts'),
        uploadRowQueued: __('Queued', 'tasty-fonts'),
        uploadRowUploading: __('Uploading…', 'tasty-fonts'),
        uploadRowError: __('Error', 'tasty-fonts'),
        uploadUseDetected: __('Use Detected Values', 'tasty-fonts'),
        uploadDetectedSummary: __('Detected: %1$s / %2$s / %3$s', 'tasty-fonts'),
        uploadDetectedWeightStyle: __('Detected: %1$s / %2$s', 'tasty-fonts'),
        uploadRequiresRows: __('Add at least one upload row before submitting.', 'tasty-fonts'),
        rolesDraftSaved: __('Roles saved.', 'tasty-fonts'),
        rolesDraftSaveError: __('The roles could not be saved.', 'tasty-fonts'),
        roleSaveDisabledNoChanges: __('No draft changes to save.', 'tasty-fonts'),
        roleApplyLiveDisabledNoChanges: __('No live role changes to publish.', 'tasty-fonts'),
        roleApplyLiveDisabledSitewideOff: __('Apply Sitewide is off. Turn it on before publishing role changes.', 'tasty-fonts'),
        roleFallbackOnly: __('Fallback only (%1$s)', 'tasty-fonts'),
        fallbackSaving: __('Saving fallback…', 'tasty-fonts'),
        fallbackSaved: __('Saved fallback for %1$s.', 'tasty-fonts'),
        fallbackSaveError: __('The fallback could not be saved.', 'tasty-fonts'),
        fontDisplaySaving: __('Saving font display…', 'tasty-fonts'),
        fontDisplaySaved: __('Saved font display for %1$s.', 'tasty-fonts'),
        fontDisplaySaveError: __('The font-display override could not be saved.', 'tasty-fonts'),
        familyDeliverySaving: __('Switching live delivery…', 'tasty-fonts'),
        familyDeliverySaved: __('Live delivery updated.', 'tasty-fonts'),
        familyDeliverySaveError: __('The live delivery could not be updated.', 'tasty-fonts'),
        familyPublishStateSaving: __('Updating publish state…', 'tasty-fonts'),
        familyPublishStateSaved: __('Publish state updated.', 'tasty-fonts'),
        familyPublishStateSaveError: __('The publish state could not be updated.', 'tasty-fonts'),
        previewCurrentDraft: __('Current draft', 'tasty-fonts'),
        previewLiveSitewide: __('Live sitewide', 'tasty-fonts'),
        deliveryDeleteConfirm: __('Delete the "%1$s" delivery from %2$s?', 'tasty-fonts'),
        deliveryDeleteError: __('The delivery profile could not be deleted.', 'tasty-fonts'),
        deleteConfirm: __('Delete "%s" and remove its files from uploads/fonts?', 'tasty-fonts'),
        copied: __('Copied', 'tasty-fonts'),
        activityCountSingle: __('%1$d entry', 'tasty-fonts'),
        activityCountMultiple: __('%1$d entries', 'tasty-fonts'),
        activityCountFilteredSingle: __('%1$d of %2$d entry', 'tasty-fonts'),
        activityCountFilteredMultiple: __('%1$d of %2$d entries', 'tasty-fonts'),
    };

    // Shared helpers
    const slugify = typeof adminContracts.slugify === 'function'
        ? adminContracts.slugify
        : (value) => String(value || '').toLowerCase().replace(/[^a-z0-9\-_]+/g, '-').replace(/^-+|-+$/g, '') || 'font';

    const sanitizeFallback = typeof adminContracts.sanitizeFallback === 'function'
        ? adminContracts.sanitizeFallback
        : (fallback, defaultValue = 'sans-serif') => {
            const sanitized = String(fallback || '')
                .trim()
                .replace(/[^a-zA-Z0-9,\- "'`]+/g, '')
                .replace(/\s*,\s*/g, ', ')
                .replace(/\s+/g, ' ')
                .replace(/^[,\s]+|[,\s]+$/g, '');

            return sanitized || defaultValue;
        };

    const escapeFontFamily = typeof adminContracts.escapeFontFamily === 'function'
        ? adminContracts.escapeFontFamily
        : (family) => String(family || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');

    function buildStack(family, fallback, defaultFallback = 'sans-serif') {
        const sanitizedFallback = sanitizeFallback(fallback, defaultFallback);
        const trimmedFamily = String(family || '').trim();

        return trimmedFamily ? `"${escapeFontFamily(trimmedFamily)}", ${sanitizedFallback}` : sanitizedFallback;
    }

    function buildFontVariable(family) {
        const trimmedFamily = String(family || '').trim();

        return trimmedFamily ? `var(--font-${slugify(trimmedFamily)})` : '';
    }

    function previewRoleName(family, fallback, defaultFallback = 'sans-serif', fallbackOnly = false) {
        const trimmedFamily = String(family || '').trim();

        if (trimmedFamily) {
            return trimmedFamily;
        }

        if (!fallbackOnly) {
            return '';
        }

        return formatMessage(
            getString('roleFallbackOnly', 'Fallback only (%1$s)'),
            [sanitizeFallback(fallback, defaultFallback)]
        );
    }

    function buildRoleSelectionKey(roleKeys) {
        return ['heading', 'body', 'monospace'].filter((roleKey) => roleKeys.includes(roleKey)).join('-');
    }

    function normalizeRoleState(input = {}) {
        return {
            heading: String(input.heading || '').trim(),
            body: String(input.body || '').trim(),
            monospace: monospaceRoleEnabled ? String(input.monospace || '').trim() : '',
            headingFallback: sanitizeFallback(input.headingFallback || input.heading_fallback, 'sans-serif'),
            bodyFallback: sanitizeFallback(input.bodyFallback || input.body_fallback, 'sans-serif'),
            monospaceFallback: sanitizeFallback(input.monospaceFallback || input.monospace_fallback, 'monospace'),
        };
    }

    function buildRoleDataFromValues(values = {}) {
        const normalized = normalizeRoleState(values);
        const heading = normalized.heading;
        const body = normalized.body;
        const monospace = monospaceRoleEnabled ? normalized.monospace : '';

        return {
            includeMonospace: monospaceRoleEnabled,
            heading,
            body,
            monospace,
            headingFallback: normalized.headingFallback,
            bodyFallback: normalized.bodyFallback,
            monospaceFallback: normalized.monospaceFallback,
            headingSlug: heading ? slugify(heading) : '',
            bodySlug: body ? slugify(body) : '',
            monospaceSlug: monospace ? slugify(monospace) : '',
            headingVariable: 'var(--font-heading)',
            bodyVariable: 'var(--font-body)',
            monospaceVariable: 'var(--font-monospace)',
            headingFamilyVariable: heading ? buildFontVariable(heading) : buildStack('', normalized.headingFallback),
            bodyFamilyVariable: body ? buildFontVariable(body) : buildStack('', normalized.bodyFallback),
            monospaceFamilyVariable: monospace
                ? buildFontVariable(monospace)
                : buildStack('', normalized.monospaceFallback, 'monospace'),
            headingStack: buildStack(heading, normalized.headingFallback),
            bodyStack: buildStack(body, normalized.bodyFallback),
            monospaceStack: buildStack(monospace, normalized.monospaceFallback, 'monospace')
        };
    }

    function previewBootstrap() {
        const bootstrapConfig = config.previewBootstrap && typeof config.previewBootstrap === 'object'
            ? config.previewBootstrap
            : {};

        return {
            roles: normalizeRoleState(bootstrapConfig.roles || {}),
            appliedRoles: normalizeRoleState(bootstrapConfig.appliedRoles || {}),
            baselineSource: bootstrapConfig.baselineSource === 'live_sitewide' ? 'live_sitewide' : 'draft',
            baselineLabel: String(
                bootstrapConfig.baselineLabel
                || (bootstrapConfig.baselineSource === 'live_sitewide'
                    ? getString('previewLiveSitewide', 'Live sitewide')
                    : getString('previewCurrentDraft', 'Current draft'))
            ),
        };
    }

    function selectedRoleKeysForFamily(family, data) {
        const roleKeys = [];

        if (family && family === data.heading) {
            roleKeys.push('heading');
        }

        if (family && family === data.body) {
            roleKeys.push('body');
        }

        if (data.includeMonospace && family && family === data.monospace) {
            roleKeys.push('monospace');
        }

        return roleKeys;
    }

    function getString(key, fallback) {
        return runtimeStrings[key] || staticStrings[key] || fallback;
    }

    function getElementValue(element, fallback) {
        return element ? element.value : fallback;
    }

    function formatMessage(template, replacements) {
        let sequentialIndex = 0;

        if (wpSprintf) {
            try {
                return wpSprintf(template || '', ...replacements);
            } catch (error) {
                // Fall back to the lightweight formatter below.
            }
        }

        return (template || '').replace(/%(?:(\d+)\$)?[sd]/g, (match, index) => {
            const replacementIndex = index ? Number(index) - 1 : sequentialIndex++;
            const replacement = replacements[replacementIndex];

            return replacement === undefined ? match : String(replacement);
        });
    }

    function formatPluralMessage(single, plural, count, replacements = []) {
        return formatMessage(_n(single, plural, count, 'tasty-fonts'), replacements);
    }

    function getApiMessage(payload, fallback) {
        return payload && typeof payload.message === 'string' && payload.message ? payload.message : fallback;
    }

    function getErrorMessage(error, fallback) {
        return error instanceof Error && error.message ? error.message : fallback;
    }

    function hasRestConfig() {
        return Boolean(config.restUrl && config.restNonce);
    }

    function getRoutePath(key, fallback) {
        const route = config.routes && typeof config.routes[key] === 'string' ? config.routes[key] : '';

        return route || fallback;
    }

    function buildRestUrl(path, query) {
        if (!config.restUrl || !path) {
            return '';
        }

        const url = new URL(String(path).replace(/^\/+/, ''), config.restUrl);

        Object.entries(query || {}).forEach(([key, value]) => {
            if (value === undefined || value === null || value === '') {
                return;
            }

            if (Array.isArray(value)) {
                value.forEach((item) => url.searchParams.append(key, String(item)));
                return;
            }

            url.searchParams.set(key, String(value));
        });

        return url.toString();
    }

    async function readApiPayload(response) {
        const rawBody = await response.text();

        if (!rawBody) {
            return {};
        }

        try {
            return JSON.parse(rawBody);
        } catch (error) {
            return {};
        }
    }

    async function requestJson(path, { method = 'GET', query = {}, body = null, fallbackMessage = '' } = {}) {
        const url = buildRestUrl(path, query);

        if (!url) {
            throw new Error(fallbackMessage || 'Request failed.');
        }

        const headers = {
            Accept: 'application/json',
            'X-WP-Nonce': config.restNonce || ''
        };
        const options = {
            method,
            headers
        };

        if (body !== null) {
            headers['Content-Type'] = 'application/json; charset=UTF-8';
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);
        const payload = await readApiPayload(response);

        if (!response.ok) {
            throw new Error(getApiMessage(payload, fallbackMessage || 'Request failed.'));
        }

        return payload && typeof payload === 'object' ? payload : {};
    }

    function getSessionStorage() {
        try {
            return window.sessionStorage || null;
        } catch (error) {
            return null;
        }
    }

    function savePendingUiState(state) {
        const storage = getSessionStorage();

        if (!storage || !state || typeof state !== 'object') {
            return;
        }

        try {
            storage.setItem(pendingUiStateKey, JSON.stringify(state));
        } catch (error) {
            // Ignore storage failures and continue without persisted UI state.
        }
    }

    function consumePendingUiState() {
        const storage = getSessionStorage();

        if (!storage) {
            return null;
        }

        try {
            const rawValue = storage.getItem(pendingUiStateKey);

            if (!rawValue) {
                return null;
            }

            storage.removeItem(pendingUiStateKey);

            return JSON.parse(rawValue);
        } catch (error) {
            return null;
        }
    }

    function getDisclosureToggleTargetId(toggle) {
        return toggle ? String(toggle.getAttribute('data-disclosure-toggle') || '').trim() : '';
    }

    function disclosureToggleByTargetId(targetId) {
        if (!targetId) {
            return null;
        }

        return disclosureToggles.find((toggle) => getDisclosureToggleTargetId(toggle) === targetId) || null;
    }

    function isDisclosureExpanded(toggle) {
        return Boolean(toggle) && toggle.getAttribute('aria-expanded') === 'true';
    }

    function isTrackedDisclosureToggle(toggle) {
        return trackedUiDisclosureTargets.has(getDisclosureToggleTargetId(toggle));
    }

    function isTrackedTabGroup(group) {
        return trackedUiTabGroups.has(String(group || '').trim());
    }

    function isAllowedTabKey(group, key) {
        if (!group || !key) {
            return false;
        }

        return tabButtonsForGroup(group).some((tab) => tab.getAttribute('data-tab-target') === key);
    }

    function trackedUiStateHas(state, key) {
        return Boolean(state) && Object.prototype.hasOwnProperty.call(state, key);
    }

    function defaultTrackedUiFlag(key) {
        return trackedUiStateHas(defaultTrackedUiState, key) ? Boolean(defaultTrackedUiState[key]) : false;
    }

    function defaultTrackedUiTabKey(group) {
        if (!defaultTrackedUiState || typeof defaultTrackedUiState !== 'object') {
            return '';
        }

        switch (group) {
            case 'studio':
                return String(defaultTrackedUiState.studio || '');
            case 'preview':
                return String(defaultTrackedUiState.preview || '');
            case 'output':
                return String(defaultTrackedUiState.output || '');
            case 'add-font':
                return String(defaultTrackedUiState.source || '');
            default:
                return '';
        }
    }

    function firstTabKeyForGroup(group) {
        const firstButton = tabButtonsForGroup(group)[0];

        return firstButton ? String(firstButton.getAttribute('data-tab-target') || '') : '';
    }

    function resolveTrackedTabKey(group, requestedKey) {
        if (isAllowedTabKey(group, requestedKey)) {
            return requestedKey;
        }

        const defaultKey = defaultTrackedUiTabKey(group);

        if (isAllowedTabKey(group, defaultKey)) {
            return defaultKey;
        }

        const firstKey = firstTabKeyForGroup(group);

        return isAllowedTabKey(group, firstKey) ? firstKey : '';
    }

    function readTrackedUiState(locationValue) {
        const url = locationValue instanceof URL
            ? locationValue
            : new URL(locationValue && typeof locationValue.href === 'string' ? locationValue.href : window.location.href);
        const params = url.searchParams;
        const state = {};

        const studio = String(params.get('tf_studio') || '');
        const previewToggle = disclosureToggleByTargetId('tasty-fonts-role-preview-panel');
        const advancedToggle = disclosureToggleByTargetId('tasty-fonts-role-advanced-panel');

        if (params.get('tf_advanced') === '1') {
            if (studio === 'preview' && previewToggle) {
                state.previewOpen = true;
            } else if (advancedToggle) {
                state.advancedOpen = true;
            }
        }

        if (state.advancedOpen && isAllowedTabKey('studio', studio)) {
            state.studio = studio;
        }

        const preview = String(params.get('tf_preview') || '');

        if (state.previewOpen && isAllowedTabKey('preview', preview)) {
            state.preview = preview;
        }

        const output = String(params.get('tf_output') || '');

        if (state.studio === 'snippets' && isAllowedTabKey('output', output)) {
            state.output = output;
        }

        if (params.get('tf_add_fonts') === '1' && addFontsPanelToggle) {
            state.addFontsOpen = true;
        }

        const source = String(params.get('tf_source') || '');

        if (state.addFontsOpen && isAllowedTabKey('add-font', source)) {
            state.source = source;
        }

        if (
            state.addFontsOpen
            && state.source === 'google'
            && params.get('tf_google_access') === '1'
            && disclosureToggleByTargetId('tasty-fonts-google-access-panel')
        ) {
            state.googleAccessOpen = true;
        }

        if (
            state.addFontsOpen
            && state.source === 'adobe'
            && params.get('tf_adobe_project') === '1'
            && disclosureToggleByTargetId('tasty-fonts-adobe-project-panel')
        ) {
            state.adobeProjectOpen = true;
        }

        return state;
    }

    function applyTrackedUiState(state) {
        const nextState = state && typeof state === 'object' ? state : {};
        const previewToggle = disclosureToggleByTargetId('tasty-fonts-role-preview-panel');
        const advancedToggle = disclosureToggleByTargetId('tasty-fonts-role-advanced-panel');
        const addFontsOpen = trackedUiStateHas(nextState, 'addFontsOpen')
            ? Boolean(nextState.addFontsOpen)
            : defaultTrackedUiFlag('addFontsOpen');
        const previewOpen = trackedUiStateHas(nextState, 'previewOpen')
            ? Boolean(nextState.previewOpen)
            : defaultTrackedUiFlag('previewOpen');
        const advancedOpen = !previewOpen && (trackedUiStateHas(nextState, 'advancedOpen')
            ? Boolean(nextState.advancedOpen)
            : defaultTrackedUiFlag('advancedOpen'));
        const studio = resolveTrackedTabKey(
            'studio',
            trackedUiStateHas(nextState, 'studio') ? String(nextState.studio || '') : defaultTrackedUiTabKey('studio')
        );
        const preview = resolveTrackedTabKey(
            'preview',
            trackedUiStateHas(nextState, 'preview') ? String(nextState.preview || '') : defaultTrackedUiTabKey('preview')
        );
        const output = resolveTrackedTabKey(
            'output',
            trackedUiStateHas(nextState, 'output') ? String(nextState.output || '') : defaultTrackedUiTabKey('output')
        );
        const source = resolveTrackedTabKey(
            'add-font',
            trackedUiStateHas(nextState, 'source') ? String(nextState.source || '') : defaultTrackedUiTabKey('add-font')
        );
        const googleAccessOpen = addFontsOpen && source === 'google'
            ? (trackedUiStateHas(nextState, 'googleAccessOpen')
                ? Boolean(nextState.googleAccessOpen)
                : defaultTrackedUiFlag('googleAccessOpen'))
            : false;
        const adobeProjectOpen = addFontsOpen && source === 'adobe'
            ? (trackedUiStateHas(nextState, 'adobeProjectOpen')
                ? Boolean(nextState.adobeProjectOpen)
                : defaultTrackedUiFlag('adobeProjectOpen'))
            : false;

        if (previewToggle) {
            setDisclosureState(previewToggle, previewOpen);
        }

        if (advancedToggle) {
            setDisclosureState(advancedToggle, advancedOpen);
        }

        if (advancedOpen && studio) {
            activateTabGroup('studio', studio);
        }

        if (previewOpen && preview) {
            activateTabGroup('preview', preview);
        }

        if (output) {
            activateTabGroup('output', output);
        }

        if (addFontsPanelToggle) {
            setDisclosureState(addFontsPanelToggle, addFontsOpen);
        }

        if (source) {
            activateTabGroup('add-font', source);
        }

        const googleAccessToggle = disclosureToggleByTargetId('tasty-fonts-google-access-panel');

        if (googleAccessToggle) {
            setDisclosureState(googleAccessToggle, googleAccessOpen);
        }

        const adobeProjectToggle = disclosureToggleByTargetId('tasty-fonts-adobe-project-panel');

        if (adobeProjectToggle) {
            setDisclosureState(adobeProjectToggle, adobeProjectOpen);
        }
    }

    function hasTrackedUiState(state) {
        return Boolean(state) && typeof state === 'object' && Object.keys(state).length > 0;
    }

    function resetInitialScrollPosition() {
        if (typeof window.scrollTo !== 'function') {
            return;
        }

        if (window.history && 'scrollRestoration' in window.history) {
            window.history.scrollRestoration = 'manual';
        }

        window.requestAnimationFrame(() => {
            window.scrollTo(0, 0);

            window.setTimeout(() => {
                window.scrollTo(0, 0);
            }, 0);
        });
    }

    function revealDisclosurePanel(targetId, anchor = null) {
        if (!targetId) {
            return;
        }

        const panel = document.getElementById(targetId);

        if (!panel || typeof window.scrollTo !== 'function') {
            return;
        }

        window.setTimeout(() => {
            const anchorElement = anchor && typeof anchor.closest === 'function'
                ? (anchor.closest('.tasty-fonts-role-command-deck') || anchor.closest('.tasty-fonts-role-command-card--utilities'))
                : null;
            const scrollTarget = anchorElement || panel;
            const panelTop = scrollTarget.getBoundingClientRect().top + window.scrollY;
            const topOffset = 24;

            window.scrollTo({
                top: Math.max(0, panelTop - topOffset),
                left: 0,
                behavior: 'smooth',
            });
        }, 80);
    }

    function captureTrackedUiState() {
        const state = {};
        const previewToggle = disclosureToggleByTargetId('tasty-fonts-role-preview-panel');
        const advancedToggle = disclosureToggleByTargetId('tasty-fonts-role-advanced-panel');
        const googleAccessToggle = disclosureToggleByTargetId('tasty-fonts-google-access-panel');
        const adobeProjectToggle = disclosureToggleByTargetId('tasty-fonts-adobe-project-panel');

        if (isDisclosureExpanded(previewToggle)) {
            state.previewOpen = true;

            const preview = activeTabKeyForGroup('preview');

            if (isAllowedTabKey('preview', preview)) {
                state.preview = preview;
            }
        } else if (isDisclosureExpanded(advancedToggle)) {
            state.advancedOpen = true;

            const studio = activeTabKeyForGroup('studio');

            if (isAllowedTabKey('studio', studio)) {
                state.studio = studio;

                if (studio === 'snippets') {
                    const output = activeTabKeyForGroup('output');

                    if (isAllowedTabKey('output', output)) {
                        state.output = output;
                    }
                }
            }
        }

        if (isDisclosureExpanded(addFontsPanelToggle)) {
            state.addFontsOpen = true;

            const source = activeTabKeyForGroup('add-font');

            if (isAllowedTabKey('add-font', source)) {
                state.source = source;

                if (source === 'google' && isDisclosureExpanded(googleAccessToggle)) {
                    state.googleAccessOpen = true;
                }

                if (source === 'adobe' && isDisclosureExpanded(adobeProjectToggle)) {
                    state.adobeProjectOpen = true;
                }
            }
        }

        return state;
    }

    function syncTrackedUiUrl(historyMode = 'replace') {
        if (!window.history) {
            return;
        }

        const method = historyMode === 'push' ? 'pushState' : 'replaceState';

        if (typeof window.history[method] !== 'function') {
            return;
        }

        const currentUrl = new URL(window.location.href);
        const nextUrl = new URL(currentUrl.toString());
        const state = captureTrackedUiState();

        trackedUiQueryKeys.forEach((key) => {
            nextUrl.searchParams.delete(key);
        });

        if (state.previewOpen) {
            nextUrl.searchParams.set('tf_advanced', '1');
            nextUrl.searchParams.set('tf_studio', 'preview');
        } else if (state.advancedOpen) {
            nextUrl.searchParams.set('tf_advanced', '1');
        }

        if (state.advancedOpen && state.studio) {
            nextUrl.searchParams.set('tf_studio', state.studio);
        }

        if (state.previewOpen && state.preview) {
            nextUrl.searchParams.set('tf_preview', state.preview);
        }

        if (state.output) {
            nextUrl.searchParams.set('tf_output', state.output);
        }

        if (state.addFontsOpen) {
            nextUrl.searchParams.set('tf_add_fonts', '1');
        }

        if (state.source) {
            nextUrl.searchParams.set('tf_source', state.source);
        }

        if (state.googleAccessOpen) {
            nextUrl.searchParams.set('tf_google_access', '1');
        }

        if (state.adobeProjectOpen) {
            nextUrl.searchParams.set('tf_adobe_project', '1');
        }

        if (nextUrl.toString() === currentUrl.toString()) {
            return;
        }

        window.history[method](window.history.state, '', nextUrl.toString());
    }

    function handleTrackedUiPopState() {
        applyTrackedUiState(readTrackedUiState(window.location));
    }

    function deliveryButtonLabel(mode, provider) {
        if (mode === 'cdn') {
            return provider === 'bunny'
                ? getString('saveDeliveryBunnyCdn', 'Add Bunny CDN')
                : getString('saveDeliveryGoogleCdn', 'Add Google CDN');
        }

        return getString('saveDeliverySelfHosted', 'Add Self-Hosted');
    }

    function formatActivityCount(visibleCount, totalCount) {
        if (visibleCount === totalCount) {
            return formatMessage(
                totalCount === 1
                    ? getString('activityCountSingle', '%1$d entry')
                    : getString('activityCountMultiple', '%1$d entries'),
                [totalCount]
            );
        }

        return formatMessage(
            totalCount === 1
                ? getString('activityCountFilteredSingle', '%1$d of %2$d entry')
                : getString('activityCountFilteredMultiple', '%1$d of %2$d entries'),
            [visibleCount, totalCount]
        );
    }

    function googlePreviewStylesheet() {
        return document.getElementById('tasty-fonts-google-preview-stylesheet');
    }

    function bunnyPreviewStylesheet() {
        return document.getElementById('tasty-fonts-bunny-preview-stylesheet');
    }

    function bunnySearchPreviewStylesheet() {
        return document.getElementById('tasty-fonts-bunny-search-preview-stylesheet');
    }

    function removeGooglePreviewStylesheet() {
        const stylesheet = googlePreviewStylesheet();

        if (stylesheet) {
            stylesheet.remove();
        }
    }

    function removeBunnyPreviewStylesheet() {
        const stylesheet = bunnyPreviewStylesheet();

        if (stylesheet) {
            stylesheet.remove();
        }
    }

    function removeBunnySearchPreviewStylesheet() {
        const stylesheet = bunnySearchPreviewStylesheet();

        if (stylesheet) {
            stylesheet.remove();
        }
    }

    function googlePreviewFallback(category) {
        switch ((category || '').toLowerCase()) {
            case 'serif':
                return 'serif';
            case 'monospace':
                return 'monospace';
            case 'handwriting':
                return 'cursive';
            case 'display':
                return 'sans-serif';
            default:
                return 'sans-serif';
        }
    }

    function googlePreviewText() {
        const rawText = getElementValue(previewTextInput, '').trim() || getString('previewFallback', 'The quick brown fox jumps over the lazy dog.');

        return rawText.replace(/\s+/g, ' ').trim().slice(0, 36);
    }

    function googleSelectedPreviewText() {
        const rawText = getElementValue(previewTextInput, '').trim();

        if (rawText) {
            return rawText.replace(/\s+/g, ' ').trim().slice(0, 72);
        }

        return getString('importPreviewSample', 'Aa Bb Cc Dd Ee Ff Gg Hh\n0123456789');
    }

    function googlePreviewStylesheetText() {
        return [googlePreviewText(), googleSelectedPreviewText()]
            .join(' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function normalizeHostedVariantTokens(value) {
        return normalizeVariantTokens(value).filter((token) => /^(regular|italic|[1-9]00|[1-9]00italic)$/i.test(token));
    }

    function buildHostedCssAxes(variants) {
        const axes = [];

        normalizeHostedVariantTokens(Array.isArray(variants) ? variants.join(',') : variants).forEach((token) => {
            const normalized = String(token).toLowerCase();

            if (normalized === 'regular') {
                axes.push('0,400');
                return;
            }

            if (normalized === 'italic') {
                axes.push('1,400');
                return;
            }

            const match = normalized.match(/^([1-9]00)(italic)?$/);

            if (!match) {
                return;
            }

            axes.push(`${match[2] ? '1' : '0'},${match[1]}`);
        });

        return Array.from(new Set(axes));
    }

    function buildHostedCssUrl(baseUrl, family, variants) {
        const familyName = String(family || '').trim();

        if (!familyName) {
            return '';
        }

        const familyQuery = encodeURIComponent(familyName).replace(/%20/g, '+');
        const axes = buildHostedCssAxes(variants);
        let url = `${baseUrl}?family=${familyQuery}`;

        if (axes.length) {
            url += `:ital,wght@${axes.join(';')}`;
        }

        return `${url}&display=swap`;
    }

    function updateGooglePreviewStylesheet(items) {
        const families = Array.from(
            new Set(
                [selectedSearchFamily ? selectedSearchFamily.family : '']
                    .concat(
                        (items || [])
                            .map((item) => (item && item.family ? String(item.family).trim() : ''))
                    )
                    .filter(Boolean)
            )
        ).slice(0, 8);

        if (!families.length) {
            removeGooglePreviewStylesheet();
            return;
        }

        const sample = googlePreviewStylesheetText();
        const queryParts = families.map((family) => `family=${encodeURIComponent(family).replace(/%20/g, '+')}`);
        queryParts.push(`text=${encodeURIComponent(sample)}`);
        queryParts.push('display=swap');

        const href = `https://fonts.googleapis.com/css2?${queryParts.join('&')}`;
        let stylesheet = googlePreviewStylesheet();

        if (!stylesheet) {
            stylesheet = document.createElement('link');
            stylesheet.id = 'tasty-fonts-google-preview-stylesheet';
            stylesheet.rel = 'stylesheet';
            document.head.appendChild(stylesheet);
        }

        if (stylesheet.href !== href) {
            stylesheet.href = href;
        }
    }

    function updateBunnyPreviewStylesheet(family, variants) {
        const href = buildHostedCssUrl('https://fonts.bunny.net/css2', family, variants);

        if (!href) {
            removeBunnyPreviewStylesheet();
            return;
        }

        let stylesheet = bunnyPreviewStylesheet();

        if (!stylesheet) {
            stylesheet = document.createElement('link');
            stylesheet.id = 'tasty-fonts-bunny-preview-stylesheet';
            stylesheet.rel = 'stylesheet';
            document.head.appendChild(stylesheet);
        }

        if (stylesheet.href !== href) {
            stylesheet.href = href;
        }
    }

    function updateBunnySearchPreviewStylesheet(items) {
        const families = Array.from(
            new Set(
                (items || [])
                    .map((item) => (item && item.family ? String(item.family).trim() : ''))
                    .filter(Boolean)
            )
        ).slice(0, 8);

        if (!families.length) {
            removeBunnySearchPreviewStylesheet();
            return;
        }

        const sample = googlePreviewText();
        const queryParts = families.map((family) => `family=${encodeURIComponent(family).replace(/%20/g, '+')}`);
        queryParts.push(`text=${encodeURIComponent(sample)}`);
        queryParts.push('display=swap');

        const href = `https://fonts.bunny.net/css2?${queryParts.join('&')}`;
        let stylesheet = bunnySearchPreviewStylesheet();

        if (!stylesheet) {
            stylesheet = document.createElement('link');
            stylesheet.id = 'tasty-fonts-bunny-search-preview-stylesheet';
            stylesheet.rel = 'stylesheet';
            document.head.appendChild(stylesheet);
        }

        if (stylesheet.href !== href) {
            stylesheet.href = href;
        }
    }

    function reloadPageSoon(delay = 900, pendingUiState = null) {
        if (pendingUiState) {
            savePendingUiState(pendingUiState);
        }

        window.setTimeout(() => {
            window.location.reload();
        }, delay);
    }

    function roleFieldSnapshot() {
        const snapshot = {
            heading: getElementValue(roleHeading, ''),
            body: getElementValue(roleBody, ''),
            headingFallback: getElementValue(roleHeadingFallback, 'sans-serif'),
            bodyFallback: getElementValue(roleBodyFallback, 'sans-serif')
        };

        if (monospaceRoleEnabled) {
            snapshot.monospace = getElementValue(roleMonospace, '');
            snapshot.monospaceFallback = getElementValue(roleMonospaceFallback, 'monospace');
        }

        return snapshot;
    }

    function restoreRoleFieldSnapshot(snapshot) {
        if (!snapshot) {
            return;
        }

        if (roleHeading) {
            roleHeading.value = snapshot.heading || '';
        }

        if (roleBody) {
            roleBody.value = snapshot.body || '';
        }

        if (roleHeadingFallback) {
            roleHeadingFallback.value = snapshot.headingFallback || 'sans-serif';
        }

        if (roleBodyFallback) {
            roleBodyFallback.value = snapshot.bodyFallback || 'sans-serif';
        }

        if (monospaceRoleEnabled && roleMonospace) {
            roleMonospace.value = snapshot.monospace || '';
        }

        if (monospaceRoleEnabled && roleMonospaceFallback) {
            roleMonospaceFallback.value = snapshot.monospaceFallback || 'monospace';
        }
    }

    function setRoleDraftSavingState(isSaving) {
        roleDraftSaveInFlight = isSaving;

        roleAssignButtons.forEach((button) => {
            button.disabled = isSaving;
            button.setAttribute('aria-busy', isSaving ? 'true' : 'false');
        });

        [previewSaveDraftButton, previewApplyLiveButton].forEach((button) => {
            if (!button) {
                return;
            }

            button.disabled = isSaving;
            button.setAttribute('aria-busy', isSaving ? 'true' : 'false');
        });

        if (roleForm) {
            roleForm.classList.toggle('is-saving', isSaving);
            roleForm.setAttribute('aria-busy', isSaving ? 'true' : 'false');
        }

        roleFormSubmitButtons.forEach((button) => {
            button.disabled = isSaving;
            button.setAttribute('aria-busy', isSaving ? 'true' : 'false');
        });

        if (!isSaving) {
            syncRoleActionButtonStates();
        }
    }

    function buildRoleDeploymentTooltip(deployment) {
        const title = deployment && deployment.title ? String(deployment.title).trim() : '';
        const copy = deployment && deployment.copy ? String(deployment.copy).trim() : '';

        if (!title) {
            return copy;
        }

        if (!copy) {
            return title;
        }

        return `${title}. ${copy}`;
    }

    function syncRoleDeploymentState(deployment) {
        if (!roleDeployment || !deployment) {
            return;
        }

        const badgeClass = deployment.badge_class || '';
        const tooltip = buildRoleDeploymentTooltip(deployment);

        roleDeployment.classList.remove('is-success', 'is-warning', 'is-accent');

        if (badgeClass) {
            roleDeployment.classList.add(badgeClass);
        }

        if (roleDeploymentPill) {
            roleDeploymentPill.className = `tasty-fonts-pill tasty-fonts-pill--status tasty-fonts-pill--interactive tasty-fonts-pill--help tasty-fonts-role-status-pill${badgeClass ? ` ${badgeClass}` : ''}`;
            setPassiveHelpTooltip(roleDeploymentPill, tooltip);
        }

        if (roleDeploymentBadge) {
            roleDeploymentBadge.textContent = deployment.badge || '';
        }

        if (roleDeploymentAnnouncement) {
            roleDeploymentAnnouncement.textContent = tooltip;
        }

        if (activeHelpButton === roleDeploymentPill && helpTooltipLayer && !helpTooltipLayer.hidden) {
            helpTooltipLayer.textContent = tooltip;
            positionHelpTooltip(roleDeploymentPill);
        }
    }

    function getFallbackSelector(family) {
        return document.querySelector(`.tasty-fonts-fallback-selector[data-font-family="${CSS.escape(family)}"]`);
    }

    function updateSelectedFamilyLabel(familyName) {
        if (!selectedFamily) {
            return;
        }

        selectedFamily.textContent = familyName || getString('importFamilyEmpty', 'Choose a Google family or type one manually.');
    }

    function currentGoogleImportFamily() {
        return manualFamily ? manualFamily.value.trim() : '';
    }

    function currentGoogleDeliveryMode() {
        const selected = googleDeliveryModes.find((input) => input.checked);

        return selected ? String(selected.value || 'self_hosted') : 'self_hosted';
    }

    function currentBunnyImportFamily() {
        return bunnyFamily ? bunnyFamily.value.trim() : '';
    }

    function currentBunnyDeliveryMode() {
        const selected = bunnyDeliveryModes.find((input) => input.checked);

        return selected ? String(selected.value || 'self_hosted') : 'self_hosted';
    }

    function normalizeFamilyKey(value) {
        return String(value || '').trim().toLowerCase();
    }

    function familySlug(value) {
        return normalizeFamilyKey(slugify(String(value || '')));
    }

    function libraryFamilySlugs() {
        return new Set(
            Array.from(document.querySelectorAll('[data-font-row]'))
                .map((row) => normalizeFamilyKey(row.getAttribute('data-font-slug') || familySlug(row.getAttribute('data-font-family') || '')))
                .filter(Boolean)
        );
    }

    function isFamilyInLibrary(familyName, explicitSlug = '') {
        const targetSlug = normalizeFamilyKey(explicitSlug || familySlug(familyName));

        if (!targetSlug) {
            return false;
        }

        return libraryFamilySlugs().has(targetSlug);
    }

    function flashElement(element, className = 'is-emphasized', duration = 2200) {
        if (!element) {
            return;
        }

        if (element._tastyFontsFlashTimer) {
            window.clearTimeout(element._tastyFontsFlashTimer);
            element._tastyFontsFlashTimer = 0;
        }

        element.classList.remove(className);
        void element.offsetWidth;
        element.classList.add(className);
        element._tastyFontsFlashTimer = window.setTimeout(() => {
            element.classList.remove(className);
            element._tastyFontsFlashTimer = 0;
        }, duration);
    }

    function highlightLibraryRow(familySlugValue) {
        const normalizedSlug = normalizeFamilyKey(familySlugValue);

        if (!normalizedSlug) {
            return;
        }

        const row = document.querySelector(`[data-font-row][data-font-slug="${CSS.escape(normalizedSlug)}"]`);

        if (!row) {
            return;
        }

        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        flashElement(row);
    }

    function highlightRoleSelector(role) {
        const field = role === 'heading'
            ? roleHeading
            : role === 'body'
                ? roleBody
                : roleMonospace;
        const fieldWrap = field ? field.closest('.tasty-fonts-stack-field') : null;

        if (roleStudio) {
            roleStudio.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        if (field) {
            window.setTimeout(() => {
                try {
                    field.focus({ preventScroll: true });
                } catch (error) {
                    field.focus();
                }
            }, 120);
        }

        if (fieldWrap) {
            window.setTimeout(() => {
                flashElement(fieldWrap);
            }, 180);
        }
    }

    function normalizeImportFamilySlug(result, fallbackFamily) {
        if (result && result.family_record && typeof result.family_record.slug === 'string' && result.family_record.slug.trim() !== '') {
            return normalizeFamilyKey(result.family_record.slug);
        }

        if (result && typeof result.family === 'string' && result.family.trim() !== '') {
            return familySlug(result.family);
        }

        return familySlug(fallbackFamily);
    }

    function setRadioGroupValue(inputs, value) {
        let matched = false;

        inputs.forEach((input) => {
            const isMatch = String(input.value || '') === value;
            input.checked = isMatch;
            matched = matched || isMatch;
        });

        return matched;
    }

    function ensureAddFontPanelOpen(panelKey, { syncUrl = false } = {}) {
        if (addFontsPanelToggle && addFontsPanelToggle.getAttribute('aria-expanded') !== 'true') {
            setDisclosureState(addFontsPanelToggle, true);
        }

        activateTabGroup('add-font', panelKey);

        if (syncUrl) {
            syncTrackedUiUrl('push');
        }
    }

    function syncSearchCardSelection(provider, familyName) {
        const resultContainer = provider === 'bunny' ? bunnyResults : googleResults;

        if (!resultContainer) {
            return;
        }

        resultContainer.querySelectorAll('.tasty-fonts-search-card').forEach((card) => {
            const isActive = normalizeFamilyKey(card.dataset.family || '') === normalizeFamilyKey(familyName);
            card.classList.toggle('is-active', isActive);
            card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function prefillSelfHostedMigration(button) {
        const provider = normalizeFamilyKey(button.getAttribute('data-migrate-provider') || '');
        const family = String(button.getAttribute('data-migrate-family') || '').trim();
        const variantsValue = String(button.getAttribute('data-migrate-variants') || '').trim();
        const variants = normalizeVariantTokens(variantsValue);
        const seededVariants = variants.length > 0 ? variants : ['regular'];

        if (!family || (provider !== 'google' && provider !== 'bunny')) {
            return;
        }

        ensureAddFontPanelOpen(provider, { syncUrl: true });

        if (provider === 'google') {
            setRadioGroupValue(googleDeliveryModes, 'self_hosted');

            if (manualFamily) {
                manualFamily.value = family;
            }

            if (manualVariants) {
                manualVariants.value = seededVariants.join(',');
                delete manualVariants.dataset.explicitEmpty;
            }

            selectedSearchFamily = findGoogleFamilyMatch(family);
            syncSearchCardSelection('google', selectedSearchFamily ? selectedSearchFamily.family || family : family);
            renderVariantOptions(
                selectedSearchFamily && Array.isArray(selectedSearchFamily.variants) && selectedSearchFamily.variants.length > 0
                    ? selectedSearchFamily.variants
                    : seededVariants,
                family
            );
            syncImportDeliveryButtons();
            updateGoogleImportSummary();

            if (manualFamily) {
                manualFamily.scrollIntoView({ behavior: 'smooth', block: 'center' });
                window.setTimeout(() => manualFamily.focus(), 120);
            }

            return;
        }

        setRadioGroupValue(bunnyDeliveryModes, 'self_hosted');

        if (bunnyFamily) {
            bunnyFamily.value = family;
        }

        if (bunnyVariants) {
            bunnyVariants.value = seededVariants.join(',');
            delete bunnyVariants.dataset.explicitEmpty;
        }

        selectedBunnySearchFamily = findBunnyFamilyMatch(family) || {
            family,
            slug: familySlug(family),
            variants: seededVariants,
        };
        syncSearchCardSelection('bunny', selectedBunnySearchFamily.family || family);
        renderBunnyVariantOptions(
            selectedBunnySearchFamily && Array.isArray(selectedBunnySearchFamily.variants) && selectedBunnySearchFamily.variants.length > 0
                ? selectedBunnySearchFamily.variants
                : seededVariants,
            family
        );
        syncImportDeliveryButtons();
        updateBunnyImportSummary();

        if (bunnyFamily) {
            bunnyFamily.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.setTimeout(() => bunnyFamily.focus(), 120);
        }
    }

    function applyPendingUiState() {
        const pendingUiState = consumePendingUiState();

        if (!pendingUiState || typeof pendingUiState !== 'object') {
            return false;
        }

        if (pendingUiState.type === 'highlight-library-row') {
            window.setTimeout(() => {
                highlightLibraryRow(pendingUiState.familySlug || '');
            }, 120);
        }

        return true;
    }

    function findGoogleFamilyMatch(familyName) {
        const normalized = String(familyName || '').trim().toLowerCase();

        if (!normalized) {
            return null;
        }

        return searchResults.find((item) => String(item.family || '').trim().toLowerCase() === normalized) || null;
    }

    function matchesBunnyFamilyEntry(item, familyName) {
        const normalized = normalizeFamilyKey(familyName);

        if (!normalized || !item) {
            return false;
        }

        const itemFamily = normalizeFamilyKey(item.family || '');
        const itemSlug = normalizeFamilyKey(item.slug || '');
        const spacedSlug = normalizeFamilyKey(String(item.slug || '').replace(/-/g, ' '));

        return normalized === itemFamily || normalized === itemSlug || normalized === spacedSlug;
    }

    function findBunnyFamilyMatch(familyName) {
        const normalized = normalizeFamilyKey(familyName);

        if (!normalized) {
            return null;
        }

        const exactSearchResult = bunnySearchResults.find((item) => matchesBunnyFamilyEntry(item, normalized)) || null;

        if (exactSearchResult) {
            return exactSearchResult;
        }

        return matchesBunnyFamilyEntry(selectedBunnySearchFamily, normalized) ? selectedBunnySearchFamily : null;
    }

    function approximateVariantTransferSize(variant, category) {
        const baseSize = {
            serif: 30,
            'sans-serif': 26,
            monospace: 24,
            display: 32,
            handwriting: 34,
        };
        const token = String(variant || '').toLowerCase();
        const normalizedCategory = String(category || '').toLowerCase();
        let estimate = baseSize[normalizedCategory] || 28;
        const weightMatch = token.match(/\d{3}/);

        if (token.includes('italic')) {
            estimate += 3;
        }

        if (weightMatch) {
            const weight = Number(weightMatch[0]);

            if (weight >= 700) {
                estimate += 1;
            }

            if (weight <= 200) {
                estimate -= 1;
            }
        }

        return Math.max(18, estimate);
    }

    function formatTransferEstimate(kilobytes) {
        if (kilobytes >= 1024) {
            const megabytes = kilobytes / 1024;

            return `${megabytes >= 10 ? Math.round(megabytes) : megabytes.toFixed(1)} MB`;
        }

        return `${Math.round(kilobytes)} KB`;
    }

    function syncVariantChipState(input) {
        const chip = input ? input.closest('.tasty-fonts-variant-chip') : null;

        if (!chip || !input) {
            return;
        }

        chip.classList.toggle('is-checked', input.checked);
    }

    function normalizeVariantTokens(value) {
        const seen = new Set();

        return String(value || '')
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean)
            .filter((token) => {
                const key = token.toLowerCase();

                if (seen.has(key)) {
                    return false;
                }

                seen.add(key);
                return true;
            });
    }

    function availableVariantInputs() {
        return variantsWrap ? Array.from(variantsWrap.querySelectorAll('input[type="checkbox"]')) : [];
    }

    function availableBunnyVariantInputs() {
        return bunnyVariantsWrap ? Array.from(bunnyVariantsWrap.querySelectorAll('input[type="checkbox"]')) : [];
    }

    function syncManualVariantsInputFromChips() {
        if (!manualVariants) {
            return;
        }

        manualVariants.value = availableVariantInputs()
            .filter((input) => input.checked)
            .map((input) => input.value)
            .join(',');
    }

    function syncVariantChipsFromManualInput() {
        if (!manualVariants) {
            return false;
        }

        const inputs = availableVariantInputs();

        if (!inputs.length) {
            return false;
        }

        const selectedTokens = new Set(normalizeVariantTokens(manualVariants.value).map((token) => token.toLowerCase()));
        const normalizedValues = [];

        syncingGoogleVariants = true;

        inputs.forEach((input) => {
            input.checked = selectedTokens.has(String(input.value).toLowerCase());
            syncVariantChipState(input);

            if (input.checked) {
                normalizedValues.push(input.value);
            }
        });

        manualVariants.value = normalizedValues.join(',');
        syncingGoogleVariants = false;

        return true;
    }

    function bunnySelectedVariantTokens() {
        if (bunnyVariantsWrap && bunnyVariantsWrap.querySelectorAll('input').length > 0) {
            return Array.from(bunnyVariantsWrap.querySelectorAll('input:checked')).map((input) => input.value);
        }

        return normalizeHostedVariantTokens(getElementValue(bunnyVariants, ''));
    }

    function syncBunnyManualVariantsInputFromChips() {
        if (!bunnyVariants) {
            return;
        }

        bunnyVariants.value = availableBunnyVariantInputs()
            .filter((input) => input.checked)
            .map((input) => input.value)
            .join(',');
    }

    function syncBunnyVariantChipsFromManualInput() {
        if (!bunnyVariants) {
            return false;
        }

        const inputs = availableBunnyVariantInputs();

        if (!inputs.length) {
            return false;
        }

        const selectedTokens = new Set(normalizeHostedVariantTokens(bunnyVariants.value).map((token) => token.toLowerCase()));
        const normalizedValues = [];

        syncingBunnyVariants = true;

        inputs.forEach((input) => {
            input.checked = selectedTokens.has(String(input.value).toLowerCase());
            syncVariantChipState(input);

            if (input.checked) {
                normalizedValues.push(input.value);
            }
        });

        bunnyVariants.value = normalizedValues.join(',');
        syncingBunnyVariants = false;

        return true;
    }

    function applyVariantQuickSelection(mode) {
        const inputs = availableVariantInputs();

        if (!inputs.length) {
            return;
        }

        inputs.forEach((input) => {
            const value = String(input.value).toLowerCase();
            let shouldCheck = false;

            switch (mode) {
                case 'all':
                    shouldCheck = true;
                    break;
                case 'normal':
                    shouldCheck = !value.includes('italic');
                    break;
                case 'italic':
                    shouldCheck = value.includes('italic');
                    break;
                case 'clear':
                    shouldCheck = false;
                    break;
                default:
                    shouldCheck = input.checked;
                    break;
            }

            input.checked = shouldCheck;
            syncVariantChipState(input);
        });

        if (manualVariants) {
            if (mode === 'clear') {
                manualVariants.dataset.explicitEmpty = 'true';
            } else {
                delete manualVariants.dataset.explicitEmpty;
            }
        }

        syncManualVariantsInputFromChips();
        updateGoogleImportSummary();
    }

    function updateGoogleImportSummary() {
        const familyName = currentGoogleImportFamily();
        const matchedFamily = selectedSearchFamily || findGoogleFamilyMatch(familyName);
        const hasFamily = familyName !== '';
        const variants = hasFamily ? Array.from(new Set(selectedVariantTokens())) : [];
        const variantCount = variants.length;
        const fallback = googlePreviewFallback(matchedFamily ? matchedFamily.category : '');
        const previewText = googleSelectedPreviewText();
        const availableCount = matchedFamily && Array.isArray(matchedFamily.variants)
            ? matchedFamily.variants.length
            : 0;
        const estimatedKilobytes = variants.reduce((total, variant) => {
            return total + approximateVariantTransferSize(variant, matchedFamily ? matchedFamily.category : '');
        }, 0);

        updateSelectedFamilyLabel(familyName);

        if (selectedFamilyPreview) {
            const previewFamilyName = matchedFamily ? matchedFamily.family : familyName;

            selectedFamilyPreview.textContent = hasFamily
                ? previewText
                : getString('importPreviewEmpty', 'Preview appears here after you choose a family.');
            selectedFamilyPreview.classList.toggle('is-placeholder', !hasFamily);
            selectedFamilyPreview.style.fontFamily = hasFamily ? `"${previewFamilyName}", ${fallback}` : '';
        }

        if (importFilesEstimate) {
            importFilesEstimate.textContent = formatPluralMessage(
                '%1$d File Selected',
                '%1$d Files Selected',
                hasFamily ? variantCount : 0,
                [hasFamily ? variantCount : 0]
            );
        }

        if (importSizeEstimate) {
            importSizeEstimate.textContent = formatMessage(
                getString('importEstimateSize', 'Approx. +%1$s WOFF2'),
                [formatTransferEstimate(hasFamily ? estimatedKilobytes : 0)]
            );
        }

        if (importSelectionSummary) {
            if (!hasFamily) {
                importSelectionSummary.textContent = getString(
                    'importSelectionSummaryEmpty',
                    '0 Variants Selected'
                );
            } else if (availableCount > 0) {
                importSelectionSummary.textContent = formatMessage(
                    getString('importSelectionSummaryAvailable', '%1$d of %2$d Variants Selected'),
                    [variantCount, availableCount]
                );
            } else if (variantCount > 0) {
                importSelectionSummary.textContent = formatPluralMessage(
                    '%1$d Variant Selected',
                    '%1$d Variants Selected',
                    variantCount,
                    [variantCount]
                );
            } else {
                importSelectionSummary.textContent = getString(
                    'importSelectionSummaryEmpty',
                    '0 Variants Selected'
                );
            }
        }

        variantQuickSelectButtons.forEach((button) => {
            button.disabled = !hasFamily || availableCount === 0;
        });

        updateGooglePreviewStylesheet(searchResults);
    }

    function updateBunnyImportSummary() {
        const familyName = currentBunnyImportFamily();
        const matchedFamily = selectedBunnySearchFamily || findBunnyFamilyMatch(familyName);
        const hasFamily = familyName !== '';
        const previewText = googleSelectedPreviewText();
        const variants = hasFamily ? Array.from(new Set(bunnySelectedVariantTokens())) : [];
        const effectiveVariants = variants.length > 0 ? variants : (hasFamily ? ['regular'] : []);
        const variantCount = variants.length;
        const availableCount = matchedFamily && Array.isArray(matchedFamily.variants)
            ? matchedFamily.variants.length
            : 0;
        const fallback = googlePreviewFallback(matchedFamily ? matchedFamily.category : '');
        const estimatedKilobytes = effectiveVariants.reduce((total, variant) => {
            return total + approximateVariantTransferSize(variant, matchedFamily ? matchedFamily.category : '');
        }, 0);

        if (bunnySelectedFamily) {
            bunnySelectedFamily.textContent = familyName || getString('bunnyImportFamilyEmpty', 'Choose a Bunny family or type one manually.');
        }

        if (bunnySelectedFamilyPreview) {
            const previewFamilyName = matchedFamily ? matchedFamily.family : familyName;

            bunnySelectedFamilyPreview.textContent = hasFamily
                ? previewText
                : getString('bunnyImportPreviewEmpty', 'Preview appears here after you choose a Bunny family.');
            bunnySelectedFamilyPreview.classList.toggle('is-placeholder', !hasFamily);
            bunnySelectedFamilyPreview.style.fontFamily = hasFamily ? `"${previewFamilyName}", ${fallback}` : '';
        }

        if (bunnyImportSizeEstimate) {
            bunnyImportSizeEstimate.textContent = formatMessage(
                getString('importEstimateSize', 'Approx. +%1$s WOFF2'),
                [formatTransferEstimate(hasFamily ? estimatedKilobytes : 0)]
            );
        }

        if (bunnyImportSelectionSummary) {
            if (!hasFamily) {
                bunnyImportSelectionSummary.textContent = getString(
                    'importSelectionSummaryEmpty',
                    '0 Variants Selected'
                );
            } else if (availableCount > 0) {
                bunnyImportSelectionSummary.textContent = formatMessage(
                    getString('importSelectionSummaryAvailable', '%1$d of %2$d Variants Selected'),
                    [variantCount, availableCount]
                );
            } else if (variantCount > 0) {
                bunnyImportSelectionSummary.textContent = formatPluralMessage(
                    '%1$d Variant Selected',
                    '%1$d Variants Selected',
                    variantCount,
                    [variantCount]
                );
            } else {
                bunnyImportSelectionSummary.textContent = getString(
                    'importSelectionSummaryEmpty',
                    '0 Variants Selected'
                );
            }
        }

        bunnyVariantQuickSelectButtons.forEach((button) => {
            button.disabled = !hasFamily || availableCount === 0;
        });

        updateBunnyPreviewStylesheet(familyName, effectiveVariants);
    }

    function getDisclosureTarget(toggle) {
        if (!toggle) {
            return null;
        }

        const targetId = toggle.getAttribute('data-disclosure-toggle');

        return targetId ? document.getElementById(targetId) : null;
    }

    function tabButtonsForGroup(group) {
        return tabButtons.filter((tab) => tab.getAttribute('data-tab-group') === group);
    }

    function tabPanelsForGroup(group) {
        return tabPanels.filter((panel) => panel.getAttribute('data-tab-group') === group);
    }

    function activeTabKeyForGroup(group) {
        const activeTab = tabButtonsForGroup(group).find((tab) => tab.classList.contains('is-active'));

        return activeTab ? activeTab.getAttribute('data-tab-target') : '';
    }

    function activateTabGroup(group, key) {
        tabButtonsForGroup(group).forEach((tab) => {
            const isActive = tab.getAttribute('data-tab-target') === key;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
        });

        tabPanelsForGroup(group).forEach((panel) => {
            const isActive = panel.getAttribute('data-tab-panel') === key;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        });
    }

    function setDisclosureState(toggle, expanded) {
        const target = getDisclosureTarget(toggle);

        if (!toggle || !target) {
            return;
        }

        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        target.hidden = !expanded;

        const expandedLabel = toggle.getAttribute('data-expanded-label');
        const collapsedLabel = toggle.getAttribute('data-collapsed-label');

        if (expandedLabel && collapsedLabel) {
            toggle.textContent = expanded ? expandedLabel : collapsedLabel;
        }

        const fontRow = toggle.closest('[data-font-row]');
        if (fontRow) {
            fontRow.classList.toggle('is-expanded', expanded);
        }
    }

    function syncDisclosureToggles() {
        disclosureToggles.forEach((toggle) => {
            setDisclosureState(toggle, toggle.getAttribute('aria-expanded') === 'true');
        });
    }

    function focusAddFontPanel() {
        const activeKey = activeTabKeyForGroup('add-font') || 'google';

        if (activeKey === 'upload') {
            const firstFamilyInput = uploadGroupsWrap ? uploadGroupsWrap.querySelector('[data-upload-group-field="family"]') : null;

            if (firstFamilyInput) {
                firstFamilyInput.focus();
            }

            return;
        }

        if (activeKey === 'adobe') {
            if (adobeProjectId && !adobeProjectId.closest('[hidden]')) {
                adobeProjectId.focus();
                return;
            }

            const adobeSettingsButton = document.querySelector('[data-disclosure-toggle="tasty-fonts-adobe-project-panel"]');

            if (adobeSettingsButton) {
                adobeSettingsButton.focus();
            }

            return;
        }

        if (activeKey === 'bunny') {
            if (bunnySearch) {
                bunnySearch.focus();
                return;
            }

            if (bunnyFamily) {
                bunnyFamily.focus();
            }

            return;
        }

        if (googleSearch && !googleSearch.disabled) {
            googleSearch.focus();
            return;
        }

        if (manualFamily) {
            manualFamily.focus();
        }
    }

    // Toasts and tooltips
    function dismissToast(toast) {
        if (!toast || !toast.parentNode) {
            return;
        }

        if (toast._dismissTimer) {
            window.clearTimeout(toast._dismissTimer);
            toast._dismissTimer = 0;
        }

        if (toast.classList.contains('is-leaving')) {
            return;
        }

        toast.classList.add('is-leaving');

        window.setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 180);
    }

    function ensureToastStack() {
        let stack = document.querySelector('.tasty-fonts-toast-stack');

        if (stack) {
            return stack;
        }

        const toastRoot = document.querySelector('.tasty-fonts-admin') || document.body;

        stack = document.createElement('div');
        stack.className = 'tasty-fonts-toast-stack';
        stack.setAttribute('aria-live', 'polite');
        stack.setAttribute('aria-atomic', 'true');
        toastRoot.appendChild(stack);

        return stack;
    }

    function scheduleToastDismiss(toast, tone) {
        if (!toast) {
            return;
        }

        if (toast._dismissTimer) {
            window.clearTimeout(toast._dismissTimer);
        }

        toast._dismissTimer = window.setTimeout(() => {
            dismissToast(toast);
        }, tone === 'error' ? 6000 : 3200);
    }

    function showToast(message, tone) {
        if (!message) {
            return;
        }

        const stack = ensureToastStack();
        const resolvedTone = tone === 'error' ? 'error' : 'success';
        const existingToast = Array.from(stack.querySelectorAll('[data-toast]')).find((toast) => {
            if (toast.getAttribute('data-toast-tone') !== resolvedTone) {
                return false;
            }

            const text = toast.querySelector('.tasty-fonts-toast-message');

            return text && text.textContent === message;
        });

        if (existingToast) {
            existingToast.classList.remove('is-leaving');
            stack.appendChild(existingToast);
            scheduleToastDismiss(existingToast, resolvedTone);
            return;
        }

        const toast = document.createElement('div');
        const dismiss = document.createElement('button');
        const text = document.createElement('div');

        toast.className = `tasty-fonts-toast is-${resolvedTone}`;
        toast.setAttribute('data-toast', '');
        toast.setAttribute('data-toast-tone', resolvedTone);
        toast.setAttribute('role', resolvedTone === 'error' ? 'alert' : 'status');

        text.className = 'tasty-fonts-toast-message';
        text.textContent = message;

        dismiss.type = 'button';
        dismiss.className = 'tasty-fonts-toast-dismiss';
        dismiss.setAttribute('data-toast-dismiss', '');
        dismiss.setAttribute('aria-label', 'Dismiss notification');
        dismiss.innerHTML = '<span aria-hidden="true">&times;</span>';

        toast.appendChild(text);
        toast.appendChild(dismiss);
        stack.appendChild(toast);

        scheduleToastDismiss(toast, resolvedTone);
    }

    function initToasts() {
        if (!toastItems.length) {
            return;
        }

        toastItems.forEach((toast) => {
            scheduleToastDismiss(toast, toast.getAttribute('data-toast-tone') === 'error' ? 'error' : 'success');
        });
    }

    function positionHelpTooltip(button) {
        if (!button || !helpTooltipLayer || helpTooltipLayer.hidden) {
            return;
        }

        const margin = 12;
        const gap = 10;
        const triggerRect = button.getBoundingClientRect();

        helpTooltipLayer.style.left = '0px';
        helpTooltipLayer.style.top = '0px';
        helpTooltipLayer.style.maxWidth = `${Math.max(220, Math.min(320, window.innerWidth - (margin * 2)))}px`;

        const tooltipRect = helpTooltipLayer.getBoundingClientRect();
        const left = Math.max(
            margin,
            Math.min(
                triggerRect.left + (triggerRect.width / 2) - (tooltipRect.width / 2),
                window.innerWidth - tooltipRect.width - margin
            )
        );

        let top = triggerRect.bottom + gap;
        let isAbove = false;

        if (top + tooltipRect.height > window.innerHeight - margin && triggerRect.top - gap - tooltipRect.height >= margin) {
            top = triggerRect.top - tooltipRect.height - gap;
            isAbove = true;
        } else {
            top = Math.max(margin, Math.min(top, window.innerHeight - tooltipRect.height - margin));
        }

        helpTooltipLayer.style.left = `${left}px`;
        helpTooltipLayer.style.top = `${top}px`;
        helpTooltipLayer.classList.toggle('is-above', isAbove);
    }

    function rememberHelpTooltipDescription(button) {
        if (!button || Object.prototype.hasOwnProperty.call(button.dataset, 'helpTooltipDescribedby')) {
            return;
        }

        button.dataset.helpTooltipDescribedby = button.getAttribute('aria-describedby') || '';
    }

    function restoreHelpTooltipDescription(button) {
        if (!button) {
            return;
        }

        const describedBy = button.dataset.helpTooltipDescribedby || '';

        if (describedBy) {
            button.setAttribute('aria-describedby', describedBy);
            return;
        }

        button.removeAttribute('aria-describedby');
    }

    function applyHelpTooltipDescription(button) {
        if (!button || !helpTooltipLayer) {
            return;
        }

        rememberHelpTooltipDescription(button);
        button.setAttribute('aria-describedby', helpTooltipLayer.id);
    }

    function hideHelpTooltip() {
        if (!helpTooltipLayer) {
            return;
        }

        if (activeHelpButton) {
            activeHelpButton.setAttribute('aria-expanded', 'false');
            restoreHelpTooltipDescription(activeHelpButton);
        }

        helpTooltipLayer.hidden = true;
        helpTooltipLayer.textContent = '';
        helpTooltipLayer.classList.remove('is-above');
        activeHelpButton = null;
    }

    function clearPassiveHelpTooltip(button) {
        if (!button) {
            return;
        }

        if (activeHelpButton === button) {
            hideHelpTooltip();
        }

        button.removeAttribute('data-help-tooltip');
        button.removeAttribute('data-help-passive');
        button.removeAttribute('title');
    }

    function setPassiveHelpTooltip(button, copy) {
        if (!button) {
            return;
        }

        const nextCopy = typeof copy === 'string' ? copy.trim() : '';

        if (trainingWheelsOff || !nextCopy) {
            clearPassiveHelpTooltip(button);
            return;
        }

        button.setAttribute('data-help-tooltip', nextCopy);
        button.setAttribute('data-help-passive', '1');
        button.setAttribute('title', nextCopy);
    }

    function upgradePillTooltips(scope = document) {
        const candidates = Array.from(scope.querySelectorAll('.tasty-fonts-pill[title], .tasty-fonts-badge[title], .tasty-fonts-chip[title], .tasty-fonts-face-pill[title], .tasty-fonts-preview-pill[title], .tasty-fonts-kbd[title], .tasty-fonts-stack-copy[title]'));

        candidates.forEach((button) => {
            if (button.hasAttribute('data-help-tooltip')) {
                return;
            }

            const copy = button.getAttribute('title');

            if (!copy) {
                return;
            }

            button.setAttribute('data-help-tooltip', copy);
            button.setAttribute('data-help-passive', '1');
        });
    }

    function showHelpTooltip(button) {
        if (!button || !helpTooltipLayer) {
            return;
        }

        const copy = button.getAttribute('data-help-tooltip');

        if (!copy) {
            hideHelpTooltip();
            return;
        }

        if (activeHelpButton && activeHelpButton !== button) {
            activeHelpButton.setAttribute('aria-expanded', 'false');
            restoreHelpTooltipDescription(activeHelpButton);
        }

        activeHelpButton = button;
        activeHelpButton.setAttribute('aria-expanded', 'true');
        applyHelpTooltipDescription(activeHelpButton);
        helpTooltipLayer.textContent = copy;
        helpTooltipLayer.hidden = false;
        positionHelpTooltip(button);
    }

    function initHelpTooltips() {
        if (!helpTooltipLayer) {
            return;
        }

        upgradePillTooltips();
        helpButtons = Array.from(document.querySelectorAll('[data-help-tooltip]'));

        if (!helpButtons.length) {
            return;
        }

        if (trainingWheelsOff) {
            helpButtons.forEach((button) => clearPassiveHelpTooltip(button));
            hideHelpTooltip();
            return;
        }

        helpButtons.forEach((button) => {
            button.setAttribute('aria-expanded', 'false');
            rememberHelpTooltipDescription(button);

            button.addEventListener('mouseenter', () => showHelpTooltip(button));
            button.addEventListener('focus', () => showHelpTooltip(button));
            button.addEventListener('mouseleave', () => {
                if (activeHelpButton === button && document.activeElement !== button) {
                    hideHelpTooltip();
                }
            });
            button.addEventListener('blur', () => {
                window.setTimeout(() => {
                    if (activeHelpButton === button && document.activeElement !== button) {
                        hideHelpTooltip();
                    }
                }, 0);
            });
            button.addEventListener('click', (event) => {
                if (button.hasAttribute('data-help-passive')) {
                    hideHelpTooltip();
                    return;
                }

                event.preventDefault();

                if (activeHelpButton === button && !helpTooltipLayer.hidden) {
                    hideHelpTooltip();
                    return;
                }

                showHelpTooltip(button);
            });
        });

        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-help-tooltip]')) {
                return;
            }

            hideHelpTooltip();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                hideHelpTooltip();
            }
        });

        window.addEventListener('resize', () => {
            positionHelpTooltip(activeHelpButton);
        });

        window.addEventListener('scroll', () => {
            positionHelpTooltip(activeHelpButton);
        }, true);
    }

    async function requestMultipart(path, formData, onProgress, fallbackMessage) {
        const url = buildRestUrl(path, {});

        if (!url) {
            throw new Error(fallbackMessage || getString('uploadError', 'The font upload failed.'));
        }

        return new Promise((resolve, reject) => {
            const request = new XMLHttpRequest();

            request.open('POST', url, true);
            request.setRequestHeader('Accept', 'application/json');
            request.setRequestHeader('X-WP-Nonce', config.restNonce || '');

            request.upload.addEventListener('progress', (event) => {
                if (!event.lengthComputable || typeof onProgress !== 'function') {
                    return;
                }

                onProgress(Math.round((event.loaded / event.total) * 100));
            });

            request.addEventListener('load', () => {
                let payload = {};

                try {
                    payload = request.responseText ? JSON.parse(request.responseText) : {};
                } catch (error) {
                    payload = {};
                }

                if (request.status >= 200 && request.status < 300) {
                    resolve(payload);
                    return;
                }

                reject(
                    new Error(
                        getApiMessage(payload, fallbackMessage || getString('uploadError', 'The font upload failed.'))
                    )
                );
            });

            request.addEventListener('error', () => {
                reject(new Error(getString('uploadError', 'The font upload failed.')));
            });

            request.send(formData);
        });
    }

    function setStatus(target, message, type, progress) {
        if (!target) {
            return;
        }

        target.innerHTML = '';
        target.classList.remove('is-error', 'is-success', 'is-progress');

        if (!message) {
            return;
        }

        const text = document.createElement('div');
        text.className = 'tasty-fonts-import-status-message';
        text.textContent = message;
        target.appendChild(text);

        if (type && type !== 'progress') {
            target.classList.add(type === 'error' ? 'is-error' : 'is-success');
        }

        if (typeof progress === 'number') {
            const track = document.createElement('div');
            const bar = document.createElement('div');
            const clamped = Math.max(0, Math.min(100, progress));

            target.classList.add('is-progress');
            track.className = 'tasty-fonts-import-progress-track';
            bar.className = 'tasty-fonts-import-progress-bar';
            bar.style.width = `${clamped}%`;
            track.appendChild(bar);
            target.appendChild(track);
            return;
        }
    }

    // Role preview and output state
    function currentRoleData() {
        return buildRoleDataFromValues({
            heading: getElementValue(roleHeading, ''),
            body: getElementValue(roleBody, ''),
            monospace: monospaceRoleEnabled ? getElementValue(roleMonospace, '') : '',
            headingFallback: getElementValue(roleHeadingFallback, 'sans-serif'),
            bodyFallback: getElementValue(roleBodyFallback, 'sans-serif'),
            monospaceFallback: monospaceRoleEnabled ? getElementValue(roleMonospaceFallback, 'monospace') : 'monospace',
        });
    }

    function currentDraftRoleState() {
        return normalizeRoleState({
            heading: getElementValue(roleHeading, ''),
            body: getElementValue(roleBody, ''),
            monospace: monospaceRoleEnabled ? getElementValue(roleMonospace, '') : '',
            headingFallback: getElementValue(roleHeadingFallback, 'sans-serif'),
            bodyFallback: getElementValue(roleBodyFallback, 'sans-serif'),
            monospaceFallback: monospaceRoleEnabled ? getElementValue(roleMonospaceFallback, 'monospace') : 'monospace',
        });
    }

    function currentAppliedRoleState() {
        return previewBootstrap().appliedRoles;
    }

    const initialDraftRoleState = roleForm ? currentDraftRoleState() : normalizeRoleState({});

    function roleStatesMatch(left = {}, right = {}) {
        const leftState = normalizeRoleState(left);
        const rightState = normalizeRoleState(right);
        const keys = monospaceRoleEnabled
            ? ['heading', 'body', 'monospace', 'headingFallback', 'bodyFallback', 'monospaceFallback']
            : ['heading', 'body', 'headingFallback', 'bodyFallback'];

        return keys.every((key) => leftState[key] === rightState[key]);
    }

    function syncDisabledRoleActionHelp(target, disabled, copy) {
        if (!target) {
            return;
        }

        const nextCopy = disabled ? copy : '';

        target.classList.toggle('has-disabled-reason', !!nextCopy);
        target.tabIndex = disabled && !trainingWheelsOff ? 0 : -1;
        target.setAttribute('aria-label', nextCopy);
        setPassiveHelpTooltip(target, nextCopy);

        if (activeHelpButton === target) {
            if (!nextCopy || trainingWheelsOff) {
                hideHelpTooltip();
            } else if (helpTooltipLayer && !helpTooltipLayer.hidden) {
                helpTooltipLayer.textContent = nextCopy;
                positionHelpTooltip(target);
            }
        }
    }

    function syncRoleActionButtonStates() {
        const draftChanged = !roleStatesMatch(currentDraftRoleState(), initialDraftRoleState);
        const hasPendingLiveChanges = !!config.applyEverywhere && !roleStatesMatch(currentDraftRoleState(), currentAppliedRoleState());

        if (roleApplyLiveButton) {
            roleApplyLiveButton.classList.toggle('button-primary', hasPendingLiveChanges);
            roleApplyLiveButton.classList.toggle('is-pending-live-change', hasPendingLiveChanges);
            roleApplyLiveButton.setAttribute('aria-disabled', hasPendingLiveChanges ? 'false' : 'true');
            roleApplyLiveButton.disabled = !hasPendingLiveChanges;
        }

        if (roleSaveDraftButton) {
            roleSaveDraftButton.setAttribute('aria-disabled', draftChanged ? 'false' : 'true');
            roleSaveDraftButton.disabled = !draftChanged;
        }

        syncDisabledRoleActionHelp(
            roleApplyLiveWrap,
            !hasPendingLiveChanges,
            config.applyEverywhere
                ? getString('roleApplyLiveDisabledNoChanges', 'No live role changes to publish.')
                : getString('roleApplyLiveDisabledSitewideOff', 'Apply Sitewide is off. Turn it on before publishing role changes.')
        );
        syncDisabledRoleActionHelp(
            roleSaveDraftWrap,
            !draftChanged,
            getString('roleSaveDisabledNoChanges', 'No draft changes to save.')
        );
    }

    function currentPreviewData() {
        return buildRoleDataFromValues(previewRoleState || currentDraftRoleState());
    }

    function computePreviewBaseline() {
        const bootstrapData = previewBootstrap();

        if (bootstrapData.baselineSource === 'live_sitewide' && (bootstrapData.appliedRoles.heading || bootstrapData.appliedRoles.body || bootstrapData.appliedRoles.monospace)) {
            return {
                source: 'live_sitewide',
                label: bootstrapData.baselineLabel,
                roles: bootstrapData.appliedRoles,
            };
        }

        return {
            source: 'draft',
            label: bootstrapData.baselineSource === 'live_sitewide'
                ? getString('previewCurrentDraft', 'Current draft')
                : bootstrapData.baselineLabel,
            roles: currentDraftRoleState(),
        };
    }

    function setPreviewSourceLabel(label) {
        if (previewSourceLabel) {
            previewSourceLabel.textContent = label || getString('previewCurrentDraft', 'Current draft');
        }
    }

    function buildPreviewCustomCss(data) {
        const lines = [':root {'];
        const variableLines = [];

        if (data.headingSlug) {
            variableLines.push(`  --font-${data.headingSlug}: ${data.headingStack};`);
            variableLines.push(`  --font-heading: var(--font-${data.headingSlug});`);
        } else {
            variableLines.push(`  --font-heading: ${data.headingStack};`);
        }

        if (data.bodySlug) {
            variableLines.push(`  --font-${data.bodySlug}: ${data.bodyStack};`);
            variableLines.push(`  --font-body: var(--font-${data.bodySlug});`);
        } else {
            variableLines.push(`  --font-body: ${data.bodyStack};`);
        }

        if (data.includeMonospace) {
            if (data.monospaceSlug) {
                variableLines.push(`  --font-${data.monospaceSlug}: ${data.monospaceStack};`);
                variableLines.push(`  --font-monospace: var(--font-${data.monospaceSlug});`);
            } else {
                variableLines.push(`  --font-monospace: ${data.monospaceStack};`);
            }
        }

        lines.push(...variableLines);
        lines.push('}', '');
        lines.push('body {');
        lines.push('  font-family: var(--font-body);');
        lines.push('}', '');
        lines.push('h1, h2, h3, h4, h5, h6 {');
        lines.push('  font-family: var(--font-heading);');
        lines.push('}');

        if (data.includeMonospace) {
            lines.push('');
            lines.push('code, pre, kbd, samp {');
            lines.push('  font-family: var(--font-monospace);');
            lines.push('}');
        }

        return lines.join('\n');
    }

    function updatePreviewCopyCssButton(data) {
        if (!previewCopyCssButton) {
            return;
        }

        const previewData = data && typeof data === 'object' ? data : currentPreviewData();
        previewCopyCssButton.setAttribute('data-copy-text', buildPreviewCustomCss(previewData));
    }

    function updatePreviewDirtyState() {
        if (previewDirtyIndicator) {
            previewDirtyIndicator.hidden = !previewDirty;
        }
    }

    function syncPreviewControlsFromState() {
        const state = normalizeRoleState(previewRoleState || currentDraftRoleState());

        Object.entries(previewRoleSelects).forEach(([roleKey, element]) => {
            if (!element) {
                return;
            }

            element.value = state[roleKey] || '';
        });
    }

    function applyPreviewOutputs(sourceLabel = '') {
        const data = currentPreviewData();

        if (previewCanvas) {
            previewCanvas.style.setProperty('--tasty-preview-heading-stack', data.headingStack);
            previewCanvas.style.setProperty('--tasty-preview-body-stack', data.bodyStack);
            previewCanvas.style.setProperty('--tasty-preview-monospace-stack', data.monospaceStack);
        }

        roleHeadingPreviews.forEach((element) => {
            element.style.fontFamily = data.headingStack;
        });

        roleBodyPreviews.forEach((element) => {
            element.style.fontFamily = data.bodyStack;
        });

        roleMonospacePreviews.forEach((element) => {
            element.style.fontFamily = data.monospaceStack;
        });

        roleHeadingPreviewNames.forEach((element) => {
            element.textContent = previewRoleName(data.heading, data.headingFallback, 'sans-serif', true);
        });

        roleBodyPreviewNames.forEach((element) => {
            element.textContent = previewRoleName(data.body, data.bodyFallback, 'sans-serif', true);
        });

        roleMonospacePreviewNames.forEach((element) => {
            element.textContent = previewRoleName(data.monospace, data.monospaceFallback, 'monospace', true);
        });

        syncPreviewControlsFromState();
        updatePreviewCopyCssButton(data);
        setPreviewSourceLabel(sourceLabel);
        updatePreviewDirtyState();
    }

    function resetPreviewWorkspace() {
        const baseline = computePreviewBaseline();
        previewRoleState = normalizeRoleState(baseline.roles);
        previewDirty = false;
        previewFollowsDraft = baseline.source === 'draft';
        applyPreviewOutputs(baseline.label);
    }

    function applyPreviewStateToRoleFields() {
        const state = normalizeRoleState(previewRoleState || currentDraftRoleState());

        if (roleHeading) {
            roleHeading.value = state.heading || '';
        }

        if (roleBody) {
            roleBody.value = state.body || '';
        }

        if (roleHeadingFallback) {
            roleHeadingFallback.value = state.headingFallback || 'sans-serif';
        }

        if (roleBodyFallback) {
            roleBodyFallback.value = state.bodyFallback || 'sans-serif';
        }

        if (monospaceRoleEnabled && roleMonospace) {
            roleMonospace.value = state.monospace || '';
        }

        if (monospaceRoleEnabled && roleMonospaceFallback) {
            roleMonospaceFallback.value = state.monospaceFallback || 'monospace';
        }
    }

    function syncPreviewWorkspaceToDraft({ markDirty = false } = {}) {
        previewRoleState = currentDraftRoleState();
        previewFollowsDraft = true;
        previewDirty = markDirty;
        applyPreviewOutputs(getString('previewCurrentDraft', 'Current draft'));
    }

    function initializePreviewWorkspace() {
        if (previewWorkspaceInitialized) {
            return;
        }

        previewWorkspaceInitialized = true;
        resetPreviewWorkspace();
    }

    function updateRoleOutputs() {
        const data = currentRoleData();

        headingRoleVariableCopies.forEach((button) => {
            button.textContent = data.headingVariable;
            button.setAttribute('data-copy-text', data.headingVariable);
            button.setAttribute('title', `Heading font variable: ${data.headingVariable}. Resolved stack: ${data.headingStack}`);
        });

        bodyRoleVariableCopies.forEach((button) => {
            button.textContent = data.bodyVariable;
            button.setAttribute('data-copy-text', data.bodyVariable);
            button.setAttribute('title', `Body font variable: ${data.bodyVariable}. Resolved stack: ${data.bodyStack}`);
        });

        monospaceRoleVariableCopies.forEach((button) => {
            button.textContent = data.monospaceVariable;
            button.setAttribute('data-copy-text', data.monospaceVariable);
            button.setAttribute('title', `Monospace font variable: ${data.monospaceVariable}. Resolved stack: ${data.monospaceStack}`);
        });

        headingFamilyVariableCopies.forEach((button) => {
            button.textContent = data.headingFamilyVariable;
            button.setAttribute('data-copy-text', data.headingFamilyVariable);
            button.setAttribute(
                'title',
                data.heading
                    ? `Heading family variable: ${data.headingFamilyVariable}. Role alias: ${data.headingVariable}. Resolved stack: ${data.headingStack}`
                    : `Heading uses the fallback stack directly: ${data.headingStack}. Role alias: ${data.headingVariable}`
            );
        });

        bodyFamilyVariableCopies.forEach((button) => {
            button.textContent = data.bodyFamilyVariable;
            button.setAttribute('data-copy-text', data.bodyFamilyVariable);
            button.setAttribute(
                'title',
                data.body
                    ? `Body family variable: ${data.bodyFamilyVariable}. Role alias: ${data.bodyVariable}. Resolved stack: ${data.bodyStack}`
                    : `Body uses the fallback stack directly: ${data.bodyStack}. Role alias: ${data.bodyVariable}`
            );
        });

        monospaceFamilyVariableCopies.forEach((button) => {
            button.textContent = data.monospaceFamilyVariable;
            button.setAttribute('data-copy-text', data.monospaceFamilyVariable);
            button.setAttribute(
                'title',
                data.monospace
                    ? `Monospace family variable: ${data.monospaceFamilyVariable}. Role alias: ${data.monospaceVariable}. Resolved stack: ${data.monospaceStack}`
                    : `Monospace uses the fallback stack directly: ${data.monospaceStack}. Role alias: ${data.monospaceVariable}`
            );
        });

        syncRoleActionButtonStates();

        roleAssignButtons.forEach((button) => {
            const family = button.getAttribute('data-font-family') || '';
            const role = button.getAttribute('data-role-assign');
            const isCurrent = (role === 'heading' && family === data.heading)
                || (role === 'body' && family === data.body)
                || (role === 'monospace' && family === data.monospace);
            const label = button.querySelector('.tasty-fonts-role-assign-label');
            const nextLabel = isCurrent ? (button.dataset.activeLabel || '') : (button.dataset.idleLabel || '');
            const nextHelp = isCurrent ? (button.dataset.activeHelp || nextLabel) : (button.dataset.idleHelp || nextLabel);

            button.classList.toggle('is-current', isCurrent);
            button.setAttribute('aria-pressed', isCurrent ? 'true' : 'false');
            button.setAttribute('aria-label', nextLabel);
            setPassiveHelpTooltip(button, nextHelp);

            if (label) {
                label.textContent = nextLabel;
            }

            if (activeHelpButton === button && helpTooltipLayer && !helpTooltipLayer.hidden) {
                helpTooltipLayer.textContent = nextHelp;
                positionHelpTooltip(button);
            }
        });

        deleteFamilyButtons.forEach((button) => {
            const family = button.getAttribute('data-delete-family') || '';
            const blockedRoleKeys = selectedRoleKeysForFamily(family, data);
            const blockedMessage = blockedRoleKeys.length > 0
                ? (button.getAttribute(`data-delete-blocked-${buildRoleSelectionKey(blockedRoleKeys)}`) || '')
                : '';

            button.classList.toggle('is-disabled', blockedMessage !== '');
            button.setAttribute('aria-disabled', blockedMessage !== '' ? 'true' : 'false');
            setPassiveHelpTooltip(button, blockedMessage || button.dataset.deleteReadyTitle || '');

            if (blockedMessage !== '') {
                button.setAttribute('data-delete-blocked', blockedMessage);
                if (activeHelpButton === button && helpTooltipLayer && !helpTooltipLayer.hidden) {
                    helpTooltipLayer.textContent = blockedMessage;
                    positionHelpTooltip(button);
                }
                return;
            }

            button.removeAttribute('data-delete-blocked');

            if (activeHelpButton === button && helpTooltipLayer && !helpTooltipLayer.hidden) {
                helpTooltipLayer.textContent = button.dataset.deleteReadyTitle || '';
                positionHelpTooltip(button);
            }
        });

        if (outputNames) {
            const names = [data.heading, data.body];

            if (data.includeMonospace) {
                names.push(data.monospace);
            }

            outputNames.value = names.join('\n');
        }

        if (outputStacks) {
            const stacks = [data.headingStack, data.bodyStack];

            if (data.includeMonospace) {
                stacks.push(data.monospaceStack);
            }

            outputStacks.value = stacks.join('\n');
        }

        let variableSnippet = '';

        if (data.headingSlug && data.bodySlug) {
            const variableLines = [
                ':root {',
                `  --font-${data.headingSlug}: ${data.headingStack};`,
                `  --font-${data.bodySlug}: ${data.bodyStack};`,
                `  --font-heading: var(--font-${data.headingSlug});`,
                `  --font-body: var(--font-${data.bodySlug});`,
            ];

            if (data.includeMonospace) {
                if (data.monospaceSlug) {
                    variableLines.push(`  --font-${data.monospaceSlug}: ${data.monospaceStack};`);
                    variableLines.push(`  --font-monospace: var(--font-${data.monospaceSlug});`);
                } else {
                    variableLines.push(`  --font-monospace: ${data.monospaceStack};`);
                }
            }

            variableLines.push('}');
            variableSnippet = variableLines.join('\n');
        }

        if (outputVars) {
            outputVars.value = variableSnippet;
        }

        if (outputUsage) {
            if (!variableSnippet) {
                outputUsage.value = '';
            } else {
                const usageLines = [
                    variableSnippet,
                    '',
                    'body {',
                    '  font-family: var(--font-body);',
                    '}',
                    '',
                    'h1, h2, h3, h4, h5, h6 {',
                    '  font-family: var(--font-heading);',
                    '}'
                ];

                if (data.includeMonospace) {
                    usageLines.push(
                        '',
                        'code, pre {',
                        '  font-family: var(--font-monospace);',
                        '}'
                    );
                }

                outputUsage.value = usageLines.join('\n');
            }
        }

        if (previewWorkspaceInitialized && previewFollowsDraft) {
            previewRoleState = currentDraftRoleState();
            applyPreviewOutputs(getString('previewCurrentDraft', 'Current draft'));
        }
    }

    function updatePreviewDynamicText() {
        if (!previewTextInput) {
            return;
        }

        const previewText = previewTextInput.value.trim();
        const text = previewText
            ? previewText
            : getString('previewFallback', 'The quick brown fox jumps over the lazy dog. 1234567890');

        previewDynamicText.forEach((element) => {
            element.textContent = text;
        });
    }

    function updatePreviewScale() {
        if (!previewCanvas || !previewSizeInput) {
            return;
        }

        const size = Number.parseInt(previewSizeInput.value, 10);
        const safeSize = Number.isFinite(size) && size > 0 ? size : 32;

        previewCanvas.style.setProperty('--tasty-preview-base', `${safeSize}px`);
    }

    function updateInlineStackPreview(family) {
        const selector = getFallbackSelector(family);
        const preview = document.querySelector(`[data-stack-preview="${CSS.escape(family)}"]`);

        if (selector && preview) {
            const stack = buildStack(family, selector.value);
            preview.textContent = stack;

            document.querySelectorAll(`[data-font-preview-family="${CSS.escape(family)}"]`).forEach((element) => {
                element.style.fontFamily = stack;
            });
        }
    }

    function updateGroupDetectedSuggestions(group) {
        uploadRows(group).forEach((row) => updateUploadDetectedSuggestion(row));
    }

    function setFamilyFallbackFeedback(form, message, tone) {
        const feedback = form ? form.querySelector('[data-family-fallback-feedback]') : null;

        if (!feedback) {
            return;
        }

        feedback.textContent = message || '';
        feedback.hidden = !message;
        feedback.classList.toggle('is-success', tone === 'success');
        feedback.classList.toggle('is-error', tone === 'error');
        feedback.classList.toggle('is-saving', tone === 'saving');
    }

    function setFamilyFontDisplayFeedback(form, message, tone) {
        const feedback = form ? form.querySelector('[data-family-font-display-feedback]') : null;

        if (!feedback) {
            return;
        }

        feedback.textContent = message || '';
        feedback.hidden = !message;
        feedback.classList.toggle('is-success', tone === 'success');
        feedback.classList.toggle('is-error', tone === 'error');
        feedback.classList.toggle('is-saving', tone === 'saving');
    }

    function setFamilyDeliveryFeedback(form, message, tone) {
        const feedback = form ? form.querySelector('[data-family-delivery-feedback]') : null;

        if (!feedback) {
            return;
        }

        feedback.textContent = message || '';
        feedback.hidden = !message;
        feedback.classList.toggle('is-success', tone === 'success');
        feedback.classList.toggle('is-error', tone === 'error');
        feedback.classList.toggle('is-saving', tone === 'saving');
    }

    function setFamilyPublishStateFeedback(form, message, tone) {
        const feedback = form ? form.querySelector('[data-family-publish-state-feedback]') : null;

        if (!feedback) {
            return;
        }

        feedback.textContent = message || '';
        feedback.hidden = !message;
        feedback.classList.toggle('is-success', tone === 'success');
        feedback.classList.toggle('is-error', tone === 'error');
        feedback.classList.toggle('is-saving', tone === 'saving');
    }

    function syncFamilyFallbackSaveState(form) {
        if (!form) {
            return;
        }

        const selector = form.querySelector('.tasty-fonts-fallback-selector');
        const button = form.querySelector('[data-family-fallback-save]');

        if (!selector || !button) {
            return;
        }

        const isDirty = (selector.dataset.savedValue || 'sans-serif') !== (selector.value || 'sans-serif');
        button.disabled = !isDirty;
    }

    function syncFamilyFontDisplaySaveState(form) {
        if (!form) {
            return;
        }

        const selector = form.querySelector('.tasty-fonts-font-display-selector');
        const button = form.querySelector('[data-family-font-display-save]');

        if (!selector || !button) {
            return;
        }

        const isDirty = (selector.dataset.savedValue || 'inherit') !== (selector.value || 'inherit');
        button.disabled = !isDirty;
    }

    function syncFamilyDeliverySaveState(form) {
        if (!form) {
            return;
        }

        const selector = form.querySelector('.tasty-fonts-family-delivery-selector');
        const button = form.querySelector('[data-family-delivery-save]');

        if (!selector || !button) {
            return;
        }

        button.disabled = (selector.dataset.savedValue || '') === (selector.value || '');
    }

    function syncFamilyPublishStateSaveState(form) {
        if (!form) {
            return;
        }

        const selector = form.querySelector('.tasty-fonts-family-publish-state-selector');
        const button = form.querySelector('[data-family-publish-state-save]');

        if (!selector || !button || selector.disabled) {
            return;
        }

        button.disabled = (selector.dataset.savedValue || 'published') === (selector.value || 'published');
    }

    function buildFallbackSavedMessage(family, message) {
        return message || formatMessage(getString('fallbackSaved', 'Saved fallback for %1$s.'), [family]);
    }

    function buildFallbackErrorMessage(error) {
        return getErrorMessage(error, getString('fallbackSaveError', 'The fallback could not be saved.'));
    }

    function buildFontDisplaySavedMessage(family, message) {
        return message || formatMessage(getString('fontDisplaySaved', 'Saved font display for %1$s.'), [family]);
    }

    function buildFontDisplayErrorMessage(error) {
        return getErrorMessage(error, getString('fontDisplaySaveError', 'The font-display override could not be saved.'));
    }

    function buildFamilyDeliveryErrorMessage(error) {
        return getErrorMessage(error, getString('familyDeliverySaveError', 'The live delivery could not be updated.'));
    }

    function buildFamilyPublishStateErrorMessage(error) {
        return getErrorMessage(error, getString('familyPublishStateSaveError', 'The publish state could not be updated.'));
    }

    function buildRoleDraftErrorMessage(error) {
        return getErrorMessage(error, getString('rolesDraftSaveError', 'The roles could not be saved.'));
    }

    // Family fallback persistence
    async function saveFamilyFallback(selector, form) {
        if (!selector) {
            return false;
        }

        const family = selector.dataset.fontFamily || '';
        const previousValue = selector.dataset.savedValue || 'sans-serif';
        const nextValue = selector.value || 'sans-serif';
        const saveForm = form || selector.closest('[data-family-fallback-form]');
        const button = saveForm ? saveForm.querySelector('[data-family-fallback-save]') : null;

        if (!family || !hasRestConfig() || previousValue === nextValue) {
            selector.dataset.savedValue = nextValue;
            syncFamilyFallbackSaveState(saveForm);
            return true;
        }

        const row = selector.closest('[data-font-row]');

        selector.disabled = true;
        selector.setAttribute('aria-busy', 'true');
        setFamilyFallbackFeedback(saveForm, getString('fallbackSaving', 'Saving fallback…'), 'saving');
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }

        if (row) {
            row.classList.add('is-saving');
        }

        try {
            const payload = await requestJson(getRoutePath('saveFamilyFallback', 'families/fallback'), {
                method: 'PATCH',
                body: {
                    family,
                    fallback: nextValue
                },
                fallbackMessage: getString('fallbackSaveError', 'The fallback could not be saved.')
            });
            const savedFallback = payload.fallback || nextValue;
            const message = buildFallbackSavedMessage(family, payload.message);

            selector.value = savedFallback;
            selector.dataset.savedValue = savedFallback;
            updateInlineStackPreview(family);
            syncFamilyFallbackSaveState(saveForm);
            setFamilyFallbackFeedback(saveForm, message, 'success');
            showToast(message, 'success');
            return true;
        } catch (error) {
            const message = buildFallbackErrorMessage(error);

            selector.value = previousValue;
            updateInlineStackPreview(family);
            syncFamilyFallbackSaveState(saveForm);
            setFamilyFallbackFeedback(saveForm, message, 'error');
            showToast(message, 'error');
            return false;
        } finally {
            selector.disabled = false;
            selector.removeAttribute('aria-busy');
            if (button) {
                button.removeAttribute('aria-busy');
            }

            if (row) {
                row.classList.remove('is-saving');
            }
        }
    }

    async function saveFamilyFontDisplay(selector, form) {
        if (!selector) {
            return false;
        }

        const family = selector.dataset.fontFamily || '';
        const previousValue = selector.dataset.savedValue || 'inherit';
        const nextValue = selector.value || 'inherit';
        const saveForm = form || selector.closest('[data-family-font-display-form]');
        const button = saveForm ? saveForm.querySelector('[data-family-font-display-save]') : null;

        if (!family || !hasRestConfig() || previousValue === nextValue) {
            selector.dataset.savedValue = nextValue;
            syncFamilyFontDisplaySaveState(saveForm);
            return true;
        }

        const row = selector.closest('[data-font-row]');

        selector.disabled = true;
        selector.setAttribute('aria-busy', 'true');
        setFamilyFontDisplayFeedback(saveForm, getString('fontDisplaySaving', 'Saving font display…'), 'saving');
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }

        if (row) {
            row.classList.add('is-saving');
        }

        try {
            const payload = await requestJson(getRoutePath('saveFamilyFontDisplay', 'families/font-display'), {
                method: 'PATCH',
                body: {
                    family,
                    font_display: nextValue
                },
                fallbackMessage: getString('fontDisplaySaveError', 'The font-display override could not be saved.')
            });
            const savedDisplay = payload.font_display || nextValue;
            const message = buildFontDisplaySavedMessage(family, payload.message);

            selector.value = savedDisplay;
            selector.dataset.savedValue = savedDisplay;
            syncFamilyFontDisplaySaveState(saveForm);
            setFamilyFontDisplayFeedback(saveForm, message, 'success');
            showToast(message, 'success');
            return true;
        } catch (error) {
            const message = buildFontDisplayErrorMessage(error);

            selector.value = previousValue;
            syncFamilyFontDisplaySaveState(saveForm);
            setFamilyFontDisplayFeedback(saveForm, message, 'error');
            showToast(message, 'error');
            return false;
        } finally {
            selector.disabled = false;
            selector.removeAttribute('aria-busy');
            if (button) {
                button.removeAttribute('aria-busy');
            }

            if (row) {
                row.classList.remove('is-saving');
            }
        }
    }

    async function saveFamilyDelivery(selector, form) {
        if (!selector) {
            return false;
        }

        const familySlug = selector.dataset.familySlug || '';
        const previousValue = selector.dataset.savedValue || '';
        const nextValue = selector.value || '';
        const saveForm = form || selector.closest('[data-family-delivery-form]');
        const button = saveForm ? saveForm.querySelector('[data-family-delivery-save]') : null;

        if (!familySlug || !nextValue || !hasRestConfig() || previousValue === nextValue) {
            selector.dataset.savedValue = nextValue;
            syncFamilyDeliverySaveState(saveForm);
            return true;
        }

        selector.disabled = true;
        selector.setAttribute('aria-busy', 'true');
        setFamilyDeliveryFeedback(saveForm, getString('familyDeliverySaving', 'Switching live delivery…'), 'saving');

        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }

        try {
            const payload = await requestJson(getRoutePath('saveFamilyDelivery', 'families/delivery'), {
                method: 'PATCH',
                body: {
                    family_slug: familySlug,
                    delivery_id: nextValue
                },
                fallbackMessage: getString('familyDeliverySaveError', 'The live delivery could not be updated.')
            });
            const message = payload.message || getString('familyDeliverySaved', 'Live delivery updated.');

            selector.dataset.savedValue = nextValue;
            syncFamilyDeliverySaveState(saveForm);
            setFamilyDeliveryFeedback(saveForm, message, 'success');
            showToast(message, 'success');
            reloadPageSoon(600);
            return true;
        } catch (error) {
            const message = buildFamilyDeliveryErrorMessage(error);

            selector.value = previousValue;
            syncFamilyDeliverySaveState(saveForm);
            setFamilyDeliveryFeedback(saveForm, message, 'error');
            showToast(message, 'error');
            return false;
        } finally {
            selector.disabled = false;
            selector.removeAttribute('aria-busy');

            if (button) {
                button.removeAttribute('aria-busy');
            }
        }
    }

    async function saveFamilyPublishState(selector, form) {
        if (!selector) {
            return false;
        }

        const familySlug = selector.dataset.familySlug || '';
        const previousValue = selector.dataset.savedValue || 'published';
        const nextValue = selector.value || 'published';
        const saveForm = form || selector.closest('[data-family-publish-state-form]');
        const button = saveForm ? saveForm.querySelector('[data-family-publish-state-save]') : null;

        if (!familySlug || !hasRestConfig() || previousValue === nextValue) {
            selector.dataset.savedValue = nextValue;
            syncFamilyPublishStateSaveState(saveForm);
            return true;
        }

        selector.disabled = true;
        selector.setAttribute('aria-busy', 'true');
        setFamilyPublishStateFeedback(saveForm, getString('familyPublishStateSaving', 'Updating publish state…'), 'saving');

        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }

        try {
            const payload = await requestJson(getRoutePath('saveFamilyPublishState', 'families/publish-state'), {
                method: 'PATCH',
                body: {
                    family_slug: familySlug,
                    publish_state: nextValue
                },
                fallbackMessage: getString('familyPublishStateSaveError', 'The publish state could not be updated.')
            });
            const message = payload.message || getString('familyPublishStateSaved', 'Publish state updated.');

            selector.dataset.savedValue = nextValue;
            syncFamilyPublishStateSaveState(saveForm);
            setFamilyPublishStateFeedback(saveForm, message, 'success');
            showToast(message, 'success');
            reloadPageSoon(600);
            return true;
        } catch (error) {
            const message = buildFamilyPublishStateErrorMessage(error);

            selector.value = previousValue;
            syncFamilyPublishStateSaveState(saveForm);
            setFamilyPublishStateFeedback(saveForm, message, 'error');
            showToast(message, 'error');
            return false;
        } finally {
            selector.disabled = false;
            selector.removeAttribute('aria-busy');

            if (button) {
                button.removeAttribute('aria-busy');
            }
        }
    }

    async function deleteDeliveryProfile(button) {
        if (!button || !hasRestConfig()) {
            return false;
        }

        const blockedMessage = button.getAttribute('data-delete-blocked');

        if (blockedMessage) {
            showToast(blockedMessage, 'error');
            return false;
        }

        const familySlug = button.getAttribute('data-family-slug') || '';
        const familyName = button.getAttribute('data-family-name') || '';
        const deliveryId = button.getAttribute('data-delivery-id') || '';
        const deliveryLabel = button.getAttribute('data-delivery-label') || '';

        if (!familySlug || !deliveryId) {
            return false;
        }

        const confirmTemplate = getString('deliveryDeleteConfirm', 'Delete the "%1$s" delivery from %2$s?');
        const confirmMessage = confirmTemplate
            .replace('%1$s', deliveryLabel || 'selected')
            .replace('%2$s', familyName || 'this family');

        if (!window.confirm(confirmMessage)) {
            return false;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');

        try {
            const payload = await requestJson(getRoutePath('deleteDeliveryProfile', 'families/delivery-profile'), {
                method: 'DELETE',
                query: {
                    family_slug: familySlug,
                    delivery_id: deliveryId
                },
                fallbackMessage: getString('deliveryDeleteError', 'The delivery profile could not be deleted.')
            });
            const message = payload.message || getString('familyDeliverySaved', 'Live delivery updated.');

            showToast(message, 'success');
            reloadPageSoon(600);
            return true;
        } catch (error) {
            showToast(getErrorMessage(error, getString('deliveryDeleteError', 'The delivery profile could not be deleted.')), 'error');
            return false;
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    }

    async function saveRoleDraft(snapshotBeforeChange) {
        if (!hasRestConfig() || !window.fetch || roleDraftSaveInFlight) {
            return false;
        }

        setRoleDraftSavingState(true);

        try {
            const requestBody = {
                heading: getElementValue(roleHeading, ''),
                body: getElementValue(roleBody, ''),
                heading_fallback: getElementValue(roleHeadingFallback, 'sans-serif'),
                body_fallback: getElementValue(roleBodyFallback, 'sans-serif')
            };

            if (monospaceRoleEnabled) {
                requestBody.monospace = getElementValue(roleMonospace, '');
                requestBody.monospace_fallback = getElementValue(roleMonospaceFallback, 'monospace');
            }

            const payload = await requestJson(getRoutePath('saveRoleDraft', 'roles/draft'), {
                method: 'PATCH',
                body: requestBody,
                fallbackMessage: getString('rolesDraftSaveError', 'The roles could not be saved.')
            });
            const roles = payload.roles || {};

            if (roleHeading && typeof roles.heading === 'string') {
                roleHeading.value = roles.heading;
            }

            if (roleBody && typeof roles.body === 'string') {
                roleBody.value = roles.body;
            }

            if (roleHeadingFallback && typeof roles.heading_fallback === 'string') {
                roleHeadingFallback.value = roles.heading_fallback;
            }

            if (roleBodyFallback && typeof roles.body_fallback === 'string') {
                roleBodyFallback.value = roles.body_fallback;
            }

            if (monospaceRoleEnabled && roleMonospace && typeof roles.monospace === 'string') {
                roleMonospace.value = roles.monospace;
            }

            if (monospaceRoleEnabled && roleMonospaceFallback && typeof roles.monospace_fallback === 'string') {
                roleMonospaceFallback.value = roles.monospace_fallback;
            }

            updateRoleOutputs();
            syncRoleDeploymentState(payload.role_deployment || null);
            showToast(payload.message || getString('rolesDraftSaved', 'Roles saved.'), 'success');
            return true;
        } catch (error) {
            restoreRoleFieldSnapshot(snapshotBeforeChange);
            updateRoleOutputs();
            showToast(buildRoleDraftErrorMessage(error), 'error');
            return false;
        } finally {
            setRoleDraftSavingState(false);
        }
    }

    function markCopyButton(button) {
        if (!button) {
            return;
        }

        if (button.copyFeedbackTimeoutId) {
            window.clearTimeout(button.copyFeedbackTimeoutId);
        }

        button.classList.add('is-copied');
        button.copyFeedbackTimeoutId = window.setTimeout(() => {
            button.classList.remove('is-copied');
            button.copyFeedbackTimeoutId = 0;
        }, 1200);
    }

    function copyText(text, button, options = {}) {
        if (!text || !navigator.clipboard) {
            return;
        }

        navigator.clipboard.writeText(text).then(() => {
            markCopyButton(button);

            if (options.toastMessage) {
                showToast(options.toastMessage, 'success');
            }

            if (options.preserveLabel) {
                return;
            }

            if (!button) {
                return;
            }

            const original = button.textContent;
            button.textContent = getString('copied', 'Copied');
            window.setTimeout(() => {
                button.textContent = original;
            }, 1200);
        });
    }

    // Google search and import
    function renderVariantOptions(variants, familyName = '') {
        if (!variantsWrap) {
            return;
        }

        const nextFamilyKey = normalizeFamilyKey(familyName || currentGoogleImportFamily());
        const familyChanged = nextFamilyKey !== '' && nextFamilyKey !== renderedGoogleVariantFamily;
        const seededTokens = manualVariants ? normalizeVariantTokens(manualVariants.value) : [];
        const preserveEmptySelection = !!(manualVariants && manualVariants.dataset.explicitEmpty === 'true' && !familyChanged);

        variantsWrap.innerHTML = '';

        (variants || []).forEach((variant) => {
            const label = document.createElement('label');
            const input = document.createElement('input');
            const mark = document.createElement('span');
            const text = document.createElement('span');

            label.className = 'tasty-fonts-variant-chip';
            input.type = 'checkbox';
            input.value = variant;
            mark.className = 'tasty-fonts-variant-chip-mark';
            mark.setAttribute('aria-hidden', 'true');
            text.className = 'tasty-fonts-variant-chip-label';
            text.textContent = variant;

            label.append(input, mark, text);
            variantsWrap.appendChild(label);
        });

        if (seededTokens.length > 0) {
            if (manualVariants) {
                delete manualVariants.dataset.explicitEmpty;
            }
            syncVariantChipsFromManualInput();
        } else if (preserveEmptySelection) {
            availableVariantInputs().forEach((input) => {
                input.checked = false;
                syncVariantChipState(input);
            });

            if (manualVariants) {
                manualVariants.value = '';
                manualVariants.dataset.explicitEmpty = 'true';
            }
        } else {
            const inputs = availableVariantInputs();
            const preferredInputs = inputs.filter((input) => {
                const value = String(input.value).toLowerCase();

                return value === 'regular' || value === '700';
            });
            const normalInputs = inputs.filter((input) => !String(input.value).toLowerCase().includes('italic'));
            const defaultSelection = preferredInputs.length > 0
                ? preferredInputs
                : (normalInputs.length > 0 ? normalInputs.slice(0, Math.min(2, normalInputs.length)) : inputs.slice(0, 1));

            inputs.forEach((input) => {
                input.checked = defaultSelection.includes(input);
                syncVariantChipState(input);
            });
            if (manualVariants) {
                delete manualVariants.dataset.explicitEmpty;
            }
            syncManualVariantsInputFromChips();
        }

        renderedGoogleVariantFamily = nextFamilyKey;
        updateGoogleImportSummary();
    }

    function renderSearchResults(items) {
        if (!googleResults) {
            return;
        }

        searchResults = items || [];
        googleResults.innerHTML = '';

        if (!searchResults.length) {
            updateGooglePreviewStylesheet([]);
            googleResults.innerHTML = `<div class="tasty-fonts-empty">${getString('searchEmpty', 'No Google Fonts families matched that search.')}</div>`;
            return;
        }

        updateGooglePreviewStylesheet(searchResults);

        searchResults.forEach((item) => {
            const card = document.createElement('article');
            const head = document.createElement('div');
            const title = document.createElement('h3');
            const preview = document.createElement('div');
            const meta = document.createElement('div');
            const category = document.createElement('span');
            const variants = document.createElement('span');
            const fallback = googlePreviewFallback(item.category);
            const inLibrary = isFamilyInLibrary(item.family || '');
            const variantCount = Number(item.variants_count || (Array.isArray(item.variants) ? item.variants.length : 0));

            card.className = 'tasty-fonts-search-card';
            card.dataset.family = item.family;
            card.dataset.searchProvider = 'google';
            card.setAttribute('role', 'button');
            card.setAttribute('tabindex', '0');
            card.setAttribute('aria-label', formatMessage(getString('searchResultSelectLabel', 'Select %s'), [item.family]));
            card.setAttribute('aria-pressed', !!selectedSearchFamily && selectedSearchFamily.family === item.family ? 'true' : 'false');
            card.classList.toggle('is-active', !!selectedSearchFamily && selectedSearchFamily.family === item.family);

            head.className = 'tasty-fonts-search-card-head';
            title.className = 'tasty-fonts-search-card-title';
            title.textContent = item.family;
            head.appendChild(title);

            if (inLibrary) {
                const badges = document.createElement('div');
                const badge = document.createElement('span');

                badges.className = 'tasty-fonts-badges tasty-fonts-badges--import tasty-fonts-search-card-badges';
                badge.className = 'tasty-fonts-badge is-success';
                badge.textContent = getString('searchResultInLibrary', 'In Library');
                badges.appendChild(badge);
                head.appendChild(badges);
            }

            preview.className = 'tasty-fonts-search-card-preview';
            preview.textContent = googlePreviewText();
            preview.style.fontFamily = `"${item.family}", ${fallback}`;

            meta.className = 'tasty-fonts-search-card-meta tasty-fonts-muted';
            category.textContent = item.category || fallback;
            variants.textContent = formatPluralMessage('%d variant', '%d variants', variantCount, [variantCount]);

            meta.append(category, variants);
            card.append(head, preview, meta);
            googleResults.appendChild(card);
        });

        const matchedFamily = findGoogleFamilyMatch(currentGoogleImportFamily());

        if (matchedFamily) {
            selectedSearchFamily = matchedFamily;

            if (!availableVariantInputs().length) {
                if (Array.isArray(matchedFamily.variants) && matchedFamily.variants.length > 0) {
                    renderVariantOptions(matchedFamily.variants || [], matchedFamily.family || '');
                } else {
                    void loadGoogleFamilyDetails(matchedFamily.family || currentGoogleImportFamily());
                }
            }
        }
    }

    function selectSearchFamily(familyName) {
        selectedSearchFamily = searchResults.find((item) => item.family === familyName) || null;

        if (googleResults) {
            googleResults.querySelectorAll('.tasty-fonts-search-card').forEach((card) => {
                const isActive = card.dataset.family === familyName;
                card.classList.toggle('is-active', isActive);
                card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        }

        if (manualFamily) {
            manualFamily.value = familyName || '';
        }

        updateSelectedFamilyLabel(familyName);

        if (selectedSearchFamily && Array.isArray(selectedSearchFamily.variants) && selectedSearchFamily.variants.length > 0) {
            renderVariantOptions(selectedSearchFamily.variants, familyName);
            return;
        }

        renderedGoogleVariantFamily = '';

        if (variantsWrap) {
            variantsWrap.innerHTML = '';
        }

        updateGoogleImportSummary();
        void loadGoogleFamilyDetails(familyName);
    }

    function renderBunnyVariantOptions(variants, familyName = '') {
        if (!bunnyVariantsWrap) {
            return;
        }

        const nextFamilyKey = normalizeFamilyKey(familyName || currentBunnyImportFamily());
        const familyChanged = nextFamilyKey !== '' && nextFamilyKey !== renderedBunnyVariantFamily;
        const seededTokens = bunnyVariants ? normalizeHostedVariantTokens(bunnyVariants.value) : [];
        const preserveEmptySelection = !!(bunnyVariants && bunnyVariants.dataset.explicitEmpty === 'true' && !familyChanged);

        bunnyVariantsWrap.innerHTML = '';

        (variants || []).forEach((variant) => {
            const label = document.createElement('label');
            const input = document.createElement('input');
            const mark = document.createElement('span');
            const text = document.createElement('span');

            label.className = 'tasty-fonts-variant-chip';
            input.type = 'checkbox';
            input.value = variant;
            mark.className = 'tasty-fonts-variant-chip-mark';
            mark.setAttribute('aria-hidden', 'true');
            text.className = 'tasty-fonts-variant-chip-label';
            text.textContent = variant;

            label.append(input, mark, text);
            bunnyVariantsWrap.appendChild(label);
        });

        if (seededTokens.length > 0) {
            if (bunnyVariants) {
                delete bunnyVariants.dataset.explicitEmpty;
            }
            syncBunnyVariantChipsFromManualInput();
        } else if (preserveEmptySelection) {
            availableBunnyVariantInputs().forEach((input) => {
                input.checked = false;
                syncVariantChipState(input);
            });

            if (bunnyVariants) {
                bunnyVariants.value = '';
                bunnyVariants.dataset.explicitEmpty = 'true';
            }
        } else {
            const inputs = availableBunnyVariantInputs();
            const preferredInputs = inputs.filter((input) => {
                const value = String(input.value).toLowerCase();

                return value === 'regular' || value === '700';
            });
            const normalInputs = inputs.filter((input) => !String(input.value).toLowerCase().includes('italic'));
            const defaultSelection = preferredInputs.length > 0
                ? preferredInputs
                : (normalInputs.length > 0 ? normalInputs.slice(0, Math.min(2, normalInputs.length)) : inputs.slice(0, 1));

            inputs.forEach((input) => {
                input.checked = defaultSelection.includes(input);
                syncVariantChipState(input);
            });

            if (bunnyVariants) {
                delete bunnyVariants.dataset.explicitEmpty;
            }

            syncBunnyManualVariantsInputFromChips();
        }

        renderedBunnyVariantFamily = nextFamilyKey;
        updateBunnyImportSummary();
    }

    function renderBunnySearchResults(items) {
        if (!bunnyResults) {
            return;
        }

        bunnySearchResults = items || [];
        bunnyResults.innerHTML = '';

        if (!bunnySearchResults.length) {
            updateBunnySearchPreviewStylesheet([]);
            bunnyResults.innerHTML = `<div class="tasty-fonts-empty">${getString('bunnySearchEmpty', 'No Bunny Fonts families matched that search.')}</div>`;
            return;
        }

        updateBunnySearchPreviewStylesheet(bunnySearchResults);

        bunnySearchResults.forEach((item) => {
            const card = document.createElement('article');
            const head = document.createElement('div');
            const title = document.createElement('h3');
            const preview = document.createElement('div');
            const meta = document.createElement('div');
            const category = document.createElement('span');
            const variants = document.createElement('span');
            const fallback = googlePreviewFallback(item.category);
            const styleCount = Number(item.style_count || (Array.isArray(item.variants) ? item.variants.length : 0));
            const isActive = !!selectedBunnySearchFamily && matchesBunnyFamilyEntry(selectedBunnySearchFamily, item.family || item.slug || '');
            const inLibrary = isFamilyInLibrary(item.family || '', item.slug || '');

            card.className = 'tasty-fonts-search-card';
            card.dataset.family = item.family || '';
            card.dataset.searchProvider = 'bunny';
            card.setAttribute('role', 'button');
            card.setAttribute('tabindex', '0');
            card.setAttribute('aria-label', formatMessage(getString('searchResultSelectLabel', 'Select %s'), [item.family || '']));
            card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            card.classList.toggle('is-active', isActive);

            head.className = 'tasty-fonts-search-card-head';
            title.className = 'tasty-fonts-search-card-title';
            title.textContent = item.family || '';
            head.appendChild(title);

            if (inLibrary) {
                const badges = document.createElement('div');
                const badge = document.createElement('span');

                badges.className = 'tasty-fonts-badges tasty-fonts-badges--import tasty-fonts-search-card-badges';
                badge.className = 'tasty-fonts-badge is-success';
                badge.textContent = getString('searchResultInLibrary', 'In Library');
                badges.appendChild(badge);
                head.appendChild(badges);
            }

            preview.className = 'tasty-fonts-search-card-preview';
            preview.textContent = googlePreviewText();
            preview.style.fontFamily = `"${item.family}", ${fallback}`;

            meta.className = 'tasty-fonts-search-card-meta tasty-fonts-muted';
            category.textContent = item.category_label || 'Bunny Fonts';
            variants.textContent = styleCount > 0
                ? formatPluralMessage('%d variant', '%d variants', styleCount, [styleCount])
                : __('Bunny Fonts', 'tasty-fonts');

            meta.append(category, variants);
            card.append(head, preview, meta);
            bunnyResults.appendChild(card);
        });

        const matchedFamily = findBunnyFamilyMatch(currentBunnyImportFamily());

        if (matchedFamily) {
            selectedBunnySearchFamily = matchedFamily;

            if (!availableBunnyVariantInputs().length) {
                renderBunnyVariantOptions(matchedFamily.variants || [], matchedFamily.family || '');
            }
        }
    }

    function selectBunnySearchFamily(familyName) {
        selectedBunnySearchFamily = bunnySearchResults.find((item) => matchesBunnyFamilyEntry(item, familyName)) || null;

        if (bunnyResults) {
            bunnyResults.querySelectorAll('.tasty-fonts-search-card').forEach((card) => {
                const isActive = selectedBunnySearchFamily
                    ? matchesBunnyFamilyEntry(selectedBunnySearchFamily, card.dataset.family || '')
                    : false;
                card.classList.toggle('is-active', isActive);
                card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        }

        if (bunnyFamily) {
            bunnyFamily.value = selectedBunnySearchFamily ? (selectedBunnySearchFamily.family || familyName || '') : (familyName || '');
        }

        renderBunnyVariantOptions(selectedBunnySearchFamily ? selectedBunnySearchFamily.variants : [], familyName);
    }

    function applyBunnyVariantQuickSelection(mode) {
        const inputs = availableBunnyVariantInputs();

        if (!inputs.length) {
            return;
        }

        inputs.forEach((input) => {
            const value = String(input.value).toLowerCase();
            let shouldCheck = false;

            switch (mode) {
                case 'all':
                    shouldCheck = true;
                    break;
                case 'normal':
                    shouldCheck = !value.includes('italic');
                    break;
                case 'italic':
                    shouldCheck = value.includes('italic');
                    break;
                case 'clear':
                    shouldCheck = false;
                    break;
                default:
                    shouldCheck = input.checked;
                    break;
            }

            input.checked = shouldCheck;
            syncVariantChipState(input);
        });

        if (bunnyVariants) {
            if (mode === 'clear') {
                bunnyVariants.dataset.explicitEmpty = 'true';
            } else {
                delete bunnyVariants.dataset.explicitEmpty;
            }
        }

        syncBunnyManualVariantsInputFromChips();
        updateBunnyImportSummary();
    }

    // Local upload builder
    function uploadGroups() {
        return uploadGroupsWrap ? Array.from(uploadGroupsWrap.querySelectorAll('[data-upload-group]')) : [];
    }

    function uploadRows(group) {
        const scope = group || uploadGroupsWrap;

        return scope ? Array.from(scope.querySelectorAll('[data-upload-row]')) : [];
    }

    function titleCaseWords(value) {
        return value.replace(/\b[a-z]/g, (match) => match.toUpperCase());
    }

    function detectUploadMetadata(fileName) {
        if (!fileName) {
            return null;
        }

        const baseName = fileName.replace(/\.[^.]+$/, '').trim();

        if (!baseName) {
            return null;
        }

        const normalized = baseName
            .replace(/[_]+/g, '-')
            .replace(/\s+/g, '-')
            .replace(/([a-z])([A-Z])/g, '$1-$2');
        const tokens = normalized.split('-').filter(Boolean);

        if (!tokens.length) {
            return null;
        }

        const weightMap = {
            thin: '100',
            extralight: '200',
            ultralight: '200',
            light: '300',
            regular: '400',
            book: '400',
            normal: '400',
            medium: '500',
            semibold: '600',
            demibold: '600',
            bold: '700',
            extrabold: '800',
            ultrabold: '800',
            black: '900',
            heavy: '900'
        };
        let style = 'normal';
        let weight = '400';
        let workingTokens = [...tokens];

        const lastToken = workingTokens[workingTokens.length - 1].toLowerCase();

        if (lastToken === 'italic' || lastToken === 'oblique') {
            style = lastToken;
            workingTokens.pop();
        }

        const trailingWeight = workingTokens[workingTokens.length - 1];

        if (/^[1-9]00$/.test(trailingWeight || '')) {
            weight = trailingWeight;
            workingTokens.pop();
        } else if (trailingWeight && weightMap[trailingWeight.toLowerCase()]) {
            weight = weightMap[trailingWeight.toLowerCase()];
            workingTokens.pop();
        }

        const family = titleCaseWords(workingTokens.join(' ').trim());

        if (!family) {
            return null;
        }

        return { family, weight, style };
    }

    function familyInputForGroup(group) {
        return group ? group.querySelector('[data-upload-group-field="family"]') : null;
    }

    function fallbackInputForGroup(group) {
        return group ? group.querySelector('[data-upload-group-field="fallback"]') : null;
    }

    function assignUploadGroupIndex(group) {
        if (!group) {
            return;
        }

        if (!group.hasAttribute('data-upload-group-index')) {
            group.setAttribute('data-upload-group-index', String(nextUploadGroupIndex));
            nextUploadGroupIndex += 1;
        }
    }

    function assignUploadRowIndex(row) {
        if (!row) {
            return;
        }

        if (!row.hasAttribute('data-upload-index')) {
            row.setAttribute('data-upload-index', String(nextUploadRowIndex));
            nextUploadRowIndex += 1;
        }
    }

    function updateUploadRemoveButtons() {
        const groups = uploadGroups();

        groups.forEach((group) => {
            const removeGroup = group.querySelector('[data-upload-remove-group]');
            const addFace = group.querySelector('[data-upload-add-face]');
            const rows = uploadRows(group);

            if (removeGroup) {
                removeGroup.disabled = groups.length <= 1 || uploadInFlight;
            }

            if (addFace) {
                addFace.disabled = uploadInFlight;
            }

            rows.forEach((row) => {
                const button = row.querySelector('[data-upload-remove]');

                if (!button) {
                    return;
                }

                button.disabled = rows.length <= 1 || uploadInFlight;
            });
        });
    }

    function setUploadRowStatus(row, status, message) {
        if (!row) {
            return;
        }

        const target = row.querySelector('[data-upload-row-status]');

        if (!target) {
            return;
        }

        target.textContent = message || '';
        target.classList.remove('is-queued', 'is-uploading', 'is-imported', 'is-skipped', 'is-error');

        if (status) {
            target.classList.add(`is-${status}`);
        }
    }

    function updateUploadDetectedSuggestion(row) {
        if (!row) {
            return null;
        }

        const group = row.closest('[data-upload-group]');
        const familyField = familyInputForGroup(group);
        const weightField = row.querySelector('[data-upload-field="weight"]');
        const styleField = row.querySelector('[data-upload-field="style"]');
        const fileField = row.querySelector('[data-upload-field="file"]');
        const button = row.querySelector('[data-upload-detected-apply]');
        const file = fileField && fileField.files ? fileField.files[0] : null;
        const detected = file ? detectUploadMetadata(file.name) : null;

        if (!button) {
            return null;
        }

        if (!detected) {
            button.hidden = true;
            delete button.dataset.detectedFamily;
            delete button.dataset.detectedWeight;
            delete button.dataset.detectedStyle;
            return null;
        }

        const familyValue = familyField ? familyField.value.trim() : '';
        const weightValue = weightField ? weightField.value : '400';
        const styleValue = styleField ? styleField.value : 'normal';
        const shouldShowFamily = familyValue === '' || familyValue.toLowerCase() === detected.family.toLowerCase();
        const label = shouldShowFamily
            ? formatMessage(getString('uploadDetectedSummary', 'Detected: %1$s / %2$s / %3$s'), [detected.family, detected.weight, detected.style])
            : formatMessage(getString('uploadDetectedWeightStyle', 'Detected: %1$s / %2$s'), [detected.weight, detected.style]);
        const alreadyApplied = weightValue === detected.weight
            && styleValue === detected.style
            && (!shouldShowFamily || familyValue.toLowerCase() === detected.family.toLowerCase());

        button.hidden = alreadyApplied;
        button.dataset.detectedFamily = detected.family;
        button.dataset.detectedWeight = detected.weight;
        button.dataset.detectedStyle = detected.style;
        button.textContent = `${getString('uploadUseDetected', 'Use Detected Values')} · ${label}`;

        return detected;
    }

    function updateUploadFileName(row) {
        if (!row) {
            return;
        }

        const fileField = row.querySelector('[data-upload-field="file"]');
        const fileName = row.querySelector('[data-upload-file-name]');
        const file = fileField && fileField.files ? fileField.files[0] : null;

        if (!fileName) {
            return;
        }

        fileName.textContent = file ? file.name : getString('uploadNoFile', 'No file chosen');
        row.classList.toggle('has-file', !!file);
    }

    function initializeUploadRow(row) {
        if (!row) {
            return;
        }

        assignUploadRowIndex(row);
        setUploadRowStatus(row, '', '');
        updateUploadFileName(row);
        updateUploadDetectedSuggestion(row);
    }

    function initializeUploadGroup(group) {
        if (!group) {
            return;
        }

        assignUploadGroupIndex(group);

        const rows = uploadRows(group);

        if (!rows.length) {
            createUploadRow(group);
            return;
        }

        rows.forEach((row) => initializeUploadRow(row));
        updateUploadRemoveButtons();
    }

    function createUploadRow(group, options = {}) {
        if (!group || !uploadRowTemplate) {
            return null;
        }

        const faceList = group.querySelector('[data-upload-face-list]');

        if (!faceList) {
            return null;
        }

        const fragment = uploadRowTemplate.content.cloneNode(true);
        const row = fragment.querySelector('[data-upload-row]');

        if (!row) {
            return null;
        }

        const weightField = row.querySelector('[data-upload-field="weight"]');
        const styleField = row.querySelector('[data-upload-field="style"]');

        if (weightField && options.weight) {
            weightField.value = options.weight;
        }

        if (styleField && options.style) {
            styleField.value = options.style;
        }

        faceList.appendChild(fragment);
        const appendedRow = faceList.lastElementChild;

        initializeUploadRow(appendedRow);
        updateUploadRemoveButtons();

        return appendedRow;
    }

    function createUploadGroup(options = {}) {
        if (!uploadGroupsWrap || !uploadGroupTemplate) {
            return null;
        }

        const fragment = uploadGroupTemplate.content.cloneNode(true);
        const group = fragment.querySelector('[data-upload-group]');

        if (!group) {
            return null;
        }

        uploadGroupsWrap.appendChild(fragment);
        const appendedGroup = uploadGroupsWrap.lastElementChild;

        initializeUploadGroup(appendedGroup);

        const familyField = familyInputForGroup(appendedGroup);
        const fallbackField = fallbackInputForGroup(appendedGroup);

        if (familyField && options.family) {
            familyField.value = options.family;
        }

        if (fallbackField && options.fallback) {
            fallbackField.value = options.fallback;
        }

        uploadRows(appendedGroup).forEach((row) => updateUploadDetectedSuggestion(row));

        return appendedGroup;
    }

    function lastUploadGroupSeed() {
        const groups = uploadGroups();
        const lastGroup = groups[groups.length - 1];

        if (!lastGroup) {
            return {};
        }

        const family = familyInputForGroup(lastGroup);
        const fallback = fallbackInputForGroup(lastGroup);

        return {
            family: '',
            fallback: fallback ? fallback.value : 'sans-serif'
        };
    }

    function initUploadRows() {
        if (!uploadGroupsWrap) {
            return;
        }

        if (!uploadGroups().length) {
            createUploadGroup();
        }

        uploadGroups().forEach((group) => initializeUploadGroup(group));
        updateUploadRemoveButtons();
    }

    function buildUploadRequestData(groups) {
        const formData = new FormData();
        let hasAnyFile = false;
        let rowIndex = 0;

        groups.forEach((group) => {
            const family = familyInputForGroup(group);
            const fallback = fallbackInputForGroup(group);

            uploadRows(group).forEach((row) => {
                const weight = row.querySelector('[data-upload-field="weight"]');
                const style = row.querySelector('[data-upload-field="style"]');
                const file = row.querySelector('[data-upload-field="file"]');
                const selectedFile = file && file.files ? file.files[0] : null;

                formData.append(`rows[${rowIndex}][family]`, family ? family.value.trim() : '');
                formData.append(`rows[${rowIndex}][weight]`, weight ? weight.value : '400');
                formData.append(`rows[${rowIndex}][style]`, style ? style.value : 'normal');
                formData.append(`rows[${rowIndex}][fallback]`, fallback ? fallback.value : 'sans-serif');

                if (selectedFile) {
                    hasAnyFile = true;
                    formData.append(`files[${rowIndex}]`, selectedFile, selectedFile.name);
                }

                row.dataset.uploadSubmitIndex = String(rowIndex);
                setUploadRowStatus(row, 'queued', getString('uploadRowQueued', 'Queued'));
                rowIndex += 1;
            });
        });

        return { formData, hasAnyFile };
    }

    function setUploadBusyState(isBusy) {
        if (uploadSubmit) {
            uploadSubmit.disabled = isBusy;

            if (isBusy) {
                uploadSubmit.setAttribute('aria-busy', 'true');
                uploadSubmit.textContent = getString('uploadButtonBusy', 'Uploading…');
            } else {
                uploadSubmit.removeAttribute('aria-busy');
                uploadSubmit.textContent = getString('uploadButtonIdle', 'Upload to Library');
            }
        }

        if (uploadAddFamily) {
            uploadAddFamily.disabled = isBusy;
        }

        updateUploadRemoveButtons();
    }

    function applyUploadResults(rows, results) {
        const resultMap = new Map(results.map((result) => [String(result.index), result]));

        rows.forEach((row) => {
            const result = resultMap.get(row.dataset.uploadSubmitIndex || '');

            if (!result) {
                setUploadRowStatus(row, 'error', getString('uploadRowError', 'Error'));
                return;
            }

            setUploadRowStatus(row, result.status || '', result.message || '');
        });
    }

    async function uploadLocalFonts() {
        if (!uploadForm || !uploadGroupsWrap || !hasRestConfig() || uploadInFlight) {
            return;
        }

        const groups = uploadGroups();
        const rows = uploadRows();

        if (!rows.length) {
            setStatus(uploadStatus, getString('uploadRequiresRows', 'Add at least one upload row before submitting.'), 'error');
            return;
        }

        const { formData, hasAnyFile } = buildUploadRequestData(groups);

        if (!hasAnyFile) {
            setStatus(uploadStatus, getString('uploadError', 'The font upload failed.'), 'error');
            return;
        }

        uploadInFlight = true;
        setUploadBusyState(true);

        try {
            rows.forEach((row) => setUploadRowStatus(row, 'uploading', getString('uploadRowUploading', 'Uploading…')));
            setStatus(uploadStatus, getString('uploadSubmitting', 'Uploading font files…'), 'progress', 0);

            const payload = await requestMultipart(
                getRoutePath('uploadLocal', 'local/upload'),
                formData,
                (progress) => {
                    setStatus(
                        uploadStatus,
                        formatMessage(getString('uploadProgress', 'Uploading files… %1$d%%'), [progress]),
                        'progress',
                        progress
                    );
                },
                getString('uploadError', 'The font upload failed.')
            );
            const results = Array.isArray(payload.rows) ? payload.rows : [];
            applyUploadResults(rows, results);

            setStatus(uploadStatus, payload.message || getString('uploadSuccess', 'Upload complete. Refreshing the library…'), 'success', 100);

            if ((payload.summary && payload.summary.imported > 0) || (Array.isArray(payload.families) && payload.families.length > 0)) {
                reloadPageSoon();
            }
        } catch (error) {
            const message = getErrorMessage(error, getString('uploadError', 'The font upload failed.'));

            setStatus(uploadStatus, message, 'error');
            showToast(message, 'error');
        } finally {
            uploadInFlight = false;
            setUploadBusyState(false);
        }
    }

    async function runGoogleSearch(query) {
        if (!googleResults) {
            return;
        }

        if (!config.googleApiEnabled) {
            updateGooglePreviewStylesheet([]);
            googleResults.innerHTML = `<div class="tasty-fonts-empty">${getString('searchDisabled', '')}</div>`;
            return;
        }

        if (query.trim().length < 2) {
            updateGooglePreviewStylesheet([]);
            googleResults.innerHTML = '';
            return;
        }

        googleResults.innerHTML = `<div class="tasty-fonts-empty">${getString('searching', 'Searching Google Fonts…')}</div>`;

        try {
            const payload = await requestJson(getRoutePath('searchGoogle', 'google/search'), {
                method: 'GET',
                query: { query },
                fallbackMessage: getString('importError', 'Request failed.')
            });

            renderSearchResults(payload.items || []);
        } catch (error) {
            updateGooglePreviewStylesheet([]);
            googleResults.innerHTML = `<div class="tasty-fonts-empty">${getErrorMessage(error, getString('importError', 'Request failed.'))}</div>`;
        }
    }

    async function runBunnySearch(query) {
        if (!bunnyResults) {
            return;
        }

        if (!hasRestConfig() || !window.fetch) {
            updateBunnySearchPreviewStylesheet([]);
            bunnyResults.innerHTML = '';
            return;
        }

        if (query.trim().length < 2) {
            bunnySearchResults = [];
            updateBunnySearchPreviewStylesheet([]);
            bunnyResults.innerHTML = '';
            return;
        }

        bunnyResults.innerHTML = `<div class="tasty-fonts-empty">${getString('bunnySearching', 'Searching Bunny Fonts…')}</div>`;
        try {
            const payload = await requestJson(getRoutePath('searchBunny', 'bunny/search'), {
                method: 'GET',
                query: { query },
                fallbackMessage: getString('bunnySearchEmpty', 'No Bunny Fonts families matched that search.')
            });

            renderBunnySearchResults(payload.items || []);
        } catch (error) {
            bunnySearchResults = [];
            updateBunnySearchPreviewStylesheet([]);
            bunnyResults.innerHTML = `<div class="tasty-fonts-empty">${getErrorMessage(error, getString('bunnySearchEmpty', 'No Bunny Fonts families matched that search.'))}</div>`;
        }
    }

    async function loadGoogleFamilyDetails(familyName) {
        if (!hasRestConfig() || !window.fetch) {
            return null;
        }

        const requestToken = ++googleFamilyLookupToken;

        try {
            const payload = await requestJson(getRoutePath('googleFamily', 'google/family'), {
                method: 'GET',
                query: { family: familyName }
            });

            if (requestToken !== googleFamilyLookupToken) {
                return null;
            }

            const item = payload && payload.item ? payload.item : null;

            if (!item || normalizeFamilyKey(currentGoogleImportFamily()) !== normalizeFamilyKey(familyName)) {
                return null;
            }

            selectedSearchFamily = item;

            const existingIndex = searchResults.findIndex((entry) => normalizeFamilyKey(entry && entry.family ? entry.family : '') === normalizeFamilyKey(item.family || ''));

            if (existingIndex === -1) {
                searchResults = [item].concat(searchResults).slice(0, 8);
            } else {
                searchResults[existingIndex] = item;
            }

            renderVariantOptions(item.variants || [], item.family || familyName);

            if (googleResults && googleResults.childElementCount > 0) {
                renderSearchResults(searchResults);
            }

            return item;
        } catch (error) {
            return null;
        }
    }

    async function loadBunnyFamilyDetails(familyName) {
        if (!hasRestConfig() || !window.fetch) {
            return null;
        }

        const requestToken = ++bunnyFamilyLookupToken;
        try {
            const payload = await requestJson(getRoutePath('bunnyFamily', 'bunny/family'), {
                method: 'GET',
                query: { family: familyName }
            });

            if (requestToken !== bunnyFamilyLookupToken) {
                return null;
            }

            const item = payload && payload.item ? payload.item : null;

            if (!item || normalizeFamilyKey(currentBunnyImportFamily()) !== normalizeFamilyKey(familyName)) {
                return null;
            }

            selectedBunnySearchFamily = item;

            if (!bunnySearchResults.some((entry) => matchesBunnyFamilyEntry(entry, item.family || item.slug || ''))) {
                bunnySearchResults = [item].concat(bunnySearchResults).slice(0, 8);
            }

            renderBunnyVariantOptions(item.variants || [], item.family || familyName);

            if (bunnyResults && bunnyResults.childElementCount > 0) {
                renderBunnySearchResults(bunnySearchResults);
            }

            return item;
        } catch (error) {
            return null;
        }
    }

    function selectedVariantTokens() {
        if (variantsWrap && variantsWrap.querySelectorAll('input').length > 0) {
            return Array.from(variantsWrap.querySelectorAll('input:checked')).map((input) => input.value);
        }

        return (manualVariants && manualVariants.value ? manualVariants.value.split(',') : []).map((item) => item.trim()).filter(Boolean);
    }

    function setButtonBusyState(button, isBusy, idleLabel, busyLabel) {
        if (!button) {
            return;
        }

        button.disabled = isBusy;

        if (isBusy) {
            button.setAttribute('aria-busy', 'true');
            button.textContent = busyLabel;
            return;
        }

        button.removeAttribute('aria-busy');
        button.textContent = idleLabel;
    }

    function setImportBusyState(isBusy, idleLabel) {
        setButtonBusyState(
            importButton,
            isBusy,
            idleLabel || getString('importButtonIdle', 'Add to Library'),
            getString('importButtonBusy', 'Importing…')
        );
    }

    function setBunnyImportBusyState(isBusy, idleLabel) {
        setButtonBusyState(
            bunnyImportButton,
            isBusy,
            idleLabel || getString('importButtonIdle', 'Add to Library'),
            getString('bunnyImportBusy', 'Importing Bunny Fonts…')
        );
    }

    async function importGoogleFont() {
        const family = manualFamily ? manualFamily.value.trim() : '';
        const deliveryMode = currentGoogleDeliveryMode();

        if (!family) {
            setStatus(importStatus, getString('selectFamily', 'Select a family from search results or type one manually.'), 'error');
            return;
        }

        if (importInFlight) {
            return;
        }

        const variants = Array.from(new Set(selectedVariantTokens()));
        if (variants.length === 0) {
            setStatus(importStatus, getString('importNoVariants', 'Select at least one variant to import.'), 'error');
            return;
        }

        const originalLabel = importButton ? importButton.textContent : '';

        importInFlight = true;
        setImportBusyState(true, originalLabel);

        try {
            setStatus(importStatus, getString('importing', 'Saving the selected Google delivery…'), 'progress', 20);

            const result = await requestJson(getRoutePath('importGoogle', 'google/import'), {
                method: 'POST',
                body: {
                    family,
                    variant_tokens: variants.join(','),
                    delivery_mode: deliveryMode
                },
                fallbackMessage: getString('importError', 'Import failed.')
            });
            const importedCount = Array.isArray(result.imported_variants) ? result.imported_variants.length : 0;
            const skippedCount = Array.isArray(result.skipped_variants) ? result.skipped_variants.length : 0;
            const summary = formatPluralMessage(
                'Saved %1$d variant. %2$d skipped. Reloading…',
                'Saved %1$d variants. %2$d skipped. Reloading…',
                importedCount,
                [importedCount, skippedCount]
            );
            const message = getApiMessage(result, summary);
            const tone = result.status === 'skipped' ? 'error' : 'success';

            setStatus(importStatus, message, tone, tone === 'success' ? 100 : undefined);
            showToast(message, tone);

            if (tone === 'success') {
                reloadPageSoon(900, {
                    type: 'highlight-library-row',
                    familySlug: normalizeImportFamilySlug(result, family)
                });
            }
        } catch (error) {
            const message = getErrorMessage(error, getString('importError', 'Import failed.'));

            setStatus(importStatus, message, 'error');
            showToast(message, 'error');
        } finally {
            importInFlight = false;
            setImportBusyState(false, originalLabel);
        }
    }

    async function importBunnyFont() {
        const selectedFamilyName = selectedBunnySearchFamily && selectedBunnySearchFamily.family
            ? String(selectedBunnySearchFamily.family).trim()
            : '';
        const family = selectedFamilyName || currentBunnyImportFamily();
        const deliveryMode = currentBunnyDeliveryMode();

        if (!family) {
            setStatus(bunnyImportStatus, getString('bunnySelectFamily', 'Type a Bunny Fonts family name before importing.'), 'error');
            return;
        }

        if (importInFlight) {
            return;
        }

        const variants = bunnySelectedVariantTokens();
        const originalLabel = bunnyImportButton ? bunnyImportButton.textContent : '';

        importInFlight = true;
        setBunnyImportBusyState(true, originalLabel);

        try {
            setStatus(
                bunnyImportStatus,
                getString('bunnyImportSubmitting', 'Importing Bunny Fonts and self-hosting the selected files…'),
                'progress',
                20
            );

            const result = await requestJson(getRoutePath('importBunny', 'bunny/import'), {
                method: 'POST',
                body: {
                    family,
                    variant_tokens: variants.join(','),
                    delivery_mode: deliveryMode
                },
                fallbackMessage: getString('bunnyImportError', 'The Bunny Fonts import failed.')
            });
            const message = getApiMessage(result, getString('bunnyImportSuccess', 'Bunny Fonts imported successfully. Reloading…'));
            const tone = result.status === 'imported' || result.status === 'saved' ? 'success' : 'error';

            setStatus(bunnyImportStatus, message, tone, tone === 'success' ? 100 : undefined);
            showToast(message, tone);

            if (tone === 'success') {
                reloadPageSoon(900, {
                    type: 'highlight-library-row',
                    familySlug: normalizeImportFamilySlug(result, family)
                });
            }
        } catch (error) {
            const message = getErrorMessage(error, getString('bunnyImportError', 'The Bunny Fonts import failed.'));

            setStatus(bunnyImportStatus, message, 'error');
            showToast(message, 'error');
        } finally {
            importInFlight = false;
            setBunnyImportBusyState(false, originalLabel);
        }
    }

    function expandRowForSearch(row) {
        if (!row) {
            return;
        }

        const toggle = row.querySelector('[data-disclosure-toggle]');

        if (toggle) {
            const wasExpanded = toggle.getAttribute('aria-expanded') === 'true';

            if (!wasExpanded) {
                toggle.dataset.searchExpanded = 'true';
                setDisclosureState(toggle, true);
            }
        }
    }

    function resetRowAfterSearch(row) {
        if (!row) {
            return;
        }

        const toggle = row.querySelector('[data-disclosure-toggle]');

        if (!toggle || toggle.dataset.searchExpanded !== 'true') {
            return;
        }

        delete toggle.dataset.searchExpanded;
        setDisclosureState(toggle, false);
    }

    // Library filtering
    function initLibraryFiltering() {
        if (!librarySearch && !librarySourceFilter && !libraryCategoryFilter) {
            return;
        }

        const libraryRows = Array.from(document.querySelectorAll('[data-font-row]'));
        activeLibrarySourceFilter = librarySourceFilter ? (librarySourceFilter.value || 'all') : 'all';
        activeLibraryCategoryFilter = libraryCategoryFilter ? (libraryCategoryFilter.value || 'all') : 'all';

        function rowMatchesSource(row, sourceFilter) {
            if (!row || !sourceFilter || sourceFilter === 'all') {
                return true;
            }

            const sourceTokens = (row.getAttribute('data-font-sources') || '').split(/\s+/).filter(Boolean);

            if (sourceFilter === 'published' && sourceTokens.includes('role_active')) {
                return true;
            }

            return sourceTokens.includes(sourceFilter);
        }

        function rowMatchesCategory(row, categoryFilter) {
            if (!row || !categoryFilter || categoryFilter === 'all') {
                return true;
            }

            const categoryTokens = (row.getAttribute('data-font-categories') || '').split(/\s+/).filter(Boolean);

            return categoryTokens.includes(categoryFilter);
        }

        const applyLibraryFilter = () => {
            const query = librarySearch ? librarySearch.value.trim().toLowerCase() : '';
            let visibleCount = 0;

            libraryRows.forEach((row) => {
                const name = row.getAttribute('data-font-name') || '';
                const matchesQuery = !query || name.includes(query);
                const matchesSource = rowMatchesSource(row, activeLibrarySourceFilter);
                const matchesCategory = rowMatchesCategory(row, activeLibraryCategoryFilter);
                const matches = matchesQuery && matchesSource && matchesCategory;

                row.hidden = !matches;

                if (!matches) {
                    resetRowAfterSearch(row);
                    return;
                }

                visibleCount += 1;

                if (query) {
                    expandRowForSearch(row);
                    return;
                }

                resetRowAfterSearch(row);
            });

            if (libraryFilteredEmpty) {
                libraryFilteredEmpty.hidden = visibleCount !== 0;
            }
        };

        if (librarySearch) {
            librarySearch.addEventListener('input', applyLibraryFilter);
            librarySearch.addEventListener('search', applyLibraryFilter);
        }

        if (librarySourceFilter) {
            librarySourceFilter.addEventListener('change', () => {
                activeLibrarySourceFilter = librarySourceFilter.value || 'all';
                applyLibraryFilter();
            });
        }

        if (libraryCategoryFilter) {
            libraryCategoryFilter.addEventListener('change', () => {
                activeLibraryCategoryFilter = libraryCategoryFilter.value || 'all';
                applyLibraryFilter();
            });
        }

        applyLibraryFilter();
    }

    function initActivityFiltering() {
        if (!activityList || (!activityActorFilter && !activitySearch)) {
            return;
        }

        const activityEntries = Array.from(activityList.querySelectorAll('[data-activity-entry]'));
        const totalCount = activityEntries.length;

        const applyActivityFilter = () => {
            const actorFilter = activityActorFilter ? activityActorFilter.value.trim().toLowerCase() : '';
            const query = activitySearch ? activitySearch.value.trim().toLowerCase() : '';
            let visibleCount = 0;

            activityEntries.forEach((entry) => {
                const actor = (entry.getAttribute('data-activity-actor') || '').trim().toLowerCase();
                const searchValue = (entry.getAttribute('data-activity-search') || '').trim().toLowerCase();
                const matchesActor = !actorFilter || actor === actorFilter;
                const matchesQuery = !query || searchValue.includes(query);
                const matches = matchesActor && matchesQuery;

                entry.hidden = !matches;

                if (matches) {
                    visibleCount += 1;
                }
            });

            if (activityCount) {
                activityCount.textContent = formatActivityCount(visibleCount, totalCount);
            }

            if (activityFilteredEmpty) {
                activityFilteredEmpty.hidden = visibleCount !== 0;
            }

            activityList.hidden = visibleCount === 0;
        };

        if (activityActorFilter) {
            activityActorFilter.addEventListener('change', applyActivityFilter);
        }

        if (activitySearch) {
            activitySearch.addEventListener('input', applyActivityFilter);
            activitySearch.addEventListener('search', applyActivityFilter);
        }

        applyActivityFilter();
    }

    function handleDisclosureToggleClick(event) {
        const disclosureToggle = event.target.closest('[data-disclosure-toggle]');

        if (!disclosureToggle) {
            return false;
        }

        const isExpanded = disclosureToggle.getAttribute('aria-expanded') === 'true';
        const nextExpanded = !isExpanded;
        const targetId = disclosureToggle.getAttribute('data-disclosure-toggle') || '';
        const isRoleToolToggle = targetId === 'tasty-fonts-role-preview-panel' || targetId === 'tasty-fonts-role-advanced-panel';

        if (nextExpanded && isRoleToolToggle) {
            const pairedTargetId = targetId === 'tasty-fonts-role-preview-panel'
                ? 'tasty-fonts-role-advanced-panel'
                : 'tasty-fonts-role-preview-panel';
            const pairedToggle = disclosureToggleByTargetId(pairedTargetId);

            if (pairedToggle) {
                setDisclosureState(pairedToggle, false);
            }
        }

        setDisclosureState(disclosureToggle, nextExpanded);

        if (isTrackedDisclosureToggle(disclosureToggle)) {
            syncTrackedUiUrl('push');
        }

        if (nextExpanded && disclosureToggle.getAttribute('data-disclosure-toggle') === 'tasty-fonts-add-font-panel') {
            window.setTimeout(focusAddFontPanel, 0);
        }

        if (nextExpanded && disclosureToggle.getAttribute('data-disclosure-toggle') === 'tasty-fonts-role-preview-panel') {
            initializePreviewWorkspace();
        }

        if (nextExpanded && isRoleToolToggle) {
            revealDisclosurePanel(targetId, disclosureToggle);
        }

        return true;
    }

    function handleOpenAddFontsClick(event) {
        const trigger = event.target.closest('[data-open-add-fonts]');

        if (!trigger || !addFontsPanelToggle) {
            return false;
        }

        event.preventDefault();

        if (addFontsPanelToggle.getAttribute('aria-expanded') !== 'true') {
            setDisclosureState(addFontsPanelToggle, true);
        }

        syncTrackedUiUrl('push');
        window.setTimeout(focusAddFontPanel, 0);
        return true;
    }

    function handleTabClick(event) {
        const tab = event.target.closest('[data-tab-group][data-tab-target]');

        if (tab) {
            const group = tab.getAttribute('data-tab-group') || '';

            activateTabGroup(group, tab.getAttribute('data-tab-target'));

            if (isTrackedTabGroup(group)) {
                syncTrackedUiUrl('push');
            }

            return true;
        }

        return false;
    }

    function handleTabKeydown(event) {
        const tab = event.target.closest('[data-tab-group][data-tab-target][role="tab"]');

        if (!tab) {
            return false;
        }

        const group = tab.getAttribute('data-tab-group') || '';
        const buttons = tabButtonsForGroup(group);

        if (!group || buttons.length < 2) {
            return false;
        }

        const currentIndex = buttons.indexOf(tab);
        let nextIndex = currentIndex;

        switch (event.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                nextIndex = (currentIndex + 1) % buttons.length;
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                nextIndex = (currentIndex - 1 + buttons.length) % buttons.length;
                break;
            case 'Home':
                nextIndex = 0;
                break;
            case 'End':
                nextIndex = buttons.length - 1;
                break;
            default:
                return false;
        }

        event.preventDefault();

        const nextTab = buttons[nextIndex];
        activateTabGroup(group, nextTab.getAttribute('data-tab-target'));

        if (isTrackedTabGroup(group)) {
            syncTrackedUiUrl('push');
        }

        nextTab.focus();
        return true;
    }

    function handleCopyClick(event) {
        const copyTextButton = event.target.closest('[data-copy-text]');

        if (copyTextButton) {
            copyText(
                copyTextButton.getAttribute('data-copy-text') || '',
                copyTextButton,
                {
                    preserveLabel: copyTextButton.hasAttribute('data-copy-static-label'),
                    toastMessage: copyTextButton.getAttribute('data-copy-success') || ''
                }
            );
            return true;
        }

        const copyTarget = event.target.closest('[data-copy-target]');

        if (!copyTarget) {
            return false;
        }

        const target = document.getElementById(copyTarget.getAttribute('data-copy-target'));
        const targetValue = target
            ? ('value' in target ? target.value : target.textContent || '')
            : '';
        copyText(targetValue, copyTarget);
        return true;
    }

    function handleSnippetDisplayToggle(event) {
        const toggle = event.target.closest('[data-snippet-display-toggle]');

        if (!toggle) {
            return false;
        }

        const codePanel = toggle.closest('.tasty-fonts-code-panel');
        const rawView = codePanel ? codePanel.querySelector('[data-snippet-view="raw"]') : null;
        const readableView = codePanel ? codePanel.querySelector('[data-snippet-view="readable"]') : null;

        if (!rawView || !readableView) {
            return false;
        }

        event.preventDefault();

        const showReadable = toggle.getAttribute('aria-pressed') !== 'true';
        const defaultLabel = toggle.getAttribute('data-label-default') || '';
        const activeLabel = toggle.getAttribute('data-label-active') || '';

        toggle.setAttribute('aria-pressed', showReadable ? 'true' : 'false');
        toggle.textContent = showReadable ? activeLabel : defaultLabel;
        rawView.hidden = showReadable;
        readableView.hidden = !showReadable;
        return true;
    }

    function handleRoleAssignClick(event) {
        const roleTarget = event.target.closest('[data-role-assign]');

        if (!roleTarget) {
            return false;
        }

        const family = roleTarget.getAttribute('data-font-family');
        const role = roleTarget.getAttribute('data-role-assign');
        const snapshotBeforeChange = roleFieldSnapshot();

        if (
            (role === 'heading' && snapshotBeforeChange.heading === family)
            || (role === 'body' && snapshotBeforeChange.body === family)
            || (role === 'monospace' && snapshotBeforeChange.monospace === family)
        ) {
            return true;
        }

        if (role === 'heading' && roleHeading) {
            roleHeading.value = family;
        }

        if (role === 'body' && roleBody) {
            roleBody.value = family;
        }

        if (role === 'monospace' && roleMonospace) {
            roleMonospace.value = family;
        }

        updateRoleOutputs();
        void saveRoleDraft(snapshotBeforeChange).then((saved) => {
            if (saved) {
                highlightRoleSelector(role);
            }
        });
        return true;
    }

    function handlePreviewWorkspaceClick(event) {
        const resetTarget = event.target.closest('[data-preview-reset]');

        if (resetTarget) {
            initializePreviewWorkspace();
            resetPreviewWorkspace();
            return true;
        }

        const syncTarget = event.target.closest('[data-preview-sync-draft]');

        if (!syncTarget) {
            return false;
        }

        initializePreviewWorkspace();
        const baseline = computePreviewBaseline();
        syncPreviewWorkspaceToDraft({ markDirty: baseline.source === 'live_sitewide' });
        return true;
    }

    function handlePreviewSelectionActions(event) {
        const saveDraftTarget = event.target.closest('[data-preview-save-draft]');

        if (saveDraftTarget) {
            initializePreviewWorkspace();
            const snapshotBeforeChange = roleFieldSnapshot();
            applyPreviewStateToRoleFields();
            updateRoleOutputs();
            void saveRoleDraft(snapshotBeforeChange).then((saved) => {
                if (saved) {
                    previewDirty = false;
                    previewFollowsDraft = true;
                    previewRoleState = currentDraftRoleState();
                    applyPreviewOutputs(getString('previewCurrentDraft', 'Current draft'));
                }
            });
            return true;
        }

        const applyLiveTarget = event.target.closest('[data-preview-apply-live]');

        if (!applyLiveTarget || !roleForm) {
            return false;
        }

        initializePreviewWorkspace();
        applyPreviewStateToRoleFields();
        updateRoleOutputs();
        savePendingUiState(captureTrackedUiState());

        if (roleActionTypeInput) {
            roleActionTypeInput.value = 'apply';
        }

        if (typeof roleForm.requestSubmit === 'function') {
            roleForm.requestSubmit();
        } else {
            roleForm.submit();
        }

        return true;
    }

    function handleDeleteFamilyClick(event) {
        const deleteTarget = event.target.closest('[data-delete-family]');

        if (!deleteTarget) {
            return false;
        }

        const blockedMessage = deleteTarget.getAttribute('data-delete-blocked');

        if (blockedMessage) {
            event.preventDefault();
            showToast(blockedMessage, 'error');
            return true;
        }

        const familyName = deleteTarget.getAttribute('data-delete-family') || 'this font family';
        const confirmTemplate = getString('deleteConfirm', 'Delete "%s" and remove its files from uploads/fonts?');

        if (!window.confirm(confirmTemplate.replace('%s', familyName))) {
            event.preventDefault();
        }

        return true;
    }

    function handleDeleteDeliveryProfileClick(event) {
        const deleteTarget = event.target.closest('[data-delete-delivery-profile]');

        if (!deleteTarget) {
            return false;
        }

        event.preventDefault();
        void deleteDeliveryProfile(deleteTarget);
        return true;
    }

    function handleMigrateDeliveryClick(event) {
        const migrateTarget = event.target.closest('[data-migrate-delivery]');

        if (!migrateTarget) {
            return false;
        }

        event.preventDefault();
        prefillSelfHostedMigration(migrateTarget);
        return true;
    }

    function handleSearchCardClick(event) {
        const searchCard = event.target.closest('.tasty-fonts-search-card');

        if (!searchCard) {
            return false;
        }

        if ((searchCard.dataset.searchProvider || 'google') === 'bunny') {
            selectBunnySearchFamily(searchCard.dataset.family || '');
            return true;
        }

        selectSearchFamily(searchCard.dataset.family || '');
        return true;
    }

    function handleSearchCardKeydown(event) {
        const searchCard = event.target.closest('.tasty-fonts-search-card[role="button"]');

        if (!searchCard) {
            return false;
        }

        if (event.key !== 'Enter' && event.key !== ' ') {
            return false;
        }

        event.preventDefault();

        if ((searchCard.dataset.searchProvider || 'google') === 'bunny') {
            selectBunnySearchFamily(searchCard.dataset.family || '');
            return true;
        }

        selectSearchFamily(searchCard.dataset.family || '');
        return true;
    }

    function handleGoogleVariantQuickSelect(event) {
        const quickSelect = event.target.closest('[data-google-variant-select]');

        if (!quickSelect) {
            return false;
        }

        applyVariantQuickSelection(quickSelect.getAttribute('data-google-variant-select') || '');
        return true;
    }

    function handleBunnyVariantQuickSelect(event) {
        const quickSelect = event.target.closest('[data-bunny-variant-select]');

        if (!quickSelect) {
            return false;
        }

        applyBunnyVariantQuickSelection(quickSelect.getAttribute('data-bunny-variant-select') || '');
        return true;
    }

    function handleUploadBuilderClick(event) {
        const uploadRemove = event.target.closest('[data-upload-remove]');

        if (uploadRemove) {
            const row = uploadRemove.closest('[data-upload-row]');
            const group = uploadRemove.closest('[data-upload-group]');

            if (row && group && uploadRows(group).length > 1) {
                row.remove();
                updateUploadRemoveButtons();
            }

            return true;
        }

        const uploadRemoveGroup = event.target.closest('[data-upload-remove-group]');

        if (uploadRemoveGroup) {
            const group = uploadRemoveGroup.closest('[data-upload-group]');

            if (group && uploadGroups().length > 1) {
                group.remove();
                updateUploadRemoveButtons();
            }

            return true;
        }

        const uploadAddFaceButton = event.target.closest('[data-upload-add-face]');

        if (!uploadAddFaceButton) {
            return false;
        }

        const group = uploadAddFaceButton.closest('[data-upload-group]');
        const nextRow = createUploadRow(group);
        const nextFile = nextRow ? nextRow.querySelector('[data-upload-field="file"]') : null;

        if (nextFile) {
            nextFile.focus();
        }

        return true;
    }

    function handleDetectedUploadClick(event) {
        const detectedButton = event.target.closest('[data-upload-detected-apply]');

        if (!detectedButton) {
            return false;
        }

        const row = detectedButton.closest('[data-upload-row]');
        const group = detectedButton.closest('[data-upload-group]');
        const familyField = familyInputForGroup(group);
        const weightField = row ? row.querySelector('[data-upload-field="weight"]') : null;
        const styleField = row ? row.querySelector('[data-upload-field="style"]') : null;
        const detectedFamily = detectedButton.dataset.detectedFamily || '';
        const detectedWeight = detectedButton.dataset.detectedWeight || '';
        const detectedStyle = detectedButton.dataset.detectedStyle || '';

        if (familyField && !familyField.value.trim() && detectedFamily) {
            familyField.value = detectedFamily;
        }

        if (weightField && detectedWeight) {
            weightField.value = detectedWeight;
        }

        if (styleField && detectedStyle) {
            styleField.value = detectedStyle;
        }

        updateUploadDetectedSuggestion(row);
        return true;
    }

    // Event binding
    function handleDocumentClick(event) {
        const toastDismiss = event.target.closest('[data-toast-dismiss]');
        if (toastDismiss) {
            dismissToast(toastDismiss.closest('[data-toast]'));
            return;
        }

        if (handleOpenAddFontsClick(event)) {
            return;
        }

        if (handleDisclosureToggleClick(event)) {
            return;
        }

        if (handleTabClick(event)) {
            return;
        }

        if (handleSnippetDisplayToggle(event)) {
            return;
        }

        if (handleCopyClick(event)) {
            return;
        }

        if (handleRoleAssignClick(event)) {
            return;
        }

        if (handlePreviewWorkspaceClick(event)) {
            return;
        }

        if (handlePreviewSelectionActions(event)) {
            return;
        }

        if (handleDeleteFamilyClick(event)) {
            return;
        }

        if (handleDeleteDeliveryProfileClick(event)) {
            return;
        }

        if (handleMigrateDeliveryClick(event)) {
            return;
        }

        if (handleSearchCardClick(event)) {
            return;
        }

        if (handleGoogleVariantQuickSelect(event)) {
            return;
        }

        if (handleBunnyVariantQuickSelect(event)) {
            return;
        }

        if (handleUploadBuilderClick(event)) {
            return;
        }

        handleDetectedUploadClick(event);
    }

    function bindFamilyFallbackControls() {
        document.querySelectorAll('.tasty-fonts-fallback-selector').forEach((element) => {
            const form = element.closest('[data-family-fallback-form]');

            element.addEventListener('change', () => {
                updateInlineStackPreview(element.dataset.fontFamily || '');

                syncFamilyFallbackSaveState(form);
                setFamilyFallbackFeedback(form, '', '');
            });
            updateInlineStackPreview(element.dataset.fontFamily || '');
            syncFamilyFallbackSaveState(form);
        });

        document.querySelectorAll('[data-family-fallback-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (!hasRestConfig() || !window.fetch) {
                    return;
                }

                event.preventDefault();

                const selector = form.querySelector('.tasty-fonts-fallback-selector');
                const saved = await saveFamilyFallback(selector, form);

                if (!saved) {
                    syncFamilyFallbackSaveState(form);
                }
            });
        });
    }

    function bindFamilyFontDisplayControls() {
        document.querySelectorAll('.tasty-fonts-font-display-selector').forEach((element) => {
            const form = element.closest('[data-family-font-display-form]');

            element.addEventListener('change', () => {
                syncFamilyFontDisplaySaveState(form);
                setFamilyFontDisplayFeedback(form, '', '');
            });
            syncFamilyFontDisplaySaveState(form);
        });

        document.querySelectorAll('[data-family-font-display-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (!hasRestConfig() || !window.fetch) {
                    return;
                }

                event.preventDefault();

                const selector = form.querySelector('.tasty-fonts-font-display-selector');
                const saved = await saveFamilyFontDisplay(selector, form);

                if (!saved) {
                    syncFamilyFontDisplaySaveState(form);
                }
            });
        });
    }

    function bindFamilyDeliveryControls() {
        document.querySelectorAll('.tasty-fonts-family-delivery-selector').forEach((element) => {
            const form = element.closest('[data-family-delivery-form]');

            element.addEventListener('change', () => {
                syncFamilyDeliverySaveState(form);
                setFamilyDeliveryFeedback(form, '', '');
            });

            syncFamilyDeliverySaveState(form);
        });

        document.querySelectorAll('[data-family-delivery-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (!hasRestConfig() || !window.fetch) {
                    return;
                }

                event.preventDefault();

                const selector = form.querySelector('.tasty-fonts-family-delivery-selector');
                const saved = await saveFamilyDelivery(selector, form);

                if (!saved) {
                    syncFamilyDeliverySaveState(form);
                }
            });
        });
    }

    function bindFamilyPublishStateControls() {
        document.querySelectorAll('.tasty-fonts-family-publish-state-selector').forEach((element) => {
            const form = element.closest('[data-family-publish-state-form]');

            element.addEventListener('change', () => {
                syncFamilyPublishStateSaveState(form);
                setFamilyPublishStateFeedback(form, '', '');
            });

            syncFamilyPublishStateSaveState(form);
        });

        document.querySelectorAll('[data-family-publish-state-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (!hasRestConfig() || !window.fetch) {
                    return;
                }

                event.preventDefault();

                const selector = form.querySelector('.tasty-fonts-family-publish-state-selector');
                const saved = await saveFamilyPublishState(selector, form);

                if (!saved) {
                    syncFamilyPublishStateSaveState(form);
                }
            });
        });
    }

    function syncImportDeliveryButtons() {
        if (importButton) {
            importButton.textContent = deliveryButtonLabel(currentGoogleDeliveryMode(), 'google');
        }

        if (bunnyImportButton) {
            bunnyImportButton.textContent = deliveryButtonLabel(currentBunnyDeliveryMode(), 'bunny');
        }
    }

    function bindRolePreviewControls() {
        [roleHeading, roleBody, roleMonospace, roleHeadingFallback, roleBodyFallback, roleMonospaceFallback].forEach((element) => {
            if (element) {
                element.addEventListener('change', updateRoleOutputs);
            }
        });

        Object.entries(previewRoleSelects).forEach(([roleKey, element]) => {
            if (!element) {
                return;
            }

            element.addEventListener('change', () => {
                initializePreviewWorkspace();
                previewRoleState = normalizeRoleState({
                    ...(previewRoleState || currentDraftRoleState()),
                    [roleKey]: element.value || '',
                });
                previewDirty = true;
                previewFollowsDraft = false;
                applyPreviewOutputs(previewSourceLabel ? previewSourceLabel.textContent : '');
            });
        });

        if (roleForm) {
            roleForm.addEventListener('submit', () => {
                savePendingUiState(captureTrackedUiState());
            });
        }

        if (previewTextInput) {
            previewTextInput.addEventListener('input', updatePreviewDynamicText);
            previewTextInput.addEventListener('input', updateGoogleImportSummary);
            previewTextInput.addEventListener('input', updateBunnyImportSummary);
        }

        if (previewSizeInput) {
            previewSizeInput.addEventListener('input', updatePreviewScale);
            previewSizeInput.addEventListener('change', updatePreviewScale);
        }
    }

    function bindGoogleImportControls() {
        if (googleSearch) {
            googleSearch.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(() => {
                    runGoogleSearch(googleSearch.value);
                }, 250);
            });
        }

        if (manualFamily) {
            manualFamily.addEventListener('input', () => {
                window.clearTimeout(googleFamilyLookupTimer);

                const familyName = manualFamily.value.trim();
                const matchedFamily = findGoogleFamilyMatch(familyName);
                const hasVariantInputs = !!(variantsWrap && variantsWrap.querySelector('input[type="checkbox"]'));

                if (matchedFamily) {
                    const shouldRenderVariants = !selectedSearchFamily
                        || selectedSearchFamily.family !== matchedFamily.family
                        || !hasVariantInputs;

                    selectedSearchFamily = matchedFamily;

                    if (shouldRenderVariants) {
                        if (Array.isArray(matchedFamily.variants) && matchedFamily.variants.length > 0) {
                            renderVariantOptions(matchedFamily.variants || [], matchedFamily.family || '');
                        } else {
                            renderedGoogleVariantFamily = '';

                            if (variantsWrap) {
                                variantsWrap.innerHTML = '';
                            }

                            updateGoogleImportSummary();
                            googleFamilyLookupTimer = window.setTimeout(() => {
                                void loadGoogleFamilyDetails(familyName);
                            }, 300);
                        }

                        return;
                    }
                } else if (selectedSearchFamily || hasVariantInputs) {
                    selectedSearchFamily = null;
                    renderedGoogleVariantFamily = '';

                    if (variantsWrap) {
                        variantsWrap.innerHTML = '';
                    }
                }

                updateGoogleImportSummary();
            });
        }

        if (manualVariants) {
            manualVariants.addEventListener('input', () => {
                if (normalizeVariantTokens(manualVariants.value).length === 0) {
                    manualVariants.dataset.explicitEmpty = 'true';
                } else {
                    delete manualVariants.dataset.explicitEmpty;
                }

                if (!syncingGoogleVariants) {
                    syncVariantChipsFromManualInput();
                }

                updateGoogleImportSummary();
            });
        }

        if (variantsWrap) {
            variantsWrap.addEventListener('change', (event) => {
                const input = event.target.closest('input[type="checkbox"]');

                if (!input) {
                    return;
                }

                syncVariantChipState(input);
                syncManualVariantsInputFromChips();
                if (manualVariants) {
                    if (selectedVariantTokens().length === 0) {
                        manualVariants.dataset.explicitEmpty = 'true';
                    } else {
                        delete manualVariants.dataset.explicitEmpty;
                    }
                }
                updateGoogleImportSummary();
            });
        }

        if (importButton) {
            importButton.addEventListener('click', importGoogleFont);
        }

        googleDeliveryModes.forEach((input) => {
            input.addEventListener('change', syncImportDeliveryButtons);
        });
    }

    function bindBunnyImportControls() {
        if (bunnySearch) {
            bunnySearch.addEventListener('input', () => {
                window.clearTimeout(bunnySearchTimer);
                bunnySearchTimer = window.setTimeout(() => {
                    void runBunnySearch(bunnySearch.value);
                }, 250);
            });
        }

        if (bunnyFamily) {
            bunnyFamily.addEventListener('input', () => {
                window.clearTimeout(bunnyFamilyLookupTimer);

                const familyName = bunnyFamily.value.trim();
                const matchedFamily = findBunnyFamilyMatch(familyName);
                const hasVariantInputs = !!(bunnyVariantsWrap && bunnyVariantsWrap.querySelector('input[type="checkbox"]'));

                if (!familyName) {
                    selectedBunnySearchFamily = null;
                    renderedBunnyVariantFamily = '';

                    if (bunnyVariantsWrap) {
                        bunnyVariantsWrap.innerHTML = '';
                    }

                    updateBunnyImportSummary();
                    return;
                }

                if (matchedFamily) {
                    const shouldRenderVariants = !selectedBunnySearchFamily
                        || !matchesBunnyFamilyEntry(selectedBunnySearchFamily, matchedFamily.family || matchedFamily.slug || '')
                        || !hasVariantInputs;

                    selectedBunnySearchFamily = matchedFamily;

                    if (shouldRenderVariants) {
                        renderBunnyVariantOptions(matchedFamily.variants || [], matchedFamily.family || familyName);
                        return;
                    }
                } else if (selectedBunnySearchFamily || hasVariantInputs) {
                    selectedBunnySearchFamily = null;
                    renderedBunnyVariantFamily = '';

                    if (bunnyVariantsWrap) {
                        bunnyVariantsWrap.innerHTML = '';
                    }
                }

                updateBunnyImportSummary();

                if (familyName.length < 2) {
                    return;
                }

                bunnyFamilyLookupTimer = window.setTimeout(() => {
                    void loadBunnyFamilyDetails(familyName);
                }, 300);
            });
        }

        if (bunnyVariants) {
            bunnyVariants.addEventListener('input', () => {
                if (normalizeHostedVariantTokens(bunnyVariants.value).length === 0) {
                    bunnyVariants.dataset.explicitEmpty = 'true';
                } else {
                    delete bunnyVariants.dataset.explicitEmpty;
                }

                if (!syncingBunnyVariants) {
                    syncBunnyVariantChipsFromManualInput();
                }

                updateBunnyImportSummary();
            });
        }

        if (bunnyVariantsWrap) {
            bunnyVariantsWrap.addEventListener('change', (event) => {
                const input = event.target.closest('input[type="checkbox"]');

                if (!input) {
                    return;
                }

                syncVariantChipState(input);
                syncBunnyManualVariantsInputFromChips();

                if (bunnyVariants) {
                    if (bunnySelectedVariantTokens().length === 0) {
                        bunnyVariants.dataset.explicitEmpty = 'true';
                    } else {
                        delete bunnyVariants.dataset.explicitEmpty;
                    }
                }

                updateBunnyImportSummary();
            });
        }

        if (bunnyImportButton) {
            bunnyImportButton.addEventListener('click', importBunnyFont);
        }

        bunnyDeliveryModes.forEach((input) => {
            input.addEventListener('change', syncImportDeliveryButtons);
        });
    }

    function bindUploadControls() {
        if (uploadAddFamily) {
            uploadAddFamily.addEventListener('click', () => {
                const nextGroup = createUploadGroup(lastUploadGroupSeed());
                const nextFamily = familyInputForGroup(nextGroup);

                if (nextFamily) {
                    nextFamily.focus();
                }
            });
        }

        if (uploadForm) {
            uploadForm.addEventListener('submit', (event) => {
                event.preventDefault();
                uploadLocalFonts();
            });
        }

        if (!uploadGroupsWrap) {
            return;
        }

        uploadGroupsWrap.addEventListener('input', (event) => {
            const familyInput = event.target.closest('[data-upload-group-field="family"]');

            if (familyInput) {
                updateGroupDetectedSuggestions(familyInput.closest('[data-upload-group]'));
            }
        });

        uploadGroupsWrap.addEventListener('change', (event) => {
            const fileInput = event.target.closest('[data-upload-field="file"]');

            if (fileInput) {
                const row = fileInput.closest('[data-upload-row]');
                updateUploadFileName(row);
                updateUploadDetectedSuggestion(row);
                return;
            }

            const familyInput = event.target.closest('[data-upload-group-field="family"]');

            if (familyInput) {
                updateGroupDetectedSuggestions(familyInput.closest('[data-upload-group]'));
                return;
            }

            const rowField = event.target.closest('[data-upload-field="weight"], [data-upload-field="style"]');

            if (rowField) {
                updateUploadDetectedSuggestion(rowField.closest('[data-upload-row]'));
            }
        });
    }

    function initializeTabs() {
        const groups = new Set(tabButtons.map((tab) => tab.getAttribute('data-tab-group')).filter(Boolean));

        groups.forEach((group) => {
            const buttons = tabButtonsForGroup(group);
            const activeTab = buttons.find((tab) => tab.classList.contains('is-active')) || buttons[0];

            if (activeTab) {
                activateTabGroup(group, activeTab.getAttribute('data-tab-target'));
            }
        });
    }

    // Bootstrap
    function bootstrap() {
        const initialTrackedUiState = readTrackedUiState(window.location);

        document.addEventListener('click', handleDocumentClick);
        document.addEventListener('keydown', (event) => {
            if (handleSearchCardKeydown(event)) {
                return;
            }

            handleTabKeydown(event);
        });
        window.addEventListener('popstate', handleTrackedUiPopState);

        bindFamilyFallbackControls();
        bindFamilyFontDisplayControls();
        bindFamilyDeliveryControls();
        bindFamilyPublishStateControls();
        bindRolePreviewControls();
        bindGoogleImportControls();
        bindBunnyImportControls();
        bindUploadControls();

        syncDisclosureToggles();
        initToasts();
        initHelpTooltips();
        initUploadRows();
        initLibraryFiltering();
        initActivityFiltering();
        updatePreviewDynamicText();
        updatePreviewScale();
        updateRoleOutputs();
        updateGoogleImportSummary();
        updateBunnyImportSummary();
        syncImportDeliveryButtons();
        initializeTabs();
        defaultTrackedUiState = captureTrackedUiState();
        applyTrackedUiState(initialTrackedUiState);
        syncTrackedUiUrl('replace');
        const previewToggle = disclosureToggleByTargetId('tasty-fonts-role-preview-panel');
        const advancedToggle = disclosureToggleByTargetId('tasty-fonts-role-advanced-panel');
        if (previewToggle && isDisclosureExpanded(previewToggle)) {
            initializePreviewWorkspace();
            revealDisclosurePanel('tasty-fonts-role-preview-panel', previewToggle);
        } else if (advancedToggle && isDisclosureExpanded(advancedToggle)) {
            revealDisclosurePanel('tasty-fonts-role-advanced-panel', advancedToggle);
        }
        const appliedPendingUiState = applyPendingUiState();

        if (!appliedPendingUiState && !hasTrackedUiState(initialTrackedUiState)) {
            resetInitialScrollPosition();
        }
    }

    bootstrap();
})();
