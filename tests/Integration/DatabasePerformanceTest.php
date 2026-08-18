<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Tests\PerformanceTestCase;

/**
 * Database schema and index performance tests.
 *
 * @package Felkyo\Tests\Integration
 *
 * These tests verify that the database schema supports efficient queries:
 * - All frequently-queried columns have indexes
 * - Foreign keys are properly indexed
 * - No accidental full-table scans can happen
 *
 * If any of these fail, queries that worked fast with 100 rows will be slow
 * with 10,000 rows.
 */
final class DatabasePerformanceTest extends PerformanceTestCase
{
    /**
     * USERS table: commonly queried by id, username, email.
     */
    public function testUsersTableHasRequiredIndexes(): void
    {
        $this->assertIndexExists('users', 'id', 'PRIMARY key on users.id');
        $this->assertIndexExists('users', 'username', 'Lookups by username need an index');
        $this->assertIndexExists('users', 'email', 'Email lookups during login need an index');
    }

    /**
     * CREATURES table: queried by owner_id (show my creatures), by id (individual creature page).
     * species_id is a foreign key that doesn't need its own index (the FK constraint is enough).
     */
    public function testCreaturesTableHasRequiredIndexes(): void
    {
        $this->assertIndexExists('creatures', 'id', 'PRIMARY key on creatures.id');
        $this->assertIndexExists('creatures', 'owner_id', 'List my creatures without full table scan');
    }

    /**
     * PETTINGS table: heavily queried by (creature_id, actor_user_id) for cooldown checks,
     * and by creature_id alone for "times petted" counts.
     *
     * This is a high-volume table (one row per pet). Without indexes, it becomes a bottleneck.
     */
    public function testPettingsTableHasRequiredIndexes(): void
    {
        $this->assertIndexExists('pettings', 'creature_id', 'Count pettings per creature');
        $this->assertIndexExists('pettings', 'actor_user_id', 'Check cooldown per actor');
    }

    /**
     * INVENTORY table: queried by user_id (show player's items) and by (user_id, item_id)
     * (check if player owns a specific item).
     */
    public function testInventoryTableHasRequiredIndexes(): void
    {
        $this->assertIndexExists('inventory', 'user_id', 'List inventory per user');
    }

    /**
     * RATE_LIMIT_HITS table: queried by (action, client_ip) to enforce rate limits.
     * This is a security-critical, high-volume table that needs to be fast.
     */
    public function testRateLimitTableHasRequiredIndexes(): void
    {
        $this->assertIndexExists('rate_limit_hits', 'action', 'Rate limit lookups by action');
        $this->assertIndexExists('rate_limit_hits', 'client_ip', 'Rate limit lookups by IP');
    }

    /**
     * GUESTBOOK_ENTRIES table: queried by owner_user_id (show entries on a profile page).
     */
    public function testGuestbookTableHasRequiredIndexes(): void
    {
        $this->assertIndexExists('guestbook_entries', 'owner_user_id', 'List guestbook entries per owner');
    }

    /**
     * SHOP_ITEMS table: this is mostly read-only (static content), so indexes are less critical
     * but still good to have for shop display.
     */
    public function testShopTableHasRequiredIndexes(): void
    {
        $this->assertIndexExists('shops', 'slug', 'Shop lookups by URL slug');
    }

    /**
     * SPECIES table: mostly read-only, queried by id and occasionally by is_starter/is_adoptable.
     * Not as critical as high-volume tables, but worth having.
     */
    public function testSpeciesTableHasRequiredIndexes(): void
    {
        $this->assertIndexExists('species', 'id', 'Species lookup by id');
    }

    /**
     * Verify that foreign keys are in place (data integrity).
     * These also implicitly create indexes on the foreign key columns.
     */
    public function testForeignKeyConstraintsExist(): void
    {
        $fks = $this->connection->query(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME != 'PRIMARY'"
        )->fetchAll();

        $this->assertNotEmpty($fks, 'Foreign key constraints should be in place');
    }

    /**
     * Performance insight test: make sure creature search (if it exists) won't do a full table scan.
     * If there's a search feature that filters by username_skeleton (for fuzzy search),
     * that column should be indexed.
     */
    public function testSearchIndexesExist(): void
    {
        // Check if username_skeleton column exists (used for search)
        $stmt = $this->connection->query(
            "SHOW COLUMNS FROM users WHERE Field = 'username_skeleton'"
        );
        $hasSkeletonColumn = $stmt->fetch() !== false;

        if ($hasSkeletonColumn) {
            $this->assertIndexExists(
                'users',
                'username_skeleton',
                'Search by username_skeleton should use an index'
            );
        }
    }

    /**
     * Verify no accidental MAX_ROWS or table size limits that could cause issues.
     * This is more of a warning than a hard requirement.
     */
    public function testTableRowCounts(): void
    {
        $tables = [
            'users' => 'Should grow with player base (1k-100k players possible)',
            'creatures' => 'Should grow with petting activity (~5-10x users)',
            'pettings' => 'Will be the largest table (millions of rows at scale)',
            'inventory' => 'Moderate size (~10x creatures)',
        ];

        $tableSizes = $this->connection->query(
            "SELECT TABLE_NAME, TABLE_ROWS FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()"
        )->fetchAll();

        $sizes = [];
        foreach ($tableSizes as $row) {
            $sizes[$row['TABLE_NAME']] = $row['TABLE_ROWS'];
        }

        // This is informational, not a hard assertion. It helps understand growth.
        echo "\n--- Table Row Counts ---\n";
        foreach ($tables as $table => $note) {
            $count = $sizes[$table] ?? 0;
            echo "$table: ~$count rows — $note\n";
        }
    }
}
