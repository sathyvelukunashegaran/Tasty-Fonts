(function () {
    const config = window.TastyFontsCanvas || {};
    const canvasContracts = window.TastyFontsCanvasContracts || {};
    const {
        getIframeDocument = () => null,
        normalizeInlineCssBlocks = () => [],
        normalizeStylesheetUrls = () => [],
        syncIframeInlineStyles = () => false,
        syncIframeStylesheets = () => false,
    } = canvasContracts;
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
