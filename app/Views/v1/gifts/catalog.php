<?php
/**
 * Sending a gift on its own, or adding one to a greeting already made (?booking_id=). A gift chosen inside the
 * greeting form doesn't come here: it is part of that greeting's checkout.
 *
 * @var list<array<string, mixed>> $gifts
 * @var int|string|null            $bookingId
 */
$this->setVar('lean', true);
$this->setVar('fullTitle', null); // the page title below names this page; never a title left from another page
$this->setVar('pageTitle', 'Send a gift');
$this->setVar('metaDescription', 'Send an e-card, airtime, a shout-out, a song dedication or a delivered gift through CBS Radio.');

$asset   = static fn (string $path): string => base_url($path) . (is_file(FCPATH . $path) ? '?v=' . filemtime(FCPATH . $path) : '');
$errors  = (array) (session('errors') ?? []);
$oldGift = (string) old('gift_id');
$tick    = cbs_icon_svg('check', .95);
?>
<?= $this->extend(ui_layout('public')) ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= esc($asset('assets/css/site.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="ck-page">
    <section class="ck-hero">
        <div class="ck-wrap">
            <p class="ck-eyebrow"><?= cbs_icon_svg('gift', 1.1) ?> Gifts</p>
            <h1>Send a gift</h1>
            <p class="ck-lede">A digital e-card, airtime top-up, shout-out, song dedication, or a delivered gift from our partners.</p>
        </div>
    </section>

    <div class="ck-wrap ck-lift">
        <?php if ($errors !== []): ?>
            <div class="ck-alert ck-alert--error" role="alert">
                <?= cbs_icon_svg('alert', 1.25) ?>
                <div>
                    <strong>Please check a few things:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($gifts)): ?>
            <div class="ck-card ck-card--pad">
                <div class="ck-empty">
                    <span class="ck-art ck-art--navy"><?= cbs_icon_svg('gift') ?></span>
                    <strong>Nothing in the gift shop today</strong>
                    <p>No gifts are available right now.</p>
                    <a class="ck-btn ck-btn--ink" href="<?= site_url('booking') ?>">Send a greeting instead</a>
                </div>
            </div>
        <?php else: ?>
            <form action="<?= site_url('gifts') ?>" method="post" id="giftForm" class="ck-layout">
                <?= csrf_field() ?>
                <div class="ck-main">
                    <?php if (! empty($bookingId)): ?>
                        <input type="hidden" name="booking_id" value="<?= esc($bookingId, 'attr') ?>">
                        <div class="ck-alert ck-alert--info" style="margin-bottom:1.25rem">
                            <?= cbs_icon_svg('radio', 1.25) ?>
                            <div>This gift will be sent along with your greeting (ref <?= esc(cbs_booking_reference((int) $bookingId)) ?>).</div>
                        </div>
                    <?php endif; ?>

                    <section class="ck-panel" aria-labelledby="giftChooseTitle">
                        <div class="ck-panel-head">
                            <span class="ck-num" aria-hidden="true">1</span>
                            <div>
                                <h2 id="giftChooseTitle">Choose a gift</h2>
                                <p>Paid on its own, with its own reference.</p>
                            </div>
                        </div>
                        <div class="ck-choices" role="radiogroup" aria-labelledby="giftChooseTitle">
                            <?php foreach ($gifts as $gift): ?>
                                <?php $look = cbs_gift_look($gift['type']); ?>
                                <label class="ck-choice">
                                    <input type="radio" name="gift_id" value="<?= esc($gift['id'], 'attr') ?>" required
                                        data-name="<?= esc($gift['name'], 'attr') ?>" data-price="<?= esc((float) $gift['price'], 'attr') ?>"
                                        data-currency="<?= esc($gift['currency'], 'attr') ?>" data-type="<?= esc($gift['type'], 'attr') ?>"
                                        <?= $oldGift === (string) $gift['id'] ? 'checked' : '' ?>>
                                    <span class="ck-tick" aria-hidden="true"><?= $tick ?></span>
                                    <span class="ck-art ck-art--<?= esc($look['tone'], 'attr') ?>"><?= cbs_icon_svg($look['icon']) ?></span>
                                    <span class="ck-choice-name"><?= esc($gift['name']) ?></span>
                                    <span class="ck-gift-kind" style="margin:0"><?= esc($look['label']) ?><?= ! empty($gift['vendor_name']) ? ' · via ' . esc($gift['vendor_name']) : '' ?></span>
                                    <?php if (! empty($gift['description'])): ?>
                                        <span class="ck-choice-note"><?= esc($gift['description']) ?></span>
                                    <?php endif; ?>
                                    <span class="ck-gift-price" style="margin-top:auto"><?= cbs_money((float) $gift['price'], $gift['currency']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="ck-panel" aria-labelledby="giftWhoTitle">
                        <div class="ck-panel-head">
                            <span class="ck-num" aria-hidden="true">2</span>
                            <div>
                                <h2 id="giftWhoTitle">Who's it from, and for?</h2>
                                <p>We'll confirm by SMS to your phone.</p>
                            </div>
                        </div>
                        <div class="ck-grid-2">
                            <div class="ck-field">
                                <label class="ck-label" for="giftSender">Your name</label>
                                <input type="text" name="sender_name" id="giftSender" class="ck-input" required maxlength="150" autocomplete="name" value="<?= esc((string) old('sender_name'), 'attr') ?>">
                            </div>
                            <div class="ck-field">
                                <label class="ck-label" for="giftSenderPhone">Your phone</label>
                                <input type="tel" name="sender_phone" id="giftSenderPhone" class="ck-input" required maxlength="20" autocomplete="tel" inputmode="tel" value="<?= esc((string) old('sender_phone'), 'attr') ?>">
                            </div>
                            <div class="ck-field">
                                <label class="ck-label" for="giftRecipient">Recipient's name</label>
                                <input type="text" name="recipient_name" id="giftRecipient" class="ck-input" required maxlength="150" value="<?= esc((string) old('recipient_name'), 'attr') ?>">
                            </div>
                            <div class="ck-field">
                                <label class="ck-label" for="giftRecipientPhone">Recipient's phone <span class="ck-optional">(for airtime and shout-outs)</span></label>
                                <input type="tel" name="recipient_phone" id="giftRecipientPhone" class="ck-input" maxlength="20" inputmode="tel" value="<?= esc((string) old('recipient_phone'), 'attr') ?>">
                            </div>
                        </div>
                        <div class="ck-grid-2">
                            <div class="ck-field">
                                <label class="ck-label" for="giftQuantity">Quantity</label>
                                <input type="number" name="quantity" id="giftQuantity" class="ck-input" min="1" max="99" value="<?= esc((string) (old('quantity') ?? 1), 'attr') ?>" style="max-width:9rem">
                            </div>
                        </div>
                        <div class="ck-field">
                            <label class="ck-label" for="deliveryDetailsInput" id="deliveryDetailsLabel">Delivery details <span class="ck-optional">(optional)</span></label>
                            <textarea name="delivery_details" id="deliveryDetailsInput" class="ck-textarea" rows="2"
                                placeholder="For a physical gift: the delivery address. For airtime or e-vouchers: usually not needed."><?= esc((string) old('delivery_details')) ?></textarea>
                        </div>
                    </section>
                </div>

                <aside class="ck-aside">
                    <div class="ck-summary ck-summary--review">
                        <div class="ck-summary-head"><span class="ck-lampdot" aria-hidden="true"></span><h2>Your gift</h2></div>
                        <div class="ck-summary-body">
                            <div class="ck-sum-row"><span>Gift</span><span id="giftSumName">Not chosen yet</span></div>
                            <div class="ck-sum-row"><span>Quantity</span><span id="giftSumQty">1</span></div>
                            <div class="ck-sum-total" aria-live="polite"><span>Total</span><strong id="giftSumTotal">&mdash;</strong></div>
                            <button type="submit" class="ck-btn ck-btn--gold ck-btn--block ck-btn--lg" style="margin-top:1.25rem">Continue to payment</button>
                            <ul class="ck-trust">
                                <li><?= cbs_icon_svg('check', 1) ?> Pay by Mobile Money</li>
                                <li><?= cbs_icon_svg('check', 1) ?> Follow it with your reference</li>
                            </ul>
                        </div>
                    </div>
                </aside>
            </form>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// The running total, and the delivery field's label: a physical gift needs an address, a digital one usually doesn't.
(function () {
    var form = document.getElementById('giftForm');
    if (!form) { return; }
    var qty = document.getElementById('giftQuantity');
    var label = document.getElementById('deliveryDetailsLabel');
    var box = document.getElementById('deliveryDetailsInput');

    function update() {
        var gift = form.querySelector('input[name="gift_id"]:checked');
        var n = Math.max(1, parseInt(qty.value, 10) || 1);
        document.getElementById('giftSumQty').textContent = String(n);
        if (!gift) { return; }
        document.getElementById('giftSumName').textContent = gift.dataset.name;
        document.getElementById('giftSumTotal').textContent = gift.dataset.currency + ' ' + Math.round(parseFloat(gift.dataset.price) * n).toLocaleString('en-US');
        var physical = gift.dataset.type === 'physical';
        label.innerHTML = physical ? 'Delivery address' : 'Delivery details <span class="ck-optional">(optional)</span>';
        box.required = physical;
        box.placeholder = physical ? 'Street or area, nearest landmark, and the best time to deliver.' : 'Only needed if this gift requires extra delivery info.';
    }

    form.addEventListener('change', update);
    qty.addEventListener('input', update);
    update();
})();
</script>
<?= $this->endSection() ?>
