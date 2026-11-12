<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * More of the site's look in the artist's hands, without a developer.
 *
 * @package Felkyo\Migrations
 *
 * themes.style               JSON of allow-listed choices (fonts, corners,
 *                            frames, glow, drifting symbols) — see
 *                            Felkyo\Themes\ThemeStyle for every allowed value.
 *                            NULL = everything as the site's own look.
 * themes.background_art_id   a page background tile from the art store
 * themes.banner_art_id       a banner to use instead of the painted logo
 *                            (both SET NULL: deleting the picture just puts
 *                            the site's own back)
 *
 * custom_css_versions        the fine-tuning CSS box's undo history. Append-
 *                            only: every save (and every restore) adds a row,
 *                            nothing is ever overwritten. The CSS the site
 *                            actually prints is the site_settings row
 *                            "look.custom_css" (so reading it costs no extra
 *                            query); this table is what "go back" reads from.
 *
 * The site-wide choices (which theme everyone sees by default, section
 * order, the announcement line) are rows in the existing site_settings
 * table, under keys starting with "look." — no new table needed.
 */
final class CreateSiteLook extends AbstractMigration
{
    public function change(): void
    {
        $this->table('themes')
            ->addColumn('style', 'text', ['null' => true])
            ->addColumn('background_art_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('banner_art_id', 'integer', ['signed' => false, 'null' => true])
            ->addForeignKey('background_art_id', 'art_files', 'id', ['delete' => 'SET NULL'])
            ->addForeignKey('banner_art_id', 'art_files', 'id', ['delete' => 'SET NULL'])
            ->update();

        $this->table('custom_css_versions', ['signed' => false])
            ->addColumn('css', 'text', ['null' => false])
            ->addColumn('note', 'string', ['limit' => 120, 'null' => false, 'default' => ''])
            ->addColumn('saved_by_user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('saved_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('saved_by_user_id', 'users', 'id', ['delete' => 'SET NULL'])
            ->create();
    }
}
