<?php
/**
 * The filter row of an admin list. Rendered by App\Cells\FilterBar; see that class for the variables.
 *
 * @var string                          $action
 * @var list<array<string, mixed>>      $fields
 * @var array<string, mixed>            $values
 * @var int                             $active how many filters are applied
 */
?>
<form method="get" action="<?= site_url(trim($action, '/')) ?>" class="cbs-filters row g-2 align-items-end p-3 border-bottom" role="search">
    <?php foreach ($fields as $field): ?>
        <?php
        $name  = (string) $field['name'];
        $type  = (string) ($field['type'] ?? 'search');
        $value = (string) ($values[$name] ?? '');
        $id    = 'filter-' . $name;
        ?>
        <div class="<?= $type === 'search' ? 'col-12 col-md-4' : 'col-6 col-md-2' ?>">
            <label for="<?= esc($id, 'attr') ?>" class="form-label fs-12 mb-1"><?= esc($field['label'] ?? $name) ?></label>
            <?php if ($type === 'select'): ?>
                <select name="<?= esc($name, 'attr') ?>" id="<?= esc($id, 'attr') ?>" class="form-select form-select-sm">
                    <option value=""><?= esc($field['any'] ?? 'Any') ?></option>
                    <?php foreach ((array) ($field['options'] ?? []) as $optionValue => $label): ?>
                        <option value="<?= esc((string) $optionValue, 'attr') ?>"<?= (string) $optionValue === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($type === 'date'): ?>
                <input type="date" name="<?= esc($name, 'attr') ?>" id="<?= esc($id, 'attr') ?>" class="form-control form-control-sm" value="<?= esc($value, 'attr') ?>">
            <?php else: ?>
                <input type="search" name="<?= esc($name, 'attr') ?>" id="<?= esc($id, 'attr') ?>" class="form-control form-control-sm"
                       value="<?= esc($value, 'attr') ?>" placeholder="<?= esc($field['placeholder'] ?? '', 'attr') ?>" autocomplete="off">
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <div class="col-12 col-md-auto d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        <?php if ($active > 0): ?>
            <a href="<?= site_url(trim($action, '/')) ?>" class="btn btn-sm btn-light">Clear (<?= (int) $active ?>)</a>
        <?php endif; ?>
    </div>
</form>
