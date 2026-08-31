<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use PDO;

/**
 * The species editor's undo: one snapshot per save, newest N kept.
 *
 * @package Felkyo\Creatures
 *
 * The only class that runs SQL for species_versions. A snapshot is the whole
 * editable state of one species as JSON — its words, numbers, flags and its
 * stages' art REFERENCES (never bytes: art is content-addressed in the
 * store, so a version is a few hundred bytes however big the pictures are).
 *
 * Snapshots are WRITTEN here and DECIDED elsewhere: SpeciesEditorService
 * builds them before a change and applies them on revert. This class only
 * keeps and returns them.
 */
final class SpeciesVersionRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * Keep a snapshot, and prune the species' history to the newest $keep.
     */
    public function record(int $speciesId, array $snapshot, ?int $savedByUserId, int $keep): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO species_versions (species_id, snapshot, saved_by_user_id)
             VALUES (:species_id, :snapshot, :saved_by)'
        );
        $statement->execute([
            ':species_id' => $speciesId,
            // Readable in the database (names survive as names) — this JSON
            // is also what a human reads when digging through history.
            ':snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            ':saved_by' => $savedByUserId,
        ]);

        // Prune: delete everything older than the newest $keep. MySQL will
        // not delete from a table it is subquerying, so the newest-Nth id is
        // found in a derived table first. $keep is our own int, clamped.
        $keep = max(1, min(100, $keep));
        $prune = $this->connection->prepare(
            'DELETE FROM species_versions
              WHERE species_id = :species_a
                AND id < (SELECT oldest_kept FROM (
                        SELECT MIN(id) AS oldest_kept FROM (
                            SELECT id FROM species_versions
                             WHERE species_id = :species_b
                             ORDER BY id DESC LIMIT ' . $keep . '
                        ) AS newest
                    ) AS boundary)'
        );
        $prune->execute([':species_a' => $speciesId, ':species_b' => $speciesId]);
    }

    /**
     * One species' history, newest first, for the version list: when, who,
     * and the snapshot itself (the screen shows the name it held then).
     *
     * @return array<int, array{id: int, created_at: string, saved_by: ?string, snapshot: array}>
     */
    public function listFor(int $speciesId): array
    {
        $statement = $this->connection->prepare(
            'SELECT v.id, v.created_at, v.snapshot, u.username AS saved_by
               FROM species_versions v
               LEFT JOIN users u ON u.id = v.saved_by_user_id
              WHERE v.species_id = :species_id
              ORDER BY v.id DESC'
        );
        $statement->execute([':species_id' => $speciesId]);

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
     * One snapshot by its id — but only if it belongs to the given species.
     * The species id comes from the URL the artist is ON; requiring both to
     * match means a version id from another species' history is a null, not
     * a cross-species restore.
     *
     * @return array|null the decoded snapshot
     */
    public function findSnapshot(int $versionId, int $speciesId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT snapshot FROM species_versions
              WHERE id = :id AND species_id = :species_id LIMIT 1'
        );
        $statement->execute([':id' => $versionId, ':species_id' => $speciesId]);
        $json = $statement->fetchColumn();

        if (!is_string($json)) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
