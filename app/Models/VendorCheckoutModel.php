<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * One hosted checkout with a vendor: the cart a visitor sent to the vendor, the session the vendor opened for it,
 * and what the vendor has since told us about payment and delivery. The visitor pays the vendor, not us, so this
 * table is the only record on our side that the purchase happened.
 */
class VendorCheckoutModel extends Model
{
    protected $table            = 'vendor_checkouts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    public const STATE_DRAFT            = 'draft';
    public const STATE_OPEN             = 'open';
    public const STATE_IN_CHECKOUT      = 'in_checkout';
    public const STATE_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATE_PAID             = 'paid';
    public const STATE_CANCELLED        = 'cancelled';
    public const STATE_EXPIRED          = 'expired';
    public const STATE_FAILED           = 'failed';

    /** Every state the vendor can report for a session. Anything else it sends is ignored rather than stored. */
    public const VENDOR_STATES = [
        self::STATE_OPEN, self::STATE_IN_CHECKOUT, self::STATE_AWAITING_PAYMENT,
        self::STATE_PAID, self::STATE_CANCELLED, self::STATE_EXPIRED,
    ];

    /** Not paid yet and not over: worth asking the vendor about again later. */
    public const WAITING_STATES = [self::STATE_OPEN, self::STATE_IN_CHECKOUT, self::STATE_AWAITING_PAYMENT];

    /** Delivery states after which nothing more will change. */
    public const FINAL_DELIVERY_STATES = ['delivered', 'cancelled'];

    protected $allowedFields = [
        'vendor_id',
        'public_token',
        'purchase_reference',
        'idempotency_key',
        'items',
        'estimate_total',
        'currency',
        'state',
        'paid',
        'session_id',
        'checkout_url',
        'expires_at',
        'vendor_order_id',
        'vendor_reference',
        'final_amount',
        'order_details',
        'last_error',
        'last_checked_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'vendor_id'          => 'required|is_natural_no_zero',
        'public_token'       => 'required|exact_length[32]',
        'purchase_reference' => 'required|max_length[40]',
        'idempotency_key'    => 'required|min_length[8]|max_length[100]',
        'items'              => 'required',
        'estimate_total'     => 'required|decimal',
        'currency'           => 'permit_empty|max_length[3]',
        'state'              => 'permit_empty|in_list[draft,open,in_checkout,awaiting_payment,paid,cancelled,expired,failed]',
        'paid'               => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * The purchase behind a visitor's link. Anything that is not a well-formed token finds nothing, and never
     * reaches the database.
     */
    public function findByToken(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return null;
        }

        return $this->where('public_token', $token)->first();
    }

    /**
     * Newest first, with the vendor's name attached. Pass a state to filter.
     */
    public function adminList(?string $state = null): array
    {
        $this->select('vendor_checkouts.*, gift_vendors.name AS vendor_name')
            ->join('gift_vendors', 'gift_vendors.id = vendor_checkouts.vendor_id');

        if ($state !== null && $state !== '') {
            $this->where('vendor_checkouts.state', $state);
        }

        return $this->orderBy('vendor_checkouts.created_at', 'DESC')->orderBy('vendor_checkouts.id', 'DESC')->findAll();
    }

    /**
     * The admin list with filters: state, shop, a started-between date range, and a search over the purchase
     * reference, the shop's own reference and the shop's name.
     *
     * @param array<string, string> $filters q, state, vendor, from, to (already checked by AdminFilters)
     */
    public function adminSearch(array $filters, int $limit, int $offset): array
    {
        $this->select('vendor_checkouts.*, gift_vendors.name AS vendor_name')
            ->join('gift_vendors', 'gift_vendors.id = vendor_checkouts.vendor_id');
        $this->applyAdminFilters($filters);

        return $this->orderBy('vendor_checkouts.created_at', 'DESC')->orderBy('vendor_checkouts.id', 'DESC')->findAll($limit, $offset);
    }

    public function adminCount(array $filters): int
    {
        $this->join('gift_vendors', 'gift_vendors.id = vendor_checkouts.vendor_id');
        $this->applyAdminFilters($filters);

        return $this->countAllResults();
    }

    private function applyAdminFilters(array $f): void
    {
        if (($f['state'] ?? '') !== '') {
            $this->where('vendor_checkouts.state', $f['state']);
        }

        if (($f['vendor'] ?? '') !== '') {
            $this->where('vendor_checkouts.vendor_id', (int) $f['vendor']);
        }

        if (($f['from'] ?? '') !== '') {
            $this->where('vendor_checkouts.created_at >=', $f['from'] . ' 00:00:00');
        }

        if (($f['to'] ?? '') !== '') {
            $this->where('vendor_checkouts.created_at <=', $f['to'] . ' 23:59:59');
        }

        $q = $f['q'] ?? '';

        if ($q !== '') {
            $this->groupStart()
                ->like('vendor_checkouts.purchase_reference', $q)
                ->orLike('vendor_checkouts.vendor_reference', $q)
                ->orLike('gift_vendors.name', $q)
                ->groupEnd();
        }
    }

    public function withVendor(int $id): ?array
    {
        return $this->select('vendor_checkouts.*, gift_vendors.name AS vendor_name')
            ->join('gift_vendors', 'gift_vendors.id = vendor_checkouts.vendor_id')
            ->where('vendor_checkouts.id', $id)
            ->first();
    }

    /**
     * The purchases the scheduled check should ask the vendor about: sessions still waiting (for two weeks), and
     * paid purchases whose delivery is still moving (for a month). The longest-unchecked go first, so a run that
     * stops at its limit still gets to the rest next time.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dueForCheck(int $limit): array
    {
        $waiting = implode(',', array_map(static fn (string $s): string => "'" . $s . "'", self::WAITING_STATES));

        return $this->where('session_id IS NOT NULL', null, false)
            ->groupStart()
                ->groupStart()
                    ->where("state IN ({$waiting})", null, false)
                    ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-14 days')))
                ->groupEnd()
                ->orGroupStart()
                    ->where('state', self::STATE_PAID)
                    ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-30 days')))
                ->groupEnd()
            ->groupEnd()
            ->orderBy('last_checked_at IS NULL', 'DESC', false)
            ->orderBy('last_checked_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll($limit);
    }

    /**
     * The cart lines of a purchase, as stored.
     *
     * @param array<string, mixed> $checkout
     *
     * @return array<int, array<string, mixed>>
     */
    public static function lines(array $checkout): array
    {
        $decoded = json_decode((string) ($checkout['items'] ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * What the vendor last said about the order and the delivery of each gift.
     *
     * @param array<string, mixed> $checkout
     *
     * @return array<string, mixed>
     */
    public static function order(array $checkout): array
    {
        $decoded = json_decode((string) ($checkout['order_details'] ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }
}
