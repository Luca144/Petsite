<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Core\PendingMigrations;
use Felkyo\Tests\DatabaseTestCase;

/**
 * Tests the guard that notices when the database is behind the code.
 *
 * @package Felkyo\Tests\Integration
 *
 * WHY IT EXISTS: three migrations were written and shipped without being run
 * against production, and every page became "Unknown column 'energy'" from inside
 * a repository. The message was accurate and useless — it named a column rather
 * than the thing to do about it.
 *
 * The most important test here is the LAST one: that a broken diagnostic cannot
 * take the site down by itself. A check that fails loudly when it cannot answer
 * its own question would be worse than not having it.
 */
final class PendingMigrationsTest extends DatabaseTestCase
{
    private const MIGRATIONS = __DIR__ . '/../../migrations';

    public function testAFullyMigratedDatabaseHasNothingOutstanding(): void
    {
        // The test database is migrated by the bootstrap, so this is the ordinary
        // healthy case — and it is the one that must never report a problem,
        // because a false alarm here is a site outage.
        $this->assertSame(
            0,
            PendingMigrations::count($this->connection, self::MIGRATIONS),
            'The test database reports outstanding migrations while fully migrated. '
            . 'A false alarm here would take the live site down.'
        );
    }

    public function testAnUnmigratedDatabaseReportsEveryFile(): void
    {
        // What the deploy actually looked like: the code is new, the database has
        // never heard of any of it. Simulated by hiding Phinx's bookkeeping table,
        // which is exactly what a fresh database looks like.
        $this->connection->exec('RENAME TABLE phinxlog TO phinxlog_hidden_by_test');

        try {
            $pending = PendingMigrations::count($this->connection, self::MIGRATIONS);
        } finally {
            // Restore it whatever happened, or every later test in the run
            // inherits a database with no migration history.
            $this->connection->exec('RENAME TABLE phinxlog_hidden_by_test TO phinxlog');
        }

        $this->assertGreaterThan(0, $pending);
        $this->assertSame(count(glob(self::MIGRATIONS . '/*.php')), $pending);
    }

    public function testOneUnappliedMigrationIsNoticed(): void
    {
        // The realistic case: the database is one release behind. Simulated by
        // removing the newest row from the log rather than by adding a file, so
        // the test does not depend on writing to the migrations directory.
        $newest = (int) $this->connection->query('SELECT MAX(version) FROM phinxlog')->fetchColumn();
        $row = $this->connection->query(
            'SELECT * FROM phinxlog WHERE version = ' . $newest
        )->fetch();

        $this->connection->exec('DELETE FROM phinxlog WHERE version = ' . $newest);

        try {
            $pending = PendingMigrations::count($this->connection, self::MIGRATIONS);
        } finally {
            // Put the row back exactly as it was — column by column, so this keeps
            // working if Phinx ever adds a column to its own table.
            $columns = array_keys($row);
            $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);
            $restore = $this->connection->prepare(
                'INSERT INTO phinxlog (' . implode(', ', $columns) . ') '
                . 'VALUES (' . implode(', ', $placeholders) . ')'
            );
            $restore->execute($row);
        }

        $this->assertSame(1, $pending);
    }

    public function testAMissingMigrationsDirectoryReportsNothingRatherThanCrashing(): void
    {
        // THE ONE THAT MATTERS MOST. This class is a diagnostic, and a diagnostic
        // that can bring the site down on its own is worse than no diagnostic at
        // all. Asked an impossible question, it must shrug and let the app carry
        // on to either work or fail for its own reasons.
        $this->assertSame(
            0,
            PendingMigrations::count($this->connection, '/no/such/directory/anywhere')
        );
    }
}
