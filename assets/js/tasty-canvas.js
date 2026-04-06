(function () {
    const config = window.TastyFontsCanvas || {};
    const stylesheetUrls = Array.isArray(config.stylesheetUrls)
        ? config.stylesheetUrls.filter(Boolean)
        : (config.stylesheetUrl ? [config.stylesheetUrl] : []);

    if (!stylesheetUrls.length) {
        return;
    }

    const getIframeDocument = (iframe) => {
        if (!iframe || !iframe.contentDocument || !iframe.contentDocument.head) {
            return null;
        }

        return iframe.contentDocument;
    };

    const injectIntoIframe = (iframe) => {
        const doc = getIframeDocument(iframe);

        if (!doc) {
            return false;
        }

        let existingIndex = 0;

        for (const node of doc.querySelectorAll('link[data-tasty-fonts-runtime="1"]')) {
            if (existingIndex >= stylesheetUrls.length && node.parentNode) {
                node.parentNode.removeChild(node);
            }

            existingIndex += 1;
        }

        for (const [index, stylesheetUrl] of stylesheetUrls.entries()) {
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
