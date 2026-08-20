<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the admin foundation: roles, the audit log, and admin second factors.
 *
 * @package Felkyo\Migrations
 *
 * This is M2.1 — the ground the whole creator's panel stands on. Four tables:
 *
 * WHY ROLES ARE A JOIN TABLE AND NOT A COLUMN ON users. Roles are ADDITIVE —
 * the artist can also be an owner, a coder can also moderate. A single column
 * can hold one value; a join table holds any combination, one row per
 * (user, role). The role names themselves are allow-listed in PHP
 * (src/Admin/Role.php), and a test asserts the PHP list and the database
 * contents agree — the same honesty deal the reports table made.
 *
 * WHY THE UNIQUE INDEX ON (user_id, role) IS SECURITY, NOT TIDINESS. Granting
 * is "INSERT IGNORE" against this index, which makes the same grant arriving
 * twice at once a no-op instead of a second row. Duplicate rows would make
 * "how many owners are left?" — the check that stops the site ending up with
 * nobody able to assign roles — count wrong.
 *
 * WHY THE AUDIT LOG HAS NO DELETE PATH. Whoever reaches the panel owns the
 * site, so the last honest witness must be something they cannot quietly
 * edit. The repository for this table (AuditLogRepository) has insert and
 * read methods only, and no route removes rows. Covering your tracks should
 * require the database server itself, not a web request.
 *
 * WHY SECOND FACTORS ARE THEIR OWN TABLES, NOT COLUMNS ON users. Only a
 * handful of accounts will ever have them, and keeping the TOTP secret out of
 * the users table means the everywhere-used UserRepository can never
 * accidentally load it — the secret is only readable by the one repository
 * whose job that is (SecondFactorRepository).
 */
final class CreateAdminFoundation extends AbstractMigration
{
    public function change(): void
    {
        // One row per role a user holds. See the class note for why this is a
        // join table and why the unique index matters.
        $this->table('user_roles', ['signed' => false])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            // One of: owner, moderator, artist, coder. Checked against the
            // allow-list in src/Admin/Role.php before it is ever written.
            ->addColumn('role', 'string', ['limit' => 20, 'null' => false])
            // Who granted it. NULL means the CLI bootstrap script
            // (bin/grant-role.php) — the only way the FIRST owner can exist,
            // since granting through the panel needs an owner already.
            ->addColumn('granted_by_user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('granted_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            // A user losing their account takes their roles with them.
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            // The granter's row must exist, but deleting the granter must not
            // delete the grant — the role is real either way. SET_NULL keeps
            // the grant and honestly says "granted by an account now gone".
            ->addForeignKey('granted_by_user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            // One row per (user, role) — the idempotency guard (class note).
            ->addIndex(['user_id', 'role'], ['unique' => true])
            // "List everyone with this role" — the roles screen, and the
            // last-owner count, both filter by role first.
            ->addIndex(['role'])
            ->create();

        // Every admin action: who, what, when, before, after. Append-only —
        // see the class note.
        $this->table('admin_audit_log', ['signed' => false])
            // NULL actor means the CLI scripts, which run before any owner
            // exists (and are recorded with ip 'cli' so the two NULL-actor
            // cases can never be confused with a web request).
            ->addColumn('actor_user_id', 'integer', ['signed' => false, 'null' => true])
            // What happened, e.g. "role.granted". Allow-listed in PHP
            // (src/Admin/AuditAction.php) before it is ever written.
            ->addColumn('action', 'string', ['limit' => 50, 'null' => false])
            // What it happened TO — same polymorphic pointer the reports
            // table uses, because admin actions will touch many tables
            // (users now; species, items, shops from M2.3 on).
            ->addColumn('subject_type', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('subject_id', 'integer', ['signed' => false, 'null' => true])
            // The changed values as small JSON snapshots, so the log can say
            // not just "roles changed" but from-what to-what.
            ->addColumn('detail_before', 'text', ['null' => true])
            ->addColumn('detail_after', 'text', ['null' => true])
            // varchar(45) fits a full IPv6 address.
            ->addColumn('ip', 'string', ['limit' => 45, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            // NO foreign key on actor_user_id, deliberately: deleting an
            // account must never delete its audit trail (CASCADE would), and
            // the log must accept rows for accounts that later vanish.
            //
            // "Everything this person did" — the per-account history view.
            ->addIndex(['actor_user_id', 'created_at'])
            // "What happened recently" — the audit page reads newest-first.
            ->addIndex(['created_at'])
            ->create();

        // One TOTP secret per admin account (see class note for why this is
        // not a column on users). The secret is the shared key an
        // authenticator app uses to mint 6-digit codes.
        $this->table('admin_second_factors', ['signed' => false])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            // Base32 text of a 20-byte secret is 32 characters; 64 leaves
            // room if the secret length is ever increased.
            ->addColumn('totp_secret', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('enrolled_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            // One enrolment per account.
            ->addIndex(['user_id'], ['unique' => true])
            ->create();

        // Recovery codes: the way back in when the phone with the
        // authenticator app is lost. Stored HASHED, like passwords — a copy
        // of the database must not be a copy of the keys.
        $this->table('admin_recovery_codes', ['signed' => false])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('code_hash', 'string', ['limit' => 255, 'null' => false])
            // Stamped when the code is spent. A timestamp rather than a
            // boolean so the audit trail and this table tell the same story
            // about WHEN a code was used. Single-use is enforced by an
            // atomic "set used_at only where it is still NULL" update.
            ->addColumn('used_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            // "This account's unspent codes" — checked on every door entry
            // that types a recovery code.
            ->addIndex(['user_id', 'used_at'])
            ->create();
    }
}
