<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the "items" table — the definitions of things that can be owned/sold.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS TABLE EXISTS: items are CONTENT (build plan section 2). This table
 * defines what an item IS — its name, description, price and type. Adding a new
 * item is inserting a row here, not writing code. Which users OWN items is a
 * separate table (inventory); which shop SELLS them is another (shop_items).
 *
 * The "type" is a plain text label (e.g. "sticker", "food") so the inventory page
 * can group items by type and new types are just new values — no schema change.
 */
final class CreateItemsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('items', ['signed' => false]);

        $table
            // Short machine-friendly identifier, e.g. "gold-star-sticker".
            ->addColumn('slug', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 60, 'null' => false])
            // Genuinely optional, so NULL is allowed.
            ->addColumn('description', 'text', ['null' => true])
            // The cost in the single in-game currency. UNSIGNED — never negative.
            ->addColumn('price', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            // A grouping label used by the inventory page (increment B.8).
            ->addColumn('type', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            // Each slug identifies exactly one item.
            ->addIndex(['slug'], ['unique' => true])
            // We group the inventory by type, so index it for quick grouping.
            ->addIndex(['type'])
            ->create();
    }
}
