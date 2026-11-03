<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * M6 — postcards, bottles, and company.
 *
 * @package Felkyo\Migrations
 *
 * friends             a BOOKMARK: "I like visiting this person". One-way, no
 *                     consent needed, and it unlocks nothing (build plan
 *                     M6.1) — so it needs no approval, exactly like
 *                     bookmarking a page.
 * blocks              "nothing from this person reaches me": no cards, and
 *                     their bottles never wash up on my shore.
 * postcard_designs    the cards on sale at the post office: art + a FIXED
 *                     message the project wrote. Players never type words.
 * postcards           a card sent: from, to, which design. Shown publicly on
 *                     the recipient's page, like a mantelpiece.
 * bottles             a message in a bottle: a FIXED message key (the
 *                     catalogue lives in config, kept warm and never
 *                     confessional), its sender, and who found it.
 * daily_claims        house pattern 2: one row per person per action per
 *                     day, UNIQUE — the database refuses a second bottle sent
 *                     or found on the same day.
 */
final class CreateCompany extends AbstractMigration
{
    public function up(): void
    {
        $this->table('friends', ['id' => false, 'primary_key' => ['user_id', 'friend_user_id']])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('friend_user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('friend_user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('blocks', ['id' => false, 'primary_key' => ['user_id', 'blocked_user_id']])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('blocked_user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('blocked_user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            // "Has anyone blocked ME?" is asked when sending.
            ->addIndex(['blocked_user_id'])
            ->create();

        $this->table('postcard_designs', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 60, 'null' => false])
            ->addColumn('message', 'string', ['limit' => 200, 'null' => false])
            ->addColumn('art_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('gem_price', 'integer', ['signed' => false, 'default' => 2, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 10, 'default' => 'draft', 'null' => false])
            ->addForeignKey('art_id', 'art_files', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('postcards', ['signed' => false])
            ->addColumn('sender_user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('recipient_user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('design_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('sent_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addForeignKey('sender_user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('recipient_user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('design_id', 'postcard_designs', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            // The mantelpiece: a recipient's cards, newest first.
            ->addIndex(['recipient_user_id', 'sent_at'])
            ->create();

        $this->table('bottles', ['signed' => false])
            ->addColumn('sender_user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('message_key', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('sent_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('finder_user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('found_at', 'datetime', ['null' => true])
            ->addForeignKey('sender_user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('finder_user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            // "Any bottle still adrift?" — the shore looks for unfound ones.
            ->addIndex(['finder_user_id', 'sent_at'])
            ->create();

        $this->table('daily_claims', ['id' => false, 'primary_key' => ['user_id', 'action_key', 'action_date']])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('action_key', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('action_date', 'date', ['null' => false])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        // Starter cards — words written by the project, never by players.
        $this->table('postcard_designs')->insert([
            ['name' => 'Thinking of you', 'message' => 'Thinking of you and your creatures today!', 'gem_price' => 2, 'status' => 'published'],
            ['name' => 'Thank you', 'message' => 'Thank you for being so kind.', 'gem_price' => 2, 'status' => 'published'],
            ['name' => 'Your garden of friends', 'message' => 'Your creatures always make me smile.', 'gem_price' => 3, 'status' => 'published'],
            ['name' => 'Cosy evening', 'message' => 'Wishing you a warm and cosy evening.', 'gem_price' => 3, 'status' => 'published'],
            ['name' => 'Happy day', 'message' => 'Happy, happy day to you!', 'gem_price' => 5, 'status' => 'published'],
        ])->saveData();
    }

    public function down(): void
    {
        foreach (['daily_claims', 'bottles', 'postcards', 'postcard_designs', 'blocks', 'friends'] as $table) {
            $this->table($table)->drop()->save();
        }
    }
}
