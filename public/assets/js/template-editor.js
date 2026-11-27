/*
 * Message template editor (Admin > Message templates): placeholder buttons, a live count against the booking form's
 * limit, and a preview of the message with sample names. Plain script, no dependencies. The text area stays an
 * ordinary text area, so the form works the same without it.
 *
 * Markup: app/Views/admin/message_templates/_editor_tools.php
 */
(function () {
    'use strict';

    var tools = document.querySelector('.tpl-tools');
    if (!tools) { return; }

    var box = document.getElementById(tools.getAttribute('data-target'));
    if (!box) { return; }

    var max = parseInt(tools.getAttribute('data-max'), 10) || 500;
    var wpm = parseInt(tools.getAttribute('data-wpm'), 10) || 130;
    var known = (tools.getAttribute('data-tokens') || '').split(',').filter(Boolean);
    var SAMPLE = { recipient: 'Mama Ruth', sender: 'Grace' };

    var count = document.getElementById('tplCount');
    var unknown = document.getElementById('tplUnknown');
    var preview = document.getElementById('tplPreview');
    var onAir = document.getElementById('tplOnAir');

    function render() {
        var text = box.value;

        // Measured with the placeholders still in, the worst case: the same way the server checks the limit.
        var length = text.length;
        count.textContent = length + ' / ' + max + ' characters';
        count.className = 'small ' + (length > max ? 'text-danger fw-semibold' : (length > max * 0.9 ? 'text-warning-emphasis fw-semibold' : 'text-muted'));

        var bad = [];
        text.replace(/\{([^{}]*)\}/g, function (all, name) { if (known.indexOf(name) === -1 && bad.indexOf(name) === -1) { bad.push(name); } return all; });
        if (bad.length) {
            unknown.textContent = '{' + bad[0] + '} is not a placeholder, so a visitor would see it as written. You can use: ' + known.map(function (t) { return '{' + t + '}'; }).join(', ') + '.';
            unknown.hidden = false;
        } else {
            unknown.hidden = true;
        }

        var shown = text.replace(/\{([^{}]*)\}/g, function (all, name) { return Object.prototype.hasOwnProperty.call(SAMPLE, name) ? SAMPLE[name] : all; });
        preview.textContent = shown.trim() === '' ? 'Nothing written yet.' : shown;

        var words = shown.trim() === '' ? 0 : shown.trim().split(/\s+/).length;
        var seconds = Math.max(words ? 1 : 0, Math.round(words / wpm * 60));
        onAir.textContent = words === 0 ? '' : words + (words === 1 ? ' word' : ' words') + ', about ' + seconds + (seconds === 1 ? ' second' : ' seconds') + ' on air.';
    }

    function insert(token) {
        var text = '{' + token + '}';
        var start = box.selectionStart, end = box.selectionEnd;

        if (typeof start === 'number') {
            box.value = box.value.slice(0, start) + text + box.value.slice(end);
            box.selectionStart = box.selectionEnd = start + text.length;
        } else {
            box.value += text;
        }

        box.focus();
        render();
    }

    tools.querySelectorAll('[data-token]').forEach(function (button) {
        button.addEventListener('click', function () { insert(button.getAttribute('data-token')); });
    });

    box.addEventListener('input', render);
    render();
})();
