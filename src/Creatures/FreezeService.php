<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use Felkyo\Pound\PoundResult;
use PDO;

/**
 * Keeping a creature at a stage you love (M3.2b).
 *
 * @package Felkyo\Creatures
 *
 * Freezing pins the SHOWN stage (creatures.shown_stage) and sets is_frozen.
 * Nothing else changes: XP keeps counting, petting still pays, happiness
 * still rises — freezing is about keeping a shape, never opting out, and is
 * never punished.
 *
 * Unfreezing clears is_frozen but leaves shown_stage where it was; from then
 * on each growth tick steps it forward one stage
 * (CreatureInteractions::stepCatchUp) until it meets the stage the XP has
 * earned. If it is already there, shown_stage clears at once.
 *
 * Only the owner may do either: the owner id sits in the WHERE clause.
 */
final class FreezeService
{
    public function __construct(
        private PDO $connection,
        private CreatureRepository $creatures,
        private GrowthCalculator $growth,
    ) {
    }

    public function freeze(int $ownerId, int $creatureId): PoundResult
    {
        $creature = $this->creatures->findById($creatureId);
        if ($creature === null || $creature->ownerId !== $ownerId) {
            return PoundResult::refused('Only a creature\'s own keeper can do that.');
        }

        $stage = $this->growth->shownStageFor($creature);
        $this->connection->prepare(
            'UPDATE creatures SET is_frozen = 1, shown_stage = :stage WHERE id = :id AND owner_id = :owner'
        )->execute([':stage' => $stage, ':id' => $creatureId, ':owner' => $ownerId]);

        return PoundResult::ok(sprintf(
            '%s will stay a %s for as long as you like. It still learns and earns as usual — let it grow again whenever you\'re ready.',
            $creature->name,
            $stage
        ), $creatureId);
    }

    public function unfreeze(int $ownerId, int $creatureId): PoundResult
    {
        $creature = $this->creatures->findById($creatureId);
        if ($creature === null || $creature->ownerId !== $ownerId) {
            return PoundResult::refused('Only a creature\'s own keeper can do that.');
        }

        // Already at (or past) what the XP has earned? Then nothing to catch up.
        $earned = $this->growth->stageFor($creature->xp);
        $shown = $creature->shownStage;
        $stillBehind = $shown !== null && $this->growth->isEarlier($shown, $earned);

        $this->connection->prepare(
            'UPDATE creatures SET is_frozen = 0, shown_stage = :stage WHERE id = :id AND owner_id = :owner'
        )->execute([':stage' => $stillBehind ? $shown : null, ':id' => $creatureId, ':owner' => $ownerId]);

        return PoundResult::ok($stillBehind
            ? sprintf('%s is growing again — it will catch up one stage at a time as you care for it.', $creature->name)
            : sprintf('%s is growing again.', $creature->name), $creatureId);
    }
}
