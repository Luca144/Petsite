<?php

declare(strict_types=1);

namespace Felkyo\Economy;

/**
 * The outcome of selling or discarding one item: it happened, or it did not.
 *
 * @package Felkyo\Economy
 *
 * Build one with sold(), discarded() or refused(), never with "new".
 *
 * EVERY OUTCOME CARRIES A MESSAGE, including the refusals, because golden rule 4
 * says an action always gets a visible, plain-language answer. Silence makes
 * people press the button again — which, for an action that takes something away,
 * is the last thing anybody wants.
 */
final class DisposalResult
{
    private function __construct(
        private bool $successful,
        private string $message,
        private int $currencyEarned,
    ) {
    }

    public static function sold(string $message, int $currencyEarned): self
    {
        return new self(true, $message, $currencyEarned);
    }

    public static function discarded(string $message): self
    {
        return new self(true, $message, 0);
    }

    public static function refused(string $message): self
    {
        return new self(false, $message, 0);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * How much the player earned. Zero for a discard, and zero for any refusal.
     */
    public function currencyEarned(): int
    {
        return $this->currencyEarned;
    }
}
