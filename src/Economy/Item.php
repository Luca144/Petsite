<?php

declare(strict_types=1);

namespace Felkyo\Economy;

/**
 * One item definition — a kind of thing that can be owned or sold.
 *
 * @package Felkyo\Economy
 *
 * WHAT THIS IS: a plain, read-only value object describing an item (its name,
 * what it costs, what it sells for, and which category it belongs to). Items
 * are content stored as data; since M2.4 they are BUILT in the creator's
 * panel — drafted, given words, numbers and an icon, published.
 *
 * THE TWO NUMBERS: "price" is what a shop charges for one; "sellValue" is what a
 * player gets back for parting with one. They are deliberately separate, and the
 * second must always stay meaningfully below the first — see docs/owned-things.md
 * rule 3, and the shop margin in config (checked at SAVE since M2.4, not only
 * by the test suite).
 *
 * WHERE AN ITEM'S PICTURE COMES FROM — one method, three sources, in order:
 * iconUrl() returns the art-store picture if one is linked (content-hash URL,
 * so replacing it defeats every cache), else the pre-panel convention path
 * (public/assets/items/{slug}.png — templates check the file really exists),
 * and when neither exists the templates fall back to the category's icon, as
 * they always have. Nothing existing looks different until an icon is chosen.
 *
 * DRAFT vs PUBLISHED: a draft item is on no shelf and cannot be bought, but
 * everything a player already OWNS keeps working — inventory, feeding,
 * selling read items unfiltered. The same asymmetry species have.
 */
final class Item
{
    // The two states an item can be in (same shape as Species).
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $price,
        public readonly int $sellValue,
        public readonly ItemCategory $category,
        /** How much happier a creature is for eating this. 0 means it is not food. */
        public readonly int $happinessBonus = 0,
        /** How much more rested a creature is for eating this. */
        public readonly int $energyBonus = 0,
        public readonly string $status = self::STATUS_PUBLISHED,
        /** The icon's stored (content-hash) name in the art store, or null. */
        public readonly ?string $iconArtName = null,
        public readonly ?int $iconArtId = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    /**
     * Build an item from a row that has been joined to its category.
     *
     * The category's columns arrive under "category_" names (category_slug, and
     * so on) because both tables have a "name" and an "id" — without the prefixes
     * one would quietly overwrite the other, and an item would take the
     * category's name as its own. The icon columns are optional so queries
     * written before M2.4 keep producing usable items.
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
            (int) ($row['happiness_bonus'] ?? 0),
            (int) ($row['energy_bonus'] ?? 0),
            (string) ($row['status'] ?? self::STATUS_PUBLISHED),
            $row['icon_art_name'] ?? null,
            isset($row['icon_art_id']) ? (int) $row['icon_art_id'] : null,
            $row['updated_at'] ?? null,
        );
    }

    /**
     * Can a creature eat this?
     *
     * There is no "is a treat" column, and there should not be: what makes
     * something food is that eating it does something. An item whose effects are
     * both zero simply is not food, and there is no way for a flag to disagree
     * with the effects because there is no flag.
     */
    public function isTreat(): bool
    {
        return $this->happinessBonus > 0 || $this->energyBonus > 0;
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

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Is the icon an uploaded art-store picture (as opposed to the
     * convention path below, whose existence templates check on disk)?
     */
    public function hasStoredIcon(): bool
    {
        return $this->iconArtName !== null && $this->iconArtName !== '';
    }

    /**
     * THE icon address (see the class note for the three sources).
     */
    public function iconUrl(): string
    {
        if ($this->hasStoredIcon()) {
            return '/art/' . $this->iconArtName;
        }

        return $this->conventionPath();
    }

    /**
     * The pre-panel convention path. Public because the editor says honestly
     * which source an item is using.
     */
    public function conventionPath(): string
    {
        return '/assets/items/' . rawurlencode($this->slug) . '.png';
    }

    /**
     * The old name for the convention path, kept so templates written before
     * M2.4 keep working until each is moved to iconUrl().
     */
    public function imagePath(): string
    {
        return $this->conventionPath();
    }
}
