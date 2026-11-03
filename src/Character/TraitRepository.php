<?php

declare(strict_types=1);

namespace Felkyo\Character;

use PDO;

/**
 * Reads and writes traits and which creatures have them (M3.2).
 *
 * @package Felkyo\Character
 *
 * Giving a trait is INSERT IGNORE against creature_traits' primary key
 * (creature_id, trait_id): the database refuses a second copy, so two
 * requests racing to award the same trait can never give it twice
 * (house pattern 2 — a ledger row with a unique key).
 */
final class TraitRepository
{
    private const COLUMNS = 't.id, t.slug, t.name, t.description, t.tier, t.earn_kind,
             t.earn_threshold, t.chance_percent, t.xp_bonus_percent';

    public function __construct(private PDO $connection)
    {
    }

    /** @return CreatureTrait[] every trait, by tier then name (the panel). */
    public function all(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . ' FROM traits t ORDER BY t.tier, t.name'
        );

        return array_map([CreatureTrait::class, 'fromRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return CreatureTrait[] the traits of one tier. */
    public function ofTier(string $tier): array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM traits t WHERE t.tier = :tier ORDER BY t.id'
        );
        $statement->execute([':tier' => $tier]);

        return array_map([CreatureTrait::class, 'fromRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?CreatureTrait
    {
        $statement = $this->connection->prepare('SELECT ' . self::COLUMNS . ' FROM traits t WHERE t.id = :id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? CreatureTrait::fromRow($row) : null;
    }

    /**
     * The traits one creature has, oldest first, each with how it got it.
     *
     * @return CreatureTrait[]
     */
    public function forCreature(int $creatureId): array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ', ct.how
               FROM creature_traits ct
               JOIN traits t ON t.id = ct.trait_id
              WHERE ct.creature_id = :id
              ORDER BY ct.gained_at, t.id'
        );
        $statement->execute([':id' => $creatureId]);

        return array_map([CreatureTrait::class, 'fromRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Give a trait; returns false when it already had it. */
    public function give(int $creatureId, int $traitId, string $how): bool
    {
        $statement = $this->connection->prepare(
            'INSERT IGNORE INTO creature_traits (creature_id, trait_id, how) VALUES (:creature, :trait, :how)'
        );
        $statement->execute([':creature' => $creatureId, ':trait' => $traitId, ':how' => $how]);

        return $statement->rowCount() > 0;
    }

    // ---- The panel ----

    public function slugExists(string $slug): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM traits WHERE slug = :slug');
        $statement->execute([':slug' => $slug]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Insert a trait (id 0) or update one. $fields are already validated by
     * TraitEditorService.
     *
     * @param array{slug: string, name: string, description: string, tier: string,
     *              earn_kind: ?string, earn_threshold: ?int, chance_percent: int,
     *              xp_bonus_percent: int} $fields
     */
    public function save(int $id, array $fields): void
    {
        $values = [
            ':name' => $fields['name'], ':description' => $fields['description'], ':tier' => $fields['tier'],
            ':earn_kind' => $fields['earn_kind'], ':earn_threshold' => $fields['earn_threshold'],
            ':chance' => $fields['chance_percent'], ':bonus' => $fields['xp_bonus_percent'],
        ];

        if ($id === 0) {
            $this->connection->prepare(
                'INSERT INTO traits (slug, name, description, tier, earn_kind, earn_threshold, chance_percent, xp_bonus_percent)
                 VALUES (:slug, :name, :description, :tier, :earn_kind, :earn_threshold, :chance, :bonus)'
            )->execute($values + [':slug' => $fields['slug']]);

            return;
        }

        $this->connection->prepare(
            'UPDATE traits SET name = :name, description = :description, tier = :tier, earn_kind = :earn_kind,
                    earn_threshold = :earn_threshold, chance_percent = :chance, xp_bonus_percent = :bonus
              WHERE id = :id'
        )->execute($values + [':id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->connection->prepare('DELETE FROM traits WHERE id = :id')->execute([':id' => $id]);
    }
}
