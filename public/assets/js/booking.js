/*
 * The booking form: message editor, voice-note recorder, and form checks.
 *
 * Used only by app/Views/booking/create.php, with assets/css/booking.css.
 * No libraries. Every limit comes from data-* attributes on <form id="bookingForm">
 * (which the page fills from Admin > Settings), so nothing is hardcoded here.
 *
 * Three parts, in this order:
 *   1. Message editor  — live count and on-air time, example messages, undo, drafts.
 *   2. Voice note      — record in the browser or choose a file; checked before it is attached.
 *   3. Form checks     — plain-language errors, no double submit.
 *
 * The server checks everything again (the browser can be bypassed), so this file
 * exists to save people a round trip, not to be the only line of defence.
 */
(function () {
    'use strict';

    var form = document.getElementById('bookingForm');
    if (!form) { return; }

    /* ------------------------------------------------------------------ helpers */

    function $(id) { return document.getElementById(id); }
    function int(value, fallback) { var n = parseInt(value, 10); return isNaN(n) ? fallback : n; }
    function show(el, on) { if (el) { el.hidden = !on; } }
    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }
    function normalise(text) { return text.replace(/\s+/g, ' ').trim(); } // matches what the server stores

    function clock(totalSeconds) {
        var s = Math.max(0, Math.round(totalSeconds));
        return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
    }

    function describeSize(bytes) {
        return bytes >= 1048576
            ? (Math.round(bytes / 104857.6) / 10) + ' MB'
            : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    function readableDuration(seconds) {
        if (seconds < 60) { return plural(Math.max(1, seconds), 'second', 'seconds'); }
        var m = Math.floor(seconds / 60), s = seconds % 60;
        return m + ' min' + (s ? ' ' + s + ' s' : '');
    }

    var announceEl = $('announce');
    /** Says something to screen-reader users without moving focus. */
    function announce(message) {
        if (!announceEl) { return; }
        announceEl.textContent = '';
        window.setTimeout(function () { announceEl.textContent = message; }, 30);
    }

    var cfg = {
        maxChars:  int(form.dataset.maxChars, 500),
        wpm:       int(form.dataset.wpm, 150),
        draftKey:  'cbs.draft.' + (form.dataset.draftKey || 'default'),
        minSec:    int(form.dataset.minSeconds, 5),
        maxSec:    int(form.dataset.maxSeconds, 30),
        maxKB:     int(form.dataset.maxKb, 2048),
        exts:      (form.dataset.exts || 'mp3,m4a,wav,ogg,opus,webm,aac').split(','),
        templates: []
    };

    try { cfg.templates = JSON.parse(form.dataset.templates || '[]'); } catch (e) { cfg.templates = []; }

    form.classList.add('bk-js');
    form.setAttribute('novalidate', ''); // our messages replace the browser's pop-ups; without this script the browser's still apply

    var message   = $('messageText');
    var recipient = form.elements.recipient_name;
    var sender    = form.elements.sender_name;

    /* ================================================================== 1. MESSAGE EDITOR */

    var editor = (function () {
        var editorEl  = $('messageEditor');
        var charLine  = $('charLine');
        var timeLine  = $('timeLine');
        var meterFill = $('meterFill');
        var clearBtn  = $('clearBtn');
        var undoBtn   = $('undoBtn');
        var warnEl    = $('placeholderWarn');

        var PLACEHOLDER = /\[(their name|your name)\]/i;
        var undoValue = null;
        var lastState = 'ok';

        function update() {
            var text  = normalise(message.value);
            var len   = text.length;
            var ratio = len / cfg.maxChars;
            var state = len > cfg.maxChars ? 'over' : (ratio >= 0.9 ? 'near' : 'ok');

            charLine.textContent = len > cfg.maxChars
                ? (len - cfg.maxChars) + ' over the ' + cfg.maxChars + '-character limit'
                : len + ' of ' + cfg.maxChars + ' characters';

            var words = text === '' ? 0 : text.split(' ').length;
            timeLine.textContent = words === 0
                ? 'Start typing to see how long it takes to read out.'
                : 'About ' + readableDuration(Math.max(1, Math.round(words / cfg.wpm * 60))) + ' on air';

            meterFill.style.width = Math.min(100, Math.round(ratio * 100)) + '%';
            editorEl.classList.toggle('bk-near', state === 'near');
            editorEl.classList.toggle('bk-over', state === 'over');
            message.setAttribute('aria-invalid', state === 'over' ? 'true' : 'false');

            // Say it once when the state changes, not on every key.
            if (state !== lastState) {
                if (state === 'near') { announce('You are close to the ' + cfg.maxChars + '-character limit.'); }
                if (state === 'over') { announce('Your message is over the character limit.'); }
                lastState = state;
            }

            var marker = text.match(PLACEHOLDER);
            warnEl.textContent = marker ? 'Replace ' + marker[0] + ' with a name before sending.' : '';
            show(warnEl, !!marker);

            show(clearBtn, text !== '');
            return len;
        }

        function setText(value, why) {
            undoValue = message.value;
            message.value = value;
            show(undoBtn, undoValue !== '' && undoValue !== value);
            update();
            drafts.save();
            if (why) { announce(why); }
        }

        message.addEventListener('input', function () { undoValue = null; show(undoBtn, false); update(); drafts.save(); });
        clearBtn.addEventListener('click', function () { setText('', 'Message cleared. Undo is available.'); message.focus(); });
        undoBtn.addEventListener('click', function () {
            var back = undoValue; undoValue = null; show(undoBtn, false);
            if (back !== null) { message.value = back; update(); drafts.save(); announce('Restored.'); }
            message.focus();
        });

        // Names typed *after* an example was chosen fill the markers the example left.
        function fillMarker(field, marker) {
            if (!field) { return; }
            field.addEventListener('change', function () {
                var name = field.value.trim();
                if (name && marker.test(message.value)) { setText(message.value.replace(new RegExp(marker.source, 'gi'), name), ''); }
            });
        }
        fillMarker(recipient, /\[their name\]/);
        fillMarker(sender, /\[your name\]/);

        return { update: update, setText: setText, charCount: update };
    })();

    /* ---- example messages ("Start from an example") ---- */

    (function templates() {
        var box = $('templates');
        if (!box || !cfg.templates.length) { return; }

        var langBar  = $('templateLangs');
        var chipsEl  = $('templateChips');
        var replace  = $('replaceNotice');
        var replaceT = $('replaceText');
        var pending  = null;

        var languages = [];
        cfg.templates.forEach(function (t) { if (languages.indexOf(t.language) === -1) { languages.push(t.language); } });
        var active = languages[0];

        function fill(body) {
            var to   = (recipient.value || '').trim()  || '[their name]';
            var from = (sender.value || '').trim()     || '[your name]';
            return body.replace(/\{recipient\}/g, to).replace(/\{sender\}/g, from);
        }

        function insert(template) {
            pending = null; show(replace, false);
            editor.setText(fill(template.body), 'Example added. Change it however you like.');
            message.focus();
            message.setSelectionRange(message.value.length, message.value.length);
        }

        function choose(template) {
            if (normalise(message.value) === '') { insert(template); return; }
            pending = template;
            replaceT.textContent = 'Replace what you have written with "' + template.title + '"?';
            show(replace, true);
            $('replaceYes').focus();
        }

        function render() {
            chipsEl.textContent = '';
            cfg.templates.filter(function (t) { return t.language === active; }).forEach(function (t) {
                var b = document.createElement('button');
                b.type = 'button'; b.className = 'bk-chip'; b.textContent = t.title;
                b.addEventListener('click', function () { choose(t); });
                chipsEl.appendChild(b);
            });

            langBar.textContent = '';
            if (languages.length > 1) {
                languages.forEach(function (lang) {
                    var p = document.createElement('button');
                    p.type = 'button'; p.className = 'bk-pill'; p.textContent = lang;
                    p.setAttribute('aria-pressed', lang === active ? 'true' : 'false');
                    p.addEventListener('click', function () { active = lang; render(); });
                    langBar.appendChild(p);
                });
            }
            show(langBar, languages.length > 1);
        }

        $('replaceYes').addEventListener('click', function () { if (pending) { insert(pending); } });
        $('replaceNo').addEventListener('click', function () { pending = null; show(replace, false); message.focus(); });

        render();
        show(box, true);
    })();

    /* ---- drafts: kept on this device only, so a refresh or a dropped connection costs nothing ---- */

    var drafts = (function () {
        var TTL = 7 * 24 * 3600 * 1000;
        var notice = $('draftNotice');
        var timer = null;

        function read() {
            try {
                var d = JSON.parse(window.localStorage.getItem(cfg.draftKey) || 'null');
                return d && d.t && (Date.now() - d.t) < TTL && normalise(d.message || '') ? d : null;
            } catch (e) { return null; }
        }

        function write() {
            try {
                if (normalise(message.value) === '') { window.localStorage.removeItem(cfg.draftKey); return; }
                window.localStorage.setItem(cfg.draftKey, JSON.stringify({
                    message: message.value,
                    recipient: recipient ? recipient.value : '',
                    sender: sender ? sender.value : '',
                    t: Date.now()
                }));
            } catch (e) { /* private mode or storage full: drafts are a convenience, never a requirement */ }
        }

        function ago(ms) {
            var m = Math.round(ms / 60000);
            if (m < 1) { return 'just now'; }
            if (m < 60) { return plural(m, 'minute', 'minutes') + ' ago'; }
            var h = Math.round(m / 60);
            return h < 24 ? plural(h, 'hour', 'hours') + ' ago' : plural(Math.round(h / 24), 'day', 'days') + ' ago';
        }

        function drop() { try { window.localStorage.removeItem(cfg.draftKey); } catch (e) { /* nothing to do */ } }

        function save() {
            window.clearTimeout(timer);
            timer = window.setTimeout(write, 700);
        }

        function offer() {
            var d = read();
            if (!d || !notice || normalise(message.value) !== '') { return; } // never overwrite what's already there
            $('draftAge').textContent = ago(Date.now() - d.t);
            show(notice, true);

            $('draftRestore').addEventListener('click', function () {
                message.value = d.message;
                if (recipient && !recipient.value) { recipient.value = d.recipient || ''; }
                if (sender && !sender.value) { sender.value = d.sender || ''; }
                show(notice, false);
                editor.update();
                announce('Your draft is back.');
                message.focus();
            });
            $('draftDiscard').addEventListener('click', function () { drop(); show(notice, false); message.focus(); });
        }

        [recipient, sender].forEach(function (f) { if (f) { f.addEventListener('input', save); } });
        offer();

        return { save: save, drop: drop };
    })();

    editor.update();

    /* ================================================================== 2. VOICE NOTE */

    var voice = (function () {
        var root = $('voice');
        if (!root) { return { isBusy: function () { return false; }, focusStop: function () {} }; }

        var el = {
            idle: $('voiceIdle'), rec: $('voiceRec'), ready: $('voiceReady'),
            recBtn: $('recBtn'), pickBtn: $('pickBtn'), stopBtn: $('stopBtn'),
            time: $('recTime'), bar: $('recBar'), level: $('recLevel'),
            player: $('voicePlayer'), meta: $('voiceMeta'), again: $('againBtn'), remove: $('removeBtn'),
            msg: $('voiceMsg'), err: $('voiceErr'), unavailable: $('voiceUnavailable'),
            input: $('voiceNoteInput'), duration: $('voiceDuration')
        };

        // Can we hand a recording to the form? (DataTransfer is how a File gets into an <input type=file>.)
        var canAttach = (function () {
            try { var dt = new DataTransfer(); dt.items.add(new File(['x'], 'x.txt')); return dt.files.length === 1; } catch (e) { return false; }
        })();
        var canRecord = canAttach && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) && typeof window.MediaRecorder === 'function';

        var state = 'idle';
        var waiting = false;
        var objectUrl = null;
        var stream = null, recorder = null, chunks = [], ticker = null, startedAt = 0, seconds = 0, why = 'user';
        var audioCtx = null, levelFrame = null;
        var peak = 0, meterWorking = false, silenceWarned = false;
        var SILENT = 0.008; // roughly -42 dBFS: below this the microphone is effectively hearing nothing

        function setState(next) {
            state = next;
            show(el.idle, next === 'idle');
            show(el.rec, next === 'recording');
            show(el.ready, next === 'ready');
        }
        function say(text) { el.msg.textContent = text || ''; }
        function fail(text) { say(''); el.err.textContent = text; show(el.err, true); } // a failure replaces any earlier hint, never sits beside it
        function reset() { el.err.textContent = ''; show(el.err, false); say(''); }
        function busy(on, text) {
            waiting = on;
            el.recBtn.disabled = on; el.pickBtn.disabled = on;
            say(text || '');
        }

        // The buttons stand in for the plain file picker, so keep the picker out of keyboard and screen-reader reach.
        el.input.tabIndex = -1;
        el.input.setAttribute('aria-hidden', 'true');

        if (!canRecord) {
            show(el.recBtn, false);
            var secure = window.isSecureContext || /^(localhost|127\.0\.0\.1)$/.test(location.hostname);
            el.unavailable.textContent = secure
                ? "Recording isn't available in this browser, but you can choose an audio file you've already made."
                : "Recording needs a secure (https) connection, but you can choose an audio file you've already made.";
            show(el.unavailable, true);
        }

        /* ---- attaching, previewing, removing ---- */

        function describe(file, secs) {
            var ext = (file.name.split('.').pop() || '').toUpperCase();
            return (secs === null ? 'Length not checked' : clock(secs)) + ' \u00B7 ' + describeSize(file.size) + (ext ? ' \u00B7 ' + ext : '');
        }

        /** The file is already in the input (picked) or gets put there (recorded). */
        function attach(file, secs, alreadyInInput) {
            if (!alreadyInInput) {
                try {
                    var dt = new DataTransfer(); dt.items.add(file); el.input.files = dt.files;
                } catch (e) {
                    return fail("This browser couldn't attach the recording. Please choose an audio file instead.");
                }
            }
            el.duration.value = secs === null ? '' : String(secs);

            if (objectUrl) { URL.revokeObjectURL(objectUrl); }
            objectUrl = URL.createObjectURL(file);

            // Browser-recorded WebM has no length in its header, so the player would show "0:00" and a dead scrub bar.
            // Seeking far past the end makes the browser work the length out; then jump back to the start.
            el.player.onloadedmetadata = function () {
                if (el.player.duration === Infinity) {
                    el.player.currentTime = 1e101;
                    el.player.ontimeupdate = function () { el.player.ontimeupdate = null; el.player.currentTime = 0; };
                }
            };
            el.player.src = objectUrl;
            el.meta.textContent = describe(file, secs);

            setState('ready');
            announce('Voice note ready' + (secs === null ? '' : ', ' + readableDuration(secs)) + '. You can play it, record again, or remove it.');
        }

        function removeNote() {
            try { el.input.value = ''; } catch (e) { /* older browsers */ }
            if (el.input.files && el.input.files.length) { el.input.files = new DataTransfer().files; }
            el.duration.value = '';
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
            el.player.removeAttribute('src');
            el.player.load();
            reset();
            setState('idle');
            (canRecord ? el.recBtn : el.pickBtn).focus();
        }

        /* ---- recording ---- */

        function pickMime() {
            var wanted = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/ogg'];
            for (var i = 0; i < wanted.length; i++) {
                if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(wanted[i])) { return wanted[i]; }
            }
            return '';
        }

        // The file must be named for what it really is: Safari records MP4, Chrome and Firefox record WebM or Ogg.
        function extensionFor(type) {
            type = (type || '').toLowerCase();
            if (type.indexOf('mp4') > -1) { return 'm4a'; }
            if (type.indexOf('ogg') > -1) { return 'ogg'; }
            return 'webm';
        }

        function micProblem(e) {
            var name = e && e.name;
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'SecurityError') {
                return "We can't use your microphone. Allow microphone access for this site (look for the lock or microphone icon next to the address), then try again. Or choose an audio file instead.";
            }
            if (name === 'NotFoundError' || name === 'OverconstrainedError') {
                return 'No microphone was found on this device. You can choose an audio file instead.';
            }
            if (name === 'NotReadableError' || name === 'AbortError') {
                return 'Your microphone is busy or unavailable. Another app may be using it. Close that app and try again.';
            }
            return "We couldn't start recording. You can choose an audio file instead.";
        }

        function releaseStream() {
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
        }

        function startMeter() {
            try {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) { return; }
                audioCtx = new AC();
                if (audioCtx.resume) { audioCtx.resume(); }
                var analyser = audioCtx.createAnalyser();
                analyser.fftSize = 512;
                audioCtx.createMediaStreamSource(stream).connect(analyser);
                var data = new Uint8Array(analyser.fftSize);
                (function loop() {
                    analyser.getByteTimeDomainData(data);
                    var sum = 0;
                    for (var i = 0; i < data.length; i++) { var v = (data[i] - 128) / 128; sum += v * v; }
                    var rms = Math.sqrt(sum / data.length);
                    if (rms > peak) { peak = rms; }
                    meterWorking = true;
                    el.level.style.width = Math.min(100, Math.round(rms * 260)) + '%';
                    levelFrame = window.requestAnimationFrame(loop);
                })();
            } catch (e) { /* the meter is a nicety; recording works without it */ }
        }

        function stopMeter() {
            if (levelFrame) { window.cancelAnimationFrame(levelFrame); levelFrame = null; }
            if (audioCtx) { try { audioCtx.close(); } catch (e) { /* already closed */ } audioCtx = null; }
            el.level.style.width = '0';
        }

        function tick() {
            var elapsed = (performance.now() - startedAt) / 1000;
            el.time.textContent = clock(elapsed);
            el.bar.style.width = Math.min(100, (elapsed / cfg.maxSec) * 100) + '%';
            // A muted or blocked microphone records silence, and the person only finds out after sending. Say so early.
            // (Only when the level meter is really running, or a browser without one would always look "silent".)
            if (meterWorking && elapsed >= 3) {
                if (peak < SILENT && !silenceWarned) {
                    silenceWarned = true;
                    say("We can't hear anything yet. Check that your microphone isn't muted, and that you're close to it.");
                } else if (peak >= SILENT && silenceWarned) {
                    silenceWarned = false; say('');
                }
            }
            if (elapsed >= cfg.maxSec) { why = 'limit'; stopNow(); }
        }

        function stopNow() {
            if (!recorder || recorder.state === 'inactive') { return; }
            seconds = Math.min((performance.now() - startedAt) / 1000, cfg.maxSec);
            recorder.stop();
        }

        function finish() {
            window.clearInterval(ticker); ticker = null;
            stopMeter(); releaseStream();
            setState('idle');
            if (why === 'discard') { return; }

            if (why === 'error' || !chunks.length) { return fail("The recording didn't come through. Please try again."); }

            if (seconds < cfg.minSec - 0.25) {
                return fail('That was only ' + plural(Math.max(1, Math.round(seconds)), 'second', 'seconds') + '. A voice note needs to be at least '
                    + cfg.minSec + ' seconds. Try again and keep going a little longer.');
            }

            var type = (recorder && recorder.mimeType) || 'audio/webm';
            var blob = new Blob(chunks, { type: type });

            if (blob.size > cfg.maxKB * 1024) {
                return fail('That recording is ' + describeSize(blob.size) + ' and the largest allowed is ' + describeSize(cfg.maxKB * 1024) + '. Try a shorter one.');
            }

            attach(new File([blob], 'voice-note.' + extensionFor(type), { type: type, lastModified: Date.now() }), Math.round(seconds), false);
            var notes = [];
            if (why === 'limit') { notes.push('Stopped at the ' + cfg.maxSec + '-second limit.'); }
            if (meterWorking && peak < SILENT) { notes.push('This recording sounds silent. Play it back to check, or record again.'); }
            say(notes.join(' ') + (why === 'limit' && !notes[1] ? ' Listen to it, or record again for a different take.' : ''));
        }

        async function start() {
            if (waiting || state === 'recording') { return; }
            reset();
            busy(true, 'Waiting for permission to use your microphone\u2026');

            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
            } catch (e) {
                busy(false);
                return fail(micProblem(e));
            }
            busy(false);

            var mime = pickMime();
            var options = mime ? { mimeType: mime, audioBitsPerSecond: 32000 } : { audioBitsPerSecond: 32000 };

            try {
                recorder = new MediaRecorder(stream, options);
            } catch (e) {
                releaseStream();
                return fail("This browser couldn't start recording. You can choose an audio file instead.");
            }

            chunks = []; why = 'user'; seconds = 0; peak = 0; meterWorking = false; silenceWarned = false;
            recorder.ondataavailable = function (ev) { if (ev.data && ev.data.size) { chunks.push(ev.data); } };
            recorder.onstop = finish;
            recorder.onerror = function () { why = 'error'; stopNow(); };
            recorder.start(250);
            startedAt = performance.now();

            el.time.textContent = '0:00'; el.bar.style.width = '0';
            setState('recording');
            startMeter();
            ticker = window.setInterval(tick, 100);
            announce('Recording started. Speak now.');
            el.stopBtn.focus();
        }

        /* ---- choosing an existing file ---- */

        function measureDuration(file) {
            return new Promise(function (resolve) {
                var url = URL.createObjectURL(file);
                var probe = new Audio();
                var settled = false;

                function done(value) {
                    if (settled) { return; }
                    settled = true; URL.revokeObjectURL(url); resolve(value);
                }

                // Some containers (WebM from a recorder) report no length up front, so decode as a fallback.
                function decode() {
                    var AC = window.AudioContext || window.webkitAudioContext;
                    if (!AC || !file.arrayBuffer) { return done(null); }
                    file.arrayBuffer().then(function (buf) {
                        var ctx = new AC();
                        return ctx.decodeAudioData(buf).then(function (decoded) { try { ctx.close(); } catch (e) { /* fine */ } done(decoded.duration); });
                    }).catch(function () { done(null); });
                }

                probe.preload = 'metadata';
                probe.onloadedmetadata = function () { isFinite(probe.duration) && probe.duration > 0 ? done(probe.duration) : decode(); };
                probe.onerror = decode;
                probe.src = url;
                window.setTimeout(function () { if (!settled) { decode(); } }, 4000);
                window.setTimeout(function () { done(null); }, 9000);
            });
        }

        function rejectPicked(text) {
            try { el.input.value = ''; } catch (e) { /* older browsers */ }
            fail(text);
        }

        function onPick() {
            var file = el.input.files && el.input.files[0];
            if (!file) { return; } // the picker was cancelled
            reset();

            var ext = (file.name.split('.').pop() || '').toLowerCase();
            var type = file.type || '';
            var typeOk = type === '' || /^audio\//.test(type) || (type === 'video/webm' && ext === 'webm') || (type === 'video/mp4' && ext === 'm4a');

            if (cfg.exts.indexOf(ext) === -1 || !typeOk) {
                return rejectPicked("That isn't an audio file we can use. Choose an " + cfg.exts.map(function (e) { return e.toUpperCase(); }).join(', ').replace(/, ([^,]*)$/, ' or $1') + ' file.');
            }

            if (file.size > cfg.maxKB * 1024) {
                return rejectPicked('That file is ' + describeSize(file.size) + ' and the largest allowed is ' + describeSize(cfg.maxKB * 1024) + '. Choose a shorter recording.');
            }

            busy(true, 'Checking the length\u2026');
            measureDuration(file).then(function (secs) {
                busy(false);
                if (secs === null) {
                    attach(file, null, true);
                    return say("We couldn't check how long this is, so we'll check it when you send.");
                }
                var whole = Math.round(secs);
                if (secs < cfg.minSec - 0.25) {
                    return rejectPicked('That recording is ' + plural(Math.max(1, whole), 'second', 'seconds') + ' long. A voice note needs to be at least ' + cfg.minSec + ' seconds.');
                }
                if (secs > cfg.maxSec + 0.25) {
                    return rejectPicked('That recording is ' + readableDuration(whole) + ' long. The longest allowed is ' + cfg.maxSec + ' seconds. Trim it, or record a shorter one.');
                }
                attach(file, whole, true);
            });
        }

        /* ---- wiring ---- */

        el.recBtn.addEventListener('click', start);
        el.stopBtn.addEventListener('click', function () { why = 'user'; stopNow(); });
        el.pickBtn.addEventListener('click', function () { el.input.click(); });
        el.input.addEventListener('change', onPick);
        el.remove.addEventListener('click', removeNote);
        el.again.addEventListener('click', function () { removeNote(); if (canRecord) { start(); } });

        window.addEventListener('pagehide', function () {
            if (state === 'recording') { why = 'discard'; stopNow(); }
            releaseStream();
        });

        return {
            isBusy: function () { return state === 'recording' || waiting; },
            focusStop: function () { (state === 'recording' ? el.stopBtn : el.recBtn).focus(); },
            say: say, fail: fail
        };
    })();

    /* ================================================================== 3. FORM CHECKS */

    (function guard() {
        var submitBtn = $('submitBtn');
        var submitLabel = submitBtn ? submitBtn.textContent : '';
        var packageErr = $('packageError');
        var touched = {};

        var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        var fields = [
            { el: sender,                      need: 'Enter your name.' },
            { el: form.elements.sender_phone,  need: 'Enter your phone number.',
              check: function (v) { return v.replace(/\D/g, '').length >= 7 ? '' : 'Enter a phone number with at least 7 digits.'; } },
            { el: form.elements.sender_email,
              check: function (v) { return v === '' || EMAIL.test(v) ? '' : 'Enter a valid email address, or leave it empty.'; } },
            { el: recipient,                   need: "Enter the recipient's name." },
            { el: message,                     need: 'Write a message.', errId: 'messageError',
              check: function (v) {
                  var text = normalise(v), marker = text.match(/\[(their name|your name)\]/i);
                  if (text.length > cfg.maxChars) { return 'Your message is ' + (text.length - cfg.maxChars) + ' characters over the ' + cfg.maxChars + '-character limit.'; }
                  return marker ? 'Replace ' + marker[0] + ' with a name before sending.' : '';
              } }
        ].filter(function (f) { return !!f.el; });

        function errorEl(field) {
            var id = field.errId || (field.el.id + '-error');
            var node = $(id);
            if (!node) {
                node = document.createElement('p');
                node.id = id; node.className = 'bk-error'; node.hidden = true;
                field.el.insertAdjacentElement('afterend', node);
            }
            var described = (field.el.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
            if (described.indexOf(id) === -1) { described.push(id); field.el.setAttribute('aria-describedby', described.join(' ')); }
            return node;
        }

        function problem(field) {
            var v = field.el.value.trim();
            if (field.need && v === '') { return field.need; }
            return field.check ? field.check(field.el.value) : '';
        }

        function mark(field, text) {
            var node = errorEl(field);
            node.textContent = text;
            node.hidden = text === '';
            field.el.setAttribute('aria-invalid', text ? 'true' : 'false');
        }

        function checkPackage() {
            var chosen = form.querySelector('input[name="rate_tier_id"]:checked');
            if (packageErr) {
                packageErr.textContent = chosen ? '' : 'Choose a package to continue.';
                packageErr.hidden = !!chosen;
            }
            return !!chosen;
        }

        // Don't scold someone for an EMPTY field just because they moved past it: the message appears under the
        // field, pushes the page down, and the tap they were making on the next control lands on the wrong thing.
        // Until they first press Send, only a problem with something they actually typed is reported.
        var attempted = false;

        // A message that appears when a field loses focus moves everything below it. If the person left the field
        // by tapping another control, that control moves out from under their finger and the tap is lost. So an
        // error caused by *leaving* a field waits until any press has finished, plus a moment, before it appears.
        var pointerDown = false, waiting = [];
        function release() {
            pointerDown = false;
            if (waiting.length) {
                window.setTimeout(function () { var run = waiting; waiting = []; run.forEach(function (fn) { fn(); }); }, 0);
            }
        }
        document.addEventListener('pointerdown', function () { pointerDown = true; }, true);
        document.addEventListener('pointerup', release, true);
        document.addEventListener('pointercancel', release, true);
        function whenSettled(fn) { if (pointerDown) { waiting.push(fn); } else { fn(); } }

        fields.forEach(function (f) {
            f.el.addEventListener('blur', function () {
                touched[f.el.name] = true;
                if (!attempted && (f.el.value.trim() === '' || f.el === message)) { return; } // the message has its own live warning
                window.setTimeout(function () {
                    if (document.activeElement === f.el) { return; } // they came back to it
                    whenSettled(function () { mark(f, problem(f)); });
                }, 250);
            });
            f.el.addEventListener('input', function () {
                if (attempted || f.el.getAttribute('aria-invalid') === 'true') { mark(f, problem(f)); }
            });
        });
        form.querySelectorAll('input[name="rate_tier_id"]').forEach(function (r) { r.addEventListener('change', checkPackage); });

        function lock() {
            if (!submitBtn) { return; }
            submitBtn.disabled = true; submitBtn.textContent = 'Sending\u2026'; form.setAttribute('aria-busy', 'true');
        }
        function unlock() {
            if (!submitBtn) { return; }
            submitBtn.disabled = false; submitBtn.textContent = submitLabel; form.removeAttribute('aria-busy');
        }

        form.addEventListener('submit', function (e) {
            if (voice.isBusy()) {
                e.preventDefault();
                voice.fail('Finish or stop the recording before you send.');
                voice.focusStop();
                return;
            }

            attempted = true;
            var firstBad = null;

            if (!checkPackage()) { firstBad = form.querySelector('input[name="rate_tier_id"]'); }

            fields.forEach(function (f) {
                var text = problem(f);
                mark(f, text);
                if (text && !firstBad) { firstBad = f.el; }
            });

            if (firstBad) {
                e.preventDefault();
                announce('Please fix the highlighted fields before sending.');
                firstBad.focus();
                return;
            }

            drafts.drop();
            lock();
        });

        // Coming back to the page with the back button leaves it "Sending..." otherwise.
        window.addEventListener('pageshow', function (ev) { if (ev.persisted) { unlock(); } });
    })();
})();
