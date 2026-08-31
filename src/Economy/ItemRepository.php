<?php

declare(strict_types=1);

namespace Felkyo\Economy;

use PDO;

/**
 * Reads and writes item definitions.
 *
 * @package Felkyo\Economy
 *
 * Until M2.4 every item was written by a migration and there was no class
 * for the items table at all (inventory and shop queries join into it).
 * This repository is its home now: the panel's item editor reads and writes
 * through here, and ONLY the editor service calls the writes.
 *
 * The reads are UNFILTERED by status on purpose: they serve the panel
 * (drafts are the point) and reference lookups. What players can ACQUIRE
 * is filtered where acquiring happens — ShopRepository's listing and
 * purchase queries require published.
 */
final class ItemRepository
{
    // A whole item with its category and its icon. Same aliasing rules as
    // ShopRepository::ITEM_COLUMNS (both tables have id and name); the icon
    // join is LEFT — most items have no uploaded icon yet.
    private const COLUMNS =
        'items.id, items.slug, items.name, items.description, items.price, items.sell_value,
         items.happiness_bonus, items.energy_bonus, items.status, items.icon_art_id, items.updated_at,
         icon.stored_name AS icon_art_name,
         item_categories.id AS category_id,
         item_categories.slug AS category_slug,
         item_categories.name AS category_name,
         item_categories.colour_token AS category_colour_token,
         item_categories.icon_key AS category_icon_key,
         item_categories.sort_order AS category_sort_order';

    private const FROM =
        ' FROM items
          JOIN item_categories ON item_categories.id = items.category_id
          LEFT JOIN art_files icon ON icon.id = items.icon_art_id ';

    public function __construct(private PDO $connection)
    {
    }

    /**
     * One item by id, drafts included, or null.
     */
    public function findById(int $id): ?Item
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . self::FROM . 'WHERE items.id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? Item::fromRow($row) : null;
    }

    /**
     * Every item, drafts included, for the panel's list — ordered the way
     * the artist scans: by category, then name.
     *
     * @return Item[]
     */
    public function all(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . self::FROM .
            'ORDER BY item_categories.sort_order, items.name'
        );

        return array_map(static fn (array $row): Item => Item::fromRow($row), $statement->fetchAll());
    }

    /**
     * Every PUBLISHED item — what a shop's shelf-stocking picker offers.
     *
     * @return Item[]
     */
    public function allPublished(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . self::FROM .
            "WHERE items.status = 'published' ORDER BY item_categories.sort_order, items.name"
        );

        return array_map(static fn (array $row): Item => Item::fromRow($row), $statement->fetchAll());
    }

    /**
     * Every category, for the editor's category tiles. (Before M2.4 no query
     * listed categories at all — they were only ever derived from items.)
     *
     * @return ItemCategory[]
     */
    public function allCategories(): array
    {
        $statement = $this->connection->query(
            'SELECT id, slug, name, colour_token, icon_key, sort_order
               FROM item_categories ORDER BY sort_order'
        );

        return array_map(
            static fn (array $row): ItemCategory => new ItemCategory(
                (int) $row['id'], $row['slug'], $row['name'],
                $row['colour_token'], $row['icon_key'], (int) $row['sort_order']
            ),
            $statement->fetchAll()
        );
    }

    /**
     * Is this slug taken? Asked when a new item is started.
     */
    public function slugExists(string $slug): bool
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM items WHERE slug = :slug');
        $statement->execute([':slug' => $slug]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * How many shop shelves list this item — part of the editor's
     * "where is this used?" answer.
     */
    public function shelfCount(int $itemId): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM shop_items WHERE item_id = :item_id'
        );
        $statement->execute([':item_id' => $itemId]);

        return (int) $statement->fetchColumn();
    }

    // ---- Writes (called only by ItemEditorService) ----

    /**
     * Start a new item: a DRAFT in the given category, priceless and
     * unsellable until its numbers are set. Returns the new record.
     */
    public function create(string $name, string $slug, int $categoryId): Item
    {
        $statement = $this->connection->prepare(
            "INSERT INTO items (slug, name, category_id, price, sell_value, status, updated_at)
             VALUES (:slug, :name, :category_id, 0, 0, 'draft', NOW())"
        );
        $statement->execute([':slug' => $slug, ':name' => $name, ':category_id' => $categoryId]);

        $created = $this->findById((int) $this->connection->lastInsertId());
        if ($created === null) {
            throw new \RuntimeException('Failed to load the item that was just created.');
        }

        return $created;
    }

    /**
     * Save the item's words: name and description.
     */
    public function updateWords(int $id, string $name, ?string $description): void
    {
        $statement = $this->connection->prepare(
            'UPDATE items SET name = :name, description = :description, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([':name' => $name, ':description' => $description, ':id' => $id]);
    }

    /**
     * Save the item's numbers and category. Named keys, not five loose
     * parameters (CLAUDE.md §5).
     *
     * @param array{price: int, sell_value: int, happiness_bonus: int,
     *              energy_bonus: int, category_id: int} $numbers
     */
    public function updateNumbers(int $id, array $numbers): void
    {
        $statement = $this->connection->prepare(
            'UPDATE items
                SET price = :price, sell_value = :sell_value,
                    happiness_bonus = :happiness, energy_bonus = :energy,
                    category_id = :category_id, updated_at = NOW()
              WHERE id = :id'
        );
        $statement->execute([
            ':price' => $numbers['price'],
            ':sell_value' => $numbers['sell_value'],
            ':happiness' => $numbers['happiness_bonus'],
            ':energy' => $numbers['energy_bonus'],
            ':category_id' => $numbers['category_id'],
            ':id' => $id,
        ]);
    }

    /**
     * Link (or unlink, with null) the item's icon from the art store.
     */
    public function setIconArt(int $id, ?int $artFileId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE items SET icon_art_id = :art_id, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([':art_id' => $artFileId, ':id' => $id]);
    }

    /**
     * Move between draft and published (the value is validated by the
     * service against Item's two constants before it gets here).
     */
    public function setStatus(int $id, string $status): void
    {
        $statement = $this->connection->prepare(
            'UPDATE items SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([':status' => $status, ':id' => $id]);
    }
}
