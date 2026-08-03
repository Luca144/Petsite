<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use PDO;

/**
 * Reads and writes creatures in the database.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the only place that runs SQL for the creatures table. Even though
 * Core gives a player just one creature, the queries are built for the real
 * one-to-many relationship (a user can own MANY creatures) from the start — so
 * later increments (the collection view, adoption) need no rework.
 */
final class CreatureRepository
{
    private const COLUMNS = 'id, owner_id, species_id, name, xp, happiness, bio, is_public, last_interacted_at, created_at';

    public function __construct(private PDO $connection)
    {
    }

    /**
     * Create a new creature owned by a user, and return the saved record.
     * XP, happiness and public-visibility use their database defaults (0, 0, and
     * public), so a fresh creature starts as a public baby with no interactions.
     */
    public function create(int $ownerId, int $speciesId, string $name): Creature
    {
        $statement = $this->connection->prepare(
            'INSERT INTO creatures (owner_id, species_id, name)
             VALUES (:owner_id, :species_id, :name)'
        );
        $statement->execute([
            ':owner_id' => $ownerId,
            ':species_id' => $speciesId,
            ':name' => $name,
        ]);

        $newCreature = $this->findById((int) $this->connection->lastInsertId());

        if ($newCreature === null) {
            throw new \RuntimeException('Failed to load the creature that was just created.');
        }

        return $newCreature;
    }

    /**
     * Find one creature by its id, or return null if there is no such creature.
     */
    public function findById(int $id): ?Creature
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM creatures WHERE id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);

        $row = $statement->fetch();

        return is_array($row) ? Creature::fromRow($row) : null;
    }

    /**
     * Get all creatures owned by a user, newest first. Used to list a player's
     * own creatures (their home page now; the full collection view in B.3).
     *
     * @return Creature[]
     */
    public function findByOwner(int $ownerId): array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM creatures WHERE owner_id = :owner_id ORDER BY created_at DESC, id DESC'
        );
        $statement->execute([':owner_id' => $ownerId]);

        return array_map(
            static fn (array $row): Creature => Creature::fromRow($row),
            $statement->fetchAll()
        );
    }
}
