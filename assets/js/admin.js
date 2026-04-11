(function () {
    // DOM references
    const config = window.TastyFontsAdmin || {};
    const adminContracts = window.TastyFontsAdminContracts || {};
    const runtimeStrings = {
        ...(config.strings || {}),
        ...(config.runtimeStrings || {}),
    };
    let currentPage = (() => {
        const allowedPages = new Set(['roles', 'library', 'settings', 'diagnostics']);
        const rootPage = document.querySelector('[data-current-page]');
        const requestedPage = String(config.currentPage || (rootPage ? rootPage.getAttribute('data-current-page') : '') || 'roles').trim();

        return allowedPages.has(requestedPage) ? requestedPage : 'roles';
    })();
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
    let monospaceRoleEnabled = !!config.monospaceRoleEnabled && !!roleMonospace;
    const variableFontsEnabled = !!config.variableFontsEnabled;
    const roleFamilyCatalog = config.roleFamilyCatalog && typeof config.roleFamilyCatalog === 'object'
        ? config.roleFamilyCatalog
        : {};
    const roleWeightEditors = {
        heading: document.querySelector('[data-role-weight-editor="heading"]'),
        body: document.querySelector('[data-role-weight-editor="body"]'),
        monospace: document.querySelector('[data-role-weight-editor="monospace"]'),
    };
    const roleWeightSelects = {
        heading: document.querySelector('[data-role-weight-select="heading"]'),
        body: document.querySelector('[data-role-weight-select="body"]'),
        monospace: document.querySelector('[data-role-weight-select="monospace"]'),
    };
    const roleAxisEditors = {
        heading: document.querySelector('[data-role-axis-editor="heading"]'),
        body: document.querySelector('[data-role-axis-editor="body"]'),
        monospace: document.querySelector('[data-role-axis-editor="monospace"]'),
    };
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
    const previewWeightEditors = {
        heading: document.querySelector('[data-preview-weight-editor="heading"]'),
        body: document.querySelector('[data-preview-weight-editor="body"]'),
        monospace: document.querySelector('[data-preview-weight-editor="monospace"]'),
    };
    const previewWeightSelects = {
        heading: document.querySelector('[data-preview-weight-select="heading"]'),
        body: document.querySelector('[data-preview-weight-select="body"]'),
        monospace: document.querySelector('[data-preview-weight-select="monospace"]'),
    };
    const previewAxisEditors = {
        heading: document.querySelector('[data-preview-axis-editor="heading"]'),
        body: document.querySelector('[data-preview-axis-editor="body"]'),
        monospace: document.querySelector('[data-preview-axis-editor="monospace"]'),
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
    const outputNames = document.getElementById('tasty-fonts-output-names');
    const outputStacks = document.getElementById('tasty-fonts-output-stacks');
    const outputVars = document.getElementById('tasty-fonts-output-vars');
    const outputUsage = document.getElementById('tasty-fonts-output-usage');
    const outputClasses = document.getElementById('tasty-fonts-output-classes');
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
    const googleImportStepTitle = document.getElementById('tasty-fonts-google-import-step-title');
    const googleManualVariantsLabel = document.getElementById('tasty-fonts-google-manual-variants-label');
    const googleSelectedVariantsLabel = document.getElementById('tasty-fonts-google-selected-variants-label');
    const googleSelectedVariantsNote = document.getElementById('tasty-fonts-google-selected-variants-note');
    const googleFormatChoice = document.getElementById('tasty-fonts-google-format-choice');
    const googleDeliveryModes = Array.from(document.querySelectorAll('input[name="tasty_fonts_google_delivery_mode"]'));
    const selectedFamily = document.getElementById('tasty-fonts-selected-family');
    const selectedFamilyMeta = document.getElementById('tasty-fonts-selected-family-meta');
    const selectedFamilyNote = document.getElementById('tasty-fonts-selected-family-note');
    const selectedFamilyPreview = document.getElementById('tasty-fonts-selected-family-preview');
    const bunnyFamily = document.getElementById('tasty-fonts-bunny-family');
    const bunnyVariants = document.getElementById('tasty-fonts-bunny-variants');
    const bunnyImportStepTitle = document.getElementById('tasty-fonts-bunny-import-step-title');
    const bunnyManualVariantsLabel = document.getElementById('tasty-fonts-bunny-manual-variants-label');
    const bunnySelectedVariantsLabel = document.getElementById('tasty-fonts-bunny-selected-variants-label');
    const bunnySelectedVariantsNote = document.getElementById('tasty-fonts-bunny-selected-variants-note');
    const bunnyFormatChoice = document.getElementById('tasty-fonts-bunny-format-choice');
    const bunnyDeliveryModes = Array.from(document.querySelectorAll('input[name="tasty_fonts_bunny_delivery_mode"]'));
    const bunnySelectedFamily = document.getElementById('tasty-fonts-bunny-selected-family');
    const bunnySelectedFamilyMeta = document.getElementById('tasty-fonts-bunny-selected-family-meta');
    const bunnySelectedFamilyNote = document.getElementById('tasty-fonts-bunny-selected-family-note');
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
    let helpTooltipLayer = null;
    let helpTooltipEventsBound = false;
    const activityActorFilter = document.querySelector('[data-activity-actor-filter]');
    const activitySearch = document.querySelector('[data-activity-search]');
    const activityCount = document.querySelector('[data-activity-count]');
    const activityList = document.querySelector('[data-activity-list]');
    const activityFilteredEmpty = document.getElementById('tasty-fonts-activity-empty-filtered');
    const pillOptionInputs = Array.from(document.querySelectorAll('[data-pill-option-input]'));
    const pillOptions = Array.from(document.querySelectorAll('[data-pill-option]'));
    const outputQuickModeInputs = Array.from(document.querySelectorAll('[data-output-quick-mode]'));
    const outputAdvancedPanel = document.getElementById('tasty-fonts-advanced-output-controls');
    const outputMinimalPresetInput = document.querySelector('[data-output-minimal-preset]');
    const outputQuickModePreferenceInput = document.querySelector('[data-output-quick-mode-preference]');
    const initialOutputQuickModePreference = outputQuickModePreferenceInput
        ? (outputQuickModePreferenceInput.getAttribute('data-output-quick-mode-saved-preference') || '')
        : '';
    const outputQuickModeNotice = document.querySelector('[data-output-quick-mode-notice]');
    const developerConfirmForms = Array.from(document.querySelectorAll('[data-developer-confirm-message]'));
    const outputMasterInputs = {
        classes: document.querySelector('[data-output-master="classes"]'),
        variables: document.querySelector('[data-output-master="variables"]'),
    };
    const outputPanels = {
        classes: document.querySelector('[data-output-panel="classes"]'),
        variables: document.querySelector('[data-output-panel="variables"]'),
    };
    const outputMonoDependentInputs = Array.from(document.querySelectorAll('[data-output-mono-dependent]'));
    const monospaceRoleSettingInputs = Array.from(document.querySelectorAll('input[type="checkbox"][name="monospace_role_enabled"]'));
    const outputRoleWeightInput = document.querySelector('input[type="checkbox"][name="role_usage_font_weight_enabled"]');
    const unicodeRangeModeInputs = Array.from(document.querySelectorAll('[data-unicode-range-mode]'));
    const unicodeRangeCustomWrap = document.querySelector('[data-unicode-range-custom-wrap]');
    const unicodeRangeCustomInput = document.querySelector('[data-unicode-range-custom]');
    const settingsForms = Array.from(document.querySelectorAll('[data-settings-form]'));
    const settingsSaveShell = document.querySelector('[data-settings-save-shell]');

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
    let nextGeneratedFieldIndex = 0;
    let disclosureAnnouncementRegion = null;
    let disclosureAnnouncementToken = 0;
    let activeLibrarySourceFilter = 'all';
    let activeLibraryCategoryFilter = 'all';
    let syncingGoogleVariants = false;
    let renderedGoogleVariantFamily = '';
    let outputQuickModeBootstrapped = false;
    let selectedGoogleFormatMode = 'static';
    let syncingBunnyVariants = false;
    let renderedBunnyVariantFamily = '';
    let selectedBunnyFormatMode = 'static';
    let roleDraftSaveInFlight = false;
    let previewRoleState = null;
    let previewWorkspaceInitialized = false;
    let previewDirty = false;
    let previewFollowsDraft = false;
    let defaultTrackedUiState = null;
    let pageReloadScheduled = false;
    let settingsNavigationInFlight = false;
    const pendingUiStateKey = 'tastyFontsPendingUiState';
    const reloadQueryKey = 'tf_reload';
    const trackedUiQueryKeys = [
        'tf_page',
        'tf_advanced',
        'tf_studio',
        'tf_preview',
        'tf_output',
        'tf_add_fonts',
        'tf_source',
        'tf_google_access',
        'tf_adobe_project'
    ];
    const trackedUiTabGroups = new Set(['page', 'settings', 'diagnostics', 'preview', 'output', 'add-font']);
    const trackedUiDisclosureTargets = new Set([
        'tasty-fonts-role-preview-panel',
        'tasty-fonts-role-snippets-panel',
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
        importSelectionSummaryAvailableVariable: __('%1$d of %2$d Styles Selected', 'tasty-fonts'),
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
        uploadFaceRowLabel: __('Font face %d', 'tasty-fonts'),
        uploadFaceRowLabelFamily: __('Font face %1$d for %2$s', 'tasty-fonts'),
        uploadRemoveFace: __('Remove font face %d', 'tasty-fonts'),
        disclosureExpanded: __('%s expanded.', 'tasty-fonts'),
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
        confirmAction: __('Confirm', 'tasty-fonts'),
        confirmActionLabel: __('Confirm %s', 'tasty-fonts'),
        continueAction: __('Continue', 'tasty-fonts'),
        cancelAction: __('Cancel', 'tasty-fonts'),
        confirmActionShort: __('Confirm?', 'tasty-fonts'),
        copied: __('Copied', 'tasty-fonts'),
        activityCountSingle: __('%1$d entry', 'tasty-fonts'),
        activityCountMultiple: __('%1$d entries', 'tasty-fonts'),
        activityCountFilteredSingle: __('%1$d of %2$d entry', 'tasty-fonts'),
        activityCountFilteredMultiple: __('%1$d of %2$d entries', 'tasty-fonts'),
        variantCountSingle: __('%d variant', 'tasty-fonts'),
        variantCountMultiple: __('%d variants', 'tasty-fonts'),
        styleCountSingle: __('%d style', 'tasty-fonts'),
        styleCountMultiple: __('%d styles', 'tasty-fonts'),
        variableBadge: __('Variable', 'tasty-fonts'),
        staticBadge: __('Static', 'tasty-fonts'),
        variableSourceBadge: __('Variable Source', 'tasty-fonts'),
        googleVariableImportNote: __('Variable import keeps the upstream axis ranges and stores a variable font file when Google serves one.', 'tasty-fonts'),
        bunnyVariableImportNote: __('Bunny Fonts doesn\'t deliver variable fonts through download or CDN. Use Upload Files to add a true variable font file.', 'tasty-fonts'),
        requestFailed: __('Request failed.', 'tasty-fonts'),
        dismissNotification: __('Dismiss notification', 'tasty-fonts'),
        bunnySourceLabel: __('Bunny Fonts', 'tasty-fonts'),
        headingVariableTitle: __('Heading font variable: %1$s. Resolved stack: %2$s', 'tasty-fonts'),
        bodyVariableTitle: __('Body font variable: %1$s. Resolved stack: %2$s', 'tasty-fonts'),
        monospaceVariableTitle: __('Monospace font variable: %1$s. Resolved stack: %2$s', 'tasty-fonts'),
        headingFamilyVariableTitle: __('Heading family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'),
        headingFamilyFallbackTitle: __('Heading uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'),
        bodyFamilyVariableTitle: __('Body family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'),
        bodyFamilyFallbackTitle: __('Body uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'),
        monospaceFamilyVariableTitle: __('Monospace family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s', 'tasty-fonts'),
        monospaceFamilyFallbackTitle: __('Monospace uses the fallback stack directly: %1$s. Role alias: %2$s', 'tasty-fonts'),
        roleWeightDefault: __('Use Role Default (%1$s)', 'tasty-fonts'),
        roleWeightSummary: __('Available static weights for %1$s. Choose one to override the default role weight when role font-weight output is enabled.', 'tasty-fonts'),
        settingsUnsaved: __('Unsaved changes', 'tasty-fonts'),
        settingsLeaveWarning: __('You have unsaved settings changes.', 'tasty-fonts'),
    };

    function getHelpTooltipLayer() {
        if (!helpTooltipLayer || !document.body.contains(helpTooltipLayer)) {
            helpTooltipLayer = document.getElementById('tasty-fonts-help-tooltip-layer');
        }

        return helpTooltipLayer;
    }

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
    const settingsStatesMatch = typeof adminContracts.settingsStatesMatch === 'function'
        ? adminContracts.settingsStatesMatch
        : (left, right) => JSON.stringify(left || {}) === JSON.stringify(right || {});
    const getTabNavigationTargetIndex = typeof adminContracts.getTabNavigationTargetIndex === 'function'
        ? adminContracts.getTabNavigationTargetIndex
        : (key, currentIndex, count, orientation = 'horizontal') => {
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
        };
    const resolveStatusAnnouncement = typeof adminContracts.resolveStatusAnnouncement === 'function'
        ? adminContracts.resolveStatusAnnouncement
        : (type) => (String(type || '').trim().toLowerCase() === 'error'
            ? { role: 'alert', live: 'assertive' }
            : { role: 'status', live: 'polite' });
    const describeFontType = typeof adminContracts.describeFontType === 'function'
        ? adminContracts.describeFontType
        : (entry, provider = 'library') => {
            const hasVariable = !!(
                entry
                && typeof entry === 'object'
                && (
                    !!entry.has_variable_faces
                    || !!entry.is_variable
                    || (entry.variation_axes && typeof entry.variation_axes === 'object' && Object.keys(entry.variation_axes).length > 0)
                    || (entry.axes && typeof entry.axes === 'object' && Object.keys(entry.axes).length > 0)
                    || (Array.isArray(entry.axis_tags) && entry.axis_tags.some((tag) => /^[A-Z0-9]{4}$/i.test(String(tag || '').trim())))
                    || (Array.isArray(entry.faces) && entry.faces.some((face) => face && typeof face === 'object' && (!!face.is_variable || (face.axes && Object.keys(face.axes).length > 0))))
                )
            );
            const hasStatic = !!(
                entry
                && typeof entry === 'object'
                && (
                    !!entry.has_static_faces
                    || (
                        entry.formats
                        && typeof entry.formats === 'object'
                        && entry.formats.static
                        && typeof entry.formats.static === 'object'
                    )
                    || (Array.isArray(entry.faces) && entry.faces.some((face) => face && typeof face === 'object' && !face.is_variable && !(face.axes && Object.keys(face.axes).length > 0)))
                    || !hasVariable
                )
            );
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

            return {
                type: hasStatic && hasVariable ? 'static-variable' : (hasVariable ? 'variable' : 'static'),
                hasVariable,
                hasStatic,
                isSourceOnly: !!(
                    hasVariable
                    && (
                        (variableFormat && typeof variableFormat === 'object' && variableFormat.source_only)
                        || (normalizedProvider === 'bunny' && (!variableFormat || variableFormat.available === false))
                    )
                ),
            };
        };
    const sanitizeOutputQuickModePreference = typeof adminContracts.sanitizeOutputQuickModePreference === 'function'
        ? adminContracts.sanitizeOutputQuickModePreference
        : (value) => {
            const normalized = String(value || '').trim().toLowerCase();

            return ['minimal', 'variables', 'classes', 'custom'].includes(normalized) ? normalized : '';
        };
    const deriveExactOutputQuickMode = typeof adminContracts.deriveExactOutputQuickMode === 'function'
        ? adminContracts.deriveExactOutputQuickMode
        : (state = {}) => {
            const minimalEnabled = !!state.minimalEnabled;
            const classOutputEnabled = !!state.classOutputEnabled;
            const variableOutputEnabled = !!state.variableOutputEnabled;
            const roleUsageFontWeightEnabled = !!state.roleUsageFontWeightEnabled;
            const classFlags = Array.isArray(state.classFlags) ? state.classFlags.filter((value) => typeof value === 'boolean') : [];
            const variableFlags = Array.isArray(state.variableFlags) ? state.variableFlags.filter((value) => typeof value === 'boolean') : [];

            if (minimalEnabled) {
                return 'minimal';
            }

            if (!roleUsageFontWeightEnabled && !classOutputEnabled && variableOutputEnabled && variableFlags.every((value) => value)) {
                return 'variables';
            }

            if (!roleUsageFontWeightEnabled && classOutputEnabled && !variableOutputEnabled && classFlags.every((value) => value)) {
                return 'classes';
            }

            return 'custom';
        };
    const normalizeOutputQuickModePreference = typeof adminContracts.normalizeOutputQuickModePreference === 'function'
        ? adminContracts.normalizeOutputQuickModePreference
        : (preference, state = {}) => {
            const normalizedPreference = sanitizeOutputQuickModePreference(preference);
            const exactMode = deriveExactOutputQuickMode(state);

            if (normalizedPreference === 'custom') {
                return 'custom';
            }

            if (!normalizedPreference) {
                return exactMode;
            }

            return exactMode === normalizedPreference ? normalizedPreference : 'custom';
        };
    const canDisableOutputLayer = typeof adminContracts.canDisableOutputLayer === 'function'
        ? adminContracts.canDisableOutputLayer
        : (layerKey, state = {}) => {
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
        };

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
            const familySelects = [
                previewRoleSelects.heading,
                previewRoleSelects.body,
                previewRoleSelects.monospace,
                roleHeading,
                roleBody,
                roleMonospace,
            ].filter(Boolean);

            for (const select of familySelects) {
                const match = Array.from(select.options || []).find((option) => String(option.value || '') === trimmedFamily);

                if (match) {
                    return String(match.textContent || '').trim() || trimmedFamily;
                }
            }

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

    function defaultRoleFallback(roleKey) {
        return roleKey === 'monospace' ? 'monospace' : 'sans-serif';
    }

    function resolveRoleFallbackValue(roleKey, input = {}) {
        const defaultFallback = defaultRoleFallback(roleKey);
        const familyName = String(input[roleKey] || '').trim();

        if (familyName) {
            const entry = roleFamilyEntryForFamily(familyName);
            const entryFallback = entry && typeof entry.fallback === 'string' ? entry.fallback : '';

            if (entryFallback.trim() !== '') {
                return sanitizeFallback(entryFallback, defaultFallback);
            }
        }

        return sanitizeFallback(
            input[`${roleKey}Fallback`] || input[`${roleKey}_fallback`],
            defaultFallback
        );
    }

    function buildRoleSelectionKey(roleKeys) {
        return ['heading', 'body', 'monospace'].filter((roleKey) => roleKeys.includes(roleKey)).join('-');
    }

    function normalizeAxisTag(tag) {
        const normalized = String(tag || '').trim().toUpperCase();

        return /^[A-Z0-9]{4}$/.test(normalized) ? normalized : '';
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

    function normalizeAxisSettings(input = {}) {
        if (!input || typeof input !== 'object') {
            return {};
        }

        const normalized = {};

        Object.entries(input).forEach(([tag, value]) => {
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

    function normalizeRoleWeightValue(value) {
        const normalized = String(value || '').trim().toLowerCase();

        if (!normalized) {
            return '';
        }

        if (normalized === 'normal') {
            return '400';
        }

        if (normalized === 'bold') {
            return '700';
        }

        return /^\d{1,4}$/.test(normalized) ? normalized : '';
    }

    function buildVariationSettings(settings = {}) {
        const normalized = normalizeAxisSettings(settings);
        const parts = Object.entries(normalized).map(([tag, value]) => `"${cssAxisTag(tag)}" ${value}`);

        return parts.length ? parts.join(', ') : 'normal';
    }

    function normalizeRoleState(input = {}) {
        return {
            heading: String(input.heading || '').trim(),
            body: String(input.body || '').trim(),
            monospace: monospaceRoleEnabled ? String(input.monospace || '').trim() : '',
            headingFallback: resolveRoleFallbackValue('heading', input),
            bodyFallback: resolveRoleFallbackValue('body', input),
            monospaceFallback: resolveRoleFallbackValue('monospace', input),
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

    function buildRoleDataFromValues(values = {}) {
        const normalized = normalizeRoleState(values);
        const heading = normalized.heading;
        const body = normalized.body;
        const monospace = monospaceRoleEnabled ? normalized.monospace : '';
        const applyRoleWeights = !!(outputRoleWeightInput && outputRoleWeightInput.checked);
        const headingPreviewWeight = hasExplicitRoleWeight('heading', normalized)
            ? resolveRoleWeight('heading', normalized)
            : '';
        const bodyPreviewWeight = hasExplicitRoleWeight('body', normalized)
            ? resolveRoleWeight('body', normalized)
            : '';
        const monospacePreviewWeight = hasExplicitRoleWeight('monospace', normalized)
            ? resolveRoleWeight('monospace', normalized)
            : '';

        return {
            includeMonospace: monospaceRoleEnabled,
            heading,
            body,
            monospace,
            headingFallback: normalized.headingFallback,
            bodyFallback: normalized.bodyFallback,
            monospaceFallback: normalized.monospaceFallback,
            headingWeight: normalized.headingWeight,
            bodyWeight: normalized.bodyWeight,
            monospaceWeight: normalized.monospaceWeight,
            headingAxes: normalized.headingAxes,
            bodyAxes: normalized.bodyAxes,
            monospaceAxes: normalized.monospaceAxes,
            headingResolvedWeight: resolveRoleWeight('heading', normalized),
            bodyResolvedWeight: resolveRoleWeight('body', normalized),
            monospaceResolvedWeight: resolveRoleWeight('monospace', normalized),
            headingPreviewWeight,
            bodyPreviewWeight,
            monospacePreviewWeight,
            applyRoleWeights,
            headingSettings: buildVariationSettings(normalized.headingAxes),
            bodySettings: buildVariationSettings(normalized.bodyAxes),
            monospaceSettings: buildVariationSettings(normalized.monospaceAxes),
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

    function syncPreviewBootstrapState(nextState = {}) {
        const bootstrapConfig = config.previewBootstrap && typeof config.previewBootstrap === 'object'
            ? config.previewBootstrap
            : {};

        config.previewBootstrap = bootstrapConfig;

        if (nextState.roles && typeof nextState.roles === 'object' && !Array.isArray(nextState.roles)) {
            bootstrapConfig.roles = normalizeRoleState(nextState.roles);
        }

        if (nextState.appliedRoles && typeof nextState.appliedRoles === 'object' && !Array.isArray(nextState.appliedRoles)) {
            bootstrapConfig.appliedRoles = normalizeRoleState(nextState.appliedRoles);
        }

        if (typeof nextState.baselineSource === 'string') {
            bootstrapConfig.baselineSource = nextState.baselineSource === 'live_sitewide' ? 'live_sitewide' : 'draft';
        }

        if (typeof nextState.baselineLabel === 'string') {
            bootstrapConfig.baselineLabel = nextState.baselineLabel;
        }
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

    function defaultRequestFailedMessage() {
        return getString('requestFailed', 'Request failed.');
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
            throw new Error(fallbackMessage || defaultRequestFailedMessage());
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
            throw new Error(getApiMessage(payload, fallbackMessage || defaultRequestFailedMessage()));
        }

        return payload && typeof payload === 'object' ? payload : {};
    }

    function getSettingsFormState(form) {
        if (!form) {
            return null;
        }

        if (!form._tastyFontsSettingsState) {
            form._tastyFontsSettingsState = {
                initialState: {},
                isDirty: false,
            };
        }

        return form._tastyFontsSettingsState;
    }

    function serializeSettingsForm(form) {
        const body = {};

        if (!form || !window.FormData) {
            return body;
        }

        const formData = new FormData(form);

        formData.forEach((value, key) => {
            if (
                key === '_wpnonce'
                || key === '_wp_http_referer'
                || key === 'tasty_fonts_save_settings'
                || key === 'tasty_fonts_output_quick_mode'
            ) {
                return;
            }

            if (typeof value !== 'string') {
                return;
            }

            body[key] = value;
        });

        return body;
    }

    function syncCheckboxFields(name, checked) {
        document.querySelectorAll(`input[type="checkbox"][name="${name}"]`).forEach((input) => {
            input.checked = !!checked;
        });
    }

    function syncRadioFields(name, value) {
        document.querySelectorAll(`input[type="radio"][name="${name}"]`).forEach((input) => {
            input.checked = input.value === String(value);
        });
    }

    function syncMonoDependentControls(enabled, options = {}) {
        outputMonoDependentInputs.forEach((input) => {
            input.disabled = !enabled;

            const label = input.closest('.tasty-fonts-toggle-field');

            if (label) {
                label.classList.toggle('is-disabled', !enabled);
            }
        });

        if (enabled && options.enableDefaults) {
            outputMonoDependentInputs.forEach((input) => {
                input.checked = true;
            });
        }
    }

    function setSettingsFormStatus(form, isDirty) {
        if (!form) {
            return;
        }

        getSettingsFormStatuses(form).forEach((status) => {
            status.textContent = isDirty
                ? getString('settingsUnsaved', 'Unsaved changes')
                : '';
            status.hidden = !isDirty;
        });
    }

    function settingsFormHasUnsavedChanges(form) {
        const state = getSettingsFormState(form);

        return !!(state && state.isDirty);
    }

    function anySettingsFormHasUnsavedChanges() {
        return settingsForms.some((form) => settingsFormHasUnsavedChanges(form));
    }

    function syncSettingsFormDirtyState(form) {
        const state = getSettingsFormState(form);

        if (!form || !state) {
            return false;
        }

        const currentState = serializeSettingsForm(form);
        const isDirty = !settingsStatesMatch(state.initialState, currentState);

        state.isDirty = isDirty;
        form.toggleAttribute('data-has-unsaved-changes', isDirty);
        form.classList.toggle('has-unsaved-changes', isDirty);

        getSettingsFormButtons(form).forEach((button) => {
            button.disabled = !isDirty;
        });

        setSettingsFormStatus(form, isDirty);

        return isDirty;
    }

    function refreshSettingsFormBaseline(form) {
        const state = getSettingsFormState(form);

        if (!form || !state) {
            return;
        }

        state.initialState = serializeSettingsForm(form);
        state.isDirty = false;
        form.removeAttribute('data-has-unsaved-changes');
        form.classList.remove('has-unsaved-changes');

        getSettingsFormButtons(form).forEach((button) => {
            button.disabled = true;
        });

        setSettingsFormStatus(form, false);
    }

    function getSettingsFormButtons(form) {
        if (!form) {
            return [];
        }

        const formId = String(form.getAttribute('id') || '').trim();
        const internalButtons = Array.from(form.querySelectorAll('[data-settings-save-button]'));
        const externalButtons = formId
            ? Array.from(document.querySelectorAll(`[data-settings-save-button][form="${formId}"]`))
            : [];

        return Array.from(new Set([...internalButtons, ...externalButtons]));
    }

    function getSettingsFormStatuses(form) {
        if (!form) {
            return [];
        }

        const formId = String(form.getAttribute('id') || '').trim();
        const internalStatuses = Array.from(form.querySelectorAll('[data-settings-save-status]'));
        const externalStatuses = formId
            ? Array.from(document.querySelectorAll(`[data-settings-save-status][data-settings-form-id="${formId}"]`))
            : [];

        return Array.from(new Set([...internalStatuses, ...externalStatuses]));
    }

    function syncSettingsSaveShell() {
        if (!settingsSaveShell) {
            return;
        }

        settingsSaveShell.hidden = activeTabKeyForGroup('settings') === 'developer';
    }

    function handleSettingsBeforeUnload(event) {
        if (settingsNavigationInFlight || !anySettingsFormHasUnsavedChanges()) {
            return;
        }

        event.preventDefault();
        event.returnValue = getString('settingsLeaveWarning', 'You have unsaved settings changes.');
    }

    function applySavedSettingsState(settings) {
        if (!settings || typeof settings !== 'object') {
            return;
        }

        if (Object.prototype.hasOwnProperty.call(settings, 'css_delivery_mode')) {
            syncRadioFields('css_delivery_mode', settings.css_delivery_mode || 'file');
        }

        if (Object.prototype.hasOwnProperty.call(settings, 'font_display')) {
            syncRadioFields('font_display', settings.font_display || 'optional');
        }

        if (Object.prototype.hasOwnProperty.call(settings, 'unicode_range_mode')) {
            syncRadioFields('unicode_range_mode', settings.unicode_range_mode || 'off');
        }

        if (Object.prototype.hasOwnProperty.call(settings, 'unicode_range_custom_value') && unicodeRangeCustomInput) {
            unicodeRangeCustomInput.value = settings.unicode_range_custom_value || '';
        }

        if (Object.prototype.hasOwnProperty.call(settings, 'output_quick_mode_preference') && outputQuickModePreferenceInput) {
            const normalizedQuickModePreference = sanitizeOutputQuickModePreference(settings.output_quick_mode_preference) || 'custom';
            outputQuickModePreferenceInput.value = normalizedQuickModePreference;
            outputQuickModePreferenceInput.setAttribute('data-output-quick-mode-saved-preference', normalizedQuickModePreference);
        }

        [
            'minify_css_output',
            'role_usage_font_weight_enabled',
            'class_output_enabled',
            'class_output_role_heading_enabled',
            'class_output_role_body_enabled',
            'class_output_role_monospace_enabled',
            'class_output_role_alias_interface_enabled',
            'class_output_role_alias_ui_enabled',
            'class_output_role_alias_code_enabled',
            'class_output_category_sans_enabled',
            'class_output_category_serif_enabled',
            'class_output_category_mono_enabled',
            'class_output_families_enabled',
            'per_variant_font_variables_enabled',
            'extended_variable_weight_tokens_enabled',
            'extended_variable_role_aliases_enabled',
            'extended_variable_category_sans_enabled',
            'extended_variable_category_serif_enabled',
            'extended_variable_category_mono_enabled',
            'preload_primary_fonts',
            'remote_connection_hints',
            'block_editor_font_library_sync_enabled',
            'acss_font_role_sync_enabled',
            'delete_uploaded_files_on_uninstall',
            'training_wheels_off',
            'monospace_role_enabled'
        ].forEach((field) => {
            if (!Object.prototype.hasOwnProperty.call(settings, field)) {
                return;
            }

            syncCheckboxFields(field, !!settings[field]);
        });

        if (Object.prototype.hasOwnProperty.call(settings, 'minimal_output_preset_enabled') && outputMinimalPresetInput) {
            outputMinimalPresetInput.value = settings.minimal_output_preset_enabled ? '1' : '0';
        }

        if (Object.prototype.hasOwnProperty.call(settings, 'monospace_role_enabled')) {
            monospaceRoleEnabled = !!settings.monospace_role_enabled && !!roleMonospace && !!roleMonospaceFallback;
            syncMonoDependentControls(!!settings.monospace_role_enabled);
        }

        syncOutputSettingsUi();
        syncUnicodeRangeUi();

        settingsForms.forEach((form) => {
            refreshSettingsFormBaseline(form);
        });
    }

    function escapeSnippetHtml(value) {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function highlightSnippetValue(value) {
        const parts = String(value || '').split(/(".*?"|'.*?')/);

        return parts.map((part) => {
            if (!part) {
                return '';
            }

            if (
                (part.startsWith('"') && part.endsWith('"'))
                || (part.startsWith("'") && part.endsWith("'"))
            ) {
                return `<span class="tasty-fonts-syntax-string">${escapeSnippetHtml(part)}</span>`;
            }

            return escapeSnippetHtml(part);
        }).join('');
    }

    function highlightSnippetLine(line) {
        const value = String(line || '');
        const trimmed = value.trim();

        if (!trimmed) {
            return '';
        }

        let matches = value.match(/^(\s*)(\/\*.*\*\/|\*\/|\*)(.*)$/);

        if (matches) {
            return `<span class="tasty-fonts-syntax-comment">${escapeSnippetHtml(value)}</span>`;
        }

        matches = value.match(/^(\s*)(@[\w-]+)(\s+[^{};]+)?(\s*\{?\s*;?\s*)$/);

        if (matches) {
            return escapeSnippetHtml(matches[1] || '')
                + `<span class="tasty-fonts-syntax-at-rule">${escapeSnippetHtml(matches[2] || '')}</span>`
                + highlightSnippetValue(matches[3] || '')
                + `<span class="tasty-fonts-syntax-punctuation">${escapeSnippetHtml(matches[4] || '')}</span>`;
        }

        matches = value.match(/^(\s*)([^{}]+)(\s*\{\s*)$/);

        if (matches) {
            return escapeSnippetHtml(matches[1] || '')
                + `<span class="tasty-fonts-syntax-selector">${escapeSnippetHtml((matches[2] || '').trim())}</span>`
                + `<span class="tasty-fonts-syntax-punctuation">${escapeSnippetHtml(matches[3] || '')}</span>`;
        }

        matches = value.match(/^(\s*)(--[\w-]+|[\w-]+)(\s*:\s*)(.+?)(\s*[;,]?\s*)$/);

        if (matches) {
            return escapeSnippetHtml(matches[1] || '')
                + `<span class="tasty-fonts-syntax-property">${escapeSnippetHtml(matches[2] || '')}</span>`
                + `<span class="tasty-fonts-syntax-punctuation">${escapeSnippetHtml(matches[3] || '')}</span>`
                + highlightSnippetValue(matches[4] || '')
                + `<span class="tasty-fonts-syntax-punctuation">${escapeSnippetHtml(matches[5] || '')}</span>`;
        }

        matches = value.match(/^(\s*)([{}])(\s*)$/);

        if (matches) {
            return escapeSnippetHtml(matches[1] || '')
                + `<span class="tasty-fonts-syntax-punctuation">${escapeSnippetHtml(matches[2] || '')}</span>`
                + escapeSnippetHtml(matches[3] || '');
        }

        return escapeSnippetHtml(value);
    }

    function renderHighlightedSnippet(value) {
        return String(value || '')
            .split(/\r\n|\n|\r/)
            .map((line) => highlightSnippetLine(line))
            .join('\n');
    }

    function setSnippetTargetContent(target, value) {
        if (!target) {
            return;
        }

        if ('value' in target) {
            target.value = value;
            return;
        }

        target.innerHTML = renderHighlightedSnippet(value);
    }

    function setSnippetPanelContent(targetId, rawValue, displayValue = '') {
        if (!targetId) {
            return;
        }

        const target = document.getElementById(targetId);

        if (!target) {
            return;
        }

        const nextRawValue = String(rawValue || '');
        const nextDisplayValue = displayValue !== '' ? String(displayValue) : nextRawValue;
        const codePanel = target.closest('.tasty-fonts-code-panel');
        const copyButton = codePanel ? codePanel.querySelector('[data-copy-text]') : null;

        setSnippetTargetContent(target, nextDisplayValue);

        if (copyButton) {
            copyButton.setAttribute('data-copy-text', nextRawValue);
        }
    }

    function applyOutputPanelsState(panels) {
        if (!Array.isArray(panels) || panels.length === 0) {
            return;
        }

        panels.forEach((panel) => {
            if (!panel || typeof panel !== 'object') {
                return;
            }

            setSnippetPanelContent(
                String(panel.target || ''),
                String(panel.value || ''),
                typeof panel.display_value === 'string' ? panel.display_value : ''
            );
        });
    }

    function applyGeneratedCssPanelState(panel) {
        if (!panel || typeof panel !== 'object') {
            return;
        }

        const targetId = String(panel.target || '');
        const rawValue = String(panel.value || '');

        if (!targetId) {
            return;
        }

        setSnippetPanelContent(
            targetId,
            rawValue,
            typeof panel.display_value === 'string' ? panel.display_value : rawValue
        );

        const readableTargetId = `${targetId}-readable`;
        const readableTarget = document.getElementById(readableTargetId);

        if (readableTarget && typeof panel.readable_display_value === 'string') {
            setSnippetTargetContent(readableTarget, panel.readable_display_value);
        }
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

    function disclosureToggleElements() {
        return Array.from(document.querySelectorAll('[data-disclosure-toggle]'));
    }

    function tabButtonElements() {
        return Array.from(document.querySelectorAll('[data-tab-group][data-tab-target]'));
    }

    function tabPanelElements() {
        return Array.from(document.querySelectorAll('[data-tab-group][data-tab-panel]'));
    }

    function disclosureToggleByTargetId(targetId) {
        if (!targetId) {
            return null;
        }

        return disclosureToggleElements().find((toggle) => getDisclosureToggleTargetId(toggle) === targetId) || null;
    }

    function isDisclosureTargetVisible(target) {
        return Boolean(target) && !target.hidden && !target.closest('[hidden]');
    }

    function isDisclosureExpanded(toggle) {
        if (!toggle || toggle.getAttribute('aria-expanded') !== 'true') {
            return false;
        }

        return isDisclosureTargetVisible(getDisclosureTarget(toggle));
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
            case 'page':
                return String(defaultTrackedUiState.page || '');
            case 'settings':
            case 'diagnostics':
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
        const page = String(params.get('tf_page') || '');
        const studio = String(params.get('tf_studio') || '');
        const preview = String(params.get('tf_preview') || '');
        const output = String(params.get('tf_output') || '');
        const source = String(params.get('tf_source') || '');
        const previewToggle = disclosureToggleByTargetId('tasty-fonts-role-preview-panel');
        const snippetsToggle = disclosureToggleByTargetId('tasty-fonts-role-snippets-panel');

        if (isAllowedTabKey('page', page)) {
            state.page = page;
        }

        const resolvedPage = state.page || currentPage;

        if (resolvedPage === 'settings') {
            if (isAllowedTabKey('settings', studio)) {
                state.studio = studio;
            }

            return state;
        }

        if (resolvedPage === 'diagnostics') {
            if (isAllowedTabKey('diagnostics', studio)) {
                state.studio = studio;
            }

            if (state.studio === 'snippets' && isAllowedTabKey('output', output)) {
                state.output = output;
            }

            return state;
        }

        if (resolvedPage === 'library') {
            if (params.get('tf_add_fonts') === '1' && addFontsPanelToggle) {
                state.addFontsOpen = true;
            }

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

        if (params.get('tf_advanced') === '1') {
            if (studio === 'preview' && previewToggle) {
                state.previewOpen = true;
            } else if (studio === 'snippets' && snippetsToggle) {
                state.snippetsOpen = true;
            }
        }

        if (state.previewOpen && isAllowedTabKey('preview', preview)) {
            state.preview = preview;
        }

        if (state.snippetsOpen && isAllowedTabKey('output', output)) {
            state.output = output;
        }

        if (params.get('tf_add_fonts') === '1' && addFontsPanelToggle) {
            state.addFontsOpen = true;
        }

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
        const snippetsToggle = disclosureToggleByTargetId('tasty-fonts-role-snippets-panel');
        const resolvedPage = resolveTrackedTabKey(
            'page',
            trackedUiStateHas(nextState, 'page') ? String(nextState.page || '') : currentPage
        ) || 'roles';

        activateTabGroup('page', resolvedPage);

        if (resolvedPage === 'settings') {
            const studio = resolveTrackedTabKey(
                'settings',
                trackedUiStateHas(nextState, 'studio') ? String(nextState.studio || '') : defaultTrackedUiTabKey('settings')
            );

            if (studio) {
                activateTabGroup('settings', studio);
            }

            return;
        }

        if (resolvedPage === 'diagnostics') {
            const studio = resolveTrackedTabKey(
                'diagnostics',
                trackedUiStateHas(nextState, 'studio') ? String(nextState.studio || '') : defaultTrackedUiTabKey('diagnostics')
            );
            const output = resolveTrackedTabKey(
                'output',
                trackedUiStateHas(nextState, 'output') ? String(nextState.output || '') : defaultTrackedUiTabKey('output')
            );

            if (studio) {
                activateTabGroup('diagnostics', studio);
            }

            if (output) {
                activateTabGroup('output', output);
            }

            return;
        }

        if (resolvedPage === 'library') {
            const addFontsOpen = trackedUiStateHas(nextState, 'addFontsOpen')
                ? Boolean(nextState.addFontsOpen)
                : defaultTrackedUiFlag('addFontsOpen');
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

            if (addFontsPanelToggle) {
                setDisclosureState(addFontsPanelToggle, addFontsOpen);
            }

            if (addFontsOpen && source) {
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

            return;
        }

        if (resolvedPage === 'roles') {
            const previewOpen = trackedUiStateHas(nextState, 'previewOpen')
                ? Boolean(nextState.previewOpen)
                : defaultTrackedUiFlag('previewOpen');
            const snippetsOpen = !previewOpen && (trackedUiStateHas(nextState, 'snippetsOpen')
                ? Boolean(nextState.snippetsOpen)
                : defaultTrackedUiFlag('snippetsOpen'));
            const preview = resolveTrackedTabKey(
                'preview',
                trackedUiStateHas(nextState, 'preview') ? String(nextState.preview || '') : defaultTrackedUiTabKey('preview')
            );
            const output = resolveTrackedTabKey(
                'output',
                trackedUiStateHas(nextState, 'output') ? String(nextState.output || '') : defaultTrackedUiTabKey('output')
            );

            if (previewToggle) {
                setDisclosureState(previewToggle, previewOpen);
            }

            if (snippetsToggle) {
                setDisclosureState(snippetsToggle, snippetsOpen);
            }

            if (previewOpen && preview) {
                activateTabGroup('preview', preview);
            }

            if (snippetsOpen && output) {
                activateTabGroup('output', output);
            }

            return;
        }

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

        if (snippetsToggle) {
            setDisclosureState(snippetsToggle, advancedOpen);
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

        if (!isDisclosureTargetVisible(panel) || typeof window.scrollTo !== 'function') {
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
        const snippetsToggle = disclosureToggleByTargetId('tasty-fonts-role-snippets-panel');
        const googleAccessToggle = disclosureToggleByTargetId('tasty-fonts-google-access-panel');
        const adobeProjectToggle = disclosureToggleByTargetId('tasty-fonts-adobe-project-panel');
        const page = activeTabKeyForGroup('page') || currentPage;

        if (page && isAllowedTabKey('page', page) && page !== 'roles') {
            state.page = page;
        }

        if (page === 'settings') {
            const studio = activeTabKeyForGroup('settings');

            if (isAllowedTabKey('settings', studio)) {
                state.studio = studio;
            }

            return state;
        }

        if (page === 'diagnostics') {
            const studio = activeTabKeyForGroup('diagnostics');

            if (isAllowedTabKey('diagnostics', studio)) {
                state.studio = studio;
            }

            if (studio === 'snippets') {
                const output = activeTabKeyForGroup('output');

                if (isAllowedTabKey('output', output)) {
                    state.output = output;
                }
            }

            return state;
        }

        if (page === 'library') {
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

        if (page === 'roles') {
            if (isDisclosureExpanded(previewToggle)) {
                state.previewOpen = true;

                const preview = activeTabKeyForGroup('preview');

                if (isAllowedTabKey('preview', preview)) {
                    state.preview = preview;
                }
            } else if (isDisclosureExpanded(snippetsToggle)) {
                state.snippetsOpen = true;

                const output = activeTabKeyForGroup('output');

                if (isAllowedTabKey('output', output)) {
                    state.output = output;
                }
            }

            return state;
        }

        if (isDisclosureExpanded(previewToggle)) {
            state.previewOpen = true;

            const preview = activeTabKeyForGroup('preview');

            if (isAllowedTabKey('preview', preview)) {
                state.preview = preview;
            }
        } else if (isDisclosureExpanded(snippetsToggle)) {
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

        if (state.page) {
            nextUrl.searchParams.set('tf_page', state.page);
        }

        if (currentPage === 'settings') {
            if (state.studio) {
                nextUrl.searchParams.set('tf_studio', state.studio);
            }
        } else if (currentPage === 'diagnostics') {
            if (state.studio) {
                nextUrl.searchParams.set('tf_studio', state.studio);
            }

            if (state.output) {
                nextUrl.searchParams.set('tf_output', state.output);
            }
        } else if (currentPage === 'library') {
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
        } else if (currentPage === 'roles') {
            if (state.previewOpen) {
                nextUrl.searchParams.set('tf_advanced', '1');
                nextUrl.searchParams.set('tf_studio', 'preview');
            }

            if (state.previewOpen && state.preview) {
                nextUrl.searchParams.set('tf_preview', state.preview);
            } else if (state.snippetsOpen) {
                nextUrl.searchParams.set('tf_advanced', '1');
                nextUrl.searchParams.set('tf_studio', 'snippets');
            }

            if (state.snippetsOpen && state.output) {
                nextUrl.searchParams.set('tf_output', state.output);
            }
        } else {
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
        }

        if (nextUrl.toString() === currentUrl.toString()) {
            return;
        }

        window.history[method](window.history.state, '', nextUrl.toString());
    }

    function handleTrackedUiPopState() {
        applyTrackedUiState(readTrackedUiState(window.location));
        syncRoleDisclosureForCurrentPage();
    }

    function syncRoleDisclosureForCurrentPage() {
        if (currentPage !== 'roles') {
            return;
        }

        const previewToggle = disclosureToggleByTargetId('tasty-fonts-role-preview-panel');
        const snippetsToggle = disclosureToggleByTargetId('tasty-fonts-role-snippets-panel');

        if (previewToggle && isDisclosureExpanded(previewToggle)) {
            initializePreviewWorkspace();
            revealDisclosurePanel('tasty-fonts-role-preview-panel', previewToggle);
            return;
        }

        if (snippetsToggle && isDisclosureExpanded(snippetsToggle)) {
            revealDisclosurePanel('tasty-fonts-role-snippets-panel', snippetsToggle);
        }
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

    function canonicalizeVariantToken(token) {
        const normalized = String(token || '').trim().toLowerCase();

        if (!normalized) {
            return '';
        }

        const compact = normalized.replace(/[\s_-]+/g, '');
        const weightAliases = {
            thin: '100',
            hairline: '100',
            extralight: '200',
            ultralight: '200',
            light: '300',
            regular: 'regular',
            normal: 'regular',
            book: 'regular',
            medium: '500',
            semibold: '600',
            demibold: '600',
            bold: '700',
            extrabold: '800',
            ultrabold: '800',
            black: '900',
            heavy: '900',
        };

        if (compact === 'italic') {
            return 'italic';
        }

        if (/^([1-9]00)(italic)?$/.test(compact) || /^([1-9]00)\.\.([1-9]00)(italic)?$/.test(compact)) {
            return compact;
        }

        if (weightAliases[compact]) {
            return weightAliases[compact];
        }

        if (compact.endsWith('italic')) {
            const weightAlias = compact.slice(0, -6);

            if (weightAlias === '') {
                return 'italic';
            }

            if (weightAliases[weightAlias]) {
                return weightAliases[weightAlias] === 'regular'
                    ? 'italic'
                    : `${weightAliases[weightAlias]}italic`;
            }
        }

        return '';
    }

    function resolveGoogleManualVariantTokens() {
        const tokens = normalizeVariantTokens(manualVariants ? manualVariants.value : '');

        return currentGoogleFormatMode() === 'variable'
            ? googleDisplayVariants(tokens)
            : tokens;
    }

    function resolveBunnyManualVariantTokens() {
        const tokens = normalizeHostedVariantTokens(getElementValue(bunnyVariants, ''));

        return currentBunnyFormatMode() === 'variable'
            ? bunnyDisplayVariants(tokens)
            : tokens;
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
        if (pageReloadScheduled) {
            return;
        }

        pageReloadScheduled = true;
        settingsNavigationInFlight = true;

        if (pendingUiState) {
            savePendingUiState(pendingUiState);
        }

        window.setTimeout(() => {
            syncTrackedUiUrl('replace');
            const reloadUrl = new URL(window.location.href);
            reloadUrl.searchParams.set('page', 'tasty-custom-fonts');
            reloadUrl.searchParams.set(reloadQueryKey, String(Date.now()));
            window.location.replace(reloadUrl.toString());
        }, delay);
    }

    function roleFieldSnapshot() {
        const snapshot = {
            heading: getElementValue(roleHeading, ''),
            body: getElementValue(roleBody, ''),
            headingFallback: getElementValue(roleHeadingFallback, 'sans-serif'),
            bodyFallback: getElementValue(roleBodyFallback, 'sans-serif'),
            headingWeight: getElementValue(roleWeightSelects.heading, ''),
            bodyWeight: getElementValue(roleWeightSelects.body, ''),
            headingAxes: roleAxisFieldValues('heading'),
            bodyAxes: roleAxisFieldValues('body')
        };

        if (monospaceRoleEnabled) {
            snapshot.monospace = getElementValue(roleMonospace, '');
            snapshot.monospaceFallback = getElementValue(roleMonospaceFallback, 'monospace');
            snapshot.monospaceWeight = getElementValue(roleWeightSelects.monospace, '');
            snapshot.monospaceAxes = roleAxisFieldValues('monospace');
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

        renderAllRoleWeightEditors(snapshot);

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

        renderAllRoleAxisEditors(snapshot);
    }

    function roleAxisFieldValues(roleKey) {
        const values = {};

        document.querySelectorAll(`[data-role-axis-input="${roleKey}"]`).forEach((input) => {
            const tag = normalizeAxisTag(input.getAttribute('data-axis-tag') || '');
            const value = normalizeAxisValue(input.value);

            if (!tag || !value) {
                return;
            }

            values[tag] = value;
        });

        return normalizeAxisSettings(values);
    }

    function roleFamilyEntryForFamily(familyName) {
        const family = String(familyName || '').trim();
        const entry = family ? roleFamilyCatalog[family] : null;

        return entry && typeof entry === 'object' ? entry : null;
    }

    function deliveryFormatForFamily(familyName) {
        const entry = roleFamilyEntryForFamily(familyName);
        const format = entry ? String(entry.format || '').trim().toLowerCase() : '';

        return format === 'variable' ? 'variable' : 'static';
    }

    function roleAxisDefinitionsForFamily(familyName) {
        const entry = roleFamilyEntryForFamily(familyName);
        const axes = entry && entry.axes && typeof entry.axes === 'object' ? entry.axes : null;

        return axes && typeof axes === 'object' ? axes : {};
    }

    function roleWeightEntryForFamily(familyName) {
        return roleFamilyEntryForFamily(familyName);
    }

    function roleWeightOptionsForFamily(familyName) {
        const entry = roleWeightEntryForFamily(familyName);
        const weights = entry && Array.isArray(entry.weights) ? entry.weights : [];

        return weights.filter((option) => option && typeof option === 'object');
    }

    function familyUsesVariableWeightAxis(familyName) {
        const entry = roleWeightEntryForFamily(familyName);

        return !!(entry && entry.hasWeightAxis);
    }

    function renderRoleWeightEditor(roleKey, overrideState = null) {
        const editor = roleWeightEditors[roleKey];
        const select = roleWeightSelects[roleKey];
        const clearButton = select ? select.closest('.tasty-fonts-select-field')?.querySelector('[data-clear-select-button]') : null;

        if (!editor || !select) {
            return;
        }

        const roleSelect = ({
            heading: roleHeading,
            body: roleBody,
            monospace: roleMonospace,
        })[roleKey];
        const summary = editor.querySelector(`[data-role-weight-summary="${roleKey}"]`);
        const familyName = roleSelect ? String(roleSelect.value || '').trim() : '';
        const state = normalizeRoleState(overrideState || currentDraftRoleState());
        const deliveryFormat = deliveryFormatForFamily(familyName);
        const options = roleWeightOptionsForFamily(familyName);
        const shouldShow = !!familyName && deliveryFormat === 'static' && options.length > 1;
        const currentValue = state[`${roleKey}Weight`] || '';

        if (!shouldShow) {
            editor.hidden = true;
            select.innerHTML = '';

            if (clearButton) {
                syncClearableSelectButton(clearButton);
            }

            return;
        }

        editor.hidden = false;
        select.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = formatMessage(
            getString('roleWeightDefault', 'Use Role Default (%1$s)'),
            [defaultRoleWeight(roleKey)]
        );
        select.appendChild(defaultOption);

        const validValues = new Set(['']);

        options.forEach((option) => {
            const value = normalizeRoleWeightValue(option.value);

            if (!value) {
                return;
            }

            const element = document.createElement('option');
            element.value = value;
            element.textContent = String(option.label || value);
            select.appendChild(element);
            validValues.add(value);
        });

        select.value = validValues.has(currentValue) ? currentValue : '';

        if (summary) {
            summary.textContent = formatMessage(
                getString('roleWeightSummary', 'Available static weights for %1$s. Choose one to override the default role weight when role font-weight output is enabled.'),
                [familyName]
            );
        }

        if (clearButton) {
            syncClearableSelectButton(clearButton);
        }
    }

    function renderAllRoleWeightEditors(overrideState = null) {
        renderRoleWeightEditor('heading', overrideState);
        renderRoleWeightEditor('body', overrideState);

        if (monospaceRoleEnabled) {
            renderRoleWeightEditor('monospace', overrideState);
        }
    }

    function defaultRoleAxesForFamily(familyName) {
        const axes = roleAxisDefinitionsForFamily(familyName);
        const defaults = {};

        Object.entries(axes).forEach(([tag, definition]) => {
            const defaultValue = normalizeAxisValue(definition && definition.default);

            if (!defaultValue) {
                return;
            }

            defaults[normalizeAxisTag(tag)] = defaultValue;
        });

        return normalizeAxisSettings(defaults);
    }

    function renderRoleAxisEditor(roleKey, overrideState = null) {
        const editor = roleAxisEditors[roleKey];

        if (!editor) {
            return;
        }

        const roleSelect = ({
            heading: roleHeading,
            body: roleBody,
            monospace: roleMonospace,
        })[roleKey];
        const heading = editor.querySelector(`[data-role-axis-heading="${roleKey}"]`);
        const fields = editor.querySelector(`[data-role-axis-fields="${roleKey}"]`);
        const summary = editor.querySelector(`[data-role-axis-summary="${roleKey}"]`);
        const familyName = roleSelect ? String(roleSelect.value || '').trim() : '';
        const state = normalizeRoleState(overrideState || currentDraftRoleState());
        const deliveryFormat = deliveryFormatForFamily(familyName);
        const definitions = variableFontsEnabled ? roleAxisDefinitionsForFamily(familyName) : {};
        const currentValues = state[`${roleKey}Axes`] && Object.keys(state[`${roleKey}Axes`]).length
            ? state[`${roleKey}Axes`]
            : defaultRoleAxesForFamily(familyName);

        if (!fields || !familyName || deliveryFormat !== 'variable' || !Object.keys(definitions).length) {
            editor.hidden = true;

            if (fields) {
                fields.innerHTML = '';
            }

            return;
        }

        editor.hidden = false;
        fields.innerHTML = '';
        const axisEntries = Object.entries(definitions);
        const singleAxis = axisEntries.length === 1;

        if (heading) {
            heading.textContent = singleAxis
                ? formatMessage(
                    getString('singleAxisHeading', 'Variable Axes (%1$s - %2$s)'),
                    [axisEntries[0][1].min, axisEntries[0][1].max]
                )
                : getString('variableAxesHeading', 'Variable Axes');
        }

        axisEntries.forEach(([tag, definition]) => {
            const normalizedTag = normalizeAxisTag(tag);

            if (!normalizedTag) {
                return;
            }

            const field = document.createElement('label');
            field.className = 'tasty-fonts-stack-field tasty-fonts-role-axis-field';

            const title = document.createElement('span');
            title.className = singleAxis ? 'screen-reader-text' : 'tasty-fonts-field-label-text';
            title.textContent = `${cssAxisTag(normalizedTag)} (${definition.min} - ${definition.max})`;

            const input = document.createElement('input');
            input.type = 'number';
            input.step = 'any';
            input.name = `tasty_fonts_${roleKey}_axes[${normalizedTag}]`;
            input.value = currentValues[normalizedTag] || normalizeAxisValue(definition.default) || '';
            input.min = normalizeAxisValue(definition.min) || '';
            input.max = normalizeAxisValue(definition.max) || '';
            input.setAttribute('data-role-axis-input', roleKey);
            input.setAttribute('data-axis-tag', normalizedTag);

            if (roleFormId) {
                input.setAttribute('form', roleFormId);
            }

            field.appendChild(title);
            field.appendChild(input);
            fields.appendChild(field);
        });

        if (summary) {
            summary.hidden = singleAxis;

            if (!singleAxis) {
                summary.textContent = formatMessage(
                    getString('roleAxisSummary', 'Available axes for %1$s. Leave a field empty to use the family default.'),
                    [familyName]
                );
            } else {
                summary.textContent = '';
            }
        }
    }

    function renderAllRoleAxisEditors(overrideState = null) {
        renderRoleAxisEditor('heading', overrideState);
        renderRoleAxisEditor('body', overrideState);

        if (monospaceRoleEnabled) {
            renderRoleAxisEditor('monospace', overrideState);
        }
    }

    function previewAxisFieldValues(roleKey) {
        const values = {};

        document.querySelectorAll(`[data-preview-axis-input="${roleKey}"]`).forEach((input) => {
            const tag = normalizeAxisTag(input.getAttribute('data-axis-tag') || '');
            const value = normalizeAxisValue(input.value);

            if (!tag || !value) {
                return;
            }

            values[tag] = value;
        });

        return normalizeAxisSettings(values);
    }

    function renderPreviewWeightEditor(roleKey, overrideState = null) {
        const editor = previewWeightEditors[roleKey];
        const select = previewWeightSelects[roleKey];
        const clearButton = select ? select.closest('.tasty-fonts-select-field')?.querySelector('[data-clear-select-button]') : null;

        if (!editor || !select) {
            return;
        }

        const familySelect = previewRoleSelects[roleKey];
        const state = normalizeRoleState(overrideState || currentPreviewRoleState());
        const familyName = String(state[roleKey] || (familySelect ? familySelect.value : '') || '').trim();
        const deliveryFormat = deliveryFormatForFamily(familyName);
        const options = roleWeightOptionsForFamily(familyName);
        const shouldShow = !!familyName && deliveryFormat === 'static' && options.length > 1;
        const currentValue = state[`${roleKey}Weight`] || '';

        if (!shouldShow) {
            editor.hidden = true;
            select.innerHTML = '';

            if (clearButton) {
                syncClearableSelectButton(clearButton);
            }

            return;
        }

        editor.hidden = false;
        select.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = formatMessage(
            getString('roleWeightDefault', 'Use Role Default (%1$s)'),
            [defaultRoleWeight(roleKey)]
        );
        select.appendChild(defaultOption);

        const validValues = new Set(['']);

        options.forEach((option) => {
            const value = normalizeRoleWeightValue(option.value);

            if (!value) {
                return;
            }

            const element = document.createElement('option');
            element.value = value;
            element.textContent = String(option.label || value);
            select.appendChild(element);
            validValues.add(value);
        });

        select.value = validValues.has(currentValue) ? currentValue : '';

        if (clearButton) {
            syncClearableSelectButton(clearButton);
        }
    }

    function renderAllPreviewWeightEditors(overrideState = null) {
        renderPreviewWeightEditor('heading', overrideState);
        renderPreviewWeightEditor('body', overrideState);

        if (monospaceRoleEnabled) {
            renderPreviewWeightEditor('monospace', overrideState);
        }
    }

    function renderPreviewAxisEditor(roleKey, overrideState = null) {
        const editor = previewAxisEditors[roleKey];

        if (!editor) {
            return;
        }

        const familySelect = previewRoleSelects[roleKey];
        const heading = editor.querySelector(`[data-preview-axis-heading="${roleKey}"]`);
        const fields = editor.querySelector(`[data-preview-axis-fields="${roleKey}"]`);
        const state = normalizeRoleState(overrideState || currentPreviewRoleState());
        const familyName = String(state[roleKey] || (familySelect ? familySelect.value : '') || '').trim();
        const deliveryFormat = deliveryFormatForFamily(familyName);
        const definitions = variableFontsEnabled ? roleAxisDefinitionsForFamily(familyName) : {};
        const currentValues = state[`${roleKey}Axes`] && Object.keys(state[`${roleKey}Axes`]).length
            ? state[`${roleKey}Axes`]
            : defaultRoleAxesForFamily(familyName);

        if (!fields || !familyName || deliveryFormat !== 'variable' || !Object.keys(definitions).length) {
            editor.hidden = true;

            if (fields) {
                fields.innerHTML = '';
            }

            return;
        }

        editor.hidden = false;
        fields.innerHTML = '';
        const axisEntries = Object.entries(definitions);
        const singleAxis = axisEntries.length === 1;

        if (heading) {
            heading.textContent = singleAxis
                ? formatMessage(
                    getString('singleAxisHeading', 'Variable Axes (%1$s - %2$s)'),
                    [axisEntries[0][1].min, axisEntries[0][1].max]
                )
                : getString('variableAxesHeading', 'Variable Axes');
        }

        axisEntries.forEach(([tag, definition]) => {
            const normalizedTag = normalizeAxisTag(tag);

            if (!normalizedTag) {
                return;
            }

            const field = document.createElement('label');
            field.className = 'tasty-fonts-stack-field tasty-fonts-role-axis-field';

            const title = document.createElement('span');
            title.className = singleAxis ? 'screen-reader-text' : 'tasty-fonts-field-label-text';
            title.textContent = `${cssAxisTag(normalizedTag)} (${definition.min} - ${definition.max})`;

            const input = document.createElement('input');
            input.type = 'number';
            input.step = 'any';
            input.value = currentValues[normalizedTag] || normalizeAxisValue(definition.default) || '';
            input.min = normalizeAxisValue(definition.min) || '';
            input.max = normalizeAxisValue(definition.max) || '';
            input.setAttribute('data-preview-axis-input', roleKey);
            input.setAttribute('data-axis-tag', normalizedTag);

            field.appendChild(title);
            field.appendChild(input);
            fields.appendChild(field);
        });

    }

    function renderAllPreviewAxisEditors(overrideState = null) {
        renderPreviewAxisEditor('heading', overrideState);
        renderPreviewAxisEditor('body', overrideState);

        if (monospaceRoleEnabled) {
            renderPreviewAxisEditor('monospace', overrideState);
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
            syncPreviewActionButtonStates();
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

        const tooltipLayer = getHelpTooltipLayer();

        if (activeHelpButton === roleDeploymentPill && tooltipLayer && !tooltipLayer.hidden) {
            tooltipLayer.textContent = tooltip;
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

    function currentGoogleFormatMode() {
        return String(selectedGoogleFormatMode || 'static').trim() || 'static';
    }

    function currentBunnyFormatMode() {
        return String(selectedBunnyFormatMode || 'static').trim() || 'static';
    }

    function normalizeFamilyKey(value) {
        return String(value || '').trim().toLowerCase();
    }

    function familySlug(value) {
        return normalizeFamilyKey(slugify(String(value || '')));
    }

    function familyAxisMap(entry) {
        const axes = entry && typeof entry === 'object' ? entry.axes : null;

        return axes && typeof axes === 'object' ? axes : {};
    }

    function familyAxisTags(entry) {
        const tags = entry && typeof entry === 'object' && Array.isArray(entry.axis_tags) ? entry.axis_tags : [];

        return Array.from(
            new Set(
                tags
                    .map((tag) => String(tag || '').trim().toUpperCase())
                    .filter((tag) => /^[A-Z0-9]{4}$/.test(tag))
            )
        );
    }

    function familyHasVariableMetadata(entry, provider = 'library') {
        const descriptor = describeFontType(entry, provider);

        return !!descriptor.hasVariable;
    }

    function familyHasStaticMetadata(entry, provider = 'library') {
        const descriptor = describeFontType(entry, provider);

        return descriptor.hasStatic !== false;
    }

    function familyFormatMap(entry, provider = 'library') {
        const formats = entry && typeof entry === 'object' && entry.formats && typeof entry.formats === 'object'
            ? entry.formats
            : {};
        const normalized = {};

        ['static', 'variable'].forEach((mode) => {
            const format = formats[mode];

            if (!format || typeof format !== 'object') {
                return;
            }

            normalized[mode] = {
                label: mode === 'variable'
                    ? getString('variableBadge', 'Variable')
                    : String(format.label || getString('staticBadge', 'Static')).trim(),
                available: format.available !== false,
                source_only: !!format.source_only,
            };
        });

        if (provider === 'bunny' && normalized.variable) {
            delete normalized.variable;
        }

        if (Object.keys(normalized).length > 0) {
            if (provider === 'bunny' && !normalized.static) {
                normalized.static = {
                    label: getString('staticBadge', 'Static'),
                    available: true,
                    source_only: false,
                };
            }

            return normalized;
        }

        const descriptor = describeFontType(entry, provider);

        if (descriptor.hasStatic !== false) {
            normalized.static = {
                label: getString('staticBadge', 'Static'),
                available: true,
                source_only: false,
            };
        }

        if (descriptor.hasVariable && provider !== 'bunny') {
            normalized.variable = {
                label: getString('variableBadge', 'Variable'),
                available: !descriptor.isSourceOnly,
                source_only: !!descriptor.isSourceOnly,
            };
        }

        if (provider === 'bunny' && !normalized.static) {
            normalized.static = {
                label: getString('staticBadge', 'Static'),
                available: true,
                source_only: false,
            };
        }

        return normalized;
    }

    function familyFormatInfo(entry, mode, provider = 'library') {
        const normalizedMode = String(mode || '').trim().toLowerCase();

        if (normalizedMode !== 'static' && normalizedMode !== 'variable') {
            return null;
        }

        const formats = familyFormatMap(entry, provider);
        const format = formats[normalizedMode];

        return format && typeof format === 'object' ? format : null;
    }

    function familyVisibleFormats(entry, provider = 'library') {
        const formats = familyFormatMap(entry, provider);

        return ['static', 'variable'].filter((mode) => {
            if (mode === 'variable' && !variableFontsEnabled) {
                return false;
            }

            return !!formats[mode];
        });
    }

    function familySelectableFormats(entry, provider = 'library') {
        return familyVisibleFormats(entry, provider).filter((mode) => {
            const format = familyFormatInfo(entry, mode, provider);

            return !!(format && format.available !== false);
        });
    }

    function familySupportsFormat(entry, mode, provider = 'library', requireSelectable = false) {
        const format = familyFormatInfo(entry, mode, provider);

        if (!format) {
            return false;
        }

        if (mode === 'variable' && !variableFontsEnabled) {
            return false;
        }

        if (!requireSelectable) {
            return true;
        }

        return format.available !== false;
    }

    function resolveImportFormatMode(entry, provider, currentMode = 'static') {
        if (!variableFontsEnabled) {
            return 'static';
        }

        const requestedMode = String(currentMode || 'static').trim().toLowerCase();
        const selectableModes = familySelectableFormats(entry, provider);

        if (selectableModes.includes(requestedMode)) {
            return requestedMode;
        }

        if (selectableModes.includes('static')) {
            return 'static';
        }

        return selectableModes[0] || 'static';
    }

    function fontTypeDescriptor(entry, provider = 'library') {
        const descriptor = describeFontType(entry, provider);

        if (descriptor.hasStatic && descriptor.hasVariable) {
            return {
                type: 'static-variable',
                label: getString('staticVariableBadge', 'Static + Variable'),
                badgeClass: 'is-role',
                hasVariable: true,
                hasStatic: true,
                isSourceOnly: !!descriptor.isSourceOnly,
            };
        }

        if (!descriptor.hasVariable) {
            return {
                type: 'static',
                label: getString('staticBadge', 'Static'),
                badgeClass: '',
                hasVariable: false,
                hasStatic: true,
                isSourceOnly: false,
            };
        }

        if (descriptor.isSourceOnly) {
            return {
                type: 'variable',
                label: getString('variableSourceBadge', 'Variable Source'),
                badgeClass: 'is-warning',
                hasVariable: true,
                hasStatic: !!descriptor.hasStatic,
                isSourceOnly: true,
            };
        }

        return {
            type: 'variable',
            label: getString('variableBadge', 'Variable'),
            badgeClass: 'is-role',
            hasVariable: true,
            hasStatic: !!descriptor.hasStatic,
            isSourceOnly: false,
        };
    }

    function axisSummaryLabel(tag, definition) {
        const min = String(definition && definition.min ? definition.min : '').trim();
        const max = String(definition && definition.max ? definition.max : '').trim();
        const fallbackValue = String(definition && definition.default ? definition.default : '').trim() || min || max;
        const value = min && max && min !== max ? `${min}..${max}` : fallbackValue;

        return value ? `${String(tag || '').toLowerCase()} ${value}` : String(tag || '').toLowerCase();
    }

    function familyAxisSummaryLabels(entry) {
        const labels = [];
        const seen = new Set();
        const axes = familyAxisMap(entry);

        Object.keys(axes)
            .sort()
            .forEach((tag) => {
                const label = axisSummaryLabel(tag, axes[tag]);

                if (!label) {
                    return;
                }

                labels.push(label);
                seen.add(String(tag || '').toUpperCase());
            });

        familyAxisTags(entry).forEach((tag) => {
            if (seen.has(tag)) {
                return;
            }

            labels.push(tag.toLowerCase());
        });

        return labels;
    }

    function familyVariableNote(provider) {
        if (provider === 'google') {
            return getString(
                'googleVariableImportNote',
                'Variable import keeps the upstream axis ranges and stores a variable font file when Google serves one.'
            );
        }

        return '';
    }

    function renderSelectedFamilyMeta(container, noteElement, family, provider) {
        if (container) {
            container.innerHTML = '';
            container.hidden = true;
        }

        if (noteElement) {
            noteElement.textContent = '';
            noteElement.hidden = true;
        }

        if (!container) {
            return;
        }

        familyVisibleFormats(family, provider).forEach((mode) => {
            const format = familyFormatInfo(family, mode, provider);

            if (!format) {
                return;
            }

            const badge = document.createElement('span');
            const badgeClass = mode === 'variable'
                ? (format.source_only ? 'is-warning' : 'is-role')
                : '';

            badge.className = `tasty-fonts-badge${badgeClass ? ` ${badgeClass}` : ''}`;
            badge.textContent = format.label;
            container.appendChild(badge);
        });

        if (variableFontsEnabled && familySupportsFormat(family, 'variable', provider)) {
            familyAxisSummaryLabels(family).forEach((label) => {
                const pill = document.createElement('span');

                pill.className = 'tasty-fonts-face-pill is-muted';
                pill.textContent = label;
                container.appendChild(pill);
            });
        }

        container.hidden = false;

        if (noteElement && variableFontsEnabled && familySupportsFormat(family, 'variable', provider)) {
            const note = familyVariableNote(provider);
            const showNote = (provider === 'google' && currentGoogleFormatMode() === 'variable')
                || (provider === 'library' && familyHasVariableMetadata(family, provider));

            if (note && showNote) {
                noteElement.textContent = note;
                noteElement.hidden = false;
            }
        }
    }

    function appendSearchResultBadges(head, item, inLibrary, provider) {
        if (!head) {
            return;
        }

        const badges = document.createElement('div');

        badges.className = 'tasty-fonts-badges tasty-fonts-badges--import tasty-fonts-search-card-badges';

        if (inLibrary) {
            const badge = document.createElement('span');

            badge.className = 'tasty-fonts-badge is-success';
            badge.textContent = getString('searchResultInLibrary', 'In Library');
            badges.appendChild(badge);
        }

        familyVisibleFormats(item, provider).forEach((mode) => {
            const format = familyFormatInfo(item, mode, provider);

            if (!format) {
                return;
            }

            const badge = document.createElement('span');
            const badgeClass = mode === 'variable'
                ? (format.source_only ? 'is-warning' : 'is-role')
                : '';

            badge.className = `tasty-fonts-badge${badgeClass ? ` ${badgeClass}` : ''}`;
            badge.textContent = format.label;
            badges.appendChild(badge);
        });

        if (badges.childElementCount > 0) {
            head.appendChild(badges);
        }
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
            selectedGoogleFormatMode = 'static';

            if (manualFamily) {
                manualFamily.value = family;
            }

            if (manualVariants) {
                manualVariants.value = seededVariants.join(',');
                delete manualVariants.dataset.explicitEmpty;
            }

            selectedSearchFamily = findGoogleFamilyMatch(family);
            syncGoogleFormatChoice(selectedSearchFamily);
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
        selectedBunnyFormatMode = 'static';

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
        syncBunnyFormatChoice(selectedBunnySearchFamily);
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
            .map((item) => canonicalizeVariantToken(item))
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
            .map((input) => serializeVariantTokenForManualField(input.value, currentGoogleFormatMode() === 'variable'))
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

        const selectedTokens = new Set(resolveGoogleManualVariantTokens().map((token) => token.toLowerCase()));

        syncingGoogleVariants = true;

        inputs.forEach((input) => {
            input.checked = selectedTokens.has(String(input.value).toLowerCase());
            syncVariantChipState(input);
        });
        syncingGoogleVariants = false;

        return true;
    }

    function bunnySelectedVariantTokens() {
        return resolveBunnyManualVariantTokens();
    }

    function syncBunnyManualVariantsInputFromChips() {
        if (!bunnyVariants) {
            return;
        }

        bunnyVariants.value = availableBunnyVariantInputs()
            .filter((input) => input.checked)
            .map((input) => serializeVariantTokenForManualField(input.value, currentBunnyFormatMode() === 'variable'))
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

        const selectedTokens = new Set(resolveBunnyManualVariantTokens().map((token) => token.toLowerCase()));

        syncingBunnyVariants = true;

        inputs.forEach((input) => {
            input.checked = selectedTokens.has(String(input.value).toLowerCase());
            syncVariantChipState(input);
        });
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
        const matchedFamily = activeGoogleFamilyForImport();
        const hasFamily = familyName !== '';
        const variants = hasFamily ? Array.from(new Set(selectedVariantTokens())) : [];
        const variantCount = variants.length;
        const isVariableMode = currentGoogleFormatMode() === 'variable' && familySupportsFormat(matchedFamily, 'variable', 'google', true);
        const fallback = googlePreviewFallback(matchedFamily ? matchedFamily.category : '');
        const previewText = googleSelectedPreviewText();
        const availableCount = matchedFamily && Array.isArray(matchedFamily.variants)
            ? googleDisplayVariants(matchedFamily.variants).length
            : 0;
        const estimatedKilobytes = variants.reduce((total, variant) => {
            return total + approximateVariantTransferSize(variant, matchedFamily ? matchedFamily.category : '');
        }, 0);

        syncGoogleFormatChoice(matchedFamily);
        syncGoogleImportTerminology(isVariableMode);
        updateSelectedFamilyLabel(familyName);
        renderSelectedFamilyMeta(selectedFamilyMeta, selectedFamilyNote, hasFamily ? matchedFamily : null, 'google');

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
                importSelectionSummary.textContent = selectionSummaryEmptyLabel(isVariableMode);
            } else if (availableCount > 0) {
                importSelectionSummary.textContent = formatMessage(
                    isVariableMode
                        ? getString('importSelectionSummaryAvailableVariable', '%1$d of %2$d Styles Selected')
                        : getString('importSelectionSummaryAvailable', '%1$d of %2$d Variants Selected'),
                    [variantCount, availableCount]
                );
            } else if (variantCount > 0) {
                importSelectionSummary.textContent = formatMessage(
                    variantCount === 1
                        ? (
                            isVariableMode
                                ? getString('styleCountSingle', '%d style')
                                : getString('variantCountSingle', '%d variant')
                        )
                        : (
                            isVariableMode
                                ? getString('styleCountMultiple', '%d styles')
                                : getString('variantCountMultiple', '%d variants')
                        ),
                    [variantCount]
                );
            } else {
                importSelectionSummary.textContent = selectionSummaryEmptyLabel(isVariableMode);
            }
        }

        variantQuickSelectButtons.forEach((button) => {
            button.disabled = !hasFamily || availableCount === 0;
        });

        updateGooglePreviewStylesheet(searchResults);
    }

    function updateBunnyImportSummary() {
        const familyName = currentBunnyImportFamily();
        const matchedFamily = activeBunnyFamilyForImport();
        const hasFamily = familyName !== '';
        const previewText = googleSelectedPreviewText();
        const variants = hasFamily ? Array.from(new Set(bunnySelectedVariantTokens())) : [];
        const isVariableMode = currentBunnyFormatMode() === 'variable' && familySupportsFormat(matchedFamily, 'variable', 'bunny', true);
        const effectiveVariants = variants.length > 0 ? variants : (hasFamily ? ['regular'] : []);
        const variantCount = variants.length;
        const availableCount = matchedFamily && Array.isArray(matchedFamily.variants)
            ? bunnyDisplayVariants(matchedFamily.variants).length
            : 0;
        const fallback = googlePreviewFallback(matchedFamily ? matchedFamily.category : '');
        const estimatedKilobytes = effectiveVariants.reduce((total, variant) => {
            return total + approximateVariantTransferSize(variant, matchedFamily ? matchedFamily.category : '');
        }, 0);

        syncBunnyFormatChoice(matchedFamily);
        syncBunnyImportTerminology(isVariableMode);

        if (bunnySelectedFamily) {
            bunnySelectedFamily.textContent = familyName || getString('bunnyImportFamilyEmpty', 'Choose a Bunny family or type one manually.');
        }

        renderSelectedFamilyMeta(bunnySelectedFamilyMeta, bunnySelectedFamilyNote, hasFamily ? matchedFamily : null, 'bunny');

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
                bunnyImportSelectionSummary.textContent = selectionSummaryEmptyLabel(isVariableMode);
            } else if (availableCount > 0) {
                bunnyImportSelectionSummary.textContent = formatMessage(
                    isVariableMode
                        ? getString('importSelectionSummaryAvailableVariable', '%1$d of %2$d Styles Selected')
                        : getString('importSelectionSummaryAvailable', '%1$d of %2$d Variants Selected'),
                    [variantCount, availableCount]
                );
            } else if (variantCount > 0) {
                bunnyImportSelectionSummary.textContent = formatMessage(
                    variantCount === 1
                        ? (
                            isVariableMode
                                ? getString('styleCountSingle', '%d style')
                                : getString('variantCountSingle', '%d variant')
                        )
                        : (
                            isVariableMode
                                ? getString('styleCountMultiple', '%d styles')
                                : getString('variantCountMultiple', '%d variants')
                        ),
                    [variantCount]
                );
            } else {
                bunnyImportSelectionSummary.textContent = selectionSummaryEmptyLabel(isVariableMode);
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

    function collapseRoleDisclosures() {
        const previewToggle = disclosureToggleByTargetId('tasty-fonts-role-preview-panel');
        const snippetsToggle = disclosureToggleByTargetId('tasty-fonts-role-snippets-panel');

        if (previewToggle) {
            setDisclosureState(previewToggle, false);
        }

        if (snippetsToggle) {
            setDisclosureState(snippetsToggle, false);
        }
    }

    function tabButtonsForGroup(group) {
        return tabButtonElements().filter((tab) => tab.getAttribute('data-tab-group') === group);
    }

    function tabPanelsForGroup(group) {
        return tabPanelElements().filter((panel) => panel.getAttribute('data-tab-group') === group);
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

        if (group === 'page' && key) {
            if (key !== 'roles') {
                collapseRoleDisclosures();
            }

            currentPage = key;

            const rootPage = document.querySelector('[data-current-page]');

            if (rootPage) {
                rootPage.setAttribute('data-current-page', key);
            }
        }

        if (group === 'settings') {
            syncSettingsSaveShell();
        }
    }

    function setDisclosureState(toggle, expanded) {
        const target = getDisclosureTarget(toggle);

        if (!toggle || !target) {
            return;
        }

        const toggleId = ensureElementId(toggle, 'tasty-fonts-disclosure-toggle');

        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        target.hidden = !expanded;
        target.setAttribute('aria-labelledby', toggleId);

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
        disclosureToggleElements().forEach((toggle) => {
            setDisclosureState(toggle, isDisclosureExpanded(toggle));
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

    function ensureDisclosureAnnouncementRegion() {
        if (disclosureAnnouncementRegion) {
            return disclosureAnnouncementRegion;
        }

        const root = document.querySelector('.tasty-fonts-admin') || document.body;

        disclosureAnnouncementRegion = document.createElement('div');
        disclosureAnnouncementRegion.className = 'screen-reader-text';
        disclosureAnnouncementRegion.setAttribute('aria-live', 'polite');
        disclosureAnnouncementRegion.setAttribute('aria-atomic', 'true');
        disclosureAnnouncementRegion.setAttribute('role', 'status');
        root.appendChild(disclosureAnnouncementRegion);

        return disclosureAnnouncementRegion;
    }

    function announceDisclosureExpansion(toggle) {
        if (!toggle) {
            return;
        }

        const region = ensureDisclosureAnnouncementRegion();
        const label = String(
            toggle.getAttribute('data-expanded-label')
            || toggle.getAttribute('aria-label')
            || toggle.textContent
            || ''
        ).trim();

        if (!label) {
            return;
        }

        const token = disclosureAnnouncementToken + 1;
        disclosureAnnouncementToken = token;
        region.textContent = '';

        window.setTimeout(() => {
            if (disclosureAnnouncementToken !== token) {
                return;
            }

            region.textContent = formatMessage(
                getString('disclosureExpanded', '%s expanded.'),
                [label]
            );
        }, 20);
    }

    function firstFocusableIn(container) {
        if (!container) {
            return null;
        }

        return container.querySelector([
            'input:not([type="hidden"]):not([disabled])',
            'select:not([disabled])',
            'textarea:not([disabled])',
            'button:not([disabled])',
            '[href]',
            '[tabindex]:not([tabindex="-1"])'
        ].join(', '));
    }

    function focusDisclosureTarget(targetId) {
        if (!targetId) {
            return;
        }

        const panel = document.getElementById(targetId);

        if (!panel || panel.hidden || panel.closest('[hidden]')) {
            return;
        }

        window.setTimeout(() => {
            const focusTarget = firstFocusableIn(panel);

            if (focusTarget && typeof focusTarget.focus === 'function') {
                focusTarget.focus({ preventScroll: true });
                return;
            }

            if (!panel.hasAttribute('tabindex')) {
                panel.setAttribute('tabindex', '-1');
            }

            if (typeof panel.focus === 'function') {
                panel.focus({ preventScroll: true });
            }
        }, 0);
    }

    // Toasts and tooltips
    function dismissToast(toast) {
        if (!toast || !toast.parentNode) {
            return;
        }

        const dismissCallback = typeof toast._dismissCallback === 'function' ? toast._dismissCallback : null;

        toast._dismissCallback = null;

        if (toast._dismissTimer) {
            window.clearTimeout(toast._dismissTimer);
            toast._dismissTimer = 0;
        }

        if (toast.classList.contains('is-leaving')) {
            return;
        }

        toast.classList.add('is-leaving');

        window.setTimeout(() => {
            if (dismissCallback) {
                dismissCallback();
            }

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

    function scheduleToastDismiss(toast, tone, duration) {
        if (!toast) {
            return;
        }

        if (toast._dismissTimer) {
            window.clearTimeout(toast._dismissTimer);
        }

        const dismissAfter = typeof duration === 'number'
            ? duration
            : (tone === 'error' ? 6000 : 3200);

        if (dismissAfter <= 0) {
            toast._dismissTimer = 0;
            return;
        }

        toast._dismissTimer = window.setTimeout(() => {
            dismissToast(toast);
        }, dismissAfter);
    }

    function showToast(message, tone, options) {
        if (!message) {
            return;
        }

        const stack = ensureToastStack();
        const resolvedTone = tone === 'error' ? 'error' : 'success';
        const toastOptions = options && typeof options === 'object' ? options : {};
        const actionItems = Array.isArray(toastOptions.actions)
            ? toastOptions.actions.filter((action) => action && action.label)
            : [];
        const shouldDeduplicate = actionItems.length === 0 && typeof toastOptions.onDismiss !== 'function';

        if (shouldDeduplicate) {
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
                scheduleToastDismiss(existingToast, resolvedTone, toastOptions.duration);
                return existingToast;
            }
        }

        const toast = document.createElement('div');
        const body = document.createElement('div');
        const dismiss = document.createElement('button');
        const text = document.createElement('div');

        toast.className = `tasty-fonts-toast is-${resolvedTone}`;
        toast.setAttribute('data-toast', '');
        toast.setAttribute('data-toast-tone', resolvedTone);
        toast.setAttribute('role', resolvedTone === 'error' ? 'alert' : 'status');

        if (actionItems.length > 0) {
            toast.classList.add('is-actionable');

            if (resolvedTone === 'error') {
                toast.classList.add('is-confirmation');
            }
        }

        body.className = 'tasty-fonts-toast-body';
        text.className = 'tasty-fonts-toast-message';
        text.textContent = message;
        body.appendChild(text);

        if (actionItems.length > 0) {
            const actions = document.createElement('div');

            actions.className = 'tasty-fonts-toast-actions';

            actionItems.forEach((action) => {
                const button = document.createElement('button');

                button.type = 'button';
                button.className = 'tasty-fonts-toast-action';

                if (action.variant) {
                    button.classList.add(`is-${action.variant}`);
                }

                button.textContent = action.label;
                button.addEventListener('click', (event) => {
                    event.preventDefault();

                    if (typeof action.onClick === 'function') {
                        action.onClick({
                            toast,
                            dismiss() {
                                dismissToast(toast);
                            },
                            close() {
                                toast._dismissCallback = null;
                                dismissToast(toast);
                            },
                        });
                    }
                });

                actions.appendChild(button);
            });

            body.appendChild(actions);
        }

        dismiss.type = 'button';
        dismiss.className = 'tasty-fonts-toast-dismiss';
        dismiss.setAttribute('data-toast-dismiss', '');
        dismiss.setAttribute('aria-label', getString('dismissNotification', 'Dismiss notification'));
        dismiss.innerHTML = '<span aria-hidden="true">&times;</span>';

        if (typeof toastOptions.onDismiss === 'function') {
            toast._dismissCallback = toastOptions.onDismiss;
        }

        toast.appendChild(body);
        toast.appendChild(dismiss);
        stack.appendChild(toast);

        scheduleToastDismiss(toast, resolvedTone, toastOptions.duration);
        return toast;
    }

    function restoreButtonAttribute(button, attribute, value) {
        if (!button) {
            return;
        }

        if (value) {
            button.setAttribute(attribute, value);
            return;
        }

        button.removeAttribute(attribute);
    }

    function readButtonLabel(button) {
        if (!button) {
            return '';
        }

        const srText = button.querySelector('.screen-reader-text');

        if (button.classList.contains('tasty-fonts-font-action-button--icon') && srText) {
            return String(srText.textContent || '').trim();
        }

        return String(button.textContent || '').trim();
    }

    function writeButtonLabel(button, label) {
        if (!button || !label) {
            return;
        }

        const srText = button.querySelector('.screen-reader-text');

        if (button.classList.contains('tasty-fonts-font-action-button--icon') && srText) {
            srText.textContent = label;
        } else {
            button.textContent = label;
        }

        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    }

    function resetDestructiveButton(button) {
        if (!button) {
            return;
        }

        if (button._tastyConfirmTimer) {
            window.clearTimeout(button._tastyConfirmTimer);
            button._tastyConfirmTimer = 0;
        }

        if (button.dataset.confirmOriginalLabel) {
            writeButtonLabel(button, button.dataset.confirmOriginalLabel);
        }

        restoreButtonAttribute(button, 'aria-label', button.dataset.confirmOriginalAriaLabel || '');
        restoreButtonAttribute(button, 'title', button.dataset.confirmOriginalTitle || '');

        delete button.dataset.confirmArmed;
        delete button.dataset.confirmOriginalLabel;
        delete button.dataset.confirmOriginalAriaLabel;
        delete button.dataset.confirmOriginalTitle;
        button.classList.remove('is-awaiting-confirmation');
    }

    function armDestructiveButton(button, confirmMessage) {
        if (!button) {
            return false;
        }

        if (button.dataset.confirmArmed === '1') {
            resetDestructiveButton(button);
            return true;
        }

        const originalLabel = readButtonLabel(button) || getString('confirmAction', 'Confirm');

        button.dataset.confirmArmed = '1';
        button.dataset.confirmOriginalLabel = originalLabel;
        button.dataset.confirmOriginalAriaLabel = button.getAttribute('aria-label') || '';
        button.dataset.confirmOriginalTitle = button.getAttribute('title') || '';

        writeButtonLabel(
            button,
            getString('confirmActionShort', 'Confirm?')
        );

        button.classList.add('is-awaiting-confirmation');
        button._tastyConfirmTimer = window.setTimeout(() => {
            resetDestructiveButton(button);
        }, 6000);

        if (confirmMessage) {
            showToast(confirmMessage, 'error', {
                duration: 6000,
                onDismiss() {
                    resetDestructiveButton(button);
                },
                actions: [
                    {
                        label: getString('continueAction', 'Continue'),
                        variant: 'primary',
                        onClick({ close }) {
                            close();
                            button.click();
                        },
                    },
                    {
                        label: getString('cancelAction', 'Cancel'),
                        variant: 'secondary',
                        onClick({ dismiss }) {
                            dismiss();
                        },
                    },
                ],
            });
        }

        return false;
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
        const tooltipLayer = getHelpTooltipLayer();

        if (!button || !tooltipLayer || tooltipLayer.hidden) {
            return;
        }

        const margin = 12;
        const gap = 10;
        const triggerRect = button.getBoundingClientRect();

        tooltipLayer.style.left = '0px';
        tooltipLayer.style.top = '0px';
        tooltipLayer.style.maxWidth = `${Math.max(220, Math.min(320, window.innerWidth - (margin * 2)))}px`;

        const tooltipRect = tooltipLayer.getBoundingClientRect();
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

        tooltipLayer.style.left = `${left}px`;
        tooltipLayer.style.top = `${top}px`;
        tooltipLayer.classList.toggle('is-above', isAbove);
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
        const tooltipLayer = getHelpTooltipLayer();

        if (!button || !tooltipLayer) {
            return;
        }

        rememberHelpTooltipDescription(button);
        button.setAttribute('aria-describedby', tooltipLayer.id);
    }

    function hideHelpTooltip() {
        const tooltipLayer = getHelpTooltipLayer();

        if (!tooltipLayer) {
            return;
        }

        if (activeHelpButton) {
            activeHelpButton.setAttribute('aria-expanded', 'false');
            restoreHelpTooltipDescription(activeHelpButton);
        }

        tooltipLayer.hidden = true;
        tooltipLayer.textContent = '';
        tooltipLayer.classList.remove('is-above');
        activeHelpButton = null;
    }

    function prepareHelpTooltipTrigger(button) {
        if (!button) {
            return;
        }

        button.setAttribute('aria-expanded', 'false');
        rememberHelpTooltipDescription(button);
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
        prepareHelpTooltipTrigger(button);
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
            prepareHelpTooltipTrigger(button);
        });
    }

    function showHelpTooltip(button) {
        const tooltipLayer = getHelpTooltipLayer();

        if (!button || !tooltipLayer) {
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
        tooltipLayer.textContent = copy;
        tooltipLayer.hidden = false;
        positionHelpTooltip(button);
    }

    function initHelpTooltips() {
        const tooltipLayer = getHelpTooltipLayer();

        if (!tooltipLayer) {
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

        helpButtons.forEach((button) => prepareHelpTooltipTrigger(button));

        if (helpTooltipEventsBound) {
            return;
        }

        helpTooltipEventsBound = true;

        document.addEventListener('mouseover', (event) => {
            const button = event.target.closest('[data-help-tooltip]');

            if (!button) {
                return;
            }

            if (event.relatedTarget instanceof Node && button.contains(event.relatedTarget)) {
                return;
            }

            showHelpTooltip(button);
        });

        document.addEventListener('mouseout', (event) => {
            const button = event.target.closest('[data-help-tooltip]');

            if (!button) {
                return;
            }

            if (event.relatedTarget instanceof Node && button.contains(event.relatedTarget)) {
                return;
            }

            if (activeHelpButton === button && document.activeElement !== button) {
                hideHelpTooltip();
            }
        });

        document.addEventListener('focusin', (event) => {
            const button = event.target.closest('[data-help-tooltip]');

            if (button) {
                showHelpTooltip(button);
            }
        });

        document.addEventListener('focusout', (event) => {
            const button = event.target.closest('[data-help-tooltip]');

            if (!button) {
                return;
            }

            window.setTimeout(() => {
                if (activeHelpButton === button && document.activeElement !== button) {
                    hideHelpTooltip();
                }
            }, 0);
        });

        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-help-tooltip]')) {
                hideHelpTooltip();
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

        const announcement = resolveStatusAnnouncement(type);

        target.innerHTML = '';
        target.classList.remove('is-error', 'is-success', 'is-progress');
        target.setAttribute('role', announcement.role);
        target.setAttribute('aria-live', announcement.live);
        target.setAttribute('aria-atomic', 'true');

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
            headingWeight: getElementValue(roleWeightSelects.heading, ''),
            bodyWeight: getElementValue(roleWeightSelects.body, ''),
            monospaceWeight: monospaceRoleEnabled ? getElementValue(roleWeightSelects.monospace, '') : '',
            headingAxes: roleAxisFieldValues('heading'),
            bodyAxes: roleAxisFieldValues('body'),
            monospaceAxes: monospaceRoleEnabled ? roleAxisFieldValues('monospace') : {},
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
            headingWeight: getElementValue(roleWeightSelects.heading, ''),
            bodyWeight: getElementValue(roleWeightSelects.body, ''),
            monospaceWeight: monospaceRoleEnabled ? getElementValue(roleWeightSelects.monospace, '') : '',
            headingAxes: roleAxisFieldValues('heading'),
            bodyAxes: roleAxisFieldValues('body'),
            monospaceAxes: monospaceRoleEnabled ? roleAxisFieldValues('monospace') : {},
        });
    }

    function currentAppliedRoleState() {
        return previewBootstrap().appliedRoles;
    }

    if (roleForm) {
        renderAllRoleWeightEditors(previewBootstrap().roles);
        renderAllRoleAxisEditors(previewBootstrap().roles);
    }

    let initialDraftRoleState = roleForm ? currentDraftRoleState() : normalizeRoleState({});

    function resolveAssignedRoleState(roleKey, family, currentState = {}, preserveStates = []) {
        if (typeof adminContracts.resolveAssignedRoleState === 'function') {
            return adminContracts.resolveAssignedRoleState(roleKey, family, currentState, {
                monospaceRoleEnabled,
                variableFontsEnabled,
                roleFamilyCatalog,
                preserveStates,
            });
        }

        const normalizedRoleKey = String(roleKey || '').trim();
        const nextFamily = String(family || '').trim();
        const nextState = normalizeRoleState({
            ...currentState,
            [normalizedRoleKey]: nextFamily,
            [`${normalizedRoleKey}Weight`]: '',
            [`${normalizedRoleKey}Axes`]: {},
        });
        const matchingState = preserveStates
            .map((state) => normalizeRoleState(state))
            .find((state) => String(state[normalizedRoleKey] || '').trim() === nextFamily);

        if (!matchingState) {
            return nextState;
        }

        nextState[`${normalizedRoleKey}Weight`] = String(matchingState[`${normalizedRoleKey}Weight`] || '').trim();
        nextState[`${normalizedRoleKey}Axes`] = variableFontsEnabled ? (matchingState[`${normalizedRoleKey}Axes`] || {}) : {};

        return nextState;
    }

    function roleStatesMatch(left = {}, right = {}) {
        if (typeof adminContracts.roleStatesMatch === 'function') {
            return adminContracts.roleStatesMatch(left, right, {
                monospaceRoleEnabled,
                variableFontsEnabled,
                roleFamilyCatalog,
            });
        }

        const leftState = normalizeRoleState(left);
        const rightState = normalizeRoleState(right);
        const keys = monospaceRoleEnabled
            ? ['heading', 'body', 'monospace', 'headingFallback', 'bodyFallback', 'monospaceFallback', 'headingWeight', 'bodyWeight', 'monospaceWeight', 'headingAxes', 'bodyAxes', 'monospaceAxes']
            : ['heading', 'body', 'headingFallback', 'bodyFallback', 'headingWeight', 'bodyWeight', 'headingAxes', 'bodyAxes'];

        return keys.every((key) => {
            if (key.endsWith('Axes')) {
                return JSON.stringify(leftState[key] || {}) === JSON.stringify(rightState[key] || {});
            }

            return leftState[key] === rightState[key];
        });
    }

    function syncDisabledRoleActionHelp(target, disabled, copy) {
        if (!target) {
            return;
        }

        const nextCopy = disabled ? copy : '';

        target.classList.toggle('has-disabled-reason', !!nextCopy);
        target.tabIndex = disabled && !trainingWheelsOff ? 0 : -1;
        if (nextCopy) {
            target.setAttribute('aria-label', nextCopy);
        } else {
            target.removeAttribute('aria-label');
        }
        setPassiveHelpTooltip(target, nextCopy);

        if (activeHelpButton === target) {
            if (!nextCopy || trainingWheelsOff) {
                hideHelpTooltip();
            } else {
                const tooltipLayer = getHelpTooltipLayer();

                if (!tooltipLayer || tooltipLayer.hidden) {
                    return;
                }

                tooltipLayer.textContent = nextCopy;
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

    function currentPreviewRoleState() {
        return normalizeRoleState(previewRoleState || currentDraftRoleState());
    }

    function syncPreviewActionButtonStates() {
        const previewState = currentPreviewRoleState();
        const previewDraftChanged = !roleStatesMatch(previewState, currentDraftRoleState());
        const previewHasPendingLiveChanges = !!config.applyEverywhere && !roleStatesMatch(previewState, currentAppliedRoleState());

        if (previewSaveDraftButton) {
            previewSaveDraftButton.setAttribute('aria-disabled', previewDraftChanged ? 'false' : 'true');
            previewSaveDraftButton.disabled = roleDraftSaveInFlight || !previewDraftChanged;
        }

        if (previewApplyLiveButton) {
            previewApplyLiveButton.classList.toggle('button-primary', previewHasPendingLiveChanges);
            previewApplyLiveButton.classList.toggle('is-pending-live-change', previewHasPendingLiveChanges);
            previewApplyLiveButton.setAttribute('aria-disabled', previewHasPendingLiveChanges ? 'false' : 'true');
            previewApplyLiveButton.disabled = roleDraftSaveInFlight || !previewHasPendingLiveChanges;
        }
    }

    function currentPreviewData() {
        return buildRoleDataFromValues(currentPreviewRoleState());
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
        const headingPreviewWeight = data.headingPreviewWeight || (data.applyRoleWeights ? data.headingResolvedWeight : '');
        const bodyPreviewWeight = data.bodyPreviewWeight || (data.applyRoleWeights ? data.bodyResolvedWeight : '');
        const monospacePreviewWeight = data.monospacePreviewWeight || (data.applyRoleWeights ? data.monospaceResolvedWeight : '');

        if (data.headingSlug) {
            variableLines.push(`  --font-${data.headingSlug}: ${data.headingStack};`);
            variableLines.push(`  --font-heading: var(--font-${data.headingSlug});`);
        } else {
            variableLines.push(`  --font-heading: ${data.headingStack};`);
        }

        variableLines.push(`  --font-heading-settings: ${data.headingSettings};`);
        Object.entries(data.headingAxes || {}).forEach(([tag, value]) => {
            variableLines.push(`  --font-heading-axis-${cssAxisTag(tag)}: ${value};`);
        });

        if (data.bodySlug) {
            variableLines.push(`  --font-${data.bodySlug}: ${data.bodyStack};`);
            variableLines.push(`  --font-body: var(--font-${data.bodySlug});`);
        } else {
            variableLines.push(`  --font-body: ${data.bodyStack};`);
        }

        variableLines.push(`  --font-body-settings: ${data.bodySettings};`);
        Object.entries(data.bodyAxes || {}).forEach(([tag, value]) => {
            variableLines.push(`  --font-body-axis-${cssAxisTag(tag)}: ${value};`);
        });

        if (data.includeMonospace) {
            if (data.monospaceSlug) {
                variableLines.push(`  --font-${data.monospaceSlug}: ${data.monospaceStack};`);
                variableLines.push(`  --font-monospace: var(--font-${data.monospaceSlug});`);
            } else {
                variableLines.push(`  --font-monospace: ${data.monospaceStack};`);
            }

            variableLines.push(`  --font-monospace-settings: ${data.monospaceSettings};`);
            Object.entries(data.monospaceAxes || {}).forEach(([tag, value]) => {
                variableLines.push(`  --font-monospace-axis-${cssAxisTag(tag)}: ${value};`);
            });
        }

        lines.push(...variableLines);
        lines.push('}', '');
        lines.push('body {');
        lines.push('  font-family: var(--font-body);');
        lines.push('  font-variation-settings: var(--font-body-settings);');
        if (bodyPreviewWeight) {
            lines.push(`  font-weight: ${bodyPreviewWeight};`);
        }
        lines.push('}', '');
        lines.push('h1, h2, h3, h4, h5, h6 {');
        lines.push('  font-family: var(--font-heading);');
        lines.push('  font-variation-settings: var(--font-heading-settings);');
        if (headingPreviewWeight) {
            lines.push(`  font-weight: ${headingPreviewWeight};`);
        }
        lines.push('}');

        if (data.includeMonospace) {
            lines.push('');
            lines.push('code, pre, kbd, samp {');
            lines.push('  font-family: var(--font-monospace);');
            lines.push('  font-variation-settings: var(--font-monospace-settings);');
            if (monospacePreviewWeight) {
                lines.push(`  font-weight: ${monospacePreviewWeight};`);
            }
            lines.push('}');
        }

        return lines.join('\n');
    }

    function buildOutputVariableLines(data) {
        if (!data.headingSlug || !data.bodySlug) {
            return [];
        }

        const variableLines = [
            `--font-${data.headingSlug}: ${data.headingStack};`,
            `--font-${data.bodySlug}: ${data.bodyStack};`,
            `--font-heading: var(--font-${data.headingSlug});`,
            `--font-body: var(--font-${data.bodySlug});`,
            `--font-heading-settings: ${data.headingSettings};`,
            `--font-body-settings: ${data.bodySettings};`,
        ];

        Object.entries(data.headingAxes || {}).forEach(([tag, value]) => {
            variableLines.push(`--font-heading-axis-${cssAxisTag(tag)}: ${value};`);
        });

        Object.entries(data.bodyAxes || {}).forEach(([tag, value]) => {
            variableLines.push(`--font-body-axis-${cssAxisTag(tag)}: ${value};`);
        });

        if (data.includeMonospace) {
            if (data.monospaceSlug) {
                variableLines.push(`--font-${data.monospaceSlug}: ${data.monospaceStack};`);
                variableLines.push(`--font-monospace: var(--font-${data.monospaceSlug});`);
            } else {
                variableLines.push(`--font-monospace: ${data.monospaceStack};`);
            }

            variableLines.push(`--font-monospace-settings: ${data.monospaceSettings};`);
            Object.entries(data.monospaceAxes || {}).forEach(([tag, value]) => {
                variableLines.push(`--font-monospace-axis-${cssAxisTag(tag)}: ${value};`);
            });
        }

        return variableLines;
    }

    function minimalOutputModeEnabled() {
        return !!(outputMinimalPresetInput && outputMinimalPresetInput.value === '1');
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

        Object.entries(previewWeightSelects).forEach(([roleKey, element]) => {
            if (!element || element.options.length === 0) {
                return;
            }

            const currentValue = state[`${roleKey}Weight`] || '';
            const validValues = new Set(Array.from(element.options).map((option) => String(option.value || '')));

            element.value = validValues.has(currentValue) ? currentValue : '';
        });
    }

    function applyPreviewOutputs(sourceLabel = '') {
        const data = currentPreviewData();
        const headingPreviewWeight = data.headingPreviewWeight || (data.applyRoleWeights ? data.headingResolvedWeight : '');
        const bodyPreviewWeight = data.bodyPreviewWeight || (data.applyRoleWeights ? data.bodyResolvedWeight : '');
        const monospacePreviewWeight = data.monospacePreviewWeight || (data.applyRoleWeights ? data.monospaceResolvedWeight : '');

        if (previewCanvas) {
            previewCanvas.style.setProperty('--tasty-preview-heading-stack', data.headingStack);
            previewCanvas.style.setProperty('--tasty-preview-body-stack', data.bodyStack);
            previewCanvas.style.setProperty('--tasty-preview-monospace-stack', data.monospaceStack);
            previewCanvas.style.setProperty('--tasty-preview-heading-settings', data.headingSettings);
            previewCanvas.style.setProperty('--tasty-preview-body-settings', data.bodySettings);
            previewCanvas.style.setProperty('--tasty-preview-monospace-settings', data.monospaceSettings);
        }

        roleHeadingPreviews.forEach((element) => {
            element.style.fontFamily = data.headingStack;
            element.style.fontVariationSettings = data.headingSettings;
            element.style.fontWeight = headingPreviewWeight;
        });

        roleBodyPreviews.forEach((element) => {
            element.style.fontFamily = data.bodyStack;
            element.style.fontVariationSettings = data.bodySettings;
            element.style.fontWeight = bodyPreviewWeight;
        });

        roleMonospacePreviews.forEach((element) => {
            element.style.fontFamily = data.monospaceStack;
            element.style.fontVariationSettings = data.monospaceSettings;
            element.style.fontWeight = monospacePreviewWeight;
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
        syncPreviewActionButtonStates();
    }

    function resetPreviewWorkspace() {
        const baseline = computePreviewBaseline();
        previewRoleState = normalizeRoleState(baseline.roles);
        previewDirty = false;
        previewFollowsDraft = baseline.source === 'draft';
        renderAllPreviewWeightEditors(previewRoleState);
        renderAllPreviewAxisEditors(previewRoleState);
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

        renderAllRoleWeightEditors(state);
        renderAllRoleAxisEditors(state);
    }

    function syncPreviewWorkspaceToDraft({ markDirty = false } = {}) {
        previewRoleState = currentDraftRoleState();
        previewFollowsDraft = true;
        previewDirty = markDirty;
        renderAllPreviewWeightEditors(previewRoleState);
        renderAllPreviewAxisEditors(previewRoleState);
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

        const setButtonLabel = (button, value) => {
            const label = button.querySelector('.tasty-fonts-role-box-copy-label');

            if (label) {
                label.textContent = value;
                return;
            }

            button.textContent = value;
        };

        headingRoleVariableCopies.forEach((button) => {
            const copyTitle = formatMessage(
                getString('headingVariableTitle', 'Heading font variable: %1$s. Resolved stack: %2$s'),
                [data.headingVariable, data.headingStack]
            );
            button.textContent = data.headingVariable;
            button.setAttribute('data-copy-text', data.headingVariable);
            button.setAttribute('title', copyTitle);
            button.setAttribute('aria-label', copyTitle);
        });

        bodyRoleVariableCopies.forEach((button) => {
            const copyTitle = formatMessage(
                getString('bodyVariableTitle', 'Body font variable: %1$s. Resolved stack: %2$s'),
                [data.bodyVariable, data.bodyStack]
            );
            button.textContent = data.bodyVariable;
            button.setAttribute('data-copy-text', data.bodyVariable);
            button.setAttribute('title', copyTitle);
            button.setAttribute('aria-label', copyTitle);
        });

        monospaceRoleVariableCopies.forEach((button) => {
            const copyTitle = formatMessage(
                getString('monospaceVariableTitle', 'Monospace font variable: %1$s. Resolved stack: %2$s'),
                [data.monospaceVariable, data.monospaceStack]
            );
            button.textContent = data.monospaceVariable;
            button.setAttribute('data-copy-text', data.monospaceVariable);
            button.setAttribute('title', copyTitle);
            button.setAttribute('aria-label', copyTitle);
        });

        headingFamilyVariableCopies.forEach((button) => {
            const copyTitle = data.heading
                ? formatMessage(
                    getString('headingFamilyVariableTitle', 'Heading family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s'),
                    [data.headingFamilyVariable, data.headingVariable, data.headingStack]
                )
                : formatMessage(
                    getString('headingFamilyFallbackTitle', 'Heading uses the fallback stack directly: %1$s. Role alias: %2$s'),
                    [data.headingStack, data.headingVariable]
                );
            setButtonLabel(button, data.headingFamilyVariable);
            button.setAttribute('data-copy-text', data.headingFamilyVariable);
            button.setAttribute('title', copyTitle);
            button.setAttribute('aria-label', copyTitle);
        });

        bodyFamilyVariableCopies.forEach((button) => {
            const copyTitle = data.body
                ? formatMessage(
                    getString('bodyFamilyVariableTitle', 'Body family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s'),
                    [data.bodyFamilyVariable, data.bodyVariable, data.bodyStack]
                )
                : formatMessage(
                    getString('bodyFamilyFallbackTitle', 'Body uses the fallback stack directly: %1$s. Role alias: %2$s'),
                    [data.bodyStack, data.bodyVariable]
                );
            setButtonLabel(button, data.bodyFamilyVariable);
            button.setAttribute('data-copy-text', data.bodyFamilyVariable);
            button.setAttribute('title', copyTitle);
            button.setAttribute('aria-label', copyTitle);
        });

        monospaceFamilyVariableCopies.forEach((button) => {
            const copyTitle = data.monospace
                ? formatMessage(
                    getString('monospaceFamilyVariableTitle', 'Monospace family variable: %1$s. Role alias: %2$s. Resolved stack: %3$s'),
                    [data.monospaceFamilyVariable, data.monospaceVariable, data.monospaceStack]
                )
                : formatMessage(
                    getString('monospaceFamilyFallbackTitle', 'Monospace uses the fallback stack directly: %1$s. Role alias: %2$s'),
                    [data.monospaceStack, data.monospaceVariable]
                );
            setButtonLabel(button, data.monospaceFamilyVariable);
            button.setAttribute('data-copy-text', data.monospaceFamilyVariable);
            button.setAttribute('title', copyTitle);
            button.setAttribute('aria-label', copyTitle);
        });

        syncRoleActionButtonStates();
        syncPreviewActionButtonStates();

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

            const tooltipLayer = getHelpTooltipLayer();

            if (activeHelpButton === button && tooltipLayer && !tooltipLayer.hidden) {
                tooltipLayer.textContent = nextHelp;
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
                const tooltipLayer = getHelpTooltipLayer();

                if (activeHelpButton === button && tooltipLayer && !tooltipLayer.hidden) {
                    tooltipLayer.textContent = blockedMessage;
                    positionHelpTooltip(button);
                }
                return;
            }

            button.removeAttribute('data-delete-blocked');

            const tooltipLayer = getHelpTooltipLayer();

            if (activeHelpButton === button && tooltipLayer && !tooltipLayer.hidden) {
                tooltipLayer.textContent = button.dataset.deleteReadyTitle || '';
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

        const variableLines = buildOutputVariableLines(data);
        const variableSnippet = variableLines.join('\n');

        if (outputVars) {
            outputVars.value = variableSnippet;
        }

        if (outputUsage) {
            if (!variableSnippet) {
                outputUsage.value = '';
            } else if (minimalOutputModeEnabled()) {
                outputUsage.value = [
                    ':root {',
                    ...variableLines.map((line) => `  ${line}`),
                    '}'
                ].join('\n');
            } else {
                const usageLines = [
                    ':root {',
                    ...variableLines.map((line) => `  ${line}`),
                    '}',
                    '',
                    'body {',
                    '  font-family: var(--font-body);',
                    '  font-variation-settings: var(--font-body-settings);',
                    ...(data.applyRoleWeights && data.bodyResolvedWeight ? [`  font-weight: ${data.bodyResolvedWeight};`] : []),
                    '}',
                    '',
                    'h1, h2, h3, h4, h5, h6 {',
                    '  font-family: var(--font-heading);',
                    '  font-variation-settings: var(--font-heading-settings);',
                    ...(data.applyRoleWeights && data.headingResolvedWeight ? [`  font-weight: ${data.headingResolvedWeight};`] : []),
                    '}'
                ];

                if (data.includeMonospace) {
                    usageLines.push(
                        '',
                        'code, pre {',
                        '  font-family: var(--font-monospace);',
                        '  font-variation-settings: var(--font-monospace-settings);',
                        ...(data.applyRoleWeights && data.monospaceResolvedWeight ? [`  font-weight: ${data.monospaceResolvedWeight};`] : []),
                        '}'
                    );
                }

                outputUsage.value = usageLines.join('\n');
            }
        }

        if (previewWorkspaceInitialized) {
            if (previewFollowsDraft) {
                previewRoleState = currentDraftRoleState();
                renderAllPreviewWeightEditors(previewRoleState);
                renderAllPreviewAxisEditors(previewRoleState);
                applyPreviewOutputs(getString('previewCurrentDraft', 'Current draft'));
                return;
            }

            applyPreviewOutputs(previewSourceLabel ? previewSourceLabel.textContent : '');
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

    function buildFallbackBatchSavedMessage(count) {
        return formatPluralMessage(
            'Saved fallback change for %d family.',
            'Saved fallback changes for %d families.',
            count,
            [count]
        );
    }

    function buildFallbackBatchPartialMessage(savedCount, failedCount) {
        return formatMessage(
            getString(
                'fallbackSavedPartial',
                'Saved fallback changes for %1$d families. %2$d still need attention.'
            ),
            [savedCount, failedCount]
        );
    }

    function setRoleFamilyCatalogFallback(family, fallback) {
        const familyName = String(family || '').trim();

        if (!familyName) {
            return;
        }

        if (!roleFamilyCatalog[familyName] || typeof roleFamilyCatalog[familyName] !== 'object') {
            roleFamilyCatalog[familyName] = {};
        }

        roleFamilyCatalog[familyName].fallback = fallback;
    }

    function dirtyFamilyFallbackSelectors(primarySelector = null) {
        const selectors = Array.from(document.querySelectorAll('.tasty-fonts-fallback-selector'));
        const dirtySelectors = selectors.filter((selector) => {
            const savedValue = selector.dataset.savedValue || 'sans-serif';
            const nextValue = selector.value || 'sans-serif';

            return savedValue !== nextValue;
        });

        if (!primarySelector || !dirtySelectors.includes(primarySelector)) {
            return dirtySelectors;
        }

        return [primarySelector, ...dirtySelectors.filter((selector) => selector !== primarySelector)];
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
    async function saveFamilyFallback(selector, form, options = {}) {
        if (!selector) {
            return false;
        }

        const suppressToast = !!options.suppressToast;
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
            setRoleFamilyCatalogFallback(family, savedFallback);
            updateInlineStackPreview(family);
            updateRoleOutputs();
            applyGeneratedCssPanelState(payload.generated_css_panel || null);
            syncFamilyFallbackSaveState(saveForm);
            setFamilyFallbackFeedback(saveForm, message, 'success');
            if (!suppressToast) {
                showToast(message, 'success');
            }
            return true;
        } catch (error) {
            const message = buildFallbackErrorMessage(error);

            selector.value = previousValue;
            updateInlineStackPreview(family);
            syncFamilyFallbackSaveState(saveForm);
            setFamilyFallbackFeedback(saveForm, message, 'error');
            if (!suppressToast) {
                showToast(message, 'error');
            }
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

    async function saveDirtyFamilyFallbacks(primarySelector, primaryForm) {
        const selectors = dirtyFamilyFallbackSelectors(primarySelector);

        if (selectors.length === 0) {
            syncFamilyFallbackSaveState(primaryForm);
            return true;
        }

        if (selectors.length === 1) {
            const selector = selectors[0];

            return saveFamilyFallback(selector, selector.closest('[data-family-fallback-form]'));
        }

        let savedCount = 0;
        let failedCount = 0;

        for (const selector of selectors) {
            const form = selector.closest('[data-family-fallback-form]');
            const saved = await saveFamilyFallback(selector, form, { suppressToast: true });

            if (saved) {
                savedCount += 1;
                continue;
            }

            failedCount += 1;
        }

        if (failedCount === 0) {
            showToast(buildFallbackBatchSavedMessage(savedCount), 'success');
            return true;
        }

        showToast(buildFallbackBatchPartialMessage(savedCount, failedCount), 'error');
        return false;
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

        if (!armDestructiveButton(button, confirmMessage)) {
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
                heading_weight: getElementValue(roleWeightSelects.heading, ''),
                body_weight: getElementValue(roleWeightSelects.body, ''),
                heading_axes: roleAxisFieldValues('heading'),
                body_axes: roleAxisFieldValues('body')
            };

            if (monospaceRoleEnabled) {
                requestBody.monospace = getElementValue(roleMonospace, '');
                requestBody.monospace_weight = getElementValue(roleWeightSelects.monospace, '');
                requestBody.monospace_axes = roleAxisFieldValues('monospace');
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

            renderAllRoleWeightEditors({
                heading: typeof roles.heading === 'string' ? roles.heading : getElementValue(roleHeading, ''),
                body: typeof roles.body === 'string' ? roles.body : getElementValue(roleBody, ''),
                monospace: typeof roles.monospace === 'string' ? roles.monospace : getElementValue(roleMonospace, ''),
                headingFallback: typeof roles.heading_fallback === 'string' ? roles.heading_fallback : getElementValue(roleHeadingFallback, 'sans-serif'),
                bodyFallback: typeof roles.body_fallback === 'string' ? roles.body_fallback : getElementValue(roleBodyFallback, 'sans-serif'),
                monospaceFallback: typeof roles.monospace_fallback === 'string' ? roles.monospace_fallback : getElementValue(roleMonospaceFallback, 'monospace'),
                headingWeight: roles.heading_weight || '',
                bodyWeight: roles.body_weight || '',
                monospaceWeight: roles.monospace_weight || '',
                headingAxes: roles.heading_axes || {},
                bodyAxes: roles.body_axes || {},
                monospaceAxes: roles.monospace_axes || {},
            });

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

            renderAllRoleAxisEditors({
                heading: typeof roles.heading === 'string' ? roles.heading : getElementValue(roleHeading, ''),
                body: typeof roles.body === 'string' ? roles.body : getElementValue(roleBody, ''),
                monospace: typeof roles.monospace === 'string' ? roles.monospace : getElementValue(roleMonospace, ''),
                headingFallback: typeof roles.heading_fallback === 'string' ? roles.heading_fallback : getElementValue(roleHeadingFallback, 'sans-serif'),
                bodyFallback: typeof roles.body_fallback === 'string' ? roles.body_fallback : getElementValue(roleBodyFallback, 'sans-serif'),
                monospaceFallback: typeof roles.monospace_fallback === 'string' ? roles.monospace_fallback : getElementValue(roleMonospaceFallback, 'monospace'),
                headingWeight: roles.heading_weight || '',
                bodyWeight: roles.body_weight || '',
                monospaceWeight: roles.monospace_weight || '',
                headingAxes: roles.heading_axes || {},
                bodyAxes: roles.body_axes || {},
                monospaceAxes: roles.monospace_axes || {},
            });

            syncPreviewBootstrapState({
                roles,
                appliedRoles: payload.applied_roles || {},
            });
            initialDraftRoleState = currentDraftRoleState();
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
    function googleDisplayVariants(variants) {
        const normalized = normalizeVariantTokens(Array.isArray(variants) ? variants : []);

        if (currentGoogleFormatMode() !== 'variable') {
            return normalized;
        }

        const styles = [];

        if (normalized.some((variant) => !String(variant || '').toLowerCase().includes('italic'))) {
            styles.push('regular');
        }

        if (normalized.some((variant) => String(variant || '').toLowerCase().includes('italic'))) {
            styles.push('italic');
        }

        return styles.length > 0 ? styles : ['regular'];
    }

    function bunnyDisplayVariants(variants) {
        const normalized = normalizeHostedVariantTokens(Array.isArray(variants) ? variants.join(',') : variants);

        if (currentBunnyFormatMode() !== 'variable') {
            return normalized;
        }

        const styles = [];

        if (normalized.some((variant) => !String(variant || '').toLowerCase().includes('italic'))) {
            styles.push('regular');
        }

        if (normalized.some((variant) => String(variant || '').toLowerCase().includes('italic'))) {
            styles.push('italic');
        }

        return styles.length > 0 ? styles : ['regular'];
    }

    function formatVariantChipLabel(variant, useStyleLabels = false) {
        const normalized = String(variant || '').trim().toLowerCase();

        if (normalized === 'regular') {
            return useStyleLabels ? 'Regular' : '400';
        }

        if (normalized === 'italic') {
            return useStyleLabels ? 'Italic' : '400italic';
        }

        return String(variant || '').trim();
    }

    function serializeVariantTokenForManualField(variant, useStyleLabels = false) {
        const normalized = String(variant || '').trim().toLowerCase();

        if (normalized === 'regular') {
            return useStyleLabels ? 'regular' : '400';
        }

        if (normalized === 'italic') {
            return useStyleLabels ? 'italic' : '400italic';
        }

        return String(variant || '').trim();
    }

    function serializeVariantTokensForManualField(variants, useStyleLabels = false) {
        return (Array.isArray(variants) ? variants : [])
            .map((variant) => serializeVariantTokenForManualField(variant, useStyleLabels))
            .filter(Boolean);
    }

    function activeGoogleFamilyForImport() {
        return selectedSearchFamily || findGoogleFamilyMatch(currentGoogleImportFamily());
    }

    function activeBunnyFamilyForImport() {
        return selectedBunnySearchFamily || findBunnyFamilyMatch(currentBunnyImportFamily());
    }

    function selectedGoogleVariantsLabel() {
        return currentGoogleFormatMode() === 'variable'
            ? getString('selectedStylesLabel', 'Styles to Import')
            : getString('selectedVariantsLabel', 'Variants to Import');
    }

    function selectedBunnyVariantsLabel() {
        return currentBunnyFormatMode() === 'variable'
            ? getString('selectedStylesLabel', 'Styles to Import')
            : getString('selectedVariantsLabel', 'Variants to Import');
    }

    function selectedGoogleManualVariantsLabel() {
        return currentGoogleFormatMode() === 'variable'
            ? getString('manualStylesLabel', 'Manual Styles')
            : getString('manualVariantsLabel', 'Manual Variants');
    }

    function selectedBunnyManualVariantsLabel() {
        return currentBunnyFormatMode() === 'variable'
            ? getString('manualStylesLabel', 'Manual Styles')
            : getString('manualVariantsLabel', 'Manual Variants');
    }

    function importSelectionStepTitle(isVariableMode) {
        return isVariableMode
            ? getString('importStepTitleVariable', 'Choose Styles and Delivery')
            : getString('importStepTitle', 'Choose Variants and Delivery');
    }

    function selectionHelperCopy(isVariableMode) {
        return isVariableMode
            ? getString(
                'importStyleSelectionNote',
                'Choose which variable styles to import. Normal and italic are separate variable font files when available.'
            )
            : getString(
                'importVariantSelectionNote',
                'Click chips or type a comma-separated list above. Both stay in sync.'
            );
    }

    function selectionSummaryEmptyLabel(isVariableMode) {
        return isVariableMode
            ? getString('importSelectionSummaryEmptyVariable', '0 Styles Selected')
            : getString('importSelectionSummaryEmpty', '0 Variants Selected');
    }

    function manualSelectionPlaceholder(isVariableMode) {
        return isVariableMode
            ? getString('manualStylesPlaceholder', 'e.g. regular,italic')
            : getString('manualVariantsPlaceholder', 'e.g. 400,700');
    }

    function syncGoogleImportTerminology(isVariableMode) {
        if (googleImportStepTitle) {
            googleImportStepTitle.textContent = importSelectionStepTitle(isVariableMode);
        }

        if (googleManualVariantsLabel) {
            googleManualVariantsLabel.textContent = selectedGoogleManualVariantsLabel();
        }

        if (manualVariants) {
            manualVariants.placeholder = manualSelectionPlaceholder(isVariableMode);
        }

        if (googleSelectedVariantsLabel) {
            googleSelectedVariantsLabel.textContent = selectedGoogleVariantsLabel();
        }

        if (googleSelectedVariantsNote) {
            googleSelectedVariantsNote.textContent = selectionHelperCopy(isVariableMode);
        }
    }

    function syncBunnyImportTerminology(isVariableMode) {
        if (bunnyImportStepTitle) {
            bunnyImportStepTitle.textContent = importSelectionStepTitle(isVariableMode);
        }

        if (bunnyManualVariantsLabel) {
            bunnyManualVariantsLabel.textContent = selectedBunnyManualVariantsLabel();
        }

        if (bunnyVariants) {
            bunnyVariants.placeholder = isVariableMode
                ? getString('manualStylesPlaceholderOptional', 'Leave blank, or enter regular,italic')
                : getString('manualVariantsPlaceholderOptional', 'Leave blank, or enter 400,700italic');
        }

        if (bunnySelectedVariantsLabel) {
            bunnySelectedVariantsLabel.textContent = selectedBunnyVariantsLabel();
        }

        if (bunnySelectedVariantsNote) {
            bunnySelectedVariantsNote.textContent = selectionHelperCopy(isVariableMode);
        }
    }

    function variableFormatSelectionNote(provider, format) {
        if (!format || typeof format !== 'object') {
            return '';
        }

        if (format.source_only) {
            return familyVariableNote(provider);
        }

        return provider === 'google'
            ? getString(
                'googleFormatChoiceNote',
                'Variable keeps Google’s supported axis ranges. Static lets you pick individual saved weights and styles.'
            )
            : getString(
                'bunnyFormatChoiceNote',
                'Static imports from Bunny are fully supported. Variable source metadata is shown here when Bunny exposes it for the family.'
            );
    }

    function renderImportFormatChoice(container, family, provider, selectedMode) {
        if (!container) {
            return;
        }

        container.innerHTML = '';
        container.hidden = true;

        const fieldset = container.closest('fieldset');
        const noteElement = fieldset
            ? fieldset.querySelector(`[data-import-format-note="${provider}"]`)
            : null;
        const visibleModes = familyVisibleFormats(family, provider);

        if (noteElement) {
            noteElement.hidden = true;
            noteElement.textContent = '';
        }

        if (!family || visibleModes.length < 2) {
            if (fieldset) {
                fieldset.hidden = true;
            }

            return;
        }

        visibleModes.forEach((mode) => {
            const format = familyFormatInfo(family, mode, provider);

            if (!format) {
                return;
            }

            const button = document.createElement('button');
            const isActive = mode === selectedMode;

            button.type = 'button';
            button.className = `tasty-fonts-output-quick-option${isActive ? ' is-active' : ''}`;
            button.dataset.importFormatMode = mode;
            button.dataset.importFormatProvider = provider;
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            button.disabled = format.available === false;
            button.textContent = format.label;

            const note = variableFormatSelectionNote(provider, format);

            if (note) {
                button.title = note;
            }

            container.appendChild(button);
        });

        const sourceOnlyFormat = visibleModes
            .map((mode) => familyFormatInfo(family, mode, provider))
            .find((format) => format && format.source_only);

        if (noteElement && sourceOnlyFormat) {
            noteElement.textContent = variableFormatSelectionNote(provider, sourceOnlyFormat);
            noteElement.hidden = !noteElement.textContent;
        }

        container.hidden = false;

        if (fieldset) {
            fieldset.hidden = false;
        }
    }

    function syncGoogleFormatChoice(family) {
        selectedGoogleFormatMode = resolveImportFormatMode(family, 'google', selectedGoogleFormatMode);
        renderImportFormatChoice(googleFormatChoice, family, 'google', selectedGoogleFormatMode);
    }

    function syncBunnyFormatChoice(family) {
        selectedBunnyFormatMode = resolveImportFormatMode(family, 'bunny', selectedBunnyFormatMode);
        renderImportFormatChoice(bunnyFormatChoice, family, 'bunny', selectedBunnyFormatMode);
    }

    function normalizeGoogleManualVariantsForCurrentFormat() {
        if (!manualVariants) {
            return [];
        }

        const nextTokens = currentGoogleFormatMode() === 'variable'
            ? googleDisplayVariants(normalizeVariantTokens(manualVariants.value))
            : normalizeVariantTokens(manualVariants.value);

        manualVariants.value = serializeVariantTokensForManualField(
            nextTokens,
            currentGoogleFormatMode() === 'variable'
        ).join(',');

        if (nextTokens.length === 0) {
            manualVariants.dataset.explicitEmpty = 'true';
        } else {
            delete manualVariants.dataset.explicitEmpty;
        }

        return nextTokens;
    }

    function normalizeBunnyManualVariantsForCurrentFormat() {
        if (!bunnyVariants) {
            return [];
        }

        const nextTokens = currentBunnyFormatMode() === 'variable'
            ? bunnyDisplayVariants(normalizeHostedVariantTokens(bunnyVariants.value))
            : normalizeHostedVariantTokens(bunnyVariants.value);

        bunnyVariants.value = serializeVariantTokensForManualField(
            nextTokens,
            currentBunnyFormatMode() === 'variable'
        ).join(',');

        if (nextTokens.length === 0) {
            bunnyVariants.dataset.explicitEmpty = 'true';
        } else {
            delete bunnyVariants.dataset.explicitEmpty;
        }

        return nextTokens;
    }

    function renderVariantOptions(variants, familyName = '') {
        if (!variantsWrap) {
            return;
        }

        const displayVariants = googleDisplayVariants(variants);

        const nextFamilyKey = normalizeFamilyKey(familyName || currentGoogleImportFamily());
        const familyChanged = nextFamilyKey !== '' && nextFamilyKey !== renderedGoogleVariantFamily;
        const seededTokens = manualVariants ? normalizeVariantTokens(manualVariants.value) : [];
        const preserveEmptySelection = !!(manualVariants && manualVariants.dataset.explicitEmpty === 'true' && !familyChanged);

        variantsWrap.innerHTML = '';

        displayVariants.forEach((variant) => {
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
            text.textContent = formatVariantChipLabel(variant, currentGoogleFormatMode() === 'variable');

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
            const axes = document.createElement('span');
            const fallback = googlePreviewFallback(item.category);
            const inLibrary = isFamilyInLibrary(item.family || '');
            const variantCount = Number(item.variants_count || (Array.isArray(item.variants) ? item.variants.length : 0));
            const showVariableMeta = variableFontsEnabled && familySupportsFormat(item, 'variable', 'google');

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
            appendSearchResultBadges(head, item, inLibrary, 'google');

            preview.className = 'tasty-fonts-search-card-preview';
            preview.textContent = googlePreviewText();
            preview.style.fontFamily = `"${item.family}", ${fallback}`;

            meta.className = 'tasty-fonts-search-card-meta tasty-fonts-muted';
            category.textContent = item.category || fallback;
            variants.textContent = formatPluralMessage(
                getString('variantCountSingle', '%d variant'),
                getString('variantCountMultiple', '%d variants'),
                variantCount,
                [variantCount]
            );

            meta.append(category, variants);

            if (showVariableMeta) {
                axes.textContent = familyAxisSummaryLabels(item).join(' · ');

                if (axes.textContent) {
                    meta.append(axes);
                }
            }

            card.append(head, preview, meta);
            googleResults.appendChild(card);
        });

        const matchedFamily = findGoogleFamilyMatch(currentGoogleImportFamily());

        if (matchedFamily) {
            selectedSearchFamily = matchedFamily;
            syncGoogleFormatChoice(matchedFamily);

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

        syncGoogleFormatChoice(selectedSearchFamily);
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

        const displayVariants = bunnyDisplayVariants(variants);

        const nextFamilyKey = normalizeFamilyKey(familyName || currentBunnyImportFamily());
        const familyChanged = nextFamilyKey !== '' && nextFamilyKey !== renderedBunnyVariantFamily;
        const seededTokens = bunnyVariants ? normalizeHostedVariantTokens(bunnyVariants.value) : [];
        const preserveEmptySelection = !!(bunnyVariants && bunnyVariants.dataset.explicitEmpty === 'true' && !familyChanged);

        bunnyVariantsWrap.innerHTML = '';

        displayVariants.forEach((variant) => {
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
            text.textContent = formatVariantChipLabel(variant, currentBunnyFormatMode() === 'variable');

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
            const axes = document.createElement('span');
            const fallback = googlePreviewFallback(item.category);
            const variantCount = Array.isArray(item.variants) ? bunnyDisplayVariants(item.variants).length : Number(item.style_count || 0);
            const isActive = !!selectedBunnySearchFamily && matchesBunnyFamilyEntry(selectedBunnySearchFamily, item.family || item.slug || '');
            const inLibrary = isFamilyInLibrary(item.family || '', item.slug || '');
            const showVariableMeta = variableFontsEnabled && familySupportsFormat(item, 'variable', 'bunny');

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
            appendSearchResultBadges(head, item, inLibrary, 'bunny');

            preview.className = 'tasty-fonts-search-card-preview';
            preview.textContent = googlePreviewText();
            preview.style.fontFamily = `"${item.family}", ${fallback}`;

            meta.className = 'tasty-fonts-search-card-meta tasty-fonts-muted';
            category.textContent = item.category || fallback;
            variants.textContent = variantCount > 0
                ? formatPluralMessage(
                    getString('variantCountSingle', '%d variant'),
                    getString('variantCountMultiple', '%d variants'),
                    variantCount,
                    [variantCount]
                )
                : getString('bunnySourceLabel', 'Bunny Fonts');

            meta.append(category, variants);

            if (showVariableMeta) {
                axes.textContent = familyAxisSummaryLabels(item).join(' · ');

                if (axes.textContent) {
                    meta.append(axes);
                }
            }

            card.append(head, preview, meta);
            bunnyResults.appendChild(card);
        });

        const matchedFamily = findBunnyFamilyMatch(currentBunnyImportFamily());

        if (matchedFamily) {
            selectedBunnySearchFamily = matchedFamily;
            syncBunnyFormatChoice(matchedFamily);

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

        syncBunnyFormatChoice(selectedBunnySearchFamily);
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

        const axisTags = [];
        let isVariable = /variablefont/i.test(baseName);
        const axisMatch = baseName.match(/\[([a-z0-9,]+)\]/i);

        if (axisMatch && axisMatch[1]) {
            isVariable = true;
            axisMatch[1].split(',').forEach((tag) => {
                const normalizedTag = normalizeAxisTag(tag);

                if (normalizedTag) {
                    axisTags.push(normalizedTag);
                }
            });
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

        if (!isVariable) {
            return { family, weight, style, isVariable: false, axes: [] };
        }

        if (!axisTags.length) {
            axisTags.push('WGHT');
        }

        const axes = axisTags.map((tag) => {
            if (tag === 'WGHT') {
                return { tag: 'WGHT', min: '100', default: '400', max: '900' };
            }

            return { tag, min: '0', default: '0', max: '100' };
        });

        return { family, weight: '100..900', style, isVariable: true, axes };
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

    function nextGeneratedFieldId(prefix) {
        const normalizedPrefix = String(prefix || 'tasty-fonts-field').trim() || 'tasty-fonts-field';
        const id = `${normalizedPrefix}-${nextGeneratedFieldIndex}`;

        nextGeneratedFieldIndex += 1;

        return id;
    }

    function ensureElementId(element, prefix) {
        if (!element) {
            return '';
        }

        if (!element.id) {
            element.id = nextGeneratedFieldId(prefix);
        }

        return element.id;
    }

    function bindExplicitLabel(label, control, prefix) {
        if (!label || !control) {
            return '';
        }

        const controlId = ensureElementId(control, prefix);

        label.setAttribute('for', controlId);

        return controlId;
    }

    function uploadHeadingMap(group) {
        const headings = {};

        if (!group) {
            return headings;
        }

        const groupIndex = group.getAttribute('data-upload-group-index') || '0';

        Array.from(group.querySelectorAll('[data-upload-heading]')).forEach((heading) => {
            const key = String(heading.getAttribute('data-upload-heading') || '').trim();

            if (!key) {
                return;
            }

            if (!heading.id) {
                heading.id = `tasty-fonts-upload-heading-${groupIndex}-${key}`;
            }

            headings[key] = heading.id;
        });

        return headings;
    }

    function setUploadFieldDescriptions(target, ids = []) {
        if (!target) {
            return;
        }

        const describedBy = ids.filter(Boolean).join(' ').trim();

        if (describedBy) {
            target.setAttribute('aria-describedby', describedBy);
            return;
        }

        target.removeAttribute('aria-describedby');
    }

    function buildUploadRowLabel(group, index) {
        const familyName = String(familyInputForGroup(group)?.value || '').trim();

        if (familyName !== '') {
            return formatMessage(
                getString('uploadFaceRowLabelFamily', 'Font face %1$d for %2$s'),
                [index + 1, familyName]
            );
        }

        return formatMessage(getString('uploadFaceRowLabel', 'Font face %d'), [index + 1]);
    }

    function ensureUploadRowLabel(row, rowIndex, text) {
        if (!row) {
            return '';
        }

        let label = row.querySelector('[data-upload-row-label]');

        if (!label) {
            label = document.createElement('span');
            label.className = 'screen-reader-text';
            label.setAttribute('data-upload-row-label', '');
            row.insertBefore(label, row.firstChild);
        }

        label.id = `tasty-fonts-upload-row-label-${rowIndex}`;
        label.textContent = text;

        return label.id;
    }

    function bindUploadGroupLabels(group) {
        if (!group) {
            return;
        }

        const groupIndex = group.getAttribute('data-upload-group-index') || '0';

        bindExplicitLabel(
            group.querySelector('[data-upload-group-label="family"]'),
            familyInputForGroup(group),
            `tasty-fonts-upload-group-family-${groupIndex}`
        );
        bindExplicitLabel(
            group.querySelector('[data-upload-group-label="fallback"]'),
            fallbackInputForGroup(group),
            `tasty-fonts-upload-group-fallback-${groupIndex}`
        );
    }

    function bindUploadRowFieldLabels(row, rowIndex) {
        if (!row) {
            return;
        }

        bindExplicitLabel(
            row.querySelector('[data-upload-field-label="file"]'),
            row.querySelector('[data-upload-field="file"]'),
            `tasty-fonts-upload-row-file-${rowIndex}`
        );
        bindExplicitLabel(
            row.querySelector('[data-upload-field-label="weight"]'),
            row.querySelector('[data-upload-field="weight"]'),
            `tasty-fonts-upload-row-weight-${rowIndex}`
        );
        bindExplicitLabel(
            row.querySelector('[data-upload-field-label="style"]'),
            row.querySelector('[data-upload-field="style"]'),
            `tasty-fonts-upload-row-style-${rowIndex}`
        );
        bindExplicitLabel(
            row.querySelector('[data-upload-field-label="variable"]'),
            row.querySelector('[data-upload-field="is-variable"]'),
            `tasty-fonts-upload-row-variable-${rowIndex}`
        );
    }

    function updateUploadGroupAccessibility(group) {
        if (!group) {
            return;
        }

        assignUploadGroupIndex(group);
        bindUploadGroupLabels(group);

        const headingIds = uploadHeadingMap(group);

        uploadRows(group).forEach((row, index) => {
            assignUploadRowIndex(row);

            const rowIndex = row.getAttribute('data-upload-index') || String(index);
            bindUploadRowFieldLabels(row, rowIndex);
            const rowLabelId = ensureUploadRowLabel(row, rowIndex, buildUploadRowLabel(group, index));
            const rowFieldIds = (key) => [rowLabelId, headingIds[key]];

            row.setAttribute('role', 'group');
            row.setAttribute('aria-labelledby', rowLabelId);

            setUploadFieldDescriptions(row.querySelector('[data-upload-field="file"]'), rowFieldIds('file'));
            setUploadFieldDescriptions(row.querySelector('[data-upload-field="weight"]'), rowFieldIds('weight'));
            setUploadFieldDescriptions(row.querySelector('[data-upload-field="style"]'), rowFieldIds('style'));
            setUploadFieldDescriptions(row.querySelector('[data-upload-field="is-variable"]'), rowFieldIds('variable'));

            const removeButton = row.querySelector('[data-upload-remove]');

            if (removeButton) {
                removeButton.setAttribute(
                    'aria-label',
                    formatMessage(getString('uploadRemoveFace', 'Remove font face %d'), [index + 1])
                );
                setUploadFieldDescriptions(removeButton, rowFieldIds('action'));
            }

            const status = row.querySelector('[data-upload-row-status]');

            if (status) {
                status.setAttribute('aria-live', 'polite');
                status.setAttribute('aria-atomic', 'true');
            }
        });
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
        const variableField = row.querySelector('[data-upload-field="is-variable"]');
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
            delete button.dataset.detectedVariable;
            delete button.dataset.detectedAxes;
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
        button.dataset.detectedVariable = detected.isVariable ? '1' : '0';
        button.dataset.detectedAxes = JSON.stringify(detected.axes || []);
        button.textContent = `${getString('uploadUseDetected', 'Use Detected Values')} · ${label}`;

        return detected;
    }

    function uploadAxisRows(row) {
        return row ? Array.from(row.querySelectorAll('[data-upload-axis-row]')) : [];
    }

    function buildUploadAxisRow(axis = {}) {
        const wrapper = document.createElement('div');
        wrapper.className = 'tasty-fonts-upload-axis-row';
        wrapper.setAttribute('data-upload-axis-row', '');
        const axisRowId = nextGeneratedFieldId('tasty-fonts-upload-axis-row');

        const fields = [
            { key: 'tag', placeholder: 'wght', label: getString('uploadAxisTag', 'Axis') },
            { key: 'min', placeholder: '100', label: getString('uploadAxisMin', 'Min') },
            { key: 'default', placeholder: '400', label: getString('uploadAxisDefault', 'Default') },
            { key: 'max', placeholder: '900', label: getString('uploadAxisMax', 'Max') },
        ];

        fields.forEach(({ key, placeholder, label }) => {
            const field = document.createElement('label');
            field.className = 'tasty-fonts-stack-field tasty-fonts-upload-axis-field';
            field.setAttribute('data-upload-axis-label', key);

            const title = document.createElement('span');
            title.className = 'screen-reader-text';
            title.textContent = label;

            const input = document.createElement('input');
            input.id = `${axisRowId}-${key}`;
            input.type = key === 'tag' ? 'text' : 'number';
            input.step = key === 'tag' ? '' : 'any';
            input.placeholder = placeholder;
            input.value = key === 'tag'
                ? String(axis[key] || '').toUpperCase()
                : String(axis[key] || '');
            input.setAttribute('data-upload-axis-field', key);
            field.setAttribute('for', input.id);

            field.appendChild(title);
            field.appendChild(input);
            wrapper.appendChild(field);
        });

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'button tasty-fonts-button-danger tasty-fonts-upload-axis-remove';
        removeButton.setAttribute('data-upload-remove-axis', '');
        removeButton.textContent = getString('uploadAxisRemove', 'Remove');
        wrapper.appendChild(removeButton);

        return wrapper;
    }

    function replaceUploadAxisRows(row, axes = []) {
        const list = row ? row.querySelector('[data-upload-axis-list]') : null;

        if (!list) {
            return;
        }

        list.innerHTML = '';

        axes.forEach((axis) => {
            list.appendChild(buildUploadAxisRow(axis));
        });
    }

    function ensureUploadAxisRow(row) {
        const list = row ? row.querySelector('[data-upload-axis-list]') : null;

        if (!list || uploadAxisRows(row).length) {
            return;
        }

        list.appendChild(buildUploadAxisRow({ tag: 'WGHT', min: '100', default: '400', max: '900' }));
    }

    function syncUploadVariableState(row) {
        if (!row) {
            return;
        }

        const variableField = row.querySelector('[data-upload-field="is-variable"]');
        const weightField = row.querySelector('[data-upload-field="weight"]');
        const axisShell = row.querySelector('[data-upload-axis-shell]');
        const isVariable = !!variableFontsEnabled && !!(variableField && variableField.checked);

        if (weightField) {
            weightField.disabled = isVariable;
        }

        if (axisShell) {
            axisShell.hidden = !isVariable;
        }

        if (isVariable) {
            ensureUploadAxisRow(row);
        }
    }

    function readUploadAxes(row) {
        const axes = [];

        uploadAxisRows(row).forEach((axisRow) => {
            const axis = {
                tag: normalizeAxisTag(axisRow.querySelector('[data-upload-axis-field="tag"]')?.value || ''),
                min: normalizeAxisValue(axisRow.querySelector('[data-upload-axis-field="min"]')?.value || ''),
                default: normalizeAxisValue(axisRow.querySelector('[data-upload-axis-field="default"]')?.value || ''),
                max: normalizeAxisValue(axisRow.querySelector('[data-upload-axis-field="max"]')?.value || ''),
            };

            if (!axis.tag || !axis.min || !axis.default || !axis.max) {
                return;
            }

            axes.push(axis);
        });

        return axes;
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
        syncUploadVariableState(row);
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
        updateUploadGroupAccessibility(group);
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
        const variableField = row.querySelector('[data-upload-field="is-variable"]');

        if (weightField && options.weight) {
            weightField.value = options.weight;
        }

        if (styleField && options.style) {
            styleField.value = options.style;
        }

        if (variableField) {
            variableField.checked = !!options.isVariable;
        }

        faceList.appendChild(fragment);
        const appendedRow = faceList.lastElementChild;

        if (appendedRow && Array.isArray(options.axes) && options.axes.length) {
            replaceUploadAxisRows(appendedRow, options.axes);
        }

        initializeUploadRow(appendedRow);
        updateUploadGroupAccessibility(group);
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

        updateUploadGroupAccessibility(appendedGroup);
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
                const variableField = row.querySelector('[data-upload-field="is-variable"]');
                const selectedFile = file && file.files ? file.files[0] : null;
                const isVariable = !!variableFontsEnabled && !!(variableField && variableField.checked);
                const axes = isVariable ? readUploadAxes(row) : [];

                formData.append(`rows[${rowIndex}][family]`, family ? family.value.trim() : '');
                formData.append(`rows[${rowIndex}][weight]`, weight ? weight.value : '400');
                formData.append(`rows[${rowIndex}][style]`, style ? style.value : 'normal');
                formData.append(`rows[${rowIndex}][fallback]`, fallback ? fallback.value : 'sans-serif');
                formData.append(`rows[${rowIndex}][is_variable]`, isVariable ? '1' : '0');

                axes.forEach((axis, axisIndex) => {
                    formData.append(`rows[${rowIndex}][axes][${axisIndex}][tag]`, axis.tag);
                    formData.append(`rows[${rowIndex}][axes][${axisIndex}][min]`, axis.min);
                    formData.append(`rows[${rowIndex}][axes][${axisIndex}][default]`, axis.default);
                    formData.append(`rows[${rowIndex}][axes][${axisIndex}][max]`, axis.max);
                    formData.append(`rows[${rowIndex}][variation_defaults][${axis.tag}]`, axis.default);
                });

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
            syncGoogleFormatChoice(item);

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
            syncBunnyFormatChoice(item);

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
        return resolveGoogleManualVariantTokens();
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
        const formatMode = currentGoogleFormatMode();

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
                    delivery_mode: deliveryMode,
                    format_mode: formatMode,
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
                    delivery_mode: deliveryMode,
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
            const wasExpanded = isDisclosureExpanded(toggle);

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

    function getClearableSelectButtonTarget(button) {
        if (!button) {
            return null;
        }

        const explicitTargetId = String(button.getAttribute('data-clear-target') || '').trim();

        if (explicitTargetId) {
            return document.getElementById(explicitTargetId);
        }

        const field = button.closest('.tasty-fonts-select-field, .tasty-fonts-combobox-field');

        return field ? field.querySelector('select, input') : null;
    }

    function syncClearableSelectButton(button) {
        const select = getClearableSelectButtonTarget(button);

        if (!select) {
            if (button) {
                button.hidden = true;
                button.disabled = true;
            }

            return;
        }

        const clearValue = String(button.getAttribute('data-clear-value') || '');
        const field = button.closest('.tasty-fonts-select-field, .tasty-fonts-combobox-field');
        const forceAffordance = String(button.getAttribute('data-clear-affordance') || '').trim() === 'always';
        const hasOptions = select.tagName === 'SELECT' ? Number(select.options ? select.options.length : 0) > 0 : true;
        const hasClearValue = !select.disabled && String(select.value || '') !== clearValue;
        const shouldShowButton = !select.disabled && hasOptions && (forceAffordance || hasClearValue);

        button.hidden = !shouldShowButton;
        button.disabled = !shouldShowButton;

        if (field) {
            field.classList.toggle('has-clear-value', shouldShowButton);
        }
    }

    function syncAllClearableSelectButtons() {
        document.querySelectorAll('[data-clear-select-button]').forEach((button) => {
            syncClearableSelectButton(button);
        });
    }

    function handleClearableSelectClick(event) {
        const button = event.target.closest('[data-clear-select-button]');

        if (!button) {
            return false;
        }

        const select = getClearableSelectButtonTarget(button);

        if (!select || select.disabled) {
            syncClearableSelectButton(button);
            return true;
        }

        const clearValue = String(button.getAttribute('data-clear-value') || '');
        const previousValue = String(select.value || '');

        if (select.tagName === 'SELECT') {
            const options = Array.from(select.options || []);
            const hasClearOption = options.some((option) => String(option.value) === clearValue);

            if (hasClearOption) {
                select.value = clearValue;
            } else if (options.length > 0) {
                select.selectedIndex = 0;
            }
        } else {
            select.value = clearValue;
        }

        if (String(select.value || '') !== previousValue) {
            if (select.tagName !== 'SELECT') {
                select.dispatchEvent(new Event('input', { bubbles: true }));
            }

            select.dispatchEvent(new Event('change', { bubbles: true }));
            if (select.tagName === 'SELECT') {
                select.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        syncClearableSelectButton(button);

        if (typeof select.focus === 'function') {
            try {
                select.focus({ preventScroll: true });
            } catch (error) {
                select.focus();
            }
        }

        return true;
    }

    function handleDisclosureToggleClick(event) {
        const disclosureToggle = event.target.closest('[data-disclosure-toggle]');

        if (!disclosureToggle) {
            return false;
        }

        const isExpanded = isDisclosureExpanded(disclosureToggle);
        const nextExpanded = !isExpanded;
        const targetId = disclosureToggle.getAttribute('data-disclosure-toggle') || '';
        const isRoleToolToggle = targetId === 'tasty-fonts-role-preview-panel' || targetId === 'tasty-fonts-role-snippets-panel';

        if (nextExpanded && isRoleToolToggle) {
            const pairedTargetId = targetId === 'tasty-fonts-role-preview-panel'
                ? 'tasty-fonts-role-snippets-panel'
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
        } else if (nextExpanded) {
            focusDisclosureTarget(targetId);
        }

        if (nextExpanded && disclosureToggle.getAttribute('data-disclosure-toggle') === 'tasty-fonts-role-preview-panel') {
            initializePreviewWorkspace();
        }

        if (nextExpanded && isRoleToolToggle) {
            revealDisclosurePanel(targetId, disclosureToggle);
        }

        if (nextExpanded) {
            announceDisclosureExpansion(disclosureToggle);
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

            if (group === 'page') {
                syncRoleDisclosureForCurrentPage();
            }

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
        const tablist = tab.closest('[role="tablist"]');
        const orientation = tablist ? (tablist.getAttribute('aria-orientation') || 'horizontal') : 'horizontal';

        if (!group || buttons.length < 2) {
            return false;
        }

        const currentIndex = buttons.indexOf(tab);
        const nextIndex = getTabNavigationTargetIndex(event.key, currentIndex, buttons.length, orientation);

        if (nextIndex === null) {
            return false;
        }

        event.preventDefault();

        const nextTab = buttons[nextIndex];
        activateTabGroup(group, nextTab.getAttribute('data-tab-target'));

        if (group === 'page') {
            syncRoleDisclosureForCurrentPage();
        }

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

        const nextRoleState = resolveAssignedRoleState(role, family, snapshotBeforeChange, [currentAppliedRoleState()]);

        renderAllRoleWeightEditors(nextRoleState);
        renderAllRoleAxisEditors(nextRoleState);
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
                    renderAllPreviewWeightEditors(previewRoleState);
                    renderAllPreviewAxisEditors(previewRoleState);
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

        if (!armDestructiveButton(deleteTarget, confirmTemplate.replace('%s', familyName))) {
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
                updateUploadGroupAccessibility(group);
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

        if (event.target.closest('[data-upload-remove-axis]')) {
            const axisRow = event.target.closest('[data-upload-axis-row]');
            const row = event.target.closest('[data-upload-row]');

            if (axisRow && row && uploadAxisRows(row).length > 1) {
                axisRow.remove();
            }

            return true;
        }

        if (event.target.closest('[data-upload-add-axis]')) {
            const row = event.target.closest('[data-upload-row]');
            const list = row ? row.querySelector('[data-upload-axis-list]') : null;

            if (row && list) {
                list.appendChild(buildUploadAxisRow());
            }

            return true;
        }

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
        const variableField = row ? row.querySelector('[data-upload-field="is-variable"]') : null;
        const detectedFamily = detectedButton.dataset.detectedFamily || '';
        const detectedWeight = detectedButton.dataset.detectedWeight || '';
        const detectedStyle = detectedButton.dataset.detectedStyle || '';
        const detectedVariable = detectedButton.dataset.detectedVariable === '1';
        const detectedAxes = (() => {
            try {
                return JSON.parse(detectedButton.dataset.detectedAxes || '[]');
            } catch (error) {
                return [];
            }
        })();

        if (familyField && !familyField.value.trim() && detectedFamily) {
            familyField.value = detectedFamily;
        }

        if (weightField && detectedWeight) {
            weightField.value = detectedWeight;
        }

        if (styleField && detectedStyle) {
            styleField.value = detectedStyle;
        }

        if (variableField) {
            variableField.checked = detectedVariable;
        }

        if (row && detectedVariable) {
            replaceUploadAxisRows(row, Array.isArray(detectedAxes) ? detectedAxes : []);
        }

        syncUploadVariableState(row);

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

        if (handleClearableSelectClick(event)) {
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
                const saved = await saveDirtyFamilyFallbacks(selector, form);

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

    function bindClearableSelectControls() {
        document.querySelectorAll('.tasty-fonts-select-field select, .tasty-fonts-combobox-field > input').forEach((element) => {
            const field = element.closest('.tasty-fonts-select-field, .tasty-fonts-combobox-field');
            const button = field ? field.querySelector('[data-clear-select-button]') : null;

            if (!button) {
                return;
            }

            const syncButton = () => {
                syncClearableSelectButton(button);
            };

            element.addEventListener('change', syncButton);
            element.addEventListener('input', syncButton);

            syncClearableSelectButton(button);
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
                element.addEventListener('change', () => {
                    renderAllRoleWeightEditors();
                    renderAllRoleAxisEditors();
                    updateRoleOutputs();
                });
            }
        });

        Object.values(roleWeightSelects).forEach((element) => {
            if (element) {
                element.addEventListener('change', updateRoleOutputs);
            }
        });

        document.querySelectorAll('[data-role-axis-fields]').forEach((container) => {
            container.addEventListener('input', updateRoleOutputs);
            container.addEventListener('change', updateRoleOutputs);
        });

        Object.entries(previewRoleSelects).forEach(([roleKey, element]) => {
            if (!element) {
                return;
            }

            element.addEventListener('change', () => {
                initializePreviewWorkspace();
                const nextFamily = element.value || '';
                previewRoleState = normalizeRoleState({
                    ...(previewRoleState || currentDraftRoleState()),
                    [roleKey]: nextFamily,
                    [`${roleKey}Weight`]: '',
                    [`${roleKey}Axes`]: {},
                });
                previewDirty = true;
                previewFollowsDraft = false;
                renderAllPreviewWeightEditors(previewRoleState);
                renderAllPreviewAxisEditors(previewRoleState);
                applyPreviewOutputs(previewSourceLabel ? previewSourceLabel.textContent : '');
            });
        });

        Object.entries(previewWeightSelects).forEach(([roleKey, element]) => {
            if (!element) {
                return;
            }

            element.addEventListener('change', () => {
                initializePreviewWorkspace();
                previewRoleState = normalizeRoleState({
                    ...(previewRoleState || currentDraftRoleState()),
                    [`${roleKey}Weight`]: element.value || '',
                });
                previewDirty = true;
                previewFollowsDraft = false;
                applyPreviewOutputs(previewSourceLabel ? previewSourceLabel.textContent : '');
            });
        });

        document.querySelectorAll('[data-preview-axis-fields]').forEach((container) => {
            const roleKey = String(container.getAttribute('data-preview-axis-fields') || '').trim();

            if (!roleKey) {
                return;
            }

            const syncPreviewAxisState = () => {
                initializePreviewWorkspace();
                previewRoleState = normalizeRoleState({
                    ...(previewRoleState || currentDraftRoleState()),
                    [`${roleKey}Axes`]: previewAxisFieldValues(roleKey),
                });
                previewDirty = true;
                previewFollowsDraft = false;
                applyPreviewOutputs(previewSourceLabel ? previewSourceLabel.textContent : '');
            };

            container.addEventListener('input', syncPreviewAxisState);
            container.addEventListener('change', syncPreviewAxisState);
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
                    syncGoogleFormatChoice(matchedFamily);

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
                    syncGoogleFormatChoice(null);
                    renderedGoogleVariantFamily = '';

                    if (variantsWrap) {
                        variantsWrap.innerHTML = '';
                    }
                } else {
                    syncGoogleFormatChoice(null);
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

            manualVariants.addEventListener('change', () => {
                normalizeGoogleManualVariantsForCurrentFormat();
                syncVariantChipsFromManualInput();
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

        if (googleFormatChoice) {
            googleFormatChoice.addEventListener('click', (event) => {
                const button = event.target.closest('[data-import-format-mode]');

                if (!button || button.disabled) {
                    return;
                }

                selectedGoogleFormatMode = resolveImportFormatMode(
                    activeGoogleFamilyForImport(),
                    'google',
                    button.dataset.importFormatMode || 'static'
                );
                normalizeGoogleManualVariantsForCurrentFormat();

                const matchedFamily = activeGoogleFamilyForImport();

                renderVariantOptions(
                    matchedFamily && Array.isArray(matchedFamily.variants)
                        ? matchedFamily.variants
                        : normalizeVariantTokens(manualVariants ? manualVariants.value : ''),
                    currentGoogleImportFamily()
                );
                updateGoogleImportSummary();
            });
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
                    syncBunnyFormatChoice(null);
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
                    syncBunnyFormatChoice(matchedFamily);

                    if (shouldRenderVariants) {
                        renderBunnyVariantOptions(matchedFamily.variants || [], matchedFamily.family || familyName);
                        return;
                    }
                } else if (selectedBunnySearchFamily || hasVariantInputs) {
                    selectedBunnySearchFamily = null;
                    syncBunnyFormatChoice(null);
                    renderedBunnyVariantFamily = '';

                    if (bunnyVariantsWrap) {
                        bunnyVariantsWrap.innerHTML = '';
                    }
                } else {
                    syncBunnyFormatChoice(null);
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

            bunnyVariants.addEventListener('change', () => {
                normalizeBunnyManualVariantsForCurrentFormat();
                syncBunnyVariantChipsFromManualInput();
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

        if (bunnyFormatChoice) {
            bunnyFormatChoice.addEventListener('click', (event) => {
                const button = event.target.closest('[data-import-format-mode]');

                if (!button || button.disabled) {
                    return;
                }

                selectedBunnyFormatMode = resolveImportFormatMode(
                    activeBunnyFamilyForImport(),
                    'bunny',
                    button.dataset.importFormatMode || 'static'
                );
                normalizeBunnyManualVariantsForCurrentFormat();

                const matchedFamily = activeBunnyFamilyForImport();

                renderBunnyVariantOptions(
                    matchedFamily && Array.isArray(matchedFamily.variants)
                        ? matchedFamily.variants
                        : normalizeHostedVariantTokens(bunnyVariants ? bunnyVariants.value : ''),
                    currentBunnyImportFamily()
                );
                updateBunnyImportSummary();
            });
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
                const group = familyInput.closest('[data-upload-group]');
                updateUploadGroupAccessibility(group);
                updateGroupDetectedSuggestions(group);
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
                const group = familyInput.closest('[data-upload-group]');
                updateUploadGroupAccessibility(group);
                updateGroupDetectedSuggestions(group);
                return;
            }

            const variableField = event.target.closest('[data-upload-field="is-variable"]');

            if (variableField) {
                syncUploadVariableState(variableField.closest('[data-upload-row]'));
                return;
            }

            const rowField = event.target.closest('[data-upload-field="weight"], [data-upload-field="style"], [data-upload-axis-field]');

            if (rowField) {
                updateUploadDetectedSuggestion(rowField.closest('[data-upload-row]'));
            }
        });
    }

    function initializeTabs() {
        const groups = new Set(tabButtonElements().map((tab) => tab.getAttribute('data-tab-group')).filter(Boolean));

        groups.forEach((group) => {
            const buttons = tabButtonsForGroup(group);
            const activeTab = buttons.find((tab) => tab.classList.contains('is-active')) || buttons[0];

            if (activeTab) {
                activateTabGroup(group, activeTab.getAttribute('data-tab-target'));
            }
        });
    }

    function setOutputPanelState(panel, enabled) {
        if (!panel) {
            return;
        }

        panel.classList.toggle('is-inactive', !enabled);
    }

    function outputClassFlagInputs() {
        return Array.from(document.querySelectorAll(
            'input[type="checkbox"][name="class_output_role_heading_enabled"], ' +
            'input[type="checkbox"][name="class_output_role_body_enabled"], ' +
            'input[type="checkbox"][name="class_output_role_monospace_enabled"], ' +
            'input[type="checkbox"][name="class_output_role_alias_interface_enabled"], ' +
            'input[type="checkbox"][name="class_output_role_alias_ui_enabled"], ' +
            'input[type="checkbox"][name="class_output_role_alias_code_enabled"], ' +
            'input[type="checkbox"][name="class_output_category_sans_enabled"], ' +
            'input[type="checkbox"][name="class_output_category_serif_enabled"], ' +
            'input[type="checkbox"][name="class_output_category_mono_enabled"], ' +
            'input[type="checkbox"][name="class_output_families_enabled"]'
        ));
    }

    function outputVariableFlagInputs() {
        return Array.from(document.querySelectorAll(
            'input[type="checkbox"][name="extended_variable_weight_tokens_enabled"], ' +
            'input[type="checkbox"][name="extended_variable_role_aliases_enabled"], ' +
            'input[type="checkbox"][name="extended_variable_category_sans_enabled"], ' +
            'input[type="checkbox"][name="extended_variable_category_serif_enabled"], ' +
            'input[type="checkbox"][name="extended_variable_category_mono_enabled"]'
        ));
    }

    function activeOutputClassFlags() {
        return outputClassFlagInputs().filter((input) => !input.disabled);
    }

    function activeOutputVariableFlags() {
        return outputVariableFlagInputs().filter((input) => !input.disabled);
    }

    function currentOutputQuickModePreference() {
        return sanitizeOutputQuickModePreference(outputQuickModePreferenceInput ? outputQuickModePreferenceInput.value : '');
    }

    function setOutputQuickModePreference(mode) {
        if (!outputQuickModePreferenceInput) {
            return;
        }

        const normalizedMode = sanitizeOutputQuickModePreference(mode);
        outputQuickModePreferenceInput.value = normalizedMode || 'custom';
    }

    function currentOutputQuickModeState() {
        return {
            minimalEnabled: !!(outputMinimalPresetInput && outputMinimalPresetInput.value === '1'),
            classOutputEnabled: !!(outputMasterInputs.classes && outputMasterInputs.classes.checked),
            variableOutputEnabled: !!(outputMasterInputs.variables && outputMasterInputs.variables.checked),
            roleUsageFontWeightEnabled: !!(outputRoleWeightInput && outputRoleWeightInput.checked),
            classFlags: activeOutputClassFlags().map((input) => !!input.checked),
            variableFlags: activeOutputVariableFlags().map((input) => !!input.checked),
        };
    }

    function syncOutputQuickModeNotice(visible) {
        if (!outputQuickModeNotice) {
            return;
        }

        outputQuickModeNotice.hidden = !visible;
    }

    function deriveOutputQuickMode() {
        return normalizeOutputQuickModePreference(currentOutputQuickModePreference(), currentOutputQuickModeState());
    }

    function syncOutputQuickModePreferenceFromState() {
        const state = currentOutputQuickModeState();
        const preference = !outputQuickModeBootstrapped && initialOutputQuickModePreference
            ? sanitizeOutputQuickModePreference(initialOutputQuickModePreference)
            : currentOutputQuickModePreference();

        if (preference === 'custom') {
            setOutputQuickModePreference('custom');
            outputQuickModeBootstrapped = true;
            return;
        }

        setOutputQuickModePreference(
            normalizeOutputQuickModePreference(preference, state)
        );
        outputQuickModeBootstrapped = true;
    }

    function ensureOutputLayerCanDisable(layerKey, input) {
        if (!input || input.checked) {
            syncOutputQuickModeNotice(false);
            return true;
        }

        const state = currentOutputQuickModeState();

        if (layerKey === 'classes') {
            state.classOutputEnabled = true;
        } else if (layerKey === 'variables') {
            state.variableOutputEnabled = true;
        }

        if (canDisableOutputLayer(layerKey, state)) {
            syncOutputQuickModeNotice(false);
            return true;
        }

        input.checked = true;
        syncOutputQuickModeNotice(true);

        return false;
    }

    function syncPillOptionUi() {
        pillOptions.forEach((option) => {
            const input = option.querySelector('[data-pill-option-input]');
            option.classList.toggle('is-active', !!(input && input.checked));
        });
    }

    function setOutputAdvancedPanelState(expanded) {
        if (!outputAdvancedPanel) {
            return;
        }

        outputAdvancedPanel.hidden = !expanded;
    }

    function syncOutputQuickModeUi() {
        syncOutputQuickModePreferenceFromState();

        const mode = deriveOutputQuickMode();

        outputQuickModeInputs.forEach((input) => {
            input.checked = input.value === mode;
        });
        setOutputAdvancedPanelState(mode === 'custom');
        syncPillOptionUi();
    }

    function applyOutputQuickMode(mode) {
        const classFlags = outputClassFlagInputs();
        const variableFlags = outputVariableFlagInputs();
        const minimalMode = mode === 'minimal';
        const enableClasses = mode === 'classes' || mode === 'custom';
        const enableVariables = mode === 'variables' || mode === 'custom' || minimalMode;
        const enableClassFlags = mode === 'classes' || mode === 'custom';
        const enableVariableFlags = mode === 'variables' || mode === 'custom';

        if (outputMinimalPresetInput) {
            outputMinimalPresetInput.value = minimalMode ? '1' : '0';
        }

        setOutputQuickModePreference(mode);

        if (outputMasterInputs.classes) {
            outputMasterInputs.classes.checked = enableClasses;
        }

        if (outputMasterInputs.variables) {
            outputMasterInputs.variables.checked = enableVariables;
        }

        classFlags.forEach((input) => {
            if (!input.disabled) {
                input.checked = enableClassFlags;
            }
        });

        variableFlags.forEach((input) => {
            if (!input.disabled) {
                input.checked = enableVariableFlags;
            }
        });

        if (outputRoleWeightInput && mode !== 'custom') {
            outputRoleWeightInput.checked = false;
        }

        syncOutputSettingsUi();
    }

    function syncOutputSettingsUi() {
        setOutputPanelState(outputPanels.classes, !!(outputMasterInputs.classes && outputMasterInputs.classes.checked));
        setOutputPanelState(outputPanels.variables, !!(outputMasterInputs.variables && outputMasterInputs.variables.checked));

        outputMonoDependentInputs.forEach((input) => {
            const label = input.closest('.tasty-fonts-toggle-field');
            if (label) {
                label.classList.toggle('is-disabled', input.disabled);
            }
        });

        syncOutputQuickModeNotice(false);
        syncOutputQuickModeUi();
        updateRoleOutputs();
    }

    function syncUnicodeRangeUi() {
        if (!unicodeRangeCustomWrap) {
            return;
        }

        const customMode = unicodeRangeModeInputs.some((input) => input.checked && input.value === 'custom');
        unicodeRangeCustomWrap.hidden = !customMode;
    }

    function bindOutputSettingsControls() {
        pillOptionInputs.forEach((input) => {
            input.addEventListener('change', syncPillOptionUi);
        });

        unicodeRangeModeInputs.forEach((input) => {
            input.addEventListener('change', syncUnicodeRangeUi);
        });

        outputQuickModeInputs.forEach((input) => {
            input.addEventListener('change', () => {
                if (!input.checked) {
                    return;
                }

                applyOutputQuickMode(input.value);
                if (input.form) {
                    syncSettingsFormDirtyState(input.form);
                }
            });
        });

        [...outputClassFlagInputs(), ...outputVariableFlagInputs()].forEach((input) => {
            input.addEventListener('change', syncOutputSettingsUi);
        });

        if (outputMasterInputs.classes) {
            outputMasterInputs.classes.addEventListener('change', () => {
                if (ensureOutputLayerCanDisable('classes', outputMasterInputs.classes)) {
                    syncOutputSettingsUi();
                }
            });
        }

        if (outputMasterInputs.variables) {
            outputMasterInputs.variables.addEventListener('change', () => {
                if (ensureOutputLayerCanDisable('variables', outputMasterInputs.variables)) {
                    syncOutputSettingsUi();
                }
            });
        }

        if (outputRoleWeightInput) {
            outputRoleWeightInput.addEventListener('change', syncOutputSettingsUi);
        }

        monospaceRoleSettingInputs.forEach((input) => {
            input.addEventListener('change', () => {
                monospaceRoleEnabled = !!input.checked && !!roleMonospace && !!roleMonospaceFallback;
                syncCheckboxFields('monospace_role_enabled', !!input.checked);
                syncMonoDependentControls(!!input.checked, { enableDefaults: !!input.checked });
                syncOutputSettingsUi();
            });
        });

        syncPillOptionUi();
        syncOutputSettingsUi();
        syncUnicodeRangeUi();
    }

    function bindSettingsForms() {
        window.addEventListener('beforeunload', handleSettingsBeforeUnload);

        settingsForms.forEach((form) => {
            refreshSettingsFormBaseline(form);
            form.querySelectorAll('input, select, textarea').forEach((field) => {
                const eventName = field.matches('input[type="text"], input[type="search"], input[type="url"], input[type="number"], textarea')
                    ? 'input'
                    : 'change';

                field.addEventListener(eventName, () => {
                    syncSettingsFormDirtyState(form);
                });

                if (eventName !== 'change') {
                    field.addEventListener('change', () => {
                        syncSettingsFormDirtyState(form);
                    });
                }
            });

            form.addEventListener('submit', (event) => {
                if (!syncSettingsFormDirtyState(form)) {
                    event.preventDefault();
                    return;
                }

                settingsNavigationInFlight = true;
            });
        });
    }

    function bindDeveloperToolsControls() {
        developerConfirmForms.forEach((form) => {
            const confirmMessage = String(form.getAttribute('data-developer-confirm-message') || '').trim();

            if (!confirmMessage) {
                return;
            }

            form.addEventListener('submit', (event) => {
                const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');

                if (!armDestructiveButton(submitButton, confirmMessage)) {
                    event.preventDefault();
                }
            });
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
        bindClearableSelectControls();
        bindRolePreviewControls();
        bindGoogleImportControls();
        bindBunnyImportControls();
        bindUploadControls();
        bindOutputSettingsControls();
        bindSettingsForms();
        bindDeveloperToolsControls();

        syncDisclosureToggles();
        initToasts();
        initHelpTooltips();
        initUploadRows();
        initLibraryFiltering();
        initActivityFiltering();
        renderAllRoleWeightEditors();
        renderAllRoleAxisEditors();
        updatePreviewDynamicText();
        updatePreviewScale();
        updateRoleOutputs();
        updateGoogleImportSummary();
        updateBunnyImportSummary();
        syncImportDeliveryButtons();
        initializeTabs();
        syncAllClearableSelectButtons();
        defaultTrackedUiState = captureTrackedUiState();
        applyTrackedUiState(initialTrackedUiState);
        syncTrackedUiUrl('replace');
        syncRoleDisclosureForCurrentPage();
        syncSettingsSaveShell();
        const appliedPendingUiState = applyPendingUiState();

        if (!appliedPendingUiState && !hasTrackedUiState(initialTrackedUiState)) {
            resetInitialScrollPosition();
        }
    }

    bootstrap();
})();
