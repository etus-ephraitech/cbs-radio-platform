<?php

namespace App\Models;

use CodeIgniter\Model;

class GiftModel extends Model
{
    protected $table            = 'gifts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    public const SOURCE_LOCAL  = 'local';
    public const SOURCE_VENDOR = 'vendor';

    protected $allowedFields = [
        'name',
        'type',
        'description',
        'price',
        'currency',
        'is_active',
        'source',
        'vendor_id',
        'vendor_product_ref',
        'synced_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'name'               => 'required|max_length[150]',
        'type'               => 'required|in_list[ecard,airtime,shoutout,dedication,physical]',
        'description'        => 'permit_empty',
        'price'              => 'required|decimal',
        'currency'           => 'permit_empty|max_length[3]',
        'is_active'          => 'permit_empty|in_list[0,1]',
        'source'             => 'permit_empty|in_list[local,vendor]',
        'vendor_id'          => 'permit_empty|is_natural_no_zero',
        'vendor_product_ref' => 'permit_empty|max_length[150]',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Active gifts for the digital gifts catalog — local and vendor-
     * sourced items together, since the catalog shows both. Vendor name
     * is left-joined (not inner) so local gifts, which have no vendor_id,
     * still return correctly.
     */
    public function active(): array
    {
        return $this->select('gifts.*, gift_vendors.name AS vendor_name')
            ->join('gift_vendors', 'gift_vendors.id = gifts.vendor_id', 'left')
            ->where('gifts.is_active', 1)
            ->orderBy('gifts.name', 'ASC')
            ->findAll();
    }

    public function byType(string $type): array
    {
        return $this->where('type', $type)->where('is_active', 1)->findAll();
    }

    /**
     * Gifts created directly on this platform — the only ones an admin
     * can hand-edit. Vendor-sourced gifts are managed by GiftVendorSyncService.
     */
    public function localOnly(): array
    {
        return $this->where('source', self::SOURCE_LOCAL)->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Gifts pulled in from a vendor's API, with the vendor's name attached
     * for the admin listing.
     */
    public function vendorSourced(): array
    {
        return $this->select('gifts.*, gift_vendors.name AS vendor_name')
            ->join('gift_vendors', 'gift_vendors.id = gifts.vendor_id')
            ->where('gifts.source', self::SOURCE_VENDOR)
            ->orderBy('gift_vendors.name', 'ASC')
            ->orderBy('gifts.name', 'ASC')
            ->findAll();
    }

    /**
     * Finds the existing row for a vendor's product on re-sync, so the
     * sync service updates it in place instead of duplicating it.
     */
    public function findByVendorProduct(int $vendorId, string $vendorProductRef): ?array
    {
        return $this->where('vendor_id', $vendorId)
            ->where('vendor_product_ref', $vendorProductRef)
            ->first();
    }
}
