/*
 * The greeting form as steps (app/Views/v1/booking/create.php): one step at a time, a running summary and total,
 * gifts with quantities, a review, and the phone's bottom bar.
 *
 * Without this script the form still works: every step shows, one after the other, and it posts from the bottom.
 * The message editor and voice recorder are assets/js/booking.js, which loads first and keeps validating the whole
 * form when it is sent; this script checks each step before moving on, and if the final check finds a problem in
 * an earlier step, opens that step.
 */
(function () {
    'use strict';

    var form = document.getElementById('bookingForm');
    if (!form || !form.querySelector('[data-step]')) { return; }

    function $(id) { return document.getElementById(id); }
    function all(selector, root) { return Array.prototype.slice.call((root || form).querySelectorAll(selector)); }
    function int(value, fallback) { var n = parseInt(value, 10); return isNaN(n) ? fallback : n; }
    function normalise(text) { return (text || '').replace(/\s+/g, ' ').trim(); }

    var cfg = {
        maxChars: int(form.dataset.maxChars, 500),
        giftMaxQty: int(form.dataset.giftMaxQuantity, 10),
        giftMaxCount: int(form.dataset.giftMaxCount, 5)
    };

    var steps = all('[data-step]');
    var order = steps.map(function (s) { return int(s.dataset.step, 0); });
    var current = order[0];
    var furthest = order[0];
    var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var PLACEHOLDER = /\[(their name|your name)\]/i;

    form.classList.add('ck-js');

    function announce(text) {
        var el = $('announce');
        if (!el) { return; }
        el.textContent = '';
        window.setTimeout(function () { el.textContent = text; }, 60);
    }

    /* ================================================================== money */

    function money(amount, currency) {
        var rounded = Math.round(amount);
        return (currency || '') + ' ' + rounded.toLocaleString('en-US');
    }

    function selectedPackage() {
        var radio = form.querySelector('input[name="rate_tier_id"]:checked');
        if (!radio) { return null; }
        return { name: radio.dataset.name, price: parseFloat(radio.dataset.price) || 0, currency: radio.dataset.currency || '' };
    }

    /* ================================================================== field errors (same pattern as booking.js) */

    function errorFor(field) {
        var id = field.id === 'messageText' ? 'messageError' : field.id + '-error';
        var node = $(id);
        if (!node) {
            node = document.createElement('p');
            node.id = id; node.className = 'bk-error'; node.hidden = true; node.setAttribute('role', 'alert');
            field.insertAdjacentElement('afterend', node);
        }
        var described = (field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
        if (described.indexOf(id) === -1) { described.push(id); field.setAttribute('aria-describedby', described.join(' ')); }
        return node;
    }

    function mark(field, text) {
        var node = errorFor(field);
        node.textContent = text || '';
        node.hidden = !text;
        field.setAttribute('aria-invalid', text ? 'true' : 'false');
        return !text;
    }

    function note(id, text) {
        var node = $(id);
        if (!node) { return !text; }
        node.textContent = text || '';
        node.hidden = !text;
        return !text;
    }

    /* ================================================================== gifts */

    var gifts = all('[data-gift]').map(function (card) {
        return {
            card: card,
            id: card.dataset.id,
            name: card.dataset.name,
            price: parseFloat(card.dataset.price) || 0,
            currency: card.dataset.currency || '',
            type: card.dataset.type,
            input: card.querySelector('[data-gift-qty]'),
            add: card.querySelector('[data-gift-add]'),
            minus: card.querySelector('[data-gift-minus]'),
            plus: card.querySelector('[data-gift-plus]')
        };
    });

    function qty(gift) { return Math.max(0, Math.min(cfg.giftMaxQty, int(gift.input.value, 0))); }
    function chosenGifts() { return gifts.filter(function (g) { return qty(g) > 0 && !g.card.classList.contains('is-unavailable'); }); }

    function setQty(gift, value, focusAfter) {
        value = Math.max(0, Math.min(cfg.giftMaxQty, value));
        gift.input.value = String(value);
        gift.card.classList.toggle('is-selected', value > 0);
        gift.minus.disabled = value <= 0;
        gift.plus.disabled = value >= cfg.giftMaxQty;
        if (focusAfter) {
            (value > 0 ? (focusAfter === 'plus' ? gift.plus : gift.minus) : gift.add).focus();
        }
        refreshGifts();
        refresh();
    }

    function refreshGifts() {
        var wrap = $('giftDelivery');
        if (!wrap) { return; }
        var chosen = chosenGifts();
        var physical = chosen.some(function (g) { return g.type === 'physical'; });
        var phone = chosen.some(function (g) { return g.type === 'airtime' || g.type === 'shoutout'; });
        wrap.hidden = !(physical || phone);
        $('giftAddressField').hidden = !physical;
        if (!physical) { mark($('giftDeliveryDetails'), ''); }

        var next = form.querySelector('[data-step="4"] [data-next]');
        if (next) {
            var label = chosen.length ? next.dataset.addLabel : next.dataset.skipLabel;
            if (label) { next.firstChild.nodeValue = label + ' '; }
        }
        if (chosen.length <= cfg.giftMaxCount) { note('giftError', ''); }
    }

    function applyCurrency() {
        var pkg = selectedPackage();
        gifts.forEach(function (gift) {
            var unusable = !!pkg && gift.currency !== pkg.currency;
            gift.card.classList.toggle('is-unavailable', unusable);
            gift.add.disabled = unusable;
            if (unusable && qty(gift) > 0) { setQty(gift, 0); }
        });
    }

    gifts.forEach(function (gift) {
        gift.add.addEventListener('click', function () { setQty(gift, 1, 'plus'); announce(gift.name + ' added.'); });
        gift.plus.addEventListener('click', function () { setQty(gift, qty(gift) + 1, 'plus'); });
        gift.minus.addEventListener('click', function () {
            var next = qty(gift) - 1;
            setQty(gift, next, 'minus');
            if (next === 0) { announce(gift.name + ' removed.'); }
        });
        gift.input.addEventListener('change', function () { setQty(gift, int(gift.input.value, 0)); });
        setQty(gift, int(gift.input.value, 0));
    });

    /* ================================================================== shows and days */

    var dateInput = $('scheduledDate');

    function isoDay(value) {
        if (!value) { return 0; }
        var parts = value.split('-');
        var d = new Date(+parts[0], +parts[1] - 1, +parts[2]);
        var day = d.getDay();
        return isNaN(day) ? 0 : (day === 0 ? 7 : day);
    }

    function refreshShows() {
        var day = isoDay(dateInput && dateInput.value);
        all('[data-show-days]').forEach(function (label) {
            var days = (label.dataset.showDays || '').split(',').filter(Boolean).map(Number);
            var off = day > 0 && days.length > 0 && days.indexOf(day) === -1;
            var radio = label.querySelector('input');
            label.classList.toggle('is-unavailable', off);
            label.style.opacity = off ? '.45' : '';
            radio.disabled = off;
            if (off && radio.checked) {
                var any = form.querySelector('input[name="show_id"][value=""]');
                if (any) { any.checked = true; }
            }
        });
    }

    if (dateInput) { dateInput.addEventListener('change', function () { refreshShows(); refresh(); }); }

    /* ================================================================== the summary */

    function setText(id, text) { var el = $(id); if (el) { el.textContent = text; } }

    function refresh() {
        var pkg = selectedPackage();
        var recipient = normalise(form.elements.recipient_name && form.elements.recipient_name.value);
        var message = normalise($('messageText') && $('messageText').value);
        var chosen = chosenGifts();
        var currency = pkg ? pkg.currency : (chosen[0] ? chosen[0].currency : '');
        var total = (pkg ? pkg.price : 0);

        setText('sumPackage', pkg ? pkg.name + ' · ' + money(pkg.price, pkg.currency) : 'Not chosen yet');
        setText('sumRecipient', recipient || '\u2014');
        all('[data-recipient-echo]', document).forEach(function (el) { el.textContent = recipient || 'the person you\u2019re greeting'; });

        var quote = $('sumMessage');
        if (quote) {
            quote.textContent = '';
            if (message) {
                var p = document.createElement('p');
                p.textContent = message.length > 160 ? message.slice(0, 157) + '\u2026' : message;
                quote.appendChild(p);
            }
        }

        var ready = $('voiceReady');
        $('sumVoiceRow').hidden = !(ready && !ready.hidden);

        var list = $('sumGifts');
        list.textContent = '';
        chosen.forEach(function (g) {
            var line = g.price * qty(g);
            total += line;
            var li = document.createElement('li');
            var name = document.createElement('span');
            name.textContent = (qty(g) > 1 ? qty(g) + ' \u00d7 ' : '') + g.name;
            var amount = document.createElement('span');
            amount.textContent = money(line, g.currency);
            li.appendChild(name); li.appendChild(amount);
            list.appendChild(li);
        });
        $('sumGiftsWrap').hidden = chosen.length === 0;
        setText('sumGiftCount', chosen.length === 1 ? '1 gift' : chosen.length + ' gifts');

        var airs = airsText();
        $('sumAirsRow').hidden = !airs;
        setText('sumAirs', airs);

        var totalText = pkg || chosen.length ? money(total, currency) : '\u2014';
        setText('sumTotal', totalText);
        setText('barTotal', totalText);
    }

    function airsText() {
        var show = form.querySelector('input[name="show_id"]:checked');
        var date = dateInput && dateInput.value;
        var parts = [];
        if (show && show.value) { parts.push(show.dataset.name + (show.dataset.time ? ' (' + show.dataset.time + ')' : '')); }
        if (date) {
            var d = new Date(date + 'T00:00:00');
            parts.push(isNaN(d) ? date : d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }));
        }
        return parts.join(', ');
    }

    form.addEventListener('input', refresh);
    form.addEventListener('change', function (e) {
        if (e.target.name === 'rate_tier_id') { applyCurrency(); note('packageError', ''); }
        refresh();
    });
    // booking.js shows and hides the recorder's panels; follow them for the summary.
    var voice = $('voice');
    if (voice && window.MutationObserver) {
        new MutationObserver(refresh).observe(voice, { attributes: true, subtree: true, attributeFilter: ['hidden'] });
    }

    /* ================================================================== checking a step */

    var checks = {
        2: function () {
            var ok = !!selectedPackage();
            note('packageError', ok ? '' : 'Choose a package to continue.');
            return ok ? null : form.querySelector('input[name="rate_tier_id"]');
        },
        3: function () {
            var first = null;
            var recipient = form.elements.recipient_name;
            var message = $('messageText');
            var rec = $('voiceRec');

            if (!mark(recipient, normalise(recipient.value) ? '' : "Enter the name of the person you're greeting.")) { first = first || recipient; }

            var text = normalise(message.value), problem = '';
            if (!text) { problem = 'Write a message.'; }
            else if (text.length > cfg.maxChars) { problem = 'Your message is ' + (text.length - cfg.maxChars) + ' characters over the ' + cfg.maxChars + '-character limit.'; }
            else if (PLACEHOLDER.test(text)) { problem = 'Replace ' + text.match(PLACEHOLDER)[0] + ' with a name before you continue.'; }
            if (!mark(message, problem)) { first = first || message; }

            if (rec && !rec.hidden) {
                note('voiceErr', 'Finish or stop the recording before you continue.');
                first = first || $('stopBtn');
            }
            return first;
        },
        4: function () {
            var chosen = chosenGifts();
            if (chosen.length > cfg.giftMaxCount) {
                note('giftError', 'You can add up to ' + cfg.giftMaxCount + ' different gifts to one greeting.');
                return chosen[0].input;
            }
            var address = $('giftDeliveryDetails');
            if (address && chosen.some(function (g) { return g.type === 'physical'; })) {
                if (!mark(address, normalise(address.value) ? '' : 'Add a delivery address for the gift that will be delivered.')) { return address; }
            }
            return null;
        },
        5: function () {
            var first = null;
            var name = form.elements.sender_name, phone = form.elements.sender_phone, email = form.elements.sender_email;
            if (!mark(name, normalise(name.value) ? '' : 'Enter your name.')) { first = first || name; }
            var digits = phone.value.replace(/\D/g, '').length;
            if (!mark(phone, !phone.value.trim() ? 'Enter your phone number.' : (digits < 7 ? 'Enter a phone number with at least 7 digits.' : ''))) { first = first || phone; }
            if (!mark(email, email.value.trim() === '' || EMAIL.test(email.value.trim()) ? '' : 'Enter a valid email address, or leave it empty.')) { first = first || email; }

            var show = form.querySelector('input[name="show_id"]:checked');
            var needsDay = show && show.value && !(dateInput && dateInput.value);
            var past = dateInput && dateInput.value && dateInput.min && dateInput.value < dateInput.min;
            note('scheduleError', needsDay ? 'Choose the day you would like it read on ' + show.dataset.name + '.' : (past ? 'Choose today or a later day.' : ''));
            if ((needsDay || past) && !first) { first = dateInput; }
            return first;
        }
    };

    /* ================================================================== moving between steps */

    function stepEl(n) { return form.querySelector('[data-step="' + n + '"]'); }

    function updateIndicators() {
        all('[data-step-indicator]', document).forEach(function (li) {
            var n = int(li.dataset.stepIndicator, 0);
            if (n <= 1) { return; }
            var state = n === current ? 'current' : (n <= furthest ? 'done' : 'todo');
            li.dataset.state = state;
            if (n === current) { li.setAttribute('aria-current', 'step'); } else { li.removeAttribute('aria-current'); }
            var btn = li.querySelector('[data-goto]');
            if (btn) { btn.disabled = state === 'todo'; }
            // On a narrow screen the steps scroll sideways: keep the current one in view.
            if (n === current && li.parentElement && li.parentElement.scrollWidth > li.parentElement.clientWidth) {
                li.parentElement.scrollTo({ left: li.offsetLeft - 16, behavior: 'smooth' });
            }
        });
    }

    function show(n, focus) {
        current = n;
        furthest = Math.max(furthest, n);
        steps.forEach(function (s) { s.hidden = int(s.dataset.step, 0) !== n; });
        updateIndicators();
        if (n === order[order.length - 1]) { buildReview(); }

        var bar = $('barNext');
        if (bar) { bar.textContent = n === order[order.length - 1] ? 'Continue to payment' : (n === 5 ? 'Review' : 'Continue'); }

        if (focus !== false) {
            var heading = stepEl(n).querySelector('h2');
            var top = stepEl(n).getBoundingClientRect().top + window.pageYOffset - 16;
            window.scrollTo({ top: Math.max(0, top), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            if (heading) { heading.focus({ preventScroll: true }); }
        }
    }

    // Once a step has been checked, its messages follow what is typed: a fixed field clears straight away.
    var checked = {};
    form.addEventListener('input', function () { if (checked[current] && checks[current]) { checks[current](); } });
    form.addEventListener('change', function () { if (checked[current] && checks[current]) { checks[current](); } });

    function next() {
        checked[current] = true;
        var check = checks[current];
        var bad = check ? check() : null;
        if (bad) {
            announce('Please fix the highlighted fields to continue.');
            if (bad.focus) { bad.focus(); }
            return;
        }
        var i = order.indexOf(current);
        if (i < order.length - 1) { show(order[i + 1]); }
    }

    function back() {
        var i = order.indexOf(current);
        if (i > 0) { show(order[i - 1]); }
    }

    function goTo(n) {
        if (n > furthest || n === current) { return; }
        // Going back is always fine; going forward again re-checks the steps in between.
        if (n > current) {
            for (var s = current; s < n; s++) {
                var bad = checks[s] ? checks[s]() : null;
                if (bad) { show(s); if (bad.focus) { bad.focus(); } return; }
            }
        }
        show(n);
    }

    all('[data-next]').forEach(function (b) { b.addEventListener('click', next); });
    all('[data-back]').forEach(function (b) { b.addEventListener('click', back); });
    all('[data-goto]', document).forEach(function (b) { b.addEventListener('click', function () { goTo(int(b.dataset.goto, 0)); }); });

    var barNext = $('barNext');
    if (barNext) {
        barNext.addEventListener('click', function () {
            if (current === order[order.length - 1]) {
                if (typeof form.requestSubmit === 'function') { form.requestSubmit($('submitBtn')); } else { $('submitBtn').click(); }
            } else {
                next();
            }
        });
    }

    // Enter in a text field moves to the next step instead of sending a half-filled form.
    form.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA' || e.target.type === 'submit' || e.target.tagName === 'BUTTON') { return; }
        if (current !== order[order.length - 1]) { e.preventDefault(); next(); }
    });

    /* ================================================================== review */

    function block(title, step, lines) {
        var wrap = document.createElement('div');
        wrap.className = 'ck-review-block';
        var h = document.createElement('h3');
        h.appendChild(document.createTextNode(title));
        var edit = document.createElement('button');
        edit.type = 'button'; edit.className = 'ck-link'; edit.textContent = 'Edit';
        edit.setAttribute('aria-label', 'Edit ' + title.toLowerCase());
        edit.addEventListener('click', function () { show(step); });
        h.appendChild(edit);
        wrap.appendChild(h);
        lines.forEach(function (line) {
            if (!line) { return; }
            var p = document.createElement('p');
            p.textContent = line.text;
            if (line.className) { p.className = line.className; }
            wrap.appendChild(p);
        });
        return wrap;
    }

    function buildReview() {
        var body = $('reviewBody');
        if (!body) { return; }
        var pkg = selectedPackage();
        var chosen = chosenGifts();
        var voiceReady = $('voiceReady') && !$('voiceReady').hidden;
        var email = form.elements.sender_email.value.trim();
        var phone = $('giftRecipientPhone') ? $('giftRecipientPhone').value.trim() : '';
        var address = $('giftDeliveryDetails') ? normalise($('giftDeliveryDetails').value) : '';

        body.textContent = '';
        body.hidden = false;
        body.appendChild(block('Package', 2, [{ text: pkg ? pkg.name + ' — ' + money(pkg.price, pkg.currency) : 'Not chosen' }]));
        body.appendChild(block('Message', 3, [
            { text: 'For ' + normalise(form.elements.recipient_name.value) },
            { text: $('messageText').value.trim(), className: 'ck-review-msg' },
            voiceReady ? { text: 'With your voice note', className: 'ck-muted' } : null
        ]));
        body.appendChild(block('Gifts', 4, chosen.length
            ? chosen.map(function (g) { return { text: (qty(g) > 1 ? qty(g) + ' × ' : '') + g.name + ' — ' + money(g.price * qty(g), g.currency) }; })
                .concat([phone ? { text: 'Recipient’s phone: ' + phone, className: 'ck-muted' } : null,
                         address ? { text: 'Deliver to: ' + address, className: 'ck-muted' } : null])
            : [{ text: 'No gifts', className: 'ck-muted' }]));
        body.appendChild(block('Your details', 5, [
            { text: normalise(form.elements.sender_name.value) + ' · ' + form.elements.sender_phone.value.trim() },
            email ? { text: email, className: 'ck-muted' } : null,
            { text: airsText() ? 'Airs: ' + airsText() : 'Airs at the next good moment', className: 'ck-muted' }
        ]));
    }

    /* ================================================================== sending */

    // booking.js checks the whole form first (its submit listener was added before this one). When it stops the
    // send because of a field in another step, open that step so the visitor can see what to fix.
    form.addEventListener('submit', function (e) {
        if (!e.defaultPrevented) {
            for (var s = 0; s < order.length - 1; s++) {
                var bad = checks[order[s]] ? checks[order[s]]() : null;
                if (bad) { e.preventDefault(); show(order[s]); if (bad.focus) { bad.focus(); } return; }
            }
            return;
        }
        var invalid = form.querySelector('[aria-invalid="true"]');
        if (!invalid) { return; }
        var owner = invalid.closest('[data-step]');
        if (owner && int(owner.dataset.step, 0) !== current) {
            show(int(owner.dataset.step, 0), false);
            invalid.focus();
        }
    });

    /* ================================================================== start */

    applyCurrency();
    refreshGifts();
    refreshShows();
    refresh();

    // Back from the server with an error: everything typed is still there, so open the review, where the message
    // above it says what to fix and each part has an Edit button. A fresh visit starts at the package.
    if (form.dataset.resume === '1') {
        furthest = order[order.length - 1];
        show(order[order.length - 1], false);
    } else {
        show(order[0], false);
    }
})();
