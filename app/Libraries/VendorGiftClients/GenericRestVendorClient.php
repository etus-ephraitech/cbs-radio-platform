<?php

namespace App\Libraries\VendorGiftClients;

use Config\Services as CoreServices;

/**
 * A reasonable-default REST client for a vendor exposing a simple
 * "list products" / "create order" API. This is the gift-catalog
 * equivalent of PaymentService::chargeMobileMoney() and
 * NotificationService::sendSms() — a real, working shape, but the exact
 * endpoint paths and JSON field names below are assumed, not confirmed
 * against any specific vendor's actual documentation.
 *
 * OPEN ITEM: once CBS/Ephraitech has a confirmed vendor, verify:
 *   - the catalog endpoint path and its response field names (assumed:
 *     GET {base}/products -> {"data": [{"id","name","description","price","currency","category"}]})
 *   - the order endpoint path and its response field names (assumed:
 *     POST {base}/orders -> {"order_id": "..."})
 *   - the authentication method (assumed: Bearer token from
 *     vendor.{slug}.apiKey)
 * If the real vendor's API differs meaningfully, write a new class
 * implementing VendorGiftClientInterface rather than bending this one.
 */
class GenericRestVendorClient implements VendorGiftClientInterface
{
    protected array $vendor;

    public function __construct(array $vendor)
    {
        $this->vendor = $vendor;
    }

    public function fetchCatalog(): array
    {
        $apiKey = env('vendor.' . $this->vendor['slug'] . '.apiKey');

        if (empty($this->vendor['api_base_url']) || empty($apiKey)) {
            return [];
        }

        try {
            $client   = CoreServices::curlrequest();
            $response = $client->get(rtrim($this->vendor['api_base_url'], '/') . '/products', [
                'headers' => ['Authorization' => 'Bearer ' . $apiKey, 'Accept' => 'application/json'],
            ]);

            $body = json_decode($response->getBody(), true);
            $rows = $body['data'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (empty($row['id']) || empty($row['name']) || ! isset($row['price'])) {
                continue;
            }

            $normalized[] = [
                'vendor_product_ref' => (string) $row['id'],
                'name'               => $row['name'],
                'description'        => $row['description'] ?? null,
                'price'              => (float) $row['price'],
                'currency'           => $row['currency'] ?? 'UGX',
                // Defaults to 'physical' since the original partner-gift
                // use case (flowers/cakes/hampers) is the most likely
                // reason to integrate a vendor catalog at all — override
                // per-row if the vendor reports its own category.
                'type' => $row['category'] ?? 'physical',
            ];
        }

        return $normalized;
    }

    public function submitOrder(array $orderData): array
    {
        $apiKey = env('vendor.' . $this->vendor['slug'] . '.apiKey');

        if (empty($this->vendor['api_base_url']) || empty($apiKey)) {
            return ['success' => false, 'vendor_order_ref' => null, 'message' => 'Vendor is not configured.'];
        }

        try {
            $client   = CoreServices::curlrequest();
            $response = $client->post(rtrim($this->vendor['api_base_url'], '/') . '/orders', [
                'headers' => ['Authorization' => 'Bearer ' . $apiKey, 'Content-Type' => 'application/json'],
                'json'    => [
                    'product_id'        => $orderData['vendor_product_ref'],
                    'quantity'          => $orderData['quantity'],
                    'recipient_name'    => $orderData['recipient_name'],
                    'recipient_phone'   => $orderData['recipient_phone'],
                    'delivery_details'  => $orderData['delivery_details'],
                ],
            ]);

            $body = json_decode($response->getBody(), true);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300 && ! empty($body['order_id'])) {
                return ['success' => true, 'vendor_order_ref' => (string) $body['order_id'], 'message' => 'Order placed with vendor.'];
            }

            return ['success' => false, 'vendor_order_ref' => null, 'message' => 'Vendor declined the order.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'vendor_order_ref' => null, 'message' => 'Could not reach vendor: ' . $e->getMessage()];
        }
    }
}
