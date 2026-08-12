<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Stores a "skeleton" of each username, so lookalike names can be found quickly.
 *
 * @package Felkyo\Migrations
 *
 * THE PROBLEM: "mira" and "m1ra" and "rnira" are three different strings and one
 * name, as far as a person reading quickly is concerned. Somebody who registers
 * the second one can be mistaken for the first — which is worth doing if the
 * first is trusted, and worth doing to a child by an adult who wants to be
 * mistaken for their friend.
 *
 * THE OBVIOUS FIX IS TOO SLOW: comparing a new name against every existing one,
 * on every registration, means reading the whole users table each time. That is
 * fine with twelve accounts and hopeless with twelve thousand, and the moment it
 * becomes slow is the moment somebody quietly removes the check.
 *
 * SO WE STORE THE COMPARISON. ImpersonationGuard::skeletonOf() reduces a name to
 * a dull form — case, digits-for-letters, spacing, accents and lookalike letters
 * all flattened. That form is saved here and indexed, so "is anybody already
 * using a name that reads like this one?" is a single indexed lookup.
 *
 * WHY THE INDEX IS NOT UNIQUE: the existing demo accounts might already collide,
 * and a migration that fails on live data at 2am is worse than one that lets a
 * rare duplicate through. Uniqueness is enforced by the check in
 * RegistrationService, where it can produce a kind message instead of an error.
 */
final class AddUsernameSkeleton extends AbstractMigration
{
    public function up(): void
    {
        $this->table('users')
            ->addColumn('username_skeleton', 'string', [
                'limit' => 40,
                'default' => '',
                'null' => false,
                'after' => 'username',
            ])
            ->addIndex(['username_skeleton'])
            ->update();

        // Fill it in for accounts that already exist. This does the same job as
        // skeletonOf() for the plain ASCII names the current rules allow: lower
        // case, digits mapped back to the letters they imitate, and anything that
        // is not a letter or number removed.
        //
        // It is written out in SQL rather than looping in PHP because a migration
        // should not need the application's classes to be loadable — those move
        // and get renamed, and a migration has to still run in five years.
        $this->execute(
            "UPDATE users SET username_skeleton = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                 REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(username),
                 '_', ''), '-', ''),
                 '0', 'o'), '1', 'i'), '3', 'e'), '4', 'a'), '5', 's'),
                 '7', 't'), '8', 'b'), '9', 'g'), 'rn', 'm')"
        );
    }

    public function down(): void
    {
        $this->table('users')->removeIndex(['username_skeleton'])->removeColumn('username_skeleton')->update();
    }
}
