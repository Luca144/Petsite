<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Gives players a profile: an avatar, an about text, and featured creatures.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS EXISTS (increment M1.3): until now a player was an account — a name, a
 * password and a purse. A profile turns that into somewhere other people can
 * visit, which is most of what makes a site like this feel inhabited rather than
 * merely functional.
 *
 * THREE SMALL COLUMNS, AND WHY EACH IS SHAPED THE WAY IT IS:
 *
 * users.avatar_key — the KEY of a picture, never a filename and never a path.
 * That distinction is the whole security story of this migration. If this column
 * held a filename, somebody could type "../../../etc/passwd" or a web address
 * into it and we would faithfully put it in an <img> tag. Holding a key means the
 * value is looked up in a list the project controls, and anything not in that
 * list simply is not an avatar. See src/Users/AvatarSet.php.
 *
 * Players never upload an avatar, by design (build plan M1.3). Accepting uploaded
 * pictures would mean moderating pictures, which is far harder than moderating
 * text and is the easiest route for something genuinely harmful onto a site with
 * children on it. A chosen set removes that entirely.
 *
 * users.about — a short free text. It is deliberately the ONLY new place a player
 * can type words that another player reads, and it is length-capped and filtered
 * from the first day. M1.4 hardens every free-text field on the site at once
 * (links, lookalike characters, reporting); until then this carries the same
 * protection the creature bio already has.
 *
 * creatures.featured_order — which creatures a player shows off, and in what
 * order. NULL means "not featured", a number means "show it, in this position".
 * It sits on the creatures table rather than in a table of its own because it is
 * a fact ABOUT a creature, and the owned-thing model (docs/owned-things.md) keeps
 * kind-specific facts on the kind's own table.
 */
final class AddProfileFields extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            // Short, because it holds a key like "default" — not a path.
            ->addColumn('avatar_key', 'string', [
                'limit' => 40,
                'default' => 'default',
                'null' => false,
                'after' => 'currency_balance',
            ])
            // Genuinely optional: a profile with nothing written on it is fine,
            // and NULL says "never written" rather than "written, then emptied".
            ->addColumn('about', 'text', ['null' => true, 'after' => 'avatar_key'])
            ->update();

        $this->table('creatures')
            // NULL = not featured. UNSIGNED because a position is never negative.
            ->addColumn('featured_order', 'integer', [
                'signed' => false,
                'null' => true,
                'after' => 'is_public',
            ])
            // Featured creatures are looked up per owner, in order, every time
            // somebody opens a profile — so the database is told to expect that.
            ->addIndex(['owner_id', 'featured_order'])
            ->update();
    }
}
