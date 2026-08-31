<?php

declare(strict_types=1);

namespace Felkyo\Economy;

use PDO;

/**
 * Reads and writes shops and the items they sell.
 *
 * @package Felkyo\Economy
 *
 * WHAT THIS IS: the only place that runs SQL for the shops and shop_items
 * tables. A shop's stock is found by joining shop_items to items (the price
 * lives on the item). Read-only until M2.4; the writes exist for exactly one
 * caller — ShopEditorService, which owns the rules.
 *
 * WHAT A PLAYER IS OFFERED is decided in findItems()/findSoldItem(), and
 * BOTH filter the same way: the item must be PUBLISHED, and the listing must
 * be inside its season (the available_from/available_to dates added
 * 2026-08-12 and honoured since M2.4 — NULL means always). Filtering the
 * purchase lookup too, not just the page, is what makes "buy a draft" or
 * "buy out of season" impossible however the request was built.
 *
 * ONE FILE, DELIBERATELY OVER THE USUAL LIMIT: every method is shop SQL and
 * nothing else — the same knowing use of the CLAUDE.md §4 escape hatch as
 * SpeciesRepository.
 */
final class ShopRepository
{
    /**
     * The columns every item query here needs in order to build a whole Item.
     *
     * WHY IT IS WRITTEN ONCE: an item is only complete with its category, so
     * every query joins the tables and needs the same columns. Writing them
     * out repeatedly invites the day somebody adds a column to two of three
     * copies and spends an afternoon on "undefined array key".
     *
     * WHY THE CATEGORY COLUMNS ARE RENAMED: both tables have "id" and "name".
     * In a joined row the second silently replaces the first, so without
     * these prefixes every item would be called "Dish" and carry its
     * category's id. The icon join is LEFT — most items have none yet.
     *
     * Columns are still listed one by one — never SELECT * (CLAUDE.md §5).
     */
    private const ITEM_COLUMNS =
        'items.id, items.slug, items.name, items.description, items.price, items.sell_value,
         items.happiness_bonus, items.energy_bonus, items.status, items.icon_art_id,
         icon.stored_name AS icon_art_name,
         item_categories.id AS category_id,
         item_categories.slug AS category_slug,
         item_categories.name AS category_name,
         item_categories.colour_token AS category_colour_token,
         item_categories.icon_key AS category_icon_key,
         item_categories.sort_order AS category_sort_order';

    private const ITEM_FROM =
        ' FROM shop_items
          JOIN items ON items.id = shop_items.item_id
          JOIN item_categories ON item_categories.id = items.category_id
          LEFT JOIN art_files icon ON icon.id = items.icon_art_id ';

    // What a player may be offered — see the class note. CURDATE() compares
    // in the database's own timezone, the same clock that will retire the
    // listing at midnight.
    private const OFFERED =
        " items.status = 'published'
          AND (shop_items.available_from IS NULL OR shop_items.available_from <= CURDATE())
          AND (shop_items.available_to   IS NULL OR shop_items.available_to   >= CURDATE()) ";

    // A whole shop, with its two pictures' stored names joined in (LEFT —
    // both optional). Aliased twice because one table is joined twice.
    private const SHOP_COLUMNS =
        's.id, s.slug, s.name, s.description, s.keeper_name, s.keeper_greeting, s.keeper_refusal,
         s.background_art_id, s.portrait_art_id, s.status, s.updated_at,
         bg.stored_name AS background_art_name,
         pt.stored_name AS portrait_art_name';

    private const SHOP_FROM =
        ' FROM shops s
          LEFT JOIN art_files bg ON bg.id = s.background_art_id
          LEFT JOIN art_files pt ON pt.id = s.portrait_art_id ';

    public function __construct(private PDO $connection)
    {
    }

    // ---- Reads ----

