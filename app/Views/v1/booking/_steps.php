<?php
/**
 * The greeting flow's progress steps, shown in the page hero. Included with $this->include() after
 * $this->setVar('current', …): not with a nested view(), whose saveData=false would wipe the variables the page
 * set for its layout (lean, pageTitle).
 *
 * @var int  $current  which step is current (1 = occasion … 6 = review)
 * @var bool $wizard   on the greeting form, steps 2–6 are buttons the form's script drives
 */
$labels = [1 => 'Occasion', 2 => 'Package', 3 => 'Message', 4 => 'Gifts', 5 => 'Your details', 6 => 'Review & pay'];
$wizard ??= false;
?>
<ol class="ck-steps" aria-label="Steps to send a greeting">
    <?php foreach ($labels as $n => $label): ?>
        <?php $state = $n < $current ? 'done' : ($n === $current ? 'current' : 'todo'); ?>
        <li data-state="<?= $state ?>" data-step-indicator="<?= $n ?>"<?= $n === $current ? ' aria-current="step"' : '' ?>>
            <?php if ($n === 1 && $current > 1): ?>
                <a class="ck-step-btn" href="<?= site_url('booking') ?>" title="Choose a different occasion"><?= esc($label) ?></a>
            <?php elseif ($wizard && $n > 1): ?>
                <button type="button" class="ck-step-btn" data-goto="<?= $n ?>" <?= $state === 'todo' ? 'disabled' : '' ?>><?= esc($label) ?></button>
            <?php else: ?>
                <span class="ck-step-btn"><?= esc($label) ?></span>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ol>
