<?php

declare(strict_types=1);

namespace Felkyo\Auth;

use Felkyo\Safety\ImpersonationGuard;
use Felkyo\Safety\TextGuard;
use Felkyo\Users\UserRepository;
use Felkyo\Users\UserValidator;

/**
 * The business rules for creating a new account.
 *
 * @package Felkyo\Auth
 *
 * WHAT THIS CLASS IS: the "service" that ties the registration steps together:
 * validate the input, make sure the username is not already taken or pretending
 * to be somebody, hash the password, and save the user. Controllers call this one
 * method; they do not know or care about the individual steps.
 *
 * WHY IT IS SEPARATE FROM THE CONTROLLER: the controller's job is web plumbing
 * (read the form, return a page). The rules of "what makes a valid new account"
 * live here, where they can be tested directly without a web request.
 *
 * WHAT M1.4 ADDED, AND WHY IT IS HERE RATHER THAN IN THE VALIDATOR: a username is
 * one of only three places on this site where a player chooses their own words,
 * so it goes through the same TextGuard as the others. Two of the checks need the
 * database — "is somebody already called something that reads like this?" — and
 * UserValidator deliberately has no database access, so those live here.
 *
 * A username is the strictest of the three fields on purpose. It follows a player
 * everywhere, appears beside everything they do, and cannot be hidden pending
 * review the way a bio can, because hiding it would break every page it is on.
 */
final class RegistrationService
{
    public function __construct(
        private UserRepository $users,
        private UserValidator $validator,
        private PasswordHasher $passwordHasher,
        private TextGuard $textGuard,
        private ImpersonationGuard $impersonation,
        private int $usernameMaxLength,
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

        // Then the safety rules for the name itself: hidden characters, contact
        // details, blocked words, and claiming to be staff. Only run if the basic
        // format passed, so somebody who typed nothing gets one clear message
        // rather than a list of everything that is wrong with nothing.
        if ($errors === []) {
            $guarded = $this->textGuard->checkName($username, $this->usernameMaxLength);

            if (!$guarded->isAccepted()) {
                $errors[] = $guarded->message();
            }
        }

        // Then, the uniqueness rules. These need the database, so they are checked
        // here rather than in the validator. The database also has unique indexes
        // as a final guarantee, but checking here lets us give a friendly message.
        if ($this->users->findByUsername($username) !== null) {
            $errors[] = 'That username is already taken.';
        }
        if ($this->users->findByEmail($email) !== null) {
            $errors[] = 'An account with that email already exists.';
        }

        $skeleton = $this->impersonation->skeletonOf($username);

        // And finally: is somebody already using a name that READS like this one?
        // "mira" and "m1ra" are different strings and, to anybody glancing at a
        // page, the same person. Being mistaken for a familiar name is worth doing
        // to a child, so the second one is refused.
        if ($this->users->findByUsernameSkeleton($skeleton) !== null) {
            $errors[] = 'That name is very close to one already in use. Please choose another.';
        }

        if ($errors !== []) {
            return RegistrationResult::failed($errors);
        }

        // All good: hash the password and save the new user, with the comparison
        // form of the name stored beside it for the next person who registers.
        $hashedPassword = $this->passwordHasher->hash($password);
        $newUser = $this->users->create($username, $email, $hashedPassword, $skeleton);

        return RegistrationResult::success($newUser);
    }
}
