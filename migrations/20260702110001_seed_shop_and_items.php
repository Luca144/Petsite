<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seeds the first shop and the items it sells.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS EXISTS: the shop needs items to sell, and items are content stored as
 * data. This inserts a handful of starter items, one shop, and the links saying
 * the shop sells them — so the economy works out of the box (and the tests have
 * something to buy).
 *
 * PLACEHOLDER CONTENT: the item names/prices and the shop name are placeholders.
 * The Product Owner can change them — it is a data change. To add an item to the
 * shop: add a row to items, then a shop_items row linking it to a shop. No code.
 *
 * We use up()/down() because Phinx cannot auto-reverse inserted data.
 */
final class SeedShopAndItems extends AbstractMigration
{
    public function up(): void
    {
        // A few starter items, grouped by "type" (stickers and treats).
        $this->table('items')->insert([
            ['slug' => 'gold-star-sticker', 'name' => 'Gold Star Sticker', 'description' => 'A shining little star to show off.', 'price' => 20, 'type' => 'sticker'],
            ['slug' => 'autumn-leaf-sticker', 'name' => 'Autumn Leaf Sticker', 'description' => 'A crisp painted leaf.', 'price' => 15, 'type' => 'sticker'],
            ['slug' => 'honey-treat', 'name' => 'Honey Treat', 'description' => 'A sweet golden treat.', 'price' => 10, 'type' => 'treat'],
            ['slug' => 'acorn-treat', 'name' => 'Acorn Treat', 'description' => 'A crunchy little acorn.', 'price' => 8, 'type' => 'treat'],
        ])->saveData();

        // The one shop.
        $this->table('shops')->insert([
            ['slug' => 'general-store', 'name' => 'The Village Store', 'description' => 'A cosy little shop with a warm lamp in the window.'],
        ])->saveData();

        // Link every seeded item to the shop. We match rows by their slugs (rather
        // than guessing ids) so this works whatever ids the inserts happened to get.
        $this->execute(
            "INSERT INTO shop_items (shop_id, item_id)
             SELECT shops.id, items.id
               FROM shops
               JOIN items ON items.slug IN
                    ('gold-star-sticker', 'autumn-leaf-sticker', 'honey-treat', 'acorn-treat')
              WHERE shops.slug = 'general-store'"
        );
    }

    public function down(): void
    {
        // Remove the links first (they reference shops and items), then the rest.
        $this->execute(
            "DELETE shop_items FROM shop_items
               JOIN shops ON shops.id = shop_items.shop_id
              WHERE shops.slug = 'general-store'"
        );
        $this->execute("DELETE FROM shops WHERE slug = 'general-store'");
        $this->execute(
            "DELETE FROM items WHERE slug IN
             ('gold-star-sticker', 'autumn-leaf-sticker', 'honey-treat', 'acorn-treat')"
        );
    }
}
