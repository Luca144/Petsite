<?php

declare(strict_types=1);

namespace Felkyo\Character;

/**
 * A trait (M3.2) — either the definition from the `traits` table, or, when
 * $how is set, one a particular creature HAS, with how it came by it.
 *
 * @package Felkyo\Character
 *
 * Named CreatureTrait because `trait` is a reserved word in PHP.
 *
 * THE TWO TIERS, kept apart on purpose:
 *   earned   — personality, earned by living (petted N times, level N, N days
 *              old). The creature page says how it was earned.
 *   shelter  — given by the world when a creature leaves the pound.
 *   gift     — "grateful": a chance reward on adoption, and the only kind of
 *              trait with a mechanical effect (a small XP bonus).
 * Traits never change a stat, a price, a cooldown or an outcome — build plan
 * M3.2 rules that out until the arena exists (M13).
 */
final class CreatureTrait
{
    public const TIER_EARNED = 'earned';
    public const TIER_SHELTER = 'shelter';
    public const TIER_GIFT = 'gift';
    public const TIERS = [self::TIER_EARNED, self::TIER_SHELTER, self::TIER_GIFT];

    /** What an earned trait can be earned by, and how the page words it. */
    public const EARN_KINDS = [
        'petted' => 'times petted',
        'level' => 'level reached',
        'age_days' => 'days old',
    ];

    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly string $tier,
        public readonly ?string $earnKind,
        public readonly ?int $earnThreshold,
        public readonly int $chancePercent,
        public readonly int $xpBonusPercent,
        /** Set only for a trait a creature has: "after 50 pets". */
        public readonly ?string $how = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['slug'],
            (string) $row['name'],
            (string) $row['description'],
            (string) $row['tier'],
            $row['earn_kind'] ?? null,
            isset($row['earn_threshold']) ? (int) $row['earn_threshold'] : null,
            (int) ($row['chance_percent'] ?? 0),
            (int) ($row['xp_bonus_percent'] ?? 0),
            $row['how'] ?? null,
        );
    }

    /** "after 50 pets" — the words stored when an earned trait arrives. */
    public function earnedHow(): string
    {
        return match ($this->earnKind) {
            'petted' => sprintf('after being petted %d times', (int) $this->earnThreshold),
            'level' => sprintf('on reaching level %d', (int) $this->earnThreshold),
            'age_days' => sprintf('after %d days in Felkyo', (int) $this->earnThreshold),
            default => 'along the way',
        };
    }
}
