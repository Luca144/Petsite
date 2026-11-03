<?php

declare(strict_types=1);

namespace Felkyo\Character;

use Felkyo\Creatures\Creature;

/**
 * Everything that says WHO a creature is (M3.2), gathered for its page:
 * its traits, its level title, and its friendly age.
 *
 * @package Felkyo\Character
 *
 * Also the moment earned traits are handed out: viewing the page checks
 * whether any are now due (see TraitAwarder — idempotent and cheap), so a
 * trait appears the first time anybody looks after it was earned, and the
 * page can say so.
 */
final class CharacterSheet
{
    public function __construct(
        private TraitRepository $traits,
        private TraitAwarder $awarder,
        private LadderRepository $ladders,
    ) {
    }

    /**
     * @return array{traits: CreatureTrait[], newTraits: string[], levelTitle: ?string, ageLabel: ?string}
     */
    public function for(Creature $creature, int $level, int $timesPetted): array
    {
        // A creature at the pound belongs to nobody; it doesn't grow new
        // personality there (the pound gives its own traits on the way out).
        $newTraits = $creature->isAtPound() ? [] : $this->awarder->awardEarned($creature, $level, $timesPetted);

        return [
            'traits' => $this->traits->forCreature($creature->id),
            'newTraits' => $newTraits,
            'levelTitle' => $this->ladders->titleForLevel($level),
            'ageLabel' => $this->ladders->ageFor($creature->createdAt),
        ];
    }
}
