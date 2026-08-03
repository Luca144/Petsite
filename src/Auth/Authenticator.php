<?php

declare(strict_types=1);

namespace Felkyo\Auth;

use Felkyo\Users\User;
use Felkyo\Users\UserRepository;

/**
 * Checks login credentials.
 *
 * @package Felkyo\Auth
 *
 * WHAT THIS CLASS IS: the rule for "is this username-and-password pair correct?".
 * Given a username and a plain password, it returns the matching User, or null if
 * the login should be refused.
 *
 * SECURITY NOTE — we return the same "null" whether the username does not exist or
 * the password is wrong, and the controller shows one generic message for both.
 * This avoids telling an attacker which usernames are real ("user enumeration").
 */
final class Authenticator
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
    ) {
    }

    /**
     * Try to log in. Returns the User on success, or null if the username is
     * unknown or the password does not match.
     */
    public function attempt(string $username, string $password): ?User
    {
        $user = $this->users->findByUsername(trim($username));

        if ($user === null) {
            return null;
        }

        if (!$this->passwordHasher->verify($password, $user->passwordHash)) {
            return null;
        }

        return $user;
    }
}
