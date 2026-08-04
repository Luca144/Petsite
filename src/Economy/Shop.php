<?php

declare(strict_types=1);

namespace Felkyo\Economy;

/**
 * One shop — a place that sells items for currency.
 *
 * @package Felkyo\Economy
 *
 * A plain, read-only value object. There is one shop for now, but it lives as
 * data (a row) so adding another later is a data change, not new code.
 */
final class Shop
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $description,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['slug'],
            $row['name'],
            $row['description'] ?? null,
        );
    }
}
