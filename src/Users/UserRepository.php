<?php

declare(strict_types=1);

namespace Felkyo\Users;

use PDO;

/**
 * Reads and writes user accounts in the database.
 *
 * @package Felkyo\Users
 *
 * WHAT THIS CLASS IS: the ONLY place that runs SQL for the users table. This is
 * the "repository" layer (CLAUDE.md section 5): controllers and services ask it
 * for users, and it owns the queries. Keeping all user SQL here means the rest of
 * the app never has to think about the database, and every query uses prepared
 * statements, which protect against SQL injection.
 *
 * We always list the exact columns we need — never SELECT * — so the data each
 * query returns is explicit.
 */
final class UserRepository
{
    // The columns that make up a full user record. Listed once, reused below.
    private const COLUMNS = 'id, username, email, password_hash, currency_balance, created_at, last_login_at';

    public function __construct(private PDO $connection)
    {
    }

    /**
     * Find a user by their username, or return null if there is no such user.
     * Used both when logging in and when checking a new username is free.
     * (Our collation is case-insensitive, so "Bob" and "bob" are the same name.)
     */
    public function findByUsername(string $username): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE username = :username LIMIT 1'
        );
        $statement->execute([':username' => $username]);

        return $this->rowToUserOrNull($statement->fetch());
    }

    /**
     * Find a user by their email, or return null. Used to check that a new
     * registration is not reusing an existing account's email.
     */
    public function findByEmail(string $email): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute([':email' => $email]);

        return $this->rowToUserOrNull($statement->fetch());
    }

    /**
     * Find a user by their id, or return null. Used to load the logged-in user
     * from the id we keep in their session.
     */
    public function findById(int $id): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);

        return $this->rowToUserOrNull($statement->fetch());
    }

    /**
     * Create a new user and return the saved record (with its new id).
     *
     * We only insert the three fields a new account needs; currency_balance and
     * created_at use their database defaults (0 and "now"). The password must
     * already be hashed by the caller — this class never sees a plain password.
     */
    public function create(string $username, string $email, string $passwordHash): User
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users (username, email, password_hash)
             VALUES (:username, :email, :password_hash)'
        );
        $statement->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash,
        ]);

        // Load the row we just created so callers get the full, saved user back.
        $newUser = $this->findById((int) $this->connection->lastInsertId());

        // This should never happen right after a successful insert, but we assert
        // it clearly rather than return a "maybe null" from a create() method.
        if ($newUser === null) {
            throw new \RuntimeException('Failed to load the user that was just created.');
        }

        return $newUser;
    }

    /**
     * Record that a user has just logged in, by stamping last_login_at with the
     * database's current time.
     */
    public function updateLastLogin(int $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id'
        );
        $statement->execute([':id' => $userId]);
    }

    /**
     * Has this user adopted a creature within the last $withinSeconds seconds?
     * This backs the "once per day" adoption limit. The number of seconds is cast
     * to an integer and placed straight into the SQL (safe: it is our own value,
     * and INTERVAL will not take a bound parameter).
     */
    public function hasAdoptedWithin(int $userId, int $withinSeconds): bool
    {
        $withinSeconds = max(0, $withinSeconds);

        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM users
              WHERE id = :id
                AND last_adopted_at IS NOT NULL
                AND last_adopted_at >= NOW() - INTERVAL ' . $withinSeconds . ' SECOND'
        );
        $statement->execute([':id' => $userId]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * Record that a user has just adopted a creature, by stamping last_adopted_at
     * with the database's current time.
     */
    public function markAdopted(int $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET last_adopted_at = NOW() WHERE id = :id'
        );
        $statement->execute([':id' => $userId]);
    }

    /**
     * Turn a fetched row into a User, or null if the query found nothing. PDO's
     * fetch() returns false when there is no row, which we treat as "not found".
     */
    private function rowToUserOrNull(mixed $row): ?User
    {
        if (!is_array($row)) {
            return null;
        }

        return User::fromRow($row);
    }
}
