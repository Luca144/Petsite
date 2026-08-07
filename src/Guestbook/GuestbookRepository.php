<?php

declare(strict_types=1);

namespace Felkyo\Guestbook;

use PDO;

/**
 * Reads and writes guestbook entries.
 *
 * @package Felkyo\Guestbook
 *
 * WHAT THIS IS: the only place that runs SQL for the guestbook_entries table.
 * Controllers and services never touch PDO themselves — they ask this class. That
 * boundary means the storage could change without any other file noticing
 * (CLAUDE.md section 5).
 *
 * Every query here joins the users table, because an entry is never shown without
 * saying who left it. Fetching the name in the same query avoids the classic
 * mistake of running one extra query per entry just to look up a username.
 */
final class GuestbookRepository
{
    /**
     * The columns every query below selects. Listing them explicitly (never
     * SELECT *) keeps the contract with GuestbookEntry visible and stops new
     * columns leaking out by accident.
     */
    private const SELECTED_COLUMNS =
        'guestbook_entries.id,
         guestbook_entries.creature_id,
         guestbook_entries.author_user_id,
         guestbook_entries.message_key,
         guestbook_entries.created_at,
         guestbook_entries.updated_at,
         users.username AS author_username';

    public function __construct(private PDO $connection)
    {
    }

    /**
     * The entries in one creature's guestbook, newest first.
     *
     * @return GuestbookEntry[]
     */
    public function listForCreature(int $creatureId, int $limit): array
    {
        // The limit is a number we control (it comes from config), and MySQL will
        // not accept a bound parameter in LIMIT, so it is cast to an integer and
        // placed directly into the statement. The creature id — which DOES come
        // from the URL — stays a bound parameter, as all user input must.
        $limit = max(1, $limit);

        $statement = $this->connection->prepare(
            'SELECT ' . self::SELECTED_COLUMNS . '
               FROM guestbook_entries
               JOIN users ON users.id = guestbook_entries.author_user_id
              WHERE guestbook_entries.creature_id = :creature_id
              ORDER BY guestbook_entries.created_at DESC, guestbook_entries.id DESC
              LIMIT ' . $limit
        );
        $statement->execute([':creature_id' => $creatureId]);

        $entries = [];
        foreach ($statement->fetchAll() as $row) {
            $entries[] = GuestbookEntry::fromRow($row);
        }

        return $entries;
    }

    /**
     * The entry this person left on this creature, if they have left one.
     *
     * This is how we answer "have they already signed?" — which decides whether
     * signing creates a new entry or changes the existing one.
     */
    public function findByCreatureAndAuthor(int $creatureId, int $authorUserId): ?GuestbookEntry
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::SELECTED_COLUMNS . '
               FROM guestbook_entries
               JOIN users ON users.id = guestbook_entries.author_user_id
              WHERE guestbook_entries.creature_id = :creature_id
                AND guestbook_entries.author_user_id = :author_user_id'
        );
        $statement->execute([
            ':creature_id' => $creatureId,
            ':author_user_id' => $authorUserId,
        ]);

        $row = $statement->fetch();

        return $row === false ? null : GuestbookEntry::fromRow($row);
    }

    /**
     * Record a new signature. The unique index on (creature_id, author_user_id)
     * means a second one for the same pair simply cannot be created.
     */
    public function add(int $creatureId, int $authorUserId, string $messageKey): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO guestbook_entries (creature_id, author_user_id, message_key)
             VALUES (:creature_id, :author_user_id, :message_key)'
        );
        $statement->execute([
            ':creature_id' => $creatureId,
            ':author_user_id' => $authorUserId,
            ':message_key' => $messageKey,
        ]);
    }

    /**
     * Swap an existing entry for a different message, and stamp the time of the
     * change — that stamp is what the once-a-day limit is measured from.
     */
    public function changeMessage(int $entryId, string $messageKey): void
    {
        $statement = $this->connection->prepare(
            'UPDATE guestbook_entries
                SET message_key = :message_key, updated_at = NOW()
              WHERE id = :id'
        );
        $statement->execute([
            ':message_key' => $messageKey,
            ':id' => $entryId,
        ]);
    }

    /**
     * Was this entry created or changed within the last $withinSeconds seconds?
     * This is the once-a-day check.
     *
     * We ask the DATABASE to compare the times rather than doing it in PHP, so the
     * comparison always happens in one clock and one timezone. (As in
     * PettingRepository, the number of seconds is cast to an integer and placed
     * straight into the SQL — it is a value we control, not user input, and MySQL
     * will not accept a bound parameter inside INTERVAL.)
     */
    public function wasChangedRecently(int $entryId, int $withinSeconds): bool
    {
        $withinSeconds = max(0, $withinSeconds);

        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM guestbook_entries
              WHERE id = :id
                AND updated_at >= NOW() - INTERVAL ' . $withinSeconds . ' SECOND'
        );
        $statement->execute([':id' => $entryId]);

        return (int) $statement->fetchColumn() > 0;
    }
}
