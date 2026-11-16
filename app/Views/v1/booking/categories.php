<?php
/**
 * Step 1 of a greeting: choose the occasion.
 *
 * @var list<array<string, mixed>> $categories
 */
$this->setVar('lean', true);
$this->setVar('fullTitle', null); // the page title below names this page; never a title left from another page
$this->setVar('pageTitle', 'Send a greeting');
$this->setVar('metaDescription', 'Choose what you are celebrating and have your greeting read out live on CBS Radio.');

$asset = static fn (string $path): string => base_url($path) . (is_file(FCPATH . $path) ? '?v=' . filemtime(FCPATH . $path) : '');
?>
<?= $this->extend(ui_layout('public')) ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= esc($asset('assets/css/site.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="ck-page">
    <section class="ck-hero">
        <div class="ck-wrap">
            <p class="ck-eyebrow"><?= cbs_icon_svg('radio', 1.1) ?> Live on CBS Radio</p>
            <h1>Send a greeting</h1>
            <p class="ck-lede">Pick what you're celebrating. Choose a package, write your message, add a gift if you like, and pay once — we read it out on air.</p>
            <?php $this->setVar('current', 1); ?><?= $this->include(ui_view_path('booking/_steps')) ?>
        </div>
    </section>

    <div class="ck-wrap ck-lift">
        <?php if (empty($categories)): ?>
            <div class="ck-card ck-card--pad">
                <div class="ck-empty">
                    <span class="ck-art ck-art--slate"><?= cbs_icon_svg('radio') ?></span>
                    <strong>Greetings are taking a short break</strong>
                    <p>No categories are open for booking right now — please check back shortly.</p>
                </div>
            </div>
        <?php else: ?>
            <h2 class="ck-sr">Occasions</h2>
            <div class="ck-occasions">
                <?php foreach ($categories as $category): ?>
                    <?php $look = cbs_occasion_look($category); ?>
                    <a class="ck-occasion" href="<?= site_url('booking/' . $category['slug']) ?>">
                        <span class="ck-art ck-art--<?= esc($look['tone'], 'attr') ?>"><?= cbs_icon_svg($look['icon']) ?></span>
                        <h2><?= esc($category['name']) ?></h2>
                        <?php if (! empty($category['description'])): ?>
                            <p><?= esc(cbs_excerpt($category['description'], 110)) ?></p>
                        <?php endif; ?>
                        <span class="ck-occasion-go">Choose <?= cbs_icon_svg('arrow', 1.05) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <ol class="ck-how" aria-label="How it works">
                <li><div><strong>Choose a package</strong><span>Text readout, voice note or a prime-time slot.</span></div></li>
                <li><div><strong>Write your message</strong><span>Start from an example, or record your own voice.</span></div></li>
                <li><div><strong>Add a gift</strong><span>Airtime, an e-card or a song — optional.</span></div></li>
                <li><div><strong>Pay once, hear it live</strong><span>We check every greeting, then read it on air.</span></div></li>
            </ol>
        <?php endif; ?>

        <p class="ck-hint" style="margin-top:2rem">Only want to send a gift? <a href="<?= site_url('gifts') ?>">Send a gift without a greeting</a>.</p>
    </div>
</div>
<?= $this->endSection() ?>
