<?php

declare(strict_types=1);

namespace Felkyo\Moderation;

use PDO;

/**
 * Stores and serves official notices — the one-way channel's only SQL.
 *
 * @package Felkyo\Moderation
 *
 * Only ModerationService ever calls send(): a notice is always the
 * shadow of a moderation action, never a thing created on its own. The
 * player side reads unread ones (every page) and acknowledges them —
 * and acknowledging is the ONE thing a player can do here, scoped in
 * the SQL itself to their own rows.
 */
final class NoticeRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * File a notice for a player. $detail is machine-formatted (a date)
     * or null — never typed words; see the migration's note.
     */
    public function send(int $userId, string $kind, ?string $detail = null): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO moderation_notices (user_id, kind, detail) VALUES (:user, :kind, :detail)'
        );
        $statement->execute([':user' => $userId, ':kind' => $kind, ':detail' => $detail]);
    }

    /**
     * The unread notices to show at the top of this player's next page,
     * oldest first (they happened in that order). Runs on every page for
     * a logged-in player — the (user_id, read_at) index is for exactly
     * this query.
     *
     * @return ModerationNotice[]
     */
    public function unreadFor(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, kind, detail, created_at, read_at
               FROM moderation_notices
              WHERE user_id = :user AND read_at IS NULL
              ORDER BY created_at, id'
        );
        $statement->execute([':user' => $userId]);

        return array_map(
            static fn (array $row): ModerationNotice => ModerationNotice::fromRow($row),
            $statement->fetchAll() ?: []
        );
    }

    /**
     * Mark one notice read — "I've read this". The WHERE binds the row to
     * the acknowledging player, so nobody can acknowledge (or probe) a
     * notice that isn't theirs: someone else's id simply updates nothing,
     * and the caller cannot tell "not yours" from "already read".
     */
    public function acknowledge(int $noticeId, int $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE moderation_notices SET read_at = NOW()
              WHERE id = :id AND user_id = :user AND read_at IS NULL'
        );
        $statement->execute([':id' => $noticeId, ':user' => $userId]);
    }

    /**
     * Everything this player was ever told, newest first — for the
     * account-history page, so a moderator sees what has already been
     * said before saying more.
     *
     * @return ModerationNotice[]
     */
    public function allFor(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, kind, detail, created_at, read_at
               FROM moderation_notices
              WHERE user_id = :user
              ORDER BY created_at DESC, id DESC'
        );
        $statement->execute([':user' => $userId]);

        return array_map(
            static fn (array $row): ModerationNotice => ModerationNotice::fromRow($row),
            $statement->fetchAll() ?: []
        );
    }
}
