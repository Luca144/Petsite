<?php

declare(strict_types=1);

namespace Felkyo\Moderation;

use PDO;

/**
 * All the SQL behind moderating: account states, forced words, and the
 * queue's questions about reports.
 *
 * @package Felkyo\Moderation
 *
 * DELIBERATELY OVER THE USUAL SHAPE in one way: this class reaches into
 * users, creatures and reports directly instead of going through their
 * own repositories. Moderation's writes are different in kind from the
 * site's writes — forcing a name skips the player-facing validation ON
 * PURPOSE (the forced value is our own neutral word, not player input),
 * and clearing a bio must also clear its hidden-flag in the same breath.
 * Spreading those special cases across three player-facing repositories
 * would put "the moderator's override" next to code paths players reach.
 * Kept here, everything a moderator can touch is in one reviewable file.
 */
final class ModerationRepository
{
    public function __construct(private PDO $connection)
    {
    }

    // ---- Account states (read fresh, never cached) ----

    /**
     * The two timed states for one account. NULLs mean good standing;
     * times in the past mean the state has expired on its own.
     *
     * @return array{muted_until: ?string, suspended_until: ?string}
     */
    public function stateFor(int $userId): array
    {
        // Whether each state is CURRENTLY active is computed by the
        // database clock (NOW()), not PHP's — the same clock that wrote
        // the timestamps, so an expiry is never off by a timezone.
        $statement = $this->connection->prepare(
            'SELECT muted_until, suspended_until,
                    (muted_until IS NOT NULL AND muted_until > NOW()) AS is_muted,
                    (suspended_until IS NOT NULL AND suspended_until > NOW()) AS is_suspended
               FROM users WHERE id = :id'
        );
        $statement->execute([':id' => $userId]);
        $row = $statement->fetch();

        return [
            'muted_until' => $row['muted_until'] ?? null,
            'suspended_until' => $row['suspended_until'] ?? null,
            'is_muted' => (bool) ($row['is_muted'] ?? false),
            'is_suspended' => (bool) ($row['is_suspended'] ?? false),
        ];
    }

    /** Is this player muted right now? The per-write check. */
    public function isMuted(int $userId): bool
    {
        return $this->stateFor($userId)['is_muted'];
    }

    /** Is this account suspended right now? The login and per-request check. */
    public function isSuspended(int $userId): bool
    {
        return $this->stateFor($userId)['is_suspended'];
    }

