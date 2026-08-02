<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the "users" table — one row per player account.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS TABLE EXISTS: everything a player does hangs off their account. A user
 * owns creatures, holds the in-game currency balance, and owns inventory. We keep
 * the currency balance and the "last adopted" timestamp directly on the user
 * because there is exactly one of each per user — a separate table would add
 * complexity for no benefit.
 *
 * The change() method is automatically reversible: Phinx knows how to undo a
 * table creation, so we do not need to write a separate "down" method.
 */
final class CreateUsersTable extends AbstractMigration
{
    public function change(): void
    {
        // 'signed' => false makes the auto-generated "id" an UNSIGNED integer:
        // ids are never negative, so we allow twice the positive range.
        $table = $this->table('users', ['signed' => false]);

        $table
            // Login name shown around the site. Kept short and unique. Required,
            // so we forbid NULL ('null' => false) — every account has a username.
            ->addColumn('username', 'string', ['limit' => 30, 'null' => false])
            // Used for account identity (no email is sent in the demo). Required.
            ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
            // The password is stored ONLY as a hash from password_hash() — never
            // in plain text (CLAUDE.md section 6). 255 chars leaves room for the
            // algorithm PHP's default may upgrade to in future. Required.
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => false])
            // The single in-game currency, held as a running balance on the user.
            ->addColumn('currency_balance', 'integer', ['signed' => false, 'default' => 0, 'null' => false])
            // When the user last adopted a creature — powers the once-per-day
            // adoption limit (increment B.4). Null until they first adopt.
            ->addColumn('last_adopted_at', 'datetime', ['null' => true])
            // When the user last logged in. Null until their first login.
            ->addColumn('last_login_at', 'datetime', ['null' => true])
            // Set once, on creation. 'update' => '' stops MariaDB from also
            // refreshing this whenever the row changes.
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            // Usernames and emails must be unique — two accounts cannot share one.
            ->addIndex(['username'], ['unique' => true])
            ->addIndex(['email'], ['unique' => true])
            ->create();
    }
}
