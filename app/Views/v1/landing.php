<?php
/**
 * The homepage in the v1 UI: what CBS Radio greetings are, every occasion with its real packages and prices, how
 * it works, the shows, gifts, tributes and questions — all from live data and Settings, as before. It stays light:
 * the lean public layout, site.css and home.css, and one small script for "Listen live".
 *
 * @var \Config\CbsRadio                                     $cbs
 * @var \Config\Payments                                     $payments
 * @var bool                                                 $smsOn
 * @var list<array{id: int, name: string, slug: string, description: string, packages: array, isTribute: bool}> $occasions
 * @var list<array{name: string, times: string, days: string, onAirNow: bool}> $shows
 * @var string                                               $sampleShow
 * @var list<array<string, mixed>>                           $gifts
 */
$asset = static fn (string $path): string => base_url($path) . (is_file(FCPATH . $path) ? '?v=' . filemtime(FCPATH . $path) : '');

$tribute = null;
foreach ($occasions as $occasion) {
    if ($occasion['isTribute']) {
        $tribute = $occasion;
        break;
    }
}
$tributeUrl = $tribute ? site_url('booking/' . $tribute['slug']) : site_url('booking');

$liveShow = null;
foreach ($shows as $show) {
    if ($show['onAirNow']) {
        $liveShow = $show;
        break;
    }
}

$contact = array_filter([
    $cbs->contactPhone !== '' ? 'call ' . $cbs->contactPhone : '',
    $cbs->contactEmail !== '' ? 'email ' . $cbs->contactEmail : '',
]);
$tel = $cbs->contactPhone !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $cbs->contactPhone) : '';

$payShort = $payments->mode === 'mobile_money' ? 'Pay by Mobile Money from your phone.' : 'Pay by Mobile Money, and our team confirms it.';
$payLine  = $payments->mode === 'mobile_money'
    ? 'You pay by Mobile Money from your phone.'
    : 'You pay by Mobile Money using our merchant code, and our team confirms the payment.';

$faq = [
    ['How do I pay?', $payLine . ($payments->mode !== 'mobile_money' && $payments->merchantCode !== '' ? ' Our merchant code is ' . $payments->merchantCode . '.' : '')
        . ' One payment covers your greeting and any gifts you add.'],
    ['When will my greeting be read?', 'You choose a show and a day when you book. Every message is reviewed before it goes on air, so book ahead of the day you need it.'],
    ['Can I add a voice note?', 'Yes, up to ' . (int) $cbs->voiceNoteMaxSeconds . ' seconds. You record it on your phone or computer while you book, and your browser will ask to use the microphone.'],
    ['Can I send a gift with my greeting?', 'Yes. After your message, add airtime, an e-card, a shout-out, a song dedication or a delivered gift. It goes to the same person, and you pay once.'],
    ["What if my message can't be approved?", "We'll tell you, and we'll work it out with you. " . ($contact ? 'You can ' . implode(' or ', $contact) . '.' : 'Get in touch with the station.')],
    ['How will I know it has aired?', $smsOn
        ? 'We send you a text message once it has been read out. You can also open the status page you were given at checkout to see where it has got to.'
        : 'Open the status page you were given at checkout. It shows where your greeting has got to.'],
];

$ld = ['@context' => 'https://schema.org', '@type' => 'RadioStation', 'name' => 'CBS Radio', 'url' => site_url('/')];
if ($cbs->contactPhone !== '') {
    $ld['telephone'] = $cbs->contactPhone;
}
if ($cbs->contactEmail !== '') {
    $ld['email'] = $cbs->contactEmail;
}

$sampleGift = $gifts[0] ?? null;
?>
<?= $this->extend(ui_layout('public')) ?>