    /** Set (or with null, lift) the mute. The service owns the duration. */
    public function setMutedUntil(int $userId, ?string $until): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET muted_until = :until WHERE id = :id'
        );
        $statement->execute([':until' => $until, ':id' => $userId]);
    }

    /** Set (or with null, lift) the suspension. */
    public function setSuspendedUntil(int $userId, ?string $until): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET suspended_until = :until WHERE id = :id'
        );
        $statement->execute([':until' => $until, ':id' => $userId]);
    }

    // ---- Forced words (the neutral defaults; see the class note) ----

    /**
     * Force a player's name to the given neutral value. The skeleton
     * travels with it — the impersonation guard compares skeletons, and a
     * renamed account whose old skeleton lingered would block its own old
     * name forever while failing to guard the new one.
     */
    public function forceUsername(int $userId, string $name, string $skeleton): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET username = :name, username_skeleton = :skeleton WHERE id = :id'
        );
        $statement->execute([':name' => $name, ':skeleton' => $skeleton, ':id' => $userId]);
    }

    /** Force a creature's name to the given neutral value. */
    public function forceCreatureName(int $creatureId, string $name): void
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures SET name = :name WHERE id = :id'
        );
        $statement->execute([':name' => $name, ':id' => $creatureId]);
    }

    /**
     * Remove a creature's bio AND its hidden-flag together: after removal
     * there is nothing left to keep hidden, and a lingering flag would
     * hide whatever innocent bio the owner writes next.
     */
    public function clearCreatureBio(int $creatureId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures SET bio = NULL, bio_hidden_at = NULL WHERE id = :id'
        );
        $statement->execute([':id' => $creatureId]);
    }

    /** Remove a player's about text and its hidden-flag together. */
    public function clearProfileAbout(int $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET about = NULL, about_hidden_at = NULL WHERE id = :id'
        );
        $statement->execute([':id' => $userId]);
    }

    /**
     * Bring a hidden creature bio back unchanged — the dismiss path: the
     * report was judged wrong, so the words go back up.
     */
    public function unhideCreatureBio(int $creatureId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures SET bio_hidden_at = NULL WHERE id = :id'
        );
        $statement->execute([':id' => $creatureId]);
    }

    /** Bring a hidden about text back unchanged. */
    public function unhideProfileAbout(int $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET about_hidden_at = NULL WHERE id = :id'
        );
        $statement->execute([':id' => $userId]);
    }

    // ---- The queue's reads ----

    /**
     * Every open report, most urgent first, oldest first within a
     * priority — the queue order the reports table is indexed for.
     * Reporter and subject usernames ride along so the queue never does
     * an N+1 walk for names.
     *
     * @return array<int, array<string, mixed>>
     */
    public function openReports(): array
    {
        // Two joined aliases of users: the person who reported, and the
        // person reported about. The reporter's track record (how many of
        // their CLOSED reports were dismissed) rides along too — the
        // spite-report signal ReportService's plan requires this queue to
        // surface.
        $statement = $this->connection->query(
            'SELECT r.id, r.subject_type, r.subject_id, r.category, r.priority,
                    r.status, r.created_at, r.reporter_user_id, r.about_user_id,
                    reporter.username AS reporter_name,
                    about.username AS about_name,
                    (SELECT COUNT(*) FROM reports same
                      WHERE same.subject_type = r.subject_type
                        AND same.subject_id = r.subject_id) AS voices,
                    (SELECT COUNT(*) FROM reports filed
                      WHERE filed.reporter_user_id = r.reporter_user_id
                        AND filed.status <> \'open\') AS reporter_closed,
                    (SELECT COUNT(*) FROM reports filed
                      WHERE filed.reporter_user_id = r.reporter_user_id
                        AND filed.status = \'dismissed\') AS reporter_dismissed
               FROM reports r
               JOIN users reporter ON reporter.id = r.reporter_user_id
               JOIN users about ON about.id = r.about_user_id
              WHERE r.status = \'open\'
              ORDER BY r.priority, r.created_at, r.id'
        );

        return $statement->fetchAll() ?: [];
    }

    /**
     * One report by id — the actions work from this, so a stale form
     * posting about an already-closed report can be answered honestly.
     *
     * @return array<string, mixed>|null
     */
    public function findReport(int $reportId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, reporter_user_id, subject_type, subject_id, about_user_id,
                    category, priority, status, created_at
               FROM reports WHERE id = :id'
        );
        $statement->execute([':id' => $reportId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Close a report as dismissed or actioned — but never reopen one:
     * the WHERE keeps this a one-way door, so two moderators acting on
     * the same report don't flip it back and forth.
     */
    public function closeReport(int $reportId, string $status): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE reports SET status = :status WHERE id = :id AND status = \'open\''
        );
        $statement->execute([':status' => $status, ':id' => $reportId]);

        return $statement->rowCount() === 1;
    }

    // ---- The account-history page's reads ----

    /**
     * Every report ABOUT one account, newest first, any status — the
     * pattern view. The reporter's name rides along.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reportsAbout(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT r.id, r.subject_type, r.subject_id, r.category, r.priority,
                    r.status, r.created_at, reporter.username AS reporter_name
               FROM reports r
               JOIN users reporter ON reporter.id = r.reporter_user_id
              WHERE r.about_user_id = :user
              ORDER BY r.created_at DESC, r.id DESC'
        );
        $statement->execute([':user' => $userId]);

        return $statement->fetchAll() ?: [];
    }

    /**
     * What one account has reported, and how those reports fared —
     * their credibility, in numbers.
     *
     * @return array{filed: int, dismissed: int, actioned: int}
     */
    public function reporterRecord(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) AS filed,
                    SUM(status = \'dismissed\') AS dismissed,
                    SUM(status = \'actioned\') AS actioned
               FROM reports WHERE reporter_user_id = :user'
        );
        $statement->execute([':user' => $userId]);
        $row = $statement->fetch() ?: [];

        return [
            'filed' => (int) ($row['filed'] ?? 0),
            'dismissed' => (int) ($row['dismissed'] ?? 0),
            'actioned' => (int) ($row['actioned'] ?? 0),
        ];
    }

    /**
     * The guestbook entries one account has signed, newest first, with
     * whose creature each one is on — the "pattern of who keeps signing"
     * that ReportSubject's notes name as the guestbook risk.
     *
     * @return array<int, array<string, mixed>>
     */
    public function guestbookEntriesBy(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT g.id, g.message_key, g.created_at,
                    c.name AS creature_name, c.id AS creature_id,
                    owner.username AS owner_name
               FROM guestbook_entries g
               JOIN creatures c ON c.id = g.creature_id
               JOIN users owner ON owner.id = c.owner_id
              WHERE g.author_user_id = :user
              ORDER BY g.created_at DESC, g.id DESC'
        );
        $statement->execute([':user' => $userId]);

        return $statement->fetchAll() ?: [];
    }
}
