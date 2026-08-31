<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use PDO;

/**
 * Reads and writes creature species.
 *
 * @package Felkyo\Creatures
 *
 * The only place that runs SQL for the species, species_stages and (reads
 * of) their art links. Read-only until M2.3; the writes exist for exactly
 * one caller — SpeciesEditorService, which owns the RULES (who may, what
 * publishing requires, versions, audit). This class only owns the SQL.
 *
 * THE DRAFT ASYMMETRY, load-bearing and easy to get backwards:
 *  - findById() and all() DO return drafts — players already own creatures
 *    of a species, and putting it back into draft must never make their
 *    creature render as "Unknown species".
 *  - findStarters() and findAdoptable() do NOT — nothing can be ACQUIRED
 *    while a species is a draft: not bought, not granted as a starter, not
 *    found while exploring. All three acquisition paths call these two.
 *
 * ONE FILE, DELIBERATELY OVER THE USUAL LIMIT: every method is species SQL
 * and nothing else; splitting reads from writes would put one table's
 * queries in two files that always change together (the CLAUDE.md §4
 * escape hatch, used knowingly).
 */
final class SpeciesRepository
{
    // The full species record. 's' is the species table, 'lib' the library
    // picture's art_files row (LEFT JOIN — most species have none yet).
    private const COLUMNS = 's.id, s.slug, s.name, s.flavour_text, s.is_starter, s.is_adoptable,
             s.gem_price, s.favourite_item_id, s.disliked_item_id,
             s.status, s.lore, s.library_art_id, s.updated_at,
             lib.stored_name AS library_art_name';

    private const FROM = ' FROM species s LEFT JOIN art_files lib ON lib.id = s.library_art_id ';

    public function __construct(private PDO $connection)
    {
    }

    // ---- Reads ----

