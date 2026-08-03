<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use Felkyo\Users\User;

/**
 * Gives a brand-new player their first creature.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the rule for "a new user receives a starter creature". It picks a
 * starter species and a friendly default name, then creates the creature owned by
 * that user. It is called just after a successful registration.
 *
 * The choices are made deterministically from the user's id (rather than at
 * random) so that the same user would always get the same starter — which also
 * makes the behaviour easy to test. Because there are several starter species and
 * several names, different players still get pleasant variety.
 */
final class StarterCreatureService
{
    /**
     * @param string[] $starterNames The pool of default names (from config).
     */
    public function __construct(
        private SpeciesRepository $species,
        private CreatureRepository $creatures,
        private array $starterNames,
    ) {
    }

    /**
     * Create and return the starter creature for a newly registered user.
     */
    public function grantStarterTo(User $user): Creature
    {
        $starterSpecies = $this->species->findStarters();
        if ($starterSpecies === []) {
            // This only happens if the species were never seeded — a setup error,
            // so we fail loudly rather than silently create nothing.
            throw new \RuntimeException('No starter species are available to hand out.');
        }

        // Pick a species and a name using the user's id. The modulo (%) keeps the
        // choice inside the list no matter how large the id grows.
        $species = $starterSpecies[$user->id % count($starterSpecies)];
        $name = $this->starterNames[$user->id % count($this->starterNames)];

        return $this->creatures->create($user->id, $species->id, $name);
    }
}
