<?php

declare(strict_types=1);

namespace Felkyo\Character;

use Felkyo\Creatures\Creature;

/**
 * Hands out traits at the moments they are due (M3.2).
 *
 * @package Felkyo\Character
 *
 *   awardEarned()   — called when a creature's page is viewed: any earned
 *                     trait whose rule it now meets is given. Cheap (the
 *                     traits table is tiny) and idempotent (INSERT IGNORE),
 *                     so checking on every view costs nothing and can't
 *                     double-award.
 *   onAdoption()    — called by the pound when a creature finds a new home:
 *                     one or two shelter traits, plus the chance of
 *                     "grateful" (or any other gift-tier trait).
 */
final class TraitAwarder
{
    public function __construct(private TraitRepository $traits)
    {
    }

    /**
     * Give any earned traits this creature now qualifies for.
     *
     * @return string[] names of traits given just now (for a celebration line)
     */
    public function awardEarned(Creature $creature, int $level, int $timesPetted): array
    {
        $daysOld = $creature->createdAt === null
            ? 0
            : intdiv(max(0, time() - (int) strtotime($creature->createdAt)), 86400);
        $measures = ['petted' => $timesPetted, 'level' => $level, 'age_days' => $daysOld];

        $given = [];
        foreach ($this->traits->ofTier(CreatureTrait::TIER_EARNED) as $trait) {
            $measure = $measures[$trait->earnKind ?? ''] ?? null;
            if ($measure === null || $trait->earnThreshold === null || $measure < $trait->earnThreshold) {
                continue;
            }
            if ($this->traits->give($creature->id, $trait->id, $trait->earnedHow())) {
                $given[] = $trait->name;
            }
        }

        return $given;
    }

    /**
     * Traits for a creature leaving the pound with a new owner.
     *
     * @return string[] names of the traits it picked up
     */
    public function onAdoption(int $creatureId): array
    {
        $given = [];

        // One or two shelter traits, picked at random from the set.
        $shelter = $this->traits->ofTier(CreatureTrait::TIER_SHELTER);
        shuffle($shelter);
        foreach (array_slice($shelter, 0, random_int(1, 2)) as $trait) {
            if ($this->traits->give($creatureId, $trait->id, 'from its time at the pound')) {
                $given[] = $trait->name;
            }
        }

        // Gift traits are each a separate roll of their own chance.
        foreach ($this->traits->ofTier(CreatureTrait::TIER_GIFT) as $trait) {
            if ($trait->chancePercent > 0 && random_int(1, 100) <= $trait->chancePercent
                && $this->traits->give($creatureId, $trait->id, 'when it was adopted')) {
                $given[] = $trait->name;
            }
        }

        return $given;
    }
}