    /**
     * Find a shop by its slug (e.g. "general-store"), or null if there is none.
     */
    public function findBySlug(string $slug): ?Shop
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::SHOP_COLUMNS . self::SHOP_FROM . 'WHERE s.slug = :slug LIMIT 1'
        );
        $statement->execute([':slug' => $slug]);
        $row = $statement->fetch();

        return is_array($row) ? Shop::fromRow($row) : null;
    }

    /**
     * Find a shop by id, drafts included (the panel's editor), or null.
     */
    public function findById(int $id): ?Shop
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::SHOP_COLUMNS . self::SHOP_FROM . 'WHERE s.id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? Shop::fromRow($row) : null;
    }

    /**
     * Every shop, drafts included — the panel's list. (Players never see a
     * list of shops yet; that page is M9's, and it will want a published
     * filter of its own when it comes.)
     *
     * @return Shop[]
     */
    public function allShops(): array
    {
        $statement = $this->connection->query(
            'SELECT ' . self::SHOP_COLUMNS . self::SHOP_FROM . 'ORDER BY s.id'
        );

        return array_map(static fn (array $row): Shop => Shop::fromRow($row), $statement->fetchAll());
    }

    /**
     * Everything this shop OFFERS a player right now — published items,
     * inside their season, cheapest first. See the class note.
     *
     * @return Item[]
     */
    public function findItems(int $shopId): array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::ITEM_COLUMNS . self::ITEM_FROM .
            'WHERE shop_items.shop_id = :shop_id AND ' . self::OFFERED .
            'ORDER BY items.price'
        );
        $statement->execute([':shop_id' => $shopId]);

        return array_map(
            static fn (array $row): Item => Item::fromRow($row),
            $statement->fetchAll()
        );
    }

    /**
     * Find a single item ONLY IF this shop actually offers it right now —
     * same filters as the page (class note), so a purchase can never reach
     * past them. The purchase flow uses this and gets the real, current
     * price from the database, not the browser.
     */
    public function findSoldItem(int $shopId, int $itemId): ?Item
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::ITEM_COLUMNS . self::ITEM_FROM .
            'WHERE shop_items.shop_id = :shop_id AND shop_items.item_id = :item_id
               AND ' . self::OFFERED . '
             LIMIT 1'
        );
        $statement->execute([':shop_id' => $shopId, ':item_id' => $itemId]);
        $row = $statement->fetch();

        return is_array($row) ? Item::fromRow($row) : null;
    }

    /**
     * The shelf as the PANEL sees it: every listing, dates and all,
     * including items that are drafts or out of season — the editor must
     * show honestly what is on the shelf and why players can't see it.
     *
     * @return array<int, array{item_id: int, item_name: string, item_status: string,
     *                          available_from: ?string, available_to: ?string}>
     */
    public function listingsFor(int $shopId): array
    {
        $statement = $this->connection->prepare(
            'SELECT shop_items.item_id, items.name AS item_name, items.status AS item_status,
                    shop_items.available_from, shop_items.available_to
               FROM shop_items
               JOIN items ON items.id = shop_items.item_id
              WHERE shop_items.shop_id = :shop_id
              ORDER BY items.name'
        );
        $statement->execute([':shop_id' => $shopId]);

        $listings = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $listings[] = [
                'item_id' => (int) $row['item_id'],
                'item_name' => (string) $row['item_name'],
                'item_status' => (string) $row['item_status'],
                'available_from' => $row['available_from'] ?? null,
                'available_to' => $row['available_to'] ?? null,
            ];
        }

        return $listings;
    }

    // ---- Writes (called only by ShopEditorService) ----

    /**
     * Start a new shop: a DRAFT with a name. Returns the new record.
     */
    public function create(string $name, string $slug): Shop
    {
        $statement = $this->connection->prepare(
            "INSERT INTO shops (slug, name, status, updated_at) VALUES (:slug, :name, 'draft', NOW())"
        );
        $statement->execute([':slug' => $slug, ':name' => $name]);

        $created = $this->findById((int) $this->connection->lastInsertId());
        if ($created === null) {
            throw new \RuntimeException('Failed to load the shop that was just created.');
        }

        return $created;
    }

    /**
     * Is this slug taken? Asked when a new shop is started.
     */
    public function slugExists(string $slug): bool
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM shops WHERE slug = :slug');
        $statement->execute([':slug' => $slug]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * Save the shop's own words: name and description.
     */
    public function updateWords(int $id, string $name, ?string $description): void
    {
        $statement = $this->connection->prepare(
            'UPDATE shops SET name = :name, description = :description, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([':name' => $name, ':description' => $description, ':id' => $id]);
    }

    /**
     * Save the keeper: who minds the counter and what they say.
     *
     * @param array{name: ?string, greeting: ?string, refusal: ?string} $keeper
     */
    public function updateKeeper(int $id, array $keeper): void
    {
        $statement = $this->connection->prepare(
            'UPDATE shops
                SET keeper_name = :keeper_name, keeper_greeting = :greeting,
                    keeper_refusal = :refusal, updated_at = NOW()
              WHERE id = :id'
        );
        $statement->execute([
            ':keeper_name' => $keeper['name'],
            ':greeting' => $keeper['greeting'],
            ':refusal' => $keeper['refusal'],
            ':id' => $id,
        ]);
    }

    /**
     * Link (or unlink) one of the shop's two pictures. $slot is validated by
     * the service against exactly these two names; the match here is the
     * belt-and-braces that keeps a typo from becoming SQL.
     */
    public function setArt(int $id, string $slot, ?int $artFileId): void
    {
        $column = match ($slot) {
            'background' => 'background_art_id',
            'portrait' => 'portrait_art_id',
            default => throw new \InvalidArgumentException('Unknown shop art slot: ' . $slot),
        };

        $statement = $this->connection->prepare(
            'UPDATE shops SET ' . $column . ' = :art_id, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([':art_id' => $artFileId, ':id' => $id]);
    }

    /**
     * Move between draft and published.
     */
    public function setStatus(int $id, string $status): void
    {
        $statement = $this->connection->prepare(
            'UPDATE shops SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([':status' => $status, ':id' => $id]);
    }

    /**
     * Put an item on this shelf (or update its season if it is already
     * there — the unique (shop_id, item_id) index makes this an upsert).
     * NULL dates mean "always available".
     */
    public function putListing(int $shopId, int $itemId, ?string $availableFrom, ?string $availableTo): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO shop_items (shop_id, item_id, available_from, available_to)
             VALUES (:shop_id, :item_id, :from_a, :to_a)
             ON DUPLICATE KEY UPDATE available_from = :from_b, available_to = :to_b'
        );
        $statement->execute([
            ':shop_id' => $shopId,
            ':item_id' => $itemId,
            ':from_a' => $availableFrom,
            ':to_a' => $availableTo,
            ':from_b' => $availableFrom,
            ':to_b' => $availableTo,
        ]);
    }

    /**
     * Take an item off this shelf. Removing one that is not there is not an
     * error. Players who already bought it keep it — a shelf says what is
     * for sale, never what may be owned.
     */
    public function removeListing(int $shopId, int $itemId): void
    {
        $statement = $this->connection->prepare(
            'DELETE FROM shop_items WHERE shop_id = :shop_id AND item_id = :item_id'
        );
        $statement->execute([':shop_id' => $shopId, ':item_id' => $itemId]);
    }
}
