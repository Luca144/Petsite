<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * One creature species — a KIND of creature, as loaded from the database.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: a plain, read-only value object describing a species (e.g.
 * "Foxlen"). Species are content stored as data, so adding a new one is a new row
 * in the species table, not new code (build plan section 2).
 *
 * The species' animated images are NOT stored here. They are found by convention:
 * public/assets/creatures/{slug}/{stage}.gif — for example, for a "foxlen" at the
 * "baby" stage, public/assets/creatures/foxlen/baby.gif. This keeps the data
 * simple: add a species row and drop its images into a folder named after its slug.
 */
final class Species
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $flavourText,
        public readonly bool $isStarter,
        /** Can a player come to own one of these at all? */
        public readonly bool $isAdoptable,
        /** What one costs in the shop. */
        public readonly int $gemPrice = 0,
        /** The item this species adores, if it has one. */
        public readonly ?int $favouriteItemId = null,
        /**
         * The item this species would rather not, if it has one.
         *
         * A DISLIKE NEVER TAKES ANYTHING AWAY. The creature eats it, gets a little
         * happier, and makes a face — the face is the whole point. Nothing here can
         * leave a creature worse off than before it was fed.
         */
        public readonly ?int $dislikedItemId = null,
    ) {
    }

    /**
     * Does this species adore that item?
     */
    public function adores(int $itemId): bool
    {
        return $this->favouriteItemId !== null && $this->favouriteItemId === $itemId;
    }

    /**
     * Would this species rather not have that item?
     */
    public function dislikes(int $itemId): bool
    {
        return $this->dislikedItemId !== null && $this->dislikedItemId === $itemId;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['slug'],
            $row['name'],
            $row['flavour_text'] ?? null,
            // The database stores booleans as 0/1; cast them back to real bools.
            (bool) $row['is_starter'],
            (bool) $row['is_adoptable'],
            (int) ($row['gem_price'] ?? 0),
            isset($row['favourite_item_id']) ? (int) $row['favourite_item_id'] : null,
            isset($row['disliked_item_id']) ? (int) $row['disliked_item_id'] : null,
        );
    }

    /**
     * The picture of one of these at a given life stage.
     *
     * Built from the slug by convention, exactly as item art is, so adding a
     * species never means typing a path.
     */
    public function imagePathFor(string $stage): string
    {
        return '/assets/creatures/' . rawurlencode($this->slug) . '/' . rawurlencode($stage) . '.gif';
    }
}
