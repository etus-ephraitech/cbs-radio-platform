<?= $this->extend(ui_layout('public')) ?>

<?php
// Small inline icons for the gift list, by gift type (stroke only, inherit colour).
$giftIcons = [
    'ecard'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
    'airtime'    => '<rect x="6.5" y="3" width="11" height="18" rx="2"/><path d="M11 18h2"/>',
    'shoutout'   => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>',
    'dedication' => '<path d="M9 18V5l11-2v13"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="17.5" cy="16" r="2.5"/>',
    'physical'   => '<rect x="3" y="9" width="18" height="12" rx="1"/><path d="M12 9v12M3 13h18M12 9C9 9 7 7.5 8 6s4 .5 4 3c0-2.5 3-4.5 4-3s-1 3-4 3"/>',
];

$tribute    = null;
foreach ($occasions as $occasion) {
    if ($occasion['isTribute']) {
        $tribute = $occasion;
        break;
    }
}
$tributeUrl = $tribute ? site_url('booking/' . $tribute['slug']) : site_url('booking');

$contact = array_filter([
    $cbs->contactPhone !== '' ? 'call ' . $cbs->contactPhone : '',
    $cbs->contactEmail !== '' ? 'email ' . $cbs->contactEmail : '',
]);

$payShort = $payments->mode === 'mobile_money'
    ? 'Pay by Mobile Money from your phone.'
    : 'We show you how to pay, and our team confirms it.';

$payLine = $payments->mode === 'mobile_money'
    ? 'You pay by Mobile Money from your phone.'
    : 'We show you the amount, a reference and how to pay. Once our team confirms your payment, your greeting goes to review.';

$faq = [
    [
        'How do I pay?',
        $payLine . ($payments->mode !== 'mobile_money' && $payments->merchantCode !== ''
            ? ' Our merchant code is ' . $payments->merchantCode . '.' : ''),
    ],
    [
        'When will my greeting be read?',
        'You choose a show and a day when you book. Every message is reviewed before it goes on air, so book ahead of the day you need it.',
    ],
    [
        'Can I add a voice note?',
        'Yes, up to ' . (int) $cbs->voiceNoteMaxSeconds . ' seconds. You record it on your phone or computer while you book, and your browser will ask to use the microphone.',
    ],
    [
        "What if my message can't be approved?",
        "We'll tell you, and we'll work it out with you. "
            . ($contact ? 'You can ' . implode(' or ', $contact) . '.' : 'Get in touch with the station.'),
    ],
    [
        'How will I know it has aired?',
        $smsOn
            ? "We send you a text message once it has been read out. You can also open the booking page you were given at checkout to see where it has got to."
            : 'Open the booking page you were given at checkout. It shows where your greeting has got to.',
    ],
];

$ld = ['@context' => 'https://schema.org', '@type' => 'RadioStation', 'name' => 'CBS Radio', 'url' => site_url('/')];

if ($cbs->contactPhone !== '') {
    $ld['telephone'] = $cbs->contactPhone;
}

if ($cbs->contactEmail !== '') {
    $ld['email'] = $cbs->contactEmail;
}
?>

<?= $this->section('head') ?>
<script type="application/ld+json">
    <?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>
