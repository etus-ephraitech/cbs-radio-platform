<?php
/**
 * Under the template's message box (both admin UIs): buttons that put a placeholder where the cursor is, a live
 * character count against the booking form's limit, and a preview of the message as a visitor will see it.
 * public/assets/js/template-editor.js does the work; without it the box is still an ordinary text area.
 *
 * @var int      $maxLength the booking form's message limit
 * @var string[] $tokens    placeholders a template may use, without braces
 * @var int      $wordsPerMinute reading pace for the on-air estimate
 */
$hint = ['recipient' => 'The name the visitor typed for who it is for', 'sender' => 'The name the visitor typed for themselves'];
?>
<div class="tpl-tools mt-2" data-target="template-body" data-max="<?= (int) $maxLength ?>" data-wpm="<?= (int) $wordsPerMinute ?>"
     data-tokens="<?= esc(implode(',', $tokens), 'attr') ?>">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="small text-muted">Insert:</span>
        <?php foreach ($tokens as $token): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-token="<?= esc($token, 'attr') ?>" title="<?= esc($hint[$token] ?? '', 'attr') ?>">
                <code>{<?= esc($token) ?>}</code>
            </button>
        <?php endforeach; ?>
        <span class="ms-auto small" id="tplCount" aria-live="polite"></span>
    </div>

    <p class="small text-danger mt-2 mb-0" id="tplUnknown" role="alert" hidden></p>

    <div class="border rounded p-3 mt-3 bg-light">
        <div class="small text-muted mb-1">How a visitor will see it <span class="text-nowrap">(with the names “Mama Ruth” and “Grace” typed in)</span></div>
        <div id="tplPreview" style="white-space: pre-wrap;" aria-live="polite"></div>
        <div class="small text-muted mt-2" id="tplOnAir"></div>
    </div>
</div>
