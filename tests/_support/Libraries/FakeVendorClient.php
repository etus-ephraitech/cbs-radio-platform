<?php

namespace Tests\Support\Libraries;

use App\Libraries\VendorGiftClients\VendorGiftClientInterface;
use RuntimeException;

/**
 * A gift vendor that answers from a script instead of the internet: a fixed catalogue, and a chosen result for
 * every order. Records the orders it receives so a test can check what would have been sent.
 */
class FakeVendorClient implements VendorGiftClientInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $orders = [];

    /**
     * @param array<int, array<string, mixed>> $catalog       what fetchCatalog() returns
     * @param array<string, mixed>             $orderResult   what submitOrder() returns
     * @param string|null                      $failCatalogWith throw this from fetchCatalog() (a vendor that is down)
     */
    public function __construct(
        public array $catalog = [],
        public array $orderResult = ['success' => true, 'vendor_order_ref' => 'V-1001', 'message' => 'Order placed with vendor.'],
        public ?string $failCatalogWith = null,
    ) {
    }

    public function fetchCatalog(): array
    {
        if ($this->failCatalogWith !== null) {
            throw new RuntimeException($this->failCatalogWith);
        }

        return $this->catalog;
    }

    public function submitOrder(array $orderData): array
    {
        $this->orders[] = $orderData;

        return $this->orderResult;
    }
}
