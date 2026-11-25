<?php

namespace App\Models;

use App\Models\Traits\GeneratesUniqueSlugs;
use CodeIgniter\Model;

/**
 * A third-party vendor whose gift catalog is pulled in via API and shown
 * alongside our own local digital gifts (see gifts.source). The vendor's
 * actual API key is never stored here — it lives in .env as
 * vendor.{slug}.apiKey, looked up by GenericRestVendorClient using the
 * slug from this table.
 */
class GiftVendorModel extends Model
{
    use GeneratesUniqueSlugs;

    protected $table            = 'gift_vendors';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    /** Pays the vendor's own checkout, never ours: gifts from these vendors are bought through the gift cart. */
    public const DRIVER_GENERIC = 'generic';
    public const DRIVER_KATALE  = 'katale';
    public const HOSTED_DRIVERS = [self::DRIVER_KATALE];

    protected $allowedFields = [
        'name',
        'slug',
        'driver',
        'api_base_url',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        // Needed by the {id} placeholder in the slug rule below: CodeIgniter insists the placeholder's field
        // has a rule of its own. See GeneratesUniqueSlugs::update().
        'id'          => 'permit_empty|is_natural_no_zero',
        'name'         => 'required|max_length[150]',
        'slug'         => 'required|max_length[100]|is_unique[gift_vendors.slug,id,{id}]',
        'driver'       => 'permit_empty|in_list[generic,katale]',
        'api_base_url' => 'permit_empty|valid_url',
        'is_active'    => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * True when customers pay this vendor's own hosted checkout rather than paying us.
     *
     * @param array<string, mixed>|null $vendor
     */
    public static function isHosted(?array $vendor): bool
    {
        return $vendor !== null && in_array($vendor['driver'] ?? self::DRIVER_GENERIC, self::HOSTED_DRIVERS, true);
    }

    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }
}
