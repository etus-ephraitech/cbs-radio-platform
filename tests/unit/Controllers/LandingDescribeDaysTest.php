<?php

namespace Tests\Unit\Controllers;

use App\Controllers\LandingController;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Support\TestCase\UnitTestCase;

/**
 * How the homepage words the days a show runs. The days are stored as a comma-separated list of ISO day
 * numbers (1 = Monday ... 7 = Sunday), or nothing at all for "every day".
 *
 * @internal
 */
final class LandingDescribeDaysTest extends UnitTestCase
{
    #[DataProvider('schedules')]
    public function testDays(?string $stored, string $expected): void
    {
        $method = new ReflectionMethod(LandingController::class, 'describeDays');

        $this->assertSame($expected, $method->invoke(new LandingController(), $stored));
    }

    /**
     * @return array<string, array{0: string|null, 1: string}>
     */
    public static function schedules(): array
    {
        return [
            'nothing stored means every day' => [null, 'Every day'],
            'an empty string too'            => ['', 'Every day'],
            'all seven'                      => ['1,2,3,4,5,6,7', 'Every day'],
            'all seven, shuffled'            => ['7,3,1,5,2,6,4', 'Every day'],
            'weekdays'                       => ['1,2,3,4,5', 'Weekdays'],
            'weekends'                       => ['6,7', 'Weekends'],
            'a run of three'                 => ['1,2,3', 'Mon to Wed'],
            'a run of six'                   => ['1,2,3,4,5,6', 'Mon to Sat'],
            'a run of two reads as a pair'   => ['1,2', 'Mon, Tue'],
            'separate days'                  => ['1,3,5', 'Mon, Wed, Fri'],
            'a run and a run'                => ['1,2,4,5', 'Mon, Tue, Thu, Fri'],
            'a run and a single day'         => ['1,2,3,6', 'Mon to Wed, Sat'],
            'one day'                        => ['7', 'Sun'],
            'unsorted input is sorted'       => ['3,1,2', 'Mon to Wed'],
            'duplicates are ignored'         => ['1,1,2,2', 'Mon, Tue'],
        ];
    }
}
