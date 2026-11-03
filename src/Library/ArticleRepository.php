<?php

declare(strict_types=1);

namespace Felkyo\Library;

use PDO;

/**
 * Reads and writes the Felkyo Gazette's articles.
 *
 * @package Felkyo\Library
 *
 * The public reads (published(), findPublishedBySlug()) only ever return
 * PUBLISHED pieces — a draft is invisible outside the panel no matter what
 * address somebody types.
 */
final class ArticleRepository
{
    // 'a' is the article, 'pic' its picture's art_files row (LEFT JOIN: most
    // articles may have no picture).
    private const COLUMNS = 'a.id, a.slug, a.title, a.summary, a.body, a.art_id, a.status,
             a.published_at, pic.stored_name AS art_name';
    private const FROM = ' FROM articles a LEFT JOIN art_files pic ON pic.id = a.art_id ';

    public function __construct(private PDO $connection)
    {
    }

    /**
     * Published articles, newest first — the Gazette's front page. Uses the
     * (status, published_at) index.
     *
     * @return Article[]
     */
    public function published(int $limit): array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . self::FROM .
            "WHERE a.status = 'published' ORDER BY a.published_at DESC, a.id DESC LIMIT :limit"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map([Article::class, 'fromRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** One published article by its address, or null. */
    public function findPublishedBySlug(string $slug): ?Article
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . self::FROM . "WHERE a.slug = :slug AND a.status = 'published' LIMIT 1"
        );
        $statement->execute([':slug' => $slug]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? Article::fromRow($row) : null;
    }

    /**
     * Every article, drafts included, newest first — the panel's list.
     *
     * @return Article[]
     */
    public function all(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . self::FROM . 'ORDER BY a.id DESC'
        );

        return array_map([Article::class, 'fromRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Any article by id, drafts included (the panel's editor). */
    public function findById(int $id): ?Article
    {
        $statement = $this->connection->prepare('SELECT ' . self::COLUMNS . self::FROM . 'WHERE a.id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? Article::fromRow($row) : null;
    }

    public function slugExists(string $slug): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM articles WHERE slug = :slug LIMIT 1');
        $statement->execute([':slug' => $slug]);

        return $statement->fetchColumn() !== false;
    }

    // ---- Writes ----

    /** Start a draft; returns its id. */
    public function create(string $slug, string $title): int
    {
        $this->connection->prepare('INSERT INTO articles (slug, title) VALUES (:slug, :title)')
            ->execute([':slug' => $slug, ':title' => $title]);

        return (int) $this->connection->lastInsertId();
    }

    public function updateWords(int $id, string $title, ?string $summary, ?string $body): void
    {
        $this->connection->prepare(
            'UPDATE articles SET title = :title, summary = :summary, body = :body WHERE id = :id'
        )->execute([':title' => $title, ':summary' => $summary, ':body' => $body, ':id' => $id]);
    }

    public function setArt(int $id, ?int $artId): void
    {
        $this->connection->prepare('UPDATE articles SET art_id = :art_id WHERE id = :id')
            ->execute([':art_id' => $artId, ':id' => $id]);
    }

    /**
     * Publish or take back to draft. The first publish stamps the date the
     * Gazette shows; republishing later keeps that original date, so fixing a
     * typo doesn't bump an old piece back to the top.
     */
    public function setStatus(int $id, string $status): void
    {
        $this->connection->prepare(
            'UPDATE articles
                SET status = :status,
                    published_at = CASE WHEN :status2 = \'published\' AND published_at IS NULL THEN NOW() ELSE published_at END
              WHERE id = :id'
        )->execute([':status' => $status, ':status2' => $status, ':id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->connection->prepare('DELETE FROM articles WHERE id = :id')->execute([':id' => $id]);
    }
}
