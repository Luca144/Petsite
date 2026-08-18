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

    /**
     * How many PAID pets this person has done in the last $withinSeconds seconds.
     *
     * WHAT "PAID" MEANS AND WHY THE JOIN IS HERE: petting earns gems only when the
     * creature belongs to somebody else — petting your own never pays. So the
     * count that matters for the daily cap is not "how often did they pet", it is
     * "how often were they paid", and answering that needs to know who owns each
     * creature. Doing the join in SQL means the answer is one trip to the database
     * rather than one trip per petting, and it cannot drift from the rule in
     * PettingService because it encodes the same condition.
     *
     * As elsewhere, the number of seconds is cast to an integer and placed straight
     * into the SQL — safe because it is a value we control, and INTERVAL will not
     * take a bound parameter.
     */
    public function countPaidPetsBy(int $actorUserId, int $withinSeconds): int
    {
        $withinSeconds = max(0, $withinSeconds);

        $statement = $this->connection->prepare(
            'SELECT COUNT(*)
               FROM pettings
               JOIN creatures ON creatures.id = pettings.creature_id
              WHERE pettings.actor_user_id = :actor_user_id
                AND creatures.owner_id <> :owner_check
                AND pettings.created_at >= NOW() - INTERVAL ' . $withinSeconds . ' SECOND'
        );
        $statement->execute([
            ':actor_user_id' => $actorUserId,
            ':owner_check' => $actorUserId,
        ]);

        return (int) $statement->fetchColumn();
    }
}
