<?php

declare(strict_types=1);

namespace Felkyo\Look;

use PDO;

/**
 * The history of the fine-tuning CSS box — append-only.
 *
 * @package Felkyo\Look
 *
 * Every save adds a row; nothing is ever updated or deleted. The CSS the
 * site actually prints is kept in site_settings (SiteLook::KEY_CUSTOM_CSS)
 * so reading it costs no query; this table is the undo history behind it.
 */
final class CustomCssRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function add(string $css, string $note, int $savedBy): void
    {
        $this->connection->prepare(
            'INSERT INTO custom_css_versions (css, note, saved_by_user_id) VALUES (:css, :note, :by)'
        )->execute([':css' => $css, ':note' => $note, ':by' => $savedBy]);
    }

    /**
     * The newest versions first, with who saved them — for the history list.
     *
     * @return array<int, array{id: int, note: string, saved_by: ?string, saved_at: string, length: int}>
     */
    public function history(int $limit): array
    {
        $statement = $this->connection->query(
            'SELECT v.id, v.note, u.username, v.saved_at, CHAR_LENGTH(v.css) AS length
               FROM custom_css_versions v LEFT JOIN users u ON u.id = v.saved_by_user_id
              ORDER BY v.id DESC LIMIT ' . max(1, $limit)
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'note' => (string) $row['note'],
            'saved_by' => $row['username'] === null ? null : (string) $row['username'],
            'saved_at' => (string) $row['saved_at'],
            'length' => (int) $row['length'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** One old version's CSS, or null if there is no such version. */
    public function cssOf(int $id): ?string
    {
        $statement = $this->connection->prepare('SELECT css FROM custom_css_versions WHERE id = :id');
        $statement->execute([':id' => $id]);
        $css = $statement->fetchColumn();

        return $css === false ? null : (string) $css;
    }
}
