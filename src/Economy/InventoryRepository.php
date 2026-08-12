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
 * items table so callers get whole OwnedItemStack objects — the item itself, plus
 * how many of it the player has.
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
    public function __construct(private PDO $connection)
    {
    }

    /**
     * Get everything a user owns: each item they hold and how many. Ordered by
     * item type then name, so the inventory page can group them tidily.
     *
     * @return array<int, OwnedItemStack>
     */
    public function findForUser(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT items.id, items.slug, items.name, items.description,
                    items.price, items.sell_value, items.type,
                    inventory.quantity
               FROM inventory
               JOIN items ON items.id = inventory.item_id
              WHERE inventory.user_id = :user_id
              ORDER BY items.type, items.name'
        );
        $statement->execute([':user_id' => $userId]);

        $owned = [];
        foreach ($statement->fetchAll() as $row) {
            $owned[] = new OwnedItemStack(Item::fromRow($row), (int) $row['quantity']);
        }

        return $owned;
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
}
