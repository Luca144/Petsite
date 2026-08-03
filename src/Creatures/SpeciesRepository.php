<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use PDO;

/**
 * Reads creature species from the database.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the only place that runs SQL for the species table. Species are
 * reference content (they are seeded, not created by players), so this repository
 * only reads them.
 */
final class SpeciesRepository
{
    private const COLUMNS = 'id, slug, name, flavour_text, is_starter, is_adoptable';

    public function __construct(private PDO $connection)
    {
    }

    /**
     * Find a species by its id, or return null if there is no such species.
     * Used to show a creature's species on its page.
     */
    public function findById(int $id): ?Species
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM species WHERE id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);

        $row = $statement->fetch();

        return is_array($row) ? Species::fromRow($row) : null;
    }

    /**
     * Get every species that can be given to a new player as their starter.
     * Ordered by id so the choice is stable and predictable.
     *
     * @return Species[]
     */
    public function findStarters(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . ' FROM species WHERE is_starter = 1 ORDER BY id'
        );

        return array_map(
            static fn (array $row): Species => Species::fromRow($row),
            $statement->fetchAll()
        );
    }
}
