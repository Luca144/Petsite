<?php

declare(strict_types=1);

namespace Felkyo\Auth;

use Felkyo\Users\User;

/**
 * The outcome of a registration attempt: either success (with the new user) or
 * failure (with a list of error messages to show).
 *
 * @package Felkyo\Auth
 *
 * WHY THIS EXISTS: registering can succeed or fail for several reasons, and the
 * controller needs to know which. Returning this small result object — rather
 * than throwing exceptions for ordinary "the form had mistakes" cases — keeps the
 * controller's logic a simple, readable "did it work? if not, show the errors".
 *
 * You never build one of these with "new"; use the success() or failed()
 * shortcuts below, which make the intent obvious at the call site.
 */
final class RegistrationResult
{
    /**
     * @param User|null $user   The created user on success; null on failure.
     * @param string[]  $errors The error messages on failure; empty on success.
     */
    private function __construct(
        private ?User $user,
        private array $errors,
    ) {
    }

    /** Build a "it worked" result carrying the newly created user. */
    public static function success(User $user): self
    {
        return new self($user, []);
    }

    /**
     * Build a "it failed" result carrying the reasons.
     *
     * @param string[] $errors
     */
    public static function failed(array $errors): self
    {
        return new self(null, $errors);
    }

    public function isSuccessful(): bool
    {
        return $this->user !== null;
    }

    /**
     * The created user. Only call this after checking isSuccessful() is true.
     */
    public function user(): User
    {
        if ($this->user === null) {
            throw new \LogicException('There is no user on a failed registration result.');
        }

        return $this->user;
    }

    /**
     * The error messages (empty on success).
     *
     * @return string[]
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
