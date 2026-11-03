<?php

declare(strict_types=1);

namespace Felkyo\Social;

use PDO;

/**
 * Friends (bookmarks) and blocks (M6.1).
 *
 * @package Felkyo\Social
 *
 * Both are one-way rows with a composite primary key, written with INSERT
 * IGNORE — adding twice is harmless and can never make a duplicate.
 *
 * A FRIEND UNLOCKS NOTHING. Nothing in the codebase may check "are they
 * friends?" to permit anything: the list exists only to save clicks. If a
 * feature ever wants "friends only", that is a design change needing a fresh
 * decision (build plan M6.1).
 */
final class CompanyRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function addFriend(int $userId, int $friendId): void
    {
        $this->connection->prepare('INSERT IGNORE INTO friends (user_id, friend_user_id) VALUES (:u, :f)')
            ->execute([':u' => $userId, ':f' => $friendId]);
    }

    public function removeFriend(int $userId, int $friendId): void
    {
        $this->connection->prepare('DELETE FROM friends WHERE user_id = :u AND friend_user_id = :f')
            ->execute([':u' => $userId, ':f' => $friendId]);
    }

    public function isFriend(int $userId, int $friendId): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM friends WHERE user_id = :u AND friend_user_id = :f');
        $statement->execute([':u' => $userId, ':f' => $friendId]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * The people this player bookmarked: id and name, alphabetically.
     *
     * @return array<int, array{id: int, username: string}>
     */
    public function friendsOf(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT u.id, u.username FROM friends f JOIN users u ON u.id = f.friend_user_id
              WHERE f.user_id = :u ORDER BY u.username'
        );
        $statement->execute([':u' => $userId]);

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'username' => (string) $row['username']],
            $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Blocking also drops them from your friends — you don't bookmark someone you've blocked. */
    public function block(int $userId, int $blockedId): void
    {
        $this->connection->prepare('INSERT IGNORE INTO blocks (user_id, blocked_user_id) VALUES (:u, :b)')
            ->execute([':u' => $userId, ':b' => $blockedId]);
        $this->removeFriend($userId, $blockedId);
    }

    public function unblock(int $userId, int $blockedId): void
    {
        $this->connection->prepare('DELETE FROM blocks WHERE user_id = :u AND blocked_user_id = :b')
            ->execute([':u' => $userId, ':b' => $blockedId]);
    }

    /** Has $userId blocked $otherId? */
    public function hasBlocked(int $userId, int $otherId): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM blocks WHERE user_id = :u AND blocked_user_id = :b');
        $statement->execute([':u' => $userId, ':b' => $otherId]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * The people this player has blocked, so they can unblock from one place.
     *
     * @return array<int, array{id: int, username: string}>
     */
    public function blockedBy(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT u.id, u.username FROM blocks b JOIN users u ON u.id = b.blocked_user_id
              WHERE b.user_id = :u ORDER BY u.username'
        );
        $statement->execute([':u' => $userId]);

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'username' => (string) $row['username']],
            $statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
