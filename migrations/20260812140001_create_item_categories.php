<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates "item_categories" and moves items onto it (increment M1.2).
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS EXISTS: until now an item's kind was a free-text "type" column holding
 * whatever string somebody typed — "sticker", "treat". That was fine while there
 * were four items. It stops being fine the moment the kind has to carry a colour,
 * an icon and a label, because free text cannot be relied on: one stray "Sticker"
 * or "stickers" and an item quietly falls out of its own group with nothing to
 * warn you.
 *
 * A category is now a real row that items point at. The database refuses an item
 * in a category that does not exist, the artist edits categories in one place
 * (M2.4), and everything that shows an item reads its look from the same source.
 *
 * WHERE THE CATEGORIES CAME FROM: the artist's design document
 * (docs/design/felkyo-site-items.md), not from invention. It already sorts things
 * into ingredients, cooked dishes, potions, stickers, gathered materials, tools
 * that unlock activities, badges, and seeds.
 *
 * WHY THE COLOUR IS A TOKEN NAME AND NOT A COLOUR: CLAUDE.md section 8 is firm
 * that no component invents colour values — every colour lives in the theme file
 * as a named custom property. So a category stores the NAME of a theme token
 * ("--category-dish") rather than a hex value. That keeps one palette, lets the
 * selectable themes of M4 restyle every category at once, and means the contrast
 * checking built in M4.2 has a single place to check.
 */
final class CreateItemCategories extends AbstractMigration
{
    public function up(): void
    {
        $this->table('item_categories', ['signed' => false])
            // Machine name, used in CSS hooks and in the "how to add one" recipe.
            ->addColumn('slug', 'string', ['limit' => 40, 'null' => false])
            // What a player reads on the card. Never only a colour — CLAUDE.md
            // requires that colour is never the sole carrier of meaning, so every
            // category shows this word next to its tint and its icon.
            ->addColumn('name', 'string', ['limit' => 40, 'null' => false])
            // The name of a theme token, e.g. "--category-dish". See the docblock.
            ->addColumn('colour_token', 'string', ['limit' => 60, 'null' => false])
            // Which drawing to show. Icons live in one small inline SVG sprite so
            // the site adds no icon library and nothing extra is downloaded.
            ->addColumn('icon_key', 'string', ['limit' => 40, 'null' => false])
            // The order categories appear in. Kept as data so the artist can
            // reorder the inventory without a developer.
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        // The eight categories, read off the artist's design document. These are
        // CONTENT: changing a name, colour, icon or order is a data edit, and from
        // M2.4 it is a panel edit. Adding a ninth is one row.
        $this->table('item_categories')->insert([
            ['slug' => 'ingredient', 'name' => 'Ingredient', 'colour_token' => '--category-ingredient', 'icon_key' => 'leaf', 'sort_order' => 1],
            ['slug' => 'dish', 'name' => 'Dish', 'colour_token' => '--category-dish', 'icon_key' => 'bowl', 'sort_order' => 2],
            ['slug' => 'potion', 'name' => 'Potion', 'colour_token' => '--category-potion', 'icon_key' => 'flask', 'sort_order' => 3],
            ['slug' => 'material', 'name' => 'Material', 'colour_token' => '--category-material', 'icon_key' => 'gem', 'sort_order' => 4],
            ['slug' => 'seed', 'name' => 'Seed', 'colour_token' => '--category-seed', 'icon_key' => 'sprout', 'sort_order' => 5],
            ['slug' => 'tool', 'name' => 'Tool', 'colour_token' => '--category-tool', 'icon_key' => 'tool', 'sort_order' => 6],
            ['slug' => 'sticker', 'name' => 'Sticker', 'colour_token' => '--category-sticker', 'icon_key' => 'star', 'sort_order' => 7],
            ['slug' => 'badge', 'name' => 'Badge', 'colour_token' => '--category-badge', 'icon_key' => 'shield', 'sort_order' => 8],
        ])->saveData();

        // Point items at their category. Added as NULL first, because the existing
        // rows have no category yet and the database would refuse a NOT NULL column
        // with nothing to put in it.
        $this->table('items')
            ->addColumn('category_id', 'integer', ['signed' => false, 'null' => true, 'after' => 'sell_value'])
            ->update();

        // Move the old free-text types across. "treat" becomes a dish, which is
        // what the design document calls cooked food.
        $this->execute(
            "UPDATE items
                JOIN item_categories ON item_categories.slug = CASE items.type
                    WHEN 'treat' THEN 'dish'
                    ELSE items.type
                END
                SET items.category_id = item_categories.id"
        );

        // Anything the mapping above did not recognise would still be NULL, and
        // making the column required would fail loudly rather than silently
        // leaving orphans. Park stragglers in "material" so the migration is safe
        // to run on a database that grew a type we did not anticipate.
        $this->execute(
            "UPDATE items
                JOIN item_categories ON item_categories.slug = 'material'
                SET items.category_id = item_categories.id
              WHERE items.category_id IS NULL"
        );

        // Now every row has one, the column can become required and get its
        // foreign key. RESTRICT: a category still in use cannot be deleted, so an
        // item can never point at a category that has gone.
        $this->table('items')
            ->changeColumn('category_id', 'integer', ['signed' => false, 'null' => false])
            ->addForeignKey('category_id', 'item_categories', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION'])
            ->update();

        // The old column goes. Keeping both would leave two answers to "what kind
        // of thing is this?", and they would drift apart the first time somebody
        // updated one and forgot the other.
        $this->table('items')->removeIndex(['type'])->removeColumn('type')->update();
    }

    public function down(): void
    {
        // Put the free-text column back and refill it from the category slugs, so
        // stepping backwards leaves a working database rather than a broken one.
        $this->table('items')->addColumn('type', 'string', ['limit' => 30, 'null' => true])->update();
        $this->execute(
            'UPDATE items JOIN item_categories ON item_categories.id = items.category_id
                SET items.type = item_categories.slug'
        );
        $this->table('items')->changeColumn('type', 'string', ['limit' => 30, 'null' => false])->addIndex(['type'])->update();

        $this->table('items')->dropForeignKey('category_id')->removeColumn('category_id')->update();
        $this->table('item_categories')->drop()->save();
    }
}
