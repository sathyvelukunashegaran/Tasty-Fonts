const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

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
