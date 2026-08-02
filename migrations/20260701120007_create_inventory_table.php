<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the "inventory" table — which items each user owns, and how many.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS TABLE EXISTS: it connects users to items (a "join table"). One row
 * means "this user owns this many of this item". We keep a quantity rather than
 * one row per copy, so owning five stickers is a single row with quantity 5.
 *
 * The unique (user_id, item_id) rule below guarantees a user has at most ONE row
 * per item, which is what makes "quantity" the reliable count.
 */
final class CreateInventoryTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('inventory', ['signed' => false]);

        $table
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('item_id', 'integer', ['signed' => false, 'null' => false])
            // How many of this item the user owns. UNSIGNED — never negative.
            ->addColumn('quantity', 'integer', ['signed' => false, 'default' => 1, 'null' => false])
            // If the user is deleted, their inventory goes too.
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            // An item definition that someone owns cannot be deleted (RESTRICT).
            ->addForeignKey('item_id', 'items', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION'])
            // One row per user+item. This both enforces that rule and gives us a
            // fast lookup of "does this user own this item?".
            ->addIndex(['user_id', 'item_id'], ['unique' => true])
            ->create();
    }
}