<?= $this->section('head') ?>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<link rel="stylesheet" href="<?= esc($asset('assets/css/site.css')) ?>">
<link rel="stylesheet" href="<?= esc($asset('assets/css/home.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="hp">

    <!-- ========================================================= hero -->
    <section class="hp-hero" aria-labelledby="hpTitle">
        <div class="ck-wrap">
            <div>
                <?php if ($liveShow !== null): ?>
                    <p class="hp-live"><i aria-hidden="true"></i>On air now &middot; <?= esc($liveShow['name']) ?></p>
                <?php else: ?>
                    <p class="hp-live hp-live--calm"><i aria-hidden="true"></i>Greetings, tributes &amp; gifts on CBS Radio</p>
                <?php endif; ?>

                <h1 id="hpTitle"><?= esc($cbs->heroHeadline) ?></h1>
                <p class="hp-sub"><?= esc($cbs->heroSubheadline) ?></p>

                <div class="hp-actions">
                    <a class="ck-btn ck-btn--gold ck-btn--lg" href="<?= site_url('booking') ?>"><?= cbs_icon_svg('mic', 1.1) ?> Send a greeting</a>
                    <a class="ck-btn ck-btn--lg hp-btn-ghost" href="<?= site_url('gifts') ?>"><?= cbs_icon_svg('gift', 1.1) ?> Send a gift</a>
                    <?php if ($cbs->liveStreamUrl !== ''): ?>
                        <button class="ck-btn ck-btn--lg hp-btn-ghost" id="listenBtn" type="button" aria-pressed="false">
                            <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 1.5v13l11-6.5z" fill="currentColor" /></svg>
                            <span id="listenLabel">Listen live</span>
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($cbs->liveStreamUrl !== ''): ?>
                    <audio id="liveAudio" preload="none" data-src="<?= esc($cbs->liveStreamUrl, 'attr') ?>"></audio>
                    <p class="hp-listen-msg" id="listenMsg" role="status"></p>
                <?php endif; ?>

                <ul class="hp-proof">
                    <li><?= cbs_icon_svg('shield', 1) ?> Read by our team before it airs</li>
                    <li><?= cbs_icon_svg('check', 1) ?> One payment for greeting and gifts</li>
                    <?php if ($smsOn): ?>
                        <li><?= cbs_icon_svg('phone', 1) ?> SMS when it's been read</li>
                    <?php endif; ?>
                </ul>

                <?php if ($contact): ?>
                    <p class="hp-note">Questions? <?= esc(ucfirst(implode(' or ', $contact))) ?>.</p>
                <?php endif; ?>
            </div>

            <div class="hp-stage" aria-hidden="true">
                <div class="hp-stage-inner">
                <div class="hp-player">
                    <div class="hp-player-top">
                        <span class="hp-show"><i></i><?= esc($sampleShow) ?></span>
                        <span class="hp-onair">ON AIR</span>
                    </div>
                    <p class="hp-to">For Mama Ruth</p>
                    <p class="hp-msg">“Happy birthday Mama Ruth, we love you! Many more blessed years.”</p>
                    <p class="hp-from">From Grace and all the children</p>
                    <div class="hp-eq">
                        <?php for ($i = 0; $i < 28; $i++): ?><span></span><?php endfor; ?>
                    </div>
                </div>

                <?php if ($sampleGift !== null): ?>
                    <?php $look = cbs_gift_look($sampleGift['type']); ?>
                    <div class="hp-chip hp-chip--gift">
                        <span class="ck-art ck-art--<?= esc($look['tone'], 'attr') ?>"><?= cbs_icon_svg($look['icon'], 1.2) ?></span>
                        <span><strong><?= esc($sampleGift['name']) ?></strong><span class="hp-chip-sub">added to the greeting</span></span>
                    </div>
                <?php endif; ?>

                <div class="hp-chip hp-chip--ok">
                    <span class="hp-tick"><?= cbs_icon_svg('check', 1.2) ?></span>
                    <span><strong>Approved</strong><span class="hp-chip-sub">Queued for the show</span></span>
                </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================= occasions -->
    <section id="greetings" class="hp-section hp-section--mist" style="padding-top:0" aria-labelledby="greetingsTitle">
        <div class="ck-wrap">
            <div class="hp-occasions-wrap">
                <?php if (empty($occasions)): ?>
                    <div class="hp-empty">
                        <h2 id="greetingsTitle" style="margin:0 0 .5rem;color:var(--ink)">What would you like to say?</h2>
                        <p style="margin:0">Greetings aren't open for booking just yet. Please check back soon<?= $contact ? ', or ' . esc(implode(' or ', $contact)) : '' ?>.</p>
                    </div>
                <?php else: ?>
                    <ul class="hp-occasions">
                        <?php foreach ($occasions as $occasion): ?>
                            <?php
                            $look  = cbs_occasion_look($occasion);
                            $from  = min(array_map(static fn (array $p): float => (float) $p['price'], $occasion['packages']));
                            $money = $occasion['packages'][0]['currency'] ?? 'UGX';
                            ?>
                            <li class="hp-occasion">
                                <div class="hp-occasion-top">
                                    <span class="ck-art ck-art--<?= esc($look['tone'], 'attr') ?>"><?= cbs_icon_svg($look['icon']) ?></span>
                                    <div class="hp-from-price"><span>From</span><strong><?= esc(cbs_money($from, $money)) ?></strong></div>
                                </div>
                                <div>
                                    <h3><?= esc($occasion['name']) ?></h3>
                                    <?php if ($occasion['description'] !== ''): ?>
                                        <p class="hp-occasion-desc"><?= esc($occasion['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <dl class="hp-packages">
                                    <?php foreach ($occasion['packages'] as $package): ?>
                                        <div>
                                            <dt>
                                                <?= esc($package['package_level']) ?>
                                                <?php if (! empty($package['features'])): ?>
                                                    <small><?= esc(cbs_excerpt($package['features'], 80)) ?></small>
                                                <?php endif; ?>
                                            </dt>
                                            <dd><?= esc(cbs_money((float) $package['price'], $package['currency'])) ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                                <a class="ck-btn ck-btn--ink ck-btn--block" href="<?= site_url('booking/' . $occasion['slug']) ?>"
                                    aria-label="Send a <?= esc(strtolower($occasion['name']), 'attr') ?> greeting">Send this greeting <?= cbs_icon_svg('arrow', 1.05) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ========================================================= how it works -->
    <section id="how" class="hp-section" aria-labelledby="howTitle">
        <div class="ck-wrap">
            <div class="hp-head">
                <div>
                    <span class="hp-kicker"><?= cbs_icon_svg('radio', 1) ?> Simple from start to finish</span>
                    <h2 id="howTitle">How it works</h2>
                    <p>From your phone to the airwaves in four steps — usually in under three minutes.</p>
                </div>
                <a class="ck-btn ck-btn--gold" href="<?= site_url('booking') ?>">Start now <?= cbs_icon_svg('arrow', 1.05) ?></a>
            </div>
            <ol class="hp-steps">
                <li class="hp-step">
                    <span class="hp-step-icon"><?= cbs_icon_svg('sparkle', 1.4) ?></span>
                    <h3>Choose the occasion</h3>
                    <p>A birthday, a wedding, a milestone or a tribute — then the package that suits it.</p>
                </li>
                <li class="hp-step">
                    <span class="hp-step-icon"><?= cbs_icon_svg('mic', 1.4) ?></span>
                    <h3>Write it, or say it</h3>
                    <p>Start from an example or write your own. Add a voice note of up to <?= (int) $cbs->voiceNoteMaxSeconds ?> seconds.</p>
                </li>
                <li class="hp-step">
                    <span class="hp-step-icon"><?= cbs_icon_svg('gift', 1.4) ?></span>
                    <h3>Add a gift, pay once</h3>
                    <p>Airtime, an e-card or a song if you like, and pick the show and day. <?= esc($payShort) ?></p>
                </li>
                <li class="hp-step">
                    <span class="hp-step-icon"><?= cbs_icon_svg('radio', 1.4) ?></span>
                    <h3>We read it on air</h3>
                    <p>Every message is reviewed first.<?= $smsOn ? ' We text you once it has been read out.' : ' Follow it on your status page.' ?></p>
                </li>
            </ol>
        </div>
    </section>

    <!-- ========================================================= shows -->
    <?php if (! empty($shows)): ?>
        <section id="shows" class="hp-section hp-section--mist" aria-labelledby="showsTitle">
            <div class="ck-wrap">
                <div class="hp-head">
                    <div>
                        <span class="hp-kicker"><?= cbs_icon_svg('clock', 1) ?> Pick your moment</span>
                        <h2 id="showsTitle">On the air</h2>
                        <p>Your greeting is read out during one of these shows. You choose which when you book.</p>
                    </div>
                </div>
                <ul class="hp-shows">
                    <?php foreach ($shows as $show): ?>
                        <li class="hp-show-card<?= $show['onAirNow'] ? ' is-live' : '' ?>">
                            <?php if ($show['onAirNow']): ?>
                                <span class="hp-live"><i aria-hidden="true"></i>On air now</span>
                            <?php endif; ?>
                            <span class="ck-art ck-art--navy" style="width:2.8rem;height:2.8rem;border-radius:14px;margin-bottom:.5rem"><?= cbs_icon_svg('radio', 1.3) ?></span>
                            <h3><?= esc($show['name']) ?></h3>
                            <span class="hp-show-time"><?= cbs_icon_svg('clock', 1) ?> <?= esc($show['times']) ?></span>
                            <p class="hp-show-days"><?= esc($show['days']) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>

    <!-- ========================================================= gifts -->
    <?php if (! empty($gifts)): ?>
        <section id="gifts" class="hp-section" aria-labelledby="giftsTitle">
            <div class="ck-wrap">
                <div class="hp-head">
                    <div>
                        <span class="hp-kicker"><?= cbs_icon_svg('gift', 1) ?> Make it extra special</span>
                        <h2 id="giftsTitle">Send a gift too</h2>
                        <p>Add one to your greeting and pay once, or send one on its own.</p>
                    </div>
                    <a class="ck-btn ck-btn--line" href="<?= site_url('gifts') ?>">See all gifts <?= cbs_icon_svg('arrow', 1.05) ?></a>
                </div>
                <ul class="hp-gifts">
                    <?php foreach ($gifts as $gift): ?>
                        <?php $look = cbs_gift_look($gift['type']); ?>
                        <li class="hp-gift">
                            <span class="ck-art ck-art--<?= esc($look['tone'], 'attr') ?>"><?= cbs_icon_svg($look['icon']) ?></span>
                            <div>
                                <h3><?= esc($gift['name']) ?></h3>
                                <?php if (! str_contains(strtolower($gift['name']), strtolower(strtok($look['label'], ' ')))): ?>
                                    <p class="hp-gift-kind"><?= esc($look['label']) ?></p>
                                <?php endif; ?>
                                <?php if (! empty($gift['vendor_name'])): ?>
                                    <p class="hp-gift-kind">From <?= esc($gift['vendor_name']) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if (! empty($gift['description'])): ?>
                                <p class="hp-gift-desc"><?= esc(cbs_excerpt($gift['description'], 90)) ?></p>
                            <?php endif; ?>
                            <span class="hp-gift-price"><?= esc(cbs_money((float) $gift['price'], $gift['currency'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>

    <!-- ========================================================= tributes -->
    <?php if ($tribute): ?>
        <section id="tributes" class="hp-tribute" aria-labelledby="tributeTitle">
            <div class="ck-wrap">
                <span class="hp-candle" aria-hidden="true"><?= cbs_icon_svg('candle', 2.2) ?></span>
                <div>
                    <h2 id="tributeTitle">For the moments that matter most</h2>
                    <p>When you want to remember someone, tell us who and what you'd like said. Messages of remembrance are reviewed with care by our team before they air.</p>
                </div>
                <a class="ck-btn ck-btn--gold ck-btn--lg" href="<?= esc($tributeUrl, 'attr') ?>">Send a tribute</a>
            </div>
        </section>
    <?php endif; ?>

    <!-- ========================================================= questions -->
    <section id="faq" class="hp-section hp-section--mist" aria-labelledby="faqTitle">
        <div class="ck-wrap hp-faq">
            <div class="hp-faq-aside">
                <span class="hp-kicker"><?= cbs_icon_svg('alert', 1) ?> Good to know</span>
                <div class="hp-head" style="margin-bottom:0">
                    <div>
                        <h2 id="faqTitle">Questions</h2>
                        <p>The things people ask before they book.</p>
                    </div>
                </div>
                <?php if ($contact): ?>
                    <div class="hp-help">
                        <strong>Still not sure?</strong>
                        <?php if ($tel !== ''): ?>
                            Call us on <a href="<?= esc($tel, 'attr') ?>"><?= esc($cbs->contactPhone) ?></a><?= $cbs->contactEmail !== '' ? ' or email ' : '.' ?>
                        <?php endif; ?>
                        <?php if ($cbs->contactEmail !== ''): ?>
                            <?= $tel === '' ? 'Email ' : '' ?><a href="mailto:<?= esc($cbs->contactEmail, 'attr') ?>"><?= esc($cbs->contactEmail) ?></a>.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="hp-faq-list">
                <?php foreach ($faq as [$question, $answer]): ?>
                    <details>
                        <summary><?= esc($question) ?></summary>
                        <p><?= esc($answer) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ========================================================= closing call to action -->
    <section class="hp-closer" aria-labelledby="closerTitle">
        <div class="ck-wrap">
            <div>
                <h2 id="closerTitle">Ready when you are.</h2>
                <p>Make someone's day — live on CBS Radio.</p>
            </div>
            <div class="hp-actions" style="margin:0">
                <a class="ck-btn ck-btn--ink ck-btn--lg" href="<?= site_url('booking') ?>">Send a greeting</a>
                <a class="ck-btn ck-btn--line ck-btn--lg" href="<?= site_url('gifts') ?>">Send a gift</a>
            </div>
        </div>
    </section>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($cbs->liveStreamUrl !== ''): ?>
    <script>
        (function () {
            var button = document.getElementById('listenBtn'),
                audio = document.getElementById('liveAudio'),
                label = document.getElementById('listenLabel'),
                note = document.getElementById('listenMsg');
            if (!button || !audio) {
                return;
            }

            function reset() {
                button.setAttribute('aria-pressed', 'false');
                label.textContent = 'Listen live';
            }

            function fail() {
                reset();
                note.textContent = "We couldn't connect to the stream just now. Please try again in a moment.";
            }

            button.addEventListener('click', function () {
                note.textContent = '';
                if (audio.paused) {
                    audio.src = audio.getAttribute('data-src');
                    var started = audio.play();
                    if (started && started.catch) {
                        started.catch(fail);
                    }
                } else {
                    audio.pause();
                    audio.removeAttribute('src'); // stop downloading the stream, not just mute it
                    audio.load();
                    reset();
                }
            });
            audio.addEventListener('playing', function () {
                button.setAttribute('aria-pressed', 'true');
                label.textContent = 'Stop listening';
            });
            audio.addEventListener('error', fail);
        })();
    </script>
<?php endif; ?>
<?= $this->endSection() ?>
