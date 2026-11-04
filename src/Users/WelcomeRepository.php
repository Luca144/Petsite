<?php

declare(strict_types=1);

namespace Felkyo\Users;

use PDO;

/**
 * The first-run welcome (M7): Ruinily's steps, and who has dismissed them.
 *
 * @package Felkyo\Users
 *
 * Steps are plain arrays (position, words, link_href, link_label). A step's
 * link may only point INSIDE the site (it must start with a single "/");
 * that rule is enforced when the panel saves them, in replaceSteps().
 */
final class WelcomeRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return array<int, array{words: string, link_href: ?string, link_label: ?string}> in order */
    public function steps(): array
    {
        $statement = $this->connection->query(
            'SELECT words, link_href, link_label FROM welcome_steps ORDER BY position, id'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Has this player said "don't show me this again"? */
    public function isDismissed(int $userId): bool
    {
        $statement = $this->connection->prepare('SELECT welcome_dismissed_at FROM users WHERE id = :id');
        $statement->execute([':id' => $userId]);

        return $statement->fetchColumn() !== null;
    }

    public function dismiss(int $userId): void
    {
        $this->connection->prepare('UPDATE users SET welcome_dismissed_at = NOW() WHERE id = :id AND welcome_dismissed_at IS NULL')
            ->execute([':id' => $userId]);
    }

    /**
     * Replace every step (the panel edits them as one list), in one
     * transaction so nobody ever sees half a welcome.
     *
     * @param array<int, array{words: string, link_href: ?string, link_label: ?string}> $steps
     */
    public function replaceSteps(array $steps): void
    {
        $this->connection->beginTransaction();
        try {
            $this->connection->exec('DELETE FROM welcome_steps');
            $insert = $this->connection->prepare(
                'INSERT INTO welcome_steps (position, words, link_href, link_label) VALUES (:p, :w, :h, :l)'
            );
            foreach (array_values($steps) as $index => $step) {
                $insert->execute([':p' => $index + 1, ':w' => $step['words'], ':h' => $step['link_href'], ':l' => $step['link_label']]);
            }
            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }
    }
}
