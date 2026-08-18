<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * How a creature is feeling right now.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: a plain, read-only answer produced by MoodCalculator and handed
 * to a template. It carries both numbers AND the word for them, because the word
 * is what a player should read. "Very happy" is something you can feel about;
 * "87%" is a readout on a machine, and this is meant to be a creature.
 *
 * THE NUMBERS ARE STILL HERE because the bars need them, and because a bar and a
 * word together carry the same fact two ways — which is the accessibility rule
 * that nothing important may ride on one signal alone (CLAUDE.md section 8).
 */
final class Mood
{
    public function __construct(
        /** 0–100, though it never falls below the configured floor. */
        public readonly int $happiness,
        /** 0–100. */
        public readonly int $energy,
        /** What this happiness is called, e.g. "very happy". */
        public readonly string $word,
        /** True when energy is low: the creature is dozing rather than bouncing. */
        public readonly bool $isResting,
    ) {
    }

    /**
     * A short sentence a creature page or sidebar card can show as-is.
     *
     * Resting is mentioned FIRST when it applies, because it is the thing a
     * player can see in the picture and would otherwise wonder about. It is
     * phrased as something the creature is doing, never as something wrong: a
     * resting creature is having a nap, not failing to be looked after.
     */
    public function sentence(string $creatureName): string
    {
        if ($this->isResting) {
            return $creatureName . ' is having a doze, and is ' . $this->word . '.';
        }

        return $creatureName . ' is ' . $this->word . '.';
    }
}
