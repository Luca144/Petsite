<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Gives every shop listing an optional "available from" and "available to" date.
 *
 * @package Felkyo\Migrations
 *
 * WHAT THIS IS FOR: seasonal stock. Ruinily selling Valentine chocolates for one
 * week in February, the Gallows opening for Halloween, a summer item that goes away
 * again — all of it becomes two dates somebody types, rather than a developer's
 * afternoon each time.
 *
 * NOTHING READS THESE COLUMNS YET, AND THAT IS DELIBERATE. The shop engine that
 * honours them is M9. They are added now because adding a column to a small table
 * nobody is using costs nothing, while adding one later — to a table full of live
 * listings, on a running site — is a careful job done under time pressure. The old
 * prototype had exactly these two columns and they were the right idea; this is one
 * of the few things carried over from it.
 *
 * WHAT M9 MUST DO WITH THEM (written here so it is not rediscovered):
 *   - NULL means "always available". Both columns are nullable and default to NULL,
 *     so every existing listing keeps behaving exactly as it does today.
 *   - A listing outside its window is simply not offered. It is not shown greyed
 *     out with "come back in March" — that is a tease, and this site does not do
 *     those (build plan 2, principle (d): keep the loop kind).
 *   - The panel screen gets two date fields, and must accept them being left empty.
 *
 * WHY DATE AND NOT DATETIME: shops open for days, not for hours. A date is what the
 * artist actually thinks in, and it avoids every timezone question — "the 14th of
 * February" means the same thing to everybody, where "midnight" does not.
 */
final class AddShopListingDates extends AbstractMigration
{
    public function change(): void
    {
        $this->table('shop_items')
            // NULL = "has always been on sale". The default matters: every listing
            // that exists today gets NULL and carries on unchanged.
            ->addColumn('available_from', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'item_id',
            ])
            // NULL = "and always will be".
            ->addColumn('available_to', 'date', [
                'null' => true,
                'default' => null,
                'after' => 'available_from',
            ])
            // The shop page will ask "what is on sale today?", which is a question
            // about both columns at once, so they are indexed together.
            ->addIndex(['available_from', 'available_to'])
            ->update();
    }
}
