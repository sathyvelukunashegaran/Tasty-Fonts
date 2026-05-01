const test = require('node:test');
const assert = require('node:assert/strict');

const {
    buildQuickRoleRouteUrl,
    getIframeDocument,
    getQuickRoleKeys,
    normalizeInlineCssBlocks,
    normalizeQuickRoleConfig,
    normalizeStylesheetUrls,
    requiresCrossOriginStylesheetAccess,
    syncIframeInlineStyles,
    syncIframeStylesheets,
} = require('../../assets/js/canvas-contracts.js');

class FakeLink {
    constructor() {
        this.attributes = {};
        this.parentNode = null;
        this.href = '';
        this.rel = '';
        this.crossOrigin = null;
    }

    getAttribute(name) {
        return this.attributes[name] || null;
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }

    removeAttribute(name) {
        delete this.attributes[name];
    }
}

class FakeStyle {
    constructor() {
        this.attributes = {};
        this.parentNode = null;
        this.textContent = '';
    }

    getAttribute(name) {
        return this.attributes[name] || null;
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }
}

function createFakeDocument(urls = []) {
    const links = [];
    const styles = [];
    const head = {
        appendChild(node) {
            node.parentNode = head;
            if (node instanceof FakeStyle) {
                styles.push(node);
                return;
            }

            links.push(node);
        },
        removeChild(node) {
            const index = links.indexOf(node);

            if (index >= 0) {
                links.splice(index, 1);
                return;
            }

            const styleIndex = styles.indexOf(node);

            if (styleIndex >= 0) {
                styles.splice(styleIndex, 1);
            }
        },
    };

    const document = {
        head,
        location: {
            href: 'https://example.test/?etch=1',
        },
        createElement(tagName) {
            if (tagName === 'style') {
                return new FakeStyle();
            }

            assert.equal(tagName, 'link');

            return new FakeLink();
        },
        querySelector(selector) {
            const linkMatch = selector.match(/link\[data-tasty-fonts-runtime="1"\]\[data-tasty-fonts-runtime-index="(\d+)"\]/);

            if (linkMatch) {
                return links.find((link) => link.getAttribute('data-tasty-fonts-runtime-index') === linkMatch[1]) || null;
            }

            const styleMatch = selector.match(/style\[data-tasty-fonts-runtime-inline="1"\]\[data-tasty-fonts-runtime-inline-index="(\d+)"\]/);

            if (styleMatch) {
                return styles.find((style) => style.getAttribute('data-tasty-fonts-runtime-inline-index') === styleMatch[1]) || null;
            }

            return null;
        },
        querySelectorAll(selector) {
            if (selector === 'link[data-tasty-fonts-runtime="1"]') {
                return links.filter((link) => link.getAttribute('data-tasty-fonts-runtime') === '1');
            }

            if (selector === 'link[rel="stylesheet"]') {
                return links.filter((link) => link.rel === 'stylesheet');
            }

            if (selector === 'style[data-tasty-fonts-runtime-inline="1"]') {
                return [...styles];
            }

            return [];
        },
    };

    urls.forEach((url, index) => {
        const link = new FakeLink();
        link.rel = 'stylesheet';
        link.href = url;
        link.setAttribute('data-tasty-fonts-runtime', '1');
        link.setAttribute('data-tasty-fonts-runtime-index', String(index));
        head.appendChild(link);
    });

    return { document, links, styles };
}

test('canvas contracts normalize stylesheet URLs from array or singular config', () => {
    assert.deepEqual(
        normalizeStylesheetUrls({ stylesheetUrls: ['https://example.test/a.css', '', null] }),
        ['https://example.test/a.css']
    );
    assert.deepEqual(
        normalizeStylesheetUrls({ stylesheetUrl: 'https://example.test/one.css' }),
        ['https://example.test/one.css']
    );
    assert.deepEqual(normalizeStylesheetUrls({}), []);
});

test('canvas contracts normalize inline CSS blocks from array or singular config', () => {
    assert.deepEqual(
        normalizeInlineCssBlocks({ inlineCss: ['body{font-weight:var(--font-body-weight);}', '', null] }),
        ['body{font-weight:var(--font-body-weight);}']
    );
    assert.deepEqual(
        normalizeInlineCssBlocks({ inlineCss: 'body{font-weight:var(--font-body-weight);}' }),
        ['body{font-weight:var(--font-body-weight);}']
    );
    assert.deepEqual(normalizeInlineCssBlocks({}), []);
});

test('canvas contracts normalize quick-role config only when transport is complete', () => {
    assert.deepEqual(normalizeQuickRoleConfig({ enabled: true }), { enabled: false });

    const config = normalizeQuickRoleConfig({
        enabled: true,
        restUrl: ' https://example.test/wp-json/tasty-fonts/v1/ ',
        restNonce: ' nonce ',
        routes: { saveRoleDraft: '/roles/draft', publishRoleDraft: 'roles/publish' },
        adminUrl: ' https://example.test/wp-admin/admin.php?page=tasty-fonts ',
        roles: { heading: 'Inter' },
        appliedRoles: { heading: 'Inter' },
        monospaceRoleEnabled: true,
        roleFamilyCatalog: { Inter: { publishState: 'published' } },
    });

    assert.equal(config.enabled, true);
    assert.equal(config.restUrl, 'https://example.test/wp-json/tasty-fonts/v1/');
    assert.equal(config.restNonce, 'nonce');
    assert.equal(config.routes.saveRoleDraft, '/roles/draft');
    assert.equal(config.routes.publishRoleDraft, 'roles/publish');
    assert.equal(config.adminUrl, 'https://example.test/wp-admin/admin.php?page=tasty-fonts');
    assert.deepEqual(config.roles, { heading: 'Inter' });
    assert.deepEqual(config.appliedRoles, { heading: 'Inter' });
    assert.equal(config.monospaceRoleEnabled, true);
});

