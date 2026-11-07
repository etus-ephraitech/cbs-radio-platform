<?php

namespace App\Services;

use App\Models\GiftModel;
use App\Models\RateTierModel;

/**
 * One checkout for a greeting and the gifts chosen with it in the same form.
 *
 * The visitor picks an occasion, a package, writes the message, adds gifts if they like, and pays once. This
 * creates the booking (BookingService) and one gift order per chosen gift (GiftOrderService), each marked
 * bundled_with_booking so that PaymentService adds it to the greeting's payment and settles it with that payment.
 *
 * Every gift is checked before anything is written, so a gift that is unavailable, in another currency, ordered in
 * an impossible quantity or missing its delivery address stops the checkout with nothing saved and a message the
 * visitor can act on.
 */
class GreetingCheckoutService
{
    /** Most of one gift in a single greeting: enough for a family, few enough that a typo can't order 900. */
    public const MAX_QUANTITY = 10;

    /** Most different gifts in a single greeting. */
    public const MAX_GIFTS = 5;

    protected BookingService $bookingService;
    protected GiftOrderService $giftOrderService;
    protected GiftModel $giftModel;
    protected RateTierModel $rateTierModel;

    public function __construct(
        ?BookingService $bookingService = null,
        ?GiftOrderService $giftOrderService = null,
        ?GiftModel $giftModel = null,
        ?RateTierModel $rateTierModel = null
    ) {
        $this->bookingService   = $bookingService ?? new BookingService();
        $this->giftOrderService = $giftOrderService ?? new GiftOrderService();
        $this->giftModel        = $giftModel ?? new GiftModel();
        $this->rateTierModel    = $rateTierModel ?? new RateTierModel();
    }

    /**
     * Creates the greeting and its gifts.
     *
     * @param array<string, mixed>       $bookingInput what BookingService::createBooking() expects
     * @param array<int|string, mixed>   $giftQuantities gift id => quantity, as posted (gifts[<id>]=<qty>); a quantity
     *                                                 of 0 or empty means "not chosen"
     * @param array{recipient_phone?: ?string, delivery_details?: ?string} $delivery shared by every gift chosen
     *
     * @return array{success: bool, message: string, booking: ?array, giftOrders: list<array>}
     */
    public function submit(array $bookingInput, array $giftQuantities, array $delivery = []): array
    {
        $chosen = $this->chosenGifts($bookingInput, $giftQuantities, $delivery);

        if (! $chosen['success']) {
            return $this->failure($chosen['message']);
        }

        $result = $this->bookingService->createBooking($bookingInput);

        if (! $result['success']) {
            return $this->failure($result['message']);
        }

        $booking = $result['booking'];
        $orders  = [];

        foreach ($chosen['lines'] as $line) {
            $order = $this->giftOrderService->createOrder([
                'gift_id'          => $line['gift']['id'],
                'sender_name'      => $booking['sender_name'],
                'sender_phone'     => $booking['sender_phone'],
                'recipient_name'   => $booking['recipient_name'],
                'recipient_phone'  => $this->clean($delivery['recipient_phone'] ?? null),
                'delivery_details' => $this->clean($delivery['delivery_details'] ?? null),
                'quantity'         => $line['quantity'],
            ], (int) $booking['id'], true);

            if (! $order['success']) {
                // Everything was checked above, so this is the database refusing a write. Undo what was made rather
                // than leave a greeting without the gift the visitor paid attention to choosing.
                foreach ($orders as $made) {
                    $this->giftOrderService->cancel((int) $made['id']);
                }
                $this->bookingService->cancelBooking((int) $booking['id']);

                return $this->failure("We couldn't add your gift just now, so nothing was booked. Please try again.");
            }

            $orders[] = $order['giftOrder'];
        }

        return ['success' => true, 'message' => 'Greeting created.', 'booking' => $booking, 'giftOrders' => $orders];
    }

    /**
     * Checks the chosen gifts against the catalog and the package, without writing anything.
     *
     * @param array<string, mixed>     $bookingInput
     * @param array<int|string, mixed> $giftQuantities
     * @param array<string, mixed>     $delivery
     *
     * @return array{success: bool, message: string, lines: list<array{gift: array, quantity: int}>}
     */
    public function chosenGifts(array $bookingInput, array $giftQuantities, array $delivery = []): array
    {
        $wanted = [];

        foreach ($giftQuantities as $giftId => $quantity) {
            if (is_array($quantity) || is_object($quantity)) {
                return $this->rejected('One of the chosen gifts could not be read. Please choose it again.');
            }

            if ($quantity === null || $quantity === '' || (string) $quantity === '0') {
                continue; // offered but not chosen
            }

            if (! ctype_digit((string) $giftId) || (int) $giftId < 1 || ! ctype_digit((string) $quantity)) {
                return $this->rejected('One of the chosen gifts could not be read. Please choose it again.');
            }

            if ((int) $quantity > self::MAX_QUANTITY) {
                return $this->rejected('You can send up to ' . self::MAX_QUANTITY . ' of any one gift.');
            }

            $wanted[(int) $giftId] = (int) $quantity;
        }

        if ($wanted === []) {
            return ['success' => true, 'message' => '', 'lines' => []];
        }

        if (count($wanted) > self::MAX_GIFTS) {
            return $this->rejected('You can add up to ' . self::MAX_GIFTS . ' different gifts to one greeting.');
        }

        $tier     = empty($bookingInput['rate_tier_id']) ? null : $this->rateTierModel->find($bookingInput['rate_tier_id']);
        $currency = $tier['currency'] ?? null;

        $lines         = [];
        $needsDelivery   = false;

        foreach ($wanted as $giftId => $quantity) {
            $gift = $this->giftModel->find($giftId);

            if ($gift === null || (int) $gift['is_active'] !== 1) {
                return $this->rejected('A gift you chose is no longer available. Please choose another.');
            }

            if ($currency !== null && $gift['currency'] !== $currency) {
                return $this->rejected($gift['name'] . ' is priced in ' . $gift['currency'] . ' and can\'t be paid together with this package.');
            }

            $needsDelivery = $needsDelivery || $gift['type'] === 'physical';
            $lines[]       = ['gift' => $gift, 'quantity' => $quantity];
        }

        if ($needsDelivery && $this->clean($delivery['delivery_details'] ?? null) === null) {
            return $this->rejected('Add a delivery address for the gift that will be delivered.');
        }

        return ['success' => true, 'message' => '', 'lines' => $lines];
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{success: false, message: string, lines: list<array>}
     */
    private function rejected(string $message): array
    {
        return ['success' => false, 'message' => $message, 'lines' => []];
    }

    /**
     * @return array{success: false, message: string, booking: null, giftOrders: list<array>}
     */
    private function failure(string $message): array
    {
        return ['success' => false, 'message' => $message, 'booking' => null, 'giftOrders' => []];
    }
}
