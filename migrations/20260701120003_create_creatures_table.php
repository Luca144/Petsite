<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the "creatures" table — the animals players collect.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS TABLE EXISTS: this is the heart of the game. Each row is one creature,
 * owned by one user, of one species. The one-to-many relationship (a user can own
 * many creatures) is built in from the start via the owner_id foreign key, even
 * though the early increments give a user just one.
 *
 * KEY DESIGN DECISION — growth is derived, not stored. We keep only "xp" (the
 * experience points a creature has earned). Its level and its life stage
 * (baby / juvenile / adult) are CALCULATED from xp using thresholds in config
 * (from increment B.2). Storing them as columns too would risk them drifting out
 * of sync with xp; deriving them keeps a single source of truth and makes
 * "level creatures faster" a one-line config change.
 */
final class CreateCreaturesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('creatures', ['signed' => false]);

        $table
            // The owner. UNSIGNED to match users.id exactly (a foreign key must
            // have the same type as the column it points at).
            ->addColumn('owner_id', 'integer', ['signed' => false, 'null' => false])
            // Which kind of creature this is.
            ->addColumn('species_id', 'integer', ['signed' => false, 'null' => false])
            // The name the owner gives this creature.
            ->addColumn('name', 'string', ['limit' => 40, 'null' => false])
            // Experience points — the single source of truth for growth (see the
            // class docblock). Level and stage are computed from this.
            ->addColumn('xp', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            // The interaction stat, shown on the creature page. It is a simple
            // counter that only goes up when the creature is petted — it does NOT
            // decay over time (the simpler model chosen for this project).
            ->addColumn('happiness', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            // A short bio the owner can write (increment C.1). Genuinely optional,
            // so NULL is allowed until one is written.
            ->addColumn('bio', 'text', ['null' => true])
            // Whether logged-out visitors may see this creature (increment B.6).
            ->addColumn('is_public', 'boolean', ['default' => true, 'null' => false])
            // When the creature was last petted — used for the interaction
            // cooldown and for "last petted" display. Null until first petted.
            ->addColumn('last_interacted_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            // If a user is deleted, their creatures go with them (CASCADE).
            ->addForeignKey('owner_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            // A species that is in use cannot be deleted (RESTRICT) — this stops a
            // creature from ever pointing at a species that no longer exists.
            ->addForeignKey('species_id', 'species', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION'])
            // Indexes for the lists we will query often: public creatures, and
            // newest-first browsing (increment B.6). (The foreign-key columns are
            // indexed automatically by MariaDB.)
            ->addIndex(['is_public'])
            ->addIndex(['created_at'])
            ->create();
    }
}
