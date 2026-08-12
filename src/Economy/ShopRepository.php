<?php

declare(strict_types=1);

namespace Felkyo\Economy;

use PDO;

/**
 * Reads shops and the items they sell.
 *
 * @package Felkyo\Economy
 *
 * WHAT THIS IS: the only place that runs SQL for the shops and shop_items tables.
 * A shop's stock is found by joining shop_items to items (the price lives on the
 * item). Because "which shop sells which item" is a table, the same item could be
 * sold by more than one shop in future without any code change.
 */
final class ShopRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * The columns every query here needs in order to build a whole Item.
     *
     * WHY IT IS WRITTEN ONCE: an item is only complete with its category, so
     * every query joins the two tables and needs the same twelve columns. Writing
     * them out three times invites the day somebody adds a column to two of the
     * three and spends an afternoon on the resulting "undefined array key".
     *
     * WHY THE CATEGORY COLUMNS ARE RENAMED: both tables have "id" and "name". In
     * a joined row the second silently replaces the first, so without these
     * prefixes every item would be called "Dish" and carry its category's id.
     *
     * Columns are still listed one by one — never SELECT * (CLAUDE.md section 5).
     */
    private const ITEM_COLUMNS =
        'items.id, items.slug, items.name, items.description, items.price, items.sell_value,
         item_categories.id AS category_id,
         item_categories.slug AS category_slug,
         item_categories.name AS category_name,
         item_categories.colour_token AS category_colour_token,
         item_categories.icon_key AS category_icon_key,
         item_categories.sort_order AS category_sort_order';

    /**
     * Find a shop by its slug (e.g. "general-store"), or null if there is none.
     */
    public function findBySlug(string $slug): ?Shop
    {
        $statement = $this->connection->prepare(
            'SELECT id, slug, name, description FROM shops WHERE slug = :slug LIMIT 1'
        );
        $statement->execute([':slug' => $slug]);

        $row = $statement->fetch();

        return is_array($row) ? Shop::fromRow($row) : null;
    }

    /**
     * Get all the items a shop sells, cheapest first.
     *
     * @return Item[]
     */
    public function findItems(int $shopId): array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::ITEM_COLUMNS . '
               FROM shop_items
               JOIN items ON items.id = shop_items.item_id
               JOIN item_categories ON item_categories.id = items.category_id
              WHERE shop_items.shop_id = :shop_id
              ORDER BY items.price'
        );
        $statement->execute([':shop_id' => $shopId]);

        return array_map(
            static fn (array $row): Item => Item::fromRow($row),
            $statement->fetchAll()
        );
    }

    /**
     * Find a single item ONLY IF this shop actually sells it, or null otherwise.
     * The purchase flow uses this so a player can only buy what is really on sale
     * here (and gets the real, current price from the database, not the browser).
     */
    public function findSoldItem(int $shopId, int $itemId): ?Item
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::ITEM_COLUMNS . '
               FROM shop_items
               JOIN items ON items.id = shop_items.item_id
               JOIN item_categories ON item_categories.id = items.category_id
              WHERE shop_items.shop_id = :shop_id AND shop_items.item_id = :item_id
              LIMIT 1'
        );
        $statement->execute([':shop_id' => $shopId, ':item_id' => $itemId]);

        $row = $statement->fetch();

        return is_array($row) ? Item::fromRow($row) : null;
    }
}
