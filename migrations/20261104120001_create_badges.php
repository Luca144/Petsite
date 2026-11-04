<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Keepsake badges (M8, pulled forward for the birthday sprint).
 *
 * @package Felkyo\Migrations
 *
 * badges           the badge itself: a name, a few words, a picture (art kind
 *                  "badge"), and who it is for:
 *                    'creature' — earned automatically by a creature that has
 *                                 been in Felkyo for kept_days days. Only ever
 *                                 earned, never lost: absence is not punished.
 *                    'player'   — special badges ("Founder") the owner gives
 *                                 from the gift desk. Never earned by playing.
 * creature_badges  which creature earned which badge, and when.
 * player_badges    which player was given which badge, when, and why.
 *
 * Both award tables have a composite primary key, so a badge can never be
 * given twice to the same creature or player (INSERT IGNORE, house pattern 2).
 */
final class CreateBadges extends AbstractMigration
{
    public function up(): void
    {
        $this->table('badges', ['signed' => false])
            ->addColumn('slug', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('description', 'string', ['limit' => 200, 'null' => false])
            ->addColumn('art_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('awarded_to', 'string', ['limit' => 10, 'null' => false])
            ->addColumn('kept_days', 'integer', ['signed' => false, 'null' => true])
            ->addForeignKey('art_id', 'art_files', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        $this->table('creature_badges', ['id' => false, 'primary_key' => ['creature_id', 'badge_id']])
            ->addColumn('creature_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('badge_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('earned_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addForeignKey('creature_id', 'creatures', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('badge_id', 'badges', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('player_badges', ['id' => false, 'primary_key' => ['user_id', 'badge_id']])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('badge_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('reason', 'string', ['limit' => 200, 'null' => true])
            ->addColumn('given_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('badge_id', 'badges', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('badges')->insert([
            ['slug' => 'one-moon', 'name' => 'One moon together', 'description' => 'A whole month in Felkyo.', 'awarded_to' => 'creature', 'kept_days' => 30],
            ['slug' => 'hundred-days', 'name' => 'A hundred days', 'description' => 'One hundred days of being looked after.', 'awarded_to' => 'creature', 'kept_days' => 100],
            ['slug' => 'whole-year', 'name' => 'A whole year', 'description' => 'Round the seasons once, together.', 'awarded_to' => 'creature', 'kept_days' => 365],
            ['slug' => 'founder', 'name' => 'Founder', 'description' => 'Here from the very beginning — and the reason it began.', 'awarded_to' => 'player', 'kept_days' => null],
        ])->saveData();
    }

    public function down(): void
    {
        foreach (['player_badges', 'creature_badges', 'badges'] as $table) {
            $this->table($table)->drop()->save();
        }
    }
}
