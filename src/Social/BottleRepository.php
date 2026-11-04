<?php

declare(strict_types=1);

namespace Felkyo\Social;

use PDO;

/**
 * Messages in bottles and the once-a-day ledger (M6.5).
 *
 * @package Felkyo\Social
 *
 * WHY RANDOM, AND WHY NO CHOICE: nobody picks who finds their bottle, and
 * nobody picks whose bottle they find. The "pick a target" step that starts
 * every approach pattern is not forbidden here — it simply doesn't exist.
 * Keep it that way: no filters, no browsing, no re-rolling (build plan M6.5).
 */
final class BottleRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * Claim today's go at an action (house pattern 2). True the first time
     * today; false after that — the UNIQUE primary key decides, not a count.
     */
    public function claimToday(int $userId, string $actionKey): bool
    {
        $statement = $this->connection->prepare(
            'INSERT IGNORE INTO daily_claims (user_id, action_key, action_date) VALUES (:u, :a, CURDATE())'
        );
        $statement->execute([':u' => $userId, ':a' => $actionKey]);

        return $statement->rowCount() === 1;
    }

    /** Give back today's claim (used when the shore had nothing — never punish trying). */
    public function releaseToday(int $userId, string $actionKey): void
    {
        $this->connection->prepare('DELETE FROM daily_claims WHERE user_id = :u AND action_key = :a AND action_date = CURDATE()')
            ->execute([':u' => $userId, ':a' => $actionKey]);
    }

    public function hasClaimedToday(int $userId, string $actionKey): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM daily_claims WHERE user_id = :u AND action_key = :a AND action_date = CURDATE()'
        );
        $statement->execute([':u' => $userId, ':a' => $actionKey]);

        return $statement->fetchColumn() !== false;
    }

    public function setAdrift(int $senderId, string $messageKey): void
    {
        $this->connection->prepare('INSERT INTO bottles (sender_user_id, message_key) VALUES (:s, :m)')
            ->execute([':s' => $senderId, ':m' => $messageKey]);
    }

    /**
     * Wash one bottle up for this finder: a random unfound bottle that isn't
     * their own and isn't from someone they've blocked. The final UPDATE
     * re-checks "still unfound" (compare and swap), so two finders racing for
     * the same bottle can't both get it. Returns the bottle id, or null.
     *
     * ORDER BY RAND() reads every unfound bottle; fine for thousands. At far
     * larger scale, pick a random id range instead.
     */
    public function washUpFor(int $finderId): ?int
    {
        $statement = $this->connection->prepare(
            'SELECT b.id FROM bottles b
              WHERE b.finder_user_id IS NULL AND b.sender_user_id <> :me
                AND NOT EXISTS (SELECT 1 FROM blocks x WHERE x.user_id = :me2 AND x.blocked_user_id = b.sender_user_id)
              ORDER BY RAND() LIMIT 1'
        );
        $statement->execute([':me' => $finderId, ':me2' => $finderId]);
        $bottleId = $statement->fetchColumn();
        if ($bottleId === false) {
            return null;
        }

        $claim = $this->connection->prepare(
            'UPDATE bottles SET finder_user_id = :me, found_at = NOW() WHERE id = :id AND finder_user_id IS NULL'
        );
        $claim->execute([':me' => $finderId, ':id' => (int) $bottleId]);

        return $claim->rowCount() === 1 ? (int) $bottleId : null;
    }

    /**
     * Bottles this player has found, newest first, with who sent them.
     *
     * @return array<int, array{sender_id: int, sender: string, message_key: string, found_at: string}>
     */
    public function foundBy(int $finderId, int $limit): array
    {
        $statement = $this->connection->prepare(
            'SELECT b.id, b.sender_user_id, u.username, b.message_key, b.found_at
               FROM bottles b JOIN users u ON u.id = b.sender_user_id
              WHERE b.finder_user_id = :me
              ORDER BY b.found_at DESC, b.id DESC LIMIT ' . max(1, $limit)
        );
        $statement->execute([':me' => $finderId]);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'sender_id' => (int) $row['sender_user_id'],
            'sender' => (string) $row['username'],
            'message_key' => (string) $row['message_key'],
            'found_at' => (string) $row['found_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** How many of this player's bottles are still out on the water. */
    public function countAdriftFrom(int $senderId): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM bottles WHERE sender_user_id = :s AND finder_user_id IS NULL');
        $statement->execute([':s' => $senderId]);

        return (int) $statement->fetchColumn();
    }
}
