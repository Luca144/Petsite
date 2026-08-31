<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Makes species EDITABLE: draft/publish, per-stage art and words, versions.
 *
 * @package Felkyo\Migrations
 *
 * M2.3 — one page per creature. Until now a species was a seeded row plus a
 * filename convention; from here it is something the artist builds in the
 * panel. Three changes:
 *
 * WHY status DEFAULTS TO 'published'. Backwards, at first glance — but the
 * default serves everything that inserts a species row WITHOUT going through
 * the panel: the three seeded species, and every test fixture written before
 * drafts existed. All of those are live content and must stay visible. The
 * panel's "start a new species" is the one place that creates DRAFTS, and it
 * says 'draft' explicitly. So the default keeps the past working and the
 * explicit value guards the future — the safe direction for each.
 *
 * WHY species_stages IS ITS OWN TABLE, keyed (species_id, stage): the stage
 * names live in config (growth.stage_start_levels) and the screen iterates
 * them — a fourth stage added in config becomes a fourth card, with no
 * migration. Columns-per-stage on species would hardcode three forever.
 * (Alternate FORMS were deliberately deferred to M5 — decisions.md
 * 2026-08-31; adding a form column here later is one small migration.)
 *
 * WHY species_versions STORES ONE JSON SNAPSHOT per save: the undo the plan
 * requires. A snapshot holds the species' words, numbers and its stages'
 * art REFERENCES — never bytes; art is content-addressed in the store, so
 * a version is a few hundred bytes however big the pictures are. Pruned to
 * the newest N (config: gameplay-independent 'species' section) on save.
 */
final class CreateSpeciesEditing extends AbstractMigration
{
    public function change(): void
    {
        $this->table('species')
            // 'draft' or 'published' — allow-listed in PHP (SpeciesStatus).
            ->addColumn('status', 'string', ['limit' => 10, 'default' => 'published', 'null' => false, 'after' => 'is_adoptable'])
            // Stamped by the editor on every save; NULL for rows that predate
            // editing (the seeds) until first touched.
            ->addColumn('updated_at', 'datetime', ['null' => true, 'after' => 'created_at'])
            // The library's long text (the library PAGE is M5; the field is
            // authored now so a species arrives there complete).
            ->addColumn('lore', 'text', ['null' => true, 'after' => 'flavour_text'])
            // The library illustration, from the art store.
            ->addColumn('library_art_id', 'integer', ['signed' => false, 'null' => true, 'after' => 'lore'])
            // SET NULL: deleting a picture must never delete a species; the
            // screen shows "missing picture" instead. (ArtService also refuses
            // deleting a picture that is still referenced — this is the
            // belt-and-braces beneath that.)
            ->addForeignKey('library_art_id', 'art_files', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            // The shop and starter pools filter on status now (with
            // is_adoptable / is_starter beside it).
            ->addIndex(['status'])
            ->update();

        // One row per (species, stage): the stage's sprite and its words.
        $this->table('species_stages', ['signed' => false])
            ->addColumn('species_id', 'integer', ['signed' => false, 'null' => false])
            // 'baby' / 'juvenile' / 'adult' — validated in PHP against the
            // config's stage list, so config and table cannot drift apart.
            ->addColumn('stage', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('sprite_art_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('blurb', 'text', ['null' => true])
            ->addForeignKey('species_id', 'species', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('sprite_art_id', 'art_files', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            // One row per stage per species — a save is an upsert against this.
            ->addIndex(['species_id', 'stage'], ['unique' => true])
            // "Which species use this picture?" — the delete guard's question.
            ->addIndex(['sprite_art_id'])
            ->create();

        // The undo: a snapshot per save, newest N kept.
        $this->table('species_versions', ['signed' => false])
            ->addColumn('species_id', 'integer', ['signed' => false, 'null' => false])
            // The whole editable state as JSON — see the class note.
            ->addColumn('snapshot', 'text', ['null' => false])
            // Who saved; SET NULL so departed staff take nothing with them.
            ->addColumn('saved_by_user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            ->addForeignKey('species_id', 'species', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('saved_by_user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            // The version list: one species, newest first.
            ->addIndex(['species_id', 'created_at'])
            ->create();
    }
}
