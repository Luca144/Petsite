<?php

declare(strict_types=1);

namespace Felkyo\Exploration;

use PDO;

/**
 * Reads and writes exploration areas, their spots and their loot tables.
 *
 * @package Felkyo\Exploration
 *
 * The only class that runs SQL for areas, area_spots and loot_entries.
 * Reads split cleanly by audience: the PLAYER methods (findBySlugPublished,
 * allPublished) serve only published areas; the PANEL methods (findById,
 * all) include drafts, because drafts are the panel's point. The writes
 * exist for one caller — AreaEditorService, which owns the rules.
 *
 * LOOT ROWS COME OUT SERVICE-READY: lootFor() joins the item's slug and
 * name in, so ExplorationService and ItemRewardGranter keep working on the
 * exact array shape the config used to provide — the move from config to
 * database never touched them.
 *
 * ONE FILE, DELIBERATELY OVER THE USUAL LIMIT: every method is area SQL and
 * nothing else — the same knowing use of the CLAUDE.md §4 escape hatch as
 * the other content repositories.
 */
final class AreaRepository
{
    private const COLUMNS =
        'a.id, a.slug, a.name, a.description, a.background_art_id, a.status, a.updated_at,
         bg.stored_name AS background_art_name';

    private const FROM =
        ' FROM areas a LEFT JOIN art_files bg ON bg.id = a.background_art_id ';

    public function __construct(private PDO $connection)
    {
    }

    // ---- Player-facing reads (published only) ----

    /**
     * The areas a player may wander — the /explore list.
     *
     * @return Area[]
     */
    public function allPublished(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . self::FROM . "WHERE a.status = 'published' ORDER BY a.id"
        );

