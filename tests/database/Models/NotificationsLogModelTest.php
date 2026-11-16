<?php

namespace Tests\Database\Models;

use App\Models\NotificationsLogModel;
use Tests\Support\TestCase\AppTestCase;

/**
 * The record of every text and email the system tried to send.
 *
 * @internal
 */
final class NotificationsLogModelTest extends AppTestCase
{
    private NotificationsLogModel $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->log = new NotificationsLogModel();
    }

    public function testASentMessageIsLoggedWithTheTimeItWentOut(): void
    {
        $booking = $this->makeBooking();

        $id = $this->log->logSent((int) $booking['id'], 'sms', '0772123456', 'Thanks Grace');

        $row = $this->log->find($id);
        $this->assertSame('sent', $row['status']);
        $this->assertSame('sms', $row['channel']);
        $this->assertSame('0772123456', $row['recipient']);
        $this->assertSame('Thanks Grace', $row['message']);
        $this->assertEqualsWithDelta(time(), strtotime($row['sent_at']), 5);
    }

    public function testAFailedMessageIsLoggedWithNoSendTime(): void
    {
        $id = $this->log->logFailed(null, 'email', 'grace@example.test', "Subject\n\nBody");

        $row = $this->log->find($id);
        $this->assertSame('failed', $row['status']);
        $this->assertNull($row['sent_at']);
        $this->assertNull($row['booking_id'], 'A test message belongs to no booking.');
    }

    public function testAnUnknownChannelIsNotLogged(): void
    {
        $this->assertFalse($this->log->logSent(null, 'pigeon', 'someone', 'coo'));
        $this->assertSame(0, $this->db->table('notifications_log')->countAllResults());
    }

    public function testABookingsMessagesAreNewestFirst(): void
    {
        $booking = $this->makeBooking();
        $other   = $this->makeBooking();

        $first  = $this->log->logSent((int) $booking['id'], 'sms', '1', 'first');
        $second = $this->log->logFailed((int) $booking['id'], 'sms', '1', 'second');
        $this->log->logSent((int) $other['id'], 'sms', '2', 'not ours');

        $this->assertSame([$second, $first], array_map('intval', array_column($this->log->forBooking((int) $booking['id']), 'id')));
    }

    public function testTheLogCanBeFilteredByStatusChannelAndBooking(): void
    {
        $booking = $this->makeBooking();

        $a = $this->log->logSent((int) $booking['id'], 'sms', '1', 'a');
        $b = $this->log->logFailed((int) $booking['id'], 'sms', '1', 'b');
        $c = $this->log->logSent((int) $booking['id'], 'email', 'x@y.test', 'c');
        $d = $this->log->logSent(null, 'sms', '9', 'd');

        $ids = fn (array $filters): array => array_map('intval', array_column($this->log->filtered($filters, 50, 0), 'id'));

        $this->assertEqualsCanonicalizing([$a, $c, $d], $ids(['status' => 'sent']));
        $this->assertSame([$b], $ids(['status' => 'failed']));
        $this->assertEqualsCanonicalizing([$a, $b, $d], $ids(['channel' => 'sms']));
        $this->assertEqualsCanonicalizing([$a, $b, $c], $ids(['booking_id' => $booking['id']]));
        $this->assertSame([$b], $ids(['status' => 'failed', 'channel' => 'sms', 'booking_id' => $booking['id']]));
    }

    public function testTheLogIsPagedNewestFirstAndCountedWithTheSameFilters(): void
    {
        $ids = [];

        for ($i = 1; $i <= 5; $i++) {
            $ids[] = (int) $this->log->logSent(null, 'sms', (string) $i, "m{$i}");
        }

        $this->assertSame([$ids[4], $ids[3]], array_map('intval', array_column($this->log->filtered([], 2, 0), 'id')));
        $this->assertSame([$ids[0]], array_map('intval', array_column($this->log->filtered([], 2, 4), 'id')));
        $this->assertSame(5, $this->log->countFiltered([]));
        $this->assertSame(0, $this->log->countFiltered(['status' => 'failed']));
    }
}
