<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Append-only history of settings changes — who changed what, from what,
 * to what. A correction is a new row, never an edit.
 */
class SettingChangeModel extends Model
{
    protected $table            = 'setting_changes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['setting_key', 'action', 'old_value', 'new_value', 'changed_by'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $skipValidation = true;

    public function record(string $key, string $action, ?string $old, ?string $new, ?int $userId): void
    {
        $this->insert([
            'setting_key' => $key,
            'action'      => $action,
            'old_value'   => $old,
            'new_value'   => $new,
            'changed_by'  => $userId,
        ]);
    }

    public function recent(int $limit = 15): array
    {
        return $this->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll($limit);
    }
}
