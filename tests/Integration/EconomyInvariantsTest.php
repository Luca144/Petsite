<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Economy\InventoryRepository;
use Felkyo\Economy\ShopRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * The things about the economy that must never stop being true.
 *
 * @package Felkyo\Tests\Integration
 *
 * WHY THESE ARE TESTED AGAINST THE DATABASE rather than against the code: "the
 * code never writes a negative balance" and "a negative balance cannot exist" are
 * different claims, and only the second one survives somebody adding a new way to
 * spend money in eight months' time.
 *
 * So each of these asks the database what it will actually allow, or asks it what
 * it currently contains. See docs/house-patterns.md section 4.
 */
final class EconomyInvariantsTest extends DatabaseTestCase
{
    /**
     * Ask the database itself how a column is defined. Used throughout rather than
     * trusting the migration file, because what matters is the table that exists.
     *
     * @return array{column_type: string, is_nullable: string}|null
     */
    private function columnDefinition(string $table, string $column): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT column_type, is_nullable
               FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c'
        );
        $statement->execute([':t' => $table, ':c' => $column]);

        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    // ---- Nothing may go negative ----

    /**
     * Every column holding money or a count is UNSIGNED, so the database itself
     * refuses a negative — whatever the code above it believes.
     */
    public function testNoMoneyOrCountColumnCanEverGoNegative(): void
    {
        $mustBeUnsigned = [
            ['users', 'currency_balance'],
            ['inventory', 'quantity'],
            ['items', 'price'],
            ['items', 'sell_value'],
            ['creatures', 'xp'],
            ['creatures', 'happiness'],
        ];

        foreach ($mustBeUnsigned as [$table, $column]) {
            $definition = $this->columnDefinition($table, $column);

            $this->assertNotNull($definition, "{$table}.{$column} does not exist.");
            $this->assertStringContainsString(
                'unsigned',
                strtolower($definition['column_type']),
                "{$table}.{$column} is not UNSIGNED, so a negative value could be stored."
            );
        }
    }

    public function testSpendingMoreThanYouHaveChangesNothing(): void
    {
        // The conditional update in action: the balance is not read, compared and
        // written back — the condition is inside the statement, so two requests
        // arriving together cannot both pass it.
        $users = new UserRepository($this->connection);
        $this->clearTables('inventory', 'pettings', 'creatures', 'users');
        $user = $users->create('spender', 'spender@example.com', 'hash');

        $users->addCurrency($user->id, 10);

        $this->assertFalse($users->deductCurrency($user->id, 11), 'Overspending should be refused.');
        $this->assertSame(10, $users->findById($user->id)->currencyBalance);

        $this->assertTrue($users->deductCurrency($user->id, 10), 'Spending exactly the balance is fine.');
        $this->assertSame(0, $users->findById($user->id)->currencyBalance);
    }

    // ---- No ghost rows ----

    public function testAnEmptiedPileLeavesNoGhostRowBehind(): void
    {
        // A row of quantity zero is not "owning none of something", it is a piece
        // of litter that every later query has to remember to filter out. The row
        // goes when the last one does.
        $this->clearTables('inventory', 'pettings', 'creatures', 'users');

        $users = new UserRepository($this->connection);
        $inventory = new InventoryRepository($this->connection);
        $shops = new ShopRepository($this->connection);

        $user = $users->create('keeper', 'keeper@example.com', 'hash');
        $item = $shops->findItems($shops->findBySlug('general-store')->id)[0];

        $inventory->addItem($user->id, $item->id);
        $inventory->removeOne($user->id, $item->id);

        $ghosts = $this->connection
            ->query('SELECT COUNT(*) FROM inventory WHERE quantity = 0')
            ->fetchColumn();

        $this->assertSame(0, (int) $ghosts, 'An inventory row of quantity zero was left behind.');
        $this->assertNull($inventory->findStackForUser($user->id, $item->id));
    }

    public function testNoGhostRowsExistInTheDatabaseAtAll(): void
    {
        // Not about one code path — a sweep of everything currently stored, which
        // would catch a row left behind by any route, including one added later.
        $ghosts = $this->connection
            ->query('SELECT COUNT(*) FROM inventory WHERE quantity = 0')
            ->fetchColumn();

        $this->assertSame(0, (int) $ghosts);
    }

    // ---- Prices ----

    public function testEveryPriceAndSellValueStoredIsZeroOrMore(): void
    {
        // The UNSIGNED columns make this true; this asserts it about the actual
        // contents, which is what a person would actually want to know.
        $bad = $this->connection
            ->query('SELECT COUNT(*) FROM items WHERE price < 0 OR sell_value < 0')
            ->fetchColumn();

        $this->assertSame(0, (int) $bad);
    }

    /**
     * The money-loop rule has its own file — this exists so that somebody reading
     * the invariants in one place is told where the fifth one lives, rather than
     * concluding it is missing.
     *
     * @see ItemSellValueTest::testEveryShopKeepsItsMarginOnEverythingItSells()
     */
    public function testTheSellValueRuleIsCoveredElsewhere(): void
    {
        $this->assertTrue(
            method_exists(ItemSellValueTest::class, 'testEveryShopKeepsItsMarginOnEverythingItSells'),
            'The rule that nothing sells for more than it costs has lost its test.'
        );
    }
}
