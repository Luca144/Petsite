<?php

declare(strict_types=1);

namespace Felkyo\Themes;

use PDO;

/**
 * Reads and writes themes, and which one each player has chosen (M4).
 *
 * @package Felkyo\Themes
 *
 * forUser() runs on every page a logged-in player loads (the layout needs
 * the theme). It is one primary-key lookup with a join — cheap. If themes
 * ever get heavy, this is the place to add a per-request cache.
 */
final class ThemeRepository
{
    private const COLUMNS = 't.id, t.slug, t.name, t.tokens, t.easy_reading, t.status';

    public function __construct(private PDO $connection)
    {
    }

    /** The PUBLISHED theme this player chose, or null for the default look. */
    public function forUser(int $userId): ?Theme
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM users u JOIN themes t ON t.id = u.theme_id
              WHERE u.id = :id AND t.status = \'published\''
        );
        $statement->execute([':id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? Theme::fromRow($row) : null;
    }

    /** @return Theme[] every published theme, by name — what players choose from. */
    public function published(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . " FROM themes t WHERE t.status = 'published' ORDER BY t.name"
        );

        return array_map([Theme::class, 'fromRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return Theme[] every theme, drafts included (the panel). */
    public function all(): array
    {
        $statement = $this->connection->query('SELECT ' . self::COLUMNS . ' FROM themes t ORDER BY t.name');

        return array_map([Theme::class, 'fromRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?Theme
    {
        $statement = $this->connection->prepare('SELECT ' . self::COLUMNS . ' FROM themes t WHERE t.id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? Theme::fromRow($row) : null;
    }

    /**
     * Set a player's theme. Only a PUBLISHED theme can be chosen — the
     * condition is in the statement, so a draft's id typed into the form
     * changes nothing. Null goes back to the default.
     */
    public function chooseForUser(int $userId, ?int $themeId): bool
    {
        if ($themeId === null) {
            $this->connection->prepare('UPDATE users SET theme_id = NULL WHERE id = :id')->execute([':id' => $userId]);

            return true;
        }

        $statement = $this->connection->prepare(
            "UPDATE users SET theme_id = :theme
              WHERE id = :id AND EXISTS (SELECT 1 FROM themes WHERE id = :theme2 AND status = 'published')"
        );
        $statement->execute([':theme' => $themeId, ':theme2' => $themeId, ':id' => $userId]);

        return $statement->rowCount() > 0 || $this->forUser($userId)?->id === $themeId;
    }

    // ---- The panel ----

    public function slugExists(string $slug): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM themes WHERE slug = :slug');
        $statement->execute([':slug' => $slug]);

        return $statement->fetchColumn() !== false;
    }

    public function create(string $slug, string $name): int
    {
        $this->connection->prepare("INSERT INTO themes (slug, name, tokens) VALUES (:slug, :name, '{}')")
            ->execute([':slug' => $slug, ':name' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, string> $tokens already cleaned */
    public function save(int $id, string $name, array $tokens, bool $easyReading): void
    {
        $this->connection->prepare(
            'UPDATE themes SET name = :name, tokens = :tokens, easy_reading = :easy WHERE id = :id'
        )->execute([':name' => $name, ':tokens' => json_encode($tokens), ':easy' => $easyReading ? 1 : 0, ':id' => $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->connection->prepare('UPDATE themes SET status = :status WHERE id = :id')
            ->execute([':status' => $status, ':id' => $id]);
    }

    /** Players using it fall back to the default look (FK SET NULL). */
    public function delete(int $id): void
    {
        $this->connection->prepare('DELETE FROM themes WHERE id = :id')->execute([':id' => $id]);
    }
}
