<?php
/**
 * The greeting form for one occasion, as five steps of one form: package, message, gifts, your details, review.
 *
 * Without JavaScript every step shows, one after the other, and the form posts from the bottom. With it
 * (assets/js/checkout.js) one step shows at a time with a running summary and total. The message editor and voice
 * recorder are assets/js/booking.js, which keeps the element ids it has always used.
 *
 * @var array<string, mixed>       $category
 * @var list<array<string, mixed>> $rateTiers
 * @var list<array<string, mixed>> $allShows
 * @var list<array<string, mixed>> $gifts
 * @var list<array<string, mixed>> $templates
 * @var \Config\CbsRadio           $config
 * @var int                        $giftMaxQuantity
 * @var int                        $giftMaxCount
 */
$this->setVar('lean', true);
$this->setVar('fullTitle', null); // the page title below names this page; never a title left from another page
$this->setVar('pageTitle', $category['name'] . ' — send a greeting');
$this->setVar('metaDescription', 'Send a ' . strtolower($category['name']) . ' greeting, read out live on CBS Radio.');

// A version suffix (the file's modified time) so browsers pick up a changed script or stylesheet straight away.
$asset = static fn (string $path): string => base_url($path) . (is_file(FCPATH . $path) ? '?v=' . filemtime(FCPATH . $path) : '');

