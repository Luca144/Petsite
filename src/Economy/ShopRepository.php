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
            'SELECT items.id, items.slug, items.name, items.description, items.price, items.sell_value, items.type
               FROM shop_items
               JOIN items ON items.id = shop_items.item_id
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
            'SELECT items.id, items.slug, items.name, items.description, items.price, items.sell_value, items.type
               FROM shop_items
               JOIN items ON items.id = shop_items.item_id
              WHERE shop_items.shop_id = :shop_id AND shop_items.item_id = :item_id
              LIMIT 1'
        );
        $statement->execute([':shop_id' => $shopId, ':item_id' => $itemId]);

        $row = $statement->fetch();

        return is_array($row) ? Item::fromRow($row) : null;
    }
}
