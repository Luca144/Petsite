<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the "species" table — the kinds of creature that can exist.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS TABLE EXISTS: species are CONTENT, and content lives as data, not as
 * hardcoded logic (build plan section 2). Adding a new kind of creature should be
 * inserting a row here (plus dropping in its art), not writing code.
 *
 * WHERE THE IMAGES LIVE: we do NOT store image paths here. Each species' animated
 * images are found by a naming convention:
 *     public/assets/creatures/{slug}/{stage}.gif
 * for example public/assets/creatures/mossling/baby.gif. That keeps this table
 * simple and means art is added by following the convention, not by editing rows.
 */
final class CreateSpeciesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('species', ['signed' => false]);

        $table
            // Short machine-friendly identifier, e.g. "mossling". Also the folder
            // name where this species' images live (see the docblock above).
            ->addColumn('slug', 'string', ['limit' => 50, 'null' => false])
            // The display name shown to players, e.g. "Mossling".
            ->addColumn('name', 'string', ['limit' => 60, 'null' => false])
            // Optional flavour text describing the species (used from C.2). This
            // one is genuinely optional, so NULL is allowed.
            ->addColumn('flavour_text', 'text', ['null' => true])
            // Whether a brand-new player can be given this species as their
            // starter creature (increment A.2).
            ->addColumn('is_starter', 'boolean', ['default' => false, 'null' => false])
            // Whether this species can appear in the daily-adoption / exploration
            // pool (increments B.4, B.5).
            ->addColumn('is_adoptable', 'boolean', ['default' => true, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            // Each slug identifies exactly one species.
            ->addIndex(['slug'], ['unique' => true])
            ->create();
    }
}
