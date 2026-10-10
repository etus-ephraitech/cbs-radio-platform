<?= $this->extend('layouts/public') ?>

<?php
// A version suffix (the file's modified time) so browsers pick up a changed script or stylesheet
// straight away instead of using a stale cached copy.
$asset = static fn(string $path): string => base_url($path) . (is_file(FCPATH . $path) ? '?v=' . filemtime(FCPATH . $path) : '');

$exts = implode(',', $config->voiceNoteAllowedExtensions);
?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= esc($asset('assets/css/booking.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-header">
    <p class="mb-1"><a href="<?= site_url('booking') ?>" class="text-muted small"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:.35rem">
                <path d="M10 3 5 8l5 5" />
            </svg>Categories</a></p>
    <h1 class="h3 mb-1"><?= esc($category['name']) ?></h1>
    <p class="text-muted mb-0"><?= esc($category['description'] ?? '') ?></p>
</div>

<?php if (session('errors')): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach (session('errors') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (empty($rateTiers)): ?>
    <div class="alert alert-secondary">No packages are currently available for this category.</div>
<?php else: ?>

    <form action="<?= site_url('booking') ?>" method="post" enctype="multipart/form-data" id="bookingForm"
        data-max-chars="<?= (int) $config->messageMaxLength ?>"
        data-wpm="<?= (int) $config->readingWordsPerMinute ?>"
        data-draft-key="<?= esc($category['slug']) ?>"
        data-min-seconds="<?= (int) $config->voiceNoteMinSeconds ?>"
        data-max-seconds="<?= (int) $config->voiceNoteMaxSeconds ?>"
        data-max-kb="<?= (int) $config->voiceNoteMaxSizeKB ?>"
        data-exts="<?= esc($exts) ?>"
        data-templates="<?= esc(json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="category_id" value="<?= esc($category['id']) ?>">

        <!-- Spoken updates for screen-reader users; not shown. -->
        <p id="announce" class="bk-sr" aria-live="polite"></p>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3" id="packageHeading">1. Choose a package</h2>
                <div class="row g-3" role="radiogroup" aria-labelledby="packageHeading" id="packageGroup">
                    <?php foreach ($rateTiers as $tier): ?>
                        <div class="col-md-6">
                            <label class="bk-pkg">
                                <span class="bk-pkg-row">
                                    <span>
                                        <input type="radio" name="rate_tier_id" value="<?= esc($tier['id']) ?>" required class="form-check-input me-2"
                                            <?= (string) old('rate_tier_id') === (string) $tier['id'] ? 'checked' : '' ?>>
                                        <span class="bk-pkg-name"><?= esc($tier['package_level']) ?></span>
                                    </span>
                                    <span class="bk-pkg-price"><?= cbs_money((float) $tier['price'], $tier['currency']) ?></span>
                                </span>
                                <?php if (! empty($tier['features'])): ?>
                                    <span class="bk-pkg-note d-block"><?= esc($tier['features']) ?></span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p id="packageError" class="bk-error" role="alert" hidden></p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">2. Your details</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="senderName">Your name</label>
                        <input type="text" name="sender_name" id="senderName" class="form-control" required autocomplete="name"
                            maxlength="150" value="<?= old('sender_name') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="senderPhone">Your phone (for confirmation)</label>
                        <input type="tel" name="sender_phone" id="senderPhone" class="form-control" required autocomplete="tel"
                            inputmode="tel" maxlength="20" value="<?= old('sender_phone') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="senderEmail">Your email (optional)</label>
                        <input type="email" name="sender_email" id="senderEmail" class="form-control" autocomplete="email"
                            maxlength="150" value="<?= old('sender_email') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="recipientName">Recipient's name</label>
                        <input type="text" name="recipient_name" id="recipientName" class="form-control" required
                            maxlength="<?= (int) $config->recipientNameMaxLength ?>" value="<?= old('recipient_name') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">3. Your message</h2>

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
                        aria-describedby="messageStatus messageError"><?= old('message_text') ?></textarea>

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

                <hr class="my-4">

                <fieldset class="bk-voice" id="voice" aria-describedby="voiceHelp">
                    <legend>Add your voice <span class="bk-optional">(optional)</span></legend>
                    <p class="bk-help" id="voiceHelp">
                        Between <?= (int) $config->voiceNoteMinSeconds ?> and <?= (int) $config->voiceNoteMaxSeconds ?> seconds.
                        It's recorded on your device and only sent when you send the booking.
                    </p>

                    <div id="voiceIdle">
                        <div class="bk-voice-actions">
                            <button type="button" class="bk-btn bk-btn--rec" id="recBtn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                    <rect x="9" y="3" width="6" height="11" rx="3" />
                                    <path d="M5 11a7 7 0 0 0 14 0M12 18v3" />
                                </svg>
                                Record a voice note
                            </button>
                            <button type="button" class="bk-btn bk-btn--ghost" id="pickBtn">Choose an audio file</button>
                        </div>
                        <p class="bk-help mt-2 mb-0" id="voiceUnavailable" hidden></p>
                    </div>

                    <div class="bk-rec" id="voiceRec" hidden>
                        <div class="bk-rec-top">
                            <span class="bk-rec-dot" aria-hidden="true"></span>
                            <span class="bk-time" id="recTime" aria-hidden="true">0:00</span>
                            <span class="bk-time-max" aria-hidden="true">of <?= (int) floor($config->voiceNoteMaxSeconds / 60) ?>:<?= str_pad((string) ($config->voiceNoteMaxSeconds % 60), 2, '0', STR_PAD_LEFT) ?></span>
                            <span class="bk-sr">Recording</span>
                        </div>
                        <div class="bk-bar" aria-hidden="true"><span id="recBar"></span></div>
                        <div class="bk-level-row" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <rect x="9" y="3" width="6" height="11" rx="3" />
                                <path d="M5 11a7 7 0 0 0 14 0M12 18v3" />
                            </svg>
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

                    <p class="bk-status mt-2" id="voiceMsg" role="status"></p>
                    <p class="bk-error" id="voiceErr" role="alert" hidden></p>

                    <!-- Without JavaScript this is a plain file picker; with it, the buttons above drive it. -->
                    <div class="bk-voice-note-nojs">
                        <label class="form-label" for="voiceNoteInput">Choose an audio file</label>
                        <input type="file" name="voice_note" id="voiceNoteInput" class="form-control"
                            accept="audio/*,.m4a,.opus,.aac,.webm,.ogg,.mp3,.wav">
                    </div>
                    <input type="hidden" name="voice_note_duration_seconds" id="voiceDuration">
                </fieldset>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">4. When should it air? (optional)</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="showId">Show</label>
                        <select name="show_id" id="showId" class="form-select">
                            <option value="">No preference</option>
                            <?php foreach ($shows as $show): ?>
                                <option value="<?= esc($show['id']) ?>" <?= (string) old('show_id') === (string) $show['id'] ? 'selected' : '' ?>>
                                    <?= esc($show['name']) ?> (<?= esc(substr($show['start_time'], 0, 5)) ?>&ndash;<?= esc(substr($show['end_time'], 0, 5)) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="scheduledDate">Date</label>
                        <input type="date" name="scheduled_date" id="scheduledDate" class="form-control" min="<?= date('Y-m-d') ?>"
                            value="<?= old('scheduled_date') ?>">
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-cbs-primary btn-lg" id="submitBtn">Continue to payment</button>
    </form>

<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= esc($asset('assets/js/booking.js')) ?>" defer></script>
<?= $this->endSection() ?>