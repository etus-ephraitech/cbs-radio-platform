<?php

namespace Tests\Feature;

use App\Models\BookingModel;
use App\Models\GiftOrderModel;
use App\Models\ModerationLogModel;
use App\Models\NotificationsLogModel;
use App\Models\PaymentModel;
use App\Models\ShowSlotModel;
use Tests\Support\Concerns\SetsEnvironment;
use Tests\Support\Libraries\FakeHttpGateway;
use Tests\Support\Libraries\StaffUser;
use Tests\Support\TestCase\AppFeatureTestCase;

/**
 * What staff do all day: find a booking, review a greeting, confirm a payment, send a gift, re-send a failed text.
 * Everything runs signed in as a real staff user (see Tests\Support\Filters\ActAsStaff).
 *
 * @internal
 */
final class AdminOperationsTest extends AppFeatureTestCase
{
    use SetsEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->silenceNotifications();
    }

    private function visible(string $html): string
    {
        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    // ================================================================== the booking list

    public function testTheListShowsEachBookingWithItsReferenceNamesAndStatus(): void
    {
        $booking = $this->makeBooking(['sender_name' => 'Grace Nakato', 'recipient_name' => 'Mama Ruth', 'status' => BookingModel::STATUS_APPROVED]);

        $visible = $this->visible($this->get('admin/bookings')->getBody());

        foreach ([cbs_booking_reference((int) $booking['id']), 'Grace Nakato', 'Mama Ruth', 'Approved', 'UGX 15,000'] as $text) {
            $this->assertStringContainsString($text, $visible);
        }
    }

    public function testAnEmptyListSaysSo(): void
    {
        $this->assertStringContainsString('No bookings match these filters.', $this->visible($this->get('admin/bookings')->getBody()));
    }

    public function testTheSearchBoxFindsByNameReferenceAndMessage(): void
    {
        $a = $this->makeBooking(['sender_name' => 'Nakato Grace', 'message_text' => 'Happy birthday']);
        $this->makeBooking(['sender_name' => 'Okello Peter', 'message_text' => 'Congratulations on the baby']);

        $names = fn (string $query): string => $this->visible($this->get('admin/bookings?q=' . urlencode($query))->getBody());

        $this->assertStringContainsString('Nakato Grace', $names('nakato'));
        $this->assertStringNotContainsString('Okello Peter', $names('nakato'));
        $this->assertStringContainsString('Okello Peter', $names('the baby'));
        $this->assertStringContainsString('Nakato Grace', $names(cbs_booking_reference((int) $a['id'])));
    }

    public function testFiltersNarrowTheList(): void
    {
        $birthdays = $this->makeCategory();
        $weddings  = $this->makeCategory();
        $this->makeBooking(['category_id' => $birthdays['id'], 'status' => BookingModel::STATUS_APPROVED, 'recipient_name' => 'Approved Birthday']);
        $this->makeBooking(['category_id' => $birthdays['id'], 'status' => BookingModel::STATUS_REJECTED, 'recipient_name' => 'Rejected Birthday']);
        $this->makeBooking(['category_id' => $weddings['id'], 'status' => BookingModel::STATUS_APPROVED, 'recipient_name' => 'Approved Wedding']);

        $visible = $this->visible($this->get('admin/bookings?status=approved&category_id=' . $birthdays['id'])->getBody());

        $this->assertStringContainsString('Approved Birthday', $visible);
        $this->assertStringNotContainsString('Rejected Birthday', $visible);
        $this->assertStringNotContainsString('Approved Wedding', $visible);
    }

    public function testNonsenseFiltersAreIgnoredRatherThanBreakingThePage(): void
    {
        $this->makeBooking(['recipient_name' => 'Still Here']);

        foreach (['status=teleported', 'category_id=abc', 'date_from=yesterday-ish', 'date_to=2026-02-30', 'page=banana', 'q=' . str_repeat('x', 500)] as $query) {
            $result = $this->get('admin/bookings?' . $query);

            $result->assertOK();
        }

        $this->assertStringContainsString('Still Here', $this->visible($this->get('admin/bookings?status=teleported&date_from=nope')->getBody()));
    }

    public function testTheListIsPagedAndAPageNumberOutOfRangeIsClamped(): void
    {
        config('CbsRadio')->adminPageSize = 3;

        foreach (range(1, 7) as $i) {
            $this->makeBooking(['recipient_name' => "Person{$i}x"]);
        }

        $page = fn (string $query): string => $this->visible($this->get('admin/bookings' . $query)->getBody());

        $first = $page('');
        $this->assertStringContainsString('Person7x', $first, 'Newest first.');
        $this->assertStringContainsString('Person5x', $first);
        $this->assertStringNotContainsString('Person4x', $first);

        $this->assertStringContainsString('Person4x', $page('?page=2'));
        $this->assertStringContainsString('Person1x', $page('?page=3'));
        $this->assertStringContainsString('Person1x', $page('?page=99'), 'Past the end shows the last page, not an empty one.');
        $this->assertStringContainsString('Person7x', $page('?page=0'), 'Zero and negative show the first page.');
        $this->assertStringContainsString('Person7x', $page('?page=-5'));
    }

    // ================================================================== one booking

    public function testABookingsPageGathersItsWholeHistory(): void
    {
        $booking = $this->makeBooking(['message_text' => 'Happy birthday dear friend', 'status' => BookingModel::STATUS_PENDING_MODERATION]);
        $this->makePayment(['booking_id' => $booking['id']]);
        (new ModerationLogModel())->insert(['booking_id' => $booking['id'], 'moderator_id' => StaffUser::id(), 'action' => 'flagged', 'notes' => 'Check the spelling']);
        (new NotificationsLogModel())->logSent((int) $booking['id'], 'sms', '0772123456', 'Thanks for your booking');

        $visible = $this->visible($this->get('admin/bookings/' . $booking['id'])->getBody());

        foreach (['Happy birthday dear friend', 'manual', 'Check the spelling', 'Thanks for your booking'] as $text) {
            $this->assertStringContainsString($text, $visible);
        }
    }

    public function testWhatAVisitorTypedIsEscapedOnTheStaffPage(): void
    {
        // Stored text is already stripped of markup; this is the second line of defence, in case it ever was not.
        $booking = $this->makeBooking();
        $this->db->table('bookings')->where('id', $booking['id'])->update(['message_text' => '<script>alert(1)</script>', 'recipient_name' => '<img src=x onerror=alert(1)>']);

        $body = $this->get('admin/bookings/' . $booking['id'])->getBody();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringNotContainsString('<img src=x', $body);
    }

    public function testCancellingABookingGivesBackItsPlaceOnTheShow(): void
    {
        $show = $this->makeShow();
        $slot = (new ShowSlotModel())->findOrCreateForDate($show['id'], '2026-12-24');
        (new ShowSlotModel())->incrementBooked($slot['id']);
        $booking = $this->makeBooking(['show_slot_id' => $slot['id']]);

        $this->post('admin/bookings/' . $booking['id'] . '/cancel')->assertRedirect();

        $this->assertSame('Booking cancelled.', $this->flash('toastr_success'));
        $this->assertSame('cancelled', (new BookingModel())->find($booking['id'])['status']);
        $this->assertSame('0', (string) (new ShowSlotModel())->find($slot['id'])['booked_count']);
    }

    public function testAnAiredBookingCannotBeCancelled(): void
    {
        $booking = $this->makeBooking(['status' => BookingModel::STATUS_AIRED]);

        $this->post('admin/bookings/' . $booking['id'] . '/cancel')->assertRedirect();

        $this->assertSame('This booking can no longer be cancelled.', $this->flash('toastr_error'));
        $this->assertSame('aired', (new BookingModel())->find($booking['id'])['status']);
    }

    // ================================================================== moderation

    public function testTheQueueHoldsOnlyGreetingsWaitingForReview(): void
    {
        $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION, 'recipient_name' => 'Waiting One']);
        $this->makeBooking(['status' => BookingModel::STATUS_APPROVED, 'recipient_name' => 'Already Approved']);
        $this->makeBooking(['status' => BookingModel::STATUS_PENDING_PAYMENT, 'recipient_name' => 'Never Paid']);

        $visible = $this->visible($this->get('admin/moderation')->getBody());

        $this->assertStringContainsString('Waiting One', $visible);
        $this->assertStringNotContainsString('Already Approved', $visible);
        $this->assertStringNotContainsString('Never Paid', $visible);
    }

    public function testAnEmptyQueueSaysSo(): void
    {
        $this->assertStringContainsString('Nothing waiting for review right now.', $this->visible($this->get('admin/moderation')->getBody()));
    }

    public function testApprovingRecordsTheSignedInStaffMemberAndTheirNote(): void
    {
        $booking = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);

        $this->post('admin/moderation/' . $booking['id'] . '/approve', ['notes' => 'Lovely message'])->assertRedirect();

        $this->assertSame('Booking approved.', $this->flash('toastr_success'));

        $after = (new BookingModel())->find($booking['id']);
        $this->assertSame('approved', $after['status']);
        $this->assertSame((string) StaffUser::id(), (string) $after['moderated_by'], 'Whoever is signed in is on record, not whoever the form says.');
        $this->assertSame('Lovely message', $after['moderation_notes']);
    }

    public function testTheModeratorCannotBeNamedByTheForm(): void
    {
        $booking = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);

        $this->post('admin/moderation/' . $booking['id'] . '/approve', ['moderator_id' => 99999, 'moderated_by' => 99999]);

        $this->assertSame((string) StaffUser::id(), (string) (new BookingModel())->find($booking['id'])['moderated_by']);
    }

    public function testRejectingAndFlaggingGiveTheirOwnMessages(): void
    {
        $toReject = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);
        $toFlag   = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);

        $this->post('admin/moderation/' . $toReject['id'] . '/reject', ['notes' => 'Not suitable'])->assertRedirect();
        $this->assertSame('Booking rejected.', $this->flash('toastr_success'));

        $this->post('admin/moderation/' . $toFlag['id'] . '/flag', ['notes' => 'Ask the presenter'])->assertRedirect();
        $this->assertSame('Booking flagged for review.', $this->flash('toastr_success'));

        $this->assertSame('rejected', (new BookingModel())->find($toReject['id'])['status']);
        $this->assertSame('pending_moderation', (new BookingModel())->find($toFlag['id'])['status'], 'Flagging leaves the decision open.');
    }

    public function testAnUnpaidBookingCannotBeApprovedForAir(): void
    {
        $booking = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_PAYMENT]);

        $this->post('admin/moderation/' . $booking['id'] . '/approve')->assertRedirect();

        $this->assertSame('Only bookings pending moderation can be approved.', $this->flash('toastr_error'));
        $this->assertSame('pending_payment', (new BookingModel())->find($booking['id'])['status']);
    }

    public function testTheHistoryIsAvailableAsJson(): void
    {
        $booking = $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);
        $this->post('admin/moderation/' . $booking['id'] . '/flag', ['notes' => 'Look again']);
        $this->post('admin/moderation/' . $booking['id'] . '/approve', ['notes' => 'Fine now']);

        $result = $this->get('admin/moderation/' . $booking['id'] . '/history');

        $result->assertOK();
        $history = json_decode($result->getJSON(), true)['history'];
        $actions = array_column($history, 'action');
        sort($actions);

        $this->assertSame(['approved', 'flagged'], $actions);
    }

    // ================================================================== payments

    public function testThePaymentListCanBeFilteredByStatus(): void
    {
        $paid    = $this->makeBooking();
        $waiting = $this->makeBooking();
        $this->makePayment(['booking_id' => $paid['id'], 'status' => 'success']);
        $this->makePayment(['booking_id' => $waiting['id'], 'status' => 'pending']);

        $pending = $this->visible($this->get('admin/payments?status=pending')->getBody());

        $this->assertStringContainsString('#' . $waiting['id'], $pending);
        $this->assertStringNotContainsString('#' . $paid['id'] . ' ', $pending . ' ');
    }

    public function testStaffCanConfirmAPaymentTheyReceivedByHand(): void
    {
        $booking = $this->makeBooking();
        $payment = $this->makePayment(['booking_id' => $booking['id']]);

        $this->post('admin/payments/' . $payment['id'] . '/mark-manual')->assertRedirect();

        $this->assertSame('Payment marked as paid.', $this->flash('toastr_success'));
        $this->assertSame('success', (new PaymentModel())->find($payment['id'])['status']);
        $this->assertSame((string) StaffUser::id(), (string) (new PaymentModel())->find($payment['id'])['marked_paid_by']);
        $this->assertSame('pending_moderation', (new BookingModel())->find($booking['id'])['status']);
    }

    public function testAPaymentAlreadyConfirmedCannotBeConfirmedTwice(): void
    {
        $payment = $this->makePayment();

        $this->post('admin/payments/' . $payment['id'] . '/mark-manual');
        $this->post('admin/payments/' . $payment['id'] . '/mark-manual')->assertRedirect();

        $this->assertSame('Could not mark this payment as paid.', $this->flash('toastr_error'));
    }

    // ================================================================== gift orders

    public function testTheGiftOrderListCanBeFilteredByStatus(): void
    {
        $this->makeGiftOrder(['recipient_name' => 'Pays Later']);
        $this->makeGiftOrder(['recipient_name' => 'Already Paid', 'status' => GiftOrderModel::STATUS_PAID]);

        $visible = $this->visible($this->get('admin/gift-orders?status=paid')->getBody());

        $this->assertStringContainsString('Already Paid', $visible);
        $this->assertStringNotContainsString('Pays Later', $visible);
    }

    public function testAGiftOfOurOwnIsMarkedFulfilledByHand(): void
    {
        $order = $this->makeGiftOrder(['status' => 'paid']);

        $this->post('admin/gift-orders/' . $order['id'] . '/fulfill')->assertRedirect();

        $this->assertSame('Gift order marked fulfilled.', $this->flash('toastr_success'));
        $this->assertSame('fulfilled', (new GiftOrderModel())->find($order['id'])['status']);
    }

    public function testAnUnpaidGiftCannotBeFulfilled(): void
    {
        $order = $this->makeGiftOrder();

        $this->post('admin/gift-orders/' . $order['id'] . '/fulfill')->assertRedirect();

        $this->assertSame('This gift order is not ready to be fulfilled.', $this->flash('toastr_error'));
    }

    public function testAVendorGiftIsOrderedFromTheVendorOverTheirApi(): void
    {
        $vendor = $this->makeVendor(['slug' => 'acme', 'api_base_url' => 'https://vendor.example.test/api']);
        $gift   = $this->makeGift(['type' => 'physical', 'source' => 'vendor', 'vendor_id' => $vendor['id'], 'vendor_product_ref' => 'SKU-9']);
        $order  = $this->makeGiftOrder(['gift_id' => $gift['id'], 'status' => 'paid', 'quantity' => 2]);
        $this->setEnv('vendor.acme.apiKey', 'secret');
        $http = FakeHttpGateway::install(201, json_encode(['order_id' => 'ORD-77']));

        $this->post('admin/gift-orders/' . $order['id'] . '/fulfill')->assertRedirect();

        $this->assertSame('Order placed with vendor and marked fulfilled.', $this->flash('toastr_success'));
        $this->assertSame('ORD-77', (new GiftOrderModel())->find($order['id'])['vendor_order_ref']);
        $this->assertSame('https://vendor.example.test/api/orders', $http->calls[0]['url']);
        $this->assertSame('SKU-9', $http->lastPayload()['product_id']);
    }

    public function testAVendorWhoDeclinesLeavesTheOrderPaidForAnotherTry(): void
    {
        $vendor = $this->makeVendor(['slug' => 'acme']);
        $gift   = $this->makeGift(['type' => 'physical', 'source' => 'vendor', 'vendor_id' => $vendor['id'], 'vendor_product_ref' => 'SKU-9']);
        $order  = $this->makeGiftOrder(['gift_id' => $gift['id'], 'status' => 'paid']);
        $this->setEnv('vendor.acme.apiKey', 'secret');
        FakeHttpGateway::install(422, '{"error":"out of stock"}');

        $this->post('admin/gift-orders/' . $order['id'] . '/fulfill')->assertRedirect();

        $this->assertSame('Vendor declined the order.', $this->flash('toastr_error'));
        $this->assertSame('paid', (new GiftOrderModel())->find($order['id'])['status']);
    }

    public function testAnOrderCanBeCancelledUntilItIsDelivered(): void
    {
        $open      = $this->makeGiftOrder(['status' => 'paid']);
        $delivered = $this->makeGiftOrder(['status' => 'fulfilled']);

        $this->post('admin/gift-orders/' . $open['id'] . '/cancel');
        $this->assertSame('Gift order cancelled.', $this->flash('toastr_success'));

        $this->post('admin/gift-orders/' . $delivered['id'] . '/cancel');
        $this->assertSame('Could not cancel this gift order.', $this->flash('toastr_error'));
    }

    // ================================================================== the notification log

    public function testTheLogShowsEveryMessageAndCanBeFiltered(): void
    {
        $log = new NotificationsLogModel();
        $log->logSent(null, 'sms', '0772000001', 'sms that went out');
        $log->logFailed(null, 'sms', '0772000002', 'sms that failed');
        $log->logSent(null, 'email', 'a@b.test', "Subject line\n\nemail that went out");

        $all = $this->visible($this->get('admin/notifications')->getBody());
        $this->assertStringContainsString('sms that went out', $all);
        $this->assertStringContainsString('sms that failed', $all);

        $failed = $this->visible($this->get('admin/notifications?status=failed')->getBody());
        $this->assertStringContainsString('sms that failed', $failed);
        $this->assertStringNotContainsString('sms that went out', $failed);

        $email = $this->visible($this->get('admin/notifications?channel=email')->getBody());
        $this->assertStringContainsString('email that went out', $email);
        $this->assertStringNotContainsString('sms that failed', $email);
    }

    public function testNonsenseLogFiltersAreIgnored(): void
    {
        (new NotificationsLogModel())->logSent(null, 'sms', '1', 'still visible');

        $visible = $this->visible($this->get('admin/notifications?status=lost&channel=pigeon&booking_id=abc&page=zzz')->getBody());

        $this->assertStringContainsString('still visible', $visible);
    }

    public function testAFailedTextCanBeSentAgain(): void
    {
        $this->setEnv('sms.apiKey', 'key');
        config('Notifications')->channelsEnabled = ['sms' => true, 'email' => false];
        $failedId = (new NotificationsLogModel())->logFailed(null, 'sms', '0772123456', 'Thanks for booking');
        FakeHttpGateway::install(200);

        $this->post('admin/notifications/' . $failedId . '/resend')->assertRedirect();

        $this->assertSame('Notification re-sent.', $this->flash('toastr_success'));
    }

    public function testAResendThatFailsAgainSaysSo(): void
    {
        $this->setEnv('sms.apiKey', 'key');
        $failedId = (new NotificationsLogModel())->logFailed(null, 'sms', '0772123456', 'Thanks for booking');
        FakeHttpGateway::install(500);

        $this->post('admin/notifications/' . $failedId . '/resend')->assertRedirect();

        $this->assertSame('Re-send failed again — check the gateway settings in .env.', $this->flash('toastr_error'));
    }

    public function testOnlyAFailedMessageCanBeResentAndAnUnknownOneIsReported(): void
    {
        $sentId = (new NotificationsLogModel())->logSent(null, 'sms', '0772123456', 'Already delivered');
        $http   = FakeHttpGateway::install();

        $this->post('admin/notifications/' . $sentId . '/resend');
        $this->assertSame('Only failed notifications can be re-sent.', $this->flash('toastr_error'));

        $this->post('admin/notifications/999999/resend');
        $this->assertSame('Notification not found.', $this->flash('toastr_error'));

        $this->assertSame([], $http->calls, 'A message that was delivered must never be sent a second time.');
    }

    // ================================================================== the dashboard

    public function testTheDashboardShowsWhatNeedsAttention(): void
    {
        $visible = $this->visible($this->get('admin')->getBody());

        foreach (['Needs attention', 'Awaiting moderation', 'Pending payments', 'Gift orders to fulfill', 'Failed notifications (24h)', 'Configuration', 'Catalog health'] as $text) {
            $this->assertStringContainsString($text, $visible);
        }
    }

    public function testTheDashboardCountsEachQueue(): void
    {
        $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);
        $this->makeBooking(['status' => BookingModel::STATUS_PENDING_MODERATION]);
        $this->makePayment(['booking_id' => $this->makeBooking()['id'], 'status' => 'pending']);
        $this->makeGiftOrder(['status' => 'paid']);
        (new NotificationsLogModel())->logFailed(null, 'sms', '1', 'failed just now');

        $visible = $this->visible($this->get('admin')->getBody());

        $count = static function (string $label) use ($visible): ?int {
            return preg_match('/(\d+)\s*' . preg_quote($label, '/') . '|' . preg_quote($label, '/') . '\s*(\d+)/', $visible, $m)
                ? (int) ($m[1] !== '' ? $m[1] : $m[2])
                : null;
        };

        $this->assertSame(2, $count('Awaiting moderation'));
        $this->assertSame(1, $count('Pending payments'));
        $this->assertSame(1, $count('Gift orders to fulfill'));
        $this->assertSame(1, $count('Failed notifications (24h)'));
    }

    public function testTheDashboardReportsHowPaymentsAreSetUp(): void
    {
        config('Payments')->mode = 'manual';

        $this->assertStringContainsString('Manual — staff confirm each payment', $this->visible($this->get('admin')->getBody()));
    }

    public function testTheDashboardNotesWhenGiftsWereLastSyncedFromVendors(): void
    {
        $vendor = $this->makeVendor();
        $this->makeGift(['type' => 'physical', 'source' => 'vendor', 'vendor_id' => $vendor['id']]);
        $this->db->table('gifts')->update(['synced_at' => '2026-03-05 10:30:00']);

        $this->assertStringContainsString('2026-03-05', $this->get('admin')->getBody());
    }
}
