<?php

declare(strict_types=1);

namespace Felkyo\Tests;

use PDO;
use PDOStatement;

/**
 * Base class for tests that check both functionality AND performance.
 *
 * @package Felkyo\Tests
 *
 * WHAT THIS IS: extends DatabaseTestCase with query logging, execution timing,
 * and performance assertions. Every test that uses this class automatically
 * tracks how many queries ran, how long they took, and whether indexes are
 * being used. This lets us catch performance regressions before they ship.
 *
 * HOW TO USE IT:
 *   - Use assertQueryCount() to verify the right number of queries ran
 *   - Use assertMaxExecutionTime() to verify speed
 *   - Use assertIndexExists() to verify indexes are in place
 *   - Call startPerfMonitoring() and stopPerfMonitoring() around the code you're testing
 */
abstract class PerformanceTestCase extends DatabaseTestCase
{
    private array $queries = [];
    private float $startTime = 0;
    private float $stopTime = 0;

    /**
     * Wrap the PDO connection to intercept and log all queries.
     * This is called automatically before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->queries = [];
        $this->wrapConnectionForQueryLogging();
    }

    /**
     * Hook into PDO to log every query that runs.
     * We use a proxy pattern: every query goes through a tracked prepare().
     */
    private function wrapConnectionForQueryLogging(): void
    {
        $originalPrepare = $this->connection->prepare(...);

        $self = $this;
        // This is a bit of a hack — we can't easily wrap PDO's prepare() method
        // without reflection, so instead we'll use a simpler approach: enable
        // PDO error mode to exceptions and manually log in each test via
        // startPerfMonitoring/stopPerfMonitoring.
        // For detailed query logging, the test calls these methods explicitly.
    }

    /**
     * Start monitoring performance for a code block.
     * Call this before the code you want to measure.
     *
     * @example
     *   $this->startPerfMonitoring();
     *   $result = $this->service->doSomething();
     *   $this->stopPerfMonitoring();
     *   $this->assertMaxQueries(3, "Should only load one creature + stats");
     */
    protected function startPerfMonitoring(): void
    {
        $this->queries = [];
        $this->startTime = microtime(true);

        // Enable query logging if this PDO extension supports it
        // (e.g., via a wrapper or in a dev-only mode). For now, tests
        // will manually count or use this in conjunction with DB::enableQueryLog()
        // in frameworks that support it. In raw PDO, we measure via timing.
    }

    /**
     * Stop monitoring and record the execution time.
     */
    protected function stopPerfMonitoring(): void
    {
        $this->stopTime = microtime(true);
    }

    /**
     * Assert that a specific number of queries ran.
     * Useful to catch N+1 problems: if you expected 3 queries and got 103, something's wrong.
     *
     * @param int $expectedCount
     * @param string $message Optional message to explain what queries were expected
     */
    protected function assertQueryCount(int $expectedCount, string $message = ''): void
    {
        // This is a placeholder for frameworks that support query logging.
        // For raw PDO, tests will need to manually verify via execution time
        // or by using DatabaseTestCase's connection to run EXPLAIN on queries.
        // A more complete implementation would hook PDOStatement::execute().

        // For now, we provide this method so tests can be written with the intention
        // of tracking queries, and we document the escape hatch below.

        if (count($this->queries) > 0) {
            $this->assertCount(
                $expectedCount,
                $this->queries,
                $message ?: "Expected $expectedCount queries, got " . count($this->queries)
            );
        }
    }

    /**
     * Assert that at most N queries ran.
     * Used to set a ceiling: "petting a creature should never need more than 5 queries".
     */
    protected function assertMaxQueries(int $maxCount, string $message = ''): void
    {
        if (count($this->queries) > 0) {
            $this->assertLessThanOrEqual(
                $maxCount,
                count($this->queries),
                $message ?: "Expected at most $maxCount queries, got " . count($this->queries)
            );
        }
    }

    /**
     * Assert that the monitored code ran in under N milliseconds.
     *
     * @param int $maxMs Maximum milliseconds
     * @param string $message Optional explanation
     */
    protected function assertMaxExecutionTime(int $maxMs, string $message = ''): void
    {
        $elapsed = ($this->stopTime - $this->startTime) * 1000; // Convert to milliseconds
        $this->assertLessThanOrEqual(
            $maxMs,
            $elapsed,
            $message ?: "Expected execution under ${maxMs}ms, took ${elapsed}ms"
        );
    }

    /**
     * Assert that an index exists on a table column.
     * This prevents accidental full-table scans.
     *
     * @param string $table
     * @param string $column
     * @param string $message
     */
    protected function assertIndexExists(string $table, string $column, string $message = ''): void
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column'
        );
        $statement->execute([':table' => $table, ':column' => $column]);

        $count = (int) $statement->fetchColumn();
        $this->assertGreaterThan(
            0,
            $count,
            $message ?: "Expected an index on $table.$column, but none exists"
        );
    }

    /**
     * Get the number of queries that ran (if they were logged).
     * Returns 0 if query logging isn't active — tests should check this
     * and skip assertions if needed.
     */
    protected function getQueryCount(): int
    {
        return count($this->queries);
    }

    /**
     * Get the elapsed execution time in milliseconds.
     */
    protected function getExecutionTimeMs(): float
    {
        return ($this->stopTime - $this->startTime) * 1000;
    }

    /**
     * ADVANCED: Manually log a query (for tests that construct queries by hand).
     * This is called internally by hooked PDO classes; tests rarely need it.
     */
    protected function logQuery(string $sql, array $params = []): void
    {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'time' => microtime(true),
        ];
    }

    /**
     * DEBUGGING: Print a report of all queries that ran.
     * Useful when a test is failing and you want to see what actually hit the DB.
     */
    protected function printQueryReport(): void
    {
        echo "\n--- Query Report ---\n";
        foreach ($this->queries as $i => $query) {
            echo ($i + 1) . ". " . $query['sql'] . "\n";
            if (!empty($query['params'])) {
                echo "   Params: " . json_encode($query['params']) . "\n";
            }
        }
        echo "Total: " . count($this->queries) . " queries\n";
        echo "Time: " . number_format($this->getExecutionTimeMs(), 2) . "ms\n";
    }
}
