<?php

declare(strict_types=1);

namespace Felkyo\Admin;

use PDO;

/**
 * Every editor's undo: one snapshot per save, newest N kept, per thing.
 *
 * @package Felkyo\Admin
 *
 * The only class that runs SQL for content_versions. It replaced the
 * species-only version table when the item and shop editors arrived
 * (M2.4): one undo engine, not three copies — the build plan's "engines
 * before content", applied to the panel itself.
 *
 * A snapshot is the whole editable state of one thing as JSON, with art as
 * REFERENCES, never bytes. WHAT goes into a snapshot and what restoring
 * means is each editor service's business; this class only keeps them,
 * lists them, and hands one back. It lives in the Admin namespace because
 * versioning is a panel concern, whatever the content is.
 *
 * $contentType is always a value the CALLING SERVICE hard-codes
 * ('species', 'item', 'shop') — never anything from a request.
 */
final class ContentVersionRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * Keep a snapshot, and prune this thing's history to the newest $keep.
     */
    public function record(string $contentType, int $contentId, array $snapshot, ?int $savedByUserId, int $keep): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO content_versions (content_type, content_id, snapshot, saved_by_user_id)
             VALUES (:content_type, :content_id, :snapshot, :saved_by)'
        );
        $statement->execute([
            ':content_type' => $contentType,
            ':content_id' => $contentId,
            // Readable in the database (names survive as names) — this JSON
            // is also what a human reads when digging through history.
            ':snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            ':saved_by' => $savedByUserId,
        ]);

        // Prune: delete everything older than the newest $keep. MySQL will
        // not delete from a table it is subquerying, so the boundary id is
        // found through a derived table. $keep is our own int, clamped.
        $keep = max(1, min(100, $keep));
        $prune = $this->connection->prepare(
            'DELETE FROM content_versions
              WHERE content_type = :type_a AND content_id = :id_a
                AND id < (SELECT oldest_kept FROM (
                        SELECT MIN(id) AS oldest_kept FROM (
                            SELECT id FROM content_versions
                             WHERE content_type = :type_b AND content_id = :id_b
                             ORDER BY id DESC LIMIT ' . $keep . '
                        ) AS newest
                    ) AS boundary)'
        );
        $prune->execute([
            ':type_a' => $contentType, ':id_a' => $contentId,
            ':type_b' => $contentType, ':id_b' => $contentId,
        ]);
    }

    /**
     * One thing's history, newest first: when, who, and the snapshot itself
     * (the screens show the name it held then).
     *
     * @return array<int, array{id: int, created_at: string, saved_by: ?string, snapshot: array}>
     */
    public function listFor(string $contentType, int $contentId): array
    {
        $statement = $this->connection->prepare(
            'SELECT v.id, v.created_at, v.snapshot, u.username AS saved_by
               FROM content_versions v
               LEFT JOIN users u ON u.id = v.saved_by_user_id
              WHERE v.content_type = :content_type AND v.content_id = :content_id
              ORDER BY v.id DESC'
        );
        $statement->execute([':content_type' => $contentType, ':content_id' => $contentId]);

        $versions = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $versions[] = [
                'id' => (int) $row['id'],
                'created_at' => (string) $row['created_at'],
                'saved_by' => $row['saved_by'] ?? null,
                'snapshot' => json_decode((string) $row['snapshot'], true) ?: [],
            ];
        }

        return $versions;
    }

    /**
     * One snapshot by its id — but only if it belongs to the given thing.
     * Type and id come from the page the person is ON; requiring all three
     * to match means a version id from some other thing's history is a
     * null, never a cross-content restore.
     *
     * @return array|null the decoded snapshot
     */
    public function findSnapshot(int $versionId, string $contentType, int $contentId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT snapshot FROM content_versions
              WHERE id = :id AND content_type = :content_type AND content_id = :content_id
              LIMIT 1'
        );
        $statement->execute([
            ':id' => $versionId,
            ':content_type' => $contentType,
            ':content_id' => $contentId,
        ]);
        $json = $statement->fetchColumn();

        if (!is_string($json)) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
