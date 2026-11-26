<?php

namespace Tests\Unit\Libraries;

use App\Libraries\AdminFilters;
use CodeIgniter\HTTP\IncomingRequest;
use Config\Services;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase\UnitTestCase;

/**
 * What an admin list accepts from its query string: choices must be on the list, dates must exist, text is capped,
 * and anything else is dropped rather than reaching the database.
 *
 * @internal
 */
final class AdminFiltersTest extends UnitTestCase
{
    private function request(array $get): IncomingRequest
    {
        $request = Services::incomingrequest(null, false);
        $request->setGlobal('get', $get);

        return $request;
    }

    public function testAChoiceOnTheListIsKept(): void
    {
        $out = AdminFilters::read($this->request(['status' => 'paid']), ['status' => ['pending', 'paid']]);

        $this->assertSame(['status' => 'paid'], $out);
    }

    public function testAChoiceNotOnTheListIsDropped(): void
    {
        $out = AdminFilters::read($this->request(['status' => "'; DROP TABLE x"]), ['status' => ['pending', 'paid']]);

        $this->assertSame(['status' => ''], $out);
    }

    public function testAnAbsentOrNonTextValueIsEmpty(): void
    {
        $out = AdminFilters::read($this->request(['q' => ['a', 'b']]), ['q' => 'search', 'status' => ['paid']]);

        $this->assertSame(['q' => '', 'status' => ''], $out);
    }

    public function testSearchTextIsTrimmedAndCapped(): void
    {
        $out = AdminFilters::read($this->request(['q' => '  ' . str_repeat('é', 300) . '  ']), ['q' => 'search']);

        $this->assertSame(AdminFilters::SEARCH_MAX, mb_strlen($out['q']));
        $this->assertSame('Ruth', AdminFilters::read($this->request(['q' => ' Ruth ']), ['q' => 'search'])['q']);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function dates(): array
    {
        return [
            'a real date'         => ['2026-11-27', true],
            'a leap day'          => ['2028-02-29', true],
            'not a leap year'     => ['2026-02-29', false],
            'month thirteen'      => ['2026-13-01', false],
            'wrong order'         => ['27-11-2026', false],
            'with a time'         => ['2026-11-27 10:00', false],
            'words'               => ['yesterday', false],
            'empty'               => ['', false],
        ];
    }

    #[DataProvider('dates')]
    public function testDatesMustExist(string $value, bool $valid): void
    {
        $this->assertSame($valid, AdminFilters::validDate($value));
        $this->assertSame($valid ? $value : '', AdminFilters::read($this->request(['from' => $value]), ['from' => 'date'])['from']);
    }

    public function testAReversedRangeIsPutTheRightWayRound(): void
    {
        $out = AdminFilters::orderedRange(['from' => '2026-12-31', 'to' => '2026-01-01']);

        $this->assertSame(['from' => '2026-01-01', 'to' => '2026-12-31'], $out);
    }

    public function testARangeWithOneEndIsLeftAlone(): void
    {
        $this->assertSame(['from' => '2026-12-31', 'to' => ''], AdminFilters::orderedRange(['from' => '2026-12-31', 'to' => '']));
    }

    public function testThePageStaysInsideTheList(): void
    {
        $this->assertSame([1, 1], AdminFilters::page($this->request(['page' => '9']), 0, 25));
        $this->assertSame([3, 3], AdminFilters::page($this->request(['page' => '9']), 60, 25));
        $this->assertSame([1, 3], AdminFilters::page($this->request(['page' => '-4']), 60, 25));
        $this->assertSame([2, 3], AdminFilters::page($this->request(['page' => '2']), 60, 25));
    }
}
