<?php

declare(strict_types=1);

namespace Felkyo\Users;

/**
 * Checks that registration input is acceptable before we try to save it.
 *
 * @package Felkyo\Users
 *
 * WHAT THIS CLASS IS: the one place that holds the rules for a valid username,
 * email and password. Validation lives in a dedicated class (CLAUDE.md section 6)
 * rather than being scattered through controllers, so the rules are easy to find,
 * read, and test.
 *
 * It returns a list of plain-English error messages. An empty list means the
 * input is valid. Messages are written to be shown straight to the person.
 *
 * The exact limits (lengths, etc.) are passed in from config so they can be
 * retuned in one place without touching this logic.
 */
final class UserValidator
{
    /**
     * @param array $securityConfig The "security" section of the config array.
     */
    public function __construct(private array $securityConfig)
    {
    }

    /**
     * Validate a registration attempt. Returns an array of error messages
     * (empty if everything is fine).
     *
     * @return string[]
     */
    public function validateRegistration(string $username, string $email, string $password): array
    {
        $errors = [];

        foreach ($this->usernameErrors($username) as $error) {
            $errors[] = $error;
        }
        foreach ($this->emailErrors($email) as $error) {
            $errors[] = $error;
        }
        foreach ($this->passwordErrors($password) as $error) {
            $errors[] = $error;
        }

        return $errors;
    }

    /**
     * Username rules: within the configured length, and only letters, numbers,
     * underscores and hyphens (so names stay simple and URL-friendly).
     *
     * @return string[]
     */
    private function usernameErrors(string $username): array
    {
        $min = $this->securityConfig['username_min_length'];
        $max = $this->securityConfig['username_max_length'];
        $length = mb_strlen($username);

        if ($length < $min || $length > $max) {
            return ["Username must be between {$min} and {$max} characters long."];
        }

        // ^...$ means the WHOLE username must be made only of these characters.
        if (preg_match('/^[A-Za-z0-9_-]+$/', $username) !== 1) {
            return ['Username can only contain letters, numbers, underscores and hyphens.'];
        }

        return [];
    }

    /**
     * Email rules: a valid email shape (checked by PHP's built-in validator) and
     * within the configured maximum length.
     *
     * @return string[]
     */
    private function emailErrors(string $email): array
    {
        if ($email === '') {
            return ['Please enter your email address.'];
        }

        if (mb_strlen($email) > $this->securityConfig['email_max_length']) {
            return ['That email address is too long.'];
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['Please enter a valid email address.'];
        }

        return [];
    }

    /**
     * Password rules: length only, following modern guidance that a decent length
     * matters more than forced symbols. The maximum matches what the hashing
     * algorithm actually uses (see the config comments).
     *
     * @return string[]
     */
    private function passwordErrors(string $password): array
    {
        $min = $this->securityConfig['password_min_length'];
        $max = $this->securityConfig['password_max_length'];
        $length = mb_strlen($password);

        if ($length < $min) {
            return ["Password must be at least {$min} characters long."];
        }

        if ($length > $max) {
            return ["Password must be no more than {$max} characters long."];
        }

        return [];
    }
}
