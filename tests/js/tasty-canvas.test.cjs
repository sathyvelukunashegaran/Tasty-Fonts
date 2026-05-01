const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const canvasContracts = require('../../assets/js/canvas-contracts.js');
const adminContracts = require('../../assets/js/admin-contracts.js');

class FakeElement {
    constructor(tagName) {
        this.tagName = tagName;
        this.children = [];
        this.attributes = {};
        this.dataset = {};
        this.eventListeners = {};
        this.className = '';
        this.hidden = false;
        this.textContent = '';
        this.type = '';
        this.value = '';
        this.disabled = false;
        this.parentNode = null;
        this.focused = false;
    }

    focus() {
        this.focused = true;
    }

    appendChild(node) {
        if (node.parentNode && Array.isArray(node.parentNode.children)) {
            const existingIndex = node.parentNode.children.indexOf(node);

            if (existingIndex >= 0) {
                node.parentNode.children.splice(existingIndex, 1);
            }
        }

        node.parentNode = this;
        this.children.push(node);
    }

    get lastChild() {
        return this.children.length ? this.children[this.children.length - 1] : null;
    }

    append(...nodes) {
        nodes.forEach((node) => this.appendChild(node));
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);

        if (name.startsWith('data-')) {
            const key = name
                .slice(5)
                .replace(/-([a-z])/g, (match, letter) => letter.toUpperCase());
            this.dataset[key] = String(value);
        }
    }

    getAttribute(name) {
        return this.attributes[name] || null;
    }

    addEventListener(event, handler) {
        this.eventListeners[event] = handler;
    }

    dispatch(event) {
        if (this.eventListeners[event]) {
            this.eventListeners[event]({ type: event, target: this });
        }
    }

    find(predicate) {
        if (predicate(this)) {
            return this;
        }

        for (const child of this.children) {
            const match = child.find ? child.find(predicate) : null;

            if (match) {
                return match;
            }
        }

        return null;
    }

    findAll(predicate, results = []) {
        if (predicate(this)) {
            results.push(this);
        }

        this.children.forEach((child) => {
            if (child.findAll) {
                child.findAll(predicate, results);
            }
        });

        return results;
    }
}

function runCanvasScript(windowOverrides = {}, documentOverrides = {}) {
    const syncCalls = [];
    const iframe = {
        dataset: {},
        addEventListener(event, handler) {
            this.lastEvent = event;
            this.lastHandler = handler;
        },
    };
    const document = {
        body: {},
        querySelectorAll(selector) {
            return selector === 'iframe' ? [iframe] : [];
        },
        ...documentOverrides,
    };
    const window = {
        TastyFontsCanvas: {
            stylesheetUrls: ['https://example.test/runtime.css?ver=123'],
            inlineCss: ['body{font-family:var(--font-body);}'],
        },
        TastyFontsCanvasContracts: {
            normalizeStylesheetUrls(config) {
                syncCalls.push(['normalizeStylesheetUrls', config]);
                return config.stylesheetUrls;
            },
            normalizeInlineCssBlocks(config) {
                syncCalls.push(['normalizeInlineCssBlocks', config]);
                return config.inlineCss;
            },
            getIframeDocument(target) {
                syncCalls.push(['getIframeDocument', target]);
                return { head: {}, marker: 'iframe-doc' };
            },
            syncIframeStylesheets(doc, urls) {
                syncCalls.push(['syncIframeStylesheets', doc, urls]);
                return true;
            },
            syncIframeInlineStyles(doc, blocks) {
                syncCalls.push(['syncIframeInlineStyles', doc, blocks]);
                return true;
            },
        },
        clearTimeout() {},
        setTimeout(callback) {
            callback();
            return 1;
        },
        ...windowOverrides,
    };

    const context = vm.createContext({
        window,
        document,
        MutationObserver: class {
            constructor(callback) {
                this.callback = callback;
            }

            observe() {}
        },
    });
    const scriptPath = path.join(__dirname, '../../assets/js/tasty-canvas.js');
    const source = fs.readFileSync(scriptPath, 'utf8');

    vm.runInContext(source, context, { filename: scriptPath });

    return { iframe, syncCalls };
}

