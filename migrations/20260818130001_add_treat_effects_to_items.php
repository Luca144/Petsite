<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Gives items the power to cheer a creature up (increment M2).
 *
 * @package Felkyo\Migrations
 *
 * WHY TWO COLUMNS AND NOT A "is_treat" FLAG. What makes something feedable is
 * that eating it DOES something, and the two numbers say what. An item with both
 * at zero simply is not food, which needs no separate flag to be true — and a
 * flag that could disagree with the effects (edible, but does nothing) would be a
 * state somebody has to think about. There is no such state now.
 *
 * This also keeps treats as CONTENT rather than code. Adding a new one is a row,
 * and inventing a whole new kind of food — something purely restful, something
 * purely cheering — is two numbers, not a new class. The artist can price and
 * balance the pantry without a developer.
 *
 * THE ONE RULE TO RESPECT WHEN ADDING TREATS: a treat must not be worth more fed
 * than it costs. Nothing enforces that in the schema because the two are measured
 * in different things (gems against happiness), but a treat that is trivially
 * cheap and hugely effective makes the mood system pointless — you would simply
 * buy contentment. Keep them small; the point is the visit, not the arithmetic.
 */
final class AddTreatEffectsToItems extends AbstractMigration
{
    public function up(): void
    {
        $this->table('items')
            // UNSIGNED, like every other effect number here: a treat can only ever
            // be kind. There is deliberately no way to express an item that makes
            // a creature less happy — that is not a mechanic this game has.
            ->addColumn('happiness_bonus', 'integer', [
                'signed' => false,
                'default' => 0,
                'null' => false,
                'after' => 'sell_value',
            ])
            ->addColumn('energy_bonus', 'integer', [
                'signed' => false,
                'default' => 0,
                'null' => false,
                'after' => 'happiness_bonus',
            ])
            ->update();

        // The two treats that already existed become actually edible. Their
        // numbers are PLACEHOLDERS in the same sense their prices are — the
        // artist owns them, and once the panel exists this is an edit, not a
        // migration.
        //
        // Honey is the comforting one and acorn the small everyday one, which is
        // why honey costs more and does more. Matched by slug rather than id
        // because ids differ between a fresh install and a migrated one.
        $this->execute(
            "UPDATE items SET happiness_bonus = 10, energy_bonus = 20 WHERE slug = 'honey-treat'"
        );
        $this->execute(
            "UPDATE items SET happiness_bonus = 6, energy_bonus = 10 WHERE slug = 'acorn-treat'"
        );

        // A third treat, so that choosing one is a choice. Two treats that differ
        // only in size is a number; three that differ in KIND is a small decision,
        // and a small decision is the difference between feeding a stat and
        // looking after a creature. This one does nothing for happiness and a
        // great deal for rest — the thing to reach for when a creature is dozing.
        $this->execute(
            "INSERT INTO items (slug, name, description, price, sell_value, happiness_bonus, energy_bonus, category_id)
             SELECT 'chamomile-bundle',
                    'Chamomile Bundle',
                    'A little bundle of dried flowers. Sleepy creatures perk up after one.',
                    14, 7, 0, 50,
                    item_categories.id
               FROM item_categories
              WHERE item_categories.slug = 'ingredient'"
        );

        // Put it on the shelf of the shop that sells the others.
        $this->execute(
            "INSERT INTO shop_items (shop_id, item_id)
             SELECT shops.id, items.id
               FROM shops
               JOIN items ON items.slug = 'chamomile-bundle'
              WHERE shops.slug = 'general-store'"
        );
    }

    public function down(): void
    {
        $this->execute(
            "DELETE shop_items FROM shop_items
               JOIN items ON items.id = shop_items.item_id
              WHERE items.slug = 'chamomile-bundle'"
        );
        // Inventory rows referencing it go too, or the delete below is refused.
        $this->execute(
            "DELETE inventory FROM inventory
               JOIN items ON items.id = inventory.item_id
              WHERE items.slug = 'chamomile-bundle'"
        );
        $this->execute("DELETE FROM items WHERE slug = 'chamomile-bundle'");

        $this->table('items')
            ->removeColumn('happiness_bonus')
            ->removeColumn('energy_bonus')
            ->update();
    }
}
