<?php

namespace Tests\Support\TestCase;

use App\Libraries\Settings\Overrides;
use App\Libraries\Settings\SettingsManager;
use App\Models\SettingModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Concerns\BuildsCatalog;
use Tests\Support\Libraries\AudioFixtures;

/**
 * Base for every test that needs the database.
 *
 * The schema is built once per run by this project's own migrations, against the `tests` database group (MySQL,
 * see phpunit.xml.dist). Before each test the tables this project owns are emptied, so tests never see each
 * other's rows and may be run in any order. The starter kit's `users` table is left alone.
 *
 * What a test changes in the settings (an admin override) is held in the database and in a cache, so both are
 * reset here, and so are the configuration objects that read them.
 */
abstract class AppTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use BuildsCatalog;

    protected $DBGroup     = 'tests';
    protected $migrate     = true;
    protected $migrateOnce = true;   // build the schema once per run, not once per test (it takes seconds)
    protected $refresh     = false;  // ...and don't tear it down in between: tables are emptied instead
    protected $namespace   = null;   // every namespace's migrations, which includes the starter kit's

    /** Tables this project owns, emptied before every test. Order does not matter: foreign keys are off meanwhile. */
    private const TABLES = [
        'setting_changes', 'settings', 'message_templates', 'notifications_log', 'moderation_logs',
        'payments', 'gift_orders', 'bookings', 'show_slots', 'shows', 'gifts', 'gift_vendors',
        'rate_tiers', 'categories',
    ];

    private const VOICE_SUBPATH = 'voice-notes-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->emptyTables();
        SettingsManager::flush();
        Overrides::$suspended = false;
        $this->configureForTests();
    }

    protected function tearDown(): void
    {
        AudioFixtures::cleanUp();
        $this->removeDirectory(WRITEPATH . self::VOICE_SUBPATH);
        SettingsManager::flush();
        Overrides::$suspended = false;

        parent::tearDown();
    }

    /**
     * Saves an admin override, exactly as Admin > Settings would, and makes the next config() call see it.
     */
    protected function setSetting(string $key, string $raw): void
    {
        (new SettingModel())->setOverride($key, $raw, null);

        $this->reloadConfig();
    }

    /**
     * Throws away the cached configuration objects so the next config() call reads the database again.
     */
    protected function reloadConfig(): void
    {
        SettingsManager::flush();
        $this->resetFactories();
        $this->configureForTests();
        $this->afterConfigReload();
    }

    /**
     * For subclasses that put something into the configuration cache and need it back after a reload.
     */
    protected function afterConfigReload(): void
    {
    }

    private function configureForTests(): void
    {
        // Voice notes go to their own folder, which is removed after every test, never into real storage.
        config('CbsRadio')->voiceNoteUploadSubpath = self::VOICE_SUBPATH;
    }

    private function emptyTables(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TABLES as $table) {
            $this->db->table($table)->truncate();
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            is_dir("{$path}/{$entry}") ? $this->removeDirectory("{$path}/{$entry}") : unlink("{$path}/{$entry}");
        }

        rmdir($path);
    }
}
