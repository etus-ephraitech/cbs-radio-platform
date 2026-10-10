<?php

namespace Tests\Support\TestCase;

use App\Libraries\Settings\Overrides;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Base for tests that must not touch the database.
 *
 * Configuration classes such as Config\CbsRadio normally read the administrators' overrides from the database
 * when they are created. That is switched off here, so a config object holds only its coded defaults (and whatever
 * the test then sets on it) and nothing in a unit test can reach a table.
 */
abstract class UnitTestCase extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Overrides::$suspended = true;
    }

    protected function tearDown(): void
    {
        Overrides::$suspended = false;

        parent::tearDown();
    }
}
