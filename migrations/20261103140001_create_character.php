<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * M3 — the pound, and who a creature is.
 *
 * @package Felkyo\Migrations
 *
 * creatures (new columns):
 *   owner_id becomes NULLABLE. A creature at the pound belongs to NOBODY —
 *   which is what makes the pound safe: every existing "is this yours?" check
 *   in the codebase compares owner_id with the viewer and now refuses
 *   everyone, without each of those checks having to learn about the pound.
 *   pound_entered_at          when it arrived (NULL = not at the pound).
 *   pound_previous_owner_id   who brought it — the one person who may
 *                             reclaim it during the grace period, and the one
 *                             person who may never adopt it back afterwards.
 *   is_frozen / shown_stage   freezing at a stage (M3.2b). shown_stage
 *                             overrides the stage worked out from XP; while
 *                             catching up after an unfreeze it walks forward
 *                             one stage per growth tick, then clears.
 *
 * traits / creature_traits  — personality (earned) and system (given) traits.
 * ladder_rungs              — level titles and the friendly-age ladder.
 *
 * Starter data for traits and both ladders is inserted below; all of it is
 * editable in the panel (/admin/character) afterwards.
 */
final class CreateCharacter extends AbstractMigration
{
    public function up(): void
    {
        $this->table('creatures')
            ->changeColumn('owner_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('pound_entered_at', 'datetime', ['null' => true])
            ->addColumn('pound_previous_owner_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('is_frozen', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('shown_stage', 'string', ['limit' => 20, 'null' => true])
            // The pound page lists creatures by arrival time.
            ->addIndex(['pound_entered_at'])
            ->addIndex(['pound_previous_owner_id'])
            ->update();

        $this->table('traits', ['signed' => false])
            ->addColumn('slug', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('description', 'string', ['limit' => 200, 'null' => false])
            // 'earned' (by living), 'shelter' (given on leaving the pound),
            // 'gift' (a chance reward on adoption — "grateful").
            ->addColumn('tier', 'string', ['limit' => 10, 'null' => false])
            // For earned traits: what earns it, and how much of it.
            ->addColumn('earn_kind', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('earn_threshold', 'integer', ['signed' => false, 'null' => true])
            // For gift traits: the chance in percent of receiving it.
            ->addColumn('chance_percent', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            // The ONE mechanical effect a trait may have (build plan M3.2).
            ->addColumn('xp_bonus_percent', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        $this->table('creature_traits', ['id' => false, 'primary_key' => ['creature_id', 'trait_id']])
            ->addColumn('creature_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('trait_id', 'integer', ['signed' => false, 'null' => false])
            // In words, how it came by this ("after its first 50 pets").
            ->addColumn('how', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('gained_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addForeignKey('creature_id', 'creatures', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('trait_id', 'traits', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('ladder_rungs', ['signed' => false])
            ->addColumn('ladder', 'string', ['limit' => 20, 'null' => false])
            ->addColumn('threshold', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('label', 'string', ['limit' => 60, 'null' => false])
            ->addIndex(['ladder', 'threshold'], ['unique' => true])
            ->create();

        $this->seedStarterData();
    }

    public function down(): void
    {
        $this->table('ladder_rungs')->drop()->save();
        $this->table('creature_traits')->drop()->save();
        $this->table('traits')->drop()->save();
        $this->table('creatures')
            ->removeColumn('pound_entered_at')
            ->removeColumn('pound_previous_owner_id')
            ->removeColumn('is_frozen')
            ->removeColumn('shown_stage')
            ->update();
    }

    private function seedStarterData(): void
    {
        $this->table('traits')->insert([
            ['slug' => 'well-loved', 'name' => 'Well-loved', 'description' => 'Has been petted so often it purrs at footsteps.', 'tier' => 'earned', 'earn_kind' => 'petted', 'earn_threshold' => 50],
            ['slug' => 'cherished', 'name' => 'Cherished', 'description' => 'Knows, deep down, that it is somebody\'s favourite.', 'tier' => 'earned', 'earn_kind' => 'petted', 'earn_threshold' => 250],
            ['slug' => 'curious', 'name' => 'Curious', 'description' => 'Grew up asking questions about everything.', 'tier' => 'earned', 'earn_kind' => 'level', 'earn_threshold' => 5],
            ['slug' => 'wise', 'name' => 'Wise', 'description' => 'Has seen a great deal, and is not easily surprised.', 'tier' => 'earned', 'earn_kind' => 'level', 'earn_threshold' => 15],
            ['slug' => 'old-friend', 'name' => 'Old friend', 'description' => 'Has been around long enough to remember the old days.', 'tier' => 'earned', 'earn_kind' => 'age_days', 'earn_threshold' => 100],
            ['slug' => 'shy', 'name' => 'Shy', 'description' => 'Needs a little time before it trusts a new face.', 'tier' => 'shelter'],
            ['slug' => 'watchful', 'name' => 'Watchful', 'description' => 'Always keeps one eye on the door.', 'tier' => 'shelter'],
            ['slug' => 'hopeful', 'name' => 'Hopeful', 'description' => 'Looks up whenever someone comes in.', 'tier' => 'shelter'],
            ['slug' => 'scrappy', 'name' => 'Scrappy', 'description' => 'Learned to look after itself at the pound.', 'tier' => 'shelter'],
            ['slug' => 'grateful', 'name' => 'Grateful', 'description' => 'Never forgets who gave it a home. Grows a little faster for it.', 'tier' => 'gift', 'chance_percent' => 30, 'xp_bonus_percent' => 10],
        ])->saveData();

        $rungs = [];
        foreach ([1 => 'Newcomer', 3 => 'Sprout', 6 => 'Sprightly', 10 => 'Steady', 15 => 'Seasoned', 25 => 'Venerable', 40 => 'Legendary'] as $level => $label) {
            $rungs[] = ['ladder' => 'level_title', 'threshold' => $level, 'label' => $label];
        }
        foreach ([0 => 'brand new', 2 => 'a few days old', 7 => 'about a week old', 14 => 'a few weeks old', 45 => 'a couple of months old', 120 => 'several months old', 300 => 'getting on for a year', 365 => 'over a year old', 730 => 'a grand old age'] as $days => $label) {
            $rungs[] = ['ladder' => 'age', 'threshold' => $days, 'label' => $label];
        }
        $this->table('ladder_rungs')->insert($rungs)->saveData();
    }
}