    /**
     * Find a species by its id — DRAFTS INCLUDED (see the class note), or
     * null. Used to show a creature's species on its page.
     */
    public function findById(int $id): ?Species
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . self::FROM . 'WHERE s.id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return $this->rowsToSpecies([$row])[0];
    }

    /**
     * Every PUBLISHED species that can be a new player's starter. Ordered by
     * id so the deterministic pick stays stable.
     *
     * @return Species[]
     */
    public function findStarters(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . self::FROM .
            "WHERE s.is_starter = 1 AND s.status = 'published' ORDER BY s.id"
        );

        return $this->rowsToSpecies($statement->fetchAll());
    }

    /**
     * Every PUBLISHED species a player can come to own — the shop's creature
     * shelf and exploration's rare finds both draw from this.
     *
     * @return Species[]
     */
    public function findAdoptable(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . self::FROM .
            "WHERE s.is_adoptable = 1 AND s.status = 'published' ORDER BY s.id"
        );

        return $this->rowsToSpecies($statement->fetchAll());
    }

    /**
     * Every species, drafts included (see the class note). Used to build
     * id => Species lookups so lists never query per creature — and by the
     * panel's species list, where drafts are the point.
     *
     * @return Species[]
     */
    public function all(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . self::FROM . 'ORDER BY s.id'
        );

        return $this->rowsToSpecies($statement->fetchAll());
    }

    /**
     * Is this slug taken? Asked when a new species is started.
     */
    public function slugExists(string $slug): bool
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM species WHERE slug = :slug');
        $statement->execute([':slug' => $slug]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * Which species use this picture — as a stage sprite or as library art?
     * The art store's delete guard asks this before letting a picture go.
     *
     * @return string[] species names
     */
    public function namesUsingArt(int $artFileId): array
    {
        // Two homes a picture can have; UNION folds duplicates (a species
        // using one picture twice should be named once).
        $statement = $this->connection->prepare(
            'SELECT s.name FROM species s
              INNER JOIN species_stages st ON st.species_id = s.id
              WHERE st.sprite_art_id = :art_a
             UNION
             SELECT s2.name FROM species s2 WHERE s2.library_art_id = :art_b'
        );
        $statement->execute([':art_a' => $artFileId, ':art_b' => $artFileId]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    // ---- Writes (called only by SpeciesEditorService — see class note) ----

    /**
     * Start a new species: a DRAFT, not adoptable, not a starter, priceless.
     * Everything else is filled in on its page. Returns the new record.
     */
    public function create(string $name, string $slug): Species
    {
        $statement = $this->connection->prepare(
            "INSERT INTO species (slug, name, status, is_starter, is_adoptable, gem_price, updated_at)
             VALUES (:slug, :name, 'draft', 0, 0, 0, NOW())"
        );
        $statement->execute([':slug' => $slug, ':name' => $name]);

        $created = $this->findById((int) $this->connection->lastInsertId());
        if ($created === null) {
            throw new \RuntimeException('Failed to load the species that was just created.');
        }

        return $created;
    }

    /**
     * Save the species' words: name, flavour text, lore.
     */
    public function updateWords(int $id, string $name, ?string $flavourText, ?string $lore): void
    {
        $statement = $this->connection->prepare(
            'UPDATE species SET name = :name, flavour_text = :flavour, lore = :lore, updated_at = NOW()
              WHERE id = :id'
        );
        $statement->execute([':name' => $name, ':flavour' => $flavourText, ':lore' => $lore, ':id' => $id]);
    }

    /**
     * Save the species' numbers and flags. Takes the values as a named-key
     * array because five loose parameters would be unreadable at the call
     * site (CLAUDE.md §5).
     *
     * @param array{gem_price: int, is_adoptable: bool, is_starter: bool,
     *              favourite_item_id: ?int, disliked_item_id: ?int} $settings
     */
    public function updateSettings(int $id, array $settings): void
    {
        $statement = $this->connection->prepare(
            'UPDATE species
                SET gem_price = :price, is_adoptable = :adoptable, is_starter = :starter,
                    favourite_item_id = :favourite, disliked_item_id = :disliked, updated_at = NOW()
              WHERE id = :id'
        );
        $statement->execute([
            ':price' => $settings['gem_price'],
            ':adoptable' => $settings['is_adoptable'] ? 1 : 0,
            ':starter' => $settings['is_starter'] ? 1 : 0,
            ':favourite' => $settings['favourite_item_id'],
            ':disliked' => $settings['disliked_item_id'],
            ':id' => $id,
        ]);
    }

    /**
     * Link (or unlink, with null) a stage's sprite. The unique
     * (species_id, stage) index turns this into insert-or-update.
     */
    public function setStageArt(int $speciesId, string $stage, ?int $artFileId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO species_stages (species_id, stage, sprite_art_id)
             VALUES (:species_id, :stage, :art_id)
             ON DUPLICATE KEY UPDATE sprite_art_id = VALUES(sprite_art_id)'
        );
        $statement->execute([':species_id' => $speciesId, ':stage' => $stage, ':art_id' => $artFileId]);
        $this->touch($speciesId);
    }

    /**
     * Save a stage's words, same upsert shape.
     */
    public function setStageBlurb(int $speciesId, string $stage, ?string $blurb): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO species_stages (species_id, stage, blurb)
             VALUES (:species_id, :stage, :blurb)
             ON DUPLICATE KEY UPDATE blurb = VALUES(blurb)'
        );
        $statement->execute([':species_id' => $speciesId, ':stage' => $stage, ':blurb' => $blurb]);
        $this->touch($speciesId);
    }

    /**
     * Link (or unlink) the library picture.
     */
    public function setLibraryArt(int $speciesId, ?int $artFileId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE species SET library_art_id = :art_id, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([':art_id' => $artFileId, ':id' => $speciesId]);
    }

    /**
     * Move between draft and published. The VALUE is validated by the
     * service against Species' two constants before it gets here.
     */
    public function setStatus(int $speciesId, string $status): void
    {
        $statement = $this->connection->prepare(
            'UPDATE species SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([':status' => $status, ':id' => $speciesId]);
    }

    // ---- The plumbing ----

    /**
     * Stamp "something about this species changed just now".
     */
    private function touch(int $speciesId): void
    {
        $this->connection->prepare('UPDATE species SET updated_at = NOW() WHERE id = :id')
            ->execute([':id' => $speciesId]);
    }

    /**
     * Rows → Species, with each one's stage art attached. ONE extra query
     * for the whole set, not one per species — the stage table is read with
     * `IN (all ids)`, then dealt out. A page of twelve cards costs two
     * queries total.
     *
     * @param array<int, array> $rows
     * @return Species[]
     */
    private function rowsToSpecies(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Each stage row with its picture's stored name joined in (LEFT:
        // a stage may have words but no picture yet, or the picture may
        // have been deleted — SET NULL leaves the row).
        $statement = $this->connection->prepare(
            'SELECT st.species_id, st.stage, st.sprite_art_id, st.blurb, a.stored_name
               FROM species_stages st
               LEFT JOIN art_files a ON a.id = st.sprite_art_id
              WHERE st.species_id IN (' . $placeholders . ')'
        );
        $statement->execute($ids);

        $stagesBySpecies = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $stageRow) {
            $stagesBySpecies[(int) $stageRow['species_id']][(string) $stageRow['stage']] = [
                'art_id' => $stageRow['sprite_art_id'] === null ? null : (int) $stageRow['sprite_art_id'],
                'art_name' => $stageRow['stored_name'] ?? null,
                'blurb' => $stageRow['blurb'] ?? null,
            ];
        }

        return array_map(
            static fn (array $row): Species => Species::fromRow($row, $stagesBySpecies[(int) $row['id']] ?? []),
            $rows
        );
    }
}
