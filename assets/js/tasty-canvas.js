(function () {
    const config = window.TastyFontsCanvas || {};
    const canvasContracts = window.TastyFontsCanvasContracts || {};
    const normalizeStylesheetUrls = typeof canvasContracts.normalizeStylesheetUrls === 'function'
        ? canvasContracts.normalizeStylesheetUrls
        : (runtimeConfig) => Array.isArray(runtimeConfig.stylesheetUrls)
            ? runtimeConfig.stylesheetUrls.filter(Boolean)
            : (runtimeConfig.stylesheetUrl ? [runtimeConfig.stylesheetUrl] : []);
    const normalizeInlineCssBlocks = typeof canvasContracts.normalizeInlineCssBlocks === 'function'
        ? canvasContracts.normalizeInlineCssBlocks
        : (runtimeConfig) => Array.isArray(runtimeConfig.inlineCss)
            ? runtimeConfig.inlineCss.filter(Boolean)
            : (runtimeConfig.inlineCss ? [runtimeConfig.inlineCss] : []);
    const getIframeDocument = typeof canvasContracts.getIframeDocument === 'function'
        ? canvasContracts.getIframeDocument
        : (iframe) => {
            if (!iframe || !iframe.contentDocument || !iframe.contentDocument.head) {
                return null;
            }

            return iframe.contentDocument;
        };
    const syncIframeStylesheets = typeof canvasContracts.syncIframeStylesheets === 'function'
        ? canvasContracts.syncIframeStylesheets
        : (doc, runtimeStylesheetUrls) => {
            let existingIndex = 0;

            for (const node of doc.querySelectorAll('link[data-tasty-fonts-runtime="1"]')) {
                if (existingIndex >= runtimeStylesheetUrls.length && node.parentNode) {
                    node.parentNode.removeChild(node);
                }

                existingIndex += 1;
            }

            for (const [index, stylesheetUrl] of runtimeStylesheetUrls.entries()) {
                const current = doc.querySelector(`link[data-tasty-fonts-runtime="1"][data-tasty-fonts-runtime-index="${index}"]`);

                if (current) {
                    if (current.href !== stylesheetUrl) {
                        current.href = stylesheetUrl;
                    }

                    continue;
                }

                const link = doc.createElement('link');
                link.rel = 'stylesheet';
                link.href = stylesheetUrl;
                link.setAttribute('data-tasty-fonts-runtime', '1');
                link.setAttribute('data-tasty-fonts-runtime-index', String(index));
                doc.head.appendChild(link);
            }

            return true;
        };
    const syncIframeInlineStyles = typeof canvasContracts.syncIframeInlineStyles === 'function'
        ? canvasContracts.syncIframeInlineStyles
        : (doc, runtimeInlineCssBlocks) => {
            let existingIndex = 0;

            for (const node of doc.querySelectorAll('style[data-tasty-fonts-runtime-inline="1"]')) {
                if (existingIndex >= runtimeInlineCssBlocks.length && node.parentNode) {
                    node.parentNode.removeChild(node);
                }

                existingIndex += 1;
            }

            for (const [index, inlineCss] of runtimeInlineCssBlocks.entries()) {
                const current = doc.querySelector(`style[data-tasty-fonts-runtime-inline="1"][data-tasty-fonts-runtime-inline-index="${index}"]`);

                if (current) {
                    if (current.textContent !== inlineCss) {
                        current.textContent = inlineCss;
                    }

                    continue;
                }

                const style = doc.createElement('style');
                style.textContent = inlineCss;
                style.setAttribute('data-tasty-fonts-runtime-inline', '1');
                style.setAttribute('data-tasty-fonts-runtime-inline-index', String(index));
                doc.head.appendChild(style);
            }

            return true;
        };
    const stylesheetUrls = normalizeStylesheetUrls(config);
    const inlineCssBlocks = normalizeInlineCssBlocks(config);

    if (!stylesheetUrls.length && !inlineCssBlocks.length) {
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

        return true;
    };

    const bindIframe = (iframe) => {
        if (!iframe || iframe.dataset.tastyFontsBound === '1') {
            return;
        }

        iframe.dataset.tastyFontsBound = '1';

        iframe.addEventListener('load', () => {
            injectIntoIframe(iframe);
        });

        injectIntoIframe(iframe);
    };

    const bindAllIframes = () => {
        for (const iframe of document.querySelectorAll('iframe')) {
            bindIframe(iframe);
        }
    };

    let bindAllIframesTimeout = 0;

    const scheduleBindAllIframes = () => {
        window.clearTimeout(bindAllIframesTimeout);
        bindAllIframesTimeout = window.setTimeout(bindAllIframes, 100);
    };

    if (document.body) {
        const observer = new MutationObserver(scheduleBindAllIframes);
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    bindAllIframes();
})();
