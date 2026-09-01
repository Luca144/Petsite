<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The moderation increment (M2.7): timed account states, and notices.
 *
 * @package Felkyo\Migrations
 *
 * TWO TIMED STATES ON THE ACCOUNT, both NULL for a player in good
 * standing:
 *
 *  - muted_until: the player cannot write NEW free text (bios, about
 *    texts, names, guestbook signing) until this moment, but plays on
 *    normally — petting, feeding, exploring, buying all still work.
 *    Free-text fields are the one place a player can put chosen words in
 *    front of another player, so muting them IS muting the player,
 *    without taking their creatures hostage.
 *  - suspended_until: the account cannot log in until this moment, and a
 *    live session ends at its next request. A timestamp, not a boolean:
 *    every suspension has a built-in end, so a forgotten one expires
 *    instead of quietly becoming forever.
 *
 * NOTICES are the one-way channel the build plan asks for: the site can
 * tell a player something ("your creature's description was removed"),
 * delivered as a card on their next page view, acknowledged with one
 * button, never repliable. `kind` is a TEMPLATE KEY, never free text —
 * even staff don't get a typing channel; the words live in code where
 * they were written calmly (ModerationNotice::words). No staff id on the
 * row: the player is told what happened, not who did it (the audit log
 * carries who, for the owner).
 */
final class CreateModeration extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('muted_until', 'datetime', ['null' => true, 'after' => 'about_hidden_at'])
            ->addColumn('suspended_until', 'datetime', ['null' => true, 'after' => 'muted_until'])
            ->update();

        $this->table('moderation_notices', ['signed' => false])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            // The template key, e.g. "bio_removed". An allow-list in PHP
            // (ModerationNotice) is what turns it into words; an unknown key
            // renders a safe generic notice rather than nothing.
            ->addColumn('kind', 'string', ['limit' => 40, 'null' => false])
            // Machine-formatted detail for kinds that need one — today only a
            // date ("until 2026-09-07" on mute/suspend cards). Written by the
            // moderation service from a DateTime, NEVER from typed input, so
            // the no-free-text rule holds.
            ->addColumn('detail', 'string', ['limit' => 60, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            // When the player pressed "I've read this" — NULL means the card
            // still shows. Kept (not deleted) so account history can show
            // what the player was told and when they saw it.
            ->addColumn('read_at', 'datetime', ['null' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            // The every-page question is "does this player have unread
            // notices?" — this index answers it without touching read rows.
            ->addIndex(['user_id', 'read_at'])
            ->create();
    }
}
