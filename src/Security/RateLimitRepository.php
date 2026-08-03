<?php

declare(strict_types=1);

namespace Felkyo\Security;

use PDO;

/**
 * Reads and writes rate-limit records in the database.
 *
 * @package Felkyo\Security
 *
 * WHAT THIS CLASS IS: the only place that runs SQL for the rate_limit_hits table.
 * It records one row each time a protected action is attempted, and can count how
 * many attempts happened recently. The RateLimiter service uses these two
 * operations to decide whether the next attempt is allowed.
 *
 * "action_key" is which action (e.g. "login"), and "identifier" is who is acting
 * (here, their IP address).
 */
final class RateLimitRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * Count how many attempts an identifier has made for an action within the
     * last $windowSeconds seconds.
     *
     * We do the time arithmetic in the database (NOW() - INTERVAL ... SECOND) so
     * that "recently" is measured by the database's own clock, consistently. The
     * window length is cast to an integer and placed directly into the SQL: that
     * is safe from injection because it is a number we control, not user text —
     * and INTERVAL will not accept a normal bound parameter here.
     */
    public function countRecentHits(string $actionKey, string $identifier, int $windowSeconds): int
    {
        $windowSeconds = max(0, $windowSeconds);

        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM rate_limit_hits
             WHERE action_key = :action_key
               AND identifier = :identifier
               AND created_at >= NOW() - INTERVAL ' . $windowSeconds . ' SECOND'
        );
        $statement->execute([
            ':action_key' => $actionKey,
            ':identifier' => $identifier,
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Record one attempt. created_at defaults to the current time in the database.
     */
    public function recordHit(string $actionKey, string $identifier): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO rate_limit_hits (action_key, identifier)
             VALUES (:action_key, :identifier)'
        );
        $statement->execute([
            ':action_key' => $actionKey,
            ':identifier' => $identifier,
        ]);
    }
}
