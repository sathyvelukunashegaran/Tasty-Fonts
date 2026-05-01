(function () {
    const config = window.TastyFontsCanvas || {};
    const canvasContracts = window.TastyFontsCanvasContracts || {};
    const adminContracts = window.TastyFontsAdminContracts || {};
    const {
        buildQuickRoleRouteUrl = () => '',
        getIframeDocument = () => null,
        getQuickRoleKeys = (enabled) => (enabled ? ['heading', 'body', 'monospace'] : ['heading', 'body']),
        normalizeInlineCssBlocks = () => [],
        normalizeQuickRoleConfig = () => ({ enabled: false }),
        normalizeStylesheetUrls = () => [],
        syncIframeInlineStyles = () => false,
        syncIframeStylesheets = () => false,
    } = canvasContracts;
    const {
        normalizeRoleState = (state) => state || {},
        resolveAssignedRoleState = (roleKey, family, state) => ({ ...(state || {}), [roleKey]: family }),
        roleStatesMatch = () => false,
    } = adminContracts;
    const stylesheetUrls = normalizeStylesheetUrls(config);
    const inlineCssBlocks = normalizeInlineCssBlocks(config);
    const quickRoles = normalizeQuickRoleConfig(config.quickRoles || {});
    let latestGeneratedCssBlocks = [];

    if (!stylesheetUrls.length && !inlineCssBlocks.length && !quickRoles.enabled) {
        return;
    }

    const injectIntoIframe = (iframe) => {
        const doc = getIframeDocument(iframe);

        if (!doc) {
            return false;
        }

        if (stylesheetUrls.length) {
            syncIframeStylesheets(doc, stylesheetUrls);
        }

        if (inlineCssBlocks.length) {
            syncIframeInlineStyles(doc, inlineCssBlocks);
        }

        if (latestGeneratedCssBlocks.length) {
            syncIframeGeneratedCssRefresh(doc, latestGeneratedCssBlocks);
        }

        return true;
    };

    const bindIframe = (iframe) => {
        if (!iframe || iframe.dataset.tastyFontsBound === '1') {
            return;
        }

        iframe.dataset.tastyFontsBound = '1';

        iframe.addEventListener('load', () => {
            if (injectIntoIframe(iframe)) {
                iframe.dataset.tastyFontsRuntimeInjected = '1';
            }
        });

        if (injectIntoIframe(iframe)) {
            iframe.dataset.tastyFontsRuntimeInjected = '1';
        }
    };

    const bindAllIframes = () => {
        for (const iframe of document.querySelectorAll('iframe')) {
            bindIframe(iframe);
        }
    };

    let bindAllIframesTimeout = 0;

    const scheduleBindAllIframes = () => {
        window.clearTimeout(bindAllIframesTimeout);
        bindAllIframesTimeout = window.setTimeout(() => {
            bindAllIframes();
            dockQuickRolePanel(document.querySelector('[data-tasty-fonts-quick-roles]'));
        }, 100);
    };

    function quickRoleString(key, fallback) {
        return String((quickRoles.strings && quickRoles.strings[key]) || fallback || '').trim();
    }

    function roleLabel(roleKey) {
        const labels = {
            heading: quickRoleString('heading', 'Heading'),
            body: quickRoleString('body', 'Body'),
            monospace: quickRoleString('monospace', 'Monospace'),
        };

        return labels[roleKey] || roleKey;
    }

    function roleOptions() {
        return Object.keys(quickRoles.roleFamilyCatalog || {})
            .sort((left, right) => left.localeCompare(right, undefined, { sensitivity: 'base' }));
    }

    function buildRequestBody(state) {
        const keys = getQuickRoleKeys(quickRoles.monospaceRoleEnabled);
        const body = {};

        keys.forEach((roleKey) => {
            body[roleKey] = String(state[roleKey] || '').trim();
            body[`${roleKey}_fallback`] = String(state[`${roleKey}Fallback`] || '').trim();
            body[`${roleKey}_weight`] = String(state[`${roleKey}Weight`] || '').trim();
            body[`${roleKey}_axes`] = state[`${roleKey}Axes`] && typeof state[`${roleKey}Axes`] === 'object'
                ? state[`${roleKey}Axes`]
                : {};
        });

        return body;
    }

    function setStatus(status, message, mode) {
        if (!status) {
            return;
        }

        const normalizedMessage = String(message || '').trim();
        status.textContent = normalizedMessage;
        status.hidden = normalizedMessage === '';
        status.dataset.state = mode || 'idle';
        status.setAttribute('role', mode === 'error' ? 'alert' : 'status');
        status.setAttribute('aria-live', mode === 'error' ? 'assertive' : 'polite');
    }

    function findEtchToolbarSection() {
        if (typeof document.querySelector !== 'function' || typeof document.querySelectorAll !== 'function') {
            return null;
        }

        const explicit = document.querySelector('.settings-bar__section.top, [class*="settings-bar__section"][class*="top"]');

        if (explicit) {
            return explicit;
        }

        const leftRailButtons = Array.from(document.querySelectorAll('.etch-builder-button--variant-icon, [data-button-root="true"]'))
            .filter((button) => {
                const rect = button.getBoundingClientRect();

                const viewportHeight = Number.isFinite(window.innerHeight) ? window.innerHeight : 650;

                return rect.width > 0 && rect.height > 0 && rect.left < 80 && rect.top < Math.max(viewportHeight * 0.4, 260);
            });

        return leftRailButtons[0] ? leftRailButtons[0].parentElement : null;
    }

    let publishRefreshSequence = 0;

    function addPublishRefreshParam(stylesheetUrl, token) {
        const url = String(stylesheetUrl || '').trim();

        if (!url) {
            return '';
        }

        const hashIndex = url.indexOf('#');
        const base = hashIndex >= 0 ? url.slice(0, hashIndex) : url;
        const hash = hashIndex >= 0 ? url.slice(hashIndex) : '';
        const separator = base.includes('?') ? '&' : '?';

        return `${base}${separator}tasty_fonts_canvas_refresh=${encodeURIComponent(token)}${hash}`;
    }

    function isTastyGeneratedStylesheet(stylesheetUrl) {
        const url = String(stylesheetUrl || '').toLowerCase();

        return /\/\.generated\/tasty-fonts\.css(?:[?#]|$)/.test(url);
    }

    function generatedStylesheetUrls() {
        return stylesheetUrls.filter(isTastyGeneratedStylesheet);
    }

    function syncIframeGeneratedCssRefresh(doc, cssBlocks) {
        if (!doc || !doc.head || typeof doc.createElement !== 'function') {
            return false;
        }

        const css = cssBlocks.filter(Boolean).join('\n');
        const selector = 'style[data-tasty-fonts-generated-refresh="1"]';
        let style = typeof doc.querySelector === 'function' ? doc.querySelector(selector) : null;

        if (!style) {
            style = doc.createElement('style');
            style.setAttribute('data-tasty-fonts-generated-refresh', '1');
        }

        if (style.textContent !== css) {
            style.textContent = css;
        }

        if (style.parentNode !== doc.head) {
            doc.head.appendChild(style);
        } else if (doc.head.lastChild !== style) {
            doc.head.appendChild(style);
        }

        return true;
    }

    function generatedCssBlocksFromPayload(payload) {
        const canvasRefresh = payload && payload.canvas_refresh && typeof payload.canvas_refresh === 'object'
            ? payload.canvas_refresh
            : {};
        const css = typeof canvasRefresh.generated_css === 'string'
            ? canvasRefresh.generated_css
            : (typeof payload?.generated_css === 'string' ? payload.generated_css : '');

        return css ? [css] : [];
    }

    async function fetchGeneratedCssRefreshBlocks(token) {
        const blocks = [];

        for (const stylesheetUrl of generatedStylesheetUrls()) {
            const response = await window.fetch(addPublishRefreshParam(stylesheetUrl, token), {
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error(quickRoleString('publishFailed', 'Could not publish roles.'));
            }

            blocks.push(await response.text());
        }

        return blocks;
    }

    function refreshIframeRuntimeStyles(iframe, generatedCssBlocks) {
        const doc = getIframeDocument(iframe);

        if (!doc) {
            return false;
        }

        if (stylesheetUrls.length) {
            syncIframeStylesheets(doc, stylesheetUrls);
        }

        if (inlineCssBlocks.length) {
            syncIframeInlineStyles(doc, inlineCssBlocks);
        }

        if (generatedCssBlocks.length) {
            syncIframeGeneratedCssRefresh(doc, generatedCssBlocks);
        }

        iframe.dataset.tastyFontsRuntimeInjected = '1';

        return true;
    }

    async function refreshEtchCanvasAfterPublish(payload) {
        const payloadGeneratedCssBlocks = generatedCssBlocksFromPayload(payload);
        const generatedCssBlocks = payloadGeneratedCssBlocks.length
            ? payloadGeneratedCssBlocks
            : await fetchGeneratedCssRefreshBlocks(`${Date.now()}-${++publishRefreshSequence}`);
        latestGeneratedCssBlocks = [...generatedCssBlocks];
        let refreshed = 0;

        bindAllIframes();

        for (const iframe of document.querySelectorAll('iframe')) {
            if (!iframe || iframe.dataset.tastyFontsRuntimeInjected !== '1') {
                continue;
            }

            try {
                if (refreshIframeRuntimeStyles(iframe, generatedCssBlocks)) {
                    refreshed += 1;
                }
            } catch (error) {
                // Cross-origin or transient Etch iframes can reject access. Leave the builder intact.
            }
        }

        return refreshed;
    }

    function dockQuickRolePanel(shell) {
        if (!shell || shell.parentElement?.matches?.('.settings-bar__section.top, [class*="settings-bar__section"][class*="top"]')) {
            return;
        }

        const toolbarSection = findEtchToolbarSection();

        if (!toolbarSection) {
            return;
        }

        const toggle = shell.querySelector ? shell.querySelector('.tasty-fonts-canvas-toggle') : null;
        shell.className = 'tasty-fonts-canvas-shell tasty-fonts-canvas-shell--toolbar';

        if (toggle) {
            toggle.className = 'tasty-fonts-canvas-toggle etch-builder-button etch-builder-button--icon-placement-before etch-builder-button--variant-icon';
        }

        toolbarSection.appendChild(shell);
    }

    function mountQuickRolePanel() {
        if (!quickRoles.enabled || !document.body || !document.createElement || document.querySelector('[data-tasty-fonts-quick-roles]')) {
            return;
        }

        const toolbarSection = findEtchToolbarSection();
        const roleKeys = getQuickRoleKeys(quickRoles.monospaceRoleEnabled);
        const options = {
            monospaceRoleEnabled: quickRoles.monospaceRoleEnabled,
            variableFontsEnabled: quickRoles.variableFontsEnabled,
            roleFamilyCatalog: quickRoles.roleFamilyCatalog,
        };
        let committedRoles = normalizeRoleState(quickRoles.roles || {}, options);
        let appliedRoles = normalizeRoleState(quickRoles.appliedRoles || {}, options);
        const hasRoleFamilies = roleKeys.some(() => roleOptions().length > 0);
        let saveSequence = 0;
        let saveController = null;
        let isSaving = false;
        let isPublishing = false;
        let publishButton = null;
        const selects = {};

        const shell = document.createElement('div');
        shell.className = toolbarSection
            ? 'tasty-fonts-canvas-shell tasty-fonts-canvas-shell--toolbar'
            : 'tasty-fonts-canvas-shell';
        shell.setAttribute('data-tasty-fonts-quick-roles', '1');

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = toolbarSection
            ? 'tasty-fonts-canvas-toggle etch-builder-button etch-builder-button--icon-placement-before etch-builder-button--variant-icon'
            : 'tasty-fonts-canvas-toggle';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', quickRoleString('title', 'Tasty Fonts'));
        toggle.textContent = 'Aa';

        const panel = document.createElement('section');
        panel.className = 'tasty-fonts-canvas-panel';
        panel.hidden = true;

        const header = document.createElement('header');
        header.className = 'tasty-fonts-canvas-panel__header';

        const headerCopy = document.createElement('div');
        headerCopy.className = 'tasty-fonts-canvas-panel__header-copy';

        const title = document.createElement('strong');
        title.textContent = quickRoleString('title', 'Tasty Fonts');

        const subtitle = document.createElement('span');
        subtitle.textContent = quickRoleString('subtitle', 'Quick roles');

        headerCopy.append(title, subtitle);

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'tasty-fonts-canvas-close';
        closeButton.setAttribute('aria-label', quickRoleString('close', 'Close panel'));
        closeButton.textContent = '×';

        header.append(headerCopy, closeButton);
        panel.appendChild(header);

        const fields = document.createElement('div');
        fields.className = 'tasty-fonts-canvas-panel__fields';

        roleKeys.forEach((roleKey) => {
            const families = roleOptions(roleKey);
            const label = document.createElement('label');
            label.className = 'tasty-fonts-canvas-field';

            const labelText = document.createElement('span');
            labelText.textContent = roleLabel(roleKey);

            const select = document.createElement('select');
            select.setAttribute('data-role-key', roleKey);

            const fallbackOption = document.createElement('option');
            fallbackOption.value = '';
            fallbackOption.textContent = quickRoleString('fallbackOnly', 'Fallback only');
            select.appendChild(fallbackOption);

            families.forEach((familyName) => {
                const option = document.createElement('option');
                option.value = familyName;
                option.textContent = familyName;
                select.appendChild(option);
            });

            select.value = committedRoles[roleKey] || '';
            selects[roleKey] = select;
            label.append(labelText, select);
            fields.appendChild(label);
        });

        panel.appendChild(fields);

        const actions = document.createElement('div');
        actions.className = 'tasty-fonts-canvas-panel__actions';

        publishButton = document.createElement('button');
        publishButton.type = 'button';
        publishButton.className = 'tasty-fonts-canvas-publish';
        publishButton.textContent = quickRoleString('publish', 'Publish Site-Wide');
        actions.appendChild(publishButton);

        if (quickRoles.adminUrl) {
            const adminLink = document.createElement('a');
            adminLink.className = 'tasty-fonts-canvas-admin-link';
            adminLink.href = quickRoles.adminUrl;
            adminLink.target = '_blank';
            adminLink.rel = 'noopener noreferrer';
            adminLink.textContent = quickRoleString('openAdmin', 'Open Tasty Fonts');
            actions.appendChild(adminLink);
        }

        panel.appendChild(actions);

        const status = document.createElement('div');
        status.className = 'tasty-fonts-canvas-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        panel.appendChild(status);
        setStatus(status, hasRoleFamilies ? '' : quickRoleString('noFamilies', 'No Tasty Fonts families are available yet.'), hasRoleFamilies ? 'idle' : 'error');

        function syncSelects() {
            roleKeys.forEach((roleKey) => {
                if (selects[roleKey]) {
                    selects[roleKey].value = committedRoles[roleKey] || '';
                }
            });
        }

        function rolesAreLive() {
            return !!quickRoles.applyEverywhere && roleStatesMatch(committedRoles, appliedRoles, options);
        }

        function publishDisabled() {
            return isSaving
                || isPublishing
                || !hasRoleFamilies
                || !quickRoles.routes.publishRoleDraft
                || rolesAreLive();
        }

        function updateControls() {
            roleKeys.forEach((roleKey) => {
                if (selects[roleKey]) {
                    selects[roleKey].disabled = isSaving || isPublishing;
                }
            });

            if (publishButton) {
                publishButton.disabled = publishDisabled();
                publishButton.setAttribute('aria-disabled', publishButton.disabled ? 'true' : 'false');
            }
        }

        function setSaving(saving) {
            isSaving = saving;
            updateControls();
        }

        function setPublishing(publishing) {
            isPublishing = publishing;
            updateControls();
        }

        async function saveRole(roleKey, familyName) {
            const previousRoles = committedRoles;
            const nextRoles = resolveAssignedRoleState(roleKey, familyName, committedRoles, {
                ...options,
                preserveStates: [committedRoles, quickRoles.appliedRoles || {}],
            });
            const sequence = ++saveSequence;

            if (saveController && typeof saveController.abort === 'function') {
                saveController.abort();
            }

            saveController = typeof AbortController !== 'undefined' ? new AbortController() : null;
            committedRoles = normalizeRoleState(nextRoles, options);
            syncSelects();
            setSaving(true);
            setStatus(status, quickRoleString('saving', 'Saving…'), 'saving');

            try {
                const response = await window.fetch(buildQuickRoleRouteUrl(quickRoles.restUrl, quickRoles.routes.saveRoleDraft), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': quickRoles.restNonce,
                    },
                    body: JSON.stringify(buildRequestBody(committedRoles)),
                    signal: saveController ? saveController.signal : undefined,
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error((payload && payload.message) || quickRoleString('failed', 'Could not save roles.'));
                }

                if (sequence !== saveSequence) {
                    return;
                }

                committedRoles = normalizeRoleState(payload.roles || committedRoles, options);
                appliedRoles = normalizeRoleState(payload.applied_roles || appliedRoles, options);
                quickRoles.appliedRoles = appliedRoles;
                quickRoles.applyEverywhere = !!payload.apply_everywhere;
                syncSelects();
                updateControls();
                bindAllIframes();

                const deployment = payload.role_deployment && typeof payload.role_deployment === 'object' ? payload.role_deployment : {};
                setStatus(
                    status,
                    deployment.state === 'pending_publish'
                        ? quickRoleString('pendingPublish', 'Saved as draft. Publish roles in Tasty Fonts to update the live site.')
                        : quickRoleString('saved', 'Saved'),
                    'saved'
                );
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }

                committedRoles = previousRoles;
                syncSelects();
                setStatus(status, error && error.message ? error.message : quickRoleString('failed', 'Could not save roles.'), 'error');
            } finally {
                if (sequence === saveSequence) {
                    setSaving(false);
                    saveController = null;
                }
            }
        }

        async function publishRoles() {
            if (publishDisabled()) {
                if (rolesAreLive()) {
                    setStatus(status, quickRoleString('alreadyLive', 'These roles are already live site-wide.'), 'saved');
                }

                return;
            }

            setPublishing(true);
            setStatus(status, quickRoleString('publishing', 'Publishing…'), 'saving');

            try {
                const response = await window.fetch(buildQuickRoleRouteUrl(quickRoles.restUrl, quickRoles.routes.publishRoleDraft), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': quickRoles.restNonce,
                    },
                    body: JSON.stringify(buildRequestBody(committedRoles)),
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error((payload && payload.message) || quickRoleString('publishFailed', 'Could not publish roles.'));
                }

                if (payload.role_family_catalog && typeof payload.role_family_catalog === 'object') {
                    quickRoles.roleFamilyCatalog = payload.role_family_catalog;
                    options.roleFamilyCatalog = payload.role_family_catalog;
                }

                committedRoles = normalizeRoleState(payload.roles || committedRoles, options);
                appliedRoles = normalizeRoleState(payload.applied_roles || committedRoles, options);
                quickRoles.appliedRoles = appliedRoles;
                quickRoles.applyEverywhere = !!payload.apply_everywhere;
                syncSelects();
                setStatus(status, quickRoleString('refreshingCanvas', 'Refreshing the Etch canvas…'), 'saving');
                await refreshEtchCanvasAfterPublish(payload);
                setStatus(status, quickRoleString('published', 'Published site-wide.'), 'saved');
            } catch (error) {
                setStatus(status, error && error.message ? error.message : quickRoleString('publishFailed', 'Could not publish roles.'), 'error');
            } finally {
                setPublishing(false);
            }
        }

        roleKeys.forEach((roleKey) => {
            if (!selects[roleKey]) {
                return;
            }

            selects[roleKey].addEventListener('change', () => {
                void saveRole(roleKey, selects[roleKey].value);
            });
        });

        if (publishButton) {
            publishButton.addEventListener('click', () => {
                void publishRoles();
            });
            updateControls();
        }

        function closePanel() {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');

            if (typeof toggle.focus === 'function') {
                toggle.focus();
            }
        }

        closeButton.addEventListener('click', closePanel);

        panel.addEventListener('keydown', (event) => {
            if (event && event.key === 'Escape') {
                closePanel();
            }
        });

        toggle.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
            toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
        });

        shell.append(toggle, panel);

        if (toolbarSection) {
            toolbarSection.appendChild(shell);
        } else {
            document.body.appendChild(shell);
            window.setTimeout(() => dockQuickRolePanel(shell), 100);
            window.setTimeout(() => dockQuickRolePanel(shell), 500);
        }
    }

    if (document.body) {
        const observer = new MutationObserver(scheduleBindAllIframes);
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    bindAllIframes();
    mountQuickRolePanel();
})();
