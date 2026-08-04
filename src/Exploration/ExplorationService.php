<?php

declare(strict_types=1);

namespace Felkyo\Exploration;

use Felkyo\Creatures\Creature;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;

/**
 * The rules for exploring an area.
 *
 * @package Felkyo\Exploration
 *
 * WHAT THIS IS: the one place that decides what happens when a player searches a
 * spot in an area. It enforces the per-visit click limit (refreshing after the
 * window passes), rolls the area's weighted loot table, and grants the reward —
 * which, for now, is either nothing or a new creature.
 *
 * It is written to be the reusable pattern for every future area: the areas
 * themselves are just data (see config), and this one service drives them all.
 * Adding an area is a config change, not new code (build plan section 2).
 */
final class ExplorationService
{
    /**
     * @param array{clicks_per_visit: int, window_seconds: int, creature_names: string[]} $config
     */
    public function __construct(
        private ExplorationRepository $visits,
        private WeightedPicker $picker,
        private CreatureRepository $creatures,
        private SpeciesRepository $species,
        private array $config,
    ) {
    }

    /**
     * How many clicks the player still has in this area's current window.
     */
    public function remainingClicks(int $userId, string $areaSlug): int
    {
        $used = $this->visits->clicksUsedInCurrentWindow(
            $userId,
            $areaSlug,
            $this->config['window_seconds']
        ) ?? 0;

        return max(0, $this->config['clicks_per_visit'] - $used);
    }

    /**
     * Search this area once. $area is the area's config (its loot table etc.).
     */
    public function explore(int $userId, string $areaSlug, array $area): ExplorationResult
    {
        $used = $this->visits->clicksUsedInCurrentWindow(
            $userId,
            $areaSlug,
            $this->config['window_seconds']
        );

        // No current window (never visited, or it has expired) — start a fresh one.
        if ($used === null) {
            $this->visits->startFreshWindow($userId, $areaSlug);
            $used = 0;
        }

        if ($used >= $this->config['clicks_per_visit']) {
            return ExplorationResult::limitReached(
                'You have searched all you can here for now. Come back a little later.'
            );
        }

        $reward = $this->rollLoot($area['loot']);

        // Spend the click, then grant whatever was rolled.
        $this->visits->recordClick($userId, $areaSlug);

        $creature = null;
        if (($reward['type'] ?? 'nothing') === 'creature') {
            $creature = $this->grantRandomCreature($userId);
        }

        return ExplorationResult::reward($reward['message'], $creature);
    }

    /**
     * Choose one reward from the loot table, weighted by each entry's "weight".
     *
     * @param array<int, array{type: string, weight: int, message: string}> $loot
     * @return array{type: string, weight: int, message: string}
     */
    private function rollLoot(array $loot): array
    {
        $total = $this->picker->totalWeight($loot);
        // random_int needs a valid range; a well-formed loot table always has one.
        $roll = random_int(0, $total - 1);

        return $this->picker->pickByRoll($loot, $roll);
    }

    /**
     * Create a new creature for the player, of a random adoptable species with a
     * random friendly name — the "you found a creature" reward.
     */
    private function grantRandomCreature(int $userId): Creature
    {
        $pool = $this->species->findAdoptable();
        $species = $pool[array_rand($pool)];
        $names = $this->config['creature_names'];
        $name = $names[array_rand($names)];

        return $this->creatures->create($userId, $species->id, $name);
    }
}
