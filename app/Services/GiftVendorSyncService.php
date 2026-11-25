<?php

namespace App\Services;

use App\Libraries\VendorGiftClients\VendorClients;
use App\Libraries\VendorGiftClients\VendorGiftClientInterface;
use App\Models\GiftModel;
use App\Models\GiftVendorModel;

/**
 * Pulls every active vendor's catalog into the gifts table, so they show
 * up in the public catalog alongside local gifts. Run via the
 * `php spark gifts:sync` command (schedule it with cron) — never on a
 * user-facing request, since a vendor API can be slow or down.
 */
class GiftVendorSyncService
{
    protected GiftVendorModel $giftVendorModel;
    protected GiftModel $giftModel;

    public function __construct(?GiftVendorModel $giftVendorModel = null, ?GiftModel $giftModel = null)
    {
        $this->giftVendorModel = $giftVendorModel ?? new GiftVendorModel();
        $this->giftModel       = $giftModel ?? new GiftModel();
    }

    /**
     * @return array{synced: int, errors: string[]}
     */
    public function syncAll(): array
    {
        $vendors = $this->giftVendorModel->active();
        $synced  = 0;
        $errors  = [];

        foreach ($vendors as $vendor) {
            try {
                $synced += $this->syncVendor($vendor);
            } catch (\Throwable $e) {
                $errors[] = $vendor['name'] . ': ' . $e->getMessage();
            }
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * The client for one vendor. A method of its own so that a test can supply a fake, instead of the
     * service reaching out to a real vendor's servers.
     */
    protected function vendorClient(array $vendor): VendorGiftClientInterface
    {
        return VendorClients::for($vendor);
    }

    protected function syncVendor(array $vendor): int
    {
        // Which client speaks to this vendor is its `driver` (see VendorClients). A client may throw when the
        // vendor cannot be read (Katale does, so that a half-read catalogue is a failed sync); syncAll() reports it.
        $client  = $this->vendorClient($vendor);
        $catalog = $client->fetchCatalog();
        $count   = 0;

        foreach ($catalog as $item) {
            $existing = $this->giftModel->findByVendorProduct((int) $vendor['id'], $item['vendor_product_ref']);

            $data = [
                'name'               => $item['name'],
                'type'               => $item['type'],
                'description'        => $item['description'],
                'price'              => $item['price'],
                'currency'           => $item['currency'],
                'source'             => GiftModel::SOURCE_VENDOR,
                'vendor_id'          => $vendor['id'],
                'vendor_product_ref' => $item['vendor_product_ref'],
                'synced_at'          => date('Y-m-d H:i:s'),
            ];

            // What only some vendors report: a picture, the preparation time and the units left. Written only when
            // the vendor sent it, so a vendor that does not report them never blanks what is already there.
            foreach (['image_url', 'minimum_delivery_hours'] as $optional) {
                if (array_key_exists($optional, $item)) {
                    $data[$optional] = $item[$optional];
                }
            }

            if (array_key_exists('stock', $item)) {
                $data['stock_available'] = (int) $item['stock'];
            }

            if ($existing !== null) {
                // Deliberately does NOT touch is_active — an admin may have
                // hidden this specific vendor item, and a re-sync should
                // never silently undo that.
                $this->giftModel->update($existing['id'], $data);
            } else {
                $data['is_active'] = 1;
                $this->giftModel->insert($data);
            }

            $count++;
        }

        return $count;
    }
}
