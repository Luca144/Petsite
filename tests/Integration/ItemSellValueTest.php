<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Economy\ShopRepository;
use Felkyo\Tests\DatabaseTestCase;

/**
 * Guards the rule that stops currency being made out of nothing.
 *
 * @package Felkyo\Tests\Integration
 *
 * THE RULE: an item a shop offers must never sell back for more than that shop
 * charges for it. If it ever did, a player could buy and sell the same thing in a
 * loop and mint unlimited currency — the duplication problem CLAUDE.md names.
 *
 * WHY THE TEST IS WRITTEN AGAINST REAL DATA rather than a made-up example: this
 * is not really a test of code, because no code is capable of breaking it. It is a
 * test of CONTENT. The way this rule gets broken in practice is somebody typing a
 * generous sell value into the panel one evening (M2.4) — so the test walks
 * everything the shops actually offer and fails naming the item at fault. It will
 * be doing its most useful work years from now, for someone who never read this.
 */
final class ItemSellValueTest extends DatabaseTestCase
{
    public function testNoItemInAnyShopSellsForMoreThanItCosts(): void
    {
        // Every offering in every shop, with both of its numbers. We look at the
        // real shop_items links rather than at items generally, because an item
        // nobody sells cannot be bought, and something that cannot be bought
        // cannot be part of a buy-and-sell loop however much it is worth.
        $offerings = $this->connection->query(
            'SELECT shops.name AS shop_name, items.name AS item_name,
                    items.price, items.sell_value
               FROM shop_items
               JOIN items ON items.id = shop_items.item_id
               JOIN shops ON shops.id = shop_items.shop_id'
        )->fetchAll();

        $this->assertNotEmpty($offerings, 'No shop offerings found — the seed data should provide some.');

        foreach ($offerings as $offering) {
            $this->assertLessThanOrEqual(
                (int) $offering['price'],
                (int) $offering['sell_value'],
                sprintf(
                    '"%s" in %s costs %d but sells back for %d. That is a money loop: a player could '
                    . 'buy and sell it forever and make currency out of nothing. Lower its sell value.',
                    $offering['item_name'],
                    $offering['shop_name'],
                    (int) $offering['price'],
                    (int) $offering['sell_value']
                )
            );
        }
    }

    public function testTheShopSellsSomethingAPlayerCanSellBack(): void
    {
        // Not a rule, a sanity check: if every item were unsellable, selling would
        // look broken to a player and to whoever builds M1.2 on top of this.
        $items = (new ShopRepository($this->connection))
            ->findItems((new ShopRepository($this->connection))->findBySlug('general-store')->id);

        $sellable = array_filter($items, static fn ($item): bool => $item->isSellable());

        $this->assertNotEmpty($sellable, 'No seeded item can be sold — selling would appear broken.');
    }

    public function testANewItemIsNotSellableUntilSomebodySaysWhatItIsWorth(): void
    {
        // The column defaults to 0, and 0 means "no shop wants this". That is the
        // safe direction to be wrong in: a new item that quietly cannot be sold is
        // a small disappointment, whereas one that quietly sells for a fortune is
        // an economy bug nobody notices until the currency is worthless.
        $this->connection->exec(
            "INSERT INTO items (slug, name, description, price, type)
             VALUES ('test-pebble', 'Test Pebble', 'A pebble, for testing.', 5, 'oddment')"
        );

        $sellValue = $this->connection
            ->query("SELECT sell_value FROM items WHERE slug = 'test-pebble'")
            ->fetchColumn();

        $this->connection->exec("DELETE FROM items WHERE slug = 'test-pebble'");

        $this->assertSame(0, (int) $sellValue);
    }

    public function testSellValueCanNeverGoNegative(): void
    {
        // We check the column's DEFINITION rather than trying to insert a negative
        // number, because what happens on a bad insert depends on the database's
        // strict-mode setting — which differs between a development machine and
        // the live server. The column being UNSIGNED is true on both, so that is
        // what we pin down.
        $definition = $this->connection->query(
            "SELECT column_type, is_nullable
               FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = 'items'
                AND column_name = 'sell_value'"
        )->fetch();

        $this->assertNotFalse($definition, 'The items table has no sell_value column.');
        $this->assertStringContainsString('unsigned', strtolower($definition['column_type']));
        $this->assertSame('NO', $definition['is_nullable']);
    }
}
