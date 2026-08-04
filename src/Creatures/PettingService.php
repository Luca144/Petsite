<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use Felkyo\Users\UserRepository;

/**
 * The rules for petting a creature (the core interaction).
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the one place that decides what happens when someone pets a
 * creature. If the same person has petted this creature too recently, it is
 * refused (the cooldown). Otherwise it records the pet, raises the creature's
 * happiness, grants it XP (which makes it grow — increment B.2), and — when the
 * petter is someone OTHER than the owner — earns the owner some currency
 * (increment B.7). The cooldown is what stops the currency being farmed.
 *
 * The amounts and the cooldown come from config (gameplay.petting + the currency
 * amount), passed in as one array so this class stays easy to read and to retune.
 */
final class PettingService
{
    /**
     * @param array{cooldown_seconds: int, happiness_per_pet: int, xp_per_pet: int, currency_per_pet: int} $pettingConfig
     */
    public function __construct(
        private PettingRepository $pettings,
        private CreatureRepository $creatures,
        private UserRepository $users,
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

        // Petting SOMEONE ELSE'S creature earns its owner some currency. Petting
        // your own creature does not (you can't pay yourself). The cooldown above
        // is what keeps this from being farmed.
        if ($actorUserId !== $creature->ownerId) {
            $this->users->addCurrency($creature->ownerId, $this->pettingConfig['currency_per_pet']);
        }

        return PettingResult::success('You petted ' . $creature->name . '! They look happier.');
    }
}
