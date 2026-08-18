<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * The outcome of offering a creature something to eat.
 *
 * @package Felkyo\Creatures
 *
 * Built with success() or refused(), never with "new", so the meaning is obvious
 * where it is used. A refusal always carries a sentence saying what to do
 * instead — golden rule 3, never a dead end.
 */
final class FeedingResult
{
    private function __construct(
        private bool $successful,
        private string $message,
    ) {
    }

    public static function success(string $message): self
    {
        return new self(true, $message);
    }

    public static function refused(string $message): self
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
