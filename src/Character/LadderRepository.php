<?php

declare(strict_types=1);

namespace Felkyo\Character;

use PDO;

/**
 * The two word-ladders that make numbers friendlier (M3.2c):
 *
 *   'level_title'  level 7       → "Sprightly"
 *   'age'          41 days old   → "a few weeks old"
 *
 * @package Felkyo\Character
 *
 * Each ladder is a list of rungs: a threshold and a label. A value gets the
 * label of the highest rung it has reached. Both ladders are tiny (a dozen
 * rows), so each is read ONCE per request and kept here — a page with twenty
 * creature cards still costs one query per ladder, not one per card.
 *
 * Adding a rung is a row (the panel's /admin/character page does it); every
 * place that shows a level or an age picks it up at once.
 */
final class LadderRepository
{
    public const LEVEL_TITLE = 'level_title';
    public const AGE = 'age';

    /** @var array<string, array<int, string>> ladder => [threshold => label], ascending */
    private array $cache = [];

    public function __construct(private PDO $connection)
    {
    }

    /**
     * Every rung of one ladder, lowest threshold first.
     *
     * @return array<int, string> threshold => label
     */
    public function rungs(string $ladder): array
    {
        if (!isset($this->cache[$ladder])) {
            $statement = $this->connection->prepare(
                'SELECT threshold, label FROM ladder_rungs WHERE ladder = :ladder ORDER BY threshold'
            );
            $statement->execute([':ladder' => $ladder]);
            $this->cache[$ladder] = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $this->cache[$ladder][(int) $row['threshold']] = (string) $row['label'];
            }
        }

        return $this->cache[$ladder];
    }

    /** The label for a value, or null if the ladder has no rung that low. */
    public function labelFor(string $ladder, int $value): ?string
    {
        $label = null;
        foreach ($this->rungs($ladder) as $threshold => $rungLabel) {
            if ($value >= $threshold) {
                $label = $rungLabel;
            }
        }

        return $label;
    }

    /** "Sprightly" for a level, or null. */
    public function titleForLevel(int $level): ?string
    {
        return $this->labelFor(self::LEVEL_TITLE, $level);
    }

    /** "a few weeks old" for a creation date (as stored), or null. */
    public function ageFor(?string $createdAt): ?string
    {
        if ($createdAt === null) {
            return null;
        }
        $days = intdiv(max(0, time() - (int) strtotime($createdAt)), 86400);

        return $this->labelFor(self::AGE, $days);
    }

    /**
     * Replace a whole ladder in one go (the panel edits a ladder as a block
     * of lines). Inside a transaction, so a reader never sees it half-written.
     *
     * @param array<int, string> $rungs threshold => label
     */
    public function replace(string $ladder, array $rungs): void
    {
        $this->connection->beginTransaction();
        try {
            $this->connection->prepare('DELETE FROM ladder_rungs WHERE ladder = :ladder')
                ->execute([':ladder' => $ladder]);
            $insert = $this->connection->prepare(
                'INSERT INTO ladder_rungs (ladder, threshold, label) VALUES (:ladder, :threshold, :label)'
            );
            foreach ($rungs as $threshold => $label) {
                $insert->execute([':ladder' => $ladder, ':threshold' => $threshold, ':label' => $label]);
            }
            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }
        unset($this->cache[$ladder]);
    }
}
