<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * M5 — the library: alternate forms for species, and the newspaper.
 *
 * @package Felkyo\Migrations
 *
 * Three things arrive together because the library page shows all of them:
 *
 *   species_forms        — an alternate look for a species ("Frost Foxlen").
 *                          Pure data: which species get alternates is the
 *                          artist's call, made in the panel.
 *   species_form_stages  — the form's own sprite per life stage. A stage with
 *                          no sprite here falls back to the species' default
 *                          sprite, so a half-drawn form never shows a hole.
 *   creatures.form_id    — which form a creature wears. NULL = the default
 *                          form, which is what every existing creature keeps.
 *   articles             — the Felkyo Gazette: newspaper pieces about the NPCs,
 *                          authored in the panel, read in the library.
 *
 * Species slugs and article slugs appear in addresses, so both are UNIQUE —
 * the database refuses a duplicate even if the code forgets to check.
 */
final class CreateLibrary extends AbstractMigration
{
    public function change(): void
    {
        $this->table('species_forms', ['signed' => false])
            ->addColumn('species_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('slug', 'string', ['limit' => 60, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 60, 'null' => false])
            ->addColumn('blurb', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 10, 'default' => 'draft', 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addForeignKey('species_id', 'species', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addIndex(['species_id', 'slug'], ['unique' => true])
            ->create();

        $this->table('species_form_stages', ['signed' => false])
            ->addColumn('form_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('stage', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('sprite_art_id', 'integer', ['signed' => false, 'null' => true])
            ->addForeignKey('form_id', 'species_forms', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('sprite_art_id', 'art_files', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->addIndex(['form_id', 'stage'], ['unique' => true])
            ->create();

        // SET NULL on delete: removing a form turns its creatures back into the
        // default look instead of deleting anybody's creature.
        $this->table('creatures')
            ->addColumn('form_id', 'integer', ['signed' => false, 'null' => true, 'after' => 'species_id'])
            ->addForeignKey('form_id', 'species_forms', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->update();

        $this->table('articles', ['signed' => false])
            ->addColumn('slug', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('title', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('summary', 'string', ['limit' => 300, 'null' => true])
            ->addColumn('body', 'text', ['null' => true])
            ->addColumn('art_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 10, 'default' => 'draft', 'null' => false])
            ->addColumn('published_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addForeignKey('art_id', 'art_files', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->addIndex(['slug'], ['unique' => true])
            // The gazette lists published pieces newest first.
            ->addIndex(['status', 'published_at'])
            ->create();
    }
}
