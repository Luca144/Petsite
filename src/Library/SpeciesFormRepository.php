<?php

declare(strict_types=1);

namespace Felkyo\Library;

use PDO;

/**
 * Reads and writes alternate forms (species_forms + species_form_stages).
 *
 * @package Felkyo\Library
 *
 * Like SpeciesRepository, every list read costs TWO queries no matter how many
 * forms come back: one for the forms, one `IN (...)` for all their stage
 * pictures, dealt out in PHP. Lists never query per form.
 */
final class SpeciesFormRepository
{
    private const COLUMNS = 'id, species_id, slug, name, blurb, status';

    public function __construct(private PDO $connection)
    {
    }

    /**
     * Every form of one species. The panel wants drafts too; the library
     * passes $publishedOnly = true so unfinished forms stay secret.
     *
     * @return SpeciesForm[]
     */
    public function forSpecies(int $speciesId, bool $publishedOnly): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM species_forms WHERE species_id = :species_id'
            . ($publishedOnly ? " AND status = 'published'" : '')
            . ' ORDER BY name';
        $statement = $this->connection->prepare($sql);
        $statement->execute([':species_id' => $speciesId]);

        return $this->rowsToForms($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Forms by id, for dressing a list of creatures: the profile builder
     * collects every form id on the page and asks once.
     *
     * @param int[] $ids
     * @return array<int, SpeciesForm> id => form
     */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM species_forms WHERE id IN (' . $placeholders . ')'
        );
        $statement->execute($ids);

        $byId = [];
        foreach ($this->rowsToForms($statement->fetchAll(PDO::FETCH_ASSOC)) as $form) {
            $byId[$form->id] = $form;
        }

        return $byId;
    }

    public function findById(int $id): ?SpeciesForm
    {
        return $this->findByIds([$id])[$id] ?? null;
    }

    /**
     * Ids of the PUBLISHED forms of a species — what a newly arriving
     * creature may be born wearing (see CreatureFormRoller).
     *
     * @return int[]
     */
    public function publishedIdsFor(int $speciesId): array
    {
        $statement = $this->connection->prepare(
            "SELECT id FROM species_forms WHERE species_id = :species_id AND status = 'published'"
        );
        $statement->execute([':species_id' => $speciesId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Is this slug already used by another form of the same species? */
    public function slugTaken(int $speciesId, string $slug): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM species_forms WHERE species_id = :species_id AND slug = :slug LIMIT 1'
        );
        $statement->execute([':species_id' => $speciesId, ':slug' => $slug]);

        return $statement->fetchColumn() !== false;
    }

    // ---- Writes ----

    /** Start a new form as a draft; returns its id. */
    public function create(int $speciesId, string $slug, string $name): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO species_forms (species_id, slug, name) VALUES (:species_id, :slug, :name)'
        );
        $statement->execute([':species_id' => $speciesId, ':slug' => $slug, ':name' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    public function updateWords(int $formId, string $name, ?string $blurb): void
    {
        $this->connection->prepare('UPDATE species_forms SET name = :name, blurb = :blurb WHERE id = :id')
            ->execute([':name' => $name, ':blurb' => $blurb, ':id' => $formId]);
    }

    /** The VALUE is checked against SpeciesForm's constants by the caller. */
    public function setStatus(int $formId, string $status): void
    {
        $this->connection->prepare('UPDATE species_forms SET status = :status WHERE id = :id')
            ->execute([':status' => $status, ':id' => $formId]);
    }

    /** Link (or unlink, with null) a stage's sprite — insert-or-update. */
    public function setStageArt(int $formId, string $stage, ?int $artFileId): void
    {
        $this->connection->prepare(
            'INSERT INTO species_form_stages (form_id, stage, sprite_art_id)
             VALUES (:form_id, :stage, :art_id)
             ON DUPLICATE KEY UPDATE sprite_art_id = VALUES(sprite_art_id)'
        )->execute([':form_id' => $formId, ':stage' => $stage, ':art_id' => $artFileId]);
    }

    /**
     * Delete a form. Creatures wearing it go back to the default look
     * (creatures.form_id is ON DELETE SET NULL) — nobody loses a creature.
     */
    public function delete(int $formId): void
    {
        $this->connection->prepare('DELETE FROM species_forms WHERE id = :id')->execute([':id' => $formId]);
    }

    /**
     * Rows → forms, with each one's stage pictures attached in ONE query.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return SpeciesForm[]
     */
    private function rowsToForms(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->connection->prepare(
            'SELECT fs.form_id, fs.stage, fs.sprite_art_id, a.stored_name
               FROM species_form_stages fs
               LEFT JOIN art_files a ON a.id = fs.sprite_art_id
              WHERE fs.form_id IN (' . $placeholders . ')'
        );
        $statement->execute($ids);

        $artByForm = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $stageRow) {
            $artByForm[(int) $stageRow['form_id']][(string) $stageRow['stage']] = [
                'art_id' => $stageRow['sprite_art_id'] === null ? null : (int) $stageRow['sprite_art_id'],
                'art_name' => $stageRow['stored_name'] ?? null,
            ];
        }

        return array_map(static fn (array $row): SpeciesForm => new SpeciesForm(
            (int) $row['id'],
            (int) $row['species_id'],
            (string) $row['slug'],
            (string) $row['name'],
            $row['blurb'] ?? null,
            (string) $row['status'],
            $artByForm[(int) $row['id']] ?? [],
        ), $rows);
    }
}
