<?php

namespace App\Models;

use CodeIgniter\Model;

class ShowModel extends Model
{
    protected $table            = 'shows';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'name',
        'start_time',
        'end_time',
        'days_of_week',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'name'         => 'required|max_length[100]',
        'start_time'   => 'required',
        'end_time'     => 'required',
        'days_of_week' => 'permit_empty|max_length[20]',
        'is_active'    => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Shows available for the booking flow's time-slot picker.
     */
    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('start_time', 'ASC')->findAll();
    }

    /**
     * Whether a show runs on a given day of the week (1=Mon..7=Sun), based
     * on its comma-separated days_of_week column. A null/empty value means
     * the show runs daily.
     */
    public function runsOn(array $show, int $isoDayOfWeek): bool
    {
        if (empty($show['days_of_week'])) {
            return true;
        }

        $days = array_map('intval', explode(',', $show['days_of_week']));

        return in_array($isoDayOfWeek, $days, true);
    }
}
