<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * Works out a creature's level and life stage from its XP.
 *
 * @package Felkyo\Creatures
 *
 * WHY THIS EXISTS: a creature stores only its XP. Its LEVEL and its life STAGE
 * (baby / juvenile / adult) are both CALCULATED from that XP whenever they are
 * needed, so they can never drift out of step with it. This class is that
 * calculation, and it is the single place the growth rules live.
 *
 * The two knobs come from config (gameplay.growth): how much XP a level costs,
 * and the level at which each stage begins. Changing how fast creatures grow is a
 * config edit, not a code change.
 */
final class GrowthCalculator
{
    /**
     * @param int                $xpPerLevel       XP needed for each level (at least 1).
     * @param array<string, int> $stageStartLevels Stage name => the level it starts
     *        at, in ascending order (e.g. ['baby' => 1, 'juvenile' => 3, 'adult' => 6]).
     */
    public function __construct(
        private int $xpPerLevel,
        private array $stageStartLevels,
    ) {
        // Guard against a mis-set config that would divide by zero.
        $this->xpPerLevel = max(1, $xpPerLevel);
    }

    /**
     * The creature's level. A creature starts at level 1 with 0 XP, and gains a
     * level for every $xpPerLevel of XP it earns.
     */
    public function levelFor(int $xp): int
    {
        $xp = max(0, $xp);

        return intdiv($xp, $this->xpPerLevel) + 1;
    }

    /**
     * The creature's life stage: the highest stage whose starting level the
     * creature's current level has reached.
     */
    public function stageFor(int $xp): string
    {
        $level = $this->levelFor($xp);

        $currentStage = array_key_first($this->stageStartLevels) ?? 'baby';
        foreach ($this->stageStartLevels as $stage => $startLevel) {
            if ($level >= $startLevel) {
                $currentStage = $stage;
            }
        }

        return $currentStage;
    }
}
