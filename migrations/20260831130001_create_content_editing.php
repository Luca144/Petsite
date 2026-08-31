<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Makes items and shops EDITABLE, and unifies every editor's undo.
 *
 * @package Felkyo\Migrations
 *
 * M2.4 — the item and shop editors. Three moves:
 *
 * 1. `items` and `shops` gain what editing needs: a draft/published `status`
 *    (DEFAULT 'published', for the same reason species' did — everything
 *    that inserts WITHOUT the panel is live content and must stay live;
 *    the panel alone writes 'draft'), `updated_at`, and art-store links
 *    (SET NULL — deleting a picture never deletes content; the screen says
 *    "missing picture"). Shops also gain their KEEPER: a name, a greeting
 *    line, and the refusal line that until now was the hardcoded string
 *    "Ruinily doesn't have anything like that on the shelves" — which this
 *    migration writes into the general store's row verbatim, so the day
 *    after deploying, nothing reads differently until somebody edits it.
 *
 * 2. `content_versions` replaces `species_versions` as ONE undo table for
 *    every editor (content_type + content_id instead of a species FK).
 *    Three parallel version tables would be the second copy of the same
 *    code the build plan forbids. The existing species snapshots are
 *    copied across and the old table dropped.
 *
 * 3. Nothing here reads or changes shop_items — its seasonal
 *    available_from/available_to columns (added 2026-08-12) start being
 *    HONOURED by the code this increment ships, but the schema was ready.
 *
 * NOT REVERSIBLE: the copy-and-drop in (2) is a one-way data move, like
 * the seed migrations before it. Rolling back means restoring a backup,
 * not `phinx rollback` — said here so nobody discovers it at 11pm.
 */
final class CreateContentEditing extends AbstractMigration
{
    public function change(): void
    {
        // ---- Items become editable ----
        $this->table('items')
            ->addColumn('status', 'string', ['limit' => 10, 'default' => 'published', 'null' => false, 'after' => 'category_id'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'after' => 'created_at'])
            // The item's icon in the art store. NULL = the old convention
            // path (public/assets/items/{slug}.png), else the category icon
            // stands in — the same two-source-plus-fallback resolution
            // sprites got in M2.3, now for items.
            ->addColumn('icon_art_id', 'integer', ['signed' => false, 'null' => true, 'after' => 'status'])
            ->addForeignKey('icon_art_id', 'art_files', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            // The shop shelf and the purchase check both filter on status.
            ->addIndex(['status'])
            ->update();

        // ---- Shops get their keeper, their art, and a draft state ----
        $this->table('shops')
            ->addColumn('status', 'string', ['limit' => 10, 'default' => 'published', 'null' => false, 'after' => 'description'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'after' => 'created_at'])
            // Who minds the counter, and what they say. All nullable: a shop
            // without a keeper simply shows none, exactly as today.
            ->addColumn('keeper_name', 'string', ['limit' => 60, 'null' => true, 'after' => 'description'])
            // The line under the shop's name — the keeper greeting you.
            ->addColumn('keeper_greeting', 'string', ['limit' => 300, 'null' => true, 'after' => 'keeper_name'])
            // What the finder says when a search finds nothing here.
            ->addColumn('keeper_refusal', 'string', ['limit' => 150, 'null' => true, 'after' => 'keeper_greeting'])
            ->addColumn('background_art_id', 'integer', ['signed' => false, 'null' => true, 'after' => 'keeper_refusal'])
            ->addColumn('portrait_art_id', 'integer', ['signed' => false, 'null' => true, 'after' => 'background_art_id'])
            ->addForeignKey('background_art_id', 'art_files', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->addForeignKey('portrait_art_id', 'art_files', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->update();

        // Ruinily takes her official place — with the exact words the site
        // already used for her, apostrophe and all (see class note).
        $this->execute(
            "UPDATE shops
                SET keeper_name = 'Ruinily',
                    keeper_refusal = 'Ruinily doesn’t have anything like that on the shelves.'
              WHERE slug = 'general-store'"
        );

        // ---- One undo table for every editor ----
        $this->table('content_versions', ['signed' => false])
            // 'species', 'item', 'shop' — allow-listed by each editor
            // service; the same polymorphic shape as reports and the audit
            // log, and the same honesty deal: PHP guards what the FK cannot.
            ->addColumn('content_type', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('content_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('snapshot', 'text', ['null' => false])
            ->addColumn('saved_by_user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            ->addForeignKey('saved_by_user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            // The version list: one thing's history, newest first.
            ->addIndex(['content_type', 'content_id', 'created_at'])
            ->create();

        // Move the species history across, then retire the old table.
        //
        // The LEFT JOIN launders saved_by: a snapshot whose saver's account
        // no longer exists (test databases delete users with FK checks off,
        // and one day account deletion will too) arrives as "saved by
        // nobody" instead of tripping the new table's foreign key. The
        // snapshot itself — the thing worth keeping — survives either way.
        $this->execute(
            "INSERT INTO content_versions (content_type, content_id, snapshot, saved_by_user_id, created_at)
             SELECT 'species', v.species_id, v.snapshot, u.id, v.created_at
               FROM species_versions v
               LEFT JOIN users u ON u.id = v.saved_by_user_id"
        );
        $this->table('species_versions')->drop()->save();
    }
}
