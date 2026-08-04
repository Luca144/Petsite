<?php

declare(strict_types=1);

namespace Felkyo\Exploration;

use PDO;

/**
 * Tracks how many exploration clicks a user has used in each area's current window.
 *
 * @package Felkyo\Exploration
 *
 * WHAT THIS IS: the only place that runs SQL for the exploration_visits table.
 * There is one row per (user, area). It remembers how many clicks the user has
 * spent and when the current window started, so the per-visit limit can be
 * enforced and refreshed.
 */
final class ExplorationRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * How many clicks has the user used in the CURRENT window for this area? A
     * window counts as current only if it started within the last $windowSeconds.
     * Returns null when there is no current window (no row, or it has expired) —
     * which the service treats as "time to start a fresh visit".
     *
     * The window length is cast to an integer and placed straight into the SQL
     * (safe: it is our own value; INTERVAL will not take a bound parameter).
     */
    public function clicksUsedInCurrentWindow(int $userId, string $areaSlug, int $windowSeconds): ?int
    {
        $windowSeconds = max(0, $windowSeconds);

        $statement = $this->connection->prepare(
            'SELECT clicks_used FROM exploration_visits
              WHERE user_id = :user_id
                AND area_slug = :area_slug
                AND window_started_at >= NOW() - INTERVAL ' . $windowSeconds . ' SECOND'
        );
        $statement->execute([':user_id' => $userId, ':area_slug' => $areaSlug]);

        $clicksUsed = $statement->fetchColumn();

        return $clicksUsed === false ? null : (int) $clicksUsed;
    }

    /**
     * Begin a fresh visit window now: set clicks_used back to 0 and the window
     * start to now. If a row already exists for this (user, area) it is reset;
     * otherwise a new one is created. The unique index on (user_id, area_slug)
     * makes this "insert or update" safe.
     */
    public function startFreshWindow(int $userId, string $areaSlug): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO exploration_visits (user_id, area_slug, clicks_used, window_started_at)
             VALUES (:user_id, :area_slug, 0, NOW())
             ON DUPLICATE KEY UPDATE clicks_used = 0, window_started_at = NOW()'
        );
        $statement->execute([':user_id' => $userId, ':area_slug' => $areaSlug]);
    }

    /**
     * Count one more click used in this area's current window.
     */
    public function recordClick(int $userId, string $areaSlug): void
    {
        $statement = $this->connection->prepare(
            'UPDATE exploration_visits SET clicks_used = clicks_used + 1
              WHERE user_id = :user_id AND area_slug = :area_slug'
        );
        $statement->execute([':user_id' => $userId, ':area_slug' => $areaSlug]);
    }
}
