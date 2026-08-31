<?php

declare(strict_types=1);

namespace Felkyo\Art;

use PDO;

/**
 * Reads and writes the art_files table — the site's memory of its pictures.
 *
 * @package Felkyo\Art
 *
 * The only class that runs SQL against art_files. Every query lists its
 * columns; the uploader's name is JOINed in where a screen shows it, so the
 * Pictures grid is one query, not one per picture.
 */
final class ArtRepository
{
    // The columns of one picture, prefixed for the JOINs below.
    private const COLUMNS = 'a.id, a.stored_name, a.kind, a.original_name, a.type, a.width, a.height,
                             a.bytes, a.is_animated, a.uploaded_by_user_id, a.created_at,
                             u.username AS uploader_name';

    public function __construct(private PDO $connection)
    {
    }

    /**
     * One picture by id, or null. Used before a delete, to check it exists
     * and who uploaded it.
     */
    public function findById(int $id): ?ArtFile
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . '
               FROM art_files a
               LEFT JOIN users u ON u.id = a.uploaded_by_user_id
              WHERE a.id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);

        return $this->rowToArtOrNull($statement->fetch(PDO::FETCH_ASSOC));
    }

    /**
     * One picture by its stored (content-hash) name, or null. This is what
     * the serving route asks first — it never reads a byte from disk for a
     * name this table does not know — and what makes a duplicate upload a
     * friendly "already here" rather than a second row.
     */
    public function findByStoredName(string $storedName): ?ArtFile
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . '
               FROM art_files a
               LEFT JOIN users u ON u.id = a.uploaded_by_user_id
              WHERE a.stored_name = :stored_name LIMIT 1'
        );
        $statement->execute([':stored_name' => $storedName]);

        return $this->rowToArtOrNull($statement->fetch(PDO::FETCH_ASSOC));
    }

    /**
     * Every picture, newest first, for the Pictures screen. One query, with
     * the uploader's name joined in. When the store grows past a few hundred
     * pictures this wants paging or a per-kind filter — M2.3's per-species
     * picker will ask by kind, which the (kind, created_at) index serves.
     *
     * @return ArtFile[]
     */
    public function all(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . '
               FROM art_files a
               LEFT JOIN users u ON u.id = a.uploaded_by_user_id
              ORDER BY a.created_at DESC, a.id DESC'
        );

        return array_map([ArtFile::class, 'fromRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Record a validated, stored picture. Returns the new row.
     */
    public function insert(UploadedImage $image, ArtKind $kind, ?int $uploadedByUserId): ArtFile
    {
        $statement = $this->connection->prepare(
            'INSERT INTO art_files
                (stored_name, kind, original_name, type, width, height, bytes, is_animated, uploaded_by_user_id)
             VALUES
                (:stored_name, :kind, :original_name, :type, :width, :height, :bytes, :is_animated, :uploaded_by)'
        );
        $statement->execute([
            ':stored_name' => $image->storedName(),
            ':kind' => $kind->value,
            ':original_name' => $image->originalName,
            ':type' => $image->type,
            ':width' => $image->width,
            ':height' => $image->height,
            ':bytes' => $image->byteCount(),
            ':is_animated' => $image->isAnimated ? 1 : 0,
            ':uploaded_by' => $uploadedByUserId,
        ]);

        $saved = $this->findById((int) $this->connection->lastInsertId());
        if ($saved === null) {
            throw new \RuntimeException('Failed to load the picture that was just recorded.');
        }

        return $saved;
    }

    /**
     * Forget a picture. Returns true if a row was removed.
     */
    public function delete(int $id): bool
    {
        $statement = $this->connection->prepare('DELETE FROM art_files WHERE id = :id');
        $statement->execute([':id' => $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * How many bytes the store holds altogether — the number the Pictures
     * screen compares with the "disk getting full" threshold. Summed from
     * this table rather than measured on disk, so it is the same answer on
     * a laptop, in a test, and on the volume.
     */
    public function totalBytes(): int
    {
        return (int) $this->connection->query('SELECT COALESCE(SUM(bytes), 0) FROM art_files')->fetchColumn();
    }

    private function rowToArtOrNull(mixed $row): ?ArtFile
    {
        return is_array($row) ? ArtFile::fromRow($row) : null;
    }
}
