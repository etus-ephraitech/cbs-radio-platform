<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * One row of the settings form, built entirely from a setting's registry
 * definition (see App\Libraries\Settings\Registry): the right control for
 * its type, its help text, what the default would be, and — when an admin
 * has overridden it — a "reset to default" tick box.
 *
 * Field names are `s[<field>]` / `reset[<field>]`, where <field> has no dots
 * (old() treats dots as array paths). After a failed save, what the admin
 * typed is shown again rather than the stored value.
 *
 *   <?= view_cell('App\Cells\SettingField::render', ['item' => $item]) ?>
 *
 * $item is one entry of SettingsManager::forGroup().
 */
class SettingField extends Cell
{
    public array $item = [];

    public function render(): string
    {
        if ($this->item === []) {
            return '';
        }

        helper('form');

        $def     = $this->item['def'];
        $field   = $this->item['field'];
        $id      = 'f-' . $field;
        $name    = 's[' . $field . ']';
        $posted  = old('s.' . $field, null, false); // raw: escaped once, below
        $type    = $def['type'];

        $html = '<div class="mb-4">';

        if ($type !== 'bool') {
            $html .= '<label class="form-label fw-semibold" for="' . $id . '">' . esc($def['label']) . $this->customBadge() . '</label>';
        }

        $html .= match ($type) {
            'bool'   => $this->switch($id, $name, $def, $posted),
            'int'    => $this->input($id, $name, 'number', $posted ?? $this->item['current'], $def),
            'select' => $this->select($id, $name, $def, $posted ?? $this->item['current']),
            'text'   => $this->textarea($id, $name, $posted ?? $this->item['current'], $def),
            'list'   => $this->textarea($id, $name, $posted ?? implode("\n", (array) $this->item['current']), $def),
            default  => $this->input($id, $name, $this->htmlType($def), $posted ?? $this->item['current'], $def),
        };

        if (($def['help'] ?? '') !== '') {
            $html .= '<div class="form-text">' . esc($def['help']) . '</div>';
        }

        if (! empty($def['sms'])) {
            $html .= '<div class="form-text" data-sms-counter-for="' . $id . '"></div>';
        }

        $html .= '<div class="form-text">Default: ' . $this->describe($this->item['base'], $def) . '</div>';

        if ($this->item['overridden']) {
            $html .= '<div class="form-check mt-1"><input type="checkbox" class="form-check-input" name="reset[' . $field
                . ']" value="1" id="reset-' . $field . '"><label class="form-check-label small" for="reset-' . $field
                . '">Reset to default</label></div>';
        }

        return $html . '</div>';
    }

    protected function customBadge(): string
    {
        return $this->item['overridden'] ? ' <span class="badge bg-info text-dark fw-normal">Customised</span>' : '';
    }

    protected function htmlType(array $def): string
    {
        return match ($def['format'] ?? '') {
            'email'    => 'email',
            'phone'    => 'tel',
            'datetime' => 'datetime-local',
            default    => 'text',
        };
    }

    protected function input(string $id, string $name, string $type, mixed $value, array $def): string
    {
        $extra = '';

        if ($type === 'number') {
            $extra = ' min="' . (int) $def['min'] . '" max="' . (int) $def['max'] . '"';
        } elseif (isset($def['maxLength'])) {
            $extra = ' maxlength="' . (int) $def['maxLength'] . '"';
        }

        // A date-and-time picker wants "2026-12-01T09:00"; what is stored (and shown as the default) has a space.
        if ($type === 'datetime-local') {
            $value = str_replace(' ', 'T', (string) $value);
        }

        return '<input type="' . $type . '" class="form-control" id="' . $id . '" name="' . esc($name, 'attr')
            . '" value="' . esc((string) $value, 'attr') . '"' . $extra
            . (($def['required'] ?? false) ? ' required' : '') . '>';
    }

    protected function textarea(string $id, string $name, mixed $value, array $def): string
    {
        return '<textarea class="form-control" id="' . $id . '" name="' . esc($name, 'attr') . '" rows="' . (int) ($def['rows'] ?? 3)
            . '"' . (isset($def['maxLength']) && $def['type'] !== 'list' ? ' maxlength="' . (int) $def['maxLength'] . '"' : '')
            . (! empty($def['sms']) ? ' data-sms-counter="1"' : '')
            . '>' . esc((string) $value) . '</textarea>';
    }

    protected function select(string $id, string $name, array $def, mixed $value): string
    {
        $html = '<select class="form-select" id="' . $id . '" name="' . esc($name, 'attr') . '">';

        foreach ($def['options'] as $optionValue => $label) {
            $html .= '<option value="' . esc((string) $optionValue, 'attr') . '"'
                . ((string) $optionValue === (string) $value ? ' selected' : '') . '>' . esc($label) . '</option>';
        }

        return $html . '</select>';
    }

    protected function switch(string $id, string $name, array $def, mixed $posted): string
    {
        // The hidden 0 makes an unticked box submit "0" instead of nothing.
        $checked = $posted !== null ? $posted === '1' : (bool) $this->item['current'];

        return '<input type="hidden" name="' . esc($name, 'attr') . '" value="0">'
            . '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="' . $id
            . '" name="' . esc($name, 'attr') . '" value="1"' . ($checked ? ' checked' : '') . '>'
            . '<label class="form-check-label fw-semibold" for="' . $id . '">' . esc($def['label']) . $this->customBadge() . '</label></div>';
    }

    /** A short, safe description of a default value for the "Default: ..." line. */
    protected function describe(mixed $value, array $def): string
    {
        if ($def['type'] === 'bool') {
            return $value ? 'on' : 'off';
        }

        if ($def['type'] === 'list') {
            $count = count((array) $value);

            return $count === 0 ? 'none' : $count . ' ' . ($count === 1 ? 'entry' : 'entries');
        }

        if ($def['type'] === 'select') {
            return '<code>' . esc($def['options'][(string) $value] ?? (string) $value) . '</code>';
        }

        $text = trim((string) $value);

        if ($text === '') {
            return '<em>empty</em>';
        }

        return '<code>' . esc(mb_strlen($text) > 70 ? mb_substr($text, 0, 70) . '…' : $text) . '</code>';
    }
}
