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
 *
 * THE MOOD READINGS WORK THE SAME WAY, and the "age" fields are the reason they
 * can. `happiness` and `energy` are what a creature felt at a MOMENT, and the two
 * age fields say how long ago that moment was — measured by the database, in the
 * same query, with the same clock that wrote the value. MoodCalculator turns the
 * pair into how the creature feels now. Working the elapsed time out in PHP
 * instead would mean trusting the web server's clock and timezone to agree with
 * the database's, which is a bug waiting for the first server move.
 */
final class Creature
{
    public function __construct(
        public readonly int $id,
        public readonly int $ownerId,
        public readonly int $speciesId,
        public readonly string $name,
        public readonly int $xp,
        /** Happiness AS OF $happinessAgeSeconds ago — not necessarily now. */
        public readonly int $happiness,
        public readonly int $happinessAgeSeconds,
        /** Energy AS OF $energyAgeSeconds ago — not necessarily now. */
        public readonly int $energy,
        public readonly int $energyAgeSeconds,
        public readonly ?string $bio,
        // When a report hid this bio, or null if it is visible (M1.4).
        public readonly ?string $bioHiddenAt,
        public readonly bool $isPublic,
        // Where this creature sits among the ones its owner shows off, or null if
        // it is not one of them (M1.3). Carried here so a card can mark it.
        public readonly ?int $featuredOrder,
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
            // Defaulted to 0 ("as of right now") rather than left to blow up, so a
            // query that has not been updated to fetch the ages still produces a
            // usable creature — it simply shows an un-aged mood.
            (int) ($row['happiness_age_seconds'] ?? 0),
            (int) ($row['energy'] ?? 100),
            (int) ($row['energy_age_seconds'] ?? 0),
            $row['bio'] ?? null,
            $row['bio_hidden_at'] ?? null,
            (bool) $row['is_public'],
            isset($row['featured_order']) ? (int) $row['featured_order'] : null,
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

    /**
     * Is this one of the creatures its owner chose to show off?
     */
    public function isFeatured(): bool
    {
        return $this->featuredOrder !== null;
    }
}