<style>
    .lp section {
        padding-block: clamp(3.5rem, 8vw, 6rem);
    }

    .lp h2 {
        font-size: clamp(2rem, 4.6vw, 3.1rem);
        margin: 0 0 .75rem;
        text-wrap: balance;
    }

    .lp h3 {
        font-weight: 700;
        letter-spacing: -0.01em;
    }

    .lp p {
        text-wrap: pretty;
    }

    .lp .lede {
        color: var(--muted);
        max-width: 52ch;
        font-size: 1.15rem;
        margin: 0 0 2.5rem;
    }

    .lp .on-dark .lede {
        color: #C9D3EC;
    }

    .lp .mist {
        background: var(--mist);
    }

    .lp .on-dark {
        background: var(--ink);
        color: #fff;
    }

    /* ============================ hero ============================ */
    .hero {
        padding-block: clamp(2.5rem, 6vw, 4.5rem) clamp(3.5rem, 7vw, 6rem) !important;
    }

    .hero .cbs-wrap {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr);
        gap: clamp(2rem, 5vw, 4.5rem);
        align-items: center;
    }

    .hero h1 {
        font-size: clamp(3.25rem, 9vw, 6.5rem);
        line-height: .93;
        letter-spacing: -0.035em;
        margin: 0 0 1.25rem;
        text-wrap: balance;
    }

    .hero .sub {
        font-size: clamp(1.1rem, 2.2vw, 1.35rem);
        color: #D6DEF2;
        max-width: 34ch;
        margin: 0 0 2rem;
    }

    .hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .85rem;
    }

    .hero-note {
        margin: 1.5rem 0 0;
        color: #AEB9D6;
        font-size: .975rem;
        max-width: 44ch;
    }

    .listen-msg {
        margin: .85rem 0 0;
        color: #FFD27A;
        font-size: .95rem;
        min-height: 1.4em;
    }

    .radio {
        position: relative;
    }

    .radio svg {
        display: block;
        width: 100%;
        height: auto;
    }

    .radio .needle {
        transform: translateX(280px);
        animation: tune 2.6s .35s ease-in-out both;
    }

    .radio .lamp {
        animation: lamp-on 1s 2.75s both;
    }

    .radio .lamp-body {
        fill: var(--lamp);
    }

    .radio .lamp {
        filter: drop-shadow(0 0 12px rgba(214, 40, 40, .85));
    }

    @keyframes tune {
        0% {
            transform: translateX(500px);
        }

        38% {
            transform: translateX(96px);
        }

        66% {
            transform: translateX(372px);
        }

        84% {
            transform: translateX(262px);
        }

        100% {
            transform: translateX(280px);
        }
    }

    @keyframes lamp-on {
        0% {
            filter: none;
        }

        0%,
        28% {
            opacity: .55;
        }

        30% {
            opacity: 1;
            filter: drop-shadow(0 0 12px rgba(214, 40, 40, .85));
        }

        46% {
            opacity: .6;
            filter: none;
        }

        100% {
            opacity: 1;
            filter: drop-shadow(0 0 12px rgba(214, 40, 40, .85));
        }
    }

    @media (prefers-reduced-motion: reduce) {

        .radio .needle,
        .radio .lamp {
            animation: none;
        }
    }

    .read {
        position: relative;
        margin: -1.15rem 1.5rem 0;
        background: #fff;
        color: var(--ink);
        border-radius: 14px;
        padding: 1.15rem 1.35rem 1.25rem;
        box-shadow: 0 22px 40px -22px rgba(0, 0, 0, .65);
    }

    .read-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        font-size: .9rem;
        color: var(--muted);
        margin-bottom: .5rem;
    }

    .read-show {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        font-weight: 700;
        color: var(--ink);
    }

    .read-show i {
        width: .55rem;
        height: .55rem;
        border-radius: 50%;
        background: var(--lamp);
    }

    .read-to {
        font-family: var(--display);
        font-weight: 800;
        font-size: 1.35rem;
        margin: 0 0 .15rem;
        letter-spacing: -0.01em;
    }

    .read-msg {
        margin: 0;
        font-size: 1.05rem;
    }

    /* ============================ occasions ============================ */
    .occasions {
        list-style: none;
        margin: 0;
        padding: 0;
        border-top: 1px solid var(--line);
    }

    .occasion {
        display: grid;
        grid-template-columns: minmax(0, 4fr) minmax(0, 5fr) auto;
        gap: 1.5rem 3rem;
        align-items: center;
        padding: 2rem 0;
        border-bottom: 1px solid var(--line);
    }

    .occasion h3 {
        font-size: clamp(1.4rem, 2.6vw, 1.95rem);
        margin: 0 0 .35rem;
    }

    .occasion p {
        margin: 0;
        color: var(--muted);
    }

    .packages {
        margin: 0;
    }

    .packages>div {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 1.25rem;
        padding: .6rem 0;
        border-bottom: 1px dashed var(--line);
    }

    .packages>div:last-child {
        border-bottom: 0;
    }

    .packages dt {
        font-weight: 700;
    }

    .packages dt small {
        display: block;
        font-weight: 400;
        color: var(--muted);
        font-size: .9rem;
    }

    .packages dd {
        margin: 0;
        font-family: var(--display);
        font-weight: 700;
        white-space: nowrap;
    }

    .empty-note {
        padding: 2rem 0;
        color: var(--muted);
        border-top: 1px solid var(--line);
    }

    /* ============================ how it works ============================ */
    .steps {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 2.25rem;
        counter-reset: step;
    }

    .step {
        position: relative;
        padding-top: 4.4rem;
    }

    .step::before {
        counter-increment: step;
        content: counter(step);
        position: absolute;
        top: 0;
        left: 0;
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        background: var(--gold);
        color: var(--ink);
        font-family: var(--display);
        font-weight: 800;
        font-size: 1.45rem;
        display: grid;
        place-items: center;
    }

    .step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 1.45rem;
        left: 3.8rem;
        right: -1.1rem;
        height: 2px;
        background: var(--ink);
        opacity: .16;
    }

    .step h3 {
        font-size: 1.3rem;
        margin: 0 0 .4rem;
    }

    .step p {
        margin: 0;
        color: var(--muted);
    }

    /* ============================ shows ============================ */
    .shows {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 2.25rem 2rem;
    }

    .shows li {
        border-top: 3px solid var(--ink);
        padding-top: 1rem;
    }

    .shows h3 {
        font-size: 1.45rem;
        margin: 0 0 .25rem;
    }

    .shows p {
        margin: 0;
        color: var(--muted);
    }

    .shows .times {
        color: var(--ink);
        font-family: var(--display);
        font-weight: 700;
        font-size: 1.1rem;
    }

    .live {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        margin-top: .65rem;
        font-weight: 700;
        color: var(--lamp);
    }

    .live i {
        width: .65rem;
        height: .65rem;
        border-radius: 50%;
        background: var(--lamp);
        box-shadow: 0 0 0 4px rgba(214, 40, 40, .2);
    }

    /* ============================ gifts ============================ */
    .gift-list {
        list-style: none;
        margin: 0 0 2rem;
        padding: 0;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0 3.5rem;
    }

    .gift-list li {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 0;
        border-bottom: 1px solid #C9D3EC;
    }

    .gift-list svg {
        width: 1.75rem;
        height: 1.75rem;
        flex: none;
        fill: none;
        stroke: var(--ink);
        stroke-width: 1.7;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .gift-name {
        flex: 1;
        min-width: 0;
    }

    .gift-name strong {
        display: block;
    }

    .gift-name span {
        color: var(--muted);
        font-size: .92rem;
    }

    .gift-price {
        font-family: var(--display);
        font-weight: 700;
        white-space: nowrap;
    }

    /* ============================ tributes ============================ */
    .tribute {
        position: relative;
        overflow: hidden;
    }

    .tribute .cbs-wrap {
        position: relative;
    }

    .tribute h2 {
        color: #fff;
        max-width: 16ch;
    }

    .tribute p {
        color: #D6DEF2;
        max-width: 50ch;
        font-size: 1.15rem;
        margin: 0 0 2rem;
    }

    .tribute .arcs {
        position: absolute;
        right: -4rem;
        top: 50%;
        width: min(520px, 70vw);
        transform: translateY(-50%);
        opacity: .14;
        pointer-events: none;
    }

    /* ============================ faq ============================ */
    .faq .cbs-wrap {
        display: grid;
        grid-template-columns: minmax(0, 4fr) minmax(0, 8fr);
        gap: 2rem 4rem;
        align-items: start;
    }

    .faq details {
        border-top: 1px solid var(--line);
    }

    .faq details:last-child {
        border-bottom: 1px solid var(--line);
    }

    .faq summary {
        list-style: none;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        padding: 1.15rem 0;
        font-family: var(--display);
        font-weight: 700;
        font-size: 1.2rem;
        letter-spacing: -0.01em;
    }

    .faq summary::-webkit-details-marker {
        display: none;
    }

    .faq summary::after {
        content: '+';
        flex: none;
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        border: 2px solid var(--ink);
        display: grid;
        place-items: center;
        font-size: 1.25rem;
        line-height: 1;
        padding-bottom: 2px;
    }

    .faq details[open] summary::after {
        content: '\2013';
        background: var(--ink);
        color: #fff;
    }

    .faq details p {
        margin: 0 0 1.25rem;
        color: var(--muted);
        max-width: 60ch;
    }

    /* ============================ closing call to action ============================ */
    .closer {
        background: var(--gold);
        color: var(--ink);
        text-align: center;
    }

    .closer h2 {
        margin-bottom: 1.5rem;
    }

    /* ============================ small screens ============================ */
    @media (max-width: 940px) {
        .hero .cbs-wrap {
            grid-template-columns: 1fr;
        }

        .radio {
            max-width: 560px;
        }

        .occasion {
            grid-template-columns: 1fr;
            gap: 1.25rem;
            padding-inline: 0;
        }

        .occasion .cbs-btn {
            justify-self: start;
        }

        .steps {
            grid-template-columns: 1fr 1fr;
            row-gap: 2.5rem;
        }

        .step:not(:last-child)::after {
            display: none;
        }

        .gift-list {
            grid-template-columns: 1fr;
        }

        .faq .cbs-wrap {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 560px) {
        .steps {
            grid-template-columns: 1fr;
        }

        .hero-actions .cbs-btn {
            flex: 1 1 100%;
        }

        .read {
            margin-inline: .75rem;
        }

        .tribute .arcs {
            opacity: .08;
            right: -8rem;
        }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="lp">

    <!-- ========================= hero ========================= -->
    <section class="hero on-dark">
        <div class="cbs-wrap">
            <div>
                <h1><?= esc($cbs->heroHeadline) ?></h1>
                <p class="sub"><?= esc($cbs->heroSubheadline) ?></p>

                <div class="hero-actions">
                    <a class="cbs-btn cbs-btn--gold" href="<?= site_url('booking') ?>">Send a greeting</a>
                    <a class="cbs-btn cbs-btn--ghost" href="<?= site_url('gifts') ?>">Send a gift</a>
                    <?php if ($cbs->liveStreamUrl !== ''): ?>
                        <button class="cbs-btn cbs-btn--ghost" id="listenBtn" type="button" aria-pressed="false">
                            <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M3 1.5v13l11-6.5z" fill="currentColor" />
                            </svg>
                            <span id="listenLabel">Listen live</span>
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($cbs->liveStreamUrl !== ''): ?>
                    <audio id="liveAudio" preload="none" data-src="<?= esc($cbs->liveStreamUrl) ?>"></audio>
                    <p class="listen-msg" id="listenMsg" role="status"></p>
                <?php endif; ?>

                <p class="hero-note">
                    <?= $smsOn ? 'We text you to confirm your booking, and again when it has been read out.' : 'Every message is reviewed by our team before it goes on air.' ?>
                    <?= $contact ? ' Questions? ' . esc(ucfirst(implode(' or ', $contact))) . '.' : '' ?>
                </p>
            </div>

            <div class="radio" aria-hidden="true">
                <svg viewBox="0 0 560 330" focusable="false">
                    <rect x="4" y="4" width="552" height="322" rx="34" fill="#17306E" stroke="#2B4A9A" stroke-width="2" />

                    <g class="lamp" transform="translate(372 20)">
                        <rect class="lamp-body" width="168" height="34" rx="8" />
                        <text x="84" y="24" text-anchor="middle" font-family="Bricolage Grotesque, sans-serif" font-weight="800" font-size="17" letter-spacing="2" fill="#fff">ON AIR</text>
                    </g>

                    <rect x="36" y="70" width="488" height="150" rx="14" fill="#F5A800" />
                    <rect x="36" y="70" width="488" height="52" rx="14" fill="#FFC73D" opacity=".55" />
                    <rect x="44" y="78" width="472" height="134" rx="9" fill="none" stroke="#0E1B3D" stroke-opacity=".28" stroke-width="1.5" />

                    <?php for ($i = 0; $i <= 40; $i++): ?>
                        <?php
                        $x      = 76 + $i * 10.2;
                        $height = $i % 8 === 0 ? 38 : ($i % 4 === 0 ? 26 : 16);
                        ?>
                        <line x1="<?= $x ?>" y1="128" x2="<?= $x ?>" y2="<?= 128 + $height ?>" stroke="#0E1B3D" stroke-width="<?= $i % 8 === 0 ? 3 : 2 ?>" stroke-linecap="round" />
                    <?php endfor; ?>
                    <?php foreach ([88, 92, 96, 100, 104, 108] as $k => $number): ?>
                        <text x="<?= 76 + $k * 81.6 ?>" y="112" text-anchor="middle" font-family="Bricolage Grotesque, sans-serif" font-weight="700" font-size="17" fill="#0E1B3D"><?= $number ?></text>
                    <?php endforeach; ?>
                    <text x="76" y="198" font-family="Bricolage Grotesque, sans-serif" font-weight="800" font-size="14" fill="#0E1B3D">FM</text>

                    <g class="needle">
                        <line x1="0" y1="78" x2="0" y2="210" stroke="#D62828" stroke-width="3.5" stroke-linecap="round" />
                        <circle cx="0" cy="78" r="6" fill="#D62828" />
                    </g>

                    <?php foreach ([120, 440] as $cx): ?>
                        <circle cx="<?= $cx ?>" cy="272" r="30" fill="#0E1B3D" stroke="#2B4A9A" stroke-width="3" />
                        <line x1="<?= $cx ?>" y1="262" x2="<?= $cx ?>" y2="248" stroke="#F5A800" stroke-width="3.5" stroke-linecap="round" transform="rotate(<?= $cx === 120 ? -35 : 28 ?> <?= $cx ?> 272)" />
                    <?php endforeach; ?>
                    <?php for ($k = 0; $k < 9; $k++): ?>
                        <rect x="<?= 196 + $k * 20 ?>" y="246" width="8" height="54" rx="4" fill="#0E1B3D" />
                    <?php endfor; ?>
                </svg>

                <div class="read">
                    <div class="read-top">
                        <span>Example greeting</span>
                        <span class="read-show"><i></i><?= esc($sampleShow) ?></span>
                    </div>
                    <p class="read-to">For Mama Ruth</p>
                    <p class="read-msg">Happy birthday Mama Ruth, we love you! From Grace and all the children.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================= occasions ========================= -->
    <section id="greetings">
        <div class="cbs-wrap">
            <h2>What would you like to say?</h2>
            <p class="lede">Pick an occasion, then the package that suits it. The price is the price you pay.</p>

            <?php if (empty($occasions)): ?>
                <p class="empty-note">Greetings aren't open for booking just yet. Please check back soon<?= $contact ? ', or ' . esc(implode(' or ', $contact)) : '' ?>.</p>
            <?php else: ?>
                <ul class="occasions">
                    <?php foreach ($occasions as $occasion): ?>
                        <li class="occasion">
                            <div>
                                <h3><?= esc($occasion['name']) ?></h3>
                                <?php if ($occasion['description'] !== ''): ?>
                                    <p><?= esc($occasion['description']) ?></p>
                                <?php endif; ?>
                            </div>
                            <dl class="packages">
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
                            <a class="cbs-btn cbs-btn--line" href="<?= site_url('booking/' . $occasion['slug']) ?>"
                                aria-label="Choose a package for <?= esc($occasion['name']) ?>">Choose a package</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <!-- ========================= how it works ========================= -->
    <section id="how" class="mist">
        <div class="cbs-wrap">
            <h2>How it works</h2>
            <p class="lede">From your phone to the airwaves in four steps.</p>

            <ol class="steps">
                <li class="step">
                    <h3>Choose an occasion</h3>
                    <p>Pick the kind of greeting, then the package that suits it.</p>
                </li>
                <li class="step">
                    <h3>Write it, or say it</h3>
                    <p>Type your message. If you'd like to be heard, add a voice note of up to <?= (int) $cbs->voiceNoteMaxSeconds ?> seconds.</p>
                </li>
                <li class="step">
                    <h3>Pick a show and pay</h3>
                    <p>Choose which show reads it and on which day. <?= esc($payShort) ?></p>
                </li>
                <li class="step">
                    <h3>We read it on air</h3>
                    <p>Every message is reviewed first.<?= $smsOn ? ' We text you once it has been read out.' : '' ?></p>
                </li>
            </ol>
        </div>
    </section>

    <!-- ========================= shows ========================= -->
    <?php if (! empty($shows)): ?>
        <section id="shows">
            <div class="cbs-wrap">
                <h2>On the air</h2>
                <p class="lede">Your greeting is read out during one of these shows. You choose which when you book.</p>

                <ul class="shows">
                    <?php foreach ($shows as $show): ?>
                        <li>
                            <h3><?= esc($show['name']) ?></h3>
                            <p class="times"><?= esc($show['times']) ?></p>
                            <p><?= esc($show['days']) ?></p>
                            <?php if ($show['onAirNow']): ?>
                                <span class="live"><i aria-hidden="true"></i>On air now</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>

    <!-- ========================= gifts ========================= -->
    <?php if (! empty($gifts)): ?>
        <section id="gifts" class="mist">
            <div class="cbs-wrap">
                <h2>Send a gift too</h2>
                <p class="lede">Add one to a greeting, or send one on its own.</p>

                <ul class="gift-list">
                    <?php foreach ($gifts as $gift): ?>
                        <li>
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $giftIcons[$gift['type']] ?? $giftIcons['physical'] ?></svg>
                            <span class="gift-name">
                                <strong><?= esc($gift['name']) ?></strong>
                                <span><?= esc($giftTypes[$gift['type']] ?? ucfirst($gift['type'])) ?></span>
                            </span>
                            <span class="gift-price"><?= esc(cbs_money((float) $gift['price'], $gift['currency'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <a class="cbs-btn cbs-btn--ink" href="<?= site_url('gifts') ?>">See all gifts</a>
            </div>
        </section>
    <?php endif; ?>

    <!-- ========================= tributes ========================= -->
    <?php if ($tribute): ?>
        <section id="tributes" class="tribute on-dark">
            <svg class="arcs" viewBox="0 0 400 400" aria-hidden="true" focusable="false" fill="none" stroke="#fff" stroke-width="3">
                <circle cx="400" cy="200" r="90" />
                <circle cx="400" cy="200" r="150" />
                <circle cx="400" cy="200" r="210" />
                <circle cx="400" cy="200" r="270" />
                <circle cx="400" cy="200" r="330" />
            </svg>
            <div class="cbs-wrap">
                <h2>For the moments that matter most</h2>
                <p>When you want to remember someone, tell us who and what you'd like said. Messages of remembrance are reviewed by our team before they air.</p>
                <a class="cbs-btn cbs-btn--gold" href="<?= esc($tributeUrl) ?>">Send a tribute</a>
            </div>
        </section>
    <?php endif; ?>

    <!-- ========================= questions ========================= -->
    <section id="faq" class="faq">
        <div class="cbs-wrap">
            <div>
                <h2>Questions</h2>
                <p class="lede">The things people ask before they book.</p>
            </div>
            <div>
                <?php foreach ($faq as [$question, $answer]): ?>
                    <details>
                        <summary><?= esc($question) ?></summary>
                        <p><?= esc($answer) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ========================= closing call to action ========================= -->
    <section class="closer">
        <div class="cbs-wrap">
            <h2>Ready when you are.</h2>
            <a class="cbs-btn cbs-btn--ink" href="<?= site_url('booking') ?>">Send a greeting</a>
        </div>
    </section>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($cbs->liveStreamUrl !== ''): ?>
    <script>
        (function() {
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

            button.addEventListener('click', function() {
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
            audio.addEventListener('playing', function() {
                button.setAttribute('aria-pressed', 'true');
                label.textContent = 'Stop listening';
            });
            audio.addEventListener('error', fail);
        })();
    </script>
<?php endif; ?>
<?= $this->endSection() ?>