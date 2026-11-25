<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Admin-chosen overrides for the values defined in
 * App\Libraries\Settings\Registry. A row exists only while an admin has
 * overridden a value; deleting the row hands the value back to .env / the
 * code default.
 */
class SettingModel extends Model
{
    protected $table            = 'settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['setting_key', 'setting_value', 'updated_by'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Values are validated by SettingsManager against the Registry before
    // they get here, so the model does not repeat those rules.
    protected $skipValidation = true;

    /**
     * Every override as key => raw string.
     *
     * @return array<string, string>
     */
    public function allKeyed(): array
    {
        $out = [];

        foreach ($this->findAll() as $row) {
            $out[$row['setting_key']] = (string) $row['setting_value'];
        }

        return $out;
    }

    public function setOverride(string $key, string $value, ?int $userId): void
    {
        $existing = $this->where('setting_key', $key)->first();

        if ($existing === null) {
            $this->insert(['setting_key' => $key, 'setting_value' => $value, 'updated_by' => $userId]);

            return;
        }

        $this->update($existing['id'], ['setting_value' => $value, 'updated_by' => $userId]);
    }

    public function clearOverride(string $key): void
    {
        $this->where('setting_key', $key)->delete();
    }
}
