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
        // Which alternate form the creature wears (M5.3); null = the default.
        public readonly ?int $formId = null,
        // M3: at the pound since (null = not at the pound), and who brought it.
        public readonly ?string $poundEnteredAt = null,
        public readonly ?int $poundPreviousOwnerId = null,
        // M3.2b: kept at a stage, and the stage shown while frozen or catching up.
        public readonly bool $isFrozen = false,
        public readonly ?string $shownStage = null,
    ) {
    }

    /**
     * Is this creature at the pound? Its ownerId is then 0 — it belongs to
     * nobody, so every ownership check refuses everyone (see the M3 migration).
     */
    public function isAtPound(): bool
    {
        return $this->poundEnteredAt !== null;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            // NULL while at the pound (M3) — 0 matches no account.
            (int) ($row['owner_id'] ?? 0),
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
            isset($row['form_id']) ? (int) $row['form_id'] : null,
            $row['pound_entered_at'] ?? null,
            isset($row['pound_previous_owner_id']) ? (int) $row['pound_previous_owner_id'] : null,
            (bool) ($row['is_frozen'] ?? false),
            $row['shown_stage'] ?? null,
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
