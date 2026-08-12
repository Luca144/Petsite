<?php

declare(strict_types=1);

namespace Felkyo\Economy;

/**
 * One item definition — a kind of thing that can be owned or sold.
 *
 * @package Felkyo\Economy
 *
 * WHAT THIS IS: a plain, read-only value object describing an item (its name,
 * what it costs, what it sells for, and its "type" grouping). Items are content
 * stored as data, so adding a new item is a new row in the items table, not new
 * code. The inventory and shop repositories build these from database rows.
 *
 * THE TWO NUMBERS: "price" is what a shop charges for one; "sellValue" is what a
 * player gets back for parting with one. They are deliberately separate — see the
 * long explanation in the migration that added sell_value, and docs/owned-things.md.
 *
 * WHERE AN ITEM'S PICTURE LIVES: nowhere in this object. Like creature art, item
 * art is found by convention from the slug — public/assets/items/{slug}.png — so
 * adding an item does not mean typing a file path. (When the panel gains uploads
 * in M2.2 this becomes a stored column, because uploaded files must be saved
 * under a generated name for safety, and a generated name cannot be guessed.)
 */
final class Item
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $price,
        public readonly int $sellValue,
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
            (int) $row['sell_value'],
            $row['type'],
        );
    }

    /**
     * Can a player sell this at all?
     *
     * A sell value of 0 means "nobody would buy this", which is different from
     * "this sells for nothing". We keep the difference because it changes what the
     * site can say to a player: an unsellable item can be explained ("no shop
     * around here wants one of these") instead of showing a sell button that
     * takes the item and hands back nothing. Golden rule 3 — never a dead end.
     */
    public function isSellable(): bool
    {
        return $this->sellValue > 0;
    }
}
