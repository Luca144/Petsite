<?php

declare(strict_types=1);

namespace Felkyo\Auth;

use Felkyo\Users\UserRepository;
use Felkyo\Users\UserValidator;

/**
 * The business rules for creating a new account.
 *
 * @package Felkyo\Auth
 *
 * WHAT THIS CLASS IS: the "service" that ties the registration steps together:
 * validate the input, make sure the username and email are not already taken,
 * hash the password, and save the user. Controllers call this one method; they
 * do not know or care about the individual steps.
 *
 * WHY IT IS SEPARATE FROM THE CONTROLLER: the controller's job is web plumbing
 * (read the form, return a page). The rules of "what makes a valid new account"
 * live here, where they can be tested directly without a web request.
 *
 * NOTE FOR PHASE D: registration flows through this single service, so the later
 * "registration closed" switch can be added in one place without reshaping this.
 */
final class RegistrationService
{
    public function __construct(
        private UserRepository $users,
        private UserValidator $validator,
        private PasswordHasher $passwordHasher,
    ) {
    }

    /**
     * Attempt to register a new account. Returns a RegistrationResult describing
     * success (with the new user) or failure (with messages to show).
     */
    public function register(string $username, string $email, string $password): RegistrationResult
    {
        // Trim surrounding spaces from the name and email (people often paste
        // them with a stray space). We do NOT trim the password — a space can be
        // a real, intended character in a password.
        $username = trim($username);
        $email = trim($email);

        // First, the format rules (length, characters, valid email shape).
        $errors = $this->validator->validateRegistration($username, $email, $password);

        // Then, the uniqueness rules. These need the database, so they are checked
        // here rather than in the validator. The database also has unique indexes
        // as a final guarantee, but checking here lets us give a friendly message.
        if ($this->users->findByUsername($username) !== null) {
            $errors[] = 'That username is already taken.';
        }
        if ($this->users->findByEmail($email) !== null) {
            $errors[] = 'An account with that email already exists.';
        }

        if ($errors !== []) {
            return RegistrationResult::failed($errors);
        }

        // All good: hash the password and save the new user.
        $hashedPassword = $this->passwordHasher->hash($password);
        $newUser = $this->users->create($username, $email, $hashedPassword);

        return RegistrationResult::success($newUser);
    }
}
