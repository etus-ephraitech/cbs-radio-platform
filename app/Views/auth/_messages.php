<?php
/**
 * The flash message ci4-auth sets after an action ("message" for success, "error" for a problem).
 */
?>
<?php if (is_string(session('message')) && session('message') !== ''): ?>
    <div class="au-msg au-msg--ok" role="status"><?= cbs_icon_svg('check', 1.15) ?><span><?= esc(session('message')) ?></span></div>
<?php endif ?>

<?php if (is_string(session('error')) && session('error') !== ''): ?>
    <div class="au-msg au-msg--error" role="alert"><?= cbs_icon_svg('alert', 1.15) ?><span><?= esc(session('error')) ?></span></div>
<?php endif ?>
