<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use Felkyo\Users\UserRepository;

/**
 * Gathers everything needed to display a creature.
 *
 * @package Felkyo\Creatures
 *
 * WHY THIS EXISTS: showing a creature needs several pieces from different places —
 * its species, its owner, and its calculated level and stage, plus how many times
 * it has been petted. Collecting them in one small helper keeps the controller
 * thin (it just asks for "the profile") and means the same assembly can be reused
 * by later pages that show creatures (the collection view, public browsing).
 */
final class CreatureProfileBuilder
{
    public function __construct(
        private SpeciesRepository $species,
        private UserRepository $users,
        private GrowthCalculator $growth,
        private PettingRepository $pettings,
    ) {
    }

    /**
     * Build the display data for one creature.
     *
     * @return array{species: ?Species, owner: ?\Felkyo\Users\User, level: int, stage: string, timesPetted: int}
     */
    public function buildFor(Creature $creature): array
    {
        return [
            'species' => $this->species->findById($creature->speciesId),
            'owner' => $this->users->findById($creature->ownerId),
            'level' => $this->growth->levelFor($creature->xp),
            'stage' => $this->growth->stageFor($creature->xp),
            'timesPetted' => $this->pettings->countForCreature($creature->id),
        ];
    }

    /**
     * Build lightweight summaries for a LIST of creatures (used by the collection
     * view). Each summary has just what a card needs: the creature, its species,
     * and its level and stage.
     *
     * To stay efficient, we load every species once and look each creature's up
     * from that, instead of querying the database once per creature.
     *
     * @param Creature[] $creatures
     * @return array<int, array{creature: Creature, species: ?Species, level: int, stage: string}>
     */
    public function summariesFor(array $creatures): array
    {
        // Build a lookup of species id => Species, once.
        $speciesById = [];
        foreach ($this->species->all() as $species) {
            $speciesById[$species->id] = $species;
        }

        $summaries = [];
        foreach ($creatures as $creature) {
            $summaries[] = [
                'creature' => $creature,
                'species' => $speciesById[$creature->speciesId] ?? null,
                'level' => $this->growth->levelFor($creature->xp),
                'stage' => $this->growth->stageFor($creature->xp),
            ];
        }

        return $summaries;
    }
}
