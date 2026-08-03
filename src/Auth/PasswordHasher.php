<?php

declare(strict_types=1);

namespace Felkyo\Auth;

/**
 * Hashes and checks passwords.
 *
 * @package Felkyo\Auth
 *
 * WHAT THIS CLASS IS: the single place that turns a plain password into a stored
 * hash, and checks a login attempt against that hash. We never store or compare
 * plain passwords — only hashes (CLAUDE.md section 6).
 *
 * WHY A HASH: a hash is a one-way scramble. If our database were ever seen, the
 * stored hashes could not be turned back into the real passwords. We use PHP's
 * password_hash() with its default algorithm (currently bcrypt), which also adds
 * a random "salt" so two identical passwords produce different hashes.
 *
 * Wrapping this in one tiny class means if PHP's default algorithm improves in
 * future, there is exactly one place to adjust.
 */
final class PasswordHasher
{
    /**
     * Turn a plain password into a hash safe to store in the database.
     */
    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_DEFAULT);
    }

    /**
     * Check a plain password (from a login attempt) against a stored hash.
     * Returns true only if they match. password_verify() does this safely,
     * including a constant-time comparison so timing cannot leak the answer.
     */
    public function verify(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }
}
