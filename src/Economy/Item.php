<?php

declare(strict_types=1);

namespace Felkyo\Economy;

/**
 * One item definition — a kind of thing that can be owned or sold.
 *
 * @package Felkyo\Economy
 *
 * WHAT THIS IS: a plain, read-only value object describing an item (its name,
 * what it costs, what it sells for, and which category it belongs to). Items are
 * content stored as data, so adding a new item is a new row in the items table,
 * not new code. The inventory and shop repositories build these from database rows.
 *
 * THE TWO NUMBERS: "price" is what a shop charges for one; "sellValue" is what a
 * player gets back for parting with one. They are deliberately separate, and the
 * second must always stay meaningfully below the first — see docs/owned-things.md
 * rule 3, and the shop margin in config.
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
        public readonly ItemCategory $category,
    ) {
    }

    /**
     * Build an item from a row that has been joined to its category.
     *
     * The category's columns arrive under "category_" names (category_slug, and
     * so on) because both tables have a "name" and an "id" — without the prefixes
     * one would quietly overwrite the other, and an item would take the
     * category's name as its own.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['slug'],
            $row['name'],
            $row['description'] ?? null,
            (int) $row['price'],
            (int) $row['sell_value'],
            new ItemCategory(
                (int) $row['category_id'],
                $row['category_slug'],
                $row['category_name'],
                $row['category_colour_token'],
                $row['category_icon_key'],
                (int) $row['category_sort_order'],
            ),
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

    /**
     * Where this item's picture lives. Built from the slug by convention, exactly
     * as creature art is, so adding an item never means typing a path.
     */
    public function imagePath(): string
    {
        return '/assets/items/' . rawurlencode($this->slug) . '.png';
    }
}
