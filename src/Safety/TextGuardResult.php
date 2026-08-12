<?php

declare(strict_types=1);

namespace Felkyo\Safety;

/**
 * The outcome of checking a piece of player-written text.
 *
 * @package Felkyo\Safety
 *
 * Build one with accepted() or refused(), never with "new".
 *
 * An accepted result carries the CLEANED text — trimmed, ready to store — so a
 * caller cannot accidentally save the raw version after the guard has approved a
 * tidied one. Asking for the value is how you get the safe form.
 *
 * A refusal carries a message written for the player, in plain words, saying what
 * to change (golden rule 3 and 5). It never quotes the pattern that matched or
 * the word that was found: that is a free lesson in how to word it next time.
 */
final class TextGuardResult
{
    private function __construct(
        private bool $accepted,
        private string $value,
        private string $message,
    ) {
    }

    public static function accepted(string $cleanedValue): self
    {
        return new self(true, $cleanedValue, '');
    }

    public static function refused(string $message): self
    {
        return new self(false, '', $message);
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    /**
     * The cleaned text, ready to store. Empty for a refusal — there is nothing
     * safe to hand back, and returning the original would invite somebody to
     * store it anyway.
     */
    public function value(): string
    {
        return $this->value;
    }

    public function message(): string
    {
        return $this->message;
    }
}
