<?php

declare(strict_types=1);

namespace Felkyo\Character;

use PDO;

/**
 * Keepsake badges: for creatures that have been kept a long time, and
 * special ones the owner gives to players (see the CreateBadges migration).
 *
 * @package Felkyo\Character
 *
 * Badges are returned as plain arrays (id, slug, name, description,
 * art_name, awarded_to, kept_days) — small read-only records, like postcard
 * designs. Awarding is INSERT IGNORE against a composite primary key, so a
 * badge can't arrive twice.
 */
final class BadgeRepository
{
    private const COLUMNS = 'b.id, b.slug, b.name, b.description, b.art_id, b.awarded_to, b.kept_days, a.stored_name AS art_name';
    private const FROM = ' FROM badges b LEFT JOIN art_files a ON a.id = b.art_id ';

    public function __construct(private PDO $connection)
    {
    }

    /** @return array<int, array> every badge, creature ones first (the panel). */
    public function all(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . self::FROM . 'ORDER BY b.awarded_to, b.kept_days, b.name'
        );

        return array_map([$this, 'row'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<int, array> badges of one audience ('creature' | 'player'). */
    public function forAudience(string $audience): array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . self::FROM . 'WHERE b.awarded_to = :to ORDER BY b.kept_days, b.name'
        );
        $statement->execute([':to' => $audience]);

        return array_map([$this, 'row'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?array
    {
        $statement = $this->connection->prepare('SELECT ' . self::COLUMNS . self::FROM . 'WHERE b.id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->row($row) : null;
    }

    /**
     * Give a creature every keepsake badge its age has reached. Idempotent,
     * one statement: the badges that are due are selected and inserted
     * together, and the primary key quietly skips any it already has.
     */
    public function awardKeptBadges(int $creatureId, int $daysKept): void
    {
        $this->connection->prepare(
            "INSERT IGNORE INTO creature_badges (creature_id, badge_id)
             SELECT :creature, id FROM badges WHERE awarded_to = 'creature' AND kept_days <= :days"
        )->execute([':creature' => $creatureId, ':days' => $daysKept]);
    }

    /** @return array<int, array> a creature's badges, oldest first. */
    public function ofCreature(int $creatureId): array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM creature_badges cb JOIN badges b ON b.id = cb.badge_id
               LEFT JOIN art_files a ON a.id = b.art_id
              WHERE cb.creature_id = :id ORDER BY b.kept_days'
        );
        $statement->execute([':id' => $creatureId]);

        return array_map([$this, 'row'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<int, array> a player's special badges, oldest first. */
    public function ofPlayer(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM player_badges pb JOIN badges b ON b.id = pb.badge_id
               LEFT JOIN art_files a ON a.id = b.art_id
              WHERE pb.user_id = :id ORDER BY pb.given_at'
        );
        $statement->execute([':id' => $userId]);

        return array_map([$this, 'row'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Give a player a special badge; false if they already have it. */
    public function giveToPlayer(int $userId, int $badgeId, string $reason): bool
    {
        $statement = $this->connection->prepare(
            'INSERT IGNORE INTO player_badges (user_id, badge_id, reason) VALUES (:u, :b, :r)'
        );
        $statement->execute([':u' => $userId, ':b' => $badgeId, ':r' => $reason]);

        return $statement->rowCount() === 1;
    }

    // ---- The panel ----

    public function slugExists(string $slug): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM badges WHERE slug = :slug');
        $statement->execute([':slug' => $slug]);

        return $statement->fetchColumn() !== false;
    }

    /** Insert (id 0) or update; values already validated by the service. */
    public function save(int $id, string $slug, string $name, string $description, string $audience, ?int $keptDays): void
    {
        if ($id === 0) {
            $this->connection->prepare(
                'INSERT INTO badges (slug, name, description, awarded_to, kept_days) VALUES (:s, :n, :d, :a, :k)'
            )->execute([':s' => $slug, ':n' => $name, ':d' => $description, ':a' => $audience, ':k' => $keptDays]);

            return;
        }
        $this->connection->prepare(
            'UPDATE badges SET name = :n, description = :d, awarded_to = :a, kept_days = :k WHERE id = :id'
        )->execute([':n' => $name, ':d' => $description, ':a' => $audience, ':k' => $keptDays, ':id' => $id]);
    }

    public function setArt(int $id, ?int $artId): void
    {
        $this->connection->prepare('UPDATE badges SET art_id = :art WHERE id = :id')->execute([':art' => $artId, ':id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->connection->prepare('DELETE FROM badges WHERE id = :id')->execute([':id' => $id]);
    }

    private function row(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'description' => (string) $row['description'],
            'art_id' => isset($row['art_id']) ? (int) $row['art_id'] : null,
            'art_name' => $row['art_name'] ?? null,
            'awarded_to' => (string) $row['awarded_to'],
            'kept_days' => isset($row['kept_days']) ? (int) $row['kept_days'] : null,
        ];
    }
}
