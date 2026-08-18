<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * Works out how a creature feels now, from how it felt then.
 *
 * @package Felkyo\Creatures
 *
 * WHY THIS EXISTS, AND WHY IT IS SHAPED LIKE THIS. A creature stores two readings
 * and the moment each was taken. Everything since is CALCULATED here, the same way
 * GrowthCalculator works out a level from XP. Nothing anywhere ticks moods down on
 * a timer.
 *
 * That is not a performance trick, it is a reliability one. A scheduled job that
 * decays every creature every hour is a thing that can fail quietly: it stops, and
 * nobody notices for a week, and by then every creature in the game is frozen at
 * whatever it felt like when the job died. Deriving on read cannot fail that way,
 * because there is nothing to run.
 *
 * THE TWO RULES, AND WHY THEY ARE NOT SYMMETRICAL:
 *
 *   HAPPINESS falls with time, slowly, and then STOPS at a floor. It never
 *   reaches zero. This is the whole reason the class is careful: the genre this
 *   borrows from runs on guilt — feed me or I suffer — and golden rule 10 forbids
 *   punishing absence. A creature nobody visits for a month is sleepy and pleased
 *   to see you. It is not sad, not sick, and nothing has been lost.
 *
 *   ENERGY rises with time. It is spent by interacting and comes back on its own,
 *   which gives visits a gentle rhythm and gives treats something to be for. Low
 *   energy changes what a creature DOES, never what a player MAY do — there is no
 *   code anywhere that refuses an interaction because a creature is tired.
 *
 * ELAPSED TIME IS PASSED IN, not read from the clock, so every rule here can be
 * tested exactly — hand it "three days" and check the number. Same reason
 * WeightedPicker takes its dice roll rather than rolling one.
 */
final class MoodCalculator
{
    private const SECONDS_PER_DAY = 86400;
    private const SECONDS_PER_HOUR = 3600;

    /**
     * @param array{
     *     starting_happiness: int,
     *     starting_energy: int,
     *     happiness_decay_per_day: int,
     *     happiness_floor: int,
     *     energy_recovery_per_hour: int,
     *     resting_below: int,
     *     favourite_treat_multiplier: int,
     *     disliked_treat_divisor: int,
     *     happiness_words: array<int, string>
     * } $config The "gameplay.mood" section.
     */
    public function __construct(private array $config)
    {
    }

    /**
     * The creature's mood right now, from its stored readings and how long ago
     * each was taken.
     */
    public function moodFor(int $storedHappiness, int $happinessAgeSeconds, int $storedEnergy, int $energyAgeSeconds): Mood
    {
        $happiness = $this->happinessNow($storedHappiness, $happinessAgeSeconds);
        $energy = $this->energyNow($storedEnergy, $energyAgeSeconds);

        return new Mood(
            $happiness,
            $energy,
            $this->wordFor($happiness),
            $energy < $this->config['resting_below'],
        );
    }

    /**
     * Happiness after $ageSeconds have passed since it was last set.
     *
     * The floor is applied last and unconditionally, so no amount of time can put
     * a creature below it — and a creature already below the floor (which only a
     * config change could produce) is lifted back up to it rather than left there.
     */
    public function happinessNow(int $storedHappiness, int $ageSeconds): int
    {
        $days = max(0, $ageSeconds) / self::SECONDS_PER_DAY;
        $lost = (int) floor($days * $this->config['happiness_decay_per_day']);

        return $this->clamp($storedHappiness - $lost, $this->config['happiness_floor'], 100);
    }

    /**
     * Energy after $ageSeconds have passed since it was last set. Rest is the
     * only thing that restores energy for free; a treat can add more on top.
     */
    public function energyNow(int $storedEnergy, int $ageSeconds): int
    {
        $hours = max(0, $ageSeconds) / self::SECONDS_PER_HOUR;
        $gained = (int) floor($hours * $this->config['energy_recovery_per_hour']);

        return $this->clamp($storedEnergy + $gained, 0, 100);
    }

    /**
     * Happiness after something kind happens to the creature. Takes the CURRENT
     * happiness (already aged), not the stored one — the caller derives it first,
     * so a pet after a long absence tops up from where the creature actually is.
     */
    public function afterGaining(int $currentHappiness, int $gain): int
    {
        return $this->clamp($currentHappiness + $gain, $this->config['happiness_floor'], 100);
    }

    /**
     * Energy after an interaction spends some of it. Bottoms out at zero, which
     * means "fast asleep" and still refuses nobody.
     */
    public function afterSpending(int $currentEnergy, int $cost): int
    {
        return $this->clamp($currentEnergy - $cost, 0, 100);
    }

    /**
     * Energy after a restful treat. Same arithmetic as spending, the other way —
     * kept as its own named method because "resting" and "tiring" are different
     * things to read at a call site, and a negative cost would be a puzzle.
     */
    public function afterResting(int $currentEnergy, int $recovery): int
    {
        return $this->clamp($currentEnergy + $recovery, 0, 100);
    }

    /**
     * How much a treat's effect is worth to a creature that adores it, dislikes
     * it, or has no opinion.
     *
     * THE FLOOR OF 1 IS THE IMPORTANT LINE. A disliked treat's effect is divided
     * down, and integer division would take a small effect to zero — which would
     * mean a creature refusing a gift outright, which is not a thing this game
     * does. Anything that helped at all still helps at least a little, so feeding
     * is never a wasted item and never a rebuff.
     *
     * A base of zero stays zero: a treat with no happiness effect does not become
     * one just because the creature likes it.
     */
    public function tasteAdjusted(int $baseEffect, bool $adores, bool $dislikes): int
    {
        if ($baseEffect <= 0) {
            return 0;
        }

        if ($adores) {
            return $baseEffect * $this->config['favourite_treat_multiplier'];
        }

        if ($dislikes) {
            return max(1, (int) ceil($baseEffect / $this->config['disliked_treat_divisor']));
        }

        return $baseEffect;
    }

    /**
     * What a happiness reading is called. The bands come from config, low to
     * high; the highest one the creature has reached wins. If the config is empty
     * or malformed we fall back to a warm neutral rather than showing a number,
     * because a bare number is exactly what this system exists to avoid.
     */
    public function wordFor(int $happiness): string
    {
        $words = $this->config['happiness_words'] ?? [];
        ksort($words);

        $word = 'content';
        foreach ($words as $from => $candidate) {
            if ($happiness >= (int) $from) {
                $word = $candidate;
            }
        }

        return $word;
    }

    /**
     * Keep a reading inside its allowed range.
     */
    private function clamp(int $value, int $lowest, int $highest): int
    {
        return max($lowest, min($highest, $value));
    }
}
