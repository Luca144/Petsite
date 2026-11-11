<?php

declare(strict_types=1);

namespace Felkyo\Feedback;

use PDO;

/**
 * Stores and lists bug reports (see the bug_reports migration for why they
 * are kept apart from moderation reports).
 *
 * @package Felkyo\Feedback
 *
 * Rows come back as plain arrays: a bug report is a small read-only record
 * shown on one panel screen, and a class for it would add nothing a reader
 * needs.
 */
final class BugReportRepository
{
    public const STATUS_OPEN = 'open';
    public const STATUS_FIXED = 'fixed';
    public const STATUS_CLOSED = 'closed';

    /** The statuses a report can be moved to from the panel. */
    public const STATUSES = [self::STATUS_OPEN, self::STATUS_FIXED, self::STATUS_CLOSED];

    public function __construct(private PDO $connection)
    {
    }

    /** @param array{reporter: int, path: string, browser: string, width: ?int, description: string} $report */
    public function add(array $report): void
    {
        $this->connection->prepare(
            'INSERT INTO bug_reports (reporter_user_id, page_path, browser, screen_width, description)
             VALUES (:reporter, :path, :browser, :width, :description)'
        )->execute([
            ':reporter' => $report['reporter'],
            ':path' => $report['path'],
            ':browser' => $report['browser'],
            ':width' => $report['width'],
            ':description' => $report['description'],
        ]);
    }

    /**
     * Reports with this status, newest first, with the sender's name.
     * Served by the (status, created_at) index.
     *
     * @return array<int, array{id: int, reporter: string, page_path: string, browser: string,
     *               screen_width: ?int, description: string, status: string, created_at: string}>
     */
    public function withStatus(string $status, int $limit): array
    {
        $statement = $this->connection->prepare(
            'SELECT b.id, u.username, b.page_path, b.browser, b.screen_width, b.description, b.status, b.created_at
               FROM bug_reports b JOIN users u ON u.id = b.reporter_user_id
              WHERE b.status = :status
              ORDER BY b.created_at DESC, b.id DESC
              LIMIT ' . max(1, $limit)
        );
        $statement->execute([':status' => $status]);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'reporter' => (string) $row['username'],
            'page_path' => (string) $row['page_path'],
            'browser' => (string) $row['browser'],
            'screen_width' => $row['screen_width'] === null ? null : (int) $row['screen_width'],
            'description' => (string) $row['description'],
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** How many reports have each status, for the panel's tabs. @return array<string, int> */
    public function countsByStatus(): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        $statement = $this->connection->query('SELECT status, COUNT(*) AS n FROM bug_reports GROUP BY status');
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** Move a report to a status and remember who did it. False if there is no such report. */
    public function setStatus(int $id, string $status, int $handledBy): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE bug_reports SET status = :status, handled_by_user_id = :by, handled_at = NOW() WHERE id = :id'
        );
        $statement->execute([':status' => $status, ':by' => $handledBy, ':id' => $id]);

        return $statement->rowCount() > 0;
    }
}
