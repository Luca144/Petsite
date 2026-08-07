<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the "guestbook_entries" table — visitors signing a creature's guestbook.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS TABLE LOOKS THE WAY IT DOES:
 *
 * 1. We store a message KEY, not the message text. Visitors do not type anything;
 *    they pick one of a fixed list of friendly messages defined in
 *    config/config.php. Storing the short key (e.g. "what-a-sweetheart") means the
 *    Product Owner can reword a message and every entry that used it updates
 *    instantly, with no database change at all. It also means no user-written text
 *    is ever stored — which is what makes this guestbook essentially spam-proof.
 *
 * 2. The unique index on (creature_id, author_user_id) is the real enforcement of
 *    the rule "one entry per person per creature". The service layer checks it too
 *    and gives a friendly message, but the database is the guarantee: even a
 *    double-submitted form or two simultaneous requests cannot create a second row.
 *    Rules that matter should be enforced where they cannot be bypassed.
 *
 * 3. updated_at exists so we can enforce "you may change your entry once a day".
 *    It is set on creation and refreshed on every change; the cooldown is measured
 *    from it. We turn OFF MySQL's automatic ON UPDATE behaviour ('update' => '')
 *    so the value only ever changes when our own code decides it should.
 */
final class CreateGuestbookEntriesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('guestbook_entries', ['signed' => false]);

        $table
            // Whose guestbook this entry is in.
            ->addColumn('creature_id', 'integer', ['signed' => false, 'null' => false])
            // Who signed it.
            ->addColumn('author_user_id', 'integer', ['signed' => false, 'null' => false])
            // Which of the predefined messages they chose (the key, not the text).
            ->addColumn('message_key', 'string', ['limit' => 60, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            // When the entry was last changed. Starts equal to created_at.
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            // If a creature or a user is removed, their guestbook entries go too.
            ->addForeignKey('creature_id', 'creatures', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('author_user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            // One entry per person per creature — see note 2 above.
            ->addIndex(
                ['creature_id', 'author_user_id'],
                ['unique' => true, 'name' => 'unique_entry_per_author_and_creature']
            )
            // Makes "show this creature's guestbook, newest first" fast.
            ->addIndex(['creature_id', 'created_at'])
            ->create();
    }
}
