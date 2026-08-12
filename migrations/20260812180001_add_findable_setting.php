<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Lets a player choose not to be findable by search.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS EXISTS (increment M1.5): search is safe on this site in a way it is
 * not on most, because there is nothing harmful you can do with a player once you
 * have found them — no messaging, no private channel, and every word between
 * players written by us. The worst somebody can do with a found profile is send a
 * pre-written card that everybody can see.
 *
 * But "safe by design" is not the same as "comfortable for everybody". Some
 * people simply do not want to be looked up, and the cost of letting them opt out
 * is one column. So it is a column.
 *
 * ON BY DEFAULT, because a site where nobody can find anybody is a lonely one and
 * most players want to be visited. Turning it off is one tick on the profile form.
 *
 * WHAT IT DOES NOT DO: hide the profile. A player who is not findable still has a
 * page, and somebody who already knows their name can still visit it — exactly
 * like an unlisted phone number. Making the page itself private would be a
 * different feature with different consequences (their creatures would vanish
 * from browse, their guestbook entries would dangle), and it is not what was
 * asked for.
 */
final class AddFindableSetting extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('is_findable', 'boolean', [
                'default' => true,
                'null' => false,
                'after' => 'about_hidden_at',
            ])
            // Search filters on this and matches a name prefix, so the two are
            // indexed together — the database can then answer a search without
            // reading rows it is going to discard.
            ->addIndex(['is_findable', 'username'])
            ->update();
    }
}
