<?php

declare(strict_types=1);

namespace Felkyo\Character;

use Felkyo\Creatures\Creature;

/**
 * Everything that says WHO a creature is (M3.2), gathered for its page:
 * its traits, its level title, its friendly age, and its keepsake badges.
 *
 * @package Felkyo\Character
 *
 * Also the moment earned traits and keepsake badges are handed out: viewing
 * the page checks whether any are now due (both idempotent and cheap), so
 * they appear the first time anybody looks after they were earned.
 */
final class CharacterSheet
{
    public function __construct(
        private TraitRepository $traits,
        private TraitAwarder $awarder,
        private LadderRepository $ladders,
        // Keepsake badges (M8). Optional so older wiring still builds.
        private ?BadgeRepository $badges = null,
    ) {
    }

    /**
     * @return array{traits: CreatureTrait[], newTraits: string[], levelTitle: ?string, ageLabel: ?string, badges: array}
     */
    public function for(Creature $creature, int $level, int $timesPetted): array
    {
        // A creature at the pound belongs to nobody; it doesn't grow new
        // personality there (the pound gives its own traits on the way out).
        $newTraits = $creature->isAtPound() ? [] : $this->awarder->awardEarned($creature, $level, $timesPetted);

        // Keepsake badges come with time alone — never lost, never hurried.
        if ($this->badges !== null && $creature->createdAt !== null) {
            $daysKept = intdiv(max(0, time() - (int) strtotime($creature->createdAt)), 86400);
            $this->badges->awardKeptBadges($creature->id, $daysKept);
        }

        return [
            'traits' => $this->traits->forCreature($creature->id),
            'newTraits' => $newTraits,
            'levelTitle' => $this->ladders->titleForLevel($level),
            'ageLabel' => $this->ladders->ageFor($creature->createdAt),
            'badges' => $this->badges?->ofCreature($creature->id) ?? [],
        ];
    }
}
