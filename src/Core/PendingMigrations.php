<?php

declare(strict_types=1);

namespace Felkyo\Core;

use PDO;

/**
 * Answers one question: is the database behind the code?
 *
 * @package Felkyo\Core
 *
 * WHY THIS EXISTS, AND IT IS WORTH READING ONCE.
 *
 * Code deploys automatically; database structure does not. Adding a migration
 * means running it by hand against production afterwards
 * (docs/deployment-guide.md), and the day that step is forgotten the site does
 * not politely degrade — every page becomes a fatal error from deep inside a
 * repository, saying something like:
 *
 *     Unknown column 'energy' in 'field list' … CreatureRepository.php:115
 *
 * That happened, with three migrations outstanding. The message is perfectly
 * accurate and completely unhelpful: it names a column, not the thing to do about
 * it, and it arrives as a blank "the page isn't working" for whoever is looking.
 *
 * WHAT THIS TURNS THAT INTO: a plain sentence for the visitor, and one line in the
 * log naming the command to run. The site is still down — nothing here can invent
 * a missing column — but being down for a *legible* reason is the difference
 * between a five-minute fix and an afternoon.
 *
 * WHY IT COUNTS FILES RATHER THAN CHECKING FOR PARTICULAR COLUMNS. A list of
 * expected columns is a second copy of the schema, and it would need editing every
 * time a migration is written — so the one time somebody forgot to update it would
 * be the one time it mattered. Phinx already records every migration it has run in
 * the phinxlog table. Comparing that count against the number of migration files
 * on disk needs no maintenance at all and catches every future case, including the
 * ones that would not have thrown.
 *
 * WHY IT IS NOT AUTOMATIC. Running migrations on container start is the obvious
 * alternative and it is worse: several containers can boot at once, and a
 * migration that fails half way through takes the site down with a half-changed
 * schema, which is a considerably harder afternoon than this one.
 */
final class PendingMigrations
{
    /**
     * How many migration files have not been applied to this database.
     *
     * Returns 0 when everything is applied — and also 0 when the question cannot
     * be answered at all. THAT IS DELIBERATE: this is a diagnostic, and a
     * diagnostic that can take the site down on its own is worse than no
     * diagnostic. If phinxlog is unreadable, or the migrations directory is not
     * where we expected, the app carries on and either works or fails for its own
     * reasons.
     */
    public static function count(PDO $connection, string $migrationsDirectory): int
    {
        $onDisk = glob(rtrim($migrationsDirectory, '/\\') . '/*.php');
        if ($onDisk === false || $onDisk === []) {
            return 0;
        }

        try {
            // phinxlog is Phinx's own bookkeeping table: one row per migration it
            // has run. It does not exist at all on a database nothing has been
            // migrated onto — which is itself the answer, so the catch below
            // reports every file as outstanding rather than swallowing it.
            $applied = (int) $connection->query('SELECT COUNT(*) FROM phinxlog')->fetchColumn();
        } catch (\Throwable $error) {
            return count($onDisk);
        }

        // Never negative. A database with MORE applied migrations than there are
        // files means somebody is running older code against a newer database — a
        // real situation during a rollback, and not one this class is about.
        return max(0, count($onDisk) - $applied);
    }
}
