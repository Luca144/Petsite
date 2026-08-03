<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * The rules for petting a creature (the core interaction).
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the one place that decides what happens when someone pets a
 * creature. If the same person has petted this creature too recently, it is
 * refused (the cooldown). Otherwise it records the pet, raises the creature's
 * happiness, and grants it XP — which is what makes it grow (increment B.2).
 *
 * The amounts and the cooldown come from config (gameplay.petting), passed in as
 * one array so this class stays easy to read and to retune.
 */
final class PettingService
{
    /**
     * @param array{cooldown_seconds: int, happiness_per_pet: int, xp_per_pet: int} $pettingConfig
     */
    public function __construct(
        private PettingRepository $pettings,
        private CreatureRepository $creatures,
        private array $pettingConfig,
    ) {
    }

    /**
     * Try to pet a creature on behalf of a user. Returns a PettingResult saying
     * whether it worked, with a message to show either way.
     */
    public function pet(int $actorUserId, Creature $creature): PettingResult
    {
        $cooldownSeconds = $this->pettingConfig['cooldown_seconds'];

        // Cooldown: the same person cannot pet the same creature again too soon.
        if ($this->pettings->wasPettedRecentlyBy($actorUserId, $creature->id, $cooldownSeconds)) {
            return PettingResult::onCooldown(
                'You petted ' . $creature->name . ' recently — give them a little while.'
            );
        }

        // Record the pet, then apply its effects: more happiness and some XP.
        $this->pettings->record($creature->id, $actorUserId);
        $this->creatures->applyPetting(
            $creature->id,
            $this->pettingConfig['happiness_per_pet'],
            $this->pettingConfig['xp_per_pet']
        );

        return PettingResult::success('You petted ' . $creature->name . '! They look happier.');
    }
}
