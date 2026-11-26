<?php

namespace Tests\Database;

use App\Models\CategoryModel;
use Config\Database;
use Tests\Support\TestCase\AppTestCase;

/**
 * Guards the foundation of the suite. If one of these fails, nothing else in the suite can be trusted, so the
 * message says what to fix.
 *
 * @internal
 */
final class TestEnvironmentTest extends AppTestCase
{
    public function testTheSuiteRunsAgainstMysqlWithNoTablePrefix(): void
    {
        $this->assertSame('MySQLi', $this->db->DBDriver, 'Set database.tests.DBDriver = MySQLi (phpunit.xml.dist does).');
        $this->assertSame('', $this->db->getPrefix(), 'The migrations use raw SQL that names tables without a prefix.');
    }

    public function testTheApplicationAndTheTestsShareOneConnection(): void
    {
        // If these were two connections, a row the application wrote would sit in a transaction the test cannot see.
        $this->assertSame('tests', config(Database::class)->defaultGroup);
        $this->assertSame(db_connect(), $this->db);
    }

    public function testTheSchemaWasBuiltByThisProjectsMigrations(): void
    {
        foreach (
            ['bookings', 'categories', 'rate_tiers', 'shows', 'show_slots', 'gifts', 'gift_orders', 'gift_vendors',
            'payments', 'notifications_log', 'moderation_logs', 'settings', 'setting_changes', 'message_templates', 'vendor_checkouts'] as $table
        ) {
            $this->assertTrue($this->db->tableExists($table), "Table {$table} is missing: did the migrations run?");
        }
    }

    public function testTablesStartEveryTestEmpty(): void
    {
        $this->assertSame(0, $this->db->table('categories')->countAllResults());

        $this->makeCategory();

        $this->assertSame(1, $this->db->table('categories')->countAllResults());
    }

    public function testTheRowFromTheLastTestIsGone(): void
    {
        // Runs after the test above (or before it); either way the table must be empty, never 1 or 2.
        $this->assertSame(0, (new CategoryModel())->countAllResults());
    }

    public function testFactoriesBuildValidRowsAndRejectInvalidOnes(): void
    {
        $category = $this->makeCategory(['name' => 'Birthdays']);
        $tier     = $this->makeTier($category['id'], ['price' => 30000]);
        $booking  = $this->makeBooking(['category_id' => $category['id'], 'rate_tier_id' => $tier['id']]);

        $this->assertSame('Birthdays', $category['name']);
        $this->assertSame('30000.00', $booking['amount']);

        $this->expectExceptionMessage('Test fixture rejected');
        $this->makeCategory(['name' => '']);
    }

    public function testAStaffUserExistsForForeignKeys(): void
    {
        $id = \Tests\Support\Libraries\StaffUser::id();

        $this->assertGreaterThan(0, $id);
        $this->assertSame(1, $this->db->table('users')->where('id', $id)->countAllResults());
    }
}
