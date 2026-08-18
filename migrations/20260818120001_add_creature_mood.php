<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Gives every creature a mood: happiness that fades, and energy that returns.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS EXISTS (M2): until now a creature was a page you visited. You petted
 * it, a number went up forever, and nothing about it ever changed on its own.
 * M2 makes one creature something you keep — it has a mood, that mood moves while
 * you are away, and small acts of care move it back.
 *
 * WHAT "happiness" MEANT BEFORE, AND WHAT IT MEANS NOW. It used to be a tally: it
 * counted upward by one for every pet a creature had ever received and had no
 * ceiling. From here it is a 0–100 FEELING that falls slowly and is topped back
 * up by petting and treats. The old tally is not lost — "times petted" was always
 * counted properly from the pettings table, which is where that question belongs.
 *
 * THE TWO TIMESTAMPS ARE THE WHOLE DESIGN. We do not run anything on a schedule
 * to make moods drift. We store the value and WHEN IT WAS TRUE, and work out the
 * current value on read (see MoodCalculator). That is how XP already decides a
 * creature's level, and it has the property that matters here: there is no
 * scheduled job whose failure would silently freeze every creature in the game.
 */
final class AddCreatureMood extends AbstractMigration
{
    public function up(): void
    {
        $this->table('creatures')
            // A creature is BORN HAPPY, and the database is what decides that —
            // CreatureRepository::create() has always let the schema supply the
            // starting values rather than passing them in, and the three places
            // that make creatures (hatching, adopting, finding one) all go through
            // it. config's gameplay.mood.starting_happiness documents the same
            // number for humans, and CreatureMoodTest proves the two agree, so a
            // change to one that forgets the other fails the build.
            ->changeColumn('happiness', 'integer', [
                'signed' => false,
                'default' => 80,
                'null' => false,
            ])
            // When happiness was last SET to the stored value. Everything since
            // then is worked out on read.
            ->addColumn('happiness_at', 'timestamp', [
                'null' => true,
                'after' => 'happiness',
            ])
            // Energy: how rested a creature is. UNSIGNED because, like happiness,
            // it is a 0–100 reading and a negative one would be meaningless.
            ->addColumn('energy', 'integer', [
                'signed' => false,
                'default' => 100,
                'null' => false,
                'after' => 'happiness_at',
            ])
            ->addColumn('energy_at', 'timestamp', [
                'null' => true,
                'after' => 'energy',
            ])
            ->update();

        // Bring the creatures that already exist onto the new scale.
        //
        // Their stored happiness is a pet TALLY, which for a creature nobody has
        // petted is 0 — and 0 on the new scale would mean every existing creature
        // suddenly reads as sleepy, which would be a lie about how they have been
        // looked after. So the tally is clamped into a band that starts at
        // "happy": a much-petted creature lands at 100, an unpetted one at 70.
        // Nobody's creature is worse off the morning this ships, which is the only
        // acceptable direction for a migration that changes what a number means.
        $this->execute('UPDATE creatures SET happiness = LEAST(GREATEST(happiness, 70), 100)');

        // Both readings are "true as of" the last time anything happened to the
        // creature — or, for one nothing has ever happened to, as of its birth.
        // Starting the clock at NOW() instead would be the same thing said less
        // honestly, and would show every creature as freshly petted.
        $this->execute(
            'UPDATE creatures
                SET happiness_at = COALESCE(last_interacted_at, created_at),
                    energy_at = COALESCE(last_interacted_at, created_at)'
        );
    }

    public function down(): void
    {
        $this->table('creatures')
            ->removeColumn('happiness_at')
            ->removeColumn('energy')
            ->removeColumn('energy_at')
            ->update();
    }
}
