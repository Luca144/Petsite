<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use Felkyo\Users\UserRepository;

/**
 * The rules for adopting a new creature (once per day).
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the one place that decides what happens when a player adopts. If
 * they have adopted within the cooldown, it is refused. Otherwise it picks a
 * random species from the adoptable pool and a friendly name, creates the creature
 * for the player, and records that they have now adopted (so the daily limit
 * applies).
 *
 * The cooldown length and the name pool come from config, passed in as one array
 * so this class stays small and easy to retune.
 */
final class AdoptionService
{
    /**
     * @param array{cooldown_seconds: int, names: string[]} $config
     */
    public function __construct(
        private SpeciesRepository $species,
        private CreatureRepository $creatures,
        private UserRepository $users,
        private array $config,
    ) {
    }

    /**
     * May this user adopt right now? True unless they have adopted within the
     * cooldown window.
     */
    public function canAdopt(int $userId): bool
    {
        return !$this->users->hasAdoptedWithin($userId, $this->config['cooldown_seconds']);
    }

    /**
     * Try to adopt a creature for the user. Returns an AdoptionResult describing
     * success (with the new creature) or the "come back later" refusal.
     */
    public function adopt(int $userId): AdoptionResult
    {
        if (!$this->canAdopt($userId)) {
            return AdoptionResult::onCooldown(
                'You have already adopted today. Come back tomorrow to meet another creature.'
            );
        }

        $pool = $this->species->findAdoptable();
        if ($pool === []) {
            // A setup error (no adoptable species seeded) — fail loudly.
            throw new \RuntimeException('There are no adoptable species available.');
        }

        // Pick a random species from the pool and a random name from the pool.
        $species = $pool[array_rand($pool)];
        $names = $this->config['names'];
        $name = $names[array_rand($names)];

        $creature = $this->creatures->create($userId, $species->id, $name);
        $this->users->markAdopted($userId);

        return AdoptionResult::success(
            $creature,
            'You adopted ' . $name . ', a ' . $species->name . '! Say hello.'
        );
    }
}
