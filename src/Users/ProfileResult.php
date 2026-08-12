<?php

declare(strict_types=1);

namespace Felkyo\Users;

/**
 * The outcome of trying to change a profile: saved, or refused with a reason.
 *
 * @package Felkyo\Users
 *
 * Build one with saved() or rejected(), never with "new".
 *
 * Both outcomes carry a message, because golden rule 4 asks that every action get
 * a visible, plain-language answer. A silent save makes people press the button
 * again to check; a silent refusal makes them think the site is broken.
 */
final class ProfileResult
{
    private function __construct(
        private bool $successful,
        private string $message,
    ) {
    }

    public static function saved(string $message): self
    {
        return new self(true, $message);
    }

    public static function rejected(string $message): self
    {
        return new self(false, $message);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function message(): string
    {
        return $this->message;
    }
}
