<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Creates the "shops" table — the shops that sell items for currency.
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS TABLE EXISTS: the game has exactly ONE shop for now (build plan B.9).
 * We still give shops their own table (with a single seeded row) so that adding a
 * second shop later is a data change — insert a row and link items to it — rather
 * than a code change. This is the honest, data-driven foundation the plan asks
 * for, and it is just one small table.
 */
final class CreateShopsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('shops', ['signed' => false]);

        $table
            // Short machine-friendly identifier, e.g. "general-store".
            ->addColumn('slug', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 60, 'null' => false])
            // Genuinely optional, so NULL is allowed.
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => '', 'null' => false])
            ->addIndex(['slug'], ['unique' => true])
            ->create();
    }
}
