const test = require('node:test');
const assert = require('node:assert/strict');

const {
    getIframeDocument,
    normalizeStylesheetUrls,
    requiresCrossOriginStylesheetAccess,
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

function createFakeDocument(urls = []) {
    const links = [];
    const head = {
        appendChild(node) {
            node.parentNode = head;
            links.push(node);
        },
        removeChild(node) {
            const index = links.indexOf(node);

            if (index >= 0) {
                links.splice(index, 1);
            }
        },
    };

    const document = {
        head,
        location: {
            href: 'https://example.test/?etch=1',
        },
        createElement(tagName) {
            assert.equal(tagName, 'link');

            return new FakeLink();
        },
        querySelector(selector) {
            const match = selector.match(/data-tasty-fonts-runtime-index="(\d+)"/);

            if (!match) {
                return null;
            }

            return links.find((link) => link.getAttribute('data-tasty-fonts-runtime-index') === match[1]) || null;
        },
        querySelectorAll(selector) {
            if (selector !== 'link[data-tasty-fonts-runtime="1"]') {
                return [];
            }

            return [...links];
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

    return { document, links };
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
