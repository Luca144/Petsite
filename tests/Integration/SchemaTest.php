<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the migrations build the database we designed (increment 0.2).
 *
 * @package Felkyo\Tests\Integration
 *
 * WHAT THIS IS: an integration test — it runs against the real (test) database,
 * not against fake objects. Its job is to prove that after the migrations run,
 * the felkyo_test database actually contains the tables and the key rules
 * (required columns, foreign keys) we intended. If someone later changes a
 * migration and breaks the shape, these tests catch it.
 *
 * The test bootstrap has already migrated felkyo_test before this test runs.
 */
final class SchemaTest extends TestCase
{
    private PDO $connection;

    /**
     * Open a connection to the test database before each test. The test
     * bootstrap has forced the database name to felkyo_test.
     */
    protected function setUp(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $this->connection = Database::connect($config['database']);
    }

    /**
     * Every table in our approved v2 schema must exist — and nothing should be
     * missing. We list them explicitly so a forgotten migration is caught.
     */
    public function testAllExpectedTablesExist(): void
    {
        $expectedTables = [
            'users',
            'species',
            'creatures',
            'pettings',
            'exploration_visits',
            'items',
            'inventory',
            'shops',
            'shop_items',
            'rate_limit_hits',
            'guestbook_entries',
            'reports',
            // The admin foundation (M2.1): roles, the audit log, and second
            // factors for staff accounts.
            'user_roles',
            'admin_audit_log',
            'admin_second_factors',
            'admin_recovery_codes',
        ];

        foreach ($expectedTables as $tableName) {
            $this->assertTrue(
                $this->tableExists($tableName),
                "Expected table '{$tableName}' to exist in felkyo_test, but it does not."
            );
        }
    }

    /**
     * A creature must always have an owner and a species, so those columns must
     * be NOT NULL. This proves the required-field rules made it into the schema.
     */
    public function testRequiredCreatureColumnsAreNotNullable(): void
    {
        $this->assertFalse($this->columnIsNullable('creatures', 'owner_id'));
        $this->assertFalse($this->columnIsNullable('creatures', 'species_id'));
        $this->assertFalse($this->columnIsNullable('creatures', 'name'));
    }

    /**
     * A genuinely optional field (a creature's bio) must remain nullable, so an
     * owner who has not written one yet is stored as NULL rather than forced text.
     */
    public function testOptionalCreatureBioIsNullable(): void
    {
        $this->assertTrue($this->columnIsNullable('creatures', 'bio'));
    }

    /**
     * The creature -> user link must be a real foreign key, so the database
     * itself refuses to store a creature owned by a user who does not exist.
     */
    public function testCreatureOwnerForeignKeyExists(): void
    {
        $this->assertTrue(
            $this->foreignKeyExists('creatures', 'owner_id', 'users', 'id'),
            'Expected a foreign key from creatures.owner_id to users.id.'
        );
    }

    /**
     * The unique (user_id, role) index is what makes granting a role
     * idempotent — the same grant arriving twice at once is a no-op, never a
     * duplicate row that would make the last-owner count lie (M2.1).
     */
    public function testUserRolesAreUniquePerUserAndRole(): void
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'user_roles\'
               AND NON_UNIQUE = 0
               AND COLUMN_NAME IN (\'user_id\', \'role\')'
        );
        $statement->execute();

        // Both columns of the composite unique index must be present in a
        // unique index (2 rows: one per column of the composite key).
        $this->assertGreaterThanOrEqual(2, (int) $statement->fetchColumn(),
            'Expected a unique index across user_roles (user_id, role).');
    }

    /**
     * The audit log must have NO foreign key on actor_user_id: a CASCADE
     * would let deleting an account delete its audit trail, and the trail
     * must outlive whoever it describes (M2.1).
     */
    public function testTheAuditLogSurvivesItsActors(): void
    {
        $this->assertFalse(
            $this->foreignKeyExists('admin_audit_log', 'actor_user_id', 'users', 'id'),
            'admin_audit_log.actor_user_id must not be a foreign key — the log outlives its actors.'
        );
    }

    // --- Small helpers that ask the database about its own structure. ---
    // These use the standard "information_schema", a built-in database that
    // describes all the other databases. DATABASE() is the one we are connected
    // to (felkyo_test), so every check is scoped to the test database.

    private function tableExists(string $tableName): bool
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $statement->execute([':table' => $tableName]);

        return (int) $statement->fetchColumn() === 1;
    }

    private function columnIsNullable(string $tableName, string $columnName): bool
    {
        $statement = $this->connection->prepare(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $statement->execute([':table' => $tableName, ':column' => $columnName]);

        // information_schema reports 'YES' when a column allows NULL.
        return $statement->fetchColumn() === 'YES';
    }

    private function foreignKeyExists(
        string $tableName,
        string $columnName,
        string $referencedTable,
        string $referencedColumn
    ): bool {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table AND COLUMN_NAME = :column
               AND REFERENCED_TABLE_NAME = :refTable
               AND REFERENCED_COLUMN_NAME = :refColumn'
        );
        $statement->execute([
            ':table' => $tableName,
            ':column' => $columnName,
            ':refTable' => $referencedTable,
            ':refColumn' => $referencedColumn,
        ]);

        return (int) $statement->fetchColumn() >= 1;
    }
}
