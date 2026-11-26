<?php

namespace App\Libraries\VendorGiftClients;

/**
 * A vendor whose customers pay the vendor's own checkout. We send the cart, the vendor answers with an address to
 * send the customer to, and we later ask the vendor whether it was paid. Delivery details, the final price and the
 * payment all stay on the vendor's side.
 *
 * Every method that talks to the vendor reports trouble in its result (never by throwing), except fetchGift(),
 * which is part of the catalogue and throws like VendorGiftClientInterface::fetchCatalog() does. A result is:
 *
 *     success    bool    the vendor accepted the request
 *     status     int     the HTTP status, or 0 when the vendor could not be reached
 *     message    string  fit to show a visitor
 *     errors     array   field => messages, when the vendor explained what was wrong
 *     retryable  bool    the same request may work if sent again (a timeout, a busy or failing vendor)
 *     data       array   what the vendor returned, in the shapes below; empty on failure
 */
interface HostedCheckoutClientInterface
{
    /**
     * One gift as the vendor holds it now, with its inventories. Null when the vendor no longer has it.
     *
     * @return array{
     *     vendor_product_ref: string,
     *     name: string,
     *     description: ?string,
     *     price: float,
     *     currency: string,
     *     image_url: ?string,
     *     minimum_delivery_hours: ?int,
     *     stock: int,
     *     inventories: array<int, array{id: string, quantity: int}>
     * }|null
     *
     * @throws \RuntimeException when the vendor cannot be asked
     */
    public function fetchGift(string $vendorProductRef): ?array;

    /**
     * Opens a hosted checkout for a cart.
     *
     * @param array<int, array{gift_id: int, inventory_id: int, quantity: int}> $items
     * @param string                                                          $idempotencyKey the same key and items return the same session
     *
     * @return array{success: bool, status: int, message: string, errors: array, retryable: bool,
     *     data: array{session_id?: string, checkout_url?: string, expires_at?: ?string, currency?: string}}
     */
    public function createCheckoutSession(array $items, string $idempotencyKey, ?string $returnUrl = null): array;

    /**
     * Where a checkout stands: waiting, in checkout, awaiting payment, paid, cancelled or expired.
     *
     * @return array{success: bool, status: int, message: string, errors: array, retryable: bool,
     *     data: array{state?: string, paid?: bool, order_id?: ?string, reference?: ?string, amount?: ?float,
     *         currency?: ?string, expires_at?: ?string}}
     */
    public function checkoutSession(string $sessionId): array;

    /**
     * The vendor's order, with the delivery progress of each gift.
     *
     * @return array{success: bool, status: int, message: string, errors: array, retryable: bool,
     *     data: array{id?: string, reference?: ?string, amount?: ?float, currency?: ?string, payment_method?: ?string,
     *         paid?: bool, cancelled?: bool, status?: int, deliveries?: array<int, array<string, mixed>>}}
     */
    public function order(string $orderId): array;
}
