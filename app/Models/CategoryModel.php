<?php

namespace App\Models;

use App\Models\Traits\GeneratesUniqueSlugs;
use CodeIgniter\Model;

class CategoryModel extends Model
{
    use GeneratesUniqueSlugs;

    protected $table            = 'categories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        // Needed by the {id} placeholder in the slug rule below: CodeIgniter insists the placeholder's field
        // has a rule of its own. See GeneratesUniqueSlugs::update().
        'id'          => 'permit_empty|is_natural_no_zero',
        'name'        => 'required|max_length[100]',
        'slug'        => 'required|max_length[100]|is_unique[categories.slug,id,{id}]',
        'description' => 'permit_empty',
        'is_active'   => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Categories available for the booking flow's category selection step.
     */
    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Find a category by its slug (e.g. for a direct link into one category).
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->first();
    }
}
