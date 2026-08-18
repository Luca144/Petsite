<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Gives each species a price, so creatures can be bought rather than waited for.
 *
 * @package Felkyo\Migrations
 *
 * WHY (M2): a creature arrived through daily adoption — one free creature every
 * 24 hours, from a button that either worked or told you to come back tomorrow.
 * That is a mechanic whose only input is patience, and it gave gems nothing
 * interesting to be for. Creatures are now bought with gems, which are earned by
 * visiting other people's creatures: the way you get a creature is the way the
 * site is meant to be used.
 *
 * WHY THE PRICE IS A COLUMN HERE AND NOT A shop_creatures TABLE. Items work that
 * way — items.price says what one costs, shop_items says which shop stocks it —
 * because there could plausibly be several shops with different shelves. There is
 * one place to get a creature, and a species has one price. A join table would
 * model "many shops sell many species", which is not a thing this site has, and
 * building it now would be the speculative layer CLAUDE.md section 3 forbids. If a
 * second creature shop ever exists, adding the table then is a small migration.
 *
 * WHICH SPECIES ARE FOR SALE is still is_adoptable — the same flag that decided
 * which ones the adoption button could hand out. The word survives the change in
 * mechanic: these are the species a player can come to own.
 *
 * THE PRICES ARE PLACEHOLDERS, like every price in this schema. The artist owns
 * them. The only rule worth respecting is that the cheapest one stays reachable
 * in an evening's petting — a creature you cannot afford for a fortnight is not a
 * goal, it is a wall.
 */
final class PriceSpeciesForTheShop extends AbstractMigration
{
    public function up(): void
    {
        $this->table('species')
            // UNSIGNED like every price in this schema. 0 would mean "free", which
            // is a thing we might want one day (a gift, a promotion) and which the
            // shop treats honestly rather than as a bug.
            ->addColumn('gem_price', 'integer', [
                'signed' => false,
                'default' => 0,
                'null' => false,
                'after' => 'is_adoptable',
            ])
            ->update();

        // Everything obtainable gets a starting price. The starters are the
        // cheapest, because they are what a new player recognises and the first
        // thing they are likely to want a second of.
        $this->execute('UPDATE species SET gem_price = 50 WHERE is_starter = 1');
        $this->execute('UPDATE species SET gem_price = 120 WHERE is_starter = 0 AND is_adoptable = 1');
    }

    public function down(): void
    {
        $this->table('species')->removeColumn('gem_price')->update();
    }
}
