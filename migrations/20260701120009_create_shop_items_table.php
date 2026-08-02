<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the "shop_items" table — which items each shop offers for sale.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS TABLE EXISTS: it connects shops to items (a "join table"). One row
 * means "this shop sells this item". The item's price lives on the item itself
 * (in the items table), so listing a shop's stock is a simple join. Putting the
 * offering here — rather than a "shop_id" column on items — is what lets the same
 * item be sold by more than one shop in future without duplicating the item.
 */
final class CreateShopItemsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('shop_items', ['signed' => false]);

        $table
            ->addColumn('shop_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('item_id', 'integer', ['signed' => false, 'null' => false])
            // If a shop is deleted, its offerings go with it.
            ->addForeignKey('shop_id', 'shops', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            // An item that is on sale somewhere cannot be deleted (RESTRICT).
            ->addForeignKey('item_id', 'items', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION'])
            // A shop offers a given item at most once.
            ->addIndex(['shop_id', 'item_id'], ['unique' => true])
            ->create();
    }
}