$exts      = implode(',', $config->voiceNoteAllowedExtensions);
$look      = cbs_occasion_look($category);
$errors    = (array) (session('errors') ?? []);
$resume    = old('rate_tier_id') !== null;
$dayNames  = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
$maxClock  = (int) floor($config->voiceNoteMaxSeconds / 60) . ':' . str_pad((string) ($config->voiceNoteMaxSeconds % 60), 2, '0', STR_PAD_LEFT);
$tick      = cbs_icon_svg('check', .95);
$oldShow   = (string) old('show_id');
?>
<?= $this->extend(ui_layout('public')) ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= esc($asset('assets/css/booking.css')) ?>">
<link rel="stylesheet" href="<?= esc($asset('assets/css/site.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="ck-page">
    <section class="ck-hero">
        <div class="ck-wrap">
            <a class="ck-crumb" href="<?= site_url('booking') ?>"><?= cbs_icon_svg('back', 1) ?> All occasions</a>
            <p class="ck-eyebrow"><?= cbs_icon_svg($look['icon'], 1.1) ?> Greeting</p>
            <h1><?= esc($category['name']) ?></h1>
            <?php if (! empty($category['description'])): ?>
                <p class="ck-lede"><?= esc($category['description']) ?></p>
            <?php endif; ?>
            <?php if (! empty($rateTiers)): ?>
                <?php $this->setVar('current', 2)->setVar('wizard', true); ?>
                <?= $this->include(ui_view_path('booking/_steps')) ?>
            <?php endif; ?>
        </div>
    </section>

    <div class="ck-wrap ck-lift">
        <?php if ($errors !== []): ?>
            <div class="ck-alert ck-alert--error" role="alert">
                <?= cbs_icon_svg('alert', 1.25) ?>
                <div>
                    <strong>Please check a few things:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= esc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($rateTiers)): ?>
            <div class="ck-card ck-card--pad">
                <div class="ck-empty">
                    <span class="ck-art ck-art--<?= esc($look['tone'], 'attr') ?>"><?= cbs_icon_svg($look['icon']) ?></span>
                    <strong>Not open just yet</strong>
                    <p>No packages are currently available for this category.</p>
                    <a class="ck-btn ck-btn--ink" href="<?= site_url('booking') ?>">Choose another occasion</a>
                </div>
            </div>
        <?php else: ?>

        <form action="<?= site_url('booking') ?>" method="post" enctype="multipart/form-data" id="bookingForm" class="ck-layout"
            data-max-chars="<?= (int) $config->messageMaxLength ?>"
            data-wpm="<?= (int) $config->readingWordsPerMinute ?>"
            data-draft-key="<?= esc($category['slug'], 'attr') ?>"
            data-min-seconds="<?= (int) $config->voiceNoteMinSeconds ?>"
            data-max-seconds="<?= (int) $config->voiceNoteMaxSeconds ?>"
            data-max-kb="<?= (int) $config->voiceNoteMaxSizeKB ?>"
            data-exts="<?= esc($exts, 'attr') ?>"
            data-templates="<?= esc(json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') ?>"
            data-gift-max-quantity="<?= (int) $giftMaxQuantity ?>"
            data-gift-max-count="<?= (int) $giftMaxCount ?>"
            data-resume="<?= $resume ? '1' : '0' ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="category_id" value="<?= esc($category['id'], 'attr') ?>">

            <!-- Spoken updates for screen-reader users; not shown. -->
            <p id="announce" class="bk-sr" aria-live="polite"></p>

            <div class="ck-main">

                <!-- ============================================================ 2. package -->
                <section class="ck-panel ck-step" data-step="2" aria-labelledby="step2Title">
                    <div class="ck-panel-head">
                        <span class="ck-num" aria-hidden="true">2</span>
                        <div>
                            <h2 id="step2Title" tabindex="-1">Choose a package</h2>
                            <p>How would you like your greeting to sound on air?</p>
                        </div>
                    </div>

                    <div class="ck-choices" role="radiogroup" aria-labelledby="step2Title" id="packageGroup">
                        <?php foreach ($rateTiers as $tier): ?>
                            <label class="ck-choice">
                                <input type="radio" name="rate_tier_id" value="<?= esc($tier['id'], 'attr') ?>" required
                                    data-name="<?= esc($tier['package_level'], 'attr') ?>"
                                    data-price="<?= esc((float) $tier['price'], 'attr') ?>"
                                    data-currency="<?= esc($tier['currency'], 'attr') ?>"
                                    <?= (string) old('rate_tier_id') === (string) $tier['id'] ? 'checked' : '' ?>>
                                <span class="ck-tick" aria-hidden="true"><?= $tick ?></span>
                                <span class="ck-choice-name"><?= esc($tier['package_level']) ?></span>
                                <span class="ck-choice-price"><small><?= esc($tier['currency']) ?></small><?= esc(number_format((float) $tier['price'], 0)) ?></span>
                                <span class="bk-sr"><?= cbs_money((float) $tier['price'], $tier['currency']) ?></span>
                                <?php if (! empty($tier['features'])): ?>
                                    <span class="ck-choice-note"><?= esc($tier['features']) ?></span>
                                <?php endif; ?>
                                <span class="ck-choice-tags">
                                    <?php if ((int) ($tier['includes_on_air'] ?? 0) === 1): ?>
                                        <span class="ck-tag ck-tag--gold"><?= cbs_icon_svg('radio', .95) ?> Read on air</span>
                                    <?php endif; ?>
                                    <?php if (stripos($tier['package_level'] . ' ' . ($tier['features'] ?? ''), 'voice') !== false): ?>
                                        <span class="ck-tag"><?= cbs_icon_svg('mic', .95) ?> Voice note</span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p id="packageError" class="bk-error" role="alert" hidden></p>

                    <div class="ck-panel-foot">
                        <a class="ck-link" href="<?= site_url('booking') ?>"><?= cbs_icon_svg('back', 1) ?> Change occasion</a>
                        <button type="button" class="ck-btn ck-btn--gold ck-nav-js" data-next>Continue <?= cbs_icon_svg('arrow', 1.05) ?></button>
                    </div>
                </section>

                <!-- ============================================================ 3. message -->
                <section class="ck-panel ck-step" data-step="3" aria-labelledby="step3Title">
                    <div class="ck-panel-head">
                        <span class="ck-num" aria-hidden="true">3</span>
                        <div>
                            <h2 id="step3Title" tabindex="-1">Your message</h2>
                            <p>Who is it for, and what would you like us to say?</p>
                        </div>
                    </div>

                    <div class="ck-field">
                        <label class="ck-label" for="recipientName">Who is it for?</label>
                        <input type="text" name="recipient_name" id="recipientName" class="ck-input" required autocomplete="off"
                            maxlength="<?= (int) $config->recipientNameMaxLength ?>" placeholder="e.g. Mama Ruth" value="<?= esc((string) old('recipient_name'), 'attr') ?>">
                    </div>

                    <div class="bk-editor" id="messageEditor">
                        <div class="bk-notice" id="draftNotice" role="status" hidden>
                            <span>We saved a draft you started <strong id="draftAge"></strong>. Drafts stay on this device only.</span>
                            <button type="button" class="bk-link" id="draftRestore">Restore it</button>
                            <button type="button" class="bk-link" id="draftDiscard">Discard</button>
                        </div>

                        <div class="bk-templates" id="templates" hidden>
                            <p class="bk-label" id="templatesLabel">Not sure what to say? Start from an example.</p>
                            <div class="bk-pills" id="templateLangs" role="group" aria-label="Language of the examples" hidden></div>
                            <div class="bk-chips" id="templateChips" role="group" aria-labelledby="templatesLabel"></div>
                        </div>

                        <div class="bk-notice bk-notice--replace" id="replaceNotice" role="status" hidden>
                            <span id="replaceText"></span>
                            <button type="button" class="bk-link" id="replaceYes">Replace it</button>
                            <button type="button" class="bk-link" id="replaceNo">Keep mine</button>
                        </div>

                        <label class="form-label" for="messageText">What would you like us to say?</label>
                        <textarea name="message_text" id="messageText" class="form-control" rows="5" required
                            aria-describedby="messageStatus messageError"><?= esc((string) old('message_text')) ?></textarea>

                        <div class="bk-meter" aria-hidden="true"><span id="meterFill"></span></div>

                        <div class="bk-editor-foot">
                            <p class="bk-status" id="messageStatus">
                                <span id="charLine"><?= (int) $config->messageMaxLength ?> characters at most</span>
                                <span id="timeLine"></span>
                            </p>
                            <div class="bk-tools">
                                <button type="button" class="bk-link" id="undoBtn" hidden>Undo</button>
                                <button type="button" class="bk-link" id="clearBtn" hidden>Clear</button>
                            </div>
                        </div>

                        <p class="bk-warn" id="placeholderWarn" role="status" hidden></p>
                        <p class="bk-error" id="messageError" role="alert" hidden></p>
                    </div>

                    <fieldset class="bk-voice" id="voice" aria-describedby="voiceHelp" style="margin-top:1.5rem">
                        <legend>Add your voice <span class="bk-optional">(optional)</span></legend>
                        <p class="bk-help" id="voiceHelp">
                            Between <?= (int) $config->voiceNoteMinSeconds ?> and <?= (int) $config->voiceNoteMaxSeconds ?> seconds.
                            It's recorded on your device and only sent when you send the booking.
                        </p>

                        <div id="voiceIdle">
                            <div class="bk-voice-actions">
                                <button type="button" class="bk-btn bk-btn--rec" id="recBtn"><?= cbs_icon_svg('mic', 1.1) ?> Record a voice note</button>
                                <button type="button" class="bk-btn bk-btn--ghost" id="pickBtn">Choose an audio file</button>
                            </div>
                            <p class="bk-help" id="voiceUnavailable" style="margin:.6rem 0 0" hidden></p>
                        </div>

                        <div class="bk-rec" id="voiceRec" hidden>
                            <div class="bk-rec-top">
                                <span class="bk-rec-dot" aria-hidden="true"></span>
                                <span class="bk-time" id="recTime" aria-hidden="true">0:00</span>
                                <span class="bk-time-max" aria-hidden="true">of <?= esc($maxClock) ?></span>
                                <span class="bk-sr">Recording</span>
                            </div>
                            <div class="bk-bar" aria-hidden="true"><span id="recBar"></span></div>
                            <div class="bk-level-row" aria-hidden="true">
                                <?= cbs_icon_svg('mic', 1) ?>
                                <div class="bk-level"><span id="recLevel"></span></div>
                            </div>
                            <div><button type="button" class="bk-btn bk-btn--stop" id="stopBtn">Stop recording</button></div>
                        </div>

                        <div class="bk-ready" id="voiceReady" hidden>
                            <audio id="voicePlayer" controls preload="metadata" aria-label="Your voice note"></audio>
                            <p class="bk-meta" id="voiceMeta"></p>
                            <div class="bk-voice-actions">
                                <button type="button" class="bk-btn bk-btn--ghost" id="againBtn">Record again</button>
                                <button type="button" class="bk-btn bk-btn--ghost" id="removeBtn">Remove</button>
                            </div>
                        </div>

                        <p class="bk-status" id="voiceMsg" role="status" style="margin-top:.5rem"></p>
                        <p class="bk-error" id="voiceErr" role="alert" hidden></p>

                        <!-- Without JavaScript this is a plain file picker; with it, the buttons above drive it. -->
                        <div class="bk-voice-note-nojs">
                            <label class="form-label" for="voiceNoteInput">Choose an audio file</label>
                            <input type="file" name="voice_note" id="voiceNoteInput" class="form-control" accept="audio/*,.m4a,.opus,.aac,.webm,.ogg,.mp3,.wav">
                        </div>
                        <input type="hidden" name="voice_note_duration_seconds" id="voiceDuration">
                    </fieldset>

                    <div class="ck-panel-foot">
                        <button type="button" class="ck-link ck-nav-js" data-back><?= cbs_icon_svg('back', 1) ?> Back</button>
                        <button type="button" class="ck-btn ck-btn--gold ck-nav-js" data-next>Continue <?= cbs_icon_svg('arrow', 1.05) ?></button>
                    </div>
                </section>

                <!-- ============================================================ 4. gifts -->
                <section class="ck-panel ck-step" data-step="4" aria-labelledby="step4Title">
                    <div class="ck-panel-head">
                        <span class="ck-num" aria-hidden="true">4</span>
                        <div>
                            <h2 id="step4Title" tabindex="-1">Add a gift <span class="ck-optional">(optional)</span></h2>
                            <p>Make it extra special. Gifts go to <span data-recipient-echo>the person you're greeting</span> and are paid together with your greeting.</p>
                        </div>
                    </div>

                    <?php if (empty($gifts)): ?>
                        <div class="ck-empty">
                            <span class="ck-art ck-art--navy"><?= cbs_icon_svg('gift') ?></span>
                            <strong>No gifts right now</strong>
                            <p>Your greeting is lovely on its own. Continue to add your details.</p>
                        </div>
                    <?php else: ?>
                        <div class="ck-gifts" id="giftGrid">
                            <?php foreach ($gifts as $gift): ?>
                                <?php
                                $giftLook = cbs_gift_look($gift['type']);
                                $qty      = (int) old('gifts.' . $gift['id']);
                                $qtyId    = 'gift-qty-' . (int) $gift['id'];
                                ?>
                                <div class="ck-gift<?= $qty > 0 ? ' is-selected' : '' ?>" data-gift
                                    data-id="<?= esc($gift['id'], 'attr') ?>"
                                    data-name="<?= esc($gift['name'], 'attr') ?>"
                                    data-price="<?= esc((float) $gift['price'], 'attr') ?>"
                                    data-currency="<?= esc($gift['currency'], 'attr') ?>"
                                    data-type="<?= esc($gift['type'], 'attr') ?>">
                                    <div class="ck-gift-top">
                                        <span class="ck-art ck-art--<?= esc($giftLook['tone'], 'attr') ?>"><?= cbs_icon_svg($giftLook['icon']) ?></span>
                                        <div>
                                            <h3 class="ck-gift-name"><?= esc($gift['name']) ?></h3>
                                            <p class="ck-gift-kind"><?= esc($giftLook['label']) ?><?= ! empty($gift['vendor_name']) ? ' · from ' . esc($gift['vendor_name']) : '' ?></p>
                                        </div>
                                    </div>
                                    <?php if (! empty($gift['description'])): ?>
                                        <p class="ck-gift-desc"><?= esc(cbs_excerpt($gift['description'], 110)) ?></p>
                                    <?php endif; ?>
                                    <div class="ck-gift-foot">
                                        <span class="ck-gift-price"><?= cbs_money((float) $gift['price'], $gift['currency']) ?></span>
                                        <button type="button" class="ck-btn ck-btn--line ck-add" data-gift-add aria-label="Add <?= esc($gift['name'], 'attr') ?>">Add</button>
                                        <span class="ck-qty">
                                            <button type="button" data-gift-minus aria-label="One fewer <?= esc($gift['name'], 'attr') ?>">&minus;</button>
                                            <label class="bk-sr" for="<?= $qtyId ?>">How many <?= esc($gift['name']) ?></label>
                                            <input type="number" inputmode="numeric" id="<?= $qtyId ?>" name="gifts[<?= (int) $gift['id'] ?>]"
                                                min="0" max="<?= (int) $giftMaxQuantity ?>" step="1" value="<?= $qty ?>" data-gift-qty>
                                            <button type="button" data-gift-plus aria-label="One more <?= esc($gift['name'], 'attr') ?>">+</button>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="ck-hint" style="margin-top:.9rem">Up to <?= (int) $giftMaxCount ?> different gifts, <?= (int) $giftMaxQuantity ?> of each.</p>
                        <p id="giftError" class="bk-error" role="alert" hidden></p>

                        <div class="ck-delivery" id="giftDelivery">
                            <h3><?= cbs_icon_svg('truck', 1.1) ?> Delivery</h3>
                            <div class="ck-field" id="giftPhoneField">
                                <label class="ck-label" for="giftRecipientPhone">Recipient's phone <span class="ck-optional">(for airtime, shout-outs and delivery)</span></label>
                                <input type="tel" name="gift_recipient_phone" id="giftRecipientPhone" class="ck-input" inputmode="tel" maxlength="20"
                                    autocomplete="off" placeholder="e.g. 0772 000 000" value="<?= esc((string) old('gift_recipient_phone'), 'attr') ?>">
                            </div>
                            <div class="ck-field" id="giftAddressField">
                                <label class="ck-label" for="giftDeliveryDetails">Delivery address <span class="ck-optional" id="giftAddressHint">(needed for a delivered gift)</span></label>
                                <textarea name="gift_delivery_details" id="giftDeliveryDetails" class="ck-textarea" rows="2" maxlength="1000"
                                    placeholder="Street or area, nearest landmark, and the best time to deliver."><?= esc((string) old('gift_delivery_details')) ?></textarea>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="ck-panel-foot">
                        <button type="button" class="ck-link ck-nav-js" data-back><?= cbs_icon_svg('back', 1) ?> Back</button>
                        <button type="button" class="ck-btn ck-btn--gold ck-nav-js" data-next data-skip-label="Skip gifts" data-add-label="Continue">Continue <?= cbs_icon_svg('arrow', 1.05) ?></button>
                    </div>
                </section>

                <!-- ============================================================ 5. details -->
                <section class="ck-panel ck-step" data-step="5" aria-labelledby="step5Title">
                    <div class="ck-panel-head">
                        <span class="ck-num" aria-hidden="true">5</span>
                        <div>
                            <h2 id="step5Title" tabindex="-1">Your details</h2>
                            <p>We'll send your confirmation and on-air updates here.</p>
                        </div>
                    </div>

                    <div class="ck-grid-2">
                        <div class="ck-field">
                            <label class="ck-label" for="senderName">Your name</label>
                            <input type="text" name="sender_name" id="senderName" class="ck-input" required autocomplete="name" maxlength="150"
                                value="<?= esc((string) old('sender_name'), 'attr') ?>">
                        </div>
                        <div class="ck-field">
                            <label class="ck-label" for="senderPhone">Your phone</label>
                            <input type="tel" name="sender_phone" id="senderPhone" class="ck-input" required autocomplete="tel" inputmode="tel" maxlength="20"
                                placeholder="e.g. 0772 000 000" value="<?= esc((string) old('sender_phone'), 'attr') ?>">
                        </div>
                    </div>
                    <div class="ck-field">
                        <label class="ck-label" for="senderEmail">Your email <span class="ck-optional">(optional)</span></label>
                        <input type="email" name="sender_email" id="senderEmail" class="ck-input" autocomplete="email" maxlength="150"
                            value="<?= esc((string) old('sender_email'), 'attr') ?>">
                    </div>

                    <fieldset style="border:0;margin:1.6rem 0 0;padding:0;min-inline-size:0">
                        <legend class="ck-section-title" style="font-size:1.2rem;margin-bottom:.35rem">When should it air? <span class="ck-optional">(optional)</span></legend>
                        <p class="ck-hint" style="margin-bottom:1rem">Pick a day, and a show if you have a favourite. Otherwise we'll read it at the next good moment.</p>

                        <div class="ck-field" style="max-width:20rem">
                            <label class="ck-label" for="scheduledDate">Day</label>
                            <input type="date" name="scheduled_date" id="scheduledDate" class="ck-input" min="<?= date('Y-m-d') ?>" value="<?= esc((string) old('scheduled_date'), 'attr') ?>">
                        </div>

                        <div class="ck-choices" role="radiogroup" aria-label="Show" id="showGroup">
                            <label class="ck-choice">
                                <input type="radio" name="show_id" value="" data-name="Any show" <?= $oldShow === '' ? 'checked' : '' ?>>
                                <span class="ck-tick" aria-hidden="true"><?= $tick ?></span>
                                <span class="ck-choice-name">Any show</span>
                                <span class="ck-choice-note">We'll find the right moment.</span>
                            </label>
                            <?php foreach ($allShows as $show): ?>
                                <?php $days = empty($show['days_of_week']) ? [] : array_map('intval', explode(',', $show['days_of_week'])); ?>
                                <label class="ck-choice" data-show-days="<?= esc(implode(',', $days), 'attr') ?>">
                                    <input type="radio" name="show_id" value="<?= esc($show['id'], 'attr') ?>"
                                        data-name="<?= esc($show['name'], 'attr') ?>"
                                        data-time="<?= esc(substr($show['start_time'], 0, 5) . '–' . substr($show['end_time'], 0, 5), 'attr') ?>"
                                        <?= $oldShow === (string) $show['id'] ? 'checked' : '' ?>>
                                    <span class="ck-tick" aria-hidden="true"><?= $tick ?></span>
                                    <span class="ck-choice-name"><?= esc($show['name']) ?></span>
                                    <span class="ck-choice-note"><?= cbs_icon_svg('clock', .95) ?> <?= esc(substr($show['start_time'], 0, 5)) ?>&ndash;<?= esc(substr($show['end_time'], 0, 5)) ?></span>
                                    <span class="ck-choice-tags">
                                        <span class="ck-tag"><?= $days === [] ? 'Every day' : esc(implode(' · ', array_map(static fn (int $d): string => $dayNames[$d] ?? '', $days))) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p id="scheduleError" class="bk-error" role="alert" hidden></p>
                    </fieldset>

                    <div class="ck-panel-foot">
                        <button type="button" class="ck-link ck-nav-js" data-back><?= cbs_icon_svg('back', 1) ?> Back</button>
                        <button type="button" class="ck-btn ck-btn--gold ck-nav-js" data-next>Review <?= cbs_icon_svg('arrow', 1.05) ?></button>
                    </div>
                </section>

                <!-- ============================================================ 6. review -->
                <section class="ck-panel ck-step" data-step="6" aria-labelledby="step6Title">
                    <div class="ck-panel-head">
                        <span class="ck-num" aria-hidden="true">6</span>
                        <div>
                            <h2 id="step6Title" tabindex="-1">Review and pay</h2>
                            <p>Check everything reads right. You'll pay on the next page.</p>
                        </div>
                    </div>

                    <div class="ck-review" id="reviewBody" hidden></div>

                    <div class="ck-alert ck-alert--info" style="margin-top:1.25rem">
                        <?= cbs_icon_svg('shield', 1.25) ?>
                        <div>Every greeting is read by our team before it goes on air. If we can't air yours, we'll let you know.</div>
                    </div>

                    <div class="ck-panel-foot">
                        <button type="button" class="ck-link ck-nav-js" data-back><?= cbs_icon_svg('back', 1) ?> Back</button>
                        <button type="submit" class="ck-btn ck-btn--gold ck-btn--lg" id="submitBtn">Continue to payment</button>
                    </div>
                </section>
            </div>

            <!-- ============================================================ summary -->
            <aside class="ck-aside" aria-label="Your greeting so far">
                <div class="ck-summary" id="summary">
                    <div class="ck-summary-head">
                        <span class="ck-lampdot" aria-hidden="true"></span>
                        <h2>Your greeting</h2>
                    </div>
                    <div class="ck-summary-body">
                        <div class="ck-sum-row"><span>Occasion</span><span><?= esc($category['name']) ?></span></div>
                        <div class="ck-sum-row"><span>Package</span><span id="sumPackage">Not chosen yet</span></div>
                        <div class="ck-sum-row"><span>For</span><span id="sumRecipient">&mdash;</span></div>
                        <div class="ck-sum-quote" id="sumMessage"></div>
                        <div class="ck-sum-row" id="sumVoiceRow" hidden><span>Voice note</span><span>Attached</span></div>
                        <div class="ck-sum-row" id="sumAirsRow" hidden><span>Airs</span><span id="sumAirs"></span></div>
                        <div id="sumGiftsWrap" hidden>
                            <div class="ck-sum-row"><span>Gifts</span><span id="sumGiftCount"></span></div>
                            <ul class="ck-sum-gifts" id="sumGifts"></ul>
                        </div>
                        <div class="ck-sum-total" aria-live="polite">
                            <span>Total</span>
                            <strong id="sumTotal">&mdash;</strong>
                        </div>
                        <ul class="ck-trust">
                            <li><?= cbs_icon_svg('check', 1) ?> One payment for your greeting and gifts</li>
                            <li><?= cbs_icon_svg('check', 1) ?> Checked by our team before it airs</li>
                            <li><?= cbs_icon_svg('check', 1) ?> Updates by SMS, with a link to follow it</li>
                        </ul>
                    </div>
                </div>
            </aside>

            <!-- The phone's bottom bar: total and the current step's main button. -->
            <div class="ck-bar" aria-hidden="true">
                <div class="ck-bar-total"><span>Total</span><strong id="barTotal">&mdash;</strong></div>
                <button type="button" class="ck-btn ck-btn--gold" id="barNext" tabindex="-1">Continue</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (! empty($rateTiers)): ?>
    <script src="<?= esc($asset('assets/js/booking.js')) ?>" defer></script>
    <script src="<?= esc($asset('assets/js/checkout.js')) ?>" defer></script>
<?php endif; ?>
<?= $this->endSection() ?>
