<?php

declare(strict_types=1);

namespace Felkyo\Economy;

use PDO;

/**
 * Reads and writes what each user owns (their inventory).
 *
 * @package Felkyo\Economy
 *
 * WHAT THIS IS: the only place that runs SQL for the inventory table. Inventory
 * rows connect a user to an item, with a quantity. This repository joins to the
 * items table and on to item_categories, so callers get whole OwnedItemStack
 * objects — the item, its category, and how many of it the player has.
 *
 * A RULE THIS CLASS FOLLOWS, AND WHY (the owned-thing model, M1.1): every method
 * that reads or changes something a player owns takes the acting player's id and
 * puts it in the WHERE clause. We never fetch a row by its id and then check the
 * owner in PHP afterwards. Both approaches work when written carefully — but the
 * second one is a single forgotten "if" away from letting somebody sell another
 * player's things by changing a number in a form, and a missing "if" is invisible
 * when you read the code. Putting the owner in the query means the database
 * refuses, whatever the calling code forgets. See docs/owned-things.md.
 */
final class InventoryRepository
{
    /**
     * The columns needed to build a whole Item with its category attached.
     * The category's own columns are renamed because both tables have an "id"
     * and a "name", and in a joined row the second silently replaces the first.
     * Listed one by one — never SELECT * (CLAUDE.md section 5).
     */
    private const ITEM_COLUMNS =
        'items.id, items.slug, items.name, items.description, items.price, items.sell_value,
         item_categories.id AS category_id,
         item_categories.slug AS category_slug,
         item_categories.name AS category_name,
         item_categories.colour_token AS category_colour_token,
         item_categories.icon_key AS category_icon_key,
         item_categories.sort_order AS category_sort_order';

    public function __construct(private PDO $connection)
    {
    }

    /**
     * Get everything a user owns: each item they hold and how many.
     *
     * Ordered by the category's own sort_order, then by name — so the artist
     * decides what appears first by editing data, not by anyone editing code.
     *
     * @return array<int, OwnedItemStack>
     */
    public function findForUser(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::ITEM_COLUMNS . ', inventory.quantity
               FROM inventory
               JOIN items ON items.id = inventory.item_id
               JOIN item_categories ON item_categories.id = items.category_id
              WHERE inventory.user_id = :user_id
              ORDER BY item_categories.sort_order, items.name'
        );
        $statement->execute([':user_id' => $userId]);

        $owned = [];
        foreach ($statement->fetchAll() as $row) {
            $owned[] = new OwnedItemStack(Item::fromRow($row), (int) $row['quantity']);
        }

        return $owned;
    }

    /**
     * Find one pile this user owns, or null if they do not own any.
     *
     * The user id is part of the lookup rather than something checked afterwards,
     * so "show me item 7" from somebody who does not own item 7 simply finds
     * nothing. There is no version of this method that can be called unsafely.
     */
    public function findStackForUser(int $userId, int $itemId): ?OwnedItemStack
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::ITEM_COLUMNS . ', inventory.quantity
               FROM inventory
               JOIN items ON items.id = inventory.item_id
               JOIN item_categories ON item_categories.id = items.category_id
              WHERE inventory.user_id = :user_id AND inventory.item_id = :item_id
              LIMIT 1'
        );
        $statement->execute([':user_id' => $userId, ':item_id' => $itemId]);

        $row = $statement->fetch();

        return is_array($row) ? new OwnedItemStack(Item::fromRow($row), (int) $row['quantity']) : null;
    }

    /**
     * Give a user one of an item. If they already own some, the quantity goes up
     * by one; otherwise a new row is created. The unique (user_id, item_id) index
     * makes this "insert or add one" safe.
     */
    public function addItem(int $userId, int $itemId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO inventory (user_id, item_id, quantity)
             VALUES (:user_id, :item_id, 1)
             ON DUPLICATE KEY UPDATE quantity = quantity + 1'
        );
        $statement->execute([':user_id' => $userId, ':item_id' => $itemId]);
    }

    /**
     * Take exactly one of an item away from a user. Returns true only if one was
     * really taken.
     *
     * THIS METHOD IS THE WHOLE DEFENCE against being paid twice for one treat, so
     * it is worth reading slowly.
     *
     * The condition "quantity >= 1" lives in the WHERE clause, not in PHP above
     * it. That matters because two requests can arrive at the same instant — a
     * double-tapped button, an impatient refresh, or somebody deliberately firing
     * the same request twice to see what happens. If we checked the quantity in
     * PHP and then subtracted, both requests could read "1 left", both could
     * decide it was fine, and both could pay out. The database would end up at -1
     * and the player would have been paid twice for one item.
     *
     * Written this way, the two requests queue up inside the database. The first
     * one matches a row and changes it. The second finds nothing left to match,
     * changes nothing, and gets false back — so the caller knows not to pay.
     *
     * The return value is therefore not a courtesy. Ignoring it re-opens the hole.
     */
    public function removeOne(int $userId, int $itemId): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE inventory
                SET quantity = quantity - 1
              WHERE user_id = :user_id AND item_id = :item_id AND quantity >= 1'
        );
        $statement->execute([':user_id' => $userId, ':item_id' => $itemId]);

        if ($statement->rowCount() !== 1) {
            return false;
        }

        // An empty pile is not a pile. Clearing the row out keeps the inventory
        // honest, so nothing shows "Honey Treat ×0" or offers to sell it.
        $tidy = $this->connection->prepare(
            'DELETE FROM inventory WHERE user_id = :user_id AND item_id = :item_id AND quantity = 0'
        );
        $tidy->execute([':user_id' => $userId, ':item_id' => $itemId]);

        return true;
    }
}
