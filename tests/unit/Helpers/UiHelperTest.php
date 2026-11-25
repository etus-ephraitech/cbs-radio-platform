<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase\UnitTestCase;

/**
 * @internal
 */
final class UiHelperTest extends UnitTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        helper('ui');
    }

    #[DataProvider('knownBadges')]
    public function testKnownStatusesHaveALabelAndAColour(string $function, string $status, string $label, string $class): void
    {
        $html = $function($status);

        $this->assertStringContainsString(">{$label}</span>", $html);
        $this->assertStringContainsString($class, $html);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function knownBadges(): array
    {
        return [
            'booking: pending payment'    => ['cbs_booking_status_badge', 'pending_payment', 'Pending Payment', 'bg-secondary'],
            'booking: pending moderation' => ['cbs_booking_status_badge', 'pending_moderation', 'Pending Moderation', 'bg-warning'],
            'booking: approved'           => ['cbs_booking_status_badge', 'approved', 'Approved', 'bg-info'],
            'booking: rejected'           => ['cbs_booking_status_badge', 'rejected', 'Rejected', 'bg-danger'],
            'booking: aired'              => ['cbs_booking_status_badge', 'aired', 'Aired', 'bg-success'],
            'booking: cancelled'          => ['cbs_booking_status_badge', 'cancelled', 'Cancelled', 'bg-dark'],
            'payment: success'            => ['cbs_payment_status_badge', 'success', 'Success', 'bg-success'],
            'payment: failed'             => ['cbs_payment_status_badge', 'failed', 'Failed', 'bg-danger'],
            'gift order: paid'            => ['cbs_gift_order_status_badge', 'paid', 'Paid', 'bg-info'],
            'gift order: fulfilled'       => ['cbs_gift_order_status_badge', 'fulfilled', 'Fulfilled', 'bg-success'],
            'notification: sent'          => ['cbs_notification_status_badge', 'sent', 'Sent', 'bg-success'],
            'notification: failed'        => ['cbs_notification_status_badge', 'failed', 'Failed', 'bg-danger'],
        ];
    }

    public function testAnUnknownStatusIsStillShownNeutrally(): void
    {
        $this->assertSame('<span class="badge bg-secondary">Brand_new</span>', cbs_booking_status_badge('brand_new'));
    }

    #[DataProvider('badgeFunctions')]
    public function testAStatusIsNeverAbleToInjectMarkup(string $function): void
    {
        $html = $function('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function badgeFunctions(): array
    {
        return [
            'booking'      => ['cbs_booking_status_badge'],
            'payment'      => ['cbs_payment_status_badge'],
            'gift order'   => ['cbs_gift_order_status_badge'],
            'notification' => ['cbs_notification_status_badge'],
        ];
    }

    #[DataProvider('activeValues')]
    public function testActiveBadgeAcceptsWhatTheDatabaseReturns(int|string|bool $value, string $label): void
    {
        $this->assertStringContainsString(">{$label}</span>", cbs_active_badge($value));
    }

    /**
     * @return array<string, array{0: int|string|bool, 1: string}>
     */
    public static function activeValues(): array
    {
        return [
            'int 1'        => [1, 'Active'],
            'string "1"'   => ['1', 'Active'],
            'true'         => [true, 'Active'],
            'int 0'        => [0, 'Inactive'],
            'string "0"'   => ['0', 'Inactive'],
            'false'        => [false, 'Inactive'],
        ];
    }

    #[DataProvider('ages')]
    public function testTimeAgo(int $secondsAgo, string $expected): void
    {
        $this->assertSame($expected, cbs_time_ago(date('Y-m-d H:i:s', time() - $secondsAgo)));
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function ages(): array
    {
        return [
            'this instant'    => [0, 'just now'],
            'under a minute'  => [45, 'just now'],
            'one minute'      => [60, '1 minute ago'],
            'five minutes'    => [300, '5 minutes ago'],
            'one hour'        => [3600, '1 hour ago'],
            'two days'        => [2 * 86400 + 30, '2 days ago'],
            'a week'          => [7 * 86400, '1 week ago'],
        ];
    }

    public function testTimeAgoLeavesAnUnreadableDateAlone(): void
    {
        $this->assertSame('not a date', cbs_time_ago('not a date'));
    }
}
