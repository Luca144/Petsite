<?php

declare(strict_types=1);

namespace Felkyo\Safety;

use PDO;

/**
 * Stores reports, and hides what they are about until somebody has looked.
 *
 * @package Felkyo\Safety
 *
 * WHAT THIS IS: the only place that runs SQL for reports. It also owns the two
 * "hide this until reviewed" flags, because hiding is part of filing a report and
 * should not be something a caller can forget to do separately.
 */
final class ReportRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * File a report. Returns false if this person has already reported this
     * exact thing.
     *
     * WHY IT RETURNS FALSE RATHER THAN FAILING: reporting twice is not an error,
     * it is a person pressing a button again because nothing visibly happened.
     * They get the same reassuring answer either way, and the queue is not filled
     * with duplicates.
     *
     * The uniqueness is enforced by the database, not by looking first — two taps
     * arriving together would both pass a check-then-insert.
     */
    public function file(
        int $reporterUserId,
        ReportSubject $subject,
        int $subjectId,
        int $aboutUserId,
        ReportReason $reason,
    ): bool {
        $statement = $this->connection->prepare(
            'INSERT IGNORE INTO reports
                (reporter_user_id, subject_type, subject_id, about_user_id, category, priority, status)
             VALUES (:reporter, :subject_type, :subject_id, :about_user, :category, :priority, \'open\')'
        );
        $statement->execute([
            ':reporter' => $reporterUserId,
            ':subject_type' => $subject->value,
            ':subject_id' => $subjectId,
            ':about_user' => $aboutUserId,
            ':category' => $reason->value,
            ':priority' => $reason->priority(),
        ]);

        return $statement->rowCount() === 1;
    }

    /**
     * Hide a creature's bio until a human has looked at it.
     *
     * Only sets the time if it is not already set, so a second report does not
     * restart the clock — the queue shows how long something has been waiting,
     * and that number should be measured from the FIRST report.
     */
    /** Who sent this postcard — but only if it was sent to $recipientId. */
    public function postcardSenderFor(int $postcardId, int $recipientId): ?int
    {
        $statement = $this->connection->prepare(
            'SELECT sender_user_id FROM postcards WHERE id = :id AND recipient_user_id = :me'
        );
        $statement->execute([':id' => $postcardId, ':me' => $recipientId]);
        $sender = $statement->fetchColumn();

        return $sender === false ? null : (int) $sender;
    }

    /** Who sent this bottle — but only if $finderId is the one who found it. */
    public function bottleSenderFor(int $bottleId, int $finderId): ?int
    {
        $statement = $this->connection->prepare(
            'SELECT sender_user_id FROM bottles WHERE id = :id AND finder_user_id = :me'
        );
        $statement->execute([':id' => $bottleId, ':me' => $finderId]);
        $sender = $statement->fetchColumn();

        return $sender === false ? null : (int) $sender;
    }

    public function hideCreatureBio(int $creatureId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures SET bio_hidden_at = NOW() WHERE id = :id AND bio_hidden_at IS NULL'
        );
        $statement->execute([':id' => $creatureId]);
    }

    /**
     * Hide a player's about text until a human has looked at it.
     */
    public function hideProfileAbout(int $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET about_hidden_at = NOW() WHERE id = :id AND about_hidden_at IS NULL'
        );
        $statement->execute([':id' => $userId]);
    }

    /**
     * How many DIFFERENT people have reported this thing.
     *
     * The most useful number a small moderation team has. One report is a
     * disagreement; five separate people is a fact.
     */
    public function countReportsFor(ReportSubject $subject, int $subjectId): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM reports WHERE subject_type = :subject_type AND subject_id = :subject_id'
        );
        $statement->execute([':subject_type' => $subject->value, ':subject_id' => $subjectId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Has this person already reported this thing? Used only to shape what the
     * page says to them, never to decide whether the report is stored.
     */
    public function hasAlreadyReported(int $reporterUserId, ReportSubject $subject, int $subjectId): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM reports
              WHERE reporter_user_id = :reporter AND subject_type = :subject_type AND subject_id = :subject_id
              LIMIT 1'
        );
        $statement->execute([
            ':reporter' => $reporterUserId,
            ':subject_type' => $subject->value,
            ':subject_id' => $subjectId,
        ]);

        return $statement->fetchColumn() !== false;
    }
}
