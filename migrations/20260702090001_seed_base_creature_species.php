<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seeds the initial creature species.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS EXISTS: the game needs at least one species to exist before a player
 * can be given a starter creature. Species are content stored as data, and this
 * migration inserts the first few so the app works out of the box (and so the
 * tests have species to use).
 *
 * PLACEHOLDER NAMES: the names below are placeholders paired with the provided
 * sprite art. The Product Owner may rename them — that is just a data change (an
 * UPDATE, e.g. via a follow-up migration). Their "slug" also names the folder
 * that holds their images: public/assets/creatures/{slug}/{stage}.gif.
 *
 * HOW TO ADD ANOTHER SPECIES later: add a row here (or in a new migration) with a
 * unique slug and a display name, and drop its baby/juvenile/adult images into
 * public/assets/creatures/{slug}/. No code changes are needed.
 *
 * We use up()/down() rather than change() because Phinx cannot automatically
 * reverse inserted data — so we spell out how to undo it.
 */
final class SeedBaseCreatureSpecies extends AbstractMigration
{
    public function up(): void
    {
        // All three are marked as starters and adoptable, so new players get some
        // variety and later features (adoption, exploration) have a pool to draw from.
        $this->table('species')->insert([
            ['slug' => 'foxlen', 'name' => 'Foxlen', 'is_starter' => 1, 'is_adoptable' => 1],
            ['slug' => 'mossling', 'name' => 'Mossling', 'is_starter' => 1, 'is_adoptable' => 1],
            ['slug' => 'pebblewing', 'name' => 'Pebblewing', 'is_starter' => 1, 'is_adoptable' => 1],
        ])->saveData();
    }

    public function down(): void
    {
        $this->execute(
            "DELETE FROM species WHERE slug IN ('foxlen', 'mossling', 'pebblewing')"
        );
    }
}
