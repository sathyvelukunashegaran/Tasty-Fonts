(function (global, factory) {
    const contracts = factory();

    global.TastyFontsCanvasContracts = contracts;

    if (typeof module === 'object' && module.exports) {
        module.exports = contracts;
    }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    function normalizeStylesheetUrls(config) {
        return Array.isArray(config.stylesheetUrls)
            ? config.stylesheetUrls.filter(Boolean)
            : (config.stylesheetUrl ? [config.stylesheetUrl] : []);
    }

    function normalizeInlineCssBlocks(config) {
        return Array.isArray(config.inlineCss)
            ? config.inlineCss.filter(Boolean)
            : (config.inlineCss ? [config.inlineCss] : []);
    }

    function getIframeDocument(iframe) {
        if (!iframe || !iframe.contentDocument || !iframe.contentDocument.head) {
            return null;
        }

        return iframe.contentDocument;
    }

    function requiresCrossOriginStylesheetAccess(doc, stylesheetUrl) {
        if (typeof stylesheetUrl !== 'string' || stylesheetUrl.trim() === '') {
            return false;
        }

        const baseHref = doc && doc.location && typeof doc.location.href === 'string'
            ? doc.location.href
            : (typeof globalThis.location !== 'undefined' ? globalThis.location.href : 'https://example.test/');

        try {
            const stylesheet = new URL(stylesheetUrl, baseHref);
            const base = new URL(baseHref);

            return stylesheet.origin !== base.origin;
        } catch (error) {
            return false;
        }
    }

    function normalizeStylesheetUrlForComparison(doc, stylesheetUrl) {
        if (typeof stylesheetUrl !== 'string' || stylesheetUrl.trim() === '') {
            return '';
        }

        const baseHref = doc && doc.location && typeof doc.location.href === 'string'
            ? doc.location.href
            : (typeof globalThis.location !== 'undefined' ? globalThis.location.href : 'https://example.test/');

        try {
            const stylesheet = new URL(stylesheetUrl, baseHref);
            stylesheet.hash = '';
            stylesheet.searchParams.delete('ver');
            stylesheet.searchParams.delete('etch_rand');

            return stylesheet.toString();
        } catch (error) {
            return stylesheetUrl.trim();
        }
    }

    function iframeAlreadyHasStylesheet(doc, stylesheetUrl, ignoredNode) {
        const target = normalizeStylesheetUrlForComparison(doc, stylesheetUrl);

        if (target === '') {
            return false;
        }

        for (const link of doc.querySelectorAll('link[rel="stylesheet"]')) {
            if (link === ignoredNode) {
                continue;
            }

            const current = normalizeStylesheetUrlForComparison(doc, link.href || link.getAttribute('href') || '');

            if (current === target) {
                return true;
            }
        }

        return false;
    }

    function syncIframeStylesheets(doc, stylesheetUrls) {
        const claimedIndexes = new Set();

        for (const [index, stylesheetUrl] of stylesheetUrls.entries()) {
            const current = doc.querySelector(`link[data-tasty-fonts-runtime="1"][data-tasty-fonts-runtime-index="${index}"]`);

            if (iframeAlreadyHasStylesheet(doc, stylesheetUrl, current)) {
                if (current && current.parentNode) {
                    current.parentNode.removeChild(current);
                }

                continue;
            }

            if (current) {
                if (current.href !== stylesheetUrl) {
                    current.href = stylesheetUrl;
                }

                if (requiresCrossOriginStylesheetAccess(doc, stylesheetUrl)) {
                    current.crossOrigin = 'anonymous';
                } else {
                    current.removeAttribute('crossorigin');
                }

                claimedIndexes.add(String(index));
                continue;
            }

            const link = doc.createElement('link');
            link.rel = 'stylesheet';
            link.href = stylesheetUrl;
            if (requiresCrossOriginStylesheetAccess(doc, stylesheetUrl)) {
                link.crossOrigin = 'anonymous';
            }
            link.setAttribute('data-tasty-fonts-runtime', '1');
            link.setAttribute('data-tasty-fonts-runtime-index', String(index));
            doc.head.appendChild(link);
            claimedIndexes.add(String(index));
        }

        for (const node of doc.querySelectorAll('link[data-tasty-fonts-runtime="1"]')) {
            if (!claimedIndexes.has(node.getAttribute('data-tasty-fonts-runtime-index'))) {
                node.parentNode.removeChild(node);
            }
        }

        return true;
    }

    function syncIframeInlineStyles(doc, inlineCssBlocks) {
        let existingIndex = 0;

        for (const node of doc.querySelectorAll('style[data-tasty-fonts-runtime-inline="1"]')) {
            if (existingIndex >= inlineCssBlocks.length && node.parentNode) {
                node.parentNode.removeChild(node);
            }

            existingIndex += 1;
        }

        for (const [index, inlineCss] of inlineCssBlocks.entries()) {
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
    }

    return {
        getIframeDocument,
        normalizeInlineCssBlocks,
        normalizeStylesheetUrls,
        requiresCrossOriginStylesheetAccess,
        syncIframeInlineStyles,
        syncIframeStylesheets,
    };
});
