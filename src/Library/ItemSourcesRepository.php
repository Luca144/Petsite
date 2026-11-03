<?php

declare(strict_types=1);

namespace Felkyo\Library;

use PDO;

/**
 * Where an item comes from — for its page in the library.
 *
 * @package Felkyo\Library
 *
 * Skerro asked (2026-10-13) for item, fish and plant names to link somewhere
 * that answers "where do I get this?". This class answers it from the data
 * that already decides it: the shop shelves (shop_items) and the areas' loot
 * tables (loot_entries). Two small queries; only PUBLISHED shops and areas
 * are mentioned, so a draft area stays the artist's secret.
 */
final class ItemSourcesRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return string[] names of published shops that sell this item */
    public function shopsSelling(int $itemId): array
    {
        $statement = $this->connection->prepare(
            "SELECT DISTINCT s.name FROM shop_items si JOIN shops s ON s.id = si.shop_id
              WHERE si.item_id = :item AND s.status = 'published' ORDER BY s.name"
        );
        $statement->execute([':item' => $itemId]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Published areas whose loot table can turn this item up.
     *
     * @return array<int, array{slug: string, name: string}>
     */
    public function areasDropping(int $itemId): array
    {
        $statement = $this->connection->prepare(
            "SELECT DISTINCT a.slug, a.name FROM loot_entries l JOIN areas a ON a.id = l.area_id
              WHERE l.item_id = :item AND a.status = 'published' ORDER BY a.name"
        );
        $statement->execute([':item' => $itemId]);

        return array_map(
            static fn (array $row): array => ['slug' => (string) $row['slug'], 'name' => (string) $row['name']],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
