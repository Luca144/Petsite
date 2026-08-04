<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Makes a user's exploration visit unique per area.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS EXISTS: exploration tracks, per user and per area, how many clicks they
 * have used in the current window — so there should be exactly ONE row per
 * (user, area). This changes the existing (non-unique) index on those two columns
 * into a UNIQUE one, which both enforces "one row per user per area" and lets the
 * code update-or-insert that row in a single, safe statement.
 *
 * The exploration_visits table is empty at this point (exploration is being built
 * now), so there is no existing data to conflict with.
 *
 * We use up()/down() because swapping an index is not something Phinx can reverse
 * on its own — we spell out both directions.
 */
final class MakeExplorationVisitUnique extends AbstractMigration
{
    public function up(): void
    {
        $this->table('exploration_visits')
            ->removeIndex(['user_id', 'area_slug'])
            ->addIndex(['user_id', 'area_slug'], ['unique' => true])
            ->update();
    }

    public function down(): void
    {
        $this->table('exploration_visits')
            ->removeIndex(['user_id', 'area_slug'])
            ->addIndex(['user_id', 'area_slug'])
            ->update();
    }
}
