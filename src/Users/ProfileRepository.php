<?php

declare(strict_types=1);

namespace Felkyo\Users;

use PDO;

/**
 * Reads and writes the parts of a user that make up their profile.
 *
 * @package Felkyo\Users
 *
 * WHAT THIS IS: the only place that runs SQL for profiles. It sits beside
 * UserRepository rather than inside it because that class is about accounts —
 * logging in, balances, adoption cooldowns — and this one is about the page other
 * people visit. Keeping them apart means a change to how profiles work cannot
 * accidentally disturb how logging in works.
 *
 * WHAT IT NEVER RETURNS: the email address or the password hash. A profile is a
 * public page, and the safest way to make sure a private column never leaks onto
 * it is for the queries that feed it to not ask for that column at all. Every
 * SELECT here lists its columns by name (CLAUDE.md section 5), so adding a column
 * to the users table cannot quietly add it to everybody's public page.
 *
 * THE OWNERSHIP RULE (docs/owned-things.md rule 2) applies to every write: the
 * acting player's id goes in the WHERE clause, never into an "if" above the query.
 */
final class ProfileRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * Find a profile by username, or null if there is no such player.
     *
     * Deliberately does NOT select email or password_hash — see the class note.
     */
    public function findByUsername(string $username): ?Profile
    {
        $statement = $this->connection->prepare(
            'SELECT id, username, avatar_key, about, about_hidden_at, is_findable, created_at
               FROM users
              WHERE username = :username
              LIMIT 1'
        );
        $statement->execute([':username' => $username]);

        $row = $statement->fetch();

        return is_array($row) ? Profile::fromRow($row) : null;
    }

    /**
     * Find a profile by user id — used when showing somebody their own page.
     */
    public function findById(int $userId): ?Profile
    {
        $statement = $this->connection->prepare(
            'SELECT id, username, avatar_key, about, about_hidden_at, is_findable, created_at
               FROM users
              WHERE id = :id
              LIMIT 1'
        );
        $statement->execute([':id' => $userId]);

        $row = $statement->fetch();

        return is_array($row) ? Profile::fromRow($row) : null;
    }

    /**
     * Save the avatar and about text for one player.
     *
     * The id in the WHERE clause is the acting player's, passed down from the
     * session — so a request that names somebody else changes nothing at all,
     * whatever checks above it were forgotten.
     */
    public function saveAppearance(int $userId, string $avatarKey, ?string $about, bool $isFindable): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET avatar_key = :avatar_key, about = :about, is_findable = :is_findable WHERE id = :id'
        );
        $statement->execute([
            ':avatar_key' => $avatarKey,
            ':about' => $about,
            ':is_findable' => $isFindable ? 1 : 0,
            ':id' => $userId,
        ]);
    }

    /**
     * Replace which creatures a player features, and in what order.
     *
     * WHY IT CLEARS EVERYTHING FIRST: the form sends the full list of what should
     * be featured, so the honest way to apply it is "forget the old answer, write
     * the new one". Trying to work out the difference would be more code and would
     * leave a creature featured if it were dropped from the list in an unusual way.
     *
     * WHY THE SECOND STATEMENT NAMES THE OWNER TWICE OVER: the ids come from a
     * form, so they are whatever somebody chose to send. The owner_id condition
     * means an id belonging to another player matches no row and is silently
     * ignored — you cannot put somebody else's creature on your page by typing its
     * number. The service above also filters the list, but this is the layer that
     * cannot be bypassed.
     *
     * @param array<int, int> $creatureIds In the order they should appear.
     */
    public function replaceFeatured(int $userId, array $creatureIds): void
    {
        $clear = $this->connection->prepare(
            'UPDATE creatures SET featured_order = NULL WHERE owner_id = :owner_id'
        );
        $clear->execute([':owner_id' => $userId]);

        $feature = $this->connection->prepare(
            'UPDATE creatures SET featured_order = :position
              WHERE id = :id AND owner_id = :owner_id'
        );

        $position = 1;
        foreach ($creatureIds as $creatureId) {
            $feature->execute([
                ':position' => $position,
                ':id' => $creatureId,
                ':owner_id' => $userId,
            ]);
            $position++;
        }
    }

    /**
     * Find players whose name STARTS WITH what was typed.
     *
     * @return array<int, Profile>
     *
     * FOUR THINGS ABOUT THIS QUERY ARE DELIBERATE, and each closes a different way
     * of turning a search box into a list of everybody on the site.
     *
     * 1. PREFIX MATCHING, NOT "CONTAINS". "mi%" finds Mira. "%mi%" would also find
     *    Jasmine, Camilla and Tomiko — and, more to the point, searching for a
     *    single common letter would return a large slice of the playerbase. A
     *    prefix search only answers a question somebody already half knows the
     *    answer to.
     *
     * 2. A MINIMUM LENGTH, enforced by the caller. One letter is not a search, it
     *    is a way of listing everybody whose name begins with "a".
     *
     * 3. ORDERED BY NAME, NEVER BY WHEN THEY JOINED. There is no "newest members"
     *    anywhere on this site, and this is one of the places that could quietly
     *    become one. New accounts are the least familiar with how things work and
     *    the most likely to be young, so a list of recent arrivals is precisely
     *    the tool somebody would want for finding them. It is not offered.
     *
     * 4. UNFINDABLE PLAYERS ARE EXCLUDED IN THE QUERY, not filtered out
     *    afterwards. Filtering afterwards means the row was fetched, which means
     *    one careless change to a template could show it.
     *
     * The LIMIT is placed straight into the SQL rather than bound, because MySQL
     * will not accept a parameter there under real prepared statements. It is our
     * own integer, never anything a visitor sent.
     */
    public function searchByNamePrefix(string $prefix, int $limit): array
    {
        $limit = max(1, $limit);

        // Escape the wildcards a player could type, so a search for "100%" looks
        // for the characters "100%" rather than for "anything at all".
        $escaped = addcslashes($prefix, chr(92) . '%_');

        $statement = $this->connection->prepare(
            'SELECT id, username, avatar_key, about, about_hidden_at, is_findable, created_at
               FROM users
              WHERE is_findable = 1 AND username LIKE :prefix
              ORDER BY username
              LIMIT ' . $limit
        );
        $statement->execute([':prefix' => $escaped . '%']);

        return array_map(
            static fn (array $row): Profile => Profile::fromRow($row),
            $statement->fetchAll()
        );
    }
}
