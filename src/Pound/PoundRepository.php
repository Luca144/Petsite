<?php

declare(strict_types=1);

namespace Felkyo\Pound;

use Felkyo\Creatures\Creature;
use PDO;

/**
 * The pound's SQL (M3.1). Every move in or out is ONE conditional UPDATE
 * that states what it expects and reports whether it happened — house
 * pattern 1, "compare and swap". Two people adopting the same creature at the
 * same instant both send the update; the database lets exactly one change
 * the row, and the other sees zero rows changed and is told kindly.
 *
 * @package Felkyo\Pound
 *
 * The grace period is a number of whole days from config, cast to an integer
 * and placed into INTERVAL (which will not take a bound parameter) — safe,
 * because it is our own value, never the visitor's.
 */
final class PoundRepository
{
    private const COLUMNS = 'id, owner_id, species_id, form_id, name, xp, happiness, energy,
             pound_entered_at, pound_previous_owner_id, is_frozen, shown_stage,
             bio, bio_hidden_at, is_public, featured_order, last_interacted_at, created_at';

    public function __construct(private PDO $connection, private int $graceDays)
    {
    }

    /**
     * Hand a creature in. Only succeeds if $ownerId really owns it right now.
     * It stops being featured (it is nobody's to show off) and becomes public
     * so adopters can see it.
     */
    public function send(int $creatureId, int $ownerId): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures
                SET pound_previous_owner_id = owner_id, owner_id = NULL,
                    pound_entered_at = NOW(), featured_order = NULL, is_public = 1
              WHERE id = :id AND owner_id = :owner AND pound_entered_at IS NULL'
        );
        $statement->execute([':id' => $creatureId, ':owner' => $ownerId]);

        return $statement->rowCount() === 1;
    }

    /** Take it back home — only its previous owner, only during the grace period. */
    public function reclaim(int $creatureId, int $previousOwnerId): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures
                SET owner_id = pound_previous_owner_id, pound_entered_at = NULL, pound_previous_owner_id = NULL
              WHERE id = :id AND owner_id IS NULL AND pound_previous_owner_id = :previous
                AND pound_entered_at > NOW() - INTERVAL ' . $this->graceDays . ' DAY'
        );
        $statement->execute([':id' => $creatureId, ':previous' => $previousOwnerId]);

        return $statement->rowCount() === 1;
    }

    /**
     * Adopt — anyone but the previous owner, only after the grace period.
     * The previous-owner check lives IN the statement, so no route around
     * the page can let someone re-adopt their own creature (M3.1).
     */
    public function adopt(int $creatureId, int $adopterId): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures
                SET owner_id = :adopter, pound_entered_at = NULL, pound_previous_owner_id = NULL,
                    -- a new keeper decides afresh whether to keep it at a stage
                    is_frozen = 0
              WHERE id = :id AND owner_id IS NULL
                AND pound_entered_at <= NOW() - INTERVAL ' . $this->graceDays . ' DAY
                AND (pound_previous_owner_id IS NULL OR pound_previous_owner_id <> :adopter2)'
        );
        $statement->execute([':id' => $creatureId, ':adopter' => $adopterId, ':adopter2' => $adopterId]);

        return $statement->rowCount() === 1;
    }

    /**
     * Creatures ready for a new home, longest-waiting first (uses the
     * pound_entered_at index).
     *
     * @return Creature[]
     */
    public function available(int $limit): array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM creatures
              WHERE owner_id IS NULL AND pound_entered_at <= NOW() - INTERVAL ' . $this->graceDays . ' DAY
              ORDER BY pound_entered_at
              LIMIT ' . max(1, $limit)
        );
        $statement->execute();

        return array_map([Creature::class, 'fromRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** How many are still being settled in (inside the grace period). */
    public function countSettlingIn(): int
    {
        return (int) $this->connection->query(
            'SELECT COUNT(*) FROM creatures
              WHERE owner_id IS NULL AND pound_entered_at > NOW() - INTERVAL ' . $this->graceDays . ' DAY'
        )->fetchColumn();
    }

    /**
     * The creatures THIS player brought in that they can still reclaim.
     *
     * @return Creature[]
     */
    public function reclaimableBy(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM creatures
              WHERE owner_id IS NULL AND pound_previous_owner_id = :user
                AND pound_entered_at > NOW() - INTERVAL ' . $this->graceDays . ' DAY
              ORDER BY pound_entered_at DESC'
        );
        $statement->execute([':user' => $userId]);

        return array_map([Creature::class, 'fromRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** When the grace period of a creature that arrived at $enteredAt ends. */
    public function graceEndsAt(string $enteredAt): int
    {
        return (int) strtotime($enteredAt) + $this->graceDays * 86400;
    }

    public function graceDays(): int
    {
        return $this->graceDays;
    }
}