test('canvas contracts keep quick-role config enabled when publish route is missing', () => {
    const config = normalizeQuickRoleConfig({
        enabled: true,
        restUrl: 'https://example.test/wp-json/tasty-fonts/v1/',
        restNonce: 'nonce',
        routes: { saveRoleDraft: 'roles/draft' },
    });

    assert.equal(config.enabled, true);
    assert.equal(config.routes.saveRoleDraft, 'roles/draft');
    assert.equal(config.routes.publishRoleDraft, '');
});

test('canvas contracts provide quick-role keys and route URL joining', () => {
    assert.deepEqual(getQuickRoleKeys(false), ['heading', 'body']);
    assert.deepEqual(getQuickRoleKeys(true), ['heading', 'body', 'monospace']);
    assert.equal(
        buildQuickRoleRouteUrl('https://example.test/wp-json/tasty-fonts/v1/', '/roles/draft'),
        'https://example.test/wp-json/tasty-fonts/v1/roles/draft'
    );
});

test('canvas contracts guard iframe documents without a head element', () => {
    assert.equal(getIframeDocument(null), null);
    assert.equal(getIframeDocument({ contentDocument: {} }), null);

    const { document } = createFakeDocument();
    assert.equal(getIframeDocument({ contentDocument: document }), document);
});

test('canvas contracts detect when runtime stylesheets need crossorigin access', () => {
    const { document } = createFakeDocument();

    assert.equal(
        requiresCrossOriginStylesheetAccess(document, 'https://fonts.googleapis.com/css2?family=Inter'),
        true
    );
    assert.equal(
        requiresCrossOriginStylesheetAccess(document, 'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css'),
        false
    );
});

test('canvas contracts sync iframe stylesheets by updating and pruning runtime links', () => {
    const { document, links } = createFakeDocument([
        'https://example.test/old-a.css',
        'https://example.test/old-b.css',
        'https://example.test/old-c.css',
    ]);

    assert.equal(
        syncIframeStylesheets(document, [
            'https://example.test/new-a.css',
            'https://example.test/new-b.css',
        ]),
        true
    );

    assert.deepEqual(
        links.map((link) => link.href),
        ['https://example.test/new-a.css', 'https://example.test/new-b.css']
    );
    assert.deepEqual(
        links.map((link) => link.getAttribute('data-tasty-fonts-runtime-index')),
        ['0', '1']
    );
    assert.equal(links[0].crossOrigin, null);
    assert.equal(links[1].crossOrigin, null);
});

test('canvas contracts mark cross-origin runtime stylesheets anonymous for iframe access', () => {
    const { document, links } = createFakeDocument();

    assert.equal(
        syncIframeStylesheets(document, [
            'https://fonts.googleapis.com/css2?family=JetBrains+Mono',
        ]),
        true
    );

    assert.equal(links[0].crossOrigin, 'anonymous');
});

test('canvas contracts skip injecting duplicate iframe stylesheets when Etch already loaded an equivalent href', () => {
    const { document, links } = createFakeDocument();
    const existing = new FakeLink();
    existing.rel = 'stylesheet';
    existing.href = 'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css?etch_rand=abc123';
    document.head.appendChild(existing);

    assert.equal(
        syncIframeStylesheets(document, [
            'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css?ver=hash456&tasty_fonts_canvas_refresh=publish-1',
        ]),
        true
    );

    assert.deepEqual(
        links.map((link) => ({
            href: link.href,
            runtime: link.getAttribute('data-tasty-fonts-runtime'),
        })),
        [
            {
                href: 'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css?etch_rand=abc123',
                runtime: null,
            },
        ]
    );
});

test('canvas contracts can force-refresh selected runtime stylesheets despite equivalent Etch links', () => {
    const { document, links } = createFakeDocument();
    const existing = new FakeLink();
    existing.rel = 'stylesheet';
    existing.href = 'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css?etch_rand=abc123';
    document.head.appendChild(existing);

    assert.equal(
        syncIframeStylesheets(
            document,
            [
                'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css?ver=hash456&tasty_fonts_canvas_refresh=publish-1',
            ],
            { forceRefreshIndexes: [0] }
        ),
        true
    );

    assert.deepEqual(
        links.map((link) => ({
            href: link.href,
            runtime: link.getAttribute('data-tasty-fonts-runtime'),
        })),
        [
            {
                href: 'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css?etch_rand=abc123',
                runtime: null,
            },
            {
                href: 'https://example.test/wp-content/uploads/fonts/.generated/tasty-fonts.css?ver=hash456&tasty_fonts_canvas_refresh=publish-1',
                runtime: '1',
            },
        ]
    );
});

test('canvas contracts sync iframe inline styles by updating and pruning runtime style tags', () => {
    const { document, styles } = createFakeDocument();

    assert.equal(
        syncIframeInlineStyles(document, [
            'body{font-weight:var(--font-body-weight);}',
            'h1,h2,h3,h4,h5,h6{font-weight:var(--font-heading-weight);}',
        ]),
        true
    );

    assert.deepEqual(
        styles.map((style) => style.textContent),
        [
            'body{font-weight:var(--font-body-weight);}',
            'h1,h2,h3,h4,h5,h6{font-weight:var(--font-heading-weight);}',
        ]
    );

    assert.equal(
        syncIframeInlineStyles(document, [
            'body{font-weight:var(--font-body-weight);}',
        ]),
        true
    );

    assert.deepEqual(
        styles.map((style) => style.textContent),
        ['body{font-weight:var(--font-body-weight);}']
    );
});
