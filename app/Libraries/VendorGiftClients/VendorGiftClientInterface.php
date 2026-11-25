<?php

namespace App\Libraries\VendorGiftClients;

/**
 * Contract every vendor gift integration must implement. One vendor whose
 * API shape differs meaningfully from the generic REST assumption in
 * GenericRestVendorClient gets its own class implementing this interface
 * — GiftVendorSyncService and GiftOrderService depend on this interface,
 * never a concrete client, so adding a second vendor never means
 * touching either of those.
 */
interface VendorGiftClientInterface
{
    /**
     * Fetches the vendor's current product catalog, normalized to this
     * shape regardless of the vendor's own field names.
     *
     * @return array<int, array{
     *     vendor_product_ref: string,
     *     name: string,
     *     description: ?string,
     *     price: float,
     *     currency: string,
     *     type: string,
     *     image_url?: ?string,
     *     minimum_delivery_hours?: ?int,
     *     stock?: int
     * }>  The last three are optional: a vendor that reports them has them stored on the gift (see
     *     GiftVendorSyncService), one that does not simply leaves them out.
     */
    public function fetchCatalog(): array;

    /**
     * Places an order with the vendor once a gift order on our side has
     * been paid.
     *
     * @param array{
     *     vendor_product_ref: string,
     *     quantity: int,
     *     recipient_name: string,
     *     recipient_phone: ?string,
     *     delivery_details: ?string
     * } $orderData
     * @return array{success: bool, vendor_order_ref: ?string, message: string}
     */
    public function submitOrder(array $orderData): array;
}
