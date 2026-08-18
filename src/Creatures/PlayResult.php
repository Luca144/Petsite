<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * How a game with a creature went.
 *
 * @package Felkyo\Creatures
 *
 * Three outcomes, and note that only ONE of them is a refusal: you either guessed
 * right, guessed wrong, or there was no round to answer. Guessing wrong is not a
 * failure — the creature still had a game, still cheered up, and the message says
 * so. There is deliberately no outcome here that takes anything away.
 */
final class PlayResult
{
    private function __construct(
        private bool $played,
        private bool $won,
        private string $message,
    ) {
    }

    /**
     * The player guessed right.
     */
    public function playedAndWon(): bool
    {
        return $this->played && $this->won;
    }

    /**
     * A game happened, however it went. Used to decide whether to celebrate — and
     * the celebration plays for a loss too, because being played with is the good
     * thing that happened, not winning.
     */
    public function played(): bool
    {
        return $this->played;
    }

    public function message(): string
    {
        return $this->message;
    }

    public static function won(string $message): self
    {
        return new self(true, true, $message);
    }

    public static function lost(string $message): self
    {
        return new self(true, false, $message);
    }

    /**
     * Nothing happened: there was no round open, or it was for a different
     * creature. Not a telling-off — the message points at starting a new one.
     */
    public static function noRound(string $message): self
    {
        return new self(false, false, $message);
    }
}
