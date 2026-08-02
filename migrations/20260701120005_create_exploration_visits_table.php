<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the "exploration_visits" table — tracks a user's clicks in an area.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS TABLE EXISTS: exploration areas (increment B.5) let a user click spots
 * for rewards, but only a limited number of times per visit, refreshing after a
 * while. This table remembers, per user and per area, how many clicks they have
 * used in the current time window and when that window started, so the limit can
 * be enforced and later refreshed.
 *
 * The area itself is content/config identified by a slug (e.g. "whispering-wood");
 * areas are not stored as their own table because, like species, they are content
 * defined in config.
 */
final class CreateExplorationVisitsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('exploration_visits', ['signed' => false]);

        $table
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            // Which area this row is counting clicks for.
            ->addColumn('area_slug', 'string', ['limit' => 50, 'null' => false])
            // How many clicks the user has spent in the current window.
            ->addColumn('clicks_used', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            // When the current window began — the limit refreshes relative to this.
            ->addColumn('window_started_at', 'datetime', ['null' => false])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            // We look these up by (user, area) together, so index that pair.
            ->addIndex(['user_id', 'area_slug'])
            ->create();
    }
}
