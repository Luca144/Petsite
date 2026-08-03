<?php

declare(strict_types=1);

namespace Felkyo\Users;

/**
 * One user account, as loaded from the database.
 *
 * @package Felkyo\Users
 *
 * WHAT THIS CLASS IS: a plain, read-only value object describing a user. Using a
 * typed object (with $user->username) rather than a loose array (with
 * $user['username']) means typos are caught early and it is obvious what fields a
 * user has. The UserRepository builds these from database rows.
 *
 * The password hash lives here because the Authenticator needs it to check a
 * login. It is a hash, never a plain password, and it never leaves the server.
 */
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly int $currencyBalance,
        public readonly ?string $createdAt,
        public readonly ?string $lastLoginAt,
    ) {
    }

    /**
     * Build a User from a database row (an associative array of column => value).
     * Keeping this mapping in one place means the repository's queries and this
     * object stay in step.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['username'],
            $row['email'],
            $row['password_hash'],
            (int) $row['currency_balance'],
            $row['created_at'] ?? null,
            $row['last_login_at'] ?? null,
        );
    }
}
