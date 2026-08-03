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
        public readonly bool $isAdoptable,
    ) {
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
        );
    }
}
