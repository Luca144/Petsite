<?php

declare(strict_types=1);

namespace Felkyo\Exploration;

use Felkyo\Economy\InventoryRepository;
use PDO;

/**
 * Puts an item found while exploring into the finder's satchel.
 *
 * @package Felkyo\Exploration
 *
 * WHY THIS IS ITS OWN SMALL CLASS. ExplorationService knows about areas, clicks
 * and dice; it should not also know how items are looked up. This is the one
 * piece that turns "the loot table said honey-treat" into a row in somebody's
 * inventory, and keeping it separate means the exploration rules stay readable
 * and this can be reused the next time something grants an item.
 *
 * IT TAKES A SLUG, NOT AN ID, and that is deliberate. Ids differ between a fresh
 * install and a migrated one, so an id written into config would mean one thing
 * on the developer's machine and something else on the live site — the kind of
 * mistake that hands out the wrong prize and is very hard to see. A slug is a
 * stable, readable handle, and the artist can write one without asking anybody.
 */
final class ItemRewardGranter
{
    public function __construct(
        private PDO $connection,
        private InventoryRepository $inventory,
    ) {
    }

    /**
     * Give the player one of the item with this slug, and return its NAME so the
     * message can say what was found. Returns null when no such item exists.
     *
     * A missing slug is a content mistake — a typo in an area's loot table — and
     * it must not take the page down. The caller turns null into a gentle "it
     * slipped away", which is a sentence, not an error, and the player loses
     * nothing but a click they were spending anyway.
     */
    public function grant(int $userId, string $slug): ?string
    {
        if ($slug === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            'SELECT id, name FROM items WHERE slug = :slug LIMIT 1'
        );
        $statement->execute([':slug' => $slug]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        $this->inventory->addItem($userId, (int) $row['id']);

        return $row['name'];
    }
}
