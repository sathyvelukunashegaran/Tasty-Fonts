(function () {
    // DOM references
    const config = window.TastyFontsAdmin || {};
    const strings = config.strings || {};
    const roleHeading = document.getElementById('tasty_fonts_heading_font');
    const roleBody = document.getElementById('tasty_fonts_body_font');
    const roleHeadingFallback = document.getElementById('tasty_fonts_heading_fallback');
    const roleBodyFallback = document.getElementById('tasty_fonts_body_fallback');
    const roleForm = document.querySelector('[data-role-form]');
    const roleDeployment = document.querySelector('[data-role-deployment]');
    const roleDeploymentBadge = document.querySelector('[data-role-deployment-badge]');
    const roleDeploymentPill = document.querySelector('[data-role-deployment-pill]');
    const roleDeploymentAnnouncement = document.querySelector('[data-role-deployment-announcement]');
    const headingRoleVariableCopies = Array.from(document.querySelectorAll('[data-role-variable-copy="heading"]'));
    const bodyRoleVariableCopies = Array.from(document.querySelectorAll('[data-role-variable-copy="body"]'));
    const headingFamilyVariableCopies = Array.from(document.querySelectorAll('[data-role-family-variable-copy="heading"]'));
    const bodyFamilyVariableCopies = Array.from(document.querySelectorAll('[data-role-family-variable-copy="body"]'));
    const previewCanvas = document.getElementById('tasty-fonts-preview-canvas');
    const previewTextInput = document.getElementById('tasty-fonts-preview-text');
    const previewSizeInput = document.getElementById('tasty-fonts-preview-size');
    const roleAssignButtons = Array.from(document.querySelectorAll('[data-role-assign]'));
    const deleteFamilyButtons = Array.from(document.querySelectorAll('[data-delete-family]'));
    const roleHeadingPreviews = Array.from(document.querySelectorAll('[data-role-preview="heading"]'));
    const roleBodyPreviews = Array.from(document.querySelectorAll('[data-role-preview="body"]'));
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
    const libraryFilteredEmpty = document.getElementById('tasty-fonts-library-empty-filtered');
    const googleSearch = document.getElementById('tasty-fonts-google-search');
    const adobeProjectId = document.getElementById('tasty-fonts-adobe-project-id');
    const googleResults = document.getElementById('tasty-fonts-google-results');
    const manualFamily = document.getElementById('tasty-fonts-manual-family');
    const manualVariants = document.getElementById('tasty-fonts-manual-variants');
    const selectedFamily = document.getElementById('tasty-fonts-selected-family');
    const selectedFamilyPreview = document.getElementById('tasty-fonts-selected-family-preview');
    const importFilesEstimate = document.getElementById('tasty-fonts-import-files-estimate');
    const importSizeEstimate = document.getElementById('tasty-fonts-import-size-estimate');
    const importSelectionSummary = document.getElementById('tasty-fonts-import-selection-summary');
    const variantsWrap = document.getElementById('tasty-fonts-google-variants');
    const variantQuickSelectButtons = Array.from(document.querySelectorAll('[data-google-variant-select]'));
    const importButton = document.getElementById('tasty-fonts-import-submit');
    const importStatus = document.getElementById('tasty-fonts-import-status');
    const uploadForm = document.getElementById('tasty-fonts-upload-form');
    const uploadGroupsWrap = document.getElementById('tasty-fonts-upload-groups');
    const uploadGroupTemplate = document.getElementById('tasty-fonts-upload-group-template');
    const uploadRowTemplate = document.getElementById('tasty-fonts-upload-row-template');
    const uploadAddFamily = document.getElementById('tasty-fonts-upload-add-family');
    const uploadSubmit = document.getElementById('tasty-fonts-upload-submit');
    const uploadStatus = document.getElementById('tasty-fonts-upload-status');
    const addFontsPanelToggle = document.getElementById('tasty-fonts-add-font-panel-toggle');
    const toastItems = Array.from(document.querySelectorAll('[data-toast]'));
    const helpButtons = Array.from(document.querySelectorAll('[data-help-tooltip]'));
    const helpTooltipLayer = document.getElementById('tasty-fonts-help-tooltip-layer');
    const activityActorFilter = document.querySelector('[data-activity-actor-filter]');
    const activitySearch = document.querySelector('[data-activity-search]');
    const activityCount = document.querySelector('[data-activity-count]');
    const activityList = document.querySelector('[data-activity-list]');
    const activityFilteredEmpty = document.getElementById('tasty-fonts-activity-empty-filtered');

    let selectedSearchFamily = null;
    let searchResults = [];
    let searchTimer = 0;
    let importInFlight = false;
    let uploadInFlight = false;
    let activeHelpButton = null;
    let nextUploadGroupIndex = uploadGroupsWrap ? uploadGroupsWrap.querySelectorAll('[data-upload-group]').length : 0;
    let nextUploadRowIndex = uploadGroupsWrap ? uploadGroupsWrap.querySelectorAll('[data-upload-row]').length : 0;
    let activeLibrarySourceFilter = 'all';
    let syncingGoogleVariants = false;
    let renderedGoogleVariantFamily = '';
    let roleDraftSaveInFlight = false;

    // Shared helpers
    function slugify(value) {
        return value.toLowerCase().replace(/[^a-z0-9\-_]+/g, '-').replace(/^-+|-+$/g, '') || 'font';
    }

    function buildStack(family, fallback) {
        return `"${family}", ${fallback}`;
    }

    function getString(key, fallback) {
        return strings[key] || fallback;
    }

    function getElementValue(element, fallback) {
        return element ? element.value : fallback;
    }

    function formatMessage(template, replacements) {
        let sequentialIndex = 0;

        return (template || '').replace(/%(?:(\d+)\$)?[sd]/g, (match, index) => {
            const replacementIndex = index ? Number(index) - 1 : sequentialIndex++;
            const replacement = replacements[replacementIndex];

            return replacement === undefined ? match : String(replacement);
        });
    }

    function getPayloadMessage(payload, fallback) {
        return payload && payload.data && payload.data.message ? payload.data.message : fallback;
    }

    function getErrorMessage(error, fallback) {
        return error instanceof Error && error.message ? error.message : fallback;
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

    function removeGooglePreviewStylesheet() {
        const stylesheet = googlePreviewStylesheet();

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

    function reloadPageSoon(delay = 900) {
        window.setTimeout(() => {
            window.location.reload();
        }, delay);
    }

    function roleFieldSnapshot() {
        return {
            heading: getElementValue(roleHeading, ''),
            body: getElementValue(roleBody, ''),
            headingFallback: getElementValue(roleHeadingFallback, 'sans-serif'),
            bodyFallback: getElementValue(roleBodyFallback, 'sans-serif')
        };
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
    }

    function setRoleDraftSavingState(isSaving) {
        roleDraftSaveInFlight = isSaving;

        roleAssignButtons.forEach((button) => {
            button.disabled = isSaving;
            button.setAttribute('aria-busy', isSaving ? 'true' : 'false');
        });

        if (roleForm) {
            roleForm.classList.toggle('is-saving', isSaving);
            roleForm.setAttribute('aria-busy', isSaving ? 'true' : 'false');
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
            roleDeploymentPill.className = `tasty-fonts-role-status-pill${badgeClass ? ` ${badgeClass}` : ''}`;
            roleDeploymentPill.setAttribute('data-help-tooltip', tooltip);
            roleDeploymentPill.setAttribute('title', tooltip);
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

    function normalizeFamilyKey(value) {
        return String(value || '').trim().toLowerCase();
    }

    function findGoogleFamilyMatch(familyName) {
        const normalized = String(familyName || '').trim().toLowerCase();

        if (!normalized) {
            return null;
        }

        return searchResults.find((item) => String(item.family || '').trim().toLowerCase() === normalized) || null;
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
            importFilesEstimate.textContent = formatMessage(
                getString('importEstimateFiles', '%1$d file%2$s selected'),
                [hasFamily ? variantCount : 0, variantCount === 1 ? '' : 's']
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
                importSelectionSummary.textContent = formatMessage(
                    getString('importSelectionSummaryManual', '%1$d Variant%2$s Selected'),
                    [variantCount, variantCount === 1 ? '' : 's']
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

        stack = document.createElement('div');
        stack.className = 'tasty-fonts-toast-stack';
        stack.setAttribute('aria-live', 'polite');
        stack.setAttribute('aria-atomic', 'true');
        document.body.appendChild(stack);

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
        if (!helpTooltipLayer || !helpButtons.length) {
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

    async function postAjax(action, nonce, fields) {
        const body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', nonce || '');

        Object.entries(fields).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                value.forEach((item) => body.append(key, item));
                return;
            }

            body.set(key, value);
        });

        const response = await fetch(config.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        });

        return response.json();
    }

    async function postMultipartAjax(formData, onProgress) {
        return new Promise((resolve, reject) => {
            const request = new XMLHttpRequest();

            request.open('POST', config.ajaxUrl || '', true);

            request.upload.addEventListener('progress', (event) => {
                if (!event.lengthComputable || typeof onProgress !== 'function') {
                    return;
                }

                onProgress(Math.round((event.loaded / event.total) * 100));
            });

            request.addEventListener('load', () => {
                let payload = null;

                try {
                    payload = JSON.parse(request.responseText || '{}');
                } catch (error) {
                    reject(new Error(getString('uploadError', 'The font upload failed.')));
                    return;
                }

                if (request.status >= 200 && request.status < 300) {
                    resolve(payload);
                    return;
                }

                reject(
                    new Error(
                        getPayloadMessage(payload, getString('uploadError', 'The font upload failed.'))
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
        const heading = getElementValue(roleHeading, '');
        const body = getElementValue(roleBody, '');
        const headingFallbackValue = getElementValue(roleHeadingFallback, 'sans-serif');
        const bodyFallbackValue = getElementValue(roleBodyFallback, 'sans-serif');

        return {
            heading,
            body,
            headingFallback: headingFallbackValue,
            bodyFallback: bodyFallbackValue,
            headingSlug: slugify(heading),
            bodySlug: slugify(body),
            headingVariable: 'var(--font-heading)',
            bodyVariable: 'var(--font-body)',
            headingFamilyVariable: heading ? `var(--font-${slugify(heading)})` : '',
            bodyFamilyVariable: body ? `var(--font-${slugify(body)})` : '',
            headingStack: buildStack(heading, headingFallbackValue),
            bodyStack: buildStack(body, bodyFallbackValue)
        };
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

        headingFamilyVariableCopies.forEach((button) => {
            button.textContent = data.headingFamilyVariable;
            button.setAttribute('data-copy-text', data.headingFamilyVariable);
            button.setAttribute('title', `Heading family variable: ${data.headingFamilyVariable}. Role alias: ${data.headingVariable}. Resolved stack: ${data.headingStack}`);
        });

        bodyFamilyVariableCopies.forEach((button) => {
            button.textContent = data.bodyFamilyVariable;
            button.setAttribute('data-copy-text', data.bodyFamilyVariable);
            button.setAttribute('title', `Body family variable: ${data.bodyFamilyVariable}. Role alias: ${data.bodyVariable}. Resolved stack: ${data.bodyStack}`);
        });

        roleHeadingPreviews.forEach((element) => {
            element.style.fontFamily = data.headingStack;
        });

        roleBodyPreviews.forEach((element) => {
            element.style.fontFamily = data.bodyStack;
        });

        roleAssignButtons.forEach((button) => {
            const family = button.getAttribute('data-font-family') || '';
            const role = button.getAttribute('data-role-assign');
            const isCurrent = (role === 'heading' && family === data.heading) || (role === 'body' && family === data.body);
            const label = button.querySelector('.tasty-fonts-role-assign-label');

            button.classList.toggle('is-current', isCurrent);
            button.setAttribute('aria-pressed', isCurrent ? 'true' : 'false');

            if (label) {
                label.textContent = isCurrent ? (button.dataset.activeLabel || '') : (button.dataset.idleLabel || '');
            }
        });

        deleteFamilyButtons.forEach((button) => {
            const family = button.getAttribute('data-delete-family') || '';
            const isHeading = family !== '' && family === data.heading;
            const isBody = family !== '' && family === data.body;
            let blockedMessage = '';

            if (isHeading && isBody) {
                blockedMessage = button.dataset.deleteBlockedBoth || '';
            } else if (isHeading) {
                blockedMessage = button.dataset.deleteBlockedHeading || '';
            } else if (isBody) {
                blockedMessage = button.dataset.deleteBlockedBody || '';
            }

            button.classList.toggle('is-disabled', blockedMessage !== '');
            button.setAttribute('aria-disabled', blockedMessage !== '' ? 'true' : 'false');
            button.setAttribute('title', blockedMessage || button.dataset.deleteReadyTitle || '');

            if (blockedMessage !== '') {
                button.setAttribute('data-delete-blocked', blockedMessage);
                return;
            }

            button.removeAttribute('data-delete-blocked');
        });

        if (outputNames) {
            outputNames.value = `${data.heading}\n${data.body}`;
        }

        if (outputStacks) {
            outputStacks.value = `${data.headingStack}\n${data.bodyStack}`;
        }

        const variableSnippet = [
            ':root {',
            `  --font-${data.headingSlug}: ${data.headingStack};`,
            `  --font-${data.bodySlug}: ${data.bodyStack};`,
            `  --font-heading: var(--font-${data.headingSlug});`,
            `  --font-body: var(--font-${data.bodySlug});`,
            '}'
        ].join('\n');

        if (outputVars) {
            outputVars.value = variableSnippet;
        }

        if (outputUsage) {
            outputUsage.value = [
                variableSnippet,
                '',
                'body {',
                '  font-family: var(--font-body);',
                '}',
                '',
                'h1, h2, h3, h4, h5, h6 {',
                '  font-family: var(--font-heading);',
                '}'
            ].join('\n');
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

    function buildFallbackSavedMessage(family, message) {
        return message || formatMessage(getString('fallbackSaved', 'Saved fallback for %1$s.'), [family]);
    }

    function buildFallbackErrorMessage(error) {
        return getErrorMessage(error, getString('fallbackSaveError', 'The fallback could not be saved.'));
    }

    function buildRoleDraftErrorMessage(error) {
        return getErrorMessage(error, getString('rolesDraftSaveError', 'The role draft could not be saved.'));
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

        if (!family || !config.saveFallbackNonce || previousValue === nextValue) {
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
            const payload = await postAjax('tasty_fonts_save_family_fallback', config.saveFallbackNonce, {
                family,
                fallback: nextValue
            });

            if (!payload.success) {
                throw new Error(getPayloadMessage(payload, getString('fallbackSaveError', 'The fallback could not be saved.')));
            }

            const data = payload.data || {};
            const savedFallback = data.fallback || nextValue;
            const message = buildFallbackSavedMessage(family, data.message);

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

    async function saveRoleDraft(snapshotBeforeChange) {
        if (!config.saveRolesNonce || !window.fetch || roleDraftSaveInFlight) {
            return false;
        }

        setRoleDraftSavingState(true);

        try {
            const payload = await postAjax('tasty_fonts_save_role_draft', config.saveRolesNonce, {
                heading: getElementValue(roleHeading, ''),
                body: getElementValue(roleBody, ''),
                heading_fallback: getElementValue(roleHeadingFallback, 'sans-serif'),
                body_fallback: getElementValue(roleBodyFallback, 'sans-serif')
            });

            if (!payload.success) {
                throw new Error(getPayloadMessage(payload, getString('rolesDraftSaveError', 'The role draft could not be saved.')));
            }

            const data = payload.data || {};
            const roles = data.roles || {};

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

            updateRoleOutputs();
            syncRoleDeploymentState(data.role_deployment || null);
            showToast(data.message || getString('rolesDraftSaved', 'Role draft saved.'), 'success');
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

    function copyText(text, button, options = {}) {
        if (!text || !navigator.clipboard) {
            return;
        }

        navigator.clipboard.writeText(text).then(() => {
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
            const title = document.createElement('h3');
            const preview = document.createElement('div');
            const meta = document.createElement('div');
            const category = document.createElement('span');
            const variants = document.createElement('span');
            const fallback = googlePreviewFallback(item.category);

            card.className = 'tasty-fonts-search-card';
            card.dataset.family = item.family;
            card.setAttribute('role', 'button');
            card.setAttribute('tabindex', '0');
            card.setAttribute('aria-label', formatMessage(getString('searchResultSelectLabel', 'Select %s'), [item.family]));
            card.setAttribute('aria-pressed', !!selectedSearchFamily && selectedSearchFamily.family === item.family ? 'true' : 'false');
            card.classList.toggle('is-active', !!selectedSearchFamily && selectedSearchFamily.family === item.family);

            title.className = 'tasty-fonts-search-card-title';
            title.textContent = item.family;

            preview.className = 'tasty-fonts-search-card-preview';
            preview.textContent = googlePreviewText();
            preview.style.fontFamily = `"${item.family}", ${fallback}`;

            meta.className = 'tasty-fonts-search-card-meta tasty-fonts-muted';
            category.textContent = item.category || fallback;
            variants.textContent = `${(item.variants || []).length} variant(s)`;

            meta.append(category, variants);
            card.append(title, preview, meta);
            googleResults.appendChild(card);
        });

        const matchedFamily = findGoogleFamilyMatch(currentGoogleImportFamily());

        if (matchedFamily) {
            selectedSearchFamily = matchedFamily;

            if (!availableVariantInputs().length) {
                renderVariantOptions(matchedFamily.variants || [], matchedFamily.family || '');
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
        renderVariantOptions(selectedSearchFamily ? selectedSearchFamily.variants : [], familyName);
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

        formData.append('action', 'tasty_fonts_upload_local');
        formData.append('nonce', config.uploadNonce || '');

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
        if (!uploadForm || !uploadGroupsWrap || !config.uploadNonce || uploadInFlight) {
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

            const payload = await postMultipartAjax(formData, (progress) => {
                setStatus(
                    uploadStatus,
                    formatMessage(getString('uploadProgress', 'Uploading files… %1$d%%'), [progress]),
                    'progress',
                    progress
                );
            });

            if (!payload.success) {
                throw new Error(getPayloadMessage(payload, getString('uploadError', 'The font upload failed.')));
            }

            const data = payload.data || {};
            const results = Array.isArray(data.rows) ? data.rows : [];
            applyUploadResults(rows, results);

            setStatus(uploadStatus, data.message || getString('uploadSuccess', 'Upload complete. Refreshing the library…'), 'success', 100);

            if ((data.summary && data.summary.imported > 0) || (Array.isArray(data.families) && data.families.length > 0)) {
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
        const payload = await postAjax('tasty_fonts_search_google', config.searchNonce, { query });

        if (!payload.success) {
            updateGooglePreviewStylesheet([]);
            googleResults.innerHTML = `<div class="tasty-fonts-empty">${getPayloadMessage(payload, getString('importError', 'Request failed.'))}</div>`;
            return;
        }

        renderSearchResults(payload.data.items || []);
    }

    function selectedVariantTokens() {
        if (variantsWrap && variantsWrap.querySelectorAll('input').length > 0) {
            return Array.from(variantsWrap.querySelectorAll('input:checked')).map((input) => input.value);
        }

        return (manualVariants && manualVariants.value ? manualVariants.value.split(',') : []).map((item) => item.trim()).filter(Boolean);
    }

    function setImportBusyState(isBusy, idleLabel) {
        if (!importButton) {
            return;
        }

        importButton.disabled = isBusy;

        if (isBusy) {
            importButton.setAttribute('aria-busy', 'true');
            importButton.textContent = getString('importButtonBusy', 'Importing…');
            return;
        }

        importButton.removeAttribute('aria-busy');
        importButton.textContent = idleLabel || getString('importButtonIdle', 'Import and Self-Host');
    }

    async function importGoogleVariant(family, variant, index, total) {
        const progressMessage = formatMessage(
            getString('importProgress', 'Importing %1$s: %2$d of %3$d (%4$s)…'),
            [family, index + 1, total, variant]
        );

        setStatus(importStatus, progressMessage, 'progress', Math.round((index / total) * 100));

        const payload = await postAjax('tasty_fonts_import_google', config.importNonce, {
            family,
            variant_tokens: variant,
            'variants[]': [variant]
        });

        return { payload, progressMessage };
    }

    async function importGoogleFont() {
        const family = manualFamily ? manualFamily.value.trim() : '';

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

        const total = variants.length || 1;
        const originalLabel = importButton ? importButton.textContent : '';
        let importedCount = 0;
        let skippedCount = 0;

        importInFlight = true;
        setImportBusyState(true, originalLabel);

        try {
            for (let index = 0; index < total; index += 1) {
                const variant = variants[index] || 'regular';
                const { payload, progressMessage } = await importGoogleVariant(family, variant, index, total);

                if (!payload.success) {
                    const message = getPayloadMessage(payload, getString('importError', 'Import failed.'));

                    setStatus(importStatus, message, 'error');
                    showToast(message, 'error');
                    return;
                }

                const result = payload.data || {};
                const importedVariants = Array.isArray(result.imported_variants) ? result.imported_variants : [];
                const skippedVariants = Array.isArray(result.skipped_variants) ? result.skipped_variants : [];

                importedCount += importedVariants.length;
                skippedCount += skippedVariants.length;

                setStatus(importStatus, progressMessage, 'progress', Math.round(((index + 1) / total) * 100));
            }

            if (importedCount > 0) {
                const summary = formatMessage(
                    getString('importSummary', 'Imported %1$d variant%2$s. %3$d skipped. Reloading…'),
                    [importedCount, importedCount === 1 ? '' : 's', skippedCount]
                );

                setStatus(importStatus, summary, 'success', 100);
                showToast(summary, 'success');
                reloadPageSoon();

                return;
            }

            const existingMessage = formatMessage(
                getString('importAlreadyExists', '%s already exists in the library for the selected variants.'),
                [family]
            );

            setStatus(importStatus, existingMessage, 'error');
            showToast(existingMessage, 'error');
        } catch (error) {
            const message = getErrorMessage(error, getString('importError', 'Import failed.'));

            setStatus(importStatus, message, 'error');
            showToast(message, 'error');
        } finally {
            importInFlight = false;
            setImportBusyState(false, originalLabel);
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

        row.hidden = false;

        const toggle = row.querySelector('[data-disclosure-toggle]');

        if (!toggle || toggle.dataset.searchExpanded !== 'true') {
            return;
        }

        delete toggle.dataset.searchExpanded;
        setDisclosureState(toggle, false);
    }

    // Library filtering
    function initLibraryFiltering() {
        if (!librarySearch && !librarySourceFilter) {
            return;
        }

        const libraryRows = Array.from(document.querySelectorAll('[data-font-row]'));
        activeLibrarySourceFilter = librarySourceFilter ? (librarySourceFilter.value || 'all') : 'all';

        function rowMatchesSource(row, sourceFilter) {
            if (!row || !sourceFilter || sourceFilter === 'all') {
                return true;
            }

            const sourceTokens = (row.getAttribute('data-font-sources') || '').split(/\s+/).filter(Boolean);

            return sourceTokens.includes(sourceFilter);
        }

        const applyLibraryFilter = () => {
            const query = librarySearch ? librarySearch.value.trim().toLowerCase() : '';
            let visibleCount = 0;

            libraryRows.forEach((row) => {
                const name = row.getAttribute('data-font-name') || '';
                const matchesQuery = !query || name.includes(query);
                const matchesSource = rowMatchesSource(row, activeLibrarySourceFilter);
                const matches = matchesQuery && matchesSource;

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

        setDisclosureState(disclosureToggle, nextExpanded);

        if (nextExpanded && disclosureToggle.getAttribute('data-disclosure-toggle') === 'tasty-fonts-add-font-panel') {
            window.setTimeout(focusAddFontPanel, 0);
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

        window.setTimeout(focusAddFontPanel, 0);
        return true;
    }

    function handleTabClick(event) {
        const tab = event.target.closest('[data-tab-group][data-tab-target]');

        if (tab) {
            activateTabGroup(tab.getAttribute('data-tab-group'), tab.getAttribute('data-tab-target'));
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
        copyText(target ? target.value : '', copyTarget);
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

        if ((role === 'heading' && snapshotBeforeChange.heading === family) || (role === 'body' && snapshotBeforeChange.body === family)) {
            return true;
        }

        if (role === 'heading' && roleHeading) {
            roleHeading.value = family;
        }

        if (role === 'body' && roleBody) {
            roleBody.value = family;
        }

        updateRoleOutputs();
        void saveRoleDraft(snapshotBeforeChange);
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

    function handleSearchCardClick(event) {
        const searchCard = event.target.closest('.tasty-fonts-search-card');

        if (!searchCard) {
            return false;
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

        if (handleCopyClick(event)) {
            return;
        }

        if (handleRoleAssignClick(event)) {
            return;
        }

        if (handleDeleteFamilyClick(event)) {
            return;
        }

        if (handleSearchCardClick(event)) {
            return;
        }

        if (handleGoogleVariantQuickSelect(event)) {
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
                if (!config.saveFallbackNonce || !window.fetch) {
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

    function bindRolePreviewControls() {
        [roleHeading, roleBody, roleHeadingFallback, roleBodyFallback].forEach((element) => {
            if (element) {
                element.addEventListener('change', updateRoleOutputs);
            }
        });

        if (previewTextInput) {
            previewTextInput.addEventListener('input', updatePreviewDynamicText);
            previewTextInput.addEventListener('input', updateGoogleImportSummary);
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
                const familyName = manualFamily.value.trim();
                const matchedFamily = findGoogleFamilyMatch(familyName);
                const hasVariantInputs = !!(variantsWrap && variantsWrap.querySelector('input[type="checkbox"]'));

                if (matchedFamily) {
                    const shouldRenderVariants = !selectedSearchFamily
                        || selectedSearchFamily.family !== matchedFamily.family
                        || !hasVariantInputs;

                    selectedSearchFamily = matchedFamily;

                    if (shouldRenderVariants) {
                        renderVariantOptions(matchedFamily.variants || [], matchedFamily.family || '');
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
        document.addEventListener('click', handleDocumentClick);
        document.addEventListener('keydown', (event) => {
            if (handleSearchCardKeydown(event)) {
                return;
            }

            handleTabKeydown(event);
        });

        bindFamilyFallbackControls();
        bindRolePreviewControls();
        bindGoogleImportControls();
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
        initializeTabs();
    }

    bootstrap();
})();