function runCanvasScriptWithQuickRoles(fetchImpl, overrides = {}) {
    const body = new FakeElement('body');
    const iframes = Array.isArray(overrides.iframes) ? overrides.iframes : [];
    const quickRolesConfig = {
        enabled: true,
        restUrl: 'https://example.test/wp-json/tasty-fonts/v1/',
        restNonce: 'nonce:wp_rest',
        routes: { saveRoleDraft: 'roles/draft', publishRoleDraft: 'roles/publish' },
        adminUrl: 'https://example.test/wp-admin/admin.php?page=tasty-custom-fonts',
        roles: { heading: 'Inter', body: '' },
        appliedRoles: { heading: 'Inter', body: '' },
        roleFamilyCatalog: {
            Inter: { fallback: 'system-ui, sans-serif', publishState: 'published' },
            Manrope: { fallback: 'system-ui, sans-serif', publishState: 'published' },
            'Draft Sans': { fallback: 'system-ui, sans-serif', publishState: 'library_only' },
        },
        strings: { saved: 'Saved', published: 'Published site-wide.' },
        ...(overrides.quickRoles || {}),
    };
    const document = {
        body,
        createElement(tagName) {
            return new FakeElement(tagName);
        },
        querySelector(selector) {
            if (selector === '[data-tasty-fonts-quick-roles]') {
                return body.find((node) => node.dataset.tastyFontsQuickRoles === '1');
            }

            return null;
        },
        querySelectorAll(selector) {
            return selector === 'iframe' ? iframes : [];
        },
    };
    const window = {
        TastyFontsCanvas: {
            ...(overrides.canvasConfig || {}),
            quickRoles: quickRolesConfig,
        },
        TastyFontsCanvasContracts: overrides.canvasContracts || canvasContracts,
        TastyFontsAdminContracts: adminContracts,
        clearTimeout() {},
        setTimeout(callback) {
            callback();
            return 1;
        },
        fetch: fetchImpl,
    };

    const context = vm.createContext({
        window,
        document,
        MutationObserver: class {
            observe() {}
        },
    });
    const scriptPath = path.join(__dirname, '../../assets/js/tasty-canvas.js');
    const source = fs.readFileSync(scriptPath, 'utf8');

    vm.runInContext(source, context, { filename: scriptPath });

    return { body, window };
}

test('tasty-canvas delegates stylesheet and inline-css sync to canvas contracts', () => {
    const { iframe, syncCalls } = runCanvasScript();

    assert.equal(iframe.dataset.tastyFontsBound, '1');
    assert.equal(iframe.lastEvent, 'load');
    assert.deepEqual(
        syncCalls.map(([name]) => name),
        [
            'normalizeStylesheetUrls',
            'normalizeInlineCssBlocks',
            'getIframeDocument',
            'syncIframeStylesheets',
            'syncIframeInlineStyles',
        ]
    );
    assert.deepEqual(syncCalls[3][2], ['https://example.test/runtime.css?ver=123']);
    assert.deepEqual(syncCalls[4][2], ['body{font-family:var(--font-body);}']);
});

test('tasty-canvas renders quick roles and saves changes immediately', async () => {
    const fetchCalls = [];
    const { body } = runCanvasScriptWithQuickRoles(async (url, options) => {
        fetchCalls.push({ url, options });

        return {
            ok: true,
            async json() {
                return {
                    roles: {
                        heading: 'Manrope',
                        body: '',
                        heading_fallback: 'system-ui, sans-serif',
                        body_fallback: 'system-ui, sans-serif',
                    },
                    applied_roles: {},
                    apply_everywhere: false,
                    role_deployment: { state: 'draft' },
                };
            },
        };
    });

    const shell = body.find((node) => node.dataset.tastyFontsQuickRoles === '1');
    const selects = body.findAll((node) => node.tagName === 'select');
    const toggle = body.find((node) => node.className === 'tasty-fonts-canvas-toggle');
    const closeButton = body.find((node) => node.className === 'tasty-fonts-canvas-close');
    const panel = body.find((node) => node.className === 'tasty-fonts-canvas-panel');
    const status = body.find((node) => node.className === 'tasty-fonts-canvas-status');

    assert.ok(shell, 'The quick role shell should mount into the Etch parent document.');
    assert.ok(closeButton, 'The quick role panel should include a close button.');
    assert.equal(status.hidden, true, 'The status row should be hidden while there is no message.');
    toggle.dispatch('click');
    assert.equal(panel.hidden, false, 'The toggle should open the panel.');
    closeButton.dispatch('click');
    assert.equal(panel.hidden, true, 'The close button should close the panel.');
    assert.equal(toggle.attributes['aria-expanded'], 'false');
    assert.equal(toggle.focused, true, 'Closing should return focus to the panel toggle.');
    assert.equal(selects.length, 2, 'The monospace select should stay hidden when the monospace role is disabled.');
    assert.equal(selects[0].value, 'Inter');
    assert.ok(
        selects[0].children.some((option) => option.value === 'Draft Sans'),
        'Library-only families should be available in the Etch quick role picker.'
    );

    selects[0].value = 'Manrope';
    selects[0].dispatch('change');
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(status.hidden, false, 'Saving should reveal the status row once there is a message.');
    assert.equal(fetchCalls.length, 1);
    assert.equal(fetchCalls[0].url, 'https://example.test/wp-json/tasty-fonts/v1/roles/draft');
    assert.equal(fetchCalls[0].options.method, 'PATCH');
    assert.equal(fetchCalls[0].options.headers['X-WP-Nonce'], 'nonce:wp_rest');
    assert.equal(JSON.parse(fetchCalls[0].options.body).heading, 'Manrope');
});

