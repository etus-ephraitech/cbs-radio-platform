/*
 * Staff account pages: show/hide password, a Caps Lock warning, digits-only code boxes, a resend countdown, and a
 * busy state on submit so a slow connection doesn't invite a second click. Everything works without it.
 */
(function () {
    'use strict';

    // Show / hide password. The button is hidden in the markup and only revealed here, so it never does nothing.
    document.querySelectorAll('[data-reveal]').forEach(function (button) {
        var input = document.getElementById(button.getAttribute('data-reveal'));
        if (!input) { return; }
        var hiddenIcon = button.querySelector('[data-when="hidden"]');
        var shownIcon = button.querySelector('[data-when="shown"]');
        button.hidden = false;
        button.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            hiddenIcon.hidden = show;
            shownIcon.hidden = !show;
            input.focus();
        });
    });

    // Caps Lock warning on password fields.
    document.querySelectorAll('input[data-caps]').forEach(function (input) {
        var note = document.getElementById(input.id + '-caps');
        if (!note) { return; }
        function check(e) { if (e.getModifierState) { note.hidden = !e.getModifierState('CapsLock'); } }
        input.addEventListener('keydown', check);
        input.addEventListener('keyup', check);
        input.addEventListener('blur', function () { note.hidden = true; });
    });

    // Codes are digits: drop anything else as it is typed or pasted.
    document.querySelectorAll('input[data-digits]').forEach(function (input) {
        input.addEventListener('input', function () {
            var clean = input.value.replace(/\D/g, '');
            if (clean !== input.value) { input.value = clean; }
        });
    });

    // "You can request a new code in N seconds", counting down, then the button wakes up.
    document.querySelectorAll('[data-countdown]').forEach(function (note) {
        var left = parseInt(note.getAttribute('data-countdown'), 10) || 0;
        var count = note.querySelector('span');
        var button = note.parentElement.querySelector('[data-countdown-target]');
        var timer = window.setInterval(function () {
            left -= 1;
            if (count) { count.textContent = String(Math.max(0, left)); }
            if (left <= 0) {
                window.clearInterval(timer);
                note.hidden = true;
                if (button) { button.disabled = false; }
            }
        }, 1000);
    });

    // One submit per click: the button says what is happening and can't be pressed twice.
    document.querySelectorAll('form[data-auth-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var button = form.querySelector('button[type="submit"]');
            if (!button) { return; }
            window.setTimeout(function () {
                button.disabled = true;
                if (button.dataset.busyLabel) { button.textContent = button.dataset.busyLabel; }
            }, 0);
        });
    });

    // Back from the browser's cache (the back button after a failed sign-in): re-enable the button.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) { return; }
        document.querySelectorAll('form[data-auth-form] button[type="submit"]').forEach(function (b) { b.disabled = false; });
    });
})();
