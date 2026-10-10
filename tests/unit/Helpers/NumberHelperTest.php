<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase\UnitTestCase;

/**
 * @internal
 */
final class NumberHelperTest extends UnitTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        helper('number');
    }

    public function testMoneyIsShownInWholeUnitsWithThousandsSeparators(): void
    {
        $this->assertSame('UGX 15,000', cbs_money(15000));
        $this->assertSame('UGX 1,234,568', cbs_money(1234567.89), 'Fractions are rounded, not cut off.');
        $this->assertSame('UGX 0', cbs_money(0));
    }

    public function testMoneyUsesTheCurrencyItIsGiven(): void
    {
        $this->assertSame('USD 20', cbs_money(20, 'USD'));
    }

    public function testDecimalMoneyAlwaysShowsTwoPlaces(): void
    {
        $this->assertSame('UGX 15,000.00', cbs_money_decimal(15000));
        $this->assertSame('UGX 0.50', cbs_money_decimal(0.5));
    }

    #[DataProvider('percentages')]
    public function testPercentage(int|float $value, int|float $total, int $decimals, string $expected): void
    {
        $this->assertSame($expected, cbs_percentage($value, $total, $decimals));
    }

    /**
     * @return array<string, array{0: int|float, 1: int|float, 2: int, 3: string}>
     */
    public static function percentages(): array
    {
        return [
            'a quarter'                       => [1, 4, 0, '25%'],
            'with decimals'                   => [1, 3, 1, '33.3%'],
            'rounds to the places asked for'  => [2, 3, 0, '67%'],
            'more than the whole'             => [5, 4, 0, '125%'],
            'nothing of something'            => [0, 10, 0, '0%'],
            'a zero total is not a division'  => [3, 0, 0, '0%'],
            'a negative total is not either'  => [3, -5, 0, '0%'],
        ];
    }

    #[DataProvider('durations')]
    public function testDuration(?int $seconds, string $expected): void
    {
        $this->assertSame($expected, cbs_duration($seconds));
    }

    /**
     * @return array<string, array{0: int|null, 1: string}>
     */
    public static function durations(): array
    {
        return [
            'unknown'          => [null, '—'],
            'zero'             => [0, '0:00'],
            'seconds only'     => [7, '0:07'],
            'pads the seconds' => [65, '1:05'],
            'a full minute'    => [60, '1:00'],
            'ten minutes'      => [600, '10:00'],
        ];
    }

    public function testRemainingSlotsNeverGoBelowZero(): void
    {
        $this->assertSame(15, cbs_slot_remaining(20, 5));
        $this->assertSame(0, cbs_slot_remaining(20, 20));
        $this->assertSame(0, cbs_slot_remaining(5, 9), 'An overbooked slot reports none left, not a negative number.');
    }
}
