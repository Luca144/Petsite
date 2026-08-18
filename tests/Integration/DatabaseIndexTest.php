<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Tests\DatabaseTestCase;

/**
 * Proves that the columns we search and filter on are indexed.
 *
 * @package Felkyo\Tests\Integration
 *
 * WHY THIS TEST EXISTS. Every query in this codebase is fast today, because every
 * table has a few dozen rows. A missing index is invisible until it isn't: the
 * pettings table gains a row every time anybody pets anything, and the day it
 * holds a million of them, a cooldown check without an index reads all million,
 * on every click. Nothing warns you — the page just gets slower and slower.
 *
 * So the indexes are asserted here, next to the reason for each one, and a
 * migration that forgets one fails the build instead of failing in a year.
 *
 * WHAT THIS TEST DOES NOT DO: it does not time anything. A test that asserts
 * "this must finish in 50ms" passes or fails depending on what else the machine
 * is doing, and a test that fails at random teaches people to ignore failures.
 * Whether an index is USED is a question for EXPLAIN when something is actually
 * slow; whether it EXISTS is a fact, and facts are what tests are good at.
 */
final class DatabaseIndexTest extends DatabaseTestCase
{
    /**
     * Is the given column part of at least one index — on its own, or as the
     * first column of a composite one?
     *
     * ONLY THE FIRST COLUMN COUNTS, and that is the whole point of this helper.
     * An index on (a, b, c) helps a query that filters on "a", and does nothing
     * at all for a query that filters only on "c" — the database can no more use
     * it than you could use a phone book to look somebody up by first name. So
     * "is this column mentioned anywhere in any index" would be the wrong
     * question, and would pass while the query still read the whole table.
     */
    private function assertColumnIsIndexedFirst(string $table, string $column, string $why): void
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*)
               FROM INFORMATION_SCHEMA.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND COLUMN_NAME = :column
                AND SEQ_IN_INDEX = 1'
        );
        $statement->execute([':table' => $table, ':column' => $column]);

        $this->assertGreaterThan(
            0,
            (int) $statement->fetchColumn(),
            "No index starts with {$table}.{$column}. {$why}"
        );
    }

    public function testCooldownAndPetCountsAreIndexed(): void
    {
        // Every pet asks "has this person petted this creature recently?" and every
        // creature page asks "how many times has this been petted?". Both start
        // from creature_id, and this is the fastest-growing table on the site.
        $this->assertColumnIsIndexedFirst(
            'pettings',
            'creature_id',
            'The cooldown check and the "times petted" count both filter on it, on a table that gains a row per pet.'
        );
    }

    public function testCreaturesAreIndexedByOwner(): void
    {
        // "My creatures", the home page and every profile page filter on this.
        $this->assertColumnIsIndexedFirst(
            'creatures',
            'owner_id',
            'Listing a player\'s creatures filters on it, on every page they visit.'
        );
    }

    public function testRateLimitLookupsAreIndexed(): void
    {
        // The rate limiter runs on every state-changing request, so an unindexed
        // lookup here would slow down the whole site — and it is the table that
        // grows fastest after pettings.
        $this->assertColumnIsIndexedFirst(
            'rate_limit_hits',
            'action_key',
            'Every rate-limited request counts recent hits by action, on every login, pet and search.'
        );
    }

    public function testInventoryIsIndexedByOwner(): void
    {
        // Both "show me my things" and "do I own this one?" start from the user.
        $this->assertColumnIsIndexedFirst(
            'inventory',
            'user_id',
            'Every inventory read and every purchase filters on it.'
        );
    }

    public function testGuestbookIsIndexedByCreature(): void
    {
        // A creature page loads its guestbook on every view.
        $this->assertColumnIsIndexedFirst(
            'guestbook_entries',
            'creature_id',
            'Every creature page loads that creature\'s guestbook entries.'
        );
    }

    public function testPlayerLookupsAreIndexed(): void
    {
        // Logging in looks a player up by name; player search matches on a name
        // prefix, which an index on username can serve.
        $this->assertColumnIsIndexedFirst(
            'users',
            'username',
            'Logging in and searching for a player both start from the username.'
        );
    }
}
