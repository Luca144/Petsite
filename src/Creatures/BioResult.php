<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * The outcome of trying to save a creature's bio: saved, or rejected with a reason.
 *
 * @package Felkyo\Creatures
 *
 * Build one with saved() or rejected(), never with "new". Each carries a
 * ready-to-show message.
 */
final class BioResult
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
