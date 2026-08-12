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

    /**
     * Get the most recently created PUBLIC creatures, across all users, for the
     * browse page. Private creatures are never included. The limit is cast to an
     * integer and placed straight into the SQL (safe: it is our own value; MySQL
     * will not accept a bound parameter for LIMIT under real prepared statements).
     *
     * @return Creature[]
     */
    public function findRecentPublic(int $limit): array
    {
        $limit = max(1, $limit);

        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . ' FROM creatures
              WHERE is_public = 1
              ORDER BY created_at DESC, id DESC
              LIMIT ' . $limit
        );

        return array_map(
            static fn (array $row): Creature => Creature::fromRow($row),
            $statement->fetchAll()
        );
    }

    /**
     * Save a creature's bio (its owner-written description).
     *
     * NOTE THE OWNER IN THE WHERE CLAUSE. It is there on purpose, and it is not
     * redundant with the check the controller already does. This is the rule the
     * owned-thing model sets for everything a player owns (docs/owned-things.md):
     * the query itself says who is allowed, so that if the check further up were
     * ever removed, moved, or forgotten during a rewrite, this statement would
     * simply match no rows and change nothing. A permission check you can delete
     * by accident is not really a permission check.
     *
     * Pass the id of the person DOING the edit — not the creature's stored owner
     * id, which would make the condition always true and the protection fake.
     */
    public function updateBio(int $creatureId, int $editorUserId, string $bio): void
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures SET bio = :bio WHERE id = :id AND owner_id = :editor_user_id'
        );
        $statement->execute([
            ':bio' => $bio,
            ':id' => $creatureId,
            ':editor_user_id' => $editorUserId,
        ]);
    }

    /**
     * Apply the effects of one pet to a creature: add to its happiness and XP, and
     * stamp when it was last interacted with. Doing the additions in the database
     * (happiness = happiness + :amount) is safe even if two pets land at once.
     */
    public function applyPetting(int $creatureId, int $happinessGain, int $xpGain): void
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures
                SET happiness = happiness + :happiness_gain,
                    xp = xp + :xp_gain,
                    last_interacted_at = NOW()
              WHERE id = :id'
        );
        $statement->execute([
            ':happiness_gain' => $happinessGain,
            ':xp_gain' => $xpGain,
            ':id' => $creatureId,
        ]);
    }
}
