<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds "sell_value" to items — what a player gets back for parting with one.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS EXISTS (increment M1.1, the owned-thing model): an item has two
 * numbers attached to it, and they are genuinely different things:
 *
 *   price       what a shop CHARGES a player for one.
 *   sell_value  what a player GETS BACK for selling one.
 *
 * Until now only "price" existed, because nothing could be sold yet. M1.2 adds
 * selling, so the second number has to exist and has to be its own column.
 *
 * WHY THEY MUST BE SEPARATE — this is the important part. If an item could be
 * sold back for MORE than it costs to buy, a player could buy it and sell it,
 * over and over, and make endless currency out of nothing. That is the currency
 * duplication problem CLAUDE.md names directly. Two separate, named numbers make
 * that rule something we can write down and test. One column doing both jobs
 * would hide it.
 *
 * The rule, stated plainly: **an item a shop offers must never sell for more
 * than that shop charges for it.** It is checked by
 * tests/Integration/ItemSellValueTest.php against all real data, and it is the
 * artist's job to respect it when adding items through the panel (M2.4).
 *
 * A sell_value of 0 means "this cannot be sold" — not "this sells for nothing".
 * That distinction lets the site say something kind and specific ("Nobody around
 * here would buy this") instead of showing a sell button that pays zero, which
 * would just feel broken.
 */
final class AddSellValueToItems extends AbstractMigration
{
    public function up(): void
    {
        $this->table('items')
            // UNSIGNED, like every other money column in this schema — a sell
            // value can never be negative. Default 0 means a newly added item is
            // NOT sellable until somebody deliberately says what it is worth,
            // which is the safe direction to be wrong in.
            ->addColumn('sell_value', 'integer', [
                'signed' => false,
                'default' => 0,
                'null' => false,
                'after' => 'price',
            ])
            ->update();

        // Give the four seeded starter items a sensible sell value so selling
        // works the moment M1.2 lands, rather than every item being unsellable.
        //
        // Half the price, rounded down, is the familiar shopkeeper's bargain: you
        // lose a little on the way back out. It is comfortably below the price,
        // so it cannot become a money loop. These are PLACEHOLDER values like the
        // prices beside them — the artist can change any of them, and once M2.4
        // exists that is a panel edit rather than a migration.
        $this->execute('UPDATE items SET sell_value = FLOOR(price / 2)');
    }

    public function down(): void
    {
        $this->table('items')->removeColumn('sell_value')->update();
    }
}
