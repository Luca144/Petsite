<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the "rate_limit_hits" table — records attempts, so we can throttle them.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS TABLE EXISTS: CLAUDE.md (section 6) requires rate limits on every
 * state-changing public action (login, registration, commenting, petting, and so
 * on) to blunt spam and brute-force attempts. The rate limiter records one row
 * here each time such an action is attempted; it then counts recent rows to
 * decide whether the next attempt is allowed.
 *
 * It is created now, with the rest of the schema, so the limiter has a home and
 * the database design is complete — even though the limiter service that uses it
 * arrives with the first protected endpoint (increment A.1).
 *
 * - action_key : which action, e.g. "login" or "register".
 * - identifier : who is acting, e.g. an IP address or a user id.
 */
final class CreateRateLimitHitsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('rate_limit_hits', ['signed' => false]);

        $table
            ->addColumn('action_key', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('identifier', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            // The limiter asks "how many hits for this action by this identifier
            // since a cutoff time?" — this combined index answers that quickly.
            ->addIndex(['action_key', 'identifier', 'created_at'])
            ->create();
    }
}
