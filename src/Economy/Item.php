<?php

declare(strict_types=1);

namespace Felkyo\Economy;

/**
 * One item definition — a kind of thing that can be owned or sold.
 *
 * @package Felkyo\Economy
 *
 * WHAT THIS IS: a plain, read-only value object describing an item (its name,
 * price, and "type" grouping). Items are content stored as data, so adding a new
 * item is a new row in the items table, not new code. The inventory and shop
 * repositories build these from database rows.
 */
final class Item
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $price,
        public readonly string $type,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['slug'],
            $row['name'],
            $row['description'] ?? null,
            (int) $row['price'],
            $row['type'],
        );
    }
}
