<?php

declare(strict_types=1);

namespace Felkyo\Texts;

use PDO;

/**
 * The changed sentences (see the site_texts migration: only CHANGES are
 * stored, the originals live in the templates and the code).
 *
 * @package Felkyo\Texts
 */
final class SiteTextRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * Every changed sentence — the one read per page. The table only holds
     * changes, so this stays small however many sentences the site has.
     *
     * @return array<string, array{body: string, original: ?string}> key => change
     */
    public function all(): array
    {
        $changes = [];
        foreach ($this->connection->query('SELECT text_key, body, original FROM site_texts')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $changes[(string) $row['text_key']] = [
                'body' => (string) $row['body'],
                'original' => $row['original'] === null ? null : (string) $row['original'],
            ];
        }

        return $changes;
    }

    /** Store (or replace) one changed sentence; $original only for messages. */
    public function set(string $key, string $body, ?string $original, int $userId): void
    {
        $this->connection->prepare(
            'INSERT INTO site_texts (text_key, body, original, updated_by_user_id, updated_at)
             VALUES (:key, :body, :original, :user, NOW())
             ON DUPLICATE KEY UPDATE body = VALUES(body), original = VALUES(original),
                                     updated_by_user_id = VALUES(updated_by_user_id), updated_at = NOW()'
        )->execute([':key' => $key, ':body' => $body, ':original' => $original, ':user' => $userId]);
    }

    /** Back to the original words: the change simply stops existing. */
    public function reset(string $key): void
    {
        $this->connection->prepare('DELETE FROM site_texts WHERE text_key = :key')->execute([':key' => $key]);
    }
}
