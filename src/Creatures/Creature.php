<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * One creature, as loaded from the database.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: a plain, read-only value object for a single creature owned by a
 * player. It carries the creature's own state; its life stage and level are NOT
 * stored here — they are worked out from `xp` by GrowthCalculator, so there is a
 * single source of truth for growth (see the schema notes).
 */
final class Creature
{
    public function __construct(
        public readonly int $id,
        public readonly int $ownerId,
        public readonly int $speciesId,
        public readonly string $name,
        public readonly int $xp,
        public readonly int $happiness,
        public readonly ?string $bio,
        // When a report hid this bio, or null if it is visible (M1.4).
        public readonly ?string $bioHiddenAt,
        public readonly bool $isPublic,
        public readonly ?string $lastInteractedAt,
        public readonly ?string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['owner_id'],
            (int) $row['species_id'],
            $row['name'],
            (int) $row['xp'],
            (int) $row['happiness'],
            $row['bio'] ?? null,
            $row['bio_hidden_at'] ?? null,
            (bool) $row['is_public'],
            $row['last_interacted_at'] ?? null,
            $row['created_at'] ?? null,
        );
    }

    /**
     * Is this bio hidden while somebody looks at a report about it?
     */
    public function isBioHidden(): bool
    {
        return $this->bioHiddenAt !== null;
    }
}
