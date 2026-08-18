<?php

declare(strict_types=1);

namespace Felkyo\Tests;

use Felkyo\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * A base class for tests that need the real (test) database.
 *
 * @package Felkyo\Tests
 *
 * WHAT THIS IS: integration tests run against the separate felkyo_test database
 * (the test bootstrap has already migrated it). This base class opens a
 * connection for each test and offers a helper to empty tables so every test
 * starts from a clean, known state and does not depend on any other test.
 *
 * Tests that use it should call parent::setUp() and then clearTables(...) for the
 * tables they touch.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $connection;

    /**
     * The real config, so tests can build services with the REAL tuning rather
     * than numbers invented in the test. A test that makes up its own gameplay
     * values proves the code works with those values, which is not the question
     * anybody is asking. Tests that deliberately want different numbers (to reach
     * a cap in three lines, say) still pass their own — but they do it on purpose.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    protected function setUp(): void
    {
        // config.php reads the database name from the environment, which the test
        // bootstrap forced to felkyo_test — so this connects to the test database.
        $this->config = require dirname(__DIR__) . '/config/config.php';
        $this->connection = Database::connect($this->config['database']);
    }

    /**
     * The mood rules, built from real config. Creature displays all need one, so
     * it is offered here instead of being assembled in six different test files.
     */
    protected function moodCalculator(): \Felkyo\Creatures\MoodCalculator
    {
        return new \Felkyo\Creatures\MoodCalculator($this->config['gameplay']['mood']);
    }

    /**
     * Empty the given tables. We turn foreign-key checks off for the duration so
     * the tables can be cleared in any order without tripping over the links
     * between them, then turn the checks back on.
     *
     * The table names are written by us in the tests (never user input), so it is
     * safe to place them directly in the statement.
     */
    protected function clearTables(string ...$tables): void
    {
        $this->connection->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $this->connection->exec('DELETE FROM ' . $table);
        }
        $this->connection->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
