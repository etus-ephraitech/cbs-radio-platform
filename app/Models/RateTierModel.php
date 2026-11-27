<?php

namespace App\Models;

use CodeIgniter\Model;

class RateTierModel extends Model
{
    protected $table            = 'rate_tiers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'category_id',
        'package_level',
        'features',
        'includes_on_air',
        'includes_voice_note',
        'price',
        'currency',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'category_id'     => 'required|is_natural_no_zero',
        'package_level'   => 'required|max_length[100]',
        'features'        => 'permit_empty',
        'includes_on_air' => 'permit_empty|in_list[0,1]',
        'includes_voice_note' => 'permit_empty|in_list[0,1]',
        'price'           => 'required|decimal|greater_than_equal_to[0]',
        'currency'        => 'permit_empty|max_length[3]',
        'is_active'       => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Active rate tiers for a given category, for the booking flow's
     * package-selection step.
     */
    public function forCategory(int $categoryId): array
    {
        return $this->where('category_id', $categoryId)
            ->where('is_active', 1)
            ->orderBy('price', 'ASC')
            ->findAll();
    }

    /**
     * Every package a visitor can actually book: active packages in active
     * categories, cheapest first within each category. This is what the
     * public homepage lists — an inactive package or category must never
     * appear there, because the booking page would refuse it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activePricing(): array
    {
        return $this->select('rate_tiers.*, categories.name AS category_name, categories.slug AS category_slug')
            ->join('categories', 'categories.id = rate_tiers.category_id')
            ->where('rate_tiers.is_active', 1)
            ->where('categories.is_active', 1)
            ->orderBy('categories.name', 'ASC')
            ->orderBy('rate_tiers.price', 'ASC')
            ->findAll();
    }

    /**
     * Every rate tier with its parent category's name attached, for the
     * admin listing — avoids an N+1 category lookup per row.
     */
    public function allWithCategory(): array
    {
        return $this->select('rate_tiers.*, categories.name AS category_name')
            ->join('categories', 'categories.id = rate_tiers.category_id')
            ->orderBy('categories.name', 'ASC')
            ->orderBy('rate_tiers.price', 'ASC')
            ->findAll();
    }
}
