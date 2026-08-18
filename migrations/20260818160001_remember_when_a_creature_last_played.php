<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Remembers when a creature last had a game, so playing has a gentle rhythm.
 *
 * @package Felkyo\Migrations
 *
 * WHY A COLUMN AND NOT A TABLE. Petting records every single pet in its own table,
 * because two different questions need the history: "has THIS person petted this
 * creature recently?" and "how many times has it been petted altogether?" Playing
 * asks neither. Only a creature's owner can play with it, so there is exactly one
 * person the cooldown could ever be about, and nothing displays a play count. One
 * timestamp answers the only question there is.
 *
 * If a "played together" count is ever wanted on a creature's page, THAT is when
 * this becomes a table — and it will be a small migration, not a rewrite.
 *
 * WHAT THE COOLDOWN IS FOR. Playing cheers a creature up for free, where a treat
 * costs gems. Without a cooldown it would simply be a better treat, and the whole
 * economy would route around the shop. Five minutes makes it a small event you
 * come back to rather than a button you hold down.
 *
 * WHAT IT IS NOT FOR: it never stops anybody from playing. A creature on cooldown
 * still plays the game and still enjoys it; the mood bonus is what waits. Being
 * told "come back later" is not a sentence this site says.
 */
final class RememberWhenACreatureLastPlayed extends AbstractMigration
{
    public function up(): void
    {
        $this->table('creatures')
            // NULL means "never played", which is true of every creature that
            // exists the moment this runs — and reads correctly as "the cooldown
            // has certainly passed" without needing a made-up date in the past.
            ->addColumn('last_played_at', 'timestamp', [
                'null' => true,
                'after' => 'last_interacted_at',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('creatures')->removeColumn('last_played_at')->update();
    }
}
