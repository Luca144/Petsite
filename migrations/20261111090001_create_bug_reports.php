<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Bug reports: a logged-in player tells us something on the site is broken,
 * from the page where it happened.
 *
 * @package Felkyo\Migrations
 *
 * WHY A SEPARATE TABLE FROM `reports`. The reports table is for people and
 * their words (moderation): every row points at a player. A bug report points
 * at a PAGE, and is read by whoever fixes the code, not by a moderator. Mixing
 * the two would put "the shop button is cut off on my phone" in the queue
 * where somebody is deciding whether to suspend an account.
 *
 * Text only, on purpose: no screenshot uploads. Uploads are the riskiest thing
 * a site can accept, and many of our players are children.
 *
 * bug_reports
 *   reporter_user_id   who sent it. CASCADE: deleting an account takes the
 *                      person's own words with it (data protection), even
 *                      though a sender-less report could still be read.
 *   page_path          the address they were on, filled in automatically
 *   browser            the browser's own description of itself (user agent)
 *   screen_width       how wide their window was, for layout bugs (nullable)
 *   description        what happened, in their words
 *   status             'open' | 'fixed' | 'closed'
 *   handled_by_user_id / handled_at   who ticked it off, and when
 *
 * The (status, created_at) index serves the panel's only query: open reports,
 * newest first.
 */
final class CreateBugReports extends AbstractMigration
{
    public function change(): void
    {
        $this->table('bug_reports', ['signed' => false])
            ->addColumn('reporter_user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('page_path', 'string', ['limit' => 300, 'null' => false])
            ->addColumn('browser', 'string', ['limit' => 300, 'null' => false, 'default' => ''])
            ->addColumn('screen_width', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('description', 'text', ['null' => false])
            ->addColumn('status', 'string', ['limit' => 10, 'null' => false, 'default' => 'open'])
            ->addColumn('handled_by_user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('handled_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['status', 'created_at'])
            ->addForeignKey('reporter_user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('handled_by_user_id', 'users', 'id', ['delete' => 'SET NULL'])
            ->create();
    }
}
