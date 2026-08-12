<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the reports table, and the two "hidden pending review" flags.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS IS THE MOST IMPORTANT TABLE ON THE SITE. Every filter Felkyo has is a
 * floor, not a wall: a blocklist catches the obvious, a link detector catches the
 * casual. What catches the rest is a person noticing and saying so. Reporting is
 * therefore not a feature beside the safety work — it IS the safety work, and
 * everything else exists to keep its volume manageable.
 *
 * WHAT A REPORT POINTS AT. subject_type plus subject_id, rather than a foreign key.
 * A report can be about a username, a creature's name, a creature's bio, a
 * profile's about text, or a guestbook entry — five different tables — and later
 * about cards and bottles too. A single foreign key cannot point at five tables,
 * and five nullable columns would be five chances to fill in the wrong one.
 *
 * The cost of that choice is honest: the database cannot check that the thing
 * being reported exists. It is paid for with an allow-list of subject types in
 * PHP (ReportSubject) and a test that the two lists agree.
 *
 * ONE REPORT PER PERSON PER THING. The unique index is not tidiness. Without it,
 * one person could file the same report a hundred times and bury everything else
 * in a queue that two part-time humans have to read. It also makes "how many
 * DIFFERENT people reported this" a meaningful number, which is the single most
 * useful signal a small moderation team has.
 *
 * WHY BIOS HIDE AND NAMES DO NOT. A reported bio disappears behind a neutral
 * placeholder until somebody looks at it; a reported name is only flagged. That
 * asymmetry is deliberate. Hiding a name would break every page it appears on,
 * and would let anybody erase another player from the site by reporting them.
 * Hiding a bio costs its owner a few hours of a hidden paragraph in the worst
 * case — and with two people who both have day jobs, the alternative is harmful
 * text sitting in public overnight.
 */
final class CreateReports extends AbstractMigration
{
    public function change(): void
    {
        $this->table('reports', ['signed' => false])
            ->addColumn('reporter_user_id', 'integer', ['signed' => false, 'null' => false])
            // What KIND of thing this is about, e.g. "creature_bio". Checked
            // against an allow-list in PHP before it is ever written.
            ->addColumn('subject_type', 'string', ['limit' => 30, 'null' => false])
            // Which one — the id within that kind's own table.
            ->addColumn('subject_id', 'integer', ['signed' => false, 'null' => false])
            // Who the report is ABOUT. Kept alongside the subject because the
            // pattern is the risk: judging reports one at a time is how a repeat
            // offender slips through, each single thing looking borderline. This
            // column is what makes "everything about this account" one query.
            ->addColumn('about_user_id', 'integer', ['signed' => false, 'null' => false])
            // The reason, chosen from a fixed list. Never free text — a free-text
            // reason box is itself a channel for one player to send another words.
            ->addColumn('category', 'string', ['limit' => 30, 'null' => false])
            // How urgently this needs a human, copied from the category at the
            // time of reporting. Stored rather than looked up so that retuning a
            // category's priority later does not silently reorder old reports.
            ->addColumn('priority', 'integer', ['signed' => false, 'null' => false])
            // open / actioned / dismissed. The queue in M2.7 works from this.
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'open', 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            ->addForeignKey('reporter_user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('about_user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            // One report per person per thing — see the class note.
            ->addIndex(['reporter_user_id', 'subject_type', 'subject_id'], ['unique' => true])
            // The queue: most urgent first, oldest first within that.
            ->addIndex(['status', 'priority', 'created_at'])
            // Everything ever reported about one account, for seeing the pattern.
            ->addIndex(['about_user_id'])
            ->create();

        // The "hidden pending review" flags. A timestamp rather than a boolean,
        // so the queue can show how long something has been waiting — which is
        // the number that tells two busy people whether this is working.
        $this->table('creatures')
            ->addColumn('bio_hidden_at', 'datetime', ['null' => true, 'after' => 'bio'])
            ->update();

        $this->table('users')
            ->addColumn('about_hidden_at', 'datetime', ['null' => true, 'after' => 'about'])
            ->update();
    }
}
