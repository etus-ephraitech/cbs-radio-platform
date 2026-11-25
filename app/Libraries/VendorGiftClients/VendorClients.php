<?php

namespace App\Libraries\VendorGiftClients;

use App\Models\GiftVendorModel;
use LogicException;

/**
 * Picks the integration for a vendor from its `driver`. The one place that knows which class speaks to which
 * vendor, so that sync, ordering and checkout never name a concrete client.
 */
final class VendorClients
{
    /**
     * @param array<string, mixed> $vendor a gift_vendors row
     */
    public static function for(array $vendor): VendorGiftClientInterface
    {
        return ($vendor['driver'] ?? GiftVendorModel::DRIVER_GENERIC) === GiftVendorModel::DRIVER_KATALE
            ? new KataleGiftClient($vendor)
            : new GenericRestVendorClient($vendor);
    }

    /**
     * @param array<string, mixed> $vendor a gift_vendors row
     */
    public static function hosted(array $vendor): HostedCheckoutClientInterface
    {
        $client = self::for($vendor);

        if (! $client instanceof HostedCheckoutClientInterface) {
            throw new LogicException('Vendor "' . ($vendor['name'] ?? '?') . '" does not use a hosted checkout.');
        }

        return $client;
    }
}
