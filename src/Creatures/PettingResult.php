<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * The outcome of trying to pet a creature: it either worked, or it was on cooldown.
 *
 * @package Felkyo\Creatures
 *
 * Returning this small result object (rather than throwing for the ordinary
 * "you already petted it recently" case) keeps the controller's logic a simple
 * "did it work? show the message either way". Each result carries a short,
 * ready-to-show message.
 *
 * Build one with success() or onCooldown(), never with "new" — the named
 * shortcuts make the meaning obvious where they are used.
 */
final class PettingResult
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

    public static function onCooldown(string $message): self
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
