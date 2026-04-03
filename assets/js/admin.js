(function () {
    // DOM references
    const config = window.EtchFontsAdmin || {};
    const strings = config.strings || {};
    const roleHeading = document.getElementById('etch_fonts_heading_font');
    const roleBody = document.getElementById('etch_fonts_body_font');
    const roleHeadingFallback = document.getElementById('etch_fonts_heading_fallback');
    const roleBodyFallback = document.getElementById('etch_fonts_body_fallback');
    const headingStack = document.getElementById('etch-fonts-role-heading-stack');
    const bodyStack = document.getElementById('etch-fonts-role-body-stack');
    const previewCanvas = document.getElementById('etch-fonts-preview-canvas');
    const previewTextInput = document.getElementById('etch-fonts-preview-text');
    const previewSizeInput = document.getElementById('etch-fonts-preview-size');
    const roleHeadingPreviews = Array.from(document.querySelectorAll('[data-role-preview="heading"]'));
    const roleBodyPreviews = Array.from(document.querySelectorAll('[data-role-preview="body"]'));
    const previewDynamicText = Array.from(document.querySelectorAll('[data-preview-dynamic-text], .etch-fonts-preview-dynamic-text'));
    const previewTabs = Array.from(document.querySelectorAll('[data-preview-tab]'));
    const previewPanels = Array.from(document.querySelectorAll('[data-preview-panel]'));
    const studioTabs = Array.from(document.querySelectorAll('[data-studio-tab]'));
    const studioPanels = Array.from(document.querySelectorAll('[data-studio-panel]'));
    const outputNames = document.getElementById('etch-fonts-output-names');
    const outputStacks = document.getElementById('etch-fonts-output-stacks');
    const outputVars = document.getElementById('etch-fonts-output-vars');
    const outputUsage = document.getElementById('etch-fonts-output-usage');
    const outputTabs = Array.from(document.querySelectorAll('[data-output-tab]'));
    const outputPanels = Array.from(document.querySelectorAll('[data-output-panel]'));
    const disclosureToggles = Array.from(document.querySelectorAll('[data-disclosure-toggle]'));
    const librarySearch = document.getElementById('etch-fonts-library-search');
    const googleSearch = document.getElementById('etch-fonts-google-search');
    const googleResults = document.getElementById('etch-fonts-google-results');
    const manualFamily = document.getElementById('etch-fonts-manual-family');
    const manualVariants = document.getElementById('etch-fonts-manual-variants');
    const selectedFamily = document.getElementById('etch-fonts-selected-family');
    const variantsWrap = document.getElementById('etch-fonts-google-variants');
    const importButton = document.getElementById('etch-fonts-import-submit');
    const importStatus = document.getElementById('etch-fonts-import-status');
    const addFontTabs = Array.from(document.querySelectorAll('[data-add-font-tab]'));
    const addFontPanels = Array.from(document.querySelectorAll('[data-add-font-panel]'));
    const uploadForm = document.getElementById('etch-fonts-upload-form');
    const uploadGroupsWrap = document.getElementById('etch-fonts-upload-groups');
    const uploadGroupTemplate = document.getElementById('etch-fonts-upload-group-template');
    const uploadRowTemplate = document.getElementById('etch-fonts-upload-row-template');
    const uploadAddFamily = document.getElementById('etch-fonts-upload-add-family');
    const uploadSubmit = document.getElementById('etch-fonts-upload-submit');
    const uploadStatus = document.getElementById('etch-fonts-upload-status');
    const toastItems = Array.from(document.querySelectorAll('[data-toast]'));
    const helpButtons = Array.from(document.querySelectorAll('[data-help-tooltip]'));
    const helpTooltipLayer = document.getElementById('etch-fonts-help-tooltip-layer');

    let selectedSearchFamily = null;
    let searchResults = [];
    let searchTimer = 0;
    let importInFlight = false;
    let uploadInFlight = false;
    let activeHelpButton = null;
    let nextUploadGroupIndex = uploadGroupsWrap ? uploadGroupsWrap.querySelectorAll('[data-upload-group]').length : 0;
    let nextUploadRowIndex = uploadGroupsWrap ? uploadGroupsWrap.querySelectorAll('[data-upload-row]').length : 0;

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
        return (template || '').replace(/%(\d+)\$[sd]/g, (match, index) => {
            const replacement = replacements[Number(index) - 1];

            return replacement === undefined ? match : String(replacement);
        });
    }

    function getPayloadMessage(payload, fallback) {
        return payload && payload.data && payload.data.message ? payload.data.message : fallback;
    }

    function getErrorMessage(error, fallback) {
        return error instanceof Error && error.message ? error.message : fallback;
    }

    function reloadPageSoon(delay = 900) {
        window.setTimeout(() => {
            window.location.reload();
        }, delay);
    }

    function getFallbackSelector(family) {
        return document.querySelector(`.etch-fonts-fallback-selector[data-font-family="${CSS.escape(family)}"]`);
    }

    function updateSelectedFamilyLabel(familyName) {
        if (!selectedFamily) {
            return;
        }

        selectedFamily.textContent = familyName || getString('selectFamily', 'Select a family from search results or type one manually.');
    }

    function getDisclosureTarget(toggle) {
        if (!toggle) {
            return null;
        }

        const targetId = toggle.getAttribute('data-disclosure-toggle');

        return targetId ? document.getElementById(targetId) : null;
    }

    function activateTabSet(tabs, panels, tabAttribute, panelAttribute, key) {
        tabs.forEach((tab) => {
            const isActive = tab.getAttribute(tabAttribute) === key;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            const isActive = panel.getAttribute(panelAttribute) === key;
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
        const activeTab = addFontTabs.find((tab) => tab.classList.contains('is-active'));
        const activeKey = activeTab ? activeTab.getAttribute('data-add-font-tab') : 'google';

        if (activeKey === 'upload') {
            const firstFamilyInput = uploadGroupsWrap ? uploadGroupsWrap.querySelector('[data-upload-group-field="family"]') : null;

            if (firstFamilyInput) {
                firstFamilyInput.focus();
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

    function activateOutputTab(key) {
        activateTabSet(outputTabs, outputPanels, 'data-output-tab', 'data-output-panel', key);
    }

    function activatePreviewTab(key) {
        activateTabSet(previewTabs, previewPanels, 'data-preview-tab', 'data-preview-panel', key);
    }

    function activateStudioTab(key) {
        activateTabSet(studioTabs, studioPanels, 'data-studio-tab', 'data-studio-panel', key);
    }

    function activateAddFontTab(key) {
        activateTabSet(addFontTabs, addFontPanels, 'data-add-font-tab', 'data-add-font-panel', key);
    }

    // Toasts and tooltips
    function dismissToast(toast) {
        if (!toast || !toast.parentNode) {
            return;
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
        let stack = document.querySelector('.etch-fonts-toast-stack');

        if (stack) {
            return stack;
        }

        stack = document.createElement('div');
        stack.className = 'etch-fonts-toast-stack';
        stack.setAttribute('aria-live', 'polite');
        stack.setAttribute('aria-atomic', 'true');
        document.body.appendChild(stack);

        return stack;
    }

    function showToast(message, tone) {
        if (!message) {
            return;
        }

        const stack = ensureToastStack();
        const toast = document.createElement('div');
        const dismiss = document.createElement('button');
        const text = document.createElement('div');

        toast.className = `etch-fonts-toast is-${tone === 'error' ? 'error' : 'success'}`;
        toast.setAttribute('data-toast', '');
        toast.setAttribute('data-toast-tone', tone === 'error' ? 'error' : 'success');
        toast.setAttribute('role', tone === 'error' ? 'alert' : 'status');

        text.className = 'etch-fonts-toast-message';
        text.textContent = message;

        dismiss.type = 'button';
        dismiss.className = 'etch-fonts-toast-dismiss';
        dismiss.setAttribute('data-toast-dismiss', '');
        dismiss.setAttribute('aria-label', 'Dismiss notification');
        dismiss.innerHTML = '<span aria-hidden="true">&times;</span>';

        toast.appendChild(text);
        toast.appendChild(dismiss);
        stack.appendChild(toast);

        window.setTimeout(() => {
            dismissToast(toast);
        }, tone === 'error' ? 6000 : 3200);
    }

    function initToasts() {
        if (!toastItems.length) {
            return;
        }

        toastItems.forEach((toast) => {
            const tone = toast.getAttribute('data-toast-tone');
            const timeout = tone === 'error' ? 6000 : 3200;

            window.setTimeout(() => {
                dismissToast(toast);
            }, timeout);
        });

        const params = new URLSearchParams(window.location.search);
        const ephemeralKeys = [
            'settings_saved',
            'google_key_saved',
            'google_key_cleared',
            'fallback_saved',
            'roles_saved',
            'rescan',
            'log_cleared',
            'family_deleted',
            'etch_fonts_error'
        ];
        let changed = false;

        ephemeralKeys.forEach((key) => {
            if (params.has(key)) {
                params.delete(key);
                changed = true;
            }
        });

        if (!changed || !window.history.replaceState) {
            return;
        }

        const query = params.toString();
        const nextUrl = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`;
        window.history.replaceState({}, '', nextUrl);
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

    function hideHelpTooltip() {
        if (!helpTooltipLayer) {
            return;
        }

        if (activeHelpButton) {
            activeHelpButton.setAttribute('aria-expanded', 'false');
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
        }

        activeHelpButton = button;
        activeHelpButton.setAttribute('aria-expanded', 'true');
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

            button.addEventListener('mouseenter', () => showHelpTooltip(button));
            button.addEventListener('focus', () => showHelpTooltip(button));
            button.addEventListener('mouseleave', () => {
                if (document.activeElement !== button) {
                    hideHelpTooltip();
                }
            });
            button.addEventListener('blur', () => {
                window.setTimeout(() => {
                    if (document.activeElement !== button) {
                        hideHelpTooltip();
                    }
                }, 0);
            });
            button.addEventListener('click', (event) => {
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
        text.className = 'etch-fonts-import-status-message';
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
            track.className = 'etch-fonts-import-progress-track';
            bar.className = 'etch-fonts-import-progress-bar';
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
            headingStack: buildStack(heading, headingFallbackValue),
            bodyStack: buildStack(body, bodyFallbackValue)
        };
    }

    function updateRoleOutputs() {
        const data = currentRoleData();

        if (headingStack) {
            headingStack.textContent = data.headingStack;
        }

        if (bodyStack) {
            bodyStack.textContent = data.bodyStack;
        }

        roleHeadingPreviews.forEach((element) => {
            element.style.fontFamily = data.headingStack;
        });

        roleBodyPreviews.forEach((element) => {
            element.style.fontFamily = data.bodyStack;
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
            : 'The quick brown fox jumps over the lazy dog. 1234567890';

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

        previewCanvas.style.setProperty('--etch-preview-base', `${safeSize}px`);
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

    function syncFamilyFallbackSaveState(form) {
        if (!form) {
            return;
        }

        const selector = form.querySelector('.etch-fonts-fallback-selector');
        const button = form.querySelector('[data-family-fallback-save]');

        if (!selector || !button) {
            return;
        }

        const isDirty = (selector.dataset.savedValue || 'sans-serif') !== (selector.value || 'sans-serif');
        button.disabled = !isDirty;
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
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }

        if (row) {
            row.classList.add('is-saving');
        }

        try {
            const payload = await postAjax('etch_fonts_save_family_fallback', config.saveFallbackNonce, {
                family,
                fallback: nextValue
            });

            if (!payload.success) {
                throw new Error(getPayloadMessage(payload, getString('fallbackSaveError', 'The fallback could not be saved.')));
            }

            const data = payload.data || {};
            const savedFallback = data.fallback || nextValue;

            selector.value = savedFallback;
            selector.dataset.savedValue = savedFallback;
            updateInlineStackPreview(family);
            syncFamilyFallbackSaveState(saveForm);
            showToast(
                data.message || formatMessage(getString('fallbackSaved', 'Saved fallback for %1$s.'), [family]),
                'success'
            );
            return true;
        } catch (error) {
            selector.value = previousValue;
            updateInlineStackPreview(family);
            syncFamilyFallbackSaveState(saveForm);
            showToast(
                getErrorMessage(error, getString('fallbackSaveError', 'The fallback could not be saved.')),
                'error'
            );
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

    function copyText(text, button) {
        if (!text || !navigator.clipboard) {
            return;
        }

        navigator.clipboard.writeText(text).then(() => {
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
    function renderVariantOptions(variants) {
        if (!variantsWrap) {
            return;
        }

        variantsWrap.innerHTML = '';

        (variants || []).forEach((variant, index) => {
            const label = document.createElement('label');
            label.innerHTML = `<input type="checkbox" value="${variant}" ${index < 2 || variant === 'regular' ? 'checked' : ''}> <span>${variant}</span>`;
            variantsWrap.appendChild(label);
        });
    }

    function renderSearchResults(items) {
        if (!googleResults) {
            return;
        }

        searchResults = items || [];
        googleResults.innerHTML = '';

        if (!searchResults.length) {
            googleResults.innerHTML = `<div class="etch-fonts-empty">${getString('searchEmpty', 'No Google Fonts families matched that search.')}</div>`;
            return;
        }

        searchResults.forEach((item) => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'etch-fonts-search-card';
            card.dataset.family = item.family;
            card.innerHTML = `
                <h3>${item.family}</h3>
                <div class="etch-fonts-muted">${item.category || ''}</div>
                <div class="etch-fonts-muted">${(item.variants || []).length} variant(s)</div>
            `;
            googleResults.appendChild(card);
        });
    }

    function selectSearchFamily(familyName) {
        selectedSearchFamily = searchResults.find((item) => item.family === familyName) || null;

        if (googleResults) {
            googleResults.querySelectorAll('.etch-fonts-search-card').forEach((card) => {
                card.classList.toggle('is-active', card.dataset.family === familyName);
            });
        }

        if (manualFamily) {
            manualFamily.value = familyName || '';
        }

        updateSelectedFamilyLabel(familyName);
        renderVariantOptions(selectedSearchFamily ? selectedSearchFamily.variants : []);
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
        button.textContent = `${getString('uploadUseDetected', 'Use detected values')} · ${label}`;

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

        formData.append('action', 'etch_fonts_upload_local');
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
                uploadSubmit.textContent = getString('uploadButtonIdle', 'Upload to library');
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
            googleResults.innerHTML = `<div class="etch-fonts-empty">${getString('searchDisabled', '')}</div>`;
            return;
        }

        if (query.trim().length < 2) {
            googleResults.innerHTML = '';
            return;
        }

        googleResults.innerHTML = `<div class="etch-fonts-empty">${getString('searching', 'Searching Google Fonts…')}</div>`;
        const payload = await postAjax('etch_fonts_search_google', config.searchNonce, { query });

        if (!payload.success) {
            googleResults.innerHTML = `<div class="etch-fonts-empty">${getPayloadMessage(payload, getString('importError', 'Request failed.'))}</div>`;
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
        importButton.textContent = idleLabel || getString('importButtonIdle', 'Import and self-host');
    }

    async function importGoogleVariant(family, variant, index, total) {
        const progressMessage = formatMessage(
            getString('importProgress', 'Importing %1$s: %2$d of %3$d (%4$s)…'),
            [family, index + 1, total, variant]
        );

        setStatus(importStatus, progressMessage, 'progress', Math.round((index / total) * 100));

        const payload = await postAjax('etch_fonts_import_google', config.importNonce, {
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
        if (!librarySearch) {
            return;
        }

        const applyLibraryFilter = () => {
            const query = librarySearch.value.trim().toLowerCase();

            document.querySelectorAll('[data-font-row]').forEach((row) => {
                if (!query) {
                    resetRowAfterSearch(row);
                    return;
                }

                const name = row.getAttribute('data-font-name') || '';
                const matches = name.includes(query);

                row.hidden = !matches;

                if (matches) {
                    expandRowForSearch(row);
                }
            });
        };

        librarySearch.addEventListener('input', applyLibraryFilter);
        librarySearch.addEventListener('search', applyLibraryFilter);
    }

    function handleDisclosureToggleClick(event) {
        const disclosureToggle = event.target.closest('[data-disclosure-toggle]');

        if (!disclosureToggle) {
            return false;
        }

        const isExpanded = disclosureToggle.getAttribute('aria-expanded') === 'true';
        const nextExpanded = !isExpanded;

        setDisclosureState(disclosureToggle, nextExpanded);

        if (nextExpanded && disclosureToggle.getAttribute('data-disclosure-toggle') === 'etch-fonts-add-font-panel') {
            window.setTimeout(focusAddFontPanel, 0);
        }

        return true;
    }

    function handleTabClick(event) {
        const addFontTab = event.target.closest('[data-add-font-tab]');
        if (addFontTab) {
            activateAddFontTab(addFontTab.getAttribute('data-add-font-tab'));
            return true;
        }

        const outputTab = event.target.closest('[data-output-tab]');
        if (outputTab) {
            activateOutputTab(outputTab.getAttribute('data-output-tab'));
            return true;
        }

        const previewTab = event.target.closest('[data-preview-tab]');
        if (previewTab) {
            activatePreviewTab(previewTab.getAttribute('data-preview-tab'));
            return true;
        }

        const studioTab = event.target.closest('[data-studio-tab]');
        if (studioTab) {
            activateStudioTab(studioTab.getAttribute('data-studio-tab'));
            return true;
        }

        return false;
    }

    function handleCopyClick(event) {
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

        if (role === 'heading' && roleHeading) {
            roleHeading.value = family;
        }

        if (role === 'body' && roleBody) {
            roleBody.value = family;
        }

        updateRoleOutputs();
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
        const searchCard = event.target.closest('.etch-fonts-search-card');

        if (!searchCard) {
            return false;
        }

        selectSearchFamily(searchCard.dataset.family || '');
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

        if (handleUploadBuilderClick(event)) {
            return;
        }

        handleDetectedUploadClick(event);
    }

    function bindFamilyFallbackControls() {
        document.querySelectorAll('.etch-fonts-fallback-selector').forEach((element) => {
            element.addEventListener('change', () => {
                updateInlineStackPreview(element.dataset.fontFamily || '');
                syncFamilyFallbackSaveState(element.closest('[data-family-fallback-form]'));
            });
            updateInlineStackPreview(element.dataset.fontFamily || '');
            syncFamilyFallbackSaveState(element.closest('[data-family-fallback-form]'));
        });

        document.querySelectorAll('[data-family-fallback-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (!config.saveFallbackNonce || !window.fetch) {
                    return;
                }

                event.preventDefault();

                const selector = form.querySelector('.etch-fonts-fallback-selector');
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
                if (!selectedSearchFamily || manualFamily.value !== selectedSearchFamily.family) {
                    selectedSearchFamily = null;
                    updateSelectedFamilyLabel(manualFamily.value);
                }
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
        const activeTab = outputTabs.find((tab) => tab.classList.contains('is-active')) || outputTabs[0];
        const activePreviewTab = previewTabs.find((tab) => tab.classList.contains('is-active')) || previewTabs[0];
        const activeStudioTab = studioTabs.find((tab) => tab.classList.contains('is-active')) || studioTabs[0];
        const activeAddFontTab = addFontTabs.find((tab) => tab.classList.contains('is-active')) || addFontTabs[0];

        if (activeTab) {
            activateOutputTab(activeTab.getAttribute('data-output-tab'));
        }

        if (activePreviewTab) {
            activatePreviewTab(activePreviewTab.getAttribute('data-preview-tab'));
        }

        if (activeStudioTab) {
            activateStudioTab(activeStudioTab.getAttribute('data-studio-tab'));
        }

        if (activeAddFontTab) {
            activateAddFontTab(activeAddFontTab.getAttribute('data-add-font-tab'));
        }
    }

    // Bootstrap
    function bootstrap() {
        document.addEventListener('click', handleDocumentClick);

        bindFamilyFallbackControls();
        bindRolePreviewControls();
        bindGoogleImportControls();
        bindUploadControls();

        syncDisclosureToggles();
        initToasts();
        initHelpTooltips();
        initUploadRows();
        initLibraryFiltering();
        updatePreviewDynamicText();
        updatePreviewScale();
        updateRoleOutputs();
        initializeTabs();
    }

    bootstrap();
})();
