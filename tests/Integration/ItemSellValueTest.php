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
 * THE RULE: a shop must always keep a margin. It may never buy an item back for
 * more than a set fraction of what it charges for it — currently 80%. If it ever
 * paid the full price, a player could buy and sell the same thing in a loop and
 * mint unlimited currency; and even at exactly the price, the shopkeeper would be
 * running a cloakroom rather than a shop.
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
    public function testEveryShopKeepsItsMarginOnEverythingItSells(): void
    {
        // The ceiling comes from config rather than being written here, so the
        // rule and the number cannot drift apart. Retuning the economy stays one
        // edit in one documented place, and this test follows it there.
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $ceiling = $config['gameplay']['economy']['maximum_sell_fraction_of_price'];

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
            $price = (int) $offering['price'];
            $sellValue = (int) $offering['sell_value'];
            $mostItMayPay = $price * $ceiling;

            $this->assertLessThanOrEqual(
                $mostItMayPay,
                $sellValue,
                sprintf(
                    '"%s" in %s costs %dg and buys back at %dg — that is %.0f%% of the price. A shop may '
                    . 'pay at most %.0f%% (%.0fg here): it has to live off the difference, and a buy-back '
                    . 'near the full price turns buying and selling into a way of making currency out of '
                    . 'nothing. Lower its sell value.',
                    $offering['item_name'],
                    $offering['shop_name'],
                    $price,
                    $sellValue,
                    $price > 0 ? ($sellValue / $price) * 100 : 100,
                    $ceiling * 100,
                    $mostItMayPay
                )
            );
        }
    }

    public function testTheShopSellsSomethingAPlayerCanSellBack(): void
    {
        // Not a rule, a sanity check: if every item were unsellable, selling would
        // look broken to a player and to whoever builds on this next.
        $shops = new ShopRepository($this->connection);
        $items = $shops->findItems($shops->findBySlug('general-store')->id);

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
            "INSERT INTO items (slug, name, description, price, category_id)
             SELECT 'test-pebble', 'Test Pebble', 'A pebble, for testing.', 5, id
               FROM item_categories WHERE slug = 'material'"
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

    public function testEveryItemBelongsToACategoryThatReallyExists(): void
    {
        // The foreign key makes this true, so the test is really checking that the
        // foreign key is still there — the sort of thing a hasty migration removes
        // without anybody noticing until items start losing their colours.
        $orphans = $this->connection->query(
            'SELECT COUNT(*) FROM items
               LEFT JOIN item_categories ON item_categories.id = items.category_id
              WHERE item_categories.id IS NULL'
        )->fetchColumn();

        $this->assertSame(0, (int) $orphans, 'Some items point at a category that does not exist.');
    }
}