        return $this->rowsToAreas($statement->fetchAll());
    }

    /**
     * One area by slug, ONLY if published — what both /explore/{area} routes
     * use, so a draft is a redirect however the address was typed.
     */
    public function findBySlugPublished(string $slug): ?Area
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . self::FROM . "WHERE a.slug = :slug AND a.status = 'published' LIMIT 1"
        );
        $statement->execute([':slug' => $slug]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return $this->rowsToAreas([$row])[0];
    }

    // ---- Panel reads (drafts included) ----

    /**
     * One area by id, drafts included — the editor's read.
     */
    public function findById(int $id): ?Area
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . self::FROM . 'WHERE a.id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return $this->rowsToAreas([$row])[0];
    }

    /**
     * Every area, drafts included — the panel's list.
     *
     * @return Area[]
     */
    public function all(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . self::FROM . 'ORDER BY a.id'
        );

        return $this->rowsToAreas($statement->fetchAll());
    }

    public function slugExists(string $slug): bool
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM areas WHERE slug = :slug');
        $statement->execute([':slug' => $slug]);

        return (int) $statement->fetchColumn() > 0;
    }

    // ---- Loot ----

    /**
     * An area's loot table, in editor order, each row carrying its item's
     * slug and name (LEFT JOIN — most rows are not item rows). The shape is
     * exactly what ExplorationService has always eaten: type, weight,
     * message, item (the slug) — see the class note.
     *
     * @return array<int, array{type: string, weight: int, message: string,
     *                          item: ?string, item_id: ?int, item_name: ?string,
     *                          item_status: ?string}>
     */
    public function lootFor(int $areaId): array
    {
        $statement = $this->connection->prepare(
            'SELECT l.type, l.weight, l.message, l.item_id,
                    i.slug AS item_slug, i.name AS item_name, i.status AS item_status
               FROM loot_entries l
               LEFT JOIN items i ON i.id = l.item_id
              WHERE l.area_id = :area_id
              ORDER BY l.sort_order, l.id'
        );
        $statement->execute([':area_id' => $areaId]);

        $rows = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'type' => (string) $row['type'],
                'weight' => (int) $row['weight'],
                'message' => (string) $row['message'],
                'item' => $row['item_slug'] ?? null,
                'item_id' => $row['item_id'] === null ? null : (int) $row['item_id'],
                'item_name' => $row['item_name'] ?? null,
                'item_status' => $row['item_status'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Replace an area's whole loot table — the plain-line editor saves
     * all-or-nothing, so the write is all-or-nothing too. Runs inside the
     * caller's transaction.
     *
     * @param array<int, array{type: string, item_id: ?int, weight: int, message: string}> $rows
     */
    public function replaceLoot(int $areaId, array $rows): void
    {
        $this->connection->prepare('DELETE FROM loot_entries WHERE area_id = :area_id')
            ->execute([':area_id' => $areaId]);

        $insert = $this->connection->prepare(
            'INSERT INTO loot_entries (area_id, type, item_id, weight, message, sort_order)
             VALUES (:area_id, :type, :item_id, :weight, :message, :sort_order)'
        );
        foreach (array_values($rows) as $order => $row) {
            $insert->execute([
                ':area_id' => $areaId,
                ':type' => $row['type'],
                ':item_id' => $row['item_id'],
                ':weight' => $row['weight'],
                ':message' => $row['message'],
                ':sort_order' => $order,
            ]);
        }
        $this->touch($areaId);
    }

    /**
     * Where each item drops, across all areas: item slug => area names.
     * This feeds the item editor's "woven into the game" lines — the same
     * answer the front controller used to assemble from config.
     *
     * @return array<string, string[]>
     */
    public function lootItemReferences(): array
    {
        // ORDER BY a column that is IN the select list: production MySQL 8
        // refuses DISTINCT with an ORDER BY on an unselected column (error
        // 3065), while the development MariaDB happily allowed it — this
        // one line took the whole live site down at boot on deploy day.
        // Name order is what the editor page reads best anyway.
        $statement = $this->connection->query(
            "SELECT DISTINCT i.slug, a.name
               FROM loot_entries l
               JOIN items i ON i.id = l.item_id
               JOIN areas a ON a.id = l.area_id
              WHERE l.type = 'item'
              ORDER BY a.name"
        );

        $references = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $references[(string) $row['slug']][] = 'found while exploring ' . $row['name'];
        }

        return $references;
    }

    // ---- Writes (called only by AreaEditorService) ----

    /**
     * Start a new area: a DRAFT with a name. Returns the new record.
     */
    public function create(string $name, string $slug): Area
    {
        $statement = $this->connection->prepare(
            "INSERT INTO areas (slug, name, status, updated_at) VALUES (:slug, :name, 'draft', NOW())"
        );
        $statement->execute([':slug' => $slug, ':name' => $name]);

        $created = $this->findById((int) $this->connection->lastInsertId());
        if ($created === null) {
            throw new \RuntimeException('Failed to load the area that was just created.');
        }

        return $created;
    }

    public function updateWords(int $id, string $name, ?string $description): void
    {
        $statement = $this->connection->prepare(
            'UPDATE areas SET name = :name, description = :description, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([':name' => $name, ':description' => $description, ':id' => $id]);
    }

    public function setBackgroundArt(int $id, ?int $artFileId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE areas SET background_art_id = :art_id, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([':art_id' => $artFileId, ':id' => $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $statement = $this->connection->prepare(
            'UPDATE areas SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([':status' => $status, ':id' => $id]);
    }

    /**
     * Replace an area's spots wholesale — the spots form saves the whole
     * set, so the write matches. Runs inside the caller's transaction.
     *
     * @param array<int, array{x: int, y: int}> $spots
     */
    public function replaceSpots(int $areaId, array $spots): void
    {
        $this->connection->prepare('DELETE FROM area_spots WHERE area_id = :area_id')
            ->execute([':area_id' => $areaId]);

        $insert = $this->connection->prepare(
            'INSERT INTO area_spots (area_id, x, y) VALUES (:area_id, :x, :y)'
        );
        foreach ($spots as $spot) {
            $insert->execute([':area_id' => $areaId, ':x' => $spot['x'], ':y' => $spot['y']]);
        }
        $this->touch($areaId);
    }

    // ---- Plumbing ----

    private function touch(int $areaId): void
    {
        $this->connection->prepare('UPDATE areas SET updated_at = NOW() WHERE id = :id')
            ->execute([':id' => $areaId]);
    }

    /**
     * Rows → Areas with their spots attached — one extra query for the whole
     * set, never one per area.
     *
     * @param array<int, array> $rows
     * @return Area[]
     */
    private function rowsToAreas(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $statement = $this->connection->prepare(
            'SELECT area_id, x, y FROM area_spots WHERE area_id IN (' . $placeholders . ') ORDER BY id'
        );
        $statement->execute($ids);

        $spotsByArea = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $spotRow) {
            $spotsByArea[(int) $spotRow['area_id']][] = [
                'x' => (int) $spotRow['x'],
                'y' => (int) $spotRow['y'],
            ];
        }

        return array_map(
            static fn (array $row): Area => Area::fromRow($row, $spotsByArea[(int) $row['id']] ?? []),
            $rows
        );
    }
}
