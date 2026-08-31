<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the art_files table: one row per picture uploaded through the panel.
 *
 * @package Felkyo\Migrations
 *
 * M2.2 — image upload. The picture's BYTES live on the Railway volume (see
 * docs/plan/2026-08-21-m2-2-where-files-live.md); this table is the site's
 * memory of what is there: what kind of picture it is, how big, whether it
 * animates, who uploaded it. Serving looks a picture up here BY NAME before
 * reading a single byte from disk — so the serving route never touches a path
 * it did not itself record.
 *
 * WHY THE NAME IS A CONTENT HASH. stored_name is sha256(bytes) plus the
 * extension. Three things fall out of that for free:
 *   - A replacement is a NEW name, so a browser that cached the old picture
 *     for a year never shows it again (M2.3's "replacement defeats caching").
 *   - The same bytes uploaded twice make ONE row (the unique index below),
 *     never a duplicate.
 *   - The browser's filename plays no part in where anything is written,
 *     which is what makes "../../.env" as a filename a non-event.
 */
final class CreateArtFiles extends AbstractMigration
{
    public function change(): void
    {
        $this->table('art_files', ['signed' => false])
            // sha256 hex (64) + '.' + extension (up to 4).
            ->addColumn('stored_name', 'string', ['limit' => 70, 'null' => false])
            // What the picture is FOR: sprite / icon / portrait / card /
            // background / library. Allow-listed in src/Art/ArtKind.php.
            ->addColumn('kind', 'string', ['limit' => 20, 'null' => false])
            // The name the browser sent, sanitised, for DISPLAY only ("which
            // one was 'bramble-adult.gif' again?"). Never used as a path.
            ->addColumn('original_name', 'string', ['limit' => 100, 'null' => false])
            // The real type, decided by reading the bytes: gif / png / webp.
            ->addColumn('type', 'string', ['limit' => 10, 'null' => false])
            ->addColumn('width', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('height', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('bytes', 'integer', ['signed' => false, 'null' => false])
            // Whether it animates. Decides how it was treated on upload:
            // animated files are stored byte-for-byte (minus metadata blocks),
            // still ones re-encoded. Also what the panel labels it with.
            ->addColumn('is_animated', 'boolean', ['default' => false, 'null' => false])
            // Who uploaded it — the delete rule (an artist may only delete
            // their own) is checked against THIS column, never the form.
            ->addColumn('uploaded_by_user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            // SET NULL, not CASCADE: an artist leaving must not take the
            // site's sprites with them. The picture stays; "uploaded by" goes
            // blank — the same shape as user_roles.granted_by_user_id.
            ->addForeignKey('uploaded_by_user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            // The content-hash rule (class note): one row per distinct file.
            ->addIndex(['stored_name'], ['unique' => true])
            // The Pictures screen lists by kind; M2.3 will ask "all sprites".
            ->addIndex(['kind', 'created_at'])
            ->create();
    }
}
