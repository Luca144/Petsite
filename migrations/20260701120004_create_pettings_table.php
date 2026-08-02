<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the "pettings" table — one row each time a creature is petted.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS TABLE EXISTS (this is the important one to understand): petting is the
 * core interaction, and several features all need to know WHO petted WHICH
 * creature and WHEN:
 *   - the petting cooldown (increment B.1): has this person petted this creature
 *     too recently? — needs the last petting by this actor on this creature.
 *   - currency earning with an anti-spam cap (increment B.7): how many times has
 *     this actor petted in the current window? — needs a count over time.
 *   - "times petted" shown on the creature page — a count of these rows.
 *
 * A single timestamp column on the creature could not answer "has THIS person
 * petted recently", so we record each petting as its own row here. This one small
 * event table serves all three features cleanly and avoids a painful retrofit
 * later. The rule (built in later increments): a petting earns the OWNER currency
 * only when the actor is someone other than the owner.
 */
final class CreatePettingsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('pettings', ['signed' => false]);

        $table
            // The creature that was petted.
            ->addColumn('creature_id', 'integer', ['signed' => false, 'null' => false])
            // The user who did the petting ("actor" = the one performing the act).
            ->addColumn('actor_user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            // If a creature or user is removed, their petting records go too.
            ->addForeignKey('creature_id', 'creatures', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('actor_user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            // This combined index makes the cooldown question fast: "the most
            // recent petting by this actor on this creature". Ordering the columns
            // creature, actor, time lets the database jump straight to it.
            ->addIndex(['creature_id', 'actor_user_id', 'created_at'])
            ->create();
    }
}
