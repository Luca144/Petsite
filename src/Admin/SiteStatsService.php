<?php

declare(strict_types=1);

namespace Felkyo\Admin;

use PDO;

/**
 * The stats page's numbers — the game's health, at a glance (M2.9).
 *
 * @package Felkyo\Admin
 *
 * AGGREGATE ONLY, BY DESIGN: counts and sums, never user rows. The build
 * plan's words — "this is about the game's health, not about surveilling
 * individuals" — are enforced right here by what the queries SELECT: the
 * only names that ever leave this class are CONTENT names (species,
 * areas), and the only number about people is how many there are. Do not
 * add a per-player query to this class; if a moderation question needs
 * one, it belongs on the account-history page, where looking at one
 * person is the declared job.
 *
 * Eight aggregate queries per view, on tables counted in thousands at
 * most — milliseconds with the existing indexes. If the site ever grows
 * enough for this to hurt, cache the result for an hour; the numbers are
 * a weather report, not a ticker.
 */
final class SiteStatsService
{
    private const WEEK_SECONDS = 7 * 86400;

    public function __construct(private PDO $connection)
    {
    }

    /**
     * Everything the screen shows, gathered fresh.
     *
     * @return array<string, mixed>
     */
    public function gather(): array
    {
        return [
            'players' => $this->players(),
            'creatures' => $this->creatures(),
            'bySpecies' => $this->bySpecies(),
            'care' => $this->care(),
            'economy' => $this->economy(),
            'areas' => $this->areas(),
        ];
    }

    /** @return array{total: int, newThisWeek: int, activeThisWeek: int} */
    private function players(): array
    {
        // One pass over users: the total, the week's newcomers, and who
        // has logged in this week — the three "is anybody out there?"
        // numbers, in one query instead of three.
        $row = $this->connection->query(
            'SELECT COUNT(*) AS total,
                    SUM(created_at >= NOW() - INTERVAL ' . self::WEEK_SECONDS . ' SECOND) AS new_week,
                    SUM(last_login_at >= NOW() - INTERVAL ' . self::WEEK_SECONDS . ' SECOND) AS active_week
               FROM users'
        )->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'newThisWeek' => (int) ($row['new_week'] ?? 0),
            'activeThisWeek' => (int) ($row['active_week'] ?? 0),
        ];
    }

    /** @return array{total: int, newThisWeek: int} */
    private function creatures(): array
    {
        $row = $this->connection->query(
            'SELECT COUNT(*) AS total,
                    SUM(created_at >= NOW() - INTERVAL ' . self::WEEK_SECONDS . ' SECOND) AS new_week
               FROM creatures'
        )->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'newThisWeek' => (int) ($row['new_week'] ?? 0),
        ];
    }

    /**
     * The adoption picture: how many of each species exist — including
     * species nobody has picked (LEFT JOIN), because "nobody picks the
     * Mossling" is exactly the signal this page exists to show.
     *
     * @return array<int, array{name: string, status: string, count: int}>
     */
    private function bySpecies(): array
    {
        $rows = $this->connection->query(
            'SELECT s.name, s.status, COUNT(c.id) AS creature_count
               FROM species s
               LEFT JOIN creatures c ON c.species_id = s.id
              GROUP BY s.id, s.name, s.status
              ORDER BY creature_count DESC, s.name'
        )->fetchAll() ?: [];

        return array_map(static fn (array $row): array => [
            'name' => (string) $row['name'],
            'status' => (string) $row['status'],
            'count' => (int) $row['creature_count'],
        ], $rows);
    }

    /** @return array{petsThisWeek: int, guestbookThisWeek: int} */
    private function care(): array
    {
        $pets = (int) $this->connection->query(
            'SELECT COUNT(*) FROM pettings
              WHERE created_at >= NOW() - INTERVAL ' . self::WEEK_SECONDS . ' SECOND'
        )->fetchColumn();

        $signatures = (int) $this->connection->query(
            'SELECT COUNT(*) FROM guestbook_entries
              WHERE created_at >= NOW() - INTERVAL ' . self::WEEK_SECONDS . ' SECOND'
        )->fetchColumn();

        return ['petsThisWeek' => $pets, 'guestbookThisWeek' => $signatures];
    }

    /** @return array{gemsInCirculation: int, itemsHeld: int} */
    private function economy(): array
    {
        // How much currency EXISTS — the number that says whether the
        // faucets (petting) and sinks (the shop) are anywhere near
        // balance. What the shop sold is not knowable yet: no order
        // ledger exists, and the screen says so honestly.
        $gems = (int) $this->connection->query(
            'SELECT COALESCE(SUM(currency_balance), 0) FROM users'
        )->fetchColumn();

        $items = (int) $this->connection->query(
            'SELECT COALESCE(SUM(quantity), 0) FROM inventory'
        )->fetchColumn();

        return ['gemsInCirculation' => $gems, 'itemsHeld' => $items];
    }

    /**
     * Which places get used. exploration_visits is WINDOW STATE, not an
     * event log (one row per visitor+area, reset as windows refresh) —
     * so these are honest approximations, labelled as such on the
     * screen: visitors whose current window began this week, and the
     * searches spent in those windows.
     *
     * @return array<int, array{slug: string, name: string, wanderers: int, searches: int}>
     */
    private function areas(): array
    {
        // LEFT JOIN from areas so every published place appears — an area
        // with no visitors at all is a finding, not a missing row. (Areas
        // moved into the database in M2.5; visits reference them by slug.)
        $rows = $this->connection->query(
            'SELECT a.slug, a.name,
                    COUNT(v.id) AS wanderers,
                    COALESCE(SUM(v.clicks_used), 0) AS searches
               FROM areas a
               LEFT JOIN exploration_visits v
                 ON v.area_slug = a.slug
                AND v.window_started_at >= NOW() - INTERVAL ' . self::WEEK_SECONDS . ' SECOND
              WHERE a.status = \'published\'
              GROUP BY a.id, a.slug, a.name
              ORDER BY searches DESC, a.name'
        )->fetchAll() ?: [];

        return array_map(static fn (array $row): array => [
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'wanderers' => (int) $row['wanderers'],
            'searches' => (int) $row['searches'],
        ], $rows);
    }
}
