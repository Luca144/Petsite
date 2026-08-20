<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Tests\DatabaseTestCase;

/**
 * Checks the rules we rely on are enforced by the DATABASE, not only by the code.
 *
 * @package Felkyo\Tests\Integration
 *
 * WHY THIS FILE EXISTS: several house patterns work by letting the database refuse
 * something — "once per day" is a UNIQUE index doing the refusing, not an `if`.
 * That is much stronger than a check in code, but only while the index is actually
 * there. Somebody rewriting a migration can remove one without noticing, and
 * nothing else would fail: the code would keep working perfectly right up until
 * two requests arrived at the same instant.
 *
 * So these ask the database to describe itself, and fail if a guarantee we are
 * leaning on has quietly gone. See docs/house-patterns.md.
 */
final class DatabaseGuaranteesTest extends DatabaseTestCase
{
    /**
     * The columns of a UNIQUE index, if one exists across exactly these columns.
     *
     * @param array<int, string> $columns
     */
    private function hasUniqueIndexOn(string $table, array $columns): bool
    {
        $statement = $this->connection->prepare(
            'SELECT index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS cols
               FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = :t AND non_unique = 0
              GROUP BY index_name'
        );
        $statement->execute([':t' => $table]);

        foreach ($statement->fetchAll() as $index) {
            if ($index['cols'] === implode(',', $columns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every "one of these per person per thing" rule we currently rely on.
     *
     * Each of these is a ledger in the sense of house pattern 2: the row's
     * existence IS the record, and the UNIQUE is what makes writing it twice
     * impossible rather than merely unlikely.
     */
    public function testTheOncePerThingRulesAreEnforcedByTheDatabase(): void
    {
        $ledgers = [
            // One report per person per thing. Without this, one person could file
            // the same report a hundred times and bury a queue that two part-time
            // humans have to read.
            'reports' => ['reporter_user_id', 'subject_type', 'subject_id'],

            // One guestbook entry per person per creature.
            'guestbook_entries' => ['creature_id', 'author_user_id'],

            // One inventory row per person per item — this is what makes the
            // quantity column a reliable count rather than one of several.
            'inventory' => ['user_id', 'item_id'],

            // A shop offers a given item at most once.
            'shop_items' => ['shop_id', 'item_id'],
        ];

        foreach ($ledgers as $table => $columns) {
            $this->assertTrue(
                $this->hasUniqueIndexOn($table, $columns),
                sprintf(
                    'No UNIQUE index on %s(%s). The rule it enforces is currently only a '
                    . 'convention in the code, which means two simultaneous requests can break it.',
                    $table,
                    implode(', ', $columns)
                )
            );
        }
    }

    public function testAnIdentifierThatMustBeUniqueIsUnique(): void
    {
        foreach ([['users', ['username']], ['users', ['email']], ['items', ['slug']],
                  ['species', ['slug']], ['shops', ['slug']], ['item_categories', ['slug']]] as [$table, $columns]) {
            $this->assertTrue(
                $this->hasUniqueIndexOn($table, $columns),
                "{$table}." . implode(',', $columns) . ' is not unique in the database.'
            );
        }
    }

    public function testDeletingAnAccountTakesItsThingsWithIt(): void
    {
        // Not tidiness — a data-protection requirement (M7.2). "Delete my account"
        // has to actually delete, and rows left pointing at a user who no longer
        // exists are exactly how that promise gets quietly broken.
        $statement = $this->connection->query(
            "SELECT k.table_name AS table_name, k.column_name AS column_name, rc.delete_rule AS delete_rule
               FROM information_schema.referential_constraints rc
               JOIN information_schema.key_column_usage k
                 ON k.constraint_name = rc.constraint_name AND k.table_schema = rc.constraint_schema
              WHERE rc.constraint_schema = DATABASE() AND k.referenced_table_name = 'users'"
        );

        $rows = $statement->fetchAll();
        $this->assertNotEmpty($rows, 'Nothing references users at all, which cannot be right.');

        foreach ($rows as $row) {
            // Two rules keep the promise, for two kinds of column:
            //  - A column meaning "this row BELONGS TO that user" must
            //    CASCADE — the row goes with the account.
            //  - A column meaning "that user once DID this to somebody
            //    else's row" (user_roles.granted_by_user_id, M2.1) must SET
            //    NULL — the reference to the deleted account is removed, but
            //    the row belongs to someone else and stays. CASCADE here
            //    would delete the TARGET's role because the GRANTER left.
            // Both remove every reference to the deleted account, which is
            // what the data-protection promise actually requires.
            $this->assertContains(
                $row['delete_rule'],
                ['CASCADE', 'SET NULL'],
                sprintf(
                    '%s.%s points at a user but neither CASCADEs nor SET-NULLs on delete, '
                    . 'so deleting an account would leave a reference to it behind.',
                    $row['table_name'],
                    $row['column_name']
                )
            );
        }
    }
}
