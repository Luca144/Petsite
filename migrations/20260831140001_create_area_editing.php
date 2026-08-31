<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Exploration areas leave the config file and become data (M2.5).
 *
 * @package Felkyo\Migrations
 *
 * Three tables, and a word-for-word move of the one area that exists:
 *
 * - `areas` — the area itself. Slug minted once and never editable, because
 *   exploration_visits has always referenced areas by slug string and player
 *   history must survive any edit. `status` defaults to 'published' for the
 *   same reason it does everywhere: rows inserted outside the panel (this
 *   seed, test fixtures) are live content; only the panel writes 'draft'.
 * - `area_spots` — the clickable points, x/y as percentages across the
 *   scene, exactly as the config carried them.
 * - `loot_entries` — the weighted reward table. THE ONE DELIBERATE CHANGE
 *   from the config shape: an item reward references items by FOREIGN KEY,
 *   not by typed slug. In config, a slug was the right call ("ids differ
 *   between installs"); now that areas live in the SAME database as items,
 *   the honest reference is relational — and a mistyped item becomes
 *   impossible to WRITE instead of quietly granting nothing. RESTRICT
 *   costs nothing: items cannot be deleted (M2.4 decision).
 *
 * The config's `areas` block is removed by the same increment — one source
 * of truth. The mechanics knobs (clicks_per_visit, window_seconds) stay in
 * config: they are tuning, not content.
 *
 * The seed below reproduces whispering-wood exactly as config defined it,
 * messages verbatim, resolving item slugs to ids at migrate time.
 */
final class CreateAreaEditing extends AbstractMigration
{
    public function change(): void
    {
        $this->table('areas', ['signed' => false])
            ->addColumn('slug', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 60, 'null' => false])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('background_art_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 10, 'default' => 'published', 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            // Deleting a picture never deletes an area; the scene falls back
            // to its cosy gradient, exactly as before backgrounds existed.
            ->addForeignKey('background_art_id', 'art_files', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->addIndex(['slug'], ['unique' => true])
            ->addIndex(['status'])
            ->create();

        $this->table('area_spots', ['signed' => false])
            ->addColumn('area_id', 'integer', ['signed' => false, 'null' => false])
            // Percentages across the scene, 0–100, validated in PHP.
            ->addColumn('x', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('y', 'integer', ['signed' => false, 'null' => false])
            ->addForeignKey('area_id', 'areas', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addIndex(['area_id'])
            ->create();

        $this->table('loot_entries', ['signed' => false])
            ->addColumn('area_id', 'integer', ['signed' => false, 'null' => false])
            // 'nothing' / 'item' / 'creature' — allow-listed in PHP.
            ->addColumn('type', 'string', ['limit' => 20, 'null' => false])
            // Only for type 'item' (see the class note on the FK).
            ->addColumn('item_id', 'integer', ['signed' => false, 'null' => true])
            // Relative weights, each at least 1 — enforced in PHP, because an
            // all-zero table would crash the reward roll (see the editor).
            ->addColumn('weight', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('message', 'string', ['limit' => 200, 'null' => false])
            // The order the editor shows rows in (the roll ignores it).
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addForeignKey('area_id', 'areas', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('item_id', 'items', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION'])
            ->addIndex(['area_id', 'sort_order'])
            // "Which areas drop this item?" — the item editor's question.
            ->addIndex(['item_id'])
            ->create();

        // ---- Whispering Wood moves in, word for word ----
        $this->execute(
            "INSERT INTO areas (slug, name, description, status)
             VALUES ('whispering-wood', 'The Whispering Wood',
                     'A hush of amber trees where small things rustle just out of sight.',
                     'published')"
        );

        $areaId = (int) $this->fetchRow("SELECT id FROM areas WHERE slug = 'whispering-wood'")['id'];

        foreach ([[18, 60], [40, 42], [63, 66], [80, 48], [52, 78]] as [$x, $y]) {
            $this->execute("INSERT INTO area_spots (area_id, x, y) VALUES ({$areaId}, {$x}, {$y})");
        }

        // The loot rows, messages verbatim from config. Item ids are looked
        // up by slug HERE, at migrate time, in whatever database this runs
        // against — which is exactly why content never carried ids before.
        $loot = [
            ['nothing', null, 45, 'Only leaves and a soft wind. Nothing this time.'],
            ['item', 'acorn-treat', 25, 'A crunchy little acorn, tucked under a root.'],
            ['item', 'honey-treat', 10, 'Something sweet, wrapped in a leaf.'],
            ['item', 'chamomile-bundle', 5, 'A bundle of little dried flowers, still faintly scented.'],
            ['creature', null, 15, 'Something small blinks in the undergrowth — a new creature follows you home!'],
        ];
        $insert = 0;
        foreach ($loot as [$type, $itemSlug, $weight, $message]) {
            $itemId = 'NULL';
            if ($itemSlug !== null) {
                $row = $this->fetchRow("SELECT id FROM items WHERE slug = '{$itemSlug}'");
                if ($row === false) {
                    // A database without the seeded items (should not exist)
                    // gets the row as 'nothing' rather than a broken link.
                    $type = 'nothing';
                } else {
                    $itemId = (string) (int) $row['id'];
                }
            }
            $safeMessage = str_replace("'", "''", $message);
            $this->execute(
                "INSERT INTO loot_entries (area_id, type, item_id, weight, message, sort_order)
                 VALUES ({$areaId}, '{$type}', {$itemId}, {$weight}, '{$safeMessage}', {$insert})"
            );
            $insert++;
        }
    }
}
