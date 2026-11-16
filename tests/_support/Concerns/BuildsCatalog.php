<?php

namespace Tests\Support\Concerns;

use App\Models\BookingModel;
use App\Models\CategoryModel;
use App\Models\GiftModel;
use App\Models\GiftOrderModel;
use App\Models\GiftVendorModel;
use App\Models\PaymentModel;
use App\Models\RateTierModel;
use App\Models\ShowModel;
use CodeIgniter\Model;
use RuntimeException;

/**
 * Factories for the rows tests need. Each returns the row as the model reads it back (so tests see the same
 * array the application sees), takes overrides for any field, and throws if the row is invalid, rather than
 * quietly building nothing.
 *
 * Names and slugs are made unique with a counter, so two rows in one test never collide on a unique key.
 */
trait BuildsCatalog
{
    private static int $catalogSequence = 0;

    protected function sequence(): int
    {
        return ++self::$catalogSequence;
    }

    protected function makeCategory(array $overrides = []): array
    {
        $n = $this->sequence();

        return $this->insertRow(new CategoryModel(), $overrides + [
            'name'        => "Category {$n}",
            'slug'        => "category-{$n}",
            'description' => "Description of category {$n}",
            'is_active'   => 1,
        ]);
    }

    protected function makeTier(?int $categoryId = null, array $overrides = []): array
    {
        $n = $this->sequence();

        return $this->insertRow(new RateTierModel(), $overrides + [
            'category_id'     => $categoryId ?? $this->makeCategory()['id'],
            'package_level'   => "Package {$n}",
            'features'        => "Features of package {$n}",
            'includes_on_air' => 1,
            'price'           => 15000,
            'currency'        => 'UGX',
            'is_active'       => 1,
        ]);
    }

    protected function makeShow(array $overrides = []): array
    {
        $n = $this->sequence();

        return $this->insertRow(new ShowModel(), $overrides + [
            'name'         => "Show {$n}",
            'start_time'   => '06:00:00',
            'end_time'     => '10:00:00',
            'days_of_week' => null, // every day
            'is_active'    => 1,
        ]);
    }

    protected function makeGift(array $overrides = []): array
    {
        $n = $this->sequence();

        return $this->insertRow(new GiftModel(), $overrides + [
            'name'      => "Gift {$n}",
            'type'      => 'ecard',
            'price'     => 5000,
            'currency'  => 'UGX',
            'is_active' => 1,
            'source'    => GiftModel::SOURCE_LOCAL,
        ]);
    }

    protected function makeVendor(array $overrides = []): array
    {
        $n = $this->sequence();

        return $this->insertRow(new GiftVendorModel(), $overrides + [
            'name'         => "Vendor {$n}",
            'slug'         => "vendor-{$n}",
            'api_base_url' => 'https://vendor.example.test/api',
            'is_active'    => 1,
        ]);
    }

    /**
     * A booking in a category with a package. Supply category_id and rate_tier_id to attach it to ones you made.
     */
    protected function makeBooking(array $overrides = []): array
    {
        $categoryId = $overrides['category_id'] ?? $this->makeCategory()['id'];
        $tier       = isset($overrides['rate_tier_id'])
            ? (new RateTierModel())->find($overrides['rate_tier_id'])
            : $this->makeTier($categoryId);

        return $this->insertRow(new BookingModel(), $overrides + [
            'category_id'    => $categoryId,
            'rate_tier_id'   => $tier['id'],
            'sender_name'    => 'Grace Nakato',
            'sender_phone'   => '0772123456',
            'sender_email'   => null,
            'recipient_name' => 'Mama Ruth',
            'message_text'   => 'Happy birthday Mama Ruth!',
            'status'         => BookingModel::STATUS_PENDING_PAYMENT,
            'amount'         => $tier['price'],
            'currency'       => $tier['currency'],
        ]);
    }

    protected function makeGiftOrder(array $overrides = []): array
    {
        $gift = isset($overrides['gift_id']) ? (new GiftModel())->find($overrides['gift_id']) : $this->makeGift();

        return $this->insertRow(new GiftOrderModel(), $overrides + [
            'gift_id'         => $gift['id'],
            'sender_name'     => 'Grace Nakato',
            'sender_phone'    => '0772123456',
            'recipient_name'  => 'Mama Ruth',
            'recipient_phone' => '0700111222',
            'quantity'        => 1,
            'amount'          => $gift['price'],
            'currency'        => $gift['currency'],
            'status'          => GiftOrderModel::STATUS_PENDING_PAYMENT,
        ]);
    }

    protected function makePayment(array $overrides = []): array
    {
        if (! isset($overrides['booking_id']) && ! isset($overrides['gift_order_id'])) {
            $overrides['booking_id'] = $this->makeBooking()['id'];
        }

        return $this->insertRow(new PaymentModel(), $overrides + [
            'provider' => 'manual',
            'amount'   => 15000,
            'currency' => 'UGX',
            'status'   => PaymentModel::STATUS_PENDING,
        ]);
    }

    private function insertRow(Model $model, array $data): array
    {
        $id = $model->insert($data);

        if ($id === false) {
            throw new RuntimeException(
                'Test fixture rejected by ' . $model::class . ': ' . json_encode($model->errors(), JSON_UNESCAPED_SLASHES)
            );
        }

        return $model->find($id);
    }
}
