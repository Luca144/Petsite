<?php

declare(strict_types=1);

namespace Felkyo\Pound;

use Felkyo\Character\TraitAwarder;
use Felkyo\Creatures\CreatureRepository;
use PDO;

/**
 * The pound's rules (M3.1), in plain sentences for the player.
 *
 * @package Felkyo\Pound
 *
 * WHO MAY DO WHAT, and where it is enforced — in the SQL of PoundRepository,
 * not only here, so a hand-crafted request cannot get round it:
 *   send     — only the creature's current owner.
 *   reclaim  — only the person who brought it, only within the grace period.
 *   adopt    — anyone logged in EXCEPT the person who brought it, and only
 *              after the grace period. (So nobody can farm "grateful" or the
 *              shelter traits by bouncing their own creature through.)
 *
 * Adoption and its traits happen in one transaction: the new home and the
 * traits it picks up on the way out arrive together or not at all.
 */
final class PoundService
{
    public function __construct(
        private PDO $connection,
        private PoundRepository $pound,
        private CreatureRepository $creatures,
        private TraitAwarder $traits,
    ) {
    }

    public function send(int $ownerId, int $creatureId): PoundResult
    {
        $creature = $this->creatures->findById($creatureId);
        if ($creature === null || !$this->pound->send($creatureId, $ownerId)) {
            return PoundResult::refused('Only a creature that is yours, and at home, can be taken to the pound.');
        }

        return PoundResult::ok(sprintf(
            '%s is at the pound now. You have %d days to change your mind — find it on the pound page to take it home again.',
            $creature->name,
            $this->pound->graceDays()
        ));
    }

    public function reclaim(int $userId, int $creatureId): PoundResult
    {
        $creature = $this->creatures->findById($creatureId);
        if ($creature === null || !$this->pound->reclaim($creatureId, $userId)) {
            return PoundResult::refused('That creature can\'t be taken home any more — the grace period may have ended, or it was never yours.');
        }

        return PoundResult::ok(sprintf('%s is home again, and very glad about it.', $creature->name), $creatureId);
    }

    public function adopt(int $userId, int $creatureId): PoundResult
    {
        $creature = $this->creatures->findById($creatureId);
        if ($creature === null) {
            return PoundResult::refused('That creature isn\'t at the pound.');
        }
        if ($creature->poundPreviousOwnerId === $userId) {
            return PoundResult::refused(sprintf(
                'You brought %s here, so someone else gets the chance to give it a new home.',
                $creature->name
            ));
        }

        $this->connection->beginTransaction();
        try {
            if (!$this->pound->adopt($creatureId, $userId)) {
                $this->connection->rollBack();

                return PoundResult::refused(sprintf('Someone has already given %s a home — have a look at who else is waiting.', $creature->name));
            }
            $traits = $this->traits->onAdoption($creatureId);
            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }

        $message = sprintf('%s is yours now. Welcome home!', $creature->name);
        if ($traits !== []) {
            $message .= ' It left the pound ' . strtolower(implode(' and ', $traits)) . '.';
        }

        return PoundResult::ok($message, $creatureId);
    }
}
