<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * Works out a creature's life stage from its XP.
 *
 * @package Felkyo\Creatures
 *
 * WHY THIS EXISTS: a creature's stage (baby / juvenile / adult) is NOT stored in
 * the database — only its XP is. The stage is calculated from XP whenever it is
 * needed, so the two can never drift apart. This class is that calculation.
 *
 * The thresholds come from config (gameplay.stage_xp_thresholds), so changing how
 * much XP a stage needs is a one-line config edit. Actually EARNING xp arrives in
 * increment B.2; for now every creature is a baby, and this class is ready for
 * when they start to grow.
 */
final class GrowthCalculator
{
    /**
     * @param array<string, int> $stageThresholds Stage name => XP required, in
     *        ascending order (e.g. ['baby' => 0, 'juvenile' => 100, 'adult' => 300]).
     */
    public function __construct(private array $stageThresholds)
    {
    }

    /**
     * Return the life stage for a given amount of XP: the highest stage whose XP
     * requirement the creature has met.
     */
    public function stageFor(int $xp): string
    {
        // Start at the lowest stage and step up as long as the creature has enough
        // XP to have reached the next one.
        $currentStage = array_key_first($this->stageThresholds) ?? 'baby';

        foreach ($this->stageThresholds as $stage => $requiredXp) {
            if ($xp >= $requiredXp) {
                $currentStage = $stage;
            }
        }

        return $currentStage;
    }
}
