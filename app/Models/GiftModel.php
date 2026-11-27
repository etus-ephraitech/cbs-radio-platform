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
        'image_url',
        'minimum_delivery_hours',
        'stock_available',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'name'               => 'required|max_length[150]',
        'type'               => 'required|in_list[ecard,airtime,shoutout,dedication,physical]',
        'description'        => 'permit_empty',
        'price'              => 'required|decimal|greater_than_equal_to[0]',
        'currency'           => 'permit_empty|max_length[3]',
        'is_active'          => 'permit_empty|in_list[0,1]',
        'source'             => 'permit_empty|in_list[local,vendor]',
        'vendor_id'          => 'permit_empty|is_natural_no_zero',
        'vendor_product_ref' => 'permit_empty|max_length[150]',
        'image_url'              => 'permit_empty|max_length[500]',
        'minimum_delivery_hours' => 'permit_empty|is_natural',
        'stock_available'        => 'permit_empty|is_natural',
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
        return $this->select('gifts.*, gift_vendors.name AS vendor_name, gift_vendors.driver AS vendor_driver, gift_vendors.is_active AS vendor_active')
            ->join('gift_vendors', 'gift_vendors.id = gifts.vendor_id', 'left')
            ->where('gifts.is_active', 1)
            ->orderBy('gifts.name', 'ASC')
            ->findAll();
    }

    /**
     * The active gifts that can be ordered with the standard form or inside a greeting: everything except gifts
     * from a hosted-checkout vendor (Katale), which are bought through the gift cart and paid on the vendor's page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function orderable(): array
    {
        return array_values(array_filter(
            $this->active(),
            static fn (array $gift): bool => ! ($gift['source'] === self::SOURCE_VENDOR
                && in_array($gift['vendor_driver'] ?? '', GiftVendorModel::HOSTED_DRIVERS, true)),
        ));
    }

    /**
     * The active gifts that are bought through a vendor's hosted checkout (the gift cart), by id.
     * A gift whose vendor is switched off, or that is not from a hosted vendor, is not returned.
     *
     * @param array<int, int|string> $ids
     *
     * @return array<int, array<string, mixed>> keyed by gift id
     */
    public function hostedByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        $rows = $this->select('gifts.*, gift_vendors.name AS vendor_name, gift_vendors.driver AS vendor_driver')
            ->join('gift_vendors', 'gift_vendors.id = gifts.vendor_id')
            ->whereIn('gifts.id', $ids)
            ->where('gifts.is_active', 1)
            ->where('gifts.source', self::SOURCE_VENDOR)
            ->where('gift_vendors.is_active', 1)
            ->whereIn('gift_vendors.driver', GiftVendorModel::HOSTED_DRIVERS)
            ->findAll();

        $byId = [];

        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        return $byId;
    }

    public function byType(string $type): array
    {
        return $this->where('type', $type)->where('is_active', 1)->findAll();
    }

    /**
     * Gifts created directly on this platform — the only ones an admin
     * can hand-edit. Vendor-sourced gifts are managed by GiftVendorSyncService.
     */
    public function localOnly(array $filters = []): array
    {
        $this->applyAdminFilters($filters, 'gifts');

        return $this->where('gifts.source', self::SOURCE_LOCAL)->orderBy('gifts.name', 'ASC')->findAll();
    }

    /**
     * Gifts pulled in from a vendor's API, with the vendor's name attached
     * for the admin listing.
     */
    public function vendorSourced(array $filters = []): array
    {
        $this->applyAdminFilters($filters, 'gifts');

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

    /**
     * The admin catalogue's filters: a search over the name and description, the gift's type, whether it is
     * active, and (for vendor gifts) which vendor.
     *
     * @param array<string, string> $f q, type, active ('1' or '0'), vendor (already checked by AdminFilters)
     */
    private function applyAdminFilters(array $f, string $table): void
    {
        if (($f['type'] ?? '') !== '') {
            $this->where($table . '.type', $f['type']);
        }

        if (in_array($f['active'] ?? '', ['0', '1'], true)) {
            $this->where($table . '.is_active', (int) $f['active']);
        }

        if (($f['vendor'] ?? '') !== '') {
            $this->where($table . '.vendor_id', (int) $f['vendor']);
        }

        $q = $f['q'] ?? '';

        if ($q !== '') {
            $this->groupStart()->like($table . '.name', $q)->orLike($table . '.description', $q)->groupEnd();
        }
    }
}
