<?php

namespace Tests\Database\Models;

use App\Models\ShowModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase\AppTestCase;

/**
 * @internal
 */
final class ShowModelTest extends AppTestCase
{
    public function testActiveShowsComeInOrderOfStartTime(): void
    {
        $this->makeShow(['name' => 'Evening Drive', 'start_time' => '16:00:00', 'end_time' => '19:00:00']);
        $this->makeShow(['name' => 'Morning Express', 'start_time' => '06:00:00', 'end_time' => '10:00:00']);
        $this->makeShow(['name' => 'Midday', 'start_time' => '12:00:00', 'end_time' => '14:00:00']);

        $this->assertSame(
            ['Morning Express', 'Midday', 'Evening Drive'],
            array_column((new ShowModel())->active(), 'name')
        );
    }

    public function testAnInactiveShowIsLeftOut(): void
    {
        $this->makeShow(['name' => 'Live']);
        $this->makeShow(['name' => 'Retired', 'is_active' => 0]);

        $this->assertSame(['Live'], array_column((new ShowModel())->active(), 'name'));
    }

    #[DataProvider('days')]
    public function testWhichDaysAShowRuns(?string $stored, int $isoDay, bool $runs): void
    {
        $this->assertSame($runs, (new ShowModel())->runsOn(['days_of_week' => $stored], $isoDay));
    }

    /**
     * @return array<string, array{0: string|null, 1: int, 2: bool}>
     */
    public static function days(): array
    {
        return [
            'nothing stored: every day (Monday)'  => [null, 1, true],
            'nothing stored: every day (Sunday)'  => [null, 7, true],
            'an empty string is every day too'    => ['', 3, true],
            'a listed day'                        => ['1,3,5', 3, true],
            'the first listed day'                => ['1,3,5', 1, true],
            'a day not listed'                    => ['1,3,5', 2, false],
            'Sunday is 7'                         => ['6,7', 7, true],
            'Monday is not in a weekend'          => ['6,7', 1, false],
            'a single day'                        => ['4', 4, true],
            'a single day, another day'           => ['4', 5, false],
        ];
    }

    public function testAShowNeedsANameAndTimes(): void
    {
        $model = new ShowModel();

        $this->assertFalse($model->insert(['name' => '', 'start_time' => '06:00:00', 'end_time' => '10:00:00']));
        $this->assertArrayHasKey('name', $model->errors());

        $this->assertFalse($model->insert(['name' => 'No times']));
        $this->assertArrayHasKey('start_time', $model->errors());
        $this->assertArrayHasKey('end_time', $model->errors());
    }
}
