<?php

declare(strict_types=1);

namespace Felkyo\Admin;

use PDO;

/**
 * Writes and reads the admin audit log.
 *
 * @package Felkyo\Admin
 *
 * WHAT THIS CLASS IS: the only place that runs SQL for admin_audit_log.
 *
 * DELIBERATELY APPEND-ONLY. This class has a write method and read methods,
 * and will never grow an update or a delete. The audit log is the one witness
 * that outlives whoever it is describing: if an admin account is ever misused,
 * the log is how the owners find out what happened — so no web request,
 * however privileged, may edit it. If the table ever needs pruning for size,
 * that is a future, deliberate decision made outside this class — not a
 * method waiting here to be called.
 */
final class AuditLogRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * Record one thing that happened. The before/after arrays are stored as
     * JSON so the log can say not just "roles changed" but from-what to-what.
     */
    public function record(AuditEntry $entry): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO admin_audit_log
                (actor_user_id, action, subject_type, subject_id, detail_before, detail_after, ip)
             VALUES
                (:actor_user_id, :action, :subject_type, :subject_id, :detail_before, :detail_after, :ip)'
        );
        $statement->execute([
            ':actor_user_id' => $entry->actorUserId,
            ':action' => $entry->action->value,
            ':subject_type' => $entry->subjectType,
            ':subject_id' => $entry->subjectId,
            // JSON_UNESCAPED_UNICODE keeps names readable in the log ("Bö"
            // stays "Bö", not "Bö") — this column is read by humans.
            ':detail_before' => $entry->before === null
                ? null : json_encode($entry->before, JSON_UNESCAPED_UNICODE),
            ':detail_after' => $entry->after === null
                ? null : json_encode($entry->after, JSON_UNESCAPED_UNICODE),
            ':ip' => $entry->ip,
        ]);
    }

    /**
     * The most recent entries, newest first, with the actor's name joined in
     * (one query, not one lookup per row). Rows for CLI actions have a null
     * username and are shown as "the command line" by the template.
     *
     * $limit is our own integer, clamped and inlined — LIMIT will not take a
     * bound parameter on all drivers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit): array
    {
        $limit = max(1, min(500, $limit));

        // LEFT JOIN, not JOIN: an entry whose actor account was deleted (or
        // was the CLI) must still appear — losing audit rows to a JOIN would
        // quietly defeat the append-only promise above.
        $statement = $this->connection->query(
            'SELECT a.actor_user_id, u.username AS actor_username, a.action,
                    a.subject_type, a.subject_id, a.detail_before, a.detail_after,
                    a.ip, a.created_at
               FROM admin_audit_log a
               LEFT JOIN users u ON u.id = a.actor_user_id
              ORDER BY a.created_at DESC, a.id DESC
              LIMIT ' . $limit
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
