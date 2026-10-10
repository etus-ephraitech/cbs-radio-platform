<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Example messages offered under the message box on the booking form
 * ("Start from an example"). Written and maintained by staff — including
 * the languages — under Admin > Message templates.
 */
class MessageTemplateModel extends Model
{
    /** Placeholders a template body may use; the booking form fills them from what the visitor has typed. */
    public const TOKENS = ['recipient', 'sender'];

    protected $table            = 'message_templates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = ['category_id', 'language', 'title', 'body', 'sort_order', 'is_active'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'category_id' => 'permit_empty|is_natural_no_zero',
        'language'    => 'required|max_length[40]',
        'title'       => 'required|max_length[80]',
        'body'        => 'required',
        'sort_order'  => 'permit_empty|is_natural',
        'is_active'   => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * The templates to offer on one category's booking form: its own, plus
     * any marked "any occasion", active only, in the order staff set.
     */
    public function forCategory(int $categoryId): array
    {
        return $this->groupStart()
                ->where('category_id', $categoryId)
                ->orWhere('category_id', null)
            ->groupEnd()
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('language', 'ASC')
            ->orderBy('title', 'ASC')
            ->findAll();
    }

    /**
     * Every template with its category's name, for the admin list.
     */
    public function adminList(): array
    {
        return $this->select('message_templates.*, categories.name AS category_name')
            ->join('categories', 'categories.id = message_templates.category_id', 'left')
            ->orderBy('categories.name', 'ASC')
            ->orderBy('message_templates.sort_order', 'ASC')
            ->orderBy('message_templates.title', 'ASC')
            ->findAll();
    }

    /**
     * Languages already in use, so the admin form can suggest them.
     *
     * @return string[]
     */
    public function languages(): array
    {
        $rows = $this->select('language')->groupBy('language')->orderBy('language', 'ASC')->findAll();

        return array_values(array_unique(array_merge(['English', 'Luganda'], array_column($rows, 'language'))));
    }
}
