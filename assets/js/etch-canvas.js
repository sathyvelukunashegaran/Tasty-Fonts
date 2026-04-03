(function () {
    var config = window.EtchFontsCanvas || {};
    var stylesheetUrl = config.stylesheetUrl || '';

    if (!stylesheetUrl) {
        return;
    }

    function getIframeDocument(iframe) {
        if (!iframe || !iframe.contentDocument || !iframe.contentDocument.head) {
            return null;
        }

        return iframe.contentDocument;
    }

    function injectIntoIframe(iframe) {
        var doc = getIframeDocument(iframe);

        if (!doc) {
            return false;
        }

        var existing = doc.querySelector('link[data-etch-fonts-runtime="1"]');

        if (existing) {
            if (existing.href !== stylesheetUrl) {
                existing.href = stylesheetUrl;
            }

            return true;
        }

        var link = doc.createElement('link');
        link.rel = 'stylesheet';
        link.href = stylesheetUrl;
        link.setAttribute('data-etch-fonts-runtime', '1');
        doc.head.appendChild(link);

        return true;
    }

    function bindIframe(iframe) {
        if (!iframe || iframe.dataset.etchFontsBound === '1') {
            return;
        }

        iframe.dataset.etchFontsBound = '1';

        iframe.addEventListener('load', function () {
            injectIntoIframe(iframe);
        });

        injectIntoIframe(iframe);
    }

    function bindAllIframes() {
        document.querySelectorAll('iframe').forEach(bindIframe);
    }

    var observer = new MutationObserver(bindAllIframes);
    observer.observe(document.documentElement, {
        childList: true,
        subtree: true
    });

    bindAllIframes();

    var attempts = 0;
    var interval = window.setInterval(function () {
        bindAllIframes();
        attempts += 1;

        if (attempts > 40) {
            window.clearInterval(interval);
        }
    }, 500);
})();
