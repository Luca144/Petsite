<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use PDO;

/**
 * Reads and writes petting records (one row per time a creature is petted).
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the only place that runs SQL for the pettings table. Recording
 * each pet as its own row is what lets us answer, per person and per creature,
 * "have they petted this one recently?" (the cooldown) and "how many times has
 * this creature been petted?" (the display count, and later the currency it earns).
 */
final class PettingRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * Record that a user petted a creature, right now.
     */
    public function record(int $creatureId, int $actorUserId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO pettings (creature_id, actor_user_id) VALUES (:creature_id, :actor_user_id)'
        );
        $statement->execute([
            ':creature_id' => $creatureId,
            ':actor_user_id' => $actorUserId,
        ]);
    }

    /**
     * Has this user petted this creature within the last $withinSeconds seconds?
     * This is the cooldown check. As in the rate limiter, the number of seconds is
     * cast to an integer and placed straight into the SQL — safe because it is a
     * value we control, not user input (and INTERVAL will not take a bound param).
     */
    public function wasPettedRecentlyBy(int $actorUserId, int $creatureId, int $withinSeconds): bool
    {
        $withinSeconds = max(0, $withinSeconds);

        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM pettings
             WHERE creature_id = :creature_id
               AND actor_user_id = :actor_user_id
               AND created_at >= NOW() - INTERVAL ' . $withinSeconds . ' SECOND'
        );
        $statement->execute([
            ':creature_id' => $creatureId,
            ':actor_user_id' => $actorUserId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * How many times has this creature been petted in total (its "times petted").
     */
    public function countForCreature(int $creatureId): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM pettings WHERE creature_id = :creature_id'
        );
        $statement->execute([':creature_id' => $creatureId]);

        return (int) $statement->fetchColumn();
    }
}
