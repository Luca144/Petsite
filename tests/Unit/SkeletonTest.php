<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Skeleton tests for increment 0.1.
 *
 * @package Felkyo\Tests\Unit
 *
 * WHAT THIS IS: the very first automated tests. Their job is not to check game
 * behaviour (there is none yet) but to prove the test harness itself works: that
 * PHPUnit runs, that our autoloading and configuration load, and that the tests
 * can reach the SEPARATE test database. Real feature tests arrive from Phase A.
 */
final class SkeletonTest extends TestCase
{
    /**
     * Proves PHPUnit is installed and running our tests at all. If this fails,
     * something is wrong with the test setup, not with any feature.
     */
    public function testTheTestHarnessRuns(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Proves two things the plan requires for 0.1: that we can open a database
     * connection through our own Database class, and that the tests are pointed
     * at the SEPARATE "felkyo_test" database (the safety guard in
     * tests/bootstrap.php), never the real one.
     */
    public function testTestsConnectToTheSeparateTestDatabase(): void
    {
        // Rebuild the config now that the test bootstrap has forced the test
        // database name, then connect using our own factory.
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $connection = Database::connect($config['database']);

        // Ask the database which database we are actually connected to. This is
        // the honest end-to-end proof that the separate test DB is wired up.
        $connectedDatabaseName = $connection->query('SELECT DATABASE()')->fetchColumn();

        $this->assertSame('felkyo_test', $connectedDatabaseName);
        $this->assertInstanceOf(PDO::class, $connection);
    }
}
