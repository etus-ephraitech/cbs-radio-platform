/*
 * Small touches for the v1 visitor pages: "Copy" buttons next to a code to type into the phone.
 * Each [data-copy] button copies the [data-copy-source] inside the same .ck-code box. The button stays hidden
 * where the browser can't copy, so it never does nothing.
 */
(function () {
    'use strict';

    if (!navigator.clipboard || !window.isSecureContext) { return; }

    document.querySelectorAll('[data-copy]').forEach(function (button) {
        var box = button.closest('.ck-code');
        var source = box && box.querySelector('[data-copy-source]');
        if (!source) { return; }

        var label = button.innerHTML;
        button.hidden = false;
        button.addEventListener('click', function () {
            navigator.clipboard.writeText(source.textContent.trim()).then(function () {
                button.textContent = 'Copied';
                window.setTimeout(function () { button.innerHTML = label; }, 1800);
            });
        });
    });
})();