test('tasty-canvas publishes quick roles without reloading Etch iframes and safely refreshes Tasty styles', async () => {
    const fetchCalls = [];
    const canvasSyncCalls = [];
    let iframeReloads = 0;
    const iframeHead = new FakeElement('head');
    const previousGeneratedRefreshStyle = new FakeElement('style');
    previousGeneratedRefreshStyle.textContent = ':root{--font-body:"JetBrains Mono";}';
    previousGeneratedRefreshStyle.setAttribute('data-tasty-fonts-generated-refresh', '1');
    iframeHead.appendChild(previousGeneratedRefreshStyle);
    const existingEtchLink = new FakeElement('link');
    existingEtchLink.rel = 'stylesheet';
    existingEtchLink.href = 'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css?etch_rand=stale';
    iframeHead.appendChild(existingEtchLink);
    const iframeDocument = {
        head: iframeHead,
        marker: 'iframe-doc',
        location: { href: 'https://example.test/etch/?builder=1' },
        createElement(tagName) {
            return new FakeElement(tagName);
        },
        querySelector(selector) {
            if (selector === 'style[data-tasty-fonts-generated-refresh="1"]') {
                return iframeHead.find((node) => node.dataset.tastyFontsGeneratedRefresh === '1');
            }

            return null;
        },
        querySelectorAll(selector) {
            if (selector === 'link[rel="stylesheet"]') {
                return iframeHead.findAll((node) => node.tagName === 'link' && node.rel === 'stylesheet');
            }

            if (selector === 'link[data-tasty-fonts-runtime="1"]') {
                return iframeHead.findAll((node) => node.tagName === 'link' && node.dataset.tastyFontsRuntime === '1');
            }

            if (selector === 'style[data-tasty-fonts-runtime-inline="1"]') {
                return iframeHead.findAll((node) => node.tagName === 'style' && node.dataset.tastyFontsRuntimeInline === '1');
            }

            return [];
        },
    };
    const iframe = {
        dataset: {},
        addEventListener(event, handler) {
            this.eventListeners = this.eventListeners || {};
            this.eventListeners[event] = handler;
        },
        contentDocument: iframeDocument,
        contentWindow: {
            location: {
                reload() {
                    iframeReloads += 1;
                },
            },
        },
    };
    const canvasContractsStub = {
        ...canvasContracts,
        getIframeDocument(target) {
            canvasSyncCalls.push(['getIframeDocument', target]);
            return target.contentDocument;
        },
        syncIframeStylesheets(doc, urls, options) {
            canvasSyncCalls.push(['syncIframeStylesheets', doc, urls, options]);
            return true;
        },
        syncIframeInlineStyles(doc, blocks) {
            canvasSyncCalls.push(['syncIframeInlineStyles', doc, blocks]);
            return true;
        },
    };
    const { body } = runCanvasScriptWithQuickRoles(async (url, options) => {
        fetchCalls.push({ url, options });

        if (!options.method) {
            return {
                ok: true,
                async text() {
                    return ':root{--font-body:"Draft Sans";} @font-face{font-family:"Draft Sans";}';
                },
            };
        }

        if (options.method === 'PATCH') {
            return {
                ok: true,
                async json() {
                    return {
                        roles: {
                            heading: 'Draft Sans',
                            body: '',
                            heading_fallback: 'system-ui, sans-serif',
                            body_fallback: 'system-ui, sans-serif',
                        },
                        applied_roles: { heading: 'Inter', body: '' },
                        apply_everywhere: false,
                        role_deployment: { state: 'off' },
                    };
                },
            };
        }

        return {
            ok: true,
            async json() {
                return {
                    roles: {
                        heading: 'Draft Sans',
                        body: '',
                        heading_fallback: 'system-ui, sans-serif',
                        body_fallback: 'system-ui, sans-serif',
                    },
                    applied_roles: {
                        heading: 'Draft Sans',
                        body: '',
                        heading_fallback: 'system-ui, sans-serif',
                        body_fallback: 'system-ui, sans-serif',
                    },
                    apply_everywhere: true,
                    role_family_catalog: {
                        Inter: { fallback: 'system-ui, sans-serif', publishState: 'published' },
                        'Draft Sans': { fallback: 'system-ui, sans-serif', publishState: 'role_active' },
                    },
                    role_deployment: { state: 'live' },
                    canvas_refresh: {
                        mode: 'runtime_styles',
                        generated_css: ':root{--font-body:"Draft Sans";} @font-face{font-family:"Draft Sans";}',
                    },
                };
            },
        };
    }, {
        iframes: [iframe],
        canvasConfig: {
            stylesheetUrls: [
                'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css?ver=old',
                'https://fonts.googleapis.com/css2?family=Inter',
            ],
            inlineCss: ['body{font-family:var(--font-body);}'],
        },
        canvasContracts: canvasContractsStub,
    });

    const selects = body.findAll((node) => node.tagName === 'select');
    const publishButton = body.find((node) => node.className === 'tasty-fonts-canvas-publish');
    const status = body.find((node) => node.className === 'tasty-fonts-canvas-status');
    const adminLink = body.find((node) => node.className === 'tasty-fonts-canvas-admin-link');

    assert.ok(publishButton, 'The quick panel should expose a publish button.');
    assert.equal(adminLink.href, 'https://example.test/wp-admin/admin.php?page=tasty-custom-fonts');
    assert.equal(adminLink.target, '_blank');

    selects[0].value = 'Draft Sans';
    selects[0].dispatch('change');
    await new Promise((resolve) => setImmediate(resolve));

    publishButton.dispatch('click');
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(fetchCalls.length, 2, 'Inline generated CSS from the publish response should avoid a generated-file fetch.');
    assert.equal(fetchCalls[1].url, 'https://example.test/wp-json/tasty-fonts/v1/roles/publish');
    assert.equal(fetchCalls[1].options.method, 'POST');
    assert.equal(fetchCalls[1].options.headers['X-WP-Nonce'], 'nonce:wp_rest');
    assert.equal(JSON.parse(fetchCalls[1].options.body).heading, 'Draft Sans');
    const stylesheetSyncs = canvasSyncCalls.filter(([name]) => name === 'syncIframeStylesheets');
    const inlineSyncs = canvasSyncCalls.filter(([name]) => name === 'syncIframeInlineStyles');
    const generatedRefreshStyle = iframeHead.find((node) => node.dataset.tastyFontsGeneratedRefresh === '1');

    assert.equal(iframeReloads, 0, 'Publishing must not reload Etch/canvas iframes.');
    assert.equal(stylesheetSyncs.length, 2, 'Publishing should resync the Tasty runtime stylesheet after the initial injection.');
    assert.equal(inlineSyncs.length, 2, 'Publishing should resync Tasty inline runtime styles after the initial injection.');
    assert.equal(stylesheetSyncs.at(-1)[1], iframeDocument);
    assert.deepEqual(
        stylesheetSyncs.at(-1)[2],
        [
            'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css?ver=old',
            'https://fonts.googleapis.com/css2?family=Inter',
        ],
        'Provider stylesheet handling should stay on the normal runtime sync path.'
    );
    assert.equal(stylesheetSyncs.at(-1)[3], undefined);
    assert.equal(generatedRefreshStyle, previousGeneratedRefreshStyle, 'Publishing should replace the previous Tasty generated CSS refresh node.');
    assert.equal(generatedRefreshStyle.textContent, ':root{--font-body:"Draft Sans";} @font-face{font-family:"Draft Sans";}');
    assert.equal(iframeHead.lastChild, generatedRefreshStyle, 'The generated CSS refresh style should be last so role variables win.');

    generatedRefreshStyle.textContent = ':root{--font-body:"Stale After Load";}';
    iframe.eventListeners.load();
    assert.equal(
        generatedRefreshStyle.textContent,
        ':root{--font-body:"Draft Sans";} @font-face{font-family:"Draft Sans";}',
        'Later iframe load events in the same Etch session should reuse the latest publish payload CSS.'
    );

    assert.equal(status.textContent, 'Published site-wide.');
    assert.equal(publishButton.disabled, true, 'Published matching roles should disable the publish action.');
});
